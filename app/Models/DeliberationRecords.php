<?php
namespace App\Models;

use CodeIgniter\Model;

class DeliberationRecords extends Model
{
	protected $table="deliberation_records";
	protected $allowedFields = ["studentId","oldClass","newClass","deliberationId","decision","decisionType"
		,"nextAcademicYear","operator",'status'];
	protected $useTimestamps = true;
}
