<?php
/**
 * Align Primary and Nursery bells with the other classes.
 * Keeps special activities. Clips regular lessons to 15:40. Sunday stays off.
 *
 *   docker exec xander_school_app php /var/www/html/deploy/align_primary_nursery_periods.php [school_id]
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

$schoolId = (int) ($argv[1] ?? 27);
$schema = new TimetableSchemaModel();
$schema->ensureSchema();
$n = $schema->alignPrimaryNurseryWithOtherClasses($schoolId);

$db = \Config\Database::connect();
echo "Aligned school {$schoolId} ({$n} slot rows).\n";
foreach ([TimetableTrack::PRIMARY, TimetableTrack::NURSERY, TimetableTrack::O_LEVEL] as $track) {
	echo "=== {$track} ===\n";
	$rows = $db->table('timetable_slots')
		->where('school_id', $schoolId)
		->where('track_key', $track)
		->orderBy('sort_order', 'ASC')
		->get()
		->getResultArray();
	foreach ($rows as $row) {
		$mark = !empty($row['is_break']) ? ' [break]' : '';
		echo '  ' . $row['label'] . ' ' . substr((string) $row['start_time'], 0, 5) . '-' . substr((string) $row['end_time'], 0, 5) . $mark . "\n";
	}
	$specials = (int) $db->table('timetable_special_times')
		->where('school_id', $schoolId)
		->where('track_key', $track)
		->countAllResults();
	echo "  specials={$specials}\n";
}
echo "Done.\n";
