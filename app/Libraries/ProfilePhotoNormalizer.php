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

		$mode = strtolower($fit) === 'cover' ? 'cover' : 'contain';
		$out = $this->cropOntoWhite($src, self::WIDTH, self::HEIGHT, $mode);
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
