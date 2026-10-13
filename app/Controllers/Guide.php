<?php

namespace App\Controllers;

use CodeIgniter\Controller;

/**
 * Spoken steps for the public staff support guide.
 * The Gemini key stays on the server. Only the fixed step list can be spoken.
 */
class Guide extends Controller
{
	public function voice($step = '')
	{
		$step = strtolower(preg_replace('/[^a-z0-9_-]/', '', (string) $step) ?? '');
		$lines = self::lines();
		if (!isset($lines[$step])) {
			return $this->response->setStatusCode(404)->setBody('Unknown step');
		}

		$dir = WRITEPATH . 'cache/guide-voice';
		if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
			return $this->response->setStatusCode(503)->setBody('Voice unavailable');
		}
		$file = $dir . '/' . $step . '.wav';
		if (!is_file($file) || filesize($file) < 800) {
			$made = $this->synthesize($lines[$step], $file);
			if (!$made) {
				return $this->response->setStatusCode(503)->setBody('Voice unavailable');
			}
		}

		$this->response->setHeader('Content-Type', 'audio/wav');
		$this->response->setHeader('Cache-Control', 'public, max-age=86400');
		$this->response->setHeader('X-Content-Type-Options', 'nosniff');
		return $this->response->setBody((string) file_get_contents($file));
	}

	/** @return array<string, string> */
	public static function lines(): array
	{
		$file = FCPATH . 'guides/staff-system-steps.json';
		if (!is_file($file)) {
			return [];
		}
		$data = json_decode((string) file_get_contents($file), true);
		if (!is_array($data)) {
			return [];
		}
		$lines = [];
		foreach ($data['steps'] ?? [] as $step) {
			if (!is_array($step)) {
				continue;
			}
			$id = strtolower(preg_replace('/[^a-z0-9_-]/', '', (string) ($step['id'] ?? '')) ?? '');
			$say = trim((string) ($step['say'] ?? ''));
			if ($id !== '' && $say !== '') {
				$lines[$id] = $say;
			}
		}
		return $lines;
	}

	private function synthesize(string $line, string $file): bool
	{
		$key = trim((string) (env('GOOGLE_AI_API_KEY') ?: env('GEMINI_API_KEY') ?: ''));
		if ($key === '') {
			return false;
		}
		$model = trim((string) (env('GEMINI_TTS_MODEL') ?: 'gemini-2.5-flash-preview-tts'));
		$voice = trim((string) (env('GEMINI_TTS_VOICE') ?: 'Charon'));
		if ($model === '') {
			$model = 'gemini-2.5-flash-preview-tts';
		}
		if ($voice === '') {
			$voice = 'Charon';
		}
		$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent';
		$body = json_encode([
			'contents' => [[
				'role' => 'user',
				'parts' => [[
					'text' => 'Say in a calm, clear teaching voice, at a moderate pace, with a short pause between sentences: ' . $line,
				]],
			]],
			'generationConfig' => [
				'responseModalities' => ['AUDIO'],
				'speechConfig' => [
					'voiceConfig' => [
						'prebuiltVoiceConfig' => ['voiceName' => $voice],
					],
				],
			],
		], JSON_UNESCAPED_UNICODE);
		$ch = curl_init($url);
		curl_setopt_array($ch, [
			CURLOPT_POST => true,
			CURLOPT_HTTPHEADER => [
				'Content-Type: application/json; charset=utf-8',
				'x-goog-api-key: ' . $key,
			],
			CURLOPT_POSTFIELDS => $body,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 60,
			CURLOPT_CONNECTTIMEOUT => 12,
		]);
		$raw = curl_exec($ch);
		$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		if (!is_string($raw) || $raw === '' || $code >= 400) {
			return false;
		}
		$json = json_decode($raw, true);
		$parts = $json['candidates'][0]['content']['parts'] ?? [];
		if (!is_array($parts)) {
			return false;
		}
		foreach ($parts as $part) {
			if (!is_array($part)) {
				continue;
			}
			$inline = $part['inlineData'] ?? $part['inline_data'] ?? null;
			if (!is_array($inline) || empty($inline['data'])) {
				continue;
			}
			$bin = base64_decode((string) $inline['data'], true);
			if (!is_string($bin) || $bin === '') {
				continue;
			}
			$mime = (string) ($inline['mimeType'] ?? $inline['mime_type'] ?? '');
			if (stripos($mime, 'wav') !== false) {
				return file_put_contents($file, $bin) !== false;
			}
			$rate = 24000;
			if (preg_match('/rate=(\d+)/', $mime, $match)) {
				$rate = max(8000, (int) $match[1]);
			}
			return file_put_contents($file, $this->wav($bin, $rate)) !== false;
		}
		return false;
	}

	private function wav(string $pcm, int $rate): string
	{
		$length = strlen($pcm);
		return pack('a4Va4a4VvvVVvva4V', 'RIFF', 36 + $length, 'WAVE', 'fmt ', 16, 1, 1, $rate, $rate * 2, 2, 16, 'data', $length)
			. $pcm;
	}
}
