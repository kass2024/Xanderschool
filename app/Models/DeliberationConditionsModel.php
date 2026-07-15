<?php
namespace App\Models;

use CodeIgniter\Model;

class DeliberationConditionsModel extends Model
{
	protected $table="deliberation_conditions";
	protected $allowedFields = ["conditions","value","type","deliberation_id"];
	protected $useTimestamps = false;
	protected $primaryKey = 'id';
	protected $createdField  = 'created_at';
	protected $updatedField  = 'updated_at';

}
