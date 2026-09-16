<?php
/**
 * Apply PRIMARY LIST 2026-2027 Excel names/classes to Wisdom School Rwanda.
 * Updates fname/lname and boarding/day only. Never touches photo, regno, parents, marks payload except class remap.
 *
 * Dry run:  docker exec xander_school_app php /var/www/html/deploy/apply_wisdom_primary_list.php
 * Execute:  docker exec xander_school_app php /var/www/html/deploy/apply_wisdom_primary_list.php --execute
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

require __DIR__ . '/_remap_student_class.php';

$execute = in_array('--execute', $argv ?? [], true);
$jsonPath = __DIR__ . '/_primary_name_match_report.json';
if (!is_file($jsonPath)) {
	fwrite(STDERR, "Missing {$jsonPath}\n");
	exit(1);
}
$raw = json_decode((string) file_get_contents($jsonPath), true);
if (!is_array($raw) || empty($raw['matches'])) {
	fwrite(STDERR, "Invalid match JSON\n");
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

/**
 * @param array<string,array<string,mixed>> $classes
 */
function ensure_p5c(\CodeIgniter\Database\BaseConnection $db, array &$classes, bool $execute): int
{
	if (isset($classes['P5C'])) {
		say('P5C already exists id=' . (int) $classes['P5C']['id']);
		return (int) $classes['P5C']['id'];
	}
	if (!isset($classes['P5A'])) {
		fwrite(STDERR, "P5A missing, cannot create P5C\n");
		exit(1);
	}
	$src = $classes['P5A'];
	$now = date('Y-m-d H:i:s');
	if (!$execute) {
		say('WOULD CREATE P5C cloned from P5A id=' . (int) $src['id']);
		$classes['P5C'] = $src;
		$classes['P5C']['id'] = -1;
		$classes['P5C']['stream'] = 'C';
		return -1;
	}
	$db->table('classes')->insert([
		'school_id' => SCHOOL_ID,
		'level' => (int) $src['level_id'],
		'department' => (int) $src['department_id'],
		'title' => 'C',
		'mentor' => (int) ($src['mentor'] ?? 0),
		'created_at' => $now,
		'created_by' => CREATED_BY,
		'updated_at' => $now,
		'updated_by' => CREATED_BY,
	]);
	$newId = (int) $db->insertID();
	$srcCourses = $db->table('course_records')->where('class', (int) $src['id'])->where('year', ACADEMIC_YEAR_ID)->get()->getResultArray();
	foreach ($srcCourses as $cr) {
		unset($cr['id']);
		$cr['class'] = $newId;
		try {
			$db->table('course_records')->insert($cr);
		} catch (\Throwable $e) {
			say('course_records note: ' . $e->getMessage());
		}
	}
	$srcFees = $db->table('extra_fees')
		->where('school_id', SCHOOL_ID)
		->where('type', 0)
		->where('type_id', (int) $src['id'])
		->where('academic_year', ACADEMIC_YEAR_ID)
		->get()->getResultArray();
	foreach ($srcFees as $fee) {
		unset($fee['id']);
		$fee['type_id'] = $newId;
		$fee['created_by'] = CREATED_BY;
		$db->table('extra_fees')->insert($fee);
	}
	$classes = load_primary_classes($db);
	say('Created P5C id=' . $newId . ' courses=' . count($srcCourses) . ' extra_fees=' . count($srcFees));
	return $newId;
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

$db = Database::connect();
$classes = load_primary_classes($db);
say('=== Wisdom primary Excel apply ' . ($execute ? 'EXECUTE' : 'DRY RUN') . ' ===');
ensure_p5c($db, $classes, $execute);
if ($execute) {
	$classes = load_primary_classes($db);
	if (!isset($classes['P5C'])) {
		fwrite(STDERR, "P5C still missing after create\n");
		exit(1);
	}
}

$nameN = 0;
$modeN = 0;
$moveN = 0;
$skipN = 0;
$now = date('Y-m-d H:i:s');

foreach ($raw['matches'] as $m) {
	$studentId = (int) ($m['id'] ?? 0);
	$sheet = strtoupper(trim((string) ($m['sheet'] ?? '')));
	if ($studentId < 1 || $sheet === '' || !isset($classes[$sheet])) {
		say('SKIP missing class ' . $sheet . ' student ' . $studentId);
		$skipN++;
		continue;
	}
	$st = $db->table('students')->where('id', $studentId)->where('school_id', SCHOOL_ID)->get(1)->getRowArray();
	if (!$st) {
		say('SKIP missing student ' . $studentId);
		$skipN++;
		continue;
	}
	$to = $classes[$sheet];
	$toId = (int) $to['id'];
	$fromId = (int) ($m['old_class_id'] ?? 0);
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
	if ($patch !== []) {
		if ($execute) {
			$patch['updated_at'] = $now;
			$patch['updated_by'] = CREATED_BY;
			$db->table('students')->where('id', $studentId)->update($patch);
		}
		if (isset($patch['fname'])) {
			$nameN++;
			say('NAME ' . $st['regno'] . ' [' . trim($st['fname'] . ' ' . $st['lname']) . '] => ' . trim($nf . ' ' . $nl));
		}
		if (isset($patch['studying_mode'])) {
			$modeN++;
			say('MODE ' . $st['regno'] . ' ' . $st['studying_mode'] . ' => ' . $wantMode);
		}
	}
	// Always ensure the target class_record is active, even when the student
	// was already listed on that sheet (stale status=0 leftovers).
	$enroll = enroll_regular($db, $studentId, $toId, $execute);
	if ($fromId > 0 && $fromId !== $toId) {
		$from = $db->table('classes')->where('id', $fromId)->get(1)->getRowArray();
		if ($toId < 1) {
			$moveN++;
			say('WOULD MOVE ' . $st['regno'] . ' ' . ($m['old_class'] ?? '?') . ' -> ' . $sheet . ' (P5C create first)');
		} else {
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
	} elseif ($enroll !== 'already') {
		say('ENROLL ' . $st['regno'] . ' ' . $sheet . ' (' . $enroll . ')');
	}
}

say('');
say('Names updated: ' . $nameN);
say('Mode updated: ' . $modeN);
say('Class moves: ' . $moveN);
say('Skipped: ' . $skipN);
say('Unmatched Excel left as-is: ' . count($raw['unmatched'] ?? []));
say($execute ? 'DONE' : 'DRY RUN only — re-run with --execute to apply');
