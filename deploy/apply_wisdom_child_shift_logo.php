<?php
/**
 * Copy WISDOM SCHOOL RWANDA "Academic Staffs" shift to every Wisdom child school,
 * assign all child-school staff to it, and set the shared Wisdom logo.
 *
 * Run: docker exec xander_school_app php /var/www/html/deploy/apply_wisdom_child_shift_logo.php
 */
declare(strict_types=1);

define('FCPATH', __DIR__ . '/../public/');
chdir(FCPATH);
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/Config/Paths.php';

$paths = new Config\Paths();
require rtrim($paths->systemDirectory, '/ ') . '/bootstrap.php';

$db = \Config\Database::connect();
$svc = new \App\Services\SchoolHierarchyService();
$seeded = $svc->seedWisdomMasterGroup();
$masterId = (int) ($seeded['master_id'] ?? 0);
if ($masterId < 1) {
	fwrite(STDERR, "WISDOM SCHOOL RWANDA master school not found\n");
	exit(1);
}

$logoName = 'wisdom_logo.png';
$logoDest = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR
	. 'images' . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $logoName;
if (!is_file($logoDest) || filesize($logoDest) < 1000) {
	fwrite(STDERR, "Missing or empty logo at {$logoDest}\n");
	exit(1);
}

$source = $db->table('shifts')
	->where('school_id', $masterId)
	->like('title', 'Academic Staff', 'both')
	->orderBy('id', 'ASC')
	->get(1)
	->getRowArray();
if (!$source) {
	fwrite(STDERR, "Academic Staffs shift not found on master school {$masterId}\n");
	exit(1);
}

$options = (string) ($source['options'] ?? '');
$title = trim((string) ($source['title'] ?? 'Academic Staffs'));
if ($title === '') {
	$title = 'Academic Staffs';
}
$now = date('Y-m-d H:i:s');
$createdBy = (int) ($source['created_by'] ?? 1);

echo "Master school: {$masterId}\n";
echo "Source shift: #{$source['id']} {$title} {$options}\n";
echo "Logo: {$logoName} (" . filesize($logoDest) . " bytes)\n\n";

$children = $svc->childSchools($masterId);
if (!$children) {
	fwrite(STDERR, "No Wisdom child schools found\n");
	exit(1);
}

foreach ($children as $school) {
	$sid = (int) ($school['id'] ?? 0);
	$name = trim((string) ($school['name'] ?? ''));
	if ($sid < 1 || $sid === $masterId) {
		echo "SKIP {$name}\n";
		continue;
	}

	$existing = $db->table('shifts')
		->where('school_id', $sid)
		->where('title', $title)
		->get(1)
		->getRowArray();
	if ($existing) {
		$db->table('shifts')->where('id', (int) $existing['id'])->update([
			'options' => $options,
			'status' => 1,
			'updated_at' => $now,
		]);
		$shiftId = (int) $existing['id'];
		$shiftAction = 'updated';
	} else {
		$db->table('shifts')->insert([
			'school_id' => $sid,
			'title' => $title,
			'options' => $options,
			'status' => 1,
			'created_by' => $createdBy,
			'created_at' => $now,
			'updated_at' => $now,
		]);
		$shiftId = (int) $db->insertID();
		$shiftAction = 'created';
	}

	$db->table('staffs')->where('school_id', $sid)->update(['shift_id' => $shiftId]);
	$assigned = $db->table('staffs')->where('school_id', $sid)->where('shift_id', $shiftId)->countAllResults(false);
	$total = $db->table('staffs')->where('school_id', $sid)->countAllResults(false);

	$db->table('schools')->where('id', $sid)->update(['logo' => $logoName]);
	$logoNow = (string) ($db->table('schools')->select('logo')->where('id', $sid)->get(1)->getRowArray()['logo'] ?? '');

	echo sprintf(
		"%s (#%d): shift %s #%d, staff %d/%d assigned, logo=%s\n",
		$name,
		$sid,
		$shiftAction,
		$shiftId,
		$assigned,
		$total,
		$logoNow
	);
}

echo "\nDONE\n";
exit(0);
