<?php
/**
 * Sync budget permissions for headmaster/headmistress (fix inactive Prepare budget).
 * Run: docker exec xander_school_app php /var/www/html/deploy/sync_budget_permissions.php
 */
define('FCPATH', __DIR__ . '/../public/');
chdir(FCPATH);
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/Config/Paths.php';

$paths = new Config\Paths();
require rtrim($paths->systemDirectory, '/ ') . '/bootstrap.php';

$schema = new \App\Models\BudgetSchemaModel();
$schema->seedFinanceRoles();

$db = \Config\Database::connect();
$branches = $db->table('branches b')->select('b.id, b.organization_id, b.school_id')->where('b.status', 1)->get()->getResultArray();
$year = (int) date('Y');
$title = $year . '-' . ($year + 1) . ' Annual Budget';
foreach ($branches as $br) {
	$bid = (int) $br['id'];
	if ($db->table('budget_periods')->where('branch_id', $bid)->where('status', 'open')->countAllResults()) {
		continue;
	}
	$exists = $db->table('budget_periods')->where('branch_id', $bid)->where('title', $title)->get(1)->getRowArray();
	if ($exists) {
		$db->table('budget_periods')->where('id', (int) $exists['id'])->update(['status' => 'open', 'updated_at' => date('Y-m-d H:i:s')]);
	} else {
		$db->table('budget_periods')->insert([
			'organization_id' => (int) $br['organization_id'],
			'branch_id' => $bid,
			'title' => $title,
			'period_type' => 'annual',
			'start_date' => $year . '-01-01',
			'end_date' => $year . '-12-31',
			'status' => 'open',
			'created_at' => date('Y-m-d H:i:s'),
			'updated_at' => date('Y-m-d H:i:s'),
		]);
	}
}
$posts = [1 => 'Head master', 18 => 'Headmistress'];
echo "=== Budget permissions synced ===\n";
foreach ($posts as $pid => $label) {
	$perms = $db->table('post_budget_permissions')->where('post_id', $pid)->get()->getResultArray();
	echo "$label ($pid): " . count($perms) . " permissions\n";
	foreach ($perms as $p) {
		echo "  - {$p['perm_key']}\n";
	}
}

$periods = $db->table('budget_periods bp')
	->select('bp.title, bp.status, b.name as branch, s.name as school')
	->join('branches b', 'b.id = bp.branch_id')
	->join('schools s', 's.id = b.school_id', 'left')
	->where('bp.status', 'open')
	->get()->getResultArray();
echo "\nOpen budget periods: " . count($periods) . "\n";
foreach ($periods as $p) {
	echo "  - {$p['school']} / {$p['branch']}: {$p['title']}\n";
}
exit(0);
