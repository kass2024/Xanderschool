<?php

namespace App\Libraries;

/**
 * Push only new people, edited names, and new cards to a HeyStar terminal.
 * Never deletes people and never rewrites match score, faces, or branding.
 */
class HeyStarSyncService
{
	/**
	 * @return array<string,mixed>
	 */
	public static function syncSchool(int $schoolId): array
	{
		// Full sync used to rewrite recognition settings and delete people who were
		// on the terminal but missing from one roster pull. That path is closed.
		return self::syncChangesOnly($schoolId);
	}

	/**
	 * Fill the terminal address when this computer is on the school network.
	 * Away from that network, keep the address the terminal last reported.
	 * Reads the device only. Does not change people, faces, or match score.
	 *
	 * @return array<string,mixed>
	 */
	public static function discoverDeviceIp(int $schoolId, string $deviceKey = '', string $password = '', string $browserIp = ''): array
	{
		$dev = HeyStarDeviceStore::forSchool($schoolId) ?: [];
		$wantKey = trim($deviceKey) !== '' ? trim($deviceKey) : trim((string) ($dev['device_key'] ?? ''));
		$savedIp = trim((string) ($dev['device_ip'] ?? ''));
		$passwords = [];
		foreach ([$password, (string) ($dev['password'] ?? ''), '123456'] as $candidate) {
			$candidate = trim($candidate);
			if ($candidate !== '') {
				$passwords[$candidate] = true;
			}
		}
		$passwords = array_keys($passwords);
		if ($passwords === []) {
			$passwords = ['123456'];
		}
		$local = self::localSchoolIpv4();
		if ($local === []) {
			$browserIp = trim($browserIp);
			if (
				HeyStarClient::isPrivateIp($browserIp)
				&& $browserIp !== '127.0.0.1'
				&& $browserIp !== $savedIp
			) {
				$save = ['device_ip' => $browserIp, 'last_seen' => time()];
				if ($wantKey !== '') {
					$save['device_key'] = $wantKey;
				}
				HeyStarDeviceStore::save($schoolId, $save);
				return self::discoverResult($browserIp, $wantKey, 'lan', time());
			}
			return self::discoverResult($savedIp, $wantKey, $savedIp !== '' ? 'reported' : 'none', (int) ($dev['last_seen'] ?? 0));
		}
		if ($savedIp !== '' && self::probeHeyStar($savedIp, $passwords, $wantKey) !== null) {
			return self::discoverResult($savedIp, $wantKey, 'lan', time());
		}
		$open = self::hostsOnPort8090($local);
		$matches = [];
		foreach (array_slice($open, 0, 8) as $ip) {
			$hit = self::probeHeyStar($ip, $passwords, $wantKey);
			if ($hit !== null) {
				$matches[] = $hit;
			}
		}
		if ($wantKey !== '') {
			foreach ($matches as $hit) {
				if (strcasecmp((string) $hit['device_key'], $wantKey) === 0 || (string) $hit['device_key'] === '') {
					$save = ['device_ip' => (string) $hit['ip'], 'last_seen' => time()];
					if ($wantKey !== '') {
						$save['device_key'] = $wantKey;
					}
					HeyStarDeviceStore::save($schoolId, $save);
					return self::discoverResult((string) $hit['ip'], $wantKey, 'lan', time());
				}
			}
			return self::discoverResult($savedIp, $wantKey, $savedIp !== '' ? 'reported' : 'none', (int) ($dev['last_seen'] ?? 0));
		}
		if (count($matches) === 1) {
			$hit = $matches[0];
			$save = ['device_ip' => (string) $hit['ip'], 'last_seen' => time()];
			if ((string) $hit['device_key'] !== '') {
				$save['device_key'] = (string) $hit['device_key'];
			}
			HeyStarDeviceStore::save($schoolId, $save);
			return self::discoverResult((string) $hit['ip'], (string) $hit['device_key'], 'lan', time());
		}
		return self::discoverResult($savedIp, $wantKey, $savedIp !== '' ? 'reported' : 'none', (int) ($dev['last_seen'] ?? 0));
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function discoverResult(string $ip, string $deviceKey, string $source, int $lastSeen): array
	{
		if ($source === 'lan' && $ip !== '') {
			$message = 'Found the terminal at ' . $ip . ' on this network.';
		} elseif ($ip !== '') {
			$message = 'Terminal address ' . $ip . ', reported by the device on the school Wi-Fi.';
		} else {
			$message = 'No terminal address yet. It fills in when the terminal is on the school Wi-Fi.';
		}
		return [
			'success' => $ip !== '' ? 1 : 0,
			'device_ip' => $ip,
			'device_key' => $deviceKey,
			'source' => $source,
			'last_seen' => $lastSeen,
			'message' => $message,
		];
	}

	/**
	 * @param list<string> $passwords
	 * @return array{ip:string,device_key:string}|null
	 */
	private static function probeHeyStar(string $ip, array $passwords, string $wantKey): ?array
	{
		foreach ($passwords as $password) {
			$client = new HeyStarClient($ip, $password);
			$ping = $client->post('device/getConfig', ['type' => 1], 2);
			if (!$client->ok($ping)) {
				$msg = (string) ($ping['msg'] ?? '');
				$unreachable = (string) ($ping['code'] ?? '') === 'ERR' && (
					stripos($msg, 'timed out') !== false
					|| stripos($msg, 'Could not connect') !== false
					|| stripos($msg, 'Connection refused') !== false
					|| stripos($msg, 'Failed to connect') !== false
					|| stripos($msg, 'not reachable') !== false
				);
				if ($unreachable) {
					return null;
				}
				continue;
			}
			$key = self::searchDeviceKey($ping);
			if ($wantKey !== '' && $key !== '' && strcasecmp($key, $wantKey) !== 0) {
				return null;
			}
			return ['ip' => $ip, 'device_key' => $key !== '' ? $key : $wantKey];
		}
		return null;
	}

	/**
	 * @return list<string>
	 */
	private static function localSchoolIpv4(): array
	{
		$found = [];
		$push = static function (string $ip) use (&$found): void {
			$ip = trim($ip);
			if ($ip === '' || $ip === '127.0.0.1') {
				return;
			}
			if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
				return;
			}
			if (!HeyStarClient::isPrivateIp($ip)) {
				return;
			}
			if (preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $ip) || strpos($ip, '169.254.') === 0) {
				return;
			}
			$found[$ip] = true;
		};
		$push((string) ($_SERVER['SERVER_ADDR'] ?? ''));
		$name = gethostname();
		if (is_string($name) && $name !== '') {
			$list = @gethostbynamel($name);
			if (is_array($list)) {
				foreach ($list as $ip) {
					$push((string) $ip);
				}
			}
		}
		return array_keys($found);
	}

	/**
	 * @param list<string> $localIps
	 * @return list<string>
	 */
	private static function hostsOnPort8090(array $localIps): array
	{
		$hosts = [];
		foreach ($localIps as $local) {
			$parts = explode('.', $local);
			if (count($parts) !== 4) {
				continue;
			}
			$prefix = $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.';
			for ($i = 1; $i <= 254; $i++) {
				$ip = $prefix . $i;
				if ($ip === $local) {
					continue;
				}
				$hosts[$ip] = true;
			}
		}
		$hosts = array_keys($hosts);
		if ($hosts === [] || !function_exists('curl_multi_init')) {
			return [];
		}
		$open = [];
		foreach (array_chunk($hosts, 64) as $chunk) {
			$multi = curl_multi_init();
			$handles = [];
			foreach ($chunk as $ip) {
				$ch = curl_init('http://' . $ip . ':8090/');
				curl_setopt_array($ch, [
					CURLOPT_NOBODY => true,
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_CONNECTTIMEOUT_MS => 250,
					CURLOPT_TIMEOUT_MS => 400,
					CURLOPT_NOSIGNAL => true,
				]);
				curl_multi_add_handle($multi, $ch);
				$handles[$ip] = $ch;
			}
			$running = null;
			do {
				$stat = curl_multi_exec($multi, $running);
			} while ($stat === CURLM_CALL_MULTI_PERFORM);
			while ($running && $stat === CURLM_OK) {
				if (curl_multi_select($multi, 0.4) === -1) {
					usleep(10000);
				}
				do {
					$stat = curl_multi_exec($multi, $running);
				} while ($stat === CURLM_CALL_MULTI_PERFORM);
			}
			foreach ($handles as $ip => $ch) {
				if ((int) curl_getinfo($ch, CURLINFO_HTTP_CODE) > 0) {
					$open[] = $ip;
				}
				curl_multi_remove_handle($multi, $ch);
				curl_close($ch);
			}
			curl_multi_close($multi);
			if (count($open) >= 8) {
				break;
			}
		}
		return $open;
	}

	/**
	 * Send only new staff and students, edited names, and new or changed cards.
	 * Does not change match score, branding, faces, or anyone already in sync.
	 * Does not delete anyone and does not call the display/branding APIs.
	 *
	 * When this PHP host cannot reach a school-Wi-Fi address, the result asks
	 * the browser on that Wi-Fi to send the same update through the local helper.
	 *
	 * @param callable(array<string,mixed>):void|null $onProgress
	 * @return array<string,mixed>
	 */
	public static function syncChangesOnly(int $schoolId, ?callable $onProgress = null): array
	{
		$emit = static function (int $sent, int $total, string $label, string $line = '') use ($onProgress): void {
			if ($onProgress === null) {
				return;
			}
			$event = ['type' => 'progress', 'sent' => $sent, 'total' => $total, 'label' => $label];
			if ($line !== '') {
				$event['line'] = $line;
			}
			$onProgress($event);
		};
		$dev = HeyStarDeviceStore::forSchool($schoolId);
		if (!$dev || trim((string) ($dev['device_ip'] ?? '')) === '') {
			return ['success' => 0, 'message' => 'The terminal address fills in when it is on the school Wi-Fi.'];
		}
		$ip = trim((string) $dev['device_ip']);
		$emit(0, 0, 'Checking the terminal…');
		$password = (string) ($dev['password'] ?? '123456');
		if ($password === '') {
			$password = '123456';
		}
		$client = new HeyStarClient($ip, $password);
		$ping = $client->post('device/getConfig', ['type' => 1], 3, 2);
		if (!$client->ok($ping) && self::pingAuthFailed($ping) && $password !== '123456') {
			$client = new HeyStarClient($ip, '123456');
			$ping = $client->post('device/getConfig', ['type' => 1], 3, 2);
		}
		if (!$client->ok($ping)) {
			if (self::pingUnreachable($ping) && HeyStarClient::isPrivateIp($ip)) {
				HeyStarDeviceStore::requestStaffSync($schoolId);
				return [
					'success' => 1,
					'lan_client' => 1,
					'queued' => 1,
					'device_ip' => $ip,
					'message' => 'This server cannot reach the terminal on the school Wi-Fi. This computer will send staff and students.',
					'errors' => [],
				];
			}
			if (self::pingUnreachable($ping)) {
				return [
					'success' => 0,
					'message' => 'Terminal not reachable at ' . $ip . '.',
					'errors' => [(string) ($ping['msg'] ?? 'unreachable')],
				];
			}
			return [
				'success' => 0,
				'message' => 'The terminal refused the update. Check the communication password.',
				'errors' => [(string) ($ping['msg'] ?? 'device error')],
			];
		}
		$deviceKey = self::resolveDeviceKey($dev, $ping);
		if ($deviceKey === '') {
			return ['success' => 0, 'message' => 'Enter the device key so people already on the terminal are kept.'];
		}
		$emit(0, 0, 'Reading people already on the terminal. Branding and faces stay.');
		$list = $client->listPersons($deviceKey, 40);
		if (!$list['ok']) {
			return ['success' => 0, 'message' => 'Could not read the terminal list. Nothing was changed.', 'errors' => [(string) ($list['error'] ?? '')]];
		}
		$deviceName = [];
		$deviceCard = [];
		foreach ($list['people'] as $person) {
			$sn = self::devicePersonSn($person);
			if ($sn === '') {
				continue;
			}
			$deviceName[$sn] = self::safeName((string) ($person['name'] ?? ''));
			$raw = trim((string) ($person['cardNo'] ?? $person['card'] ?? ''));
			$deviceCard[$sn] = preg_match('/^\d+$/', $raw)
				? $raw
				: strtoupper(preg_replace('/[^A-F0-9]/', '', $raw));
		}
		$emit(0, 0, 'Loading staff and students from the server…');
		$jobs = [];
		$skipped = 0;
		foreach (self::changeRoster($schoolId) as $p) {
			$sn = (string) $p['sn'];
			$wantName = self::safeName((string) $p['name']);
			$wantCard = trim((string) ($p['cardNo'] ?? ''));
			$cardLabel = trim((string) ($p['card'] ?? ''));
			if ($cardLabel === '') {
				$cardLabel = $wantCard;
			}
			$role = strncmp($sn, 'S', 1) === 0 ? 'Student' : 'Staff';
			if (isset($deviceName[$sn])) {
				$nameSame = strcasecmp($deviceName[$sn], $wantName) === 0;
				$cardSame = ($wantCard === '' || strcasecmp($deviceCard[$sn] ?? '', $wantCard) === 0);
				if ($nameSame && $cardSame) {
					$skipped++;
					continue;
				}
				$parts = [];
				if (!$nameSame) {
					$parts[] = 'name';
				}
				if ($wantCard !== '' && !$cardSame) {
					$parts[] = 'card ' . $cardLabel;
				}
				$jobs[] = [
					'sn' => $sn,
					'name' => $wantName,
					'cardNo' => $wantCard,
					'role' => $role,
					'is_new' => false,
					'rename' => !$nameSame,
					'card' => ($wantCard !== '' && !$cardSame),
					'line' => $role . ' · ' . $wantName . ' — ' . ($parts !== [] ? implode(', ', $parts) : 'updated'),
				];
				continue;
			}
			$jobs[] = [
				'sn' => $sn,
				'name' => $wantName,
				'cardNo' => $wantCard,
				'role' => $role,
				'is_new' => true,
				'rename' => false,
				'card' => $wantCard !== '',
				'line' => $role . ' · ' . $wantName . ' — added' . ($cardLabel !== '' ? ' card ' . $cardLabel : ''),
			];
		}
		$total = count($jobs);
		$emit(
			0,
			$total,
			$total === 0
				? 'Nothing new. Existing staff, students, faces, and branding stay on the terminal.'
				: 'Sending ' . $total . ' update' . ($total === 1 ? '' : 's') . '. Faces and branding stay.'
		);
		$added = 0;
		$renamed = 0;
		$cards = 0;
		$staffSent = 0;
		$studentsSent = 0;
		$errors = [];
		$changes = [];
		foreach ($jobs as $index => $job) {
			$payload = [
				'type' => 1,
				'sn' => $job['sn'],
				'name' => $job['name'],
				'verifyStyle' => 0,
			];
			if ($job['cardNo'] !== '') {
				$payload['cardNo'] = $job['cardNo'];
			}
			$res = $client->post('person/merge', $payload);
			$sent = $index + 1;
			if (!$client->ok($res)) {
				$errors[] = $job['sn'] . ': ' . (string) ($res['msg'] ?? 'person merge failed');
				$emit($sent, $total, 'Could not send ' . $job['line'], 'Not sent: ' . $job['line']);
				continue;
			}
			if ($job['is_new']) {
				$added++;
			}
			if ($job['rename']) {
				$renamed++;
			}
			if ($job['card']) {
				$cards++;
			}
			if ($job['role'] === 'Student') {
				$studentsSent++;
			} else {
				$staffSent++;
			}
			$changes[] = $job['line'];
			$emit($sent, $total, $job['line'], $job['line']);
		}
		if ($errors === []) {
			HeyStarDeviceStore::markStaffSynced($schoolId);
		}
		$shown = array_slice($changes, 0, 80);
		$more = count($changes) - count($shown);
		$message = "Sent {$added} new, {$renamed} names, {$cards} cards ({$staffSent} staff, {$studentsSent} students). Left {$skipped} unchanged.";
		if ($more > 0) {
			$message .= " {$more} more were sent and are not listed below.";
		}
		if ($errors !== []) {
			$message .= ' ' . count($errors) . ' could not be sent.';
		}
		$message .= ' Faces, matching cards, and branding were not changed.';
		return [
			'success' => $errors === [] ? 1 : 0,
			'message' => $message,
			'added' => $added,
			'renamed' => $renamed,
			'cards_synced' => $cards,
			'staff_sent' => $staffSent,
			'students_sent' => $studentsSent,
			'skipped_existing' => $skipped,
			'changes' => $shown,
			'errors' => array_slice($errors, 0, 12),
		];
	}

	/**
	 * @param array<string,mixed> $ping
	 */
	private static function pingAuthFailed(array $ping): bool
	{
		$msg = strtolower((string) ($ping['msg'] ?? ''));
		$code = (string) ($ping['code'] ?? '');
		return $code === '401' || $code === '403' || strpos($msg, 'auth') !== false;
	}

	/**
	 * @param array<string,mixed> $ping
	 */
	private static function pingUnreachable(array $ping): bool
	{
		if ((string) ($ping['code'] ?? '') !== 'ERR') {
			return false;
		}
		$msg = strtolower((string) ($ping['msg'] ?? ''));
		if ($msg === '') {
			return true;
		}
		foreach ([
			'timed out',
			'could not connect',
			'couldn\'t connect',
			'connection refused',
			'failed to connect',
			'not reachable',
			'network is unreachable',
			'no route to host',
		] as $needle) {
			if (strpos($msg, $needle) !== false) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Staff plus boarding students. Terminal card numbers use the same reader form as working staff cards.
	 * The stored server card text is not rewritten.
	 *
	 * @return list<array{sn:string,name:string,cardNo:string}>
	 */
	private static function changeRoster(int $schoolId): array
	{
		$out = [];
		foreach (self::staffRoster($schoolId) as $p) {
			$out[] = [
				'sn' => 'T' . (int) $p['id'],
				'name' => (string) $p['name'],
				'card' => trim((string) ($p['card'] ?? '')),
				'cardNo' => trim((string) ($p['cardNo'] ?? '')),
			];
		}
		foreach (AttendanceScanService::heystarBoardingRoster($schoolId) as $p) {
			$out[] = [
				'sn' => (string) $p['sn'],
				'name' => (string) $p['name'],
				'card' => trim((string) ($p['card'] ?? '')),
				'cardNo' => trim((string) ($p['cardNo'] ?? '')),
			];
		}
		return $out;
	}

	/**
	 * Speak and show IN/OUT on the terminal when this PHP host can reach it (school LAN).
	 */
	public static function announceClock(int $schoolId, string $name, string $status, int $staffId = 0): void
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
			'uiScreensaverWait' => 86400,
			'uiScreenSaverEnable' => 0,
		];
		$logo = self::schoolLogoBase64((string) ($school['logo'] ?? ''));
		if ($logo !== '') {
			$ui['uiCompanyLogo'] = $logo;
		}
		$uiRes = $client->post('device/setUiConfig', $ui, 60);
		$recRes = $client->post('device/setRecConfig', [
			'recRank' => 2,
			'recThreshold1vN' => 68,
			'recThreshold1v1' => 60,
			'recInterval' => 2,
			'recDistance' => 0,
			'recSucTtsMode' => 2,
			'recSucDisplayMode' => 1,
			'recRecordUploadMode' => 2,
			'recRecordSave' => 1,
			'recStrangerEnable' => 1,
			'recIsStrangerTimes' => 1,
			'recStrangerTtsMode' => 2,
			'recStrangerDisplayMode' => 1,
			'recStrangerOpenDoor' => 0,
			'recMultiplayer' => 0,
			'recNoPerTtsMode' => 2,
			'recNotBioTtsMode' => 1,
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
	 * HeyStar staff for one school only (never expands master→child campuses).
	 *
	 * @return list<array{id:int,sn:string,name:string,has_photo:int}>
	 */
	public static function staffRoster(int $schoolId): array
	{
		$schoolId = (int) $schoolId;
		if ($schoolId <= 0) {
			return [];
		}
		helper('qonics');
		$db = \Config\Database::connect();
		$rows = $db->table('staffs s')
			->select('s.id, s.fname, s.lname, s.photo, s.card')
			->where('s.school_id', $schoolId)
			->where('s.status !=', 0)
			->orderBy('s.fname', 'ASC')
			->orderBy('s.lname', 'ASC')
			->get()
			->getResultArray();
		$out = [];
		helper('card_uid');
		foreach ($rows as $p) {
			$id = (int) ($p['id'] ?? 0);
			if ($id <= 0) {
				continue;
			}
			$name = trim((string) ($p['fname'] ?? '') . ' ' . (string) ($p['lname'] ?? ''));
			$cardPhoto = (string) AttendanceScanService::staffUploadedPhotoUrl($p['photo'] ?? null);
			$storedCard = strtoupper(preg_replace('/[^A-F0-9]/', '', (string) ($p['card'] ?? '')));
			// Built-in NFC fails on hex cardNo; device matches decimal of reader-order UID.
			// DB 074AF8DA → reader DAF84A07 → cardNo "3673704967" (failed taps logged this).
			$deviceCard = $storedCard !== '' ? heystar_card_no_from_stored($storedCard) : '';
			$out[] = [
				'id' => $id,
				'sn' => 'T' . $id,
				'name' => self::safeName($name),
				'has_photo' => $cardPhoto !== '' ? 1 : 0,
				// Staff View photo for card-swipe display only (not a face template).
				'photo_url' => $cardPhoto !== ''
					? (rtrim(base_url(), '/') . '/api/heystar_staff_photo?school_id=' . $schoolId . '&sn=T' . $id)
					: '',
				'card' => $storedCard,
				'cardNo' => $deviceCard,
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

	private static function resolveDeviceKey(array $deviceRow, array $ping): string
	{
		$stored = trim((string) ($deviceRow['device_key'] ?? ''));
		if ($stored !== '') {
			return $stored;
		}
		return trim(self::searchDeviceKey($ping));
	}

	/**
	 * @param mixed $value
	 */
	private static function searchDeviceKey($value): string
	{
		if (!is_array($value)) {
			return '';
		}
		foreach (['deviceKey', 'sn', 'devSn', 'serialNo', 'serial', 'device_sn'] as $key) {
			$hit = trim((string) ($value[$key] ?? ''));
			if ($hit !== '') {
				return $hit;
			}
		}
		foreach ($value as $item) {
			$hit = self::searchDeviceKey($item);
			if ($hit !== '') {
				return $hit;
			}
		}
		return '';
	}

	/**
	 * @param array<string,mixed> $person
	 */
	private static function devicePersonSn(array $person): string
	{
		foreach (['sn', 'personSn', 'workNo', 's'] as $key) {
			$value = trim((string) ($person[$key] ?? ''));
			if ($value !== '') {
				return $value;
			}
		}
		return '';
	}

	private static function buildSyncMessage(string $schoolName, int $added, int $skipped, int $renamed, int $deviceCount, int $deviceOnly, bool $compared): string
	{
		if (!$compared) {
			return "Branded HeyStar as {$schoolName}. Synced {$added} staff names. Existing enrolled faces on the terminal were not removed. Capture faces on the terminal. Staff card photos are uploaded on Xander, not from the camera.";
		}
		$message = "Branded HeyStar as {$schoolName}. Compared {$deviceCount} existing people on the terminal with the online staff roster, added {$added} missing staff, updated {$renamed} edited names, and left {$skipped} matching entries untouched so their faces stay as they are.";
		if ($deviceOnly > 0) {
			$message .= " Removed {$deviceOnly} staff deleted from Xander.";
		}
		$message .= ' Capture faces on the terminal. Staff card photos are uploaded on Xander and shown on card swipe; enrolled faces stay for face recognition.';
		return $message;
	}
}
