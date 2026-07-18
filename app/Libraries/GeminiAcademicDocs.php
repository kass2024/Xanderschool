<?php

namespace App\Libraries;

/**
 * Gemini-powered academic document analysis & generation.
 * TVET/RTB → Scheme of Work + Session Plan
 * REB → Scheme of Work + Lesson Plan
 *
 * Accepts Word/PDF of any layout; always sends raw file + extracted text to Gemini.
 */
class GeminiAcademicDocs
{
	/** Posts allowed to use academic AI plans */
	public const ALLOWED_POSTS = [1, 3, 5, 6, 13, 15, 17, 18];
	// Headmaster, DoS, Patron, Matron, Librarian, Principal, IT Support, Headmistress

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
		$key = trim((string) (env('GOOGLE_AI_API_KEY') ?: env('GEMINI_API_KEY') ?: ''));
		return trim($key, " \t\"'");
	}

	/** @return list<string> */
	public function textModels(): array
	{
		$primary = trim((string) (env('GEMINI_MODEL') ?: ''));
		$candidates = array_filter([
			$primary,
			'gemini-2.5-flash',
			'gemini-2.0-flash',
			'gemini-1.5-flash',
		]);
		return array_values(array_unique($candidates));
	}

	/**
	 * Analyze curriculum (+ optional chronogram) and return structured modules matched to DB.
	 *
	 * @param array{path:string,original?:string} $curriculum
	 * @param array{path:string,original?:string}|null $chronogram
	 * @param array $dbContext school/class/levels/courses/teachers
	 * @return array|null
	 */
	public function analyzeCurriculum(array $curriculum, ?array $chronogram, array $dbContext): ?array
	{
		$parts = $this->buildFileParts($curriculum, $chronogram);
		$prompt = $this->promptAnalyze($dbContext);
		$raw = $this->generateJson($prompt, $parts);
		if ($raw === null) {
			return null;
		}
		return $this->matchToDb($raw, $dbContext);
	}

	/**
	 * Generate Scheme of Work HTML + structured JSON.
	 *
	 * @return array{html:string,json:array,title:string}|null
	 */
	public function generateSchemeOfWork(array $module, array $dbContext, string $programType, ?array $chronogramAnalysis = null): ?array
	{
		$prompt = $this->promptScheme($module, $dbContext, $programType, $chronogramAnalysis);
		$data = $this->generateJson($prompt, []);
		if ($data === null) {
			return null;
		}
		$html = (string) ($data['html'] ?? '');
		if ($html === '' && !empty($data['rows'])) {
			$html = $this->fallbackSchemeHtml($data, $dbContext, $programType);
		}
		$title = (string) ($data['title'] ?? ('Scheme of Work — ' . ($module['title'] ?? 'Module')));
		return ['html' => $html, 'json' => $data, 'title' => $title];
	}

	/**
	 * Generate Session Plan (TVET) or Lesson Plan (REB).
	 *
	 * @return array{html:string,json:array,title:string}|null
	 */
	public function generateSessionOrLessonPlan(
		array $module,
		array $schemeJson,
		array $topic,
		array $dbContext,
		string $programType
	): ?array {
		$isLesson = $programType === 'reb';
		$prompt = $this->promptSession($module, $schemeJson, $topic, $dbContext, $isLesson);
		$data = $this->generateJson($prompt, []);
		if ($data === null) {
			return null;
		}
		$html = (string) ($data['html'] ?? '');
		if ($html === '') {
			$html = $this->fallbackSessionHtml($data, $dbContext, $isLesson);
		}
		$kind = $isLesson ? 'Lesson Plan' : 'Session Plan';
		$title = (string) ($data['title'] ?? ($kind . ' — ' . ($topic['topic'] ?? $topic['indicative_content'] ?? 'Topic')));
		return ['html' => $html, 'json' => $data, 'title' => $title];
	}

	/**
	 * @param array{path:string,original?:string} $curriculum
	 * @param array{path:string,original?:string}|null $chronogram
	 * @return list<array<string,mixed>>
	 */
	private function buildFileParts(array $curriculum, ?array $chronogram): array
	{
		$parts = [];
		foreach ([['label' => 'CURRICULUM', 'file' => $curriculum], ['label' => 'CHRONOGRAM', 'file' => $chronogram]] as $pack) {
			if (empty($pack['file']['path']) || !is_file($pack['file']['path'])) {
				continue;
			}
			$extracted = DocumentTextExtractor::extract($pack['file']['path']);
			$label = $pack['label'];
			$orig = $pack['file']['original'] ?? basename($pack['file']['path']);
			$parts[] = [
				'text' => "=== {$label} FILE: {$orig} (ext={$extracted['ext']}, extracted_chars={$extracted['chars']}) ===\n"
					. ($extracted['chars'] > 0
						? ("EXTRACTED TEXT (may be incomplete; also use the attached binary):\n" . mb_substr($extracted['text'], 0, 120000, 'UTF-8'))
						: "EXTRACTED TEXT EMPTY — this is likely a scanned/image document. You MUST visually analyze the attached binary file."),
			];
			if (!empty($extracted['bytes']) && strlen($extracted['bytes']) < 18 * 1024 * 1024) {
				$parts[] = [
					'inlineData' => [
						'mimeType' => $extracted['mime'],
						'data' => base64_encode($extracted['bytes']),
					],
				];
			}
		}
		return $parts;
	}

	private function promptAnalyze(array $ctx): string
	{
		$dbLevels = json_encode($ctx['levels'] ?? [], JSON_UNESCAPED_UNICODE);
		$dbCourses = json_encode($ctx['courses'] ?? [], JSON_UNESCAPED_UNICODE);
		$classMeta = json_encode($ctx['class'] ?? [], JSON_UNESCAPED_UNICODE);
		$school = json_encode($ctx['school'] ?? [], JSON_UNESCAPED_UNICODE);

		return <<<PROMPT
You are an expert Rwanda education curriculum analyst for both TVET/RTB and REB programmes.

TASK: Analyze the attached CURRICULUM document (Word or PDF — layouts vary wildly; never assume a fixed template) and optional CHRONOGRAM.
Extract EVERY course/module/subject with its RQF / curriculum level and learning structure.

POWERFUL ANALYSIS RULES:
1. Documents do NOT share one structure. Detect headings, tables, competence codes, learning outcomes, indicative contents, hours, credits, sector, trade, module type.
2. For TVET/RTB: look for module codes like GENCP401, ETEED401, SWDPH401, RQF Level, Learning Outcomes (LO), Indicative Content (IC), Elements of competency, Performance criteria, Learning hours.
3. For REB: look for subjects, topics, units, periods, syllabus strands, senior/ordinary level labels.
4. From chronogram (often a grid/image PDF): extract weeks, date ranges, module codes, periods per week, terms.
5. Normalize RQF level to an integer 1–5 when possible (Level 4 / RQF 4 / L4 → 4).
6. Match modules to the school's DB courses and levels using fuzzy title/code similarity. Prefer exact code matches.

SCHOOL CONTEXT JSON:
{$school}

CLASS CONTEXT JSON:
{$classMeta}

DB LEVELS JSON:
{$dbLevels}

DB COURSES ASSIGNED TO THIS CLASS (with teachers) JSON:
{$dbCourses}

Return ONLY valid JSON (no markdown fences) with this shape:
{
  "program_type": "tvet" | "reb",
  "qualification_title": string,
  "sector": string,
  "trade": string,
  "detected_rqf_level": number|null,
  "modules": [
    {
      "code": string,
      "title": string,
      "rqf_level": number|null,
      "learning_hours": number|null,
      "credits": number|null,
      "module_type": string,
      "learning_outcomes": [{"code":string,"title":string,"indicative_contents":[{"code":string,"title":string,"hours":number|null}]}],
      "matched_course_id": number|null,
      "matched_level_id": number|null,
      "match_confidence": number,
      "match_reason": string
    }
  ],
  "chronogram": {
    "school_year_label": string,
    "weeks": [{"week":number,"term":number,"date_from":string,"date_to":string,"modules":[{"code":string,"periods":number}]}]
  },
  "notes": string
}
PROMPT;
	}

	private function promptScheme(array $module, array $ctx, string $programType, ?array $chrono): string
	{
		$school = $ctx['school'] ?? [];
		$class = $ctx['class'] ?? [];
		$teacher = $module['teacher_name'] ?? ($ctx['teacher_name'] ?? '');
		$modJson = json_encode($module, JSON_UNESCAPED_UNICODE);
		$chronoJson = json_encode($chrono ?? new \stdClass(), JSON_UNESCAPED_UNICODE);
		$schoolJson = json_encode($school, JSON_UNESCAPED_UNICODE);
		$classJson = json_encode($class, JSON_UNESCAPED_UNICODE);
		$kind = $programType === 'reb' ? 'REB Scheme of Work' : 'TVET/RTB Scheme of Work';

		return <<<PROMPT
You are an expert instructional designer for Rwanda {$kind}.

Generate a complete SCHEME OF WORK for ONE module/course, following the professional sample structure used in Rwandan TVET schools (even if REB, keep a clear weekly/term plan).

SAMPLE STRUCTURE TO EMULATE (adapt fields; do not invent school identity — use provided school/class/teacher):
- Header: School name, district/sector/contact if known, title "SCHEME OF WORK"
- Meta: Sector, Trainer, Trade/Subject, School Year, Qualification Title, Term(s), RQF Level (TVET) or Level (REB)
- Module details: Module code & title, Learning hours, Number of classes, Class name, Date
- For each Term: table columns —
  Date | Competence Code and Name (LO) | Indicative Content (IC) | Duration | Learning Activities | Resources | Evidence of Formative Assessment | Learning Place | Observation
- Footer: Prepared by (trainer), Verified by (DOS), Approved by (Head teacher)

Spread indicative contents across weeks using chronogram periods/dates when available.
Use realistic classroom activities and resources for the subject.

SCHOOL: {$schoolJson}
CLASS: {$classJson}
TRAINER: {$teacher}
MODULE: {$modJson}
CHRONOGRAM ANALYSIS: {$chronoJson}

Return ONLY valid JSON:
{
  "title": string,
  "html": "full printable HTML document (inline CSS, A4-friendly tables, school header)",
  "meta": {"sector":"","trade":"","rqf_level":"","school_year":"","terms":"","module_code":"","module_title":"","learning_hours":0,"class_name":"","trainer":""},
  "rows": [{"term":1,"date":"","lo_code":"","lo_title":"","ic_code":"","ic_title":"","duration":"","activities":"","resources":"","assessment":"","place":"","observation":""}],
  "topics_for_sessions": [{"week":number,"term":number,"date":"","lo_code":"","lo_title":"","ic_code":"","ic_title":"","topic":"","duration":""}]
}
PROMPT;
	}

	private function promptSession(array $module, array $schemeJson, array $topic, array $ctx, bool $isLesson): string
	{
		$label = $isLesson ? 'LESSON PLAN (REB)' : 'SESSION PLAN (TVET/RTB)';
		$schoolJson = json_encode($ctx['school'] ?? [], JSON_UNESCAPED_UNICODE);
		$classJson = json_encode($ctx['class'] ?? [], JSON_UNESCAPED_UNICODE);
		$modJson = json_encode($module, JSON_UNESCAPED_UNICODE);
		$topicJson = json_encode($topic, JSON_UNESCAPED_UNICODE);
		$schemeBrief = json_encode([
			'meta' => $schemeJson['meta'] ?? [],
			'topics_for_sessions' => $schemeJson['topics_for_sessions'] ?? [],
		], JSON_UNESCAPED_UNICODE);
		$teacher = $module['teacher_name'] ?? '';

		$sampleHint = $isLesson
			? "Include: subject, class, topic, objectives, teaching aids, introduction, presentation/development steps, conclusion, assessment, homework, references."
			: "Emulate TVET SESSION PLAN sample fields: Sector, Trade, Level, Date, Trainer, School year, Term, Module (Code&Name), Week, No. Trainees, Class(es), Learning Outcome, Indicative content, Topic of the session, Range, Duration, Objectives, Facilitation techniques, Introduction (trainer/learner activity + resources + duration), Development/Body steps, Conclusion, Assessment/Assignment, Evaluation, References, Appendices, Reflection.";

		return <<<PROMPT
You are an expert Rwanda trainer preparing a {$label}.

The plan MUST be based on the already prepared Scheme of Work for this module and the teacher-selected topic/week (aligned to chronogram).

{$sampleHint}

SCHOOL: {$schoolJson}
CLASS: {$classJson}
TRAINER: {$teacher}
MODULE: {$modJson}
SELECTED TOPIC: {$topicJson}
SCHEME OF WORK SUMMARY: {$schemeBrief}

Return ONLY valid JSON:
{
  "title": string,
  "html": "full printable HTML (inline CSS)",
  "meta": {"week":0,"term":0,"topic":"","duration":"","learning_outcome":"","indicative_content":""},
  "objectives": [string],
  "facilitation_techniques": [string],
  "sections": {"introduction":{},"development":[],"conclusion":{},"assessment":"","evaluation":"","references":"","reflection":""}
}
PROMPT;
	}

	/**
	 * @param list<array<string,mixed>> $extraParts
	 */
	private function generateJson(string $prompt, array $extraParts): ?array
	{
		$this->lastError = '';
		if (!$this->isConfigured()) {
			$this->lastError = 'Gemini API key not configured (GOOGLE_AI_API_KEY)';
			return null;
		}
		$parts = array_merge([['text' => $prompt]], $extraParts);
		$payload = [
			'contents' => [['role' => 'user', 'parts' => $parts]],
			'generationConfig' => [
				'temperature' => 0.35,
				'maxOutputTokens' => 8192,
				'responseMimeType' => 'application/json',
			],
		];

		$lastErr = '';
		foreach ($this->textModels() as $model) {
			try {
				$raw = $this->request($model, $payload);
				$text = $this->extractText($raw);
				$json = $this->parseJson($text);
				if (is_array($json)) {
					return $json;
				}
				$lastErr = 'Model ' . $model . ' returned non-JSON';
			} catch (\Throwable $e) {
				$lastErr = $e->getMessage();
				// Retry without responseMimeType for older models
				try {
					$payload2 = $payload;
					unset($payload2['generationConfig']['responseMimeType']);
					$raw = $this->request($model, $payload2);
					$text = $this->extractText($raw);
					$json = $this->parseJson($text);
					if (is_array($json)) {
						return $json;
					}
				} catch (\Throwable $e2) {
					$lastErr = $e2->getMessage();
				}
			}
		}
		$this->lastError = $lastErr ?: 'Gemini generation failed';
		return null;
	}

	private function request(string $model, array $payload): array
	{
		$key = $this->apiKey();
		$url = 'https://generativelanguage.googleapis.com/v1beta/models/'
			. rawurlencode($model) . ':generateContent';
		$ch = curl_init($url);
		curl_setopt_array($ch, [
			CURLOPT_POST => true,
			CURLOPT_HTTPHEADER => [
				'Content-Type: application/json',
				'x-goog-api-key: ' . $key,
			],
			CURLOPT_POSTFIELDS => json_encode($payload),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 180,
			CURLOPT_CONNECTTIMEOUT => 25,
		]);
		$raw = curl_exec($ch);
		$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$err = curl_error($ch);
		curl_close($ch);
		if ($raw === false) {
			throw new \RuntimeException('curl failed: ' . $err);
		}
		if ($code >= 400) {
			$msg = substr(preg_replace('/\s+/', ' ', $raw) ?? $raw, 0, 320);
			throw new \RuntimeException("HTTP {$code}: {$msg}");
		}
		$data = json_decode($raw, true);
		if (!is_array($data)) {
			throw new \RuntimeException('Invalid JSON from Gemini');
		}
		if (!empty($data['error']['message'])) {
			throw new \RuntimeException((string) $data['error']['message']);
		}
		return $data;
	}

	private function extractText(array $data): string
	{
		$parts = $data['candidates'][0]['content']['parts'] ?? [];
		$buf = '';
		foreach ($parts as $p) {
			if (!empty($p['text'])) {
				$buf .= $p['text'];
			}
		}
		return trim($buf);
	}

	private function parseJson(string $text): ?array
	{
		$text = trim($text);
		if ($text === '') {
			return null;
		}
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
		return null;
	}

	private function matchToDb(array $analysis, array $ctx): array
	{
		$courses = $ctx['courses'] ?? [];
		$levels = $ctx['levels'] ?? [];
		$modules = $analysis['modules'] ?? [];
		if (!is_array($modules)) {
			$modules = [];
		}

		foreach ($modules as &$mod) {
			$code = strtoupper(trim((string) ($mod['code'] ?? '')));
			$title = strtolower(trim((string) ($mod['title'] ?? '')));
			$rqf = $mod['rqf_level'] ?? $analysis['detected_rqf_level'] ?? null;
			$best = null;
			$bestScore = 0.0;
			foreach ($courses as $c) {
				$cCode = strtoupper(trim((string) ($c['code'] ?? '')));
				$cTitle = strtolower(trim((string) ($c['title'] ?? '')));
				$score = 0.0;
				if ($code !== '' && $cCode !== '' && ($code === $cCode || strpos($cCode, $code) !== false || strpos($code, $cCode) !== false)) {
					$score += 0.7;
				}
				similar_text($title, $cTitle, $pct);
				$score += ($pct / 100) * 0.5;
				if ($score > $bestScore) {
					$bestScore = $score;
					$best = $c;
				}
			}
			if ($best && $bestScore >= 0.35) {
				$mod['matched_course_id'] = (int) $best['id'];
				$mod['matched_course_title'] = $best['title'] ?? '';
				$mod['teacher_id'] = $best['lecturer_id'] ?? null;
				$mod['teacher_name'] = $best['mentor_name'] ?? ($best['teacher_name'] ?? '');
				$mod['match_confidence'] = round($bestScore, 2);
				$mod['match_reason'] = 'Matched to DB course by code/title similarity';
			} else {
				$mod['matched_course_id'] = $mod['matched_course_id'] ?? null;
				$mod['teacher_name'] = $mod['teacher_name'] ?? '';
				$mod['match_confidence'] = $mod['match_confidence'] ?? 0;
			}

			$mod['matched_level_id'] = null;
			$mod['matched_level_title'] = '';
			if ($rqf !== null && $rqf !== '') {
				foreach ($levels as $lv) {
					$lt = (string) ($lv['title'] ?? '');
					if (preg_match('/\b' . preg_quote((string) (int) $rqf, '/') . '\b/', $lt)
						|| stripos($lt, 'level ' . (int) $rqf) !== false
						|| stripos($lt, 'l' . (int) $rqf) !== false) {
						$mod['matched_level_id'] = (int) $lv['id'];
						$mod['matched_level_title'] = $lt;
						break;
					}
				}
			}
			// Prefer class level when RQF matches class
			if (!empty($ctx['class']['level_id']) && empty($mod['matched_level_id'])) {
				$mod['matched_level_id'] = (int) $ctx['class']['level_id'];
				$mod['matched_level_title'] = (string) ($ctx['class']['level_name'] ?? '');
			}
		}
		unset($mod);
		$analysis['modules'] = $modules;
		if (empty($analysis['program_type'])) {
			$ftype = (int) ($ctx['class']['faculty_type'] ?? 1);
			$analysis['program_type'] = $ftype === 2 ? 'reb' : 'tvet';
		}
		return $analysis;
	}

	private function fallbackSchemeHtml(array $data, array $ctx, string $programType): string
	{
		$school = esc($ctx['school']['name'] ?? 'School');
		$title = esc($data['title'] ?? 'Scheme of Work');
		$rows = '';
		foreach ($data['rows'] ?? [] as $r) {
			$rows .= '<tr>'
				. '<td>' . esc($r['date'] ?? '') . '</td>'
				. '<td>' . esc(($r['lo_code'] ?? '') . ' ' . ($r['lo_title'] ?? '')) . '</td>'
				. '<td>' . esc(($r['ic_code'] ?? '') . ' ' . ($r['ic_title'] ?? '')) . '</td>'
				. '<td>' . esc($r['duration'] ?? '') . '</td>'
				. '<td>' . esc($r['activities'] ?? '') . '</td>'
				. '<td>' . esc($r['resources'] ?? '') . '</td>'
				. '<td>' . esc($r['assessment'] ?? '') . '</td>'
				. '<td>' . esc($r['place'] ?? '') . '</td>'
				. '<td>' . esc($r['observation'] ?? '') . '</td>'
				. '</tr>';
		}
		return "<html><head><meta charset='utf-8'><style>body{font-family:DejaVu Sans,Arial,sans-serif;font-size:11px}table{width:100%;border-collapse:collapse}td,th{border:1px solid #333;padding:4px;vertical-align:top}h1{text-align:center}</style></head><body><h1>{$title}</h1><p><b>{$school}</b> — " . esc(strtoupper($programType)) . "</p><table><thead><tr><th>Date</th><th>Competence / LO</th><th>Indicative Content</th><th>Duration</th><th>Activities</th><th>Resources</th><th>Assessment</th><th>Place</th><th>Observation</th></tr></thead><tbody>{$rows}</tbody></table></body></html>";
	}

	private function fallbackSessionHtml(array $data, array $ctx, bool $isLesson): string
	{
		$kind = $isLesson ? 'Lesson Plan' : 'Session Plan';
		$title = esc($data['title'] ?? $kind);
		$school = esc($ctx['school']['name'] ?? '');
		$topic = esc($data['meta']['topic'] ?? '');
		$objs = '';
		foreach ($data['objectives'] ?? [] as $o) {
			$objs .= '<li>' . esc($o) . '</li>';
		}
		return "<html><head><meta charset='utf-8'><style>body{font-family:DejaVu Sans,Arial,sans-serif;font-size:12px}h1{text-align:center}</style></head><body><h1>{$title}</h1><p>{$school}</p><p><b>Topic:</b> {$topic}</p><ol>{$objs}</ol></body></html>";
	}
}
