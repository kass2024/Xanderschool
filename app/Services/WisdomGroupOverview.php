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

	/** App all-schools monitor: Executive Principal, Director of Finance, Director, Deputy Director. */
	const MONITOR_POSTS = [15, 24, 29, 30];

	public function isLeaderPost($postId)
	{
		return in_array((int) $postId, self::LEADER_POSTS, true);
	}

	/** Customer Care may use the Wisdom Musanze master dashboard. */
	public function isCustomerCarePost($postId)
	{
		return \Config\MenuClearance::postTitle((int) $postId) === 'customer care';
	}

	/**
	 * Customer Care sees the same attendance monitor as the directors,
	 * for Wisdom School Musanze only.
	 */
	public function musanzeOnlyMonitor($postId): bool
	{
		$postId = (int) $postId;
		if ($postId === 32) {
			return true;
		}
		return \Config\MenuClearance::postTitle($postId) === 'customer care';
	}

	/** @return array{id: int, name: string}|null */
	public function wisdomMusanzeSchool(): ?array
	{
		$rows = \Config\Database::connect()->table('schools')->select('id, name, acronym')->get()->getResultArray();
		$fallback = null;
		foreach ($rows as $row) {
			$name = strtoupper(trim((string) preg_replace('/\s+/', ' ', (string) ($row['name'] ?? ''))));
			$acr = strtoupper(trim((string) ($row['acronym'] ?? '')));
			$mentionsMusanze = strpos($name, 'MUSANZE') !== false || $acr === 'MUSANZE' || strpos($acr, 'MUSANZE') !== false;
			if (!$mentionsMusanze || (!$this->nameIsWisdom($name) && !$this->nameIsWisdom($acr))) {
				continue;
			}
			$item = ['id' => (int) $row['id'], 'name' => (string) ($row['name'] ?? '')];
			if ($name === 'WISDOM SCHOOL MUSANZE') {
				return $item;
			}
			if ($fallback === null) {
				$fallback = $item;
			}
		}
		return $fallback;
	}

	public function canMonitor($postId)
	{
		return in_array((int) $postId, self::MONITOR_POSTS, true);
	}

	/**
	 * All-schools monitor while one of the four posts is on the Wisdom master school.
	 */
	public function showMonitorGroup($homeSchoolId, $postId, $currentSchoolId)
	{
		$homeSchoolId = (int) $homeSchoolId;
		return $homeSchoolId > 0
			&& $homeSchoolId === (int) $currentSchoolId
			&& $this->canMonitor($postId)
			&& $this->isWisdomMaster($homeSchoolId);
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
		$canSee = $this->isLeaderPost($postId) || $this->isCustomerCarePost($postId);
		return $homeSchoolId > 0
			&& $homeSchoolId === (int) $currentSchoolId
			&& $canSee
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
					COUNT(DISTINCT CASE WHEN
						UPPER(TRIM(s.sex)) IN ('F','FEMALE','GIRL','GIRLS','FEMININ','FEMININE','GORE')
						OR UPPER(LEFT(TRIM(s.sex), 1)) = 'F'
					THEN s.id END) AS girls,
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
				INNER JOIN class_records a ON a.student = s.id
					AND a.year = IFNULL(atr.academic_year, {$yearId})
				INNER JOIN classes cl ON cl.id = a.class
				LEFT JOIN levels l ON l.id = cl.level
				WHERE s.status = 1
					AND s.school_id IN ({$idList})
					AND IFNULL(cl.title,'') NOT LIKE '%Holiday%'
					AND IFNULL(l.title,'') NOT LIKE '%Holiday%'
				GROUP BY s.school_id";
			foreach ($db->query($studentSql)->getResultArray() as $row) {
				$sid = (int) $row['school_id'];
				if (!isset($bySchool[$sid])) {
					continue;
				}
				$students = (int) $row['students'];
				$girls = min($students, (int) $row['girls']);
				$bySchool[$sid]['students'] = $students;
				$bySchool[$sid]['girls'] = $girls;
				$bySchool[$sid]['boys'] = $students - $girls;
				$bySchool[$sid]['parents'] = (int) ($row['parents'] ?? 0);
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
		$masterLocations = $this->masterLocationFlow((int) $masterId, (int) $yearId);
		foreach ($rows as $i => $row) {
			$pack = $today[(int) $row['id']] ?? ['absent' => [], 'late' => [], 'early' => []];
			$rows[$i]['staff_absent'] = count($pack['absent']);
			$rows[$i]['late_today'] = $pack['late'];
			$rows[$i]['early_today'] = $pack['early'];
			$rows[$i]['locations'] = !empty($row['is_master']) ? $masterLocations : [];
			$rows[$i]['att_summary'] = [];
			$rows[$i]['prep_summary'] = (!empty($row['is_master']) && $this->isWisdomSchoolRwanda((string) ($row['name'] ?? '')))
				? $this->rwandaPrepSummary((int) $row['id'])
				: [];
			$totals['staff_absent'] += count($pack['absent']);
		}
		$childOnly = [];
		foreach ($ids as $id) {
			if ((int) $id !== (int) $masterId) {
				$childOnly[] = (int) $id;
			}
		}
		$attendance = $this->childAttendanceSummaries($childOnly, (int) $yearId);
		foreach ($rows as $i => $row) {
			$id = (int) $row['id'];
			$enrolled = (int) ($row['students'] ?? 0);
			if ($id === (int) $masterId) {
				$split = $this->masterPresentSplit($id, (int) $yearId);
				$present = (int) $split['total'];
				$rows[$i]['att_summary'] = [
					'daily_present' => $present,
					'daily_absent' => max(0, $enrolled - $present),
					'day_present' => (int) $split['day'],
					'board_present' => (int) $split['board'],
					'girls_present' => (int) $split['girls'],
					'boys_present' => (int) $split['boys'],
					'source' => 'device',
				];
				$rows[$i]['daily_visitors'] = $this->dailyVisitorPulse($id);
				$rows[$i]['parent_visits'] = $this->parentVisitPulse($id, (int) $yearId);
				continue;
			}
			if (isset($attendance[$id])) {
				$present = (int) $attendance[$id]['daily_present'];
				$attendance[$id]['daily_absent'] = max(0, $enrolled - $present);
				$attendance[$id]['source'] = 'register';
				$rows[$i]['att_summary'] = $attendance[$id];
			}
		}
		return ['schools' => $rows, 'totals' => $totals, 'master_id' => (int) $masterId];
	}

	/**
	 * Master school: day scholars present at the school gate, boarding present in boarding attendance.
	 *
	 * @return list<int>
	 */
	public function devicePresentStudentIds(int $schoolId, int $yearId, int $classId = 0): array
	{
		if ($schoolId < 1) {
			return [];
		}
		$db = \Config\Database::connect();
		if (!$db->tableExists('students') || !$db->tableExists('class_records')) {
			return [];
		}
		$todayStart = strtotime(date('Y-m-d') . ' 00:00:00');
		$todayEnd = $todayStart + 86400;
		$today = $db->escape(date('Y-m-d'));
		$classSql = '';
		if ($classId > 0) {
			$classSql = ' AND es.id IN (SELECT student FROM class_records WHERE class = ' . (int) $classId . ' AND status = 1)';
		}
		$gateIds = $this->gateAreaIds($schoolId);
		$gateSql = '0';
		if ($gateIds !== [] && $db->tableExists('attendance_records')) {
			$gateSql = 'es.studying_mode = 1 AND es.id IN (
				SELECT ar.user_id FROM attendance_records ar
				WHERE ar.school_id = ' . (int) $schoolId . '
					AND ar.user_type = 0
					AND ar.area_id IN (' . implode(',', $gateIds) . ')
					AND ar.time_in >= ' . $todayStart . '
					AND ar.time_in < ' . $todayEnd . '
			)';
		}
		$boardSql = '0';
		if ($db->tableExists('boarding_attendance')) {
			$boardSql = 'es.studying_mode = 0 AND es.id IN (
				SELECT ba.student_id FROM boarding_attendance ba
				WHERE DATE(ba.datee) = ' . $today . '
			)';
		}
		try {
			$rows = $db->query(
				'SELECT es.id FROM (' . $this->enrolledFlagsSql($schoolId, $yearId) . ') es
				WHERE (' . $gateSql . ' OR ' . $boardSql . ')' . $classSql
			)->getResultArray();
		} catch (\Throwable $e) {
			return [];
		}
		$ids = [];
		foreach ($rows as $row) {
			$id = (int) ($row['id'] ?? 0);
			if ($id > 0) {
				$ids[] = $id;
			}
		}
		return $ids;
	}

	public function devicePresentCount(int $schoolId, int $yearId): int
	{
		return (int) $this->masterPresentSplit($schoolId, $yearId)['total'];
	}

	/**
	 * Master present split: school-gate day scholars, boarding device, and sex of those present.
	 *
	 * @return array{total:int,day:int,board:int,girls:int,boys:int}
	 */
	public function masterPresentSplit(int $schoolId, int $yearId): array
	{
		$blank = ['total' => 0, 'day' => 0, 'board' => 0, 'girls' => 0, 'boys' => 0];
		if ($schoolId < 1) {
			return $blank;
		}
		$db = \Config\Database::connect();
		if (!$db->tableExists('students') || !$db->tableExists('class_records')) {
			return $blank;
		}
		$todayStart = strtotime(date('Y-m-d') . ' 00:00:00');
		$todayEnd = $todayStart + 86400;
		$today = $db->escape(date('Y-m-d'));
		$gateIds = $this->gateAreaIds($schoolId);
		$gateSql = '0';
		if ($gateIds !== [] && $db->tableExists('attendance_records')) {
			$gateSql = 'es.studying_mode = 1 AND es.id IN (
				SELECT ar.user_id FROM attendance_records ar
				WHERE ar.school_id = ' . (int) $schoolId . '
					AND ar.user_type = 0
					AND ar.area_id IN (' . implode(',', $gateIds) . ')
					AND ar.time_in >= ' . $todayStart . '
					AND ar.time_in < ' . $todayEnd . '
			)';
		}
		$boardSql = '0';
		if ($db->tableExists('boarding_attendance')) {
			$boardSql = 'es.studying_mode = 0 AND es.id IN (
				SELECT ba.student_id FROM boarding_attendance ba
				WHERE DATE(ba.datee) = ' . $today . '
			)';
		}
		$girl = "UPPER(TRIM(s.sex)) IN ('F','FEMALE','GIRL','GIRLS','FEMININ','FEMININE','GORE')
			OR UPPER(LEFT(TRIM(s.sex), 1)) = 'F'";
		try {
			$row = $db->query(
				'SELECT COUNT(*) AS total,
					SUM(es.studying_mode = 1) AS day_present,
					SUM(es.studying_mode = 0) AS board_present,
					SUM(CASE WHEN ' . $girl . ' THEN 1 ELSE 0 END) AS girls_present
				FROM (' . $this->enrolledFlagsSql($schoolId, $yearId) . ') es
				INNER JOIN students s ON s.id = es.id
				WHERE (' . $gateSql . ' OR ' . $boardSql . ')'
			)->getRowArray();
		} catch (\Throwable $e) {
			return $blank;
		}
		$total = (int) ($row['total'] ?? 0);
		$girls = min($total, (int) ($row['girls_present'] ?? 0));
		return [
			'total' => $total,
			'day' => (int) ($row['day_present'] ?? 0),
			'board' => (int) ($row['board_present'] ?? 0),
			'girls' => $girls,
			'boys' => max(0, $total - $girls),
		];
	}

	/**
	 * Today's daily gate visitors. Same totals as the visiting report.
	 *
	 * @return array{visits:int,still_inside:int,checked_out:int}
	 */
	public function dailyVisitorPulse(int $schoolId): array
	{
		$blank = ['visits' => 0, 'still_inside' => 0, 'checked_out' => 0];
		if ($schoolId < 1) {
			return $blank;
		}
		$db = \Config\Database::connect();
		if (!$db->tableExists('gate_visits')) {
			return $blank;
		}
		try {
			$row = $db->query(
				'SELECT COUNT(*) AS visits,
					SUM(CASE WHEN IFNULL(time_out, 0) = 0 THEN 1 ELSE 0 END) AS still_inside,
					SUM(CASE WHEN IFNULL(time_out, 0) > 0 THEN 1 ELSE 0 END) AS checked_out
				FROM gate_visits
				WHERE school_id = ' . (int) $schoolId . '
					AND visit_date = ' . $db->escape(date('Y-m-d'))
			)->getRowArray();
		} catch (\Throwable $e) {
			return $blank;
		}
		return [
			'visits' => (int) ($row['visits'] ?? 0),
			'still_inside' => (int) ($row['still_inside'] ?? 0),
			'checked_out' => (int) ($row['checked_out'] ?? 0),
		];
	}

	/**
	 * Today's parent visiting short report. Same rules as the parent visiting report.
	 *
	 * @return array{classes:int,students:int,visited:int,not_visited:int,check_ins:int}
	 */
	public function parentVisitPulse(int $schoolId, int $yearId): array
	{
		$blank = ['classes' => 0, 'students' => 0, 'visited' => 0, 'not_visited' => 0, 'check_ins' => 0];
		if ($schoolId < 1) {
			return $blank;
		}
		$db = \Config\Database::connect();
		if (!$db->tableExists('students') || !$db->tableExists('class_records') || !$db->tableExists('classes')) {
			return $blank;
		}
		try {
			$pop = $db->query(
				'SELECT COUNT(DISTINCT s.id) AS students, COUNT(DISTINCT c.id) AS classes
				FROM students s
				INNER JOIN class_records cr ON cr.student = s.id AND cr.year = ' . (int) $yearId . '
				INNER JOIN classes c ON c.id = cr.class
				WHERE s.school_id = ' . (int) $schoolId . ' AND s.status = 1'
			)->getRowArray();
			$students = (int) ($pop['students'] ?? 0);
			$classes = (int) ($pop['classes'] ?? 0);
			$visited = 0;
			$checkIns = 0;
			if ($db->tableExists('visitor_visits')) {
				$vis = $db->query(
					'SELECT COUNT(*) AS check_ins, COUNT(DISTINCT student_id) AS visited
					FROM visitor_visits
					WHERE school_id = ' . (int) $schoolId . '
						AND visit_date = ' . $db->escape(date('Y-m-d'))
				)->getRowArray();
				$visited = (int) ($vis['visited'] ?? 0);
				$checkIns = (int) ($vis['check_ins'] ?? 0);
			}
			return [
				'classes' => $classes,
				'students' => $students,
				'visited' => $visited,
				'not_visited' => max(0, $students - $visited),
				'check_ins' => $checkIns,
			];
		} catch (\Throwable $e) {
			return $blank;
		}
	}

	public function schoolIsMaster(int $schoolId): bool
	{
		if ($schoolId < 1) {
			return false;
		}
		$db = \Config\Database::connect();
		if (!$db->tableExists('schools') || !$db->fieldExists('is_master', 'schools')) {
			return false;
		}
		$row = $db->table('schools')->select('is_master')->where('id', $schoolId)->get(1)->getRowArray();
		return !empty($row['is_master']);
	}

	/** @return list<int> */
	private function gateAreaIds(int $schoolId): array
	{
		$db = \Config\Database::connect();
		if (!$db->tableExists('attendance_areas')) {
			return [];
		}
		$ids = [];
		foreach ($db->table('attendance_areas')->select('id, name')->where('school_id', $schoolId)->where('active', 1)->get()->getResultArray() as $area) {
			if ($this->isGateLocation((string) ($area['name'] ?? ''))) {
				$ids[] = (int) $area['id'];
			}
		}
		return $ids;
	}

	private function isGateLocation(string $name): bool
	{
		$n = strtolower(trim(preg_replace('/[^a-z0-9]+/i', ' ', $name) ?? ''));
		$n = trim($n);
		if ($n === '') {
			return false;
		}
		return $n === 'gate' || $n === 'school gate' || strpos($n, 'school gate') !== false || substr($n, -5) === ' gate';
	}

	/**
	 * Today's register totals from the daily attendance app. Numbers only.
	 *
	 * @param int[] $ids
	 * @return array<int, array<string, int>>
	 */
	private function childAttendanceSummaries(array $ids, int $yearId): array
	{
		$blank = [
			'daily_present' => 0,
			'daily_absent' => 0,
		];
		$out = [];
		foreach ($ids as $id) {
			if ($id > 0) {
				$out[$id] = $blank;
			}
		}
		if ($out === []) {
			return [];
		}
		try {
			$db = \Config\Database::connect();
			if (!$db->tableExists('daily_attendance') || !$db->tableExists('students')) {
				return $out;
			}
			$idList = implode(',', array_map('intval', array_keys($out)));
			$today = $db->escape(date('Y-m-d'));
			$sql = "SELECT st.school_id, COUNT(DISTINCT da.student_id) AS daily_present
				FROM daily_attendance da
				INNER JOIN students st ON st.id = da.student_id
				WHERE st.school_id IN ({$idList})
					AND st.status = 1
					AND DATE(da.datee) = {$today}
				GROUP BY st.school_id";
			foreach ($db->query($sql)->getResultArray() as $row) {
				$sid = (int) $row['school_id'];
				if (isset($out[$sid])) {
					$out[$sid]['daily_present'] = (int) $row['daily_present'];
				}
			}
		} catch (\Throwable $e) {
			return $out;
		}
		return $out;
	}

	/**
	 * Today's student IN/OUT by attendance location. Master school only.
	 *
	 * @return list<array{id:int,name:string,checked_in:int,checked_out:int,inside:int,absent:int}>
	 */
	private function masterLocationFlow(int $masterId, int $yearId = 0): array
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
		$population = $this->locationPopulation($masterId, $yearId);
		$tapped = [];
		if ($population['enrolled'] > 0 && $db->tableExists('students')) {
			$tapSql = "SELECT ar.area_id,
					COUNT(DISTINCT es.id) AS tapped,
					COUNT(DISTINCT CASE WHEN es.studying_mode = 1 THEN es.id END) AS day_tapped,
					COUNT(DISTINCT CASE WHEN es.is_nursery = 0 THEN es.id END) AS prep_tapped,
					COUNT(DISTINCT CASE WHEN es.is_nursery = 0 AND es.studying_mode = 1 THEN es.id END) AS prep_day_tapped
				FROM attendance_records ar
				INNER JOIN (" . $this->enrolledFlagsSql($masterId, $yearId) . ") es ON es.id = ar.user_id
				WHERE ar.school_id = {$masterId}
					AND ar.user_type = 0
					AND ar.time_in >= {$todayStart}
					AND ar.time_in < {$todayEnd}
				GROUP BY ar.area_id";
			foreach ($db->query($tapSql)->getResultArray() as $tap) {
				$tapped[(int) $tap['area_id']] = $tap;
			}
		}
		$out = [];
		foreach ($db->query($sql)->getResultArray() as $row) {
			$name = trim((string) ($row['name'] ?? ''));
			if ($name === '') {
				continue;
			}
			$areaId = (int) ($row['id'] ?? 0);
			$tap = $tapped[$areaId] ?? [];
			$prep = $this->isPrepLocation($name);
			$baseEnrolled = $prep ? $population['prep_enrolled'] : $population['enrolled'];
			$dayEnrolled = $prep ? $population['prep_day'] : $population['day_enrolled'];
			$seen = $prep ? (int) ($tap['prep_tapped'] ?? 0) : (int) ($tap['tapped'] ?? 0);
			$daySeen = $prep ? (int) ($tap['prep_day_tapped'] ?? 0) : (int) ($tap['day_tapped'] ?? 0);
			$out[] = [
				'id' => $areaId,
				'school_id' => $masterId,
				'name' => $name,
				'checked_in' => (int) ($row['checked_in'] ?? 0),
				'checked_out' => (int) ($row['checked_out'] ?? 0),
				'inside' => (int) ($row['inside'] ?? 0),
				'absent' => max(0, $baseEnrolled - $seen),
				'students_in' => $seen,
				'day_in' => $daySeen,
				'day_absent' => max(0, $dayEnrolled - $daySeen),
			];
		}
		return $out;
	}

	private function isPrepLocation(string $name): bool
	{
		return stripos($name, 'prep') !== false;
	}

	/** Nursery / baby / middle / top class. Prep locations skip these students. */
	private function nurseryMatchSql(string $levelAlias, string $classAlias, string $deptAlias): string
	{
		return "(
			LOWER(IFNULL({$levelAlias}.title,'')) LIKE '%nursery%'
			OR LOWER(IFNULL({$levelAlias}.title,'')) LIKE '%maternelle%'
			OR LOWER(IFNULL({$classAlias}.title,'')) LIKE '%baby%'
			OR LOWER(IFNULL({$classAlias}.title,'')) LIKE '%middle class%'
			OR LOWER(IFNULL({$classAlias}.title,'')) LIKE '%top class%'
			OR LOWER(IFNULL({$classAlias}.title,'')) REGEXP '(^|[^a-z0-9])n[1-3]([^0-9]|$)'
			OR LOWER(IFNULL({$deptAlias}.title,'')) LIKE '%nursery%'
		)";
	}

	/**
	 * One row per enrolled student, with day-scholar and nursery flags.
	 */
	private function enrolledFlagsSql(int $schoolId, int $yearId): string
	{
		$schoolId = (int) $schoolId;
		$yearId = (int) $yearId;
		$nursery = $this->nurseryMatchSql('l', 'cl', 'd');
		return "SELECT s.id,
				MAX(CASE WHEN IFNULL(s.studying_mode, 1) = 1 THEN 1 ELSE 0 END) AS studying_mode,
				MAX(CASE WHEN {$nursery} THEN 1 ELSE 0 END) AS is_nursery
			FROM students s
			INNER JOIN schools sch ON sch.id = s.school_id
			LEFT JOIN active_term atr ON atr.id = sch.active_term
			INNER JOIN class_records a ON a.student = s.id
				AND a.year = IFNULL(atr.academic_year, {$yearId})
			INNER JOIN classes cl ON cl.id = a.class
			LEFT JOIN levels l ON l.id = cl.level
			LEFT JOIN departments d ON d.id = cl.department
			WHERE s.status = 1
				AND s.school_id = {$schoolId}
				AND IFNULL(cl.title,'') NOT LIKE '%Holiday%'
				AND IFNULL(l.title,'') NOT LIKE '%Holiday%'
			GROUP BY s.id";
	}

	/**
	 * @return array{enrolled:int,day_enrolled:int,prep_enrolled:int,prep_day:int}
	 */
	private function locationPopulation(int $schoolId, int $yearId): array
	{
		$blank = ['enrolled' => 0, 'day_enrolled' => 0, 'prep_enrolled' => 0, 'prep_day' => 0];
		$db = \Config\Database::connect();
		if ($schoolId < 1 || !$db->tableExists('students') || !$db->tableExists('class_records')) {
			return $blank;
		}
		try {
			$row = $db->query(
				"SELECT COUNT(*) AS enrolled,
					SUM(studying_mode = 1) AS day_enrolled,
					SUM(is_nursery = 0) AS prep_enrolled,
					SUM(is_nursery = 0 AND studying_mode = 1) AS prep_day
				FROM (" . $this->enrolledFlagsSql($schoolId, $yearId) . ") es"
			)->getRowArray();
		} catch (\Throwable $e) {
			return $blank;
		}
		return [
			'enrolled' => (int) ($row['enrolled'] ?? 0),
			'day_enrolled' => (int) ($row['day_enrolled'] ?? 0),
			'prep_enrolled' => (int) ($row['prep_enrolled'] ?? 0),
			'prep_day' => (int) ($row['prep_day'] ?? 0),
		];
	}

	/**
	 * Location list. Without a class, returns class totals. With a class, returns those students.
	 * Absent means enrolled students who never tapped this location today.
	 *
	 * @return array{name:string,mode:string,classes:list<array{name:string,count:int}>,people:list<array<string,string>>}
	 */
	public function locationRoster(int $schoolId, int $areaId, int $yearId, string $mode = 'in', string $classFilter = ''): array
	{
		$mode = in_array($mode, ['in', 'out', 'absent', 'day_in', 'day_absent'], true) ? $mode : 'in';
		$empty = ['name' => '', 'mode' => $mode, 'classes' => [], 'people' => []];
		if ($schoolId < 1 || $areaId < 1) {
			return $empty;
		}
		$db = \Config\Database::connect();
		if (!$db->tableExists('attendance_areas') || !$db->tableExists('attendance_records') || !$db->tableExists('students')) {
			return $empty;
		}
		$area = $db->table('attendance_areas')
			->select('id, name')
			->where('id', $areaId)
			->where('school_id', $schoolId)
			->get(1)
			->getRowArray();
		if (!$area) {
			return $empty;
		}
		$todayStart = strtotime(date('Y-m-d') . ' 00:00:00');
		$todayEnd = $todayStart + 86400;
		$classFilter = trim($classFilter);
		$skipNursery = $this->isPrepLocation((string) ($area['name'] ?? ''));
		$dayscholar = $mode === 'day_in' || $mode === 'day_absent';
		try {
			$rows = ($mode === 'absent' || $mode === 'day_absent')
				? $this->locationAbsentRows($schoolId, $areaId, $yearId, $todayStart, $todayEnd, $dayscholar, $skipNursery)
				: $this->locationTapRows($schoolId, $areaId, $yearId, $todayStart, $todayEnd, $mode === 'out', $dayscholar, $skipNursery);
		} catch (\Throwable $e) {
			return $empty;
		}
		$grouped = [];
		foreach ($rows as $row) {
			$label = \App\Models\SchoolFeesModel::displayLabel($row);
			if ($label === '') {
				$label = 'No class';
			}
			if ($classFilter !== '' && strcasecmp($label, $classFilter) !== 0) {
				continue;
			}
			$outAt = (int) ($row['time_out'] ?? 0);
			$grouped[$label][] = [
				'name' => trim((string) ($row['student_name'] ?? '')),
				'class' => $label,
				'time_in' => !empty($row['time_in']) ? date('H:i', (int) $row['time_in']) : '',
				'time_out' => $outAt > 0 ? date('H:i', $outAt) : '',
				'status' => ($mode === 'absent' || $mode === 'day_absent') ? 'absent' : ($outAt > 0 ? 'out' : 'in'),
			];
		}
		ksort($grouped, SORT_NATURAL | SORT_FLAG_CASE);
		if ($classFilter !== '') {
			$people = $grouped[$classFilter] ?? [];
			if ($people === []) {
				foreach ($grouped as $label => $pack) {
					if (strcasecmp($label, $classFilter) === 0) {
						$people = $pack;
						break;
					}
				}
			}
			usort($people, static function ($a, $b) {
				return strcasecmp((string) $a['name'], (string) $b['name']);
			});
			return [
				'name' => trim((string) ($area['name'] ?? '')),
				'mode' => $mode,
				'class' => $classFilter,
				'classes' => [],
				'people' => $people,
			];
		}
		$classes = [];
		foreach ($grouped as $label => $pack) {
			$classes[] = ['name' => $label, 'count' => count($pack)];
		}
		return [
			'name' => trim((string) ($area['name'] ?? '')),
			'mode' => $mode,
			'classes' => $classes,
			'people' => [],
		];
	}

	/**
	 * Class list behind a dashboard number. Without a class, returns class totals.
	 *
	 * @return array{mode:string,classes:list<array{name:string,count:int}>,people:list<array<string,string>>}
	 */
	public function monitorPeople(int $schoolId, int $yearId, string $mode, string $classFilter = ''): array
	{
		$mode = strtolower(trim($mode));
		$allowed = [
			'present', 'day', 'board', 'girls', 'boys', 'absent',
			'visit', 'visit_in', 'visit_out',
			'parent_in', 'parent_out', 'parent_checks',
			'staff_in', 'staff_out',
		];
		if (!in_array($mode, $allowed, true)) {
			$mode = 'present';
		}
		$classFilter = trim($classFilter);
		$empty = ['mode' => $mode, 'classes' => [], 'people' => []];
		if ($schoolId < 1) {
			return $empty;
		}
		try {
			if (strpos($mode, 'visit') === 0) {
				$rows = $this->visitorPeople($schoolId, $mode);
			} elseif (strpos($mode, 'parent_') === 0) {
				$rows = $this->parentPeople($schoolId, $yearId, $mode);
			} elseif (strpos($mode, 'staff_') === 0) {
				$rows = $this->staffPeople($schoolId, $mode);
			} else {
				$rows = $this->attendancePeople($schoolId, $yearId, $mode);
			}
		} catch (\Throwable $e) {
			return $empty;
		}
		return $this->groupPeople($rows, $mode, $classFilter);
	}

	/**
	 * @param list<array<string,string>> $rows
	 * @return array{mode:string,classes:list<array{name:string,count:int}>,people:list<array<string,string>>,class?:string}
	 */
	private function groupPeople(array $rows, string $mode, string $classFilter): array
	{
		$grouped = [];
		foreach ($rows as $row) {
			$label = trim((string) ($row['class'] ?? ''));
			if ($label === '') {
				$label = 'No class';
			}
			if ($classFilter !== '' && strcasecmp($label, $classFilter) !== 0) {
				continue;
			}
			$grouped[$label][] = $row;
		}
		ksort($grouped, SORT_NATURAL | SORT_FLAG_CASE);
		if ($classFilter !== '') {
			$people = [];
			foreach ($grouped as $label => $pack) {
				if (strcasecmp($label, $classFilter) === 0) {
					$people = $pack;
					break;
				}
			}
			usort($people, static function ($a, $b) {
				return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
			});
			return [
				'mode' => $mode,
				'class' => $classFilter,
				'classes' => [],
				'people' => $people,
			];
		}
		$classes = [];
		foreach ($grouped as $label => $pack) {
			$classes[] = ['name' => $label, 'count' => count($pack)];
		}
		return ['mode' => $mode, 'classes' => $classes, 'people' => []];
	}

	/** @return list<array<string,string>> */
	private function attendancePeople(int $schoolId, int $yearId, string $mode): array
	{
		$db = \Config\Database::connect();
		if (!$db->tableExists('students') || !$db->tableExists('class_records')) {
			return [];
		}
		$master = $this->schoolIsMaster($schoolId);
		$todayStart = strtotime(date('Y-m-d') . ' 00:00:00');
		$todayEnd = $todayStart + 86400;
		$today = $db->escape(date('Y-m-d'));
		$girl = "UPPER(TRIM(s.sex)) IN ('F','FEMALE','GIRL','GIRLS','FEMININ','FEMININE','GORE')
			OR UPPER(LEFT(TRIM(s.sex), 1)) = 'F'";
		$presentSql = '0';
		$timeSql = "'' AS seen_at";
		if ($master) {
			$gateIds = $this->gateAreaIds($schoolId);
			$gateJoin = '0 AS gate_time';
			if ($gateIds !== [] && $db->tableExists('attendance_records')) {
				$gateJoin = '(SELECT MIN(ar.time_in) FROM attendance_records ar
					WHERE ar.user_id = s.id AND ar.user_type = 0 AND ar.school_id = ' . (int) $schoolId . '
						AND ar.area_id IN (' . implode(',', $gateIds) . ')
						AND ar.time_in >= ' . $todayStart . ' AND ar.time_in < ' . $todayEnd . ') AS gate_time';
			}
			$boardJoin = 'NULL AS board_time';
			if ($db->tableExists('boarding_attendance')) {
				$boardJoin = '(SELECT MIN(ba.clock_time) FROM boarding_attendance ba
					WHERE ba.student_id = s.id AND DATE(ba.datee) = ' . $today . ') AS board_time';
			}
			$presentSql = '((es.studying_mode = 1 AND gate_time IS NOT NULL) OR (es.studying_mode = 0 AND board_time IS NOT NULL))';
			$timeSql = $gateJoin . ', ' . $boardJoin;
		} elseif ($db->tableExists('daily_attendance')) {
			$presentSql = 'reg.student_id IS NOT NULL';
			$timeSql = 'reg.seen_at';
		}
		$regJoin = '';
		if (!$master && $db->tableExists('daily_attendance')) {
			$regJoin = 'LEFT JOIN (
				SELECT student_id, MIN(datee) AS seen_at
				FROM daily_attendance
				WHERE DATE(datee) = ' . $today . '
				GROUP BY student_id
			) reg ON reg.student_id = s.id';
		}
		$sql = 'SELECT s.id, es.studying_mode,
				CASE WHEN ' . $girl . ' THEN 1 ELSE 0 END AS is_girl,
				' . $timeSql . ',
				' . $this->classSelect('s') . '
			FROM (' . $this->enrolledFlagsSql($schoolId, $yearId) . ') es
			INNER JOIN students s ON s.id = es.id
			' . $regJoin . '
			' . $this->classJoins('s', $yearId);
		$raw = $db->query($sql)->getResultArray();
		$out = [];
		foreach ($raw as $row) {
			$isGirl = (int) ($row['is_girl'] ?? 0) === 1;
			$modeDay = (int) ($row['studying_mode'] ?? 1) === 1;
			$present = false;
			$when = '';
			if ($master) {
				$gateTime = (int) ($row['gate_time'] ?? 0);
				$boardRaw = trim((string) ($row['board_time'] ?? ''));
				$boardTime = $boardRaw !== '' ? strtotime($boardRaw) : 0;
				$atGate = $modeDay && $gateTime > 0;
				$atBoard = !$modeDay && $boardTime > 0;
				$present = $atGate || $atBoard;
				$stamp = $atBoard ? $boardTime : $gateTime;
				if ($stamp > 0) {
					$when = date('H:i', $stamp);
				}
			} else {
				$present = !empty($row['seen_at']);
				$seen = (string) ($row['seen_at'] ?? '');
				if ($seen !== '') {
					$stamp = strtotime($seen);
					if ($stamp > 0) {
						$when = date('H:i', $stamp);
					}
				}
			}
			$keep = false;
			if ($mode === 'absent') {
				$keep = !$present;
			} elseif ($mode === 'day') {
				$keep = $present && $modeDay;
			} elseif ($mode === 'board') {
				$keep = $present && !$modeDay;
			} elseif ($mode === 'girls') {
				$keep = $present && $isGirl;
			} elseif ($mode === 'boys') {
				$keep = $present && !$isGirl;
			} else {
				$keep = $present;
			}
			if (!$keep) {
				continue;
			}
			$out[] = [
				'name' => trim((string) ($row['student_name'] ?? '')),
				'class' => \App\Models\SchoolFeesModel::displayLabel($row),
				'time_in' => $mode === 'absent' ? '' : $when,
				'time_out' => '',
				'status' => $mode === 'absent' ? 'absent' : 'in',
			];
		}
		return $out;
	}

	/** @return list<array<string,string>> */
	private function visitorPeople(int $schoolId, string $mode): array
	{
		$db = \Config\Database::connect();
		if (!$db->tableExists('gate_visits')) {
			return [];
		}
		$rows = $db->query(
			'SELECT names, reason, time_in, time_out
			FROM gate_visits
			WHERE school_id = ' . (int) $schoolId . '
				AND visit_date = ' . $db->escape(date('Y-m-d')) . '
			ORDER BY time_in DESC'
		)->getResultArray();
		$out = [];
		foreach ($rows as $row) {
			$outAt = (int) ($row['time_out'] ?? 0);
			$inside = $outAt <= 0;
			if ($mode === 'visit_in' && !$inside) {
				continue;
			}
			if ($mode === 'visit_out' && $inside) {
				continue;
			}
			$in = (int) ($row['time_in'] ?? 0);
			$reason = trim((string) ($row['reason'] ?? ''));
			$out[] = [
				'name' => trim((string) ($row['names'] ?? '')),
				'class' => $reason !== '' ? $reason : 'Visit',
				'time_in' => $in > 0 ? date('H:i', $in) : '',
				'time_out' => $outAt > 0 ? date('H:i', $outAt) : '',
				'status' => $inside ? 'in' : 'out',
			];
		}
		return $out;
	}

	/** @return list<array<string,string>> */
	private function parentPeople(int $schoolId, int $yearId, string $mode): array
	{
		$db = \Config\Database::connect();
		if (!$db->tableExists('students') || !$db->tableExists('class_records') || !$db->tableExists('classes')) {
			return [];
		}
		$today = $db->escape(date('Y-m-d'));
		if ($mode === 'parent_checks' && $db->tableExists('visitor_visits')) {
			$rows = $db->query(
				'SELECT TRIM(CONCAT(s.fname, " ", s.lname)) AS student_name,
					sv.names AS visitor_name, vv.time_in, vv.time_out,
					l.title AS level_title, d.code AS dept_code, d.title AS dept_title,
					f.abbrev AS faculty_code, c.title AS class_title
				FROM visitor_visits vv
				INNER JOIN students s ON s.id = vv.student_id
				LEFT JOIN student_visitors sv ON sv.id = vv.visitor_id
				' . $this->classJoins('s', $yearId) . '
				WHERE vv.school_id = ' . (int) $schoolId . ' AND vv.visit_date = ' . $today
			)->getResultArray();
			$out = [];
			foreach ($rows as $row) {
				$in = (int) ($row['time_in'] ?? 0);
				$outAt = (int) ($row['time_out'] ?? 0);
				$visitor = trim((string) ($row['visitor_name'] ?? ''));
				$out[] = [
					'name' => trim((string) ($row['student_name'] ?? '')),
					'class' => \App\Models\SchoolFeesModel::displayLabel($row),
					'time_in' => $in > 0 ? date('H:i', $in) : '',
					'time_out' => $outAt > 0 ? date('H:i', $outAt) : '',
					'status' => $outAt > 0 ? 'out' : 'in',
					'note' => $visitor,
				];
			}
			return $out;
		}
		$students = $db->query(
			'SELECT s.id, TRIM(CONCAT(s.fname, " ", s.lname)) AS student_name,
				l.title AS level_title, d.code AS dept_code, d.title AS dept_title,
				f.abbrev AS faculty_code, c.title AS class_title
			FROM students s
			INNER JOIN class_records cr ON cr.student = s.id AND cr.year = ' . (int) $yearId . '
			INNER JOIN classes c ON c.id = cr.class
			LEFT JOIN levels l ON l.id = c.level
			LEFT JOIN departments d ON d.id = c.department
			LEFT JOIN faculty f ON f.id = d.faculty_id
			WHERE s.school_id = ' . (int) $schoolId . ' AND s.status = 1'
		)->getResultArray();
		$visited = [];
		if ($db->tableExists('visitor_visits')) {
			foreach ($db->query(
				'SELECT DISTINCT student_id FROM visitor_visits
				WHERE school_id = ' . (int) $schoolId . ' AND visit_date = ' . $today
			)->getResultArray() as $row) {
				$visited[(int) ($row['student_id'] ?? 0)] = true;
			}
		}
		$out = [];
		$seen = [];
		foreach ($students as $row) {
			$id = (int) ($row['id'] ?? 0);
			if ($id < 1 || isset($seen[$id])) {
				continue;
			}
			$seen[$id] = true;
			$did = !empty($visited[$id]);
			if ($mode === 'parent_in' && !$did) {
				continue;
			}
			if ($mode === 'parent_out' && $did) {
				continue;
			}
			$out[] = [
				'name' => trim((string) ($row['student_name'] ?? '')),
				'class' => \App\Models\SchoolFeesModel::displayLabel($row),
				'time_in' => '',
				'time_out' => '',
				'status' => $did ? 'in' : 'absent',
			];
		}
		return $out;
	}

	/** @return list<array<string,string>> */
	private function staffPeople(int $schoolId, string $mode): array
	{
		$pack = $this->loadTodayStaff([$schoolId]);
		$kind = $mode === 'staff_out' ? 'absent' : 'in';
		$rows = $pack[$schoolId][$kind] ?? [];
		$out = [];
		foreach ($rows as $row) {
			$post = trim((string) ($row['post'] ?? ''));
			$out[] = [
				'name' => trim((string) ($row['name'] ?? '')),
				'class' => $post !== '' ? $post : 'Staff',
				'time_in' => (string) ($row['time'] ?? ''),
				'time_out' => '',
				'status' => $kind === 'absent' ? 'absent' : 'in',
			];
		}
		return $out;
	}

	private function enrolledStudentSql(int $schoolId, int $yearId): string
	{
		$schoolId = (int) $schoolId;
		$yearId = (int) $yearId;
		return "SELECT DISTINCT s.id
			FROM students s
			INNER JOIN schools sch ON sch.id = s.school_id
			LEFT JOIN active_term atr ON atr.id = sch.active_term
			INNER JOIN class_records a ON a.student = s.id
				AND a.year = IFNULL(atr.academic_year, {$yearId})
			INNER JOIN classes cl ON cl.id = a.class
			LEFT JOIN levels l ON l.id = cl.level
			WHERE s.status = 1
				AND s.school_id = {$schoolId}
				AND IFNULL(cl.title,'') NOT LIKE '%Holiday%'
				AND IFNULL(l.title,'') NOT LIKE '%Holiday%'";
	}

	private function enrolledStudentCount(int $schoolId, int $yearId): int
	{
		$db = \Config\Database::connect();
		if ($schoolId < 1 || !$db->tableExists('students') || !$db->tableExists('class_records')) {
			return 0;
		}
		try {
			$row = $db->query('SELECT COUNT(*) AS total FROM (' . $this->enrolledStudentSql($schoolId, $yearId) . ') es')->getRowArray();
		} catch (\Throwable $e) {
			return 0;
		}
		return (int) ($row['total'] ?? 0);
	}

	private function classSelect(string $studentAlias = 's'): string
	{
		return "TRIM(CONCAT({$studentAlias}.fname, ' ', {$studentAlias}.lname)) AS student_name,
			l.title AS level_title, d.code AS dept_code, d.title AS dept_title,
			f.abbrev AS faculty_code, c.title AS class_title";
	}

	private function classJoins(string $studentAlias = 's', int $yearId = 0): string
	{
		$yearId = (int) $yearId;
		$year = $yearId > 0 ? " AND cr2.year = {$yearId}" : '';
		return "LEFT JOIN class_records cr ON cr.id = (
				SELECT cr2.id
				FROM class_records cr2
				INNER JOIN classes c2 ON c2.id = cr2.class
				LEFT JOIN levels l2 ON l2.id = c2.level
				LEFT JOIN departments d2 ON d2.id = c2.department
				WHERE cr2.student = {$studentAlias}.id AND cr2.status = 1{$year}
					AND IFNULL(c2.title,'') NOT LIKE '%Holiday%'
					AND IFNULL(l2.title,'') NOT LIKE '%Holiday%'
					AND IFNULL(d2.title,'') NOT LIKE '%Holiday%'
					AND IFNULL(d2.code,'') NOT LIKE '%Holiday%'
				ORDER BY cr2.id DESC
				LIMIT 1
			)
			LEFT JOIN classes c ON c.id = cr.class
			LEFT JOIN levels l ON l.id = c.level
			LEFT JOIN departments d ON d.id = c.department
			LEFT JOIN faculty f ON f.id = d.faculty_id";
	}

	private function locationTapRows(int $schoolId, int $areaId, int $yearId, int $todayStart, int $todayEnd, bool $outOnly, bool $dayscholarOnly = false, bool $skipNursery = false): array
	{
		$db = \Config\Database::connect();
		$out = $outOnly ? ' AND COALESCE(ar.time_out, 0) > 0' : '';
		$day = $dayscholarOnly ? ' AND IFNULL(s.studying_mode, 1) = 1' : '';
		$nur = $skipNursery ? ' AND NOT ' . $this->nurseryMatchSql('l', 'c', 'd') : '';
		$sql = "SELECT ar.time_in, ar.time_out, " . $this->classSelect('s') . "
			FROM attendance_records ar
			INNER JOIN students s ON s.id = ar.user_id
			" . $this->classJoins('s', $yearId) . "
			WHERE ar.area_id = " . (int) $areaId . "
				AND ar.school_id = " . (int) $schoolId . "
				AND ar.user_type = 0
				AND ar.time_in >= {$todayStart}
				AND ar.time_in < {$todayEnd}{$out}{$day}{$nur}
			ORDER BY s.fname, s.lname";
		return $db->query($sql)->getResultArray();
	}

	private function locationAbsentRows(int $schoolId, int $areaId, int $yearId, int $todayStart, int $todayEnd, bool $dayscholarOnly = false, bool $skipNursery = false): array
	{
		$db = \Config\Database::connect();
		$day = $dayscholarOnly ? ' AND IFNULL(s.studying_mode, 1) = 1' : '';
		$nur = $skipNursery ? ' AND NOT ' . $this->nurseryMatchSql('l', 'c', 'd') : '';
		$sql = "SELECT 0 AS time_in, 0 AS time_out, " . $this->classSelect('s') . "
			FROM students s
			INNER JOIN (" . $this->enrolledStudentSql($schoolId, $yearId) . ") es ON es.id = s.id
			" . $this->classJoins('s', $yearId) . "
			WHERE NOT EXISTS (
				SELECT 1 FROM attendance_records ar
				WHERE ar.user_id = s.id
					AND ar.user_type = 0
					AND ar.area_id = " . (int) $areaId . "
					AND ar.school_id = " . (int) $schoolId . "
					AND ar.time_in >= {$todayStart}
					AND ar.time_in < {$todayEnd}
			){$day}{$nur}
			ORDER BY s.fname, s.lname";
		return $db->query($sql)->getResultArray();
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

	private function isWisdomSchoolRwanda(string $name): bool
	{
		$name = strtoupper(trim((string) preg_replace('/\s+/', ' ', $name)));
		foreach (SchoolHierarchyService::WISDOM_RWANDA_NAMES as $candidate) {
			if ($name === strtoupper($candidate)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Today's morning and evening prep invigilators for Wisdom School Rwanda.
	 *
	 * @return array{morning: array{present: int, absent: int, people: list<array<string, string>>}, evening: array{present: int, absent: int, people: list<array<string, string>>}}
	 */
	private function rwandaPrepSummary(int $schoolId): array
	{
		$blank = ['present' => 0, 'absent' => 0, 'people' => []];
		$out = ['morning' => $blank, 'evening' => $blank];
		if ($schoolId < 1) {
			return $out;
		}
		try {
			$today = date('Y-m-d');
			$model = new \App\Models\PrepTimetableModel();
			$times = $model->forSchool($schoolId);
			$starts = [
				'morning' => (string) ($times['morning_start'] ?? '05:30'),
				'evening' => (string) ($times['evening_start'] ?? '19:00'),
			];
			$nowHm = date('H:i');
			$report = $model->attendanceReport($schoolId, $today, $today);
			$day = null;
			foreach ($report['days'] ?? [] as $candidate) {
				if (($candidate['date'] ?? '') === $today) {
					$day = $candidate;
					break;
				}
			}
			if (!$day) {
				return $out;
			}
			foreach (['morning', 'evening'] as $slot) {
				$opens = $starts[$slot] ?? '19:00';
				if ($nowHm < $opens) {
					$out[$slot] = [
						'present' => 0,
						'absent' => 0,
						'people' => [],
						'pending' => true,
						'starts' => $opens,
					];
					continue;
				}
				$pack = $day['slots'][$slot] ?? [];
				$people = [];
				foreach ($pack['present'] ?? [] as $person) {
					$people[] = [
						'name' => (string) ($person['name'] ?? ''),
						'post' => (string) ($person['post'] ?? ''),
						'status' => 'present',
						'time' => (string) ($person['time'] ?? ''),
					];
				}
				foreach ($pack['absent'] ?? [] as $person) {
					$people[] = [
						'name' => (string) ($person['name'] ?? ''),
						'post' => (string) ($person['post'] ?? ''),
						'status' => 'absent',
						'time' => '',
					];
				}
				$out[$slot] = [
					'present' => count($pack['present'] ?? []),
					'absent' => count($pack['absent'] ?? []),
					'people' => $people,
				];
			}
		} catch (\Throwable $e) {
			return ['morning' => $blank, 'evening' => $blank];
		}
		return $out;
	}
}
