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

	public function saveFromFile(string $srcPath, string $destPath, bool $useAi = true, string $fit = 'contain'): bool
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
		return $this->saveFromBytes($bytes, $destPath, $useAi, $fit);
	}

	public function saveFromBytes(string $bytes, string $destPath, bool $useAi = true, string $fit = 'contain'): bool
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

		$this->whitenBackdropInPlace($src);
		$mode = strtolower($fit);
		if ($mode === 'circle') {
			$out = $this->tightCoverOntoWhite($src, self::CIRCLE, self::CIRCLE);
		} else {
			$outFit = $mode === 'cover' ? 'cover' : 'contain';
			$out = $this->cropOntoWhite($src, self::WIDTH, self::HEIGHT, $outFit);
		}
		imagedestroy($src);
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
		$ok = @imagejpeg($out, $tmp, 92);
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
	 * Build a square portrait that fills a circular card hole: white backdrop, person zoomed to fit.
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
		$copy = imagecreatetruecolor(imagesx($src), imagesy($src));
		if ($copy === false) {
			return null;
		}
		imagecopy($copy, $src, 0, 0, 0, 0, imagesx($src), imagesy($src));
		$this->whitenBackdropInPlace($copy);
		$out = $this->tightCoverOntoWhite($copy, $size, $size);
		imagedestroy($copy);
		return $out;
	}

	/**
	 * Replace a dark / studio backdrop (edge-connected) with pure white. Keeps skin and clothing.
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

		$border = $this->sampleBorderRgb($im, $w, $h);
		$borderLum = $this->luma($border[0], $border[1], $border[2]);
		if ($borderLum > 205) {
			return;
		}

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

		$white = imagecolorallocate($im, 255, 255, 255);
		while ($qi < count($queue)) {
			$i = $queue[$qi++];
			$x = $i % $w;
			$y = intdiv($i, $w);
			$rgb = imagecolorat($im, $x, $y) & 0xFFFFFF;
			$r = ($rgb >> 16) & 255;
			$g = ($rgb >> 8) & 255;
			$b = $rgb & 255;
			if ($this->isSkinTone($r, $g, $b) || !$this->isBackdropPixel($r, $g, $b, $border)) {
				continue;
			}
			imagesetpixel($im, $x, $y, $white);
			$push($x + 1, $y);
			$push($x - 1, $y);
			$push($x, $y + 1);
			$push($x, $y - 1);
		}
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
		$pad = (int) max(4, round(max($bbox[2], $bbox[3]) * 0.06));
		$sx = max(0, $bbox[0] - $pad);
		$sy = max(0, $bbox[1] - $pad);
		$bw = min($sw - $sx, $bbox[2] + $pad * 2);
		$bh = min($sh - $sy, $bbox[3] + $pad * 2);
		$side = max($bw, $bh);
		if ($side < 8) {
			return $this->cropOntoWhite($src, $outW, $outH, 'cover');
		}
		if ($bw < $side) {
			$sx = max(0, min($sw - $side, $sx - intdiv($side - $bw, 2)));
			$bw = min($side, $sw - $sx);
		}
		if ($bh < $side) {
			$sy = max(0, min($sh - $side, $sy - (int) round(($side - $bh) * 0.18)));
			$bh = min($side, $sh - $sy);
		}
		$crop = max(1, min($bw, $bh, $sw - $sx, $sh - $sy));

		$dst = imagecreatetruecolor($outW, $outH);
		$white = imagecolorallocate($dst, 255, 255, 255);
		imagefill($dst, 0, 0, $white);
		imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $outW, $outH, $crop, $crop);
		return $dst;
	}

	/** @return array{0:int,1:int,2:int,3:int} x,y,w,h */
	private function subjectBBox($im, int $w, int $h): array
	{
		$minX = $w;
		$minY = $h;
		$maxX = 0;
		$maxY = 0;
		$step = max(1, (int) floor(min($w, $h) / 220));
		for ($y = 0; $y < $h; $y += $step) {
			for ($x = 0; $x < $w; $x += $step) {
				$rgb = imagecolorat($im, $x, $y) & 0xFFFFFF;
				$r = ($rgb >> 16) & 255;
				$g = ($rgb >> 8) & 255;
				$b = $rgb & 255;
				if ($r > 246 && $g > 246 && $b > 246) {
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
		return [$minX, $minY, max(1, $maxX - $minX + 1), max(1, $maxY - $minY + 1)];
	}

	/** @return array{0:int,1:int,2:int} */
	private function sampleBorderRgb($im, int $w, int $h): array
	{
		$rs = [];
		$gs = [];
		$bs = [];
		$step = max(1, (int) floor($w / 60));
		$take = static function ($im, int $x, int $y) use (&$rs, &$gs, &$bs) {
			$rgb = imagecolorat($im, $x, $y) & 0xFFFFFF;
			$rs[] = ($rgb >> 16) & 255;
			$gs[] = ($rgb >> 8) & 255;
			$bs[] = $rgb & 255;
		};
		for ($x = 0; $x < $w; $x += $step) {
			$take($im, $x, 0);
			$take($im, $x, $h - 1);
		}
		for ($y = 0; $y < $h; $y += $step) {
			$take($im, 0, $y);
			$take($im, $w - 1, $y);
		}
		sort($rs);
		sort($gs);
		sort($bs);
		$mid = intdiv(count($rs), 2);
		return [$rs[$mid] ?? 20, $gs[$mid] ?? 20, $bs[$mid] ?? 20];
	}

	private function isBackdropPixel(int $r, int $g, int $b, array $border): bool
	{
		$lum = $this->luma($r, $g, $b);
		if ($lum > 168) {
			return false;
		}
		$dist = abs($r - $border[0]) + abs($g - $border[1]) + abs($b - $border[2]);
		if ($dist <= 92 && $lum < 155) {
			return true;
		}
		$maxc = max($r, $g, $b);
		$minc = min($r, $g, $b);
		$sat = $maxc === 0 ? 0 : ($maxc - $minc) / $maxc;
		return $lum < 48 && $sat < 0.28;
	}

	private function isSkinTone(int $r, int $g, int $b): bool
	{
		if ($r < 70 || $g < 25 || $b < 12) {
			return false;
		}
		if ($r <= $g || $r <= $b) {
			return false;
		}
		return ($r - $g) >= 8 && ($r - $b) >= 12;
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
		imagecopyresampled($dst, $src, 0, 0, 0, 0, $dw, $dh, $sw, $sh);
		ob_start();
		imagejpeg($dst, null, 88);
		$out = ob_get_clean();
		imagedestroy($dst);
		return is_string($out) && strlen($out) > 32 ? $out : null;
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
			imagecopyresampled($dst, $src, $ox, $oy, 0, 0, $nw, $nh, $sw, $sh);
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
			$sy = (int) max(0, ($sh - $cropH) * 0.12);
		}
		$cropW = max(1, min($sw - $sx, $cropW));
		$cropH = max(1, min($sh - $sy, $cropH));
		imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $outW, $outH, $cropW, $cropH);
		return $dst;
	}
}
