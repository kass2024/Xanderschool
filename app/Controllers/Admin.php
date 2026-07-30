<?php namespace App\Controllers;

use App\Models\AddressModel;
use App\Models\ClassesModel;
use App\Models\ClassRecordModel;
use App\Models\DeptModel;
use App\Models\ExtraSMSModel;
use App\Models\FacultyModel;
use App\Models\LevelsModel;
use App\Models\PackageModel;
use App\Models\SchoolModel;
use App\Models\SmsModel;
use App\Models\StaffModel;
use App\Models\StudentModel;
use App\Models\UserModel;
use App\Models\PlatformSettingsModel;
use CodeIgniter\HTTP\Response;

class Admin extends BaseController
{
	private $log_status = 'Soma_admin_logged_in';

	public function __construct()
	{
		service('request')->setLocale(isset($_COOKIE['lang']) ? $_COOKIE['lang'] : 'en');
	}

	public function _preset()
	{
		$this->session->set('return_url', current_url());
		if ($this->session->get($this->log_status) === null)
		{
			header('location: ' . base_url('admin/login'));
			die();
		}
		else if ($this->session->get('t_lock_status') !== null)
		{
			header('location: ' . base_url('admin/login'));
			die();
		}
	}

	public function index()
	{
		$this->_preset();
		$data['title']         = 'Admin Dashboard';
		$data['subtitle']      = 'XanderTech Admin Dashboard';
		$smsRecordModel        = new SmsModel();
		$schoolModel           = new SchoolModel();
		$packageModel          = new PackageModel();
		$classRecord           = new ClassRecordModel();
		$staffModel            = new StaffModel();
		$userModel             = new UserModel();
		$smsRecord             = $smsRecordModel->select('sms_records.id')
			->join('sms_record_recipients sr', 'sms_records.id=sr.sms_record_id')
			->where('sr.status', 1)
			->like('sms_records.created_at', date('Y-m-d'))
			->get()->getResultArray();
		$totalSms              = $smsRecordModel->select('sms_records.id')
			->join('sms_record_recipients sr', 'sms_records.id=sr.sms_record_id')
			->where('sr.status', 1)
			->get()->getResultArray();
		$fromschools           = $smsRecordModel->select('sms_records.id')
			->join('sms_record_recipients sr', 'sms_records.id=sr.sms_record_id')
			->where('sr.status', 1)
			->like('sms_records.created_at', date('Y-m-d'))
			->groupBy('sms_records.school_id')
			->get()->getResultArray();
		$activeSchool          = $schoolModel->select('id')
			->where('status', 1)
			->get()->getResultArray();
		$totalSchool           = $schoolModel->select('id')
			->get()->getResultArray();
		$package               = $packageModel->select('id')
			->get()->getResultArray();
		$activeStudent         = $classRecord->select('id')
			->where('year', date('Y'))
			->get()->getResultArray();
		$activeStaffs          = $staffModel->select('id')
			->get()->getResultArray();
		$users                 = $userModel->select('id')->get()->getResultArray();
		$data['sms_array']     = '[' . $this->get_sms_month(1) . ',
							 ' . $this->get_sms_month(2) . ',
							 ' . $this->get_sms_month(3) . ',
							 ' . $this->get_sms_month(4) . ',
							 ' . $this->get_sms_month(5) . ',
							 ' . $this->get_sms_month(6) . ',
							 ' . $this->get_sms_month(7) . ',
							 ' . $this->get_sms_month(8) . ',
							 ' . $this->get_sms_month(9) . ',
							 ' . $this->get_sms_month(10) . ',
							 ' . $this->get_sms_month(11) . ',
							 ' . $this->get_sms_month(12) . ']';
		$data['recentSchoolS'] = $schoolModel->select('schools.id,schools.name,schools.acronym,schools.phone,schools.email,schools.head_master,schools.logo,schools.country')
			->orderBy('schools.id', 'DESC')
			->get()->getResultArray();
		$data['users']         = count($users);
		$data['first']         = $this->get_school_prommoter(0);
		$data['second']        = $this->get_school_prommoter(1);
		$data['third']         = $this->get_school_prommoter(2);
		$data['fourth']        = $this->get_school_prommoter(3);
		$data['fifth']         = $this->get_school_prommoter(4);
		$data['students']      = count($activeStudent);
		$data['staffs']        = count($activeStaffs);
		$data['sms']           = count($smsRecord);
		$data['totalSms']      = count($totalSms);
		$data['package']       = count($package);
		$data['from_schools']  = count($fromschools);
		$data['schools']       = count($activeSchool);
		$data['totalSchools']  = count($totalSchool);
		$data['page']          = 'dashboard';
		$data['content']       = view('admin/dashboard', $data);
		return view('main_admin', $data);
	}

	public function get_sms_month($month)
	{
		$endDate = date("Y-m-t", strtotime(date('Y-' . str_pad($month,2,'0',STR_PAD_LEFT))));
		$this->_preset();
		$postModel = new SmsModel();
		$mnth      = $postModel->select('sms_records.id,sr.id')
			->join('sms_record_recipients sr', 'sr.sms_record_id=sms_records.id', 'LEFT')
			->where('sr.status', 1)
			->where('sms_records.created_at >=', date('Y-' . str_pad($month,2,'0',STR_PAD_LEFT) . '-1'))
			->where('sms_records.created_at <=', $endDate)
			->get()->getResultArray();
		return count($mnth);
	}

	public function get_school_prommoter($data)
	{
		$classRecord = new ClassRecordModel();
		$classes     = $classRecord->select('class_records.id,count(class_records.student) as students,sc.acronym as school')
			->join('classes cl', 'cl.id=class_records.class')
			->where('class_records.year', date('Y'))
			->join('schools sc', 'sc.id=cl.school_id')
			->groupBy('cl.school_id')
			->orderBy('students', 'DESC')
			->get()->getResultArray();
		$i           = 0;
		foreach ($classes as $class)
		{
			if ($data === $i)
			{
				return $class;
				break;
			}
			$i++;
		}
	}

	public function login()
	{
		$data['email'] = $this->session->getFlashdata('email');
		$data['error'] = $this->session->getFlashdata('error');
		return view('login_admin', $data);
	}

	public function login_pro()
	{
		$model      = new UserModel();
		$email      = $this->request->getPost('email');
		$password   = $this->request->getPost('password');
		$validation = \Config\Services::validation();
		$validation->setRule('email', 'email', 'trim|required');
		$validation->setRule('password', 'password', 'required|min_length[6]');
		if ($validation->run() !== false)
		{
			$this->session->setFlashdata('email', $email);
			if ($this->request->getGet('type', true) == 'ajax')
			{
				echo '{"type":"error","msg":"' . $validation->getError() . '"}';
			}
			else
			{
				$this->session->setFlashdata('error', $validation->getError());
				$this->session->setFlashdata('email', $email);
				echo 'errrrer';
				die();
				return redirect()->to(base_url('admin/login'));
			}
		}
		else
		{
			$result = $model->checkUser($email);
			$this->session->setFlashdata('email', $email);
			if ($result !== null)
			{
				if (password_verify($password, $result->password))
				{
					if ($result->status == 1 || $result->status == 2)
					{
						$data = [
							'soma_admin_name'   => $result->names,
							'soma_admin_email'  => $result->email,
							'soma_admin_id'     => $result->id,
							'soma_admin_status' => $result->status,
							$this->log_status   => true,
						];
						$this->session->set($data);
						$model->updateLogin($result->id);
						if ($this->request->getGet('type', true) == 'ajax')
						{
							echo '{"type":"success","msg":"login done"}';
						}
						else
						{
							return redirect()->to(base_url('admin'));
						}
					}
					else
					{
						if ($this->request->getGet('type', true) == 'ajax')
						{
							echo '{"type":"error","msg":"Account not active"}';
						}
						else
						{
							$this->session->setFlashdata('error', 'Account not active');
							return redirect()->to(base_url('admin/login'));
						}
					}
				}
				else
				{
					if ($this->request->getGet('type', true) == 'ajax')
					{
						echo '{"type":"error","msg":"Password not correct"}';
					}
					else
					{
						$this->session->setFlashdata('error', 'Password not correct');
						return redirect()->to(base_url('admin/login'));
					}
				}
			}
			else
			{
				if ($this->request->getGet('type', true) == 'ajax')
				{
					echo '{"type":"error","msg":"User not found"}';
				}
				else
				{
					$this->session->setFlashdata('error', 'User not found');
					return redirect()->to(base_url('admin/login'));
				}
			}
		}
	}

	public function change_password()
	{
		$oldpwd  = $this->request->getPost('current_password');
		$pwd     = $this->request->getPost('password');
		$userMdl = new UserModel();
		$result  = $userMdl->checkUser($this->session->get('soma_admin_id'), 'id');
		if ($result !== null)
		{
			if (password_verify($oldpwd, $result->password))
			{
				if ($result->status === 1 || $result->status === 2)
				{
					$data = [
						'id'       => $this->session->get('soma_admin_id'),
						'password' => password_hash($pwd, PASSWORD_DEFAULT),
						'status'   => 1,
					];
					try
					{
						$userMdl->save($data);
						$this->session->set('soma_admin_status', 1);
						return $this->response->setJSON(['success' => 'Password changed successfully']);
					}
					catch (\Exception $e)
					{
						return $this->response->setJSON(['error' => 'Oops, Change password failed, please try again later']);
					}
				}
				else
				{
					return $this->response->setJSON(['error' => 'Account not active']);
				}
			}
			else
			{
				return $this->response->setJSON(['error' => 'Current Password not correct']);
			}
		}
	}

	public function logout($msg = null)
	{
		session_destroy();
		$this->session->setFlashdata('error', $msg);
		return redirect()->to(base_url());
	}

	public function add_school()
	{
		$this->_preset();
		$addressModel      = new AddressModel();
		$pModel            = new PackageModel();
		$data['title']     = 'Add new school';
		$data['subtitle']  = 'Create new school';
		$data['page']      = 'add_school';
		$data['provinces'] = $addressModel->getProvince();
		$data['packages']  = $pModel->get()->getResultArray();
		$data['content']   = view('admin/add_school', $data);
		return view('main_admin', $data);
	}

	public function schools()
	{
		$this->_preset();
		$schoolMdl        = new SchoolModel();
		$pkgMdl           = new PackageModel();
		$data['title']    = 'view all schools';
		$data['subtitle'] = 'view schools';
		$data['page']     = 'schools';
		$data['packages'] = $pkgMdl->get()->getResultArray();
		$data['schools']  = $schoolMdl->getSchool()->getResultArray();
		$data['content']  = view('admin/schools', $data);
		return view('main_admin', $data);
	}

	public function extra_sms()
	{
		$this->_preset();
		$extraMdl         = new ExtraSMSModel();
		$data['title']    = 'view all extra SMS';
		$data['subtitle'] = 'view extra SMS';
		$data['page']     = 'extra_sms';
		$schoolMdl        = new SchoolModel();
		$data['schools']  = $schoolMdl->select('id,name')->getSchool()->getResultArray();
		$data['sms']      = $extraMdl->select('extra_sms_records.sms_count,extra_sms_records.created_at,sk.name as school_name,u.names as operator')
			->join('schools sk', 'sk.id=extra_sms_records.school_id')
			->join('users u', 'u.id=extra_sms_records.created_by')
			->get()->getResultArray();
		$data['content']  = view('admin/extra_sms', $data);
		return view('main_admin', $data);
	}

	public function packages()
	{
		$this->_preset();
		$pkgMdl           = new PackageModel();
		$data['title']    = 'view all packages';
		$data['subtitle'] = 'view packages';
		$data['page']     = 'packages';
		$data['packages'] = $pkgMdl->get()->getResultArray();
		$data['content']  = view('admin/packages', $data);
		return view('main_admin', $data);
	}

	/** Super-admin: global registration service + platform fees. */
	public function platform_fees()
	{
		$this->_preset();
		$mdl = new PlatformSettingsModel();
		$fees = $mdl->getFees();
		$momo = trim((string) env('REGISTRATION_SERVICE_FEE_MOMO', ''));
		$display = $momo !== '' ? $momo : 'Not set — add REGISTRATION_SERVICE_FEE_MOMO in .env';
		$data = [
			'title' => 'Registration service & platform fees',
			'subtitle' => 'Global fees for online registration (all schools)',
			'page' => 'platform_fees',
			'fees' => $fees,
			'service_momo_display' => $display,
		];
		$data['content'] = view('admin/platform_fees', $data);
		return view('main_admin', $data);
	}

	public function save_platform_fees(): Response
	{
		$this->_preset();
		$service = (int) preg_replace('/\D/', '', (string) $this->request->getPost('service_fee'));
		$platform = (int) preg_replace('/\D/', '', (string) $this->request->getPost('platform_fee'));
		if ($service < 0 || $platform < 0) {
			return $this->response->setJSON(['error' => 'Fees must be 0 or more']);
		}
		$mdl = new PlatformSettingsModel();
		$adminId = (int) ($this->session->get('soma_admin_id') ?: $this->session->get('soma_id') ?: 0);
		$fees = $mdl->saveFees($service, $platform, $adminId);
		return $this->response->setJSON([
			'success' => 'Registration fees saved',
			'fees' => $fees,
		]);
	}

	public function users()
	{
		$this->_preset();
		$userMdl          = new UserModel();
		$data['title']    = 'view all users';
		$data['subtitle'] = 'view users';
		$data['page']     = 'users';
		$data['users']    = $userMdl->get()->getResultArray();
		$data['content']  = view('admin/users', $data);
		return view('main_admin', $data);
	}

	/** Super-admin: Faculty → Department → Level (REB & TVET). */
	public function academic_structure()
	{
		$this->_preset();
		$this->ensureAcademicStructureSchema();
		$data = [];
		$data['title'] = 'Academic structure';
		$data['subtitle'] = 'Manage faculties, departments and levels (REB & TVET)';
		$data['page'] = 'academic_structure';
		$data['structureApiPrefix'] = 'admin';
		$data['content'] = view('pages/academic_structure', $data);
		return view('main_admin', $data);
	}

	private function ensureAcademicStructureSchema()
	{
		$db = \Config\Database::connect();
		$col = $db->query(
			"SELECT COUNT(*) AS c FROM information_schema.COLUMNS
			 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'levels' AND COLUMN_NAME = 'department_id'"
		)->getRow();
		if ($col && (int) $col->c === 0) {
			$db->query('ALTER TABLE levels ADD COLUMN department_id INT NULL DEFAULT NULL AFTER faculty_id');
		}
	}

	public function getAcademicStructure($program = 0): Response
	{
		$this->_preset();
		$this->ensureAcademicStructureSchema();
		$db = \Config\Database::connect();
		$program = (int) $program;

		$facBuilder = $db->table('faculty')->select('id, title, abbrev, type, status')->orderBy('title', 'ASC');
		if ($program === 1 || $program === 2) {
			$facBuilder->where('type', $program);
		}
		$faculties = $facBuilder->get()->getResultArray();

		$sharedLevels = [];
		if ($program === 1) {
			$rows = $db->table('levels')->select('id, title, type, faculty_id, department_id, status')
				->where('type', 1)
				->orderBy('title', 'ASC')
				->get()->getResultArray();
			$byId = [];
			foreach ($rows as $lv) {
				$title = strtolower(trim((string) $lv['title']));
				if (preg_match('/\b(senior|ordinary|primary|nursery|year|s1|s2|s3|s4|s5|s6)\b/', $title)) {
					continue;
				}
				if (!preg_match('/\blevel\s*[1-5]\b|^l\s*[1-5]$/', $title)) {
					continue;
				}
				$byId[$lv['id']] = $lv;
			}
			$sharedLevels = array_values($byId);
			usort($sharedLevels, function ($a, $b) {
				preg_match('/([1-5])/', (string) $a['title'], $ma);
				preg_match('/([1-5])/', (string) $b['title'], $mb);
				$na = isset($ma[1]) ? (int) $ma[1] : 9;
				$nb = isset($mb[1]) ? (int) $mb[1] : 9;
				if ($na !== $nb) {
					return $na < $nb ? -1 : 1;
				}
				return strcasecmp((string) $a['title'], (string) $b['title']);
			});
		}

		$tree = [];
		foreach ($faculties as $fac) {
			$depts = $db->table('departments')->select('id, title, code, faculty_id')
				->where('faculty_id', $fac['id'])
				->orderBy('title', 'ASC')
				->get()->getResultArray();
			$deptNodes = [];
			foreach ($depts as $dept) {
				$deptNodes[] = [
					'id' => (int) $dept['id'],
					'title' => $dept['title'],
					'code' => $dept['code'],
				];
			}
			$facLevels = [];
			if ((int) $fac['type'] === 2) {
				$facLevels = $db->table('levels')->select('id, title, type, faculty_id, department_id, status')
					->where('faculty_id', $fac['id'])
					->orderBy('title', 'ASC')
					->get()->getResultArray();
			}
			$tree[] = [
				'id' => (int) $fac['id'],
				'title' => $fac['title'],
				'abbrev' => $fac['abbrev'],
				'type' => (int) $fac['type'],
				'status' => (int) $fac['status'],
				'departments' => $deptNodes,
				'levels' => $facLevels,
			];
		}
		return $this->response->setJSON([
			'success' => 1,
			'program' => $program,
			'shared_levels' => $sharedLevels,
			'levels_mode' => $program === 1 ? 'program' : 'faculty',
			'faculties' => $tree,
		]);
	}

	public function saveAcademicFaculty(): Response
	{
		$this->_preset();
		$fMdl = new FacultyModel();
		$id = (int) $this->request->getPost('id');
		$title = trim((string) $this->request->getPost('title'));
		$abbrev = trim((string) $this->request->getPost('abbrev'));
		$type = (int) $this->request->getPost('type');
		if ($title === '') {
			return $this->response->setJSON(['error' => 'Faculty name is required']);
		}
		if ($type !== 1 && $type !== 2) {
			return $this->response->setJSON(['error' => 'Type must be REB (2) or TVET (1)']);
		}
		$row = [
			'title' => $title,
			'abbrev' => $abbrev !== '' ? $abbrev : substr($title, 0, 20),
			'type' => $type,
			'status' => 0,
		];
		if ($id > 0) {
			$row['id'] = $id;
		}
		try {
			$fMdl->save($row);
			return $this->response->setJSON(['success' => 'Faculty saved', 'id' => $id > 0 ? $id : $fMdl->getInsertID()]);
		} catch (\Throwable $e) {
			return $this->response->setJSON(['error' => $e->getMessage()]);
		}
	}

	public function saveAcademicDepartment(): Response
	{
		$this->_preset();
		$dMdl = new DeptModel();
		$id = (int) $this->request->getPost('id');
		$facultyId = (int) $this->request->getPost('faculty_id');
		$title = trim((string) $this->request->getPost('title'));
		$code = trim((string) $this->request->getPost('code'));
		if ($facultyId <= 0) {
			return $this->response->setJSON(['error' => 'Select a faculty first']);
		}
		if ($title === '') {
			return $this->response->setJSON(['error' => 'Department name is required']);
		}
		if ($code === '') {
			$code = strtoupper(substr(preg_replace('/\s+/', '', $title), 0, 10));
		}
		$adminId = (int) $this->session->get('soma_admin_id');
		$row = [
			'title' => $title,
			'code' => $code,
			'faculty_id' => $facultyId,
			'created_by' => $adminId,
			'updated_by' => $adminId,
		];
		if ($id > 0) {
			$row['id'] = $id;
		}
		try {
			$dMdl->save($row);
			return $this->response->setJSON(['success' => 'Department saved', 'id' => $id > 0 ? $id : $dMdl->getInsertID()]);
		} catch (\Throwable $e) {
			return $this->response->setJSON(['error' => $e->getMessage()]);
		}
	}

	public function saveAcademicLevel(): Response
	{
		$this->_preset();
		$this->ensureAcademicStructureSchema();
		$lMdl = new LevelsModel();
		$fMdl = new FacultyModel();
		$id = (int) $this->request->getPost('id');
		$facultyId = (int) $this->request->getPost('faculty_id');
		$title = trim((string) $this->request->getPost('title'));
		if ($title === '') {
			return $this->response->setJSON(['error' => 'Level name is required']);
		}

		$facType = 2;
		if ($facultyId > 0) {
			$fac = $fMdl->select('id,type')->where('id', $facultyId)->get(1)->getRow();
			if (!$fac) {
				return $this->response->setJSON(['error' => 'Faculty not found']);
			}
			$facType = (int) $fac->type;
		} else {
			$facType = 1;
		}

		if ($facType === 1 && preg_match('/\b(senior|s4|s5|s6)\b/i', $title)) {
			return $this->response->setJSON(['error' => 'TVET uses Level 1–5 only (not Senior)']);
		}
		if ($facType === 2 && $facultyId <= 0) {
			return $this->response->setJSON(['error' => 'Select a faculty first — REB levels are shared by all departments under that faculty']);
		}

		$row = [
			'title' => $title,
			'faculty_id' => $facType === 1 ? ($facultyId > 0 ? $facultyId : 0) : $facultyId,
			'department_id' => null,
			'type' => $facType,
			'status' => 1,
		];
		if ($id > 0) {
			$row['id'] = $id;
		}
		try {
			$lMdl->save($row);
			return $this->response->setJSON(['success' => 'Level saved', 'id' => $id > 0 ? $id : $lMdl->getInsertID()]);
		} catch (\Throwable $e) {
			return $this->response->setJSON(['error' => $e->getMessage()]);
		}
	}

	public function deleteAcademicNode(): Response
	{
		$this->_preset();
		$kind = strtolower(trim((string) $this->request->getPost('kind')));
		$id = (int) $this->request->getPost('id');
		if ($id <= 0 || !in_array($kind, ['faculty', 'department', 'level'], true)) {
			return $this->response->setJSON(['error' => 'Invalid request']);
		}
		$db = \Config\Database::connect();
		try {
			if ($kind === 'faculty') {
				$used = $db->table('departments')->where('faculty_id', $id)->countAllResults();
				if ($used > 0) {
					return $this->response->setJSON(['error' => 'Remove departments under this faculty first']);
				}
				$db->table('faculty')->where('id', $id)->delete();
			} elseif ($kind === 'department') {
				$used = $db->table('classes')->where('department', $id)->countAllResults();
				if ($used > 0) {
					return $this->response->setJSON(['error' => 'Department is used by classes — cannot delete']);
				}
				$db->table('departments')->where('id', $id)->delete();
			} else {
				$used = $db->table('classes')->where('level', $id)->countAllResults();
				if ($used > 0) {
					return $this->response->setJSON(['error' => 'Level is used by classes — cannot delete']);
				}
				$db->table('levels')->where('id', $id)->delete();
			}
			return $this->response->setJSON(['success' => 'Deleted']);
		} catch (\Throwable $e) {
			return $this->response->setJSON(['error' => $e->getMessage()]);
		}
	}

	public function get_package($json = false)
	{
		$pModel = new PackageModel();
		$pkg    = $pModel->get()->getResultArray();

		echo '<option selected disabled>Select packages</option>';
		foreach ($pkg as $item)
		{
			echo "<option value='{$item['id']}'>{$item['title']}</option>";
		}
	}

	public function manipulate_school($id = null)
	{
		$this->_preset();
		$name       = $this->request->getPost('name');
		$acronym    = $this->request->getPost('acronym');
		$phone      = $this->request->getPost('phone');
		$email      = $this->request->getPost('email');
		$headmaster = $this->request->getPost('headmaster');
		$website    = $this->request->getPost('web');
		$package    = $this->request->getPost('package');
		$country    = ucfirst($this->request->getPost('country'));
		$address    = ucfirst($this->request->getPost('address'));

		try
		{
			$schoolMdl = new SchoolModel();
			$data      = [
				'name'        => $name,
				'acronym'     => $acronym,
				'phone'       => $phone,
				'email'       => $email,
				'head_master' => $headmaster,
				'website'     => $website,
				'package'     => $package,
				'country'     => $country,
				'address'     => $address,
				'status'      => 1,
				'created_by'  => $this->session->get('soma_admin_id'),
				'website'     => $website,
			];
			$school_id = $schoolMdl->insert($data);
			//CREATE DEFAULT STAFF ACCOUNT
			$staffMdl         = new StaffModel();
			$head_names       = explode(' ', $headmaster, 2);
			$fname            = $head_names[0];
			$lname            = isset($head_names[1]) ? $head_names[1] : '';
			$default_password = $this->random_password();
			$staffData        = [
				'school_id'  => $school_id,
				'fname'      => $fname,
				'lname'      => $lname,
				'phone'      => $phone,
				'password'   => password_hash($default_password, PASSWORD_DEFAULT),
				'status'     => 2,
				'email'      => $email,
				'post'       => 1,
				'created_by' => $this->session->get('soma_admin_id'),
			];
			$staffMdl->save($staffData);
			//send notification EMAIL and SMS
			$msg  = "Dear $fname, $name is on XanderTech SmartSMS, you can now login, \nEmail: "
				. $email . "\nPassword: " . $default_password . "\n Thank you";
			$msg2 = "Dear $fname, $name is on XanderTech SmartSMS, you can now login, \nEmail: "
				. $email . "\nPassword: *******\n Thank you";
//			if ($this->_send_sms($phone, $msg, $result, 1))
            if ($this->sendSMS($phone, $msg, $result))
			{
				//save sent sms
				$smsMdl = new SmsModel();
				$smsMdl->save([
					'school_id'      => $school_id,
					'active_term'    => 0,
					'content'        => $msg2,
					'recipient'      => $phone,
					'recipient_type' => 1,
				]);
			}
			$data     = [
				'name'             => $name,
				'email'            => $email,
				'fname'            => $fname,
				'lname'            => $lname,
				'default_password' => $default_password,
			];
			$html_msg = view('emails/school_creation', $data);
			$sent = $this->_send_email($email, 'Welcome to XanderTech SmartSMS', $html_msg);
			if (! $sent) {
				return $this->response->setJSON([
					'success' => 'School saved!',
					'warning' => 'School created but welcome email could not be sent. Check SMTP settings in .env.',
				]);
			}
			return $this->response->setJSON(['success' => 'School saved! Welcome email sent.']);
		}
		catch (\Exception $e)
		{
			return $this->response->setJSON(['error' => 'Error occurred: ' . $e->getMessage()]);
		}
	}

	public function manipulate_package($id = null)
	{
		$this->_preset();
		$id   = $this->request->getPost('fId');
		$name = $this->request->getPost('name');
		$sms  = $this->request->getPost('sms');
		if ($id === '')
		{
			$data = [
				'title'     => $name,
				'sms_limit' => $sms,
			];
		}
		else
		{
			$data = [
				'id'        => $id,
				'title'     => $name,
				'sms_limit' => $sms,
			];
		}
		try
		{
			$pModel = new PackageModel();
			$pModel->save($data);
			return $this->response->setJSON(['success' => 'Package saved']);
		}
		catch (\Exception $e)
		{
			return $this->response->setJSON(['error' => 'Error occurred: ' . $e->getCode()]);
		}
	}
	public function manipulate_extra_sms($id = null)
	{
		$this->_preset();
		$id   = $this->request->getPost('sid');
		$sms  = $this->request->getPost('sms');
		$data = [
			'school_id'  => $id,
			'sms_count'  => $sms,
			'created_by' => $this->session->get('soma_admin_id'),
		];
		try
		{
			$schoolModel = new SchoolModel();
			if ($schoolModel->where('id', $id)->increment('extra_sms', $sms))
			{
				$smsModel = new ExtraSMSModel();
				$smsModel->save($data);
			}
			else
			{
				return $this->response->setJSON(['error' => 'Error occurred: Please try again later']);
			}
			return $this->response->setJSON(['success' => 'SMS Given']);
		}
		catch (\Exception $e)
		{
			return $this->response->setJSON(['error' => 'Error occurred: ' . $e->getMessage()]);
		}
	}

	public function get_single_package($id)
	{
		$this->_preset();
		$pModel = new PackageModel();
		$pack   = $pModel->select('id,title,sms_limit')
			->where('id', $id)->get()->getRowArray();
		echo json_encode($pack);
	}

	public function get_school_package($id)
	{
		$this->_preset();
		$sklPackage = new SchoolModel();
		$pack       = $sklPackage->select('id,package')
			->where('id', $id)->get()->getRowArray();
		echo json_encode($pack);
	}

	public function changeSchoolPackge()
	{
		$this->_preset();
		$sklPackage = new SchoolModel();
		$pack       = $this->request->getPost('package');
		$id         = $this->request->getPost('fId');
		$data       = [
			'id'      => $id,
			'package' => $pack,
		];
		try
		{
			$sklPackage->save($data);
			return $this->response->setJSON(['success' => 'Package Changed']);
		}
		catch (\Exception $e)
		{
			return $this->response->setJSON(['error' => 'Error occurred: ' . $e->getCode()]);
		}
	}

	public function manipulate_user($id = null)
	{
		$this->_preset();
		$name             = $this->request->getPost('name');
		$email            = $this->request->getPost('email');
		$default_password = $this->random_password();
		try
		{
			$userMdl = new UserModel();
			$userMdl->save([
				'names'     => $name,
				'email'     => $email,
				'password'  => password_hash($default_password, PASSWORD_DEFAULT),
				'status'    => 2,
				'privilege' => 1,
			]);
			$data     = [
				'name'             => $name,
				'email'            => $email,
				'default_password' => $default_password,
			];
			$html_msg = view('emails/user_creation', $data);
			$sent = $this->_send_email($email, 'Welcome to XanderTech SmartSMS', $html_msg);
			if (! $sent) {
				return $this->response->setJSON([
					'success' => 'User saved',
					'warning' => 'User created but welcome email could not be sent. Check SMTP settings in .env.',
				]);
			}
			return $this->response->setJSON(['success' => 'User saved. Welcome email sent.']);
		}
		catch (\Exception $e)
		{
			return $this->response->setJSON(['error' => 'Error occurred: ' . $e->getCode()]);
		}
	}

	public function delete_package()
	{
		$this->_preset();
		$id = $this->request->getPost('data');
		try
		{
			$pModel    = new PackageModel();
			$schoolMdl = new SchoolModel();
			$res       = $schoolMdl->where('package', $id)->get(1)->getRowArray();
			if (is_array($res))
			{
				//package can not be deleted because it is used
				return $this->response->setJSON(['error' => 'Oops, Package can not be deleted because it is used on school']);
			}
			$pModel->delete($id);
			return $this->response->setJSON(['success' => 'Package deleted']);
		}
		catch (\Exception $e)
		{
			return $this->response->setJSON(['error' => 'Error occurred: ' . $e->getMessage()]);
		}
	}

	public function delete_user()
	{
		$this->_preset();
		$id = $this->request->getPost('data');
		try
		{
			$userMdl   = new UserModel();
			$schoolMdl = new SchoolModel();
			$res       = $schoolMdl->where('created_by', $id)->get(1)->getRowArray();
			if (is_array($res))
			{
				//package can not be deleted because it is used
				return $this->response->setJSON(['error' => 'Oops, User can not be deleted because He is needed by the system']);
			}
			$userMdl->delete($id);
			return $this->response->setJSON(['success' => 'User deleted']);
		}
		catch (\Exception $e)
		{
			return $this->response->setJSON(['error' => 'Error occurred: ' . $e->getMessage()]);
		}
	}

	public function delete_school()
	{
		$this->_preset();
		$id = $this->request->getPost('data');
		try
		{
			$schoolMdl = new SchoolModel();

			//delete school
			$schoolMdl->delete($id);
			//delete staff
			$staffMdl = new StaffModel();
			$staffMdl->where('school_id', $id)->delete();

			//delete student
			$studentMdl = new StudentModel();
			$studentMdl->where('school_id', $id)->delete();

			return $this->response->setJSON(['success' => 'School deleted']);
		}
		catch (\Exception $e)
		{
			return $this->response->setJSON(['error' => 'Error occurred: ' . $e->getMessage()]);
		}
	}

	public function testSMS()
	{
		if ($this->sendSMS('250780699435', 'Test by XanderTech on ' . date('Y-m-d H:i:s') . ' from SmartSMS', $result))
		{
			echo 'SMS SENT <br>CODE: ' . $result['code'] . '<br>CONTENT: ' . $result['content'];
		}
		else
		{
			echo 'Oops, SMS NOT SENT, code: ' . $result['code'] . '<br>CONTENT: ' . $result['content'];
			;
		}
	}

	public function testEmail()
	{
		if ($this->_send_email('methode@visaconsultantcanada.com', 'Test from XanderTech SmartSMS', 'SMTP test on ' . date('Y-m-d H:i:s') . ' from XanderTech SmartSMS'))
		{
			echo 'EMAIL SENT';
		}
		else
		{
			echo 'Oops, EMAIL NOT SENT';
		}
	}

	function test()
	{
		$default_password = $this->random_password();
		echo $default_password;
	}
}
