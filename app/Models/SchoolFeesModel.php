<?php
namespace App\Models;

use CodeIgniter\Model;

class SchoolFeesModel extends Model
{
	protected $table="school_fees";
	protected $allowedFields = ["school_id","level","department","amount","term","academic_year","created_by"];
	protected $useTimestamps = true;
	protected $primaryKey = 'id';
	protected $createdField  = 'created_at';
	protected $updatedField  = 'updated_at';

}
