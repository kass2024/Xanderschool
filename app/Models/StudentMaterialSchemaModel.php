<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Student required materials — catalog, class assignments, per-student checks.
 */
class StudentMaterialSchemaModel extends Model
{
	protected $table = 'required_materials';
	protected $primaryKey = 'id';
	protected $returnType = 'array';
	protected $allowedFields = ['school_id', 'name', 'unit', 'sort_order', 'active'];
	protected $useTimestamps = true;

	/** @var bool */
	private static $schemaReady = false;

	public function ensureSchema()
	{
		if (self::$schemaReady) {
			return;
		}

		$db = \Config\Database::connect();

		$db->query("CREATE TABLE IF NOT EXISTS `required_materials` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`school_id` INT UNSIGNED NOT NULL,
			`name` VARCHAR(200) NOT NULL,
			`unit` VARCHAR(60) NOT NULL DEFAULT 'pcs',
			`sort_order` INT NOT NULL DEFAULT 0,
			`active` TINYINT(1) NOT NULL DEFAULT 1,
			`created_at` DATETIME NULL DEFAULT NULL,
			`updated_at` DATETIME NULL DEFAULT NULL,
			PRIMARY KEY (`id`),
			KEY `idx_rm_school` (`school_id`, `active`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

		$db->query("CREATE TABLE IF NOT EXISTS `class_required_materials` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`school_id` INT UNSIGNED NOT NULL,
			`class_id` INT UNSIGNED NOT NULL,
			`material_id` INT UNSIGNED NOT NULL,
			`academic_year` INT UNSIGNED NOT NULL,
			`quantity` DECIMAL(10,2) NOT NULL DEFAULT 0,
			`created_at` DATETIME NULL DEFAULT NULL,
			`updated_at` DATETIME NULL DEFAULT NULL,
			PRIMARY KEY (`id`),
			UNIQUE KEY `uniq_class_mat_year` (`school_id`, `class_id`, `material_id`, `academic_year`),
			KEY `idx_crm_class` (`class_id`, `academic_year`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

		$db->query("CREATE TABLE IF NOT EXISTS `student_material_checks` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`school_id` INT UNSIGNED NOT NULL,
			`student_id` INT UNSIGNED NOT NULL,
			`class_id` INT UNSIGNED NOT NULL,
			`material_id` INT UNSIGNED NOT NULL,
			`academic_year` INT UNSIGNED NOT NULL,
			`quantity_required` DECIMAL(10,2) NOT NULL DEFAULT 0,
			`quantity_brought` DECIMAL(10,2) NOT NULL DEFAULT 0,
			`notes` VARCHAR(500) NULL DEFAULT NULL,
			`checked_by` INT UNSIGNED NULL DEFAULT NULL,
			`checked_at` DATETIME NULL DEFAULT NULL,
			`updated_at` DATETIME NULL DEFAULT NULL,
			PRIMARY KEY (`id`),
			UNIQUE KEY `uniq_student_mat_year` (`student_id`, `material_id`, `academic_year`),
			KEY `idx_smc_class` (`class_id`, `academic_year`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

		self::$schemaReady = true;
	}

	public function listMaterials(int $schoolId, bool $activeOnly = true): array
	{
		$this->ensureSchema();
		$b = $this->where('school_id', $schoolId);
		if ($activeOnly) {
			$b->where('active', 1);
		}
		return $b->orderBy('sort_order', 'ASC')->orderBy('name', 'ASC')->findAll();
	}

	public function getClassAssignments(int $schoolId, int $classId, int $yearId): array
	{
		$this->ensureSchema();
		$db = \Config\Database::connect();
		return $db->table('class_required_materials crm')
			->select('crm.material_id, crm.quantity, rm.name, rm.unit')
			->join('required_materials rm', 'rm.id = crm.material_id')
			->where('crm.school_id', $schoolId)
			->where('crm.class_id', $classId)
			->where('crm.academic_year', $yearId)
			->where('crm.quantity >', 0)
			->where('rm.active', 1)
			->orderBy('rm.sort_order', 'ASC')
			->orderBy('rm.name', 'ASC')
			->get()->getResultArray();
	}

	public function saveClassAssignments(int $schoolId, int $classId, int $yearId, array $rows): void
	{
		$this->ensureSchema();
		$db = \Config\Database::connect();
		$now = date('Y-m-d H:i:s');

		$db->table('class_required_materials')
			->where('school_id', $schoolId)
			->where('class_id', $classId)
			->where('academic_year', $yearId)
			->delete();

		foreach ($rows as $row) {
			$matId = (int) ($row['material_id'] ?? 0);
			$qty = (float) ($row['quantity'] ?? 0);
			if ($matId <= 0 || $qty <= 0) {
				continue;
			}
			$db->table('class_required_materials')->insert([
				'school_id' => $schoolId,
				'class_id' => $classId,
				'material_id' => $matId,
				'academic_year' => $yearId,
				'quantity' => $qty,
				'created_at' => $now,
				'updated_at' => $now,
			]);
		}
	}

	/**
	 * Apply the same material quantities to many classes (same catalog items / qty).
	 *
	 * @param int[] $classIds
	 */
	public function saveClassAssignmentsForClasses(int $schoolId, array $classIds, int $yearId, array $rows): int
	{
		$saved = 0;
		foreach ($classIds as $classId) {
			$classId = (int) $classId;
			if ($classId <= 0) {
				continue;
			}
			$this->saveClassAssignments($schoolId, $classId, $yearId, $rows);
			$saved++;
		}
		return $saved;
	}

	public function getStudentChecklist(int $schoolId, int $studentId, int $classId, int $yearId): array
	{
		$this->ensureSchema();
		$db = \Config\Database::connect();

		$assignments = $this->getClassAssignments($schoolId, $classId, $yearId);
		if (empty($assignments)) {
			return [];
		}

		$checks = $db->table('student_material_checks')
			->where('student_id', $studentId)
			->where('academic_year', $yearId)
			->get()->getResultArray();
		$byMat = [];
		$staffIds = [];
		foreach ($checks as $c) {
			$byMat[(int) $c['material_id']] = $c;
			$cb = (int) ($c['checked_by'] ?? 0);
			if ($cb > 0) {
				$staffIds[$cb] = $cb;
			}
		}
		$staffMeta = $this->resolveStaffMeta(array_values($staffIds));

		$list = [];
		foreach ($assignments as $a) {
			$mid = (int) $a['material_id'];
			$req = (float) $a['quantity'];
			$brought = isset($byMat[$mid]) ? (float) $byMat[$mid]['quantity_brought'] : 0;
			$missing = max(0, $req - $brought);
			$status = 'missing';
			if ($brought >= $req && $req > 0) {
				$status = 'complete';
			} elseif ($brought > 0) {
				$status = 'partial';
			}
			$cb = isset($byMat[$mid]) ? (int) ($byMat[$mid]['checked_by'] ?? 0) : 0;
			$meta = $cb > 0 ? ($staffMeta[$cb] ?? null) : null;
			$list[] = [
				'material_id' => $mid,
				'name' => $a['name'],
				'unit' => $a['unit'],
				'quantity_required' => $req,
				'quantity_brought' => $brought,
				'quantity_missing' => $missing,
				'status' => $status,
				'notes' => $byMat[$mid]['notes'] ?? '',
				'check_id' => isset($byMat[$mid]) ? (int) $byMat[$mid]['id'] : 0,
				'checked_by' => $cb > 0 ? $cb : null,
				'checked_at' => isset($byMat[$mid]['checked_at']) ? (string) $byMat[$mid]['checked_at'] : null,
				'checker_name' => $meta['name'] ?? '',
				'checker_post' => $meta['post'] ?? '',
			];
		}
		return $list;
	}

	/**
	 * Latest check activity for a student (who / when).
	 *
	 * @param list<array> $checklist
	 * @return array{checked_by:?int,checked_at:?string,checker_name:string,checker_post:string}|null
	 */
	public function latestCheckMetaFromChecklist(array $checklist): ?array
	{
		$best = null;
		$bestTs = '';
		foreach ($checklist as $row) {
			$at = (string) ($row['checked_at'] ?? '');
			if ($at === '') {
				continue;
			}
			if ($best === null || strcmp($at, $bestTs) > 0) {
				$bestTs = $at;
				$best = [
					'checked_by' => isset($row['checked_by']) && $row['checked_by'] ? (int) $row['checked_by'] : null,
					'checked_at' => $at,
					'checker_name' => (string) ($row['checker_name'] ?? ''),
					'checker_post' => (string) ($row['checker_post'] ?? ''),
				];
			}
		}
		return $best;
	}

	/**
	 * @param list<int> $staffIds
	 * @return array<int, array{name:string,post:string}>
	 */
	public function resolveStaffMeta(array $staffIds): array
	{
		$staffIds = array_values(array_unique(array_filter(array_map('intval', $staffIds))));
		if ($staffIds === []) {
			return [];
		}
		$db = \Config\Database::connect();
		$rows = $db->table('staffs s')
			->select('s.id, CONCAT(s.fname," ",s.lname) AS name, p.title AS post_title')
			->join('posts p', 'p.id = s.post', 'left')
			->whereIn('s.id', $staffIds)
			->get()->getResultArray();
		$out = [];
		foreach ($rows as $r) {
			$out[(int) $r['id']] = [
				'name' => trim((string) ($r['name'] ?? '')),
				'post' => trim((string) ($r['post_title'] ?? '')),
			];
		}
		return $out;
	}

	/**
	 * Recent material-check activity for a class (for accountant / headmaster audit).
	 *
	 * @return list<array>
	 */
	public function getClassRecentActivity(int $schoolId, int $classId, int $yearId, int $limit = 25): array
	{
		$this->ensureSchema();
		$limit = max(1, min(50, $limit));
		$db = \Config\Database::connect();
		$rows = $db->query(
			'SELECT smc.student_id, smc.checked_by, smc.checked_at,
				MAX(CONCAT(st.fname, " ", st.lname)) AS student_name,
				MAX(st.regno) AS regno,
				MAX(CONCAT(sf.fname, " ", sf.lname)) AS checker_name,
				MAX(p.title) AS checker_post,
				COUNT(*) AS items_checked
			FROM student_material_checks smc
			INNER JOIN students st ON st.id = smc.student_id
			LEFT JOIN staffs sf ON sf.id = smc.checked_by
			LEFT JOIN posts p ON p.id = sf.post
			WHERE smc.school_id = ?
				AND smc.class_id = ?
				AND smc.academic_year = ?
				AND smc.checked_by IS NOT NULL
				AND smc.checked_at IS NOT NULL
			GROUP BY smc.student_id, smc.checked_by, smc.checked_at
			ORDER BY smc.checked_at DESC
			LIMIT ' . (int) $limit,
			[$schoolId, $classId, $yearId]
		)->getResultArray();

		$out = [];
		foreach ($rows as $r) {
			$out[] = [
				'student_id' => (int) $r['student_id'],
				'student_name' => (string) ($r['student_name'] ?? ''),
				'regno' => (string) ($r['regno'] ?? ''),
				'checked_by' => (int) ($r['checked_by'] ?? 0),
				'checked_at' => (string) ($r['checked_at'] ?? ''),
				'checker_name' => (string) ($r['checker_name'] ?? ''),
				'checker_post' => (string) ($r['checker_post'] ?? ''),
				'items_checked' => (int) ($r['items_checked'] ?? 0),
			];
		}
		return $out;
	}

	/**
	 * School-wide live search for material check (name / regno + class).
	 *
	 * @param list<int>|null $allowedClassIds null = all classes
	 * @return list<array>
	 */
	public function searchStudents(int $schoolId, int $yearId, string $query, ?array $allowedClassIds = null, int $limit = 20): array
	{
		$this->ensureSchema();
		$q = trim($query);
		if ($q === '' || strlen($q) < 2) {
			return [];
		}
		$limit = max(1, min(40, $limit));
		$db = \Config\Database::connect();
		$like = '%' . $q . '%';

		$b = $db->table('students s')
			->select('s.id, s.regno, s.fname, s.lname, c.id AS class_id, c.title AS class_title,
				l.title AS level_name, d.code AS dept_code, h.name AS hostel_name')
			->join(
				'class_records cr',
				'cr.student = s.id AND cr.year = ' . (int) $yearId . ' AND cr.status = 1',
				'inner'
			)
			->join('classes c', 'c.id = cr.class', 'inner')
			->join('levels l', 'l.id = c.level', 'left')
			->join('departments d', 'd.id = c.department', 'left')
			->join(
				'hostel_allocations ha',
				'ha.student_id = s.id AND ha.academic_year = ' . (int) $yearId . ' AND ha.school_id = ' . (int) $schoolId,
				'left'
			)
			->join('hostels h', 'h.id = ha.hostel_id', 'left')
			->where('s.school_id', $schoolId)
			->where('s.status', 1)
			->groupStart()
				->like('s.regno', $q)
				->orLike('s.fname', $q)
				->orLike('s.lname', $q)
				->orWhere("CONCAT(IFNULL(s.fname,''), ' ', IFNULL(s.lname,'')) LIKE " . $db->escape($like), null, false)
			->groupEnd();

		if ($allowedClassIds !== null) {
			$ids = array_values(array_filter(array_map('intval', $allowedClassIds)));
			if ($ids === []) {
				return [];
			}
			$b->whereIn('c.id', $ids);
		}

		$rows = $b->orderBy('s.fname', 'ASC')->orderBy('s.lname', 'ASC')->limit($limit * 2)->get()->getResultArray();
		$out = [];
		$seen = [];
		foreach ($rows as $row) {
			$sid = (int) ($row['id'] ?? 0);
			if ($sid <= 0 || isset($seen[$sid])) {
				continue;
			}
			$seen[$sid] = true;
			$label = trim(preg_replace(
				'/\s+/',
				' ',
				trim(($row['level_name'] ?? '') . ' ' . ($row['dept_code'] ?? '') . ' ' . ($row['class_title'] ?? ''))
			) ?: '');
			$out[] = [
				'id' => $sid,
				'regno' => (string) ($row['regno'] ?? ''),
				'name' => trim(($row['fname'] ?? '') . ' ' . ($row['lname'] ?? '')),
				'class_id' => (int) ($row['class_id'] ?? 0),
				'class_label' => $label !== '' ? $label : 'No class',
				'hostel_name' => (string) ($row['hostel_name'] ?? ''),
			];
			if (count($out) >= $limit) {
				break;
			}
		}
		return $out;
	}

	public function summarizeChecklist(array $materials): array
	{
		$summary = ['complete' => 0, 'partial' => 0, 'missing' => 0, 'total' => count($materials)];
		foreach ($materials as $m) {
			if (($m['status'] ?? '') === 'complete') {
				$summary['complete']++;
			} elseif (($m['status'] ?? '') === 'partial') {
				$summary['partial']++;
			} else {
				$summary['missing']++;
			}
		}
		$summary['overall'] = 'none';
		if ($summary['total'] > 0) {
			if ($summary['complete'] === $summary['total']) {
				$summary['overall'] = 'complete';
			} elseif ($summary['complete'] > 0 || $summary['partial'] > 0) {
				$summary['overall'] = 'partial';
			} else {
				$summary['overall'] = 'missing';
			}
		}
		return $summary;
	}

	public function getClassOverview(int $schoolId, int $classId, int $yearId): array
	{
		$this->ensureSchema();
		$db = \Config\Database::connect();
		$assignments = $this->getClassAssignments($schoolId, $classId, $yearId);
		$materialCount = count($assignments);
		$materialIds = array_map(static fn ($a) => (int) $a['material_id'], $assignments);

		$students = $db->table('class_records cr')
			->select('students.id, students.regno, CONCAT(students.fname," ",students.lname) AS name,
				ha.hostel_id, h.name AS hostel_name')
			->join('students', 'students.id = cr.student')
			->join(
				'hostel_allocations ha',
				'ha.student_id = students.id AND ha.academic_year = ' . (int) $yearId . ' AND ha.school_id = ' . (int) $schoolId,
				'left'
			)
			->join('hostels h', 'h.id = ha.hostel_id', 'left')
			->where('cr.class', $classId)
			->where('cr.year', $yearId)
			->where('students.school_id', $schoolId)
			->where('students.status', 1)
			->orderBy('students.fname', 'ASC')
			->orderBy('students.lname', 'ASC')
			->get()->getResultArray();

		$classKpi = [
			'total' => count($students),
			'complete' => 0,
			'partial' => 0,
			'missing' => 0,
			'unchecked' => 0,
		];

		if ($materialCount === 0) {
			$list = [];
			foreach ($students as $s) {
				$list[] = [
					'id' => (int) $s['id'],
					'regno' => (string) $s['regno'],
					'name' => (string) $s['name'],
					'hostel_id' => isset($s['hostel_id']) && $s['hostel_id'] !== null ? (int) $s['hostel_id'] : null,
					'hostel_name' => (string) ($s['hostel_name'] ?? ''),
					'overall' => 'none',
					'complete' => 0,
					'partial' => 0,
					'missing' => 0,
					'material_count' => 0,
					'missing_summary' => '—',
					'items' => [],
				];
			}
			$classKpi['unchecked'] = count($students);
			return [
				'students' => $list,
				'class_kpi' => $classKpi,
				'material_count' => 0,
				'materials' => [],
				'item_totals' => [],
				'hostel_totals' => $this->buildHostelTotals([], $list),
				'recent_activity' => $this->getClassRecentActivity($schoolId, $classId, $yearId),
			];
		}

		$studentIds = array_map(static fn ($s) => (int) $s['id'], $students);
		$checks = [];
		$staffIds = [];
		if (!empty($studentIds)) {
			$rows = $db->table('student_material_checks')
				->whereIn('student_id', $studentIds)
				->where('academic_year', $yearId)
				->whereIn('material_id', $materialIds)
				->get()->getResultArray();
			foreach ($rows as $c) {
				$checks[(int) $c['student_id']][(int) $c['material_id']] = $c;
				$cb = (int) ($c['checked_by'] ?? 0);
				if ($cb > 0) {
					$staffIds[$cb] = $cb;
				}
			}
		}
		$staffMeta = $this->resolveStaffMeta(array_values($staffIds));

		$list = [];
		foreach ($students as $s) {
			$sid = (int) $s['id'];
			$complete = $partial = $missing = 0;
			$items = [];
			$missingParts = [];
			$lastAt = '';
			$lastBy = null;
			foreach ($assignments as $a) {
				$mid = (int) $a['material_id'];
				$req = (float) $a['quantity'];
				$brought = isset($checks[$sid][$mid]) ? (float) $checks[$sid][$mid]['quantity_brought'] : 0;
				$missingQty = max(0, $req - $brought);
				$itemStatus = 'missing';
				if ($brought >= $req && $req > 0) {
					$complete++;
					$itemStatus = 'complete';
				} elseif ($brought > 0) {
					$partial++;
					$itemStatus = 'partial';
				} else {
					$missing++;
				}
				$items[] = [
					'material_id' => $mid,
					'name' => $a['name'],
					'unit' => $a['unit'],
					'required' => $req,
					'brought' => $brought,
					'missing_qty' => $missingQty,
					'status' => $itemStatus,
				];
				if ($itemStatus !== 'complete') {
					$missingParts[] = $a['name'] . ' (' . $missingQty . ' ' . $a['unit'] . ')';
				}
				if (isset($checks[$sid][$mid]['checked_at'])) {
					$at = (string) $checks[$sid][$mid]['checked_at'];
					if ($at !== '' && ($lastAt === '' || strcmp($at, $lastAt) > 0)) {
						$lastAt = $at;
						$cb = (int) ($checks[$sid][$mid]['checked_by'] ?? 0);
						$lastBy = $cb > 0 ? $cb : null;
					}
				}
			}

			$overall = 'missing';
			if ($complete === $materialCount) {
				$overall = 'complete';
			} elseif ($complete > 0 || $partial > 0) {
				$overall = 'partial';
			} elseif (empty($checks[$sid])) {
				$overall = 'unchecked';
			}

			if ($overall === 'complete') {
				$classKpi['complete']++;
			} elseif ($overall === 'partial') {
				$classKpi['partial']++;
			} elseif ($overall === 'unchecked') {
				$classKpi['unchecked']++;
			} else {
				$classKpi['missing']++;
			}

			$meta = $lastBy ? ($staffMeta[$lastBy] ?? null) : null;
			$list[] = [
				'id' => $sid,
				'regno' => (string) $s['regno'],
				'name' => (string) $s['name'],
				'hostel_id' => isset($s['hostel_id']) && $s['hostel_id'] !== null ? (int) $s['hostel_id'] : null,
				'hostel_name' => (string) ($s['hostel_name'] ?? ''),
				'overall' => $overall,
				'complete' => $complete,
				'partial' => $partial,
				'missing' => $missing,
				'material_count' => $materialCount,
				'missing_summary' => $overall === 'complete' ? 'All supplied' : (implode(', ', $missingParts) ?: '—'),
				'items' => $items,
				'checked_by' => $lastBy,
				'checked_at' => $lastAt !== '' ? $lastAt : null,
				'checker_name' => $meta['name'] ?? '',
				'checker_post' => $meta['post'] ?? '',
			];
		}

		$materials = array_map(static fn ($a) => [
			'material_id' => (int) $a['material_id'],
			'name' => $a['name'],
			'unit' => $a['unit'],
			'quantity' => (float) $a['quantity'],
		], $assignments);

		$itemTotals = $this->buildItemTotals($assignments, $list);
		$hostelTotals = $this->buildHostelTotals($assignments, $list);

		return [
			'students' => $list,
			'class_kpi' => $classKpi,
			'material_count' => $materialCount,
			'materials' => $materials,
			'item_totals' => $itemTotals,
			'hostel_totals' => $hostelTotals,
			'recent_activity' => $this->getClassRecentActivity($schoolId, $classId, $yearId),
		];
	}

	/**
	 * Per-item totals across the class.
	 *
	 * @param list<array> $assignments
	 * @param list<array> $students
	 * @return list<array>
	 */
	public function buildItemTotals(array $assignments, array $students): array
	{
		$n = count($students);
		$out = [];
		foreach ($assignments as $a) {
			$mid = (int) $a['material_id'];
			$reqEach = (float) $a['quantity'];
			$row = [
				'material_id' => $mid,
				'name' => (string) $a['name'],
				'unit' => (string) $a['unit'],
				'qty_each' => $reqEach,
				'students' => $n,
				'required_total' => $reqEach * $n,
				'brought_total' => 0.0,
				'missing_total' => 0.0,
				'students_complete' => 0,
				'students_partial' => 0,
				'students_missing' => 0,
			];
			foreach ($students as $st) {
				foreach (($st['items'] ?? []) as $it) {
					if ((int) ($it['material_id'] ?? 0) !== $mid) {
						continue;
					}
					$row['brought_total'] += (float) ($it['brought'] ?? 0);
					$row['missing_total'] += (float) ($it['missing_qty'] ?? 0);
					$status = (string) ($it['status'] ?? 'missing');
					if ($status === 'complete') {
						$row['students_complete']++;
					} elseif ($status === 'partial') {
						$row['students_partial']++;
					} else {
						$row['students_missing']++;
					}
				}
			}
			$out[] = $row;
		}
		return $out;
	}

	/**
	 * Per-hostel item totals for students in this class.
	 *
	 * @param list<array> $assignments
	 * @param list<array> $students
	 * @return list<array>
	 */
	public function buildHostelTotals(array $assignments, array $students): array
	{
		$groups = [];
		foreach ($students as $st) {
			$hid = $st['hostel_id'] ?? null;
			$key = $hid === null || $hid === '' ? 'none' : (string) (int) $hid;
			if (!isset($groups[$key])) {
				$groups[$key] = [
					'hostel_id' => $key === 'none' ? null : (int) $key,
					'hostel_name' => $key === 'none' ? 'Not allocated' : (string) ($st['hostel_name'] ?? 'Hostel'),
					'students' => [],
				];
			}
			if ($key !== 'none' && $groups[$key]['hostel_name'] === 'Hostel' && !empty($st['hostel_name'])) {
				$groups[$key]['hostel_name'] = (string) $st['hostel_name'];
			}
			$groups[$key]['students'][] = $st;
		}

		$out = [];
		foreach ($groups as $g) {
			$out[] = [
				'hostel_id' => $g['hostel_id'],
				'hostel_name' => $g['hostel_name'],
				'student_count' => count($g['students']),
				'item_totals' => $this->buildItemTotals($assignments, $g['students']),
			];
		}
		usort($out, static function ($a, $b) {
			if ($a['hostel_id'] === null) {
				return 1;
			}
			if ($b['hostel_id'] === null) {
				return -1;
			}
			return strcasecmp((string) $a['hostel_name'], (string) $b['hostel_name']);
		});
		return $out;
	}

	public function filterClassStudentsByStatus(array $students, string $filter): array
	{
		$filter = strtolower(trim($filter));
		if ($filter === '' || $filter === 'all') {
			return $students;
		}
		return array_values(array_filter($students, static function ($s) use ($filter) {
			$overall = (string) ($s['overall'] ?? '');
			if ($filter === 'unchecked') {
				return $overall === 'unchecked' || $overall === 'none';
			}
			return $overall === $filter;
		}));
	}

	public function saveStudentChecks(int $schoolId, int $studentId, int $classId, int $yearId, int $staffId, array $items): int
	{
		$this->ensureSchema();
		$db = \Config\Database::connect();
		$now = date('Y-m-d H:i:s');
		$saved = 0;

		foreach ($items as $item) {
			$matId = (int) ($item['material_id'] ?? 0);
			$req = (float) ($item['quantity_required'] ?? 0);
			$brought = max(0, (float) ($item['quantity_brought'] ?? 0));
			$notes = trim((string) ($item['notes'] ?? ''));
			if ($matId <= 0) {
				continue;
			}

			$existing = $db->table('student_material_checks')
				->where('student_id', $studentId)
				->where('material_id', $matId)
				->where('academic_year', $yearId)
				->get(1)->getRowArray();

			$payload = [
				'school_id' => $schoolId,
				'student_id' => $studentId,
				'class_id' => $classId,
				'material_id' => $matId,
				'academic_year' => $yearId,
				'quantity_required' => $req,
				'quantity_brought' => $brought,
				'notes' => $notes !== '' ? $notes : null,
				'checked_by' => $staffId > 0 ? $staffId : null,
				'checked_at' => $now,
				'updated_at' => $now,
			];

			if ($existing) {
				$db->table('student_material_checks')->where('id', $existing['id'])->update($payload);
			} else {
				$db->table('student_material_checks')->insert($payload);
			}
			$saved++;
		}
		return $saved;
	}
}
