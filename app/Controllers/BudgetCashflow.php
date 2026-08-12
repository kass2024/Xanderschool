<?php

namespace App\Controllers;

use App\Models\BudgetSchemaModel;
use App\Services\Budget\BranchContextService;
use App\Services\Budget\BudgetAvailabilityService;
use App\Services\Budget\BudgetCalculationService;
use App\Services\Budget\BudgetNotificationService;
use App\Services\Budget\BudgetPermissionService;
use App\Services\Budget\MobileScanBridgeService;
use App\Services\Budget\BudgetExcelFillService;
use App\Services\Budget\BudgetTemplateImportService;
use App\Services\Budget\BudgetWorkflowService;
use App\Services\Budget\CashRequestWorkflowService;
use App\Services\Budget\DocumentStorageService;
use App\Services\Budget\FinancialAuditService;
use App\Services\Budget\PaymentService;
use App\Services\Budget\TermBudgetResetService;
use App\Services\Budget\TermExpensesBudgetImportService;
use App\Services\Budget\GeminiBudgetAnalysisService;
use App\Services\Budget\SchoolFeesBudgetProjectionService;
use App\Services\Budget\BudgetEmptyAmountsService;
use App\Services\Budget\CashRequestApprovalPolicy;
use App\Services\SchoolHierarchyService;

class BudgetCashflow extends Home
{
	protected function bootBudget()
	{
		$this->_preset();
		$schoolId = (int) $this->session->get('soma_school_id');
		$staffId = (int) $this->session->get('soma_id');
		$schema = new BudgetSchemaModel();
		$schema->ensureSchema();
		$schema->seedFoundation($schoolId, $staffId);
		return [$schoolId, $staffId];
	}

	protected function denyMenu($key)
	{
		if (!function_exists('menu_clearance_allowed') || !menu_clearance_allowed($key)) {
			$this->session->setFlashdata('error', 'You do not have access to Budget & Cash Flow.');
			header('Location: ' . base_url('dashboard'));
			exit;
		}
	}

	protected function denyMenuAny(array $keys)
	{
		if (function_exists('budget_menu_any') && budget_menu_any($keys)) {
			return;
		}
		$this->denyMenu($keys[0] ?? 'budget_dashboard');
	}

	protected function branchFinancialSummary($branchId, $db)
	{
		$branchId = (int) $branchId;
		$budget = $db->table('budgets')->where('branch_id', $branchId)->where('status', 'APPROVED')
			->orderBy('id', 'DESC')->get(1)->getRowArray();
		$totalBudget = $budget ? (float) $budget['total_expenses'] : 0.0;
		$totalIncome = $budget ? (float) $budget['total_income'] : 0.0;
		$periodTitle = '';
		$enrollment = 0;
		if ($budget && !empty($budget['notes'])) {
			$setup = json_decode($budget['notes'], true);
			if (is_array($setup)) {
				$enrollment = (int) ($setup['enrollment'] ?? 0);
			}
		}
		if ($budget && !empty($budget['budget_period_id'])) {
			$period = $db->table('budget_periods')->where('id', (int) $budget['budget_period_id'])->get(1)->getRowArray();
			$periodTitle = $period['title'] ?? '';
		}
		$availSvc = new BudgetAvailabilityService();
		$lineVariances = [];
		$totalUsed = 0.0;
		if ($budget) {
			$lines = $db->table('budget_lines')->where('budget_id', (int) $budget['id'])
				->orderBy('sort_order', 'ASC')->get()->getResultArray();
			foreach ($lines as $line) {
				if (stripos($line['section_label'] ?? '', 'INCOME') !== false) {
					continue;
				}
				$budgetAmt = (float) $line['annual_amount'];
				$avail = $availSvc->lineAvailability((int) $line['id']);
				$used = $avail ? ((float) $avail['paid'] + (float) $avail['committed']) : 0.0;
				$excelUsed = null;
				if (!empty($line['assumptions'])) {
					$meta = json_decode($line['assumptions'], true);
					if (is_array($meta) && isset($meta['excel_used'])) {
						$excelUsed = (float) $meta['excel_used'];
					}
				}
				$displayUsed = $used > 0 ? $used : ($excelUsed ?? 0.0);
				if (empty($line['is_total_row'])) {
					$totalUsed += $displayUsed;
				}
				$lineVariances[] = [
					'id' => (int) $line['id'],
					'section' => $line['section_label'],
					'category' => $line['category'],
					'budget' => $budgetAmt,
					'used' => round($displayUsed, 2),
					'variance' => round($budgetAmt - $displayUsed, 2),
					'is_total_row' => (int) ($line['is_total_row'] ?? 0),
					'utilization_pct' => $budgetAmt > 0 ? round(($displayUsed / $budgetAmt) * 100, 1) : 0,
				];
			}
		}
		$paidRow = $db->query(
			"SELECT COALESCE(SUM(p.amount),0) AS t FROM cash_request_payments p
			INNER JOIN cash_requests cr ON cr.id = p.cash_request_id
			WHERE cr.branch_id = ? AND p.status = 'completed'",
			[$branchId]
		)->getRowArray();
		$totalActual = (float) ($paidRow['t'] ?? 0);
		if ($totalUsed <= 0 && $totalActual > 0) {
			$totalUsed = $totalActual;
		}
		$variance = round($totalBudget - $totalUsed, 2);
		$variancePct = $totalBudget > 0 ? round(($variance / $totalBudget) * 100, 1) : 0.0;
		return [
			'budget' => $budget,
			'period_title' => $periodTitle,
			'total_budget' => $totalBudget,
			'total_income' => $totalIncome,
			'total_actual' => $totalUsed,
			'total_paid_cashflow' => $totalActual,
			'variance' => $variance,
			'variance_pct' => $variancePct,
			'enrollment' => $enrollment,
			'line_variances' => $lineVariances,
		];
	}

	protected function denyPerm($perm)
	{
		$perms = new BudgetPermissionService();
		$perms->denyRedirect($perm);
	}

	protected function ctx()
	{
		list($schoolId, $staffId) = $this->bootBudget();
		$perms = new BudgetPermissionService();
		$branchCtx = new BranchContextService();
		$postId = (int) $this->session->get('soma_post');
		$branchId = $perms->primaryBranchId($staffId, $schoolId);
		$db = \Config\Database::connect();
		$branch = $branchId ? $db->table('branches')->where('id', $branchId)->get(1)->getRowArray() : null;
		$orgId = $branch ? (int) $branch['organization_id'] : 0;
		$isWisdom = $branchCtx->isWisdomSchoolId($schoolId);
		$isCentral = $branchCtx->hasCentralDashboard($staffId, $postId, $schoolId);
		return compact('schoolId', 'staffId', 'postId', 'branchId', 'orgId', 'branch', 'perms', 'branchCtx', 'isWisdom', 'isCentral');
	}

	public function dashboard()
	{
		$this->denyMenu('budget_dashboard');
		$c = $this->ctx();
		$data = $this->data;
		$db = \Config\Database::connect();
		$data['title'] = 'Budget & Cash Flow Dashboard';
		$data['subtitle'] = 'Budget & Cash Flow';
		$data['page'] = 'budget_dashboard';
		$data['is_wisdom'] = $c['isWisdom'];
		$data['is_central'] = $c['isCentral'];
		$data['branch_label'] = $c['branch']
			? $c['branchCtx']->displaySchoolBranchLabel($c['schoolId'], $c['branch'], false)
			: session('soma_school');
		$data['ai_enabled'] = (new GeminiBudgetAnalysisService())->isConfigured();
		$data['ai_auto'] = true;
		$data['gemini_enabled'] = $data['ai_enabled']; // legacy alias
		$data['gemini_auto'] = $data['ai_auto'];

		if ($c['isCentral']) {
			$data['branch_stats'] = [];
			$branches = $c['branchCtx']->accessibleBranches($c['staffId'], $c['postId'], $c['schoolId'], true);
			foreach ($branches as $br) {
				$bid = (int) $br['id'];
				$data['branch_stats'][] = [
					'display_name' => $br['display_name'],
					'branch_id' => $bid,
					'draft_budgets' => $db->table('budgets')->where('branch_id', $bid)->where('status', 'DRAFT')->countAllResults(),
					'pending_cash' => $db->table('cash_requests')->where('branch_id', $bid)->whereNotIn('status', ['DRAFT','CLOSED','CANCELLED','REJECTED','VOIDED'])->countAllResults(),
					'awaiting_payment' => $db->table('cash_requests')->where('branch_id', $bid)->where('status', 'FINANCE_AUTHORIZED')->countAllResults(),
					'submitted_budgets' => $db->table('budgets')->where('branch_id', $bid)->whereIn('status', ['SUBMITTED','PROCUREMENT_REVIEW','BUDGET_MANAGER_REVIEW','DEPUTY_DIRECTOR_REVIEW'])->countAllResults(),
					'approved_budgets' => $db->table('budgets')->where('branch_id', $bid)->where('status', 'APPROVED')->countAllResults(),
				];
			}
			$branchIds = array_column($branches, 'id');
			$data['stats'] = [
				'draft_budgets' => array_sum(array_column($data['branch_stats'], 'draft_budgets')),
				'pending_cash' => array_sum(array_column($data['branch_stats'], 'pending_cash')),
				'awaiting_payment' => array_sum(array_column($data['branch_stats'], 'awaiting_payment')),
				'awaiting_receipt' => empty($branchIds) ? 0 : $db->table('cash_requests')->whereIn('branch_id', $branchIds)->where('status', 'PAID')->countAllResults(),
			];
			$data['budget_pipeline'] = empty($branchIds) ? [] : [
				'DRAFT' => $db->table('budgets')->whereIn('branch_id', $branchIds)->where('status', 'DRAFT')->countAllResults(),
				'SUBMITTED' => $db->table('budgets')->whereIn('branch_id', $branchIds)->where('status', 'SUBMITTED')->countAllResults(),
				'PROCUREMENT_REVIEW' => $db->table('budgets')->whereIn('branch_id', $branchIds)->where('status', 'PROCUREMENT_REVIEW')->countAllResults(),
				'BUDGET_MANAGER_REVIEW' => $db->table('budgets')->whereIn('branch_id', $branchIds)->where('status', 'BUDGET_MANAGER_REVIEW')->countAllResults(),
				'DEPUTY_DIRECTOR_REVIEW' => $db->table('budgets')->whereIn('branch_id', $branchIds)->where('status', 'DEPUTY_DIRECTOR_REVIEW')->countAllResults(),
				'APPROVED' => $db->table('budgets')->whereIn('branch_id', $branchIds)->where('status', 'APPROVED')->countAllResults(),
				'RETURNED' => $db->table('budgets')->whereIn('branch_id', $branchIds)->where('status', 'RETURNED')->countAllResults(),
			];
			$data['cash_pipeline'] = empty($branchIds) ? [] : [
				'SUBMITTED' => $db->table('cash_requests')->whereIn('branch_id', $branchIds)->where('status', 'SUBMITTED')->countAllResults(),
				'HEADTEACHER_APPROVED' => $db->table('cash_requests')->whereIn('branch_id', $branchIds)->where('status', 'HEADTEACHER_APPROVED')->countAllResults(),
				'PROCUREMENT_APPROVED' => $db->table('cash_requests')->whereIn('branch_id', $branchIds)->where('status', 'PROCUREMENT_APPROVED')->countAllResults(),
				'BUDGET_APPROVED' => $db->table('cash_requests')->whereIn('branch_id', $branchIds)->where('status', 'BUDGET_APPROVED')->countAllResults(),
				'FINANCE_AUTHORIZED' => $db->table('cash_requests')->whereIn('branch_id', $branchIds)->where('status', 'FINANCE_AUTHORIZED')->countAllResults(),
				'PAID' => $db->table('cash_requests')->whereIn('branch_id', $branchIds)->where('status', 'PAID')->countAllResults(),
			];
			// Master HQ financial snapshot (own branch) for AI + KPIs when available
			$data['financials'] = $this->branchFinancialSummary((int) $c['branchId'], $db);
		} else {
			$bid = (int) $c['branchId'];
			$data['stats'] = [
				'draft_budgets' => $db->table('budgets')->where('branch_id', $bid)->where('status', 'DRAFT')->countAllResults(),
				'pending_cash' => $db->table('cash_requests')->where('branch_id', $bid)->whereNotIn('status', ['DRAFT','CLOSED','CANCELLED','REJECTED','VOIDED'])->countAllResults(),
				'awaiting_payment' => $db->table('cash_requests')->where('branch_id', $bid)->where('status', 'FINANCE_AUTHORIZED')->countAllResults(),
				'awaiting_receipt' => $db->table('cash_requests')->where('branch_id', $bid)->where('status', 'PAID')->countAllResults(),
			];
			$data['branch_stats'] = [];
			$data['financials'] = $this->branchFinancialSummary($bid, $db);
			$data['budget_pipeline'] = [
				'DRAFT' => $db->table('budgets')->where('branch_id', $bid)->where('status', 'DRAFT')->countAllResults(),
				'SUBMITTED' => $db->table('budgets')->where('branch_id', $bid)->where('status', 'SUBMITTED')->countAllResults(),
				'PROCUREMENT_REVIEW' => $db->table('budgets')->where('branch_id', $bid)->where('status', 'PROCUREMENT_REVIEW')->countAllResults(),
				'BUDGET_MANAGER_REVIEW' => $db->table('budgets')->where('branch_id', $bid)->where('status', 'BUDGET_MANAGER_REVIEW')->countAllResults(),
				'DEPUTY_DIRECTOR_REVIEW' => $db->table('budgets')->where('branch_id', $bid)->where('status', 'DEPUTY_DIRECTOR_REVIEW')->countAllResults(),
				'APPROVED' => $db->table('budgets')->where('branch_id', $bid)->where('status', 'APPROVED')->countAllResults(),
				'RETURNED' => $db->table('budgets')->where('branch_id', $bid)->where('status', 'RETURNED')->countAllResults(),
			];
			$data['cash_pipeline'] = [
				'SUBMITTED' => $db->table('cash_requests')->where('branch_id', $bid)->where('status', 'SUBMITTED')->countAllResults(),
				'HEADTEACHER_APPROVED' => $db->table('cash_requests')->where('branch_id', $bid)->where('status', 'HEADTEACHER_APPROVED')->countAllResults(),
				'PROCUREMENT_APPROVED' => $db->table('cash_requests')->where('branch_id', $bid)->where('status', 'PROCUREMENT_APPROVED')->countAllResults(),
				'BUDGET_APPROVED' => $db->table('cash_requests')->where('branch_id', $bid)->where('status', 'BUDGET_APPROVED')->countAllResults(),
				'FINANCE_AUTHORIZED' => $db->table('cash_requests')->where('branch_id', $bid)->where('status', 'FINANCE_AUTHORIZED')->countAllResults(),
				'PAID' => $db->table('cash_requests')->where('branch_id', $bid)->where('status', 'PAID')->countAllResults(),
			];
		}
		$data['budget_view_only'] = \Config\MenuClearance::isChildBudgetViewOnly($c['postId'], $c['schoolId']);
		$data['can_prepare_budget'] = \Config\MenuClearance::canPrepareBudgetAtSchool($c['postId'], $c['schoolId'])
			&& $c['perms']->can($c['staffId'], $c['postId'], 'budget.prepare');

		$feesSvc = new SchoolFeesBudgetProjectionService();
		$data['fees_projection'] = $feesSvc->projectForSchool((int) $c['schoolId']);
		$data['fees_projection_branches'] = [];
		if (!empty($c['isCentral']) && !empty($data['branch_stats'])) {
			foreach ($c['branchCtx']->accessibleBranches($c['staffId'], $c['postId'], $c['schoolId'], true) as $br) {
				$sid = (int) ($br['school_id'] ?? 0);
				if ($sid < 1) {
					continue;
				}
				$fp = $feesSvc->projectForSchool($sid);
				$data['fees_projection_branches'][] = [
					'display_name' => $br['display_name'] ?? ('School #' . $sid),
					'school_id' => $sid,
					'term_1' => (float) ($fp['term_1'] ?? 0),
					'term_2' => (float) ($fp['term_2'] ?? 0),
					'term_3' => (float) ($fp['term_3'] ?? 0),
					'annual' => (float) ($fp['annual'] ?? 0),
					'boarding_students' => (int) ($fp['boarding_students'] ?? 0),
					'day_students' => (int) ($fp['day_students'] ?? 0),
					'total_students' => (int) ($fp['total_students'] ?? 0),
					'success' => !empty($fp['success']),
					'error' => $fp['error'] ?? null,
				];
			}
		}

		$data['content'] = view('pages/budget/dashboard', $data);
		return view('main', $data);
	}

	public function fill_budget_from_excel()
	{
		$this->bootBudget();
		$this->denyPerm('budget.templates.upload');
		$c = $this->ctx();
		$hierarchy = new SchoolHierarchyService();
		if (!$hierarchy->canManageBudgetLineStructure($c['schoolId'])) {
			return $this->response->setJSON(['error' => 'Only the master school may import or add budget line items. Child schools fill amounts on existing lines only.']);
		}
		// Structure only — do not import Excel quantities/money. Director fills amounts; School Fees auto-syncs.
		return $this->response->setJSON([
			'error' => 'Excel amount import is disabled. Use “Restore empty lines” so all line items appear with blank amounts (School Fees still auto-fills from fees settings).',
		]);
	}

	/**
	 * Restore all template line items and clear amounts (except School Fees projection).
	 */
	public function reset_budget_empty_amounts()
	{
		$this->bootBudget();
		$this->denyMenu('budget_prepare');
		$c = $this->ctx();
		$budgetId = (int) ($this->request->getPost('budget_id') ?? 0);
		$budget = $this->loadEditableBudgetForSave($c, $budgetId);
		if (!$budget) {
			return $this->response->setJSON(['error' => 'Budget not found.']);
		}
		$status = (string) ($budget['status'] ?? '');
		if (!BudgetWorkflowService::canEditBudgetAmounts($status, $c['perms'], $c['staffId'], $c['postId'])) {
			return $this->response->setJSON(['error' => 'Budget is not editable.']);
		}
		$db = \Config\Database::connect();
		$branch = $db->table('branches')->where('id', (int) $budget['branch_id'])->get(1)->getRowArray();
		$schoolId = (int) ($branch['school_id'] ?? $c['schoolId']);
		$result = (new BudgetEmptyAmountsService())->resetEmptyExceptSchoolFees($budgetId, $schoolId, (int) $c['staffId']);
		if (empty($result['success'])) {
			return $this->response->setJSON(['error' => $result['error'] ?? 'Reset failed.']);
		}
		$sf = $result['school_fees']['annual'] ?? ($result['projection']['annual'] ?? 0);
		return $this->response->setJSON([
			'success' => sprintf(
				'Restored %d missing line(s), cleared %d amount line(s). School Fees set to %s RWF from fees × students.',
				(int) ($result['lines_ensured'] ?? 0),
				(int) ($result['cleared'] ?? 0),
				number_format((float) $sf, 0)
			),
			'result' => $result,
		]);
	}

	/**
	 * Project / apply School Fees income from fees management × student counts (boarding/day).
	 */
	public function fill_school_fees_income()
	{
		$this->bootBudget();
		$this->denyMenu('budget_prepare');
		$c = $this->ctx();
		$budgetId = (int) ($this->request->getPost('budget_id') ?? 0);
		$apply = (int) ($this->request->getPost('apply') ?? 1) === 1;
		$db = \Config\Database::connect();
		$budget = $this->loadEditableBudgetForSave($c, $budgetId);
		if (!$budget) {
			return $this->response->setJSON(['error' => 'Budget not found.']);
		}
		$status = (string) ($budget['status'] ?? '');
		if ($apply && !BudgetWorkflowService::canEditBudgetAmounts($status, $c['perms'], $c['staffId'], $c['postId'])) {
			return $this->response->setJSON(['error' => 'Budget is not editable.']);
		}

		$branch = $db->table('branches')->where('id', (int) $budget['branch_id'])->get(1)->getRowArray();
		$schoolId = (int) ($branch['school_id'] ?? $c['schoolId']);
		$setup = [];
		if (!empty($budget['notes'])) {
			$decoded = json_decode($budget['notes'], true);
			if (is_array($decoded)) {
				$setup = $decoded;
			}
		}
		$yearHint = $setup['academic_year'] ?? null;
		$proj = (new SchoolFeesBudgetProjectionService())->projectForSchool($schoolId, $yearHint);
		if (empty($proj['success'])) {
			return $this->response->setJSON([
				'error' => $proj['error'] ?? 'Could not project school fees.',
				'projection' => $proj,
			]);
		}

		$line = $db->table('budget_lines')
			->where('budget_id', $budgetId)
			->where('is_total_row', 0)
			->groupStart()
				->like('category', 'School Fee')
				->orLike('category', 'school fee')
			->groupEnd()
			->orderBy('sort_order', 'ASC')
			->get(1)->getRowArray();
		if (!$line) {
			$line = $db->table('budget_lines')
				->where('budget_id', $budgetId)
				->where('is_total_row', 0)
				->where('section_label', 'INCOME')
				->like('category', 'Fee')
				->orderBy('sort_order', 'ASC')
				->get(1)->getRowArray();
		}
		if (!$line) {
			return $this->response->setJSON([
				'error' => 'No “School Fees” income line found on this budget. Add the line first.',
				'projection' => $proj,
			]);
		}

		$payload = [
			'success' => true,
			'message' => sprintf(
				'School Fees from fees management: T1 %s · T2 %s · T3 %s RWF (%d students).',
				number_format((float) $proj['term_1'], 0),
				number_format((float) $proj['term_2'], 0),
				number_format((float) $proj['term_3'], 0),
				(int) $proj['total_students']
			),
			'line_id' => (int) $line['id'],
			'projection' => $proj,
		];

		if (!$apply) {
			return $this->response->setJSON($payload);
		}

		$calc = new BudgetCalculationService();
		$update = [
			'term_1_amount' => (float) $proj['term_1'],
			'term_2_amount' => (float) $proj['term_2'],
			'term_3_amount' => (float) $proj['term_3'],
			'calculation_mode' => 'term_sum',
			'user_amount' => (float) $proj['annual'],
			'assumptions' => (string) ($proj['notes'] ?? ''),
		];
		$update['annual_amount'] = $calc->lineAnnualAmount(array_merge($line, $update));
		$db->table('budget_lines')->where('id', (int) $line['id'])->where('budget_id', $budgetId)->update($update);
		$totals = $calc->recalculateBudgetTotals($budgetId);

		// Keep setup enrollment in sync when empty
		if ((int) ($setup['enrollment'] ?? 0) < 1) {
			$setup['enrollment'] = (int) $proj['total_students'];
		}
		$setup['fees_projection_at'] = date('Y-m-d H:i:s');
		$setup['fees_projection_notes'] = $proj['notes'] ?? '';
		if (empty($setup['academic_year']) && !empty($proj['academic_year_title'])) {
			$setup['academic_year'] = $proj['academic_year_title'];
		}
		$db->table('budgets')->where('id', $budgetId)->update([
			'notes' => json_encode($setup),
			'updated_at' => date('Y-m-d H:i:s'),
			'updated_by' => $c['staffId'],
		]);

		$payload['totals'] = $totals;
		$payload['applied'] = true;
		return $this->response->setJSON($payload);
	}

	public function dashboard_ai_json()
	{
		$this->denyMenu('budget_dashboard');
		$c = $this->ctx();
		$db = \Config\Database::connect();
		$fin = $this->branchFinancialSummary((int) $c['branchId'], $db);
		$stats = [
			'pending_cash' => $db->table('cash_requests')->where('branch_id', $c['branchId'])
				->whereNotIn('status', ['DRAFT','CLOSED','CANCELLED','REJECTED','VOIDED'])->countAllResults(),
			'awaiting_payment' => $db->table('cash_requests')->where('branch_id', $c['branchId'])
				->where('status', 'FINANCE_AUTHORIZED')->countAllResults(),
			'draft_budgets' => $db->table('budgets')->where('branch_id', $c['branchId'])->where('status', 'DRAFT')->countAllResults(),
		];
		$ctx = array_merge($fin, $stats, [
			'branch_label' => $c['branch']
				? $c['branchCtx']->displaySchoolBranchLabel($c['schoolId'], $c['branch'], false)
				: session('soma_school'),
			'is_central' => !empty($c['isCentral']),
			'role_hint' => $this->dashboardAiRoleHint((int) $c['postId']),
		]);

		if (!empty($c['isCentral'])) {
			$branches = $c['branchCtx']->accessibleBranches($c['staffId'], $c['postId'], $c['schoolId'], true);
			$branchRollup = [];
			foreach ($branches as $br) {
				$bid = (int) $br['id'];
				$branchRollup[] = [
					'branch' => $br['display_name'],
					'draft_budgets' => $db->table('budgets')->where('branch_id', $bid)->where('status', 'DRAFT')->countAllResults(),
					'in_approval' => $db->table('budgets')->where('branch_id', $bid)->whereIn('status', ['SUBMITTED','PROCUREMENT_REVIEW','BUDGET_MANAGER_REVIEW','DEPUTY_DIRECTOR_REVIEW'])->countAllResults(),
					'approved' => $db->table('budgets')->where('branch_id', $bid)->where('status', 'APPROVED')->countAllResults(),
					'active_cash_requests' => $db->table('cash_requests')->where('branch_id', $bid)->whereNotIn('status', ['DRAFT','CLOSED','CANCELLED','REJECTED','VOIDED'])->countAllResults(),
					'awaiting_payment' => $db->table('cash_requests')->where('branch_id', $bid)->where('status', 'FINANCE_AUTHORIZED')->countAllResults(),
				];
			}
			$ctx['branch_rollup'] = $branchRollup;
			$ctx['scope'] = 'central_all_branches';
		} else {
			$ctx['scope'] = 'single_school';
			$ctx['budget_pipeline'] = [
				'DRAFT' => $db->table('budgets')->where('branch_id', $c['branchId'])->where('status', 'DRAFT')->countAllResults(),
				'SUBMITTED' => $db->table('budgets')->where('branch_id', $c['branchId'])->where('status', 'SUBMITTED')->countAllResults(),
				'PROCUREMENT_REVIEW' => $db->table('budgets')->where('branch_id', $c['branchId'])->where('status', 'PROCUREMENT_REVIEW')->countAllResults(),
				'BUDGET_MANAGER_REVIEW' => $db->table('budgets')->where('branch_id', $c['branchId'])->where('status', 'BUDGET_MANAGER_REVIEW')->countAllResults(),
				'DEPUTY_DIRECTOR_REVIEW' => $db->table('budgets')->where('branch_id', $c['branchId'])->where('status', 'DEPUTY_DIRECTOR_REVIEW')->countAllResults(),
				'APPROVED' => $db->table('budgets')->where('branch_id', $c['branchId'])->where('status', 'APPROVED')->countAllResults(),
				'RETURNED' => $db->table('budgets')->where('branch_id', $c['branchId'])->where('status', 'RETURNED')->countAllResults(),
			];
			$ctx['cash_pipeline'] = [
				'SUBMITTED' => $db->table('cash_requests')->where('branch_id', $c['branchId'])->where('status', 'SUBMITTED')->countAllResults(),
				'HEADTEACHER_APPROVED' => $db->table('cash_requests')->where('branch_id', $c['branchId'])->where('status', 'HEADTEACHER_APPROVED')->countAllResults(),
				'PROCUREMENT_APPROVED' => $db->table('cash_requests')->where('branch_id', $c['branchId'])->where('status', 'PROCUREMENT_APPROVED')->countAllResults(),
				'BUDGET_APPROVED' => $db->table('cash_requests')->where('branch_id', $c['branchId'])->where('status', 'BUDGET_APPROVED')->countAllResults(),
				'FINANCE_AUTHORIZED' => $db->table('cash_requests')->where('branch_id', $c['branchId'])->where('status', 'FINANCE_AUTHORIZED')->countAllResults(),
				'PAID' => $db->table('cash_requests')->where('branch_id', $c['branchId'])->where('status', 'PAID')->countAllResults(),
			];
		}

		$feesProj = (new SchoolFeesBudgetProjectionService())->projectForSchool((int) $c['schoolId']);
		$ctx['school_fees_projection'] = [
			'success' => !empty($feesProj['success']),
			'academic_year' => $feesProj['academic_year_title'] ?? '',
			'term_1' => (float) ($feesProj['term_1'] ?? 0),
			'term_2' => (float) ($feesProj['term_2'] ?? 0),
			'term_3' => (float) ($feesProj['term_3'] ?? 0),
			'annual' => (float) ($feesProj['annual'] ?? 0),
			'boarding_students' => (int) ($feesProj['boarding_students'] ?? 0),
			'day_students' => (int) ($feesProj['day_students'] ?? 0),
			'total_students' => (int) ($feesProj['total_students'] ?? 0),
			'classes_used' => (int) ($feesProj['classes_used'] ?? 0),
			'notes' => $feesProj['notes'] ?? '',
			'error' => $feesProj['error'] ?? null,
		];
		if (!empty($c['isCentral'])) {
			$feesBranches = [];
			foreach ($c['branchCtx']->accessibleBranches($c['staffId'], $c['postId'], $c['schoolId'], true) as $br) {
				$sid = (int) ($br['school_id'] ?? 0);
				if ($sid < 1) {
					continue;
				}
				$fp = (new SchoolFeesBudgetProjectionService())->projectForSchool($sid);
				$feesBranches[] = [
					'branch' => $br['display_name'] ?? '',
					'annual' => (float) ($fp['annual'] ?? 0),
					'total_students' => (int) ($fp['total_students'] ?? 0),
					'boarding' => (int) ($fp['boarding_students'] ?? 0),
					'day' => (int) ($fp['day_students'] ?? 0),
				];
			}
			$ctx['school_fees_projection']['branches'] = $feesBranches;
		}

		$gemini = new GeminiBudgetAnalysisService();
		if (!$gemini->isConfigured()) {
			return $this->response->setJSON(['error' => 'AI analysis unavailable — service not configured.']);
		}
		$analysis = $gemini->analyzeDashboard($ctx);
		if (!$analysis) {
			return $this->response->setJSON(['error' => $gemini->lastError() ?: 'Analysis failed.']);
		}
		return $this->response->setJSON(['success' => true, 'analysis' => $analysis]);
	}

	/** Short role context for AI follow-up tone. */
	protected function dashboardAiRoleHint($postId)
	{
		$map = [
			24 => 'Director of Finance — prioritize approvals, payment authorization, and branch exceptions.',
			19 => 'Budget Manager — prioritize availability checks, returns, and schools stuck in budget review.',
			15 => 'Principal — oversight of all branches; highlight schools needing follow-up.',
			1 => 'Head master — own-school oversight only; focus on local draft progress and request delays.',
			18 => 'Headmistress — own-school oversight only.',
			4 => 'Dean — own-school oversight only.',
			8 => 'Cashier — preparation and payment processing follow-up.',
			9 => 'Accountant — preparation and receipt filing follow-up.',
		];
		return $map[(int) $postId] ?? 'School finance user — practical next steps for follow-up.';
	}

	public function download_term_template()
	{
		$this->bootBudget();
		$this->denyPerm('budget.templates.view');
		$svc = new TermExpensesBudgetImportService();
		$path = $svc->templatePath();
		if (!$path) {
			return redirect()->to(base_url('budget/prepare'))->with('error', 'Term budget template not found.');
		}
		return $this->response->download($path, null)->setFileName(TermExpensesBudgetImportService::TEMPLATE_FILE);
	}

	public function reset_term_budget()
	{
		$this->bootBudget();
		$this->denyPerm('budget.templates.upload');
		$c = $this->ctx();
		$hierarchy = new SchoolHierarchyService();
		if (!$hierarchy->canManageBudgetLineStructure($c['schoolId'])) {
			return $this->response->setJSON(['error' => 'Only the master school may reset or rebuild budget line structure.']);
		}
		$confirm = trim((string) $this->request->getPost('confirm'));
		if ($confirm !== 'RESET') {
			return $this->response->setJSON(['error' => 'Type RESET to confirm wiping all budget and cash request data for this branch.']);
		}
		$resetSvc = new TermBudgetResetService();
		$res = $resetSvc->resetAndSeedBranch($c['orgId'], (int) $c['branchId'], $c['staffId']);
		if (empty($res['success'])) {
			return $this->response->setJSON(['error' => $res['error'] ?? 'Reset failed.']);
		}
		(new FinancialAuditService())->log('budget', (int) $res['budget_id'], 'reset_seed', $c['staffId'], null, $res, $c['orgId'], $c['branchId']);
		return $this->response->setJSON([
			'success' => 'Budget reset complete. New term budget seeded from Excel template.',
			'budget_id' => $res['budget_id'],
			'line_count' => $res['line_count'],
			'total_expenses' => $res['total_expenses'],
		]);
	}

	public function periods()
	{
		return redirect()->to(base_url('budget/prepare?tab=periods'));
	}

	public function save_period()
	{
		$this->bootBudget();
		$this->denyPerm('budget.periods.manage');
		$c = $this->ctx();
		$db = \Config\Database::connect();
		$id = (int) $this->request->getPost('id');
		$branchId = (int) $this->request->getPost('branch_id') ?: $c['branchId'];
		if (!$c['perms']->canAccessBranch($c['staffId'], $c['postId'], $c['schoolId'], $branchId)) {
			return $this->response->setJSON(['error' => 'Branch access denied.']);
		}
		$row = [
			'organization_id' => $c['orgId'],
			'branch_id' => $branchId,
			'title' => trim((string) $this->request->getPost('title')),
			'period_type' => $this->request->getPost('period_type') ?: 'annual',
			'start_date' => $this->request->getPost('start_date'),
			'end_date' => $this->request->getPost('end_date'),
			'status' => $this->request->getPost('status') ?: 'draft',
			'updated_by' => $c['staffId'],
			'updated_at' => date('Y-m-d H:i:s'),
		];
		if ($row['title'] === '' || !$row['start_date'] || !$row['end_date']) {
			return $this->response->setJSON(['error' => 'Title and dates are required.']);
		}
		if ($id > 0) {
			$db->table('budget_periods')->where('id', $id)->update($row);
		} else {
			$row['created_by'] = $c['staffId'];
			$row['created_at'] = date('Y-m-d H:i:s');
			$db->table('budget_periods')->insert($row);
			$id = (int) $db->insertID();
		}
		(new FinancialAuditService())->log('budget_period', $id, $id ? 'update' : 'create', $c['staffId'], null, $row, $c['orgId'], $branchId);
		return $this->response->setJSON(['success' => 'Budget period saved.', 'id' => $id]);
	}

	public function settings()
	{
		$this->bootBudget();
		$this->denyPerm('budget.settings.manage');
		$c = $this->ctx();
		CashRequestApprovalPolicy::ensureSchema();
		$hier = new SchoolHierarchyService();
		$isMaster = $hier->isMasterSchool((int) $c['schoolId'])
			|| !$hier->isChildSchool((int) $c['schoolId']);
		if (!$isMaster) {
			return redirect()->to(base_url('budget/dashboard'))
				->with('error', 'Cash flow approval settings are managed at the master school only.');
		}
		$db = \Config\Database::connect();
		$data = $this->data;
		$data['title'] = 'Cash flow settings';
		$data['page'] = 'budget_settings';
		$data['settings'] = $db->table('budget_settings')
			->where('organization_id', $c['orgId'])->where('branch_id', null)->get(1)->getRowArray() ?: [];
		$data['approval_tiers'] = CashRequestApprovalPolicy::loadTiersForOrg((int) $c['orgId']);
		$data['chain_labels'] = CashRequestApprovalPolicy::chainLabels();
		$data['is_master'] = true;
		$data['branch_label'] = $c['branch']
			? $c['branchCtx']->displaySchoolBranchLabel($c['schoolId'], $c['branch'], false)
			: session('soma_school');
		$data['content'] = view('pages/budget/settings', $data);
		return view('main', $data);
	}

	public function save_settings()
	{
		$this->bootBudget();
		$this->denyPerm('budget.settings.manage');
		$c = $this->ctx();
		$hier = new SchoolHierarchyService();
		$isMaster = $hier->isMasterSchool((int) $c['schoolId'])
			|| !$hier->isChildSchool((int) $c['schoolId']);
		if (!$isMaster) {
			return $this->response->setJSON(['error' => 'Only the master school can change cash flow settings.']);
		}
		CashRequestApprovalPolicy::ensureSchema();
		$db = \Config\Database::connect();

		$tiersRaw = $this->request->getPost('approval_tiers_json');
		if ($tiersRaw === null || $tiersRaw === '') {
			// Build from repeated fields
			$maxes = $this->request->getPost('tier_max') ?? [];
			$chains = $this->request->getPost('tier_chain') ?? [];
			$labels = $this->request->getPost('tier_label') ?? [];
			$built = [];
			if (is_array($maxes)) {
				foreach ($maxes as $i => $max) {
					$built[] = [
						'max_amount' => ($max === '' || $max === null) ? null : (float) $max,
						'chain' => $chains[$i] ?? CashRequestApprovalPolicy::CHAIN_FULL,
						'label' => $labels[$i] ?? '',
					];
				}
			}
			$tiers = CashRequestApprovalPolicy::parseTiers($built);
		} else {
			$tiers = CashRequestApprovalPolicy::parseTiers($tiersRaw);
		}
		if ($tiers === []) {
			$tiers = CashRequestApprovalPolicy::defaultTiers();
		}
		// Ensure an open-ended full/medium/short catch-all exists
		$hasOpen = false;
		foreach ($tiers as $t) {
			if ($t['max_amount'] === null) {
				$hasOpen = true;
				break;
			}
		}
		if (!$hasOpen) {
			$tiers[] = [
				'max_amount' => null,
				'chain' => CashRequestApprovalPolicy::CHAIN_FULL,
				'label' => 'Standard',
			];
		}

		$row = [
			'default_currency' => $this->request->getPost('default_currency') ?: 'RWF',
			'headteacher_approval_mode' => $this->request->getPost('headteacher_approval_mode') ?: 'evidence',
			'ai_enabled' => $this->request->getPost('ai_enabled') ? 1 : 0,
			'budget_utilization_alert_pct' => (float) $this->request->getPost('budget_utilization_alert_pct') ?: 80,
			'approval_tiers_json' => json_encode(array_values($tiers)),
			'updated_by' => $c['staffId'],
			'updated_at' => date('Y-m-d H:i:s'),
		];
		$exists = $db->table('budget_settings')->where('organization_id', $c['orgId'])->where('branch_id', null)->get(1)->getRowArray();
		if ($exists) {
			$db->table('budget_settings')->where('id', $exists['id'])->update($row);
		} else {
			$row['organization_id'] = $c['orgId'];
			$row['created_by'] = $c['staffId'];
			$row['created_at'] = date('Y-m-d H:i:s');
			$db->table('budget_settings')->insert($row);
		}
		return $this->response->setJSON([
			'success' => 'Cash flow settings saved.',
			'tiers' => $tiers,
		]);
	}

	public function resolve_approval_chain()
	{
		$this->bootBudget();
		$c = $this->ctx();
		$amount = (float) ($this->request->getGet('amount') ?? $this->request->getPost('amount') ?? 0);
		$resolved = CashRequestApprovalPolicy::resolveChain((int) $c['orgId'], $amount);
		return $this->response->setJSON($resolved);
	}

	public function templates()
	{
		return redirect()->to(base_url('budget/prepare?tab=templates'));
	}

	public function download_official_template()
	{
		$this->bootBudget();
		$this->denyPerm('budget.templates.view');
		$import = new BudgetTemplateImportService();
		$path = $import->officialTemplatePath();
		if (!$path) {
			return redirect()->to(base_url('budget/templates'))->with('error', 'Official template not found.');
		}
		return $this->response->download($path, null)->setFileName(BudgetTemplateImportService::OFFICIAL_TEMPLATE);
	}

	public function install_official_template()
	{
		$this->bootBudget();
		$this->denyPerm('budget.templates.upload');
		$c = $this->ctx();
		$import = new BudgetTemplateImportService();
		$res = $import->installOfficialTemplate($c['orgId'], $c['staffId'], true);
		if (empty($res['success'])) {
			return $this->response->setJSON(['error' => $res['error'] ?? 'Install failed.']);
		}
		(new FinancialAuditService())->log('budget_template', $res['template_id'], 'install_official', $c['staffId']);
		$msg = !empty($res['existing']) ? 'Official template already installed and activated.' : 'Official Wisdom template installed and activated.';
		return $this->response->setJSON([
			'success' => $msg,
			'template_id' => $res['template_id'],
			'line_count' => $res['line_count'] ?? 0,
		]);
	}

	public function upload_template()
	{
		$this->bootBudget();
		$this->denyPerm('budget.templates.upload');
		$c = $this->ctx();
		$file = $this->request->getFile('template_file');
		$doc = new DocumentStorageService();
		$stored = $doc->storeUpload($file, 'budget/templates');
		if (!$stored['success']) {
			return $this->response->setJSON($stored);
		}
		$fullPath = WRITEPATH . 'uploads/budget/templates/' . basename($stored['stored_path']);
		$import = new BudgetTemplateImportService();
		$parsed = $import->parseUpload($fullPath, $stored['original_name']);
		if (!$parsed['success']) {
			return $this->response->setJSON($parsed);
		}
		$name = trim((string) $this->request->getPost('name')) ?: 'Budget Template ' . date('Y-m-d');
		$res = $import->saveTemplateVersion($c['orgId'], $name, $fullPath, $stored['original_name'], $stored['checksum'], $c['staffId'], $parsed['rows']);
		(new FinancialAuditService())->log('budget_template', $res['template_id'], 'upload', $c['staffId']);
		return $this->response->setJSON([
			'success' => 'Template uploaded (' . ($parsed['format'] ?? 'import') . ').',
			'template_id' => $res['template_id'],
			'line_count' => $res['line_count'] ?? count($parsed['rows']),
		]);
	}

	public function activate_template()
	{
		$this->bootBudget();
		$this->denyPerm('budget.templates.activate');
		$c = $this->ctx();
		$id = (int) $this->request->getPost('template_id');
		$import = new BudgetTemplateImportService();
		$import->activateTemplateForOrg($c['orgId'], $id);
		return $this->response->setJSON(['success' => 'Template activated.']);
	}

	public function prepare()
	{
		$this->denyMenuAny(['budget_prepare', 'budget_periods', 'budget_templates', 'budget_review', 'budget_approved']);
		$c = $this->ctx();
		// Child-school leaders: no prepare workspace — send to smart dashboard
		if (\Config\MenuClearance::isChildBudgetViewOnly($c['postId'], $c['schoolId'])) {
			return redirect()->to(base_url('budget/dashboard'))->with('error', 'Head master and school leaders can only view the Budget Dashboard. Cashier or Accountant prepare the budget.');
		}
		$data = $this->data;
		$db = \Config\Database::connect();
		$tab = trim((string) $this->request->getGet('tab')) ?: 'budgets';
		$tabKeys = [
			'budgets' => ['budget_prepare'],
			'periods' => ['budget_periods'],
			'review' => ['budget_review'],
			'approved' => ['budget_approved'],
		];
		if ($tab === 'templates') {
			return redirect()->to(base_url('budget/prepare'));
		}
		if (!isset($tabKeys[$tab]) || !budget_menu_any($tabKeys[$tab])) {
			$tab = 'budgets';
			if (!budget_menu_any(['budget_prepare'])) {
				foreach ($tabKeys as $k => $keys) {
					if (budget_menu_any($keys)) {
						$tab = $k;
						break;
					}
				}
			}
		}
		$data['tab'] = $tab;
		$data['title'] = 'Prepare Budget';
		$data['page'] = 'budget_prepare';
		$data['branch_label'] = $c['branch']
			? $c['branchCtx']->displaySchoolBranchLabel($c['schoolId'], $c['branch'], false)
			: session('soma_school');
		$data['budgets'] = $db->table('budgets')->where('branch_id', $c['branchId'])->orderBy('id', 'DESC')->get()->getResultArray();
		$data['periods'] = $db->table('budget_periods')->where('branch_id', $c['branchId'])->orderBy('start_date', 'DESC')->get()->getResultArray();
		$data['templates'] = $db->table('budget_templates')->where('organization_id', $c['orgId'])->where('status', 'active')->get()->getResultArray();
		$data['active_template'] = $data['templates'][0] ?? null;
		if ($tab === 'periods') {
			$this->denyPerm('budget.periods.manage');
			if ($c['isCentral']) {
				$branchIds = array_column($c['branchCtx']->accessibleBranchIds($c['staffId'], $c['postId'], $c['schoolId']), 'id');
				$data['periods'] = $db->table('budget_periods bp')
					->select('bp.*, b.name as branch_name, b.organization_id')
					->join('branches b', 'b.id = bp.branch_id')
					->whereIn('bp.branch_id', $branchIds)
					->orderBy('bp.start_date', 'DESC')->get()->getResultArray();
				foreach ($data['periods'] as &$p) {
					$p['branch_name'] = $c['branchCtx']->displayBranchName([
						'name' => $p['branch_name'],
						'organization_id' => $p['organization_id'],
					], true);
				}
				unset($p);
			} else {
				$label = $data['branch_label'];
				foreach ($data['periods'] as &$p) {
					$p['branch_name'] = $label;
				}
				unset($p);
			}
			$data['branches'] = $c['branchCtx']->accessibleBranches($c['staffId'], $c['postId'], $c['schoolId'], $c['isCentral']);
		}
		if ($tab === 'templates') {
			$this->denyPerm('budget.templates.view');
			$allTemplates = $db->table('budget_templates')->where('organization_id', $c['orgId'])->orderBy('id', 'DESC')->get()->getResultArray();
			foreach ($allTemplates as &$t) {
				$vid = (int) ($t['current_version_id'] ?? 0);
				$t['line_count'] = $vid ? $db->table('budget_template_lines')->where('version_id', $vid)->countAllResults() : 0;
			}
			unset($t);
			$data['all_templates'] = $allTemplates;
			$import = new BudgetTemplateImportService();
			$data['can_install_official'] = $import->officialTemplatePath() && $c['perms']->can($c['staffId'], $c['postId'], 'budget.templates.upload');
		}
		if ($tab === 'review') {
			BudgetWorkflowService::normalizeLegacyReviewStatuses();
			$branchIds = array_column($c['branchCtx']->accessibleBranchIds($c['staffId'], $c['postId'], $c['schoolId']), 'id');
			$statuses = BudgetWorkflowService::reviewStatusesForUser($c['perms'], $c['staffId'], $c['postId']);
			$data['review_budgets'] = [];
			if ($branchIds && $statuses) {
				$rows = $db->table('budgets b')
					->select('b.*, br.name as branch_name, br.organization_id')
					->join('branches br', 'br.id = b.branch_id')
					->whereIn('b.branch_id', $branchIds)
					->whereIn('b.status', $statuses)
					->orderBy('b.id', 'DESC')->get()->getResultArray();
				foreach ($rows as &$rb) {
					$rb['branch_name'] = $c['branchCtx']->displayBranchName([
						'name' => $rb['branch_name'],
						'organization_id' => $rb['organization_id'],
					], $c['isCentral']);
					$rb['allowed_actions'] = BudgetWorkflowService::allowedActionsForStatus(
						$rb['status'], $c['perms'], $c['staffId'], $c['postId']
					);
					$rb['pending_label'] = BudgetWorkflowService::pendingApproverLabel((string) $rb['status']);
				}
				unset($rb);
				$data['review_budgets'] = $rows;
			}
		}
		if ($tab === 'approved') {
			$branchIds = array_column($c['branchCtx']->accessibleBranchIds($c['staffId'], $c['postId'], $c['schoolId']), 'id');
			$data['approved_budgets'] = $db->table('budgets b')
				->select('b.*, br.name as branch_name, br.organization_id')
				->join('branches br', 'br.id = b.branch_id')
				->where('b.status', 'APPROVED')
				->whereIn('b.branch_id', $branchIds)->orderBy('b.id', 'DESC')->get()->getResultArray();
			foreach ($data['approved_budgets'] as &$b) {
				$b['branch_name'] = $c['branchCtx']->displayBranchName([
					'name' => $b['branch_name'],
					'organization_id' => $b['organization_id'],
				], $c['isCentral']);
			}
			unset($b);
		}
		$data['content'] = view('pages/budget/prepare', $data);
		return view('main', $data);
	}

	public function create_budget()
	{
		$this->bootBudget();
		$this->denyPerm('budget.prepare');
		$c = $this->ctx();
		if (!\Config\MenuClearance::canPrepareBudgetAtSchool($c['postId'], $c['schoolId'])) {
			return $this->response->setJSON(['error' => 'Only Cashier, Accountant, or Director of Finance can prepare the budget.']);
		}
		$db = \Config\Database::connect();
		$periodId = (int) $this->request->getPost('budget_period_id');
		if ($periodId <= 0) {
			$periodId = $this->ensureAnnualBudgetPeriod($c['orgId'], (int) $c['branchId'], $c['staffId']);
		}
		if ($periodId <= 0) {
			return $this->response->setJSON(['error' => 'Could not create budget period.']);
		}
		$yearLabel = trim((string) $this->request->getPost('academic_year'));
		if ($yearLabel === '') {
			$y = (int) date('Y');
			$yearLabel = $y . '-' . substr((string) ($y + 1), -2);
		}
		$title = trim((string) $this->request->getPost('title'));
		if ($title === '') {
			$title = 'Annual Budget ' . $yearLabel;
		}
		$db->transStart();
		$db->table('budgets')->insert([
			'organization_id' => $c['orgId'],
			'branch_id' => $c['branchId'],
			'budget_period_id' => $periodId,
			'template_version_id' => null,
			'title' => $title,
			'currency' => 'RWF',
			'status' => 'DRAFT',
			'prepared_by' => $c['staffId'],
			'prepared_at' => date('Y-m-d H:i:s'),
			'notes' => json_encode([
				'academic_year' => $yearLabel,
				'planning_type' => 'three_term_annual',
				'enrollment' => 0,
				'opening_cash' => 0,
				'planning_notes' => '',
			]),
			'created_by' => $c['staffId'],
			'created_at' => date('Y-m-d H:i:s'),
			'updated_at' => date('Y-m-d H:i:s'),
		]);
		$budgetId = (int) $db->insertID();
		$lineCount = $this->seedBudgetLines($db, $budgetId, (int) $c['schoolId']);
		if ($lineCount === 0) {
			$db->table('budgets')->where('id', $budgetId)->delete();
			return $this->response->setJSON(['error' => 'No budget line template from master school. Ask the master school to prepare the annual budget first.']);
		}
		$db->transComplete();
		return $this->response->setJSON(['success' => 'Annual budget workspace ready.', 'budget_id' => $budgetId]);
	}

	/** Master: default template. Child: copy line structure from master budget (quantities zero). */
	protected function seedBudgetLines($db, $budgetId, $schoolId)
	{
		$hierarchy = new SchoolHierarchyService();
		if (!$hierarchy->isChildSchool($schoolId)) {
			$import = new BudgetTemplateImportService();
			foreach ($import->defaultStructure() as $ln) {
				$db->table('budget_lines')->insert([
					'budget_id' => $budgetId,
					'section_label' => $ln['section'],
					'category' => $ln['normalized_label'],
					'is_total_row' => $ln['is_total_row'] ? 1 : 0,
					'is_editable' => $ln['is_editable'] ? 1 : 0,
					'calculation_mode' => 'term_sum',
					'sort_order' => $ln['sort_order'],
				]);
			}
			return count($import->defaultStructure());
		}

		$masterId = $hierarchy->masterSchoolId($schoolId);
		if ($masterId < 1) {
			return 0;
		}
		$masterBranch = $db->table('branches')->where('school_id', $masterId)->where('status', 1)
			->orderBy('id', 'ASC')->get(1)->getRowArray();
		if (!$masterBranch) {
			return 0;
		}
		$masterBudget = $db->table('budgets')->where('branch_id', (int) $masterBranch['id'])
			->whereIn('status', ['DRAFT', 'APPROVED', 'RETURNED', 'SUBMITTED'])
			->orderBy('id', 'DESC')->get(1)->getRowArray();
		if (!$masterBudget) {
			return 0;
		}
		$masterLines = $db->table('budget_lines')->where('budget_id', (int) $masterBudget['id'])
			->orderBy('sort_order', 'ASC')->get()->getResultArray();
		foreach ($masterLines as $ln) {
			$db->table('budget_lines')->insert([
				'budget_id' => $budgetId,
				'section_label' => $ln['section_label'],
				'category' => $ln['category'],
				'is_total_row' => (int) ($ln['is_total_row'] ?? 0),
				'is_editable' => (int) ($ln['is_editable'] ?? 1),
				'calculation_mode' => $ln['calculation_mode'] ?? 'term_sum',
				'sort_order' => (int) ($ln['sort_order'] ?? 0),
				'template_line_id' => $ln['template_line_id'] ?? null,
			]);
		}
		return count($masterLines);
	}

	protected function ensureAnnualBudgetPeriod(int $orgId, int $branchId, int $staffId): int
	{
		$db = \Config\Database::connect();
		$row = $db->table('budget_periods')->where('branch_id', $branchId)
			->where('period_type', 'annual')->where('status', 'open')
			->orderBy('id', 'DESC')->get(1)->getRowArray();
		if ($row) {
			return (int) $row['id'];
		}
		$y = (int) date('Y');
		$title = 'Academic Year ' . $y . '-' . substr((string) ($y + 1), -2);
		$db->table('budget_periods')->insert([
			'organization_id' => $orgId,
			'branch_id' => $branchId,
			'title' => $title,
			'period_type' => 'annual',
			'start_date' => $y . '-01-01',
			'end_date' => ($y + 1) . '-12-31',
			'status' => 'open',
			'created_by' => $staffId,
			'created_at' => date('Y-m-d H:i:s'),
			'updated_at' => date('Y-m-d H:i:s'),
		]);
		return (int) $db->insertID();
	}

	public function edit_budget($id = null)
	{
		$this->denyMenu('budget_prepare');
		$c = $this->ctx();
		$id = (int) $id;
		$db = \Config\Database::connect();
		$this->ensureBudgetLineColumns($db);
		$calc = new BudgetCalculationService();
		$allowedBranchIds = array_map('intval', array_column(
			$c['branchCtx']->accessibleBranchIds($c['staffId'], $c['postId'], $c['schoolId']),
			'id'
		));
		if (!$allowedBranchIds) {
			$allowedBranchIds = [(int) $c['branchId']];
		}
		$budget = $db->table('budgets b')
			->select('b.*, bp.title as period_title, bp.start_date, bp.end_date')
			->join('budget_periods bp', 'bp.id = b.budget_period_id', 'left')
			->where('b.id', $id)
			->whereIn('b.branch_id', $allowedBranchIds)
			->get(1)->getRowArray();
		if (!$budget) {
			return redirect()->to(base_url('budget/prepare'));
		}
		$status = (string) ($budget['status'] ?? '');
		$canEdit = BudgetWorkflowService::canEditBudgetAmounts($status, $c['perms'], $c['staffId'], $c['postId']);
		if (!$canEdit
			&& !$c['perms']->can($c['staffId'], $c['postId'], 'budget.prepare')
			&& !$c['perms']->can($c['staffId'], $c['postId'], 'budget.edit_own')
				&& !$c['perms']->can($c['staffId'], $c['postId'], 'budget.edit_submitted')) {
			return redirect()->to(base_url('budget/prepare'))->with('error', 'You cannot open this budget.');
		}
		$isFinanceAdjust = BudgetWorkflowService::isFinanceAdjustment($status, $c['perms'], $c['staffId'], $c['postId']);
		$setup = [];
		if (!empty($budget['notes'])) {
			$decoded = json_decode($budget['notes'], true);
			if (is_array($decoded)) {
				$setup = $decoded;
			}
		}
		$branchRow = $db->table('branches')->where('id', (int) $budget['branch_id'])->get(1)->getRowArray();
		$budgetSchoolId = (int) ($branchRow['school_id'] ?? $c['schoolId']);

		// First DoF finance-adjust open: restore full lines, clear false Excel amounts, keep School Fees only
		$forceEmpty = (int) ($this->request->getGet('reset_empty') ?? 0) === 1;
		if ($canEdit && $isFinanceAdjust && ($forceEmpty || empty($setup['amounts_cleared_for_dof_at']))) {
			(new BudgetEmptyAmountsService())->resetEmptyExceptSchoolFees($id, $budgetSchoolId, (int) $c['staffId']);
			$budget = $db->table('budgets b')
				->select('b.*, bp.title as period_title, bp.start_date, bp.end_date')
				->join('budget_periods bp', 'bp.id = b.budget_period_id', 'left')
				->where('b.id', $id)
				->get(1)->getRowArray() ?: $budget;
			$setup = [];
			if (!empty($budget['notes'])) {
				$decoded = json_decode($budget['notes'], true);
				if (is_array($decoded)) {
					$setup = $decoded;
				}
			}
		}

		$lines = $db->query("
			SELECT bl.*, btl.line_key
			FROM budget_lines bl
			LEFT JOIN budget_template_lines btl ON btl.id = bl.template_line_id
			WHERE bl.budget_id = ?
			ORDER BY bl.sort_order
		", [$id])->getResultArray();
		foreach ($lines as &$ln) {
			$ln['months'] = $calc->decodeMonthlyJson($ln['monthly_json'] ?? null);
		}
		unset($ln);
		$data = $this->data;
		$data['title'] = 'Budget Workspace';
		$data['page'] = 'budget_prepare';
		$data['budget'] = $budget;
		$data['lines'] = $lines;
		$data['sections'] = $calc->groupLinesBySection($lines);
		$data['branch_label'] = $branchRow
			? $c['branchCtx']->displaySchoolBranchLabel($budgetSchoolId, $branchRow, false)
			: session('soma_school');
		$data['setup'] = $setup;
		$data['units'] = ['Student', 'Meal', 'Trip', 'Litre', 'Month', 'Item', 'Employee', 'Vehicle', 'Other'];
		$data['budget_branch_fill'] = (new SchoolHierarchyService())->isBudgetBranchFillSchool($c['schoolId']);
		$data['can_edit'] = $canEdit;
		$data['is_finance_adjust'] = $isFinanceAdjust;
		$data['can_submit'] = $canEdit && in_array($status, BudgetWorkflowService::preparerEditableStatuses(), true)
			&& $c['perms']->can($c['staffId'], $c['postId'], 'budget.submit');
		$data['can_add_lines'] = $c['perms']->can($c['staffId'], $c['postId'], 'budget.edit_submitted');
		$sectionKeys = array_keys($data['sections'] ?? []);
		$data['section_options'] = $sectionKeys ?: ['INCOME', 'OPERATING EXPENSES', 'ADMINISTRATIVE COSTS', 'FINANCE COSTS'];
		$data['content'] = view('pages/budget/edit_budget', $data);
		return view('main', $data);
	}

	protected function loadEditableBudgetForSave(array $c, int $budgetId): ?array
	{
		$db = \Config\Database::connect();
		$allowedBranchIds = array_map('intval', array_column(
			$c['branchCtx']->accessibleBranchIds($c['staffId'], $c['postId'], $c['schoolId']),
			'id'
		));
		if (!$allowedBranchIds) {
			$allowedBranchIds = [(int) $c['branchId']];
		}
		$budget = $db->table('budgets')->where('id', $budgetId)->whereIn('branch_id', $allowedBranchIds)->get(1)->getRowArray();
		return $budget ?: null;
	}

	public function save_budget_setup()
	{
		$this->bootBudget();
		$c = $this->ctx();
		$budgetId = (int) $this->request->getPost('budget_id');
		$db = \Config\Database::connect();
		$budget = $this->loadEditableBudgetForSave($c, $budgetId);
		if (!$budget) {
			return $this->response->setJSON(['error' => 'Budget not found.']);
		}
		$status = (string) ($budget['status'] ?? '');
		if (!BudgetWorkflowService::canEditBudgetAmounts($status, $c['perms'], $c['staffId'], $c['postId'])) {
			return $this->response->setJSON(['error' => 'Budget is not editable. Only Director of Finance can edit submitted or approved budgets.']);
		}
		$setup = [
			'academic_year' => trim((string) $this->request->getPost('academic_year')),
			'planning_type' => 'three_term_annual',
			'enrollment' => (int) $this->request->getPost('enrollment'),
			'opening_cash' => (float) $this->request->getPost('opening_cash'),
			'planning_notes' => trim((string) $this->request->getPost('planning_notes')),
			'prepared_by' => session('soma_name'),
			'updated_at' => date('Y-m-d H:i:s'),
		];
		$title = trim((string) $this->request->getPost('title'));
		$update = [
			'notes' => json_encode($setup),
			'updated_at' => date('Y-m-d H:i:s'),
			'updated_by' => $c['staffId'],
		];
		if ($title !== '') {
			$update['title'] = $title;
		}
		$db->table('budgets')->where('id', $budgetId)->update($update);
		if (BudgetWorkflowService::isFinanceAdjustment($status, $c['perms'], $c['staffId'], $c['postId'])) {
			(new FinancialAuditService())->log(
				'budget',
				$budgetId,
				'finance_adjust_setup',
				$c['staffId'],
				['status' => $status, 'title' => $budget['title'] ?? ''],
				['status' => $status, 'title' => $title !== '' ? $title : ($budget['title'] ?? '')],
				$budget['organization_id'] ?? null,
				$budget['branch_id'] ?? null
			);
		}
		return $this->response->setJSON(['success' => 'Setup saved.', 'setup' => $setup]);
	}

	public function save_budget_lines()
	{
		$this->bootBudget();
		$c = $this->ctx();
		$budgetId = (int) $this->request->getPost('budget_id');
		$db = \Config\Database::connect();
		$this->ensureBudgetLineColumns($db);
		$budget = $this->loadEditableBudgetForSave($c, $budgetId);
		if (!$budget) {
			return $this->response->setJSON(['error' => 'Budget not found.']);
		}
		$status = (string) ($budget['status'] ?? '');
		if (!BudgetWorkflowService::canEditBudgetAmounts($status, $c['perms'], $c['staffId'], $c['postId'])) {
			return $this->response->setJSON(['error' => 'Budget is not editable. Only Director of Finance can edit submitted or approved budgets.']);
		}
		$lines = $this->request->getPost('lines') ?: [];
		$calc = new BudgetCalculationService();
		foreach ($lines as $lid => $row) {
			if (!is_array($row)) continue;
			$monthlyJson = null;
			if (!empty($row['months']) && is_array($row['months'])) {
				$monthlyJson = $calc->encodeMonthlyJson($row['months']);
			}
			$update = [
				'quantity' => $row['quantity'] ?? null,
				'unit' => $row['unit'] ?? null,
				'unit_cost' => $row['unit_cost'] ?? null,
				'frequency' => $row['frequency'] ?? 1,
				'term_1_amount' => (float) ($row['term_1_amount'] ?? 0),
				'term_2_amount' => (float) ($row['term_2_amount'] ?? 0),
				'term_3_amount' => (float) ($row['term_3_amount'] ?? 0),
				'calculation_mode' => $row['calculation_mode'] ?? 'term_sum',
				'assumptions' => $row['assumptions'] ?? null,
				'monthly_json' => $monthlyJson,
			];
			if (($update['calculation_mode'] ?? '') === 'term_sum') {
				$update['user_amount'] = $update['term_1_amount'] + $update['term_2_amount'] + $update['term_3_amount'];
			} else {
				$update['user_amount'] = (float) ($row['user_amount'] ?? 0);
			}
			$line = $db->table('budget_lines')->where('id', (int) $lid)->where('budget_id', $budgetId)->get(1)->getRowArray();
			if ($line && (int) $line['is_editable'] === 1) {
				$update['annual_amount'] = $calc->lineAnnualAmount(array_merge($line, $update));
				$db->table('budget_lines')->where('id', (int) $lid)->update($update);
			}
		}
		$totals = $calc->recalculateBudgetTotals($budgetId);
		$db->table('budgets')->where('id', $budgetId)->update([
			'updated_at' => date('Y-m-d H:i:s'),
			'updated_by' => $c['staffId'],
		]);
		$isAdjust = BudgetWorkflowService::isFinanceAdjustment($status, $c['perms'], $c['staffId'], $c['postId']);
		if ($isAdjust) {
			(new FinancialAuditService())->log(
				'budget',
				$budgetId,
				'finance_adjust_lines',
				$c['staffId'],
				['status' => $status],
				['status' => $status, 'totals' => $totals],
				$budget['organization_id'] ?? null,
				$budget['branch_id'] ?? null
			);
		}
		$msg = $isAdjust
			? 'Budget adjusted by Director of Finance. Approval status unchanged — verification chain still applies for new submissions.'
			: 'Budget saved.';
		return $this->response->setJSON(['success' => $msg, 'totals' => $totals]);
	}

	public function submit_budget()
	{
		$this->bootBudget();
		$this->denyPerm('budget.submit');
		$c = $this->ctx();
		$id = (int) $this->request->getPost('budget_id');
		$branchIds = array_column($c['branchCtx']->accessibleBranchIds($c['staffId'], $c['postId'], $c['schoolId']), 'id');
		$wf = new BudgetWorkflowService();
		$result = $wf->transition($id, 'submit', $c['staffId'], $c['postId'], $this->request->getPost('comment'), [
			'perms' => $c['perms'],
			'allowed_branch_ids' => $branchIds,
		]);
		if (!empty($result['success']) || !empty($result['status'])) {
			$this->notifyBudgetWorkflow($id, 'submit', $c);
		}
		return $this->response->setJSON($result);
	}

	public function budget_review()
	{
		return redirect()->to(base_url('budget/prepare?tab=review'));
	}

	public function budget_action()
	{
		$this->bootBudget();
		$c = $this->ctx();
		$action = trim((string) $this->request->getPost('action'));
		$id = (int) $this->request->getPost('budget_id');
		$need = BudgetWorkflowService::permissionForAction($action);
		if ($need) {
			$this->denyPerm($need);
		} else {
			return $this->response->setJSON(['error' => 'Unknown approval action.']);
		}
		$branchIds = array_column($c['branchCtx']->accessibleBranchIds($c['staffId'], $c['postId'], $c['schoolId']), 'id');
		$wf = new BudgetWorkflowService();
		$result = $wf->transition($id, $action, $c['staffId'], $c['postId'], $this->request->getPost('comment'), [
			'perms' => $c['perms'],
			'allowed_branch_ids' => $branchIds,
		]);
		if (!empty($result['success']) || !empty($result['status'])) {
			$this->notifyBudgetWorkflow($id, $action, $c);
		}
		return $this->response->setJSON($result);
	}

	/**
	 * Director of Finance: add a budget section title and/or line row.
	 * Amounts are optional — saving with a single line is allowed.
	 */
	public function add_budget_line()
	{
		$this->bootBudget();
		$c = $this->ctx();
		if (!$c['perms']->can($c['staffId'], $c['postId'], 'budget.edit_submitted')) {
			return $this->response->setJSON(['error' => 'Only Director of Finance can add budget titles and rows.']);
		}
		$budgetId = (int) $this->request->getPost('budget_id');
		$section = trim((string) $this->request->getPost('section_label'));
		$category = trim((string) $this->request->getPost('category'));
		$assumptions = trim((string) $this->request->getPost('assumptions'));
		$mode = trim((string) $this->request->getPost('mode')) ?: 'line'; // line | section

		if ($section === '') {
			return $this->response->setJSON(['error' => 'Section title is required.']);
		}
		$section = strtoupper($section);
		if ($mode === 'line' && $category === '') {
			return $this->response->setJSON(['error' => 'Budget line title is required.']);
		}
		if ($mode === 'section' && $category === '') {
			$category = 'Total ' . ucwords(strtolower($section));
		}

		$budget = $this->loadEditableBudgetForSave($c, $budgetId);
		if (!$budget) {
			return $this->response->setJSON(['error' => 'Budget not found.']);
		}
		$status = (string) ($budget['status'] ?? '');
		if (!BudgetWorkflowService::canEditBudgetAmounts($status, $c['perms'], $c['staffId'], $c['postId'])
			&& !in_array($status, BudgetWorkflowService::preparerEditableStatuses(), true)) {
			return $this->response->setJSON(['error' => 'This budget cannot be edited.']);
		}
		// DoF may add lines even on DRAFT (managing master catalog)
		if (!$c['perms']->can($c['staffId'], $c['postId'], 'budget.edit_submitted')) {
			return $this->response->setJSON(['error' => 'Not allowed.']);
		}

		$db = \Config\Database::connect();
		$this->ensureBudgetLineColumns($db);
		$maxSort = (int) ($db->table('budget_lines')->selectMax('sort_order')->where('budget_id', $budgetId)->get()->getRowArray()['sort_order'] ?? 0);

		$created = [];
		// Ensure section exists: if new section, insert a total row placeholder then the editable line
		$secExists = $db->table('budget_lines')->where('budget_id', $budgetId)->where('section_label', $section)->countAllResults() > 0;

		if ($mode === 'section' || !$secExists) {
			$totalLabel = 'Total ' . ucwords(strtolower($section));
			$hasTotal = $db->table('budget_lines')->where('budget_id', $budgetId)
				->where('section_label', $section)->where('is_total_row', 1)->countAllResults() > 0;
			if (!$hasTotal) {
				$maxSort++;
				$db->table('budget_lines')->insert([
					'budget_id' => $budgetId,
					'section_label' => $section,
					'category' => $totalLabel,
					'is_total_row' => 1,
					'is_editable' => 0,
					'calculation_mode' => 'term_sum',
					'sort_order' => $maxSort + 100,
					'term_1_amount' => 0,
					'term_2_amount' => 0,
					'term_3_amount' => 0,
					'annual_amount' => 0,
					'user_amount' => 0,
				]);
				$created[] = ['id' => (int) $db->insertID(), 'category' => $totalLabel, 'is_total_row' => 1];
			}
		}

		if ($mode === 'line' || ($mode === 'section' && trim((string) $this->request->getPost('category')) !== '')) {
			$dup = $db->table('budget_lines')->where('budget_id', $budgetId)
				->where('section_label', $section)->where('category', $category)->where('is_total_row', 0)->countAllResults();
			if ($dup > 0) {
				return $this->response->setJSON(['error' => 'That budget line already exists in this section.']);
			}
			// Insert before section total
			$totalRow = $db->table('budget_lines')->where('budget_id', $budgetId)
				->where('section_label', $section)->where('is_total_row', 1)->get(1)->getRowArray();
			$sort = $totalRow ? max(1, (int) $totalRow['sort_order'] - 1) : ($maxSort + 1);
			$db->table('budget_lines')->insert([
				'budget_id' => $budgetId,
				'section_label' => $section,
				'category' => $category,
				'is_total_row' => 0,
				'is_editable' => 1,
				'calculation_mode' => 'term_sum',
				'sort_order' => $sort,
				'assumptions' => $assumptions !== '' ? $assumptions : null,
				'term_1_amount' => 0,
				'term_2_amount' => 0,
				'term_3_amount' => 0,
				'annual_amount' => 0,
				'user_amount' => 0,
			]);
			$lineId = (int) $db->insertID();
			$created[] = ['id' => $lineId, 'category' => $category, 'is_total_row' => 0, 'section_label' => $section];

			// Master catalog: push new line structure into child DRAFT budgets
			$this->propagateLineToChildDrafts($c['schoolId'], $section, $category, $assumptions);
		}

		(new FinancialAuditService())->log(
			'budget',
			$budgetId,
			'add_budget_line',
			$c['staffId'],
			['status' => $status],
			['section' => $section, 'category' => $category, 'mode' => $mode],
			$budget['organization_id'] ?? null,
			$budget['branch_id'] ?? null
		);

		return $this->response->setJSON([
			'success' => ($mode === 'section' ? 'Section title added.' : 'Budget line added.') . ' Synced to child schools.',
			'created' => $created,
			'reload' => true,
		]);
	}

	/**
	 * Delete a budget line on master and remove matching lines from all child-school budgets.
	 */
	public function delete_budget_line()
	{
		$this->bootBudget();
		$c = $this->ctx();
		if (!$c['perms']->can($c['staffId'], $c['postId'], 'budget.edit_submitted')) {
			return $this->response->setJSON(['error' => 'Only Director of Finance can delete budget lines.']);
		}
		$hierarchy = new SchoolHierarchyService();
		if (!$hierarchy->canManageBudgetLineStructure($c['schoolId'])) {
			return $this->response->setJSON(['error' => 'Only the master school can delete shared budget lines.']);
		}
		$budgetId = (int) $this->request->getPost('budget_id');
		$lineId = (int) $this->request->getPost('line_id');
		$budget = $this->loadEditableBudgetForSave($c, $budgetId);
		if (!$budget) {
			return $this->response->setJSON(['error' => 'Budget not found.']);
		}
		$status = (string) ($budget['status'] ?? '');
		if (!BudgetWorkflowService::canEditBudgetAmounts($status, $c['perms'], $c['staffId'], $c['postId'])) {
			return $this->response->setJSON(['error' => 'Budget is not editable.']);
		}
		$db = \Config\Database::connect();
		$line = $db->table('budget_lines')->where('id', $lineId)->where('budget_id', $budgetId)->get(1)->getRowArray();
		if (!$line || (int) ($line['is_total_row'] ?? 0) === 1) {
			return $this->response->setJSON(['error' => 'Budget line not found.']);
		}
		$category = (string) ($line['category'] ?? '');
		$section = (string) ($line['section_label'] ?? '');
		if (stripos($category, 'school fee') !== false) {
			return $this->response->setJSON(['error' => 'School Fees cannot be deleted — it is auto-filled from fees management.']);
		}

		$db->table('budget_lines')->where('id', $lineId)->delete();
		$childDeleted = $this->deleteLineFromChildBudgets($c['schoolId'], $section, $category);
		(new BudgetCalculationService())->recalculateBudgetTotals($budgetId);

		(new FinancialAuditService())->log(
			'budget',
			$budgetId,
			'delete_budget_line',
			$c['staffId'],
			['section' => $section, 'category' => $category],
			['child_deleted' => $childDeleted],
			$budget['organization_id'] ?? null,
			$budget['branch_id'] ?? null
		);

		return $this->response->setJSON([
			'success' => sprintf(
				'Deleted “%s”. Removed from %d child-school budget line(s).',
				$category,
				$childDeleted
			),
			'child_deleted' => $childDeleted,
		]);
	}

	/**
	 * Move a budget line up/down within its section (smart reorder) and sync order to child budgets.
	 */
	public function move_budget_line()
	{
		$this->bootBudget();
		$c = $this->ctx();
		if (!$c['perms']->can($c['staffId'], $c['postId'], 'budget.edit_submitted')) {
			return $this->response->setJSON(['error' => 'Only Director of Finance can reorder budget lines.']);
		}
		$hierarchy = new SchoolHierarchyService();
		if (!$hierarchy->canManageBudgetLineStructure($c['schoolId'])) {
			return $this->response->setJSON(['error' => 'Only the master school can reorder shared budget lines.']);
		}
		$budgetId = (int) $this->request->getPost('budget_id');
		$lineId = (int) $this->request->getPost('line_id');
		$direction = strtolower(trim((string) $this->request->getPost('direction')));
		if (!in_array($direction, ['up', 'down'], true)) {
			return $this->response->setJSON(['error' => 'Invalid move direction.']);
		}
		$budget = $this->loadEditableBudgetForSave($c, $budgetId);
		if (!$budget) {
			return $this->response->setJSON(['error' => 'Budget not found.']);
		}
		$status = (string) ($budget['status'] ?? '');
		if (!BudgetWorkflowService::canEditBudgetAmounts($status, $c['perms'], $c['staffId'], $c['postId'])) {
			return $this->response->setJSON(['error' => 'Budget is not editable.']);
		}
		$db = \Config\Database::connect();
		$line = $db->table('budget_lines')->where('id', $lineId)->where('budget_id', $budgetId)->get(1)->getRowArray();
		if (!$line || (int) ($line['is_total_row'] ?? 0) === 1) {
			return $this->response->setJSON(['error' => 'Budget line not found.']);
		}
		$section = (string) ($line['section_label'] ?? '');
		$siblings = $db->table('budget_lines')
			->where('budget_id', $budgetId)
			->where('section_label', $section)
			->where('is_total_row', 0)
			->orderBy('sort_order', 'ASC')
			->orderBy('id', 'ASC')
			->get()->getResultArray();
		$idx = -1;
		foreach ($siblings as $i => $sib) {
			if ((int) $sib['id'] === $lineId) {
				$idx = $i;
				break;
			}
		}
		if ($idx < 0) {
			return $this->response->setJSON(['error' => 'Line not in section.']);
		}
		$swapIdx = $direction === 'up' ? $idx - 1 : $idx + 1;
		if ($swapIdx < 0 || $swapIdx >= count($siblings)) {
			return $this->response->setJSON(['error' => $direction === 'up' ? 'Already at the top of this section.' : 'Already at the bottom of this section.']);
		}
		$a = $siblings[$idx];
		$b = $siblings[$swapIdx];
		$sortA = (int) ($a['sort_order'] ?? 0);
		$sortB = (int) ($b['sort_order'] ?? 0);
		if ($sortA === $sortB) {
			$sortB = $sortA + ($direction === 'up' ? -1 : 1);
		}
		$db->table('budget_lines')->where('id', (int) $a['id'])->update(['sort_order' => $sortB]);
		$db->table('budget_lines')->where('id', (int) $b['id'])->update(['sort_order' => $sortA]);

		// Re-number section for clean order, then push to children
		$ordered = $db->table('budget_lines')
			->where('budget_id', $budgetId)
			->where('section_label', $section)
			->where('is_total_row', 0)
			->orderBy('sort_order', 'ASC')
			->orderBy('id', 'ASC')
			->get()->getResultArray();
		$base = 10;
		$orderMap = [];
		foreach ($ordered as $i => $row) {
			$sort = $base + ($i * 10);
			$db->table('budget_lines')->where('id', (int) $row['id'])->update(['sort_order' => $sort]);
			$orderMap[(string) $row['category']] = $sort;
		}
		$this->propagateLineOrderToChildBudgets($c['schoolId'], $section, $orderMap);

		return $this->response->setJSON([
			'success' => 'Line moved ' . $direction . '.',
			'reload' => false,
			'section' => $section,
		]);
	}

	/**
	 * Persist a full section order (drag-and-drop) and sync to child schools.
	 */
	public function reorder_budget_lines()
	{
		$this->bootBudget();
		$c = $this->ctx();
		if (!$c['perms']->can($c['staffId'], $c['postId'], 'budget.edit_submitted')) {
			return $this->response->setJSON(['error' => 'Only Director of Finance can reorder budget lines.']);
		}
		$hierarchy = new SchoolHierarchyService();
		if (!$hierarchy->canManageBudgetLineStructure($c['schoolId'])) {
			return $this->response->setJSON(['error' => 'Only the master school can reorder shared budget lines.']);
		}
		$budgetId = (int) $this->request->getPost('budget_id');
		$section = trim((string) $this->request->getPost('section'));
		$lineIds = $this->request->getPost('line_ids');
		if (!is_array($lineIds)) {
			$raw = trim((string) $this->request->getPost('line_ids'));
			$lineIds = $raw !== '' ? preg_split('/\s*,\s*/', $raw) : [];
		}
		$lineIds = array_values(array_filter(array_map('intval', (array) $lineIds)));
		if ($section === '' || !$lineIds) {
			return $this->response->setJSON(['error' => 'Section and line order are required.']);
		}
		$budget = $this->loadEditableBudgetForSave($c, $budgetId);
		if (!$budget) {
			return $this->response->setJSON(['error' => 'Budget not found.']);
		}
		$status = (string) ($budget['status'] ?? '');
		if (!BudgetWorkflowService::canEditBudgetAmounts($status, $c['perms'], $c['staffId'], $c['postId'])) {
			return $this->response->setJSON(['error' => 'Budget is not editable.']);
		}
		$db = \Config\Database::connect();
		$orderMap = [];
		$base = 10;
		foreach ($lineIds as $i => $lid) {
			$row = $db->table('budget_lines')
				->where('id', $lid)
				->where('budget_id', $budgetId)
				->where('section_label', $section)
				->where('is_total_row', 0)
				->get(1)->getRowArray();
			if (!$row) {
				continue;
			}
			$sort = $base + ($i * 10);
			$db->table('budget_lines')->where('id', $lid)->update(['sort_order' => $sort]);
			$orderMap[(string) $row['category']] = $sort;
		}
		if (!$orderMap) {
			return $this->response->setJSON(['error' => 'No matching lines to reorder.']);
		}
		$this->propagateLineOrderToChildBudgets($c['schoolId'], $section, $orderMap);
		return $this->response->setJSON([
			'success' => 'Order updated and synced to child schools.',
			'section' => $section,
			'count' => count($orderMap),
		]);
	}

	/** Remove matching lines from every child-school budget (all statuses). */
	protected function deleteLineFromChildBudgets(int $schoolId, string $section, string $category): int
	{
		$hierarchy = new SchoolHierarchyService();
		if ($hierarchy->isChildSchool($schoolId)) {
			return 0;
		}
		$db = \Config\Database::connect();
		$childIds = array_values(array_filter(array_map(static function ($r) {
			return (int) ($r['id'] ?? 0);
		}, $hierarchy->childSchools($schoolId))));
		if (!$childIds) {
			return 0;
		}
		$branchIds = [];
		foreach ($db->table('branches')->whereIn('school_id', $childIds)->where('status', 1)->get()->getResultArray() as $br) {
			$branchIds[] = (int) $br['id'];
		}
		if (!$branchIds) {
			return 0;
		}
		$budgetIds = array_map('intval', array_column(
			$db->table('budgets')->select('id')->whereIn('branch_id', $branchIds)->get()->getResultArray(),
			'id'
		));
		if (!$budgetIds) {
			return 0;
		}
		$deleted = 0;
		foreach ($budgetIds as $bid) {
			$q = $db->table('budget_lines')
				->where('budget_id', $bid)
				->where('section_label', $section)
				->where('category', $category)
				->where('is_total_row', 0);
			$count = $q->countAllResults(false);
			if ($count > 0) {
				$db->table('budget_lines')
					->where('budget_id', $bid)
					->where('section_label', $section)
					->where('category', $category)
					->where('is_total_row', 0)
					->delete();
				$deleted += $count;
				(new BudgetCalculationService())->recalculateBudgetTotals($bid);
			}
		}
		return $deleted;
	}

	/**
	 * @param array<string,int> $orderMap category => sort_order
	 */
	protected function propagateLineOrderToChildBudgets(int $schoolId, string $section, array $orderMap): void
	{
		$hierarchy = new SchoolHierarchyService();
		if ($hierarchy->isChildSchool($schoolId) || !$orderMap) {
			return;
		}
		$db = \Config\Database::connect();
		$childIds = array_values(array_filter(array_map(static function ($r) {
			return (int) ($r['id'] ?? 0);
		}, $hierarchy->childSchools($schoolId))));
		if (!$childIds) {
			return;
		}
		$branchIds = [];
		foreach ($db->table('branches')->whereIn('school_id', $childIds)->where('status', 1)->get()->getResultArray() as $br) {
			$branchIds[] = (int) $br['id'];
		}
		if (!$branchIds) {
			return;
		}
		foreach ($db->table('budgets')->select('id')->whereIn('branch_id', $branchIds)->get()->getResultArray() as $b) {
			$bid = (int) $b['id'];
			foreach ($orderMap as $cat => $sort) {
				$db->table('budget_lines')
					->where('budget_id', $bid)
					->where('section_label', $section)
					->where('category', $cat)
					->where('is_total_row', 0)
					->update(['sort_order' => (int) $sort]);
			}
		}
	}

	/** Copy a new master line into all child-school budgets (structure only). */
	protected function propagateLineToChildDrafts(int $schoolId, string $section, string $category, string $assumptions = ''): void
	{
		$hierarchy = new SchoolHierarchyService();
		if ($hierarchy->isChildSchool($schoolId)) {
			return;
		}
		$db = \Config\Database::connect();
		$childRows = $hierarchy->childSchools($schoolId);
		$childIds = array_map(static function ($r) {
			return (int) ($r['id'] ?? 0);
		}, $childRows);
		$childIds = array_values(array_filter($childIds));
		if (!$childIds) {
			return;
		}
		$branchIds = [];
		foreach ($db->table('branches')->whereIn('school_id', $childIds)->where('status', 1)->get()->getResultArray() as $br) {
			$branchIds[] = (int) $br['id'];
		}
		if (!$branchIds) {
			return;
		}
		// All child budgets (draft + submitted/approved) get the new structure line
		$childBudgets = $db->table('budgets')->whereIn('branch_id', $branchIds)->get()->getResultArray();
		foreach ($childBudgets as $b) {
			$bid = (int) $b['id'];
			$exists = $db->table('budget_lines')->where('budget_id', $bid)
				->where('section_label', $section)->where('category', $category)->where('is_total_row', 0)->countAllResults();
			if ($exists) {
				continue;
			}
			$secExists = $db->table('budget_lines')->where('budget_id', $bid)->where('section_label', $section)->countAllResults() > 0;
			if (!$secExists) {
				$db->table('budget_lines')->insert([
					'budget_id' => $bid,
					'section_label' => $section,
					'category' => 'Total ' . ucwords(strtolower($section)),
					'is_total_row' => 1,
					'is_editable' => 0,
					'calculation_mode' => 'term_sum',
					'sort_order' => 9000,
					'term_1_amount' => 0, 'term_2_amount' => 0, 'term_3_amount' => 0,
					'annual_amount' => 0, 'user_amount' => 0,
				]);
			}
			$totalRow = $db->table('budget_lines')->where('budget_id', $bid)
				->where('section_label', $section)->where('is_total_row', 1)->get(1)->getRowArray();
			$sort = $totalRow ? max(1, (int) $totalRow['sort_order'] - 1) : 100;
			$db->table('budget_lines')->insert([
				'budget_id' => $bid,
				'section_label' => $section,
				'category' => $category,
				'is_total_row' => 0,
				'is_editable' => 1,
				'calculation_mode' => 'term_sum',
				'sort_order' => $sort,
				'assumptions' => $assumptions !== '' ? $assumptions : null,
				'term_1_amount' => 0, 'term_2_amount' => 0, 'term_3_amount' => 0,
				'annual_amount' => 0, 'user_amount' => 0,
			]);
		}
	}

	/**
	 * In-app + SMS + email for budget submit / approval steps.
	 */
	protected function notifyBudgetWorkflow(int $budgetId, string $action, array $c): void
	{
		$db = \Config\Database::connect();
		$budget = $db->table('budgets b')
			->select('b.*, br.name as branch_name, br.school_id as branch_school_id')
			->join('branches br', 'br.id = b.branch_id', 'left')
			->where('b.id', $budgetId)->get(1)->getRowArray();
		if (!$budget) {
			return;
		}
		$notify = new BudgetNotificationService();
		$schoolId = (int) ($budget['branch_school_id'] ?? $c['schoolId']);
		$title = (string) ($budget['title'] ?? 'Budget');
		$branchLabel = (string) ($budget['branch_name'] ?? 'School');
		$status = (string) ($budget['status'] ?? '');
		$reviewUrl = base_url('budget/prepare?tab=review');
		$prepareUrl = base_url('budget/prepare');

		$chain = BudgetWorkflowService::approvalChainLabels();
		$actionLabel = $chain[$action] ?? $action;

		if ($action === 'submit') {
			$msgTitle = 'Budget submitted for approval';
			$msgBody = $branchLabel . ' — "' . $title . '" was submitted. Verification required: Procurement → Budget Manager → Director of Finance.';
			// All 3 approvers
			foreach ($notify->approverContacts($schoolId) as $staff) {
				$notify->notifyStaff((int) $staff['id'], $msgTitle, $msgBody, $reviewUrl, (int) $budget['branch_id']);
				$this->sendBudgetContactAlert($staff, $msgTitle, $msgBody);
			}
			// Preparer (accountant / head who prepared)
			$preparer = $notify->staffById((int) ($budget['prepared_by'] ?? 0));
			if ($preparer) {
				$pTitle = 'Your budget was submitted';
				$pBody = '"' . $title . '" is now awaiting Procurement, Budget Manager, and Director of Finance approval. It stays in review until all three approve.';
				$notify->notifyStaff((int) $preparer['id'], $pTitle, $pBody, $prepareUrl, (int) $budget['branch_id']);
				$this->sendBudgetContactAlert($preparer, $pTitle, $pBody);
			}
			return;
		}

		// Step / final outcomes → preparer
		$preparer = $notify->staffById((int) ($budget['prepared_by'] ?? 0));
		if (!$preparer) {
			return;
		}
		if ($action === 'approve' && $status === 'APPROVED') {
			$pTitle = 'Budget approved';
			$pBody = '"' . $title . '" (' . $branchLabel . ') is fully approved after Procurement, Budget Manager, and Director of Finance.';
		} elseif ($action === 'return') {
			$pTitle = 'Budget returned';
			$pBody = '"' . $title . '" was returned for changes. Status: ' . $status . '.';
		} elseif ($action === 'reject') {
			$pTitle = 'Budget rejected';
			$pBody = '"' . $title . '" was rejected. Status: ' . $status . '.';
		} else {
			$pTitle = 'Budget approval update';
			$pBody = '"' . $title . '" — step completed: ' . $actionLabel . '. Current status: ' . $status . '.';
		}
		$notify->notifyStaff((int) $preparer['id'], $pTitle, $pBody, $prepareUrl, (int) $budget['branch_id']);
		$this->sendBudgetContactAlert($preparer, $pTitle, $pBody);

		// After procurement / budget manager, nudge next role
		$nextPost = null;
		if ($action === 'procurement_review') {
			$nextPost = 19;
		} elseif ($action === 'budget_review') {
			$nextPost = 24;
		}
		if ($nextPost) {
			$nTitle = 'Budget awaiting your approval';
			$nBody = $branchLabel . ' — "' . $title . '" needs your review. Status: ' . $status . '.';
			foreach ($notify->activeStaffByPost($nextPost, $schoolId) as $staff) {
				$notify->notifyStaff((int) $staff['id'], $nTitle, $nBody, $reviewUrl, (int) $budget['branch_id']);
				$this->sendBudgetContactAlert($staff, $nTitle, $nBody);
			}
		}
	}

	protected function sendBudgetContactAlert(array $staff, string $subject, string $body): void
	{
		$name = (new BudgetNotificationService())->displayName($staff);
		$phone = trim((string) ($staff['phone'] ?? ''));
		$email = trim((string) ($staff['email'] ?? ''));
		$sms = 'SmartSMS Budget: ' . $subject . ' — ' . $body;
		if (strlen($sms) > 300) {
			$sms = substr($sms, 0, 297) . '...';
		}
		if ($phone !== '') {
			$result = null;
			try {
				$this->sendSMS($phone, $sms, $result);
			} catch (\Throwable $e) {
				// non-fatal
			}
		}
		if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$html = '<p>Dear ' . htmlspecialchars($name) . ',</p><p>' . nl2br(htmlspecialchars($body)) . '</p><p>— XanderTech SmartSMS</p>';
			try {
				$this->_send_email($email, $subject, $html);
			} catch (\Throwable $e) {
				// non-fatal
			}
		}
	}

	public function delete_budget()
	{
		$this->bootBudget();
		$c = $this->ctx();
		$id = (int) $this->request->getPost('budget_id');
		if ($id <= 0) {
			return $this->response->setJSON(['error' => 'Missing budget.']);
		}
		$branchIds = array_column($c['branchCtx']->accessibleBranchIds($c['staffId'], $c['postId'], $c['schoolId']), 'id');
		// Always allow acting on own primary school branch
		if ($c['branchId'] > 0 && !in_array((int) $c['branchId'], array_map('intval', $branchIds), true)) {
			$branchIds[] = (int) $c['branchId'];
		}
		$wf = new BudgetWorkflowService();
		$res = $wf->deleteBudget($id, $c['staffId'], $c['postId'], $branchIds, $c['perms']);
		if (!empty($res['error'])) {
			return $this->response->setJSON(['error' => $res['error']]);
		}
		return $this->response->setJSON(['success' => $res['message'] ?? 'Budget deleted.']);
	}

	public function approved_budgets()
	{
		return redirect()->to(base_url('budget/prepare?tab=approved'));
	}

	public function requests()
	{
		$this->denyMenuAny([
			'budget_cash_requests', 'budget_pending', 'budget_procurement',
			'budget_availability', 'budget_final_approval', 'budget_payments', 'budget_filing',
		]);
		$c = $this->ctx();
		$db = \Config\Database::connect();
		$data = $this->data;
		$tab = trim((string) $this->request->getGet('tab')) ?: 'all';
		$tabKeys = [
			'all' => ['budget_cash_requests'],
			'pending' => ['budget_pending', 'budget_procurement', 'budget_availability', 'budget_final_approval'],
			'payments' => ['budget_payments'],
			'receipts' => ['budget_filing'],
		];
		if (!isset($tabKeys[$tab]) || !budget_menu_any($tabKeys[$tab])) {
			$tab = 'all';
		}
		$data['tab'] = $tab;
		$data['title'] = 'Requests & Approvals';
		$data['page'] = 'budget_requests';
		$branchIds = array_column($c['branchCtx']->accessibleBranchIds($c['staffId'], $c['postId'], $c['schoolId']), 'id');
		$data['requests'] = [];
		if ($tab === 'all') {
			$q = $db->table('cash_requests cr')->select('cr.*, b.name as branch_name, b.organization_id')->join('branches b', 'b.id=cr.branch_id');
			$data['requests'] = $q->whereIn('cr.branch_id', $branchIds)->orderBy('cr.id', 'DESC')->get()->getResultArray();
		} elseif ($tab === 'pending') {
			$postId = $c['postId'];
			$statusMap = [
				20 => ['HEADTEACHER_APPROVED'], // Procurement after HM (full/medium)
				19 => ['PROCUREMENT_APPROVED'],
				21 => ['BUDGET_APPROVED'],
				22 => ['FINANCE_AUTHORIZED'],
				// DoF: full chain at BUDGET_APPROVED; short at HEADTEACHER_APPROVED; medium at PROCUREMENT_APPROVED
				24 => ['HEADTEACHER_APPROVED', 'PROCUREMENT_APPROVED', 'BUDGET_APPROVED', 'FINANCE_AUTHORIZED'],
				9 => ['FINANCE_AUTHORIZED', 'PAID'],
				1 => ['SUBMITTED'],
				18 => ['SUBMITTED'],
			];
			$statuses = $statusMap[$postId] ?? [];
			if ($statuses) {
				$rows = $db->table('cash_requests')->whereIn('status', $statuses)
					->whereIn('branch_id', $branchIds)->orderBy('id', 'DESC')->get()->getResultArray();
				// Filter DoF / Procurement so short-chain items only show to the right role
				if ((int) $postId === 24) {
					$rows = array_values(array_filter($rows, static function ($r) {
						$chain = strtolower((string) ($r['approval_chain'] ?? 'full'));
						$st = (string) ($r['status'] ?? '');
						if ($st === 'FINANCE_AUTHORIZED') {
							return true;
						}
						if ($st === 'HEADTEACHER_APPROVED') {
							return $chain === 'short';
						}
						if ($st === 'PROCUREMENT_APPROVED') {
							return $chain === 'medium';
						}
						if ($st === 'BUDGET_APPROVED') {
							return $chain === 'full' || $chain === '';
						}
						return false;
					}));
				} elseif ((int) $postId === 20) {
					$rows = array_values(array_filter($rows, static function ($r) {
						$chain = strtolower((string) ($r['approval_chain'] ?? 'full'));
						return $chain !== 'short';
					}));
				}
				$data['requests'] = $rows;
			}
		} elseif ($tab === 'payments') {
			$data['requests'] = $db->table('cash_requests')->whereIn('branch_id', $branchIds)
				->whereIn('status', ['FINANCE_AUTHORIZED', 'PARTIALLY_PAID'])->orderBy('id', 'DESC')->get()->getResultArray();
		} else {
			$data['requests'] = $db->table('cash_requests')->whereIn('branch_id', $branchIds)
				->where('status', 'PAID')->orderBy('id', 'DESC')->get()->getResultArray();
		}
		foreach ($data['requests'] as &$r) {
			if (!empty($r['branch_name'])) {
				continue;
			}
			$br = $db->table('branches')->where('id', (int) $r['branch_id'])->get(1)->getRowArray();
			if ($br) {
				$r['branch_name'] = $c['branchCtx']->displayBranchName($br, $c['isCentral']);
			}
		}
		unset($r);
		$data['content'] = view('pages/budget/requests', $data);
		return view('main', $data);
	}

	public function cash_requests()
	{
		return redirect()->to(base_url('budget/requests'));
	}

	public function cash_request_form($id = null)
	{
		$this->denyMenu('budget_cash_requests');
		$this->denyPerm('cash_request.create');
		$c = $this->ctx();
		$db = \Config\Database::connect();
		$data = $this->data;
		$data['title'] = $id ? 'Edit Cash Request' : 'New Cash Request';
		$data['page'] = 'budget_cash_requests';
		$data['request'] = $id ? $db->table('cash_requests')->where('id', (int)$id)->get(1)->getRowArray() : null;
		$data['budgets'] = $db->table('budgets')->where('branch_id', $c['branchId'])->where('status','APPROVED')->orderBy('id','DESC')->get()->getResultArray();
		$data['is_accountant'] = ((int) $c['postId'] === 9);
		$data['lines'] = $id ? $db->table('cash_request_lines')->where('cash_request_id', (int)$id)->get()->getResultArray() : [];
		$data['documents'] = $id ? $db->table('cash_request_documents')->where('cash_request_id', (int)$id)->orderBy('id')->get()->getResultArray() : [];
		CashRequestApprovalPolicy::ensureSchema();
		$data['approval_tiers'] = CashRequestApprovalPolicy::loadTiersForOrg((int) $c['orgId']);
		$data['content'] = view('pages/budget/cash_request_form', $data);
		return view('main', $data);
	}

	public function save_cash_request()
	{
		$this->bootBudget();
		$this->denyPerm('cash_request.create');
		$c = $this->ctx();
		CashRequestApprovalPolicy::ensureSchema();
		$db = \Config\Database::connect();
		$id = (int) $this->request->getPost('id');
		$submitNow = (bool) $this->request->getPost('submit_now');
		$budgetId = (int) $this->request->getPost('budget_id');

		if ($budgetId <= 0) {
			return $this->response->setJSON(['error' => 'Select an approved budget.']);
		}

		$budget = $db->table('budgets')->where('id', $budgetId)->where('branch_id', $c['branchId'])
			->where('status', 'APPROVED')->get(1)->getRowArray();
		if (!$budget) {
			return $this->response->setJSON(['error' => 'Budget must be APPROVED before raising cash requests.']);
		}

		// Multi-item payload (preferred). Fallback to legacy single-line fields.
		$lineIds = $this->request->getPost('item_budget_line_id');
		$lineDescs = $this->request->getPost('item_description');
		$lineAmounts = $this->request->getPost('item_amount');
		$items = [];
		if (is_array($lineIds) && count($lineIds) > 0) {
			foreach ($lineIds as $i => $lid) {
				$lid = (int) $lid;
				$amt = (float) ($lineAmounts[$i] ?? 0);
				$desc = trim((string) ($lineDescs[$i] ?? ''));
				if ($lid <= 0 && $amt <= 0 && $desc === '') {
					continue;
				}
				$items[] = [
					'budget_line_id' => $lid,
					'amount' => $amt,
					'description' => $desc,
				];
			}
		} else {
			$legacyLineId = (int) $this->request->getPost('budget_line_id');
			$legacyAmount = (float) ($this->request->getPost('line_amount') ?: $this->request->getPost('requested_amount'));
			$legacyDesc = trim((string) $this->request->getPost('line_description'));
			if ($legacyLineId > 0 || $legacyAmount > 0) {
				$items[] = [
					'budget_line_id' => $legacyLineId,
					'amount' => $legacyAmount,
					'description' => $legacyDesc,
				];
			}
		}

		if ($items === []) {
			return $this->response->setJSON(['error' => 'Add at least one request item with a budget line and amount.']);
		}

		$availSvc = new BudgetAvailabilityService();
		$byLine = [];
		$total = 0.0;
		$normalized = [];
		foreach ($items as $idx => $it) {
			$lid = (int) ($it['budget_line_id'] ?? 0);
			$amt = round((float) ($it['amount'] ?? 0), 2);
			if ($lid <= 0) {
				return $this->response->setJSON(['error' => 'Item #' . ($idx + 1) . ': select a budget line.']);
			}
			if ($amt <= 0) {
				return $this->response->setJSON(['error' => 'Item #' . ($idx + 1) . ': enter a valid amount.']);
			}
			$bLine = $db->table('budget_lines')->where('id', $lid)->where('budget_id', $budgetId)
				->where('is_total_row', 0)->get(1)->getRowArray();
			if (!$bLine) {
				return $this->response->setJSON(['error' => 'Item #' . ($idx + 1) . ': invalid budget line for this budget.']);
			}
			$desc = trim((string) ($it['description'] ?? ''));
			if ($desc === '') {
				$desc = (string) ($bLine['category'] ?? 'Cash request item');
			}
			$normalized[] = [
				'budget_line_id' => $lid,
				'description' => $desc,
				'amount' => $amt,
				'category' => (string) ($bLine['category'] ?? ''),
			];
			$byLine[$lid] = ($byLine[$lid] ?? 0) + $amt;
			$total += $amt;
		}

		if ($submitNow) {
			foreach ($byLine as $lid => $need) {
				$avail = $availSvc->lineAvailability((int) $lid);
				$left = $avail ? (float) $avail['available'] : 0.0;
				if ($need > $left) {
					$cat = '';
					foreach ($normalized as $n) {
						if ((int) $n['budget_line_id'] === (int) $lid) {
							$cat = $n['category'];
							break;
						}
					}
					return $this->response->setJSON([
						'error' => ($cat ?: 'A budget line') . ' exceeds remaining budget (need '
							. number_format($need, 0) . ' / available ' . number_format($left, 0) . ' RWF).',
					]);
				}
			}
		}

		$row = [
			'organization_id' => $c['orgId'],
			'branch_id' => $c['branchId'],
			'budget_id' => $budgetId,
			'budget_period_id' => (int) $this->request->getPost('budget_period_id') ?: null,
			'request_date' => $this->request->getPost('request_date') ?: date('Y-m-d'),
			'required_payment_date' => $this->request->getPost('required_payment_date'),
			'payee_name' => trim((string) $this->request->getPost('payee_name')),
			'payee_type' => $this->request->getPost('payee_type'),
			'purpose' => trim((string) $this->request->getPost('purpose')),
			'currency' => 'RWF',
			'requested_amount' => round($total, 2),
			'approval_chain' => CashRequestApprovalPolicy::resolveChain((int) $c['orgId'], $total)['chain'],
			'payment_method' => $this->request->getPost('payment_method'),
			'urgency' => $this->request->getPost('urgency') ?: 'normal',
			'internal_notes' => $this->request->getPost('internal_notes'),
			'updated_by' => $c['staffId'],
			'updated_at' => date('Y-m-d H:i:s'),
		];
		if ($row['payee_name'] === '' || $row['purpose'] === '') {
			return $this->response->setJSON(['error' => 'Payee and purpose are required.']);
		}

		$wf = new CashRequestWorkflowService();
		if ($id > 0) {
			$existing = $db->table('cash_requests')->where('id', $id)->where('branch_id', $c['branchId'])->get(1)->getRowArray();
			if (!$existing || !in_array($existing['status'], ['DRAFT', 'RETURNED_TO_ACCOUNTANT'], true)) {
				return $this->response->setJSON(['error' => 'This request cannot be edited.']);
			}
			$db->table('cash_requests')->where('id', $id)->update($row);
		} else {
			$row['request_no'] = $wf->nextRequestNo($c['branchId']);
			$row['status'] = 'DRAFT';
			$row['created_by'] = $c['staffId'];
			$row['created_at'] = date('Y-m-d H:i:s');
			$db->table('cash_requests')->insert($row);
			$id = (int) $db->insertID();
		}

		$db->table('cash_request_lines')->where('cash_request_id', $id)->delete();
		$sort = 0;
		foreach ($normalized as $n) {
			$db->table('cash_request_lines')->insert([
				'cash_request_id' => $id,
				'budget_line_id' => (int) $n['budget_line_id'],
				'description' => $n['description'],
				'amount' => $n['amount'],
				'sort_order' => $sort++,
			]);
		}

		$docSvc = new DocumentStorageService();
		$docType = trim((string) $this->request->getPost('doc_type')) ?: 'invoice';
		$newDocCount = 0;
		$files = $this->request->getFileMultiple('documents');
		if ($files) {
			foreach ($files as $file) {
				if (!$file || !$file->isValid() || $file->getError() === UPLOAD_ERR_NO_FILE) {
					continue;
				}
				$stored = $docSvc->storeUpload($file, 'budget/cash_requests');
				if (empty($stored['success'])) {
					continue;
				}
				$newDocCount++;
				$db->table('cash_request_documents')->insert([
					'cash_request_id' => $id,
					'doc_type' => $docType,
					'original_name' => $stored['original_name'],
					'stored_path' => $stored['stored_path'],
					'uploaded_by' => $c['staffId'],
					'created_at' => date('Y-m-d H:i:s'),
				]);
			}
		}
		$existingDocs = (int) $db->table('cash_request_documents')->where('cash_request_id', $id)->countAllResults();

		if ($submitNow) {
			if ($newDocCount + $existingDocs < 1) {
				return $this->response->setJSON([
					'error' => 'Attach at least one supporting document (invoice, quotation, or memo) before submitting.',
				]);
			}
			$res = $wf->transition($id, 'submit', $c['staffId'], $c['postId'], 'Submitted by accountant');
			if (empty($res['success'])) {
				return $this->response->setJSON($res);
			}
			return $this->response->setJSON([
				'success' => 'Cash request submitted for approval (' . count($normalized) . ' item' . (count($normalized) === 1 ? '' : 's') . ').',
				'id' => $id,
			]);
		}
		return $this->response->setJSON([
			'success' => 'Cash request saved as draft (' . count($normalized) . ' item' . (count($normalized) === 1 ? '' : 's') . ').',
			'id' => $id,
		]);
	}

	public function cash_request_view($id = null)
	{
		$this->denyMenu('budget_cash_requests');
		$c = $this->ctx();
		$id = (int) $id;
		$db = \Config\Database::connect();
		$req = $db->table('cash_requests')->where('id', $id)->get(1)->getRowArray();
		if (!$req) {
			return redirect()->to(base_url('budget/cash_requests'));
		}
		$data = $this->data;
		$data['title'] = $req['request_no'];
		$data['page'] = 'budget_cash_requests';
		$data['request'] = $req;
		$data['lines'] = $db->table('cash_request_lines crl')
			->select('crl.*, bl.category as budget_category, bl.section_label')
			->join('budget_lines bl', 'bl.id = crl.budget_line_id', 'left')
			->where('crl.cash_request_id', $id)
			->orderBy('crl.sort_order', 'ASC')
			->orderBy('crl.id', 'ASC')
			->get()->getResultArray();
		$data['actions'] = $db->table('cash_request_actions')->where('cash_request_id', $id)->orderBy('id')->get()->getResultArray();
		$data['payments'] = $db->table('cash_request_payments')->where('cash_request_id', $id)->get()->getResultArray();
		$data['documents'] = $db->table('cash_request_documents')->where('cash_request_id', $id)->orderBy('id')->get()->getResultArray();
		$data['availability'] = [];
		foreach ($data['lines'] as $ln) {
			if (!empty($ln['budget_line_id'])) {
				$data['availability'][$ln['budget_line_id']] = (new BudgetAvailabilityService())->lineAvailability($ln['budget_line_id']);
			}
		}
		CashRequestApprovalPolicy::ensureSchema();
		$chain = (string) ($req['approval_chain'] ?? 'full');
		if ($chain === '' || empty($req['approval_chain'])) {
			$resolved = CashRequestApprovalPolicy::resolveChain((int) ($req['organization_id'] ?? 0), (float) ($req['requested_amount'] ?? 0));
			$chain = $resolved['chain'];
		}
		$data['approval_chain'] = $chain;
		$data['approval_flow'] = CashRequestApprovalPolicy::flowLabels($chain);
		$data['approval_steps_label'] = CashRequestApprovalPolicy::chainLabels()[$chain] ?? $chain;
		$data['wf_actions'] = CashRequestWorkflowService::uiActionsForRequest(array_merge($req, ['approval_chain' => $chain]));
		$data['content'] = view('pages/budget/cash_request_view', $data);
		return view('main', $data);
	}

	public function cash_request_action()
	{
		$this->bootBudget();
		$c = $this->ctx();
		$wf = new CashRequestWorkflowService();
		$res = $wf->transition(
			(int) $this->request->getPost('request_id'),
			$this->request->getPost('action'),
			$c['staffId'],
			$c['postId'],
			$this->request->getPost('comment'),
			['override' => $this->request->getPost('override'), 'override_reason' => $this->request->getPost('override_reason')]
		);
		return $this->response->setJSON($res);
	}

	public function pending_actions()
	{
		return redirect()->to(base_url('budget/requests?tab=pending'));
	}

	public function procurement_review()
	{
		return redirect()->to(base_url('budget/requests?tab=pending'));
	}

	public function budget_availability_review()
	{
		return redirect()->to(base_url('budget/requests?tab=pending'));
	}

	public function final_approval()
	{
		return redirect()->to(base_url('budget/requests?tab=pending'));
	}

	public function payments()
	{
		return redirect()->to(base_url('budget/requests?tab=payments'));
	}

	public function record_payment()
	{
		$this->bootBudget();
		$this->denyPerm('cash_request.process_payment');
		$c = $this->ctx();
		$pay = new PaymentService();
		$res = $pay->recordPayment(
			(int) $this->request->getPost('request_id'),
			(float) $this->request->getPost('amount'),
			$this->request->getPost('payment_method'),
			trim((string) $this->request->getPost('payment_reference')),
			$this->request->getPost('payment_date') ?: date('Y-m-d'),
			$c['staffId'],
			$c['postId']
		);
		return $this->response->setJSON($res);
	}

	public function filing()
	{
		return redirect()->to(base_url('budget/requests?tab=receipts'));
	}

	public function confirm_receipt()
	{
		$this->bootBudget();
		$this->denyPerm('cash_request.confirm_receipt');
		$c = $this->ctx();
		$requestId = (int) $this->request->getPost('request_id');
		$db = \Config\Database::connect();
		$db->table('receipt_confirmations')->insert([
			'cash_request_id' => $requestId,
			'confirmed_by' => $c['staffId'],
			'confirmed_at' => date('Y-m-d H:i:s'),
			'filing_reference' => $this->request->getPost('filing_reference'),
			'notes' => $this->request->getPost('notes'),
		]);
		$wf = new CashRequestWorkflowService();
		$wf->transition($requestId, 'confirm_receipt', $c['staffId'], $c['postId']);
		$wf->transition($requestId, 'close', $c['staffId'], $c['postId']);
		return $this->response->setJSON(['success' => 'Receipt confirmed and request closed.']);
	}

	public function reports()
	{
		$this->denyMenuAny(['budget_reports', 'budget_audit', 'budget_settings']);
		$c = $this->ctx();
		$db = \Config\Database::connect();
		$data = $this->data;
		$tab = trim((string) $this->request->getGet('tab')) ?: 'summary';
		if (!in_array($tab, ['summary', 'cashflow', 'actuals', 'audit'], true)) {
			$tab = 'summary';
		}
		$data['tab'] = $tab;
		$data['title'] = 'Budget Reports';
		$data['page'] = 'budget_reports';
		$branchIds = array_column($c['branchCtx']->accessibleBranchIds($c['staffId'], $c['postId'], $c['schoolId']), 'id');
		$data['branch_label'] = $c['branch']
			? $c['branchCtx']->displaySchoolBranchLabel($c['schoolId'], $c['branch'], false)
			: session('soma_school');
		$bid = (int) $c['branchId'];
		$data['financials'] = $this->branchFinancialSummary($bid, $db);
		$budgetId = !empty($data['financials']['budget']['id']) ? (int) $data['financials']['budget']['id'] : 0;
		$data['summary_lines'] = [];
		if ($budgetId > 0) {
			$data['summary_lines'] = $db->table('budget_lines')->where('budget_id', $budgetId)
				->where('is_total_row', 0)->orderBy('sort_order')->get()->getResultArray();
		}
		$data['actuals'] = $db->table('cash_request_payments p')
			->select('p.*, cr.request_no, cr.payee_name, cr.purpose')
			->join('cash_requests cr', 'cr.id = p.cash_request_id')
			->whereIn('cr.branch_id', $branchIds)
			->where('p.status', 'completed')
			->orderBy('p.payment_date', 'DESC')->limit(200)->get()->getResultArray();
		$data['cashflow'] = [];
		if ($budgetId > 0) {
			$lines = $db->table('budget_lines')->where('budget_id', $budgetId)->where('is_total_row', 0)->get()->getResultArray();
			$calc = new BudgetCalculationService();
			$months = BudgetCalculationService::MONTHS;
			foreach ($months as $m) {
				$data['cashflow'][$m] = ['in' => 0.0, 'out' => 0.0];
			}
			foreach ($lines as $ln) {
				$monthly = $calc->decodeMonthlyJson($ln['monthly_json'] ?? null);
				$isIncome = stripos($ln['section_label'] ?? '', 'INCOME') !== false;
				foreach ($months as $m) {
					if ($isIncome) {
						$data['cashflow'][$m]['in'] += (float) ($monthly[$m] ?? 0);
					} else {
						$data['cashflow'][$m]['out'] += (float) ($monthly[$m] ?? 0);
					}
				}
			}
		}
		if ($tab === 'audit' && budget_menu_any(['budget_audit'])) {
			$data['logs'] = $db->table('financial_audit_logs')->whereIn('branch_id', $branchIds)
				->orderBy('id', 'DESC')->limit(300)->get()->getResultArray();
		} else {
			$data['logs'] = [];
		}
		$data['settings'] = $db->table('budget_settings')->where('organization_id', $c['orgId'])->where('branch_id', null)->get(1)->getRowArray();
		$data['content'] = view('pages/budget/reports', $data);
		return view('main', $data);
	}

	public function audit_trail()
	{
		return redirect()->to(base_url('budget/reports?tab=audit'));
	}

	public function get_budget_lines_json($budgetId = null)
	{
		$this->bootBudget();
		$db = \Config\Database::connect();
		$availSvc = new BudgetAvailabilityService();
		$calc = new BudgetCalculationService();
		// All real budget lines (income + expenses) — exclude section total rows only
		$lines = $db->table('budget_lines')
			->where('budget_id', (int) $budgetId)
			->where('is_total_row', 0)
			->orderBy('sort_order')
			->get()->getResultArray();
		$out = [];
		foreach ($lines as $ln) {
			// Prefer stored annual; fall back to term/monthly calc if annual was left blank
			$annual = (float) ($ln['annual_amount'] ?? 0);
			if ($annual <= 0) {
				$annual = (float) $calc->lineAnnualAmount($ln);
				if ($annual > 0 && (float) ($ln['annual_amount'] ?? 0) <= 0) {
					$db->table('budget_lines')->where('id', (int) $ln['id'])->update(['annual_amount' => $annual]);
					$ln['annual_amount'] = $annual;
				}
			}
			$ln['availability'] = $availSvc->lineAvailability((int) $ln['id']);
			$ln['section'] = $ln['section_label'] ?? '';
			$ln['is_income'] = (stripos((string) ($ln['section_label'] ?? ''), 'INCOME') !== false) ? 1 : 0;
			$out[] = $ln;
		}
		return $this->response->setJSON(['lines' => $out]);
	}

	public function cash_request_document($docId = null)
	{
		$this->bootBudget();
		$this->denyMenu('budget_cash_requests');
		$db = \Config\Database::connect();
		$doc = $db->table('cash_request_documents')->where('id', (int) $docId)->get(1)->getRowArray();
		if (!$doc) {
			return redirect()->to(base_url('budget/cash_requests'));
		}
		$path = WRITEPATH . str_replace('writable/', '', $doc['stored_path']);
		if (!is_file($path)) {
			return redirect()->back()->with('error', 'Document file not found.');
		}
		$inline = (string) ($this->request->getGet('inline') ?? '') === '1';
		$mime = $doc['mime'] ?? null;
		if (!$mime) {
			$ext = strtolower(pathinfo($doc['original_name'] ?? '', PATHINFO_EXTENSION));
			$mimeMap = [
				'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
				'gif' => 'image/gif', 'webp' => 'image/webp', 'pdf' => 'application/pdf',
			];
			$mime = $mimeMap[$ext] ?? 'application/octet-stream';
		}
		if ($inline && (strpos($mime, 'image/') === 0 || $mime === 'application/pdf')) {
			return $this->response
				->setHeader('Content-Type', $mime)
				->setHeader('Content-Disposition', 'inline; filename="' . str_replace('"', '', (string) $doc['original_name']) . '"')
				->setBody(file_get_contents($path));
		}
		return $this->response->download($path, null)->setFileName($doc['original_name']);
	}

	public function scan_session_start()
	{
		$this->bootBudget();
		$c = $this->ctx();
		$svc = new MobileScanBridgeService();
		$session = $svc->createSession((int) $c['staffId']);
		return $this->response->setJSON(['success' => true] + $session);
	}

	public function scan_session_poll()
	{
		$this->bootBudget();
		$token = trim((string) ($this->request->getGet('token') ?? $this->request->getPost('token')));
		if ($token === '') {
			return $this->response->setJSON(['error' => 'Missing token.']);
		}
		$svc = new MobileScanBridgeService();
		return $this->response->setJSON($svc->poll($token));
	}

	public function badge_counts_json()
	{
		$this->bootBudget();
		$c = $this->ctx();
		$db = \Config\Database::connect();
		$bid = (int) $c['branchId'];
		return $this->response->setJSON([
			'pending' => $db->table('cash_requests')->where('branch_id', $bid)->whereNotIn('status', ['CLOSED','CANCELLED','DRAFT'])->countAllResults(),
			'notifications' => (new BudgetNotificationService())->unreadCount($c['staffId']),
		]);
	}

	private function ensureBudgetLineColumns($db)
	{
		static $done = false;
		if ($done) {
			return;
		}
		$sqlFile = ROOTPATH . 'deploy/add_budget_monthly_json.sql';
		if (is_file($sqlFile)) {
			foreach (array_filter(array_map('trim', explode(';', file_get_contents($sqlFile)))) as $stmt) {
				if ($stmt === '') {
					continue;
				}
				try {
					$db->query($stmt);
				} catch (\Throwable $e) {
					// column may exist
				}
			}
		}
		$done = true;
	}
}
