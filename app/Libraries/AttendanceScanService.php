<?php

namespace App\Libraries;

use App\Models\AttendanceAreaModel;

/**
 * Fast IN/OUT clock for the Android NFC + face kiosk.
 * Same toggle rules as the web scanners: student needs a location, staff uses shift.
 */
class AttendanceScanService
{
	public static function ensureFaceColumn(): void
	{
		static $ready = false;
		if ($ready) {
			return;
		}
		$db = \Config\Database::connect();
		if ($db->tableExists('staffs') && !$db->fieldExists('face_enrolled', 'staffs')) {
			try {
				$db->query('ALTER TABLE `staffs` ADD COLUMN `face_enrolled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `card`');
			} catch (\Throwable $e) {
				// column may already exist on another node
			}
		}
		$ready = true;
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function bootstrap(int $schoolId): array
	{
		self::ensureFaceColumn();
		helper('qonics');
		$db = \Config\Database::connect();

		$school = $db->table('schools sc')
			->select('sc.id, sc.name, sc.logo, sc.phone, sc.email, at.academic_year')
			->join('active_term at', 'at.id = sc.active_term', 'left')
			->where('sc.id', $schoolId)
			->get()
			->getRowArray();

		$logo = '';
		if (!empty($school['logo'])) {
			$logo = base_url('assets/images/logo/' . $school['logo']);
		}

		$areaMdl = new AttendanceAreaModel();
		$locations = [];
		foreach ($areaMdl->listAreas($schoolId, true) as $area) {
			$locations[] = [
				'id' => (int) $area['id'],
				'name' => (string) ($area['name'] ?? ''),
			];
		}

		return [
			'success' => 1,
			'school' => [
				'id' => $schoolId,
				'name' => (string) ($school['name'] ?? ''),
				'phone' => (string) ($school['phone'] ?? ''),
				'email' => (string) ($school['email'] ?? ''),
				'logo' => $logo,
				'academic_year' => (int) ($school['academic_year'] ?? 0),
			],
			'locations' => $locations,
			'staff' => self::staffList($schoolId),
		];
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	public static function staffList(int $schoolId): array
	{
		self::ensureFaceColumn();
		helper('qonics');
		$db = \Config\Database::connect();
		$hasFace = $db->fieldExists('face_enrolled', 'staffs');
		$faceSelect = $hasFace ? ', s.face_enrolled' : '';
		$rows = $db->table('staffs s')
			->select('s.id, s.fname, s.lname, s.photo, s.card, s.shift_id, s.status, p.title as post_title, sh.title as shift_title' . $faceSelect)
			->join('posts p', 'p.id = s.post', 'left')
			->join('shifts sh', 'sh.id = s.shift_id', 'left')
			->where('s.school_id', $schoolId)
			->where('s.status !=', 0)
			->orderBy('s.fname', 'ASC')
			->orderBy('s.lname', 'ASC')
			->get()
			->getResultArray();

		$out = [];
		foreach ($rows as $r) {
			$out[] = [
				'id' => (int) $r['id'],
				'name' => trim((string) ($r['fname'] ?? '') . ' ' . (string) ($r['lname'] ?? '')),
				'post' => (string) ($r['post_title'] ?? ''),
				'shift' => (string) ($r['shift_title'] ?? ''),
				'shift_id' => (int) ($r['shift_id'] ?? 0),
				'card' => (string) ($r['card'] ?? ''),
				'photo' => profile_photo_url($r['photo'] ?? null),
				'face_enrolled' => $hasFace ? (int) ($r['face_enrolled'] ?? 0) : 0,
			];
		}
		return $out;
	}

	/**
	 * Card tap: student (needs location) or staff (shift clock).
	 *
	 * @return array<string,mixed>
	 */
	public static function scanCard(int $schoolId, string $cardRaw, int $areaId): array
	{
		$owner = CardRegistry::lookup($schoolId, $cardRaw);
		if (!$owner) {
			return ['success' => 0, 'message' => 'Card not found'];
		}
		if ($owner['type'] === 'visitor') {
			return ['success' => 0, 'message' => 'Visitor cards cannot be used here'];
		}
		if ($owner['type'] === 'student') {
			return self::scanStudent($schoolId, (int) $owner['id'], $areaId);
		}
		return self::scanStaff($schoolId, (int) $owner['id']);
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function scanStudent(int $schoolId, int $studentId, int $areaId): array
	{
		helper('qonics');
		if ($areaId <= 0) {
			return ['success' => 0, 'message' => 'Select a student location in Settings first'];
		}

		$areaMdl = new AttendanceAreaModel();
		$area = $areaMdl->getActiveForSchool($schoolId, $areaId);
		if (!$area) {
			return ['success' => 0, 'message' => 'Invalid attendance location'];
		}
		$areaName = (string) ($area['name'] ?? '');

		$db = \Config\Database::connect();
		$student = $db->table('students')
			->where('id', $studentId)
			->where('school_id', $schoolId)
			->get()
			->getRow();
		if (!$student) {
			return ['success' => 0, 'message' => 'Student not found'];
		}

		$academicYear = self::academicYear($schoolId);
		$className = '';
		$class = $db->table('class_records cr')
			->select('c.level, c.title')
			->join('classes c', 'c.id = cr.class')
			->where('cr.student', $student->id)
			->where('cr.year', $academicYear)
			->get()
			->getRow();
		if ($class) {
			$className = 'Level ' . $class->level . ' ' . $class->title;
		}

		$time = time();
		$todayStart = strtotime('today');
		$todayEnd = strtotime('tomorrow') - 1;
		$month = date('m-Y');

		$records = $db->table('attendance_records')
			->select("GROUP_CONCAT(DATE_FORMAT(FROM_UNIXTIME(time_in),'%d %H:%i'),';',DATE_FORMAT(FROM_UNIXTIME(time_out),'%d %H:%i')) as records", false)
			->where('user_type', 0)
			->where('user_id', $student->id)
			->where('area_id', $areaId)
			->where("DATE_FORMAT(FROM_UNIXTIME(time_in),'%m-%Y') = " . $db->escape($month), null, false)
			->get()
			->getRow();

		$attendance = $db->table('attendance_records')
			->where('user_id', $student->id)
			->where('school_id', $schoolId)
			->where('area_id', $areaId)
			->where('user_type', 0)
			->where('time_in >=', $todayStart)
			->where('time_in <=', $todayEnd)
			->orderBy('id', 'DESC')
			->get()
			->getRow();

		$status = 'IN';
		if (!$attendance) {
			$db->table('attendance_records')->insert([
				'user_id' => $student->id,
				'user_type' => 0,
				'time_in' => $time,
				'time_out' => 0,
				'school_id' => $schoolId,
				'area_id' => $areaId,
				'shift_id' => 1,
			]);
		} elseif ((int) $attendance->time_out === 0) {
			$db->table('attendance_records')
				->where('id', $attendance->id)
				->update(['time_out' => $time]);
			$status = 'OUT';
		} else {
			return [
				'success' => 0,
				'kind' => 'student',
				'message' => 'Already checked out of ' . $areaName . ' today',
				'person' => self::studentPayload($student, $className, $records->records ?? ''),
				'area' => ['id' => $areaId, 'name' => $areaName],
				'time' => date('H:i', $time),
			];
		}

		$school = $db->table('schools')->select('name, email, phone, logo')->where('id', $schoolId)->get()->getRow();

		return [
			'success' => 1,
			'kind' => 'student',
			'status' => $status,
			'time' => date('H:i', $time),
			'message' => $status === 'IN' ? 'Student IN' : 'Student OUT',
			'person' => self::studentPayload($student, $className, $records->records ?? ''),
			'school' => [
				'name' => (string) ($school->name ?? ''),
				'email' => (string) ($school->email ?? ''),
				'phone' => (string) ($school->phone ?? ''),
				'logo' => !empty($school->logo) ? base_url('assets/images/logo/' . $school->logo) : '',
			],
			'area' => ['id' => $areaId, 'name' => $areaName],
			'month' => $month,
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function scanStaff(int $schoolId, int $staffId): array
	{
		helper('qonics');
		$db = \Config\Database::connect();
		$staff = $db->table('staffs s')
			->select('s.id, s.fname, s.lname, s.photo, s.status, s.shift_id, s.school_id, p.title as post_title, sh.title as shift_title, sh.options as shift_options')
			->join('posts p', 'p.id = s.post', 'left')
			->join('shifts sh', 'sh.id = s.shift_id', 'left')
			->where('s.id', $staffId)
			->where('s.school_id', $schoolId)
			->where('s.status !=', 0)
			->get()
			->getRow();

		if (!$staff) {
			return ['success' => 0, 'message' => 'Staff not found'];
		}

		$time = time();
		$shift = null;
		if (!empty($staff->shift_id) && (int) $staff->shift_id > 0) {
			$shift = [
				'title' => (string) ($staff->shift_title ?? ''),
				'options' => $staff->shift_options ?? '[]',
			];
		}
		$window = StaffShiftClock::windowFor($shift, $time);
		$lookFrom = (int) ($window['look_from'] ?? strtotime('today'));
		$lookTo = (int) ($window['look_to'] ?? (strtotime('tomorrow') - 1));

		$attendance = $db->table('attendance_records')
			->where('user_id', (int) $staff->id)
			->where('user_type', 1)
			->where('school_id', $schoolId)
			->where('time_in >=', $lookFrom)
			->where('time_in <=', $lookTo)
			->orderBy('id', 'DESC')
			->get()
			->getRow();

		$status = 'IN';
		$verdict = StaffShiftClock::evaluateIn($time, $window);
		if (!$attendance) {
			$db->table('attendance_records')->insert([
				'user_id' => (int) $staff->id,
				'user_type' => 1,
				'time_in' => $time,
				'time_out' => 0,
				'school_id' => $schoolId,
				'area_id' => 0,
				'shift_id' => (int) ($staff->shift_id ?? 0),
			]);
		} elseif ((int) $attendance->time_out === 0) {
			$db->table('attendance_records')
				->where('id', $attendance->id)
				->update(['time_out' => $time]);
			$status = 'OUT';
			$verdict = StaffShiftClock::evaluateOut($time, $window);
		} else {
			return [
				'success' => 0,
				'kind' => 'staff',
				'message' => 'Already checked out for this shift',
				'person' => self::staffPayload($staff),
				'time' => date('H:i', $time),
			];
		}

		$shiftHours = '';
		if (!empty($window['working']) && !empty($window['start_label'])) {
			$shiftHours = $window['start_label'] . ' – ' . $window['end_label'];
		} elseif ($shift) {
			$shiftHours = 'Off day';
		} else {
			$shiftHours = 'No shift assigned';
		}

		return [
			'success' => 1,
			'kind' => 'staff',
			'status' => $status,
			'time' => date('H:i', $time),
			'message' => $status === 'IN' ? 'Staff IN' : 'Staff OUT',
			'verdict' => $verdict,
			'shift' => [
				'title' => (string) ($window['title'] ?: ($staff->shift_title ?? 'No shift')),
				'hours' => $shiftHours,
				'working' => !empty($window['working']),
			],
			'person' => self::staffPayload($staff),
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function enrollFace(int $schoolId, int $staffId, ?string $photoBase64 = null): array
	{
		self::ensureFaceColumn();
		helper('qonics');
		$db = \Config\Database::connect();
		$staff = $db->table('staffs')
			->select('id, photo')
			->where('id', $staffId)
			->where('school_id', $schoolId)
			->where('status !=', 0)
			->get()
			->getRow();
		if (!$staff) {
			return ['success' => 0, 'message' => 'Staff not found'];
		}

		$filename = null;
		if ($photoBase64) {
			$raw = $photoBase64;
			if (strpos($raw, ',') !== false) {
				$raw = substr($raw, strpos($raw, ',') + 1);
			}
			$decoded = base64_decode($raw, true);
			if ($decoded !== false && strlen($decoded) > 100) {
				$filename = 'face_staff_' . $staffId . '_' . uniqid() . '.jpg';
				$dir = FCPATH . 'assets/images/profile/';
				if (!is_dir($dir)) {
					@mkdir($dir, 0775, true);
				}
				if (file_put_contents($dir . $filename, $decoded) === false) {
					$filename = null;
				}
			}
		}

		$data = ['face_enrolled' => 1];
		if ($filename) {
			$data['photo'] = $filename;
		}
		$db->table('staffs')->where('id', $staffId)->where('school_id', $schoolId)->update($data);

		$photo = $filename ? profile_photo_url($filename) : profile_photo_url($staff->photo ?? null);
		return [
			'success' => 1,
			'message' => 'Face enrolled',
			'staff_id' => $staffId,
			'photo' => $photo,
		];
	}

	private static function academicYear(int $schoolId): int
	{
		$db = \Config\Database::connect();
		$row = $db->table('schools sc')
			->select('at.academic_year')
			->join('active_term at', 'at.id = sc.active_term', 'left')
			->where('sc.id', $schoolId)
			->get()
			->getRow();
		$year = (int) ($row->academic_year ?? 0);
		return $year > 0 ? $year : (int) date('Y');
	}

	/**
	 * @param object $student
	 * @return array<string,mixed>
	 */
	private static function studentPayload($student, string $className, string $records): array
	{
		return [
			'id' => (int) $student->id,
			'name' => trim((string) $student->fname . ' ' . (string) $student->lname),
			'regno' => (string) ($student->regno ?? ''),
			'class' => $className,
			'photo' => profile_photo_url($student->photo ?? null),
			'records' => $records,
		];
	}

	/**
	 * @param object $staff
	 * @return array<string,mixed>
	 */
	private static function staffPayload($staff): array
	{
		return [
			'id' => (int) $staff->id,
			'name' => trim((string) $staff->fname . ' ' . (string) $staff->lname),
			'post' => (string) ($staff->post_title ?? ''),
			'photo' => profile_photo_url($staff->photo ?? null),
		];
	}
}
