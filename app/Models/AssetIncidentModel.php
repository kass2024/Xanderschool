<?php

namespace App\Models;

use CodeIgniter\Model;

class AssetIncidentModel extends Model
{
	protected $table = 'asset_incidents';
	protected $primaryKey = 'id';
	protected $returnType = 'array';
	protected $useTimestamps = true;
	protected $allowedFields = [
		'school_id', 'incident_no', 'asset_id', 'reported_by', 'incident_at',
		'location_id', 'incident_type', 'description', 'people_involved',
		'immediate_action', 'estimated_loss', 'police_ref', 'insurance_ref',
		'findings', 'decision', 'financial_recovery', 'status',
	];

	public function ensureSchema()
	{
		AssetOpsSchema::ensureAll();
	}
}
