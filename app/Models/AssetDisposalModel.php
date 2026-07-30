<?php

namespace App\Models;

use CodeIgniter\Model;

class AssetDisposalModel extends Model
{
	protected $table = 'asset_disposals';
	protected $primaryKey = 'id';
	protected $returnType = 'array';
	protected $useTimestamps = true;
	protected $allowedFields = [
		'school_id', 'asset_id', 'disposal_no', 'method', 'status',
		'reason', 'proceeds', 'requested_by', 'approved_by', 'completed_at',
	];

	public function ensureSchema()
	{
		AssetOpsSchema::ensureAll();
	}
}
