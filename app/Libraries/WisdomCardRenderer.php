<?php

namespace App\Libraries;

/**
 * Rasterize Wisdom landscape student IDs with GD.
 * wkhtmltopdf cannot clip border-radius, so photo/logo circles and text
 * are painted onto a 1712×1080 bitmap (20 px/mm CR80) then embedded in PDF.
 */
class WisdomCardRenderer
{
	public const W = 1712;
	public const H = 1080;

	private const TEAL = [0, 130, 142];
	private const NAVY = [4, 73, 107];
	private const BG = [248, 248, 248];

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

		$im = $this->baseCanvas();
		imagealphablending($im, true);

		$white = imagecolorallocate($im, 255, 255, 255);
		$navy = imagecolorallocate($im, self::NAVY[0], self::NAVY[1], self::NAVY[2]);
		$teal = imagecolorallocate($im, self::TEAL[0], self::TEAL[1], self::TEAL[2]);

		$schoolName = $this->upper(CardLayout::wisdomCardSchoolName($student));
		$fullName = $this->upper(trim((string) ($student['name'] ?? '')));
		$classLabel = $this->upper(trim((string) ($student['class'] ?? '')));
		$year = $this->upper(CardLayout::formatAcademicYear((string) ($ctx['year'] ?? '')));
		$idNo = trim((string) ($student['regno'] ?? ''));
		if ($idNo === '') {
			$idNo = trim((string) ($student['card'] ?? ''));
		}
		$idNo = $this->upper($idNo);

		$this->drawSchoolName($im, $schoolName, $white);
		$this->drawBadge($im, 'STUDENT ID CARD', $white);
		$this->drawInfo($im, $fullName, $classLabel, $year, $navy);
		$this->drawIdBar($im, $idNo !== '' ? $idNo : '—', $white);

		$logoPath = $this->logoPath((string) ($ctx['logo'] ?? ''));
		$this->pasteLogo($im, $logoPath, $teal, $white);
		$this->pastePhoto($im, $photoPath, $teal);

		return $im;
	}

	/** @return resource|\GdImage */
	private function baseCanvas()
	{
		$chrome = $this->assetPath(CardLayout::WISDOM_CHROME);
		if ($chrome !== null) {
			$src = $this->loadImage($chrome);
			if ($src) {
				if (imagesx($src) === self::W && imagesy($src) === self::H) {
					return $src;
				}
				$im = imagecreatetruecolor(self::W, self::H);
				imagecopyresampled($im, $src, 0, 0, 0, 0, self::W, self::H, imagesx($src), imagesy($src));
				imagedestroy($src);
				return $im;
			}
		}
		return $this->drawChrome();
	}

	/** @return resource|\GdImage */
	private function drawChrome()
	{
		$im = imagecreatetruecolor(self::W, self::H);
		$bg = imagecolorallocate($im, self::BG[0], self::BG[1], self::BG[2]);
		$teal = imagecolorallocate($im, self::TEAL[0], self::TEAL[1], self::TEAL[2]);
		$navy = imagecolorallocate($im, self::NAVY[0], self::NAVY[1], self::NAVY[2]);
		imagefill($im, 0, 0, $bg);

		$this->fillPoly($im, [[0, 5.8], [56.2, 5.2], [54.0, 8.2], [0, 8.6]], $navy);
		$this->fillPoly($im, [[0, 5.8], [6.2, 5.8], [0.4, 23.6], [0, 23.6]], $navy);
		$this->fillPoly($im, [[8.3, 0], [22.9, 0], [20.4, 8.2], [5.3, 8.2]], $teal);
		$this->fillPoly($im, [[5.3, 8.0], [91.1, 8.0], [85.6, 23.6], [0.0, 23.6]], $teal);
		$this->fillPoly($im, [[16.8, 8.0], [21.9, 8.0], [16.7, 23.6], [14.8, 23.6]], $navy);
		$this->fillPoly($im, [[37.2, 36.2], [76.8, 36.2], [72.6, 47.4], [40.6, 47.4]], $navy);
		$this->fillPoly($im, [[0, 79.5], [7.0, 79.5], [1.4, 96.2], [0, 96.2]], $navy);
		$this->fillPoly($im, [[5.2, 84.8], [39.6, 84.8], [35.8, 95.4], [1.6, 95.4]], $teal);
		$this->fillPoly($im, [[40.6, 84.8], [41.7, 84.8], [38.1, 95.4], [37.0, 95.4]], $teal);
		$this->fillPoly($im, [[43.0, 84.8], [44.9, 84.8], [41.2, 95.4], [39.3, 95.4]], $teal);
		$this->fillPoly($im, [[46.3, 84.8], [48.9, 84.8], [45.2, 95.4], [42.6, 95.4]], $teal);
		return $im;
	}

	/**
	 * @param array<int,array{0:float,1:float}> $pctPts
	 * @param resource|\GdImage $im
	 */
	private function fillPoly($im, array $pctPts, int $color): void
	{
		$xy = [];
		foreach ($pctPts as $p) {
			$xy[] = (int) round($p[0] / 100.0 * self::W);
			$xy[] = (int) round($p[1] / 100.0 * self::H);
		}
		$n = (int) (count($xy) / 2);
		if (PHP_VERSION_ID >= 80000) {
			imagefilledpolygon($im, $xy, $color);
		} else {
			imagefilledpolygon($im, $xy, $n, $color);
		}
	}

	/** @param resource|\GdImage $im */
	private function drawSchoolName($im, string $text, int $white): void
	{
		if ($text === '') {
			return;
		}
		// Teal banner inner area — after the logo, before the right slant.
		$x = (int) round(self::W * 0.205);
		$y = (int) round(self::H * 0.082);
		$w = (int) round(self::W * 0.68);
		$h = (int) round(self::H * 0.142);
		$size = $this->fitSize($text, $w, (int) round($h * 0.78), 104, 28);
		$this->drawCentered($im, $text, $size, $x, $y, $w, $h, $white);
	}

	/** @param resource|\GdImage $im */
	private function drawBadge($im, string $text, int $white): void
	{
		$x = (int) round(self::W * 0.395);
		$y = (int) round(self::H * 0.362);
		$w = (int) round(self::W * 0.32);
		$h = (int) round(self::H * 0.108);
		$size = $this->fitSize($text, $w, (int) round($h * 0.58), 36, 16);
		$this->drawCentered($im, $text, $size, $x, $y, $w, $h, $white);
	}

	/** @param resource|\GdImage $im */
	private function drawInfo($im, string $name, string $class, string $year, int $navy): void
	{
		$x = (int) round(self::W * 0.395);
		$y = (int) round(self::H * 0.495);
		$w = (int) round(self::W * 0.575);
		$rowH = (int) round(self::H * 0.082);
		$rows = [
			['NAME', $name !== '' ? $name : '—'],
			['CLASS', $class !== '' ? $class : '—'],
			['ACADEMIC YEAR', $year !== '' ? $year : '—'],
		];
		$labelSize = 28.0;
		$labelW = 0;
		foreach ($rows as $row) {
			$m = $this->measure($labelSize, $row[0]);
			if ($m['w'] > $labelW) {
				$labelW = $m['w'];
			}
		}
		$colon = ' :';
		$colonW = $this->measure($labelSize, $colon)['w'];
		$gap = (int) round(self::W * 0.012);
		$valueX = $x + $labelW + $colonW + $gap;
		$valueMaxW = max(40, $x + $w - $valueX);

		foreach ($rows as $i => $row) {
			$rowY = $y + $i * $rowH;
			$this->drawText($im, $row[0], $labelSize, $x, $rowY, $labelW + 4, $rowH, $navy, 'left');
			$this->drawText($im, $colon, $labelSize, $x + $labelW, $rowY, $colonW + 4, $rowH, $navy, 'left');
			$valSize = $this->fitSize($row[1], $valueMaxW, (int) round($rowH * 0.62), $labelSize, 16);
			$this->drawText($im, $row[1], $valSize, $valueX, $rowY, $valueMaxW, $rowH, $navy, 'left');
		}
	}

	/** @param resource|\GdImage $im */
	private function drawIdBar($im, string $idNo, int $white): void
	{
		$text = 'ID NO: ' . $idNo;
		$x = (int) round(self::W * 0.055);
		$y = (int) round(self::H * 0.848);
		$w = (int) round(self::W * 0.32);
		$h = (int) round(self::H * 0.102);
		$size = $this->fitSize($text, $w, (int) round($h * 0.52), 30, 14);
		$this->drawCentered($im, $text, $size, $x, $y, $w, $h, $white);
	}

	/**
	 * @param resource|\GdImage $im
	 */
	private function pasteLogo($im, ?string $path, int $teal, int $white): void
	{
		$d = (int) round(self::H * 0.248);
		$cx = (int) round(self::W * 0.118);
		$cy = (int) round(self::H * 0.155);
		$ring = (int) round(self::W * 0.0045);
		imagefilledellipse($im, $cx, $cy, $d + $ring * 2, $d + $ring * 2, $teal);
		imagefilledellipse($im, $cx, $cy, $d, $d, $white);

		$src = $path ? $this->loadImage($path) : null;
		$circle = $src ? $this->containCircle($src, $d - 8) : null;
		if ($src) {
			imagedestroy($src);
		}
		if ($circle) {
			$cd = imagesx($circle);
			imagecopy($im, $circle, $cx - (int) ($cd / 2), $cy - (int) ($cd / 2), 0, 0, $cd, $cd);
			imagedestroy($circle);
		}
	}

	/**
	 * @param resource|\GdImage $im
	 */
	private function pastePhoto($im, string $path, int $teal): void
	{
		$d = (int) round(min(self::W * 0.252, self::H * 0.40));
		$cx = (int) round(self::W * 0.066 + $d / 2);
		$cy = (int) round(self::H * 0.338 + $d / 2);
		$ring = (int) round(self::W * 0.0175);

		$src = $this->loadImage($path);
		if (!$src) {
			return;
		}
		$circle = $this->coverCircle($src, $d, 0.28);
		imagedestroy($src);
		if (!$circle) {
			return;
		}

		imagefilledellipse($im, $cx, $cy, $d + $ring * 2, $d + $ring * 2, $teal);
		$cd = imagesx($circle);
		imagecopy($im, $circle, $cx - (int) ($cd / 2), $cy - (int) ($cd / 2), 0, 0, $cd, $cd);
		imagedestroy($circle);
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
		$sq = $this->truecolor($size, $size, false);
		imagecopyresampled($sq, $src, 0, 0, $sx, $sy, $size, $size, $side, $side);
		$this->applyCircleMask($sq);
		return $sq;
	}

	/**
	 * @param resource|\GdImage $src
	 * @return resource|\GdImage|null
	 */
	private function containCircle($src, int $size)
	{
		$sw = imagesx($src);
		$sh = imagesy($src);
		if ($sw < 2 || $sh < 2) {
			return null;
		}
		$scale = min($size / $sw, $size / $sh);
		$nw = max(1, (int) round($sw * $scale));
		$nh = max(1, (int) round($sh * $scale));
		$ox = (int) floor(($size - $nw) / 2);
		$oy = (int) floor(($size - $nh) / 2);
		$sq = $this->truecolor($size, $size, true);
		imagealphablending($sq, true);
		imagecopyresampled($sq, $src, $ox, $oy, 0, 0, $nw, $nh, $sw, $sh);
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
			? $x + (int) round(($w - $m['w']) / 2) - $m['box'][0]
			: $x - $m['box'][0];
		$ty = $y + (int) round(($h - ($m['box'][1] + $m['box'][7])) / 2);
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

	private function logoPath(string $logo): ?string
	{
		$logo = trim($logo);
		if ($logo !== '') {
			$p = $this->assetPath('assets/images/logo/' . basename($logo));
			if ($p) {
				return $p;
			}
		}
		return $this->assetPath('assets/images/fallback-logo.png');
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
