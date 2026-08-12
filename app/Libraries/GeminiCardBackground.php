<?php

namespace App\Libraries;

/**
 * Generate school ID-card backgrounds via Gemini image models.
 * Analyzes the active card template layout (keep-out zones for text/photo),
 * then generates a full-bleed CR80 background with soft lightening under text.
 */
class GeminiCardBackground
{
	/** @var string */
	private $lastError = '';

	public function lastError(): string
	{
		return $this->lastError;
	}

	public function isConfigured(): bool
	{
		return $this->apiKey() !== '';
	}

	public function apiKey(): string
	{
		$key = trim((string)(env('GOOGLE_AI_API_KEY') ?: env('GEMINI_API_KEY') ?: ''));
		return trim($key, " \t\"'");
	}

	/** @return list<string> */
	public function models(): array
	{
		$primary = trim((string)(env('GEMINI_IMAGE_MODEL') ?: env('GOOGLE_AI_IMAGE_MODEL') ?: ''));
		$candidates = array_filter([
			$primary,
			'gemini-2.5-flash-image',
			'gemini-2.5-flash-image-preview',
			'gemini-2.0-flash-preview-image-generation',
			'gemini-2.0-flash-exp',
		]);
		return array_values(array_unique($candidates));
	}

	public function aspectForOrientation(string $orientation): string
	{
		return strtolower($orientation) === 'portrait' ? '2:3' : '3:2';
	}

	public function pixelSize(string $orientation): array
	{
		return strtolower($orientation) === 'portrait'
			? ['w' => 640, 'h' => 1016]
			: ['w' => 1016, 'h' => 640];
	}

	/**
	 * Analyze template field positions → keep-out zones + decoration bands.
	 *
	 * @param array<string,array{x?:float,y?:float,w?:float,h?:float,visible?:bool}> $fields
	 * @return array{
	 *   template:string,orientation:string,accent:string,
	 *   keepouts:list<array{x:float,y:float,w:float,h:float,key:string}>,
	 *   content_box:array{x:float,y:float,w:float,h:float},
	 *   decorate:array{top:float,bottom:float,left:float,right:float},
	 *   prompt_block:string
	 * }
	 */
	public function analyzeTemplate(
		string $template,
		string $orientation,
		array $fields = [],
		string $audience = 'student',
		string $brandAccent = ''
	): array {
		$template = CardLayout::normalizeTemplate($template);
		$orientation = CardLayout::normalizeOrientation($orientation);
		$meta = CardLayout::TEMPLATES[$template] ?? CardLayout::TEMPLATES['ocean'];
		$tplAccent = (string)($meta['accent'] ?? '#0EA5E9');
		$accent = preg_match('/^#[0-9A-Fa-f]{3,8}$/', trim($brandAccent)) ? trim($brandAccent) : $tplAccent;

		$resolved = $audience === 'staff'
			? CardLayout::staffDefaults($template, $orientation)
			: ($audience === 'visitor'
				? CardLayout::visitorDefaults($template, $orientation)
				: CardLayout::defaults($template, $orientation));
		$baseFields = $resolved['fields'];
		foreach ($fields as $key => $cfg) {
			if (!is_array($cfg) || !isset($baseFields[$key])) {
				continue;
			}
			$baseFields[$key] = array_merge($baseFields[$key], [
				'x' => (float)($cfg['x'] ?? $baseFields[$key]['x']),
				'y' => (float)($cfg['y'] ?? $baseFields[$key]['y']),
				'w' => (float)($cfg['w'] ?? $baseFields[$key]['w']),
				'h' => (float)($cfg['h'] ?? $baseFields[$key]['h']),
				'visible' => array_key_exists('visible', $cfg) ? (bool)$cfg['visible'] : (bool)$baseFields[$key]['visible'],
			]);
		}

		$contentKeys = ['logo', 'school_name', 'header1', 'header2', 'badge', 'photo', 'names', 'regno', 'class', 'father', 'phone', 'mode', 'post', 'email', 'staff_id', 'relationship', 'student_name', 'student_class', 'card_uid', 'moto'];
		$keepouts = [];
		$minX = 100.0;
		$minY = 100.0;
		$maxX = 0.0;
		$maxY = 0.0;
		$pad = 2.5;
		foreach ($contentKeys as $key) {
			$f = $baseFields[$key] ?? null;
			if (!$f || empty($f['visible'])) {
				continue;
			}
			$x = max(0, (float)$f['x'] - $pad);
			$y = max(0, (float)$f['y'] - $pad);
			$w = min(100 - $x, (float)$f['w'] + $pad * 2);
			$h = min(100 - $y, (float)$f['h'] + $pad * 2);
			$keepouts[] = ['x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'key' => $key];
			$minX = min($minX, $x);
			$minY = min($minY, $y);
			$maxX = max($maxX, $x + $w);
			$maxY = max($maxY, $y + $h);
		}
		if (!$keepouts) {
			$minX = 8;
			$minY = 10;
			$maxX = 92;
			$maxY = 88;
			$keepouts[] = ['x' => $minX, 'y' => $minY, 'w' => $maxX - $minX, 'h' => $maxY - $minY, 'key' => 'content'];
		}

		$contentBox = [
			'x' => max(0, $minX - 1),
			'y' => max(0, $minY - 1),
			'w' => min(100, $maxX - $minX + 2),
			'h' => min(100, $maxY - $minY + 2),
		];

		$decorate = [
			'top' => max(8.0, min(28.0, $contentBox['y'] + 6)),
			'bottom' => max(8.0, min(28.0, 100 - ($contentBox['y'] + $contentBox['h']) + 6)),
			'left' => max(5.0, min(18.0, $contentBox['x'] + 4)),
			'right' => max(5.0, min(18.0, 100 - ($contentBox['x'] + $contentBox['w']) + 4)),
		];

		$lines = [];
		$lines[] = "TEMPLATE ANALYSIS — \"{$meta['label']}\" ({$template}), {$orientation} CR80 PVC ID card for {$audience}.";
		$lines[] = "Brand accent color: {$accent} (use light/pastel versions throughout).";
		$lines[] = "FULL-BLEED REQUIREMENT: the artwork MUST fill the ENTIRE card edge-to-edge (0–100% width and height). Do NOT leave a large empty white void. Decorative waves/shapes/gradients should wrap the card like a finished PVC print.";
		$lines[] = "TEXT LEGIBILITY: behind these field boxes, keep the fill LIGHT (soft white or very pale tint of {$accent}) so dark text remains readable — but the overall design must still look continuous, not a white rectangle floating on a footer strip:";
		foreach ($keepouts as $z) {
			$lines[] = sprintf(
				"- %s zone: left %.0f%%, top %.0f%%, width %.0f%%, height %.0f%% → keep light/pale under this box",
				$z['key'],
				$z['x'],
				$z['y'],
				$z['w'],
				$z['h']
			);
		}
		$lines[] = "Compose rich edge and corner decoration (top ~{$decorate['top']}%, bottom ~{$decorate['bottom']}%, sides) that flows into the card, while mid zones stay lighter for text/photo.";
		$lines[] = "Absolutely no readable text, numbers, percentages, watermarks, logos, people, barcodes, or QR codes.";

		return [
			'template' => $template,
			'orientation' => $orientation,
			'accent' => $accent,
			'keepouts' => $keepouts,
			'content_box' => $contentBox,
			'decorate' => $decorate,
			'prompt_block' => implode("\n", $lines),
			'fields' => $baseFields,
		];
	}

	/** Full-bleed modern styles that fill the CR80 card */
	public function proposalStyles(string $audience = 'student', string $accent = '#0EA5E9'): array
	{
		$who = $audience === 'staff'
			? 'staff identity card'
			: ($audience === 'visitor' ? 'parent visitor card' : 'student identity card');
		return [
			[
				'id' => 'soft-waves',
				'label' => 'Soft Waves',
				'brief' => "FULL-BLEED {$who} background: layered soft teal/mint waves of {$accent} flowing across top AND bottom thirds, wrapping the card edge-to-edge. Center stays lightly tinted white for photo/text — not a bare empty white sheet with only a footer strip.",
			],
			[
				'id' => 'geo-minimal',
				'label' => 'Geo Minimal',
				'brief' => "FULL-BLEED {$who} background: geometric corner frames and subtle diagonal bands in pastel {$accent} covering the whole card canvas. Soft pale center for content. Fill the entire rectangle.",
			],
			[
				'id' => 'paper-gradient',
				'label' => 'Paper Gradient',
				'brief' => "FULL-BLEED {$who} background: soft paper gradient from light {$accent} at edges into near-white center, with delicate education motifs in corners. Edge-to-edge coverage, no empty white void.",
			],
		];
	}

	/**
	 * @param array|null $analysis from analyzeTemplate()
	 * @return array{filename:string, path:string, source:string, model?:string, error?:string, style?:string, label?:string}
	 */
	public function generate(
		string $schoolName,
		string $orientation = 'landscape',
		string $accent = '#0EA5E9',
		string $styleBrief = '',
		string $styleId = '',
		string $styleLabel = '',
		?array $analysis = null
	): array {
		$dir = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'background' . DIRECTORY_SEPARATOR;
		if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
			throw new \RuntimeException('Background folder is not writable.');
		}
		if (!is_writable($dir)) {
			@chmod($dir, 0775);
		}

		$orientation = strtolower($orientation) === 'portrait' ? 'portrait' : 'landscape';
		$aspect = $this->aspectForOrientation($orientation);
		$filename = 'ai_' . ($styleId !== '' ? preg_replace('/[^a-z0-9_-]+/i', '', $styleId) . '_' : '') . bin2hex(random_bytes(6)) . '.png';
		$path = $dir . $filename;

		$errors = [];
		if ($this->isConfigured()) {
			foreach ($this->models() as $model) {
				try {
					$bytes = $this->requestGeminiImage($model, $schoolName, $orientation, $accent, $aspect, $styleBrief, $analysis);
					if ($bytes !== null && strlen($bytes) > 200) {
						$bytes = $this->normalizeToCardSize($bytes, $orientation) ?? $bytes;
						if ($analysis) {
							$bytes = $this->softTextLegibility($bytes, $orientation, $analysis) ?? $bytes;
						}
						if (file_put_contents($path, $bytes) !== false) {
							@chmod($path, 0664);
							$this->lastError = '';
							return [
								'filename' => $filename,
								'path' => $path,
								'source' => 'ai',
								'model' => $model,
								'style' => $styleId,
								'label' => $styleLabel,
							];
						}
					}
					$errors[] = "{$model}: empty image bytes";
				} catch (\Throwable $e) {
					$errors[] = "{$model}: " . $e->getMessage();
					log_message('error', 'AI card background [' . $model . ']: ' . $e->getMessage());
				}
			}
		} else {
			$errors[] = 'No API key configured';
		}

		$this->lastError = implode(' | ', $errors);
		$this->writeAnalyzedFallbackPng($path, $orientation, $accent, $analysis);
		return [
			'filename' => $filename,
			'path' => $path,
			'source' => 'fallback',
			'error' => $this->lastError,
			'style' => $styleId,
			'label' => $styleLabel,
		];
	}

	/**
	 * @param array|null $analysis
	 * @return list<array{filename:string,url:string,source:string,style:string,label:string,model?:string,error?:string}>
	 */
	public function generateProposals(
		string $schoolName,
		string $orientation = 'landscape',
		string $accent = '#0EA5E9',
		string $audience = 'student',
		int $count = 3,
		string $template = 'ocean',
		array $fields = [],
		bool $shuffle = false
	): array {
		$analysis = $this->analyzeTemplate($template, $orientation, $fields, $audience, $accent);
		$accent = $analysis['accent'];
		$styles = $this->proposalStyles($audience, $accent);
		if ($shuffle) {
			shuffle($styles);
		}
		$count = max(3, min(5, $count));
		$out = [];
		for ($i = 0; $i < $count; $i++) {
			$style = $styles[$i % count($styles)];
			$result = $this->generate(
				$schoolName,
				$orientation,
				$accent,
				$style['brief'],
				$style['id'] . '_' . ($i + 1) . ($shuffle ? 'r' : ''),
				$style['label'],
				$analysis
			);
			$out[] = [
				'filename' => $result['filename'],
				'url' => base_url('assets/images/background/' . $result['filename']),
				'source' => $result['source'],
				'style' => $style['id'],
				'label' => $style['label'],
				'error' => $result['error'] ?? null,
			];
		}
		return $out;
	}

	private function requestGeminiImage(
		string $model,
		string $schoolName,
		string $orientation,
		string $accent,
		string $aspect,
		string $styleBrief = '',
		?array $analysis = null
	): ?string {
		$key = $this->apiKey();
		$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent';

		$style = trim($styleBrief) !== ''
			? $styleBrief
			: 'Soft professional edge decoration with accent ' . $accent . '.';

		$analysisBlock = is_array($analysis) ? (string)($analysis['prompt_block'] ?? '') : '';

		$prompt = "You are designing a printable FULL-BLEED CR80 school ID CARD BACKGROUND.\n"
			. "Orientation: {$orientation}. Aspect: {$aspect}. School: \"{$schoolName}\".\n\n"
			. "The image must FILL the entire card rectangle edge-to-edge — like a finished PVC print. "
			. "Do NOT produce a mostly blank white card with only a thin decorative strip at the bottom.\n\n"
			. "STEP 1 — Read the template field map and keep those zones LIGHT for text readability.\n"
			. "STEP 2 — Choose soft pastel colors from {$accent} for waves/shapes that wrap top, sides, and bottom.\n"
			. "STEP 3 — Generate ONE full-bleed background that matches the template proportions.\n\n"
			. ($analysisBlock !== '' ? $analysisBlock . "\n\n" : '')
			. "STYLE DIRECTION: {$style}\n\n"
			. "HARD RULES:\n"
			. "- Full card coverage (edge-to-edge).\n"
			. "- Light/pale under text and photo zones; richer decoration on borders.\n"
			. "- No readable text, numbers, percentages, logos, people, barcodes, or QR codes.\n"
			. "- Not dark navy across the middle.\n"
			. "- Output a single flat background image only.";

		$payload = [
			'contents' => [[
				'role' => 'user',
				'parts' => [['text' => $prompt]],
			]],
			'generationConfig' => [
				'responseModalities' => ['TEXT', 'IMAGE'],
				'imageConfig' => [
					'aspectRatio' => $aspect,
				],
			],
		];

		$ch = curl_init($url);
		curl_setopt_array($ch, [
			CURLOPT_POST => true,
			CURLOPT_HTTPHEADER => [
				'Content-Type: application/json',
				'x-goog-api-key: ' . $key,
			],
			CURLOPT_POSTFIELDS => json_encode($payload),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 120,
			CURLOPT_CONNECTTIMEOUT => 20,
		]);
		$raw = curl_exec($ch);
		$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$err = curl_error($ch);
		curl_close($ch);

		if ($raw === false) {
			throw new \RuntimeException('curl failed: ' . $err);
		}

		if ($code >= 400 && (stripos($raw, 'imageConfig') !== false || stripos($raw, 'Unknown name') !== false || stripos($raw, 'aspectRatio') !== false)) {
			$payload['generationConfig']['imageConfig']['aspectRatio'] = $orientation === 'portrait' ? '3:4' : '4:3';
			$ch = curl_init($url);
			curl_setopt_array($ch, [
				CURLOPT_POST => true,
				CURLOPT_HTTPHEADER => [
					'Content-Type: application/json',
					'x-goog-api-key: ' . $key,
				],
				CURLOPT_POSTFIELDS => json_encode($payload),
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_TIMEOUT => 120,
			]);
			$raw = curl_exec($ch);
			$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$err = curl_error($ch);
			curl_close($ch);
			if ($raw === false) {
				throw new \RuntimeException('curl failed: ' . $err);
			}
		}

		if ($code >= 400 && (stripos($raw, 'imageConfig') !== false || stripos($raw, 'Unknown name') !== false)) {
			unset($payload['generationConfig']['imageConfig']);
			$ch = curl_init($url);
			curl_setopt_array($ch, [
				CURLOPT_POST => true,
				CURLOPT_HTTPHEADER => [
					'Content-Type: application/json',
					'x-goog-api-key: ' . $key,
				],
				CURLOPT_POSTFIELDS => json_encode($payload),
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_TIMEOUT => 120,
			]);
			$raw = curl_exec($ch);
			$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$err = curl_error($ch);
			curl_close($ch);
			if ($raw === false) {
				throw new \RuntimeException('curl failed: ' . $err);
			}
		}

		if ($code >= 400) {
			$msg = substr(preg_replace('/\s+/', ' ', $raw), 0, 280);
			throw new \RuntimeException("HTTP {$code}: {$msg}");
		}

		$data = json_decode($raw, true);
		if (!is_array($data)) {
			throw new \RuntimeException('Invalid JSON from AI service');
		}
		if (!empty($data['error']['message'])) {
			throw new \RuntimeException((string)$data['error']['message']);
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

		$json = json_encode($data);
		if (preg_match('/"data"\s*:\s*"([A-Za-z0-9+\/=]{400,})"/', (string)$json, $m)) {
			$bin = base64_decode($m[1], true);
			if ($bin !== false && strlen($bin) > 200) {
				return $bin;
			}
		}

		throw new \RuntimeException('AI response had no image data');
	}

	/**
	 * Gently lighten text/photo slots for readability without wiping the full-bleed design.
	 */
	private function softTextLegibility(string $bytes, string $orientation, array $analysis): ?string
	{
		if (!function_exists('imagecreatefromstring')) {
			return null;
		}
		$im = @imagecreatefromstring($bytes);
		if ($im === false) {
			return null;
		}
		$w = imagesx($im);
		$h = imagesy($im);

		$textKeys = ['names', 'regno', 'class', 'father', 'phone', 'mode', 'post', 'email', 'staff_id', 'badge', 'school_name', 'header1', 'header2', 'moto'];
		foreach ($analysis['keepouts'] ?? [] as $z) {
			$key = (string)($z['key'] ?? '');
			// Photo: very light lift only; text: moderate lift
			if ($key === 'photo') {
				$strength = 0.18;
			} elseif (in_array($key, $textKeys, true)) {
				$strength = 0.28;
			} else {
				continue;
			}
			$zx0 = (int)round($w * ((float)$z['x'] / 100));
			$zy0 = (int)round($h * ((float)$z['y'] / 100));
			$zx1 = (int)round($w * (((float)$z['x'] + (float)$z['w']) / 100));
			$zy1 = (int)round($h * (((float)$z['y'] + (float)$z['h']) / 100));
			$zx0 = max(0, min($w - 1, $zx0));
			$zy0 = max(0, min($h - 1, $zy0));
			$zx1 = max($zx0 + 1, min($w, $zx1));
			$zy1 = max($zy0 + 1, min($h, $zy1));
			for ($y = $zy0; $y < $zy1; $y++) {
				for ($x = $zx0; $x < $zx1; $x++) {
					$rgb = imagecolorat($im, $x, $y);
					$r = ($rgb >> 16) & 0xFF;
					$g = ($rgb >> 8) & 0xFF;
					$b = $rgb & 0xFF;
					$nr = (int)round($r + (255 - $r) * $strength);
					$ng = (int)round($g + (255 - $g) * $strength);
					$nb = (int)round($b + (255 - $b) * $strength);
					imagesetpixel($im, $x, $y, imagecolorallocate($im, $nr, $ng, $nb));
				}
			}
		}

		ob_start();
		imagepng($im);
		$out = ob_get_clean();
		imagedestroy($im);
		return $out !== false ? $out : null;
	}

	private function normalizeToCardSize(string $bytes, string $orientation): ?string
	{
		if (!function_exists('imagecreatefromstring')) {
			return null;
		}
		$src = @imagecreatefromstring($bytes);
		if ($src === false) {
			return null;
		}
		$size = $this->pixelSize($orientation);
		$w = $size['w'];
		$h = $size['h'];
		$dst = imagecreatetruecolor($w, $h);
		$white = imagecolorallocate($dst, 255, 255, 255);
		imagefilledrectangle($dst, 0, 0, $w - 1, $h - 1, $white);

		$sw = imagesx($src);
		$sh = imagesy($src);
		$scale = max($w / max(1, $sw), $h / max(1, $sh));
		$nw = (int) round($sw * $scale);
		$nh = (int) round($sh * $scale);
		$dx = (int) round(($w - $nw) / 2);
		$dy = (int) round(($h - $nh) / 2);
		imagecopyresampled($dst, $src, $dx, $dy, 0, 0, $nw, $nh, $sw, $sh);
		ob_start();
		imagepng($dst);
		$out = ob_get_clean();
		imagedestroy($src);
		imagedestroy($dst);
		return $out !== false ? $out : null;
	}

	/** Fallback: full-bleed soft accent wash matching CR80 */
	private function writeAnalyzedFallbackPng(string $path, string $orientation, string $accent, ?array $analysis): void
	{
		$size = $this->pixelSize($orientation);
		$im = imagecreatetruecolor($size['w'], $size['h']);
		$white = imagecolorallocate($im, 255, 255, 255);
		imagefilledrectangle($im, 0, 0, $size['w'] - 1, $size['h'] - 1, $white);

		[$r, $g, $b] = $this->hexToRgb($accent);
		$topH = (int)round($size['h'] * 0.28);
		$botH = (int)round($size['h'] * 0.26);
		for ($i = 0; $i < $topH; $i++) {
			$a = 0.32 * (1 - $i / max(1, $topH));
			$col = imagecolorallocate(
				$im,
				(int)(255 * (1 - $a) + $r * $a),
				(int)(255 * (1 - $a) + $g * $a),
				(int)(255 * (1 - $a) + $b * $a)
			);
			imageline($im, 0, $i, $size['w'], $i, $col);
		}
		for ($i = 0; $i < $botH; $i++) {
			$a = 0.34 * (1 - $i / max(1, $botH));
			$y = $size['h'] - 1 - $i;
			$col = imagecolorallocate(
				$im,
				(int)(255 * (1 - $a) + $r * $a),
				(int)(255 * (1 - $a) + $g * $a),
				(int)(255 * (1 - $a) + $b * $a)
			);
			imageline($im, 0, $y, $size['w'], $y, $col);
		}
		// Soft side fades so the whole card feels filled
		$side = (int)round($size['w'] * 0.12);
		for ($i = 0; $i < $side; $i++) {
			$a = 0.12 * (1 - $i / max(1, $side));
			$col = imagecolorallocate(
				$im,
				(int)(255 * (1 - $a) + $r * $a),
				(int)(255 * (1 - $a) + $g * $a),
				(int)(255 * (1 - $a) + $b * $a)
			);
			imageline($im, $i, 0, $i, $size['h'], $col);
			imageline($im, $size['w'] - 1 - $i, 0, $size['w'] - 1 - $i, $size['h'], $col);
		}

		imagepng($im, $path);
		imagedestroy($im);
		@chmod($path, 0664);
	}

	/** @return array{0:int,1:int,2:int} */
	private function hexToRgb(string $hex): array
	{
		$hex = ltrim(trim($hex), '#');
		if (strlen($hex) === 3) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if (strlen($hex) < 6) {
			return [14, 165, 233];
		}
		return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
	}
}
