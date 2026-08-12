<?php

namespace App\Services\Budget;

class MobileCashFlowApiService
{
	public function staffContext(int $schoolId, int $staffId): ?array
	{
		$db = \Config\Database::connect();
		$staff = $db->table('staffs')->where('id', $staffId)->where('school_id', $schoolId)->get(1)->getRowArray();
		if (!$staff) {
			return null;
		}
		$postId = (int) ($staff['post'] ?? 0);
		$perms = new BudgetPermissionService();
		$branchId = $perms->primaryBranchId($staffId, $schoolId);
		$branch = $branchId ? $db->table('branches')->where('id', $branchId)->get(1)->getRowArray() : null;
		return [
			'staff_id' => $staffId,
			'post_id' => $postId,
			'school_id' => $schoolId,
			'branch_id' => $branchId,
			'org_id' => $branch ? (int) ($branch['organization_id'] ?? 0) : 0,
			'staff_name' => trim(($staff['fname'] ?? '') . ' ' . ($staff['lname'] ?? '')),
		];
	}

	public function listRequests(int $branchId, int $staffId): array
	{
		CashRequestApprovalPolicy::ensureSchema();
		$db = \Config\Database::connect();
		$rows = $db->table('cash_requests cr')
			->select('cr.id, cr.request_no, cr.status, cr.payee_name, cr.purpose, cr.requested_amount, cr.approval_chain, cr.request_date, cr.created_by')
			->where('cr.branch_id', $branchId)
			->where('cr.created_by', $staffId)
			->orderBy('cr.id', 'DESC')
			->limit(100)
			->get()->getResultArray();
		return $this->enrichLines($rows);
	}

	/**
	 * Pending inbox — mirrors web BudgetCashflow::requests?tab=pending
	 * (amount-tier chains: short / medium / full).
	 */
	public function listPending(int $branchId, int $postId): array
	{
		CashRequestApprovalPolicy::ensureSchema();
		$db = \Config\Database::connect();
		$statusMap = [
			20 => ['HEADTEACHER_APPROVED'], // Procurement after HM (full/medium)
			19 => ['PROCUREMENT_APPROVED'],
			21 => ['BUDGET_APPROVED'],
			22 => ['FINANCE_AUTHORIZED'],
			// DoF: short at HEADTEACHER_APPROVED; medium at PROCUREMENT_APPROVED; full at BUDGET_APPROVED
			24 => ['HEADTEACHER_APPROVED', 'PROCUREMENT_APPROVED', 'BUDGET_APPROVED', 'FINANCE_AUTHORIZED'],
			9 => ['FINANCE_AUTHORIZED', 'PAID'],
			1 => ['SUBMITTED'],
			18 => ['SUBMITTED'],
		];
		$statuses = $statusMap[$postId] ?? [];
		if (!$statuses) {
			return [];
		}
		$rows = $db->table('cash_requests cr')
			->select('cr.id, cr.request_no, cr.status, cr.payee_name, cr.purpose, cr.requested_amount, cr.approval_chain, cr.request_date, cr.created_by')
			->where('cr.branch_id', $branchId)
			->whereIn('cr.status', $statuses)
			->orderBy('cr.id', 'DESC')
			->limit(100)
			->get()->getResultArray();

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

		return $this->enrichLines($rows);
	}

	public function formData(int $branchId, int $orgId = 0): array
	{
		$db = \Config\Database::connect();
		$budgets = $db->table('budgets')->where('branch_id', $branchId)->where('status', 'APPROVED')
			->orderBy('id', 'DESC')->get()->getResultArray();
		$lines = [];
		if ($budgets) {
			$budgetId = (int) $budgets[0]['id'];
			$availSvc = new BudgetAvailabilityService();
			foreach ($db->table('budget_lines')->where('budget_id', $budgetId)->where('is_total_row', 0)->orderBy('sort_order')->get()->getResultArray() as $ln) {
				$av = $availSvc->lineAvailability((int) $ln['id']);
				$lines[] = [
					'id' => (int) $ln['id'],
					'category' => $ln['category'],
					'section' => $ln['section_label'] ?? '',
					'available' => (float) ($av['available'] ?? 0),
				];
			}
			$tiers = $orgId > 0 ? CashRequestApprovalPolicy::loadTiersForOrg($orgId) : CashRequestApprovalPolicy::defaultTiers();
			return [
				'budget_id' => $budgetId,
				'budget_title' => $budgets[0]['title'] ?? '',
				'lines' => $lines,
				'org_id' => $orgId,
				'approval_tiers' => $tiers,
				'approval_chain_labels' => CashRequestApprovalPolicy::chainLabels(),
			];
		}
		return [
			'budget_id' => 0,
			'budget_title' => '',
			'lines' => [],
			'org_id' => $orgId,
			'approval_tiers' => [],
			'approval_chain_labels' => CashRequestApprovalPolicy::chainLabels(),
		];
	}

	/**
	 * Dashboard KPIs aligned with web Budget & Cash Flow dashboard.
	 */
	public function dashboardStats(int $branchId, int $staffId, int $postId): array
	{
		CashRequestApprovalPolicy::ensureSchema();
		$db = \Config\Database::connect();

		$active = (int) $db->table('cash_requests')
			->where('branch_id', $branchId)
			->whereNotIn('status', ['DRAFT', 'CLOSED', 'CANCELLED', 'REJECTED', 'VOIDED'])
			->countAllResults();
		$awaitingPayment = (int) $db->table('cash_requests')
			->where('branch_id', $branchId)
			->where('status', 'FINANCE_AUTHORIZED')
			->countAllResults();
		$awaitingReceipt = (int) $db->table('cash_requests')
			->where('branch_id', $branchId)
			->where('status', 'PAID')
			->countAllResults();
		$myDrafts = (int) $db->table('cash_requests')
			->where('branch_id', $branchId)
			->where('created_by', $staffId)
			->whereIn('status', ['DRAFT', 'RETURNED_TO_ACCOUNTANT'])
			->countAllResults();
		$myActive = (int) $db->table('cash_requests')
			->where('branch_id', $branchId)
			->where('created_by', $staffId)
			->whereNotIn('status', ['DRAFT', 'CLOSED', 'CANCELLED', 'REJECTED', 'VOIDED'])
			->countAllResults();

		$pendingRows = $this->listPending($branchId, $postId);
		$pendingMine = count($pendingRows);

		$pipeline = [
			'SUBMITTED' => (int) $db->table('cash_requests')->where('branch_id', $branchId)->where('status', 'SUBMITTED')->countAllResults(),
			'HEADTEACHER_APPROVED' => (int) $db->table('cash_requests')->where('branch_id', $branchId)->where('status', 'HEADTEACHER_APPROVED')->countAllResults(),
			'PROCUREMENT_APPROVED' => (int) $db->table('cash_requests')->where('branch_id', $branchId)->where('status', 'PROCUREMENT_APPROVED')->countAllResults(),
			'BUDGET_APPROVED' => (int) $db->table('cash_requests')->where('branch_id', $branchId)->where('status', 'BUDGET_APPROVED')->countAllResults(),
			'FINANCE_AUTHORIZED' => $awaitingPayment,
			'PAID' => $awaitingReceipt,
		];

		$totalPaid = 0.0;
		try {
			$row = $db->table('cash_request_payments crp')
				->selectSum('crp.amount', 'total')
				->join('cash_requests cr', 'cr.id = crp.cash_request_id')
				->where('cr.branch_id', $branchId)
				->where('crp.status', 'completed')
				->get(1)->getRowArray();
			$totalPaid = (float) ($row['total'] ?? 0);
		} catch (\Throwable $e) {
			$totalPaid = 0.0;
		}

		return [
			'active_requests' => $active,
			'awaiting_payment' => $awaitingPayment,
			'awaiting_receipt' => $awaitingReceipt,
			'pending_approvals' => $pendingMine,
			'my_drafts' => $myDrafts,
			'my_active' => $myActive,
			'total_paid' => $totalPaid,
			'pipeline' => $pipeline,
		];
	}

	/**
	 * Actions the current post may run on this request (chain-aware, mirrors web UI).
	 */
	public function allowedActions(int $postId, string $status, string $chain = 'full'): array
	{
		$chain = strtolower(trim($chain)) ?: CashRequestApprovalPolicy::CHAIN_FULL;
		$ui = CashRequestWorkflowService::uiActionsForRequest([
			'status' => $status,
			'approval_chain' => $chain,
		]);

		$postActionMap = [
			1 => ['headteacher_approve', 'return', 'reject'],
			18 => ['headteacher_approve', 'return', 'reject'],
			20 => ['procurement_approve', 'return', 'reject'],
			19 => ['budget_approve', 'return', 'reject'],
			21 => ['final_approve', 'return', 'reject'],
			24 => ['final_approve', 'return', 'reject', 'pay'],
			22 => ['pay', 'partial_pay'],
			9 => ['pay', 'confirm_receipt', 'close'],
		];
		$allowedForPost = $postActionMap[$postId] ?? [];
		if ((int) $postId === 24) {
			$allowedForPost = array_unique(array_merge($allowedForPost, ['final_approve', 'return', 'reject', 'pay']));
		}

		$actions = [];
		foreach (array_keys($ui) as $action) {
			if (in_array($action, $allowedForPost, true)) {
				$actions[] = $action;
			}
		}

		// Payment / receipt steps not always in uiActionsForRequest
		if ($status === 'FINANCE_AUTHORIZED' && in_array('pay', $allowedForPost, true)) {
			$actions[] = 'pay';
		}
		if ($status === 'PAID' && in_array('confirm_receipt', $allowedForPost, true)) {
			$actions[] = 'confirm_receipt';
		}

		return array_values(array_unique($actions));
	}

	private function enrichLines(array $rows): array
	{
		$db = \Config\Database::connect();
		foreach ($rows as &$row) {
			$line = $db->table('cash_request_lines crl')
				->select('crl.description, crl.amount, bl.category')
				->join('budget_lines bl', 'bl.id=crl.budget_line_id', 'left')
				->where('crl.cash_request_id', (int) $row['id'])
				->get(1)->getRowArray();
			$row['line_description'] = $line['description'] ?? '';
			$row['budget_line'] = $line['category'] ?? '';
			$row['amount'] = $line['amount'] ?? $row['requested_amount'];
			$chain = strtolower((string) ($row['approval_chain'] ?? 'full')) ?: 'full';
			$row['approval_chain'] = $chain;
			$row['approval_steps_label'] = CashRequestApprovalPolicy::chainLabels()[$chain] ?? $chain;
		}
		return $rows;
	}
}
