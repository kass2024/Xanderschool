<?php

namespace App\Services\Budget;

class BudgetWorkflowService
{
	private static $transitions = [
		'DRAFT' => ['submit' => 'SUBMITTED'],
		'SUBMITTED' => ['procurement_review' => 'PROCUREMENT_REVIEW', 'return' => 'RETURNED', 'reject' => 'REJECTED'],
		'PROCUREMENT_REVIEW' => ['budget_review' => 'BUDGET_MANAGER_REVIEW', 'return' => 'RETURNED', 'reject' => 'REJECTED'],
		'BUDGET_MANAGER_REVIEW' => ['final_review' => 'DEPUTY_DIRECTOR_REVIEW', 'return' => 'RETURNED', 'reject' => 'REJECTED'],
		'DEPUTY_DIRECTOR_REVIEW' => ['approve' => 'APPROVED', 'reject' => 'REJECTED', 'return' => 'RETURNED'],
		'RETURNED' => ['submit' => 'SUBMITTED'],
	];

	/** Thin school budget chain: which permission unlocks each action. */
	private static $actionPerms = [
		'submit' => 'budget.submit',
		'procurement_review' => 'budget.review_procurement',
		'budget_review' => 'budget.review_budget',
		'final_review' => 'budget.review_budget',
		'approve' => 'budget.final_approve',
		'return' => 'budget.return',
		'reject' => 'budget.reject',
	];

	/** Statuses each review role should see in the Review queue. */
	private static $reviewQueueByPerm = [
		'budget.review_procurement' => ['SUBMITTED'],
		'budget.review_budget' => ['PROCUREMENT_REVIEW', 'BUDGET_MANAGER_REVIEW'],
		'budget.final_approve' => ['DEPUTY_DIRECTOR_REVIEW'],
		'budget.return' => ['SUBMITTED', 'PROCUREMENT_REVIEW', 'BUDGET_MANAGER_REVIEW', 'DEPUTY_DIRECTOR_REVIEW'],
	];

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
		if ($perms->can($staffId, $postId, 'budget.view_all_branches')
			|| $perms->can($staffId, $postId, 'budget.final_approve')) {
			return ['SUBMITTED', 'PROCUREMENT_REVIEW', 'BUDGET_MANAGER_REVIEW', 'DEPUTY_DIRECTOR_REVIEW', 'RETURNED', 'REJECTED'];
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
			$out[] = $action;
		}
		return $out;
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
		$need = self::$actionPerms[$action] ?? null;
		if ($need && !$perms->can((int) $actorId, (int) $postId, $need)) {
			return ['success' => false, 'error' => 'You are not allowed to perform this approval step.'];
		}

		if (!empty($opts['allowed_branch_ids'])) {
			$allowed = array_map('intval', $opts['allowed_branch_ids']);
			if (!in_array((int) $b['branch_id'], $allowed, true)) {
				return ['success' => false, 'error' => 'This budget belongs to another school/branch.'];
			}
		}

		$current = $b['status'];
		$new = self::$transitions[$current][$action] ?? null;
		if (!$new) {
			return ['success' => false, 'error' => 'Invalid step for status ' . $current . '.'];
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

		$softStatuses = ['DRAFT', 'RETURNED', 'REJECTED', 'SUBMITTED', 'CANCELLED'];
		if (in_array($status, $softStatuses, true)) {
			if (!$canPrepare && !$canFinal) {
				return ['success' => false, 'error' => 'You cannot delete this budget.'];
			}
		} elseif ($status === 'APPROVED') {
			if (!$canFinal) {
				return ['success' => false, 'error' => 'Only finance final approver can delete an unused approved budget.'];
			}
		} else {
			// In review pipeline — preparer or final approver may withdraw if no spending
			if (!$canPrepare && !$canFinal) {
				return ['success' => false, 'error' => 'You cannot delete a budget in review.'];
			}
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
}
