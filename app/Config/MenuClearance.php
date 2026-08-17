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
	 * Finance / budget role defaults (Level clearance + runtime filter).
	 * Full control: Director of Finance (#24) — all schools.
	 * Prepare/fill: Cashier + Accountant (all schools).
	 * View-only dashboard: school leaders (all schools — including master).
	 * Child schools: everyone else loses the Finance menu (except DoF / prepare / view roles).
	 */
	const FINANCE_FULL_CONTROL_POSTS = [24]; // Director of Finance
	const CHILD_BUDGET_PREPARE_POSTS = [8, 9]; // Cashier, Accountant
	const CHILD_BUDGET_VIEW_POSTS = [1, 3, 4, 15, 18]; // Head master, DOS, Dean of discipline, Principal, Headmistress
	/** Budget Dashboard “All branches” / cross-school rollup (master school only). */
	const BUDGET_CROSS_BRANCH_DASHBOARD_POSTS = [15, 19, 24]; // Principal, Budget Manager, Director of Finance

	/** @deprecated alias — use CHILD_BUDGET_PREPARE_POSTS */
	const BUDGET_PREPARE_POSTS = self::CHILD_BUDGET_PREPARE_POSTS;
	/** @deprecated alias — use CHILD_BUDGET_VIEW_POSTS */
	const BUDGET_VIEW_ONLY_POSTS = self::CHILD_BUDGET_VIEW_POSTS;

	/** Menu keys leaders may see (no prepare). */
	public static function childBudgetViewKeys()
	{
		return [
			'finance',
			'budget_cashflow',
			'budget_dashboard',
			'budget_reports',
			'budget_audit',
			'budget_cash_requests',
			'budget_pending',
			'budget_procurement',
			'budget_availability',
			'budget_final_approval',
			'budget_payments',
			'budget_filing',
		];
	}

	/** Menu keys for Cashier/Accountant prepare + fill. */
	public static function childBudgetPrepareKeys()
	{
		return array_values(array_unique(array_merge(
			self::budgetMenuKeys(),
			['finance', 'budget_cashflow']
		)));
	}

	/** Prepare-related menu keys that leaders must never get. */
	public static function budgetPrepareMenuKeys()
	{
		return [
			'budget_prepare',
			'budget_periods',
			'budget_templates',
			'budget_review',
			'budget_approved',
		];
	}

	/**
	 * True when key belongs to Finance / Fees / Budget sidebar.
	 *
	 * @param string $key
	 * @return bool
	 */
	public static function isFinanceMenuKey($key)
	{
		$key = (string) $key;
		if ($key === 'finance' || $key === 'fees' || $key === 'budget_cashflow') {
			return true;
		}
		if (strpos($key, 'budget_') === 0) {
			return true;
		}
		$feeKeys = self::feeMenuKeys();
		return in_array($key, $feeKeys, true);
	}

	/**
	 * Apply finance policy on top of allowed keys.
	 * - Director of Finance (#24): full Finance menus (master + child).
	 * - Leaders (1,3,4,15,18): always view-only budget menus (master + child).
	 * - Cashier/Accountant: prepare menus.
	 * - Child schools: all other posts lose Finance entirely.
	 *
	 * @param string[] $keys
	 * @param int      $postId
	 * @param int|null $schoolId
	 * @return string[]
	 */
	public static function applyChildSchoolFinancePolicy(array $keys, $postId, $schoolId = null)
	{
		$postId = (int) $postId;
		$schoolId = (int) $schoolId;
		$isChild = $schoolId > 0 && self::isChildSchoolId($schoolId);

		// Director of Finance — never strip; ensure full budget menus
		if (in_array($postId, self::FINANCE_FULL_CONTROL_POSTS, true)) {
			return array_values(array_unique(array_merge($keys, self::budgetMenuKeys(), ['finance'], self::feeMenuKeys())));
		}

		// Always: school leaders = view-only (strip prepare even on master / full-access posts)
		if (in_array($postId, self::CHILD_BUDGET_VIEW_POSTS, true)) {
			$nonFinance = array_values(array_filter($keys, static function ($k) {
				return !self::isFinanceMenuKey($k);
			}));
			// Keep fee menus for leaders if they already had them (master full access) — but no budget prepare
			$feeKeep = [];
			foreach ($keys as $k) {
				if (in_array($k, self::feeMenuKeys(), true) || $k === 'fees' || $k === 'fees_pending_approval') {
					$feeKeep[] = $k;
				}
			}
			return array_values(array_unique(array_merge($nonFinance, self::childBudgetViewKeys(), $feeKeep)));
		}

		if (!$isChild) {
			// Master / standalone: non-leaders keep existing keys (Cashier/Accountant/finance roles)
			return array_values(array_unique($keys));
		}

		$nonFinance = array_values(array_filter($keys, static function ($k) {
			return !self::isFinanceMenuKey($k);
		}));

		if (in_array($postId, self::CHILD_BUDGET_PREPARE_POSTS, true)) {
			$extra = self::childBudgetPrepareKeys();
			if ($postId === 9) {
				$extra = array_merge($extra, self::feeMenuKeys());
			}
			return array_values(array_unique(array_merge($nonFinance, $extra)));
		}

		// Child schools: all other posts — no Finance menu
		return $nonFinance;
	}

	/**
	 * @param int $schoolId
	 * @return bool
	 */
	public static function isChildSchoolId($schoolId)
	{
		$schoolId = (int) $schoolId;
		if ($schoolId < 1) {
			return false;
		}
		try {
			return (new \App\Services\SchoolHierarchyService())->isChildSchool($schoolId);
		} catch (\Throwable $e) {
			return false;
		}
	}

	/**
	 * Whether this post may prepare/fill budgets.
	 * Director of Finance (full control) + Cashier / Accountant.
	 *
	 * @param int $postId
	 * @param int $schoolId unused — kept for call-site compatibility
	 * @return bool
	 */
	public static function canPrepareBudgetAtSchool($postId, $schoolId = 0)
	{
		$postId = (int) $postId;
		if (in_array($postId, self::FINANCE_FULL_CONTROL_POSTS, true)) {
			return true;
		}
		return in_array($postId, self::CHILD_BUDGET_PREPARE_POSTS, true);
	}

	/** @param int $postId */
	public static function hasFinanceFullControl($postId)
	{
		return in_array((int) $postId, self::FINANCE_FULL_CONTROL_POSTS, true);
	}

	/**
	 * Cross-branch Budget Dashboard (child schools table).
	 * Head master / Headmistress / Deans / DOS stay school-scoped.
	 *
	 * @param int $postId
	 * @return bool
	 */
	public static function canSeeCrossBranchBudgetDashboard($postId)
	{
		return in_array((int) $postId, self::BUDGET_CROSS_BRANCH_DASHBOARD_POSTS, true);
	}

	/**
	 * View-only budget oversight (no prepare) — Head master and other leaders, any school.
	 *
	 * @param int $postId
	 * @param int $schoolId unused — kept for call-site compatibility
	 * @return bool
	 */
	public static function isChildBudgetViewOnly($postId, $schoolId = 0)
	{
		return in_array((int) $postId, self::CHILD_BUDGET_VIEW_POSTS, true);
	}

	/** Alias for clarity. */
	public static function isBudgetViewOnlyPost($postId)
	{
		return self::isChildBudgetViewOnly($postId, 0);
	}

	/**
	 * Menu tree synced from dashboard sidebar (app/Views/main.php).
	 * Falls back to staticTree() if the view cannot be parsed.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function tree()
	{
		return \App\Services\DashboardSidebarParser::tree();
	}

	/**
	 * Legacy static registry (fallback only).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function staticTree()
	{
		return self::legacyTree();
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function legacyTree()
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
					['key' => 'student_material_check', 'label' => 'Required Material Check'],
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
					['key' => 'parent_visiting/cards', 'label' => 'Print visitor cards'],
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
					['key' => 'timetable_dashboard', 'label' => 'Timetable Management'],
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
					['key' => 'attendance-card', 'label' => 'Student IN/OUT Attendance'],
					['key' => 'student-report/course/monthly', 'label' => 'Student Course'],
					['key' => 'student-report/daily/class', 'label' => 'Student Daily Attendance'],
					['key' => 'student-report/daily/all', 'label' => 'Daily Attendance'],
					['key' => 'student-report/daily/details', 'label' => 'Daily General Attendance'],
					['key' => 'student-report/boarding/all', 'label' => 'Boarding Attendance'],
					['key' => 'student-report/boarding/details', 'label' => 'Boarding General Attendance'],
					['key' => 'student-report/inout/monthly', 'label' => 'In/Out Report'],
				],
			],
			[
				'key' => 'staff_reports',
				'label' => 'Staff Attendance',
				'children' => [
					['key' => 'staff-attendance-card', 'label' => 'Staff IN/OUT Attendance'],
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
				'key' => 'finance',
				'label' => 'Finance',
				'children' => [
					['key' => 'fees', 'label' => 'Fees Management (group)'],
					['key' => 'budget_cashflow', 'label' => 'Budget & Cash Flow (group)'],
				],
			],
			[
				'key' => 'fees',
				'label' => 'Fees Management',
				'children' => [
					['key' => 'fees_entry', 'label' => 'Fees Entry'],
					['key' => 'fees_pending_approval', 'label' => 'Pending Fees Approval'],
					['key' => 'school_fees_management', 'label' => 'School Fees Management'],
					['key' => 'extra_fees_management', 'label' => 'Extra Fees Management'],
					['key' => 'transport_fees_management', 'label' => 'Transport Fees Management'],
					['key' => 'finance_records', 'label' => 'Self service transactions'],
					['key' => 'system-report/fees', 'label' => 'Fees Report'],
				],
			],
			[
				'key' => 'asset_management',
				'label' => 'Asset Management',
				'children' => [
					['key' => 'asset_dashboard', 'label' => 'Dashboard'],
					['key' => 'asset_assets', 'label' => 'Assets'],
					['key' => 'asset_locations', 'label' => 'Areas and Locations'],
					['key' => 'asset_categories', 'label' => 'Categories'],
					['key' => 'asset_assignments', 'label' => 'Asset Assignments'],
					['key' => 'asset_checkout', 'label' => 'Check-out / Check-in'],
					['key' => 'asset_transfers', 'label' => 'Transfers'],
					['key' => 'asset_maintenance', 'label' => 'Maintenance'],
					['key' => 'asset_inspections', 'label' => 'Inspections'],
					['key' => 'asset_incidents', 'label' => 'Incidents and Losses'],
					['key' => 'asset_audits', 'label' => 'Inventory Audits'],
					['key' => 'book_management', 'label' => 'Library — Books'],
					['key' => 'borrowed_report', 'label' => 'Library — Borrowed Report'],
					['key' => 'asset_reports', 'label' => 'Reports'],
					['key' => 'asset_settings', 'label' => 'Settings'],
				],
			],
			[
				'key' => 'library',
				'label' => 'Library Management (legacy group)',
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
				'key' => 'budget_cashflow',
				'label' => 'Budget & Cash Flow',
				'children' => [
					['key' => 'budget_dashboard', 'label' => 'Dashboard'],
					['key' => 'budget_prepare', 'label' => 'Budget Preparation'],
					['key' => 'budget_periods', 'label' => 'Budget Periods'],
					['key' => 'budget_templates', 'label' => 'Budget Templates'],
					['key' => 'budget_review', 'label' => 'Budget Review & Approval'],
					['key' => 'budget_approved', 'label' => 'Approved Budgets'],
					['key' => 'budget_cash_requests', 'label' => 'Cash Requests'],
					['key' => 'budget_pending', 'label' => 'My Pending Actions'],
					['key' => 'budget_procurement', 'label' => 'Procurement Review'],
					['key' => 'budget_availability', 'label' => 'Budget Availability Review'],
					['key' => 'budget_final_approval', 'label' => 'Final Finance Approval'],
					['key' => 'budget_payments', 'label' => 'Payments'],
					['key' => 'budget_filing', 'label' => 'Receipt & Filing'],
					['key' => 'budget_reports', 'label' => 'Reports'],
					['key' => 'budget_audit', 'label' => 'Audit Trail'],
					['key' => 'budget_settings', 'label' => 'Settings'],
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
		return \App\Services\DashboardSidebarParser::allKeys();
	}

	/**
	 * Fee menu keys under Finance (parsed from sidebar).
	 *
	 * @return string[]
	 */
	public static function feeMenuKeys()
	{
		$keys = \App\Services\DashboardSidebarParser::financeSubgroupKeys('fees');
		return $keys ?: self::childKeysFromLegacy('fees');
	}

	/**
	 * Budget & cash-flow keys under Finance (parsed from sidebar).
	 *
	 * @return string[]
	 */
	public static function budgetMenuKeys()
	{
		$keys = \App\Services\DashboardSidebarParser::financeSubgroupKeys('budget_cashflow');
		return $keys ?: self::childKeysFromLegacy('budget_cashflow');
	}

	/**
	 * @param string $parentKey
	 * @return string[]
	 */
	private static function childKeysFromLegacy($parentKey)
	{
		foreach (self::legacyTree() as $group) {
			if (($group['key'] ?? '') === $parentKey) {
				$keys = [];
				foreach ($group['children'] ?? [] as $child) {
					$keys[] = $child['key'];
				}
				return $keys;
			}
		}
		return [];
	}

	/**
	 * Keys for a group: parent + all children.
	 *
	 * @param string $parentKey
	 * @return string[]
	 */
	public static function groupKeys($parentKey)
	{
		if ($parentKey === 'fees') {
			return array_merge(['fees'], self::feeMenuKeys());
		}
		if ($parentKey === 'budget_cashflow') {
			return array_merge(['budget_cashflow'], self::budgetMenuKeys());
		}
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
		if ($parentKey === 'fees') {
			return self::feeMenuKeys();
		}
		if ($parentKey === 'budget_cashflow') {
			return self::budgetMenuKeys();
		}
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

		// Fees: is_allowed(1, 9, 3) — Accountant also prepares budget by default
		if ($postId === 9) {
			$keys = array_merge($keys, self::feeMenuKeys());
			$keys = array_merge($keys, self::childBudgetPrepareKeys());
		}

		// Fees pending approval + finance menu for Director / Deputy Director of Finance (master/central)
		if (in_array($postId, [21, 24], true)) {
			$keys[] = 'finance';
			$keys[] = 'fees_pending_approval';
		}
		if ($postId === 24) {
			$keys = array_merge($keys, self::feeMenuKeys());
		}

		// Cashier: prepare & fill budget (child-school default; also on master)
		if ($postId === 8) {
			$keys = array_merge($keys, self::childBudgetPrepareKeys());
		}

		// School leaders: view-only budget dashboard (defaults). Full-access posts 1/3/18 already have allKeys().
		if (in_array($postId, [4, 15], true)) {
			$keys = array_merge($keys, self::childBudgetViewKeys());
		}

		// Central finance roles (Budget Manager, Procurement, …) — used mainly at master
		if (in_array($postId, [19, 20, 21, 22, 23, 24], true)) {
			$keys = array_merge($keys, self::budgetMenuKeys());
			$keys[] = 'finance';
		}

		// Asset Management + Library: Store keeper (12), Secretary (7), Librarian (13)
		if (in_array($postId, [7, 12, 13], true)) {
			$keys = array_merge($keys, self::groupKeys('asset_management'));
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

	/**
	 * Whether a sidebar group (or any of its children) is allowed given a key set.
	 * Mirrors menu_clearance_group_visible() without session.
	 *
	 * @param string[] $allowedKeys
	 * @param string   $parentKey
	 */
	public static function groupVisibleForKeys(array $allowedKeys, $parentKey)
	{
		$parentKey = (string) $parentKey;
		$set = array_flip($allowedKeys);
		if ($parentKey === 'finance') {
			return self::groupVisibleForKeys($allowedKeys, 'fees')
				|| self::groupVisibleForKeys($allowedKeys, 'budget_cashflow');
		}
		if (isset($set[$parentKey])) {
			return true;
		}
		foreach (self::childKeys($parentKey) as $child) {
			if (isset($set[$child])) {
				return true;
			}
			if (in_array($child, ['fees', 'budget_cashflow'], true)
				&& self::groupVisibleForKeys($allowedKeys, $child)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * SmartSMS / Android home tiles mapped from Level Clearance keys.
	 *
	 * @param string[] $allowedKeys
	 * @return array<string,bool>
	 */
	public static function appMenusForKeys(array $allowedKeys)
	{
		$set = array_flip($allowedKeys);
		$has = static function ($key) use ($set) {
			return isset($set[$key]);
		};

		return [
			'discipline' => self::groupVisibleForKeys($allowedKeys, 'behavior')
				|| $has('discipline_record_entry') || $has('discipline_record'),
			'permission' => self::groupVisibleForKeys($allowedKeys, 'permissions')
				|| $has('permission_entry') || $has('permission_record'),
			'students' => self::groupVisibleForKeys($allowedKeys, 'students')
				|| $has('register-student') || $has('students'),
			'attendance' => $has('attendance_record') || $has('marks_entry')
				|| self::groupVisibleForKeys($allowedKeys, 'marks'),
			'daily_attendance' => $has('attendance_record') || $has('attendance-card'),
			'payment' => self::groupVisibleForKeys($allowedKeys, 'fees')
				|| $has('fees_entry') || $has('extra_fees_entry'),
			'library' => self::groupVisibleForKeys($allowedKeys, 'library')
				|| $has('book_management')
				|| self::groupVisibleForKeys($allowedKeys, 'asset_management'),
			'marks' => self::groupVisibleForKeys($allowedKeys, 'marks') || $has('marks_entry'),
			'transport' => self::groupVisibleForKeys($allowedKeys, 'transport'),
			'leaves' => $has('leave_application') || $has('leave_management'),
			'cashflow' => self::groupVisibleForKeys($allowedKeys, 'budget_cashflow'),
		];
	}
}
