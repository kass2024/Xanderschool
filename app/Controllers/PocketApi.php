<?php

namespace App\Controllers;

use App\Services\Pocket\PocketWalletService;
use CodeIgniter\HTTP\Response;

/**
 * Parent pocket-money API for the Xander Pocket Android app,
 * plus the card charge the canteen POS will call later.
 *
 * Parent calls send Authorization: Bearer {token}.
 * POS calls send X-Pocket-Pos-Key from POCKET_POS_KEY in .env.
 */
class PocketApi extends BaseController
{
	/** @var PocketWalletService */
	protected $wallet;

	public function __construct()
	{
		$this->wallet = new PocketWalletService();
	}

	public function health()
	{
		return $this->ok($this->wallet->health());
	}

	public function besoftWebhook()
	{
		try {
			return $this->ok($this->wallet->applyBesoftNotice($this->body()));
		} catch (\Throwable $e) {
			log_message('error', 'BeSoft webhook: ' . $e->getMessage());

			return $this->fail(500, 'Webhook could not be saved.');
		}
	}

	public function register()
	{
		return $this->guard(function () {
			$body = $this->body();

			return $this->wallet->register(
				(string) ($body['name'] ?? ''),
				(string) ($body['phone'] ?? ''),
				(string) ($body['pin'] ?? '')
			);
		});
	}

	public function login()
	{
		return $this->guard(function () {
			$body = $this->body();

			return $this->wallet->login(
				(string) ($body['phone'] ?? ''),
				(string) ($body['pin'] ?? '')
			);
		});
	}

	public function recover()
	{
		return $this->guard(function () {
			$body = $this->body();
			$draft = $this->wallet->preparePinReset((string) ($body['phone'] ?? ''));
			$result = [];
			$sent = $this->sendSMS(
				(string) $draft['phone'],
				(string) $draft['message'],
				$result,
				null,
				20,
				(int) ($draft['schoolId'] ?? 0)
			);
			$detail = trim((string) ($result['content'] ?? ''));
			$accepted = $sent || stripos($detail, 'delivery is not confirmed') !== false;
			if (!$accepted) {
				$this->wallet->clearPinReset((string) $draft['phone']);
				throw new \RuntimeException($detail !== '' ? $detail : 'The SMS could not be sent. Try again.', 502);
			}

			return ['ok' => true, 'message' => 'A code was sent by SMS. Enter it with your new PIN.'];
		});
	}

	public function recoverConfirm()
	{
		return $this->guard(function () {
			$body = $this->body();

			return $this->wallet->confirmPinReset(
				(string) ($body['phone'] ?? ''),
				(string) ($body['code'] ?? ''),
				(string) ($body['pin'] ?? '')
			);
		});
	}

	public function logout()
	{
		$token = $this->bearer();
		if ($token !== '') {
			$this->wallet->logout($token);
		}

		return $this->ok(['ok' => true]);
	}

	public function me()
	{
		$parent = $this->parent();
		if ($parent instanceof Response) {
			return $parent;
		}

		return $this->ok(['parent' => $parent]);
	}

	public function lookup()
	{
		$parent = $this->parent();
		if ($parent instanceof Response) {
			return $parent;
		}

		return $this->guard(function () use ($parent) {
			$body = $this->body();

			return $this->wallet->findAsYouType((string) ($body['regno'] ?? ''), (int) $parent['id']);
		});
	}

	public function link()
	{
		$parent = $this->parent();
		if ($parent instanceof Response) {
			return $parent;
		}

		return $this->guard(function () use ($parent) {
			$body = $this->body();

			return [
				'student' => $this->wallet->link(
					(int) $parent['id'],
					(int) ($body['studentId'] ?? 0),
					(string) ($body['spendPin'] ?? '')
				),
			];
		});
	}

	public function unlink()
	{
		$parent = $this->parent();
		if ($parent instanceof Response) {
			return $parent;
		}

		return $this->guard(function () use ($parent) {
			$body = $this->body();
			$this->wallet->unlink((int) $parent['id'], (int) ($body['studentId'] ?? 0));

			return ['ok' => true];
		});
	}

	public function wallets()
	{
		$parent = $this->parent();
		if ($parent instanceof Response) {
			return $parent;
		}

		return $this->ok(['students' => $this->wallet->wallets((int) $parent['id'])]);
	}

	public function ledger($studentId = 0)
	{
		$parent = $this->parent();
		if ($parent instanceof Response) {
			return $parent;
		}

		return $this->guard(function () use ($parent, $studentId) {
			return $this->wallet->ledger((int) $parent['id'], (int) $studentId);
		});
	}

	public function spendPin()
	{
		$parent = $this->parent();
		if ($parent instanceof Response) {
			return $parent;
		}

		return $this->guard(function () use ($parent) {
			$body = $this->body();
			$this->wallet->setSpendPin(
				(int) $parent['id'],
				(int) ($body['studentId'] ?? 0),
				(string) ($body['pin'] ?? ''),
				(string) ($body['oldPin'] ?? '')
			);

			return ['ok' => true, 'message' => 'Spend PIN saved. The student will use it on the card.'];
		});
	}

	public function topup()
	{
		$parent = $this->parent();
		if ($parent instanceof Response) {
			return $parent;
		}

		return $this->guard(function () use ($parent) {
			$body = $this->body();

			return $this->wallet->startTopup(
				(int) $parent['id'],
				(int) ($body['studentId'] ?? 0),
				$body['amount'] ?? '',
				(string) ($body['phone'] ?? ''),
				(string) ($body['mno'] ?? 'mtn')
			);
		});
	}

	public function topupStatus($paymentId = 0)
	{
		$parent = $this->parent();
		if ($parent instanceof Response) {
			return $parent;
		}

		return $this->guard(function () use ($parent, $paymentId) {
			return $this->wallet->pollTopup((int) $parent['id'], (int) $paymentId);
		});
	}

	public function posSchools()
	{
		if (($denied = $this->posAuth()) instanceof Response) {
			return $denied;
		}

		$acronym = trim((string) $this->request->getGet('acronym'));
		if ($acronym === '') {
			return $this->fail(404, 'Enter the school acronym.');
		}

		return $this->guard(function () use ($acronym) {
			return ['school' => $this->wallet->schoolByAcronym($acronym)];
		});
	}

	public function posCatalog()
	{
		if (($denied = $this->posAuth()) instanceof Response) {
			return $denied;
		}

		return $this->guard(function () {
			$school = $this->wallet->schoolByAcronym((string) $this->request->getGet('acronym'));

			return $this->wallet->canteenCatalog((int) $school['id']);
		});
	}

	public function posPreview()
	{
		if (($denied = $this->posAuth()) instanceof Response) {
			return $denied;
		}

		return $this->guard(function () {
			$body = $this->body();

			$school = $this->wallet->schoolByAcronym((string) ($body['acronym'] ?? ''));

			return [
				'student' => $this->wallet->posPreview(
					(int) $school['id'],
					(string) ($body['card'] ?? '')
				),
			];
		});
	}

	public function posCharge()
	{
		if (($denied = $this->posAuth()) instanceof Response) {
			return $denied;
		}

		return $this->guard(function () {
			$body = $this->body();

			$school = $this->wallet->schoolByAcronym((string) ($body['acronym'] ?? ''));

			return $this->wallet->posCharge(
				(int) $school['id'],
				(string) ($body['card'] ?? ''),
				$body['amount'] ?? '',
				(string) ($body['pin'] ?? ''),
				(string) ($body['merchant'] ?? 'POS'),
				(string) ($body['clientRef'] ?? ''),
				(string) ($body['canteenPhone'] ?? ''),
				(string) ($body['items'] ?? ''),
				isset($body['lines']) && is_array($body['lines']) ? $body['lines'] : [],
				(string) ($body['signature'] ?? '')
			);
		});
	}

	/**
	 * @param callable():array $work
	 * @return Response
	 */
	protected function guard(callable $work)
	{
		try {
			return $this->ok($work());
		} catch (\InvalidArgumentException $e) {
			return $this->fail(422, $e->getMessage());
		} catch (\RuntimeException $e) {
			$code = (int) $e->getCode();
			if ($code < 400 || $code > 599) {
				$code = 400;
			}

			return $this->fail($code, $e->getMessage());
		} catch (\Throwable $e) {
			log_message('error', 'PocketApi: ' . $e->getMessage());

			return $this->fail(500, 'Something went wrong. Please try again.');
		}
	}

	/**
	 * @return array|Response
	 */
	protected function parent()
	{
		$parent = $this->wallet->parentByToken($this->bearer());
		if (!$parent) {
			return $this->fail(401, 'Sign in to continue.');
		}

		return $parent;
	}

	protected function bearer(): string
	{
		$header = $this->request->getHeaderLine('Authorization');
		if (stripos($header, 'Bearer ') !== 0) {
			return '';
		}

		return trim(substr($header, 7));
	}

	/**
	 * @return true|Response
	 */
	protected function posAuth()
	{
		$expected = trim((string) env('POCKET_POS_KEY', ''));
		$given = trim($this->request->getHeaderLine('X-Pocket-Pos-Key'));
		if ($expected === '') {
			return $this->fail(503, 'The card terminal key is not configured.');
		}
		if ($given === '' || !hash_equals($expected, $given)) {
			return $this->fail(401, 'This card terminal is not authorized.');
		}

		return true;
	}

	protected function body(): array
	{
		$json = $this->request->getJSON(true);
		if (is_array($json)) {
			return $json;
		}
		$post = $this->request->getPost();

		return is_array($post) ? $post : [];
	}

	protected function ok(array $data)
	{
		return $this->response->setContentType('application/json')->setJSON($data);
	}

	protected function fail(int $status, string $message)
	{
		return $this->response->setStatusCode($status)->setContentType('application/json')->setJSON([
			'message' => $message,
		]);
	}
}
