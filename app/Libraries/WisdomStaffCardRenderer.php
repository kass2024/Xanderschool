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
	public const TEMPLATE_BURERA = 'assets/images/background/wisdom_staff_card_burera.png';
	public const TEMPLATE_KANZENZE = 'assets/images/background/wisdom_staff_card_kanzenze.png';
	public const TEMPLATE_NGORORERO = 'assets/images/background/wisdom_staff_card_ngororero.png';
	public const TEMPLATE_NYABIHU = 'assets/images/background/wisdom_staff_card_nyabihu.png';
	public const TEMPLATE_SUSA = 'assets/images/background/wisdom_staff_card_susa.png';
	/** Default / campus card (backward compatible). */
	public const TEMPLATE = self::TEMPLATE_MUSANZE;

	/** Executive Principal, Director of Finance, Chief Accountant, Director, Deputy Director. */
	public const RWANDA_ART_POST_IDS = [15, 24, 28, 29, 30];

	/** Measured on 591×1004 source artwork. */
	private const SRC_W = 591;
	private const SRC_H = 1004;
	private const HOLE_CX = 296;
	private const HOLE_CY = 312;
	private const HOLE_D = 274;

	private const NAVY = [8, 32, 96];
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
			'chief accountant',
			'deputy director',
			'director',
			'director of finance',
			'director of finances',
			'executive principal',
			'principal',
		], true);
	}

	/**
	 * Printed staff number on the Wisdom master card.
	 *
	 * @param array<string,mixed> $staff
	 */
	public static function cardStaffId(array $staff, string $schoolName = ''): string
	{
		$real = trim((string) ($staff['id'] ?? ''));
		if (!self::isMasterSchool($schoolName !== '' ? $schoolName : (string) ($staff['school_name'] ?? ''))) {
			return $real;
		}
		$byPost = [
			29 => '1',
			30 => '3',
			15 => '4',
			24 => '5',
			28 => '6',
		];
		$postId = (int) ($staff['post'] ?? 0);
		if (isset($byPost[$postId])) {
			return $byPost[$postId];
		}
		$title = self::normTitle((string) ($staff['post_title'] ?? ''));
		$byTitle = [
			'deputy director' => '3',
			'director of finance' => '5',
			'director of finances' => '5',
			'executive principal' => '4',
			'principal' => '4',
			'chief accountant' => '6',
			'director' => '1',
		];
		return $byTitle[$title] ?? $real;
	}

	public static function isMasterSchool(string $schoolName): bool
	{
		$name = strtoupper(preg_replace('/\s+/', ' ', trim($schoolName)) ?? '');
		if ($name === '' || strpos($name, 'WISDOM') === false) {
			return false;
		}
		foreach (['BURERA', 'FUMBWE', 'KABARORE', 'KANZENZE', 'KAYONZA', 'KIRAMURUZI', 'MUSANZE', 'MUYUMBU', 'NGORORERO', 'NYABIHU', 'NYAMASHEKE', 'RUBAVU', 'RUBENGERA', 'RUNDA', 'SUSA'] as $campus) {
			if (strpos($name, $campus) !== false) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @param array<string,mixed> $staff
	 */
	public static function templateForStaff(array $staff, string $schoolName = ''): string
	{
		if (self::usesRwandaArtwork($staff) && self::assetPath(self::TEMPLATE_RWANDA) !== null) {
			return self::TEMPLATE_RWANDA;
		}
		$campus = self::campusTemplate($schoolName !== '' ? $schoolName : (string) ($staff['school_name'] ?? ''));
		if ($campus !== null) {
			return $campus;
		}
		if (self::assetPath(self::TEMPLATE_MUSANZE) !== null) {
			return self::TEMPLATE_MUSANZE;
		}
		return self::TEMPLATE_RWANDA;
	}

	public static function campusTemplate(string $schoolName): ?string
	{
		$name = strtoupper(preg_replace('/\s+/', ' ', trim($schoolName)) ?? '');
		$map = [
			'BURERA' => self::TEMPLATE_BURERA,
			'KANZENZE' => self::TEMPLATE_KANZENZE,
			'NGORORERO' => self::TEMPLATE_NGORORERO,
			'NYABIHU' => self::TEMPLATE_NYABIHU,
			'SUSA' => self::TEMPLATE_SUSA,
		];
		foreach ($map as $key => $path) {
			if ($name !== '' && strpos($name, $key) !== false && self::assetPath($path) !== null) {
				return $path;
			}
		}
		return null;
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
		$im = $this->baseFromTemplate(self::templateForStaff($staff, (string) ($ctx['school_name'] ?? '')));
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
		// Stay inside the black inner line so the blue artwork ring stays visible.
		$d = (int) max(2, round(min($this->sx(self::HOLE_D), $this->sy(self::HOLE_D)) * 0.93));

		$src = $this->loadImage($path);
		if (!$src) {
			return;
		}
		$square = $this->fitSubjectInCircle($src, $d);
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
		$gold = imagecolorallocate($im, self::GOLD[0], self::GOLD[1], self::GOLD[2]);
		$white = imagecolorallocate($im, 255, 255, 255);

		$name = $this->upper($this->cleanName((string) (($staff['fname'] ?? '') . ' ' . ($staff['lname'] ?? ''))));
		$post = $this->upper(trim((string) ($staff['post_title'] ?? '')));
		$phone = $this->formatPhone(trim((string) ($staff['phone'] ?? '')));
		$email = strtolower(trim((string) ($staff['email'] ?? '')));
		$staffId = self::cardStaffId($staff, (string) ($ctx['school_name'] ?? ''));

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
			$rows[] = ['PHONE', $phone, 'phone'];
		}
		if ($email !== '') {
			$rows[] = ['EMAIL', $email, 'email'];
		}
		if ($staffId !== '') {
			$rows[] = ['STAFF ID', $staffId, 'id'];
		}
		$this->drawContactPills($im, $rows, $navy);
	}

	/**
	 * Icon + label | value pills. Values sit left of the remaining space.
	 *
	 * @param resource|\GdImage $im
	 * @param list<array{0:string,1:string,2:string}> $rows
	 */
	private function drawContactPills($im, array $rows, int $navy): void
	{
		if ($rows === []) {
			return;
		}
		$fill = imagecolorallocate($im, 228, 236, 248);
		$disc = imagecolorallocate($im, 244, 247, 252);
		$split = imagecolorallocate($im, 176, 192, 216);
		$x = $this->sx(36);
		$w = $this->sx(519);
		$rowH = $this->sy(54);
		$gap = $this->sy(8);
		$y = $this->sy(594);
		$limitY = $this->sy(810);
		$radius = (int) round($rowH * 0.42);
		$discD = (int) max(22, round($rowH * 0.68));
		$labelW = $this->sx(118);
		foreach ($rows as $row) {
			if ($y + $rowH > $limitY) {
				break;
			}
			[$lab, $val, $kind] = $row;
			$this->fillRoundRect($im, $x, $y, $w, $rowH, $radius, $fill);
			$cx = $x + $this->sx(28);
			$cy = $y + (int) round($rowH / 2);
			imagefilledellipse($im, $cx, $cy, $discD, $discD, $disc);
			$this->drawContactGlyph($im, $cx, $cy, (int) round($discD * 0.34), $navy, $kind);
			$labX = $cx + (int) round($discD / 2) + $this->sx(12);
			$this->drawTrackedText($im, $lab, 13.2, 1.5, $labX, $y, $labelW, $rowH, $navy, 'left');
			$splitX = $labX + $labelW + $this->sx(6);
			$splitH = (int) round($rowH * 0.46);
			imagefilledrectangle(
				$im,
				$splitX,
				$y + (int) round(($rowH - $splitH) / 2),
				$splitX + max(2, $this->sx(2)),
				$y + (int) round(($rowH + $splitH) / 2),
				$split
			);
			$valX = $splitX + $this->sx(14);
			$valW = ($x + $w) - $valX - $this->sx(16);
			$size = $this->fitSize($val, max(20, $valW), (int) round($rowH * 0.62), 23, 13);
			$this->drawText($im, $val, $size, $valX, $y, $valW, $rowH, $navy, 'left');
			$y += $rowH + $gap;
		}
	}

	/**
	 * @param resource|\GdImage $im
	 */
	private function drawContactGlyph($im, int $cx, int $cy, int $s, int $navy, string $kind): void
	{
		$s = max(6, $s);
		if ($kind === 'email') {
			$w = (int) round($s * 1.75);
			$h = (int) round($s * 1.2);
			$x0 = $cx - (int) round($w / 2);
			$y0 = $cy - (int) round($h / 2);
			$t = max(2, (int) round($s * 0.18));
			imagesetthickness($im, $t);
			imagerectangle($im, $x0, $y0, $x0 + $w, $y0 + $h, $navy);
			imageline($im, $x0, $y0, $cx, $cy + (int) round($h * 0.08), $navy);
			imageline($im, $x0 + $w, $y0, $cx, $cy + (int) round($h * 0.08), $navy);
			imagesetthickness($im, 1);
			return;
		}
		if ($kind === 'id') {
			$w = (int) round($s * 1.8);
			$h = (int) round($s * 1.25);
			$x0 = $cx - (int) round($w / 2);
			$y0 = $cy - (int) round($h / 2);
			$t = max(2, (int) round($s * 0.16));
			imagesetthickness($im, $t);
			imagerectangle($im, $x0, $y0, $x0 + $w, $y0 + $h, $navy);
			imagesetthickness($im, 1);
			$hx = $x0 + (int) round($w * 0.28);
			$hy = $y0 + (int) round($h * 0.32);
			imagefilledellipse($im, $hx, $hy, max(4, (int) round($s * 0.55)), max(4, (int) round($s * 0.55)), $navy);
			$lx = $x0 + (int) round($w * 0.55);
			$lw = max(3, (int) round($w * 0.32));
			$lh = max(2, (int) round($s * 0.12));
			imagefilledrectangle($im, $lx, $y0 + (int) round($h * 0.32), $lx + $lw, $y0 + (int) round($h * 0.32) + $lh, $navy);
			imagefilledrectangle($im, $lx, $y0 + (int) round($h * 0.52), $lx + $lw, $y0 + (int) round($h * 0.52) + $lh, $navy);
			imagefilledrectangle($im, $lx, $y0 + (int) round($h * 0.72), $lx + (int) round($lw * 0.7), $y0 + (int) round($h * 0.72) + $lh, $navy);
			return;
		}
		$a = (int) round($s * 0.72);
		imagefilledellipse($im, $cx - (int) round($s * 0.38), $cy - (int) round($s * 0.42), $a, $a, $navy);
		imagefilledellipse($im, $cx + (int) round($s * 0.38), $cy + (int) round($s * 0.42), $a, $a, $navy);
		$pts = [
			$cx - (int) round($s * 0.12), $cy - (int) round($s * 0.58),
			$cx + (int) round($s * 0.58), $cy + (int) round($s * 0.12),
			$cx + (int) round($s * 0.12), $cy + (int) round($s * 0.58),
			$cx - (int) round($s * 0.58), $cy - (int) round($s * 0.12),
		];
		if (PHP_VERSION_ID >= 80100) {
			imagefilledpolygon($im, $pts, $navy);
		} else {
			imagefilledpolygon($im, $pts, 4, $navy);
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
		$lineY = $y + (int) round($h / 2);
		$th = max(2, $this->sy(2.4));
		$gap = $this->sx(10);
		imagefilledrectangle($im, $this->sx(42), $lineY - (int) floor($th / 2), $x - $gap, $lineY + (int) ceil($th / 2), $gold);
		imagefilledrectangle($im, $x + $w + $gap, $lineY - (int) floor($th / 2), $this->sx(549), $lineY + (int) ceil($th / 2), $gold);
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
	 * Fit the whole person inside the circle. White margins are ignored so
	 * the portrait fills the round frame, and the circle does not cut the
	 * bottom of the body.
	 *
	 * @param resource|\GdImage $src
	 * @return resource|\GdImage|null
	 */
	private function fitSubjectInCircle($src, int $size)
	{
		if ($size < 2) {
			return null;
		}
		$sw = max(1, imagesx($src));
		$sh = max(1, imagesy($src));
		[$bx, $by, $bw, $bh] = $this->subjectBounds($src, $sw, $sh);
		$sq = imagecreatetruecolor($size, $size);
		$white = imagecolorallocate($sq, 255, 255, 255);
		imagefill($sq, 0, 0, $white);
		$radius = ($size / 2.0) * 0.96;
		$halfDiag = 0.5 * sqrt(($bw * $bw) + ($bh * $bh));
		$scale = $radius / max(0.001, $halfDiag);
		$nw = max(1, (int) round($bw * $scale));
		$nh = max(1, (int) round($bh * $scale));
		$ox = (int) round(($size - $nw) / 2);
		$oy = (int) round(($size - $nh) / 2);
		imagecopyresampled($sq, $src, $ox, $oy, $bx, $by, $nw, $nh, $bw, $bh);
		return $sq;
	}

	/**
	 * Tight box around the person. Near-white padding is dropped so a
	 * head-and-shoulders portrait can fill the card circle.
	 *
	 * @param resource|\GdImage $src
	 * @return array{0:int,1:int,2:int,3:int}
	 */
	private function subjectBounds($src, int $sw, int $sh): array
	{
		$step = max(1, (int) floor(min($sw, $sh) / 160));
		$minX = $sw;
		$minY = $sh;
		$maxX = 0;
		$maxY = 0;
		$hits = 0;
		for ($y = 0; $y < $sh; $y += $step) {
			for ($x = 0; $x < $sw; $x += $step) {
				$rgb = imagecolorat($src, $x, $y) & 0xFFFFFF;
				$r = ($rgb >> 16) & 255;
				$g = ($rgb >> 8) & 255;
				$b = $rgb & 255;
				if ($r >= 246 && $g >= 246 && $b >= 246) {
					continue;
				}
				$hits++;
				if ($x < $minX) {
					$minX = $x;
				}
				if ($y < $minY) {
					$minY = $y;
				}
				if ($x > $maxX) {
					$maxX = $x;
				}
				if ($y > $maxY) {
					$maxY = $y;
				}
			}
		}
		if ($hits < 8 || $maxX <= $minX || $maxY <= $minY) {
			return [0, 0, $sw, $sh];
		}
		$padX = (int) max($step, round(($maxX - $minX) * 0.04));
		$padY = (int) max($step, round(($maxY - $minY) * 0.04));
		$x0 = max(0, $minX - $padX);
		$y0 = max(0, $minY - $padY);
		$x1 = min($sw - 1, $maxX + $padX);
		$y1 = min($sh - 1, $maxY + $padY);
		return [$x0, $y0, $x1 - $x0 + 1, $y1 - $y0 + 1];
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
