<?php

namespace App\Services\Assets;

use App\Models\AssetCategoryModel;
use App\Models\AssetLocationModel;
use App\Models\AssetModel;
use App\Models\AssetOpsSchema;
use App\Models\AssetSchemaModel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Bulk import / export for assets (PhpSpreadsheet). PHP 7.4.
 */
class AssetImportService
{
	/** @var string[] */
	private static $importColumns = [
		'asset_code', 'name', 'description', 'category_code', 'location_code',
		'brand', 'model', 'serial_number', 'barcode', 'rfid_tag',
		'custodian_staff_id', 'lifecycle_status', 'purchase_price', 'purchase_date',
		'warranty_expiry', 'notes',
	];

	/** @var string[] */
	private static $lifecycleStatuses = [
		'draft', 'pending_approval', 'approved', 'available', 'assigned',
		'checked_out', 'in_use', 'under_inspection', 'under_maintenance',
		'under_repair', 'damaged', 'missing', 'stolen', 'retired',
	];

	/**
	 * @param int $schoolId
	 * @return Spreadsheet
	 */
	public function buildTemplate($schoolId)
	{
		AssetOpsSchema::ensureAll();
		(new AssetSchemaModel())->ensureSchema();

		$schoolId = (int) $schoolId;
		$catMdl = new AssetCategoryModel();
		$locMdl = new AssetLocationModel();
		$categories = $catMdl->listForSchool($schoolId);
		$locations = $locMdl->listForSchool($schoolId);

		$spreadsheet = new Spreadsheet();

		$instr = $spreadsheet->getActiveSheet();
		$instr->setTitle('Instructions');
		$instr->fromArray([
			['Asset Import Template'],
			[''],
			['Required columns: name, category_code, location_code'],
			['Optional: asset_code (auto-generated if blank), description, brand, model, serial_number, barcode, rfid_tag'],
			['custodian_staff_id must match an active staff ID for this school'],
			['Modes: create_only (skip existing codes), create_update (update matching asset_code), validate_only (no commit)'],
			['Use Reference sheet for valid category codes, location codes, and lifecycle statuses'],
		], null, 'A1');

		$assets = $spreadsheet->createSheet();
		$assets->setTitle('Assets');
		$headers = array_map(function ($col) {
			return strtoupper(str_replace('_', ' ', $col));
		}, self::$importColumns);
		$assets->fromArray([$headers], null, 'A1');
		$this->styleHeader($assets, 'A1:' . $this->colLetter(count($headers)) . '1');
		$assets->freezePane('A2');
		foreach (range(1, count($headers)) as $i) {
			$assets->getColumnDimension($this->colLetter($i))->setAutoSize(true);
		}

		$ref = $spreadsheet->createSheet();
		$ref->setTitle('Reference');
		$ref->fromArray([
			['Category Code', 'Category Name'],
		], null, 'A1');
		$row = 2;
		foreach ($categories as $c) {
			$ref->setCellValue('A' . $row, $c['category_code']);
			$ref->setCellValue('B' . $row, $c['name']);
			$row++;
		}
		$catEnd = max(2, $row - 1);

		$locStart = $row + 2;
		$ref->setCellValue('A' . $locStart, 'Location Code');
		$ref->setCellValue('B' . $locStart, 'Location Name');
		$locRow = $locStart + 1;
		foreach ($locations as $l) {
			$ref->setCellValue('A' . $locRow, $l['location_code']);
			$ref->setCellValue('B' . $locRow, $l['name']);
			$locRow++;
		}
		$locEnd = max($locStart + 1, $locRow - 1);

		$statStart = $locRow + 2;
		$ref->setCellValue('A' . $statStart, 'Lifecycle Status');
		$statRow = $statStart + 1;
		foreach (self::$lifecycleStatuses as $st) {
			$ref->setCellValue('A' . $statRow, $st);
			$statRow++;
		}
		$statEnd = max($statStart + 1, $statRow - 1);

		$this->styleHeader($ref, 'A1:B1');
		$this->styleHeader($ref, 'A' . $locStart . ':B' . $locStart);
		$this->styleHeader($ref, 'A' . $statStart . ':A' . $statStart);

		if ($catEnd >= 2) {
			$formula = sprintf("='Reference'!\$A\$2:\$A\$%d", $catEnd);
			for ($r = 2; $r <= 500; $r++) {
				$this->setListValidation($assets, 'D' . $r, $formula);
			}
		}
		if ($locEnd >= $locStart + 1) {
			$formula = sprintf("='Reference'!\$A\$%d:\$A\$%d", $locStart + 1, $locEnd);
			for ($r = 2; $r <= 500; $r++) {
				$this->setListValidation($assets, 'E' . $r, $formula);
			}
		}
		if ($statEnd >= $statStart + 1) {
			$formula = sprintf("='Reference'!\$A\$%d:\$A\$%d", $statStart + 1, $statEnd);
			for ($r = 2; $r <= 500; $r++) {
				$this->setListValidation($assets, 'L' . $r, $formula);
			}
		}

		$spreadsheet->setActiveSheetIndex(1);
		return $spreadsheet;
	}

	/**
	 * @param int $schoolId
	 * @param string $filePath
	 * @param string $mode create_only|create_update|validate_only
	 * @return array
	 */
	public function parseAndValidate($schoolId, $filePath, $mode)
	{
		AssetOpsSchema::ensureAll();
		(new AssetSchemaModel())->ensureSchema();

		$schoolId = (int) $schoolId;
		$mode = in_array($mode, ['create_only', 'create_update', 'validate_only'], true)
			? $mode : 'create_only';

		$db = \Config\Database::connect();
		$now = date('Y-m-d H:i:s');
		$filename = basename($filePath);

		$db->table('asset_imports')->insert([
			'school_id' => $schoolId,
			'filename' => $filename,
			'mode' => $mode,
			'status' => 'preview',
			'created_at' => $now,
			'updated_at' => $now,
		]);
		$importId = (int) $db->insertID();

		$categories = $this->indexByCode($db->table('asset_categories')
			->where('school_id', $schoolId)->where('status', 1)->get()->getResultArray(), 'category_code');
		$locations = $this->indexByCode($db->table('asset_locations')
			->where('school_id', $schoolId)->where('status', 1)->get()->getResultArray(), 'location_code');
		$existingCodes = $this->loadExistingAssetCodes($schoolId);

		$spreadsheet = IOFactory::load($filePath);
		$sheet = $spreadsheet->getSheetByName('Assets');
		if ($sheet === null) {
			$sheet = $spreadsheet->getActiveSheet();
		}

		$headerMap = $this->parseHeaderRow($sheet);
		$highest = (int) $sheet->getHighestRow();

		$valid = 0;
		$warnings = 0;
		$errors = 0;
		$rows = [];
		$seenCodes = [];

		for ($r = 2; $r <= $highest; $r++) {
			$payload = $this->readRow($sheet, $r, $headerMap);
			if ($this->rowIsEmpty($payload)) {
				continue;
			}

			$rowErrors = [];
			$rowWarnings = [];

			$name = trim((string) ($payload['name'] ?? ''));
			if ($name === '') {
				$rowErrors[] = 'name is required';
			}

			$catCode = strtoupper(trim((string) ($payload['category_code'] ?? '')));
			if ($catCode === '') {
				$rowErrors[] = 'category_code is required';
			} elseif (!isset($categories[$catCode])) {
				$rowErrors[] = 'Unknown category_code: ' . $catCode;
			}

			$locCode = strtoupper(trim((string) ($payload['location_code'] ?? '')));
			if ($locCode === '') {
				$rowErrors[] = 'location_code is required';
			} elseif (!isset($locations[$locCode])) {
				$rowErrors[] = 'Unknown location_code: ' . $locCode;
			}

			$assetCode = strtoupper(trim((string) ($payload['asset_code'] ?? '')));
			if ($assetCode !== '') {
				if (isset($seenCodes[$assetCode])) {
					$rowErrors[] = 'Duplicate asset_code in file: ' . $assetCode;
				}
				$seenCodes[$assetCode] = true;
				if ($mode === 'create_only' && isset($existingCodes[$assetCode])) {
					$rowErrors[] = 'asset_code already exists: ' . $assetCode;
				}
			}

			$custodianId = trim((string) ($payload['custodian_staff_id'] ?? ''));
			if ($custodianId !== '') {
				$staff = $db->table('staffs')
					->where('school_id', $schoolId)
					->where('id', (int) $custodianId)
					->where('status', 1)
					->get(1)->getRowArray();
				if (!$staff) {
					$rowErrors[] = 'Invalid custodian_staff_id: ' . $custodianId;
				}
			}

			$status = strtolower(trim((string) ($payload['lifecycle_status'] ?? 'draft')));
			if ($status !== '' && !in_array($status, self::$lifecycleStatuses, true)) {
				$rowWarnings[] = 'Unknown lifecycle_status; will default to draft';
				$payload['lifecycle_status'] = 'draft';
			} elseif ($status === '') {
				$payload['lifecycle_status'] = 'draft';
			}

			if (!empty($payload['serial_number'])) {
				$serial = trim((string) $payload['serial_number']);
				$dup = $db->table('assets')
					->where('school_id', $schoolId)
					->where('serial_number', $serial)
					->where('archived_at', null);
				if ($assetCode !== '') {
					$dup->where('asset_code !=', $assetCode);
				}
				if ($dup->countAllResults() > 0) {
					$rowWarnings[] = 'Serial number may duplicate an existing asset';
				}
			}

			$rowStatus = 'valid';
			if (!empty($rowErrors)) {
				$rowStatus = 'error';
				$errors++;
			} elseif (!empty($rowWarnings)) {
				$rowStatus = 'warning';
				$warnings++;
			} else {
				$valid++;
			}

			$db->table('asset_import_rows')->insert([
				'import_id' => $importId,
				'school_id' => $schoolId,
				'row_number' => $r,
				'status' => $rowStatus,
				'asset_code' => $assetCode !== '' ? $assetCode : null,
				'payload_json' => json_encode($payload),
				'errors_json' => json_encode(array_merge($rowErrors, array_map(function ($w) {
					return '[warning] ' . $w;
				}, $rowWarnings))),
				'created_at' => $now,
			]);

			$rows[] = [
				'row_number' => $r,
				'status' => $rowStatus,
				'asset_code' => $assetCode,
				'errors' => $rowErrors,
				'warnings' => $rowWarnings,
			];
		}

		$total = count($rows);
		$summary = [
			'total_rows' => $total,
			'valid_rows' => $valid,
			'warning_rows' => $warnings,
			'error_rows' => $errors,
			'mode' => $mode,
		];

		$db->table('asset_imports')->where('id', $importId)->update([
			'total_rows' => $total,
			'valid_rows' => $valid,
			'warning_rows' => $warnings,
			'error_rows' => $errors,
			'summary_json' => json_encode($summary),
			'updated_at' => $now,
		]);

		return [
			'import_id' => $importId,
			'rows' => $rows,
			'summary' => $summary,
		];
	}

	/**
	 * @param int $schoolId
	 * @param int $importId
	 * @param int $actorId
	 * @return array
	 */
	public function commitImport($schoolId, $importId, $actorId)
	{
		AssetOpsSchema::ensureAll();
		(new AssetSchemaModel())->ensureSchema();

		$schoolId = (int) $schoolId;
		$importId = (int) $importId;
		$actorId = (int) $actorId;
		$db = \Config\Database::connect();
		$now = date('Y-m-d H:i:s');

		$import = $db->table('asset_imports')
			->where('school_id', $schoolId)
			->where('id', $importId)
			->get(1)->getRowArray();

		if (!$import) {
			return ['success' => false, 'error' => 'Import not found'];
		}
		if ($import['mode'] === 'validate_only') {
			return ['success' => false, 'error' => 'Import was validate_only; nothing to commit'];
		}
		if ($import['status'] === 'committed') {
			return ['success' => false, 'error' => 'Import already committed'];
		}

		$categories = $this->indexByCode($db->table('asset_categories')
			->where('school_id', $schoolId)->get()->getResultArray(), 'category_code');
		$locations = $this->indexByCode($db->table('asset_locations')
			->where('school_id', $schoolId)->get()->getResultArray(), 'location_code');
		$schema = new AssetSchemaModel();
		$assetMdl = new AssetModel();
		$mode = $import['mode'];

		$importRows = $db->table('asset_import_rows')
			->where('import_id', $importId)
			->whereIn('status', ['valid', 'warning'])
			->orderBy('row_number', 'ASC')
			->get()->getResultArray();

		$created = 0;
		$updated = 0;
		$skipped = 0;
		$failed = 0;

		$db->transStart();

		foreach ($importRows as $row) {
			$payload = json_decode($row['payload_json'], true);
			if (!is_array($payload)) {
				$failed++;
				continue;
			}

			$catCode = strtoupper(trim((string) ($payload['category_code'] ?? '')));
			$locCode = strtoupper(trim((string) ($payload['location_code'] ?? '')));
			if (!isset($categories[$catCode]) || !isset($locations[$locCode])) {
				$failed++;
				continue;
			}

			$assetCode = strtoupper(trim((string) ($payload['asset_code'] ?? '')));
			$existing = null;
			if ($assetCode !== '') {
				$existing = $assetMdl->where('school_id', $schoolId)
					->where('asset_code', $assetCode)
					->first();
			}

			if ($existing && $mode === 'create_only') {
				$skipped++;
				continue;
			}

			$purchasePrice = $this->parseDecimal($payload['purchase_price'] ?? 0);
			$data = [
				'school_id' => $schoolId,
				'name' => trim((string) $payload['name']),
				'description' => trim((string) ($payload['description'] ?? '')),
				'category_id' => (int) $categories[$catCode]['id'],
				'location_id' => (int) $locations[$locCode]['id'],
				'brand' => trim((string) ($payload['brand'] ?? '')),
				'model' => trim((string) ($payload['model'] ?? '')),
				'serial_number' => trim((string) ($payload['serial_number'] ?? '')),
				'barcode' => trim((string) ($payload['barcode'] ?? '')),
				'rfid_tag' => strtoupper(trim((string) ($payload['rfid_tag'] ?? ''))),
				'custodian_staff_id' => !empty($payload['custodian_staff_id']) ? (int) $payload['custodian_staff_id'] : null,
				'lifecycle_status' => trim((string) ($payload['lifecycle_status'] ?? 'draft')),
				'purchase_price' => $purchasePrice,
				'total_acquisition_cost' => $purchasePrice,
				'net_book_value' => $purchasePrice,
				'purchase_date' => $this->parseDate($payload['purchase_date'] ?? null),
				'warranty_expiry' => $this->parseDate($payload['warranty_expiry'] ?? null),
				'notes' => trim((string) ($payload['notes'] ?? '')),
				'updated_by' => $actorId,
				'updated_at' => $now,
			];

			if ($existing && $mode === 'create_update') {
				$data['version'] = ((int) ($existing['version'] ?? 1)) + 1;
				$assetMdl->update($existing['id'], $data);
				$db->table('asset_import_rows')->where('id', $row['id'])->update(['status' => 'imported']);
				$updated++;
				continue;
			}

			if ($assetCode === '') {
				$assetCode = $schema->nextAssetCode($schoolId, $catCode);
			}
			$data['asset_code'] = $assetCode;
			$data['created_by'] = $actorId;
			$data['created_at'] = $now;

			$assetMdl->insert($data);
			$db->table('asset_import_rows')->where('id', $row['id'])->update([
				'status' => 'imported',
				'asset_code' => $assetCode,
			]);
			$created++;
		}

		$db->table('asset_imports')->where('id', $importId)->update([
			'status' => 'committed',
			'updated_at' => $now,
			'summary_json' => json_encode([
				'created' => $created,
				'updated' => $updated,
				'skipped' => $skipped,
				'failed' => $failed,
			]),
		]);

		$db->transComplete();

		if ($db->transStatus() === false) {
			$db->table('asset_imports')->where('id', $importId)->update([
				'status' => 'failed',
				'updated_at' => $now,
			]);
			return ['success' => false, 'error' => 'Transaction failed'];
		}

		return [
			'success' => true,
			'created' => $created,
			'updated' => $updated,
			'skipped' => $skipped,
			'failed' => $failed,
		];
	}

	/**
	 * @param int $schoolId
	 * @param array $filters
	 * @return Spreadsheet
	 */
	public function exportAssets($schoolId, array $filters = [])
	{
		AssetOpsSchema::ensureAll();
		$assetMdl = new AssetModel();
		$rows = $assetMdl->listDetailed((int) $schoolId, $filters);

		$spreadsheet = new Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
		$sheet->setTitle('Assets');
		$headers = [
			'Asset Code', 'Name', 'Description', 'Category Code', 'Category Name',
			'Location Code', 'Location Name', 'Brand', 'Model', 'Serial Number',
			'Barcode', 'RFID Tag', 'Custodian', 'Status', 'Purchase Price',
			'Net Book Value', 'Purchase Date', 'Warranty Expiry', 'Notes',
		];
		$sheet->fromArray([$headers], null, 'A1');
		$this->styleHeader($sheet, 'A1:S1');

		$r = 2;
		foreach ($rows as $a) {
			$sheet->fromArray([[
				$a['asset_code'] ?? '',
				$a['name'] ?? '',
				$a['description'] ?? '',
				$a['category_code'] ?? '',
				$a['category_name'] ?? '',
				$a['location_code'] ?? '',
				$a['location_name'] ?? '',
				$a['brand'] ?? '',
				$a['model'] ?? '',
				$a['serial_number'] ?? '',
				$a['barcode'] ?? '',
				$a['rfid_tag'] ?? '',
				$a['custodian_name'] ?? '',
				$a['lifecycle_status'] ?? '',
				$a['purchase_price'] ?? '',
				$a['net_book_value'] ?? '',
				$a['purchase_date'] ?? '',
				$a['warranty_expiry'] ?? '',
				$a['notes'] ?? '',
			]], null, 'A' . $r);
			$r++;
		}

		$sheet->freezePane('A2');
		return $spreadsheet;
	}

	/**
	 * @param array $rows
	 * @param string $keyField
	 * @return array<string, array>
	 */
	private function indexByCode(array $rows, $keyField)
	{
		$map = [];
		foreach ($rows as $row) {
			$code = strtoupper(trim((string) ($row[$keyField] ?? '')));
			if ($code !== '') {
				$map[$code] = $row;
			}
		}
		return $map;
	}

	/**
	 * @param int $schoolId
	 * @return array<string, bool>
	 */
	private function loadExistingAssetCodes($schoolId)
	{
		$db = \Config\Database::connect();
		$codes = [];
		$rows = $db->table('assets')
			->select('asset_code')
			->where('school_id', (int) $schoolId)
			->get()->getResultArray();
		foreach ($rows as $row) {
			$codes[strtoupper($row['asset_code'])] = true;
		}
		return $codes;
	}

	/**
	 * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
	 * @return array<string, int>
	 */
	private function parseHeaderRow($sheet)
	{
		$map = [];
		$colMax = $sheet->getHighestColumn();
		$colIndex = 1;
		$maxCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($colMax);
		for ($c = 1; $c <= $maxCol; $c++) {
			$letter = $this->colLetter($c);
			$val = strtolower(trim((string) $sheet->getCell($letter . '1')->getValue()));
			$val = str_replace(' ', '_', $val);
			foreach (self::$importColumns as $expected) {
				if ($val === $expected || $val === str_replace('_', '', $expected)) {
					$map[$expected] = $c;
				}
			}
			$colIndex++;
		}
		return $map;
	}

	/**
	 * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
	 * @param int $row
	 * @param array<string, int> $headerMap
	 * @return array<string, mixed>
	 */
	private function readRow($sheet, $row, array $headerMap)
	{
		$payload = [];
		foreach (self::$importColumns as $col) {
			if (!isset($headerMap[$col])) {
				$payload[$col] = '';
				continue;
			}
			$letter = $this->colLetter($headerMap[$col]);
			$payload[$col] = $sheet->getCell($letter . $row)->getFormattedValue();
		}
		return $payload;
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return bool
	 */
	private function rowIsEmpty(array $payload)
	{
		foreach ($payload as $v) {
			if (trim((string) $v) !== '') {
				return false;
			}
		}
		return true;
	}

	/**
	 * @param mixed $val
	 * @return float
	 */
	private function parseDecimal($val)
	{
		if ($val === null || $val === '') {
			return 0.0;
		}
		return (float) preg_replace('/[^0-9.\-]/', '', (string) $val);
	}

	/**
	 * @param mixed $val
	 * @return string|null
	 */
	private function parseDate($val)
	{
		$val = trim((string) $val);
		if ($val === '') {
			return null;
		}
		if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
			return $val;
		}
		$ts = strtotime($val);
		if ($ts === false) {
			return null;
		}
		return date('Y-m-d', $ts);
	}

	/**
	 * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
	 * @param string $range
	 */
	private function styleHeader($sheet, $range)
	{
		$sheet->getStyle($range)->applyFromArray([
			'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
			'fill' => [
				'fillType' => Fill::FILL_SOLID,
				'startColor' => ['rgb' => '012F6B'],
			],
			'alignment' => [
				'horizontal' => Alignment::HORIZONTAL_CENTER,
				'vertical' => Alignment::VERTICAL_CENTER,
				'wrapText' => true,
			],
		]);
	}

	/**
	 * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
	 * @param string $cell
	 * @param string $formula
	 */
	private function setListValidation($sheet, $cell, $formula)
	{
		$validation = new DataValidation();
		$validation->setType(DataValidation::TYPE_LIST);
		$validation->setErrorStyle(DataValidation::STYLE_STOP);
		$validation->setAllowBlank(true);
		$validation->setShowDropDown(true);
		$validation->setFormula1($formula);
		$sheet->getCell($cell)->setDataValidation($validation);
	}

	/**
	 * @param int $index 1-based
	 * @return string
	 */
	private function colLetter($index)
	{
		return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index);
	}
}
