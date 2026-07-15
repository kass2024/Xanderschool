<?php

namespace App\Controllers;

use App\Models\AcademicYearModel;
use App\Models\ActiveTermModel;
use App\Models\AttendanceRecordsModel;
use App\Models\BookModel;
use App\Models\BusModel;
use App\Models\BookRecordModel;
use App\Models\ClassesModel;
use App\Models\ClassRecordModel;
use App\Models\CourseAttendanceModel;
use App\Models\CourseAttendanceRecordsModel;
use App\Models\CourseModel;
use App\Models\CourseRecordModel;
use App\Models\DailyAttendanceModel;
use App\Models\DisciplineModel;
use App\Models\ExtraFeesModel;
use App\Models\FeesRecordModel;
use App\Models\LeaveModel;
use App\Models\PaymentModel;
use App\Models\PermissionModel;
use App\Models\RouteModel;
use App\Models\SchoolFeesModel;
use App\Models\SchoolModel;
use App\Models\SmsModel;
use App\Models\SmsRecipientModel;
use App\Models\StaffModel;
use App\Models\StudentModel;
use App\Models\TermModel;
use App\Models\TransportRecordModel;
use App\Models\UpdateVersionModel;
use App\Models\MarksModel;
use App\Models\BoardingAttendanceModel;
use CodeIgniter\HTTP\Response;

class Api extends BaseController
{
	private $data = array();

//	public function __construct()
//	{
//		helper('qonics');
//        // Set default language from GET, session, or fallback to 'en'
//            $lang = 'en';
//            $this->data = [
//                'lang' => $lang,
//                // Other default values
//            ];
//            service('request')->setLocale($lang);
//            $this->session->set('lang', $lang);
//	}
    
    public function __construct()
        {
//            parent::__construct(); // Initialize parent controller services
            helper('qonics');
            
            // Manually initialize session if not set
            $this->session = $this->session ?? \Config\Services::session();
            
            // Set default language from GET, session, or fallback to 'en'
            $lang = 'en';
            $this->data = [
                'lang' => $lang,
                // Other default values
            ];
            service('request')->setLocale($lang);
            $this->session->set('lang', $lang);
        }

	public function index()
	{
		echo "Welcome on IOTXAD API";
	}

	private function _preset($school_id)
	{
		$schoolMdl = new SchoolModel();
		$skl = $schoolMdl->select("schools.name,schools.extra_sms,at.term,schools.acronym,p.sms_limit,schools.status,schools.active_term,at.sms_usage,schools.discipline_max,at.academic_year")
			->join("packages p", "p.id=schools.package")
			->join("active_term at", "at.id=schools.active_term")
			->where("schools.id", $school_id)->get()->getRow();
		if ($skl->status == 0) {
			//school is disabled by somanet admin
			$this->session->setFlashdata('error', lang("app.lockedBySomanetAdmin"));
			header("location: " . base_url('logout'));
			die();
		}
		if ($skl->active_term == 0 && $this->session->get('soma_post') != 1) {
			//no active term, disable other accounts except admin
			$this->session->setFlashdata('error', lang("app.activeTermNotSet"));
			header("location: " . base_url('login'));
			die();
		}
		$this->data['academic_year'] = $skl->academic_year;
		$this->data['term'] = $skl->term;
		$this->data['sms_limit'] = $skl->sms_limit;
		$this->data['sms_usage'] = $skl->sms_usage;
		$this->data['school_acronym'] = $skl->acronym;
//		$this->data['remaining_sms'] = $skl->sms_limit - $skl->sms_usage + $skl->extra_sms;
		$this->data['remaining_sms'] = $skl->extra_sms;
		$this->data['active_term'] = $skl->active_term;
		$this->data['discipline_max'] = $skl->discipline_max;
        // Set language from GET parameter, session, or default to 'en'
            $lang = $this->data['lang'] ?? 'en';
            $this->data['lang'] = $lang;
		$this->session->set(['soma_academics_year' => $skl->academic_year]);
		service('request')->setLocale($lang);
	}

	public function check_server()
	{
		return $this->response->setJSON(array("success" => "Soma"));
	}

	public function save_student_marks($school_id)
	{

		//Check if all comming information are valid
		$info = $this->request->getPost();
		if (!trim($info['marks'])) {
			return $this->response->setJSON(["error" => "No marks to be saved found", "success" => false]);
		}
		if (!is_numeric($info['marks'])) {
			return $this->response->setJSON(["error" => "Invalid Marks Provided!", "success" => false]);
		}
		if (!trim($info['outof'])) {
			return $this->response->setJSON(["error" => "No maximum is found!", "success" => false]);
		}

		if ($info['marks'] > $info['outof']) {
			return $this->response->setJSON(["error" => sprintf("Invalid marks obtained %s out of %s", $info['marks'], $info['outof']), "success" => false]);
		}
		//Get the Active Term Information
		$schoolMdl = new SchoolModel();
		$active_term = $schoolMdl->select("schools.active_term, at.use_period")->join('active_term at', 'schools.active_term=at.id')->where('schools.id=' . $school_id)->get()->getResultArray();

		//Name Make sure to save data in tha database
		$marksRecordModel = new MarksModel();

		$old_records = $marksRecordModel->select('id')
			->where('student_id=' . $info['student_id'])
			->where('term=' . $active_term[0]['active_term'])
			->where('course_id=' . $info['course_id'])
			->where('class_id=' . $info['class_id'])
			->where('mark_type=' . $info['mark_type'])
			// ->where('marks='.$info['marks'])
			->where('outof=' . $info['outof'])
			->where('created_by=' . $info['created_by'])
			->get()->getResultArray();
		if (count($old_records) > 0) {
			return $this->response->setJSON(["error" => "The Comming information seems to be included before!", "success" => false]);
		}

		$try = $marksRecordModel->save([
			'student_id' => $info['student_id'],
			'term' => $active_term[0]['active_term'],
			'examDate' => (new \DateTime($info['examDate']))->getTimestamp(),
			'course_id' => $info['course_id'],
			'class_id' => $info['class_id'],
			'mark_type' => $info['mark_type'],
			'marks' => $info['marks'],
			'outof' => $info['outof'],
			'cat_type' => $info['cat_type'],
			'period' => $info['period'],
			'created_by' => $info['created_by'],
		]);
		$data = [];
		$data["success"] = "true";
		$data["message"] = "Marks Recorded!";
		// $data["active_term"] = $active_term[0];
		// $data["marks"] = $try;

		return $this->response->setJSON($data);
	}

	public function login()
	{
        // Ensure language is set
            $lang = $this->data['lang'] ?? 'en';
            $this->data['lang'] = $lang;
            service('request')->setLocale($lang);
        
		$model = new StaffModel();
		$email = $this->request->getPost('email');
		$password = $this->request->getPost('password');
		$validation = \Config\Services::validation();
		$validation->setRule("email", 'email', 'trim|required');
		$validation->setRule("password", 'password', 'required|min_length[6]');
		if ($validation->run() !== FALSE) {
			return $this->response->setJSON(array("error" => $validation->getError()));
		} else {
			$result = $model->checkUser($email);
			$this->session->setFlashdata('email', $email);
			if ($result != null) {
				if (password_verify($password, $result->password)) {
					if ($result->status == 1 || $result->status == 2) {
						if ($result->school_status == 0) {
							return $this->response->setJSON(array("error" => lang("app.lockedBySomanetAdmin")));
						} else {
							$model->save(array("id" => $result->id, "last_login" => time()));
			
							$data = [
								'soma_name' => $result->fname . ' ' . $result->lname,
								'soma_id' => $result->id,
								'soma_term' => $result->active_term,
								'soma_term_number' => $result->term,
								'soma_academic' => $result->academic_year,
								'soma_school_id' => $result->school_id,
								'soma_school' => $result->school_name,
								'soma_post' => $result->post,
								'soma_post_title' => $result->post_title,
								'soma_use_period' => $result->use_period,
								'school_country' => isset($result->school_country) ? $result->school_country : 'Rwanda',
								'academic_type' => isset($result->academic_type) ? $result->academic_type : '', // ✅ added
								'school_phone' => isset($result->school_phone) ? $result->school_phone : '', // ✅ Add this line
								'school_email' => isset($result->school_email) ? $result->school_email : '', // ✅ Add this line
								'success' => "Login done",
								'courses' => [],
								'classes' => [],
								'assessmentTypes' => [],
							];
							$csMdl = new CourseModel();
							$coursesData = $csMdl->select("courses.id,courses.title,courses.code,r.class as class_id,courses.marks")
								->join("course_records r", "courses.id=r.course")
								->where("r.year", $result->academic_year)
								->where("find_in_set({$result->term},r.term)>0")
								->where("r.lecturer", $result->id)
								->groupBy("courses.id")
								->groupBy("r.class")
								->get()->getResultArray();
							$data['courses'] = $coursesData;
							$clMdl = new CourseRecordModel();
							$classes = $clMdl->select("c.id,concat(l.title,' ',c.title,' ',d.code) as title")
								->join("classes c", "c.id=course_records.class")
								->join("departments d", "d.id=c.department")
								->join("levels l", "l.id=c.level")
								->where("course_records.year", $result->academic_year)
								->where("course_records.lecturer", $result->id)
								->where("c.school_id", $result->school_id)
								->groupBy("c.id")
								->orderBy("d.code")
								->orderBy("l.title")->get()->getResultArray();
							$data['classes'] = $classes;
							return $this->response->setJSON($data);
						}
					} else {
						return $this->response->setJSON(array("error" => "1"));
					}
				} else {
					return $this->response->setJSON(array("error" => "0"));
				}
			} else {
				return $this->response->setJSON(array("error" => lang("app.userNotFound")));
			}
		}
	}

public function boarding_clock_in()
{
    $student_id = $this->request->getPost("student_id");
    $school_id  = $this->request->getPost("school_id");

    // Validate inputs
    if (empty($student_id) || empty($school_id)) {
        return $this->response->setJSON([
            "success" => false,
            "error"   => "Missing required fields"
        ]);
    }

    // Load active term for this school
    $this->_preset($school_id);
    $active_term = $this->data['active_term'];

    $mdl = new \App\Models\BoardingAttendanceModel();

    // Insert a new clock-in
    $inserted = $mdl->clockIn($student_id, $active_term);

    if (!$inserted) {
        return $this->response->setJSON([
            "success" => false,
            "error"   => "Failed to save clock-in"
        ]);
    }

    // Count today's clock-ins
    $today = date('Y-m-d');
    $records = $mdl->getClockInsByDate($student_id, $today);
    $count   = is_array($records) ? count($records) : 0;

    return $this->response->setJSON([
        "success"    => true,
        "message"    => "Clock-in recorded",
        "student_id" => $student_id,
        "time"       => date("Y-m-d H:i:s"),
        "count"      => $count
    ]);
}


public function get_boarding_attendance($student_id, $date = null)
{
    $mdl = new \App\Models\BoardingAttendanceModel();

    // Default to today's date if not provided
    if (empty($date)) {
        $date = date('Y-m-d');
    }

    $records = $mdl->getClockInsByDate($student_id, $date);

    if (empty($records)) {
        return $this->response->setJSON([
            "success" => false,
            "error"   => lang("app.noAttendanceFound") ?? "No clock-ins found",
            "student_id" => $student_id,
            "date" => $date,
            "count" => 0,
            "attendance" => []
        ]);
    }

    $count = count($records);

    return $this->response->setJSON([
        "success"    => true,
        "student_id" => $student_id,
        "date"       => $date,
        "count"      => $count,
        "attendance" => $records
    ]);
}

public function save_boarding_attendance()
{
    $students    = $this->request->getPost("students"); // JSON array string
    $school_id   = $this->request->getPost("school_id");
    $active_term = $this->request->getPost("active_term");

    if (empty($students) || empty($school_id) || empty($active_term)) {
        return $this->response->setJSON([
            "success" => false,
            "error"   => "Missing required fields"
        ]);
    }

    $ids = json_decode($students, true);
    if (!is_array($ids)) {
        return $this->response->setJSON([
            "success" => false,
            "error"   => "Invalid students format"
        ]);
    }

    $mdl    = new \App\Models\BoardingAttendanceModel();
    $saved  = 0;
    $counts = [];
    $today  = date("Y-m-d");

    foreach ($ids as $sid) {
        if ($mdl->clockIn($sid, $active_term)) {
            $saved++;
        }

        // ✅ Use model counter (faster & cleaner)
        $counts[$sid] = $mdl->getCountByDate($sid, $today);
    }

    return $this->response->setJSON([
        "success"        => true,
        "message"        => "Boarding attendance saved",
        "studentsSent"   => count($ids),
        "studentsSaved"  => $saved,
        "counts"         => $counts  // 🔥 always reflects DB counts
    ]);
}



	public function get_course_global($teacher, $class, $term)
	{
		$atMdl = new ActiveTermModel();
		$tt = $atMdl->select("term,academic_year")->where("id", $term)->get(1)->getRow();
		if ($tt == null) {
			return $this->response->setJSON(array("error" => lang("app.InvalidDataSupplied")));
		}
		$csMdl = new CourseModel();
		$courses = $csMdl->select("courses.id,courses.title,courses.code")
			->join("course_records r", "courses.id=r.course")
			->where("r.year", $tt->academic_year)
			->where("find_in_set({$tt->term},r.term)>0")
			->where("r.class", $class)
			->groupBy("courses.id")
			->get()->getResultArray();
		if (count($courses) > 0) {
			$data = array();
			foreach ($courses as $item) {
				$data['courses'][] = $item;
			}
			return $this->response->setJSON($data);
		}
		return $this->response->setJSON(array("error" => lang("app.noCourseFound")));
	}

	public function get_class($teacher, $school)
	{
		$teacher = $teacher == 0 ? null : $teacher;
		$csMdl = new ClassesModel();
		$classes = $csMdl->get_teacher_classes($teacher, $school);

		//Get the active term id
		$schoolMdl = new SchoolModel();
		$active_term = $schoolMdl->select("schools.active_term, at.use_period, at.academic_year, at.term")->join('active_term at', 'schools.active_term=at.id')->where('schools.id=' . $school)->get()->getResultArray();
		if (count($classes) > 0) {
			$data = array();
			$data['active_term'] = $active_term[0];// <<<<<< This should be always available
			$term = $data['active_term']['term'];
			foreach ($classes as $item) {
				//Now try to get all subjects related to this class.
				$csMdl = new CourseModel();
				$courses = $csMdl->select("courses.id,courses.title,courses.code, r.id AS record_id")
					->join("course_records r", "courses.id=r.course")
					->where("r.year", $data['active_term']['academic_year'])
					->where("find_in_set({$term},r.term)>0")
					->where("r.class", $item['id'])
					->groupBy("courses.id")
					->get()->getResultArray();
				$item['subjects'] = $courses;

				$csMdl = new StudentModel();
				$students = $csMdl->select('students.id,regno,concat(students.fname," ",students.lname) as name, students.fname, students.lname, students.card AS card_id, "" AS mode, students.sex AS gender, photo, COALESCE(students.ft_phone, students.mt_phone, gd_phone, "") AS phone_number')
					->join('class_records cr', 'cr.student=students.id')
					->where('cr.class', $item['id'])
					->where('students.status', 1)
					->where('cr.year', $data['active_term']['academic_year'])
					->get()->getResultArray();
				$item['students'] = $students;
				$data['classes'][] = $item;
			}
			return $this->response->setJSON($data);
		}
		return $this->response->setJSON(array("error" => lang("app.noClassFound")));
	}

	public function get_transport_data($school_id)
	{
		$busMdl = new BusModel();
		$routesMdl = new RouteModel();
		$buses = $busMdl->select("id,car_maker,car_model,plate,places")->where("school_id", $school_id)->get()->getResultArray();
		$routes = $routesMdl->select("id,title,details,price")->where("school_id", $school_id)->get()->getResultArray();
		$data = array();
		foreach ($buses as $item) {
			$data['buses'][] = $item;
		}

		foreach ($routes as $item) {
			$data['routes'][] = $item;
		}
		return $this->response->setJSON($data);
	}

	public function get_transport_records($school_id, $bus, $route, $way, $student_card = null)
	{
		$trMdl = new TransportRecordModel();
		$st_check = $student_card == null ? "1=1" : "st.card='$student_card'";
		$join = $student_card == null ? "inner" : "right";
		$check = $student_card != null ? "1=1" : "bus=" . $bus . " AND route=" . $route . " AND way=" . $way . " AND date_format(transport_records.created_at,'%Y%m%d')='" . date("Ymd") . "'";
		$query = $trMdl->select("way,st.transport_money,st.id as student_id,st.photo,concat(st.fname,' ',st.lname) as name,st.regno,concat(l.title,' ',d.code,' ',c.title) as classe,date_format(transport_records.created_at,'%Y%m%d') as datee")
			->join('students st', 'transport_records.student_id=st.id', $join)
			->join('class_records cr', 'cr.student=st.id')
			->join('classes c', 'c.id=cr.class')
			->join('departments d', 'd.id=c.department')
			->join('levels l', 'l.id=c.level')
			->where("st.school_id", $school_id)
			->where($check)
			->where($st_check)
			->orderBy("transport_records.id", "DESC")
			->get();
		if ($student_card != null) {
			$students = $query->getRowArray();
		} else {
			$students = $query->getResultArray();
		}
		if ($student_card != null) {
			return $students;
		}
		$data = array();
		if (count($students) == 0)
			return $this->response->setJSON(array("error" => "0"));
		foreach ($students as $item) {
			unset($item['transport_money']);
			unset($item['way']);
			$data['students'][] = $item;
		}
		return $this->response->setJSON($data);
	}

	public function add_transport_records($card)
	{
		$stMdl = new StudentModel();
		$bus = $this->request->getPost("bus");
		$created_by = $this->request->getPost("operator");
		$route = $this->request->getPost("route");
		$way = $this->request->getPost("way");
		$bus_title = $this->request->getPost("bus_title");
		$school_id = $this->request->getPost("school_id");
		$this->_preset($school_id);

		if (strlen($card) < 4) {
			//no student id provided
			return $this->response->setJSON(array("error" => lang("app.fatalErrorRestart")));
		}
		if ($school_id == 0) {
			//no school id provided
			return $this->response->setJSON(array("error" => lang("app.fatalErrorLogin")));
		}
		$student = $this->get_transport_records($school_id, $bus, $route, $way, $card);
		if ($student == null) {
			return $this->response->setJSON(array("error" => lang("app.opsStudentNotFound")));
		}
		$student_id = $student['student_id'];
		if ($student['way'] == $way && $student['datee'] == date("Ymd")) {
			return $this->response->setJSON(array("error" => lang("app.studentAlreadyRegistered")));
		}
		$routeMdl = new RouteModel();
		$routee = $routeMdl->select("price")->where("id", $route)->get(1)->getRow();
		if ($routee == null) {
			return $this->response->setJSON(array("error" => lang("app.opsRouteNotFound")));
		}
		if ($routee->price > $student['transport_money']) {
			return $this->response->setJSON(array("error" => lang("app.notEnoughAmountCard")));
		}
		$new_amount = $student['transport_money'] - $routee->price;
		$data = array(
			"bus" => $bus,
			"route" => $route,
			"student_id" => $student_id,
			"way" => $way,
			"price" => $routee->price,
			"created_by" => $created_by);
		$trMdl = new TransportRecordModel();
		try {
			$trMdl->save($data);
			//deduct money to card
			$stMdl->save(array("transport_money" => $new_amount, "id" => $student_id));
			//send sms
			$way_str = $way == 0 ? " to School" : " Home";
			$active = $this->data['active_term'];
			$st_data = $this->_get_parent_phone($student_id);
			$phone = $st_data['phone'];
			$msg = lang("app.dearParents") . $student['name'] . lang("app.isOnWayGoing") . $way_str . lang("app.onBUS") . $bus_title;
//			if ($this->_send_sms($phone, $msg, $result, $this->data['remaining_sms'], $this->data['school_acronym'])) {
//				//save sent sms
//				$sms_count = (int)ceil(strlen($msg) / PER_SMS);
//				$this->data['remaining_sms'] = $this->data['remaining_sms'] - 1;//prevent exceeding sms limit
//				$this->_save_sms($active, $phone, $msg, 0, $school_id, "Discipline", $student_id, $sms_count);
//			} else {
//				$this->_save_sms($active, $phone, $msg, 0, $school_id, "Discipline", $student_id, 0, $result);
//			}
            
            if ($this->sendSMS($phone, $msg, $result)) {
                //save sent sms
                $sms_count = (int)ceil(strlen($msg) / PER_SMS);
                $this->data['remaining_sms'] = $this->data['remaining_sms'] - 1;//prevent exceeding sms limit
                $this->_save_sms($active, $phone, $msg, 0, $school_id, "Discipline", $student_id, $sms_count);
            } else {
                $this->_save_sms($active, $phone, $msg, 0, $school_id, "Discipline", $student_id, 0, $result);
            }
            
			$student = $student;
			$student['success'] = "1";
			$student['amount'] = $new_amount;
			return $this->response->setJSON($student);
		} catch (\Exception $e) {
			return $this->response->setJSON(array("error" => lang("app.OopsAction") . $e));
		}
	}

	public function send_payment_notification()
	{
		$school_id = $this->request->getPost('school_id');
		$this->_preset($school_id);
		$student_id = $this->request->getPost('student_id');
		$amount = $this->request->getPost('amount');
		$stMdl = new StudentModel();
		$student = $stMdl->select('regno,concat(students.fname," ",students.lname) as name,ft_phone,mt_phone,gd_phone')
			->where('students.id', $student_id)->get(1)->getRow();
		if ($student == null) {
			return $this->response->setJSON(array("message" => "Oops, Invalid student data"));
		}
		$msg = "Mubyeyi dufatanije kurera {$student->name},turakwibutsa kwishyura umwenda ufite ungana na " . number_format($amount);
		$phone = '';
		if (strlen($student->ft_phone) > 3) {
			$phone = $student->ft_phone;
		} else if (strlen($student->mt_phone) > 3) {
			$phone = $student->mt_phone;
		} else if (strlen($student->gd_phone) > 3) {
			$phone = $student->gd_phone;
		}
		if ($phone < 5) {
			return $this->response->setJSON(array("message" => "Oops, Invalid parent phone: $phone"));
		}
//		if ($this->_send_sms($phone, $msg, $result, $this->data['remaining_sms'], $this->data['school_acronym'])) {
//			//save sent sms
//			$sms_count = (int)ceil(strlen($msg) / PER_SMS);
//			$this->data['remaining_sms'] = $this->data['remaining_sms'] - 1;//prevent exceeding sms limit
//			$this->_save_sms($this->data['active_term'], $phone, $msg, 0, $school_id, "Payment", $student_id, $sms_count);
//			return $this->response->setJSON(array("success" => "Notification sent"));
//		} else {
//			$this->_save_sms($this->data['active_term'], $phone, $msg, 0, $school_id, "Payment", $student_id, 0, $result);
//			return $this->response->setJSON(array("message" => "Oops, SMS not sent: $result"));
//		}
        if ($this->sendSMS($phone, $msg, $result)) {
            //save sent sms
            $sms_count = (int)ceil(strlen($msg) / PER_SMS);
            $this->data['remaining_sms'] = $this->data['remaining_sms'] - 1;//prevent exceeding sms limit
            $this->_save_sms($this->data['active_term'], $phone, $msg, 0, $school_id, "Payment", $student_id, $sms_count);
            return $this->response->setJSON(array("success" => "Notification sent"));
        } else {
            $this->_save_sms($this->data['active_term'], $phone, $msg, 0, $school_id, "Payment", $student_id, 0, $result);
            return $this->response->setJSON(array("message" => "Oops, SMS not sent: $result"));
        }
	}

public function get_students($class, $academicYear, $termId = null)
{
    $csMdl = new StudentModel();

    // Boarding: include counts if $termId is passed
    if (!empty($termId)) {
        $students = $csMdl->select('students.id, 
                                    regno, 
                                    CONCAT(students.fname," ",students.lname) as name, 
                                    photo,
                                    (
                                        SELECT COUNT(*) 
                                        FROM boarding_attendance ba
                                        WHERE ba.student_id = students.id
                                        AND ba.active_term = ' . (int)$termId . '
                                    ) as count')
            ->join('class_records cr', 'cr.student = students.id')
            ->where('cr.class', $class)
            ->where('students.status', 1)
            ->where('cr.year', $academicYear)
            ->get()
            ->getResultArray();
    } else {
        // Daily: no count column
        $students = $csMdl->select('students.id, 
                                    regno, 
                                    CONCAT(students.fname," ",students.lname) as name, 
                                    photo')
            ->join('class_records cr', 'cr.student = students.id')
            ->where('cr.class', $class)
            ->where('students.status', 1)
            ->where('cr.year', $academicYear)
            ->get()
            ->getResultArray();
    }

    if (!empty($students)) {
        $baseUrl = base_url('assets/images/profile/');
        $data = [];

        foreach ($students as $item) {
            $item['photo'] = !empty($item['photo'])
                ? $baseUrl . $item['photo']
                : null;

            // For Daily calls (no $termId) → strip out count key if it exists
            if (isset($item['count']) && empty($termId)) {
                unset($item['count']);
            }

            $data['students'][] = $item;
        }

        return $this->response->setJSON($data);
    }

    return $this->response->setJSON([
        "error" => lang("app.noStudentFound")
    ]);
}


	public function sync($option, $school_id)
	{
		$this->_preset($school_id);
		$updateVersion = $this->request->getGet("updateVersion");
		$data = [];
		$other_info = [];
		switch ($option) {
			case "student":
				$Mdl = new StudentModel();
				$other_info = $dt = $Mdl->get_student_simple2("students.updateVersion>$updateVersion", $school_id);
				$latest_version = 1;
				foreach ($dt as $item) {
					if ($latest_version < $item['updateVersion'])
						$latest_version = $item['updateVersion'];
					$data['students'][] = $item;
				}
				$uvMdl = new UpdateVersionModel();
				$update_v_data = $uvMdl->select("version")->where("type", "student")->where("school_id", $school_id)->get(1)->getRow();
				if ($update_v_data != null) {
					if ($latest_version >= $update_v_data->version) {
						$uvMdl->where("type", "student")->where("school_id", $school_id)->update(null, array("version" => ($latest_version + 1)));
					}
				} else {
					$uvMdl->insert(array("version" => ($latest_version + 1), "type" => "student", "school_id" => $school_id));
				}
				break;
			case "student_v2":
				$updatedAt = $this->request->getGet("updatedAt");
				$Mdl = new StudentModel();
				$other_info = $dt = $Mdl->get_student_simple2("UNIX_TIMESTAMP(students.updated_at)>$updatedAt", $school_id, false, $this->data['academic_year']);
				$latest_version = 0;
				foreach ($dt as $item) {
					$data['students'][] = $item;
				}
				break;
			case "staff":
				$Mdl = new StaffModel();
				$dt = $Mdl->select("staffs.id,concat(fname,' ',lname) as name,phone,email,photo,p.title as post_title,updateVersion")
					->join("posts p", "p.id=staffs.post")
					->where("school_id", $school_id)
					->where("updateVersion>" . $updateVersion)->get()->getResultArray();
				$latest_version = 1;
				foreach ($dt as $item) {
					if ($latest_version < $item['updateVersion'])
						$latest_version = $item['updateVersion'];
					$data['staffs'][] = $item;
				}
				$uvMdl = new UpdateVersionModel();
				$update_v_data = $uvMdl->select("version")->where("type", "staff")->where("school_id", $school_id)->get(1)->getRow();
				if ($update_v_data != null) {
					if ($latest_version >= $update_v_data->version) {
						$uvMdl->where("type", "staff")->where("school_id", $school_id)->update(null, array("version" => ($latest_version + 1)));
					}
				} else {
					$uvMdl->insert(array("version" => ($latest_version + 1), "type" => "staff", "school_id" => $school_id));
				}
				break;
		}
		if (count($data) > 0) {
			return $this->response->setJSON($data);
		}
		return $this->response->setJSON(array("error" => "0", "custom_message" => "Here We are trying information", "option" => $option, "school_id" => $school_id, "test_info" => $other_info));
	}

	public function get_fees()
	{
//		$input = json_decode(file_get_contents('php://input'));
		if (isset($_GET['debug']) && $this->request->getGet('debug') == 1) {
			$debug_data = array('inputs' => $_GET, 'debugMode' => 'YES', "header" => array("reference-id" => "{$this->request->getHeader("X-Reference-Id")}",
				"api-key" => "{$this->request->getHeader("X-Api-Key")}"));
			return $this->response->setStatusCode(200)->setJSON($debug_data);
		}
		if (!isset($_GET['studentRegno']) || $this->request->getHeader("X-Reference-Id") == null || $this->request->getMethod() != 'get') {
			return $this->response->setStatusCode(200)->
			setJSON(['error' => 'Invalid request', 'statusCode' => 400
				, 'message' => 'Error occurred: please provide all required data']);
		}
		$student = $this->request->getGet('studentRegno');
		$year = date('Y');

		$term = 1;
		if (!$this->authenticate_api($res)) {
			return $this->response->setStatusCode(200)->
			setJSON(['error' => 'Authentication error', 'statusCode' => 403
				, 'message' => 'Error occurred: ' . $res]);
		}
		$sfMdl = new SchoolFeesModel();;
		$classMdl = new ClassesModel();
		$stMdl = new StudentModel();
		$student_info = $stMdl->select('students.id,sk.name as skul,sk.bank_account,sk.bank_name,studying_mode,regno,concat(students.fname," ",students.lname) as name,concat(l.title," ",d.code," ",c.title) as class,c.id as class_id,d.title as dept_title')
			->join('class_records cr', 'cr.student=students.id')
			->join('classes c', 'c.id=cr.class')
			->join('departments d', 'd.id=c.department')
			->join('levels l', 'l.id=c.level')
			->join('schools sk', 'sk.id=students.school_id')
			->where('students.regno', $student)
			->get(1)->getRow();
		if ($student_info == null) {
			return $this->response->setStatusCode(200)->
			setJSON(['error' => 'Student not found', 'statusCode' => 404
				, 'message' => 'Error occurred: student with REG NO: ' . $student . ' not found']);
		}
		$level = $classMdl->select("classes.id,l.id as level_id, d.id as dept_id")
			->join("departments d", "d.id=classes.department")
			->join("levels l", "l.id=classes.level")
			->where("classes.id", $student_info->class_id)
			->get()->getRowArray();
		$schoolfees = $sfMdl->select("school_fees.id as feesId,(school_fees.amount+coalesce(fd.amount,0)-coalesce(sum(fr.amount),0)) as amount,0 as feesType,'single' as term")
			->join("(select sum(amount) as amount,feesId from school_fees_discount where student='{$student_info->id}' group by feesId) fd", "fd.feesId=school_fees.id", "LEFT")
			->join("fees_records fr", "fr.fees_id=school_fees.id and fr.student_id='{$student_info->id}' and fr.fees_type=0", "LEFT")
			->where("school_fees.level", $level['level_id'])
			->where("school_fees.department", $level['dept_id'])
			->where("school_fees.academic_year", $year)
			->having("amount>", 0)
			->where("school_fees.term", $term)
			->get()->getRow();
		$schoolfees = $schoolfees == null ? array() : $schoolfees;
		$extrafeesM = new ExtraFeesModel();
		$extrafees = $extrafeesM->select("extra_fees.id as feesId,extra_fees.title,1 as feesType,(extra_fees.amount-coalesce(sum(fr.amount),0)) as amount")
			->join("fees_records fr", "fr.fees_id=extra_fees.id and fr.student_id='{$student_info->id}' and fr.fees_type=1", "LEFT")
			->where("extra_fees.class_id", $student_info->class_id)
			->having("amount>", 0)
			->where("find_in_set($term,extra_fees.term)>0")
			->get()->getResultArray();

		$data = array("name" => $student_info->name, "studentId" => $student_info->id, "class" => $student_info->class, "department" => $student_info->dept_title,
			'schoolName' => $student_info->skul,
			'bank' => array("name" => $student_info->bank_name, "account" => $student_info->bank_account), 'schoolFees' => $schoolfees, 'extraFees' => $extrafees, 'statusCode' => 200);
//		echo '<pre>';var_dump($data);
		return $this->response->setJSON($data);
	}

	public function getFeesRecords($student, $class, $school_id)
	{
		$this->_secure();
		$schoolFees = new SchoolFeesModel();;
		$classMdl = new ClassesModel();
		$sMdl = new SchoolModel();
//		$yearData = $sMdl->select('at.academic_year')
//			->join('active_term at','at.id = schools.active_term')
//			->where('schools.id',$school_id)
//			->get(1)->getRow();
		$clMdl = new ClassRecordModel();
		$classYear = $clMdl->select('year')->where('student', $student)
			->orderBy('id', 'desc')
			->get(1)->getRow();
		$level = $classMdl->select("classes.id,l.id as level_id, d.id as dept_id")
			->join("departments d", "d.id=classes.department")
			->join("levels l", "l.id=classes.level")
			->where("classes.school_id", $school_id)
			->where("classes.id", $class)
			->get()->getRowArray();
		$schoolfrees = $schoolFees->select("school_fees.id,'School fees' as title,0 as type,(school_fees.amount+coalesce(fd.amount,0)) as amount ,coalesce(sum(fr.amount),0) as paid, fr.due_date,school_fees.term")
			->join("(select sum(amount) as amount,feesId from school_fees_discount where student=$student group by feesId) fd", "fd.feesId=school_fees.id", "LEFT")
			->join("fees_records fr", "fr.fees_id=school_fees.id and fr.student_id=$student and fr.fees_type=2", "LEFT")
			->where("school_fees.level", $level['level_id'])
			->where("school_fees.department", $level['dept_id'])
			->where("school_fees.academic_year", $classYear->year)
			->where("school_fees.school_id", $school_id)
			->groupBy("school_fees.id")
			->get()->getResultArray();

		$extraFees = new ExtraFeesModel();
		$extraFeesx = $extraFees->select("extra_fees.id,extra_fees.title,1 as type,extra_fees.amount
		,coalesce(sum(fr.amount),0) as paid,fr.due_date,extra_fees.term")
			->join("fees_records fr", "extra_fees.id=fr.fees_id and fr.student_id=$student and fr.fees_type=1", "LEFT")
			->where("((extra_fees.type_id=$class AND extra_fees.type=0) or (extra_fees.type_id=$student AND extra_fees.type=1))")
			->where("extra_fees.academic_year", $classYear->year)
			->groupBy("extra_fees.id")
			->get()->getResultArray();
		$dt = array_merge_recursive($schoolfrees, $extraFeesx);
		usort($dt, function ($a, $b) {
			return $a['term'] <=> $b['term'];
		});
		$data = ['fees' => $dt];
		return $this->response->setJSON($data);
	}

	public function manipulate_fee_entry()
	{
		$input = json_decode(file_get_contents('php://input'));
		if (!isset($input->studentId) || !isset($input->refNo) || !isset($input->payments)
			|| $this->request->getHeader("x-reference-id") == null || $this->request->getHeader("X-Api-Key") == null
			|| $this->request->getMethod() != 'post') {
			return $this->response->setJSON(['error' => 'Invalid request', 'statusCode' => 400
				, 'message' => 'Error occurred: please provide all required data']);
		}
		if (!$this->authenticate_api($API_res)) {
			return $this->response->setJSON(['error' => 'Authentication error', 'statusCode' => 403
				, 'message' => 'Error occurred: ' . $API_res]);
		}
		$student = $input->studentId;
		$ref_no = $input->refNo;//bank reference no
		$payments = $input->payments;//payment items #array
		if (strlen($ref_no) == 0) {
			return $this->response->setJSON(['error' => 'Reference no error', 'statusCode' => 400
				, 'message' => 'Error occurred: Reference can not be empty']);
		}
		if (!is_array($payments)) {
			return $this->response->setJSON(['error' => 'Invalid request', 'statusCode' => 400
				, 'message' => 'Error occurred: payments must contains iterable items']);
		} elseif (count($payments) == 0) {
			return $this->response->setJSON(['error' => 'Invalid request', 'statusCode' => 400
				, 'message' => 'Error occurred: payments has no items']);
		}
		//verify student
		$stMdl = new StudentModel();
		$st = $stMdl->select('id')->where('id', $student)->get(1)->getRow();
		if ($st == null) {
			return $this->response->setJSON(['error' => 'Student not found', 'statusCode' => 404
				, 'message' => 'Error occurred: student with id:' . $student . ' not found']);
		}
		$feeEntryModel = new FeesRecordModel();
		$ExtraFeeMdl = new ExtraFeesModel();
		$skulFeeMdl = new SchoolFeesModel();
		$errorMsgs = array();
		$countAll = 0;
		$countSuccess = 0;
		foreach ($payments as $payment) {
			$countAll++;
			//verify if fees exists
			$feeMdl = $ExtraFeeMdl;//extra fees mdl as default
			if ($payment->feesType == 0) {
				//school fees
				$feeMdl = $skulFeeMdl;
			}
			$sr = $feeMdl->select('id')->where('id', $payment->feesId)->get(1)->getRow();
			if ($sr == null) {
				$errorMsgs[] = "Fees id not found (feesId:{$payment->feesId},feesType:{$payment->feesType})";
				continue;
			}
			if (!@preg_match('/^[0-9]+(\.[0-9]{1,2})?$/', $payment->amount)) {
				$errorMsgs[] = "Fees Amount not valid #{$payment->amount} (feesId:{$payment->feesId},feesType:{$payment->feesType})";
				continue;
			}
			//check if payment already exists
			$pr = $feeEntryModel->select('id')->where('fees_id', $payment->feesId)->where('refNo', $ref_no)->get(1)->getRow();
			if ($pr != null) {
				$errorMsgs[] = "This transaction already exists (feesId:{$payment->feesId},refNo:{$ref_no})";
				continue;
			}
			$data = array(
				"student_id" => $student,
				"fees_type" => $payment->feesType,
				"amount" => $payment->amount,
				"fees_id" => $payment->feesId,
				"apiId" => $API_res,
				"refNo" => $ref_no,
				"created_by" => 0);
			try {
				$feeEntryModel->save($data);
				$countSuccess++;
			} catch (\Exception $e) {
				$errorMsgs[] = "Payment record not saved (feesId:{$payment->feesId},refNo:{$ref_no})";
			}
		}
		if (count($errorMsgs) == 0) {
			//all is fine
			return $this->response->setJSON(array('statusCode' => 200, 'refNo' => $ref_no, 'studentId' => $student, 'message' => ''));
		} elseif ($countSuccess == 0) {
			//all failed
			return $this->response->setJSON(array('statusCode' => 400, 'error' => 'save payments failed'
			, 'message' => implode('\n', $errorMsgs)));
		} else {
			return $this->response->setJSON(array('statusCode' => 400, 'error' => 'some payments failed '
				. ($countAll - $countSuccess) . ' over ' . $countAll, 'message' => implode('\n', $errorMsgs)));
		}
	}

	public function upload_data($option)
	{
//		$records = '[{"timee":"1580403179","id":21,"user_type":"1","user_id":"36"},{"timee":"1580403184","id":22,"user_type":"1","user_id":"21"},{"timee":"1580405180","id":23,"user_type":"1","user_id":"21"},{"timee":"1580405189","id":24,"user_type":"1","user_id":"21"},{"timee":"1580405191","id":25,"user_type":"1","user_id":"21"},{"timee":"1580405196","id":26,"user_type":"1","user_id":"21"},{"timee":"1580405198","id":27,"user_type":"1","user_id":"36"},{"timee":"1580405203","id":28,"user_type":"1","user_id":"36"},{"timee":"1580405206","id":29,"user_type":"1","user_id":"21"}]';
		$records = $this->request->getPost("records");
		$school_id = $this->request->getPost("school");
		$this->_preset($school_id);
//		$school_id = 3;
		$records = json_decode($records, true);
		switch ($option) {
			case "records":
				$atrMdl = new AttendanceRecordsModel();
				$last_id = 0;
				foreach ($records as $item) {
					try {
						$staff_id = $item['user_id'];
						$timee = $item['timee'];
						$rec = $atrMdl->select("id,time_in")->where("user_id", $staff_id)->where("user_type", $item['user_type'])
							->where("school_id", $school_id)->where("date_format(from_unixtime(time_in),'%Y-%m-%d')", date("Y-m-d", $timee))
							->get(1)->getRow();
						if ($rec == null) {
							//in
							$atrMdl->save(array("user_id" => $staff_id, "user_type" => $item['user_type']
							, "time_in" => $timee, "school_id" => $school_id));
						} else {
							//out, check if difference is greater than 30 min, then record out
							if (($rec->time_in + (30 * 60)) < $timee) {
								$atrMdl->save(array("id" => $rec->id, "time_out" => $timee));
							}
						}
						$last_id = $item['id'];
					} catch (\Exception $e) {
						return $this->response->setJSON(array("error" => lang("app.failedSaveRecords") . $e->getMessage()));
					}
				}
				$data['success'] = "1";
				$data['last_id'] = $last_id;
				return $this->response->setJSON($data);
				break;
			case "marks":
				$mMdl = new MarksModel();
				$last_id = 0;
				foreach ($records as $info) {
					try {
						$mMdl->save([
							'student_id' => $info['student_id'],
							'term' => $this->data['active_term'],
							'examDate' => strtotime($info['examDate']),
							'course_id' => $info['course_id'],
							'class_id' => $info['class_id'],
							'mark_type' => $info['mark_type'],
							'marks' => $info['marks'],
							'outof' => $info['out_of'],
							'cat_type' => $info['cat_type'],
							'period' => $info['period'],
							'created_by' => $info['created_by'],
						]);
						$last_id = $info['id'];
					} catch (\Exception $e) {
						if ($e->getCode() == 1062) {
							//marks already exists
							$last_id = $info['id'];
							log_message('critical', 'Marks_exists: ' . json_encode($info));
							continue;
						}
						return $this->response->setJSON(array("error" => lang("app.failedSaveRecords") . $e->getMessage()));
					}
				}
				$data['success'] = "1";
				$data['last_id'] = $last_id;
				return $this->response->setJSON($data);
				break;
			case "discipline":
				$dMdl = new DisciplineModel();
				$last_id = 0;
				foreach ($records as $info) {
					try {
						$dMdl->save([
							'type' => $info['type'],
							'active_term' => $this->data['active_term'],
							'student_id' => $info['student_id'],
							'notify_parent' => $info['notify_parent'],
							'comment' => $info['comment'],
							'school_id' => $info['school_id'],
							'created_by' => $info['operator'],
							'marks' => $info['marks'],
						]);
						if ($info['notify_parent'] == 1) {
							//send sms
							$st_data = $this->_get_parent_phone($info['student_id']);
							$phone = $st_data['phone'];
							if (strlen($phone) > 3) {
								$msg = $this->get_discipline_msg($st_data['name'], $info['marks'], $info['comment']);
//								if ($this->_send_sms($phone, $msg, $result, $this->data['remaining_sms'], $this->data['school_acronym'])) {
//									//save sent sms
//									$sms_count = (int)ceil(strlen($msg) / PER_SMS);
//									$this->data['remaining_sms'] = $this->data['remaining_sms'] - 1;//prevent exceeding sms limit
//									$this->_save_sms($this->data['active_term'], $phone, $msg, $info['type'], $school_id, "Discipline", $info['student_id'], $sms_count);
//								} else {
//									$this->_save_sms($this->data['active_term'], $phone, $msg, $info['type'], $school_id, "Discipline", $info['student_id'], 0, $result);
//								}
                                
                                if ($this->sendSMS($phone, $msg, $result)) {
                                    //save sent sms
                                    $sms_count = (int)ceil(strlen($msg) / PER_SMS);
                                    $this->data['remaining_sms'] = $this->data['remaining_sms'] - 1;//prevent exceeding sms limit
                                    $this->_save_sms($this->data['active_term'], $phone, $msg, $info['type'], $school_id, "Discipline", $info['student_id'], $sms_count);
                                } else {
                                    $this->_save_sms($this->data['active_term'], $phone, $msg, $info['type'], $school_id, "Discipline", $info['student_id'], 0, $result);
                                }
							}
						}
						$last_id = $info['id'];
					} catch (\Exception $e) {
						if ($e->getCode() == 1062) {
							//marks already exists
							$last_id = $info['id'];
							log_message('critical', 'Marks_exists: ' . json_encode($info));
							continue;
						}
						return $this->response->setJSON(array("error" => lang("app.failedSaveRecords") . $e->getMessage()));
					}
				}
				$data['success'] = "1";
				$data['last_id'] = $last_id;
				return $this->response->setJSON($data);
				break;
		}
		return $this->response->setJSON(array("error" => "0"));
	}

	public function take_daily_attendance()
	{
//		$records = '[{"timee":"1580403179","id":21,"user_type":"1","user_id":"36"},{"timee":"1580403184","id":22,"user_type":"1","user_id":"21"},{"timee":"1580405180","id":23,"user_type":"1","user_id":"21"},{"timee":"1580405189","id":24,"user_type":"1","user_id":"21"},{"timee":"1580405191","id":25,"user_type":"1","user_id":"21"},{"timee":"1580405196","id":26,"user_type":"1","user_id":"21"},{"timee":"1580405198","id":27,"user_type":"1","user_id":"36"},{"timee":"1580405203","id":28,"user_type":"1","user_id":"36"},{"timee":"1580405206","id":29,"user_type":"1","user_id":"21"}]';
        
		$card = $this->request->getPost("card");
		$school_id = $this->request->getPost("school");
		$term = $this->request->getPost("term");
        $this->_preset($school_id);
		$stMdl = new StudentModel();
		$student = $stMdl->get_student_simple2(array("card" => $card), $school_id, true);
		if ($student == null) {
			//student not found
			return $this->response->setJSON(array("error" => lang("app.noStudentFound")));
		}
		$mdl = new DailyAttendanceModel();
		try {
			$mdl->save(array("student_id" => $student['id'], "datee" => date("Y-m-d")
			, "active_term" => $term));
			return $this->response->setJSON(array("success" => "1", "name" => $student['name']
			, "regno" => $student['regno'], "class" => $student['class'], "photo" => $student['photo']));
		} catch (\Exception $e) {
			if ($e->getCode() == 1062) {
				return $this->response->setJSON(array("error" => lang("app.studentAlreadyAttendedToday")));
			}
			return $this->response->setJSON(array("error" => lang("app.failedSaveRecords") . $e->getMessage()));
		}
	}

	public function search_student($query, $type, $school_id)
	{
		$StudentModel = new StudentModel();
		$students = $StudentModel->search_student_api($query, $school_id, $type);
		if (is_array($students) && count($students) == 0)
			return $this->response->setJSON(array("error" => lang("app.noStudentsFound")));
		if ($type == 1 && $students['id'] == null) {
			return $this->response->setJSON(array("error" => lang("app.noStudentsFound")));
		}
		if ($type == 0 && $students[0]['id'] == null) {
			return $this->response->setJSON(array("error" => lang("app.noStudentsFound")));
		}
		if ($type == 1)
			return $this->response->setJSON($students);
		$data = array();
		foreach ($students as $item) {
			$data['students'][] = $item;
		}
		return $this->response->setJSON($data);
	}
public function get_boarding_classes()
{
    $teacher    = $this->request->getPost("teacher") ?? 0;
    $school_id  = (int) $this->request->getPost("school");
    $study_mode = $this->request->getPost("study_mode"); // 0=Boarding, 1=Day, optional
    $sex        = strtoupper($this->request->getPost("sex") ?? "ALL"); // M/F/ALL
    $class_id   = $this->request->getPost("class"); // optional

    // Normalize teacher param
    $teacher = ($teacher == 0) ? null : $teacher;

    // Load classes
    $csMdl   = new ClassesModel();
    $classes = $csMdl->get_teacher_classes($teacher, $school_id);

    // Ensure we have an active term for this school
    $schoolMdl = new SchoolModel();
    $active_term = $schoolMdl->select("schools.active_term, at.use_period, at.academic_year, at.term")
        ->join("active_term at", "CAST(schools.active_term AS UNSIGNED) = at.id", "left")
        ->where("schools.id", $school_id)
        ->get()
        ->getResultArray();

    if (empty($active_term)) {
        return $this->response->setJSON([
            "error" => lang("app.activeTermNotSet")
        ]);
    }

    if (empty($classes)) {
        return $this->response->setJSON([
            "error" => lang("app.noClassFound")
        ]);
    }

    $data = [];
    $data["active_term"] = $active_term[0]; // only one row expected
    $term = $data["active_term"]["term"];

    $data["classes"] = [];

    foreach ($classes as $item) {
        // If a specific class is requested, skip others
        if (!empty($class_id) && $item["id"] != $class_id) {
            continue;
        }

        // Fetch students
        $stMdl = new StudentModel();
        $students = $stMdl->select("
                students.id,
                students.regno,
                CONCAT(students.fname, ' ', students.lname) as name,
                students.photo,
                students.sex as gender,
                students.studying_mode as mode,
                students.card as card_id
            ")
            ->join("class_records cr", "cr.student = students.id")
            ->where("cr.class", $item["id"])
            ->where("students.status", 1)
            ->where("cr.year", $data["active_term"]["academic_year"]);

        // Apply filters
        if ($study_mode !== null && $study_mode !== '') {
            $students->where("students.studying_mode", $study_mode);
        }
        if ($sex !== "ALL") {
            $students->where("students.sex", $sex);
        }

        $item["students"] = $students->get()->getResultArray();

        $data["classes"][] = $item;
    }

    return $this->response->setJSON($data);
}


	public function check_permission($student_id)
	{
		$permMdl = new PermissionModel();
		$data = $permMdl->select("permission.id as permission_id,permission.destination,permission.reason,permission.leave_time,permission.return_time,concat(sf.fname,' ',sf.lname) as operator,concat(st.fname,' ',st.lname) as name
		,sk.phone as school_phone,sk.email as school_email,at.term")
			->join("students st", "st.id = permission.student_id")
			->join("schools sk", "sk.id = st.school_id")
			->join("active_term at", "at.id = permission.active_term")
			->join("staffs sf", "sf.id = permission.created_by")
			->where("permission.status", "0")
			->where("permission.student_id", $student_id)
			->get()->getRowArray();
		if ($data == null)
			return $this->response->setJSON(array("error" => "0"));
		return $this->response->setJSON($data);
	}

	public function get_years($school_id)
	{
		$acMdl = new AcademicYearModel();
		$years = $acMdl->select('id,title')->where("school_id", $school_id)
			->orderBy("id", 'DESC')->get()->getResultArray();
		if (count($years) > 0) {
			$data = array();
			foreach ($years as $item) {
				$data['years'][] = $item;
			}
			return $this->response->setJSON($data);
		}
		return $this->response->setStatusCode(404)->setJSON(array("message" => "No academic year found"));
	}

	public function payment_check($student_id, $term, $school_id, $year)
	{
		$schoolFees = new SchoolFeesModel();
		$extraFees = new ExtraFeesModel();
		$classMdl = new ClassesModel();
		$classRMdl = new ClassRecordModel();
		$class_data = $classRMdl->select("class,st.transport_money")
			->join("students st", "st.id = class_records.student")
			->where("student", $student_id)
			->where("year", $year)
			->get(1)->getRow();
		if ($class_data == null) {
			//class not found
			return $this->response->setJSON(array("message" => lang("app.ClassFoundInvalid")));
		}
		$class = $class_data->class;
		$extraFeesx = $extraFees->select("coalesce(sum(extra_fees.amount),0) as extra_amount,coalesce(sum(fr.amount),0) as paidextra")
			->join("(select fr.student_id,fr.fees_id,COALESCE(sum(fr.amount),0) as amount from fees_records fr where fr.fees_type=1 and fr.status=1 and fr.student_id=$student_id group by fr.student_id) fr", "extra_fees.id=fr.fees_id", "LEFT")
			->where("((extra_fees.type_id=$class AND extra_fees.type=0) or (extra_fees.type_id=$student_id AND extra_fees.type=1))")
			->where("extra_fees.academic_year", $year)
			->where("extra_fees.term", $term)
//			->groupBy("extra_fees.type_id")
//			->where("find_in_set($term,extra_fees.term) >0")
			->get()->getRowArray();
		$level = $classMdl->select("classes.id,l.id as level_id, d.id as dept_id")
			->join("departments d", "d.id=classes.department")
			->join("levels l", "l.id=classes.level")
			->where("classes.school_id", $school_id)
			->where("classes.id", $class)
			->get()->getRowArray();
		$schoolfrees = $schoolFees->select("(school_fees.amount+coalesce(fd.amount,0)) as skl_amount ,coalesce(sum(fr.amount),0) as paidschoolfees")
			->join("(select sum(amount) as amount,feesId from school_fees_discount where student=$student_id group by feesId) fd", "fd.feesId=school_fees.id", "LEFT")
			->join("fees_records fr", "fr.fees_id=school_fees.id and fr.student_id=$student_id and fr.fees_type=0 and fr.status=1", "LEFT")
			->where("school_fees.level", $level['level_id'])
			->where("school_fees.department", $level['dept_id'])
			->where("school_fees.academic_year", $year)
			->where("school_fees.term", $term)
			->where("school_fees.school_id", $school_id)
			->groupBy("school_fees.academic_year")
			->groupBy("school_fees.term")
			->get()->getRowArray();
		if ($schoolfrees == null)
			$schoolfrees = array("skl_amount" => "0", "paidschoolfees" => "0");
		$schoolfrees['transport_money'] = $class_data->transport_money;
		$data = array_merge($extraFeesx, $schoolfrees);
		$data['success'] = 1;
		return $this->response->setJSON($data);
	}

	public function save_course_attendance()
	{
		$teacher = $this->request->getPost("teacher");
		$course = $this->request->getPost("course");
		$class = $this->request->getPost("class");
		$students = $this->request->getPost("students");
		$records = json_decode($students, true);
		$atrMdl = new CourseAttendanceRecordsModel();
		$atMdl = new CourseAttendanceModel();
		try {
			$attendance = $atMdl->insert(array("teacher_id" => $teacher, "course_id" => $course
			, "class_id" => $class));
			foreach ($records as $item) {
				$atrMdl->save(array("attendance_id" => $attendance, "student_id" => $item));
			}
		} catch (\Exception $e) {
			return $this->response->setJSON(array("error" => lang("app.failedSaveRecords") . $e->getMessage()));
		}
		$data['success'] = "1";
		return $this->response->setJSON($data);
	}

	public function save_class_attendance()
	{
		$teacher = $this->request->getPost("teacher");
		$class = $this->request->getPost("class");
		$term = $this->request->getPost("term");
		$students = $this->request->getPost("students");
		$records = json_decode($students, true);
		$atMdl = new DailyAttendanceModel();
		try {
			foreach ($records as $item) {
				$atMdl->save(array("datee" => date('Y-m-d'), "student_id" => $item, 'active_term' => $term));
			}
		} catch (\Exception $e) {
			if ($e->getCode() == 1062) {
				return $this->response->setJSON(array("error" => "Student already attended"));
			}
			return $this->response->setJSON(array("error" => lang("app.failedSaveRecords") . $e->getMessage()));
		}
		$data['success'] = "1";
		return $this->response->setJSON($data);
	}

	public function get_leave($school_id, $user_id)
	{
		$csMdl = new LeaveModel();
		$leaves = $csMdl->select('leaves.id,leaves.type,leaves.days,leaves.status,leaves.reason,leaves.requested_by,leaves.fromDate,leaves.toDate,leaves.address')
			->join('staffs s', 's.id=leaves.requested_by')
			->where('s.school_id', $school_id)
			->where('leaves.requested_by', $user_id)
			->orderBy("leaves.id", "DESC")
			->get()->getResultArray();
		if (count($leaves) > 0) {
			$data = array();
			foreach ($leaves as $item) {
				$data['leaves'][] = $item;
			}
			return $this->response->setJSON($data);
		}
		return $this->response->setJSON(array("error" => lang("app.noLeaveApplicationFound")));
	}

	public function get_book($school_id, $query)
	{
		$bookModel = new BookModel();
		$books = $bookModel->select("books.id,books.title,books.author,books.quantity,books.status,c.id AS category")
			->join("bookcategory c", "c.id=books.category", "LEFT")
			->where("books.school_id=$school_id AND (books.title like '%$query%' OR books.author like '%$query%')")
			->get()->getResultArray();
		if (count($books) > 0) {
			$data = array();
			foreach ($books as $item) {
				$data['books'][] = $item;
			}
			return $this->response->setJSON($data);
		}
		return $this->response->setJSON(array("error" => lang("app.noBooksFound")));

	}

	public function save_leave()
	{
		$school_id = $this->request->getPost("school_id");
		$this->_preset($school_id);
		$lvModel = new LeaveModel();
		$address = $this->request->getPost("address");
		$days = $this->request->getPost("days");
		$comment = $this->request->getPost("reason");
		$types = $this->request->getPost("type");
		$fromDate = strtotime($this->request->getPost("fromDate"));
		$toDate = strtotime($this->request->getPost("toDate"));
		$created_by = $this->request->getPost("requested_by");

		if (strlen($fromDate) < 5) {
			//invalid date
			return $this->response->setJSON(array("error" => lang("app.fromDateInvalid")));
		}

		if (strlen($toDate) < 5) {
			//invalid date
			return $this->response->setJSON(array("error" => lang("app.tillDateInvalid")));
		}

		//check date difference if match with requested days
		$diff = get_days_difference($this->request->getPost("fromDate"), $this->request->getPost("toDate"));
		if ($days != $diff) {
			return $this->response->setJSON(array("error" => lang("app.daysNotMatch")));
		}
		$data = array(
			"school_id" => $school_id,
			"type" => $types,
			"reason" => $comment,
			"days" => $days,
			"requested_by" => $created_by,
			"fromDate" => $fromDate,
			"toDate" => $toDate,
			"address" => $address,
			"status" => 0);
		try {
			$lvModel->save($data);
			return $this->response->setJSON(array("success" => lang("app.requestSentSuccessfully")));
		} catch (\Exception $e) {
			return $this->response->setJSON(array("error" => lang("app.OopsAction")));
		}
	}

	public function save_borrow_book()
	{
		$school_id = $this->request->getPost("school_id");
		$this->_preset($school_id);
		$bookModel = new BookRecordModel();
		$book_id = $this->request->getPost("book_id");
		$student_id = $this->request->getPost("student_id");
		$borrow_date = strtotime($this->request->getPost("borrow_date"));
		$return_due_date = strtotime($this->request->getPost("return_due_date"));
		$created_by = $this->request->getPost("created_by");

		if (strlen($borrow_date) < 5) {
			//invalid date
			return $this->response->setJSON(array("error" => lang("app.fromDateInvalid")));
		}

		if (strlen($return_due_date) < 5) {
			//invalid date
			return $this->response->setJSON(array("error" => lang("app.tillDateInvalid")));
		}
		if ($return_due_date < $borrow_date) {
			//invalid date
			return $this->response->setJSON(array("error" => lang("app.invaliDatesRange")));
		}
		if ($borrow_date > strtotime(date("Y-m-d"))) {
			return $this->response->setJSON(array("error" => lang("app.youCotInFuture")));
		}
		$books = $bookModel->select("book_records.book_id,book_records.return_due_date,book_records.status")
			->where("book_records.school_id", $school_id)
			->where("book_records.student_id", $student_id)
			->where("book_records.status", 0)
			->get()->getResultArray();

		//check if he has no returned the same book
		foreach ($books as $book) {
			if ($book['book_id'] == $book_id) {
				return $this->response->setJSON(array("error" => lang("app.bookStillYourHands")));
			}
		}

		//check if there pending delayed book
		foreach ($books as $book) {
			if ($book['return_due_date'] < time()) {
				return $this->response->setJSON(array("error" => lang("app.penalty")));
			}
		}
		$data = array(
			"book_id" => $book_id,
			"school_id" => $school_id,
			"student_id" => $student_id,
			"academic_year" => $this->data['academic_year'],
			"term" => $this->data['term'],
			"borrow_date" => $borrow_date,
			"return_due_date" => $return_due_date,
			"return_date" => 0,
			"status" => 0,
			"created_by" => $created_by);
		try {
			$bookModel->save($data);
			return $this->response->setJSON(array("success" => lang("app.bookBrrowsaved")));
		} catch (\Exception $e) {
			return $this->response->setJSON(array("error" => lang("app.OopsAction")));
		}
	}

	public function save_return_book()
	{
		$bookModel = new BookRecordModel();
		$record_id = $this->request->getPost("record_id");
		if (strlen($record_id) == 0) {
			//invalid date
			return $this->response->setJSON(array("error" => lang("app.InvaliDataFound")));
		}

		$data = array(
			"id" => $record_id,
			"status" => 1,
			"return_date" => time());
		try {
			$bookModel->save($data);
			return $this->response->setJSON(array("success" => lang("app.bookReturnedSuccessfully")));
		} catch (\Exception $e) {
			return $this->response->setJSON(array("error" => lang("app.OopsAction")));
		}
	}

	public function get_library($school_id, $student_id)
	{
		$csMdl = new BookRecordModel();

		$leaves = $csMdl->select('book_records.id,book_records.book_id,book_records.typeId as student_id,book_records.academic_year,book_records.term,book_records.borrow_date,book_records.return_due_date,book_records.return_date,book_records.status,b.title,b.author')
			->join('students s', 's.id=book_records.typeId and type=1')
			->join('books b', 'b.id=book_records.book_id')
			->where('s.school_id', $school_id)
			->where('book_records.student_id', $student_id)
			->orderBy("book_records.status", "ASC")
			->orderBy("book_records.id", "DESC")
			->get()->getResultArray();
		if (count($leaves) > 0) {
			$data = array();
			foreach ($leaves as $item) {
				$data['leaves'][] = $item;
			}
			return $this->response->setJSON($data);
		}
		return $this->response->setJSON(array("error" => lang("app.notBorrowBook")));
	}

	public function save_discipline()
	{
		$school_id = $this->request->getPost("school_id");
		$this->_preset($school_id);
		$DisciplineModel = new DisciplineModel();
		$notify = $this->request->getPost("notify_parent");
		$marks = $this->request->getPost("marks");
		$comment = $this->request->getPost("reason");
		$types = $this->request->getPost("type");
		$active = $this->data['active_term'];
		$created_by = $this->request->getPost("operator");
		$student_id = $this->request->getPost("student_id");
		if ($types == 0) {
			//behavior, force remove marks and notify
			$notify = 0;
			$marks = 0;
		}
		if (strlen($student_id) == 0) {
			//no student selected
			return $this->response->setJSON(array("error" => lang("app.pleaseadStudent")));
		}
		$data = array(
			"student_id" => $student_id,
			"school_id" => $school_id,
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
				$st_data = $this->_get_parent_phone($student_id);
				$phone = $st_data['phone'];
				if (strlen($phone) > 3) {
					$msg = $this->get_discipline_msg($st_data['name'], $marks, $comment);
//					if ($this->_send_sms($phone, $msg, $result, $this->data['remaining_sms'], $this->data['school_acronym'])) {
//						//save sent sms
//						$sms_count = (int)ceil(strlen($msg) / PER_SMS);
//						$this->data['remaining_sms'] = $this->data['remaining_sms'] - 1;//prevent exceeding sms limit
//						$this->_save_sms($active, $phone, $msg, $types, $school_id, "Discipline", $student_id, $sms_count);
//					} else {
//						$this->_save_sms($active, $phone, $msg, $types, $school_id, "Discipline", $student_id, 0, $result);
//					}
                    
                    if ($this->sendSMS($phone, $msg, $result)) {
                        //save sent sms
                        $sms_count = (int)ceil(strlen($msg) / PER_SMS);
                        $this->data['remaining_sms'] = $this->data['remaining_sms'] - 1;//prevent exceeding sms limit
                        $this->_save_sms($active, $phone, $msg, $types, $school_id, "Discipline", $student_id, $sms_count);
                    } else {
                        $this->_save_sms($active, $phone, $msg, $types, $school_id, "Discipline", $student_id, 0, $result);
                    }
				}
			}
			return $this->response->setJSON(array("success" => lang("app.entrySavedSuccessfully")));
		} catch (\Exception $e) {
			return $this->response->setJSON(array("error" => lang("app.OopsAction")));
		}
	}

	public function save_permission()
	{
		$school_id = $this->request->getPost("school_id");
		$this->_preset($school_id);
		$permMdl = new PermissionModel();
		$notify = $this->request->getPost("notify_parent");
		$destination = $this->request->getPost("destination");
		$comment = $this->request->getPost("reason");
		$leave = $this->request->getPost("leave");
		$return = $this->request->getPost("return");
		$active = $this->data['active_term'];
		$created_by = $this->request->getPost("operator");
		$student_id = $this->request->getPost("student_id");

		if (strlen($student_id) == 0) {
			//no student selected
			return $this->response->setJSON(array("error" => lang("app.pleaseadStudent")));
		}
		$data = array(
			"student_id" => $student_id,
			"destination" => $destination,
			"reason" => $comment,
			"leave_time" => $leave,
			"return_time" => $return,
			"active_term" => $active,
			"status" => 0,
			"notify_parent" => $notify,
			"created_by" => $created_by);
		try {
			$permMdl->save($data);
			if ($notify == 1) {
				//send sms
				$st_data = $this->_get_parent_phone($student_id);
				$phone = $st_data['phone'];
				if (strlen($phone) > 3) {
					$msg = $this->get_permisson_msg($st_data['name'], $destination, $comment);
//					if ($this->_send_sms($phone, $msg, $result, $this->data['remaining_sms'], $this->data['school_acronym'])) {
//						//save sent sms
//						$sms_count = (int)ceil(strlen($msg) / PER_SMS);
//						$this->data['remaining_sms'] = $this->data['remaining_sms'] - 1;//prevent exceeding sms limit
//						$this->_save_sms($active, $phone, $msg, "0", $school_id, "Permission", $student_id, $sms_count);
//					} else {
//						$this->_save_sms($active, $phone, $msg, "0", $school_id, "Permission", $student_id, 0, $result);
//					}
                    
                    if ($this->sendSMS($phone, $msg, $result)) {
                        //save sent sms
                        $sms_count = (int)ceil(strlen($msg) / PER_SMS);
                        $this->data['remaining_sms'] = $this->data['remaining_sms'] - 1;//prevent exceeding sms limit
                        $this->_save_sms($active, $phone, $msg, "0", $school_id, "Permission", $student_id, $sms_count);
                    } else {
                        $this->_save_sms($active, $phone, $msg, "0", $school_id, "Permission", $student_id, 0, $result);
                    }
				}
			}
			$schoolMdl = new SchoolModel();
			$skl = $schoolMdl->select("phone")->where("id", $school_id)->get()->getRow();
			return $this->response->setJSON(array("success" => lang("app.permissionSavedsuccessfully"), "school_phone" => $skl->phone));
		} catch (\Exception $e) {
			return $this->response->setJSON(array("error" => lang("app.OopsAction")));
		}
	}

	public function save_justification()
	{
		$permMdl = new PermissionModel();
		$comment = $this->request->getPost("comment");
		$created_by = $this->request->getPost("operator");
		$permission_id = $this->request->getPost("permission_id");

		if (strlen($permission_id) == 0) {
			//no permission provided
			return $this->response->setJSON(array("error" => lang("app.fatalErrorRestart")));
		}
		$data = array(
			"id" => $permission_id,
			"comment" => $comment,
			"status" => 1,
			"updated_by" => $created_by);
		try {
			$permMdl->save($data);
			return $this->response->setJSON(array("success" => lang("app.justificationSaved")));
		} catch (\Exception $e) {
			return $this->response->setJSON(array("error" => lang("app.OopsAction")));
		}
	}

	public function assign_card()
	{
		$stMdl = new StudentModel();
		$card = $this->request->getPost("card");
		$created_by = $this->request->getPost("operator");
		$student_id = $this->request->getPost("student_id");
		$school_id = $this->request->getPost("school_id");

		if (strlen($student_id) == 0) {
			//no student id provided
			return $this->response->setJSON(array("error" => lang("app.fatalErrorRestart")));
		}
		if ($school_id == 0) {
			//no school id provided
			return $this->response->setJSON(array("error" => lang("app.fatalErrorLogin")));
		}
		$uvMdl = new UpdateVersionModel();
		$update_v = 1;
		$update_v_data = $uvMdl->select("version")->where("type", "student")->where("school_id", $school_id)->get(1)->getRow();
		if ($update_v_data != null)
			$update_v = $update_v_data->version;
		$data = array(
			"card" => $card,
			"id" => $student_id,
			"updateVersion" => $update_v,
			"updated_by" => $created_by);
		try {
			//check if card is used
			$dt = $stMdl->select("id,concat(fname,' ',lname) as name")->where("card", $card)
				->where("school_id", $school_id)->get()->getRow();
			if ($dt != null) {
				return $this->response->setJSON(array("error" => lang("app.cardAlreadyAssigned") . $dt->name));
			}
			$stMdl->save($data);
			return $this->response->setJSON(array("success" => lang("app.assignedSuccessfully")));
		} catch (\Exception $e) {
			return $this->response->setJSON(array("error" => lang("app.OopsAction")));
		}
	}

	public function save_student_photo()
	{
		sleep(2);
		$stMdl = new StudentModel();
		$photo = $this->request->getPost("photo");
		$student_id = $this->request->getPost("student");
		if (strlen($student_id) == 0) {
			//no student id provided
			return $this->response->setJSON(array("error" => lang("app.fatalErrorRestart")));
		}
		$filename = uniqid() . ".jpg";
		$decoded = base64_decode($photo);
		if (file_put_contents(FCPATH . "assets/images/profile/" . $filename, $decoded) === false) {
			return $this->response->setJSON(array("error" => lang("app.ImagenotSaved")));
		}
		$data = array(
			"photo" => $filename,
			"id" => $student_id);
		try {
			//check if card is used
			$stMdl->save($data);
			return $this->response->setJSON(array("success" => $filename));
		} catch (\Exception $e) {
			return $this->response->setJSON(array("error" => lang("app.OopsAction")));
		}
	}

	private function _save_sms($term_id, $phone, $msg, $type, $school_id, $subject, $receiver_id, $sms_count, $fail = "")
	{
		$schoolMdl = new SchoolModel();
		$skl = $schoolMdl->select("schools.extra_sms,p.sms_limit,at.sms_usage")
			->join("packages p", "p.id=schools.package")
			->join("active_term at", "at.id=schools.active_term", "LEFT")
			->where("schools.id", $school_id)->get()->getRow();
//		if (($skl->sms_limit-$skl->sms_usage)<=0 && $skl->extra_sms>0){
		if ($skl->extra_sms > 0) {
			//decrement extra sms
			$schoolMdl->where("id", $school_id)->decrement("extra_sms", $sms_count);
		}
		$smsMdl = new SmsModel();
		$termMdl = new TermModel();
		$termMdl->incrementSMS($term_id, $sms_count);
		$id = $smsMdl->insert(array("school_id" => $school_id, "active_term" => $term_id,
			"content" => $msg, "subject" => $subject, "recipient_type" => $type));
		$smsRMdl = new SmsRecipientModel();
		$status = strlen($fail) > 3 ? 2 : 1;
		$smsRMdl->save(array("sms_record_id" => $id, "receiver_id" => $receiver_id,
			"phone" => $phone, "sent_on" => time(), "status" => $status, "fail_reason" => $fail));
	}

	public function test()
	{
		$st_data = $this->_get_parent_phone(17);
		echo $st_data['phone'];
	}

	public function _secure()
	{
		$auth = !isset(apache_request_headers()["Authorization"]) ? $this->request->getHeader("Authorization") : apache_request_headers()["Authorization"];
//		if ($auth==null) {
//			$this->response->setStatusCode(401)->setJSON(array("error" => "Access denied", "message" => "You don't have permission to access this resource."))->send();
//			exit();
//		}
//        $auth = $this->request->getHeader("Authorization");
		if ($auth == null || strlen($auth) < 5) {
			$this->response->setStatusCode(401)->setJSON(array("error" => "Access denied", "message" => "You don't have permission to access this resource."))->send();
			exit();
		} else if (strpos($auth, APP_API_KEY) === false) {
			//secure mobile app
			$this->response->setStatusCode(401)->setJSON(array("error" => "Invalid token", "message" => "Invalid authentication."))->send();
			exit();
		}
	}

	public function get_single_student($regno)
	{
		$StudentModel = new StudentModel();
		$student = $StudentModel->select("students.id,students.photo,students.regno,concat(students.fname,' ',students.lname) as name
		,concat(l.title,' ',c.title,' ',d.code) as classe,sk.id as schoolId,sk.name as school,c.id as classId")
			->join('class_records cr', 'cr.student=students.id')
			->join('classes c', 'c.id=cr.class')
			->join('departments d', 'd.id=c.department')
			->join('levels l', 'l.id=c.level')
			->join('faculty f', 'f.id=d.faculty_id')
			->join('schools sk', 'sk.id=students.school_id')
			->join('active_term at', 'at.id=sk.active_term')
			->where('students.status', "1")
			->where('lower(students.regno)', strtolower($regno))
			->get(1)->getRowArray();

		if ($student == null) {
			return $this->response->setStatusCode(404)->setJSON(array("message" => lang("app.noStudentFound")));
		}
		return $this->response->setJSON($student);
	}

	/**
	 * This function help to get pocket money transactions
	 * @param string $studentId
	 * @return Response
	 */
	public function getTransactions(string $studentId): Response
	{
		$this->_secure();
		$pMdl = new PaymentModel();

		$transactions = $pMdl->select('payment_transactions.id,payment_transactions.balance,payment_transactions.amount,payment_transactions.type,payment_transactions.source,payment_transactions.status,payment_transactions.created_at,st.wallet_balance')
			->join('students st', 'st.id=payment_transactions.student_id')
			->where('payment_transactions.student_id', $studentId)
			->where('payment_transactions.type !=', 4)
			->where('payment_transactions.status', 1)
			->orderBy("payment_transactions.id", "DESC")
			->get()->getResultArray();
		if (count($transactions) > 0) {
			$data = array();
			$a = 0;
			foreach ($transactions as $item) {
				if ($a == 0) {
					$data['walletBalance'] = $item['wallet_balance'];
				}
				if (!isset($data['latestTopUpDate']) && $item['type'] == 0) {
					$data['latestTopUpDate'] = $item['created_at'];
					$data['latestTopUpAmount'] = $item['amount'];
					$data['latestTopUpBalance'] = $item['balance'];
				}
				unset($item['wallet_balance']);
				$data['transactions'][] = $item;
				$a++;
			}
			return $this->response->setJSON($data);
		}
		return $this->response->setStatusCode(404)->setJSON(array("message" => "no transaction"));
	}

	public function topUpWallet(): ?Response
	{
		$this->_secure();
		$input = json_decode(file_get_contents('php://input'));
		if (strlen($input->token) < 10) {
			return $this->response->setStatusCode(400)->setJSON(array("message" => "Invalid Token"));
		}
		if (strlen($input->studentId) == 0) {
			return $this->response->setStatusCode(400)->setJSON(array("message" => "Invalid Student"));
		}
		if ($input->amount < 200) {
			return $this->response->setStatusCode(400)->setJSON(array("message" => "Invalid Amount, minimum is 200 RWF"));
		}
		$stMdl = new StudentModel();
		$student = $stMdl->select("students.id,students.fname,students.regno,students.wallet_balance,sk.mtn_momo_phone,sk.pocket_money_phone,sk.name as school_name,sk.bank_account")
			->join("schools sk", "students.school_id=sk.id")->where("students.id", $input->studentId)
			->get()->getRow();
		if ($student == null) {
			return $this->response->setStatusCode(400)->setJSON(array("message" => "Student not found, Invalid student ID"));
		}
		if (strlen($student->pocket_money_phone) < 8 && $input->type != 4) {
			return $this->response->setStatusCode(400)->setJSON(array("message" => "Pocket money system of " . $student->school_name . " is not active"));
		}
		if (strlen($student->mtn_momo_phone) < 8 && $input->type == 4) {
			return $this->response->setStatusCode(400)->setJSON(array("message" => "Payment information of " . $student->school_name . " is not active, contact school"));
		}

		$input->phone = substr($input->phone, 0, 3) == "250" ? $input->phone : "25" . $input->phone;
		$input->schoolPhone = $input->type == 4 ? $student->mtn_momo_phone : $student->pocket_money_phone;
		$input->schoolPhone = substr($student->mtn_momo_phone, 0, 3) == "250" ? $student->mtn_momo_phone : "25" . $student->mtn_momo_phone;
		$pMdl = new PaymentModel();
		$walletId = null;
		try {
			$input->somanetChargesAmount = 0;
			$extraOption = [
				"paymentMode" => "MTN MOMO",
				"token" => $input->token,
			];
			if ($input->type == 4) {
				$extraOption = [
					"paymentMode" => "MTN MOMO",
					"token" => $input->token,
					"fees" => $input->extra,
				];
				$input->somanetChargesAmount = 100;
			}
			$input->grandTotal = ($input->amount + $input->charges);
			$walletData = [
				"student_id" => $student->id,
				"amount" => $input->amount,
				"type" => $input->type,
				"source" => $input->phone,
				"balance" => ($input->type == 4 ? null : ($student->wallet_balance + $input->amount)),
				"txn_fee" => $input->charges,
				"status" => 0,
				"extra_options" => json_encode($extraOption),
			];
			$walletId = $pMdl->insert($walletData);
			$txId = ID_SUFFIX . strtoupper(substr(getenv("CI_ENVIRONMENT") ?? 'P', 0, 1)) . $walletId;
			$pMdl->save(['id' => $walletId, 'txn_Id' => $txId]);
			$input->walletId = $walletId;
			$ref_id = $this->topUpMOMO($txId, $input, $student);
			return $this->response->setStatusCode(202)->setJSON(array("message" => "pending confirmation", "ref_id" => $ref_id));
		} catch (\ReflectionException $e) {
			return $this->response->setStatusCode(500)->setJSON(array("message" => "System error, please try again later"));
		} catch (\Exception $e) {
			$errorMsg = trim(str_replace("Error:", "", $e->getMessage()));
			try {
				if ($walletId == null) {
					return $this->response->setStatusCode(500)->setJSON(array("message" => "System error, {$errorMsg}"));
				}
				$pMdl->save(['id' => $walletId, "status" => 2, "tx_error" => $errorMsg]);
			} catch (\ReflectionException $e) {
				return $this->response->setStatusCode(500)->setJSON(array("message" => "System error, please try again later"));
			}
			return $this->response->setStatusCode(500)->setJSON(array("message" => "Error: " . $errorMsg));
		}
	}

	public function updatePaymentStatus()
	{
		$input = json_decode(file_get_contents('php://input'));
		$pMdl = new PaymentModel();
		$stMdl = new StudentModel();
		$walletId = str_replace(ID_SUFFIX . strtoupper(substr(getenv("CI_ENVIRONMENT") ?? 'P', 0, 1)), "", $input->external_transaction_id);
		$wData = $pMdl->select('extra_options,coalesce(balance,0) as balance,student_id,amount,type,id')->where("id", $walletId)
			->get(1)->getRow();
		if ($wData == null) {
			return $this->response->setStatusCode(500)->setJSON(array("message" => "System error, invalid transaction"));
		}
		$token = json_decode($wData->extra_options);
		try {
			$status = 2;
			$error = $input->message;
			if ($input->status_code == 200) {
				$status = 1;
				$error = null;
				if ($wData->type == 4) {
					//update fee records
					$fRMdl = new FeesRecordModel();
					$extra = json_decode($wData->extra_options, true);
					foreach ($extra['fees'] as $fee) {
						$fRMdl->save(['student_id' => $wData->student_id, 'fees_id' => $fee['id'], 'fees_type' => $fee['type'],
							'amount' => $fee['amount'], 'created_by' => $walletId, 'refNo' => $input->momo_ref_number]);
					}
				}
			}
			$pMdl->save(['id' => $walletId, "status" => $status, "reference_id" => $input->momo_ref_number, "tx_error" => $error]);
			if ($wData->type != 4) {
				$stMdl->where('id', $wData->student_id)->increment('wallet_balance', $wData->amount);
			}
			//notify device
			$this->sendNotificationMessage($token->token,
				["message" => ($wData->type == 4 ? "Payment completed" : "Top up completed"), "balance" => $wData->balance,
					"ref_id" => $input->momo_ref_number],
				["title" => ($wData->type == 4 ? "Payment completed" : "Wallet top up done"),
					"message" => ($wData->type == 4 ? "Hello, your School fees payment is completed" : "Hello, your top up is completed")]);
			//trigger process pending transfer on background
//			$param = base_url("processPendingBprTransfer");
//			$command = "curl $param > /dev/null &";
//			exec($command);
		} catch (\ReflectionException|\Exception $e) {
			return $this->response->setStatusCode(500)->setJSON(array("message" => "System error, please try again later"));
		}
	}

	public function walletTransaction($type = "P")
	{
		$this->_secure();
		$pMdl = new PaymentModel();
		$stMdl = new StudentModel();
		$input = json_decode(file_get_contents('php://input'));
		$walletId = str_replace(ID_SUFFIX, "", $input->external_transaction_id);
		$wData = $pMdl->select('extra_options,balance,student_id,amount')->where("id", $walletId)->get(1)->getRow();
		if ($wData == null) {
			return $this->response->setStatusCode(500)->setJSON(array("message" => "System error, invalid transaction"));
		}
		$token = json_decode($wData->extra_options);
		try {
			$status = 2;
			$error = $input->message;
			if ($input->status_code == 200) {
				$status = 1;
				$error = null;
			}
			$pMdl->save(['id' => $walletId, "status" => $status, "reference_id" => $input->momo_ref_number, "tx_error" => $error]);
			$stMdl->where('id', $wData->student_id)->increment('wallet_balance', $wData->amount);
			//notify device
			$this->sendNotificationMessage($token->token,
				["message" => "Top up completed", "balance" => $wData->balance, "ref_id" => $input->momo_ref_number], ["title" => "Wallet top up done", "message" => "Hello, your top up is completed"]);
		} catch (\ReflectionException|\Exception $e) {
			return $this->response->setStatusCode(500)->setJSON(array("message" => "System error, please try again later"));
		}
	}

	public function change_pin($cardSn)
	{
		$this->_secure();
		$oldpwd = $this->request->getPost("old");
		$pwd = $this->request->getPost("new");
		$stMdl = new StudentModel();
		$result = $stMdl->select('id,wallet_pin,status')->where("lower(card)", strtolower($cardSn))->get(1)->getRow();
		if ($result != null) {
			if ($result->wallet_pin == '' || $result->wallet_pin == sha1($oldpwd)) {
				if ($result->status == 1 || $result->status == 2) {
					$data = array(
						'id' => $result->id, 'wallet_pin' => sha1($pwd)
					);
					try {
						$stMdl->save($data);
						return $this->response->setJSON(array("success" => "PIN changed successfully"));
					} catch (\Exception $e) {
						return $this->response->setJSON(array("error" => "PIN change failed"));
					}
				} else {
					return $this->response->setJSON(array("error" => "Student account not active"));
				}
			} else {
				return $this->response->setJSON(array("error" => "Old PIN not\n correct"));
			}
		}
	}

	public function save_transaction($action, $student)
	{
		$this->_secure();
		$pMdl = new PaymentModel();
		$stMdl = new StudentModel();
		$pin = $this->request->getPost("pin");
		$amount = $this->request->getPost("amount");
		$useRegNo = $this->request->getPost("useRegNo") == 1;
		$operator = $this->request->getPost("operator");
		if (empty($operator) || $operator == '0') {
			return $this->response->setStatusCode(400)->setJSON(array("message" => "Invalid operator"));
		}
		$studentData = $stMdl->select('id,concat(fname," ",lname) as names,regno,wallet_balance,wallet_pin')->where(($useRegNo ? 'regno' : 'card'), $student)
			->get(1)->getRow();
		if ($studentData == null) {
			return $this->response->setStatusCode(400)->setJSON(array("message" => "Student not found"));
		}
		if (strtolower($action) != "topup" && sha1($pin) != $studentData->wallet_pin) {
			return $this->response->setStatusCode(401)->setJSON(array("message" => "Invalid PIN, please try again"));
		}
		if ($studentData->wallet_balance < $amount) {
			return $this->response->setStatusCode(400)->setJSON(array("message" => "No sufficient amount available"));
		}
		try {
			$balance = strtolower($action) == "topup"
				? ($studentData->wallet_balance + $amount)
				: ($studentData->wallet_balance - $amount);
			$pMdl->save(["status" => 1, "type" => walletStrToCode($action), "balance" => $balance
				, 'amount' => $amount, 'student_id' => $studentData->id, 'created_by' => $operator]);
			if (strtolower($action) == "refund" || strtolower($action) == "topup") {
				$stMdl->where('id', $studentData->id)->increment('wallet_balance', $amount);
			} else {
				$stMdl->where('id', $studentData->id)->decrement('wallet_balance', $amount);
			}

			return $this->response->setJSON(array("message" => "$action done successfully", 'names' => $studentData->names,
				'balance' => $balance, 'amount' => $amount));
		} catch (\ReflectionException|\Exception $e) {
			return $this->response->setStatusCode(500)->setJSON(array("message" => "System error, please try again later"));
		}
	}
}
