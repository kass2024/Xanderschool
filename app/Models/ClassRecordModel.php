<?php
namespace App\Models;

use CodeIgniter\Model;

class ClassRecordModel extends Model
{
	protected $table="class_records";
	protected $allowedFields = ["student","year","class","status"];
	protected $useTimestamps = false;
	protected $primaryKey = 'id';
}
