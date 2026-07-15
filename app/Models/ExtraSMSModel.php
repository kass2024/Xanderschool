<?php namespace App\Models;
use CodeIgniter\Model;

class ExtraSMSModel extends Model{
	protected $table = "extra_sms_records";
	protected $allowedFields = ["id","school_id","sms_count","created_by"];
	protected $useTimestamps = true;
}
