<?php

namespace App\Libraries;

/**
 * Resolve a staff member's expected IN/OUT window from School settings shifts
 * (`shifts.options`: "weekday start end", weekday 0=Mon … 6=Sun, hours as 7.0 / 16.5).
 */
class StaffShiftClock
{
	public const GRACE_SECONDS = 59;

	public static function phpWeekdayToShiftDay(int $phpW): int
	{
		return $phpW === 0 ? 6 : $phpW - 1;
	}

	public static function decimalToSeconds(string $hour): int
	{
		$parts = explode('.', (string) $hour, 2);
		$hh = (int) ($parts[0] ?? 0);
		$frac = $parts[1] ?? '0';
		$mm = ($frac === '0' || $frac === '00' || $frac === '') ? 0 : 30;
		return ($hh * 60 + $mm) * 60;
	}

	/**
	 * @param array<string,mixed>|null $shift
	 * @return array{title:string,working:bool,overnight:bool,start_ts:int,end_ts:int,start_label:string,end_label:string,look_from:int,look_to:int}
	 */
	public static function windowFor(?array $shift, int $atTs): array
	{
		$empty = [
			'title' => (string) ($shift['title'] ?? ''),
			'working' => false,
			'overnight' => false,
			'start_ts' => 0,
			'end_ts' => 0,
			'start_label' => '',
			'end_label' => '',
			'look_from' => strtotime('today', $atTs),
			'look_to' => strtotime('tomorrow', $atTs) - 1,
		];
		if (!$shift) {
			return $empty;
		}

		$options = json_decode((string) ($shift['options'] ?? '[]'), true);
		if (!is_array($options)) {
			$options = [];
		}

		helper('qonics');
		foreach ([0, -1] as $dayOffset) {
			$dayStart = strtotime('today', $atTs + ($dayOffset * 86400));
			$parsed = self::parseDay($options, self::phpWeekdayToShiftDay((int) date('w', $dayStart)), $dayStart);
			if (!$parsed) {
				continue;
			}
			$openFrom = $parsed['start_ts'] - (2 * 3600);
			$openTo = $parsed['end_ts'] + (4 * 3600);
			if ($atTs >= $openFrom && $atTs <= $openTo) {
				$parsed['title'] = (string) ($shift['title'] ?? '');
				$parsed['working'] = true;
				$parsed['look_from'] = $openFrom;
				$parsed['look_to'] = $openTo;
				return $parsed;
			}
		}

		$dayStart = strtotime('today', $atTs);
		$parsed = self::parseDay($options, self::phpWeekdayToShiftDay((int) date('w', $dayStart)), $dayStart);
		if ($parsed) {
			$parsed['title'] = (string) ($shift['title'] ?? '');
			$parsed['working'] = true;
			$parsed['look_from'] = strtotime('today', $atTs);
			$parsed['look_to'] = strtotime('tomorrow', $atTs) - 1;
			return $parsed;
		}

		$empty['title'] = (string) ($shift['title'] ?? '');
		return $empty;
	}

	/**
	 * @param list<string> $options
	 * @return array{overnight:bool,start_ts:int,end_ts:int,start_label:string,end_label:string}|null
	 */
	private static function parseDay(array $options, int $shiftDay, int $dayStart): ?array
	{
		foreach ($options as $opt) {
			$p = preg_split('/\s+/', trim((string) $opt)) ?: [];
			if (count($p) < 3 || (int) $p[0] !== $shiftDay) {
				continue;
			}
			$startSec = self::decimalToSeconds((string) $p[1]);
			$endSec = self::decimalToSeconds((string) $p[2]);
			$overnight = $endSec <= $startSec;
			$endTs = $dayStart + $endSec;
			if ($overnight) {
				$endTs += 86400;
			}
			return [
				'overnight' => $overnight,
				'start_ts' => $dayStart + $startSec,
				'end_ts' => $endTs,
				'start_label' => hours((string) $p[1], 1),
				'end_label' => hours((string) $p[2], 1),
			];
		}
		return null;
	}

	/**
	 * @param array<string,mixed> $window
	 * @return array{code:string,label:string,detail:string,minutes:int}
	 */
	public static function evaluateIn(int $time, array $window): array
	{
		if (empty($window['working']) || empty($window['start_ts'])) {
			return ['code' => 'none', 'label' => 'Recorded', 'detail' => 'No shift hours for today', 'minutes' => 0];
		}
		$diff = $time - (int) $window['start_ts'];
		if ($diff < -60) {
			$mins = (int) round(abs($diff) / 60);
			return ['code' => 'early', 'label' => 'Early', 'detail' => $mins . ' min before shift start', 'minutes' => $mins];
		}
		if ($diff <= self::GRACE_SECONDS) {
			return ['code' => 'ontime', 'label' => 'On time', 'detail' => 'Shift starts ' . ($window['start_label'] ?? ''), 'minutes' => 0];
		}
		$mins = (int) round($diff / 60);
		return ['code' => 'late', 'label' => 'Late', 'detail' => $mins . ' min after ' . ($window['start_label'] ?? ''), 'minutes' => $mins];
	}

	/**
	 * @param array<string,mixed> $window
	 * @return array{code:string,label:string,detail:string,minutes:int}
	 */
	public static function evaluateOut(int $time, array $window): array
	{
		if (empty($window['working']) || empty($window['end_ts'])) {
			return ['code' => 'none', 'label' => 'Checked out', 'detail' => '', 'minutes' => 0];
		}
		$diff = (int) $window['end_ts'] - $time;
		if ($diff > self::GRACE_SECONDS) {
			$mins = (int) round($diff / 60);
			return ['code' => 'early_leave', 'label' => 'Early leave', 'detail' => $mins . ' min before ' . ($window['end_label'] ?? ''), 'minutes' => $mins];
		}
		if ($diff < -60) {
			$mins = (int) round(abs($diff) / 60);
			return ['code' => 'overtime', 'label' => 'Overtime', 'detail' => $mins . ' min after ' . ($window['end_label'] ?? ''), 'minutes' => $mins];
		}
		return ['code' => 'ontime', 'label' => 'On time', 'detail' => 'Shift ends ' . ($window['end_label'] ?? ''), 'minutes' => 0];
	}

	/**
	 * @return array{kpi: array<string,int>, recent: list<array<string,mixed>>}
	 */
	public static function dashboard(int $schoolId): array
	{
		helper('qonics');
		$db = \Config\Database::connect();
		$todayStart = strtotime('today');
		$todayEnd = strtotime('tomorrow') - 1;
		if ($schoolId <= 0 || !$db->tableExists('attendance_records')) {
			return [
				'kpi' => ['inside' => 0, 'checked_in' => 0, 'checked_out' => 0, 'late' => 0, 'ontime' => 0],
				'recent' => [],
			];
		}

		$rows = $db->table('attendance_records ar')
			->select('ar.time_in, ar.time_out, s.fname, s.lname, s.photo, p.title as post_title, sh.title as shift_title, sh.options')
			->join('staffs s', 's.id = ar.user_id')
			->join('posts p', 'p.id = s.post', 'left')
			->join('shifts sh', 'sh.id = s.shift_id', 'left')
			->where('ar.school_id', $schoolId)
			->where('ar.user_type', 1)
			->where('ar.time_in >=', $todayStart)
			->where('ar.time_in <=', $todayEnd)
			->orderBy('IF(ar.time_out > 0, ar.time_out, ar.time_in)', 'DESC', false)
			->get()
			->getResultArray();

		$inside = 0;
		$out = 0;
		$late = 0;
		$ontime = 0;
		$recent = [];
		foreach ($rows as $r) {
			$inTs = (int) ($r['time_in'] ?? 0);
			$outTs = (int) ($r['time_out'] ?? 0);
			$shift = [
				'title' => (string) ($r['shift_title'] ?? ''),
				'options' => $r['options'] ?? '[]',
			];
			$window = self::windowFor($shift, $inTs > 0 ? $inTs : time());
			$inEval = self::evaluateIn($inTs, $window);
			if ($outTs > 0) {
				$out++;
			} else {
				$inside++;
			}
			if ($inEval['code'] === 'late') {
				$late++;
			} elseif ($inEval['code'] === 'ontime' || $inEval['code'] === 'early') {
				$ontime++;
			}
			if (count($recent) < 10) {
				$name = trim((string) ($r['fname'] ?? '') . ' ' . (string) ($r['lname'] ?? ''));
				$recent[] = [
					'name' => $name !== '' ? $name : 'Staff',
					'post' => (string) ($r['post_title'] ?? ''),
					'shift' => (string) ($window['title'] ?? $r['shift_title'] ?? ''),
					'time_in' => $inTs > 0 ? date('H:i', $inTs) : '',
					'time_out' => $outTs > 0 ? date('H:i', $outTs) : '',
					'in_code' => $inEval['code'],
					'in_label' => $inEval['label'],
					'photo' => profile_photo_url($r['photo'] ?? null),
				];
			}
		}

		return [
			'kpi' => [
				'inside' => $inside,
				'checked_in' => count($rows),
				'checked_out' => $out,
				'late' => $late,
				'ontime' => $ontime,
			],
			'recent' => $recent,
		];
	}
}
