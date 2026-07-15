<?php namespace App\Models;
use CodeIgniter\Model;

class TransportRecordModel extends Model{
	protected $table = "transport_records";
	protected $allowedFields = ["student_id","bus","route","way","price","created_by"];
	protected $useTimestamps = true;
	protected $primaryKey = 'id';
	protected $createdField  = 'created_at';
	protected $updatedField  = 'updated_at';

}
