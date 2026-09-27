<?php
/**
 * Place leftover lessons into empty class+teacher cells without regenerating.
 *
 *   docker exec xander_school_app php /var/www/html/deploy/fill_version2_gaps.php
 */
declare(strict_types=1);

define('FCPATH', __DIR__ . '/../public/');
chdir(FCPATH);
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/Config/Paths.php';

$paths = new Config\Paths();
require rtrim($paths->systemDirectory, '/ ') . '/bootstrap.php';

use App\Models\TimetableSchemaModel;
use App\Services\Timetable\TimetableStagingService;

@set_time_limit(0);
$db = \Config\Database::connect();
$schoolId = 27;
$row = $db->table('schools s')
	->select('at.term, at.academic_year')
	->join('active_term at', 'at.id = s.active_term', 'left')
	->where('s.id', $schoolId)->get(1)->getRowArray();
$year = (int) ($row['academic_year'] ?? 16);
$term = max(1, (int) ($row['term'] ?? 1));
$schedule = $db->table('timetable_schedules')
	->where('school_id', $schoolId)
	->where('academic_year', $year)
	->where('term', $term)
	->orderBy('id', 'DESC')
	->get(1)->getRowArray();
if (!$schedule) {
	fwrite(STDERR, "No schedule\n");
	exit(1);
}
$schema = new TimetableSchemaModel();
$before = (int) $db->table('timetable_entries')
	->where('schedule_id', (int) $schedule['id'])
	->where('day_of_week', -1)
	->where('slot_id', 0)
	->countAllResults();
$placed = (new TimetableStagingService())->fillVersion2Gaps((int) $schedule['id'], $schoolId, $schema);
$after = (int) $db->table('timetable_entries')
	->where('schedule_id', (int) $schedule['id'])
	->where('day_of_week', -1)
	->where('slot_id', 0)
	->countAllResults();
$db->table('timetable_schedules')->where('id', (int) $schedule['id'])->update([
	'notes' => 'Version 2 rules are locked for the next generation. Leftover periods fill an empty class and teacher cell, including Mon–Thu 15:40–16:20. Combined courses follow Version 1. Periods that still exceed free slots stay highlighted.',
]);
echo "parked_before {$before}\nplaced_attempt {$placed}\nparked_after {$after}\nFILL_OK\n";
