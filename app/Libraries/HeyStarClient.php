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

	public function __construct(string $deviceIp, string $password = 'HFSecurity')
	{
		$ip = trim($deviceIp);
		$this->base = 'http://' . $ip . ':8090/cgi-bin/js';
		$this->password = $password !== '' ? $password : 'HFSecurity';
	}

	/**
	 * @param array<string,mixed>|list<mixed> $body
	 * @return array<string,mixed>
	 */
	public function post(string $path, $body): array
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
			CURLOPT_TIMEOUT => 25,
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
}
