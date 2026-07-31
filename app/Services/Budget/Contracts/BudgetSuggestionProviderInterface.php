<?php

namespace App\Services\Budget\Contracts;

interface BudgetSuggestionProviderInterface
{
	public function suggestBudgetLines(array $context);
	public function explainVariance(array $context);
	public function detectAnomalies(array $context);
	public function suggestMissingCategories(array $context);
	public function generateBudgetNarrative(array $context);
}
