<?php
/**
 * Wisdom School Rwanda (school 27):
 * Rename level N2 -> Middle Class, N3 -> Top Class for that school's classes.
 *
 * Strategy:
 * - Prefer renaming the level row if it is only used by Wisdom.
 * - Otherwise create/reuse Middle Class / Top Class levels and re-point Wisdom classes.
 *
 * Dry run:  docker exec xander_school_app php /var/www/html/deploy/rename_wisdom_n2_n3_levels.php
 * Execute:  docker exec xander_school_app php /var/www/html/deploy/rename_wisdom_n2_n3_levels.php --execute
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

$execute = in_array('--execute', $argv ?? [], true);

function say(string $msg): void
{
	echo $msg . PHP_EOL;
}

/**
 * @return list<array<string,mixed>>
 */
function wisdomClassesForLevelTitles(\CodeIgniter\Database\BaseConnection $db, array $titles): array
{
	$placeholders = implode(',', array_fill(0, count($titles), '?'));
	$bind = array_merge([SCHOOL_ID], $titles);
	return $db->query(
		"SELECT c.id AS class_id, c.title AS stream_title, c.level AS level_id,
		        l.title AS level_title, l.type AS level_type, l.faculty_id,
		        IFNULL(d.title,'') AS dept_title
		 FROM classes c
		 JOIN levels l ON l.id = c.level
		 LEFT JOIN departments d ON d.id = c.department
		 WHERE c.school_id = ?
		   AND UPPER(TRIM(l.title)) IN ($placeholders)
		   AND LOWER(IFNULL(c.title,'')) NOT LIKE '%holiday%'
		   AND LOWER(IFNULL(l.title,'')) NOT LIKE '%holiday%'
		 ORDER BY l.title, c.id",
		$bind
	)->getResultArray();
}

function levelUsageOutsideSchool(\CodeIgniter\Database\BaseConnection $db, int $levelId): int
{
	return (int) $db->table('classes')
		->where('level', $levelId)
		->where('school_id !=', SCHOOL_ID)
		->countAllResults();
}

function findOrCreateLevel(
	\CodeIgniter\Database\BaseConnection $db,
	string $newTitle,
	array $template,
	bool $execute
): int {
	$existing = $db->query(
		"SELECT id FROM levels
		 WHERE UPPER(TRIM(title)) = UPPER(?)
		   AND type = ?
		 LIMIT 1",
		[$newTitle, (int) ($template['level_type'] ?? 0)]
	)->getRowArray();
	if ($existing) {
		return (int) $existing['id'];
	}

	// Also try any matching title regardless of type
	$any = $db->query(
		"SELECT id FROM levels WHERE UPPER(TRIM(title)) = UPPER(?) LIMIT 1",
		[$newTitle]
	)->getRowArray();
	if ($any) {
		return (int) $any['id'];
	}

	if (!$execute) {
		say("Would create level '{$newTitle}' (type=" . ($template['level_type'] ?? '?') . ')');
		return 0;
	}

	$db->table('levels')->insert([
		'title' => $newTitle,
		'type' => (int) ($template['level_type'] ?? 2),
		'faculty_id' => $template['faculty_id'] !== null && $template['faculty_id'] !== ''
			? (int) $template['faculty_id']
			: null,
		'status' => 1,
	]);
	$id = (int) $db->insertID();
	say("Created level '{$newTitle}' id={$id}");
	return $id;
}

$map = [
	'N2' => 'Middle Class',
	'N3' => 'Top Class',
];

say('=== Rename Wisdom N2/N3 levels ' . ($execute ? 'EXECUTE' : 'DRY RUN') . ' ===');

$classes = wisdomClassesForLevelTitles($db, array_keys($map));
if ($classes === []) {
	fwrite(STDERR, "No Wisdom N2/N3 classes found.\n");
	exit(1);
}

$byLevelId = [];
foreach ($classes as $row) {
	$oldTitle = strtoupper(trim((string) $row['level_title']));
	$newTitle = $map[$oldTitle] ?? null;
	if ($newTitle === null) {
		continue;
	}
	$lid = (int) $row['level_id'];
	if (!isset($byLevelId[$lid])) {
		$byLevelId[$lid] = [
			'old' => (string) $row['level_title'],
			'new' => $newTitle,
			'type' => $row['level_type'],
			'faculty_id' => $row['faculty_id'],
			'classes' => [],
		];
	}
	$byLevelId[$lid]['classes'][] = $row;
	say(sprintf(
		'  class id=%d  level=%s (%d) stream=%s dept=%s  -> %s',
		$row['class_id'],
		$row['level_title'],
		$row['level_id'],
		$row['stream_title'] !== '' ? $row['stream_title'] : '-',
		$row['dept_title'] !== '' ? $row['dept_title'] : '-',
		$newTitle
	));
}

foreach ($byLevelId as $levelId => $info) {
	$outside = levelUsageOutsideSchool($db, (int) $levelId);
	say("Level id={$levelId} '{$info['old']}' used by other schools: {$outside}");

	if ($outside === 0) {
		// Safe to rename in place
		say("Action: RENAME levels.id={$levelId} '{$info['old']}' -> '{$info['new']}'");
		if ($execute) {
			$db->table('levels')->where('id', (int) $levelId)->update(['title' => $info['new']]);
		}
		continue;
	}

	// Shared level: create/reuse destination and re-point Wisdom classes only
	$newId = findOrCreateLevel($db, $info['new'], $info, $execute);
	say("Action: REPOINT Wisdom classes from level {$levelId} -> {$newId} ({$info['new']})");
	if ($execute && $newId > 0) {
		$classIds = array_map(static fn ($c) => (int) $c['class_id'], $info['classes']);
		$db->table('classes')->whereIn('id', $classIds)->where('school_id', SCHOOL_ID)->update(['level' => $newId]);
	}
}

if ($execute) {
	$check = wisdomClassesForLevelTitles($db, ['N2', 'N3']);
	$ok = wisdomClassesForLevelTitles($db, ['MIDDLE CLASS', 'TOP CLASS']);
	say('Remaining Wisdom N2/N3 classes: ' . count($check));
	say('Wisdom Middle/Top classes now: ' . count($ok));
	foreach ($ok as $row) {
		say(sprintf('  OK class id=%d level=%s stream=%s', $row['class_id'], $row['level_title'], $row['stream_title'] ?: '-'));
	}
	say('DONE');
} else {
	say('Dry run only. Re-run with --execute to apply.');
}

exit(0);
