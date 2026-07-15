<?php
namespace App\Models;

use CodeIgniter\Model;

class ShiftModel extends Model
{
	protected $table="shifts";
	protected $allowedFields = ["title","options","status","school_id","created_by"];
	protected $useTimestamps = true;
	protected $primaryKey = 'id';
}
