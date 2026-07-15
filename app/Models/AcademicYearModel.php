<?php namespace App\Models;
use CodeIgniter\Model;

class AcademicYearModel extends Model{
	protected $table = "academic_year";
	protected $allowedFields = ["id","school_id","title"];
	protected $useTimestamps = true;
	protected $primaryKey = 'id';

}
