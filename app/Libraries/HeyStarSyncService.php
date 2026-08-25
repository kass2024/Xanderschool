<?php

namespace App\Libraries;

/**
 * Push Xander people to a stock HeyStar terminal (LAN :8090).
 * Staff names only — faces are captured on HeyStar and uploaded to the VPS.
 * Students are not sent to the terminal.
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
			'recModeCardEnable' => 0,
			'recModeFaceEnable' => 1,
			'recModeFingerEnable' => 0,
			'recModePalmEnable' => 0,
		]);

		$staff = 0;
		$errors = [];

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
		}

		return [
			'success' => 1,
			'message' => "Synced {$staff} staff names. Capture faces on HeyStar — photos upload to the VPS.",
			'staff' => $staff,
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
}
