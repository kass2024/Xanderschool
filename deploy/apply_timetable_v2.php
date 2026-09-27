<?php
/**
 * Timetable version 2 for Wisdom Rwanda (school 27).
 * Version 1 tables are copied once (*_v1) and left in place.
 *
 *   docker exec xander_school_app php /var/www/html/deploy/apply_timetable_v2.php
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

@set_time_limit(0);
@ini_set('memory_limit', '1024M');

$schoolId = 27;
$db = \Config\Database::connect();
$schema = new TimetableSchemaModel();
$schema->ensureSchema();

$snapshotTables = [
	'timetable_entries',
	'timetable_slots',
	'timetable_special_times',
	'timetable_settings',
	'timetable_schedules',
	'timetable_custom_criteria',
];
foreach ($snapshotTables as $table) {
	$copy = $table . '_v1';
	if ($db->tableExists($copy)) {
		echo "Keeping existing snapshot {$copy}\n";
		continue;
	}
	$db->query("CREATE TABLE `{$copy}` AS SELECT * FROM `{$table}`");
	echo "Saved version 1 snapshot {$copy}\n";
}

$row = $db->table('schools s')
	->select('at.term, at.academic_year')
	->join('active_term at', 'at.id = s.active_term', 'left')
	->where('s.id', $schoolId)->get(1)->getRowArray();
$year = (int) ($row['academic_year'] ?? 16);
$term = max(1, (int) ($row['term'] ?? 1));
echo "School {$schoolId} year {$year} term {$term}\n";

$hsTracks = [TimetableTrack::O_LEVEL, TimetableTrack::A_LEVEL, TimetableTrack::SPECIAL, TimetableTrack::RTB];
foreach ($hsTracks as $track) {
	alignEveningSlots($db, $schoolId, $track);
	rewriteEveningSpecials($db, $schoolId, $track);
}

$db->table('course_records')
	->where('class', 220)
	->whereIn('course', [476, 478])
	->delete();
echo "Removed L3 SOD course records\n";

$sample = $db->table('course_records')->where('id', 2082)->get()->getRowArray();
if (!$sample) {
	fwrite(STDERR, "Home Science sample record missing\n");
	exit(1);
}
unset($sample['id']);
$homeClasses = [225, 227, 224, 226, 228, 193, 196, 197, 198, 232, 195];
$added = 0;
foreach ($homeClasses as $classId) {
	$exists = (int) $db->table('course_records')
		->where('class', $classId)
		->where('course', 505)
		->where('year', $year)
		->countAllResults();
	if ($exists > 0) {
		continue;
	}
	$insert = $sample;
	$insert['class'] = $classId;
	$insert['course'] = 505;
	$insert['year'] = $year;
	$insert['lecturer'] = 220;
	$db->table('course_records')->insert($insert);
	$added++;
}
echo "Added Home Science assignments: {$added}\n";

$assignments = loadAssignments($db, $schoolId, $year, $term);
$byTrack = [];
$classTrack = [];
foreach ($assignments as $assignment) {
	$classId = (int) $assignment['class_id'];
	$track = $schema->trackForClass($schoolId, $classId);
	$assignment['_track_key'] = $track;
	$byTrack[$track][] = $assignment;
	$classTrack[$classId] = $track;
}
$hsClassIds = [];
foreach ($hsTracks as $track) {
	foreach ($byTrack[$track] ?? [] as $assignment) {
		$hsClassIds[(int) $assignment['class_id']] = true;
	}
}
$hsClassIds = array_keys($hsClassIds);

$schedule = $db->table('timetable_schedules')
	->where('school_id', $schoolId)
	->where('academic_year', $year)
	->where('term', $term)
	->orderBy('id', 'DESC')
	->get(1)->getRowArray();
if (!$schedule) {
	fwrite(STDERR, "No schedule to update\n");
	exit(1);
}
$scheduleId = (int) $schedule['id'];
$keep = [];
if ($hsClassIds !== []) {
	$keep = $db->table('timetable_entries te')
		->select('te.class_id, te.staff_id, te.course_id, te.day_of_week, te.slot_id, ts.start_time, ts.end_time')
		->join('timetable_slots ts', 'ts.id = te.slot_id', 'left')
		->where('te.schedule_id', $scheduleId)
		->where('te.day_of_week >=', 0)
		->where('te.slot_id >', 0)
		->whereNotIn('te.class_id', $hsClassIds)
		->get()->getResultArray();
	$db->table('timetable_entries')->where('schedule_id', $scheduleId)->whereIn('class_id', $hsClassIds)->delete();
}
echo 'Kept other-level lessons: ' . count($keep) . "\n";

$clockMap = [];
foreach (array_keys($byTrack) as $track) {
	foreach ($schema->generationSlots($schoolId, $track) as $slot) {
		$key = TimetableSchemaModel::slotClock((string) $slot['start_time'])
			. '|' . TimetableSchemaModel::slotClock((string) $slot['end_time']);
		$clockMap[$track][$key] = (int) $slot['id'];
	}
}
$settings = $db->table('timetable_settings')->where('school_id', $schoolId)->get(1)->getRowArray();
$generator = new TimetableGeneratorService();
$generator->setCombineSlotMaps($classTrack, $clockMap);
$allEntries = [];
$reset = true;
foreach ($hsTracks as $track) {
	$done = [];
	foreach ($allEntries as $entry) {
		if ((int) ($entry['slot_id'] ?? 0) > 0) {
			$done[(int) ($entry['class_id'] ?? 0) . ':' . (int) ($entry['course_id'] ?? 0)] = true;
		}
	}
	$trackAssignments = array_values(array_filter($byTrack[$track] ?? [], static function (array $assignment) use ($done): bool {
		$key = (int) ($assignment['class_id'] ?? 0) . ':' . (int) ($assignment['course_id'] ?? 0);
		return !isset($done[$key]);
	}));
	if ($trackAssignments === []) {
		continue;
	}
	$days = TimetableSchemaModel::weekDaysForTrack($settings, $track);
	$blocked = [];
	foreach ($schema->specialTimesMap($schoolId, $track) as $key => $unused) {
		$blocked[$key] = true;
	}
	$context = [];
	foreach ($hsTracks as $contextTrack) {
		foreach ($byTrack[$contextTrack] ?? [] as $assignment) {
			$context[] = $assignment;
		}
	}
	echo "Generating {$track} (" . count($trackAssignments) . " courses)\n";
	$result = $generator->generate(
		$trackAssignments,
		$schema->generationSlots($schoolId, $track),
		$days,
		$blocked,
		$reset,
		$context,
		$reset ? $keep : []
	);
	$reset = false;
	$allEntries = array_merge($allEntries, $result['entries']);
	echo "  placed rows " . count($result['entries']) . "\n";
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
		'entry_type' => $entry['entry_type'] ?? 'lesson',
		'custom_label' => $entry['custom_label'] ?? null,
		'is_locked' => !empty($entry['is_locked']) ? 1 : 0,
	]);
}
$db->table('timetable_schedules')->where('id', $scheduleId)->update([
	'title' => 'Version 2',
	'status' => 'published',
	'notes' => 'Version 2 evening activities. Version 1 is stored in timetable_*_v1.',
	'generated_at' => date('Y-m-d H:i:s'),
]);

$activity = $db->query("SELECT co.title, ts.start_time, ts.end_time, COUNT(*) n
 FROM timetable_entries te
 JOIN courses co ON co.id = te.course_id
 JOIN timetable_slots ts ON ts.id = te.slot_id
 WHERE te.schedule_id = ? AND te.day_of_week >= 0
 AND co.id IN (505, 528)
 GROUP BY co.title, ts.start_time, ts.end_time", [$scheduleId])->getResultArray();
echo "=== ACTIVITY PLACEMENT ===\n";
foreach ($activity as $line) {
	echo $line['title'] . ' ' . substr((string) $line['start_time'], 0, 5) . '-' . substr((string) $line['end_time'], 0, 5) . ' x' . $line['n'] . "\n";
}
$sod = (int) $db->table('course_records')->where('class', 220)->whereIn('course', [476, 478])->countAllResults();
echo "L3 SOD removed courses left: {$sod}\n";
$chapel = (int) $db->table('timetable_special_times')->where('school_id', $schoolId)->where('label', 'CHAPEL')->countAllResults();
$dinner = (int) $db->table('timetable_special_times')->where('school_id', $schoolId)->where('label', 'DINNER')->countAllResults();
$preps = (int) $db->table('timetable_special_times')->where('school_id', $schoolId)->where('label', 'PREPS')->countAllResults();
echo "specials chapel={$chapel} dinner={$dinner} preps={$preps}\n";
echo "VERSION2_OK\n";

function alignEveningSlots($db, int $schoolId, string $track): void
{
	$rows = $db->table('timetable_slots')
		->where('school_id', $schoolId)
		->where('track_key', $track)
		->orderBy('sort_order', 'ASC')
		->get()->getResultArray();
	$byLabel = [];
	foreach ($rows as $row) {
		$byLabel[(string) $row['label']] = $row;
	}
	$targets = [
		'15' => ['17:30:00', '18:00:00'],
		'16' => ['18:00:00', '19:00:00'],
	];
	foreach ($targets as $label => [$start, $end]) {
		if (!isset($byLabel[$label])) {
			continue;
		}
		$db->table('timetable_slots')->where('id', (int) $byLabel[$label]['id'])->update([
			'start_time' => $start,
			'end_time' => $end,
			'is_break' => 0,
		]);
	}
	if (!isset($byLabel['17'])) {
		$after = $byLabel['16']['sort_order'] ?? (count($rows) - 1);
		$db->table('timetable_slots')->insert([
			'school_id' => $schoolId,
			'track_key' => $track,
			'level_id' => 0,
			'sort_order' => ((int) $after) + 1,
			'label' => '17',
			'start_time' => '19:00:00',
			'end_time' => '21:00:00',
			'is_break' => 0,
			'break_label' => null,
		]);
	} else {
		$db->table('timetable_slots')->where('id', (int) $byLabel['17']['id'])->update([
			'start_time' => '19:00:00',
			'end_time' => '21:00:00',
			'is_break' => 0,
		]);
	}
	echo "Aligned evening slots {$track}\n";
}

function rewriteEveningSpecials($db, int $schoolId, string $track): void
{
	$slots = $db->table('timetable_slots')
		->where('school_id', $schoolId)
		->where('track_key', $track)
		->get()->getResultArray();
	$eveningIds = [];
	$byClock = [];
	foreach ($slots as $slot) {
		$start = substr((string) $slot['start_time'], 0, 8);
		$end = substr((string) $slot['end_time'], 0, 8);
		if ($start >= '16:40:00') {
			$eveningIds[] = (int) $slot['id'];
		}
		$byClock[$start . '|' . $end] = (int) $slot['id'];
	}
	if ($eveningIds !== []) {
		$db->table('timetable_special_times')
			->where('school_id', $schoolId)
			->where('track_key', $track)
			->whereIn('slot_id', $eveningIds)
			->delete();
	}
	$bands = [
		['17:30:00|18:00:00', 'CHAPEL', 'yellow'],
		['18:00:00|19:00:00', 'DINNER', 'gray'],
		['19:00:00|21:00:00', 'PREPS', 'green'],
	];
	$order = 0;
	foreach ([0, 1, 2, 3, 4, 6] as $day) {
		foreach ($bands as [$clock, $label, $color]) {
			$slotId = $byClock[$clock] ?? 0;
			if ($slotId <= 0) {
				echo "Missing {$track} {$clock}\n";
				continue;
			}
			$db->table('timetable_special_times')->insert([
				'school_id' => $schoolId,
				'track_key' => $track,
				'level_id' => 0,
				'day_of_week' => $day,
				'slot_id' => $slotId,
				'label' => $label,
				'color' => $color,
				'sort_order' => $order++,
			]);
		}
	}
	echo "Evening specials {$track}\n";
}

function loadAssignments($db, int $schoolId, int $year, int $term): array
{
	return $db->table('course_records cr')
		->select('cr.id AS course_record_id, cr.course AS course_id, cr.lecturer, cr.class AS class_id,
			c.title AS course_title, c.credit, cl.title AS class_title, l.title AS level_name,
			d.code AS dept_code, d.title AS dept_title,
			CONCAT(s.fname, " ", s.lname) AS teacher_name')
		->join('courses c', 'c.id = cr.course')
		->join('classes cl', 'cl.id = cr.class')
		->join('levels l', 'l.id = cl.level', 'left')
		->join('departments d', 'd.id = cl.department', 'left')
		->join('staffs s', 's.id = cr.lecturer', 'left')
		->where('cl.school_id', $schoolId)
		->where('cr.year', $year)
		->where("find_in_set({$term}, cr.term) > 0", null, false)
		->get()->getResultArray();
}
