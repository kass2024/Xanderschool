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
		$schema->ensureSchema();
		AssetOpsSchema::ensureAll();
		$schema->seedDefaults($schoolId, (int) $this->session->get('soma_id'));
		return $schoolId;
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
		$this->denyUnless('asset_dashboard');
		$data = $this->data;
		$assetMdl = new AssetModel();
		$locMdl = new AssetLocationModel();
		$catMdl = new AssetCategoryModel();

		$data['title'] = 'Asset Management Dashboard';
		$data['subtitle'] = 'Asset Management';
		$data['page'] = 'asset_dashboard';
		$data['stats'] = $assetMdl->dashboardStats($schoolId);
		$data['recent'] = array_slice($assetMdl->listDetailed($schoolId), 0, 10);
		$data['location_count'] = count($locMdl->listForSchool($schoolId));
		$data['category_count'] = count($catMdl->listForSchool($schoolId));
		$data['content'] = view('pages/assets/dashboard', $data);
		return view('main', $data);
	}

	public function assets()
	{
		$schoolId = $this->bootAssets();
		$this->denyUnless('asset_assets');
		$data = $this->data;
		$assetMdl = new AssetModel();
		$catMdl = new AssetCategoryModel();
		$locMdl = new AssetLocationModel();
		$staffMdl = new StaffModel();

		$filters = [
			'q' => $this->request->getGet('q'),
			'status' => $this->request->getGet('status'),
			'category_id' => $this->request->getGet('category_id'),
			'location_id' => $this->request->getGet('location_id'),
		];

		$data['title'] = 'Assets';
		$data['subtitle'] = 'Asset register';
		$data['page'] = 'asset_assets';
		$data['filters'] = $filters;
		$data['assets'] = $assetMdl->listDetailed($schoolId, $filters);
		$data['categories'] = $catMdl->listForSchool($schoolId);
		$data['locations'] = $locMdl->listForSchool($schoolId);
		$data['staffs'] = $staffMdl->select("id, concat(fname,' ',lname) as names")
			->where('school_id', $schoolId)
			->orderBy('fname', 'ASC')
			->get()->getResultArray();
		$data['statuses'] = self::$lifecycleStatuses;
		$data['content'] = view('pages/assets/assets_list', $data);
		return view('main', $data);
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
		$schoolId = $this->bootAssets();
		$this->denyUnless('asset_locations');
		$data = $this->data;
		$locMdl = new AssetLocationModel();
		$staffMdl = new StaffModel();
		$rows = $locMdl->listForSchool($schoolId, true);
		foreach ($rows as &$row) {
			$stats = $locMdl->assetStats($schoolId, (int) $row['id']);
			$row['asset_count'] = $stats['count'];
			$row['asset_value'] = $stats['value'];
		}
		unset($row);

		$data['title'] = 'Areas and Locations';
		$data['subtitle'] = 'Asset locations';
		$data['page'] = 'asset_locations';
		$data['locations'] = $rows;
		$data['location_tree'] = $locMdl->buildTree(array_filter($rows, function ($r) {
			return (int) $r['status'] === 1;
		}));
		$data['staffs'] = $staffMdl->select("id, concat(fname,' ',lname) as names")
			->where('school_id', $schoolId)->get()->getResultArray();
		$data['content'] = view('pages/assets/locations', $data);
		return view('main', $data);
	}

	public function save_location()
	{
		$schoolId = $this->bootAssets();
		$this->denyUnless('asset_locations');
		$locMdl = new AssetLocationModel();
		$id = (int) $this->request->getPost('id');
		$name = trim((string) $this->request->getPost('name'));
		$code = strtoupper(trim((string) $this->request->getPost('location_code')));
		if ($name === '' || $code === '') {
			return $this->response->setJSON(['error' => 'Name and location code are required.']);
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
