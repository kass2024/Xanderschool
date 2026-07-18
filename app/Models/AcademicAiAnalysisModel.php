<?php

namespace App\Models;

use CodeIgniter\Model;

/** Cache Gemini curriculum/chronogram analysis per class + year */
class AcademicAiAnalysisModel extends Model
{
	protected $table = 'academic_ai_analyses';
	protected $primaryKey = 'id';
	protected $allowedFields = [
		'school_id', 'class_id', 'academic_year',
		'program_type', 'analysis_json', 'created_by',
		'created_at', 'updated_at',
	];
	protected $useTimestamps = true;
	protected $createdField = 'created_at';
	protected $updatedField = 'updated_at';
}
