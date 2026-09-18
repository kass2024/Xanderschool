<?php
/**
 * Import WISDOM SCHOOL MUYUMBU only (WIS-MUY / school 37).
 *
 * Source: deploy/_wisdom_muyumbu_school_information.json (parsed from
 * C:\methode\15 Wisdoms\11.Wisdom Muyumbu). Classes are REB
 * (faculty type 2) using Wisdom Rwanda level names:
 * Baby class, Middle Class, Top Class, P1–P6.
 * Every student is Day (studying_mode = 1).
 *
 * Usage:
 *   php deploy/import_wisdom_muyumbu_school_information.php --dry-run
 *   php deploy/import_wisdom_muyumbu_school_information.php
 */
declare(strict_types=1);

define('FCPATH', __DIR__ . '/../public/');
chdir(FCPATH);
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/Config/Paths.php';

$paths = new Config\Paths();
require rtrim($paths->systemDirectory, '/ ') . '/bootstrap.php';

helper('qonics');

$db = \Config\Database::connect();
$jsonPath = __DIR__ . '/_wisdom_muyumbu_school_information.json';
$dryRun = in_array('--dry-run', $argv ?? [], true);
$photoDir = rtrim((string) (getenv('PHOTO_DIR') ?: (WRITEPATH . 'staff_photos_import_muyumbu')), '/\\') . DIRECTORY_SEPARATOR;

if (!is_file($jsonPath)) {
	fwrite(STDERR, "Missing {$jsonPath}\n");
	exit(1);
}

$data = json_decode((string) file_get_contents($jsonPath), true);
if (!is_array($data) || !is_array($data['school'] ?? null)
	|| !is_array($data['classes'] ?? null) || !is_array($data['staff'] ?? null)) {
	fwrite(STDERR, "Invalid Muyumbu import JSON\n");
	exit(1);
}

const TARGET_ACRONYM = 'WIS-MUY';
const TARGET_SCHOOL_ID = 37;
const ACADEMIC_YEAR_TITLE = '2026-2027';
const CREATED_BY = 1;
const DEFAULT_VILLAGE_ID = 1202;
const YEAR_CODE = '26';
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

function clean_text($value, int $max = 100): string
{
	$text = trim(preg_replace('/\s+/', ' ', (string) ($value ?? '')) ?? '');
	return substr($text, 0, $max);
}

function name_tokens(string $value): array
{
	$normalized = preg_replace('/[^A-Z0-9 ]/', ' ', strtoupper($value)) ?? '';
	$parts = preg_split('/\s+/', $normalized) ?: [];
	$parts = array_values(array_filter($parts, static fn ($p) => $p !== ''));
	sort($parts);
	return $parts;
}

function post_id(\CodeIgniter\Database\BaseConnection $db, string $title): int
{
	$row = $db->table('posts')->where('title', $title)->get(1)->getRowArray();
	if ($row) {
		if ((int) ($row['status'] ?? 0) !== 1) {
			$db->table('posts')->where('id', (int) $row['id'])->update(['status' => 1]);
		}
		return (int) $row['id'];
	}
	$db->table('posts')->insert(['title' => $title, 'status' => 1]);
	return (int) $db->insertID();
}

function position_post(\CodeIgniter\Database\BaseConnection $db, string $position): int
{
	$position = strtoupper(clean_text($position));
	if (strpos($position, 'HEAD') !== false) {
		return HEAD_MASTER_POST;
	}
	if (strpos($position, 'DOS') !== false || strpos($position, 'DIRECTOR OF STUDIES') !== false) {
		return post_id($db, 'Director of studies');
	}
	if (strpos($position, 'ACCOUNTANT') !== false) {
		return post_id($db, 'Accountant');
	}
	return post_id($db, 'Teacher');
}

function default_dob(string $classLabel): string
{
	$years = [
		'BABY CLASS' => 2022,
		'MIDDLE CLASS' => 2021,
		'TOP CLASS' => 2020,
		'P1' => 2019,
		'P2' => 2018,
		'P3' => 2017,
		'P4' => 2016,
		'P5' => 2015,
		'P6' => 2014,
	];
	return sprintf('%d-01-01', $years[strtoupper(trim($classLabel))] ?? 2000);
}

function normalized_year_title(string $title): string
{
	return preg_replace('/[^0-9]/', '', $title) ?? '';
}

function regno(\CodeIgniter\Database\BaseConnection $db, int &$next): string
{
	do {
		$value = YEAR_CODE . sprintf('%03d', TARGET_SCHOOL_ID) . sprintf('%04d', $next++);
		$exists = $db->table('students')
			->where('school_id', TARGET_SCHOOL_ID)
			->where('regno', $value)
			->countAllResults();
	} while ($exists > 0);
	return $value;
}

function find_reb_level(\CodeIgniter\Database\BaseConnection $db, string $classLabel): array
{
	$aliases = [
		'BABY CLASS' => ['Baby class', 'Baby Class', 'N1'],
		'MIDDLE CLASS' => ['Middle Class', 'Middle class', 'N2'],
		'TOP CLASS' => ['Top Class', 'Top class', 'N3'],
	];
	$titles = $aliases[strtoupper(trim($classLabel))] ?? [strtoupper(trim($classLabel))];
	foreach ($titles as $title) {
		$row = $db->table('levels l')
			->select('l.id, l.title')
			->join('faculty f', 'f.id = l.faculty_id', 'left')
			->where('l.status', 1)
			->groupStart()
				->where('l.title', $title)
				->orWhere('UPPER(TRIM(l.title))', strtoupper($title))
			->groupEnd()
			->groupStart()
				->where('f.type', 2)
				->orWhere('l.type', 2)
				->orWhere('l.faculty_id IS NULL', null, false)
			->groupEnd()
			->get(1)
			->getRowArray();
		if ($row) {
			return $row;
		}
		$row = $db->table('levels')
			->select('id, title')
			->where('status', 1)
			->where('UPPER(TRIM(title))', strtoupper($title))
			->get(1)
			->getRowArray();
		if ($row) {
			return $row;
		}
	}
	throw new RuntimeException('REB level not found for ' . $classLabel . ' (tried ' . implode(', ', $titles) . ')');
}

function find_reb_department(\CodeIgniter\Database\BaseConnection $db, string $classLabel): array
{
	$wanted = in_array(strtoupper(trim($classLabel)), ['BABY CLASS', 'MIDDLE CLASS', 'TOP CLASS', 'N1', 'N2', 'N3'], true)
		? ['Nursery', 'NURSERY']
		: ['Primary', 'PRIMARY'];
	foreach ($wanted as $title) {
		$row = $db->table('departments d')
			->select('d.id, d.title')
			->join('faculty f', 'f.id = d.faculty_id', 'left')
			->where('d.title', $title)
			->where('f.type', 2)
			->get(1)
			->getRowArray();
		if ($row) {
			return $row;
		}
	}
	throw new RuntimeException('REB department not found for ' . $classLabel);
}

function ensure_class(
	\CodeIgniter\Database\BaseConnection $db,
	string $classLabel,
	int $mentor,
	string $now
): array {
	$level = find_reb_level($db, $classLabel);
	$department = find_reb_department($db, $classLabel);

	$existing = $db->table('classes')
		->where('school_id', TARGET_SCHOOL_ID)
		->where('level', (int) $level['id'])
		->where('department', (int) $department['id'])
		->where('title', '')
		->get(1)
		->getRowArray();
	if ($existing) {
		return ['id' => (int) $existing['id'], 'created' => false, 'label' => (string) $level['title']];
	}

	$any = $db->table('classes c')
		->select('c.id, c.title, l.title AS level_title')
		->join('levels l', 'l.id = c.level')
		->where('c.school_id', TARGET_SCHOOL_ID)
		->where('c.level', (int) $level['id'])
		->get()
		->getResultArray();
	foreach ($any as $row) {
		if (stripos((string) ($row['title'] ?? ''), 'holiday') !== false) {
			continue;
		}
		return ['id' => (int) $row['id'], 'created' => false, 'label' => (string) $row['level_title']];
	}

	$db->table('classes')->insert([
		'school_id' => TARGET_SCHOOL_ID,
		'level' => (int) $level['id'],
		'department' => (int) $department['id'],
		'title' => '',
		'mentor' => $mentor,
		'created_at' => $now,
		'created_by' => CREATED_BY,
		'updated_at' => $now,
		'updated_by' => CREATED_BY,
	]);
	return ['id' => (int) $db->insertID(), 'created' => true, 'label' => (string) $level['title']];
}

function enroll(
	\CodeIgniter\Database\BaseConnection $db,
	int $studentId,
	int $classId,
	int $academicYearId
): void {
	$db->table('class_records')
		->where('student', $studentId)
		->where('year', (string) $academicYearId)
		->where('status', 1)
		->update(['status' => 0]);

	$record = $db->table('class_records')
		->where('student', $studentId)
		->where('year', (string) $academicYearId)
		->where('class', $classId)
		->get(1)
		->getRowArray();
	if ($record) {
		$db->table('class_records')->where('id', (int) $record['id'])->update(['status' => 1]);
		return;
	}
	$db->table('class_records')->insert([
		'student' => $studentId,
		'year' => (string) $academicYearId,
		'class' => $classId,
		'status' => 1,
	]);
}

function token_close(string $a, string $b): bool
{
	if ($a === $b) {
		return true;
	}
	$a = str_replace(['NYORHA', 'NYORA'], 'NYOR', $a);
	$b = str_replace(['NYORHA', 'NYORA'], 'NYOR', $b);
	$a = str_replace(['DAMASEN', 'DAMSEN'], 'DAMS', $a);
	$b = str_replace(['DAMASEN', 'DAMSEN'], 'DAMS', $b);
	$a = str_replace(['KEVINAH'], 'KEVIN', $a);
	$b = str_replace(['KEVINAH'], 'KEVIN', $b);
	$a = str_replace(['KALINIJABO', 'KARINIJABO'], 'KALINI', $a);
	$b = str_replace(['KALINIJABO', 'KARINIJABO'], 'KALINI', $b);
	$a = str_replace(['MONIQUE', 'MONIC'], 'MONIC', $a);
	$b = str_replace(['MONIQUE', 'MONIC'], 'MONIC', $b);
	$a = str_replace(['NSYIMYUMUREMYI', 'NSHIMIYIMANA', 'NSHIMYUMUREMYI'], 'NSHIMI', $a);
	$b = str_replace(['NSYIMYUMUREMYI', 'NSHIMIYIMANA', 'NSHIMYUMUREMYI'], 'NSHIMI', $b);
	if ($a === $b) {
		return true;
	}
	return strlen($a) >= 4 && strlen($b) >= 4 && strncmp($a, $b, 4) === 0;
}

function name_key(string $value): string
{
	$key = compact_name($value);
	$key = str_replace(['NYORHA', 'NYORA'], 'NYOR', $key);
	$key = str_replace(['DAMASEN', 'DAMSEN'], 'DAMS', $key);
	$key = str_replace(['KALINIJABO', 'KARINIJABO'], 'KALINI', $key);
	$key = str_replace(['MONIQUE'], 'MONIC', $key);
	$key = str_replace(['NSYIMYUMUREMYI', 'NSHIMIYIMANA', 'NSHIMYUMUREMYI'], 'NSHIMI', $key);
	return $key;
}

function collect_photos(string $photoDir, array $fromJson): array
{
	$photos = [];
	foreach ($fromJson as $photo) {
		if (is_array($photo) && !empty($photo['file'])) {
			$hint = (string) ($photo['name_hint'] ?? pathinfo((string) $photo['file'], PATHINFO_FILENAME));
			$photos[(string) $photo['file']] = ['file' => (string) $photo['file'], 'name_hint' => $hint];
		}
	}
	if (is_dir($photoDir)) {
		foreach (scandir($photoDir) ?: [] as $file) {
			if (!preg_match('/\.(jpe?g|png|webp)$/i', $file)) {
				continue;
			}
			$photos[$file] = ['file' => $file, 'name_hint' => pathinfo($file, PATHINFO_FILENAME)];
		}
	}
	return array_values($photos);
}

function match_photo(array $photos, string $fullName): ?array
{
	$want = name_tokens($fullName);
	$wantKey = name_key($fullName);
	$best = null;
	$bestScore = 0.0;
	foreach ($photos as $photo) {
		if (!is_array($photo)) {
			continue;
		}
		$hint = (string) ($photo['name_hint'] ?? pathinfo((string) ($photo['file'] ?? ''), PATHINFO_FILENAME));
		$have = name_tokens($hint);
		$haveKey = name_key($hint);
		if ($wantKey !== '' && $haveKey !== '' && ($wantKey === $haveKey
			|| strpos($haveKey, $wantKey) !== false || strpos($wantKey, $haveKey) !== false)) {
			return $photo;
		}
		if ($have === []) {
			continue;
		}
		$overlap = 0;
		foreach ($want as $w) {
			foreach ($have as $h) {
				if (token_close($w, $h)) {
					$overlap++;
					break;
				}
			}
		}
		$score = $overlap / max(count($want), count($have), 1);
		if ($overlap >= 2 && $score > $bestScore) {
			$bestScore = $score;
			$best = $photo;
		} elseif ($overlap === count($want) && $overlap === count($have) && $score > $bestScore) {
			$bestScore = $score;
			$best = $photo;
		} elseif ($overlap === 1 && $score > $bestScore) {
			$long = false;
			foreach ($want as $w) {
				if (strlen($w) < 6) {
					continue;
				}
				foreach ($have as $h) {
					if (token_close($w, $h)) {
						$long = true;
						break;
					}
				}
			}
			if ($long) {
				$bestScore = $score;
				$best = $photo;
			}
		}
	}
	return $bestScore >= 0.45 ? $best : null;
}

$school = $db->table('schools')->where('id', TARGET_SCHOOL_ID)->get(1)->getRowArray();
if (!$school || strtoupper((string) ($school['acronym'] ?? '')) !== TARGET_ACRONYM) {
	$school = $db->table('schools')->where('acronym', TARGET_ACRONYM)->get(1)->getRowArray();
}
if (!$school || (int) $school['id'] !== TARGET_SCHOOL_ID) {
	fwrite(STDERR, "Target Muyumbu school was not found at expected school id 37 / WIS-MUY\n");
	exit(1);
}
$schoolName = compact_name((string) ($school['name'] ?? ''));
if (strpos($schoolName, 'MUYUMBU') === false || strpos($schoolName, 'SUSA') !== false
	|| strpos($schoolName, 'NYABIHU') !== false || strpos($schoolName, 'BURERA') !== false
	|| strpos($schoolName, 'NGORORERO') !== false || strpos($schoolName, 'RWANDA') !== false
	|| strpos($schoolName, 'KAYONZA') !== false) {
	fwrite(STDERR, "Refusing to import: school id 37 is not Wisdom Muyumbu (" . ($school['name'] ?? '') . ")\n");
	exit(1);
}

$year = null;
$activeTerm = $db->table('active_term')
	->where('id', (int) ($school['active_term'] ?? 0))
	->where('school_id', TARGET_SCHOOL_ID)
	->get(1)
	->getRowArray();
$years = $db->table('academic_year')
	->where('school_id', TARGET_SCHOOL_ID)
	->orderBy('id', 'ASC')
	->get()
	->getResultArray();
foreach ($years as $candidate) {
	if (normalized_year_title((string) $candidate['title']) === normalized_year_title(ACADEMIC_YEAR_TITLE)) {
		$year = $candidate;
		if ((int) ($activeTerm['academic_year'] ?? 0) === (int) $candidate['id']) {
			break;
		}
	}
}

$now = date('Y-m-d H:i:s');
$db->transStart();

if (!$year) {
	$db->table('academic_year')->insert([
		'school_id' => TARGET_SCHOOL_ID,
		'title' => ACADEMIC_YEAR_TITLE,
		'created_at' => $now,
		'updated_at' => $now,
	]);
	$academicYearId = (int) $db->insertID();
} else {
	$academicYearId = (int) $year['id'];
}

$schoolInfo = $data['school'];
$schoolUpdate = [
	'name' => clean_text($schoolInfo['name'] ?? 'WISDOM SCHOOL MUYUMBU', 100),
	'slogan' => clean_text($schoolInfo['slogan'] ?? '', 100),
	'address' => 'Muyumbu, Rwanda',
	'head_master' => clean_text($schoolInfo['head_teacher'] ?? 'KWIRINGIRA HENRY', 100),
	'updated_at' => $now,
];
if ($activeTerm) {
	$db->table('active_term')
		->where('id', (int) $activeTerm['id'])
		->where('school_id', TARGET_SCHOOL_ID)
		->update(['academic_year' => (string) $academicYearId]);
} else {
	$db->table('active_term')->insert([
		'school_id' => TARGET_SCHOOL_ID,
		'academic_year' => (string) $academicYearId,
		'term' => 1,
		'sms_usage' => 1,
		'created_by' => CREATED_BY,
		'created_at' => $now,
		'updated_at' => $now,
	]);
	$schoolUpdate['active_term'] = (int) $db->insertID();
}
$db->table('schools')->where('id', TARGET_SCHOOL_ID)->update($schoolUpdate);

$staffByName = [];
$staffByRole = [];
$staffCreated = 0;
$staffUpdated = 0;
$photosSaved = 0;
$photos = collect_photos($photoDir, $data['photos'] ?? []);
say('Photo files available: ' . count($photos));

foreach ($data['staff'] as $staff) {
	$fname = clean_text($staff['fname'] ?? '');
	$lname = clean_text($staff['lname'] ?? '');
	$fullName = trim($fname . ' ' . $lname);
	if ($fullName === '') {
		continue;
	}
	$phone = clean_text($staff['phone'] ?? '', 20);
	$email = strtolower(clean_text($staff['email'] ?? '', 100));
	$post = position_post($db, (string) ($staff['position'] ?? ''));
	$existing = null;
	if ($post === HEAD_MASTER_POST) {
		$existing = $db->table('staffs')
			->where('school_id', TARGET_SCHOOL_ID)
			->where('post', HEAD_MASTER_POST)
			->orderBy('id', 'ASC')
			->get(1)
			->getRowArray();
	}
	if (!$existing) {
		$existing = $db->table('staffs')
			->where('school_id', TARGET_SCHOOL_ID)
			->groupStart()
				->where('fname', $fname)
				->where('lname', $lname)
			->groupEnd()
			->get(1)
			->getRowArray();
	}
	if (!$existing && $phone !== '') {
		$existing = $db->table('staffs')
			->where('school_id', TARGET_SCHOOL_ID)
			->where('phone', $phone)
			->get(1)
			->getRowArray();
	}

	$photoName = '';
	$matchedPhoto = match_photo($photos, $fullName);
	if (!$matchedPhoto) {
		say('PHOTO UNMATCHED ' . $fullName);
	}
	if ($matchedPhoto) {
		say('PHOTO MATCH ' . $fullName . ' <- ' . ($matchedPhoto['file'] ?? ''));
		$src = $photoDir . ($matchedPhoto['file'] ?? '');
		if (is_file($src)) {
			$photoName = function_exists('make_profile_photo_name')
				? make_profile_photo_name('jpg')
				: ('img_' . bin2hex(random_bytes(8)) . '.jpg');
			$dest = FCPATH . 'assets/images/profile/' . $photoName;
			if (!is_dir(FCPATH . 'assets/images/profile/')) {
				mkdir(FCPATH . 'assets/images/profile/', 0775, true);
			}
			$saved = function_exists('save_profile_photo_white_bg')
				? save_profile_photo_white_bg($src, $dest, true)
				: false;
			if (!$saved) {
				$saved = @copy($src, $dest);
			}
			if (!$saved) {
				say('PHOTO FAIL ' . $fullName . ' <- ' . ($matchedPhoto['file'] ?? ''));
				$photoName = '';
			} else {
				$photosSaved++;
				if ($existing) {
					$oldPhoto = basename((string) ($existing['photo'] ?? ''));
					if ($oldPhoto !== '' && $oldPhoto !== $photoName) {
						$oldPath = FCPATH . 'assets/images/profile/' . $oldPhoto;
						if (is_file($oldPath)) {
							@unlink($oldPath);
						}
					}
				}
			}
		}
	}

	$payload = [
		'fname' => $fname,
		'lname' => $lname,
		'phone' => $phone,
		'post' => $post,
		'status' => 1,
		'updated_at' => $now,
		'updated_by' => CREATED_BY,
	];
	if ($email !== '' && !$existing) {
		$payload['email'] = $email;
	} elseif ($email !== '' && $existing && strpos((string) ($existing['email'] ?? ''), '@wisdomschools.rw') === false) {
		$payload['email'] = $email;
	}
	if ($photoName !== '') {
		$payload['photo'] = $photoName;
	}

	if ($existing) {
		$staffId = (int) $existing['id'];
		$db->table('staffs')->where('id', $staffId)->where('school_id', TARGET_SCHOOL_ID)->update($payload);
		$staffUpdated++;
	} else {
		if ($email === '') {
			$slug = strtolower(preg_replace('/[^a-z0-9]+/i', '.', $fullName) ?? 'staff');
			$email = trim($slug, '.') . '@wisdomschoolmuyumbu.rw';
		}
		$taken = $db->table('staffs')->where('email', $email)->get(1)->getRowArray();
		if ($taken) {
			$email = 's37.' . preg_replace('/[^a-z0-9]+/i', '.', strtolower($fullName)) . '@wisdomschoolmuyumbu.rw';
		}
		$db->table('staffs')->insert([
			'school_id' => TARGET_SCHOOL_ID,
			'fname' => $fname,
			'lname' => $lname,
			'phone' => $phone,
			'password' => password_hash(DEFAULT_PASSWORD, PASSWORD_DEFAULT),
			'status' => 1,
			'last_login' => 0,
			'email' => $email,
			'post' => $post,
			'shift_id' => 0,
			'country' => 'Rwanda',
			'city' => 'Muyumbu',
			'address' => 'Muyumbu, Rwanda',
			'photo' => $photoName,
			'lang' => 'en',
			'next_login' => 0,
			'reset_exp' => 0,
			'created_at' => $now,
			'created_by' => CREATED_BY,
			'updated_at' => $now,
			'updated_by' => CREATED_BY,
			'updateVersion' => 1,
		]);
		$staffId = (int) $db->insertID();
		$staffCreated++;
	}
	$staffByName[compact_name($fullName)] = $staffId;
	$staffByRole[strtoupper(clean_text($staff['position'] ?? ''))] = $staffId;
}

$mentor = $staffByRole['HEAD TEACHER'] ?? 0;
if ((int) $mentor <= 0) {
	$head = $db->table('staffs')
		->where('school_id', TARGET_SCHOOL_ID)
		->where('post', HEAD_MASTER_POST)
		->orderBy('id', 'ASC')
		->get(1)
		->getRowArray();
	$mentor = (int) ($head['id'] ?? 0);
}
if ((int) $mentor <= 0) {
	throw new RuntimeException('No staff member is available to mentor the classes');
}

$classCache = [];
$classesCreated = 0;
$studentCreated = 0;
$studentUpdated = 0;
$enrolled = 0;
$counterRow = $db->table('reg_number')
	->where('school_id', TARGET_SCHOOL_ID)
	->where('academic_year', YEAR_CODE)
	->get(1)
	->getRowArray();
$nextReg = (int) ($counterRow['next_number'] ?? 1);
$startReg = $nextReg;

$existingStudents = $db->table('students')
	->where('school_id', TARGET_SCHOOL_ID)
	->get()
	->getResultArray();
$studentsByName = [];
foreach ($existingStudents as $existingStudent) {
	$key = compact_name(trim($existingStudent['fname'] . ' ' . $existingStudent['lname']));
	if ($key !== '') {
		$studentsByName[$key] = $existingStudent;
	}
}

foreach ($data['classes'] as $student) {
	$classLabel = strtoupper(clean_text($student['class_label'] ?? ''));
	$fullName = clean_text($student['full_name'] ?? '');
	if ($classLabel === '' || $fullName === '') {
		continue;
	}
	if (!isset($classCache[$classLabel])) {
		$classCache[$classLabel] = ensure_class($db, $classLabel, (int) $mentor, $now);
		if ($classCache[$classLabel]['created']) {
			$classesCreated++;
		}
	}
	$classId = (int) $classCache[$classLabel]['id'];
	$fname = clean_text($student['fname'] ?? '');
	$lname = clean_text($student['lname'] ?? '');
	$key = compact_name($fullName);
	$existing = $studentsByName[$key] ?? null;

	if ($existing) {
		$studentId = (int) $existing['id'];
		$db->table('students')->where('id', $studentId)->where('school_id', TARGET_SCHOOL_ID)->update([
			'status' => 1,
			'studying_mode' => 1,
			'updated_at' => $now,
			'updated_by' => CREATED_BY,
		]);
		$studentUpdated++;
	} else {
		$studentRegno = regno($db, $nextReg);
		$db->table('students')->insert([
			'school_id' => TARGET_SCHOOL_ID,
			'fname' => $fname !== '' ? $fname : 'UNKNOWN',
			'lname' => $lname,
			'phone' => '',
			'email' => '',
			'regno' => $studentRegno,
			'sex' => 'U',
			'dob' => default_dob($classLabel),
			'photo' => '',
			'village_id' => DEFAULT_VILLAGE_ID,
			'studying_mode' => 1,
			'religion' => '',
			'nationality' => 'rwanda',
			'card' => '',
			'transport_money' => 0,
			'wallet_balance' => 0,
			'father' => '',
			'ft_phone' => '',
			'mother' => '',
			'mt_phone' => '',
			'guardian' => '',
			'gd_phone' => '',
			'created_at' => $now,
			'created_by' => CREATED_BY,
			'updated_at' => $now,
			'updated_by' => CREATED_BY,
			'status' => 1,
			'updateVersion' => 1,
		]);
		$studentId = (int) $db->insertID();
		$studentCreated++;
		$studentsByName[$key] = [
			'id' => $studentId,
			'fname' => $fname,
			'lname' => $lname,
		];
	}
	enroll($db, $studentId, $classId, $academicYearId);
	$enrolled++;
}

if ($nextReg > $startReg) {
	if ($counterRow) {
		$db->table('reg_number')->where('id', (int) $counterRow['id'])
			->update(['next_number' => $nextReg]);
	} else {
		$db->table('reg_number')->insert([
			'school_id' => TARGET_SCHOOL_ID,
			'academic_year' => YEAR_CODE,
			'next_number' => $nextReg,
		]);
	}
}

$db->table('students')
	->where('school_id', TARGET_SCHOOL_ID)
	->update(['studying_mode' => 1]);
$dayCount = $db->table('students')
	->where('school_id', TARGET_SCHOOL_ID)
	->where('studying_mode', 1)
	->countAllResults();

try {
	(new \App\Services\SchoolHierarchyService())->seedWisdomMasterGroup();
} catch (\Throwable $e) {
	say('Hierarchy note: ' . $e->getMessage());
}

if ($dryRun) {
	$db->transRollback();
	say('DRY RUN — no database changes committed');
} else {
	$db->transComplete();
	if (!$db->transStatus()) {
		fwrite(STDERR, "Import transaction failed\n");
		exit(1);
	}
}

say('School: ' . ($school['name'] ?? TARGET_ACRONYM) . ' (id ' . TARGET_SCHOOL_ID . ')');
say('Academic year: ' . ACADEMIC_YEAR_TITLE . ' (id ' . $academicYearId . ')');
say('Staff created: ' . $staffCreated . '; updated: ' . $staffUpdated);
say('Staff photos cropped on white: ' . $photosSaved);
say('Classes created: ' . $classesCreated);
say('Students created: ' . $studentCreated . '; existing reactivated: ' . $studentUpdated);
say('Enrollments ensured: ' . $enrolled);
say('Studying mode Day: ' . $dayCount . ' students');
say('Source students: ' . count($data['classes']) . '; source staff: ' . count($data['staff']));
if (!empty($data['skipped'])) {
	foreach ($data['skipped'] as $skip) {
		say('SKIPPED ' . ($skip['sheet'] ?? '') . ': ' . ($skip['reason'] ?? '') . ' (' . count($skip['students'] ?? []) . ' names)');
	}
}

exit(0);
