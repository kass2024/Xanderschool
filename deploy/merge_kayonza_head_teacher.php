<?php
/**
 * Keep a single Head Teacher at WISDOM SCHOOL KAYONZA (school 35).
 * Keep staff 47 (umutonihenry@gmail.com), merge class mentoring from
 * duplicate staff 105, then delete 105.
 *
 * Run: docker exec xander_school_app php /var/www/html/deploy/merge_kayonza_head_teacher.php
 */
declare(strict_types=1);

define('FCPATH', __DIR__ . '/../public/');
chdir(FCPATH);
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/Config/Paths.php';

$paths = new Config\Paths();
require rtrim($paths->systemDirectory, '/ ') . '/bootstrap.php';

$db = \Config\Database::connect();

const SCHOOL_ID = 35;
const KEEP_ID = 47;
const DROP_ID = 105;
const HEAD_TEACHER_ID = 25;

$school = $db->table('schools')->where('id', SCHOOL_ID)->get(1)->getRowArray();
$name = strtoupper((string) ($school['name'] ?? ''));
if (!$school || strpos($name, 'KAYONZA') === false) {
	fwrite(STDERR, "Refusing: school 35 is not Wisdom Kayonza\n");
	exit(1);
}

$keep = $db->table('staffs')->where('id', KEEP_ID)->where('school_id', SCHOOL_ID)->get(1)->getRowArray();
$drop = $db->table('staffs')->where('id', DROP_ID)->where('school_id', SCHOOL_ID)->get(1)->getRowArray();
if (!$keep || !$drop) {
	fwrite(STDERR, "Expected Kayonza staff 47 and 105 were not both found\n");
	exit(1);
}

$now = date('Y-m-d H:i:s');
$movedClasses = 0;

$db->transStart();

$classes = $db->table('classes')->where('school_id', SCHOOL_ID)->where('mentor', DROP_ID)->get()->getResultArray();
foreach ($classes as $class) {
	$db->table('classes')->where('id', (int) $class['id'])->where('school_id', SCHOOL_ID)->update(['mentor' => KEEP_ID]);
	$movedClasses++;
	echo 'Class mentor ' . ($class['id'] ?? '') . ' ' . ($class['code'] ?? '') . " -> staff 47\n";
}

foreach (['course_records' => 'lecturer', 'timetable_entries' => 'staff_id'] as $table => $col) {
	if (!$db->tableExists($table)) {
		continue;
	}
	$n = $db->table($table)->where($col, DROP_ID)->countAllResults(false);
	if ($n > 0) {
		$db->table($table)->where($col, DROP_ID)->update([$col => KEEP_ID]);
		echo "Updated {$n} {$table}.{$col}\n";
	}
}

foreach (['discipline', 'permissions', 'marks'] as $table) {
	if (!$db->tableExists($table)) {
		continue;
	}
	$n = $db->table($table)->where('created_by', DROP_ID)->countAllResults(false);
	if ($n > 0) {
		$db->table($table)->where('created_by', DROP_ID)->update(['created_by' => KEEP_ID]);
		echo "Updated {$n} {$table}.created_by\n";
	}
}

$keepUpdate = [
	'post' => HEAD_TEACHER_ID,
	'status' => 1,
	'fname' => 'UMUTONI',
	'lname' => 'HENRIETTE',
	'email' => 'umutonihenry@gmail.com',
	'updated_at' => $now,
];
if (trim((string) ($keep['phone'] ?? '')) === '' && trim((string) ($drop['phone'] ?? '')) !== '') {
	$keepUpdate['phone'] = $drop['phone'];
}
if (trim((string) ($keep['photo'] ?? '')) === '' && trim((string) ($drop['photo'] ?? '')) !== '') {
	$keepUpdate['photo'] = $drop['photo'];
}
$db->table('staffs')->where('id', KEEP_ID)->where('school_id', SCHOOL_ID)->update($keepUpdate);

$db->table('staffs')->where('id', DROP_ID)->where('school_id', SCHOOL_ID)->delete();

$db->table('schools')->where('id', SCHOOL_ID)->update([
	'head_master' => 'UMUTONI HENRIETTE',
]);

$db->transComplete();
if ($db->transStatus() === false) {
	fwrite(STDERR, "Transaction failed\n");
	exit(1);
}

$left = $db->table('staffs')->where('school_id', SCHOOL_ID)->where('post', HEAD_TEACHER_ID)->get()->getResultArray();
echo "\nMoved class mentors: {$movedClasses}\n";
echo "Remaining Head Teachers at Kayonza: " . count($left) . "\n";
foreach ($left as $row) {
	echo sprintf(
		"  #%d %s %s <%s> status=%s\n",
		(int) $row['id'],
		$row['fname'] ?? '',
		$row['lname'] ?? '',
		$row['email'] ?? '',
		$row['status'] ?? ''
	);
}
$gone = $db->table('staffs')->where('id', DROP_ID)->get(1)->getRowArray();
echo 'Duplicate 105 ' . ($gone ? "STILL EXISTS\n" : "deleted\n");
echo "DONE\n";
exit(0);
