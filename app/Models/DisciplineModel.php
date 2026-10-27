<?php namespace App\Models;
use CodeIgniter\Model;

class DisciplineModel extends Model{
	protected $table = "disciplines";
	protected $allowedFields = ["school_id","type","code_id","occurrence","student_id","active_term","comment","marks","notify_parent","created_by","voided","void_reason","voided_by","voided_name","voided_at","lateness_key"];
	protected $useTimestamps = true;
	protected $primaryKey = 'id';
	protected $createdField  = 'created_at';
	protected $updatedField  = 'updated_at';

	public function ensureVoidColumns(): void
	{
		static $done = false;
		if ($done) {
			return;
		}
		$db = \Config\Database::connect();
		$add = [
			'voided' => 'ALTER TABLE disciplines ADD COLUMN voided TINYINT(1) NOT NULL DEFAULT 0',
			'void_reason' => 'ALTER TABLE disciplines ADD COLUMN void_reason VARCHAR(255) NULL',
			'voided_by' => 'ALTER TABLE disciplines ADD COLUMN voided_by INT NULL',
			'voided_name' => 'ALTER TABLE disciplines ADD COLUMN voided_name VARCHAR(120) NULL',
			'voided_at' => 'ALTER TABLE disciplines ADD COLUMN voided_at DATETIME NULL',
		];
		foreach ($add as $col => $sql) {
			if (!$db->fieldExists($col, 'disciplines')) {
				$db->query($sql);
			}
		}
		$done = true;
	}

	public const LATENESS_MARKS = 5;

	public function ensureLatenessColumn(): void
	{
		static $done = false;
		if ($done) {
			return;
		}
		$this->ensureVoidColumns();
		$db = \Config\Database::connect();
		if ($db->tableExists('disciplines') && !$db->fieldExists('lateness_key', 'disciplines')) {
			$db->query('ALTER TABLE disciplines ADD COLUMN lateness_key VARCHAR(80) NULL DEFAULT NULL');
		}
		if ($db->tableExists('disciplines')) {
			try {
				$idx = $db->query("SHOW INDEX FROM disciplines WHERE Key_name = 'uniq_disc_lateness'")->getResultArray();
				if ($idx === []) {
					$db->query('ALTER TABLE disciplines ADD UNIQUE KEY uniq_disc_lateness (lateness_key)');
				}
			} catch (\Throwable $e) {
				// index may already exist
			}
		}
		$done = true;
	}

	/**
	 * Deduct 5 discipline marks for one late arrival. Parents are not notified.
	 * The same student, location, and day is charged once.
	 *
	 * @param array<string,mixed> $area
	 */
	public function recordLateness(int $schoolId, int $studentId, array $area, int $eventTime): bool
	{
		if ($schoolId < 1 || $studentId < 1 || $eventTime < 1) {
			return false;
		}
		if (!\App\Models\AttendanceAreaModel::isLate($area, $eventTime)) {
			return false;
		}
		$this->ensureLatenessColumn();
		$areaId = (int) ($area['id'] ?? 0);
		$day = \App\Models\AttendanceAreaModel::schoolDay($eventTime);
		$key = $schoolId . ':' . $studentId . ':' . $areaId . ':' . $day;
		$db = \Config\Database::connect();
		$exists = $db->table('disciplines')->where('lateness_key', $key)->countAllResults();
		if ($exists > 0) {
			return false;
		}
		$term = $db->table('schools')->select('active_term')->where('id', $schoolId)->get()->getRow();
		$activeTerm = (int) ($term->active_term ?? 0);
		if ($activeTerm < 1) {
			return false;
		}
		$name = trim((string) ($area['name'] ?? ''));
		if ($name === '') {
			$name = 'Location';
		}
		$cut = \App\Models\AttendanceAreaModel::normalizeDailyStart((string) ($area['consider_lateness'] ?? ''));
		$comment = 'Late at ' . $name . '. Arrived ' . \App\Models\AttendanceAreaModel::schoolClock($eventTime) . '. Consider lateness ' . $cut . '.';
		try {
			$this->insert([
				'school_id' => $schoolId,
				'type' => 1,
				'code_id' => 0,
				'occurrence' => 1,
				'student_id' => $studentId,
				'active_term' => $activeTerm,
				'comment' => $comment,
				'marks' => self::LATENESS_MARKS,
				'notify_parent' => 0,
				'created_by' => 0,
				'voided' => 0,
				'lateness_key' => $key,
			]);
		} catch (\Throwable $e) {
			return false;
		}
		return true;
	}

	/**
	 * Students who never tapped this location today, after the consider-lateness clock.
	 * Parents are not notified. One charge per student, location, and day.
	 *
	 * @param array<string,mixed> $area
	 * @param list<int> $studentIds
	 */
	public function recordUntappedLateness(int $schoolId, array $area, array $studentIds, int $eventTime): int
	{
		if ($schoolId < 1 || $eventTime < 1 || !\App\Models\AttendanceAreaModel::isLate($area, $eventTime)) {
			return 0;
		}
		$this->ensureLatenessColumn();
		$ids = [];
		foreach ($studentIds as $id) {
			$id = (int) $id;
			if ($id > 0) {
				$ids[$id] = $id;
			}
		}
		if ($ids === []) {
			return 0;
		}
		$db = \Config\Database::connect();
		$term = $db->table('schools')->select('active_term')->where('id', $schoolId)->get()->getRow();
		$activeTerm = (int) ($term->active_term ?? 0);
		if ($activeTerm < 1) {
			return 0;
		}
		$areaId = (int) ($area['id'] ?? 0);
		$day = \App\Models\AttendanceAreaModel::schoolDay($eventTime);
		$name = trim((string) ($area['name'] ?? ''));
		if ($name === '') {
			$name = 'Location';
		}
		$cut = \App\Models\AttendanceAreaModel::normalizeDailyStart((string) ($area['consider_lateness'] ?? ''));
		$comment = 'Late at ' . $name . '. No tap by ' . $cut . '. Consider lateness ' . $cut . '.';
		$keys = [];
		foreach ($ids as $id) {
			$keys[$schoolId . ':' . $id . ':' . $areaId . ':' . $day] = $id;
		}
		$existing = [];
		foreach (array_chunk(array_keys($keys), 400) as $chunk) {
			$found = $db->table('disciplines')->select('lateness_key')->whereIn('lateness_key', $chunk)->get()->getResultArray();
			foreach ($found as $row) {
				$existing[(string) ($row['lateness_key'] ?? '')] = true;
			}
		}
		$stamp = date('Y-m-d H:i:s');
		$batch = [];
		foreach ($keys as $key => $id) {
			if (isset($existing[$key])) {
				continue;
			}
			$batch[] = [
				'school_id' => $schoolId,
				'type' => 1,
				'code_id' => 0,
				'occurrence' => 1,
				'student_id' => $id,
				'active_term' => $activeTerm,
				'comment' => $comment,
				'marks' => self::LATENESS_MARKS,
				'notify_parent' => 0,
				'created_by' => 0,
				'voided' => 0,
				'lateness_key' => $key,
				'created_at' => $stamp,
				'updated_at' => $stamp,
			];
		}
		if ($batch === []) {
			return 0;
		}
		$written = 0;
		foreach (array_chunk($batch, 200) as $chunk) {
			try {
				$db->table('disciplines')->insertBatch($chunk);
				$written += count($chunk);
			} catch (\Throwable $e) {
				foreach ($chunk as $row) {
					try {
						$db->table('disciplines')->insert($row);
						$written++;
					} catch (\Throwable $e2) {
					}
				}
			}
		}
		return $written;
	}

	/**
	 * @param array<string,mixed> $in
	 * @return array{mode:string,start:string,end:string,label:string,date:string,week:string,month:string,from:string,to:string}
	 */
	public static function latenessWindow(array $in): array
	{
		$mode = strtolower(trim((string) ($in['mode'] ?? 'day')));
		if (!in_array($mode, ['day', 'week', 'month', 'range'], true)) {
			$mode = 'day';
		}
		$validDay = static function (string $raw): string {
			return preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) ? $raw : '';
		};
		$today = date('Y-m-d');
		$day = $validDay(trim((string) ($in['date'] ?? ''))) ?: $today;
		$weekOf = $validDay(trim((string) ($in['week'] ?? ''))) ?: $today;
		$monthRaw = trim((string) ($in['month'] ?? ''));
		if (!preg_match('/^\d{4}-\d{2}$/', $monthRaw)) {
			$monthRaw = date('Y-m');
		}
		$from = $validDay(trim((string) ($in['from'] ?? '')));
		$to = $validDay(trim((string) ($in['to'] ?? '')));
		if ($mode === 'week') {
			$ts = strtotime($weekOf);
			$dow = (int) date('N', $ts);
			$start = date('Y-m-d', strtotime('-' . ($dow - 1) . ' days', $ts));
			$end = date('Y-m-d', strtotime('+' . (7 - $dow) . ' days', $ts));
		} elseif ($mode === 'month') {
			$start = $monthRaw . '-01';
			$end = date('Y-m-t', strtotime($start));
		} elseif ($mode === 'range') {
			if ($from === '') {
				$from = date('Y-m-01');
			}
			if ($to === '') {
				$to = $today;
			}
			if ($from > $to) {
				$swap = $from;
				$from = $to;
				$to = $swap;
			}
			$start = $from;
			$end = $to;
		} else {
			$start = $day;
			$end = $day;
		}
		$fmt = static function (string $ymd): string {
			$ts = strtotime($ymd);
			return $ts ? date('j M Y', $ts) : $ymd;
		};
		if ($start === $end) {
			$label = $fmt($start);
		} elseif ($mode === 'month') {
			$label = date('F Y', strtotime($start));
		} else {
			$label = $fmt($start) . ' – ' . $fmt($end);
		}
		return [
			'mode' => $mode,
			'start' => $start,
			'end' => $end,
			'label' => $label,
			'date' => $day,
			'week' => $weekOf,
			'month' => $monthRaw,
			'from' => $mode === 'range' ? $start : ($from !== '' ? $from : date('Y-m-01')),
			'to' => $mode === 'range' ? $end : ($to !== '' ? $to : $today),
		];
	}

	/**
	 * @return array{rows:list<array<string,mixed>>,students:int,arrivals:int,marks:int,places:array<string,int>}
	 */
	public function listLateness(int $schoolId, string $start, string $end, int $classId = 0): array
	{
		$this->ensureLatenessColumn();
		$empty = ['rows' => [], 'students' => 0, 'arrivals' => 0, 'marks' => 0, 'places' => []];
		if ($schoolId < 1 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
			return $empty;
		}
		$today = \App\Models\AttendanceAreaModel::schoolDay();
		if ($start <= $today && $today <= $end) {
			try {
				\App\Libraries\AttendanceScanService::chargeUntappedLateness($schoolId);
			} catch (\Throwable $e) {
				log_message('error', 'untapped lateness: ' . $e->getMessage());
			}
		}
		$db = \Config\Database::connect();
		$dayExpr = "SUBSTRING_INDEX(d.lateness_key, ':', -1)";
		$q = $db->table('disciplines d')
			->select("d.id, d.marks, d.comment, d.created_at, s.id AS student_id, s.fname, s.lname, s.regno, {$dayExpr} AS late_day, MAX(CONCAT(COALESCE(l.title,''),' ',COALESCE(dp.code,''),' ',COALESCE(c.title,''))) AS class_name", false)
			->join('students s', 's.id = d.student_id')
			->join('class_records cr', 'cr.student = s.id AND cr.status = 1', 'left')
			->join('classes c', 'c.id = cr.class', 'left')
			->join('levels l', 'l.id = c.level', 'left')
			->join('departments dp', 'dp.id = c.department', 'left')
			->where('d.school_id', $schoolId)
			->where($dayExpr . ' >= ' . $db->escape($start), null, false)
			->where($dayExpr . ' <= ' . $db->escape($end), null, false)
			->where('IFNULL(d.voided,0) = 0', null, false)
			->groupBy('d.id, d.marks, d.comment, d.created_at, s.id, s.fname, s.lname, s.regno, ' . $dayExpr, false)
			->orderBy('late_day', 'DESC')
			->orderBy('s.fname', 'ASC');
		if ($classId > 0) {
			$q->where('cr.class', $classId);
		}
		$rows = $q->get()->getResultArray();
		$students = [];
		$marks = 0;
		$places = [];
		foreach ($rows as &$row) {
			$students[(int) ($row['student_id'] ?? 0)] = true;
			$marks += (int) ($row['marks'] ?? 0);
			$comment = (string) ($row['comment'] ?? '');
			$row['place'] = '';
			$row['arrived'] = '';
			$row['cutoff'] = '';
			if (preg_match('/^Late at (.+)\. Arrived (\d{2}:\d{2})\. Consider lateness (\d{2}:\d{2})\./', $comment, $m)) {
				$row['place'] = $m[1];
				$row['arrived'] = $m[2];
				$row['cutoff'] = $m[3];
			} elseif (preg_match('/^Late at (.+)\. No tap by (\d{2}:\d{2})\. Consider lateness (\d{2}:\d{2})\./', $comment, $m)) {
				$row['place'] = $m[1];
				$row['arrived'] = 'No tap';
				$row['cutoff'] = $m[3];
			}
			$place = $row['place'] !== '' ? $row['place'] : 'Location';
			if (!isset($places[$place])) {
				$places[$place] = 0;
			}
			$places[$place]++;
		}
		unset($row);
		arsort($places);
		return [
			'rows' => $rows,
			'students' => count($students),
			'arrivals' => count($rows),
			'marks' => $marks,
			'places' => $places,
		];
	}

}
