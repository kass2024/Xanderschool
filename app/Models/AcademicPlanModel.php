<?php

namespace App\Models;

use CodeIgniter\Model;

class AcademicPlanModel extends Model
{
	protected $table = 'academic_plans';
	protected $primaryKey = 'id';
	protected $allowedFields = [
		'school_id', 'class_id', 'course_id', 'academic_year',
		'plan_type', 'program_type', 'title', 'week_number', 'term',
		'topic', 'lecturer_id', 'content_html', 'content_json',
		'created_by', 'created_at', 'updated_at',
	];
	protected $useTimestamps = true;
	protected $createdField = 'created_at';
	protected $updatedField = 'updated_at';
}
