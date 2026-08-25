<?php

namespace App\Libraries;

/**
 * Push Xander people to a stock HeyStar terminal (LAN :8090).
 * Staff names go first; VPS-enrolled faces are pushed when present.
 * Manual faces assigned on the terminal POST back to the VPS.
 */
class HeyStarSyncService
{
	/**
	 * @return array<string,mixed>
	 */
	public static function syncSchool(int $schoolId): array
	{
		$dev = HeyStarDeviceStore::forSchool($schoolId);
		if (!$dev || trim((string) ($dev['device_ip'] ?? '')) === '') {
			return ['success' => 0, 'message' => 'Save the HeyStar device IP in School Settings first.'];
		}
		$client = new HeyStarClient((string) $dev['device_ip'], (string) ($dev['password'] ?? 'HFSecurity'));
		$base = rtrim(base_url(), '/');
		$upload = $base . '/api/heystar_record?school_id=' . $schoolId;
		$heartbeat = $base . '/api/heystar_heartbeat?school_id=' . $schoolId;
		$personUrl = $base . '/api/heystar_person?school_id=' . $schoolId;

		$client->post('device/setUploadUrl', [
			['type' => 1, 'url' => $heartbeat],
			['type' => 2, 'url' => $upload],
			['type' => 3, 'url' => $personUrl],
		]);
		$client->post('device/setSevConfig', [
			'sevUploadDevHeartbeatUrl' => $heartbeat,
			'sevUploadRecRecordUrl' => $upload,
			'sevUploadRegPersonUrl' => $personUrl,
			'sevUploadRecSnapshotEnable' => 1,
			'sevUploadRecStrangerDataEnable' => 0,
		]);
		$client->post('device/setPciConfig', [
			'pciLedAlwaysEnable' => 1,
			'pciLedColorStranger' => 1,
			'pciRelayOut' => 0,
		]);
		$client->post('device/setRecModeConfig', [
			'recModeCardEnable' => 1,
			'recModeFaceEnable' => 1,
			'recModeFingerEnable' => 0,
			'recModePalmEnable' => 0,
		]);

		$students = 0;
		$staff = 0;
		$faces = 0;
		$errors = [];

		foreach (AttendanceScanService::studentList($schoolId) as $p) {
			$card = trim((string) ($p['card'] ?? ''));
			if ($card === '') {
				continue;
			}
			$sn = 'S' . (int) $p['id'];
			$res = $client->post('person/merge', [
				'type' => 1,
				'sn' => $sn,
				'name' => self::safeName((string) $p['name']),
				'cardNo' => $card,
				'verifyStyle' => 2,
			]);
			if ($client->ok($res)) {
				$students++;
			} else {
				$errors[] = $sn . ': ' . (string) ($res['msg'] ?? 'person merge failed');
			}
		}

		helper('qonics');
		foreach (AttendanceScanService::staffList($schoolId) as $p) {
			$sn = 'T' . (int) $p['id'];
			$res = $client->post('person/merge', [
				'type' => 1,
				'sn' => $sn,
				'name' => self::safeName((string) $p['name']),
				'verifyStyle' => 1,
			]);
			if (!$client->ok($res)) {
				$errors[] = $sn . ': ' . (string) ($res['msg'] ?? 'person merge failed');
				continue;
			}
			$staff++;

			$verifyUrl = (string) ($p['verify_photo'] ?? '');
			if ($verifyUrl === '' || (int) ($p['face_enrolled'] ?? 0) !== 1) {
				continue;
			}
			$b64 = self::photoBase64($verifyUrl);
			if ($b64 === '') {
				continue;
			}
			$face = $client->post('face/merge', [
				'personSn' => $sn,
				'imgUrl' => strtok($verifyUrl, '?') ?: $verifyUrl,
				'imgBase64' => $b64,
			]);
			if ($client->ok($face)) {
				$faces++;
			} else {
				$errors[] = $sn . ' face: ' . (string) ($face['msg'] ?? 'face merge failed');
			}
		}

		return [
			'success' => 1,
			'message' => "Synced {$students} student cards and {$staff} staff ({$faces} VPS faces) to HeyStar. Assign remaining faces on the terminal — they save back to the school server.",
			'students' => $students,
			'staff' => $staff,
			'faces' => $faces,
			'errors' => array_slice($errors, 0, 12),
			'upload_url' => $upload,
			'person_url' => $personUrl,
		];
	}

	private static function safeName(string $name): string
	{
		$name = trim($name);
		if ($name === '') {
			return 'Person';
		}
		return mb_substr($name, 0, 60);
	}

	private static function photoBase64(string $url): string
	{
		$path = parse_url($url, PHP_URL_PATH);
		$file = '';
		if (is_string($path) && $path !== '') {
			$base = basename($path);
			$resolved = function_exists('resolve_profile_photo') ? resolve_profile_photo($base) : $base;
			if ($resolved) {
				$file = FCPATH . 'assets/images/profile/' . $resolved;
			}
		}
		if ($file === '' || !is_file($file)) {
			return '';
		}
		$raw = @file_get_contents($file);
		if ($raw === false || strlen($raw) < 80) {
			return '';
		}
		return base64_encode($raw);
	}
}
