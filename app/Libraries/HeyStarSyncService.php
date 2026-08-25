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
		$ip = trim((string) $dev['device_ip']);
		if (HeyStarClient::isPrivateIp($ip) && !self::phpOnSchoolLan()) {
			HeyStarDeviceStore::requestStaffSync($schoolId);
			$count = count(AttendanceScanService::staffList($schoolId));
			return [
				'success' => 1,
				'queued' => 1,
				'staff' => $count,
				'message' => "Sync queued for {$count} staff. The school-LAN helper pushes names to the terminal as soon as it is online. New staff are auto-synced — no extra tap needed.",
				'errors' => [],
			];
		}
		$client = new HeyStarClient($ip, (string) ($dev['password'] ?? '123456'));
		$ping = $client->post('device/getConfig', ['type' => 1], 4);
		if (!$client->ok($ping) && (string) ($ping['code'] ?? '') === 'ERR') {
			HeyStarDeviceStore::requestStaffSync($schoolId);
			return [
				'success' => 1,
				'queued' => 1,
				'staff' => 0,
				'message' => 'Terminal not reachable from this server. Names are queued and will auto-sync on the school LAN.',
				'errors' => [(string) ($ping['msg'] ?? 'unreachable')],
			];
		}
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
			'pciLedAlwaysEnable' => 0,
			'pciLedColorStranger' => 1,
			'pciRelayOut' => 1,
			'pciRelayMode' => 1,
			'pciRelayDelay' => 2000,
		]);
		$client->post('device/setRecModeConfig', [
			'recModeCardEnable' => 0,
			'recModeFaceEnable' => 1,
			'recModeFingerEnable' => 0,
			'recModePalmEnable' => 0,
		]);
		// Keep the live camera always ready. IN/OUT is decided on Xander from the
		// staff shift (same toggle as the web scanner), not Check-In / Check-Out taps.
		$client->post('device/setCstConfig', [
			'attendance_direction_enable' => false,
			'recognize_result_countdown' => 2200,
			'evt_show_image_duration' => 2200,
		]);
		$brand = self::applySchoolBranding($client, $schoolId);

		$staff = 0;
		$errors = [];
		if (!$client->ok($brand['ui'] ?? [])) {
			$errors[] = 'School UI: ' . (string) (($brand['ui']['msg'] ?? 'branding failed'));
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
		}

		HeyStarDeviceStore::markStaffSynced($schoolId);
		return [
			'success' => 1,
			'message' => "Branded HeyStar as {$brand['name']}. Synced {$staff} staff names. Capture faces on the terminal — photos upload to the VPS.",
			'staff' => $staff,
			'school' => $brand['name'],
			'errors' => array_slice($errors, 0, 12),
			'upload_url' => $upload,
			'person_url' => $personUrl,
		];
	}

	/**
	 * Speak and show IN/OUT on the terminal when this PHP host can reach it (school LAN).
	 */
	public static function announceClock(int $schoolId, string $name, string $status): void
	{
		$status = strtoupper(trim($status));
		if ($status !== 'IN' && $status !== 'OUT') {
			return;
		}
		$dev = HeyStarDeviceStore::forSchool($schoolId);
		if (!$dev) {
			return;
		}
		$ip = trim((string) ($dev['device_ip'] ?? ''));
		if ($ip === '') {
			return;
		}
		if (HeyStarClient::isPrivateIp($ip) && !self::phpOnSchoolLan()) {
			return;
		}
		try {
			$client = new HeyStarClient($ip, (string) ($dev['password'] ?? '123456'));
			$client->announceClock($name, $status);
		} catch (\Throwable $e) {
			return;
		}
	}

	/**
	 * School name + logo on the stock HeyStar UI (official setUiConfig, no APK rebuild).
	 *
	 * @return array{name:string,ui:array<string,mixed>,rec:array<string,mixed>}
	 */
	public static function applySchoolBranding(HeyStarClient $client, int $schoolId): array
	{
		$db = \Config\Database::connect();
		$school = $db->table('schools')
			->select('name, acronym, logo')
			->where('id', $schoolId)
			->get()
			->getRowArray() ?: [];
		$name = trim((string) ($school['name'] ?? ''));
		if ($name === '') {
			$name = trim((string) ($school['acronym'] ?? ''));
		}
		if ($name === '') {
			$name = 'School';
		}
		$name = mb_substr($name, 0, 48);

		$ui = [
			'uiCompanyName' => $name,
			'uiShowIp' => 0,
			'uiShowSn' => 0,
			'uiShowPersonCount' => 1,
			'uiScreensaverWait' => 90,
		];
		$logo = self::schoolLogoBase64((string) ($school['logo'] ?? ''));
		if ($logo !== '') {
			$ui['uiCompanyLogo'] = $logo;
		}
		$uiRes = $client->post('device/setUiConfig', $ui, 60);
		$recRes = $client->post('device/setRecConfig', [
			'recRank' => 3,
			'recSucTtsMode' => 2,
			'recSucDisplayMode' => 1,
			'recRecordUploadMode' => 2,
			'recRecordSave' => 1,
			'recStrangerEnable' => 1,
			'recIsStrangerTimes' => 1,
			'recStrangerTtsMode' => 2,
			'recStrangerDisplayMode' => 1,
			'recStrangerOpenDoor' => 0,
			'recNoPerTtsMode' => 2,
			'recNotBioTtsMode' => 2,
			'recNotBioDisplayMode' => 1,
		], 25);
		return ['name' => $name, 'ui' => $uiRes, 'rec' => $recRes];
	}

	public static function phpOnSchoolLan(): bool
	{
		$serverAddr = (string) ($_SERVER['SERVER_ADDR'] ?? '');
		return $serverAddr !== '' && (
			(bool) preg_match('/^10\./', $serverAddr)
			|| (bool) preg_match('/^192\.168\./', $serverAddr)
		);
	}

	/**
	 * @return list<array{id:int,sn:string,name:string}>
	 */
	public static function staffRoster(int $schoolId): array
	{
		$out = [];
		foreach (AttendanceScanService::staffList($schoolId) as $p) {
			$id = (int) ($p['id'] ?? 0);
			if ($id <= 0) {
				continue;
			}
			$out[] = [
				'id' => $id,
				'sn' => 'T' . $id,
				'name' => self::safeName((string) ($p['name'] ?? '')),
			];
		}
		return $out;
	}

	private static function schoolLogoBase64(string $stored): string
	{
		$candidates = [];
		$stored = trim($stored);
		if ($stored !== '') {
			$candidates[] = FCPATH . 'assets/images/logo/' . basename($stored);
		}
		$candidates[] = FCPATH . 'assets/images/fallback-logo.png';
		$candidates[] = FCPATH . 'assets/images/logo.jpeg';
		$candidates[] = FCPATH . 'assets/images/smartsms-logo-web.png';
		foreach ($candidates as $file) {
			if (!is_file($file)) {
				continue;
			}
			$raw = @file_get_contents($file);
			if ($raw === false || strlen($raw) < 80) {
				continue;
			}
			if (strlen($raw) > 900000) {
				continue;
			}
			return base64_encode($raw);
		}
		return '';
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
