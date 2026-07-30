<?php

namespace App\Models;

use CodeIgniter\Model;

class AssetCategoryModel extends Model
{
	protected $table = 'asset_categories';
	protected $primaryKey = 'id';
	protected $returnType = 'array';
	protected $useTimestamps = true;
	protected $allowedFields = [
		'school_id', 'parent_category_id', 'category_code', 'name', 'description',
		'asset_class', 'tracking_mode', 'is_fixed_asset', 'is_consumable',
		'default_useful_life', 'default_depreciation_method', 'default_residual_percent',
		'inspection_frequency_days', 'maintenance_frequency_days',
		'requires_serial_number', 'requires_rfid', 'requires_barcode', 'requires_warranty',
		'status', 'created_by', 'updated_by', 'archived_at',
	];

	public function ensureSchema()
	{
		(new AssetSchemaModel())->ensureSchema();
	}

	public function listForSchool($schoolId, $includeArchived = false)
	{
		$this->ensureSchema();
		$builder = $this->where('school_id', (int) $schoolId)->orderBy('name', 'ASC');
		if (!$includeArchived) {
			$builder->where('status', 1);
		}
		return $builder->findAll();
	}

	public function buildTree(array $rows)
	{
		$byParent = [];
		foreach ($rows as $row) {
			$pid = $row['parent_category_id'] ? (int) $row['parent_category_id'] : 0;
			if (!isset($byParent[$pid])) {
				$byParent[$pid] = [];
			}
			$byParent[$pid][] = $row;
		}
		$walk = function ($parentId) use (&$walk, $byParent) {
			$nodes = [];
			if (!isset($byParent[$parentId])) {
				return $nodes;
			}
			foreach ($byParent[$parentId] as $row) {
				$row['children'] = $walk((int) $row['id']);
				$nodes[] = $row;
			}
			return $nodes;
		};
		return $walk(0);
	}
}
