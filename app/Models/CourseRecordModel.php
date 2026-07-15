<?php
namespace App\Models;

use CodeIgniter\Model;

class CourseRecordModel extends Model
{
	protected $table="course_records";
	protected $allowedFields = ["course","lecturer","class","year","term"];
	// protected $useTimestamps = true;
	protected $primaryKey = 'id';

}
