<?php

namespace App\Libraries;

/**
 * Optional Gemini assist for timetable scheduling tips.
 */
class GeminiTimetable
{
	/** @var string */
	private $lastError = '';

	public function lastError(): string
	{
		return $this->lastError;
	}

	public function isConfigured(): bool
	{
		return (new GeminiAcademicDocs())->isConfigured();
	}

	/**
	 * @param list<string> $warnings
	 */
	public function suggestFixes(array $warnings, array $context = []): ?string
	{
		if (!$this->isConfigured() || $warnings === []) {
			return null;
		}

		$key = trim((string) (env('GOOGLE_AI_API_KEY') ?: env('GEMINI_API_KEY') ?: ''));
		if ($key === '') {
			return null;
		}

		$model = trim((string) (env('GEMINI_MODEL') ?: 'gemini-2.5-flash-lite'));
		$prompt = "School timetable advisor (aSc style). Given warnings, give 3-5 bullet fixes.\n"
			. implode("\n", array_map(static function ($w) {
				return '- ' . $w;
			}, array_slice($warnings, 0, 12)))
			. "\nContext: " . json_encode($context);

		$url = 'https://generativelanguage.googleapis.com/v1beta/models/'
			. rawurlencode($model) . ':generateContent';
		$payload = json_encode([
			'contents' => [['parts' => [['text' => $prompt]]]],
			'generationConfig' => ['temperature' => 0.3, 'maxOutputTokens' => 512],
		]);

		$ch = curl_init($url);
		curl_setopt_array($ch, [
			CURLOPT_POST => true,
			CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . $key],
			CURLOPT_POSTFIELDS => $payload,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 45,
		]);
		$raw = curl_exec($ch);
		$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($code !== 200 || !is_string($raw)) {
			$this->lastError = 'AI service HTTP ' . $code;
			return null;
		}

		$data = json_decode($raw, true);
		$text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
		return trim((string) $text) !== '' ? trim((string) $text) : null;
	}
}
