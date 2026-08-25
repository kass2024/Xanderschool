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
	 * Show CLOCK IN/OUT on the live camera (LAN HTTP device/output type 4).
	 */
	public function announceClock(string $name, string $status): array
	{
		$status = strtoupper(trim($status)) === 'OUT' ? 'OUT' : 'IN';
		$label = $status === 'OUT' ? 'CLOCK OUT' : 'CLOCK IN';
		$content = json_encode([
			'displayContent' => $label,
		], JSON_UNESCAPED_UNICODE);
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
}
