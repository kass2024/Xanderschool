<?php
/**
 * Wisdom School Rwanda (school 27):
 * Dispatch P2A students into P2A + P2B, balancing gender, studying mode, and class size.
 * Uses the same class_records update + record remapping approach as student Move.
 *
 * Dry run:  docker exec xander_school_app php /var/www/html/deploy/dispatch_wisdom_p2_ab.php
 * Execute:  docker exec xander_school_app php /var/www/html/deploy/dispatch_wisdom_p2_ab.php --execute
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

function modeLabel($mode): string
{
	$m = (int) $mode;
	if ($m === 0) {
		return 'boarding';
	}
	if ($m === 1) {
		return 'day';
	}
	return 'mode' . $m;
}

/**
 * @return array{id:int,stream:string,level:string,level_id:int,department_id:int}|null
 */
function findP2Section(\CodeIgniter\Database\BaseConnection $db, string $streamLetter): ?array
{
	$row = $db->query(
		"SELECT c.id, c.title AS stream, l.title AS level_title, c.level AS level_id, c.department AS department_id
		 FROM classes c
		 JOIN levels l ON l.id = c.level
		 WHERE c.school_id = ?
		   AND UPPER(TRIM(l.title)) = 'P2'
		   AND UPPER(TRIM(IFNULL(c.title,''))) = ?
		   AND LOWER(IFNULL(c.title,'')) NOT LIKE '%holiday%'
		   AND LOWER(IFNULL(l.title,'')) NOT LIKE '%holiday%'
		 LIMIT 1",
		[SCHOOL_ID, strtoupper($streamLetter)]
	)->getRowArray();
	if (!$row) {
		return null;
	}
	return [
		'id' => (int) $row['id'],
		'stream' => (string) $row['stream'],
		'level' => (string) $row['level_title'],
		'level_id' => (int) $row['level_id'],
		'department_id' => (int) $row['department_id'],
	];
}

/**
 * Balance assign: keep totals nearly equal; within each gender x mode bucket, split half/half.
 *
 * @param list<array<string,mixed>> $students
 * @return array{0:list<array<string,mixed>>,1:list<array<string,mixed>>} [keepInA, moveToB]
 */
function balanceSplit(array $students): array
{
	$buckets = [];
	foreach ($students as $st) {
		$sex = strtoupper(trim((string) ($st['sex'] ?? '')));
		if ($sex === '') {
			$sex = '?';
		}
		$mode = (string) ((int) ($st['studying_mode'] ?? -1));
		$key = $sex . '|' . $mode;
		$buckets[$key][] = $st;
	}
	ksort($buckets);

	$toA = [];
	$toB = [];
	$toggle = 0; // for odd remainders, alternate which section gets +1

	foreach ($buckets as $key => $list) {
		// Stable order within bucket
		usort($list, static function ($a, $b) {
			$na = strtolower(trim(($a['lname'] ?? '') . ' ' . ($a['fname'] ?? '')));
			$nb = strtolower(trim(($b['lname'] ?? '') . ' ' . ($b['fname'] ?? '')));
			return $na <=> $nb;
		});
		$n = count($list);
		$half = intdiv($n, 2);
		$extra = $n % 2;
		$nA = $half;
		$nB = $half;
		if ($extra === 1) {
			if ($toggle % 2 === 0) {
				$nA++;
			} else {
				$nB++;
			}
			$toggle++;
		}
		// Prefer slightly filling the currently smaller overall side when odd
		if ($extra === 1) {
			// recompute using running totals for better global balance
			$curA = count($toA);
			$curB = count($toB);
			$nA = $half;
			$nB = $half;
			if ($curA <= $curB) {
				$nA++;
			} else {
				$nB++;
			}
		}
		for ($i = 0; $i < $n; $i++) {
			if ($i < $nA) {
				$toA[] = $list[$i];
			} else {
				$toB[] = $list[$i];
			}
		}
	}

	// Final size balance: if |A-B| > 1, move from larger to smaller (prefer same sex/mode when possible)
	while (abs(count($toA) - count($toB)) > 1) {
		if (count($toA) > count($toB)) {
			$moved = array_pop($toA);
			if ($moved === null) {
				break;
			}
			$toB[] = $moved;
		} else {
			$moved = array_pop($toB);
			if ($moved === null) {
				break;
			}
			$toA[] = $moved;
		}
	}

	return [$toA, $toB];
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

function summarize(array $list, string $label): void
{
	$n = count($list);
	$bySex = [];
	$byMode = [];
	foreach ($list as $st) {
		$sex = strtoupper(trim((string) ($st['sex'] ?? ''))) ?: '?';
		$mode = modeLabel($st['studying_mode'] ?? -1);
		$bySex[$sex] = ($bySex[$sex] ?? 0) + 1;
		$byMode[$mode] = ($byMode[$mode] ?? 0) + 1;
	}
	ksort($bySex);
	ksort($byMode);
	$sexParts = [];
	foreach ($bySex as $k => $v) {
		$sexParts[] = "{$k}={$v}";
	}
	$modeParts = [];
	foreach ($byMode as $k => $v) {
		$modeParts[] = "{$k}={$v}";
	}
	say(sprintf(
		'%s: total=%d | gender %s | mode %s',
		$label,
		$n,
		$sexParts !== [] ? implode(', ', $sexParts) : '-',
		$modeParts !== [] ? implode(', ', $modeParts) : '-'
	));
}

// --- main ---
say('=== Dispatch Wisdom P2A -> P2A/P2B ' . ($execute ? 'EXECUTE' : 'DRY RUN') . ' ===');

$at = $db->query(
	'SELECT academic_year, term FROM active_term WHERE school_id = ? ORDER BY id DESC LIMIT 1',
	[SCHOOL_ID]
)->getRowArray();
if (!$at || (int) ($at['academic_year'] ?? 0) < 1) {
	fwrite(STDERR, "No active academic year for school " . SCHOOL_ID . ".\n");
	exit(1);
}
$yearKey = (string) $at['academic_year'];
$year = $db->query('SELECT id, title FROM academic_year WHERE id = ? LIMIT 1', [$yearKey])->getRowArray();
say('Academic year: ' . ($year['title'] ?? $yearKey) . ' (id=' . $yearKey . ') term=' . ($at['term'] ?? '?'));

$p2a = findP2Section($db, 'A');
$p2b = findP2Section($db, 'B');
if (!$p2a || !$p2b) {
	fwrite(STDERR, 'Missing P2A or P2B class for school ' . SCHOOL_ID . "\n");
	exit(1);
}
say(sprintf('P2A id=%d | P2B id=%d', $p2a['id'], $p2b['id']));

$existingB = (int) $db->query(
	'SELECT COUNT(*) AS c FROM class_records WHERE class = ? AND year = ?',
	[$p2b['id'], $yearKey]
)->getRowArray()['c'];
if ($existingB > 0) {
	say("WARNING: P2B already has {$existingB} student(s). Script only moves from P2A; existing P2B kept.");
}

$students = $db->query(
	"SELECT s.id, s.fname, s.lname, s.regno, s.sex, s.studying_mode, cr.id AS cr_id
	 FROM class_records cr
	 JOIN students s ON s.id = cr.student
	 WHERE cr.class = ? AND cr.year = ? AND s.school_id = ?
	 ORDER BY s.lname, s.fname, s.id",
	[$p2a['id'], $yearKey, SCHOOL_ID]
)->getResultArray();

if ($students === []) {
	fwrite(STDERR, "No students in P2A for this year.\n");
	exit(1);
}

say('P2A source students: ' . count($students));
[$keepA, $moveB] = balanceSplit($students);
summarize($keepA, 'Plan P2A');
summarize($moveB, 'Plan P2B');

say('--- Assignments to P2B ---');
foreach ($moveB as $st) {
	say(sprintf(
		'  %s %s (id=%d regno=%s sex=%s mode=%s) -> P2B',
		$st['fname'] ?? '',
		$st['lname'] ?? '',
		(int) $st['id'],
		$st['regno'] ?? '',
		strtoupper(trim((string) ($st['sex'] ?? ''))) ?: '?',
		modeLabel($st['studying_mode'] ?? -1)
	));
}

if (!$execute) {
	say('Dry run only. Re-run with --execute to apply.');
	exit(0);
}

$db->transStart();
$moved = 0;
foreach ($moveB as $st) {
	$sid = (int) $st['id'];
	$crId = (int) $st['cr_id'];
	// Avoid duplicate enrollment in P2B
	$already = $db->query(
		'SELECT id FROM class_records WHERE student = ? AND year = ? AND class = ? LIMIT 1',
		[$sid, $yearKey, $p2b['id']]
	)->getRowArray();
	if ($already) {
		$db->query('DELETE FROM class_records WHERE id = ?', [$crId]);
	} else {
		$db->query('UPDATE class_records SET class = ? WHERE id = ?', [$p2b['id'], $crId]);
	}
	remapOnMove(
		$db,
		$sid,
		$p2a['id'],
		$p2b['id'],
		$yearKey,
		$p2a['level_id'],
		$p2a['department_id'],
		$p2b['level_id'],
		$p2b['department_id']
	);
	$moved++;
}
$db->transComplete();
if ($db->transStatus() === false) {
	fwrite(STDERR, "Transaction failed.\n");
	exit(1);
}

$cntA = (int) $db->query(
	'SELECT COUNT(*) AS c FROM class_records WHERE class = ? AND year = ?',
	[$p2a['id'], $yearKey]
)->getRowArray()['c'];
$cntB = (int) $db->query(
	'SELECT COUNT(*) AS c FROM class_records WHERE class = ? AND year = ?',
	[$p2b['id'], $yearKey]
)->getRowArray()['c'];
say("DONE moved={$moved} | P2A now={$cntA} | P2B now={$cntB}");
exit(0);
