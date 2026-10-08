<?php

namespace App\Services\Budget;

/**
 * One budget per school per academic year. An approved budget stays locked
 * until the Chief Accountant opens Term I, Term II, or Term III.
 */
class BudgetYearLock
{
	public static function yearOf(array $budget): string
	{
		$notes = [];
		if (!empty($budget['notes'])) {
			$decoded = json_decode((string) $budget['notes'], true);
			if (is_array($decoded)) {
				$notes = $decoded;
			}
		}
		return trim((string) ($notes['academic_year'] ?? ''));
	}

	/** @return array{1: bool, 2: bool, 3: bool} */
	public static function unlockedTerms(array $budget): array
	{
		$notes = [];
		if (!empty($budget['notes'])) {
			$decoded = json_decode((string) $budget['notes'], true);
			if (is_array($decoded)) {
				$notes = $decoded;
			}
		}
		$raw = is_array($notes['term_unlock'] ?? null) ? $notes['term_unlock'] : [];
		return [
			1 => !empty($raw['1']) || !empty($raw[1]),
			2 => !empty($raw['2']) || !empty($raw[2]),
			3 => !empty($raw['3']) || !empty($raw[3]),
		];
	}

	/**
	 * Block a second budget for the same academic year.
	 * $replaceId is the open budget an upload is allowed to replace.
	 */
	public static function blockNewBudget($db, int $branchId, string $year, int $replaceId = 0): ?string
	{
		$year = trim($year);
		$rows = $db->table('budgets')
			->where('branch_id', $branchId)
			->whereNotIn('status', ['CANCELLED'])
			->orderBy('id', 'DESC')
			->get()->getResultArray();
		foreach ($rows as $row) {
			if ((int) $row['id'] === $replaceId) {
				continue;
			}
			$rowYear = self::yearOf($row);
			$sameYear = $year !== '' && $rowYear !== '' && strcasecmp($rowYear, $year) === 0;
			$approvedSameSchool = ($row['status'] ?? '') === 'APPROVED' && ($sameYear || $year === '' || $rowYear === '');
			if (!$sameYear && !$approvedSameSchool) {
				continue;
			}
			if (($row['status'] ?? '') === 'APPROVED' && ($sameYear || $rowYear === '' || $year === '')) {
				$label = $rowYear !== '' ? $rowYear : ($year !== '' ? $year : 'this year');
				return 'This school already has an approved budget for ' . $label
					. '. Only one budget is allowed for the year. The Chief Accountant can allow editing of Term I, Term II, or Term III.';
			}
			if ($sameYear) {
				return 'This school already has a budget for ' . $rowYear
					. '. Only one budget is allowed each year. Cancel or delete the current one before uploading another.';
			}
		}
		return null;
	}

	public static function setTermUnlock($db, int $budgetId, int $term, bool $allow): array
	{
		if (!in_array($term, [1, 2, 3], true)) {
			return ['success' => false, 'error' => 'Choose Term I, Term II, or Term III.'];
		}
		$budget = $db->table('budgets')->where('id', $budgetId)->get(1)->getRowArray();
		if (!$budget) {
			return ['success' => false, 'error' => 'Budget not found.'];
		}
		if (($budget['status'] ?? '') !== 'APPROVED') {
			return ['success' => false, 'error' => 'Term editing is opened only after the budget is approved.'];
		}
		$notes = [];
		if (!empty($budget['notes'])) {
			$decoded = json_decode((string) $budget['notes'], true);
			if (is_array($decoded)) {
				$notes = $decoded;
			}
		}
		if (!isset($notes['term_unlock']) || !is_array($notes['term_unlock'])) {
			$notes['term_unlock'] = ['1' => 0, '2' => 0, '3' => 0];
		}
		$notes['term_unlock'][(string) $term] = $allow ? 1 : 0;
		$db->table('budgets')->where('id', $budgetId)->update([
			'notes' => json_encode($notes),
			'updated_at' => date('Y-m-d H:i:s'),
		]);
		$names = [1 => 'Term I', 2 => 'Term II', 3 => 'Term III'];
		return [
			'success' => true,
			'message' => $allow
				? $names[$term] . ' can be edited. The rest of this approved budget stays locked.'
				: $names[$term] . ' is locked again.',
		];
	}
}
