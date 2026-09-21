<?php

namespace App\Libraries;

/**
 * Crop a person photo to an ID portrait and place it on a pure white background.
 * Used for every student/staff profile picture (uploads and Wisdom imports).
 */
class ProfilePhotoNormalizer
{
	public const WIDTH = 900;
	public const HEIGHT = 1200;
	/** Square ID-card hole (webcam + Wisdom circle artwork). */
	public const CIRCLE = 1000;

	/** @var string */
	private $lastError = '';

	public function lastError(): string
	{
		return $this->lastError;
	}

	public function saveFromFile(string $srcPath, string $destPath, bool $useAi = true, string $fit = 'contain', bool $whiten = true): bool
	{
		if (!is_file($srcPath)) {
			$this->lastError = 'Source photo missing';
			return false;
		}
		$bytes = @file_get_contents($srcPath);
		if ($bytes === false || strlen($bytes) < 32) {
			$this->lastError = 'Source photo unreadable';
			return false;
		}
		return $this->saveFromBytes($bytes, $destPath, $useAi, $fit, $whiten);
	}

	public function saveFromBytes(string $bytes, string $destPath, bool $useAi = true, string $fit = 'contain', bool $whiten = true): bool
	{
		$this->lastError = '';
		if (!function_exists('imagecreatetruecolor')) {
			$this->lastError = 'GD missing';
			return false;
		}

		$work = $bytes;
		if ($useAi) {
			$aiBytes = $this->isolateOnWhite($bytes);
			if (is_string($aiBytes) && strlen($aiBytes) > 200) {
				$work = $aiBytes;
			}
		}

		$src = @imagecreatefromstring($work);
		if ($src === false && $work !== $bytes) {
			$src = @imagecreatefromstring($bytes);
		}
		if ($src === false) {
			$this->lastError = 'Could not decode photo';
			return false;
		}
		if ($this->isEmptyPortrait($src)) {
			imagedestroy($src);
			$this->lastError = 'Photo is empty';
			return false;
		}

		$mode = strtolower($fit);
		if ($mode === 'circle') {
			$out = $this->idCircleCoverFromImage($src, self::CIRCLE);
			if ($out === null) {
				$out = $this->cropOntoWhite($src, self::CIRCLE, self::CIRCLE, 'cover');
			}
		} else {
			$outFit = $mode === 'cover' ? 'cover' : 'contain';
			$out = $this->cropOntoWhite($src, self::WIDTH, self::HEIGHT, $outFit);
		}
		imagedestroy($src);
		if ($whiten && $mode !== 'circle' && $out !== null) {
			$backup = imagecreatetruecolor(imagesx($out), imagesy($out));
			if ($backup !== false) {
				imagecopy($backup, $out, 0, 0, 0, 0, imagesx($out), imagesy($out));
			}
			$this->whitenBackdropInPlace($out);
			if ($this->looksBlank($out) && $backup) {
				imagedestroy($out);
				$out = $backup;
			} elseif ($backup) {
				imagedestroy($backup);
			}
		}
		if ($out === null) {
			$this->lastError = 'Could not crop photo';
			return false;
		}

		$dir = dirname($destPath);
		if (!is_dir($dir)) {
			@mkdir($dir, 0775, true);
		}

		$tmp = $destPath . '.tmp.jpg';
		imageinterlace($out, true);
		$ok = @imagejpeg($out, $tmp, 94);
		imagedestroy($out);
		if (!$ok || !is_file($tmp)) {
			$this->lastError = 'Could not write photo';
			@unlink($tmp);
			return false;
		}
		if (is_file($destPath) && !@unlink($destPath) && strcasecmp($destPath, $tmp) !== 0) {
			@unlink($tmp);
			$this->lastError = 'Could not replace photo';
			return false;
		}
		if (!@rename($tmp, $destPath)) {
			$copied = @copy($tmp, $destPath);
			@unlink($tmp);
			if (!$copied) {
				$this->lastError = 'Could not move photo';
				return false;
			}
		}
		return true;
	}

	/**
	 * Ask Gemini to keep the same person and replace the scene with pure white.
	 */
	private function isolateOnWhite(string $bytes): ?string
	{
		$key = trim((string) (env('GOOGLE_AI_API_KEY') ?: env('GEMINI_API_KEY') ?: ''), " \t\"'");
		if ($key === '') {
			return null;
		}

		$src = @imagecreatefromstring($bytes);
		if ($src === false) {
			return null;
		}
		$payloadBytes = $this->jpegForAi($src);
		imagedestroy($src);
		if ($payloadBytes === null) {
			return null;
		}

		$primary = trim((string) (env('GEMINI_IMAGE_MODEL') ?: env('GOOGLE_AI_IMAGE_MODEL') ?: ''));
		$models = array_values(array_unique(array_filter([
			$primary,
			'gemini-2.5-flash-image',
			'gemini-2.5-flash-image-preview',
			'gemini-2.0-flash-preview-image-generation',
		])));

		$prompt = "Edit this photograph into a school ID photo.\n"
			. "Keep the exact same person, clothing, and framing as the source.\n"
			. "Keep the FULL person that is already visible: top of the hair or cap, ears, neck, both shoulders, and the clothing on the chest.\n"
			. "Do NOT zoom in. Do NOT crop tighter than the source. Do NOT cut off hair, ears, chin, or shoulders.\n"
			. "Do NOT add glasses, a tie, jewelry, makeup, a new beard, or any garment that is not already in the photo.\n"
			. "Do NOT remove a graduation cap, glasses, or head covering if they are already in the photo.\n"
			. "Do not beautify or change identity.\n"
			. "Remove only the original background (walls, banners, flowers, other people).\n"
			. "Place the person on a pure solid white background (#FFFFFF) with no shadows or gradients.\n"
			. "Leave a small white margin around the whole person. Output one photorealistic image only. No text.";

		foreach ($models as $model) {
			try {
				$bin = $this->requestGeminiEdit($model, $key, $payloadBytes, $prompt);
				if (is_string($bin) && strlen($bin) > 200) {
					return $bin;
				}
			} catch (\Throwable $e) {
				$this->lastError = $e->getMessage();
			}
		}
		return null;
	}

	private function requestGeminiEdit(string $model, string $key, string $jpegBytes, string $prompt): ?string
	{
		$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent';
		$payload = [
			'contents' => [[
				'role' => 'user',
				'parts' => [
					['text' => $prompt],
					[
						'inline_data' => [
							'mime_type' => 'image/jpeg',
							'data' => base64_encode($jpegBytes),
						],
					],
				],
			]],
			'generationConfig' => [
				'responseModalities' => ['TEXT', 'IMAGE'],
				'imageConfig' => [
					'aspectRatio' => '3:4',
				],
			],
		];

		$raw = $this->postGemini($url, $key, $payload);
		$code = $raw['code'];
		$body = $raw['body'];
		if ($code >= 400 && (stripos($body, 'imageConfig') !== false || stripos($body, 'Unknown name') !== false)) {
			unset($payload['generationConfig']['imageConfig']);
			$raw = $this->postGemini($url, $key, $payload);
			$code = $raw['code'];
			$body = $raw['body'];
		}
		if ($code >= 400) {
			throw new \RuntimeException('HTTP ' . $code . ': ' . substr(preg_replace('/\s+/', ' ', $body), 0, 220));
		}

		$data = json_decode($body, true);
		if (!is_array($data)) {
			throw new \RuntimeException('Invalid JSON from AI service');
		}
		if (!empty($data['error']['message'])) {
			throw new \RuntimeException((string) $data['error']['message']);
		}

		$parts = $data['candidates'][0]['content']['parts'] ?? [];
		foreach ($parts as $part) {
			$inline = $part['inlineData'] ?? $part['inline_data'] ?? null;
			if (!$inline || empty($inline['data'])) {
				continue;
			}
			$bin = base64_decode($inline['data'], true);
			if ($bin !== false && strlen($bin) > 200) {
				return $bin;
			}
		}
		throw new \RuntimeException('AI response had no image data');
	}

	/** @return array{code:int,body:string} */
	private function postGemini(string $url, string $key, array $payload): array
	{
		$ch = curl_init($url);
		curl_setopt_array($ch, [
			CURLOPT_POST => true,
			CURLOPT_HTTPHEADER => [
				'Content-Type: application/json',
				'x-goog-api-key: ' . $key,
			],
			CURLOPT_POSTFIELDS => json_encode($payload),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 90,
			CURLOPT_CONNECTTIMEOUT => 20,
		]);
		$body = curl_exec($ch);
		$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$err = curl_error($ch);
		curl_close($ch);
		if ($body === false) {
			throw new \RuntimeException('curl failed: ' . $err);
		}
		return ['code' => $code, 'body' => (string) $body];
	}

	/**
	 * Square for a circular card hole: white studio wall, then zoom the person to fill the circle.
	 *
	 * @param resource|\GdImage $src
	 * @return resource|\GdImage|null
	 */
	public function circlePortraitFromImage($src, int $size)
	{
		if (!is_resource($src) && !is_object($src)) {
			return null;
		}
		$size = max(32, $size);
		$fallback = $this->cropOntoWhite($src, $size, $size, 'cover');

		$copy = imagecreatetruecolor(imagesx($src), imagesy($src));
		if ($copy === false) {
			return $fallback;
		}
		imagecopy($copy, $src, 0, 0, 0, 0, imagesx($src), imagesy($src));
		$this->whitenBackdropInPlace($copy);
		$work = $copy;
		if ($this->looksBlank($copy)) {
			imagedestroy($copy);
			$work = $src;
		}
		$out = $this->tightCoverOntoWhite($work, $size, $size);
		if ($out !== null && !$this->looksBlank($out)) {
			$this->smoothWhiteStudioInPlace($out);
			if ($this->looksBlank($out)) {
				imagedestroy($out);
				$out = null;
			}
		}
		if ($work !== $src) {
			imagedestroy($work);
		}
		if ($out === null || $this->looksBlank($out)) {
			if ($out) {
				imagedestroy($out);
			}
			if ($fallback) {
				$this->smoothWhiteStudioInPlace($fallback);
			}
			return $fallback;
		}
		if ($fallback) {
			imagedestroy($fallback);
		}
		return $out;
	}

	/**
	 * Replace connected studio walls with #FFFFFF. Flood-fill from the edges only so
	 * clothing / hair / skin keep their original pixels (no dashed white holes).
	 *
	 * @param resource|\GdImage $im
	 */
	public function whitenBackdropInPlace($im): void
	{
		$w = imagesx($im);
		$h = imagesy($im);
		if ($w < 4 || $h < 4) {
			return;
		}

		$cornerLum = $this->cornerMedianLuma($im, $w, $h);
		if ($cornerLum < 88) {
			$this->whitenDarkStudioInPlace($im, $w, $h);
			return;
		}

		$wall = $this->sampleCornerRgb($im, $w, $h);
		$cx = ($w - 1) * 0.5;
		$cy = ($h - 1) * 0.42;
		$rx = max(8.0, $w * 0.40);
		$ry = max(8.0, $h * 0.44);

		$white = imagecolorallocate($im, 255, 255, 255);
		$seen = str_repeat("\0", $w * $h);
		$queue = [];
		$qi = 0;
		$push = static function (int $x, int $y) use (&$queue, &$seen, $w, $h) {
			if ($x < 0 || $y < 0 || $x >= $w || $y >= $h) {
				return;
			}
			$i = $y * $w + $x;
			if ($seen[$i] !== "\0") {
				return;
			}
			$seen[$i] = "\1";
			$queue[] = $i;
		};
		for ($x = 0; $x < $w; $x++) {
			$push($x, 0);
			$push($x, $h - 1);
		}
		for ($y = 0; $y < $h; $y++) {
			$push(0, $y);
			$push($w - 1, $y);
		}

		while ($qi < count($queue)) {
			$i = $queue[$qi++];
			$x = $i % $w;
			$y = intdiv($i, $w);
			$rgb = imagecolorat($im, $x, $y) & 0xFFFFFF;
			$r = ($rgb >> 16) & 255;
			$g = ($rgb >> 8) & 255;
			$b = $rgb & 255;
			$nearWhite = ($r >= 242 && $g >= 242 && $b >= 242);
			if ($this->isProtectedPerson($r, $g, $b, $wall)) {
				continue;
			}
			$nx = ($x - $cx) / $rx;
			$ny = ($y - $cy) / $ry;
			$inCore = ($nx * $nx + $ny * $ny) < 1.0;
			$dist = abs($r - $wall[0]) + abs($g - $wall[1]) + abs($b - $wall[2]);
			if ($inCore) {
				if ($nearWhite || $dist > 28) {
					continue;
				}
			} elseif ($nearWhite) {
				$push($x + 1, $y);
				$push($x - 1, $y);
				$push($x, $y + 1);
				$push($x, $y - 1);
				continue;
			}
			if (!$this->isWallPixel($r, $g, $b, $wall)) {
				continue;
			}
			imagesetpixel($im, $x, $y, $white);
			$push($x + 1, $y);
			$push($x - 1, $y);
			$push($x, $y + 1);
			$push($x, $y - 1);
		}

		$this->featherWallEdge($im, $w, $h, $wall, $white, $cx, $cy, $rx, $ry);
	}

	/**
	 * Webcam studio is usually a dark wall. Replace only edge-connected dark pixels
	 * outside the head/shoulders core so hair and uniforms stay intact.
	 *
	 * @param resource|\GdImage $im
	 */
	private function whitenDarkStudioInPlace($im, int $w, int $h): void
	{
		$cx = ($w - 1) * 0.5;
		$cy = ($h - 1) * 0.34;
		$rx = max(8.0, $w * 0.26);
		$ry = max(8.0, $h * 0.32);
		$white = imagecolorallocate($im, 255, 255, 255);
		$seen = str_repeat("\0", $w * $h);
		$queue = [];
		$qi = 0;
		$push = static function (int $x, int $y) use (&$queue, &$seen, $w, $h) {
			if ($x < 0 || $y < 0 || $x >= $w || $y >= $h) {
				return;
			}
			$i = $y * $w + $x;
			if ($seen[$i] !== "\0") {
				return;
			}
			$seen[$i] = "\1";
			$queue[] = $i;
		};
		for ($x = 0; $x < $w; $x++) {
			$push($x, 0);
			$push($x, $h - 1);
		}
		for ($y = 0; $y < $h; $y++) {
			$push(0, $y);
			$push($w - 1, $y);
		}

		while ($qi < count($queue)) {
			$i = $queue[$qi++];
			$x = $i % $w;
			$y = intdiv($i, $w);
			$rgb = imagecolorat($im, $x, $y) & 0xFFFFFF;
			$r = ($rgb >> 16) & 255;
			$g = ($rgb >> 8) & 255;
			$b = $rgb & 255;
			if ($this->isSkinTone($r, $g, $b)) {
				continue;
			}
			$nx = ($x - $cx) / $rx;
			$ny = ($y - $cy) / $ry;
			if (($nx * $nx + $ny * $ny) < 1.0) {
				continue;
			}
			$lum = $this->luma($r, $g, $b);
			$maxc = max($r, $g, $b);
			$minc = min($r, $g, $b);
			$sat = $maxc === 0 ? 0.0 : ($maxc - $minc) / $maxc;
			$nearWhite = ($r >= 242 && $g >= 242 && $b >= 242);
			$darkWall = $lum <= 85 && $sat < 0.35;
			if ($lum <= 45) {
				$darkWall = true;
			}
			$grayWall = $lum <= 175 && $sat < 0.12;
			if (!$nearWhite && !$darkWall && !$grayWall) {
				continue;
			}
			if (!$nearWhite) {
				imagesetpixel($im, $x, $y, $white);
			}
			$push($x + 1, $y);
			$push($x - 1, $y);
			$push($x, $y + 1);
			$push($x, $y - 1);
		}
	}

	/** @param resource|\GdImage $im */
	private function cornerMedianLuma($im, int $w, int $h): float
	{
		$box = max(4, (int) floor(min($w, $h) * 0.08));
		$step = max(1, (int) floor($box / 6));
		$vals = [];
		$regions = [[0, 0], [$w - $box, 0], [0, $h - $box], [$w - $box, $h - $box]];
		foreach ($regions as $rg) {
			for ($y = $rg[1]; $y < $rg[1] + $box && $y < $h; $y += $step) {
				for ($x = $rg[0]; $x < $rg[0] + $box && $x < $w; $x += $step) {
					$rgb = imagecolorat($im, $x, $y) & 0xFFFFFF;
					$r = ($rgb >> 16) & 255;
					$g = ($rgb >> 8) & 255;
					$b = $rgb & 255;
					if ($this->isSkinTone($r, $g, $b)) {
						continue;
					}
					$vals[] = $this->luma($r, $g, $b);
				}
			}
		}
		if (count($vals) < 4) {
			return 160.0;
		}
		sort($vals);
		return $vals[intdiv(count($vals), 2)];
	}

	/**
	 * Crop the non-white subject so it fills the frame (ID circle), then place on white.
	 *
	 * @param resource|\GdImage $src
	 * @return resource|\GdImage|null
	 */
	public function tightCoverOntoWhite($src, int $outW, int $outH)
	{
		$sw = imagesx($src);
		$sh = imagesy($src);
		if ($sw < 2 || $sh < 2) {
			return null;
		}
		$bbox = $this->subjectBBox($src, $sw, $sh);
		$area = $bbox[2] * $bbox[3];
		$frame = $sw * $sh;
		$cornerLum = $this->cornerMedianLuma($src, $sw, $sh);
		$alreadyFills = $area >= (int) ($frame * 0.55)
			&& $bbox[2] >= (int) ($sw * 0.70)
			&& $bbox[3] >= (int) ($sh * 0.62)
			&& $cornerLum >= 88;
		if ($alreadyFills) {
			return $this->cropOntoWhite($src, $outW, $outH, 'cover');
		}
		if ($area < (int) ($frame * 0.08) || max($bbox[2], $bbox[3]) < (int) (min($sw, $sh) * 0.22)) {
			return $this->cropOntoWhite($src, $outW, $outH, 'cover');
		}

		$bw = $bbox[2];
		$bh = $bbox[3];
		$pad = (int) max(2, round($bw * 0.035));
		// Fill the circle using shoulder width, keeping the top of the head.
		$side = (int) round($bw + $pad * 2);
		$minHeadShoulders = (int) round($bh * 0.60);
		if ($side < $minHeadShoulders) {
			$side = $minHeadShoulders;
		}
		$side = max(8, min($side, $sw, $sh));
		$cx = $bbox[0] + intdiv($bw, 2);
		$sx = (int) max(0, min($sw - $side, $cx - intdiv($side, 2)));
		$sy = (int) max(0, min($sh - $side, $bbox[1] - $pad));

		$dst = imagecreatetruecolor($outW, $outH);
		$white = imagecolorallocate($dst, 255, 255, 255);
		imagefill($dst, 0, 0, $white);
		$this->hiQualityResample($dst, $src, 0, 0, $sx, $sy, $outW, $outH, $side, $side);
		return $dst;
	}

	/**
	 * True when the face area was wiped to white (photo would vanish on the card).
	 *
	 * @param resource|\GdImage $im
	 */
	public function looksBlank($im): bool
	{
		$w = imagesx($im);
		$h = imagesy($im);
		if ($w < 4 || $h < 4) {
			return true;
		}
		$hits = 0;
		$seen = 0;
		$x0 = (int) floor($w * 0.20);
		$x1 = (int) ceil($w * 0.80);
		$y0 = (int) floor($h * 0.16);
		$y1 = (int) ceil($h * 0.84);
		$step = max(1, (int) floor(min($w, $h) / 36));
		for ($y = $y0; $y < $y1; $y += $step) {
			for ($x = $x0; $x < $x1; $x += $step) {
				$seen++;
				$rgb = imagecolorat($im, $x, $y) & 0xFFFFFF;
				$r = ($rgb >> 16) & 255;
				$g = ($rgb >> 8) & 255;
				$b = $rgb & 255;
				if ($r < 238 || $g < 238 || $b < 238) {
					$hits++;
				}
			}
		}
		return $seen > 0 && ($hits / $seen) < 0.08;
	}

	/**
	 * True when the file has no usable face (flat grey/white disc).
	 *
	 * @param resource|\GdImage $im
	 */
	public function isEmptyPortrait($im): bool
	{
		if ($this->looksBlank($im)) {
			return true;
		}
		$w = imagesx($im);
		$h = imagesy($im);
		$step = max(1, (int) floor(min($w, $h) / 40));
		$seen = 0;
		$person = 0;
		for ($y = (int) ($h * 0.12); $y < (int) ($h * 0.88); $y += $step) {
			for ($x = (int) ($w * 0.12); $x < (int) ($w * 0.88); $x += $step) {
				$seen++;
				$rgb = imagecolorat($im, $x, $y) & 0xFFFFFF;
				$r = ($rgb >> 16) & 255;
				$g = ($rgb >> 8) & 255;
				$b = $rgb & 255;
				if ($this->isSkinTone($r, $g, $b)) {
					$person++;
					continue;
				}
				$maxc = max($r, $g, $b);
				$minc = min($r, $g, $b);
				$sat = $maxc === 0 ? 0.0 : ($maxc - $minc) / $maxc;
				$lum = $this->luma($r, $g, $b);
				if ($sat > 0.22 && $lum > 30 && $lum < 220) {
					$person++;
				}
			}
		}
		return $seen > 0 && ($person / $seen) < 0.04;
	}

	/**
	 * Cover-crop the real portrait into a square that fills the ID-card circle.
	 * Strips white letterbox bars (circle-on-3:4 files) so the face is not
	 * taken from empty padding.
	 *
	 * @param resource|\GdImage $src
	 * @return resource|\GdImage|null
	 */
	public function idCircleCoverFromImage($src, int $size)
	{
		if (!is_resource($src) && !is_object($src)) {
			return null;
		}
		$size = max(32, $size);
		$sw = imagesx($src);
		$sh = imagesy($src);
		if ($sw < 2 || $sh < 2) {
			return null;
		}
		[$bx, $by, $bw, $bh] = $this->letterboxBox($src, $sw, $sh);
		if ($bw < 8 || $bh < 8) {
			$bx = 0;
			$by = 0;
			$bw = $sw;
			$bh = $sh;
		}

		$person = $this->personBox($src, $bx, $by, $bw, $bh);
		if ($person !== null) {
			[$px, $py, $pw, $ph] = $person;
			$padX = (int) max(8, round($pw * 0.22));
			$padTop = (int) max(8, round($ph * 0.18));
			$padBot = (int) max(6, round($ph * 0.08));
			$px = max($bx, $px - $padX);
			$py = max($by, $py - $padTop);
			$pr = min($bx + $bw, $px + $pw + $padX * 2);
			$pb = min($by + $bh, $py + $ph + $padBot);
			$pw = max(8, $pr - $px);
			$ph = max(8, $pb - $py);
			$side = max($pw, $ph);
			$side = min($side, $bw, $bh);
			$cx = $px + intdiv($pw, 2);
			$sx = (int) max($bx, min($bx + $bw - $side, $cx - intdiv($side, 2)));
			$sy = (int) max($by, min($by + $bh - $side, $py));
			$crop = $side;
		} else {
			$side = min($bw, $bh);
			$cx = $bx + intdiv($bw, 2);
			$sx = (int) max($bx, min($bx + $bw - $side, $cx - intdiv($side, 2)));
			$sy = $by;
			if ($bh > $bw) {
				$extra = $bh - $side;
				$sy = (int) max($by, min($by + $extra, $by + (int) round($extra * 0.10)));
			}
			$zoom = 1.0;
			$crop = max(8, (int) round($side / $zoom));
			$sx = (int) max($bx, min($bx + $bw - $crop, $sx + intdiv($side - $crop, 2)));
			$sy = (int) max($by, min($by + $bh - $crop, $sy + (int) round(($side - $crop) * 0.08)));
		}
		$crop = max(1, min($crop, $sw - $sx, $sh - $sy, $bx + $bw - $sx, $by + $bh - $sy));

		$dst = imagecreatetruecolor($size, $size);
		$white = imagecolorallocate($dst, 255, 255, 255);
		imagefill($dst, 0, 0, $white);
		$this->hiQualityResample($dst, $src, 0, 0, $sx, $sy, $size, $size, $crop, $crop);
		return $dst;
	}

	/** @return array{0:int,1:int,2:int,3:int} x,y,w,h */
	private function letterboxBox($im, int $w, int $h): array
	{
		$top = 0;
		while ($top < (int) ($h * 0.40) && $this->isWhiteBar($im, 0, $top, $w, 1)) {
			$top++;
		}
		$bot = $h - 1;
		while ($bot > (int) ($h * 0.60) && $this->isWhiteBar($im, 0, $bot, $w, 1)) {
			$bot--;
		}
		$left = 0;
		while ($left < (int) ($w * 0.40) && $this->isWhiteBar($im, $left, $top, 1, max(1, $bot - $top + 1))) {
			$left++;
		}
		$right = $w - 1;
		while ($right > (int) ($w * 0.60) && $this->isWhiteBar($im, $right, $top, 1, max(1, $bot - $top + 1))) {
			$right--;
		}
		if ($right <= $left || $bot <= $top) {
			return [0, 0, $w, $h];
		}
		return [$left, $top, $right - $left + 1, $bot - $top + 1];
	}

	private function isWhiteBar($im, int $x, int $y, int $bw, int $bh): bool
	{
		$w = imagesx($im);
		$h = imagesy($im);
		$x1 = min($w, $x + max(1, $bw));
		$y1 = min($h, $y + max(1, $bh));
		$seen = 0;
		$white = 0;
		$step = 2;
		for ($yy = max(0, $y); $yy < $y1; $yy += $step) {
			for ($xx = max(0, $x); $xx < $x1; $xx += $step) {
				$seen++;
				$rgb = imagecolorat($im, $xx, $yy) & 0xFFFFFF;
				$r = ($rgb >> 16) & 255;
				$g = ($rgb >> 8) & 255;
				$b = $rgb & 255;
				if ($r >= 248 && $g >= 248 && $b >= 248) {
					$white++;
				}
			}
		}
		return $seen > 0 && ($white / $seen) >= 0.90;
	}

	/**
	 * Bounding box of the person inside a (letterboxed) portrait, or null when
	 * the subject already fills the frame.
	 *
	 * @return array{0:int,1:int,2:int,3:int}|null
	 */
	private function personBox($im, int $x0, int $y0, int $bw, int $bh): ?array
	{
		$wall = $this->sampleInnerWall($im, $x0, $y0, $bw, $bh);
		$step = max(1, (int) floor(min($bw, $bh) / 180));
		$minX = $x0 + $bw;
		$minY = $y0 + $bh;
		$maxX = $x0;
		$maxY = $y0;
		$hits = 0;
		$wallLum = $this->luma($wall[0], $wall[1], $wall[2]);
		for ($y = $y0; $y < $y0 + $bh; $y += $step) {
			for ($x = $x0; $x < $x0 + $bw; $x += $step) {
				$rgb = imagecolorat($im, $x, $y) & 0xFFFFFF;
				$r = ($rgb >> 16) & 255;
				$g = ($rgb >> 8) & 255;
				$b = $rgb & 255;
				if ($this->isBackdropPixel($r, $g, $b)) {
					continue;
				}
				$dist = abs($r - $wall[0]) + abs($g - $wall[1]) + abs($b - $wall[2]);
				$lum = $this->luma($r, $g, $b);
				$maxc = max($r, $g, $b);
				$minc = min($r, $g, $b);
				$sat = $maxc === 0 ? 0.0 : ($maxc - $minc) / $maxc;
				$isPerson = $this->isSkinTone($r, $g, $b)
					|| $lum < $wallLum - 22
					|| $sat > 0.20
					|| $dist > 70;
				if (!$isPerson) {
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
		if ($maxX < $minX || $hits < 12) {
			return null;
		}
		$pw = max(1, $maxX - $minX + 1);
		$ph = max(1, $maxY - $minY + 1);
		$area = $pw * $ph;
		$frame = max(1, $bw * $bh);
		// Already filling the portrait — a second zoom would cut the head.
		if ($pw >= (int) ($bw * 0.58) && $ph >= (int) ($bh * 0.52)) {
			return null;
		}
		if ($area < (int) ($frame * 0.06) || max($pw, $ph) < (int) (min($bw, $bh) * 0.18)) {
			return null;
		}
		return [$minX, $minY, $pw, $ph];
	}

	/** @return array{0:int,1:int,2:int} */
	private function sampleInnerWall($im, int $x0, int $y0, int $bw, int $bh): array
	{
		$insetX = max(2, (int) round($bw * 0.08));
		$insetY = max(2, (int) round($bh * 0.08));
		$pts = [
			[$x0 + $insetX, $y0 + $insetY],
			[$x0 + $bw - $insetX - 1, $y0 + $insetY],
			[$x0 + $insetX, $y0 + $bh - $insetY - 1],
			[$x0 + $bw - $insetX - 1, $y0 + $bh - $insetY - 1],
		];
		$rs = $gs = $bs = [];
		foreach ($pts as [$x, $y]) {
			for ($dy = -3; $dy <= 3; $dy++) {
				for ($dx = -3; $dx <= 3; $dx++) {
					$xx = max(0, min(imagesx($im) - 1, $x + $dx));
					$yy = max(0, min(imagesy($im) - 1, $y + $dy));
					$rgb = imagecolorat($im, $xx, $yy) & 0xFFFFFF;
					$r = ($rgb >> 16) & 255;
					$g = ($rgb >> 8) & 255;
					$b = $rgb & 255;
					if ($this->isBackdropPixel($r, $g, $b)) {
						continue;
					}
					$rs[] = $r;
					$gs[] = $g;
					$bs[] = $b;
				}
			}
		}
		if ($rs === []) {
			return [210, 214, 220];
		}
		sort($rs);
		sort($gs);
		sort($bs);
		$m = intdiv(count($rs), 2);
		return [$rs[$m], $gs[$m], $bs[$m]];
	}

	private function subjectBBox($im, int $w, int $h): array
	{
		$minX = $w;
		$minY = $h;
		$maxX = 0;
		$maxY = 0;
		$step = max(1, (int) floor(min($w, $h) / 220));
		$darkStudio = $this->cornerMedianLuma($im, $w, $h) < 88;
		for ($y = 0; $y < $h; $y += $step) {
			for ($x = 0; $x < $w; $x += $step) {
				$rgb = imagecolorat($im, $x, $y) & 0xFFFFFF;
				$r = ($rgb >> 16) & 255;
				$g = ($rgb >> 8) & 255;
				$b = $rgb & 255;
				if ($this->isBackdropPixel($r, $g, $b)) {
					continue;
				}
				if ($darkStudio && $this->isDarkStudioPixel($r, $g, $b)) {
					continue;
				}
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
		if ($maxX < $minX) {
			return [0, 0, $w, $h];
		}
		$bw = max(1, $maxX - $minX + 1);
		$bh = max(1, $maxY - $minY + 1);
		if ($darkStudio) {
			$hairPad = (int) round($bh * 0.18);
			$minY = max(0, $minY - $hairPad);
			$sidePad = (int) round($bw * 0.04);
			$minX = max(0, $minX - $sidePad);
			$maxX = min($w - 1, $maxX + $sidePad);
			$bw = max(1, $maxX - $minX + 1);
			$bh = max(1, $maxY - $minY + 1);
		}
		return [$minX, $minY, $bw, $bh];
	}

	private function isDarkStudioPixel(int $r, int $g, int $b): bool
	{
		if ($this->isSkinTone($r, $g, $b)) {
			return false;
		}
		$lum = $this->luma($r, $g, $b);
		$maxc = max($r, $g, $b);
		$minc = min($r, $g, $b);
		$sat = $maxc === 0 ? 0.0 : ($maxc - $minc) / $maxc;
		if ($lum <= 48) {
			return true;
		}
		return $lum <= 88 && $sat < 0.35;
	}

	/**
	 * Composite the student onto a smooth pure-white studio backdrop.
	 * Leftover gray/beige/dark wall is keyed out; the person edge is feathered
	 * so ID cards print without a patchy or jagged background.
	 *
	 * @param resource|\GdImage $im
	 */
	public function smoothWhiteStudioInPlace($im): void
	{
		$w = imagesx($im);
		$h = imagesy($im);
		if ($w < 8 || $h < 8) {
			return;
		}

		$wall = $this->sampleCornerRgb($im, $w, $h);
		$cx = ($w - 1) * 0.5;
		$cy = ($h - 1) * 0.40;
		$rad = max(8.0, min($w, $h) * 0.5);
		$n = $w * $h;
		$conf = str_repeat("\x00", $n);

		for ($y = 0; $y < $h; $y++) {
			$row = $y * $w;
			for ($x = 0; $x < $w; $x++) {
				$rgb = imagecolorat($im, $x, $y) & 0xFFFFFF;
				$r = ($rgb >> 16) & 255;
				$g = ($rgb >> 8) & 255;
				$b = $rgb & 255;
				$lum = $this->luma($r, $g, $b);
				$maxc = max($r, $g, $b);
				$minc = min($r, $g, $b);
				$sat = $maxc === 0 ? 0.0 : ($maxc - $minc) / $maxc;
				$dx = ($x - $cx) / $rad;
				$dy = ($y - $cy) / $rad;
				$distN = sqrt($dx * $dx + $dy * $dy);
				$wallDist = abs($r - $wall[0]) + abs($g - $wall[1]) + abs($b - $wall[2]);
				$inHead = ($y < $h * 0.62) && ($distN < 0.52);
				$inTorso = ($y >= $h * 0.38) && ($distN < 0.70);

				$p = 90;
				if ($this->isSkinTone($r, $g, $b)) {
					$p = 255;
				} elseif ($sat > 0.28 && $wallDist > 55) {
					$p = 245;
				} elseif ($lum < 58 && $inHead) {
					$p = 235;
				} elseif ($lum < 72 && $inTorso && $sat < 0.22) {
					$p = 220;
				} elseif ($lum > 208 && $inTorso && $distN < 0.62) {
					$p = 215;
				} elseif ($distN > 1.02) {
					$p = 0;
				} elseif ($this->isStudioBackdropPixel($r, $g, $b, $wall, $wallDist, $lum, $sat)) {
					$p = $inHead && $lum < 70 ? 160 : 8;
				} elseif ($sat < 0.12 && $distN > 0.42 && !$inTorso) {
					$p = 12;
				} elseif ($distN < 0.34) {
					$p = 200;
				}
				$conf[$row + $x] = chr($p);
			}
		}

		$conf = $this->dilateU8($conf, $w, $h);
		$matte = $this->boxBlurU8($conf, $w, $h, 2);

		for ($y = 0; $y < $h; $y++) {
			$row = $y * $w;
			for ($x = 0; $x < $w; $x++) {
				$a = ord($matte[$row + $x]) / 255.0;
				$rgb = imagecolorat($im, $x, $y) & 0xFFFFFF;
				$r = ($rgb >> 16) & 255;
				$g = ($rgb >> 8) & 255;
				$b = $rgb & 255;
				if ($this->isSkinTone($r, $g, $b)) {
					continue;
				}
				$maxc = max($r, $g, $b);
				$minc = min($r, $g, $b);
				$sat = $maxc === 0 ? 0.0 : ($maxc - $minc) / $maxc;
				if ($sat > 0.24 && $a > 0.20) {
					continue;
				}
				if ($a <= 0.12) {
					imagesetpixel($im, $x, $y, 0xFFFFFF);
					continue;
				}
				if ($a >= 0.88) {
					continue;
				}
				$nr = (int) round($r * $a + 255 * (1 - $a));
				$ng = (int) round($g * $a + 255 * (1 - $a));
				$nb = (int) round($b * $a + 255 * (1 - $a));
				imagesetpixel($im, $x, $y, ($nr << 16) | ($ng << 8) | $nb);
			}
		}
	}

	private function isStudioBackdropPixel(int $r, int $g, int $b, array $wall, int $wallDist, float $lum, float $sat): bool
	{
		if ($this->isSkinTone($r, $g, $b)) {
			return false;
		}
		if ($sat > 0.26) {
			return false;
		}
		if ($lum <= 92 && $sat < 0.34) {
			return true;
		}
		if ($sat < 0.13 && $lum >= 88 && $lum <= 222) {
			return true;
		}
		return $this->isWallPixel($r, $g, $b, $wall) || $wallDist <= 62;
	}

	private function dilateU8(string $src, int $w, int $h): string
	{
		$out = $src;
		for ($y = 1; $y < $h - 1; $y++) {
			$row = $y * $w;
			for ($x = 1; $x < $w - 1; $x++) {
				$max = 0;
				for ($oy = -1; $oy <= 1; $oy++) {
					$rr = ($y + $oy) * $w;
					for ($ox = -1; $ox <= 1; $ox++) {
						$v = ord($src[$rr + $x + $ox]);
						if ($v > $max) {
							$max = $v;
						}
					}
				}
				$out[$row + $x] = chr($max);
			}
		}
		return $out;
	}

	private function boxBlurU8(string $src, int $w, int $h, int $radius): string
	{
		$r = max(1, $radius);
		$div = $r * 2 + 1;
		$tmp = $src;
		for ($y = 0; $y < $h; $y++) {
			$row = $y * $w;
			for ($x = 0; $x < $w; $x++) {
				$sum = 0;
				for ($k = -$r; $k <= $r; $k++) {
					$xx = $x + $k;
					if ($xx < 0) {
						$xx = 0;
					} elseif ($xx >= $w) {
						$xx = $w - 1;
					}
					$sum += ord($src[$row + $xx]);
				}
				$tmp[$row + $x] = chr(intdiv($sum, $div));
			}
		}
		$out = $tmp;
		for ($x = 0; $x < $w; $x++) {
			for ($y = 0; $y < $h; $y++) {
				$sum = 0;
				for ($k = -$r; $k <= $r; $k++) {
					$yy = $y + $k;
					if ($yy < 0) {
						$yy = 0;
					} elseif ($yy >= $h) {
						$yy = $h - 1;
					}
					$sum += ord($tmp[$yy * $w + $x]);
				}
				$out[$y * $w + $x] = chr(intdiv($sum, $div));
			}
		}
		return $out;
	}

	/**
	 * After the person fills the square, clear leftover studio around the head
	 * without touching dark uniforms at the shoulders.
	 *
	 * @param resource|\GdImage $im
	 */
	private function whitenHeadHaloInPlace($im): void
	{
		$w = imagesx($im);
		$h = imagesy($im);
		if ($w < 8 || $h < 8) {
			return;
		}
		$cx = ($w - 1) * 0.5;
		$cy = ($h - 1) * 0.30;
		$rx = max(8.0, $w * 0.24);
		$ry = max(8.0, $h * 0.30);
		$white = imagecolorallocate($im, 255, 255, 255);
		$yMax = (int) floor($h * 0.50);
		for ($y = 0; $y < $yMax; $y++) {
			for ($x = 0; $x < $w; $x++) {
				$nx = ($x - $cx) / $rx;
				$ny = ($y - $cy) / $ry;
				if (($nx * $nx + $ny * $ny) < 1.0) {
					continue;
				}
				$rgb = imagecolorat($im, $x, $y) & 0xFFFFFF;
				$r = ($rgb >> 16) & 255;
				$g = ($rgb >> 8) & 255;
				$b = $rgb & 255;
				if ($this->isSkinTone($r, $g, $b)) {
					continue;
				}
				$lum = $this->luma($r, $g, $b);
				$maxc = max($r, $g, $b);
				$minc = min($r, $g, $b);
				$sat = $maxc === 0 ? 0.0 : ($maxc - $minc) / $maxc;
				if ($sat > 0.22) {
					continue;
				}
				if ($lum <= 95 || ($lum <= 180 && $sat < 0.12)) {
					imagesetpixel($im, $x, $y, $white);
				}
			}
		}
	}

	/**
	 * Clean leftover studio only in the corners / outer ring (the card clips to a circle).
	 *
	 * @param resource|\GdImage $im
	 */
	private function whitenRimInPlace($im): void
	{
		$w = imagesx($im);
		$h = imagesy($im);
		if ($w < 8 || $h < 8) {
			return;
		}
		$cx = ($w - 1) * 0.5;
		$cy = ($h - 1) * 0.5;
		$rad = min($w, $h) * 0.5;
		$inner = $rad * 0.86;
		$white = imagecolorallocate($im, 255, 255, 255);
		for ($y = 0; $y < $h; $y++) {
			for ($x = 0; $x < $w; $x++) {
				$dx = $x - $cx;
				$dy = $y - $cy;
				if (($dx * $dx + $dy * $dy) < ($inner * $inner)) {
					continue;
				}
				$rgb = imagecolorat($im, $x, $y) & 0xFFFFFF;
				$r = ($rgb >> 16) & 255;
				$g = ($rgb >> 8) & 255;
				$b = $rgb & 255;
				if ($this->isSkinTone($r, $g, $b)) {
					continue;
				}
				$lum = $this->luma($r, $g, $b);
				$maxc = max($r, $g, $b);
				$minc = min($r, $g, $b);
				$sat = $maxc === 0 ? 0.0 : ($maxc - $minc) / $maxc;
				if (($lum <= 80 && $sat < 0.22) || ($lum >= 205 && $sat < 0.08) || ($lum <= 160 && $sat < 0.08)) {
					imagesetpixel($im, $x, $y, $white);
				}
			}
		}
	}

	/** @return array{0:int,1:int,2:int} */
	private function sampleCornerRgb($im, int $w, int $h): array
	{
		$box = max(4, (int) floor(min($w, $h) * 0.10));
		$step = max(1, (int) floor($box / 8));
		$regions = [
			[0, 0],
			[$w - $box, 0],
			[0, $h - $box],
			[$w - $box, $h - $box],
			[(int) floor($w * 0.08), 0],
			[(int) floor($w * 0.82) - $box, 0],
		];
		$rs = [];
		$gs = [];
		$bs = [];
		foreach ($regions as $rg) {
			for ($y = $rg[1]; $y < $rg[1] + $box && $y < $h; $y += $step) {
				for ($x = $rg[0]; $x < $rg[0] + $box && $x < $w; $x += $step) {
					$rgb = imagecolorat($im, max(0, min($w - 1, $x)), max(0, min($h - 1, $y))) & 0xFFFFFF;
					$r = ($rgb >> 16) & 255;
					$g = ($rgb >> 8) & 255;
					$b = $rgb & 255;
					$lum = $this->luma($r, $g, $b);
					if ($lum > 232 || $lum < 55) {
						continue;
					}
					if ($this->isSkinTone($r, $g, $b)) {
						continue;
					}
					$rs[] = $r;
					$gs[] = $g;
					$bs[] = $b;
				}
			}
		}
		if (count($rs) < 6) {
			return [150, 148, 142];
		}
		sort($rs);
		sort($gs);
		sort($bs);
		$mid = intdiv(count($rs), 2);
		return [$rs[$mid], $gs[$mid], $bs[$mid]];
	}

	private function isBackdropPixel(int $r, int $g, int $b): bool
	{
		if ($r > 246 && $g > 246 && $b > 246) {
			return true;
		}
		$lum = $this->luma($r, $g, $b);
		$maxc = max($r, $g, $b);
		$minc = min($r, $g, $b);
		$sat = $maxc === 0 ? 0.0 : ($maxc - $minc) / $maxc;
		return $lum >= 205 && $sat < 0.08;
	}

	private function isWallPixel(int $r, int $g, int $b, array $wall): bool
	{
		$dist = abs($r - $wall[0]) + abs($g - $wall[1]) + abs($b - $wall[2]);
		if ($dist <= 70) {
			return true;
		}
		$lum = $this->luma($r, $g, $b);
		$wallLum = $this->luma($wall[0], $wall[1], $wall[2]);
		$maxc = max($r, $g, $b);
		$minc = min($r, $g, $b);
		$sat = $maxc === 0 ? 0.0 : ($maxc - $minc) / $maxc;
		return $sat < 0.14 && $dist <= 100 && abs($lum - $wallLum) <= 20 && $lum >= 110;
	}

	private function isProtectedPerson(int $r, int $g, int $b, array $wall): bool
	{
		if ($this->isSkinTone($r, $g, $b)) {
			return true;
		}
		$lum = $this->luma($r, $g, $b);
		$wallLum = $this->luma($wall[0], $wall[1], $wall[2]);
		if ($lum < 58 || $lum < $wallLum - 16) {
			return true;
		}
		$dist = abs($r - $wall[0]) + abs($g - $wall[1]) + abs($b - $wall[2]);
		$maxc = max($r, $g, $b);
		$minc = min($r, $g, $b);
		$sat = $maxc === 0 ? 0.0 : ($maxc - $minc) / $maxc;
		return $sat > 0.22 && $dist > 70;
	}

	private function isSkinTone(int $r, int $g, int $b): bool
	{
		$maxc = max($r, $g, $b);
		$minc = min($r, $g, $b);
		if (($maxc - $minc) < 18) {
			return false;
		}
		if ($r <= $g + 4) {
			return false;
		}
		$rg = $r - $g;
		$gb = $g - $b;
		$sat = $maxc === 0 ? 0.0 : ($maxc - $minc) / $maxc;
		$lum = $this->luma($r, $g, $b);
		if ($rg < 12 && $gb < 18 && $lum > 100 && $sat < 0.22) {
			return false;
		}
		if ($lum < 22 || $lum > 210) {
			return false;
		}
		return ($r - $b) >= 10;
	}

	/**
	 * Soften only wall-colored pixels that are almost surrounded by white, outside the face core.
	 *
	 * @param resource|\GdImage $im
	 */
	private function featherWallEdge($im, int $w, int $h, array $wall, int $white, float $cx, float $cy, float $rx, float $ry): void
	{
		$mark = [];
		for ($y = 1; $y < $h - 1; $y++) {
			for ($x = 1; $x < $w - 1; $x++) {
				$nx = ($x - $cx) / $rx;
				$ny = ($y - $cy) / $ry;
				if (($nx * $nx + $ny * $ny) < 1.0) {
					continue;
				}
				$rgb = imagecolorat($im, $x, $y) & 0xFFFFFF;
				if ($rgb === 0xFFFFFF) {
					continue;
				}
				$r = ($rgb >> 16) & 255;
				$g = ($rgb >> 8) & 255;
				$b = $rgb & 255;
				$dist = abs($r - $wall[0]) + abs($g - $wall[1]) + abs($b - $wall[2]);
				if ($dist > 80 || $this->isProtectedPerson($r, $g, $b, $wall)) {
					continue;
				}
				$n = 0;
				for ($oy = -1; $oy <= 1; $oy++) {
					for ($ox = -1; $ox <= 1; $ox++) {
						if ($ox === 0 && $oy === 0) {
							continue;
						}
						if ((imagecolorat($im, $x + $ox, $y + $oy) & 0xFFFFFF) === 0xFFFFFF) {
							$n++;
						}
					}
				}
				if ($n >= 6) {
					$mark[] = [$x, $y];
				}
			}
		}
		foreach ($mark as $p) {
			imagesetpixel($im, $p[0], $p[1], $white);
		}
	}

	private function luma(int $r, int $g, int $b): float
	{
		return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
	}

	private function jpegForAi($src): ?string
	{
		$sw = imagesx($src);
		$sh = imagesy($src);
		$max = 1024;
		$scale = min(1.0, $max / max(1, $sw), $max / max(1, $sh));
		$dw = max(1, (int) round($sw * $scale));
		$dh = max(1, (int) round($sh * $scale));
		$dst = imagecreatetruecolor($dw, $dh);
		$white = imagecolorallocate($dst, 255, 255, 255);
		imagefill($dst, 0, 0, $white);
		$this->hiQualityResample($dst, $src, 0, 0, 0, 0, $dw, $dh, $sw, $sh);
		ob_start();
		imagejpeg($dst, null, 88);
		$out = ob_get_clean();
		imagedestroy($dst);
		return is_string($out) && strlen($out) > 32 ? $out : null;
	}

	/**
	 * @param resource|\GdImage $dst
	 * @param resource|\GdImage $src
	 */
	private function hiQualityResample($dst, $src, int $dx, int $dy, int $sx, int $sy, int $dw, int $dh, int $sw, int $sh): void
	{
		if (function_exists('imagesetinterpolation') && defined('IMG_BICUBIC')) {
			@imagesetinterpolation($dst, IMG_BICUBIC);
		}
		imagecopyresampled($dst, $src, $dx, $dy, $sx, $sy, $dw, $dh, $sw, $sh);
	}

	private function cropOntoWhite($src, int $outW, int $outH, string $fit = 'cover')
	{
		$sw = imagesx($src);
		$sh = imagesy($src);
		if ($sw < 2 || $sh < 2) {
			return null;
		}

		$dst = imagecreatetruecolor($outW, $outH);
		$white = imagecolorallocate($dst, 255, 255, 255);
		imagefill($dst, 0, 0, $white);

		if ($fit === 'contain') {
			$scale = min($outW / $sw, $outH / $sh);
			$nw = max(1, (int) round($sw * $scale));
			$nh = max(1, (int) round($sh * $scale));
			$ox = (int) (($outW - $nw) / 2);
			$oy = (int) (($outH - $nh) / 2);
			$this->hiQualityResample($dst, $src, $ox, $oy, 0, 0, $nw, $nh, $sw, $sh);
			return $dst;
		}

		$targetRatio = $outW / max(1, $outH);
		$srcRatio = $sw / max(1, $sh);
		if ($srcRatio > $targetRatio) {
			$cropH = $sh;
			$cropW = (int) round($sh * $targetRatio);
			$sx = (int) max(0, ($sw - $cropW) / 2);
			$sy = 0;
		} else {
			$cropW = $sw;
			$cropH = (int) round($sw / $targetRatio);
			$sx = 0;
			// Keep the top of the head / cap; crop extra from the bottom.
			$sy = (int) max(0, ($sh - $cropH) * 0.08);
		}
		$cropW = max(1, min($sw - $sx, $cropW));
		$cropH = max(1, min($sh - $sy, $cropH));
		$this->hiQualityResample($dst, $src, 0, 0, $sx, $sy, $outW, $outH, $cropW, $cropH);
		return $dst;
	}
}
