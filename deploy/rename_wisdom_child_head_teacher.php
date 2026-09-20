<?php
/**
 * On every Wisdom child school, rename Head master staff to Head Teacher.
 * Creates Head Teacher if missing, with the same full-access rights as Head master.
 *
 * Run: docker exec xander_school_app php /var/www/html/deploy/rename_wisdom_child_head_teacher.php
 */
declare(strict_types=1);

define('FCPATH', __DIR__ . '/../public/');
chdir(FCPATH);
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/Config/Paths.php';

$paths = new Config\Paths();
require rtrim($paths->systemDirectory, '/ ') . '/bootstrap.php';

$db = \Config\Database::connect();
$postsMdl = new \App\Models\PostsModel();
$postsMdl->ensureLeadershipPosts();

$headMaster = $db->query(
	"SELECT id, title FROM posts WHERE LOWER(title) IN ('head master','headmaster') ORDER BY id ASC LIMIT 1"
)->getRowArray();
$headMasterId = (int) ($headMaster['id'] ?? 1);

$created = $postsMdl->createByTitle('Head Teacher');
$headTeacherId = (int) ($created['id'] ?? 0);
if ($headTeacherId < 1) {
	$headTeacherId = (int) \App\Models\PostsModel::HEAD_TEACHER_ID;
}
if ($headTeacherId < 1) {
	fwrite(STDERR, "Could not resolve Head Teacher post\n");
	exit(1);
}

$db->table('posts')->where('id', $headTeacherId)->update(['status' => 1]);

$svc = new \App\Services\SchoolHierarchyService();
$seeded = $svc->seedWisdomMasterGroup();
$masterId = (int) ($seeded['master_id'] ?? 0);
if ($masterId < 1) {
	fwrite(STDERR, "WISDOM SCHOOL RWANDA master school not found\n");
	exit(1);
}

echo "Head master post: #{$headMasterId}\n";
echo "Head Teacher post: #{$headTeacherId}" . (!empty($created['created']) ? " (created)" : " (existing)") . "\n";
echo "Full access posts include Head Teacher: "
	. (\Config\MenuClearance::isFullAccessPost($headTeacherId) ? "yes" : "NO") . "\n";
echo "Head-master-equivalent: "
	. (\Config\MenuClearance::isHeadMasterEquivalent($headTeacherId) ? "yes" : "NO") . "\n\n";

$now = date('Y-m-d H:i:s');
$changed = 0;
$already = 0;

foreach ($svc->childSchools($masterId) as $school) {
	$sid = (int) ($school['id'] ?? 0);
	$name = trim((string) ($school['name'] ?? ''));
	if ($sid < 1 || $sid === $masterId) {
		continue;
	}

	$heads = $db->table('staffs')
		->select('id,fname,lname,email,post')
		->where('school_id', $sid)
		->where('post', $headMasterId)
		->orderBy('id', 'ASC')
		->get()->getResultArray();

	$updatedHere = 0;
	foreach ($heads as $staff) {
		$db->table('staffs')->where('id', (int) $staff['id'])->update([
			'post' => $headTeacherId,
			'updated_at' => $now,
		]);
		$updatedHere++;
		$changed++;
		echo sprintf(
			"%s (#%d): %s %s (%s) Head master -> Head Teacher\n",
			$name,
			$sid,
			$staff['fname'] ?? '',
			$staff['lname'] ?? '',
			$staff['email'] ?? ''
		);
	}

	$htCount = $db->table('staffs')->where('school_id', $sid)->where('post', $headTeacherId)->countAllResults(false);
	$hmLeft = $db->table('staffs')->where('school_id', $sid)->where('post', $headMasterId)->countAllResults(false);
	if ($updatedHere === 0) {
		$already++;
		echo sprintf("%s (#%d): no Head master staff (Head Teacher now %d)\n", $name, $sid, $htCount);
	} elseif ($hmLeft > 0) {
		echo sprintf("  leftover Head master: %d\n", $hmLeft);
	}
}

echo "\nChanged: {$changed} staff. Schools already on Head Teacher only: {$already}\n";
echo "DONE\n";
exit(0);
