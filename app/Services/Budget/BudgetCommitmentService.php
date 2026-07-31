<?php

namespace App\Services\Budget;

class BudgetCommitmentService
{
	private $audit;
	private $avail;

	public function __construct()
	{
		$this->audit = new FinancialAuditService();
		$this->avail = new BudgetAvailabilityService();
	}

	public function createCommitment($cashRequestId, $lineId, $amount, $orgId, $branchId, $actorId, $allowOverride = false, $overrideReason = null)
	{
		$db = \Config\Database::connect();
		$db->transStart();
		$db->query('SELECT id FROM budget_lines WHERE id = ? FOR UPDATE', [(int) $lineId]);
		$availability = $this->avail->lineAvailability($lineId);
		if (!$availability) {
			$db->transRollback();
			return ['success' => false, 'error' => 'Budget line not found.'];
		}
		if ($amount > $availability['available'] && !$allowOverride) {
			$db->transRollback();
			return ['success' => false, 'error' => 'Insufficient budget. Available: ' . number_format($availability['available'], 2)];
		}
		$db->table('budget_commitments')->insert([
			'organization_id' => (int) $orgId,
			'branch_id' => (int) $branchId,
			'budget_line_id' => (int) $lineId,
			'cash_request_id' => (int) $cashRequestId,
			'amount' => round((float) $amount, 2),
			'status' => 'open',
			'created_at' => date('Y-m-d H:i:s'),
		]);
		$id = (int) $db->insertID();
		$this->audit->log('budget_commitment', $id, 'create', $actorId, null, [
			'amount' => $amount,
			'line_id' => $lineId,
			'override' => $allowOverride,
			'override_reason' => $overrideReason,
		], $orgId, $branchId);
		$db->transComplete();
		return ['success' => true, 'commitment_id' => $id];
	}

	public function releaseForRequest($cashRequestId, $actorId)
	{
		$db = \Config\Database::connect();
		$db->table('budget_commitments')
			->where('cash_request_id', (int) $cashRequestId)
			->where('status', 'open')
			->update(['status' => 'released']);
		$this->audit->log('cash_request', (int) $cashRequestId, 'commitments_released', $actorId);
		return true;
	}
}
