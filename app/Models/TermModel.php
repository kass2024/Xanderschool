<?php
namespace App\Models;

use CodeIgniter\Model;

class TermModel extends Model
{
	protected $table="active_term";
	protected $allowedFields = ["school_id","academic_year","term","sms_usage","use_period"];
	protected $useTimestamps = false;
	protected $primaryKey = 'id';
	public function incrementSMS($id,$count=1){
		$this->set("sms_usage","sms_usage+".$count,FALSE)
			->where("id",$id)
			->update();
	}
}
