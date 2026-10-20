<?php

/**
 * CLI: confirm a personal fee amount replaces the class amount, then
 * empty budget rows and the cash requests drawn from them.
 *
 * Usage: php deploy/_check_student_fee_and_clear_budgets.php --clear-budgets
 */

define('FCPATH', dirname(__DIR__) . '/public' . DIRECTORY_SEPARATOR);
chdir(FCPATH);
require dirname(__DIR__) . '/app/Config/Paths.php';
$paths = new Config\Paths();
$app = require rtrim($paths->systemDirectory, '/ ') . '/bootstrap.php';
$app->initialize();

$day = [
	'amount' => 650000,
	'amount_boarding' => 650000,
	'amount_day' => 250000,
	'student_amount' => 120000,
];
$got = App\Models\SchoolFeesModel::expectedForStudent($day, 1, -400000);
if (abs($got - 120000) > 0.01) {
	fwrite(STDERR, "day override got {$got}\n");
	exit(1);
}
$plain = App\Models\SchoolFeesModel::expectedForStudent([
	'amount' => 650000,
	'amount_boarding' => 650000,
	'amount_day' => 250000,
], 1, 0);
if (abs($plain - 250000) > 0.01) {
	fwrite(STDERR, "class day got {$plain}\n");
	exit(1);
}
echo "expected ok\n";

$fee = new App\Models\SchoolFeesModel();
$fee->ensureSchema();
echo "schema ok\n";

if (!in_array('--clear-budgets', $argv ?? [], true)) {
	echo "skip clear\n";
	exit(0);
}

$db = Config\Database::connect();
$tables = [
	'cash_request_payment_documents',
	'cash_request_payments',
	'receipt_confirmations',
	'cash_request_documents',
	'cash_request_actions',
	'budget_commitments',
	'cash_request_lines',
	'cash_requests',
	'cash_request_sequences',
	'ai_budget_suggestions',
	'budget_documents',
	'budget_approval_actions',
	'budget_adjustments',
	'budget_transfers',
	'budget_lines',
	'budgets',
	'budget_notifications',
];
foreach ($tables as $table) {
	if (!$db->tableExists($table)) {
		echo "skip {$table}\n";
		continue;
	}
	$n = (int) $db->table($table)->countAllResults();
	echo "before {$table} {$n}\n";
}
$db->query('SET FOREIGN_KEY_CHECKS=0');
foreach ($tables as $table) {
	if ($db->tableExists($table)) {
		$db->query('DELETE FROM `' . $table . '`');
	}
}
if ($db->tableExists('financial_audit_logs')) {
	$db->query("DELETE FROM financial_audit_logs WHERE entity_type LIKE '%budget%' OR entity_type LIKE '%cash_request%'");
}
$db->query('SET FOREIGN_KEY_CHECKS=1');
foreach (['budgets', 'budget_lines', 'cash_requests', 'cash_request_lines', 'budget_commitments'] as $table) {
	if (!$db->tableExists($table)) {
		continue;
	}
	$n = (int) $db->table($table)->countAllResults();
	echo "after {$table} {$n}\n";
	if ($n !== 0) {
		fwrite(STDERR, "{$table} not empty\n");
		exit(1);
	}
}
echo "budgets cleared\n";
