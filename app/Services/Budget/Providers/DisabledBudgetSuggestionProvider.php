<?php

namespace App\Services\Budget\Providers;

use App\Services\Budget\Contracts\BudgetSuggestionProviderInterface;

class DisabledBudgetSuggestionProvider implements BudgetSuggestionProviderInterface
{
	public function suggestBudgetLines(array $context)
	{
		return ['success' => true, 'suggestions' => [], 'message' => 'AI suggestions are disabled. Configure an API key in environment settings.'];
	}

	public function explainVariance(array $context)
	{
		return ['success' => true, 'explanation' => 'AI unavailable — review variance manually.'];
	}

	public function detectAnomalies(array $context)
	{
		return ['success' => true, 'anomalies' => []];
	}

	public function suggestMissingCategories(array $context)
	{
		return ['success' => true, 'categories' => []];
	}

	public function generateBudgetNarrative(array $context)
	{
		return ['success' => true, 'narrative' => ''];
	}
}
