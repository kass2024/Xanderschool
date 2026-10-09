<?php

namespace App\Libraries;

/**
 * Short class-teacher and head-teacher lines for nursery and primary reports.
 * Gemini writes them from the marks. A plain fallback is used when the API is down.
 */
class ReportRemarks
{
	/**
	 * @param list<array<string, mixed>> $records
	 * @return list<array{id:int,name:string,subjects:list<array{title:string,score:?float,full:float}>}>
	 */
	public static function pupilsFromRecords(array $records): array
	{
		$pupils = [];
		foreach ($records as $student) {
			if (!is_array($student) || !isset($student['id'])) {
				continue;
			}
			$subjects = [];
			foreach ($student['courses'] ?? [] as $core) {
				if (!is_array($core)) {
					continue;
				}
				$title = trim((string) ($core['title'] ?? ''));
				if ($title === '') {
					continue;
				}
				$full = (float) ($core['marks'] ?? 0);
				$result = is_array($core['result'] ?? null) ? $core['result'] : [];
				$mid = $result['marks'] ?? null;
				$exam = $result['exam_marks'] ?? null;
				$mid = ($mid === null || $mid === '') ? null : (float) $mid;
				$exam = ($exam === null || $exam === '') ? null : (float) $exam;
				$score = null;
				if ($mid !== null && $exam !== null) {
					$score = $mid + $exam;
					$full *= 2;
				} elseif ($mid !== null) {
					$score = $mid;
				} elseif ($exam !== null) {
					$score = $exam;
				}
				$subjects[] = [
					'title' => $title,
					'score' => $score,
					'full' => $full,
				];
			}
			$pupils[] = [
				'id' => (int) $student['id'],
				'name' => trim((string) ($student['fname'] ?? '') . ' ' . (string) ($student['lname'] ?? '')),
				'subjects' => $subjects,
			];
		}
		return $pupils;
	}

	/**
	 * @param list<array{id:int,name:string,subjects:list<array{title:string,score:?float,full:float}>}> $pupils
	 * @return array<int, array{class_teacher:string,head_teacher:string}>
	 */
	public static function forPupils(array $pupils): array
	{
		$out = [];
		foreach ($pupils as $pupil) {
			$id = (int) ($pupil['id'] ?? 0);
			if ($id < 1) {
				continue;
			}
			$out[$id] = self::fallback($pupil);
		}
		if ($out === [] || self::apiKey() === '') {
			return $out;
		}
		foreach (array_chunk($pupils, 8) as $chunk) {
			$written = self::ask($chunk);
			foreach ($written as $id => $lines) {
				if (!isset($out[$id])) {
					continue;
				}
				if ($lines['class_teacher'] !== '') {
					$out[$id]['class_teacher'] = $lines['class_teacher'];
				}
				if ($lines['head_teacher'] !== '') {
					$out[$id]['head_teacher'] = $lines['head_teacher'];
				}
			}
		}
		return $out;
	}

	/** @param array{id:int,name:string,subjects:list<array{title:string,score:?float,full:float}>} $pupil */
	private static function fallback(array $pupil): array
	{
		$first = self::calledName((string) ($pupil['name'] ?? ''));
		$scored = [];
		foreach ($pupil['subjects'] ?? [] as $subject) {
			$full = (float) ($subject['full'] ?? 0);
			$score = $subject['score'] ?? null;
			if ($full <= 0 || $score === null) {
				continue;
			}
			$scored[] = [
				'title' => (string) $subject['title'],
				'pct' => ((float) $score * 100) / $full,
			];
		}
		if ($scored === []) {
			return [
				'class_teacher' => $first . ' has no marks in yet.',
				'head_teacher' => 'Results are not in yet.',
			];
		}
		usort($scored, static function (array $a, array $b): int {
			return $b['pct'] <=> $a['pct'];
		});
		$avg = array_sum(array_column($scored, 'pct')) / count($scored);
		$best = $scored[0]['title'];
		$weak = $scored[count($scored) - 1]['title'];
		$blank = [];
		foreach ($pupil['subjects'] ?? [] as $subject) {
			if (($subject['score'] ?? null) === null && trim((string) ($subject['title'] ?? '')) !== '') {
				$blank[] = (string) $subject['title'];
			}
		}
		$blankText = self::joinSubjects(array_slice($blank, 0, 3));
		if ($blankText !== '' && $avg >= 75) {
			$class = $first . ' is full in ' . $best . '. ' . $blank[0] . ' is not in yet.';
			$head = 'Finish ' . $blank[0] . ' before this report closes.';
		} elseif ($avg >= 80) {
			$class = $first . ' is secure in ' . $best . ', and ' . $weak . ' is the soft spot.';
			$head = 'Keep ' . $weak . ' from slipping.';
		} elseif ($avg >= 60) {
			$class = $first . ' is steady in ' . $best . ', but ' . $weak . ' is behind.';
			$head = 'Practice ' . $weak . ' a little every day.';
		} elseif ($avg >= 50) {
			$class = $first . ' is uneven. ' . $weak . ' needs the most work.';
			$head = 'Sit with ' . $first . ' on ' . $weak . '.';
		} else {
			$class = $first . ' is behind, most clearly in ' . $weak . '.';
			$head = 'Daily practice in ' . $weak . ' comes first.';
		}
		return [
			'class_teacher' => self::clip($class, 12),
			'head_teacher' => self::clip($head, 10),
		];
	}

	/**
	 * @param list<array{id:int,name:string,subjects:list<array{title:string,score:?float,full:float}>}> $chunk
	 * @return array<int, array{class_teacher:string,head_teacher:string}>
	 */
	private static function ask(array $chunk): array
	{
		$cacheFile = self::cacheFile($chunk);
		if (is_file($cacheFile) && (time() - (int) filemtime($cacheFile)) < 43200) {
			$cached = json_decode((string) file_get_contents($cacheFile), true);
			if (is_array($cached)) {
				$ready = [];
				foreach ($cached as $id => $lines) {
					if (!is_array($lines)) {
						continue;
					}
					$ready[(int) $id] = [
						'class_teacher' => self::clip((string) ($lines['class_teacher'] ?? ''), 12),
						'head_teacher' => self::clip((string) ($lines['head_teacher'] ?? ''), 10),
					];
				}
				if ($ready !== []) {
					return $ready;
				}
			}
		}
		$brief = [];
		foreach ($chunk as $pupil) {
			$lines = [];
			foreach ($pupil['subjects'] ?? [] as $subject) {
				$score = $subject['score'] ?? null;
				$full = (float) ($subject['full'] ?? 0);
				$lines[] = $subject['title'] . ': ' . ($score === null ? 'no mark' : self::num($score) . '/' . self::num($full));
			}
			$brief[] = [
				'id' => (int) $pupil['id'],
				'call' => self::calledName((string) ($pupil['name'] ?? '')),
				'marks' => $lines,
			];
		}
		$prompt = "You write the two handwritten lines on a nursery or primary report in Rwanda.\n"
			. "Sound like two different adults who looked at this child's marks, not like a form.\n"
			. "Every child in this list must get different sentences. Do not reuse a sentence. Do not say \"doing well, especially\" or \"Good work. Keep it up.\"\n"
			. "Use the call name. One sentence each.\n"
			. "class_teacher: at most 12 words. Name one strong subject and one low or blank subject. If a subject has no mark, say it is not in yet.\n"
			. "head_teacher: at most 10 words. One different line for the parent. Do not repeat the class teacher.\n"
			. "No scores, no percentages, no labels like Excellent.\n"
			. "Return JSON only: {\"comments\":[{\"id\":1,\"class_teacher\":\"...\",\"head_teacher\":\"...\"}]}\n\n"
			. json_encode($brief, JSON_UNESCAPED_UNICODE);
		try {
			$raw = self::request($prompt);
			$text = self::extractText($raw);
			$json = self::parseJson($text);
			$comments = is_array($json['comments'] ?? null) ? $json['comments'] : (array_is_list($json ?? []) ? $json : []);
			$out = self::normalize($comments);
			if ($out !== []) {
				$dir = dirname($cacheFile);
				if (!is_dir($dir)) {
					mkdir($dir, 0775, true);
				}
				file_put_contents($cacheFile, json_encode($out));
			}
			return $out;
		} catch (\Throwable $e) {
			return [];
		}
	}

	/** @param list<mixed>|array<mixed> $rows */
	private static function normalize(array $rows): array
	{
		$out = [];
		foreach ($rows as $row) {
			if (!is_array($row)) {
				continue;
			}
			$id = (int) ($row['id'] ?? 0);
			if ($id < 1) {
				continue;
			}
			$out[$id] = [
				'class_teacher' => self::clip((string) ($row['class_teacher'] ?? ''), 12),
				'head_teacher' => self::clip((string) ($row['head_teacher'] ?? ''), 10),
			];
		}
		return $out;
	}

	private static function request(string $prompt): array
	{
		$model = trim((string) (env('GEMINI_MODEL') ?: env('GOOGLE_AI_MODEL') ?: 'gemini-2.5-flash'));
		if ($model === '') {
			$model = 'gemini-2.5-flash';
		}
		$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent';
		$body = json_encode([
			'contents' => [[
				'role' => 'user',
				'parts' => [['text' => $prompt]],
			]],
			'generationConfig' => [
				'temperature' => 0.8,
				'maxOutputTokens' => 4096,
				'responseMimeType' => 'application/json',
				'thinkingConfig' => ['thinkingBudget' => 0],
			],
		], JSON_UNESCAPED_UNICODE);
		$ch = curl_init($url);
		curl_setopt_array($ch, [
			CURLOPT_POST => true,
			CURLOPT_HTTPHEADER => [
				'Content-Type: application/json; charset=utf-8',
				'x-goog-api-key: ' . self::apiKey(),
			],
			CURLOPT_POSTFIELDS => $body,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 60,
			CURLOPT_CONNECTTIMEOUT => 15,
		]);
		$raw = curl_exec($ch);
		$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$err = curl_error($ch);
		if ($raw !== false && $code >= 400 && stripos((string) $raw, 'thinking') !== false) {
			$retry = json_decode((string) $body, true);
			unset($retry['generationConfig']['thinkingConfig']);
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($retry, JSON_UNESCAPED_UNICODE));
			$raw = curl_exec($ch);
			$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$err = curl_error($ch);
		}
		curl_close($ch);
		if ($raw === false) {
			throw new \RuntimeException($err !== '' ? $err : 'AI request failed');
		}
		if ($code >= 400) {
			throw new \RuntimeException('AI HTTP ' . $code);
		}
		$data = json_decode($raw, true);
		if (!is_array($data)) {
			throw new \RuntimeException('AI returned invalid JSON');
		}
		return $data;
	}

	private static function extractText(array $data): string
	{
		$buf = '';
		foreach ($data['candidates'][0]['content']['parts'] ?? [] as $part) {
			if (is_array($part) && !empty($part['text']) && empty($part['thought'])) {
				$buf .= $part['text'];
			}
		}
		if (trim($buf) === '') {
			foreach ($data['candidates'][0]['content']['parts'] ?? [] as $part) {
				if (is_array($part) && !empty($part['text'])) {
					$buf .= $part['text'];
				}
			}
		}
		return trim($buf);
	}

	private static function parseJson(string $text): array
	{
		$text = trim($text);
		if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $text, $m)) {
			$text = trim($m[1]);
		}
		$decoded = json_decode($text, true);
		if (is_array($decoded)) {
			return $decoded;
		}
		$start = strpos($text, '{');
		$end = strrpos($text, '}');
		if ($start !== false && $end !== false && $end > $start) {
			$decoded = json_decode(substr($text, $start, $end - $start + 1), true);
			if (is_array($decoded)) {
				return $decoded;
			}
		}
		return [];
	}

	private static function apiKey(): string
	{
		$key = trim((string) (env('GOOGLE_AI_API_KEY') ?: env('GEMINI_API_KEY') ?: ''));
		return trim($key, " \t\"'");
	}

	private static function calledName(string $name): string
	{
		$parts = preg_split('/\s+/', trim($name)) ?: [];
		$parts = array_values(array_filter($parts, static function ($part) {
			return trim((string) $part) !== '';
		}));
		$pick = $parts === [] ? 'This child' : (string) $parts[count($parts) - 1];
		return ucwords(strtolower($pick));
	}

	/** @param list<string> $titles */
	private static function joinSubjects(array $titles): string
	{
		$titles = array_values(array_filter(array_map('trim', $titles)));
		$n = count($titles);
		if ($n === 0) {
			return '';
		}
		if ($n === 1) {
			return $titles[0];
		}
		if ($n === 2) {
			return $titles[0] . ' and ' . $titles[1];
		}
		$last = array_pop($titles);
		return implode(', ', $titles) . ' and ' . $last;
	}

	private static function clip(string $text, int $words): string
	{
		$text = trim((string) preg_replace('/\s+/', ' ', $text));
		$text = trim($text, "\"'");
		if ($text === '') {
			return '';
		}
		$bits = preg_split('/\s+/', $text) ?: [];
		if (count($bits) > $words) {
			$text = implode(' ', array_slice($bits, 0, $words));
			$text = rtrim($text, '.,;:') . '.';
		}
		if (!preg_match('/[.!?]$/', $text)) {
			$text .= '.';
		}
		return $text;
	}

	private static function num(float $n): string
	{
		$s = number_format($n, 1, '.', '');
		return rtrim(rtrim($s, '0'), '.');
	}

	/** @param list<array<string, mixed>> $chunk */
	private static function cacheFile(array $chunk): string
	{
		$payload = [];
		foreach ($chunk as $pupil) {
			$payload[] = [
				(int) ($pupil['id'] ?? 0),
				$pupil['subjects'] ?? [],
			];
		}
		return WRITEPATH . 'cache/report_remarks_v2/' . sha1(json_encode($payload)) . '.json';
	}
}
