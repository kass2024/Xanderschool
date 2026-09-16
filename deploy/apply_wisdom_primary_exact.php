<?php
/**
 * Make Wisdom primary 2026-2027 class lists match PRIMARY LIST Excel exactly.
 * Locks extras (students.status=0, records kept). Never deletes. Never touches photos.
 * Creates remaining Excel names that have no existing student.
 *
 * Dry run:  docker exec xander_school_app php /var/www/html/deploy/apply_wisdom_primary_exact.php
 * Execute:  docker exec xander_school_app php /var/www/html/deploy/apply_wisdom_primary_exact.php --execute
 */
declare(strict_types=1);

@ini_set('max_execution_time', '0');
@set_time_limit(0);
@ini_set('memory_limit', '512M');

define('FCPATH', __DIR__ . '/../public/');
chdir(FCPATH);
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/Config/Paths.php';

$paths = new Config\Paths();
require rtrim($paths->systemDirectory, '/ ') . '/bootstrap.php';

use Config\Database;

const SCHOOL_ID = 27;
const ACADEMIC_YEAR_ID = 16;
const CREATED_BY = 1;
const YEAR_CODE = '26';

require __DIR__ . '/_remap_student_class.php';

$execute = in_array('--execute', $argv ?? [], true);
$jsonPath = __DIR__ . '/_primary_exact_plan.json';
if (!is_file($jsonPath)) {
	fwrite(STDERR, "Missing {$jsonPath}\n");
	exit(1);
}
$plan = json_decode((string) file_get_contents($jsonPath), true);
if (!is_array($plan)) {
	fwrite(STDERR, "Invalid plan JSON\n");
	exit(1);
}

function say(string $msg): void
{
	echo $msg . PHP_EOL;
}

function is_holiday_title(string $title): bool
{
	return stripos($title, 'Holiday') !== false;
}

/**
 * @return array<string,array<string,mixed>>
 */
function load_primary_classes(\CodeIgniter\Database\BaseConnection $db): array
{
	$rows = $db->query(
		"SELECT c.id, c.title AS stream, c.level AS level_id, c.department AS department_id, l.title AS level_title, c.mentor
		 FROM classes c
		 JOIN levels l ON l.id = c.level
		 WHERE c.school_id = ?
		   AND l.title IN ('P1','P2','P3','P4','P5','P6')
		   AND LOWER(IFNULL(l.title,'')) NOT LIKE '%holiday%'
		   AND LOWER(IFNULL(c.title,'')) NOT LIKE '%holiday%'
		 ORDER BY l.title, c.title",
		[SCHOOL_ID]
	)->getResultArray();
	$out = [];
	foreach ($rows as $row) {
		$level = strtoupper(trim((string) $row['level_title']));
		$stream = strtoupper(trim((string) $row['stream']));
		$key = $level === 'P1' ? 'P1' : ($stream !== '' ? $level . $stream : $level);
		$out[$key] = $row;
	}
	return $out;
}

function enroll_regular(\CodeIgniter\Database\BaseConnection $db, int $studentId, int $classId, bool $execute): string
{
	$rows = $db->table('class_records cr')
		->select('cr.id, cr.class, cr.status, c.title, l.title AS level_title')
		->join('classes c', 'c.id = cr.class')
		->join('levels l', 'l.id = c.level')
		->where('cr.student', $studentId)
		->where('cr.year', (string) ACADEMIC_YEAR_ID)
		->get()->getResultArray();
	$already = false;
	foreach ($rows as $row) {
		if ((int) $row['class'] === $classId) {
			if ((int) ($row['status'] ?? 0) !== 1 && $execute) {
				$db->table('class_records')->where('id', (int) $row['id'])->update(['status' => 1]);
			}
			$already = true;
			continue;
		}
		$levelTitle = strtoupper(trim((string) ($row['level_title'] ?? '')));
		if (is_holiday_title((string) $row['title']) || is_holiday_title($levelTitle)) {
			continue;
		}
		if (!preg_match('/^P[1-6]$/', $levelTitle)) {
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

function has_active_non_primary(\CodeIgniter\Database\BaseConnection $db, int $studentId): bool
{
	$row = $db->query(
		"SELECT cr.id
		 FROM class_records cr
		 JOIN classes c ON c.id = cr.class
		 JOIN levels l ON l.id = c.level
		 WHERE cr.student = ?
		   AND cr.year = ?
		   AND cr.status = 1
		   AND l.title NOT IN ('P1','P2','P3','P4','P5','P6')
		   AND LOWER(IFNULL(l.title,'')) NOT LIKE '%holiday%'
		   AND LOWER(IFNULL(c.title,'')) NOT LIKE '%holiday%'
		 LIMIT 1",
		[$studentId, (string) ACADEMIC_YEAR_ID]
	)->getRowArray();
	return (bool) $row;
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

function default_dob(string $sheet): string
{
	$n = (int) preg_replace('/\D/', '', $sheet);
	$year = 2020 - max(1, $n);
	return sprintf('%04d-01-15', $year);
}

$db = Database::connect();
$classes = load_primary_classes($db);
$now = date('Y-m-d H:i:s');
say('=== Wisdom primary EXACT Excel ' . ($execute ? 'EXECUTE' : 'DRY RUN') . ' ===');

$keepN = 0;
$renameN = 0;
$moveN = 0;
foreach (($plan['keep'] ?? []) as $m) {
	$studentId = (int) ($m['id'] ?? 0);
	$sheet = strtoupper(trim((string) ($m['sheet'] ?? '')));
	if ($studentId < 1 || $sheet === '' || !isset($classes[$sheet])) {
		say('SKIP keep missing class ' . $sheet . ' student ' . $studentId);
		continue;
	}
	$st = $db->table('students')->where('id', $studentId)->where('school_id', SCHOOL_ID)->get(1)->getRowArray();
	if (!$st) {
		say('SKIP keep missing student ' . $studentId);
		continue;
	}
	$to = $classes[$sheet];
	$toId = (int) $to['id'];
	$patch = [];
	$nf = trim((string) ($m['new_fname'] ?? ''));
	$nl = trim((string) ($m['new_lname'] ?? ''));
	if ($nf !== '' && (strcasecmp((string) $st['fname'], $nf) !== 0 || strcasecmp((string) $st['lname'], $nl) !== 0)) {
		$patch['fname'] = $nf;
		$patch['lname'] = $nl;
	}
	$wantMode = (string) ($m['want_mode'] ?? '');
	if ($wantMode !== '' && (string) $st['studying_mode'] !== $wantMode) {
		$patch['studying_mode'] = (int) $wantMode;
	}
	if ($patch !== [] && $execute) {
		$patch['updated_at'] = $now;
		$patch['updated_by'] = CREATED_BY;
		$db->table('students')->where('id', $studentId)->update($patch);
	}
	if (isset($patch['fname'])) {
		$renameN++;
		say('NAME ' . $st['regno'] . ' [' . trim($st['fname'] . ' ' . $st['lname']) . '] => ' . trim($nf . ' ' . $nl) . ' PHOTO kept ' . (string) ($st['photo'] ?? ''));
	}
	$fromId = (int) ($m['old_class_id'] ?? 0);
	$enroll = enroll_regular($db, $studentId, $toId, $execute);
	if ($fromId > 0 && $fromId !== $toId) {
		$from = $db->table('classes')->where('id', $fromId)->get(1)->getRowArray();
		if ($execute && $from) {
			remapOnMove(
				$db,
				$studentId,
				$fromId,
				$toId,
				(string) ACADEMIC_YEAR_ID,
				(int) ($from['level'] ?? 0),
				(int) ($from['department'] ?? 0),
				(int) $to['level_id'],
				(int) $to['department_id']
			);
		}
		$moveN++;
		say('MOVE ' . $st['regno'] . ' ' . ($m['old_class'] ?? '?') . ' -> ' . $sheet . ' (' . $enroll . ')');
	}
	$keepN++;
}

$lockN = 0;
$lockPrimaryOnly = 0;
foreach (($plan['lock'] ?? []) as $row) {
	$studentId = (int) ($row['id'] ?? 0);
	if ($studentId < 1) {
		continue;
	}
	$st = $db->table('students')->where('id', $studentId)->where('school_id', SCHOOL_ID)->get(1)->getRowArray();
	if (!$st) {
		say('SKIP lock missing ' . $studentId);
		continue;
	}
	$keepSecondary = has_active_non_primary($db, $studentId);
	if ($execute) {
		if ($keepSecondary) {
			$rows = $db->table('class_records cr')
				->select('cr.id, c.title, l.title AS level_title')
				->join('classes c', 'c.id = cr.class')
				->join('levels l', 'l.id = c.level')
				->where('cr.student', $studentId)
				->where('cr.year', (string) ACADEMIC_YEAR_ID)
				->where('cr.status', 1)
				->get()->getResultArray();
			foreach ($rows as $cr) {
				$levelTitle = strtoupper(trim((string) ($cr['level_title'] ?? '')));
				if (!preg_match('/^P[1-6]$/', $levelTitle)) {
					continue;
				}
				if (is_holiday_title((string) $cr['title']) || is_holiday_title($levelTitle)) {
					continue;
				}
				$db->table('class_records')->where('id', (int) $cr['id'])->update(['status' => 0]);
			}
		} else {
			$db->table('students')->where('id', $studentId)->update([
				'status' => 0,
				'updated_at' => $now,
				'updated_by' => CREATED_BY,
			]);
			$db->table('class_records')->where('student', $studentId)->update(['status' => 0]);
		}
	}
	$lockN++;
	if ($keepSecondary) {
		$lockPrimaryOnly++;
		say('LOCK-PRIMARY-ONLY ' . $st['regno'] . ' ' . ($row['name'] ?? '') . ' ' . ($row['class'] ?? '') . ' PHOTO kept ' . (string) ($st['photo'] ?? ''));
	} else {
		say('LOCK ' . $st['regno'] . ' ' . ($row['name'] ?? '') . ' ' . ($row['class'] ?? '') . ' PHOTO kept ' . (string) ($st['photo'] ?? ''));
	}
}

$counter = $db->table('reg_number')
	->where('school_id', SCHOOL_ID)
	->where('academic_year', YEAR_CODE)
	->get(1)
	->getRowArray();
$nextSeq = (int) ($counter['next_number'] ?? 1);
$createN = 0;
foreach (($plan['create'] ?? []) as $row) {
	$sheet = strtoupper(trim((string) ($row['sheet'] ?? '')));
	if ($sheet === '' || !isset($classes[$sheet])) {
		say('SKIP create missing class ' . $sheet);
		continue;
	}
	$nf = trim((string) ($row['new_fname'] ?? ''));
	$nl = trim((string) ($row['new_lname'] ?? ''));
	if ($nf === '') {
		say('SKIP create empty name ' . $sheet);
		continue;
	}
	$regno = make_regno($db, $nextSeq);
	$classId = (int) $classes[$sheet]['id'];
	if ($execute) {
		$db->table('students')->insert([
			'school_id' => SCHOOL_ID,
			'fname' => $nf,
			'lname' => $nl,
			'phone' => '',
			'email' => '',
			'regno' => $regno,
			'sex' => '',
			'dob' => default_dob($sheet),
			'photo' => '',
			'studying_mode' => (int) ($row['want_mode'] ?? 1),
			'religion' => '',
			'nationality' => '',
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
		$newId = (int) $db->insertID();
		$db->table('class_records')->insert([
			'student' => $newId,
			'year' => (string) ACADEMIC_YEAR_ID,
			'class' => $classId,
			'status' => 1,
		]);
	}
	$createN++;
	say('CREATE ' . $sheet . ' ' . trim($nf . ' ' . $nl) . ' regno=' . $regno);
}
if ($execute && $createN > 0) {
	bump_reg_counter($db, $nextSeq);
}

say('');
say('Kept on Excel lists: ' . $keepN);
say('Names updated: ' . $renameN);
say('Class moves: ' . $moveN);
say('Locked (not deleted): ' . $lockN . ' (primary-only leftover: ' . $lockPrimaryOnly . ')');
say('Created from Excel: ' . $createN);
say($execute ? 'DONE' : 'DRY RUN only — re-run with --execute to apply');
