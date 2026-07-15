<?php namespace App\Models;
use CodeIgniter\Model;

class DisciplineModel extends Model{
	protected $table = "disciplines";
	protected $allowedFields = ["school_id","type","student_id","active_term","comment","marks","notify_parent","created_by"];
	protected $useTimestamps = true;
	protected $primaryKey = 'id';
	protected $createdField  = 'created_at';
	protected $updatedField  = 'updated_at';

}
