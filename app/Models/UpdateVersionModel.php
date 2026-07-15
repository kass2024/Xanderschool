<?php namespace App\Models;
use CodeIgniter\Model;

class UpdateVersionModel extends Model{
	protected $table = "update_version";
	protected $allowedFields = ["id","school_id","version","type"];
	protected $useTimestamps = false;
	protected $primaryKey = 'id';

}
