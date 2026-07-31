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
	 * Active visitors holding a card (both byte orders).
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

		$db = \Config\Database::connect();
		$placeholders = implode(',', array_fill(0, count($matchCards), '?'));
		$params = array_merge([(int) $schoolId], $matchCards);
		$sql = "SELECT sv.id, sv.names, sv.student_id, sv.card, sv.relationship, sv.status,
				CONCAT(st.fname, ' ', st.lname) AS student_name
			FROM student_visitors sv
			LEFT JOIN students st ON st.id = sv.student_id
			WHERE sv.school_id = ? AND sv.status = 1
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
		helper('card_uid');
		$variants = card_uid_lookup_variants($card);
		if (empty($variants)) {
			return null;
		}
		$db = \Config\Database::connect();
		$placeholders = implode(',', array_fill(0, count($variants), '?'));
		$params = array_merge([(int) $schoolId], $variants);
		return $db->query(
			"SELECT id, CONCAT(fname, ' ', lname) AS name FROM students
			WHERE school_id = ? AND card IS NOT NULL AND TRIM(card) <> ''
			AND UPPER(TRIM(card)) IN ({$placeholders}) LIMIT 1",
			$params
		)->getRowArray();
	}

	/**
	 * @param int $schoolId
	 * @param string $card
	 * @return array|null
	 */
	private function findStaffCardOwner($schoolId, $card)
	{
		$db = \Config\Database::connect();
		if (!$db->tableExists('staffs') || !$db->fieldExists('card', 'staffs')) {
			return null;
		}
		helper('card_uid');
		$variants = card_uid_lookup_variants($card);
		if (empty($variants)) {
			return null;
		}
		$placeholders = implode(',', array_fill(0, count($variants), '?'));
		$params = array_merge([(int) $schoolId], $variants);
		return $db->query(
			"SELECT id, CONCAT(fname, ' ', lname) AS name FROM staffs
			WHERE school_id = ? AND card IS NOT NULL AND TRIM(card) <> ''
			AND UPPER(TRIM(card)) IN ({$placeholders}) LIMIT 1",
			$params
		)->getRowArray();
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
			];
		}

		$staff = $this->findStaffCardOwner($schoolId, $card);
		if ($staff) {
			return [
				'type' => 'staff',
				'id' => (int) $staff['id'],
				'name' => $staff['name'],
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
	 * Normalize relationship label for storage (matches parent visiting assign UI).
	 */
	public static function normalizeRelationship(string $raw): string
	{
		$raw = trim($raw);
		if ($raw === '') {
			return '';
		}
		$key = strtolower(preg_replace('/[^a-z]/', '', $raw));
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
}
