<?php namespace App\Models;
use CodeIgniter\Model;

class IntouchAccount extends Model{
	protected $table = "intouch_accounts";
	protected $allowedFields = ["school_id","username","password"];
	protected $useTimestamps = true;

}
