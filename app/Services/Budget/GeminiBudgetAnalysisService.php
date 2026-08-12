<?php

namespace App\Services\Budget;

/**
 * Gemini AI dashboard analysis for budget utilization & cash flow follow-up.
 */
class GeminiBudgetAnalysisService
{
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

	public function analyzeDashboard(array $context): ?array
	{
		if (!$this->isConfigured()) {
			$this->lastError = 'AI service is not configured.';
			return null;
		}
		$prompt = $this->buildPrompt($context);
		$payload = [
			'contents' => [[
				'role' => 'user',
				'parts' => [['text' => $prompt]],
			]],
			'generationConfig' => [
				'responseMimeType' => 'application/json',
				'temperature' => 0.25,
			],
		];
		$models = ['gemini-2.5-flash-lite', 'gemini-2.5-flash', 'gemini-flash-latest'];
		foreach ($models as $model) {
			try {
				$data = $this->request($model, $payload);
				$text = $this->extractText($data);
				$parsed = json_decode($text, true);
				if (is_array($parsed) && !empty($parsed['summary'])) {
					return $this->normalize($parsed);
				}
			} catch (\Throwable $e) {
				$this->lastError = $e->getMessage();
			}
		}
		return null;
	}

	private function normalize(array $parsed): array
	{
		$followUps = $parsed['follow_up_actions'] ?? $parsed['followups'] ?? [];
		if (!is_array($followUps)) {
			$followUps = [];
		}
		$priority = strtolower((string) ($parsed['priority'] ?? 'medium'));
		if (!in_array($priority, ['high', 'medium', 'low'], true)) {
			$priority = 'medium';
		}
		return [
			'summary' => (string) ($parsed['summary'] ?? ''),
			'health_score' => (int) ($parsed['health_score'] ?? 0),
			'priority' => $priority,
			'alerts' => array_values(array_slice(array_filter((array) ($parsed['alerts'] ?? [])), 0, 6)),
			'recommendations' => array_values(array_slice(array_filter((array) ($parsed['recommendations'] ?? [])), 0, 6)),
			'follow_up_actions' => array_values(array_slice(array_filter($followUps), 0, 6)),
			'branches_to_watch' => array_values(array_slice(array_filter((array) ($parsed['branches_to_watch'] ?? [])), 0, 8)),
			'cashflow_outlook' => (string) ($parsed['cashflow_outlook'] ?? ''),
		];
	}

	private function buildPrompt(array $ctx): string
	{
		$lines = $ctx['line_variances'] ?? [];
		$topOver = array_values(array_slice(array_filter($lines, static function ($l) {
			return ($l['variance'] ?? 0) < 0 && empty($l['is_total_row']);
		}), 0, 8));
		$payload = [
			'scope' => $ctx['scope'] ?? 'single_school',
			'school' => $ctx['branch_label'] ?? '',
			'period' => $ctx['period_title'] ?? '',
			'role_context' => $ctx['role_hint'] ?? '',
			'total_budget' => $ctx['total_budget'] ?? 0,
			'total_used' => $ctx['total_actual'] ?? 0,
			'variance' => $ctx['variance'] ?? 0,
			'total_income_plan' => $ctx['total_income'] ?? 0,
			'draft_budgets' => $ctx['draft_budgets'] ?? 0,
			'pending_requests' => $ctx['pending_cash'] ?? 0,
			'awaiting_payment' => $ctx['awaiting_payment'] ?? 0,
			'over_budget_lines' => $topOver,
			'budget_pipeline' => $ctx['budget_pipeline'] ?? null,
			'cash_pipeline' => $ctx['cash_pipeline'] ?? null,
			'branch_rollup' => $ctx['branch_rollup'] ?? null,
			'school_fees_projection' => $ctx['school_fees_projection'] ?? null,
			'currency' => 'RWF',
		];
		return 'You are a school group finance coach for Rwanda TVET/basic schools using Xander School. '
			. 'Analyze the snapshot and return JSON only with keys: '
			. 'summary (2-3 sentences), health_score (0-100 integer), priority (high|medium|low), '
			. 'alerts (array of short strings, max 6), recommendations (array of actionable strings, max 6), '
			. 'follow_up_actions (array of concrete next steps the user should take this week, max 6), '
			. 'branches_to_watch (array of school/branch names that need attention — empty if single-school), '
			. 'cashflow_outlook (one sentence). '
			. 'Be practical: name stuck approval stages, drafts that need submit, payments waiting, and overspent lines. '
			. 'Include school fee projection (boarding×rate + day×rate by term) when present — compare to income plan if useful. '
			. 'Match tone to role_context. Data: '
			. json_encode($payload, JSON_UNESCAPED_UNICODE);
	}

	private function request(string $model, array $payload): array
	{
		$key = $this->apiKey();
		$url = 'https://generativelanguage.googleapis.com/v1beta/models/'
			. rawurlencode($model) . ':generateContent';
		$body = json_encode($payload, JSON_UNESCAPED_UNICODE);
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
			CURLOPT_CONNECTTIMEOUT => 15,
		]);
		$raw = curl_exec($ch);
		$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		if ($raw === false || $code >= 400) {
			throw new \RuntimeException('AI service HTTP ' . $code);
		}
		$data = json_decode($raw, true);
		if (!is_array($data)) {
			throw new \RuntimeException('Invalid AI service response');
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
}
