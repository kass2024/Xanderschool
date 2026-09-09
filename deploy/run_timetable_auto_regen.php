<?php
/**
 * Force timetable auto-regen for one school (or all).
 * Run: docker exec xander_school_app php /var/www/html/deploy/run_timetable_auto_regen.php [school_id]
 */
declare(strict_types=1);

define('FCPATH', __DIR__ . '/../public/');
chdir(FCPATH);
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/Config/Paths.php';

$paths = new Config\Paths();
require rtrim($paths->systemDirectory, '/ ') . '/bootstrap.php';

use App\Controllers\TimetableManagement;
use Config\Services;

$onlySchool = isset($argv[1]) && $argv[1] !== '' ? (int) $argv[1] : null;

$ctl = new TimetableManagement();
$ctl->initController(Services::request(), Services::response(), Services::logger());
$result = $ctl->autoRegenerateStaleSchools($onlySchool);

echo 'queued=' . (int) ($result['queued'] ?? 0) . ' skipped=' . (int) ($result['skipped'] ?? 0) . PHP_EOL;
foreach (($result['schools'] ?? []) as $row) {
	$job = $row['result']['job_id'] ?? ($row['result']['error'] ?? json_encode($row['result'] ?? []));
	echo 'school ' . (int) ($row['school_id'] ?? 0)
		. ' y' . (int) ($row['year'] ?? 0)
		. ' t' . (int) ($row['term'] ?? 0)
		. ' -> ' . $job . PHP_EOL;
}

// Docker/FPM often cannot detach workers; process queued jobs in this same cron run.
$processed = $ctl->processQueuedTimetableJobs(3);
echo 'processed=' . (int) ($processed['processed'] ?? 0)
	. ' failed=' . (int) ($processed['failed'] ?? 0)
	. ' busy=' . (int) ($processed['busy'] ?? 0) . PHP_EOL;
foreach (($processed['jobs'] ?? []) as $jobRow) {
	echo 'job ' . ($jobRow['job_id'] ?? '?')
		. ' status=' . ($jobRow['status'] ?? '?')
		. ' ok=' . (!empty($jobRow['ok']) ? '1' : '0')
		. PHP_EOL;
}
