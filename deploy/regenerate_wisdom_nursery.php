<?php
/**
 * Apply nursery HOME WORK afternoon band and regenerate Wisdom nursery only.
 *
 *   docker exec xander_school_app php /var/www/html/deploy/regenerate_wisdom_nursery.php [school_id]
 */
declare(strict_types=1);

define('FCPATH', __DIR__ . '/../public/');
chdir(FCPATH);
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/Config/Paths.php';

$paths = new Config\Paths();
require rtrim($paths->systemDirectory, '/ ') . '/bootstrap.php';

use App\Libraries\TimetableTrack;
use App\Models\TimetableSchemaModel;
use App\Services\Timetable\TimetableConflictService;
use App\Services\Timetable\TimetableGeneratorService;
use App\Services\Timetable\TimetableStagingService;

$schoolId = (int) ($argv[1] ?? 27);
$db = \Config\Database::connect();
$row = $db->table('schools s')
	->select('at.term, at.academic_year')
	->join('active_term at', 'at.id = s.active_term', 'left')
	->where('s.id', $schoolId)->get(1)->getRowArray();
if (!$row || empty($row['academic_year'])) {
	fwrite(STDERR, "No active year for school {$schoolId}\n");
	exit(1);
}

$year = (int) $row['academic_year'];
$term = max(1, (int) ($row['term'] ?? 1));
echo "School {$schoolId}, year {$year}, term {$term}\n";

$schema = new TimetableSchemaModel();
$schema->ensureSchema();
$schema->ensureTrackSlots($schoolId, TimetableTrack::NURSERY);
$n = $schema->restorePrimaryNurseryHourPeriods($schoolId);
echo "Nursery/primary period restore rows={$n}\n";

$slots = $db->table('timetable_slots')
	->where('school_id', $schoolId)
	->where('track_key', TimetableTrack::NURSERY)
	->orderBy('sort_order', 'ASC')
	->get()->getResultArray();
foreach ($slots as $slot) {
	$mark = !empty($slot['is_break']) ? ' [break]' : '';
	echo '  ' . $slot['label'] . ' ' . substr((string) $slot['start_time'], 0, 5)
		. '-' . substr((string) $slot['end_time'], 0, 5) . $mark . "\n";
}

$assignments = $db->table('course_records cr')
	->select('cr.id AS course_record_id, cr.course AS course_id, cr.lecturer, cr.class AS class_id,
		c.title AS course_title, c.code AS course_code, c.credit, c.marks, c.program_type,
		cc.title AS category_title, cl.title AS class_title, l.title AS level_name,
		d.code AS dept_code, d.title AS dept_title,
		CONCAT(s.fname, " ", s.lname) AS teacher_name')
	->join('courses c', 'c.id = cr.course')
	->join('course_category cc', 'cc.id = c.category', 'left')
	->join('classes cl', 'cl.id = cr.class')
	->join('levels l', 'l.id = cl.level', 'left')
	->join('departments d', 'd.id = cl.department', 'left')
	->join('staffs s', 's.id = cr.lecturer', 'left')
	->where('cl.school_id', $schoolId)
	->where('cr.year', $year)
	->where("find_in_set($term, cr.term) > 0", null, false)
	->get()->getResultArray();

$nurseryAssignments = [];
$nurseryClassIds = [];
foreach ($assignments as $assignment) {
	$classId = (int) ($assignment['class_id'] ?? 0);
	if ($classId <= 0) {
		continue;
	}
	$track = $schema->trackForClass($schoolId, $classId);
	if ($track !== TimetableTrack::NURSERY) {
		continue;
	}
	$assignment['_track_key'] = $track;
	$nurseryAssignments[] = $assignment;
	$nurseryClassIds[$classId] = true;
}
$classIdList = array_keys($nurseryClassIds);
echo 'Nursery classes: ' . implode(',', $classIdList) . "\n";
echo 'Nursery assignments: ' . count($nurseryAssignments) . "\n";
if ($nurseryAssignments === [] || $classIdList === []) {
	fwrite(STDERR, "No nursery assignments.\n");
	exit(1);
}

$settings = $db->table('timetable_settings')->where('school_id', $schoolId)->get(1)->getRowArray();
$days = TimetableSchemaModel::weekDaysForTrack($settings, TimetableTrack::NURSERY);
$blocked = [];
foreach ($schema->specialTimesMap($schoolId, TimetableTrack::NURSERY) as $key => $special) {
	$blocked[$key] = true;
}

$existing = $db->table('timetable_schedules')
	->where('school_id', $schoolId)->where('academic_year', $year)->where('term', $term)
	->orderBy('id', 'DESC')->get(1)->getRowArray();
if (!$existing) {
	fwrite(STDERR, "No timetable schedule yet. Generate from the dashboard first.\n");
	exit(1);
}
$scheduleId = (int) $existing['id'];

$keepBusy = $db->table('timetable_entries te')
	->select('te.class_id, te.staff_id, te.course_id, te.day_of_week, te.slot_id, ts.start_time, ts.end_time')
	->join('timetable_slots ts', 'ts.id = te.slot_id', 'left')
	->where('te.schedule_id', $scheduleId)
	->where('te.entry_type', 'lesson')
	->where('te.day_of_week >=', 0)
	->where('te.slot_id >', 0)
	->whereNotIn('te.class_id', $classIdList)
	->get()->getResultArray();

$slotTimesById = [];
foreach ($keepBusy as $busy) {
	$sid = (int) ($busy['slot_id'] ?? 0);
	if ($sid > 0) {
		$slotTimesById[$sid] = [
			'start' => (string) ($busy['start_time'] ?? '00:00:00'),
			'end' => (string) ($busy['end_time'] ?? '00:00:00'),
		];
	}
}

$generator = new TimetableGeneratorService();
if ($keepBusy !== []) {
	$generator->seedBusyFromEntries($keepBusy, $slotTimesById);
}
$result = $generator->generate(
	$nurseryAssignments,
	$schema->teachingSlots($schoolId, TimetableTrack::NURSERY),
	$days,
	$blocked,
	$keepBusy === []
);
if (!empty($result['assignments'])) {
	$nurseryAssignments = $result['assignments'];
}
echo 'Generated entries: ' . count($result['entries']) . "\n";
$seenCourses = [];
foreach ($nurseryAssignments as $assignment) {
	$cid = (int) ($assignment['course_id'] ?? 0);
	if ($cid <= 0 || isset($seenCourses[$cid])) {
		continue;
	}
	$seenCourses[$cid] = true;
	$core = ((int) round((float) ($assignment['marks'] ?? 0)) >= 100) ? 'CORE' : 'other';
	echo 'CREDIT ' . ($assignment['course_title'] ?? '') . " {$core} periods="
		. (int) \App\Services\Timetable\TimetableGeneratorService::weeklyHoursFromCourse($assignment) . "\n";
}
foreach ($result['warnings'] as $warning) {
	echo 'WARN ' . $warning . "\n";
}

$db->table('timetable_entries')
	->where('schedule_id', $scheduleId)
	->whereIn('class_id', $classIdList)
	->delete();

$filtered = (new TimetableConflictService())->filterCollisionFreeEntries(
	$result['entries'],
	$slotTimesById,
	$keepBusy
);
$kept = $filtered['kept'];
foreach ($filtered['rejected'] as $rejected) {
	$rejected['day_of_week'] = -1;
	$rejected['slot_id'] = 0;
	unset($rejected['_reject_reason']);
	$kept[] = $rejected;
}

$hasCustom = $db->fieldExists('custom_label', 'timetable_entries');
$hasLocked = $db->fieldExists('is_locked', 'timetable_entries');
foreach ($kept as $entry) {
	$row = [
		'schedule_id' => $scheduleId,
		'school_id' => $schoolId,
		'class_id' => $entry['class_id'],
		'staff_id' => $entry['staff_id'],
		'course_id' => $entry['course_id'],
		'course_record_id' => $entry['course_record_id'] ?: null,
		'day_of_week' => $entry['day_of_week'],
		'slot_id' => $entry['slot_id'],
		'entry_type' => $entry['entry_type'] ?? 'lesson',
	];
	if ($hasCustom) {
		$row['custom_label'] = $entry['custom_label'] ?? null;
	}
	if ($hasLocked) {
		$row['is_locked'] = 0;
	}
	$db->table('timetable_entries')->insert($row);
}

$staging = new TimetableStagingService();
$staging->reconcile($scheduleId, $schoolId, $nurseryAssignments);
$staging->fillMorningGaps($scheduleId, $schoolId, $schema);

$scheduled = (int) $db->table('timetable_entries')
	->where('schedule_id', $scheduleId)
	->whereIn('class_id', $classIdList)
	->where('day_of_week >=', 0)->where('slot_id >', 0)
	->countAllResults();
$parking = (int) $db->table('timetable_entries')
	->where('schedule_id', $scheduleId)
	->whereIn('class_id', $classIdList)
	->where('day_of_week', -1)->where('slot_id', 0)
	->countAllResults();

$idsSql = implode(',', array_map('intval', $classIdList));
$afterLunch = $db->query(
	"SELECT te.id, te.day_of_week, ts.label, ts.start_time, c.title
	 FROM timetable_entries te
	 JOIN timetable_slots ts ON ts.id = te.slot_id
	 JOIN classes cl ON cl.id = te.class_id
	 LEFT JOIN courses c ON c.id = te.course_id
	 WHERE te.schedule_id = {$scheduleId} AND te.class_id IN ({$idsSql})
	   AND te.day_of_week >= 0 AND te.slot_id > 0
	   AND ts.start_time >= '13:00:00'"
)->getResultArray();

echo "Schedule {$scheduleId}: scheduled={$scheduled}, parking={$parking}, after-lunch=" . count($afterLunch) . "\n";
foreach ($afterLunch as $bad) {
	echo 'AFTER_LUNCH ' . json_encode($bad) . "\n";
}

$days = $db->query(
	"SELECT te.class_id, cl.title AS class_title, te.day_of_week,
	        COUNT(*) AS periods, COUNT(DISTINCT te.course_id) AS courses
	 FROM timetable_entries te
	 JOIN classes cl ON cl.id = te.class_id
	 JOIN timetable_slots ts ON ts.id = te.slot_id
	 WHERE te.schedule_id = {$scheduleId} AND te.class_id IN ({$idsSql})
	   AND te.day_of_week >= 0 AND te.slot_id > 0 AND ts.is_break = 0
	 GROUP BY te.class_id, cl.title, te.day_of_week
	 ORDER BY cl.title, te.day_of_week"
)->getResultArray();
foreach ($days as $d) {
	echo "DAY {$d['class_title']} d{$d['day_of_week']} periods={$d['periods']} courses={$d['courses']}\n";
}

$courseCounts = $db->query(
	"SELECT cl.title AS class_title, c.title AS course_title, c.marks, c.credit, COUNT(*) AS periods
	 FROM timetable_entries te
	 JOIN classes cl ON cl.id = te.class_id
	 JOIN courses c ON c.id = te.course_id
	 JOIN timetable_slots ts ON ts.id = te.slot_id
	 WHERE te.schedule_id = {$scheduleId} AND te.class_id IN ({$idsSql})
	   AND te.day_of_week >= 0 AND te.slot_id > 0 AND ts.is_break = 0
	 GROUP BY cl.title, c.title, c.marks, c.credit
	 ORDER BY cl.title, c.marks DESC, periods DESC, c.title"
)->getResultArray();
foreach ($courseCounts as $row) {
	$core = ((int) round((float) $row['marks']) >= 100) ? 'CORE' : 'other';
	echo "COURSE {$row['class_title']} {$row['course_title']} {$core} marks={$row['marks']} credit={$row['credit']} placed={$row['periods']}\n";
}

echo "Schedule {$scheduleId}: scheduled={$scheduled}, parking={$parking}, after-lunch=" . count($afterLunch) . "\n";
foreach ($afterLunch as $bad) {
	echo 'AFTER_LUNCH ' . json_encode($bad) . "\n";
}
echo "DONE\n";
exit(0);
