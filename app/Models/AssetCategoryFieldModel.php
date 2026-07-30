<?php

namespace App\Models;

use CodeIgniter\Model;

class AssetCategoryFieldModel extends Model
{
	protected $table = 'asset_category_fields';
	protected $primaryKey = 'id';
	protected $returnType = 'array';
	protected $useTimestamps = true;
	protected $allowedFields = [
		'school_id', 'category_id', 'field_key', 'field_label', 'data_type',
		'options_json', 'is_required', 'sort_order', 'status',
	];

	public function ensureSchema()
	{
		(new AssetSchemaModel())->ensureSchema();
	}

	public function forCategory($schoolId, $categoryId)
	{
		$this->ensureSchema();
		return $this->where('school_id', (int) $schoolId)
			->where('category_id', (int) $categoryId)
			->where('status', 1)
			->orderBy('sort_order', 'ASC')
			->findAll();
	}
}
