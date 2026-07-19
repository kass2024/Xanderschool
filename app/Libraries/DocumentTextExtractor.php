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
