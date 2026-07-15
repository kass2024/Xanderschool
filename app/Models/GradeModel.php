<?php
namespace App\Models;

use CodeIgniter\Model;

class GradeModel extends Model
{
	protected $table="grade";
	protected $allowedFields = ["faculty_id","school_id","color_title","max_point","min_point","color","created_by"];
	protected $useTimestamps = true;
	protected $primaryKey = 'id';
	protected $createdField  = 'created_at';
	protected $updatedField  = 'updated_at';
}
