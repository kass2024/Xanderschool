<?php
/**
 * Install Wisdom Schools Professional Budget Template for wisdom-schools org.
 * Run: docker exec xander_school_app php /var/www/html/deploy/seed_wisdom_budget_template.php
 */
define('FCPATH', __DIR__ . '/../public/');
chdir(FCPATH);
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/Config/Paths.php';

$paths = new Config\Paths();
require rtrim($paths->systemDirectory, '/ ') . '/bootstrap.php';

$db = \Config\Database::connect();
$org = $db->table('organizations')->where('slug', 'wisdom-schools')->get(1)->getRowArray();
if (!$org) {
	echo "ERROR: wisdom-schools organization not found. Open Budget module once to seed branches.\n";
	exit(1);
}

$import = new \App\Services\Budget\BudgetTemplateImportService();
$path = $import->officialTemplatePath();
if (!$path) {
	echo "ERROR: Template file missing at writable/templates/budget/" . \App\Services\Budget\BudgetTemplateImportService::OFFICIAL_TEMPLATE . "\n";
	exit(1);
}

$res = $import->installOfficialTemplate((int) $org['id'], 0, true);
if (empty($res['success'])) {
	echo "ERROR: " . ($res['error'] ?? 'Unknown') . "\n";
	exit(1);
}

echo "=== Wisdom budget template installed ===\n";
echo "Template ID: " . $res['template_id'] . "\n";
echo "Lines: " . ($res['line_count'] ?? 0) . "\n";
echo "Status: active\n";
echo "Path: $path\n";
exit(0);
