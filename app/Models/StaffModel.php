<?php
namespace App\Models;

use CodeIgniter\Model;

class StaffModel extends Model
{
	protected $table="staffs";
	protected $allowedFields = ["school_id","fname","lname","phone","email","password","status","lastlogin"
    ,"next_login","post","shift_id","country","city","address","photo","card","face_enrolled","created_by","updated_by","updateVersion","reset_exp"];
	protected $useTimestamps = true;
	protected $primaryKey = "id";
	public function checkUser($email,$key="staffs.email"){
		$res = $this->select("staffs.id,staffs.photo,staffs.school_id,fname,lname,staffs.email,password,staffs.status,post
		,p.title as post_title,sc.name as school_name,sc.status as school_status,sc.active_term,at.academic_year,at.term,at.use_period
		,ay.title as academic_year_title")
			->where($key,$email)
			->join("posts p","p.id=staffs.post","inner")
			->join("schools sc","sc.id=staffs.school_id","inner")
			->join("active_term at","sc.active_term=at.id","left")
			->join("academic_year ay","ay.id=at.academic_year","left")
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

	/**
	 * Staff card generation list: one person, one post, or every post (id=0).
	 */
	public function get_staff_for_card_list($id, $isPost = 0)
	{
		$id = (int) $id;
		$isPost = (int) $isPost;
		if ($isPost === 1 && $id === 0) {
			$staffs = $this->get_staff('staffs.id > 0');
			usort($staffs, static function ($a, $b) {
				$postCmp = strcasecmp((string) ($a['post_title'] ?? ''), (string) ($b['post_title'] ?? ''));
				if ($postCmp !== 0) {
					return $postCmp;
				}
				$nameCmp = strcasecmp((string) ($a['fname'] ?? ''), (string) ($b['fname'] ?? ''));
				if ($nameCmp !== 0) {
					return $nameCmp;
				}
				return strcasecmp((string) ($a['lname'] ?? ''), (string) ($b['lname'] ?? ''));
			});
			return $staffs;
		}
		$key = $isPost === 0 ? 'staffs.id' : 'p.id';
		return $this->get_staff($key . '=' . $id);
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

	/**
	 * Any non-locked staff (all roles/posts) may receive an RFID card.
	 */
	public function findForCardOperation(int $staffId, int $schoolId): ?object
	{
		$staffId = (int) $staffId;
		$schoolId = (int) $schoolId;
		if ($staffId <= 0 || $schoolId <= 0) {
			return null;
		}

		return $this->select('id, card, status, post')
			->where('id', $staffId)
			->where('school_id', $schoolId)
			->where('status !=', 0)
			->get(1)->getRow();
	}

	/**
	 * Guaranteed DB write for staff RFID card.
	 */
	public function persistCard(int $staffId, int $schoolId, string $card, ?int $operator = null): bool
	{
		$staffId = (int) $staffId;
		$schoolId = (int) $schoolId;
		$card = strtoupper(trim($card));
		if ($staffId <= 0 || $schoolId <= 0 || $card === '') {
			return false;
		}

		$data = [
			'card' => $card,
			'updated_at' => date('Y-m-d H:i:s'),
		];
		if ($operator !== null) {
			$data['updated_by'] = (int) $operator;
		}

		$db = \Config\Database::connect();
		$updated = $db->table('staffs')
			->where('id', $staffId)
			->where('school_id', $schoolId)
			->update($data);

		return $updated !== false;
	}

	/**
	 * Remove RFID card from a staff member.
	 */
	public function clearCard(int $staffId, int $schoolId, ?int $operator = null): bool
	{
		$staffId = (int) $staffId;
		$schoolId = (int) $schoolId;
		if ($staffId <= 0 || $schoolId <= 0) {
			return false;
		}

		$data = [
			'card' => null,
			'updated_at' => date('Y-m-d H:i:s'),
		];
		if ($operator !== null) {
			$data['updated_by'] = (int) $operator;
		}

		$db = \Config\Database::connect();
		$updated = $db->table('staffs')
			->where('id', $staffId)
			->where('school_id', $schoolId)
			->update($data);

		return $updated !== false;
	}

}
