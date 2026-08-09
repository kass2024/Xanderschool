<?php

namespace App\Services\Budget;

class BudgetCalculationService
{
	public const MONTHS = ['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec'];

	public function decodeMonthlyJson($json)
	{
		if (!$json || !is_string($json)) {
			return array_fill_keys(self::MONTHS, 0.0);
		}
		$data = json_decode($json, true);
		if (!is_array($data)) {
			return array_fill_keys(self::MONTHS, 0.0);
		}
		$out = array_fill_keys(self::MONTHS, 0.0);
		foreach (self::MONTHS as $m) {
			$out[$m] = (float) ($data[$m] ?? 0);
		}
		return $out;
	}

	public function encodeMonthlyJson(array $months)
	{
		$out = [];
		foreach (self::MONTHS as $m) {
			$out[$m] = round((float) ($months[$m] ?? 0), 2);
		}
		return json_encode($out);
	}

	public function sumMonthly(array $months)
	{
		return round(array_sum($months), 2);
	}

	public function lineAnnualAmount(array $line)
	{
		$mode = $line['calculation_mode'] ?? 'manual';
		if ($mode === 'monthly_grid') {
			$months = $this->decodeMonthlyJson($line['monthly_json'] ?? null);
			return $this->sumMonthly($months);
		}
		if ($mode === 'monthly') {
			if (!empty($line['user_amount'])) {
				return round((float) $line['user_amount'] * 12, 2);
			}
			$q = (float) ($line['quantity'] ?? 0);
			$u = (float) ($line['unit_cost'] ?? 0);
			$f = (float) ($line['frequency'] ?? 1);
			return round($q * $u * $f * 12, 2);
		}
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
		$allLines = $db->table('budget_lines')->where('budget_id', (int) $budgetId)
			->orderBy('sort_order')->get()->getResultArray();

		$sectionSums = [];
		$income = 0.0;
		$expense = 0.0;

		foreach ($allLines as $line) {
			if ((int) ($line['is_total_row'] ?? 0) === 1) {
				continue;
			}
			$amt = $this->lineAnnualAmount($line);
			$db->table('budget_lines')->where('id', $line['id'])->update(['annual_amount' => $amt]);
			$section = strtoupper(trim($line['section_label'] ?? 'GENERAL'));
			if (!isset($sectionSums[$section])) {
				$sectionSums[$section] = 0.0;
			}
			$sectionSums[$section] += $amt;
			if (strpos($section, 'INCOME') !== false) {
				$income += $amt;
			} else {
				$expense += $amt;
			}
		}

		foreach ($allLines as $line) {
			if ((int) ($line['is_total_row'] ?? 0) !== 1) {
				continue;
			}
			$section = strtoupper(trim($line['section_label'] ?? ''));
			$total = $sectionSums[$section] ?? 0.0;
			$db->table('budget_lines')->where('id', $line['id'])->update(['annual_amount' => $total]);
		}

		$surplus = round($income - $expense, 2);
		$db->table('budgets')->where('id', (int) $budgetId)->update([
			'total_income' => $income,
			'total_expenses' => $expense,
			'surplus_deficit' => $surplus,
			'updated_at' => date('Y-m-d H:i:s'),
		]);
		return [
			'income' => $income,
			'expenses' => $expense,
			'surplus' => $surplus,
			'sections' => $sectionSums,
		];
	}

	public function groupLinesBySection(array $lines)
	{
		$groups = [];
		foreach ($lines as $line) {
			$sec = $line['section_label'] ?? 'GENERAL';
			if (!isset($groups[$sec])) {
				$groups[$sec] = ['label' => $sec, 'lines' => [], 'is_income' => stripos($sec, 'INCOME') !== false];
			}
			$groups[$sec]['lines'][] = $line;
		}
		return $groups;
	}
}
