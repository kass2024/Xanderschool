<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Weekly morning and evening prep duty rota.
 * Staff must hold a Teacher, Patron, or Matron post.
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
	 * Teacher, Patron, or Matron. Leadership titles such as Head Teacher are not prep posts.
	 */
	public static function postGroup(string $title): string
	{
		$t = strtolower(trim(preg_replace('/\s+/', ' ', $title) ?? ''));
		if ($t === '') {
			return '';
		}
		if (preg_match('/\b(head|deputy|director|principal|accountant|mistress)\b/', $t)) {
			return '';
		}
		if (preg_match('/\bmatrons?\b/', $t)) {
			return 'Matron';
		}
		if (preg_match('/\bpatrons?\b/', $t)) {
			return 'Patron';
		}
		if (preg_match('/\bteachers?\b/', $t)) {
			return 'Teacher';
		}
		return '';
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
		self::$schemaReady = true;
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
	 * Active staff whose post is Teacher, Patron, or Matron.
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
			 INNER JOIN posts p ON p.id = s.post
			 WHERE s.school_id = ? AND s.status IN (1, 2)
			 ORDER BY s.fname ASC, s.lname ASC",
			[$schoolId]
		);
		$rows = is_object($result) ? $result->getResultArray() : [];
		$out = [];
		foreach ($rows as $row) {
			$group = self::postGroup((string) ($row['post_title'] ?? ''));
			if ($group === '') {
				continue;
			}
			$out[] = [
				'id' => (int) $row['id'],
				'name' => trim((string) ($row['fname'] ?? '') . ' ' . (string) ($row['lname'] ?? '')),
				'post' => $group,
			];
		}
		usort($out, static function (array $a, array $b): int {
			$order = ['Teacher' => 0, 'Patron' => 1, 'Matron' => 2];
			$cmp = ($order[$a['post']] ?? 9) <=> ($order[$b['post']] ?? 9);
			if ($cmp !== 0) {
				return $cmp;
			}
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
						return ['ok' => false, 'message' => 'Choose only staff whose post is Teacher, Patron, or Matron'];
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
