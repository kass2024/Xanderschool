<?php

namespace App\Models;

use CodeIgniter\Model;

class StudentVisitorModel extends Model
{
	protected $table = 'student_visitors';
	protected $primaryKey = 'id';
	protected $returnType = 'array';
	protected $allowedFields = [
		'school_id',
		'student_id',
		'names',
		'phone',
		'relationship',
		'photo',
		'card',
		'status',
		'created_by',
		'updated_by',
	];
	protected $useTimestamps = true;

	/** @var bool */
	private static $schemaReady = false;

	public function ensureSchema()
	{
		if (self::$schemaReady) {
			return;
		}

		$db = \Config\Database::connect();
		try {
		$db->query("CREATE TABLE IF NOT EXISTS `student_visitors` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`school_id` INT UNSIGNED NOT NULL,
			`student_id` INT UNSIGNED NOT NULL,
			`names` VARCHAR(150) NOT NULL,
			`phone` VARCHAR(50) NULL DEFAULT NULL,
			`relationship` VARCHAR(80) NULL DEFAULT NULL,
			`card` VARCHAR(50) NULL DEFAULT NULL,
			`status` TINYINT(1) NOT NULL DEFAULT 1,
			`created_by` INT NULL DEFAULT NULL,
			`updated_by` INT NULL DEFAULT NULL,
			`created_at` DATETIME NULL DEFAULT NULL,
			`updated_at` DATETIME NULL DEFAULT NULL,
			PRIMARY KEY (`id`),
			KEY `idx_sv_school_student` (`school_id`, `student_id`),
			KEY `idx_sv_school_card` (`school_id`, `card`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

		if (!$db->fieldExists('photo', 'student_visitors')) {
			$db->query("ALTER TABLE `student_visitors`
				ADD COLUMN `photo` VARCHAR(120) NULL DEFAULT NULL AFTER `relationship`");
		}

		$db->query("CREATE TABLE IF NOT EXISTS `visitor_settings` (
			`school_id` INT UNSIGNED NOT NULL,
			`card_sharing` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '0=exclusive,1=same student,2=school-wide',
			`min_visitors` TINYINT UNSIGNED NOT NULL DEFAULT 2,
			`max_per_card` TINYINT UNSIGNED NOT NULL DEFAULT 2,
			`updated_at` DATETIME NULL DEFAULT NULL,
			PRIMARY KEY (`school_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

		if (!$db->fieldExists('max_per_card', 'visitor_settings')) {
			$db->query("ALTER TABLE `visitor_settings`
				ADD COLUMN `max_per_card` TINYINT UNSIGNED NOT NULL DEFAULT 2 AFTER `min_visitors`");
		}

		$db->query("CREATE TABLE IF NOT EXISTS `visitor_visits` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`school_id` INT UNSIGNED NOT NULL,
			`visitor_id` INT UNSIGNED NOT NULL,
			`student_id` INT UNSIGNED NOT NULL,
			`card` VARCHAR(50) NULL DEFAULT NULL,
			`visit_date` DATE NOT NULL,
			`time_in` INT UNSIGNED NOT NULL DEFAULT 0,
			`time_out` INT UNSIGNED NOT NULL DEFAULT 0,
			`source` VARCHAR(20) NOT NULL DEFAULT 'web',
			`operator` INT NULL DEFAULT NULL,
			`notes` TEXT NULL,
			`created_at` DATETIME NULL DEFAULT NULL,
			`updated_at` DATETIME NULL DEFAULT NULL,
			PRIMARY KEY (`id`),
			KEY `idx_vv_school_date` (`school_id`, `visit_date`),
			KEY `idx_vv_visitor` (`visitor_id`),
			KEY `idx_vv_student` (`student_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
		} catch (\Throwable $e) {
		}

		self::$schemaReady = true;
	}

	/**
	 * Per-school visitor module settings.
	 *
	 * @param int $schoolId
	 * @return array{card_sharing:int,min_visitors:int}
	 */
	public function getSettings($schoolId)
	{
		$this->ensureSchema();
		$schoolId = (int) $schoolId;
		$db = \Config\Database::connect();
		$row = $db->table('visitor_settings')->where('school_id', $schoolId)->get(1)->getRowArray();
		if (!$row) {
			return ['card_sharing' => 1, 'min_visitors' => 2, 'max_per_card' => 2];
		}
		return [
			'card_sharing' => (int) ($row['card_sharing'] ?? 1),
			'min_visitors' => max(1, (int) ($row['min_visitors'] ?? 2)),
			'max_per_card' => max(1, min(5, (int) ($row['max_per_card'] ?? 2))),
		];
	}

	/**
	 * @param int $schoolId
	 * @param array $data
	 * @return bool
	 */
	public function saveSettings($schoolId, array $data)
	{
		$this->ensureSchema();
		$schoolId = (int) $schoolId;
		$payload = [
			'school_id' => $schoolId,
			'card_sharing' => max(0, min(2, (int) ($data['card_sharing'] ?? 1))),
			'min_visitors' => max(1, min(10, (int) ($data['min_visitors'] ?? 2))),
			'max_per_card' => max(1, min(5, (int) ($data['max_per_card'] ?? 2))),
			'updated_at' => date('Y-m-d H:i:s'),
		];
		$db = \Config\Database::connect();
		$exists = $db->table('visitor_settings')->where('school_id', $schoolId)->countAllResults();
		if ($exists) {
			return $db->table('visitor_settings')->where('school_id', $schoolId)->update($payload);
		}
		return (bool) $db->table('visitor_settings')->insert($payload);
	}

	/**
	 * Persist RFID card on a visitor row (guaranteed DB write).
	 */
	public function persistCard(int $visitorId, int $schoolId, string $card, ?int $operator = null): bool
	{
		$this->ensureSchema();
		$visitorId = (int) $visitorId;
		$schoolId = (int) $schoolId;
		$card = strtoupper(trim($card));
		if ($visitorId <= 0 || $schoolId <= 0 || $card === '') {
			return false;
		}

		$data = [
			'card' => $card,
			'updated_at' => date('Y-m-d H:i:s'),
		];
		if ($operator !== null) {
			$data['updated_by'] = (int) $operator;
		}

		$db = \Config\Database::connect();
		$updated = $db->table('student_visitors')
			->where('id', $visitorId)
			->where('school_id', $schoolId)
			->update($data);

		return $updated !== false && $db->affectedRows() >= 0;
	}

	/**
	 * @return list<int>
	 */
	private function scopeSchoolIds(int $schoolId): array
	{
		$schoolId = (int) $schoolId;
		if ($schoolId <= 0) {
			return [];
		}

		$db = \Config\Database::connect();
		$scope = [$schoolId];
		try {
			if ($db->fieldExists('is_master', 'schools') && $db->fieldExists('master_school_id', 'schools')) {
				$row = $db->table('schools')
					->select('is_master')
					->where('id', $schoolId)
					->get()
					->getRowArray();
				if (!empty($row['is_master'])) {
					$children = $db->table('schools')
						->select('id')
						->where('master_school_id', $schoolId)
						->get()
						->getResultArray();
					foreach ($children as $child) {
						$childId = (int) ($child['id'] ?? 0);
						if ($childId > 0) {
							$scope[] = $childId;
						}
					}
				}
			}
		} catch (\Throwable $e) {
			// Keep single-school behavior if hierarchy metadata is unavailable.
		}
		return array_values(array_unique(array_filter(array_map('intval', $scope))));
	}

	/**
	 *
	 * @param int $schoolId
	 * @param string $card
	 * @param int $excludeVisitorId
	 * @return array
	 */
	public function getCardHolders($schoolId, $card, $excludeVisitorId = 0)
	{
		helper('card_uid');
		$matchCards = card_uid_lookup_variants($card);
		if (empty($matchCards)) {
			return [];
		}

		$scopeSchoolIds = $this->scopeSchoolIds((int) $schoolId);
		if ($scopeSchoolIds === []) {
			return [];
		}

		$db = \Config\Database::connect();
		$placeholders = implode(',', array_fill(0, count($matchCards), '?'));
		$scopePlaceholders = implode(',', array_fill(0, count($scopeSchoolIds), '?'));
		$params = array_merge($scopeSchoolIds, $matchCards);
		$sql = "SELECT sv.id, sv.names, sv.student_id, sv.card, sv.relationship, sv.status, sv.photo,
				sv.school_id, CONCAT(st.fname, ' ', st.lname) AS student_name
			FROM student_visitors sv
			INNER JOIN students st ON st.id = sv.student_id AND st.school_id = sv.school_id AND st.status = 1
			WHERE sv.school_id IN ({$scopePlaceholders}) AND sv.status = 1
			AND UPPER(TRIM(sv.card)) IN ({$placeholders})";
		if ($excludeVisitorId > 0) {
			$sql .= ' AND sv.id != ?';
			$params[] = (int) $excludeVisitorId;
		}
		$sql .= ' ORDER BY sv.id DESC';
		return $db->query($sql, $params)->getResultArray();
	}

	/**
	 * @param int $schoolId
	 * @param string $card
	 * @return array|null
	 */
	private function findStudentCardOwner($schoolId, $card)
	{
		$owner = \App\Libraries\CardRegistry::lookup((int) $schoolId, (string) $card);
		if ($owner && $owner['type'] === 'student') {
			return [
				'id' => (int) $owner['id'],
				'name' => (string) $owner['name'],
			];
		}
		return null;
	}

	/**
	 * @param int $schoolId
	 * @param string $card
	 * @return array|null
	 */
	private function findStaffCardOwner($schoolId, $card)
	{
		$owner = \App\Libraries\CardRegistry::lookup((int) $schoolId, (string) $card);
		if ($owner && $owner['type'] === 'staff') {
			return [
				'id' => (int) $owner['id'],
				'name' => (string) $owner['name'],
			];
		}
		return null;
	}

	/**
	 * Check if card is already used by a student, staff, or visitor in this school.
	 *
	 * @param int $schoolId
	 * @param string $card
	 * @param int $excludeVisitorId
	 * @param int $forStudentId student being assigned (for sharing rules)
	 * @param int|null $sharingMode 0 exclusive, 1 same-student share, 2 school-wide share
	 * @return array|null ['type'=>'student'|'staff'|'visitor','name'=>..., 'id'=>..., 'holders'=>...]
	 */
	public function findCardCollision($schoolId, $card, $excludeVisitorId = 0, $forStudentId = 0, $sharingMode = null)
	{
		$schoolId = (int) $schoolId;
		$card = strtoupper(trim((string) $card));
		if ($card === '' || $schoolId <= 0) {
			return null;
		}

		$settings = $this->getSettings($schoolId);
		if ($sharingMode === null) {
			$sharingMode = (int) $settings['card_sharing'];
		}
		$maxPerCard = max(1, (int) ($settings['max_per_card'] ?? 2));

		$db = \Config\Database::connect();

		$student = $this->findStudentCardOwner($schoolId, $card);
		if ($student) {
			return [
				'type' => 'student',
				'id' => (int) $student['id'],
				'name' => $student['name'],
				'error' => 'This card is assigned to student: ' . $student['name'] . '. Student cards cannot be used for visitors.',
			];
		}

		$staff = $this->findStaffCardOwner($schoolId, $card);
		if ($staff) {
			return [
				'type' => 'staff',
				'id' => (int) $staff['id'],
				'name' => $staff['name'],
				'error' => 'This card is assigned to staff member: ' . $staff['name'] . '. Staff cards cannot be used for visitors.',
			];
		}

		$holders = $this->getCardHolders($schoolId, $card, $excludeVisitorId);
		if (empty($holders)) {
			return null;
		}

		if ((int) $sharingMode === 0) {
			$h = $holders[0];
			return [
				'type' => 'visitor',
				'id' => (int) $h['id'],
				'name' => $h['names'],
				'holders' => $holders,
			];
		}

		if ((int) $sharingMode === 1) {
			foreach ($holders as $h) {
				if ((int) $forStudentId > 0 && (int) $h['student_id'] !== (int) $forStudentId) {
					return [
						'type' => 'visitor',
						'id' => (int) $h['id'],
						'name' => $h['names'],
						'error' => 'Card belongs to a visitor of another student.',
						'holders' => $holders,
					];
				}
			}
			$sameStudentCount = 0;
			foreach ($holders as $h) {
				if ((int) $forStudentId <= 0 || (int) $h['student_id'] === (int) $forStudentId) {
					$sameStudentCount++;
				}
			}
			if ($sameStudentCount >= $maxPerCard) {
				$h = $holders[0];
				return [
					'type' => 'visitor',
					'id' => (int) $h['id'],
					'name' => $h['names'],
					'error' => "This card already has {$maxPerCard} visitor(s). Remove one or use another card.",
					'holders' => $holders,
				];
			}
			return null;
		}

		// School-wide sharing
		if (count($holders) >= $maxPerCard) {
			$h = $holders[0];
			return [
				'type' => 'visitor',
				'id' => (int) $h['id'],
				'name' => $h['names'],
				'error' => "This card already has {$maxPerCard} visitor(s) school-wide.",
				'holders' => $holders,
			];
		}

		return null;
	}

	/**
	 * Cards assigned to a student's visitors, grouped by UID.
	 *
	 * @param int $schoolId
	 * @param int $studentId
	 * @return array
	 */
	public function getStudentCardGroups($schoolId, $studentId)
	{
		$rows = $this->where('school_id', (int) $schoolId)
			->where('student_id', (int) $studentId)
			->where('status', 1)
			->orderBy('card', 'ASC')
			->findAll();

		$groups = [];
		foreach ($rows as $row) {
			$key = strtoupper(trim((string) ($row['card'] ?? '')));
			if ($key === '') {
				continue;
			}
			if (!isset($groups[$key])) {
				$groups[$key] = ['card' => $key, 'visitors' => []];
			}
			$groups[$key]['visitors'][] = [
				'id' => (int) $row['id'],
				'names' => $row['names'],
				'relationship' => $row['relationship'] ?? '',
			];
		}
		return array_values($groups);
	}

	/**
	 * Active visitors sharing a card (for scan disambiguation).
	 *
	 * @param int $schoolId
	 * @param string $card
	 * @return array
	 */
	public function findByCard($schoolId, $card)
	{
		return $this->getCardHolders($schoolId, $card, 0);
	}

	/**
	 * Expand a scan result to include students that appear to share the same
	 * visitors, using both students-table parent info and visitor rows.
	 *
	 * @param int $schoolId
	 * @param array<int,array<string,mixed>> $seedVisitors
	 * @return array{student_ids:array<int,int>,visitors:array<int,array<string,mixed>>}
	 */
	public function expandSharedVisitGroup(int $schoolId, array $seedVisitors): array
	{
		$this->ensureSchema();
		$schoolId = (int) $schoolId;
		if ($schoolId <= 0 || empty($seedVisitors)) {
			return ['student_ids' => [], 'visitors' => []];
		}

		$db = \Config\Database::connect();
		$studentRows = $db->table('students')
			->select('id, status, father, ft_phone, mother, mt_phone, guardian, gd_phone')
			->where('school_id', $schoolId)
			->where('status', 1)
			->get()->getResultArray();
		$visitorRows = $db->table('student_visitors')
			->select('id, school_id, student_id, names, phone, relationship, photo, card, status')
			->where('school_id', $schoolId)
			->where('status', 1)
			->get()->getResultArray();

		$studentsById = [];
		foreach ($studentRows as $row) {
			$studentsById[(int) $row['id']] = $row;
		}

		$visitorsByStudent = [];
		foreach ($visitorRows as $row) {
			$studentId = (int) ($row['student_id'] ?? 0);
			if ($studentId <= 0) {
				continue;
			}
			if (!isset($visitorsByStudent[$studentId])) {
				$visitorsByStudent[$studentId] = [];
			}
			$visitorsByStudent[$studentId][] = $row;
		}

		$studentIds = [];
		foreach ($seedVisitors as $visitor) {
			$studentId = (int) ($visitor['student_id'] ?? 0);
			if ($studentId > 0 && isset($studentsById[$studentId])) {
				$studentIds[$studentId] = $studentId;
			}
		}

		$changed = true;
		while ($changed) {
			$changed = false;
			$parentKeys = [];
			$visitorKeys = [];

			foreach ($studentIds as $studentId) {
				if (isset($studentsById[$studentId])) {
					foreach ($this->studentParentKeys($studentsById[$studentId]) as $key) {
						$parentKeys[$key] = true;
					}
				}
				foreach ($visitorsByStudent[$studentId] ?? [] as $visitorRow) {
					foreach ($this->visitorIdentityKeys($visitorRow) as $key) {
						$visitorKeys[$key] = true;
					}
				}
			}

			foreach ($studentsById as $candidateId => $studentRow) {
				if (isset($studentIds[$candidateId])) {
					continue;
				}
				$matched = false;
				foreach ($this->studentParentKeys($studentRow) as $key) {
					if (isset($parentKeys[$key])) {
						$matched = true;
						break;
					}
				}
				if (!$matched) {
					foreach ($visitorsByStudent[$candidateId] ?? [] as $visitorRow) {
						foreach ($this->visitorIdentityKeys($visitorRow) as $key) {
							if (isset($visitorKeys[$key])) {
								$matched = true;
								break 2;
							}
						}
					}
				}
				if ($matched) {
					$studentIds[$candidateId] = $candidateId;
					$changed = true;
				}
			}
		}

		$visitors = [];
		foreach ($studentIds as $studentId) {
			foreach ($visitorsByStudent[$studentId] ?? [] as $visitorRow) {
				$visitors[(int) $visitorRow['id']] = $visitorRow;
			}
		}

		return [
			'student_ids' => array_values($studentIds),
			'visitors' => array_values($visitors),
		];
	}

	/**
	 * @param array<string,mixed> $row
	 * @return array<int,string>
	 */
	private function studentParentKeys(array $row): array
	{
		$pairs = [
			[$row['father'] ?? '', $row['ft_phone'] ?? ''],
			[$row['mother'] ?? '', $row['mt_phone'] ?? ''],
			[$row['guardian'] ?? '', $row['gd_phone'] ?? ''],
		];
		$keys = [];
		foreach ($pairs as [$name, $phone]) {
			$nameKey = $this->normalizeMatchName((string) $name);
			$phoneKey = $this->normalizeMatchPhone((string) $phone);
			if ($nameKey !== '' && $phoneKey !== '') {
				$keys[] = $nameKey . '|' . $phoneKey;
			}
		}
		return array_values(array_unique($keys));
	}

	/**
	 * @param array<string,mixed> $row
	 * @return array<int,string>
	 */
	private function visitorIdentityKeys(array $row): array
	{
		$nameKey = $this->normalizeMatchName((string) ($row['names'] ?? ''));
		$phoneKey = $this->normalizeMatchPhone((string) ($row['phone'] ?? ''));
		$relKey = self::normalizeRelationship((string) ($row['relationship'] ?? ''));
		$relKey = strtolower($relKey);
		$keys = [];
		if ($nameKey !== '' && $phoneKey !== '') {
			$keys[] = $nameKey . '|' . $phoneKey;
			if ($relKey !== '') {
				$keys[] = $nameKey . '|' . $phoneKey . '|' . $relKey;
			}
		}
		return array_values(array_unique($keys));
	}

	private function normalizeMatchName(string $value): string
	{
		$value = strtolower(trim($value));
		$value = preg_replace('/\s+/', ' ', $value);
		$value = preg_replace('/[^a-z0-9 ]/', '', $value);
		return trim((string) $value);
	}

	private function normalizeMatchPhone(string $value): string
	{
		$value = preg_replace('/\D+/', '', $value);
		return trim((string) $value);
	}

	/**
	 * @param string $card
	 * @return string
	 */
	public function reverseCardBytes($card)
	{
		helper('card_uid');
		return reverse_card_uid_bytes((string) $card);
	}

	/**
	 * Active visitor count for a student.
	 * @param int $schoolId
	 * @param int $studentId
	 * @return int
	 */
	public function countActiveForStudent($schoolId, $studentId)
	{
		return (int) $this->where('school_id', (int) $schoolId)
			->where('student_id', (int) $studentId)
			->where('status', 1)
			->countAllResults();
	}

	/**
	 * Active visitors with no RFID card assigned.
	 */
	public function countActiveWithoutCard(int $schoolId): int
	{
		$this->ensureSchema();
		$db = \Config\Database::connect();
		$row = $db->query(
			"SELECT COUNT(*) AS c FROM student_visitors
			WHERE school_id = ? AND status = 1
			AND (card IS NULL OR TRIM(card) = '')",
			[(int) $schoolId]
		)->getRowArray();
		return (int) ($row['c'] ?? 0);
	}

	/**
	 * Per-student counts of active visitors missing a card.
	 *
	 * @param int $schoolId
	 * @param list<int> $studentIds
	 * @return array<int,int> student_id => count
	 */
	public function countActiveWithoutCardByStudents(int $schoolId, array $studentIds): array
	{
		$this->ensureSchema();
		$studentIds = array_values(array_unique(array_filter(array_map('intval', $studentIds))));
		if ($schoolId <= 0 || empty($studentIds)) {
			return [];
		}
		$placeholders = implode(',', array_fill(0, count($studentIds), '?'));
		$params = array_merge([(int) $schoolId], $studentIds);
		$db = \Config\Database::connect();
		$rows = $db->query(
			"SELECT student_id, COUNT(*) AS c FROM student_visitors
			WHERE school_id = ? AND status = 1
			AND (card IS NULL OR TRIM(card) = '')
			AND student_id IN ({$placeholders})
			GROUP BY student_id",
			$params
		)->getResultArray();
		$out = [];
		foreach ($rows as $row) {
			$out[(int) $row['student_id']] = (int) $row['c'];
		}
		return $out;
	}

	/**
	 * Normalize relationship label for storage (matches parent visiting assign UI).
	 */
	public static function normalizeRelationship(string $raw): string
	{
		$raw = trim($raw);
		if ($raw === '') {
			return '';
		}
		$key = strtolower(preg_replace('/[^a-z]/', '', strtolower($raw)));
		$map = [
			'mother' => 'Mother',
			'father' => 'Father',
			'guardian' => 'Guardian',
			'sibling' => 'Sibling',
			'relative' => 'Relative',
			'other' => 'Other',
		];
		return $map[$key] ?? ucfirst(strtolower($raw));
	}

	/**
	 * Create active visitor rows for a student (skips empty names; no RFID card at import).
	 *
	 * @param int $schoolId
	 * @param int $studentId
	 * @param array<int,array{names?:string,phone?:string,relationship?:string}> $visitors
	 * @param int|null $operator
	 * @return int rows inserted
	 */
	public function syncForStudent(int $schoolId, int $studentId, array $visitors, ?int $operator = null): int
	{
		$this->ensureSchema();
		$schoolId = (int) $schoolId;
		$studentId = (int) $studentId;
		if ($schoolId <= 0 || $studentId <= 0) {
			return 0;
		}

		$inserted = 0;
		foreach ($visitors as $v) {
			$names = trim((string) ($v['names'] ?? ''));
			if ($names === '') {
				continue;
			}
			$phone = trim((string) ($v['phone'] ?? ''));
			$relationship = self::normalizeRelationship((string) ($v['relationship'] ?? ''));
			$this->insert([
				'school_id' => $schoolId,
				'student_id' => $studentId,
				'names' => $names,
				'phone' => $phone !== '' ? $phone : null,
				'relationship' => $relationship !== '' ? $relationship : null,
				'status' => 1,
				'created_by' => $operator,
				'updated_by' => $operator,
			]);
			$inserted++;
		}
		return $inserted;
	}

	/**
	 * Force-delete all parent-visiting data for a student (visits, visitors, photos, cards).
	 *
	 * @return array{visitors:int,visits:int,photos:int}
	 */
	public function purgeForStudent(int $schoolId, int $studentId): array
	{
		$this->ensureSchema();
		$schoolId = (int) $schoolId;
		$studentId = (int) $studentId;
		$stats = ['visitors' => 0, 'visits' => 0, 'photos' => 0];
		if ($schoolId <= 0 || $studentId <= 0) {
			return $stats;
		}

		$db = \Config\Database::connect();
		$visitors = $this->where('school_id', $schoolId)
			->where('student_id', $studentId)
			->findAll();

		$visitorIds = [];
		$profileDir = FCPATH . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'profile' . DIRECTORY_SEPARATOR;
		foreach ($visitors as $v) {
			$vid = (int) ($v['id'] ?? 0);
			if ($vid > 0) {
				$visitorIds[] = $vid;
			}
			$photo = trim((string) ($v['photo'] ?? ''));
			if ($photo !== '' && strpos($photo, '..') === false) {
				$path = $profileDir . $photo;
				if (is_file($path)) {
					@unlink($path);
					$stats['photos']++;
				}
			}
		}

		if (!empty($visitorIds)) {
			$stats['visits'] = (int) $db->table('visitor_visits')
				->where('school_id', $schoolId)
				->whereIn('visitor_id', $visitorIds)
				->delete();
		}

		$stats['visits'] += (int) $db->table('visitor_visits')
			->where('school_id', $schoolId)
			->where('student_id', $studentId)
			->delete();

		$stats['visitors'] = (int) $db->table('student_visitors')
			->where('school_id', $schoolId)
			->where('student_id', $studentId)
			->delete();

		return $stats;
	}

	/**
	 * Remove visitors whose student was deleted or is inactive.
	 *
	 * @return array{visitors:int,visits:int,photos:int}
	 */
	public function purgeOrphans(int $schoolId): array
	{
		$this->ensureSchema();
		$schoolId = (int) $schoolId;
		$stats = ['visitors' => 0, 'visits' => 0, 'photos' => 0];
		if ($schoolId <= 0) {
			return $stats;
		}

		$db = \Config\Database::connect();
		$orphans = $db->query(
			"SELECT sv.id, sv.student_id, sv.photo
			FROM student_visitors sv
			LEFT JOIN students s ON s.id = sv.student_id AND s.school_id = sv.school_id AND s.status = 1
			WHERE sv.school_id = ? AND s.id IS NULL",
			[$schoolId]
		)->getResultArray();

		$profileDir = FCPATH . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'profile' . DIRECTORY_SEPARATOR;
		$visitorIds = [];
		foreach ($orphans as $v) {
			$vid = (int) ($v['id'] ?? 0);
			if ($vid > 0) {
				$visitorIds[] = $vid;
			}
			$photo = trim((string) ($v['photo'] ?? ''));
			if ($photo !== '' && strpos($photo, '..') === false) {
				$path = $profileDir . $photo;
				if (is_file($path)) {
					@unlink($path);
					$stats['photos']++;
				}
			}
		}

		if (!empty($visitorIds)) {
			$placeholders = implode(',', array_fill(0, count($visitorIds), '?'));
			$params = array_merge([$schoolId], $visitorIds);
			$stats['visits'] = (int) $db->query(
				"DELETE FROM visitor_visits WHERE school_id = ? AND visitor_id IN ({$placeholders})",
				$params
			);
			$stats['visitors'] = (int) $db->query(
				"DELETE FROM student_visitors WHERE school_id = ? AND id IN ({$placeholders})",
				$params
			);
		}

		$db->query(
			"DELETE vv FROM visitor_visits vv
			LEFT JOIN students s ON s.id = vv.student_id AND s.school_id = vv.school_id AND s.status = 1
			WHERE vv.school_id = ? AND s.id IS NULL",
			[$schoolId]
		);

		return $stats;
	}

	/**
	 * Unassign every visitor RFID card for a school (visitors kept).
	 */
	public function clearAllCards(int $schoolId): int
	{
		$this->ensureSchema();
		$schoolId = (int) $schoolId;
		if ($schoolId <= 0) {
			return 0;
		}
		$db = \Config\Database::connect();
		$db->table('student_visitors')
			->where('school_id', $schoolId)
			->update([
				'card' => null,
				'updated_at' => date('Y-m-d H:i:s'),
			]);
		return (int) $db->affectedRows();
	}
}
