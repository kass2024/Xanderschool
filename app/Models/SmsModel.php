<?php namespace App\Models;
use CodeIgniter\Model;

class SmsModel extends Model{
	protected $table = "sms_records";
	protected $allowedFields = ["school_id","active_term","content"
		,"recipient_type","subject","director_send"];
	protected $useTimestamps = true;

}
