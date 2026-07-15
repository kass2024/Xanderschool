<?php
namespace App\Models;

use CodeIgniter\Model;

class FacultyModel extends Model
{
	protected $table="faculty";
	protected $allowedFields = ["title","abbrev","type","status"];
	protected $useTimestamps = true;
	protected $primaryKey = 'id';
	protected $createdField  = 'created_at';
	protected $updatedField  = 'updated_at';

}
