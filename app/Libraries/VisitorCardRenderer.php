<?php

namespace App\Libraries;

/**
 * Rasterize visitor passes onto visitor_pass_template.png.
 * Overlay only: visited student name + registration code.
 * Leaves template barcode/artwork untouched.
 * Output: 1712×1080 JPEG (20 px/mm CR80) for Cr80ImagePdf / DataCard printers.
 */
class VisitorCardRenderer
{
	public const W = 1712;
	public const H = 1080;

	/** Source artwork size (visitor pass PNG is 1011×638). */
	private const SRC_W = 1011;
	private const SRC_H = 638;

	public const TEMPLATE = 'assets/images/background/visitor_pass_template.png';

	/** @var string */
	private $font;

	public function __construct(?string $fontPath = null)
	{
		$this->font = $fontPath ?: self::resolveFont();
	}

	public static function isAvailable(): bool
	{
		return function_exists('imagecreatetruecolor')
			&& function_exists('imagettftext')
			&& is_file(self::resolveFont())
			&& self::templatePath() !== null;
	}

	/**
	 * @param array{student_name?:string,student_regno?:string,name?:string,regno?:string} $visitor
	 */
	public function renderJpeg(array $visitor): ?string
	{
		$im = $this->render($visitor);
		if ($im === null) {
			return null;
		}
		ob_start();
		imagejpeg($im, null, 95);
		$bytes = ob_get_clean();
		imagedestroy($im);
		return is_string($bytes) && strlen($bytes) > 100 ? $bytes : null;
	}

	/**
	 * @param array<string,mixed> $visitor
	 * @return resource|\GdImage|null
	 */
	public function render(array $visitor)
	{
		if (!function_exists('imagecreatetruecolor') || !is_file($this->font)) {
			return null;
		}

		$im = $this->baseFromTemplate();
		if ($im === null) {
			return null;
		}
		imagealphablending($im, true);

		$name = $this->upper(trim((string) ($visitor['student_name'] ?? ($visitor['name'] ?? ''))));
		if ($name === '') {
			$name = '—';
		}
		$code = $this->upper(trim((string) ($visitor['student_regno'] ?? ($visitor['regno'] ?? ''))));
		if ($code === '') {
			$code = '—';
		}

		$white = imagecolorallocate($im, 248, 250, 252);
		$gold = imagecolorallocate($im, 245, 158, 11);

		/*
		 * Boxes measured on 1011×638 artwork (same as visitor_card_smart.php mm layout):
		 * name below VISITED STUDENT:; code after CODE: label.
		 */
		$this->drawWrappedCentered(
			$im,
			$name,
			$this->sx(65),
			$this->sy(187),
			$this->sx(881),
			$this->sy(99),
			$white,
			52.0,
			22.0
		);
		$this->drawLeftFit(
			$im,
			$code,
			$this->sx(328),
			$this->sy(378),
			$this->sx(496),
			$this->sy(40),
			$gold,
			34.0,
			18.0
		);

		return $im;
	}

	/** @return resource|\GdImage|null */
	private function baseFromTemplate()
	{
		$path = self::templatePath();
		if ($path === null) {
			return null;
		}
		$src = $this->loadImage($path);
		if (!$src) {
			return null;
		}
		if (imagesx($src) === self::W && imagesy($src) === self::H) {
			return $src;
		}
		$im = imagecreatetruecolor(self::W, self::H);
		imagecopyresampled($im, $src, 0, 0, 0, 0, self::W, self::H, imagesx($src), imagesy($src));
		imagedestroy($src);
		return $im;
	}

	private static function templatePath(): ?string
	{
		$rel = self::TEMPLATE;
		$candidates = [
			rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel),
			rtrim(ROOTPATH, '/\\') . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel),
		];
		foreach ($candidates as $p) {
			if (is_file($p)) {
				return $p;
			}
		}
		return null;
	}

	private function sx(float $x): int
	{
		return (int) round($x / self::SRC_W * self::W);
	}

	private function sy(float $y): int
	{
		return (int) round($y / self::SRC_H * self::H);
	}

	/** @param resource|\GdImage $im */
	private function drawLeftFit($im, string $text, int $x, int $y, int $w, int $h, int $color, float $max, float $min): void
	{
		$size = $this->fitSize($text, $w, $h, $max, $min);
		$m = $this->measure($size, $text);
		$tx = $x - (int) $m['box'][0];
		$textH = max(1, $m['h']);
		$ty = $y + (int) round(($h - $textH) / 2) + (int) abs($m['box'][7]);
		imagettftext($im, $size, 0, $tx, $ty, $color, $this->font, $text);
	}

	/** @param resource|\GdImage $im */
	private function drawWrappedCentered($im, string $text, int $x, int $y, int $w, int $h, int $color, float $max, float $min): void
	{
		$size = $max;
		$lines = [$text];
		while ($size > $min) {
			$lines = $this->wrapLines($text, $size, $w);
			$lineH = 0;
			$maxLineW = 0;
			foreach ($lines as $line) {
				$m = $this->measure($size, $line);
				$lineH = max($lineH, $m['h']);
				$maxLineW = max($maxLineW, $m['w']);
			}
			$totalH = (int) round(count($lines) * $lineH * 1.12);
			if ($maxLineW <= $w && $totalH <= $h && count($lines) <= 3) {
				break;
			}
			$size -= 1.2;
		}

		$lineMetrics = [];
		$lineH = 0;
		foreach ($lines as $line) {
			$m = $this->measure($size, $line);
			$lineMetrics[] = $m;
			$lineH = max($lineH, $m['h']);
		}
		$gap = (int) round($lineH * 0.12);
		$totalH = count($lines) * $lineH + max(0, count($lines) - 1) * $gap;
		$cursorY = $y + (int) round(($h - $totalH) / 2);

		foreach ($lines as $i => $line) {
			$m = $lineMetrics[$i];
			$tx = $x + (int) round(($w - $m['w']) / 2) - (int) $m['box'][0];
			$ty = $cursorY + (int) abs($m['box'][7]);
			imagettftext($im, $size, 0, $tx, $ty, $color, $this->font, $line);
			$cursorY += $lineH + $gap;
		}
	}

	/** @return list<string> */
	private function wrapLines(string $text, float $size, int $maxW): array
	{
		$words = preg_split('/\s+/u', trim($text)) ?: [];
		if (count($words) === 0) {
			return [$text];
		}
		$lines = [];
		$current = '';
		foreach ($words as $word) {
			$trial = $current === '' ? $word : ($current . ' ' . $word);
			$m = $this->measure($size, $trial);
			if ($m['w'] <= $maxW || $current === '') {
				$current = $trial;
				continue;
			}
			$lines[] = $current;
			$current = $word;
		}
		if ($current !== '') {
			$lines[] = $current;
		}
		return $lines ?: [$text];
	}

	private function fitSize(string $text, int $maxW, int $maxH, float $max, float $min): float
	{
		$size = $max;
		while ($size > $min) {
			$m = $this->measure($size, $text);
			if ($m['w'] <= $maxW && $m['h'] <= $maxH) {
				return $size;
			}
			$size -= 0.8;
		}
		return $min;
	}

	/** @return array{w:int,h:int,box:array<int,int|float>} */
	private function measure(float $size, string $text): array
	{
		$b = imagettfbbox($size, 0, $this->font, $text);
		if ($b === false) {
			return ['w' => 0, 'h' => 0, 'box' => [0, 0, 0, 0, 0, 0, 0, 0]];
		}
		return [
			'w' => (int) abs($b[2] - $b[0]),
			'h' => (int) abs($b[7] - $b[1]),
			'box' => $b,
		];
	}

	/** @return resource|\GdImage|null */
	private function loadImage(string $path)
	{
		$info = @getimagesize($path);
		if (!is_array($info)) {
			return null;
		}
		switch ((int) $info[2]) {
			case IMAGETYPE_JPEG:
				return @imagecreatefromjpeg($path) ?: null;
			case IMAGETYPE_PNG:
				return @imagecreatefrompng($path) ?: null;
			case IMAGETYPE_GIF:
				return @imagecreatefromgif($path) ?: null;
			case IMAGETYPE_WEBP:
				return function_exists('imagecreatefromwebp') ? (@imagecreatefromwebp($path) ?: null) : null;
			default:
				return null;
		}
	}

	private function upper(string $v): string
	{
		return mb_strtoupper($v, 'UTF-8');
	}

	private static function resolveFont(): string
	{
		$fcpath = rtrim(FCPATH, '/\\');
		$root = defined('ROOTPATH') ? rtrim(ROOTPATH, '/\\') : dirname($fcpath);
		$candidates = [
			$fcpath . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'fonts' . DIRECTORY_SEPARATOR . 'DejaVuSans-Bold.ttf',
			$root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'fonts' . DIRECTORY_SEPARATOR . 'DejaVuSans-Bold.ttf',
			'/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
			'/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
			'/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
			'C:\\Windows\\Fonts\\arialbd.ttf',
			'C:\\Windows\\Fonts\\arial.ttf',
		];
		foreach ($candidates as $p) {
			if (is_file($p)) {
				return $p;
			}
		}
		return $candidates[0];
	}
}
