<?php
namespace App\Models;

use CodeIgniter\Model;

class FeesRecordModel extends Model
{
	protected $table="fees_records";
	protected $allowedFields = ["student_id","fees_type","amount","fees_id","apiId","refNo","payment_mode","due_date","status","created_by"];
	protected $useTimestamps = true;
	protected $primaryKey = 'id';
	protected $createdField  = 'created_at';
	protected $updatedField  = 'updated_at';

}
