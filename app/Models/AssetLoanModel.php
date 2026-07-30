<?php

namespace App\Models;

use CodeIgniter\Model;

class AssetLoanModel extends Model
{
	protected $table = 'asset_loans';
	protected $primaryKey = 'id';
	protected $returnType = 'array';
	protected $useTimestamps = true;
	protected $allowedFields = [
		'school_id', 'asset_id', 'borrower_type', 'borrower_id',
		'issued_by', 'issue_at', 'due_at', 'return_at',
		'issue_condition', 'return_condition', 'source_location_id',
		'intended_use', 'notes', 'penalty_amount', 'status',
	];

	public function ensureSchema()
	{
		AssetOpsSchema::ensureAll();
	}
}
