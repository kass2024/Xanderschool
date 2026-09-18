<?php
/**
 * Set WISDOM SCHOOL KAYONZA (school 35 / WSY) login to
 * umutonihenry@gmail.com with the default child password.
 *
 * Only this school is touched.
 *
 * Usage:
 *   php deploy/set_wisdom_kayonza_login.php --dry-run
 *   php deploy/set_wisdom_kayonza_login.php
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

const TARGET_SCHOOL_ID = 35;
const LOGIN_EMAIL = 'umutonihenry@gmail.com';
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

$school = $db->table('schools')->where('id', TARGET_SCHOOL_ID)->get(1)->getRowArray();
$schoolName = compact_name((string) ($school['name'] ?? ''));
if (!$school || strpos($schoolName, 'KAYONZA') === false || (int) $school['id'] !== TARGET_SCHOOL_ID) {
	fwrite(STDERR, "Refusing: school id 35 is not Wisdom Kayonza\n");
	exit(1);
}

$head = $db->table('staffs')
	->where('school_id', TARGET_SCHOOL_ID)
	->where('post', HEAD_MASTER_POST)
	->where('status', 1)
	->orderBy('id', 'ASC')
	->get(1)
	->getRowArray();
if (!$head) {
	fwrite(STDERR, "No Kayonza head-teacher login account found\n");
	exit(1);
}

$now = date('Y-m-d H:i:s');
$taken = $db->table('staffs')->where('email', LOGIN_EMAIL)->get(1)->getRowArray();
$moved = '';

$db->transStart();

if ($taken && (int) $taken['id'] !== (int) $head['id']) {
	if ((int) ($taken['school_id'] ?? 0) !== TARGET_SCHOOL_ID) {
		$db->transRollback();
		fwrite(STDERR, "Refusing: " . LOGIN_EMAIL . " is already used by another school\n");
		exit(1);
	}
	$slug = strtolower(preg_replace('/[^a-z0-9]+/i', '.', trim(($taken['fname'] ?? '') . '.' . ($taken['lname'] ?? ''))) ?? 'staff');
	$alt = trim($slug, '.') . '@wisdomschoolkayonza.rw';
	$clash = $db->table('staffs')->where('email', $alt)->where('id !=', (int) $taken['id'])->get(1)->getRowArray();
	if ($clash) {
		$alt = 's35.' . trim($slug, '.') . '@wisdomschoolkayonza.rw';
	}
	$db->table('staffs')->where('id', (int) $taken['id'])->where('school_id', TARGET_SCHOOL_ID)->update([
		'email' => $alt,
		'updated_at' => $now,
	]);
	$moved = trim(($taken['fname'] ?? '') . ' ' . ($taken['lname'] ?? '')) . ' -> ' . $alt;
}

$db->table('staffs')
	->where('id', (int) $head['id'])
	->where('school_id', TARGET_SCHOOL_ID)
	->update([
		'email' => LOGIN_EMAIL,
		'password' => password_hash(DEFAULT_PASSWORD, PASSWORD_DEFAULT),
		'status' => 1,
		'reset_exp' => 0,
		'updated_at' => $now,
	]);

$db->table('schools')->where('id', TARGET_SCHOOL_ID)->update([
	'email' => LOGIN_EMAIL,
	'updated_at' => $now,
]);

$oldAdmin = $db->table('staffs')
	->where('school_id', TARGET_SCHOOL_ID)
	->where('email', 'admin.kay@wisdomschools.rw')
	->where('id !=', (int) $head['id'])
	->get(1)
	->getRowArray();
$disabledOld = '';
if ($oldAdmin) {
	$db->table('staffs')
		->where('id', (int) $oldAdmin['id'])
		->where('school_id', TARGET_SCHOOL_ID)
		->update([
			'status' => 0,
			'updated_at' => $now,
		]);
	$disabledOld = trim(($oldAdmin['fname'] ?? '') . ' ' . ($oldAdmin['lname'] ?? '')) . ' <admin.kay@wisdomschools.rw>';
}

if ($dryRun) {
	$db->transRollback();
	say('DRY RUN — no database changes committed');
} else {
	$db->transComplete();
	if (!$db->transStatus()) {
		fwrite(STDERR, "Kayonza login update failed\n");
		exit(1);
	}
}

say('School: ' . ($school['name'] ?? '') . ' (id ' . TARGET_SCHOOL_ID . ')');
say('Head teacher: ' . trim(($head['fname'] ?? '') . ' ' . ($head['lname'] ?? '')));
say('Previous email: ' . ($head['email'] ?? ''));
say('Login email: ' . LOGIN_EMAIL);
say('Password: default Wisdom child password');
if ($moved !== '') {
	say('Moved duplicate email: ' . $moved);
}
if ($disabledOld !== '') {
	say('Disabled old admin login: ' . $disabledOld);
}

exit(0);
