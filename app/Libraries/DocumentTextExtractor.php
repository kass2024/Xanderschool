<?php

namespace App\Libraries;

/**
 * Extract readable text from PDF / DOC / DOCX pedagogical uploads.
 * Image-only PDFs return little text — Gemini multimodal should receive the raw file.
 */
class DocumentTextExtractor
{
	/**
	 * @return array{text:string,mime:string,bytes:?string,chars:int,ext:string}
	 */
	public static function extract(string $absolutePath): array
	{
		$abs = $absolutePath;
		$ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
		$bytes = is_file($abs) ? (string) @file_get_contents($abs) : '';
		$mime = self::mimeForExt($ext);
		$text = '';

		if ($bytes === '') {
			return ['text' => '', 'mime' => $mime, 'bytes' => null, 'chars' => 0, 'ext' => $ext];
		}

		if ($ext === 'docx') {
			$text = self::extractDocx($abs);
		} elseif ($ext === 'doc') {
			$text = self::extractDocBinary($bytes);
		} elseif ($ext === 'pdf') {
			$text = self::extractPdf($bytes);
			// Prefer pdftotext when available (much better for RTB curricula)
			$cli = self::extractPdfCli($abs);
			if (mb_strlen($cli, 'UTF-8') > mb_strlen($text, 'UTF-8')) {
				$text = $cli;
			}
		}

		$text = self::normalize($text);
		return [
			'text' => $text,
			'mime' => $mime,
			'bytes' => $bytes,
			'chars' => mb_strlen($text, 'UTF-8'),
			'ext' => $ext,
		];
	}

	/**
	 * Strip PDF language tags (en-ZA, en-US…) and tidy pedagogical text.
	 */
	public static function cleanPedagogicalText(string $text): string
	{
		if ($text === '') {
			return '';
		}
		// Tagged-PDF locale markers (often glued: en-ZADevelop, en-US3, en-ZACCM…)
		$text = preg_replace('/[a-z]{2}-[A-Z]{2}/u', ' ', $text) ?? $text;
		$text = preg_replace('/[a-z]{2}_[A-Z]{2}/u', ' ', $text) ?? $text;
		$text = preg_replace('/\b(?:en|fr|rw|sw)(?=(?:CCM|SWD|GEN|ICT))/iu', '', $text) ?? $text;
		$text = preg_replace('/\bZA(?=CCM)/u', '', $text) ?? $text;
		$text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
		$text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
		$text = preg_replace('/\s{2,}/', ' ', $text) ?? $text;
		return trim($text);
	}

	/** Normalize a module/course code extracted from messy PDF text. */
	public static function cleanModuleCode(string $code): string
	{
		$code = strtoupper(trim($code));
		$code = preg_replace('/[A-Z]{2}-[A-Z]{2}/', '', $code) ?? $code;
		$code = preg_replace('/[^A-Z0-9]/', '', $code) ?? $code;
		$code = preg_replace('/^(?:EN|FR|RW|SW)?ZA(?=CCM)/', '', $code) ?? $code;
		$code = preg_replace('/^(?:EN|FR|RW|SW)(?=(?:CCM|SWD|GEN|ICT))/', '', $code) ?? $code;
		if (preg_match('/((?:SWD|GEN|CCM|ICT)[A-Z]{0,6}\d{3})/', $code, $m)) {
			return $m[1];
		}
		if (preg_match('/([A-Z]{3,8}\d{3})/', $code, $m)) {
			return $m[1];
		}
		return $code;
	}

	public static function cleanModuleTitle(string $title): string
	{
		$title = self::cleanPedagogicalText($title);
		// Drop standalone module codes that leaked into the title
		$title = preg_replace('/\b(?:SWD|GEN|CCM|ICT)[A-Z]{0,6}\d{3}\b/', ' ', $title) ?? $title;
		$title = preg_replace('/\b\d+(?:\.\d+)?\b/', ' ', $title) ?? $title;
		$title = preg_replace('/\s+/', ' ', $title) ?? $title;
		$title = trim($title, " \t\n\r\0\x0B-–—.");
		return $title;
	}

	/**
	 * Extract module code from RTB curriculum filenames, e.g.
	 * "SWDDD401 - DATABASE DEVELOPMENT.pdf", "CCMEN 402  - ENGLISH.pdf", "CMCZ401 - CITIZENSHIP.pdf"
	 */
	public static function extractModuleCodeFromFilename(string $filename): string
	{
		$base = pathinfo(str_replace('\\', '/', $filename), PATHINFO_FILENAME);
		$base = strtoupper(trim($base));
		$base = preg_replace('/\s+/', ' ', $base) ?? $base;
		// Skip structure / qualification overview PDFs
		if (stripos($base, 'GENERAL INFORMATION') !== false || stripos($base, 'CURRICULUM GENERAL') !== false) {
			return '';
		}
		// Typo: CMCZ401 → CCMCZ401
		if (preg_match('/^CM([A-Z]{2}\s*\d{3})\b/', $base, $m)) {
			return self::cleanModuleCode('CCM' . preg_replace('/\s+/', '', $m[1]));
		}
		// Prefer CODE at start: SWDDD401 - TITLE / CCMEN 402 - ENGLISH
		if (preg_match('/^((?:SWD|GEN|CCM)[A-Z]{0,6})\s*[-_]?\s*(\d{3})\b/', $base, $m)) {
			return self::cleanModuleCode($m[1] . $m[2]);
		}
		if (preg_match('/\b((?:SWD|GEN|CCM)[A-Z]{0,6})\s*[-_]?\s*(\d{3})\b/', $base, $m)) {
			$code = self::cleanModuleCode($m[1] . $m[2]);
			// Reject qualification-style codes (ICTSWD400…)
			if (preg_match('/^ICT/', $code)) {
				return '';
			}
			return $code;
		}
		return '';
	}

	/** Human title from "CODE - TITLE.pdf" filenames. */
	public static function extractModuleTitleFromFilename(string $filename): string
	{
		$base = pathinfo(str_replace('\\', '/', $filename), PATHINFO_FILENAME);
		$title = preg_replace('/^(?:CM|SWD|GEN|CCM|ICT)[A-Z]{0,6}\s*[-_]?\s*\d{3}\s*[-–—_]?\s*/i', '', $base) ?? $base;
		$title = preg_replace('/\s*correct\s*$/i', '', $title) ?? $title;
		$title = preg_replace('/\s+/', ' ', $title) ?? $title;
		$title = trim($title, " \t\n\r\0\x0B-–—_.");
		$clean = self::cleanModuleTitle($title);
		return $clean !== '' ? $clean : $title;
	}

	/**
	 * Guess module_type from folder/filename (Specific / General / CCM / structure).
	 */
	public static function guessModuleTypeFromPath(string $pathOrName): string
	{
		$n = strtolower(str_replace('\\', '/', $pathOrName));
		if (strpos($n, 'general information') !== false || strpos($n, 'curriculum general') !== false
			|| (strpos($n, 'structure') !== false && strpos($n, 'module') === false)) {
			return 'structure';
		}
		if (strpos($n, 'complementary') !== false || strpos($n, 'ccm module') !== false
			|| preg_match('#/(ccm|ccms)(/|$)#', $n) || preg_match('/\bccm[a-z]{0,6}\s*\d{3}\b/', $n)) {
			return 'ccm';
		}
		if (strpos($n, 'specific') !== false || preg_match('/\bswd[a-z]{0,6}\s*\d{3}\b/', $n)) {
			return 'specific';
		}
		if (strpos($n, 'general module') !== false || preg_match('/\bgen[a-z]{0,6}\s*\d{3}\b/', $n)) {
			return 'general';
		}
		return '';
	}

	/** Sort key: structure/general-info first, then specific, general, ccm. */
	public static function curriculumFileSortKey(string $pathOrName): int
	{
		$type = self::guessModuleTypeFromPath($pathOrName);
		$map = ['structure' => 0, 'specific' => 1, 'general' => 2, 'ccm' => 3];
		return $map[$type] ?? 5;
	}

	/** Normalize curriculum credit (supports decimals like 1.5). */
	public static function normalizeCreditValue($value): float
	{
		if ($value === null || $value === '') {
			return 0.0;
		}
		if (is_string($value)) {
			$value = str_replace(',', '.', trim($value));
		}
		if (!is_numeric($value)) {
			return 0.0;
		}
		$credit = round((float) $value, 1);
		if ($credit < 0.5 || $credit > 40) {
			return 0.0;
		}
		return $credit;
	}

	/**
	 * Parse module credits from RTB curriculum structure (competences tables).
	 *
	 * @return array<string,float> code => credit
	 */
	public static function parseCurriculumModuleCredits(string $curText): array
	{
		if (trim($curText) === '') {
			return [];
		}
		$rawLines = preg_split("/\r\n|\n|\r/", $curText) ?: [];
		$out = [];
		$inSection = false;
		$pending = '';

		foreach ($rawLines as $rawLine) {
			$line = trim(self::cleanPedagogicalText($rawLine));
			if ($line === '') {
				continue;
			}
			if (preg_match('/information\s+about\s+competenc/i', $line)) {
				$inSection = true;
				$pending = '';
				continue;
			}
			if ($inSection && preg_match('/allocation\s+of\s+learning\s+hours/i', $line)) {
				break;
			}
			if (!$inSection) {
				continue;
			}
			if (preg_match('/\b((?:SWD|GEN|CCM|ICT)[A-Z]{0,6}\d{3})\b/i', $line)) {
				$pending = $pending !== '' ? ($pending . ' ' . $line) : $line;
			} elseif ($pending !== '') {
				$pending .= ' ' . $line;
			} else {
				continue;
			}
			$parsed = self::parseCreditFromLine($pending);
			if ($parsed !== null) {
				$out[$parsed['code']] = $parsed['credit'];
				$pending = '';
			}
		}

		// Fallback when section header missing in PDF extract
		if ($out === []) {
			foreach ($rawLines as $rawLine) {
				$line = trim(self::cleanPedagogicalText($rawLine));
				if ($line === '') {
					continue;
				}
				if (preg_match('/^(?:total|no\b|code\b|credit\b|general\b|specific\b)/i', $line)) {
					continue;
				}
				$parsed = self::parseCreditFromLine($line);
				if ($parsed !== null) {
					$out[$parsed['code']] = $parsed['credit'];
				}
			}
		}

		return $out;
	}

	/** @return array{code:string,credit:float}|null */
	private static function parseCreditFromLine(string $line): ?array
	{
		if (!preg_match('/\b((?:SWD|GEN|CCM|ICT)[A-Z]{0,6}\d{3})\b/i', $line, $cm)) {
			return null;
		}
		$code = self::cleanModuleCode($cm[1]);
		if ($code === '') {
			return null;
		}
		$afterCode = preg_replace('/^.*?\b' . preg_quote($code, '/') . '\b/i', '', $line) ?? '';
		$afterCode = trim($afterCode);
		if ($afterCode === '' || !preg_match('/(\d+(?:\.\d+)?)\s*$/', $afterCode, $credM)) {
			return null;
		}
		$credit = self::normalizeCreditValue($credM[1]);
		if ($credit <= 0) {
			return null;
		}
		return ['code' => $code, 'credit' => $credit];
	}

	/**
	 * Parse RTB chronogram text for timetable hours/week (typical weekly periods).
	 * Uses week-grid cells when present; else Modules periods ÷ teaching weeks.
	 *
	 * @return array<string,array{hours_per_week:float,period_total:float,samples:int}>
	 */
	public static function parseChronogramWeeklyHours(string $chrText): array
	{
		if (trim($chrText) === '') {
			return [];
		}
		$text = self::cleanPedagogicalText($chrText);
		$upper = strtoupper($text);
		$cut = stripos($upper, 'MODULES HOURS');
		if ($cut === false) {
			$cut = stripos($upper, 'MODULES PERIODS');
		}
		$headerChunk = $cut !== false ? substr($text, 0, (int) $cut) : $text;
		$ordered = [];
		$seen = [];
		if (preg_match_all('/\b((?:SWD|GEN|CCM|ICT)[A-Z]{0,6}\d{3})\b/', strtoupper($headerChunk), $cm)) {
			foreach ($cm[1] as $c) {
				$c = self::cleanModuleCode($c);
				if ($c === '' || isset($seen[$c])) {
					continue;
				}
				$seen[$c] = true;
				$ordered[] = $c;
			}
		}
		if ($ordered === []) {
			if (preg_match_all('/\b((?:SWD|GEN|CCM|ICT)[A-Z]{0,6}\d{3})\b/', strtoupper($text), $cm2)) {
				foreach ($cm2[1] as $c) {
					$c = self::cleanModuleCode($c);
					if ($c === '' || isset($seen[$c])) {
						continue;
					}
					$seen[$c] = true;
					$ordered[] = $c;
				}
			}
		}
		if ($ordered === []) {
			return [];
		}

		$n = count($ordered);
		$samples = [];
		foreach ($ordered as $c) {
			$samples[$c] = [];
		}

		$lines = preg_split("/\r\n|\n|\r/", $chrText) ?: [];
		foreach ($lines as $line) {
			$line = trim($line);
			if (!preg_match('/^(\d{1,2})\s+(\d{2}\/\d{2}-\d{2}\/\d{2}\/\d{4})\s+(.+)$/', $line, $wm)) {
				continue;
			}
			$rest = trim($wm[3]);
			$parts = preg_split('/\s+/', $rest) ?: [];
			$nums = [];
			foreach ($parts as $p) {
				if (preg_match('/^\d{1,2}$/', $p)) {
					$nums[] = (int) $p;
				}
			}
			if (count($nums) < 3) {
				continue;
			}
			// Drop trailing "Total Periods/Week" (commonly 40–55)
			$last = $nums[count($nums) - 1];
			if ($last >= 40 && $last <= 60) {
				array_pop($nums);
			}
			$cols = array_slice($nums, 0, $n);
			foreach ($cols as $i => $v) {
				if ($v > 0 && $v <= 20 && isset($ordered[$i])) {
					$samples[$ordered[$i]][] = (float) $v;
				}
			}
		}

		$periodTotals = [];
		if (preg_match('/Modules\s+periods\s+([0-9\s]+)/i', $text, $pm)) {
			$pnums = array_values(array_filter(array_map('floatval', preg_split('/\s+/', trim($pm[1])) ?: []), static function ($x) {
				return $x > 0 && $x <= 500;
			}));
			for ($i = 0; $i < min($n, count($pnums)); $i++) {
				$periodTotals[$ordered[$i]] = $pnums[$i];
			}
		}

		$teachingWeeks = 0;
		foreach ($samples as $vals) {
			$teachingWeeks = max($teachingWeeks, count($vals));
		}
		if ($teachingWeeks < 8) {
			$teachingWeeks = 30;
		}

		$out = [];
		foreach ($ordered as $code) {
			$vals = $samples[$code] ?? [];
			$periodTotal = (float) ($periodTotals[$code] ?? 0);
			$hpw = 0.0;
			if ($vals !== []) {
				$hpw = self::modeFloat($vals);
			} elseif ($periodTotal > 0) {
				$hpw = round($periodTotal / $teachingWeeks, 1);
			}
			if ($hpw <= 0) {
				continue;
			}
			$out[$code] = [
				'hours_per_week' => round($hpw, 1),
				'period_total' => $periodTotal,
				'samples' => count($vals),
			];
		}
		return $out;
	}

	/** @param list<float|int> $vals */
	private static function modeFloat(array $vals): float
	{
		if ($vals === []) {
			return 0.0;
		}
		$counts = [];
		foreach ($vals as $v) {
			$key = (string) round((float) $v, 1);
			if (!isset($counts[$key])) {
				$counts[$key] = 0;
			}
			$counts[$key]++;
		}
		arsort($counts);
		reset($counts);
		$top = key($counts);
		if ($top === null || $top === '') {
			return round(array_sum($vals) / count($vals), 1);
		}
		return (float) $top;
	}

	/**
	 * Best-effort module titles from chronogram header (names above codes).
	 *
	 * @return array<string,string> code => title
	 */
	public static function parseChronogramModuleTitles(string $chrText): array
	{
		if (trim($chrText) === '') {
			return [];
		}
		$upper = strtoupper($chrText);
		$cut = stripos($upper, 'MODULES HOURS');
		if ($cut === false) {
			$cut = stripos($upper, 'MODULES PERIODS');
		}
		$header = $cut !== false ? substr($chrText, 0, (int) $cut) : $chrText;
		$lines = preg_split("/\r\n|\n|\r/", $header) ?: [];
		$codes = [];
		$codeLineIdx = null;
		foreach ($lines as $i => $line) {
			$line = trim($line);
			if (preg_match('/^((?:SWD|GEN|CCM|ICT)[A-Z]{0,6}\d{3})$/i', $line, $m)) {
				if ($codeLineIdx === null) {
					$codeLineIdx = $i;
				}
				$c = self::cleanModuleCode($m[1]);
				if ($c !== '' && !in_array($c, $codes, true)) {
					$codes[] = $c;
				}
			}
		}
		if ($codes === [] || $codeLineIdx === null) {
			return [];
		}
		$rawTitles = [];
		for ($i = 0; $i < $codeLineIdx; $i++) {
			$t = trim($lines[$i]);
			if ($t === '') {
				continue;
			}
			if (preg_match('/^(Republic|Ministry|TRAINING|SECTOR|TRADE|RQF|QUALIFICATION|SCHOOL|CORE|SPECIFIC|COMPLEMENTARY|HOLIDAYS|PERIODS|FIRST|SECOND|THIRD)/i', $t)) {
				continue;
			}
			if (preg_match('/^(?:SWD|GEN|CCM|ICT)[A-Z]{0,6}\d{3}$/i', $t)) {
				continue;
			}
			$rawTitles[] = preg_replace('/\s+/', ' ', $t) ?? $t;
		}
		if ($rawTitles === []) {
			return [];
		}
		// Merge wrapped title lines into ~count(codes) phrases
		$merged = [];
		$buf = '';
		foreach ($rawTitles as $t) {
			$startsNew = preg_match('/^(Develop|Apply|Design|Perform|Use|Integrate|Gukoresha|Exprime|Citiz)/i', $t);
			if ($buf !== '' && $startsNew && count($merged) < count($codes) - 1) {
				$merged[] = trim($buf);
				$buf = $t;
			} else {
				$buf = $buf === '' ? $t : ($buf . ' ' . $t);
			}
		}
		if ($buf !== '') {
			$merged[] = trim($buf);
		}
		$out = [];
		$n = min(count($codes), count($merged));
		for ($i = 0; $i < $n; $i++) {
			$title = self::cleanModuleTitle($merged[$i]);
			if ($title === '') {
				$title = trim($merged[$i]);
			}
			if ($title !== '' && strcasecmp($title, $codes[$i]) !== 0) {
				$out[$codes[$i]] = $title;
			}
		}
		return $out;
	}

	public static function mimeForExt(string $ext): string
	{
		$map = [
			'pdf' => 'application/pdf',
			'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'doc' => 'application/msword',
			'png' => 'image/png',
			'jpg' => 'image/jpeg',
			'jpeg' => 'image/jpeg',
		];
		return $map[$ext] ?? 'application/octet-stream';
	}

	private static function normalize(string $text): string
	{
		if ($text !== '' && !mb_check_encoding($text, 'UTF-8')) {
			$converted = @mb_convert_encoding($text, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
			$text = is_string($converted) ? $converted : utf8_encode($text);
		}
		// Strip leftover invalid bytes so json_encode never fails on Gemini payloads
		if (function_exists('iconv')) {
			$clean = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
			if (is_string($clean)) {
				$text = $clean;
			}
		}
		$text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$text = preg_replace("/\r\n?/", "\n", $text) ?? $text;
		$text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
		$text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
		$text = self::cleanPedagogicalText(trim($text));
		return $text;
	}

	private static function extractDocx(string $path): string
	{
		if (!class_exists(\ZipArchive::class)) {
			return '';
		}
		$zip = new \ZipArchive();
		if ($zip->open($path) !== true) {
			return '';
		}
		$xml = $zip->getFromName('word/document.xml');
		$zip->close();
		if ($xml === false || $xml === '') {
			return '';
		}
		$xml = preg_replace('/<\/w:p>/', "\n", $xml) ?? $xml;
		$xml = preg_replace('/<\/w:tr>/', "\n", $xml) ?? $xml;
		$xml = preg_replace('/<w:tab[^\/]*\/>/', "\t", $xml) ?? $xml;
		$xml = strip_tags($xml);
		return $xml;
	}

	/** Best-effort for legacy .doc (binary). */
	private static function extractDocBinary(string $bytes): string
	{
		$out = '';
		$len = strlen($bytes);
		$buf = '';
		for ($i = 0; $i < $len; $i++) {
			$c = ord($bytes[$i]);
			if (($c >= 32 && $c < 127) || $c === 10 || $c === 13 || $c === 9) {
				$buf .= chr($c);
			} else {
				if (strlen($buf) >= 4) {
					$out .= $buf . ' ';
				}
				$buf = '';
			}
		}
		if (strlen($buf) >= 4) {
			$out .= $buf;
		}
		return $out;
	}

	/** Lightweight PDF text stream scrape (no external libs). */
	private static function extractPdf(string $bytes): string
	{
		$chunks = [];
		if (preg_match_all('/stream\s*(.*?)\s*endstream/s', $bytes, $m)) {
			foreach ($m[1] as $stream) {
				$decoded = @gzuncompress($stream);
				if ($decoded === false) {
					$decoded = @gzinflate($stream);
				}
				if ($decoded === false) {
					$decoded = $stream;
				}
				if (preg_match_all('/\((?:\\\\.|[^\\\\)])*\)/', $decoded, $tm)) {
					foreach ($tm[0] as $tok) {
						$t = substr($tok, 1, -1);
						$t = str_replace(['\\n', '\\r', '\\t', '\\(', '\\)'], ["\n", "\n", "\t", '(', ')'], $t);
						$chunks[] = $t;
					}
				}
				if (preg_match_all('/\[(.*?)\]\s*TJ/s', $decoded, $tj)) {
					foreach ($tj[1] as $arr) {
						if (preg_match_all('/\((?:\\\\.|[^\\\\)])*\)/', $arr, $parts)) {
							foreach ($parts[0] as $p) {
								$t = substr($p, 1, -1);
								$chunks[] = $t;
							}
							$chunks[] = ' ';
						}
					}
				}
			}
		}
		$text = implode('', $chunks);
		if (mb_strlen(trim($text), 'UTF-8') < 40) {
			// Likely scanned/image PDF
			return '';
		}
		return $text;
	}

	/**
	 * Deterministic LO/IC extraction from RTB/TVET module curriculum text.
	 * Handles Learning Outcomes, Indicative Contents, Elements of Competence, Performance Criteria.
	 *
	 * @return list<array{code:string,title:string,performance_criteria:list<string>,indicative_contents:list<array{code:string,title:string,hours:float|null}>}>
	 */
	public static function parseModuleLearningOutcomes(string $text): array
	{
		$text = self::cleanPedagogicalText($text);
		if (mb_strlen($text, 'UTF-8') < 80) {
			return [];
		}
		// Normalize common headings
		$norm = preg_replace('/\r\n?/', "\n", $text) ?? $text;
		$norm = preg_replace('/[ \t]+/', ' ', $norm) ?? $norm;

		$los = [];
		// Pattern A: LO1 / LO 1 / Learning Outcome 1: title
		if (preg_match_all(
			'/(?:^|\n)\s*(?:Learning\s+Outcomes?\s*|LO\s*)(\d{1,2})\s*[:.\-–—)]\s*([^\n]{3,200})/iu',
			$norm,
			$lm,
			PREG_SET_ORDER
		)) {
			foreach ($lm as $m) {
				$num = (int) $m[1];
				$title = self::cleanModuleTitle(trim($m[2]));
				if ($title === '' || $num < 1) {
					continue;
				}
				$code = 'LO' . $num;
				$los[$num] = [
					'code' => $code,
					'title' => $title,
					'performance_criteria' => [],
					'indicative_contents' => [],
				];
			}
		}

		// Pattern B: Element of competence N: title
		if ($los === [] && preg_match_all(
			'/(?:^|\n)\s*(?:Element(?:s)?\s+of\s+Competence|EoC)\s*(\d{1,2})\s*[:.\-–—)]\s*([^\n]{3,200})/iu',
			$norm,
			$em,
			PREG_SET_ORDER
		)) {
			foreach ($em as $m) {
				$num = (int) $m[1];
				$title = self::cleanModuleTitle(trim($m[2]));
				if ($title === '' || $num < 1) {
					continue;
				}
				$los[$num] = [
					'code' => 'LO' . $num,
					'title' => $title,
					'performance_criteria' => [],
					'indicative_contents' => [],
				];
			}
		}

		// Indicative contents: IC1.1 / 1.1 title / Indicative content 1.1
		if (preg_match_all(
			'/(?:^|\n)\s*(?:IC\s*)?(\d{1,2})\.(\d{1,2})\s*[:.\-–—)]\s*([^\n]{3,220})/iu',
			$norm,
			$im,
			PREG_SET_ORDER
		)) {
			foreach ($im as $m) {
				$loNum = (int) $m[1];
				$icNum = (int) $m[2];
				$icTitle = trim($m[3]);
				// Skip performance-criteria-looking lines that are too short / numeric only
				$icTitle = self::cleanModuleTitle($icTitle);
				if ($icTitle === '' || $loNum < 1) {
					continue;
				}
				// Reject lines that look like page headers
				if (preg_match('/^(page|rqf|tvet|module\s+code|learning\s+hours)\b/i', $icTitle)) {
					continue;
				}
				$hours = null;
				if (preg_match('/\((\d+(?:\.\d+)?)\s*h(?:ours?)?\)\s*$/i', $m[3], $hm)) {
					$hours = (float) $hm[1];
				} elseif (preg_match('/\b(\d+(?:\.\d+)?)\s*h(?:ours?)?\s*$/i', $m[3], $hm2)) {
					$val = (float) $hm2[1];
					if ($val > 0 && $val <= 80) {
						$hours = $val;
					}
				}
				if (!isset($los[$loNum])) {
					$los[$loNum] = [
						'code' => 'LO' . $loNum,
						'title' => '',
						'performance_criteria' => [],
						'indicative_contents' => [],
					];
				}
				$icCode = 'IC' . $loNum . '.' . $icNum;
				// Avoid duplicates
				$exists = false;
				foreach ($los[$loNum]['indicative_contents'] as $existing) {
					if (($existing['code'] ?? '') === $icCode) {
						$exists = true;
						break;
					}
				}
				if (!$exists) {
					$los[$loNum]['indicative_contents'][] = [
						'code' => $icCode,
						'title' => $icTitle,
						'hours' => $hours,
					];
				}
			}
		}

		// Performance criteria under each LO (optional)
		if (preg_match_all(
			'/(?:^|\n)\s*(?:PC\s*)?(\d{1,2})\.(\d{1,2})\s*[:.\-–—)]\s*((?:The\s+trainee|Trainee|Candidate|Able to)[^\n]{10,220})/iu',
			$norm,
			$pm,
			PREG_SET_ORDER
		)) {
			foreach ($pm as $m) {
				$loNum = (int) $m[1];
				$pc = trim(preg_replace('/\s+/', ' ', $m[3]) ?? $m[3]);
				if ($pc === '' || !isset($los[$loNum])) {
					continue;
				}
				if (!in_array($pc, $los[$loNum]['performance_criteria'], true)) {
					$los[$loNum]['performance_criteria'][] = $pc;
				}
			}
		}

		if ($los === []) {
			return [];
		}
		ksort($los);
		// Drop empty shell LOs with no title and no IC
		$out = [];
		foreach ($los as $lo) {
			if (($lo['title'] ?? '') === '' && empty($lo['indicative_contents'])) {
				continue;
			}
			if (($lo['title'] ?? '') === '' && !empty($lo['indicative_contents'])) {
				$lo['title'] = 'Learning outcome ' . preg_replace('/\D+/', '', (string) ($lo['code'] ?? ''));
			}
			$out[] = $lo;
		}
		return $out;
	}

	/** Use poppler/xpdf pdftotext when installed on the server. */
	private static function extractPdfCli(string $absolutePath): string
	{
		if (!is_file($absolutePath) || !function_exists('exec')) {
			return '';
		}
		$bin = trim((string) @shell_exec('command -v pdftotext 2>/dev/null'));
		if ($bin === '') {
			// Windows common path
			foreach ([
				'C:\\xampp\\pdftotext.exe',
				'C:\\Program Files\\poppler\\Library\\bin\\pdftotext.exe',
			] as $cand) {
				if (is_file($cand)) {
					$bin = $cand;
					break;
				}
			}
		}
		if ($bin === '') {
			return '';
		}
		$tmp = tempnam(sys_get_temp_dir(), 'pdfx');
		if ($tmp === false) {
			return '';
		}
		@unlink($tmp);
		$outFile = $tmp . '.txt';
		$cmd = escapeshellarg($bin) . ' -layout -enc UTF-8 ' . escapeshellarg($absolutePath) . ' ' . escapeshellarg($outFile) . ' 2>nul';
		@exec($cmd);
		$text = is_file($outFile) ? (string) @file_get_contents($outFile) : '';
		@unlink($outFile);
		return $text;
	}
}
