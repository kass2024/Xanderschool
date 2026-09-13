<?php
/**
 * Split Wisdom School Rwanda P5 C students equally into P5 A and P5 B,
 * remapping enrollment + class-bound marks/fees/materials (same as student Move).
 *
 * Dry run:  docker exec xander_school_app php /var/www/html/deploy/split_p5c_to_p5ab.php
 * Execute:  docker exec xander_school_app php /var/www/html/deploy/split_p5c_to_p5ab.php --execute
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

$execute = in_array('--execute', $argv ?? [], true);
const SCHOOL_ID = 27;

function say(string $msg): void
{
	echo $msg . PHP_EOL;
}

/**
 * Remap class-scoped records for one student (subset of Home::remapStudentRecordsOnClassMove).
 */
function remapOnMove(
	\CodeIgniter\Database\BaseConnection $db,
	int $studentId,
	int $fromClassId,
	int $toClassId,
	string $yearKey,
	int $fromLevel,
	int $fromDept,
	int $toLevel,
	int $toDept
): void {
	if ($db->tableExists('marks')) {
		$db->query(
			'UPDATE marks SET class_id = ? WHERE student_id = ? AND class_id = ?',
			[$toClassId, $studentId, $fromClassId]
		);
		if ($db->tableExists('course_records') && $db->tableExists('courses')) {
			$db->query(
				'UPDATE marks m
				 INNER JOIN courses c_old ON c_old.id = m.course_id
				 INNER JOIN course_records cr_new ON cr_new.class = ? AND cr_new.year = ?
				 INNER JOIN courses c_new ON c_new.id = cr_new.course
				 SET m.course_id = c_new.id
				 WHERE m.student_id = ? AND m.class_id = ?
				   AND c_old.id <> c_new.id
				   AND (
					 (c_old.code IS NOT NULL AND c_old.code <> \'\' AND LOWER(TRIM(c_old.code)) = LOWER(TRIM(c_new.code)))
					 OR LOWER(TRIM(c_old.title)) = LOWER(TRIM(c_new.title))
				   )',
				[$toClassId, $yearKey, $studentId, $toClassId]
			);
		}
	}
	if ($db->tableExists('student_material_checks') && $db->fieldExists('class_id', 'student_material_checks')) {
		$sql = 'UPDATE student_material_checks SET class_id = ? WHERE student_id = ? AND class_id = ?';
		$bind = [$toClassId, $studentId, $fromClassId];
		if ($db->fieldExists('academic_year', 'student_material_checks')) {
			$sql .= ' AND academic_year = ?';
			$bind[] = $yearKey;
		}
		$db->query($sql, $bind);
	}
	if ($db->tableExists('course_attendance_records') && $db->fieldExists('class_id', 'course_attendance_records')) {
		$db->query(
			'UPDATE course_attendance_records SET class_id = ? WHERE student_id = ? AND class_id = ?',
			[$toClassId, $studentId, $fromClassId]
		);
	}
	if ($db->tableExists('deliberation_records')) {
		if ($db->fieldExists('oldClass', 'deliberation_records') && $db->fieldExists('studentId', 'deliberation_records')) {
			$db->query(
				'UPDATE deliberation_records SET oldClass = ? WHERE studentId = ? AND oldClass = ?',
				[$toClassId, $studentId, $fromClassId]
			);
		}
		if ($db->fieldExists('newClass', 'deliberation_records') && $db->fieldExists('studentId', 'deliberation_records')) {
			$db->query(
				'UPDATE deliberation_records SET newClass = ? WHERE studentId = ? AND newClass = ?',
				[$toClassId, $studentId, $fromClassId]
			);
		}
	}
	if ($db->tableExists('fees_records') && $db->tableExists('extra_fees')) {
		$paid = $db->query(
			'SELECT DISTINCT fr.fees_id
			 FROM fees_records fr
			 INNER JOIN extra_fees ef ON ef.id = fr.fees_id
			 WHERE fr.student_id = ? AND fr.fees_type = 1
			   AND ef.school_id = ? AND ef.type = 0 AND ef.type_id = ? AND ef.academic_year = ?',
			[$studentId, SCHOOL_ID, $fromClassId, $yearKey]
		)->getResultArray();
		foreach ($paid as $row) {
			$oldId = (int) ($row['fees_id'] ?? 0);
			if ($oldId < 1) {
				continue;
			}
			$oldFee = $db->query('SELECT * FROM extra_fees WHERE id = ? LIMIT 1', [$oldId])->getRowArray();
			if (!$oldFee) {
				continue;
			}
			$title = trim((string) ($oldFee['title'] ?? ''));
			$term = (int) ($oldFee['term'] ?? 0);
			$match = $db->query(
				'SELECT id FROM extra_fees
				 WHERE school_id = ? AND type = 0 AND type_id = ? AND academic_year = ?
				   AND LOWER(TRIM(title)) = LOWER(?) AND term = ?
				 LIMIT 1',
				[SCHOOL_ID, $toClassId, $yearKey, $title, $term]
			)->getRowArray();
			$newId = (int) ($match['id'] ?? 0);
			if ($newId < 1) {
				$loose = $db->query(
					'SELECT id FROM extra_fees
					 WHERE school_id = ? AND type = 0 AND type_id = ? AND academic_year = ?
					   AND LOWER(TRIM(title)) = LOWER(?)
					 ORDER BY ABS(term - ?) ASC, id ASC LIMIT 1',
					[SCHOOL_ID, $toClassId, $yearKey, $title, $term]
				)->getRowArray();
				$newId = (int) ($loose['id'] ?? 0);
			}
			if ($newId < 1) {
				$insert = [
					'school_id' => SCHOOL_ID,
					'title' => $title !== '' ? $title : ('Fee ' . $oldId),
					'academic_year' => $yearKey,
					'type_id' => $toClassId,
					'type' => 0,
					'term' => $term > 0 ? $term : 1,
					'amount' => $oldFee['amount'] ?? 0,
					'created_by' => (int) ($oldFee['created_by'] ?? 0),
				];
				if ($db->fieldExists('amount_boarding', 'extra_fees')) {
					$insert['amount_boarding'] = $oldFee['amount_boarding'] ?? null;
				}
				if ($db->fieldExists('amount_day', 'extra_fees')) {
					$insert['amount_day'] = $oldFee['amount_day'] ?? null;
				}
				$db->table('extra_fees')->insert($insert);
				$newId = (int) $db->insertID();
			}
			if ($newId > 0 && $newId !== $oldId) {
				$db->query(
					'UPDATE fees_records SET fees_id = ? WHERE student_id = ? AND fees_type = 1 AND fees_id = ?',
					[$newId, $studentId, $oldId]
				);
			}
		}
	}
	if (
		$db->tableExists('fees_records')
		&& $db->tableExists('school_fees')
		&& $fromLevel > 0 && $toLevel > 0
		&& ($fromLevel !== $toLevel || $fromDept !== $toDept)
	) {
		$oldFees = $db->query(
			'SELECT id, term FROM school_fees WHERE school_id = ? AND level = ? AND department = ? AND academic_year = ?',
			[SCHOOL_ID, $fromLevel, $fromDept, $yearKey]
		)->getResultArray();
		$newFees = $db->query(
			'SELECT id, term FROM school_fees WHERE school_id = ? AND level = ? AND department = ? AND academic_year = ?',
			[SCHOOL_ID, $toLevel, $toDept, $yearKey]
		)->getResultArray();
		$newByTerm = [];
		foreach ($newFees as $sf) {
			$t = (int) ($sf['term'] ?? 0);
			if ($t > 0 && !isset($newByTerm[$t])) {
				$newByTerm[$t] = (int) $sf['id'];
			}
		}
		foreach ($oldFees as $sf) {
			$oldId = (int) ($sf['id'] ?? 0);
			$newId = $newByTerm[(int) ($sf['term'] ?? 0)] ?? 0;
			if ($oldId < 1 || $newId < 1 || $oldId === $newId) {
				continue;
			}
			$db->query(
				'UPDATE fees_records SET fees_id = ? WHERE student_id = ? AND fees_type = 0 AND fees_id = ?',
				[$newId, $studentId, $oldId]
			);
			if ($db->tableExists('school_fees_discount')) {
				$db->query(
					'UPDATE school_fees_discount SET feesId = ? WHERE student = ? AND feesId = ?',
					[$newId, $studentId, $oldId]
				);
			}
		}
	}
	if ($db->tableExists('students') && $db->fieldExists('application_id', 'students') && $db->tableExists('applications')) {
		$appId = (int) ($db->query(
			'SELECT application_id FROM students WHERE id = ? AND school_id = ? LIMIT 1',
			[$studentId, SCHOOL_ID]
		)->getRowArray()['application_id'] ?? 0);
		if ($appId > 0 && $db->fieldExists('class_id', 'applications')) {
			$db->query(
				'UPDATE applications SET class_id = ? WHERE id = ? AND schoolId = ?',
				[$toClassId, $appId, SCHOOL_ID]
			);
		}
	}
}

$db = Database::connect();

$classes = $db->query(
	"SELECT c.id, l.title AS level_title, c.title AS stream, c.level AS level_id, c.department AS department_id,
	        CONCAT(IFNULL(st.fname,''),' ',IFNULL(st.lname,'')) AS mentor,
	        (SELECT COUNT(*) FROM class_records cr WHERE cr.class = c.id) AS students
	 FROM classes c
	 JOIN levels l ON l.id = c.level
	 LEFT JOIN staffs st ON st.id = c.mentor
	 WHERE c.school_id = ?
	   AND UPPER(TRIM(l.title)) = 'P5'
	   AND UPPER(TRIM(c.title)) IN ('A','B','C')
	   AND l.title NOT LIKE '%Holiday%'
	   AND c.title NOT LIKE '%Holiday%'
	 ORDER BY c.title",
	[SCHOOL_ID]
)->getResultArray();

$byStream = [];
foreach ($classes as $row) {
	$byStream[strtoupper(trim((string) $row['stream']))] = $row;
}
if (!isset($byStream['A'], $byStream['B'], $byStream['C'])) {
	fwrite(STDERR, "Need P5 A, P5 B and P5 C. Found: " . implode(',', array_keys($byStream)) . PHP_EOL);
	exit(1);
}

$p5a = (int) $byStream['A']['id'];
$p5b = (int) $byStream['B']['id'];
$p5c = (int) $byStream['C']['id'];

say('=== Split P5 C -> P5 A / P5 B ' . ($execute ? 'EXECUTE' : 'DRY RUN') . ' ===');
foreach (['A', 'B', 'C'] as $s) {
	$row = $byStream[$s];
	say(sprintf(
		'  P5 %s id=%d mentor=%s enrollments=%s',
		$s,
		$row['id'],
		trim((string) $row['mentor']),
		$row['students']
	));
}

$students = $db->query(
	"SELECT cr.id AS cr_id, cr.student, cr.year, s.fname, s.lname, s.regno
	 FROM class_records cr
	 JOIN students s ON s.id = cr.student AND s.school_id = ?
	 WHERE cr.class = ?
	 ORDER BY s.lname ASC, s.fname ASC, s.id ASC",
	[SCHOOL_ID, $p5c]
)->getResultArray();

$total = count($students);
if ($total < 1) {
	fwrite(STDERR, "No students enrolled in P5 C.\n");
	exit(1);
}

$toA = (int) ceil($total / 2);
$toB = $total - $toA;
say("P5 C students: {$total}  ->  P5 A: {$toA}  P5 B: {$toB}");

$okA = 0;
$okB = 0;
$failed = [];

if ($execute) {
	$db->transStart();
}

foreach ($students as $i => $st) {
	$destId = $i < $toA ? $p5a : $p5b;
	$destLabel = $i < $toA ? 'P5 A' : 'P5 B';
	$destKey = $i < $toA ? 'A' : 'B';
	$name = trim(($st['fname'] ?? '') . ' ' . ($st['lname'] ?? ''));
	$year = (string) ($st['year'] ?? '');
	$sid = (int) $st['student'];
	$crId = (int) $st['cr_id'];
	say(sprintf(
		'  %s  %s  regno=%s  year=%s  -> %s',
		$execute ? 'MOVE' : 'PLAN',
		$name,
		$st['regno'] ?? '',
		$year,
		$destLabel
	));
	if (!$execute) {
		continue;
	}
	try {
		$already = $db->query(
			'SELECT id FROM class_records WHERE student = ? AND year = ? AND class = ? LIMIT 1',
			[$sid, $year, $destId]
		)->getRowArray();
		if ($already && (int) $already['id'] !== $crId) {
			$db->query('DELETE FROM class_records WHERE id = ?', [$crId]);
		} else {
			$db->query('UPDATE class_records SET class = ? WHERE id = ?', [$destId, $crId]);
		}
		$dupRows = $db->query(
			'SELECT id FROM class_records WHERE student = ? AND year = ? AND class = ? ORDER BY id ASC',
			[$sid, $year, $destId]
		)->getResultArray();
		if (count($dupRows) > 1) {
			array_shift($dupRows);
			foreach ($dupRows as $extra) {
				$db->query('DELETE FROM class_records WHERE id = ?', [(int) $extra['id']]);
			}
		}
		remapOnMove(
			$db,
			$sid,
			$p5c,
			$destId,
			$year,
			(int) $byStream['C']['level_id'],
			(int) $byStream['C']['department_id'],
			(int) $byStream[$destKey]['level_id'],
			(int) $byStream[$destKey]['department_id']
		);
		if ($db->tableExists('update_version')) {
			$uv = $db->query(
				"SELECT version FROM update_version WHERE type = 'student' AND school_id = ? LIMIT 1",
				[SCHOOL_ID]
			)->getRowArray();
			$ver = (int) ($uv['version'] ?? 1);
			$db->query(
				'UPDATE students SET updateVersion = ? WHERE id = ? AND school_id = ?',
				[$ver, $sid, SCHOOL_ID]
			);
		}
		if ($destId === $p5a) {
			$okA++;
		} else {
			$okB++;
		}
	} catch (Throwable $e) {
		$failed[] = $name . ': ' . $e->getMessage();
		say('    FAIL ' . $e->getMessage());
	}
}

if ($execute) {
	$db->transComplete();
	if ($db->transStatus() === false) {
		fwrite(STDERR, "Transaction failed.\n");
		exit(1);
	}
}

$leftC = (int) $db->table('class_records')->where('class', $p5c)->countAllResults();
$nowA = (int) $db->table('class_records')->where('class', $p5a)->countAllResults();
$nowB = (int) $db->table('class_records')->where('class', $p5b)->countAllResults();
say('');
say("P5 A enrollments now: {$nowA}");
say("P5 B enrollments now: {$nowB}");
say("P5 C enrollments left: {$leftC}");
if ($failed !== []) {
	say('Failed: ' . count($failed));
	foreach ($failed as $f) {
		say('  - ' . $f);
	}
}
say($execute ? "MOVED A={$okA} B={$okB}" : 'Dry run only. Re-run with --execute to move.');
say('DONE');
exit(($execute && ($leftC > 0 || $failed !== [])) ? 1 : 0);
