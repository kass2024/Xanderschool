<?php
/**
 * Ensure Deputy Director post (#30) and assign it to NDUWAYESU Aime.
 * Does not create staff or change password / RFID / photo.
 * Run: docker exec xander_school_app php /var/www/html/deploy/seed_deputy_director_aime.php
 */
declare(strict_types=1);

define('FCPATH', __DIR__ . '/../public/');
chdir(FCPATH);
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/Config/Paths.php';

$paths = new Config\Paths();
require rtrim($paths->systemDirectory, '/ ') . '/bootstrap.php';

$db = \Config\Database::connect();

$sqlFile = __DIR__ . '/add_deputy_director_post.sql';
if (is_file($sqlFile)) {
	try {
		$db->query(file_get_contents($sqlFile));
	} catch (\Throwable $e) {
		echo 'SQL note: ' . $e->getMessage() . PHP_EOL;
	}
}

$post = $db->table('posts')->where('title', 'Deputy Director')->get(1)->getRowArray();
if (!$post) {
	$post = $db->table('posts')->where('id', 30)->get(1)->getRowArray();
	if ($post && strcasecmp((string) ($post['title'] ?? ''), 'Deputy Director') !== 0) {
		$db->table('posts')->insert(['title' => 'Deputy Director', 'status' => 1]);
		$post = $db->table('posts')->where('title', 'Deputy Director')->get(1)->getRowArray();
	} elseif (!$post) {
		$db->table('posts')->insert(['id' => 30, 'title' => 'Deputy Director', 'status' => 1]);
		$post = $db->table('posts')->where('title', 'Deputy Director')->get(1)->getRowArray();
	} else {
		$db->table('posts')->where('id', 30)->update(['status' => 1]);
		$post = $db->table('posts')->where('id', 30)->get(1)->getRowArray();
	}
} elseif ((int) ($post['status'] ?? 0) !== 1) {
	$db->table('posts')->where('id', (int) $post['id'])->update(['status' => 1]);
	$post['status'] = 1;
}

if (!$post) {
	fwrite(STDERR, "Could not create Deputy Director post.\n");
	exit(1);
}
$postId = (int) $post['id'];
printf("Post [%d] %s (status %d)\n", $postId, $post['title'], (int) ($post['status'] ?? 0));

$email = 'agaime2020@gmail.com';
$phones = ['0782406217', '250782406217', '+250782406217', '782406217'];

$existing = $db->table('staffs')
	->groupStart()
		->where('email', $email)
		->orWhereIn('phone', $phones)
	->groupEnd()
	->get()->getResultArray();

if (!$existing) {
	$existing = $db->table('staffs')
		->like('fname', 'NDUWAYESU')
		->groupStart()
			->like('lname', 'Aime')
			->orLike('lname', 'Aimé')
			->orLike('lname', 'aime')
		->groupEnd()
		->get()->getResultArray();
}

if (!$existing) {
	fwrite(STDERR, "Staff NDUWAYESU Aime / {$email} not found.\n");
	exit(1);
}

$now = date('Y-m-d H:i:s');
foreach ($existing as $row) {
	$staffId = (int) $row['id'];
	$prevPost = (int) ($row['post'] ?? 0);
	$db->table('staffs')->where('id', $staffId)->update([
		'post' => $postId,
		'updated_at' => $now,
	]);
	$school = $db->table('schools')->select('name')->where('id', (int) ($row['school_id'] ?? 0))->get(1)->getRowArray();
	printf(
		"UPD   staff id %d %s %s <%s> phone=%s school_id=%d (%s) post %d -> %d Deputy Director\n",
		$staffId,
		(string) ($row['fname'] ?? ''),
		(string) ($row['lname'] ?? ''),
		(string) ($row['email'] ?? ''),
		(string) ($row['phone'] ?? ''),
		(int) ($row['school_id'] ?? 0),
		(string) ($school['name'] ?? ''),
		$prevPost,
		$postId
	);
}

echo 'DONE' . PHP_EOL;
exit(0);
