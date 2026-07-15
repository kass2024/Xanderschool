<?php namespace App\Models;
use CodeIgniter\Model;

class RegnumberModel extends Model{
	protected $table="reg_number";
	protected $allowedFields = ["school_id","academic_year","next_number"];
	protected $useTimestamps = false;
	protected $primaryKey = 'id';

}
