<?php

namespace App\Models;

use CodeIgniter\Model;

class AssetImportModel extends Model
{
	protected $table = 'asset_imports';
	protected $primaryKey = 'id';
	protected $returnType = 'array';
	protected $useTimestamps = true;
	protected $allowedFields = [
		'school_id', 'filename', 'mode', 'status',
		'total_rows', 'valid_rows', 'warning_rows', 'error_rows',
		'created_by', 'summary_json',
	];

	public function ensureSchema()
	{
		AssetOpsSchema::ensureAll();
	}
}
