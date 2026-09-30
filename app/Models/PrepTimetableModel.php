<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Weekly morning and evening prep duty rota.
 * Active staff can invigilate except Cooker, Cleaner, and Security posts.
 */
class PrepTimetableModel extends Model
{
	protected $table = 'prep_timetable';
	protected $primaryKey = 'school_id';
	protected $returnType = 'array';
	protected $allowedFields = ['school_id', 'morning_start', 'morning_end', 'evening_start', 'evening_end', 'updated_at'];
	protected $useTimestamps = false;

	/** @var bool */
	private static $schemaReady = false;

	/** @return array<int,string> */
	public static function days(): array
	{
		return [
			0 => 'Sun',
			1 => 'Mon',
			2 => 'Tue',
			3 => 'Wed',
			4 => 'Thu',
			5 => 'Fri',
			6 => 'Sat',
		];
	}

	/**
	 * Cooker, cleaner, and security posts are not prep invigilators.
	 * "cook" also matches Cooker and Cooks.
	 */
	public static function excludedPrepPost(string $title): bool
	{
		$t = strtolower(trim(preg_replace('/\s+/', ' ', $title) ?? ''));
		if ($t === '') {
			return false;
		}
		foreach (['cook', 'cleaner', 'security'] as $needle) {
			if (strpos($t, $needle) !== false) {
				return true;
			}
		}
		return false;
	}

	public function ensureSchema(): void
	{
		if (self::$schemaReady) {
			return;
		}
		$db = \Config\Database::connect();
		$db->query("CREATE TABLE IF NOT EXISTS `prep_timetable` (
			`school_id` INT UNSIGNED NOT NULL,
			`morning_start` TIME NOT NULL DEFAULT '05:30:00',
			`morning_end` TIME NOT NULL DEFAULT '06:30:00',
			`evening_start` TIME NOT NULL DEFAULT '19:00:00',
			`evening_end` TIME NOT NULL DEFAULT '21:00:00',
			`updated_at` DATETIME NULL DEFAULT NULL,
			PRIMARY KEY (`school_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
		$db->query("CREATE TABLE IF NOT EXISTS `prep_timetable_duty` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`school_id` INT UNSIGNED NOT NULL,
			`day_of_week` TINYINT UNSIGNED NOT NULL,
			`slot` VARCHAR(16) NOT NULL,
			`staff_id` INT UNSIGNED NOT NULL,
			`sort_order` INT NOT NULL DEFAULT 0,
			PRIMARY KEY (`id`),
			UNIQUE KEY `uniq_prep_duty` (`school_id`, `day_of_week`, `slot`, `staff_id`),
			KEY `idx_prep_duty_school` (`school_id`, `day_of_week`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
		$db->query("CREATE TABLE IF NOT EXISTS `prep_invigilation_attendance` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`school_id` INT UNSIGNED NOT NULL,
			`staff_id` INT UNSIGNED NOT NULL,
			`duty_date` DATE NOT NULL,
			`slot` VARCHAR(16) NOT NULL,
			`area_id` INT UNSIGNED NOT NULL DEFAULT 0,
			`time_in` INT UNSIGNED NOT NULL,
			PRIMARY KEY (`id`),
			UNIQUE KEY `uniq_prep_att` (`school_id`, `staff_id`, `duty_date`, `slot`),
			KEY `idx_prep_att_date` (`school_id`, `duty_date`, `slot`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
		self::$schemaReady = true;
	}

	/**
	 * Morning prep / Evening prep attendance locations. Other locations are not prep duty.
	 */
	public static function slotFromAreaName(string $name): string
	{
		$n = strtolower(trim($name));
		$n = trim((string) preg_replace('/[^a-z0-9]+/', ' ', $n));
		$prep = strpos($n, 'prep') !== false || strpos($n, 'preparation') !== false;
		if (!$prep) {
			return '';
		}
		if (strpos($n, 'morning') !== false) {
			return 'morning';
		}
		if (strpos($n, 'evening') !== false) {
			return 'evening';
		}
		return '';
	}

	/**
	 * @return array{morning_start:string,morning_end:string,evening_start:string,evening_end:string,duties:array<int,array{morning:list<int>,evening:list<int>}>}
	 */
	public function forSchool(int $schoolId): array
	{
		$this->ensureSchema();
		$db = \Config\Database::connect();
		$row = $db->table('prep_timetable')->where('school_id', $schoolId)->get(1)->getRowArray();
		$duties = [];
		foreach (self::days() as $day => $label) {
			$duties[$day] = ['morning' => [], 'evening' => []];
		}
		if ($schoolId > 0) {
			$assigned = $db->table('prep_timetable_duty')
				->select('day_of_week, slot, staff_id, sort_order')
				->where('school_id', $schoolId)
				->orderBy('sort_order', 'ASC')
				->orderBy('id', 'ASC')
				->get()
				->getResultArray();
			foreach ($assigned as $item) {
				$day = (int) ($item['day_of_week'] ?? -1);
				$slot = (string) ($item['slot'] ?? '');
				$staffId = (int) ($item['staff_id'] ?? 0);
				if (!isset($duties[$day]) || !isset($duties[$day][$slot]) || $staffId < 1) {
					continue;
				}
				$duties[$day][$slot][] = $staffId;
			}
		}
		return [
			'morning_start' => self::hhmm($row['morning_start'] ?? '05:30:00', '05:30'),
			'morning_end' => self::hhmm($row['morning_end'] ?? '06:30:00', '06:30'),
			'evening_start' => self::hhmm($row['evening_start'] ?? '19:00:00', '19:00'),
			'evening_end' => self::hhmm($row['evening_end'] ?? '21:00:00', '21:00'),
			'duties' => $duties,
		];
	}

	/**
	 * Active staff except Cooker, Cleaner, and Security.
	 *
	 * @return list<array{id:int,name:string,post:string}>
	 */
	public function eligibleStaff(int $schoolId): array
	{
		if ($schoolId < 1) {
			return [];
		}
		$db = \Config\Database::connect();
		// Status 1 and 2 are both "active" on the staff list. Status 0 is locked.
		$result = $db->query(
			"SELECT s.id, s.fname, s.lname, p.title AS post_title
			 FROM staffs s
			 LEFT JOIN posts p ON p.id = s.post
			 WHERE s.school_id = ? AND s.status IN (1, 2)
			 ORDER BY s.fname ASC, s.lname ASC",
			[$schoolId]
		);
		$rows = is_object($result) ? $result->getResultArray() : [];
		$out = [];
		foreach ($rows as $row) {
			$title = trim((string) ($row['post_title'] ?? ''));
			if (self::excludedPrepPost($title)) {
				continue;
			}
			$out[] = [
				'id' => (int) $row['id'],
				'name' => trim((string) ($row['fname'] ?? '') . ' ' . (string) ($row['lname'] ?? '')),
				'post' => $title !== '' ? $title : 'Staff',
			];
		}
		usort($out, static function (array $a, array $b): int {
			return strcasecmp($a['name'], $b['name']);
		});
		return $out;
	}

	/**
	 * @param array<string,mixed> $times
	 * @param array<int|string,array<string,mixed>> $assignments
	 * @return array{ok:bool,message:string}
	 */
	public function saveRota(int $schoolId, array $times, array $assignments): array
	{
		$this->ensureSchema();
		if ($schoolId < 1) {
			return ['ok' => false, 'message' => 'School is required'];
		}
		$morningStart = self::normalizeTime($times['morning_start'] ?? '', '05:30');
		$morningEnd = self::normalizeTime($times['morning_end'] ?? '', '06:30');
		$eveningStart = self::normalizeTime($times['evening_start'] ?? '', '19:00');
		$eveningEnd = self::normalizeTime($times['evening_end'] ?? '', '21:00');
		if ($morningStart >= $morningEnd) {
			return ['ok' => false, 'message' => 'Morning prep must end after it starts'];
		}
		if ($eveningStart >= $eveningEnd) {
			return ['ok' => false, 'message' => 'Evening prep must end after it starts'];
		}
		$allowed = [];
		foreach ($this->eligibleStaff($schoolId) as $staff) {
			$allowed[(int) $staff['id']] = true;
		}
		$rows = [];
		foreach (self::days() as $day => $label) {
			$cell = $assignments[$day] ?? $assignments[(string) $day] ?? [];
			if (!is_array($cell)) {
				continue;
			}
			foreach (['morning', 'evening'] as $slot) {
				$ids = $cell[$slot] ?? [];
				if (!is_array($ids)) {
					continue;
				}
				$seen = [];
				$order = 0;
				foreach ($ids as $id) {
					$staffId = (int) $id;
					if ($staffId < 1 || isset($seen[$staffId])) {
						continue;
					}
					if (!isset($allowed[$staffId])) {
						return ['ok' => false, 'message' => 'Cooker, cleaner, and security staff cannot invigilate prep'];
					}
					$seen[$staffId] = true;
					$rows[] = [
						'school_id' => $schoolId,
						'day_of_week' => $day,
						'slot' => $slot,
						'staff_id' => $staffId,
						'sort_order' => $order,
					];
					$order++;
					if ($order > 8) {
						return ['ok' => false, 'message' => 'Each prep can have at most 8 staff'];
					}
				}
			}
		}
		$db = \Config\Database::connect();
		$db->transStart();
		$exists = $db->table('prep_timetable')->where('school_id', $schoolId)->countAllResults();
		$payload = [
			'morning_start' => $morningStart . ':00',
			'morning_end' => $morningEnd . ':00',
			'evening_start' => $eveningStart . ':00',
			'evening_end' => $eveningEnd . ':00',
			'updated_at' => date('Y-m-d H:i:s'),
		];
		if ($exists > 0) {
			$db->table('prep_timetable')->where('school_id', $schoolId)->update($payload);
		} else {
			$payload['school_id'] = $schoolId;
			$db->table('prep_timetable')->insert($payload);
		}
		$db->table('prep_timetable_duty')->where('school_id', $schoolId)->delete();
		if ($rows !== []) {
			$db->table('prep_timetable_duty')->insertBatch($rows);
		}
		$db->transComplete();
		if ($db->transStatus() === false) {
			return ['ok' => false, 'message' => 'Could not save the preps timetable'];
		}
		return ['ok' => true, 'message' => 'Preps invigilation saved'];
	}

	/**
	 * Weekly rota for the USB reader. Staff with a card can tap offline at Morning prep and Evening prep.
	 *
	 * @return array{days:list<array{day:int,morning:list<array<string,mixed>>,evening:list<array<string,mixed>>}>}
	 */
	public function deviceRota(int $schoolId): array
	{
		$this->ensureSchema();
		$days = [];
		foreach (self::days() as $day => $label) {
			$days[$day] = ['day' => $day, 'morning' => [], 'evening' => []];
		}
		if ($schoolId < 1) {
			return ['days' => array_values($days)];
		}
		$db = \Config\Database::connect();
		$rows = $db->query(
			"SELECT d.day_of_week, d.slot, s.id, s.fname, s.lname, s.card, s.photo, p.title AS post_title
			 FROM prep_timetable_duty d
			 INNER JOIN staffs s ON s.id = d.staff_id AND s.school_id = d.school_id
			 LEFT JOIN posts p ON p.id = s.post
			 WHERE d.school_id = ? AND s.status IN (1, 2)
			 ORDER BY d.day_of_week ASC, d.slot ASC, d.sort_order ASC, d.id ASC",
			[$schoolId]
		);
		$list = is_object($rows) ? $rows->getResultArray() : [];
		foreach ($list as $row) {
			$day = (int) ($row['day_of_week'] ?? -1);
			$slot = (string) ($row['slot'] ?? '');
			$card = strtoupper(trim((string) ($row['card'] ?? '')));
			if (!isset($days[$day][$slot]) || strlen($card) < 4) {
				continue;
			}
			if (self::excludedPrepPost((string) ($row['post_title'] ?? ''))) {
				continue;
			}
			$title = trim((string) ($row['post_title'] ?? ''));
			$days[$day][$slot][] = [
				'id' => (int) ($row['id'] ?? 0),
				'name' => trim((string) ($row['fname'] ?? '') . ' ' . (string) ($row['lname'] ?? '')),
				'post' => $title !== '' ? $title : 'Staff',
				'card' => $card,
				'photo' => \App\Libraries\AttendanceScanService::staffUploadedPhotoUrl($row['photo'] ?? null),
			];
		}
		return ['days' => array_values($days)];
	}

	/**
	 * Staff card tap at a Morning prep or Evening prep location.
	 * Only the staff assigned to that weekday and slot are marked present.
	 *
	 * @return array<string,mixed>
	 */
	public function recordDutyTap(int $schoolId, int $staffId, int $areaId, int $eventTime = 0): array
	{
		$this->ensureSchema();
		(new AttendanceAreaModel())->ensureSchema();
		if ($schoolId < 1 || $staffId < 1 || $areaId < 1) {
			return ['success' => 0, 'kind' => 'prep', 'skipped' => 1, 'message' => 'School, staff, and location are required'];
		}
		$db = \Config\Database::connect();
		$area = $db->table('attendance_areas')
			->select('id, name, school_id')
			->where('id', $areaId)
			->where('school_id', $schoolId)
			->where('active', 1)
			->get()
			->getRowArray();
		$areaName = (string) ($area['name'] ?? '');
		$slot = self::slotFromAreaName($areaName);
		if ($slot === '') {
			return ['success' => 0, 'kind' => 'staff', 'message' => 'Staff must use face, not card'];
		}
		$staff = $db->query(
			"SELECT s.id, s.fname, s.lname, s.photo, s.status, p.title AS post_title
			 FROM staffs s
			 LEFT JOIN posts p ON p.id = s.post
			 WHERE s.id = ? AND s.school_id = ?
			 LIMIT 1",
			[$staffId, $schoolId]
		);
		$person = is_object($staff) ? $staff->getRowArray() : null;
		if (!$person || !in_array((int) ($person['status'] ?? 0), [1, 2], true)) {
			return ['success' => 0, 'kind' => 'prep', 'skipped' => 1, 'message' => 'Staff is not active'];
		}
		if (self::excludedPrepPost((string) ($person['post_title'] ?? ''))) {
			return ['success' => 0, 'kind' => 'prep', 'skipped' => 1, 'message' => 'Not on prep duty today'];
		}
		$time = $eventTime > 1000000000 ? $eventTime : time();
		$dutyDate = date('Y-m-d', $time);
		$dow = (int) date('w', $time);
		$duty = $db->table('prep_timetable_duty')
			->where('school_id', $schoolId)
			->where('day_of_week', $dow)
			->where('slot', $slot)
			->where('staff_id', $staffId)
			->get()
			->getRowArray();
		$title = trim((string) ($person['post_title'] ?? ''));
		$payloadPerson = [
			'id' => (int) $person['id'],
			'name' => trim((string) ($person['fname'] ?? '') . ' ' . (string) ($person['lname'] ?? '')),
			'class' => $title !== '' ? $title : 'Staff',
			'extra' => $title !== '' ? $title : 'Staff',
			'regno' => '',
			'photo' => \App\Libraries\AttendanceScanService::staffUploadedPhotoUrl($person['photo'] ?? null),
			'kind' => 'prep',
		];
		if (!$duty) {
			return [
				'success' => 0,
				'kind' => 'prep',
				'skipped' => 1,
				'message' => 'Not on prep duty today',
				'person' => $payloadPerson,
				'area' => ['id' => $areaId, 'name' => $areaName],
			];
		}
		$existing = $db->table('prep_invigilation_attendance')
			->where('school_id', $schoolId)
			->where('staff_id', $staffId)
			->where('duty_date', $dutyDate)
			->where('slot', $slot)
			->get()
			->getRowArray();
		$already = $existing !== null;
		$shown = $already ? (int) ($existing['time_in'] ?? $time) : $time;
		if (!$already) {
			$db->table('prep_invigilation_attendance')->insert([
				'school_id' => $schoolId,
				'staff_id' => $staffId,
				'duty_date' => $dutyDate,
				'slot' => $slot,
				'area_id' => $areaId,
				'time_in' => $time,
			]);
		}
		return [
			'success' => 1,
			'kind' => 'prep',
			'already' => $already ? 1 : 0,
			'status' => 'IN',
			'time' => date('H:i', $shown),
			'message' => $already ? 'Already present' : 'Invigilator present',
			'person' => $payloadPerson,
			'area' => ['id' => $areaId, 'name' => $areaName],
			'slot' => $slot,
		];
	}

	/**
	 * Present and absent invigilators for each day in the range.
	 * A person is present only after their card tap at that prep location.
	 *
	 * @return array{from:string,to:string,slot:string,days:list<array<string,mixed>>}
	 */
	public function attendanceReport(int $schoolId, string $from, string $to, string $slotFilter = ''): array
	{
		$this->ensureSchema();
		$fromTs = strtotime($from);
		$toTs = strtotime($to);
		if ($fromTs === false || $toTs === false || $schoolId < 1) {
			return ['from' => $from, 'to' => $to, 'slot' => $slotFilter, 'days' => []];
		}
		if ($fromTs > $toTs) {
			$swap = $fromTs;
			$fromTs = $toTs;
			$toTs = $swap;
		}
		if (($toTs - $fromTs) > 62 * 86400) {
			$toTs = $fromTs + 62 * 86400;
		}
		$fromDate = date('Y-m-d', $fromTs);
		$toDate = date('Y-m-d', $toTs);
		$slotFilter = in_array($slotFilter, ['morning', 'evening'], true) ? $slotFilter : '';
		$db = \Config\Database::connect();
		$dutyRows = $db->query(
			"SELECT d.day_of_week, d.slot, s.id, s.fname, s.lname, p.title AS post_title
			 FROM prep_timetable_duty d
			 INNER JOIN staffs s ON s.id = d.staff_id AND s.school_id = d.school_id
			 LEFT JOIN posts p ON p.id = s.post
			 WHERE d.school_id = ?
			 ORDER BY d.sort_order ASC, d.id ASC",
			[$schoolId]
		);
		$duties = is_object($dutyRows) ? $dutyRows->getResultArray() : [];
		$byDay = [];
		foreach ($duties as $row) {
			$day = (int) ($row['day_of_week'] ?? -1);
			$slot = (string) ($row['slot'] ?? '');
			if ($day < 0 || $day > 6 || ($slot !== 'morning' && $slot !== 'evening')) {
				continue;
			}
			if ($slotFilter !== '' && $slot !== $slotFilter) {
				continue;
			}
			if (self::excludedPrepPost((string) ($row['post_title'] ?? ''))) {
				continue;
			}
			$title = trim((string) ($row['post_title'] ?? ''));
			$byDay[$day][$slot][] = [
				'id' => (int) ($row['id'] ?? 0),
				'name' => trim((string) ($row['fname'] ?? '') . ' ' . (string) ($row['lname'] ?? '')),
				'post' => $title !== '' ? $title : 'Staff',
			];
		}
		$tapRows = $db->table('prep_invigilation_attendance')
			->select('staff_id, duty_date, slot, time_in')
			->where('school_id', $schoolId)
			->where('duty_date >=', $fromDate)
			->where('duty_date <=', $toDate)
			->get()
			->getResultArray();
		$taps = [];
		foreach ($tapRows as $tap) {
			$key = (string) ($tap['duty_date'] ?? '') . '|' . (string) ($tap['slot'] ?? '') . '|' . (int) ($tap['staff_id'] ?? 0);
			$taps[$key] = (int) ($tap['time_in'] ?? 0);
		}
		$slots = $slotFilter !== '' ? [$slotFilter] : ['morning', 'evening'];
		$out = [];
		for ($ts = $fromTs; $ts <= $toTs; $ts += 86400) {
			$date = date('Y-m-d', $ts);
			$dow = (int) date('w', $ts);
			$slotOut = [];
			$any = false;
			foreach ($slots as $slot) {
				$present = [];
				$absent = [];
				foreach ($byDay[$dow][$slot] ?? [] as $person) {
					$any = true;
					$key = $date . '|' . $slot . '|' . (int) $person['id'];
					if (isset($taps[$key]) && $taps[$key] > 0) {
						$person['time'] = date('H:i', $taps[$key]);
						$present[] = $person;
					} else {
						$person['time'] = '';
						$absent[] = $person;
					}
				}
				$slotOut[$slot] = ['present' => $present, 'absent' => $absent];
			}
			if (!$any) {
				continue;
			}
			$out[] = [
				'date' => $date,
				'day_num' => (int) date('j', $ts),
				'label' => date('D j M Y', $ts),
				'slots' => $slotOut,
			];
		}
		return ['from' => $fromDate, 'to' => $toDate, 'slot' => $slotFilter, 'days' => $out];
	}

	private static function hhmm($value, string $fallback): string
	{
		$text = substr(trim((string) $value), 0, 5);
		return preg_match('/^\d{2}:\d{2}$/', $text) ? $text : $fallback;
	}

	private static function normalizeTime($value, string $fallback): string
	{
		$text = substr(trim((string) $value), 0, 5);
		if (!preg_match('/^\d{2}:\d{2}$/', $text)) {
			return $fallback;
		}
		[$h, $m] = array_map('intval', explode(':', $text));
		if ($h < 0 || $h > 23 || $m < 0 || $m > 59) {
			return $fallback;
		}
		return sprintf('%02d:%02d', $h, $m);
	}
}
