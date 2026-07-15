<?php namespace App\Models;
use CodeIgniter\Model;

class ActiveTermModel extends Model{
	protected $table = "active_term";
	protected $allowedFields = ["id","school_id","academic_year","term","sms_usage","use_period","created_by"];
	protected $useTimestamps = true;
	protected $primaryKey = 'id';

}
