<?php
namespace App\Models;

use CodeIgniter\Model;

class LeaveModel extends Model
{
	protected $table="leaves";
	protected $allowedFields = ["type","school_id","reason","days","requested_by","fromDate","toDate","address","approved_by","status","deny_reason"];
	protected $useTimestamps = true;
	protected $primaryKey = 'id';
	protected $createdField  = 'created_at';
	protected $updatedField  = 'updated_at';

}
