<?php

namespace App\Services\Budget;

use App\Services\Budget\Providers\DisabledBudgetSuggestionProvider;

class BudgetSuggestionService
{
	private $provider;

	public function __construct()
	{
		$this->provider = new DisabledBudgetSuggestionProvider();
	}

	public function getProvider()
	{
		return $this->provider;
	}

	public function recordDecision($budgetId, $lineId, $suggestionType, $value, $status, $reason = null, $confidence = null)
	{
		$db = \Config\Database::connect();
		$db->table('ai_budget_suggestions')->insert([
			'budget_id' => (int) $budgetId,
			'budget_line_id' => $lineId ? (int) $lineId : null,
			'suggestion_type' => $suggestionType,
			'suggested_value' => $value,
			'reason' => $reason,
			'confidence' => $confidence,
			'status' => $status,
			'created_at' => date('Y-m-d H:i:s'),
		]);
	}
}
