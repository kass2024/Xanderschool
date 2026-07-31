<?php

namespace App\Services\Budget;

class PaymentService
{
	private $audit;
	private $workflow;

	public function __construct()
	{
		$this->audit = new FinancialAuditService();
		$this->workflow = new CashRequestWorkflowService();
	}

	public function recordPayment($requestId, $amount, $method, $reference, $paymentDate, $actorId, $postId)
	{
		$db = \Config\Database::connect();
		$req = $db->table('cash_requests')->where('id', (int) $requestId)->get(1)->getRowArray();
		if (!$req) {
			return ['success' => false, 'error' => 'Request not found.'];
		}
		if (!in_array($req['status'], ['FINANCE_AUTHORIZED', 'PARTIALLY_PAID'], true)) {
			return ['success' => false, 'error' => 'Request is not authorized for payment.'];
		}
		$authorized = (float) ($req['authorized_amount'] ?? $req['requested_amount']);
		$paidSoFar = (float) $req['paid_amount'];
		$newTotal = round($paidSoFar + (float) $amount, 2);
		if ($newTotal > $authorized + 0.001) {
			return ['success' => false, 'error' => 'Payment exceeds authorized amount.'];
		}
		$dup = $db->table('cash_request_payments')->where('payment_reference', $reference)->countAllResults();
		if ($dup) {
			return ['success' => false, 'error' => 'Duplicate payment reference.'];
		}
		$db->transStart();
		$db->table('cash_request_payments')->insert([
			'cash_request_id' => (int) $requestId,
			'payment_date' => $paymentDate,
			'amount' => round((float) $amount, 2),
			'payment_method' => $method,
			'payment_reference' => $reference,
			'status' => 'completed',
			'processed_by' => (int) $actorId,
			'created_at' => date('Y-m-d H:i:s'),
		]);
		$paymentId = (int) $db->insertID();
		$status = $newTotal >= $authorized ? 'PAID' : 'PARTIALLY_PAID';
		$db->table('cash_requests')->where('id', (int) $requestId)->update([
			'paid_amount' => $newTotal,
			'status' => $status,
			'updated_at' => date('Y-m-d H:i:s'),
		]);
		if ($status === 'PAID') {
			$db->table('budget_commitments')
				->where('cash_request_id', (int) $requestId)->where('status', 'open')
				->update(['status' => 'paid']);
		}
		$this->audit->log('cash_request_payment', $paymentId, 'record', $actorId, null, [
			'request_id' => $requestId,
			'amount' => $amount,
			'reference' => $reference,
		], $req['organization_id'], $req['branch_id']);
		$db->transComplete();
		return ['success' => true, 'payment_id' => $paymentId, 'status' => $status];
	}

	public function reversePayment($paymentId, $reason, $actorId, $postId)
	{
		$db = \Config\Database::connect();
		$pay = $db->table('cash_request_payments')->where('id', (int) $paymentId)->get(1)->getRowArray();
		if (!$pay || $pay['status'] !== 'completed') {
			return ['success' => false, 'error' => 'Payment not found or already reversed.'];
		}
		$db->transStart();
		$db->table('cash_request_payments')->where('id', (int) $paymentId)->update([
			'status' => 'reversed',
			'reversal_reason' => $reason,
		]);
		$req = $db->table('cash_requests')->where('id', (int) $pay['cash_request_id'])->get(1)->getRowArray();
		$newPaid = max(0, round((float) $req['paid_amount'] - (float) $pay['amount'], 2));
		$db->table('cash_requests')->where('id', (int) $pay['cash_request_id'])->update([
			'paid_amount' => $newPaid,
			'status' => $newPaid > 0 ? 'PARTIALLY_PAID' : 'FINANCE_AUTHORIZED',
		]);
		$this->audit->log('cash_request_payment', (int) $paymentId, 'reverse', $actorId, $pay, ['reason' => $reason]);
		$db->transComplete();
		return ['success' => true];
	}
}
