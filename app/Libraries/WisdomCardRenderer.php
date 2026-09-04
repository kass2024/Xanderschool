<?php

namespace App\Libraries;

/**
 * Rasterize student ID cards onto the fixed Wisdom High School PNG template.
 * Photo (circle) + NAME / CLASS / ACADEMIC YEAR / ID NO overlays.
 * Output: 1712×1080 JPEG (20 px/mm CR80) for Cr80ImagePdf.
 */
class WisdomCardRenderer
{
	public const W = 1712;
	public const H = 1080;

	/** Source artwork size (student_pass_template.png). */
	private const SRC_W = 1011;
	private const SRC_H = 639;

	public const TEMPLATE = 'assets/images/background/student_pass_template.png';

	private const TEAL = [0, 130, 142];
	private const NAVY = [4, 73, 107];

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
			&& is_file(self::resolveFont());
	}

	/**
	 * @param array<string,mixed> $student
	 * @param array<string,mixed> $ctx logo, year
	 */
	public function renderJpeg(array $student, array $ctx = []): ?string
	{
		$im = $this->render($student, $ctx);
		if ($im === null) {
			return null;
		}
		ob_start();
		imagejpeg($im, null, 93);
		$bytes = ob_get_clean();
		imagedestroy($im);
		return is_string($bytes) && strlen($bytes) > 100 ? $bytes : null;
	}

	/**
	 * @param array<string,mixed> $student
	 * @param array<string,mixed> $ctx
	 * @return resource|\GdImage|null
	 */
	public function render(array $student, array $ctx = [])
	{
		if (!function_exists('imagecreatetruecolor') || !is_file($this->font)) {
			return null;
		}

		$photoPath = $this->profilePath($student['photo'] ?? '');
		if ($photoPath === null) {
			return null;
		}

		$im = $this->baseFromTemplate();
		if ($im === null) {
			return null;
		}
		imagealphablending($im, true);

		$white = imagecolorallocate($im, 255, 255, 255);
		$navy = imagecolorallocate($im, self::NAVY[0], self::NAVY[1], self::NAVY[2]);
		$teal = imagecolorallocate($im, self::TEAL[0], self::TEAL[1], self::TEAL[2]);

		$fullName = $this->upper(trim((string) ($student['name'] ?? ($student['stdnames'] ?? ''))));
		$classLabel = $this->upper(trim((string) ($student['class'] ?? '')));
		$year = $this->upper(CardLayout::formatAcademicYear((string) ($ctx['year'] ?? '')));
		// Keep year as 2026/2027 — drop trailing term suffix if present.
		$year = preg_replace('#/+TERM\s*\d*$#i', '', $year) ?? $year;
		$year = rtrim($year, '/');
		$idNo = trim((string) ($student['regno'] ?? ''));
		if ($idNo === '') {
			$idNo = trim((string) ($student['card'] ?? ''));
		}
		$idNo = $this->upper($idNo);

		$this->pastePhoto($im, $photoPath, $teal);
		$this->drawFieldValues($im, $fullName, $classLabel, $year, $navy);
		$this->drawIdBar($im, $idNo !== '' ? $idNo : '—', $navy, $white);

		return $im;
	}

	/**
	 * Detect the inner photo hole on the already-scaled card canvas.
	 * Returns [cx, cy, diameter] in output pixels.
	 *
	 * @param resource|\GdImage $im
	 * @return array{0:int,1:int,2:int}
	 */
	private function detectPhotoHole($im): array
	{
		$w = imagesx($im);
		$h = imagesy($im);
		$guessCx = (int) round($w * 0.205);
		$y0 = (int) round($h * 0.35);
		$y1 = (int) round($h * 0.72);

		$bestW = 0;
		$bestY = (int) round($h * 0.54);
		$bestL = 0;
		$bestR = 0;
		for ($y = $y0; $y < $y1; $y++) {
			$l = $guessCx;
			while ($l > (int) ($w * 0.04) && !$this->isTealRingPixel($im, $l, $y)) {
				$l--;
			}
			$r = $guessCx;
			while ($r < (int) ($w * 0.42) && !$this->isTealRingPixel($im, $r, $y)) {
				$r++;
			}
			if (!$this->isTealRingPixel($im, $l, $y) || !$this->isTealRingPixel($im, $r, $y)) {
				continue;
			}
			$l++;
			$r--;
			$span = $r - $l + 1;
			// Photo hole: left edge inside left slab, right edge before ID banner.
			if ($span > $bestW && $l > (int) ($w * 0.07) && $l < (int) ($w * 0.12) && $r > (int) ($w * 0.28) && $r < (int) ($w * 0.36)) {
				$bestW = $span;
				$bestY = $y;
				$bestL = $l;
				$bestR = $r;
			}
		}

		if ($bestW < 100) {
			// Fallback measured on 1011×639 artwork.
			return [$this->sx(207.3), $this->sy(353), $this->sx(266)];
		}

		$cx = (int) round(($bestL + $bestR) / 2);
		// Refine cy: equal chord widths above/below equator.
		$target = (int) round($bestW * 0.92);
		$yTop = $bestY;
		$yBot = $bestY;
		for ($y = $bestY; $y >= $y0; $y--) {
			$span = $this->holeSpanAt($im, $cx, $y, $w);
			if ($span < $target) {
				$yTop = $y;
				break;
			}
		}
		for ($y = $bestY; $y < $y1; $y++) {
			$span = $this->holeSpanAt($im, $cx, $y, $w);
			if ($span < $target) {
				$yBot = $y;
				break;
			}
		}
		$cy = (int) round(($yTop + $yBot) / 2);
		// Template ring sits slightly below the widest chord midpoint.
		$cy += (int) round($bestW * 0.025);
		// Oversized so photo tucks under the teal ring (covers white inner stroke).
		$d = (int) round($bestW * 1.10);
		return [$cx, $cy, max(2, $d)];
	}

	/** @param resource|\GdImage $im */
	private function isTealRingPixel($im, int $x, int $y): bool
	{
		$rgb = imagecolorat($im, $x, $y);
		$r = ($rgb >> 16) & 0xFF;
		$g = ($rgb >> 8) & 0xFF;
		$b = $rgb & 0xFF;
		return $r <= 25 && $g >= 105 && $g <= 160 && $b >= 115 && $b <= 175;
	}

	/** @param resource|\GdImage $im */
	private function holeSpanAt($im, int $cx, int $y, int $w): int
	{
		$l = $cx;
		while ($l > (int) ($w * 0.04) && !$this->isTealRingPixel($im, $l, $y)) {
			$l--;
		}
		$r = $cx;
		while ($r < (int) ($w * 0.42) && !$this->isTealRingPixel($im, $r, $y)) {
			$r++;
		}
		if (!$this->isTealRingPixel($im, $l, $y) || !$this->isTealRingPixel($im, $r, $y)) {
			return 0;
		}
		return ($r - 1) - ($l + 1) + 1;
	}

	/**
	 * @param resource|\GdImage $im
	 */
	private function pastePhoto($im, string $path, int $teal): void
	{
		[$cx, $cy, $d] = $this->detectPhotoHole($im);

		$src = $this->loadImage($path);
		if (!$src) {
			return;
		}
		$square = $this->coverSquare($src, max(2, $d), 0.22);
		imagedestroy($src);
		if (!$square) {
			return;
		}

		// Opaque circle paint — truecolor RGB ints (no imagecolorallocate / alpha gaps).
		$r = $d / 2.0;
		$r2 = $r * $r;
		$x0 = $cx - (int) ($d / 2);
		$y0 = $cy - (int) ($d / 2);
		for ($yy = 0; $yy < $d; $yy++) {
			$dy = $yy + 0.5 - $r;
			for ($xx = 0; $xx < $d; $xx++) {
				$dx = $xx + 0.5 - $r;
				if (($dx * $dx + $dy * $dy) > $r2) {
					continue;
				}
				imagesetpixel($im, $x0 + $xx, $y0 + $yy, imagecolorat($square, $xx, $yy) & 0xFFFFFF);
			}
		}
		imagedestroy($square);
	}

	/** @return resource|\GdImage|null */
	private function baseFromTemplate()
	{
		$path = $this->assetPath(self::TEMPLATE);
		if ($path === null) {
			$path = $this->assetPath(CardLayout::WISDOM_CHROME);
		}
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

	private function sx(float $x): int
	{
		return (int) round($x / self::SRC_W * self::W);
	}

	private function sy(float $y): int
	{
		return (int) round($y / self::SRC_H * self::H);
	}

	/**
	 * Wipe template sample labels/values and redraw aligned "LABEL : VALUE" rows.
	 *
	 * @param resource|\GdImage $im
	 */
	private function drawFieldValues($im, string $name, string $class, string $year, int $navy): void
	{
		// Clear full info block (labels + values) on the white card face.
		$clearX = $this->sx(360);
		$clearY = $this->sy(320);
		$clearW = $this->sx(620);
		$clearH = $this->sy(130);
		$bg = imagecolorallocate($im, 248, 248, 248);
		imagefilledrectangle($im, $clearX, $clearY, $clearX + $clearW, $clearY + $clearH, $bg);

		$labelX = $this->sx(368);
		// Colon column after longest label "ACADEMIC YEAR".
		$colonX = $this->sx(560);
		$valueX = $this->sx(575);
		$valueW = $this->sx(400);
		$rowH = $this->sy(36);
		$labelSize = 22.0;

		$rows = [
			[$this->sy(328), 'NAME', $name !== '' ? $name : '—'],
			[$this->sy(368), 'CLASS', $class !== '' ? $class : '—'],
			[$this->sy(408), 'ACADEMIC YEAR', $year !== '' ? $year : '—'],
		];
		foreach ($rows as $row) {
			[$y, $label, $val] = $row;
			$this->drawText($im, $label, $labelSize, $labelX, $y, $this->sx(200), $rowH, $navy, 'left');
			$this->drawText($im, ':', $labelSize, $colonX, $y, $this->sx(20), $rowH, $navy, 'left');
			$size = $this->fitSize($val, $valueW, (int) round($rowH * 0.78), 28, 12);
			$this->drawText($im, $val, $size, $valueX, $y, $valueW, $rowH, $navy, 'left');
		}
	}

	/**
	 * Cover-crop to an opaque square for reliable circle painting.
	 * Slight zoom so the subject fills the circle (less backdrop crescent).
	 *
	 * @param resource|\GdImage $src
	 * @return resource|\GdImage|null
	 */
	private function coverSquare($src, int $size, float $biasY)
	{
		$sw = imagesx($src);
		$sh = imagesy($src);
		if ($sw < 2 || $sh < 2) {
			return null;
		}
		if ($sw >= $sh) {
			$side = $sh;
			$sx = (int) max(0, ($sw - $sh) / 2);
			$sy = 0;
		} else {
			$side = $sw;
			$sx = 0;
			$sy = (int) max(0, ($sh - $sw) * $biasY);
		}
		$side = max(1, min($side, $sw - $sx, $sh - $sy));
		// Zoom ~28% into the cover square so faces fill the ring better.
		$zoom = 0.72;
		$crop = max(1, (int) round($side * $zoom));
		$sx += (int) round(($side - $crop) / 2);
		$sy += (int) round(($side - $crop) * 0.35);
		$sx = max(0, min($sx, $sw - $crop));
		$sy = max(0, min($sy, $sh - $crop));
		$sq = imagecreatetruecolor($size, $size);
		imagecopyresampled($sq, $src, 0, 0, $sx, $sy, $size, $size, $crop, $crop);
		return $sq;
	}

	/**
	 * Cover sample ID text on the footer bar, then draw real ID NO.
	 *
	 * @param resource|\GdImage $im
	 */
	private function drawIdBar($im, string $idNo, int $navy, int $white): void
	{
		// Cover navy ribbon + leftover sample "ID NO: …" on the teal footer.
		$x = $this->sx(40);
		$y = $this->sy(490);
		$w = $this->sx(380);
		$h = $this->sy(90);
		imagefilledrectangle($im, $x, $y, $x + $w, $y + $h, $navy);

		$text = 'ID NO: ' . $idNo;
		$size = $this->fitSize($text, $w - 16, (int) round($h * 0.42), 34, 14);
		$this->drawCentered($im, $text, $size, $x, $y, $w, $h, $white);
	}

	/**
	 * @param resource|\GdImage $src
	 * @return resource|\GdImage|null
	 */
	private function coverCircle($src, int $size, float $biasY)
	{
		$sw = imagesx($src);
		$sh = imagesy($src);
		if ($sw < 2 || $sh < 2) {
			return null;
		}
		if ($sw >= $sh) {
			$side = $sh;
			$sx = (int) max(0, ($sw - $sh) / 2);
			$sy = 0;
		} else {
			$side = $sw;
			$sx = 0;
			$sy = (int) max(0, ($sh - $sw) * $biasY);
		}
		$side = max(1, min($side, $sw - $sx, $sh - $sy));
		$sq = $this->truecolor($size, $size, true);
		imagealphablending($sq, true);
		imagecopyresampled($sq, $src, 0, 0, $sx, $sy, $size, $size, $side, $side);
		imagealphablending($sq, false);
		$this->applyCircleMask($sq);
		return $sq;
	}

	/** @param resource|\GdImage $im */
	private function applyCircleMask($im): void
	{
		$s = imagesx($im);
		imagealphablending($im, false);
		imagesavealpha($im, true);
		$r = $s / 2.0;
		$rInner = $r - 0.65;
		for ($y = 0; $y < $s; $y++) {
			$dy = $y + 0.5 - $r;
			for ($x = 0; $x < $s; $x++) {
				$dx = $x + 0.5 - $r;
				$dist = sqrt($dx * $dx + $dy * $dy);
				if ($dist >= $r) {
					imagesetpixel($im, $x, $y, 0x7F000000);
					continue;
				}
				if ($dist <= $rInner) {
					continue;
				}
				$fade = ($r - $dist) / max(0.001, $r - $rInner);
				$pix = imagecolorat($im, $x, $y);
				$a = ($pix >> 24) & 0x7F;
				$na = (int) round(127 - (127 - $a) * $fade);
				$rgb = $pix & 0x00FFFFFF;
				imagesetpixel($im, $x, $y, ($na << 24) | $rgb);
			}
		}
	}

	/**
	 * @return resource|\GdImage
	 */
	private function truecolor(int $w, int $h, bool $clear)
	{
		$im = imagecreatetruecolor($w, $h);
		imagealphablending($im, false);
		imagesavealpha($im, true);
		$t = imagecolorallocatealpha($im, 0, 0, 0, 127);
		imagefill($im, 0, 0, $t);
		if (!$clear) {
			imagealphablending($im, true);
			$white = imagecolorallocate($im, 255, 255, 255);
			imagefilledrectangle($im, 0, 0, $w, $h, $white);
			imagealphablending($im, false);
		}
		return $im;
	}

	/** @param resource|\GdImage $im */
	private function drawCentered($im, string $text, float $size, int $x, int $y, int $w, int $h, int $color): void
	{
		$this->drawText($im, $text, $size, $x, $y, $w, $h, $color, 'center');
	}

	/** @param resource|\GdImage $im */
	private function drawText($im, string $text, float $size, int $x, int $y, int $w, int $h, int $color, string $align): void
	{
		$m = $this->measure($size, $text);
		$tx = $align === 'center'
			? $x + (int) round(($w - $m['w']) / 2) - (int) $m['box'][0]
			: $x - (int) $m['box'][0];
		// imagettftext Y is baseline: center glyph box inside [y, y+h]
		$textH = max(1, $m['h']);
		$ty = $y + (int) round(($h - $textH) / 2) + (int) abs($m['box'][7]);
		imagettftext($im, $size, 0, $tx, $ty, $color, $this->font, $text);
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
		$src = null;
		switch ((int) $info[2]) {
			case IMAGETYPE_JPEG:
				$src = @imagecreatefromjpeg($path);
				break;
			case IMAGETYPE_PNG:
				$src = @imagecreatefrompng($path);
				break;
			case IMAGETYPE_GIF:
				$src = @imagecreatefromgif($path);
				break;
			case IMAGETYPE_WEBP:
				if (function_exists('imagecreatefromwebp')) {
					$src = @imagecreatefromwebp($path);
				}
				break;
		}
		if (!$src) {
			return null;
		}
		imagealphablending($src, true);
		imagesavealpha($src, true);
		return $src;
	}

	private function profilePath($stored): ?string
	{
		$base = null;
		if (function_exists('resolve_profile_photo')) {
			$base = resolve_profile_photo(is_string($stored) ? $stored : null);
		} elseif (is_string($stored) && $stored !== '') {
			$base = basename(str_replace(["\0", '\\'], '', $stored));
		}
		if ($base === null || $base === '') {
			return null;
		}
		return $this->assetPath('assets/images/profile/' . $base);
	}

	private function assetPath(string $relative): ?string
	{
		if (function_exists('asset_resolve_path')) {
			return asset_resolve_path($relative, null);
		}
		$base = defined('FCPATH') ? rtrim(FCPATH, '/\\') : (defined('ROOTPATH') ? rtrim(ROOTPATH, '/\\') . DIRECTORY_SEPARATOR . 'public' : '');
		$path = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($relative, '/'));
		return is_file($path) ? $path : null;
	}

	private function upper(string $v): string
	{
		$v = trim($v);
		if ($v === '') {
			return '';
		}
		return function_exists('mb_strtoupper') ? mb_strtoupper($v, 'UTF-8') : strtoupper($v);
	}

	public static function resolveFont(): string
	{
		$fcpath = defined('FCPATH') ? rtrim(FCPATH, '/\\') : '';
		$cands = [
			$fcpath . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'fonts' . DIRECTORY_SEPARATOR . 'DejaVuSans-Bold.ttf',
			'/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
			'/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
			'C:\\Windows\\Fonts\\arialbd.ttf',
			'C:\\Windows\\Fonts\\ARIALBD.TTF',
			'C:\\Windows\\Fonts\\calibrib.ttf',
		];
		foreach ($cands as $p) {
			if ($p !== '' && is_file($p)) {
				return $p;
			}
		}
		return '';
	}
}
