<?php

namespace App\Libraries;

use App\Services\Timetable\SecondaryTimetableCriteria;
use App\Services\Timetable\TimetableGeneratorService;
use Config\Database;

/**
 * Course / weekly-period counts for staff already assigned in Manage Course.
 */
class StaffTeachingLoad
{
	/**
	 * @return array<int,array{courses:int,periods:int}>
	 */
	public static function countsByStaff(int $schoolId, int $year, int $term = 0): array
	{
		if ($schoolId <= 0 || $year <= 0) {
			return [];
		}

		$db = Database::connect();
		$builder = $db->table('course_records cr')
			->select('cr.course AS course_id, cr.lecturer, cr.class AS class_id,
				c.title AS course_title, c.credit,
				cl.title AS class_title, l.title AS level_name,
				d.code AS dept_code, d.title AS dept_title')
			->join('courses c', 'c.id = cr.course')
			->join('classes cl', 'cl.id = cr.class')
			->join('levels l', 'l.id = cl.level', 'left')
			->join('departments d', 'd.id = cl.department', 'left')
			->where('cl.school_id', $schoolId)
			->where('cr.year', $year)
			->where('cr.lecturer >', 0);
		if ($term > 0) {
			$builder->where("find_in_set({$term}, cr.term) > 0", null, false);
		}
		$rows = $builder->get()->getResultArray();
		if ($rows === []) {
			return [];
		}

		$criteria = new SecondaryTimetableCriteria();
		$criteria->hydrateFromAssignments($rows);
		$seenCombine = [];
		$out = [];
		foreach ($rows as $row) {
			$staffId = (int) ($row['lecturer'] ?? 0);
			if ($staffId <= 0) {
				continue;
			}
			if (!isset($out[$staffId])) {
				$out[$staffId] = ['courses' => 0, 'periods' => 0];
			}
			$out[$staffId]['courses']++;
			$hours = TimetableGeneratorService::weeklyHoursFromCourse($row);
			$combineKey = $criteria->combineGroupKey($row);
			if ($combineKey !== '' && isset($seenCombine[$combineKey])) {
				continue;
			}
			if ($combineKey !== '') {
				$seenCombine[$combineKey] = true;
			}
			$out[$staffId]['periods'] += $hours;
		}

		return $out;
	}

	/**
	 * @param list<array<string,mixed>> $staffs
	 * @return list<array<string,mixed>>
	 */
	public static function attach(array $staffs, int $schoolId, int $year, int $term = 0): array
	{
		$counts = self::countsByStaff($schoolId, $year, $term);
		foreach ($staffs as &$staff) {
			$id = (int) ($staff['id'] ?? 0);
			$stat = $counts[$id] ?? ['courses' => 0, 'periods' => 0];
			$staff['taught_courses'] = (int) $stat['courses'];
			$staff['taught_periods'] = (int) $stat['periods'];
		}
		unset($staff);

		return $staffs;
	}
}
