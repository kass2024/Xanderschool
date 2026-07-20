<?php

namespace App\Controllers;

use App\Libraries\Wkhtmltopdf;
use App\Libraries\WdaReportBuilder;
use App\Libraries\GeminiCardBackground;
use App\Libraries\GeminiAcademicDocs;
use App\Models\AcademicYearModel;
use App\Models\AcademicPlanModel;
use App\Models\AcademicAiAnalysisModel;
use App\Models\ActiveTermModel;
use App\Models\ActivityModel;
use App\Models\AddressModel;
use App\Models\ApplicationSettingsModel;
use App\Models\ApplicationTransactionModel;
use App\Models\AttendanceRecordsModel;
use App\Models\BookCategoryModel;
use App\Models\BookModel;
use App\Models\BookRecordModel;
use App\Models\BusModel;
use App\Models\ClassesModel;
use App\Models\ClassPedagogicalDocModel;
use App\Models\ClassRecordModel;
use App\Models\CourseCategoryModel;
use App\Models\CourseModel;
use App\Models\CourseRecordModel;
use App\Models\DailyAttendanceModel;
use App\Models\DeliberationConditionsModel;
use App\Models\DeliberationCriteriaModel;
use App\Models\DeliberationFailedCoursesModel;
use App\Models\DeliberationRecords;
use App\Models\DeptModel;
use App\Models\DisciplineModel;
use App\Models\DocumentsModel;
use App\Models\ExtraFeesModel;
use App\Models\FacultyModel;
use App\Models\FeesRecordModel;
use App\Models\GradeModel;
use App\Models\LeaveModel;
use App\Models\LevelsModel;
use App\Models\ManualDecisionModel;
use App\Models\MarksModel;
use App\Models\ParentsModel;
use App\Models\PaymentModel;
use App\Models\PermissionModel;
use App\Models\PostsModel;
use App\Models\RegnumberModel;
use App\Models\RouteModel;
use App\Models\SchoolFeesDiscountModel;
use App\Models\SchoolFeesModel;
use App\Models\SchoolModel;
use App\Models\ShiftModel;
use App\Models\SmsModel;
use App\Models\SmsRecipientModel;
use App\Models\StaffModel;
use App\Models\StudentApplicationModel;
use App\Models\StudentModel;
use App\Models\TermModel;
use App\Models\TransportFeesModel;
use App\Models\UpdateVersionModel;
use App\Models\VerdictModel;
use App\Models\IntouchAccount;
use App\Models\BoardingAttendanceModel;
use CodeIgniter\HTTP\Response;

use GuzzleHttp\Client;

class Home extends BaseController
{
	private $log_status = "Soma_logged_in";
	private $data = array();

	public function __construct()
	{
		helper('qonics');
		service('request')->setLocale(isset($_COOKIE['lang']) ? $_COOKIE['lang'] : "en");
	}

	public function _preset(...$allowed)
	{
		$this->session->set("return_url", current_url());
		if ($this->session->get($this->log_status) == null) {
			header("location: " . base_url('login'));
			die();
		} else {
			// if (!_is_allowed($allowed)) {
			// 	header("location: " . base_url('dashboard'));
			// 	die();
			// }
			$this->ensurePeriodLocksSchema();
			$schoolMdl = new SchoolModel();
			$skl = $schoolMdl->select("schools.name,schools.slogan,schools.extra_sms,schools.head_master
			,schools.head_master_gender,schools.headmaster_signature,schools.acronym,p.sms_limit,at.academic_year,schools.status
			,schools.email,schools.phone,schools.website,schools.active_term,schools.logo,schools.in_time,schools.leave_time,schools.address
			,schools.tolerance,schools.pobox,at.term,at.sms_usage,schools.discipline_max,at.use_period,at.locked_periods
			,ac.title as academic_year_title, ac.id AS academic_year_id, at.id as active_term_id,date_format(schools.created_at,'%Y') as start_year")
					->join("packages p", "p.id=schools.package")
					->join("active_term at", "at.id=schools.active_term", "LEFT")
					->join("academic_year ac", "at.academic_year=ac.id", "LEFT")
					->where("schools.id", $this->session->get("soma_school_id"))->get()->getRow();
			if ($skl->status == 0) {
				//school is disabled by somanet admin
				$this->session->setFlashdata('error', "Your school is locked by XanderTech admin");
				header("location: " . base_url('logout'));
				die();
			}
			if ($skl->active_term == 0 && $this->session->get('soma_post') != 1) {
				//no active term, disable other accounts except admin
				$this->session->setFlashdata('error', "Active term not set, contact school admin");
				header("location: " . base_url('login'));
				die();
			}
			$shiftMdl = new ShiftModel();
			$this->data['shifts'] = $shiftMdl->select("shifts.*,count(st.id) as staffs")
					->join("staffs st", "shifts.id=st.shift_id", "left")
					->where("shifts.school_id", $this->session->get("soma_school_id"))
					->groupBy("shifts.id")
					->get()->getResultArray();
			$acMdl = new AcademicYearModel();
			$this->data['academicYears'] = $acMdl->select('id,title')
					->where('school_id', $this->session->get("soma_school_id"))
					->orderBy('id', 'DESC')
					->get(10)->getResultArray();
			$suggestions = '';
			if (count($this->data['academicYears']) > 0) {
				$latest = $this->data['academicYears'][(count($this->data['academicYears']) - 1)];
				if (strpos($latest['title'], '-') !== false) {
					$last_year = explode('-', $latest['title'])[1];
					$suggestions = $last_year . '-' . ($last_year + 1);
				} else if (strlen($latest['title']) == 4) {
					$suggestions = ($latest['title'] + 1);
				}
			}
			$this->data['academicYearSuggestion'] = $suggestions;
			$this->data['sms_limit'] = $skl->sms_limit;
			$this->data['sms_usage'] = $skl->sms_usage;
//			$this->data['remaining_sms'] = $skl->sms_limit - $skl->sms_usage + $skl->extra_sms;
			$this->data['remaining_sms'] = $skl->extra_sms;
			$this->data['active_term'] = $skl->active_term;
			$this->data['term'] = $skl->term;
			$this->data['academic_year'] = $skl->academic_year;
			$this->data['academic_year_title'] = $skl->academic_year_title;
			$this->data['discipline_max'] = $skl->discipline_max;
			$this->data['periodic'] = $skl->use_period;
			$this->data['locked_periods'] = $this->parseLockedPeriods($skl->locked_periods ?? '');
			$this->data['school_address'] = $skl->address;
			$this->data['school_acronym'] = $skl->acronym;
			$this->data['school_moto'] = $skl->slogan;
			$this->data['school_name'] = $skl->name;
			$this->data['school_phone'] = $skl->phone;
			$this->data['school_website'] = $skl->website;
			$this->data['school_pobox'] = $skl->pobox;
			$this->data['school_start_year'] = $skl->start_year;
			$this->data['school_email'] = $skl->email;
			$this->data['school_cell'] = "M";
			$this->data['school_sector'] = "M";
			$this->data['school_district'] = "M";
			$this->data['school_logo'] = $skl->logo;
			$this->data['school_in_time'] = $skl->in_time;
			$this->data['school_leave_time'] = $skl->leave_time;
			$this->data['school_tolerance'] = $skl->tolerance;
			$this->data['school_logo'] = $skl->logo;
			$this->data['province'] = "M";
			$this->data['head_master'] = $skl->head_master;
			$this->data['head_master_gender'] = $skl->head_master_gender;
			$this->data['headmaster_signature'] = $skl->headmaster_signature;
			$this->session->set(['soma_academics_year' => $skl->academic_year]);

			$this->data['academic_year_id'] = $skl->academic_year_id;
			$this->data['active_term_id'] = $skl->active_term_id;
			$this->data['school_id'] = $this->session->get("soma_school_id");
//			echo $this->data['remaining_sms'];die();
//			echo "<pre>";var_dump($this->data);die();
		}
	}
public function testEmail()
{
    echo "Testing...<br>";
    $result = $this->_send_email('your@email.com', 'Test Email', 'This is a test message');

    echo $result ? "✅ Email sent!" : "❌ Email failed!";
    die("<br>Finished.");
}



	public function set_lang($lang = 'en')
	{
		setcookie("lang", $lang, time() + (86400 * 30), "/"); // 86400 = 1 day
		return $this->response->setJSON(array("success" => "language changed"));
	}

	public function index($type = null)
	{
		if ($type !== null) {
			return redirect()->to("login");
		}
		$data['title'] = "SmartSMS";
		$data['subtitle'] = "XanderTech Smart School Management System";
		$data['content'] = view('landingPage/main', $data);
		return view('landing_page', $data);
	}

	public function dashboard()
	{
		$this->_preset();
		$data = $this->data;
		$data['title'] = lang("app.dashboard");
		$studentMdl = new StudentModel();
		$staff = new StaffModel();
		$sms = new SmsModel();
		$schoolFeesModel = new SchoolFeesModel();
		$extraFeesModel = new ExtraFeesModel();
		$permission = new PermissionModel();
		$school_id = $this->session->get("soma_school_id");
		$postModel = new LeaveModel();
		$data['approveds'] = $postModel->select("leaves.id,leaves.status")
				->where("leaves.school_id", $school_id)
				->where("leaves.status", 1)
				->where("leaves.created_at >=", date('Y-1-1'))
				->get()->getResultArray();
		$data['sms_array'] = "[" . $this->get_sms_month(1) . ",
							 " . $this->get_sms_month(2) . ",
							 " . $this->get_sms_month(3) . ",
							 " . $this->get_sms_month(4) . ",
							 " . $this->get_sms_month(5) . ",
							 " . $this->get_sms_month(6) . ",
							 " . $this->get_sms_month(7) . ",
							 " . $this->get_sms_month(8) . ",
							 " . $this->get_sms_month(9) . ",
							 " . $this->get_sms_month(10) . ",
							 " . $this->get_sms_month(11) . ",
							 " . $this->get_sms_month(12) . "]";

		$data['leave_array'] = "[" . $this->get_leave_month(1) . ",
							 " . $this->get_leave_month(2) . ",
							 " . $this->get_leave_month(3) . ",
							 " . $this->get_leave_month(4) . ",
							 " . $this->get_leave_month(5) . ",
							 " . $this->get_leave_month(6) . ",
							 " . $this->get_leave_month(7) . ",
							 " . $this->get_leave_month(8) . ",
							 " . $this->get_leave_month(9) . ",
							 " . $this->get_leave_month(10) . ",
							 " . $this->get_leave_month(11) . ",
							 " . $this->get_leave_month(12) . "]";
		$data['denieds'] = $postModel->select("leaves.id,leaves.status")
				->where("leaves.school_id", $school_id)
				->where("leaves.status", 2)
				->where("leaves.created_at >=", date('Y-1-1'))
				->get()->getResultArray();
//		print_r($approveds); die();
		$data['schoolfees'] = $studentMdl->select("students.id,students.lname,(sf.amount+coalesce(fd.amount,0)) as expected,sum(fr.amount) as paid,fr.due_date")
				->join("class_records cr", "cr.student=students.id", "LEFT")
				->join("classes cl", "cl.id=cr.class", "LEFT")
				->join("levels l", "l.id=cl.level", "LEFT")
				->join("departments d", "d.id=cl.department", "LEFT")
				->join("school_fees sf", "sf.level=l.id and sf.department=d.id ")
				->join("(select sum(amount) as amount,feesId,student from school_fees_discount group by student,feesId) fd", "fd.feesId=sf.id AND fd.student=students.id", "LEFT")
				->join("fees_records fr", "fr.fees_id=sf.id and fr.student_id=students.id and fr.fees_type=0 and fr.status=1", "LEFT ")
				->where("sf.term", $this->data['term'])
				->where("sf.academic_year", $this->data['academic_year'])
				->where("sf.school_id", $school_id)
				->groupBy("students.id")
				->get()->getResultArray();
		$data['extrafees'] = $studentMdl->select("students.id,ex.amount as expected,sum(fr.amount) as paid")
				->join("class_records cr", "cr.student=students.id", "LEFT")
				->join("classes cl", "cl.id=cr.class", "LEFT")
				->join("extra_fees ex", "(ex.type_id=cl.id AND ex.type=0) OR (ex.type_id=students.id AND ex.type=1)", "LEFT")
				->join("fees_records fr", "fr.fees_id=ex.id and fr.student_id=students.id and fr.fees_type=1 and fr.status=1", "LEFT ")
//			->having("ex.term",$this->data['term'])
				->where("ex.academic_year", $this->data['academic_year'])
				->where("ex.school_id", $school_id)
				->groupBy("students.id")
				->get()->getResultArray();
		$data['scl_due_dates'] = $studentMdl->select("students.id,(sf.amount+coalesce(fd.amount,0)) as expected,sum(fr.amount) as paid
															,fr.due_date
															,fr.fees_type
														 	,concat(students.fname,' ',students.lname) as student,
														 	,students.regno
															,d.title as department_name,
															,cl.title
															,d.code,l.title as level_name
															,f.abbrev as faculty_code")
				->join("class_records cr", "cr.student=students.id", "LEFT")
				->join("classes cl", "cl.id=cr.class", "LEFT")
				->join("levels l", "l.id=cl.level", "LEFT")
				->join("departments d", "d.id=cl.department", "LEFT")
				->join("faculty f", "f.id=d.faculty_id", "LEFT")
				->join("school_fees sf", "sf.level=l.id and sf.department=d.id ")
				->join("(select sum(amount) as amount,feesId,student from school_fees_discount group by student,feesId) fd", "fd.feesId=sf.id AND fd.student = students.id", "LEFT")
				->join("fees_records fr", "fr.fees_id=sf.id and fr.student_id=students.id and fr.fees_type=0 and fr.status=1", "LEFT ")
				->where("sf.term", $this->data['term'])
				->where("sf.academic_year", $this->data['academic_year'])
				->where("sf.school_id", $school_id)
				->where("fr.due_date <=", date("Y-m-d"))
				->groupBy("students.id")
				->having("expected > paid")
				->orderBy("fr.id", "DESC")
				->get()->getResultArray();
		$data['ext_due_dates'] = $studentMdl->select("students.id,ex.amount as expected,sum(fr.amount) as paid
															,ex.title as extra
															,fr.due_date
															,fr.fees_type
														 	,concat(students.fname,' ',students.lname) as student,
														 	,students.regno
															,d.title as department_name,
															,cl.title
															,d.code,l.title as level_name
															,f.abbrev as faculty_code")
				->join("class_records cr", "cr.student=students.id", "LEFT")
				->join("classes cl", "cl.id=cr.class", "LEFT")
				->join("levels l", "l.id=cl.level", "LEFT")
				->join("departments d", "d.id=cl.department", "LEFT")
				->join("faculty f", "f.id=d.faculty_id", "LEFT")
				->join("extra_fees ex", "(ex.type_id=cl.id AND ex.type=0) OR (ex.type_id=students.id AND ex.type=1)", "LEFT ")
				->join("fees_records fr", "fr.fees_id=ex.id and fr.student_id=students.id and fr.fees_type=1 and fr.status=1", "LEFT ")
				->where("ex.term", $this->data['term'])
				->where("ex.academic_year", $this->data['academic_year'])
				->where("ex.school_id", $school_id)
				->where("fr.due_date <=", date("Y-m-d"))
				->groupBy("students.id")
				->groupBy("ex.id")
				->having("expected > paid")
				->orderBy("fr.id", "DESC")
				->get()->getResultArray();

		$data['attend_day_array'] = "[" . $this->get_attend_week(1) . ",
							 " . $this->get_attend_week(2) . ",
							 " . $this->get_attend_week(3) . ",
							 " . $this->get_attend_week(4) . ",
							 " . $this->get_attend_week(5) . ",
							 " . $this->get_attend_week(6) . ",
							 " . $this->get_attend_week(7) . "]";
		$data['present'] = $this->get_attend_week(1) +
				$this->get_attend_week(2) +
				$this->get_attend_week(3) +
				$this->get_attend_week(4) +
				$this->get_attend_week(5) +
				$this->get_attend_week(6) +
				$this->get_attend_week(7);
		$data['absent'] = $this->get_week_present();

//		print_r($ext_due_dates); die();
		$data['schoolfeesdeposits'] = $schoolFeesModel->select("school_fees.id,sum(fr.amount) as deposit")
				->join("fees_records fr", "fr.fees_id=school_fees.id  and fr.fees_type=0 and fr.status=1", "LEFT ")
				->where("school_fees.term", $this->data['term'])
				->where("school_fees.academic_year", $this->data['academic_year'])
				->where("school_fees.school_id", $school_id)
				->get()->getResultArray();
		$data['extrafeesdeposits'] = $extraFeesModel->select("extra_fees.id,sum(fr.amount) as depositExt")
				->join("fees_records fr", "fr.fees_id=extra_fees.id  and fr.fees_type=1 and fr.status=1", "LEFT ")
				->where("extra_fees.term", $this->data['term'])
				->where("extra_fees.academic_year", $this->data['academic_year'])
				->where("extra_fees.school_id", $school_id)
				->get()->getResultArray();
		$data['students'] = $studentMdl->select("count(students.id) as st")
				->join("class_records a", "a.student=students.id")
				->where("students.status", 1)
				->where("a.year", $this->data['academic_year'])
				->where("students.school_id", $this->session->get("soma_school_id"))
				->get()->getRowArray()['st'];
		$data['staff'] = $staff->select("count(staffs.id) as st")
				//->where("staffs.status", 1)
				->where("staffs.school_id", $this->session->get("soma_school_id"))
				->get()->getRowArray()['st'];
		$data['permission'] = $permission->select("permission.*")
				->join("students s", "s.id=permission.student_id")
				->join("active_term a", "a.school_id=s.school_id")
				->where("s.school_id", $this->session->get("soma_school_id"))
				->where("a.academic_year", $this->data['academic_year'])
				->get()->getResultArray();
		$pare = $studentMdl->select("count(students.mother) as mother,count(students.father) as father")
				->join("class_records a", "a.student=students.id")
				->where("students.status", 1)
				->where("a.year", $this->data['academic_year'])
				->where("students.school_id", $this->session->get("soma_school_id"))
				->get()->getResultArray();
		foreach ($pare as $par) {
			if ($par['mother'] == null || $par['father'] == null) {
				continue;
			}
		}
		//ntabwo birangiye kuko ndibaza one parent may have more than one child.
		$data['parent'] = $par['mother'] + $par['father'];
		$data['subtitle'] = lang("app.SomanetDashboard");
		$data['page'] = "dashboard";
		$data['content'] = view("pages/dashboard", $data);
		return view('main', $data);
	}

	public function get_attend_week($day)
	{
		$this->_preset();
		$instution = $this->session->get("soma_school_id");
		$postModel = new AttendanceRecordsModel();
		$mnth = $postModel->select("id,time_in")
				->where("school_id", $instution)
				->where('from_unixtime(time_in,\'%Y-%u-%w\')', date('Y-W-' . $day))
				->get()->getResultArray();
		return count($mnth);
	}

	public function get_week_present()
	{
		$this->_preset();
		$instution = $this->session->get("soma_school_id");
		$staffModel = new StaffModel();
		$staffs = $staffModel->select("staffs.id")
				->where("staffs.school_id", $instution)
				->get()->getResultArray();
		return count($staffs);
	}

	public function get_leave_month($month)
	{
		$this->_preset();
		$school_id = $this->session->get("soma_school_id");
		$postModel = new LeaveModel();
		$endDate = date("Y-m-t", strtotime(date('Y-' . str_pad($month, 2, '0', STR_PAD_LEFT))));
		$mnth = $postModel->select("leaves.id")
				->where("leaves.school_id", $school_id)
				->where("leaves.created_at >=", date('Y-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-1 00:00:00'))
				->where("leaves.created_at <=", $endDate)
				->get()->getResultArray();
		return count($mnth);
	}

	public function get_sms_month($month)
	{
		$this->_preset();
		$school_id = $this->session->get("soma_school_id");
		$postModel = new SmsModel();
		$endDate = date("Y-m-t", strtotime(date('Y-' . str_pad($month, 2, '0', STR_PAD_LEFT))));
		$mnth = $postModel->select("sms_records.id,sr.id")
				->join("sms_record_recipients sr", "sr.sms_record_id=sms_records.id", "LEFT")
				->where("sms_records.school_id", $school_id)
				->where("sr.status", 1)
				->where("sms_records.created_at >=", date('Y-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-1 00:00:00'))
				->where("sms_records.created_at <=", $endDate)
				->get()->getResultArray();
		return count($mnth);
	}

	public function add_student()
	{
		$this->_preset(1, 3, 4, 5, 6);
		$data = $this->data;
		$addressModel = new AddressModel();
		$data['title'] = lang("app.AddnewStudent");
		$data['subtitle'] = lang("app.createNewStudent");
		$data['page'] = "add_student";
		$classMdl = new ClassesModel();
		$school_id = $this->session->get("soma_school_id");
		$data['title'] = lang("app.manageCourse");
		$data['provinces'] = $addressModel->getProvince();
		$data['classes'] = $classMdl->get_classes();
		$data['regno'] = $this->_generate_regno();//generate temporary reg number
		$data['content'] = view("pages/add_student", $data);
		return view('main', $data);
	}

	public function add_dept()
	{
		$this->_preset(1, 3);
		$data = $this->data;
		$data['title'] = lang("app.addNewDepartment");
		$data['subtitle'] = lang("app.createNewDeparment");
		$data['page'] = "add_dept";
		$data['content'] = view("pages/add_dep", array());
		return view('main', $data);
	}

	public function student_photo()
	{
		$this->_preset(1, 3);
		$data = $this->data;
		$data['title'] = lang("app.studentPic");
		$data['subtitle'] = lang("app.studentPicSub");
		$data['page'] = "student_pic";
		$data['content'] = view("pages/students_picture", array());
		return view('main', $data);
	}

	public function generate_cards()
	{
		$this->_preset(1, 3);
		set_time_limit(0);
		ini_set("memory_limit", -1);
		ini_set("max_execution_time", -1);
		$ids = $this->request->getPost("stId");
		if (!isset($ids) || count($ids) == 0) {
			return redirect()->to("student-cards");
		}
		$stMdl = new StudentModel();
		$sklMdl = new SchoolModel();
		$this->ensureStaffCardSchema();
		$skData = $sklMdl->select("name,card_design,card_orientation,card_bg_mode,card_template,card_layout,logo,slogan,card_background,header_text_1,header_text_2,header_color,main_color,footer_color,paint_color,capitalize,headmaster_signature,head_master,phone,email,address,website,pobox")
				->where("id", $this->session->get("soma_school_id"))
				->get(1)->getRow();
		$cardTemplate = \App\Libraries\CardLayout::normalizeTemplate($skData->card_template ?? 'ocean');
		$orientation = \App\Libraries\CardLayout::normalizeOrientation(
			$skData->card_orientation ?: \App\Libraries\CardLayout::preferredOrientation($cardTemplate)
		);
		$autoHeaders = \App\Libraries\CardLayout::composeHeaderLines($skData);
		$data['year'] = $this->data['academic_year'];
		$data['theyear'] = $this->data['academic_year_title'];
		$data['moto'] = $skData->slogan;
		$data['logo'] = $skData->logo;
		$data['school_name'] = $skData->name;
		$data['header1'] = $autoHeaders['header1'];
		$data['header2'] = $autoHeaders['header2'];
		$data['background'] = $skData->card_background;
		$data['header_color'] = $skData->header_color;
		$data['main_color'] = $skData->main_color ?: \App\Libraries\CardLayout::defaultAccent($cardTemplate);
		$data['paint_color'] = $skData->paint_color ?: ($skData->main_color ?: \App\Libraries\CardLayout::defaultAccent($cardTemplate));
		$data['capitalize'] = $skData->capitalize;
		$data['footer_color'] = $skData->footer_color;
		$data['orientation'] = $orientation;
		$data['headmaster_signature'] = $skData->headmaster_signature ?? '';
		$data['head_master'] = $skData->head_master ?? '';
		$data['card_template'] = $cardTemplate;
		$data['card_layout'] = $skData->card_layout ?? null;
		$safeIds = array_map('intval', (array)$ids);
		$safeIds = array_values(array_filter($safeIds));
		if (count($safeIds) === 0) {
			return redirect()->to("student-cards");
		}
		$ids = implode(",", $safeIds);
		$students = $stMdl->get_student_simple2("students.id in (" . $ids . ")");
		// Only print cards for students with a real uploaded photo (no fallback).
		$printable = [];
		foreach ($students as $student) {
			if (resolve_profile_photo($student['photo'] ?? '') !== null) {
				$printable[] = $student;
			}
		}
		if (count($printable) === 0) {
			return redirect()->to("student-cards");
		}
		$data['students'] = $printable;
		$html = view("templates/student_card_smart", $data);
		try {
			$tplDir = FCPATH . 'assets/templates/';
			$imgDir = $tplDir . '_card_img';
			if (!is_dir($imgDir)) {
				@mkdir($imgDir, 0775, true);
			}
			$mask = $tplDir . '*.html';
			array_map('unlink', glob($mask) ?: []);
			$wkhtmltopdf = new Wkhtmltopdf(array('path' => $tplDir));
			$wkhtmltopdf->setTitle(lang("app.studentCards"));
			$wkhtmltopdf->setHtml($html);
			// Exact CR80 size. Always Portrait + page-width/height — Landscape orientation
			// swaps dimensions in wkhtmltopdf and produced tall/portrait PDFs for landscape cards.
			$pageW = $orientation === 'portrait' ? '54mm' : '85.6mm';
			$pageH = $orientation === 'portrait' ? '85.6mm' : '54mm';
			$wkhtmltopdf->setOrientation("Portrait");
			$wkhtmltopdf->setOptions(array(
				'enable-local-file-access' => null,
				'disable-smart-shrinking' => null,
				'enable-javascript' => null,
				'javascript-delay' => 500,
				'no-stop-slow-scripts' => null,
				'encoding' => 'UTF-8',
				'page-width' => $pageW,
				'page-height' => $pageH,
			));
			$wkhtmltopdf->setMargins(array("top" => 0, "left" => 0, "right" => 0, "bottom" => 0));
			$wkhtmltopdf->output(Wkhtmltopdf::MODE_EMBEDDED, "students_card_" . time() . ".pdf");
		} catch (\Exception $e) {
			echo $e->getMessage();
		}
	}

	/**
	 * Save card template + drag-drop field layout JSON from School Settings.
	 */
	public function save_card_layout()
	{
		$this->_preset(1, 3);
		$this->ensureStaffCardSchema();
		$schoolId = (int)$this->session->get('soma_school_id');
		$audience = strtolower((string)$this->request->getPost('audience')) === 'staff' ? 'staff' : 'student';
		$template = \App\Libraries\CardLayout::normalizeTemplate($this->request->getPost('template'));
		$orientation = \App\Libraries\CardLayout::normalizeOrientation($this->request->getPost('orientation'));
		$fieldsRaw = $this->request->getPost('fields');
		$fields = is_string($fieldsRaw) ? json_decode($fieldsRaw, true) : $fieldsRaw;
		if (!is_array($fields)) {
			return $this->response->setStatusCode(400)->setJSON(['error' => 'Invalid fields payload']);
		}
		$rawPayload = json_encode([
			'template' => $template,
			'orientation' => $orientation,
			'fields' => $fields,
		], JSON_UNESCAPED_UNICODE);
		$resolved = $audience === 'staff'
			? \App\Libraries\CardLayout::resolveStaff($rawPayload, $template, $orientation)
			: \App\Libraries\CardLayout::resolve($rawPayload, $template, $orientation);
		$payload = json_encode($resolved, JSON_UNESCAPED_UNICODE);
		$update = $audience === 'staff'
			? [
				'sf_card_template' => $resolved['template'],
				'sf_card_layout' => $payload,
				'sf_card_orientation' => $resolved['orientation'],
			]
			: [
				'card_template' => $resolved['template'],
				'card_layout' => $payload,
				'card_orientation' => $resolved['orientation'],
			];
		try {
			$skl = new SchoolModel();
			$skl->update($schoolId, $update);
			return $this->response->setJSON([
				'success' => 'Card layout saved',
				'audience' => $audience,
				'template' => $resolved['template'],
				'orientation' => $resolved['orientation'],
				'layout' => $resolved,
			]);
		} catch (\Throwable $e) {
			return $this->response->setStatusCode(500)->setJSON(['error' => $e->getMessage()]);
		}
	}

	/**
	 * Reset card layout to selected template defaults.
	 */
	public function reset_card_layout()
	{
		$this->_preset(1, 3);
		$this->ensureStaffCardSchema();
		$schoolId = (int)$this->session->get('soma_school_id');
		$audience = strtolower((string)$this->request->getPost('audience')) === 'staff' ? 'staff' : 'student';
		$template = \App\Libraries\CardLayout::normalizeTemplate($this->request->getPost('template'));
		$orientation = \App\Libraries\CardLayout::normalizeOrientation(
			$this->request->getPost('orientation') ?: \App\Libraries\CardLayout::preferredOrientation($template)
		);
		$defaults = $audience === 'staff'
			? \App\Libraries\CardLayout::staffDefaults($template, $orientation)
			: \App\Libraries\CardLayout::defaults($template, $orientation);
		$payload = json_encode($defaults, JSON_UNESCAPED_UNICODE);
		$update = $audience === 'staff'
			? [
				'sf_card_template' => $template,
				'sf_card_layout' => $payload,
				'sf_card_orientation' => $orientation,
			]
			: [
				'card_template' => $template,
				'card_layout' => $payload,
				'card_orientation' => $orientation,
			];
		try {
			(new SchoolModel())->update($schoolId, $update);
			return $this->response->setJSON([
				'success' => 'Reset to template defaults',
				'audience' => $audience,
				'layout' => $defaults,
				'orientation' => $orientation,
			]);
		} catch (\Throwable $e) {
			return $this->response->setStatusCode(500)->setJSON(['error' => $e->getMessage()]);
		}
	}

	/**
	 * Generate 3+ modern CR80-sized AI background proposals for student or staff.
	 * Does not auto-apply — client picks one via apply_card_background_proposal.
	 */
	public function generate_card_background()
	{
		$this->_preset(1, 3);
		$this->ensureStaffCardSchema();
		$schoolId = (int)$this->session->get("soma_school_id");
		$audience = strtolower((string)$this->request->getPost('type')) === 'staff' ? 'staff' : 'student';
		$regenerate = (int)$this->request->getPost('regenerate') === 1;
		$sklMdl = new SchoolModel();
		$sk = $sklMdl->select("id,name,main_color,sf_main_color,card_orientation,sf_card_orientation,card_template,sf_card_template,card_layout,sf_card_layout")
			->where("id", $schoolId)->get(1)->getRow();
		if (!$sk) {
			return $this->response->setStatusCode(404)->setJSON(["error" => "School not found"]);
		}

		try {
			$templatePost = (string)$this->request->getPost('template');
			$template = \App\Libraries\CardLayout::normalizeTemplate(
				$templatePost !== ''
					? $templatePost
					: ($audience === 'staff' ? ($sk->sf_card_template ?? 'ocean') : ($sk->card_template ?? 'ocean'))
			);
			$orientation = $audience === 'staff'
				? ((string)($sk->sf_card_orientation ?: 'landscape'))
				: ((string)($sk->card_orientation ?: 'landscape'));
			$orientation = \App\Libraries\CardLayout::normalizeOrientation(
				$this->request->getPost('orientation') ?: $orientation
			);
			$brandColor = $audience === 'staff'
				? ((string)($sk->sf_main_color ?: $sk->main_color ?: ''))
				: ((string)($sk->main_color ?: ''));
			if ($brandColor === '') {
				$brandColor = \App\Libraries\CardLayout::defaultAccent($template);
			}

			$fieldsRaw = $this->request->getPost('fields');
			$fields = is_string($fieldsRaw) ? json_decode($fieldsRaw, true) : $fieldsRaw;
			if (!is_array($fields)) {
				$layoutJson = $audience === 'staff' ? ($sk->sf_card_layout ?? null) : ($sk->card_layout ?? null);
				$resolved = $audience === 'staff'
					? \App\Libraries\CardLayout::resolveStaff($layoutJson, $template, $orientation)
					: \App\Libraries\CardLayout::resolve($layoutJson, $template, $orientation);
				$fields = $resolved['fields'] ?? [];
			}

			$lib = new GeminiCardBackground();
			$analysis = $lib->analyzeTemplate($template, $orientation, $fields, $audience, $brandColor);
			$proposals = $lib->generateProposals(
				(string)$sk->name,
				$orientation,
				$analysis['accent'],
				$audience,
				3,
				$template,
				$fields,
				$regenerate
			);
			$anyGemini = false;
			foreach ($proposals as $p) {
				if (($p['source'] ?? '') === 'gemini') {
					$anyGemini = true;
					break;
				}
			}
			$msg = $anyGemini
				? ($regenerate
					? 'New set ready — pick one, or regenerate again'
					: 'Analyzed “' . $template . '” template — pick a background (content areas kept white for text)')
				: 'Background generation unavailable — edge-accent blanks shown.';
			return $this->response->setJSON([
				"success" => $msg,
				"proposals" => $proposals,
				"audience" => $audience,
				"orientation" => $orientation,
				"template" => $template,
				"accent" => $analysis['accent'],
				"analysis" => [
					'content_box' => $analysis['content_box'],
					'decorate' => $analysis['decorate'],
					'keepout_count' => count($analysis['keepouts']),
				],
				"source" => $anyGemini ? 'gemini' : 'fallback',
				"regenerated" => $regenerate,
			]);
		} catch (\Throwable $e) {
			return $this->response->setStatusCode(500)->setJSON(["error" => $e->getMessage()]);
		}
	}

	/**
	 * Apply a chosen AI proposal (or any background filename) to student/staff card.
	 */
	public function apply_card_background_proposal()
	{
		$this->_preset(1, 3);
		$this->ensureStaffCardSchema();
		$schoolId = (int)$this->session->get("soma_school_id");
		$audience = strtolower((string)$this->request->getPost('type')) === 'staff' ? 'staff' : 'student';
		$filename = basename((string)$this->request->getPost('filename'));
		if ($filename === '' || !preg_match('/^[a-zA-Z0-9._-]+\.(png|jpe?g|webp)$/i', $filename)) {
			return $this->response->setStatusCode(400)->setJSON(['error' => 'Invalid background file']);
		}
		$path = FCPATH . 'assets/images/background/' . $filename;
		if (!is_file($path)) {
			return $this->response->setStatusCode(404)->setJSON(['error' => 'Background file not found']);
		}
		$sklMdl = new SchoolModel();
		$sk = $sklMdl->select('id,card_background,sf_card_background')->where('id', $schoolId)->get(1)->getRow();
		if (!$sk) {
			return $this->response->setStatusCode(404)->setJSON(['error' => 'School not found']);
		}
		$old = $audience === 'staff' ? (string)($sk->sf_card_background ?? '') : (string)($sk->card_background ?? '');
		$orientation = \App\Libraries\CardLayout::normalizeOrientation($this->request->getPost('orientation') ?: 'landscape');
		$update = $audience === 'staff'
			? [
				'sf_card_background' => $filename,
				'sf_card_bg_mode' => 'smart',
				'sf_card_orientation' => $orientation,
			]
			: [
				'card_background' => $filename,
				'card_bg_mode' => 'smart',
				'card_orientation' => $orientation,
			];
		$sklMdl->update($schoolId, $update);
		return $this->response->setJSON([
			'success' => 'Background applied',
			'filename' => $filename,
			'url' => base_url('assets/images/background/' . $filename),
			'audience' => $audience,
			'orientation' => $orientation,
			'previous' => $old,
		]);
	}

	private function ensureStaffCardSchema()
	{
		static $done = false;
		if ($done) {
			return;
		}
		$db = \Config\Database::connect();
		if (!$db->tableExists('schools')) {
			$done = true;
			return;
		}
		$fields = $db->getFieldNames('schools');
		$alters = [];
		if (!in_array('sf_card_template', $fields, true)) {
			$alters[] = "ADD COLUMN `sf_card_template` VARCHAR(32) DEFAULT 'ocean'";
		}
		if (!in_array('sf_card_orientation', $fields, true)) {
			$alters[] = "ADD COLUMN `sf_card_orientation` VARCHAR(20) DEFAULT 'landscape'";
		}
		if (!in_array('sf_card_bg_mode', $fields, true)) {
			$alters[] = "ADD COLUMN `sf_card_bg_mode` VARCHAR(20) DEFAULT 'manual'";
		}
		if (!in_array('sf_card_layout', $fields, true)) {
			$alters[] = "ADD COLUMN `sf_card_layout` LONGTEXT NULL";
		}
		if (!in_array('sf_header_text_1', $fields, true)) {
			$alters[] = "ADD COLUMN `sf_header_text_1` VARCHAR(255) NULL";
		}
		if (!in_array('sf_header_text_2', $fields, true)) {
			$alters[] = "ADD COLUMN `sf_header_text_2` VARCHAR(255) NULL";
		}
		if (!in_array('sf_header_color', $fields, true)) {
			$alters[] = "ADD COLUMN `sf_header_color` VARCHAR(20) NULL";
		}
		if (!in_array('sf_main_color', $fields, true)) {
			$alters[] = "ADD COLUMN `sf_main_color` VARCHAR(20) NULL";
		}
		if (!in_array('sf_footer_color', $fields, true)) {
			$alters[] = "ADD COLUMN `sf_footer_color` VARCHAR(20) NULL";
		}
		if (!in_array('paint_color', $fields, true)) {
			$alters[] = "ADD COLUMN `paint_color` VARCHAR(20) NULL";
		}
		if (!in_array('sf_paint_color', $fields, true)) {
			$alters[] = "ADD COLUMN `sf_paint_color` VARCHAR(20) NULL";
		}
		if (!in_array('sf_capitalize', $fields, true)) {
			$alters[] = "ADD COLUMN `sf_capitalize` TINYINT(1) NULL";
		}
		if ($alters) {
			$db->query('ALTER TABLE `schools` ' . implode(', ', $alters));
			$db->query("UPDATE `schools` SET
				`sf_header_text_1` = IFNULL(`sf_header_text_1`, `header_text_1`),
				`sf_header_text_2` = IFNULL(`sf_header_text_2`, `header_text_2`),
				`sf_header_color` = IFNULL(`sf_header_color`, `header_color`),
				`sf_main_color` = IFNULL(`sf_main_color`, `main_color`),
				`sf_footer_color` = IFNULL(`sf_footer_color`, `footer_color`),
				`sf_capitalize` = IFNULL(`sf_capitalize`, `capitalize`),
				`paint_color` = IFNULL(`paint_color`, IFNULL(`main_color`, '#1E6FD9')),
				`sf_paint_color` = IFNULL(`sf_paint_color`, IFNULL(`sf_main_color`, IFNULL(`main_color`, '#1E6FD9')))
			");
		}
		$done = true;
	}

	public function generate_staff_cards()
	{
		$this->_preset(1, 3);
		set_time_limit(0);
		ini_set("memory_limit", -1);
		ini_set("max_execution_time", -1);
		$ids = $this->request->getPost("stId");
		if (!isset($ids) || count($ids) == 0) {
			return redirect()->to("staff-cards");
		}
		$stMdl = new StaffModel();
		$sklMdl = new SchoolModel();
		$this->ensureStaffCardSchema();
		$skData = $sklMdl->select("name,card_design,logo,slogan,sf_card_background,sf_card_template,sf_card_orientation,sf_card_layout,header_text_1,header_text_2,header_color,main_color,footer_color,paint_color,capitalize,sf_header_text_1,sf_header_text_2,sf_header_color,sf_main_color,sf_footer_color,sf_paint_color,sf_capitalize,headmaster_signature,head_master,phone,email,address,website,pobox")
				->where("id", $this->session->get("soma_school_id"))
				->get(1)->getRow();
		$cardTemplate = \App\Libraries\CardLayout::normalizeTemplate($skData->sf_card_template ?? 'ocean');
		$orientation = \App\Libraries\CardLayout::normalizeOrientation(
			$skData->sf_card_orientation ?: \App\Libraries\CardLayout::preferredOrientation($cardTemplate)
		);
		$autoHeaders = \App\Libraries\CardLayout::composeHeaderLines($skData);
		$data['year'] = $this->data['academic_year'];
		$data['theyear'] = $this->data['academic_year_title'] ?? $this->data['academic_year'];
		$data['moto'] = $skData->slogan;
		$data['logo'] = $skData->logo;
		$data['school_name'] = $skData->name;
		$data['header1'] = $autoHeaders['header1'];
		$data['header2'] = $autoHeaders['header2'];
		$data['background'] = $skData->sf_card_background;
		$data['header_color'] = $skData->sf_header_color ?: $skData->header_color;
		$data['main_color'] = ($skData->sf_main_color ?: $skData->main_color) ?: \App\Libraries\CardLayout::defaultAccent($cardTemplate);
		$data['paint_color'] = ($skData->sf_paint_color ?: ($skData->paint_color ?: ($skData->sf_main_color ?: $skData->main_color)))
			?: \App\Libraries\CardLayout::defaultAccent($cardTemplate);
		$data['capitalize'] = $skData->sf_capitalize !== null && $skData->sf_capitalize !== ''
			? $skData->sf_capitalize
			: $skData->capitalize;
		$data['footer_color'] = $skData->sf_footer_color ?: $skData->footer_color;
		$data['orientation'] = $orientation;
		$data['card_template'] = $cardTemplate;
		$data['card_layout'] = $skData->sf_card_layout ?? null;
		$data['headmaster_signature'] = $skData->headmaster_signature ?? '';
		$data['head_master'] = $skData->head_master ?? '';
		$data['card_badge'] = 'STAFF CARD';
		$ids = implode(",", array_map('intval', (array) $ids));
		$data['staffs'] = $stMdl->get_staff("staffs.id in (" . $ids . ")");
		$html = view("templates/staff_card_smart", $data);
		try {
			$mask = FCPATH . "assets/templates/*.html";
			array_map('unlink', glob($mask) ?: []);
			$wkhtmltopdf = new Wkhtmltopdf(array('path' => FCPATH . 'assets/templates/'));
			$wkhtmltopdf->setTitle(lang("app.staffCards") ?: 'Staff cards');
			$wkhtmltopdf->setHtml($html);
			$pageW = $orientation === 'portrait' ? '54mm' : '85.6mm';
			$pageH = $orientation === 'portrait' ? '85.6mm' : '54mm';
			$wkhtmltopdf->setOrientation("Portrait");
			$wkhtmltopdf->setOptions(array(
				'enable-local-file-access' => null,
				'disable-smart-shrinking' => null,
				'enable-javascript' => null,
				'javascript-delay' => 500,
				'no-stop-slow-scripts' => null,
				'encoding' => 'UTF-8',
				'page-width' => $pageW,
				'page-height' => $pageH,
			));
			$wkhtmltopdf->setMargins(array("top" => 0, "left" => 0, "right" => 0, "bottom" => 0));
			$wkhtmltopdf->output(Wkhtmltopdf::MODE_EMBEDDED, "staffs_card_" . time() . ".pdf");
		} catch (\Exception $e) {
			echo $e->getMessage();
		}
	}

	public function student_cards()
	{
		$this->_preset(1, 3);
		$data = $this->data;
		$data['title'] = lang("app.cardGeneration");
		$data['subtitle'] = lang("app.studentCards");
		$data['page'] = "generate_cards";
		$classMdl = new ClassesModel();
		$SchoolModel = new SchoolModel();
		$data['classes'] = $classMdl->select("classes.id,classes.title,d.title as department_name,d.code,l.title as level_name
		,f.type,f.abbrev as faculty_code,concat(s.fname,' ',s.lname) as mentor_name,s.id as idstf")
				->join("departments d", "d.id=classes.department")
				->join("levels l", "l.id=classes.level")
				->join("faculty f", "f.id=d.faculty_id")
				->join("staffs s", "s.id=classes.mentor", "LEFT")
				->where("classes.school_id", $this->session->get("soma_school_id"))
				->get()->getResultArray();
		$data['activeTerm'] = $SchoolModel->select("at.term,at.id")
				->join("active_term at", "at.id=schools.active_term")
				->where("at.school_id", $this->session->get("soma_school_id"))
				->get()->getRowArray();
		$data['content'] = view("pages/student_cards", $data);
		return view('main', $data);
	}

	public function staff_cards()
	{
		$this->_preset(1, 3);
		$data = $this->data;
		$data['title'] = lang("app.cardGeneration");
		$data['subtitle'] = lang("app.staffCards");
		$data['page'] = "generate_cards";
		$postMdl = new PostsModel();
		$SchoolModel = new SchoolModel();
		$data['posts'] = $postMdl->select("id,title")
				->get()->getResultArray();
		$data['content'] = view("pages/staff_cards", $data);
		return view('main', $data);
	}

	public function add_classes()
	{
		$this->_preset(1, 3);
		$data = $this->data;
		$faculty = new FacultyModel();
		$staffMdl = new StaffModel();
		$classMdl = new ClassesModel();
		$data['title'] = lang("app.addNewClass");
		$data['classes'] = $classMdl->get_classes();
		$data['faculty'] = $faculty->get()->getResultArray();
		$data['staffs'] = $staffMdl->where("school_id", $this->session->get("soma_school_id"))->get()->getResultArray();
		$data['subtitle'] = lang("app.CreatenewClass");
		$data['page'] = "add_classes";
		$data['content'] = view("pages/add_class", $data);
		return view('main', $data);
	}

	/**
	 * Academic structure manager: Faculty → Department → Level (REB & TVET).
	 */
	public function academic_structure()
	{
		$this->_preset(1, 3);
		$this->ensureAcademicStructureSchema();
		$data = $this->data;
		$data['title'] = 'Academic structure';
		$data['subtitle'] = 'Manage faculties, departments and levels';
		$data['page'] = 'academic_structure';
		$data['content'] = view('pages/academic_structure', $data);
		return view('main', $data);
	}

	/** Ensure levels.department_id exists for Faculty→Dept→Level hierarchy. */
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

	/** JSON tree for structure manager. Levels are shared (not per-department). */
	public function getAcademicStructure($program = 0): Response
	{
		$this->_preset(1, 3);
		$this->ensureAcademicStructureSchema();
		$db = \Config\Database::connect();
		$program = (int) $program;

		$facBuilder = $db->table('faculty')->select('id, title, abbrev, type, status')->orderBy('title', 'ASC');
		if ($program === 1 || $program === 2) {
			$facBuilder->where('type', $program);
		}
		$faculties = $facBuilder->get()->getResultArray();

		// TVET: one shared Level 1–5 pool for all departments
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

			// REB: levels belong to faculty (shared by all its departments)
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
		$this->_preset(1, 3);
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
		$this->_preset(1, 3);
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
		$row = [
			'title' => $title,
			'code' => $code,
			'faculty_id' => $facultyId,
			'created_by' => (int) $this->session->get('soma_id'),
			'updated_by' => (int) $this->session->get('soma_id'),
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
		$this->_preset(1, 3);
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
			// TVET shared pool: type=1, no faculty ownership required
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
		$this->_preset(1, 3);
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

	public function settings()
	{
		$this->_preset(1, 3);
		$this->ensurePeriodLocksSchema();
		$data = $this->data;
		$settingsMdl = new SchoolModel();
		$faculityModel = new FacultyModel();
		$grade = new GradeModel();
		$schoolId = (int) $this->session->get("soma_school_id");
		$data['settings'] = $settingsMdl->getSchool(array("schools.id" => $schoolId))->getRowArray();
		$data['faculities'] = $data['faculty'] = $faculityModel->get()->getResultArray();
		$nurseryFaculty = null;
		foreach ($data['faculities'] as $fac) {
			if (strcasecmp(trim((string) ($fac['title'] ?? '')), 'Nursery') === 0
				|| stripos((string) ($fac['title'] ?? ''), 'Nursery') !== false) {
				$nurseryFaculty = $fac;
				break;
			}
		}
		$data['nursery_faculty'] = $nurseryFaculty;
		$data['colors'] = $grade->select("grade.id,grade.color_title,grade.max_point,grade.min_point,grade.color,f.title")
				->join("faculty f", "f.id=grade.faculty_id", "LEFT")
				->where("grade.school_id", $schoolId)
				->orderBy('grade.max_point', 'DESC')
				->orderBy('grade.min_point', 'DESC')
				->get()->getResultArray();
		$data['title'] = lang("app.settings");
		$data['subtitle'] = lang("app.schoolSettings");
		$data['page'] = "settings";
		$data['intouch_info'] = (new IntouchAccount())->where('school_id', $schoolId)->get()->getResultArray()[0] ?? ['school_id' => $schoolId, "username" => "", "password" => ""];
		$data['app_settings'] = (new ApplicationSettingsModel())->forSchool($schoolId);
		$shiftMdl = new ShiftModel();
		$data['shifts'] = $shiftMdl->select("shifts.*,count(st.id) as staffs")
				->join("staffs st", "shifts.id=st.shift_id", "left")
				->where("shifts.school_id", $schoolId)
				->groupBy("shifts.id")
				->get()->getResultArray();

		$this->ensurePedagogicalDocsSchema();
		$this->ensureStaffCardSchema();
		$classMdl = new ClassesModel();
		$data['classes'] = $classMdl->select("classes.id,classes.title,d.title as department_name,d.code as dept_code,l.title as level_name,f.abbrev as faculty_code")
				->join("departments d", "d.id=classes.department")
				->join("levels l", "l.id=classes.level")
				->join("faculty f", "f.id=d.faculty_id")
				->where("classes.school_id", $schoolId)
				->orderBy("l.id", "ASC")
				->orderBy("classes.title", "ASC")
				->get()->getResultArray();
		$yearId = (int) ($this->data['academic_year'] ?? 0);
		$pedMdl = new ClassPedagogicalDocModel();
		$data['academic_year_id'] = $yearId;
		$data['pedagogical_docs'] = [];
		if ($yearId > 0) {
			// Strict: only documents for the active academic year (new year = empty set)
			$data['pedagogical_docs'] = $pedMdl->where('school_id', $schoolId)
				->where('academic_year', $yearId)
				->orderBy('id', 'DESC')
				->findAll();
		}

		// Sample staff from DB for staff card preview (post required)
		$stMdl = new StaffModel();
		$sampleStaff = $stMdl->select('staffs.id,staffs.fname,staffs.lname,staffs.phone,staffs.email,staffs.photo,p.title as post_title')
			->join('posts p', 'p.id=staffs.post', 'left')
			->where('staffs.school_id', $schoolId)
			->where('staffs.status', 1)
			->orderBy('staffs.id', 'ASC')
			->get(1)
			->getRowArray();
		if (!$sampleStaff) {
			$sampleStaff = $stMdl->select('staffs.id,staffs.fname,staffs.lname,staffs.phone,staffs.email,staffs.photo,p.title as post_title')
				->join('posts p', 'p.id=staffs.post', 'left')
				->where('staffs.school_id', $schoolId)
				->orderBy('staffs.id', 'ASC')
				->get(1)
				->getRowArray();
		}
		$data['card_sample_staff'] = $sampleStaff ?: null;

		$data['content'] = view("pages/school_settings", $data);
		return view('main', $data);
	}

	private function ensurePedagogicalDocsSchema()
	{
		$db = \Config\Database::connect();
		if (!$db->tableExists('class_pedagogical_docs')) {
			$db->query("CREATE TABLE IF NOT EXISTS `class_pedagogical_docs` (
			  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
			  `school_id` int(11) NOT NULL,
			  `class_id` int(11) NOT NULL,
			  `academic_year` int(11) DEFAULT NULL,
			  `doc_type` varchar(32) NOT NULL,
			  `term` tinyint(4) DEFAULT NULL,
			  `file_name` varchar(255) NOT NULL,
			  `original_name` varchar(255) NOT NULL,
			  `created_by` int(11) DEFAULT NULL,
			  `created_at` datetime DEFAULT NULL,
			  `updated_at` datetime DEFAULT NULL,
			  PRIMARY KEY (`id`),
			  KEY `idx_school_class` (`school_id`,`class_id`),
			  KEY `idx_school_year_type` (`school_id`,`academic_year`,`doc_type`),
			  KEY `idx_type` (`doc_type`,`term`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
			return;
		}
		// Allow multiple curriculum/chronogram files per class+year (drop old single-file unique key)
		try {
			$indexes = $db->query("SHOW INDEX FROM `class_pedagogical_docs` WHERE Key_name = 'uniq_class_year_type'")->getResultArray();
			if (!empty($indexes)) {
				$db->query('ALTER TABLE `class_pedagogical_docs` DROP INDEX `uniq_class_year_type`');
			}
		} catch (\Throwable $e) {
			// ignore if already dropped
		}
		try {
			$idx = $db->query("SHOW INDEX FROM `class_pedagogical_docs` WHERE Key_name = 'idx_school_year_type'")->getResultArray();
			if (empty($idx)) {
				$db->query('ALTER TABLE `class_pedagogical_docs` ADD KEY `idx_school_year_type` (`school_id`,`academic_year`,`doc_type`)');
			}
		} catch (\Throwable $e) {
			// ignore
		}
	}

	private function ensurePeriodLocksSchema()
	{
		static $done = false;
		if ($done) {
			return;
		}
		$db = \Config\Database::connect();
		if (!$db->tableExists('active_term')) {
			$done = true;
			return;
		}
		$fields = $db->getFieldNames('active_term');
		if (!in_array('locked_periods', $fields, true)) {
			$db->query("ALTER TABLE `active_term` ADD COLUMN `locked_periods` VARCHAR(32) NOT NULL DEFAULT '' AFTER `use_period`");
		}
		$done = true;
	}

	private function parseLockedPeriods($raw): array
	{
		if ($raw === null || $raw === '') {
			return [];
		}
		$out = [];
		foreach (explode(',', (string) $raw) as $part) {
			$n = (int) trim($part);
			if ($n >= 1 && $n <= 4 && !in_array($n, $out, true)) {
				$out[] = $n;
			}
		}
		sort($out);
		return $out;
	}

	private function isPeriodLocked($activeTermId, $period): bool
	{
		$period = (int) $period;
		$activeTermId = (int) $activeTermId;
		if ($period < 1 || $activeTermId < 1) {
			return false;
		}
		$this->ensurePeriodLocksSchema();
		$row = (new ActiveTermModel())->select('locked_periods')->find($activeTermId);
		if (!$row) {
			return false;
		}
		$raw = is_array($row) ? ($row['locked_periods'] ?? '') : ($row->locked_periods ?? '');
		return in_array($period, $this->parseLockedPeriods($raw), true);
	}

	public function toggle_period_lock()
	{
		$this->_preset(1, 3);
		$this->ensurePeriodLocksSchema();
		$period = (int) $this->request->getPost('period');
		$lock = (int) $this->request->getPost('lock');
		if ($period < 1 || $period > 4) {
			return $this->response->setJSON(['error' => 'Invalid period']);
		}
		$termId = (int) ($this->data['active_term'] ?? 0);
		if ($termId <= 0) {
			return $this->response->setJSON(['error' => 'No active term set']);
		}
		if ((int) ($this->data['periodic'] ?? 0) !== 1) {
			return $this->response->setJSON(['error' => 'Enable the periodic system for this term first']);
		}
		$mdl = new ActiveTermModel();
		$row = $mdl->select('id, locked_periods')->find($termId);
		if (!$row) {
			return $this->response->setJSON(['error' => 'Active term not found']);
		}
		$raw = is_array($row) ? ($row['locked_periods'] ?? '') : ($row->locked_periods ?? '');
		$locked = $this->parseLockedPeriods($raw);
		if ($lock === 1) {
			if (!in_array($period, $locked, true)) {
				$locked[] = $period;
			}
		} else {
			$locked = array_values(array_filter($locked, static function ($p) use ($period) {
				return (int) $p !== $period;
			}));
		}
		sort($locked);
		$mdl->save(['id' => $termId, 'locked_periods' => implode(',', $locked)]);
		$msg = $lock === 1
			? ('Period ' . $period . ' is now locked. Teachers cannot enter marks for it.')
			: ('Period ' . $period . ' is unlocked. Marks entry is allowed again.');
		return $this->response->setJSON([
			'success' => $msg,
			'locked_periods' => $locked,
			'period' => $period,
			'locked' => $lock === 1,
		]);
	}

	public function upload_pedagogical_document()
	{
		$this->_preset(1, 3);
		$this->ensurePedagogicalDocsSchema();
		$schoolId = (int) $this->session->get('soma_school_id');
		$classId = (int) $this->request->getPost('class_id');
		$docType = strtolower(trim((string) $this->request->getPost('doc_type')));
		$replaceId = (int) $this->request->getPost('replace_id');
		$yearId = (int) ($this->data['academic_year'] ?? 0);

		if ($yearId <= 0) {
			return $this->response->setJSON(['error' => 'No active academic year. Set an active term first.']);
		}
		if (!in_array($docType, ['curriculum', 'chronogram'], true)) {
			return $this->response->setJSON(['error' => 'Invalid document type']);
		}

		$class = (new ClassesModel())->where('id', $classId)->where('school_id', $schoolId)->first();
		if (!$class) {
			return $this->response->setJSON(['error' => 'Class not found']);
		}

		// Support one or many files in the same request
		$files = $this->request->getFileMultiple('documents');
		if (!$files || $files === []) {
			$one = $this->request->getFile('document');
			$files = ($one && $one->isValid()) ? [$one] : [];
		}
		$files = array_values(array_filter($files, static function ($f) {
			return $f && $f->isValid() && !$f->hasMoved();
		}));
		if ($files === []) {
			return $this->response->setJSON(['error' => 'Please choose at least one valid file']);
		}
		// Replace mode only applies to a single file
		if ($replaceId > 0 && count($files) > 1) {
			return $this->response->setJSON(['error' => 'Replace one file at a time']);
		}

		$dir = FCPATH . 'assets/documents/pedagogical';
		if (!is_dir($dir)) {
			mkdir($dir, 0755, true);
		}

		$mdl = new ClassPedagogicalDocModel();
		$saved = [];
		$hadZip = false;
		foreach ($files as $i => $file) {
			$ext = strtolower($file->getClientExtension());
			if (!in_array($ext, ['pdf', 'doc', 'docx', 'zip'], true)) {
				return $this->response->setJSON(['error' => 'Only PDF, Word, or ZIP are allowed']);
			}
			$maxMb = $ext === 'zip' ? 80 : 20;
			if ($file->getSize() > $maxMb * 1024 * 1024) {
				return $this->response->setJSON(['error' => "File too large (max {$maxMb}MB): " . $file->getClientName()]);
			}

			$newName = 'ped_' . $schoolId . '_' . $classId . '_y' . $yearId . '_' . $docType . '_' . time() . '_' . $i . '_' . bin2hex(random_bytes(2)) . '.' . $ext;
			$file->move($dir, $newName);

			if ($ext === 'zip') {
				$hadZip = true;
				$pkgDir = $dir . '/pkg_' . $schoolId . '_' . $classId . '_y' . $yearId
					. ($docType === 'chronogram' ? '_chr' : '');
				$this->wipeDir($pkgDir);
				@mkdir($pkgDir, 0755, true);
				$zip = new \ZipArchive();
				if ($zip->open($dir . '/' . $newName) === true) {
					$zip->extractTo($pkgDir);
					$zip->close();
				}
				$this->expandNestedZipsInDir($pkgDir);
			}

			$payload = [
				'file_name' => $newName,
				'original_name' => $file->getClientName(),
				'term' => null,
				'academic_year' => $yearId,
				'created_by' => (int) $this->session->get('soma_id'),
			];

			if ($replaceId > 0) {
				$old = $mdl->where('id', $replaceId)->where('school_id', $schoolId)
					->where('class_id', $classId)->where('doc_type', $docType)
					->where('academic_year', $yearId)->first();
				if (!$old) {
					@unlink($dir . '/' . $newName);
					return $this->response->setJSON(['error' => 'Document to replace not found']);
				}
				$oldPath = $dir . '/' . $old['file_name'];
				if (is_file($oldPath)) {
					@unlink($oldPath);
				}
				$mdl->update($old['id'], $payload);
				$saved[] = $newName;
			} else {
				$mdl->insert(array_merge($payload, [
					'school_id' => $schoolId,
					'class_id' => $classId,
					'doc_type' => $docType,
				]));
				$saved[] = $newName;
			}
		}

		$count = count($saved);
		$msg = $count . ' ' . $docType . ' file' . ($count === 1 ? '' : 's')
			. ' saved for academic year ' . ($this->data['academic_year_title'] ?? $yearId);
		if ($hadZip) {
			$msg .= ' (ZIP package extracted)';
		}

		// Invalidate stale AI cache so next Analyse re-runs with new files
		try {
			$cacheMdl = new AcademicAiAnalysisModel();
			$cached = $cacheMdl->where('school_id', $schoolId)->where('class_id', $classId)
				->where('academic_year', $yearId)->first();
			if ($cached) {
				$cacheMdl->update($cached['id'], ['source_hash' => 'invalidated_after_upload_' . time()]);
			}
		} catch (\Throwable $e) {
			// non-fatal
		}

		return $this->response->setJSON([
			'success' => $msg,
			'files' => $saved,
			'academic_year' => $yearId,
		]);
	}

	/** Recursively delete a directory. */
	private function wipeDir(string $dir): void
	{
		if (!is_dir($dir)) {
			return;
		}
		$it = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ($it as $file) {
			/** @var \SplFileInfo $file */
			if ($file->isDir()) {
				@rmdir($file->getPathname());
			} else {
				@unlink($file->getPathname());
			}
		}
		@rmdir($dir);
	}

	/** Expand nested .zip files found inside an extracted pedagogical package. */
	private function expandNestedZipsInDir(string $dir, int $depth = 0): void
	{
		if ($depth > 3 || !is_dir($dir) || !class_exists(\ZipArchive::class)) {
			return;
		}
		$rii = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
		);
		$zips = [];
		foreach ($rii as $file) {
			/** @var \SplFileInfo $file */
			if ($file->isFile() && strtolower($file->getExtension()) === 'zip') {
				$zips[] = $file->getPathname();
			}
		}
		foreach ($zips as $zipPath) {
			$subDest = $zipPath . '_extracted';
			if (is_dir($subDest)) {
				continue;
			}
			@mkdir($subDest, 0755, true);
			$zip = new \ZipArchive();
			if ($zip->open($zipPath) === true) {
				$zip->extractTo($subDest);
				$zip->close();
				$this->expandNestedZipsInDir($subDest, $depth + 1);
			}
		}
	}

	public function delete_pedagogical_document()
	{
		$this->_preset(1, 3);
		$this->ensurePedagogicalDocsSchema();
		$schoolId = (int) $this->session->get('soma_school_id');
		$id = (int) $this->request->getPost('id');
		$yearId = (int) ($this->data['academic_year'] ?? 0);
		$mdl = new ClassPedagogicalDocModel();
		$row = $mdl->where('id', $id)->where('school_id', $schoolId)->first();
		if (!$row) {
			return $this->response->setJSON(['error' => 'Document not found']);
		}
		if ($yearId > 0 && (int) ($row['academic_year'] ?? 0) !== $yearId) {
			return $this->response->setJSON(['error' => 'This document belongs to another academic year']);
		}
		$path = FCPATH . 'assets/documents/pedagogical/' . $row['file_name'];
		if (is_file($path)) {
			@unlink($path);
		}
		$mdl->delete($id);
		return $this->response->setJSON(['success' => 'Document deleted for current academic year']);
	}

	/** Posts allowed for AI academic plans (DoS, HM, Headmistress, Principal, IT, Librarian, Matron, Patron). */
	private function academicPlanPosts(): array
	{
		return GeminiAcademicDocs::ALLOWED_POSTS;
	}

	private function requireAcademicPlanAccess(): void
	{
		$this->_preset(...$this->academicPlanPosts());
		if (!_is_allowed($this->academicPlanPosts())) {
			header('location: ' . base_url('dashboard'));
			die();
		}
	}

	private function ensureCoursesMetaSchema(): void
	{
		static $done = false;
		if ($done) {
			return;
		}
		try {
			$db = \Config\Database::connect();
			if (!$db->tableExists('courses')) {
				$done = true;
				return;
			}
			$fields = $db->getFieldNames('courses');
			if (!in_array('program_type', $fields, true)) {
				$db->query("ALTER TABLE `courses` ADD COLUMN `program_type` varchar(16) NOT NULL DEFAULT 'tvet' AFTER `marks`");
				$fields[] = 'program_type';
			}
			if (!in_array('create_source', $fields, true)) {
				$db->query("ALTER TABLE `courses` ADD COLUMN `create_source` varchar(16) NOT NULL DEFAULT 'manual' AFTER `program_type`");
			}
		} catch (\Throwable $e) {
			log_message('error', 'ensureCoursesMetaSchema: ' . $e->getMessage());
		}
		$done = true;
	}

	private function ensureAcademicPlansSchema(): void
	{
		static $done = false;
		if ($done) {
			return;
		}
		$db = \Config\Database::connect();
		if (!$db->tableExists('academic_plans')) {
			$db->query("CREATE TABLE IF NOT EXISTS `academic_plans` (
			  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
			  `school_id` int(11) NOT NULL,
			  `class_id` int(11) NOT NULL,
			  `course_id` int(11) DEFAULT NULL,
			  `academic_year` int(11) DEFAULT NULL,
			  `plan_type` varchar(32) NOT NULL,
			  `program_type` varchar(16) NOT NULL DEFAULT 'tvet',
			  `title` varchar(255) NOT NULL,
			  `week_number` int(11) DEFAULT NULL,
			  `term` tinyint(4) DEFAULT NULL,
			  `topic` varchar(255) DEFAULT NULL,
			  `lecturer_id` int(11) DEFAULT NULL,
			  `content_html` longtext,
			  `content_json` longtext,
			  `created_by` int(11) DEFAULT NULL,
			  `created_at` datetime DEFAULT NULL,
			  `updated_at` datetime DEFAULT NULL,
			  PRIMARY KEY (`id`),
			  KEY `idx_school_class_year` (`school_id`,`class_id`,`academic_year`),
			  KEY `idx_plan_type` (`plan_type`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
		}
		if (!$db->tableExists('academic_ai_analyses')) {
			$db->query("CREATE TABLE IF NOT EXISTS `academic_ai_analyses` (
			  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
			  `school_id` int(11) NOT NULL,
			  `class_id` int(11) NOT NULL,
			  `academic_year` int(11) DEFAULT NULL,
			  `program_type` varchar(16) DEFAULT NULL,
			  `source_hash` varchar(64) DEFAULT NULL,
			  `module_count` int(11) DEFAULT 0,
			  `extract_meta` longtext,
			  `source_text` longtext,
			  `chronogram_text` longtext,
			  `analysis_json` longtext,
			  `created_by` int(11) DEFAULT NULL,
			  `created_at` datetime DEFAULT NULL,
			  `updated_at` datetime DEFAULT NULL,
			  PRIMARY KEY (`id`),
			  UNIQUE KEY `uniq_class_year` (`school_id`,`class_id`,`academic_year`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
		} else {
			$fields = $db->getFieldNames('academic_ai_analyses');
			if (!in_array('source_hash', $fields, true)) {
				$db->query("ALTER TABLE `academic_ai_analyses` ADD COLUMN `source_hash` varchar(64) DEFAULT NULL AFTER `program_type`");
			}
			if (!in_array('module_count', $fields, true)) {
				$db->query("ALTER TABLE `academic_ai_analyses` ADD COLUMN `module_count` int(11) DEFAULT 0 AFTER `source_hash`");
			}
			if (!in_array('extract_meta', $fields, true)) {
				$db->query("ALTER TABLE `academic_ai_analyses` ADD COLUMN `extract_meta` longtext AFTER `module_count`");
			}
			if (!in_array('source_text', $fields, true)) {
				$db->query("ALTER TABLE `academic_ai_analyses` ADD COLUMN `source_text` longtext AFTER `extract_meta`");
			}
			if (!in_array('chronogram_text', $fields, true)) {
				$db->query("ALTER TABLE `academic_ai_analyses` ADD COLUMN `chronogram_text` longtext AFTER `source_text`");
			}
		}
		$dir = FCPATH . 'assets/documents/academic_plans';
		if (!is_dir($dir)) {
			@mkdir($dir, 0755, true);
		}
		$done = true;
	}

	/**
	 * Academic AI plans page — Scheme of Work + Session/Lesson plans from curriculum & chronogram.
	 */
	public function academic_plans()
	{
		return redirect()->to(base_url('ped_analyse'));
	}

	public function ped_analyse()
	{
		return $this->renderPedagogicalPage('analyse', 'Analyse Curriculum & Chronogram', 'Extract courses from curriculum and map chronogram weeks/hours (cached in DB).');
	}

	public function ped_scheme_of_work()
	{
		return $this->renderPedagogicalPage('scheme', 'Scheme of Work', 'Select an extracted course and generate a weekly Scheme of Work mapped to the chronogram.');
	}

	public function ped_session_plan()
	{
		return $this->renderPedagogicalPage('session', 'Session Plan', 'Pick a Scheme of Work topic/week and generate a weekly Session Plan.');
	}

	private function renderPedagogicalPage(string $section, string $title, string $subtitle)
	{
		$this->requireAcademicPlanAccess();
		$this->ensureAcademicPlansSchema();
		$this->ensurePedagogicalDocsSchema();
		$data = $this->data;
		$schoolId = (int) $this->session->get('soma_school_id');
		$yearId = (int) ($this->data['academic_year'] ?? 0);

		$classMdl = new ClassesModel();
		$data['classes'] = $classMdl->select("classes.id,classes.title,d.title as department_name,d.code as dept_code,l.id as level_id,l.title as level_name,f.type as faculty_type,f.abbrev as faculty_code,f.title as faculty_title")
			->join('departments d', 'd.id=classes.department')
			->join('levels l', 'l.id=classes.level')
			->join('faculty f', 'f.id=d.faculty_id')
			->where('classes.school_id', $schoolId)
			->orderBy('l.id', 'ASC')
			->orderBy('classes.title', 'ASC')
			->get()->getResultArray();

		$pedMdl = new ClassPedagogicalDocModel();
		$data['pedagogical_docs'] = $yearId > 0
			? $pedMdl->where('school_id', $schoolId)->where('academic_year', $yearId)->findAll()
			: [];

		$planMdl = new AcademicPlanModel();
		$allPlans = $yearId > 0
			? $planMdl->where('school_id', $schoolId)->where('academic_year', $yearId)->orderBy('id', 'DESC')->findAll(120)
			: [];
		$data['saved_plans'] = $allPlans;
		$data['scheme_plans'] = array_values(array_filter($allPlans, static function ($p) {
			return ($p['plan_type'] ?? '') === 'scheme_of_work';
		}));
		$data['session_plans'] = array_values(array_filter($allPlans, static function ($p) {
			return in_array($p['plan_type'] ?? '', ['session_plan', 'lesson_plan'], true);
		}));

		$ai = new GeminiAcademicDocs();
		$data['gemini_ready'] = $ai->isConfigured();

		$cacheMdl = new AcademicAiAnalysisModel();
		$cacheRows = $yearId > 0
			? $cacheMdl->select('id,class_id,program_type,module_count,source_hash,updated_at,analysis_json')
				->where('school_id', $schoolId)->where('academic_year', $yearId)->findAll()
			: [];
		$cacheByClass = [];
		foreach ($cacheRows as $row) {
			$decoded = json_decode($row['analysis_json'] ?? '', true);
			$cacheByClass[(int) $row['class_id']] = [
				'id' => (int) $row['id'],
				'module_count' => (int) ($row['module_count'] ?? 0),
				'program_type' => $row['program_type'] ?? '',
				'updated_at' => $row['updated_at'] ?? '',
				'has_cache' => !empty($decoded['modules']),
				'modules' => is_array($decoded['modules'] ?? null) ? $decoded['modules'] : [],
				'analysis' => is_array($decoded) ? $decoded : null,
			];
		}
		$data['analysis_cache'] = $cacheByClass;
		$data['ped_section'] = $section;

		$data['title'] = $title;
		$data['subtitle'] = $subtitle;
		$data['page'] = 'academic_plans';
		$view = 'pages/ped/' . $section;
		$data['content'] = view($view, $data);
		return view('main', $data);
	}

	/** Build DB context for Gemini matching. */
	private function buildAcademicAiContext(int $schoolId, int $classId, int $yearId): array
	{
		$school = (new SchoolModel())->select('id,name,acronym,address,phone,email,website,slogan,head_master')
			->where('id', $schoolId)->first();
		$class = (new ClassesModel())->select("classes.id,classes.title,d.title as department_name,d.code as dept_code,l.id as level_id,l.title as level_name,f.type as faculty_type,f.abbrev as faculty_code,f.title as faculty_title")
			->join('departments d', 'd.id=classes.department')
			->join('levels l', 'l.id=classes.level')
			->join('faculty f', 'f.id=d.faculty_id')
			->where('classes.id', $classId)
			->where('classes.school_id', $schoolId)
			->first();
		$levels = (new LevelsModel())->select('levels.id,levels.title,levels.type,levels.faculty_id')
			->orderBy('levels.id', 'ASC')
			->findAll();
		$courses = [];
		if ($yearId > 0) {
			$courses = (new CourseModel())->select("courses.id,courses.title,courses.code,courses.credit,r.lecturer as lecturer_id,concat(s.fname,' ',s.lname) as mentor_name,r.id as record_id,r.term")
				->join('course_records r', 'courses.id=r.course')
				->join('staffs s', 's.id=r.lecturer', 'left')
				->where('courses.school_id', $schoolId)
				->where('r.class', $classId)
				->where('r.year', $yearId)
				->groupBy('courses.id')
				->get()->getResultArray();
		}
		return [
			'school' => $school ?: [],
			'class' => $class ?: [],
			'levels' => $levels,
			'courses' => $courses,
			'academic_year_title' => $this->data['academic_year_title'] ?? '',
			'academic_year_id' => $yearId,
		];
	}

	public function ai_analyze_curriculum()
	{
		$this->requireAcademicPlanAccess();
		$this->ensureAcademicPlansSchema();
		$this->ensurePedagogicalDocsSchema();
		@ini_set('max_execution_time', '0');
		@set_time_limit(0);
		@ignore_user_abort(true);
		$schoolId = (int) $this->session->get('soma_school_id');
		$classId = (int) $this->request->getPost('class_id');
		$yearId = (int) ($this->data['academic_year'] ?? 0);
		$force = (int) $this->request->getPost('force') === 1;
		$createdBy = (int) $this->session->get('soma_id');

		// Release session lock so progress polling can read updates while this runs
		if (session_status() === PHP_SESSION_ACTIVE) {
			session_write_close();
		}

		if ($yearId <= 0) {
			return $this->response->setJSON(['error' => 'No active academic year']);
		}
		$ctx = $this->buildAcademicAiContext($schoolId, $classId, $yearId);
		if (empty($ctx['class']['id'])) {
			return $this->response->setJSON(['error' => 'Class not found']);
		}

		try {
			return $this->runAiAnalyzeCurriculum($schoolId, $classId, $yearId, $force, $ctx, $createdBy);
		} catch (\Throwable $e) {
			$this->writeAiProgress($schoolId, $classId, $yearId, 0, 'Analysis failed: ' . $e->getMessage(), ['status' => 'error']);
			log_message('error', 'ai_analyze_curriculum: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
			return $this->response->setJSON([
				'error' => 'Analysis failed: ' . $e->getMessage(),
			]);
		}
	}

	public function ai_analyze_progress()
	{
		$this->requireAcademicPlanAccess();
		$schoolId = (int) $this->session->get('soma_school_id');
		$classId = (int) ($this->request->getGet('class_id') ?: $this->request->getPost('class_id'));
		$yearId = (int) ($this->data['academic_year'] ?? 0);
		// Do not hold session while serving progress JSON
		if (session_status() === PHP_SESSION_ACTIVE) {
			session_write_close();
		}
		$data = $this->readAiProgress($schoolId, $classId, $yearId);
		return $this->response->setJSON($data ?: [
			'pct' => 0,
			'action' => 'Waiting…',
			'status' => 'idle',
			'updated_at' => null,
		]);
	}

	/**
	 * @param array<string,mixed> $ctx
	 */
	private function runAiAnalyzeCurriculum(int $schoolId, int $classId, int $yearId, bool $force, array $ctx, int $createdBy = 0)
	{
		$this->writeAiProgress($schoolId, $classId, $yearId, 0, 'Checking uploaded documents…', ['status' => 'running']);

		$pedMdl = new ClassPedagogicalDocModel();
		$docs = $pedMdl->where('school_id', $schoolId)->where('class_id', $classId)->where('academic_year', $yearId)->findAll();
		$this->writeAiProgress($schoolId, $classId, $yearId, 1, 'Found ' . count($docs) . ' uploaded document(s)…', ['status' => 'running']);
		$curricula = [];
		$chronograms = [];
		foreach ($docs as $d) {
			$path = FCPATH . 'assets/documents/pedagogical/' . $d['file_name'];
			if (!is_file($path)) {
				continue;
			}
			$pack = [
				'path' => $path,
				'original' => $d['original_name'],
				'file_name' => $d['file_name'],
				'updated_at' => $d['updated_at'] ?? ($d['created_at'] ?? ''),
			];
			if ($d['doc_type'] === 'curriculum') {
				$curricula[] = $pack;
			} elseif ($d['doc_type'] === 'chronogram') {
				$chronograms[] = $pack;
			}
		}
		if ($curricula === []) {
			$this->writeAiProgress($schoolId, $classId, $yearId, 0, 'No curriculum uploaded', ['status' => 'error']);
			return $this->response->setJSON(['error' => 'Upload at least one curriculum file for this class in School Settings → Pedagogical documents.']);
		}
		if ($chronograms === []) {
			$this->writeAiProgress($schoolId, $classId, $yearId, 0, 'No chronogram uploaded', ['status' => 'error']);
			return $this->response->setJSON(['error' => 'Upload at least one chronogram for this class in School Settings → Pedagogical documents (needed to map weeks & hours).']);
		}

		// Prefer General Information / structure PDF as primary when multiple curricula uploaded
		usort($curricula, static function ($a, $b) {
			$ka = \App\Libraries\DocumentTextExtractor::curriculumFileSortKey(($a['original'] ?? '') . ' ' . ($a['path'] ?? ''));
			$kb = \App\Libraries\DocumentTextExtractor::curriculumFileSortKey(($b['original'] ?? '') . ' ' . ($b['path'] ?? ''));
			return $ka <=> $kb;
		});
		$curriculum = $curricula[0];
		$chronogram = $chronograms[0];
		$sourceHash = $this->pedagogicalSourceHashMulti($curricula, $chronograms);
		// Include package folder mtime in hash when ZIP was expanded
		$pkgDir = FCPATH . 'assets/documents/pedagogical/pkg_' . $schoolId . '_' . $classId . '_y' . $yearId;
		if (is_dir($pkgDir)) {
			$sourceHash = hash('sha256', $sourceHash . '|pkg|' . $this->dirFingerprint($pkgDir));
		}

		$cacheMdl = new AcademicAiAnalysisModel();
		$cached = $cacheMdl->where('school_id', $schoolId)->where('class_id', $classId)->where('academic_year', $yearId)->first();

		// Serve DB cache whenever files unchanged AND extract is complete — do NOT re-call Gemini
		if (!$force && $cached && !empty($cached['analysis_json'])) {
			$decoded = json_decode($cached['analysis_json'], true);
			$hashOk = empty($cached['source_hash']) || hash_equals((string) $cached['source_hash'], $sourceHash);
			if (is_array($decoded) && $hashOk && !empty($decoded['modules'])) {
				$stats = $this->countAnalysisStats($decoded);
				// Incomplete cache (0 LO/IC while package likely has module PDFs) → re-analyse
				$pkgPdfCount = 0;
				if (is_dir($pkgDir)) {
					$rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($pkgDir, \FilesystemIterator::SKIP_DOTS));
					foreach ($rii as $file) {
						/** @var \SplFileInfo $file */
						if ($file->isFile() && strtolower($file->getExtension()) === 'pdf') {
							$pkgPdfCount++;
						}
					}
				}
				$incomplete = $stats['lo_count'] === 0 && ($pkgPdfCount >= 3 || count($curricula) >= 3);
				$hoursMissing = $stats['chronogram_slots'] === 0 && $stats['module_count'] > 0;
				// Partial extract: some LO but not all modules — continue from resume
				$partialResume = $stats['lo_count'] > 0 && $stats['lo_count'] < max(3, (int) ($stats['module_count'] * 0.5));
				if (!$incomplete && !$hoursMissing && !$partialResume) {
					$this->writeAiProgress($schoolId, $classId, $yearId, 100, 'Loaded from database cache', ['status' => 'done', 'cached' => true]);
					return $this->response->setJSON([
						'success' => 'Loaded from database cache (no AI call)',
						'analysis' => $decoded,
						'cached' => true,
						'module_count' => $stats['module_count'],
						'lo_count' => $stats['lo_count'],
						'ic_count' => $stats['ic_count'],
						'chronogram_weeks' => $stats['chronogram_weeks'],
						'chronogram_slots' => $stats['chronogram_slots'],
						'needs_reanalyse' => false,
						'source_hash' => $sourceHash,
						'updated_at' => $cached['updated_at'] ?? null,
					]);
				}
				$this->writeAiProgress($schoolId, $classId, $yearId, 2, 'Cached analysis incomplete — re-running extraction…', [
					'status' => 'running',
					'lo_count' => $stats['lo_count'],
					'chronogram_slots' => $stats['chronogram_slots'],
				]);
			}
		}

		$ai = new GeminiAcademicDocs();
		if (!$ai->isConfigured()) {
			$this->writeAiProgress($schoolId, $classId, $yearId, 0, 'Gemini API key missing', ['status' => 'error']);
			return $this->response->setJSON(['error' => 'Gemini API key missing on server']);
		}

		// Resume LO/IC from DB + in-progress snapshot (survives proxy timeout)
		$resumeModules = [];
		if ($cached && !empty($cached['analysis_json'])) {
			$prev = json_decode($cached['analysis_json'], true);
			if (is_array($prev['modules'] ?? null)) {
				$resumeModules = $prev['modules'];
			}
		}
		$progressSnap = $this->readAiProgress($schoolId, $classId, $yearId);
		if (is_array($progressSnap['partial_analysis']['modules'] ?? null)) {
			$resumeModules = $this->mergeResumeModules($resumeModules, $progressSnap['partial_analysis']['modules']);
		}

		$lastPersistAt = 0;
		$ai->onProgress(function (int $pct, string $action, array $meta = []) use ($schoolId, $classId, $yearId, $sourceHash, &$cached, &$lastPersistAt, $createdBy) {
			$meta['status'] = $meta['status'] ?? 'running';
			$this->writeAiProgress($schoolId, $classId, $yearId, $pct, $action, $meta);
			// Persist partial every ~3 modules or after chronogram map so timeout keeps work
			$partial = $meta['partial_analysis'] ?? null;
			$done = (int) ($meta['modules_done'] ?? 0);
			$shouldPersist = is_array($partial) && !empty($partial['modules'])
				&& ($done === 0 || $done % 3 === 0 || $pct >= 88 || ($done - $lastPersistAt) >= 3);
			if ($shouldPersist) {
				$lastPersistAt = $done;
				$this->persistPartialAnalysis($schoolId, $classId, $yearId, $sourceHash, $partial, $cached, $createdBy);
			}
		});

		// Extra curriculum files: additional uploads (General/Specific/CCM PDFs) + ZIP package folder
		$extraFiles = array_slice($curricula, 1);
		if (is_dir($pkgDir)) {
			$rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($pkgDir, \FilesystemIterator::SKIP_DOTS));
			$pkgRoot = realpath($pkgDir) ?: $pkgDir;
			foreach ($rii as $file) {
				/** @var \SplFileInfo $file */
				if (!$file->isFile()) {
					continue;
				}
				$e = strtolower($file->getExtension());
				if (!in_array($e, ['pdf', 'doc', 'docx'], true)) {
					continue;
				}
				$full = $file->getPathname();
				$rel = $file->getFilename();
				if (strpos($full, $pkgRoot) === 0) {
					$rel = ltrim(str_replace('\\', '/', substr($full, strlen($pkgRoot))), '/');
				}
				$extraFiles[] = ['path' => $full, 'original' => $rel];
			}
		}

		$extraChronograms = array_slice($chronograms, 1);
		$chrPkgDir = FCPATH . 'assets/documents/pedagogical/pkg_' . $schoolId . '_' . $classId . '_y' . $yearId . '_chr';
		if (is_dir($chrPkgDir)) {
			$rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($chrPkgDir, \FilesystemIterator::SKIP_DOTS));
			$pkgRoot = realpath($chrPkgDir) ?: $chrPkgDir;
			foreach ($rii as $file) {
				/** @var \SplFileInfo $file */
				if (!$file->isFile()) {
					continue;
				}
				$e = strtolower($file->getExtension());
				if (!in_array($e, ['pdf', 'doc', 'docx'], true)) {
					continue;
				}
				$full = $file->getPathname();
				$rel = $file->getFilename();
				if (strpos($full, $pkgRoot) === 0) {
					$rel = ltrim(str_replace('\\', '/', substr($full, strlen($pkgRoot))), '/');
				}
				$extraChronograms[] = ['path' => $full, 'original' => $rel];
			}
		}
		$this->writeAiProgress($schoolId, $classId, $yearId, 0, 'Starting AI analysis…', [
			'status' => 'running',
			'curriculum_files' => count($curricula),
			'chronogram_files' => count($chronograms),
		]);
		$analysis = $ai->analyzeCurriculum($curriculum, $chronogram, $ctx, $extraFiles, $extraChronograms, $resumeModules);
		if ($analysis === null) {
			$err = 'AI analysis failed: ' . $ai->lastError();
			$this->writeAiProgress($schoolId, $classId, $yearId, 0, $err, ['status' => 'error']);
			return $this->response->setJSON(['error' => $err]);
		}

		$modules = is_array($analysis['modules'] ?? null) ? $analysis['modules'] : [];
		$extractMeta = $analysis['_extract_meta'] ?? null;
		$sourceText = $analysis['_source_text'] ?? null;
		$chronogramText = $analysis['_chronogram_text'] ?? null;
		unset($analysis['_extract_meta'], $analysis['_source_text'], $analysis['_chronogram_text']);

		$this->writeAiProgress($schoolId, $classId, $yearId, 96, 'Saving analysis to database…', ['status' => 'running']);
		$payload = [
			'school_id' => $schoolId,
			'class_id' => $classId,
			'academic_year' => $yearId,
			'program_type' => $analysis['program_type'] ?? (((int)($ctx['class']['faculty_type'] ?? 1) === 2) ? 'reb' : 'tvet'),
			'source_hash' => $sourceHash,
			'module_count' => count($modules),
			'extract_meta' => $extractMeta ? json_encode($extractMeta, JSON_UNESCAPED_UNICODE) : null,
			'source_text' => $sourceText,
			'chronogram_text' => $chronogramText,
			'analysis_json' => json_encode($analysis, JSON_UNESCAPED_UNICODE),
			'created_by' => $createdBy ?: (int) $this->session->get('soma_id'),
		];
		if ($cached) {
			$cacheMdl->update($cached['id'], $payload);
		} else {
			$cacheMdl->insert($payload);
		}

		$stats = $this->countAnalysisStats($analysis);
		$this->writeAiProgress($schoolId, $classId, $yearId, 100, 'Full analysis saved', [
			'status' => 'done',
			'modules' => $stats['module_count'],
			'lo' => $stats['lo_count'],
			'ic' => $stats['ic_count'],
		]);

		return $this->response->setJSON([
			'success' => 'Full curriculum + chronogram analysis saved to database',
			'analysis' => $analysis,
			'cached' => false,
			'module_count' => $stats['module_count'],
			'lo_count' => $stats['lo_count'],
			'ic_count' => $stats['ic_count'],
			'chronogram_weeks' => $stats['chronogram_weeks'],
			'chronogram_slots' => $stats['chronogram_slots'],
			'needs_reanalyse' => $stats['lo_count'] === 0,
			'file_count' => count($extraFiles) + 1,
			'source_hash' => $sourceHash,
		]);
	}

	private function aiProgressPath(int $schoolId, int $classId, int $yearId): string
	{
		$dir = WRITEPATH . 'ai_progress';
		if (!is_dir($dir)) {
			@mkdir($dir, 0755, true);
		}
		return $dir . '/analyze_' . $schoolId . '_' . $classId . '_y' . $yearId . '.json';
	}

	/** @param array<string,mixed> $meta */
	private function writeAiProgress(int $schoolId, int $classId, int $yearId, int $pct, string $action, array $meta = []): void
	{
		$path = $this->aiProgressPath($schoolId, $classId, $yearId);
		$payload = array_merge($meta, [
			'pct' => max(0, min(100, $pct)),
			'action' => $action,
			'updated_at' => date('c'),
			'class_id' => $classId,
			'academic_year' => $yearId,
		]);
		@file_put_contents($path, json_encode($payload, JSON_UNESCAPED_UNICODE), LOCK_EX);
		// Help PHP-FPM free buffers so concurrent progress polls see updates promptly
		if (function_exists('clearstatcache')) {
			clearstatcache(true, $path);
		}
	}

	/**
	 * Save in-progress analysis so proxy timeout does not lose LO/IC work.
	 *
	 * @param array<string,mixed> $partial
	 * @param array<string,mixed>|null $cached
	 */
	private function persistPartialAnalysis(int $schoolId, int $classId, int $yearId, string $sourceHash, array $partial, &$cached, int $createdBy = 0): void
	{
		try {
			$modules = is_array($partial['modules'] ?? null) ? $partial['modules'] : [];
			if ($modules === []) {
				return;
			}
			$stats = $this->countAnalysisStats($partial);
			$cacheMdl = new AcademicAiAnalysisModel();
			$payload = [
				'school_id' => $schoolId,
				'class_id' => $classId,
				'academic_year' => $yearId,
				'program_type' => $partial['program_type'] ?? 'tvet',
				'source_hash' => $sourceHash,
				'module_count' => count($modules),
				'extract_meta' => json_encode([
					'partial' => true,
					'lo_count' => $stats['lo_count'],
					'ic_count' => $stats['ic_count'],
					'module_count' => $stats['module_count'],
					'saved_at' => date('c'),
				], JSON_UNESCAPED_UNICODE),
				'analysis_json' => json_encode($partial, JSON_UNESCAPED_UNICODE),
				'created_by' => $createdBy,
			];
			if ($cached && !empty($cached['id'])) {
				$cacheMdl->update($cached['id'], $payload);
			} else {
				$id = $cacheMdl->insert($payload);
				$cached = ['id' => $id];
			}
		} catch (\Throwable $e) {
			log_message('error', 'persistPartialAnalysis: ' . $e->getMessage());
		}
	}

	/**
	 * Prefer modules that already have LO/IC when merging resume sources.
	 *
	 * @param list<array<string,mixed>> $base
	 * @param list<array<string,mixed>> $extra
	 * @return list<array<string,mixed>>
	 */
	private function mergeResumeModules(array $base, array $extra): array
	{
		$byCode = [];
		foreach (array_merge($base, $extra) as $m) {
			if (!is_array($m)) {
				continue;
			}
			$code = \App\Libraries\DocumentTextExtractor::cleanModuleCode((string) ($m['code'] ?? ''));
			if ($code === '') {
				continue;
			}
			$lo = is_array($m['learning_outcomes'] ?? null) ? count($m['learning_outcomes']) : 0;
			$prevLo = isset($byCode[$code]) && is_array($byCode[$code]['learning_outcomes'] ?? null)
				? count($byCode[$code]['learning_outcomes']) : 0;
			if (!isset($byCode[$code]) || $lo > $prevLo) {
				$byCode[$code] = $m;
			}
		}
		return array_values($byCode);
	}

	/** @return array<string,mixed>|null */
	private function readAiProgress(int $schoolId, int $classId, int $yearId): ?array
	{
		$path = $this->aiProgressPath($schoolId, $classId, $yearId);
		if (!is_file($path)) {
			return null;
		}
		$raw = @file_get_contents($path);
		$data = is_string($raw) ? json_decode($raw, true) : null;
		return is_array($data) ? $data : null;
	}

	private function dirFingerprint(string $dir): string
	{
		$bits = [];
		if (!is_dir($dir)) {
			return 'missing';
		}
		$rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
		foreach ($rii as $file) {
			/** @var \SplFileInfo $file */
			if ($file->isFile()) {
				$bits[] = $file->getFilename() . ':' . $file->getSize() . ':' . $file->getMTime();
			}
		}
		sort($bits);
		return hash('sha256', implode('|', $bits));
	}

	/**
	 * @param array<string,mixed> $analysis
	 * @return array{module_count:int,lo_count:int,ic_count:int,chronogram_weeks:int,chronogram_slots:int}
	 */
	private function countAnalysisStats(array $analysis): array
	{
		$modules = is_array($analysis['modules'] ?? null) ? $analysis['modules'] : [];
		$loTotal = 0;
		$icTotal = 0;
		$slotTotal = 0;
		foreach ($modules as $m) {
			foreach ($m['learning_outcomes'] ?? [] as $lo) {
				$loTotal++;
				$icTotal += is_array($lo['indicative_contents'] ?? null) ? count($lo['indicative_contents']) : 0;
			}
			$slotTotal += is_array($m['chronogram_slots'] ?? null) ? count($m['chronogram_slots']) : 0;
		}
		$weekTotal = is_array($analysis['chronogram']['weeks'] ?? null) ? count($analysis['chronogram']['weeks']) : 0;
		if ($weekTotal === 0 && isset($analysis['hours_distribution']['total_weeks'])) {
			$weekTotal = (int) $analysis['hours_distribution']['total_weeks'];
		}

		return [
			'module_count' => count($modules),
			'lo_count' => $loTotal,
			'ic_count' => $icTotal,
			'chronogram_weeks' => $weekTotal,
			'chronogram_slots' => $slotTotal,
		];
	}

	/**
	 * Fingerprint all curriculum + chronogram uploads so cache invalidates when any file changes.
	 *
	 * @param list<array{path:string,file_name?:string,updated_at?:string}> $curricula
	 * @param list<array{path:string,file_name?:string,updated_at?:string}> $chronograms
	 */
	private function pedagogicalSourceHashMulti(array $curricula, array $chronograms): string
	{
		$bits = [];
		foreach (array_merge($curricula, $chronograms) as $f) {
			$path = $f['path'] ?? '';
			$bits[] = ($f['file_name'] ?? basename($path))
				. '|' . (is_file($path) ? (filesize($path) . '|' . filemtime($path)) : 'missing')
				. '|' . ($f['updated_at'] ?? '');
		}
		sort($bits);
		return hash('sha256', implode('||', $bits));
	}

	/**
	 * @deprecated use pedagogicalSourceHashMulti
	 * @param array{path:string,file_name?:string,updated_at?:string} $curriculum
	 * @param array{path:string,file_name?:string,updated_at?:string} $chronogram
	 */
	private function pedagogicalSourceHash(array $curriculum, array $chronogram): string
	{
		return $this->pedagogicalSourceHashMulti([$curriculum], [$chronogram]);
	}

	public function ai_generate_scheme_of_work()
	{
		$this->requireAcademicPlanAccess();
		$this->ensureAcademicPlansSchema();
		set_time_limit(240);
		$schoolId = (int) $this->session->get('soma_school_id');
		$classId = (int) $this->request->getPost('class_id');
		$yearId = (int) ($this->data['academic_year'] ?? 0);
		$moduleJson = $this->request->getPost('module');
		$module = is_string($moduleJson) ? json_decode($moduleJson, true) : (array) $moduleJson;
		if (!is_array($module) || empty($module)) {
			return $this->response->setJSON(['error' => 'Module payload required']);
		}

		$ctx = $this->buildAcademicAiContext($schoolId, $classId, $yearId);
		$cache = (new AcademicAiAnalysisModel())->where('school_id', $schoolId)->where('class_id', $classId)->where('academic_year', $yearId)->first();
		$analysis = $cache ? json_decode($cache['analysis_json'] ?? '', true) : [];
		$programType = (string) ($analysis['program_type'] ?? (((int)($ctx['class']['faculty_type'] ?? 1) === 2) ? 'reb' : 'tvet'));
		$chrono = is_array($analysis) ? ($analysis['chronogram'] ?? null) : null;
		// Prefer full module (with chronogram_slots) from saved analysis
		if (is_array($analysis) && !empty($analysis['modules']) && !empty($module['code'])) {
			$code = strtoupper(trim((string) $module['code']));
			foreach ($analysis['modules'] as $am) {
				if (strcasecmp((string) ($am['code'] ?? ''), $code) === 0) {
					$module = array_merge($am, $module);
					if (empty($module['chronogram_slots']) && !empty($am['chronogram_slots'])) {
						$module['chronogram_slots'] = $am['chronogram_slots'];
					}
					break;
				}
			}
		}

		$ai = new GeminiAcademicDocs();
		$result = $ai->generateSchemeOfWork($module, $ctx, $programType, $chrono);
		if ($result === null) {
			return $this->response->setJSON(['error' => 'Scheme generation failed: ' . $ai->lastError()]);
		}

		$planMdl = new AcademicPlanModel();
		$courseId = (int) ($module['matched_course_id'] ?? 0) ?: null;
		$lecturerId = (int) ($module['teacher_id'] ?? 0) ?: null;
		// Replace previous SOW for same class+course+year
		if ($courseId) {
			$planMdl->where('school_id', $schoolId)->where('class_id', $classId)->where('academic_year', $yearId)
				->where('course_id', $courseId)->where('plan_type', 'scheme_of_work')->delete();
		}
		$id = $planMdl->insert([
			'school_id' => $schoolId,
			'class_id' => $classId,
			'course_id' => $courseId,
			'academic_year' => $yearId,
			'plan_type' => 'scheme_of_work',
			'program_type' => $programType,
			'title' => $result['title'],
			'week_number' => null,
			'term' => null,
			'topic' => $module['title'] ?? ($module['code'] ?? ''),
			'lecturer_id' => $lecturerId,
			'content_html' => $result['html'],
			'content_json' => json_encode($result['json'], JSON_UNESCAPED_UNICODE),
			'created_by' => (int) $this->session->get('soma_id'),
		]);

		return $this->response->setJSON([
			'success' => 'Scheme of Work generated',
			'plan_id' => $id,
			'title' => $result['title'],
			'topics' => $result['json']['topics_for_sessions'] ?? [],
			'preview_url' => base_url('view_academic_plan/' . $id),
		]);
	}

	public function ai_generate_session_plan()
	{
		$this->requireAcademicPlanAccess();
		$this->ensureAcademicPlansSchema();
		set_time_limit(240);
		$schoolId = (int) $this->session->get('soma_school_id');
		$classId = (int) $this->request->getPost('class_id');
		$schemeId = (int) $this->request->getPost('scheme_id');
		$yearId = (int) ($this->data['academic_year'] ?? 0);
		$topicJson = $this->request->getPost('topic');
		$topic = is_string($topicJson) ? json_decode($topicJson, true) : (array) $topicJson;
		$moduleJson = $this->request->getPost('module');
		$module = is_string($moduleJson) ? json_decode($moduleJson, true) : (array) $moduleJson;
		if (!is_array($topic) || empty($topic)) {
			return $this->response->setJSON(['error' => 'Select a topic / week from the Scheme of Work']);
		}

		$planMdl = new AcademicPlanModel();
		$scheme = $planMdl->where('id', $schemeId)->where('school_id', $schoolId)->where('plan_type', 'scheme_of_work')->first();
		if (!$scheme) {
			return $this->response->setJSON(['error' => 'Generate a Scheme of Work for this course first']);
		}
		$schemeJson = json_decode($scheme['content_json'] ?? '', true) ?: [];
		$ctx = $this->buildAcademicAiContext($schoolId, $classId, $yearId);
		$programType = (string) ($scheme['program_type'] ?: 'tvet');
		$planType = $programType === 'reb' ? 'lesson_plan' : 'session_plan';

		$ai = new GeminiAcademicDocs();
		$result = $ai->generateSessionOrLessonPlan(is_array($module) ? $module : [], $schemeJson, $topic, $ctx, $programType);
		if ($result === null) {
			return $this->response->setJSON(['error' => 'Plan generation failed: ' . $ai->lastError()]);
		}

		$id = $planMdl->insert([
			'school_id' => $schoolId,
			'class_id' => $classId,
			'course_id' => $scheme['course_id'],
			'academic_year' => $yearId,
			'plan_type' => $planType,
			'program_type' => $programType,
			'title' => $result['title'],
			'week_number' => (int) ($topic['week'] ?? 0) ?: null,
			'term' => (int) ($topic['term'] ?? 0) ?: null,
			'topic' => $topic['topic'] ?? ($topic['ic_title'] ?? ''),
			'lecturer_id' => $scheme['lecturer_id'],
			'content_html' => $result['html'],
			'content_json' => json_encode($result['json'], JSON_UNESCAPED_UNICODE),
			'created_by' => (int) $this->session->get('soma_id'),
		]);

		$label = $planType === 'lesson_plan' ? 'Lesson Plan' : 'Session Plan';
		return $this->response->setJSON([
			'success' => $label . ' generated',
			'plan_id' => $id,
			'title' => $result['title'],
			'preview_url' => base_url('view_academic_plan/' . $id),
		]);
	}

	public function view_academic_plan($id = 0)
	{
		$this->requireAcademicPlanAccess();
		$this->ensureAcademicPlansSchema();
		$schoolId = (int) $this->session->get('soma_school_id');
		$row = (new AcademicPlanModel())->where('id', (int) $id)->where('school_id', $schoolId)->first();
		if (!$row) {
			return $this->response->setStatusCode(404)->setBody('Plan not found');
		}
		$html = (string) ($row['content_html'] ?? '');
		if ($html === '') {
			$html = '<p>Empty plan</p>';
		}
		// Ensure standalone document
		if (stripos($html, '<html') === false) {
			$html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . esc($row['title']) . '</title></head><body>' . $html . '</body></html>';
		}
		return $this->response->setHeader('Content-Type', 'text/html; charset=UTF-8')->setBody($html);
	}

	public function download_academic_plan($id = 0)
	{
		$this->requireAcademicPlanAccess();
		$this->ensureAcademicPlansSchema();
		$schoolId = (int) $this->session->get('soma_school_id');
		$row = (new AcademicPlanModel())->where('id', (int) $id)->where('school_id', $schoolId)->first();
		if (!$row) {
			return $this->response->setStatusCode(404)->setBody('Plan not found');
		}
		$html = (string) ($row['content_html'] ?? '');
		$name = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $row['title'] ?? 'plan') . '.doc';
		return $this->response
			->setHeader('Content-Type', 'application/msword')
			->setHeader('Content-Disposition', 'attachment; filename="' . $name . '"')
			->setBody($html);
	}

	public function list_academic_plan_topics()
	{
		$this->requireAcademicPlanAccess();
		$this->ensureAcademicPlansSchema();
		$schoolId = (int) $this->session->get('soma_school_id');
		$schemeId = (int) $this->request->getGet('scheme_id');
		$row = (new AcademicPlanModel())->where('id', $schemeId)->where('school_id', $schoolId)->where('plan_type', 'scheme_of_work')->first();
		if (!$row) {
			return $this->response->setJSON(['error' => 'Scheme not found', 'topics' => []]);
		}
		$json = json_decode($row['content_json'] ?? '', true) ?: [];
		return $this->response->setJSON(['topics' => $json['topics_for_sessions'] ?? [], 'scheme' => ['id' => $row['id'], 'title' => $row['title'], 'program_type' => $row['program_type']]]);
	}

	/**
	 * Save online registration fee + Babyeyi requirement for current school.
	 */
	public function save_application_settings()
	{
		$this->_preset(1, 3);
		$schoolId = (int) $this->session->get('soma_school_id');
		$appMdl = new ApplicationSettingsModel();
		$current = $appMdl->forSchool($schoolId);

		$fees = (int) preg_replace('/\D/', '', (string) $this->request->getPost('registration_fees'));
		$start = trim((string) $this->request->getPost('start_date'));
		$end = trim((string) $this->request->getPost('end_date'));
		$babyeyi = (int) $this->request->getPost('babyeyi_required') === 1 ? 1 : 0;

		if ($fees < 0) {
			return $this->response->setJSON(['error' => 'Registration fee must be 0 or more']);
		}
		if ($start === '' || $end === '') {
			return $this->response->setJSON(['error' => 'Start and end dates are required']);
		}

		$payload = [
			'id' => (int) ($current['id'] ?? 0),
			'school_id' => $schoolId,
			'registration_fees' => $fees,
			'start_date' => $start,
			'end_date' => $end,
			'babyeyi_required' => $babyeyi,
			'requirement_document' => $current['requirement_document'] ?? '',
			'operator' => (int) ($this->session->get('soma_id') ?: 0),
		];
		try {
			$appMdl->save($payload);
			$fresh = $appMdl->forSchool($schoolId);
			return $this->response->setJSON([
				'success' => 'Online registration settings saved',
				'settings' => $fresh,
			]);
		} catch (\Throwable $e) {
			return $this->response->setStatusCode(500)->setJSON(['error' => $e->getMessage()]);
		}
	}

	/**
	 * Upload requirement PDF (shown on online registration).
	 */
	public function upload_requirement_document()
	{
		$this->_preset(1, 3);
		$schoolId = (int) $this->session->get('soma_school_id');
		$file = $this->request->getFile('file');
		if (!$file || !$file->isValid()) {
			return $this->response->setJSON(['error' => 'Invalid file upload']);
		}
		$ext = strtolower($file->getClientExtension() ?: '');
		if ($ext !== 'pdf') {
			return $this->response->setJSON(['error' => 'Only PDF is allowed for requirement document']);
		}
		if ($file->getSize() > (10 * 1024 * 1024)) {
			return $this->response->setJSON(['error' => 'File too large (max 10MB)']);
		}
		$dir = FCPATH . 'assets/documents/';
		if (!is_dir($dir)) {
			@mkdir($dir, 0775, true);
		}
		$name = 'req_' . $schoolId . '_' . time() . '.pdf';
		$file->move($dir, $name, true);

		$appMdl = new ApplicationSettingsModel();
		$current = $appMdl->forSchool($schoolId);
		$old = (string) ($current['requirement_document'] ?? '');
		$appMdl->save([
			'id' => (int) $current['id'],
			'requirement_document' => $name,
		]);
		if ($old !== '' && $old !== $name && is_file($dir . $old)) {
			@unlink($dir . $old);
		}
		return $this->response->setJSON([
			'success' => 'Requirement PDF uploaded',
			'filename' => $name,
			'url' => base_url('assets/documents/' . $name),
		]);
	}
private function normalizeUID($uid)
{
    // Trim input
    $uid = trim($uid);
    if ($uid === '') return '';

    // Remove spaces and unwanted characters early
    $uid = preg_replace('/\s+/', '', $uid);

    // ==========================
    // DECIMAL → HEX
    // ==========================
    if (ctype_digit($uid)) {
        try {
            // Convert decimal to HEX
            $uid = strtoupper(base_convert($uid, 10, 16));
        } catch (\Throwable $e) {
            log_message('error', 'UID decimal conversion failed: ' . $uid);
            return '';
        }
    }

    // ==========================
    // CLEAN HEX
    // ==========================
    $uid = strtoupper(preg_replace('/[^A-F0-9]/', '', $uid));

    // ==========================
    // VALIDATION (OPTIONAL BUT GOOD)
    // ==========================
    if ($uid === '' || strlen($uid) < 6) {
        log_message('error', 'Invalid UID after normalization: ' . $uid);
        return '';
    }

    return $uid;
}
public function scanCard()
{
    $request = service('request');
    $db = \Config\Database::connect();

    // جلوگیری از کش
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");

    // ==========================
    // GET CARD
    // ==========================
    $cardRaw = trim($request->getPost('card') ?? $request->getGet('card'));

    if (!$cardRaw) {
        return $this->response->setJSON([
            "success" => 0,
            "message" => "Card missing"
        ]);
    }

    // ==========================
    // NORMALIZE
    // ==========================
    $card = $this->normalizeUID($cardRaw);

    // Generate reversed UID (VERY IMPORTANT)
    $reversed = '';
    if (strlen($card) % 2 === 0) {
        $bytes = str_split($card, 2);
        $bytes = array_reverse($bytes);
        $reversed = implode('', $bytes);
    }

    log_message('error', "SCAN RAW: [$cardRaw] → NORMAL: [$card] → REVERSED: [$reversed]");

    // ==========================
    // TIME VARIABLES
    // ==========================
    $academic_year = $this->data['academic_year'] ?? date("Y");
    $time = time();
    $todayStart = strtotime("today");
    $todayEnd   = strtotime("tomorrow") - 1;
    $month = date("m-Y");

    // ==========================
    // FIND STUDENT (TRY BOTH)
    // ==========================
    $student = $db->table('students')
        ->groupStart()
            ->where("UPPER(TRIM(card))", $card)
            ->orWhere("UPPER(TRIM(card))", $reversed)
        ->groupEnd()
        ->get()
        ->getRow();

    if (!$student) {

        log_message('error', "CARD NOT FOUND → Tried: $card | $reversed");

        return $this->response->setJSON([
            "success" => 0,
            "message" => "Card not found"
        ]);
    }

    // ==========================
    // SCHOOL
    // ==========================
    $school_id = $student->school_id;

    $school = $db->table('schools')
        ->select("name,email,phone,logo")
        ->where("id", $school_id)
        ->get()
        ->getRow();

    // ==========================
    // CLASS
    // ==========================
    $className = "";

    $class = $db->table('class_records cr')
        ->select("c.level,c.title")
        ->join("classes c", "c.id = cr.class")
        ->where("cr.student", $student->id)
        ->where("cr.year", $academic_year)
        ->get()
        ->getRow();

    if ($class) {
        $className = "Level {$class->level} {$class->title}";
    }

    // ==========================
    // MONTH RECORDS
    // ==========================
    $records = $db->table('attendance_records')
        ->select("
            GROUP_CONCAT(
                DATE_FORMAT(FROM_UNIXTIME(time_in),'%d %H:%i'),
                ';',
                DATE_FORMAT(FROM_UNIXTIME(time_out),'%d %H:%i')
            ) as records
        ")
        ->where("user_type", 0)
        ->where("user_id", $student->id)
        ->where("DATE_FORMAT(FROM_UNIXTIME(time_in),'%m-%Y') = '$month'", null, false)
        ->get()
        ->getRow()
        ->records ?? "";

    // ==========================
    // PHOTO
    // ==========================
    $photo = profile_photo_url($student->photo ?? null);

    // ==========================
    // ATTENDANCE
    // ==========================
    $attendance = $db->table('attendance_records')
        ->where("user_id", $student->id)
        ->where("school_id", $school_id)
        ->where("time_in >=", $todayStart)
        ->where("time_in <=", $todayEnd)
        ->get()
        ->getRow();

    if (!$attendance) {

        $db->table('attendance_records')->insert([
            "user_id"   => $student->id,
            "user_type" => 0,
            "time_in"   => $time,
            "time_out"  => 0,
            "school_id" => $school_id,
            "shift_id"  => 1
        ]);

        $status = "IN";

    } elseif ($attendance->time_out == 0) {

        $db->table('attendance_records')
            ->where("id", $attendance->id)
            ->update(["time_out" => $time]);

        $status = "OUT";

    } else {

        return $this->response->setJSON([
            "success" => 0,
            "message" => "Already checked out today"
        ]);
    }

    // ==========================
    // RESPONSE
    // ==========================
    return $this->response->setJSON([
        "success" => 1,
        "status"  => $status,

        "student" => [
            "id"      => $student->id,
            "name"    => $student->fname . " " . $student->lname,
            "regno"   => $student->regno,
            "class"   => $className,
            "photo"   => $photo,
            "records" => $records
        ],

        "school" => [
            "name"  => $school->name ?? "",
            "email" => $school->email ?? "",
            "phone" => $school->phone ?? "",
            "logo"  => !empty($school->logo)
                ? base_url('assets/images/logo/' . $school->logo)
                : ""
        ],

        "month" => $month,
        "time"  => date("H:i", $time)
    ]);
}
	public function manipulate_grade()
	{
		$this->_preset();
		$grade = new GradeModel();
		$title = trim((string) $this->request->getPost("color_title"));
		$max = $this->request->getPost("max_point");
		$min = $this->request->getPost("min_point");
		$color = $this->request->getPost("color");
		$schoolId = (int) $this->session->get("soma_school_id");

		// Always lock educational path to Nursery
		$facMdl = new FacultyModel();
		$nursery = $facMdl->like('title', 'Nursery', 'both')->first();
		if (!$nursery) {
			$all = $facMdl->findAll();
			foreach ($all as $f) {
				if (stripos((string) ($f['title'] ?? ''), 'Nursery') !== false) {
					$nursery = $f;
					break;
				}
			}
		}
		if (!$nursery) {
			return $this->response->setJSON(['error' => 'Nursery educational path not found. Create a faculty named Nursery first.']);
		}
		$facId = (int) $nursery['id'];

		if ($title === '') {
			return $this->response->setJSON(['error' => 'Mention is required']);
		}
		if ($max === '' || $min === '' || !is_numeric($max) || !is_numeric($min)) {
			return $this->response->setJSON(['error' => 'Max and Min points must be numbers']);
		}

		$data = [
			'faculty_id' => $facId,
			'school_id' => $schoolId,
			'color_title' => $title,
			'max_point' => $max,
			'min_point' => $min,
			'color' => $color ?: '#22c55e',
			'created_by' => $this->session->get('soma_id'),
		];
		try {
			$grade->save($data);
			$newId = (int) $grade->getInsertID();
			return $this->response->setJSON([
				'success' => lang('app.gradeSaved'),
				'grade' => [
					'id' => $newId,
					'color_title' => $title,
					'max_point' => $max,
					'min_point' => $min,
					'color' => $data['color'],
					'title' => $nursery['title'] ?? 'Nursery',
				],
			]);
		} catch (\Exception $e) {
			return $this->response->setJSON(['error' => 'Error: ' . $e->getMessage()]);
		}
	}
public function attendanceCard()
{
    return view('attendance_card');
}
	public function manipulate_intouch($school_id)
	{
		$this->_preset();
		$data = $this->data;

		$intouchSetting = new IntouchAccount();

		$data_info = [
				"school_id" => $this->session->get("soma_school_id"),
				"username" => $this->request->getPost('intouch_username'),
				"password" => $this->request->getPost('intouch_username'),
		];

		$record_exists = $intouchSetting->where('school_id', $this->session->get("soma_school_id"))->first();

		// var_dump($record_exists);
		try {
			if ($record_exists) {
				$intouchSetting->update($record_exists['id'], $data_info);
			} else {
				$intouchSetting->save($data_info);
			}
			return $this->response->setJSON(array("success" => lang("app.intouchSaved")));
		} catch (\Exception $e) {
			return $this->response->setJSON(array("error" => "Error: " . $e->getMessage()));
		}
	}

	public function delete_grade()
	{
		$this->_preset();
		$data = $this->data;
		$grade = new GradeModel();
		$id = $this->request->getPost("fId");

		try {
			$grade->delete($id);
			return $this->response->setJSON(array("success" => "Grade Deleted"));
		} catch (\Exception $e) {
			return $this->response->setJSON(array("error" => "Error: " . $e->getMessage()));
		}
	}

	public function messaging_parents()
	{
		$this->_preset(1, 3, 4);
		$data = $this->data;
		$faculty = new FacultyModel();
		$stModel = new StudentModel();
		$data['title'] = lang("app.CommunicationPortal");
		$data['faculty'] = $faculty->get()->getResultArray();
		$data['departments'] = $stModel->student_department_phone();
		$data['classes'] = $stModel->student_class_phone();
		$data['subtitle'] = lang("app.parentMessagingPortal");
		$data['page'] = "messaging_parents";
		$data['content'] = view("pages/send_sms", $data);
		return view('main', $data);
	}

	public function messaging_employees()
	{
		$this->_preset(1, 3, 4);
		$data = $this->data;
		$stModel = new StaffModel();
		$data['title'] = lang("app.CommunicationPortal");
		$data['posts'] = $stModel->staff_post_phone();
		$data['subtitle'] = lang("app.employeesMessagingPortal");
		$data['page'] = "messaging_employees";
		$data['content'] = view("pages/send_sms_staff", $data);
		return view('main', $data);
	}

	public function add_course()
	{
		$this->_preset(1, 3);
		$this->ensureCoursesMetaSchema();
		$data = $this->data;
		$faculty = new FacultyModel();
		$staffMdl = new StaffModel();
		$classMdl = new ClassesModel();
		$courseModel = new CourseModel();
		$CourseCategory = new CourseCategoryModel();
		$data['title'] = lang("app.createNewCourse");
		$school_id = $this->session->get("soma_school_id");
		$yearId = (int) ($this->data['academic_year'] ?? 0);

		$data['classes'] = $classMdl->select("classes.id,classes.title,d.title as department_name,d.code as dept_code,d.code,l.title as level_name,f.type as faculty_type,f.abbrev as faculty_code")
			->join('departments d', 'd.id=classes.department')
			->join('levels l', 'l.id=classes.level')
			->join('faculty f', 'f.id=d.faculty_id')
			->where('classes.school_id', $school_id)
			->orderBy('l.id', 'ASC')
			->orderBy('classes.title', 'ASC')
			->get()->getResultArray();

		$courseFields = [];
		try {
			$courseFields = \Config\Database::connect()->getFieldNames('courses');
		} catch (\Throwable $e) {
			$courseFields = [];
		}
		$hasProgramType = in_array('program_type', $courseFields, true);
		$hasCreateSource = in_array('create_source', $courseFields, true);
		$selectCols = 'courses.id,courses.title,courses.code,courses.marks,courses.credit,cs.title as category';
		if ($hasProgramType) {
			$selectCols .= ',courses.program_type';
		}
		if ($hasCreateSource) {
			$selectCols .= ',courses.create_source';
		}
		$this->syncCourseMarksFromCredits((int) $school_id);
		$data['courses'] = $courseModel->select($selectCols)
				->join("course_category cs", "cs.id=courses.category")
				->where("courses.school_id", $school_id)
				->orderBy('courses.title', 'ASC')
				->get()->getResultArray();

		$aiCodeMeta = $this->buildAiCourseCodeMeta($school_id, $yearId);
		$assignmentTypes = $this->courseAssignmentProgramTypes($school_id, $yearId);
		$coursesGrouped = ['tvet' => [], 'reb' => []];
		foreach ($data['courses'] as &$courseRow) {
			try {
				$meta = $this->classifyCourseProgramAndSource($courseRow, $aiCodeMeta, $assignmentTypes);
			} catch (\Throwable $e) {
				$meta = ['program_type' => 'tvet', 'create_source' => 'manual'];
			}
			$courseRow['program_type'] = $meta['program_type'];
			$courseRow['create_source'] = $meta['create_source'];
			$bucket = ($meta['program_type'] === 'reb') ? 'reb' : 'tvet';
			$coursesGrouped[$bucket][] = $courseRow;
		}
		unset($courseRow);
		$data['courses_grouped'] = $coursesGrouped;

		$data['faculty'] = $faculty->get()->getResultArray();
		$data['categories'] = $CourseCategory->where("school_id", $school_id)->get()->getResultArray();
		$data['staffs'] = $staffMdl->where("school_id", $this->session->get("soma_school_id"))->get()->getResultArray();

		// Extracted modules from Pedagogical Documents analysis, grouped by class
		$existingCodes = [];
		foreach ($data['courses'] as $c) {
			$code = strtoupper(trim((string) ($c['code'] ?? '')));
			if ($code !== '') {
				$existingCodes[$code] = (int) $c['id'];
			}
		}
		$smartByClass = [];
		if ($yearId > 0) {
			$cacheRows = (new AcademicAiAnalysisModel())
				->where('school_id', $school_id)
				->where('academic_year', $yearId)
				->findAll();
			foreach ($cacheRows as $row) {
				$cid = (int) ($row['class_id'] ?? 0);
				$decoded = json_decode($row['analysis_json'] ?? '', true);
				$modules = is_array($decoded['modules'] ?? null) ? $decoded['modules'] : [];
				$curText = (string) ($row['source_text'] ?? '');
				if ($curText === '' && !empty($decoded['_source_text'])) {
					$curText = (string) $decoded['_source_text'];
				}
				if (strpos($curText, '===== CHRONOGRAM SOURCE =====') !== false) {
					$curText = explode('===== CHRONOGRAM SOURCE =====', $curText, 2)[0];
				}
				$creditMap = [];
				try {
					$creditMap = \App\Libraries\DocumentTextExtractor::parseCurriculumModuleCredits($curText);
				} catch (\Throwable $e) {
					log_message('error', 'parseCurriculumModuleCredits: ' . $e->getMessage());
				}
				$items = [];
				foreach ($modules as $m) {
					$code = \App\Libraries\DocumentTextExtractor::cleanModuleCode((string) ($m['code'] ?? ''));
					$rawTitle = trim((string) ($m['title'] ?? ''));
					$title = \App\Libraries\DocumentTextExtractor::cleanModuleTitle($rawTitle);
					if ($title === '' && $rawTitle !== '') {
						$title = \App\Libraries\DocumentTextExtractor::cleanPedagogicalText($rawTitle);
					}
					if ($code === '' && $title === '') {
						continue;
					}
					$credit = $this->estimateCreditFromModule($m, $creditMap);
					$cat = $this->categoryTitleFromModuleType((string) ($m['module_type'] ?? ''));
					$items[] = [
						'code' => $code,
						'title' => $title !== '' ? $title : $code,
						'category_title' => $cat,
						'credit' => $credit,
						'marks' => (int) round($credit * 10),
						'already_exists' => ($code !== '' && isset($existingCodes[$code])),
						'existing_course_id' => ($code !== '' && isset($existingCodes[$code])) ? $existingCodes[$code] : null,
						'matched_course_id' => (int) ($m['matched_course_id'] ?? 0) ?: null,
					];
				}
				if ($items !== []) {
					$smartByClass[$cid] = [
						'program_type' => (string) ($row['program_type'] ?? ($decoded['program_type'] ?? '')),
						'module_count' => count($items),
						'modules' => $items,
					];
				}
			}
		}
		$data['smart_by_class'] = $smartByClass;

		$data['subtitle'] = lang("app.createNewCourse");
		$data['page'] = "add_course";
		$data['content'] = view("pages/add_course", $data);
		return view('main', $data);
	}

	/** Force courses.marks = ROUND(credit × 10) for a school (credit 0 => marks 0). */
	private function syncCourseMarksFromCredits(int $schoolId): void
	{
		if ($schoolId <= 0) {
			return;
		}
		try {
			$db = \Config\Database::connect();
			$db->query(
				"UPDATE courses
				 SET marks = ROUND(COALESCE(credit, 0) * 10)
				 WHERE school_id = ?
				   AND CAST(marks AS DECIMAL(12,2)) <> ROUND(COALESCE(credit, 0) * 10)",
				[$schoolId]
			);
		} catch (\Throwable $e) {
			log_message('error', 'syncCourseMarksFromCredits: ' . $e->getMessage());
		}
	}

	/**
	 * @return array<string,array{program_type:string,from_ai:bool}>
	 */
	private function buildAiCourseCodeMeta(int $schoolId, int $yearId): array
	{
		$out = [];
		if ($yearId <= 0) {
			return $out;
		}
		$rows = (new AcademicAiAnalysisModel())
			->where('school_id', $schoolId)
			->where('academic_year', $yearId)
			->findAll();
		foreach ($rows as $row) {
			$prog = strtolower(trim((string) ($row['program_type'] ?? 'tvet')));
			if ($prog !== 'reb') {
				$prog = 'tvet';
			}
			$decoded = json_decode($row['analysis_json'] ?? '', true);
			$modules = is_array($decoded['modules'] ?? null) ? $decoded['modules'] : [];
			foreach ($modules as $m) {
				$code = \App\Libraries\DocumentTextExtractor::cleanModuleCode((string) ($m['code'] ?? ''));
				if ($code !== '') {
					$out[$code] = ['program_type' => $prog, 'from_ai' => true];
				}
			}
		}
		return $out;
	}

	/** @return array<int,list<int>> course_id => faculty types from assignments */
	private function courseAssignmentProgramTypes(int $schoolId, int $yearId): array
	{
		$out = [];
		try {
			$db = \Config\Database::connect();
			$sql = "SELECT cr.course, f.type AS faculty_type
				FROM course_records cr
				INNER JOIN classes c ON c.id = cr.class
				INNER JOIN departments d ON d.id = c.department
				INNER JOIN faculty f ON f.id = d.faculty_id
				WHERE c.school_id = ?";
			$params = [$schoolId];
			if ($yearId > 0) {
				$sql .= " AND cr.year = ?";
				$params[] = $yearId;
			}
			$rows = $db->query($sql, $params)->getResultArray();
			foreach ($rows as $r) {
				$cid = (int) ($r['course'] ?? 0);
				if ($cid <= 0) {
					continue;
				}
				$out[$cid][] = (int) ($r['faculty_type'] ?? 1);
			}
		} catch (\Throwable $e) {
			log_message('error', 'courseAssignmentProgramTypes: ' . $e->getMessage());
		}
		return $out;
	}

	/**
	 * @param array<string,array{program_type:string,from_ai:bool}> $aiCodeMeta
	 * @param array<int,list<int>> $assignmentTypes
	 * @return array{program_type:string,create_source:string}
	 */
	private function classifyCourseProgramAndSource(array $course, array $aiCodeMeta, array $assignmentTypes): array
	{
		$code = \App\Libraries\DocumentTextExtractor::cleanModuleCode((string) ($course['code'] ?? ''));
		$storedProg = strtolower(trim((string) ($course['program_type'] ?? '')));
		$storedSource = strtolower(trim((string) ($course['create_source'] ?? '')));
		$program = ($storedProg === 'reb') ? 'reb' : 'tvet';
		$source = ($storedSource === 'ai') ? 'ai' : 'manual';

		if ($code !== '' && isset($aiCodeMeta[$code])) {
			$source = 'ai';
			$program = ($aiCodeMeta[$code]['program_type'] === 'reb') ? 'reb' : 'tvet';
		}

		$cid = (int) ($course['id'] ?? 0);
		if ($cid > 0 && isset($assignmentTypes[$cid])) {
			$types = array_values(array_unique(array_map('intval', $assignmentTypes[$cid])));
			if (in_array(2, $types, true) && !in_array(1, $types, true)) {
				$program = 'reb';
			} elseif (in_array(1, $types, true) && !in_array(2, $types, true)) {
				$program = 'tvet';
			}
		} elseif ($source === 'manual' && $code !== '' && preg_match('/^(SWD|GEN|CCM|ICT)[A-Z]{0,6}\d{3}$/', $code)) {
			$program = 'tvet';
		}

		if ($cid > 0 && ($source !== $storedSource || $program !== $storedProg)) {
			try {
				$this->ensureCoursesMetaSchema();
				(new CourseModel())->update($cid, [
					'program_type' => $program,
					'create_source' => $source,
				]);
			} catch (\Throwable $e) {
				// display-only fallback
			}
		}

		return ['program_type' => $program, 'create_source' => $source];
	}

	/**
	 * Curriculum credit for course create (from analysis or competences table — not chronogram hours/week).
	 *
	 * @param array<string,float> $creditMap from curriculum text parse
	 */
	private function estimateCreditFromModule(array $m, array $creditMap = []): float
	{
		$credit = \App\Libraries\DocumentTextExtractor::normalizeCreditValue($m['credits'] ?? null);
		if ($credit > 0) {
			return $credit;
		}
		$credit = \App\Libraries\DocumentTextExtractor::normalizeCreditValue($m['credit'] ?? null);
		if ($credit > 0) {
			return $credit;
		}

		$code = \App\Libraries\DocumentTextExtractor::cleanModuleCode((string) ($m['code'] ?? ''));
		if ($code !== '' && isset($creditMap[$code]) && (float) $creditMap[$code] > 0) {
			return round((float) $creditMap[$code], 1);
		}
		if ($code !== '' && $creditMap !== []) {
			foreach ($creditMap as $mapCode => $mapCredit) {
				$mapCode = \App\Libraries\DocumentTextExtractor::cleanModuleCode((string) $mapCode);
				if ($mapCode === $code && (float) $mapCredit > 0) {
					return round((float) $mapCredit, 1);
				}
				if ($mapCode !== '' && preg_match('/^([A-Z]+)(\d{3})$/', $code, $ma) && preg_match('/^([A-Z]+)(\d{3})$/', $mapCode, $mb)
					&& $ma[2] === $mb[2]
					&& (strpos($ma[1], $mb[1]) !== false || strpos($mb[1], $ma[1]) !== false || levenshtein($ma[1], $mb[1]) <= 2)
					&& (float) $mapCredit > 0) {
					return round((float) $mapCredit, 1);
				}
			}
		}
		return 0.0;
	}

	/**
	 * Timetable hours/week = typical weekly periods from chronogram (not yearly module hours).
	 *
	 * @param array<string,array{hours_per_week?:float}> $weeklyMap from chronogram text parse
	 */
	private function estimateHoursPerWeekFromModule(array $m, array $weeklyMap = []): float
	{
		// Prefer value already computed during analysis
		if (isset($m['hours_per_week']) && (float) $m['hours_per_week'] > 0 && (float) $m['hours_per_week'] <= 20) {
			return round((float) $m['hours_per_week'], 1);
		}

		$code = \App\Libraries\DocumentTextExtractor::cleanModuleCode((string) ($m['code'] ?? ''));
		if ($code !== '' && isset($weeklyMap[$code]['hours_per_week']) && (float) $weeklyMap[$code]['hours_per_week'] > 0) {
			return round((float) $weeklyMap[$code]['hours_per_week'], 1);
		}
		if ($code !== '' && $weeklyMap !== []) {
			foreach ($weeklyMap as $wkCode => $info) {
				$wkCode = \App\Libraries\DocumentTextExtractor::cleanModuleCode((string) $wkCode);
				if ($wkCode === $code && (float) ($info['hours_per_week'] ?? 0) > 0) {
					return round((float) $info['hours_per_week'], 1);
				}
				// Soft match stems (SWDBS vs SWBS)
				if ($wkCode !== '' && preg_match('/^([A-Z]+)(\d{3})$/', $code, $ma) && preg_match('/^([A-Z]+)(\d{3})$/', $wkCode, $mb)
					&& $ma[2] === $mb[2]
					&& (strpos($ma[1], $mb[1]) !== false || strpos($mb[1], $ma[1]) !== false || levenshtein($ma[1], $mb[1]) <= 2)
					&& (float) ($info['hours_per_week'] ?? 0) > 0) {
					return round((float) $info['hours_per_week'], 1);
				}
			}
		}

		// Mode of realistic weekly slot values (1–20 periods)
		$vals = [];
		foreach ($m['chronogram_slots'] ?? [] as $s) {
			if (!is_array($s)) {
				continue;
			}
			$p = (float) ($s['periods'] ?? $s['hours'] ?? 0);
			if ($p > 0 && $p <= 20) {
				$vals[] = $p;
			}
		}
		if ($vals !== []) {
			$counts = [];
			foreach ($vals as $v) {
				$key = (string) round($v, 1);
				$counts[$key] = ($counts[$key] ?? 0) + 1;
			}
			arsort($counts);
			reset($counts);
			$top = key($counts);
			if ($top !== null && $top !== '') {
				return (float) $top;
			}
			return round(array_sum($vals) / count($vals), 1);
		}

		// Yearly period total ÷ teaching weeks (never treat yearly as weekly)
		$total = (float) ($m['chronogram_periods_total'] ?? $m['weekly_hours_total'] ?? 0);
		$slotCount = 0;
		foreach ($m['chronogram_slots'] ?? [] as $s) {
			if (is_array($s)) {
				$slotCount++;
			}
		}
		if ($total > 20) {
			$weeks = $slotCount >= 8 ? $slotCount : 30;
			return round($total / $weeks, 1);
		}

		// Only accept learning_hours when it already looks like weekly load
		$lh = (float) ($m['learning_hours'] ?? 0);
		if ($lh > 0 && $lh <= 12) {
			return $lh;
		}
		return 0.0;
	}

	private function categoryTitleFromModuleType(string $type): string
	{
		$t = strtolower(trim($type));
		$map = [
			'specific' => 'Specific',
			'core' => 'Core',
			'general' => 'General',
			'ccm' => 'Complementary',
			'complementary' => 'Complementary',
			'optional' => 'Optional',
		];
		if (isset($map[$t])) {
			return $map[$t];
		}
		if ($t !== '') {
			return ucwords(str_replace(['_', '-'], ' ', $t));
		}
		return 'General';
	}

	/** Find or create a course category by title for this school. */
	private function resolveCourseCategoryId(int $schoolId, string $title): int
	{
		$title = trim($title);
		if ($title === '') {
			$title = 'General';
		}
		$mdl = new CourseCategoryModel();
		$rows = $mdl->where('school_id', $schoolId)->findAll();
		foreach ($rows as $r) {
			if (strcasecmp(trim((string) ($r['title'] ?? '')), $title) === 0) {
				return (int) $r['id'];
			}
		}
		$mdl->insert([
			'school_id' => $schoolId,
			'title' => $title,
			'status' => 1,
		]);
		return (int) $mdl->getInsertID();
	}

	/**
	 * Bulk-create courses from Pedagogical Documents extraction.
	 * Marks = credit × 10. Category created if missing.
	 * Does not assign to class/teacher — assign manually per course later.
	 */
	public function smart_create_courses()
	{
		$this->_preset(1, 3);
		$this->ensureCoursesMetaSchema();
		$schoolId = (int) $this->session->get('soma_school_id');
		$raw = $this->request->getPost('courses');
		$courses = is_string($raw) ? json_decode($raw, true) : $raw;
		if (!is_array($courses) || $courses === []) {
			return $this->response->setJSON(['error' => 'No courses selected']);
		}
		$programType = strtolower(trim((string) $this->request->getPost('program_type')));
		if ($programType !== 'reb') {
			$programType = 'tvet';
		}

		$courseMdl = new CourseModel();
		$created = 0;
		$skipped = 0;
		$errors = [];
		$createdIds = [];

		foreach ($courses as $item) {
			if (!is_array($item)) {
				continue;
			}
			$code = \App\Libraries\DocumentTextExtractor::cleanModuleCode((string) ($item['code'] ?? ''));
			$title = trim((string) ($item['title'] ?? ''));
			if ($title === '') {
				$title = $code;
			}
			if ($code === '' && $title === '') {
				continue;
			}
			$credit = \App\Libraries\DocumentTextExtractor::normalizeCreditValue($item['credit'] ?? null);
			if ($credit < 0) {
				$credit = 0.0;
			}
			$marks = (int) round($credit * 10);
			$catTitle = trim((string) ($item['category_title'] ?? 'General')) ?: 'General';
			$catId = $this->resolveCourseCategoryId($schoolId, $catTitle);

			$existing = null;
			if ($code !== '') {
				$existing = $courseMdl->where('school_id', $schoolId)->where('code', $code)->first();
			}
			if ($existing) {
				$skipped++;
			} else {
				try {
					$courseMdl->insert([
						'school_id' => $schoolId,
						'title' => $title,
						'code' => $code !== '' ? $code : strtoupper(substr(preg_replace('/\s+/', '', $title) ?? 'CRS', 0, 12)),
						'category' => $catId,
						'credit' => $credit,
						'marks' => $marks,
						'program_type' => $programType,
						'create_source' => 'ai',
						'created_by' => (int) $this->session->get('soma_id'),
					]);
					$courseId = (int) $courseMdl->getInsertID();
					$created++;
					$createdIds[] = $courseId;
				} catch (\Throwable $e) {
					$errors[] = ($code ?: $title) . ': ' . $e->getMessage();
					continue;
				}
			}
		}

		$msg = $created . ' course(s) created';
		if ($skipped) {
			$msg .= ', ' . $skipped . ' already existed';
		}
		$msg .= '. Assign teachers/classes manually on each course.';
		return $this->response->setJSON([
			'success' => $msg,
			'created' => $created,
			'skipped' => $skipped,
			'created_ids' => $createdIds,
			'errors' => $errors,
		]);
	}

	public function assign_shift()
	{
		$this->_preset();
		$current_shift = $this->request->getPost("shift_id");
		$shift = $this->request->getPost("shift");
		$staff_id = $this->request->getPost("staff");

		$staffMdl = new StaffModel();
		if ($current_shift == $shift) {
			//course already assigned to teacher
			return $this->response->setJSON(array("error" => lang("app.currentShiftError")));
		}
		$data = array(
				"shift_id" => $shift,
				"id" => $staff_id,
				"updated_by" => $this->session->get("soma_id"));
		try {
			$staffMdl->save($data);
			return $this->response->setJSON(array("success" => lang("app.shiftAssigned")));
		} catch (\Exception $e) {
			return $this->response->setJSON(array("error" => lang("app.lblError") . $e->getMessage()));
		}
	}

	public function change_post()
	{
		$this->_preset();
		$current_post = $this->request->getPost("post_id");
		$post = $this->request->getPost("privilege");
		$staff_id = $this->request->getPost("staff");

		$staffMdl = new StaffModel();
		if ($current_post == $post) {
			//course already assigned to teacher
			return $this->response->setJSON(array("error" => lang("app.currentPostError")));
		}
		$data = array(
				"post" => $post,
				"id" => $staff_id,
				"updated_by" => $this->session->get("soma_id"));
		try {
			$staffMdl->save($data);
			return $this->response->setJSON(array("success" => lang("app.postChanged")));
		} catch (\Exception $e) {
			return $this->response->setJSON(array("error" => lang("app.lblError") . $e->getMessage()));
		}
	}

	public function dept()
	{
		$this->_preset(1, 3);
		$data = $this->data;
		$data['title'] = lang("app.departmentsList");
		$data['subtitle'] = lang("app.viewDepartments");
		$data['page'] = "department";
		$data['content'] = view("pages/dept", array());
		return view('main', $data);
	}

	public function staff_monthly_report()
	{
		$this->_preset();
		$data = $this->data;
		$data['title'] = lang("app.staffMonthlyReport");
		$data['subtitle'] = lang("app.viewAllMnthlyStaff");
		$data['page'] = "staff_monthly_report";
		$acMdl = new AcademicYearModel();
		$data['years'] = get_years($this->data['school_start_year']);
		$data['show_header'] = true;
		$data['content'] = view("pages/reports/staff_report_monthly", $data);
		return view('main', $data);
	}

	public function staff_monthly_report_data($pdf = false)
	{
		$this->_preset();
		$data = $this->data;
		$month = $this->request->getGet("year") . "-" . sprintf("%02d", $this->request->getGet("month"));
//		echo $month;die();
		$staffMdl = new StaffModel();
		$data['staffs'] = $staffMdl->select("staffs.*,sh.options,sh.title,(select group_concat(time_in,':',coalesce(time_out,0))
		 from attendance_records where user_id=staffs.id and user_type =1 and date_format(from_unixtime(time_in),'%Y-%m')='$month' group by user_id,date_format(from_unixtime(time_in),'%m-%Y')) as records
		 ,lv.fromDate as leave_start,lv.toDate as leave_end")
				->where("staffs.school_id", $this->session->get("soma_school_id"))
				->join("shifts sh", "sh.id=staffs.shift_id")
				->join("leaves lv", "lv.requested_by=staffs.id and lv.status=1 and (from_unixtime(lv.fromDate,'%Y-%m')='$month' OR from_unixtime(lv.toDate,'%Y-%m')='$month')", "LEFT")
				->groupBy("staffs.id")
				->orderBy("staffs.fname")
				->orderBy("staffs.lname")
				->get()->getResultArray();
		$data['show_header'] = false;
		$data['month'] = $month;
		$data['pdf'] = false;
		if ($pdf == 'true') {
			$data['pdf'] = true;
			$html = view("pages/reports/staff_report_monthly", $data);
			try {
				$mask = FCPATH . "assets/templates/*.html";
				array_map('unlink', glob($mask));//clear previous cards
				$wkhtmltopdf = new Wkhtmltopdf(array('path' => FCPATH . 'assets/templates/'));
				$wkhtmltopdf->setTitle(lang("app.staffMonthlyReport"));
				$wkhtmltopdf->setHtml($html);
				$wkhtmltopdf->setOrientation("portrait");
				
//					$wkhtmltopdf->setOptions(array("page-width" => "278px", "page-height" => "430px"));
				$wkhtmltopdf->setMargins(array("top" => 0, "left" => 0, "right" => 0, "bottom" => 0));
				$wkhtmltopdf->output(Wkhtmltopdf::MODE_EMBEDDED, "staff_report_individual" . time() . ".pdf");
			} catch (\Exception $e) {
				echo $e->getMessage();
			}
		} else {
			echo view("pages/reports/staff_report_monthly", $data);
		}
	}

	public function staff_individual_report()
	{
		$this->_preset();
		$data = $this->data;
		$data['title'] = lang("app.staffIndividualReport");
		$data['subtitle'] = lang("app.viewAllIndividualStaff");
		$data['page'] = "staff_individual_report";
		$staffMdl = new StaffModel();
		$data['staffs'] = $staffMdl->select("staffs.id,concat(staffs.fname,' ',staffs.lname) as name")
				->join("shifts sh", "sh.id=staffs.shift_id")
				->where("staffs.school_id", $this->session->get("soma_school_id"))
				->groupBy("staffs.id")
				->get()->getResultArray();
		$data['show_header'] = true;
		$data['content'] = view("pages/reports/staff_report_individual", $data);
		return view('main', $data);
	}

	public function staff_individual_report_data($pdf = false)
	{
		$this->_preset();
		$data = $this->data;
		$staff = $this->request->getGet("staff");
		$date1 = $this->request->getGet("date1");
		$date1_unix = strtotime($date1);
		$date2 = $this->request->getGet("date2");
		$date2_unix = strtotime($date2) + 86399;
		$staffMdl = new StaffModel();
		$staffBuilder = $staffMdl->select("staffs.*,sh.options,sh.title,p.title as post_title,lv.fromDate as leave_start,lv.toDate as leave_end")
				->join("shifts sh", "sh.id=staffs.shift_id")
				->join("leaves lv", "lv.requested_by=staffs.id and lv.status=1 and (lv.fromDate>='$date1_unix' OR lv.toDate<='$date2_unix')", "LEFT")
				->join("posts p", "p.id=staffs.post")
				->where("staffs.school_id", $this->session->get("soma_school_id"));
		if ($staff != 0) {
			$staffBuilder->where("staffs.id", $staff);
		}
		$staffs = $staffBuilder->get()->getResultArray();
		$data['staffs'] = $staffs;
		$attMdl = new AttendanceRecordsModel();
//		$data["records"] = $attMdl->select("time_in,coalesce(time_out,0) as time_out")
//			->where("user_id", $staffs['id'])
//			->where("user_type", 1)
//			->where("time_in>='$date1_unix' and time_in<='$date2_unix'")
//			->groupBy("user_id")
//			->groupBy("date_format(from_unixtime(time_in),'%d-%m-%Y')")
//			->orderBy("time_in", "ASC")
//			->get()->getResultArray();
		$data['show_header'] = false;
		$data['date1'] = $date1;
		$data['date2'] = $date2;
		$data['reportType'] = 0;
		$data['pdf'] = false;
		if ($pdf == 'true') {
			$data['pdf'] = true;
			$html = view("pages/reports/staff_report_individual", $data);
			try {
				$mask = FCPATH . "assets/templates/*.html";
				array_map('unlink', glob($mask));//clear previous cards
				$wkhtmltopdf = new Wkhtmltopdf(array('path' => FCPATH . 'assets/templates/'));
				$wkhtmltopdf->setTitle(lang("app.Staffattendancereport"));
				$wkhtmltopdf->setHtml($html);
				$wkhtmltopdf->setOrientation("portrait");
//					$wkhtmltopdf->setOptions(array("page-width" => "278px", "page-height" => "430px"));
				$wkhtmltopdf->setMargins(array("top" => 0, "left" => 0, "right" => 0, "bottom" => 0));
				$wkhtmltopdf->output(Wkhtmltopdf::MODE_EMBEDDED, "staff_report_individual" . time() . ".pdf");
			} catch (\Exception $e) {
				echo $e->getMessage();
			}
		} else {
			echo view("pages/reports/staff_report_individual", $data);
		}

	}

	public function student_inout_monthly_report()
	{
		$this->_preset();
		$data = $this->data;
		$data['title'] = lang("app.staffMonthlyReport");
		$data['subtitle'] = lang("app.viewAllMnthlyStaff");
		$data['page'] = "staff_monthly_report";
		$clMdl = new ClassesModel();
		$data['classes'] = $clMdl->get_classes();
		$data['show_header'] = true;
		$data['content'] = view("pages/reports/student_inout_report_monthly", $data);
		return view('main', $data);
	}

	public function student_inout_monthly_report_data($pdf = false)
	{
		$this->_preset();
		$data = $this->data;
		$month = sprintf("%02d", $this->request->getGet("month")) . "-" . date("Y");
		$classe = $this->request->getGet("class");
		$stMdl = new StudentModel();
		$data['students'] = $stMdl->select("students.*,(select group_concat(date_format(from_unixtime(time_in),'%d %H:%i'),';',date_format(from_unixtime(time_out),'%d %H:%i'))
		 from attendance_records where user_type=0 and user_id=students.id and date_format(from_unixtime(time_in),'%m-%Y')='$month' group by user_id) as records")
				->join("class_records cr", "cr.student=students.id")
				->where("cr.class", $classe)
				->where("cr.year", $this->data['academic_year'])
				->where("school_id", $this->session->get("soma_school_id"))
				->groupBy("students.id")
				->get()->getResultArray();
		$data['show_header'] = false;
		$data['month'] = $month;
		$data['classe'] = "";//to be done later
		echo view("pages/reports/student_inout_report_monthly", $data);
	}

	public function student_course_report()
	{
		$this->_preset();
		$data = $this->data;
		$data['title'] = lang("app.ctudentCourseReport");
		$data['subtitle'] = lang("app.viewStudentCourseReport");
		$data['page'] = "staff_monthly_report";
		$courseModel = new CourseModel();
		$school_id = $this->session->get("soma_school_id");
		$year = $this->data['academic_year'];
		$term = $data['term'];
		$builder = $courseModel->select("courses.id,courses.title,courses.code,r.id record_id,concat(s.fname,' ',s.lname) as mentor_name")
				->join("course_category cs", "cs.id=courses.category")
				->join("course_records r", "courses.id=r.course")
				->join("staffs s", "s.id=r.lecturer")
				->where("courses.school_id", $school_id)
				->where("r.year", $year)
				->where("find_in_set({$term},r.term)>0")
				->groupBy("courses.id");
		if ($this->session->get("soma_post") != 1 && $this->session->get("soma_post") != 3) {
			//filter courses if is not head master or dean of studies
			$builder->where("s.id", $this->session->get("soma_id"));
		}
		$data['courses'] = $builder->get()->getResultArray();
		$data['show_header'] = true;
		$data['content'] = view("pages/reports/student_course_report", $data);
		return view('main', $data);
	}

	public function student_course_report_data($pdf = false)
	{
		$this->_preset();
		$data = $this->data;
		$course = $this->request->getGet("course");
		$month = sprintf("%02d", $this->request->getGet("months")) . "-" . date("Y");
		$month2 = date("Y") . '-' . sprintf("%02d", $this->request->getGet("months"));
		$classe = $this->request->getGet("class");
		$stMdl = new StudentModel();
		$data['students'] = $stMdl->select("students.*")
				->join("class_records cr", "cr.student=students.id")
				->where("cr.class", $classe)
				->where("cr.year", $this->data['academic_year'])
				->where("school_id", $this->session->get("soma_school_id"))
				->groupBy("students.id")
				->get()->getResultArray();
		$data['show_header'] = false;
		$csMdl = new CourseModel();
		$clMdl = new ClassesModel();
		$data['course'] = $csMdl->select("concat(code,' ',title) as course,concat(s.fname,' ',s.lname) as lecturer")
				->join('course_records cr', "cr.course=courses.id AND cr.class=$classe AND cr.year = " . $this->data['academic_year'])
				->join('staffs s', "s.id=cr.lecturer")
				->where('courses.id', $course)
				->get(1)->getRowArray();
		$data['class_id'] = $classe;
		$data['course_id'] = $course;
		$data['classe'] = $clMdl->get_class_name($classe);
		$data['month'] = $month;
		$data['month2'] = $month2;
		$data['pdf'] = false;
		if ($pdf == 'true') {
			$data['pdf'] = true;
			$html = view("pages/reports/student_course_report", $data);
			try {
				$mask = FCPATH . "assets/templates/*.html";
				array_map('unlink', glob($mask));//clear previous cards
				$wkhtmltopdf = new Wkhtmltopdf(array('path' => FCPATH . 'assets/templates/'));
				$wkhtmltopdf->setTitle(lang("app.classDailyAttendanceReport"));
				$wkhtmltopdf->setHtml($html);
				$wkhtmltopdf->setOrientation("landscape");
//					$wkhtmltopdf->setOptions(array("page-width" => "278px", "page-height" => "430px"));
				$wkhtmltopdf->setMargins(array("top" => 0, "left" => 0, "right" => 0, "bottom" => 0));
				$wkhtmltopdf->output(Wkhtmltopdf::MODE_EMBEDDED, "student_course_report" . time() . ".pdf");
			} catch (\Exception $e) {
				echo $e->getMessage();
			}
		} else {
			echo view("pages/reports/student_course_report", $data);
		}
	}

	public function student_course_summary_report()
	{
		$this->_preset();
		$data = $this->data;
		$data['title'] = lang("app.studentCourseSummaryReport");
		$data['subtitle'] = lang("app.viewCourseSummaryStaff");
		$data['page'] = "course_summary_report";
		$courseModel = new CourseModel();
		$school_id = $this->session->get("soma_school_id");
		$year = $this->data['academic_year'];
		$term = $data['term'];
		$builder = $courseModel->select("courses.id,courses.title,courses.code,r.id record_id,concat(s.fname,' ',s.lname) as mentor_name")
				->join("course_category cs", "cs.id=courses.category")
				->join("course_records r", "courses.id=r.course")
				->join("staffs s", "s.id=r.lecturer")
				->where("courses.school_id", $school_id)
				->where("r.year", $year)
				->where("find_in_set({$term},r.term)>0")
				->groupBy("courses.id");
		if ($this->session->get("soma_post") != 1 && $this->session->get("soma_post") != 3) {
			//filter courses if is not head master or dean of studies
			$builder->where("s.id", $this->session->get("soma_id"));
		}
		$data['courses'] = $builder->get()->getResultArray();
		$data['show_header'] = true;
		$data['content'] = view("pages/reports/student_course_summary_report", $data);
		return view('main', $data);
	}

	public function student_course_summary_report_data($pdf = false)
	{
		$this->_preset();
		$data = $this->data;
		$course = $this->request->getGet("course");
		$date1 = $this->request->getGet("date1");
		$date1_unix = strtotime($date1);
		$date2 = $this->request->getGet("date2");
		$classe = $this->request->getGet("class");
		$date2_unix = strtotime($date2) + 86399;
		$stMdl = new StudentModel();
		$data['students'] = $stMdl->select("students.*")
				->join("class_records cr", "cr.student=students.id")
				->where("cr.class", $classe)
				->where("cr.year", $this->data['academic_year'])
				->where("school_id", $this->session->get("soma_school_id"))
				->groupBy("students.id")
				->get()->getResultArray();
		$data['show_header'] = false;
		$data['date1'] = $date1;
		$data['date2'] = $date2;
		$csMdl = new CourseModel();
		$clMdl = new ClassesModel();
		$data['course'] = $csMdl->select("concat(code,' ',title) as course,concat(s.fname,' ',s.lname) as lecturer")
				->join('course_records cr', "cr.course=courses.id AND cr.class=$classe AND cr.year = " . $this->data['academic_year'])
				->join('staffs s', "s.id=cr.lecturer")
				->where('courses.id', $course)
				->get(1)->getRowArray();
		$data['classe'] = $clMdl->get_class_name($classe);
		echo view("pages/reports/student_course_summary_report", $data);
	}

	public function student_class_daily_report()
	{
		$this->_preset();
		$data = $this->data;
		$data['title'] = lang("app.classDailyReport");
		$data['subtitle'] = lang("app.viewClassDailyReport");
		$data['page'] = "class_daily_report";
		$clMdl = new ClassesModel();
		$data['classes'] = $clMdl->get_classes();
		$data['show_header'] = true;
		$data['content'] = view("pages/reports/student_class_daily_report", $data);
		return view('main', $data);
	}

	public function student_class_daily_report_data($pdf = false)
	{
		$this->_preset();
		$data = $this->data;
		$date1 = $this->request->getGet("date1");
		$date2 = $this->request->getGet("date2");
		$classe = $this->request->getGet("class");
		$stMdl = new StudentModel();
		$dailyMdl = new DailyAttendanceModel();
		$data['students'] = $stMdl->select("concat(students.studying_mode,':',students.sex,':',count(students.id)) as sex")
				->join("class_records cr", "cr.student=students.id")
				->where("cr.class", $classe)
				->where("cr.year", $this->data['academic_year'])
				->where("school_id", $this->session->get("soma_school_id"))
				->groupBy("students.sex")
				->groupBy("students.studying_mode")
				->get()->getResultArray();
		$data['dates'] = $dailyMdl->select("datee")
				->join("students st", "st.id=daily_attendance.student_id")
				->join("class_records cr", "cr.student=st.id")
				->where("daily_attendance.datee>='$date1' AND daily_attendance.datee<='$date2'")
				->where("cr.class", $classe)
				->where("cr.year", $this->data['academic_year'])
				->where("st.school_id", $this->session->get("soma_school_id"))
				->groupBy("daily_attendance.datee")
				->get()->getResultArray();
		$data['show_header'] = false;
		$data['date1'] = $date1;
		$data['date2'] = $date2;
		$data['class_id'] = $classe;
		$clMdl = new ClassesModel();
		$data['classe'] = $clMdl->get_class_name($classe);
		$data['pdf'] = false;
		if ($pdf == 'true') {
			$data['pdf'] = true;
			$html = view("pages/reports/student_class_daily_report", $data);
			try {
				$mask = FCPATH . "assets/templates/*.html";
				array_map('unlink', glob($mask));//clear previous cards
				$wkhtmltopdf = new Wkhtmltopdf(array('path' => FCPATH . 'assets/templates/'));
				$wkhtmltopdf->setTitle(lang("app.classDailyAttendanceReport"));
				$wkhtmltopdf->setHtml($html);
				$wkhtmltopdf->setOrientation("portrait");
//					$wkhtmltopdf->setOptions(array("page-width" => "278px", "page-height" => "430px"));
				$wkhtmltopdf->setMargins(array("top" => 0, "left" => 0, "right" => 0, "bottom" => 0));
				$wkhtmltopdf->output(Wkhtmltopdf::MODE_EMBEDDED, "student_class_daily_report" . time() . ".pdf");
			} catch (\Exception $e) {
				echo $e->getMessage();
			}
		} else {
			echo view("pages/reports/student_class_daily_report", $data);
		}
	}

	public function student_details_daily_report()
	{
		$this->_preset();
		$data = $this->data;
		$data['title'] = lang("app.generalDailyReport");
		$data['subtitle'] = lang("app.viewGeneralDailyReport");
		$data['page'] = "general_daily_report";
		$clMdl = new ClassesModel();
		$data['classes'] = $clMdl->get_classes();
		$data['show_header'] = true;
		$data['content'] = view("pages/reports/student_general_daily_report", $data);
		return view('main', $data);
	}

	
	public function student_details_boarding_report()
	{
		$this->_preset();
		$data = $this->data;
		$data['title'] = lang("app.generalBoardingReport");
		$data['subtitle'] = lang("app.viewGeneralBoardingReport");
		$data['page'] = "general_daily_report";
		$clMdl = new ClassesModel();
		$data['classes'] = $clMdl->get_classes();
		$data['show_header'] = true;
		$data['content'] = view("pages/reports/student_general_boarding_report", $data);
		return view('main', $data);
	}

	public function student_details_daily_report_data($pdf = false)
	{
		$this->_preset();
		$data = $this->data;
		$date1 = $this->request->getGet("date1");
		$date2 = $this->request->getGet("date2");
		$classe = $this->request->getGet("class");
		$stMdl = new StudentModel();
		$data['dates'] = $stMdl->select("datee")
				->join("daily_attendance d", "students.id=d.student_id AND d.datee>='$date1' AND d.datee<='$date2'")
				->join("class_records cr", "cr.student=students.id")
				->where("cr.class", $classe)
				->where("cr.year", $this->data['academic_year'])
				->where("students.school_id", $this->session->get("soma_school_id"))
				->groupBy("d.datee")
				->orderBy("d.datee", "ASC")
				->get()->getResultArray();
		$data['show_header'] = false;
		$data['date1'] = $date1;
		$data['date2'] = $date2;
		$data['class_id'] = $classe;
		$clMdl = new ClassesModel();
		$data['classe'] = $clMdl->get_class_name($classe);
		$data['pdf'] = false;
		if ($pdf == 'true') {
			$data['pdf'] = true;
			$html = view("pages/reports/student_general_daily_report", $data);
			try {
				$mask = FCPATH . "assets/templates/*.html";
				array_map('unlink', glob($mask));//clear previous cards
				$wkhtmltopdf = new Wkhtmltopdf(array('path' => FCPATH . 'assets/templates/'));
				$wkhtmltopdf->setTitle(lang("app.classDailyAttendanceReport"));
				$wkhtmltopdf->setHtml($html);
				$wkhtmltopdf->setOrientation("portrait");
//					$wkhtmltopdf->setOptions(array("page-width" => "278px", "page-height" => "430px"));
				$wkhtmltopdf->setMargins(array("top" => 2, "left" => 2, "right" => 2, "bottom" => 2));
				$wkhtmltopdf->output(Wkhtmltopdf::MODE_EMBEDDED, "student_class_daily_report" . time() . ".pdf");
			} catch (\Exception $e) {
				echo $e->getMessage();
			}
		} else {
			echo view("pages/reports/student_general_daily_report", $data);
		}
	}
	public function student_details_boarding_report_data($pdf = false)
{
    $this->_preset();
    $data = $this->data;
    $date1 = $this->request->getGet("date1");
    $date2 = $this->request->getGet("date2");
    $classe = $this->request->getGet("class");

    // Collect distinct dates for attendance
    $stMdl = new StudentModel();
    $data['dates'] = $stMdl->select("datee")
        ->join("boarding_attendance d", "students.id=d.student_id AND d.datee>='$date1' AND d.datee<='$date2'")
        ->join("class_records cr", "cr.student=students.id")
        ->where("cr.class", $classe)
        ->where("cr.year", $this->data['academic_year'])
        ->where("students.school_id", $this->session->get("soma_school_id"))
        ->groupBy("d.datee")
        ->orderBy("d.datee", "ASC")
        ->get()->getResultArray();

    // Fetch all students in this class for the period
    $students = $stMdl->select("students.id, fname, lname, sex")
        ->join("class_records cr", "cr.student=students.id")
        ->where("cr.class", $classe)
        ->where("cr.year", $this->data['academic_year'])
        ->where("students.school_id", $this->session->get("soma_school_id"))
        ->orderBy("fname", "ASC")
        ->findAll();

    // ✅ Chunk students into groups of 20 to prevent row breaking in PDF
    $data['student_chunks'] = array_chunk($students, 20);

    $data['show_header'] = false;
    $data['date1'] = $date1;
    $data['date2'] = $date2;
    $data['class_id'] = $classe;

    $clMdl = new ClassesModel();
    $data['classe'] = $clMdl->get_class_name($classe);
    $data['pdf'] = false;

    if ($pdf == 'true') {
        $data['pdf'] = true;
        $html = view("pages/reports/student_general_boarding_report", $data);

        try {
            // clear previous templates
            $mask = FCPATH . "assets/templates/*.html";
            array_map('unlink', glob($mask));

            $wkhtmltopdf = new Wkhtmltopdf(['path' => FCPATH . 'assets/templates/']);
            $wkhtmltopdf->setTitle(lang("app.boardingGeneralAttendance"));
            $wkhtmltopdf->setHtml($html);
            $wkhtmltopdf->setOrientation("Landscape");

            // Margins for header/footer space
            $wkhtmltopdf->setMargins([
                "top"    => 15,
                "left"   => 8,
                "right"  => 8,
                "bottom" => 12
            ]);

            $wkhtmltopdf->output(Wkhtmltopdf::MODE_EMBEDDED, "student_general_boarding_report" . time() . ".pdf");
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
    } else {
        echo view("pages/reports/student_general_boarding_report", $data);
    }
}

	public function student_daily_report()
	{
		$this->_preset();
		$data = $this->data;
		$data['title'] = lang("app.dailyReport");
		$data['subtitle'] = lang("app.viewBoardingReport");
		$data['page'] = "daily_report";
		$clMdl = new ClassesModel();
		$data['classes'] = $clMdl->get_classes();
		$data['show_header'] = true;
		$data['content'] = view("pages/reports/student_daily_report", $data);
		return view('main', $data);
	}

	public function student_boarding_report()
	{
		$this->_preset();
		$data = $this->data;
		$data['title'] = lang("app.boardingReport");
		$data['subtitle'] = lang("app.viewBoardingReport");
		$data['page'] = "daily_report";
		$clMdl = new ClassesModel();
		$data['classes'] = $clMdl->get_classes();
		$data['show_header'] = true;
		$data['content'] = view("pages/reports/student_boarding_report", $data);
		return view('main', $data);
	}
	public function student_daily_report_data($pdf = false)
	{
		$this->_preset();
		$data = $this->data;
		$date1 = $this->request->getGet("date1");
		$clMdl = new ClassesModel();
		$data['classes'] = $clMdl->get_classes();
		$data['school_id'] = $this->session->get("soma_school_id");
		$data['show_header'] = false;
		$data['date1'] = $date1;
		$data['pdf'] = false;
		if ($pdf == 'true') {
			$data['pdf'] = true;
			$html = view("pages/reports/student_daily_report", $data);
			try {
				$mask = FCPATH . "assets/templates/*.html";
				array_map('unlink', glob($mask));//clear previous cards
				$wkhtmltopdf = new Wkhtmltopdf(array('path' => FCPATH . 'assets/templates/'));
				$wkhtmltopdf->setTitle(lang("app.dailyAttendanceReport"));
				$wkhtmltopdf->setHtml($html);
				$wkhtmltopdf->setOrientation("portrait");
//					$wkhtmltopdf->setOptions(array("page-width" => "278px", "page-height" => "430px"));
				$wkhtmltopdf->setMargins(array("top" => 0, "left" => 0, "right" => 0, "bottom" => 0));
				$wkhtmltopdf->output(Wkhtmltopdf::MODE_EMBEDDED, "student_daily_report" . time() . ".pdf");
			} catch (\Exception $e) {
				echo $e->getMessage();
			}
		} else {
			echo view("pages/reports/student_daily_report", $data);
		}
	}

		public function student_boarding_report_data($pdf = false)
	{
		$this->_preset();
		$data = $this->data;
		$date1 = $this->request->getGet("date1");
		$clMdl = new ClassesModel();
		$data['classes'] = $clMdl->get_classes();
		$data['school_id'] = $this->session->get("soma_school_id");
		$data['show_header'] = false;
		$data['date1'] = $date1;
		$data['pdf'] = false;
		if ($pdf == 'true') {
			$data['pdf'] = true;
			$html = view("pages/reports/student_boarding_report", $data);
			try {
				$mask = FCPATH . "assets/templates/*.html";
				array_map('unlink', glob($mask));//clear previous cards
				$wkhtmltopdf = new Wkhtmltopdf(array('path' => FCPATH . 'assets/templates/'));
				$wkhtmltopdf->setTitle(lang("app.boardingAttendanceReport"));
				$wkhtmltopdf->setHtml($html);
				$wkhtmltopdf->setOrientation("portrait");
//					$wkhtmltopdf->setOptions(array("page-width" => "278px", "page-height" => "430px"));
				$wkhtmltopdf->setMargins(array("top" => 0, "left" => 0, "right" => 0, "bottom" => 0));
				$wkhtmltopdf->output(Wkhtmltopdf::MODE_EMBEDDED, "student_boarding_report" . time() . ".pdf");
			} catch (\Exception $e) {
				echo $e->getMessage();
			}
		} else {
			echo view("pages/reports/student_boarding_report", $data);
		}
	}

	public function shifts()
	{
		return redirect()->to(base_url('settings#staff-attendance-settings'));
	}

	public function staffs()
	{
		$this->_preset(1, 3);
		$data = $this->data;
		$data['title'] = lang("app.staffLists");
		$data['subtitle'] = lang("app.viewAllStaff");
		$data['page'] = "staffs";
		$staffMdl = new StaffModel();
		$data['staffs'] = $staffMdl->select("staffs.*,p.title as post_title,shf.title as shift_title")
				->join("posts p", "p.id=staffs.post")
				->join("shifts shf", "shf.id=staffs.shift_id", "left")
				->where("staffs.school_id", $this->session->get("soma_school_id"))
				->get()->getResultArray();
		$data['content'] = view("pages/staffs", $data);
		return view('main', $data);
	}

	public function students()
	{
		$this->_preset(1, 3, 4, 5, 6);
		$data = $this->data;
		$data['title'] = lang("app.studentsLists");
		$data['subtitle'] = lang("app.viewAllStudent");
		$data['page'] = "students";
		$classe = $this->request->getGet("c") == null ? "-1" : $this->request->getGet("c");
		$yearId = $this->request->getGet("y") == null ? "-1" : $this->request->getGet("y");
		$classMdl = new ClassesModel();
		$school_id = $this->session->get("soma_school_id");
		$data['classes'] = $classMdl->select("classes.id,classes.title,d.title as department_name,d.code as dept_code,l.title as level_name
		,f.type,f.abbrev as faculty_code,concat(s.fname,' ',s.lname) as mentor_name,s.id as idstf")
				->join("departments d", "d.id=classes.department")
				->join("levels l", "l.id=classes.level")
				->join("faculty f", "f.id=d.faculty_id")
				->join("staffs s", "s.id=classes.mentor", "LEFT")
				->where("classes.school_id", $school_id)
				->get()->getResultArray();
		$acMdl = new AcademicYearModel();
		$data['years'] = $acMdl->select('id,title')->where("school_id", $school_id)->get()->getResultArray();
		$studentMdl = new StudentModel();
		$data['students'] = $studentMdl->get_student_simple("c.id = $classe and cr.year=$yearId", null);
		$data['class_id'] = $classe;
		$data['academic_year'] = $yearId;
		$data['content'] = view("pages/students", $data);
		return view('main', $data);
	}

	public function dismissedStudent()
	{
		$this->_preset(1, 3, 4, 5, 6);
		$Mdl = new StudentModel();
		$data = $this->data;
		$data['page'] = "Dismissed";
		$data['title'] = lang("app.DismissedStudents");
		$data['subtitle'] = lang("app.DismissedStudents");
		$data['students'] = $Mdl->where("school_id", $this->session->get("soma_school_id"))->where("status", 0)->get()->getResultArray();
		$data['content'] = view("pages/dismissedStudent", $data);
		return view('main', $data);
	}

	public function student($id)
	{
		$this->_preset(1, 3, 4, 5, 6);
		$data = $this->data;
		$data['title'] = lang("app.viewStudent");
		$studentMdl = new StudentModel();
		$active_term = new ActiveTermModel();
		$classModel = new ClassesModel();
		$data['academic'] = $active_term->select("active_term.*")
				->where("school_id", $this->session->get("soma_school_id"))
				->groupBy("active_term.academic_year")
				->get()->getResultArray();
		$student = $studentMdl->get_student_simple($id, "students.id", true);
		if ($student == null)
			return redirect()->to(base_url('students'));
		$data['student'] = $student;
		$data['classes'] = $classModel->get_classes();
		$data['subtitle'] = $student['fname'] . ' ' . $student['lname'] . lang("app.profile");
		$data['page'] = "student";
		$data['content'] = view("pages/student", $data);
		return view('main', $data);
	}

	public function staff($id)
	{
		$this->_preset(1, 3);
		$data = $this->data;
		$data['title'] = lang("app.viewStaff");
		$stfMdl = new StaffModel();
		$staff = $stfMdl->select("staffs.*,p.title as post_title")
				->join("posts p", "staffs.post=p.id")
				->where("staffs.id", $id)
				->where("school_id", $this->session->get("soma_school_id"))
				->get(1)
				->getRowArray();
		if ($staff == null)
			return redirect()->to(base_url('staffs'));
		$data['staff'] = $staff;
		$data['subtitle'] = $staff['fname'] . ' ' . $staff['lname'] . lang("app.profile");
		$data['page'] = "students";
		$data['content'] = view("pages/staff", $data);
		return view('main', $data);
	}

	public function profile()
	{
		$this->_preset();
		$data = $this->data;
		$data['title'] = lang("app.viewStaff");
		$stfMdl = new StaffModel();
		$staff = $stfMdl->select("staffs.*,p.title as post_title")
				->join("posts p", "staffs.post=p.id")
				->where("staffs.id", $this->session->get("soma_id"))
				->where("school_id", $this->session->get("soma_school_id"))
				->get(1)
				->getRowArray();
		if ($staff == null)
			return redirect()->to(base_url('staffs'));
		$data['staff'] = $staff;
		$data['subtitle'] = $staff['fname'] . ' ' . $staff['lname'] . lang("app.profile");
		$data['page'] = "students";
		$data['content'] = view("pages/staff", $data);
		return view('main', $data);
	}

	public function course_category()
	{
		$this->_preset(1, 3);
		$data = $this->data;
		$data['title'] = lang("app.courseCategoryLists");
		$data['subtitle'] = lang("app.viewAllCategory");
		$data['page'] = "course_category";
		$categoryMdl = new CourseCategoryModel();
		$data['categories'] = $categoryMdl
				->where("school_id", $this->session->get("soma_school_id"))
				->get()->getResultArray();
		$data['content'] = view("pages/course_category", $data);
		return view('main', $data);
	}

	public function leave_management()
	{
		$this->_preset();
		$data = $this->data;
		$data['title'] = lang("app.leaveManagement");
		$data['subtitle'] = lang("app.viewLeave");
		$data['page'] = "leave";
		$postModel = new LeaveModel();
		$data['leaves'] = $postModel->select("leaves.id,leaves.type,leaves.reason,leaves.requested_by,leaves.fromDate,leaves.toDate,leaves.address,leaves.status,s.email,concat(s.fname,' ',s.lname) as staff")
				->join("staffs s", "s.id=leaves.requested_by")
				->where("leaves.school_id", $this->session->get("soma_school_id"))
				->get()->getResultArray();
		$data['content'] = view("pages/leave_management", $data);
		return view('main', $data);
	}

	public function print_leave($id)
	{
		$this->_preset();
		$data = $this->data;
		$data['title'] = lang("app.leaveManagement");
		$data['subtitle'] = lang("app.viewLeave");
		$data['page'] = "leave";
		$postModel = new LeaveModel();
		$data['leaves'] = $postModel->select("leaves.id,leaves.type,leaves.reason,leaves.requested_by,leaves.fromDate,leaves.toDate,leaves.address,leaves.days,leaves.status,s.email as staff_email,s.phone as staff_phone,concat(s.fname,' ',s.lname) as staff")
				->join("staffs s", "s.id=leaves.requested_by")
				->where("leaves.school_id", $this->session->get("soma_school_id"))
				->where("leaves.id", $id)
				->get()->getRowArray();
		$data['approver'] = $postModel->select("concat(s.fname,' ',s.lname) as staff")
				->join("staffs s", "s.id=leaves.requested_by")
				->where("leaves.school_id", $this->session->get("soma_school_id"))
				->where("leaves.id", $id)
				->where("leaves.id", $id)
				->get()->getRowArray();
//		$data['content'] = view("pages/print_leave_view", $data);
		$html = view('pages/reports/print_leave_view', $data);
		try {
			$mask = FCPATH . "assets/templates/*.html";
			array_map('unlink', glob($mask));//clear previous cards
			$wkhtmltopdf = new Wkhtmltopdf(array('path' => FCPATH . 'assets/templates/'));
			$wkhtmltopdf->setTitle(lang("app.leaveReport"));
			$wkhtmltopdf->setHtml($html);
			$wkhtmltopdf->setPageSize("A4");
			$wkhtmltopdf->setOrientation("portrait");
//					$wkhtmltopdf->setOptions(array("page-width" => "278px", "page-height" => "430px"));
			$wkhtmltopdf->setMargins(array("top" => 1, "left" => 0, "right" => 0, "bottom" => 1));
			$wkhtmltopdf->output(Wkhtmltopdf::MODE_EMBEDDED, "staff_leave_report" . time() . ".pdf");
		} catch (\Exception $e) {
			echo $e->getMessage();
		}
	}

	public function get_leave($type)
	{
		switch ($type) {
			case 1:
				echo lang("app.annualLeave");
				break;
			case 2:
				echo lang("app.sickLeave");
				break;
			case 3:
				echo lang("app.maternityLeave");
				break;
			case 4:
				echo lang("app.unpaidLeave");
				break;
		}
	}

	public function leave_application()
	{
		$this->_preset();
		$data = $this->data;
		$data['title'] = lang("app.leaveApplication");
		$data['subtitle'] = lang("app.viewLeaves");
		$data['page'] = "apply_leave";
		$postModel = new LeaveModel();
		$data['leaves'] = $postModel->select("leaves.id,leaves.type,leaves.reason,leaves.requested_by,leaves.fromDate,leaves.toDate,leaves.address,leaves.status,s.email,concat(s.fname,' ',s.lname) as staff")
				->join("staffs s", "s.id=leaves.requested_by")
				->where("leaves.school_id", $this->session->get("soma_school_id"))
				->where("s.id", $this->session->get("soma_id"))
				->get()->getResultArray();
		$data['content'] = view("pages/leave_application", $data);
		return view('main', $data);
	}

	public function manipulate_leave()
	{
		$id = $this->request->getPost("fId");
		$this->_preset();
		$type = $this->request->getPost("type");
		$reason = $this->request->getPost("reason");
		$days = $this->request->getPost("days");
		$fdate = $this->request->getPost("fdate");
		$tdate = $this->request->getPost("tdate");
		$address = $this->request->getPost("address");
		$created = $this->session->get("soma_id");
		$school = $this->session->get("soma_school_id");
		$BranchModel = new LeaveModel();
		if ($id != null) {
			$data = array(
					"id" => $id,
					"type" => $type,
					"school_id" => $school,
					"reason" => $reason,
					"days" => $days,
					"fromDate" => strtotime($fdate),
					"toDate" => strtotime($tdate),
					"address" => $address
			);
		} else {
			$data = array(
					"type" => $type,
					"school_id" => $school,
					"reason" => $reason,
					"days" => $days,
					"fromDate" => strtotime($fdate),
					"toDate" => strtotime($tdate),
					"address" => $address,
					"requested_by" => $created
			);
		}
		try {
			$BranchModel->save($data);
			return $this->response->setJSON(array("success" => lang("app.sentSucc")));
		} catch (\Exception $e) {
			return $this->response->setJSON(array("error" => "Error: " . $e->getMessage()));
		}
	}

	public function manipulate_leaveOrDeny()
	{
		$id = $this->request->getPost("fId");
		$type = $this->request->getPost("type");
		$denyReason = $this->request->getPost("denyReason");
		$email = $this->request->getPost("email");
		$this->_preset();
		$created = $this->session->get("soma_id");
		$BranchModel = new LeaveModel();
		if ($type == 1) {
			$data = array(
					"id" => $id,
					"status" => 1,
					"approved_by" => $created

			);
			$msg = lang("app.yourLeaveApproved");
		} else {
			$data = array(
					"id" => $id,
					"status" => 2,
					"deny_reason" => $denyReason,
					"approved_by" => $created
			);
			$msg = lang("app.yourLeaveDenied") . " " . $denyReason;
		}
		try {
			$BranchModel->save($data);
			$this->_send_email($email, lang("app.leaveFeedback"), $msg);
			return $this->response->setJSON(array("success" => lang("app.doneSuccessful")));
		} catch (\Exception $e) {
			return $this->response->setJSON(array("error" => "Error: " . $e->getMessage()));
		}
	}

	public function leaveTypeTostr($type)
	{
		switch ($type) {
			case 1:
				return lang("app.annual");
				break;
			case 2:
				return lang("app.sick");
				break;
			case 3:
				return lang("app.maternity");
				break;
			case 4:
				return lang("app.unpaid");
				break;

		}
	}

	public function leaveStatusTostr($status)
	{
		switch ($status) {
			case 0:
				return lang("app.pending");
				break;
			case 1:
				return lang("app.approved");
				break;
			case 2:
				return lang("app.denied");
				break;
		}
	}

	public static function typeToStr($type)
	{
		switch ($type) {
			case 1:
				return lang("app.sWDA");
			case 2:
				return lang("app.sREB");
//			case 3: return "CAMBRIDGE";
		}
	}

	public static function marksTypeToStr($type)
	{
		switch ($type) {
			case 1:
				return lang("app.cat");
			case 2:
				return lang("app.exam");
			case 3:
				return lang("app.secondSitting");
			case 9:
				return lang("app.reAssess");
		}
	}

	/**
	 * Marks entry rules:
	 * - "0" = scored zero (stored as 0)
	 * - "" / "-" = did not sit test (stored as -1, shown as "-")
	 * Calculations treat -1 as 0.
	 */
	public static function normalizeMarkEntry($raw)
	{
		if ($raw === null) {
			return -1;
		}
		$v = is_string($raw) ? trim($raw) : $raw;
		if ($v === '' || $v === '-' || $v === '--' || $v === '-1') {
			return -1;
		}
		if (!is_numeric($v)) {
			return -1;
		}
		return 0 + $v;
	}

	/** Format stored mark for the entry input field. */
	public static function displayMarkEntry($stored, $markId = '')
	{
		$hasRecord = $markId !== null && $markId !== '' && (string) $markId !== '0';
		if (!$hasRecord && ($stored === null || $stored === '')) {
			return '';
		}
		if ($stored === null || $stored === '' || (is_numeric($stored) && (float) $stored < 0)) {
			return '-';
		}
		if ((float) $stored == 0.0) {
			return '0';
		}
		return (string) $stored;
	}

	/** SQL expression: treat absent (-1) as zero in averages. */
	public static function sqlMarkValue($alias = 'marks.marks')
	{
		return "IF(COALESCE({$alias},0)<0,0,COALESCE({$alias},0))";
	}

	public static function ModeToStr($type)
	{
		switch ($type) {
			case 0:
				return lang("app.boarding");
			case 1:
				return lang("app.day");
		}
	}

	public static function TermToStr($type)
	{
		switch ($type) {
			case 1:
				return lang("app.term1");
			case 2:
				return lang("app.term2");
			case 3:
				return lang("app.term3");
		}
	}

	public function login($type = null)
	{
		$data["email"] = $this->session->getFlashdata("email");
		$data["error"] = $this->session->getFlashdata("error");
		$data['type'] = $type;
		return view("login", $data);
	}

	public
	function login_pro()
	{
		$model = new StaffModel();
		$email = $this->request->getPost('email');
		$password = $this->request->getPost('password');
		$validation = \Config\Services::validation();
		$validation->setRule("email", 'email', 'trim|required');
		$validation->setRule("password", 'password', 'required|min_length[6]');
		if ($validation->run() !== FALSE) {
			$this->session->setFlashdata('email', $email);
			if ($this->request->getGet("type", true) == "ajax") {
				echo '{"type":"error","msg":"' . $validation->getError() . '"}';
			} else {
				$this->session->setFlashdata('error', $validation->getError());
				$this->session->setFlashdata('email', $email);
				return redirect()->to(base_url("login"));
			}
		} else {
			$result = $model->checkUser($email);
			$this->session->setFlashdata('email', $email);
			if ($result != null) {
				if (password_verify($password, $result->password)) {
					if ($result->status == 1 || $result->status == 2) {
						if ($result->school_status == 0) {
							if ($this->request->getGet("type", true) == "ajax") {
								echo '{"type":"error","msg":"login done"}';
							} else {
								$this->session->setFlashdata('error', lang("app.accountLocked"));
								return redirect()->to(base_url('login'));
							}
						} else {
							$picture = strlen($result->photo) > 4 ? $result->photo : "../no_image.jpg";
							$schoolMdl = new SchoolModel();
							$data = array(
									'soma_name' => $result->fname . ' ' . $result->lname,
									'soma_email' => $result->email,
									'soma_id' => $result->id,
									'soma_school_id' => $result->school_id,
									'soma_school' => $result->school_name,
									'soma_post' => $result->post,
									'soma_post_title' => $result->post_title,
									'soma_picture' => $picture,
									'soma_status' => $result->status,
									'soma_academics_year' => $result->academic_year,
									$this->log_status => true
							);
							$this->session->set($data);
							$model->save(array("id" => $result->id, "last_login" => time()));
							if ($this->request->getGet("type", true) == "ajax") {
								echo '{"type":"success","msg":"login done"}';
							} else {
								return redirect()->to(base_url('dashboard'));
							}
						}
					} else {
						if ($this->request->getGet("type", true) == "ajax") {
							echo '{"type":"error","msg":"Account not active"}';
						} else {
							$this->session->setFlashdata('error', lang("app.accountNoActive"));
							return redirect()->to(base_url("login"));
						}
					}
				} else {
					if ($this->request->getGet("type", true) == "ajax") {
						echo '{"type":"error","msg":"Password not correct"}';
					} else {
						$this->session->setFlashdata('error', lang("app.passIncorrect"));
						return redirect()->to(base_url("login"));
					}
				}
			} else {
				if ($this->request->getGet("type", true) == "ajax") {
					echo '{"type":"error","msg":"User not found"}';
				} else {
					$this->session->setFlashdata('error', lang("app.userNotFound"));
					return redirect()->to(base_url("login"));
				}
			}
		}
	}

	public
	function verify_password($direct = false)
	{
		$password = $this->request->getPost('password');
		$model = new StaffModel();
		$result = $model->checkUser($this->session->get("soma_id"), "staffs.id");
		if ($result != null) {
			if (password_verify($password, $result->password)) {
				if ($result->status == 1 || $result->status == 2) {
					if ($result->school_status == 0) {
						if ($direct) {
							return false;
						}
						return $this->response->setStatusCode(400)->setJSON(['message' => lang("app.accountLocked")]);
					} else {
						if ($direct) {
							return true;
						}
						return $this->response->setJSON(['success' => 1]);
					}
				} else {
					if ($direct) {
						return false;
					}
					return $this->response->setStatusCode(400)->setJSON(['message' => lang("app.accountNoActive")]);
				}
			} else {
				if ($direct) {
					return false;
				}
				return $this->response->setStatusCode(400)->setJSON(['message' => lang("app.passIncorrect")]);
			}
		} else {
			if ($direct) {
				return false;
			}
			return $this->response->setStatusCode(400)->setJSON(['message' => lang("app.userNotFound")]);
		}

	}

	public
	function api_login($password, &$msg)
	{
		$model = new StaffModel();
		$result = $model->checkUser($this->session->get("soma_id"), "staffs.id");
		if ($result != null) {
			if (password_verify($password, $result->password)) {
				if ($result->status == 1 || $result->status == 2) {
					if ($result->school_status == 0) {
						$msg = lang("app.accountLocked");
						return false;
					} else {
						//login successful
						return true;
					}
				} else {
					$msg = lang("app.accountNoActive");
					return false;
				}
			} else {
				$msg = lang("app.passIncorrect");
				return false;
			}
		} else {
			$msg = lang("app.userNotFound");
			return false;
		}

	}

	public function change_password()
	{
		$oldpwd = $this->request->getPost("current_password");
		$pwd = $this->request->getPost("password");
		$staffMdl = new StaffModel();
		$result = $staffMdl->checkUser($this->session->get("soma_id"), 'staffs.id');
		if ($result != null) {
			if (password_verify($oldpwd, $result->password)) {
				if ($result->status == 1 || $result->status == 2) {
					$data = array(
							'id' => $this->session->get("soma_id"), 'password' => password_hash($pwd, PASSWORD_DEFAULT)
					, 'status' => 1
					);
					try {
						$staffMdl->save($data);
						$this->session->set("soma_status", 1);
						return $this->response->setJSON(array("success" => lang("app.passwordChangedSuccessfully")));
					} catch (\Exception $e) {
						return $this->response->setJSON(array("error" => lang("app.changePasswordFailed")));
					}
				} else {
					return $this->response->setJSON(array("error" => lang("app.accountNoActive")));
				}
			} else {
				return $this->response->setJSON(array("error" => lang("app.currentPasswordNorrect")));
			}
		}
	}

	public
	function logout($msg = null)
	{
		session_destroy();
		$this->session->setFlashdata("error", $msg);
		return redirect()->to(base_url('login'));
	}

	public function get_address($target)
	{
		$addressModel = new AddressModel();
		$key = $this->request->getGet("key");
		$val = $this->request->getGet("val");
		echo "<option selected disabled>" . lang("app.select") . "{$target}</option>";
		foreach ($addressModel->getAddress("soma_" . $target, $val, $key) as $data) {
			echo "<option value='{$data['id']}'>{$data['title']}</option>";
		}
	}

	public function test()
	{
		print_r(explode("/", "Cat/50"));
	}

	public function test2()
	{
//		$StudentModel = new StudentModel();
//		$markMdl = new MarksModel();
//		$marks = $markMdl->select("id,student_id")->where("class_id", "0")->get()->getResultArray();
//
//		foreach ($marks as $mark) {
//			$students = $StudentModel->select("cr.class")
//				->join("class_records cr", "students.id=cr.student")
//				->where("students.id", $mark["student_id"])
//				->groupBy("students.id")
//				->get()->getRowArray();
//			try {
//				$st = array("id" => $mark['id'], "class_id" => $students['class']);
//				$markMdl->save($st);
//			} catch (\Exception $e) {
//				echo $e->getMessage() . "<br>";
//			}
//		}
//		echo "Total affected rows: " . count($marks);
	}

	public function manipulate_student($id = null)
	{
		$this->_preset(1, 3, 4, 5, 6);
		$fname = $this->request->getPost("fname");
		$lname = $this->request->getPost("lname");
		$email = $this->request->getPost("email");
		$dob = str_replace("/", "", $this->request->getPost("dob"));
		$dob_v = str_split($dob, 2);
		$dob = $dob_v[2] . $dob_v[3] . '-' . $dob_v[1] . '-' . $dob_v[0];//yyyy-mm-dd
		$sex = $this->request->getPost("sex");
		$nationality = $this->request->getPost("nationality");
		$village = $this->request->getPost("village");
		$class = $this->request->getPost("class");
		$mode = $this->request->getPost("mode");
		$religion = $this->request->getPost("religion");
		$father = $this->request->getPost("father");
		$ft_phone = $this->request->getPost("father_phone");
		$mother = $this->request->getPost("mother");
		$mt_phone = $this->request->getPost("mother_phone");
		$guardian = $this->request->getPost("guardian");
		$gd_phone = $this->request->getPost("guardian_phone");
		$isParent = false;
		$isParentPhone = false;
		if (strlen($father) > 3)
			$isParent = true;
		if (strlen($ft_phone) > 3)
			$isParentPhone = true;

		if (strlen($mother) > 3)
			$isParent = true;
		if (strlen($mt_phone) > 3)
			$isParentPhone = true;

		if (strlen($guardian) > 3)
			$isParent = true;
		if (strlen($gd_phone) > 3)
			$isParentPhone = true;
		if (!$isParent) {
			return $this->response->setJSON(array("error" => lang("app.oneParentRequired")));
		}
		if (!$isParentPhone) {
			return $this->response->setJSON(array("error" => lang("app.oneParentPhoneRequired")));
		}
//		return $this->response->setJSON(array("error"=>"Error: ".$dob));
		$studentMdl = new StudentModel();
		try {
			$school_id = $this->session->get("soma_school_id");
			$classMdl = new ClassesModel();
			$classData = $classMdl->select("classes.id,classes.title,d.title as department_name,d.code,l.title as level_name
											,f.type,f.abbrev as faculty_code")
					->join("departments d", "d.id=classes.department")
					->join("levels l", "l.id=classes.level")
					->join("faculty f", "f.id=d.faculty_id")
					->where("classes.id", $class)->get(1)->getRow();
			$regno = $this->_generate_regno(true);//save permanent regno
			$uvMdl = new UpdateVersionModel();
			$update_v = 1;
			$update_v_data = $uvMdl->select("version")->where("type", "student")->where("school_id", $school_id)->get(1)->getRow();
			if ($update_v_data != null)
				$update_v = $update_v_data->version;
			$dt = array("school_id" => $school_id, "fname" => $fname, "lname" => $lname, "email" => $email, "regno" => $regno, "sex" => $sex, "status" => "1"
			, "dob" => $dob, "village_id" => $village, "studying_mode" => $mode, "religion" => $religion, "nationality" => $nationality, "father" => $father, "ft_phone" => $ft_phone
			, "mother" => $mother, "mt_phone" => $mt_phone, "guardian" => $guardian, "gd_phone" => $gd_phone, "created_by" => $this->session->get("soma_id"), "updateVersion" => $update_v);
			$id = $studentMdl->insert($dt);
			//create class record
			$classRecordMdl = new ClassRecordModel();
			$classRecordMdl->save(array("student" => $id, "year" => $this->data['academic_year'], "class" => $class));
			$msg = "{$this->data['school_name']} irakumenyesha ko {$fname} {$lname} yanditswe neza muri {$classData->level_name} {$classData->code} {$classData->title}";
			if (strlen($ft_phone) > 3) {
//				if ($this->_send_sms($ft_phone, $msg, $result, $this->data['remaining_sms'], $this->data['school_acronym'])) {
//					//save sent sms
//					$sms_count = (int)ceil(strlen($msg) / PER_SMS);
//					$this->_save_sms($this->data['active_term'], $ft_phone, $msg, lang("app.studentRegistration"), $id, 1, $sms_count);
//				} else {
//					$this->_save_sms($this->data['active_term'], $ft_phone, $msg, lang("app.studentRegistration"), $id, 1, 0);
//				}
                if ($this->sendSMS($ft_phone, $msg, $result)) {
                    //save sent sms
                    $sms_count = (int)ceil(strlen($msg) / PER_SMS);
                    $this->_save_sms($this->data['active_term'], $ft_phone, $msg, lang("app.studentRegistration"), $id, 1, $sms_count);
                } else {
                    $this->_save_sms($this->data['active_term'], $ft_phone, $msg, lang("app.studentRegistration"), $id, 1, 0);
                }
			}
			if (strlen($mt_phone) > 3) {
				if ($this->sendSMS($mt_phone, $msg)) {
					//save sent sms
					$sms_count = (int)ceil(strlen($msg) / PER_SMS);
					$this->_save_sms($this->data['active_term'], $mt_phone, $msg, lang("app.studentRegistration"), $id, 1, $sms_count);
				} else {
					$this->_save_sms($this->data['active_term'], $mt_phone, $msg, lang("app.studentRegistration"), $id, 1, 0);
				}
			}
			if (strlen($gd_phone) > 3) {
				if ($this->sendSMS($gd_phone, $msg, $result)) {
					//save sent sms
					$sms_count = (int)ceil(strlen($msg) / PER_SMS);
					$this->_save_sms($this->data['active_term'], $gd_phone, $msg, lang("app.studentRegistration"), $id, 1, $sms_count);
				} else {
					$this->_save_sms($this->data['active_term'], $gd_phone, $msg, lang("app.studentRegistration"), $id, 1, 0);
				}
			}
			return $this->response->setJSON(array("success" => $fname . lang("app.enrolledSuccessfully") . " <strong>" . $regno . "</strong>"));
		} catch (\Exception $e) {
//			var_dump($e);
			return $this->response->setJSON(array("error" => "Error: " . $e->getMessage()));
		}
	}

	public function manipulate_dept($id = null): Response
	{
		$this->_preset();
		$title = $this->request->getPost("title");
		$code = $this->request->getPost("code");
		$depModel = new DeptModel();
		$data = array("title" => $title, "code" => $code, "created_by" => $this->session->get("soma_id"));
		try {
			$depModel->save($data);
			return $this->response->setJSON(array("success" => lang("app.DepartmentSaved")));
		} catch (\Exception $e) {
			return $this->response->setJSON(array("error" => "Error: " . $e->getMessage()));
		}
	}

	public function manipulate_settings($type = "text", $link = "s")
	{
		$this->_preset(1, 3);
		$id = $this->request->getPost("id");
		$target = $this->request->getPost("target");
		$val = $this->request->getPost("val");
		if (strlen($target) == 0) {
			return $this->response->setJSON(array("error" => lang("app.pleaseProvide")));
		}
		//echo "id:$id,target: $target,val: $val";die();
		$data = array("id" => $id, $target => $val);
		$sklMdl = new SchoolModel();
		try {
			$sklMdl->save($data);
			switch ($type) {
				case "number":
					$result = number_format($val);
					break;
				case "status":
					$result = ($val == 0 ? "<span class='text-danger'>" . lang("app.disabled") . "</span>" : "<span class='text-success'>" . lang("app.enabled") . "</span>");
					break;
				default:
					$result = $val;
					break;
			}
			return $this->response->setJSON(array("success" => lang("app.settingsSaved"), "result" => "&nbsp;" . $result));
		} catch (\Exception $e) {
			return $this->response->setJSON(array("error" => "Error: " . $e->getMessage()));
		}
	}

	public function edit_student($type = "text", $link = "s")
	{
		$id = $this->request->getPost("id");
		$target = $this->request->getPost("target");
		$val = $this->request->getPost("val");
		if (strlen($target) == 0) {
			return $this->response->setJSON(["error" => lang("app.pleaseProvide"), "msg" => lang("app.pleaseProvide")]);
		}
		if ($target == 'sex' && !in_array($val, ['F', 'M'])) {
			return $this->response->setJSON(["error" => "Sex must be F or M", "msg" => "Sex must be F or M"]);
		}
		//echo "id:$id,target: $target,val: $val";die();
		$uvMdl = new UpdateVersionModel();
		$update_v = 1;
		$update_v_data = $uvMdl->select("version")->where("type", "student")->where("school_id", $this->session->get("soma_school_id"))->get(1)->getRow();
		if ($update_v_data != null)
			$update_v = $update_v_data->version;
		$data = array("id" => $id, $target => $val, "updateVersion" => $update_v);
		$stMdl = new StudentModel();
		try {
			$stMdl->save($data);
			switch ($type) {
				case "number":
					$result = number_format($val);
					break;
				case "status":
					$result = ($val == 0 ? "<span class='text-danger'>" . lang("app.disabled") . "</span>" : "<span class='text-success'>" . lang("app.enabled") . "</span>");
					break;
				default:
					$result = $val;
					break;
			}
			return $this->response->setJSON(array("success" => lang("app.studentDataSaved"), "result" => "&nbsp;" . $result));
		} catch (\Exception $e) {
			return $this->response->setJSON(array("error" => "Error: " . $e->getMessage()));
		}
	}

	public function edit_staff($type = "text", $link = "s")
	{
		$id = $this->request->getPost("id");
		$target = $this->request->getPost("target");
		$val = $this->request->getPost("val");
		if (strlen($target) == 0) {
			return $this->response->setJSON(array("error" => lang("app.pleaseProvide")));
		}
		//echo "id:$id,target: $target,val: $val";die();
		$uvMdl = new UpdateVersionModel();
		$update_v = 1;
		$update_v_data = $uvMdl->select("version")->where("type", "staff")->where("school_id", $this->session->get("soma_school_id"))->get(1)->getRow();
		if ($update_v_data != null)
			$update_v = $update_v_data->version;
		$data = array("id" => $id, $target => $val, "updateVersion" => $update_v);
		$stMdl = new StaffModel();
		try {
			$stMdl->save($data);
			switch ($type) {
				case "number":
					$result = number_format($val);
					break;
				case "status":
					$result = ($val == 0 ? "<span class='text-danger'>" . lang("app.disabled") . "</span>" : "<span class='text-success'>" . lang("app.enabled") . "</span>");
					break;
				default:
					$result = $val;
					break;
			}
			return $this->response->setJSON(array("success" => lang("app.staffDataSaved"), "result" => "&nbsp;" . $result));
		} catch (\Exception $e) {
			return $this->response->setJSON(array("error" => "Error: " . $e->getMessage()));
		}
	}

	public function manipulate_course_category($id = null)
	{
		$this->_preset();
		$title = $this->request->getPost("title");
		$categoryMdl = new CourseCategoryModel();
		$data = array("title" => $title, "school_id" => $this->session->get("soma_school_id"));
		try {
			$categoryMdl->save($data);
			return $this->response->setJSON(array("success" => lang("app.courseCategorySaved")));
		} catch (\Exception $e) {
			return $this->response->setJSON(array("error" => "Error: " . $e->getMessage()));
		}
	}

	public function manipulate_term($id = null)
	{
		$this->_preset();
		$password = $this->request->getPost("password");
		if ($this->api_login($password, $msg) !== true) {
			return $this->response->setJSON(array("error" => $msg));
		}
//		$data = $this->data;
		$academic_id = $this->request->getPost("academic_year");
		$term = $this->request->getPost("term");
		$periods = empty($this->request->getPost("period")) ? 0 : 1;
		$termMdl = new TermModel();
		$school_id = $this->session->get("soma_school_id");
		//try to check if it is previous term
		$term_dt = $termMdl->select("term")->where('academic_year', $academic_id)->where("school_id", $school_id)->orderBy("id", "desc")->get()->getRow();
		if ($term_dt != null) {
			if ($term_dt->term == $term) {
//				return $this->response->setJSON(array("error" => lang("app.currentlyEnabld")));
			} else if ($term_dt->term > $term) {
//				return $this->response->setJSON(array("error" => lang("app.canNotSwitch")));
			}
		}
		$data = array("school_id" => $school_id, "academic_year" => $academic_id, "term" => $term, "sms_usage" => 0, "use_period" => $periods, "created_by" => $this->session->get("soma_id"));
		try {
			$termData = $termMdl->select("id")
					->where('school_id', $school_id)
					->where('term', $term)
					->where('academic_year', $academic_id)
					->get()->getRow();
			if ($termData == null) {
				$active_term = $termMdl->insert($data);
				if ($active_term === false)
					return $this->response->setJSON(array("error" => lang("app.savactivetermeRR")));
			} else {
				//term data already exists — reuse and refresh periodic flag
				$active_term = $termData->id;
				$termMdl->save(array("id" => $active_term, "use_period" => $periods));
			}
			$schoolMdl = new SchoolModel();
			//update school active term
			$schoolMdl->save(array("id" => $school_id, "active_term" => $active_term));
			return $this->response->setJSON(array("success" => lang("app.activeTermSet")));
		} catch (\Exception $e) {
			return $this->response->setJSON(array("error" => "Error: " . $e->getMessage()));
		}
	}

	public function manipulate_shift()
	{
		$this->_preset();
		$data = $this->data;
		$shiftMdl = new ShiftModel();
		$hours = $this->request->getPost('hours');
		if ($hours == null) {
			return $this->response->setJSON(array("error" => lang("app.adAtLeastOne")));
		}
		$arr = array();
		$a = 0;
		foreach ($hours as $hour) {
			$weekday = substr($hour, 0, 1);
			if (in_array($weekday, $arr)) {
				return $this->response->setJSON(array("error" => lang("app.weekdayDuplicate") . " <strong>$weekday</strong>"));
				break;
			}
			$arr[$a] = $weekday;
			$a++;
		}
		$hourss = json_encode($hours);
		$data = array(
				"school_id" => $this->session->get("soma_school_id"),
				"title" => $this->request->getPost("title"),
				"options" => $hourss,
				"status" => '1',
				"created_by" => $this->session->get("soma_id"));
		try {
			$shiftMdl->save($data);
			return $this->response->setJSON(array("success" => lang("app.shiftSaved")));
		} catch (\Exception $e) {
			return $this->response->setJSON(array("error" => "Error: " . $e->getMessage()));
		}
	}

	public function manipulate_staff($id = null)
	{
		$this->_preset(1, 3);
		$fname = $this->request->getPost("fname");
		$lname = $this->request->getPost("lname");
		$email = $this->request->getPost("email");
		$phone = $this->request->getPost("phone");
		$post = $this->request->getPost("privilege");
		$country = $this->request->getPost("country");
		$city = $this->request->getPost("city");
		$address = $this->request->getPost("address");
		$shift = (int) ($this->request->getPost("shift") ?? 0);
		$default_password = $this->random_password();
		try {
			$staffMdl = new StaffModel();
			$school_id = $this->session->get("soma_school_id");
			$uvMdl = new UpdateVersionModel();
			$update_v = 1;
			$update_v_data = $uvMdl->select("version")->where("type", "staff")->where("school_id", $school_id)->get(1)->getRow();
			if ($update_v_data != null)
				$update_v = $update_v_data->version;
			$id = $staffMdl->insert(array("school_id" => $school_id, "fname" => $fname, "lname" => $lname, "phone" => $phone, "email" => $email, "password" => password_hash($default_password, PASSWORD_DEFAULT)
			, "status" => 2, "post" => $post, "shift_id" => $shift > 0 ? $shift : 0, "country" => $country, "city" => $city, "address" => $address, "updateVersion" => $update_v));
			$name = $fname . " " . strtoupper(substr($lname, 0, 1)) . ".";
			//send notification EMAIL and SMS
			$msg = lang("app.dear") . " $name" . lang("app.accountIsCreated") . ", \nEmail: "
					. $email . "\n" . lang("app.password") . ": " . $default_password . "\n " . lang("app.thankyou");
			$msg2 = lang("app.dear") . " $name" . lang("app.accountIsCreated") . ", \nEmail: "
					. $email . "\n" . lang("app.password") . ": ********** \n " . lang("app.thankyou");

			if ($this->sendSMS($phone, $msg, $result)) {
				//save sent sms
				$sms_count = (int)ceil(strlen($msg) / PER_SMS);
				$this->_save_sms($this->data['active_term'], $phone, $msg2, lang("app.staffCreation"), $id, 1, $sms_count);
			} else {
				$this->_save_sms($this->data['active_term'], $phone, $msg2, lang("app.staffCreation"), $id, 1, 0, $result);
			}
			$data = array("name" => $name, "phone" => $phone, "email" => $email, "default_password" => $default_password);
			$html_msg = view("emails/staff_creation", $data);
			$sent = $this->_send_email($email, lang("app.welcomeOnSomanet"), $html_msg);
			if (! $sent) {
				return $this->response->setJSON(array(
					"success" => lang("app.userSaved"),
					"warning" => "Staff saved but welcome email could not be sent. Check SMTP settings in .env.",
				));
			}
			return $this->response->setJSON(array("success" => lang("app.userSaved") . " Welcome email sent."));
		} catch (\Exception $e) {
			if ($e->getCode() == 1062) {
				return $this->response->setJSON(array("error" => lang("app.emailAlready")));
			}
			return $this->response->setJSON(array("error" => lang("app.errorOccurred") . $e->getCode()));
		}
	}

	public function manipulate_cl()
	{
		$this->_preset();
		$data = $this->data;
		$classesModel = new ClassesModel();
		$data = array(
				"school_id" => $this->session->get("soma_school_id"),
				"level" => $this->request->getPost("levels"),
				"department" => $this->request->getPost("depts"),
				"title" => $this->request->getPost("subclass"),
				"mentor" => $this->request->getPost("teacher"),
				"created_by" => $this->session->get("soma_id"));
		try {
			$classesModel->save($data);
			return $this->response->setJSON(array("success" => lang("app.classSaved")));
		} catch (\Exception $e) {
			if ($e->getCode() == 1062) {
				return $this->response->setJSON(array("error" => lang("app.classExist")));
			}
			return $this->response->setJSON(array("error" => "Error: " . $e->getMessage()));
		}
	}

	public function manipulate_assign_course()
	{
		$this->_preset();
		$data = $this->data;
		$course = null;
		$classes = null;
		$status = $this->request->getPost("status");
		$id = $this->request->getPost('fid');

		if ($status == 1) {
			$course = $this->request->getPost("fId");
			$classes = $this->request->getPost("classes");
		}
		if ($status == 2) {
			$course = $this->request->getPost("classes");
			$classes = $this->request->getPost("fId");
		}
		$year = $this->data['academic_year'];
		$term = $this->request->getPost("term[]");
		$CourseRecordModel = new CourseRecordModel();
		$activityModel = new ActivityModel();
		//check if course is assigned to class
		$dt = $CourseRecordModel->select("count(id) as cc")->where("course='$course' AND class='$classes' AND year='$year'")->get()->getRow();
		if ($dt->cc > 0) {
			//course already assigned to teacher
			return $this->response->setJSON(array("error" => lang("app.courseAlready")));
		}
		if ($id == null) {
			$term = implode(",", $term);
			$data = array(
					"course" => $course,
					"lecturer" => $this->request->getPost("teacher"),
					"class" => $classes,
					"year" => $year,
					"term" => $term);
		} else {
			//get teachers name, for history
			$old_data = $CourseRecordModel->select("concat(st.fname,' ',st.lname) as name,cs.title")->join("staffs st", "st.id=course_records.lecturer")
					->join("courses cs", "cs.id=course_records.course")
					->where("course_records.id", $id)->get(1)->getRow();
			$stMdl = new StaffModel();
			$new_teacher = $stMdl->select("concat(fname,' ',lname) as name")->where("id", $this->request->getPost("teacher"))->get(1)->getRow();
			$data = array(
					"id" => $id,
					"lecturer" => $this->request->getPost("teacher"));
			$activity = array(
					"school_id" => $this->session->get("soma_school_id"),
					"activity" => lang("app.thisSubject") . " <strong>" . $old_data->title . "</strong>" . lang("app.isMovedFrom") . " " . $old_data->name . lang("app.andAssignedTo") . $new_teacher->name
			);
			$activityModel->save($activity);

		}

		try {
			$CourseRecordModel->save($data);

			return $this->response->setJSON(array(
				"success" => lang("app.courseAssignedSuccess"),
				"course_id" => (int) $course,
			));
		} catch (\Exception $e) {
			return $this->response->setJSON(array("error" => "Error: " . $e->getMessage()));
		}
	}

	/**
	 * Assignments for a course in the active academic year (smart view).
	 */
	public function get_course_assignments($courseId = 0)
	{
		$this->_preset();
		$courseId = (int) $courseId;
		$year = (int) ($this->data['academic_year'] ?? 0);
		$schoolId = (int) $this->session->get("soma_school_id");
		if ($courseId <= 0) {
			return $this->response->setJSON(["assignments" => []]);
		}
		$CourseRecordModel = new CourseRecordModel();
		$rows = $CourseRecordModel->select("course_records.id, course_records.term,
				concat(l.title,' ',d.code,' ',if(classes.title='','',classes.title)) as class_name,
				concat(s.fname,' ',s.lname) as teacher_name")
			->join("classes", "classes.id=course_records.class")
			->join("departments d", "d.id=classes.department")
			->join("levels l", "l.id=classes.level")
			->join("staffs s", "s.id=course_records.lecturer", "LEFT")
			->where("course_records.course", $courseId)
			->where("course_records.year", $year)
			->where("classes.school_id", $schoolId)
			->orderBy("class_name", "ASC")
			->get()->getResultArray();

		return $this->response->setJSON(["assignments" => $rows, "course_id" => $courseId]);
	}

	public function get_faculty($val)
	{
		$faculty = new facultyModel();
		$faculities = $faculty->where("type", $val)->get()->getResultArray();
		echo "<option selected disabled>" . lang("app.select") . "</option>";
		foreach ($faculities as $data) {
			echo "<option value='{$data['id']}'>{$data['title']}</option>";
		}

	}

	public function get_dept($val)
	{
		$dept = new DeptModel();
		$depts = $dept->where("faculty_id", $val)->get()->getResultArray();
		echo "<option selected disabled>" . lang("app.select") . "</option>";
		foreach ($depts as $data) {
			echo "<option value='{$data['id']}'>{$data['code']}-{$data['title']}</option>";
		}
	}

	public function get_course_category()
	{
		$categMdl = new CourseCategoryModel();
		$categs = $categMdl->where("school_id", $this->session->get("soma_school_id"))->get()->getResultArray();
		echo "<option selected disabled>" . lang("app.chooseCategory") . "</option>";
		foreach ($categs as $data) {
			echo "<option value='{$data['id']}'>{$data['title']}</option>";
		}
	}

	public function get_levels($val, $fac = 0)
	{
		$levels = new LevelsModel();
		$key = "type";
		if ($fac == 1)
			$key = "faculty_id";
		$levs = $levels->where($key, $val)->orderBy("title")->get()->getResultArray();
		echo "<option selected disabled>" . lang("app.select") . "</option>";
		foreach ($levs as $data) {
			echo "<option value='{$data['id']}'>{$data['title']}</option>";
		}
	}

	public function get_posts()
	{
		$postsMdl = new PostsModel();
		$posts = $postsMdl->orderBy("title", "ASC")->get()->getResultArray();
		echo "<option selected disabled>" . lang("app.selectPrevilagies") . "</option>";
		foreach ($posts as $data) {
			echo "<option value='{$data['id']}'>{$data['title']}</option>";
		}
	}

	public function get_provinces()
	{
		$addressModel = new AddressModel();
		$provinces = $addressModel->getProvince();
		echo "<option selected disabled>" . lang("app.selectProvince") . "</option>";
		foreach ($provinces as $data) {
			echo "<option value='{$data['id']}'>{$data['title']}</option>";
		}

	}

	public function manipulate_course()
	{
		$this->_preset();
		$this->ensureCoursesMetaSchema();
		$courseModel = new CourseModel();
		$courseId = $this->request->getPost("courseId") ? $this->request->getPost("courseId") : 0;
		$creditRaw = $this->request->getPost("credit");
		$credit = is_numeric($creditRaw) ? (float) $creditRaw : 0.0;
		if ($credit < 0) {
			$credit = 0.0;
		}
		// Always Marks = Credit × 10 (credit 0 => marks 0)
		$marks = (int) round($credit * 10);
		$programType = strtolower(trim((string) $this->request->getPost('program_type')));
		if ($programType !== 'reb') {
			$programType = 'tvet';
		}
		if ($courseId == 0) {
			$data = array(
					"school_id" => $this->session->get("soma_school_id"),
					"title" => $this->request->getPost("title"),
					"code" => $this->request->getPost("code"),
					"category" => $this->request->getPost("category"),
					"credit" => $credit,
					"teacher_id" => $this->request->getPost("teacher"),
					"marks" => $marks,
					"program_type" => $programType,
					"create_source" => 'manual',
					"created_by" => $this->session->get("soma_id"));
		} else {
			$data = array(
					"id" => $courseId,
					"title" => $this->request->getPost("title"),
					"code" => $this->request->getPost("code"),
					"category" => $this->request->getPost("category"),
					"credit" => $credit,
					"teacher_id" => $this->request->getPost("teacher"),
					"marks" => $marks);
		}
		try {
			$courseModel->save($data);
			return $this->response->setJSON(array("success" => lang("app.courseSaved")));
		} catch (\Exception $e) {
			return $this->response->setJSON(array("error" => "Error: " . $e->getMessage()));
		}
	}

	public function manage_courses()
	{
		$this->_preset();
		$data = $this->data;
		$faculty = new FacultyModel();
		$staffMdl = new StaffModel();
		$courseModel = new CourseModel();
		$CourseCategory = new CourseCategoryModel();
		$acMdl = new AcademicYearModel();
		$classMdl = new ClassesModel();
		$school_id = $this->session->get("soma_school_id");
		$data['title'] = lang("app.manageCourse");
		$data['classes'] = $classMdl->select("classes.id,classes.title,d.title as department_name,d.code as dept_code,l.title as level_name
		,f.type,f.abbrev as faculty_code,concat(s.fname,' ',s.lname) as mentor_name,s.id as idstf")
				->join("departments d", "d.id=classes.department")
				->join("levels l", "l.id=classes.level")
				->join("faculty f", "f.id=d.faculty_id")
				->join("staffs s", "s.id=classes.mentor", "LEFT")
				->where("classes.school_id", $school_id)
				->get()->getResultArray();
		$data['courses'] = $courseModel->select("courses.id,courses.title,courses.code,courses.marks,courses.credit,cs.title as category")
				->join("course_category cs", "cs.id=courses.category")
				->where("courses.school_id", $school_id)
				->get()->getResultArray();
		$data['faculty'] = $faculty->get()->getResultArray();
		$data['years'] = $acMdl->select('id,title')->where("school_id", $school_id)
				->orderBy("id", 'DESC')->get()->getResultArray();
		$data['categories'] = $CourseCategory->get()->getResultArray();
		$data['staffs'] = $staffMdl->where("school_id", $school_id)->get()->getResultArray();
		$data['subtitle'] = lang("app.manageCourse");
		$data['page'] = "manage_Course";
		$data['content'] = view("pages/manage_course", $data);
		return view('main', $data);
	}

	public function get_class(int $course = 0, int $yearId = null)
	{
		$this->_preset();
		$classMdl = new ClassesModel();
		$year = $yearId ?? $this->data['academic_year'];
		if ($course > 0) {
			$builder = $classMdl->select("classes.id,classes.title,d.title as department_name,d.code,l.title as level_name
											,f.type,f.abbrev as faculty_code,concat(s.fname,' ',s.lname) as mentor_name")
					->join("departments d", "d.id=classes.department")
					->join("levels l", "l.id=classes.level")
					->join("faculty f", "f.id=d.faculty_id")
					->join("staffs s", "s.id=classes.mentor", "LEFT")
					->join("course_records cr", "cr.class=classes.id")
					->where("cr.year", $year)
					->where("cr.course", $course)
					->where("classes.school_id", $this->session->get("soma_school_id"))
					->groupBy("classes.id")
					->orderBy("d.code")
					->orderBy("l.title");

			if ($this->session->get("soma_post") != 1 && $this->session->get("soma_post") != 3) {
				//filter class by teacher if is not head master or dean of studies
				$builder->where("cr.lecturer", $this->session->get("soma_id"));
			}
			$classes = $builder->get()->getResultArray();
			echo "<option selected disabled>" . lang("app.selectClass") . "</option>";
			foreach ($classes as $classe) {
				echo "<option value='" . $classe['id'] . "'>" . $classe['level_name'] . " " . $classe['code'] . " " . $classe['title'] . "</option>";
			}
		}
	}


	public function get_course($val, $year, $type = 0)
	{
		$courseModel = new CourseModel();
		$courses = $courseModel->select("courses.id,courses.title,courses.code,courses.marks,r.term,courses.credit,cs.title as category,r.id record_id,r.class,concat(s.fname,' ',s.lname) as mentor_name")
				->join("course_category cs", "cs.id=courses.category")
				->join("course_records r", "courses.id=r.course")
				->join("staffs s", "s.id=r.lecturer")
				->where("courses.school_id", $this->session->get("soma_school_id"))
				->where("r.class", $val)
				->where("r.year", $year)
				->groupBy("courses.id")
				->get()->getResultArray();

		if ($type == 0) {
			//use table
			echo "<tr>
				<th>" . lang("app.title") . "</th>
				<th>" . lang("app.category") . "</th>
				<th>" . lang("app.maxMarks") . "</th>
				<th>" . lang("app.term") . "</th>
				<th>" . lang("app.lecturer") . "</th>
			     </tr>";

			foreach ($courses as $course) {
				$term = "";
				foreach (explode(",", $course['term']) as $t) {
					$term .= "<label style='border: 1px dashed rgba(6,22,7,0.95);padding: 2px;border-radius: 3px;margin-right: 4px'>" . $this->TermToStr($t) . "</label>";
				}
				echo "<tr>
				<td>" . $course['title'] . " <a class='link' data-toggle='modal' data-target='#editCourseModal'
				data-name='" . $course['title'] . "' data-id='" . $course['id'] . "'> <i class='fa fa-pencil-alt'></i></a>
				</td>
				<td>" . $course['category'] . "</td>
				<td>" . $course['marks'] . "</td>
				<td>" . $term . " <a class='link' data-toggle='modal' data-target='#editTermModal'
				data-name='" . $course['class'] . "' data-id='" . $course['record_id'] . "'> <i class='fa fa-pencil-alt'></i></a>
				</td>
				<td>" . $course['mentor_name'] . " <a class='link' data-toggle='modal' data-target='#editLecCourseModal'  data-id='" . $course['record_id'] . "'><i class='fa fa-pencil-alt'></i></a></td>
				<td style='text-align: center;'>
				<label class='typcn typcn-delete text-danger link' data-title='" . $course['title'] . " to class' data-toggle='delete'
																		   data-target='" . $course['id'] . "'  data-href='delete_course_assign/" . $course['record_id'] . "'>" . lang("app.del") . "</label></td>
				</tr>";
			}
		} else {
			echo "<option value='0' selected>" . lang("app.allCourses") . "</option>";
			foreach ($courses as $course) {
				echo "<option value='" . $course['id'] . "'>" . $course['code'] . " " . $course['title'] . "</option>";
			}

		}
	}

	public function get_course_record($id)
	{
		$CourseRecordModel = new CourseRecordModel();
		$school_id = $this->session->get("soma_school_id");
		$records = $CourseRecordModel->select("id,course,lecturer")
				->where("id", $id)
				->get()->getRowArray();
		echo json_encode($records);
	}

	public function delete_course_assign($id)
	{

		$CourseRecordModel = new CourseRecordModel();
		$mMdl = new MarksModel();
		try {
//			$r = $mMdl->select('marks.id,')
//				->join('active_term at', 'marks.term=at.id', 'INNER')
//				->join('course_records c', 'marks.course_id=c.course AND marks.class_id=c.class AND c.year=at.academic_year', 'INNER')
//				->where('c.id', $id)
//				->get(1)->getRow();
//			if ($r != null) {
//				return $this->response->setJSON(array("error" => "Error: This record has marks, can not be deleted"));
//			}
			$CourseRecordModel->delete($id);

			return $this->response->setJSON(array("success" => lang("app.recordDeleted")));
		} catch (\Exception $e) {
			return $this->response->setJSON(array("error" => "Error: " . $e->getMessage()));
		}

	}

	public function delete_staff()
	{
		$id = $this->request->getPost("data");
		$stfMdl = new StaffModel();
		try {
			$courseRMdl = new CourseRecordModel();
			$discMdl = new DisciplineModel();
			$permMdl = new PermissionModel();
			$marksMdl = new MarksModel();
			if ($courseRMdl->where("lecturer", $id)->get()->getRow() != null)
				return $this->response->setJSON(array("error" => lang("app.notDeletedcourse")));
			if ($discMdl->where("created_by", $id)->get()->getRow() != null)
				return $this->response->setJSON(array("error" => lang("app.notDeletedDiscipline")));
			if ($permMdl->where("created_by", $id)->get()->getRow() != null)
				return $this->response->setJSON(array("error" => lang("app.notDeletedPermission")));
			if ($marksMdl->where("created_by", $id)->get()->getRow() != null)
				return $this->response->setJSON(array("error" => lang("app.notDeletedMmarks")));
			$stfMdl->delete($id);
			return $this->response->setJSON(array("success" => lang("app.staffDeleted")));
		} catch (\Exception $e) {
			return $this->response->setJSON(array("error" => "Error: " . $e->getMessage()));
		}

	}

	public function delete_category()
	{
		$id = $this->request->getPost("data");
		$categoryMdl = new CourseCategoryModel();
		try {
			$courseMdl = new CourseModel();
			if ($courseMdl->where("category", $id)->get()->getRow() != null)
				return $this->response->setJSON(array("error" => lang("app.categoryNotDeleted")));
			$categoryMdl->delete($id);
			return $this->response->setJSON(array("success" => lang("app.categoryDeleted")));
		} catch (\Exception $e) {
			return $this->response->setJSON(array("error" => "Error: " . $e->getMessage()));
		}
	}

	public function delete_class()
	{
		$id = $this->request->getPost("data");
		$classMdl = new ClassesModel();
		try {
			$courseRMdl = new CourseRecordModel();
			$classRMdl = new ClassRecordModel();
			if ($courseRMdl->where("class", $id)->get()->getRow() != null)
				return $this->response->setJSON(array("error" => lang("app.classNotDeleted")));
			if ($classRMdl->where("class", $id)->get()->getRow() != null)
				return $this->response->setJSON(array("error" => lang("app.classNotDeleted")));
			$classMdl->delete($id);
			return $this->response->setJSON(array("success" => lang("app.classDeleted")));
		} catch (\Exception $e) {
			return $this->response->setJSON(array("error" => "Error: " . $e->getMessage()));
		}
	}

	public function remove_extra_fee()
	{
		$id = $this->request->getPost("id");
		$mld = new ExtraFeesModel();
		try {
			$rMdl = new FeesRecordModel();
			if ($rMdl->where("fees_id", $id)->where("fees_type", 1)->get()->getRow() != null)
				return $this->response->setJSON(array("error" => 'Fees can not be deleted, because it is used'));

			$mld->delete($id);
			return $this->response->setJSON(array("success" => 'fees deleted successfully'));
		} catch (\Exception $e) {
			return $this->response->setJSON(array("error" => "Error: " . $e->getMessage()));
		}
	}

	public function revoke_deliberation()
	{
		$id = $this->request->getPost("id");
		$dMdl = new DeliberationRecords();
		try {
			$dMdl->delete($id);
			return $this->response->setJSON(array("success" => "1", 'message' => lang("app.deliberationRevoked")));
		} catch (\Exception $e) {
			return $this->response->setJSON(array("success" => '0', 'message' => "Error: " . $e->getMessage()));
		}
	}

	public function delete_marks()
	{
		if (!in_array($this->session->get("soma_post"), [1, 3])) {
			return $this->response->setJSON(array("error" => "Oops, Only head master or dean of study can delete marks "));
		}
		$this->_preset(1, 3);
		$ids = '';
		if (!empty($this->request->getPost("data"))) {
			$ids = $this->request->getPost("data");
		}
		if (!empty($this->request->getPost("data1"))) {
			if (!empty($ids)) {
				$ids .= ',';
			}
			$ids .= $this->request->getPost("data1");
		}
		$term = $this->request->getPost("term");
		$year = $this->request->getPost("year");
		if (strlen($ids) == 0) {
			return $this->response->setJSON(array("error" => "Invalid marks data"));
		}
		$atMdl = new ActiveTermModel();
		$term_data = $atMdl->select("id")
				->where("school_id", $this->session->get("soma_school_id"))
				->where("term", $term)
				->where("academic_year", $year)
				->get()->getRow();
		if ($term_data == null) {
			return $this->response->setJSON(array("error" => "Invalid marks term data"));
		}
		if ($term_data->id != $this->data['active_term']) {
			return $this->response->setJSON(array("error" => "Oops, You can only delete marks of current active term only"));
		}
		$marksMdl = new MarksModel();
		try {
			$marksMdl->whereIn("id", explode(",", $ids))->delete();
			return $this->response->setJSON(array("success" => "Marks delete successfully"));
		} catch (\Exception $e) {
			return $this->response->setJSON(array("error" => "Error: " . $e->getMessage()));
		}
	}

	public function delete_student()
	{
		$id = $this->request->getPost("data");
		$stMdl = new StudentModel();
		try {
			$mksMdl = new MarksModel();
			$dscMdl = new DisciplineModel();
			$permMdl = new PermissionModel();
			$clRecord = new ClassRecordModel();
			$isUsed = false;
			/**** disable student checking as requested by Methode on 10/06/2021 ****
			 * if ($mksMdl->where("student_id", $id)->get()->getRow() != null)
			 * $isUsed = true;
			 * if ($dscMdl->where("student_id", $id)->get()->getRow() != null)
			 * $isUsed = true;
			 * if ($permMdl->where("student_id", $id)->get()->getRow() != null)
			 * $isUsed = true;
			 * //check if has record in library, finance and attendance
			 * if ($isUsed)
			 * return $this->response->setJSON(array("error" => lang("app.studentNotDeleted")));
			 */
			$stMdl->delete($id);
			//remove class record
			$clRecord->where("student", $id)->delete();
			$mksMdl->where("student_id", $id)->delete();
			$dscMdl->where("student_id", $id)->delete();
			$permMdl->where("student_id", $id)->delete();
			return $this->response->setJSON(array("success" => lang("app.studentDeleted")));
		} catch (\Exception $e) {
			return $this->response->setJSON(array("error" => "Error: " . $e->getMessage()));
		}
	}

	public function discipline_record_entry()
	{
		$this->_preset();
		$data = $this->data;
		$classMdl = new ClassesModel();
		$SchoolModel = new SchoolModel();
		$data['title'] = lang("app.disciplineRecordEntry");
		$data['classes'] = $classMdl->select("classes.id,classes.title,d.title as department_name,d.code,l.title as level_name
		,f.type,f.abbrev as faculty_code,concat(s.fname,' ',s.lname) as mentor_name,s.id as idstf")
				->join("departments d", "d.id=classes.department")
				->join("levels l", "l.id=classes.level")
				->join("faculty f", "f.id=d.faculty_id")
				->join("staffs s", "s.id=classes.mentor", "LEFT")
				->where("classes.school_id", $this->session->get("soma_school_id"))
				->get()->getResultArray();
		$data['activeTerm'] = $SchoolModel->select("at.term,at.id")
				->join("active_term at", "at.id=schools.active_term")
				->where("at.school_id", $this->session->get("soma_school_id"))
				->get()->getRowArray();
		$data['subtitle'] = lang("app.disciplineRecordEntry");
		$data['page'] = "Discipline Record Entry";
		$data['content'] = view("pages/discipline_record_entry", $data);
		return view('main', $data);
	}

	public function multiple_extra_fees_records(): string
	{
		$this->_preset();
		$data = $this->data;
		$classMdl = new ClassesModel();
		$SchoolModel = new SchoolModel();
		$data['title'] = 'Extra fees records';
		$data['classes'] = $classMdl->select("classes.id,classes.title,d.title as department_name,d.code,l.title as level_name
		,f.type,f.abbrev as faculty_code,concat(s.fname,' ',s.lname) as mentor_name,s.id as idstf")
				->join("departments d", "d.id=classes.department")
				->join("levels l", "l.id=classes.level")
				->join("faculty f", "f.id=d.faculty_id")
				->join("staffs s", "s.id=classes.mentor", "LEFT")
				->where("classes.school_id", $this->session->get("soma_school_id"))
				->get()->getResultArray();
		$data['activeTerm'] = $SchoolModel->select("at.term,at.id")
				->join("active_term at", "at.id=schools.active_term")
				->where("at.school_id", $this->session->get("soma_school_id"))
				->get()->getRowArray();
		$data['subtitle'] = lang("app.createFee");
		$data['page'] = "multiple_extra_fees_records";
		$data['content'] = view("pages/multiple_extra_fees_records", $data);
		return view('main', $data);
	}

	public function get_student_json($id, $isClass = null): Response
	{
		$this->_preset();
		$StudentModel = new StudentModel();
		$key = $isClass == null ? "students.id" : "c.id";
		$students = $StudentModel->get_student($id, $key, "students.id,students.regno,students.photo
		,concat(students.fname,' ',students.lname) as names,c.title,d.code,l.title as level_name", $isClass == null);
		if ($students != null) {
			return $this->response->setJSON(['success' => 1, 'student' => $students]);
		}
		return $this->response->setJSON(['message' => 'No student found']);
	}

	public function get_student_json2($regno)
{
    $StudentModel = new StudentModel();
    $data = $this->data;

    $student = $StudentModel->select("
            students.id,
            students.regno,
            students.photo,
            sk.id as school_id,
            concat(students.fname,' ',students.lname) as stdnames,
            if(students.studying_mode=0,'Boarding','Day') as studying_mode,
            students.sex,
            ft_phone,
            c.id as class_id,
            c.title,
            d.title as department_name,
            d.code,
            l.title as level_name,
            f.type,
            f.abbrev as faculty_code,
            c.level,
            (select coalesce(sum(ds.marks),0) 
                from disciplines ds
                where students.id=ds.student_id 
                AND ds.active_term = sk.active_term) as total_marks,
            sk.discipline_max,
            sk.name as school,
            sk.phone as school_phone
        ")
        ->join('class_records cr', 'cr.student = students.id')
        ->join('classes c', 'c.id = cr.class')
        ->join('departments d', 'd.id = c.department')
        ->join('levels l', 'l.id = c.level')
        ->join('faculty f', 'f.id = d.faculty_id')
        ->join('schools sk', 'sk.id = students.school_id')
        ->where('students.status', '1')
        ->where('students.regno', $regno)
        ->orderBy('cr.year', 'DESC')   // ✅ Get the most recent year first
        ->limit(1)                     // ✅ Only the latest class record
        ->get()
        ->getRowArray();

    if ($student == null) {
        return $this->response->setStatusCode(404)
                              ->setJSON(["message" => 'No student found']);
    }

    // Load academic years for that school
    $aMdl = new AcademicYearModel();
    $student['success'] = 1;
    $student['academic_years'] = $aMdl->select('id,title')
        ->where('school_id', $student['school_id'])
        ->orderBy('id', 'DESC')
        ->get()
        ->getResultArray();

    return $this->response->setJSON($student);
}


	public function student_marks_json($student_id, $class_id, $year, $term = null, $school = 0)
	{
//		if($school ==1 && in_array($term,[3,4])){
//			return $this->response->setStatusCode(400)->setJSON(['error'=>1,'message'=>"This marks are not yet published, contact school admin"]);
//		}
		$tot = 0;
		$records = array();
		$fac = null;
		$records['type'] = 0;
		$times = 1;
		if ($term == 4) {
			$times = 3;
		}

		foreach ($this->get_courses($class_id, $term, $year) as $core) {
			$marks = $this->__result($core['id'], $student_id, $term, $year);
			if ($term != 4) {
				$marks = ['marks' => $marks['cat'][$term] ?? null
					, 'exam_marks' => $marks['exam'][$term] ?? null
				];
			} else {
				$tot1 = 0;
				$tot2 = 0;
				$tot3 = 0;
				if (in_array('1', explode(',', $core['term1']))) {
					$tot1 += $result['cat'][1] ?? null;
					$tot1 += $result['exam'][1] ?? null;
				}
				if (in_array('2', explode(',', $core['term1']))) {
					$tot2 += $result['cat'][2] ?? null;
					$tot2 += $result['exam'][2] ?? null;
				}
				if (in_array('3', explode(',', $core['term1']))) {
					$tot3 += $result['cat'][3] ?? null;
					$tot3 += $result['exam'][3] ?? null;
				}
//				if (isset($marks['cat'][1])) {
//					$cM += $marks['cat'][1] ?? null;
//					$exM += $marks['exam'][1] ?? null;
//				}
//				if (isset($marks['cat'][2])) {
//					$cM += $marks['cat'][2] ?? null;
//					$exM += $marks['exam'][2] ?? null;
//				}
//				if (isset($marks['cat'][3])) {
//					$cM += $marks['cat'][3] ?? null;
//					$exM += $marks['exam'][3] ?? null;
//				}
				$marks = ['marks' => $tot1 ?? null
					, 'exam_marks' => $tot2 ?? null
				];
			}
			$core['marks'] = $core['marks'] * $times;
			$isCourseValid = true;
			$core['result']['marks'] = (float)$marks['marks'];
			$core['result']['exam_marks'] = (float)$marks['exam_marks'];
			$tot += (float)$marks['marks'] + (float)$marks['exam_marks'];
			if ($isCourseValid) {
				$records['marks'][] = $core;
//				$courseCount++;
			}
		}
		$records['total'] = $tot;
		$records['success'] = 1;
		return $this->response->setJSON($records);
	}
// In your Admin controller (or Home if you prefer)
// In App\Controllers\Home.php

public function getApplicationDocs($id = null)
{
    // 1) Validate id
    $id = is_numeric($id) ? (int)$id : 0;
    if ($id <= 0) {
        return $this->response
            ->setStatusCode(400)
            ->setJSON(['success' => false, 'error' => 'Missing or invalid id']);
    }

    $mdl = new \App\Models\StudentApplicationModel();
    $row = $mdl->select('applications.id, applications.report1, applications.report2, applications.report3, applications.documents,
                applications.faculty_id, applications.level, applications.schoolId,
                f.title as faculty_title, f.type as faculty_type, l.title as level_title')
               ->join('faculty f', 'f.id = applications.faculty_id', 'left')
               ->join('levels l', 'l.id = applications.level', 'left')
               ->where('applications.id', $id)
               ->get(1)
               ->getRowArray();

    if (!$row) {
        return $this->response
            ->setStatusCode(404)
            ->setJSON(['success' => false, 'error' => 'Application not found']);
    }

    $clean = static function ($v) {
        $v = is_string($v) ? trim($v) : $v;
        return ($v === '' || $v === null) ? null : $v;
    };

    $paths = [
        'report1'   => $clean($row['report1']   ?? null),
        'report2'   => $clean($row['report2']   ?? null),
        'report3'   => $clean($row['report3']   ?? null),
        'documents' => $clean($row['documents'] ?? null),
    ];

    if ((int) ($this->request->getGet('abs') ?? 0) === 1) {
        helper('url');
        $abs = static function ($rel) {
            if (!$rel) {
                return null;
            }
            $rel = ltrim($rel, '/');
            return rtrim(base_url('/'), '/') . '/' . $rel;
        };
        foreach ($paths as $k => $v) {
            $paths[$k] = $abs($v);
        }
    }

    // Labels match online registration form (by faculty + level)
    $pack = $this->resolveApplicationDocRequirements(
        (int) ($row['faculty_type'] ?? 1),
        (string) ($row['faculty_title'] ?? ''),
        (string) ($row['level_title'] ?? '')
    );
    $items = [];
    $seen = [];
    foreach ($pack['docs'] as $doc) {
        $field = $doc['field'];
        $seen[$field] = true;
        $items[] = [
            'field'    => $field,
            'label'    => $doc['label'],
            'required' => !empty($doc['required']),
            'path'     => $paths[$field] ?? null,
        ];
    }
    // Show any leftover uploaded slots with friendly names
    $fallbackLabels = [
        'report1'   => 'Previous academic report',
        'report2'   => 'Supporting certificate / exam slip',
        'report3'   => 'Additional document',
        'documents' => 'Payment proof',
    ];
    foreach ($fallbackLabels as $field => $label) {
        if (!empty($seen[$field])) {
            continue;
        }
        if (empty($paths[$field])) {
            continue;
        }
        // Prefer "Payment proof" when path looks like a payment proof upload
        if ($field === 'documents') {
            $label = 'Payment proof';
        } elseif ($field === 'report3' && is_string($paths[$field]) && strpos($paths[$field], 'payment_proof') !== false) {
            $label = 'Payment proof';
        }
        $items[] = [
            'field'    => $field,
            'label'    => $label,
            'required' => false,
            'path'     => $paths[$field],
        ];
    }

    // Always surface payment proof even if empty slot was in required pack (should not happen)
    if (!empty($paths['documents'])) {
        $hasProof = false;
        foreach ($items as $it) {
            if (($it['field'] ?? '') === 'documents') {
                $hasProof = true;
                break;
            }
        }
        if (!$hasProof) {
            $items[] = [
                'field'    => 'documents',
                'label'    => 'Payment proof',
                'required' => false,
                'path'     => $paths['documents'],
            ];
        }
    }

    return $this->response->setJSON([
        'success' => true,
        'hint'    => $pack['hint'] ?? '',
        'faculty' => $row['faculty_title'] ?? null,
        'level'   => $row['level_title'] ?? null,
        'data'    => [
            'report1'   => $paths['report1'],
            'report2'   => $paths['report2'],
            'report3'   => $paths['report3'],
            'documents' => $paths['documents'],
            'items'     => $items,
        ],
    ]);
}

	public function get_student($id, $isClass = 0, $type = 0, $academicYear = 0)
	{
		//$type tuzajya twongeraho rimwe uko tugiye kuyikoresha duhereye kuyiheruka
		$this->_preset();
		$StudentModel = new StudentModel();
		$AddressModel = new AddressModel();
//		var_dump($_SESSION['soma_academics_year']); die();
		$data = $this->data;
		$key = $isClass == 0 ? "students.id" : "c.id";
		$students = $StudentModel->get_student($id, $key, null, false, $academicYear);
		if (count($students) < 1) {
			echo "<center><h3>" . lang("app.sorryNoStudents") . "</h3></center><script>$(function() {
		  $('#class_text').text('');
		});</script>";
		}
		$i = 1;
		foreach ($students as $student) {
			$remaining = ($student['discipline_max'] - $student['total_marks']);
			$phone = strlen(trim($student["ft_phone"])) > 4 ? $student["ft_phone"] : (strlen(trim($student["mt_phone"])) > 4 ? $student["mt_phone"] :
					(strlen(trim($student["gd_phone"])) > 4 ? $student["gd_phone"] : ""));
			if ($type == 1) {
				//permission
				$color = false ? "color:orangered" : "";
				echo "<tr class='disc_row' id=" . $student['regno'] . " style='$color'>
				<td>" . $student['regno'] . "</td>
				<td>" . $student['stdnames'] . "<input type='hidden' value=" . $student['id'] . " name='discId[]'></td>
				<td>" . $student['level_name'] . " " . $student['title'] . " " . $student['code'] . " </td>
				<td style='text-align: center;'>
				<span class='btn-sm btn-danger' id='removerow'>" . lang("app.remove") . "</span></td>
				</tr>";
			} else if ($type == 3) {
				//sms
				$chk_val = strlen($phone) == 0 ? "disabled" : "checked";
				$color = strlen($phone) == 0 ? "color:orangered" : "";
				echo "<tr class='disc_row' id=" . $student['regno'] . $type . " style='$color'>
				<td><input type='checkbox' $chk_val class='chk_item' value='" . $student['id'] . "' name='studentId[]'></td>
				<td>" . $student['regno'] . "</td>
				<td>" . $student['stdnames'] . "</td>
				<td>" . $student['level_name'] . " " . $student['title'] . " " . $student['code'] . " </td>
				<td>" . $phone . "</td>
				<td style='text-align: center;'>
				<span class='btn-sm btn-danger' id='removerow'>" . lang("app.remove") . "</span></td>
				</tr>";
			} else if ($type == 2) {
				//student card preview — no photo fallback; only printable students get stId[]
				$resolved = resolve_profile_photo($student['photo'] ?? '');
				$hasPhoto = $resolved !== null;
				// Heal truncated DB values when we can match the file on disk.
				if ($hasPhoto && trim((string)$student['photo']) !== $resolved) {
					try {
						$StudentModel->update((int)$student['id'], ['photo' => $resolved]);
					} catch (\Throwable $e) {
						// non-fatal; preview still works via resolve
					}
				}
				if ($hasPhoto) {
					$photoUrl = profile_photo_url($resolved);
					$photoHtml = "<img src='" . esc($photoUrl, 'attr') . "' alt='' style='width:60px;height:60px;object-fit:cover;border-radius:4px;' />"
						. "<input type='hidden' value='" . (int)$student['id'] . "' name='stId[]'>";
				} else {
					$photoHtml = "<span style='display:inline-block;width:60px;height:60px;background:#f1f3f5;border-radius:4px;' title='No photo'></span>";
				}
				$color = $hasPhoto ? "" : "color:orangered";
				echo "<tr class='disc_row' style='$color' id='" . esc($student['regno'] . $type, 'attr') . "'>
				<td>" . esc($student['regno']) . "</td>
				<td>" . esc($student['stdnames']) . "</td>
				<td>" . esc($student['level_name'] . " " . $student['title'] . " " . $student['code']) . " </td>
				<td>" . $photoHtml . "</td>
				<td style='text-align: center;'>
				<span class='btn-sm btn-danger' id='removerow'>" . lang("app.remove") . "</span></td>
				</tr>";
			} else if ($type == 4) {
				//Marks Entry
				echo "
				<tr>
				<td>" . $student['regno'] . "</td>
				<td>" . $student['stdnames'] . "<input type='hidden' value=" . $student['id'] . " name='discId[]'></td>
				<td><input type='text'  name='marks[]' class='form-control' value=" . $student['cat_marks'] . " required  data-parsley-lt=\"#outofmarks\" data-parsley-lt-message=\"" . lang("app.shouldBeLess") . "\"></td>
				</tr>
				";
			} else if ($type == 5) {
				//discipline Record

				$class = $student['level_name'] . " " . $student['title'] . " " . $student['code'];
				$province = $AddressModel->getOneProvince($data['province']);
//				print_r($province);die();
				$color = $remaining < ($student['discipline_max'] / 2) ? "color:orangered" : "";
				echo "<tr class='disc_row' id=" . $student['regno'] . $type . " style='$color'>
				<td>" . $i . "</td>
				<td>" . $student['regno'] . "</td>
				<td>" . $student['stdnames'] . "</td>
				<td>" . $remaining . "<input type='hidden' value=" . $student['id'] . " name='discId[]'></td>
				</tr>
				<script>
					$(function(){
					$(\"#class_text\").text('$class');
					$(\"#province\").text('$province');
					});
				</script>
				";
			} else if ($type == 6) {
				//discipline Record
				$class = $student['level_name'] . " " . $student['title'] . " " . $student['code'];
				$province = $AddressModel->getOneProvince($data['province']);
//				print_r($province);die();
				echo "<tr class='disc_row' id=" . $student['regno'] . $type . " >
				<td>" . $i . "</td>
				<td>" . $student['regno'] . "</td>
				<td>" . $student['stdnames'] . "</td>
				<td>
				<a href='student_report' style='color: white;' class='btn btn-success btn-sm viewreport' data-id=" . $student['id'] . ">" . lang("app.viewReport") . "</a>
				<a style='color: white;' class='btn btn-success btn-sm viewreport' data-id=" . $student['id'] . "><i class='fa fa-file-pdf'></i>" . lang("app.export") . "</a>
				</td>
				</tr>
				<script>
					$(function(){
					$(\"#class_text\").text('$class');
					$(\"#province\").text('$province');
					});
				</script>
				";

			} else if ($type == 7) {
				echo "
				<option selected disabled></option>
				<option value=" . $student['id'] . ">" . $student['regno'] . " " . $student['stdnames'] . "</option>";
			} else if ($type == 8) {
				//Deliberation
				echo "<tr class='disc_row' id=" . $student['regno'] . ">
				<td>" . $i . "</td>
				<td>" . $student['regno'] . "</td>
				<td>" . $student['stdnames'] . "<input type='hidden' value=" . $student['id'] . " name='studentId[]'></td>
				<td>" . $student['level_name'] . " " . $student['title'] . " " . $student['code'] . " </td>
				<td style='text-align: center;'>
				<span class='btn-sm btn-danger' id='removerow'>" . lang("app.remove") . "</span></td>
				</tr>";
			} else if ($type == 9) {
				$color = $remaining < ($student['discipline_max'] / 2) ? "color:orangered" : "";
				echo "<tr class='disc_row' id=" . $student['regno'] . $type . " style='$color'>
				<td>" . $student['regno'] . "</td>
				<td>" . $student['stdnames'] . "</td>
				<td>" . $student['level_name'] . " " . $student['title'] . " " . $student['code'] . " </td>
				<td>" . $remaining . "<input type='hidden' value=" . $student['id'] . " name='discId[]'></td>
				<td><input type='number' placeholder='Mark' style='min-width:100px' class='form-control' name='marks[]'></td>
				<td style='text-align: center;'>
				<span class='btn-sm btn-danger' id='removerow'>" . lang("app.remove") . "</span></td>
				</tr>
				";
			} else if ($type == 10) {
				//multiple extra
				$color = false ? "color:orangered" : "";
				echo "<tr class='disc_row' id=" . $student['regno'] . " style='$color'>
				<td>" . $student['regno'] . "</td>
				<td>" . $student['stdnames'] . "<input type='hidden' value=" . $student['id'] . " name='discId[]'></td>
				<td>" . $student['level_name'] . " " . $student['title'] . " " . $student['code'] . " </td>
				<td><input type='number' required name='amounts[]' class='txt-fees-inputs'> </td>
				<td style='text-align: center;'>
				<span class='btn-sm btn-danger' id='removerow'>" . lang("app.remove") . "</span></td>
				</tr>";
			} else {
				//discipline
				$color = $remaining < ($student['discipline_max'] / 2) ? "color:orangered" : "";
				echo "<tr class='disc_row' id=" . $student['regno'] . $type . " style='$color'>
				<td>" . $student['regno'] . "</td>
				<td>" . $student['stdnames'] . "</td>
				<td>" . $student['level_name'] . " " . $student['title'] . " " . $student['code'] . " </td>
				<td>" . $remaining . "<input type='hidden' value=" . $student['id'] . " name='discId[]'></td>
				<td style='text-align: center;'>
				<span class='btn-sm btn-danger' id='removerow'>" . lang("app.remove") . "</span></td>
				</tr>
				";
			}
			$i++;
		}
	}

	public function get_staffs($id, $isPost = 0, $type = 0)
	{
		//$type tuzajya twongeraho rimwe uko tugiye kuyikore duhereye kuyiheruka
		$this->_preset();
		$StaffModel = new StaffModel();
		$data = $this->data;
		$key = $isPost == 0 ? "staffs.id" : "p.id";
		$staffs = $StaffModel->get_staff($key . '=' . $id);
		if (count($staffs) < 1) {
			echo "<center><h3>" . lang("app.noStaffsFound") . "</h3></center><script>$(function() {
		  $('#class_text').text('');
		});</script>";
		}
		$i = 1;
		foreach ($staffs as $staff) {
			if ($type == 1) {
				//staff card preview
				$resolved = resolve_profile_photo($staff['photo'] ?? '');
				$hasPhoto = $resolved !== null;
				if ($hasPhoto && trim((string)$staff['photo']) !== $resolved) {
					try {
						$StaffModel->update((int)$staff['id'], ['photo' => $resolved]);
					} catch (\Throwable $e) {
					}
				}
				$fallback = profile_photo_url(null);
				$photoUrl = $hasPhoto ? profile_photo_url($resolved) : $fallback;
				$photo = "<img src='" . esc($photoUrl, 'attr') . "' alt='' style='width:60px;height:60px;object-fit:cover;border-radius:4px;' onerror=\"this.onerror=null;this.src='" . esc($fallback, 'attr') . "';\" />"
					. "<input type='hidden' value='" . (int)$staff['id'] . "' name='stId[]'>";
				$color = $hasPhoto ? "" : "color:orangered";
				echo "<tr class='disc_row' style='$color' id='row" . (int)$staff['id'] . "'>
				<td>" . (int)$staff['id'] . "</td>
				<td>" . esc($staff['fname'] . ' ' . $staff['lname']) . "</td>
				<td>" . esc($staff['post_title']) . " </td>
				<td>" . $photo . "</td>
				<td style='text-align: center;'>
				<span class='btn-sm btn-danger' id='removerow'>" . lang("app.remove") . "</span></td>
				</tr>";
			}
			$i++;
		}

	}

	public function get_staff($id)
	{
		$this->_preset();
		$stMdl = new StaffModel();
		$data = $this->data;
		$staffs = $stMdl->get_staff(array("staffs.id" => $id));
		if (count($staffs) < 1) {
			echo "<center><h3>" . lang("app.sorryNoStudents") . "</h3></center><script>$(function() {
		  $('#class_text').text('');
		});</script>";
		}
		$i = 1;
		foreach ($staffs as $staff) {
			$phone = $staff["phone"];
			$chk_val = strlen($phone) == 0 ? "disabled" : "checked";
			$color = strlen($phone) == 0 ? "color:orangered" : "";
			echo "<tr class='disc_row' id=" . $staff['id'] . " style='$color'>
				<td><input type='checkbox' $chk_val class='chk_item' value='" . $staff['id'] . "' name='staffId[]'></td>
				<td>" . $staff['id'] . "</td>
				<td>" . $staff['fname'] . ' ' . $staff['lname'] . "</td>
				<td>" . $staff['post_title'] . " </td>
				<td>" . $phone . "</td>
				<td style='text-align: center;'>
				<span class='btn-sm btn-danger' id='removerow'>" . lang("app.remove") . "</span></td>
				</tr>";
			$i++;
		}

	}

	public function search_student()
	{
		$this->_preset();
		$key = $this->request->getPost('searchTerm');
		$StudentModel = new StudentModel();
		$students = $StudentModel->search_student($key);
		echo json_encode($students);
	}

	public function search_staff()
	{
		$this->_preset();
		$key = $this->request->getPost('searchTerm');
		$stMdl = new StaffModel();
		$staffs = $stMdl->search_staff($key);
		echo json_encode($staffs);
	}

	private
	function _generate_regno($save = false)
	{
		$regMdl = new RegnumberModel();
		$student_id = 1;
		$year_code = date('y');
		$school_id = $this->session->get('soma_school_id');
		$reg_dt = $regMdl->select("id,next_number")
				->where("academic_year", $year_code)
				->where("school_id", $school_id)->get()->getRow();
		if ($reg_dt == null) {
			//new record start from one
			if ($save) {
				$regMdl->save(array('academic_year' => $year_code, 'school_id' => $school_id, 'next_number' => 2));
			}
		} else {
			//increment
			$student_id = $reg_dt->next_number;
			//new record start from one
			if ($save) {
				$regMdl->save(array('id' => $reg_dt->id, 'next_number' => ($student_id + 1)));
			}
		}
		return $year_code . sprintf('%03d', $school_id) . sprintf('%04d', $student_id);
	}

	private
	function _save_sms($term_id, $phone, $msg, $subject = "", $receiver_id = 0, $type = 0, $smsCount = 1, $fail = "")
	{
		$smsMdl = new SmsModel();
		$termMdl = new TermModel();
		$school_id = $this->session->get("soma_school_id");
		$termMdl->incrementSMS($term_id, $smsCount);
		$id = $smsMdl->insert(array("school_id" => $school_id, "active_term" => $term_id,
				"content" => $msg, "subject" => $subject, "recipient_type" => $type));
		$status = strlen($fail) > 3 ? 2 : 1;
		$smsRMdl = new SmsRecipientModel();
		$smsRMdl->save(array("sms_record_id" => $id, "receiver_id" => $receiver_id,
				"phone" => $phone, "sent_on" => time(), "status" => $status, "fail_reason" => $fail));
	}

	public
	function manipulate_discipline_entry($completed = 0)
	{
		$this->_preset();
		set_time_limit(0);
		ini_set("memory_limit", -1);
		ini_set("max_execution_time", -1);
		$DisciplineModel = new DisciplineModel();
		$school_id = $this->session->get("soma_school_id");
		$notify = $this->request->getPost("sms") == null ? 0 : $this->request->getPost("sms");
		$marks = $this->request->getPost("reduce_marks");
		$comment = $this->request->getPost("reason");
		$types = $this->request->getPost("discipline_type");
		$active = $this->request->getPost("active_term");
		$schoo = $school_id;
		$created_by = $this->session->get("soma_id");
		$formids = $this->request->getPost("discId[]");
		if ($types == 0) {
			//behavior, force remove marks and notify
			$notify = 0;
			$marks = 0;
		}
		if (!is_array($formids)) {
			//no student selected
			return $this->response->setJSON(array("error" => lang("app.pleaseAddErr")));
		}
		$isSMSError = false;
		foreach ($formids as $formid) {
			$a = $formid;
			$data = array(
					"student_id" => $a,
					"school_id" => $schoo,
					"type" => $types,
					"comment" => $comment,
					"marks" => $marks,
					"active_term" => $active,
					"notify_parent" => $notify,
					"created_by" => $created_by);
			try {
				$DisciplineModel->save($data);
				if ($notify == 1) {
					//send sms
					$st_data = $this->_get_parent_phone($formid);
					$phone = $st_data['phone'];
					if (strlen($phone) > 3) {
						$msg = $this->get_discipline_msg($st_data['name'], $marks, $comment);
						if ($this->sendSMS($phone, $msg, $result)) {
							//save sent sms
							$sms_count = (int)ceil(strlen($msg) / PER_SMS);
							$this->data['remaining_sms'] = $this->data['remaining_sms'] - $sms_count;//prevent exceeding sms limit
							$this->_save_sms($active, $phone, $msg, "Discipline", $a, 0, $sms_count);
						} else {
							$isSMSError = true;
							$this->_save_sms($active, $phone, $msg, "Discipline", $a, 0, 0, $result);
						}
					}
				}
			} catch (\Exception $e) {
				//kugerageza kwerekana abanyeshuri byakunze tukabakura kuri list
				return $this->response->setJSON(array("error" => lang("app.OopsAction")));
			}
		}
		$ms = "";
		if ($isSMSError)
			$ms = lang("app.notSent");
		return $this->response->setJSON(array("success" => lang("app.disciplineSuccessfully") . $ms));
	}

	public
	function manipulate_multiple_fees($completed = 0)
	{
		$this->_preset();
		set_time_limit(0);
		ini_set("memory_limit", -1);
		ini_set("max_execution_time", -1);
		$mdl = new ExtraFeesModel();
		$school_id = $this->session->get("soma_school_id");
		$amount = $this->request->getPost("amount");
		$amounts = $this->request->getPost("amounts[]");
		$title = $this->request->getPost("title");
		$created_by = $this->session->get("soma_id");
		$term = $this->request->getPost("term");
		$formids = $this->request->getPost("discId[]");

		if (!is_array($formids)) {
			//no student selected
			return $this->response->setJSON(["error" => lang("app.pleaseAddErr")]);
		}
		if (!is_array($amounts)) {
			//no amount available
			return $this->response->setJSON(["error" => "No amount available"]);
		}

		if (!$this->verify_password(true)) {
			return $this->response->setJSON(["error" => 'Invalid password, please try again']);
		}
		$aa = 0;
		foreach ($formids as $formid) {
			$data = [
					"type_id" => $formid,
					"type" => 1,
					"title" => $title,
					"school_id" => $school_id,
					"term" => $term,
					"amount" => $amounts[$aa],
					"academic_year" => $this->data['academic_year'],
					"created_by" => $created_by
			];
			$aa++;
			try {
				$mdl->save($data);
			} catch (\Exception $e) {
				//kugerageza kwerekana abanyeshuri byakunze tukabakura kuri list
				return $this->response->setJSON(array("error" => lang("app.OopsAction")));
			}
		}
		return $this->response->setJSON(array("success" => 'Multiple extra fees created'));
	}

	public
	function download_student_template()
	{
		$this->_preset();
		$rename = $this->request->getPost("class_id_name");
		$ex_class_id = explode("-", $rename);
		$id = $ex_class_id[0];
		$class = str_replace(" ", "_", $ex_class_id[1]);
		$inputFileName = ("assets/templates/Students.xlsx");
		header("Content-Type:   application/vnd.ms-excel; charset=utf-8");
		header("Content-type:   application/x-msexcel; charset=utf-8");
		header("Content-Disposition: attachment; filename=abc.xsl");
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Cache-Control: private", false);
		header('Content-Disposition: attachment; filename=Student_' . $class . '_' . $id . '_lists.xlsx');
		echo file_get_contents($inputFileName);
	}

	public
	function export_student_list($class_id, $yearId)
	{
		$this->_preset();
		$id = $class_id;
		$classMdl = new ClassesModel();
		$classes = $classMdl->select('concat(l.title," ",d.code," ",classes.title) as classe,concat(s.fname," ",s.lname) as mentor_name')
				->join('departments d', 'd.id=classes.department')
				->join('levels l', 'l.id=classes.level')
				->join("staffs s", "s.id=classes.mentor", "LEFT")
				->where('classes.id', $class_id)
				->get()->getRow();
		if ($classes == null) {
			echo "Invalid class found";
			die();
		}
		$name = $classes->classe;
		$StudentModel = new StudentModel();
		$students = $StudentModel->select("students.id,
														  students.regno,
														  students.fname,students.lname,students.regno,students.sex,students.studying_mode,students.dob,students.nationality,students.religion")
				->join("class_records cr", "students.id=cr.student")
				->where("cr.class", $id)
				->where("cr.year", $yearId)
				->where("students.status", 1)
				->groupBy("students.id")
				->orderBy("students.regno", "ASC")
				->get()->getResultArray();
//		print_r($students);die();
		$class = str_replace(" ", "_", $name);
		$inputFileName = FCPATH . "assets/templates/students_export.xlsx";
//		echo $inputFileName;die();
		$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($inputFileName);
		$worksheet = $spreadsheet->getActiveSheet();
		$worksheet->getCell('B1')->setValue(lang("app.sClass") . ": " . $name);
		$worksheet->getCell('C1')->setValue(lang("app.mentor") . ": " . $classes->mentor_name);
		$worksheet->getCell('E1')->setValue(lang("app.academicYear") . ": " . $this->data['academic_year_title']);

		$worksheet->getCell('G1')->setValue($this->TermTostr($this->data['term']));
		$i = 6;
		foreach ($students as $student) {
			$dob = $student['dob'] == "0000-00-00" ? "" : $student['dob'];
			$worksheet->getCell('A' . $i)->setValue($student['id']);
			$worksheet->getCell('B' . $i)->setValue($student['fname']);
			$worksheet->getCell('C' . $i)->setValue($student['lname']);
			$worksheet->getCell('D' . $i)->setValue($student['regno']);
			$worksheet->getCell('E' . $i)->setValue($student['sex']);
			$worksheet->getCell('F' . $i)->setValue($this->ModeToStr($student['studying_mode']));
			$worksheet->getCell('G' . $i)->setValue($dob);
			$worksheet->getCell('H' . $i)->setValue($student['nationality']);
			$worksheet->getCell('I' . $i)->setValue($student['religion']);
			$i++;
		}
		$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xls');
		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment; filename=students_' . $class . '_' . $id . '_lists.xls');
		$writer->save("php://output");

	}

	public
	function down_student_marks_template()
	{
		$this->_preset();
		$academic_year = $this->request->getPost("year");
		$id = $this->request->getPost("check_class");
		$name = $this->request->getPost("check_class_name");
		$course_name = $this->request->getPost("course_name");
		$course_id = $this->request->getPost("course_id");
		$StudentModel = new StudentModel();
		$students = $StudentModel->select("students.id,students.regno,students.fname,students.lname,cs.marks")
				->join("class_records cr", "students.id=cr.student")
				->join("course_records r", "cr.class=r.class")
				->join("courses cs", "cs.id=r.course AND cs.id=$course_id")
				->where("students.status", 1)
				->where("r.class", $id)
				->where("cr.year", $academic_year)
				->groupBy("students.id")
				->orderBy("students.fname", "ASC")
				->orderBy("students.lname", "ASC")
				->get()->getResultArray();
		$class = str_replace(" ", "_", trim($name));
		$inputFileName = ("assets/templates/Students_marks.xlsx");
		$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($inputFileName);
		$worksheet = $spreadsheet->getActiveSheet();
		$worksheet->getCell('B1')->setValue(lang("app.sClass") . ": " . $name);
		$worksheet->getCell('C1')->setValue(lang("app.course") . ": " . $course_name);
		$worksheet->getCell('D1')->setValue(lang("app.academicYear") . ": " . $this->data['academic_year']);
		$worksheet->getCell('E1')->setValue($this->TermTostr($this->data['term']));
		$outof = $students[0]['marks'];
		$worksheet->getCell('D5')->setValue(lang("app.cat") . " /" . $outof);
		$worksheet->getCell('E5')->setValue(lang("app.exam") . " /" . $outof);
		$i = 6;
		foreach ($students as $student) {
			$worksheet->getCell('A' . $i)->setValue($student['id']);
			$worksheet->getCell('B' . $i)->setValue($student['fname']);
			$worksheet->getCell('C' . $i)->setValue($student['lname']);
			$i++;
		}
		$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xls');
		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment; filename=Students_' . $class . '_' . $course_id . '_' . $id . '_marks.xls');
		$writer->save("php://output");

	}

	public
	function uploadExcelMarks()
	{
		$url = $this->session->get("return_url");
		$this->_preset();
		set_time_limit(0);
		ini_set("memory_limit", -1);
		ini_set("max_execution_time", -1);
		$marks = new MarksModel();
		$file_mimes = array('application/octet-stream', 'application/vnd.ms-excel', 'application/x-csv', 'text/x-csv', 'text/csv', 'application/csv', 'application/excel', 'application/vnd.msexcel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		if (isset($_FILES['documents']['name']) && in_array($_FILES['documents']['type'], $file_mimes)) {
			$name = $_FILES['documents']['name'];
			$chunks = explode("_", $name);
			$file_class = explode(".", $chunks[count($chunks) - 2])[0];
			$file_course = explode(".", $chunks[count($chunks) - 3])[0];
			$post_class = $this->request->getPost("check_class");
			$post_course = $this->request->getPost("course_id");
			$post_marks = $this->request->getPost("course_marks");
			$term = $this->request->getPost("term");
			$year = $this->request->getPost("year");
			$atMdl = new ActiveTermModel();
			$school_id = $this->session->get("soma_school_id");
			$active_term = $atMdl->select("id")->where("term", $term)
					->where("academic_year", $year)->where("school_id", $school_id)
					->get(1)->getRow();
			if ($active_term == null) {
				return $this->response->setJSON(array("error" => "invalid term data, please try again later"));
			}
			if ($file_class != $post_class) {
				return $this->response->setJSON(array("error" => lang("app.pleaseUpload")));
			} else if ($file_course != $post_course) {
				return $this->response->setJSON(array("error" => lang("app.pleaseUploadcourse")));
			} else {
				$arr_file = explode('.', $_FILES['documents']['name']);
				$extension = end($arr_file);
				if ('csv' == $extension) {
					$reader = new \PhpOffice\PhpSpreadsheet\Reader\Csv();
				} else {
					return $this->response->setJSON(array("error" => "Please convert excel file to CSV for quick upload"));
//					$reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
				}
				$spreadsheet = $reader->load($_FILES['documents']['tmp_name']);
				$sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
				//print_r($sheetData);die();
				$i = 0;
				$empty = 0;
				// echo "upload done";die();
				$cat_max = $post_marks / 2;
				$exam_max = $post_marks / 2;
				$isVerified = false;
				foreach ($sheetData as $sheet) {
					if ($i == 0) {
						$i++;
						continue;
					}
					if (empty($sheet['A'])) {
						$empty++;
						if ($empty > 3) {
							break;
						}
						continue;
					}
					if ($i == 1) {
						$cat_max = explode("/", str_replace(" ", "", $sheet['D']))[1];
						$exam_max = explode("/", str_replace(" ", "", $sheet['E']))[1];
						$i++;
						continue;
					}
					$empty = 0;
					$i++;
					if (!$isVerified) {
						//check if same data exists
						$dtt = $marks->select("id")->where("student_id", $this->_sanitize_txt($sheet['A']))->where("term", $this->data['active_term'])
								->where("course_id", $post_course)->where("class_id", $post_class)->where("mark_type", 2)->get()->getRow();
						if ($dtt != null) {
							return $this->response->setJSON(array("error" => lang("app.marksExists")));
						}
						$isVerified = true;
					}
					if (!empty($sheet['E'])) {
						$data1 = array(
								"student_id" => $this->_sanitize_txt($sheet['A']),
								"term" => $active_term->id,
								"examDate" => time(),
								"course_id" => $post_course,
								"class_id" => $post_class,
								"mark_type" => 1,
								"marks" => $this->_sanitize_txt($sheet['D']),
								"outof" => $cat_max,
								"created_by" => $this->session->get("soma_id"),
						);
						$query1 = $marks->save($data1);
					}
					if (!empty($sheet['E'])) {
						$data2 = array(
								"student_id" => $this->_sanitize_txt($sheet['A']),
								"term" => $active_term->id,
								"examDate" => time(),
								"course_id" => $post_course,
								"class_id" => $post_class,
								"mark_type" => 2,
								"marks" => $this->_sanitize_txt($sheet['E']),
								"outof" => $exam_max,
								"created_by" => $this->session->get("soma_id"),
						);
						$query2 = $marks->save($data2);
					}
				}
				if (!$query1 && !$query2) {
					return $this->response->setJSON(array("error" => lang("app.recordnotSent")));
				} else {
					return $this->response->setJSON(array("success" => lang("app.successfullySent")));
				}
			}
		}
	}

	public
	function upload_student_template()
	{
		$this->_preset();
		set_time_limit(0);
		ini_set("memory_limit", -1);
		ini_set("max_execution_time", -1);
		$studentMdl = new StudentModel();
		$file_mimes = array('application/octet-stream', 'application/vnd.ms-excel', 'application/x-csv', 'text/x-csv', 'text/csv', 'application/csv', 'application/excel', 'application/vnd.msexcel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		if (isset($_FILES['documents']['name']) && in_array($_FILES['documents']['type'], $file_mimes)) {
			$name = $_FILES['documents']['name'];
			$chunks = explode("_", $name);
			$file_class = explode(".", $chunks[count($chunks) - 2])[0];
			$post_cl = explode("-", $this->request->getPost("check_class"), 2);
			$post_class = explode("-", $this->request->getPost("check_class"))[0];
			if ($file_class != $post_class) {
				$this->session->setFlashdata("error", lang("app.pleaseUpload"));
				return redirect()->to(base_url("students"));
			} else {
				$arr_file = explode('.', $_FILES['documents']['name']);
				$extension = end($arr_file);
				if ('csv' == $extension) {
					$reader = new \PhpOffice\PhpSpreadsheet\Reader\Csv();
				} else {
					$reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
				}
				$spreadsheet = $reader->load($_FILES['documents']['tmp_name']);
				$sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
				//print_r($sheetData);die();
				$i = 0;
				$empty = 0;
				// echo "upload done";die();
				foreach ($sheetData as $sheet) {
					if ($i == 0) {
						$i++;
						continue;
					}
//					echo $sheet['A']." ".$i;
					if (empty($sheet['A'])) {
						$empty++;
						if ($empty > 2) {
							break;
						}
						continue;
					}
					$empty = 0;
					$mode = strtolower($this->_sanitize_txt($sheet['F'])) == "day" ? 1 : 0;
					//if regno not available generate new
					$uvMdl = new UpdateVersionModel();
					$update_v = 1;
					$update_v_data = $uvMdl->select("version")->where("type", "student")->where("school_id", $this->session->get("soma_school_id"))->get(1)->getRow();
					if ($update_v_data != null)
						$update_v = $update_v_data->version;
					$regno = strlen($this->_sanitize_txt($sheet['C'])) > 2 ? $this->_sanitize_txt($sheet['C']) : $this->_generate_regno(true);
					$dt = array("school_id" => $this->session->get("soma_school_id"),
							"fname" => $this->_sanitize_txt($sheet['A']),
							"lname" => $this->_sanitize_txt($sheet['B']),
							"sex" => $this->_sanitize_txt($sheet['D']),
							"dob" => $this->_sanitize_txt($sheet['E']),
							"regno" => $regno,
							"studying_mode" => $mode,
							"nationality" => $this->_sanitize_txt($sheet['G']),
							"father" => $this->_sanitize_txt($sheet['H']),
							"ft_phone" => $this->_sanitize_txt($sheet['I']),
							"mother" => $this->_sanitize_txt($sheet['J']),
							"mt_phone" => $this->_sanitize_txt($sheet['K']),
							"guardian" => $this->_sanitize_txt($sheet['L']),
							"gd_phone" => $this->_sanitize_txt($sheet['M']),
							"religion" => $this->_sanitize_txt($sheet['N']),
							"created_by" => $this->session->get("soma_id"),
							"status" => 1,
							"updateVersion" => $update_v);
					$id = $studentMdl->insert($dt);
					//create class record
					$classRecordMdl = new ClassRecordModel();
					$classRecordMdl->save(array("student" => $id, "year" => $this->data['academic_year'], "class" => $post_class));
					$i++;
				}
				$this->session->setFlashdata("success", ($i - 1) . lang("app.studentsUploade") . str_replace("-", " ", $post_cl[1]));
				return redirect()->to(base_url("students"));
			}

		} else {
			$this->session->setFlashdata("error", lang("app.invalidFileUploaded"));
			return redirect()->to(base_url("students"));
		}
	}

	public function download_staff_template()
	{
		$this->_preset(1, 3);
		$posts = (new PostsModel())->orderBy('title', 'ASC')->findAll();
		$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
		$sheet->setTitle('Staff');
		$sheet->fromArray([
			['First Name', 'Last Name', 'Phone', 'Email', 'Privilege', 'Country', 'City', 'Address'],
			['John', 'Doe', '0788000000', 'john.doe@school.com', $posts[0]['title'] ?? 'Teacher', 'Rwanda', 'Kigali', ''],
		], null, 'A1');
		$this->_styleExcelTemplateHeader($sheet, 'A1:H1');
		$sheet->freezePane('A2');
		foreach (range('A', 'H') as $col) {
			$sheet->getColumnDimension($col)->setAutoSize(true);
		}

		$privSheet = $spreadsheet->createSheet();
		$privSheet->setTitle('Privileges');
		$privSheet->fromArray([['Privilege title', 'ID']], null, 'A1');
		$row = 2;
		foreach ($posts as $post) {
			$privSheet->setCellValue('A' . $row, $post['title']);
			$privSheet->setCellValue('B' . $row, $post['id']);
			$row++;
		}
		$this->_styleExcelTemplateHeader($privSheet, 'A1:B1');
		$privSheet->freezePane('A2');
		foreach (range('A', 'B') as $col) {
			$privSheet->getColumnDimension($col)->setAutoSize(true);
		}

		if (! empty($posts)) {
			$lastPrivRow = $row - 1;
			$listFormula = sprintf("='Privileges'!\$A\$2:\$A\$%d", $lastPrivRow);
			for ($dataRow = 2; $dataRow <= 500; $dataRow++) {
				$validation = new \PhpOffice\PhpSpreadsheet\Cell\DataValidation();
				$validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
				$validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
				$validation->setAllowBlank(false);
				$validation->setShowInputMessage(true);
				$validation->setShowErrorMessage(true);
				$validation->setShowDropDown(true);
				$validation->setErrorTitle('Invalid privilege');
				$validation->setError('Choose a privilege from the dropdown list.');
				$validation->setPromptTitle('Privilege');
				$validation->setPrompt('Select a post from all available privileges.');
				$validation->setFormula1($listFormula);
				$sheet->getCell('E' . $dataRow)->setDataValidation($validation);
			}
		}

		$spreadsheet->setActiveSheetIndex(0);

		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment;filename="Staff_upload_template.xlsx"');
		header('Cache-Control: max-age=0');
		$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
		$writer->save('php://output');
		exit;
	}

	private function _styleExcelTemplateHeader(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $range): void
	{
		$sheet->getStyle($range)->applyFromArray([
			'font' => [
				'bold' => true,
				'color' => ['rgb' => 'FFFFFF'],
			],
			'fill' => [
				'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
				'startColor' => ['rgb' => '012F6B'],
			],
			'alignment' => [
				'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
				'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
				'wrapText' => true,
			],
		]);
		$sheet->getRowDimension(1)->setRowHeight(24);
	}

	public function upload_staff_template()
	{
		$this->_preset(1, 3);
		set_time_limit(0);
		ini_set('memory_limit', '-1');
		ini_set('max_execution_time', '-1');

		if (! isset($_FILES['documents']['name']) || $_FILES['documents']['error'] !== UPLOAD_ERR_OK) {
			$this->session->setFlashdata('error', lang('app.invalidFileUploaded'));
			return redirect()->to(base_url('staffs'));
		}

		$arr_file = explode('.', $_FILES['documents']['name']);
		$extension = strtolower((string) end($arr_file));
		if (! in_array($extension, ['csv', 'xlsx', 'xls'], true)) {
			$this->session->setFlashdata('error', lang('app.invalidFileUploaded'));
			return redirect()->to(base_url('staffs'));
		}

		try {
			if ($extension === 'csv') {
				$reader = new \PhpOffice\PhpSpreadsheet\Reader\Csv();
			} elseif ($extension === 'xls') {
				$reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
			} else {
				$reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
			}
			$spreadsheet = $reader->load($_FILES['documents']['tmp_name']);
			$sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
		} catch (\Throwable $e) {
			$this->session->setFlashdata('error', 'Could not read file: ' . $e->getMessage());
			return redirect()->to(base_url('staffs'));
		}

		$postsMdl = new PostsModel();
		$postsByTitle = [];
		$postsById = [];
		foreach ($postsMdl->findAll() as $post) {
			$postsByTitle[strtolower(trim((string) $post['title']))] = (int) $post['id'];
			$postsById[(int) $post['id']] = (int) $post['id'];
		}

		$staffMdl = new StaffModel();
		$school_id = (int) $this->session->get('soma_school_id');
		$uvMdl = new UpdateVersionModel();
		$update_v = 1;
		$update_v_data = $uvMdl->select('version')->where('type', 'staff')->where('school_id', $school_id)->get(1)->getRow();
		if ($update_v_data != null) {
			$update_v = $update_v_data->version;
		}

		$imported = 0;
		$skipped = 0;
		$empty = 0;
		$i = 0;
		foreach ($sheetData as $sheet) {
			if ($i === 0) {
				$i++;
				continue;
			}
			$fname = $this->_sanitize_txt($sheet['A'] ?? '');
			if ($fname === '') {
				$empty++;
				if ($empty > 2) {
					break;
				}
				continue;
			}
			$empty = 0;
			$lname = $this->_sanitize_txt($sheet['B'] ?? '');
			$phone = $this->_sanitize_txt($sheet['C'] ?? '');
			$email = $this->_sanitize_txt($sheet['D'] ?? '');
			$privRaw = $this->_sanitize_txt($sheet['E'] ?? '');
			$country = $this->_sanitize_txt($sheet['F'] ?? '') ?: 'Rwanda';
			$city = $this->_sanitize_txt($sheet['G'] ?? '');
			$address = $this->_sanitize_txt($sheet['H'] ?? '');

			$postId = null;
			if (is_numeric($privRaw)) {
				$postId = $postsById[(int) $privRaw] ?? null;
			} else {
				$postId = $postsByTitle[strtolower($privRaw)] ?? null;
			}

			if ($postId === null || $email === '' || $lname === '') {
				$skipped++;
				$i++;
				continue;
			}

			try {
				$default_password = $this->random_password();
				$staffMdl->insert([
					'school_id' => $school_id,
					'fname' => $fname,
					'lname' => $lname,
					'phone' => $phone,
					'email' => $email,
					'password' => password_hash($default_password, PASSWORD_DEFAULT),
					'status' => 2,
					'post' => $postId,
					'shift_id' => 0,
					'country' => $country,
					'city' => $city,
					'address' => $address,
					'updateVersion' => $update_v,
				]);
				$imported++;
			} catch (\Exception $e) {
				if ((int) $e->getCode() === 1062) {
					$skipped++;
				} else {
					$skipped++;
				}
			}
			$i++;
		}

		$msg = $imported . ' staff imported successfully';
		if ($skipped > 0) {
			$msg .= ' (' . $skipped . ' skipped — duplicate email or invalid privilege)';
		}
		$this->session->setFlashdata('success', $msg);
		return redirect()->to(base_url('staffs'));
	}

	public
	function _sanitize_txt($txt)
	{
		return empty($txt) ? "" : trim($txt);
	}

	public
	function permission_entry()
	{
		$this->_preset();
		set_time_limit(0);
		ini_set("memory_limit", -1);
		ini_set("max_execution_time", -1);
		$data = $this->data;
		$faculty = new FacultyModel();
		$staffMdl = new StaffModel();
		$classMdl = new ClassesModel();
		$courseModel = new CourseModel();
		$CourseCategory = new CourseCategoryModel();
		$SchoolModel = new SchoolModel();
		$data['title'] = lang("app.permissionManagement");
		$data['classes'] = $classMdl->select("classes.id,classes.title,d.title as department_name,d.code,l.title as level_name
		,f.type,f.abbrev as faculty_code,concat(s.fname,' ',s.lname) as mentor_name,s.id as idstf")
				->join("departments d", "d.id=classes.department")
				->join("levels l", "l.id=classes.level")
				->join("faculty f", "f.id=d.faculty_id")
				->join("staffs s", "s.id=classes.mentor", "LEFT")
				->where("classes.school_id", $this->session->get("soma_school_id"))
				->get()->getResultArray();
		$data['courses'] = $courseModel->select("courses.id,courses.title,courses.code,courses.marks,courses.credit,cs.title as category")
				->join("course_category cs", "cs.id=courses.category")
				->where("courses.school_id", $this->session->get("soma_school_id"))
				->get()->getResultArray();
		$data['faculty'] = $faculty->get()->getResultArray();
		$data['categories'] = $CourseCategory->get()->getResultArray();
		$data['staffs'] = $staffMdl->where("post", 2)->get()->getResultArray();
		$data['activeTerm'] = $SchoolModel->select("at.term,at.id")
				->join("active_term at", "at.id=schools.active_term")
				->where("at.school_id", $this->session->get("soma_school_id"))
				->get()->getRowArray();
		$data['subtitle'] = lang("app.permissionManagement");
		$data['page'] = "permission_entry";
		$data['content'] = view("pages/permission_entry", $data);
		return view('main', $data);
	}

	public function manipulate_permissions()
{
    $this->_preset();
    $PermissionModel = new PermissionModel();
    $reatlime = null;
    $notify = $this->request->getPost("sms") == null ? 0 : $this->request->getPost("sms");
    $comment = $this->request->getPost("reason");
    $destination = $this->request->getPost("destination");
    $active = $this->request->getPost("active_term");
    $time = $this->request->getPost("datetimes");
    $created_by = $this->session->get("soma_id");
    $formids = $this->request->getPost("discId[]");
    $reatlime = explode("-", $time);
    $leave = strtotime($reatlime[0]);
    $returns = strtotime($reatlime[1]);

    if (!is_array($formids)) {
        return $this->response->setJSON(["error" => lang("app.pleaseAddErr")]);
    }

    $isSMSError = false;
    $lastInsertedId = null; // ✅ track last inserted permission id

    foreach ($formids as $formid) {
        $data = [
            "student_id" => $formid,
            "reason" => $comment,
            "destination" => $destination,
            "leave_time" => date('Y-m-d H:i:s', $leave),
            "return_time" => date('Y-m-d H:i:s', $returns),
            "active_term" => $active,
            "notify_parent" => $notify,
            "created_by" => $created_by
        ];

        try {
            // ✅ Save and capture insert ID
            $PermissionModel->save($data);
            $lastInsertedId = $PermissionModel->getInsertID();

            if ($notify == 1) {
                // send SMS
                $st_data = $this->_get_parent_phone($formid);
                $phone = $st_data['phone'];
                if (strlen($phone) > 3) {
                    $msg = $this->get_permisson_msg($st_data['name'], $destination, $comment);
                    if ($this->sendSMS($phone, $msg, $result)) {
                        //save sent sms
                        $sms_count = (int)ceil(strlen($msg) / PER_SMS);
                        $this->data['remaining_sms'] -= $sms_count; // prevent exceeding sms limit
                        $this->_save_sms($active, $phone, $msg, "Permission", $formid, 0, $sms_count);
                    } else {
                        $isSMSError = true;
                        $this->_save_sms($active, $phone, $msg, "Permission", $formid, 0, 0, $result);
                    }
                }
            }
        } catch (\Exception $e) {
            return $this->response->setJSON(["error" => $e->getMessage()]);
        }
    }

    $ms = $isSMSError ? lang("app.notSent") : "";

    // ✅ Return JSON with inserted ID for auto-print
    return $this->response->setJSON([
        "success" => lang("app.permissionSaved") . $ms,
        "permission_id" => $lastInsertedId
    ]);
}

	public
	function verify_forget($resetKey, $id, $return = false)
	{
		//verify if reset link is valid and not expired
		$data = null;
		$stMdl = new StaffModel();
		$data = $stMdl->select("id,fname,lname,reset_exp,email")->where("id", $id)->get()->getRowArray();
		if ($data == null) {
			if ($return)
				return lang("app.accountnotFound");
			$this->session->setFlashdata('error', lang("app.accountnotFound"));
			return redirect()->to(base_url("login"));
		}

		$resetExp = $data['reset_exp'];
		$resetTxt = $data['email'] . "" . $resetExp;
		$resetKey2 = md5($resetTxt);
		if ($resetKey == $resetKey2) {//verify if resetkey is valid
			if ($resetExp > time()) { //check expiration
				$this->session->setFlashdata('resetKey', $resetKey);
				$this->session->setFlashdata('id', $data['id']);
				$this->session->setFlashdata('email', $data['email']);
				if ($return)
					return true;
				return redirect()->to(base_url("forget/reset"));
			} else {
				if ($return)
					return lang("app.reseLinkExpired");
				$this->session->setFlashdata('error', lang("app.reseLinkExpired"));
				return redirect()->to(base_url("login"));
			}
		} else {
			if ($return)
				return lang("app.invalidResetLink") . $data['email'] . " | " . $id;
			$this->session->setFlashdata('error', lang("app.invalidResetLink"));
			return redirect()->to(base_url("login"));
		}
	}

	public
	function forget($type)
	{
		if ($type == "save") {
			//save new password to db
			$stMdl = new StaffModel();
			$this->data['title'] = lang("app.resetPassword");
			$rsKey = $this->request->getPost("resetKey");
			$id = $this->request->getPost("id");

			//verify if user does alter anything after checking;
			$res = $this->verify_forget($rsKey, $id, true);
			if ($res === true) {
				try {
					$db_pass = array("id" => $id, "password" => password_hash($this->request->getPost("password"), PASSWORD_DEFAULT), "reset_exp" => 0);//save password and reset resetExp to initial
					$stMdl->save($db_pass);
					$this->session->setFlashdata('success', lang("app.resetSuccess"));
					return redirect()->to(base_url("login"));
				} catch (\Exception $e) {
					$this->session->setFlashdata('error', lang("app.resetErr"));
					return redirect()->to(base_url("login"));
				}
			} else {
				$this->session->setFlashdata('error', "Error: " . $res);
				return redirect()->to(base_url("login"));

			}
		} elseif ($type == "reset") {
			$this->data['resetKey'] = $this->session->getFlashdata("resetKey");
			$this->data['email'] = $this->session->getFlashdata("email");
			$this->data['id'] = $this->session->getFlashdata("id");
			$this->data['page'] = "login";
			return view("pages/reset", $this->data);
		}
	}

	public
	function reset_password()
	{
		$email = $this->request->getPost("email");
		$stMdl = new StaffModel();
		$staff = $stMdl->select("id,fname,lname")->where("lower(email)", strtolower($email))->get()->getRow();
		if ($staff == null) {
			return $this->response->setJSON(array("error" => lang("app.mailNotFound")));
		}
		$resetExp = time() + 600; //10 min after now
		$resetTxt = $email . "" . $resetExp;
		$resetKey = md5($resetTxt);
		try {
			$db_data = array("id" => $staff->id, "reset_exp" => $resetExp);
			$stMdl->save($db_data);
			$data['name'] = $staff->fname . " " . substr($staff->fname, 0, 1) . ".";
			$data['link'] = base_url() . "verify_forget/" . $resetKey . "/" . $staff->id;
			$html_msg = view("emails/password_reset", $data);
			if ($this->_send_email($email, lang("app.SomanetAccount"), $html_msg)) {
				return $this->response->setJSON(array("success" => lang("app.linkSent"), "name" => $data['name']));
			} else {
				return $this->response->setJSON(array("error" => lang("app.linkSentFail")));
			}
		} catch (\Exception $e) {
			return $this->response->setJSON(array("error" => lang("app.sendFailPass") . $e->getMessage()));
		}
	}

	public function upload_image($type)
{
    helper('filesystem');

    $id   = $this->request->getPost("id");
    $file = $this->request->getFile("file");
    $maxBytes = 5 * 1024 * 1024; // 5 MB

    // --- Validate file existence
    if (!$file || !$file->isValid()) {
        return $this->response->setJSON(["error" => lang("app.invalidFile") ?: "Invalid file upload"]);
    }

    // --- Validate file type (prefer client extension; fallback guess)
    $allowedExt = ["jpg", "jpeg", "png"];
    $ext = strtolower($file->getClientExtension() ?: $file->getExtension() ?: '');
    if ($ext === '' && $file->getMimeType()) {
        $mimeMap = ['image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/png' => 'png'];
        $ext = $mimeMap[$file->getMimeType()] ?? '';
    }

    if (!in_array($ext, $allowedExt, true)) {
        return $this->response->setJSON(["error" => lang("app.fileNotAllowed")]);
    }

    // --- Validate file size (5 MB max)
    if ($file->getSize() > $maxBytes) {
        return $this->response->setJSON(["error" => lang("app.fileSizeBigger") . " (max 5MB)"]);
    }

    $name = make_profile_photo_name($ext);

    // Define base folders
    $profilePath    = FCPATH . "assets/images/profile/";
    $logoPath       = FCPATH . "assets/images/logo/";
    $backgroundPath = FCPATH . "assets/images/background/";
    $signaturePath  = FCPATH . "assets/images/signatures/";

    $ensureDir = static function (string $path): void {
        if (!is_dir($path) && !@mkdir($path, 0775, true) && !is_dir($path)) {
            throw new \RuntimeException("Upload folder missing and could not be created: " . $path);
        }
        if (!is_writable($path)) {
            @chmod($path, 0775);
        }
        if (!is_writable($path)) {
            throw new \RuntimeException("Upload folder is not writable: " . $path);
        }
    };

    $safeMove = static function ($file, string $path, string $name) use ($ensureDir): void {
        $ensureDir($path);
        if (!$file->move($path, $name, true)) {
            $err = method_exists($file, 'getErrorString') ? $file->getErrorString() : 'move failed';
            throw new \RuntimeException("Could not save upload to {$path}{$name}: {$err}");
        }
    };

    try {
        switch ($type) {

            /* ==================== STUDENT PICTURE ==================== */
            case "student_picture":
                $safeMove($file, $profilePath, $name);

                $student = new \App\Models\StudentModel();
                $student->update($id, ["photo" => $name]);

                return $this->response->setJSON(["success" => lang("app.photoUploaded")]);

            /* ==================== STAFF PICTURE ==================== */
            case "staff_picture":
                $safeMove($file, $profilePath, $name);

                $staff = new \App\Models\StaffModel();
                $staff->update($id, ["photo" => $name]);

                if ($id == $this->session->get("soma_id")) {
                    $this->session->set("soma_picture", $name);
                }

                return $this->response->setJSON(["success" => lang("app.staffPhotoUploaded")]);

            /* ==================== STUDENT / STAFF CARD BACKGROUND ==================== */
            case "card_background":
            case "sf_card_background":
                $safeMove($file, $backgroundPath, $name);

                $schoolId = $this->session->get("soma_school_id");
                if (!$schoolId) throw new \Exception("School ID missing from session.");

                $school = new \App\Models\SchoolModel();
                $school->update($schoolId, [$type => $name]);

                return $this->response->setJSON([
                    "success" => $type === "card_background"
                        ? lang("app.schltCardSaved")
                        : lang("app.schlStaffCardSaved")
                ]);

            /* ==================== ALL SIGNATURES ==================== */
            case "headmaster_signature":
            case "matron_signature":
            case "patron_signature":
            case "discipline_signature":
                $safeMove($file, $signaturePath, $name);

                $schoolId = $this->session->get("soma_school_id");
                if (!$schoolId) throw new \Exception("School ID missing from session.");

                $school = new \App\Models\SchoolModel();

                // --- Remove old signature if exists ---
                $old = $school->select($type)->find($schoolId);
                if (!empty($old[$type]) && file_exists($signaturePath . $old[$type])) {
                    @unlink($signaturePath . $old[$type]);
                }

                // --- Update new signature ---
                $school->update($schoolId, [$type => $name]);

                $label = ucfirst(str_replace("_", " ", $type));
                return $this->response->setJSON(["success" => "$label uploaded successfully"]);

            /* ==================== SCHOOL LOGO (DEFAULT) ==================== */
            default:
                $safeMove($file, $logoPath, $name);

                $schoolId = $this->session->get("soma_school_id");
                if (!$schoolId) throw new \Exception("School ID missing from session.");

                $school = new \App\Models\SchoolModel();

                // --- Remove old logo if exists ---
                $old = $school->select('logo')->find($schoolId);
                if (!empty($old['logo']) && file_exists($logoPath . $old['logo'])) {
                    @unlink($logoPath . $old['logo']);
                }

                // --- Update new logo ---
                $school->update($schoolId, ["logo" => $name]);

                return $this->response->setJSON(["success" => lang("app.logoSaved")]);
        }
    } catch (\Exception $e) {
        return $this->response->setJSON([
            "error" => $e->getMessage() ?: lang("app.systemErr")
        ]);
    }
}


	public
	function send_multiple_sms()
	{
		$this->_preset();
		set_time_limit(0);
		ini_set("memory_limit", -1);
		ini_set("max_execution_time", -1);
		$type = $this->request->getPost("type");
		$message = $this->request->getPost("message");
		$mode_boarding = empty($this->request->getPost("mode_boarding")) ? 0 : 1;
		$mode_day = empty($this->request->getPost("mode_day")) ? 0 : 1;
		$estimation = $this->request->getPost("estimation");
		$stMdl = new StudentModel();
		$smsMdl = new SmsModel();
		$smsRMdl = new SmsRecipientModel();

		//check if the school has intouch info and prevent is from balance check
		$intouchAccount = new IntouchAccount();
		$account_info = $intouchAccount->where('school_id', $this->session->get("soma_school_id"))->first();

		log_message('error', 'found account {id} with {username} as username and {password} as pwd', [$account_info]);

		$intouch_account_found = false;
		if (!is_null($account_info) && trim($account_info['username']) && trim($account_info['password'])) {
			$intouch_account_found = true;
		}
		if (!$intouch_account_found && $estimation > $this->data['remaining_sms']) {
			return $this->response->setJSON(['error' => "SMS can not be sent, Remaining balance is " . $this->data['remaining_sms']]);
		}
		if ($type == "dep") {
			//send to selected departments
			$ids = $this->request->getPost("dept_id");
			$sent = 0;
			$all = 0;
			if (count($ids) == 0) {
				return $this->response->setJSON(array("error" => lang("app.optionsErr")));
			}
			$sid = $smsMdl->insert(array("school_id" => $this->session->get("soma_school_id")
			, "active_term" => $this->data['active_term'], "content" => $message, "recipient_type" => 0
			, "subject" => "Communication"));
			if ($sid === false)
				return $this->response->setJSON(array("error" => lang("app.smsErr")));
			foreach ($ids as $id) {
				$phones = $stMdl->get_student("d.id={$id} AND (ft_phone!='' OR mt_phone!='' OR gd_phone!='')", null, "students.id,ft_phone,mt_phone,gd_phone");

				foreach ($phones as $phone) {
					$all++;
					$p = strlen(trim($phone["ft_phone"])) > 4 ? $phone["ft_phone"] : (strlen(trim($phone["mt_phone"])) > 4 ? $phone["mt_phone"] :
							(strlen(trim($phone["gd_phone"])) > 4 ? $phone["gd_phone"] : ""));
					try {
						$smsRMdl->save(array("sms_record_id" => $sid, "receiver_id" => $phone['id'], "phone" => $p, "status" => 0));
						$sent++;
					} catch (\Exception $e) {
						//future use
						return $this->response->setJSON(array("error" => "Error: " . $e));
					}
				}
			}
			$param = base_url("background_process/2");
			if ($intouch_account_found) {
				$param .= "/" . $this->session->get("soma_school_id");
			}
			$command = "curl $param > /dev/null &";
			exec($command);
			return $this->response->setJSON(array("success" => lang("app.beSent") . " $sent" . lang("app.over") . " $all"));
		} else if ($type == "class") {
			//send to selected departments
			$ids = $this->request->getPost("class_id");
			if (count($ids) == 0) {
				return $this->response->setJSON(array("error" => lang("app.optionsErr")));
			}
			$sent = 0;
			$all = 0;
			$sid = $smsMdl->insert(array("school_id" => $this->session->get("soma_school_id")
			, "active_term" => $this->data['active_term'], "content" => $message, "recipient_type" => 0
			, "subject" => "Communication"));
			if ($sid === false)
				return $this->response->setJSON(array("error" => lang("app.smsErr")));
			foreach ($ids as $id) {
				$phones = $stMdl->get_student("c.id={$id} AND (ft_phone!='' OR mt_phone!='' OR gd_phone!='')", null, "students.id,ft_phone,mt_phone,gd_phone");
				foreach ($phones as $phone) {
					$all++;
					$p = strlen(trim($phone["ft_phone"])) > 4 ? $phone["ft_phone"] : (strlen(trim($phone["mt_phone"])) > 4 ? $phone["mt_phone"] :
							(strlen(trim($phone["gd_phone"])) > 4 ? $phone["gd_phone"] : ""));
					try {
						$smsRMdl->save(array("sms_record_id" => $sid, "receiver_id" => $phone['id'], "phone" => $p, "status" => 0));
						$sent++;
					} catch (\Exception $e) {
						//future use
						return $this->response->setJSON(array("error" => "Error: " . $e));
					}
				}
			}
			$param = base_url("background_process/2");
			$command = "curl $param > /dev/null &";
			exec($command);
			return $this->response->setJSON(array("success" => lang("app.beSent") . " $sent" . lang("app.over") . " $all"));
		} else if ($type == "student") {
			//send to selected departments
			$ids = $this->request->getPost("studentId");
			if (count($ids) == 0) {
				return $this->response->setJSON(array("error" => lang("app.optionsErr")));
			}
			$sent = 0;
			$all = 0;
			$sid = $smsMdl->insert(array("school_id" => $this->session->get("soma_school_id")
			, "active_term" => $this->data['active_term'], "content" => $message, "recipient_type" => 0
			, "subject" => "Communication"));
			if ($sid === false)
				return $this->response->setJSON(array("error" => lang("app.smsErr")));
			foreach ($ids as $id) {
				$phones = $stMdl->get_student("students.id={$id} AND (ft_phone!='' OR mt_phone!='' OR gd_phone!='')", null, "students.id,ft_phone,mt_phone,gd_phone");
				foreach ($phones as $phone) {
					$all++;
					$p = strlen(trim($phone["ft_phone"])) > 4 ? $phone["ft_phone"] : (strlen(trim($phone["mt_phone"])) > 4 ? $phone["mt_phone"] :
							(strlen(trim($phone["gd_phone"])) > 4 ? $phone["gd_phone"] : ""));
					try {
						$smsRMdl->save(array("sms_record_id" => $sid, "receiver_id" => $phone['id'], "phone" => $p, "status" => 0));
						$sent++;
					} catch (\Exception $e) {
						//future use
						return $this->response->setJSON(array("error" => "Error: " . $e));
					}
				}
			}
			$param = base_url("background_process/2");
			$command = "curl $param > /dev/null &";
			exec($command);
			return $this->response->setJSON(array("success" => lang("app.beSent") . " $sent" . lang("app.over") . " $all"));
		}
//		$param = base_url("background_process/2");
//		$command = "curl $param > /dev/null &";
//		exec($command);
	}

	public
	function send_multiple_sms_staff()
	{
		$this->_preset();
		set_time_limit(0);
		ini_set("memory_limit", -1);
		ini_set("max_execution_time", -1);
		$type = $this->request->getPost("type");
		$message = $this->request->getPost("message");
		$stMdl = new StaffModel();
		$smsMdl = new SmsModel();
		$smsRMdl = new SmsRecipientModel();
		if ($type == "post") {
			//send to selected departments
			$ids = $this->request->getPost("post_id");
			$sent = 0;
			$all = 0;
			if (count($ids) == 0) {
				return $this->response->setJSON(array("error" => lang("app.optionsErr")));
			}
			$sid = $smsMdl->insert(array("school_id" => $this->session->get("soma_school_id")
			, "active_term" => $this->data['active_term'], "content" => $message, "recipient_type" => 1
			, "subject" => "Communication"));
			if ($sid === false)
				return $this->response->setJSON(array("error" => lang("app.smsErr")));
			foreach ($ids as $id) {
				$phones = $stMdl->get_staff("p.id={$id} AND phone!=''", "staffs.id,staffs.phone");
				foreach ($phones as $phone) {
					$all++;
					$p = $phone["phone"];
					try {
						$smsRMdl->save(array("sms_record_id" => $sid, "receiver_id" => $phone['id'], "phone" => $p, "status" => 0));
						$sent++;
					} catch (\Exception $e) {
						//future use
						return $this->response->setJSON(array("error" => "Error: " . $e));
					}
				}
			}
			$param = base_url("background_process/2");
			$command = "curl $param > /dev/null &";
			exec($command);
			return $this->response->setJSON(array("success" => lang("app.beSent") . " $sent" . lang("app.over") . " $all"));
		} else if ($type == "staff") {
			//send to selected staffs
			$ids = $this->request->getPost("staffId");
			if (count($ids) == 0) {
				return $this->response->setJSON(array("error" => lang("app.optionsErr")));
			}
			$sent = 0;
			$all = 0;
			$sid = $smsMdl->insert(array("school_id" => $this->session->get("soma_school_id")
			, "active_term" => $this->data['active_term'], "content" => $message, "recipient_type" => 1
			, "subject" => "Communication"));
			if ($sid === false)
				return $this->response->setJSON(array("error" => lang("app.smsErr")));
			foreach ($ids as $id) {
				$phones = $stMdl->get_staff("staffs.id={$id} AND staffs.phone !=''", "staffs.id,staffs.phone");
				foreach ($phones as $phone) {
					$all++;
					$p = $phone["phone"];
					try {
						$smsRMdl->save(array("sms_record_id" => $sid, "receiver_id" => $phone['id'], "phone" => $p, "status" => 0));
						$sent++;
					} catch (\Exception $e) {
						//future use
						return $this->response->setJSON(array("error" => "Error: " . $e));
					}
				}
			}
			$param = base_url("background_process/2");
			$command = "curl $param > /dev/null &";
			exec($command);
			return $this->response->setJSON(array("success" => lang("app.beSent") . " $sent" . lang("app.over") . " $all"));
		}
//		$param = base_url("background_process/2");
//		$command = "curl $param > /dev/null &";
//		exec($command);
	}

	public
	function background_process($pid = 1, $school_account = null)
	{
		log_message('error', 'process requested with pid:{pid} and school_account:{school_account}', ['pid' => $pid, 'school_account' => $school_account]);
		session_write_close();
		set_time_limit(0);
		ini_set("memory_limit", -1);
		ini_set("max_execution_time", -1);
		$smsRMdl = new SmsRecipientModel();
		$pendings = $smsRMdl->select("sms_record_recipients.id,sms_record_recipients.phone,s.content
		,s.school_id,s.active_term,p.sms_limit,at.sms_usage,sk.acronym,sk.extra_sms")
				->join("sms_records s", "s.id=sms_record_recipients.sms_record_id")
				->join("schools sk", "sk.id=s.school_id")
				->join("packages p", "p.id=sk.package")
				->join("active_term at", "at.id=s.active_term")
				->where("sms_record_recipients.status", "0")
				->get()
				->getResultArray();
		$termMdl = new TermModel();
		if (count($pendings) > 0) {
			foreach ($pendings as $pending) {
				try {
//					$pending['remaining_sms'] = $pending['sms_limit'] - $pending['sms_usage'] + $pending['extra_sms'];
					$pending['remaining_sms'] = $pending['extra_sms'];

					// $school_account = $this->request->getGet('school_id');
					if ($school_account) {
						// echo "Here "; die();
					}
					if ($this->sendSMS($pending['phone'], $pending['content'], $result)) {
						//increment used sms
						$sms_count = (int)ceil(strlen($pending['content']) / PER_SMS);
						if (($pending['sms_limit'] - $pending['sms_usage']) <= 0 && $pending['extra_sms'] > 0) {
							//decrement extra sms
							$schoolMdl = new SchoolModel();
							$schoolMdl->where("id", $pending['school_id'])->decrement("extra_sms", $sms_count);
						}
						$termMdl->incrementSMS($pending['active_term'], $sms_count);
						$smsRMdl->save(array("id" => $pending['id'], "status" => 1, "sent_on" => time()));
					} else {
						$smsRMdl->save(array("id" => $pending['id'], "status" => 2, "fail_reason" => $result['content']));
					}
				} catch (\Exception $e) {
//					return $this->response->setJSON(array("error" => "Error: ".$e));
				}
			}
		}
	}


	public
	function marks_entry()
	{
		$this->_preset();
		$data = $this->data;
		$data['title'] = lang("app.marksEntry");
		$data['subtitle'] = lang("app.marksEntry");
		$data['page'] = "marks";
		$courseModel = new CourseModel();
		$school_id = $this->session->get("soma_school_id");
		$term = $this->request->getGet('term');
		$academic_year = $this->request->getGet('academic_year');
		$term = $term == null ? $data['term'] : $term;
		$academic_year = $academic_year == null ? $data['academic_year'] : $academic_year;
		$data['term'] = $term;
		$data['academic_year_id'] = $academic_year;
		if (!in_array($this->session->get("soma_post"), [1, 3]) && $term != $data['term']) {
			$data['error'] = "you are not allowed to manage marks of selected term";
		}
		if (!in_array($this->session->get("soma_post"), [1, 3]) && $academic_year != $data['academic_year']) {
			$data['error'] = "you are not allowed to manage marks of selected academic year";
		}
		$atMdl = new ActiveTermModel();
		$termData = $atMdl->select('active_term.id,ay.title')->join("academic_year ay", "ay.id = active_term.academic_year")->where("term", $term)->where("academic_year", $academic_year)
				->where('active_term.school_id', $this->session->get("soma_school_id"))->get(1)->getRow();
		if ($termData == null) {
			$data['error'] = "Invalid term and academic year provided, please try again later";
		} else {
			$data['academic_year'] = $termData->title;
			$builder = $courseModel->select("courses.id,courses.title,courses.code,courses.marks,courses.credit,cs.title as category ,r.id record_id,concat(s.fname,' ',s.lname) as mentor_name")
					->join("course_category cs", "cs.id=courses.category")
					->join("course_records r", "courses.id=r.course")
					->join("staffs s", "s.id=r.lecturer")
					->where("courses.school_id", $school_id)
					->where("r.year", $academic_year)
					->where("find_in_set($term,r.term) !=0")
					->groupBy("courses.id");
			if (!in_array($this->session->get("soma_post"), [1, 3])) {
				//filter courses if is not head master or dean of studies
				$builder->where("s.id", $this->session->get("soma_id"));
			}
			$data['courses'] = $builder->get()->getResultArray();
		}
		$data['soma_name'] = $this->session->get("soma_name");
		$data['content'] = view("pages/marks/marks_entry", $data);
		return view('main', $data);
	}

	public
	function manipulate_marks()
	{
		$this->_preset();
		$class = $this->request->getPost("class_id_name");
		$student_id = $this->request->getPost("discId[]");
		$marks_id = $this->request->getPost("marks_id[]");
		$year = $this->request->getPost("year");
		$term = $this->data['active_term'];
		if ($this->data['term'] != $this->request->getPost("term")) {
			$atMdl = new ActiveTermModel();
			$termData = $atMdl->select('id')->where("term", $this->request->getPost("term"))
					->where("academic_year", $year)
					->where('school_id', $this->session->get("soma_school_id"))->get(1)->getRow();
			if ($termData == null) {
				return $this->response->setJSON(array("error" => "Invalid term provided, please try again later"));
			}
			$term = $termData->id;
		}
		$course_id = $this->request->getPost("course");
		$mark_type = $this->request->getPost("marktype");
		$examDate = strtotime($this->request->getPost("examDate"));
		$marks = $this->request->getPost("marks[]");
		$Catmarks = $this->request->getPost("marksC[]");
		$Exammarks = $this->request->getPost("marksE[]");
		$catType = $this->request->getPost("catType") == null ? '' : $this->request->getPost("catType");
		$outof = $this->request->getPost("outofmarks");
		$period = $this->request->getPost("period") == null ? 0 : $this->request->getPost("period");
		$created_by = $this->session->get("soma_id");
		if (!is_array($student_id)) {
			return $this->response->setJSON(array("error" => lang("app.pleaseAddErr")));
		}
		if ((int) $period > 0 && $this->isPeriodLocked($term, $period)) {
			return $this->response->setJSON(array(
				"error" => "Period " . (int) $period . " is locked. Marks entry is not allowed. Contact the school admin to unlock it."
			));
		}
//		print_r($marks_id); die();

		$MarksModel = new MarksModel();
		if ($mark_type == 4) {
			//exam and cat
			$marks_id1 = $this->request->getPost("marks_id1[]");
			$i = 0;
			foreach ($student_id as $std) {
				$a = $std;
				$catVal = self::normalizeMarkEntry($Catmarks[$i] ?? '');
				$examVal = self::normalizeMarkEntry($Exammarks[$i] ?? '');
				$data1 = array(
						"student_id" => $a,
						"term" => $term,
						"examDate" => $examDate,
						"course_id" => $course_id,
						"class_id" => $class,
						"mark_type" => 1,
						"marks" => $catVal,
						"outof" => $outof,
						"cat_type" => $catType,
						"period" => $period,
						"created_by" => $created_by);
				$data2 = array(
						"student_id" => $a,
						"term" => $term,
						"examDate" => $examDate,
						"course_id" => $course_id,
						"class_id" => $class,
						"mark_type" => 2,
						"marks" => $examVal,
						"outof" => $outof,
						"cat_type" => $catType,
						"period" => $period,
						"created_by" => $created_by);
				if ($marks_id[$i] != 0 && strlen((string) $marks_id[$i]) > 0) {
					//edit
					$data1 = array(
							"id" => $marks_id[$i],
							"outof" => $outof,
							"marks" => $catVal);
				}
				if ($marks_id1[$i] != 0 && strlen((string) $marks_id1[$i]) > 0) {
					//edit exam
					$data2 = array(
							"id" => $marks_id1[$i],
							"outof" => $outof,
							"marks" => $examVal);
				}
				try {
					// Always save (0 = zero score, -1 = did not sit)
					$MarksModel->save($data1);
					$MarksModel->save($data2);
				} catch (\Exception $e) {
					return $this->response->setJSON(array("error" => lang("app.OopsAction") . $e->getMessage()));
				}
				$i++;
			}
			return $this->response->setJSON(array("success" => lang("app.marksSaved")));
		} else {
			$i = 0;
			foreach ($student_id as $std) {
				$a = $std;
				$rawMark = $marks[$i] ?? '';
				if ($mark_type == 9 && (trim((string) $rawMark) === '' || trim((string) $rawMark) === '-')) {
					//skip empty re-assessment
					$i++;
					continue;
				}
				$markVal = self::normalizeMarkEntry($rawMark);
				$data = array(
						"student_id" => $a,
						"term" => $term,
						"examDate" => $examDate,
						"course_id" => $course_id,
						"class_id" => $class,
						"mark_type" => $mark_type,
						"marks" => $markVal,
						"outof" => $outof,
						"cat_type" => $catType,
						"period" => $period,
						"created_by" => $created_by);
				if ($marks_id[$i] != 0 && strlen((string) $marks_id[$i]) > 0) {
					//edit
					$data = array(
							"id" => $marks_id[$i],
							"outof" => $outof,
							"marks" => $markVal);
				}

				try {
					// Save including explicit 0 and absent (-1)
					$MarksModel->save($data);
				} catch (\Exception $e) {
					return $this->response->setJSON(array("error" => lang("app.OopsAction") . $e->getMessage()));
				}
				$i++;
			}
			return $this->response->setJSON(array("success" => lang("app.marksSaved")));
		}
	}

	public
	function get_periodic_report($pdf = false)
	{
		$this->_preset();
		$data = $this->data;
		$data['title'] = lang("app.ViewStudentPeriodicMarks");
		$data['subtitle'] = lang("app.viewMarks");
		$data['page'] = "get_periodic_marks";
		$cMdl = new ClassesModel();
		$school_id = $this->session->get("soma_school_id");
		$data['classes'] = $cMdl->get_classes();
		$acMdl = new AcademicYearModel();
		$data['years'] = $acMdl->select('id,title')->where("school_id", $school_id)
				->orderBy("id", 'DESC')->get()->getResultArray();
		$data['content'] = view("pages/marks/periodic_report", $data);
		return view('main', $data);
	}

	public
	function get_uploaded_marks($type = 0, $pdf = false)
	{
		//0:view field,1:view data
		if ($type == 0) {
			$this->_preset();
			$data = $this->data;
			$data['title'] = lang("app.viewUploadedMarks");
			$data['subtitle'] = lang("app.viewMarks");
			$data['page'] = "get_uploaded_marks";
			$cMdl = new ClassesModel();
			$school_id = $this->session->get("soma_school_id");
			$data['classes'] = $cMdl->get_classes();
			$acMdl = new AcademicYearModel();
			$data['years'] = $acMdl->select('id,title')->where("school_id", $school_id)
					->orderBy("id", 'DESC')->get()->getResultArray();
			$data['content'] = view("pages/marks/uploaded_marks", $data);
			return view('main', $data);
		} else {
			$html = "";
			$pdf = $this->request->getPost("pdf");
			$year = $this->request->getPost("year");
			$term = $this->request->getPost("term");
			$class = $this->request->getPost("class");
			$course = $this->request->getPost("course");
			$period = $this->request->getPost("period");
			$marksMdl = new MarksModel();
			$atMdl = new ActiveTermModel();
			$school_id = $this->session->get("soma_school_id");
			$active_term = $atMdl->select("id")->where("term", $term)
					->where("academic_year", $year)->where("school_id", $school_id)
					->get(1)->getRow();
			if ($active_term == null) {
				echo "invalid data, please try again later";
				die();
			}
			$builder = $marksMdl->select("marks.outOf,mark_type,cat_type,period,marks.examDate,cs.id,marks.created_at,marks.class_id,
			cs.title as courseName,cs.code as courseCode,cs.marks as courseMarks,concat(l.title,' ',d.code,' ',c.title) as class,marks.course_id,
			at.term,count(marks.id) as count,avg(marks.marks) as avg,concat(s.fname,' ',s.lname) as names,at.academic_year")
					->join("classes c", "c.id=marks.class_id")
					->join('departments d', 'd.id=c.department')
					->join('levels l', 'l.id=c.level')
					->join("courses cs", "cs.id=marks.course_id")
					->join("staffs s", "s.id=marks.created_by")
					->join("active_term at", "at.id=marks.term")
					->where("at.school_id", $this->session->get("soma_school_id"))
					->where("at.academic_year", $year)
					->where("at.term", $term)
					->where("marks.class_id", $class);
			if ($period != 0) {
				$builder->where("marks.period", $period);
			}
			if ($course != 0) {
				$builder->where("marks.course_id", $course);
			}
			$builder->groupBy("marks.course_id");
			$builder->groupBy("marks.class_id");
			$builder->groupBy("marks.mark_type");
			$builder->groupBy("marks.cat_type");
			$builder->groupBy("marks.period");
			$builder->groupBy("marks.created_by");
			$builder->orderBy("c.id");
			$builder->orderBy("cs.id");
			$builder->orderBy("marks.created_at");
			$marks = $builder->get()->getResultArray();
			if (count($marks) == 0) {
				echo "No course marks found on the selected period,term and academic year, please try again later";
				die();
			}
			$html .= "<table style='border: 0px' border='1' id='marks_table' class='table table-striped table-bordered table-condensed'><thead>
<tr><th>#</th><th>Class</th><th>Course</th><th>Term</th><th>Assessment type</th><th>Period</th><th>CAT type</th><th>Out Of</th>
<th>assignment Date</th><th>No of student</th><th>Average</th><th>Created by</th><th>Created at</th><th></th></tr></thead><tbody>";
			$a = 0;
			foreach ($marks as $mark) {
				$a++;
				$html .= "<tr>
<td>{$a}</td>
<td>{$mark['class']}</td>
<td>{$mark['courseName']} - {$mark['courseCode']}</td>
<td>".termToStr($mark['term'])."</td>
<td>".self::marksTypeToStr($mark['mark_type'])."</td>
<td>{$mark['period']}</td>
<td>".catTypeStr($mark['cat_type'])."</td>
<td>{$mark['outOf']}</td>
<td>" . date('Y-m-d', $mark['examDate']) . "</td>
<td>{$mark['count']}</td>
<td>".number_format($mark['avg'],2)."</td>
<td>{$mark['names']}</td>
<td>{$mark['created_at']}</td>
<td><a target='_blank' href='".base_url('get_student_marks/'. $mark['mark_type'] .'/'. $mark['cat_type'] .'/'. $mark['class_id'] .'/'. $mark['course_id'] .'/'. $mark['period'] .'/'. $mark['term'].'/'.$mark['academic_year'])."?pdf' class='btn btn-success'>Print</a> </td>
</tr>";
			}
			$html .= "</tbody></table>";
			$html .="<script>$('#marks_table').dataTable({paging: false});</script>";

			if ($pdf) {
				$this->_preset();
				$data = $this->data;
				$classMdl = new ClassesModel();
				$data["class"] = $classMdl->get_class_name($class);
				$data["period"] = $period;
				$data["content"] = $html;
				$html = view("templates/student_results", $data);
				try {
					$mask = FCPATH . "assets/templates/*.html";
					array_map('unlink', glob($mask));//clear previous cards
					$wkhtmltopdf = new Wkhtmltopdf(array('path' => FCPATH . 'assets/templates/'));
					$wkhtmltopdf->setTitle(lang("app.rtudentRsults"));
					$wkhtmltopdf->setHtml($html);
					$wkhtmltopdf->setOrientation("Landscape");
//					$wkhtmltopdf->setOptions(array("page-width" => "278px", "page-height" => "430px"));
					$wkhtmltopdf->setMargins(array("top" => 2, "left" => 10, "right" => 5, "bottom" => 2));
					$wkhtmltopdf->output(Wkhtmltopdf::MODE_EMBEDDED, "students_results_" . time() . ".pdf");
				} catch (\Exception $e) {
					echo $e->getMessage();
				}
			} else {
				echo $html;
			}
		}
	}

	public
	function student_term_results($type = 0, $pdf = false)
	{
		//0:view field,1:view data
		if ($type == 0) {
			$this->_preset();
			$data = $this->data;
			$data['title'] = lang("app.ViewStudentTermMarks");
			$data['subtitle'] = lang("app.viewMarks");
			$data['page'] = "student_term_results";
			$cMdl = new ClassesModel();
			$termMdl = new TermModel();
			$school_id = $this->session->get("soma_school_id");
			$data['classes'] = $cMdl->get_classes();
			$data['school_id'] = $school_id;
			$acMdl = new AcademicYearModel();
			$data['years'] = $acMdl->select('id,title')->where("school_id", $school_id)
					->orderBy("id", 'DESC')->get()->getResultArray();
			$data['content'] = view("pages/marks/term_marks", $data);
			return view('main', $data);
		} else {
			$html = "";
			$pdf = $this->request->getPost("pdf");
			$year = $this->request->getPost("year");
			$term = $this->request->getPost("term");
			$class = $this->request->getPost("class");
			$course = $this->request->getPost("course");
			$type = $this->request->getPost("type");
			$StudentModel = new StudentModel();
			$course_filter = null;
			$school_id = isset($_GET['school']) ? $_GET['school'] : $this->session->get("soma_school_id");
			if ($course != 0) {
				//single  course
				$course_filter = "courses.id=" . $course;
			}

			$courses = $this->get_courses($class, $term, $year, true, $course_filter);
			$html .= "<table style='border: 0px' border='1'><tr><th colspan='3'></th>";
			$course_header = array();
			$course_header_code = array();
			$max_total = 0;
			$cols = 2;
			if (count($courses) == 0) {
				echo "<h3>No marks available in the selected period</h3>";
				die();
			}
			$times = 1;
			if ($term == 4) {
				$times = 3;
			}
			$second_header = "";
			foreach ($courses as $item) {
				$max_total += $item['marks'] * $times;
				if ($school_id == 5) {
					//cambridge for blue lakes
					$cols = 4;
					if ($type != null && $type != 0) {
						//single  type
						$cols = 1;
					}
					if ($type != null && $type != 0) {
						//single  type
						$html .= "<th colspan='$cols' class='cbd'><div style='text-align: center;font-size: 9pt'><label>" . $item['title'] . " / " . $item['marks'] . "</label></div></th>";
						$second_header .= "<td style='text-align: center'><strong>" . cambridge_option_text($type) . "</strong></td>";
					} else {
						$html .= "<th colspan='$cols' class='cbd'><div style='text-align: center;font-size: 9pt'><label>" . $item['title'] . " / " . ($item['marks'] * 4) . "</label></div></th>";
						$second_header .= "<td><strong>CW</strong></td><td><strong>BOT</strong></td><td><strong>MID</strong></td><td><strong>EOT</strong></td>";
					}

				} else {
					$html .= "<th class='rotate-45' colspan='2'><div><label>" . $item['title'] . " / " . ($item['marks'] * 2 * $times) . "</label></div></th>";
					$second_header .= "<td><strong>CAT</strong></td><td><strong>EXAM</strong></td>";
				}
				$course_header[] = $item['id'];
				$course_header_code[] = $item['code'];
				$cols++;
			}

			$school_id = isset($_GET['school']) ? $_GET['school'] : $this->session->get("soma_school_id");
			$atMdl = new ActiveTermModel();

			if ($term == 4) {
				//annual report
				$active_term = $atMdl->select("id")
						->where("academic_year", $year)->where("school_id", $school_id)
						->get()->getResultArray();
				if ($active_term == []) {
					echo "invalid data, please try again later";
					die();
				}
				$active_term = array_column($active_term, 'id', 'key');
				$active_term_id = implode(",", $active_term);
			} else {
				$active_term = $atMdl->select("id")->where("term", $term)
						->where("academic_year", $year)->where("school_id", $school_id)
						->get(1)->getRow();
				if ($active_term == null) {
					echo "invalid data, please try again later";
					die();
				}
				$active_term_id = $active_term->id;
			}
			$students = $StudentModel->select("students.id,students.regno,students.fname,
														students.lname,c.id as class,
														sum(di.marks) as displine_marks,f.id as fac_id,")
					->join('class_records cr', 'cr.student=students.id')
					->join('classes c', 'c.id=cr.class')
					->join('departments d', 'd.id=c.department')
//				->join('levels l', 'l.id=c.level')
					->join('faculty f', 'f.id=d.faculty_id')
//				->join('schools sk', 'sk.id=students.school_id')
//			->join("active_term at", "at.id=sk.active_term")
					->join('disciplines di', 'di.student_id=students.id', 'LEFT')
//				->where("c.school_id", $school_id)
//			->where("sk.active_term", $active_term->id)
					->where("cr.status", "1")
					->where("c.id", $class)
					->where("cr.year", $year)
//			->where("at.term", $term)
					->orderBy("students.fname", "ASC")
					->groupBy('students.id')
					->get()->getResultArray();
			$records = array();
//		var_dump($students);echo $active_term->id;die();
			$a = 0;
			$average = [];
			foreach ($students as $student) {
				$records[$a] = $student;
				$tot = 0;
				$cCount = 0;
				$student["fac_id"] = $school_id == 5 ? 20 : $student["fac_id"];//force blue lakes school to use cambridge mode
				foreach ($courses as $core) {
					$markss = $this->__result($core['id'], $student['id'], $term, $year, $student["fac_id"], $type);
					if ($student["fac_id"] == '20') {
						$core['result']['BOT'] = 0;
						$core['result']['CW'] = 0;
						$core['result']['MID'] = 0;
						$core['result']['EOT'] = 0;
						$typeArray = [5, 6, 7, 8];
						if ($type != null && $type != 0) {
							//single  type
							$typeArray = [$type];
						}
						foreach ($markss as $m) {
							if (in_array($m['mark_type'], $typeArray)) {
								//allow cambridge marks only
								$core['result'][cambridge_option_text($m['mark_type'])] = $m['marks'];
								$tot += $m['marks'];
							}
						}
					} else {
//						$core['result'] = $markss;
						if ($term != 4) {
							$core['result'] = ['marks' => $markss['cat'][$term] ?? null
								, 'exam_marks' => $markss['exam'][$term] ?? null
							];
							$tot += $core['result']['marks'] + $core['result']['exam_marks'];
						} else {
							$cM = 0;
							$exM = 0;
							$tot1 = 0;
							$tot2 = 0;
							$tot3 = 0;
//							$core['result'] = $markss;
							if (isset($markss['cat'][1])) {
								$tot1 += $markss['cat'][1] ?? null;
								$tot1 += $markss['exam'][1] ?? null;
								$cM += $markss['cat'][1] ?? null;
								$exM += $markss['exam'][1] ?? null;
							}
							if (isset($markss['cat'][2])) {
								$tot2 += $markss['cat'][2] ?? null;
								$tot2 += $markss['exam'][2] ?? null;
								$cM += $markss['cat'][2] ?? null;
								$exM += $markss['exam'][2] ?? null;
							}
							if (isset($markss['cat'][3])) {
								$tot3 += $markss['cat'][3] ?? null;
								$tot3 += $markss['exam'][3] ?? null;
								$cM += $markss['cat'][3] ?? null;
								$exM += $markss['exam'][3] ?? null;
							}
							$tot += $tot1 + $tot2 + $tot3;
							$core['result'] = ['marks' => $cM ?? null
								, 'exam_marks' => $exM ?? null
							];
						}
					}
					$records[$a]['courses'][] = $core;
					if ($school_id == 5) {
						$average[$cCount]['CW'] = isset($average[$cCount]['CW'])
								? ($average[$cCount]['CW'] + $core['result']['CW'])
								: $core['result']['CW'];
						$average[$cCount]['BOT'] = isset($average[$cCount]['BOT'])
								? ($average[$cCount]['BOT'] + $core['result']['BOT'])
								: $core['result']['BOT'];
						$average[$cCount]['MID'] = isset($average[$cCount]['MID'])
								? ($average[$cCount]['MID'] + $core['result']['MID'])
								: $core['result']['MID'];
						$average[$cCount]['EOT'] = isset($average[$cCount]['EOT'])
								? ($average[$cCount]['EOT'] + $core['result']['EOT'])
								: $core['result']['EOT'];
					} else {
						$average[$cCount]['cat'] = isset($average[$cCount]['cat'])
								? ($average[$cCount]['cat'] + $core['result']['marks'])
								: $core['result']['marks'];
						$average[$cCount]['exam'] = isset($average[$cCount]['exam'])
								? ($average[$cCount]['exam'] + $core['result']['exam_marks'])
								: $core['result']['exam_marks'];
					}
					$cCount++;
				}
				$records[$a]['total'] = $tot;
				$a++;
			}
			usort($records, "cmp");

//			echo '<pre>';var_dump($average);die();
			$ii = 1;
			if ($school_id == 5) {
				$html .= "<th class='cbd'><div style='text-align: center'><label>" . lang("app.total") . "</label></div></th>";
				$html .= "<th class='cbd'><div><label>% </label></div></th>";
			} else {
				$html .= "<th class='rotate-45'><div><label style='left: -65px;'>" . lang("app.total") . "</label></div></th>";
				$html .= "<th class='rotate-45'><div><label style='left: -65px;'>" . lang("app.percentage") . "</label></div></th>";
			}
			$html .= "</tr>";
			$html .= "<tr><td style='min-width: 40px;'><strong>" . lang("app.order") . "</strong></td>
<td><strong>" . lang("app.regno") . "</strong></td><td><strong>" . lang("app.studentName") . "</strong></td>";
			$html .= $second_header;
			if ($school_id == 5) {
				if ($type != null && $type != 0) {
					//single  type
					$html .= "<td><strong>/" . $max_total . "</strong></td>";
				} else {
					$html .= "<td><strong>/" . ($max_total * 4) . "</strong></td>";
				}
			} else {
				$html .= "<td><strong>/" . ($max_total * 2) . "</strong></td>";
			}

			$html .= "<td><strong> %</strong></td>";
			$html .= "</tr>";
			if (count($students) == 0) {
				echo "<h4>No marks found</h4>";
				die();
			}
			foreach ($records as $student) {
				$row_total = 0;
				$html .= "
				<tr>
				<td style='text-align: center'>" . $ii . "</td>
				<td>" . $student['regno'] . "</td>
				<td>" . $student['fname'] . ' - ' . $student['lname'] . "</td>";
				foreach ($student['courses'] as $h) {
					if ($school_id == 5) {
						if ($type != null && $type != 0) {
							//single  type
							$color1 = $h['marks'] / 2 > $h['result'][cambridge_option_text($type)] ? "color:red;text-decoration:underline" : "";
							$html .= "<td><label style='$color1'>" . number_format($h['result'][cambridge_option_text($type)], 1) . "</label></td>";
							$row_total += $h['result'][cambridge_option_text($type)];
						} else {
							$color_cw = $h['marks'] / 2 > $h['result']['CW'] ? "color:red;text-decoration:underline" : "";
							$color_bot = $h['marks'] / 2 > $h['result']['BOT'] ? "color:red;text-decoration:underline" : "";
							$color_mid = $h['marks'] / 2 > $h['result']['MID'] ? "color:red;text-decoration:underline" : "";
							$color_eot = $h['marks'] / 2 > $h['result']['EOT'] ? "color:red;text-decoration:underline" : "";
							$html .= "<td><label style='$color_cw'>" . number_format($h['result']['CW'], 1) . "</label></td>";
							$html .= "<td><label style='$color_bot'>" . number_format($h['result']['BOT'], 1) . "</label></td>";
							$html .= "<td><label style='$color_mid'>" . number_format($h['result']['MID'], 1) . "</label></td>";
							$html .= "<td><label style='$color_eot'>" . number_format($h['result']['EOT'], 1) . "</label></td>";
							$row_total += $h['result']['CW'] + $h['result']['BOT'] + $h['result']['MID'] + $h['result']['EOT'];
						}
					} else {
						$color = $h['marks'] * $times / 2 > $h['result']['marks'] ? "color:red;text-decoration:underline" : "";
						$color_exam = $h['marks'] * $times / 2 > $h['result']['exam_marks'] ? "color:red;text-decoration:underline" : "";
						$html .= "<td><label style='$color'>" . number_format($h['result']['marks'], 1) . "</label></td>";
						$html .= "<td><label style='$color_exam'>" . number_format($h['result']['exam_marks'], 1) . "</label></td>";
						$row_total += $h['result']['marks'] + $h['result']['exam_marks'];
					}
				}
				$tttt = $student['total'];
				$student['total'] = $row_total;
				$color2 = ($student['total'] / $max_total * 100) < 50 ? "color:red;text-decoration:underline" : "";
				if ($school_id == 5) {
					$html .= "<td><label style='$color2'>" . number_format($student['total'], 1) . "</label></td>";
					if ($type != null && $type != 0) {
						//single  type
						$html .= "<td><label style='$color2'>" . number_format(($student['total'] / $max_total) * 100, 1) . "</label></td>";
					} else {
						$html .= "<td><label style='$color2'>" . number_format(($student['total'] / ($max_total * 4)) * 100, 1) . "</label></td>";
					}
				} else {
					$html .= "<td><label style='$color2'>" . number_format($student['total'], 1) . "</label></td>";
					$html .= "<td><label style='$color2'>" . number_format(($student['total'] / ($max_total * 2)) * 100, 1) . "</label></td>";
				}
				$html .= "</tr>";
				$ii++;
			}
			$html .= "<tr><td colspan='3' style='text-align: center'><strong>Average</strong></td>";
			$avgTot = 0;
			foreach ($average as $avg) {
				if ($school_id == 5) {
					if ($type != null && $type != 0) {
						//single  type
						$avg1 = $avg[cambridge_option_text($type)] / count($students);
						$html .= '<td><strong>' . number_format($avg1, 1) . '</strong></td>';
						$avgTot += $avg1;
					} else {
						$avgCw = $avg['CW'] / count($students);
						$avgBot = $avg['BOT'] / count($students);
						$avgMid = $avg['MID'] / count($students);
						$avgEot = $avg['EOT'] / count($students);
						$html .= '<td><strong>' . number_format($avgCw, 1) . '</strong></td>';
						$html .= '<td><strong>' . number_format($avgBot, 1) . '</strong></td>';
						$html .= '<td><strong>' . number_format($avgMid, 1) . '</strong></td>';
						$html .= '<td><strong>' . number_format($avgEot, 1) . '</strong></td>';
//						$avgTot += ($avgCw*10/100)+($avgBot*10/100)+($avgMid*20/100)+($avgEot*60/100);
						$avgTot += $avgCw + $avgBot + $avgMid + $avgEot;
					}

				} else {
					$avgCat = $avg['cat'] / count($students);
					$avgExam = $avg['exam'] / count($students);
					$html .= '<td><strong>' . number_format($avgCat, 1) . '</strong></td>';
					$html .= '<td><strong>' . number_format($avgExam, 1) . '</strong></td>';
					$avgTot += $avgExam + $avgCat;
				}

			}
			$html .= '<td><strong>' . number_format($avgTot, 1) . '</strong></td>';
			if ($school_id == 5) {
				if ($type != null && $type != 0) {
					//single  type
					$html .= '<td><strong>' . number_format(($avgTot / $max_total) * 100, 1) . '</strong></td>';
				} else {
					$html .= '<td><strong>' . number_format(($avgTot / ($max_total * 4)) * 100, 1) . '</strong></td>';
				}
			} else {
				$html .= '<td><strong>' . number_format(($avgTot / ($max_total * 2)) * 100, 1) . '</strong></td>';
			}
			$html .= "</tr>";
			if ($pdf) {
				$this->_preset();
				$data = $this->data;
				$classMdl = new ClassesModel();
				$data["class"] = $classMdl->get_class_name($class);
				$data["term"] = $term;
				$data["content"] = $html;
				$html = view("templates/term_results", $data);
				try {
					$mask = FCPATH . "assets/templates/*.html";
					array_map('unlink', glob($mask));//clear previous cards
					$wkhtmltopdf = new Wkhtmltopdf(array('path' => FCPATH . 'assets/templates/'));
					$wkhtmltopdf->setTitle(lang("app.rtudentRsults"));
					$wkhtmltopdf->setHtml($html);
					$wkhtmltopdf->setOrientation("Landscape");
//					$wkhtmltopdf->setOptions(array("page-width" => "278px", "page-height" => "430px"));
					$wkhtmltopdf->setMargins(array("top" => 5, "left" => 3, "right" => 3, "bottom" => 5));
					$wkhtmltopdf->output(Wkhtmltopdf::MODE_EMBEDDED, "students_term_results_" . time() . ".pdf");
				} catch (\Exception $e) {
					echo $e->getMessage();
				}
			} else {
				echo $html;
			}
		}
	}

	public
	function get_periodic_marks($type = 0, $pdf = false)
	{
		//0:view field,1:view data
		if ($type == 0) {
			$this->_preset();
			$data = $this->data;
			$data['title'] = lang("app.ViewStudentPeriodicMarks");
			$data['subtitle'] = lang("app.viewMarks");
			$data['page'] = "get_periodic_marks";
			$cMdl = new ClassesModel();
			$school_id = $this->session->get("soma_school_id");
			$data['classes'] = $cMdl->get_classes();
			$acMdl = new AcademicYearModel();
			$data['years'] = $acMdl->select('id,title')->where("school_id", $school_id)
					->orderBy("id", 'DESC')->get()->getResultArray();
			$data['content'] = view("pages/marks/periodic_marks", $data);
			return view('main', $data);
		} else {
			$html = "";
			$pdf = $this->request->getPost("pdf");
			$year = $this->request->getPost("year");
			$term = $this->request->getPost("term");
			$class = $this->request->getPost("class");
			$course = $this->request->getPost("course");
			$period = $this->request->getPost("period");
			$StudentModel = new StudentModel();
			$courseMdl = new CourseModel();
			$atMdl = new ActiveTermModel();
			$school_id = $this->session->get("soma_school_id");
			$active_term = $atMdl->select("id")->where("term", $term)
					->where("academic_year", $year)->where("school_id", $school_id)
					->get(1)->getRow();
			if ($active_term == null) {
				echo "invalid data, please try again later";
				die();
			}
			$builder = $courseMdl->select("courses.id,courses.title,courses.code,courses.marks")
					->join("course_records r", "courses.id=r.course and class=$class and find_in_set($term,r.term)>0")
					->join("marks m", "courses.id=m.course_id and period=$period and class_id=$class and m.term=" . $active_term->id)
					->where("courses.school_id", $this->session->get("soma_school_id"))
					->where("r.class", $class)
					->where("r.year", $year);
			$course_filter = "1=1";
			if ($course != 0) {
				//single  course
				$builder->where("courses.id", $course);
				$course_filter = "m.course_id=$course";
			}
			$builder->groupBy("courses.id");
			$builder->orderBy("courses.id");
			$courses = $builder->get()->getResultArray();
			if (count($courses) == 0) {
				echo "No course marks found on the selected period,term and academic year, please try again later";
				die();
			}
			$html .= "<table style='border: 0px' border='1'><tr><th colspan='3'></th>";
			$course_header = array();
			$course_header_code = array();
			$max_total = 0;
			$cols = 2;
			$course_ids = [];
			foreach ($courses as $item) {
				$max_total += $item['marks'];
				$html .= "<th class='rotate-45'><div><label>" . $item['title'] . "</label></div></th>";
				$course_header[] = $item['id'];
				$course_header_code[] = $item['code'];
				$course_ids[] = $item['id'];
				$cols++;
			}
			$course_ids_str = implode(",", $course_ids);
			$students = $StudentModel->select("students.id,
														  students.regno,
														  concat(students.fname,' ',students.lname) as name,
														  group_concat(m.marks) as marks,
														  CAST(sum(m.total) as float) as total1")
					->join("class_records cr", "students.id=cr.student AND cr.year=$year")
					->join("course_records r", "cr.class=r.class AND cr.year=$year")
					->join("(select distinct m.mark_type,m.student_id,m.course_id,m.period,concat(m.course_id,':',coalesce((sum(" . self::sqlMarkValue('m.marks') . "/m.outof*c.marks)/count(m.id)),0),':',c.marks) as marks,coalesce((sum(" . self::sqlMarkValue('m.marks') . "/m.outof*c.marks)/count(m.id)),0) as total from marks m
				 inner join courses c on c.id = m.course_id where m.mark_type=1 and m.period=$period and m.term={$active_term->id} and m.course_id in ($course_ids_str) group by m.student_id,m.course_id order by m.course_id) as m"
							, "students.id=m.student_id and m.course_id=r.course and $course_filter", "LEFT")
					->where("r.class", $class)
					->where("cr.status", "1")
					->where("students.status", "1")
					->where("$course_filter")
					->groupBy("students.id")
					->orderBy("total1", "DESC")
					->get()->getResultArray();

//			echo $course_ids_str.'<pre>';var_dump($students);die();
//			echo $period.'-'.$term.'-'.$class.'-'.$course_filter.'<pre><br>';var_dump($students);die();
			$ii = 1;
			$current_course = 0;
			$current_st = "";
			$smsRMdl = new SmsRecipientModel();
			$smsMdl = new SmsModel();
			if (isset($_GET['publish']) && $this->request->getGet("publish") == "sms") {
				//send marks sms
				$this->_preset();
				if (count($students) == 0) {
					return $this->response->setJSON(array("error" => lang("app.NoMarksFound")));
				}
				$sent = 0;
				$all = 0;
				foreach ($students as $student) {
					$row_total = 0;
					if ($current_st != $student['id']) {
						$current_st = $student['id'];
						$current_course = 0;//reset
					}
					$msg = lang("app.names") . ":" . $student['name'] . "\n\rPOSITION:" . $ii . "\n\r\n\r" . lang("app.marks") . ":\n\r";
					$ch = 0;
					foreach ($course_header as $h) {
						if ($current_course >= $h)//skip previous set data
							continue;
						$dts = explode(",", $student['marks']);
						foreach ($dts as $dt) {
							$dtt = explode(":", $dt);
							if ($dtt[0] == $h) {
								//column match
								$row_total += $dtt[1];
								$msg .= $course_header_code[$ch] . ":" . number_format($dtt[1]) . "/" . $dtt[2] . "\n\r";
								$current_course = $h;
								break;
							} else {
							}
						}
						$ch++;
					}
					$student['total'] = $row_total;
					$msg .= "Tot: " . number_format($student['total'] / $max_total * 100, 2) . "%";
					$sid = $smsMdl->insert(array("school_id" => $this->session->get("soma_school_id")
					, "active_term" => $this->data['active_term'], "content" => $msg, "recipient_type" => 0
					, "subject" => "Marks publishing"));
					if ($sid === false)
						return $this->response->setJSON(array("error" => lang("app.smsErr")));
					$phones = $StudentModel->get_student("students.id=" . $student['id'] . " AND (ft_phone!='' OR mt_phone!='' OR gd_phone!='')", null, "students.id,ft_phone,mt_phone,gd_phone");
					foreach ($phones as $phone) {
						$all++;
						$p = strlen(trim($phone["ft_phone"])) > 4 ? $phone["ft_phone"] : (strlen(trim($phone["mt_phone"])) > 4 ? $phone["mt_phone"] :
								(strlen(trim($phone["gd_phone"])) > 4 ? $phone["gd_phone"] : ""));
						try {
							$smsRMdl->save(array("sms_record_id" => $sid, "receiver_id" => $phone['id'], "phone" => $p, "status" => 0));
							$sent++;
						} catch (\Exception $e) {
							//future use
							return $this->response->setJSON(array("error" => "Error: " . $e));
						}
					}
					$ii++;
				}
				$param = base_url("background_process/2");
				$command = "curl $param > /dev/null &";
				exec($command);
				return $this->response->setJSON(array("success" => lang("app.beSent") . " $sent" . lang("app.over") . " $all"));
			} else {
				$html .= "<th class='rotate-45'><div><label>" . lang("app.total") . "</label></div></th>";
				$html .= "<th class='rotate-45'><div><label>" . lang("app.percentage") . "</label></div></th>";
				$html .= "</tr>";
				$html .= "<tr><td style='min-width: 60px;'><strong>" . lang("app.order") . "</strong></td><td><strong" . lang("app.regno") . "</strong></td><td><strong>" . lang("app.studentName") . "</strong></td>";
				foreach ($courses as $item) {
					$html .= "<td><strong> /" . $item['marks'] . "</strong></td>";
				}
				$html .= "<td><strong> /$max_total</strong></td>";
				$html .= "<td><strong> %</strong></td>";
				$html .= "</tr>";
				if (count($students) == 0) {
					echo "<h4>No marks found</h4>";
					die();
				}
				foreach ($students as $student) {
					$row_total = 0;
					if ($current_st != $student['id']) {
						$current_st = $student['id'];
						$current_course = 0;//reset
					}
					$marks_data = "<td> - </td>";
					$html .= "
				<tr>
				<td style='text-align: center'>" . $ii . "</td>
				<td>" . $student['regno'] . "</td>
				<td>" . $student['name'] . "</td>";
					foreach ($course_header as $h) {
						if ($current_course >= $h)//skip previous set data
							continue;
						$dts = explode(",", $student['marks']);
						foreach ($dts as $dt) {
							$dtt = explode(":", $dt);
							if ($dtt[0] == $h) {
								//column match
								$row_total += $dtt[1];
								$color = $dtt[2] / 2 > $dtt[1] ? "color:red;text-decoration:underline" : "";
								$marks_data = "<td style='$color'>" . number_format($dtt[1], 2) . "</td>";
								$current_course = $h;
								break;
							} else {
								$marks_data = "<td style='color:black'> - </td>";
							}
						}
						$html .= $marks_data;
					}
					$student['total'] = $row_total;
					$color2 = ($student['total'] / $max_total * 100) < 50 ? "color:red;text-decoration:underline" : "";
					$html .= "<td style='$color2'>" . number_format($student['total'], 2) . "</td>";
					$html .= "<td style='$color2'>" . number_format($student['total'] / $max_total * 100, 2) . "</td>";
					$html .= "</tr>";
					$ii++;
				}
				if ($pdf) {
					$this->_preset();
					$data = $this->data;
					$classMdl = new ClassesModel();
					$data["class"] = $classMdl->get_class_name($class);
					$data["period"] = $period;
					$data["content"] = $html;
					$html = view("templates/student_results", $data);
					try {
						$mask = FCPATH . "assets/templates/*.html";
						array_map('unlink', glob($mask));//clear previous cards
						$wkhtmltopdf = new Wkhtmltopdf(array('path' => FCPATH . 'assets/templates/'));
						$wkhtmltopdf->setTitle(lang("app.rtudentRsults"));
						$wkhtmltopdf->setHtml($html);
						$wkhtmltopdf->setOrientation("Landscape");
//					$wkhtmltopdf->setOptions(array("page-width" => "278px", "page-height" => "430px"));
						$wkhtmltopdf->setMargins(array("top" => 2, "left" => 10, "right" => 5, "bottom" => 2));
						$wkhtmltopdf->output(Wkhtmltopdf::MODE_EMBEDDED, "students_results_" . time() . ".pdf");
					} catch (\Exception $e) {
						echo $e->getMessage();
					}
				} else {
					echo $html;
				}
			}
		}
	}

	public
	function proclamation_list()
	{
		$this->_preset();
		//0:view field,1:view data
		$school_id = $this->session->get("soma_school_id");
		$data = $this->data;
		$data['title'] = lang("app.proclamationList");
		$data['subtitle'] = lang("app.viewMarks");
		$data['page'] = "proclamation_list";
		$acMdl = new AcademicYearModel();
		$cMdl = new ClassesModel();
		$data['classes'] = $cMdl->get_classes();
		$data['years'] = $acMdl->select('id,title')->where("school_id", $school_id)
				->orderBy("id", 'DESC')->get()->getResultArray();
		if (!isset($_POST['class'])) {
			$data['content'] = view("pages/marks/proclamation_list", $data);
			return view('main', $data);
		} else {
			$html = "";
			$pdf = strlen($this->request->getPost("pdf")) > 3;
			$year = $this->request->getPost("year");
			$term = $this->request->getPost("term");
			$class = $this->request->getPost("class");

			$atMdl = new ActiveTermModel();
			$classMdl = new ClassesModel();
			$termBuilder = $atMdl->select("id")
					->where("academic_year", $year)
					->where("school_id", $school_id);
			if ($term != 4) {
				$termBuilder->where("term", $term);
			}
			$active_term = $termBuilder->get()->getResultArray();
			if ($active_term == []) {
				echo "invalid data, please select all required data and try again";
				die();
			}
			$class_data = $classMdl->select("classes.id,l.title as level_name,l.id as level_id,
		,l.faculty_id")
					->join("departments d", "d.id=classes.department")
					->join("levels l", "l.id=classes.level")
					->where("classes.id", $class)
					->get(1)->getRow();
			if ($class_data == null) {
				echo "invalid class data, please try again later";
				die();
			}

			$active_term = array_column($active_term, 'id', 'key');
			$active_term_id = implode(",", $active_term);
			$StudentModel = new StudentModel();
			$data['page'] = "Result_record";
			$data['class_id'] = $class;
			$data['term'] = $term;
			$data['year'] = $year;
			$data['school_id'] = $school_id;
			$data['courses'] = $this->get_courses($class, 4, $year);
			$students = $StudentModel->select("students.id,students.regno,students.sex,
														students.photo,students.fname,students.dob,
														students.lname,c.id as class_id,
														c.title,d.title as department_name,
														group_concat(di.marks,':',di.term) as displine_marks,d.id as department_id,
														d.code,l.title as level_name,f.title as fac_title,
														f.type,f.abbrev as faculty_code,f.id as fac_id,
														c.level,c.id as class,cr.year,dr.decision")
					->join('class_records cr', 'cr.student=students.id')
					->join('deliberation_records dr', 'dr.studentId=students.id', 'left')
					->join('classes c', 'c.id=cr.class')
					->join('departments d', 'd.id=c.department')
					->join('levels l', 'l.id=c.level')
					->join('faculty f', 'f.id=d.faculty_id')
					->join('schools sk', 'sk.id=students.school_id')
					// ->join("active_term at", "at.id=sk.active_term")
					->join("(select sum(di.marks) as marks,at.term,di.active_term,di.student_id from disciplines di inner join active_term as at
			ON at.id = di.active_term where di.school_id={$school_id} AND di.active_term in ($active_term_id) group by di.active_term,di.student_id) as di", 'di.student_id=students.id', 'LEFT')
					->where("c.school_id", $school_id)
					// ->where("sk.active_term", $active_term->id)
//				->where("dr.id", null)
					->where("cr.status", "1")
					->where("c.id", $class)
					->where("cr.year", $year)
					->orderBy("students.fname", "ASC")
					->groupBy('students.id')
//			->limit(2)
					->get()->getResultArray();
			$data['students'] = $students;
			$data["class"] = $classMdl->get_class_name($class);
			$data["content"] = $html;
			$data["pdf"] = $pdf;
			$html = view("pages/marks/proclamation_list", $data);
//			echo $html;die();
			if ($pdf) {
				try {
					$mask = FCPATH . "assets/templates/*.html";
					array_map('unlink', glob($mask));//clear previous cards
					$wkhtmltopdf = new Wkhtmltopdf(array('path' => FCPATH . 'assets/templates/'));
					$wkhtmltopdf->setTitle(lang("app.rtudentRsults"));
					$wkhtmltopdf->setHtml(utf8_decode($html));
					$wkhtmltopdf->setOrientation("Landscape");
//					$wkhtmltopdf->setOptions(array("page-width" => "278px", "page-height" => "430px"));
					$wkhtmltopdf->setMargins(array("top" => 2, "left" => 10, "right" => 5, "bottom" => 2));
					$wkhtmltopdf->output(Wkhtmltopdf::MODE_EMBEDDED, "proclamation_list_" . time() . ".pdf");
				} catch (\Exception $e) {
					echo $e->getMessage();
				}
			} else {
				$data['content'] = $html;
				return view('main', $data);
			}

		}
	}

	public
	function get_student_marks($mt, $ct = '', $class, $course, $period = 0, $term = null, $yearId = null)
	{
		$this->_preset();
		$active_term = $this->data['active_term'];
		$year = $yearId ?? $this->data['academic_year'];
		if (in_array($this->session->get('soma_post'), [1, 3]) && $term != null) {
			$atMdl = new ActiveTermModel();
			$at_data = $atMdl->select('id')
					->where('academic_year', $year)
					->where('term', $term)
					->get(1)
					->getRow();
			if ($at_data == null) {
				echo "<h3>" . lang('app.InvalidDataSupplied') . "</h3>";
				die();
			}
			$active_term = $at_data->id;
		}
		if ((int) $period > 0 && $this->isPeriodLocked($active_term, $period)) {
			echo '<div class="alert alert-danger" style="margin:1rem;">'
				. '<strong>Period ' . (int) $period . ' is locked.</strong> '
				. 'Marks cannot be entered or changed until the school admin unlocks this period in School settings.'
				. '</div>';
			die();
		}
		if ($ct == "undefined") {
			$ct = '';
		}
		$StudentModel = new StudentModel();
		$html_script = "";
		$html = '<table style="width: 100%;" id="marks_table"
						   class="table table-hover table-striped table-bordered"
						   role="grid" aria-describedby="example_info">
						<thead>
						<tr role="row" style="background-color: #0ba360;color: white;">
							<th>' . lang("app.reg") . '</th>
							<th>' . lang("app.studentName") . '</th>';
		if ($mt == 4) {
			//cat and exam
			$marks_sql_cat = "select student_id,m.mark_type,m.created_by,
														  m.marks,
														  m.outof,
														  m.cat_type,
														  m.id,
														  m.examDate from marks m where m.mark_type=1  AND m.course_id=$course AND m.class_id=$class AND m.period=$period AND m.term={$active_term}";
			$marks_sql_exam = "select student_id,m.mark_type,m.created_by,
														  m.marks,
														  m.outof,
														  m.cat_type,
														  m.id,
														  m.examDate from marks m where m.mark_type=2  AND m.course_id=$course AND m.class_id=$class AND m.period=$period AND m.term={$active_term}";
			$cats = $StudentModel->select("students.id,
														  students.regno,
														  concat(students.fname,' ',students.lname) as name,
														  coalesce(m.mark_type,'') as mark_type,
														  coalesce(m.marks,'') as marks,
														  coalesce(m.outof,'') as outof,
														  coalesce(m.cat_type,'') as cat_type,
														  coalesce(m.id,'') as mark_id,
														  coalesce(m.examDate,'') as examDate,
														  coalesce(mex.marks,'') as marks_ex,coalesce(mex.outof,'') as outof_ex,
														  coalesce(mex.id,'') as mark_id_ex")
					->join("class_records cr", "students.id=cr.student")
					->join("course_records r", "cr.class=r.class")
					->join("($marks_sql_cat) as m", "students.id=m.student_id", "LEFT")
					->join("($marks_sql_exam) as mex", "students.id=mex.student_id", "LEFT")
					->where("cr.status", "1")
					->where("cr.year", $year)
					->where("r.class", $class)
					->where("students.status", 1)
					->groupBy("students.id")
					->orderBy("students.fname")
					->orderBy("students.lname")
					->get()->getResultArray();
//			$exams = $StudentModel->select("coalesce(m.marks,'') as marks,coalesce(m.outof,'') as outof,
//														  coalesce(m.id,'') as mark_id")
//				->join("class_records cr", "students.id=cr.student")
//				->join("course_records r", "cr.class=r.class")
//				->join("($marks_sql_exam) as m", "students.id=m.student_id", "LEFT")
//				->where("r.class", $class)
//				->where("cr.year", $year)
//				->groupBy("students.id")
//				->orderBy("students.regno")
//				->get()->getResultArray();


//			$result = array_column($exams, 'marks');
//			var_dump($result); die();
			$html .= "<th>" . lang("app.cat") . " /" . $cats[0]['outof'] . "</th><th>" . lang("app.exam") . " /" . $cats[0]['outof_ex'] . "</th>";
			$html .= '</tr>
						</thead><tbody>';
			$i = 0;
			$filledIndex = 0;
			foreach ($cats as $student) {
				if ($student['outof'] != null && $filledIndex == 0) {
					$filledIndex = $i;
				}
				$dispC = self::displayMarkEntry($student['marks'], $student['mark_id']);
				$dispE = self::displayMarkEntry($student['marks_ex'], $student['mark_id_ex']);
				$html .= "
				<tr>
				<td>" . $student['regno'] . "<input type='hidden' value='" . $student['mark_id'] . "' name='marks_id[]' class='mark_id'>
				<input type='hidden' value='" . $student['mark_id_ex'] . "' name='marks_id1[]' class='mark_id'></td>
				<td>" . $student['name'] . "<input type='hidden' value='" . $student['id'] . "' name='discId[]'></td>
				<td><input type='text'  name='marksC[]' class='form-control marks-entry-input' value='" . $dispC . "' placeholder='-'  data-parsley-le=\"#outofmarks\" data-parsley-le-message=\"" . lang("app.shouldBeLess") . "\"></td>
				<td><input type='text'  name='marksE[]' class='form-control marks-entry-input' value='" . $dispE . "' placeholder='-'  data-parsley-le=\"#outofmarks\" data-parsley-le-message=\"" . lang("app.shouldBeLess") . "\"></td>";
				$i++;
			}
			$html .= '</tbody>
					</table>';
			if ($filledIndex != 0) {
				$date = date("Y-m-d", $cats[$filledIndex]['examDate']);
				$outofEx = empty($cats[$filledIndex]['outof_ex']) ? 0 : $cats[$filledIndex]['outof_ex'];
				$total = ($cats[$filledIndex]['outof'] + $outofEx) / 2;
				$html_script .= "<script>
//				$('[type=\"submit\"]').prop(\"disabled\",false);
				$('#outofmarks').val(" . $total . ");
				$('#btn-del-marks').prop('disabled',false);
//				$('#outofmarks').prop('readonly',true)
				$('#examDate').val('" . $date . "').prop('readonly',true);
</script>";
			}
			if (count($cats) > 0) {
				$html_script .= "<script>
				$('[type=\"submit\"]').prop(\"disabled\",false);$('#marks_table').dataTable({paging: false});
</script>";
			}
		} else if ($mt == 2) {
			$marks_sql = "select student_id,m.mark_type,m.created_by,
														  m.marks,
														  m.outof,
														  m.cat_type,
														  m.id,
														  m.examDate from marks m where m.mark_type=2 AND m.course_id=$course AND m.class_id=$class AND m.term={$active_term}";
			$students = $StudentModel->select("students.id,
														  students.regno,
														  concat(students.fname,' ',students.lname) as name,
														  coalesce(m.mark_type,'') as mark_type,
														  coalesce(m.marks,'') as marks,
														  coalesce(m.outof,'') as outof,
														  coalesce(m.cat_type,'') as cat_type,
														  coalesce(m.id,'') as mark_id,
														  coalesce(m.examDate,'') as examDate")
					->join("class_records cr", "students.id=cr.student")
					->join("course_records r", "cr.class=r.class")
					->join("($marks_sql) as m", "students.id=m.student_id", "LEFT")
					->where("r.class", $class)
					->where("students.status", "1")
					->where("cr.year", $year)
					->groupBy("students.id")
					->orderBy("students.fname")
					->orderBy("students.lname")
					->get()->getResultArray();
			$html .= "<th>" . lang("app.marks") . "</th>";
			$outof = "";
			$required = $mt == 9 ? "" : "required";
			foreach ($students as $student) {
				if (strlen($student['outof']) > 0 && strlen($outof) == 0) {
					$outof = $student['outof'];
					$date = $student['examDate'] == '' ? date('Y-m-d') : date("Y-m-d", $student['examDate']);
				}
				$disp = self::displayMarkEntry($student['marks'], $student['mark_id']);
				$html .= "
				<tr>
				<td>" . $student['regno'] . "<input type='hidden' value='" . $student['mark_id'] . "' name='marks_id[]' class='mark_id'></td>
				<td>" . $student['name'] . "<input type='hidden' value='" . $student['id'] . "' name='discId[]'></td>
				<td><input type='text'  name='marks[]' class='form-control marks-entry-input' value='" . $disp . "' placeholder='-'  data-parsley-le=\"#outofmarks\" data-parsley-le-message=\"" . lang("app.shouldBeLess") . "\"></td>
				</tr>
				";
			}
			$html .= '</tbody>
					</table>';
			$html .= '</tr>
						</thead><tbody>';
			if (count($students) > 0 && $outof != '') {
				$html_script .= "<script>
//				$('[type=\"submit\"]').prop(\"disabled\",false);
//				$('#outofmarks').val(" . $outof . ");$('#outofmarks').prop('readonly',true);
				$('#outofmarks').val(" . $outof . ");$('#outofmarks');
				$('#btn-del-marks').prop('disabled',false);
				$('#examDate').val('" . $date . "').prop('readonly',true);
</script>";
			}
			if (count($students) > 0) {
				$html_script .= "<script>
				$('[type=\"submit\"]').prop(\"disabled\",false);$('#marks_table').dataTable({paging: false});
</script>";
			}
		} else {
			$marks_sql = "select student_id,m.mark_type,m.created_by,
														  m.marks,
														  m.outof,
														  m.cat_type,
														  m.id,
														  m.examDate from marks m where m.mark_type=$mt AND m.cat_type='$ct' AND m.course_id=$course AND m.class_id=$class AND m.period=$period AND m.term={$active_term}";
			$students = $StudentModel->select("students.id,
														  students.regno,
														  concat(students.fname,' ',students.lname) as name,
														  coalesce(m.mark_type,'') as mark_type,
														  coalesce(m.marks,'') as marks,
														  coalesce(m.outof,'') as outof,
														  coalesce(m.cat_type,'') as cat_type,
														  coalesce(m.id,'') as mark_id,
														  coalesce(m.examDate,'') as examDate")
					->join("class_records cr", "students.id=cr.student")
					->join("course_records r", "cr.class=r.class")
					->join("($marks_sql) as m", "students.id=m.student_id", "LEFT")
					->where("r.class", $class)
					->where("students.status", "1")
					->where("cr.year", $year)
					->groupBy("students.id")
					->orderBy("students.fname")
					->orderBy("students.lname")
					->get()->getResultArray();
			$html .= "<th>" . lang("app.marks") . "</th>";
			$outof = "";
			$required = $mt == 9 ? "" : "required";
			foreach ($students as $student) {
				if (strlen($student['outof']) > 0 && strlen($outof) == 0) {
					$outof = $student['outof'];
					$date = $student['examDate'] == '' ? date('Y-m-d') : date("Y-m-d", $student['examDate']);
				}
				$disp = self::displayMarkEntry($student['marks'], $student['mark_id']);
				$html .= "
				<tr>
				<td>" . $student['regno'] . "<input type='hidden' value='" . $student['mark_id'] . "' name='marks_id[]' class='mark_id'></td>
				<td>" . $student['name'] . "<input type='hidden' value='" . $student['id'] . "' name='discId[]'></td>
				<td><input type='text'  name='marks[]' class='form-control marks-entry-input' value='" . $disp . "' placeholder='-'  data-parsley-le=\"#outofmarks\" data-parsley-le-message=\"" . lang("app.shouldBeLess") . "\"></td>
				</tr>
				";
			}
			$html .= '</tbody>
					</table>';
			$html .= '</tr>
						</thead><tbody>';
			if (count($students) > 0 && $outof != '') {
				$html_script .= "<script>
//				$('[type=\"submit\"]').prop(\"disabled\",false);
//				$('#outofmarks').val(" . $outof . ");$('#outofmarks').prop('readonly',true);
				$('#outofmarks').val(" . $outof . ");$('#outofmarks');
				$('#btn-del-marks').prop('disabled',false);
				$('#examDate').val('" . $date . "').prop('readonly',true);
</script>";
			}
			if (count($students) > 0) {
				$html_script .= "<script>
				$('[type=\"submit\"]').prop(\"disabled\",false);$('#marks_table').dataTable({paging: false});
</script>";
			}
		}
		if (isset($_GET['pdf'])) {
			try {
				$data = $this->data;
				$classMdl = new ClassesModel();
				$courseMdl = new CourseModel();
				$classes = $classMdl->select('concat(l.title," ",d.code," ",classes.title) as classe,concat(s.fname," ",s.lname) as mentor_name')
						->join('departments d', 'd.id=classes.department')
						->join('levels l', 'l.id=classes.level')
						->join("staffs s", "s.id=classes.mentor", "LEFT")
						->where('classes.id', $class)->get()->getRow();

				$courses = $courseMdl->select('title')
						->where('courses.id', $course)->get()->getRow();
				$data['course'] = $courses->title;
				$data['class'] = $classes->classe;
				$data['teacher'] = $classes->mentor_name;
				$data['content'] = $html;
				$html = view("pages/reports/marks_export", $data);
				$mask = FCPATH . "assets/templates/*.html";
				array_map('unlink', glob($mask));//clear previous cards
				$wkhtmltopdf = new Wkhtmltopdf(array('path' => FCPATH . 'assets/templates/'));
				$wkhtmltopdf->setTitle(lang("app.studentMarksreport"));
				$wkhtmltopdf->setHtml(utf8_decode($html));
				$wkhtmltopdf->setPageSize("A4");
				$wkhtmltopdf->setOrientation("portrait");
//					$wkhtmltopdf->setOptions(array("page-width" => "278px", "page-height" => "430px"));
				$wkhtmltopdf->setMargins(array("top" => 1, "left" => 0, "right" => 0, "bottom" => 1));
				$wkhtmltopdf->output(Wkhtmltopdf::MODE_EMBEDDED, "student_marks_report" . time() . ".pdf");
			} catch (\Exception $e) {
				echo $e->getMessage();
			}
		} else {
			echo $html . $html_script;
		}
	}

	public
	function discipline_record()
	{
		$this->_preset();
		set_time_limit(0);
		ini_set("memory_limit", -1);
		ini_set("max_execution_time", -1);
		$data = $this->data;
		$classMdl = new ClassesModel();
		$SchoolModel = new SchoolModel();
		$data['title'] = lang("app.disciplineRecord");
		$data['classes'] = $classMdl->select("classes.id,classes.title,d.title as department_name,d.code,l.title as level_name
		,f.type,f.abbrev as faculty_code,concat(s.fname,' ',s.lname) as mentor_name,s.id as idstf")
				->join("departments d", "d.id=classes.department")
				->join("levels l", "l.id=classes.level")
				->join("faculty f", "f.id=d.faculty_id")
				->join("staffs s", "s.id=classes.mentor", "LEFT")
				->where("classes.school_id", $this->session->get("soma_school_id"))
				->get()->getResultArray();
		$data['activeTerm'] = $SchoolModel->select("at.term,at.id")
				->join("active_term at", "at.id=schools.active_term")
				->where("at.school_id", $this->session->get("soma_school_id"))
				->get()->getRowArray();
		$data['subtitle'] = lang("app.disciplineRecord");
		$data['page'] = "discipline_record";
		$data['content'] = view("pages/discipline_record", $data);
		return view('main', $data);
	}

	public
	function displine_single_student($id)
	{
		$this->_preset();
		$data = $this->data;
		$StudentModel = new StudentModel();
		$builder = $StudentModel->select('students.*,d.marks as removed, concat(s.fname,\' \',s.lname) as lecturer')
				->join('disciplines d', 'd.student_id=students.id')
				->join('staffs s', 'd.created_by=s.id')
				->where('students.regno', $id)
				->where('d.active_term', $data['active_term'])
				->where("d.school_id", $this->session->get("soma_school_id"))
				->get()->getResultArray();
		$i = 1;
		foreach ($builder as $student) {
			$date = $student['created_at'];
			$remain = $data['discipline_max'];
			echo "
				<tr>
				<td>" . $i . "</td>
				<td>" . $date . "</td>
				<td>" . $student['removed'] . "</td>
				<td>" . $student['lecturer'] . "</td>
				</tr>
				";
			$i++;
		}
	}

	public
	function library_single_student($student)
	{
		$this->_preset();
		$data = $this->data;
		$bookModel = new BookModel();
		$books = $bookModel->select("books.id,books.title,books.author,br.id as record_id,br.borrow_date,br.return_due_date,br.status,br.return_date,concat(s.fname,' ',s.lname) as student")
				->join("book_records br", "br.book_id=books.id", "LEFT")
				->join("students s", "s.id=br.student_id")
				->where("books.school_id", $this->session->get("soma_school_id"))
				->where("s.id", $student)
				->get()->getResultArray();
		$i = 1;
		foreach ($books as $book) {
			$bdate = date('m-d-Y', $book['borrow_date']);
			$rddate = date('m-d-Y', $book['return_due_date']);
			$rdate = $book['return_date'];
			echo "
				<tr>
				<td>" . $i . "</td>
				<td>" . $book['title'] . "</td>
				<td>" . $book['author'] . "</td>
				<td>" . $bdate . "</td>
				<td>" . $rddate . "</td>
				<td>" . $this->get_returndate($rdate) . "</td>
				<td>" . $this->get_status($book['status']) . "</td>
				</tr>
				";
			$i++;
		}
	}

	public
	function get_status($val)
	{
		if ($val == 0) {
			return "<i style='color: darkred'>" . lang("app.borrowed") . "</i>";
		} else {
			return "<i style='color: darkgreen'>" . lang("app.returned") . "</i>";
		}
	}

	public
	function get_returndate($date)
	{
		if ($date == 0) {
			return " ";
		} else {
			return date('d-m-Y', $date);
		}
	}

	public
	function permission_single_student($id)
	{
		$this->_preset();
		$data = $this->data;
		$StudentModel = new StudentModel();
		$builder = $StudentModel->select('students.*,p.destination,p.reason,p.leave_time,p.return_time, concat(s.fname,\' \',s.lname) as lecturer')
				->join('permission p', 'p.student_id=students.id')
				->join('staffs s', 'p.created_by=s.id')
				->where('students.regno', $id)
//			->where('p.active_term',$data['active_term'])
				->get()->getResultArray();
		$i = 1;
		foreach ($builder as $student) {
			$date = $student['created_at'];
			echo "
				<tr>
				<td>" . $i . "</td>
				<td>" . $date . "</td>
				<td>" . $student['destination'] . "</td>
				<td>" . $student['reason'] . "</td>
				<td>" . $student['leave_time'] . "</td>
				<td>" . $student['return_time'] . "</td>
				<td>" . $student['lecturer'] . "</td>
				</tr>
				";
			$i++;
		}
	}

	public
	function class_record_single_student($id)
	{
		$classe = new ClassesModel();
		$classes = $classe->select("classes.id,classes.title,d.title as department_name,d.code,l.title as level_name
											,f.type,f.abbrev as faculty_code,ac.title as year")
				->join("departments d", "d.id=classes.department")
				->join("levels l", "l.id=classes.level")
				->join("faculty f", "f.id=d.faculty_id")
				->join("class_records cr", "cr.class=classes.id")
				->join("academic_year ac", "cr.year=ac.id")
				->join("students s", "s.id=cr.student")
				->where("classes.school_id", $this->session->get("soma_school_id"))
				->where("s.regno", $id)
				->groupBy('cr.year')
				->get()->getResultArray();

		$i = 1;
		foreach ($classes as $student) {
			echo "
				<tr>
				<td>" . $i . "</td>
				<td>" . $student['level_name'] . " " . $student['title'] . " " . $student['code'] . "</td>
				<td>" . $student['year'] . "</td>
				</tr>
				";
			$i++;
		}
	}

// single student result slip not completed
	public
	function student_result()
	{
		$this->_preset();
		set_time_limit(0);
		ini_set("memory_limit", -1);
		ini_set("max_execution_time", -1);
		$data = $this->data;
		$classMdl = new ClassesModel();
		$data['title'] = lang("app.resultRecord");
		$data['classes'] = $classMdl->select("classes.id,classes.title,d.title as department_name,d.code,l.title as level_name
		,f.type,f.abbrev as faculty_code,concat(s.fname,' ',s.lname) as mentor_name,s.id as idstf")
				->join("departments d", "d.id=classes.department")
				->join("levels l", "l.id=classes.level")
				->join("faculty f", "f.id=d.faculty_id")
				->join("staffs s", "s.id=classes.mentor", "LEFT")
				->where("classes.school_id", $this->session->get("soma_school_id"))
				->get()->getResultArray();
		$data['subtitle'] = lang("app.resultRecord");
		$data['page'] = "Result_record";
		$data['content'] = view("pages/reports/marks_report", $data);
		return view('main', $data);
	}


	public
	function get_periodic_slip()
	{
		ini_set('memory_limit', '4096M');
		session_write_close();

		$pdf = $this->request->getPost("pdf");
		$year = $this->request->getPost("year");
		$term = $this->request->getPost("term");
		$class = $this->request->getPost("class");
		$period = $this->request->getPost("period");

		$this->_preset();
		$data = $this->data;
		$data['title'] = lang("app.resultRecord");
		$data['subtitle'] = lang("app.resultRecord");
		$data['year'] = $year;
		$data['period'] = $period;
		$data['term'] = $term;
		$atMdl = new ActiveTermModel();
		$school_id = $this->session->get("soma_school_id");
		$active_term = $atMdl->select("id")->where("term", $term)
				->where("academic_year", $year)->where("school_id", $school_id)
				->get(1)->getRow();
		if ($active_term == null) {
			echo "invalid data, please try again later";
			die();
		}
		$classMdl = new ClassesModel();
		$classVerify = $classMdl->select("f.abbrev")
				->join("departments d", "d.id=classes.department")
				->join("faculty f", "f.id=d.faculty_id")
				->where("classes.id", $class)->get()->getRow();
		$StudentModel = new StudentModel();
		$gradesMdl = new GradeModel();
		$data['page'] = "Result_record";
		$data['class_id'] = $class;
		$data['school_id'] = $school_id;
		$data['grades'] = $gradesMdl->select("color_title,max_point,min_point,color")->where("school_id", $school_id)->get()->getResultArray();
		$students = $StudentModel->select("students.id,students.regno,
														students.photo,students.fname,students.dob,
														students.lname,c.id as class_id,
														c.title,d.title as department_name,
														sum(di.marks) as displine_marks,d.id as department_id,
														d.code,l.title as level_name,f.title as fac_title,
														f.type,f.abbrev as faculty_code,f.id as fac_id,
														c.level,c.id as class,cr.year")
				->join('class_records cr', 'cr.student=students.id')
				->join('classes c', 'c.id=cr.class')
				->join('departments d', 'd.id=c.department')
				->join('levels l', 'l.id=c.level')
				->join('faculty f', 'f.id=d.faculty_id')
				->join('schools sk', 'sk.id=students.school_id')
				// ->join("active_term at", "at.id=sk.active_term")
				->join('disciplines di', 'di.student_id=students.id AND di.active_term = ' . $active_term->id, 'LEFT')
				->where("c.school_id", $school_id)
				// ->where("sk.active_term", $active_term->id)
				->where("cr.status", "1")
				->where("c.id", $class)
				->where("cr.year", $year)
				->orderBy("students.fname", "ASC")
				->groupBy('students.id')
				->get()->getResultArray();
		$records = array();
		// var_dump($students);echo $active_term->id;die();
		$a = 0;
		$MarksModel = new MarksModel();
		foreach ($students as $student) {
			$records[$a] = $student;
			$tot = 0;
			foreach ($this->get_courses($student['class'], $term, $year) as $core) {
				$core['result'] = $MarksModel->select("(sum(" . self::sqlMarkValue('marks.marks') . "/marks.outof*c.marks)/count(marks.id)) as marks")
						->join("active_term at", "at.id=marks.term")
						->join("courses c", "c.id=marks.course_id")
						->where("marks.course_id", $core['id'])
						->where("at.term", $term)
						->where("at.academic_year", $year)
						->where("marks.mark_type", 1)//cat
						->where("marks.period", $period)
						->where("marks.student_id", $student['id'])
						->get()->getRowArray();
				if (!is_null($core['result']['marks'])) {
					$tot += $core['result']['marks'];
				}

				$records[$a]['courses'][] = $core;
			}
			$records[$a]['total'] = $tot;
			$a++;
		}
		usort($records, "cmp");
		// echo '<pre>';var_dump($records);die();
		$data['students'] = $records;
		$fact = count($data['students']) > 0 ? $data['students'][0]["fac_id"] : 0;
		$view = "";
//		$pdf = false;
		$data['pdf'] = $pdf;
//		if (isset($_GET['pdf'])) {
//			$pdf = true;
//			$data['pdf'] = true;
//		}
		/** 28 BRIGHT STARS ACADEMY FOUNDATION */
		$view = view("pages/reports/student_period_report", $data);
		if ($this->session->get("soma_school_id") == 28 && $classVerify->abbrev == 'Nursery') {

			$view = view("pages/reports/custom/bright_stars", $data);
		}
		if ($this->session->get("soma_school_id") == 52) {

			$view = view("pages/reports/custom/cyungo_periodic_report", $data);
		}
		if ($pdf) {
			/**
			 * List of customized school report
			 */
			// if(in_array($school_id, [28, 30])){
			// 	// echo $view;
			// 	// die();
			// 	header("Contet-Type: application/pdf");
			// 	$mpdf = new \Mpdf\Mpdf(['format' => 'a4', 'orientation' => 'P', 'mode' => 'utf-8']);
			// 	// $mpdf->AddPage();
			// 	$mpdf->WriteHTML($view);
			// 	$mpdf->Output();

			// 	die();
			// }
			// die($view);
			$html = $view;
			try {
				$mask = FCPATH . "assets/templates/*.html";
				array_map('unlink', glob($mask));//clear previous cards
				$wkhtmltopdf = new Wkhtmltopdf(array('path' => FCPATH . 'assets/templates/'));
				$wkhtmltopdf->setTitle(lang("app.rtudentProgressReport"));
				$wkhtmltopdf->setHtml(utf8_decode($html));
				$wkhtmltopdf->setPageSize("A4");
				$wkhtmltopdf->setOrientation("portrait");
				// $wkhtmltopdf->setOptions(array("page-width" => "278px", "page-height" => "430px"));
				$wkhtmltopdf->setMargins(array("top" => 2, "left" => 2, "right" => 2, "bottom" => 2));
				$wkhtmltopdf->output(Wkhtmltopdf::MODE_EMBEDDED, "student_periodic_report" . time() . ".pdf");
			} catch (\Exception $e) {
				echo $e->getMessage();
			}
		} else {
			$data['content'] = $view;
			return view('main', $data);
		}
	}

	public
	function student_report_slip($class = null, $year = null, $term = null, $pdf = 0)
	{
		// var_dump($_GET); die();
		ini_set('memory_limit', '4096M');
		session_write_close();
		if ($class == null) {
			$class = $_GET['class'];
			$term = $_GET['term'];
			$year = $_GET['year'];
		}


		$this->_preset();
		$school_id = $this->session->get("soma_school_id");
		$classModel = new ClassesModel();
		$classRow = $classModel->select('classes.id, f.id AS fac_id')
				->join('departments d', 'd.id = classes.department')
				->join('faculty f', 'f.id = d.faculty_id')
				->where('classes.id', $class)
				->get()->getRow();
		$fact = $classRow ? (int) $classRow->fac_id : 0;
		$isTvet = !in_array($fact, [1, 2, 3, 19], true);
		$useWdaNewFormat = $isTvet && !in_array((int) $school_id, [52], true);

		if ($useWdaNewFormat) {
			$pdfMode = isset($_GET['pdf']);
			return $this->renderWdaProgressReport($class, $year, $term, $pdfMode);
		} else {
			// die("Stoped!");
			$data = $this->data;
			$data['title'] = lang("app.resultRecord");
			$data['subtitle'] = lang("app.resultRecord");
			$data['year'] = $year;
			$data['term'] = $term;

			/**
			 *
			 * Here We start some position information
			 */

			$student_cat_marks = [];
			$student_exam_marks = [];
			$student_total_marks = [];

			$atMdl = new ActiveTermModel();
			$school_id = $this->session->get("soma_school_id");
			if ($term == 4) {
				//annual report
				$active_term = $atMdl->select("id")
						->where("academic_year", $year)->where("school_id", $school_id)
						->get()->getResultArray();
				if ($active_term == []) {
					echo "invalid data, please try again later";
					die();
				}
				$active_term = array_column($active_term, 'id', 'key');
				$active_term_id = implode(",", $active_term);
			} else {
				$active_term = $atMdl->select("id")->where("term", $term)
						->where("academic_year", $year)->where("school_id", $school_id)
						->get(1)->getRow();
				if ($active_term == null) {
					echo "invalid data, please try again later";
					die();
				}
				$active_term_id = $active_term->id;
			}

			$StudentModel = new StudentModel();
			$data['page'] = "Result_record";
			$data['class_id'] = $class;
			$data['school_id'] = $school_id;
			$students = $StudentModel->select("students.id,students.regno,
															students.photo,students.fname,students.dob,
															students.lname,c.id as class_id,
															c.title,d.title as department_name,
															group_concat(di.marks,':',di.term) as displine_marks,d.id as department_id,
															d.code,l.id as level_id,l.title as level_name,f.title as fac_title,
															f.type,f.abbrev as faculty_code,f.id as fac_id,
															c.level,c.id as class,cr.year,dr.decision")
					->join('class_records cr', 'cr.student=students.id')
					->join('deliberation_records dr', 'dr.studentId=students.id', 'left')
					->join('classes c', 'c.id=cr.class')
					->join('departments d', 'd.id=c.department')
					->join('levels l', 'l.id=c.level')
					->join('faculty f', 'f.id=d.faculty_id')
					->join('schools sk', 'sk.id=students.school_id')
					// ->join("active_term at", "at.id=sk.active_term")
					->join("(select sum(di.marks) as marks,at.term,di.active_term,di.student_id from disciplines di inner join active_term as at
				ON at.id = di.active_term where di.school_id={$school_id} group by di.active_term,di.student_id) as di", 'di.student_id=students.id AND di.active_term in (' . $active_term_id . ')', 'LEFT')
					->where("c.school_id", $school_id)
					// ->where("sk.active_term", $active_term->id)
					->where("cr.status", "1")
					->where("c.id", $class)
					->where("cr.year", $year)
					->orderBy("students.fname", "ASC")
					->groupBy('students.id')
					->get()->getResultArray();
			$records = array();
			// echo "<pre>";var_dump($students);die();
			$a = 0;
			$positions = [];
			foreach ($students as $student) {
				$records[$a] = $student;
				$tot = 0;
				$tot1 = 0;
				$tot2 = 0;
				$tot3 = 0;

				$student_cat_info = null;
				$student_exam_info = null;
				foreach ($this->get_courses($student['class'], $term, $year) as $core) {
					$result = $this->__result($core['id'], $student['id'], $term, $year);
					if ($term != 4) {
						$core['result'] = ['marks' => $result['cat'][$term] ?? null
							, 'exam_marks' => $result['exam'][$term] ?? null
						];
					} else {
						$core['result'] = $result;
						if (in_array('1', explode(',', $core['term1']))) {
							$tot1 += $result['cat'][1] ?? null;
							$tot1 += $result['exam'][1] ?? null;
						}
						if (in_array('2', explode(',', $core['term1']))) {
							$tot2 += $result['cat'][2] ?? null;
							$tot2 += $result['exam'][2] ?? null;
						}
						if (in_array('3', explode(',', $core['term1']))) {
							$tot3 += $result['cat'][3] ?? null;
							$tot3 += $result['exam'][3] ?? null;
						}

					}
					// var_dump($result); die();
					if (count($result['cat']) != 0) {
						$tot += marksTotal($result['cat']);
						$student_cat_info += marksTotal($result['cat']);
					}

					if (count($result['exam']) != 0) {
						$tot += marksTotal($result['exam']);
						// $tot += $core['result']['exam_marks'];
						$student_exam_info += marksTotal($result['exam']);
					}
					$records[$a]['courses'][] = $core;
				}
				// var_dump("<pre>", $records); die();
				$records[$a]['total'] = $tot;
				$student_cat_marks[$student['id']] = $student_cat_info;
				$student_exam_marks[$student['id']] = $student_exam_info;
				$student_total_marks[$student['id']] = $tot;
				if ($term == 4) {
					$positions['1'][] = ['total' => $tot1, 'student' => $student['id']];
					$positions['2'][] = ['total' => $tot2, 'student' => $student['id']];
					$positions['3'][] = ['total' => $tot3, 'student' => $student['id']];
				}
				$a++;
			}
			// var_dump("<pre>", $student_cat_marks, $student_exam_marks, $student_total_marks); die();

			/**
			 * Make sure we find position
			 */
			$position_data = [];
			$position_student_data = [termToStr($term) => [
					"cat" => $student_cat_marks,
					"exam" => $student_exam_marks,
					"total" => $student_total_marks,
			]];
			$this->get_position_with_same_number_when_marks_are_equal($position_student_data, $position_data);
			// var_dump("<pre>", $position_data); die();
			$data['my_position'] = $position_data;
			usort($records, "cmp");
			if ($term == 4) {
				$records['terms_total']['1'] = sortTermsTotal($positions['1']);
				$records['terms_total']['2'] = sortTermsTotal($positions['2']);
				$records['terms_total']['3'] = sortTermsTotal($positions['3']);
			}
			// echo '<pre>';var_dump($records);die();
			$data['students'] = $records;
			$fact = count($data['students']) > 0 ? $data['students'][0]["fac_id"] : 0;
			$gradeMdl = new GradeModel();
			$data['grades'] = $gradeMdl->select("color_title,max_point,min_point,color")->where("faculty_id", $fact)->where("school_id", $school_id)->get()->getResultArray();
			if (isset($_GET['publish']) && $this->request->getGet("publish") == "sms") {
				$smsRMdl = new SmsRecipientModel();
				$smsMdl = new SmsModel();
				//send marks sms
				$this->_preset();
				if (count($students) == 0) {
					return $this->response->setJSON(array("error" => lang("app.NoMarksFound")));
				}
				$sent = 0;
				$ii = 1;
				$all = 0;
				foreach ($data['students'] as $student) {
					if (!isset($student['id'])) {
						//positional data
						break;
					}
					$max_total = 0;
					$totalCatColumn = 0;
					$totalExamColumn = 0;
					foreach ($student['courses'] as $core) {
						$datas = $core['result'];
						if (isset($datas['cat'])) {
							$totalCatColumn += $datas['cat'][1] ?? 0;
							$totalCatColumn += $datas['cat'][2] ?? 0;
							$totalCatColumn += $datas['cat'][3] ?? 0;
							$totalExamColumn += $datas['exam'][1] ?? 0;
							$totalExamColumn += $datas['exam'][2] ?? 0;
							$totalExamColumn += $datas['exam'][3] ?? 0;
							if (in_array('1', explode(',', $core['term1']))) {
								$max_total += $core['marks'];
							}
							if (in_array('2', explode(',', $core['term1']))) {
								$max_total += $core['marks'];
							}
							if (in_array('3', explode(',', $core['term1']))) {
								$max_total += $core['marks'];
							}

						} else {
							$totalCatColumn += $datas['marks'];
							$totalExamColumn += $datas['exam_marks'];
							$max_total += $core['marks'];
						}

					}
					$decision = '';
					if ($term == 4) {
						if (!empty($student['decision'])) {
							$decision = 'Decision:' . verdictText($student['decision']);
						} else {
							$decision = 'Decision: PENDING...';
						}
					}
					$tot = number_format((($totalCatColumn + $totalExamColumn) * 100 / ($max_total * 2)), 1);
					$msg = lang("app.names") . ":" . $student['fname'] . " " . $student['lname'] . " \n\rPOSITION:" . $ii . " out of "
							. count($data['students']) . "\n\r" . lang("app.percentage") . ": " . $tot . "% \n\r" . $decision;
					$ii++;
					// echo $msg."\n\r";continue;
					try {
						$sid = $smsMdl->insert(array("school_id" => $this->session->get("soma_school_id")
						, "active_term" => $this->data['active_term'], "content" => $msg, "recipient_type" => 0
						, "subject" => "Marks publishing"));
						if ($sid === false)
							return $this->response->setJSON(array("error" => lang("app.smsErr")));
						$phone = $StudentModel->get_student("students.id=" . $student['id'] . " AND (ft_phone!='' OR mt_phone!='' OR gd_phone!='')", null, "students.id,ft_phone,mt_phone,gd_phone", true);
						if ($phone != null) {
							$all++;
							$p = strlen(trim($phone["ft_phone"])) > 4 ? $phone["ft_phone"] : (strlen(trim($phone["mt_phone"])) > 4 ? $phone["mt_phone"] :
									(strlen(trim($phone["gd_phone"])) > 4 ? $phone["gd_phone"] : ""));
							$smsRMdl->save(array("sms_record_id" => $sid, "receiver_id" => $phone['id'], "phone" => $p, "status" => 0));
							$sent++;

						}
					} catch (\Exception $e) {
						//future use
						return $this->response->setJSON(array("error" => "Error: " . $e));
					}
				}
				$param = base_url("background_process/2");
				$command = "curl $param > /dev/null &";
				exec($command);
				return $this->response->setJSON(array("success" => lang("app.beSent") . " $sent " . lang("app.over") . " $all"));
			}
			$view = "";
			$pdf = false;
			$data['pdf'] = false;
			if (isset($_GET['pdf'])) {
				$pdf = true;
				$data['pdf'] = true;
			}
			$annualTag = $term == 4 ? "_annual" : "";
			if ($term == 4) {
				$data['isFinalClass'] = in_array($students[0]['level_id'], [3, 6, 9, 15, 18, 21, 25, 27]);
			}
			if ($fact == 1 || $fact == 2) {
				if (in_array($school_id, [30])) {
					$view = view("pages/reports/custom/brightAcademy/bright_academy_o_level" . $annualTag, $data);
				} else {
					$view = view("pages/reports/student_report" . $annualTag, $data);
				}
			} else if ($fact == 3) {
				/**
				 * 28. Bright Stars Foundation Academy
				 * 30. Bright Academy
				 */
				if (in_array($school_id, [28])) {
					if ($term == 4) {
						$view = view("pages/reports/specific/bsfa/bright_primary" . $annualTag, $data);
					} else {
						$view = view("pages/reports/specific/bsfa/bsfa_primary" . $annualTag, $data);
					}
				} else if (in_array($school_id, [30])) {
					$view = view("pages/reports/specific/bright_academy_primary" . $annualTag, $data);
				} else if (in_array($school_id, [54])) {
					$view = view("pages/reports/kec_primary_report_slip" . $annualTag, $data);
				} else {
					$view = view("pages/reports/primary_report_slip" . $annualTag, $data);
				}
			} else if ($fact == 19) {
				//Change some specific report
				/**
				 * 28. Bright Stars Foundation Academy
				 * 30. Bright Academy
				 */
				if (in_array($school_id, [28])) {
					$view = view("pages/reports/specific/bsfa/bsfa_nursery" . $annualTag, $data);
				} else if (in_array($school_id, [30])) {
					// $view = view("pages/reports/specific/bright_academy_nursery", $data);
					$view = view("pages/reports/custom/brightAcademy/bright_academy_primary" . $annualTag, $data);
				} else if (in_array($school_id, [31])) {
					$view = view("pages/reports/custom/great_hills_nursery_progress_report" . $annualTag, $data);
				} else if (in_array($school_id, [54])) {
					$view = view("pages/reports/kec_nursery_report_slip" . $annualTag, $data);
				} else if (in_array($school_id, [42])) {
					$view = view("pages/reports/apace_nursery_report_slip" . $annualTag, $data);
				} else {
					$view = view("pages/reports/nursery_report_slip" . $annualTag, $data);
				}
			} else {
				if (in_array($school_id, [52])) {
					$view = view("pages/reports/custom/cyungo_wda" . $annualTag, $data);
				} else if (in_array($school_id, [55])) {
					$view = view("pages/reports/custom/itr_wda" . $annualTag, $data);
				} else {
					$view = view("pages/reports/wda" . $annualTag, $data);
				}
			}
			if ($pdf) {
				/**
				 * List of customized school report
				 */
				// if(in_array($school_id, [28, 30])){
				// 	// echo $view;
				// 	// die();
				// 	header("Contet-Type: application/pdf");
				// 	$mpdf = new \Mpdf\Mpdf(['format' => 'a4', 'orientation' => 'P', 'mode' => 'utf-8']);
				// 	// $mpdf->AddPage();
				// 	$mpdf->WriteHTML($view);
				// 	$mpdf->Output();

				// 	die();
				// }
				$html = $view;
				// echo $html;die();
				try {
					$mask = FCPATH . "assets/templates/*.html";
					array_map('unlink', glob($mask));//clear previous cards
					$wkhtmltopdf = new Wkhtmltopdf(array('path' => FCPATH . 'assets/templates/'));
					$wkhtmltopdf->setTitle(lang("app.rtudentProgressReport"));
					$wkhtmltopdf->setHtml(utf8_decode($html));
					$wkhtmltopdf->setPageSize("A4");
					if ($fact == 19 && in_array($school_id, [54])) {
						$wkhtmltopdf->setOrientation("landscape");
					} else {
						$wkhtmltopdf->setOrientation("portrait");
					}
					// $wkhtmltopdf->setOptions(array("page-width" => "278px", "page-height" => "430px"));
					$wkhtmltopdf->setMargins(array("top" => 2, "left" => 2, "right" => 2, "bottom" => 2));
					$wkhtmltopdf->output(Wkhtmltopdf::MODE_EMBEDDED, "student_progress_report" . time() . ".pdf");
				} catch (\Exception $e) {
					echo $e->getMessage();
				}
			} else {
				$data['content'] = $view;
				return view('main', $data);
			}
		}
	}

	public
	function get_position_with_same_number_when_marks_are_equal($student_marks, &$position_data)
	{
		/**
		 *
		 *  Expected Student marks Format
		 *  [
		 *        'term_name' => [
		 *            'cat' => [
		 *                'student_id' => cat_marks
		 *            ],
		 *            'exam' => [
		 *                'student_id' => exam_marks
		 *            ],
		 *            'total' => [
		 *                'student_id' => total_marks
		 *            ],
		 *        ],
		 *  ]
		 * final format of the position_data array
		 * [
		 *        'term_name' => [
		 *            'cat' => [
		 *                'student_id' => student_position,
		 *            ],
		 *            'exam' => [
		 *                'student_id' => student_position,
		 *            ],
		 *            'total' => [
		 *                'student_id' => student_position,
		 *            ],
		 *        ]
		 * ]
		 */
		foreach ($student_marks as $term_name => $term_info) {
			// echo $term_name;
			// var_dump("<pre>", $term_info, "<hr><hr>");
			//Find position of every student in the selected terms for cat marks
			foreach ($term_info as $column_name => $cat_marks) {
				arsort($cat_marks, SORT_NUMERIC);

				//Now array positions in the cat operations

				$position = 0;
				$skipped_position = 0;
				$previous_marks = null;
				$position_data_info = [];
				foreach ($cat_marks as $student_id => $marks) {
					if ($marks != $previous_marks) {
						if ($skipped_position > 0) {
							$position += $skipped_position;
						}
						$position++;

						$skipped_position = 0;
					} else {
						$skipped_position++;
					}
					$position_data_info[$student_id] = $position;
					$previous_marks = $marks;
				}
				// var_dump("<hr>", $);
				//Now Add those marks to the main array infomation
				$position_data[$term_name][$column_name] = $position_data_info != 0 ? $position_data_info : null;
			}
		}
	}

	public
	function get_courses($class_id, $term, $year)
	{
		$courseModel = new CourseModel();
		$subjectBuilder = $courseModel->select("courses.id,courses.title,courses.code,courses.marks,courses.credit
		,cs.title as category, $term as term, cr.term as term1")
				->join("course_category cs", "cs.id=courses.category")
				->join("course_records cr", "cr.course=courses.id")
				->join("class_records cl", "cl.class=cr.class")
				->where("cr.class", $class_id)
				->where("cr.year", $year)
				->orderBy('cs.title')
				->orderBy('courses.marks', 'desc')
				->orderBy('courses.title')
				->groupBy('courses.id');
		if ($term != 4) {
			//fetch single term courses
			$subjectBuilder->where("find_in_set($term,cr.term)>0");
		}
		return $subjectBuilder->get()->getResultArray();
	}

	public
	function __result($course, $student, $term, $year)
	{
		$MarksModel = new MarksModel();
		//check if there is direct cat marks and ignore period
//		$dtBuilder = $MarksModel->select("marks.id")
//			->join("active_term at", "at.id=marks.term")
//			->where("marks.course_id", $course)
////			->where("at.term", $term)
//			->where("at.academic_year", $year)
//			->where("marks.mark_type", 1)//cat
//			->where("marks.period", 0)//direct cat
//			->where("marks.student_id", $student);
//		if($term != 4){
//			$dtBuilder->where("at.term", $term);
//		}
//		$dt = $dtBuilder->get(1)->getRow();
//		if ($dt != null) {
//			//no direct cat
//			$cat_filter = "marks.period=0";
//		} else {
//			$cat_filter = "1=1";
//		}
//		echo $cat_filter;die();

//		//cat marks
		$catBuilder = $MarksModel->select("(sum(" . self::sqlMarkValue('marks.marks') . "/marks.outof*c.marks)/count(marks.id)) as marks,at.term")
				->join("active_term at", "at.id=marks.term")
				->join("courses c", "c.id=marks.course_id")
				->where("marks.course_id", $course)
				->where("at.academic_year", $year)
				->where("marks.mark_type", 1)//cat
//			->where($cat_filter)//direct cat filter
				->where("marks.student_id", $student);
		if ($term != 4) {
			$catBuilder->where("at.term", $term);
		}
		$cat_marks = array_column($catBuilder->groupBy("at.id")->get()->getResultArray(), 'marks', 'term');
//		//exam marks
		$examBuilder = $MarksModel->select("coalesce((" . self::sqlMarkValue('marks.marks') . "/marks.outof*c.marks)) as marks,at.term")
				->join("active_term at", "at.id=marks.term")
				->join("courses c", "c.id=marks.course_id")
				->where("marks.course_id", $course)
				->where("at.academic_year", $year)
				->where("marks.mark_type", 2)//cat
				->where("marks.student_id", $student);
		if ($term != 4) {
			$examBuilder->where("at.term", $term);
		}
		$exam_marks = array_column($examBuilder->groupBy("at.id")->get()->getResultArray(), 'marks', 'term');
//		$exam_marks = $exam_marks == null ? array("exam_marks" => null) : $exam_marks;
//		$cat_marks = $cat_marks == null ? array("marks" => null) : $cat_marks;
		$subject_result = array_merge(['cat' => $cat_marks], ['exam' => $exam_marks]);
//		var_dump($subject_result);die();
		return $subject_result;
	}

	public
	static function reAssessment($course, $student, $term, $year)
	{
		$MarksModel = new MarksModel();
		$marks = $MarksModel->select("(" . self::sqlMarkValue('marks.marks') . "/marks.outof*100) as marks")
				->join("active_term at", "at.id=marks.term")
				->join("courses c", "c.id=marks.course_id")
				->where("marks.course_id", $course)
				->where("at.term", $term)
				->where("at.academic_year", $year)
				->where("marks.mark_type", 9)//re-assessment
				->where("marks.student_id", $student)
				->get()->getRowArray();
		return $marks;
	}

	public
	function student_report()
	{
		$this->_preset();
		$data = $this->data;
		$data['title'] = lang("app.resultRecord");
		$data['subtitle'] = lang("app.resultRecord");
		$data['page'] = "Result_record";
		$cMdl = new ClassesModel();
		$school_id = $this->session->get("soma_school_id");
		$data['classes'] = $cMdl->get_classes();
		$acMdl = new AcademicYearModel();
		$data['years'] = $acMdl->select('id,title')->where("school_id", $school_id)
				->orderBy("id", 'DESC')->get()->getResultArray();
		$data['error'] = '';
		$data['reports'] = [];
		$data['content'] = view("pages/student_reports", $data);
		return view('main', $data);
	}

	/**
	 * TVET progressive report (local generation, ported from Laravel report_app).
	 */
	private function renderWdaProgressReport($class, $year, $term, $pdfMode = false)
	{
		try {
			$school_id = (int) $this->session->get("soma_school_id");
			$studentId = null;
			if (isset($_GET['student']) && is_numeric($_GET['student'])) {
				$studentId = (int) $_GET['student'];
			}

			$builder = new WdaReportBuilder();
			$reportData = $builder->build($school_id, (int) $class, (int) $year, (int) $term, $studentId);

			if (isset($reportData['error'])) {
				echo htmlspecialchars($reportData['error'], ENT_QUOTES, 'UTF-8');
				return;
			}

			$reportData['pdf'] = (bool) $pdfMode;
			$reportData['term'] = $term;
			$html = view('pages/reports/wda_new_format', $reportData);

			if ($pdfMode) {
				try {
					$mask = FCPATH . "assets/templates/*.html";
					$files = glob($mask);
					if (is_array($files)) {
						array_map('unlink', $files);
					}
					$wkhtmltopdf = new Wkhtmltopdf(array('path' => FCPATH . 'assets/templates/'));
					$wkhtmltopdf->setTitle(lang("app.rtudentProgressReport"));
					$wkhtmltopdf->setHtml(utf8_decode($html));
					$wkhtmltopdf->setPageSize("A4");
					$wkhtmltopdf->setOrientation("portrait");
					$wkhtmltopdf->setMargins(array("top" => 2, "left" => 2, "right" => 2, "bottom" => 2));
					$wkhtmltopdf->output(Wkhtmltopdf::MODE_EMBEDDED, "student_progress_report" . time() . ".pdf");
				} catch (\Exception $e) {
					echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
				}
				return;
			}

			$data = $this->data;
			$data['title'] = lang("app.resultRecord");
			$data['subtitle'] = lang("app.resultRecord");
			$data['page'] = "Result_record";
			$data['content'] = $html;
			return view('main', $data);
		} catch (\Throwable $e) {
			log_message('error', 'WDA report failed: {msg} in {file}:{line}', [
				'msg' => $e->getMessage(),
				'file' => $e->getFile(),
				'line' => $e->getLine(),
			]);
			echo '<div class="alert alert-danger" style="margin:20px">TVET report error: '
				. htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
				. '</div>';
			return;
		}
	}

	public
	function school_fees_management()
	{
		$this->_preset();
		$data = $this->data;
		$schoolFee = new SchoolFeesModel();
		$detpModel = new DeptModel();
		$academicYearMdl = new AcademicYearModel();
		$school_id = $this->session->get("soma_school_id");
		$academicYear = isset($_GET['year']) == true ? $_GET['year'] : $this->data['academic_year'];
		$data['title'] = lang("app.schoolFees");
		$data['subtitle'] = lang("app.schoolFees");
		$data['page'] = "School_fees";
		$data['years'] = $academicYearMdl->select('id,title')
				->where('school_id', $school_id)
				->orderBy('title', 'DESC')->get()->getResultArray();
		$data['depts'] = $detpModel->select("departments.id,departments.code, departments.title")
				->join("classes c", "c.department=departments.id", "INNER")
				->where("c.school_id", $school_id)
				->groupBy("departments.id")
				->get()->getResultArray();

		$data['fees'] = $schoolFee->select("school_fees.id,school_fees.amount,ac.title as academic_year,school_fees.term,l.title,d.code as dept_code")
				->join("levels l", "l.id=school_fees.level")
				->join("departments d", "d.id=school_fees.department")
				->join("academic_year ac", "ac.id=school_fees.academic_year")
				->where("ac.id", $academicYear)
				->where("school_fees.school_id", $school_id)
				->groupBy("school_fees.level")
				->groupBy("school_fees.department")
				->groupBy("school_fees.academic_year")
				->get()->getResultArray();
		$data['content'] = view("pages/school_fees_management", $data);
		return view('main', $data);
	}

	public
	function get_level($dept)
	{
		$levelModel = new LevelsModel();
		$school_id = $this->session->get("soma_school_id");
		$levs = $levelModel->select('levels.id,levels.title')
				->join("classes c", "c.level=levels.id", "INNER")
				->join("departments d", "d.id=c.department", "LEFT")
				->where("c.school_id", $school_id)
				->where("d.id", $dept)
				->groupBy("levels.id")
				->get()->getResultArray();
		echo "<option disabled selected>" . lang("app.selectLevel") . "</option>";
		foreach ($levs as $data) {
			echo "<option value='{$data['id']}'>{$data['title']}</option>";
		}
	}

	public
	function record_attendance()
	{
		$this->_preset();
		$student_id = $this->request->getPost("student_id");
		$date = $this->request->getPost("date");
		$school_id = $this->session->get("soma_school_id");

		if (strtotime($date) > strtotime(date("Y-m-d"))) {
			return $this->response->setJSON(array("error" => lang("app.upcomingDates")));
		}
//		$stMdl = new StudentModel();
		$atMdl = new DailyAttendanceModel();
//		$student = $stMdl->select("id")->where("regno", $regno)->where("school_id", $school_id)->get(1)->getRow();
//		if ($student == null) {
//			return $this->response->setJSON(array("error" => "Student with Reg No:<strong>$regno</strong> not found"));
//		}
		try {
			$data = array(
					"student_id" => $student_id,
					"datee" => $date,
					"active_term" => $this->data['active_term']);
			$atMdl->save($data);
			return $this->response->setJSON(array("success" => lang("app.attendanceRecordSaved")));
		} catch (\Exception $e) {
			if ($e->getCode() == 1062) {
				return $this->response->setJSON(array("error" => lang("app.studentAlreadyAttended")));
			}
			return $this->response->setJSON(array("error" => "Error: " . $e->getMessage()));
		}
	}

	public
	function manipulate_school_fee()
	{
		$this->_preset();
		$school_id = $this->session->get("soma_school_id");
		$level = $this->request->getPost("level");
		$dept = $this->request->getPost("dept");
		$amount = $this->request->getPost("amount");
		$schoolFee = new SchoolFeesModel();
		$i = 1;
		$verify = $schoolFee->where("school_id", $school_id)
				->where("level", $level)
				->where("department", $dept)
				->where("term", $i)
				->where("academic_year", $this->data['academic_year'])
				->countAllResults();
//		var_dump($verify); die();
		if ($verify > 0) {
			return $this->response->setJSON(["error" => "Fee record exist"]);
		}
		try {
			while ($i <= 3) {
				$data = array(
						"school_id" => $school_id,
						"level" => $level,
						"department" => $dept,
						"amount" => $amount,
						"term" => $i,
						"academic_year" => $this->data['academic_year'],
						"created_by" => $this->session->get("soma_id"));
				$schoolFee->save($data);
				$i++;
			}
			return $this->response->setJSON(array("success" => lang("app.feeSaved")));
		} catch (\Exception $e) {
			return $this->response->setJSON(array("error" => "Error: " . $e->getMessage()));
		}

	}

	public
	function extra_fees_management()
	{
		$this->_preset();
		$data = $this->data;
		$classMdl = new ClassesModel();
		$extraFees = new ExtraFeesModel();
		$school_id = $this->session->get("soma_school_id");
		$academicYear = isset($_GET['year']) == true ? $_GET['year'] : $this->data['academic_year'];
		$data['title'] = lang("app.extraFees");
		$data['subtitle'] = lang("app.extraFees");
		$data['page'] = "Extra_fees";
		$academicYearMdl = new AcademicYearModel();
		$data['years'] = $academicYearMdl->select('id,title')
				->where('school_id', $school_id)
				->orderBy('title', 'DESC')->get()->getResultArray();
		$data['classes'] = $classMdl->select("classes.id,classes.title,d.title as department_name,d.code,l.title as level_name
		,f.type,f.abbrev as faculty_code")
				->join("departments d", "d.id=classes.department")
				->join("levels l", "l.id=classes.level")
				->join("faculty f", "f.id=d.faculty_id")
				->where("classes.school_id", $this->session->get("soma_school_id"))
				->get()->getResultArray();
		$data['fees'] = $extraFees->select("extra_fees.id,extra_fees.amount,ac.title as academic_year,extra_fees.title,extra_fees.term,
												cl.title as classe,d.title as department_name,d.code,l.title as level_name
												,f.type,f.abbrev as faculty_code")
				->join("classes cl", "cl.id=extra_fees.type_id AND extra_fees.type=0")
				->join("departments d", "d.id=cl.department")
				->join("academic_year ac", "ac.id=extra_fees.academic_year")
				->join("levels l", "l.id=cl.level")
				->join("faculty f", "f.id=d.faculty_id")
				->where("extra_fees.school_id", $school_id)
				->where("extra_fees.academic_year", $academicYear)
				->get()->getResultArray();
		$data['content'] = view("pages/extra_fees_management", $data);
		return view('main', $data);
	}

	public
	function manipulate_extra_fee($type = 0)
	{
		$this->_preset();
		$ExtraFeesModel = new ExtraFeesModel();
		$school_id = $this->session->get("soma_school_id");
		$id = $this->request->getPost("feeId");
		if (!empty($id)) {
			$amount = $this->request->getPost("feeNewAmount");
			try {
				$ExtraFeesModel->save(['id' => $id, 'amount' => $amount]);
			} catch (\Exception $e) {
				return $this->response->setJSON(array("error" => "Error: " . $e->getMessage()));
			}
			return $this->response->setJSON(array("success" => lang("app.feeSaved")));
		}
		$amount = $this->request->getPost("amount");
		$title = $this->request->getPost("title");
		$classe = $this->request->getPost("classe");
		$typeId = $this->request->getPost($type == 1 ? "studentId" : "classe");
		$data = array(
				"school_id" => $school_id,
				"title" => $title,
				"academic_year" => $this->data['academic_year'],
				"type_id" => $typeId,
				"type" => $type,
				"amount" => $amount,
				"created_by" => $this->session->get("soma_id"));
		foreach ($this->request->getPost("term[]") as $term) {
			$data['term'] = $term;
			try {
				$ExtraFeesModel->save($data);
			} catch (\Exception $e) {
				return $this->response->setJSON(array("error" => "Error: " . $e->getMessage()));
			}
		}
		return $this->response->setJSON(array("success" => lang("app.feeSaved")));
	}

	public
	function manipulate_fee_discount()
	{
		$this->_preset();
		$student = $this->request->getPost("studentId");
		$oldAmount = $this->request->getPost("feeAmount");
		$newAmount = $this->request->getPost("feeNewAmount");
		$feeId = $this->request->getPost("feeId");
		$comment = $this->request->getPost("comment");
		$feesModel = new SchoolFeesModel();
		$feesDiscountModel = new SchoolFeesDiscountModel();
		$feeData = $feesModel->select('(school_fees.amount+coalesce(fd.amount,0)) as amount')->where('school_fees.id', $feeId)
				->join("(select sum(amount) as amount,feesId from school_fees_discount where student=$student group by student,feesId) fd", "fd.feesId=school_fees.id", "LEFT")->first();
		if ($feeData == null) {
			return $this->response->setJSON(array("error" => "Error: invalid school fees"));
		}
		if ($feeData['amount'] != $oldAmount) {
			return $this->response->setJSON(array("error" => "Error: Invalid data (altered)"));
		}
		$amount = $newAmount - $oldAmount;
		$type = $amount > 0 ? 1 : 0;
		$data = array(
				"student" => $student,
				"comment" => $comment,
				"feesId" => $feeId,
				"type" => $type,
				"amount" => $amount,
				"status" => 1,
				"operator" => $this->session->get("soma_id"));
		try {
			$feesDiscountModel->save($data);
		} catch (\Exception $e) {
			return $this->response->setJSON(array("error" => "Error: " . $e->getMessage()));
		}
		return $this->response->setJSON(array("success" => lang("app.feeSaved")));
//		echo $term; die();
	}

	public
	function manipulate_fee_entry()
	{
		$this->_preset();
		$items = $this->request->getPost("items");
		$student = $this->request->getPost("studentid");
		$feesTypes = $this->request->getPost("feeTypes");
		$amounts = $this->request->getPost("amounts");
		$modes = $this->request->getPost("modes");
		$due_date = $this->request->getPost("dueDate");
		$feeEntryModel = new FeesRecordModel();
		$resString = "";
		$recId = 0;
		try {
			foreach ($items as $key => $item):
				$data = [
						"student_id" => $student,
						"fees_type" => $feesTypes[$key],
						"amount" => $amounts[$key],
						"fees_id" => $item,
						"due_date" => $due_date,
						"payment_mode" => $modes[$key],
						"created_by" => $this->session->get("soma_id")];
				$recId = $feeEntryModel->insert($data);
				if (count($items) - 1 == $key) {
					$resString .= $recId . ':' . $feesTypes[$key];
				} else {
					$resString .= $recId . ':' . $feesTypes[$key] . '-';
				}

			endforeach;
			return $this->response->setJSON(["success" => lang("app.feesRecordSaved"), "id" => $recId,
					'url' => base_url('printFeesHistory/' . urlencode($resString) . '/' . $student)]);
		} catch (\Exception $e) {
			return $this->response->setJSON(["error" => "Error: " . $e->getMessage()]);
		}
	}

	public
	function fees_entry()
	{
		$this->_preset();
		$data = $this->data;
		$school_id = $this->session->get("soma_school_id");
		$data['title'] = lang("app.feesEntry");
		$data['subtitle'] = lang("app.feesEntry");
		$data['page'] = "Fees_Entry";
		$classMdl = new ClassesModel();
		$acMdl = new AcademicYearModel();
		$data['years'] = $acMdl->select('id,title')->where("school_id", $school_id)
				->orderBy("id", 'DESC')->get()->getResultArray();
//		$extrafees=new ExtraFeesModel();
		$data['classes'] = $classMdl->select("classes.id,classes.title,d.title as department_name,d.code,l.title as level_name,l.id as level_id,
		,f.type,f.abbrev as faculty_code")
				->join("departments d", "d.id=classes.department")
				->join("levels l", "l.id=classes.level")
				->join("faculty f", "f.id=d.faculty_id")
				->where("classes.school_id", $school_id)
				->get()->getResultArray();
		$data['content'] = view("pages/fees_entry", $data);
		return view('main', $data);
	}

	public
	function get_student_fees($year, $student, $class)
	{
		$this->_preset();
		$school_id = $this->session->get("soma_school_id");
		$schoolFees = new SchoolFeesModel();
		$extraFees = new ExtraFeesModel();
		$classMdl = new ClassesModel();
		$extraFeesx = $extraFees->select("extra_fees.id,extra_fees.type,extra_fees.title,extra_fees.amount,extra_fees.type,extra_fees.term,fr.amount as paidextra,fr.due_date")
				->join("(select fr.student_id,fr.fees_id,fr.due_date,COALESCE(sum(fr.amount),0) as amount from fees_records fr
			 where fr.fees_type=1 and fr.status=1 and fr.student_id=$student group by fr.fees_id) fr", "extra_fees.id=fr.fees_id", "LEFT")
				->where("(extra_fees.type_id=$class AND extra_fees.type=0 and extra_fees.academic_year=$year) or (extra_fees.type_id=$student AND extra_fees.type=1 and extra_fees.academic_year=$year)")
				->where("extra_fees.school_id", $this->session->get('soma_school_id'))
				->get()->getResultArray();
//		print_r($extraFeesx); die();
		$level = $classMdl->select("classes.id,l.id as level_id, d.id as dept_id")
				->join("departments d", "d.id=classes.department")
				->join("levels l", "l.id=classes.level")
				->where("classes.school_id", $school_id)
				->where("classes.id", $class)
				->get()->getRowArray();
		$schoolfrees = $schoolFees->select("school_fees.id,school_fees.term,(school_fees.amount+coalesce(fd.amount,0)) as amount ,sum(fr.amount) as paidschoolfees, fr.due_date")
				->join("fees_records fr", "fr.fees_id=school_fees.id and fr.student_id=$student and fr.fees_type=0 and fr.status=1", "LEFT")
				->join("(select sum(amount) as amount,feesId from school_fees_discount where student=$student group by feesId) fd", "fd.feesId=school_fees.id", "LEFT")
				->where("school_fees.level", $level['level_id'])
				->where("school_fees.department", $level['dept_id'])
				->where("school_fees.academic_year", $year)
				->where("school_fees.school_id", $school_id)
				->groupBy("school_fees.academic_year")
				->groupBy("school_fees.term")
				->get()->getResultArray();
		$i = 1;
		foreach ($schoolfrees as $schoolfree) {
			$piadschlfees = $schoolfree['amount'] - $schoolfree['paidschoolfees'];
			echo "<tr>	<td><input id='fixedSchoolFees' type='hidden' value" . $schoolfree['id'] . ">" . $i . "</td>
						<td>" . lang("app.schoolFees") . "</td>
						<td>" . $this->TermToStr($schoolfree['term']) . "</td>
						<td>" . $schoolfree['amount'] . "<a data-id='{$schoolfree['id']}' data-amount='{$schoolfree['amount']}'
						class='fa fa-pencil-alt btn-append-fees' style='cursor:pointer;'></a> </td>
						<td>" . $schoolfree['paidschoolfees'] . "</td>
						<td>" . $piadschlfees . "</td>
						<td>" . $schoolfree['due_date'] . "</td>
						</tr>";
			$i++;
		}
		foreach ($extraFeesx as $extraffe) {
			$extrapaid = $extraffe['amount'] - $extraffe['paidextra'];
			$delBtn = (empty($extraffe['paidextra']) && $extraffe['type'] == 1) ? '<a class="fa fa-trash btn-del-fee" style="color: orangered" href="#"></a>' : '';
			$editBtn = ($extraffe['type'] == 1) ? "<a data-id='{$extraffe['id']}' data-amount='{$extraffe['amount']}'
						class='fa fa-pencil-alt btn-edit-extra-fees' style='cursor:pointer;'></a>" : '';
			echo "<tr>	<td>" . $i . "</td>
						<td data-id='{$extraffe['id']}'><span>" . $extraffe['title'] . '</span> ' . $delBtn . "</td>
						<td>" . $this->TermToStr($extraffe['term']) . "</td>
						<td>" . $extraffe['amount'] . $editBtn . "</td>
						<td>" . $extraffe['paidextra'] . "</td>
						<td>" . $extrapaid . "</td>
						<td>" . $extraffe['due_date'] . "</td>
						</tr>";
			$i++;
		}

	}

	public
	function get_extra_fees($class, $student)
	{
		$this->_preset();
		$data = $this->data;
		$extrafeesM = new ExtraFeesModel();
		$school_id = $this->session->get("soma_school_id");
		$extrafees = $extrafeesM->select("extra_fees.id,extra_fees.title,extra_fees.type,extra_fees.term")
				->where("extra_fees.school_id", $school_id)
				->where("extra_fees.academic_year", $data['academic_year'])
				->where("(extra_fees.type_id=$class AND extra_fees.type=0) or (extra_fees.type_id=$student AND extra_fees.type=1)")
				->get()->getResultArray();

		echo "<option selected disabled>" . lang("app.SelectExtraFees") . "</option>";
		foreach ($extrafees as $extra) {
			echo "<option value=" . $extra['id'] . ">" . $extra['title'] . ' - ' . termToStr($extra['term']) . "</option>";
		}
	}

	public
	function get_school_fees($year, $student, $class)
	{
		$this->_preset();
		$data = $this->data;
		$school_id = $this->session->get("soma_school_id");
		$schoolFees = new SchoolFeesModel();;
		$classMdl = new ClassesModel();
		$level = $classMdl->select("classes.id,l.id as level_id, d.id as dept_id")
				->join("departments d", "d.id=classes.department")
				->join("levels l", "l.id=classes.level")
				->where("classes.school_id", $school_id)
				->where("classes.id", $class)
				->get()->getRowArray();
		$schoolfrees = $schoolFees->select("school_fees.id,school_fees.term,(school_fees.amount+coalesce(fd.amount,0)) as amount ,fr.amount as paidschoolfees, fr.due_date")
				->join("(select sum(amount) as amount,feesId from school_fees_discount where student=$student group by feesId) fd", "fd.feesId=school_fees.id", "LEFT")
				->join("fees_records fr", "fr.fees_id=school_fees.id and fr.student_id=$student and fr.fees_type=2", "LEFT")
				->where("school_fees.level", $level['level_id'])
				->where("school_fees.department", $level['dept_id'])
				->where("school_fees.academic_year", $year)
				->where("school_fees.school_id", $school_id)
				->get()->getResultArray();

		echo "<option selected disabled>" . lang("app.SelectSchoolterm") . "</option>";
		$i = 1;
		foreach ($schoolfrees as $extra) {
			echo "<option value=" . $extra['id'] . ">" . $this->TermToStr($extra['term']) . "</option>";
			$i++;
		}
	}

	public
	function get_extra_single_record($extra, $student)
	{
		$this->_preset();
		$data = $this->data;
		$extrafeesmodel = new ExtraFeesModel();
		$school_id = $this->session->get("soma_school_id");
		$extrafees = $extrafeesmodel->select("extra_fees.amount as extra_amt,sum(fr.amount) as paid_amt")
				->join("fees_records fr", "fr.fees_id=extra_fees.id AND fr.student_id=$student and fr.fees_type=1 and fr.status=1", "LEFT")
				->where("extra_fees.school_id", $school_id)
				->where("extra_fees.id", $extra)
				->get()->getRowArray();
		echo json_encode($extrafees);
	}

	public
	function get_schoolfees_single_record($feeId, $student)
	{
		$this->_preset();
		$data = $this->data;
		$schoolfeesModel = new SchoolFeesModel();
		$school_id = $this->session->get("soma_school_id");
		$schoolfees = $schoolfeesModel->select("(school_fees.amount+coalesce(fd.amount,0)) as schlfee_amt,sum(fr.amount) as paid_amt")
				->join("fees_records fr", "fr.fees_id=school_fees.id AND fr.student_id=$student and fr.fees_type=0 and fr.status=1", "LEFT")
				->join("(select sum(amount) as amount,feesId from school_fees_discount where student=$student group by feesId) fd", "fd.feesId=school_fees.id", "LEFT")
				->where("school_fees.school_id", $school_id)
				->where("school_fees.id", $feeId)
				->get()->getRowArray();
		echo json_encode($schoolfees);
	}

	public
	function delete_course($id)
	{
		$courseModel = new CourseModel();
		$mMdl = new MarksModel();
		try {
			$r = $mMdl->select('marks.id,')
					->where('marks.course_id', $id)
					->get(1)->getRow();
			if ($r != null) {
				return $this->response->setJSON(array("error" => "Error: This Course has marks, can not be deleted"));
			}
			$courseModel->delete($id);

			return $this->response->setJSON(array("success" => lang("app.courseDeleted")));
		} catch (\Exception $e) {
			return $this->response->setJSON(array("error" => "Error: " . $e->getMessage()));
		}
	}

	public
	function cancel_fee_record($id)
	{
		$fMdl = new FeesRecordModel();
		try {
			$fMdl->save(['id' => $id, 'status' => -1]);

			return $this->response->setJSON(array("success" => lang("app.feesRecordCancelled")));
		} catch (\Exception $e) {
			return $this->response->setJSON(array("error" => "Error: " . $e->getMessage()));
		}
	}

	public
	function manipulate_bookCategory()
	{

		$this->_preset();
		$data = $this->data;
		$category = new BookCategoryModel();
		$title = $this->request->getPost("title");
		$data = array(
				"school_id" => $this->session->get("soma_school_id"),
				"title" => $title,
				"created_by" => $this->session->get("soma_id"));
		try {
			$category->save($data);
			return $this->response->setJSON(array("success" => lang("app.categorySaved")));
		} catch (\Exception $e) {
			return $this->response->setJSON(array("error" => "Error: " . $e->getMessage()));
		}
	}

	public
	function book_management()
	{
		$this->_preset();
		$data = $this->data;
		$school_id = $this->session->get("soma_school_id");
		$data['title'] = lang("app.booksRecord");
		$data['subtitle'] = lang("app.booksRecord");
		$data['page'] = "Book";
		$bookModel = new BookModel();
		$category = new BookCategoryModel();
		$classModel = new ClassesModel();
		$staffMdl = new StaffModel();
		$data['staffs'] = $staffMdl->select("id,concat(fname,' ',lname) as names")
				->where("school_id", $this->session->get("soma_school_id"))
				->get()->getResultArray();
		$data['books'] = $bookModel->select("books.id,books.title,books.author,books.quantity,c.title AS category,count(br.book_id) as borrowed")
				->join("bookcategory c", "c.id=books.category", "LEFT")
				->join("book_records br", "br.book_id=books.id AND br.status=0", "LEFT")
				->where("books.school_id", $school_id)
				->groupBy("books.id")
				->get()->getResultArray();
		$data['classes'] = $classModel->get_classes();
		$data['categories'] = $category->where("school_id", $school_id)->get()->getResultArray();
		$data['content'] = view("pages/library/book_record", $data);
		return view('main', $data);
	}

	public
	function borrowed_report()
	{
		$this->_preset();
		$data = $this->data;
		$school_id = $this->session->get("soma_school_id");
		$data['title'] = lang("app.borrowedReport");
		$data['subtitle'] = lang("app.borrowedReport");
		$data['page'] = "Borrowed_report";
		$bookModel = new BookModel();
		$category = new BookCategoryModel();
		$classModel = new ClassesModel();
		$data['books'] = $bookModel->select("books.id,books.title")
				->where("books.school_id", $school_id)
				->get()->getResultArray();
		$staffMdl = new StaffModel();
		$data['staffs'] = $staffMdl->select("id,concat(fname,' ',lname) as names")
				->where("school_id", $this->session->get("soma_school_id"))
				->get()->getResultArray();
		$data['classes'] = $classModel->get_classes();
		$data['content'] = view("pages/library/borrowed_report", $data);
		return view('main', $data);
	}

	public
	function manipulate_book_entry()
	{

		$this->_preset();
		$data = $this->data;
		$school_id = $this->session->get("soma_school_id");
		$bookModel = new BookModel();
		$id = $this->request->getPost("fId");
		$title = $this->request->getPost("title");
		$author = $this->request->getPost("author");
		$category = $this->request->getPost("category");
		$quantity = $this->request->getPost("quantity");
		$quantityNew = $this->request->getPost("newquantity");
		$quantityNew = empty($quantityNew) ? 0 : $quantityNew;
		if ($id != null) {
			$book = $bookModel->select("books.quantity")
					->join("bookcategory c", "c.id=books.category", "LEFT")
					->where("books.school_id", $school_id)
					->where("books.id", $id)
					->get()->getRowArray();
			$books = $book['quantity'] + $quantityNew;
			$data = array(
					"id" => $id,
					"school_id" => $this->session->get("soma_school_id"),
					"title" => $title,
					"author" => $author,
					"category" => $category,
					"quantity" => $books,
					"status" => 1,
					"created_by" => $this->session->get("soma_id"));
		} else {
			$data = array(
					"school_id" => $this->session->get("soma_school_id"),
					"title" => $title,
					"author" => $author,
					"category" => $category,
					"quantity" => $quantity,
					"status" => 1,
					"created_by" => $this->session->get("soma_id"));
		}

		try {
			$bookModel->save($data);
			return $this->response->setJSON(array("success" => lang("app.bookSaved")));
		} catch (\Exception $e) {
			return $this->response->setJSON(array("error" => "Error: " . $e->getMessage()));
		}
	}

	public
	function get_book($id)
	{
		$this->_preset();
		$bookModel = new BookModel();
		$school_id = $this->session->get("soma_school_id");
		$book = $bookModel->select("books.id,books.title,books.author,books.quantity,books.status,c.id AS category")
				->join("bookcategory c", "c.id=books.category", "LEFT")
				->where("books.school_id", $school_id)
				->where("books.id", $id)
				->get()->getRowArray();
		echo json_encode($book);

	}

	/**
	 * @return Response
	 */
	public
	function manipulate_borrow_book(): Response
	{

		$this->_preset();
		$data = $this->data;
		$bookRecordModel = new BookRecordModel();
		$school_id = $this->session->get("soma_school_id");
		$id = $this->request->getPost("bookId");
		$term = $this->data['term'];
		$bdate = $this->request->getPost("borrow_date");
		$rdate = $this->request->getPost("return_due_date");
		$type = $this->request->getPost("borrowType");
		$bdate = strtotime($bdate);
		$rdate = strtotime($rdate);
		$typeId = 0;
		if (isset($_POST["select_student_book"])) {
			$typeId = $_POST["select_student_book"];
		} else if (isset($_POST["staff"])) {
			$typeId = $_POST["staff"];
		} else {
			return $this->response->setJSON(["error" => "Invalid request made"]);
		}
		$books = $bookRecordModel->select("book_records.book_id,book_records.borrow_date,book_records.return_due_date,book_records.status,book_records.return_date,book_records.typeId")
				->where("book_records.school_id", $this->session->get("soma_school_id"))
				->where("book_records.typeId", $typeId)
				->where("book_records.type", $type)
				->get()->getResultArray();
//		print_r(/$books); die();
		foreach ($books as $book) {
			if ($book['book_id'] == $id and $book['status'] != 1) {
				return $this->response->setJSON(array("error" => lang("app.errOne")));
			}
		}

		foreach ($books as $book) {
			if ($book['return_due_date'] < time() and $book['status'] != 1) {
				return $this->response->setJSON(array("error" => lang("app.errTwo")));
			}
		}

		if ($bdate > $rdate) {
			return $this->response->setJSON(array("error" => lang("app.errThree")));
		}
		if ($bdate > strtotime(date("Y-m-d"))) {
			return $this->response->setJSON(array("error" => lang("app.errFour")));
		}

		$data = array(
				"book_id" => $id,
				"school_id" => $school_id,
				"type" => $type,
				"typeId" => $typeId,
				"academic_year" => $this->data['academic_year'],
				"term" => $term,
				"borrow_date" => $bdate,
				"return_due_date" => $rdate,
				"status" => 0,
				"created_by" => $this->session->get("soma_id"));

		try {
			$bookRecordModel->save($data);
			return $this->response->setJSON(array("success" => lang("app.bookBorrowed")));
		} catch (\Exception $e) {
			return $this->response->setJSON(array("error" => "Error: " . $e->getMessage()));
		}
	}

	public
	function upload_pictures()
	{
		$file = $this->request->getFile("file");
		if (!$file || !$file->isValid()) {
			return $this->response->setStatusCode(400)->setJSON(array("error" => lang("app.invalidFile") ?: "Invalid file"));
		}
		$ext = strtolower($file->getClientExtension() ?: $file->getExtension() ?: '');
		if (!in_array($ext, ["jpg", "jpeg", "png"], true)) {
			return $this->response->setStatusCode(400)->setJSON(array("error" => lang("app.fileNotAllowed") . " " . $ext));
		}
		if ($file->getSize() > 5 * 1024 * 1024) {
			return $this->response->setStatusCode(400)->setJSON(array("error" => lang("app.fileSizeBigger") . " (max 5MB)"));
		}
		$stMdl = new StudentModel();
		$student = $stMdl->select('id,photo,fname')->where('regno', explode('.', $file->getName())[0])->get(1)->getRow();
		if ($student == null) {
			return $this->response->setStatusCode(400)->setJSON(array("error" => lang("app.opsStudentNotFound")));
		}
		$name = make_profile_photo_name($ext);
		$profilePath = FCPATH . "assets/images/profile/";
		if (!is_dir($profilePath)) {
			@mkdir($profilePath, 0775, true);
		}
		if (!is_writable($profilePath)) {
			@chmod($profilePath, 0775);
		}
		if ($file->move($profilePath, $name, true)) {
			//save to student
			try {
				$stMdl->save(array("id" => $student->id, "photo" => $name));
			} catch (\Exception $e) {
				return $this->response->setStatusCode(400)->setJSON(array("error" => lang("app.photoNotSaved")));
			}
			if (!empty($student->photo) && is_file($profilePath . $student->photo)) {
				@unlink($profilePath . $student->photo);//delete old photo
			}
			return $this->response->setJSON(array("message" => lang("app.photoUploaded"), "student" => $student->fname));
		} else {
			//upload error
			return $this->response->setStatusCode(400)->setJSON(array("error" => $file->getErrorString()));
		}
	}

	public
	function returing_book()
	{
		$this->_preset();
		$data = $this->data;
		$bookRecordModel = new BookRecordModel();
		$id = $this->request->getPost("record_id");

		$data = array(
				"id" => $id,
				"return_date" => time(),
				"status" => 1);

		try {
			$bookRecordModel->save($data);
			return $this->response->setJSON(array("success" => lang("app.bookReturned")));
		} catch (\Exception $e) {
			return $this->response->setJSON(array("error" => "Error: " . $e->getMessage()));
		}
	}

	public
	function book_history($id)
	{
		$this->_preset();
		$data = $this->data;
		$school_id = $this->session->get("soma_school_id");
		$data['title'] = lang("app.bookHistory");
		$data['subtitle'] = lang("app.bookHistory");
		$data['page'] = "Book_history";
		$bookModel = new BookModel();
		$students = $bookModel->select("books.id,books.title,books.author,if(br.type=1,'Student','1') as type,br.id as record_id,br.borrow_date,br.return_due_date,br.status,br.return_date,concat(s.fname,' ',s.lname) as student")
				->join("book_records br", "br.book_id=books.id", "LEFT")
				->join("students s", "s.id=br.typeId")
				->where("br.type", 1)
				->where("books.school_id", $school_id)
				->where("books.id", $id)
				->get()->getResultArray();

		$stuffs = $bookModel->select("books.id,books.title,books.author,if(br.type=2,'Staff','2') as type,br.id as record_id,br.borrow_date,br.return_due_date,br.status,br.return_date,concat(s.fname,' ',s.lname) as student")
				->join("book_records br", "br.book_id=books.id", "LEFT")
				->join("staffs s", "s.id=br.typeId")
				->where("br.type", 2)
				->where("books.school_id", $school_id)
				->where("books.id", $id)
				->get()->getResultArray();
		$histories = array_merge($students, $stuffs);
		$data['books'] = $histories;
		$data['content'] = view("pages/library/book_history", $data);
		return view('main', $data);
	}
public function assign_card()
{
    // 1️⃣ Ensure user session exists
    if (!$this->session->has('soma_school_id')) {
        return redirect()->to(base_url('login'))->with('error', 'Session expired, please log in again.');
    }

    // 2️⃣ Initialize preset data
    $this->_preset();
    $data = $this->data;

    // 3️⃣ Fetch school ID from session
    $school_id = (int) $this->session->get('soma_school_id');

    // 4️⃣ Page setup
    $data['title'] = lang('app.assignCard');
    $data['subtitle'] = lang('app.assignCard');
    $data['page'] = 'Assign_card';

    // 5️⃣ Load model
    $studentModel = new \App\Models\StudentModel();

    // 6️⃣ Build query — include full class name (L3 CSA) and section
    $students = $studentModel
        ->select("
            students.id,
            CONCAT(students.fname, ' ', students.lname) AS name,
            CONCAT(l.title, ' ', d.code, ' ', c.title) AS class,
            d.title AS section,
            students.card AS card_number
        ")
        ->join('class_records cr', 'cr.student = students.id', 'left')
        ->join('classes c', 'c.id = cr.class', 'left')
        ->join('departments d', 'd.id = c.department', 'left')
        ->join('levels l', 'l.id = c.level', 'left')
        ->where('students.school_id', $school_id)
        ->where('students.status', 1)
        ->groupBy('students.id')
        ->orderBy('students.fname', 'ASC')
        ->orderBy('students.lname', 'ASC')
        ->get()
        ->getResultArray();

    // 7️⃣ Pass data to view
    $data['students'] = $students;
    $data['content'] = view('pages/students/assign_card', $data);

    // 8️⃣ Return main layout view
    return view('main', $data);
}

	public
	function get_borrowed_report($student, $type, $book, $from, $to)
	{
		$this->_preset();
		$bookModel = new BookModel();
		$school_id = $this->session->get("soma_school_id");
		$fromdate = strtotime($from);
		$todate = strtotime($to);
		if ($type == 1) {
			$books = $bookModel->select("books.id,br.return_due_date,br.borrow_date,br.return_date,br.status,concat(s.lname,' ',s.fname) as student,c.title,
														d.title as department_name,
														d.code,
														l.title as level_name")
					->join("book_records br", "br.book_id=books.id", "LEFT")
					->join("students s", "s.id=br.typeId", "LEFT")
					->join("class_records cr", "cr.student=s.id")
					->join("classes c", "c.id=cr.class")
					->join("departments d", "d.id=c.department")
					->join("levels l", "l.id=c.level")
					->where("books.school_id", $school_id)
					->where("br.type", 1)
					->where("books.id", $book)
					->where("br.borrow_date >=", $fromdate)
					->where("br.borrow_date <=", $todate)
					->get()->getResultArray();
			if ($books == null) {
				echo "<center>" . lang("app.NoDataFound") . "</center>";
			}
			$i = 1;
			foreach ($books as $book) {
				echo "<tr>
					<td>" . $i . "</td>
					<td>" . $book['student'] . "</td>
					<td>" . $book['level_name'] . " " . $book['title'] . " " . $book['code'] . "</td>
					<td>" . date('d-m-Y', $book['borrow_date']) . "</td>
					<td>" . date('d-m-Y', $book['return_due_date']) . "</td>
					<td>" . $this->get_returndate($book['return_date']) . "</td>
					<td>" . $this->get_status($book['status']) . "</td>
						</tr>
						";
				$i++;
			}
			echo "<script> $('#reportBody').show(); $('.mylable').text('Student');$('.myClass').show();</script>";
		} else if ($type == 2) {
			$books = $bookModel->select("books.id,books.title,br.return_due_date,br.borrow_date,br.return_date,br.status")
					->join("book_records br", "br.book_id=books.id", "LEFT")
					->join("students s", "s.id=br.typeId", "LEFT")
					->where("books.school_id", $school_id)
					->where("br.type", 1)
					->where("br.typeId", $student)
					->where("br.borrow_date >=", $fromdate)
					->where("br.borrow_date <=", $todate)
					->get()->getResultArray();
			if ($books == null) {
				echo "<center>" . lang("app.NoDataFound") . "</center>";
			}
			$i = 1;
			foreach ($books as $book) {
				echo "<tr>
					<td>" . $i . "</td>
					<td>" . $book['title'] . "</td>
					<td>" . date('d-m-Y', $book['borrow_date']) . "</td>
					<td>" . date('d-m-Y', $book['return_due_date']) . "</td>
					<td>" . $this->get_returndate($book['return_date']) . "</td>
					<td>" . $this->get_status($book['status']) . "</td>
						</tr>
						";
				$i++;
			}
			echo "<script> $('#reportBody').show(); $('.mylable').text('Title'); $('.myClass').hide();</script>";
		} else if ($type == 3) {
			$books = $bookModel->select("books.id,books.title,br.return_due_date,br.borrow_date,br.return_date,br.status")
					->join("book_records br", "br.book_id=books.id", "LEFT")
					->join("staffs s", "s.id=br.typeId", "LEFT")
					->where("books.school_id", $school_id)
					->where("br.type", 2)
					->where("br.typeId", $student)
					->where("br.borrow_date >=", $fromdate)
					->where("br.borrow_date <=", $todate)
					->get()->getResultArray();
			if ($books == null) {
				echo "<center>" . lang("app.NoDataFound") . "</center>";
			}
			$i = 1;
			foreach ($books as $book) {
				echo "<tr>
					<td>" . $i . "</td>
					<td>" . $book['title'] . "</td>
					<td>" . date('d-m-Y', $book['borrow_date']) . "</td>
					<td>" . date('d-m-Y', $book['return_due_date']) . "</td>
					<td>" . $this->get_returndate($book['return_date']) . "</td>
					<td>" . $this->get_status($book['status']) . "</td>
						</tr>
						";
				$i++;
			}
			echo "<script> $('#reportBody').show(); $('.mylable').text('Title'); $('.myClass').hide();</script>";
		}

	}

	public
	function permission_report()
	{
		$this->_preset();
		$data = $this->data;
		$school_id = $this->session->get("soma_school_id");
		$data['title'] = lang("app.permissionReport");
		$data['subtitle'] = lang("app.permissionReport");
		$data['page'] = "Permission_report";
		$bookModel = new BookModel();
		$category = new BookCategoryModel();
		$classModel = new ClassesModel();
		$data['books'] = $bookModel->select("books.id,books.title")
				->where("books.school_id", $school_id)
				->get()->getResultArray();
		$data['classes'] = $classModel->get_classes();
		$data['content'] = view("pages/reports/permission_report", $data);
		return view('main', $data);
	}

	public
	function get_permission_report($student, $from, $to)
	{
		$this->_preset();
		$data = $this->data;
		$permission = new PermissionModel();

		$permissions = $permission->select("permission.*")
				->where("created_at>=", $from)
				->where("created_at <=", $to)
				->where("student_id", $student)
				->get()->getResultArray();
		$i = 1;
		foreach ($permissions as $perm) {
			echo "<tr>
					<td>" . $i . "</td>
					<td>" . $perm['destination'] . "</td>
					<td>" . $perm['reason'] . "</td>
					<td>" . $perm['leave_time'] . "</td>
					<td>" . $perm['return_time'] . "</td>
					<td><a class='btn btn-outline-success' href='print_permission/" . $perm['id'] . "'>Print</a></td>
						</tr>
						";
			$i++;
		}
		echo "<script> $('#myView').show(); </script>";
	}

	public function print_permission($id)
{
    $this->_preset();
    $data = $this->data;

    $school_id = $this->session->get("soma_school_id");

    $data['title'] = lang("app.printPermission");
    $data['subtitle'] = lang("app.printPermission");
    $data['page'] = "print_Permission";

    $permModel   = new PermissionModel();
    $schoolModel = new \App\Models\SchoolModel();

    // ✅ Fetch the permission record with full student/class details
    $data['permissions'] = $permModel
        ->select("permission.id, permission.destination, permission.reason, permission.leave_time, permission.return_time,
                  s.fname, s.lname, s.regno,
                  c.title, d.title AS department_name, d.code, l.title AS level_name")
        ->join("students s", "s.id = permission.student_id")
        ->join("class_records cr", "cr.student = s.id")
        ->join("classes c", "c.id = cr.class")
        ->join("departments d", "d.id = c.department")
        ->join("levels l", "l.id = c.level")
        ->where("s.school_id", $school_id)
        ->where("permission.id", $id)
        ->get()
        ->getRowArray();

    // ✅ Load the school data (includes signatures & logo)
    $settings = $schoolModel->where("id", $school_id)->first();

    // ✅ Prepare view data (school + settings)
    $data['school_name']   = $settings['name'];
    $data['school_email']  = $settings['email'];
    $data['school_phone']  = $settings['phone'];
    $data['school_logo']   = $settings['logo'];
    $data['settings']      = $settings;

    // ✅ Load the content (permission slip)
    $data['content'] = view("pages/reports/print_permission", $data);

    // ✅ Render the page inside the main layout
    return view("main", $data);
}


	public
	function get_student_change($id)
	{
		$this->_preset();
		$student = new StudentModel();
		$std = $student->select("students.studying_mode,cr.id,cr.class AS classe")
				->join("class_records cr", "cr.student=students.id", "LEFT")
				->where("cr.year", $this->data['academic_year'])
				->where("students.id", $id)
				->where("students.school_id", $this->session->get("soma_school_id"))
				->get()->getRowArray();
		echo json_encode($std);
	}

	public function change_studing_mode()
{
    $this->_preset();

    $studentModel = new StudentModel();
    $classRecordModel = new ClassRecordModel();

    $schoolId = $this->session->get('soma_school_id');
    $academicYear = $this->data['academic_year'];

    $rawId = trim((string)$this->request->getPost('fId'));   // can be students.id OR class_records.id
    $rawMode = trim((string)$this->request->getPost('mode')); // can be 0/1 or "Boarding"/"Day"

    // 1) Normalize mode to int 0/1
    $mode = null;
    if ($rawMode === '0' || $rawMode === 0 || strcasecmp($rawMode, 'Boarding') === 0) {
        $mode = 0;
    } elseif ($rawMode === '1' || $rawMode === 1 || strcasecmp($rawMode, 'Day') === 0) {
        $mode = 1;
    } else {
        return $this->response->setJSON(['error' => 'Invalid mode value.']);
    }

    // 2) Resolve student id
    $studentId = null;

    // Try as students.id first
    $row = $studentModel->select('id')
        ->where(['id' => $rawId, 'school_id' => $schoolId])
        ->get()->getRow();
    if ($row) {
        $studentId = (int)$row->id;
    } else {
        // Try as class_records.id => fetch student
        $cr = $classRecordModel->select('student')
            ->where(['id' => $rawId, 'year' => $academicYear])
            ->get()->getRow();
        if ($cr) {
            $studentId = (int)$cr->student;
        }
    }

    if (!$studentId) {
        return $this->response->setJSON(['error' => 'Could not resolve student id from provided identifier.']);
    }

    // 3) Update and confirm it really changed
    $studentModel->where(['id' => $studentId, 'school_id' => $schoolId])
                 ->set(['studying_mode' => $mode])
                 ->update();

    if ($studentModel->db->affectedRows() > 0) {
        return $this->response->setJSON(['success' => lang('app.modeChanged')]);
    } else {
        // Either same value was sent or WHERE matched nothing
        return $this->response->setJSON(['info' => 'No change (same value or invalid scope).']);
    }
}


	public
	function change_student_class()
	{
		$this->_preset();
		$data = $this->data;
		$classeModel = new ClassRecordModel();
		$id = $this->request->getPost("fId");
		$class = $this->request->getPost("classe");

		$data = [
				"id" => $id,
				"class" => $class
		];
		try {
			$classeModel->save($data);
			return $this->response->setJSON(["success" => lang("app.classChanged")]);
		} catch (\Exception $e) {
			return $this->response->setJSON(["error" => "Error: " . $e->getMessage()]);
		}
	}

	public
	function bus_management()
	{
		$this->_preset();
		$data = $this->data;
		$school_id = $this->session->get("soma_school_id");
		$StaffModel = new StaffModel();
		$BusModel = new BusModel();
		$data['drivers'] = $StaffModel->select("id,concat(fname,' ',lname) as driver")
				->where("school_id", $school_id)
				->get()->getResultArray();
		$data['bus'] = $BusModel->select("bus.id,bus.plate,bus.car_maker,bus.car_model,bus.car_year,bus.places,concat(s.fname,' ',s.lname) as driver")
				->join("staffs s", "s.id=bus.driver", "LEFT")
				->where("bus.school_id", $school_id)
				->get()->getResultArray();
		$data['title'] = lang("app.busManagement");
		$data['subtitle'] = lang("app.busManagement");
		$data['page'] = "Bus_management";
		$data['content'] = view("pages/transport/bus", $data);
		return view('main', $data);
	}

	public
	function get_bus($id)
	{
		$BusModel = new BusModel();
		$school_id = $this->session->get("soma_school_id");
		$bus = $BusModel->select("bus.id,bus.plate,bus.car_maker,bus.car_model,bus.car_year,bus.places,concat(s.fname,' ',s.lname) as driver,s.id as staff_id")
				->join("staffs s", "s.id=bus.driver", "LEFT")
				->where("bus.school_id", $school_id)
				->where("bus.id", $id)
				->get()->getRowArray();
		echo json_encode($bus);
	}

	public
	function route_management()
	{
		$this->_preset();
		$data = $this->data;
		$school_id = $this->session->get("soma_school_id");
		$RouteModel = new RouteModel();
		$data['routes'] = $RouteModel->select("routes.*")->where("routes.school_id", $school_id)->get()->getResultArray();
		$data['title'] = lang("app.boutesManagement");
		$data['subtitle'] = lang("app.boutesManagement");
		$data['page'] = "Route_management";
		$data['content'] = view("pages/transport/route", $data);
		return view('main', $data);
	}

	public
	function get_route($id)
	{
		$RouteModel = new RouteModel();
		$school_id = $this->session->get("soma_school_id");
		$routes = $RouteModel->select("routes.id,routes.title,routes.details,routes.price")
				->where("routes.school_id", $school_id)
				->where("routes.id", $id)
				->get()->getRowArray();
		echo json_encode($routes);
	}


	public
	function manipulate_route()
	{
		$this->_preset();
		$RouteModel = new RouteModel();
		$school_id = $this->session->get("soma_school_id");
		$id = $this->request->getPost("fId");
		$title = $this->request->getPost("title");
		$details = $this->request->getPost("details");
		$price = $this->request->getPost("price");

//		echo $id; die();
		if ($id != "") {
			$data = array(
					"id" => $id,
					"title" => $title,
					"details" => $details,
					"price" => $price,
					"updated_by" => $this->session->get("soma_id")
			);
		} else {
			$data = array(
					"school_id" => $school_id,
					"title" => $title,
					"details" => $details,
					"price" => $price,
					"created_by" => $this->session->get("soma_id")
			);

		}
		try {
			$RouteModel->save($data);
			return $this->response->setJSON(array("success" => lang("app.routeSaved")));
		} catch (\Exception $e) {
			return $this->response->setJSON(array("error" => "Error: " . $e->getMessage()));
		}
	}

	public
	function manipulate_bus()
	{
		$this->_preset();
		$BusModel = new BusModel();
		$school_id = $this->session->get("soma_school_id");
		$id = $this->request->getPost("fId");
		$plate = $this->request->getPost("plate");
		$car_maker = $this->request->getPost("car_maker");
		$car_model = $this->request->getPost("car_model");
		$car_year = $this->request->getPost("car_year");
		$places = $this->request->getPost("places");
		$driver = $this->request->getPost("driver");
		$staff = $this->request->getPost("staff");

//		echo $id; die();
		if ($id != "") {
			$data = array(
					"id" => $id,
					"plate" => $plate,
					"car_maker" => $car_maker,
					"car_model" => $car_model,
					"car_year" => $car_year,
					"places" => $places,
					"updated_by" => $this->session->get("soma_id")
			);
		}
		if ($staff != "" and $id != "") {
			$data = array(
					"id" => $id,
					"driver" => $staff,
					"updated_by" => $this->session->get("soma_id")
			);
		}
		if ($staff == "" and $id == "") {
			$data = array(
					"school_id" => $school_id,
					"plate" => $plate,
					"car_maker" => $car_maker,
					"car_model" => $car_model,
					"car_year" => $car_year,
					"places" => $places,
					"driver" => $driver,
					"created_by" => $this->session->get("soma_id")
			);

		}
		try {
			$BusModel->save($data);
			return $this->response->setJSON(array("success" => lang("app.busSaved")));
		} catch (\Exception $e) {
			return $this->response->setJSON(array("error" => "Error: " . $e->getMessage()));
		}
	}

	public
	function transport_fees_management()
	{
		$this->_preset();
		$data = $this->data;
		$school_id = $this->session->get("soma_school_id");
		$classModel = new ClassesModel();
		$TransportFeesModel = new TransportFeesModel();
		$data['classes'] = $classModel->get_classes();
		$data['transports'] = $TransportFeesModel->select("transport_fees.id,sum(transport_fees.paid_amount) as paid_amount,concat(s.fname,' ',s.lname) as student,
														s.transport_money,
														s.id as student_id,
														s.regno,
														c.title,
														d.title as department_name,
														d.code,
														l.title as level_name")
				->join("students s", "s.id=transport_fees.student_id")
				->join("class_records cr", "cr.student=s.id")
				->join("classes c", "c.id=cr.class")
				->join("departments d", "d.id=c.department")
				->join("levels l", "l.id=c.level")
				->where("s.school_id", $this->session->get("soma_school_id"))
				->groupBy("transport_fees.student_id")
				->get()->getResultArray();
		$data['title'] = lang("app.transportFees");
		$data['subtitle'] = lang("app.transportFees");
		$data['page'] = "Transport_management";
		$data['content'] = view("pages/transport_fees", $data);
		return view('main', $data);
	}

	public
	function transport_fees_history($id)
	{
		$this->_preset();
		$data = $this->data;
		$school_id = $this->session->get("soma_school_id");
		$TransportFeesModel = new TransportFeesModel();
		$studentModel = new StudentModel();
		$data['student'] = $studentModel->select("students.regno,concat(students.fname,' ',students.lname) as names,c.title,
														d.title as department_name,
														d.code,
														l.title as level_name")
				->join("class_records cr", "cr.student=students.id")
				->join("classes c", "c.id=cr.class")
				->join("departments d", "d.id=c.department")
				->join("levels l", "l.id=c.level")
				->where("students.school_id", $this->session->get("soma_school_id"))
				->where("students.id", $id)
				->get()->getRowArray();
		$data['transports'] = $TransportFeesModel->select("transport_fees.id, transport_fees.paid_amount  as paid_amount,transport_fees.created_at")
				->where("transport_fees.student_id", $id)
				->get()->getResultArray();
		$data['title'] = lang("app.transportHistory");
		$data['subtitle'] = lang("app.transportHistory");
		$data['page'] = "Transport_history";
		$data['content'] = view("pages/transport_history", $data);
		return view('main', $data);
	}


	public
	function manipulate_transport_fees()
	{
		$this->_preset();
		$studentModel = new StudentModel();
		$TransportFeesModel = new TransportFeesModel();
		$school_id = $this->session->get("soma_school_id");
		$student = $this->request->getPost("select_student_btrans");
		$amount = $this->request->getPost("recieved_amount");
		$current = $studentModel->select('transport_money')->where('id', $student)->get()->getRowArray();
		$newAmount = $current['transport_money'] + $amount;


		$data1 = array(
				"id" => $student,
				"transport_money" => $newAmount
		);

		$data2 = array(
				"student_id" => $student,
				"paid_amount" => $amount,
				"created_by" => $this->session->get("soma_id")
		);


		try {
			$TransportFeesModel->save($data2);
			$studentModel->save($data1);
			return $this->response->setJSON(array("success" => lang("app.recordSaved")));
		} catch (\Exception $e) {
			return $this->response->setJSON(array("error" => "Error: " . $e->getMessage()));
		}
	}

	public
	function download_library_template()
	{
		$this->_preset();

		$inputFileName = ("assets/templates/library_template.xlsx");
		header("Content-Type:   application/vnd.ms-excel; charset=utf-8");
		header("Content-type:   application/x-msexcel; charset=utf-8");
		header("Content-Disposition: attachment; filename=abc.xsl");
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Cache-Control: private", false);
		header('Content-Disposition: attachment; filename=Book_lists.xlsx');
		echo file_get_contents($inputFileName);
	}

	public
	function uploadBookExcel()
	{
		$url = $this->session->get("return_url");
		$this->_preset();
		set_time_limit(0);
		ini_set("memory_limit", -1);
		ini_set("max_execution_time", -1);
		$booksModel = new BookModel();
		$file_mimes = array('application/octet-stream', 'application/vnd.ms-excel', 'application/x-csv', 'text/x-csv', 'text/csv', 'application/csv', 'application/excel', 'application/vnd.msexcel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		if (isset($_FILES['documents']['name']) && in_array($_FILES['documents']['type'], $file_mimes)) {
			$name = $_FILES['documents']['name'];

			$arr_file = explode('.', $_FILES['documents']['name']);
			$extension = end($arr_file);
			if ('csv' == $extension) {
				$reader = new \PhpOffice\PhpSpreadsheet\Reader\Csv();
			} else {
				$reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
			}
			$spreadsheet = $reader->load($_FILES['documents']['tmp_name']);
			$sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
//			print_r($sheetData);
//			die();
			$i = 0;
			$empty = 0;
//				 echo "upload done";die();

			foreach ($sheetData as $sheet) {
				if ($i == 0) {
					$i++;
					continue;
				}
				if (empty($sheet['A'])) {
					$empty++;
					if ($empty > 1) {
						break;
					}
					continue;
				}

				$empty = 0;
				$i++;
				$data = array(
						"school_id" => $this->session->get("soma_school_id"),
						"title" => $this->_sanitize_txt($sheet['A']),
						"author" => $this->_sanitize_txt($sheet['B']),
						"quantity" => $this->_sanitize_txt($sheet['C']),
						"status" => 0,
						"created_by" => $this->session->get("soma_id"),
				);

				$query2 = $booksModel->save($data);
			}
			if (!$query2) {
				return $this->response->setJSON(array("error" => lang("app.recordNotSaved")));
			} else {
				return $this->response->setJSON(array("success" => lang("app.UploadedSuccessfully")));
			}
		}


	}

	public
	function get_coure_term($id, $class)
	{

		$CourseRecord = new CourseRecordModel();
		$terms = $CourseRecord->select("course_records.id,course_records.term,course_records.class")
				->where("course_records.id", $id)
				->where("course_records.class", $class)
				->get()->getRowArray();
		echo json_encode($terms);
	}

	public
	function change_course_data($type = 'term')
	{
		$this->_preset();

//		print_r($terms); die();
		$data = ["id" => $this->request->getPost("fId")];

		try {
			if ($type == 'term') {
				$courseRecordModel = new CourseRecordModel();
				$terms = implode(",", $this->request->getPost("Term[]"));
				$data["term"] = $terms;
				$courseRecordModel->save($data);
			} else if ($type == 'title') {
				$courseModel = new CourseModel();
				$title = $this->request->getPost("courseName");
				$data["title"] = $title;
				$courseModel->save($data);
			}
			return $this->response->setJSON(array("success" => lang("app.changesSaved")));
		} catch (\Exception $e) {
			return $this->response->setJSON(array("error" => "Error: " . $e->getMessage()));
		}
	}

	public
	function classDeliberation(): string
	{
		$this->_preset();
		$data = $this->data;
		$data['title'] = lang("app.deliberation");
		$data['subtitle'] = lang("app.deliberation");
		$data['page'] = "class-deliberation";
		$school_id = $this->session->get("soma_school_id");
		$acMdl = new AcademicYearModel();
		$data['years'] = $acMdl->select('id,title')->where("school_id", $school_id)
				->orderBy("id", 'DESC')->get()->getResultArray();
		$classMdl = new ClassesModel();
		$data['classes'] = $classMdl->select("classes.id,classes.title,d.title as department_name,d.code,l.title as level_name,l.id as level_id,
		,f.type,f.abbrev as faculty_code")
				->join("departments d", "d.id=classes.department")
				->join("levels l", "l.id=classes.level")
				->join("faculty f", "f.id=d.faculty_id")
				->where("classes.school_id", $school_id)
				->get()->getResultArray();
		if (isset($_POST['class'])) {
			//fetch class deliberation data
			$atMdl = new ActiveTermModel();
			$year = $data['academic_year'];
			$class = $this->request->getPost('class');
			$active_term = $atMdl->select("id")
					->where("academic_year", $year)->where("school_id", $school_id)
					->get()->getResultArray();
			if ($active_term == []) {
				echo "invalid data, please try again later";
				die();
			}
			$class_data = $classMdl->select("classes.id,l.title as level_name,l.id as level_id,
		,l.faculty_id")
					->join("departments d", "d.id=classes.department")
					->join("levels l", "l.id=classes.level")
					->where("classes.id", $class)
					->get(1)->getRow();
			if ($class_data == null) {
				echo "invalid class data, please try again later";
				die();
			}
			$data['deliberation_data'] = $this->get_deliberation_data($class_data, $data['academic_year']);
			$classBuilder = $classMdl->select("classes.id,classes.title,d.title as department_name,d.code,l.title as level_name,l.id as level_id,
		,f.type,f.abbrev as faculty_code")
					->join("departments d", "d.id=classes.department")
					->join("levels l", "l.id=classes.level")
					->join("faculty f", "f.id=d.faculty_id")
					->where("classes.school_id", $school_id);


			if ($class_data->level_id == 21) {
				//nursary load p1
				$classBuilder->where("l.id", "10");
			} else {
				$classBuilder->where("l.id", ($class_data->level_id + 1))
						->where("l.faculty_id", $class_data->faculty_id);
			}
			$data['next_classes'] = $classBuilder->get()->getResultArray();
			$active_term = array_column($active_term, 'id', 'key');
			$active_term_id = implode(",", $active_term);
			$StudentModel = new StudentModel();
			$data['page'] = "Result_record";
			$data['class_id'] = $class;
			$data['term'] = 4;
			$data['year'] = $year;
			$data['school_id'] = $school_id;
			$data['courses'] = $this->get_courses($class, 4, $year);
			$students = $StudentModel->select("students.id,students.regno,
														students.photo,students.fname,students.dob,
														students.lname,c.id as class_id,
														c.title,d.title as department_name,
														group_concat(di.marks,':',di.term) as displine_marks,d.id as department_id,
														d.code,l.title as level_name,f.title as fac_title,
														f.type,f.abbrev as faculty_code,f.id as fac_id,
														c.level,c.id as class,cr.year")
					->join('class_records cr', 'cr.student=students.id')
					->join('deliberation_records dr', 'dr.studentId=students.id', 'left')
					->join('classes c', 'c.id=cr.class')
					->join('departments d', 'd.id=c.department')
					->join('levels l', 'l.id=c.level')
					->join('faculty f', 'f.id=d.faculty_id')
					->join('schools sk', 'sk.id=students.school_id')
					// ->join("active_term at", "at.id=sk.active_term")
					->join("(select sum(di.marks) as marks,at.term,di.active_term,di.student_id from disciplines di inner join active_term as at
			ON at.id = di.active_term where di.school_id={$school_id} AND di.active_term in ($active_term_id) group by di.active_term,di.student_id) as di"
							, 'di.student_id=students.id', 'LEFT')
					->where("c.school_id", $school_id)
					// ->where("sk.active_term", $active_term->id)
					->where("dr.id", null)
					->where("cr.status", "1")
					->where("c.id", $class)
					->where("cr.year", $year)
					->orderBy("students.fname", "ASC")
					->groupBy('students.id')
//			->limit(2)
					->get()->getResultArray();
			$data['students'] = $students;
		}
		$data['content'] = view("pages/class_deliberation", $data);
		return view('main', $data);
	}

	public
	function finish_deliberation(): string
	{
		$this->_preset();
		$data = $this->data;
		$data['title'] = lang("app.deliberation");
		$data['subtitle'] = lang("app.deliberation");
		$data['page'] = "finish-deliberation";
		$school_id = $this->session->get("soma_school_id");
		$acMdl = new AcademicYearModel();
		$data['years'] = $acMdl->select('id,title')->where("school_id", $school_id)
				->orderBy("id", 'DESC')->get()->getResultArray();
		$classMdl = new ClassesModel();
		$data['classes'] = $classMdl->select("classes.id,classes.title,d.title as department_name,d.code,l.title as level_name,l.id as level_id,
		,f.type,f.abbrev as faculty_code")
				->join("departments d", "d.id=classes.department")
				->join("levels l", "l.id=classes.level")
				->join("faculty f", "f.id=d.faculty_id")
				->join("deliberation_records dr", "classes.id=dr.oldClass")
				->where("classes.school_id", $school_id)
				->where("dr.status", 0)
				->groupBy("classes.id")
				->get()->getResultArray();
		$data['content'] = view("pages/finish_deliberation", $data);
		return view('main', $data);
	}

	function get_deliberation_data($class_data, $academic)
	{
		$dMdl = new DeliberationCriteriaModel();
		$data = $dMdl->select("deliberation_criteria.id,verdict,group_concat(dc.conditions,':',dc.value,':',dc.type) as conditions,
		 df.courses")
				->join('deliberation_conditions dc', 'dc.deliberation_id = deliberation_criteria.id', 'left')
				->join("(select group_concat(df.categoryId,':',df.course_count) as courses,deliberationId from deliberation_failed_courses as df
			 group by df.deliberationId) as df"
						, 'df.deliberationId = deliberation_criteria.id', 'left')
				->where('deliberation_criteria.faculty_id', $class_data->faculty_id)
				->where('deliberation_criteria.academic_year', $academic)
				->groupBy("deliberation_criteria.id")
				->get()->getResultArray();
		return $data;
	}

	function get_deliberation_records($classId)
	{
		$classMdl = new ClassesModel();
		$classData = $classMdl->select("classes.id,classes.title,d.title as department_name,d.code,l.title as level_name,l.id as level_id,
		,f.type,f.abbrev as faculty_code")
				->join("departments d", "d.id=classes.department")
				->join("levels l", "l.id=classes.level")
				->join("faculty f", "f.id=d.faculty_id")
				->where("classes.id", $classId)
				->get()->getRow();
		if ($classData == null) {
			return $this->response->setJSON(['status' => 'error', 'message' => 'Class not found']);
		}
		$dMdl = new DeliberationRecords();
		$data = $dMdl->select("deliberation_records.id,deliberation_records.decision,deliberation_records.decisionType,st.fname,st.lname,
		regno,deliberation_records.created_at,concat(l.title,' ',d.code,' ',c.title) as newClass,concat(sf.fname,' ',sf.lname) as operator")
				->join('students st', 'st.id = deliberation_records.studentId')
				->join('classes c', 'c.id = deliberation_records.newClass')
				->join("departments d", "d.id=c.department")
				->join("levels l", "l.id=c.level")
				->join("faculty f", "f.id=d.faculty_id")
				->join("staffs sf", "sf.id=deliberation_records.operator")
				->where('deliberation_records.oldClass', $classId)
				->where('deliberation_records.status', 0)
				->groupBy("deliberation_records.studentId")
				->orderBy("st.fname", 'ASC')
				->get()->getResultArray();
		$response = [];
		$students = [];
		$decisionSummary = [];
		$i = 0;
		foreach ($data as $dt) {
			if ($i == 0) {
				$response['newClass'] = $dt['newClass'];
				$response['operator'] = $dt['operator'];
			}

			unset($dt['operator']);
			unset($dt['newClass']);
			$dt['decision'] = verdictText($dt['decision']);
			$dt['decisionType'] = decisionTypeStr($dt['decisionType']);
			$students[] = $dt;

			if (isset($decisionSummary[$dt['decision']])) {
				$decisionSummary[$dt['decision']] += 1;
			} else {
				$decisionSummary[$dt['decision']] = 1;
			}
			$i++;
		}
		$response['oldClass'] = $classData->level_name . ' ' . $classData->code . ' ' . $classData->title;
		$response['summaries'] = $decisionSummary;
		$response['students'] = $students;
		return $this->response->setJSON($response);
	}

	function process_deliberation()
	{
		$dMdl = new DeliberationRecords();
		$acMdl = new AcademicYearModel();
		$school_id = $this->session->get("soma_school_id");
		$decisions = $this->request->getPost('decisions[]');
		$next_class = $this->request->getPost('next_class');
		$class = $this->request->getPost('class');
		$next_academic = $this->request->getPost('next_academic');
		$yearData = $acMdl->select('id')
				->where('title', $next_academic)
				->where('school_id', $school_id)->get(1)->getRow();
		if ($yearData == null) {
			//create new
			try {
				$yearId = $acMdl->insert(['title' => $next_academic, 'school_id' => $school_id]);
			} catch (\ReflectionException $e) {
				$this->session->setFlashdata("error", "Deliberation failed, failed to create new academic year");
				return redirect()->to(base_url('class-deliberation'));
			}
		} else {
			$yearId = $yearData->id;
		}
		$a = 0;
		$noDecision = 0;
		foreach ($decisions as $decision) {
			$dt = explode("_", $decision);
			if (count($dt) != 4) {
				$noDecision++;
				continue;
			}
			$student = $dt[count($dt) - 4];
			$decision = $dt[count($dt) - 3];
			$deliberationId = $dt[count($dt) - 2];
			$type = $dt[count($dt) - 1];
			try {
				$dMdl->save(['studentId' => $student, 'oldClass' => $class, 'newClass' => $next_class
					, "nextAcademicYear" => $yearId, "decision" => $decision, "decisionType" => $type
					, 'deliberationId' => $deliberationId, 'operator' => $this->session->get("soma_id")]);
				$a++;
			} catch (\Exception $e) {
			}
		}
		if ($a == 0) {
			$this->session->setFlashdata("error", "Deliberation failed");
		} else if ($a != count($decisions)) {
			$this->session->setFlashdata("success", "Some deliberation failed #" . (count($decisions) - $a) . " over $a");
		} else {
			if ($noDecision == 0) {
				$this->session->setFlashdata("success", "Deliberation completed on $a students");
			} else {
				$this->session->setFlashdata("success", "Deliberation completed on $a students,
				but there are $noDecision pending decisions");
			}
		}
		return redirect()->to(base_url('class-deliberation'));
	}

	function process_finish_deliberation()
	{
		$this->_preset();
		$dMdl = new DeliberationRecords();
		$cMdl = new ClassRecordModel();
		$atMdl = new ActiveTermModel();
		$class = $this->request->getPost('class');

		if (!$this->verify_password(true)) {
			$this->session->setFlashdata("error", "Invalid password, please try again");
			return redirect()->to(base_url('finish_deliberation'));
		}
		//check if all student deliberation are made
		$pendingStudents = $cMdl->select('id')
				->join('deliberation_records dr', 'class_records.student=dr.studentId', 'LEFT')
				->where('class_records.class', $class)
				->where('class_records.status', 1)
				->where('dr.id', null)
				->countAllResults();
		//disabled for a while
//		if ($pendingStudents != 0) {
//			$this->session->setFlashdata("error", "Finish Deliberation failed, there is {$pendingStudents} pending students");
//			return redirect()->to(base_url('finish_deliberation'));
//		}
		$yearData = $atMdl->select('id')
				->where('academic_year', $this->data['academic_year'])
				->countAllResults();
		if ($yearData > 1) {
			//not new
			$this->session->setFlashdata("error", "it seems that you are not in new academic year");
			return redirect()->to(base_url('finish_deliberation'));
		}
		$deliberationRecords = $dMdl->select('id,studentId,newClass,oldClass,decision')
				->where('status', 0)
//				->whereIn('decision',[1,2])
				->where('oldClass', $class)
				->get()->getResultArray();
		if (count($deliberationRecords) == 0) {
			//not new
			$this->session->setFlashdata("error", "No pending deliberation records for the selected class");
			return redirect()->to(base_url('finish_deliberation'));
		}
		$a = 0;
		$noDecision = 0;
		foreach ($deliberationRecords as $decision) {
			try {
				if ($decision['decision'] == "1") {
					//promoted
					$cMdl->save(['student' => $decision['studentId'], 'class' => $decision['newClass']
						, 'year' => $this->data['academic_year'], 'status' => 1]);
				} else if ($decision['decision'] == "2") {
					//retake
					$cMdl->save(['student' => $decision['studentId'], 'class' => $decision['oldClass']
						, 'year' => $this->data['academic_year'], 'status' => 1]);
				}
				$dMdl->save(['id' => $decision['id'], 'status' => 1]);
				$a++;
			} catch (\Exception $e) {
			}
		}
		if ($a == 0) {
			$this->session->setFlashdata("error", "Finish Deliberation failed");
		} else if ($a != count($deliberationRecords)) {
			$this->session->setFlashdata("success", "Some deliberation failed #" . (count($decisions) - $a) . " over $a");
		} else {
			if ($noDecision == 0) {
				$this->session->setFlashdata("success", "Deliberation completed and student moved to new classes. #$a students");
			} else {
				$this->session->setFlashdata("success", "Deliberation completed on $a students,
				but there are $noDecision failed Deliberation");
			}
		}
		return redirect()->to(base_url('finish_deliberation'));
	}

	function deliberation(): string
	{
		$this->_preset();
		$data = $this->data;
		$data['title'] = lang("app.deliberation");
		$data['subtitle'] = lang("app.deliberation");
		$data['page'] = "Deliberation";
		$activeModel = new ActiveTermModel();
		$VerdictModel = new VerdictModel();
		$data['firstverdicts'] = $VerdictModel->select("verdicts.*")->where("type", 1)->get()->getResultArray();
		$data['secondverdicts'] = $VerdictModel->select("verdicts.*")->where("type", 2)->get()->getResultArray();
		$acMdl = new AcademicYearModel();
		$data['years'] = $acMdl->select('id,title')->where("school_id", $this->session->get("soma_school_id"))
				->orderBy("id", 'DESC')->get()->getResultArray();
		$data['content'] = view("pages/marks/deliberation", $data);
		return view('main', $data);
	}

	public
	function get_deliberation_criteria($year)
	{
		$classModel = new ClassesModel();
		$criterias = $classModel->select("classes.id,classes.title,d.title as department_name,d.id as department_id,d.code,l.title as level_name
			,f.type,f.abbrev as faculty_code,f.id as facul_id,dl.id as deliberation_id,dl.id as criteria_id,dl.min_marks,dl.course_number,dl.displine_min_marks,dl.class_id")
				->join("class_records cr", "cr.class=classes.id", "LEFT")
				->join("deliberation_criteria dl", "dl.class_id=classes.id", "LEFT")
				->join("departments d", "d.id=classes.department")
				->join("levels l", "l.id=classes.level")
				->join("faculty f", "f.id=d.faculty_id")
				->where("classes.school_id", $_SESSION["soma_school_id"])
//			->where("cr.year",$year)
				->groupBy("classes.id")
				->get()->getResultArray();
		$i = 1;
		foreach ($criterias as $criteria) {
			echo "
				<tr >
									<td><input value='" . $criteria['criteria_id'] . "' type='hidden' class='form-control' required name='criteria_id[]'>" . $i . "</td>
									<td><input value='" . $criteria['id'] . "' type='hidden' class='form-control' required name='class_id[]'>" . $criteria['level_name'] . " " . $criteria['code'] . " " . $criteria['title'] . "</td>
									<td><input value='" . $criteria['min_marks'] . "' type='number' class='form-control' required name='min_marks[]'></td>
									<td><input value='" . $criteria['course_number'] . "'  type='number' class='form-control' required name='course_number[]'></td>
									<td><input value='" . $criteria['displine_min_marks'] . "'  type='number' class='form-control' required name='dispilne_marks[]'></td>
								</tr>
			";
			$i++;
		}
	}

	public
	function manipulate_delib_criteria()
	{
		$this->_preset();
		$class_id = $this->request->getPost("class_id[]");
		$min_marks = $this->request->getPost("min_marks[]");
		$course_num = $this->request->getPost("course_number[]");
		$dispMarks = $this->request->getPost("dispilne_marks[]");
		$criteria_id = $this->request->getPost("criteria_id[]");

		$delModel = new DeliberationCriteriaModel();
		$i = 0;
		foreach ($class_id as $std) {
			$a = $std;
			if ($criteria_id[$i] == 0) {

				$data = array(
						"school_id" => $this->session->get("soma_school_id"),
						"class_id" => $a,
						"min_marks" => $min_marks[$i],
						"course_number" => $course_num[$i],
						"displine_min_marks" => $dispMarks[$i],
						"academic_year" => $this->data['academic_year'],
						"created_by" => $this->session->get("soma_id"));
			} else {
				$data = array(
						"id" => $criteria_id[$i],
						"min_marks" => $min_marks[$i],
						"course_number" => $course_num[$i],
						"displine_min_marks" => $dispMarks[$i]);
			}

			try {
				$delModel->save($data);

			} catch (\Exception $e) {
				return $this->response->setJSON(array("error" => lang("app.OopsAction") . $e->getMessage()));
			}
			$i++;
		}
		return $this->response->setJSON(array("success" => lang("app.recordSaved")));
	}

	public
	function manipulate_delib_manual()
	{
		$this->_preset();
		$student = $this->request->getPost("studentId[]");
		$first_verdict = $this->request->getPost("first_verdict");

		$ManualModel = new ManualDecisionModel();
		$i = 0;
		foreach ($student as $std) {
			$a = $std;
			$studentData = $ManualModel->select("manual_decisions.student,manual_decisions.academic_year,concat(s.fname,' ',s.lname) as names")
					->join("students s", "s.id=manual_decisions.student")
					->where("student", $a)
					->where("academic_year", $this->data['academic_year'])
					->get()->getRowArray();
			if ($studentData != "") {
				return $this->response->setJSON(array("error" => lang("app.system") . " Skype ," . $studentData['names'] . lang("app.isAlreadyDeliberated")));
			}
			$data = array(
					"school_id" => $this->session->get("soma_school_id"),
					"student" => $a,
					"academic_year" => $this->data['academic_year'],
					"first_verdict" => $first_verdict,
					"created_by" => $this->session->get("soma_id"));


			try {
				$ManualModel->save($data);

			} catch (\Exception $e) {
				return $this->response->setJSON(array("error" => lang("app.OopsAction") . $e->getMessage()));
			}
			$i++;
		}
		return $this->response->setJSON(array("success" => lang("app.recordSaved")));
	}

	public
	function manipulate_change_second_verdict()
	{
		$this->_preset();
		$id = $this->request->getPost("fId");
		$second_verdict = $this->request->getPost("second_verdict");
		$ManualModel = new ManualDecisionModel();

		$data = array(
				"id" => $id,
				"second_verdict" => $second_verdict,
				"updated_by" => $this->session->get("soma_id"));

		try {
			$ManualModel->save($data);
			return $this->response->setJSON(array("success" => lang("app.recordSaved")));
		} catch (\Exception $e) {
			return $this->response->setJSON(array("error" => lang("app.OopsAction") . $e->getMessage()));
		}


	}

	public
	function get_manual_student($year)
	{
		$studentModel = new StudentModel();
		$school_id = $this->session->get("soma_school_id");
		$students = $studentModel->select("students.id,students.regno,concat(students.fname,' ',students.lname) as names,v.title as first_verdict,v2.title as second_verdict,m.id as mid")
				->join("manual_decisions m", "m.student=students.id")
				->join("verdicts v", "v.id=m.first_verdict", "LEFT")
				->join("verdicts v2", "v2.id=m.second_verdict", "LEFT")
				->where("m.academic_year", $year)
				->where("m.school_id", $school_id)
				->get()->getResultArray();
		$i = 1;
		foreach ($students as $student) {
			echo "
			<tr>
			<td >" . $i . "</td>
			<td>" . $student['regno'] . "</td>
			<td>" . $student['names'] . "</td>
			<td>" . $student['first_verdict'] . "</td>
			<td>" . $student['second_verdict'] . "
			<i class='fa fa-pencil-alt link' data-toggle='modal' data-target='#changeVerdictModal' data-id='" . $student['mid'] . "'></i>
			</td>
			</tr>
			";
			$i++;
		}
	}

	public
	function get_student_verdit($id)
	{
		$studentModel = new ManualDecisionModel();
		$school_id = $this->session->get("soma_school_id");
		$verdict = $studentModel->select("manual_decisions.id,manual_decisions.second_verdict")
				->where("manual_decisions.id", $id)
				->where("manual_decisions.school_id", $school_id)
				->get()->getRowArray();
		echo json_encode($verdict);

	}

	public
	function get_verdit($id)
	{
		$verdictModel = new VerdictModel();
		$school_id = $this->session->get("soma_school_id");
		$verdict = $verdictModel->select("verdicts.*")
				->where("id", $id)
				->where("school_id", $school_id)
				->get()->getRowArray();
		echo json_encode($verdict);

	}

	public
	function verdicts()
	{
		$this->_preset();
		$data = $this->data;
		$data['title'] = lang("app.verdicts");
		$data['subtitle'] = lang("app.verdicts");
		$data['page'] = "Verdicts";
		$VerdictModel = new VerdictModel();
		$data['verdicts'] = $VerdictModel->select("verdicts.*")->get()->getResultArray();
		$data['content'] = view("pages/marks/verdicts", $data);
		return view('main', $data);
	}

	public
	function manipulate_verdicts()
	{
		$this->_preset();
		$id = $this->request->getPost("fId");
		$verdict = $this->request->getPost("title");
		$type = $this->request->getPost("type");
		$VerdictModel = new VerdictModel();
		if ($id == "") {
			$data = array(
					"school_id" => $this->session->get("soma_school_id"),
					"title" => $verdict,
					"type" => $type,
					"created_by" => $this->session->get("soma_id"));
		} else {
			$data = array(
					"id" => $id,
					"title" => $verdict,
					"type" => $type,
					"updated_by" => $this->session->get("soma_id"));
		}
		try {
			$VerdictModel->save($data);
			return $this->response->setJSON(array("success" => lang("app.verdictSaved")));
		} catch (\Exception $e) {
			return $this->response->setJSON(array("error" => lang("app.OopsAction") . $e->getMessage()));
		}
	}

	public
	function save_academic_year()
	{
		$this->_preset();
		$title = $this->request->getPost("title");
		if (strlen($title) < 4) {
			return $this->response->setStatusCode(400)->setJSON(array("message" => lang("app.provideAcademicTitle")));
		}
		$acMdl = new AcademicYearModel();
		try {
			$id = $acMdl->insert(array('title' => $title, 'school_id' => $this->session->get("soma_school_id")));
			return $this->response->setJSON(
					array("message" => lang("app.academicSaved"), 'id' => $id, 'title' => $title)
			);
		} catch (\Exception $e) {
			if ($e->getCode() == 1062) {
				return $this->response->setStatusCode(500)->setJSON(array("message" => lang("app.academicExists")));
			}
			return $this->response->setStatusCode(500)->setJSON(array("message" => lang("app.OopsAction") . $e->getMessage()));
		}
	}

	public
	function school_fees_payments($type)
	{
		$this->_preset();
		$data = $this->data;
		$schoolFeesModel = new SchoolFeesModel();
		$extraFeesModel = new ExtraFeesModel();
		$studentMdl = new StudentModel();
		$school_id = $this->session->get("soma_school_id");
		if ($type == 1) {
			$data['title'] = lang("app.finishAll");
			$data['subtitle'] = lang("app.finishAll");
		}
		if ($type == 2) {
			$data['title'] = lang("app.payHalf");
			$data['subtitle'] = lang("app.payHalf");
		}
		if ($type == 3) {
			$data['title'] = lang("app.nonePay");
			$data['subtitle'] = lang("app.nonePay");
		}
		$data['type'] = $type;
		$data['page'] = 'school_fees_payments';
		$data['schoolfees'] = $studentMdl->select("students.id,concat(students.fname,' ',students.lname) as student,
															(sf.amount+coalesce(fd.amount,0)) as expected,sum(fr.amount) as paid,
															,fr.due_date
															,d.title as department_name,
															,cl.title
															,d.code,l.title as level_name
															,f.abbrev as faculty_code")
				->join("class_records cr", "cr.student=students.id", "LEFT")
				->join("classes cl", "cl.id=cr.class", "LEFT")
				->join("levels l", "l.id=cl.level", "LEFT")
				->join("departments d", "d.id=cl.department", "LEFT")
				->join("faculty f", "f.id=d.faculty_id")
				->join("school_fees sf", "sf.level=l.id and sf.department=d.id ")
				->join("fees_records fr", "fr.fees_id=sf.id and fr.student_id=students.id and fr.fees_type=0 and fr.status=1", "LEFT ")
				->join("(select sum(amount) as amount,feesId,student from school_fees_discount group by student,feesId) fd", "fd.feesId=sf.id AND fd.student=students.id", "LEFT")
				->where("sf.term", $this->data['term'])
				->where("sf.academic_year", $this->data['academic_year'])
				->where("sf.school_id", $school_id)
				->groupBy("students.id")
				->get()->getResultArray();
		$data['content'] = view("pages/PaymentReportView", $data);
		return view('main', $data);
	}

	public
	function extra_fees_payments($type)
	{
		$this->_preset();
		$data = $this->data;
		$data['title'] = lang("app.paymentView");
		$extraFeesModel = new ExtraFeesModel();
		$studentMdl = new StudentModel();
		$school_id = $this->session->get("soma_school_id");
		if ($type == 1) {
			$data['title'] = lang("app.finishAllExtra");
			$data['subtitle'] = lang("app.finishAllExtra");
		}
		if ($type == 2) {
			$data['title'] = lang("app.payHalfExtra");
			$data['subtitle'] = lang("app.payHalfExtra");
		}
		if ($type == 3) {
			$data['title'] = lang("app.nonePay");
			$data['subtitle'] = lang("app.nonePay");
		}
		$data['type'] = $type;
		$data['page'] = 'Extra_fees_payments';
		$data['schoolfees'] = $studentMdl->select("students.id,ex.amount as expected,sum(fr.amount) as paid
															,fr.due_date
														 	,concat(students.fname,' ',students.lname) as student,
															,d.title as department_name,
															,cl.title
															,d.code,l.title as level_name
															,f.abbrev as faculty_code")
				->join("class_records cr", "cr.student=students.id", "LEFT")
				->join("classes cl", "cl.id=cr.class", "LEFT")
				->join("levels l", "l.id=cl.level", "LEFT")
				->join("departments d", "d.id=cl.department", "LEFT")
				->join("faculty f", "f.id=d.faculty_id")
				->join("extra_fees ex", "(ex.type_id=cl.id AND ex.type=0) OR (ex.type_id=students.id AND ex.type=1)")
				->join("fees_records fr", "fr.fees_id=ex.id and fr.student_id=students.id and fr.fees_type=1 and fr.status=1", "LEFT ")
				->where("ex.term", $this->data['term'])
				->where("ex.academic_year", $this->data['academic_year'])
				->where("ex.school_id", $school_id)
				->groupBy("students.id")
				->get()->getResultArray();
		$data['content'] = view("pages/ExtraPaymentReportView", $data);
		return view('main', $data);
	}

	public
	function test_export()
	{
		$path = FCPATH . "assets/templates/sopywe_" . time() . ".sql";
//		exec("mysqldump -u dev -p 'Qonics!' sopyrwa_db > ".$path." > /dev/null &");
		exec('mysqldump --user=dev --password=Qonics! --host=localhost sopyrwa_db > ' . $path);
		echo $path;
	}

	public
	function getFeesHistoricalAjax($student = 0, $year = 0)
	{
		$this->_preset();
		$feesRecordMdl = new FeesRecordModel();
		$extraFees = $feesRecordMdl->select("fees_records.id,fees_records.amount,1 as type,fees_records.created_at as date,
		concat(extra.title,' (Extra fees)') as item,extra.term,fees_records.payment_mode,fees_records.status")
				->join("extra_fees extra", "fees_records.fees_id=extra.id and fees_records.fees_type=1")
				->join("academic_year ac", "ac.id=extra.academic_year")
				->where("fees_records.student_id", $student)
				->where("ac.id", $year)
				->orderBy("fees_records.id", 'DESC')
				->get()->getResultArray();
		$schoolFees = $feesRecordMdl->select("fees_records.id,fees_records.amount,0 as type,fees_records.created_at as date
		,if(fees_records.fees_type=0,'School fees','item') as item,sf.term,fees_records.payment_mode,fees_records.status")
				->join("school_fees sf", "sf.id=fees_records.fees_id and fees_records.fees_type=0")
				->join("academic_year ac", "ac.id=sf.academic_year")
				->where("fees_records.student_id", $student)
				->where("ac.id", $year)
				->orderBy("fees_records.id", 'DESC')
				->get()->getResultArray();
		return $this->response->setJSON(array_merge($extraFees, $schoolFees));
	}

	public
	function printFeesHistory($rows = null, $student = null)
	{
		if ($rows == null || $student == null) {
			echo "invalid data, please try again later";
			die();
		}
		$this->_preset();
		$data = $this->data;
		$feesRecordMdl = new FeesRecordModel();
		$studentMdl = new StudentModel();
		$extraFeesData = [];
		$schoolFeesData = [];
		foreach (explode("-", $rows) as $item) {
			$ii = explode(':', urldecode($item));
			if (count($ii) != 2) {
				echo "invalid data, please try again later";
				die();
			}
			if ($ii[1] == '1') {
				//extra
				$extraFeesData[] = $ii[0];
			}
			if ($ii[1] == '0') {
				//extra
				$schoolFeesData[] = $ii[0];
			}
		}
		if (count($extraFeesData) > 0) {
			$extraFees = $feesRecordMdl->select("fees_records.id,fees_records.amount,fees_records.created_at as date,
			concat(extra.title,' (Extra fees)') as item,extra.term,fees_records.payment_mode")
					->join("extra_fees extra", "fees_records.fees_id=extra.id and fees_records.fees_type=1 and fees_records.status=1")
					->join("academic_year ac", "ac.id=extra.academic_year")
					->where("fees_records.student_id", $student)
//				->where("ac.id",$year)
					->whereIn("fees_records.id", $extraFeesData)
					->get()->getResultArray();
		}
		if (count($schoolFeesData) > 0) {
			$schoolFees = $feesRecordMdl->select("fees_records.id,fees_records.amount,fees_records.created_at as date,
			if(fees_records.fees_type=0,'School fees','item') as item,sf.term,fees_records.payment_mode")
					->join("school_fees sf", "sf.id=fees_records.fees_id and fees_records.fees_type=0 and fees_records.status=1")
					->join("academic_year ac", "ac.id=sf.academic_year")
					->where("fees_records.student_id", $student)
//				->where("ac.id", $year)
					->whereIn("fees_records.id", $schoolFeesData)
					->get()->getResultArray();
		}
		$data['records'] = array_merge($extraFees ?? [], $schoolFees ?? []);
		$data['student'] = $studentMdl->get_student($student, 'students.id', null, true, $this->data['academic_year']);
		$html = view("toPrint/receipt", $data);
		try {
			$mask = FCPATH . "assets/templates/*.html";
			array_map('unlink', glob($mask));//clear previous cards
			$wkhtmltopdf = new Wkhtmltopdf(array('path' => FCPATH . 'assets/templates/'));
			$wkhtmltopdf->setTitle("Cash deposit receipt");
			$wkhtmltopdf->setHtml($html);
			$wkhtmltopdf->setOrientation("portrait");
			$wkhtmltopdf->setOptions(array("page-width" => "400px", "page-height" => "1030px"));
			$wkhtmltopdf->setMargins(array("top" => 0, "left" => 0, "right" => 0, "bottom" => 0));
			$wkhtmltopdf->output(Wkhtmltopdf::MODE_EMBEDDED, "cash_receipt_" . time() . ".pdf");
		} catch (\Exception $e) {
			echo $e->getMessage();
		}
	}

	public
	function feesReport($pdf)
	{
		$this->_preset(1, 3, 4, 5, 6);
		$data = $this->data;
		$data['title'] = lang("app.studentsLists");
		$data['subtitle'] = lang("app.viewAllStudent");
		$data['page'] = "students";
		$classe = $this->request->getGet("c") ?? "-1";
		$academic = $this->request->getGet("academic") ?? $data['academic_year'];
		$term = $this->request->getGet("term") ?? $data['term'];
		$filter = $this->request->getGet("filter") ?? "0";
		$classMdl = new ClassesModel();
		$school_id = $this->session->get("soma_school_id");
		$studentMdl = new StudentModel();
		$acMdl = new AcademicYearModel();
		if ($pdf == 1) {
			$data['years'] = $acMdl->select('id,title')->where("id", $academic)->get()->getRowArray();
			$data['classe'] = $classMdl->select("classes.id,classes.title,d.title as department_name,d.code as dept_code,l.title as level_name
		,f.type,f.abbrev as faculty_code,concat(s.fname,' ',s.lname) as mentor_name,s.id as idstf")
					->join("departments d", "d.id=classes.department")
					->join("levels l", "l.id=classes.level")
					->join("faculty f", "f.id=d.faculty_id")
					->join("staffs s", "s.id=classes.mentor", "LEFT")
					->where("classes.school_id", $school_id)
					->where("classes.id", $classe)
					->get()->getRowArray();
		} else {
			$data['years'] = $acMdl->select('id,title')->where("school_id", $school_id)
					->orderBy("id", 'DESC')->get()->getResultArray();
			$data['classes'] = $classMdl->select("classes.id,classes.title,d.title as department_name,d.code as dept_code,l.title as level_name
		,f.type,f.abbrev as faculty_code,concat(s.fname,' ',s.lname) as mentor_name,s.id as idstf")
					->join("departments d", "d.id=classes.department")
					->join("levels l", "l.id=classes.level")
					->join("faculty f", "f.id=d.faculty_id")
					->join("staffs s", "s.id=classes.mentor", "LEFT")
					->where("classes.school_id", $school_id)
					->get()->getResultArray();
		}

		$studentsQuery = $studentMdl->select("concat(students.fname,' ',students.lname) as student,students.id as student_id,
		students.studying_mode,ft_phone,mt_phone,gd_phone,
		students.regno,
		students.sex,
		cl.id,cl.title as class,
		d.title as department_name,
		d.code as dept_code,
		l.title as level_name,
		,f.type,f.abbrev as faculty_code,
		 (COALESCE(sf.amount,0) + COALESCE(sum(ex.amount),0) + COALESCE(sum(student.amount),0) + coalesce(fd.amount,0)) as amount,
		(COALESCE(fr.amount,0) + COALESCE(extraPaid.amount,0) + COALESCE(extraPaidSingle.amount,0)) as paid")
				->join("class_records cr", "cr.student=students.id")
				->join("classes cl", "cl.id=cr.class")
				->join("departments d", "d.id=cl.department")
				->join("levels l", "l.id=cl.level")
				->join("faculty f", "f.id=d.faculty_id")
				->join("(select sf.id,sf.level,sf.department,sf.amount from school_fees sf where sf.term=$term and
			sf.academic_year=$academic and sf.school_id = $school_id group by sf.id) sf", "sf.level=l.id and sf.department=d.id", "LEFT")
				->join("(select sum(amount) as amount,feesId,student from school_fees_discount group by student,feesId) fd", "fd.feesId=sf.id AND fd.student=students.id", "LEFT")
				->join("(select ex.id,ex.type_id,ex.amount from extra_fees ex where ex.type=0 and ex.term=$term and
			ex.academic_year=$academic group by ex.id) ex", "ex.type_id=cl.id", "LEFT")
				->join("(select ex.id,ex.type_id,ex.amount from extra_fees ex where ex.type=1 and ex.term=$term and
			ex.academic_year=$academic group by ex.id) student", "student.type_id=students.id", "LEFT")
				->join("(select fr.student_id,fr.fees_id,sum(fr.amount) as amount from fees_records fr inner join school_fees sc ON sc.id = fr.fees_id
			where fr.fees_type=0 and fr.status=1 and sc.term=$term and sc.academic_year=$academic and sc.school_id = $school_id group by fr.student_id) fr", "fr.student_id=students.id", "LEFT")
				->join("(select fr.student_id,fr.fees_id,sum(fr.amount) as amount,ex.type_id,ex.type from fees_records fr inner join extra_fees ex ON ex.id = fr.fees_id
			where fr.fees_type=1 and fr.status=1 and ex.type_id=$classe and ex.type=0 and ex.term=$term and ex.academic_year=$academic and ex.school_id = $school_id group by fr.student_id) extraPaid", "extraPaid.student_id=students.id", "LEFT")
				->join("(select fr.student_id,fr.fees_id,sum(fr.amount) as amount,ex.type_id,ex.type from fees_records fr
			inner join extra_fees ex ON ex.id = fr.fees_id and ex.type_id = fr.student_id
			where fr.fees_type=1 and fr.status=1 and ex.type=1 and ex.term=$term and ex.academic_year=$academic and ex.school_id = $school_id group by fr.student_id) extraPaidSingle", "extraPaidSingle.student_id=students.id", "LEFT")
//			->where("sf.school_id", $school_id)
				->where("cr.year", $academic)
				->where("cl.id", $classe)
				->groupBy("students.id");
		if ($filter == 1) {
			$studentsQuery->having("paid", "amount", false);
		} else if ($filter == 2) {
			$studentsQuery->having("paid !=", 'amount', false)
					->having("paid >", 0, false);
		} else if ($filter == 3) {
			$studentsQuery->having("paid", 0, false);
		}
		$students = $studentsQuery->get()->getResultArray();
//		echo '<pre>';var_dump($students);die();
		$data['students'] = $students;
		$data['class_id'] = $classe;
		$data['year_id'] = $academic;
		$data['term'] = $term;
		$data['filter'] = $filter;
		$data['pdf'] = $pdf;
		if ($pdf == 1) {
			$html = view("pages/systemReports/feesStatementInPdf", $data);
			try {
				$mask = FCPATH . "assets/templates/*.html";
				array_map('unlink', glob($mask));//clear previous cards
				$wkhtmltopdf = new Wkhtmltopdf(array('path' => FCPATH . 'assets/templates/'));
				$wkhtmltopdf->setTitle($data['title']);
				$wkhtmltopdf->setHtml($html);
				$wkhtmltopdf->setPageSize("A4");
				$wkhtmltopdf->setOrientation("portrait");
				//					$wkhtmltopdf->setOptions(array("page-width" => "278px", "page-height" => "430px"));
				$wkhtmltopdf->setMargins(array("top" => 1, "left" => 0, "right" => 0, "bottom" => 1));
				$wkhtmltopdf->output(Wkhtmltopdf::MODE_EMBEDDED, $data['title'] . "_" . time() . ".pdf");
			} catch (\Exception $e) {
				echo $e->getMessage();
			}
		} else if ($pdf == 2) {
			//send sms
			$all = 0;
			$sent = 0;
			$smsMdl = new SmsModel();
			$smsRMdl = new SmsRecipientModel();
			if (count($students) > $this->data['remaining_sms']) {
				return $this->response->setJSON(['error' => "SMS can not be sent, Remaining balance is " . $this->data['remaining_sms']]);
			}

			foreach ($students as $student) {
				$amount = $student['amount'] - $student['paid'];
				if ($amount > 0) {
					$phone = '';
					if (strlen($student['ft_phone']) > 3) {
						$phone = $student['ft_phone'];
					} else if (strlen($student['mt_phone']) > 3) {
						$phone = $student['mt_phone'];
					} else if (strlen($student['gd_phone']) > 3) {
						$phone = $student['gd_phone'];
					}
					if ($phone < 5) {
						continue;
					}
					$all++;
					try {
						$msg = "Mubyeyi dufatanije kurera {$student['student']},turakwibutsa kwishyura umwenda ufite ungana na " . number_format($amount);
						$sid = $smsMdl->insert(array("school_id" => $this->session->get("soma_school_id")
						, "active_term" => $this->data['active_term'], "content" => $msg, "recipient_type" => 0
						, "subject" => "Payment"));
						if ($sid === false)
							return $this->response->setJSON(array("error" => lang("app.smsErr")));

						$smsRMdl->save(array("sms_record_id" => $sid, "receiver_id" => $student['student_id'], "phone" => $phone, "status" => 0));
						$sent++;
					} catch (\Exception $e) {

					}
				}
			}
			$param = base_url("background_process/2");
			$command = "curl $param > /dev/null &";
			exec($command);
			return $this->response->setJSON(array("success" => lang("app.beSent") . " $sent" . lang("app.over") . " $all"));
		} else {
			$data['content'] = view("pages/systemReports/feesReport", $data);
			return view('main', $data);
		}
	}


	public
	function exportFeesStatementInPdf($classe, $academic, $term, $filter)
	{
		$this->_preset(1, 3, 4, 5, 6);
		$data = $this->data;
		$classMdl = new ClassesModel();
		$school_id = $this->session->get("soma_school_id");
		$data['classe'] = $classMdl->select("classes.id,classes.title,d.title as department_name,d.code as dept_code,l.title as level_name
		,f.type,f.abbrev as faculty_code,concat(s.fname,' ',s.lname) as mentor_name,s.id as idstf")
				->join("departments d", "d.id=classes.department")
				->join("levels l", "l.id=classes.level")
				->join("faculty f", "f.id=d.faculty_id")
				->join("staffs s", "s.id=classes.mentor", "LEFT")
				->where("classes.school_id", $school_id)
				->where("classes.id", $classe)
				->get()->getRowArray();
		$studentMdl = new StudentModel();
		$students = $studentMdl->select("concat(students.fname,' ',students.lname) as student,
		students.studying_mode,
		students.regno,
		if(students.sex='F','Female','Male') as sex,
		cl.id,cl.title as class,
		d.title as department_name,
		d.code as dept_code,
		l.title as level_name,
		,f.type,f.abbrev as faculty_code,
		 (COALESCE(sum(sf.amount),0) + COALESCE(sum(ex.amount),0) + COALESCE(sum(student.amount),0) + coalesce(fd.amount,0)) as amount,
		COALESCE(sum(fr.amount),0) + COALESCE(sum(extraPaid.amount),0) as paid")
				->join("class_records cr", "cr.student=students.id")
				->join("classes cl", "cl.id=cr.class")
				->join("departments d", "d.id=cl.department")
				->join("levels l", "l.id=cl.level")
				->join("faculty f", "f.id=d.faculty_id")
				->join("school_fees sf", "sf.level=cl.level and sf.department=cl.department and sf.term=$term and sf.academic_year=$academic", "LEFT")
				->join("(select sum(amount) as amount,feesId,student from school_fees_discount group by student,feesId) fd", "fd.feesId=sf.id AND fd.student=students.id", "LEFT")
				->join("extra_fees ex", "ex.type_id=cl.id and ex.type=0 and ex.academic_year=$academic and ex.term=$term", "LEFT")
				->join("(select ex.id,ex.type_id,COALESCE(sum(ex.amount),0) as amount from extra_fees ex where ex.type=1 and ex.term=$term and ex.academic_year=$academic) student", "student.type_id=students.id", "LEFT")
				->join("fees_records fr", "fr.fees_id=sf.id and fr.fees_type=0 and fr.student_id=students.id and fr.status=1", "LEFT")
				->join("(select fr.student_id,fr.fees_id,fr.amount from fees_records fr where fr.fees_type=1 and fr.status=1) extraPaid", "extraPaid.student_id=students.id and (extraPaid.fees_id=ex.id || extraPaid.fees_id=student.id)", "LEFT")
				->groupBy("students.id");
		if ($filter == 0) {
			$students = $students->where("sf.school_id", $school_id)
					->where("cl.id", $classe)->get()->getResultArray();
			$data['title'] = "General fees payment report";
		} else if ($filter == 1) {
			$students = $students->having("paid", "amount", false)
					->where("sf.school_id", $school_id)
					->where("cl.id", $classe)->get()->getResultArray();
			$data['title'] = "Completed fees payment report";
		} else if ($filter == 2) {
			$students = $students->having("paid <", "amount", false)
					->having("paid >", 0, false)
					->where("sf.school_id", $school_id)
					->where("cl.id", $classe)->get()->getResultArray();
			$data['title'] = "Partial fees payment report";
		} else if ($filter == 3) {
			$students = $students->having("paid", 0, false)
					->where("sf.school_id", $school_id)
					->where("cl.id", $classe)->get()->getResultArray();
			$data['title'] = "None fees payment report";
		}
		$acMdl = new AcademicYearModel();
		$data['years'] = $acMdl->select('id,title')->where("id", $academic)->get()->getRowArray();
		$data['term'] = $term;
		$data['students'] = $students;
		return view("pages/systemReports/feesStatementInPdf", $data);
//		$html = view("pages/systemReports/feesStatementInPdf", $data);
//		try {
//			$mask = FCPATH . "assets/templates/*.html";
//			array_map('unlink', glob($mask));//clear previous cards
//			$wkhtmltopdf = new Wkhtmltopdf(array('path' => FCPATH . 'assets/templates/'));
//			$wkhtmltopdf->setTitle($data['title']);
//			$wkhtmltopdf->setHtml($html);
//			$wkhtmltopdf->setPageSize("A4");
//			$wkhtmltopdf->setOrientation("portrait");
////					$wkhtmltopdf->setOptions(array("page-width" => "278px", "page-height" => "430px"));
//			$wkhtmltopdf->setMargins(array("top" => 1, "left" => 0, "right" => 0, "bottom" => 1));
//			$wkhtmltopdf->output(Wkhtmltopdf::MODE_EMBEDDED, $data['title']."_". time() . ".pdf");
//		} catch (\Exception $e) {
//			echo $e->getMessage();
//		}

	}

	public
	function getSingleCourseAjax($course)
	{
		$courseModel = new CourseModel();
		$courses = $courseModel->select("courses.id,courses.title,courses.code,courses.marks,courses.credit,cs.id as category")
				->join("course_category cs", "cs.id=courses.category")
				->where("courses.id", $course)
				->get()->getRowArray();
		return $this->response->setJSON($courses);
	}

	public
	function deleteSchoolFee($id)
	{
		$schoolFeesMdl = new SchoolFeesModel();
		$feesRecordMdl = new FeesRecordModel();
		try {
			$verify = $feesRecordMdl->where("fees_type", 0)->where("fees_id", $id)->get(1)->getRow();
			if ($verify != null) {
				return $this->response->setStatusCode(400)->setJSON(["error" => "School fee records is in use"]);
			} else {
				$schoolFeesMdl->delete($id);
				return $this->response->setJSON(array("success" => "Record deleted successfully"));
			}
		} catch (\Exception $e) {
			return $this->response->setJSON(array("error" => "Error: " . $e->getMessage()));
		}
	}

	public
	function deleteExtraFee($id)
	{
		$extraFeesMdl = new ExtraFeesModel();
		$feesRecordMdl = new FeesRecordModel();
		try {
			$verify = $feesRecordMdl->where("fees_type", 1)->where("fees_id", $id)->get(1)->getRow();
			if ($verify != null) {
				return $this->response->setStatusCode(400)->setJSON(["error" => "Extra fee records is in use"]);
			} else {
				$extraFeesMdl->delete($id);
				return $this->response->setJSON(array("success" => "Record deleted successfully"));
			}
		} catch (\Exception $e) {
			return $this->response->setJSON(array("error" => "Error: " . $e->getMessage()));
		}
	}

	public
	function feesPaymentCorrection()
	{
		$feesRecordMdl = new FeesRecordModel();
		$extraFeesMdl = new ExtraFeesModel();
		$records = $feesRecordMdl->select("fees_records.id,fees_records.fees_id,ex.title,ex.type_id as feesClass,cr.class as studentClass")
				->join("extra_fees ex", "ex.id=fees_records.fees_id and ex.type=0")
				->join("class_records cr", "cr.student=fees_records.student_id")
				->orderBy("fees_records.fees_id")
				->get()->getResultArray();
		$table = "<table><tr><td>Fee-Id</td><td>Fee-title</td><td>Fee-class-id</td><td>student-class-id</td></tr>";
		$realFees = [];
		foreach ($records as $record):
			if ($record['feesClass'] != $record['studentClass']):
				$realFees = $extraFeesMdl->select("id,title")
						->where("type_id", $record['studentClass'])
						->where("type", 0)
						->where("title", $record['title'])
						->get()->getRow();
				if ($realFees != null):
					$feesRecordMdl->save(["id" => $record['id'], "fees_id" => $realFees->id]);
					$table .= "<tr><td>" . $record['fees_id'] . "</td><td>" . $record['title'] . "</td><td>" . $record['feesClass'] . "</td><td>" . $record['studentClass'] . "</td><td>" . $realFees->id . "-" . $realFees->title . "</td></tr>";
				else:
					$table .= "<tr><td>" . $record['fees_id'] . "</td><td>" . $record['title'] . "</td><td>" . $record['feesClass'] . "</td><td>" . $record['studentClass'] . "</td><td></td></tr>";
				endif;
			endif;
		endforeach;
		$table .= "</table>";
		echo $table;
	}

	/**
	 * @return String
	 */
	public
	function completedDisciplineMarks(): string
	{
		$this->_preset();
		$data = $this->data;
		$classMdl = new ClassesModel();
		$SchoolModel = new SchoolModel();
		$data['title'] = lang("app.disciplineRecordEntry");
		$data['classes'] = $classMdl->select("classes.id,classes.title,d.title as department_name,d.code,l.title as level_name
		,f.type,f.abbrev as faculty_code,concat(s.fname,' ',s.lname) as mentor_name,s.id as idstf")
				->join("departments d", "d.id=classes.department")
				->join("levels l", "l.id=classes.level")
				->join("faculty f", "f.id=d.faculty_id")
				->join("staffs s", "s.id=classes.mentor", "LEFT")
				->where("classes.school_id", $this->session->get("soma_school_id"))
				->get()->getResultArray();
		$data['activeTerm'] = $SchoolModel->select("at.term,at.id")
				->join("active_term at", "at.id=schools.active_term")
				->where("at.school_id", $this->session->get("soma_school_id"))
				->get()->getRowArray();
		$data['subtitle'] = lang("app.disciplineRecordEntry");
		$data['page'] = "Discipline Record Entry";
		$data['content'] = view("pages/completedDisciplineMarks", $data);
		return view('main', $data);
	}

	public
	function manipulateCompletedDisciplineEntry(): Response
	{
		$this->_preset();
		set_time_limit(0);
		ini_set("memory_limit", -1);
		ini_set("max_execution_time", -1);
		$DisciplineModel = new DisciplineModel();
		$schoolMdl = new SchoolModel();
		$school_id = $this->session->get("soma_school_id");
		$marks = $this->request->getPost("marks");
		$allowDelete = $this->request->getPost("allowDelete");
		$active = $this->request->getPost("active_term");
		$disciplineMax = $schoolMdl->select("discipline_max")->where("id", $school_id)->get()->getRow();
		$created_by = $this->session->get("soma_id");
		$formids = $this->request->getPost("discId");
		if (!is_array($formids)) {
			//no student selected
			return $this->response->setJSON(array("error" => lang("app.pleaseAddErr")));
		}
		$verifies = $DisciplineModel->select('id')
				->whereIn("student_id", $formids)
				->where("active_term", $active)->get()->getResultArray();
		if (count($verifies) > 0 && $allowDelete == 1) {
			foreach ($verifies as $key => $verify) {
				$verifyId = $verify['id'];
				$DisciplineModel->db->query("delete from disciplines where id=$verifyId and active_term=$active");
			}
			foreach ($formids as $key => $formid) {
				$data = array(
						"student_id" => $formid,
						"school_id" => $school_id,
						"type" => 1,
						"marks" => $disciplineMax->discipline_max - $marks[$key],
						"active_term" => $active,
						"created_by" => $created_by);
				$DisciplineModel->save($data);
			}
			return $this->response->setJSON(array("success" => lang("app.disciplineSuccessfully")));
		} else if (count($verifies) == 0) {
			foreach ($formids as $key => $formid) {
				$data = array(
						"student_id" => $formid,
						"school_id" => $school_id,
						"type" => 1,
						"marks" => $disciplineMax->discipline_max - $marks[$key],
						"active_term" => $active,
						"created_by" => $created_by);
				$DisciplineModel->save($data);
			}
			return $this->response->setJSON(array("success" => lang("app.disciplineSuccessfully")));
		} else {
			return $this->response->setJSON(["error" => "Sorry there are existing records"]);
		}
	}

	public
	function deliberation_settings()
	{
		$this->_preset();
		$data = $this->data;
		$data['title'] = lang("app.deliberationSettings");
		$data['subtitle'] = lang("app.deliberationSettings");
		$data['page'] = "Deliberation settings";
		$courseCatMdl = new CourseCategoryModel();
		$acMdl = new AcademicYearModel();
		$data['categories'] = $courseCatMdl->select("id,title")->where("school_id", $this->session->get("soma_school_id"))->get()->getResultArray();
		$data['years'] = $acMdl->select('id,title')->where("school_id", $this->session->get("soma_school_id"))
				->orderBy("id", 'DESC')->get()->getResultArray();
		$data['content'] = view("pages/marks/deliberation_settings", $data);
		return view('main', $data);
	}

	public
	function manipulateDeliberationSettings(): Response
	{
		$this->_preset();
		$academicYear = $this->request->getPost("academicYear");
		$educationType = $this->request->getPost("educationType");
		$levels = $this->request->getPost("levels") ? $this->request->getPost("levels") : 0;
		$conditionType = $this->request->getPost("conditionType");
		$conditions = $this->request->getPost("conditions");
		$marks = $this->request->getPost("marks");
		$cTypes = $this->request->getPost("cTypes");
		$categories = $this->request->getPost("categories");
		$coursesNums = $this->request->getPost("coursesNums");
		$deliberationMdl = new DeliberationCriteriaModel();
		$deliberationConditionMdl = new DeliberationConditionsModel();
		$deliberationFailedMdl = new DeliberationFailedCoursesModel();
		$data = ["type" => $educationType,
				"faculty_id" => $levels,
				"verdict" => $conditionType,
				"academic_year" => $academicYear,
				"created_by" => $this->session->get("soma_id")];
		try {
			$deliberationId = $deliberationMdl->insert($data);
			foreach ($conditions as $key => $condition) {
				$conditionData = ["conditions" => $condition, "value" => $marks[$key], "type" => $cTypes[$key], "deliberation_id" => $deliberationId];
				$deliberationConditionMdl->save($conditionData);
			}
			if ($categories != null) {
				foreach ($categories as $key => $category) {
					$failedData = ["categoryId" => $category, "course_count" => $coursesNums[$key], "deliberationId" => $deliberationId];
					$deliberationFailedMdl->save($failedData);
				}
			}
			return $this->response->setJSON(array("success" => "Conditions created"));
		} catch (\Exception $e) {
			return $this->response->setJSON(array("error" => lang("app.OopsAction") . $e->getMessage()));
		}
	}

	public
	function getDeliberationSettings($academicYear, $type, $level)
	{
		$deliberationMdl = new DeliberationCriteriaModel();
		$conditionsMdl = $deliberationConditionMdl = new DeliberationConditionsModel();
		$failedCoursesMdl = new DeliberationFailedCoursesModel();
		$faculties = $deliberationMdl->select("deliberation_criteria.id,deliberation_criteria.verdict")
				->where("deliberation_criteria.type", $type)
				->where("deliberation_criteria.faculty_id", $level)
				->where("deliberation_criteria.academic_year", $academicYear)
				->get()->getResultArray();
		$html = '';
		foreach ($faculties as $key => $faculty) {
			if ($key == 0) {
				$show = "show";
			} else {
				$show = "";
			}
			$conditions = $conditionsMdl->select("id,conditions,value,if(type=0,'Overall percentage','discipline')as type")
					->where("deliberation_id", $faculty['id'])
					->get()->getResultArray();
			$faileds = $failedCoursesMdl->select("deliberation_failed_courses.id,deliberation_failed_courses.course_count,ct.title as category")
					->join("course_category ct", "ct.id=deliberation_failed_courses.categoryId")
					->where("deliberation_failed_courses.deliberationId", $faculty['id'])->get()->getResultArray();
			$table = "<table class='table'><tr><th>#</th><th>Type</th><th>Condition</th><th>Marks</th></tr>";
			$failDiv = '<div style="text-align: center"><strong>Failed courses records</strong></div><br><table class="table"><tr><th>#</th><th>Category</th><th>Number of course</th></tr>';
			foreach ($conditions as $ke => $condition) {
				$table .= "<tr><td>" . ($ke + 1) . "</td><td>" . $condition['type'] . "</td><td>" . symbolsText($condition['conditions']) . "</td><td>" . $condition['value'] . "</td></tr>";
			}
			foreach ($faileds as $k => $failed) {
				$failDiv .= "<tr><td>" . ($k + 1) . "</td><td>" . $failed['category'] . "</td><td>" . $failed['course_count'] . "</td></tr>";
			}
			$failDiv .= '</table>';
			$table .= "</table>";
			$html .= '<div class="card">
					<div class="card-header" id="headingOne">
							<button class="btn btn-link btn-block text-left" type="button" data-toggle="collapse"
									data-target="#collapse' . $key . '" aria-expanded="true" aria-controls="collapse' . $key . '">
								<b>' . ($key + 1) . '.' . verdictText($faculty["verdict"]) . '</b>
							</button>
						<button style="float: right" class="deleteConditionBtn btn btn-danger btn-sm" data-id="' . $faculty['id'] . '">Delete</button>
					</div>
					<div id="collapse' . $key . '" class="collapse ' . $show . '" aria-labelledby="headingOne"
						 data-parent="#accordionExample">
						<div class="card-body">' . $table . '' . $failDiv . '</div></div></div>';
		}
		echo $html;
	}

	public
	function deleteDeliberation($deliberation): Response
	{
		$this->_preset();
		$deliberationMdl = new DeliberationCriteriaModel();
		$deliberationFailed = new DeliberationFailedCoursesModel();
		$deliberationConditions = new DeliberationConditionsModel();
		$drMdl = new DeliberationRecords();
		try {
			$data = $drMdl->select('id')->where('deliberationId', $deliberation)->get(1)->getRow();
			if ($data != null) {
				return $this->response->setJSON(["error" => "Error: Deliberation can not be deleted because it is used"]);
			}
			$deliberationMdl->delete($deliberation);
			$deliberationFailed->delete(["deliberationId" => $deliberation]);
			$deliberationConditions->delete(["deliberationId" => $deliberation]);
			return $this->response->setJSON(["success" => "Deliberation deleted"]);
		} catch (\Exception $e) {
			return $this->response->setJSON(["error" => "Error: " . $e->getMessage()]);
		}
	}

	public
	function pocket_money()
	{
		$this->_preset(1, 3, 14);
		$data = $this->data;
		$data['title'] = lang("app.PocketMoney");
		$data['subtitle'] = lang("app.PocketMoney");
		$data['page'] = "PocketMoney";
		$school_id = $this->session->get("soma_school_id");
		$Mdl = new PaymentModel();
		$data['money'] = $Mdl->db->query("select (SELECT SUM(amount) from payment_transactions p1 inner join students st1 on st1.id = p1.student_id where st1.school_id={$school_id} and p1.type=0 and p1.status=1) as transfer,
												 (SELECT COALESCE (COUNT(amount),0) from payment_transactions p2 inner join students st2 on st2.id = p2.student_id where st2.school_id={$school_id} and p2.type=0 and p2.status=1) as transferNum,
												 (SELECT SUM(amount) from payment_transactions p3 inner join students st3 on st3.id = p3.student_id where st3.school_id={$school_id} and p3.type=1 and p3.status=1) as payment,
												 (SELECT COUNT(amount) from payment_transactions p4 inner join students st4 on st4.id = p4.student_id where st4.school_id={$school_id} and p4.type=1 and p4.status=1) as paymentNum,
												 (SELECT SUM(amount) from payment_transactions p5 inner join students st5 on st5.id = p5.student_id where st5.school_id={$school_id} and p5.type=2 and p5.status=1) as withdraw,
												 (SELECT COUNT(amount) from payment_transactions p6 inner join students st6 on st6.id = p6.student_id where st6.school_id={$school_id} and p6.type=2 and p6.status=1) as withdrawNum")->getRowArray();
		$data['activeStudent'] = count($Mdl->select("payment_transactions.id")
				->join('students s', 'payment_transactions.student_id = s.id')
				->where("payment_transactions.status", 1)
				->where('s.school_id', $school_id)
				->groupBy("student_id")->get()->getResultArray());
		$data['transactions'] = $Mdl->select("payment_transactions.*")
				->join('students s', 'payment_transactions.student_id = s.id')
				->where('s.school_id', $school_id)
				->where("payment_transactions.status", 1)
				->orderBy("payment_transactions.id", "DESC")
				->limit(10)
				->get()->getResultArray();
		$data['content'] = view("pages/pocketMoney", $data);
		return view('main', $data);
	}

	public
	function finance_records(): string
	{
		$this->_preset(1, 3, 14);
		$data = $this->data;
		$data['title'] = lang("app.financeDashboard");
		$data['subtitle'] = lang("app.financeData");
		$data['page'] = "finance";
		$Mdl = new PaymentModel();
		$school_id = $this->session->get("soma_school_id");
		$data['money'] = $Mdl->db->query("select coalesce((SELECT concat(SUM(p1.amount),':',COUNT(p1.amount)) from payment_transactions p1 inner join bank_credit_transactions b1
    ON b1.wallet_id=p1.id inner join students st1 on st1.id = p1.student_id where st1.school_id={$school_id} and p1.type=4 and p1.status=1 and b1.status=1),'0:0') as completed,coalesce((SELECT concat(SUM(p2.amount),':',COUNT(p2.amount)) from
    payment_transactions p2 inner join bank_credit_transactions b2
    ON b2.wallet_id=p2.id inner join students st2 on st2.id = p2.student_id where st2.school_id={$school_id} and p2.type=4 and p2.status=1 and b2.status=0),'0:0') as bank_pending,
	coalesce((SELECT concat(SUM(amount),':',COUNT(amount)) from payment_transactions p3 inner join students st3 on st3.id = p3.student_id where st3.school_id={$school_id} and p3.type=4 and p3.status=2),'0:0') as failed,
	coalesce((SELECT concat(SUM(amount),':',COUNT(amount)) from payment_transactions p4 inner join students st4 on st4.id = p4.student_id where st4.school_id={$school_id} and p4.type=4 and p4.status=0),'0:0') as pending")->getRowArray();
//		$data['activeStudent'] = count($Mdl->select("id")->groupBy("student_id")->where("status",1)->get()->getResultArray());

		$data['content'] = view("pages/finance_dashboard", $data);
		return view('main', $data);
	}

	public
	function getPaymentTransactions($filter = 0, $search = null)
	{
		$filterQuery = "1=1";//all
		if ($filter == 1) {
			//failed
			$filterQuery = "payment_transactions.status=2";
		} else if ($filter == 2) {
			//pending bank transfer
			$filterQuery = "b.status=0";
		} else if ($filter == 3) {
			//success
			$filterQuery = "b.status=1 and payment_transactions.status=1";
		}
		$search = urldecode($search);
		if (!empty($search)) {
			$filterQuery .= " AND (payment_transactions.source like '%$search%' or s.regno = '%$search%' or payment_transactions.reference_id = '%$search%')";
		}
		$school_id = $this->session->get("soma_school_id");
		$Mdl = new PaymentModel();
		$transactions = $Mdl->select("payment_transactions.amount,payment_transactions.source,payment_transactions.reference_id
		,b.refNo,payment_transactions.status,b.status as bankStatus,s.fname,s.lname,s.regno,payment_transactions.created_at")
				->join('bank_credit_transactions b', 'payment_transactions.id = b.wallet_id', 'left')
				->join('students s', 'payment_transactions.student_id = s.id')
				->limit(10)
				->where("payment_transactions.type", 4)
				->where($filterQuery)
				->where('s.school_id', $school_id)
				->orderBy("payment_transactions.id", "DESC")->get()->getResultArray();
		foreach ($transactions as $transaction):
			?>
			<div class="card" style="padding: 10px">
				<div class="row">
					<div class="col-sm-12 col-md-9 pad">
						<strong
								style="text-transform:;"><?= $transaction['fname'] . ' ' . $transaction['lname']; ?>
							<i class="fa fa-clock"
							   title="<?= date("d M Y | h:s", strtotime($transaction['created_at'])); ?>"
							   data-toggle="tooltip"></i> </strong>
					</div>
					<div class="col-sm-12 col-md-3 pad text-right">
						<strong class="text-success"><?= number_format($transaction['amount']); ?> RWF</strong>
					</div>
				</div>
				<div class="row" style="display: block">
					<b class="text-muted"><small>MTN MOMO <?= $transaction['source']; ?></small></b>
					<b class="text-muted pull-right">
						<?php
						if ($transaction['status'] == 2) {
							echo '<i class="fa fa-ban text-danger" data-toggle="tooltip" title="Payment failed"></i><small> FAILED</small>';
						} else if ($transaction['status'] == 0) {
							echo '<i class="fa fa-hourglass-half" data-toggle="tooltip" title="Pending payment"></i><small> PENDING</small>';
						} else {
							echo '<i class="fa fa-check text-success" data-toggle="tooltip" title="Transaction completed"></i><small> COMPLETED</small>';
						}
						?>
					</b>
				</div>
			</div>
			<div style="height: 8px"></div>
		<?php
		endforeach;
	}

	public
	function getMostActiveStudents()
	{
		$Mdl = new PaymentModel();
		$school_id = $this->session->get("soma_school_id");
		$students = $Mdl->select("count(payment_transactions.student_id) as times,concat(s.fname,' ',s.lname) as student")
				->join("students s", "s.id=payment_transactions.student_id")
				->groupBy("s.id")->orderBy("count(payment_transactions.student_id)", "DESC")
				->where("payment_transactions.status", 1)
				->where("s.school_id", $school_id)
				->limit(10)
				->get()->getResultArray();
		return $this->response->setJSON($students);
	}

	public
	function transactions()
	{
		$this->_preset(1, 3, 14);
		$data = $this->data;
		$data['title'] = "Transactions";
		$data['subtitle'] = "Transactions";
		$data['page'] = "Transactions";
		$Mdl = new PaymentModel();
		$data['transactions'] = $Mdl->select("*")->where("status", 1)->get()->getResultArray();
		$data['content'] = view("pages/transactions", $data);
		return view('main', $data);
	}

	public
	function getPockMoneyHistory($student)
	{
		$Mdl = new PaymentModel();
		$students = $Mdl->select("payment_transactions.*,coalesce(concat(s.fname,' ',s.lname),'EDUPILLAR APP') as operator")
				->join("staffs s", "s.id=payment_transactions.created_by", "left")
				->where("payment_transactions.student_id", $student)
				->where("payment_transactions.status", 1)
				->get()->getResultArray();
		return $this->response->setJSON($students);
	}

	/**
	 * @return Response
	 */
	public
	function manipulateClassChanges(): Response
	{
		$this->_preset();
		$Mdl = new ClassesModel();
		$class = $this->request->getPost("key");
		$value = $this->request->getPost("value");
		$data = array(
				"id" => $class,
				"title" => $value);
		try {
			$Mdl->save($data);
			return $this->response->setJSON(["success" => "Title changed successfully"]);
		} catch (\Exception $e) {
			return $this->response->setStatusCode(400)->setJSON(["error" => "Error: " . $e->getMessage()]);
		}
	}

	/**
	 * Build required upload slots from faculty + level (REB vs TVET).
	 * Maps to report1 / report2 columns. Babyeyi is school-settings only (not collected here).
	 *
	 * @return array{hint:string,docs:array<int,array{field:string,label:string,required:bool,accept:string,hint:string}>}
	 */
	private function resolveApplicationDocRequirements(int $facultyType, string $facultyTitle, string $levelTitle): array
	{
		$levelKey = strtolower(trim($levelTitle));
		$facKey = strtolower(trim($facultyTitle));
		$docs = [];
		$hint = 'Upload clear PDF or image scans. Max 5 MB per file.';

		$isNursery = (bool) preg_match('/\b(nursery|baby class|middle class|top class|n1|n2|n3)\b/', $levelKey . ' ' . $facKey);
		$isPrimary = (bool) preg_match('/\b(primary|p1|p2|p3|p4|p5|p6)\b/', $levelKey . ' ' . $facKey);
		$isOLevel = (bool) preg_match('/\b(ordinary|o[\'’]? ?level|s1|s2|s3)\b/', $levelKey . ' ' . $facKey);
		$isALevel = (bool) preg_match('/\b(a[\'’]? ?level|s4|s5|s6|science|humanities|languages)\b/', $levelKey . ' ' . $facKey);
		$isL3 = (bool) preg_match('/\b(level\s*3|l3)\b/', $levelKey);
		$isL4 = (bool) preg_match('/\b(level\s*4|l4)\b/', $levelKey);
		$isL5 = (bool) preg_match('/\b(level\s*5|l5)\b/', $levelKey);

		if ((int) $facultyType === 2) {
			// REB — general education
			if ($isNursery) {
				$hint = 'Nursery applicants: upload the latest school/nursery report. Birth certificate helps verification.';
				$docs[] = ['field' => 'report1', 'label' => 'Previous nursery / school report', 'required' => true, 'accept' => '.pdf,.jpg,.jpeg,.png', 'hint' => 'Latest report from previous class'];
				$docs[] = ['field' => 'report2', 'label' => 'Birth certificate', 'required' => false, 'accept' => '.pdf,.jpg,.jpeg,.png', 'hint' => 'Optional but recommended'];
			} elseif ($isPrimary) {
				$hint = 'Primary applicants: upload the previous academic year school report.';
				$docs[] = ['field' => 'report1', 'label' => 'Previous year school report', 'required' => true, 'accept' => '.pdf,.jpg,.jpeg,.png', 'hint' => 'Report that led to the class you are applying for'];
				$docs[] = ['field' => 'report2', 'label' => 'Birth certificate', 'required' => false, 'accept' => '.pdf,.jpg,.jpeg,.png', 'hint' => 'Optional'];
			} elseif ($isOLevel) {
				$hint = 'O\'Level (S1–S3): upload previous academic year reports that led to completion of the prior class.';
				$docs[] = ['field' => 'report1', 'label' => 'Previous academic year reports', 'required' => true, 'accept' => '.pdf,.jpg,.jpeg,.png', 'hint' => 'Required'];
				$docs[] = ['field' => 'report2', 'label' => 'Primary leaving / prior certificate', 'required' => false, 'accept' => '.pdf,.jpg,.jpeg,.png', 'hint' => 'If available'];
			} elseif ($isALevel) {
				$hint = 'A\'Level (S4–S6): upload previous reports and the O\'Level national exam result slip.';
				$docs[] = ['field' => 'report1', 'label' => 'Previous academic year reports', 'required' => true, 'accept' => '.pdf,.jpg,.jpeg,.png', 'hint' => 'Required'];
				$docs[] = ['field' => 'report2', 'label' => 'O\'Level national exam result slip (N.E)', 'required' => true, 'accept' => '.pdf,.jpg,.jpeg,.png', 'hint' => 'Required for A\'Level entry'];
			} else {
				$hint = 'Upload previous academic reports for the level you are applying to.';
				$docs[] = ['field' => 'report1', 'label' => 'Previous academic reports', 'required' => true, 'accept' => '.pdf,.jpg,.jpeg,.png', 'hint' => 'Required'];
				$docs[] = ['field' => 'report2', 'label' => 'Supporting certificate / exam slip', 'required' => false, 'accept' => '.pdf,.jpg,.jpeg,.png', 'hint' => 'If available'];
			}
		} else {
			// RTB / TVET
			if ($isL3 || preg_match('/\b(level\s*1|level\s*2|senior\s*4)\b/', $levelKey)) {
				$hint = 'TVET entry (L3): upload prior academic reports and O\'Level national exam slip.';
				$docs[] = ['field' => 'report1', 'label' => 'Previous academic / O\'Level reports', 'required' => true, 'accept' => '.pdf,.jpg,.jpeg,.png', 'hint' => 'Required'];
				$docs[] = ['field' => 'report2', 'label' => 'O\'Level national exam result slip (N.E)', 'required' => true, 'accept' => '.pdf,.jpg,.jpeg,.png', 'hint' => 'Required for L3 entry'];
			} elseif ($isL4) {
				$hint = 'TVET Level 4: upload Level 3 academic reports.';
				$docs[] = ['field' => 'report1', 'label' => 'Level 3 academic reports', 'required' => true, 'accept' => '.pdf,.jpg,.jpeg,.png', 'hint' => 'Required'];
				$docs[] = ['field' => 'report2', 'label' => 'Level 3 assessment / trade test', 'required' => false, 'accept' => '.pdf,.jpg,.jpeg,.png', 'hint' => 'If available'];
			} elseif ($isL5) {
				$hint = 'TVET Level 5: upload Level 4 academic reports.';
				$docs[] = ['field' => 'report1', 'label' => 'Level 4 academic reports', 'required' => true, 'accept' => '.pdf,.jpg,.jpeg,.png', 'hint' => 'Required'];
				$docs[] = ['field' => 'report2', 'label' => 'Level 4 assessment / trade test', 'required' => false, 'accept' => '.pdf,.jpg,.jpeg,.png', 'hint' => 'If available'];
			} else {
				$hint = 'TVET applicants: upload previous level reports and any national exam slip you have.';
				$docs[] = ['field' => 'report1', 'label' => 'Previous TVET / academic reports', 'required' => true, 'accept' => '.pdf,.jpg,.jpeg,.png', 'hint' => 'Required'];
				$docs[] = ['field' => 'report2', 'label' => 'National exam / prior level slip', 'required' => false, 'accept' => '.pdf,.jpg,.jpeg,.png', 'hint' => 'If available'];
			}
		}

		return ['hint' => $hint, 'docs' => $docs];
	}

	/**
	 * AJAX: required documents for selected faculty + level.
	 */
	public function getApplicationRequiredDocs(int $facultyId, int $levelId, int $schoolId = 0): Response
	{
		$fMdl = new FacultyModel();
		$lMdl = new LevelsModel();
		$fac = $fMdl->select('id,title,type')->where('id', $facultyId)->get(1)->getRow();
		$level = $lMdl->select('id,title,type,faculty_id')->where('id', $levelId)->get(1)->getRow();
		if (!$fac || !$level) {
			return $this->response->setJSON(['error' => 'Invalid faculty or level']);
		}
		$pack = $this->resolveApplicationDocRequirements(
			(int) $fac->type,
			(string) $fac->title,
			(string) $level->title
		);
		return $this->response->setJSON([
			'success' => 1,
			'faculty' => $fac->title,
			'level' => $level->title,
			'program_type' => (int) $fac->type,
			'hint' => $pack['hint'],
			'docs' => $pack['docs'],
		]);
	}

	/**
	 * Notify parent by SMS + email after successful application.
	 */
	private function notifyParentOfApplication(
		string $parentPhone,
		string $parentEmail,
		string $parentNames,
		string $studentNames,
		string $schoolName,
		string $levelName,
		string $code
	): void {
		$sms = "Dear {$parentNames}, application for {$studentNames} at {$schoolName} ({$levelName}) was received. Registration code: {$code}. Keep this code. - XanderTech SmartSMS";
		$smsResult = '';
		if (strlen(preg_replace('/\D/', '', $parentPhone)) >= 9) {
			try {
				$this->sendSMS($parentPhone, $sms, $smsResult);
			} catch (\Throwable $e) {
				log_message('error', 'Application SMS failed: ' . $e->getMessage());
			}
		}
		if (filter_var($parentEmail, FILTER_VALIDATE_EMAIL)) {
			$e = function ($v) {
				return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
			};
			$html = '<p>Dear ' . $e($parentNames) . ',</p>'
				. '<p>We received the online application for <strong>' . $e($studentNames) . '</strong> '
				. 'at <strong>' . $e($schoolName) . '</strong> (Level: ' . $e($levelName) . ').</p>'
				. '<p>Your registration code is: <strong style="font-size:18px;">' . $e($code) . '</strong></p>'
				. '<p>Please keep this code. You may need it to complete remaining steps.</p>'
				. '<p>Thank you,<br>XanderTech SmartSMS<br>' . $e($schoolName) . '</p>';
			try {
				$this->_send_email($parentEmail, 'Application received — ' . $schoolName, $html);
			} catch (\Throwable $ex) {
				log_message('error', 'Application email failed: ' . $ex->getMessage());
			}
		}
	}

	/** This function helps to retrieve schools which have selected
	 * program from the user form
	 * @param int $program
	 * @return Response
	 */
	public
	function getSchoolsHavingSelectedProgram(int $program): Response
	{
		$classesMdl = new ClassesModel();
		$schools = $classesMdl->select("s.id,s.name")
				->join("departments d", "d.id=classes.department")
				->join("faculty f", "f.id=d.faculty_id")
				->join("schools s", "s.id=classes.school_id")
				->where("f.type", $program)
				->groupBy("s.id")
				->orderBy("s.name", "ASC")
				->get()->getResultArray();
		if ($schools == null) {
			return $this->response->setStatusCode("400")->setJSON(["error" => "No data found"]);
		} else {
			return $this->response->setJSON($schools);
		}
	}

	/**
	 * Faculties for a school, filtered by program (1=RTB/TVET, 2=REB).
	 */
	public
	function getFacultyBySchool(int $school, int $program = 0): Response
	{
		$mdl = new FacultyModel();
		$appMdl = new ApplicationSettingsModel();
		$settings = $appMdl->select('id,start_date,end_date,requirement_document,registration_fees,babyeyi_required')
				->where('school_id', $school)
				->orderBy('id', 'desc')
				->get(1)->getRow();
		if ($settings == null) {
			return $this->response->setJSON(["error" => "Online application not available for this school. Ask the school to configure registration fees in Settings."]);
		}
		$builder = $mdl->select("faculty.id,faculty.title as name")
				->join("departments d", "d.faculty_id=faculty.id")
				->join("classes c", "c.department=d.id")
				->where("c.school_id", $school);
		// REB=2 (general), RTB/WDA=1 (TVET) — never mix programs
		if ($program === 1 || $program === 2) {
			$builder->where("faculty.type", $program);
		}
		$faculty = $builder
				->groupBy("faculty.id")
				->orderBy("faculty.title", "ASC")
				->get()->getResultArray();
		if ($faculty == null) {
			return $this->response->setJSON(["error" => "No Faculty found for the selected school program"]);
		} else {
			$fee = (int) $settings->registration_fees;
			$charges = 600;
			$platform = 100;
			$total = $fee + $charges + $platform;
			$data['success'] = 1;
			$data['requirement_document'] = $settings->requirement_document;
			$data['has_requirement_document'] = strlen(trim((string) $settings->requirement_document)) > 3;
			$data['settings_fees'] = number_format($fee) . ' Rwf';
			$data['settings_fees_raw'] = $fee;
			$data['settings_charges'] = number_format($charges) . ' Rwf';
			$data['settings_charges_raw'] = $charges;
			$data['settings_platform'] = number_format($platform) . ' Rwf';
			$data['settings_platform_raw'] = $platform;
			$data['settings_total'] = number_format($total) . ' Rwf';
			$data['settings_total_raw'] = $total;
			$data['babyeyi_required'] = (int) ($settings->babyeyi_required ?? 1);
			$data['settings_id'] = $settings->id;
			$data['payment_bypass'] = (string) env('REGISTRATION_PAYMENT_BYPASS', '1') === '1' ? 1 : 0;
			$data['program'] = $program;
			$data['faculties'] = $faculty;
			return $this->response->setJSON($data);
		}
	}

	public
	function getDepartmentBySchool(int $faculty, int $school): Response
	{
		$mdl = new DeptModel();
		$data = $mdl->select("departments.id,departments.title as name")
				->join("classes c", "c.department=departments.id")
				->where("c.school_id", $school)
				->where("departments.faculty_id", $faculty)
				->groupBy("departments.id")
				->orderBy("departments.title", "ASC")
				->get()->getResultArray();
		if ($data == null) {
			return $this->response->setStatusCode("400")->setJSON(["error" => "No data found"]);
		} else {
			return $this->response->setJSON($data);
		}
	}

	public
	function getLevelByFaculty(int $fac, int $type): Response
	{
		$mdl = new LevelsModel();
		$fMdl = new FacultyModel();

		$facData = $fMdl->select('type')->where('id', $fac)->get()->getRow();
		$isTvet = $facData && (int) $facData->type === 1;

		if ($isTvet) {
			// TVET/RTB: only Level 1–5 (never Senior / Year / REB class names)
			$rows = $mdl->select("levels.id,levels.title as name")
					->where("type", 1)
					->orderBy("levels.title", "ASC")
					->get()->getResultArray();
			$data = [];
			foreach ($rows as $row) {
				$title = strtolower(trim((string) ($row['name'] ?? '')));
				if (preg_match('/\b(senior|ordinary|primary|nursery|year|s1|s2|s3|s4|s5|s6)\b/', $title)) {
					continue;
				}
				if (preg_match('/\blevel\s*[1-5]\b/', $title) || preg_match('/^l\s*[1-5]$/', $title)) {
					$data[] = $row;
				}
			}
			// Stable order: Level 1 → 5
			usort($data, static function ($a, $b) {
				preg_match('/([1-5])/', (string) $a['name'], $ma);
				preg_match('/([1-5])/', (string) $b['name'], $mb);
				return ((int) ($ma[1] ?? 9)) <=> ((int) ($mb[1] ?? 9));
			});
		} else {
			$data = $mdl->select("levels.id,levels.title as name")
					->where("faculty_id", $fac)
					->orderBy("levels.id", "ASC")
					->get()->getResultArray();
		}

		if ($data == null || count($data) === 0) {
			return $this->response->setStatusCode("400")->setJSON(["error" => "No data found"]);
		}
		return $this->response->setJSON($data);
	}

	/** This function helps to created new student who made his/her
	 * self registration
	 * @return Response
	 */
	public function manipulateStudentSelfRegistration(): Response
{
    $studentAppModel = new StudentApplicationModel();
    $transMdl        = new ApplicationTransactionModel();
    $school          = $this->request->getPost("school");
    $schoolMdl       = new SchoolModel();
    $settingsMdl     = new ApplicationSettingsModel();

    $schoolData = $schoolMdl->select('mtn_momo_phone,name')
        ->where('id', $school)
        ->get(1)->getRow();

    if ($schoolData == null) {
        return $this->response->setJSON(["error" => "Error: School not available"]);
    }

    // Payment API not ready yet — bypass MOMO but keep Payment step in UI.
    $paymentBypass = (string) env('REGISTRATION_PAYMENT_BYPASS', '1') === '1';
    $paymentMethod = strtolower(trim((string) $this->request->getPost('paymentMethod')));
    if ($paymentMethod !== 'proof') {
        $paymentMethod = 'momo';
    }

    if ($paymentMethod === 'momo' && !$paymentBypass && strlen($schoolData->mtn_momo_phone) < 5) {
        return $this->response->setJSON(["error" => "Error: School not available, doesn't allow online payment"]);
    }

    $applicationSettings = $this->request->getPost("applicationSettings");
    if (strlen($applicationSettings) == 0) {
        return $this->response->setJSON(["error" => "Invalid data, please try again or reload the page"]);
    }

    $settingsData = $settingsMdl->select('registration_fees,school_id,babyeyi_required')
        ->where('id', $applicationSettings)
        ->get(1)->getRow();

    if ($settingsData == null) {
        return $this->response->setJSON(["error" => "Invalid application, please try again or reload the page"]);
    }

    // ---- Student information
    $applicationId = $this->request->getPost("applicationId");
    $firstName     = $this->request->getPost("firstName");
    $lastName      = $this->request->getPost("lastName");
    $gender        = $this->request->getPost("gender");
    $level         = $this->request->getPost("level");
    $dept          = $this->request->getPost("department");
    $fac           = $this->request->getPost("faculty");
    $studentPhone  = $this->request->getPost("phoneNumber");
    $studyingMode  = $this->request->getPost("studingMode");

    // ---- Parent information
    $relationship  = $this->request->getPost("relationship");
    $parentNames   = $this->request->getPost("parentNames");
    $parentPhone   = $this->request->getPost("parentPhone");
    $parentEmail   = $this->request->getPost("email");

    // ---- Payment phone normalization
    $momoPhone = $this->request->getPost("momoPhoneNumber");
    if ($paymentMethod === 'proof') {
        $proofPhone = $this->request->getPost('proofPhoneNumber');
        if (strlen(trim((string) $proofPhone)) >= 9) {
            $momoPhone = $proofPhone;
        } elseif (strlen(trim((string) $studentPhone)) >= 9) {
            $momoPhone = $studentPhone;
        }
    }
    $momoPhone = str_replace("+", "", (string) $momoPhone);
    if (strlen($momoPhone) >= 9 && substr($momoPhone, 0, 3) !== "250") {
        $momoPhone = "25" . $momoPhone;
    }

    $code = uniqid();

    $studentData = [
        "id"                 => $applicationId,
        "schoolId"           => $school,
        "settingsId"         => $applicationSettings,
        "fname"              => $firstName,
        "lname"              => $lastName,
        "phoneNumber"        => $studentPhone,
        "level"              => $level,
        "code"               => $code,
        "department_id"      => $dept,
        "faculty_id"         => $fac,
        "gender"             => $gender,
        "studyingMode"       => $studyingMode,
        "parentNames"        => $parentNames,
        "parentType"         => $relationship,
        "status"             => 0,
        "parentPhoneNumber"  => $parentPhone,
        "email"              => $parentEmail
        // NOTE: 'documents' left untouched to preserve existing logic
    ];

    try {
        // Create or update the application (without attachments first)
        if (!empty($applicationId)) {
            $studentAppModel->save($studentData);
        } else {
            $applicationId = $studentAppModel->insert($studentData);
        }

        // ===== NEW: handle attachments (report1, report2, report3) publicly =====
        // Folder: public/uploads/applications/{applicationId}/
        $publicRoot   = FCPATH . 'uploads' . DIRECTORY_SEPARATOR . 'applications' . DIRECTORY_SEPARATOR . $applicationId . DIRECTORY_SEPARATOR;
        $publicRel    = 'uploads/applications/' . $applicationId . '/'; // path saved to DB
        if (!is_dir($publicRoot)) {
            @mkdir($publicRoot, 0755, true);
        }

        // Small helper to process each upload
        $savePath = function (string $field, string $suffix) use ($publicRoot, $publicRel, $applicationId) {
            $file = $this->request->getFile($field);
            if (!$file || !$file->isValid() || $file->hasMoved()) {
                return null; // nothing to save for this field
            }

            // Validate type & size (~5MB)
            $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png'];
            $mime = $file->getClientMimeType();
            if (!in_array($mime, $allowedMimes, true)) {
                // If you want hard-fail, throw:
                // throw new \RuntimeException("Invalid file type for $field. Allowed: PDF/JPG/PNG.");
                return null;
            }
            if ($file->getSize() > (10 * 1024 * 1024)) {
                // throw new \RuntimeException("$field is larger than 10MB.");
                return null;
            }

            $ext      = $file->getClientExtension();
            $basename = $applicationId . '_' . $suffix . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
            $file->move($publicRoot, $basename, true); // overwrite = true (safe due to random part)

            return $publicRel . $basename; // relative public path for DB
        };

        // Resolve required docs from faculty + level (REB vs TVET) — no Babyeyi upload on form
        $facRow = (new FacultyModel())->select('id,title,type')->where('id', $fac)->get(1)->getRow();
        $levelRow = (new LevelsModel())->select('id,title')->where('id', $level)->get(1)->getRow();
        $docPack = $this->resolveApplicationDocRequirements(
            $facRow ? (int) $facRow->type : 1,
            $facRow ? (string) $facRow->title : '',
            $levelRow ? (string) $levelRow->title : ''
        );
        $savedByField = [
            'report1' => $savePath('report1', 'report1'),
            'report2' => $savePath('report2', 'report2'),
            'report3' => $savePath('report3', 'report3'),
        ];
        foreach ($docPack['docs'] as $docReq) {
            if (!empty($docReq['required']) && empty($savedByField[$docReq['field']])) {
                return $this->response->setJSON([
                    'error' => $docReq['label'] . ' is required',
                ]);
            }
        }

        $paymentProofPath = null;
        if ($paymentMethod === 'proof') {
            $paymentProofPath = $savePath('paymentProof', 'payment_proof');
            if ($paymentProofPath === null) {
                return $this->response->setJSON([
                    'error' => 'Payment proof is required (PDF, JPG or PNG)',
                ]);
            }
        }

        $toUpdate = [];
        if ($savedByField['report1'] !== null) { $toUpdate['report1'] = $savedByField['report1']; }
        if ($savedByField['report2'] !== null) { $toUpdate['report2'] = $savedByField['report2']; }
        if ($savedByField['report3'] !== null) { $toUpdate['report3'] = $savedByField['report3']; }
        if ($paymentProofPath !== null) { $toUpdate['documents'] = $paymentProofPath; }

        if (!empty($toUpdate)) {
            $toUpdate['id'] = $applicationId;
            $studentAppModel->save($toUpdate);
        }
        // ===== /NEW attachments =====

        $txId         = $code . time();
        $charges      = 600;
        $SomaCharges  = 100;
        // Proof uploads: school registration fee only (no MOMO service / platform charges)
        $totalAmount  = $paymentMethod === 'proof'
            ? (int) $settingsData->registration_fees
            : ((int) $settingsData->registration_fees + $charges + $SomaCharges);
        $studentNames = trim($firstName . ' ' . $lastName);
        $levelName    = $levelRow ? (string) $levelRow->title : '';
        $schoolName   = (string) $schoolData->name;

        // Offline / other payment with attached proof
        if ($paymentMethod === 'proof') {
            $studentAppModel->save([
                'id' => $applicationId,
                'status' => 1,
            ]);
            $transMdl->save([
                'applicationId'  => $applicationId,
                'transaction_id' => $txId,
                'amount'         => $totalAmount,
                'momo_ref'       => '',
                'status'         => 200,
                'response_body'  => json_encode([
                    'payment_method' => 'proof',
                    'payment_proof'  => $paymentProofPath,
                    'registration_fee' => (int) $settingsData->registration_fees,
                    'charges'        => 0,
                    'platform_fee'   => 0,
                    'note'           => 'Applicant uploaded payment proof — registration fee only (no gateway charges)',
                ]),
            ]);
            $this->notifyParentOfApplication(
                (string) $parentPhone,
                (string) $parentEmail,
                (string) $parentNames,
                $studentNames,
                $schoolName,
                $levelName,
                $code
            );
            return $this->response->setJSON([
                'success'        => 'Application submitted with payment proof',
                'applicationId'  => $applicationId,
                'code'           => $code,
                'payment_proof'  => 1,
            ]);
        }

        // Bypass live MOMO when payment API is not configured yet.
        if ($paymentBypass) {
            $studentAppModel->save([
                'id' => $applicationId,
                'status' => 1, // treat as completed so Finish flow continues
            ]);
            $transMdl->save([
                'applicationId'  => $applicationId,
                'transaction_id' => $txId,
                'amount'         => $totalAmount,
                'momo_ref'       => '',
                'status'         => 200,
                'response_body'  => json_encode(['bypass' => true, 'payment_method' => 'momo', 'note' => 'Payment API disabled — pending real gateway']),
            ]);
            $this->notifyParentOfApplication(
                (string) $parentPhone,
                (string) $parentEmail,
                (string) $parentNames,
                $studentNames,
                $schoolName,
                $levelName,
                $code
            );
            return $this->response->setJSON([
                'success'       => 'Application submitted (payment gateway coming soon)',
                'applicationId' => $applicationId,
                'code'          => $code,
                'payment_bypass'=> 1,
            ]);
        }

        $input = (object)[
            'schoolPhone'           => $schoolData->mtn_momo_phone,
            'phone'                 => $momoPhone,
            'grossAmount'           => $totalAmount,
            'schoolAmount'          => $settingsData->registration_fees,
            'chargesAmount'         => $charges,
            'somanetChargesAmount'  => $SomaCharges
        ];

        $applicant = (object)[
            'names' => $firstName . " " . $lastName,
            'code'  => $code
        ];
        $this->registrationPayment($txId, $input, $applicant);

        $transMdl->save([
            'applicationId'  => $applicationId,
            'transaction_id' => $txId,
            'amount'         => $totalAmount,
            'status'         => 202
        ]);

        return $this->response->setJSON([
            "success"       => 'payment request send',
            'applicationId' => $applicationId
        ]);


    } catch (\Exception $e) {
        return $this->response->setJSON(["error" => "Error: " . $e->getMessage()]);
    }
}

	public
	function get_registration_status()
	{
		$applicationId = $this->request->getGet('applicationId');
		$appMdl = new StudentApplicationModel();
		$data = $appMdl->select('status,code')->where('id', $applicationId)->get(1)->getRow();
		if ($data == null) {
			return $this->response->setJSON(["error" => "Oops, invalid data"]);
		}
		if ($data->status == 1) {
			return $this->response->setJSON(["success" => "1", 'code' => $data->code]);
		}
		if ($data->status == 2) {
			return $this->response->setJSON(["error" => "Payment failed, please try again later"]);
		}
	}

	public
	function updateRegistrationPaymentStatus()
	{
		$jsonData = file_get_contents('php://input');
		$input = json_decode($jsonData);
		$appMdl = new StudentApplicationModel();
		$appTMdl = new ApplicationTransactionModel();
		log_message("alert", "request" . $jsonData);
		$appTransaction = $appTMdl->select('application_transactions.applicationId,application_transactions.status,
		ap.phoneNumber,ap.fname,ap.lname,ap.level,ap.parentNames,ap.parentPhoneNumber,ap.email,d.code as dept_code,ap.code,s.acronym,s.name as schoolName,application_transactions.id,l.title as levelName')
				->join('applications ap', 'ap.id = application_transactions.applicationId')
				->join('departments d', 'd.id = ap.department_id')
				->join('levels l', 'l.id = ap.level')
				->join('schools s', 's.id = ap.schoolId')
				->where('transaction_id', $input->external_transaction_id)
				->get(1)->getRow();
		if ($appTransaction != null && $appTransaction->status != 1) {
			try {
				$status = 2;
				if ($input->status_code == 200) {
					$status = 1;
				}
				$appMdl->save(["id" => $appTransaction->applicationId, "status" => $status]);
				$appTMdl->save(
						[
								'id' => $appTransaction->id,
								'momo_ref' => $input->momo_ref_number,
								'response_body' => $jsonData,
								'status' => $input->status_code
						]
				);
				if ($input->status_code == 200) {
					$message = "{$appTransaction->fname} {$appTransaction->lname} Wasabye umwanya mu mwaka wa {$appTransaction->levelName} {$appTransaction->dept_code} code yawe ikuranga uhawe ni {$appTransaction->code} yikoreshe usoze kuzuza ibisabwa uhabwe umwanya wasabye.";
					log_message("alert", "SMS TO {$appTransaction->phoneNumber} - " . $message);
					$this->sendSMS($appTransaction->phoneNumber, $message, $result);
					$this->notifyParentOfApplication(
						(string) ($appTransaction->parentPhoneNumber ?? ''),
						(string) ($appTransaction->email ?? ''),
						(string) ($appTransaction->parentNames ?? 'Parent'),
						trim($appTransaction->fname . ' ' . $appTransaction->lname),
						(string) ($appTransaction->schoolName ?? $appTransaction->acronym),
						(string) $appTransaction->levelName,
						(string) $appTransaction->code
					);
				}

				return $this->response->setJSON(['status' => 'success']);
			} catch (\ReflectionException|\Exception $e) {
				log_message("alert", "exception " . $e->getMessage());
				return $this->response->setStatusCode(500)->setJSON(array("message" => "System error, please try again later"));
			}
		} else {
			log_message("alert", "Transaction not found");
		}
	}

	public
	function pendingRegistrations()
	{
		$this->_preset();
		$data = $this->data;
		$applicationMdl = new StudentApplicationModel();
		$school_id = $this->session->get("soma_school_id");
		$data['title'] = lang("app.pendingRegistration");
		$data['subtitle'] = lang("app.pendingRegistration");
		$data['page'] = "pendingRegistration";
		$data['pendings'] = $applicationMdl->select("applications.id,concat(fname,' ',lname) as applicant,
		if(gender='M','Male','Famele') as gender,
		phoneNumber,parentType,
		parentPhoneNumber,parentNames,dateOfBirth,l.title as level,if(studyingMode=0,'Boarding','Day') mode,applications.status,code,admitted")
				->join("levels l", "l.id=applications.level")
				->where("admitted", 0)
				->where("schoolId", $school_id)
				->get()->getResultArray();
		$data['content'] = view("pages/pendingRegistrations", $data);
		return view('main', $data);
	}

	public
	function registrationsDocument($applicationId)
	{
		$this->_preset();
		$documentMdl = new DocumentsModel();
		$documents = $documentMdl->select("id,documentName,fileName")
				->where("applicationId", $applicationId)
				->get()->getResultArray();
		if ($documents == null) {
			return $this->response->setStatusCode(404)->setJSON(["error" => "No data found"]);
		} else {
			return $this->response->setJSON($documents);
		}
	}

	public
	function downloadDocument($id)
	{
		$this->_preset();
		$mdl = new DocumentsModel();
		$docs = $mdl->select("documentName")->where("id", $id)->get()->getRowArray();
		$documentName = $docs['documentName'];
		$file = ('./assets/uploads/documents/' . $documentName);
		if (file_exists($file)) {
			header('Content-Description: File Transfer');
			header('Content-Type: application/octet-stream');
			header('Content-Disposition: attachment; filename="' . basename($file) . '"');
			header('Expires: 0');
			header('Cache-Control: must-revalidate');
			header('Pragma: public');
			header('Content-Length: ' . filesize($file));
			readfile($file);
			exit;
		} else {
			echo "zero";
		}
	}

	public
	function getApproveStudentInformation($application)
	{
		$this->_preset();
		$applicationMdl = new StudentApplicationModel();
		$classMdl = new ClassesModel();
		$data = $applicationMdl->select("applications.id,
		l.title as level,
		l.id as levelId,
		f.title as faculty,
		f.id as facultyId,
		d.title as dpt,
		d.id as dptId,
		")->join("levels l", "l.id=applications.level")
				->join("faculty f", "f.id=applications.faculty_id")
				->join("departments d", "d.id=applications.department_id")
				->where("applications.id", $application)
				->get()
				->getRowArray();
		if (!$data) {
			return $this->response->setJSON(["error" => "Application not found"]);
		}
		$classes = $classMdl->select("classes.id,classes.title,d.title as department_name,d.code as dept_code,l.title as level_name
		,f.type,f.abbrev as faculty_code")
				->join("departments d", "d.id=classes.department and d.id={$data['dptId']}")
				->join("levels l", "l.id=classes.level and l.id={$data['levelId']}")
				->join("faculty f", "f.id=d.faculty_id and f.id={$data['facultyId']}")
				->where("classes.school_id", $this->session->get("soma_school_id"))
				->orderBy("classes.title", "ASC")
				->get()->getResultArray();

		$defaultClassId = null;
		$defaultClassLabel = null;
		if (count($classes) === 1) {
			$defaultClassId = (int) $classes[0]['id'];
			$defaultClassLabel = trim(($classes[0]['level_name'] ?? '') . ' ' . ($classes[0]['title'] ?? '') . ' (' . ($classes[0]['department_name'] ?? '') . ')');
		} elseif (count($classes) > 1) {
			// Prefer first class; registration already fixed level/dept
			$defaultClassId = (int) $classes[0]['id'];
			$defaultClassLabel = trim(($classes[0]['level_name'] ?? '') . ' ' . ($classes[0]['title'] ?? '') . ' (' . ($classes[0]['department_name'] ?? '') . ')');
		}

		return $this->response->setJSON([
			"structure" => $data,
			"classes" => $classes,
			"defaultClassId" => $defaultClassId,
			"defaultClassLabel" => $defaultClassLabel,
		]);
	}

	public
	function manipulateApproveStudentsRegistration()
	{
		$this->_preset();
		$applicationMdl = new StudentApplicationModel();
		$studentMdl = new StudentModel();
		$classRecordMdl = new ClassRecordModel();
		$classMdl = new ClassesModel();
		$applicationId = $this->request->getPost("applicationId");
		$classId = $this->request->getPost("classId");
		$application = $applicationMdl->select("id,fname,lname,
		gender,phoneNumber,parentType,parentPhoneNumber,parentNames,dateOfBirth,
		level,studyingMode,faculty_id,department_id,schoolId")
				->where("id", $applicationId)
				->get()->getRowArray();
		if (!$application) {
			return $this->response->setJSON(["error" => "Application not found"]);
		}

		// Use class from registration structure when not explicitly posted
		if (empty($classId)) {
			$match = $classMdl->select("classes.id")
					->where("classes.school_id", $this->session->get("soma_school_id"))
					->where("classes.department", $application['department_id'])
					->where("classes.level", $application['level'])
					->orderBy("classes.title", "ASC")
					->get(1)->getRowArray();
			if ($match) {
				$classId = $match['id'];
			}
		}
		if (empty($classId)) {
			return $this->response->setJSON(["error" => "No matching class found for this application's level and department. Create the class first."]);
		}

		$regNo = $this->_generate_regno(true);
		$studentData = [
				"school_id" => $this->session->get("soma_school_id"),
				"fname" => $application['fname'],
				"lname" => $application['lname'],
				"phone" => $application['phoneNumber'],
				"regno" => $regNo,
				"sex" => $application['gender'],
				"dob" => $application['dateOfBirth'],
				"status" => 1,
				"studying_mode" => $application['studyingMode'],
				"created_by" => $this->session->get("soma_id")];
		if ($application['parentType'] == 1) {
			$studentData['father'] = $application['parentNames'];
			$studentData['ft_phone'] = $application['parentPhoneNumber'];
		} else if ($application['parentType'] == 2) {
			$studentData['mother'] = $application['parentNames'];
			$studentData['mt_phone'] = $application['parentPhoneNumber'];
		} else {
			$studentData['guardian'] = $application['parentNames'];
			$studentData['gd_phone'] = $application['parentPhoneNumber'];
		}
		try {
			$studentId = $studentMdl->insert($studentData);
			if ($studentId > 0) {
				$parentData = ["student_id" => $studentId,
						"parentNames" => $application['parentNames'],
						"type" => parentType($application['parentType']),
						"phone" => $application['parentPhoneNumber']];
				$classData = ["student" => $studentId, "year" => $this->data['academic_year'], "class" => $classId, "status" => 1];
				$applicationMdl->save(["id" => $applicationId, "admitted" => 1]);
				$classRecordMdl->save($classData);
				$message = "{$application['lname']} {$application['fname']} wamaze guhabwa umwanya wasabye koresha app yitwa SOMANET CODE YAWE {$regNo} ";
				$this->sendSMS($application['phoneNumber'], $message, $result);
			}
			return $this->response->setJSON(array("success" => "Applicant approved successfully"));
		} catch (\Exception $e) {
			return $this->response->setJSON(array("error" => "Error: " . $e->getMessage()));
		}
	}

	public
	function resendApplicationSms($applicationId): Response
	{
		$this->_preset();
		$applicationMdl = new StudentApplicationModel();
		$appTransaction = $applicationMdl->select("applications.id,
		applications.fname,
		applications.lname,
		applications.phoneNumber,
		applications.code,
		l.title as levelName,
		d.code as dept_code")->join("levels l", "l.id=applications.level")
				->join("faculty f", "f.id=applications.faculty_id")
				->join("departments d", "d.id=applications.department_id")
				->where("applications.id", $applicationId)
				->get()
				->getRow();
		try {
			$message = "{$appTransaction->fname} {$appTransaction->lname} Wasabye umwanya mu mwaka wa {$appTransaction->levelName} {$appTransaction->dept_code} code yawe ikuranga uhawe ni {$appTransaction->code} yikoreshe usoze kuzuza ibisabwa uhabwe umwanya wasabye.";
			log_message("alert", "SMS TO {$appTransaction->phoneNumber} - " . $message);
			$this->sendSMS($appTransaction->phoneNumber, $message, $result);
			return $this->response->setJSON(["success" => "Message sent successfully"]);
		} catch (\Exception $e) {
			return $this->response->setJSON(array("error" => "Error: " . $e->getMessage()));
		}

	}

	public
	function studentApplication($code = null)
	{
		$data['title'] = "Somanet";
		$data['page'] = 'Application';
		$data['subtitle'] = lang("app.SchoolManagementSystem");
		if ($code != null) {
			$appMdl = new StudentApplicationModel();
			$appData = $appMdl->select('fname,lname,gender,phoneNumber,code,id,status')
					->where('code', $code)
					->get(1)->getRow();
			if ($appData == null) {
				$data['error'] = "oops, Invalid registration code";
			} else {
				if ($appData->status == 0) {
					$data['error'] = "oops, Your registration payment did not succeed";
				} else if ($appData->status == 2) {
					$data['error'] = "oops, Your registration payment failed";
				} else {
					// Documents already collected on the Documents step during registration
					$data['application'] = $appData;
					$data['applicationId'] = $appData->id;
				}
			}
		}
		$type = $this->request->getGet('type');
		$data['type'] = $type;
		$data['content'] = view('landingPage/application', $data);
		return view('landing_page', $data);
	}

	public
	function saveStudentApplication()
	{
		$model = new StudentApplicationModel();
		$input = json_decode(file_get_contents("php://input"));

		$model->save([
				'names' => $input->names,
				'gender' => $input->gender,
				'phoneNumber' => $input->phone,
				'parentPhoneNumber' => $input->parentPhone,
				'level' => $input->level,
				'payment' => 'paid',
		]);

		return $this->response->setJSON("Successfully saved");
	}

	public
	function getPendingStudentApplication()
	{
		$model = new StudentApplicationModel();
		$input = json_decode(file_get_contents("php://input"));

		$code = $input->code;

		$result = $model->where('code', $code)->first();
		if (!empty($result)) {
			return $this->response->setJSON($result);
		} else {
			$data['message'] = "Record not Found";
			return $this->response->setJSON($data);
		}
	}

	public
	function completeStudentApplication()
	{
		$applicationModel = new StudentApplicationModel();
		$documentModel = new DocumentsModel();

		$parent = $this->request->getPost("parentNames");


		if ($reportfile = $this->request->getFiles()) {
			foreach ($reportfile['upload'] as $report) {
				if ($report->isValid() && !$report->hasMoved()) {
					$newName = $report->getRandomName();
					$report->move('assets/reports', $newName);
					$documentModel->save([
							'documentName' => $newName,
					]);
				}
			}
		}
	}

	public
	function global_student_marks($type = null)
	{
		$data['title'] = "Student marks";
		$data['subtitle'] = lang("View student marks");
		$data['page'] = "marks";
		$data['type'] = $type;
//		if (!empty($type)){
//			return view('landingPage/student_marks', $data);
//		} else {
//			$data['content'] = view('landingPage/student_marks', $data);
//			return view('landing_page', $data);
//		}
		$data['type'] = $type;
		$data['content'] = view('landingPage/student_marks', $data);
		return view('landing_page', $data);
	}

	public
	function upload_application_docs()
	{
		$file = $this->request->getFile("file");
		if (!$file || !$file->isValid()) {
			return $this->response->setStatusCode(400)->setJSON(array("error" => "Invalid file"));
		}
		$ext = strtolower($file->getClientExtension() ?: $file->getExtension() ?: '');
		if ($ext != "pdf") {
			return $this->response->setStatusCode(400)->setJSON(array("error" => lang("app.fileNotAllowed") . " " . $ext));
		}
		if ($file->getSize() > 10 * 1024 * 1024) {
			return $this->response->setStatusCode(400)->setJSON(array("error" => lang("app.fileSizeBigger") . " (max 10MB)"));
		}
		$stMdl = new StudentApplicationModel();
		$appId = $this->request->getPost('applicationId');
		$student = $stMdl->select('id,code,fname')->where('id', $appId)->get(1)->getRow();
		if ($student == null) {
			return $this->response->setStatusCode(400)->setJSON(["error" => "Registration application not found " . $appId]);
		}
		$docName = $file->getName();
		$name = $student->code . "_" . $file->getName();
		$name = urlencode(str_replace(' ', '', $name));
		$docsPath = FCPATH . "assets/documents/";
		if (!is_dir($docsPath)) {
			@mkdir($docsPath, 0775, true);
		}
		if (file_exists($docsPath . $name)) {
			return $this->response->setStatusCode(400)->setJSON(["error" => 'This document already uploaded']);
		}
		if ($file->move($docsPath, $name, true)) {
			//save to db
			$docMdl = new DocumentsModel();
			try {
				$docMdl->save(["applicationId" => $appId, "fileName" => $name, 'documentName' => $docName]);
			} catch (\Exception $e) {
				return $this->response->setStatusCode(400)->setJSON(["error" => 'Document not uploaded']);
			}
			return $this->response->setJSON(array("message" => 'Document uploaded', "student" => $student->fname));
		} else {
			//upload error
			return $this->response->setStatusCode(400)->setJSON(array("error" => $file->getErrorString()));
		}
	}


	public
	function staff_all_report_data($pdf = false)
	{
		$this->_preset();
		$data = $this->data;
		$staff = $this->request->getGet("staff");
		$date1 = $this->request->getGet("date1");
		$date1_unix = strtotime($date1);
		$date2 = $this->request->getGet("date2");
		$date2_unix = strtotime($date2) + 86399;
		$staffMdl = new StaffModel();
		$staffs = $staffMdl->select("staffs.*,sh.options,sh.title,p.title as post_title,lv.fromDate as leave_start,lv.toDate as leave_end")
				->join("shifts sh", "sh.id=staffs.shift_id")
				->join("leaves lv", "lv.requested_by=staffs.id and lv.status=1 and (lv.fromDate>='$date1_unix' OR lv.toDate<='$date2_unix')", "LEFT")
				->join("posts p", "p.id=staffs.post")
				->where("staffs.school_id", $this->session->get("soma_school_id"))
				->where("staffs.id", $staff)
				->get()->getRowArray();
		$data['staffs'] = $staffs;
		$attMdl = new AttendanceRecordsModel();
		$data["records"] = $attMdl->select("time_in,coalesce(time_out,0) as time_out")
				->where("user_id", $staffs['id'])
				->where("user_type", 1)
				->where("time_in>='$date1_unix' and time_in<='$date2_unix'")
				->groupBy("user_id")
				->groupBy("date_format(from_unixtime(time_in),'%d-%m-%Y')")
				->orderBy("time_in", "ASC")
				->get()->getResultArray();
		$data['show_header'] = false;
		$data['date1'] = $date1;
		$data['date2'] = $date2;
		$data['pdf'] = false;
		if ($pdf == 'true') {
			$data['pdf'] = true;
			$html = view("pages/reports/staff_report_individual", $data);
			try {
				$mask = FCPATH . "assets/templates/*.html";
				array_map('unlink', glob($mask));//clear previous cards
				$wkhtmltopdf = new Wkhtmltopdf(array('path' => FCPATH . 'assets/templates/'));
				$wkhtmltopdf->setTitle(lang("app.Staffattendancereport"));
				$wkhtmltopdf->setHtml($html);
				$wkhtmltopdf->setOrientation("portrait");
//					$wkhtmltopdf->setOptions(array("page-width" => "278px", "page-height" => "430px"));
				$wkhtmltopdf->setMargins(array("top" => 0, "left" => 0, "right" => 0, "bottom" => 0));
				$wkhtmltopdf->output(Wkhtmltopdf::MODE_EMBEDDED, "staff_report_individual" . time() . ".pdf");
			} catch (\Exception $e) {
				echo $e->getMessage();
			}
		} else {
			echo view("pages/reports/staff_report_individual", $data);
		}

	}
}

