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

		self::$schemaReady = true;
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

		$pools = ['M' => [], 'F' => []];
		foreach ($hostels as $h) {
			$g = $this->normalizeGender((string) $h['gender']);
			$pools[$g][] = [
				'id' => (int) $h['id'],
				'name' => (string) $h['name'],
				'free' => (int) $h['free_beds'],
			];
		}

		$allocated = 0;
		$skipped = 0;
		$errors = [];

		foreach ($candidates as $st) {
			$sex = $this->normalizeStudentSex($st['sex'] ?? '');
			if ($sex !== 'M' && $sex !== 'F') {
				$skipped++;
				$errors[] = trim(($st['fname'] ?? '') . ' ' . ($st['lname'] ?? '')) . ': missing or unrecognized gender';
				continue;
			}
			$picked = null;
			foreach ($pools[$sex] as $i => $h) {
				if ($h['free'] > 0) {
					$picked = $i;
					break;
				}
			}
			if ($picked === null) {
				$skipped++;
				$errors[] = trim(($st['fname'] ?? '') . ' ' . ($st['lname'] ?? '')) . ': no free ' . ($sex === 'F' ? 'female' : 'male') . ' hostel bed';
				continue;
			}
			$hostelId = $pools[$sex][$picked]['id'];
			$res = $this->allocateStudent($schoolId, $hostelId, (int) $st['id'], $yearId, $staffId);
			if ($res['ok']) {
				$allocated++;
				$pools[$sex][$picked]['free']--;
			} else {
				$skipped++;
				$errors[] = trim(($st['fname'] ?? '') . ' ' . ($st['lname'] ?? '')) . ': ' . ($res['error'] ?? 'failed');
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
