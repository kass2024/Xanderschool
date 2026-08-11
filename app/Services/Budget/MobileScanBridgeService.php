<?php

namespace App\Services\Budget;

/**
 * Bridge web "Scan from phone" with SmartSMS AmScan capture.
 * Sessions stored as JSON files under writable/mobile_scan/.
 */
class MobileScanBridgeService
{
	private function dir(): string
	{
		$dir = WRITEPATH . 'mobile_scan';
		if (!is_dir($dir)) {
			mkdir($dir, 0755, true);
		}
		return $dir;
	}

	private function path(string $token): string
	{
		return $this->dir() . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $token) . '.json';
	}

	public function createSession(int $staffId): array
	{
		$this->purgeExpired();
		$this->cancelPendingForStaff($staffId);

		$token = bin2hex(random_bytes(16));
		$data = [
			'token' => $token,
			'staff_id' => $staffId,
			'status' => 'pending',
			'created_at' => time(),
			'expires_at' => time() + 1800, // 30 minutes — phone capture can take a while
		];
		file_put_contents($this->path($token), json_encode($data));
		return [
			'token' => $token,
			'expires_in' => 1800,
			'deep_link' => 'smartsms://amscan?token=' . $token,
			'intent_link' => 'intent://amscan?token=' . rawurlencode($token)
				. '#Intent;scheme=smartsms;package=com.xandertech.smartsms;end',
		];
	}

	public function cancelPendingForStaff(int $staffId): void
	{
		foreach (glob($this->dir() . '/*.json') ?: [] as $file) {
			$data = json_decode(file_get_contents($file) ?: '', true);
			if (!is_array($data)) {
				continue;
			}
			if ((int) ($data['staff_id'] ?? 0) === $staffId && ($data['status'] ?? '') === 'pending') {
				@unlink($file);
			}
		}
	}

	public function getPendingForStaff(int $staffId): ?array
	{
		$this->purgeExpired();
		$latest = null;
		foreach (glob($this->dir() . '/*.json') ?: [] as $file) {
			$data = json_decode(file_get_contents($file) ?: '', true);
			if (!is_array($data)) {
				continue;
			}
			if ((int) ($data['staff_id'] ?? 0) === $staffId && ($data['status'] ?? '') === 'pending') {
				if ($latest === null || (int) ($data['created_at'] ?? 0) > (int) ($latest['created_at'] ?? 0)) {
					$latest = $data;
				}
			}
		}
		return $latest;
	}

	public function uploadCapture(string $token, int $staffId, string $imageBase64, string $filename = 'scan.jpg'): array
	{
		$data = $this->read($token);
		if (!$data) {
			return ['success' => false, 'error' => 'Scan session not found or expired.'];
		}
		if ((int) ($data['staff_id'] ?? 0) !== $staffId) {
			return ['success' => false, 'error' => 'This scan session belongs to another user.'];
		}
		if (($data['status'] ?? '') !== 'pending') {
			return ['success' => false, 'error' => 'Scan session already completed.'];
		}
		$raw = base64_decode(preg_replace('#^data:image/\w+;base64,#', '', $imageBase64), true);
		if ($raw === false || strlen($raw) < 100) {
			return ['success' => false, 'error' => 'Invalid image data.'];
		}
		$ext = pathinfo($filename, PATHINFO_EXTENSION) ?: 'jpg';
		$stored = 'mobile_scan/' . $token . '.' . preg_replace('/[^a-z0-9]/i', '', $ext);
		$full = WRITEPATH . $stored;
		file_put_contents($full, $raw);
		$data['status'] = 'ready';
		$data['stored_path'] = $stored;
		$data['original_name'] = $filename ?: 'mobile-scan.jpg';
		$data['mime'] = 'image/jpeg';
		$data['captured_at'] = time();
		file_put_contents($this->path($token), json_encode($data));
		return ['success' => true, 'token' => $token];
	}

	/**
	 * @param bool $consume When true, remove session after returning ready image (one-shot to web form).
	 */
	public function poll(string $token, bool $consume = true): array
	{
		$data = $this->read($token);
		if (!$data) {
			return ['status' => 'expired'];
		}
		if (($data['expires_at'] ?? 0) < time()) {
			$this->deleteSession($token, $data);
			return ['status' => 'expired'];
		}
		if (($data['status'] ?? '') === 'ready' && !empty($data['stored_path'])) {
			$full = WRITEPATH . $data['stored_path'];
			if (is_file($full)) {
				$result = [
					'status' => 'ready',
					'filename' => $data['original_name'] ?? 'scan.jpg',
					'mime' => $data['mime'] ?? 'image/jpeg',
					'image_base64' => base64_encode(file_get_contents($full)),
				];
				if ($consume) {
					$this->deleteSession($token, $data);
				}
				return $result;
			}
		}
		return ['status' => $data['status'] ?? 'pending'];
	}

	private function deleteSession(string $token, ?array $data = null): void
	{
		$data = $data ?: $this->read($token);
		if (!empty($data['stored_path'])) {
			@unlink(WRITEPATH . $data['stored_path']);
		}
		@unlink($this->path($token));
	}

	private function read(string $token): ?array
	{
		$file = $this->path($token);
		if (!is_file($file)) {
			return null;
		}
		$data = json_decode(file_get_contents($file) ?: '', true);
		if (!is_array($data)) {
			return null;
		}
		if (($data['expires_at'] ?? 0) < time()) {
			@unlink($file);
			return null;
		}
		return $data;
	}

	private function purgeExpired(): void
	{
		foreach (glob($this->dir() . '/*.json') ?: [] as $file) {
			$data = json_decode(file_get_contents($file) ?: '', true);
			if (!is_array($data) || ($data['expires_at'] ?? 0) < time()) {
				@unlink($file);
				if (!empty($data['stored_path'])) {
					@unlink(WRITEPATH . $data['stored_path']);
				}
			}
		}
	}
}
