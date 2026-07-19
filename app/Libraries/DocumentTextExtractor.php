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
		return trim($text);
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
