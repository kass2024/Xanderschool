<?php

namespace App\Models;

use CodeIgniter\Model;

class AssetTransferModel extends Model
{
	protected $table = 'asset_transfers';
	protected $primaryKey = 'id';
	protected $returnType = 'array';
	protected $useTimestamps = true;
	protected $allowedFields = [
		'school_id', 'transfer_no', 'status', 'transfer_type', 'is_temporary',
		'from_location_id', 'to_location_id', 'from_custodian_id', 'to_custodian_id',
		'notes', 'created_by', 'approved_by', 'received_by', 'completed_at',
	];

	public function ensureSchema()
	{
		AssetOpsSchema::ensureAll();
	}
}
