<?php
/**
 * Ensure Physics Chemistry Biology (PCB) department exists, create S6 PCB for
 * Wisdom school 27, and move the two S6PCB students off S6 PCM.
 *
 * docker exec xander_school_app php /var/www/html/deploy/create_s6_pcb_class.php
 */
declare(strict_types=1);

define('FCPATH', __DIR__ . '/../public/');
chdir(FCPATH);
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/Config/Paths.php';

$paths = new Config\Paths();
require rtrim($paths->systemDirectory, '/ ') . '/bootstrap.php';

$db = \Config\Database::connect();

const SCHOOL_ID = 27;
const ACADEMIC_YEAR = '16';
const PCM_CLASS_ID = 194;
const S6_LEVEL_ID = 9;
const PCB_NAMES = [
	['fname' => 'BASANGIRA MATHIS', 'lname' => 'GABRIEL'],
	['fname' => 'NZAYISENGA', 'lname' => 'Kevin'],
];

$now = date('Y-m-d H:i:s');

$dept = $db->table('departments')
	->groupStart()
		->where('code', 'PCB')
		->orWhere('title', 'Physics Chemistry Biology')
	->groupEnd()
	->get(1)->getRowArray();

if (!$dept) {
	$fac = $db->table('faculty')->where('id', 1)->get(1)->getRowArray();
	$facultyId = (int) ($fac['id'] ?? 1);
	$db->table('departments')->insert([
		'title' => 'Physics Chemistry Biology',
		'code' => 'PCB',
		'faculty_id' => $facultyId,
		'created_at' => $now,
		'created_by' => 1,
		'updated_at' => $now,
		'updated_by' => 1,
	]);
	$deptId = (int) $db->insertID();
	echo "Created department PCB id={$deptId}\n";
} else {
	$deptId = (int) $dept['id'];
	echo "Department PCB already exists id={$deptId} {$dept['title']}\n";
}

$class = $db->table('classes')
	->where('school_id', SCHOOL_ID)
	->where('level', S6_LEVEL_ID)
	->where('department', $deptId)
	->get(1)->getRowArray();

if (!$class) {
	$pcm = $db->table('classes')->where('id', PCM_CLASS_ID)->get(1)->getRowArray();
	$mentor = (int) ($pcm['mentor'] ?? 0);
	$db->table('classes')->insert([
		'school_id' => SCHOOL_ID,
		'level' => S6_LEVEL_ID,
		'department' => $deptId,
		'title' => '',
		'mentor' => $mentor,
		'created_at' => $now,
		'created_by' => 1,
		'updated_at' => $now,
		'updated_by' => 1,
	]);
	$classId = (int) $db->insertID();
	echo "Created S6 PCB class id={$classId}\n";
} else {
	$classId = (int) $class['id'];
	echo "S6 PCB class already exists id={$classId}\n";
}

$moved = 0;
foreach (PCB_NAMES as $nm) {
	$st = $db->table('students')
		->where('school_id', SCHOOL_ID)
		->where('status', 1)
		->where('fname', $nm['fname'])
		->where('lname', $nm['lname'])
		->get(1)->getRowArray();
	if (!$st) {
		$st = $db->table('students')
			->where('school_id', SCHOOL_ID)
			->where('status', 1)
			->like('fname', $nm['fname'], 'both')
			->like('lname', $nm['lname'], 'both')
			->get(1)->getRowArray();
	}
	if (!$st) {
		echo "WARN student not found: {$nm['fname']} {$nm['lname']}\n";
		continue;
	}
	$sid = (int) $st['id'];
	$rows = $db->table('class_records')
		->where('student', $sid)
		->groupStart()
			->where('year', ACADEMIC_YEAR)
			->orWhere('year', '31536000')
		->groupEnd()
		->get()->getResultArray();
	$already = false;
	foreach ($rows as $row) {
		$rid = (int) $row['id'];
		$cid = (int) $row['class'];
		if ($cid === $classId) {
			$db->table('class_records')->where('id', $rid)->update([
				'status' => 1,
				'year' => '16',
			]);
			$already = true;
			continue;
		}
		$title = $db->table('classes')->select('title')->where('id', $cid)->get(1)->getRowArray();
		if (stripos((string) ($title['title'] ?? ''), 'holiday') !== false) {
			continue;
		}
		if ((int) ($row['status'] ?? 0) === 1) {
			$db->table('class_records')->where('id', $rid)->update(['status' => 0]);
		}
	}
	if (!$already) {
		$db->table('class_records')->insert([
			'student' => $sid,
			'year' => '16',
			'class' => $classId,
			'status' => 1,
		]);
	}
	$moved++;
	echo "Moved {$st['regno']} {$st['fname']} {$st['lname']} -> S6 PCB ({$classId})\n";
}

echo "\nVerify:\n";
$chk = $db->query(
	"SELECT c.id, l.title AS lvl, d.code, d.title AS dept,
	        (SELECT COUNT(*) FROM class_records cr WHERE cr.class=c.id AND cr.year=16 AND cr.status=1) AS n
	 FROM classes c
	 JOIN levels l ON l.id=c.level
	 JOIN departments d ON d.id=c.department
	 WHERE c.school_id=27 AND (d.code IN ('PCB','PCM') AND l.title='S6')"
)->getResultArray();
foreach ($chk as $row) {
	echo "  {$row['lvl']} {$row['code']} {$row['dept']} id={$row['id']} students={$row['n']}\n";
}

$people = $db->query(
	"SELECT s.regno, s.fname, s.lname, d.code
	 FROM class_records cr
	 JOIN students s ON s.id=cr.student
	 JOIN classes c ON c.id=cr.class
	 JOIN departments d ON d.id=c.department
	 WHERE cr.class={$classId} AND cr.year=16 AND cr.status=1"
)->getResultArray();
foreach ($people as $row) {
	echo "  PCB student: {$row['regno']} {$row['fname']} {$row['lname']}\n";
}

echo "DONE class_id={$classId}\n";
exit(0);
