<?php
/**
 * Seed Budget Manager, Procurement Manager, Deputy Director of Finance posts.
 * Run: php deploy/seed_finance_posts.php
 * Or:  docker exec xander_school_app php /var/www/html/deploy/seed_finance_posts.php
 */
define('FCPATH', __DIR__ . '/../public/');
chdir(FCPATH);
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/Config/Paths.php';

$paths = new Config\Paths();
require rtrim($paths->systemDirectory, '/ ') . '/bootstrap.php';

$db = \Config\Database::connect();
$sqlFile = __DIR__ . '/add_finance_posts.sql';
if (is_file($sqlFile)) {
	try {
		$db->query(file_get_contents($sqlFile));
	} catch (\Throwable $e) {
		// fall through to model seed
	}
}

$schema = new \App\Models\BudgetSchemaModel();
$schema->seedFinanceRoles();

$targetIds = [19, 20, 21];
echo "=== Finance posts ===\n";
$rows = $db->table('posts')->whereIn('id', $targetIds)->orderBy('id')->get()->getResultArray();
foreach ($rows as $r) {
	printf("  [%d] %s (status %d)\n", $r['id'], $r['title'], $r['status']);
}

echo "\nBudget permissions seeded for posts 19-23.\n";
echo "Assign staff to these posts under Staff > Change post.\n";
exit(0);
