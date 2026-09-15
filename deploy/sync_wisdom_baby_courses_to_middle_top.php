<?php
/**
 * Copy Baby class nursery courses + periods/week onto Middle and Top (Wisdom Rwanda).
 *
 *   docker exec xander_school_app php /var/www/html/deploy/sync_wisdom_baby_courses_to_middle_top.php [--dry-run]
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
const ALL_TERMS = '1,2,3';

$dryRun = in_array('--dry-run', $argv ?? [], true);

$yearRow = $db->table('academic_year')
	->where('school_id', SCHOOL_ID)
	->orderBy('id', 'DESC')
	->get(1)
	->getRowArray();
$yearId = (int) ($yearRow['id'] ?? 0);
if ($yearId <= 0) {
	fwrite(STDERR, "No academic year for school " . SCHOOL_ID . "\n");
	exit(1);
}

/**
 * @return array{baby:?array,middle:?array,top:?array}
 */
function loadNurseryClasses(\CodeIgniter\Database\BaseConnection $db, int $schoolId): array
{
	$rows = $db->query(
		"SELECT c.id, TRIM(c.title) AS class_title, TRIM(l.title) AS level_title
		 FROM classes c
		 JOIN levels l ON l.id = c.level
		 WHERE c.school_id = ?",
		[$schoolId]
	)->getResultArray();

	$out = ['baby' => null, 'middle' => null, 'top' => null];
	foreach ($rows as $row) {
		$blob = strtolower(trim(($row['level_title'] ?? '') . ' ' . ($row['class_title'] ?? '')));
		$id = (int) $row['id'];
		$info = [
			'id' => $id,
			'label' => trim(($row['level_title'] ?? '') . ' / ' . ($row['class_title'] ?? '')),
		];
		if (preg_match('/\bbaby\b/', $blob) && $out['baby'] === null) {
			$out['baby'] = $info;
		} elseif (preg_match('/\bmiddle\b/', $blob) && $out['middle'] === null) {
			$out['middle'] = $info;
		} elseif (preg_match('/\btop\b/', $blob) && $out['top'] === null) {
			$out['top'] = $info;
		}
	}

	return $out;
}

$classes = loadNurseryClasses($db, SCHOOL_ID);
if ($classes['baby'] === null) {
	fwrite(STDERR, "Baby class not found\n");
	exit(1);
}
echo "Baby: {$classes['baby']['label']} id={$classes['baby']['id']}\n";
echo "Middle: " . ($classes['middle']['label'] ?? 'MISSING') . "\n";
echo "Top: " . ($classes['top']['label'] ?? 'MISSING') . "\n";
echo "Year id={$yearId}\n";

$babyCourses = $db->query(
	"SELECT cr.id AS record_id, cr.course AS course_id, cr.lecturer, cr.term,
	        c.title, c.code, c.category, c.credit, c.marks
	 FROM course_records cr
	 JOIN courses c ON c.id = cr.course
	 WHERE cr.class = ? AND cr.year = ?
	 ORDER BY c.title ASC",
	[(int) $classes['baby']['id'], $yearId]
)->getResultArray();

if ($babyCourses === []) {
	fwrite(STDERR, "Baby class has no course assignments for this year\n");
	exit(1);
}

echo "Baby courses (" . count($babyCourses) . "):\n";
foreach ($babyCourses as $row) {
	echo "  - {$row['title']} credit={$row['credit']} marks={$row['marks']} lecturer={$row['lecturer']}\n";
}

/**
 * Prefer an existing lecturer already teaching on the target class; else Baby's lecturer.
 */
function lecturerForTarget(\CodeIgniter\Database\BaseConnection $db, int $classId, int $yearId, int $fallback): int
{
	$row = $db->query(
		"SELECT lecturer, COUNT(*) AS n
		 FROM course_records
		 WHERE class = ? AND year = ? AND lecturer > 0
		 GROUP BY lecturer
		 ORDER BY n DESC
		 LIMIT 1",
		[$classId, $yearId]
	)->getRowArray();
	$existing = (int) ($row['lecturer'] ?? 0);

	return $existing > 0 ? $existing : $fallback;
}

$created = 0;
$updated = 0;
$ok = 0;

foreach (['middle', 'top'] as $key) {
	$target = $classes[$key];
	if ($target === null) {
		echo "SKIP {$key}: class not found\n";
		continue;
	}
	$classId = (int) $target['id'];
	$defaultLecturer = lecturerForTarget(
		$db,
		$classId,
		$yearId,
		(int) ($babyCourses[0]['lecturer'] ?? 0)
	);
	echo "\n=== Sync to {$target['label']} (lecturer fallback={$defaultLecturer}) ===\n";

	foreach ($babyCourses as $src) {
		$courseId = (int) $src['course_id'];
		$title = (string) $src['title'];
		$credit = (float) $src['credit'];
		$marks = (float) $src['marks'];
		$lecturer = (int) ($src['lecturer'] ?? 0);
		if ($lecturer <= 0) {
			$lecturer = $defaultLecturer;
		}

		// Keep course credit/marks aligned with Baby (shared course row).
		$courseRow = $db->table('courses')->where('id', $courseId)->get(1)->getRowArray();
		if ($courseRow) {
			$needCourseUpdate = ((float) ($courseRow['credit'] ?? 0) !== $credit)
				|| ((float) ($courseRow['marks'] ?? 0) !== $marks);
			if ($needCourseUpdate) {
				if ($dryRun) {
					echo "WOULD update course {$title} credit/marks\n";
				} else {
					$db->table('courses')->where('id', $courseId)->update([
						'credit' => $credit,
						'marks' => $marks,
					]);
					echo "Updated course {$title} credit={$credit} marks={$marks}\n";
				}
			}
		}

		$existing = $db->table('course_records')
			->where('course', $courseId)
			->where('class', $classId)
			->where('year', $yearId)
			->get(1)
			->getRowArray();

		if ($existing) {
			$same = ((int) ($existing['lecturer'] ?? 0) === $lecturer)
				|| ((int) ($existing['lecturer'] ?? 0) === $defaultLecturer);
			$termOk = (string) ($existing['term'] ?? '') === ALL_TERMS
				|| (string) ($existing['term'] ?? '') === (string) ($src['term'] ?? ALL_TERMS);
			if ($same && $termOk) {
				echo "OK {$title}\n";
				$ok++;
				continue;
			}
			$payload = [
				'lecturer' => ((int) ($existing['lecturer'] ?? 0) > 0)
					? (int) $existing['lecturer']
					: ($lecturer > 0 ? $lecturer : $defaultLecturer),
				'term' => ALL_TERMS,
			];
			if ($dryRun) {
				echo "WOULD update assignment {$title}\n";
			} else {
				$db->table('course_records')->where('id', (int) $existing['id'])->update($payload);
				echo "Updated assignment {$title}\n";
			}
			$updated++;
			continue;
		}

		// Title match: class may already have a differently-id'd course with same title.
		$byTitle = $db->query(
			"SELECT cr.id, cr.course, cr.lecturer, c.title
			 FROM course_records cr
			 JOIN courses c ON c.id = cr.course
			 WHERE cr.class = ? AND cr.year = ? AND LOWER(TRIM(c.title)) = LOWER(TRIM(?))
			 LIMIT 1",
			[$classId, $yearId, $title]
		)->getRowArray();
		if ($byTitle) {
			if ($dryRun) {
				echo "WOULD realign titled course {$title} -> baby course_id={$courseId}\n";
			} else {
				$db->table('course_records')->where('id', (int) $byTitle['id'])->update([
					'course' => $courseId,
					'lecturer' => ((int) ($byTitle['lecturer'] ?? 0) > 0)
						? (int) $byTitle['lecturer']
						: ($lecturer > 0 ? $lecturer : $defaultLecturer),
					'term' => ALL_TERMS,
				]);
				echo "Realigned {$title} to Baby course id\n";
			}
			$updated++;
			continue;
		}

		if ($dryRun) {
			echo "WOULD assign {$title}\n";
		} else {
			$db->table('course_records')->insert([
				'course' => $courseId,
				'lecturer' => $lecturer > 0 ? $lecturer : $defaultLecturer,
				'class' => $classId,
				'year' => $yearId,
				'term' => ALL_TERMS,
			]);
			echo "Assigned {$title}\n";
		}
		$created++;
	}
}

echo "\nDone. created={$created} updated={$updated} ok={$ok}" . ($dryRun ? ' (dry-run)' : '') . "\n";
exit(0);
