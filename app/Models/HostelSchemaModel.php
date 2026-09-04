<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * School hostels (name, beds, gender) and student allocations.
 * Day scholars (studying_mode != 0) must never be allocated.
 */
class HostelSchemaModel extends Model
{
	protected $table = 'hostels';
	protected $primaryKey = 'id';
	protected $returnType = 'array';
	protected $allowedFields = ['school_id', 'name', 'max_beds', 'gender', 'sort_order', 'active'];
	protected $useTimestamps = true;

	/** @var bool */
	private static $schemaReady = false;

	/** studying_mode: 0 = boarding, anything else = day */
	public const MODE_BOARDING = 0;

	public function ensureSchema(): void
	{
		if (self::$schemaReady) {
			return;
		}

		$db = \Config\Database::connect();

		$db->query("CREATE TABLE IF NOT EXISTS `hostels` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`school_id` INT UNSIGNED NOT NULL,
			`name` VARCHAR(160) NOT NULL,
			`max_beds` INT UNSIGNED NOT NULL DEFAULT 1,
			`gender` CHAR(1) NOT NULL DEFAULT 'M',
			`sort_order` INT NOT NULL DEFAULT 0,
			`active` TINYINT(1) NOT NULL DEFAULT 1,
			`created_at` DATETIME NULL DEFAULT NULL,
			`updated_at` DATETIME NULL DEFAULT NULL,
			PRIMARY KEY (`id`),
			KEY `idx_hostels_school` (`school_id`, `active`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

		$db->query("CREATE TABLE IF NOT EXISTS `hostel_allocations` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`school_id` INT UNSIGNED NOT NULL,
			`hostel_id` INT UNSIGNED NOT NULL,
			`student_id` INT UNSIGNED NOT NULL,
			`academic_year` INT UNSIGNED NOT NULL,
			`allocated_by` INT UNSIGNED NULL DEFAULT NULL,
			`created_at` DATETIME NULL DEFAULT NULL,
			`updated_at` DATETIME NULL DEFAULT NULL,
			PRIMARY KEY (`id`),
			UNIQUE KEY `uniq_hostel_student_year` (`school_id`, `student_id`, `academic_year`),
			KEY `idx_ha_hostel` (`hostel_id`, `academic_year`),
			KEY `idx_ha_year` (`school_id`, `academic_year`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

		$db->query("CREATE TABLE IF NOT EXISTS `hostel_settings` (
			`school_id` INT UNSIGNED NOT NULL,
			`separate_by_level` TINYINT(1) NOT NULL DEFAULT 0,
			`updated_at` DATETIME NULL DEFAULT NULL,
			PRIMARY KEY (`school_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

		self::$schemaReady = true;
	}

	/**
	 * @return array{separate_by_level:bool}
	 */
	public function getSchoolSettings(int $schoolId): array
	{
		$this->ensureSchema();
		$db = \Config\Database::connect();
		$row = $db->table('hostel_settings')->where('school_id', $schoolId)->get(1)->getRowArray();
		return [
			'separate_by_level' => (int) ($row['separate_by_level'] ?? 0) === 1,
		];
	}

	public function saveSchoolSettings(int $schoolId, array $settings): void
	{
		$this->ensureSchema();
		$db = \Config\Database::connect();
		$payload = [
			'school_id' => $schoolId,
			'separate_by_level' => !empty($settings['separate_by_level']) ? 1 : 0,
			'updated_at' => date('Y-m-d H:i:s'),
		];
		$exists = $db->table('hostel_settings')->where('school_id', $schoolId)->countAllResults();
		if ($exists) {
			$db->table('hostel_settings')->where('school_id', $schoolId)->update($payload);
		} else {
			$db->table('hostel_settings')->insert($payload);
		}
	}

	/**
	 * Active class level for a student in a year.
	 *
	 * @return array{level_id:int,level_title:string}|null
	 */
	public function getStudentLevel(int $schoolId, int $studentId, int $yearId): ?array
	{
		$db = \Config\Database::connect();
		$row = $db->table('class_records cr')
			->select('l.id AS level_id, l.title AS level_title')
			->join('classes c', 'c.id = cr.class')
			->join('levels l', 'l.id = c.level', 'left')
			->join('students s', 's.id = cr.student')
			->where('cr.student', $studentId)
			->where('cr.year', $yearId)
			->where('cr.status', 1)
			->where('s.school_id', $schoolId)
			->orderBy('cr.id', 'DESC')
			->get(1)->getRowArray();
		if (!$row || empty($row['level_id'])) {
			return null;
		}
		return [
			'level_id' => (int) $row['level_id'],
			'level_title' => (string) ($row['level_title'] ?? ''),
		];
	}

	/**
	 * Distinct levels already living in a hostel for the year.
	 *
	 * @return list<array{level_id:int,level_title:string}>
	 */
	public function getHostelResidentLevels(int $schoolId, int $hostelId, int $yearId): array
	{
		$db = \Config\Database::connect();
		$rows = $db->table('hostel_allocations ha')
			->select('l.id AS level_id, l.title AS level_title')
			->join('students s', 's.id = ha.student_id')
			->join(
				'class_records cr',
				'cr.student = s.id AND cr.year = ' . (int) $yearId . ' AND cr.status = 1',
				'left'
			)
			->join('classes c', 'c.id = cr.class', 'left')
			->join('levels l', 'l.id = c.level', 'left')
			->where('ha.school_id', $schoolId)
			->where('ha.hostel_id', $hostelId)
			->where('ha.academic_year', $yearId)
			->where('l.id IS NOT NULL', null, false)
			->groupBy('l.id, l.title')
			->orderBy('l.title', 'ASC')
			->get()->getResultArray();

		$out = [];
		foreach ($rows as $row) {
			$lid = (int) ($row['level_id'] ?? 0);
			if ($lid > 0) {
				$out[] = [
					'level_id' => $lid,
					'level_title' => (string) ($row['level_title'] ?? ''),
				];
			}
		}
		return $out;
	}

	/**
	 * @return array{ok:bool,error?:string}
	 */
	public function assertLevelCompatible(
		int $schoolId,
		int $hostelId,
		int $studentId,
		int $yearId
	): array {
		$settings = $this->getSchoolSettings($schoolId);
		if (empty($settings['separate_by_level'])) {
			return ['ok' => true];
		}

		$studentLevel = $this->getStudentLevel($schoolId, $studentId, $yearId);
		if ($studentLevel === null) {
			return ['ok' => true];
		}

		$residentLevels = $this->getHostelResidentLevels($schoolId, $hostelId, $yearId);
		if ($residentLevels === []) {
			return ['ok' => true];
		}

		foreach ($residentLevels as $lvl) {
			if ((int) $lvl['level_id'] !== (int) $studentLevel['level_id']) {
				$names = array_values(array_unique(array_map(static function ($r) {
					return (string) ($r['level_title'] ?? '');
				}, $residentLevels)));
				$names = array_values(array_filter($names));
				$hostelLevels = $names !== [] ? implode(', ', $names) : 'another level';
				$studentTitle = $studentLevel['level_title'] !== '' ? $studentLevel['level_title'] : 'this level';
				return [
					'ok' => false,
					'error' => "Level mixing is blocked: this hostel already has {$hostelLevels} students. "
						. "Cannot add a {$studentTitle} student. Change the setting in Settings → Hostels to allow mixing.",
				];
			}
		}
		return ['ok' => true];
	}

	public function listHostels(int $schoolId, bool $activeOnly = true): array
	{
		$this->ensureSchema();
		$b = $this->where('school_id', $schoolId);
		if ($activeOnly) {
			$b->where('active', 1);
		}
		return $b->orderBy('sort_order', 'ASC')->orderBy('name', 'ASC')->findAll();
	}

	public function listHostelsWithOccupancy(int $schoolId, int $yearId): array
	{
		$hostels = $this->listHostels($schoolId, true);
		if ($hostels === []) {
			return [];
		}
		$db = \Config\Database::connect();
		$counts = $db->table('hostel_allocations')
			->select('hostel_id, COUNT(*) AS occupied')
			->where('school_id', $schoolId)
			->where('academic_year', $yearId)
			->groupBy('hostel_id')
			->get()->getResultArray();
		$byId = [];
		foreach ($counts as $c) {
			$byId[(int) $c['hostel_id']] = (int) $c['occupied'];
		}
		foreach ($hostels as &$h) {
			$hid = (int) $h['id'];
			$h['occupied'] = $byId[$hid] ?? 0;
			$h['free_beds'] = max(0, (int) $h['max_beds'] - $h['occupied']);
			$h['gender_label'] = strtoupper((string) $h['gender']) === 'F' ? 'Female' : 'Male';
			$levels = $this->getHostelResidentLevels($schoolId, $hid, $yearId);
			$h['resident_levels'] = $levels;
			$h['level_label'] = $levels === []
				? ''
				: implode(', ', array_values(array_unique(array_map(static function ($l) {
					return (string) ($l['level_title'] ?? '');
				}, $levels))));
		}
		unset($h);
		return $hostels;
	}

	public function normalizeGender(string $gender): string
	{
		$g = strtoupper(trim($gender));
		return $g === 'F' || $g === 'FEMALE' ? 'F' : 'M';
	}

	/**
	 * Normalize student sex from DB/UI variants (M, F, Male, Female, …) to M|F|''.
	 */
	public function normalizeStudentSex($sex): string
	{
		$g = strtoupper(trim((string) $sex));
		if ($g === '') {
			return '';
		}
		if ($g === 'F' || $g === 'FEMALE' || $g === 'GIRL' || $g === 'WOMAN') {
			return 'F';
		}
		if ($g === 'M' || $g === 'MALE' || $g === 'BOY' || $g === 'MAN') {
			return 'M';
		}
		return '';
	}

	public function isBoardingStudent(array $student): bool
	{
		return (int) ($student['studying_mode'] ?? 1) === self::MODE_BOARDING;
	}

	public function getStudentAllocation(int $schoolId, int $studentId, int $yearId): ?array
	{
		$this->ensureSchema();
		$db = \Config\Database::connect();
		$row = $db->table('hostel_allocations ha')
			->select('ha.*, h.name AS hostel_name, h.gender AS hostel_gender, h.max_beds')
			->join('hostels h', 'h.id = ha.hostel_id')
			->where('ha.school_id', $schoolId)
			->where('ha.student_id', $studentId)
			->where('ha.academic_year', $yearId)
			->get(1)->getRowArray();
		return $row ?: null;
	}

	/**
	 * @return array{ok:bool,error?:string,allocation_id?:int}
	 */
	public function allocateStudent(
		int $schoolId,
		int $hostelId,
		int $studentId,
		int $yearId,
		int $staffId
	): array {
		$this->ensureSchema();
		$db = \Config\Database::connect();

		$hostel = $this->where('school_id', $schoolId)->where('active', 1)->find($hostelId);
		if (!$hostel) {
			return ['ok' => false, 'error' => 'Hostel not found.'];
		}

		$student = $db->table('students')
			->select('id, fname, lname, regno, sex, studying_mode, school_id, status')
			->where('id', $studentId)
			->where('school_id', $schoolId)
			->get(1)->getRowArray();
		if (!$student || (int) ($student['status'] ?? 0) !== 1) {
			return ['ok' => false, 'error' => 'Student not found.'];
		}
		if (!$this->isBoardingStudent($student)) {
			return ['ok' => false, 'error' => 'Day students cannot be allocated to a hostel.'];
		}

		$sex = $this->normalizeStudentSex($student['sex'] ?? '');
		$hostelGender = $this->normalizeGender((string) $hostel['gender']);
		if ($sex !== '' && $sex !== $hostelGender) {
			return [
				'ok' => false,
				'error' => 'Student gender does not match this hostel (' . ($hostelGender === 'F' ? 'Female' : 'Male') . ').',
			];
		}

		$existing = $this->getStudentAllocation($schoolId, $studentId, $yearId);
		if ($existing && (int) $existing['hostel_id'] === $hostelId) {
			return ['ok' => true, 'allocation_id' => (int) $existing['id']];
		}

		$occupied = (int) $db->table('hostel_allocations')
			->where('hostel_id', $hostelId)
			->where('academic_year', $yearId)
			->countAllResults();
		// If reassigning from another hostel, bed frees on that hostel; still check target capacity
		$willOccupyNew = !$existing || (int) $existing['hostel_id'] !== $hostelId;
		if ($willOccupyNew && $occupied >= (int) $hostel['max_beds']) {
			return ['ok' => false, 'error' => 'Hostel is full (max ' . (int) $hostel['max_beds'] . ' beds).'];
		}

		$levelCheck = $this->assertLevelCompatible($schoolId, $hostelId, $studentId, $yearId);
		if (!$levelCheck['ok']) {
			return ['ok' => false, 'error' => $levelCheck['error'] ?? 'Level mixing is not allowed in this hostel.'];
		}

		$now = date('Y-m-d H:i:s');
		$payload = [
			'school_id' => $schoolId,
			'hostel_id' => $hostelId,
			'student_id' => $studentId,
			'academic_year' => $yearId,
			'allocated_by' => $staffId > 0 ? $staffId : null,
			'updated_at' => $now,
		];

		if ($existing) {
			$db->table('hostel_allocations')->where('id', (int) $existing['id'])->update($payload);
			return ['ok' => true, 'allocation_id' => (int) $existing['id']];
		}

		$payload['created_at'] = $now;
		$db->table('hostel_allocations')->insert($payload);
		return ['ok' => true, 'allocation_id' => (int) $db->insertID()];
	}

	public function unallocateStudent(int $schoolId, int $studentId, int $yearId): bool
	{
		$this->ensureSchema();
		$db = \Config\Database::connect();
		$db->table('hostel_allocations')
			->where('school_id', $schoolId)
			->where('student_id', $studentId)
			->where('academic_year', $yearId)
			->delete();
		return true;
	}

	/**
	 * Boarding students for allocation UI / auto fill.
	 *
	 * @return list<array>
	 */
	public function listBoardingCandidates(
		int $schoolId,
		int $yearId,
		?int $classId = null,
		?int $departmentId = null,
		bool $unallocatedOnly = false
	): array {
		$this->ensureSchema();
		$db = \Config\Database::connect();

		$b = $db->table('class_records cr')
			->select('students.id, students.regno, students.fname, students.lname, students.sex, students.studying_mode,
				cr.class AS class_id, c.title AS class_title, l.title AS level_name, d.id AS department_id,
				d.title AS dept_title, d.code AS dept_code, ha.hostel_id, h.name AS hostel_name')
			->join('students', 'students.id = cr.student')
			->join('classes c', 'c.id = cr.class')
			->join('levels l', 'l.id = c.level', 'left')
			->join('departments d', 'd.id = c.department', 'left')
			->join('hostel_allocations ha', 'ha.student_id = students.id AND ha.academic_year = ' . (int) $yearId . ' AND ha.school_id = ' . (int) $schoolId, 'left')
			->join('hostels h', 'h.id = ha.hostel_id', 'left')
			->where('cr.year', $yearId)
			->where('cr.status', 1)
			->where('students.school_id', $schoolId)
			->where('students.status', 1)
			->where('students.studying_mode', self::MODE_BOARDING);

		if ($classId !== null && $classId > 0) {
			$b->where('cr.class', $classId);
		}
		if ($departmentId !== null && $departmentId > 0) {
			$b->where('c.department', $departmentId);
		}
		if ($unallocatedOnly) {
			$b->where('ha.id IS NULL', null, false);
		}

		$rows = $b->orderBy('l.title', 'ASC')
			->orderBy('d.code', 'ASC')
			->orderBy('c.title', 'ASC')
			->orderBy('students.fname', 'ASC')
			->get()->getResultArray();

		// One row per student (duplicate class_records / holiday classes).
		$byId = [];
		foreach ($rows as $row) {
			$sid = (int) ($row['id'] ?? 0);
			if ($sid > 0 && !isset($byId[$sid])) {
				$byId[$sid] = $row;
			}
		}
		return array_values($byId);
	}

	/**
	 * Auto-allocate unallocated boarding students into matching-gender hostels with free beds.
	 *
	 * @return array{allocated:int,skipped:int,errors:list<string>}
	 */
	public function autoAllocate(
		int $schoolId,
		int $yearId,
		int $staffId,
		?int $classId = null,
		?int $departmentId = null
	): array {
		$candidates = $this->listBoardingCandidates($schoolId, $yearId, $classId, $departmentId, true);
		$hostels = $this->listHostelsWithOccupancy($schoolId, $yearId);
		$separateLevels = !empty($this->getSchoolSettings($schoolId)['separate_by_level']);

		$pools = ['M' => [], 'F' => []];
		foreach ($hostels as $h) {
			$g = $this->normalizeGender((string) $h['gender']);
			$levelIds = [];
			foreach (($h['resident_levels'] ?? []) as $lvl) {
				$levelIds[] = (int) ($lvl['level_id'] ?? 0);
			}
			$pools[$g][] = [
				'id' => (int) $h['id'],
				'name' => (string) $h['name'],
				'free' => (int) $h['free_beds'],
				'level_ids' => array_values(array_filter($levelIds)),
			];
		}

		$allocated = 0;
		$skipped = 0;
		$errors = [];

		foreach ($candidates as $st) {
			$sex = $this->normalizeStudentSex($st['sex'] ?? '');
			$name = trim(($st['fname'] ?? '') . ' ' . ($st['lname'] ?? ''));
			if ($sex !== 'M' && $sex !== 'F') {
				$skipped++;
				$errors[] = $name . ': missing or unrecognized gender';
				continue;
			}

			$studentLevelId = 0;
			if ($separateLevels) {
				$lvl = $this->getStudentLevel($schoolId, (int) $st['id'], $yearId);
				$studentLevelId = $lvl ? (int) $lvl['level_id'] : 0;
			}

			// Prefer hostels already holding this level, then empty hostels, then others.
			$indices = array_keys($pools[$sex]);
			usort($indices, static function ($a, $b) use ($pools, $sex, $studentLevelId, $separateLevels) {
				$ha = $pools[$sex][$a];
				$hb = $pools[$sex][$b];
				$score = static function ($h) use ($studentLevelId, $separateLevels) {
					if ($h['free'] <= 0) {
						return 1000;
					}
					if (!$separateLevels || $studentLevelId <= 0) {
						return 0;
					}
					$ids = $h['level_ids'];
					if ($ids === []) {
						return 1; // empty — good second choice
					}
					if (count($ids) === 1 && (int) $ids[0] === $studentLevelId) {
						return 0; // same level — best
					}
					return 500; // incompatible / mixed
				};
				return $score($ha) <=> $score($hb);
			});

			$placed = false;
			$lastError = 'no free ' . ($sex === 'F' ? 'female' : 'male') . ' hostel bed';
			foreach ($indices as $i) {
				if ($pools[$sex][$i]['free'] <= 0) {
					continue;
				}
				if ($separateLevels && $studentLevelId > 0) {
					$ids = $pools[$sex][$i]['level_ids'];
					foreach ($ids as $existingLevelId) {
						if ((int) $existingLevelId !== $studentLevelId) {
							$lastError = 'no free same-level hostel bed';
							continue 2;
						}
					}
				}
				$hostelId = $pools[$sex][$i]['id'];
				$res = $this->allocateStudent($schoolId, $hostelId, (int) $st['id'], $yearId, $staffId);
				if ($res['ok']) {
					$allocated++;
					$pools[$sex][$i]['free']--;
					if ($separateLevels && $studentLevelId > 0
						&& !in_array($studentLevelId, $pools[$sex][$i]['level_ids'], true)) {
						$pools[$sex][$i]['level_ids'][] = $studentLevelId;
					}
					$placed = true;
					break;
				}
				$lastError = $res['error'] ?? 'failed';
			}
			if (!$placed) {
				$skipped++;
				$errors[] = $name . ': ' . $lastError;
			}
		}

		return ['allocated' => $allocated, 'skipped' => $skipped, 'errors' => array_slice($errors, 0, 40)];
	}

	public function listHostelResidents(int $schoolId, int $hostelId, int $yearId): array
	{
		$this->ensureSchema();
		$db = \Config\Database::connect();
		$rows = $db->table('hostel_allocations ha')
			->select('students.id, students.regno, students.fname, students.lname, students.sex,
				c.title AS class_title, l.title AS level_name, d.code AS dept_code')
			->join('students', 'students.id = ha.student_id')
			->join(
				'class_records cr',
				'cr.student = students.id AND cr.year = ' . (int) $yearId . ' AND cr.status = 1',
				'left'
			)
			->join('classes c', 'c.id = cr.class', 'left')
			->join('levels l', 'l.id = c.level', 'left')
			->join('departments d', 'd.id = c.department', 'left')
			->where('ha.school_id', $schoolId)
			->where('ha.hostel_id', $hostelId)
			->where('ha.academic_year', $yearId)
			->orderBy('students.fname', 'ASC')
			->get()->getResultArray();

		$byId = [];
		foreach ($rows as $row) {
			$sid = (int) ($row['id'] ?? 0);
			if ($sid <= 0 || isset($byId[$sid])) {
				continue;
			}
			$label = trim(preg_replace(
				'/\s+/',
				' ',
				trim(($row['level_name'] ?? '') . ' ' . ($row['dept_code'] ?? '') . ' ' . ($row['class_title'] ?? ''))
			) ?? '');
			$row['class_label'] = $label;
			$byId[$sid] = $row;
		}
		return array_values($byId);
	}

	/**
	 * School-wide student lookup: class + hostel for the selected year.
	 *
	 * @return list<array>
	 */
	public function searchStudentsWithPlacement(int $schoolId, int $yearId, string $query, int $limit = 25): array
	{
		$this->ensureSchema();
		$q = trim($query);
		if ($q === '' || strlen($q) < 2) {
			return [];
		}
		$limit = max(1, min(50, $limit));
		$db = \Config\Database::connect();
		$like = '%' . $q . '%';

		$rows = $db->table('students s')
			->select('s.id, s.regno, s.fname, s.lname, s.sex, s.studying_mode,
				c.title AS class_title, l.title AS level_name, d.code AS dept_code,
				ha.hostel_id, h.name AS hostel_name, h.gender AS hostel_gender')
			->join(
				'class_records cr',
				'cr.student = s.id AND cr.year = ' . (int) $yearId . ' AND cr.status = 1',
				'left'
			)
			->join('classes c', 'c.id = cr.class', 'left')
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
				->orWhere("CONCAT(IFNULL(s.lname,''), ' ', IFNULL(s.fname,'')) LIKE " . $db->escape($like), null, false)
			->groupEnd()
			->orderBy('s.fname', 'ASC')
			->orderBy('s.lname', 'ASC')
			->limit($limit * 3)
			->get()->getResultArray();

		$byId = [];
		foreach ($rows as $row) {
			$sid = (int) ($row['id'] ?? 0);
			if ($sid <= 0 || isset($byId[$sid])) {
				continue;
			}
			$label = trim(preg_replace(
				'/\s+/',
				' ',
				trim(($row['level_name'] ?? '') . ' ' . ($row['dept_code'] ?? '') . ' ' . ($row['class_title'] ?? ''))
			) ?: '');
			$mode = (int) ($row['studying_mode'] ?? 1);
			$byId[$sid] = [
				'id' => $sid,
				'regno' => (string) ($row['regno'] ?? ''),
				'fname' => (string) ($row['fname'] ?? ''),
				'lname' => (string) ($row['lname'] ?? ''),
				'sex' => (string) ($row['sex'] ?? ''),
				'studying_mode' => $mode,
				'mode_label' => $mode === self::MODE_BOARDING ? 'Boarding' : 'Day',
				'class_label' => $label !== '' ? $label : 'No class',
				'hostel_id' => isset($row['hostel_id']) && $row['hostel_id'] !== null ? (int) $row['hostel_id'] : null,
				'hostel_name' => (string) ($row['hostel_name'] ?? ''),
				'hostel_gender' => (string) ($row['hostel_gender'] ?? ''),
			];
			if (count($byId) >= $limit) {
				break;
			}
		}
		return array_values($byId);
	}
}
