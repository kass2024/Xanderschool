<?php
namespace App\Models;

use CodeIgniter\Model;

class DeliberationCriteriaModel extends Model
{
	protected $table="deliberation_criteria";
	protected $allowedFields = ["type","faculty_id","verdict","academic_year","created_by"];
	protected $useTimestamps = true;
	protected $primaryKey = 'id';
	protected $createdField  = 'created_at';
	protected $updatedField  = 'updated_at';

}
