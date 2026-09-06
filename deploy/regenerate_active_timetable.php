<?php
/**
 * Regenerate timetable for active year/term (uses dedupe + auto-place fixes).
 * Run: docker exec xander_school_app php /var/www/html/deploy/regenerate_active_timetable.php [school_id]
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
use App\Services\Timetable\TimetableGeneratorService;
use App\Services\Timetable\TimetableStagingService;

$schoolId = (int) ($argv[1] ?? 0);
if ($schoolId <= 0) {
	$schoolId = 27;
}

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

$assignments = $db->table('course_records cr')
	->select('cr.id AS course_record_id, cr.course AS course_id, cr.lecturer, cr.class AS class_id,
		c.title AS course_title, c.code AS course_code, c.credit, c.marks, c.program_type,
		cc.title AS category_title, cl.title AS class_title')
	->join('courses c', 'c.id = cr.course')
	->join('course_category cc', 'cc.id = c.category', 'left')
	->join('classes cl', 'cl.id = cr.class')
	->where('cl.school_id', $schoolId)
	->where('cr.year', $year)
	->where("find_in_set($term, cr.term) > 0", null, false)
	->get()->getResultArray();

$categoryPriority = ['primary subjects' => 1, 'examinable subjects' => 2, 'non-examinable subjects' => 3];
$byKey = [];
foreach ($assignments as $a) {
	$classId = (int) ($a['class_id'] ?? 0);
	$title = strtolower(trim(preg_replace('/\s+/', ' ', (string) ($a['course_title'] ?? ''))));
	if ($classId <= 0 || $title === '') {
		continue;
	}
	$key = $classId . '|' . $title;
	$cat = strtolower(trim((string) ($a['category_title'] ?? '')));
	$prio = $categoryPriority[$cat] ?? 50;
	$score = $prio * 1000 - ((int) ($a['lecturer'] ?? 0) > 0 ? 0 : 500);
	if (!isset($byKey[$key]) || $score < ($byKey[$key]['_score'] ?? PHP_INT_MAX)) {
		$a['_score'] = $score;
		$byKey[$key] = $a;
	}
}
$assignments = array_values(array_map(static function ($r) {
	unset($r['_score']);
	return $r;
}, $byKey));

echo 'Assignments after dedupe: ' . count($assignments) . "\n";
if ($assignments === []) {
	fwrite(STDERR, "No course assignments.\n");
	exit(1);
}

$schema = new TimetableSchemaModel();
$schema->ensureSchema();
$schema->seedDefaultSlots($schoolId);
$schema->ensureTrackSlots($schoolId, TimetableTrack::ALL);
if (!$schema->isSharedSchedule($schoolId)) {
	foreach (TimetableTrack::tracksForSchool($schoolId) as $track) {
		$schema->ensureTrackSlots($schoolId, $track);
	}
}

$settings = $db->table('timetable_settings')->where('school_id', $schoolId)->get(1)->getRowArray();
$days = TimetableSchemaModel::weekDaysFromSettings($settings);
$generator = new TimetableGeneratorService();
$allEntries = [];
$byTrack = [];
foreach ($assignments as $assignment) {
	$track = $schema->trackForClass($schoolId, (int) $assignment['class_id']);
	$byTrack[$track][] = $assignment;
}
$reset = true;
foreach ($byTrack as $trackKey => $trackAssignments) {
	$blocked = $schema->specialTimesMap($schoolId, $trackKey);
	$result = $generator->generate(
		$trackAssignments,
		$schema->teachingSlots($schoolId, $trackKey),
		$days,
		$blocked,
		$reset
	);
	$reset = false;
	$allEntries = array_merge($allEntries, $result['entries']);
}

$existing = $db->table('timetable_schedules')
	->where('school_id', $schoolId)->where('academic_year', $year)->where('term', $term)
	->get(1)->getRowArray();

if ($existing) {
	$scheduleId = (int) $existing['id'];
	$db->table('timetable_entries')->where('schedule_id', $scheduleId)->delete();
	$db->table('timetable_schedules')->where('id', $scheduleId)->update([
		'status' => 'published',
		'generated_at' => date('Y-m-d H:i:s'),
	]);
} else {
	$db->table('timetable_schedules')->insert([
		'school_id' => $schoolId,
		'academic_year' => $year,
		'term' => $term,
		'title' => 'Main timetable',
		'status' => 'published',
		'generated_at' => date('Y-m-d H:i:s'),
	]);
	$scheduleId = (int) $db->insertID();
}

foreach ($allEntries as $entry) {
	$db->table('timetable_entries')->insert([
		'schedule_id' => $scheduleId,
		'school_id' => $schoolId,
		'class_id' => $entry['class_id'],
		'staff_id' => $entry['staff_id'],
		'course_id' => $entry['course_id'],
		'course_record_id' => $entry['course_record_id'] ?: null,
		'day_of_week' => $entry['day_of_week'],
		'slot_id' => $entry['slot_id'],
		'entry_type' => $entry['entry_type'],
	]);
}

$staging = new TimetableStagingService();
$staging->reconcile($scheduleId, $schoolId, $assignments);
$placed = $staging->autoPlaceStaging($scheduleId, $schoolId, $schema);
$conflicts = $staging->normalizeScheduleConflicts($scheduleId, $schoolId, $schema);

$scheduled = (int) $db->table('timetable_entries')
	->where('schedule_id', $scheduleId)
	->where('day_of_week >=', 0)->where('slot_id >', 0)->countAllResults();
$parking = (int) $db->table('timetable_entries')
	->where('schedule_id', $scheduleId)
	->where('day_of_week', -1)->where('slot_id', 0)->countAllResults();

echo "Schedule {$scheduleId}: generated=" . count($allEntries) . ", scheduled={$scheduled}, parking={$parking}, auto-placed={$placed}, conflict-moved=" . ($conflicts['moved_to_parking'] ?? 0) . ", conflict-replaced=" . ($conflicts['replaced'] ?? 0) . ", conflicts-left=" . ($conflicts['remaining_conflicts'] ?? 0) . "\n";
exit(0);
