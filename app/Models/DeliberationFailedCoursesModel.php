<?php
namespace App\Models;

use CodeIgniter\Model;

class DeliberationFailedCoursesModel extends Model
{
	protected $table="deliberation_failed_courses";
	protected $allowedFields = ["categoryId","course_count","deliberationId"];
	protected $useTimestamps = false;
	protected $primaryKey = 'id';

}
