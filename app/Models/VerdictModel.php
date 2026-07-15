<?php
namespace App\Models;

use CodeIgniter\Model;

class VerdictModel extends Model
{
	protected $table="verdicts";
	protected $allowedFields = ["school_id","title","type","created_by","updated_by"];
	protected $useTimestamps = true;
	protected $primaryKey = 'id';
	protected $createdField  = 'created_at';
	protected $updatedField  = 'updated_at';

}

