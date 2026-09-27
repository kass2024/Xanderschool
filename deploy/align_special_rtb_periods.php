<?php
/**
 * Copy A Level bell periods onto Special and RTB / TVET for one school.
 *
 *   docker exec xander_school_app php /var/www/html/deploy/align_special_rtb_periods.php [school_id]
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
$written = $schema->alignSpecialAndRtbPeriodsToALevel($schoolId);

$db = \Config\Database::connect();
echo "Aligned Special and RTB to A Level for school {$schoolId} ({$written} slot rows).\n";
$signatures = [];
foreach ([TimetableTrack::A_LEVEL, TimetableTrack::SPECIAL, TimetableTrack::RTB] as $track) {
	echo "=== {$track} ===\n";
	$rows = $db->table('timetable_slots')
		->where('school_id', $schoolId)
		->where('track_key', $track)
		->orderBy('sort_order', 'ASC')
		->get()
		->getResultArray();
	$parts = [];
	foreach ($rows as $row) {
		$mark = !empty($row['is_break']) ? ' [break]' : '';
		$line = $row['label'] . ' ' . substr((string) $row['start_time'], 0, 5) . '-' . substr((string) $row['end_time'], 0, 5) . $mark;
		echo '  ' . $line . "\n";
		$parts[] = $line;
	}
	$signatures[$track] = implode('|', $parts);
}
$same = ($signatures[TimetableTrack::SPECIAL] ?? '') === ($signatures[TimetableTrack::A_LEVEL] ?? '')
	&& ($signatures[TimetableTrack::RTB] ?? '') === ($signatures[TimetableTrack::A_LEVEL] ?? '');
echo $same ? "MATCH\n" : "MISMATCH\n";
echo "Done.\n";
