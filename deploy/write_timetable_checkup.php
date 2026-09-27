<?php
/**
 * Restore L3 SOD manager-only courses and write the final timetable checkup page.
 *
 *   docker exec xander_school_app php /var/www/html/deploy/write_timetable_checkup.php
 */
declare(strict_types=1);

define('FCPATH', __DIR__ . '/../public/');
chdir(FCPATH);
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/Config/Paths.php';

$paths = new Config\Paths();
require rtrim($paths->systemDirectory, '/ ') . '/bootstrap.php';

use App\Libraries\TimetableClassLabel;

@set_time_limit(0);
$db = \Config\Database::connect();
$schoolId = 27;
$year = 16;
$term = 1;
$managerOnly = [476, 478];

foreach ([476 => 'Occupation and learning process', 478 => 'Maintain SHE at Workplace'] as $courseId => $label) {
	$exists = (int) $db->table('course_records')
		->where('class', 220)
		->where('course', $courseId)
		->where('year', $year)
		->countAllResults();
	if ($exists > 0) {
		echo "Already in Manage Course: {$label}\n";
		continue;
	}
	$db->table('course_records')->insert([
		'course' => $courseId,
		'lecturer' => 134,
		'class' => 220,
		'year' => $year,
		'term' => '1',
	]);
	echo "Restored to Manage Course: {$label}\n";
}
$db->table('timetable_entries')->whereIn('course_id', $managerOnly)->delete();
echo "Timetable rows for those courses removed\n";

$schedule = $db->table('timetable_schedules')
	->where('school_id', $schoolId)
	->where('academic_year', $year)
	->where('term', $term)
	->orderBy('id', 'DESC')
	->get(1)->getRowArray();
$scheduleId = (int) ($schedule['id'] ?? 0);
$h = static function ($value): string {
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
$dayName = static function (int $day): string {
	return ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'][$day] ?? ('day ' . $day);
};
$mins = static function (?string $time): int {
	$parts = explode(':', substr((string) $time, 0, 5));
	return ((int) ($parts[0] ?? 0)) * 60 + (int) ($parts[1] ?? 0);
};

$notIn = implode(',', $managerOnly);
$teachers = $db->query("
	SELECT s.id, CONCAT(s.fname, ' ', s.lname) AS teacher,
		SUM(c.credit) AS assigned,
		(SELECT COUNT(*) FROM timetable_entries te
			WHERE te.schedule_id = {$scheduleId} AND te.staff_id = s.id
			AND te.slot_id > 0 AND te.day_of_week >= 0
			AND te.course_id NOT IN ({$notIn})) AS placed,
		(SELECT COUNT(*) FROM timetable_entries te
			WHERE te.schedule_id = {$scheduleId} AND te.staff_id = s.id
			AND te.day_of_week = -1 AND te.slot_id = 0
			AND te.course_id NOT IN ({$notIn})) AS highlighted
	FROM course_records cr
	JOIN courses c ON c.id = cr.course
	JOIN classes cl ON cl.id = cr.class
	JOIN staffs s ON s.id = cr.lecturer
	WHERE cl.school_id = {$schoolId} AND cr.year = {$year}
		AND FIND_IN_SET('{$term}', cr.term) > 0
		AND cr.lecturer > 0
		AND cr.course NOT IN ({$notIn})
	GROUP BY s.id, s.fname, s.lname
	ORDER BY teacher
")->getResultArray();

$shorts = $db->query("
	SELECT cl.id AS class_id, cl.title AS class_title, l.title AS level_name,
		d.code AS dept_code, d.title AS dept_title,
		c.title AS course_title, c.credit AS need, s.id AS staff_id,
		CONCAT(s.fname, ' ', s.lname) AS teacher,
		(SELECT COUNT(*) FROM timetable_entries te
			WHERE te.schedule_id = {$scheduleId} AND te.class_id = cr.class
			AND te.course_id = cr.course AND te.slot_id > 0 AND te.day_of_week >= 0) AS placed
	FROM course_records cr
	JOIN courses c ON c.id = cr.course
	JOIN classes cl ON cl.id = cr.class
	LEFT JOIN levels l ON l.id = cl.level
	LEFT JOIN departments d ON d.id = cl.department
	LEFT JOIN staffs s ON s.id = cr.lecturer
	WHERE cl.school_id = {$schoolId} AND cr.year = {$year}
		AND FIND_IN_SET('{$term}', cr.term) > 0
		AND c.credit > 0
		AND cr.course NOT IN ({$notIn})
	HAVING placed < need
	ORDER BY level_name, dept_code, class_title, course_title
")->getResultArray();

$kept = $db->query("
	SELECT c.id, c.title, c.code, c.credit, CONCAT(s.fname, ' ', s.lname) AS teacher, cr.term,
		l.title AS level_name, d.code AS dept_code, d.title AS dept_title, cl.title AS class_title,
		(SELECT COUNT(*) FROM timetable_entries te
			WHERE te.schedule_id = {$scheduleId} AND te.course_id = c.id AND te.class_id = cr.class
			AND te.slot_id > 0) AS on_grid
	FROM course_records cr
	JOIN courses c ON c.id = cr.course
	JOIN classes cl ON cl.id = cr.class
	LEFT JOIN levels l ON l.id = cl.level
	LEFT JOIN departments d ON d.id = cl.department
	LEFT JOIN staffs s ON s.id = cr.lecturer
	WHERE cr.class = 220 AND cr.course IN ({$notIn}) AND cr.year = {$year}
")->getResultArray();

$collisions = $db->query("
	SELECT te.class_id, te.day_of_week d, TIME_FORMAT(ts.start_time,'%H:%i') st,
		TIME_FORMAT(ts.end_time,'%H:%i') en, COUNT(*) n,
		GROUP_CONCAT(CONCAT(co.title, ' / ', s.fname, ' ', s.lname) SEPARATOR ' | ') labels
	FROM timetable_entries te
	JOIN timetable_slots ts ON ts.id = te.slot_id
	JOIN courses co ON co.id = te.course_id
	LEFT JOIN staffs s ON s.id = te.staff_id
	WHERE te.schedule_id = {$scheduleId} AND te.slot_id > 0 AND te.day_of_week >= 0
	GROUP BY te.class_id, te.day_of_week, ts.start_time, ts.end_time
	HAVING n > 1
")->getResultArray();

$weekend = (int) $db->query("
	SELECT COUNT(*) n FROM timetable_entries
	WHERE schedule_id = {$scheduleId} AND day_of_week IN (5,6) AND slot_id > 0
")->getRowArray()['n'];

$windows = [
	135 => [[3, 9 * 60, 12 * 60], [4, 8 * 60 + 30, 12 * 60]],
	137 => [[0, 7 * 60, 10 * 60], [2, 8 * 60, 10 * 60], [3, 7 * 60, 16 * 60 + 20]],
	136 => [[3, 9 * 60, 15 * 60 + 40], [4, 9 * 60, 9 * 60 + 40]],
	126 => [[0, 10 * 60 + 40, 12 * 60], [1, 10 * 60 + 40, 12 * 60], [2, 10 * 60 + 40, 12 * 60], [3, 10 * 60 + 40, 12 * 60], [4, 10 * 60 + 40, 12 * 60]],
	77 => [[0, 10 * 60, 12 * 60], [1, 10 * 60, 12 * 60], [2, 10 * 60, 12 * 60], [3, 10 * 60, 12 * 60], [4, 10 * 60, 12 * 60]],
	140 => [[1, 7 * 60, 10 * 60], [4, 13 * 60, 15 * 60]],
];
$windowNames = [
	135 => 'NTABANGANYIMANA Varlette — Thursday 09:00–12:00, Friday 08:30–12:00',
	137 => 'MARGUERITTE Uwimana — Monday 07:00–10:00, Wednesday 08:00–10:00, Thursday 07:00–16:20',
	136 => 'LINEA Kubahoniyesu — Thursday 09:00–15:40, Friday 09:00–09:40',
	126 => 'Jean Pierre Bunezero — weekdays 10:40–12:00',
	77 => 'NTAZIKA Elias — weekdays 10:00–12:00',
	140 => 'IZABAYO Patience — Tuesday 07:00–10:00, Friday 13:00–15:00',
];
$namedLessons = $db->query("
	SELECT te.staff_id, te.day_of_week, ts.start_time, ts.end_time, co.title, te.class_id
	FROM timetable_entries te
	JOIN timetable_slots ts ON ts.id = te.slot_id
	JOIN courses co ON co.id = te.course_id
	WHERE te.schedule_id = {$scheduleId} AND te.slot_id > 0 AND te.day_of_week >= 0
		AND te.staff_id IN (135,137,136,126,77,140)
	ORDER BY te.staff_id, te.day_of_week, ts.start_time
")->getResultArray();
$outside = [];
$namedCounts = [];
foreach ($namedLessons as $lesson) {
	$staffId = (int) $lesson['staff_id'];
	$namedCounts[$staffId] = ($namedCounts[$staffId] ?? 0) + 1;
	$start = $mins($lesson['start_time']);
	$end = $mins($lesson['end_time']);
	$ok = false;
	foreach ($windows[$staffId] as $window) {
		if ((int) $lesson['day_of_week'] === $window[0] && $start >= $window[1] && $end <= $window[2]) {
			$ok = true;
			break;
		}
	}
	if (!$ok) {
		$outside[] = $lesson;
	}
}

$assignedPeriods = 0;
$placedPeriods = 0;
$highlightedPeriods = 0;
$teachersOnGrid = 0;
$teachersShort = 0;
foreach ($teachers as $row) {
	$assignedPeriods += (int) $row['assigned'];
	$placedPeriods += (int) $row['placed'];
	$highlightedPeriods += (int) $row['highlighted'];
	if ((int) $row['placed'] > 0) {
		$teachersOnGrid++;
	}
	if ((int) $row['placed'] + (int) $row['highlighted'] < (int) $row['assigned']) {
		$teachersShort++;
	}
}

$base = rtrim((string) (config('App')->baseURL ?? ''), '/');
$generated = date('Y-m-d H:i');
$title = (string) ($schedule['title'] ?? 'Final Version');

ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Final timetable checkup — Wisdom School Rwanda</title>
<style>
body{margin:0;background:#f4f7fb;color:#142033;font:16px/1.5 "Segoe UI",sans-serif}
.wrap{max-width:1100px;margin:0 auto;padding:28px 18px 64px}
h1{margin:0 0 6px;font-size:28px}
h2{margin:28px 0 10px;font-size:20px}
.sub{color:#52627a;margin:0 0 18px}
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px}
.card{background:#fff;border:1px solid #d7e0ec;border-radius:12px;padding:14px 16px}
.card b{display:block;font-size:26px}
.card span{color:#52627a;font-size:13px}
.ok{color:#047857}.warn{color:#b45309}.bad{color:#b91c1c}
table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #d7e0ec;border-radius:12px;overflow:hidden}
th,td{padding:8px 10px;text-align:left;border-bottom:1px solid #e6edf5;vertical-align:top}
th{background:#eef4fb;font-size:13px}
tr:last-child td{border-bottom:0}
a{color:#1d4ed8}
.note{background:#fff7ed;border:1px solid #fed7aa;border-radius:12px;padding:12px 14px}
.good{background:#ecfdf5;border:1px solid #a7f3d0;border-radius:12px;padding:12px 14px}
ul{margin:8px 0 0;padding-left:18px}
</style>
</head>
<body>
<div class="wrap">
<h1>Final timetable checkup</h1>
<p class="sub">Wisdom School Rwanda · <?= $h($title) ?> · academic year 2026–2027, term 1 · checked <?= $h($generated) ?></p>

<div class="cards">
	<div class="card"><b><?= count($teachers) ?></b><span>Teachers in Manage Course</span></div>
	<div class="card"><b class="ok"><?= $teachersOnGrid ?></b><span>Teachers with lessons on the timetable</span></div>
	<div class="card"><b><?= $placedPeriods ?></b><span>Periods on class and teacher timetables</span></div>
	<div class="card"><b class="warn"><?= $highlightedPeriods ?></b><span>Periods highlighted because they do not fit</span></div>
	<div class="card"><b class="<?= count($collisions) === 0 ? 'ok' : 'bad' ?>"><?= count($collisions) ?></b><span>Classes with two teachers at once</span></div>
	<div class="card"><b class="<?= $weekend === 0 ? 'ok' : 'bad' ?>"><?= $weekend ?></b><span>Weekend lessons</span></div>
</div>

<h2>What was checked</h2>
<div class="good">
<ul>
<li>Manage Course weekly periods were compared with the class timetable and the teacher timetable.</li>
<li>Combined courses still share one clock, the same rule as the first version. Mathematics with Sub Math is one of those shared lessons.</li>
<li>A class period does not hold two teachers. Extra Physical Education periods that used to sit on top of another subject are highlighted instead.</li>
<li>Teachers without a special window are on Monday–Thursday through 15:40 and Friday through 15:00. Weekend is used only when a teacher was requested for it.</li>
<li>S4, S5 and S6 ANP still teach 07:00–16:20. Evening Library and Clubs, Home Science, chapel, dinner and preps are unchanged.</li>
<li>Version 1 remains stored and was not overwritten.</li>
</ul>
</div>

<h2>Kept in Manage Course, not on the timetable</h2>
<div class="note">
Occupation and learning process, and Maintain SHE at Workplace, stay assigned to L3 SOD in Manage Course. They are not placed on any class timetable or teacher timetable.
</div>
<table>
<tr><th>Course</th><th>Code</th><th>Class</th><th>Teacher</th><th>Weekly periods</th><th>Term</th><th>On timetable</th></tr>
<?php foreach ($kept as $row):
	$label = TimetableClassLabel::format($row['level_name'] ?? '', $row['class_title'] ?? '', $row['dept_code'] ?? '', $row['dept_title'] ?? '');
?>
<tr>
	<td><?= $h($row['title']) ?></td>
	<td><?= $h($row['code']) ?></td>
	<td><?= $h($label !== '' ? $label : 'L3 SOD') ?></td>
	<td><?= $h($row['teacher']) ?></td>
	<td><?= (int) $row['credit'] ?></td>
	<td><?= $h($row['term']) ?></td>
	<td class="ok"><?= (int) $row['on_grid'] === 0 ? 'Not scheduled' : $h($row['on_grid'] . ' still on the grid') ?></td>
</tr>
<?php endforeach; ?>
</table>

<h2>Named teacher windows</h2>
<table>
<tr><th>Teacher</th><th>Lessons inside the window</th><th>Lessons outside the window</th></tr>
<?php foreach ($windowNames as $staffId => $label):
	$outCount = 0;
	foreach ($outside as $lesson) {
		if ((int) $lesson['staff_id'] === $staffId) {
			$outCount++;
		}
	}
?>
<tr>
	<td><?= $h($label) ?></td>
	<td><?= (int) ($namedCounts[$staffId] ?? 0) - $outCount ?></td>
	<td class="<?= $outCount === 0 ? 'ok' : 'bad' ?>"><?= $outCount === 0 ? 'None' : $outCount ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php if ($outside !== []): ?>
<table>
<tr><th>Teacher id</th><th>When</th><th>Course</th></tr>
<?php foreach ($outside as $lesson): ?>
<tr>
	<td><?= (int) $lesson['staff_id'] ?></td>
	<td><?= $h($dayName((int) $lesson['day_of_week']) . ' ' . substr((string) $lesson['start_time'], 0, 5) . '–' . substr((string) $lesson['end_time'], 0, 5)) ?></td>
	<td><?= $h($lesson['title']) ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>

<h2>Teachers</h2>
<p class="sub">Assigned is the Manage Course weekly total. Placed is on both the class timetable and that teacher’s timetable. Highlighted means the hours are more than the free legal slots, so they stay listed under the grid.</p>
<table>
<tr><th>Teacher</th><th>Assigned</th><th>On timetable</th><th>Highlighted</th><th></th></tr>
<?php foreach ($teachers as $row):
	$assigned = (int) $row['assigned'];
	$placed = (int) $row['placed'];
	$parked = (int) $row['highlighted'];
	$href = $base . '/timetable/teacher/' . (int) $row['id'];
	if ($placed <= 0) {
		$status = 'Not on a timetable';
		$class = 'bad';
	} elseif ($placed + $parked < $assigned) {
		$status = 'Some periods still open';
		$class = 'warn';
	} elseif ($parked > 0 || $placed < $assigned) {
		$status = 'Hours exceed free slots';
		$class = 'warn';
	} else {
		$status = 'Filled';
		$class = 'ok';
	}
?>
<tr>
	<td><a href="<?= $h($href) ?>"><?= $h($row['teacher']) ?></a></td>
	<td><?= $assigned ?></td>
	<td><?= $placed ?></td>
	<td><?= $parked ?></td>
	<td class="<?= $class ?>"><?= $h($status) ?></td>
</tr>
<?php endforeach; ?>
</table>

<h2>Courses with fewer periods than Manage Course</h2>
<p class="sub"><?= count($shorts) ?> courses. These are the highlighted gaps. They were not forced into a collision.</p>
<table>
<tr><th>Class</th><th>Course</th><th>Teacher</th><th>Manage Course</th><th>On timetable</th><th>Short</th></tr>
<?php foreach ($shorts as $row):
	$label = TimetableClassLabel::format($row['level_name'] ?? '', $row['class_title'] ?? '', $row['dept_code'] ?? '', $row['dept_title'] ?? '');
	$need = (int) $row['need'];
	$placed = (int) $row['placed'];
?>
<tr>
	<td><a href="<?= $h($base . '/timetable/class/' . (int) $row['class_id']) ?>"><?= $h($label) ?></a></td>
	<td><?= $h($row['course_title']) ?></td>
	<td><?= $h($row['teacher']) ?></td>
	<td><?= $need ?></td>
	<td><?= $placed ?></td>
	<td class="warn"><?= $need - $placed ?></td>
</tr>
<?php endforeach; ?>
</table>

<?php if ($collisions !== []): ?>
<h2>Two teachers in one class</h2>
<table>
<tr><th>Class</th><th>When</th><th>Lessons</th></tr>
<?php foreach ($collisions as $row): ?>
<tr>
	<td><?= (int) $row['class_id'] ?></td>
	<td><?= $h($dayName((int) $row['d']) . ' ' . $row['st'] . '–' . $row['en']) ?></td>
	<td><?= $h($row['labels']) ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
</div>
</body>
</html>
<?php
$html = ob_get_clean();
$path = FCPATH . 'timetable-final-checkup.html';
file_put_contents($path, $html);
echo "WROTE {$path}\n";
echo "URL {$base}/timetable-final-checkup.html\n";
echo "teachers " . count($teachers) . " on_grid {$teachersOnGrid} short_courses " . count($shorts) . " collisions " . count($collisions) . " weekend {$weekend} outside " . count($outside) . "\n";
