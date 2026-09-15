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

	/** Executive Principal, Director of Finance, Director, Deputy Director. */
	public const RWANDA_ART_POST_IDS = [15, 24, 29, 30];

	/** Measured on 591×1004 source artwork. */
	private const SRC_W = 591;
	private const SRC_H = 1004;
	private const HOLE_CX = 296;
	private const HOLE_CY = 312;
	private const HOLE_D = 274;

	private const NAVY = [8, 32, 96];
	private const MUTED = [70, 96, 150];
	private const GOLD = [196, 154, 48];

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
			'executive principal',
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
		// Artwork is stretched to CR80, so the printed hole is an ellipse.
		// Use the smaller axis so the photo stays inside the ring and centered.
		$d = (int) max(2, round(min($this->sx(self::HOLE_D), $this->sy(self::HOLE_D)) * 0.98));

		$src = $this->loadImage($path);
		if (!$src) {
			return;
		}
		$square = $this->coverSquare($src, $d, 0.08);
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
		$muted = imagecolorallocate($im, self::MUTED[0], self::MUTED[1], self::MUTED[2]);
		$gold = imagecolorallocate($im, self::GOLD[0], self::GOLD[1], self::GOLD[2]);
		$white = imagecolorallocate($im, 255, 255, 255);

		$name = $this->upper($this->cleanName((string) (($staff['fname'] ?? '') . ' ' . ($staff['lname'] ?? ''))));
		$post = $this->upper(trim((string) ($staff['post_title'] ?? '')));
		$phone = $this->formatPhone(trim((string) ($staff['phone'] ?? '')));
		$email = strtolower(trim((string) ($staff['email'] ?? '')));
		$staffId = trim((string) ($staff['id'] ?? ''));

		$boxX = $this->sx(36);
		$boxW = $this->sx(519);
		if ($name !== '') {
			$size = $this->fitSize($name, $boxW, $this->sy(56), 44, 18);
			$this->drawText($im, $name, $size, $boxX, $this->sy(480), $boxW, $this->sy(58), $navy, 'center');
		}
		if ($post !== '') {
			$this->drawPostBadge($im, $post, $this->sy(542), $navy, $white, $gold);
		}

		$rows = [];
		if ($phone !== '') {
			$rows[] = ['PHONE', $phone];
		}
		if ($email !== '') {
			$rows[] = ['EMAIL', $email];
		}
		if ($staffId !== '') {
			$rows[] = ['STAFF ID', $staffId];
		}
		$this->drawContactPills($im, $rows, $navy, $muted, $gold);
	}

	/**
	 * Soft navy capsules for phone / email / staff id — grouped, no underlines.
	 *
	 * @param resource|\GdImage $im
	 * @param list<array{0:string,1:string}> $rows
	 */
	private function drawContactPills($im, array $rows, int $navy, int $muted, int $gold): void
	{
		if ($rows === []) {
			return;
		}
		$fill = imagecolorallocate($im, 236, 241, 250);
		$x = $this->sx(40);
		$w = $this->sx(511);
		$rowH = $this->sy(50);
		$gap = $this->sy(9);
		$y = $this->sy(592);
		$limitY = $this->sy(800);
		$padL = $this->sx(28);
		$padR = $this->sx(22);
		$labelW = $this->sx(132);
		$dot = (int) max(5, round($this->sx(4.5)));
		foreach ($rows as $row) {
			if ($y + $rowH > $limitY) {
				break;
			}
			[$lab, $val] = $row;
			$this->fillRoundRect($im, $x, $y, $w, $rowH, (int) round($rowH / 2), $fill);
			imagefilledellipse(
				$im,
				$x + $this->sx(16),
				$y + (int) round($rowH / 2),
				$dot,
				$dot,
				$gold
			);
			$textX = $x + $padL;
			$this->drawTrackedText($im, $lab, 12.2, 1.7, $textX, $y, $labelW, $rowH, $muted, 'left');
			$valX = $textX + $labelW + $this->sx(8);
			$valW = ($x + $w) - $valX - $padR;
			$size = $this->fitSize($val, max(20, $valW), (int) round($rowH * 0.70), 22, 13);
			$this->drawText($im, $val, $size, $valX, $y, $valW, $rowH, $navy, 'right');
			$y += $rowH + $gap;
		}
	}

	/**
	 * @param resource|\GdImage $im
	 */
	private function drawPostBadge($im, string $post, int $y, int $navy, int $white, int $gold): void
	{
		$maxW = $this->sx(520);
		$size = $this->fitSize($post, (int) round($maxW * 0.84), $this->sy(28), 18.5, 11);
		$m = $this->measure($size, $post);
		$padX = $this->sx(30);
		$h = $this->sy(42);
		$w = min($maxW, $m['w'] + ($padX * 2));
		$x = (int) round((self::W - $w) / 2);
		$this->fillRoundRect($im, $x, $y, $w, $h, (int) round($h / 2), $navy);
		$this->drawText($im, $post, $size, $x, $y, $w, $h, $white, 'center');
		$dot = (int) max(3, round($this->sx(3)));
		imagefilledellipse($im, $x + $this->sx(10), $y + (int) round($h / 2), $dot, $dot, $gold);
		imagefilledellipse($im, $x + $w - $this->sx(10), $y + (int) round($h / 2), $dot, $dot, $gold);
	}

	/**
	 * @param resource|\GdImage $im
	 */
	private function fillRoundRect($im, int $x, int $y, int $w, int $h, int $r, int $color): void
	{
		$r = max(1, min($r, (int) floor($w / 2), (int) floor($h / 2)));
		imagefilledrectangle($im, $x + $r, $y, $x + $w - $r, $y + $h, $color);
		imagefilledrectangle($im, $x, $y + $r, $x + $w, $y + $h - $r, $color);
		imagefilledellipse($im, $x + $r, $y + $r, $r * 2, $r * 2, $color);
		imagefilledellipse($im, $x + $w - $r, $y + $r, $r * 2, $r * 2, $color);
		imagefilledellipse($im, $x + $r, $y + $h - $r, $r * 2, $r * 2, $color);
		imagefilledellipse($im, $x + $w - $r, $y + $h - $r, $r * 2, $r * 2, $color);
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
			$sx = (int) max(0, (int) round(($sw - $sh) / 2));
			$sy = 0;
		} else {
			$side = $sw;
			$sx = 0;
			$maxShift = max(0, $sh - $sw);
			$sy = (int) round($maxShift * $biasY);
			$sy = max(0, min($sy, $maxShift));
		}
		$side = max(1, min($side, $sw - $sx, $sh - $sy));
		$sq = imagecreatetruecolor($size, $size);
		imagecopyresampled($sq, $src, 0, 0, $sx, $sy, $size, $size, $side, $side);
		return $sq;
	}

	/** @param resource|\GdImage $im */
	private function drawText($im, string $text, float $size, int $x, int $y, int $w, int $h, int $color, string $align): void
	{
		$m = $this->measure($size, $text);
		if ($align === 'center') {
			$tx = $x + (int) round(($w - $m['w']) / 2) - (int) $m['box'][0];
		} elseif ($align === 'right') {
			$tx = $x + $w - $m['w'] - (int) $m['box'][0];
		} else {
			$tx = $x - (int) $m['box'][0];
		}
		$textH = max(1, $m['h']);
		$ty = $y + (int) round(($h - $textH) / 2) + (int) abs($m['box'][7]);
		imagettftext($im, $size, 0, $tx, $ty, $color, $this->font, $text);
	}

	/**
	 * @param resource|\GdImage $im
	 */
	private function drawTrackedText($im, string $text, float $size, float $track, int $x, int $y, int $w, int $h, int $color, string $align): void
	{
		$chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
		if (!is_array($chars) || count($chars) === 0) {
			return;
		}
		$widths = [];
		$total = 0;
		foreach ($chars as $ch) {
			$mw = $this->measure($size, $ch)['w'];
			$widths[] = $mw;
			$total += $mw;
		}
		$total += (int) round($track * max(0, count($chars) - 1));
		if ($align === 'center') {
			$cx = $x + (int) round(($w - $total) / 2);
		} elseif ($align === 'right') {
			$cx = $x + $w - $total;
		} else {
			$cx = $x;
		}
		$m = $this->measure($size, $text);
		$textH = max(1, $m['h']);
		$ty = $y + (int) round(($h - $textH) / 2) + (int) abs($m['box'][7]);
		foreach ($chars as $i => $ch) {
			imagettftext($im, $size, 0, $cx, $ty, $color, $this->font, $ch);
			$cx += $widths[$i] + (int) round($track);
		}
	}

	private function cleanName(string $name): string
	{
		$name = preg_replace('/\s+/u', ' ', trim($name)) ?? trim($name);
		return $name;
	}

	private function formatPhone(string $phone): string
	{
		$raw = trim($phone);
		if ($raw === '') {
			return '';
		}
		$digits = preg_replace('/\D+/', '', $raw) ?? '';
		if (strlen($digits) === 12 && substr($digits, 0, 3) === '250') {
			$digits = '0' . substr($digits, 3);
		} elseif (strlen($digits) === 11 && substr($digits, 0, 2) === '25') {
			$digits = '0' . substr($digits, 2);
		}
		if (strlen($digits) === 10 && $digits[0] === '0') {
			return substr($digits, 0, 4) . ' ' . substr($digits, 4, 3) . ' ' . substr($digits, 7);
		}
		if (strlen($digits) === 11 && $digits[0] === '0') {
			return substr($digits, 0, 3) . ' ' . substr($digits, 3, 3) . ' ' . substr($digits, 6, 3) . ' ' . substr($digits, 9);
		}
		if (strlen($digits) === 9) {
			return '0' . substr($digits, 0, 3) . ' ' . substr($digits, 3, 3) . ' ' . substr($digits, 6);
		}
		return $raw;
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
