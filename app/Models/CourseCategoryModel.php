<?php
namespace App\Models;

use CodeIgniter\Model;

class CourseCategoryModel extends Model
{
	protected $table="course_category";
	protected $allowedFields = ["school_id","title","status"];
	protected $useTimestamps = false;
	protected $primaryKey = 'id';
}
