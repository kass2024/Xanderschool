<?php

namespace App\Services;

/**
 * Wisdom master dashboard and cross-campus attendance for
 * Director, Executive Principal, and Deputy Director.
 */
class WisdomGroupOverview
{
	/** Executive Principal, Director, Deputy Director. */
	const LEADER_POSTS = [15, 29, 30];

	public function isLeaderPost($postId)
	{
		return in_array((int) $postId, self::LEADER_POSTS, true);
	}

	public function isWisdomMaster($schoolId)
	{
		$schoolId = (int) $schoolId;
		if ($schoolId < 1) {
			return false;
		}
		$db = \Config\Database::connect();
		if (!$db->fieldExists('is_master', 'schools')) {
			return false;
		}
		$row = $db->table('schools')->where('id', $schoolId)->get(1)->getRowArray();
		if (!$row || empty($row['is_master'])) {
			return false;
		}
		if ($this->nameIsWisdom($row['name'] ?? '') || $this->nameIsWisdom($row['acronym'] ?? '')) {
			return true;
		}
		$children = (new SchoolHierarchyService())->childSchools($schoolId);
		foreach ($children as $child) {
			if ($this->nameIsWisdom($child['name'] ?? '') || $this->nameIsWisdom($child['acronym'] ?? '')) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Group dashboard only while the leader is on the Wisdom master school.
	 */
	public function showGroupDashboard($homeSchoolId, $postId, $currentSchoolId)
	{
		$homeSchoolId = (int) $homeSchoolId;
		return $homeSchoolId > 0
			&& $homeSchoolId === (int) $currentSchoolId
			&& $this->isLeaderPost($postId)
			&& $this->isWisdomMaster($homeSchoolId);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function schools($masterId)
	{
		$masterId = (int) $masterId;
		$db = \Config\Database::connect();
		$master = $db->table('schools')->select('id, name')->where('id', $masterId)->get(1)->getRowArray();
		$list = [];
		if ($master) {
			$list[] = $master;
		}
		foreach ((new SchoolHierarchyService())->childSchools($masterId) as $child) {
			$list[] = [
				'id' => (int) ($child['id'] ?? 0),
				'name' => (string) ($child['name'] ?? ''),
			];
		}
		return $list;
	}

	/**
	 * Per-campus students, boys, girls, staff, and today's card attendance.
	 *
	 * @return array{schools: array<int, array<string, mixed>>, totals: array<string, int>}
	 */
	public function isSchoolHeadPost($postId)
	{
		return in_array((int) $postId, [1, 18, 25, 26], true);
	}

	public function summary($masterId, $yearId)
	{
		$built = $this->summarize($this->schools($masterId), $yearId, (int) $masterId);
		$built['single'] = false;
		return $built;
	}

	public function schoolSummary($schoolId, $yearId)
	{
		$schoolId = (int) $schoolId;
		$row = \Config\Database::connect()->table('schools')->select('id, name')->where('id', $schoolId)->get(1)->getRowArray();
		if (!$row) {
			return ['schools' => [], 'totals' => [], 'master_id' => 0, 'single' => true];
		}
		$built = $this->summarize([
			['id' => (int) $row['id'], 'name' => (string) ($row['name'] ?? '')],
		], $yearId, $schoolId);
		$built['single'] = true;
		return $built;
	}

	private function summarize(array $schools, $yearId, $masterId)
	{
		$ids = [];
		foreach ($schools as $school) {
			$id = (int) ($school['id'] ?? 0);
			if ($id > 0) {
				$ids[] = $id;
			}
		}
		$blank = ['students' => 0, 'boys' => 0, 'girls' => 0, 'parents' => 0, 'staff' => 0, 'students_present' => 0, 'student_out' => 0, 'student_inside' => 0, 'staff_present' => 0, 'staff_absent' => 0];
		$bySchool = [];
		foreach ($ids as $id) {
			$bySchool[$id] = $blank;
		}
		if ($ids && (int) $yearId > 0) {
			$db = \Config\Database::connect();
			$idList = implode(',', array_map('intval', $ids));
			$yearId = (int) $yearId;
			$studentSql = "SELECT s.school_id,
					COUNT(DISTINCT s.id) AS students,
					COUNT(DISTINCT CASE WHEN UPPER(TRIM(s.sex)) IN ('M','MALE','BOY') THEN s.id END) AS boys,
					COUNT(DISTINCT CASE WHEN UPPER(TRIM(s.sex)) IN ('F','FEMALE','GIRL') THEN s.id END) AS girls,
					COUNT(DISTINCT CASE WHEN
						NULLIF(TRIM(s.father), '') IS NOT NULL
						OR NULLIF(TRIM(s.mother), '') IS NOT NULL
						OR NULLIF(TRIM(s.guardian), '') IS NOT NULL
						OR NULLIF(TRIM(s.ft_phone), '') IS NOT NULL
						OR NULLIF(TRIM(s.mt_phone), '') IS NOT NULL
						OR NULLIF(TRIM(s.gd_phone), '') IS NOT NULL
					THEN s.id END) AS parents
				FROM students s
				INNER JOIN schools sch ON sch.id = s.school_id
				LEFT JOIN active_term atr ON atr.id = sch.active_term
				INNER JOIN class_records a ON a.student = s.id AND a.status = 1
					AND a.year = IFNULL(atr.academic_year, {$yearId})
				INNER JOIN classes cl ON cl.id = a.class
				LEFT JOIN levels l ON l.id = cl.level
				LEFT JOIN departments d ON d.id = cl.department
				WHERE s.status IN (1, 2)
					AND s.school_id IN ({$idList})
					AND IFNULL(cl.title,'') NOT LIKE '%Holiday%'
					AND IFNULL(l.title,'') NOT LIKE '%Holiday%'
					AND IFNULL(d.title,'') NOT LIKE '%Holiday%'
					AND IFNULL(d.code,'') NOT LIKE '%Holiday%'
				GROUP BY s.school_id";
			foreach ($db->query($studentSql)->getResultArray() as $row) {
				$sid = (int) $row['school_id'];
				if (!isset($bySchool[$sid])) {
					continue;
				}
				$bySchool[$sid]['students'] = (int) $row['students'];
				$bySchool[$sid]['boys'] = (int) $row['boys'];
				$bySchool[$sid]['girls'] = (int) $row['girls'];
				$bySchool[$sid]['parents'] = (int) ($row['parents'] ?? 0);
			}
			$missing = [];
			foreach ($bySchool as $sid => $stats) {
				if ((int) $stats['students'] === 0) {
					$missing[] = (int) $sid;
				}
			}
			if ($missing) {
				$missingList = implode(',', $missing);
				$fallbackSql = "SELECT school_id,
						COUNT(id) AS students,
						SUM(CASE WHEN UPPER(TRIM(sex)) IN ('M','MALE','BOY') THEN 1 ELSE 0 END) AS boys,
						SUM(CASE WHEN UPPER(TRIM(sex)) IN ('F','FEMALE','GIRL') THEN 1 ELSE 0 END) AS girls,
						SUM(CASE WHEN
							NULLIF(TRIM(father), '') IS NOT NULL
							OR NULLIF(TRIM(mother), '') IS NOT NULL
							OR NULLIF(TRIM(guardian), '') IS NOT NULL
							OR NULLIF(TRIM(ft_phone), '') IS NOT NULL
							OR NULLIF(TRIM(mt_phone), '') IS NOT NULL
							OR NULLIF(TRIM(gd_phone), '') IS NOT NULL
						THEN 1 ELSE 0 END) AS parents
					FROM students
					WHERE status IN (1, 2) AND school_id IN ({$missingList})
					GROUP BY school_id";
				foreach ($db->query($fallbackSql)->getResultArray() as $row) {
					$sid = (int) $row['school_id'];
					if (!isset($bySchool[$sid]) || (int) $bySchool[$sid]['students'] > 0) {
						continue;
					}
					$bySchool[$sid]['students'] = (int) $row['students'];
					$bySchool[$sid]['boys'] = (int) $row['boys'];
					$bySchool[$sid]['girls'] = (int) $row['girls'];
					$bySchool[$sid]['parents'] = (int) ($row['parents'] ?? 0);
				}
			}
			$staffSql = "SELECT school_id, COUNT(id) AS staff FROM staffs WHERE school_id IN ({$idList}) GROUP BY school_id";
			foreach ($db->query($staffSql)->getResultArray() as $row) {
				$sid = (int) $row['school_id'];
				if (isset($bySchool[$sid])) {
					$bySchool[$sid]['staff'] = (int) $row['staff'];
				}
			}
			$todayStart = strtotime(date('Y-m-d') . ' 00:00:00');
			$todayEnd = $todayStart + 86400;
			$attendSql = "SELECT school_id, user_type, COUNT(DISTINCT user_id) AS present_today
				FROM attendance_records
				WHERE user_type IN (0, 1)
					AND school_id IN ({$idList})
					AND time_in >= {$todayStart}
					AND time_in < {$todayEnd}
				GROUP BY school_id, user_type";
			foreach ($db->query($attendSql)->getResultArray() as $row) {
				$sid = (int) $row['school_id'];
				if (!isset($bySchool[$sid])) {
					continue;
				}
				$key = ((int) $row['user_type'] === 1) ? 'staff_present' : 'students_present';
				$bySchool[$sid][$key] = (int) $row['present_today'];
			}
			if ($db->tableExists('attendance_records')) {
				$flowSql = "SELECT school_id,
						COUNT(DISTINCT CASE WHEN COALESCE(time_out, 0) > 0 THEN user_id END) AS student_out,
						COUNT(DISTINCT CASE WHEN COALESCE(time_out, 0) = 0 THEN user_id END) AS student_inside
					FROM attendance_records
					WHERE user_type = 0
						AND school_id IN ({$idList})
						AND time_in >= {$todayStart}
						AND time_in < {$todayEnd}
					GROUP BY school_id";
				foreach ($db->query($flowSql)->getResultArray() as $row) {
					$sid = (int) $row['school_id'];
					if (!isset($bySchool[$sid])) {
						continue;
					}
					$bySchool[$sid]['student_out'] = (int) ($row['student_out'] ?? 0);
					$bySchool[$sid]['student_inside'] = (int) ($row['student_inside'] ?? 0);
				}
			}
		}
		$rows = [];
		$totals = $blank;
		foreach ($schools as $school) {
			$id = (int) ($school['id'] ?? 0);
			$stats = $bySchool[$id] ?? $blank;
			$rows[] = array_merge([
				'id' => $id,
				'name' => (string) ($school['name'] ?? ''),
				'is_master' => $id === (int) $masterId,
			], $stats);
			foreach ($blank as $key => $unused) {
				$totals[$key] += (int) $stats[$key];
			}
		}
		$today = $this->loadTodayStaff($ids);
		$masterLocations = $this->masterLocationFlow((int) $masterId);
		foreach ($rows as $i => $row) {
			$pack = $today[(int) $row['id']] ?? ['absent' => [], 'late' => [], 'early' => []];
			$rows[$i]['staff_absent'] = count($pack['absent']);
			$rows[$i]['late_today'] = $pack['late'];
			$rows[$i]['early_today'] = $pack['early'];
			$rows[$i]['locations'] = !empty($row['is_master']) ? $masterLocations : [];
			$totals['staff_absent'] += count($pack['absent']);
		}
		return ['schools' => $rows, 'totals' => $totals, 'master_id' => (int) $masterId];
	}

	/**
	 * Today's student IN/OUT by attendance location. Master school only.
	 *
	 * @return list<array{id:int,name:string,checked_in:int,checked_out:int,inside:int}>
	 */
	private function masterLocationFlow(int $masterId): array
	{
		if ($masterId < 1) {
			return [];
		}
		$db = \Config\Database::connect();
		if (!$db->tableExists('attendance_areas') || !$db->tableExists('attendance_records')) {
			return [];
		}
		$todayStart = strtotime(date('Y-m-d') . ' 00:00:00');
		$todayEnd = $todayStart + 86400;
		$sql = "SELECT aa.id, aa.name,
				COUNT(ar.id) AS checked_in,
				SUM(CASE WHEN COALESCE(ar.time_out, 0) > 0 THEN 1 ELSE 0 END) AS checked_out,
				SUM(CASE WHEN ar.id IS NOT NULL AND COALESCE(ar.time_out, 0) = 0 THEN 1 ELSE 0 END) AS inside
			FROM attendance_areas aa
			LEFT JOIN attendance_records ar
				ON ar.area_id = aa.id
				AND ar.school_id = aa.school_id
				AND ar.user_type = 0
				AND ar.time_in >= {$todayStart}
				AND ar.time_in < {$todayEnd}
			WHERE aa.school_id = {$masterId}
				AND aa.active = 1
			GROUP BY aa.id, aa.name, aa.sort_order
			ORDER BY aa.sort_order ASC, aa.name ASC";
		$out = [];
		foreach ($db->query($sql)->getResultArray() as $row) {
			$name = trim((string) ($row['name'] ?? ''));
			if ($name === '') {
				continue;
			}
			$out[] = [
				'id' => (int) ($row['id'] ?? 0),
				'name' => $name,
				'checked_in' => (int) ($row['checked_in'] ?? 0),
				'checked_out' => (int) ($row['checked_out'] ?? 0),
				'inside' => (int) ($row['inside'] ?? 0),
			];
		}
		return $out;
	}

	/**
	 * Printable list of staff who are in, or still absent, today.
	 *
	 * @param int[] $schoolIds
	 * @return array<int, array<string, mixed>>
	 */
	public function todayStaffList(array $schoolIds, $kind)
	{
		$pack = $this->loadTodayStaff($schoolIds);
		$kind = $kind === 'absent' ? 'absent' : 'in';
		$list = [];
		foreach ($pack as $school) {
			foreach ($school[$kind] as $person) {
				$list[] = $person;
			}
		}
		usort($list, static function ($a, $b) {
			$school = strcasecmp((string) $a['school'], (string) $b['school']);
			if ($school !== 0) {
				return $school;
			}
			return strcasecmp((string) $a['name'], (string) $b['name']);
		});
		return $list;
	}

	/**
	 * @param int[] $ids
	 * @return array<int, array{in: array, absent: array, late: array, early: array}>
	 */
	private function loadTodayStaff(array $ids)
	{
		$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
		$out = [];
		foreach ($ids as $id) {
			$out[$id] = ['in' => [], 'absent' => [], 'late' => [], 'early' => []];
		}
		if (!$ids) {
			return $out;
		}
		$db = \Config\Database::connect();
		$idList = implode(',', $ids);
		$todayStart = strtotime(date('Y-m-d') . ' 00:00:00');
		$todayEnd = $todayStart + 86400;
		$noon = strtotime(date('Y-m-d') . ' 12:00:00');
		$staffRows = $db->query(
			"SELECT s.id, s.school_id, s.fname, s.lname, sc.name AS school_name,
				p.title AS post_title, sh.title AS shift_title, sh.options AS shift_options
			FROM staffs s
			INNER JOIN schools sc ON sc.id = s.school_id
			LEFT JOIN posts p ON p.id = s.post
			LEFT JOIN shifts sh ON sh.id = s.shift_id
			WHERE s.school_id IN ({$idList}) AND IFNULL(s.status, 1) <> 0"
		)->getResultArray();
		$clocks = [];
		$clockRows = $db->query(
			"SELECT user_id, MIN(time_in) AS time_in
			FROM attendance_records
			WHERE user_type = 1 AND school_id IN ({$idList})
				AND time_in >= {$todayStart} AND time_in < {$todayEnd}
			GROUP BY user_id"
		)->getResultArray();
		foreach ($clockRows as $clock) {
			$clocks[(int) $clock['user_id']] = (int) $clock['time_in'];
		}
		$arrivals = [];
		foreach ($staffRows as $staff) {
			$sid = (int) $staff['school_id'];
			if (!isset($out[$sid])) {
				continue;
			}
			$person = [
				'id' => (int) $staff['id'],
				'name' => trim((string) $staff['fname'] . ' ' . (string) $staff['lname']),
				'post' => (string) ($staff['post_title'] ?? ''),
				'school' => (string) ($staff['school_name'] ?? ''),
				'school_id' => $sid,
				'time' => '',
				'note' => '',
				'minutes' => 0,
			];
			$shift = [
				'title' => (string) ($staff['shift_title'] ?? ''),
				'options' => (string) ($staff['shift_options'] ?? '[]'),
			];
			$hasShift = trim($shift['options']) !== '' && $shift['options'] !== '[]';
			$window = \App\Libraries\StaffShiftClock::windowFor($shift, $noon);
			$timeIn = $clocks[(int) $staff['id']] ?? 0;
			if ($timeIn > 0) {
				$eval = \App\Libraries\StaffShiftClock::evaluateIn($timeIn, \App\Libraries\StaffShiftClock::windowFor($shift, $timeIn));
				$person['time'] = date('H:i', $timeIn);
				$person['note'] = (string) ($eval['detail'] ?? '');
				$person['minutes'] = (int) ($eval['minutes'] ?? 0);
				$person['code'] = (string) ($eval['code'] ?? 'none');
				$score = 0;
				if ($person['code'] === 'late') {
					$score = $person['minutes'];
				} elseif ($person['code'] === 'early') {
					$score = -$person['minutes'];
				}
				$person['score'] = $score;
				$person['time_in'] = $timeIn;
				$out[$sid]['in'][] = $person;
				$arrivals[$sid][] = $person;
			} elseif (!$hasShift || !empty($window['working'])) {
				$person['note'] = 'No clock-in today';
				$out[$sid]['absent'][] = $person;
			}
		}
		foreach ($arrivals as $sid => $people) {
			$late = [];
			$early = [];
			foreach ($people as $person) {
				if (($person['code'] ?? '') === 'late') {
					$late[] = $person;
				} elseif (($person['code'] ?? '') === 'early') {
					$early[] = $person;
				}
			}
			usort($late, static function ($a, $b) {
				if ($a['minutes'] !== $b['minutes']) {
					return $b['minutes'] <=> $a['minutes'];
				}
				return $b['time_in'] <=> $a['time_in'];
			});
			usort($early, static function ($a, $b) {
				if ($a['minutes'] !== $b['minutes']) {
					return $b['minutes'] <=> $a['minutes'];
				}
				return $a['time_in'] <=> $b['time_in'];
			});
			$out[$sid]['late'] = array_slice($late, 0, 5);
			$out[$sid]['early'] = array_slice($early, 0, 5);
		}
		return $out;
	}

	public function showAccountantDashboard($homeSchoolId, $postId, $currentSchoolId)
	{
		return (int) $postId === 28
			&& (int) $homeSchoolId > 0
			&& (int) $homeSchoolId === (int) $currentSchoolId
			&& $this->isWisdomMaster($homeSchoolId);
	}

	/**
	 * Accountant post (9) attendance across the master school and every child school.
	 *
	 * @return array{people: array<int, array<string, mixed>>, in: int, absent: int}
	 */
	public function accountantAttendance($masterId)
	{
		$schools = $this->schools($masterId);
		$ids = [];
		foreach ($schools as $school) {
			$id = (int) ($school['id'] ?? 0);
			if ($id > 0) {
				$ids[] = $id;
			}
		}
		$people = [];
		if (!$ids) {
			return ['people' => [], 'in' => 0, 'absent' => 0];
		}
		$db = \Config\Database::connect();
		$idList = implode(',', array_map('intval', $ids));
		$todayStart = strtotime(date('Y-m-d') . ' 00:00:00');
		$todayEnd = $todayStart + 86400;
		$noon = strtotime(date('Y-m-d') . ' 12:00:00');
		$rows = $db->query(
			"SELECT s.id, s.school_id, s.fname, s.lname, sc.name AS school_name, sc.is_master,
				p.title AS post_title, sh.title AS shift_title, sh.options AS shift_options
			FROM staffs s
			INNER JOIN schools sc ON sc.id = s.school_id
			LEFT JOIN posts p ON p.id = s.post
			LEFT JOIN shifts sh ON sh.id = s.shift_id
			WHERE s.school_id IN ({$idList}) AND s.post = 9 AND IFNULL(s.status, 1) <> 0
			ORDER BY sc.is_master DESC, sc.name, s.fname, s.lname"
		)->getResultArray();
		$clocks = [];
		$clockRows = $db->query(
			"SELECT user_id, MIN(time_in) AS time_in
			FROM attendance_records
			WHERE user_type = 1 AND school_id IN ({$idList})
				AND time_in >= {$todayStart} AND time_in < {$todayEnd}
			GROUP BY user_id"
		)->getResultArray();
		foreach ($clockRows as $clock) {
			$clocks[(int) $clock['user_id']] = (int) $clock['time_in'];
		}
		$in = 0;
		$absent = 0;
		foreach ($rows as $staff) {
			$shift = [
				'title' => (string) ($staff['shift_title'] ?? ''),
				'options' => (string) ($staff['shift_options'] ?? '[]'),
			];
			$hasShift = trim($shift['options']) !== '' && $shift['options'] !== '[]';
			$window = \App\Libraries\StaffShiftClock::windowFor($shift, $noon);
			$timeIn = $clocks[(int) $staff['id']] ?? 0;
			$status = 'absent';
			$time = '';
			if ($timeIn > 0) {
				$status = 'in';
				$time = date('H:i', $timeIn);
				$in++;
			} elseif (!$hasShift || !empty($window['working'])) {
				$absent++;
			} else {
				$status = 'off';
			}
			$people[] = [
				'name' => trim((string) $staff['fname'] . ' ' . (string) $staff['lname']),
				'post' => (string) ($staff['post_title'] ?? 'Accountant'),
				'school' => (string) ($staff['school_name'] ?? ''),
				'is_master' => !empty($staff['is_master']),
				'status' => $status,
				'time' => $time,
			];
		}
		return ['people' => $people, 'in' => $in, 'absent' => $absent];
	}

	private function nameIsWisdom($value)
	{
		$value = strtoupper(trim((string) $value));
		return $value !== '' && (strpos($value, 'WISDOM') !== false || strpos($value, 'WIS-') === 0);
	}
}
