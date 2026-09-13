<?php

namespace App\Libraries;

/**
 * Rasterize Wisdom staff ID cards onto campus artwork (Musanze or Rwanda).
 * Artwork is used as a screenshot (full-bleed). Photo sits inside the
 * existing circle. Staff fields are drawn on the white face only.
 * No colored overlays, rings, badges, or bars are painted.
 */
class WisdomStaffCardRenderer
{
	/** CR80 portrait at 20 px/mm */
	public const W = 1080;
	public const H = 1712;

	public const TEMPLATE_MUSANZE = 'assets/images/background/wisdom_staff_card_musanze.png';
	public const TEMPLATE_RWANDA = 'assets/images/background/wisdom_staff_card_rwanda.png';
	/** Default / campus card (backward compatible). */
	public const TEMPLATE = self::TEMPLATE_MUSANZE;

	/** Principal, Director of Finance, Director, Deputy Director. */
	public const RWANDA_ART_POST_IDS = [15, 24, 29, 30];

	/** Measured on 591×1004 source artwork. */
	private const SRC_W = 591;
	private const SRC_H = 1004;
	private const HOLE_CX = 296;
	private const HOLE_CY = 312;
	private const HOLE_D = 274;

	private const NAVY = [8, 32, 96];

	/** @var string */
	private $font;

	public function __construct(?string $fontPath = null)
	{
		$this->font = $fontPath ?: WisdomCardRenderer::resolveFont();
	}

	public static function isAvailable(): bool
	{
		return function_exists('imagecreatetruecolor')
			&& function_exists('imagettftext')
			&& is_file(WisdomCardRenderer::resolveFont())
			&& (self::assetPath(self::TEMPLATE_MUSANZE) !== null
				|| self::assetPath(self::TEMPLATE_RWANDA) !== null);
	}

	/**
	 * Leadership cards use the Wisdom Schools Rwanda artwork.
	 *
	 * @param array<string,mixed> $staff
	 */
	public static function usesRwandaArtwork(array $staff): bool
	{
		$postId = (int) ($staff['post'] ?? 0);
		if (in_array($postId, self::RWANDA_ART_POST_IDS, true)) {
			return true;
		}
		$title = self::normTitle((string) ($staff['post_title'] ?? ''));
		return in_array($title, [
			'deputy director',
			'director',
			'director of finance',
			'director of finances',
			'principal',
		], true);
	}

	/**
	 * @param array<string,mixed> $staff
	 */
	public static function templateForStaff(array $staff): string
	{
		if (self::usesRwandaArtwork($staff) && self::assetPath(self::TEMPLATE_RWANDA) !== null) {
			return self::TEMPLATE_RWANDA;
		}
		if (self::assetPath(self::TEMPLATE_MUSANZE) !== null) {
			return self::TEMPLATE_MUSANZE;
		}
		return self::TEMPLATE_RWANDA;
	}

	/**
	 * @param array<string,mixed> $staff
	 * @param array<string,mixed> $ctx
	 */
	public function renderJpeg(array $staff, array $ctx = []): ?string
	{
		$im = $this->render($staff, $ctx);
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
	 * @param array<string,mixed> $staff
	 * @param array<string,mixed> $ctx
	 * @return resource|\GdImage|null
	 */
	public function render(array $staff, array $ctx = [])
	{
		if (!function_exists('imagecreatetruecolor') || !is_file($this->font)) {
			return null;
		}
		$im = $this->baseFromTemplate(self::templateForStaff($staff));
		if ($im === null) {
			return null;
		}
		imagealphablending($im, true);

		$photoPath = $this->profilePath($staff['photo'] ?? '');
		if ($photoPath !== null) {
			$this->pastePhoto($im, $photoPath);
		}

		$navy = imagecolorallocate($im, self::NAVY[0], self::NAVY[1], self::NAVY[2]);
		$this->drawStaffInfo($im, $staff, $ctx, $navy);
		return $im;
	}

	/**
	 * @param resource|\GdImage $im
	 */
	private function pastePhoto($im, string $path): void
	{
		$cx = $this->sx(self::HOLE_CX);
		$cy = $this->sy(self::HOLE_CY);
		// Slightly inside the white hole so the artwork ring stays visible.
		$d = (int) max(2, round($this->sx(self::HOLE_D) * 0.96));

		$src = $this->loadImage($path);
		if (!$src) {
			return;
		}
		$square = $this->coverSquare($src, $d, 0.22);
		imagedestroy($src);
		if (!$square) {
			return;
		}

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

	/**
	 * @param resource|\GdImage $im
	 * @param array<string,mixed> $staff
	 * @param array<string,mixed> $ctx
	 */
	private function drawStaffInfo($im, array $staff, array $ctx, int $navy): void
	{
		$name = $this->upper(trim((string) (($staff['fname'] ?? '') . ' ' . ($staff['lname'] ?? ''))));
		$post = $this->upper(trim((string) ($staff['post_title'] ?? '')));
		$phone = trim((string) ($staff['phone'] ?? ''));
		$email = trim((string) ($staff['email'] ?? ''));
		$staffId = trim((string) ($staff['id'] ?? ''));
		$card = strtoupper(trim((string) ($staff['card'] ?? '')));
		$address = trim((string) ($staff['address'] ?? ''));
		$year = trim((string) ($ctx['year'] ?? ''));
		if ($year !== '') {
			$year = CardLayout::formatAcademicYear($year);
			$year = preg_replace('#/+TERM\s*\d*$#i', '', $year) ?? $year;
			$year = rtrim($year, '/');
		}

		$nameX = $this->sx(40);
		$nameW = $this->sx(511);
		$nameY = $this->sy(468);
		$nameH = $this->sy(52);
		if ($name !== '') {
			$size = $this->fitSize($name, $nameW, (int) round($nameH * 0.86), 36, 16);
			$this->drawText($im, $name, $size, $nameX, $nameY, $nameW, $nameH, $navy, 'center');
		}
		if ($post !== '') {
			$size = $this->fitSize($post, $nameW, $this->sy(36), 22, 12);
			$this->drawText($im, $post, $size, $nameX, $this->sy(518), $nameW, $this->sy(40), $navy, 'center');
		}

		$rows = [];
		if ($phone !== '') {
			$rows[] = ['Phone', $phone];
		}
		if ($email !== '') {
			$rows[] = ['Email', $email];
		}
		if ($staffId !== '') {
			$rows[] = ['Staff ID', $staffId];
		}
		if ($card !== '') {
			$rows[] = ['Card', $card];
		}
		if ($address !== '') {
			$rows[] = ['Address', $this->upper($address)];
		}
		if ($year !== '') {
			$rows[] = ['Issued', $year];
		}

		$labelX = $this->sx(52);
		$colonX = $this->sx(200);
		$valueX = $this->sx(220);
		$valueW = $this->sx(320);
		$rowH = $this->sy(38);
		$y = $this->sy(575);
		foreach ($rows as $row) {
			[$lab, $val] = $row;
			$this->drawText($im, $lab, 15.0, $labelX, $y, $this->sx(128), $rowH, $navy, 'left');
			$this->drawText($im, ':', 15.0, $colonX, $y, $this->sx(16), $rowH, $navy, 'left');
			$size = $this->fitSize($val, $valueW, (int) round($rowH * 0.78), 16, 10);
			$this->drawText($im, $val, $size, $valueX, $y, $valueW, $rowH, $navy, 'left');
			$y += $rowH;
		}
	}

	/** @return resource|\GdImage|null */
	private function baseFromTemplate(string $relative)
	{
		$path = self::assetPath($relative);
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

	/**
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
		$zoom = 0.98;
		$crop = max(1, (int) round($side * $zoom));
		$sx += (int) round(($side - $crop) / 2);
		$sy += (int) round(($side - $crop) * 0.28);
		$sx = max(0, min($sx, $sw - $crop));
		$sy = max(0, min($sy, $sh - $crop));
		$sq = imagecreatetruecolor($size, $size);
		imagecopyresampled($sq, $src, 0, 0, $sx, $sy, $size, $size, $crop, $crop);
		return $sq;
	}

	/** @param resource|\GdImage $im */
	private function drawText($im, string $text, float $size, int $x, int $y, int $w, int $h, int $color, string $align): void
	{
		$m = $this->measure($size, $text);
		$tx = $align === 'center'
			? $x + (int) round(($w - $m['w']) / 2) - (int) $m['box'][0]
			: $x - (int) $m['box'][0];
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
			$size -= 0.6;
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
		return self::assetPath('assets/images/profile/' . $base);
	}

	private static function assetPath(string $relative): ?string
	{
		if (function_exists('asset_resolve_path')) {
			return asset_resolve_path($relative, null);
		}
		$base = defined('FCPATH') ? rtrim(FCPATH, '/\\') : (defined('ROOTPATH') ? rtrim(ROOTPATH, '/\\') . DIRECTORY_SEPARATOR . 'public' : '');
		$path = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($relative, '/'));
		return is_file($path) ? $path : null;
	}

	private function sx(float $x): int
	{
		return (int) round($x / self::SRC_W * self::W);
	}

	private function sy(float $y): int
	{
		return (int) round($y / self::SRC_H * self::H);
	}

	private function upper(string $v): string
	{
		$v = trim($v);
		if ($v === '') {
			return '';
		}
		return function_exists('mb_strtoupper') ? mb_strtoupper($v, 'UTF-8') : strtoupper($v);
	}

	private static function normTitle(string $title): string
	{
		$title = strtolower(str_replace(['.', '_', '-'], ' ', $title));
		$title = preg_replace('/\s+/', ' ', $title) ?? $title;
		return trim($title);
	}
}
