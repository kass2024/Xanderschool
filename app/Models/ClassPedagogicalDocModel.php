<?php
namespace App\Models;

use CodeIgniter\Model;

class ClassPedagogicalDocModel extends Model
{
	protected $table = 'class_pedagogical_docs';
	protected $primaryKey = 'id';
	protected $allowedFields = [
		'school_id',
		'class_id',
		'academic_year',
		'doc_type',
		'term',
		'file_name',
		'original_name',
		'created_by',
	];
	protected $useTimestamps = true;
}
