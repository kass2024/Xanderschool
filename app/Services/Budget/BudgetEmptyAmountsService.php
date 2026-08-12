<?php

namespace App\Services\Budget;

/**
 * Restore full budget line structure with empty amounts (Director fills manually).
 * School Fees is filled from fees management × student counts.
 */
class BudgetEmptyAmountsService
{
	/**
	 * @return array{success:bool,error?:string,lines_ensured:int,cleared:int,school_fees:?array}
	 */
	public function resetEmptyExceptSchoolFees(int $budgetId, int $schoolId, int $staffId = 0): array
	{
		$db = \Config\Database::connect();
		$budget = $db->table('budgets')->where('id', $budgetId)->get(1)->getRowArray();
		if (!$budget) {
			return ['success' => false, 'error' => 'Budget not found.', 'lines_ensured' => 0, 'cleared' => 0, 'school_fees' => null];
		}

		$ensured = $this->ensureAllTemplateLines($db, $budgetId, $schoolId);
		$cleared = $this->clearNonSchoolFeesAmounts($db, $budgetId);

		$setup = [];
		if (!empty($budget['notes'])) {
			$decoded = json_decode($budget['notes'], true);
			if (is_array($decoded)) {
				$setup = $decoded;
			}
		}
		$yearHint = $setup['academic_year'] ?? null;
		$proj = (new SchoolFeesBudgetProjectionService())->projectForSchool($schoolId, $yearHint);
		$schoolFeesApplied = null;
		if (!empty($proj['success'])) {
			$schoolFeesApplied = $this->applySchoolFeesLine($db, $budgetId, $proj);
			if ((int) ($setup['enrollment'] ?? 0) < 1) {
				$setup['enrollment'] = (int) ($proj['total_students'] ?? 0);
			}
			if (empty($setup['academic_year']) && !empty($proj['academic_year_title'])) {
				$setup['academic_year'] = $proj['academic_year_title'];
			}
			$setup['fees_projection_at'] = date('Y-m-d H:i:s');
			$setup['fees_projection_notes'] = $proj['notes'] ?? '';
		}

		$setup['amounts_cleared_for_dof_at'] = date('Y-m-d H:i:s');
		unset($setup['excel_filled_at'], $setup['excel_source']);

		$calc = new BudgetCalculationService();
		$calc->recalculateBudgetTotals($budgetId);
		$db->table('budgets')->where('id', $budgetId)->update([
			'notes' => json_encode($setup),
			'updated_at' => date('Y-m-d H:i:s'),
			'updated_by' => $staffId > 0 ? $staffId : ($budget['updated_by'] ?? null),
		]);

		return [
			'success' => true,
			'lines_ensured' => $ensured,
			'cleared' => $cleared,
			'school_fees' => $schoolFeesApplied,
			'projection' => $proj,
		];
	}

	protected function ensureAllTemplateLines($db, int $budgetId, int $schoolId): int
	{
		$hierarchy = new \App\Services\SchoolHierarchyService();
		$desired = [];
		if ($hierarchy->isChildSchool($schoolId)) {
			$masterId = $hierarchy->masterSchoolId($schoolId);
			$masterBranch = $masterId > 0
				? $db->table('branches')->where('school_id', $masterId)->where('status', 1)->orderBy('id', 'ASC')->get(1)->getRowArray()
				: null;
			$masterBudget = $masterBranch
				? $db->table('budgets')->where('branch_id', (int) $masterBranch['id'])
					->whereIn('status', ['DRAFT', 'APPROVED', 'RETURNED', 'SUBMITTED', 'PROCUREMENT_REVIEW', 'BUDGET_MANAGER_REVIEW', 'DEPUTY_DIRECTOR_REVIEW'])
					->orderBy('id', 'DESC')->get(1)->getRowArray()
				: null;
			if ($masterBudget) {
				foreach ($db->table('budget_lines')->where('budget_id', (int) $masterBudget['id'])->orderBy('sort_order')->get()->getResultArray() as $ln) {
					$desired[] = [
						'section' => $ln['section_label'],
						'category' => $ln['category'],
						'is_total_row' => (int) ($ln['is_total_row'] ?? 0),
						'is_editable' => (int) ($ln['is_editable'] ?? 1),
						'sort_order' => (int) ($ln['sort_order'] ?? 0),
						'template_line_id' => $ln['template_line_id'] ?? null,
						'calculation_mode' => $ln['calculation_mode'] ?? 'term_sum',
					];
				}
			}
		}
		if (!$desired) {
			foreach ((new BudgetTemplateImportService())->defaultStructure() as $ln) {
				$desired[] = [
					'section' => $ln['section'],
					'category' => $ln['normalized_label'],
					'is_total_row' => !empty($ln['is_total_row']) ? 1 : 0,
					'is_editable' => !empty($ln['is_editable']) ? 1 : 0,
					'sort_order' => (int) ($ln['sort_order'] ?? 0),
					'template_line_id' => null,
					'calculation_mode' => 'term_sum',
				];
			}
		}

		$existing = $db->table('budget_lines')->where('budget_id', $budgetId)->get()->getResultArray();
		$byKey = [];
		foreach ($existing as $row) {
			$key = $this->lineKey($row['section_label'] ?? '', $row['category'] ?? '');
			$byKey[$key] = $row;
		}

		$added = 0;
		$maxSort = 0;
		foreach ($existing as $row) {
			$maxSort = max($maxSort, (int) ($row['sort_order'] ?? 0));
		}
		foreach ($desired as $ln) {
			$key = $this->lineKey($ln['section'], $ln['category']);
			if (isset($byKey[$key])) {
				continue;
			}
			$maxSort++;
			$db->table('budget_lines')->insert([
				'budget_id' => $budgetId,
				'section_label' => $ln['section'],
				'category' => $ln['category'],
				'is_total_row' => (int) $ln['is_total_row'],
				'is_editable' => (int) $ln['is_editable'],
				'calculation_mode' => $ln['calculation_mode'] ?? 'term_sum',
				'sort_order' => (int) ($ln['sort_order'] ?: $maxSort),
				'template_line_id' => $ln['template_line_id'] ?? null,
				'term_1_amount' => 0,
				'term_2_amount' => 0,
				'term_3_amount' => 0,
				'annual_amount' => 0,
				'user_amount' => 0,
				'quantity' => null,
				'unit_cost' => null,
			]);
			$added++;
		}
		return $added;
	}

	protected function clearNonSchoolFeesAmounts($db, int $budgetId): int
	{
		$lines = $db->table('budget_lines')
			->where('budget_id', $budgetId)
			->where('is_total_row', 0)
			->where('is_editable', 1)
			->get()->getResultArray();
		$cleared = 0;
		foreach ($lines as $line) {
			if ($this->isSchoolFeesCategory((string) ($line['category'] ?? ''))) {
				continue;
			}
			$db->table('budget_lines')->where('id', (int) $line['id'])->update([
				'term_1_amount' => 0,
				'term_2_amount' => 0,
				'term_3_amount' => 0,
				'annual_amount' => 0,
				'user_amount' => 0,
				'quantity' => null,
				'unit_cost' => null,
				'frequency' => 1,
				'calculation_mode' => 'term_sum',
				'assumptions' => null,
				'monthly_json' => null,
			]);
			$cleared++;
		}
		return $cleared;
	}

	/**
	 * @param array<string,mixed> $proj
	 * @return array{line_id:int,annual:float}|null
	 */
	protected function applySchoolFeesLine($db, int $budgetId, array $proj): ?array
	{
		$line = $db->table('budget_lines')
			->where('budget_id', $budgetId)
			->where('is_total_row', 0)
			->like('category', 'School Fee')
			->orderBy('sort_order', 'ASC')
			->get(1)->getRowArray();
		if (!$line) {
			$line = $db->table('budget_lines')
				->where('budget_id', $budgetId)
				->where('is_total_row', 0)
				->where('section_label', 'INCOME')
				->like('category', 'Fee')
				->orderBy('sort_order', 'ASC')
				->get(1)->getRowArray();
		}
		if (!$line) {
			return null;
		}
		$calc = new BudgetCalculationService();
		$update = [
			'term_1_amount' => (float) ($proj['term_1'] ?? 0),
			'term_2_amount' => (float) ($proj['term_2'] ?? 0),
			'term_3_amount' => (float) ($proj['term_3'] ?? 0),
			'calculation_mode' => 'term_sum',
			'user_amount' => (float) ($proj['annual'] ?? 0),
			'quantity' => null,
			'unit_cost' => null,
			'assumptions' => (string) ($proj['notes'] ?? ''),
		];
		$update['annual_amount'] = $calc->lineAnnualAmount(array_merge($line, $update));
		$db->table('budget_lines')->where('id', (int) $line['id'])->update($update);
		return ['line_id' => (int) $line['id'], 'annual' => (float) $update['annual_amount']];
	}

	public function isSchoolFeesCategory(string $category): bool
	{
		$cat = strtolower(trim($category));
		return strpos($cat, 'school fee') !== false;
	}

	protected function lineKey(string $section, string $category): string
	{
		return strtolower(trim($section)) . '|' . strtolower(trim($category));
	}
}
