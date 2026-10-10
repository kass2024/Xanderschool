<?php

namespace App\Services\Pocket;

use App\Models\PaymentModel;
use App\Services\Besoft\BesoftGatewayClient;
use App\Services\Mopay\MopayGatewayClient;

/**
 * Parent pocket-money wallet.
 * Balance lives on students.wallet_balance and is spent later by the student card (POS).
 * Mobile Money collections and send-backs use BeSoft Pay (MTN MoMo).
 */
class PocketWalletService
{
	const MIN_AMOUNT = 200;
	const MAX_AMOUNT = 500000;
	const TYPE_TOPUP = 0;
	const TYPE_PAYMENT = 1;
	const TYPE_WITHDRAW = 2;
	const TYPE_REFUND = 3;

	/** @var \CodeIgniter\Database\BaseConnection */
	protected $db;

	/** @var bool */
	protected static $ready = false;

	public function __construct()
	{
		$this->db = \Config\Database::connect();
		$this->ensureSchema();
	}

	public function health(): array
	{
		$gateway = BesoftGatewayClient::make();

		return [
			'ok' => true,
			'service' => 'Xander Pocket',
			'currency' => 'RWF',
			'mobileMoney' => $gateway->isConfigured(),
			'chargePercent' => $gateway->chargePercent(),
			'minAmount' => self::MIN_AMOUNT,
			'maxAmount' => self::MAX_AMOUNT,
		];
	}

	public function register(string $name, string $phone, string $pin): array
	{
		$name = $this->cleanName($name);
		$phone = $this->requireMsisdn($phone);
		$this->requirePin($pin);
		$exists = $this->db->query('SELECT id FROM pocket_parents WHERE phone = ? LIMIT 1', [$phone])->getRow();
		if ($exists) {
			throw new \RuntimeException('This phone already has a Pocket account. Sign in instead.', 409);
		}
		$now = $this->now();
		$this->db->table('pocket_parents')->insert([
			'phone' => $phone,
			'name' => $name,
			'pin_hash' => password_hash($pin, PASSWORD_DEFAULT),
			'failed_count' => 0,
			'locked_until' => null,
			'status' => 1,
			'created_at' => $now,
			'updated_at' => $now,
		]);
		$id = (int) $this->db->insertID();

		return $this->issueSession($id);
	}

	public function login(string $phone, string $pin): array
	{
		$phone = $this->requireMsisdn($phone);
		$this->requirePin($pin);
		$parent = $this->db->query('SELECT * FROM pocket_parents WHERE phone = ? LIMIT 1', [$phone])->getRowArray();
		if (!$parent || (int) $parent['status'] !== 1) {
			throw new \RuntimeException('Phone or PIN is not correct.', 401);
		}
		if (!empty($parent['locked_until']) && strtotime((string) $parent['locked_until']) > time()) {
			throw new \RuntimeException('Too many attempts. Try again in a few minutes.', 429);
		}
		if (!password_verify($pin, (string) $parent['pin_hash'])) {
			$fails = (int) $parent['failed_count'] + 1;
			$lock = $fails >= 5 ? date('Y-m-d H:i:s', time() + 900) : null;
			$this->db->table('pocket_parents')->where('id', (int) $parent['id'])->update([
				'failed_count' => $fails >= 5 ? 0 : $fails,
				'locked_until' => $lock,
				'updated_at' => $this->now(),
			]);
			throw new \RuntimeException('Phone or PIN is not correct.', 401);
		}
		$this->db->table('pocket_parents')->where('id', (int) $parent['id'])->update([
			'failed_count' => 0,
			'locked_until' => null,
			'updated_at' => $this->now(),
		]);

		return $this->issueSession((int) $parent['id']);
	}

	/**
	 * Store a one-time PIN reset code. The controller sends it with the school SMS API.
	 *
	 * @return array{phone:string,message:string,schoolId:int}
	 */
	public function preparePinReset(string $phone): array
	{
		$phone = $this->requireMsisdn($phone);
		$parent = $this->db->query('SELECT id, status, reset_expires FROM pocket_parents WHERE phone = ? LIMIT 1', [$phone])->getRowArray();
		if (!$parent || (int) $parent['status'] !== 1) {
			throw new \RuntimeException('No Pocket account uses that phone. Create one instead.', 404);
		}
		if (!empty($parent['reset_expires']) && strtotime((string) $parent['reset_expires']) > time() + 540) {
			throw new \RuntimeException('A code was just sent. Wait a minute, then try again.', 429);
		}
		$code = (string) random_int(100000, 999999);
		$this->db->table('pocket_parents')->where('id', (int) $parent['id'])->update([
			'reset_hash' => password_hash($code, PASSWORD_DEFAULT),
			'reset_expires' => date('Y-m-d H:i:s', time() + 600),
			'updated_at' => $this->now(),
		]);

		return [
			'phone' => $phone,
			'message' => 'Xander Pocket code: ' . $code . '. It expires in 10 minutes. Do not share it.',
			'schoolId' => $this->pocketSmsSchoolId($phone),
		];
	}

	public function clearPinReset(string $phone): void
	{
		$phone = $this->requireMsisdn($phone);
		$this->db->table('pocket_parents')->where('phone', $phone)->update([
			'reset_hash' => null,
			'reset_expires' => null,
			'updated_at' => $this->now(),
		]);
	}

	public function confirmPinReset(string $phone, string $code, string $pin): array
	{
		$phone = $this->requireMsisdn($phone);
		$this->requirePin($pin);
		$code = trim($code);
		if (!preg_match('/^\d{6}$/', $code)) {
			throw new \InvalidArgumentException('Enter the 6-digit code from the SMS.');
		}
		$parent = $this->db->query('SELECT * FROM pocket_parents WHERE phone = ? LIMIT 1', [$phone])->getRowArray();
		if (!$parent || (int) $parent['status'] !== 1) {
			throw new \RuntimeException('No Pocket account uses that phone.', 404);
		}
		$expires = strtotime((string) ($parent['reset_expires'] ?? ''));
		if ($expires === false || $expires < time() || !password_verify($code, (string) ($parent['reset_hash'] ?? ''))) {
			throw new \RuntimeException('That code is not correct or it has expired.', 401);
		}
		$this->db->table('pocket_parents')->where('id', (int) $parent['id'])->update([
			'pin_hash' => password_hash($pin, PASSWORD_DEFAULT),
			'failed_count' => 0,
			'locked_until' => null,
			'reset_hash' => null,
			'reset_expires' => null,
			'updated_at' => $this->now(),
		]);

		return $this->issueSession((int) $parent['id']);
	}

	public function logout(string $token): void
	{
		$hash = hash('sha256', trim($token));
		$this->db->table('pocket_sessions')->where('token_hash', $hash)->delete();
	}

	public function parentByToken(string $token): ?array
	{
		$token = trim($token);
		if (strlen($token) < 20) {
			return null;
		}
		$row = $this->db->query(
			'SELECT p.id, p.name, p.phone, p.role FROM pocket_sessions s
			 INNER JOIN pocket_parents p ON p.id = s.parent_id
			 WHERE s.token_hash = ? AND s.expires_at > ? AND p.status = 1 LIMIT 1',
			[hash('sha256', $token), $this->now()]
		)->getRowArray();

		return $row ? [
			'id' => (int) $row['id'],
			'name' => (string) $row['name'],
			'phone' => (string) $row['phone'],
			'role' => (string) ($row['role'] ?? 'parent'),
		] : null;
	}

	public function findAsYouType(string $regno, int $parentId): array
	{
		$regno = strtoupper(preg_replace('/\s+/', '', trim($regno)));
		if ($regno === null || strlen($regno) < 4 || strlen($regno) > 40) {
			return ['student' => null, 'matches' => []];
		}
		$exact = $this->studentByRegno($regno);
		$rows = $exact ? [$exact] : $this->studentsByPrefix($regno);
		$matches = [];
		foreach ($rows as $student) {
			$linked = $this->db->query(
				'SELECT id FROM pocket_links WHERE parent_id = ? AND student_id = ? LIMIT 1',
				[$parentId, (int) $student['id']]
			)->getRow();
			$public = $this->publicStudent($student, $linked || $this->isAgent($parentId));
			$public['linked'] = (bool) $linked;
			$matches[] = $public;
		}
		$chosen = null;
		if ($exact && isset($matches[0])) {
			$chosen = $matches[0];
		}

		return ['student' => $chosen, 'matches' => $matches];
	}

	public function lookup(string $regno, int $parentId): array
	{
		$found = $this->findAsYouType($regno, $parentId);
		if ($found['student'] === null) {
			throw new \RuntimeException('No active student uses that registration number.', 404);
		}

		return $found['student'];
	}

	public function link(int $parentId, int $studentId, string $spendPin): array
	{
		$student = $this->studentById($studentId);
		if (!$student) {
			throw new \RuntimeException('Student not found.', 404);
		}
		$this->savedCard($student);
		$now = $this->now();
		$linked = $this->db->query(
			'SELECT id FROM pocket_links WHERE parent_id = ? AND student_id = ? LIMIT 1',
			[$parentId, $studentId]
		)->getRow();
		if (!$linked) {
			$this->db->table('pocket_links')->insert([
				'parent_id' => $parentId,
				'student_id' => $studentId,
				'created_at' => $now,
			]);
		}
		$fresh = $this->studentById($studentId);

		return $this->publicStudent($fresh, true);
	}

	public function unlink(int $parentId, int $studentId): void
	{
		$this->assertLinked($parentId, $studentId);
		$this->db->table('pocket_links')->where('parent_id', $parentId)->where('student_id', $studentId)->delete();
	}

	public function wallets(int $parentId): array
	{
		if ($this->isAgent($parentId)) {
			$rows = $this->db->query(
				'SELECT id AS student_id FROM students WHERE status = 1 AND wallet_balance > 0 ORDER BY wallet_balance DESC LIMIT 400'
			)->getResultArray();
		} else {
			$rows = $this->db->query(
				'SELECT student_id FROM pocket_links WHERE parent_id = ? ORDER BY id DESC',
				[$parentId]
			)->getResultArray();
		}
		$students = [];
		foreach ($rows as $row) {
			$student = $this->studentById((int) $row['student_id']);
			if ($student) {
				$students[] = $this->publicStudent($student, true);
			}
		}

		return $students;
	}

	public function ledger(int $parentId, int $studentId): array
	{
		$this->assertLinked($parentId, $studentId);
		$student = $this->studentById($studentId);
		$rows = $this->db->query(
			'SELECT id, amount, type, source, balance, status, txn_Id, reference_id, tx_error, extra_options, created_at
			 FROM payment_transactions
			 WHERE student_id = ? AND type IN (0, 1, 2, 3)
			 ORDER BY id DESC LIMIT 40',
			[$studentId]
		)->getResultArray();
		$items = [];
		foreach ($rows as $row) {
			$items[] = $this->formatLedger($row);
		}

		return [
			'student' => $this->publicStudent($student, true),
			'transactions' => $items,
		];
	}

	public function setSpendPin(int $parentId, int $studentId, string $pin, string $oldPin): void
	{
		$this->assertLinked($parentId, $studentId);
		$student = $this->studentById($studentId);
		$this->requirePin($pin);
		$this->applySpendPin($student, $pin, $oldPin);
	}

	public function startTopup(int $parentId, int $studentId, $amountRaw, string $phone, string $mno): array
	{
		$this->assertLinked($parentId, $studentId);
		$student = $this->studentById($studentId);
		$card = $this->savedCard($student);
		$amount = $this->requireAmount($amountRaw);
		$payer = $this->requireMsisdn($phone);
		$mno = strtolower(trim($mno));
		if ($mno !== 'mtn') {
			throw new \InvalidArgumentException('Pocket money is collected with MTN MoMo. Use an MTN number.');
		}
		$gateway = BesoftGatewayClient::make();
		if (!$gateway->isConfigured()) {
			throw new \RuntimeException('Mobile Money is not configured.', 503);
		}
		$pending = $this->db->query(
			"SELECT id FROM payment_transactions
			 WHERE student_id = ? AND type = ? AND status = 0 AND created_at > DATE_SUB(NOW(), INTERVAL 3 MINUTE)
			 ORDER BY id DESC LIMIT 1",
			[$studentId, self::TYPE_TOPUP]
		)->getRow();
		if ($pending) {
			throw new \RuntimeException('A payment is already waiting on the phone. Approve it, or wait a moment and try again.', 409);
		}

		$regno = preg_replace('/[^A-Za-z0-9]/', '', (string) $student['regno']);
		if ($regno === null || $regno === '') {
			$regno = 'STU';
		}
		$regno = substr($regno, 0, 16);
		$receiver = $this->schoolReceiver($gateway, $student);
		if ($receiver === '') {
			throw new \RuntimeException('Set MOMO account (pocket money) in Settings. The registration fees number is not used for pocket money.', 409);
		}
		$extra = [
			'parent_id' => $parentId,
			'mno' => $mno,
			'phone' => $payer,
			'provider' => 'besoft',
			'receiver' => $receiver,
			'card' => $card,
		];
		$payments = new PaymentModel();
		$paymentId = $payments->insert([
			'student_id' => $studentId,
			'amount' => $amount,
			'type' => self::TYPE_TOPUP,
			'source' => $payer,
			'balance' => (int) round((float) $student['wallet_balance']),
			'txn_fee' => 0,
			'txn_Id' => substr('pkt' . time(), 0, 50),
			'status' => 0,
			'created_by' => 0,
			'extra_options' => json_encode($extra),
		]);
		if (!$paymentId) {
			throw new \RuntimeException('Could not open the payment. Try again.', 500);
		}

		try {
			$result = $gateway->collectAndPay([
				'payer' => $payer,
				'payee' => $receiver,
				'amount' => $amount,
				'fee_from_payer' => true,
				'idempotency_key' => 'pkt' . (int) $paymentId,
				'description' => 'Student wallet ' . $regno,
			]);
		} catch (\Throwable $e) {
			$payments->update((int) $paymentId, [
				'status' => 2,
				'tx_error' => substr($e->getMessage(), 0, 100),
			]);
			throw new \RuntimeException('Mobile Money could not start. Try again.', 502);
		}

		$realTx = substr((string) ($result['transaction_id'] ?? ''), 0, 50);
		if (empty($result['ok']) || $realTx === '') {
			$msg = (string) ($result['error_message'] ?? 'Mobile Money payment failed.');
			$payments->update((int) $paymentId, [
				'status' => 2,
				'tx_error' => substr($msg, 0, 100),
			]);
			throw new \RuntimeException($msg, 502);
		}
		$fee = (int) ($result['fee'] ?? 0);
		$debit = (int) ($result['debit'] ?? ($amount + $fee));
		$extra['fee'] = $fee;
		$extra['debit'] = $debit;
		$extra['receive'] = $amount;
		$extra['besoft_id'] = $realTx;
		$payments->update((int) $paymentId, [
			'txn_Id' => $realTx,
			'txn_fee' => $fee,
			'extra_options' => json_encode($extra),
		]);
		$message = 'Approve ' . number_format($debit) . ' RWF on your phone. '
			. number_format($fee) . ' RWF is the charge, taken from you. '
			. number_format($amount) . ' RWF reaches the pocket money account and this card.';

		return [
			'paymentId' => (int) $paymentId,
			'status' => 'pending',
			'message' => $message,
			'amount' => $amount,
			'charge' => $fee,
			'total' => $debit,
			'currency' => 'RWF',
		];
	}

	public function pollTopup(int $parentId, int $paymentId): array
	{
		$payment = $this->ownedPayment($parentId, $paymentId);
		if ((int) $payment['status'] === 1) {
			$student = $this->studentById((int) $payment['student_id']);

			return [
				'status' => 'paid',
				'message' => 'Mobile Money payment received. The student wallet is updated.',
				'balance' => (int) round((float) $student['wallet_balance']),
				'amount' => (int) round((float) $payment['amount']),
				'currency' => 'RWF',
			];
		}
		if ((int) $payment['status'] === 2) {
			return [
				'status' => 'failed',
				'message' => $this->failedText($payment),
				'balance' => (int) round((float) $this->studentById((int) $payment['student_id'])['wallet_balance']),
				'currency' => 'RWF',
			];
		}

		$status = $this->providerStatus($payment);
		if (!empty($status['success']) && empty($status['settlement_failed'])) {
			$balance = $this->creditOnce((int) $payment['id'], $status);

			return [
				'status' => 'paid',
				'message' => 'Mobile Money payment received. The student wallet is updated.',
				'balance' => $balance,
				'amount' => (int) round((float) $payment['amount']),
				'currency' => 'RWF',
			];
		}
		if (!empty($status['failed'])) {
			$msg = (string) ($status['error_message'] ?? 'The payment was not approved.');
			$this->db->table('payment_transactions')->where('id', (int) $payment['id'])->where('status', 0)->update([
				'status' => 2,
				'tx_error' => substr($msg, 0, 100),
				'updated_at' => $this->now(),
			]);

			return [
				'status' => 'failed',
				'message' => $msg,
				'balance' => (int) round((float) $this->studentById((int) $payment['student_id'])['wallet_balance']),
				'currency' => 'RWF',
			];
		}

		return [
			'status' => 'pending',
			'message' => 'Waiting for approval on your phone.',
			'amount' => (int) round((float) $payment['amount']),
			'currency' => 'RWF',
		];
	}

	public function posPreview(int $schoolId, string $card): array
	{
		$student = $this->studentByCard($schoolId, $card);
		if (!$student) {
			throw new \RuntimeException('No student card matches this scan.', 404);
		}

		return $this->publicStudent($student, true);
	}

	public function posCharge(int $schoolId, string $card, $amountRaw, string $pin, string $merchant, string $clientRef, string $canteenPhone = '', string $items = '', array $lines = [], string $signature = ''): array
	{
		$student = $this->studentByCard($schoolId, $card);
		if (!$student) {
			throw new \RuntimeException('No student card matches this scan.', 404);
		}
		$amount = $this->requireAmount($amountRaw, 1);
		$wanted = $this->saleLines($lines);
		$signatureFile = '';
		$merchant = trim(preg_replace('/\s+/', ' ', $merchant));
		if ($merchant === '') {
			$merchant = 'POS';
		}
		$merchant = substr($merchant, 0, 40);
		$clientRef = preg_replace('/[^A-Za-z0-9_-]/', '', $clientRef);
		if ($clientRef === null) {
			$clientRef = '';
		}
		$clientRef = substr($clientRef, 0, 40);
		if ($clientRef !== '') {
			$prior = $this->db->query(
				'SELECT * FROM payment_transactions WHERE txn_Id = ? AND student_id = ? AND type = ? LIMIT 1',
				['POS_' . $clientRef, (int) $student['id'], self::TYPE_PAYMENT]
			)->getRowArray();
			if ($prior && (int) $prior['status'] === 1) {
				return [
					'status' => 'paid',
					'message' => 'This sale was already charged.',
					'amount' => (int) round((float) $prior['amount']),
					'balance' => (int) $prior['balance'],
					'paymentId' => (int) $prior['id'],
					'student' => $this->publicStudent($this->studentById((int) $student['id']), true),
					'currency' => 'RWF',
				];
			}
		}

		$this->db->transBegin();
		try {
			$locked = $this->db->query(
				'SELECT id, wallet_balance, wallet_pin, status FROM students WHERE id = ? FOR UPDATE',
				[(int) $student['id']]
			)->getRowArray();
			if (!$locked || (int) $locked['status'] !== 1) {
				throw new \RuntimeException('Student account is not active.', 409);
			}
			$balance = (int) round((float) $locked['wallet_balance']);
			if ($balance < $amount) {
				throw new \RuntimeException('Not enough money on this student card. Balance is ' . number_format($balance) . ' RWF.', 402);
			}
			$sold = $this->lockCanteenLines((int) $student['school_id'], $wanted);
			$priced = 0;
			$parts = [];
			foreach ($sold as $line) {
				$priced += (int) $line['price'] * (int) $line['qty'];
				$parts[] = $line['name'] . ' x' . (int) $line['qty'];
			}
			if ($priced !== $amount) {
				throw new \RuntimeException('The menu changed. Choose the items again.', 409);
			}
			$next = $balance - $amount;
			$this->db->query('UPDATE students SET wallet_balance = ? WHERE id = ?', [$next, (int) $locked['id']]);
			foreach ($sold as $line) {
				$this->db->query(
					'UPDATE canteen_items SET stock = stock - ?, updated_at = ? WHERE id = ?',
					[(int) $line['qty'], $this->now(), (int) $line['id']]
				);
			}
			$schoolRow = $this->db->query('SELECT canteen_phone FROM schools WHERE id = ?', [(int) $student['school_id']])->getRowArray();
			$canteenPhone = $this->canteenPhone((string) ($schoolRow['canteen_phone'] ?? $canteenPhone));
			$signatureFile = $this->storeSignature($clientRef, $signature);
			$txn = $clientRef !== '' ? 'POS_' . $clientRef : ('POS_' . time() . '_' . random_int(1000, 9999));
			$items = implode(', ', $parts);
			$payments = new PaymentModel();
			$paymentId = $payments->insert([
				'student_id' => (int) $locked['id'],
				'amount' => $amount,
				'type' => self::TYPE_PAYMENT,
				'source' => 'POS',
				'balance' => $next,
				'txn_fee' => 0,
				'txn_Id' => substr($txn, 0, 50),
				'status' => 1,
				'created_by' => 0,
				'extra_options' => json_encode([
					'merchant' => $merchant,
					'channel' => 'card',
					'card' => strtoupper(trim((string) ($student['card'] ?? ''))),
					'canteen_phone' => $this->canteenPhone($canteenPhone),
					'items' => substr(trim(preg_replace('/\s+/', ' ', $items)), 0, 180),
					'lines' => $sold,
					'signature' => $signatureFile,
				]),
			]);
			if ($this->db->transStatus() === false || !$paymentId) {
				throw new \RuntimeException('The card charge could not be saved.', 500);
			}
			$this->db->transCommit();
		} catch (\Throwable $e) {
			$this->db->transRollback();
			if ($signatureFile !== '') {
				@unlink($this->signaturePath($signatureFile));
			}
			throw $e;
		}

		$fresh = $this->studentById((int) $student['id']);

		return [
			'status' => 'paid',
			'message' => 'Charged ' . number_format($amount) . ' RWF from ' . $fresh['name'] . '.',
			'amount' => $amount,
			'balance' => (int) round((float) $fresh['wallet_balance']),
			'paymentId' => (int) $paymentId,
			'student' => $this->publicStudent($fresh, true),
			'currency' => 'RWF',
		];
	}

	protected function creditOnce(int $paymentId, array $status): int
	{
		$this->db->transBegin();
		try {
			$payment = $this->db->query(
				'SELECT * FROM payment_transactions WHERE id = ? FOR UPDATE',
				[$paymentId]
			)->getRowArray();
			if (!$payment) {
				throw new \RuntimeException('Payment not found.', 404);
			}
			if ((int) $payment['status'] === 1) {
				$this->db->transCommit();
				$student = $this->studentById((int) $payment['student_id']);

				return (int) round((float) $student['wallet_balance']);
			}
			if ((int) $payment['status'] !== 0 || (int) $payment['type'] !== self::TYPE_TOPUP) {
				throw new \RuntimeException('This payment can no longer be completed.', 409);
			}
			$amount = (int) round((float) $payment['amount']);
			$this->db->query(
				'UPDATE students SET wallet_balance = wallet_balance + ? WHERE id = ?',
				[$amount, (int) $payment['student_id']]
			);
			$student = $this->db->query(
				'SELECT wallet_balance FROM students WHERE id = ?',
				[(int) $payment['student_id']]
			)->getRowArray();
			$balance = (int) round((float) ($student['wallet_balance'] ?? 0));
			$ref = '';
			$body = isset($status['response']) && is_array($status['response']) ? $status['response'] : [];
			foreach (['provider_ref', 'external_id', 'financialTransactionId', 'momoRef', 'momo_ref', 'reference'] as $key) {
				if (!empty($body[$key])) {
					$ref = (string) $body[$key];
					break;
				}
			}
			$owner = $this->db->query('SELECT card FROM students WHERE id = ?', [(int) $payment['student_id']])->getRowArray();
			$card = strtoupper(trim((string) ($owner['card'] ?? '')));
			$extra = json_decode((string) ($payment['extra_options'] ?? ''), true);
			if (!is_array($extra)) {
				$extra = [];
			}
			$extra['card'] = $card;
			$this->db->table('payment_transactions')->where('id', $paymentId)->update([
				'status' => 1,
				'balance' => $balance,
				'reference_id' => substr($ref, 0, 100),
				'tx_error' => null,
				'extra_options' => json_encode($extra),
				'updated_at' => $this->now(),
			]);
			if ($this->db->transStatus() === false) {
				throw new \RuntimeException('Wallet update failed.', 500);
			}
			$this->db->transCommit();

			return $balance;
		} catch (\Throwable $e) {
			$this->db->transRollback();
			throw $e;
		}
	}

	protected function ownedPayment(int $parentId, int $paymentId): array
	{
		$payment = $this->db->query('SELECT * FROM payment_transactions WHERE id = ? LIMIT 1', [$paymentId])->getRowArray();
		if (!$payment) {
			throw new \RuntimeException('Payment not found.', 404);
		}
		$this->assertLinked($parentId, (int) $payment['student_id']);
		$extra = json_decode((string) ($payment['extra_options'] ?? ''), true);
		$provider = is_array($extra) ? (string) ($extra['provider'] ?? '') : '';
		if (!is_array($extra) || (int) ($extra['parent_id'] ?? 0) !== $parentId || !in_array($provider, ['besoft', 'mopay'], true)) {
			throw new \RuntimeException('Payment not found.', 404);
		}

		return $payment;
	}

	protected function applySpendPin(array $student, string $pin, string $oldPin): void
	{
		$current = (string) ($student['wallet_pin'] ?? '');
		if ($current !== '') {
			$oldPin = trim($oldPin);
			if ($oldPin === '' || !hash_equals($current, sha1($oldPin))) {
				throw new \RuntimeException('The current spend PIN is not correct.', 401);
			}
		}
		$this->db->table('students')->where('id', (int) $student['id'])->update([
			'wallet_pin' => sha1($pin),
		]);
	}

	protected function schoolReceiver(BesoftGatewayClient $gateway, array $student): string
	{
		$pocket = $gateway->normalizeMsisdn((string) ($student['pocket_money_phone'] ?? ''));
		if (preg_match('/^2507\d{8}$/', $pocket)) {
			return $pocket;
		}

		return '';
	}

	protected function studentByRegno(string $regno): ?array
	{
		$regno = strtolower(trim($regno));
		if ($regno === '' || strlen($regno) > 40) {
			throw new \InvalidArgumentException('Enter the student registration number.');
		}

		return $this->hydrateStudent(
			'LOWER(students.regno) = ?',
			[$regno]
		);
	}

	protected function studentById(int $id): ?array
	{
		if ($id <= 0) {
			return null;
		}

		return $this->hydrateStudent('students.id = ?', [$id]);
	}

	protected function studentByCard(int $schoolId, string $card): ?array
	{
		$card = trim($card);
		if ($card === '') {
			throw new \InvalidArgumentException('Scan the student card.');
		}
		if ($schoolId > 0) {
			$owner = \App\Libraries\CardRegistry::lookup($schoolId, $card);
			if (!$owner || ($owner['type'] ?? '') !== 'student') {
				return null;
			}

			return $this->studentById((int) $owner['id']);
		}
		helper('card_uid');
		$variants = card_uid_lookup_variants($card);
		if (empty($variants)) {
			return null;
		}
		$placeholders = implode(',', array_fill(0, count($variants), '?'));
		$rows = $this->db->query(
			"SELECT id FROM students WHERE status = 1 AND UPPER(TRIM(card)) IN ({$placeholders}) LIMIT 2",
			$variants
		)->getResultArray();
		if (count($rows) !== 1) {
			return null;
		}

		return $this->studentById((int) $rows[0]['id']);
	}

	protected function saleLines(array $lines): array
	{
		$merged = [];
		foreach ($lines as $line) {
			if (!is_array($line)) {
				continue;
			}
			$id = (int) ($line['id'] ?? 0);
			$qty = (int) ($line['qty'] ?? 0);
			if ($id <= 0 || $qty <= 0) {
				continue;
			}
			if ($qty > 50) {
				throw new \InvalidArgumentException('Too many of one item.');
			}
			$merged[$id] = (int) ($merged[$id] ?? 0) + $qty;
		}
		if ($merged === []) {
			throw new \InvalidArgumentException('Choose what the student took.');
		}

		return $merged;
	}

	protected function lockCanteenLines(int $schoolId, array $wanted): array
	{
		$sold = [];
		foreach ($wanted as $id => $qty) {
			$row = $this->db->query(
				'SELECT id, name, price, stock FROM canteen_items WHERE id = ? AND school_id = ? AND active = 1 LIMIT 1 FOR UPDATE',
				[(int) $id, $schoolId]
			)->getRowArray();
			if (!$row) {
				throw new \RuntimeException('That item is no longer on the menu.', 409);
			}
			$left = (int) $row['stock'];
			if ($left < (int) $qty) {
				$name = (string) $row['name'];
				if ($left <= 0) {
					throw new \RuntimeException($name . ' is out of stock.', 409);
				}
				throw new \RuntimeException('Only ' . $left . ' ' . $name . ' left.', 409);
			}
			$sold[] = [
				'id' => (int) $row['id'],
				'name' => (string) $row['name'],
				'price' => (int) $row['price'],
				'qty' => (int) $qty,
			];
		}

		return $sold;
	}

	protected function storeSignature(string $clientRef, string $signature): string
	{
		$signature = trim($signature);
		$comma = strpos($signature, ',');
		if ($comma !== false && stripos($signature, 'base64') !== false) {
			$signature = substr($signature, $comma + 1);
		}
		$raw = base64_decode($signature, true);
		if ($raw === false || strlen($raw) < 80 || strlen($raw) > 400000) {
			throw new \InvalidArgumentException('The student must sign on the screen, then tap OK.');
		}
		if (substr($raw, 0, 8) !== "\x89PNG\r\n\x1a\n") {
			throw new \InvalidArgumentException('The student must sign on the screen, then tap OK.');
		}
		$dir = rtrim(WRITEPATH, '/\\') . DIRECTORY_SEPARATOR . 'canteen-signatures';
		if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
			throw new \RuntimeException('The signature could not be saved.', 500);
		}
		$token = $clientRef !== '' ? $clientRef : (string) time();
		$name = 'sig_' . preg_replace('/[^A-Za-z0-9_-]/', '', $token) . '.png';
		if (file_put_contents($dir . DIRECTORY_SEPARATOR . $name, $raw) === false) {
			throw new \RuntimeException('The signature could not be saved.', 500);
		}

		return $name;
	}

	protected function signaturePath(string $name): string
	{
		return rtrim(WRITEPATH, '/\\') . DIRECTORY_SEPARATOR . 'canteen-signatures' . DIRECTORY_SEPARATOR . $name;
	}

	protected function schoolBrand(array $school): array
	{
		return [
			'id' => (int) ($school['id'] ?? 0),
			'name' => (string) ($school['name'] ?? ''),
			'acronym' => trim((string) ($school['acronym'] ?? '')),
			'slogan' => trim((string) ($school['slogan'] ?? '')),
			'address' => trim((string) ($school['address'] ?? '')),
			'phone' => trim((string) ($school['phone'] ?? '')),
			'pobox' => trim((string) ($school['pobox'] ?? '')),
			'email' => trim((string) ($school['email'] ?? '')),
			'logoUrl' => $this->logoUrl((string) ($school['logo'] ?? '')),
			'headerColor' => $this->hexColor($school['header_color'] ?? ''),
			'mainColor' => $this->hexColor($school['main_color'] ?? ''),
		];
	}

	protected function logoUrl(string $logo): string
	{
		$logo = trim($logo);
		if (strlen($logo) < 5 || strpos($logo, '..') !== false || strpos($logo, '/') !== false || strpos($logo, '\\') !== false) {
			return '';
		}

		return base_url('assets/images/logo/' . rawurlencode($logo));
	}

	protected function hexColor($value): string
	{
		$value = strtoupper(trim((string) $value));
		if (preg_match('/^#?[0-9A-F]{6}$/', $value)) {
			return '#' . ltrim($value, '#');
		}

		return '';
	}

	protected function canteenPhone(string $phone): string
	{
		$digits = preg_replace('/\D+/', '', $phone);
		if ($digits === null || $digits === '') {
			return '';
		}
		if (strpos($digits, '250') === 0 && strlen($digits) === 12) {
			return $digits;
		}
		if (strlen($digits) === 10 && $digits[0] === '0') {
			return '25' . $digits;
		}
		if (strlen($digits) === 9 && $digits[0] === '7') {
			return '250' . $digits;
		}

		return '';
	}

	public function schoolByAcronym(string $acronym): array
	{
		$acronym = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $acronym));
		if ($acronym === '' || strlen($acronym) > 20) {
			throw new \InvalidArgumentException('Enter the school acronym.');
		}
		$rows = $this->db->query(
			'SELECT id, name, acronym, slogan, address, phone, email, pobox, logo, header_color, main_color
			FROM schools WHERE status = 1 AND UPPER(TRIM(acronym)) = ? LIMIT 2',
			[$acronym]
		)->getResultArray();
		if (count($rows) !== 1) {
			throw new \RuntimeException('That school acronym was not found.', 404);
		}

		return $this->schoolBrand($rows[0]);
	}

	public function canteenCatalog(int $schoolId): array
	{
		$school = $this->db->query(
			'SELECT id, name, acronym, slogan, address, phone, email, pobox, logo, header_color, main_color, canteen_phone
			FROM schools WHERE id = ? AND status = 1 LIMIT 1',
			[$schoolId]
		)->getRowArray();
		if (!$school) {
			throw new \RuntimeException('School not found.', 404);
		}
		$rows = $this->db->query(
			'SELECT id, name, price, stock FROM canteen_items WHERE school_id = ? AND active = 1 ORDER BY sort_order ASC, id ASC',
			[$schoolId]
		)->getResultArray();
		$items = [];
		foreach ($rows as $row) {
			$items[] = [
				'id' => (int) $row['id'],
				'name' => (string) $row['name'],
				'price' => (int) $row['price'],
				'stock' => (int) $row['stock'],
			];
		}
		$brand = $this->schoolBrand($school);

		return [
			'schoolId' => (int) $school['id'],
			'school' => (string) $school['name'],
			'canteenPhone' => $this->canteenPhone((string) ($school['canteen_phone'] ?? '')),
			'brand' => $brand,
			'items' => $items,
		];
	}

	public function canteenItems(int $schoolId): array
	{
		return $this->db->query(
			'SELECT id, name, price, stock, active FROM canteen_items WHERE school_id = ? ORDER BY sort_order ASC, id ASC',
			[$schoolId]
		)->getResultArray();
	}

	public function saveCanteenItem(int $schoolId, int $id, string $name, int $price, int $stock = 0): void
	{
		$name = trim(preg_replace('/\s+/', ' ', $name));
		$name = substr($name, 0, 80);
		if ($stock < 0) {
			$stock = 0;
		}
		if ($stock > 100000) {
			$stock = 100000;
		}
		if ($name === '' || $price < 1) {
			throw new \InvalidArgumentException('Enter an item name and a price.');
		}
		$now = $this->now();
		if ($id > 0) {
			$owned = $this->db->query(
				'SELECT id FROM canteen_items WHERE id = ? AND school_id = ? LIMIT 1',
				[$id, $schoolId]
			)->getRowArray();
			if (!$owned) {
				throw new \RuntimeException('That menu item was not found.');
			}
			$this->db->table('canteen_items')->where('id', $id)->update([
				'name' => $name,
				'price' => $price,
				'stock' => $stock,
				'active' => 1,
				'updated_at' => $now,
			]);

			return;
		}
		$this->db->table('canteen_items')->insert([
			'school_id' => $schoolId,
			'name' => $name,
			'price' => $price,
			'stock' => $stock,
			'active' => 1,
			'sort_order' => 0,
			'created_at' => $now,
			'updated_at' => $now,
		]);
	}

	public function deleteCanteenItem(int $schoolId, int $id): void
	{
		$this->db->table('canteen_items')->where('id', $id)->where('school_id', $schoolId)->delete();
	}

	protected function hydrateStudent(string $where, array $params): ?array
	{
		$sql = "SELECT students.id, students.regno, students.fname, students.lname, students.photo,
			students.card, students.wallet_balance, students.wallet_pin, students.school_id, students.status,
			sk.name AS school_name, sk.pocket_money_phone, sk.mtn_momo_phone,
			TRIM(CONCAT(IFNULL(l.title,''), ' ', IFNULL(d.code,''), ' ', IFNULL(c.title,''))) AS class_name
			FROM students
			LEFT JOIN schools sk ON sk.id = students.school_id
			LEFT JOIN (
				SELECT student, MAX(id) AS record_id FROM class_records WHERE status = 1 GROUP BY student
			) latest ON latest.student = students.id
			LEFT JOIN class_records cr ON cr.id = latest.record_id
			LEFT JOIN classes c ON c.id = cr.class
			LEFT JOIN departments d ON d.id = c.department
			LEFT JOIN levels l ON l.id = c.level
			WHERE students.status = 1 AND {$where}
			ORDER BY students.id DESC
			LIMIT 1";
		$row = $this->db->query($sql, $params)->getRowArray();
		if (!$row) {
			return null;
		}
		$name = trim(preg_replace('/\s+/', ' ', trim((string) $row['fname']) . ' ' . trim((string) $row['lname'])));
		$row['name'] = $name !== '' ? $name : 'Student';

		return $row;
	}

	protected function studentsByPrefix(string $prefix): array
	{
		$prefix = str_replace(['%', '_'], '', $prefix);
		if ($prefix === '') {
			return [];
		}
		$sql = "SELECT students.id, students.regno, students.fname, students.lname, students.photo,
			students.card, students.wallet_balance, students.wallet_pin, students.school_id, students.status,
			sk.name AS school_name, sk.pocket_money_phone, sk.mtn_momo_phone,
			TRIM(CONCAT(IFNULL(l.title,''), ' ', IFNULL(d.code,''), ' ', IFNULL(c.title,''))) AS class_name
			FROM students
			LEFT JOIN schools sk ON sk.id = students.school_id
			LEFT JOIN class_records cr ON cr.id = (
				SELECT MAX(id) FROM class_records WHERE student = students.id AND status = 1
			)
			LEFT JOIN classes c ON c.id = cr.class
			LEFT JOIN departments d ON d.id = c.department
			LEFT JOIN levels l ON l.id = c.level
			WHERE students.status = 1 AND students.regno LIKE ?
			ORDER BY students.regno ASC
			LIMIT 8";
		$rows = $this->db->query($sql, [$prefix . '%'])->getResultArray();
		$students = [];
		foreach ($rows as $row) {
			$name = trim(preg_replace('/\s+/', ' ', trim((string) $row['fname']) . ' ' . trim((string) $row['lname'])));
			$row['name'] = $name !== '' ? $name : 'Student';
			$students[] = $row;
		}

		return $students;
	}

	protected function publicStudent(array $student, bool $includeBalance): array
	{
		$card = strtoupper(trim((string) ($student['card'] ?? '')));
		$photo = trim((string) ($student['photo'] ?? ''));
		$photoUrl = '';
		if ($photo !== '' && strpos($photo, '..') === false) {
			$photoUrl = base_url('assets/images/profile/' . rawurlencode($photo));
		}
		$className = trim((string) ($student['class_name'] ?? ''));
		$data = [
			'id' => (int) $student['id'],
			'regno' => (string) $student['regno'],
			'name' => (string) $student['name'],
			'className' => $className !== '' ? $className : 'Class not assigned',
			'school' => (string) ($student['school_name'] ?? ''),
			'schoolId' => (int) ($student['school_id'] ?? 0),
			'photoUrl' => $photoUrl,
			'cardLinked' => $card !== '',
			'cardUid' => $card,
			'cardHint' => $card !== '' ? substr($card, -4) : '',
			'hasSpendPin' => trim((string) ($student['wallet_pin'] ?? '')) !== '',
		];
		if ($includeBalance) {
			$data['balance'] = (int) round((float) $student['wallet_balance']);
			$data['currency'] = 'RWF';
		}

		return $data;
	}

	protected function formatLedger(array $row): array
	{
		$type = (int) $row['type'];
		$label = 'Wallet movement';
		if ($type === 0) {
			$label = 'Top up';
		} elseif ($type === 1) {
			$label = 'Card spend';
		} elseif ($type === 2) {
			$label = 'Withdraw';
		} elseif ($type === 3) {
			$label = 'Sent back';
		}
		$status = (int) $row['status'];
		$state = 'pending';
		if ($status === 1) {
			$state = 'paid';
		} elseif ($status === 2) {
			$state = 'failed';
		}
		$extra = json_decode((string) ($row['extra_options'] ?? ''), true);
		$merchant = is_array($extra) ? (string) ($extra['merchant'] ?? '') : '';

		return [
			'id' => (int) $row['id'],
			'label' => $label,
			'type' => $type,
			'amount' => (int) round((float) $row['amount']),
			'balance' => $row['balance'] === null ? null : (int) $row['balance'],
			'status' => $state,
			'source' => (string) ($row['source'] ?? ''),
			'merchant' => $merchant,
			'message' => (string) ($row['tx_error'] ?? ''),
			'createdAt' => (string) $row['created_at'],
		];
	}

	protected function savedCard(array $student): string
	{
		$card = strtoupper(trim((string) ($student['card'] ?? '')));
		if ($card === '') {
			throw new \RuntimeException('This student has no card saved in the school database yet.', 409);
		}

		return $card;
	}

	protected function assertLinked(int $parentId, int $studentId): void
	{
		if ($this->isAgent($parentId)) {
			if (!$this->studentById($studentId)) {
				throw new \RuntimeException('Student not found.', 404);
			}

			return;
		}
		$row = $this->db->query(
			'SELECT id FROM pocket_links WHERE parent_id = ? AND student_id = ? LIMIT 1',
			[$parentId, $studentId]
		)->getRow();
		if (!$row) {
			throw new \RuntimeException('Add this student before sending money.', 403);
		}
	}

	protected function issueSession(int $parentId): array
	{
		$token = bin2hex(random_bytes(32));
		$now = $this->now();
		$this->db->table('pocket_sessions')->insert([
			'parent_id' => $parentId,
			'token_hash' => hash('sha256', $token),
			'expires_at' => date('Y-m-d H:i:s', time() + 60 * 60 * 24 * 90),
			'created_at' => $now,
		]);
		$parent = $this->db->query('SELECT id, name, phone, role FROM pocket_parents WHERE id = ?', [$parentId])->getRowArray();

		return [
			'token' => $token,
			'parent' => [
				'id' => (int) $parent['id'],
				'name' => (string) $parent['name'],
				'phone' => (string) $parent['phone'],
				'role' => (string) ($parent['role'] ?? 'parent'),
			],
		];
	}

	protected function requireMsisdn(string $phone): string
	{
		$normalized = BesoftGatewayClient::make()->normalizeMsisdn($phone);
		if (!preg_match('/^2507\d{8}$/', $normalized)) {
			throw new \InvalidArgumentException('Enter a valid MTN or Airtel Rwanda number.');
		}

		return $normalized;
	}

	protected function requirePin(string $pin): void
	{
		if (!preg_match('/^\d{4,6}$/', trim($pin))) {
			throw new \InvalidArgumentException('Use a PIN of 4 to 6 digits.');
		}
	}

	protected function pocketSmsSchoolId(string $phone): int
	{
		try {
			$row = $this->db->query(
				'SELECT s.school_id FROM pocket_parents p
				 INNER JOIN pocket_links l ON l.parent_id = p.id
				 INNER JOIN students s ON s.id = l.student_id
				 WHERE p.phone = ? ORDER BY l.id DESC LIMIT 1',
				[$phone]
			)->getRowArray();
		} catch (\Throwable $e) {
			return 0;
		}
		return (int) ($row['school_id'] ?? 0);
	}

	protected function requireAmount($raw, int $min = self::MIN_AMOUNT): int
	{
		$text = trim((string) $raw);
		if (!preg_match('/^\d+$/', $text)) {
			throw new \InvalidArgumentException('Enter a whole amount in RWF.');
		}
		$amount = (int) $text;
		if ($amount < $min || $amount > self::MAX_AMOUNT) {
			throw new \InvalidArgumentException('Amount must be between ' . number_format($min) . ' and ' . number_format(self::MAX_AMOUNT) . ' RWF.');
		}

		return $amount;
	}

	protected function cleanName(string $name): string
	{
		$name = trim(preg_replace('/\s+/', ' ', strip_tags($name)));
		if (strlen($name) < 2 || strlen($name) > 120) {
			throw new \InvalidArgumentException('Enter the parent name.');
		}

		return $name;
	}

	protected function failedText(array $payment): string
	{
		$text = trim((string) ($payment['tx_error'] ?? ''));

		return $text !== '' ? $text : 'The payment was not approved.';
	}

	protected function now(): string
	{
		return date('Y-m-d H:i:s');
	}

	protected function ensureSchema(): void
	{
		if (self::$ready) {
			return;
		}
		$this->db->query(
			"CREATE TABLE IF NOT EXISTS pocket_parents (
				id INT UNSIGNED NOT NULL AUTO_INCREMENT,
				phone VARCHAR(16) NOT NULL,
				name VARCHAR(120) NOT NULL,
				pin_hash VARCHAR(255) NOT NULL,
				failed_count INT NOT NULL DEFAULT 0,
				locked_until DATETIME NULL,
				status TINYINT NOT NULL DEFAULT 1,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY phone (phone)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
		);
		$this->db->query(
			"CREATE TABLE IF NOT EXISTS pocket_sessions (
				id INT UNSIGNED NOT NULL AUTO_INCREMENT,
				parent_id INT UNSIGNED NOT NULL,
				token_hash CHAR(64) NOT NULL,
				expires_at DATETIME NOT NULL,
				created_at DATETIME NOT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY token_hash (token_hash),
				KEY parent_id (parent_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
		);
		$roleColumn = $this->db->query("SHOW COLUMNS FROM pocket_parents LIKE 'role'")->getRowArray();
		if (!$roleColumn) {
			$this->db->query("ALTER TABLE pocket_parents ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'parent' AFTER name");
		}
		$resetColumn = $this->db->query("SHOW COLUMNS FROM pocket_parents LIKE 'reset_hash'")->getRowArray();
		if (!$resetColumn) {
			$this->db->query("ALTER TABLE pocket_parents ADD COLUMN reset_hash VARCHAR(255) NULL, ADD COLUMN reset_expires DATETIME NULL");
		}
		$this->ensureAgent();
		$this->db->query(
			"CREATE TABLE IF NOT EXISTS pocket_links (
				id INT UNSIGNED NOT NULL AUTO_INCREMENT,
				parent_id INT UNSIGNED NOT NULL,
				student_id INT UNSIGNED NOT NULL,
				created_at DATETIME NOT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY parent_student (parent_id, student_id),
				KEY student_id (student_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
		);
		$canteenColumn = $this->db->query("SHOW COLUMNS FROM schools LIKE 'canteen_phone'")->getRowArray();
		if (!$canteenColumn) {
			$this->db->query("ALTER TABLE schools ADD COLUMN canteen_phone VARCHAR(20) NOT NULL DEFAULT ''");
		}
		$this->db->query(
			"CREATE TABLE IF NOT EXISTS canteen_items (
				id INT UNSIGNED NOT NULL AUTO_INCREMENT,
				school_id INT UNSIGNED NOT NULL,
				name VARCHAR(80) NOT NULL,
				price INT NOT NULL,
				stock INT NOT NULL DEFAULT 0,
				active TINYINT NOT NULL DEFAULT 1,
				sort_order INT NOT NULL DEFAULT 0,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NULL,
				PRIMARY KEY (id),
				KEY school_id (school_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
		);
		$stockColumn = $this->db->query("SHOW COLUMNS FROM canteen_items LIKE 'stock'")->getRowArray();
		if (!$stockColumn) {
			$this->db->query("ALTER TABLE canteen_items ADD COLUMN stock INT NOT NULL DEFAULT 0 AFTER price");
		}
		self::$ready = true;
	}

	protected function ensureAgent(): void
	{
		$phone = '250780699435';
		$existing = $this->db->query('SELECT id, role FROM pocket_parents WHERE phone = ? LIMIT 1', [$phone])->getRowArray();
		$now = $this->now();
		if ($existing) {
			if ((string) ($existing['role'] ?? '') !== 'agent') {
				$this->db->table('pocket_parents')->where('id', (int) $existing['id'])->update([
					'role' => 'agent',
					'name' => 'Pocket agent',
					'updated_at' => $now,
				]);
			}

			return;
		}
		$this->db->table('pocket_parents')->insert([
			'phone' => $phone,
			'name' => 'Pocket agent',
			'role' => 'agent',
			'pin_hash' => password_hash('258046', PASSWORD_DEFAULT),
			'failed_count' => 0,
			'locked_until' => null,
			'status' => 1,
			'created_at' => $now,
			'updated_at' => $now,
		]);
	}

	protected function isAgent(int $parentId): bool
	{
		$row = $this->db->query('SELECT role FROM pocket_parents WHERE id = ? LIMIT 1', [$parentId])->getRowArray();

		return (string) ($row['role'] ?? '') === 'agent';
	}

	/**
	 * School pocket-money dashboard. Dates are Y-m-d or blank for all history.
	 *
	 * @return array<string, mixed>
	 */
	public function schoolReport(int $schoolId, string $report, string $from, string $to, string $q): array
	{
		$allowed = ['overview', 'canteen', 'ledger', 'topups', 'payments', 'refunds', 'problems', 'balances', 'daily', 'classes'];
		if (!in_array($report, $allowed, true)) {
			$report = 'overview';
		}
		$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : '';
		$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? $to : '';
		$q = trim(preg_replace('/[%_\\\\]/', '', $q) ?? '');
		$q = substr($q, 0, 40);
		try {
			$this->settleRefunds($schoolId);
		} catch (\Throwable $e) {
			log_message('error', 'Pocket refund check failed: ' . $e->getMessage());
		}

		$where = 's.school_id = ?';
		$params = [$schoolId];
		if ($from !== '') {
			$where .= ' AND p.created_at >= ?';
			$params[] = $from . ' 00:00:00';
		}
		if ($to !== '') {
			$where .= ' AND p.created_at <= ?';
			$params[] = $to . ' 23:59:59';
		}
		if ($q !== '') {
			$where .= ' AND (s.regno LIKE ? OR s.fname LIKE ? OR s.lname LIKE ? OR p.source LIKE ? OR p.reference_id LIKE ? OR p.txn_Id LIKE ?)';
			$like = '%' . $q . '%';
			array_push($params, $like, $like, $like, $like, $like, $like);
		}

		$summary = $this->db->query(
			"SELECT
				COALESCE(SUM(CASE WHEN p.type = 0 AND p.status = 1 THEN p.amount ELSE 0 END), 0) AS topup_amount,
				COALESCE(SUM(CASE WHEN p.type = 0 AND p.status = 1 THEN 1 ELSE 0 END), 0) AS topup_count,
				COALESCE(SUM(CASE WHEN p.type = 1 AND p.status = 1 THEN p.amount ELSE 0 END), 0) AS payment_amount,
				COALESCE(SUM(CASE WHEN p.type = 1 AND p.status = 1 THEN 1 ELSE 0 END), 0) AS payment_count,
				COALESCE(SUM(CASE WHEN p.type = 2 AND p.status = 1 THEN p.amount ELSE 0 END), 0) AS withdraw_amount,
				COALESCE(SUM(CASE WHEN p.type = 2 AND p.status = 1 THEN 1 ELSE 0 END), 0) AS withdraw_count,
				COALESCE(SUM(CASE WHEN p.type = 3 AND p.status = 1 THEN p.amount ELSE 0 END), 0) AS refund_amount,
				COALESCE(SUM(CASE WHEN p.type = 3 AND p.status = 1 THEN 1 ELSE 0 END), 0) AS refund_count,
				COALESCE(SUM(CASE WHEN p.status = 0 THEN 1 ELSE 0 END), 0) AS pending_count,
				COALESCE(SUM(CASE WHEN p.status = 2 THEN 1 ELSE 0 END), 0) AS failed_count
			 FROM payment_transactions p
			 INNER JOIN students s ON s.id = p.student_id
			 WHERE {$where}",
			$params
		)->getRowArray();

		$held = $this->db->query(
			'SELECT COALESCE(SUM(wallet_balance), 0) AS held,
			        COALESCE(SUM(CASE WHEN wallet_balance > 0 THEN 1 ELSE 0 END), 0) AS funded
			 FROM students WHERE school_id = ? AND status = 1',
			[$schoolId]
		)->getRowArray();
		$active = $this->db->query(
			'SELECT COUNT(DISTINCT p.student_id) AS total
			 FROM payment_transactions p
			 INNER JOIN students s ON s.id = p.student_id
			 WHERE s.school_id = ? AND p.status = 1',
			[$schoolId]
		)->getRowArray();

		$pack = [
			'report' => $report,
			'from' => $from,
			'to' => $to,
			'q' => $q,
			'summary' => $summary ?: [],
			'held' => (int) round((float) ($held['held'] ?? 0)),
			'fundedStudents' => (int) ($held['funded'] ?? 0),
			'activeStudents' => (int) ($active['total'] ?? 0),
			'rows' => [],
			'daily' => [],
			'classes' => [],
			'balances' => [],
			'topStudents' => [],
			'latest' => [],
			'canteenRows' => [],
			'canteenUsed' => 0,
			'canteenVisits' => 0,
			'canteenLoaded' => 0,
			'canteenRemaining' => 0,
		];

		if ($report === 'overview' || $report === 'ledger' || $report === 'topups' || $report === 'payments' || $report === 'refunds' || $report === 'problems') {
			$typeSql = '';
			if ($report === 'topups') {
				$typeSql = ' AND p.type = 0';
			} elseif ($report === 'payments') {
				$typeSql = ' AND p.type = 1';
			} elseif ($report === 'refunds') {
				$typeSql = ' AND p.type = 3';
			} elseif ($report === 'problems') {
				$typeSql = ' AND p.status IN (0, 2)';
			}
			$limit = $report === 'overview' ? 8 : 200;
			$rows = $this->db->query(
				"SELECT p.id, p.created_at, p.amount, p.type, p.status, p.source, p.balance, p.txn_fee,
				        p.txn_Id, p.reference_id, p.extra_options, p.tx_error,
				        s.id AS student_id, s.fname, s.lname, s.regno, s.card, s.wallet_balance,
				        sk.pocket_money_phone, sk.mtn_momo_phone,
				        TRIM(CONCAT(IFNULL(l.title,''), ' ', IFNULL(d.code,''), ' ', IFNULL(c.title,''))) AS class_name
				 FROM payment_transactions p
				 INNER JOIN students s ON s.id = p.student_id
				 INNER JOIN schools sk ON sk.id = s.school_id
				 LEFT JOIN class_records cr ON cr.id = (
				 	SELECT MAX(id) FROM class_records WHERE student = s.id AND status = 1
				 )
				 LEFT JOIN classes c ON c.id = cr.class
				 LEFT JOIN departments d ON d.id = c.department
				 LEFT JOIN levels l ON l.id = c.level
				 WHERE {$where}{$typeSql}
				 ORDER BY p.id DESC
				 LIMIT {$limit}",
				$params
			)->getResultArray();
			$reserved = $this->refundState($schoolId);
			$gateway = BesoftGatewayClient::make();
			$clean = [];
			foreach ($rows as $row) {
				$clean[] = $this->presentMovement($row, $reserved, $gateway);
			}
			if ($report === 'overview') {
				$pack['latest'] = $clean;
			} else {
				$pack['rows'] = $clean;
			}
		}

		if ($report === 'overview' || $report === 'daily') {
			$pack['daily'] = $this->db->query(
				"SELECT DATE(p.created_at) AS day,
				        COALESCE(SUM(CASE WHEN p.type = 0 AND p.status = 1 THEN p.amount ELSE 0 END), 0) AS topups,
				        COALESCE(SUM(CASE WHEN p.type = 1 AND p.status = 1 THEN p.amount ELSE 0 END), 0) AS spends,
				        COALESCE(SUM(CASE WHEN p.type = 3 AND p.status = 1 THEN p.amount ELSE 0 END), 0) AS refunds,
				        COUNT(*) AS txns
				 FROM payment_transactions p
				 INNER JOIN students s ON s.id = p.student_id
				 WHERE {$where}
				 GROUP BY DATE(p.created_at)
				 ORDER BY day DESC
				 LIMIT 31",
				$params
			)->getResultArray();
		}

		if ($report === 'overview') {
			$pack['topStudents'] = $this->db->query(
				"SELECT s.id, TRIM(CONCAT(s.fname, ' ', s.lname)) AS student, s.regno,
				        COUNT(p.id) AS times, COALESCE(SUM(p.amount), 0) AS total
				 FROM payment_transactions p
				 INNER JOIN students s ON s.id = p.student_id
				 WHERE {$where} AND p.status = 1
				 GROUP BY s.id, s.fname, s.lname, s.regno
				 ORDER BY times DESC, total DESC
				 LIMIT 8",
				$params
			)->getResultArray();
		}

		if ($report === 'classes') {
			$pack['classes'] = $this->db->query(
				"SELECT TRIM(CONCAT(IFNULL(l.title,''), ' ', IFNULL(d.code,''), ' ', IFNULL(c.title,''))) AS class_name,
				        COUNT(DISTINCT s.id) AS students,
				        COALESCE(SUM(CASE WHEN p.type = 0 AND p.status = 1 THEN p.amount ELSE 0 END), 0) AS topups,
				        COALESCE(SUM(CASE WHEN p.type = 1 AND p.status = 1 THEN p.amount ELSE 0 END), 0) AS spends,
				        COALESCE(SUM(CASE WHEN p.type = 3 AND p.status = 1 THEN p.amount ELSE 0 END), 0) AS refunds
				 FROM payment_transactions p
				 INNER JOIN students s ON s.id = p.student_id
				 LEFT JOIN class_records cr ON cr.id = (
				 	SELECT MAX(id) FROM class_records WHERE student = s.id AND status = 1
				 )
				 LEFT JOIN classes c ON c.id = cr.class
				 LEFT JOIN departments d ON d.id = c.department
				 LEFT JOIN levels l ON l.id = c.level
				 WHERE {$where}
				 GROUP BY l.title, d.code, c.title
				 ORDER BY topups DESC
				 LIMIT 50",
				$params
			)->getResultArray();
		}

		$use = $this->canteenUse($schoolId, $from, $to, $q);
		$pack['canteenRows'] = $use['rows'];
		$pack['canteenUsed'] = $use['used'];
		$pack['canteenVisits'] = $use['visits'];
		$pack['canteenLoaded'] = $use['loaded'];
		$pack['canteenRemaining'] = $use['remaining'];

		if ($report === 'balances') {
			$pack['balances'] = $this->db->query(
				"SELECT s.id, TRIM(CONCAT(s.fname, ' ', s.lname)) AS name, s.regno, s.card, s.wallet_balance,
				        TRIM(CONCAT(IFNULL(l.title,''), ' ', IFNULL(d.code,''), ' ', IFNULL(c.title,''))) AS class_name,
				        (SELECT MAX(created_at) FROM payment_transactions WHERE student_id = s.id) AS last_at
				 FROM students s
				 LEFT JOIN class_records cr ON cr.id = (
				 	SELECT MAX(id) FROM class_records WHERE student = s.id AND status = 1
				 )
				 LEFT JOIN classes c ON c.id = cr.class
				 LEFT JOIN departments d ON d.id = c.department
				 LEFT JOIN levels l ON l.id = c.level
				 WHERE s.school_id = ? AND s.status = 1 AND (s.wallet_balance > 0 OR (s.card IS NOT NULL AND s.card <> ''))
				 ORDER BY s.wallet_balance DESC, s.fname ASC",
				[$schoolId]
			)->getResultArray();
		}

		return $pack;
	}

	/**
	 * Money already received, already spent at the canteen, and still on the card.
	 *
	 * @return array{rows:array<int,array<string,mixed>>,used:int,visits:int,loaded:int}
	 */
	protected function canteenUse(int $schoolId, string $from, string $to, string $q): array
	{
		$spendWhere = "p.type = 1 AND p.status = 1 AND p.source = 'POS'";
		$loadWhere = 'p.type = 0 AND p.status = 1';
		$dateSql = '';
		$dates = [];
		if ($from !== '') {
			$dateSql .= ' AND p.created_at >= ?';
			$dates[] = $from . ' 00:00:00';
		}
		if ($to !== '') {
			$dateSql .= ' AND p.created_at <= ?';
			$dates[] = $to . ' 23:59:59';
		}
		$studentSql = 's.school_id = ? AND s.status = 1';
		$studentParams = [$schoolId];
		if ($q !== '') {
			$studentSql .= ' AND (s.regno LIKE ? OR s.fname LIKE ? OR s.lname LIKE ?)';
			$like = '%' . $q . '%';
			array_push($studentParams, $like, $like, $like);
		}
		$totals = $this->db->query(
			"SELECT
				COALESCE(SUM(CASE WHEN {$loadWhere}{$dateSql} THEN p.amount ELSE 0 END), 0) AS loaded,
				COALESCE(SUM(CASE WHEN {$spendWhere}{$dateSql} THEN p.amount ELSE 0 END), 0) AS used,
				COALESCE(SUM(CASE WHEN {$spendWhere}{$dateSql} THEN 1 ELSE 0 END), 0) AS visits
			 FROM payment_transactions p
			 INNER JOIN students s ON s.id = p.student_id
			 WHERE {$studentSql}",
			array_merge($dates, $dates, $dates, $studentParams)
		)->getRowArray();
		$held = $this->db->query(
			"SELECT COALESCE(SUM(s.wallet_balance), 0) AS remaining
			 FROM students s
			 WHERE {$studentSql}",
			$studentParams
		)->getRowArray();
		$rows = $this->db->query(
			"SELECT s.id,
			        TRIM(CONCAT(s.fname, ' ', s.lname)) AS name,
			        s.regno,
			        s.wallet_balance AS remaining,
			        TRIM(CONCAT(IFNULL(l.title,''), ' ', IFNULL(d.code,''), ' ', IFNULL(c.title,''))) AS class_name,
			        COALESCE((
			        	SELECT SUM(p.amount) FROM payment_transactions p
			        	WHERE p.student_id = s.id AND {$loadWhere}{$dateSql}
			        ), 0) AS loaded,
			        COALESCE((
			        	SELECT SUM(p.amount) FROM payment_transactions p
			        	WHERE p.student_id = s.id AND {$spendWhere}{$dateSql}
			        ), 0) AS used,
			        COALESCE((
			        	SELECT COUNT(*) FROM payment_transactions p
			        	WHERE p.student_id = s.id AND {$spendWhere}{$dateSql}
			        ), 0) AS visits,
			        (
			        	SELECT GROUP_CONCAT(JSON_UNQUOTE(JSON_EXTRACT(p.extra_options, '$.items')) ORDER BY p.id SEPARATOR ', ')
			        	FROM payment_transactions p
			        	WHERE p.student_id = s.id AND {$spendWhere}{$dateSql}
			        ) AS items
			 FROM students s
			 LEFT JOIN class_records cr ON cr.id = (
			 	SELECT MAX(id) FROM class_records WHERE student = s.id AND status = 1
			 )
			 LEFT JOIN classes c ON c.id = cr.class
			 LEFT JOIN departments d ON d.id = c.department
			 LEFT JOIN levels l ON l.id = c.level
			 WHERE {$studentSql}
			   AND (
			   	s.wallet_balance > 0
			   	OR EXISTS (SELECT 1 FROM payment_transactions p WHERE p.student_id = s.id AND {$loadWhere})
			   	OR EXISTS (SELECT 1 FROM payment_transactions p WHERE p.student_id = s.id AND {$spendWhere})
			   )
			 ORDER BY used DESC, s.wallet_balance DESC, s.fname ASC
			 LIMIT 400",
			array_merge($dates, $dates, $dates, $dates, $studentParams)
		)->getResultArray();

		return [
			'rows' => $rows,
			'used' => (int) round((float) ($totals['used'] ?? 0)),
			'visits' => (int) ($totals['visits'] ?? 0),
			'loaded' => (int) round((float) ($totals['loaded'] ?? 0)),
			'remaining' => (int) round((float) ($held['remaining'] ?? 0)),
		];
	}

	/**
	 * Send a paid top-up back to the Mobile Money number that paid it.
	 * The school pocket-money phone must approve the prompt.
	 */
	public function reverseToSender(int $schoolId, int $staffId, int $paymentId): string
	{
		$gateway = BesoftGatewayClient::make();
		if (!$gateway->isConfigured()) {
			throw new \RuntimeException('Mobile Money is not configured.');
		}
		$held = $this->heldSendBack((int) $paymentId);
		if ($held !== '') {
			throw new \RuntimeException($held);
		}
		$this->settleRefunds($schoolId);

		$this->db->transBegin();
		$refundId = 0;
		try {
			$payment = $this->db->query(
				'SELECT p.*, s.school_id, s.wallet_balance, s.fname, s.lname, s.regno, s.status AS student_status,
				        sk.pocket_money_phone, sk.mtn_momo_phone
				 FROM payment_transactions p
				 INNER JOIN students s ON s.id = p.student_id
				 INNER JOIN schools sk ON sk.id = s.school_id
				 WHERE p.id = ? AND s.school_id = ?
				 FOR UPDATE',
				[$paymentId, $schoolId]
			)->getRowArray();
			if (!$payment || (int) $payment['type'] !== self::TYPE_TOPUP || (int) $payment['status'] !== 1) {
				throw new \RuntimeException('Only a completed top-up can be sent back.');
			}
			if ((int) $payment['student_status'] !== 1) {
				throw new \RuntimeException('This student account is not active.');
			}
			$extra = json_decode((string) ($payment['extra_options'] ?? ''), true);
			if (!is_array($extra)) {
				$extra = [];
			}
			$schoolPhone = $this->schoolReceiver($gateway, $payment);
			if ($schoolPhone === '') {
				throw new \RuntimeException('Set MOMO account (pocket money) in Settings. The registration fees number is not used.');
			}
			$phone = $gateway->normalizeMsisdn((string) ($extra['phone'] ?? $payment['source'] ?? ''));
			if (!preg_match('/^2507\d{8}$/', $phone)) {
				throw new \RuntimeException('This top-up has no Mobile Money number to send the money back to.');
			}
			$approvePhone = $schoolPhone;
			$reservedRow = $this->db->query(
				'SELECT COALESCE(SUM(amount), 0) AS total
				 FROM payment_transactions
				 WHERE student_id = ? AND type = ? AND status IN (0, 1) AND reference_id = ?',
				[(int) $payment['student_id'], self::TYPE_REFUND, (string) $paymentId]
			)->getRowArray();
			$reserved = (int) round((float) ($reservedRow['total'] ?? 0));
			$original = (int) round((float) $payment['amount']);
			$left = $original - $reserved;
			$balance = (int) round((float) $payment['wallet_balance']);
			if ($left < 100) {
				throw new \RuntimeException('This top-up was already sent back, or a send-back is still waiting for approval.');
			}
			$amount = min($left, $balance);
			if ($amount < 100) {
				throw new \RuntimeException('Not enough money is left on this card. Balance is ' . number_format($balance) . ' RWF.');
			}
			$next = $balance - $amount;
			$this->db->query('UPDATE students SET wallet_balance = ? WHERE id = ?', [$next, (int) $payment['student_id']]);
			$refundId = (new PaymentModel())->insert([
				'student_id' => (int) $payment['student_id'],
				'amount' => $amount,
				'type' => self::TYPE_REFUND,
				'source' => $phone,
				'balance' => $next,
				'txn_fee' => 0,
				'txn_Id' => substr('rfd' . time(), 0, 50),
				'reference_id' => (string) $paymentId,
				'status' => 0,
				'created_by' => $staffId,
				'extra_options' => json_encode([
					'original_payment_id' => (int) $paymentId,
					'provider' => 'besoft',
					'phone' => $phone,
					'school_phone' => $approvePhone,
					'card' => strtoupper(trim((string) ($extra['card'] ?? ''))),
				]),
			]);
			if ($this->db->transStatus() === false || !$refundId) {
				throw new \RuntimeException('The send-back could not be saved.');
			}
			$this->db->transCommit();
		} catch (\Throwable $e) {
			$this->db->transRollback();
			throw $e instanceof \RuntimeException ? $e : new \RuntimeException('The send-back could not be saved.');
		}

		$regno = preg_replace('/[^A-Za-z0-9]/', '', (string) $payment['regno']);
		$regno = substr($regno ?: 'STU', 0, 16);
		$name = trim($payment['fname'] . ' ' . $payment['lname']);
		try {
			$result = $gateway->collectAndPay([
				'payer' => $approvePhone,
				'payee' => $phone,
				'amount' => $amount,
				'fee_from_payer' => true,
				'idempotency_key' => 'rfd' . (int) $refundId,
				'description' => 'Return pocket money ' . $regno,
			]);
		} catch (\Throwable $e) {
			$this->restoreRefund((int) $refundId, 'Mobile Money could not start.');
			throw new \RuntimeException('Mobile Money could not start. The money is back on the student card.');
		}
		if (empty($result['ok'])) {
			$msg = (string) ($result['error_message'] ?? 'Mobile Money could not send the money back.');
			$this->restoreRefund((int) $refundId, $msg);
			throw new \RuntimeException($msg . ' The money is back on the student card.');
		}
		$realTx = substr((string) ($result['transaction_id'] ?? ''), 0, 50);
		$this->db->table('payment_transactions')->where('id', (int) $refundId)->update([
			'txn_Id' => $realTx,
			'txn_fee' => (int) ($result['fee'] ?? 0),
			'updated_at' => $this->now(),
		]);

		$debit = (int) ($result['debit'] ?? $amount);
		$fee = (int) ($result['fee'] ?? 0);
		$message = number_format($amount) . ' RWF is leaving ' . $name . "'s card. Approve " . number_format($debit) . ' RWF on the pocket money agent ' . $approvePhone . '. ' . number_format($amount) . ' RWF will arrive on ' . $phone . '.';
		if ($fee > 0) {
			$message .= ' ' . number_format($fee) . ' RWF is the charge, taken from the pocket money phone.';
		}

		return $message;
	}

	/**
	 * The pocket phone already paid and the sender did not receive it.
	 * Check that same payment again. Do not collect a second time.
	 */
	public function retryRefund(int $schoolId, int $staffId, int $refundId): string
	{
		$refund = $this->db->query(
			'SELECT p.*, s.school_id, s.fname, s.lname
			 FROM payment_transactions p
			 INNER JOIN students s ON s.id = p.student_id
			 WHERE p.id = ? AND s.school_id = ? AND p.type = ?',
			[$refundId, $schoolId, self::TYPE_REFUND]
		)->getRowArray();
		if (!$refund) {
			throw new \RuntimeException('That send-back was not found.');
		}
		if ((int) $refund['status'] === 1) {
			return 'This payout already reached the sender.';
		}
		if (!$this->isUuid((string) $refund['txn_Id'])) {
			throw new \RuntimeException('This send-back cannot be retried.');
		}
		$status = $this->providerStatus($refund);
		if (!empty($status['success']) && empty($status['settlement_failed'])) {
			return $this->applyHeldRefund($refund);
		}
		if (empty($status['settlement_failed'])) {
			throw new \RuntimeException('That collection was returned to the pocket money phone. Use Send back on the original top-up.');
		}
		$extra = json_decode((string) ($refund['extra_options'] ?? ''), true);
		$phone = is_array($extra) ? trim((string) ($extra['phone'] ?? $refund['source'] ?? '')) : (string) $refund['source'];
		$reason = trim((string) ($status['error_message'] ?? ''));
		if ($reason === '') {
			$reason = 'Mobile Money could not pay ' . $phone . '.';
		}
		$this->db->table('payment_transactions')->where('id', (int) $refund['id'])->update([
			'tx_error' => substr($reason, 0, 100),
			'updated_at' => $this->now(),
		]);

		throw new \RuntimeException($reason . ' The pocket phone was not charged again. The ' . number_format((int) round((float) $refund['amount'])) . ' RWF already taken has still not reached ' . $phone . '.');
	}

	/**
	 * @param array<string, mixed> $refund
	 */
	protected function applyHeldRefund(array $refund): string
	{
		$this->db->transBegin();
		try {
			$row = $this->db->query(
				'SELECT * FROM payment_transactions WHERE id = ? AND type = ? FOR UPDATE',
				[(int) $refund['id'], self::TYPE_REFUND]
			)->getRowArray();
			if (!$row) {
				throw new \RuntimeException('That send-back was not found.');
			}
			if ((int) $row['status'] === 1) {
				$this->db->transCommit();

				return 'This payout already reached the sender.';
			}
			$amount = (int) round((float) $row['amount']);
			$student = $this->db->query(
				'SELECT wallet_balance FROM students WHERE id = ? FOR UPDATE',
				[(int) $row['student_id']]
			)->getRowArray();
			$balance = (int) round((float) ($student['wallet_balance'] ?? 0));
			if ($balance < $amount) {
				throw new \RuntimeException('The sender was paid, but the card only has ' . number_format($balance) . ' RWF left.');
			}
			$next = $balance - $amount;
			$this->db->query('UPDATE students SET wallet_balance = ? WHERE id = ?', [$next, (int) $row['student_id']]);
			$this->db->table('payment_transactions')->where('id', (int) $row['id'])->update([
				'status' => 1,
				'balance' => $next,
				'tx_error' => null,
				'updated_at' => $this->now(),
			]);
			if ($this->db->transStatus() === false) {
				throw new \RuntimeException('The card could not be updated.');
			}
			$this->db->transCommit();
		} catch (\Throwable $e) {
			$this->db->transRollback();
			throw $e instanceof \RuntimeException ? $e : new \RuntimeException('The card could not be updated.');
		}

		return number_format($amount) . ' RWF reached the sender and is now off the student card.';
	}

	public function settleRefunds(int $schoolId): void
	{
		$rows = $this->db->query(
			'SELECT p.id, p.txn_Id, p.extra_options, p.type, p.status
			 FROM payment_transactions p
			 INNER JOIN students s ON s.id = p.student_id
			 WHERE s.school_id = ? AND p.type = ? AND p.status = 0
			 ORDER BY p.id DESC
			 LIMIT 8',
			[$schoolId, self::TYPE_REFUND]
		)->getResultArray();
		foreach ($rows as $row) {
			$this->finishRefundRow($row);
		}
		$this->settleTopups($schoolId);
	}

	public function settleTopups(int $schoolId): void
	{
		$rows = $this->db->query(
			'SELECT p.*
			 FROM payment_transactions p
			 INNER JOIN students s ON s.id = p.student_id
			 WHERE s.school_id = ? AND p.type = ? AND p.status = 0
			 ORDER BY p.id DESC
			 LIMIT 8',
			[$schoolId, self::TYPE_TOPUP]
		)->getResultArray();
		foreach ($rows as $row) {
			$this->finishTopupRow($row);
		}
	}

	/**
	 * BeSoft status notice. The posted body is not trusted; the live status is read from BeSoft.
	 *
	 * @param array<string, mixed> $body
	 * @return array<string, mixed>
	 */
	public function applyBesoftNotice(array $body): array
	{
		$checked = 0;
		foreach ($this->besoftIds($body) as $id) {
			$row = $this->db->query(
				'SELECT * FROM payment_transactions WHERE txn_Id = ? AND status = 0 LIMIT 1',
				[$id]
			)->getRowArray();
			if (!$row) {
				continue;
			}
			$checked++;
			if ((int) $row['type'] === self::TYPE_TOPUP) {
				$this->finishTopupRow($row);
			} elseif ((int) $row['type'] === self::TYPE_REFUND) {
				$this->finishRefundRow($row);
			}
		}

		return ['ok' => true, 'checked' => $checked];
	}

	/**
	 * @param array<string, mixed> $row
	 */
	protected function finishTopupRow(array $row): void
	{
		if ((int) ($row['status'] ?? 0) !== 0 || (int) ($row['type'] ?? -1) !== self::TYPE_TOPUP) {
			return;
		}
		try {
			$status = $this->providerStatus($row);
		} catch (\Throwable $e) {
			return;
		}
		if (!empty($status['success']) && empty($status['settlement_failed'])) {
			try {
				$this->creditOnce((int) $row['id'], $status);
			} catch (\Throwable $e) {
				log_message('error', 'Pocket top-up credit failed: ' . $e->getMessage());
			}

			return;
		}
		if (!empty($status['failed'])) {
			$msg = !empty($status['settlement_failed'])
				? 'The pocket money account did not receive the payment, so the card was not updated.'
				: (string) ($status['error_message'] ?? 'The payment was not approved.');
			$this->db->table('payment_transactions')->where('id', (int) $row['id'])->where('status', 0)->update([
				'status' => 2,
				'tx_error' => substr($msg, 0, 100),
				'updated_at' => $this->now(),
			]);
		}
	}

	/**
	 * @param array<string, mixed> $row
	 */
	protected function finishRefundRow(array $row): void
	{
		if ((int) ($row['status'] ?? 0) !== 0) {
			return;
		}
		try {
			$status = $this->providerStatus($row);
		} catch (\Throwable $e) {
			return;
		}
		if (!empty($status['success']) && empty($status['settlement_failed'])) {
			$this->db->table('payment_transactions')->where('id', (int) $row['id'])->where('status', 0)->update([
				'status' => 1,
				'tx_error' => null,
				'updated_at' => $this->now(),
			]);
		} elseif (!empty($status['failed']) || !empty($status['settlement_failed'])) {
			$this->restoreRefund((int) $row['id'], (string) ($status['error_message'] ?? 'The pocket money phone did not approve.'));
		}
	}

	/**
	 * @param array<string, mixed> $payment
	 * @return array<string, mixed>
	 */
	protected function providerStatus(array $payment): array
	{
		$extra = json_decode((string) ($payment['extra_options'] ?? ''), true);
		$provider = is_array($extra) ? (string) ($extra['provider'] ?? '') : '';
		$txn = trim((string) ($payment['txn_Id'] ?? ''));
		$pending = [
			'ok' => false,
			'success' => false,
			'failed' => false,
			'error_message' => null,
			'response' => null,
		];
		if ($provider === 'mopay') {
			$gateway = MopayGatewayClient::make();
			if (!$gateway->isConfigured() || $txn === '') {
				return $pending;
			}

			return $gateway->transactionStatus($txn);
		}
		if (!$this->isUuid($txn) || !BesoftGatewayClient::make()->isConfigured()) {
			return $pending;
		}

		return BesoftGatewayClient::make()->transactionStatus($txn);
	}

	/**
	 * @param mixed $node
	 * @return array<int, string>
	 */
	protected function besoftIds($node, int $depth = 0): array
	{
		if ($depth > 6) {
			return [];
		}
		if (is_string($node) && $this->isUuid($node)) {
			return [strtolower($node)];
		}
		if (!is_array($node)) {
			return [];
		}
		$ids = [];
		foreach ($node as $value) {
			foreach ($this->besoftIds($value, $depth + 1) as $id) {
				$ids[$id] = $id;
				if (count($ids) >= 8) {
					return array_values($ids);
				}
			}
		}

		return array_values($ids);
	}

	protected function isUuid(string $value): bool
	{
		return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', trim($value));
	}

	/**
	 * @param array<string, mixed> $row
	 * @param array<string, array{reserved:int,pending:int}> $reserved
	 * @return array<string, mixed>
	 */
	protected function presentMovement(array $row, array $reserved, BesoftGatewayClient $gateway): array
	{
		$extra = json_decode((string) ($row['extra_options'] ?? ''), true);
		if (!is_array($extra)) {
			$extra = [];
		}
		$phone = $gateway->normalizeMsisdn((string) ($extra['phone'] ?? $row['source'] ?? ''));
		$schoolPhone = $this->schoolReceiver($gateway, $row);
		if (!preg_match('/^2507\d{8}$/', $phone)) {
			$phone = '';
		}
		$original = (int) round((float) $row['amount']);
		$state = $reserved[(string) $row['id']] ?? ['reserved' => 0, 'pending' => 0];
		$heldBack = (int) $state['reserved'];
		$pendingBack = (int) $state['pending'];
		$balance = (int) round((float) $row['wallet_balance']);
		$send = 0;
		if ((int) $row['type'] === self::TYPE_TOPUP && (int) $row['status'] === 1 && $phone !== '' && $pendingBack === 0) {
			$send = min(max(0, $original - $heldBack), $balance);
			if ($send < 100) {
				$send = 0;
			}
		}
		$name = trim(preg_replace('/\s+/', ' ', trim((string) $row['fname']) . ' ' . trim((string) $row['lname'])));

		return [
			'id' => (int) $row['id'],
			'created_at' => (string) $row['created_at'],
			'amount' => $original,
			'type' => (int) $row['type'],
			'status' => (int) $row['status'],
			'source' => (string) $row['source'],
			'phone' => (string) $phone,
			'school_phone' => (string) $schoolPhone,
			'balance' => $row['balance'] === null ? null : (int) round((float) $row['balance']),
			'wallet' => $balance,
			'txn_fee' => (int) round((float) ($row['txn_fee'] ?? 0)),
			'txn_id' => (string) $row['txn_Id'],
			'reference' => (string) ($row['reference_id'] ?? ''),
			'error' => (string) ($row['tx_error'] ?? ''),
			'name' => $name !== '' ? $name : 'Student',
			'regno' => (string) $row['regno'],
			'card' => strtoupper(trim((string) ($row['card'] ?? ''))),
			'class_name' => trim((string) ($row['class_name'] ?? '')),
			'send_back' => $send,
			'waiting' => $pendingBack > 0,
			'sent_back' => $heldBack >= $original && $original > 0 && $pendingBack === 0,
			'can_retry' => (int) $row['type'] === self::TYPE_REFUND && (int) $row['status'] === 2 && $this->isUuid((string) $row['txn_Id']),
		];
	}

	/**
	 * @return array<string, array{reserved:int,pending:int}>
	 */
	protected function refundState(int $schoolId): array
	{
		$rows = $this->db->query(
			'SELECT p.reference_id,
			        COALESCE(SUM(CASE WHEN p.status IN (0, 1) THEN p.amount ELSE 0 END), 0) AS reserved,
			        COALESCE(SUM(CASE WHEN p.status = 0 THEN p.amount ELSE 0 END), 0) AS pending
			 FROM payment_transactions p
			 INNER JOIN students s ON s.id = p.student_id
			 WHERE s.school_id = ? AND p.type = ? AND p.reference_id IS NOT NULL AND p.reference_id <> ""
			 GROUP BY p.reference_id',
			[$schoolId, self::TYPE_REFUND]
		)->getResultArray();
		$map = [];
		foreach ($rows as $row) {
			$map[(string) $row['reference_id']] = [
				'reserved' => (int) round((float) $row['reserved']),
				'pending' => (int) round((float) $row['pending']),
			];
		}

		return $map;
	}

	protected function heldSendBack(int $paymentId): string
	{
		$rows = $this->db->query(
			'SELECT * FROM payment_transactions WHERE type = ? AND reference_id = ? AND status = 2 ORDER BY id DESC LIMIT 3',
			[self::TYPE_REFUND, (string) $paymentId]
		)->getResultArray();
		foreach ($rows as $row) {
			try {
				$status = $this->providerStatus($row);
			} catch (\Throwable $e) {
				continue;
			}
			if (empty($status['settlement_failed'])) {
				continue;
			}
			$extra = json_decode((string) ($row['extra_options'] ?? ''), true);
			$phone = is_array($extra) ? trim((string) ($extra['phone'] ?? '')) : '';
			if ($phone !== '' && stripos((string) ($status['error_message'] ?? ''), 'could not find') !== false) {
				return 'Mobile Money could not find ' . $phone . '. That send-back already collected money from the pocket money phone and it has not been returned, so another send-back is blocked. The student card was not reduced.';
			}

			return 'A send-back already collected money from the pocket money phone, but the sender was not paid. That collection has not been returned, so another send-back is blocked. The student card was not reduced.';
		}

		return '';
	}

	protected function restoreRefund(int $refundId, string $reason): void
	{
		$this->db->transBegin();
		try {
			$refund = $this->db->query(
				'SELECT * FROM payment_transactions WHERE id = ? AND type = ? FOR UPDATE',
				[$refundId, self::TYPE_REFUND]
			)->getRowArray();
			if (!$refund || (int) $refund['status'] !== 0) {
				$this->db->transCommit();

				return;
			}
			$amount = (int) round((float) $refund['amount']);
			$this->db->query(
				'UPDATE students SET wallet_balance = wallet_balance + ? WHERE id = ?',
				[$amount, (int) $refund['student_id']]
			);
			$student = $this->db->query('SELECT wallet_balance FROM students WHERE id = ?', [(int) $refund['student_id']])->getRowArray();
			$this->db->table('payment_transactions')->where('id', $refundId)->where('status', 0)->update([
				'status' => 2,
				'balance' => (int) round((float) ($student['wallet_balance'] ?? 0)),
				'tx_error' => substr($reason, 0, 100),
				'updated_at' => $this->now(),
			]);
			if ($this->db->transStatus() === false) {
				throw new \RuntimeException('Could not restore the student card.');
			}
			$this->db->transCommit();
		} catch (\Throwable $e) {
			$this->db->transRollback();
			log_message('error', 'Pocket refund restore failed: ' . $e->getMessage());
		}
	}
}
