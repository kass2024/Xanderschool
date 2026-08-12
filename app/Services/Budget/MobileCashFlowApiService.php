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
		$db = \Config\Database::connect();
		$rows = $db->table('cash_requests cr')
			->select('cr.id, cr.request_no, cr.status, cr.payee_name, cr.purpose, cr.requested_amount, cr.request_date, cr.created_by')
			->where('cr.branch_id', $branchId)
			->where('cr.created_by', $staffId)
			->orderBy('cr.id', 'DESC')
			->limit(100)
			->get()->getResultArray();
		return $this->enrichLines($rows);
	}

	public function listPending(int $branchId, int $postId): array
	{
		$db = \Config\Database::connect();
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
		if (!$statuses) {
			return [];
		}
		$rows = $db->table('cash_requests cr')
			->select('cr.id, cr.request_no, cr.status, cr.payee_name, cr.purpose, cr.requested_amount, cr.request_date, cr.created_by')
			->where('cr.branch_id', $branchId)
			->whereIn('cr.status', $statuses)
			->orderBy('cr.id', 'DESC')
			->limit(100)
			->get()->getResultArray();
		return $this->enrichLines($rows);
	}

	public function formData(int $branchId): array
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
			return [
				'budget_id' => $budgetId,
				'budget_title' => $budgets[0]['title'] ?? '',
				'lines' => $lines,
			];
		}
		return ['budget_id' => 0, 'budget_title' => '', 'lines' => []];
	}

	public function allowedActions(int $postId, string $status): array
	{
		$map = [
			'SUBMITTED' => ['headteacher_approve' => 20, 'procurement_approve' => 19, 'reject' => null],
			'HEADTEACHER_APPROVED' => ['procurement_approve' => 19],
			'PROCUREMENT_APPROVED' => ['budget_approve' => 21],
			'BUDGET_APPROVED' => ['final_approve' => 24, 'reject' => 24],
			'FINANCE_AUTHORIZED' => ['pay' => 9],
		];
		$actions = [];
		foreach ($map[$status] ?? [] as $action => $allowedPost) {
			if ($allowedPost === null || (int) $allowedPost === $postId) {
				$actions[] = $action;
			}
		}
		return $actions;
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
		}
		return $rows;
	}
}
