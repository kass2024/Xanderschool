<?php
namespace App\Models;

use CodeIgniter\Model;

class StaffModel extends Model
{
	protected $table="staffs";
	protected $allowedFields = ["school_id","fname","lname","phone","email","password","status","lastlogin"
		,"next_login","post","shift_id","country","city","address","photo","created_by","updated_by","updateVersion","reset_exp"];
	protected $useTimestamps = true;
	protected $primaryKey = "id";
	public function checkUser($email,$key="staffs.email"){
		$res = $this->select("staffs.id,staffs.photo,staffs.school_id,fname,lname,staffs.email,password,staffs.status,post
		,p.title as post_title,sc.name as school_name,sc.status as school_status,sc.active_term,at.academic_year,at.term,at.use_period")
			->where($key,$email)
			->join("posts p","p.id=staffs.post","inner")
			->join("schools sc","sc.id=staffs.school_id","inner")
			->join("active_term at","sc.active_term=at.id","left")
			->get();
		return $res->getRow();
	}
	public function get_staff($val,$select="staffs.*,p.title as post_title"){
		$res = $this->select($select)
			->join("posts p", "staffs.post=p.id")
			->where($val)
			->where("school_id", $_SESSION['soma_school_id'])
			->get()->getResultArray();
		return $res;
	}
	public function staff_post_phone()
	{
		$data = $this->db->query("SELECT p.id,sum(if(st.phone!='',1,0)) as phone,sum(if(st.phone='',1,0)) as no_phone,p.title from staffs st
inner join posts p on st.post = p.id where st.school_id={$_SESSION['soma_school_id']} group by p.id");
		return $data->getResultArray();
	}
	public function search_staff($hint)
	{
		$data = $this->db->query("SELECT `staffs`.`id`, concat(`staffs`.`id`, ' ',`staffs`.`fname`, ' ', staffs.lname) as text FROM `staffs` WHERE (`staffs`.`fname` LIKE '%{$hint}%' ESCAPE '!' OR  `staffs`.`lname` LIKE '%{$hint}%' ESCAPE '!' OR `staffs`.`email` = '{$hint}')
AND `staffs`.`school_id`={$_SESSION['soma_school_id']}");
		return $data->getResultArray();
	}

}
