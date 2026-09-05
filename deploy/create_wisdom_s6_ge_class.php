<?php
/**
 * Ensure General Education (GE) exists and create S6 GE for WISDOM SCHOOL RWANDA.
 *
 * Safe to rerun: it reuses existing school/department/class rows when found.
 *
 * Usage:
 *   php deploy/create_wisdom_s6_ge_class.php
 *   php deploy/create_wisdom_s6_ge_class.php --dry-run
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
$now = date('Y-m-d H:i:s');

function say(string $msg): void
{
	echo $msg . PHP_EOL;
}

$school = $db->table('schools')
	->groupStart()
		->where('name', 'WISDOM SCHOOL RWANDA')
		->orLike('name', 'WISDOM SCHOOL RWANDA', 'both')
	->groupEnd()
	->get(1)
	->getRowArray();

if (!$school) {
	say('ERROR: WISDOM SCHOOL RWANDA not found');
	exit(1);
}

$schoolId = (int) $school['id'];
say('School: ' . $school['name'] . ' (id ' . $schoolId . ')');
say($dryRun ? 'Mode: DRY-RUN' : 'Mode: APPLY');

$level = $db->table('levels')
	->where('title', 'S6')
	->get(1)
	->getRowArray();

if (!$level) {
	say('ERROR: Level S6 not found');
	exit(1);
}

$levelId = (int) $level['id'];
say('Level S6 id=' . $levelId);

$dept = $db->table('departments')
	->groupStart()
		->where('code', 'GE')
		->orWhere('title', 'General Education')
	->groupEnd()
	->get(1)
	->getRowArray();

$facultyId = 0;
if ($dept) {
	$facultyId = (int) ($dept['faculty_id'] ?? 0);
	say('Department GE already exists id=' . (int) $dept['id']);
} else {
	// Reuse the same REB A'Level faculty currently attached to Wisdom S4-S6 classes.
	$facultyRow = $db->query(
		"SELECT f.id, f.title, f.abbrev, COUNT(*) AS n
		 FROM classes c
		 JOIN levels l ON l.id = c.level
		 JOIN departments d ON d.id = c.department
		 JOIN faculty f ON f.id = d.faculty_id
		 WHERE c.school_id = ?
		   AND f.type = 2
		   AND l.title IN ('S4','S5','S6')
		 GROUP BY f.id, f.title, f.abbrev
		 ORDER BY n DESC, f.id ASC
		 LIMIT 1",
		[$schoolId]
	)->getRowArray();

	if (!$facultyRow) {
		$facultyRow = $db->table('faculty')
			->groupStart()
				->where('type', 2)
				->like('abbrev', "A' Level", 'both')
			->groupEnd()
			->get(1)
			->getRowArray();
	}

	if (!$facultyRow) {
		say("ERROR: Could not determine an A'Level REB faculty for GE");
		exit(1);
	}

	$facultyId = (int) $facultyRow['id'];
	say("Using faculty {$facultyRow['title']} ({$facultyRow['abbrev']}) id={$facultyId}");

	if ($dryRun) {
		say('WOULD CREATE department GE / General Education');
		$deptId = -1;
	} else {
		$db->table('departments')->insert([
			'title' => 'General Education',
			'code' => 'GE',
			'faculty_id' => $facultyId,
			'created_at' => $now,
			'created_by' => 1,
			'updated_at' => $now,
			'updated_by' => 1,
		]);
		$deptId = (int) $db->insertID();
		say('Created department GE id=' . $deptId);
	}

	if ($dryRun) {
		$dept = ['id' => $deptId, 'faculty_id' => $facultyId];
	} else {
		$dept = $db->table('departments')->where('id', $deptId)->get(1)->getRowArray();
	}
}

$deptId = (int) $dept['id'];

$class = $db->table('classes')
	->where('school_id', $schoolId)
	->where('level', $levelId)
	->where('department', $deptId)
	->get(1)
	->getRowArray();

if ($class) {
	$classId = (int) $class['id'];
	say('S6 GE class already exists id=' . $classId);
} else {
	$mentorSource = $db->query(
		"SELECT c.mentor, c.created_by
		 FROM classes c
		 JOIN levels l ON l.id = c.level
		 JOIN departments d ON d.id = c.department
		 JOIN faculty f ON f.id = d.faculty_id
		 WHERE c.school_id = ?
		   AND f.type = 2
		   AND l.title IN ('S4','S5','S6')
		 ORDER BY (l.title = 'S6') DESC, c.id ASC
		 LIMIT 1",
		[$schoolId]
	)->getRowArray();

	$mentor = (int) ($mentorSource['mentor'] ?? 0);
	$createdBy = (int) ($mentorSource['created_by'] ?? 1);

	if ($dryRun) {
		say('WOULD CREATE class S6 GE mentor=' . $mentor);
		$classId = -1;
	} else {
		$db->table('classes')->insert([
			'school_id' => $schoolId,
			'level' => $levelId,
			'department' => $deptId,
			'title' => '',
			'mentor' => $mentor,
			'created_at' => $now,
			'created_by' => $createdBy > 0 ? $createdBy : 1,
			'updated_at' => $now,
			'updated_by' => $createdBy > 0 ? $createdBy : 1,
		]);
		$classId = (int) $db->insertID();
		say('Created S6 GE class id=' . $classId);
	}
}

say('');
say('Verification:');
$rows = $db->query(
	"SELECT c.id, s.name AS school_name, l.title AS level_name, d.code AS dept_code, d.title AS dept_title,
	        IF(TRIM(IFNULL(c.title,'')) = '', '-----', TRIM(c.title)) AS stream
	 FROM classes c
	 JOIN schools s ON s.id = c.school_id
	 JOIN levels l ON l.id = c.level
	 JOIN departments d ON d.id = c.department
	 WHERE c.school_id = ?
	   AND l.title = 'S6'
	   AND (d.code = 'GE' OR d.title = 'General Education')
	 ORDER BY c.id ASC",
	[$schoolId]
)->getResultArray();

if (!$rows) {
	say('  No S6 GE rows found');
	exit($dryRun ? 0 : 1);
}

foreach ($rows as $row) {
	say(sprintf(
		'  [%d] %s | %s %s | %s | title=%s',
		(int) $row['id'],
		$row['school_name'],
		$row['level_name'],
		$row['dept_code'],
		$row['dept_title'],
		$row['stream']
	));
}

say('');
say('DONE class_id=' . $classId);
exit(0);
