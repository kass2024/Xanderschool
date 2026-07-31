<?php

namespace App\Services\Budget;

class BudgetWorkflowService
{
	private static $transitions = [
		'DRAFT' => ['submit' => 'SUBMITTED'],
		'SUBMITTED' => ['procurement_review' => 'PROCUREMENT_REVIEW', 'return' => 'RETURNED', 'reject' => 'REJECTED'],
		'PROCUREMENT_REVIEW' => ['budget_review' => 'BUDGET_MANAGER_REVIEW', 'return' => 'RETURNED'],
		'BUDGET_MANAGER_REVIEW' => ['final_review' => 'DEPUTY_DIRECTOR_REVIEW', 'return' => 'RETURNED'],
		'DEPUTY_DIRECTOR_REVIEW' => ['approve' => 'APPROVED', 'reject' => 'REJECTED', 'return' => 'RETURNED'],
		'RETURNED' => ['submit' => 'SUBMITTED'],
	];

	private $audit;

	public function __construct()
	{
		$this->audit = new FinancialAuditService();
	}

	public function transition($budgetId, $action, $actorId, $postId, $comment = null)
	{
		$db = \Config\Database::connect();
		$b = $db->table('budgets')->where('id', (int) $budgetId)->get(1)->getRowArray();
		if (!$b) {
			return ['success' => false, 'error' => 'Budget not found.'];
		}
		if ($b['status'] === 'APPROVED') {
			return ['success' => false, 'error' => 'Approved budgets are read-only.'];
		}
		$current = $b['status'];
		$new = self::$transitions[$current][$action] ?? null;
		if (!$new) {
			return ['success' => false, 'error' => 'Invalid transition.'];
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
}
