<?php

namespace App\Models;

use CodeIgniter\Model;

class AcademicAiJobModel extends Model
{
	protected $table = 'academic_ai_jobs';
	protected $primaryKey = 'id';
	protected $returnType = 'array';
	protected $allowedFields = [
		'batch_id',
		'school_id',
		'class_id',
		'academic_year',
		'force_flag',
		'status',
		'skip_reason',
		'error_text',
		'result_meta',
		'created_by',
		'created_at',
		'started_at',
		'finished_at',
	];
	protected $useTimestamps = false;
}
