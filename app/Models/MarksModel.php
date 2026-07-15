<?php namespace App\Models;

use CodeIgniter\Model;

class MarksModel extends Model
{
	protected $table = 'marks';
	protected $allowedFields = [
		'student_id',
		'term',
		'examDate',
		'course_id',
		'class_id',
		'mark_type',
		'marks',
		'outof',
		'cat_type',
		'period',
		'created_by',
	];
	protected $useTimestamps = true;
}
