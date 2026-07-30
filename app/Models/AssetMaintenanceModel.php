<?php

namespace App\Models;

use CodeIgniter\Model;

class AssetMaintenanceModel extends Model
{
	protected $table = 'asset_maintenance';
	protected $primaryKey = 'id';
	protected $returnType = 'array';
	protected $useTimestamps = true;
	protected $allowedFields = [
		'school_id', 'work_order_no', 'asset_id', 'maintenance_type', 'problem',
		'priority', 'requested_by', 'assigned_to', 'provider_type',
		'scheduled_date', 'start_date', 'completion_date',
		'labour_cost', 'parts_cost', 'other_cost', 'total_cost',
		'work_performed', 'result', 'next_maintenance_date', 'status',
	];

	public function ensureSchema()
	{
		AssetOpsSchema::ensureAll();
	}
}
