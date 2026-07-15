<?php
namespace App\Models;

use CodeIgniter\Model;

class ExtraFeesModel extends Model
{
	protected $table="extra_fees";
	protected $allowedFields = ["school_id","title","academic_year","type_id","type","term","amount","created_by"];
	protected $useTimestamps = true;
	protected $primaryKey = 'id';
	protected $createdField  = 'created_at';
	protected $updatedField  = 'updated_at';

}
