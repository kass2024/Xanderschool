<?php

namespace App\Libraries;

/**
 * HeyStar LAN HTTP API (port 8090). Basic auth user is always "admin".
 */
class HeyStarClient
{
	/** @var string */
	private $base;
	/** @var string */
	private $password;

	public function __construct(string $deviceIp, string $password = '123456')
	{
		$ip = trim($deviceIp);
		$this->base = 'http://' . $ip . ':8090/cgi-bin/js';
		$this->password = $password !== '' ? $password : '123456';
	}

	/**
	 * @param array<string,mixed>|list<mixed> $body
	 * @return array<string,mixed>
	 */
	public function post(string $path, $body, int $timeout = 25): array
	{
		$url = $this->base . '/' . ltrim($path, '/');
		$payload = json_encode($body);
		$ch = curl_init($url);
		curl_setopt_array($ch, [
			CURLOPT_POST => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=UTF-8'],
			CURLOPT_USERPWD => 'admin:' . $this->password,
			CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
			CURLOPT_POSTFIELDS => $payload,
			CURLOPT_CONNECTTIMEOUT => 5,
			CURLOPT_TIMEOUT => $timeout,
		]);
		$raw = curl_exec($ch);
		$err = curl_error($ch);
		$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		if ($raw === false) {
			return ['code' => 'ERR', 'msg' => $err !== '' ? $err : 'Device not reachable on :8090. Is HeyStar running?'];
		}
		$json = json_decode((string) $raw, true);
		if (!is_array($json)) {
			return ['code' => 'ERR', 'msg' => 'HTTP ' . $code . ' ' . substr((string) $raw, 0, 180)];
		}
		return $json;
	}

	public function ok(array $res): bool
	{
		return (string) ($res['code'] ?? '') === '000';
	}

	/**
	 * Read registered people from the device without changing anything.
	 *
	 * @return array{ok:bool,people:array<int,array<string,mixed>>,pages:int,error:string}
	 */
	public function listPersons(string $deviceKey, int $pageSize = 100): array
	{
		$deviceKey = trim($deviceKey);
		if ($deviceKey === '') {
			return ['ok' => false, 'people' => [], 'pages' => 0, 'error' => 'Missing device key'];
		}
		$pageSize = max(1, min(200, $pageSize));
		$page = 1;
		$pages = 0;
		$people = [];
		$seen = [];
		while ($page <= 100) {
			$res = $this->post('person/findList', [
				'deviceKey' => $deviceKey,
				'index' => $page,
				'length' => $pageSize,
			], 20);
			if (! $this->ok($res)) {
				return [
					'ok' => false,
					'people' => $people,
					'pages' => $pages,
					'error' => (string) ($res['msg'] ?? 'person list failed'),
				];
			}
			$rows = $this->extractPersonRows($res);
			$pages++;
			$countBefore = count($people);
			foreach ($rows as $row) {
				$sn = trim((string) ($row['sn'] ?? $row['personSn'] ?? $row['workNo'] ?? $row['s'] ?? ''));
				if ($sn !== '' && isset($seen[$sn])) {
					continue;
				}
				if ($sn !== '') {
					$seen[$sn] = true;
				}
				$people[] = $row;
			}
			$reportedTotal = $this->extractPositiveInt($res, ['total', 'count', 'dataCount', 'recordsTotal', 'totalCount']);
			if ($reportedTotal > 0 && count($people) >= $reportedTotal) {
				break;
			}
			if (count($rows) < $pageSize || count($people) === $countBefore) {
				break;
			}
			$page++;
		}
		return ['ok' => true, 'people' => $people, 'pages' => $pages, 'error' => ''];
	}

	/**
	 * Show CLOCK IN/OUT on the live camera (LAN HTTP device/output type 4).
	 */
	public function announceClock(string $name, string $status): array
	{
		$status = strtoupper(trim($status)) === 'OUT' ? 'OUT' : 'IN';
		$label = $status === 'OUT' ? 'CLOCK OUT' : 'CLOCK IN';
		$content = json_encode(['displayContent' => $label], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		$this->post('device/output', ['type' => 1], 2);
		return $this->post('device/output', [
			'type' => 4,
			'content' => is_string($content) ? $content : '{"displayContent":"' . $label . '"}',
		], 2);
	}

	public static function isPrivateIp(string $ip): bool
	{
		$ip = trim($ip);
		if ($ip === '127.0.0.1' || $ip === '::1') {
			return true;
		}
		return (bool) preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[0-1])\.)/', $ip);
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function extractPersonRows(array $res): array
	{
		$candidates = [
			$res['data'] ?? null,
			$res['rows'] ?? null,
			$res['list'] ?? null,
			$res['records'] ?? null,
			isset($res['data']) && is_array($res['data']) ? ($res['data']['rows'] ?? $res['data']['list'] ?? $res['data']['records'] ?? null) : null,
		];
		foreach ($candidates as $candidate) {
			if (! is_array($candidate) || $candidate === []) {
				continue;
			}
			if ($this->isListOfArrays($candidate)) {
				return $candidate;
			}
		}
		return [];
	}

	/**
	 * @param mixed $value
	 */
	private function isListOfArrays($value): bool
	{
		if (! is_array($value)) {
			return false;
		}
		foreach ($value as $row) {
			if (! is_array($row)) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @param list<string> $keys
	 */
	private function extractPositiveInt(array $res, array $keys): int
	{
		foreach ($keys as $key) {
			if (isset($res[$key]) && is_numeric($res[$key]) && (int) $res[$key] > 0) {
				return (int) $res[$key];
			}
			if (isset($res['data']) && is_array($res['data']) && isset($res['data'][$key]) && is_numeric($res['data'][$key]) && (int) $res['data'][$key] > 0) {
				return (int) $res['data'][$key];
			}
		}
		return 0;
	}
}
