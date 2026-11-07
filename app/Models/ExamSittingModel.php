<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Exam rooms and random seat numbers. Each room starts again at 0001.
 */
class ExamSittingModel extends Model
{
	protected $table = 'exam_sitting_plans';
	protected $primaryKey = 'id';
	protected $returnType = 'array';
	protected $allowedFields = ['school_id', 'academic_year', 'title', 'class_ids', 'created_at', 'updated_at'];

	/** @var bool */
	private static $schemaReady = false;

	public function ensureSchema(): void
	{
		if (self::$schemaReady) {
			return;
		}
		$db = \Config\Database::connect();
		$db->query("CREATE TABLE IF NOT EXISTS `exam_sitting_plans` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`school_id` INT UNSIGNED NOT NULL,
			`academic_year` INT UNSIGNED NOT NULL DEFAULT 0,
			`title` VARCHAR(160) NOT NULL,
			`class_ids` TEXT NULL,
			`created_at` DATETIME NULL,
			`updated_at` DATETIME NULL,
			PRIMARY KEY (`id`),
			KEY `school_year` (`school_id`, `academic_year`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
		$db->query("CREATE TABLE IF NOT EXISTS `exam_sitting_rooms` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`plan_id` INT UNSIGNED NOT NULL,
			`school_id` INT UNSIGNED NOT NULL,
			`source_type` VARCHAR(12) NOT NULL,
			`source_id` INT UNSIGNED NOT NULL,
			`room_name` VARCHAR(160) NOT NULL,
			`tables_count` INT UNSIGNED NOT NULL,
			`columns_count` INT UNSIGNED NOT NULL,
			`students_per_table` INT UNSIGNED NOT NULL,
			`sort_order` INT NOT NULL DEFAULT 0,
			`created_at` DATETIME NULL,
			PRIMARY KEY (`id`),
			KEY `plan_room` (`plan_id`, `sort_order`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
		if ($db->tableExists('exam_sitting_plans') && !$db->fieldExists('mix_mode', 'exam_sitting_plans')) {
			$db->query("ALTER TABLE `exam_sitting_plans` ADD COLUMN `mix_mode` VARCHAR(12) NOT NULL DEFAULT 'apart' AFTER `class_ids`");
		}
		if ($db->tableExists('exam_sitting_plans') && !$db->fieldExists('mix_pairs', 'exam_sitting_plans')) {
			$db->query("ALTER TABLE `exam_sitting_plans` ADD COLUMN `mix_pairs` MEDIUMTEXT NULL AFTER `mix_mode`");
		}
		if ($db->tableExists('exam_sitting_plans') && !$db->fieldExists('level_band', 'exam_sitting_plans')) {
			$db->query("ALTER TABLE `exam_sitting_plans` ADD COLUMN `level_band` VARCHAR(20) NOT NULL DEFAULT 'high_school' AFTER `academic_year`");
		}
		if ($db->tableExists('exam_sitting_rooms') && !$db->fieldExists('invigilators', 'exam_sitting_rooms')) {
			$db->query("ALTER TABLE `exam_sitting_rooms` ADD COLUMN `invigilators` TEXT NULL AFTER `sort_order`");
		}
		$db->query("CREATE TABLE IF NOT EXISTS `exam_sitting_seats` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`plan_id` INT UNSIGNED NOT NULL,
			`room_id` INT UNSIGNED NOT NULL,
			`school_id` INT UNSIGNED NOT NULL,
			`seat_no` INT UNSIGNED NOT NULL,
			`table_no` INT UNSIGNED NOT NULL,
			`column_no` INT UNSIGNED NOT NULL,
			`place_on_table` INT UNSIGNED NOT NULL,
			`student_id` INT UNSIGNED NOT NULL DEFAULT 0,
			`regno` VARCHAR(40) NOT NULL DEFAULT '',
			`student_name` VARCHAR(160) NOT NULL DEFAULT '',
			`class_label` VARCHAR(160) NOT NULL DEFAULT '',
			PRIMARY KEY (`id`),
			KEY `plan_seat` (`plan_id`, `room_id`, `seat_no`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
		self::$schemaReady = true;
	}

	/** @return list<array<string,mixed>> */
	public function plansForSchool(int $schoolId, int $yearId): array
	{
		$this->ensureSchema();
		if ($schoolId < 1) {
			return [];
		}
		$plans = \Config\Database::connect()->table('exam_sitting_plans')
			->where('school_id', $schoolId)
			->where('academic_year', $yearId)
			->orderBy('id', 'DESC')
			->get()->getResultArray();
		if ($plans === []) {
			return [];
		}
		$ids = array_map(static function ($row) {
			return (int) $row['id'];
		}, $plans);
		$rooms = \Config\Database::connect()->table('exam_sitting_rooms')
			->where('school_id', $schoolId)
			->whereIn('plan_id', $ids)
			->orderBy('sort_order', 'ASC')
			->orderBy('id', 'ASC')
			->get()->getResultArray();
		$counts = \Config\Database::connect()->query(
			"SELECT plan_id, COUNT(*) AS seats, SUM(student_id > 0) AS filled
			FROM exam_sitting_seats WHERE school_id = ? GROUP BY plan_id",
			[$schoolId]
		)->getResultArray();
		$byPlan = [];
		foreach ($rooms as $room) {
			$byPlan[(int) $room['plan_id']][] = $room;
		}
		$filled = [];
		foreach ($counts as $count) {
			$filled[(int) $count['plan_id']] = $count;
		}
		foreach ($plans as &$plan) {
			$pid = (int) $plan['id'];
			$plan['rooms'] = $byPlan[$pid] ?? [];
			$plan['seat_count'] = (int) ($filled[$pid]['seats'] ?? 0);
			$plan['filled_count'] = (int) ($filled[$pid]['filled'] ?? 0);
			$plan['capacity'] = 0;
			foreach ($plan['rooms'] as $room) {
				$plan['capacity'] += (int) $room['tables_count'] * (int) $room['students_per_table'];
			}
		}
		unset($plan);
		return $plans;
	}

	/** @return array<string,mixed>|null */
	public function planDetail(int $schoolId, int $planId): ?array
	{
		$this->ensureSchema();
		$plan = \Config\Database::connect()->table('exam_sitting_plans')
			->where('school_id', $schoolId)
			->where('id', $planId)
			->get()->getRowArray();
		if (!$plan) {
			return null;
		}
		$rooms = \Config\Database::connect()->table('exam_sitting_rooms')
			->where('school_id', $schoolId)
			->where('plan_id', $planId)
			->orderBy('sort_order', 'ASC')
			->orderBy('id', 'ASC')
			->get()->getResultArray();
		$seats = \Config\Database::connect()->table('exam_sitting_seats')
			->where('school_id', $schoolId)
			->where('plan_id', $planId)
			->orderBy('seat_no', 'ASC')
			->get()->getResultArray();
		$byRoom = [];
		foreach ($seats as $seat) {
			$byRoom[(int) $seat['room_id']][] = $this->presentSeat($seat);
		}
		$capacity = 0;
		foreach ($rooms as &$room) {
			$room['seats'] = $byRoom[(int) $room['id']] ?? [];
			$room['capacity'] = (int) $room['tables_count'] * (int) $room['students_per_table'];
			$capacity += $room['capacity'];
		}
		unset($room);
		foreach ($rooms as &$room) {
			unset($room['invigilators']);
		}
		unset($room);
		$plan['rooms'] = $rooms;
		$plan['capacity'] = $capacity;
		$decoded = json_decode((string) ($plan['class_ids'] ?? ''), true);
		$plan['class_ids'] = is_array($decoded) ? array_values(array_map('intval', $decoded)) : [];
		$plan['mix_pairs'] = $this->decodePairs($plan['mix_pairs'] ?? '');
		$plan['level_band'] = self::normalizeBand($plan['level_band'] ?? '');
		return $plan;
	}

	public static function normalizeBand($band): string
	{
		$band = strtolower(trim((string) $band));
		if (in_array($band, ['nursery', 'primary', 'high_school'], true)) {
			return $band;
		}
		return 'high_school';
	}

	public static function bandLabel(string $band): string
	{
		$band = self::normalizeBand($band);
		if ($band === 'nursery') {
			return 'Nursery';
		}
		if ($band === 'primary') {
			return 'Primary';
		}
		return 'High school';
	}

	public function createPlan(int $schoolId, int $yearId, string $title, string $band = 'high_school'): int
	{
		$this->ensureSchema();
		$now = date('Y-m-d H:i:s');
		\Config\Database::connect()->table('exam_sitting_plans')->insert([
			'school_id' => $schoolId,
			'academic_year' => $yearId,
			'level_band' => self::normalizeBand($band),
			'title' => $title,
			'class_ids' => '[]',
			'created_at' => $now,
			'updated_at' => $now,
		]);
		return (int) \Config\Database::connect()->insertID();
	}

	public function renamePlan(int $schoolId, int $planId, string $title): bool
	{
		$this->ensureSchema();
		if (!$this->ownedPlan($schoolId, $planId)) {
			return false;
		}
		\Config\Database::connect()->table('exam_sitting_plans')
			->where('school_id', $schoolId)
			->where('id', $planId)
			->update([
				'title' => $title,
				'updated_at' => date('Y-m-d H:i:s'),
			]);
		return true;
	}

	public function deletePlan(int $schoolId, int $planId): void
	{
		$this->ensureSchema();
		$db = \Config\Database::connect();
		$db->table('exam_sitting_seats')->where('school_id', $schoolId)->where('plan_id', $planId)->delete();
		$db->table('exam_sitting_rooms')->where('school_id', $schoolId)->where('plan_id', $planId)->delete();
		$db->table('exam_sitting_plans')->where('school_id', $schoolId)->where('id', $planId)->delete();
	}

	/**
	 * @return array{ok:bool,error?:string,room?:array<string,mixed>}
	 */
	public function addRoom(int $schoolId, int $planId, string $sourceType, int $sourceId, int $tables, int $columns, int $perTable, string $roomName): array
	{
		$this->ensureSchema();
		$plan = $this->ownedPlan($schoolId, $planId);
		if (!$plan) {
			return ['ok' => false, 'error' => 'Sitting plan not found.'];
		}
		if (!in_array($sourceType, ['class', 'area'], true) || $sourceId < 1) {
			return ['ok' => false, 'error' => 'Choose a class or an attendance location.'];
		}
		if ($sourceType === 'class' && !$this->isRegularClass($schoolId, $sourceId)) {
			return ['ok' => false, 'error' => 'Holiday classes are not used as exam rooms.'];
		}
		if ($sourceType === 'class') {
			$planBand = self::normalizeBand($plan['level_band'] ?? '');
			$classBand = $this->classBand($schoolId, $sourceId);
			if ($classBand !== $planBand) {
				return ['ok' => false, 'error' => 'This plan is ' . self::bandLabel($planBand) . '. Choose a class from that level, or switch the level and create a separate plan.'];
			}
		}
		if ($tables < 1 || $tables > 80 || $perTable < 1 || $perTable > 8) {
			return ['ok' => false, 'error' => 'Set between 1 and 80 tables, and 1 to 8 students on each table.'];
		}
		if ($columns < 1) {
			$columns = 1;
		}
		if ($columns > $tables) {
			$columns = $tables;
		}
		if ($columns > 12) {
			return ['ok' => false, 'error' => 'Use at most 12 columns.'];
		}
		$roomName = trim(preg_replace('/\s+/', ' ', $roomName) ?? '');
		if ($roomName === '') {
			return ['ok' => false, 'error' => 'That room has no name.'];
		}
		$db = \Config\Database::connect();
		$dup = $db->table('exam_sitting_rooms')
			->where('plan_id', $planId)
			->where('source_type', $sourceType)
			->where('source_id', $sourceId)
			->countAllResults();
		if ($dup > 0) {
			return ['ok' => false, 'error' => 'That room is already on this plan.'];
		}
		$sort = (int) $db->table('exam_sitting_rooms')->where('plan_id', $planId)->countAllResults();
		$db->table('exam_sitting_rooms')->insert([
			'plan_id' => $planId,
			'school_id' => $schoolId,
			'source_type' => $sourceType,
			'source_id' => $sourceId,
			'room_name' => function_exists('mb_substr') ? mb_substr($roomName, 0, 160) : substr($roomName, 0, 160),
			'tables_count' => $tables,
			'columns_count' => $columns,
			'students_per_table' => $perTable,
			'sort_order' => $sort,
			'created_at' => date('Y-m-d H:i:s'),
		]);
		$this->clearSeats($schoolId, $planId);
		return ['ok' => true];
	}

	public function deleteRoom(int $schoolId, int $planId, int $roomId): bool
	{
		$this->ensureSchema();
		if (!$this->ownedPlan($schoolId, $planId)) {
			return false;
		}
		$db = \Config\Database::connect();
		$db->table('exam_sitting_seats')->where('school_id', $schoolId)->where('room_id', $roomId)->delete();
		$db->table('exam_sitting_rooms')->where('school_id', $schoolId)->where('plan_id', $planId)->where('id', $roomId)->delete();
		$this->clearSeats($schoolId, $planId);
		return true;
	}

	/**
	 * Shuffle students into the rooms. Seat numbers restart at 0001 in every room.
	 *
	 * @param list<int> $classIds empty = every regular class, used to limit which classes a pair may include
	 * @param list<list<int>> $pairs each pair is mixed on its own tables
	 * @return array{ok:bool,error?:string,plan?:array<string,mixed>,unseated?:list<array<string,string>>,student_count?:int}
	 */
	public function assign(int $schoolId, int $planId, int $yearId, array $classIds, string $mix = 'apart', array $pairs = []): array
	{
		$this->ensureSchema();
		$plan = $this->ownedPlan($schoolId, $planId);
		if (!$plan) {
			return ['ok' => false, 'error' => 'Sitting plan not found.'];
		}
		$rooms = \Config\Database::connect()->table('exam_sitting_rooms')
			->where('school_id', $schoolId)
			->where('plan_id', $planId)
			->orderBy('sort_order', 'ASC')
			->orderBy('id', 'ASC')
			->get()->getResultArray();
		if ($rooms === []) {
			return ['ok' => false, 'error' => 'Add at least one exam room.'];
		}
		$mix = 'pairs';
		$planBand = self::normalizeBand($plan['level_band'] ?? '');
		$allowedIds = [];
		foreach ($this->regularClasses($schoolId) as $class) {
			if (($class['band'] ?? '') !== $planBand) {
				continue;
			}
			$allowedIds[(int) ($class['id'] ?? 0)] = true;
		}
		$classIds = array_values(array_unique(array_filter(array_map('intval', $classIds))));
		if ($classIds !== []) {
			$kept = [];
			foreach ($classIds as $classId) {
				if (!empty($allowedIds[$classId])) {
					$kept[] = $classId;
				}
			}
			$classIds = $kept;
			if ($classIds === []) {
				return ['ok' => false, 'error' => 'Choose a ' . self::bandLabel($planBand) . ' class. Other levels stay on their own plan.'];
			}
		}
		$limit = $classIds !== [] ? array_fill_keys($classIds, true) : $allowedIds;
		$pairs = $this->normalizePairs($pairs, $limit);
		if ($pairs === []) {
			return ['ok' => false, 'error' => 'Create a mix pair first. Pair 1 can be S1 A, S3 and S6 ACC.'];
		}
		$union = [];
		foreach ($pairs as $pair) {
			foreach ($pair as $classId) {
				$union[$classId] = $classId;
			}
		}
		$students = $this->candidateStudents($schoolId, $yearId, array_values($union));
		if ($students === []) {
			return ['ok' => false, 'error' => 'No active students in these classes. Holiday classes and dismissed students are left out.'];
		}
		$slots = [];
		foreach ($rooms as $room) {
			$tables = (int) $room['tables_count'];
			$columns = max(1, (int) $room['columns_count']);
			$per = (int) $room['students_per_table'];
			$seatNo = 1;
			for ($table = 0; $table < $tables; $table++) {
				for ($place = 1; $place <= $per; $place++) {
					$slots[] = [
						'plan_id' => $planId,
						'room_id' => (int) $room['id'],
						'school_id' => $schoolId,
						'seat_no' => $seatNo,
						'table_no' => $table + 1,
						'column_no' => ($table % $columns) + 1,
						'row_no' => intdiv($table, $columns),
						'place_on_table' => $place,
						'per' => $per,
						'student_id' => 0,
						'regno' => '',
						'student_name' => '',
						'class_label' => '',
					];
					$seatNo++;
				}
			}
		}
		[$bestSlots, $bestUnseated] = $this->placeByPairs($slots, $students, $pairs);
		$slots = $bestSlots;
		$unseated = [];
		foreach ($bestUnseated as $student) {
			$unseated[] = [
				'name' => $student['student_name'],
				'regno' => $student['regno'],
				'class_label' => $student['class_label'],
			];
		}
		foreach ($slots as &$slot) {
			unset($slot['row_no'], $slot['per']);
		}
		unset($slot);
		$db = \Config\Database::connect();
		$db->transStart();
		$db->table('exam_sitting_seats')->where('school_id', $schoolId)->where('plan_id', $planId)->delete();
		foreach (array_chunk($slots, 200) as $chunk) {
			$db->table('exam_sitting_seats')->insertBatch($chunk);
		}
		$db->table('exam_sitting_plans')->where('id', $planId)->update([
			'class_ids' => json_encode(array_values($union)),
			'mix_mode' => $mix,
			'mix_pairs' => json_encode($pairs),
			'updated_at' => date('Y-m-d H:i:s'),
		]);
		$db->transComplete();
		if ($db->transStatus() === false) {
			return ['ok' => false, 'error' => 'Could not save the sitting numbers.'];
		}
		$detail = $this->planDetail($schoolId, $planId);
		return [
			'ok' => true,
			'plan' => $detail,
			'unseated' => $unseated,
			'student_count' => count($students),
		];
	}

	/** Wipe the saved pairs and seats so a new mix can be generated. */
	public function clearPairing(int $schoolId, int $planId): array
	{
		$this->ensureSchema();
		if (!$this->ownedPlan($schoolId, $planId)) {
			return ['ok' => false, 'error' => 'Sitting plan not found.'];
		}
		$this->clearSeats($schoolId, $planId);
		\Config\Database::connect()->table('exam_sitting_plans')
			->where('school_id', $schoolId)
			->where('id', $planId)
			->update([
				'class_ids' => null,
				'mix_pairs' => null,
				'mix_mode' => 'pairs',
				'updated_at' => date('Y-m-d H:i:s'),
			]);
		return ['ok' => true, 'plan' => $this->planDetail($schoolId, $planId)];
	}

	/** @return array<string,mixed>|null */
	/**
	 * Classes used for exam rooms and seating. Holiday coaching is excluded.
	 *
	 * @return list<array<string,mixed>>
	 */
	public function regularClasses(int $schoolId): array
	{
		$notHoliday = \App\Models\ClassRecordModel::notHolidaySql('c', 'l', 'd');
		$rows = \Config\Database::connect()->table('classes c')
			->select('c.id, c.title, d.code as dept_code, d.faculty_id as dept_faculty_id, l.title as level_name, l.faculty_id as level_faculty_id, f.title as faculty_title, f.abbrev as faculty_abbrev, f.type as faculty_type')
			->join('departments d', 'd.id = c.department', 'left')
			->join('levels l', 'l.id = c.level', 'left')
			->join('faculty f', 'f.id = d.faculty_id', 'left')
			->where('c.school_id', $schoolId)
			->where($notHoliday, null, false)
			->orderBy('l.id', 'ASC')
			->orderBy('d.code', 'ASC')
			->orderBy('c.title', 'ASC')
			->get()->getResultArray();
		foreach ($rows as &$row) {
			$row['band'] = self::sittingBand($row);
		}
		unset($row);
		return $rows;
	}

	/**
	 * Nursery, Primary, or High School. Everything that is not nursery or primary is High School.
	 *
	 * @param array<string,mixed> $class
	 */
	public static function sittingBand(array $class): string
	{
		$probe = $class;
		$probe['level_title'] = trim(implode(' ', [
			(string) ($class['level_name'] ?? ''),
			(string) ($class['level_title'] ?? ''),
			(string) ($class['title'] ?? ''),
			(string) ($class['dept_code'] ?? ''),
		]));
		$track = \App\Libraries\TimetableTrack::resolveFromRow($probe);
		if ($track === \App\Libraries\TimetableTrack::NURSERY) {
			return 'nursery';
		}
		if ($track === \App\Libraries\TimetableTrack::PRIMARY && self::isExplicitPrimary($probe)) {
			return 'primary';
		}
		return 'high_school';
	}

	/**
	 * Primary only when the class actually says so. Unknown classes stay High School.
	 *
	 * @param array<string,mixed> $class
	 */
	private static function isExplicitPrimary(array $class): bool
	{
		if ((int) ($class['level_faculty_id'] ?? 0) === 3 || (int) ($class['dept_faculty_id'] ?? 0) === 3) {
			return true;
		}
		$hay = strtolower(trim(implode(' ', [
			(string) ($class['level_title'] ?? ''),
			(string) ($class['level_name'] ?? ''),
			(string) ($class['title'] ?? ''),
			(string) ($class['faculty_title'] ?? ''),
			(string) ($class['faculty_abbrev'] ?? ''),
			(string) ($class['dept_code'] ?? ''),
		])));
		return (bool) preg_match('/primary|\bp[1-6]\b/', $hay);
	}

	private function classBand(int $schoolId, int $classId): string
	{
		foreach ($this->regularClasses($schoolId) as $class) {
			if ((int) ($class['id'] ?? 0) === $classId) {
				return (string) ($class['band'] ?? 'high_school');
			}
		}
		return '';
	}

	public function isRegularClass(int $schoolId, int $classId): bool
	{
		if ($classId < 1) {
			return false;
		}
		foreach ($this->regularClasses($schoolId) as $class) {
			if ((int) ($class['id'] ?? 0) === $classId) {
				return true;
			}
		}
		return false;
	}

	private function ownedPlan(int $schoolId, int $planId): ?array
	{
		$row = \Config\Database::connect()->table('exam_sitting_plans')
			->where('school_id', $schoolId)
			->where('id', $planId)
			->get()->getRowArray();
		return $row ?: null;
	}

	private function clearSeats(int $schoolId, int $planId): void
	{
		$db = \Config\Database::connect();
		$db->table('exam_sitting_seats')
			->where('school_id', $schoolId)
			->where('plan_id', $planId)
			->delete();
		$db->table('exam_sitting_rooms')->where('school_id', $schoolId)->where('plan_id', $planId)->update([
			'invigilators' => null,
		]);
		$db->table('exam_sitting_plans')->where('id', $planId)->update([
			'updated_at' => date('Y-m-d H:i:s'),
		]);
	}

	/**
	 * Fill invigilators once for a plan that already has seats.
	 *
	 * @param list<array<string,mixed>> $rooms
	 */
	private function ensureInvigilators(int $schoolId, array &$rooms): void
	{
		$pending = [];
		$needsSave = false;
		foreach ($rooms as $room) {
			$students = 0;
			foreach ($room['seats'] ?? [] as $seat) {
				if ((int) ($seat['student_id'] ?? 0) > 0) {
					$students++;
				}
			}
			$saved = $room['invigilators'] ?? null;
			$decoded = is_string($saved) && $saved !== '' ? json_decode($saved, true) : null;
			if ($students > 0 && !is_array($decoded)) {
				$needsSave = true;
			}
			$pending[] = [
				'id' => (int) ($room['id'] ?? 0),
				'students' => $students,
				'saved' => is_array($decoded) ? array_values($decoded) : null,
			];
		}
		$assigned = [];
		if ($needsSave) {
			$jobs = [];
			foreach ($pending as $row) {
				if ($row['students'] > 0) {
					$jobs[] = ['id' => $row['id'], 'students' => $row['students']];
				}
			}
			$assigned = \App\Libraries\ExamInvigilators::assignRooms($jobs);
			$this->storeInvigilators($schoolId, $assigned);
		}
		foreach ($rooms as &$room) {
			$id = (int) ($room['id'] ?? 0);
			if ($needsSave) {
				$room['invigilators'] = $assigned[$id] ?? [];
				continue;
			}
			$saved = $room['invigilators'] ?? null;
			$decoded = is_string($saved) && $saved !== '' ? json_decode($saved, true) : [];
			$room['invigilators'] = is_array($decoded) ? array_values($decoded) : [];
		}
		unset($room);
	}

	/**
	 * @param list<array<string,mixed>> $rooms
	 * @param list<array<string,mixed>> $slots
	 * @return array<int,list<string>>
	 */
	private function invigilatorsForSlots(array $rooms, array $slots): array
	{
		$counts = [];
		foreach ($slots as $slot) {
			if ((int) ($slot['student_id'] ?? 0) < 1) {
				continue;
			}
			$id = (int) ($slot['room_id'] ?? 0);
			$counts[$id] = ($counts[$id] ?? 0) + 1;
		}
		$jobs = [];
		foreach ($rooms as $room) {
			$id = (int) ($room['id'] ?? 0);
			$students = (int) ($counts[$id] ?? 0);
			if ($students > 0) {
				$jobs[] = ['id' => $id, 'students' => $students];
			}
		}
		$assigned = \App\Libraries\ExamInvigilators::assignRooms($jobs);
		foreach ($rooms as $room) {
			$id = (int) ($room['id'] ?? 0);
			if (!isset($assigned[$id])) {
				$assigned[$id] = [];
			}
		}
		return $assigned;
	}

	/**
	 * @param array<int,list<string>> $byRoom
	 */
	private function storeInvigilators(int $schoolId, array $byRoom): void
	{
		$db = \Config\Database::connect();
		foreach ($byRoom as $roomId => $names) {
			$db->table('exam_sitting_rooms')
				->where('school_id', $schoolId)
				->where('id', (int) $roomId)
				->update(['invigilators' => json_encode(array_values($names))]);
		}
	}

	/**
	 * @param list<int> $classIds
	 * @return list<array{id:int,regno:string,student_name:string,class_label:string}>
	 */
	private function candidateStudents(int $schoolId, int $yearId, array $classIds): array
	{
		$builder = \Config\Database::connect()->table('students st')
			->select("st.id, st.regno, st.fname, st.lname, MIN(c.id) AS class_id, MIN(l.id) AS level_id, MIN(l.title) AS level_name, MIN(CONCAT(IFNULL(l.title,''), ' ', IFNULL(d.code,''), ' ', IFNULL(c.title,''))) AS class_label", false)
			->join('class_records cr', 'cr.student = st.id')
			->join('classes c', 'c.id = cr.class')
			->join('departments d', 'd.id = c.department', 'left')
			->join('levels l', 'l.id = c.level', 'left')
			->where('st.school_id', $schoolId)
			->where('c.school_id', $schoolId)
			->whereIn('st.status', [1, 2])
			->where('cr.status', 1)
			->where(\App\Models\ClassRecordModel::notHolidaySql('c', 'l', 'd'), null, false)
			->groupBy('st.id, st.regno, st.fname, st.lname')
			->orderBy('st.id', 'ASC');
		if ($yearId > 0) {
			$builder->where('cr.year', $yearId);
		}
		if ($classIds !== []) {
			$builder->whereIn('c.id', $classIds);
		}
		$rows = $builder->get()->getResultArray();
		$out = [];
		$seen = [];
		foreach ($rows as $row) {
			$id = (int) ($row['id'] ?? 0);
			if ($id < 1 || isset($seen[$id])) {
				continue;
			}
			$seen[$id] = true;
			$name = trim((string) ($row['fname'] ?? '') . ' ' . (string) ($row['lname'] ?? ''));
			$out[] = [
				'id' => $id,
				'class_id' => (int) ($row['class_id'] ?? 0),
				'level_id' => (int) ($row['level_id'] ?? 0),
				'level_name' => trim((string) ($row['level_name'] ?? '')),
				'regno' => trim((string) ($row['regno'] ?? '')),
				'student_name' => $name !== '' ? $name : 'Student',
				'class_label' => trim((string) ($row['class_label'] ?? '')),
			];
		}
		return $out;
	}

	/**
	 * Same level cannot share a table, consecutive numbers, or seats that touch.
	 * S1 A and S1 B are one level. S1 and S2 are not.
	 *
	 * @param list<array<string,mixed>> $slots
	 * @return list<list<int>>
	 */
	private function seatNeighbors(array $slots): array
	{
		$neighbors = [];
		$count = count($slots);
		for ($i = 0; $i < $count; $i++) {
			$neighbors[$i] = [];
		}
		for ($i = 0; $i < $count; $i++) {
			for ($j = $i + 1; $j < $count; $j++) {
				if ((int) $slots[$i]['room_id'] !== (int) $slots[$j]['room_id']) {
					continue;
				}
				if (!$this->seatsTouch($slots[$i], $slots[$j])) {
					continue;
				}
				$neighbors[$i][] = $j;
				$neighbors[$j][] = $i;
			}
		}
		return $neighbors;
	}

	/** @param array<string,mixed> $a @param array<string,mixed> $b */
	private function seatsTouch(array $a, array $b): bool
	{
		if (abs((int) $a['seat_no'] - (int) $b['seat_no']) === 1) {
			return true;
		}
		if ((int) $a['table_no'] === (int) $b['table_no']) {
			return true;
		}
		$rowGap = abs((int) $a['row_no'] - (int) $b['row_no']);
		$colGap = abs((int) $a['column_no'] - (int) $b['column_no']);
		$placeGap = abs((int) $a['place_on_table'] - (int) $b['place_on_table']);
		if ($colGap === 0 && $rowGap === 1 && $placeGap <= 1) {
			return true;
		}
		if ($colGap === 1 && $rowGap <= 1) {
			$left = (int) $a['column_no'] < (int) $b['column_no'] ? $a : $b;
			$right = (int) $a['column_no'] < (int) $b['column_no'] ? $b : $a;
			if ((int) $left['place_on_table'] === (int) $left['per'] && (int) $right['place_on_table'] === 1) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param mixed $raw
	 * @return list<list<int>>
	 */
	private function decodePairs($raw): array
	{
		$decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);
		if (!is_array($decoded)) {
			return [];
		}
		$pairs = [];
		foreach ($decoded as $pair) {
			if (!is_array($pair)) {
				continue;
			}
			$ids = [];
			foreach ($pair as $classId) {
				$classId = (int) $classId;
				if ($classId > 0 && !in_array($classId, $ids, true)) {
					$ids[] = $classId;
				}
			}
			if (count($ids) >= 2) {
				$pairs[] = $ids;
			}
		}
		return $pairs;
	}

	/**
	 * @param list<list<int>> $pairs
	 * @param array<int,bool> $allowed
	 * @return list<list<int>>
	 */
	private function normalizePairs(array $pairs, array $allowed): array
	{
		$seen = [];
		$clean = [];
		foreach ($pairs as $pair) {
			if (!is_array($pair)) {
				continue;
			}
			$ids = [];
			foreach ($pair as $classId) {
				$classId = (int) $classId;
				if ($classId < 1 || empty($allowed[$classId]) || isset($seen[$classId]) || in_array($classId, $ids, true)) {
					continue;
				}
				$ids[] = $classId;
				$seen[$classId] = true;
			}
			if (count($ids) >= 2) {
				$clean[] = $ids;
			}
		}
		return $clean;
	}

	/**
	 * Each table belongs to one pair. Seats take one student from each class in the pair, in turn.
	 * The same level is never repeated on that table. S1 A and S1 B are both S1.
	 *
	 * @param list<array<string,mixed>> $template
	 * @param list<array<string,mixed>> $students
	 * @param list<list<int>> $pairs
	 * @return array{0:list<array<string,mixed>>,1:list<array<string,mixed>>}
	 */
	private function placeByPairs(array $template, array $students, array $pairs): array
	{
		$byClass = [];
		foreach ($students as $student) {
			$byClass[(int) $student['class_id']][] = $student;
		}
		foreach ($byClass as &$queue) {
			$this->shuffle($queue);
		}
		unset($queue);
		$tables = [];
		foreach ($template as $index => $slot) {
			$key = (int) $slot['room_id'] . ':' . (int) $slot['table_no'];
			$tables[$key][] = $index;
		}
		$slots = $template;
		$tableKeys = array_keys($tables);
		$cursor = 0;
		$turn = 0;
		foreach ($pairs as $pair) {
			$queues = [];
			foreach ($pair as $classId) {
				$queues[$classId] = $byClass[$classId] ?? [];
			}
			while ($cursor < count($tableKeys) && $this->pairStillHasStudents($queues)) {
				$usedClass = [];
				$usedLevel = [];
				$classIds = array_keys($queues);
				$count = count($classIds);
				foreach ($tables[$tableKeys[$cursor]] as $index) {
					$picked = null;
					$pickedClass = 0;
					$pickedLevel = '';
					for ($step = 0; $step < $count; $step++) {
						$classId = $classIds[($turn + $step) % $count];
						if (isset($usedClass[$classId]) || empty($queues[$classId])) {
							continue;
						}
						$candidate = $queues[$classId][0];
						$level = $this->levelGroup($candidate);
						if (isset($usedLevel[$level])) {
							continue;
						}
						$picked = array_shift($queues[$classId]);
						$pickedClass = $classId;
						$pickedLevel = $level;
						$turn = ($turn + $step + 1) % $count;
						break;
					}
					if ($picked === null) {
						break;
					}
					$usedClass[$pickedClass] = true;
					$usedLevel[$pickedLevel] = true;
					$slots[$index]['student_id'] = (int) $picked['id'];
					$slots[$index]['regno'] = $picked['regno'];
					$slots[$index]['student_name'] = $picked['student_name'];
					$slots[$index]['class_label'] = $picked['class_label'];
				}
				$cursor++;
			}
		}
		$unseated = [];
		foreach ($pairs as $pair) {
			foreach ($pair as $classId) {
				foreach ($byClass[$classId] ?? [] as $student) {
					$placed = false;
					foreach ($slots as $slot) {
						if ((int) ($slot['student_id'] ?? 0) === (int) $student['id']) {
							$placed = true;
							break;
						}
					}
					if (!$placed) {
						$unseated[] = $student;
					}
				}
			}
		}
		return [$slots, $unseated];
	}

	/**
	 * S1 A and S1 B share the level name S1, so they are one group.
	 *
	 * @param array<string,mixed> $student
	 */
	private function levelGroup(array $student): string
	{
		$name = strtoupper(trim((string) preg_replace('/\s+/', ' ', (string) ($student['level_name'] ?? ''))));
		if ($name !== '') {
			return 'N:' . $name;
		}
		$levelId = (int) ($student['level_id'] ?? 0);
		if ($levelId > 0) {
			return 'I:' . $levelId;
		}
		return 'C:' . (int) ($student['class_id'] ?? 0);
	}

	/** @param array<int,list<array<string,mixed>>> $queues */
	private function pairStillHasStudents(array $queues): bool
	{
		foreach ($queues as $queue) {
			if ($queue !== []) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param list<array<string,mixed>> $template
	 * @param list<array<string,mixed>> $students
	 * @param list<list<int>> $neighbors
	 * @return array{0:list<array<string,mixed>>,1:list<array<string,mixed>>}
	 */
	private function placeStudents(array $template, array $students, array $neighbors, bool $separate): array
	{
		$slots = $template;
		$byClass = [];
		foreach ($students as $student) {
			$byClass[(int) $student['class_id']][] = $student;
		}
		uasort($byClass, static function ($left, $right) {
			return count($right) <=> count($left);
		});
		$queues = array_values($byClass);
		$longest = 0;
		foreach ($queues as $queue) {
			$longest = max($longest, count($queue));
		}
		$order = [];
		for ($i = 0; $i < $longest; $i++) {
			foreach ($queues as $queue) {
				if (isset($queue[$i])) {
					$order[] = $queue[$i];
				}
			}
		}
		$seatOrder = range(0, count($slots) - 1);
		$this->shuffle($seatOrder);
		$classAt = array_fill(0, count($slots), 0);
		$unseated = [];
		foreach ($order as $student) {
			$placed = false;
			$classId = (int) $student['class_id'];
			$levelId = (int) ($student['level_id'] ?? 0);
			$group = $levelId > 0 ? $levelId : -$classId;
			foreach ($seatOrder as $index) {
				if ($classAt[$index] !== 0) {
					continue;
				}
				if ($separate) {
					$blocked = false;
					foreach ($neighbors[$index] as $near) {
						if ($classAt[$near] === $group) {
							$blocked = true;
							break;
						}
					}
					if ($blocked) {
						continue;
					}
				}
				$slots[$index]['student_id'] = (int) $student['id'];
				$slots[$index]['regno'] = $student['regno'];
				$slots[$index]['student_name'] = $student['student_name'];
				$slots[$index]['class_label'] = $student['class_label'];
				$classAt[$index] = $group;
				$placed = true;
				break;
			}
			if (!$placed) {
				$unseated[] = $student;
			}
		}
		return [$slots, $unseated];
	}

	/** @param list<array<string,mixed>> $items */
	private function shuffle(array &$items): void
	{
		for ($i = count($items) - 1; $i > 0; $i--) {
			$j = random_int(0, $i);
			$swap = $items[$i];
			$items[$i] = $items[$j];
			$items[$j] = $swap;
		}
	}

	/** @param array<string,mixed> $seat */
	private function presentSeat(array $seat): array
	{
		$no = str_pad((string) (int) ($seat['seat_no'] ?? 0), 4, '0', STR_PAD_LEFT);
		$reg = trim((string) ($seat['regno'] ?? ''));
		$seat['code'] = $reg !== '' ? ($no . ' / ' . $reg) : $no;
		$seat['seat_label'] = $no;
		return $seat;
	}
}
