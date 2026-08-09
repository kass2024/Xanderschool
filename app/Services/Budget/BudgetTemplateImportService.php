<?php

namespace App\Services\Budget;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class BudgetTemplateImportService
{
	public const OFFICIAL_TEMPLATE = 'Secondary_School_Budget_Template.xlsx';
	public const OFFICIAL_TEMPLATE_NAME = 'Secondary School Budget Template';

	private static $defaultCategories = [
		['INCOME', 'School Fees', false],
		['INCOME', 'Lunch', false],
		['INCOME', 'Breakfast', false],
		['INCOME', 'Transport', false],
		['INCOME', 'Other Fees', false],
		['INCOME', 'Total Income', true],
		['OPERATING EXPENSES', 'School Materials', false],
		['OPERATING EXPENSES', 'Food Expenses', false],
		['OPERATING EXPENSES', 'Travel, Mission & Transport Expenses', false],
		['OPERATING EXPENSES', 'Fuel', false],
		['OPERATING EXPENSES', 'Vehicle Maintenance', false],
		['OPERATING EXPENSES', 'Utilities (Water & Electricity)', false],
		['OPERATING EXPENSES', 'Rental Expenses', false],
		['OPERATING EXPENSES', 'Insurance', false],
		['OPERATING EXPENSES', 'Taxes', false],
		['OPERATING EXPENSES', 'Communication', false],
		['OPERATING EXPENSES', 'Capacity Building', false],
		['OPERATING EXPENSES', 'Audit Fees', false],
		['OPERATING EXPENSES', 'Rehabilitation & Maintenance', false],
		['OPERATING EXPENSES', 'Donation', false],
		['OPERATING EXPENSES', 'First Aid & Hygiene', false],
		['OPERATING EXPENSES', 'Fines & Penalties', false],
		['OPERATING EXPENSES', 'Payables', false],
		['OPERATING EXPENSES', 'Total Operating Expenses', true],
		['ADMINISTRATIVE COSTS', 'Staff Salaries', false],
		['ADMINISTRATIVE COSTS', 'RSSB Contributions', false],
		['ADMINISTRATIVE COSTS', 'Marketing, Meetings, Conferences & Rewards', false],
		['ADMINISTRATIVE COSTS', 'Total Administrative Expenses', true],
		['FINANCE COSTS', 'Bank Loan Payments', false],
		['FINANCE COSTS', 'Bank Charges & Account Maintenance Fees', false],
		['FINANCE COSTS', 'Total Finance Costs', true],
	];

	private static $sectionTotals = [
		'INCOME' => 'Total Income',
		'OPERATING EXPENSES' => 'Total Operating Expenses',
		'ADMINISTRATIVE COSTS' => 'Total Administrative Expenses',
		'FINANCE COSTS' => 'Total Finance Costs',
	];

	public function officialTemplatePath()
	{
		$candidates = [
			WRITEPATH . 'templates/budget/' . self::OFFICIAL_TEMPLATE,
			FCPATH . 'assets/templates/budget/' . self::OFFICIAL_TEMPLATE,
			ROOTPATH . 'public/assets/templates/budget/' . self::OFFICIAL_TEMPLATE,
			ROOTPATH . 'writable/templates/budget/' . self::OFFICIAL_TEMPLATE,
		];
		foreach ($candidates as $path) {
			if (is_file($path)) {
				return $path;
			}
		}
		return null;
	}

	public function normalizeLabel($label)
	{
		$label = trim(preg_replace('/\s+/', ' ', (string) $label));
		$fixes = [
			'Utilities: Water & Electricity' => 'Utilities (Water & Electricity)',
			'Rehabilitation & Maintenace' => 'Rehabilitation & Maintenance',
		];
		return $fixes[$label] ?? $label;
	}

	public function mapCalculationMode($raw)
	{
		$raw = strtolower(trim((string) $raw));
		if ($raw === 'monthly') {
			return 'monthly';
		}
		if ($raw === 'unit-based' || $raw === 'unit based') {
			return 'qty_unit_freq';
		}
		if ($raw === 'manual annual' || $raw === 'manual') {
			return 'manual';
		}
		return 'manual';
	}

	public function parseUpload($filePath, $originalName = '')
	{
		try {
			$spreadsheet = IOFactory::load($filePath);
		} catch (\Throwable $e) {
			return ['success' => false, 'error' => 'Could not parse Excel: ' . $e->getMessage()];
		}

		$format = $this->detectFormat($spreadsheet);
		if ($format === 'secondary_school') {
			$rows = $this->parseSecondarySchoolWorkbook($spreadsheet);
			if (!empty($rows)) {
				return [
					'success' => true,
					'rows' => $rows,
					'sheets' => count($spreadsheet->getSheetNames()),
					'format' => $format,
					'template_name' => self::OFFICIAL_TEMPLATE_NAME,
				];
			}
		}
		if ($format === 'wisdom_professional') {
			$rows = $this->parseWisdomProfessionalWorkbook($spreadsheet);
			if (!empty($rows)) {
				return [
					'success' => true,
					'rows' => $rows,
					'sheets' => count($spreadsheet->getSheetNames()),
					'format' => $format,
					'template_name' => self::OFFICIAL_TEMPLATE_NAME,
				];
			}
		}

		$rows = $this->parseLegacySheet($spreadsheet->getSheet(0));
		if (empty($rows)) {
			$rows = $this->defaultStructure();
		}
		return ['success' => true, 'rows' => $rows, 'sheets' => 1, 'format' => 'legacy'];
	}

	public function detectFormat(Spreadsheet $spreadsheet)
	{
		$names = array_map('strtoupper', $spreadsheet->getSheetNames());
		if (in_array('DETAILED BUDGET', $names, true) && in_array('FEES & ENROLLMENT', $names, true)) {
			return 'secondary_school';
		}
		if (in_array('IMPORT_TEMPLATE', $names, true) || in_array('BUDGET_PLAN', $names, true)) {
			return 'wisdom_professional';
		}
		return 'legacy';
	}

	public function parseSecondarySchoolWorkbook(Spreadsheet $spreadsheet)
	{
		$names = $spreadsheet->getSheetNames();
		$sheet = null;
		foreach ($names as $name) {
			if (strtoupper($name) === 'DETAILED BUDGET') {
				$sheet = $spreadsheet->getSheetByName($name);
				break;
			}
		}
		if (!$sheet) {
			return [];
		}
		$data = $sheet->toArray(null, true, true, true);
		$headerRow = $this->findHeaderRow($data, ['budget line', 'category', 'type']);
		if ($headerRow === null) {
			return [];
		}
		$headers = $this->normalizeHeaders($data[$headerRow]);
		$rows = [];
		$order = 0;
		for ($i = $headerRow + 1; $i <= count($data); $i++) {
			if (!isset($data[$i])) {
				continue;
			}
			$row = $this->rowByHeaders($data[$i], $headers);
			$label = trim((string) ($row['budget line / description'] ?? $row['budget line'] ?? ''));
			$category = trim((string) ($row['category'] ?? $label));
			$type = strtolower(trim((string) ($row['type'] ?? '')));
			if ($label === '' || $category === '' || $type === '') {
				continue;
			}
			if (preg_match('/^total/i', $label)) {
				continue;
			}
			$section = ($type === 'revenue') ? 'INCOME' : 'OPERATING EXPENSES';
			$mapped = [
				'line_id' => 'SS-' . str_pad((string) ($order + 1), 3, '0', STR_PAD_LEFT),
				'section' => $section,
				'category' => $category,
				'subcategory' => $label !== $category ? $label : '',
				'account_code' => '',
				'calculation_mode' => 'unit-based',
				'quantity' => (float) ($row['quantity'] ?? 1),
				'unit' => 'Item',
				'unit_cost' => (float) ($row['unit cost'] ?? 0),
				'frequency' => 1,
				'priority' => 'Medium',
				'funding_source' => '',
				'notes' => trim((string) ($row['notes'] ?? '')),
			];
			$rows[] = $this->buildParsedRow($mapped, $section, $category, $order++);
		}
		return $this->appendSectionTotals($rows);
	}

	public function parseWisdomProfessionalWorkbook(Spreadsheet $spreadsheet)
	{
		$names = $spreadsheet->getSheetNames();
		$upper = array_map('strtoupper', $names);
		if (in_array('IMPORT_TEMPLATE', $upper, true)) {
			$sheetName = $names[array_search('IMPORT_TEMPLATE', $upper, true)];
			$rows = $this->parseStructuredSheet($spreadsheet->getSheetByName($sheetName));
			if (!empty($rows)) {
				return $this->appendSectionTotals($rows);
			}
		}
		if (in_array('BUDGET_PLAN', $upper, true)) {
			$sheetName = $names[array_search('BUDGET_PLAN', $upper, true)];
			$rows = $this->parseBudgetPlanSheet($spreadsheet->getSheetByName($sheetName));
			if (!empty($rows)) {
				return $this->appendSectionTotals($rows);
			}
		}
		return [];
	}

	private function parseStructuredSheet($sheet)
	{
		$data = $sheet->toArray(null, true, true, true);
		$headerRow = $this->findHeaderRow($data, ['line_id', 'section', 'category']);
		if ($headerRow === null) {
			return [];
		}
		$headers = $this->normalizeHeaders($data[$headerRow]);
		$rows = [];
		$order = 0;
		for ($i = $headerRow + 1; $i <= count($data); $i++) {
			if (!isset($data[$i])) {
				continue;
			}
			$row = $this->rowByHeaders($data[$i], $headers);
			$section = strtoupper(trim((string) ($row['section'] ?? '')));
			$category = trim((string) ($row['category'] ?? ''));
			if ($section === '' || $category === '' || $category === '0') {
				continue;
			}
			$rows[] = $this->buildParsedRow($row, $section, $category, $order++);
		}
		return $rows;
	}

	private function parseBudgetPlanSheet($sheet)
	{
		$data = $sheet->toArray(null, true, true, true);
		$headerRow = $this->findHeaderRow($data, ['line id', 'section', 'category']);
		if ($headerRow === null) {
			return [];
		}
		$headers = $this->normalizeHeaders($data[$headerRow]);
		$rows = [];
		$order = 0;
		for ($i = $headerRow + 1; $i <= count($data); $i++) {
			if (!isset($data[$i])) {
				continue;
			}
			$row = $this->rowByHeaders($data[$i], $headers);
			$section = strtoupper(trim((string) ($row['section'] ?? '')));
			$category = trim((string) ($row['category'] ?? ''));
			if ($section === '' || $category === '') {
				continue;
			}
			$mapped = [
				'line_id' => $row['line id'] ?? $row['line_id'] ?? ('BL-' . str_pad((string) ($order + 1), 3, '0', STR_PAD_LEFT)),
				'section' => $section,
				'category' => $category,
				'subcategory' => $row['subcategory / description'] ?? $row['subcategory'] ?? '',
				'account_code' => $row['account code'] ?? $row['account_code'] ?? '',
				'calculation_mode' => $row['calculation mode'] ?? $row['calculation_mode'] ?? 'Manual Annual',
				'quantity' => $row['quantity'] ?? 0,
				'unit' => $row['unit'] ?? '',
				'unit_cost' => $row['unit cost'] ?? $row['unit_cost'] ?? 0,
				'frequency' => $row['frequency'] ?? 1,
				'priority' => 'Medium',
				'funding_source' => '',
				'notes' => '',
			];
			$rows[] = $this->buildParsedRow($mapped, $section, $category, $order++);
		}
		return $rows;
	}

	private function buildParsedRow(array $row, $section, $category, $order)
	{
		$category = $this->normalizeLabel($category);
		$isTotal = (bool) preg_match('/^total/i', $category);
		$lineId = trim((string) ($row['line_id'] ?? ''));
		if ($lineId === '') {
			$lineId = 'L' . ($order + 1);
		}
		return [
			'section' => $section,
			'original_label' => $category,
			'normalized_label' => $category,
			'is_total_row' => $isTotal,
			'is_editable' => !$isTotal,
			'sort_order' => $order,
			'line_key' => $lineId,
			'account_code' => trim((string) ($row['account_code'] ?? '')),
			'calculation_mode' => $this->mapCalculationMode($row['calculation_mode'] ?? 'manual'),
			'default_unit' => trim((string) ($row['unit'] ?? '')),
			'default_frequency' => (float) ($row['frequency'] ?? 1),
			'priority' => trim((string) ($row['priority'] ?? '')),
			'funding_source' => trim((string) ($row['funding_source'] ?? '')),
			'subcategory' => trim((string) ($row['subcategory'] ?? '')),
			'notes' => trim((string) ($row['notes'] ?? '')),
		];
	}

	private function appendSectionTotals(array $rows)
	{
		$grouped = [];
		foreach ($rows as $r) {
			if (!empty($r['is_total_row'])) {
				continue;
			}
			$grouped[$r['section']][] = $r;
		}
		$final = [];
		$order = 0;
		$sectionOrder = ['INCOME', 'OPERATING EXPENSES', 'ADMINISTRATIVE COSTS', 'FINANCE COSTS'];
		foreach ($sectionOrder as $section) {
			if (empty($grouped[$section])) {
				continue;
			}
			foreach ($grouped[$section] as $r) {
				$r['sort_order'] = $order++;
				$final[] = $r;
			}
			if (isset(self::$sectionTotals[$section])) {
				$final[] = [
					'section' => $section,
					'original_label' => self::$sectionTotals[$section],
					'normalized_label' => self::$sectionTotals[$section],
					'is_total_row' => true,
					'is_editable' => false,
					'sort_order' => $order++,
					'line_key' => 'TOTAL_' . preg_replace('/[^A-Z0-9_]/', '_', $section),
					'account_code' => '',
					'calculation_mode' => 'manual',
					'default_unit' => '',
					'default_frequency' => 1,
					'priority' => '',
					'funding_source' => '',
					'subcategory' => '',
					'notes' => '',
				];
			}
		}
		return $final ?: $rows;
	}

	private function findHeaderRow(array $data, array $needles)
	{
		foreach ($data as $rowNum => $row) {
			$cells = [];
			foreach ($row as $cell) {
				$cells[] = strtolower(trim(preg_replace('/[^a-z0-9_ \/]+/i', '', (string) $cell)));
			}
			$hit = 0;
			foreach ($needles as $needle) {
				foreach ($cells as $cell) {
					if ($cell !== '' && strpos($cell, strtolower($needle)) !== false) {
						$hit++;
						break;
					}
				}
			}
			if ($hit >= count($needles)) {
				return (int) $rowNum;
			}
		}
		return null;
	}

	private function normalizeHeaders(array $row)
	{
		$headers = [];
		foreach ($row as $col => $val) {
			$key = strtolower(trim(preg_replace('/\s+/', ' ', (string) $val)));
			$key = str_replace(['/', '-'], ' ', $key);
			$key = trim(preg_replace('/\s+/', ' ', $key));
			if ($key !== '') {
				$headers[$col] = $key;
			}
		}
		return $headers;
	}

	private function rowByHeaders(array $row, array $headers)
	{
		$out = [];
		foreach ($headers as $col => $key) {
			$out[$key] = $row[$col] ?? null;
		}
		return $out;
	}

	public function parseLegacySheet($sheet)
	{
		$data = $sheet->toArray(null, true, true, true);
		$section = 'GENERAL';
		$order = 0;
		$rows = [];
		foreach ($data as $row) {
			$label = trim((string) ($row['A'] ?? ''));
			if ($label === '') {
				continue;
			}
			if (preg_match('/^(INCOME|EXPENSES|OPERATING|ADMINISTRATIVE|FINANCE)/i', $label) && strlen($label) < 40) {
				$section = strtoupper($label);
			}
			$isTotal = (bool) preg_match('/^total/i', $label);
			$rows[] = [
				'section' => $section,
				'original_label' => $label,
				'normalized_label' => $this->normalizeLabel($label),
				'is_total_row' => $isTotal,
				'is_editable' => !$isTotal,
				'sort_order' => $order++,
				'line_key' => 'L' . $order,
				'account_code' => '',
				'calculation_mode' => 'manual',
				'default_unit' => '',
				'default_frequency' => 1,
				'priority' => '',
				'funding_source' => '',
				'subcategory' => '',
				'notes' => '',
			];
		}
		return $rows;
	}

	public function defaultStructure()
	{
		$rows = [];
		$order = 0;
		foreach (self::$defaultCategories as $c) {
			$rows[] = [
				'section' => $c[0],
				'original_label' => $c[1],
				'normalized_label' => $this->normalizeLabel($c[1]),
				'is_total_row' => $c[2],
				'is_editable' => !$c[2],
				'sort_order' => $order++,
				'line_key' => 'L' . $order,
				'account_code' => '',
				'calculation_mode' => 'manual',
				'default_unit' => '',
				'default_frequency' => 1,
				'priority' => '',
				'funding_source' => '',
				'subcategory' => '',
				'notes' => '',
			];
		}
		return $rows;
	}

	public function saveTemplateVersion($orgId, $name, $filePath, $originalName, $checksum, $uploadedBy, $parsedRows)
	{
		$db = \Config\Database::connect();
		$this->ensureTemplateLineColumns($db);
		$db->transStart();
		$db->table('budget_templates')->insert([
			'organization_id' => (int) $orgId,
			'name' => $name,
			'status' => 'draft',
			'created_by' => $uploadedBy,
			'created_at' => date('Y-m-d H:i:s'),
			'updated_at' => date('Y-m-d H:i:s'),
		]);
		$templateId = (int) $db->insertID();
		$db->table('budget_template_versions')->insert([
			'template_id' => $templateId,
			'version_no' => 1,
			'original_filename' => $originalName,
			'stored_filename' => basename($filePath),
			'checksum' => $checksum,
			'uploaded_by' => $uploadedBy,
			'uploaded_at' => date('Y-m-d H:i:s'),
			'status' => 'draft',
		]);
		$versionId = (int) $db->insertID();
		$sections = [];
		foreach ($parsedRows as $r) {
			$sk = $r['section'];
			if (!isset($sections[$sk])) {
				$db->table('budget_template_sections')->insert([
					'version_id' => $versionId,
					'section_key' => preg_replace('/[^A-Z0-9_]/', '_', strtoupper($sk)),
					'section_label' => $sk,
					'section_type' => stripos($sk, 'INCOME') !== false ? 'income' : 'expense',
					'sort_order' => count($sections),
					'is_total_row' => 0,
				]);
				$sections[$sk] = (int) $db->insertID();
			}
			$lineInsert = [
				'version_id' => $versionId,
				'section_id' => $sections[$sk],
				'line_key' => $r['line_key'] ?? ('L' . $r['sort_order']),
				'original_label' => $r['original_label'],
				'normalized_label' => $r['normalized_label'],
				'account_code' => $r['account_code'] ?? null,
				'is_editable' => !empty($r['is_editable']) ? 1 : 0,
				'is_total_row' => !empty($r['is_total_row']) ? 1 : 0,
				'sort_order' => $r['sort_order'],
			];
			foreach (['calculation_mode', 'default_unit', 'default_frequency', 'priority', 'funding_source'] as $col) {
				if ($this->templateLineColumnExists($db, $col)) {
					$lineInsert[$col] = $r[$col] ?? null;
				}
			}
			$db->table('budget_template_lines')->insert($lineInsert);
		}
		$db->table('budget_templates')->where('id', $templateId)->update(['current_version_id' => $versionId]);
		$db->transComplete();
		return [
			'success' => true,
			'template_id' => $templateId,
			'version_id' => $versionId,
			'line_count' => count($parsedRows),
		];
	}

	public function installOfficialTemplate($orgId, $uploadedBy = 0, $activate = true)
	{
		$path = $this->officialTemplatePath();
		if (!$path) {
			return ['success' => false, 'error' => 'Official template file not found on server.'];
		}
		$parsed = $this->parseUpload($path, self::OFFICIAL_TEMPLATE);
		if (!$parsed['success']) {
			return $parsed;
		}
		$db = \Config\Database::connect();
		$existing = $db->table('budget_templates')
			->where('organization_id', (int) $orgId)
			->where('name', self::OFFICIAL_TEMPLATE_NAME)
			->get(1)->getRowArray();
		if ($existing) {
			if ($activate) {
				$this->activateTemplateForOrg((int) $orgId, (int) $existing['id']);
			}
			return [
				'success' => true,
				'template_id' => (int) $existing['id'],
				'existing' => true,
				'line_count' => count($parsed['rows']),
			];
		}
		$checksum = md5_file($path);
		$res = $this->saveTemplateVersion(
			$orgId,
			self::OFFICIAL_TEMPLATE_NAME,
			$path,
			self::OFFICIAL_TEMPLATE,
			$checksum,
			$uploadedBy,
			$parsed['rows']
		);
		if ($activate && !empty($res['template_id'])) {
			$this->activateTemplateForOrg((int) $orgId, (int) $res['template_id']);
		}
		return $res;
	}

	public function activateTemplateForOrg($orgId, $templateId)
	{
		$db = \Config\Database::connect();
		$db->table('budget_templates')
			->where('organization_id', (int) $orgId)
			->where('id !=', (int) $templateId)
			->update(['status' => 'draft', 'updated_at' => date('Y-m-d H:i:s')]);
		$db->table('budget_templates')
			->where('id', (int) $templateId)
			->update(['status' => 'active', 'updated_at' => date('Y-m-d H:i:s')]);
	}

	private function ensureTemplateLineColumns($db)
	{
		$alters = [
			"ALTER TABLE `budget_template_lines` ADD COLUMN `calculation_mode` VARCHAR(40) NULL DEFAULT 'manual'",
			"ALTER TABLE `budget_template_lines` ADD COLUMN `default_unit` VARCHAR(40) NULL",
			"ALTER TABLE `budget_template_lines` ADD COLUMN `default_frequency` DECIMAL(10,4) NULL DEFAULT 1",
			"ALTER TABLE `budget_template_lines` ADD COLUMN `priority` VARCHAR(20) NULL",
			"ALTER TABLE `budget_template_lines` ADD COLUMN `funding_source` VARCHAR(80) NULL",
			"ALTER TABLE `budget_lines` MODIFY COLUMN `calculation_mode` ENUM('qty_unit_freq','term_sum','manual','monthly') NOT NULL DEFAULT 'manual'",
		];
		foreach ($alters as $sql) {
			try {
				$db->query($sql);
			} catch (\Throwable $e) {
				// column may already exist
			}
		}
	}

	private function templateLineColumnExists($db, $column)
	{
		static $cache = null;
		if ($cache === null) {
			$cache = [];
			try {
				$fields = $db->getFieldNames('budget_template_lines');
				foreach ($fields as $f) {
					$cache[$f] = true;
				}
			} catch (\Throwable $e) {
				$cache = [];
			}
		}
		return isset($cache[$column]);
	}
}
