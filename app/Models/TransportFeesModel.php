<?php namespace App\Models;
use CodeIgniter\Model;

class TransportFeesModel extends Model{
	protected $table = "transport_fees";
	protected $allowedFields = ["student_id","paid_amount","created_by","updated_by"];
	protected $useTimestamps = true;
	protected $primaryKey = 'id';
	protected $createdField  = 'created_at';
	protected $updatedField  = 'updated_at';

}
