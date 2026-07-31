<?php

namespace App\Services\Budget;

class BudgetCalculationService
{
	public function lineAnnualAmount(array $line)
	{
		$mode = $line['calculation_mode'] ?? 'manual';
		if ($mode === 'qty_unit_freq') {
			$q = (float) ($line['quantity'] ?? 0);
			$u = (float) ($line['unit_cost'] ?? 0);
			$f = (float) ($line['frequency'] ?? 1);
			return round($q * $u * $f, 2);
		}
		if ($mode === 'term_sum') {
			return round(
				(float) ($line['term_1_amount'] ?? 0)
				+ (float) ($line['term_2_amount'] ?? 0)
				+ (float) ($line['term_3_amount'] ?? 0),
				2
			);
		}
		if (!empty($line['user_amount'])) {
			return round((float) $line['user_amount'], 2);
		}
		return round((float) ($line['annual_amount'] ?? 0), 2);
	}

	public function recalculateBudgetTotals($budgetId)
	{
		$db = \Config\Database::connect();
		$lines = $db->table('budget_lines')->where('budget_id', (int) $budgetId)
			->where('is_total_row', 0)->get()->getResultArray();
		$income = 0.0;
		$expense = 0.0;
		foreach ($lines as $line) {
			$amt = $this->lineAnnualAmount($line);
			$db->table('budget_lines')->where('id', $line['id'])->update(['annual_amount' => $amt]);
			$section = strtoupper($line['section_label'] ?? '');
			if (strpos($section, 'INCOME') !== false) {
				$income += $amt;
			} else {
				$expense += $amt;
			}
		}
		$surplus = round($income - $expense, 2);
		$db->table('budgets')->where('id', (int) $budgetId)->update([
			'total_income' => $income,
			'total_expenses' => $expense,
			'surplus_deficit' => $surplus,
			'updated_at' => date('Y-m-d H:i:s'),
		]);
		return ['income' => $income, 'expenses' => $expense, 'surplus' => $surplus];
	}
}
