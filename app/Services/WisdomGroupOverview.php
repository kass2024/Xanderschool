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
	public function summary($masterId, $yearId)
	{
		$schools = $this->schools($masterId);
		$ids = [];
		foreach ($schools as $school) {
			$id = (int) ($school['id'] ?? 0);
			if ($id > 0) {
				$ids[] = $id;
			}
		}
		$blank = ['students' => 0, 'boys' => 0, 'girls' => 0, 'staff' => 0, 'present_today' => 0];
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
					COUNT(DISTINCT CASE WHEN UPPER(TRIM(s.sex)) IN ('M','MALE') THEN s.id END) AS boys,
					COUNT(DISTINCT CASE WHEN UPPER(TRIM(s.sex)) IN ('F','FEMALE') THEN s.id END) AS girls
				FROM students s
				INNER JOIN class_records a ON a.student = s.id AND a.status = 1 AND a.year = {$yearId}
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
			$attendSql = "SELECT school_id, COUNT(DISTINCT user_id) AS present_today
				FROM attendance_records
				WHERE user_type = 0
					AND school_id IN ({$idList})
					AND time_in >= {$todayStart}
					AND time_in < {$todayEnd}
				GROUP BY school_id";
			foreach ($db->query($attendSql)->getResultArray() as $row) {
				$sid = (int) $row['school_id'];
				if (isset($bySchool[$sid])) {
					$bySchool[$sid]['present_today'] = (int) $row['present_today'];
				}
			}
		}
		$rows = [];
		$totals = $blank;
		foreach ($schools as $school) {
			$id = (int) ($school['id'] ?? 0);
			$stats = $bySchool[$id] ?? $blank;
			$rows[] = array_merge(['id' => $id, 'name' => (string) ($school['name'] ?? '')], $stats);
			foreach ($blank as $key => $unused) {
				$totals[$key] += (int) $stats[$key];
			}
		}
		return ['schools' => $rows, 'totals' => $totals];
	}

	private function nameIsWisdom($value)
	{
		$value = strtoupper(trim((string) $value));
		return $value !== '' && (strpos($value, 'WISDOM') !== false || strpos($value, 'WIS-') === 0);
	}
}
