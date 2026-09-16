<?php
/**
 * Careful follow-up for PRIMARY LIST 2026-2027 after the main apply:
 * - Reactivate ISHIMWE ANGE on P4A (stale class_records.status=0)
 * - High-confidence spelling/stream matches that the fuzzy matcher skipped
 *
 * Never updates photo, regno, or parents.
 *
 * Dry run:  docker exec xander_school_app php /var/www/html/deploy/apply_wisdom_primary_followup.php
 * Execute:  docker exec xander_school_app php /var/www/html/deploy/apply_wisdom_primary_followup.php --execute
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

$db = Database::connect();
$classes = load_primary_classes($db);

$jobs = [
	[
		'regno' => '260270124',
		'sheet' => '',
		'fname' => 'ISHIMWE',
		'lname' => 'Ange Ornella',
		'mode' => '',
		'why' => 'revert name; keep S3 (P4A Excel row was leftover primary record)',
		'revert_only' => true,
	],
	[
		'regno' => '260270541',
		'sheet' => 'P5A',
		'fname' => 'IRACYANYIBIKA',
		'lname' => 'ELIE',
		'mode' => '1',
		'why' => 'IRACYANYIBUKA ELIA spelling',
	],
	[
		'regno' => '260270598',
		'sheet' => 'P5A',
		'fname' => 'KEZA',
		'lname' => 'CYETENGERWA MAKKA',
		'mode' => '0',
		'why' => 'CYETENGIRWE KEZA Makka P6B -> P5A',
	],
	[
		'regno' => '260270485',
		'sheet' => 'P4A',
		'fname' => 'AGATAKO',
		'lname' => 'MARUWA',
		'mode' => '0',
		'why' => 'AGATAKO MALUA P4B -> P4A',
	],
];

say('=== Wisdom primary follow-up ' . ($execute ? 'EXECUTE' : 'DRY RUN') . ' ===');
$now = date('Y-m-d H:i:s');

foreach ($jobs as $job) {
	$sheet = (string) $job['sheet'];
	$revertOnly = !empty($job['revert_only']);
	if (!$revertOnly && !isset($classes[$sheet])) {
		say('SKIP missing class ' . $sheet);
		continue;
	}
	$st = $db->table('students')->where('regno', $job['regno'])->where('school_id', SCHOOL_ID)->get(1)->getRowArray();
	if (!$st) {
		say('SKIP missing ' . $job['regno']);
		continue;
	}
	$patch = [];
	if (strcasecmp((string) $st['fname'], (string) $job['fname']) !== 0 || strcasecmp((string) $st['lname'], (string) $job['lname']) !== 0) {
		$patch['fname'] = (string) $job['fname'];
		$patch['lname'] = (string) $job['lname'];
	}
	if ((string) $job['mode'] !== '' && (string) $st['studying_mode'] !== (string) $job['mode']) {
		$patch['studying_mode'] = (int) $job['mode'];
	}
	if ($patch !== [] && $execute) {
		$patch['updated_at'] = $now;
		$patch['updated_by'] = CREATED_BY;
		$db->table('students')->where('id', (int) $st['id'])->update($patch);
	}
	if (isset($patch['fname'])) {
		say('NAME ' . $st['regno'] . ' [' . trim($st['fname'] . ' ' . $st['lname']) . '] => ' . trim($job['fname'] . ' ' . $job['lname']));
	}
	if (isset($patch['studying_mode'])) {
		say('MODE ' . $st['regno'] . ' ' . $st['studying_mode'] . ' => ' . $job['mode']);
	}
	if ($revertOnly) {
		say('KEEP CLASS ' . $st['regno'] . ' [' . $job['why'] . ']');
		say('PHOTO kept ' . (string) ($st['photo'] ?? ''));
		continue;
	}
	$to = $classes[$sheet];
	$toId = (int) $to['id'];

	$current = $db->table('class_records cr')
		->select('cr.class, cr.status, c.title, l.title AS level_title, c.level, c.department')
		->join('classes c', 'c.id = cr.class')
		->join('levels l', 'l.id = c.level')
		->where('cr.student', (int) $st['id'])
		->where('cr.year', (string) ACADEMIC_YEAR_ID)
		->where('cr.status', 1)
		->get()->getResultArray();
	$fromId = 0;
	$fromLevel = 0;
	$fromDept = 0;
	$fromKey = '';
	foreach ($current as $row) {
		if (is_holiday_title((string) $row['title']) || is_holiday_title((string) $row['level_title'])) {
			continue;
		}
		$fromId = (int) $row['class'];
		$fromLevel = (int) $row['level'];
		$fromDept = (int) $row['department'];
		$level = strtoupper(trim((string) $row['level_title']));
		$stream = strtoupper(trim((string) $row['title']));
		$fromKey = $level === 'P1' ? 'P1' : ($stream !== '' ? $level . $stream : $level);
		break;
	}
	if ($fromId < 1) {
		$fromKey = $sheet;
	}

	$enroll = enroll_regular($db, (int) $st['id'], $toId, $execute);
	if ($fromId > 0 && $fromId !== $toId && $execute) {
		remapOnMove(
			$db,
			(int) $st['id'],
			$fromId,
			$toId,
			(string) ACADEMIC_YEAR_ID,
			$fromLevel,
			$fromDept,
			(int) $to['level_id'],
			(int) $to['department_id']
		);
	}
	say(($fromId > 0 && $fromId !== $toId ? 'MOVE' : 'ENROLL') . ' ' . $st['regno'] . ' ' . ($fromKey !== '' ? $fromKey : '?') . ' -> ' . $sheet . ' (' . $enroll . ') [' . $job['why'] . ']');
	say('PHOTO kept ' . (string) ($st['photo'] ?? ''));
}

say($execute ? 'DONE' : 'DRY RUN only — re-run with --execute to apply');
