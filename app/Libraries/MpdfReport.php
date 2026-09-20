<?php

namespace App\Libraries;

use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * HTML → PDF using mPDF (modern replacement for wkhtmltopdf on staff reports).
 */
class MpdfReport
{
	/** @var array<string,string> */
	private static $imageVars = [];

	/**
	 * @param array{title?:string,orientation?:string,margin?:int} $opts
	 */
	public static function render(string $html, string $filename, array $opts = []): string
	{
		self::ensureLoaded();
		$tempDir = rtrim(WRITEPATH, '/\\') . DIRECTORY_SEPARATOR . 'mpdf';
		if (!is_dir($tempDir)) {
			@mkdir($tempDir, 0775, true);
		}

		$orientation = strtoupper((string) ($opts['orientation'] ?? 'P'));
		if ($orientation !== 'L') {
			$orientation = 'P';
		}
		$margin = (int) ($opts['margin'] ?? 10);

		$mpdf = new Mpdf([
			'mode' => 'utf-8',
			'format' => 'A4',
			'orientation' => $orientation,
			'margin_left' => $margin,
			'margin_right' => $margin,
			'margin_top' => 12,
			'margin_bottom' => 16,
			'margin_header' => 6,
			'margin_footer' => 8,
			'tempDir' => $tempDir,
			'default_font' => 'dejavusanscondensed',
			'default_font_size' => 9,
		]);
		$mpdf->SetTitle((string) ($opts['title'] ?? $filename));
		$mpdf->SetAuthor('Xander School');
		$mpdf->SetCreator('Xander School');
		$mpdf->SetDisplayMode('fullpage');
		$mpdf->SetCompression(true);
		$mpdf->shrink_tables_to_fit = 1;
		foreach (self::$imageVars as $name => $bytes) {
			$mpdf->imageVars[$name] = $bytes;
		}
		$mpdf->SetHTMLFooter(
			'<table width="100%" style="font-size:8pt;color:#64748b;border-top:1px solid #cbd5e1;">'
			. '<tr><td>Xander School</td>'
			. '<td style="text-align:right;">Page {PAGENO} / {nbpg}</td></tr></table>'
		);
		$mpdf->WriteHTML($html);
		$pdf = $mpdf->Output($filename, Destination::STRING_RETURN);
		if (!is_string($pdf) || strncmp($pdf, '%PDF', 4) !== 0) {
			throw new \RuntimeException('mPDF returned empty output.');
		}

		return $pdf;
	}

	/**
	 * Send the PDF to the browser and stop (same as wkhtmltopdf MODE_EMBEDDED).
	 * Must exit so CodeIgniter after-filters do not wrap it as HTML.
	 *
	 * @param array{title?:string,orientation?:string,margin?:int} $opts
	 */
	public static function stream(string $html, string $filename, array $opts = []): void
	{
		$pdf = self::render($html, $filename, $opts);
		$safe = preg_replace('/[^A-Za-z0-9_\-.]+/', '_', $filename) ?: 'staff_clock_report.pdf';
		while (ob_get_level() > 0) {
			ob_end_clean();
		}
		header('Content-Type: application/pdf');
		header('Content-Disposition: inline; filename="' . $safe . '"');
		header('Content-Length: ' . strlen($pdf));
		header('Cache-Control: private, max-age=0, must-revalidate');
		header('Pragma: public');
		echo $pdf;
		exit;
	}

	/**
	 * Embed a local logo as mPDF imageVars (HTTP / data-URI logos fail inside Docker).
	 * Returns src="var:name" which ImageProcessor reads from $mpdf->imageVars.
	 */
	public static function imageFileForPdf(string $absPath): string
	{
		$absPath = realpath($absPath) ?: $absPath;
		if ($absPath === '' || !is_file($absPath)) {
			return '';
		}
		$raw = @file_get_contents($absPath);
		if ($raw === false || $raw === '') {
			return '';
		}
		$jpeg = self::flattenToJpegBytes($raw);
		$bytes = ($jpeg !== '') ? $jpeg : $raw;
		$name = 'logo_' . md5($absPath . '|' . (string) @filemtime($absPath) . '|' . strlen($bytes));
		self::$imageVars[$name] = $bytes;

		return 'var:' . $name;
	}

	private static function flattenToJpegBytes(string $raw): string
	{
		$src = @imagecreatefromstring($raw);
		if ($src === false) {
			return '';
		}
		$sw = imagesx($src);
		$sh = imagesy($src);
		$scale = min(1.0, 240 / max(1, $sw), 240 / max(1, $sh));
		$dw = max(1, (int) round($sw * $scale));
		$dh = max(1, (int) round($sh * $scale));
		$dst = imagecreatetruecolor($dw, $dh);
		$white = imagecolorallocate($dst, 255, 255, 255);
		imagefill($dst, 0, 0, $white);
		imagecopyresampled($dst, $src, 0, 0, 0, 0, $dw, $dh, $sw, $sh);
		ob_start();
		imagejpeg($dst, null, 90);
		$jpeg = (string) ob_get_clean();
		imagedestroy($src);
		imagedestroy($dst);

		return strlen($jpeg) > 32 ? $jpeg : '';
	}

	private static function ensureLoaded(): void
	{
		if (class_exists(\Mpdf\Mpdf::class, false)) {
			return;
		}
		$autoload = rtrim(ROOTPATH, '/\\') . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
		if (is_file($autoload)) {
			require_once $autoload;
		}
		if (! class_exists(\Mpdf\Mpdf::class, true)) {
			throw new \RuntimeException('mPDF is not installed. Expected vendor/mpdf/mpdf.');
		}
	}
}
