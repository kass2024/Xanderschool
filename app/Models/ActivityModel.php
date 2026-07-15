<?php
namespace App\Models;

use CodeIgniter\Model;

class ActivityModel extends Model
{
	protected $table="activity_history";
	protected $allowedFields = ["school_id","activity"];
	protected $useTimestamps = true;
	protected $primaryKey = 'id';
	protected $createdField  = 'created_at';
	protected $updatedField  = 'updated_at';

}
