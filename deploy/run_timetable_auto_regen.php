<?php
/**
 * Auto-regen cron entrypoint — DISABLED.
 * Use Timetable dashboard → Generate smart timetable instead.
 *
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

echo 'DISABLED auto-regen' . PHP_EOL;
echo (string) ($result['message'] ?? 'Use Timetable dashboard Generate smart timetable.') . PHP_EOL;
echo 'queued=' . (int) ($result['queued'] ?? 0)
	. ' skipped=' . (int) ($result['skipped'] ?? 0)
	. ' disabled=' . (!empty($result['disabled']) ? '1' : '0') . PHP_EOL;
