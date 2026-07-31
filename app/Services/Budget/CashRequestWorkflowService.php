<?php

namespace App\Services\Budget;

class CashRequestWorkflowService
{
	private static $transitions = [
		'DRAFT' => ['submit' => 'SUBMITTED'],
		'SUBMITTED' => [
			'headteacher_approve' => 'HEADTEACHER_APPROVED',
			'procurement_approve' => 'PROCUREMENT_APPROVED',
			'return' => 'RETURNED_TO_ACCOUNTANT',
			'reject' => 'REJECTED',
		],
		'HEADTEACHER_APPROVED' => [
			'procurement_approve' => 'PROCUREMENT_APPROVED',
			'return' => 'RETURNED_TO_ACCOUNTANT',
		],
		'PROCUREMENT_APPROVED' => [
			'budget_approve' => 'BUDGET_APPROVED',
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

	public function transition($requestId, $action, $actorId, $postId, $comment = null, $extra = [])
	{
		$db = \Config\Database::connect();
		$req = $db->table('cash_requests')->where('id', (int) $requestId)->get(1)->getRowArray();
		if (!$req) {
			return ['success' => false, 'error' => 'Request not found.'];
		}
		$current = $req['status'];
		$map = self::$transitions[$current] ?? [];
		if (!isset($map[$action])) {
			return ['success' => false, 'error' => "Action '$action' not allowed from status $current."];
		}
		$newStatus = $map[$action];
		if (!$this->actionPermitted($action, $postId)) {
			return ['success' => false, 'error' => 'Permission denied for this action.'];
		}
		if ($action === 'submit' && (int) $req['created_by'] === (int) $actorId && $newStatus !== 'RETURNED_TO_ACCOUNTANT') {
			// accountant submitting own — ok
		}
		$db->transStart();
		if ($action === 'budget_approve') {
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
		$this->audit->log('cash_request', (int) $requestId, $action, $actorId, ['status' => $current], ['status' => $newStatus], $req['organization_id'], $req['branch_id']);
		$this->notifyNextStep($newStatus, $req);
		$db->transComplete();
		return ['success' => true, 'status' => $newStatus];
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

	private function notifyNextStep($status, $req)
	{
		$url = base_url('budget/cash_request_view/' . $req['id']);
		$postMap = [
			'SUBMITTED' => 20,
			'HEADTEACHER_APPROVED' => 20,
			'PROCUREMENT_APPROVED' => 19,
			'BUDGET_APPROVED' => 21,
			'FINANCE_AUTHORIZED' => 22,
			'PAID' => 9,
		];
		if (isset($postMap[$status])) {
			$this->notify->notifyPost($postMap[$status], 'Cash request ' . $req['request_no'], 'Status: ' . $status, $url, $req['branch_id']);
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
}
