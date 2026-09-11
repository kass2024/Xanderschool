<?php

namespace App\Libraries;

/**
 * ILO-style staff attendance metrics: attendance rate, absenteeism, punctuality,
 * late / early leave, incomplete checkout — using each staff member's shift.
 */
class StaffAttendanceReport
{
	/**
	 * @param array<string,mixed> $staff
	 * @param list<array{in:int,out:int}> $clocks
	 * @return array<string,mixed>
	 */
	public static function summarize(array $staff, string $date1, string $date2, array $clocks): array
	{
		helper('qonics');
		$shiftOptions = json_decode((string) ($staff['options'] ?? '[]'), true);
		if (!is_array($shiftOptions)) {
			$shiftOptions = [];
		}
		$created = date('Y-m-d', strtotime((string) ($staff['created_at'] ?? $date1)));
		if ($date1 < $created) {
			$date1 = $created;
		}

		$scheduled = get_total_days($date1, $date2, $shiftOptions);
		$leaveDays = self::leaveDays($staff, $date1, $date2);
		$shiftMeta = ['title' => (string) ($staff['title'] ?? ''), 'options' => json_encode($shiftOptions)];

		$present = 0;
		$lateMin = 0;
		$lateCount = 0;
		$earlyMin = 0;
		$earlyCount = 0;
		$ontime = 0;
		$clockIn = 0;
		$clockOut = 0;
		$nco = 0;
		$overtimeMin = 0;
		$workedSec = 0;
		$seen = [];
		$byDay = [];

		foreach ($clocks as $pair) {
			$inTs = (int) ($pair['in'] ?? 0);
			$outTs = (int) ($pair['out'] ?? 0);
			if ($inTs <= 0) {
				continue;
			}
			$key = date('Y-m-d', $inTs) . ':' . ($staff['id'] ?? '');
			if (isset($seen[$key])) {
				continue;
			}
			$seen[$key] = true;
			$window = StaffShiftClock::windowFor($shiftMeta, $inTs);
			if (empty($window['working'])) {
				continue;
			}
			$clockIn++;
			$present++;
			$inEval = StaffShiftClock::evaluateIn($inTs, $window);
			if ($inEval['code'] === 'late') {
				$lateCount++;
				$lateMin += (int) $inEval['minutes'];
			} else {
				$ontime++;
			}
			$outEval = null;
			if ($outTs > 0) {
				$clockOut++;
				$workedSec += max(0, $outTs - $inTs);
				$outEval = StaffShiftClock::evaluateOut($outTs, $window);
				if ($outEval['code'] === 'early_leave') {
					$earlyCount++;
					$earlyMin += (int) $outEval['minutes'];
				} elseif ($outEval['code'] === 'overtime') {
					$overtimeMin += (int) $outEval['minutes'];
				}
			} else {
				$nco++;
			}
			$day = date('Y-m-d', $inTs);
			$byDay[$day] = [
				'date' => $day,
				'label' => date('D d M Y', $inTs),
				'in' => date('H:i', $inTs),
				'out' => $outTs > 0 ? date('H:i', $outTs) : '',
				'duration' => $outTs > 0 ? self::formatDuration($outTs - $inTs) : '—',
				'late_min' => (int) ($inEval['code'] === 'late' ? $inEval['minutes'] : 0),
				'early_min' => (int) (($outEval['code'] ?? '') === 'early_leave' ? $outEval['minutes'] : 0),
				'shift' => trim(($window['start_label'] ?? '') . '–' . ($window['end_label'] ?? ''), '–'),
				'code' => self::dayCode($inEval, $outEval, $outTs),
				'label_status' => self::dayStatus($inEval, $outEval, $outTs),
			];
		}

		$absent = max(0, $scheduled - $present - $leaveDays);
		$attendanceRate = $scheduled > 0 ? (int) round(($present + $leaveDays) / $scheduled * 100) : 0;
		$absenteeism = $scheduled > 0 ? (int) round($absent / $scheduled * 100) : 0;
		$punctuality = $present > 0 ? (int) round($ontime / $present * 100) : 0;

		return [
			'id' => (int) ($staff['id'] ?? 0),
			'name' => trim((string) ($staff['fname'] ?? '') . ' ' . (string) ($staff['lname'] ?? '')),
			'post' => (string) ($staff['post_title'] ?? ''),
			'shift' => (string) ($staff['title'] ?? ''),
			'email' => (string) ($staff['email'] ?? ''),
			'phone' => (string) ($staff['phone'] ?? ''),
			'scheduled' => $scheduled,
			'present' => $present,
			'absent' => $absent,
			'leave' => $leaveDays,
			'late_min' => $lateMin,
			'late_count' => $lateCount,
			'early_min' => $earlyMin,
			'early_count' => $earlyCount,
			'ontime' => $ontime,
			'clock_in' => $clockIn,
			'clock_out' => $clockOut,
			'nco' => $nco,
			'overtime_min' => $overtimeMin,
			'hours_worked' => self::formatDuration($workedSec),
			'hours_worked_h' => round($workedSec / 3600, 1),
			'attendance_rate' => $attendanceRate,
			'absenteeism' => $absenteeism,
			'punctuality' => $punctuality,
			'days' => $byDay,
		];
	}

	/**
	 * Fill absent / leave / off days between date1 and date2.
	 *
	 * @param array<string,mixed> $summary
	 * @return list<array<string,mixed>>
	 */
	public static function calendarDays(array $staff, string $date1, string $date2, array $summary): array
	{
		helper('qonics');
		$shiftOptions = json_decode((string) ($staff['options'] ?? '[]'), true);
		if (!is_array($shiftOptions)) {
			$shiftOptions = [];
		}
		$created = date('Y-m-d', strtotime((string) ($staff['created_at'] ?? $date1)));
		if ($date1 < $created) {
			$date1 = $created;
		}
		$end = strtotime($date2) > strtotime(date('Y-m-d')) ? date('Y-m-d') : $date2;
		$leaveStart = (int) ($staff['leave_start'] ?? 0);
		$leaveEnd = (int) ($staff['leave_end'] ?? 0);
		$byDay = $summary['days'] ?? [];
		$out = [];
		$start = new \DateTime($date1);
		$stop = new \DateTime($end);
		$stop->modify('+1 day');
		foreach (new \DatePeriod($start, new \DateInterval('P1D'), $stop) as $dt) {
			$day = $dt->format('Y-m-d');
			$ts = strtotime($day);
			if (isset($byDay[$day])) {
				$out[] = $byDay[$day];
				continue;
			}
			$scheduled = false;
			foreach ($shiftOptions as $shift) {
				$opp = explode(' ', (string) $shift);
				if (isset($opp[0]) && strtolower($dt->format('D')) === strtolower(days_mini($opp[0]))) {
					$scheduled = true;
					break;
				}
			}
			if (!$scheduled) {
				continue;
			}
			$onLeave = $leaveStart > 0 && $ts >= $leaveStart && $ts <= $leaveEnd && $leaveStart <= time();
			$out[] = [
				'date' => $day,
				'label' => $dt->format('D d M Y'),
				'in' => '',
				'out' => '',
				'duration' => '—',
				'late_min' => 0,
				'early_min' => 0,
				'shift' => '',
				'code' => $onLeave ? 'leave' : 'absent',
				'label_status' => $onLeave ? 'Approved leave' : 'Absent',
			];
		}
		return $out;
	}

	/**
	 * @param list<array<string,mixed>> $rows
	 * @return array<string,int|float>
	 */
	public static function orgKpis(array $rows): array
	{
		$staff = count($rows);
		$scheduled = 0;
		$present = 0;
		$absent = 0;
		$leave = 0;
		$late = 0;
		$ontime = 0;
		$nco = 0;
		$hours = 0.0;
		foreach ($rows as $r) {
			$scheduled += (int) $r['scheduled'];
			$present += (int) $r['present'];
			$absent += (int) $r['absent'];
			$leave += (int) $r['leave'];
			$late += (int) $r['late_count'];
			$ontime += (int) $r['ontime'];
			$nco += (int) $r['nco'];
			$hours += (float) $r['hours_worked_h'];
		}
		return [
			'staff' => $staff,
			'scheduled' => $scheduled,
			'present' => $present,
			'absent' => $absent,
			'leave' => $leave,
			'late' => $late,
			'nco' => $nco,
			'hours' => round($hours, 1),
			'attendance_rate' => $scheduled > 0 ? (int) round(($present + $leave) / $scheduled * 100) : 0,
			'absenteeism' => $scheduled > 0 ? (int) round($absent / $scheduled * 100) : 0,
			'punctuality' => $present > 0 ? (int) round($ontime / $present * 100) : 0,
		];
	}

	/**
	 * Parse academic year title (e.g. 2026-2027) into inclusive calendar bounds.
	 * Rwanda school years run Sep–Aug.
	 *
	 * @return array{start:string,end:string,y1:int,y2:int,label:string}
	 */
	public static function academicYearBounds(?string $title): array
	{
		$label = trim((string) $title);
		$y1 = (int) date('Y');
		$y2 = $y1 + 1;
		if (preg_match('/(\d{4})\s*[-–\/]\s*(\d{4})/', $label, $m)) {
			$y1 = (int) $m[1];
			$y2 = (int) $m[2];
			if ($y2 < $y1) {
				$y2 = $y1 + 1;
			}
		} elseif (preg_match('/(\d{4})/', $label, $m)) {
			$y1 = (int) $m[1];
			$y2 = $y1 + 1;
		}
		return [
			'start' => sprintf('%04d-09-01', $y1),
			'end' => sprintf('%04d-08-31', $y2),
			'y1' => $y1,
			'y2' => $y2,
			'label' => $label !== '' ? $label : sprintf('%d-%d', $y1, $y2),
		];
	}

	/**
	 * Months that fall inside the academic year window (for month picker).
	 *
	 * @param array{start:string,end:string} $bounds
	 * @return list<array{value:string,label:string,year:int,month:int}>
	 */
	public static function academicYearMonths(array $bounds): array
	{
		$out = [];
		$cursor = new \DateTime(substr($bounds['start'], 0, 7) . '-01');
		$last = new \DateTime(substr($bounds['end'], 0, 7) . '-01');
		while ($cursor <= $last) {
			$out[] = [
				'value' => $cursor->format('Y-m'),
				'label' => $cursor->format('F Y'),
				'year' => (int) $cursor->format('Y'),
				'month' => (int) $cursor->format('n'),
			];
			$cursor->modify('+1 month');
		}
		return $out;
	}

	/**
	 * Clamp a date range into the academic year (and not past today for end).
	 *
	 * @param array{start:string,end:string} $bounds
	 * @return array{0:string,1:string}
	 */
	public static function clampToAcademicYear(string $date1, string $date2, array $bounds): array
	{
		$start = $bounds['start'];
		$end = $bounds['end'];
		$today = date('Y-m-d');
		if ($end > $today) {
			$end = $today;
		}
		if ($date1 === '' || $date1 < $start) {
			$date1 = $start;
		}
		if ($date1 > $end) {
			$date1 = $start;
		}
		if ($date2 === '' || $date2 > $end) {
			$date2 = $end;
		}
		if ($date2 < $date1) {
			$date2 = $date1;
		}
		return [$date1, $date2];
	}

	/**
	 * @param list<array<string,mixed>> $rows
	 * @return array<string,list<array<string,mixed>>>
	 */
	public static function groupByShift(array $rows): array
	{
		$groups = [];
		foreach ($rows as $row) {
			$key = trim((string) ($row['shift'] ?? ''));
			if ($key === '') {
				$key = 'Unassigned shift';
			}
			$groups[$key][] = $row;
		}
		ksort($groups, SORT_NATURAL | SORT_FLAG_CASE);
		return $groups;
	}

	/**
	 * @param list<int> $staffIds
	 * @return array<int,list<array{in:int,out:int}>>
	 */
	public static function loadClocksByStaff(array $staffIds, int $fromUnix, int $toUnix): array
	{
		$out = [];
		$staffIds = array_values(array_filter(array_map('intval', $staffIds)));
		if ($staffIds === []) {
			return $out;
		}
		$db = \Config\Database::connect();
		$chunks = array_chunk($staffIds, 200);
		foreach ($chunks as $chunk) {
			$rows = $db->table('attendance_records')
				->select('user_id, time_in, COALESCE(time_out, 0) AS time_out')
				->where('user_type', 1)
				->whereIn('user_id', $chunk)
				->where('time_in >=', $fromUnix)
				->where('time_in <=', $toUnix)
				->orderBy('time_in', 'ASC')
				->get()
				->getResultArray();
			$seen = [];
			foreach ($rows as $rec) {
				$uid = (int) $rec['user_id'];
				$inTs = (int) $rec['time_in'];
				$key = $uid . ':' . date('Y-m-d', $inTs);
				if (isset($seen[$key])) {
					continue;
				}
				$seen[$key] = true;
				$out[$uid][] = ['in' => $inTs, 'out' => (int) $rec['time_out']];
			}
		}
		return $out;
	}

	/**
	 * @param array<string,mixed> $staff
	 * @return list<array{in:int,out:int}>
	 */
	public static function parseConcatRecords(string $records): array
	{
		$out = [];
		if (trim($records) === '') {
			return $out;
		}
		foreach (explode(',', $records) as $in) {
			$tttt = explode(':', $in);
			$out[] = [
				'in' => (int) ($tttt[0] ?? 0),
				'out' => (int) ($tttt[1] ?? 0),
			];
		}
		return $out;
	}

	private static function leaveDays(array $staff, string $date1, string $date2): int
	{
		$lvStart = (int) ($staff['leave_start'] ?? 0);
		$lvEnd = (int) ($staff['leave_end'] ?? 0);
		if ($lvStart <= 0 || $lvEnd <= 0) {
			return 0;
		}
		if ($lvStart > strtotime($date2) || $lvEnd < strtotime($date1) || $lvStart > time()) {
			return 0;
		}
		$start = $lvStart < strtotime($date1) ? $date1 : date('Y-m-d', $lvStart);
		$end = $lvEnd > strtotime($date2) ? $date2 : date('Y-m-d', $lvEnd);
		if (strtotime($end) > time()) {
			$end = date('Y-m-d');
		}
		return get_days_difference($start, $end);
	}

	private static function formatDuration(int $seconds): string
	{
		if ($seconds <= 0) {
			return '0h';
		}
		$h = intdiv($seconds, 3600);
		$m = intdiv($seconds % 3600, 60);
		return $m > 0 ? $h . 'h ' . $m . 'm' : $h . 'h';
	}

	private static function dayCode(array $inEval, ?array $outEval, int $outTs): string
	{
		if ($outTs <= 0) {
			return 'nco';
		}
		if (($inEval['code'] ?? '') === 'late') {
			return 'late';
		}
		if (($outEval['code'] ?? '') === 'early_leave') {
			return 'early';
		}
		return 'present';
	}

	private static function dayStatus(array $inEval, ?array $outEval, int $outTs): string
	{
		$parts = [];
		if (($inEval['code'] ?? '') === 'late') {
			$parts[] = 'Late';
		} elseif (($inEval['code'] ?? '') === 'early') {
			$parts[] = 'Early IN';
		} else {
			$parts[] = 'On time';
		}
		if ($outTs <= 0) {
			$parts[] = 'No checkout';
		} elseif (($outEval['code'] ?? '') === 'early_leave') {
			$parts[] = 'Early leave';
		} elseif (($outEval['code'] ?? '') === 'overtime') {
			$parts[] = 'Overtime';
		}
		return implode(' · ', $parts);
	}
}
