<?php

namespace App\Models;

use CodeIgniter\Model;

class AssetAuditModel extends Model
{
	protected $table = 'asset_audits';
	protected $primaryKey = 'id';
	protected $returnType = 'array';
	protected $useTimestamps = true;
	protected $allowedFields = [
		'school_id', 'audit_no', 'title', 'status',
		'location_id', 'category_id', 'custodian_id', 'snapshot_json',
		'created_by', 'closed_by', 'closed_at',
	];

	public function ensureSchema()
	{
		AssetOpsSchema::ensureAll();
	}
}
