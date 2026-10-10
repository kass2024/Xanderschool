<?php

namespace App\Libraries;

/**
 * Short class-teacher and head-teacher lines for nursery and primary reports.
 * Gemini writes them from the marks. A plain fallback is used when the API is down.
 */
class ReportRemarks
{
	/** @var array<int, string> */
	private static $sexOf = [];

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
				'sex' => (string) ($student['sex'] ?? ''),
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
		self::$sexOf = [];
		$out = [];
		foreach ($pupils as $pupil) {
			$id = (int) ($pupil['id'] ?? 0);
			if ($id < 1) {
				continue;
			}
			self::$sexOf[$id] = self::sexCode($pupil['sex'] ?? '');
			$out[$id] = self::fallback($pupil);
		}
		if ($out === [] || self::apiKey() === '') {
			return $out;
		}
		$jobs = [];
		foreach (array_chunk($pupils, 8) as $chunk) {
			$written = self::cached($chunk);
			if ($written === null) {
				$job = self::job($chunk);
				if ($job !== null) {
					$jobs[] = $job;
				}
				continue;
			}
			self::mergeLines($out, $written);
		}
		foreach (self::postParallel($jobs) as $written) {
			self::mergeLines($out, $written);
		}
		return $out;
	}

	/** @param array<int, array{class_teacher:string,head_teacher:string}> $out
	 * @param array<int, array{class_teacher:string,head_teacher:string}> $written */
	private static function mergeLines(array &$out, array $written): void
	{
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
		if (count($scored) === 1) {
			$class = $first . ' is ' . self::tone($avg) . ' in ' . $best . '.';
			$head = 'Keep that standard in ' . $best . '.';
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

	/** @param list<array{id:int,name:string,subjects:list<array{title:string,score:?float,full:float}>}> $chunk
	 * @return array<int, array{class_teacher:string,head_teacher:string}>|null */
	private static function cached(array $chunk): ?array
	{
		$cacheFile = self::cacheFile($chunk);
		if (!is_file($cacheFile) || (time() - (int) filemtime($cacheFile)) >= 43200) {
			return null;
		}
		$cached = json_decode((string) file_get_contents($cacheFile), true);
		if (!is_array($cached)) {
			return null;
		}
		$ready = [];
		foreach ($cached as $id => $lines) {
			if (!is_array($lines)) {
				continue;
			}
			$ready[(int) $id] = [
				'class_teacher' => self::clip((string) ($lines['class_teacher'] ?? ''), 16, self::$sexOf[(int) $id] ?? ''),
				'head_teacher' => self::clip((string) ($lines['head_teacher'] ?? ''), 12, self::$sexOf[(int) $id] ?? ''),
			];
		}
		return $ready === [] ? null : $ready;
	}

	/**
	 * @param list<array{id:int,name:string,subjects:list<array{title:string,score:?float,full:float}>}> $chunk
	 * @return array{cache:string,prompt:string,allowed:array<int, array{scored:list<string>,blank:list<string>}>}|null
	 */
	private static function job(array $chunk): ?array
	{
		$brief = [];
		$allowed = [];
		foreach ($chunk as $pupil) {
			$id = (int) $pupil['id'];
			$lines = [];
			$blank = [];
			foreach ($pupil['subjects'] ?? [] as $subject) {
				$title = trim((string) ($subject['title'] ?? ''));
				if ($title === '') {
					continue;
				}
				$score = $subject['score'] ?? null;
				$full = (float) ($subject['full'] ?? 0);
				if ($score === null || $full <= 0) {
					$blank[] = $title;
					continue;
				}
				$lines[] = $title . ': ' . self::num((float) $score) . '/' . self::num($full);
			}
			$allowed[$id] = ['scored' => $lines, 'blank' => $blank];
			if ($lines === []) {
				continue;
			}
			$sex = self::sexCode($pupil['sex'] ?? '');
			$brief[] = [
				'id' => $id,
				'call' => self::calledName((string) ($pupil['name'] ?? '')),
				'gender' => $sex,
				'pronouns' => $sex === 'F' ? 'she, her' : ($sex === 'M' ? 'he, him, his' : ''),
				'filled_marks' => $lines,
				'comment_on' => count($lines) === 1
					? 'Only this one subject has a mark. Talk about that subject alone.'
					: 'These are the only marks in. Name the strongest and, if it is different, the weakest among them.',
			];
		}
		if ($brief === []) {
			return null;
		}
		$prompt = "You write the two handwritten lines on a nursery or primary report in Rwanda.\n"
			. "Sound like two different adults who looked at this child's filled marks, not like a form.\n"
			. "Every child must get different sentences. Do not reuse a sentence.\n"
			. "Use the call name. One sentence each.\n"
			. "The gender field is the sex saved on the student. F uses she and her. M uses he, him, and his. Do not guess from the name.\n"
			. "When gender is empty, repeat the call name and do not use he, she, him, her, or his.\n"
			. "class_teacher: at most 16 words. Comment only on subjects listed in filled_marks.\n"
			. "Never name a subject that is not in filled_marks. Never say a result is awaited, missing, blank, or not in yet.\n"
			. "If only one subject is filled, comment on that subject only.\n"
			. "If several are filled, talk about those present results: the strong one and the weaker one.\n"
			. "head_teacher: at most 12 words. A different line for the parent, still only about filled subjects.\n"
			. "No scores, no percentages, no labels like Excellent.\n"
			. "Return JSON only: {\"comments\":[{\"id\":1,\"class_teacher\":\"...\",\"head_teacher\":\"...\"}]}\n\n"
			. json_encode($brief, JSON_UNESCAPED_UNICODE);
		return [
			'cache' => self::cacheFile($chunk),
			'prompt' => $prompt,
			'allowed' => $allowed,
		];
	}

	/**
	 * Ask every uncached group at once. A failed group keeps the plain fallback.
	 *
	 * @param list<array{cache:string,prompt:string,allowed:array<int, array{scored:list<string>,blank:list<string>}>}> $jobs
	 * @return list<array<int, array{class_teacher:string,head_teacher:string}>>
	 */
	private static function postParallel(array $jobs): array
	{
		if ($jobs === []) {
			return [];
		}
		$model = trim((string) (env('GEMINI_MODEL') ?: env('GOOGLE_AI_MODEL') ?: 'gemini-2.5-flash'));
		if ($model === '') {
			$model = 'gemini-2.5-flash';
		}
		$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent';
		$key = self::apiKey();
		$ready = [];
		foreach (array_chunk($jobs, 4) as $wave) {
			$multi = curl_multi_init();
			$handles = [];
			foreach ($wave as $i => $job) {
				$body = json_encode([
					'contents' => [[
						'role' => 'user',
						'parts' => [['text' => $job['prompt']]],
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
						'x-goog-api-key: ' . $key,
					],
					CURLOPT_POSTFIELDS => $body,
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_TIMEOUT => 45,
					CURLOPT_CONNECTTIMEOUT => 10,
				]);
				curl_multi_add_handle($multi, $ch);
				$handles[] = ['ch' => $ch, 'job' => $job, 'body' => $body];
			}
			$running = null;
			do {
				$state = curl_multi_exec($multi, $running);
				if ($running) {
					curl_multi_select($multi, 1.0);
				}
			} while ($running && $state === CURLM_OK);
			foreach ($handles as $handle) {
				$ch = $handle['ch'];
				$raw = curl_multi_getcontent($ch);
				$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
				curl_multi_remove_handle($multi, $ch);
				curl_close($ch);
				if (($raw === false || $raw === null || $code >= 400) && is_string($raw) && stripos($raw, 'thinking') !== false) {
					try {
						$retryBody = json_decode((string) $handle['body'], true);
						unset($retryBody['generationConfig']['thinkingConfig']);
						$again = self::requestBody($url, $key, json_encode($retryBody, JSON_UNESCAPED_UNICODE));
						$raw = $again['raw'];
						$code = $again['code'];
					} catch (\Throwable $e) {
						$raw = false;
						$code = 0;
					}
				}
				if (!is_string($raw) || $raw === '' || $code >= 400) {
					continue;
				}
				$data = json_decode($raw, true);
				if (!is_array($data)) {
					continue;
				}
				$json = self::parseJson(self::extractText($data));
				$comments = is_array($json['comments'] ?? null) ? $json['comments'] : (array_is_list($json ?? []) ? $json : []);
				$out = self::keepPresentOnly(self::normalize($comments), $handle['job']['allowed']);
				if ($out === []) {
					continue;
				}
				$cacheFile = $handle['job']['cache'];
				$dir = dirname($cacheFile);
				if (!is_dir($dir)) {
					mkdir($dir, 0775, true);
				}
				file_put_contents($cacheFile, json_encode($out));
				$ready[] = $out;
			}
			curl_multi_close($multi);
		}
		return $ready;
	}

	/** @return array{raw:string,code:int} */
	private static function requestBody(string $url, string $key, string $body): array
	{
		$ch = curl_init($url);
		curl_setopt_array($ch, [
			CURLOPT_POST => true,
			CURLOPT_HTTPHEADER => [
				'Content-Type: application/json; charset=utf-8',
				'x-goog-api-key: ' . $key,
			],
			CURLOPT_POSTFIELDS => $body,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 45,
			CURLOPT_CONNECTTIMEOUT => 10,
		]);
		$raw = curl_exec($ch);
		$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		return ['raw' => is_string($raw) ? $raw : '', 'code' => $code];
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
				'class_teacher' => self::clip((string) ($row['class_teacher'] ?? ''), 16, self::$sexOf[$id] ?? ''),
				'head_teacher' => self::clip((string) ($row['head_teacher'] ?? ''), 12, self::$sexOf[$id] ?? ''),
			];
		}
		return $out;
	}

	private static function tone(float $pct): string
	{
		if ($pct >= 80) {
			return 'strong';
		}
		if ($pct >= 60) {
			return 'steady';
		}
		if ($pct >= 50) {
			return 'uneven';
		}
		return 'behind';
	}

	/**
	 * Drop a line that names a subject with no mark, so the scored-only fallback is used.
	 *
	 * @param array<int, array{class_teacher:string,head_teacher:string}> $out
	 * @param array<int, array{scored:list<string>,blank:list<string>}> $allowed
	 * @return array<int, array{class_teacher:string,head_teacher:string}>
	 */
	private static function keepPresentOnly(array $out, array $allowed): array
	{
		foreach ($out as $id => $lines) {
			$blank = $allowed[(int) $id]['blank'] ?? [];
			foreach (['class_teacher', 'head_teacher'] as $key) {
				if (self::mentionsAbsent((string) ($lines[$key] ?? ''), $blank)) {
					$out[$id][$key] = '';
				}
			}
		}
		return $out;
	}

	/** @param list<string> $blankTitles */
	private static function mentionsAbsent(string $text, array $blankTitles): bool
	{
		$lower = strtolower($text);
		foreach (['awaited', 'not in yet', 'no mark', 'not yet', 'still blank', 'missing', 'not filled', 'no score', 'still to come'] as $bad) {
			if (strpos($lower, $bad) !== false) {
				return true;
			}
		}
		foreach ($blankTitles as $title) {
			$title = strtolower(trim($title));
			if (strlen($title) >= 3 && strpos($lower, $title) !== false) {
				return true;
			}
		}
		return false;
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

	private static function sexCode($sex): string
	{
		$s = strtoupper(trim((string) $sex));
		if (in_array($s, ['F', 'FEMALE', 'GIRL'], true)) {
			return 'F';
		}
		if (in_array($s, ['M', 'MALE', 'BOY'], true)) {
			return 'M';
		}
		return '';
	}

	private static function swapWord(string $text, string $from, string $to): string
	{
		return preg_replace_callback('/\b' . $from . '\b/iu', static function (array $m) use ($to): string {
			$src = $m[0];
			if (strtoupper($src) === $src && strlen($src) > 1) {
				return strtoupper($to);
			}
			if (ctype_upper($src[0])) {
				return ucfirst($to);
			}
			return $to;
		}, $text) ?? $text;
	}

	/** Keep she/her or he/his lined up with the sex saved on the student. */
	private static function matchSex(string $text, string $sex): string
	{
		if ($sex === 'F') {
			$text = self::swapWord($text, 'himself', 'herself');
			$text = self::swapWord($text, 'he', 'she');
			$text = self::swapWord($text, 'his', 'her');
			$text = self::swapWord($text, 'him', 'her');
			$text = self::swapWord($text, 'boy', 'girl');
			return $text;
		}
		if ($sex === 'M') {
			$text = self::swapWord($text, 'herself', 'himself');
			$text = self::swapWord($text, 'she', 'he');
			$text = self::swapWord($text, 'hers', 'his');
			$text = preg_replace_callback('/\b(her)\s+(?!(?:with|to|and|or|for|in|on|at|from|by|of|a|an|the|is|was|be|into|about)\b)(?=\p{L})/iu', static function (array $m): string {
				return (ctype_upper($m[1][0]) ? 'His' : 'his') . ' ';
			}, $text) ?? $text;
			$text = self::swapWord($text, 'her', 'him');
			$text = self::swapWord($text, 'girl', 'boy');
		}
		return $text;
	}

	private static function clip(string $text, int $words, string $sex = ''): string
	{
		if ($sex === 'F' || $sex === 'M') {
			$text = self::matchSex($text, $sex);
		}
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
				self::sexCode($pupil['sex'] ?? ''),
				$pupil['subjects'] ?? [],
			];
		}
		return WRITEPATH . 'cache/report_remarks_v5/' . sha1(json_encode($payload)) . '.json';
	}
}
