<?php
/**
 * Set WISDOM SCHOOL KANZENZE (school 39 / WIS-KAN) login to
 * jeremiesemagori@gmail.com with the default child password.
 *
 * Only this school is touched.
 *
 * Usage:
 *   php deploy/set_wisdom_kanzenze_login.php --dry-run
 *   php deploy/set_wisdom_kanzenze_login.php
 */
declare(strict_types=1);

define('FCPATH', __DIR__ . '/../public/');
chdir(FCPATH);
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/Config/Paths.php';

$paths = new Config\Paths();
require rtrim($paths->systemDirectory, '/ ') . '/bootstrap.php';

$db = \Config\Database::connect();
$dryRun = in_array('--dry-run', $argv ?? [], true);

const TARGET_SCHOOL_ID = 39;
const LOGIN_EMAIL = 'jeremiesemagori@gmail.com';
const DEFAULT_PASSWORD = 'Wisdom@2026';
const HEAD_MASTER_POST = 1;

function say(string $msg): void
{
	echo $msg . PHP_EOL;
}

function compact_name(string $value): string
{
	return preg_replace('/[^A-Z0-9]/', '', strtoupper($value)) ?? '';
}

$probe = $db->query(
	"SELECT s.id, s.email, CHAR_LENGTH(s.email) AS email_len, s.status, s.post, s.school_id,
		p.id AS post_id, sc.id AS school_row, sc.status AS school_status
	 FROM staffs s
	 LEFT JOIN posts p ON p.id = s.post
	 LEFT JOIN schools sc ON sc.id = s.school_id
	 WHERE s.email = ?",
	[LOGIN_EMAIL]
)->getResultArray();
$current = $db->table('staffs')->select('id,password')->where('email', LOGIN_EMAIL)->get(1)->getRowArray();
say('Password already matches: ' . (($current && password_verify(DEFAULT_PASSWORD, (string) ($current['password'] ?? ''))) ? 'yes' : 'no'));
say('Login lookup rows: ' . count($probe));
foreach ($probe as $row) {
	say('  id=' . $row['id']
		. ' status=' . $row['status']
		. ' post=' . $row['post']
		. ' post_id=' . ($row['post_id'] ?? 'NULL')
		. ' school=' . ($row['school_row'] ?? 'NULL')
		. ' school_status=' . ($row['school_status'] ?? 'NULL')
		. ' email_len=' . $row['email_len']);
}

$school = $db->table('schools')->where('id', TARGET_SCHOOL_ID)->get(1)->getRowArray();
$schoolName = compact_name((string) ($school['name'] ?? ''));
if (!$school || strpos($schoolName, 'KANZENZE') === false || (int) $school['id'] !== TARGET_SCHOOL_ID) {
	fwrite(STDERR, "Refusing: school id 39 is not Wisdom Kanzenze\n");
	exit(1);
}

$head = $db->table('staffs')
	->where('school_id', TARGET_SCHOOL_ID)
	->where('post', HEAD_MASTER_POST)
	->orderBy('id', 'ASC')
	->get(1)
	->getRowArray();
if (!$head) {
	$head = $db->table('staffs')
		->where('school_id', TARGET_SCHOOL_ID)
		->where('email', LOGIN_EMAIL)
		->get(1)
		->getRowArray();
}
if (!$head) {
	$rows = $db->table('staffs')->where('school_id', TARGET_SCHOOL_ID)->get()->getResultArray();
	foreach ($rows as $row) {
		$key = compact_name(trim(($row['fname'] ?? '') . ' ' . ($row['lname'] ?? '')));
		if ($key === 'JEREMIESEMAGOLI' || $key === 'JEREMIESEMAGORI') {
			$head = $row;
			break;
		}
	}
}

$now = date('Y-m-d H:i:s');
$hash = password_hash(DEFAULT_PASSWORD, PASSWORD_DEFAULT);
$taken = $db->table('staffs')->where('email', LOGIN_EMAIL)->get(1)->getRowArray();
$moved = '';
$created = false;

$db->transStart();

if ($taken && (!$head || (int) $taken['id'] !== (int) $head['id'])) {
	if ((int) ($taken['school_id'] ?? 0) !== TARGET_SCHOOL_ID) {
		$db->transRollback();
		fwrite(STDERR, "Refusing: " . LOGIN_EMAIL . " is already used by another school\n");
		exit(1);
	}
	$slug = strtolower(preg_replace('/[^a-z0-9]+/i', '.', trim(($taken['fname'] ?? '') . '.' . ($taken['lname'] ?? ''))) ?? 'staff');
	$alt = trim($slug, '.') . '@wisdomschoolkanzenze.rw';
	$clash = $db->table('staffs')->where('email', $alt)->where('id !=', (int) $taken['id'])->get(1)->getRowArray();
	if ($clash) {
		$alt = 's39.' . trim($slug, '.') . '@wisdomschoolkanzenze.rw';
	}
	$db->table('staffs')->where('id', (int) $taken['id'])->where('school_id', TARGET_SCHOOL_ID)->update([
		'email' => $alt,
		'updated_at' => $now,
	]);
	$moved = trim(($taken['fname'] ?? '') . ' ' . ($taken['lname'] ?? '')) . ' -> ' . $alt;
}

if (!$head) {
	$db->table('staffs')->insert([
		'school_id' => TARGET_SCHOOL_ID,
		'fname' => 'JEREMIE',
		'lname' => 'SEMAGOLI',
		'phone' => '0784327527',
		'password' => $hash,
		'status' => 1,
		'last_login' => 0,
		'email' => LOGIN_EMAIL,
		'post' => HEAD_MASTER_POST,
		'shift_id' => 0,
		'country' => 'Rwanda',
		'city' => 'Kanzenze',
		'address' => 'Kanzenze, Rwanda',
		'photo' => '',
		'lang' => 'en',
		'next_login' => 0,
		'reset_exp' => 0,
		'created_at' => $now,
		'created_by' => 1,
		'updated_at' => $now,
		'updated_by' => 1,
		'updateVersion' => 1,
	]);
	$headId = (int) $db->insertID();
	$created = true;
} else {
	$headId = (int) $head['id'];
	$update = [
		'email' => LOGIN_EMAIL,
		'password' => $hash,
		'status' => 1,
		'reset_exp' => 0,
		'updated_at' => $now,
	];
	if ((int) ($head['post'] ?? 0) <= 0) {
		$update['post'] = HEAD_MASTER_POST;
	}
	$db->table('staffs')
		->where('id', $headId)
		->where('school_id', TARGET_SCHOOL_ID)
		->update($update);
}

$post = $db->table('posts')->where('id', HEAD_MASTER_POST)->get(1)->getRowArray();
if (!$post) {
	$db->transRollback();
	fwrite(STDERR, "Refusing: head-teacher post is missing, so login cannot find the user\n");
	exit(1);
}
if ((int) ($post['status'] ?? 0) !== 1) {
	$db->table('posts')->where('id', HEAD_MASTER_POST)->update(['status' => 1]);
}

$schoolUpdate = [
	'email' => LOGIN_EMAIL,
	'head_master' => 'JEREMIE SEMAGOLI',
	'updated_at' => $now,
];
if ((int) ($school['status'] ?? 0) === 0) {
	$schoolUpdate['status'] = 1;
}
$db->table('schools')->where('id', TARGET_SCHOOL_ID)->update($schoolUpdate);

if ($dryRun) {
	$db->transRollback();
	say('DRY RUN — no database changes committed');
} else {
	$db->transComplete();
	if (!$db->transStatus()) {
		fwrite(STDERR, "Kanzenze login update failed\n");
		exit(1);
	}
	$saved = $db->table('staffs')->where('id', $headId)->get(1)->getRowArray();
	$ok = $saved && password_verify(DEFAULT_PASSWORD, (string) ($saved['password'] ?? ''));
	if (!$ok || strtolower((string) ($saved['email'] ?? '')) !== LOGIN_EMAIL) {
		fwrite(STDERR, "Kanzenze login was not saved\n");
		exit(1);
	}
}

say('School: ' . ($school['name'] ?? '') . ' (id ' . TARGET_SCHOOL_ID . ')');
say($created ? 'Created head teacher login' : 'Updated head teacher: ' . trim(($head['fname'] ?? '') . ' ' . ($head['lname'] ?? '')));
say('Previous email: ' . ($head['email'] ?? '(none)'));
say('Login email: ' . LOGIN_EMAIL);
say('Password set: yes');
if ($moved !== '') {
	say('Moved duplicate email: ' . $moved);
}

exit(0);
