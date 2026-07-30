<?php

namespace App\Models;

use CodeIgniter\Model;

class AssetModel extends Model
{
	protected $table = 'assets';
	protected $primaryKey = 'id';
	protected $returnType = 'array';
	protected $useTimestamps = true;
	protected $allowedFields = [
		'school_id', 'asset_code', 'name', 'description', 'category_id', 'location_id',
		'brand', 'model', 'manufacturer', 'serial_number', 'barcode', 'rfid_tag',
		'part_number', 'external_ref', 'department', 'cost_centre',
		'custodian_staff_id', 'responsible_staff_id', 'ownership_type', 'funding_source',
		'supplier', 'purchase_date', 'receipt_date', 'commissioning_date',
		'purchase_price', 'currency', 'additional_cost', 'total_acquisition_cost',
		'po_number', 'invoice_number', 'useful_life_months', 'residual_value',
		'depreciation_method', 'depreciation_start_date', 'accumulated_depreciation',
		'net_book_value', 'replacement_value', 'condition_code', 'lifecycle_status',
		'criticality', 'warranty_start', 'warranty_expiry', 'insurance_policy',
		'insurance_expiry', 'last_inspection_date', 'next_inspection_date',
		'last_maintenance_date', 'next_maintenance_date', 'quantity', 'tracking_mode',
		'photo_path', 'notes', 'custom_fields_json', 'version', 'approved_by',
		'created_by', 'updated_by', 'archived_at',
	];

	public function ensureSchema()
	{
		(new AssetSchemaModel())->ensureSchema();
	}

	public function listDetailed($schoolId, array $filters = [])
	{
		$this->ensureSchema();
		$builder = $this->select('assets.*, c.name AS category_name, c.category_code,
				l.name AS location_name, l.location_code,
				CONCAT(cs.fname, " ", cs.lname) AS custodian_name')
			->join('asset_categories c', 'c.id = assets.category_id', 'left')
			->join('asset_locations l', 'l.id = assets.location_id', 'left')
			->join('staffs cs', 'cs.id = assets.custodian_staff_id', 'left')
			->where('assets.school_id', (int) $schoolId);

		if (empty($filters['include_archived'])) {
			$builder->where('assets.archived_at', null);
		}
		if (!empty($filters['status'])) {
			$builder->where('assets.lifecycle_status', $filters['status']);
		}
		if (!empty($filters['category_id'])) {
			$builder->where('assets.category_id', (int) $filters['category_id']);
		}
		if (!empty($filters['location_id'])) {
			$builder->where('assets.location_id', (int) $filters['location_id']);
		}
		if (!empty($filters['q'])) {
			$q = trim($filters['q']);
			$builder->groupStart()
				->like('assets.asset_code', $q)
				->orLike('assets.name', $q)
				->orLike('assets.serial_number', $q)
				->orLike('assets.barcode', $q)
				->orLike('assets.rfid_tag', $q)
				->groupEnd();
		}

		return $builder->orderBy('assets.id', 'DESC')->findAll();
	}

	public function findDetailed($schoolId, $id)
	{
		$this->ensureSchema();
		return $this->select('assets.*, c.name AS category_name, c.category_code,
				l.name AS location_name, l.location_code,
				CONCAT(cs.fname, " ", cs.lname) AS custodian_name,
				CONCAT(rs.fname, " ", rs.lname) AS responsible_name')
			->join('asset_categories c', 'c.id = assets.category_id', 'left')
			->join('asset_locations l', 'l.id = assets.location_id', 'left')
			->join('staffs cs', 'cs.id = assets.custodian_staff_id', 'left')
			->join('staffs rs', 'rs.id = assets.responsible_staff_id', 'left')
			->where('assets.school_id', (int) $schoolId)
			->where('assets.id', (int) $id)
			->first();
	}

	public function dashboardStats($schoolId)
	{
		$this->ensureSchema();
		$db = \Config\Database::connect();
		$schoolId = (int) $schoolId;

		$totals = $db->table('assets')
			->select('COUNT(*) AS total_count,
				COALESCE(SUM(total_acquisition_cost),0) AS total_cost,
				COALESCE(SUM(net_book_value),0) AS total_nbv,
				COALESCE(SUM(replacement_value),0) AS total_replacement')
			->where('school_id', $schoolId)
			->where('archived_at', null)
			->get()
			->getRowArray();

		$byStatus = $db->table('assets')
			->select('lifecycle_status, COUNT(*) AS cnt')
			->where('school_id', $schoolId)
			->where('archived_at', null)
			->groupBy('lifecycle_status')
			->get()
			->getResultArray();

		$statusMap = [];
		foreach ($byStatus as $row) {
			$statusMap[$row['lifecycle_status']] = (int) $row['cnt'];
		}

		return [
			'total_count' => (int) ($totals['total_count'] ?? 0),
			'total_cost' => (float) ($totals['total_cost'] ?? 0),
			'total_nbv' => (float) ($totals['total_nbv'] ?? 0),
			'total_replacement' => (float) ($totals['total_replacement'] ?? 0),
			'by_status' => $statusMap,
			'available' => (int) ($statusMap['available'] ?? 0),
			'assigned' => (int) ($statusMap['assigned'] ?? 0) + (int) ($statusMap['in_use'] ?? 0),
			'checked_out' => (int) ($statusMap['checked_out'] ?? 0),
			'maintenance' => (int) ($statusMap['under_maintenance'] ?? 0) + (int) ($statusMap['under_repair'] ?? 0),
			'damaged' => (int) ($statusMap['damaged'] ?? 0),
			'missing' => (int) ($statusMap['missing'] ?? 0) + (int) ($statusMap['stolen'] ?? 0),
			'draft' => (int) ($statusMap['draft'] ?? 0),
		];
	}
}
