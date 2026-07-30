<?php

namespace App\Models;

use CodeIgniter\Model;

class AssetStatusHistoryModel extends Model
{
	protected $table = 'asset_status_history';
	protected $primaryKey = 'id';
	protected $returnType = 'array';
	protected $useTimestamps = false;
	protected $allowedFields = [
		'school_id', 'asset_id', 'previous_status', 'new_status', 'operation_type',
		'actor_id', 'source_location_id', 'destination_location_id',
		'previous_custodian_id', 'new_custodian_id', 'notes', 'attachment_path',
		'approval_ref', 'created_at',
	];

	public function ensureSchema()
	{
		(new AssetSchemaModel())->ensureSchema();
	}

	public function logChange(array $payload)
	{
		$this->ensureSchema();
		$payload['created_at'] = date('Y-m-d H:i:s');
		return $this->insert($payload);
	}

	public function forAsset($schoolId, $assetId)
	{
		$this->ensureSchema();
		return $this->select('asset_status_history.*, CONCAT(st.fname, " ", st.lname) AS actor_name')
			->join('staffs st', 'st.id = asset_status_history.actor_id', 'left')
			->where('asset_status_history.school_id', (int) $schoolId)
			->where('asset_status_history.asset_id', (int) $assetId)
			->orderBy('asset_status_history.id', 'DESC')
			->findAll();
	}
}
