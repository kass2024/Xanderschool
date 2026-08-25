<?php
/**
 * Update Wisdom P6 students from UPDATED LIST OF SYSTEM.xlsx (Sheet6 / P6).
 * Matches ONLY current-year P6 enrollments. Does not deactivate other class records.
 * Does not move students from other classes into P6 by name.
 *
 * Dry run:  docker exec xander_school_app php /var/www/html/deploy/update_wisdom_p6_from_system_list.php
 * Execute:  docker exec xander_school_app php /var/www/html/deploy/update_wisdom_p6_from_system_list.php --execute
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
const CLASS_LABEL = 'P6';

$execute = in_array('--execute', $argv ?? [], true);
$jsonPath = __DIR__ . '/_p6_system_list.json';
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

function token_close(string $a, string $b): bool
{
	if ($a === $b) {
		return true;
	}
	if (strlen($a) >= 4 && strlen($b) >= 4 && (strpos($a, $b) === 0 || strpos($b, $a) === 0 || substr($a, -strlen($b)) === $b || substr($b, -strlen($a)) === $a)) {
		return true;
	}
	if (strlen($a) >= 5 && strlen($b) >= 5 && levenshtein($a, $b) <= (strlen($a) >= 6 && strlen($b) >= 6 ? 2 : 1)) {
		return true;
	}
	return false;
}

function names_match(string $a, string $b): bool
{
	$ca = compact_name($a);
	$cb = compact_name($b);
	if ($ca !== '' && $ca === $cb) {
		return true;
	}
	$minC = min(strlen($ca), strlen($cb));
	if ($minC >= 10 && ($ca !== '' && $cb !== '') && (strpos($ca, $cb) !== false || strpos($cb, $ca) !== false)) {
		return true;
	}
	$ta = name_tokens($a);
	$tb = name_tokens($b);
	if ($ta === [] || $tb === []) {
		return false;
	}
	$matched = 0;
	$used = [];
	foreach ($ta as $tokA) {
		foreach ($tb as $j => $tokB) {
			if (isset($used[$j])) {
				continue;
			}
			if (token_close($tokA, $tokB)) {
				$used[$j] = true;
				$matched++;
				break;
			}
		}
	}
	if ($matched >= 3) {
		return true;
	}
	$firstOk = token_close($ta[0], $tb[0])
		|| token_close($ta[0], $tb[count($tb) - 1])
		|| token_close($tb[0], $ta[count($ta) - 1]);
	if (!$firstOk) {
		return false;
	}
	$need = min(count($ta), count($tb));
	return $need >= 2 && $matched >= $need;
}

function is_school_regno(string $id): bool
{
	return (bool) preg_match('/^26027\d{4}$/', $id);
}

function is_usable_name(string $s): bool
{
	$s = trim($s);
	if (strlen($s) < 3 || !preg_match('/[A-Za-z]{3,}/', $s)) {
		return false;
	}
	$low = strtolower($s);
	return !in_array($low, ['none', 'n/a', 'null', 'test', 'unknown'], true);
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
	if (strlen($d) === 11 && strpos($d, '07') === 0) {
		$d = substr($d, 0, 10);
	}
	return $d;
}

function is_usable_phone(string $p): bool
{
	if (!preg_match('/^07[2389]\d{7}$/', $p)) {
		return false;
	}
	if (preg_match('/^0?(7)\1+$/', $p) || preg_match('/^(\d)\1+$/', $p)) {
		return false;
	}
	$bad = ['0789000000', '0723000000', '0788888888', '0777777777'];
	return !in_array($p, $bad, true);
}

function is_usable_dob(string $d): bool
{
	if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m)) {
		return false;
	}
	$y = (int) $m[1];
	$mo = (int) $m[2];
	$day = (int) $m[3];
	if ($y < 2009 || $y > 2018) {
		return false;
	}
	return checkdate($mo, $day, $y);
}

function is_placeholder_dob(string $d): bool
{
	return $d === '' || $d === '0000-00-00' || (bool) preg_match('/^(2008|2012|2013|2018|2019)-01-15$/', $d);
}

function is_year_only_dob(array $src): bool
{
	if (!empty($src['dob_year_only'])) {
		return true;
	}
	$dob = (string) ($src['dob'] ?? '');
	return (bool) preg_match('/^\d{4}-01-15$/', $dob);
}

function default_dob(): string
{
	return '2013-01-15';
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
		->get(1)->getRowArray();
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

/** @return array{id:int,label:string}|null */
function existing_regular_class(\CodeIgniter\Database\BaseConnection $db, string $classLabel): ?array
{
	$rows = $db->table('classes c')
		->select('c.id, c.title, l.title AS level_name')
		->join('levels l', 'l.id = c.level')
		->where('c.school_id', SCHOOL_ID)
		->where('l.title', $classLabel)
		->get()->getResultArray();
	$regular = [];
	foreach ($rows as $row) {
		if (stripos((string) $row['title'], 'Holiday') !== false) {
			continue;
		}
		$regular[] = $row;
	}
	if ($regular === []) {
		return null;
	}
	foreach ($regular as $row) {
		if (trim((string) $row['title']) === '') {
			return ['id' => (int) $row['id'], 'label' => (string) $row['level_name']];
		}
	}
	$row = $regular[0];
	return ['id' => (int) $row['id'], 'label' => trim($row['level_name'] . ' ' . $row['title'])];
}

/**
 * Ensure regular P6 enrollment exists. Never deactivates other class_records.
 */
function ensure_p6_enrollment(\CodeIgniter\Database\BaseConnection $db, int $studentId, int $classId, bool $execute): string
{
	$rows = $db->table('class_records')
		->select('id, class, status')
		->where('student', $studentId)
		->where('year', (string) ACADEMIC_YEAR_ID)
		->where('class', $classId)
		->get()->getResultArray();
	if ($rows !== []) {
		$row = $rows[0];
		if ((int) ($row['status'] ?? 0) !== 1 && $execute) {
			$db->table('class_records')->where('id', (int) $row['id'])->update(['status' => 1]);
			return 'activated';
		}
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
 * @param array<string,mixed> $src
 * @param array<string,mixed> $current
 * @return array<string,mixed>
 */
function student_patch(array $src, array $current): array
{
	$patch = [];
	foreach ([['fname', 'fname'], ['lname', 'lname']] as [$srcKey, $dbKey]) {
		$name = trim((string) ($src[$srcKey] ?? ''));
		$cur = trim((string) ($current[$dbKey] ?? ''));
		if ($cur === '' && is_usable_name($name)) {
			$patch[$dbKey] = $name;
		}
	}
	$gender = strtoupper(trim((string) ($src['gender'] ?? '')));
	if (in_array($gender, ['M', 'F'], true) && strtoupper((string) ($current['sex'] ?? '')) !== $gender) {
		$patch['sex'] = $gender;
	}
	$mode = (string) ($src['studying_mode'] ?? '');
	if (($mode === '0' || $mode === '1') && (string) ($current['studying_mode'] ?? '') !== $mode) {
		$patch['studying_mode'] = (int) $mode;
	}
	$dob = (string) ($src['dob'] ?? '');
	if (is_usable_dob($dob)) {
		$curDob = (string) ($current['dob'] ?? '');
		if (is_year_only_dob($src)) {
			if (is_placeholder_dob($curDob) && $curDob !== $dob) {
				$patch['dob'] = $dob;
			}
		} elseif ($curDob !== $dob) {
			$patch['dob'] = $dob;
		}
	}
	foreach ([['father', 'father'], ['mother', 'mother']] as [$srcKey, $dbKey]) {
		$name = trim((string) ($src[$srcKey] ?? ''));
		if (is_usable_name($name) && strcasecmp(trim((string) ($current[$dbKey] ?? '')), $name) !== 0) {
			$patch[$dbKey] = $name;
		}
	}
	foreach ([['ft_phone', 'ft_phone'], ['mt_phone', 'mt_phone']] as [$srcKey, $dbKey]) {
		$phone = normalize_phone((string) ($src[$srcKey] ?? ''));
		if (!is_usable_phone($phone)) {
			continue;
		}
		$cur = normalize_phone((string) ($current[$dbKey] ?? ''));
		if ($cur !== $phone) {
			$patch[$dbKey] = $phone;
		}
	}
	return $patch;
}

/**
 * @param array<int,array<string,mixed>> $existingById
 * @param array<int,int> $candidateIds
 * @param array<int,bool> $usedIds
 * @return array{0:?array<string,mixed>,1:int}
 */
function match_by_name(string $full, array $existingById, array $candidateIds, array $usedIds): array
{
	$hits = [];
	foreach ($candidateIds as $sid) {
		if (isset($usedIds[$sid]) || !isset($existingById[$sid])) {
			continue;
		}
		$cand = $existingById[$sid];
		$hay = trim((string) $cand['fname'] . ' ' . (string) $cand['lname']);
		if (names_match($full, $hay)) {
			$hits[] = $cand;
		}
	}
	if (count($hits) === 1) {
		return [$hits[0], 1];
	}
	return [null, count($hits)];
}

$now = date('Y-m-d H:i:s');
say('=== Wisdom P6 list update ' . ($execute ? 'EXECUTE' : 'DRY RUN') . ' ===');
say('Source sheet: ' . (string) ($raw['source_sheet'] ?? 'Sheet6'));
say('Parsed students: ' . count($raw['students']));

$found = existing_regular_class($db, CLASS_LABEL);
if (!$found) {
	fwrite(STDERR, "No existing regular P6 class\n");
	exit(1);
}
$classId = $found['id'];
$className = $found['label'];
say("Regular P6 class -> {$className} (id {$classId})");

$existingRows = $db->table('students')
	->where('school_id', SCHOOL_ID)
	->where('status', 1)
	->get()->getResultArray();
$existingById = [];
$byRegno = [];
foreach ($existingRows as $row) {
	$existingById[(int) $row['id']] = $row;
	$byRegno[(string) $row['regno']] = $row;
}

$regularIds = [];
$anyP6Ids = [];
$enrollRows = $db->query(
	"SELECT cr.student, cr.class, l.title AS level_name, c.title AS stream
	 FROM class_records cr
	 JOIN classes c ON c.id = cr.class
	 JOIN levels l ON l.id = c.level
	 WHERE cr.year = ? AND c.school_id = ? AND l.title = ?",
	[(string) ACADEMIC_YEAR_ID, SCHOOL_ID, CLASS_LABEL]
)->getResultArray();
foreach ($enrollRows as $row) {
	$sid = (int) $row['student'];
	$anyP6Ids[$sid] = $sid;
	if (stripos((string) $row['stream'], 'Holiday') !== false) {
		continue;
	}
	if ((int) $row['class'] === $classId) {
		$regularIds[$sid] = $sid;
	}
}
say('Current-year regular P6 enrollments: ' . count($regularIds));
say('Current-year any P6 enrollments: ' . count($anyP6Ids));

$counterRow = $db->table('reg_number')
	->where('school_id', SCHOOL_ID)
	->where('academic_year', YEAR_CODE)
	->get(1)->getRowArray();
$nextSeq = (int) ($counterRow['next_number'] ?? 1);
$seqStart = $nextSeq;

$usedIds = [];
$seenExcelKeys = [];
$updated = 0;
$unchanged = 0;
$created = 0;
$enrolledNew = 0;
$already = 0;
$activated = 0;
$ambiguous = [];
$createdNames = [];
$skippedDup = 0;

foreach ($raw['students'] as $src) {
	$label = strtoupper(trim((string) ($src['class_label'] ?? '')));
	$full = trim((string) ($src['full_name'] ?? ''));
	if ($full === '' || $label !== CLASS_LABEL) {
		continue;
	}
	$excelKey = compact_name($full);
	if ($excelKey !== '' && isset($seenExcelKeys[$excelKey])) {
		$skippedDup++;
		say("SKIP duplicate excel row {$full}");
		continue;
	}
	$seenExcelKeys[$excelKey] = true;

	$match = null;
	$matchHow = '';
	$excelId = (string) ($src['excel_id'] ?? '');
	if (is_school_regno($excelId) && isset($byRegno[$excelId])) {
		$cand = $byRegno[$excelId];
		$cid = (int) $cand['id'];
		if (!isset($usedIds[$cid]) && isset($anyP6Ids[$cid])) {
			$match = $cand;
			$matchHow = 'regno';
		}
	}
	if (!$match) {
		[$hit, $n] = match_by_name($full, $existingById, array_values($regularIds), $usedIds);
		if ($hit) {
			$match = $hit;
			$matchHow = 'name-regular';
		} elseif ($n > 1) {
			$ambiguous[] = $full;
			say("AMBIGUOUS in regular P6 (name): {$full}");
			continue;
		}
	}
	if (!$match) {
		[$hit, $n] = match_by_name($full, $existingById, array_values($anyP6Ids), $usedIds);
		if ($hit) {
			$match = $hit;
			$matchHow = 'name-p6';
		} elseif ($n > 1) {
			$ambiguous[] = $full;
			say("AMBIGUOUS in P6 (name): {$full}");
			continue;
		}
	}

	if ($match) {
		$studentId = (int) $match['id'];
		$usedIds[$studentId] = true;
		$patch = student_patch($src, $match);
		$action = ensure_p6_enrollment($db, $studentId, $classId, $execute);
		if ($action === 'already') {
			$already++;
		} elseif ($action === 'activated') {
			$activated++;
		} else {
			$enrolledNew++;
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
				$bits[] = $k . '=' . $v;
			}
			say('UPD ' . $match['regno'] . '  ' . $full . '  [' . $matchHow . '/' . $action . '] ' . implode(', ', $bits));
		} else {
			$unchanged++;
			say('OK  ' . $match['regno'] . '  ' . $full . '  [' . $matchHow . '/' . $action . ']');
		}
		continue;
	}

	$fname = trim((string) ($src['fname'] ?? 'UNKNOWN'));
	$lname = trim((string) ($src['lname'] ?? ''));
	$gender = strtoupper(trim((string) ($src['gender'] ?? '')));
	if (!in_array($gender, ['M', 'F'], true)) {
		$gender = 'M';
	}
	$mode = (string) ($src['studying_mode'] ?? '');
	$mode = ($mode === '0' || $mode === '1') ? (int) $mode : 1;
	$dob = is_usable_dob((string) ($src['dob'] ?? '')) ? (string) $src['dob'] : default_dob();
	$father = is_usable_name((string) ($src['father'] ?? '')) ? trim((string) $src['father']) : '';
	$mother = is_usable_name((string) ($src['mother'] ?? '')) ? trim((string) $src['mother']) : '';
	$ft = normalize_phone((string) ($src['ft_phone'] ?? ''));
	$mt = normalize_phone((string) ($src['mt_phone'] ?? ''));
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
			'studying_mode' => $mode,
			'religion' => '',
			'nationality' => 'rwanda',
			'card' => '',
			'transport_money' => 0,
			'wallet_balance' => 0,
			'father' => $father,
			'ft_phone' => is_usable_phone($ft) ? $ft : '',
			'mother' => $mother,
			'mt_phone' => is_usable_phone($mt) ? $mt : '',
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
		ensure_p6_enrollment($db, $studentId, $classId, true);
		$existingById[$studentId] = ['id' => $studentId, 'fname' => $fname, 'lname' => $lname, 'regno' => $regno];
	} else {
		$nextSeq++;
		$studentId = 0;
	}
	$usedIds[$studentId] = true;
	$created++;
	$enrolledNew++;
	$createdNames[] = $full;
	say('NEW ' . $regno . '  ' . $full . '  -> ' . $className . ' mode=' . $mode . ' ' . $gender);
}

if ($execute && $nextSeq > $seqStart) {
	bump_reg_counter($db, $nextSeq);
}

$p6Unmatched = [];
foreach ($regularIds as $sid) {
	if (!isset($usedIds[$sid]) && isset($existingById[$sid])) {
		$row = $existingById[$sid];
		$p6Unmatched[] = trim($row['fname'] . ' ' . $row['lname']) . ' (' . $row['regno'] . ')';
	}
}

say('');
say('=== Summary ===');
say('Updated existing: ' . $updated);
say('Already complete: ' . $unchanged);
say('Created: ' . $created);
say('Already in regular P6: ' . $already);
say('Activated regular P6: ' . $activated);
say('Newly enrolled in regular P6: ' . $enrolledNew);
say('Skipped duplicate excel rows: ' . $skippedDup);
say('Ambiguous skipped: ' . count($ambiguous));
if ($createdNames) {
	say('New students (Excel names not already in P6):');
	foreach ($createdNames as $line) {
		say('  ' . $line);
	}
}
if ($ambiguous) {
	say('Ambiguous Excel names (not updated, not created):');
	foreach ($ambiguous as $line) {
		say('  ' . $line);
	}
}
if ($p6Unmatched) {
	say('Existing regular P6 students with no Excel row:');
	foreach ($p6Unmatched as $line) {
		say('  ' . $line);
	}
}

$counts = $db->query(
	"SELECT l.title AS level_name, IFNULL(NULLIF(c.title,''),'(regular)') AS stream, COUNT(cr.id) AS n, c.id
	 FROM classes c
	 JOIN levels l ON l.id = c.level
	 LEFT JOIN class_records cr ON cr.class = c.id AND cr.year = '16' AND cr.status = 1
	 WHERE c.school_id = 27 AND l.title = 'P6'
	 GROUP BY c.id
	 ORDER BY c.title"
)->getResultArray();
say('');
say('P6 class enrollments now:');
foreach ($counts as $row) {
	say("  {$row['level_name']} {$row['stream']} (id {$row['id']}): {$row['n']}");
}

exit(0);