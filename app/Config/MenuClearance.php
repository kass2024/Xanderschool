<?php

namespace Config;

/**
 * Central registry of school-dashboard sidebar menu keys for Level clearance.
 * PHP 7.4 compatible.
 */
class MenuClearance
{
	/** Posts that always have full menu access (cannot be restricted). */
	const FULL_ACCESS_POSTS = [1, 3, 15, 18, 25, 26, 29, 30]; // Head master, DoS, Executive Principal, Headmistress, Head Teacher, Deputy Head Teacher, Director, Deputy Director

	/** Head master, Headmistress, and Head Teacher (same school-head rights). */
	const HEAD_MASTER_EQUIVALENT_POSTS = [1, 18, 25];

	/**
	 * School Director / head posts allowed to delete students and toggle Active/Locked.
	 * Head master / Headmistress / Head Teacher are the school-head role at schools that do not use "Director".
	 * Does not include Deputy Head Teacher, Director of studies, or Director of Finance.
	 */
	const DIRECTOR_STUDENT_LIFECYCLE_POSTS = [1, 18, 25, 29, 30];

	/**
	 * @param int $postId
	 * @param string $postTitle
	 * @return bool
	 */
	public static function canManageStudentLockDelete($postId, $postTitle = '')
	{
		$postId = (int) $postId;
		if (in_array($postId, self::DIRECTOR_STUDENT_LIFECYCLE_POSTS, true)) {
			return true;
		}
		$title = strtolower(trim(preg_replace('/\s+/', ' ', (string) $postTitle)));
		if ($title === '') {
			return false;
		}
		$allowed = [
			'director',
			'deputy director',
			'head master',
			'headmaster',
			'headmistress',
			'head teacher',
			'headteacher',
		];
		return in_array($title, $allowed, true);
	}

	/**
	 * Finance / budget role defaults (Level clearance + runtime filter).
	 * Full control: Director of Finance (#24) — all schools.
	 * Prepare/fill: Cashier + Accountant (all schools).
	 * View-only dashboard: school leaders (all schools — including master).
	 * Child schools: everyone else loses the Finance menu (except DoF / prepare / view roles).
	 */
	const FINANCE_FULL_CONTROL_POSTS = [24]; // Director of Finance
	const CHILD_BUDGET_PREPARE_POSTS = [8, 9, 28]; // Cashier, Accountant, Chief Accountant
	const CHILD_BUDGET_VIEW_POSTS = [1, 3, 4, 15, 18, 25, 26, 29, 30]; // Head master, DOS, Dean, Executive Principal, Headmistress, Head Teacher, Deputy Head Teacher, Director, Deputy Director
	/** Fees Entry + school/extra fee settings. */
	const FEE_OPERATOR_POSTS = [8, 9, 15, 24, 28, 29]; // Cashier, Accountant, Executive Principal, Director of Finance, Chief Accountant, Director
	/** Fees report only (no entry, no settings, no SMS). */
	const FEE_REPORT_VIEW_POSTS = [1, 3, 4, 18, 25, 26, 30];
	/** Budget Dashboard “All branches” / cross-school rollup (master school only). */
	const BUDGET_CROSS_BRANCH_DASHBOARD_POSTS = [15, 19, 24, 28]; // Executive Principal, Budget Manager, Director of Finance, Chief Accountant

	/** @deprecated alias — use CHILD_BUDGET_PREPARE_POSTS */
	const BUDGET_PREPARE_POSTS = self::CHILD_BUDGET_PREPARE_POSTS;
	/** @deprecated alias — use CHILD_BUDGET_VIEW_POSTS */
	const BUDGET_VIEW_ONLY_POSTS = self::CHILD_BUDGET_VIEW_POSTS;

	/** Menu keys leaders may see. Prepare and review are view-only for these posts. */
	public static function childBudgetViewKeys()
	{
		return [
			'finance',
			'budget_cashflow',
			'budget_dashboard',
			'budget_reports',
			'budget_audit',
			'budget_prepare',
			'budget_review',
			'budget_approved',
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

		// Chief Accountant on the master school prepares, and reviews every child-school budget.
		if ($postId === 28 && !$isChild) {
			return array_values(array_unique(array_merge($keys, self::budgetMenuKeys(), ['finance'], self::feeMenuKeys())));
		}

		// Always: school leaders = view-only (strip prepare even on master / full-access posts)
		if (in_array($postId, self::CHILD_BUDGET_VIEW_POSTS, true)) {
			$nonFinance = array_values(array_filter($keys, static function ($k) {
				return !self::isFinanceMenuKey($k);
			}));
			$feeKeys = self::canManageFees($postId)
				? array_merge(['finance', 'fees'], self::feeMenuKeys())
				: self::feeReportOnlyKeys();
			return array_values(array_unique(array_merge($nonFinance, self::childBudgetViewKeys(), $feeKeys)));
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
			if (in_array($postId, [8, 9, 28], true)) {
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
		if (self::headTeacherPlacesOwnOrders($postId, (int) $schoolId)) {
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
		if (self::headTeacherPlacesOwnOrders($postId, (int) $schoolId)) {
			return false;
		}
		return in_array((int) $postId, self::CHILD_BUDGET_VIEW_POSTS, true);
	}

	/**
	 * Wisdom School Kanzenze has no accountant. The head teacher uploads the budget
	 * and places cash requests. Other schools keep the accountant step.
	 */
	public static function isKanzenzeSchool($schoolId)
	{
		static $cache = [];
		$schoolId = (int) $schoolId;
		if ($schoolId < 1) {
			return false;
		}
		if (array_key_exists($schoolId, $cache)) {
			return $cache[$schoolId];
		}
		try {
			$row = \Config\Database::connect()->table('schools')->select('name')->where('id', $schoolId)->get(1)->getRowArray();
			$name = strtolower(trim((string) ($row['name'] ?? '')));
			$cache[$schoolId] = strpos($name, 'kanzenze') !== false;
		} catch (\Throwable $e) {
			$cache[$schoolId] = false;
		}
		return $cache[$schoolId];
	}

	public static function headTeacherPlacesOwnOrders($postId, $schoolId = 0)
	{
		if (!self::isHeadMasterEquivalent((int) $postId)) {
			return false;
		}
		$schoolId = (int) $schoolId;
		if ($schoolId < 1) {
			$schoolId = (int) ($_SESSION['soma_school_id'] ?? 0);
		}
		return self::isKanzenzeSchool($schoolId);
	}

	/** Alias for clarity. */
	public static function isBudgetViewOnlyPost($postId)
	{
		return self::isChildBudgetViewOnly($postId, 0);
	}

	/** Fees Entry, school fees settings, extra fees settings. */
	public static function feeOperatorMenuKeys()
	{
		return ['fees_entry', 'school_fees_management', 'extra_fees_management', 'extra_fees_entry'];
	}

	/** Sidebar keys for view-only fees report. */
	public static function feeReportOnlyKeys()
	{
		return ['finance', 'fees', 'system-report/fees'];
	}

	public static function canManageFees($postId)
	{
		$postId = (int) $postId;
		return in_array($postId, self::FEE_OPERATOR_POSTS, true) || self::isExecutivePrincipalPost($postId);
	}

	public static function canViewFeesReport($postId)
	{
		$postId = (int) $postId;
		return self::canManageFees($postId) || in_array($postId, self::FEE_REPORT_VIEW_POSTS, true);
	}

	/** SMS and other write actions on the fees report. */
	public static function canActOnFeesReport($postId)
	{
		return self::canManageFees($postId);
	}

	/**
	 * Director of Finance / Cashier / Accountant / Director / Executive Principal: full fee menus.
	 * Headmaster / Headmistress / DOS / Dean: fees report only.
	 * Everyone else: no fee entry or settings.
	 *
	 * @param string[] $keys
	 * @param int      $postId
	 * @return string[]
	 */
	public static function applyFeeOperatorPolicy(array $keys, $postId)
	{
		$postId = (int) $postId;
		$operatorKeys = self::feeOperatorMenuKeys();
		if (self::canManageFees($postId)) {
			return array_values(array_unique(array_merge($keys, ['finance', 'fees'], self::feeMenuKeys())));
		}
		$keys = array_values(array_filter($keys, static function ($k) use ($operatorKeys) {
			return !in_array($k, $operatorKeys, true);
		}));
		if (self::canViewFeesReport($postId)) {
			return array_values(array_unique(array_merge($keys, self::feeReportOnlyKeys())));
		}
		$keys = array_values(array_filter($keys, static function ($k) {
			return $k !== 'system-report/fees';
		}));
		return $keys;
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
					['key' => 'hostel_allocate', 'label' => 'Dormitories'],
					['key' => 'dormitory_attendance', 'label' => 'Dormitory attendance'],
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
					['key' => 'lateness_report', 'label' => 'Lateness'],
				],
			],
			[
				'key' => 'clinic',
				'label' => 'Clinic',
				'children' => [
					['key' => 'clinic_daily', 'label' => 'Daily report'],
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
				'key' => 'daily_visitors',
				'label' => 'Daily visiting',
				'children' => [
					['key' => 'daily_visitors/inside', 'label' => 'Currently inside'],
					['key' => 'daily_visitors/report', 'label' => 'Visiting report'],
				],
			],
			[
				'key' => 'marks',
				'label' => 'Marks',
				'children' => [
					['key' => 'marks_entry', 'label' => 'Marks Entry'],
					['key' => 'get_uploaded_marks', 'label' => 'Marks sheet'],
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
					['key' => 'staff-report/individual', 'label' => 'Attendance Report'],
					['key' => 'staff-attendance-card', 'label' => 'Record Attendance With card'],
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
				'key' => 'stock_management',
				'label' => 'Stock management',
				'children' => [
					['key' => 'stk_dashboard', 'label' => 'Dashboard'],
					['key' => 'stk_entry', 'label' => 'Stock entry'],
					['key' => 'stk_issue', 'label' => 'Stock issue'],
					['key' => 'stk_items', 'label' => 'Items'],
					['key' => 'stk_categories', 'label' => 'Categories'],
					['key' => 'stk_suppliers', 'label' => 'Suppliers'],
					['key' => 'stk_departments', 'label' => 'Departments'],
					['key' => 'stk_employees', 'label' => 'Employees'],
					['key' => 'stk_ledger', 'label' => 'Stock ledger'],
					['key' => 'stk_report_daily', 'label' => 'Daily report'],
					['key' => 'stk_report_weekly', 'label' => 'Weekly report'],
					['key' => 'stk_report_monthly', 'label' => 'Monthly report'],
					['key' => 'stk_report_custom', 'label' => 'Custom report'],
					['key' => 'stk_adjust', 'label' => 'Corrections'],
					['key' => 'stk_audit', 'label' => 'Audit logs'],
					['key' => 'stk_settings', 'label' => 'Settings'],
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
				'children' => [
					['key' => 'pocket_money_ledger', 'label' => 'Pocket Money'],
					['key' => 'parents_account', 'label' => 'Parents account'],
					['key' => 'pocket_money_report', 'label' => 'Money report'],
					['key' => 'pocket_cash_withdraw', 'label' => 'Cash withdraw'],
					['key' => 'pocket_pos', 'label' => 'POS'],
					['key' => 'canteen', 'label' => 'Canteen'],
				],
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
	/**
	 * Operational stock menus for the storekeeper post.
	 *
	 * @return string[]
	 */
	public static function stockKeeperKeys()
	{
		return [
			'stock_management',
			'stk_dashboard',
			'stk_entry',
			'stk_issue',
			'stk_items',
			'stk_categories',
			'stk_suppliers',
			'stk_departments',
			'stk_employees',
			'stk_ledger',
			'stk_report_daily',
			'stk_report_weekly',
			'stk_report_monthly',
			'stk_report_custom',
		];
	}

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
	 * Head Teacher, Headmistress, Executive Principal, and Deputy Head Teacher
	 * keep their other duties, but Level clearance can change their menus.
	 */
	public static function isCustomizableLeaderPost($postId)
	{
		$postId = (int) $postId;
		if (in_array($postId, [15, 18, 25, 26], true)) {
			return true;
		}
		$t = str_replace(['-', '_'], ' ', self::postTitle($postId));
		return in_array($t, [
			'executive principal',
			'head teacher',
			'headteacher',
			'head mistress',
			'headmistress',
			'deputy head teacher',
			'deputy headteacher',
		], true);
	}

	/** Level clearance cannot restrict these posts. */
	public static function isMenuLockedPost($postId)
	{
		$postId = (int) $postId;
		if (self::isCustomizableLeaderPost($postId)) {
			return false;
		}
		return self::isFullAccessPost($postId);
	}

	/**
	 * @param int $postId
	 * @return bool
	 */
	public static function isFullAccessPost($postId)
	{
		$postId = (int) $postId;
		if (in_array($postId, self::FULL_ACCESS_POSTS, true)) {
			return true;
		}
		return self::isExecutivePrincipalPost($postId);
	}

	/** Executive Principal (post 15). Same sidebar as Director. */
	public static function isExecutivePrincipalPost($postId)
	{
		$postId = (int) $postId;
		if ($postId === 15) {
			return true;
		}
		return self::postTitle($postId) === 'executive principal';
	}

	/**
	 * Academic coordinator: full pedagogical documents and every class/teacher timetable.
	 * Title match covers a recreated post; id 27 is the seeded Coordinator post.
	 */
	public static function isCoordinatorPost($postId)
	{
		$postId = (int) $postId;
		if ($postId < 1) {
			return false;
		}
		$title = self::postTitle($postId);
		if ($title !== '') {
			$flat = str_replace(['-', '_'], ' ', $title);
			return strpos($flat, 'coordinator') !== false || strpos($flat, 'co ordinator') !== false;
		}
		return $postId === 27;
	}

	/** Customer Care may file leave for another staff member. */
	public static function isCustomerCarePost($postId)
	{
		return strpos(self::postTitle((int) $postId), 'customer care') !== false;
	}

	/** Head Teacher, Customer Care, or Director. Not Deputy Director or Director of Finance. */
	public static function canViewFamilies($postId)
	{
		$postId = (int) $postId;
		if ($postId === \App\Models\PostsModel::HEAD_TEACHER_ID || $postId === \App\Models\PostsModel::DIRECTOR_ID) {
			return true;
		}
		$title = self::postTitle($postId);
		return in_array($title, ['head teacher', 'headteacher', 'customer care', 'director'], true);
	}

	/** Chief Accountant (post 28). Same dashboard as the Executive Principal, without Marks. */
	public static function isChiefAccountantPost($postId)
	{
		$postId = (int) $postId;
		if ($postId === 28) {
			return true;
		}
		return self::postTitle($postId) === 'chief accountant';
	}

	/**
	 * @param string[] $keys
	 * @return string[]
	 */
	public static function withoutMarksMenus(array $keys)
	{
		$drop = array_flip(self::marksMenuKeys());
		return array_values(array_filter($keys, static function ($key) use ($drop) {
			return !isset($drop[$key]);
		}));
	}

	/** Alice Namahoro, while logged in at Wisdom School Musanze. */
	public static function isAliceNamahoroAtMusanze(): bool
	{
		try {
			$overview = new \App\Services\WisdomGroupOverview();
			if (!$overview->isAliceNamahoro()) {
				return false;
			}
			$schoolId = (int) (session('soma_school_id') ?? 0);
			$musanze = $overview->wisdomMusanzeSchool();
			return $musanze !== null && (int) ($musanze['id'] ?? 0) === $schoolId && $schoolId > 0;
		} catch (\Throwable $e) {
			return false;
		}
	}

	/**
	 * Pocket Money pages that can be ticked separately in Level clearance.
	 *
	 * @return string[]
	 */
	public static function pocketMenuKeys()
	{
		return [
			'pocket_money_ledger',
			'parents_account',
			'pocket_money_report',
			'pocket_cash_withdraw',
			'pocket_pos',
			'canteen',
		];
	}

	/**
	 * A saved "Pocket Money" tick with no child ticks still means every pocket page.
	 *
	 * @param string[] $keys
	 * @return string[]
	 */
	public static function expandPocketMenus(array $keys)
	{
		$set = array_flip($keys);
		$children = self::pocketMenuKeys();
		$hasChild = false;
		foreach ($children as $child) {
			if (isset($set[$child])) {
				$hasChild = true;
				break;
			}
		}
		if (!$hasChild && isset($set['pocket_money'])) {
			$keys = array_merge($keys, $children);
		}
		$set = array_flip($keys);
		$seesPocket = isset($set['pocket_money']);
		if (!$seesPocket) {
			foreach ($children as $child) {
				if ($child !== 'pocket_pos' && isset($set[$child])) {
					$seesPocket = true;
					break;
				}
			}
		}
		if ($seesPocket && !isset($set['pocket_pos'])) {
			$keys[] = 'pocket_pos';
		}

		return array_values(array_unique($keys));
	}

	/** Pocket money page: full-access posts, or the Level clearance key for that page. */
	public static function canUsePocketMoney($postId, $menuKey = '')
	{
		$postId = (int) $postId;
		$menuKey = (string) $menuKey;
		if (!function_exists('menu_clearance_allowed')) {
			helper('qonics');
		}
		$bypass = self::isFullAccessPost($postId) && !self::isCustomizableLeaderPost($postId);
		if ($menuKey !== '') {
			return $bypass || menu_clearance_allowed($menuKey);
		}
		if ($bypass || menu_clearance_allowed('pocket_money')) {
			return true;
		}
		foreach (self::pocketMenuKeys() as $key) {
			if (menu_clearance_allowed($key)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Clinic daily report: Director, School Nurse, and the school administrators
	 * (full-access posts: head teacher, headmaster, executive principal, deputy director).
	 */
	public static function canUseClinic($postId)
	{
		$postId = (int) $postId;
		if ($postId < 1) {
			return false;
		}
		if (self::isFullAccessPost($postId) || self::isDirectorPost($postId) || self::isHeadMasterEquivalent($postId)) {
			return true;
		}
		$t = self::postTitle($postId);
		if ($t === '') {
			return false;
		}
		if (strpos($t, 'nurse') !== false || strpos($t, 'nuse') !== false) {
			return true;
		}
		return in_array($t, [
			'director',
			'deputy director',
			'executive principal',
			'head master',
			'headmaster',
			'head mistress',
			'headmistress',
			'head teacher',
			'headteacher',
			'administrator',
			'school administrator',
		], true);
	}

	/** Only the school nurse may add or remove a clinic visit. Everyone else with Clinic may view. */
	public static function canRecordClinic($postId)
	{
		$postId = (int) $postId;
		if ($postId < 1) {
			return false;
		}
		$t = self::postTitle($postId);
		return $t !== '' && (strpos($t, 'nurse') !== false || strpos($t, 'nuse') !== false);
	}

	/** School Director (post 29). Director of studies and Director of Finance stay out. */
	public static function isDirectorPost($postId)
	{
		$postId = (int) $postId;
		if ($postId === 29) {
			return true;
		}
		return self::postTitle($postId) === 'director';
	}

	/** Coordinator or Director may allow editing of a quiz, test, or homework that is already saved. */
	public static function canToggleSavedCatEdit($postId)
	{
		$postId = (int) $postId;
		return self::isCoordinatorPost($postId) || self::isDirectorPost($postId);
	}

	/**
	 * Lock Marks editing for this school.
	 * Coordinator, Director, and the school head (Head Teacher, Head master, Headmistress),
	 * including head teachers at child schools.
	 */
	public static function canLockMarksEditing($postId)
	{
		$postId = (int) $postId;
		if (self::isExecutivePrincipalPost($postId) || self::canToggleSavedCatEdit($postId) || self::isHeadMasterEquivalent($postId)) {
			return true;
		}
		$title = self::postTitle($postId);
		return in_array($title, [
			'head master',
			'headmaster',
			'head mistress',
			'headmistress',
			'head teacher',
			'headteacher',
		], true);
	}

	/** Contract dates on a staff record: Head Teacher, Director, or Coordinator. */
	public static function canSetStaffContract($postId)
	{
		return self::canLockMarksEditing($postId);
	}

	/** Director of Finance (post 24). */
	public static function isDirectorOfFinancePost($postId)
	{
		$postId = (int) $postId;
		if ($postId === 24) {
			return true;
		}
		$title = self::postTitle($postId);
		return $title === 'director of finance' || $title === 'finance director';
	}

	/** Only the Director of Finance may mark a whole canteen day card paid. */
	public static function canSettleCanteenInvoice($postId)
	{
		return self::isDirectorOfFinancePost($postId);
	}

	/** Accountant, Chief Accountant, Head Teacher, or Director may fill another staff member's identity. */
	public static function canEditStaffIdentity($postId)
	{
		$postId = (int) $postId;
		if (self::isHeadMasterEquivalent($postId) || self::isDirectorPost($postId) || self::isChiefAccountantPost($postId)) {
			return true;
		}
		if ($postId === 9) {
			return true;
		}
		$title = self::postTitle($postId);
		return in_array($title, ['accountant', 'chief accountant', 'chief account', 'head teacher', 'headteacher', 'director'], true);
	}

	/** Identity PDF: the people who fill identities, plus the Director of Finance. */
	public static function canExportStaffIdentities($postId)
	{
		return self::canEditStaffIdentity($postId) || self::isDirectorOfFinancePost($postId);
	}

	public static function postTitle($postId)
	{
		static $cache = [];
		$postId = (int) $postId;
		if ($postId < 1) {
			return '';
		}
		if (isset($cache[$postId])) {
			return $cache[$postId];
		}
		$title = '';
		try {
			$row = \Config\Database::connect()->table('posts')->select('title')->where('id', $postId)->get(1)->getRowArray();
			$title = strtolower(trim(preg_replace('/\s+/', ' ', (string) ($row['title'] ?? ''))));
		} catch (\Throwable $e) {
			$title = '';
		}
		$cache[$postId] = $title;
		return $title;
	}

	/** Classroom Teacher only. Head Teacher and Deputy Head Teacher stay allowed. */
	public static function isTeacherPost($postId)
	{
		$postId = (int) $postId;
		if ($postId === 2) {
			return true;
		}
		return self::postTitle($postId) === 'teacher';
	}

	/**
	 * Classroom teachers and similar academic posts. School leaders and finance posts stay out.
	 */
	public static function isAcademicStaffPost($postId)
	{
		$postId = (int) $postId;
		if (self::isTeacherPost($postId)) {
			return true;
		}
		if (self::isFullAccessPost($postId) || self::isChiefAccountantPost($postId) || self::isHeadMasterEquivalent($postId)) {
			return false;
		}
		if (in_array($postId, self::FINANCE_FULL_CONTROL_POSTS, true) || in_array($postId, self::FEE_OPERATOR_POSTS, true)) {
			return false;
		}
		$t = self::postTitle($postId);
		if ($t === '') {
			return false;
		}
		if (strpos($t, 'finance') !== false || strpos($t, 'account') !== false || strpos($t, 'cashier') !== false || strpos($t, 'bursar') !== false) {
			return false;
		}
		if (preg_match('/\b(head|deputy|director|principal|dean|coordinator)\b/', $t)) {
			return false;
		}
		return (bool) preg_match('/\b(teacher|tutor|lecturer|instructor|academic)\b/', $t);
	}

	/**
	 * Who may be named in charge of a dormitory: matron, patron, customer care,
	 * and other school leaders. Classroom teachers are not included.
	 */
	public static function canChargeDormitory($postId): bool
	{
		$postId = (int) $postId;
		if ($postId < 1 || self::isTeacherPost($postId)) {
			return false;
		}
		$t = self::postTitle($postId);
		if ($t === '') {
			return false;
		}
		if (preg_match('/\b(teacher|tutor|lecturer|instructor)\b/', $t)
			&& !preg_match('/\b(head|deputy|director|principal|dean|coordinator)\b/', $t)) {
			return false;
		}
		if (strpos($t, 'patron') !== false || strpos($t, 'matron') !== false) {
			return true;
		}
		if (strpos($t, 'customer care') !== false) {
			return true;
		}
		return (bool) preg_match('/\b(head|deputy|director|principal|dean|coordinator|headmaster|headmistress)\b/', $t);
	}

	/**
	 * Material Check and Dormitories: Patron, Matron, Customer Care, Director,
	 * Head of Discipline, Deputy HT Discipline.
	 */
	public static function canUseMaterialAndDormitory($postId)
	{
		if (self::isChiefAccountantPost($postId)) {
			return true;
		}
		$t = self::postTitle((int) $postId);
		if ($t === '') {
			return false;
		}
		if (strpos($t, 'patron') !== false || strpos($t, 'matron') !== false) {
			return true;
		}
		if (strpos($t, 'customer care') !== false) {
			return true;
		}
		if ($t === 'director' || $t === 'executive principal') {
			return true;
		}
		if (strpos($t, 'head of discipline') !== false || strpos($t, 'dean of discipline') !== false) {
			return true;
		}
		if (strpos($t, 'dht discipline') !== false) {
			return true;
		}
		if (strpos($t, 'discipline') !== false && (strpos($t, 'deputy') !== false || strpos($t, 'dht') !== false)) {
			return true;
		}
		return false;
	}

	/**
	 * @param string[] $keys
	 * @return string[]
	 */
	public static function applyRoleMenuPolicy(array $keys, $postId)
	{
		$postId = (int) $postId;
		if (self::isChiefAccountantPost($postId)) {
			$keys = array_values(array_unique(array_merge(self::allKeys(), $keys)));
		}
		if (self::isTeacherPost($postId)) {
			$drop = array_flip(self::groupKeys('behavior'));
			$keys = array_values(array_filter($keys, static function ($k) use ($drop) {
				return !isset($drop[$k]);
			}));
		}
		if (self::isAcademicStaffPost($postId)) {
			$keys = array_values(array_filter($keys, static function ($k) {
				return !self::isFinanceMenuKey($k);
			}));
		}
		if (self::canUseMaterialAndDormitory($postId)) {
			$keys[] = 'students';
			$keys[] = 'student_material_check';
			$keys[] = 'hostel_allocate';
			$keys[] = 'dormitory_attendance';
		} else {
			$keys = array_values(array_filter($keys, static function ($k) {
				return $k !== 'student_material_check' && $k !== 'hostel_allocate' && $k !== 'dormitory_attendance';
			}));
		}
		if (self::isChiefAccountantPost($postId)) {
			$keys = self::withoutMarksMenus($keys);
		}
		// Phone leave board and the Leave Management page follow this key.
		if (self::isExecutivePrincipalPost($postId)
			|| self::isDirectorPost($postId)
			|| self::isCustomerCarePost($postId)) {
			$keys[] = 'leave_management';
		}
		if (self::canUseClinic($postId)) {
			$keys[] = 'clinic';
			$keys[] = 'clinic_daily';
		} else {
			$keys = array_values(array_filter($keys, static function ($k) {
				return $k !== 'clinic' && $k !== 'clinic_daily';
			}));
		}
		return array_values(array_unique($keys));
	}

	/**
	 * Director, Head Teacher, Dean of Studies, Deputy HT Academics, and Headmaster
	 * see every Marks submenu.
	 *
	 * @param int $postId
	 * @return bool
	 */
	public static function seesAllMarksMenus($postId)
	{
		$postId = (int) $postId;
		if (self::isChiefAccountantPost($postId)) {
			return false;
		}
		if (self::isFullAccessPost($postId) || self::isHeadMasterEquivalent($postId)) {
			return true;
		}
		return self::canEnterAllCourseMarks($postId, self::postTitle($postId));
	}

	/** @return string[] */
	public static function marksMenuKeys()
	{
		return [
			'marks',
			'marks_entry',
			'get_uploaded_marks',
			'student_report',
			'get_periodic_report',
			'get_periodic_marks',
			'proclamation_list',
			'student_term_results',
			'class-deliberation',
			'finish_deliberation',
			'deliberation_settings',
		];
	}

	/** Head master / Headmistress / Head Teacher. */
	public static function isHeadMasterEquivalent($postId)
	{
		return in_array((int) $postId, self::HEAD_MASTER_EQUIVALENT_POSTS, true);
	}

	/** School head or Director of studies. */
	public static function isHeadMasterOrDos($postId)
	{
		$postId = (int) $postId;
		return $postId === 3 || self::isHeadMasterEquivalent($postId);
	}

	/**
	 * May enter / edit marks for every course in the school (not only assigned classes).
	 * Director, Head master, Head Teacher, Director of studies (including DOS primary), DHT ACADEMICS.
	 */
	public static function canEnterAllCourseMarks($postId, $postTitle = '')
	{
		$postId = (int) $postId;
		if (self::isHeadMasterOrDos($postId) || $postId === 29) {
			return true;
		}
		$title = strtolower(trim(preg_replace('/\s+/', ' ', (string) $postTitle)));
		if ($title === '') {
			$title = self::postTitle($postId);
		}
		if ($title === '') {
			return false;
		}
		if (strpos($title, 'finance') !== false) {
			return false;
		}
		$allowed = [
			'director',
			'head master',
			'headmaster',
			'head mistress',
			'headmistress',
			'head teacher',
			'headteacher',
			'director of studies',
			'dos primary',
			'director of studies primary',
			'primary dos',
			'dean of studies',
			'dht academics',
			'deputy ht academics',
			'deputy head teacher academics',
			'deputy headteacher academics',
		];
		if (in_array($title, $allowed, true)) {
			return true;
		}
		if (strpos($title, 'director of studies') !== false || $title === 'dos') {
			return true;
		}
		if (preg_match('/\bdos\b/', $title) && preg_match('/\bprimary\b/', $title)) {
			return true;
		}
		if (preg_match('/\bdht\b.*\bacademic/', $title)) {
			return true;
		}
		if (preg_match('/\bdeputy\b.*\b(ht|head\s*teacher|headteacher)\b.*\bacademic/', $title)) {
			return true;
		}
		return false;
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
			$keys = array_merge($keys, self::groupKeys('daily_visitors'));
		}

		// Pedagogical: is_allowed(1, 3, 5, 6, 13, 15, 17, 18) plus Coordinator
		if (in_array($postId, [5, 6, 13, 15, 17], true) || self::isCoordinatorPost($postId)) {
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

		// Fees: Accountant and Cashier record and print receipts immediately
			if (in_array($postId, [8, 9, 28], true)) {
				$keys = array_merge($keys, self::feeMenuKeys());
			$keys = array_merge($keys, self::childBudgetPrepareKeys());
		}

		if (in_array($postId, [21, 24], true)) {
			$keys[] = 'finance';
		}
		if ($postId === 24) {
			$keys = array_merge($keys, self::feeMenuKeys());
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

		// Storekeeper always receives and issues stock. Corrections stay on Level clearance.
		if ((int) $postId === 12) {
			$keys = array_merge($keys, self::stockKeeperKeys());
		}

		// Transport: is_allowed(1, 5, 6, 7, 3)
		if (in_array($postId, [5, 6, 7], true)) {
			$keys = array_merge($keys, self::groupKeys('transport'));
		}

		// Pocket money: accountant post, and Director of Finance (full pocket pages).
		if ($postId === 14 || $postId === 24) {
			$keys[] = 'pocket_money';
			$keys = array_merge($keys, self::pocketMenuKeys());
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
				|| $has('discipline_record_entry') || $has('discipline_record') || $has('lateness_report'),
			'visiting' => self::groupVisibleForKeys($allowedKeys, 'parent_visiting'),
			'permission' => self::groupVisibleForKeys($allowedKeys, 'permissions')
				|| $has('permission_entry') || $has('permission_record'),
			'students' => self::groupVisibleForKeys($allowedKeys, 'students')
				|| $has('register-student') || $has('students')
				|| $has('assign-card') || $has('student-photo')
				|| $has('student-cards') || $has('pendingRegistrations')
				|| $has('dismissedStudent') || $has('student_material_check')
				|| $has('hostel_allocate'),
			'staffs' => self::groupVisibleForKeys($allowedKeys, 'staffs')
				|| $has('staffs') || $has('staff-cards'),
			'attendance' => $has('attendance_record') || $has('marks_entry')
				|| self::groupVisibleForKeys($allowedKeys, 'marks'),
			'daily_attendance' => $has('attendance_record') || $has('attendance-card'),
			'dormitory_attendance' => $has('dormitory_attendance') || $has('hostel_allocate'),
			'payment' => self::groupVisibleForKeys($allowedKeys, 'fees')
				|| $has('fees_entry') || $has('extra_fees_entry'),
			'library' => self::groupVisibleForKeys($allowedKeys, 'library')
				|| $has('book_management')
				|| self::groupVisibleForKeys($allowedKeys, 'asset_management'),
			'marks' => self::groupVisibleForKeys($allowedKeys, 'marks') || $has('marks_entry'),
			'transport' => self::groupVisibleForKeys($allowedKeys, 'transport'),
			'leaves' => $has('leave_application') || $has('leave_management'),
			'cashflow' => self::groupVisibleForKeys($allowedKeys, 'budget_cashflow'),
			'pocket' => self::groupVisibleForKeys($allowedKeys, 'pocket_money')
				|| $has('pocket_money') || $has('pocket_money_ledger')
				|| $has('pocket_cash_withdraw') || $has('pocket_pos') || $has('parents_account')
				|| $has('pocket_money_report') || $has('canteen'),
			'stock' => self::groupVisibleForKeys($allowedKeys, 'stock_management')
				|| $has('stk_entry') || $has('stk_issue') || $has('stk_dashboard')
				|| $has('stk_report_daily') || $has('stk_report_weekly') || $has('stk_report_monthly'),
			'assets' => self::groupVisibleForKeys($allowedKeys, 'asset_management')
				|| $has('asset_dashboard') || $has('asset_assets'),
			'clinic' => self::groupVisibleForKeys($allowedKeys, 'clinic')
				|| $has('clinic') || $has('clinic_daily'),
		];
	}
}
