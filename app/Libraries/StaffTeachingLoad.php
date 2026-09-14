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
	 * Fast course/period totals for mobile list (one grouped query, no timetable hydrate).
	 *
	 * @return array<int,array{courses:int,periods:int}>
	 */
	public static function countsByStaffLite(int $schoolId, int $year, int $term = 0): array
	{
		if ($schoolId <= 0 || $year <= 0) {
			return [];
		}
		$db = Database::connect();
		$sql = "SELECT cr.lecturer AS staff_id, COUNT(*) AS courses,
				COALESCE(SUM(c.credit + 0), 0) AS periods
			FROM course_records cr
			INNER JOIN classes cl ON cl.id = cr.class
			LEFT JOIN courses c ON c.id = cr.course
			WHERE cl.school_id = ? AND cr.year = ? AND cr.lecturer > 0";
		$binds = [$schoolId, $year];
		if ($term > 0) {
			$sql .= " AND FIND_IN_SET(?, cr.term) > 0";
			$binds[] = $term;
		}
		$sql .= " GROUP BY cr.lecturer";
		$out = [];
		foreach ($db->query($sql, $binds)->getResultArray() as $row) {
			$id = (int) ($row['staff_id'] ?? 0);
			if ($id <= 0) {
				continue;
			}
			$out[$id] = [
				'courses' => (int) ($row['courses'] ?? 0),
				'periods' => (int) ($row['periods'] ?? 0),
			];
		}
		return $out;
	}

	/**
	 * @param list<array<string,mixed>> $staffs
	 * @return list<array<string,mixed>>
	 */
	public static function attachLite(array $staffs, int $schoolId, int $year, int $term = 0): array
	{
		$counts = self::countsByStaffLite($schoolId, $year, $term);
		foreach ($staffs as &$staff) {
			$id = (int) ($staff['id'] ?? 0);
			$stat = $counts[$id] ?? ['courses' => 0, 'periods' => 0];
			$staff['taught_courses'] = (int) $stat['courses'];
			$staff['taught_periods'] = (int) $stat['periods'];
		}
		unset($staff);

		return $staffs;
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

	/**
	 * Assigned staff with each class/course and weekly periods (combined lessons counted once).
	 *
	 * @return list<array{
	 *   staff_id:int,
	 *   fname:string,
	 *   lname:string,
	 *   name:string,
	 *   phone:string,
	 *   email:string,
	 *   post_title:string,
	 *   courses_count:int,
	 *   periods:int,
	 *   courses:list<array{class:string,title:string,periods:int,combined:bool,note:string}>
	 * }>
	 */
	public static function detailsByStaff(int $schoolId, int $year, int $term = 0): array
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

		$grouped = [];
		$pending = [];
		foreach ($rows as $row) {
			$staffId = (int) ($row['lecturer'] ?? 0);
			if ($staffId <= 0) {
				continue;
			}
			if (!isset($grouped[$staffId])) {
				$grouped[$staffId] = [
					'staff_id' => $staffId,
					'courses_count' => 0,
					'periods' => 0,
					'courses' => [],
				];
			}
			$grouped[$staffId]['courses_count']++;
			$label = TimetableClassLabel::fromRow($row);
			$title = trim((string) ($row['course_title'] ?? ''));
			$hours = TimetableGeneratorService::weeklyHoursFromCourse($row);
			$combineKey = $criteria->combineGroupKey($row);
			if ($combineKey !== '') {
				if (!isset($pending[$staffId][$combineKey])) {
					$pending[$staffId][$combineKey] = [
						'labels' => [],
						'title' => $title,
						'hours' => $hours,
					];
				}
				$pending[$staffId][$combineKey]['labels'][] = $label;
				if ($title !== '') {
					$pending[$staffId][$combineKey]['title'] = $title;
				}
				continue;
			}
			$grouped[$staffId]['courses'][] = [
				'class' => $label,
				'title' => $title !== '' ? $title : 'Course',
				'periods' => $hours,
				'combined' => false,
				'note' => '',
			];
			$grouped[$staffId]['periods'] += $hours;
		}

		foreach ($pending as $staffId => $groups) {
			foreach ($groups as $group) {
				$labels = [];
				foreach ($group['labels'] as $label) {
					$label = trim((string) $label);
					if ($label !== '' && !in_array($label, $labels, true)) {
						$labels[] = $label;
					}
				}
				$combined = count($labels) > 1;
				$grouped[$staffId]['courses'][] = [
					'class' => $labels !== [] ? implode(' + ', $labels) : 'Class',
					'title' => $group['title'] !== '' ? $group['title'] : 'Course',
					'periods' => (int) $group['hours'],
					'combined' => $combined,
					'note' => $combined ? 'Combined lesson' : '',
				];
				$grouped[$staffId]['periods'] += (int) $group['hours'];
			}
		}

		$staffIds = array_keys($grouped);
		$staffMeta = [];
		if ($staffIds !== []) {
			$people = $db->table('staffs s')
				->select('s.id, s.fname, s.lname, s.phone, s.email, p.title AS post_title')
				->join('posts p', 'p.id = s.post', 'left')
				->whereIn('s.id', $staffIds)
				->get()
				->getResultArray();
			foreach ($people as $person) {
				$staffMeta[(int) $person['id']] = $person;
			}
		}

		$out = [];
		foreach ($grouped as $staffId => $block) {
			$meta = $staffMeta[$staffId] ?? [];
			$fname = trim((string) ($meta['fname'] ?? ''));
			$lname = trim((string) ($meta['lname'] ?? ''));
			$name = trim($fname . ' ' . $lname);
			$block['fname'] = $fname;
			$block['lname'] = $lname;
			$block['name'] = $name !== '' ? $name : ('Staff #' . $staffId);
			$block['phone'] = trim((string) ($meta['phone'] ?? ''));
			$block['email'] = trim((string) ($meta['email'] ?? ''));
			$block['post_title'] = trim((string) ($meta['post_title'] ?? ''));
			if (StaffListExporter::isMethodeStaff($block)) {
				continue;
			}
			usort($block['courses'], static function (array $a, array $b): int {
				$classCmp = strcasecmp((string) $a['class'], (string) $b['class']);
				if ($classCmp !== 0) {
					return $classCmp;
				}

				return strcasecmp((string) $a['title'], (string) $b['title']);
			});
			$out[] = $block;
		}

		usort($out, static function (array $a, array $b): int {
			return strcasecmp((string) $a['name'], (string) $b['name']);
		});

		return $out;
	}

	public static function coursesExportFilename(string $schoolName): string
	{
		$base = trim(preg_replace('/\s+/', ' ', $schoolName) ?? '');
		$base = $base !== '' ? $base . ' staff courses' : 'staff courses';
		$safe = preg_replace('/[\\\\\\/:*?"<>|]+/', '', $base);
		$safe = trim(preg_replace('/\s{2,}/', ' ', (string) $safe) ?? '');
		$safe = $safe !== '' ? $safe : 'staff_courses';
		$safe = str_replace(' ', '_', $safe);

		return $safe . '_' . date('Y-m-d') . '.pdf';
	}
}
