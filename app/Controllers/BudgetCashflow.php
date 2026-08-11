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
				];
			}
			$data['stats'] = [
				'draft_budgets' => array_sum(array_column($data['branch_stats'], 'draft_budgets')),
				'pending_cash' => array_sum(array_column($data['branch_stats'], 'pending_cash')),
				'awaiting_payment' => array_sum(array_column($data['branch_stats'], 'awaiting_payment')),
				'awaiting_receipt' => $db->table('cash_requests')->whereIn('branch_id', array_column($branches, 'id'))->where('status', 'PAID')->countAllResults(),
			];
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
			$data['gemini_enabled'] = (new GeminiBudgetAnalysisService())->isConfigured();
		}
		$data['content'] = view('pages/budget/dashboard', $data);
		return view('main', $data);
	}

	public function fill_budget_from_excel()
	{
		$this->bootBudget();
		$this->denyPerm('budget.templates.upload');
		$c = $this->ctx();
		$budgetId = (int) ($this->request->getPost('budget_id') ?? 0);
		$svc = new BudgetExcelFillService();
		$result = $svc->fillBranchBudget((int) $c['branchId'], $budgetId ?: null);
		if (empty($result['success'])) {
			return $this->response->setJSON(['error' => $result['error'] ?? 'Fill failed.']);
		}
		return $this->response->setJSON([
			'success' => sprintf('Filled %d budget lines from Excel.', (int) ($result['lines_matched'] ?? 0)),
			'budget_id' => $result['budget_id'] ?? null,
			'matched' => $result['lines_matched'] ?? 0,
		]);
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
		];
		$ctx = array_merge($fin, $stats, [
			'branch_label' => $c['branch']
				? $c['branchCtx']->displaySchoolBranchLabel($c['schoolId'], $c['branch'], false)
				: session('soma_school'),
		]);
		$gemini = new GeminiBudgetAnalysisService();
		if (!$gemini->isConfigured()) {
			return $this->response->setJSON(['error' => 'AI analysis unavailable — Gemini API key not set.']);
		}
		$analysis = $gemini->analyzeDashboard($ctx);
		if (!$analysis) {
			return $this->response->setJSON(['error' => $gemini->lastError() ?: 'Analysis failed.']);
		}
		return $this->response->setJSON(['success' => true, 'analysis' => $analysis]);
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
		return redirect()->to(base_url('budget/reports?tab=audit'));
	}

	public function save_settings()
	{
		$this->bootBudget();
		$this->denyPerm('budget.settings.manage');
		$c = $this->ctx();
		$db = \Config\Database::connect();
		$row = [
			'default_currency' => $this->request->getPost('default_currency') ?: 'RWF',
			'headteacher_approval_mode' => $this->request->getPost('headteacher_approval_mode') ?: 'evidence',
			'ai_enabled' => $this->request->getPost('ai_enabled') ? 1 : 0,
			'budget_utilization_alert_pct' => (float) $this->request->getPost('budget_utilization_alert_pct') ?: 80,
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
		return $this->response->setJSON(['success' => 'Settings saved.']);
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
		$db->transComplete();
		return $this->response->setJSON(['success' => 'Annual budget workspace ready.', 'budget_id' => $budgetId]);
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
		$this->denyPerm('budget.prepare');
		$c = $this->ctx();
		$id = (int) $id;
		$db = \Config\Database::connect();
		$this->ensureBudgetLineColumns($db);
		$calc = new BudgetCalculationService();
		$budget = $db->table('budgets b')
			->select('b.*, bp.title as period_title, bp.start_date, bp.end_date')
			->join('budget_periods bp', 'bp.id = b.budget_period_id', 'left')
			->where('b.id', $id)->where('b.branch_id', $c['branchId'])->get(1)->getRowArray();
		if (!$budget || $budget['status'] === 'APPROVED') {
			return redirect()->to(base_url('budget/prepare'));
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
		$setup = [];
		if (!empty($budget['notes'])) {
			$decoded = json_decode($budget['notes'], true);
			if (is_array($decoded)) {
				$setup = $decoded;
			}
		}
		$data = $this->data;
		$data['title'] = 'Budget Workspace';
		$data['page'] = 'budget_prepare';
		$data['budget'] = $budget;
		$data['lines'] = $lines;
		$data['sections'] = $calc->groupLinesBySection($lines);
		$data['branch_label'] = $c['branch']
			? $c['branchCtx']->displaySchoolBranchLabel($c['schoolId'], $c['branch'], false)
			: session('soma_school');
		$data['setup'] = $setup;
		$data['units'] = ['Student', 'Meal', 'Trip', 'Litre', 'Month', 'Item', 'Employee', 'Vehicle', 'Other'];
		$data['content'] = view('pages/budget/edit_budget', $data);
		return view('main', $data);
	}

	public function save_budget_setup()
	{
		$this->bootBudget();
		$this->denyPerm('budget.edit_own');
		$c = $this->ctx();
		$budgetId = (int) $this->request->getPost('budget_id');
		$db = \Config\Database::connect();
		$budget = $db->table('budgets')->where('id', $budgetId)->where('branch_id', $c['branchId'])->get(1)->getRowArray();
		if (!$budget || !in_array($budget['status'], ['DRAFT', 'RETURNED'], true)) {
			return $this->response->setJSON(['error' => 'Budget is not editable.']);
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
		return $this->response->setJSON(['success' => 'Setup saved.', 'setup' => $setup]);
	}

	public function save_budget_lines()
	{
		$this->bootBudget();
		$this->denyPerm('budget.edit_own');
		$c = $this->ctx();
		$budgetId = (int) $this->request->getPost('budget_id');
		$db = \Config\Database::connect();
		$this->ensureBudgetLineColumns($db);
		$budget = $db->table('budgets')->where('id', $budgetId)->get(1)->getRowArray();
		if (!$budget || $budget['status'] !== 'DRAFT' && $budget['status'] !== 'RETURNED') {
			return $this->response->setJSON(['error' => 'Budget is not editable.']);
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
		return $this->response->setJSON(['success' => 'Budget saved.', 'totals' => $totals]);
	}

	public function submit_budget()
	{
		$this->bootBudget();
		$this->denyPerm('budget.submit');
		$c = $this->ctx();
		$id = (int) $this->request->getPost('budget_id');
		$branchIds = array_column($c['branchCtx']->accessibleBranchIds($c['staffId'], $c['postId'], $c['schoolId']), 'id');
		$wf = new BudgetWorkflowService();
		return $this->response->setJSON($wf->transition($id, 'submit', $c['staffId'], $c['postId'], $this->request->getPost('comment'), [
			'perms' => $c['perms'],
			'allowed_branch_ids' => $branchIds,
		]));
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
		return $this->response->setJSON($wf->transition($id, $action, $c['staffId'], $c['postId'], $this->request->getPost('comment'), [
			'perms' => $c['perms'],
			'allowed_branch_ids' => $branchIds,
		]));
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
				20 => ['SUBMITTED', 'HEADTEACHER_APPROVED'],
				19 => ['PROCUREMENT_APPROVED'],
				21 => ['BUDGET_APPROVED'],
				22 => ['FINANCE_AUTHORIZED'],
				24 => ['BUDGET_APPROVED', 'FINANCE_AUTHORIZED'],
				9 => ['FINANCE_AUTHORIZED', 'PAID'],
				1 => ['SUBMITTED'],
				18 => ['SUBMITTED'],
			];
			$statuses = $statusMap[$postId] ?? [];
			if ($statuses) {
				$data['requests'] = $db->table('cash_requests')->whereIn('status', $statuses)
					->whereIn('branch_id', $branchIds)->orderBy('id', 'DESC')->get()->getResultArray();
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
		$data['content'] = view('pages/budget/cash_request_form', $data);
		return view('main', $data);
	}

	public function save_cash_request()
	{
		$this->bootBudget();
		$this->denyPerm('cash_request.create');
		$c = $this->ctx();
		$db = \Config\Database::connect();
		$id = (int) $this->request->getPost('id');
		$submitNow = (bool) $this->request->getPost('submit_now');
		$lineAmount = (float) $this->request->getPost('line_amount');
		$amount = (float) ($this->request->getPost('requested_amount') ?: $lineAmount);
		$budgetId = (int) $this->request->getPost('budget_id');
		$budgetLineId = (int) $this->request->getPost('budget_line_id');

		if ($budgetId <= 0) {
			return $this->response->setJSON(['error' => 'Select an approved budget.']);
		}
		if ($budgetLineId <= 0) {
			return $this->response->setJSON(['error' => 'Select a budget line — every request must link to an expense category.']);
		}
		if ($lineAmount <= 0) {
			return $this->response->setJSON(['error' => 'Enter a valid amount.']);
		}

		$budget = $db->table('budgets')->where('id', $budgetId)->where('branch_id', $c['branchId'])
			->where('status', 'APPROVED')->get(1)->getRowArray();
		if (!$budget) {
			return $this->response->setJSON(['error' => 'Budget must be APPROVED before raising cash requests.']);
		}
		$bLine = $db->table('budget_lines')->where('id', $budgetLineId)->where('budget_id', $budgetId)
			->where('is_editable', 1)->get(1)->getRowArray();
		if (!$bLine) {
			return $this->response->setJSON(['error' => 'Invalid budget line for this budget.']);
		}
		$sec = strtoupper(trim($bLine['section_label'] ?? ''));
		if ($sec === 'INCOME' || strpos($sec, 'INCOME') === 0) {
			return $this->response->setJSON(['error' => 'Cash requests must use an expense budget line, not income.']);
		}

		$availSvc = new BudgetAvailabilityService();
		$avail = $availSvc->lineAvailability($budgetLineId);
		if ($submitNow && $avail && $lineAmount > (float) $avail['available']) {
			return $this->response->setJSON([
				'error' => 'Amount exceeds available budget on this line (available: '
					. number_format((float) $avail['available'], 0) . ' RWF).',
			]);
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
			'requested_amount' => $amount,
			'payment_method' => $this->request->getPost('payment_method'),
			'urgency' => $this->request->getPost('urgency') ?: 'normal',
			'internal_notes' => $this->request->getPost('internal_notes'),
			'updated_by' => $c['staffId'],
			'updated_at' => date('Y-m-d H:i:s'),
		];
		if ($row['payee_name'] === '' || $row['purpose'] === '') {
			return $this->response->setJSON(['error' => 'Payee and purpose are required.']);
		}
		$lineDesc = trim((string) $this->request->getPost('line_description'));
		if ($lineDesc === '') {
			$lineDesc = $bLine['category'] . ' — cash request';
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
		$db->table('cash_request_lines')->insert([
			'cash_request_id' => $id,
			'budget_line_id' => $budgetLineId,
			'description' => $lineDesc,
			'amount' => $lineAmount,
		]);

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
			return $this->response->setJSON(['success' => 'Cash request submitted for approval.', 'id' => $id]);
		}
		return $this->response->setJSON(['success' => 'Cash request saved as draft.', 'id' => $id]);
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
		$data['lines'] = $db->table('cash_request_lines')->where('cash_request_id', $id)->get()->getResultArray();
		$data['actions'] = $db->table('cash_request_actions')->where('cash_request_id', $id)->orderBy('id')->get()->getResultArray();
		$data['payments'] = $db->table('cash_request_payments')->where('cash_request_id', $id)->get()->getResultArray();
		$data['documents'] = $db->table('cash_request_documents')->where('cash_request_id', $id)->orderBy('id')->get()->getResultArray();
		$data['availability'] = [];
		foreach ($data['lines'] as $ln) {
			if (!empty($ln['budget_line_id'])) {
				$data['availability'][$ln['budget_line_id']] = (new BudgetAvailabilityService())->lineAvailability($ln['budget_line_id']);
			}
		}
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
		$lines = $db->table('budget_lines')->where('budget_id', (int)$budgetId)->where('is_editable', 1)
			->orderBy('sort_order')->get()->getResultArray();
		$out = [];
		foreach ($lines as $ln) {
			$sec = strtoupper(trim($ln['section_label'] ?? ''));
			if ($sec === 'INCOME' || strpos($sec, 'INCOME') === 0) {
				continue;
			}
			$ln['availability'] = $availSvc->lineAvailability((int) $ln['id']);
			$ln['section'] = $ln['section_label'];
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
