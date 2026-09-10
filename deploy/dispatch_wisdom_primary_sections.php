<?php
/**
 * Wisdom School Rwanda (school 27):
 * Dispatch {LEVEL}A students across multiple sections (A,B,C,...),
 * balancing gender, studying mode, and class size.
 *
 * Dry run:  php deploy/dispatch_wisdom_primary_sections.php --level=P5 --sections=A,B,C
 * Execute:  php ... --level=P5 --sections=A,B,C --execute
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
$levelCode = 'P5';
$sectionLetters = ['A', 'B', 'C'];
foreach ($argv ?? [] as $arg) {
	if (preg_match('/^--level=(P[1-6])$/i', (string) $arg, $m)) {
		$levelCode = strtoupper($m[1]);
	}
	if (preg_match('/^--sections=([A-Za-z,]+)$/', (string) $arg, $m)) {
		$parts = array_values(array_filter(array_map(
			static fn ($s) => strtoupper(trim($s)),
			explode(',', $m[1])
		)));
		if ($parts !== []) {
			$sectionLetters = $parts;
		}
	}
}
if (count($sectionLetters) < 2) {
	fwrite(STDERR, "Need at least 2 sections, e.g. --sections=A,B,C\n");
	exit(1);
}

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
function findSection(\CodeIgniter\Database\BaseConnection $db, string $levelCode, string $streamLetter): ?array
{
	$row = $db->query(
		"SELECT c.id, c.title AS stream, l.title AS level_title, c.level AS level_id, c.department AS department_id
		 FROM classes c
		 JOIN levels l ON l.id = c.level
		 WHERE c.school_id = ?
		   AND UPPER(TRIM(l.title)) = ?
		   AND UPPER(TRIM(IFNULL(c.title,''))) = ?
		   AND LOWER(IFNULL(c.title,'')) NOT LIKE '%holiday%'
		   AND LOWER(IFNULL(l.title,'')) NOT LIKE '%holiday%'
		 LIMIT 1",
		[SCHOOL_ID, strtoupper($levelCode), strtoupper($streamLetter)]
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
 * Split students into N groups: mix gender x mode, nearly equal totals.
 *
 * @param list<array<string,mixed>> $students
 * @return list<list<array<string,mixed>>>
 */
function balanceSplitN(array $students, int $n): array
{
	$groups = array_fill(0, $n, []);
	$buckets = [];
	foreach ($students as $st) {
		$sex = strtoupper(trim((string) ($st['sex'] ?? '')));
		if ($sex === '') {
			$sex = '?';
		}
		$mode = (string) ((int) ($st['studying_mode'] ?? -1));
		$buckets[$sex . '|' . $mode][] = $st;
	}
	ksort($buckets);

	foreach ($buckets as $list) {
		usort($list, static function ($a, $b) {
			$na = strtolower(trim(($a['lname'] ?? '') . ' ' . ($a['fname'] ?? '')));
			$nb = strtolower(trim(($b['lname'] ?? '') . ' ' . ($b['fname'] ?? '')));
			return $na <=> $nb;
		});
		$counts = array_fill(0, $n, intdiv(count($list), $n));
		$extra = count($list) % $n;
		// Give remainders to currently smallest overall groups
		$order = range(0, $n - 1);
		usort($order, static function ($i, $j) use ($groups) {
			$ci = count($groups[$i]);
			$cj = count($groups[$j]);
			if ($ci === $cj) {
				return $i <=> $j;
			}
			return $ci <=> $cj;
		});
		for ($e = 0; $e < $extra; $e++) {
			$counts[$order[$e]]++;
		}
		$idx = 0;
		for ($g = 0; $g < $n; $g++) {
			for ($k = 0; $k < $counts[$g]; $k++) {
				$groups[$g][] = $list[$idx++];
			}
		}
	}

	// Final size balance: max - min <= 1
	$guard = 0;
	while ($guard++ < 10000) {
		$sizes = array_map('count', $groups);
		$maxI = array_keys($sizes, max($sizes))[0];
		$minI = array_keys($sizes, min($sizes))[0];
		if ($sizes[$maxI] - $sizes[$minI] <= 1) {
			break;
		}
		$moved = array_pop($groups[$maxI]);
		if ($moved === null) {
			break;
		}
		$groups[$minI][] = $moved;
	}

	return $groups;
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
$labels = array_map(static fn ($s) => $levelCode . $s, $sectionLetters);
say('=== Dispatch Wisdom ' . $labels[0] . ' -> ' . implode('/', $labels) . ' ' . ($execute ? 'EXECUTE' : 'DRY RUN') . ' ===');

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

$sections = [];
foreach ($sectionLetters as $letter) {
	$sec = findSection($db, $levelCode, $letter);
	if (!$sec) {
		fwrite(STDERR, "Missing {$levelCode}{$letter} class for school " . SCHOOL_ID . "\n");
		exit(1);
	}
	$sections[] = $sec;
	say(sprintf('%s%s id=%d', $levelCode, $letter, $sec['id']));
}

$source = $sections[0];
for ($i = 1; $i < count($sections); $i++) {
	$existing = (int) $db->query(
		'SELECT COUNT(*) AS c FROM class_records WHERE class = ? AND year = ?',
		[$sections[$i]['id'], $yearKey]
	)->getRowArray()['c'];
	if ($existing > 0) {
		say("WARNING: {$labels[$i]} already has {$existing} student(s). Script only redistributes from {$labels[0]}.");
	}
}

$students = $db->query(
	"SELECT s.id, s.fname, s.lname, s.regno, s.sex, s.studying_mode, cr.id AS cr_id
	 FROM class_records cr
	 JOIN students s ON s.id = cr.student
	 WHERE cr.class = ? AND cr.year = ? AND s.school_id = ?
	 ORDER BY s.lname, s.fname, s.id",
	[$source['id'], $yearKey, SCHOOL_ID]
)->getResultArray();

if ($students === []) {
	fwrite(STDERR, "No students in {$labels[0]} for this year.\n");
	exit(1);
}

say("{$labels[0]} source students: " . count($students));
$groups = balanceSplitN($students, count($sections));
foreach ($groups as $i => $group) {
	summarize($group, 'Plan ' . $labels[$i]);
}

for ($i = 1; $i < count($groups); $i++) {
	say("--- Assignments to {$labels[$i]} ---");
	foreach ($groups[$i] as $st) {
		say(sprintf(
			'  %s %s (id=%d regno=%s sex=%s mode=%s) -> %s',
			$st['fname'] ?? '',
			$st['lname'] ?? '',
			(int) $st['id'],
			$st['regno'] ?? '',
			strtoupper(trim((string) ($st['sex'] ?? ''))) ?: '?',
			modeLabel($st['studying_mode'] ?? -1),
			$labels[$i]
		));
	}
}

if (!$execute) {
	say('Dry run only. Re-run with --execute to apply.');
	exit(0);
}

$db->transStart();
$moved = 0;
for ($i = 1; $i < count($groups); $i++) {
	$dest = $sections[$i];
	foreach ($groups[$i] as $st) {
		$sid = (int) $st['id'];
		$crId = (int) $st['cr_id'];
		$already = $db->query(
			'SELECT id FROM class_records WHERE student = ? AND year = ? AND class = ? LIMIT 1',
			[$sid, $yearKey, $dest['id']]
		)->getRowArray();
		if ($already) {
			$db->query('DELETE FROM class_records WHERE id = ?', [$crId]);
		} else {
			$db->query('UPDATE class_records SET class = ? WHERE id = ?', [$dest['id'], $crId]);
		}
		remapOnMove(
			$db,
			$sid,
			$source['id'],
			$dest['id'],
			$yearKey,
			$source['level_id'],
			$source['department_id'],
			$dest['level_id'],
			$dest['department_id']
		);
		$moved++;
	}
}
$db->transComplete();
if ($db->transStatus() === false) {
	fwrite(STDERR, "Transaction failed.\n");
	exit(1);
}

$parts = [];
foreach ($sections as $i => $sec) {
	$cnt = (int) $db->query(
		'SELECT COUNT(*) AS c FROM class_records WHERE class = ? AND year = ?',
		[$sec['id'], $yearKey]
	)->getRowArray()['c'];
	$parts[] = $labels[$i] . ' now=' . $cnt;
}
say('DONE moved=' . $moved . ' | ' . implode(' | ', $parts));
exit(0);
