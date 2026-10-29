<?php

namespace App\Controllers;

use App\Services\Mopay\MopayGatewayClient;
use CodeIgniter\Controller;

/**
 * Public package checkout. Card payments use the same Stripe account as
 * e-learning.school. Mobile Money uses the shared MoPay account.
 */
class PackageCheckout extends Controller
{
	/** @var array<string, float> */
	private $plans = [
		'essential' => 22.87,
		'professional' => 37.98,
		'complete' => 51.78,
	];

	public function start()
	{
		$payload = $this->request->getJSON(true);
		if (!is_array($payload)) {
			$payload = $this->request->getPost();
		}

		$plan = strtolower(trim((string) ($payload['plan'] ?? '')));
		$students = (int) ($payload['students'] ?? 0);
		$name = trim((string) ($payload['name'] ?? ''));
		$email = trim((string) ($payload['email'] ?? ''));
		$phone = trim((string) ($payload['phone'] ?? ''));
		$method = strtolower(trim((string) ($payload['method'] ?? '')));

		if (!isset($this->plans[$plan])) {
			return $this->fail('Choose a paid package.');
		}
		if ($students < 1 || $students > 20000) {
			return $this->fail('Enter the number of students.');
		}
		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			return $this->fail('Enter the email for the receipt.');
		}
		if ($method !== 'momo' && $method !== 'stripe') {
			return $this->fail('Choose Mobile Money or card.');
		}

		$usd = round($this->plans[$plan] * $students, 2);
		$label = 'SmartSMS ' . ucfirst($plan) . ' · ' . $students . ' students';

		if ($method === 'stripe') {
			return $this->startStripe($label, $email, $name, $plan, $students, $usd);
		}

		return $this->startMomo($label, $email, $name, $phone, $usd);
	}

	public function status()
	{
		$reference = strtoupper(trim((string) $this->request->getGet('reference')));
		if (!preg_match('/^XSPKG_[A-Z0-9_]+$/', $reference)) {
			return $this->fail('Payment reference is missing.');
		}

		try {
			$result = $this->gateway()->transactionStatus($reference);
		} catch (\Throwable $e) {
			log_message('error', 'Package MoMo status failed: ' . $e->getMessage());
			return $this->fail('Could not check the payment yet.');
		}

		$state = 'pending';
		if (!empty($result['success'])) {
			$state = 'paid';
		} elseif (!empty($result['failed'])) {
			$state = 'failed';
		}

		return $this->response->setJSON([
			'ok' => true,
			'state' => $state,
			'message' => $state === 'failed'
				? (string) ($result['error_message'] ?: 'The payment was not approved.')
				: ($state === 'paid' ? 'Payment received.' : 'Waiting for approval on your phone.'),
		]);
	}

	/**
	 * @return \CodeIgniter\HTTP\ResponseInterface
	 */
	private function startStripe(string $label, string $email, string $name, string $plan, int $students, float $usd)
	{
		$secret = trim((string) env('STRIPE_SECRET_KEY', ''));
		if ($secret === '') {
			return $this->fail('Card payment is not configured.');
		}

		$cents = (int) round($usd * 100);
		if ($cents < 50) {
			return $this->fail('The amount is too small to charge.');
		}

		$success = rtrim(base_url(), '/') . '/?package=paid';
		$cancel = rtrim(base_url(), '/') . '/#packages';
		$body = http_build_query([
			'mode' => 'payment',
			'success_url' => $success,
			'cancel_url' => $cancel,
			'customer_email' => $email,
			'client_reference_id' => $plan . '-' . $students,
			'metadata' => [
				'plan' => $plan,
				'students' => (string) $students,
				'payer_name' => $name,
			],
			'line_items' => [[
				'quantity' => 1,
				'price_data' => [
					'currency' => 'usd',
					'unit_amount' => $cents,
					'product_data' => [
						'name' => $label,
					],
				],
			]],
		]);

		$ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
		curl_setopt_array($ch, [
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $body,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_USERPWD => $secret . ':',
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
		]);
		$raw = curl_exec($ch);
		$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$err = curl_error($ch);
		curl_close($ch);

		$data = is_string($raw) ? json_decode($raw, true) : null;
		$url = is_array($data) ? (string) ($data['url'] ?? '') : '';
		if ($url === '' || $code < 200 || $code >= 300) {
			$msg = is_array($data) ? (string) ($data['error']['message'] ?? '') : '';
			log_message('error', 'Package Stripe checkout failed http=' . $code . ' curl=' . $err);
			return $this->fail($msg !== '' ? $msg : 'Card checkout could not start.');
		}

		return $this->response->setJSON([
			'ok' => true,
			'method' => 'stripe',
			'checkout_url' => $url,
			'amount_usd' => $usd,
		]);
	}

	/**
	 * @return \CodeIgniter\HTTP\ResponseInterface
	 */
	private function startMomo(string $label, string $email, string $name, string $phone, float $usd)
	{
		$rate = (int) env('PACKAGE_USD_TO_RWF', 1450);
		if ($rate < 100) {
			return $this->fail('Mobile Money pricing is not configured.');
		}
		$rwf = (int) round($usd * $rate);
		if ($rwf < 100) {
			return $this->fail('The amount is too small to charge.');
		}

		try {
			$gateway = $this->gateway();
			if (!$gateway->isConfigured()) {
				return $this->fail('Mobile Money is not configured.');
			}
			$msisdn = $gateway->normalizeMsisdn($phone);
			if (strlen($msisdn) !== 12) {
				return $this->fail('Enter the Mobile Money number that should approve this payment.');
			}
			$tx = $gateway->newTransactionId('XSPKG');
			$result = $gateway->initiateCollection([
				'account_no' => $msisdn,
				'amount' => $rwf,
				'currency' => 'RWF',
				'transaction_id' => $tx,
				'title' => 'SmartSMS package',
				'details' => $label,
				'message' => 'SMARTSMS',
				'use_transfer' => false,
				'mno' => 'mtn',
			]);
		} catch (\Throwable $e) {
			log_message('error', 'Package MoMo start failed: ' . $e->getMessage());
			return $this->fail('Mobile Money could not start. Try again.');
		}

		if (empty($result['ok'])) {
			$message = trim((string) ($result['error_message'] ?? ''));
			if ($message === '' && isset($result['response'])) {
				$message = $gateway->humanizeError($result['response'], 'Mobile Money could not start.');
			}
			if ($message === '') {
				$message = 'Mobile Money could not start.';
			}
			return $this->fail($message);
		}

		return $this->response->setJSON([
			'ok' => true,
			'method' => 'momo',
			'reference' => (string) ($result['transaction_id'] ?? $tx),
			'amount_rwf' => $rwf,
			'message' => 'Approve the Mobile Money prompt on your phone.',
		]);
	}

	private function gateway(): MopayGatewayClient
	{
		$base = (new \Config\Mopay())->toArray();
		foreach ([
			'project_slug' => 'HUB_MOPAY_PROJECT_SLUG',
			'message_prefix' => 'HUB_MOPAY_MESSAGE_PREFIX',
			'payment_title' => 'HUB_MOPAY_PAYMENT_TITLE',
			'payment_details' => 'HUB_MOPAY_PAYMENT_DETAILS',
		] as $field => $envKey) {
			$value = trim((string) env($envKey, ''));
			if ($value !== '') {
				$base[$field] = $value;
			}
		}

		return MopayGatewayClient::fromConfig($base);
	}

	/**
	 * @return \CodeIgniter\HTTP\ResponseInterface
	 */
	private function fail(string $message)
	{
		return $this->response->setStatusCode(422)->setJSON([
			'ok' => false,
			'message' => $message,
		]);
	}
}
