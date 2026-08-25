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
			->select('sc.id, sc.name, sc.acronym, sc.logo, sc.phone, sc.email, at.academic_year')
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
				'acronym' => (string) ($school['acronym'] ?? ''),
				'phone' => (string) ($school['phone'] ?? ''),
				'email' => (string) ($school['email'] ?? ''),
				'logo' => $logo,
				'academic_year' => (int) ($school['academic_year'] ?? 0),
			],
			'locations' => $locations,
			'staff' => self::staffList($schoolId),
			'students' => self::studentList($schoolId),
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function openByAcronym(string $acronym): array
	{
		$acronym = strtoupper(trim($acronym));
		if ($acronym === '') {
			return ['success' => 0, 'message' => 'Enter the school acronym'];
		}
		$db = \Config\Database::connect();
		$row = $db->table('schools')
			->select('id, name, acronym, status')
			->where('LOWER(acronym)', strtolower($acronym))
			->get()
			->getRowArray();
		if (!$row) {
			return ['success' => 0, 'message' => 'No school found for acronym ' . $acronym];
		}
		if ((int) ($row['status'] ?? 1) === 0) {
			return ['success' => 0, 'message' => 'This school is locked'];
		}
		return self::bootstrap((int) $row['id']);
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	public static function studentList(int $schoolId): array
	{
		helper('qonics');
		$db = \Config\Database::connect();
		$year = self::academicYear($schoolId);
		$rows = $db->table('students s')
			->select('s.id, s.fname, s.lname, s.regno, s.card, s.photo')
			->where('s.school_id', $schoolId)
			->where('s.status', 1)
			->orderBy('s.fname', 'ASC')
			->get()
			->getResultArray();

		$classes = [];
		if ($rows !== []) {
			$ids = [];
			foreach ($rows as $r) {
				$ids[] = (int) $r['id'];
			}
			$cr = $db->table('class_records cr')
				->select("cr.student, CONCAT(COALESCE(l.title,''),' ',COALESCE(c.title,'')) AS class_name", false)
				->join('classes c', 'c.id = cr.class', 'left')
				->join('levels l', 'l.id = c.level', 'left')
				->where('cr.year', $year)
				->whereIn('cr.student', $ids)
				->get()
				->getResultArray();
			foreach ($cr as $c) {
				$classes[(int) $c['student']] = trim((string) ($c['class_name'] ?? ''));
			}
		}

		$out = [];
		foreach ($rows as $r) {
			$sid = (int) $r['id'];
			$out[] = [
				'id' => $sid,
				'name' => trim((string) ($r['fname'] ?? '') . ' ' . (string) ($r['lname'] ?? '')),
				'regno' => (string) ($r['regno'] ?? ''),
				'class' => $classes[$sid] ?? '',
				'card' => (string) ($r['card'] ?? ''),
				'photo' => profile_photo_url($r['photo'] ?? null),
			];
		}
		return $out;
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
			$enrolled = $hasFace ? (int) ($r['face_enrolled'] ?? 0) : 0;
			$photo = profile_photo_url($r['photo'] ?? null);
			$verify = ($enrolled === 1 && $photo !== '' && strpos($photo, 'fallback-avatar') === false)
				? $photo
				: '';
			$out[] = [
				'id' => (int) $r['id'],
				'name' => trim((string) ($r['fname'] ?? '') . ' ' . (string) ($r['lname'] ?? '')),
				'post' => (string) ($r['post_title'] ?? ''),
				'shift' => (string) ($r['shift_title'] ?? ''),
				'shift_id' => (int) ($r['shift_id'] ?? 0),
				'card' => (string) ($r['card'] ?? ''),
				'photo' => $photo,
				'verify_photo' => $verify,
				'face_enrolled' => $enrolled,
			];
		}
		return $out;
	}

	/**
	 * Card tap: students only. Staff must clock by face.
	 *
	 * @return array<string,mixed>
	 */
	public static function scanCard(int $schoolId, string $cardRaw, int $areaId, int $eventTime = 0): array
	{
		$owner = CardRegistry::lookup($schoolId, $cardRaw);
		if (!$owner) {
			return ['success' => 0, 'message' => 'Card not found'];
		}
		if ($owner['type'] === 'visitor') {
			return ['success' => 0, 'message' => 'Visitor cards cannot be used here'];
		}
		if ($owner['type'] === 'staff') {
			return ['success' => 0, 'kind' => 'staff', 'message' => 'Staff must use face, not card'];
		}
		if ($owner['type'] === 'student') {
			return self::scanStudent($schoolId, (int) $owner['id'], $areaId, $eventTime);
		}
		return ['success' => 0, 'message' => 'Card not found'];
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function scanStudent(int $schoolId, int $studentId, int $areaId, int $eventTime = 0): array
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

		$time = $eventTime > 1000000000 ? $eventTime : time();
		$todayStart = strtotime('today', $time);
		$todayEnd = strtotime('tomorrow', $time) - 1;
		$month = date('m-Y', $time);

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
			->orderBy('id', 'ASC')
			->get()
			->getRow();

		// First tap of the day is IN. Every later tap is OUT and overwrites time_out.
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
		} else {
			$db->table('attendance_records')
				->where('id', $attendance->id)
				->update(['time_out' => $time]);
			$status = 'OUT';
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

	private const STAFF_MAX_IN_PER_DAY = 2;
	private const STAFF_MIN_GAP_SECONDS = 0;

	/**
	 * Staff face/card clock. First detection of the day is IN.
	 * A later detection while still inside is OUT. After OUT they may IN once more.
	 * Nobody may IN more than twice in a calendar day.
	 *
	 * @return array<string,mixed>
	 */
	public static function scanStaff(int $schoolId, int $staffId, int $eventTime = 0, string $wanted = ''): array
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

		$time = $eventTime > 1000000000 ? $eventTime : time();
		$shift = null;
		if (!empty($staff->shift_id) && (int) $staff->shift_id > 0) {
			$shift = [
				'title' => (string) ($staff->shift_title ?? ''),
				'options' => $staff->shift_options ?? '[]',
			];
		}
		$window = StaffShiftClock::windowFor($shift, $time);
		$todayStart = strtotime('today', $time);
		$todayEnd = strtotime('tomorrow', $time) - 1;

		$rows = $db->table('attendance_records')
			->where('user_id', (int) $staff->id)
			->where('user_type', 1)
			->where('school_id', $schoolId)
			->where('time_in >=', $todayStart)
			->where('time_in <=', $todayEnd)
			->orderBy('time_in', 'ASC')
			->orderBy('id', 'ASC')
			->get()
			->getResult();

		$inCount = count($rows);
		$open = null;
		foreach ($rows as $row) {
			if ((int) ($row->time_out ?? 0) === 0) {
				$open = $row;
			}
		}

		$wanted = strtoupper(trim($wanted));
		if ($wanted !== 'IN' && $wanted !== 'OUT') {
			$wanted = '';
		}

		if ($open) {
			if (((int) $open->time_in + self::STAFF_MIN_GAP_SECONDS) > $time) {
				return self::staffClockPayload(
					$staff, 'IN', (int) $open->time_in, $window, $shift,
					StaffShiftClock::evaluateIn((int) $open->time_in, $window),
					true, $inCount, 'Already checked IN'
				);
			}
			$db->table('attendance_records')
				->where('id', $open->id)
				->update(['time_out' => $time]);
			return self::staffClockPayload(
				$staff, 'OUT', $time, $window, $shift,
				StaffShiftClock::evaluateOut($time, $window),
				false, $inCount, 'Already checked IN — now OUT'
			);
		}

		if ($inCount >= self::STAFF_MAX_IN_PER_DAY) {
			return self::staffRejectPayload(
				$staff, 'Already checked IN twice today', 'OUT', $time, $window, $shift, $inCount
			);
		}

		$db->table('attendance_records')->insert([
			'user_id' => (int) $staff->id,
			'user_type' => 1,
			'time_in' => $time,
			'time_out' => 0,
			'school_id' => $schoolId,
			'area_id' => 0,
			'shift_id' => (int) ($staff->shift_id ?? 0),
		]);
		return self::staffClockPayload(
			$staff, 'IN', $time, $window, $shift,
			StaffShiftClock::evaluateIn($time, $window),
			false, $inCount + 1
		);
	}

	/**
	 * @param object $staff
	 * @param array<string,mixed> $window
	 * @param array<string,mixed>|null $shift
	 * @param array<string,mixed> $verdict
	 * @return array<string,mixed>
	 */
	private static function staffClockPayload($staff, string $status, int $time, array $window, $shift, array $verdict, bool $already, int $inCount = 0, string $message = ''): array
	{
		$shiftHours = '';
		if (!empty($window['working']) && !empty($window['start_label'])) {
			$shiftHours = $window['start_label'] . ' – ' . $window['end_label'];
		} elseif ($shift) {
			$shiftHours = 'Off day';
		} else {
			$shiftHours = 'No shift assigned';
		}
		$person = self::staffPayload($staff);
		if ($message === '') {
			$message = $already
				? ('Already ' . $status)
				: ($status === 'IN' ? 'Staff IN' : 'Staff OUT');
		}
		return [
			'success' => 1,
			'kind' => 'staff',
			'status' => $status,
			'time' => date('H:i', $time),
			'message' => $message,
			'in_count' => $inCount,
			'verdict' => $verdict,
			'shift' => [
				'title' => (string) ($window['title'] ?: ($staff->shift_title ?? 'No shift')),
				'hours' => $shiftHours,
				'working' => !empty($window['working']),
			],
			'person' => $person,
			'staff' => $person,
		];
	}

	/**
	 * @param object $staff
	 * @param array<string,mixed> $window
	 * @param array<string,mixed>|null $shift
	 * @return array<string,mixed>
	 */
	private static function staffRejectPayload($staff, string $message, string $status, int $time, array $window, $shift, int $inCount): array
	{
		$out = self::staffClockPayload(
			$staff, $status, $time, $window, $shift,
			['code' => 'none', 'label' => '', 'detail' => '', 'minutes' => 0],
			true, $inCount, $message
		);
		$out['success'] = 0;
		return $out;
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

		$filename = self::storeFaceJpeg($staffId, $photoBase64);
		if ($photoBase64 && $filename === null) {
			return ['success' => 0, 'message' => 'Could not save the face photo on the server'];
		}

		$data = ['face_enrolled' => 1];
		if ($filename) {
			$data['photo'] = $filename;
		}
		$db->table('staffs')->where('id', $staffId)->where('school_id', $schoolId)->update($data);

		$photo = $filename ? profile_photo_url($filename) : profile_photo_url($staff->photo ?? null);
		return [
			'success' => 1,
			'message' => 'Face enrolled and saved on the school server',
			'staff_id' => $staffId,
			'face_enrolled' => 1,
			'photo' => $photo,
			'verify_photo' => $photo,
		];
	}

	/**
	 * Store a HeyStar camera JPEG on the VPS. First capture enrolls; later
	 * captures refresh the same staff photo so verification stays HeyStar-sourced.
	 */
	public static function enrollFaceFromHeyStar(int $schoolId, int $staffId, ?string $photoBase64): bool
	{
		if ($schoolId <= 0 || $staffId <= 0 || !is_string($photoBase64) || trim($photoBase64) === '') {
			return false;
		}
		$out = self::enrollFace($schoolId, $staffId, $photoBase64);
		return !empty($out['success']);
	}

	/**
	 * Save a capture only when this staff does not already have a VPS face.
	 */
	public static function enrollFaceIfMissing(int $schoolId, int $staffId, ?string $photoBase64): bool
	{
		if ($schoolId <= 0 || $staffId <= 0 || !is_string($photoBase64) || trim($photoBase64) === '') {
			return false;
		}
		self::ensureFaceColumn();
		$db = \Config\Database::connect();
		$row = $db->table('staffs')
			->select('id, face_enrolled')
			->where('id', $staffId)
			->where('school_id', $schoolId)
			->where('status !=', 0)
			->get()
			->getRowArray();
		if (!$row) {
			return false;
		}
		if ((int) ($row['face_enrolled'] ?? 0) === 1) {
			return false;
		}
		return self::enrollFaceFromHeyStar($schoolId, $staffId, $photoBase64);
	}

	/**
	 * @return string|null stored filename
	 */
	private static function storeFaceJpeg(int $staffId, ?string $photoBase64): ?string
	{
		$raw = self::decodeImageBase64($photoBase64);
		if ($raw === null) {
			return null;
		}
		$filename = 'face_staff_' . $staffId . '.jpg';
		$dir = FCPATH . 'assets/images/profile/';
		if (!is_dir($dir)) {
			@mkdir($dir, 0775, true);
		}
		if (file_put_contents($dir . $filename, $raw) === false) {
			return null;
		}
		return $filename;
	}

	public static function decodeImageBase64(?string $photoBase64): ?string
	{
		if (!is_string($photoBase64)) {
			return null;
		}
		$raw = trim($photoBase64);
		if ($raw === '') {
			return null;
		}
		if (strpos($raw, ',') !== false) {
			$raw = substr($raw, strpos($raw, ',') + 1);
		}
		$decoded = base64_decode($raw, true);
		if ($decoded === false || strlen($decoded) < 100) {
			return null;
		}
		return $decoded;
	}

	public static function payloadImage(array $in): string
	{
		foreach (['checkImgBase64', 'imgBase64', 'photo', 'faceImg', 'check_img_base64'] as $key) {
			$v = $in[$key] ?? '';
			if (is_string($v) && trim($v) !== '') {
				return $v;
			}
		}
		return '';
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function removeFace(int $schoolId, int $staffId): array
	{
		self::ensureFaceColumn();
		$db = \Config\Database::connect();
		$staff = $db->table('staffs')
			->select('id')
			->where('id', $staffId)
			->where('school_id', $schoolId)
			->get()
			->getRow();
		if (!$staff) {
			return ['success' => 0, 'message' => 'Staff not found'];
		}
		$db->table('staffs')->where('id', $staffId)->where('school_id', $schoolId)->update([
			'face_enrolled' => 0,
		]);
		return [
			'success' => 1,
			'message' => 'Face removed. Record a new live face on the kiosk.',
			'staff_id' => $staffId,
			'face_enrolled' => 0,
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

	/**
	 * HeyStar identify-record upload. Card numbers must match cards assigned in the web app.
	 *
	 * @param array<string,mixed> $in
	 * @return array<string,mixed>
	 */
	public static function ingestHeyStarRecord(array $in): array
	{
		HeyStarDeviceStore::ensureSchema();
		$ack = ['result' => 1, 'code' => '000'];
		$schoolId = (int) ($in['school_id'] ?? 0);
		$deviceKey = trim((string) ($in['deviceKey'] ?? ''));
		if ($schoolId <= 0 && $deviceKey !== '') {
			$dev = HeyStarDeviceStore::forDeviceKey($deviceKey);
			$schoolId = $dev ? (int) $dev['school_id'] : 0;
		}
		if ($schoolId <= 0) {
			return $ack;
		}

		$recordId = trim((string) ($in['recordId'] ?? ''));
		if ($recordId !== '' && HeyStarDeviceStore::seenRecord($schoolId, $recordId)) {
			return $ack;
		}

		$stranger = (int) ($in['strangerFlag'] ?? 0) === 1;
		$resultFlag = (int) ($in['resultFlag'] ?? 1);
		if ($stranger || ($resultFlag !== 0 && $resultFlag !== 1)) {
			return $ack;
		}

		$eventTime = (int) ($in['recordTime'] ?? 0);
		if ($eventTime > 20000000000) {
			$eventTime = (int) floor($eventTime / 1000);
		}

		$dev = HeyStarDeviceStore::forSchool($schoolId);
		$areaId = (int) ($dev['area_id'] ?? 0);
		if ($areaId <= 0) {
			$areas = (new AttendanceAreaModel())->listAreas($schoolId, true);
			$areaId = $areas !== [] ? (int) $areas[0]['id'] : 0;
		}

		$sn = trim((string) ($in['personSn'] ?? ''));
		if (preg_match('/^T(\d+)$/', $sn, $m)) {
			$staffId = (int) $m[1];
			self::enrollFaceFromHeyStar($schoolId, $staffId, self::payloadImage($in));
			return array_merge($ack, self::scanStaff($schoolId, $staffId, $eventTime));
		}
		if (preg_match('/^S(\d+)$/', $sn, $m)) {
			return array_merge($ack, self::scanStudent($schoolId, (int) $m[1], $areaId, $eventTime));
		}

		$card = trim((string) ($in['cardNo'] ?? ''));
		if ($card !== '') {
			$owner = CardRegistry::lookup($schoolId, $card);
			if ($owner && $owner['type'] === 'student') {
				return array_merge($ack, self::scanStudent($schoolId, (int) $owner['id'], $areaId, $eventTime));
			}
		}

		return $ack;
	}

	/**
	 * HeyStar registered-person upload (type 3). Manual face on the terminal
	 * is stored on the VPS when the payload includes a JPEG.
	 *
	 * @param array<string,mixed> $in
	 */
	public static function ingestHeyStarPerson(array $in): array
	{
		HeyStarDeviceStore::ensureSchema();
		$ack = ['result' => 1, 'code' => '000'];
		$schoolId = (int) ($in['school_id'] ?? 0);
		$deviceKey = trim((string) ($in['deviceKey'] ?? ''));
		if ($schoolId <= 0 && $deviceKey !== '') {
			$dev = HeyStarDeviceStore::forDeviceKey($deviceKey);
			$schoolId = $dev ? (int) $dev['school_id'] : 0;
		}
		if ($schoolId <= 0) {
			return $ack;
		}

		$sn = trim((string) ($in['personSn'] ?? $in['sn'] ?? ''));
		if (!preg_match('/^T(\d+)$/', $sn, $m)) {
			return $ack;
		}
		$img = self::payloadImage($in);
		if ($img !== '') {
			self::enrollFaceFromHeyStar($schoolId, (int) $m[1], $img);
		}
		return $ack;
	}

	/**
	 * @param array<string,mixed> $in
	 */
	public static function ingestHeyStarHeartbeat(array $in): void
	{
		$schoolId = (int) ($in['school_id'] ?? 0);
		$deviceKey = trim((string) ($in['deviceKey'] ?? ''));
		$ip = trim((string) ($in['ip'] ?? ''));
		if ($schoolId <= 0 && $deviceKey !== '') {
			$dev = HeyStarDeviceStore::forDeviceKey($deviceKey);
			$schoolId = $dev ? (int) $dev['school_id'] : 0;
		}
		if ($schoolId <= 0) {
			return;
		}
		$data = ['last_seen' => time()];
		if ($deviceKey !== '') {
			$data['device_key'] = $deviceKey;
		}
		if ($ip !== '') {
			$data['device_ip'] = $ip;
		}
		HeyStarDeviceStore::save($schoolId, $data);
	}
}
