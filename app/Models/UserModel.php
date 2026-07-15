<?php namespace App\Models;
use CodeIgniter\Model;

class UserModel extends Model{
	protected $table = "users";
	protected $allowedFields = ["id","names","email","password","privilege","last_login","status"];
	protected $useTimestamps = true;
	public function checkUser($username,$key="email"){
		$res = $this->where($key,$username)
			->get();
		return $res->getRow();
	}

	public function updateLogin($id){
		return $this->where("email",$id)->update(null,array("lastlogin"=>time()));
	}
}
