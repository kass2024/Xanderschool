<?php

namespace App\Services\Budget;

use PhpOffice\PhpSpreadsheet\IOFactory;

class BudgetTemplateImportService
{
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
		['OPERATING EXPENSES', 'Utilities: Water & Electricity', false],
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
		['EXPENSES', 'Total Expenses', true],
	];

	public function normalizeLabel($label)
	{
		$label = trim(preg_replace('/\s+/', ' ', (string) $label));
		$fixes = [
			'Rehabilitation & Maintenace' => 'Rehabilitation & Maintenance',
			'Rehabilitation & Maintenace ' => 'Rehabilitation & Maintenance',
		];
		return $fixes[$label] ?? $label;
	}

	public function parseUpload($filePath, $originalName)
	{
		$rows = [];
		try {
			$spreadsheet = IOFactory::load($filePath);
			$sheet = $spreadsheet->getSheet(0);
			$data = $sheet->toArray(null, true, true, true);
			$section = 'GENERAL';
			$order = 0;
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
				];
			}
		} catch (\Throwable $e) {
			return ['success' => false, 'error' => 'Could not parse Excel: ' . $e->getMessage()];
		}
		if (empty($rows)) {
			$rows = $this->defaultStructure();
		}
		return ['success' => true, 'rows' => $rows, 'sheets' => 1];
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
			];
		}
		return $rows;
	}

	public function saveTemplateVersion($orgId, $name, $filePath, $originalName, $checksum, $uploadedBy, $parsedRows)
	{
		$db = \Config\Database::connect();
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
			$db->table('budget_template_lines')->insert([
				'version_id' => $versionId,
				'section_id' => $sections[$sk],
				'line_key' => 'L' . $r['sort_order'],
				'original_label' => $r['original_label'],
				'normalized_label' => $r['normalized_label'],
				'is_editable' => $r['is_editable'] ? 1 : 0,
				'is_total_row' => $r['is_total_row'] ? 1 : 0,
				'sort_order' => $r['sort_order'],
			]);
		}
		$db->table('budget_templates')->where('id', $templateId)->update(['current_version_id' => $versionId]);
		$db->transComplete();
		return ['success' => true, 'template_id' => $templateId, 'version_id' => $versionId];
	}
}
