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
use App\Models\PostMenuClearanceModel;
use App\Models\PostsModel;
use App\Models\MasterCentralPostModel;
use App\Models\SchoolHierarchyModel;
use App\Services\SchoolHierarchyService;
use CodeIgniter\HTTP\Response;
use Config\MenuClearance;

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
		$data['school']    = null;
		$data['provinces'] = $addressModel->getProvince();
		$data['packages']  = $pModel->get()->getResultArray();
		$data['content']   = view('admin/add_school', $data);
		return view('main_admin', $data);
	}

	public function edit_school($id = null)
	{
		$this->_preset();
		$id = (int) $id;
		if ($id < 1) {
			return redirect()->to(base_url('schools'));
		}
		$schoolMdl = new SchoolModel();
		$school    = $schoolMdl->getSchool(['schools.id' => $id])->getRowArray();
		if (! $school) {
			return redirect()->to(base_url('schools'));
		}
		$addressModel      = new AddressModel();
		$pModel            = new PackageModel();
		$data['title']     = 'Edit school';
		$data['subtitle']  = 'Update school details';
		$data['page']      = 'add_school';
		$data['school']    = $school;
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
		$hierMdl          = new SchoolHierarchyModel();
		$hierMdl->ensureSchema();
		$byId = [];
		foreach ($hierMdl->allSchoolsWithHierarchy() as $h) {
			$byId[(int) $h['id']] = $h;
		}
		$schools = $schoolMdl->getSchool()->getResultArray();
		foreach ($schools as &$s) {
			$hid = (int) ($s['id'] ?? 0);
			if (isset($byId[$hid])) {
				$s['is_master'] = (int) ($byId[$hid]['is_master'] ?? 0);
				$s['master_school_id'] = (int) ($byId[$hid]['master_school_id'] ?? 0);
				$s['master_name'] = $byId[$hid]['master_name'] ?? '';
			} else {
				$s['is_master'] = 0;
				$s['master_school_id'] = 0;
				$s['master_name'] = '';
			}
		}
		unset($s);
		$data['title']    = 'view all schools';
		$data['subtitle'] = 'view schools';
		$data['page']     = 'schools';
		$data['packages'] = $pkgMdl->get()->getResultArray();
		$data['schools']  = $schools;
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
		$data = [
			'title' => 'Registration service & platform fees',
			'subtitle' => 'Global fees for online registration (all schools)',
			'page' => 'platform_fees',
			'fees' => $fees,
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

	/** Super-admin: assign school-dashboard menus per staff post. */
	public function level_clearance()
	{
		$this->_preset();
		$clearanceMdl = new PostMenuClearanceModel();
		$clearanceMdl->ensureSchema();
		$centralMdl = new MasterCentralPostModel();
		$centralMdl->ensureSchema();
		$postsMdl = new PostsModel();
		$posts = $postsMdl->orderBy('id', 'ASC')->findAll();
		if (!is_array($posts)) {
			$posts = [];
		}

		$clearanceByPost = [];
		$customByPost = [];
		foreach ($posts as $post) {
			$pid = (int) ($post['id'] ?? 0);
			$clearanceByPost[$pid] = $clearanceMdl->allowedKeysForPost($pid);
			$customByPost[$pid] = $clearanceMdl->hasCustomRow($pid);
		}

		$data = [
			'title' => 'Level clearance',
			'subtitle' => 'Assign school dashboard menus per staff post',
			'page' => 'level_clearance',
			'posts' => $posts,
			'menuTree' => MenuClearance::tree(),
			'fullAccessPosts' => MenuClearance::FULL_ACCESS_POSTS,
			'clearanceByPost' => $clearanceByPost,
			'customByPost' => $customByPost,
			'defaultsByPost' => [],
			'masterCentralPosts' => $centralMdl->centralPostIds(),
			'masterCentralDefaults' => MasterCentralPostModel::defaultPostIds(),
		];
		foreach ($posts as $post) {
			$pid = (int) ($post['id'] ?? 0);
			$data['defaultsByPost'][$pid] = PostMenuClearanceModel::defaultKeysForPost($pid);
		}
		$data['content'] = view('admin/level_clearance', $data);
		return view('main_admin', $data);
	}

	public function save_master_central_posts(): Response
	{
		$this->_preset();
		$centralMdl = new MasterCentralPostModel();
		$centralMdl->ensureSchema();
		$raw = $this->request->getPost('post_ids');
		$postIds = [];
		if (is_string($raw)) {
			$decoded = json_decode($raw, true);
			if (is_array($decoded)) {
				$postIds = $decoded;
			}
		} elseif (is_array($raw)) {
			$postIds = $raw;
		}
		$adminId = (int) ($this->session->get('soma_admin_id') ?: $this->session->get('soma_id') ?: 0);
		$saved = $centralMdl->saveCentralPostIds($postIds, $adminId);
		return $this->response->setJSON([
			'success' => 'Master school central posts saved',
			'post_ids' => $saved,
		]);
	}

	/** Super-admin: master school groups and child school allocation. */
	public function school_groups()
	{
		$this->_preset();
		$mdl = new SchoolHierarchyModel();
		$mdl->ensureSchema();
		$schools = $mdl->allSchoolsWithHierarchy();
		$masters = array_values(array_filter($schools, static function ($s) {
			return !empty($s['is_master']);
		}));
		$data = [
			'title' => 'School groups',
			'subtitle' => 'Master schools and child allocation',
			'page' => 'school_groups',
			'schools' => $schools,
			'masters' => $masters,
			'centralPosts' => (new MasterCentralPostModel())->centralPostIds(),
		];
		$data['content'] = view('admin/school_groups', $data);
		return view('main_admin', $data);
	}

	public function save_school_group(): Response
	{
		$this->_preset();
		$masterId = (int) $this->request->getPost('master_id');
		$childRaw = $this->request->getPost('child_ids');
		$childIds = [];
		if (is_string($childRaw)) {
			$decoded = json_decode($childRaw, true);
			if (is_array($decoded)) {
				$childIds = $decoded;
			}
		} elseif (is_array($childRaw)) {
			$childIds = $childRaw;
		}
		if ($masterId < 1) {
			return $this->response->setJSON(['error' => 'Select a master school']);
		}
		$mdl = new SchoolHierarchyModel();
		$result = $mdl->assignChildren($masterId, $childIds);
		if (empty($result['ok'])) {
			return $this->response->setJSON([
				'error' => $result['error'] ?? 'Could not save school group',
				'conflicts' => $result['conflicts'] ?? [],
			]);
		}
		return $this->response->setJSON([
			'success' => 'School group saved',
			'master_id' => $masterId,
			'children' => count($childIds),
		]);
	}

	public function seed_wisdom_master(): Response
	{
		$this->_preset();
		$svc = new SchoolHierarchyService();
		$result = $svc->seedWisdomMasterGroup();
		if ($result['master_id'] < 1) {
			return $this->response->setJSON(['error' => 'WISDOM SCHOOL RWANDA not found in schools table']);
		}
		return $this->response->setJSON([
			'success' => 'WISDOM SCHOOL RWANDA set as master for ' . $result['children'] . ' child school(s)',
			'result' => $result,
		]);
	}

	public function export_wisdom_credentials()
	{
		$this->_preset();
		$masterId = (int) ($this->request->getGet('master_id') ?: 0);
		$svc = new SchoolHierarchyService();
		if ($masterId < 1) {
			$result = $svc->seedWisdomMasterGroup();
			$masterId = (int) ($result['master_id'] ?? 0);
		}
		if ($masterId < 1) {
			return redirect()->to(base_url('admin/school_groups'))->with('error', 'No master school found');
		}
		$file = $svc->exportChildCredentialsTxt($masterId);
		return $this->response->download($file, null)->setFileName(basename($file));
	}

	public function save_level_clearance(): Response
	{
		$this->_preset();
		$postId = (int) $this->request->getPost('post_id');
		$action = trim((string) $this->request->getPost('action'));
		$mdl = new PostMenuClearanceModel();
		$mdl->ensureSchema();

		if ($postId < 1) {
			return $this->response->setJSON(['error' => 'Invalid post']);
		}
		if (MenuClearance::isFullAccessPost($postId)) {
			return $this->response->setJSON([
				'error' => 'This post always has full access and cannot be restricted',
				'menus' => MenuClearance::allKeys(),
				'locked' => true,
			]);
		}

		$adminId = (int) ($this->session->get('soma_admin_id') ?: $this->session->get('soma_id') ?: 0);

		if ($action === 'reset') {
			$mdl->resetToDefaults($postId);
			$menus = PostMenuClearanceModel::defaultKeysForPost($postId);
			return $this->response->setJSON([
				'success' => 'Reset to defaults',
				'menus' => $menus,
				'custom' => false,
			]);
		}

		$menusRaw = $this->request->getPost('menus');
		$menus = [];
		if (is_string($menusRaw)) {
			$decoded = json_decode($menusRaw, true);
			if (is_array($decoded)) {
				$menus = $decoded;
			}
		} elseif (is_array($menusRaw)) {
			$menus = $menusRaw;
		}

		$saved = $mdl->saveForPost($postId, $menus, $adminId);
		return $this->response->setJSON([
			'success' => 'Level clearance saved',
			'menus' => $saved,
			'custom' => true,
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

		if ($program === FacultyModel::TYPE_SPECIAL) {
			(new FacultyModel())->ensureSpecialNursingAnp();
		}

		$facBuilder = $db->table('faculty')->select('id, title, abbrev, type, status')->orderBy('title', 'ASC');
		if (in_array($program, [FacultyModel::TYPE_TVET, FacultyModel::TYPE_REB, FacultyModel::TYPE_SPECIAL], true)) {
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
			if (in_array((int) $fac['type'], [FacultyModel::TYPE_REB, FacultyModel::TYPE_SPECIAL], true)) {
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
		if (!in_array($type, [FacultyModel::TYPE_TVET, FacultyModel::TYPE_REB, FacultyModel::TYPE_SPECIAL], true)) {
			return $this->response->setJSON(['error' => 'Type must be TVET (1), REB (2), or Special (3)']);
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
		if ($facType !== FacultyModel::TYPE_TVET && $facultyId <= 0) {
			return $this->response->setJSON(['error' => 'Select a faculty first — levels are shared by all departments under that faculty']);
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
		$country    = ucfirst((string) $this->request->getPost('country'));
		$address    = ucfirst((string) $this->request->getPost('address'));
		$fId        = $this->request->getPost('fId');
		$schoolId   = ($fId !== null && $fId !== '') ? (int) $fId : ((int) $id ?: 0);

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
			];

			// Update existing school
			if ($schoolId > 0) {
				$existing = $schoolMdl->getSchool(['schools.id' => $schoolId])->getRowArray();
				if (! $existing) {
					return $this->response->setJSON(['error' => 'School not found.']);
				}
				$schoolMdl->update($schoolId, $data);
				return $this->response->setJSON(['success' => 'School updated successfully.']);
			}

			// Create new school
			$data['status']     = 1;
			$data['created_by'] = $this->session->get('soma_admin_id');
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

	/**
	 * Reset headmaster password and share login via SMS and/or email (admin schools list).
	 */
	public function share_school_access()
	{
		$this->_preset();
		$schoolId = (int) ($this->request->getPost('school_id') ?? 0);
		$channel  = strtolower(trim((string) ($this->request->getPost('channel') ?? 'both')));
		if (! in_array($channel, ['sms', 'email', 'both'], true)) {
			return $this->response->setJSON(['error' => 'Invalid channel. Use sms, email, or both.']);
		}
		if ($schoolId < 1) {
			return $this->response->setJSON(['error' => 'School not specified.']);
		}

		$schoolMdl = new SchoolModel();
		$school    = $schoolMdl->select('id,name,acronym,phone,email,head_master,status')
			->where('id', $schoolId)
			->first();
		if (! $school) {
			return $this->response->setJSON(['error' => 'School not found.']);
		}

		$phone = trim((string) ($school['phone'] ?? ''));
		$email = trim((string) ($school['email'] ?? ''));
		$headMaster = trim((string) ($school['head_master'] ?? ''));
		if ($headMaster === '') {
			return $this->response->setJSON(['error' => 'This school has no headmaster name on record.']);
		}

		$staffMdl = new StaffModel();
		$staff    = $staffMdl->where('school_id', $schoolId)
			->where('post', 1)
			->whereIn('status', [1, 2])
			->orderBy('id', 'ASC')
			->first();

		$headNames = preg_split('/\s+/', $headMaster, 2);
		$fname     = $headNames[0] ?? 'Head';
		$lname     = $headNames[1] ?? 'Master';
		$defaultPassword = $this->random_password();

		try {
			if ($staff) {
				$staffId = (int) $staff['id'];
				$staffMdl->update($staffId, [
					'fname'     => $fname,
					'lname'     => $lname,
					'phone'     => $phone !== '' ? $phone : ($staff['phone'] ?? ''),
					'email'     => $email !== '' ? $email : ($staff['email'] ?? ''),
					'password'  => password_hash($defaultPassword, PASSWORD_DEFAULT),
					'reset_exp' => 0,
					'status'    => 2,
				]);
				$phone = $phone !== '' ? $phone : trim((string) ($staff['phone'] ?? ''));
				$email = $email !== '' ? $email : trim((string) ($staff['email'] ?? ''));
			} else {
				$staffId = (int) $staffMdl->insert([
					'school_id'  => $schoolId,
					'fname'      => $fname,
					'lname'      => $lname,
					'phone'      => $phone,
					'email'      => $email,
					'password'   => password_hash($defaultPassword, PASSWORD_DEFAULT),
					'status'     => 2,
					'post'       => 1,
					'created_by' => $this->session->get('soma_admin_id'),
				]);
			}
		} catch (\Exception $e) {
			return $this->response->setJSON(['error' => 'Could not reset password: ' . $e->getMessage()]);
		}

		$name    = trim($fname . ' ' . strtoupper(substr($lname, 0, 1)) . '.');
		$loginUser = $email !== '' ? $email : $phone;
		$smsPack = $this->_staffCredentialSms($name, $loginUser, $defaultPassword, true);
		$smsBody = $smsPack['body'];
		$smsLogBody = $smsPack['log'];

		$sentSms   = false;
		$sentEmail = false;
		$errors    = [];

		if (in_array($channel, ['sms', 'both'], true)) {
			if ($phone === '') {
				$errors[] = 'No phone number';
			} else {
				$smsResult = null;
				if ($this->sendSMS($phone, $smsBody, $smsResult)) {
					$smsMdl = new SmsModel();
					$smsMdl->save([
						'school_id'      => $schoolId,
						'active_term'    => 0,
						'content'        => $smsLogBody,
						'recipient'      => $phone,
						'recipient_type' => 1,
					]);
					$sentSms = true;
				} else {
					$errors[] = 'SMS failed' . (is_array($smsResult)
						? ': ' . ($smsResult['content'] ?? json_encode($smsResult))
						: ($smsResult ? ': ' . $smsResult : ''));
				}
			}
		}

		if (in_array($channel, ['email', 'both'], true)) {
			if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
				$errors[] = 'No valid email';
			} else {
				$mailData = [
					'name'             => $school['name'],
					'email'            => $email,
					'fname'            => $fname,
					'lname'            => $lname,
					'default_password' => $defaultPassword,
				];
				$htmlMsg = view('emails/school_creation', $mailData);
				if ($this->_send_email($email, 'XanderTech SmartSMS login credentials', $htmlMsg)) {
					$sentEmail = true;
				} else {
					$errors[] = 'Email failed';
				}
			}
		}

		$parts = ['Password reset for headmaster'];
		if ($sentSms) {
			$parts[] = '1 SMS sent';
		}
		if ($sentEmail) {
			$parts[] = '1 email sent';
		}

		if ($errors && ($sentSms || $sentEmail)) {
			return $this->response->setJSON([
				'warning'    => implode('. ', $parts) . '. Some deliveries failed.',
				'failed'     => $errors,
				'sent_sms'   => $sentSms ? 1 : 0,
				'sent_email' => $sentEmail ? 1 : 0,
			]);
		}
		if (! $sentSms && ! $sentEmail) {
			return $this->response->setJSON([
				'error'  => 'Could not send credentials. Check phone number and email address.',
				'failed' => $errors,
			]);
		}

		return $this->response->setJSON([
			'success'    => implode('. ', $parts) . '.',
			'sent_sms'   => $sentSms ? 1 : 0,
			'sent_email' => $sentEmail ? 1 : 0,
		]);
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
