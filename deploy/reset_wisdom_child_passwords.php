<?php
/**
 * Create/reset default Head master passwords for Wisdom CHILD schools only.
 * Never creates or changes login for WISDOM SCHOOL RWANDA (master).
 *
 * Run: php deploy/reset_wisdom_child_passwords.php
 * Or:  docker exec xander_school_app php /var/www/html/deploy/reset_wisdom_child_passwords.php
 */
define('FCPATH', __DIR__ . '/../public/');
chdir(FCPATH);
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/Config/Paths.php';

$paths = new Config\Paths();
require rtrim($paths->systemDirectory, '/ ') . '/bootstrap.php';

$svc = new \App\Services\SchoolHierarchyService();
$result = $svc->resetWisdomChildDefaultPasswords();

echo "=== Wisdom child school default passwords ===\n";
echo 'Master school ID: ' . ($result['master_id'] ?? 0) . " (login NOT created/reset)\n";
echo 'Login link: ' . ($result['login_url'] ?? '') . "\n";
echo 'Default password: ' . ($result['password'] ?? '') . "\n";
echo 'Created Head master accounts: ' . count($result['created'] ?? []) . "\n";
echo 'Updated Head master passwords: ' . count($result['updated'] ?? []) . "\n";

if (!empty($result['created'])) {
	echo "Created:\n";
	foreach ($result['created'] as $row) {
		echo '  - ' . ($row['school'] ?? '') . '  ' . ($row['email'] ?? '') . "\n";
	}
}
if (!empty($result['updated'])) {
	echo "Updated:\n";
	foreach ($result['updated'] as $row) {
		echo '  - ' . ($row['school'] ?? '') . '  ' . ($row['email'] ?? '') . "\n";
	}
}
if (!empty($result['skipped'])) {
	echo "Skipped:\n";
	foreach ($result['skipped'] as $row) {
		echo '  - ' . ($row['school'] ?? '') . ' (' . ($row['reason'] ?? '') . ")\n";
	}
}

$file = (string) ($result['file'] ?? '');
if ($file === '' || !is_file($file)) {
	echo "\nERROR: credentials file was not generated.\n";
	exit(1);
}

$deployCopy = __DIR__ . '/wisdom_child_schools_credentials.txt';
copy($file, $deployCopy);
echo "\nCredentials file:\n  $file\n  $deployCopy\n";
echo "----- FILE CONTENTS -----\n";
echo file_get_contents($file);
exit(0);
