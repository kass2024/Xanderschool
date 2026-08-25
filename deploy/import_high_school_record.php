<?php
/**
 * Import High school record.xlsx students into Wisdom school 27 production classes.
 * Matches existing students by regno variants then name; updates in place; never duplicates.
 *
 * Dry run:  docker exec xander_school_app php /var/www/html/deploy/import_high_school_record.php
 * Execute:  docker exec xander_school_app php /var/www/html/deploy/import_high_school_record.php --execute
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
const ACADEMIC_YEAR_ID = 16;
const CREATED_BY = 1;
const YEAR_CODE = '26';
const DEFAULT_VILLAGE_ID = 1202;

/** Fallback class ids (school 27). Dynamic resolve by level+dept is preferred. */
const CLASS_ID_FALLBACK = [
	'S2' => 190,
	'S3' => 191,
	'S5 ACC' => 224,
	'S5 ANP' => 230,
	'S5 ST1' => 226,
	'S5 ST2' => 228,
	'S6 ANP' => 231,
	'S6 MCB' => 195,
	'S6 MCE' => 196,
	'S6 MEG' => 198,
	'S6 MPC' => 193,
	'S6 MPG' => 197,
	'S6 PCB' => 232,
	'S6 PCM' => 194,
];

$execute = in_array('--execute', $argv ?? [], true);
$jsonPath = __DIR__ . '/_high_school_record_parsed.json';
if (!is_file($jsonPath)) {
	fwrite(STDERR, "Missing {$jsonPath}\n");
	exit(1);
}
$raw = json_decode((string) file_get_contents($jsonPath), true);
if (!is_array($raw) || empty($raw['students'])) {
	fwrite(STDERR, "Invalid JSON\n");
	exit(1);
}

function say(string $msg): void
{
	echo $msg . PHP_EOL;
}

function name_tokens(string $s): array
{
	preg_match_all('/[A-Za-z]+/', strtoupper($s), $m);
	$out = [];
	foreach ($m[0] as $t) {
		if (strlen($t) >= 2) {
			$out[$t] = true;
		}
	}
	return array_keys($out);
}

function compact_name(string $s): string
{
	return preg_replace('/[^A-Z]/', '', strtoupper($s)) ?? '';
}

function names_match(string $a, string $b): bool
{
	$ca = compact_name($a);
	$cb = compact_name($b);
	if ($ca !== '' && $ca === $cb) {
		return true;
	}
	$ta = name_tokens($a);
	$tb = name_tokens($b);
	if ($ta === [] || $tb === []) {
		return false;
	}
	if ($ta[0] !== $tb[0]) {
		return false;
	}
	$inter = array_values(array_intersect($ta, $tb));
	if (count($inter) >= 3) {
		return true;
	}
	$smaller = count($ta) <= count($tb) ? $ta : $tb;
	$larger = count($ta) <= count($tb) ? $tb : $ta;
	if (count($smaller) < 2) {
		return false;
	}
	foreach ($smaller as $t) {
		if (!in_array($t, $larger, true)) {
			return false;
		}
	}
	return true;
}

function normalize_phone(string $raw): string
{
	$d = preg_replace('/\D+/', '', $raw) ?? '';
	if ($d === '') {
		return '';
	}
	if (strpos($d, '250') === 0 && strlen($d) >= 12) {
		$d = '0' . substr($d, 3);
	}
	if (strlen($d) === 9 && $d[0] === '7') {
		$d = '0' . $d;
	}
	return $d;
}

function is_usable_dob(string $d): bool
{
	if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m)) {
		return false;
	}
	$y = (int) $m[1];
	return $y >= 2000 && $y <= 2018;
}

function is_usable_name(string $s): bool
{
	$s = trim($s);
	if (strlen($s) < 2 || !preg_match('/[A-Za-z]{2,}/', $s)) {
		return false;
	}
	$low = strtolower($s);
	return !in_array($low, ['none', 'n/a', 'null', 'test', 'unknown', '-'], true);
}

function default_dob(string $label): string
{
	if (preg_match('/^S[123]/', $label)) {
		return '2012-01-15';
	}
	return '2008-01-15';
}

function make_regno(\CodeIgniter\Database\BaseConnection $db, int &$nextSeq): string
{
	do {
		$regno = YEAR_CODE . sprintf('%03d', SCHOOL_ID) . sprintf('%04d', $nextSeq);
		$nextSeq++;
		$exists = $db->table('students')->where('school_id', SCHOOL_ID)->where('regno', $regno)->countAllResults();
	} while ($exists > 0);
	return $regno;
}

function bump_reg_counter(\CodeIgniter\Database\BaseConnection $db, int $nextNumber): void
{
	$row = $db->table('reg_number')
		->where('school_id', SCHOOL_ID)
		->where('academic_year', YEAR_CODE)
		->get(1)
		->getRowArray();
	if ($row) {
		if ($nextNumber > (int) ($row['next_number'] ?? 1)) {
			$db->table('reg_number')->where('id', (int) $row['id'])->update(['next_number' => $nextNumber]);
		}
		return;
	}
	$db->table('reg_number')->insert([
		'school_id' => SCHOOL_ID,
		'academic_year' => YEAR_CODE,
		'next_number' => $nextNumber,
	]);
}

/**
 * @return array{level:string,dept:string}
 */
function parse_class_label(string $label): array
{
	$s = strtoupper(trim(preg_replace('/\s+/', ' ', $label) ?? $label));
	if (preg_match('/^(S[2-6])(?:\s+([A-Z0-9]+))?$/', $s, $m)) {
		return ['level' => $m[1], 'dept' => trim($m[2] ?? '')];
	}
	return ['level' => '', 'dept' => ''];
}

function is_holiday_title(string $title): bool
{
	return stripos($title, 'Holiday') !== false;
}

/**
 * @param array<int,array<string,mixed>> $regular
 * @return array{id:int,label:string}|null
 */
function pick_olevel(array $regular): ?array
{
	if ($regular === []) {
		return null;
	}
	foreach ($regular as $row) {
		if (trim((string) $row['title']) === '') {
			$code = strtoupper((string) ($row['code'] ?? ''));
			$dept = strtoupper((string) ($row['dept_name'] ?? ''));
			if ($code === '' || strpos($dept, 'O') !== false || in_array($code, ['OLE', 'OLEVEL', 'OL', 'GEN', ''], true)) {
				return [
					'id' => (int) $row['id'],
					'label' => trim($row['level_name'] . ' ' . ($row['dept_name'] ?? '')),
				];
			}
		}
	}
	foreach ($regular as $row) {
		if (trim((string) $row['title']) === '') {
			return [
				'id' => (int) $row['id'],
				'label' => (string) $row['level_name'],
			];
		}
	}
	$row = $regular[0];
	return [
		'id' => (int) $row['id'],
		'label' => trim($row['level_name'] . ' ' . $row['title'] . ' ' . ($row['code'] ?? '')),
	];
}

/**
 * @return array{id:int,label:string}|null
 */
function resolve_class(\CodeIgniter\Database\BaseConnection $db, string $label): ?array
{
	$parsed = parse_class_label($label);
	$level = $parsed['level'];
	$deptCode = $parsed['dept'];
	if ($level === '') {
		return null;
	}

	$rows = $db->table('classes c')
		->select('c.id, c.title, d.code, d.title AS dept_name, l.title AS level_name')
		->join('levels l', 'l.id = c.level')
		->join('departments d', 'd.id = c.department', 'left')
		->where('c.school_id', SCHOOL_ID)
		->where('l.title', $level)
		->get()
		->getResultArray();

	$regular = [];
	foreach ($rows as $row) {
		if (is_holiday_title((string) $row['title'])) {
			continue;
		}
		$regular[] = $row;
	}

	$found = null;
	if (in_array($level, ['S2', 'S3'], true) && $deptCode === '') {
		$found = pick_olevel($regular);
	} else {
		$want = [$deptCode];
		if ($deptCode === 'ACC') {
			$want[] = 'ACCOUNTING';
		}
		foreach ($want as $code) {
			if ($code === '') {
				continue;
			}
			foreach ($regular as $row) {
				if (strcasecmp((string) ($row['code'] ?? ''), $code) === 0) {
					$found = [
						'id' => (int) $row['id'],
						'label' => trim($row['level_name'] . ' ' . ($row['code'] ?? '') . ' ' . ($row['dept_name'] ?? '')),
					];
					break 2;
				}
			}
		}
	}

	if ($found) {
		return $found;
	}

	$fallbackKey = $label;
	$fallbackId = CLASS_ID_FALLBACK[$fallbackKey] ?? CLASS_ID_FALLBACK[$label] ?? 0;
	if ($fallbackId > 0) {
		foreach ($regular as $row) {
			if ((int) $row['id'] === $fallbackId) {
				return [
					'id' => $fallbackId,
					'label' => trim($row['level_name'] . ' ' . ($row['code'] ?? '')),
				];
			}
		}
		$row = $db->table('classes c')
			->select('c.id, c.title, d.code, l.title AS level_name')
			->join('levels l', 'l.id = c.level')
			->join('departments d', 'd.id = c.department', 'left')
			->where('c.id', $fallbackId)
			->where('c.school_id', SCHOOL_ID)
			->get(1)
			->getRowArray();
		if ($row && !is_holiday_title((string) $row['title'])) {
			say("WARN resolve_class used fallback id {$fallbackId} for {$label}");
			return [
				'id' => $fallbackId,
				'label' => trim(($row['level_name'] ?? $label) . ' ' . ($row['code'] ?? '')),
			];
		}
	}
	return null;
}

function enroll(\CodeIgniter\Database\BaseConnection $db, int $studentId, int $classId, bool $execute): string
{
	$rows = $db->table('class_records cr')
		->select('cr.id, cr.class, cr.status, c.title')
		->join('classes c', 'c.id = cr.class')
		->where('cr.student', $studentId)
		->where('cr.year', (string) ACADEMIC_YEAR_ID)
		->get()
		->getResultArray();

	$already = false;
	foreach ($rows as $row) {
		if ((int) $row['class'] === $classId) {
			if ((int) ($row['status'] ?? 0) !== 1 && $execute) {
				$db->table('class_records')->where('id', (int) $row['id'])->update(['status' => 1]);
			}
			$already = true;
			continue;
		}
		if (is_holiday_title((string) $row['title'])) {
			continue;
		}
		if ((int) ($row['status'] ?? 0) === 1 && $execute) {
			$db->table('class_records')->where('id', (int) $row['id'])->update(['status' => 0]);
		}
	}
	if ($already) {
		return 'already';
	}
	if ($execute) {
		$db->table('class_records')->insert([
			'student' => $studentId,
			'year' => (string) ACADEMIC_YEAR_ID,
			'class' => $classId,
			'status' => 1,
		]);
	}
	return 'enrolled';
}

/**
 * @return list<string>
 */
function candidate_regnos(string $excelId): array
{
	$id = preg_replace('/\D+/', '', $excelId) ?? '';
	if ($id === '') {
		return [];
	}
	$out = [$id];
	if (strpos($id, '2025') === 0 && strlen($id) > 4) {
		$stripped = substr($id, 4);
		if ($stripped !== '') {
			$out[] = $stripped;
		}
	}
	if (strlen($id) >= 9) {
		$last9 = substr($id, -9);
		if (preg_match('/^26\d{7}$/', $last9)) {
			$out[] = $last9;
		}
	}
	return array_values(array_unique($out));
}

/**
 * @param array<string,mixed> $src
 * @param array<string,mixed> $current
 * @return array<string,mixed>
 */
function student_patch(array $src, array $current): array
{
	$patch = [];
	$fname = trim((string) ($src['fname'] ?? ''));
	if ($fname !== '' && strcasecmp(trim((string) ($current['fname'] ?? '')), $fname) !== 0) {
		$patch['fname'] = $fname;
	}
	$lname = trim((string) ($src['lname'] ?? ''));
	if ($lname !== '' && strcasecmp(trim((string) ($current['lname'] ?? '')), $lname) !== 0) {
		$patch['lname'] = $lname;
	}
	$gender = strtoupper(trim((string) ($src['gender'] ?? '')));
	if (in_array($gender, ['M', 'F'], true) && strtoupper((string) ($current['sex'] ?? '')) !== $gender) {
		$patch['sex'] = $gender;
	}
	$dob = (string) ($src['dob'] ?? '');
	if (is_usable_dob($dob) && (string) ($current['dob'] ?? '') !== $dob) {
		$patch['dob'] = $dob;
	}
	foreach ([['father', 'father'], ['mother', 'mother']] as [$srcKey, $dbKey]) {
		$name = trim((string) ($src[$srcKey] ?? ''));
		if (is_usable_name($name) && strcasecmp(trim((string) ($current[$dbKey] ?? '')), $name) !== 0) {
			$patch[$dbKey] = $name;
		}
	}
	foreach ([['ft_phone', 'ft_phone'], ['mt_phone', 'mt_phone']] as [$srcKey, $dbKey]) {
		$phone = normalize_phone((string) ($src[$srcKey] ?? ''));
		if ($phone === '') {
			continue;
		}
		$cur = normalize_phone((string) ($current[$dbKey] ?? ''));
		if ($cur !== $phone) {
			$patch[$dbKey] = $phone;
		}
	}
	$nat = trim((string) ($src['nationality'] ?? ''));
	if ($nat !== '' && strcasecmp(trim((string) ($current['nationality'] ?? '')), $nat) !== 0) {
		$patch['nationality'] = $nat;
	}
	return $patch;
}

$now = date('Y-m-d H:i:s');
say('=== High school record import ' . ($execute ? 'EXECUTE' : 'DRY RUN') . ' ===');
say('JSON students: ' . count($raw['students']));

$classCache = [];
$unmatchedClassLabels = [];

$existingRows = $db->table('students')
	->where('school_id', SCHOOL_ID)
	->where('status', 1)
	->get()
	->getResultArray();
$byRegno = [];
foreach ($existingRows as $row) {
	$reg = (string) $row['regno'];
	if ($reg !== '') {
		$byRegno[$reg] = $row;
	}
}

$counterRow = $db->table('reg_number')
	->where('school_id', SCHOOL_ID)
	->where('academic_year', YEAR_CODE)
	->get(1)
	->getRowArray();
$nextSeq = (int) ($counterRow['next_number'] ?? 1);
$seqStart = $nextSeq;

$usedIds = [];
$seenExcelIds = [];
$seenNameKeys = [];
$updated = 0;
$unchanged = 0;
$created = 0;
$enrolled = 0;
$already = 0;
$unmatchedClass = 0;
$duplicatesSkipped = 0;
$byClass = [];

foreach ($raw['students'] as $src) {
	$full = trim((string) ($src['full_name'] ?? ''));
	$label = strtoupper(trim(preg_replace('/\s+/', ' ', (string) ($src['class_label'] ?? '')) ?? ''));
	if ($full === '' || strcasecmp($full, 'TOTAL') === 0) {
		continue;
	}
	$excelId = preg_replace('/\D+/', '', (string) ($src['excel_id'] ?? '')) ?? '';

	if ($excelId !== '') {
		if (isset($seenExcelIds[$excelId])) {
			$duplicatesSkipped++;
			say("SKIP duplicate excel_id {$excelId} {$full}");
			continue;
		}
		$seenExcelIds[$excelId] = true;
	}
	$nameKey = compact_name($full);
	if ($nameKey !== '') {
		if (isset($seenNameKeys[$nameKey])) {
			$duplicatesSkipped++;
			say("SKIP duplicate excel name {$full} in {$label}");
			continue;
		}
		$seenNameKeys[$nameKey] = true;
	}

	if ($label === '') {
		$unmatchedClass++;
		say("SKIP no class: {$full}");
		continue;
	}
	if (!isset($classCache[$label])) {
		$resolved = resolve_class($db, $label);
		$classCache[$label] = $resolved;
		if ($resolved) {
			say("Class {$label} -> {$resolved['label']} (id {$resolved['id']})");
		} else {
			say("Class {$label} -> UNMATCHED");
			$unmatchedClassLabels[$label] = true;
		}
	}
	if (!$classCache[$label]) {
		$unmatchedClass++;
		say("SKIP unmatched class {$label}: {$full}");
		continue;
	}
	$classId = $classCache[$label]['id'];
	$className = $classCache[$label]['label'];

	$match = null;
	foreach (candidate_regnos($excelId) as $candReg) {
		if (!isset($byRegno[$candReg])) {
			continue;
		}
		$cand = $byRegno[$candReg];
		$cid = (int) $cand['id'];
		if (isset($usedIds[$cid])) {
			continue;
		}
		$match = $cand;
		break;
	}
	if (!$match) {
		foreach ($existingRows as $cand) {
			$cid = (int) $cand['id'];
			if (isset($usedIds[$cid])) {
				continue;
			}
			$hay = trim($cand['fname'] . ' ' . $cand['lname']);
			if (names_match($full, $hay)) {
				$match = $cand;
				break;
			}
		}
	}

	if (!$match) {
		$hitUsed = false;
		if ($excelId !== '') {
			foreach (candidate_regnos($excelId) as $candReg) {
				if (isset($byRegno[$candReg]) && isset($usedIds[(int) $byRegno[$candReg]['id']])) {
					$hitUsed = true;
					break;
				}
			}
		}
		if (!$hitUsed) {
			foreach ($existingRows as $cand) {
				$hay = trim($cand['fname'] . ' ' . $cand['lname']);
				if (names_match($full, $hay) && isset($usedIds[(int) $cand['id']])) {
					$hitUsed = true;
					break;
				}
			}
		}
		if ($hitUsed) {
			$duplicatesSkipped++;
			say("SKIP already-matched student {$full} excel_id={$excelId}");
			continue;
		}
	}

	if ($match) {
		$studentId = (int) $match['id'];
		$usedIds[$studentId] = true;
		$patch = student_patch($src, $match);
		$action = enroll($db, $studentId, $classId, $execute);
		if ($action === 'already') {
			$already++;
		} else {
			$enrolled++;
		}
		if ($patch !== []) {
			if ($execute) {
				$patch['updated_at'] = $now;
				$patch['updated_by'] = CREATED_BY;
				$db->table('students')->where('id', $studentId)->update($patch);
			}
			$updated++;
			$bits = [];
			foreach ($patch as $k => $v) {
				if (in_array($k, ['updated_at', 'updated_by'], true)) {
					continue;
				}
				$bits[] = $k;
			}
			say('UPD ' . $match['regno'] . '  ' . $full . '  -> ' . $className . ' [' . $action . '] ' . implode(',', $bits));
		} else {
			$unchanged++;
			say('OK  ' . $match['regno'] . '  ' . $full . '  -> ' . $className . ' [' . $action . ']');
		}
	} else {
		$fname = trim((string) ($src['fname'] ?? 'UNKNOWN'));
		$lname = trim((string) ($src['lname'] ?? ''));
		$gender = strtoupper(trim((string) ($src['gender'] ?? '')));
		if (!in_array($gender, ['M', 'F'], true)) {
			$gender = 'M';
		}
		$dob = is_usable_dob((string) ($src['dob'] ?? '')) ? (string) $src['dob'] : default_dob($label);
		$father = is_usable_name((string) ($src['father'] ?? '')) ? trim((string) $src['father']) : '';
		$mother = is_usable_name((string) ($src['mother'] ?? '')) ? trim((string) $src['mother']) : '';
		$ft = normalize_phone((string) ($src['ft_phone'] ?? ''));
		$mt = normalize_phone((string) ($src['mt_phone'] ?? ''));
		$nat = trim((string) ($src['nationality'] ?? ''));
		if ($nat === '') {
			$nat = 'rwanda';
		}
		$regno = make_regno($db, $nextSeq);
		if ($execute) {
			$db->table('students')->insert([
				'school_id' => SCHOOL_ID,
				'fname' => $fname !== '' ? $fname : 'UNKNOWN',
				'lname' => $lname,
				'phone' => '',
				'email' => '',
				'regno' => $regno,
				'sex' => $gender,
				'dob' => $dob,
				'photo' => '',
				'village_id' => DEFAULT_VILLAGE_ID,
				'studying_mode' => 1,
				'religion' => '',
				'nationality' => $nat,
				'card' => '',
				'transport_money' => 0,
				'wallet_balance' => 0,
				'father' => $father,
				'ft_phone' => $ft,
				'mother' => $mother,
				'mt_phone' => $mt,
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
			enroll($db, $studentId, $classId, true);
			$newRow = ['id' => $studentId, 'fname' => $fname, 'lname' => $lname, 'regno' => $regno];
			$existingRows[] = $newRow;
			$byRegno[$regno] = $newRow;
			$usedIds[$studentId] = true;
		} else {
			$nextSeq++;
		}
		$created++;
		$enrolled++;
		say('NEW ' . $regno . '  ' . $full . '  -> ' . $className . ' ' . $gender . ' mode=1');
	}
	$byClass[$className] = ($byClass[$className] ?? 0) + 1;
}

if ($execute && $nextSeq > $seqStart) {
	bump_reg_counter($db, $nextSeq);
}

say('');
say('=== Summary ===');
say('Updated existing: ' . $updated);
say('Already complete: ' . $unchanged);
say('Created: ' . $created);
say('Newly enrolled / moved: ' . $enrolled);
say('Already in class: ' . $already);
say('Unmatched class: ' . $unmatchedClass);
say('Duplicates skipped: ' . $duplicatesSkipped);
say('By class:');
ksort($byClass);
foreach ($byClass as $name => $n) {
	say("  {$name}: {$n}");
}

$counts = $db->query(
	"SELECT l.title AS level_name, IFNULL(d.code,'') AS dept, IFNULL(NULLIF(c.title,''),'-') AS stream, COUNT(cr.id) AS n, c.id
	 FROM classes c
	 JOIN levels l ON l.id = c.level
	 LEFT JOIN departments d ON d.id = c.department
	 LEFT JOIN class_records cr ON cr.class = c.id AND cr.year = '16' AND cr.status = 1
	 WHERE c.school_id = 27 AND IFNULL(c.title,'') NOT LIKE '%Holiday%'
	   AND l.title IN ('S2','S3','S5','S6')
	 GROUP BY c.id
	 ORDER BY l.title, d.code, c.title"
)->getResultArray();
say('');
say('S2-S6 regular class enrollments now:');
foreach ($counts as $row) {
	say("  {$row['level_name']} {$row['dept']} {$row['stream']} (id {$row['id']}): {$row['n']}");
}

exit(0);
