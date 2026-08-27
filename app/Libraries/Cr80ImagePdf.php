<?php

namespace App\Libraries;

/**
 * Minimal PDF writer: one CR80 landscape page per JPEG (no Composer PDF lib).
 * MediaBox is ISO/IEC 7810 ID-1 — 85.6 × 54 mm.
 */
class Cr80ImagePdf
{
	/** 85.6 mm in PDF points */
	public const PAGE_W = 242.6456692913;
	/** 54.0 mm in PDF points */
	public const PAGE_H = 153.0708661417;

	/**
	 * @param array<int,string> $jpegs raw JPEG bytes
	 */
	public static function fromJpegs(array $jpegs): string
	{
		$pages = [];
		foreach ($jpegs as $jpeg) {
			if (!is_string($jpeg) || strlen($jpeg) < 100) {
				continue;
			}
			$info = @getimagesizefromstring($jpeg);
			$w = is_array($info) ? (int) $info[0] : WisdomCardRenderer::W;
			$h = is_array($info) ? (int) $info[1] : WisdomCardRenderer::H;
			$pages[] = [$jpeg, max(1, $w), max(1, $h)];
		}
		if (count($pages) === 0) {
			throw new \RuntimeException('No card images to write');
		}

		$n = count($pages);
		$pageIds = [];
		$imgIds = [];
		$contentIds = [];
		$next = 3;
		for ($i = 0; $i < $n; $i++) {
			$pageIds[] = $next;
			$imgIds[] = $next + 1;
			$contentIds[] = $next + 2;
			$next += 3;
		}

		$objs = [];
		$objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
		$kids = [];
		foreach ($pageIds as $id) {
			$kids[] = $id . ' 0 R';
		}
		$objs[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $n . ' >>';

		$pw = self::PAGE_W;
		$ph = self::PAGE_H;
		foreach ($pages as $i => $page) {
			$jpeg = $page[0];
			$iw = $page[1];
			$ih = $page[2];
			$pId = $pageIds[$i];
			$imId = $imgIds[$i];
			$cId = $contentIds[$i];
			$objs[$pId] = sprintf(
				'<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.5F %.5F] /Resources << /ProcSet [/PDF /ImageC] /XObject << /Im0 %d 0 R >> >> /Contents %d 0 R >>',
				$pw,
				$ph,
				$imId,
				$cId
			);
			$len = strlen($jpeg);
			$objs[$imId] = '<< /Type /XObject /Subtype /Image /Width ' . $iw
				. ' /Height ' . $ih
				. ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '
				. $len . " >>\nstream\n" . $jpeg . "\nendstream";
			$content = sprintf("q\n%.5F 0 0 %.5F 0 0 cm\n/Im0 Do\nQ\n", $pw, $ph);
			$objs[$cId] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . 'endstream';
		}

		return self::assemble($objs);
	}

	public static function stream(string $pdf, string $filename): void
	{
		if (headers_sent()) {
			echo $pdf;
			return;
		}
		header('Content-Type: application/pdf');
		header('Cache-Control: public, must-revalidate, max-age=0');
		header('Pragma: public');
		header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
		header('Content-Length: ' . strlen($pdf));
		header('Content-Disposition: inline; filename="' . basename($filename) . '"');
		echo $pdf;
		exit();
	}

	/**
	 * @param array<int,string> $objs 1-indexed object bodies
	 */
	private static function assemble(array $objs): string
	{
		ksort($objs);
		$out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
		$offsets = [];
		$max = max(array_keys($objs));
		for ($i = 1; $i <= $max; $i++) {
			if (!isset($objs[$i])) {
				continue;
			}
			$offsets[$i] = strlen($out);
			$out .= $i . " 0 obj\n" . $objs[$i] . "\nendobj\n";
		}
		$xrefPos = strlen($out);
		$size = $max + 1;
		$out .= "xref\n0 {$size}\n";
		$out .= "0000000000 65535 f \n";
		for ($i = 1; $i < $size; $i++) {
			$off = isset($offsets[$i]) ? $offsets[$i] : 0;
			$out .= sprintf("%010d 00000 n \n", $off);
		}
		$out .= "trailer\n<< /Size {$size} /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF\n";
		return $out;
	}
}
