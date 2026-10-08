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

		if ($chain === CashRequestApprovalPolicy::CHAIN_WISDOM) {
			$base['HEADTEACHER_APPROVED']['chief_accountant_approve'] = 'CHIEF_ACCOUNTANT_APPROVED';
			$base['CHIEF_ACCOUNTANT_APPROVED'] = [
				'final_approve' => 'FINANCE_AUTHORIZED',
				'return' => 'RETURNED_TO_ACCOUNTANT',
			];
			return $base;
		}

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
			CashRequestApprovalPolicy::CHAIN_WISDOM,
		], true)) {
			$chain = CashRequestApprovalPolicy::CHAIN_FULL;
		}

		// Every new submission follows Accountant → Head Teacher → Chief Accountant → Director of Finance.
		if ($action === 'submit') {
			$chain = CashRequestApprovalPolicy::CHAIN_WISDOM;
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
		if (in_array($action, ['return', 'reject'], true) && trim((string) $comment) === '') {
			return ['success' => false, 'error' => 'A reason is required so the accountant can correct the request.'];
		}
		if (!$this->actionPermitted($action, $postId, $chain)) {
			return ['success' => false, 'error' => 'Permission denied for this action.'];
		}
		$signatureUpdate = [];
		if ($chain === CashRequestApprovalPolicy::CHAIN_WISDOM) {
			$stamped = $this->stampApprovalSignature($action, (int) (session('soma_school_id') ?? 0));
			if (!empty($stamped['error'])) {
				return ['success' => false, 'error' => $stamped['error']];
			}
			if (!empty($stamped['column'])) {
				$signatureUpdate[$stamped['column']] = $stamped['file'];
			}
		}

		$db->transStart();

		$needsCommitment = ($action === 'budget_approve')
			|| ($action === 'final_approve' && in_array($chain, [
				CashRequestApprovalPolicy::CHAIN_SHORT,
				CashRequestApprovalPolicy::CHAIN_MEDIUM,
				CashRequestApprovalPolicy::CHAIN_WISDOM,
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
		if ($signatureUpdate) {
			$update = array_merge($update, $signatureUpdate);
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

	/** @return array{error?:string,column?:string,file?:string} */
	private function stampApprovalSignature(string $action, int $schoolId): array
	{
		$map = [
			'headteacher_approve' => ['column' => 'headteacher_signature', 'school_col' => 'headmaster_signature', 'who' => 'Head Teacher'],
			'chief_accountant_approve' => ['column' => 'chief_accountant_signature', 'school_col' => 'chief_accountant_signature', 'who' => 'Chief Accountant'],
			'final_approve' => ['column' => 'finance_signature', 'school_col' => 'finance_director_signature', 'who' => 'Director of Finance'],
		];
		if (!isset($map[$action])) {
			return [];
		}
		CashRequestApprovalPolicy::ensureSchema();
		if ($schoolId < 1) {
			return ['error' => 'Upload the ' . $map[$action]['who'] . ' signature in School settings before approving.'];
		}
		$row = \Config\Database::connect()->table('schools')->where('id', $schoolId)->get(1)->getRowArray();
		$file = trim((string) ($row[$map[$action]['school_col']] ?? ''));
		if (strlen($file) < 5) {
			return ['error' => 'Upload the ' . $map[$action]['who'] . ' signature in School settings before approving.'];
		}
		return ['column' => $map[$action]['column'], 'file' => $file];
	}

	private function actionPermitted($action, $postId, string $chain = '')
	{
		$postId = (int) $postId;
		if ($chain === CashRequestApprovalPolicy::CHAIN_WISDOM) {
			if ($action === 'headteacher_approve' || ($action === 'return' && $postId !== 28 && $postId !== 24)) {
				return in_array($postId, [1, 18, 25], true);
			}
			if ($action === 'chief_accountant_approve' || ($action === 'return' && $postId === 28)) {
				return $postId === 28;
			}
			if ($action === 'final_approve' || ($action === 'return' && $postId === 24)) {
				return $postId === 24;
			}
		}
		$map = [
			'submit' => 'cash_request.submit',
			'headteacher_approve' => 'cash_request.headteacher_approve',
			'chief_accountant_approve' => 'cash_request.budget_review',
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
		if ($chain === CashRequestApprovalPolicy::CHAIN_WISDOM) {
			$this->notifyWisdomStep($status, $req, $url);
			return;
		}
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

	private function notifyWisdomStep(string $status, array $req, string $url): void
	{
		$db = \Config\Database::connect();
		$branch = $db->table('branches')->where('id', (int) ($req['branch_id'] ?? 0))->get(1)->getRowArray();
		$schoolId = (int) ($branch['school_id'] ?? 0);
		$title = 'Cash request ' . ($req['request_no'] ?? '');
		$notifySchool = function (array $posts, string $body) use ($schoolId, $title, $url, $req) {
			foreach ($posts as $postId) {
				foreach ($this->notify->activeStaffByPost((int) $postId, $schoolId > 0 ? $schoolId : null) as $staff) {
					$this->notify->notifyStaff((int) $staff['id'], $title, $body, $url, $req['branch_id'] ?? null);
				}
			}
		};
		if ($status === 'SUBMITTED') {
			$notifySchool([1, 18, 25], 'The accountant submitted a request. Approve it or send it back with a reason.');
			return;
		}
		if ($status === 'HEADTEACHER_APPROVED') {
			foreach ($this->notify->activeStaffByPost(28) as $staff) {
				$this->notify->notifyStaff((int) $staff['id'], $title, 'Head Teacher approved. Chief Accountant review is next.', $url, $req['branch_id'] ?? null);
			}
			return;
		}
		if ($status === 'CHIEF_ACCOUNTANT_APPROVED') {
			foreach ($this->notify->activeStaffByPost(24) as $staff) {
				$this->notify->notifyStaff((int) $staff['id'], $title, 'Chief Accountant approved. Director of Finance gives the final approval.', $url, $req['branch_id'] ?? null);
			}
			return;
		}
		if ($status === 'RETURNED_TO_ACCOUNTANT') {
			$notifySchool([8, 9], 'The request was sent back with a reason. Correct it and submit again.');
			$notifySchool([1, 18, 25], 'A cash request was returned. The accountant will correct it and send it again.');
			return;
		}
		if ($status === 'FINANCE_AUTHORIZED') {
			$notifySchool([8, 9], 'Director of Finance approved. The money for this request is allowed.');
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
		if ($chain === CashRequestApprovalPolicy::CHAIN_WISDOM) {
			$postId = (int) ($_SESSION['soma_post'] ?? 0);
			$out = [];
			if ($status === 'SUBMITTED' && in_array($postId, [1, 18, 25], true)) {
				$out['headteacher_approve'] = 'Approve — Head Teacher';
				$out['return'] = 'Reject and send back to the accountant';
			}
			if ($status === 'HEADTEACHER_APPROVED' && $postId === 28) {
				$out['chief_accountant_approve'] = 'Approve — Chief Accountant';
				$out['return'] = 'Reject and send back with a reason';
			}
			if ($status === 'CHIEF_ACCOUNTANT_APPROVED' && $postId === 24) {
				$out['final_approve'] = 'Final approval — Director of Finance';
				$out['return'] = 'Return with a reason';
			}
			return $out;
		}
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
