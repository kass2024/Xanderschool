<?php
namespace App\Models;

use CodeIgniter\Model;

class DeptModel extends Model
{
	protected $table="departments";
	protected $allowedFields = ["title","code","created_by","updated_by"];
	protected $useTimestamps = true;
	protected $primaryKey = 'id';
	protected $createdField  = 'created_at';
	protected $updatedField  = 'updated_at';

}
