<?php
/**
 * Replace day-scholar Feeding / Transport amounts for WISDOM SCHOOL RWANDA.
 *
 * Primary & Nursery: Feeding 60,000 · Transport 60,000 per term
 * High school: Feeding 100,000 · Transport 80,000 per term
 */
declare(strict_types=1);

define('FCPATH', __DIR__ . '/../public/');
chdir(FCPATH);
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/Config/Paths.php';

$paths = new Config\Paths();
require rtrim($paths->systemDirectory, '/ ') . '/bootstrap.php';

use App\Models\ExtraFeesModel;

$schoolId = 27;
$db = \Config\Database::connect();
$year = $db->table('academic_year')
	->where('school_id', $schoolId)
	->orderBy('id', 'DESC')
	->get(1)->getRowArray();
if (!$year) {
	echo "ERROR: no academic year for school {$schoolId}\n";
	exit(1);
}
$yearId = (int) $year['id'];
$extraFees = new ExtraFeesModel();
$saved = $extraFees->ensureDayScholarFeedingTransport($schoolId, $yearId, 1);
echo 'School ' . $schoolId . ' year ' . ($year['title'] ?? $yearId) . " ({$yearId})\n";
echo "Feeding/Transport class rows upserted: {$saved}\n";
echo "DONE\n";
exit(0);
