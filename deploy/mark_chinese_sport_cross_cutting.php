<?php
/**
 * Mark Chinese and Sport as cross-cutting (RTB + REB + Special) for a school.
 *
 * Run: docker exec xander_school_app php /var/www/html/deploy/mark_chinese_sport_cross_cutting.php [school_id]
 */
declare(strict_types=1);

define('FCPATH', __DIR__ . '/../public/');
chdir(FCPATH);
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/Config/Paths.php';

$paths = new Config\Paths();
require rtrim($paths->systemDirectory, '/ ') . '/bootstrap.php';
require_once __DIR__ . '/../app/Helpers/qonics_helper.php';

$schoolId = (int) ($argv[1] ?? 27);
$db = \Config\Database::connect();

$rows = $db->table('courses')
	->select('id, title, code, program_type')
	->where('school_id', $schoolId)
	->groupStart()
		->like('title', 'chinese', 'both')
		->orWhere('code', 'CHN')
		->orLike('title', 'sport', 'both')
		->orWhere('code', 'PES')
		->orLike('title', 'physical education', 'both')
	->groupEnd()
	->orderBy('id', 'ASC')
	->get()->getResultArray();

if ($rows === []) {
	fwrite(STDERR, "No Chinese/Sport courses found for school {$schoolId}\n");
	exit(1);
}

$updated = 0;
foreach ($rows as $row) {
	$id = (int) ($row['id'] ?? 0);
	$title = (string) ($row['title'] ?? '');
	$code = (string) ($row['code'] ?? '');
	$prev = (string) ($row['program_type'] ?? '');
	if (!is_known_cross_cutting_course($title, $code)) {
		echo "Skip #{$id} {$title} ({$code}) — not a known cross-cutting course\n";
		continue;
	}
	$db->table('courses')->where('id', $id)->update(['program_type' => 'cross']);
	$updated++;
	echo "Updated #{$id} {$title} ({$code}): {$prev} -> cross\n";
}

echo "Done. Updated {$updated} course(s) for school {$schoolId}.\n";
