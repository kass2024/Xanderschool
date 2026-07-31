<?php

namespace App\Controllers;

use App\Models\BudgetSchemaModel;
use App\Services\Budget\BranchContextService;
use App\Services\Budget\BudgetAvailabilityService;
use App\Services\Budget\BudgetCalculationService;
use App\Services\Budget\BudgetNotificationService;
use App\Services\Budget\BudgetPermissionService;
use App\Services\Budget\BudgetTemplateImportService;
use App\Services\Budget\BudgetWorkflowService;
use App\Services\Budget\CashRequestWorkflowService;
use App\Services\Budget\DocumentStorageService;
use App\Services\Budget\FinancialAuditService;
use App\Services\Budget\PaymentService;

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
		}
		$data['content'] = view('pages/budget/dashboard', $data);
		return view('main', $data);
	}

	public function periods()
	{
		$this->denyMenu('budget_periods');
		$this->denyPerm('budget.periods.manage');
		$c = $this->ctx();
		$data = $this->data;
		$db = \Config\Database::connect();
		$data['title'] = 'Budget Periods';
		$data['page'] = 'budget_periods';
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
			$data['periods'] = $db->table('budget_periods')->where('branch_id', $c['branchId'])->orderBy('start_date', 'DESC')->get()->getResultArray();
			$label = $c['branch'] ? $c['branchCtx']->displaySchoolBranchLabel($c['schoolId'], $c['branch'], false) : '';
			foreach ($data['periods'] as &$p) {
				$p['branch_name'] = $label;
			}
			unset($p);
		}
		$data['branches'] = $c['branchCtx']->accessibleBranches($c['staffId'], $c['postId'], $c['schoolId'], $c['isCentral']);
		$data['content'] = view('pages/budget/periods', $data);
		return view('main', $data);
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
		$this->denyMenu('budget_settings');
		$this->denyPerm('budget.settings.manage');
		$c = $this->ctx();
		$data = $this->data;
		$db = \Config\Database::connect();
		$data['title'] = 'Budget Settings';
		$data['page'] = 'budget_settings';
		$data['settings'] = $db->table('budget_settings')->where('organization_id', $c['orgId'])->where('branch_id', null)->get(1)->getRowArray();
		$data['content'] = view('pages/budget/settings', $data);
		return view('main', $data);
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
		$this->denyMenu('budget_templates');
		$this->denyPerm('budget.templates.view');
		$c = $this->ctx();
		$data = $this->data;
		$db = \Config\Database::connect();
		$data['title'] = 'Budget Templates';
		$data['page'] = 'budget_templates';
		$data['templates'] = $db->table('budget_templates')->where('organization_id', $c['orgId'])->orderBy('id', 'DESC')->get()->getResultArray();
		$data['content'] = view('pages/budget/templates', $data);
		return view('main', $data);
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
		return $this->response->setJSON(['success' => 'Template uploaded.', 'template_id' => $res['template_id']]);
	}

	public function activate_template()
	{
		$this->bootBudget();
		$this->denyPerm('budget.templates.activate');
		$id = (int) $this->request->getPost('template_id');
		$db = \Config\Database::connect();
		$db->table('budget_templates')->where('id', $id)->update(['status' => 'active', 'updated_at' => date('Y-m-d H:i:s')]);
		return $this->response->setJSON(['success' => 'Template activated.']);
	}

	public function prepare()
	{
		$this->denyMenu('budget_prepare');
		$this->denyPerm('budget.prepare');
		$c = $this->ctx();
		$data = $this->data;
		$db = \Config\Database::connect();
		$data['title'] = 'Budget Preparation';
		$data['page'] = 'budget_prepare';
		$data['budgets'] = $db->table('budgets')->where('branch_id', $c['branchId'])->orderBy('id', 'DESC')->get()->getResultArray();
		$data['periods'] = $db->table('budget_periods')->where('branch_id', $c['branchId'])->get()->getResultArray();
		$data['templates'] = $db->table('budget_templates')->where('organization_id', $c['orgId'])->where('status', 'active')->get()->getResultArray();
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
		$templateId = (int) $this->request->getPost('template_id');
		$template = $db->table('budget_templates')->where('id', $templateId)->get(1)->getRowArray();
		$versionId = $template ? (int) $template['current_version_id'] : 0;
		$db->transStart();
		$db->table('budgets')->insert([
			'organization_id' => $c['orgId'],
			'branch_id' => $c['branchId'],
			'budget_period_id' => $periodId,
			'template_version_id' => $versionId ?: null,
			'title' => trim((string) $this->request->getPost('title')) ?: 'Budget ' . date('Y'),
			'currency' => 'RWF',
			'status' => 'DRAFT',
			'prepared_by' => $c['staffId'],
			'prepared_at' => date('Y-m-d H:i:s'),
			'created_by' => $c['staffId'],
			'created_at' => date('Y-m-d H:i:s'),
			'updated_at' => date('Y-m-d H:i:s'),
		]);
		$budgetId = (int) $db->insertID();
		if ($versionId) {
			$lines = $db->table('budget_template_lines')->where('version_id', $versionId)->orderBy('sort_order')->get()->getResultArray();
			foreach ($lines as $ln) {
				$sec = $db->table('budget_template_sections')->where('id', $ln['section_id'])->get(1)->getRowArray();
				$secLabel = $sec ? $sec['section_label'] : 'GENERAL';
				$db->table('budget_lines')->insert([
					'budget_id' => $budgetId,
					'template_line_id' => $ln['id'],
					'section_label' => $secLabel,
					'category' => $ln['normalized_label'],
					'is_total_row' => $ln['is_total_row'],
					'is_editable' => $ln['is_editable'],
					'sort_order' => $ln['sort_order'],
				]);
			}
		} else {
			$import = new BudgetTemplateImportService();
			foreach ($import->defaultStructure() as $ln) {
				$db->table('budget_lines')->insert([
					'budget_id' => $budgetId,
					'section_label' => $ln['section'],
					'category' => $ln['normalized_label'],
					'is_total_row' => $ln['is_total_row'] ? 1 : 0,
					'is_editable' => $ln['is_editable'] ? 1 : 0,
					'sort_order' => $ln['sort_order'],
				]);
			}
		}
		$db->transComplete();
		return $this->response->setJSON(['success' => 'Budget created.', 'budget_id' => $budgetId]);
	}

	public function edit_budget($id = null)
	{
		$this->denyMenu('budget_prepare');
		$this->denyPerm('budget.prepare');
		$c = $this->ctx();
		$id = (int) $id;
		$db = \Config\Database::connect();
		$budget = $db->table('budgets')->where('id', $id)->where('branch_id', $c['branchId'])->get(1)->getRowArray();
		if (!$budget || $budget['status'] === 'APPROVED') {
			return redirect()->to(base_url('budget/prepare'));
		}
		$data = $this->data;
		$data['title'] = 'Edit Budget';
		$data['page'] = 'budget_prepare';
		$data['budget'] = $budget;
		$data['lines'] = $db->table('budget_lines')->where('budget_id', $id)->orderBy('sort_order')->get()->getResultArray();
		$data['content'] = view('pages/budget/edit_budget', $data);
		return view('main', $data);
	}

	public function save_budget_lines()
	{
		$this->bootBudget();
		$this->denyPerm('budget.edit_own');
		$c = $this->ctx();
		$budgetId = (int) $this->request->getPost('budget_id');
		$db = \Config\Database::connect();
		$budget = $db->table('budgets')->where('id', $budgetId)->get(1)->getRowArray();
		if (!$budget || $budget['status'] !== 'DRAFT' && $budget['status'] !== 'RETURNED') {
			return $this->response->setJSON(['error' => 'Budget is not editable.']);
		}
		$lines = $this->request->getPost('lines') ?: [];
		$calc = new BudgetCalculationService();
		foreach ($lines as $lid => $row) {
			if (!is_array($row)) continue;
			$update = [
				'user_amount' => (float) ($row['user_amount'] ?? 0),
				'quantity' => $row['quantity'] ?? null,
				'unit_cost' => $row['unit_cost'] ?? null,
				'frequency' => $row['frequency'] ?? 1,
				'term_1_amount' => (float) ($row['term_1_amount'] ?? 0),
				'term_2_amount' => (float) ($row['term_2_amount'] ?? 0),
				'term_3_amount' => (float) ($row['term_3_amount'] ?? 0),
				'calculation_mode' => $row['calculation_mode'] ?? 'manual',
				'assumptions' => $row['assumptions'] ?? null,
			];
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
		$wf = new BudgetWorkflowService();
		return $this->response->setJSON($wf->transition($id, 'submit', $c['staffId'], $c['postId'], $this->request->getPost('comment')));
	}

	public function budget_review()
	{
		$this->denyMenu('budget_review');
		$c = $this->ctx();
		$data = $this->data;
		$db = \Config\Database::connect();
		$data['title'] = 'Budget Review & Approval';
		$data['page'] = 'budget_review';
		$branchIds = array_column($c['branchCtx']->accessibleBranchIds($c['staffId'], $c['postId'], $c['schoolId']), 'id');
		$data['budgets'] = $db->table('budgets')->whereIn('branch_id', $branchIds)
			->whereNotIn('status', ['DRAFT','APPROVED','CANCELLED'])->orderBy('id','DESC')->get()->getResultArray();
		$data['content'] = view('pages/budget/budget_review', $data);
		return view('main', $data);
	}

	public function budget_action()
	{
		$this->bootBudget();
		$c = $this->ctx();
		$action = $this->request->getPost('action');
		$id = (int) $this->request->getPost('budget_id');
		$wf = new BudgetWorkflowService();
		return $this->response->setJSON($wf->transition($id, $action, $c['staffId'], $c['postId'], $this->request->getPost('comment')));
	}

	public function approved_budgets()
	{
		$this->denyMenu('budget_approved');
		$c = $this->ctx();
		$db = \Config\Database::connect();
		$data = $this->data;
		$data['title'] = 'Approved Budgets';
		$data['page'] = 'budget_approved';
		$data['budgets'] = $db->table('budgets b')
			->select('b.*, br.name as branch_name, br.organization_id')
			->join('branches br', 'br.id = b.branch_id')
			->where('b.status', 'APPROVED');
		$branchIds = array_column($c['branchCtx']->accessibleBranchIds($c['staffId'], $c['postId'], $c['schoolId']), 'id');
		$data['budgets'] = $data['budgets']->whereIn('b.branch_id', $branchIds)->orderBy('b.id','DESC')->get()->getResultArray();
		foreach ($data['budgets'] as &$b) {
			$b['branch_name'] = $c['branchCtx']->displayBranchName([
				'name' => $b['branch_name'],
				'organization_id' => $b['organization_id'],
			], $c['isCentral']);
		}
		unset($b);
		$data['content'] = view('pages/budget/approved_budgets', $data);
		return view('main', $data);
	}

	public function cash_requests()
	{
		$this->denyMenu('budget_cash_requests');
		$c = $this->ctx();
		$db = \Config\Database::connect();
		$data = $this->data;
		$data['title'] = 'Cash Requests';
		$data['page'] = 'budget_cash_requests';
		$q = $db->table('cash_requests cr')->select('cr.*, b.name as branch_name, b.organization_id')->join('branches b','b.id=cr.branch_id');
		$branchIds = array_column($c['branchCtx']->accessibleBranchIds($c['staffId'], $c['postId'], $c['schoolId']), 'id');
		$q->whereIn('cr.branch_id', $branchIds);
		$data['requests'] = $q->orderBy('cr.id','DESC')->get()->getResultArray();
		foreach ($data['requests'] as &$r) {
			$r['branch_name'] = $c['branchCtx']->displayBranchName([
				'name' => $r['branch_name'],
				'organization_id' => $r['organization_id'],
			], $c['isCentral']);
		}
		unset($r);
		$data['content'] = view('pages/budget/cash_requests', $data);
		return view('main', $data);
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
		$data['budgets'] = $db->table('budgets')->where('branch_id', $c['branchId'])->where('status','APPROVED')->get()->getResultArray();
		$data['lines'] = $id ? $db->table('cash_request_lines')->where('cash_request_id', (int)$id)->get()->getResultArray() : [];
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
		$amount = (float) $this->request->getPost('requested_amount');
		$row = [
			'organization_id' => $c['orgId'],
			'branch_id' => $c['branchId'],
			'budget_id' => (int) $this->request->getPost('budget_id') ?: null,
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
		$wf = new CashRequestWorkflowService();
		if ($id > 0) {
			$db->table('cash_requests')->where('id', $id)->where('created_by', $c['staffId'])->update($row);
		} else {
			$row['request_no'] = $wf->nextRequestNo($c['branchId']);
			$row['status'] = 'DRAFT';
			$row['created_by'] = $c['staffId'];
			$row['created_at'] = date('Y-m-d H:i:s');
			$db->table('cash_requests')->insert($row);
			$id = (int) $db->insertID();
		}
		$lineAmount = (float) $this->request->getPost('line_amount');
		$lineDesc = $this->request->getPost('line_description');
		$budgetLineId = (int) $this->request->getPost('budget_line_id');
		if ($lineDesc && $lineAmount > 0) {
			$db->table('cash_request_lines')->where('cash_request_id', $id)->delete();
			$db->table('cash_request_lines')->insert([
				'cash_request_id' => $id,
				'budget_line_id' => $budgetLineId ?: null,
				'description' => $lineDesc,
				'amount' => $lineAmount,
			]);
		}
		if ($this->request->getPost('submit_now')) {
			$res = $wf->transition($id, 'submit', $c['staffId'], $c['postId'], 'Submitted');
			if (!$res['success']) {
				return $this->response->setJSON($res);
			}
		}
		return $this->response->setJSON(['success' => 'Cash request saved.', 'id' => $id]);
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
		$this->denyMenu('budget_pending');
		$c = $this->ctx();
		$db = \Config\Database::connect();
		$data = $this->data;
		$data['title'] = 'My Pending Actions';
		$data['page'] = 'budget_pending';
		$postId = $c['postId'];
		$statusMap = [
			20 => ['SUBMITTED','HEADTEACHER_APPROVED'],
			19 => ['PROCUREMENT_APPROVED'],
			21 => ['BUDGET_APPROVED'],
			22 => ['FINANCE_AUTHORIZED'],
			9 => ['PAID'],
			1 => ['SUBMITTED'],
		];
		$statuses = $statusMap[$postId] ?? [];
		$data['requests'] = [];
		if ($statuses) {
			$branchIds = array_column($c['branchCtx']->accessibleBranchIds($c['staffId'], $c['postId'], $c['schoolId']), 'id');
			$data['requests'] = $db->table('cash_requests')->whereIn('status', $statuses)
				->whereIn('branch_id', $branchIds)->orderBy('id','DESC')->get()->getResultArray();
		}
		$data['content'] = view('pages/budget/pending', $data);
		return view('main', $data);
	}

	public function procurement_review()
	{
		$this->denyMenu('budget_procurement');
		$this->denyPerm('cash_request.procurement_review');
		return $this->pending_actions();
	}

	public function budget_availability_review()
	{
		$this->denyMenu('budget_availability');
		$this->denyPerm('cash_request.budget_review');
		return $this->pending_actions();
	}

	public function final_approval()
	{
		$this->denyMenu('budget_final_approval');
		$this->denyPerm('cash_request.final_approve');
		return $this->pending_actions();
	}

	public function payments()
	{
		$this->denyMenu('budget_payments');
		$this->denyPerm('cash_request.process_payment');
		$c = $this->ctx();
		$db = \Config\Database::connect();
		$data = $this->data;
		$data['title'] = 'Payments';
		$data['page'] = 'budget_payments';
		$branchIds = array_column($c['branchCtx']->accessibleBranchIds($c['staffId'], $c['postId'], $c['schoolId']), 'id');
		$data['requests'] = $db->table('cash_requests')->whereIn('branch_id', $branchIds)
			->whereIn('status', ['FINANCE_AUTHORIZED','PARTIALLY_PAID'])->orderBy('id','DESC')->get()->getResultArray();
		$data['content'] = view('pages/budget/payments', $data);
		return view('main', $data);
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
		$this->denyMenu('budget_filing');
		$this->denyPerm('cash_request.confirm_receipt');
		$c = $this->ctx();
		$db = \Config\Database::connect();
		$data = $this->data;
		$data['title'] = 'Receipt & Filing';
		$data['page'] = 'budget_filing';
		$branchIds = array_column($c['branchCtx']->accessibleBranchIds($c['staffId'], $c['postId'], $c['schoolId']), 'id');
		$data['requests'] = $db->table('cash_requests')->whereIn('branch_id', $branchIds)
			->where('status', 'PAID')->orderBy('id','DESC')->get()->getResultArray();
		$data['content'] = view('pages/budget/filing', $data);
		return view('main', $data);
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
		$this->denyMenu('budget_reports');
		$this->denyPerm('budget.view_reports');
		$data = $this->data;
		$data['title'] = 'Budget Reports';
		$data['page'] = 'budget_reports';
		$data['content'] = view('pages/budget/reports', $data);
		return view('main', $data);
	}

	public function audit_trail()
	{
		$this->denyMenu('budget_audit');
		$this->denyPerm('cash_request.view_audit');
		$c = $this->ctx();
		$db = \Config\Database::connect();
		$data = $this->data;
		$data['title'] = 'Audit Trail';
		$data['page'] = 'budget_audit';
		$q = $db->table('financial_audit_logs')->orderBy('id','DESC')->limit(500);
		$branchIds = array_column($c['branchCtx']->accessibleBranchIds($c['staffId'], $c['postId'], $c['schoolId']), 'id');
		$q->whereIn('branch_id', $branchIds);
		$data['logs'] = $q->get()->getResultArray();
		$data['content'] = view('pages/budget/audit', $data);
		return view('main', $data);
	}

	public function get_budget_lines_json($budgetId = null)
	{
		$this->bootBudget();
		$db = \Config\Database::connect();
		$lines = $db->table('budget_lines')->where('budget_id', (int)$budgetId)->where('is_editable', 1)->orderBy('sort_order')->get()->getResultArray();
		return $this->response->setJSON(['lines' => $lines]);
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
}
