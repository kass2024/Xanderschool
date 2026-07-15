<?php
namespace App\Models;

use CodeIgniter\Model;

class ManualDecisionModel extends Model
{
	protected $table="manual_decisions";
	protected $allowedFields = ["school_id","student","academic_year","first_verdict","second_verdict","created_by","updated_by"];
	protected $useTimestamps = true;
	protected $primaryKey = 'id';
	protected $createdField  = 'created_at';
	protected $updatedField  = 'updated_at';

}
