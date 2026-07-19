<?php

namespace App\Libraries;

/**
 * Gemini-powered academic document analysis & generation.
 * TVET/RTB → Scheme of Work + Session Plan
 * REB → Scheme of Work + Lesson Plan
 *
 * Accepts Word/PDF of any layout; always sends raw file + extracted text to Gemini.
 */
class GeminiAcademicDocs
{
	/** Posts allowed to use academic AI plans */
	public const ALLOWED_POSTS = [1, 3, 5, 6, 13, 15, 17, 18];
	// Headmaster, DoS, Patron, Matron, Librarian, Principal, IT Support, Headmistress

	/** @var string */
	private $lastError = '';

	/** @var callable|null fn(int $pct, string $action, array $meta): void */
	private $progressCb = null;

	public function lastError(): string
	{
		return $this->lastError;
	}

	public function onProgress(?callable $cb): self
	{
		$this->progressCb = $cb;
		return $this;
	}

	/** @param array<string,mixed> $meta */
	private function reportProgress(int $pct, string $action, array $meta = []): void
	{
		$pct = max(0, min(100, $pct));
		if ($this->progressCb) {
			try {
				($this->progressCb)($pct, $action, $meta);
			} catch (\Throwable $e) {
				// never break analysis for progress UI
			}
		}
	}

	public function isConfigured(): bool
	{
		return $this->apiKey() !== '';
	}

	public function apiKey(): string
	{
		$key = trim((string) (env('GOOGLE_AI_API_KEY') ?: env('GEMINI_API_KEY') ?: ''));
		return trim($key, " \t\"'");
	}

	/** @return list<string> */
	public function textModels(): array
	{
		$primary = trim((string) (env('GEMINI_MODEL') ?: env('GOOGLE_AI_MODEL') ?: ''));
		$dead = [
			'gemini-1.5-flash', 'gemini-1.5-pro', 'gemini-1.5-flash-latest',
			'gemini-pro', 'gemini-1.0-pro', 'gemini-1.0-pro-latest',
			'gemini-2.0-flash', 'gemini-2.0-flash-001', 'gemini-2.0-flash-lite',
			'gemini-2.5-flash-lite', 'gemini-2.5-flash-lite-preview',
			// Pro/thinking models often return non-JSON or truncate — skip for doc analysis
			'gemini-2.5-pro', 'gemini-2.5-pro-preview', 'gemini-3-pro', 'gemini-3.5-pro',
		];
		$candidates = [
			$primary,
			'gemini-2.5-flash',
			'gemini-flash-latest',
			'gemini-2.5-flash-preview-05-20',
		];
		$out = [];
		foreach ($candidates as $m) {
			$m = trim((string) $m);
			if ($m === '' || in_array(strtolower($m), $dead, true) || in_array($m, $dead, true)) {
				continue;
			}
			if (stripos($m, 'pro') !== false) {
				continue; // keep analysis fast & JSON-stable
			}
			if (!in_array($m, $out, true)) {
				$out[] = $m;
			}
		}
		return $out !== [] ? $out : ['gemini-2.5-flash'];
	}

	/**
	 * Full curriculum analyse (Word/PDF/ZIP package) → all modules + LO/IC saved for SoW.
	 *
	 * @param array{path:string,original?:string} $curriculum primary file (structure PDF or ZIP)
	 * @param array{path:string,original?:string}|null $chronogram
	 * @param array $dbContext
	 * @param list<array{path:string,original?:string}> $extraFiles additional module PDFs
	 * @param list<array{path:string,original?:string}> $extraChronograms additional chronogram files
	 * @return array|null
	 */
	public function analyzeCurriculum(array $curriculum, ?array $chronogram, array $dbContext, array $extraFiles = [], array $extraChronograms = []): ?array
	{
		$this->reportProgress(0, 'Preparing curriculum package…');
		$packFiles = $this->expandCurriculumPackage($curriculum, $extraFiles);
		if ($packFiles === []) {
			$this->lastError = 'Could not read curriculum file(s)';
			return null;
		}

		$this->reportProgress(5, 'Reading ' . count($packFiles) . ' curriculum file(s)…', ['files' => count($packFiles)]);
		$combinedText = '';
		$fileIndex = []; // code hint => text
		$pdfByCode = []; // code => extract pack with bytes
		$pdfFiles = []; // list of PDF extracts for vision
		$filenameSeeds = []; // code => [title, module_type] from RTB filenames
		$primaryPdf = null;
		$totalBytes = 0;

		foreach ($packFiles as $f) {
			$ex = DocumentTextExtractor::extract($f['path']);
			$text = $this->safeUtf8((string) ($ex['text'] ?? ''));
			$orig = $f['original'] ?? basename($f['path']);
			$pathHint = $orig . ' ' . ($f['path'] ?? '');
			$label = "=== FILE: {$orig} (ext={$ex['ext']}, chars=" . mb_strlen($text, 'UTF-8') . ") ===\n";
			$combinedText .= $label . $text . "\n\n";
			$totalBytes += (int) ($ex['chars'] ?? 0);

			$codeFromName = DocumentTextExtractor::extractModuleCodeFromFilename($orig);
			$titleFromName = DocumentTextExtractor::extractModuleTitleFromFilename($orig);
			$typeFromPath = DocumentTextExtractor::guessModuleTypeFromPath($pathHint);
			$codesInFile = [];
			if ($codeFromName !== '' && $typeFromPath !== 'structure') {
				$codesInFile[] = $codeFromName;
				if (!isset($filenameSeeds[$codeFromName])) {
					$filenameSeeds[$codeFromName] = [
						'title' => $titleFromName,
						'module_type' => $typeFromPath !== '' ? $typeFromPath : 'specific',
					];
				}
			}

			// Also map codes found in filename/text header
			if (preg_match_all('/\b([A-Z]{3,8}\d{3})\b/', strtoupper($orig . ' ' . mb_substr($text, 0, 800, 'UTF-8')), $cm)) {
				foreach ($cm[1] as $code) {
					$code = DocumentTextExtractor::cleanModuleCode($code);
					if ($code === '' || preg_match('/^(PAGE|HTTP|HTTPS|RQF|TVET|ICTSWD|LEVEL|DATE)$/', $code)) {
						continue;
					}
					$codesInFile[] = $code;
					if (!isset($fileIndex[$code]) || mb_strlen($text, 'UTF-8') > mb_strlen($fileIndex[$code], 'UTF-8')) {
						$fileIndex[$code] = $text;
					}
				}
			}
			if ($codeFromName !== '') {
				$fileIndex[$codeFromName] = $text !== '' ? $text : ($fileIndex[$codeFromName] ?? '');
			}
			$codesInFile = array_values(array_unique($codesInFile));

			$ext = strtolower((string) ($ex['ext'] ?? ''));
			$byteLen = strlen((string) ($ex['bytes'] ?? ''));
			if ($ext === 'pdf' && !empty($ex['bytes']) && $byteLen > 0 && $byteLen <= 12 * 1024 * 1024) {
				$pdfFiles[] = [
					'original' => $orig,
					'mime' => 'application/pdf',
					'bytes' => $ex['bytes'],
					'chars' => (int) ($ex['chars'] ?? 0),
					'codes' => $codesInFile,
					'module_type' => $typeFromPath,
				];
				foreach ($codesInFile as $code) {
					// Prefer dedicated module PDFs over the big General Information PDF
					$isStructure = ($typeFromPath === 'structure');
					if (!isset($pdfByCode[$code]) || (!$isStructure && ($pdfByCode[$code]['_structure'] ?? false))) {
						$pdfByCode[$code] = [
							'original' => $orig,
							'mime' => 'application/pdf',
							'bytes' => $ex['bytes'],
							'_structure' => $isStructure,
						];
					}
				}
				$n = strtolower($orig);
				if ($primaryPdf === null || $typeFromPath === 'structure'
					|| strpos($n, 'general information') !== false || strpos($n, 'structure') !== false) {
					if ($typeFromPath === 'structure' || strpos($n, 'general information') !== false
						|| strpos($n, 'structure') !== false || $primaryPdf === null) {
						$primaryPdf = $ex;
						$primaryPdf['_original'] = $orig;
					}
				}
			}
		}
		if ($primaryPdf === null && $pdfFiles !== []) {
			$primaryPdf = [
				'bytes' => $pdfFiles[0]['bytes'],
				'mime' => 'application/pdf',
				'ext' => 'pdf',
				'chars' => $pdfFiles[0]['chars'],
				'_original' => $pdfFiles[0]['original'],
			];
		}

		$curriculumChars = mb_strlen($combinedText, 'UTF-8');
		if ($curriculumChars < 400 && $pdfFiles === []) {
			$this->lastError = 'Curriculum files have no readable text and no usable PDF for AI vision. Re-upload the full package (General Information + Specific + General + CCM module PDFs, or one ZIP).';
			$this->reportProgress(0, $this->lastError, ['status' => 'error']);
			return null;
		}

		$this->reportProgress(12, 'Reading chronogram for weekly hours…');
		$chronoPacks = [];
		if ($chronogram && !empty($chronogram['path']) && is_file($chronogram['path'])) {
			$chronoPacks[] = $chronogram;
		}
		foreach ($extraChronograms as $ec) {
			if (!empty($ec['path']) && is_file($ec['path'])) {
				$chronoPacks[] = $ec;
			}
		}
		$chr = ['text' => '', 'mime' => '', 'bytes' => null, 'chars' => 0, 'ext' => ''];
		$chrText = '';
		$bestChrBytes = 0;
		foreach ($chronoPacks as $ci => $cp) {
			$ex = DocumentTextExtractor::extract($cp['path']);
			$t = $this->safeUtf8((string) ($ex['text'] ?? ''));
			$orig = $cp['original'] ?? basename($cp['path']);
			$chrText .= "=== CHRONOGRAM FILE: {$orig} ===\n" . $t . "\n\n";
			$byteLen = strlen((string) ($ex['bytes'] ?? ''));
			if ($ci === 0 || $byteLen > $bestChrBytes) {
				$chr = $ex;
				$bestChrBytes = $byteLen;
			}
		}
		$chrText = $this->safeUtf8($chrText);
		if ($chrText === '' && empty($chr['bytes'])) {
			$this->lastError = 'Chronogram file is empty or unreadable — upload a PDF/Word chronogram for weekly hour distribution';
			$this->reportProgress(0, 'Chronogram unreadable', ['status' => 'error']);
			return null;
		}

		$chronoTitles = DocumentTextExtractor::parseChronogramModuleTitles($chrText);

		// Deterministic module inventory from codes in filenames + text
		$this->reportProgress(18, 'Discovering module codes…');
		$seedCodes = $this->discoverModuleCodes($combinedText, array_merge(array_keys($fileIndex), array_keys($filenameSeeds)));
		$seedList = [];
		$seenSeed = [];
		// Prefer RTB filename seeds (Specific / General / CCM module PDFs)
		foreach ($filenameSeeds as $code => $meta) {
			$code = DocumentTextExtractor::cleanModuleCode((string) $code);
			if ($code === '' || isset($seenSeed[$code])) {
				continue;
			}
			$seenSeed[$code] = true;
			$titleHint = trim((string) ($meta['title'] ?? ''));
			if ($titleHint === '' && isset($chronoTitles[$code])) {
				$titleHint = $chronoTitles[$code];
			}
			$seedList[] = [
				'code' => $code,
				'title' => $titleHint,
				'module_type' => (string) ($meta['module_type'] ?? ''),
			];
		}
		foreach ($seedCodes as $code) {
			$code = DocumentTextExtractor::cleanModuleCode((string) $code);
			if ($code === '' || isset($seenSeed[$code])) {
				continue;
			}
			$seenSeed[$code] = true;
			$titleHint = $chronoTitles[$code] ?? '';
			if ($titleHint === '' && preg_match('/\b' . preg_quote($code, '/') . '\s+([^\n\r]{5,90})/i', $combinedText, $tm)) {
				$titleHint = DocumentTextExtractor::cleanModuleTitle(trim(preg_replace('/\s+/', ' ', $tm[1]) ?? ''));
			}
			$seedList[] = ['code' => $code, 'title' => $titleHint, 'module_type' => ''];
		}

		// Pass 1 — full inventory (must include every seed code) — attach curriculum PDFs for vision
		$this->reportProgress(22, 'AI inventory: listing all courses/modules…', ['seed_codes' => count($seedList)]);
		$invParts = [[
			'text' => "SEED MODULE CODES (MUST ALL appear in modules[] with REAL titles — never invent LO/IC here):\n"
				. json_encode($seedList, JSON_UNESCAPED_UNICODE)
				. "\n\nCHRONOGRAM MODULE TITLES (use these when curriculum title missing):\n"
				. json_encode($chronoTitles, JSON_UNESCAPED_UNICODE)
				. "\n\n=== COMBINED CURRICULUM TEXT (may be truncated) ===\n"
				. mb_substr($combinedText, 0, 140000, 'UTF-8')
				. "\n\n=== CHRONOGRAM ===\n" . mb_substr($chrText, 0, 40000, 'UTF-8'),
		]];
		// Attach up to 3 curriculum PDFs (structure first) for scanned packages
		$attached = 0;
		$budget = 18 * 1024 * 1024;
		$used = 0;
		if ($primaryPdf && !empty($primaryPdf['bytes'])) {
			$blen = strlen((string) $primaryPdf['bytes']);
			if ($blen <= $budget) {
				$invParts[] = [
					'inlineData' => [
						'mimeType' => 'application/pdf',
						'data' => base64_encode((string) $primaryPdf['bytes']),
					],
				];
				$used += $blen;
				$attached++;
			}
		}
		foreach ($pdfFiles as $pf) {
			if ($attached >= 3) {
				break;
			}
			$blen = strlen((string) $pf['bytes']);
			if ($blen <= 0 || $used + $blen > $budget) {
				continue;
			}
			// Skip duplicate of primary
			if ($primaryPdf && isset($primaryPdf['_original']) && ($pf['original'] ?? '') === $primaryPdf['_original']) {
				continue;
			}
			$invParts[] = [
				'inlineData' => [
					'mimeType' => 'application/pdf',
					'data' => base64_encode((string) $pf['bytes']),
				],
			];
			$used += $blen;
			$attached++;
		}
		if (($chr['chars'] ?? 0) < 1200 && !empty($chr['bytes']) && ($chr['ext'] ?? '') === 'pdf'
			&& strlen((string) $chr['bytes']) <= 5 * 1024 * 1024 && $used + strlen((string) $chr['bytes']) <= $budget) {
			$invParts[] = [
				'inlineData' => [
					'mimeType' => 'application/pdf',
					'data' => base64_encode((string) $chr['bytes']),
				],
			];
		}

		$invPrompt = $this->promptFullInventory($dbContext, count($seedList));
		$inventory = $this->generateJson($invPrompt, $invParts, 16384);
		if (!is_array($inventory)) {
			$inventory = [];
		}
		$modules = is_array($inventory['modules'] ?? null) ? $inventory['modules'] : [];

		// Merge missing seed codes
		$have = [];
		foreach ($modules as &$m0) {
			$code0 = DocumentTextExtractor::cleanModuleCode((string) ($m0['code'] ?? ''));
			$m0['code'] = $code0;
			$title0 = DocumentTextExtractor::cleanModuleTitle((string) ($m0['title'] ?? ''));
			if (($title0 === '' || strcasecmp($title0, $code0) === 0) && isset($filenameSeeds[$code0]['title']) && $filenameSeeds[$code0]['title'] !== '') {
				$title0 = $filenameSeeds[$code0]['title'];
			}
			if (($title0 === '' || strcasecmp($title0, $code0) === 0) && isset($chronoTitles[$code0])) {
				$title0 = $chronoTitles[$code0];
			}
			$m0['title'] = $title0 !== '' ? $title0 : $code0;
			if (empty($m0['module_type']) && isset($filenameSeeds[$code0]['module_type'])) {
				$m0['module_type'] = $filenameSeeds[$code0]['module_type'];
			}
			$have[$code0] = true;
		}
		unset($m0);
		foreach ($seedList as $s) {
			$c = DocumentTextExtractor::cleanModuleCode((string) $s['code']);
			if ($c !== '' && empty($have[$c])) {
				$modules[] = [
					'code' => $c,
					'title' => ($s['title'] ?: ($chronoTitles[$c] ?? $c)),
					'rqf_level' => null,
					'learning_hours' => null,
					'module_type' => (string) ($s['module_type'] ?? ''),
					'learning_outcomes' => [],
				];
				$have[$c] = true;
			}
		}

		if ($modules === []) {
			$this->lastError = 'No modules found in curriculum files';
			return null;
		}

		// Ensure chronogram module codes appear even if structure PDF missed them
		$chronoSeed = $this->discoverModuleCodes($chrText, []);
		$have = [];
		foreach ($modules as $m) {
			$have[DocumentTextExtractor::cleanModuleCode((string) ($m['code'] ?? ''))] = true;
		}
		foreach ($chronoSeed as $cc) {
			$cc = DocumentTextExtractor::cleanModuleCode($cc);
			if ($cc === '' || !empty($have[$cc])) {
				continue;
			}
			$titleHint = $chronoTitles[$cc] ?? '';
			if ($titleHint === '' && preg_match('/\b' . preg_quote($cc, '/') . '\s+([^\n\r]{5,90})/i', $chrText, $tm)) {
				$titleHint = DocumentTextExtractor::cleanModuleTitle($tm[1]);
			}
			$modules[] = [
				'code' => $cc,
				'title' => $titleHint ?: $cc,
				'rqf_level' => $inventory['detected_rqf_level'] ?? 4,
				'learning_hours' => null,
				'learning_outcomes' => [],
			];
			$have[$cc] = true;
		}

		// Pass 2 — LO/IC one module at a time with PDF vision when available
		$detailed = [];
		$batchSize = 1;
		$moduleCount = count($modules);
		$this->reportProgress(28, 'Extracting Learning Outcomes & Indicative Contents…', ['modules' => $moduleCount]);
		for ($i = 0, $n = $moduleCount; $i < $n; $i += $batchSize) {
			$batch = array_slice($modules, $i, $batchSize);
			$done = min($i + $batchSize, $n);
			$pct = 28 + (int) round(($done / max(1, $n)) * 48); // 28% → 76%
			$labels = array_map(static function ($m) {
				return trim((string) (($m['code'] ?? '') . ' ' . ($m['title'] ?? '')));
			}, $batch);
			$this->reportProgress($pct, 'Extracting LO/IC (' . $done . '/' . $n . '): ' . implode(', ', array_filter($labels)), [
				'batch' => (int) floor($i / $batchSize) + 1,
				'done' => $done,
				'total' => $n,
			]);
			$m = $batch[0];
			$code = DocumentTextExtractor::cleanModuleCode((string) ($m['code'] ?? ''));
			$title = trim((string) ($m['title'] ?? ''));
			$body = $fileIndex[$code] ?? $this->sliceAroundKeywords($combinedText, array_filter([$code, $title]), 22000);
			$detailPrompt = <<<PROMPT
You are extracting REAL curriculum content for ONE Rwanda TVET/RTB module from the attached document text/PDF.

RULES:
1. Extract ONLY Learning Outcomes (LO) and Indicative Contents (IC) that appear in the source. NEVER invent or guess.
2. If the PDF uses Elements of Competence / Performance Criteria, map them to learning_outcomes + indicative_contents.
3. If this module's LO/IC are not in the provided text/PDF, return learning_outcomes:[].
4. Always keep the exact module code. Prefer the human title from the document (not the code).
5. Include hours when printed next to LO/IC.

Return ONLY JSON:
{"modules":[{"code":"{$code}","title":"","rqf_level":null,"learning_hours":null,"credits":null,"module_type":"specific|general|ccm","purpose":"","learning_outcomes":[{"code":"","title":"","performance_criteria":[string],"indicative_contents":[{"code":"","title":"","hours":null}]}],"raw_topics":[string]}]}

MODULE: {$code} — {$title}

----- MODULE TEXT -----
PROMPT;
			$detailParts = [];
			// Attach module PDF or structure PDF for vision (scanned curricula)
			$pdfAttached = false;
			if (isset($pdfByCode[$code]['bytes']) && strlen((string) $pdfByCode[$code]['bytes']) <= 10 * 1024 * 1024) {
				$detailParts[] = [
					'inlineData' => [
						'mimeType' => 'application/pdf',
						'data' => base64_encode((string) $pdfByCode[$code]['bytes']),
					],
				];
				$pdfAttached = true;
			} elseif ($primaryPdf && !empty($primaryPdf['bytes']) && strlen((string) $primaryPdf['bytes']) <= 10 * 1024 * 1024
				&& mb_strlen($body, 'UTF-8') < 2500) {
				$detailParts[] = [
					'inlineData' => [
						'mimeType' => 'application/pdf',
						'data' => base64_encode((string) $primaryPdf['bytes']),
					],
				];
				$pdfAttached = true;
			}
			$fullPrompt = $detailPrompt . "\n" . mb_substr($this->safeUtf8($body), 0, 28000, 'UTF-8');
			$detail = $this->generateJson($fullPrompt, $detailParts, 16384);
			$got = (is_array($detail) && is_array($detail['modules'][0] ?? null)) ? $detail['modules'][0] : null;
			$loCount = is_array($got['learning_outcomes'] ?? null) ? count($got['learning_outcomes']) : 0;
			// Retry once with structure PDF if empty and we have vision capacity
			if ($loCount === 0 && !$pdfAttached && $primaryPdf && !empty($primaryPdf['bytes'])
				&& strlen((string) $primaryPdf['bytes']) <= 10 * 1024 * 1024) {
				$retryParts = [[
					'inlineData' => [
						'mimeType' => 'application/pdf',
						'data' => base64_encode((string) $primaryPdf['bytes']),
					],
				]];
				$retryPrompt = $detailPrompt
					. "\nFind this module inside the attached curriculum PDF and extract ALL LO/IC. If absent, return empty learning_outcomes.\n\nTEXT:\n"
					. mb_substr($this->safeUtf8($body), 0, 12000, 'UTF-8');
				$detail = $this->generateJson($retryPrompt, $retryParts, 16384);
				if (is_array($detail) && is_array($detail['modules'][0] ?? null)) {
					$got = $detail['modules'][0];
				}
			}
			if (is_array($got) && $code !== '') {
				$detailed[$code] = $got;
			}
		}

		$merged = [];
		foreach ($modules as $m) {
			$key = DocumentTextExtractor::cleanModuleCode((string) ($m['code'] ?? ''));
			$full = ($key !== '' && isset($detailed[$key])) ? array_merge($m, $detailed[$key]) : $m;
			$full['code'] = $key;
			$title = DocumentTextExtractor::cleanModuleTitle((string) ($full['title'] ?? ''));
			if (($title === '' || strcasecmp($title, $key) === 0) && isset($chronoTitles[$key])) {
				$title = $chronoTitles[$key];
			}
			if ($title === '') {
				$title = $key;
			}
			$full['title'] = $title;
			if (empty($full['learning_outcomes']) || !is_array($full['learning_outcomes'])) {
				$full['learning_outcomes'] = [];
			}
			$merged[] = $full;
		}

		// Pass 3 — dedicated chronogram extraction (weekly hours distribution)
		$codes = [];
		foreach ($merged as &$mm) {
			$mm['code'] = DocumentTextExtractor::cleanModuleCode((string) ($mm['code'] ?? ''));
			$mm['title'] = DocumentTextExtractor::cleanModuleTitle((string) ($mm['title'] ?? ''));
			if ($mm['code'] !== '') {
				$codes[] = $mm['code'];
			}
		}
		unset($mm);
		$codes = array_values(array_unique($codes));
		$chronoCodes = $this->discoverModuleCodes($chrText, $codes);
		$this->reportProgress(78, 'Extracting chronogram weekly hours distribution…', ['chrono_codes' => count($chronoCodes)]);
		$chronoResult = $this->extractChronogramDistribution($chr, $chrText, $chronoCodes, $dbContext);
		if (empty($chronoResult['module_slots']) && empty($chronoResult['chronogram']['weeks'])) {
			$this->reportProgress(85, 'Retrying chronogram totals from Modules periods row…');
			$chronoResult = $this->extractChronogramTotalsFallback($chr, $chrText, $chronoCodes, $dbContext);
		}
		$slots = is_array($chronoResult['module_slots'] ?? null) ? $chronoResult['module_slots'] : [];
		// Also harvest totals from chronogram header text (Modules periods / hours)
		$headerTotals = $this->parseChronogramHeaderTotals($chrText, $chronoCodes);
		$weeklyFromText = DocumentTextExtractor::parseChronogramWeeklyHours($chrText);
		foreach ($merged as &$mm) {
			$code = strtoupper(trim((string) ($mm['code'] ?? '')));
			if ($code === '') {
				continue;
			}
			$matchedSlots = $this->findSlotsForCode($code, $slots);
			if ($matchedSlots !== []) {
				$mm['chronogram_slots'] = $this->normalizeChronogramSlots($matchedSlots);
				$mm['weekly_hours_total'] = round(array_sum(array_map(static function ($s) {
					return (float) ($s['hours'] ?? $s['periods'] ?? 0);
				}, $mm['chronogram_slots'])), 1);
			} elseif (($headerTotal = $this->findHeaderTotalForCode($code, $headerTotals)) !== null) {
				$total = (float) $headerTotal;
				// Synthesize week slots from total periods so Scheme of Work has hour budget
				$mm['chronogram_slots'] = $this->synthesizeSlotsFromTotal($total, $chronoResult['chronogram'] ?? []);
				$mm['weekly_hours_total'] = round($total, 1);
				$mm['chronogram_periods_total'] = round($total, 1);
			}
			// Timetable load: typical periods/week from chronogram grid (not yearly totals)
			$hpw = 0.0;
			if (isset($weeklyFromText[$code]['hours_per_week'])) {
				$hpw = (float) $weeklyFromText[$code]['hours_per_week'];
			} else {
				foreach ($weeklyFromText as $wkCode => $wkInfo) {
					if ($this->moduleCodesCompatible($code, (string) $wkCode)) {
						$hpw = (float) ($wkInfo['hours_per_week'] ?? 0);
						break;
					}
				}
			}
			if ($hpw <= 0) {
				$hpw = $this->typicalWeeklyFromSlots($mm['chronogram_slots'] ?? []);
			}
			if ($hpw > 0) {
				$mm['hours_per_week'] = round($hpw, 1);
			}
			if (empty($mm['learning_hours']) && !empty($mm['weekly_hours_total'])) {
				// Chronogram periods ≈ contact hours budget when curriculum hours missing
				$mm['learning_hours'] = round((float) $mm['weekly_hours_total'], 1);
			} elseif (!empty($mm['learning_hours'])) {
				$mm['learning_hours'] = round((float) $mm['learning_hours'], 1);
			}
		}
		unset($mm);

		$chronogramBlock = $chronoResult['chronogram'] ?? ['school_year_label' => '', 'weeks' => []];
		$chronogramBlock = $this->normalizeChronogramBlock($chronogramBlock);
		if (empty($chronogramBlock['weeks']) && $headerTotals !== []) {
			$chronogramBlock['school_year_label'] = $chronogramBlock['school_year_label'] ?: $this->guessChronogramYearLabel($chrText);
			$chronogramBlock['total_weeks'] = 0;
			$chronogramBlock['module_period_totals'] = $headerTotals;
		}

		$raw = [
			'program_type' => $inventory['program_type'] ?? 'tvet',
			'qualification_title' => $inventory['qualification_title'] ?? '',
			'sector' => $inventory['sector'] ?? '',
			'trade' => $inventory['trade'] ?? '',
			'detected_rqf_level' => $inventory['detected_rqf_level'] ?? null,
			'modules' => $merged,
			'chronogram' => $chronogramBlock,
			'hours_distribution' => $this->buildHoursDistributionSummary($merged, $chronogramBlock),
			'notes' => 'Full multi-file curriculum + chronogram extract (' . count($packFiles) . ' curriculum file(s), ' . count($merged) . ' modules)',
			'source_files' => array_values(array_filter(array_merge(
				array_map(static function ($f) {
					return $f['original'] ?? basename($f['path'] ?? '');
				}, $packFiles),
				array_map(static function ($f) {
					return $f['original'] ?? basename($f['path'] ?? '');
				}, $chronoPacks)
			))),
			'_source_text' => mb_substr(
				$combinedText . "\n\n===== CHRONOGRAM SOURCE =====\n" . $chrText,
				0,
				600000,
				'UTF-8'
			),
			'_chronogram_text' => mb_substr($chrText, 0, 200000, 'UTF-8'),
		];

		$weekCount = is_array($chronogramBlock['weeks'] ?? null) ? count($chronogramBlock['weeks']) : 0;
		$slotsFilled = 0;
		$loFilled = 0;
		$icFilled = 0;
		foreach ($merged as $m) {
			$slotsFilled += is_array($m['chronogram_slots'] ?? null) ? count($m['chronogram_slots']) : 0;
			foreach ($m['learning_outcomes'] ?? [] as $lo) {
				$loFilled++;
				$icFilled += is_array($lo['indicative_contents'] ?? null) ? count($lo['indicative_contents']) : 0;
			}
		}

		$raw['_extract_meta'] = [
			'curriculum_chars' => mb_strlen($combinedText, 'UTF-8'),
			'chronogram_chars' => mb_strlen($chrText, 'UTF-8'),
			'file_count' => count($packFiles),
			'pdf_files' => count($pdfFiles),
			'filename_module_pdfs' => count($filenameSeeds),
			'seed_codes' => count($seedList),
			'module_count' => count($merged),
			'lo_count' => $loFilled,
			'ic_count' => $icFilled,
			'chronogram_weeks' => $weekCount,
			'chronogram_slots_filled' => $slotsFilled,
			'mode' => 'full_package_multipass_pdf_vision_loic',
		];
		if ($loFilled === 0) {
			$raw['notes'] = ($raw['notes'] ?? '')
				. ' WARNING: 0 Learning Outcomes extracted — upload the full RTB package (General Information + Specific Modules + General Modules + CCM Modules) as multiple PDFs or one ZIP, then Re-analyse.';
		}
		if (count($filenameSeeds) < 3 && count($packFiles) <= 2) {
			$raw['notes'] = ($raw['notes'] ?? '')
				. ' TIP: Package looks incomplete (only ' . count($packFiles) . ' curriculum file(s)). Add Specific/General/CCM module PDFs.';
		}
		$this->reportProgress(92, 'Matching modules to school courses…', [
			'modules' => count($merged),
			'chronogram_weeks' => $weekCount,
			'chronogram_slots' => $slotsFilled,
		]);
		$result = $this->matchToDb($raw, $dbContext);
		$this->reportProgress(100, 'Analysis complete', ['status' => 'done', 'modules' => count($merged)]);
		return $result;
	}

	/**
	 * Dedicated chronogram pass — always prefer PDF vision for weekly grids.
	 *
	 * @param array $chr extractor result
	 * @param list<string> $moduleCodes
	 * @return array{chronogram?:array,module_slots?:array}
	 */
	private function extractChronogramDistribution(array $chr, string $chrText, array $moduleCodes, array $dbContext): array
	{
		$classMeta = json_encode($dbContext['class'] ?? [], JSON_UNESCAPED_UNICODE);
		$year = (string) ($dbContext['academic_year_title'] ?? '');
		$prompt = <<<PROMPT
You are reading a Rwanda school CHRONOGRAM (weekly teaching timetable / distribution of hours).

GOAL: Extract EVERY week and how many periods/hours each module is taught that week.
This data is used later to distribute Scheme of Work content across weeks.

CLASS: {$classMeta}
YEAR: {$year}
MODULE CODES TO MAP: 
PROMPT
			. json_encode(array_values($moduleCodes), JSON_UNESCAPED_UNICODE)
			. <<<PROMPT


RULES:
1. Chronograms are often grids/tables — read the ATTACHED PDF carefully (do not invent empty grids).
2. Module codes in THIS chronogram look like SWDDD401, SWDBS401, SWDDA401, GENNF401, CCMCZ401 (use EXACT codes from the PDF).
3. For each week capture: week number, term (1/2/3), date_from, date_to.
4. Week cells show PERIODS (not empty). If a cell is blank, periods=0 for that module that week.
5. Also read "Modules periods" / "Modules hours" header rows for yearly totals.
6. Cover FIRST TERM, SECOND TERM, THIRD TERM when present — do not stop after a few weeks.
7. module_slots must list every week each module appears (including 0-period weeks is optional; prefer weeks with periods>0).
8. Never invent module codes with locale prefixes (no en-ZA / ZACCM…).

Return ONLY JSON:
{
  "chronogram": {
    "school_year_label": "",
    "total_weeks": 0,
    "weeks": [
      {
        "week": 1,
        "term": 1,
        "date_from": "",
        "date_to": "",
        "modules": [{"code":"SWDDD401","periods":4,"hours":4}]
      }
    ]
  },
  "module_slots": {
    "SWDDD401": [{"week":1,"term":1,"date_from":"","date_to":"","periods":4,"hours":4}]
  }
}
PROMPT;

		$parts = [[
			'text' => $prompt . "\n\n=== CHRONOGRAM EXTRACTED TEXT ===\n"
				. ($chrText !== '' ? mb_substr($chrText, 0, 100000, 'UTF-8') : '(little/no text — rely on attached PDF grid)'),
		]];

		// Always attach chronogram PDF/image when reasonably sized (grids need vision)
		$ext = strtolower((string) ($chr['ext'] ?? ''));
		$max = 10 * 1024 * 1024;
		if (!empty($chr['bytes']) && strlen((string) $chr['bytes']) <= $max
			&& in_array($ext, ['pdf', 'png', 'jpg', 'jpeg', 'webp'], true)) {
			$mime = (string) ($chr['mime'] ?? '');
			if ($mime === '') {
				$mime = $ext === 'pdf' ? 'application/pdf' : 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext);
			}
			$parts[] = [
				'inlineData' => [
					'mimeType' => $mime,
					'data' => base64_encode((string) $chr['bytes']),
				],
			];
		}

		$result = $this->generateJson(
			'Extract the complete chronogram weekly hours distribution JSON as specified in the attached content. JSON only.',
			$parts,
			16384
		);
		if (!is_array($result)) {
			return [];
		}
		// Normalize slot keys (clean locale-glued codes)
		if (is_array($result['module_slots'] ?? null)) {
			$cleanSlots = [];
			foreach ($result['module_slots'] as $sk => $sv) {
				$ck = DocumentTextExtractor::cleanModuleCode((string) $sk);
				if ($ck === '' || !is_array($sv)) {
					continue;
				}
				$cleanSlots[$ck] = array_merge($cleanSlots[$ck] ?? [], $sv);
			}
			$result['module_slots'] = $cleanSlots;
		}
		return $result;
	}

	/**
	 * Lighter chronogram pass: totals per module + as many week rows as possible.
	 *
	 * @param list<string> $moduleCodes
	 * @return array{chronogram?:array,module_slots?:array}
	 */
	private function extractChronogramTotalsFallback(array $chr, string $chrText, array $moduleCodes, array $dbContext): array
	{
		$codesJson = json_encode(array_values($moduleCodes), JSON_UNESCAPED_UNICODE);
		$prompt = <<<PROMPT
Read this Rwanda TVET TRAINING CHRONOGRAM.
MODULE CODES: {$codesJson}

Return ONLY JSON with:
1) module_period_totals — from the "Modules periods" header row (total periods per module for the year)
2) module_slots — weekly grid when readable (week, term, periods). Prefer periods from the week cells.

{
  "module_period_totals": {"SWDDD401":135,"SWDBS401":105},
  "module_slots": {"SWDBS401":[{"week":1,"term":1,"date_from":"25/09/2023","date_to":"29/09/2023","periods":0,"hours":0}]},
  "chronogram": {"school_year_label":"2023-2024","weeks":[]}
}
PROMPT;
		$parts = [['text' => $prompt . "\n\n=== TEXT ===\n" . mb_substr($chrText, 0, 80000, 'UTF-8')]];
		$ext = strtolower((string) ($chr['ext'] ?? ''));
		if (!empty($chr['bytes']) && strlen((string) $chr['bytes']) <= 10 * 1024 * 1024
			&& in_array($ext, ['pdf', 'png', 'jpg', 'jpeg', 'webp'], true)) {
			$parts[] = [
				'inlineData' => [
					'mimeType' => (string) ($chr['mime'] ?: 'application/pdf'),
					'data' => base64_encode((string) $chr['bytes']),
				],
			];
		}
		$result = $this->generateJson('Return chronogram totals/slots JSON only.', $parts, 12288);
		if (!is_array($result)) {
			return [];
		}
		// Promote totals into slots when week grid missing
		$totals = is_array($result['module_period_totals'] ?? null) ? $result['module_period_totals'] : [];
		$slots = is_array($result['module_slots'] ?? null) ? $result['module_slots'] : [];
		foreach ($totals as $code => $periods) {
			$code = DocumentTextExtractor::cleanModuleCode((string) $code);
			if ($code === '' || !empty($slots[$code])) {
				continue;
			}
			$p = (float) $periods;
			$slots[$code] = $this->synthesizeSlotsFromTotal($p, $result['chronogram'] ?? []);
		}
		$result['module_slots'] = $slots;
		return $result;
	}

	/**
	 * Parse "Modules periods" / codes from chronogram text without AI.
	 *
	 * @param list<string> $preferredCodes
	 * @return array<string,float> code => total periods
	 */
	private function parseChronogramHeaderTotals(string $chrText, array $preferredCodes = []): array
	{
		$text = DocumentTextExtractor::cleanPedagogicalText($chrText);
		$codes = $this->discoverModuleCodes($text, $preferredCodes);
		if ($codes === []) {
			return [];
		}
		$totals = [];
		// Prefer an AI-free pairing when line contains "Modules periods"
		if (preg_match('/Modules\s+periods\s+([0-9\s]+)/i', $text, $m)) {
			$nums = array_values(array_filter(array_map('floatval', preg_split('/\s+/', trim($m[1])) ?: []), static function ($n) {
				return $n > 0;
			}));
			// Visual RTB order often matches first occurrence order of codes near header — best-effort
			$headerChunk = mb_substr($text, 0, 2500, 'UTF-8');
			$headerCodes = $this->discoverModuleCodes($headerChunk, []);
			// Keep preferred order when possible
			$ordered = [];
			foreach ($preferredCodes as $c) {
				$c = DocumentTextExtractor::cleanModuleCode($c);
				if ($c !== '' && in_array($c, $headerCodes, true)) {
					$ordered[] = $c;
				}
			}
			foreach ($headerCodes as $c) {
				if (!in_array($c, $ordered, true)) {
					$ordered[] = $c;
				}
			}
			$n = min(count($ordered), count($nums));
			for ($i = 0; $i < $n; $i++) {
				$totals[$ordered[$i]] = $nums[$i];
			}
		}
		// Direct "CODE … NNN" near module titles
		foreach ($codes as $code) {
			if (isset($totals[$code])) {
				continue;
			}
			if (preg_match('/\b' . preg_quote($code, '/') . '\b[^0-9]{0,80}?(\d{2,3})\b/i', $text, $mm)) {
				$val = (float) $mm[1];
				if ($val >= 20 && $val <= 400) {
					$totals[$code] = $val;
				}
			}
		}
		return $totals;
	}

	/** @param array<string,mixed> $slots */
	private function findSlotsForCode(string $code, array $slots): array
	{
		$code = DocumentTextExtractor::cleanModuleCode($code);
		if ($code !== '' && isset($slots[$code]) && is_array($slots[$code])) {
			return $slots[$code];
		}
		foreach ($slots as $sk => $sv) {
			if (!is_array($sv)) {
				continue;
			}
			if ($this->moduleCodesCompatible($code, DocumentTextExtractor::cleanModuleCode((string) $sk))) {
				return $sv;
			}
		}
		return [];
	}

	/** @param array<string,float> $headerTotals */
	private function findHeaderTotalForCode(string $code, array $headerTotals): ?float
	{
		$code = DocumentTextExtractor::cleanModuleCode($code);
		if ($code !== '' && isset($headerTotals[$code])) {
			return (float) $headerTotals[$code];
		}
		foreach ($headerTotals as $sk => $val) {
			if ($this->moduleCodesCompatible($code, DocumentTextExtractor::cleanModuleCode((string) $sk))) {
				return (float) $val;
			}
		}
		return null;
	}

	private function moduleCodesCompatible(string $a, string $b): bool
	{
		$a = DocumentTextExtractor::cleanModuleCode($a);
		$b = DocumentTextExtractor::cleanModuleCode($b);
		if ($a === '' || $b === '') {
			return false;
		}
		if ($a === $b) {
			return true;
		}
		if (!preg_match('/^([A-Z]+)(\d{3})$/', $a, $ma) || !preg_match('/^([A-Z]+)(\d{3})$/', $b, $mb)) {
			return false;
		}
		if ($ma[2] !== $mb[2]) {
			return false;
		}
		// Same trailing digits; letter stems close (SWDBS vs SWBS, SWDPP vs SWDP)
		if (strpos($ma[1], $mb[1]) !== false || strpos($mb[1], $ma[1]) !== false) {
			return true;
		}
		return levenshtein($ma[1], $mb[1]) <= 2;
	}

	/**
	 * @param array<string,mixed> $chronogramBlock
	 * @return list<array<string,mixed>>
	 */
	private function synthesizeSlotsFromTotal(float $totalPeriods, array $chronogramBlock): array
	{
		if ($totalPeriods <= 0) {
			return [];
		}
		$weeks = is_array($chronogramBlock['weeks'] ?? null) ? $chronogramBlock['weeks'] : [];
		$weekCount = count($weeks) >= 4 ? count($weeks) : 30;
		$per = round($totalPeriods / $weekCount, 1);
		if ($per <= 0) {
			$per = 1.0;
		}
		// Cap so a yearly total never looks like one week's timetable load
		if ($per > 16) {
			$per = 16.0;
		}
		$out = [];
		if (count($weeks) >= 4) {
			foreach ($weeks as $w) {
				$out[] = [
					'week' => (int) ($w['week'] ?? 0),
					'term' => (int) ($w['term'] ?? 0) ?: null,
					'date_from' => (string) ($w['date_from'] ?? ''),
					'date_to' => (string) ($w['date_to'] ?? ''),
					'periods' => $per,
					'hours' => $per,
				];
			}
			return $out;
		}
		for ($i = 1; $i <= $weekCount; $i++) {
			$out[] = [
				'week' => $i,
				'term' => $i <= 13 ? 1 : ($i <= 25 ? 2 : 3),
				'date_from' => '',
				'date_to' => '',
				'periods' => $per,
				'hours' => $per,
			];
		}
		return $out;
	}

	/** Typical weekly periods from slot list (ignores annual dump values). */
	private function typicalWeeklyFromSlots($slots): float
	{
		if (!is_array($slots) || $slots === []) {
			return 0.0;
		}
		$vals = [];
		foreach ($slots as $s) {
			if (!is_array($s)) {
				continue;
			}
			$p = (float) ($s['periods'] ?? $s['hours'] ?? 0);
			if ($p > 0 && $p <= 20) {
				$vals[] = $p;
			}
		}
		if ($vals === []) {
			return 0.0;
		}
		$counts = [];
		foreach ($vals as $v) {
			$key = (string) round($v, 1);
			$counts[$key] = ($counts[$key] ?? 0) + 1;
		}
		arsort($counts);
		reset($counts);
		$top = key($counts);
		return $top !== null ? (float) $top : round(array_sum($vals) / count($vals), 1);
	}

	private function guessChronogramYearLabel(string $chrText): string
	{
		if (preg_match('/SCHOOL\s+YEAR\s+(\d{4}\s*[-–]\s*\d{4})/i', $chrText, $m)) {
			return trim(preg_replace('/\s+/', '', $m[1]) ?? $m[1]);
		}
		return '';
	}

	/** @param list<array<string,mixed>> $slots */
	private function normalizeChronogramSlots(array $slots): array
	{
		$out = [];
		foreach ($slots as $s) {
			if (!is_array($s)) {
				continue;
			}
			$periods = isset($s['periods']) ? (float) $s['periods'] : null;
			$hours = isset($s['hours']) ? (float) $s['hours'] : null;
			if ($hours === null && $periods !== null) {
				$hours = $periods;
			}
			if ($periods === null && $hours !== null) {
				$periods = $hours;
			}
			if ($periods !== null) {
				$periods = round($periods, 1);
			}
			if ($hours !== null) {
				$hours = round($hours, 1);
			}
			$out[] = [
				'week' => (int) ($s['week'] ?? 0),
				'term' => (int) ($s['term'] ?? 0) ?: null,
				'date_from' => (string) ($s['date_from'] ?? ''),
				'date_to' => (string) ($s['date_to'] ?? ''),
				'periods' => $periods,
				'hours' => $hours,
			];
		}
		usort($out, static function ($a, $b) {
			return ($a['week'] ?? 0) <=> ($b['week'] ?? 0);
		});
		return $out;
	}

	private function normalizeChronogramBlock(array $block): array
	{
		$weeks = [];
		foreach ($block['weeks'] ?? [] as $w) {
			if (!is_array($w)) {
				continue;
			}
			$mods = [];
			foreach ($w['modules'] ?? [] as $m) {
				if (!is_array($m)) {
					continue;
				}
				$periods = isset($m['periods']) ? (float) $m['periods'] : null;
				$hours = isset($m['hours']) ? (float) $m['hours'] : null;
				if ($hours === null && $periods !== null) {
					$hours = $periods;
				}
				$mods[] = [
					'code' => strtoupper(trim((string) ($m['code'] ?? ''))),
					'periods' => $periods,
					'hours' => $hours,
				];
			}
			$weeks[] = [
				'week' => (int) ($w['week'] ?? 0),
				'term' => (int) ($w['term'] ?? 0) ?: null,
				'date_from' => (string) ($w['date_from'] ?? ''),
				'date_to' => (string) ($w['date_to'] ?? ''),
				'modules' => $mods,
			];
		}
		usort($weeks, static function ($a, $b) {
			return ($a['week'] ?? 0) <=> ($b['week'] ?? 0);
		});
		return [
			'school_year_label' => (string) ($block['school_year_label'] ?? ''),
			'total_weeks' => (int) ($block['total_weeks'] ?? count($weeks)),
			'weeks' => $weeks,
		];
	}

	/**
	 * @param list<array<string,mixed>> $modules
	 */
	private function buildHoursDistributionSummary(array $modules, array $chronogramBlock): array
	{
		$byModule = [];
		foreach ($modules as $m) {
			$code = strtoupper(trim((string) ($m['code'] ?? '')));
			if ($code === '') {
				continue;
			}
			$slots = is_array($m['chronogram_slots'] ?? null) ? $m['chronogram_slots'] : [];
			$hours = 0.0;
			foreach ($slots as $s) {
				$hours += (float) ($s['hours'] ?? $s['periods'] ?? 0);
			}
			$byModule[$code] = [
				'title' => (string) ($m['title'] ?? ''),
				'weeks' => count($slots),
				'total_hours' => $hours,
			];
		}
		return [
			'total_weeks' => count($chronogramBlock['weeks'] ?? []),
			'modules_with_slots' => count(array_filter($byModule, static function ($x) {
				return ($x['weeks'] ?? 0) > 0;
			})),
			'by_module' => $byModule,
		];
	}

	/**
	 * @param array{path:string,original?:string} $curriculum
	 * @param list<array{path:string,original?:string}> $extraFiles
	 * @return list<array{path:string,original?:string}>
	 */
	private function expandCurriculumPackage(array $curriculum, array $extraFiles = []): array
	{
		$out = [];
		$path = $curriculum['path'] ?? '';
		if ($path === '' || !is_file($path)) {
			return $extraFiles;
		}
		$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
		if ($ext === 'zip' && class_exists(\ZipArchive::class)) {
			$dest = dirname($path) . '/pkg_' . pathinfo($path, PATHINFO_FILENAME);
			if (!is_dir($dest)) {
				@mkdir($dest, 0755, true);
				$zip = new \ZipArchive();
				if ($zip->open($path) === true) {
					$zip->extractTo($dest);
					$zip->close();
				}
			}
			$destReal = realpath($dest) ?: $dest;
			$rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dest, \FilesystemIterator::SKIP_DOTS));
			foreach ($rii as $file) {
				/** @var \SplFileInfo $file */
				if (!$file->isFile()) {
					continue;
				}
				$e = strtolower($file->getExtension());
				if (!in_array($e, ['pdf', 'doc', 'docx'], true)) {
					continue;
				}
				$full = $file->getPathname();
				$rel = $full;
				if (strpos($full, $destReal) === 0) {
					$rel = ltrim(substr($full, strlen($destReal)), '/\\');
				}
				$out[] = ['path' => $full, 'original' => str_replace('\\', '/', $rel)];
			}
		} else {
			$out[] = $curriculum;
		}
		foreach ($extraFiles as $f) {
			if (!empty($f['path']) && is_file($f['path'])) {
				$out[] = $f;
			}
		}
		// de-dupe by realpath
		$seen = [];
		$uniq = [];
		foreach ($out as $f) {
			$rp = realpath($f['path']) ?: $f['path'];
			if (isset($seen[$rp])) {
				continue;
			}
			$seen[$rp] = true;
			$uniq[] = $f;
		}
		// RTB package order: General Information → Specific → General → CCM
		usort($uniq, static function ($a, $b) {
			$ka = DocumentTextExtractor::curriculumFileSortKey(($a['original'] ?? '') . ' ' . ($a['path'] ?? ''));
			$kb = DocumentTextExtractor::curriculumFileSortKey(($b['original'] ?? '') . ' ' . ($b['path'] ?? ''));
			if ($ka !== $kb) {
				return $ka <=> $kb;
			}
			return strcasecmp((string) ($a['original'] ?? ''), (string) ($b['original'] ?? ''));
		});
		return $uniq;
	}

	/** @param list<string> $extra */
	private function discoverModuleCodes(string $text, array $extra = []): array
	{
		$text = DocumentTextExtractor::cleanPedagogicalText($text);
		$codes = [];
		foreach ($extra as $c) {
			$c = DocumentTextExtractor::cleanModuleCode((string) $c);
			if ($c !== '') {
				$codes[$c] = true;
			}
		}
		if (preg_match_all('/\b([A-Z]{3,8}\d{3})\b/', strtoupper($text), $m)) {
			foreach ($m[1] as $c) {
				$c = DocumentTextExtractor::cleanModuleCode($c);
				if ($c === '') {
					continue;
				}
				// Filter obvious non-module tokens
				if (preg_match('/^(PAGE|HTTP|HTTPS|RQF|TVET|ICTSWD|LEVEL|DATE)$/', $c)) {
					continue;
				}
				$codes[$c] = true;
			}
		}
		$out = array_keys($codes);
		sort($out);
		return $out;
	}

	private function promptFullInventory(array $ctx, int $seedCount): string
	{
		$dbCourses = json_encode($ctx['courses'] ?? [], JSON_UNESCAPED_UNICODE);
		$classMeta = json_encode($ctx['class'] ?? [], JSON_UNESCAPED_UNICODE);
		$school = json_encode($ctx['school'] ?? [], JSON_UNESCAPED_UNICODE);

		return <<<PROMPT
You are extracting a COMPLETE module inventory from a Rwanda TVET curriculum package (structure PDF + optional module PDFs).

CRITICAL: Return EVERY module/course. Seed list has about {$seedCount} codes — your modules[] must include ALL of them (CCM + General + Specific).
Do not stop after a few Specific modules.
Use REAL human-readable titles from the curriculum/chronogram (never leave title equal to the code when a name exists).
Do NOT invent Learning Outcomes here — leave learning_outcomes as [].

SCHOOL: {$school}
CLASS: {$classMeta}
DB COURSES: {$dbCourses}

Return ONLY JSON:
{
  "program_type": "tvet",
  "qualification_title": "",
  "sector": "",
  "trade": "",
  "detected_rqf_level": 4,
  "modules": [
    {"code":"","title":"","rqf_level":4,"learning_hours":null,"credits":null,"module_type":"specific|general|ccm","learning_outcomes":[]}
  ],
  "chronogram": {"school_year_label":"","weeks":[]}
}
PROMPT;
	}

	/**
	 * Build compact multimodal parts for Word/PDF (text first; PDF binary only when needed).
	 *
	 * @param array $cur
	 * @param array $chr
	 * @return list<array<string,mixed>>
	 */
	private function buildQuickAnalyseParts(
		array $curriculum,
		?array $chronogram,
		array $cur,
		array $chr,
		string $curText,
		string $chrText
	): array {
		$parts = [];
		$curExt = strtolower((string) ($cur['ext'] ?? ''));
		$chrExt = strtolower((string) ($chr['ext'] ?? ''));
		$curOrig = $curriculum['original'] ?? basename($curriculum['path'] ?? 'curriculum');
		$chrOrig = $chronogram['original'] ?? 'chronogram';

		$curSample = $this->buildInventorySample($curText);
		if ($curSample === '' && in_array($curExt, ['doc', 'docx'], true)) {
			$parts[] = ['text' => "=== CURRICULUM FILE: {$curOrig} (Word, little extractable text) ===\nRe-save as PDF if analysis is empty."];
		} else {
			$parts[] = [
				'text' => "=== CURRICULUM FILE: {$curOrig} (ext={$curExt}, chars=" . mb_strlen($curText, 'UTF-8') . ") ===\n"
					. ($curSample !== '' ? $curSample : '(no text extracted)'),
			];
		}

		// Attach curriculum PDF only when text is sparse (scanned)
		if (
			$curExt === 'pdf'
			&& mb_strlen($curText, 'UTF-8') < 1500
			&& !empty($cur['bytes'])
			&& strlen((string) $cur['bytes']) <= 6 * 1024 * 1024
		) {
			$parts[] = [
				'inlineData' => [
					'mimeType' => 'application/pdf',
					'data' => base64_encode((string) $cur['bytes']),
				],
			];
		}

		$parts[] = [
			'text' => "=== CHRONOGRAM FILE: {$chrOrig} (ext={$chrExt}, chars=" . mb_strlen($chrText, 'UTF-8') . ") ===\n"
				. ($chrText !== '' ? mb_substr($chrText, 0, 40000, 'UTF-8') : '(no text — use attached PDF grid if present)'),
		];
		if (
			in_array($chrExt, ['pdf', 'png', 'jpg', 'jpeg'], true)
			&& mb_strlen($chrText, 'UTF-8') < 1500
			&& !empty($chr['bytes'])
			&& strlen((string) $chr['bytes']) <= 5 * 1024 * 1024
		) {
			$parts[] = [
				'inlineData' => [
					'mimeType' => ($chr['mime'] ?? '') ?: ($chrExt === 'pdf' ? 'application/pdf' : 'image/png'),
					'data' => base64_encode((string) $chr['bytes']),
				],
			];
		}

		return $parts;
	}

	private function promptQuickAnalyse(array $ctx, int $curChars, int $chrChars, string $curExt, string $chrExt): string
	{
		$dbCourses = json_encode($ctx['courses'] ?? [], JSON_UNESCAPED_UNICODE);
		$classMeta = json_encode($ctx['class'] ?? [], JSON_UNESCAPED_UNICODE);
		$school = json_encode($ctx['school'] ?? [], JSON_UNESCAPED_UNICODE);
		$year = (string) ($ctx['academic_year_title'] ?? '');

		return <<<PROMPT
You are a fast Rwanda TVET/REB curriculum parser. Documents are Word (.doc/.docx) or PDF.

GOAL (one pass): Extract ALL courses/modules from CURRICULUM with LO + Indicative Contents, and map weeks/hours from CHRONOGRAM.

Doc meta: curriculum_ext={$curExt} chars={$curChars}; chronogram_ext={$chrExt} chars={$chrChars}; year={$year}

RULES:
1. Work from EXTRACTED TEXT. If a PDF is attached with little text, read the PDF visually.
2. List EVERY module/course (code + title). Do not stop early.
3. For each module include learning_outcomes (LO) and indicative_contents (IC) with hours when present.
4. From chronogram, fill chronogram.weeks and each module's chronogram_slots (week, term, dates, periods).
5. Prefer matching DB course codes when obvious.

SCHOOL: {$school}
CLASS: {$classMeta}
DB COURSES: {$dbCourses}

Return ONLY valid JSON (no markdown):
{
  "program_type": "tvet",
  "qualification_title": "",
  "sector": "",
  "trade": "",
  "detected_rqf_level": null,
  "modules": [
    {
      "code": "",
      "title": "",
      "rqf_level": null,
      "learning_hours": null,
      "credits": null,
      "module_type": "",
      "learning_outcomes": [{"code":"","title":"","indicative_contents":[{"code":"","title":"","hours":null}]}],
      "chronogram_slots": [{"week":1,"term":1,"date_from":"","date_to":"","periods":0}],
      "matched_course_id": null,
      "match_confidence": 0,
      "match_reason": ""
    }
  ],
  "chronogram": {"school_year_label":"","weeks":[{"week":1,"term":1,"date_from":"","date_to":"","modules":[{"code":"","periods":0}]}]},
  "notes": ""
}
PROMPT;
	}

	/**
	 * Multi-pass analyser: inventory → detail batches → chronogram map.
	 * Avoids stuffing a huge curriculum into one Gemini call.
	 *
	 * @param array{path:string,original?:string} $curriculum
	 * @param array{path:string,original?:string}|null $chronogram
	 * @param array $cur
	 * @param array $chr
	 */
	private function analyzeLargeCurriculum(
		string $curText,
		string $chrText,
		array $curriculum,
		?array $chronogram,
		array $cur,
		array $chr,
		array $dbContext
	): ?array {
		$ctxBrief = $this->promptAnalyze($dbContext);

		// Pass 1 — module inventory (head + sampled body + chronogram summary)
		$inventoryText = $this->buildInventorySample($curText);
		$parts1 = [[
			'text' => "=== CURRICULUM SAMPLE (large document — inventory pass) ===\n" . $inventoryText
				. "\n\n=== CHRONOGRAM TEXT (may be truncated) ===\n" . mb_substr($chrText, 0, 40000, 'UTF-8'),
		]];
		// Attach chronogram PDF when text is sparse (weekly grids)
		if (($chr['chars'] ?? 0) < 1200 && !empty($chr['bytes']) && in_array(($chr['ext'] ?? ''), ['pdf', 'png', 'jpg', 'jpeg'], true)
			&& strlen((string) $chr['bytes']) <= 4 * 1024 * 1024) {
			$parts1[] = [
				'inlineData' => [
					'mimeType' => $chr['mime'] ?: 'application/pdf',
					'data' => base64_encode((string) $chr['bytes']),
				],
			];
		}

		$invPrompt = $ctxBrief . "\n\nIMPORTANT: This is PASS 1 (INVENTORY ONLY) of a LARGE curriculum.\n"
			. "Return JSON with program_type, qualification_title, sector, trade, detected_rqf_level,\n"
			. "and modules array with ONLY code+title+rqf_level+learning_hours (leave learning_outcomes empty for now).\n"
			. "List EVERY module/course code you can find. Also return a chronogram.weeks skeleton if visible.";
		$inventory = $this->generateJson($invPrompt, $parts1);
		if ($inventory === null) {
			return null;
		}

		$modules = is_array($inventory['modules'] ?? null) ? $inventory['modules'] : [];
		if ($modules === []) {
			// Fallback: single-shot on truncated curriculum
			$parts = [[
				'text' => "=== CURRICULUM (truncated) ===\n" . mb_substr($curText, 0, 90000, 'UTF-8')
					. "\n\n=== CHRONOGRAM ===\n" . mb_substr($chrText, 0, 50000, 'UTF-8'),
			]];
			return $this->generateJson($this->promptAnalyze($dbContext), $parts);
		}

		// Pass 2 — detail batches (LO / IC) using text windows around each module code
		$detailed = [];
		$batchSize = 4;
		for ($i = 0, $n = count($modules); $i < $n; $i += $batchSize) {
			$batch = array_slice($modules, $i, $batchSize);
			$windows = '';
			foreach ($batch as $m) {
				$code = trim((string) ($m['code'] ?? ''));
				$title = trim((string) ($m['title'] ?? ''));
				$slice = $this->sliceAroundKeywords($curText, array_filter([$code, $title]), 9000);
				$windows .= "\n\n----- MODULE WINDOW: {$code} {$title} -----\n" . $slice;
			}
			$detailPrompt = <<<PROMPT
You are extracting Learning Outcomes and Indicative Contents for a batch of TVET/REB modules from curriculum text windows.

Return ONLY JSON:
{
  "modules": [
    {
      "code": string,
      "title": string,
      "rqf_level": number|null,
      "learning_hours": number|null,
      "credits": number|null,
      "module_type": string,
      "learning_outcomes": [{"code":string,"title":string,"indicative_contents":[{"code":string,"title":string,"hours":number|null}]}]
    }
  ]
}

Fill learning_outcomes thoroughly for each module in the batch. Use the module codes/titles below as anchors:
PROMPT
				. json_encode($batch, JSON_UNESCAPED_UNICODE)
				. "\n\nTEXT WINDOWS:\n" . $this->safeUtf8($windows);

			$detail = $this->generateJson($detailPrompt, []);
			if (is_array($detail) && !empty($detail['modules']) && is_array($detail['modules'])) {
				foreach ($detail['modules'] as $dm) {
					$key = strtoupper(trim((string) ($dm['code'] ?? ''))) ?: strtolower(trim((string) ($dm['title'] ?? '')));
					if ($key !== '') {
						$detailed[$key] = $dm;
					}
				}
			}
		}

		// Merge inventory + details
		$mergedModules = [];
		foreach ($modules as $m) {
			$key = strtoupper(trim((string) ($m['code'] ?? ''))) ?: strtolower(trim((string) ($m['title'] ?? '')));
			$full = $detailed[$key] ?? $m;
			$mergedModules[] = array_merge($m, is_array($full) ? $full : []);
		}

		// Pass 3 — chronogram mapping
		$chronoPrompt = <<<PROMPT
Map chronogram weeks/hours to the module codes below. Return ONLY JSON:
{
  "chronogram": {
    "school_year_label": string,
    "weeks": [{"week":number,"term":number,"date_from":string,"date_to":string,"modules":[{"code":string,"periods":number}]}]
  },
  "module_slots": {"MODULE_CODE":[{"week":number,"term":number,"date_from":string,"date_to":string,"periods":number}]}
}

MODULE CODES:
PROMPT
			. json_encode(array_values(array_filter(array_map(static function ($m) {
				return $m['code'] ?? null;
			}, $mergedModules))), JSON_UNESCAPED_UNICODE)
			. "\n\nCHRONOGRAM TEXT:\n" . mb_substr($chrText, 0, 70000, 'UTF-8');

		$chronoParts = [['text' => $chronoPrompt]];
		if (($chr['chars'] ?? 0) < 1200 && !empty($chr['bytes']) && ($chr['ext'] ?? '') === 'pdf'
			&& strlen((string) $chr['bytes']) <= 4 * 1024 * 1024) {
			$chronoParts[] = [
				'inlineData' => [
					'mimeType' => 'application/pdf',
					'data' => base64_encode((string) $chr['bytes']),
				],
			];
		}
		$chrono = $this->generateJson('Return the chronogram mapping JSON as specified.', $chronoParts);
		$slots = is_array($chrono['module_slots'] ?? null) ? $chrono['module_slots'] : [];
		foreach ($mergedModules as &$mm) {
			$code = (string) ($mm['code'] ?? '');
			if ($code !== '' && !empty($slots[$code]) && is_array($slots[$code])) {
				$mm['chronogram_slots'] = $slots[$code];
			} elseif ($code !== '') {
				foreach ($slots as $sk => $sv) {
					if (strcasecmp((string) $sk, $code) === 0 && is_array($sv)) {
						$mm['chronogram_slots'] = $sv;
						break;
					}
				}
			}
		}
		unset($mm);

		return [
			'program_type' => $inventory['program_type'] ?? null,
			'qualification_title' => $inventory['qualification_title'] ?? '',
			'sector' => $inventory['sector'] ?? '',
			'trade' => $inventory['trade'] ?? '',
			'detected_rqf_level' => $inventory['detected_rqf_level'] ?? null,
			'modules' => $mergedModules,
			'chronogram' => $chrono['chronogram'] ?? ($inventory['chronogram'] ?? ['school_year_label' => '', 'weeks' => []]),
			'notes' => 'Chunked smart analysis for large curriculum',
		];
	}

	private function buildInventorySample(string $text): string
	{
		$len = mb_strlen($text, 'UTF-8');
		if ($len <= 45000) {
			return $text;
		}
		$head = mb_substr($text, 0, 18000, 'UTF-8');
		$midStart = (int) max(0, (int) ($len / 2) - 9000);
		$mid = mb_substr($text, $midStart, 18000, 'UTF-8');
		$tail = mb_substr($text, max(0, $len - 12000), 12000, 'UTF-8');
		return $head . "\n\n[... middle sample ...]\n\n" . $mid . "\n\n[... end sample ...]\n\n" . $tail;
	}

	/** @param list<string> $keywords */
	private function sliceAroundKeywords(string $text, array $keywords, int $window = 8000): string
	{
		if ($text === '') {
			return '';
		}
		$bestPos = null;
		foreach ($keywords as $kw) {
			$kw = trim((string) $kw);
			if ($kw === '' || mb_strlen($kw, 'UTF-8') < 3) {
				continue;
			}
			$pos = mb_stripos($text, $kw, 0, 'UTF-8');
			if ($pos !== false && ($bestPos === null || $pos < $bestPos)) {
				$bestPos = $pos;
			}
		}
		if ($bestPos === null) {
			return mb_substr($text, 0, $window, 'UTF-8');
		}
		$start = max(0, $bestPos - (int) ($window / 4));
		return mb_substr($text, $start, $window, 'UTF-8');
	}

	/**
	 * Generate Scheme of Work HTML + structured JSON.
	 *
	 * @return array{html:string,json:array,title:string}|null
	 */
	public function generateSchemeOfWork(array $module, array $dbContext, string $programType, ?array $chronogramAnalysis = null): ?array
	{
		$prompt = $this->promptScheme($module, $dbContext, $programType, $chronogramAnalysis);
		$data = $this->generateJson($prompt, []);
		if ($data === null) {
			return null;
		}
		$html = (string) ($data['html'] ?? '');
		if ($html === '' && !empty($data['rows'])) {
			$html = $this->fallbackSchemeHtml($data, $dbContext, $programType);
		}
		$title = (string) ($data['title'] ?? ('Scheme of Work — ' . ($module['title'] ?? 'Module')));
		return ['html' => $html, 'json' => $data, 'title' => $title];
	}

	/**
	 * Generate Session Plan (TVET) or Lesson Plan (REB).
	 *
	 * @return array{html:string,json:array,title:string}|null
	 */
	public function generateSessionOrLessonPlan(
		array $module,
		array $schemeJson,
		array $topic,
		array $dbContext,
		string $programType
	): ?array {
		$isLesson = $programType === 'reb';
		$prompt = $this->promptSession($module, $schemeJson, $topic, $dbContext, $isLesson);
		$data = $this->generateJson($prompt, []);
		if ($data === null) {
			return null;
		}
		$html = (string) ($data['html'] ?? '');
		if ($html === '') {
			$html = $this->fallbackSessionHtml($data, $dbContext, $isLesson);
		}
		$kind = $isLesson ? 'Lesson Plan' : 'Session Plan';
		$title = (string) ($data['title'] ?? ($kind . ' — ' . ($topic['topic'] ?? $topic['indicative_content'] ?? 'Topic')));
		return ['html' => $html, 'json' => $data, 'title' => $title];
	}

	/**
	 * Gemini inlineData supports PDF/images — not Word. DOCX/DOC → text only.
	 * Attaching unsupported Word binaries (or invalid UTF-8) caused HTTP 400
	 * "GenerateContentRequest.contents: contents is not specified".
	 *
	 * @param array{path:string,original?:string} $curriculum
	 * @param array{path:string,original?:string}|null $chronogram
	 * @return list<array<string,mixed>>
	 */
	private function buildFileParts(array $curriculum, ?array $chronogram): array
	{
		$parts = [];
		foreach ([['label' => 'CURRICULUM', 'file' => $curriculum], ['label' => 'CHRONOGRAM', 'file' => $chronogram]] as $pack) {
			if (empty($pack['file']['path']) || !is_file($pack['file']['path'])) {
				continue;
			}
			$extracted = DocumentTextExtractor::extract($pack['file']['path']);
			$label = $pack['label'];
			$orig = $pack['file']['original'] ?? basename($pack['file']['path']);
			$ext = strtolower((string) ($extracted['ext'] ?? ''));
			$text = $this->safeUtf8((string) ($extracted['text'] ?? ''));
			$chars = mb_strlen($text, 'UTF-8');

			$parts[] = [
				'text' => "=== {$label} FILE: {$orig} (ext={$ext}, extracted_chars={$chars}) ===\n"
					. ($chars > 0
						? ("EXTRACTED TEXT:\n" . mb_substr($text, 0, 180000, 'UTF-8'))
						: 'EXTRACTED TEXT EMPTY — likely scanned/image PDF. Analyze the attached binary carefully for modules, weeks, and hours.'),
			];

			// Only PDF/images as multimodal attachments (Gemini rejects Word MIME types).
			$attachBinary = in_array($ext, ['pdf', 'png', 'jpg', 'jpeg', 'webp'], true)
				|| strpos((string) ($extracted['mime'] ?? ''), 'image/') === 0
				|| (($extracted['mime'] ?? '') === 'application/pdf');
			$maxBytes = 8 * 1024 * 1024;
			$fileSize = !empty($extracted['bytes']) ? strlen($extracted['bytes']) : 0;
			$needVision = $chars < 1200; // scanned / sparse text — need the PDF/image
			if (
				$attachBinary
				&& $fileSize > 0
				&& $fileSize <= $maxBytes
				&& ($needVision || ($ext === 'pdf' && $fileSize <= 4 * 1024 * 1024))
			) {
				$parts[] = [
					'inlineData' => [
						'mimeType' => $extracted['mime'] ?: DocumentTextExtractor::mimeForExt($ext),
						'data' => base64_encode($extracted['bytes']),
					],
				];
			} elseif (in_array($ext, ['doc', 'docx'], true) && $chars < 40) {
				$parts[] = [
					'text' => "WARNING: {$label} is a Word file with almost no extractable text. Re-upload as PDF if analysis is incomplete.",
				];
			}
		}
		return $parts;
	}

	private function safeUtf8(string $text): string
	{
		if ($text === '') {
			return '';
		}
		if (!mb_check_encoding($text, 'UTF-8')) {
			$converted = @mb_convert_encoding($text, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
			$text = is_string($converted) ? $converted : $text;
		}
		if (function_exists('iconv')) {
			$clean = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
			if (is_string($clean)) {
				$text = $clean;
			}
		}
		return $text;
	}

	private function promptAnalyze(array $ctx): string
	{
		$dbLevels = json_encode($ctx['levels'] ?? [], JSON_UNESCAPED_UNICODE);
		$dbCourses = json_encode($ctx['courses'] ?? [], JSON_UNESCAPED_UNICODE);
		$classMeta = json_encode($ctx['class'] ?? [], JSON_UNESCAPED_UNICODE);
		$school = json_encode($ctx['school'] ?? [], JSON_UNESCAPED_UNICODE);

		return <<<PROMPT
You are an expert Rwanda education curriculum analyst for both TVET/RTB and REB programmes.

PRIMARY GOAL: Extract EVERY course/module from the CURRICULUM with full learning structure (LO + Indicative Contents + hours), then MAP each module onto the CHRONOGRAM (weeks, date ranges, periods/hours) so a Scheme of Work can be generated later.

TASK: Analyze CURRICULUM + CHRONOGRAM (Word text and/or PDF — layouts vary; never assume one template).

EXTRACTION RULES:
1. List ALL modules/courses/subjects found — do not stop at the first module.
2. For each module extract: code, title, RQF/level, total learning hours, credits, module type, sector/trade if present.
3. For TVET/RTB: Learning Outcomes (LO1, LO2…) and nested Indicative Contents (IC1.1, IC1.2…) with hours when available.
4. For REB: subjects, units/topics, periods.
5. From chronogram (often a weekly grid): week number, term, date_from, date_to, module codes, periods/hours per week.
6. When chronogram shows a module across weeks, list those week slots under that module's "chronogram_slots".
7. Normalize RQF to integer 1–5 when possible. Prefer exact DB course code matches.

SCHOOL CONTEXT JSON:
{$school}

CLASS CONTEXT JSON:
{$classMeta}

DB LEVELS JSON:
{$dbLevels}

DB COURSES ASSIGNED TO THIS CLASS (with teachers) JSON:
{$dbCourses}

Return ONLY valid JSON (no markdown fences) with this shape:
{
  "program_type": "tvet" | "reb",
  "qualification_title": string,
  "sector": string,
  "trade": string,
  "detected_rqf_level": number|null,
  "modules": [
    {
      "code": string,
      "title": string,
      "rqf_level": number|null,
      "learning_hours": number|null,
      "credits": number|null,
      "module_type": string,
      "learning_outcomes": [{"code":string,"title":string,"indicative_contents":[{"code":string,"title":string,"hours":number|null}]}],
      "chronogram_slots": [{"week":number,"term":number,"date_from":string,"date_to":string,"periods":number}],
      "matched_course_id": number|null,
      "matched_level_id": number|null,
      "match_confidence": number,
      "match_reason": string
    }
  ],
  "chronogram": {
    "school_year_label": string,
    "weeks": [{"week":number,"term":number,"date_from":string,"date_to":string,"modules":[{"code":string,"periods":number}]}]
  },
  "notes": string
}
PROMPT;
	}

	private function promptScheme(array $module, array $ctx, string $programType, ?array $chrono): string
	{
		$school = $ctx['school'] ?? [];
		$class = $ctx['class'] ?? [];
		$teacher = $module['teacher_name'] ?? ($ctx['teacher_name'] ?? '');
		$modJson = json_encode($module, JSON_UNESCAPED_UNICODE);
		$chronoJson = json_encode($chrono ?? new \stdClass(), JSON_UNESCAPED_UNICODE);
		$schoolJson = json_encode($school, JSON_UNESCAPED_UNICODE);
		$classJson = json_encode($class, JSON_UNESCAPED_UNICODE);
		$kind = $programType === 'reb' ? 'REB Scheme of Work' : 'TVET/RTB Scheme of Work';

		return <<<PROMPT
You are an expert instructional designer for Rwanda {$kind}.

Generate a complete SCHEME OF WORK for ONE module/course that matches this TVET sample layout (Javascript Fundamentals style):

HEADER
- School name (+ district/sector/email/tel if known from school JSON)
- Centered title: SCHEME OF WORK

META TABLE (2-column pairs):
- Sector | Trainer
- Trade | School Year
- Qualification Title | Term (e.g. 1,2,3)
- RQF Level | Module details block:
  Module code and title, Learning hours, Number of Classes, Date, Class Name

WEEKLY BODY (one table per term, or continuous rows grouped by term):
Columns EXACTLY:
Date | Learning Outcome | Indicative Content | Duration (Hours) | Learning Activities | Resources (Equipment, Tools, and Materials) | Evidences of Formative Assessment | Learning Place | Observation

MAPPING RULES (critical):
1. Use MODULE.learning_outcomes and indicative_contents as the source of truth for LO/IC text.
2. Spread each IC across chronogram weeks/date ranges and periods/hours for THIS module code.
3. Prefer MODULE.chronogram_slots and CHRONOGRAM ANALYSIS weeks that mention this module.
4. Duration (Hours) must reflect chronogram periods/hours for that week when available; otherwise use IC hours.
5. Do NOT invent school identity — use SCHOOL/CLASS/TRAINER provided.
6. Activities/resources/assessment/place must be realistic for the subject (lab vs classroom).

Footer: Prepared by (trainer), Verified by (DOS), Approved by (Head teacher).

SCHOOL: {$schoolJson}
CLASS: {$classJson}
TRAINER: {$teacher}
MODULE: {$modJson}
CHRONOGRAM ANALYSIS: {$chronoJson}

Return ONLY valid JSON:
{
  "title": string,
  "html": "full printable HTML document (inline CSS, A4-friendly, matching the sample tables above)",
  "meta": {"sector":"","trade":"","rqf_level":"","school_year":"","terms":"","module_code":"","module_title":"","learning_hours":0,"class_name":"","trainer":"","qualification_title":""},
  "rows": [{"term":1,"week":1,"date":"","lo_code":"","lo_title":"","ic_code":"","ic_title":"","duration":"","activities":"","resources":"","assessment":"","place":"","observation":""}],
  "topics_for_sessions": [{"week":number,"term":number,"date":"","lo_code":"","lo_title":"","ic_code":"","ic_title":"","topic":"","duration":""}]
}
PROMPT;
	}

	private function promptSession(array $module, array $schemeJson, array $topic, array $ctx, bool $isLesson): string
	{
		$label = $isLesson ? 'LESSON PLAN (REB)' : 'SESSION PLAN (TVET/RTB)';
		$schoolJson = json_encode($ctx['school'] ?? [], JSON_UNESCAPED_UNICODE);
		$classJson = json_encode($ctx['class'] ?? [], JSON_UNESCAPED_UNICODE);
		$modJson = json_encode($module, JSON_UNESCAPED_UNICODE);
		$topicJson = json_encode($topic, JSON_UNESCAPED_UNICODE);
		$schemeBrief = json_encode([
			'meta' => $schemeJson['meta'] ?? [],
			'rows' => array_slice($schemeJson['rows'] ?? [], 0, 40),
			'topics_for_sessions' => $schemeJson['topics_for_sessions'] ?? [],
		], JSON_UNESCAPED_UNICODE);
		$teacher = $module['teacher_name'] ?? ($schemeJson['meta']['trainer'] ?? '');

		if ($isLesson) {
			$sampleHint = "Include: subject, class, topic, objectives, teaching aids, introduction, presentation/development steps, conclusion, assessment, homework, references.";
		} else {
			$sampleHint = <<<SAMPLE
Match this TVET SESSION PLAN sample (C Programming style) exactly in structure:

HEADER title: SESSION PLAN
Meta grid:
- Sector | Trade | Level | Date
- Trainer name | School year | Term
- Module (Code&Name) | Week | No. Trainees | Class(es)
Then:
- Learning Outcome (from Scheme of Work LO)
- Indicative content (from Scheme of Work IC)
- Topic of the session (weekly activity topic)
- Range | Duration of the session
- Objectives (numbered)
- Facilitation technique(s)
- Introduction table: Trainer's activity | Learner's activity | Resources | Duration
- Development/Body: Step 1..N with Trainer's activity + Learner's activity + Resources + Duration
- Conclusion / Summary
- Assessment/Assignment
- Evaluation of the session
- References | Appendices | Reflection

This is a WEEKLY activity plan for ONE selected topic/week from the Scheme of Work.
SAMPLE;
		}

		return <<<PROMPT
You are an expert Rwanda trainer preparing a {$label}.

The plan MUST be based on the already prepared Scheme of Work for this module and the teacher-selected topic/week (aligned to chronogram).

{$sampleHint}

SCHOOL: {$schoolJson}
CLASS: {$classJson}
TRAINER: {$teacher}
MODULE: {$modJson}
SELECTED TOPIC / WEEK: {$topicJson}
SCHEME OF WORK SUMMARY: {$schemeBrief}

Return ONLY valid JSON:
{
  "title": string,
  "html": "full printable HTML (inline CSS) matching the SESSION PLAN sample layout",
  "meta": {"week":0,"term":0,"topic":"","duration":"","learning_outcome":"","indicative_content":"","sector":"","trade":"","level":"","class_name":"","trainer":"","school_year":""},
  "objectives": [string],
  "facilitation_techniques": [string],
  "sections": {"introduction":{"trainer":"","learner":"","resources":"","duration":""},"development":[{"step":1,"title":"","trainer":"","learner":"","resources":"","duration":""}],"conclusion":"","assessment":"","evaluation":"","references":"","appendices":"","reflection":""}
}
PROMPT;
	}

	/**
	 * @param list<array<string,mixed>> $extraParts
	 */
	private function generateJson(string $prompt, array $extraParts, int $maxTokens = 8192): ?array
	{
		$this->lastError = '';
		if (!$this->isConfigured()) {
			$this->lastError = 'Gemini API key not configured (GOOGLE_AI_API_KEY)';
			return null;
		}
		$prompt = $this->safeUtf8($prompt);
		$parts = array_merge([['text' => $prompt]], $extraParts);
		if ($parts === []) {
			$this->lastError = 'Gemini request has no content parts';
			return null;
		}
		$payload = [
			'contents' => [[
				'role' => 'user',
				'parts' => $parts,
			]],
			'generationConfig' => [
				'temperature' => 0.2,
				'maxOutputTokens' => max(2048, $maxTokens),
				'responseMimeType' => 'application/json',
				// Disable thinking on 2.5 Flash so we get JSON, not prose/thoughts
				'thinkingConfig' => [
					'thinkingBudget' => 0,
				],
			],
		];

		$lastErr = '';
		foreach ($this->textModels() as $model) {
			try {
				$raw = $this->request($model, $payload);
				$text = $this->extractText($raw);
				$json = $this->parseJson($text);
				if (is_array($json)) {
					return $json;
				}
				$finish = (string) ($raw['candidates'][0]['finishReason'] ?? '');
				$lastErr = 'Model ' . $model . ' returned non-JSON'
					. ($finish !== '' ? (' (finish=' . $finish . ')') : '')
					. ($text !== '' ? ('; preview=' . substr(preg_replace('/\s+/', ' ', $text) ?? '', 0, 120)) : '');
			} catch (\Throwable $e) {
				$lastErr = $e->getMessage();
				if (stripos($lastErr, 'NOT_FOUND') !== false || stripos($lastErr, 'is not found') !== false || stripos($lastErr, 'HTTP 404') !== false) {
					continue;
				}
				// Retry without thinkingConfig / responseMimeType
				try {
					$payload2 = $payload;
					unset($payload2['generationConfig']['thinkingConfig'], $payload2['generationConfig']['responseMimeType']);
					$raw = $this->request($model, $payload2);
					$text = $this->extractText($raw);
					$json = $this->parseJson($text);
					if (is_array($json)) {
						return $json;
					}
					$lastErr = 'Model ' . $model . ' returned non-JSON (retry)';
				} catch (\Throwable $e2) {
					$msg = $e2->getMessage();
					// If thinkingConfig unknown, retry once without it but keep JSON mime
					if (stripos($msg, 'thinking') !== false || stripos($msg, 'Unknown name') !== false) {
						try {
							$payload3 = $payload;
							unset($payload3['generationConfig']['thinkingConfig']);
							$raw = $this->request($model, $payload3);
							$text = $this->extractText($raw);
							$json = $this->parseJson($text);
							if (is_array($json)) {
								return $json;
							}
						} catch (\Throwable $e3) {
							$lastErr = $e3->getMessage();
						}
					} else {
						$lastErr = $msg;
					}
				}
			}
		}
		$this->lastError = $lastErr ?: 'Gemini generation failed';
		return null;
	}

	private function request(string $model, array $payload): array
	{
		$key = $this->apiKey();
		$url = 'https://generativelanguage.googleapis.com/v1beta/models/'
			. rawurlencode($model) . ':generateContent';

		$flags = JSON_UNESCAPED_UNICODE;
		if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
			$flags |= JSON_INVALID_UTF8_SUBSTITUTE;
		}
		$body = json_encode($payload, $flags);
		if ($body === false || $body === '' || $body === 'null') {
			throw new \RuntimeException('Failed to encode Gemini payload: ' . json_last_error_msg());
		}
		// Guard: empty contents would trigger Google's "contents is not specified"
		if (strpos($body, '"contents"') === false) {
			throw new \RuntimeException('Gemini payload missing contents after JSON encode');
		}

		$ch = curl_init($url);
		curl_setopt_array($ch, [
			CURLOPT_POST => true,
			CURLOPT_HTTPHEADER => [
				'Content-Type: application/json; charset=utf-8',
				'x-goog-api-key: ' . $key,
			],
			CURLOPT_POSTFIELDS => $body,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 300,
			CURLOPT_CONNECTTIMEOUT => 30,
		]);
		$raw = curl_exec($ch);
		$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$err = curl_error($ch);
		curl_close($ch);
		if ($raw === false) {
			throw new \RuntimeException('curl failed: ' . $err);
		}
		if ($code >= 400) {
			$msg = substr(preg_replace('/\s+/', ' ', $raw) ?? $raw, 0, 400);
			throw new \RuntimeException("HTTP {$code}: {$msg}");
		}
		$data = json_decode($raw, true);
		if (!is_array($data)) {
			throw new \RuntimeException('Invalid JSON from Gemini');
		}
		if (!empty($data['error']['message'])) {
			throw new \RuntimeException((string) $data['error']['message']);
		}
		return $data;
	}

	private function extractText(array $data): string
	{
		$parts = $data['candidates'][0]['content']['parts'] ?? [];
		$buf = '';
		foreach ($parts as $p) {
			if (!is_array($p)) {
				continue;
			}
			// Skip thought/signature-only parts from thinking models
			if (!empty($p['thought']) || !empty($p['thoughtSignature']) || !empty($p['thought_signature'])) {
				continue;
			}
			if (isset($p['text']) && is_string($p['text']) && $p['text'] !== '') {
				$buf .= $p['text'];
			}
		}
		// Fallback: any text part if all were tagged thought
		if (trim($buf) === '') {
			foreach ($parts as $p) {
				if (!empty($p['text']) && is_string($p['text'])) {
					$buf .= $p['text'];
				}
			}
		}
		return trim($buf);
	}

	private function parseJson(string $text): ?array
	{
		$text = trim($text);
		if ($text === '') {
			return null;
		}
		// Strip BOM / fences
		$text = preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? $text;
		if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $text, $m)) {
			$text = trim($m[1]);
		}
		$decoded = json_decode($text, true);
		if (is_array($decoded)) {
			return $decoded;
		}
		$start = strpos($text, '{');
		$end = strrpos($text, '}');
		if ($start !== false && $end !== false && $end > $start) {
			$slice = substr($text, $start, $end - $start + 1);
			$decoded = json_decode($slice, true);
			if (is_array($decoded)) {
				return $decoded;
			}
			// Attempt light repair of truncated JSON (common when MAX_TOKENS)
			$repaired = $this->repairTruncatedJson($slice);
			if ($repaired !== null) {
				return $repaired;
			}
		}
		return null;
	}

	private function repairTruncatedJson(string $slice): ?array
	{
		$s = rtrim($slice);
		// Close open strings/arrays/objects roughly
		$opens = substr_count($s, '{') + substr_count($s, '[');
		$closes = substr_count($s, '}') + substr_count($s, ']');
		// Trim to last complete-looking property
		if (preg_match('/,\s*"[^"]*$/', $s)) {
			$s = preg_replace('/,\s*"[^"]*$/', '', $s) ?? $s;
		}
		if (substr($s, -1) === ',') {
			$s = substr($s, 0, -1);
		}
		while ($closes < $opens) {
			// Prefer closing object if last open was {
			$s .= '}';
			$closes++;
		}
		$decoded = json_decode($s, true);
		return is_array($decoded) ? $decoded : null;
	}

	private function matchToDb(array $analysis, array $ctx): array
	{
		$courses = $ctx['courses'] ?? [];
		$levels = $ctx['levels'] ?? [];
		$modules = $analysis['modules'] ?? [];
		if (!is_array($modules)) {
			$modules = [];
		}

		foreach ($modules as &$mod) {
			$mod['code'] = DocumentTextExtractor::cleanModuleCode((string) ($mod['code'] ?? ''));
			$mod['title'] = DocumentTextExtractor::cleanModuleTitle((string) ($mod['title'] ?? ''));
			$code = strtoupper(trim((string) ($mod['code'] ?? '')));
			$title = strtolower(trim((string) ($mod['title'] ?? '')));
			$rqf = $mod['rqf_level'] ?? $analysis['detected_rqf_level'] ?? null;
			$best = null;
			$bestScore = 0.0;
			foreach ($courses as $c) {
				$cCode = DocumentTextExtractor::cleanModuleCode((string) ($c['code'] ?? ''));
				$cTitle = strtolower(trim((string) ($c['title'] ?? '')));
				$score = 0.0;
				if ($code !== '' && $cCode !== '' && ($code === $cCode || $this->moduleCodesCompatible($code, $cCode))) {
					$score += 0.75;
				} elseif ($code !== '' && $cCode !== '' && (strpos($cCode, $code) !== false || strpos($code, $cCode) !== false)) {
					$score += 0.55;
				}
				similar_text($title, $cTitle, $pct);
				$score += ($pct / 100) * 0.5;
				if ($score > $bestScore) {
					$bestScore = $score;
					$best = $c;
				}
			}
			if ($best && $bestScore >= 0.35) {
				$mod['matched_course_id'] = (int) $best['id'];
				$mod['matched_course_title'] = $best['title'] ?? '';
				$mod['teacher_id'] = $best['lecturer_id'] ?? null;
				$mod['teacher_name'] = $best['mentor_name'] ?? ($best['teacher_name'] ?? '');
				$mod['match_confidence'] = round($bestScore, 2);
				$mod['match_reason'] = 'Matched to DB course by code/title similarity';
			} else {
				$mod['matched_course_id'] = $mod['matched_course_id'] ?? null;
				$mod['teacher_name'] = $mod['teacher_name'] ?? '';
				$mod['match_confidence'] = $mod['match_confidence'] ?? 0;
			}

			$mod['matched_level_id'] = null;
			$mod['matched_level_title'] = '';
			if ($rqf !== null && $rqf !== '') {
				foreach ($levels as $lv) {
					$lt = (string) ($lv['title'] ?? '');
					if (preg_match('/\b' . preg_quote((string) (int) $rqf, '/') . '\b/', $lt)
						|| stripos($lt, 'level ' . (int) $rqf) !== false
						|| stripos($lt, 'l' . (int) $rqf) !== false) {
						$mod['matched_level_id'] = (int) $lv['id'];
						$mod['matched_level_title'] = $lt;
						break;
					}
				}
			}
			// Prefer class level when RQF matches class
			if (!empty($ctx['class']['level_id']) && empty($mod['matched_level_id'])) {
				$mod['matched_level_id'] = (int) $ctx['class']['level_id'];
				$mod['matched_level_title'] = (string) ($ctx['class']['level_name'] ?? '');
			}
		}
		unset($mod);
		$analysis['modules'] = $modules;
		if (empty($analysis['program_type'])) {
			$ftype = (int) ($ctx['class']['faculty_type'] ?? 1);
			$analysis['program_type'] = $ftype === 2 ? 'reb' : 'tvet';
		}
		return $analysis;
	}

	private function fallbackSchemeHtml(array $data, array $ctx, string $programType): string
	{
		$school = esc($ctx['school']['name'] ?? 'School');
		$meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];
		$title = esc($data['title'] ?? 'SCHEME OF WORK');
		$trainer = esc($meta['trainer'] ?? '');
		$sector = esc($meta['sector'] ?? '');
		$trade = esc($meta['trade'] ?? '');
		$year = esc($meta['school_year'] ?? '');
		$qual = esc($meta['qualification_title'] ?? '');
		$terms = esc($meta['terms'] ?? '');
		$rqf = esc((string) ($meta['rqf_level'] ?? ''));
		$modCode = esc($meta['module_code'] ?? '');
		$modTitle = esc($meta['module_title'] ?? '');
		$hours = esc((string) ($meta['learning_hours'] ?? ''));
		$className = esc($meta['class_name'] ?? ($ctx['class']['name'] ?? ''));

		$rows = '';
		foreach ($data['rows'] ?? [] as $r) {
			$rows .= '<tr>'
				. '<td>' . esc($r['date'] ?? '') . '</td>'
				. '<td>' . esc(trim(($r['lo_code'] ?? '') . ' ' . ($r['lo_title'] ?? ''))) . '</td>'
				. '<td>' . esc(trim(($r['ic_code'] ?? '') . ' ' . ($r['ic_title'] ?? ''))) . '</td>'
				. '<td>' . esc($r['duration'] ?? '') . '</td>'
				. '<td>' . esc($r['activities'] ?? '') . '</td>'
				. '<td>' . esc($r['resources'] ?? '') . '</td>'
				. '<td>' . esc($r['assessment'] ?? '') . '</td>'
				. '<td>' . esc($r['place'] ?? '') . '</td>'
				. '<td>' . esc($r['observation'] ?? '') . '</td>'
				. '</tr>';
		}

		return "<html><head><meta charset='utf-8'><style>
body{font-family:DejaVu Sans,Arial,sans-serif;font-size:11px;color:#111}
h1{text-align:center;font-size:18px;margin:12px 0}
.meta,.body{width:100%;border-collapse:collapse;margin-bottom:14px}
.meta td,.body td,.body th{border:1px solid #333;padding:5px;vertical-align:top}
.body th{background:#f3f3f3}
.school{text-align:center;font-weight:700}
.foot{margin-top:28px;display:flex;justify-content:space-between}
</style></head><body>
<p class='school'>{$school}</p>
<h1>{$title}</h1>
<table class='meta'>
<tr><td><b>Sector:</b></td><td>{$sector}</td><td><b>Trainer:</b></td><td>{$trainer}</td></tr>
<tr><td><b>Trade:</b></td><td>{$trade}</td><td><b>School Year:</b></td><td>{$year}</td></tr>
<tr><td><b>Qualification Title:</b></td><td>{$qual}</td><td><b>Term:</b></td><td>{$terms}</td></tr>
<tr><td><b>RQF Level:</b></td><td>{$rqf}</td><td><b>Module code and title</b></td><td>{$modCode} {$modTitle}</td></tr>
<tr><td><b>Learning hours:</b></td><td>{$hours}</td><td><b>Class Name:</b></td><td>{$className}</td></tr>
</table>
<table class='body'>
<thead><tr>
<th>Date</th><th>Learning Outcome</th><th>Indicative Content</th><th>Duration (Hours)</th>
<th>Learning Activities</th><th>Resources (Equipment, Tools, and Materials)</th>
<th>Evidences of Formative Assessment</th><th>Learning Place</th><th>Observation</th>
</tr></thead>
<tbody>{$rows}</tbody>
</table>
<div class='foot'><div>Prepared by: {$trainer}</div><div>Verified by (DOS): ________</div><div>Approved by (Head teacher): ________</div></div>
</body></html>";
	}

	private function fallbackSessionHtml(array $data, array $ctx, bool $isLesson): string
	{
		$kind = $isLesson ? 'Lesson Plan' : 'Session Plan';
		$title = esc($data['title'] ?? $kind);
		$school = esc($ctx['school']['name'] ?? '');
		$topic = esc($data['meta']['topic'] ?? '');
		$objs = '';
		foreach ($data['objectives'] ?? [] as $o) {
			$objs .= '<li>' . esc($o) . '</li>';
		}
		return "<html><head><meta charset='utf-8'><style>body{font-family:DejaVu Sans,Arial,sans-serif;font-size:12px}h1{text-align:center}</style></head><body><h1>{$title}</h1><p>{$school}</p><p><b>Topic:</b> {$topic}</p><ol>{$objs}</ol></body></html>";
	}
}
