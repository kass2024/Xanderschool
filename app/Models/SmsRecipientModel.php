<?php namespace App\Models;
use CodeIgniter\Model;

class SmsRecipientModel extends Model{
	protected $table = "sms_record_recipients";
	protected $allowedFields = ["sms_record_id","receiver_id","phone"
		,"sent_on","status","fail_reason"];
	protected $useTimestamps = false;
}
