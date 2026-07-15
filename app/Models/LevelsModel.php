<?php
namespace App\Models;

use CodeIgniter\Model;

class LevelsModel extends Model
{
	protected $table="levels";
	protected $allowedFields = ["title","faculty_id","status"];
	protected $useTimestamps = true;
	protected $primaryKey = 'id';
	protected $createdField  = 'created_at';
	protected $updatedField  = 'updated_at';

}
