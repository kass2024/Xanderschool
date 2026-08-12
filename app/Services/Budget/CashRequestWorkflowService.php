<?php

namespace App\Services\Budget;

class CashRequestWorkflowService
{
	private $perms;
	private $audit;
	private $notify;
	private $commitments;

	public function __construct()
	{
		$this->perms = new BudgetPermissionService();
		$this->audit = new FinancialAuditService();
		$this->notify = new BudgetNotificationService();
		$this->commitments = new BudgetCommitmentService();
	}

	/**
	 * Base transitions; approval_chain narrows which forward actions are valid.
	 */
	private function transitionsFor(string $chain): array
	{
		$base = [
			'DRAFT' => ['submit' => 'SUBMITTED'],
			'SUBMITTED' => [
				'headteacher_approve' => 'HEADTEACHER_APPROVED',
				'return' => 'RETURNED_TO_ACCOUNTANT',
				'reject' => 'REJECTED',
			],
			'HEADTEACHER_APPROVED' => [
				'return' => 'RETURNED_TO_ACCOUNTANT',
			],
			'PROCUREMENT_APPROVED' => [
				'return' => 'RETURNED_TO_ACCOUNTANT',
			],
			'BUDGET_APPROVED' => [
				'final_approve' => 'FINANCE_AUTHORIZED',
				'return' => 'RETURNED_TO_ACCOUNTANT',
				'reject' => 'REJECTED',
			],
			'FINANCE_AUTHORIZED' => [
				'pay' => 'PAID',
				'partial_pay' => 'PARTIALLY_PAID',
			],
			'PARTIALLY_PAID' => [
				'pay' => 'PAID',
				'partial_pay' => 'PARTIALLY_PAID',
			],
			'PAID' => ['confirm_receipt' => 'RECEIPT_CONFIRMED'],
			'RECEIPT_CONFIRMED' => ['close' => 'CLOSED'],
			'RETURNED_TO_ACCOUNTANT' => ['submit' => 'SUBMITTED', 'cancel' => 'CANCELLED'],
		];

		if ($chain === CashRequestApprovalPolicy::CHAIN_SHORT) {
			$base['HEADTEACHER_APPROVED']['final_approve'] = 'FINANCE_AUTHORIZED';
			$base['HEADTEACHER_APPROVED']['reject'] = 'REJECTED';
		} elseif ($chain === CashRequestApprovalPolicy::CHAIN_MEDIUM) {
			$base['HEADTEACHER_APPROVED']['procurement_approve'] = 'PROCUREMENT_APPROVED';
			$base['PROCUREMENT_APPROVED']['final_approve'] = 'FINANCE_AUTHORIZED';
			$base['PROCUREMENT_APPROVED']['reject'] = 'REJECTED';
		} else {
			$base['HEADTEACHER_APPROVED']['procurement_approve'] = 'PROCUREMENT_APPROVED';
			$base['PROCUREMENT_APPROVED']['budget_approve'] = 'BUDGET_APPROVED';
		}

		return $base;
	}

	public function transition($requestId, $action, $actorId, $postId, $comment = null, $extra = [])
	{
		CashRequestApprovalPolicy::ensureSchema();
		$db = \Config\Database::connect();
		$req = $db->table('cash_requests')->where('id', (int) $requestId)->get(1)->getRowArray();
		if (!$req) {
			return ['success' => false, 'error' => 'Request not found.'];
		}

		$chain = strtolower(trim((string) ($req['approval_chain'] ?? CashRequestApprovalPolicy::CHAIN_FULL)));
		if (!in_array($chain, [
			CashRequestApprovalPolicy::CHAIN_SHORT,
			CashRequestApprovalPolicy::CHAIN_MEDIUM,
			CashRequestApprovalPolicy::CHAIN_FULL,
		], true)) {
			$chain = CashRequestApprovalPolicy::CHAIN_FULL;
		}

		// On submit: lock the chain from amount + master settings
		if ($action === 'submit') {
			$resolved = CashRequestApprovalPolicy::resolveChain(
				(int) ($req['organization_id'] ?? 0),
				(float) ($req['requested_amount'] ?? 0)
			);
			$chain = $resolved['chain'];
			$db->table('cash_requests')->where('id', (int) $requestId)->update([
				'approval_chain' => $chain,
				'updated_at' => date('Y-m-d H:i:s'),
			]);
			$req['approval_chain'] = $chain;
		}

		$current = $req['status'];
		$map = $this->transitionsFor($chain)[$current] ?? [];
		if (!isset($map[$action])) {
			return ['success' => false, 'error' => "Action '$action' not allowed from status $current for this amount chain."];
		}
		$newStatus = $map[$action];
		if (!$this->actionPermitted($action, $postId)) {
			return ['success' => false, 'error' => 'Permission denied for this action.'];
		}

		$db->transStart();

		$needsCommitment = ($action === 'budget_approve')
			|| ($action === 'final_approve' && in_array($chain, [
				CashRequestApprovalPolicy::CHAIN_SHORT,
				CashRequestApprovalPolicy::CHAIN_MEDIUM,
			], true));

		if ($needsCommitment) {
			$existingOpen = (int) $db->table('budget_commitments')
				->where('cash_request_id', (int) $requestId)
				->where('status', 'open')
				->countAllResults();
			if ($existingOpen < 1) {
				$lines = $db->table('cash_request_lines')->where('cash_request_id', (int) $requestId)->get()->getResultArray();
				$override = !empty($extra['override']) && $this->perms->can($actorId, $postId, 'cash_request.override_budget');
				foreach ($lines as $ln) {
					if (empty($ln['budget_line_id'])) {
						continue;
					}
					$res = $this->commitments->createCommitment(
						$requestId,
						$ln['budget_line_id'],
						$ln['amount'],
						$req['organization_id'],
						$req['branch_id'],
						$actorId,
						$override,
						$extra['override_reason'] ?? null
					);
					if (!$res['success']) {
						$db->transRollback();
						return $res;
					}
				}
			}
		}
		if ($action === 'reject') {
			$this->commitments->releaseForRequest($requestId, $actorId);
		}
		$update = [
			'status' => $newStatus,
			'updated_by' => $actorId,
			'updated_at' => date('Y-m-d H:i:s'),
		];
		if ($action === 'final_approve') {
			$update['authorized_amount'] = $req['requested_amount'];
		}
		if ($action === 'submit') {
			$update['approval_chain'] = $chain;
		}
		$db->table('cash_requests')->where('id', (int) $requestId)->update($update);
		$db->table('cash_request_actions')->insert([
			'cash_request_id' => (int) $requestId,
			'actor_id' => (int) $actorId,
			'actor_post_id' => (int) $postId,
			'action' => $action,
			'previous_status' => $current,
			'new_status' => $newStatus,
			'comment' => $comment,
			'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
			'created_at' => date('Y-m-d H:i:s'),
		]);
		$this->audit->log('cash_request', (int) $requestId, $action, $actorId, ['status' => $current], ['status' => $newStatus, 'chain' => $chain], $req['organization_id'], $req['branch_id']);
		$this->notifyNextStep($newStatus, $req, $chain);
		$db->transComplete();
		return ['success' => true, 'status' => $newStatus, 'approval_chain' => $chain];
	}

	private function actionPermitted($action, $postId)
	{
		$map = [
			'submit' => 'cash_request.submit',
			'headteacher_approve' => 'cash_request.headteacher_approve',
			'procurement_approve' => 'cash_request.procurement_review',
			'budget_approve' => 'cash_request.budget_review',
			'final_approve' => 'cash_request.final_approve',
			'return' => 'cash_request.return',
			'reject' => 'cash_request.reject',
			'pay' => 'cash_request.process_payment',
			'partial_pay' => 'cash_request.process_payment',
			'confirm_receipt' => 'cash_request.confirm_receipt',
			'close' => 'cash_request.close',
			'cancel' => 'cash_request.cancel',
		];
		$key = $map[$action] ?? null;
		return $key ? $this->perms->can((int) session('soma_id'), $postId, $key) : false;
	}

	private function notifyNextStep($status, $req, string $chain = CashRequestApprovalPolicy::CHAIN_FULL)
	{
		$url = base_url('budget/cash_request_view/' . $req['id']);
		$postMap = [
			'SUBMITTED' => 1, // Headmaster first
			'HEADTEACHER_APPROVED' => ($chain === CashRequestApprovalPolicy::CHAIN_SHORT) ? 24 : 20,
			'PROCUREMENT_APPROVED' => ($chain === CashRequestApprovalPolicy::CHAIN_MEDIUM) ? 24 : 19,
			'BUDGET_APPROVED' => 24,
			'FINANCE_AUTHORIZED' => 22,
			'PAID' => 9,
		];
		if (isset($postMap[$status])) {
			$this->notify->notifyPost($postMap[$status], 'Cash request ' . $req['request_no'], 'Status: ' . $status, $url, $req['branch_id']);
		}
		// Also ping headmistress on submit
		if ($status === 'SUBMITTED') {
			$this->notify->notifyPost(18, 'Cash request ' . $req['request_no'], 'Awaiting headmaster approval', $url, $req['branch_id']);
		}
	}

	public function nextRequestNo($branchId)
	{
		$db = \Config\Database::connect();
		$year = (int) date('Y');
		$db->transStart();
		$row = $db->query(
			'SELECT * FROM cash_request_sequences WHERE branch_id = ? AND year = ? FOR UPDATE',
			[(int) $branchId, $year]
		)->getRowArray();
		if (!$row) {
			$db->table('cash_request_sequences')->insert([
				'branch_id' => (int) $branchId,
				'year' => $year,
				'last_sequence' => 1,
			]);
			$seq = 1;
		} else {
			$seq = (int) $row['last_sequence'] + 1;
			$db->table('cash_request_sequences')->where('id', $row['id'])->update(['last_sequence' => $seq]);
		}
		$branch = $db->table('branches')->where('id', (int) $branchId)->get(1)->getRowArray();
		$code = $branch['branch_code'] ?? 'BR';
		$db->transComplete();
		return sprintf('CR/%s/%d/%04d', $code, $year, $seq);
	}

	/** UI helper: approve/return/reject buttons for current user context. */
	public static function uiActionsForRequest(array $req): array
	{
		$chain = strtolower(trim((string) ($req['approval_chain'] ?? CashRequestApprovalPolicy::CHAIN_FULL))) ?: CashRequestApprovalPolicy::CHAIN_FULL;
		$status = (string) ($req['status'] ?? '');
		$labels = [
			'headteacher_approve' => 'Headmaster approve',
			'procurement_approve' => 'Procurement approve',
			'budget_approve' => 'Budget Manager — confirm availability',
			'final_approve' => 'Director of Finance — authorize payment',
			'return' => 'Return to requester',
			'reject' => 'Reject',
		];
		$actions = CashRequestApprovalPolicy::allowedApproveActions($chain, $status);
		$out = [];
		foreach ($actions as $key) {
			$out[$key] = $labels[$key] ?? $key;
		}
		if (in_array($status, ['SUBMITTED', 'HEADTEACHER_APPROVED', 'PROCUREMENT_APPROVED', 'BUDGET_APPROVED'], true)) {
			$out['return'] = $labels['return'];
		}
		if (in_array($status, ['SUBMITTED', 'BUDGET_APPROVED'], true)
			|| ($status === 'HEADTEACHER_APPROVED' && $chain === CashRequestApprovalPolicy::CHAIN_SHORT)
			|| ($status === 'PROCUREMENT_APPROVED' && $chain === CashRequestApprovalPolicy::CHAIN_MEDIUM)) {
			$out['reject'] = $labels['reject'];
		}
		return $out;
	}
}
