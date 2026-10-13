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
		return [
			'email' => 'On the sign-in page, click the email box and type the staff email. Recover Password is there if they have forgotten it.',
			'pass' => 'Click the password box and type the password. It must be at least 6 characters. Keep me logged in is optional.',
			'login' => 'Click Login to Dashboard. The next screen shows the school name and the active term.',
			'photo' => 'Settings and My profile are under the photo at the top right, not in the left menu. Click the photo.',
			'settings' => 'Click Settings. Only posts allowed to manage the school see this. Change active term is in the same menu.',
			'basic' => 'Click Basic school info. Double-click a value to edit it, then press Enter or click outside to save. Escape cancels.',
			'logo' => 'Click Logo and signatures for the school logo and the Head Teacher signature. The blocks below cover grades, attendance, and the SMS provider.',
			'profile' => 'Open the photo menu again and click My profile. Every staff member can open this.',
			'identity' => 'On My profile, choose Rwandan or Non-Rwandan, upload the ID or passport, then click Save identity.',
			'dash' => 'Click Dashboard any time to return home. The panel depends on the staff post.',
			'students' => 'Open Students, then View students. Register, pending review, cards, photos, and dormitories are in the same group when allowed.',
			'classes' => 'Click Classes to open the class list. Marks and attendance start from a class.',
			'course' => 'Open Course, then Manage Course. Category groups subjects, and Create Course adds one.',
			'behavior' => 'Open Behavior, then Record Entry, to write a discipline case. Discipline Record lists them later.',
			'permits' => 'Open Permissions, then Permission Entry, when a student is allowed out. Permission report lists them.',
			'visit' => 'Daily visiting shows who is currently inside. Parent visiting is for assigning visitors and printing their cards.',
			'marks' => 'Open Marks, then Marks Entry. Marks sheet and progress reports are in the same menu.',
			'msg' => 'Open Messaging, then Parents messaging, to send SMS to families. Employees messaging sends to staff.',
			'att' => 'Under Attendance, click Record attendance for the class. The report shows who was absent.',
			'staffs' => 'Under HR, open Staffs, then View all staffs. IDs PDF is on that page for the posts allowed to download it.',
			'finance' => 'Open Finance, then School fees management. Fees Entry records a payment, and Fees report is the summary.',
			'leave' => 'Leave Application is where staff ask for leave. Pocket Money, Canteen, and Prep are further down when the post is allowed. Logout is under the photo.',
		];
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
