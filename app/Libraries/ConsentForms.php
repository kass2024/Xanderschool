<?php

namespace App\Libraries;

/**
 * Parent consent envelopes. One link shows every form for that parent.
 * Signing stamps the signature line and keeps the rest of the PDF in place.
 */
class ConsentForms
{
	public const LINK_DAYS = 30;

	public function ensureSchema(): void
	{
		static $done = false;
		if ($done) {
			return;
		}
		$db = \Config\Database::connect();
		if (!$db->tableExists('consent_sign_envelopes')) {
			$db->query("CREATE TABLE `consent_sign_envelopes` (
				`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
				`school_id` INT NOT NULL,
				`token` CHAR(32) NOT NULL,
				`phone_key` VARCHAR(24) NOT NULL,
				`expires_at` DATETIME NOT NULL,
				`created_by` INT NULL,
				`created_at` DATETIME NULL,
				PRIMARY KEY (`id`),
				UNIQUE KEY `uniq_consent_token` (`token`),
				KEY `idx_consent_phone` (`school_id`, `phone_key`, `expires_at`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
		}
		if (!$db->tableExists('consent_sign_envelope_docs')) {
			$db->query("CREATE TABLE `consent_sign_envelope_docs` (
				`envelope_id` INT UNSIGNED NOT NULL,
				`student_id` INT NOT NULL,
				PRIMARY KEY (`envelope_id`, `student_id`),
				KEY `idx_consent_doc_student` (`student_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
		}
		if (!$db->tableExists('consent_sign_status')) {
			$db->query("CREATE TABLE `consent_sign_status` (
				`student_id` INT NOT NULL,
				`school_id` INT NOT NULL,
				`regno` VARCHAR(32) NOT NULL,
				`signed_at` DATETIME NULL,
				PRIMARY KEY (`student_id`),
				KEY `idx_consent_status_school` (`school_id`, `signed_at`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
		}
		$done = true;
	}

	public function sourceDir(): string
	{
		return rtrim(WRITEPATH, '/\\') . DIRECTORY_SEPARATOR . 'consent' . DIRECTORY_SEPARATOR . 'source';
	}

	/**
	 * @return array<int, string>
	 */
	public function sourceRegnos(): array
	{
		$dir = $this->sourceDir();
		if (!is_dir($dir)) {
			return [];
		}
		$regnos = [];
		foreach (scandir($dir) ?: [] as $file) {
			if (preg_match('/^(\d+)\.pdf$/', $file, $match)) {
				$regnos[] = $match[1];
			}
		}
		sort($regnos);
		return $regnos;
	}

	public function sourcePath(string $regno): string
	{
		$regno = preg_replace('/\D/', '', $regno) ?? '';
		if ($regno === '') {
			return '';
		}
		return $this->sourceDir() . DIRECTORY_SEPARATOR . $regno . '.pdf';
	}

	public function signedPath(int $schoolId, string $regno): string
	{
		$regno = preg_replace('/\D/', '', $regno) ?? '';
		return rtrim(WRITEPATH, '/\\') . DIRECTORY_SEPARATOR . 'consent' . DIRECTORY_SEPARATOR . 'signed'
			. DIRECTORY_SEPARATOR . $schoolId . DIRECTORY_SEPARATOR . $regno . '.pdf';
	}

	public function currentPath(int $schoolId, string $regno): string
	{
		$signed = $this->signedPath($schoolId, $regno);
		if ($signed !== '' && is_file($signed)) {
			return $signed;
		}
		$source = $this->sourcePath($regno);
		return is_file($source) ? $source : '';
	}

	/**
	 * @return array{ready: bool, total: int, signed: int, groups: array<int, array<string, mixed>>}
	 */
	public function catalog(int $schoolId): array
	{
		$this->ensureSchema();
		$indexed = $this->indexedStudents($schoolId);
		$groups = $this->groupRows($indexed);
		$tokens = $this->liveTokens($schoolId);
		$signed = 0;
		$out = [];
		foreach ($groups as $key => $rows) {
			$docs = [];
			foreach ($rows as $row) {
				if (!empty($row['signed'])) {
					$signed++;
				}
				$docs[] = $this->publicDoc($row);
			}
			$out[] = [
				'key' => $key,
				'parent' => (string) ($rows[0]['parent'] ?? ''),
				'phones' => $this->uniquePhones($rows),
				'token' => (string) ($tokens[$key] ?? ''),
				'docs' => $docs,
			];
		}
		usort($out, static function ($a, $b) {
			return strcasecmp((string) $a['parent'], (string) $b['parent']);
		});
		return [
			'ready' => $this->sourceRegnos() !== [],
			'total' => count($indexed),
			'signed' => $signed,
			'groups' => $out,
		];
	}

	/**
	 * Create or reuse a 30-day link for each parent in the selection.
	 * A parent with several children receives one link for all of their forms.
	 *
	 * @param array<int, int> $studentIds empty means every student who has a form
	 * @return array{groups: array<int, array<string, mixed>>, missing: array<int, string>}
	 */
	public function prepare(int $schoolId, array $studentIds, int $userId): array
	{
		$this->ensureSchema();
		$indexed = $this->indexedStudents($schoolId);
		$missing = [];
		if ($studentIds !== []) {
			$wanted = [];
			foreach ($studentIds as $id) {
				$id = (int) $id;
				if ($id < 1) {
					continue;
				}
				if (!isset($indexed[$id])) {
					$missing[] = $id;
					continue;
				}
				$wanted[$indexed[$id]['key']] = true;
			}
			$picked = [];
			foreach ($indexed as $id => $row) {
				if (isset($wanted[$row['key']])) {
					$picked[$id] = $row;
				}
			}
		} else {
			$picked = $indexed;
		}
		$names = $this->namesForIds($schoolId, $missing);
		$missingLabels = [];
		foreach ($missing as $id) {
			$missingLabels[] = $names[$id] ?? ('Student #' . $id);
		}
		$groups = [];
		foreach ($this->groupRows($picked) as $key => $rows) {
			$groups[] = $this->issueGroup($schoolId, $key, $rows, $userId);
		}
		return ['groups' => $groups, 'missing' => $missingLabels];
	}

	/**
	 * One link that lists every consent form for the school.
	 *
	 * @return array{token: string, total: int, signed: int, expires_at: string}
	 */
	public function schoolBoard(int $schoolId, int $userId): array
	{
		$this->ensureSchema();
		$indexed = $this->indexedStudents($schoolId);
		if ($indexed === []) {
			return ['token' => '', 'total' => 0, 'signed' => 0, 'expires_at' => ''];
		}
		$rows = array_values($indexed);
		usort($rows, static function ($a, $b) {
			return strcasecmp((string) $a['student'], (string) $b['student']);
		});
		$group = $this->issueGroup($schoolId, 'all', $rows, $userId);
		$signed = 0;
		foreach ($group['docs'] as $doc) {
			if (!empty($doc['signed'])) {
				$signed++;
			}
		}
		return [
			'token' => (string) $group['token'],
			'total' => count($group['docs']),
			'signed' => $signed,
			'expires_at' => (string) $group['expires_at'],
		];
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function envelope(string $token): ?array
	{
		$token = strtolower(trim($token));
		if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
			return null;
		}
		$this->ensureSchema();
		$db = \Config\Database::connect();
		$row = $db->table('consent_sign_envelopes')->where('token', $token)->get(1)->getRowArray();
		if (!$row) {
			return null;
		}
		$schoolId = (int) $row['school_id'];
		$ids = $db->table('consent_sign_envelope_docs')
			->select('student_id')
			->where('envelope_id', (int) $row['id'])
			->get()->getResultArray();
		$idList = [];
		foreach ($ids as $item) {
			$idList[] = (int) $item['student_id'];
		}
		$indexed = $this->indexedStudents($schoolId);
		$docs = [];
		foreach ($idList as $id) {
			if (!isset($indexed[$id])) {
				continue;
			}
			$docs[] = $this->publicDoc($indexed[$id]);
		}
		usort($docs, static function ($a, $b) {
			return strcasecmp((string) $a['student'], (string) $b['student']);
		});
		$school = $db->table('schools')->select('name')->where('id', $schoolId)->get(1)->getRowArray();
		return [
			'school_id' => $schoolId,
			'school' => (string) ($school['name'] ?? ''),
			'token' => $token,
			'expires_at' => (string) $row['expires_at'],
			'expired' => strtotime((string) $row['expires_at']) < time(),
			'docs' => $docs,
		];
	}

	public function docInEnvelope(array $envelope, int $studentId): ?array
	{
		foreach ($envelope['docs'] as $doc) {
			if ((int) $doc['student_id'] === $studentId) {
				return $doc;
			}
		}
		return null;
	}

	/**
	 * @return array{ok: bool, error?: string}
	 */
	public function applySignature(int $schoolId, int $studentId, string $regno, string $pngBytes): array
	{
		$source = $this->sourcePath($regno);
		if ($source === '' || !is_file($source)) {
			return ['ok' => false, 'error' => 'The consent form is not available.'];
		}
		if (strlen($pngBytes) < 80 || strlen($pngBytes) > 1500000 || substr($pngBytes, 0, 8) !== "\x89PNG\r\n\x1a\n") {
			return ['ok' => false, 'error' => 'Draw a signature first.'];
		}
		if (!$this->signatureHasInk($pngBytes)) {
			return ['ok' => false, 'error' => 'Draw a signature first.'];
		}
		$tmp = rtrim(WRITEPATH, '/\\') . DIRECTORY_SEPARATOR . 'consent' . DIRECTORY_SEPARATOR . 'tmp';
		if (!is_dir($tmp) && !mkdir($tmp, 0755, true) && !is_dir($tmp)) {
			return ['ok' => false, 'error' => 'The signature could not be saved.'];
		}
		$png = $tmp . DIRECTORY_SEPARATOR . bin2hex(random_bytes(8)) . '.png';
		$dest = $this->signedPath($schoolId, $regno);
		$destDir = dirname($dest);
		if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) {
			return ['ok' => false, 'error' => 'The signature could not be saved.'];
		}
		file_put_contents($png, $pngBytes);
		try {
			$this->stamp($source, $png, $dest);
		} catch (\Throwable $e) {
			@unlink($png);
			log_message('error', 'Consent stamp failed: {msg}', ['msg' => $e->getMessage()]);
			return ['ok' => false, 'error' => 'The signature could not be saved on the form.'];
		}
		@unlink($png);
		if (!is_file($dest)) {
			return ['ok' => false, 'error' => 'The signature could not be saved on the form.'];
		}
		$now = date('Y-m-d H:i:s');
		$db = \Config\Database::connect();
		$existing = $db->table('consent_sign_status')->where('student_id', $studentId)->get(1)->getRowArray();
		if ($existing) {
			$db->table('consent_sign_status')->where('student_id', $studentId)->update([
				'school_id' => $schoolId,
				'regno' => $regno,
				'signed_at' => $now,
			]);
		} else {
			$db->table('consent_sign_status')->insert([
				'student_id' => $studentId,
				'school_id' => $schoolId,
				'regno' => $regno,
				'signed_at' => $now,
			]);
		}
		return ['ok' => true];
	}

	/**
	 * @param array<int, array{name: string, path: string}> $files
	 */
	public function zip(array $files): ?string
	{
		if (!class_exists(\ZipArchive::class) || $files === []) {
			return null;
		}
		$tmp = tempnam(sys_get_temp_dir(), 'consent');
		if ($tmp === false) {
			return null;
		}
		$zip = new \ZipArchive();
		if ($zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
			@unlink($tmp);
			return null;
		}
		$used = [];
		$added = 0;
		foreach ($files as $file) {
			$path = (string) ($file['path'] ?? '');
			if ($path === '' || !is_file($path)) {
				continue;
			}
			$name = $this->downloadName((string) ($file['name'] ?? 'consent'), (string) ($file['regno'] ?? ''));
			if (isset($used[$name])) {
				$name = str_replace('.pdf', '-' . (++$used[$name]) . '.pdf', $name);
			} else {
				$used[$name] = 1;
			}
			$zip->addFile($path, $name);
			$added++;
		}
		$zip->close();
		if ($added < 1) {
			@unlink($tmp);
			return null;
		}
		return $tmp;
	}

	public function downloadName(string $student, string $regno): string
	{
		$student = trim(preg_replace('/[^A-Za-z0-9]+/', '_', $student) ?? '');
		$regno = preg_replace('/\D/', '', $regno) ?? '';
		$name = trim($student . '_' . $regno, '_');
		if ($name === '') {
			$name = 'consent';
		}
		return $name . '.pdf';
	}

	/**
	 * @param array<int, array<string, mixed>> $rows
	 * @return array<string, mixed>
	 */
	private function issueGroup(int $schoolId, string $key, array $rows, int $userId): array
	{
		$db = \Config\Database::connect();
		$now = date('Y-m-d H:i:s');
		$expires = date('Y-m-d H:i:s', time() + (self::LINK_DAYS * 86400));
		$existing = $db->table('consent_sign_envelopes')
			->where('school_id', $schoolId)
			->where('phone_key', $key)
			->where('expires_at >', $now)
			->orderBy('id', 'DESC')
			->get(1)->getRowArray();
		if ($existing) {
			$envelopeId = (int) $existing['id'];
			$token = (string) $existing['token'];
			$db->table('consent_sign_envelopes')->where('id', $envelopeId)->update(['expires_at' => $expires]);
		} else {
			$token = bin2hex(random_bytes(16));
			$db->table('consent_sign_envelopes')->insert([
				'school_id' => $schoolId,
				'token' => $token,
				'phone_key' => substr($key, 0, 24),
				'expires_at' => $expires,
				'created_by' => $userId > 0 ? $userId : null,
				'created_at' => $now,
			]);
			$envelopeId = (int) $db->insertID();
		}
		$have = [];
		$linked = $db->table('consent_sign_envelope_docs')->select('student_id')->where('envelope_id', $envelopeId)->get()->getResultArray();
		foreach ($linked as $item) {
			$have[(int) $item['student_id']] = true;
		}
		foreach ($rows as $row) {
			$studentId = (int) $row['student_id'];
			if (!isset($have[$studentId])) {
				$db->table('consent_sign_envelope_docs')->insert([
					'envelope_id' => $envelopeId,
					'student_id' => $studentId,
				]);
			}
		}
		$docs = [];
		foreach ($rows as $row) {
			$docs[] = $this->publicDoc($row);
		}
		return [
			'key' => $key,
			'parent' => (string) ($rows[0]['parent'] ?? ''),
			'phones' => $this->uniquePhones($rows),
			'token' => $token,
			'expires_at' => $expires,
			'docs' => $docs,
		];
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function indexedStudents(int $schoolId): array
	{
		$regnos = $this->sourceRegnos();
		if ($regnos === [] || $schoolId < 1) {
			return [];
		}
		$db = \Config\Database::connect();
		$rows = $db->table('students')
			->select('id, fname, lname, regno, father, ft_phone, mother, mt_phone, guardian, gd_phone')
			->where('school_id', $schoolId)
			->whereIn('regno', $regnos)
			->get()->getResultArray();
		$signed = [];
		$status = $db->table('consent_sign_status')->where('school_id', $schoolId)->get()->getResultArray();
		foreach ($status as $item) {
			$signed[(int) $item['student_id']] = (string) ($item['signed_at'] ?? '');
		}
		$indexed = [];
		foreach ($rows as $row) {
			$regno = preg_replace('/\D/', '', (string) ($row['regno'] ?? '')) ?? '';
			if ($regno === '' || !is_file($this->sourcePath($regno))) {
				continue;
			}
			$parent = $this->activeParent($row);
			$studentId = (int) $row['id'];
			$signedAt = $signed[$studentId] ?? '';
			$signedFile = $this->signedPath($schoolId, $regno);
			$isSigned = $signedAt !== '' && is_file($signedFile);
			$indexed[$studentId] = [
				'student_id' => $studentId,
				'regno' => $regno,
				'student' => $this->personName((string) ($row['fname'] ?? ''), (string) ($row['lname'] ?? '')),
				'parent' => $parent['name'],
				'key' => $this->groupKey($row, $parent['phone'], $studentId),
				'phones' => [
					(string) ($row['ft_phone'] ?? ''),
					(string) ($row['mt_phone'] ?? ''),
					(string) ($row['gd_phone'] ?? ''),
				],
				'signed' => $isSigned,
				'signed_at' => $isSigned ? $signedAt : '',
			];
		}
		return $indexed;
	}

	/**
	 * @param array<int, array<string, mixed>> $indexed
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	private function groupRows(array $indexed): array
	{
		$groups = [];
		foreach ($indexed as $row) {
			$groups[(string) $row['key']][] = $row;
		}
		foreach ($groups as &$rows) {
			usort($rows, static function ($a, $b) {
				return strcasecmp((string) $a['student'], (string) $b['student']);
			});
		}
		unset($rows);
		return $groups;
	}

	/**
	 * @return array<string, string>
	 */
	private function liveTokens(int $schoolId): array
	{
		$now = date('Y-m-d H:i:s');
		$rows = \Config\Database::connect()->table('consent_sign_envelopes')
			->select('phone_key, token, id')
			->where('school_id', $schoolId)
			->where('expires_at >', $now)
			->orderBy('id', 'DESC')
			->get()->getResultArray();
		$map = [];
		foreach ($rows as $row) {
			$key = (string) $row['phone_key'];
			if (!isset($map[$key])) {
				$map[$key] = (string) $row['token'];
			}
		}
		return $map;
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array{name: string, phone: string}
	 */
	private function activeParent(array $row): array
	{
		$father = $this->personName((string) ($row['father'] ?? ''));
		$mother = $this->personName((string) ($row['mother'] ?? ''));
		$guardian = $this->personName((string) ($row['guardian'] ?? ''));
		if (strlen($father) > 3) {
			return ['name' => $father, 'phone' => (string) ($row['ft_phone'] ?? '')];
		}
		if (strlen($mother) > 3) {
			return ['name' => $mother, 'phone' => (string) ($row['mt_phone'] ?? '')];
		}
		if (strlen($guardian) > 3) {
			return ['name' => $guardian, 'phone' => (string) ($row['gd_phone'] ?? '')];
		}
		return ['name' => '', 'phone' => ''];
	}

	private function groupKey(array $row, string $activePhone, int $studentId): string
	{
		$key = $this->phoneDigits($activePhone);
		if ($key === '') {
			foreach (['ft_phone', 'mt_phone', 'gd_phone'] as $field) {
				$key = $this->phoneDigits((string) ($row[$field] ?? ''));
				if ($key !== '') {
					break;
				}
			}
		}
		return $key !== '' ? $key : ('student-' . $studentId);
	}

	private function phoneDigits(string $phone): string
	{
		$digits = preg_replace('/\D/', '', $phone) ?? '';
		if (strlen($digits) < 9) {
			return '';
		}
		if (strpos($digits, '250') === 0 && strlen($digits) >= 12) {
			$digits = substr($digits, 3);
		}
		if (isset($digits[0]) && $digits[0] === '0') {
			$digits = substr($digits, 1);
		}
		return strlen($digits) >= 9 ? $digits : '';
	}

	/**
	 * @param array<int, array<string, mixed>> $rows
	 * @return array<int, string>
	 */
	private function uniquePhones(array $rows): array
	{
		$phones = [];
		foreach ($rows as $row) {
			foreach ($row['phones'] as $phone) {
				$phone = trim((string) $phone);
				$key = $this->phoneDigits($phone);
				if ($key !== '' && !isset($phones[$key])) {
					$phones[$key] = $phone;
				}
			}
		}
		return array_values($phones);
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	private function publicDoc(array $row): array
	{
		return [
			'student_id' => (int) $row['student_id'],
			'regno' => (string) $row['regno'],
			'student' => (string) $row['student'],
			'parent' => (string) $row['parent'],
			'signed' => !empty($row['signed']),
			'signed_at' => (string) ($row['signed_at'] ?? ''),
		];
	}

	private function personName(string $first, string $last = ''): string
	{
		$text = trim($first . ' ' . $last);
		$text = preg_replace('/\s+/', ' ', $text) ?? '';
		return trim($text);
	}

	/**
	 * @param array<int, int> $ids
	 * @return array<int, string>
	 */
	private function namesForIds(int $schoolId, array $ids): array
	{
		$ids = array_values(array_filter(array_map('intval', $ids)));
		if ($ids === []) {
			return [];
		}
		$rows = \Config\Database::connect()->table('students')
			->select('id, fname, lname')
			->where('school_id', $schoolId)
			->whereIn('id', $ids)
			->get()->getResultArray();
		$names = [];
		foreach ($rows as $row) {
			$names[(int) $row['id']] = $this->personName((string) ($row['fname'] ?? ''), (string) ($row['lname'] ?? ''));
		}
		return $names;
	}

	private function signatureHasInk(string $pngBytes): bool
	{
		if (!function_exists('imagecreatefromstring')) {
			return true;
		}
		$image = @imagecreatefromstring($pngBytes);
		if (!$image) {
			return false;
		}
		$width = imagesx($image);
		$height = imagesy($image);
		if ($width < 20 || $height < 20 || $width > 2000 || $height > 1200) {
			imagedestroy($image);
			return false;
		}
		$ink = 0;
		for ($y = 0; $y < $height; $y += 2) {
			for ($x = 0; $x < $width; $x += 2) {
				$color = imagecolorat($image, $x, $y);
				$alpha = ($color >> 24) & 0x7F;
				$red = ($color >> 16) & 0xFF;
				$green = ($color >> 8) & 0xFF;
				$blue = $color & 0xFF;
				if ($alpha < 20 && ($red < 245 || $green < 245 || $blue < 245)) {
					$ink++;
					if ($ink > 12) {
						imagedestroy($image);
						return true;
					}
				}
			}
		}
		imagedestroy($image);
		return false;
	}

	private function stamp(string $source, string $png, string $dest): void
	{
		require_once APPPATH . 'ThirdParty/fpdf.php';
		$pdf = new \setasign\Fpdi\Fpdi('P', 'pt', [612, 792]);
		$count = $pdf->setSourceFile($source);
		$info = @getimagesize($png);
		$ratio = ($info && $info[1] > 0) ? ($info[0] / $info[1]) : 4;
		$boxW = 200.0;
		$boxH = 46.0;
		$width = $boxW;
		$height = $width / $ratio;
		if ($height > $boxH) {
			$height = $boxH;
			$width = $height * $ratio;
		}
		$x = 78.0;
		$y = 444.0 - $height;
		for ($pageNo = 1; $pageNo <= $count; $pageNo++) {
			$template = $pdf->importPage($pageNo);
			$size = $pdf->getTemplateSize($template);
			$pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
			$pdf->useTemplate($template);
			if ($pageNo === 2) {
				$pdf->Image($png, $x, $y, $width, $height);
			}
		}
		$pdf->Output('F', $dest);
	}
}
