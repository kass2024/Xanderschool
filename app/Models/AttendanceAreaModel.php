<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * School-defined locations for student IN/OUT NFC attendance (Library, Cafeteria, …).
 */
class AttendanceAreaModel extends Model
{
	protected $table = 'attendance_areas';
	protected $primaryKey = 'id';
	protected $returnType = 'array';
	protected $allowedFields = ['school_id', 'name', 'sort_order', 'active'];
	protected $useTimestamps = true;

	/** @var bool */
	private static $schemaReady = false;

	public function ensureSchema(): void
	{
		if (self::$schemaReady) {
			return;
		}

		$db = \Config\Database::connect();

		$db->query("CREATE TABLE IF NOT EXISTS `attendance_areas` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`school_id` INT UNSIGNED NOT NULL,
			`name` VARCHAR(120) NOT NULL,
			`sort_order` INT NOT NULL DEFAULT 0,
			`active` TINYINT(1) NOT NULL DEFAULT 1,
			`created_at` DATETIME NULL DEFAULT NULL,
			`updated_at` DATETIME NULL DEFAULT NULL,
			PRIMARY KEY (`id`),
			KEY `idx_aa_school` (`school_id`, `active`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

		if ($db->tableExists('attendance_records')) {
			$fields = $db->getFieldNames('attendance_records');
			if (!in_array('area_id', $fields, true)) {
				try {
					$db->query("ALTER TABLE `attendance_records` ADD COLUMN `area_id` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `school_id`");
				} catch (\Throwable $e) {
					// column may exist
				}
			}
			try {
				$idx = $db->query("SHOW INDEX FROM `attendance_records` WHERE Key_name = 'idx_att_user_area'")->getResultArray();
				if (empty($idx)) {
					$db->query("ALTER TABLE `attendance_records` ADD KEY `idx_att_user_area` (`user_id`, `school_id`, `area_id`, `time_in`)");
				}
			} catch (\Throwable $e) {
				// ignore
			}
		}

		self::$schemaReady = true;
	}

	public function listAreas(int $schoolId, bool $activeOnly = true): array
	{
		$this->ensureSchema();
		$this->ensureDefaultArea($schoolId);
		$b = $this->where('school_id', $schoolId);
		if ($activeOnly) {
			$b->where('active', 1);
		}
		return $b->orderBy('sort_order', 'ASC')->orderBy('name', 'ASC')->findAll();
	}

	public function getActiveForSchool(int $schoolId, int $areaId): ?array
	{
		$row = $this->getForSchool($schoolId, $areaId);
		if (!$row || (int) ($row['active'] ?? 0) !== 1) {
			return null;
		}
		return $row;
	}

	public function getForSchool(int $schoolId, int $areaId): ?array
	{
		$this->ensureSchema();
		if ($areaId <= 0 || $schoolId <= 0) {
			return null;
		}
		$row = $this->where('school_id', $schoolId)
			->where('id', $areaId)
			->first();
		return $row ?: null;
	}

	public function ensureDefaultArea(int $schoolId): int
	{
		$this->ensureSchema();
		if ($schoolId <= 0) {
			return 0;
		}

		$gate = null;
		foreach ($this->where('school_id', $schoolId)->findAll() as $row) {
			if (strcasecmp(trim((string) ($row['name'] ?? '')), 'School gate') === 0) {
				$gate = $row;
				break;
			}
		}

		if (!$gate) {
			$active = $this->where('school_id', $schoolId)->where('active', 1)->first();
			if ($active) {
				return (int) $active['id'];
			}
			$gateId = (int) $this->insert([
				'school_id' => $schoolId,
				'name' => 'School gate',
				'sort_order' => 0,
				'active' => 1,
			]);
			if ($gateId > 0) {
				$this->backfillLegacyRecords($schoolId, $gateId);
			}
			return $gateId;
		}

		$gateId = (int) $gate['id'];
		if ((int) ($gate['active'] ?? 0) !== 1) {
			$this->update($gateId, ['active' => 1]);
		}
		$this->backfillLegacyRecords($schoolId, $gateId);
		return $gateId;
	}

	private function backfillLegacyRecords(int $schoolId, int $areaId): void
	{
		if ($areaId <= 0) {
			return;
		}
		$db = \Config\Database::connect();
		if (!$db->tableExists('attendance_records')) {
			return;
		}
		$fields = $db->getFieldNames('attendance_records');
		if (!in_array('area_id', $fields, true)) {
			return;
		}
		$db->table('attendance_records')
			->where('school_id', $schoolId)
			->where('area_id', 0)
			->update(['area_id' => $areaId]);
	}

	/**
	 * Today's IN/OUT totals for one area.
	 *
	 * @return array{inside:int,checked_in:int,checked_out:int,scans:int,still_in_pct:int}
	 */
	public function todayKpi(int $schoolId, int $areaId): array
	{
		$this->ensureSchema();
		$empty = [
			'inside' => 0,
			'checked_in' => 0,
			'checked_out' => 0,
			'scans' => 0,
			'still_in_pct' => 0,
		];
		if ($schoolId <= 0 || $areaId <= 0) {
			return $empty;
		}

		$db = \Config\Database::connect();
		if (!$db->tableExists('attendance_records')) {
			return $empty;
		}

		$todayStart = strtotime('today');
		$todayEnd = strtotime('tomorrow') - 1;
		$row = $db->table('attendance_records')
			->select('COUNT(*) AS checked_in, SUM(CASE WHEN COALESCE(time_out, 0) = 0 THEN 1 ELSE 0 END) AS inside, SUM(CASE WHEN COALESCE(time_out, 0) > 0 THEN 1 ELSE 0 END) AS checked_out', false)
			->where('school_id', $schoolId)
			->where('area_id', $areaId)
			->where('user_type', 0)
			->where('time_in >=', $todayStart)
			->where('time_in <=', $todayEnd)
			->get()
			->getRowArray();

		$in = (int) ($row['checked_in'] ?? 0);
		$inside = (int) ($row['inside'] ?? 0);
		$out = (int) ($row['checked_out'] ?? 0);
		return [
			'inside' => $inside,
			'checked_in' => $in,
			'checked_out' => $out,
			'scans' => $in + $out,
			'still_in_pct' => $in > 0 ? (int) round($inside / $in * 100) : 0,
		];
	}

	/**
	 * Inside / checked-in counts for every active area today.
	 *
	 * @return list<array{id:int,name:string,inside:int,checked_in:int}>
	 */
	public function todayAreaSummaries(int $schoolId): array
	{
		$areas = $this->listAreas($schoolId, true);
		if ($areas === []) {
			return [];
		}

		$db = \Config\Database::connect();
		$counts = [];
		if ($db->tableExists('attendance_records')) {
			$todayStart = strtotime('today');
			$todayEnd = strtotime('tomorrow') - 1;
			$rows = $db->table('attendance_records')
				->select('area_id, COUNT(*) AS checked_in, SUM(CASE WHEN COALESCE(time_out, 0) = 0 THEN 1 ELSE 0 END) AS inside', false)
				->where('school_id', $schoolId)
				->where('user_type', 0)
				->where('time_in >=', $todayStart)
				->where('time_in <=', $todayEnd)
				->groupBy('area_id')
				->get()
				->getResultArray();
			foreach ($rows as $r) {
				$counts[(int) $r['area_id']] = [
					'inside' => (int) ($r['inside'] ?? 0),
					'checked_in' => (int) ($r['checked_in'] ?? 0),
				];
			}
		}

		$out = [];
		foreach ($areas as $a) {
			$id = (int) $a['id'];
			$out[] = [
				'id' => $id,
				'name' => (string) ($a['name'] ?? ''),
				'inside' => $counts[$id]['inside'] ?? 0,
				'checked_in' => $counts[$id]['checked_in'] ?? 0,
			];
		}
		return $out;
	}

	/**
	 * Latest students scanned in this location today (one row each: IN + OUT).
	 *
	 * @return list<array<string,mixed>>
	 */
	public function recentEvents(int $schoolId, int $areaId, int $limit = 8): array
	{
		$this->ensureSchema();
		if ($schoolId <= 0 || $areaId <= 0) {
			return [];
		}

		$db = \Config\Database::connect();
		if (!$db->tableExists('attendance_records')) {
			return [];
		}

		$todayStart = strtotime('today');
		$todayEnd = strtotime('tomorrow') - 1;
		$rows = $db->table('attendance_records ar')
			->select('ar.time_in, ar.time_out, s.fname, s.lname, s.regno, s.photo')
			->join('students s', 's.id = ar.user_id')
			->where('ar.school_id', $schoolId)
			->where('ar.area_id', $areaId)
			->where('ar.user_type', 0)
			->where('ar.time_in >=', $todayStart)
			->where('ar.time_in <=', $todayEnd)
			->orderBy('IF(ar.time_out > 0, ar.time_out, ar.time_in)', 'DESC', false)
			->limit($limit)
			->get()
			->getResultArray();

		$events = [];
		foreach ($rows as $r) {
			$name = trim((string) ($r['fname'] ?? '') . ' ' . (string) ($r['lname'] ?? ''));
			$inTs = (int) ($r['time_in'] ?? 0);
			$outTs = (int) ($r['time_out'] ?? 0);
			$events[] = [
				'name' => $name !== '' ? $name : 'Student',
				'regno' => (string) ($r['regno'] ?? ''),
				'photo' => $r['photo'] ?? null,
				'time_in' => $inTs,
				'time_out' => $outTs,
				'status' => $outTs > 0 ? 'OUT' : 'IN',
			];
		}

		return $events;
	}

	/**
	 * Monthly IN/OUT report for class/area filters (0 = all).
	 *
	 * @return array<string,mixed>
	 */
	public function monthReport(int $schoolId, string $monthYm, int $classId, int $areaId, int $academicYear): array
	{
		$this->ensureSchema();
		$parts = explode('-', $monthYm);
		$mm = isset($parts[0]) ? (int) $parts[0] : (int) date('n');
		$yy = isset($parts[1]) ? (int) $parts[1] : (int) date('Y');
		$lastDay = (int) date('t', strtotime(sprintf('%04d-%02d-01', $yy, $mm)));
		$monthLabel = date('F Y', strtotime(sprintf('%04d-%02d-01', $yy, $mm)));

		$areas = $this->listAreas($schoolId, false);
		$areaNames = [];
		foreach ($areas as $a) {
			$areaNames[(int) $a['id']] = (string) $a['name'];
		}

		$db = \Config\Database::connect();
		$stQ = $db->table('students s')
			->select("s.id, s.fname, s.lname, s.regno, CONCAT(COALESCE(l.title,''),' ',COALESCE(d.code,''),' ',COALESCE(c.title,'')) AS class_name, c.id AS class_id")
			->join('class_records cr', 'cr.student = s.id')
			->join('classes c', 'c.id = cr.class', 'left')
			->join('levels l', 'l.id = c.level', 'left')
			->join('departments d', 'd.id = c.department', 'left')
			->where('s.school_id', $schoolId)
			->where('cr.year', $academicYear);
		if ($classId > 0) {
			$stQ->where('cr.class', $classId);
		}
		$students = $stQ->groupBy('s.id, s.fname, s.lname, s.regno, l.title, d.code, c.title, c.id')
			->orderBy('s.fname', 'ASC')->orderBy('s.lname', 'ASC')->get()->getResultArray();

		$ids = [];
		foreach ($students as $s) {
			$ids[] = (int) $s['id'];
		}

		$recs = [];
		if ($ids !== [] && $db->tableExists('attendance_records')) {
			$recQ = $db->table('attendance_records')
				->select('user_id, area_id, time_in, time_out')
				->where('school_id', $schoolId)
				->where('user_type', 0)
				->where("DATE_FORMAT(FROM_UNIXTIME(time_in),'%m-%Y') = " . $db->escape($monthYm), null, false)
				->whereIn('user_id', $ids);
			if ($areaId > 0) {
				$recQ->where('area_id', $areaId);
			}
			$recs = $recQ->orderBy('time_in', 'ASC')->get()->getResultArray();
		}

		$byStudent = [];
		$areaStats = [];
		foreach ($areaNames as $aid => $aname) {
			if ($areaId > 0 && $aid !== $areaId) {
				continue;
			}
			$areaStats[$aid] = [
				'id' => $aid,
				'name' => $aname,
				'students' => 0,
				'in_count' => 0,
				'out_count' => 0,
				'missing_out' => 0,
			];
		}

		$seenAreaStudent = [];
		$inCount = 0;
		$outCount = 0;
		$missingOut = 0;
		$scannedIds = [];
		$missingOutRows = [];
		$visits = [];
		$dayStats = [];

		foreach ($recs as $r) {
			$sid = (int) $r['user_id'];
			$aid = (int) $r['area_id'];
			$tin = (int) $r['time_in'];
			$tout = (int) ($r['time_out'] ?? 0);
			$day = (int) date('j', $tin);
			$aname = $areaNames[$aid] ?? ('Area #' . $aid);
			$inHm = date('H:i', $tin);
			$outHm = $tout > 0 ? date('H:i', $tout) : '';
			$dur = '';
			if ($tout > $tin) {
				$mins = (int) floor(($tout - $tin) / 60);
				$dur = $mins >= 60
					? ((int) floor($mins / 60)) . 'h ' . ($mins % 60) . 'm'
					: $mins . ' min';
			}
			$byStudent[$sid][$day][] = [
				'in' => $inHm,
				'out' => $outHm,
				'area_id' => $aid,
				'area' => $aname,
			];
			$visits[] = [
				'student_id' => $sid,
				'day' => $day,
				'in' => $inHm,
				'out' => $outHm,
				'duration' => $dur,
				'area_id' => $aid,
				'area' => $aname,
				'complete' => $tout > 0,
			];
			if (!isset($dayStats[$day])) {
				$dayStats[$day] = ['day' => $day, 'in_count' => 0, 'out_count' => 0, 'missing_out' => 0, 'students' => []];
			}
			$dayStats[$day]['in_count']++;
			$dayStats[$day]['students'][$sid] = true;
			$inCount++;
			$scannedIds[$sid] = true;
			if (!isset($areaStats[$aid])) {
				$areaStats[$aid] = [
					'id' => $aid,
					'name' => $aname,
					'students' => 0,
					'in_count' => 0,
					'out_count' => 0,
					'missing_out' => 0,
				];
			}
			$areaStats[$aid]['in_count']++;
			$key = $sid . ':' . $aid;
			if (!isset($seenAreaStudent[$key])) {
				$seenAreaStudent[$key] = true;
				$areaStats[$aid]['students']++;
			}
			if ($tout > 0) {
				$outCount++;
				$areaStats[$aid]['out_count']++;
				$dayStats[$day]['out_count']++;
			} else {
				$missingOut++;
				$areaStats[$aid]['missing_out']++;
				$dayStats[$day]['missing_out']++;
				$missingOutRows[] = [
					'student_id' => $sid,
					'day' => $day,
					'in' => $inHm,
					'area' => $aname,
				];
			}
		}

		$never = [];
		$missingNamed = [];
		$studentMap = [];
		foreach ($students as &$s) {
			$sid = (int) $s['id'];
			$studentMap[$sid] = $s;
			$s['days'] = $byStudent[$sid] ?? [];
			$s['visit_days'] = count($s['days']);
			$s['scanned'] = isset($scannedIds[$sid]);
			if (!$s['scanned']) {
				$never[] = [
					'id' => $sid,
					'fname' => $s['fname'],
					'lname' => $s['lname'],
					'regno' => $s['regno'],
					'class_name' => $s['class_name'],
				];
			}
		}
		unset($s);

		foreach ($missingOutRows as $row) {
			$st = $studentMap[$row['student_id']] ?? null;
			if (!$st) {
				continue;
			}
			$missingNamed[] = $row + [
				'fname' => $st['fname'],
				'lname' => $st['lname'],
				'regno' => $st['regno'],
				'class_name' => $st['class_name'],
			];
		}

		foreach ($visits as &$v) {
			$st = $studentMap[$v['student_id']] ?? null;
			$v['fname'] = $st['fname'] ?? '';
			$v['lname'] = $st['lname'] ?? '';
			$v['regno'] = $st['regno'] ?? '';
			$v['class_name'] = $st['class_name'] ?? '';
		}
		unset($v);

		$daysOut = [];
		foreach ($dayStats as $d => $ds) {
			$daysOut[] = [
				'day' => (int) $d,
				'in_count' => (int) $ds['in_count'],
				'out_count' => (int) $ds['out_count'],
				'missing_out' => (int) $ds['missing_out'],
				'students' => count($ds['students']),
			];
		}
		usort($daysOut, static function ($a, $b) {
			return $a['day'] <=> $b['day'];
		});
		$defaultDay = 0;
		if ($daysOut !== []) {
			$today = (int) date('j');
			$sameMonth = ((int) date('n') === $mm && (int) date('Y') === $yy);
			$defaultDay = (int) $daysOut[count($daysOut) - 1]['day'];
			if ($sameMonth) {
				foreach ($daysOut as $ds) {
					if ((int) $ds['day'] === $today) {
						$defaultDay = $today;
						break;
					}
				}
			}
		}

		$total = count($students);
		$scanned = count($scannedIds);
		return [
			'students' => $students,
			'visits' => $visits,
			'active_days' => $daysOut,
			'default_day' => $defaultDay,
			'last_day' => $lastDay,
			'month_label' => $monthLabel,
			'month' => $monthYm,
			'kpi' => [
				'students' => $total,
				'scanned' => $scanned,
				'never' => max(0, $total - $scanned),
				'in_count' => $inCount,
				'out_count' => $outCount,
				'missing_out' => $missingOut,
				'coverage' => $total > 0 ? (int) round($scanned / $total * 100) : 0,
			],
			'area_stats' => array_values($areaStats),
			'never_scanned' => $never,
			'missing_out' => $missingNamed,
			'single_area' => $areaId > 0,
		];
	}
}
