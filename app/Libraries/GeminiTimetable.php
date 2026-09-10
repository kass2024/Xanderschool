<?php

namespace App\Libraries;

/**
 * Optional Gemini assist for timetable scheduling tips / quality review.
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

		$prompt = "School timetable advisor (aSc style). Given warnings, give 3-5 bullet fixes.\n"
			. "Rules: courses with only 2 periods/week must be on different days; never stack both on one day.\n"
			. implode("\n", array_map(static function ($w) {
				return '- ' . $w;
			}, array_slice($warnings, 0, 12)))
			. "\nContext: " . json_encode($context);

		return $this->ask($prompt, 512);
	}

	/**
	 * Ask Gemini for concrete moves to clear teacher/class collisions.
	 *
	 * @param list<array<string,mixed>> $conflicts
	 * @param list<array<string,mixed>> $freeSlots [{day,slot_id,label}]
	 * @param list<array<string,mixed>> $movable [{entry_id,class_id,staff_id,course,day,slot_id}]
	 * @return list<array{entry_id:int,day:int,slot_id:int}>
	 */
	public function suggestCollisionMoves(array $conflicts, array $freeSlots, array $movable, array $context = []): array
	{
		if (!$this->isConfigured() || $conflicts === [] || $movable === []) {
			return [];
		}

		$prompt = "You are a school timetable conflict resolver for Rwanda.\n"
			. "Hard rules: never place the same teacher in two classes at the same time; "
			. "never place two lessons in the same class at the same time.\n"
			. "Return ONLY valid JSON: {\"moves\":[{\"entry_id\":123,\"day\":0,\"slot_id\":45}]}\n"
			. "day is Mon=0..Fri=4. Prefer free slots listed. Move as few lessons as possible.\n"
			. "Conflicts: " . json_encode(array_slice($conflicts, 0, 25)) . "\n"
			. "Movable lessons: " . json_encode(array_slice($movable, 0, 40)) . "\n"
			. "Free slots: " . json_encode(array_slice($freeSlots, 0, 80)) . "\n"
			. "Context: " . json_encode($context);

		$text = $this->ask($prompt, 900);
		if ($text === null || $text === '') {
			return [];
		}
		if (preg_match('/\{.*\}/s', $text, $m)) {
			$text = $m[0];
		}
		$data = json_decode($text, true);
		if (!is_array($data) || empty($data['moves']) || !is_array($data['moves'])) {
			return [];
		}
		$out = [];
		foreach ($data['moves'] as $move) {
			if (!is_array($move)) {
				continue;
			}
			$entryId = (int) ($move['entry_id'] ?? 0);
			$day = (int) ($move['day'] ?? -1);
			$slotId = (int) ($move['slot_id'] ?? 0);
			if ($entryId <= 0 || $day < 0 || $slotId <= 0) {
				continue;
			}
			$out[] = ['entry_id' => $entryId, 'day' => $day, 'slot_id' => $slotId];
		}
		return $out;
	}

	/**
	 * Quality review after a successful generate (spread of low-hour subjects, doubles, etc.).
	 *
	 * @param list<array<string,mixed>> $sampleRows day/course samples from one or more classes
	 */
	public function reviewQuality(array $sampleRows, array $context = []): ?string
	{
		if (!$this->isConfigured() || $sampleRows === []) {
			return null;
		}

		$prompt = "You are a school timetable quality checker for Rwanda primary/secondary.\n"
			. "Hard rule: if a subject has only 2 periods per week, those two MUST be on different days "
			. "(never a double-period dump on one day). Doubles are OK only when weekly load is 3+.\n"
			. "Review this sample schedule excerpt and reply with 3-5 short bullets: what looks good, "
			. "and any remaining spread/balance issues. Be concise.\n"
			. "Sample: " . json_encode(array_slice($sampleRows, 0, 40))
			. "\nContext: " . json_encode($context);

		return $this->ask($prompt, 400);
	}

	private function ask(string $prompt, int $maxTokens): ?string
	{
		$key = trim((string) (env('GOOGLE_AI_API_KEY') ?: env('GEMINI_API_KEY') ?: ''));
		if ($key === '') {
			$this->lastError = 'AI API key missing';
			return null;
		}

		$model = trim((string) (env('GEMINI_MODEL') ?: 'gemini-2.5-flash'));
		$url = 'https://generativelanguage.googleapis.com/v1beta/models/'
			. rawurlencode($model) . ':generateContent';
		$payload = json_encode([
			'contents' => [['parts' => [['text' => $prompt]]]],
			'generationConfig' => ['temperature' => 0.2, 'maxOutputTokens' => $maxTokens],
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
		$err = curl_error($ch);
		curl_close($ch);

		if ($code !== 200 || !is_string($raw)) {
			$this->lastError = 'AI service HTTP ' . $code . ($err !== '' ? (' ' . $err) : '');
			return null;
		}

		$data = json_decode($raw, true);
		$text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
		return trim((string) $text) !== '' ? trim((string) $text) : null;
	}
}
