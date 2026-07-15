<?php
namespace App\Models;

use CodeIgniter\Model;

class CourseModel extends Model
{
	protected $table="courses";
	protected $allowedFields = ["school_id","title","code","category","credit","marks","created_by"];
	protected $useTimestamps = true;
	protected $primaryKey = 'id';
	protected $createdField  = 'created_at';
	protected $updatedField  = 'updated_at';
}
