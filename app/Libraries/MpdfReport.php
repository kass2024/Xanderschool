<?php

namespace App\Libraries;

use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * HTML → PDF using mPDF (modern replacement for wkhtmltopdf on staff reports).
 */
class MpdfReport
{
	/**
	 * @param array{title?:string,orientation?:string,margin?:int} $opts
	 */
	public static function stream(string $html, string $filename, array $opts = []): void
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
		$mpdf->SetHTMLFooter(
			'<table width="100%" style="font-size:8pt;color:#64748b;border-top:1px solid #cbd5e1;">'
			. '<tr><td>Xander School</td>'
			. '<td style="text-align:right;">Page {PAGENO} / {nbpg}</td></tr></table>'
		);
		$mpdf->WriteHTML($html);
		$mpdf->Output($filename, Destination::INLINE);
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
