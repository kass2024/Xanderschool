<?php

namespace App\Controllers;

use App\Controllers\Traits\AssetManagementOpsTrait;
use App\Models\AssetCategoryFieldModel;
use App\Models\AssetCategoryModel;
use App\Models\AssetLocationModel;
use App\Models\AssetModel;
use App\Models\AssetOpsSchema;
use App\Models\AssetSchemaModel;
use App\Models\AssetStatusHistoryModel;
use App\Models\StaffModel;
use App\Services\Assets\AssetAiAssistService;

/**
 * Asset Management — Phases 1–6.
 * Extends Home to reuse _preset() / school shell data.
 */
class AssetManagement extends Home
{
	use AssetManagementOpsTrait;

	/** Allowed lifecycle statuses for create/update in Phase 1. */
	private static $lifecycleStatuses = [
		'draft', 'pending_approval', 'approved', 'available', 'assigned',
		'checked_out', 'in_use', 'under_inspection', 'under_maintenance',
		'under_repair', 'damaged', 'missing', 'stolen', 'retired',
		'pending_disposal', 'disposed', 'sold', 'donated', 'written_off', 'archived',
	];

	protected function bootAssets()
	{
		$this->_preset();
		$schoolId = (int) $this->session->get('soma_school_id');
		$schema = new AssetSchemaModel();
		try {
			$schema->ensureSchema();
			AssetOpsSchema::ensureAll();
			$schema->seedDefaults($schoolId, (int) $this->session->get('soma_id'));
		} catch (\Throwable $e) {
			log_message('error', 'Asset schema bootstrap: ' . $e->getMessage());
		}
		return $schoolId;
	}

	public function register()
	{
		return $this->dashboard();
	}

	protected function denyUnless($menuKey)
	{
		if (!function_exists('menu_clearance_allowed') || !menu_clearance_allowed($menuKey)) {
			$this->session->setFlashdata('error', 'You do not have permission for this Asset Management page.');
			header('Location: ' . base_url('dashboard'));
			exit;
		}
	}

	public function dashboard()
	{
		$schoolId = $this->bootAssets();
		$this->denyUnlessAsset();
		$data = $this->data;
		$assetMdl = new AssetModel();
		$locMdl = new AssetLocationModel();
		$catMdl = new AssetCategoryModel();

		try {
			$assets = $assetMdl->listDetailed($schoolId);
		} catch (\Throwable $e) {
			$assets = [];
		}
		$grouped = [];
		foreach ($assets as $a) {
			$type = trim((string) ($a['category_name'] ?? '')) ?: 'Other';
			if (!isset($grouped[$type])) {
				$grouped[$type] = ['type' => $type, 'qty' => 0, 'damaged' => 0, 'rows' => []];
			}
			$qty = (float) ($a['quantity'] ?? 1);
			$dmg = (float) ($a['qty_damaged'] ?? 0);
			$grouped[$type]['qty'] += $qty;
			$grouped[$type]['damaged'] += $dmg;
			$grouped[$type]['rows'][] = $a;
		}

		$moves = [];
		try {
			$db = \Config\Database::connect();
			if ($db->tableExists('asset_distributions')) {
				$moves = $db->table('asset_distributions d')
					->select('d.*, a.name AS asset_name, lf.name AS from_name, lt.name AS to_name')
					->join('assets a', 'a.id = d.asset_id', 'left')
					->join('asset_locations lf', 'lf.id = d.from_location_id', 'left')
					->join('asset_locations lt', 'lt.id = d.to_location_id', 'left')
					->where('d.school_id', $schoolId)
					->orderBy('d.id', 'DESC')
					->limit(12)
					->get()
					->getResultArray();
			}
		} catch (\Throwable $e) {
			$moves = [];
		}

		$data['title'] = 'Fixed assets';
		$data['subtitle'] = 'Asset register';
		$data['page'] = 'asset_register';
		try {
			$data['totals'] = $assetMdl->simpleTotals($schoolId);
		} catch (\Throwable $e) {
			$data['totals'] = ['lots' => count($assets), 'qty' => 0, 'damaged' => 0, 'good' => 0];
		}
		$data['assets'] = $assets;
		$data['grouped'] = $grouped;
		$data['locations'] = $locMdl->listForSchool($schoolId);
		$data['categories'] = $catMdl->listForSchool($schoolId);
		$data['moves'] = $moves;
		$data['type_suggestions'] = $this->simpleAssetTypes($schoolId);
		$data['content'] = view('pages/assets/simple_register', $data);
		return view('main', $data);
	}

	protected function denyUnlessAsset()
	{
		if (function_exists('menu_clearance_group_visible') && menu_clearance_group_visible('asset_management')) {
			return;
		}
		if (function_exists('menu_clearance_allowed') && menu_clearance_allowed('asset_management')) {
			return;
		}
		$postId = (int) $this->session->get('soma_post');
		if (in_array($postId, [1, 3, 7, 12, 13, 18], true)) {
			return;
		}
		$keys = [
			'asset_dashboard', 'asset_assets', 'asset_locations', 'asset_categories',
			'asset_checkout', 'asset_transfers', 'asset_reports', 'asset_settings',
			'asset_assignments', 'asset_maintenance', 'asset_inspections', 'asset_incidents',
			'asset_audits', 'book_management',
		];
		foreach ($keys as $key) {
			if (function_exists('menu_clearance_allowed') && menu_clearance_allowed($key)) {
				return;
			}
		}
		$this->denyUnless('asset_dashboard');
	}

	/**
	 * @return list<string>
	 */
	private function simpleAssetTypes(int $schoolId): array
	{
		$types = [
			'Furniture', 'Electronic Equipment', 'Kitchen material',
			'Cleaning material', 'School material', 'House',
		];
		try {
			foreach ((new AssetCategoryModel())->listForSchool($schoolId) as $cat) {
				$name = trim((string) ($cat['name'] ?? ''));
				if ($name === '') {
					continue;
				}
				$known = false;
				foreach ($types as $type) {
					if (strcasecmp($type, $name) === 0) {
						$known = true;
						break;
					}
				}
				if (!$known) {
					$types[] = $name;
				}
			}
		} catch (\Throwable $e) {
		}
		return $types;
	}

	public function download_simple_template()
	{
		$schoolId = $this->bootAssets();
		$this->denyUnlessAsset();
		$types = $this->simpleAssetTypes($schoolId);
		$locations = (new AssetLocationModel())->listForSchool($schoolId);

		$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
		$sheet->setTitle('Assets');
		$sheet->fromArray([[
			'Asset type', 'Description', 'Quantity', 'Damaged', 'Location', 'Age',
		]], null, 'A1');
		$sheet->getStyle('A1:F1')->applyFromArray([
			'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
			'fill' => [
				'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
				'startColor' => ['rgb' => '0B1F4A'],
			],
		]);
		$sheet->freezePane('A2');
		foreach (['A' => 28, 'B' => 36, 'C' => 14, 'D' => 14, 'E' => 28, 'F' => 12] as $col => $width) {
			$sheet->getColumnDimension($col)->setWidth($width);
		}
		$sheet->setCellValue('H1', 'Asset type: open the dropdown and choose one. Location: choose a saved place, or type a new name. A new location is saved and appears in the list next time. Age is Old or New.');
		$sheet->getColumnDimension('H')->setWidth(42);
		$sheet->getStyle('H1')->getAlignment()->setWrapText(true);

		$lists = $spreadsheet->createSheet();
		$lists->setTitle('Lists');
		$lists->setCellValue('A1', 'Asset type');
		$row = 2;
		foreach ($types as $type) {
			$lists->setCellValue('A' . $row, $type);
			$row++;
		}
		$typeEnd = max(2, $row - 1);
		$lists->setCellValue('B1', 'Location');
		$locRow = 2;
		foreach ($locations as $loc) {
			$name = trim((string) ($loc['name'] ?? ''));
			if ($name === '') {
				continue;
			}
			$lists->setCellValue('B' . $locRow, $name);
			$locRow++;
		}
		$locEnd = $locRow - 1;
		$lists->setCellValue('C1', 'Age');
		$lists->setCellValue('C2', 'Old');
		$lists->setCellValue('C3', 'New');
		$lists->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);

		$typeFormula = "='Lists'!\$A\$2:\$A\${$typeEnd}";
		$ageFormula = "='Lists'!\$C\$2:\$C\$3";
		$locFormula = $locEnd >= 2 ? "='Lists'!\$B\$2:\$B\${$locEnd}" : '';
		for ($r = 2; $r <= 500; $r++) {
			$this->applyExcelList($sheet, 'A' . $r, $typeFormula, true, 'Asset type', 'Choose an asset type from the list.');
			$this->applyExcelList($sheet, 'F' . $r, $ageFormula, true, 'Age', 'Choose Old or New.');
			if ($locFormula !== '') {
				$this->applyExcelList($sheet, 'E' . $r, $locFormula, false, 'Location', 'Choose a saved location, or type a new one. New locations are saved for next time.');
			}
		}
		$spreadsheet->setActiveSheetIndex(0);

		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment;filename="Fixed_asset_register.xlsx"');
		header('Cache-Control: max-age=0');
		$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
		$writer->save('php://output');
		exit;
	}

	public function upload_simple_excel()
	{
		$schoolId = $this->bootAssets();
		$this->denyUnlessAsset();
		$actorId = (int) $this->session->get('soma_id');
		$file = $this->request->getFile('excel');
		if (!$file || !$file->isValid()) {
			return $this->response->setJSON(['error' => 'Choose an Excel file to upload.']);
		}
		$ext = strtolower((string) $file->getExtension());
		if (!in_array($ext, ['xlsx', 'xls'], true)) {
			return $this->response->setJSON(['error' => 'Upload an .xlsx or .xls file.']);
		}
		try {
			$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getTempName());
		} catch (\Throwable $e) {
			return $this->response->setJSON(['error' => 'Could not read that Excel file.']);
		}
		$sheet = $spreadsheet->getSheetByName('Assets') ?: $spreadsheet->getActiveSheet();
		$highest = (int) $sheet->getHighestRow();
		$headerRow = 0;
		$map = [];
		for ($r = 1; $r <= min(5, $highest); $r++) {
			$found = [];
			for ($c = 1; $c <= 8; $c++) {
				$label = strtolower(trim((string) $sheet->getCellByColumnAndRow($c, $r)->getValue()));
				if ($label === 'asset type' || $label === 'type') {
					$found['type'] = $c;
				} elseif ($label === 'description' || $label === 'name' || $label === 'item') {
					$found['name'] = $c;
				} elseif ($label === 'quantity' || $label === 'qty') {
					$found['qty'] = $c;
				} elseif ($label === 'damaged' || $label === 'damaged (ibyangiye)') {
					$found['damaged'] = $c;
				} elseif ($label === 'location') {
					$found['location'] = $c;
				} elseif ($label === 'age') {
					$found['age'] = $c;
				}
			}
			if (isset($found['name'], $found['type'], $found['location'])) {
				$headerRow = $r;
				$map = $found;
				break;
			}
		}
		if ($headerRow < 1) {
			return $this->response->setJSON(['error' => 'The file needs columns: Asset type, Description, Quantity, Damaged, Location, Age. Download the template and fill that sheet.']);
		}

		$allowed = [];
		foreach ($this->simpleAssetTypes($schoolId) as $type) {
			$allowed[strtolower($type)] = $type;
		}
		$saved = 0;
		$newLocations = [];
		$errors = [];
		$locMdl = new AssetLocationModel();
		$knownLocations = [];
		foreach ($locMdl->listForSchool($schoolId) as $loc) {
			$knownLocations[strtolower(trim((string) ($loc['name'] ?? '')))] = true;
		}

		for ($r = $headerRow + 1; $r <= $highest; $r++) {
			$cell = function ($key) use ($sheet, $map, $r) {
				if (!isset($map[$key])) {
					return '';
				}
				return trim((string) $sheet->getCellByColumnAndRow($map[$key], $r)->getFormattedValue());
			};
			$name = $cell('name');
			$typeRaw = $cell('type');
			$location = $cell('location');
			if ($name === '' && $typeRaw === '' && $location === '') {
				continue;
			}
			$typeKey = strtolower($typeRaw);
			if ($typeRaw === '' || !isset($allowed[$typeKey])) {
				$errors[] = 'Row ' . $r . ': choose an asset type from the dropdown.';
				continue;
			}
			if ($name === '') {
				$errors[] = 'Row ' . $r . ': description is required.';
				continue;
			}
			if ($location === '') {
				$errors[] = 'Row ' . $r . ': location is required.';
				continue;
			}
			$qty = (float) str_replace(',', '', $cell('qty'));
			if ($qty <= 0) {
				$qty = 1;
			}
			$damaged = (float) str_replace(',', '', $cell('damaged'));
			$age = strtolower($cell('age'));
			$result = $this->recordSimpleAsset($schoolId, $actorId, [
				'id' => 0,
				'name' => $name,
				'asset_type' => $allowed[$typeKey],
				'location_name' => $location,
				'location_id' => 0,
				'quantity' => $qty,
				'qty_damaged' => $damaged,
				'age_flag' => $age === 'new' ? 'new' : 'old',
			]);
			if (!empty($result['error'])) {
				$errors[] = 'Row ' . $r . ': ' . $result['error'];
				continue;
			}
			$saved++;
			$locKey = strtolower($location);
			if (!isset($knownLocations[$locKey])) {
				$knownLocations[$locKey] = true;
				$newLocations[] = $location;
			}
		}
		if ($saved < 1) {
			return $this->response->setJSON([
				'error' => $errors ? implode(' ', array_slice($errors, 0, 4)) : 'No asset rows were found.',
			]);
		}
		$message = $saved . ' asset row' . ($saved === 1 ? '' : 's') . ' saved.';
		if ($newLocations) {
			$message .= ' New locations saved: ' . implode(', ', $newLocations) . '. They will be in the location list next time.';
		}
		if ($errors) {
			$message .= ' ' . count($errors) . ' row' . (count($errors) === 1 ? '' : 's') . ' skipped.';
		}
		return $this->response->setJSON(['success' => $message, 'saved' => $saved, 'errors' => $errors]);
	}

	private function applyExcelList($sheet, string $cell, string $formula, bool $strict, string $title, string $prompt): void
	{
		$validation = new \PhpOffice\PhpSpreadsheet\Cell\DataValidation();
		$validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
		$validation->setErrorStyle($strict
			? \PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP
			: \PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_WARNING);
		$validation->setAllowBlank(true);
		$validation->setShowInputMessage(true);
		$validation->setShowErrorMessage(true);
		$validation->setShowDropDown(true);
		$validation->setErrorTitle($title);
		$validation->setError($strict ? 'Choose a value from the list.' : 'This value is not in the list yet. Continue if you want to save it as a new location.');
		$validation->setPromptTitle($title);
		$validation->setPrompt($prompt);
		$validation->setFormula1($formula);
		$sheet->getCell($cell)->setDataValidation($validation);
	}

	public function save_simple_asset()
	{
		$schoolId = $this->bootAssets();
		$this->denyUnlessAsset();
		$actorId = (int) $this->session->get('soma_id');
		$result = $this->recordSimpleAsset($schoolId, $actorId, [
			'id' => (int) $this->request->getPost('id'),
			'name' => (string) $this->request->getPost('name'),
			'asset_type' => (string) $this->request->getPost('asset_type'),
			'location_name' => (string) $this->request->getPost('location_name'),
			'location_id' => (int) $this->request->getPost('location_id'),
			'quantity' => $this->request->getPost('quantity'),
			'qty_damaged' => $this->request->getPost('qty_damaged'),
			'age_flag' => (string) $this->request->getPost('age_flag'),
		]);
		return $this->response->setJSON($result);
	}

	/**
	 * @param array<string,mixed> $input
	 * @return array{success?:string,error?:string}
	 */
	private function recordSimpleAsset(int $schoolId, int $actorId, array $input): array
	{
		$assetMdl = new AssetModel();
		$schema = new AssetSchemaModel();
		$catMdl = new AssetCategoryModel();
		$locMdl = new AssetLocationModel();
		$histMdl = new AssetStatusHistoryModel();

		$id = (int) ($input['id'] ?? 0);
		$name = trim((string) ($input['name'] ?? ''));
		$type = trim((string) ($input['asset_type'] ?? ''));
		$locationName = trim((string) ($input['location_name'] ?? ''));
		$locationId = (int) ($input['location_id'] ?? 0);
		$qty = (float) ($input['quantity'] ?? 0);
		$damaged = (float) ($input['qty_damaged'] ?? 0);
		$age = strtolower(trim((string) ($input['age_flag'] ?? '')));
		if ($age !== 'new') {
			$age = 'old';
		}
		if ($name === '') {
			return ['error' => 'Description is required.'];
		}
		if ($qty <= 0) {
			return ['error' => 'Quantity must be greater than 0.'];
		}
		if ($damaged < 0) {
			$damaged = 0;
		}
		if ($damaged > $qty) {
			return ['error' => 'Damaged quantity cannot exceed total quantity.'];
		}
		if ($type === '') {
			$type = 'General';
		}
		if ($locationId <= 0 && $locationName === '') {
			return ['error' => 'Location is required.'];
		}

		$cat = $catMdl->findOrCreateByName($schoolId, $type, $actorId);
		if ($locationId > 0) {
			$loc = $locMdl->where('school_id', $schoolId)->where('id', $locationId)->first();
		} else {
			$loc = null;
			foreach ($locMdl->listForSchool($schoolId) as $existing) {
				if (strcasecmp(trim((string) ($existing['name'] ?? '')), $locationName) === 0) {
					$loc = $existing;
					break;
				}
			}
			if (!$loc) {
				$loc = $locMdl->findOrCreateByName($schoolId, $locationName, $actorId);
			}
		}
		if (!$cat || !$loc) {
			return ['error' => 'Could not save type or location.'];
		}

		$good = max(0, $qty - $damaged);
		$status = $good <= 0 ? 'damaged' : 'available';
		$now = date('Y-m-d H:i:s');

		try {
			if ($id > 0) {
				$existing = $assetMdl->where('school_id', $schoolId)->where('id', $id)->first();
				if (!$existing) {
					return ['error' => 'Asset not found.'];
				}
				$assetMdl->save([
					'id' => $id,
					'name' => $name,
					'description' => $name,
					'category_id' => (int) $cat['id'],
					'location_id' => (int) $loc['id'],
					'quantity' => $qty,
					'qty_damaged' => $damaged,
					'condition_code' => $age === 'new' ? 'new' : 'good',
					'lifecycle_status' => $status,
					'tracking_mode' => 'quantity',
					'updated_by' => $actorId,
				]);
				$histMdl->insert([
					'school_id' => $schoolId,
					'asset_id' => $id,
					'previous_status' => $existing['lifecycle_status'],
					'new_status' => $status,
					'operation_type' => 'update',
					'actor_id' => $actorId,
					'destination_location_id' => (int) $loc['id'],
					'notes' => 'Updated qty ' . $qty . ' (damaged ' . $damaged . ')',
					'created_at' => $now,
				]);
				return ['success' => 'Asset updated.'];
			}

			$lot = $assetMdl->findLot($schoolId, $name, (int) $cat['id'], (int) $loc['id']);
			if ($lot) {
				$newQty = (float) $lot['quantity'] + $qty;
				$newDmg = (float) ($lot['qty_damaged'] ?? 0) + $damaged;
				$assetMdl->save([
					'id' => (int) $lot['id'],
					'quantity' => $newQty,
					'qty_damaged' => $newDmg,
					'condition_code' => $age === 'new' ? 'new' : ($lot['condition_code'] ?: 'good'),
					'lifecycle_status' => ($newQty - $newDmg) <= 0 ? 'damaged' : 'available',
					'updated_by' => $actorId,
				]);
				$histMdl->insert([
					'school_id' => $schoolId,
					'asset_id' => (int) $lot['id'],
					'previous_status' => $lot['lifecycle_status'],
					'new_status' => 'available',
					'operation_type' => 'receive',
					'actor_id' => $actorId,
					'destination_location_id' => (int) $loc['id'],
					'notes' => 'Added ' . $qty . ' to existing stock',
					'created_at' => $now,
				]);
				return ['success' => 'Quantity added to existing stock at this location.'];
			}

			$code = $schema->nextAssetCode($schoolId, $cat['category_code']);
			$assetMdl->insert([
				'school_id' => $schoolId,
				'asset_code' => $code,
				'name' => $name,
				'description' => $name,
				'category_id' => (int) $cat['id'],
				'location_id' => (int) $loc['id'],
				'quantity' => $qty,
				'qty_damaged' => $damaged,
				'condition_code' => $age === 'new' ? 'new' : 'good',
				'lifecycle_status' => $status,
				'tracking_mode' => 'quantity',
				'purchase_price' => 0,
				'total_acquisition_cost' => 0,
				'created_by' => $actorId,
				'updated_by' => $actorId,
			]);
			$newId = (int) $assetMdl->getInsertID();
			$histMdl->insert([
				'school_id' => $schoolId,
				'asset_id' => $newId,
				'new_status' => $status,
				'operation_type' => 'register',
				'actor_id' => $actorId,
				'destination_location_id' => (int) $loc['id'],
				'notes' => 'Registered ' . $qty,
				'created_at' => $now,
			]);
			return ['success' => 'Asset recorded.'];
		} catch (\Throwable $e) {
			return ['error' => 'Could not save: ' . $e->getMessage()];
		}
	}

	public function distribute_asset()
	{
		$schoolId = $this->bootAssets();
		$this->denyUnlessAsset();
		$actorId = (int) $this->session->get('soma_id');
		$assetMdl = new AssetModel();
		$locMdl = new AssetLocationModel();
		$schema = new AssetSchemaModel();
		$histMdl = new AssetStatusHistoryModel();

		$assetId = (int) $this->request->getPost('asset_id');
		$qty = (float) $this->request->getPost('quantity');
		$toLocationId = (int) $this->request->getPost('to_location_id');
		$toLocationName = trim((string) $this->request->getPost('to_location_name'));
		$notes = trim((string) $this->request->getPost('notes'));

		$from = $assetMdl->where('school_id', $schoolId)->where('id', $assetId)->where('archived_at', null)->first();
		if (!$from) {
			return $this->response->setJSON(['error' => 'Asset not found.']);
		}
		if ($qty <= 0) {
			return $this->response->setJSON(['error' => 'Enter how many to send.']);
		}
		$available = (float) $from['quantity'] - (float) ($from['qty_damaged'] ?? 0);
		if ($qty > $available) {
			return $this->response->setJSON(['error' => 'Only ' . rtrim(rtrim(number_format($available, 2), '0'), '.') . ' good items are available to distribute.']);
		}
		if ($toLocationId <= 0 && $toLocationName === '') {
			return $this->response->setJSON(['error' => 'Choose a destination location.']);
		}
		$toLoc = $toLocationId > 0
			? $locMdl->where('school_id', $schoolId)->where('id', $toLocationId)->first()
			: $locMdl->findOrCreateByName($schoolId, $toLocationName, $actorId);
		if (!$toLoc) {
			return $this->response->setJSON(['error' => 'Destination location not found.']);
		}
		if ((int) $toLoc['id'] === (int) $from['location_id']) {
			return $this->response->setJSON(['error' => 'Choose a different location.']);
		}

		$db = \Config\Database::connect();
		$db->transStart();
		try {
			$now = date('Y-m-d H:i:s');
			$newFromQty = (float) $from['quantity'] - $qty;
			$assetMdl->save([
				'id' => (int) $from['id'],
				'quantity' => $newFromQty,
				'lifecycle_status' => ($newFromQty - (float) ($from['qty_damaged'] ?? 0)) <= 0 ? 'assigned' : 'available',
				'updated_by' => $actorId,
			]);

			$dest = $assetMdl->findLot($schoolId, $from['name'], (int) $from['category_id'], (int) $toLoc['id']);
			if ($dest) {
				$assetMdl->save([
					'id' => (int) $dest['id'],
					'quantity' => (float) $dest['quantity'] + $qty,
					'lifecycle_status' => 'available',
					'updated_by' => $actorId,
				]);
				$destId = (int) $dest['id'];
			} else {
				$code = $schema->nextAssetCode($schoolId, 'MOV');
				$assetMdl->insert([
					'school_id' => $schoolId,
					'asset_code' => $code,
					'name' => $from['name'],
					'description' => $from['description'] ?: $from['name'],
					'category_id' => $from['category_id'],
					'location_id' => (int) $toLoc['id'],
					'quantity' => $qty,
					'qty_damaged' => 0,
					'condition_code' => $from['condition_code'] ?: 'good',
					'lifecycle_status' => 'available',
					'tracking_mode' => 'quantity',
					'created_by' => $actorId,
					'updated_by' => $actorId,
				]);
				$destId = (int) $assetMdl->getInsertID();
			}

			$distRow = [
				'school_id' => $schoolId,
				'asset_id' => (int) $from['id'],
				'dest_asset_id' => $destId,
				'from_location_id' => $from['location_id'],
				'to_location_id' => (int) $toLoc['id'],
				'quantity' => $qty,
				'notes' => $notes !== '' ? $notes : null,
				'created_by' => $actorId,
				'created_at' => $now,
			];
			if ($db->fieldExists('direction', 'asset_distributions')) {
				$distRow['direction'] = 'move';
			}
			$db->table('asset_distributions')->insert($distRow);
			$histMdl->insert([
				'school_id' => $schoolId,
				'asset_id' => (int) $from['id'],
				'previous_status' => $from['lifecycle_status'],
				'new_status' => 'available',
				'operation_type' => 'distribute',
				'actor_id' => $actorId,
				'source_location_id' => $from['location_id'],
				'destination_location_id' => (int) $toLoc['id'],
				'notes' => 'Distributed ' . $qty . ($notes !== '' ? (': ' . $notes) : ''),
				'created_at' => $now,
			]);
			$db->transComplete();
			if ($db->transStatus() === false) {
				return $this->response->setJSON(['error' => 'Distribution failed.']);
			}
			return $this->response->setJSON(['success' => 'Distributed ' . rtrim(rtrim(number_format($qty, 2), '0'), '.') . ' to ' . $toLoc['name'] . '.']);
		} catch (\Throwable $e) {
			$db->transRollback();
			return $this->response->setJSON(['error' => 'Could not distribute: ' . $e->getMessage()]);
		}
	}

	public function save_quick_location()
	{
		$schoolId = $this->bootAssets();
		$this->denyUnlessAsset();
		$name = trim((string) $this->request->getPost('name'));
		if ($name === '') {
			return $this->response->setJSON(['error' => 'Location name is required.']);
		}
		$loc = (new AssetLocationModel())->findOrCreateByName($schoolId, $name, (int) $this->session->get('soma_id'));
		if (!$loc) {
			return $this->response->setJSON(['error' => 'Could not save location.']);
		}
		return $this->response->setJSON(['success' => 'Location saved.', 'id' => (int) $loc['id'], 'name' => $loc['name']]);
	}

	public function stock_move()
	{
		$schoolId = $this->bootAssets();
		$this->denyUnlessAsset();
		$actorId = (int) $this->session->get('soma_id');
		$assetMdl = new AssetModel();
		$histMdl = new AssetStatusHistoryModel();

		$assetId = (int) $this->request->getPost('asset_id');
		$qty = (float) $this->request->getPost('quantity');
		$direction = strtolower(trim((string) $this->request->getPost('direction')));
		$notes = trim((string) $this->request->getPost('notes'));
		if ($direction !== 'out') {
			$direction = 'in';
		}
		if ($qty <= 0) {
			return $this->response->setJSON(['error' => 'Enter a quantity greater than 0.']);
		}
		$asset = $assetMdl->where('school_id', $schoolId)->where('id', $assetId)->where('archived_at', null)->first();
		if (!$asset) {
			return $this->response->setJSON(['error' => 'Asset not found.']);
		}

		$current = (float) $asset['quantity'];
		$damaged = (float) ($asset['qty_damaged'] ?? 0);
		$good = max(0, $current - $damaged);
		if ($direction === 'out' && $qty > $good) {
			return $this->response->setJSON(['error' => 'Only ' . rtrim(rtrim(number_format($good, 2), '0'), '.') . ' good items are in stock.']);
		}
		$newQty = $direction === 'in' ? ($current + $qty) : ($current - $qty);
		if ($newQty < $damaged) {
			return $this->response->setJSON(['error' => 'Cannot take out more than the good stock.']);
		}

		$now = date('Y-m-d H:i:s');
		$db = \Config\Database::connect();
		try {
			$assetMdl->save([
				'id' => (int) $asset['id'],
				'quantity' => $newQty,
				'lifecycle_status' => ($newQty - $damaged) <= 0 ? 'assigned' : 'available',
				'updated_by' => $actorId,
			]);
			if ($db->tableExists('asset_distributions')) {
				$row = [
					'school_id' => $schoolId,
					'asset_id' => (int) $asset['id'],
					'dest_asset_id' => (int) $asset['id'],
					'from_location_id' => $asset['location_id'],
					'to_location_id' => $asset['location_id'] ?: 0,
					'quantity' => $qty,
					'notes' => $notes !== '' ? $notes : null,
					'created_by' => $actorId,
					'created_at' => $now,
				];
				if ($db->fieldExists('direction', 'asset_distributions')) {
					$row['direction'] = $direction;
				}
				$db->table('asset_distributions')->insert($row);
			}
			$histMdl->insert([
				'school_id' => $schoolId,
				'asset_id' => (int) $asset['id'],
				'previous_status' => $asset['lifecycle_status'],
				'new_status' => 'available',
				'operation_type' => $direction === 'in' ? 'stock_in' : 'stock_out',
				'actor_id' => $actorId,
				'source_location_id' => $asset['location_id'],
				'destination_location_id' => $asset['location_id'],
				'notes' => strtoupper($direction) . ' ' . $qty . ($notes !== '' ? (': ' . $notes) : ''),
				'created_at' => $now,
			]);
			$label = $direction === 'in' ? 'Stock in recorded.' : 'Stock out recorded.';
			return $this->response->setJSON(['success' => $label]);
		} catch (\Throwable $e) {
			return $this->response->setJSON(['error' => 'Could not update stock: ' . $e->getMessage()]);
		}
	}

	public function assets()
	{
		return redirect()->to(base_url('asset_management/register'));
	}

	public function asset_view($id = null)
	{
		$schoolId = $this->bootAssets();
		$this->denyUnless('asset_assets');
		$id = (int) $id;
		$assetMdl = new AssetModel();
		$histMdl = new AssetStatusHistoryModel();
		$asset = $assetMdl->findDetailed($schoolId, $id);
		if (!$asset) {
			$this->session->setFlashdata('error', 'Asset not found.');
			return redirect()->to(base_url('asset_management/assets'));
		}

		$data = $this->data;
		$data['title'] = $asset['asset_code'] . ' — ' . $asset['name'];
		$data['subtitle'] = 'Asset details';
		$data['page'] = 'asset_assets';
		$data['asset'] = $asset;
		$data['history'] = $histMdl->forAsset($schoolId, $id);
		$fieldMdl = new AssetCategoryFieldModel();
		$data['category_fields'] = !empty($asset['category_id'])
			? $fieldMdl->forCategory($schoolId, $asset['category_id'])
			: [];
		$data['custom_values'] = [];
		if (!empty($asset['custom_fields_json'])) {
			$decoded = json_decode($asset['custom_fields_json'], true);
			if (is_array($decoded)) {
				$data['custom_values'] = $decoded;
			}
		}
		$data['content'] = view('pages/assets/asset_detail', $data);
		return view('main', $data);
	}

	public function save_asset()
	{
		$schoolId = $this->bootAssets();
		$this->denyUnless('asset_assets');
		$actorId = (int) $this->session->get('soma_id');
		$assetMdl = new AssetModel();
		$schema = new AssetSchemaModel();
		$histMdl = new AssetStatusHistoryModel();
		$catMdl = new AssetCategoryModel();

		$id = (int) $this->request->getPost('id');
		$name = trim((string) $this->request->getPost('name'));
		if ($name === '') {
			return $this->response->setJSON(['error' => 'Asset name is required.']);
		}

		$categoryId = (int) $this->request->getPost('category_id');
		$locationId = (int) $this->request->getPost('location_id');
		if ($locationId <= 0) {
			return $this->response->setJSON(['error' => 'Please select where this asset is located.']);
		}
		$status = trim((string) $this->request->getPost('lifecycle_status'));
		if ($status === '' || !in_array($status, self::$lifecycleStatuses, true)) {
			$status = 'draft';
		}

		$purchase = (float) $this->request->getPost('purchase_price');
		$additional = (float) $this->request->getPost('additional_cost');
		$total = $purchase + $additional;

		$categoryCode = 'GEN';
		if ($categoryId > 0) {
			$cat = $catMdl->where('school_id', $schoolId)->where('id', $categoryId)->first();
			if ($cat) {
				$categoryCode = $cat['category_code'];
			}
		}

		$serial = trim((string) $this->request->getPost('serial_number'));
		$barcode = trim((string) $this->request->getPost('barcode'));
		$rfid = strtoupper(trim((string) $this->request->getPost('rfid_tag')));

		$db = \Config\Database::connect();
		$db->transStart();

		try {
			if ($id > 0) {
				$existing = $assetMdl->where('school_id', $schoolId)->where('id', $id)->first();
				if (!$existing) {
					$db->transRollback();
					return $this->response->setJSON(['error' => 'Asset not found.']);
				}
				$assetCode = $existing['asset_code'];
				$dup = $this->findDuplicateIdentifiers($schoolId, $serial, $barcode, $rfid, $id);
				if ($dup) {
					$db->transRollback();
					return $this->response->setJSON(['error' => $dup]);
				}

				$row = [
					'id' => $id,
					'name' => $name,
					'description' => $this->request->getPost('description'),
					'category_id' => $categoryId ?: null,
					'location_id' => $locationId ?: null,
					'brand' => $this->request->getPost('brand'),
					'model' => $this->request->getPost('model'),
					'manufacturer' => $this->request->getPost('manufacturer'),
					'serial_number' => $serial !== '' ? $serial : null,
					'barcode' => $barcode !== '' ? $barcode : null,
					'rfid_tag' => $rfid !== '' ? $rfid : null,
					'custodian_staff_id' => (int) $this->request->getPost('custodian_staff_id') ?: null,
					'responsible_staff_id' => (int) $this->request->getPost('responsible_staff_id') ?: null,
					'supplier' => $this->request->getPost('supplier'),
					'purchase_date' => $this->nullableDate($this->request->getPost('purchase_date')),
					'purchase_price' => $purchase,
					'additional_cost' => $additional,
					'total_acquisition_cost' => $total,
					'currency' => $this->request->getPost('currency') ?: 'RWF',
					'invoice_number' => $this->request->getPost('invoice_number'),
					'condition_code' => $this->request->getPost('condition_code') ?: 'good',
					'lifecycle_status' => $status,
					'useful_life_months' => (int) $this->request->getPost('useful_life_months') ?: null,
					'residual_value' => (float) $this->request->getPost('residual_value'),
					'depreciation_method' => $this->request->getPost('depreciation_method') ?: 'straight_line',
					'net_book_value' => $total - (float) $existing['accumulated_depreciation'],
					'notes' => $this->request->getPost('notes'),
					'updated_by' => $actorId,
					'version' => ((int) $existing['version']) + 1,
				];
				$assetMdl->save($row);

				if ($existing['lifecycle_status'] !== $status
					|| (int) $existing['location_id'] !== $locationId
					|| (int) $existing['custodian_staff_id'] !== (int) ($row['custodian_staff_id'] ?? 0)
				) {
					$histMdl->logChange([
						'school_id' => $schoolId,
						'asset_id' => $id,
						'previous_status' => $existing['lifecycle_status'],
						'new_status' => $status,
						'operation_type' => 'update',
						'actor_id' => $actorId,
						'source_location_id' => $existing['location_id'],
						'destination_location_id' => $locationId ?: null,
						'previous_custodian_id' => $existing['custodian_staff_id'],
						'new_custodian_id' => $row['custodian_staff_id'],
						'notes' => 'Asset updated',
					]);
				}
			} else {
				$assetCode = trim((string) $this->request->getPost('asset_code'));
				if ($assetCode === '') {
					$assetCode = $schema->nextAssetCode($schoolId, $categoryCode);
				}
				$existsCode = $assetMdl->where('school_id', $schoolId)->where('asset_code', $assetCode)->first();
				if ($existsCode) {
					$db->transRollback();
					return $this->response->setJSON(['error' => 'Asset code already exists: ' . $assetCode]);
				}
				$dup = $this->findDuplicateIdentifiers($schoolId, $serial, $barcode, $rfid, 0);
				if ($dup) {
					$db->transRollback();
					return $this->response->setJSON(['error' => $dup]);
				}

				$row = [
					'school_id' => $schoolId,
					'asset_code' => $assetCode,
					'name' => $name,
					'description' => $this->request->getPost('description'),
					'category_id' => $categoryId ?: null,
					'location_id' => $locationId ?: null,
					'brand' => $this->request->getPost('brand'),
					'model' => $this->request->getPost('model'),
					'manufacturer' => $this->request->getPost('manufacturer'),
					'serial_number' => $serial !== '' ? $serial : null,
					'barcode' => $barcode !== '' ? $barcode : null,
					'rfid_tag' => $rfid !== '' ? $rfid : null,
					'custodian_staff_id' => (int) $this->request->getPost('custodian_staff_id') ?: null,
					'responsible_staff_id' => (int) $this->request->getPost('responsible_staff_id') ?: null,
					'supplier' => $this->request->getPost('supplier'),
					'purchase_date' => $this->nullableDate($this->request->getPost('purchase_date')),
					'purchase_price' => $purchase,
					'additional_cost' => $additional,
					'total_acquisition_cost' => $total,
					'currency' => $this->request->getPost('currency') ?: 'RWF',
					'invoice_number' => $this->request->getPost('invoice_number'),
					'condition_code' => $this->request->getPost('condition_code') ?: 'good',
					'lifecycle_status' => $status,
					'useful_life_months' => (int) $this->request->getPost('useful_life_months') ?: null,
					'residual_value' => (float) $this->request->getPost('residual_value'),
					'depreciation_method' => $this->request->getPost('depreciation_method') ?: 'straight_line',
					'net_book_value' => $total,
					'quantity' => 1,
					'tracking_mode' => 'individual',
					'notes' => $this->request->getPost('notes'),
					'created_by' => $actorId,
					'updated_by' => $actorId,
					'version' => 1,
				];
				$assetMdl->insert($row);
				$id = (int) $assetMdl->getInsertID();
				$histMdl->logChange([
					'school_id' => $schoolId,
					'asset_id' => $id,
					'previous_status' => null,
					'new_status' => $status,
					'operation_type' => 'create',
					'actor_id' => $actorId,
					'destination_location_id' => $locationId ?: null,
					'new_custodian_id' => $row['custodian_staff_id'],
					'notes' => 'Asset registered',
				]);
			}

			$db->transComplete();
			if ($db->transStatus() === false) {
				return $this->response->setJSON(['error' => 'Could not save asset. Please try again.']);
			}
			return $this->response->setJSON([
				'success' => 'Asset saved successfully.',
				'id' => $id,
				'asset_code' => $assetCode,
			]);
		} catch (\Throwable $e) {
			$db->transRollback();
			return $this->response->setJSON(['error' => 'Error: ' . $e->getMessage()]);
		}
	}

	public function archive_asset()
	{
		$schoolId = $this->bootAssets();
		$this->denyUnless('asset_assets');
		$id = (int) $this->request->getPost('id');
		$assetMdl = new AssetModel();
		$histMdl = new AssetStatusHistoryModel();
		$existing = $assetMdl->where('school_id', $schoolId)->where('id', $id)->first();
		if (!$existing) {
			return $this->response->setJSON(['error' => 'Asset not found.']);
		}
		$assetMdl->save([
			'id' => $id,
			'lifecycle_status' => 'archived',
			'archived_at' => date('Y-m-d H:i:s'),
			'updated_by' => (int) $this->session->get('soma_id'),
		]);
		$histMdl->logChange([
			'school_id' => $schoolId,
			'asset_id' => $id,
			'previous_status' => $existing['lifecycle_status'],
			'new_status' => 'archived',
			'operation_type' => 'archive',
			'actor_id' => (int) $this->session->get('soma_id'),
			'notes' => $this->request->getPost('notes') ?: 'Archived',
		]);
		return $this->response->setJSON(['success' => 'Asset archived.']);
	}

	public function locations()
	{
		return redirect()->to(base_url('asset_management/register'));
	}

	public function save_location()
	{
		$schoolId = $this->bootAssets();
		$this->denyUnless('asset_locations');
		$locMdl = new AssetLocationModel();
		$id = (int) $this->request->getPost('id');
		$name = trim((string) $this->request->getPost('name'));
		$code = strtoupper(trim((string) $this->request->getPost('location_code')));
		if ($name === '') {
			return $this->response->setJSON(['error' => 'Location name is required.']);
		}
		if ($code === '') {
			$slug = strtoupper(preg_replace('/[^A-Z0-9]+/', '_', $name));
			$slug = trim($slug, '_');
			$code = substr($slug !== '' ? $slug : ('LOC' . time()), 0, 40);
		}
		$parentId = (int) $this->request->getPost('parent_location_id') ?: null;
		if ($parentId && $id && $parentId === $id) {
			return $this->response->setJSON(['error' => 'A location cannot be its own parent.']);
		}

		$dup = $locMdl->where('school_id', $schoolId)->where('location_code', $code);
		if ($id > 0) {
			$dup->where('id !=', $id);
		}
		if ($dup->first()) {
			return $this->response->setJSON(['error' => 'Location code already exists.']);
		}

		$row = [
			'school_id' => $schoolId,
			'parent_location_id' => $parentId,
			'location_code' => $code,
			'name' => $name,
			'location_type' => $this->request->getPost('location_type'),
			'description' => $this->request->getPost('description'),
			'campus' => $this->request->getPost('campus'),
			'building' => $this->request->getPost('building'),
			'floor' => $this->request->getPost('floor'),
			'room' => $this->request->getPost('room'),
			'capacity' => (int) $this->request->getPost('capacity') ?: null,
			'responsible_staff_id' => (int) $this->request->getPost('responsible_staff_id') ?: null,
			'status' => 1,
			'updated_by' => (int) $this->session->get('soma_id'),
		];
		if ($id > 0) {
			$row['id'] = $id;
			$row['archived_at'] = null;
		} else {
			$row['created_by'] = (int) $this->session->get('soma_id');
		}
		try {
			$locMdl->save($row);
			return $this->response->setJSON(['success' => 'Location saved.']);
		} catch (\Throwable $e) {
			return $this->response->setJSON(['error' => 'Error: ' . $e->getMessage()]);
		}
	}

	public function archive_location()
	{
		$schoolId = $this->bootAssets();
		$this->denyUnless('asset_locations');
		$id = (int) $this->request->getPost('id');
		$locMdl = new AssetLocationModel();
		$existing = $locMdl->where('school_id', $schoolId)->where('id', $id)->first();
		if (!$existing) {
			return $this->response->setJSON(['error' => 'Location not found.']);
		}
		$stats = $locMdl->assetStats($schoolId, $id);
		if ($stats['count'] > 0) {
			return $this->response->setJSON([
				'error' => 'Cannot archive: ' . $stats['count'] . ' active asset(s) are still assigned to this location. Move them first.',
			]);
		}
		$locMdl->save([
			'id' => $id,
			'status' => 0,
			'archived_at' => date('Y-m-d H:i:s'),
			'updated_by' => (int) $this->session->get('soma_id'),
		]);
		return $this->response->setJSON(['success' => 'Location archived.']);
	}

	public function restore_location()
	{
		$schoolId = $this->bootAssets();
		$this->denyUnless('asset_locations');
		$id = (int) $this->request->getPost('id');
		$locMdl = new AssetLocationModel();
		$existing = $locMdl->where('school_id', $schoolId)->where('id', $id)->first();
		if (!$existing) {
			return $this->response->setJSON(['error' => 'Location not found.']);
		}
		$locMdl->save([
			'id' => $id,
			'status' => 1,
			'archived_at' => null,
			'updated_by' => (int) $this->session->get('soma_id'),
		]);
		return $this->response->setJSON(['success' => 'Location restored.']);
	}

	public function categories()
	{
		$schoolId = $this->bootAssets();
		$this->denyUnless('asset_categories');
		$data = $this->data;
		$catMdl = new AssetCategoryModel();
		$fieldMdl = new AssetCategoryFieldModel();
		$rows = $catMdl->listForSchool($schoolId, true);

		$data['title'] = 'Asset Categories';
		$data['subtitle'] = 'Categories';
		$data['page'] = 'asset_categories';
		$data['categories'] = $rows;
		$data['category_tree'] = $catMdl->buildTree(array_filter($rows, function ($r) {
			return (int) $r['status'] === 1;
		}));
		$data['content'] = view('pages/assets/categories', $data);
		return view('main', $data);
	}

	public function save_category()
	{
		$schoolId = $this->bootAssets();
		$this->denyUnless('asset_categories');
		$catMdl = new AssetCategoryModel();
		$id = (int) $this->request->getPost('id');
		$name = trim((string) $this->request->getPost('name'));
		$code = strtoupper(trim((string) $this->request->getPost('category_code')));
		if ($name === '' || $code === '') {
			return $this->response->setJSON(['error' => 'Name and category code are required.']);
		}
		$parentId = (int) $this->request->getPost('parent_category_id') ?: null;
		if ($parentId && $id && $parentId === $id) {
			return $this->response->setJSON(['error' => 'A category cannot be its own parent.']);
		}
		$dup = $catMdl->where('school_id', $schoolId)->where('category_code', $code);
		if ($id > 0) {
			$dup->where('id !=', $id);
		}
		if ($dup->first()) {
			return $this->response->setJSON(['error' => 'Category code already exists.']);
		}

		$row = [
			'school_id' => $schoolId,
			'parent_category_id' => $parentId,
			'category_code' => $code,
			'name' => $name,
			'description' => $this->request->getPost('description'),
			'asset_class' => $this->request->getPost('asset_class') ?: 'tangible',
			'tracking_mode' => $this->request->getPost('tracking_mode') ?: 'individual',
			'is_fixed_asset' => $this->request->getPost('is_fixed_asset') ? 1 : 0,
			'is_consumable' => $this->request->getPost('is_consumable') ? 1 : 0,
			'default_useful_life' => (int) $this->request->getPost('default_useful_life') ?: null,
			'default_depreciation_method' => $this->request->getPost('default_depreciation_method') ?: 'straight_line',
			'requires_serial_number' => $this->request->getPost('requires_serial_number') ? 1 : 0,
			'requires_rfid' => $this->request->getPost('requires_rfid') ? 1 : 0,
			'requires_barcode' => $this->request->getPost('requires_barcode') ? 1 : 0,
			'requires_warranty' => $this->request->getPost('requires_warranty') ? 1 : 0,
			'status' => 1,
			'updated_by' => (int) $this->session->get('soma_id'),
		];
		if ($id > 0) {
			$row['id'] = $id;
		} else {
			$row['created_by'] = (int) $this->session->get('soma_id');
		}
		try {
			$catMdl->save($row);
			return $this->response->setJSON(['success' => 'Category saved.']);
		} catch (\Throwable $e) {
			return $this->response->setJSON(['error' => 'Error: ' . $e->getMessage()]);
		}
	}

	public function archive_category()
	{
		$schoolId = $this->bootAssets();
		$this->denyUnless('asset_categories');
		$id = (int) $this->request->getPost('id');
		$catMdl = new AssetCategoryModel();
		$assetMdl = new AssetModel();
		$existing = $catMdl->where('school_id', $schoolId)->where('id', $id)->first();
		if (!$existing) {
			return $this->response->setJSON(['error' => 'Category not found.']);
		}
		$inUse = $assetMdl->where('school_id', $schoolId)->where('category_id', $id)->where('archived_at', null)->countAllResults();
		if ($inUse > 0) {
			return $this->response->setJSON(['error' => 'Cannot archive: ' . $inUse . ' active asset(s) use this category.']);
		}
		$catMdl->save([
			'id' => $id,
			'status' => 0,
			'archived_at' => date('Y-m-d H:i:s'),
			'updated_by' => (int) $this->session->get('soma_id'),
		]);
		return $this->response->setJSON(['success' => 'Category archived.']);
	}

	public function save_category_field()
	{
		$schoolId = $this->bootAssets();
		$this->denyUnless('asset_categories');
		$fieldMdl = new AssetCategoryFieldModel();
		$categoryId = (int) $this->request->getPost('category_id');
		$label = trim((string) $this->request->getPost('field_label'));
		$key = strtolower(preg_replace('/[^a-zA-Z0-9_]+/', '_', trim((string) $this->request->getPost('field_key'))));
		if ($categoryId <= 0 || $label === '' || $key === '') {
			return $this->response->setJSON(['error' => 'Category, field key and label are required.']);
		}
		try {
			$fieldMdl->insert([
				'school_id' => $schoolId,
				'category_id' => $categoryId,
				'field_key' => $key,
				'field_label' => $label,
				'data_type' => $this->request->getPost('data_type') ?: 'text',
				'is_required' => $this->request->getPost('is_required') ? 1 : 0,
				'sort_order' => (int) $this->request->getPost('sort_order'),
				'status' => 1,
			]);
			return $this->response->setJSON(['success' => 'Custom field added.']);
		} catch (\Throwable $e) {
			return $this->response->setJSON(['error' => 'Error: ' . $e->getMessage()]);
		}
	}

	public function category_fields($categoryId = null)
	{
		$schoolId = $this->bootAssets();
		$this->denyUnless('asset_categories');
		$fieldMdl = new AssetCategoryFieldModel();
		$rows = $fieldMdl->forCategory($schoolId, (int) $categoryId);
		return $this->response->setJSON(['success' => true, 'fields' => $rows]);
	}

	/** Legacy placeholder URLs redirect to live screens. */
	public function placeholder($section = 'module')
	{
		$map = [
			'assignments' => 'asset_management/assignments',
			'checkout' => 'asset_management/checkout',
			'transfers' => 'asset_management/transfers',
			'maintenance' => 'asset_management/maintenance',
			'inspections' => 'asset_management/inspections',
			'incidents' => 'asset_management/incidents',
			'audits' => 'asset_management/audits',
			'reports' => 'asset_management/reports',
			'settings' => 'asset_management/settings',
		];
		$section = strtolower((string) $section);
		$url = isset($map[$section]) ? $map[$section] : 'asset_management/dashboard';
		return redirect()->to(base_url($url));
	}

	public function settings()
	{
		$schoolId = $this->bootAssets();
		$this->denyUnless('asset_settings');
		$data = $this->data;
		$db = \Config\Database::connect();
		$settings = $db->table('asset_settings')->where('school_id', $schoolId)->get(1)->getRowArray();
		$data['title'] = 'Asset Settings';
		$data['subtitle'] = 'Settings';
		$data['page'] = 'asset_settings';
		$data['settings'] = $settings;
		$data['content'] = view('pages/assets/settings', $data);
		return view('main', $data);
	}

	public function save_settings()
	{
		$schoolId = $this->bootAssets();
		$this->denyUnless('asset_settings');
		$db = \Config\Database::connect();
		$pattern = trim((string) $this->request->getPost('code_pattern'));
		if ($pattern === '') {
			$pattern = 'AST-{CATEGORY}-{YEAR}-{SEQ}';
		}
		$row = [
			'code_pattern' => $pattern,
			'seq_padding' => max(3, (int) $this->request->getPost('seq_padding')),
			'default_currency' => $this->request->getPost('default_currency') ?: 'RWF',
			'updated_at' => date('Y-m-d H:i:s'),
		];
		$existing = $db->table('asset_settings')->where('school_id', $schoolId)->get(1)->getRowArray();
		if ($existing) {
			$db->table('asset_settings')->where('id', $existing['id'])->update($row);
		} else {
			$row['school_id'] = $schoolId;
			$row['created_at'] = date('Y-m-d H:i:s');
			$db->table('asset_settings')->insert($row);
		}
		return $this->response->setJSON(['success' => 'Settings saved.']);
	}

	private function nullableDate($value)
	{
		$value = trim((string) $value);
		if ($value === '') {
			return null;
		}
		return $value;
	}

	private function findDuplicateIdentifiers($schoolId, $serial, $barcode, $rfid, $excludeId)
	{
		$assetMdl = new AssetModel();
		if ($serial !== '') {
			$q = $assetMdl->where('school_id', $schoolId)->where('serial_number', $serial);
			if ($excludeId > 0) {
				$q->where('id !=', $excludeId);
			}
			if ($q->first()) {
				return 'Serial number already used by another asset.';
			}
		}
		if ($barcode !== '') {
			$q = $assetMdl->where('school_id', $schoolId)->where('barcode', $barcode);
			if ($excludeId > 0) {
				$q->where('id !=', $excludeId);
			}
			if ($q->first()) {
				return 'Barcode already used by another asset.';
			}
		}
		if ($rfid !== '') {
			$q = $assetMdl->where('school_id', $schoolId)->where('rfid_tag', $rfid);
			if ($excludeId > 0) {
				$q->where('id !=', $excludeId);
			}
			if ($q->first()) {
				return 'RFID tag already used by another asset.';
			}
		}
		return null;
	}
}
