<?php

namespace App\Services\Budget;

class BudgetWorkflowService
{
	/**
	 * Excel upload chain:
	 * 1) DRAFT / RETURNED → submit → Head Teacher of that school
	 * 2) HEADTEACHER_REVIEW → Chief Accountant
	 * 3) CHIEF_ACCOUNTANT_REVIEW → Director of Finance
	 * 4) DEPUTY_DIRECTOR_REVIEW → APPROVED (cash requests may start)
	 * Legacy procurement / budget-manager rows can still move forward.
	 */
	private static $transitions = [
		'DRAFT' => ['submit' => 'HEADTEACHER_REVIEW', 'cancel' => 'CANCELLED'],
		'RETURNED' => ['submit' => 'HEADTEACHER_REVIEW', 'cancel' => 'CANCELLED'],
		'HEADTEACHER_REVIEW' => [
			'headteacher_approve' => 'CHIEF_ACCOUNTANT_REVIEW',
			'return' => 'RETURNED',
			'reject' => 'REJECTED',
			'cancel' => 'CANCELLED',
		],
		'CHIEF_ACCOUNTANT_REVIEW' => [
			'chief_accountant_approve' => 'DEPUTY_DIRECTOR_REVIEW',
			'return' => 'RETURNED',
			'reject' => 'REJECTED',
			'cancel' => 'CANCELLED',
		],
		'DEPUTY_DIRECTOR_REVIEW' => [
			'approve' => 'APPROVED',
			'reject' => 'REJECTED',
			'return' => 'RETURNED',
			'cancel' => 'CANCELLED',
		],
		'APPROVED' => ['cancel' => 'CANCELLED'],
		'SUBMITTED' => [
			'chief_accountant_approve' => 'DEPUTY_DIRECTOR_REVIEW',
			'procurement_review' => 'BUDGET_MANAGER_REVIEW',
			'return' => 'RETURNED',
			'reject' => 'REJECTED',
			'cancel' => 'CANCELLED',
		],
		'PROCUREMENT_REVIEW' => [
			'budget_review' => 'DEPUTY_DIRECTOR_REVIEW',
			'chief_accountant_approve' => 'DEPUTY_DIRECTOR_REVIEW',
			'return' => 'RETURNED',
			'reject' => 'REJECTED',
			'cancel' => 'CANCELLED',
		],
		'BUDGET_MANAGER_REVIEW' => [
			'budget_review' => 'DEPUTY_DIRECTOR_REVIEW',
			'chief_accountant_approve' => 'DEPUTY_DIRECTOR_REVIEW',
			'return' => 'RETURNED',
			'reject' => 'REJECTED',
			'cancel' => 'CANCELLED',
		],
		'REJECTED' => ['cancel' => 'CANCELLED', 'submit' => 'HEADTEACHER_REVIEW'],
	];

	/** Which permission unlocks each action. */
	private static $actionPerms = [
		'submit' => 'budget.submit',
		'cancel' => 'budget.prepare',
		'chief_accountant_approve' => 'budget.chief_accountant_approve',
		'procurement_review' => 'budget.review_procurement',
		'budget_review' => 'budget.review_budget',
		'approve' => 'budget.final_approve',
		'return' => 'budget.return',
		'reject' => 'budget.reject',
	];

	/** Statuses each review role should see in the Review queue. */
	private static $reviewQueueByPerm = [
		'budget.chief_accountant_approve' => ['HEADTEACHER_REVIEW', 'CHIEF_ACCOUNTANT_REVIEW', 'SUBMITTED'],
		'budget.review_procurement' => ['SUBMITTED'],
		'budget.review_budget' => ['PROCUREMENT_REVIEW', 'BUDGET_MANAGER_REVIEW'],
		'budget.final_approve' => ['HEADTEACHER_REVIEW', 'CHIEF_ACCOUNTANT_REVIEW', 'DEPUTY_DIRECTOR_REVIEW'],
		'budget.return' => ['SUBMITTED', 'HEADTEACHER_REVIEW', 'CHIEF_ACCOUNTANT_REVIEW', 'PROCUREMENT_REVIEW', 'BUDGET_MANAGER_REVIEW', 'DEPUTY_DIRECTOR_REVIEW'],
	];

	public static function approvalChainLabels(): array
	{
		return [
			'submit' => 'Submit',
			'cancel' => 'Cancel',
			'headteacher_approve' => 'Head Teacher',
			'chief_accountant_approve' => 'Chief Accountant',
			'procurement_review' => 'Procurement',
			'budget_review' => 'Budget Manager',
			'approve' => 'Director of Finance',
			'return' => 'Return',
			'reject' => 'Reject',
		];
	}

	private $audit;

	public function __construct()
	{
		$this->audit = new FinancialAuditService();
	}

	public static function permissionForAction(string $action): ?string
	{
		return self::$actionPerms[$action] ?? null;
	}

	/**
	 * Statuses the current user may act on in Review (thin per-role queue).
	 * Users with view_all_branches see the full in-flight set.
	 */
	public static function reviewStatusesForUser(BudgetPermissionService $perms, int $staffId, int $postId): array
	{
		$waiting = ['HEADTEACHER_REVIEW', 'SUBMITTED', 'CHIEF_ACCOUNTANT_REVIEW', 'PROCUREMENT_REVIEW', 'BUDGET_MANAGER_REVIEW', 'DEPUTY_DIRECTOR_REVIEW', 'RETURNED', 'REJECTED'];
		if (\Config\MenuClearance::isDirectorPost($postId)
			|| \Config\MenuClearance::isChiefAccountantPost($postId)
			|| \Config\MenuClearance::isDirectorOfFinancePost($postId)
			|| $perms->can($staffId, $postId, 'budget.view_all_branches')
			|| $perms->can($staffId, $postId, 'budget.final_approve')
			|| $perms->can($staffId, $postId, 'budget.chief_accountant_approve')) {
			return $waiting;
		}
		if (\Config\MenuClearance::isHeadMasterEquivalent($postId)) {
			return ['HEADTEACHER_REVIEW'];
		}
		$statuses = [];
		foreach (self::$reviewQueueByPerm as $perm => $list) {
			if ($perms->can($staffId, $postId, $perm)) {
				$statuses = array_merge($statuses, $list);
			}
		}
		return array_values(array_unique($statuses));
	}

	public static function allowedActionsForStatus(string $status, BudgetPermissionService $perms, int $staffId, int $postId): array
	{
		$actions = array_keys(self::$transitions[$status] ?? []);
		$out = [];
		foreach ($actions as $action) {
			$need = self::$actionPerms[$action] ?? null;
			if ($need && !$perms->can($staffId, $postId, $need)) {
				continue;
			}
			if ($action === 'approve' && $status !== 'DEPUTY_DIRECTOR_REVIEW') {
				continue;
			}
			if ($action === 'headteacher_approve' && !\Config\MenuClearance::isHeadMasterEquivalent($postId)) {
				continue;
			}
			// Director of Finance does the last step only. Chief Accountant does the middle step.
			if ($action === 'chief_accountant_approve' && (int) $postId === 24) {
				continue;
			}
			if ($action === 'approve' && (int) $postId === 28) {
				continue;
			}
			$out[] = $action;
		}
		return $out;
	}

	/**
	 * Review buttons for one budget. The person who prepared it cannot approve it.
	 */
	public static function actionsForBudget(array $budget, BudgetPermissionService $perms, int $staffId, int $postId): array
	{
		$actions = self::allowedActionsForStatus((string) ($budget['status'] ?? ''), $perms, $staffId, $postId);
		if ((int) ($budget['prepared_by'] ?? 0) === $staffId) {
			$actions = array_values(array_filter($actions, static function ($action) {
				return !in_array($action, ['headteacher_approve', 'chief_accountant_approve', 'approve'], true);
			}));
		}
		return $actions;
	}

	/**
	 * Migrate old "BUDGET_MANAGER_REVIEW = waiting for DoF" rows to DEPUTY_DIRECTOR_REVIEW
	 * when Budget Manager already recorded budget_review.
	 */
	public static function normalizeLegacyReviewStatuses(): void
	{
		$db = \Config\Database::connect();
		try {
			$rows = $db->table('budgets')
				->select('id')
				->where('status', 'BUDGET_MANAGER_REVIEW')
				->get()->getResultArray();
			foreach ($rows as $row) {
				$id = (int) ($row['id'] ?? 0);
				if ($id < 1) {
					continue;
				}
				$hasBm = $db->table('budget_approval_actions')
					->where('budget_id', $id)
					->where('action', 'budget_review')
					->countAllResults();
				if ($hasBm > 0) {
					$db->table('budgets')->where('id', $id)->update([
						'status' => 'DEPUTY_DIRECTOR_REVIEW',
						'updated_at' => date('Y-m-d H:i:s'),
					]);
				}
			}
		} catch (\Throwable $e) {
			// Schema may differ on fresh installs; ignore
		}
	}

	/** Short label: who must approve next. */
	public static function pendingApproverLabel(string $status): string
	{
		$map = [
			'DRAFT' => 'Draft — review extracted lines, then submit',
			'SUBMITTED' => 'Waiting for Head Teacher',
			'HEADTEACHER_REVIEW' => 'Waiting for Head Teacher',
			'CHIEF_ACCOUNTANT_REVIEW' => 'Waiting for Chief Accountant',
			'PROCUREMENT_REVIEW' => 'Waiting for Budget Manager',
			'BUDGET_MANAGER_REVIEW' => 'Waiting for Budget Manager',
			'DEPUTY_DIRECTOR_REVIEW' => 'Waiting for Director of Finance',
			'APPROVED' => 'Approved — cash requests can start',
			'RETURNED' => 'Returned — upload a correction or resubmit',
			'REJECTED' => 'Rejected — delete or upload a new file',
			'CANCELLED' => 'Cancelled — upload a new Excel budget',
		];
		return $map[$status] ?? $status;
	}

	/** Statuses where preparers may edit (before / after return). */
	public static function preparerEditableStatuses(): array
	{
		return ['DRAFT', 'RETURNED'];
	}

	/** Statuses already in verification / approved — editable only via budget.edit_submitted (Director of Finance). */
	public static function financeAdjustableStatuses(): array
	{
		return [
			'SUBMITTED',
			'CHIEF_ACCOUNTANT_REVIEW',
			'PROCUREMENT_REVIEW',
			'BUDGET_MANAGER_REVIEW',
			'DEPUTY_DIRECTOR_REVIEW',
			'APPROVED',
			'REJECTED',
		];
	}

	/**
	 * Whether the actor may open the budget workspace for editing amounts.
	 * Preparers: DRAFT / RETURNED. Director of Finance (budget.edit_submitted): submitted & approved.
	 */
	public static function canEditBudgetAmounts(string $status, BudgetPermissionService $perms, int $staffId, int $postId): bool
	{
		if (in_array($status, self::preparerEditableStatuses(), true)) {
			return $perms->can($staffId, $postId, 'budget.prepare')
				|| $perms->can($staffId, $postId, 'budget.edit_own')
				|| $perms->can($staffId, $postId, 'budget.edit_submitted');
		}
		if (in_array($status, self::financeAdjustableStatuses(), true)) {
			return $perms->can($staffId, $postId, 'budget.edit_submitted');
		}
		return false;
	}

	/** True when save is a privileged finance adjustment (not a draft prepare). */
	public static function isFinanceAdjustment(string $status, BudgetPermissionService $perms, int $staffId, int $postId): bool
	{
		return in_array($status, self::financeAdjustableStatuses(), true)
			&& $perms->can($staffId, $postId, 'budget.edit_submitted');
	}

	public function transition($budgetId, $action, $actorId, $postId, $comment = null, ?array $opts = null)
	{
		$db = \Config\Database::connect();
		$b = $db->table('budgets')->where('id', (int) $budgetId)->get(1)->getRowArray();
		if (!$b) {
			return ['success' => false, 'error' => 'Budget not found.'];
		}
		if ($b['status'] === 'APPROVED') {
			return ['success' => false, 'error' => 'Approved budgets are read-only.'];
		}

		$perms = $opts['perms'] ?? new BudgetPermissionService();
		if ($action === 'director_approve') {
			return ['success' => false, 'error' => 'Approval order is the school Head Teacher, then the Chief Accountant, then the Director of Finance.'];
		}
		$need = self::$actionPerms[$action] ?? null;
		if ($action !== 'headteacher_approve' && $need && !$perms->can((int) $actorId, (int) $postId, $need)) {
			return ['success' => false, 'error' => 'You are not allowed to perform this approval step.'];
		}
		if ($action === 'headteacher_approve' && !\Config\MenuClearance::isHeadMasterEquivalent((int) $postId)) {
			return ['success' => false, 'error' => 'Only the head teacher of this school can approve this step.'];
		}

		if (!empty($opts['allowed_branch_ids'])) {
			$allowed = array_map('intval', $opts['allowed_branch_ids']);
			if (!in_array((int) $b['branch_id'], $allowed, true)) {
				return ['success' => false, 'error' => 'This budget belongs to another school/branch.'];
			}
		}

		$current = $b['status'];
		if ($action === 'headteacher_approve' && !$this->actorBelongsToBudgetSchool($db, (int) $actorId, (int) $b['branch_id'])) {
			return ['success' => false, 'error' => 'The head teacher of this school must approve this budget.'];
		}
		if ($action === 'chief_accountant_approve' && (int) $postId !== 28 && !\Config\MenuClearance::isChiefAccountantPost((int) $postId)) {
			return ['success' => false, 'error' => 'Only the Chief Accountant can complete this approval.'];
		}
		if ($action === 'chief_accountant_approve' && !$this->hasApprovalAction($db, (int) $budgetId, 'headteacher_approve')) {
			return ['success' => false, 'error' => 'The head teacher of this school must approve this budget before the Chief Accountant.'];
		}
		if ($action === 'chief_accountant_approve' && (int) ($b['prepared_by'] ?? 0) === (int) $actorId) {
			return ['success' => false, 'error' => 'You prepared this budget. The head teacher approves first, then another Chief Accountant.'];
		}
		if ($action === 'approve' && $current !== 'DEPUTY_DIRECTOR_REVIEW') {
			return ['success' => false, 'error' => 'Director of Finance can approve only after the Head Teacher and the Chief Accountant.'];
		}
		if ($action === 'approve' && (int) ($b['prepared_by'] ?? 0) === (int) $actorId && (int) $postId !== 24) {
			return ['success' => false, 'error' => 'You prepared this budget, so you cannot give the final approval.'];
		}
		$new = self::$transitions[$current][$action] ?? null;
		if (!$new) {
			return ['success' => false, 'error' => 'Invalid step for status ' . $current . '.'];
		}
		if ($action === 'cancel') {
			$cashCount = $db->table('cash_requests')->where('budget_id', (int) $budgetId)->countAllResults();
			if ($cashCount > 0) {
				return ['success' => false, 'error' => 'Cannot cancel: cash requests already use this budget.'];
			}
			$canPrepare = $perms->can((int) $actorId, (int) $postId, 'budget.prepare')
				|| $perms->can((int) $actorId, (int) $postId, 'budget.edit_own');
			$isPreparer = (int) ($b['prepared_by'] ?? 0) === (int) $actorId;
			if (!$isPreparer && !$canPrepare && !$perms->can((int) $actorId, (int) $postId, 'budget.edit_submitted')) {
				return ['success' => false, 'error' => 'Only the person who prepared this budget can cancel it.'];
			}
		}
		if ($action === 'approve') {
			$hasChief = $db->table('budget_approval_actions')
				->where('budget_id', (int) $budgetId)
				->where('action', 'chief_accountant_approve')
				->countAllResults();
			$hasProcurement = $db->table('budget_approval_actions')
				->where('budget_id', (int) $budgetId)
				->where('action', 'procurement_review')
				->countAllResults();
			$hasBudgetMgr = $db->table('budget_approval_actions')
				->where('budget_id', (int) $budgetId)
				->where('action', 'budget_review')
				->countAllResults();
			$legacyOk = $hasProcurement > 0 && $hasBudgetMgr > 0;
			$chiefOk = $hasChief > 0;
			$headOk = $this->hasApprovalAction($db, (int) $budgetId, 'headteacher_approve');
			if (!$headOk) {
				return ['success' => false, 'error' => 'Cannot approve: the head teacher of this school must approve this budget first.'];
			}
			if (!$legacyOk && !$chiefOk) {
				return ['success' => false, 'error' => 'Cannot approve: the Chief Accountant must approve this budget first.'];
			}
		}
		$db->table('budgets')->where('id', (int) $budgetId)->update([
			'status' => $new,
			'updated_by' => $actorId,
			'updated_at' => date('Y-m-d H:i:s'),
		]);
		$db->table('budget_approval_actions')->insert([
			'budget_id' => (int) $budgetId,
			'actor_id' => (int) $actorId,
			'actor_post_id' => (int) $postId,
			'action' => $action,
			'previous_status' => $current,
			'new_status' => $new,
			'comment' => $comment,
			'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
			'created_at' => date('Y-m-d H:i:s'),
		]);
		$this->audit->log('budget', (int) $budgetId, $action, $actorId, ['status' => $current], ['status' => $new], $b['organization_id'], $b['branch_id']);
		return ['success' => true, 'status' => $new];
	}

	/**
	 * Delete a school budget that is not locked by spending.
	 * DRAFT / RETURNED / REJECTED / SUBMITTED always; APPROVED only if no cash requests.
	 */
	public function deleteBudget(int $budgetId, int $actorId, int $postId, array $allowedBranchIds, BudgetPermissionService $perms): array
	{
		$db = \Config\Database::connect();
		$b = $db->table('budgets')->where('id', $budgetId)->get(1)->getRowArray();
		if (!$b) {
			return ['success' => false, 'error' => 'Budget not found.'];
		}
		if (!in_array((int) $b['branch_id'], array_map('intval', $allowedBranchIds), true)) {
			return ['success' => false, 'error' => 'This budget belongs to another school/branch.'];
		}

		$status = $b['status'];
		$canPrepare = $perms->can($actorId, $postId, 'budget.prepare') || $perms->can($actorId, $postId, 'budget.edit_own');
		$canFinal = $perms->can($actorId, $postId, 'budget.final_approve');

		$cashCount = $db->table('cash_requests')->where('budget_id', $budgetId)->countAllResults();
		if ($cashCount > 0) {
			return ['success' => false, 'error' => 'Cannot delete: cash requests already use this budget.'];
		}

		$canChief = $perms->can($actorId, $postId, 'budget.chief_accountant_approve');
		$isDirector = \Config\MenuClearance::isDirectorPost($postId);
		if (!$isDirector && !$canPrepare && !$canFinal && !$canChief) {
			return ['success' => false, 'error' => 'You cannot delete this budget.'];
		}
		if (!$isDirector && $status === 'APPROVED' && !$canPrepare && !$canFinal) {
			return ['success' => false, 'error' => 'You cannot delete this approved budget.'];
		}

		$db->transStart();
		$db->table('budget_lines')->where('budget_id', $budgetId)->delete();
		$db->table('budget_approval_actions')->where('budget_id', $budgetId)->delete();
		try { $db->table('budget_documents')->where('budget_id', $budgetId)->delete(); } catch (\Throwable $e) {}
		try { $db->table('budget_adjustments')->where('budget_id', $budgetId)->delete(); } catch (\Throwable $e) {}
		try { $db->table('ai_budget_suggestions')->where('budget_id', $budgetId)->delete(); } catch (\Throwable $e) {}
		$db->table('budgets')->where('id', $budgetId)->delete();
		$db->transComplete();

		if (!$db->transStatus()) {
			return ['success' => false, 'error' => 'Delete failed.'];
		}
		$this->audit->log('budget', $budgetId, 'delete', $actorId, ['status' => $status, 'title' => $b['title'] ?? ''], [], $b['organization_id'] ?? 0, $b['branch_id'] ?? 0);
		return ['success' => true, 'message' => 'Budget deleted.'];
	}

	/** Budgets already in later steps, with no head-teacher approval, go back to that step. */
	public static function parkUntilHeadTeacher(): void
	{
		static $done = false;
		if ($done) {
			return;
		}
		$done = true;
		$db = \Config\Database::connect();
		try {
			$rows = $db->table('budgets')
				->select('id')
				->whereIn('status', ['SUBMITTED', 'CHIEF_ACCOUNTANT_REVIEW', 'DEPUTY_DIRECTOR_REVIEW', 'PROCUREMENT_REVIEW', 'BUDGET_MANAGER_REVIEW'])
				->get()->getResultArray();
			foreach ($rows as $row) {
				$id = (int) ($row['id'] ?? 0);
				if ($id < 1 || self::hasApprovalAction($db, $id, 'headteacher_approve')) {
					continue;
				}
				$db->table('budgets')->where('id', $id)->update([
					'status' => 'HEADTEACHER_REVIEW',
					'updated_at' => date('Y-m-d H:i:s'),
				]);
			}
		} catch (\Throwable $e) {
		}
	}

	private static function hasApprovalAction($db, int $budgetId, string $action): bool
	{
		try {
			return $db->table('budget_approval_actions')
				->where('budget_id', $budgetId)
				->where('action', $action)
				->countAllResults() > 0;
		} catch (\Throwable $e) {
			return false;
		}
	}

	private function actorBelongsToBudgetSchool($db, int $actorId, int $branchId): bool
	{
		$branch = $db->table('branches')->select('school_id')->where('id', $branchId)->get(1)->getRowArray();
		$budgetSchool = (int) ($branch['school_id'] ?? 0);
		if ($budgetSchool < 1) {
			return false;
		}
		$staff = $db->table('staffs')->select('school_id')->where('id', $actorId)->get(1)->getRowArray();
		$staffSchool = (int) ($staff['school_id'] ?? 0);
		if ($staffSchool < 1) {
			$staffSchool = (int) session('soma_school_id');
		}
		return $staffSchool === $budgetSchool;
	}
}
