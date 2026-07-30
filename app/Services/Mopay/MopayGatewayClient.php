<?php

namespace App\Services\Mopay;

use Config\Mopay as MopayConfig;

/**
 * Portable MoPay Gateway V1 client (aligned with Xander Academy / Kalisa FR).
 * PHP 7.4 compatible — uses CI curlrequest, file cache for tokens.
 */
class MopayGatewayClient
{
	/** @var array<string, mixed> */
	protected $config;

	/**
	 * @param array<string, mixed>|null $config
	 */
	public function __construct(?array $config = null)
	{
		if ($config === null) {
			$cfg = new MopayConfig();
			$config = $cfg->toArray();
		}
		$this->config = $config;
	}

	/**
	 * @param array<string, mixed> $config
	 */
	public static function fromConfig(array $config): self
	{
		return new self($config);
	}

	public static function make(): self
	{
		return new self();
	}

	public function isConfigured(): bool
	{
		return trim((string) ($this->config['auth_key'] ?? '')) !== ''
			&& trim((string) ($this->config['server_base_url'] ?? '')) !== '';
	}

	public function projectSlug(): string
	{
		$slug = preg_replace('/[^a-z0-9_-]+/i', '_', (string) ($this->config['project_slug'] ?? 'app'));
		if ($slug === null || $slug === '') {
			$slug = 'app';
		}

		return strtolower($slug);
	}

	public function serverBaseUrl(): string
	{
		return rtrim((string) ($this->config['server_base_url'] ?? ''), '/');
	}

	/**
	 * Rwanda MoMo MSISDN as 12 digits: 2507XXXXXXXX
	 */
	public function normalizeMsisdn(string $phone): string
	{
		$digits = preg_replace('/\D+/', '', $phone);
		if ($digits === null) {
			$digits = '';
		}

		if (strpos($digits, '250') === 0 && strlen($digits) >= 12) {
			return substr($digits, 0, 12);
		}
		if (strpos($digits, '0') === 0 && strlen($digits) >= 10) {
			return '250' . substr($digits, 1, 9);
		}
		if (strlen($digits) === 9 && strpos($digits, '7') === 0) {
			return '250' . $digits;
		}

		return $digits;
	}

	public function authorizationHeader(): string
	{
		$bearer = trim((string) ($this->config['bearer_token'] ?? ''));
		if ($bearer !== '') {
			return stripos($bearer, 'bearer ') === 0 ? $bearer : 'Bearer ' . $bearer;
		}

		$cached = $this->readTokenCache();
		if (is_array($cached) && !empty($cached['access_token']) && !empty($cached['expires_at'])) {
			if (time() < ((int) $cached['expires_at'] - 60)) {
				return 'Bearer ' . (string) $cached['access_token'];
			}
		}

		$authKey = trim((string) ($this->config['auth_key'] ?? ''));
		if ($authKey === '') {
			throw new \RuntimeException('Missing MOPAY_AUTH_KEY.');
		}

		$token = $this->fetchAccessToken($authKey);
		if ($token !== null) {
			return 'Bearer ' . $token;
		}

		return $authKey;
	}

	protected function fetchAccessToken(string $authKey): ?string
	{
		$basicCredential = $authKey;
		if (stripos($basicCredential, 'basic ') === 0) {
			$basicCredential = trim(substr($basicCredential, 6));
		}

		$serverBase = $this->serverBaseUrl();
		$tokenUrls = [];

		$explicit = trim((string) ($this->config['token_url'] ?? ''));
		if ($explicit !== '') {
			$tokenUrls[] = $explicit;
		}
		if ($serverBase !== '') {
			$tokenUrls[] = $serverBase . '/token';
			$tokenUrls[] = preg_replace('#^http://#', 'https://', $serverBase) . '/token';
		}
		$tokenUrls[] = 'https://preproduction-gateway.bizao.com/token';
		$tokenUrls = array_values(array_unique(array_filter($tokenUrls)));

		foreach ($tokenUrls as $tokenUrl) {
			try {
				$res = $this->httpRequest('POST', $tokenUrl, [
					'Authorization' => 'Basic ' . $basicCredential,
					'Content-Type' => 'application/x-www-form-urlencoded',
					'Accept' => 'application/json',
				], 'grant_type=client_credentials', false);

				$body = $res['json'];
				if ($res['status'] < 200 || $res['status'] >= 300 || !is_array($body) || empty($body['access_token'])) {
					log_message('debug', 'MoPay token fetch skipped url=' . $tokenUrl . ' http=' . $res['status']);
					continue;
				}

				$accessToken = (string) $body['access_token'];
				$expiresIn = (int) ($body['expires_in'] ?? 3600);
				$expiresAt = time() + max(60, $expiresIn);
				$this->writeTokenCache([
					'access_token' => $accessToken,
					'expires_at' => $expiresAt,
					'token_url' => $tokenUrl,
				], max(60, $expiresIn));

				return $accessToken;
			} catch (\Throwable $e) {
				log_message('warning', 'MoPay token fetch failed: ' . $e->getMessage());
			}
		}

		return null;
	}

	/**
	 * Collect money from a customer MoMo wallet (payment+transfer or debit-only).
	 *
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public function initiateCollection(array $input): array
	{
		$amount = (int) ($input['amount'] ?? 0);
		$transactionId = (string) ($input['transaction_id'] ?? '');
		$accountNo = $this->normalizeMsisdn((string) ($input['account_no'] ?? ''));
		$currency = (string) ($input['currency'] ?? $this->config['default_currency'] ?? 'RWF');
		$country = strtolower((string) ($input['country_code'] ?? $this->config['default_country_code'] ?? 'rw'));
		$mno = (string) ($input['mno'] ?? $this->config['default_mno'] ?? 'mtn');
		$receiver = trim((string) ($input['receiver_account_no'] ?? $this->config['receiver_account_no'] ?? ''));
		$useTransfer = ($input['use_transfer'] ?? true) && $receiver !== '';

		$prefix = preg_replace('/[^A-Za-z0-9_]/', '', (string) ($this->config['message_prefix'] ?? 'MOPAY'));
		if ($prefix === null || $prefix === '') {
			$prefix = 'MOPAY';
		}
		$message = (string) ($input['message'] ?? ($prefix . '_PAYMENT'));
		$transferMessage = (string) ($input['transfer_message'] ?? ($prefix . '_RECEIVER_TRANSFER'));

		$base = $this->serverBaseUrl();
		$url = $useTransfer ? $base . '/api/v1/payment' : $base . '/api/v2/momo/debit';

		if ($useTransfer) {
			$receiverMsisdn = $this->normalizeMsisdn($receiver);
			$payload = [
				'transactionId' => $transactionId,
				'account_no' => $accountNo,
				'title' => (string) ($input['title'] ?? $this->config['payment_title'] ?? 'Service Payment'),
				'details' => (string) ($input['details'] ?? $this->config['payment_details'] ?? 'Authorized customer payment'),
				'payment_type' => 'momo',
				'amount' => $amount,
				'currency' => $currency,
				'message' => $message,
				'transfers' => [[
					'transactionId' => $transactionId . '_T',
					'account_no' => $receiverMsisdn,
					'payment_type' => 'momo',
					'amount' => $amount,
					'currency' => $currency,
					'message' => $transferMessage,
				]],
			];
			$flow = 'payment_with_transfer';
		} else {
			$payload = [
				'account_no' => $accountNo,
				'payment_type' => 'momo',
				'message' => $message,
				'transactionId' => $transactionId,
				'currency' => $currency,
				'amount' => $amount,
				'country_code' => $country,
				'mno' => $mno,
			];
			$flow = 'debit_only';
		}

		$authValue = $this->authorizationHeader();
		$headers = [
			'Authorization' => $authValue,
			'Content-Type' => 'application/json; charset=UTF-8',
			'Accept' => 'application/json',
			'category' => (string) ($this->config['category'] ?? 'BIZAO'),
		];

		$res = $this->httpRequest('POST', $url, $headers, json_encode($payload), true);
		$body = $res['json'];
		$raw = $res['raw'];

		if (
			$useTransfer
			&& $res['status'] >= 400
			&& is_array($body)
			&& isset($body['message'])
			&& is_string($body['message'])
			&& stripos($body['message'], 'Total Transfer amount not match with paid amount') !== false
		) {
			$this->httpRequest('POST', $base . '/api/v1/user/settings', $headers, json_encode([
				'id' => 'allow_transfer_cap',
				'value' => true,
			]), true);
			$res = $this->httpRequest('POST', $url, $headers, json_encode($payload), true);
			$body = $res['json'];
			$raw = $res['raw'];
		}

		if ($res['status'] >= 400 && $this->isDuplicateTransactionError($body !== null ? $body : $raw)) {
			$transactionId = $this->newTransactionId(preg_replace('/_[0-9].*$/', '', $transactionId) ?: 'XSCH');
			if (isset($payload['transactionId'])) {
				$payload['transactionId'] = $transactionId;
			}
			if (isset($payload['transfers'][0]['transactionId'])) {
				$payload['transfers'][0]['transactionId'] = $transactionId . '_T';
			}
			$res = $this->httpRequest('POST', $url, $headers, json_encode($payload), true);
			$body = $res['json'];
			$raw = $res['raw'];
		}

		$errMsg = is_array($body) && isset($body['message']) && is_string($body['message'])
			? $body['message']
			: (string) $raw;
		if (
			$useTransfer
			&& $res['status'] >= 400
			&& stripos($errMsg, 'TARGET_AUTHORIZATION') !== false
		) {
			$envReceiver = trim((string) ($this->config['receiver_account_no'] ?? ''));
			$triedReceiver = $this->normalizeMsisdn($receiver);
			$envMsisdn = $envReceiver !== '' ? $this->normalizeMsisdn($envReceiver) : '';

			if ($envMsisdn !== '' && $envMsisdn !== $triedReceiver) {
				$retryPayload = $payload;
				$retryPayload['transfers'][0]['account_no'] = $envMsisdn;
				$res = $this->httpRequest('POST', $url, $headers, json_encode($retryPayload), true);
				$body = $res['json'];
				$raw = $res['raw'];
				$payload = $retryPayload;
				$errMsg = is_array($body) && isset($body['message']) && is_string($body['message'])
					? $body['message']
					: (string) $raw;
			}

			if ($res['status'] >= 400 && stripos($errMsg, 'TARGET_AUTHORIZATION') !== false) {
				$debitUrl = $base . '/api/v2/momo/debit';
				$debitPayload = [
					'account_no' => $accountNo,
					'payment_type' => 'momo',
					'message' => $message,
					'transactionId' => $transactionId,
					'currency' => $currency,
					'amount' => $amount,
					'country_code' => $country,
					'mno' => $mno,
				];
				$res = $this->httpRequest('POST', $debitUrl, $headers, json_encode($debitPayload), true);
				$body = $res['json'];
				$raw = $res['raw'];
				$payload = $debitPayload;
				$url = $debitUrl;
				$flow = 'debit_only_after_target_auth_error';
			}
		}

		$ok = $res['status'] >= 200 && $res['status'] < 300;

		return [
			'ok' => $ok,
			'http_status' => $res['status'],
			'flow' => $flow,
			'url' => $url,
			'request' => $payload,
			'response' => $body,
			'raw' => $raw,
			'auth_mode' => $this->describeAuthMode($authValue),
			'msisdn' => $accountNo,
			'transaction_id' => (string) ($payload['transactionId'] ?? $transactionId),
			'error_message' => $ok ? null : $this->humanizeError(is_array($body) ? $body : $raw),
		];
	}

	/**
	 * @param mixed $body
	 */
	public function isSettledSuccess($body, bool $allowNumericHttpSuccess = false): bool
	{
		if (!is_array($body) || $body === []) {
			return false;
		}

		$desc = strtolower(trim((string) ($body['statusDesc'] ?? $body['status_desc'] ?? '')));
		if ($desc !== '') {
			if ($this->isPendingStatus($desc) || $this->isFailedStatus($desc)) {
				return false;
			}
			if (in_array($desc, ['success', 'successful', 'succeeded', 'completed', 'paid', 'approved'], true)) {
				return true;
			}

			return false;
		}

		$values = $this->extractStatusValues($body);

		foreach ($values as $value) {
			$s = strtolower(trim((string) $value));
			if ($s === '') {
				continue;
			}
			if ($this->isPendingStatus($s) || $this->isFailedStatus($s)) {
				return false;
			}
		}

		foreach ($values as $value) {
			if (is_int($value) || (is_string($value) && ctype_digit(trim($value)))) {
				if ($allowNumericHttpSuccess && (int) $value === 200) {
					return true;
				}
				continue;
			}
			$s = strtolower(trim((string) $value));
			if (in_array($s, ['success', 'successful', 'succeeded', 'completed', 'paid', 'approved'], true)) {
				return true;
			}
		}

		if (array_key_exists('resultCode', $body) && (string) $body['resultCode'] === '0') {
			return !$this->hasPendingOrFailedHint($values);
		}

		return false;
	}

	/**
	 * @param mixed $body
	 */
	public function isSettledFailure($body): bool
	{
		if (!is_array($body) || $body === []) {
			return false;
		}

		$desc = strtolower(trim((string) ($body['statusDesc'] ?? $body['status_desc'] ?? '')));
		if ($desc !== '') {
			if ($this->isPendingStatus($desc)) {
				return false;
			}
			if ($this->isFailedStatus($desc) || strpos($desc, 'target_authorization') !== false || strpos($desc, 'error') !== false) {
				return true;
			}
		}

		foreach ($this->extractStatusValues($body) as $value) {
			$s = strtolower(trim((string) $value));
			if ($s !== '' && $this->isFailedStatus($s)) {
				return true;
			}
			if ((is_int($value) || (is_string($value) && ctype_digit(trim($value)))) && (int) $value >= 400) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param array<string, mixed> $body
	 * @return list<mixed>
	 */
	protected function extractStatusValues(array $body): array
	{
		$keys = ['statusDesc', 'status_desc', 'status', 'transactionStatus', 'state', 'payment_status', 'txnStatus', 'momoStatus'];
		$values = [];
		foreach ($keys as $key) {
			if (array_key_exists($key, $body) && $body[$key] !== null && $body[$key] !== '') {
				$values[] = $body[$key];
			}
		}
		if (isset($body['data']) && is_array($body['data'])) {
			foreach ($keys as $key) {
				if (array_key_exists($key, $body['data']) && $body['data'][$key] !== null && $body['data'][$key] !== '') {
					$values[] = $body['data'][$key];
				}
			}
		}

		return $values;
	}

	/**
	 * @param list<mixed> $values
	 */
	protected function hasPendingOrFailedHint(array $values): bool
	{
		foreach ($values as $value) {
			$s = strtolower(trim((string) $value));
			if ($s !== '' && ($this->isPendingStatus($s) || $this->isFailedStatus($s))) {
				return true;
			}
		}

		return false;
	}

	protected function isPendingStatus(string $s): bool
	{
		foreach (['pending', 'processing', 'initiated', 'queued', 'waiting', 'inprogress', 'in_progress', 'submitted', 'ongoing'] as $hint) {
			if ($s === $hint || strpos($s, $hint) !== false) {
				return true;
			}
		}

		return false;
	}

	protected function isFailedStatus(string $s): bool
	{
		foreach (['fail', 'reject', 'cancel', 'timeout', 'expired', 'declined', 'error'] as $hint) {
			if ($s === $hint || strpos($s, $hint) !== false) {
				if (strpos($s, 'success') !== false) {
					continue;
				}

				return true;
			}
		}

		return false;
	}

	/**
	 * GET /api/v1/momo/transactionstatus/{trxId}
	 *
	 * @return array<string, mixed>
	 */
	public function transactionStatus(string $transactionId): array
	{
		$trxId = preg_replace('/_T$/', '', trim($transactionId));
		if ($trxId === null || $trxId === '') {
			$trxId = trim($transactionId);
		}
		$url = $this->serverBaseUrl() . '/api/v1/momo/transactionstatus/' . rawurlencode($trxId);
		$headers = [
			'Authorization' => $this->authorizationHeader(),
			'Accept' => 'application/json',
		];

		$res = $this->httpRequest('GET', $url, $headers, null, true);
		$body = $res['json'];
		$raw = $res['raw'];
		$okHttp = $res['status'] >= 200 && $res['status'] < 300;

		$success = $okHttp && $this->isSettledSuccess(is_array($body) ? $body : null);
		$failed = $okHttp && !$success && $this->isSettledFailure(is_array($body) ? $body : null);

		if ($okHttp && is_array($body) && !$success && !$failed) {
			log_message('info', 'MoPay transaction still unsettled trx=' . $trxId
				. ' statusDesc=' . ($body['statusDesc'] ?? ''));
		}

		return [
			'ok' => $okHttp,
			'http_status' => $res['status'],
			'response' => $body,
			'raw' => $raw,
			'success' => $success,
			'failed' => $failed,
			'error_message' => ($failed || (!$okHttp && is_array($body)))
				? $this->humanizeError($body)
				: null,
		];
	}

	public function newTransactionId(string $prefix): string
	{
		$clean = strtoupper(preg_replace('/[^A-Z0-9_]/', '', $prefix) ?: 'XSCH');
		$id = $clean . '_' . time() . '_' . substr((string) microtime(true), -6) . '_' . random_int(100000, 999999);
		$cleanId = preg_replace('/[^A-Z0-9_]/', '', $id);

		return $cleanId ?: ('XSCH_' . time() . '_' . random_int(100000, 999999));
	}

	/**
	 * @param mixed $body
	 */
	public function extractErrorText($body): string
	{
		if (is_string($body) && trim($body) !== '') {
			return trim($body);
		}
		if (!is_array($body)) {
			return '';
		}

		$keys = [
			'message', 'error', 'error_message', 'errorMessage', 'reason', 'statusMessage',
			'status_message', 'description', 'detail', 'details', 'failureReason', 'failure_reason',
		];
		foreach ($keys as $key) {
			if (!empty($body[$key]) && (is_string($body[$key]) || is_numeric($body[$key]))) {
				$text = trim((string) $body[$key]);
				if ($text !== '' && !ctype_digit($text)) {
					return $text;
				}
			}
		}
		if (isset($body['data']) && is_array($body['data'])) {
			$nested = $this->extractErrorText($body['data']);
			if ($nested !== '') {
				return $nested;
			}
		}

		return '';
	}

	/**
	 * @param mixed $bodyOrText
	 */
	public function humanizeError($bodyOrText, string $fallback = 'Mobile Money payment failed. Please try again.'): string
	{
		$raw = is_string($bodyOrText) ? trim($bodyOrText) : $this->extractErrorText($bodyOrText);
		$lower = strtolower($raw);

		if ($raw === '') {
			return $fallback;
		}

		$map = [
			['insufficient', 'Insufficient balance on the Mobile Money account.'],
			['not enough', 'Insufficient balance on the Mobile Money account.'],
			['already exists', 'A previous payment request is still open. Wait a moment, then try again.'],
			['trx id', 'A previous payment request is still open. Wait a moment, then try again.'],
			['wrong pin', 'Incorrect Mobile Money PIN. Please try again.'],
			['invalid pin', 'Incorrect Mobile Money PIN. Please try again.'],
			['timeout', 'Payment timed out before approval. Please try again.'],
			['expired', 'Payment request expired. Please try again.'],
			['cancel', 'Payment was cancelled on the phone.'],
			['reject', 'Payment was rejected on the phone.'],
			['declined', 'Payment was declined.'],
			['target_authorization', 'The school receive Mobile Money number is not authorized on MoPay. Update School Settings → MOMO account.'],
			['target authorization', 'The school receive Mobile Money number is not authorized on MoPay. Update School Settings → MOMO account.'],
			['not authorized', 'This Mobile Money number is not authorized for this payment.'],
			['msisdn', 'Invalid Mobile Money number. Check and try again.'],
			['invalid phone', 'Invalid Mobile Money number. Check and try again.'],
			['service unavailable', 'Mobile Money service is temporarily unavailable. Try again shortly.'],
		];

		foreach ($map as $pair) {
			$needle = $pair[0];
			$friendly = $pair[1];
			if ($needle !== '' && strpos($lower, $needle) !== false) {
				return $friendly;
			}
		}

		if (preg_match('/\bpin\b/i', $raw) && preg_match('/\b(wrong|invalid|incorrect|fail|error)\b/i', $raw)) {
			return 'Incorrect Mobile Money PIN. Please try again.';
		}

		if (strlen($raw) <= 180 && strpos($lower, '{') === false && strpos($lower, 'curl ') !== 0) {
			if (preg_match('/^[A-Z0-9_]{6,}$/', $raw)) {
				return $fallback;
			}

			return $raw;
		}

		return $fallback;
	}

	/**
	 * @param mixed $bodyOrText
	 */
	public function isDuplicateTransactionError($bodyOrText): bool
	{
		$raw = strtolower(is_string($bodyOrText) ? $bodyOrText : $this->extractErrorText($bodyOrText));

		return $raw !== '' && (
			strpos($raw, 'already exists') !== false
			|| strpos($raw, 'trx id already') !== false
			|| strpos($raw, 'transaction id already') !== false
			|| strpos($raw, 'duplicate') !== false
		);
	}

	public function describeAuthMode(string $authValue): string
	{
		if (stripos($authValue, 'bearer ') === 0) {
			return 'bearer';
		}
		if (stripos($authValue, 'basic ') === 0) {
			return 'basic_prefix';
		}

		return 'raw_auth_key';
	}

	/**
	 * @param array<string, string> $headers
	 * @return array{status:int,raw:string,json:mixed}
	 */
	protected function httpRequest(string $method, string $url, array $headers, ?string $body, bool $asJson): array
	{
		$curl = \Config\Services::curlrequest([
			'timeout' => 60,
			'http_errors' => false,
			'verify' => false,
		]);

		$options = [
			'headers' => $headers,
			'http_errors' => false,
			'verify' => false,
		];
		if ($body !== null) {
			$options['body'] = $body;
		}

		$response = $curl->request($method, $url, $options);
		$raw = (string) $response->getBody();
		$json = json_decode($raw, true);

		return [
			'status' => $response->getStatusCode(),
			'raw' => $raw,
			'json' => $json,
		];
	}

	protected function tokenCachePath(): string
	{
		$dir = WRITEPATH . 'cache';
		if (!is_dir($dir)) {
			@mkdir($dir, 0755, true);
		}

		return $dir . DIRECTORY_SEPARATOR . 'mopay_token_' . $this->projectSlug() . '.json';
	}

	/**
	 * @return array<string, mixed>|null
	 */
	protected function readTokenCache(): ?array
	{
		$path = $this->tokenCachePath();
		if (!is_file($path)) {
			return null;
		}
		$raw = @file_get_contents($path);
		if ($raw === false || $raw === '') {
			return null;
		}
		$data = json_decode($raw, true);

		return is_array($data) ? $data : null;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	protected function writeTokenCache(array $data, int $ttlSeconds): void
	{
		$path = $this->tokenCachePath();
		@file_put_contents($path, json_encode($data));
		unset($ttlSeconds);
	}
}
