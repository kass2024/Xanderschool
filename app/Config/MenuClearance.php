<?php

namespace Config;

/**
 * Central registry of school-dashboard sidebar menu keys for Level clearance.
 * PHP 7.4 compatible.
 */
class MenuClearance
{
	/** Posts that always have full menu access (cannot be restricted). */
	const FULL_ACCESS_POSTS = [1, 3, 18]; // Head master, Director of studies, Headmistress

	/**
	 * Menu tree used by admin UI and key enumeration.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function tree()
	{
		return [
			[
				'key' => 'dashboard',
				'label' => 'Dashboard',
				'always' => true,
				'children' => [],
			],
			[
				'key' => 'students',
				'label' => 'Students',
				'children' => [
					['key' => 'register-student', 'label' => 'New Student'],
					['key' => 'students', 'label' => 'View Students'],
					['key' => 'pendingRegistrations', 'label' => 'Pending Registration'],
					['key' => 'dismissedStudent', 'label' => 'Dismissed Students'],
					['key' => 'student-cards', 'label' => 'Student Cards'],
					['key' => 'student-photo', 'label' => 'Student Photo'],
					['key' => 'assign-card', 'label' => 'Assign Card'],
					['key' => 'attendance-card', 'label' => 'Student IN/OUT Attendance'],
				],
			],
			[
				'key' => 'classes',
				'label' => 'Classes',
				'children' => [],
			],
			[
				'key' => 'course',
				'label' => 'Course',
				'children' => [
					['key' => 'course-category', 'label' => 'Course Category'],
					['key' => 'add_course', 'label' => 'Create Course'],
					['key' => 'manage_courses', 'label' => 'Manage Course'],
				],
			],
			[
				'key' => 'behavior',
				'label' => 'Discipline',
				'children' => [
					['key' => 'discipline_record_entry', 'label' => 'Record Entry'],
					['key' => 'completedDisciplineMarks', 'label' => 'Completed Discipline Marks'],
					['key' => 'discipline_record', 'label' => 'Discipline Record'],
				],
			],
			[
				'key' => 'permissions',
				'label' => 'Permissions',
				'children' => [
					['key' => 'permission_entry', 'label' => 'Permission Entry'],
					['key' => 'permission_report', 'label' => 'Permission Report'],
				],
			],
			[
				'key' => 'parent_visiting',
				'label' => 'Parent visiting',
				'children' => [
					['key' => 'parent_visiting/assign', 'label' => 'Assign visitors'],
					['key' => 'parent_visiting/verify', 'label' => 'Verify visit'],
					['key' => 'parent_visiting/report', 'label' => 'Visiting report'],
				],
			],
			[
				'key' => 'marks',
				'label' => 'Marks',
				'children' => [
					['key' => 'marks_entry', 'label' => 'Marks Entry'],
					['key' => 'get_uploaded_marks', 'label' => 'Marks List'],
					['key' => 'student_report', 'label' => 'Progress Reports'],
					['key' => 'get_periodic_report', 'label' => 'Periodic report'],
					['key' => 'get_periodic_marks', 'label' => 'Periodic Result'],
					['key' => 'proclamation_list', 'label' => 'Proclamation List'],
					['key' => 'student_term_results', 'label' => 'Term Proclamation List'],
					['key' => 'class-deliberation', 'label' => 'Deliberation'],
					['key' => 'finish_deliberation', 'label' => 'Finish Deliberation'],
					['key' => 'deliberation_settings', 'label' => 'Deliberation Settings'],
				],
			],
			[
				'key' => 'pedagogical',
				'label' => 'Pedagogical Documents',
				'children' => [
					['key' => 'ped_analyse', 'label' => 'Analyse Curriculum & Chronogram'],
					['key' => 'ped_scheme_of_work', 'label' => 'Scheme of Work'],
					['key' => 'ped_session_plan', 'label' => 'Session Plan'],
				],
			],
			[
				'key' => 'messaging',
				'label' => 'Messaging',
				'children' => [
					['key' => 'messaging/parents', 'label' => 'Parents Messaging'],
					['key' => 'messaging/employees', 'label' => 'Employees Messaging'],
				],
			],
			[
				'key' => 'student_reports',
				'label' => 'Student Attendance',
				'children' => [
					['key' => 'attendance_record', 'label' => 'Record Attendance'],
					['key' => 'student-report/inout/monthly', 'label' => 'Student In/Out'],
					['key' => 'student-report/course/monthly', 'label' => 'Student Course'],
					['key' => 'student-report/daily/class', 'label' => 'Student Daily Attendance'],
					['key' => 'student-report/daily/all', 'label' => 'Daily Attendance'],
					['key' => 'student-report/daily/details', 'label' => 'Daily General Attendance'],
					['key' => 'student-report/boarding/all', 'label' => 'Boarding Attendance'],
					['key' => 'student-report/boarding/details', 'label' => 'Boarding General Attendance'],
				],
			],
			[
				'key' => 'staff_reports',
				'label' => 'Staff Attendance',
				'children' => [
					['key' => 'staff-report/monthly', 'label' => 'Monthly Report'],
					['key' => 'staff-report/individual', 'label' => 'Individual Report'],
				],
			],
			[
				'key' => 'staffs',
				'label' => 'Staffs',
				'children' => [
					['key' => 'staffs', 'label' => 'All Staffs'],
					['key' => 'staff-cards', 'label' => 'Staff Cards'],
				],
			],
			[
				'key' => 'fees',
				'label' => 'Fees Management',
				'children' => [
					['key' => 'fees_entry', 'label' => 'Fees Entry'],
					['key' => 'school_fees_management', 'label' => 'School Fees Management'],
					['key' => 'extra_fees_management', 'label' => 'Extra Fees Management'],
					['key' => 'transport_fees_management', 'label' => 'Transport Fees Management'],
					['key' => 'finance_records', 'label' => 'Self service transactions'],
					['key' => 'system-report/fees', 'label' => 'Fees Report'],
				],
			],
			[
				'key' => 'library',
				'label' => 'Library Management',
				'children' => [
					['key' => 'book_management', 'label' => 'Books Management'],
					['key' => 'borrowed_report', 'label' => 'Borrowed Report'],
				],
			],
			[
				'key' => 'transport',
				'label' => 'Transport Management',
				'children' => [
					['key' => 'bus_management', 'label' => 'Bus Management'],
					['key' => 'route_management', 'label' => 'Route Management'],
				],
			],
			[
				'key' => 'pocket_money',
				'label' => 'Pocket Money',
				'children' => [],
			],
			[
				'key' => 'leave_application',
				'label' => 'Leave Application',
				'children' => [],
			],
			[
				'key' => 'leave_management',
				'label' => 'Leave Management',
				'children' => [],
			],
			[
				'key' => 'settings',
				'label' => 'Settings',
				'children' => [],
			],
			[
				'key' => 'profile',
				'label' => 'Profile',
				'always' => true,
				'children' => [],
			],
		];
	}

	/**
	 * Flat list of every menu key (parents + children).
	 *
	 * @return string[]
	 */
	public static function allKeys()
	{
		$keys = [];
		foreach (self::tree() as $group) {
			$keys[] = $group['key'];
			foreach ($group['children'] as $child) {
				$keys[] = $child['key'];
			}
		}
		return array_values(array_unique($keys));
	}

	/**
	 * Keys for a group: parent + all children.
	 *
	 * @param string $parentKey
	 * @return string[]
	 */
	public static function groupKeys($parentKey)
	{
		foreach (self::tree() as $group) {
			if ($group['key'] === $parentKey) {
				$keys = [$parentKey];
				foreach ($group['children'] as $child) {
					$keys[] = $child['key'];
				}
				return $keys;
			}
		}
		return [$parentKey];
	}

	/**
	 * Child keys only for a parent.
	 *
	 * @param string $parentKey
	 * @return string[]
	 */
	public static function childKeys($parentKey)
	{
		foreach (self::tree() as $group) {
			if ($group['key'] === $parentKey) {
				$keys = [];
				foreach ($group['children'] as $child) {
					$keys[] = $child['key'];
				}
				return $keys;
			}
		}
		return [];
	}

	/**
	 * @param int $postId
	 * @return bool
	 */
	public static function isFullAccessPost($postId)
	{
		return in_array((int) $postId, self::FULL_ACCESS_POSTS, true);
	}

	/**
	 * Legacy sidebar privileges mirrored as default allowed keys.
	 * Full-access posts get every key.
	 *
	 * @param int $postId
	 * @return string[]
	 */
	public static function defaultKeysForPost($postId)
	{
		$postId = (int) $postId;
		if (self::isFullAccessPost($postId)) {
			return self::allKeys();
		}

		$keys = ['dashboard', 'profile', 'leave_application'];

		// Discipline: previously unrestricted
		$keys = array_merge($keys, self::groupKeys('behavior'));

		// Marks: everyone sees menu + marks entry; restricted items were is_allowed(1,3) only
		$keys[] = 'marks';
		$keys[] = 'marks_entry';

		// Students: !is_blocked(2) → everyone except Teacher
		if ($postId !== 2) {
			$keys = array_merge($keys, self::groupKeys('students'));
		}

		// Classes: was is_allowed(1, 3) only → no non-full-access defaults

		// Course: !is_blocked(2, 4, 5, 6)
		if (!in_array($postId, [2, 4, 5, 6], true)) {
			$keys = array_merge($keys, self::groupKeys('course'));
		}

		// Permissions + parent visiting: is_allowed(1, 3, 4, 5, 6)
		if (in_array($postId, [4, 5, 6], true)) {
			$keys = array_merge($keys, self::groupKeys('permissions'));
			$keys = array_merge($keys, self::groupKeys('parent_visiting'));
		}

		// Pedagogical: is_allowed(1, 3, 5, 6, 13, 15, 17, 18)
		if (in_array($postId, [5, 6, 13, 15, 17], true)) {
			$keys = array_merge($keys, self::groupKeys('pedagogical'));
		}

		// Messaging: is_allowed(1, 3, 4, 5, 6)
		if (in_array($postId, [4, 5, 6], true)) {
			$keys = array_merge($keys, self::groupKeys('messaging'));
		}

		// Attendance reports: !is_blocked(5, 6)
		if (!in_array($postId, [5, 6], true)) {
			$keys = array_merge($keys, self::groupKeys('student_reports'));
			$keys = array_merge($keys, self::groupKeys('staff_reports'));
		}

		// Staffs / leave_management / settings: was is_allowed(1, 3) only

		// Fees: is_allowed(1, 9, 3)
		if ($postId === 9) {
			$keys = array_merge($keys, self::groupKeys('fees'));
		}

		// Library: is_allowed(1, 7, 13, 3)
		if (in_array($postId, [7, 13], true)) {
			$keys = array_merge($keys, self::groupKeys('library'));
		}

		// Transport: is_allowed(1, 5, 6, 7, 3)
		if (in_array($postId, [5, 6, 7], true)) {
			$keys = array_merge($keys, self::groupKeys('transport'));
		}

		// Pocket money: is_allowed(1, 3, 14)
		if ($postId === 14) {
			$keys[] = 'pocket_money';
		}

		return array_values(array_unique($keys));
	}
}
