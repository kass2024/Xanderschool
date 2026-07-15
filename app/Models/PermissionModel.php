<?php namespace App\Models;
use CodeIgniter\Model;

class PermissionModel extends Model{
	protected $table = "permission";
	protected $allowedFields = ["student_id","reason","destination","leave_time","return_time","active_term","notify_parent","status","comment","created_by","updated_by"];
	protected $useTimestamps = true;
	protected $primaryKey = 'id';
	protected $createdField  = 'created_at';
	protected $updatedField  = 'updated_at';

}
