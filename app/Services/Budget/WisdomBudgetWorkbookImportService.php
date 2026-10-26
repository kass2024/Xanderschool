<?php

namespace App\Services\Budget;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Reads the official Wisdom budget workbook and stores every budget line.
 *
 * The workbook must match WISDOM_SCHOOL_BUDGET_TOTALS: the same line names,
 * columns, and formulas. A school may change only quantities (No of Children)
 * and money (Fees, Budget, and Expenses).
 * Income for a line is children × fees. The term total is cell D9 on the official sheet.
 * Expense money is the Budget column. The term total is cell E40.
 * If Budget is blank and the Expenses column has an amount, that amount is used.
 * The heading must read BUDGETS TERM … ACAD YEAR, and that term must be the
 * term selected on upload.
 */
class WisdomBudgetWorkbookImportService
{
	public const SOURCE = 'wisdom_excel_upload';

	/** @var \PhpOffice\PhpSpreadsheet\Spreadsheet|null */
	private $officialBook;

	/** @var Worksheet|null */
	private $officialSheet;

	/**
	 * Original workbook saved when an accountant uploaded this budget.
	 *
	 * @return array{path:string,name:string}|null
	 */
	public static function workbookFile(?array $budget): ?array
	{
		if (!$budget) {
			return null;
		}
		$notes = $budget['notes'] ?? null;
		$decoded = is_array($notes) ? $notes : json_decode((string) $notes, true);
		if (!is_array($decoded) || ($decoded['source'] ?? '') !== self::SOURCE) {
			return null;
		}
		$stored = str_replace('\\', '/', (string) ($decoded['excel_stored_path'] ?? ''));
		$base = basename($stored);
		if (!preg_match('/^[a-f0-9]{32}\.(xlsx|xls)$/i', $base)) {
			return null;
		}
		$path = WRITEPATH . 'uploads/budget/prepared/' . $base;
		if (!is_file($path)) {
			return null;
		}
		$name = str_replace(['"', "\r", "\n", '/', '\\'], '', trim((string) ($decoded['excel_file'] ?? '')));
		if ($name === '' || !preg_match('/\.(xlsx|xls)$/i', $name)) {
			$name = 'budget-' . (int) ($budget['id'] ?? $budget['budget_id'] ?? 0) . '.' . strtolower(pathinfo($base, PATHINFO_EXTENSION));
		}
		if (strlen($name) > 180) {
			$name = substr($name, -180);
		}
		return ['path' => $path, 'name' => $name];
	}

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

	public function parseFile(string $filePath, int $expectedTerm = 0): array
	{
		if (!is_file($filePath)) {
			return ['success' => false, 'error' => 'Excel file was not found.'];
		}
		try {
			$spreadsheet = IOFactory::load($filePath);
		} catch (\Throwable $e) {
			return ['success' => false, 'error' => 'Could not read this Excel file. Use the official Wisdom budget workbook (.xlsx).'];
		}
		$sheet = $this->pickSheet($spreadsheet);
		if (!$sheet) {
			return ['success' => false, 'error' => 'No sheet with the INCOME block from the official Wisdom budget workbook was found.'];
		}
		$problems = $this->officialFormatProblems($sheet);
		if ($problems) {
			return [
				'success' => false,
				'error' => 'This workbook was rejected because it does not match the official Wisdom budget format. '
					. 'Keep every line name, column, and formula. Only the quantities (No of Children) and the money (Fees, Budget, and Expenses) may change. '
					. implode(' ', array_slice($problems, 0, 4)),
			];
		}
		$heading = trim((string) $this->rawCell($sheet, 1, 2));
		$fileTerm = $this->headingTerm($heading);
		if ($expectedTerm >= 1 && $expectedTerm <= 3 && $fileTerm !== $expectedTerm) {
			return [
				'success' => false,
				'error' => $this->termMismatchMessage($fileTerm, $expectedTerm, $heading),
			];
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
			return ['success' => false, 'error' => 'The file has the official lines but no amounts. Fill No of Children, Fees, and the Budget column, then upload again.'];
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
		$chosenTerm = (int) ($ctx['budget_term'] ?? 0);
		$parsed = $this->parseFile($filePath, $chosenTerm);
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
			'HEADTEACHER_REVIEW', 'CHIEF_ACCOUNTANT_REVIEW', 'DEPUTY_DIRECTOR_REVIEW',
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

		$fileTerm = (int) ($parsed['term_index'] ?? 0);
		$termIndex = ($fileTerm >= 1 && $fileTerm <= 3) ? $fileTerm : $chosenTerm;
		$preserveOtherTerms = $termIndex >= 1 && $termIndex <= 3;
		$excelByTerm = [];
		if ($existing && !empty($existing['notes'])) {
			$oldNotes = json_decode((string) $existing['notes'], true);
			if (is_array($oldNotes) && !empty($oldNotes['excel_by_term']) && is_array($oldNotes['excel_by_term'])) {
				$excelByTerm = $oldNotes['excel_by_term'];
			} elseif (is_array($oldNotes) && !empty($oldNotes['excel_stored_path'])) {
				$excelByTerm[(string) ((int) ($oldNotes['excel_term'] ?? 0))] = [
					'excel_file' => (string) ($oldNotes['excel_file'] ?? ''),
					'excel_stored_path' => (string) ($oldNotes['excel_stored_path'] ?? ''),
				];
			}
		}
		$excelByTerm[(string) $termIndex] = [
			'excel_file' => (string) ($ctx['original_name'] ?? basename($filePath)),
			'excel_stored_path' => (string) ($ctx['stored_path'] ?? ''),
		];
		$notes = [
			'source' => self::SOURCE,
			'academic_year' => $year,
			'planning_type' => 'excel_upload',
			'excel_file' => (string) ($ctx['original_name'] ?? basename($filePath)),
			'excel_stored_path' => (string) ($ctx['stored_path'] ?? ''),
			'excel_school' => (string) ($parsed['school_name'] ?? ''),
			'excel_period' => (string) ($parsed['period_hint'] ?? ''),
			'excel_term' => $termIndex,
			'excel_by_term' => $excelByTerm,
			'prepared_by_excel' => (string) ($parsed['prepared_by'] ?? ''),
			'verified_by_excel' => (string) ($parsed['verified_by'] ?? ''),
			'uploaded_at' => date('Y-m-d H:i:s'),
			'enrollment' => (int) ($parsed['enrollment'] ?? 0),
		];

		$db->transStart();
		$preservedLines = [];
		if ($existing) {
			$budgetId = (int) $existing['id'];
			if ($preserveOtherTerms) {
				$oldLines = $db->table('budget_lines')->where('budget_id', $budgetId)->where('is_total_row', 0)->get()->getResultArray();
				foreach ($oldLines as $oldLine) {
					$preservedLines[$this->lineMatchKey((string) ($oldLine['section_label'] ?? ''), (string) ($oldLine['category'] ?? ''))] = $oldLine;
				}
			}
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
		$seenKeys = [];
		foreach ($parsed['rows'] as $row) {
			$amount = round((float) ($row['amount'] ?? 0), 2);
			$section = (string) ($row['section'] ?? 'OPERATING EXPENSES');
			$category = mb_substr((string) $row['category'], 0, 180);
			$key = $this->lineMatchKey($section, $category);
			$seenKeys[$key] = true;
			$prev = $preservedLines[$key] ?? null;
			$terms = [1 => 0.0, 2 => 0.0, 3 => 0.0];
			if ($prev && $preserveOtherTerms) {
				$terms[1] = (float) ($prev['term_1_amount'] ?? 0);
				$terms[2] = (float) ($prev['term_2_amount'] ?? 0);
				$terms[3] = (float) ($prev['term_3_amount'] ?? 0);
			}
			if ($termIndex >= 1 && $termIndex <= 3) {
				$terms[$termIndex] = $amount;
			} else {
				$share = floor($amount / 3);
				$terms[1] = $share;
				$terms[2] = $share;
				$terms[3] = round($amount - ($share * 2), 2);
			}
			$annual = round($terms[1] + $terms[2] + $terms[3], 2);
			$isIncome = stripos($section, 'INCOME') !== false;
			$db->table('budget_lines')->insert([
				'budget_id' => $budgetId,
				'section_label' => $section,
				'category' => $category,
				'description' => (string) ($row['note'] ?? ''),
				'quantity' => $isIncome ? ($row['quantity'] ?? null) : null,
				'unit_cost' => $isIncome ? ($row['unit_cost'] ?? null) : null,
				'frequency' => 1,
				'calculation_mode' => 'term_sum',
				'term_1_amount' => $terms[1],
				'term_2_amount' => $terms[2],
				'term_3_amount' => $terms[3],
				'annual_amount' => $annual,
				'user_amount' => $annual,
				'is_total_row' => 0,
				'is_editable' => 1,
				'sort_order' => $order++,
			]);
		}
		if ($preserveOtherTerms) {
			foreach ($preservedLines as $key => $oldLine) {
				if (isset($seenKeys[$key])) {
					continue;
				}
				$terms = [
					1 => (float) ($oldLine['term_1_amount'] ?? 0),
					2 => (float) ($oldLine['term_2_amount'] ?? 0),
					3 => (float) ($oldLine['term_3_amount'] ?? 0),
				];
				if ($termIndex >= 1 && $termIndex <= 3) {
					$terms[$termIndex] = 0.0;
				}
				$annual = round($terms[1] + $terms[2] + $terms[3], 2);
				if ($annual <= 0) {
					continue;
				}
				$db->table('budget_lines')->insert([
					'budget_id' => $budgetId,
					'section_label' => (string) ($oldLine['section_label'] ?? ''),
					'category' => (string) ($oldLine['category'] ?? ''),
					'description' => (string) ($oldLine['description'] ?? ''),
					'quantity' => $oldLine['quantity'] ?? null,
					'unit_cost' => $oldLine['unit_cost'] ?? null,
					'frequency' => 1,
					'calculation_mode' => 'term_sum',
					'term_1_amount' => $terms[1],
					'term_2_amount' => $terms[2],
					'term_3_amount' => $terms[3],
					'annual_amount' => $annual,
					'user_amount' => $annual,
					'is_total_row' => 0,
					'is_editable' => 1,
					'sort_order' => $order++,
				]);
			}
		}
		$totals = (new BudgetCalculationService())->recalculateBudgetTotals($budgetId);
		$totals = $this->applyHeadlineTotals(
			$db,
			$budgetId,
			$termIndex,
			(float) ($parsed['sheet_income'] ?? 0),
			(float) ($parsed['sheet_expenses'] ?? 0),
			$totals
		);
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
			'sheet_income' => (float) ($parsed['sheet_income'] ?? 0),
			'sheet_expenses' => (float) ($parsed['sheet_expenses'] ?? 0),
			'school_name' => $parsed['school_name'] ?? '',
			'period_hint' => $parsed['period_hint'] ?? '',
			'term_index' => $termIndex,
		];
	}

	/**
	 * Keep the saved totals on the official cells: D9 for income and E40 for expenses.
	 * Other terms already stored on the same budget stay in the annual total.
	 *
	 * @param array{income?:float,expenses?:float,surplus?:float} $totals
	 * @return array{income?:float,expenses?:float,surplus?:float}
	 */
	private function applyHeadlineTotals($db, int $budgetId, int $termIndex, float $sheetIncome, float $sheetExpenses, array $totals): array
	{
		if ($termIndex < 1 || $termIndex > 3 || ($sheetIncome <= 0 && $sheetExpenses <= 0)) {
			return $totals;
		}
		$lines = $db->table('budget_lines')->where('budget_id', $budgetId)->where('is_total_row', 0)->get()->getResultArray();
		$thisIncome = 0.0;
		$thisExpense = 0.0;
		$otherIncome = 0.0;
		$otherExpense = 0.0;
		foreach ($lines as $line) {
			$isIncome = stripos((string) ($line['section_label'] ?? ''), 'INCOME') !== false;
			for ($t = 1; $t <= 3; $t++) {
				$amt = (float) ($line['term_' . $t . '_amount'] ?? 0);
				if ($t === $termIndex) {
					if ($isIncome) {
						$thisIncome += $amt;
					} else {
						$thisExpense += $amt;
					}
				} elseif ($isIncome) {
					$otherIncome += $amt;
				} else {
					$otherExpense += $amt;
				}
			}
		}
		$income = ($sheetIncome > 0 && abs($thisIncome - $sheetIncome) <= 1)
			? round($otherIncome + $sheetIncome, 2)
			: (float) ($totals['income'] ?? 0);
		$expense = ($sheetExpenses > 0 && abs($thisExpense - $sheetExpenses) <= 1)
			? round($otherExpense + $sheetExpenses, 2)
			: (float) ($totals['expenses'] ?? 0);
		$surplus = round($income - $expense, 2);
		$db->table('budgets')->where('id', $budgetId)->update([
			'total_income' => $income,
			'total_expenses' => $expense,
			'surplus_deficit' => $surplus,
			'updated_at' => date('Y-m-d H:i:s'),
		]);
		$totals['income'] = $income;
		$totals['expenses'] = $expense;
		$totals['surplus'] = $surplus;
		return $totals;
	}

	private function lineMatchKey(string $section, string $category): string
	{
		$norm = static function (string $value): string {
			$value = trim((string) preg_replace('/\s+/', ' ', $value));
			return function_exists('mb_strtoupper') ? mb_strtoupper($value) : strtoupper($value);
		};
		return $norm($section) . '|' . $norm($category);
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

		$cols = ['children' => 2, 'fees' => 3, 'total' => 4, 'budget' => 5, 'spent' => 6];
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
				// Official format: Total Income = No of Children × Fees.
				$amount = round((float) ($children ?? 0) * (float) ($fees ?? 0), 2);
				$unitCost = $fees;
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
					'unit_cost' => $unitCost,
					'note' => $note,
				];
				continue;
			}

			$budget = $this->num($this->cell($sheet, $cols['budget'], $r));
			$spent = $this->num($this->cell($sheet, $cols['spent'], $r));
			// Official total is the Budget column (E40). Use Expenses only when Budget is blank.
			if ($budget !== null && abs($budget) > 0) {
				$amount = $budget;
			} elseif ($spent !== null && abs($spent) > 0) {
				$amount = $spent;
			} else {
				$amount = 0.0;
			}
			$rows[] = [
				'section' => $section,
				'category' => $label,
				'amount' => round((float) $amount, 2),
				'quantity' => null,
				'unit_cost' => null,
				'note' => '',
			];
		}

		$headline = $this->headlineTotals($sheet, $cols);
		return [
			'rows' => $rows,
			'school_name' => $schoolName,
			'period_hint' => $periodHint,
			'academic_year' => $academicYear,
			'term_index' => $termIndex,
			'prepared_by' => $preparedBy,
			'verified_by' => $verifiedBy,
			'enrollment' => $enrollment,
			'sheet_income' => $headline['income'],
			'sheet_expenses' => $headline['expenses'],
		];
	}

	/**
	 * Official workbook: total income is D9 and total expenses are E40.
	 * A sheet that shifted by a row is read from the TOTAL INCOME and TOTAL EXPENSES labels.
	 * When E40 is blank, the Expenses column on that total row is used.
	 *
	 * @param array{children:int,fees:int,total:int,budget:int,spent:int} $cols
	 * @return array{income:float,expenses:float}
	 */
	private function headlineTotals(Worksheet $sheet, array $cols): array
	{
		$income = null;
		$expenses = null;
		$highest = min(80, max(1, (int) $sheet->getHighestRow()));
		for ($r = 1; $r <= $highest; $r++) {
			$label = strtoupper(trim((string) preg_replace('/\s+/', ' ', (string) $this->rawCell($sheet, 1, $r))));
			if (preg_match('/^TOTAL\s+INCOME\b/', $label)) {
				$income = $this->num($this->cell($sheet, (int) $cols['total'], $r));
			}
			if (preg_match('/^TOTAL\s+EXPENSES\b/', $label)) {
				$fromBudget = $this->num($this->cell($sheet, (int) $cols['budget'], $r));
				$fromSpent = $this->num($this->cell($sheet, (int) $cols['spent'], $r));
				if ($fromBudget !== null && abs($fromBudget) > 0) {
					$expenses = $fromBudget;
				} elseif ($fromSpent !== null && abs($fromSpent) > 0) {
					$expenses = $fromSpent;
				} else {
					$expenses = 0.0;
				}
			}
		}
		if ($income === null) {
			$income = $this->num($this->cell($sheet, 4, 9));
		}
		if ($expenses === null) {
			$expenses = $this->num($this->cell($sheet, 5, 40));
		}
		return [
			'income' => round((float) ($income ?? 0), 2),
			'expenses' => round((float) ($expenses ?? 0), 2),
		];
	}

	/**
	 * Cells a school is allowed to type into: quantity and money only.
	 *
	 * @return array<string,string> address => human label
	 */
	private function moneyInputAddresses(Worksheet $sheet): array
	{
		$highest = min(80, max(1, (int) $sheet->getHighestRow()));
		$mode = null;
		$map = [];
		for ($r = 1; $r <= $highest; $r++) {
			$label = trim((string) $this->rawCell($sheet, 1, $r));
			if ($label === '') {
				continue;
			}
			$upper = strtoupper(preg_replace('/\s+/', ' ', $label));
			if (isset(self::SECTION_HEADERS[$upper])) {
				$mode = self::SECTION_HEADERS[$upper] === 'income' ? 'income' : 'expense';
				continue;
			}
			if (preg_match('/^TOTAL\b/i', $label) || $mode === null) {
				continue;
			}
			if ($mode === 'income') {
				$map['B' . $r] = 'No of Children for ' . $label;
				$map['C' . $r] = 'Fees for ' . $label;
			} else {
				$map['E' . $r] = 'Budget for ' . $label;
				$map['F' . $r] = 'Expenses for ' . $label;
			}
		}
		return $map;
	}

	/**
	 * @return list<string>
	 */
	private function officialFormatProblems(Worksheet $sheet): array
	{
		$canon = $this->canonicalSheet();
		if (!$canon) {
			return ['The official Wisdom budget workbook is not installed.'];
		}
		$inputs = $this->moneyInputAddresses($canon);
		$problems = [];
		$maxRow = min(80, max((int) $canon->getHighestRow(), (int) $sheet->getHighestRow(), 1));
		for ($r = 1; $r <= $maxRow; $r++) {
			for ($c = 1; $c <= 7; $c++) {
				if ($r === 1 && $c <= 2) {
					continue;
				}
				if ($this->isMergeSlave($canon, $c, $r)) {
					continue;
				}
				$addr = Coordinate::stringFromColumnIndex($c) . $r;
				$canonRaw = $this->rawCell($canon, $c, $r);
				$uploadRaw = $this->rawCell($sheet, $c, $r);
				$canonEmpty = $canonRaw === null || $canonRaw === '';
				$uploadEmpty = $uploadRaw === null || $uploadRaw === '';
				if ($r === 2 && $c === 1) {
					$heading = is_string($uploadRaw) ? $uploadRaw : '';
					if ($this->headingTerm($heading) < 1) {
						$problems[] = 'Row 2 must be the term heading, for example BUDGETS TERM I ACAD YEAR 2026-2027.';
					}
					continue;
				}
				if (isset($inputs[$addr])) {
					if ($uploadEmpty) {
						continue;
					}
					if (is_string($uploadRaw) && isset($uploadRaw[0]) && $uploadRaw[0] === '=') {
						$problems[] = $inputs[$addr] . ' must be a number. Only the quantity and the money amounts may change.';
						continue;
					}
					if ($this->num($uploadRaw) === null) {
						$problems[] = $inputs[$addr] . ' must be a number.';
					}
					continue;
				}
				$canonFormula = is_string($canonRaw) && isset($canonRaw[0]) && $canonRaw[0] === '=';
				$uploadFormula = is_string($uploadRaw) && isset($uploadRaw[0]) && $uploadRaw[0] === '=';
				if ($canonFormula) {
					if (!$uploadFormula || !$this->formulasMatch((string) $canonRaw, (string) $uploadRaw)) {
						$problems[] = 'Cell ' . $addr . ' must keep the formula ' . $canonRaw . '.';
					}
					continue;
				}
				if (!$canonEmpty && is_string($canonRaw)) {
					$want = $this->normLabel($canonRaw);
					$gotRaw = $uploadEmpty ? '' : (is_scalar($uploadRaw) ? (string) $uploadRaw : '');
					if ($want !== $this->normLabel($gotRaw)) {
						$shown = trim($canonRaw);
						$problems[] = $gotRaw === ''
							? 'Cell ' . $addr . ' must stay "' . $shown . '".'
							: 'Cell ' . $addr . ' must stay "' . $shown . '" (found "' . trim($gotRaw) . '").';
					}
					continue;
				}
				if (!$canonEmpty && is_numeric($canonRaw)) {
					$got = $this->num($uploadRaw);
					if ($got === null || abs($got - (float) $canonRaw) > 0.009) {
						$problems[] = 'Cell ' . $addr . ' must stay ' . $canonRaw . '.';
					}
					continue;
				}
				if ($canonEmpty && !$uploadEmpty) {
					$problems[] = 'Cell ' . $addr . ' must stay empty. Only quantities and money amounts may be filled in.';
				}
			}
			if (count($problems) >= 8) {
				break;
			}
		}
		return $problems;
	}

	private function canonicalSheet(): ?Worksheet
	{
		if ($this->officialSheet instanceof Worksheet) {
			return $this->officialSheet;
		}
		$path = $this->templatePath();
		if (!$path) {
			return null;
		}
		try {
			$this->officialBook = IOFactory::load($path);
		} catch (\Throwable $e) {
			return null;
		}
		$this->officialSheet = $this->officialBook->getSheet(0);
		return $this->officialSheet;
	}

	private function isMergeSlave(Worksheet $sheet, int $col, int $row): bool
	{
		try {
			$coord = Coordinate::stringFromColumnIndex($col) . $row;
			$range = $sheet->getCell($coord)->getMergeRange();
		} catch (\Throwable $e) {
			return false;
		}
		if (!is_string($range) || strpos($range, ':') === false) {
			return false;
		}
		$start = strtoupper(explode(':', $range)[0]);
		return $start !== strtoupper($coord);
	}

	private function headingTerm(string $text): int
	{
		$t = strtoupper(trim((string) preg_replace('/\s+/', ' ', $text)));
		if (!preg_match('/\bBUDGETS?\s+TERM\s+(III|II|I|[123])\b/', $t)) {
			return 0;
		}
		if (!preg_match('/\bACAD(?:EMIC)?\s+YEAR\b/', $t)) {
			return 0;
		}
		if (!preg_match('/20\d{2}/', $t)) {
			return 0;
		}
		return $this->termIndex($t);
	}

	private function termName(int $term): string
	{
		return [1 => 'Term I', 2 => 'Term II', 3 => 'Term III'][$term] ?? 'that term';
	}

	private function termMismatchMessage(int $fileTerm, int $expected, string $heading): string
	{
		$fileName = $this->termName($fileTerm);
		$selected = $this->termName($expected);
		return 'This Excel heading is "' . trim($heading) . '", which is ' . $fileName
			. '. You selected ' . $selected
			. '. Choose ' . $fileName . ' on the upload form, or change the heading so it names ' . $selected . '.';
	}

	private function normLabel($value): string
	{
		if ($value instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
			$value = $value->getPlainText();
		}
		$text = trim((string) preg_replace('/\s+/', ' ', (string) $value));
		$text = rtrim($text, '.');
		return function_exists('mb_strtoupper') ? mb_strtoupper($text) : strtoupper($text);
	}

	private function normFormula(string $formula): string
	{
		$formula = strtoupper(trim($formula));
		if ($formula !== '' && isset($formula[0]) && $formula[0] === '=') {
			$formula = substr($formula, 1);
		}
		return str_replace([' ', '$'], '', $formula);
	}

	private function formulasMatch(string $left, string $right): bool
	{
		$a = $this->normFormula($left);
		$b = $this->normFormula($right);
		if ($a === $b) {
			return true;
		}
		$strip = static function (string $formula): string {
			if (preg_match('/^SUM\(([^()]*)\)$/', $formula, $m) && strpos($m[1], ':') === false && strpos($m[1], ',') === false) {
				return $m[1];
			}
			return $formula;
		};
		return $strip($a) === $strip($b);
	}

	private function sheetSnapshot(Worksheet $sheet): string
	{
		$highest = min(60, max(1, (int) $sheet->getHighestRow()));
		$lines = [];
		for ($r = 1; $r <= $highest; $r++) {
			$parts = [];
			for ($c = 1; $c <= 7; $c++) {
				$raw = $this->rawCell($sheet, $c, $r);
				if ($raw === null || $raw === '') {
					continue;
				}
				$shown = is_string($raw) ? $raw : (string) $raw;
				if (strlen($shown) > 80) {
					$shown = substr($shown, 0, 80);
				}
				$parts[] = Coordinate::stringFromColumnIndex($c) . '=' . $shown;
			}
			if ($parts) {
				$lines[] = $r . ': ' . implode(' | ', $parts);
			}
		}
		$text = implode("\n", $lines);
		if (strlen($text) > 12000) {
			$text = substr($text, 0, 12000);
		}
		return $text;
	}

	private function rawCell(Worksheet $sheet, int $col, int $row)
	{
		try {
			$value = $sheet->getCell(Coordinate::stringFromColumnIndex($col) . $row)->getValue();
		} catch (\Throwable $e) {
			return null;
		}
		if ($value instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
			$value = $value->getPlainText();
		}
		if (is_string($value)) {
			$value = trim(preg_replace('/\s+/', ' ', $value));
		}
		return $value;
	}

	private function formula(Worksheet $sheet, int $col, int $row): string
	{
		$value = $this->rawCell($sheet, $col, $row);
		if (!is_string($value)) {
			return '';
		}
		$value = trim($value);
		return (strlen($value) > 1 && $value[0] === '=') ? $value : '';
	}

	private function formulaColumn(string $formula): ?string
	{
		if (!preg_match_all('/\b([A-Z]{1,3})\d+\b/', strtoupper($formula), $m)) {
			return null;
		}
		$cols = array_values(array_unique($m[1]));
		return count($cols) === 1 ? $cols[0] : null;
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
			} elseif ($text === 'expenses' || $text === 'expense') {
				$cols['spent'] = $c;
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
