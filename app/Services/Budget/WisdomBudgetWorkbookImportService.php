<?php

namespace App\Services\Budget;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Reads a Wisdom budget workbook (SUMMARY sheet) and stores every budget line with its amount.
 *
 * Income money is Total Income, or children × fees when that cell is empty.
 * Expense money is the Budget column (not actual expenses, not the balance).
 */
class WisdomBudgetWorkbookImportService
{
	public const SOURCE = 'wisdom_excel_upload';

	private const SECTION_HEADERS = [
		'INCOME' => 'income',
		'EXPENSES' => 'banner',
		'OPERATING EXPENSES' => 'OPERATING EXPENSES',
		'ADMINISTRATIVE COSTS' => 'ADMINISTRATIVE COSTS',
		'FINANCE COSTS' => 'FINANCE COSTS',
	];

	public function templatePath(): ?string
	{
		$candidates = [
			FCPATH . 'assets/templates/budget/WISDOM_BUDGET_TEMPLATE.xlsx',
			ROOTPATH . 'public/assets/templates/budget/WISDOM_BUDGET_TEMPLATE.xlsx',
		];
		foreach ($candidates as $path) {
			if (is_file($path)) {
				return $path;
			}
		}
		return null;
	}

	public function parseFile(string $filePath): array
	{
		if (!is_file($filePath)) {
			return ['success' => false, 'error' => 'Excel file was not found.'];
		}
		try {
			$spreadsheet = IOFactory::load($filePath);
		} catch (\Throwable $e) {
			return ['success' => false, 'error' => 'Could not read this Excel file. Use the Wisdom budget template (.xlsx).'];
		}
		$sheet = $this->pickSheet($spreadsheet);
		if (!$sheet) {
			return ['success' => false, 'error' => 'No SUMMARY sheet with an INCOME block was found.'];
		}
		$parsed = $this->parseSheet($sheet);
		if (empty($parsed['rows'])) {
			return ['success' => false, 'error' => 'No budget lines were found. Keep the INCOME and EXPENSES blocks from the Wisdom template.'];
		}
		$withMoney = 0;
		foreach ($parsed['rows'] as $row) {
			if ((float) ($row['amount'] ?? 0) > 0) {
				$withMoney++;
			}
		}
		if ($withMoney < 1) {
			return ['success' => false, 'error' => 'The file has line titles but no amounts. Fill Total Income and the Budget column, then upload again.'];
		}
		$parsed['success'] = true;
		$parsed['lines_with_money'] = $withMoney;
		return $parsed;
	}

	/**
	 * Create or replace the school's open budget with extracted lines.
	 *
	 * @param array{org_id:int,branch_id:int,staff_id:int,period_id:int,title?:string,academic_year?:string,original_name?:string,stored_path?:string} $ctx
	 */
	public function importIntoBranch(string $filePath, array $ctx): array
	{
		$parsed = $this->parseFile($filePath);
		if (empty($parsed['success'])) {
			return $parsed;
		}

		$db = \Config\Database::connect();
		$branchId = (int) ($ctx['branch_id'] ?? 0);
		$staffId = (int) ($ctx['staff_id'] ?? 0);
		$orgId = (int) ($ctx['org_id'] ?? 0);
		$periodId = (int) ($ctx['period_id'] ?? 0);
		if ($branchId < 1 || $periodId < 1) {
			return ['success' => false, 'error' => 'School budget period is missing.'];
		}

		$openStatuses = [
			'DRAFT', 'RETURNED', 'REJECTED', 'CANCELLED', 'SUBMITTED',
			'CHIEF_ACCOUNTANT_REVIEW', 'DEPUTY_DIRECTOR_REVIEW',
			'PROCUREMENT_REVIEW', 'BUDGET_MANAGER_REVIEW',
		];
		$existing = $db->table('budgets')
			->where('branch_id', $branchId)
			->whereIn('status', $openStatuses)
			->orderBy('id', 'DESC')
			->get(1)->getRowArray();
		if ($existing) {
			$cashCount = $db->table('cash_requests')->where('budget_id', (int) $existing['id'])->countAllResults();
			if ($cashCount > 0) {
				return ['success' => false, 'error' => 'The current budget already has cash requests, so it cannot be replaced.'];
			}
		}

		$year = trim((string) ($ctx['academic_year'] ?? ''));
		if ($year === '') {
			$year = (string) ($parsed['academic_year'] ?? '');
		}
		$replaceId = 0;
		if ($existing && (int) ($db->table('cash_requests')->where('budget_id', (int) $existing['id'])->countAllResults()) < 1) {
			$replaceId = (int) $existing['id'];
		}
		$yearBlock = BudgetYearLock::blockNewBudget($db, $branchId, $year, $replaceId);
		if ($yearBlock) {
			return ['success' => false, 'error' => $yearBlock];
		}
		$title = trim((string) ($ctx['school_name'] ?? ''));
		if ($title === '') {
			$title = 'Annual Budget';
		}
		if ($year !== '') {
			$title .= ' ' . $year;
		}
		if (strlen($title) > 180) {
			$title = substr($title, 0, 180);
		}

		$termIndex = (int) ($parsed['term_index'] ?? 0);
		$notes = [
			'source' => self::SOURCE,
			'academic_year' => $year,
			'planning_type' => 'excel_upload',
			'excel_file' => (string) ($ctx['original_name'] ?? basename($filePath)),
			'excel_stored_path' => (string) ($ctx['stored_path'] ?? ''),
			'excel_school' => (string) ($parsed['school_name'] ?? ''),
			'excel_period' => (string) ($parsed['period_hint'] ?? ''),
			'excel_term' => $termIndex,
			'prepared_by_excel' => (string) ($parsed['prepared_by'] ?? ''),
			'verified_by_excel' => (string) ($parsed['verified_by'] ?? ''),
			'uploaded_at' => date('Y-m-d H:i:s'),
			'enrollment' => (int) ($parsed['enrollment'] ?? 0),
		];

		$db->transStart();
		if ($existing) {
			$budgetId = (int) $existing['id'];
			$db->table('budget_lines')->where('budget_id', $budgetId)->delete();
			try {
				$db->table('budget_approval_actions')->where('budget_id', $budgetId)->delete();
			} catch (\Throwable $e) {
			}
			$db->table('budgets')->where('id', $budgetId)->update([
				'title' => $title,
				'budget_period_id' => $periodId,
				'status' => 'DRAFT',
				'prepared_by' => $staffId,
				'prepared_at' => date('Y-m-d H:i:s'),
				'notes' => json_encode($notes),
				'updated_by' => $staffId,
				'updated_at' => date('Y-m-d H:i:s'),
				'version_no' => (int) ($existing['version_no'] ?? 1) + 1,
			]);
		} else {
			$db->table('budgets')->insert([
				'organization_id' => $orgId,
				'branch_id' => $branchId,
				'budget_period_id' => $periodId,
				'template_version_id' => null,
				'title' => $title,
				'currency' => 'RWF',
				'status' => 'DRAFT',
				'version_no' => 1,
				'prepared_by' => $staffId,
				'prepared_at' => date('Y-m-d H:i:s'),
				'notes' => json_encode($notes),
				'created_by' => $staffId,
				'created_at' => date('Y-m-d H:i:s'),
				'updated_at' => date('Y-m-d H:i:s'),
			]);
			$budgetId = (int) $db->insertID();
		}

		$order = 0;
		foreach ($parsed['rows'] as $row) {
			$amount = round((float) ($row['amount'] ?? 0), 2);
			$terms = [1 => 0.0, 2 => 0.0, 3 => 0.0];
			if ($termIndex >= 1 && $termIndex <= 3) {
				$terms[$termIndex] = $amount;
			} else {
				$share = floor($amount / 3);
				$terms[1] = $share;
				$terms[2] = $share;
				$terms[3] = round($amount - ($share * 2), 2);
			}
			$section = (string) ($row['section'] ?? 'OPERATING EXPENSES');
			$isIncome = stripos($section, 'INCOME') !== false;
			$db->table('budget_lines')->insert([
				'budget_id' => $budgetId,
				'section_label' => $section,
				'category' => mb_substr((string) $row['category'], 0, 180),
				'description' => (string) ($row['note'] ?? ''),
				'quantity' => $isIncome ? ($row['quantity'] ?? null) : null,
				'unit_cost' => $isIncome ? ($row['unit_cost'] ?? null) : null,
				'frequency' => 1,
				'calculation_mode' => 'term_sum',
				'term_1_amount' => $terms[1],
				'term_2_amount' => $terms[2],
				'term_3_amount' => $terms[3],
				'annual_amount' => $amount,
				'user_amount' => $amount,
				'is_total_row' => 0,
				'is_editable' => 1,
				'sort_order' => $order++,
			]);
		}
		$totals = (new BudgetCalculationService())->recalculateBudgetTotals($budgetId);
		$db->transComplete();
		if (!$db->transStatus()) {
			return ['success' => false, 'error' => 'Could not save the extracted budget.'];
		}

		return [
			'success' => true,
			'budget_id' => $budgetId,
			'replaced' => $existing ? true : false,
			'line_count' => count($parsed['rows']),
			'lines_with_money' => (int) ($parsed['lines_with_money'] ?? 0),
			'total_income' => $totals['income'] ?? 0,
			'total_expenses' => $totals['expenses'] ?? 0,
			'school_name' => $parsed['school_name'] ?? '',
			'period_hint' => $parsed['period_hint'] ?? '',
			'term_index' => $termIndex,
		];
	}

	private function pickSheet($spreadsheet): ?Worksheet
	{
		$fallback = null;
		foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
			$title = strtoupper(preg_replace('/\s+/', '', $sheet->getTitle()));
			if (strpos($title, 'SUMMARY') !== false) {
				return $sheet;
			}
			if ($fallback === null && $this->sheetHasIncome($sheet)) {
				$fallback = $sheet;
			}
		}
		return $fallback;
	}

	private function sheetHasIncome(Worksheet $sheet): bool
	{
		$max = min(25, (int) $sheet->getHighestRow());
		for ($r = 1; $r <= $max; $r++) {
			$label = strtoupper(trim((string) $this->cell($sheet, 1, $r)));
			if ($label === 'INCOME') {
				return true;
			}
		}
		return false;
	}

	private function parseSheet(Worksheet $sheet): array
	{
		$highest = min(250, (int) $sheet->getHighestRow());
		$schoolName = trim((string) $this->cell($sheet, 1, 1));
		$periodHint = trim((string) $this->cell($sheet, 1, 2));
		if (strtoupper($schoolName) === 'INCOME') {
			$schoolName = '';
			$periodHint = '';
		}
		$blob = $schoolName . ' ' . $periodHint;
		$termIndex = $this->termIndex($blob);
		$academicYear = $this->academicYear($blob);

		$cols = ['children' => 2, 'fees' => 3, 'total' => 4, 'budget' => 5];
		$mode = null;
		$section = 'OPERATING EXPENSES';
		$rows = [];
		$preparedBy = '';
		$verifiedBy = '';
		$enrollment = 0;

		for ($r = 1; $r <= $highest; $r++) {
			$label = trim((string) $this->cell($sheet, 1, $r));
			if ($label === '') {
				continue;
			}
			$upper = strtoupper(preg_replace('/\s+/', ' ', $label));
			if (preg_match('/^(PREPARED|VERIFIED|VERFIED|VERFIED|CHECKED|APPROVED)\s+BY\b/i', $label)) {
				if (stripos($label, 'prepar') === 0) {
					$preparedBy = $label;
				} else {
					$verifiedBy = $label;
				}
				continue;
			}
			if ($upper === 'INCOME' || $this->rowLooksLikeHeader($sheet, $r)) {
				$cols = $this->mapColumns($sheet, $r, $cols);
			}
			if (isset(self::SECTION_HEADERS[$upper])) {
				$kind = self::SECTION_HEADERS[$upper];
				if ($kind === 'income') {
					$mode = 'income';
					$section = 'INCOME';
				} elseif ($kind === 'banner') {
					$mode = 'expense';
				} else {
					$mode = 'expense';
					$section = $kind;
				}
				continue;
			}
			if (preg_match('/^TOTAL\b/i', $label)) {
				continue;
			}
			if ($mode === null) {
				continue;
			}

			if ($mode === 'income') {
				$children = $this->num($this->cell($sheet, $cols['children'], $r));
				$fees = $this->num($this->cell($sheet, $cols['fees'], $r));
				$total = $this->num($this->cell($sheet, $cols['total'], $r));
				$amount = $total !== null ? $total : (($children ?? 0) * ($fees ?? 0));
				$note = '';
				if ($children !== null || $fees !== null) {
					$note = trim(($children !== null ? $this->fmt($children) . ' × ' : '') . ($fees !== null ? $this->fmt($fees) : ''));
				}
				if (stripos($label, 'school fee') !== false && $children !== null) {
					$enrollment = (int) round($children);
				}
				$rows[] = [
					'section' => 'INCOME',
					'category' => $label,
					'amount' => round((float) $amount, 2),
					'quantity' => $children,
					'unit_cost' => $fees,
					'note' => $note,
				];
				continue;
			}

			$budget = $this->num($this->cell($sheet, $cols['budget'], $r));
			$rows[] = [
				'section' => $section,
				'category' => $label,
				'amount' => round((float) ($budget ?? 0), 2),
				'quantity' => null,
				'unit_cost' => null,
				'note' => '',
			];
		}

		return [
			'rows' => $rows,
			'school_name' => $schoolName,
			'period_hint' => $periodHint,
			'academic_year' => $academicYear,
			'term_index' => $termIndex,
			'prepared_by' => $preparedBy,
			'verified_by' => $verifiedBy,
			'enrollment' => $enrollment,
		];
	}

	private function rowLooksLikeHeader(Worksheet $sheet, int $row): bool
	{
		for ($c = 1; $c <= 10; $c++) {
			$text = strtolower(trim((string) $this->cell($sheet, $c, $row)));
			if ($text === 'budget' || $text === 'total income' || strpos($text, 'no of children') !== false) {
				return true;
			}
		}
		return false;
	}

	private function mapColumns(Worksheet $sheet, int $row, array $cols): array
	{
		for ($c = 1; $c <= 12; $c++) {
			$text = strtolower(trim((string) $this->cell($sheet, $c, $row)));
			if ($text === '') {
				continue;
			}
			if (strpos($text, 'children') !== false) {
				$cols['children'] = $c;
			} elseif ($text === 'fees' || $text === 'fee') {
				$cols['fees'] = $c;
			} elseif (strpos($text, 'total income') !== false) {
				$cols['total'] = $c;
			} elseif ($text === 'budget') {
				$cols['budget'] = $c;
			}
		}
		return $cols;
	}

	private function termIndex(string $text): int
	{
		$t = strtoupper($text);
		if (preg_match('/TERM\s*(III|3)\b/', $t)) {
			return 3;
		}
		if (preg_match('/TERM\s*(II|2)\b/', $t)) {
			return 2;
		}
		if (preg_match('/TERM\s*I\b/', $t) || preg_match('/TERM\s*1\b/', $t)) {
			return 1;
		}
		return 0;
	}

	private function academicYear(string $text): string
	{
		if (preg_match('/(20\d{2})\s*[-–\/]\s*(20\d{2}|\d{2})/', $text, $m)) {
			$end = $m[2];
			if (strlen($end) === 4) {
				$end = substr($end, -2);
			}
			return $m[1] . '-' . $end;
		}
		return '';
	}

	private function cell(Worksheet $sheet, int $col, int $row)
	{
		try {
			$addr = Coordinate::stringFromColumnIndex($col) . $row;
			$value = $sheet->getCell($addr)->getCalculatedValue();
		} catch (\Throwable $e) {
			return null;
		}
		if (is_string($value)) {
			$value = trim(preg_replace('/\s+/', ' ', $value));
		}
		return $value;
	}

	private function num($value): ?float
	{
		if ($value === null || $value === '') {
			return null;
		}
		if (is_numeric($value)) {
			return (float) $value;
		}
		if (is_string($value)) {
			$clean = str_replace([',', ' '], '', $value);
			if (is_numeric($clean)) {
				return (float) $clean;
			}
		}
		return null;
	}

	private function fmt(float $n): string
	{
		return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
	}
}
