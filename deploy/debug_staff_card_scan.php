<?php
/**
 * Debug Wisdom staff RFID lookup for attendance scanner.
 * docker exec xander_school_app php /var/www/html/deploy/debug_staff_card_scan.php
 */
declare(strict_types=1);

define('FCPATH', __DIR__ . '/../public/');
chdir(FCPATH);
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/Config/Paths.php';
$paths = new Config\Paths();
require rtrim($paths->systemDirectory, '/ ') . '/bootstrap.php';

helper('card_uid');

use Config\Database;
use App\Libraries\CardRegistry;

const SCHOOL_ID = 27;

$db = Database::connect();
echo "=== staff card scan debug ===\n";
echo "pad_helper=" . (function_exists('card_uid_pad_hex') ? 'yes' : 'no') . "\n";

$rows = $db->query(
	"SELECT id, fname, lname, email, status, school_id, card, IFNULL(face_enrolled,0) AS face_enrolled
	 FROM staffs
	 WHERE school_id = ?
	   AND (email LIKE 'ukjpi202%' OR (fname LIKE '%Ukund%' AND lname LIKE '%Method%'))
	 LIMIT 10",
	[SCHOOL_ID]
)->getResultArray();
echo "staff_hits=" . count($rows) . "\n";
foreach ($rows as $r) {
	echo "STAFF id={$r['id']} status={$r['status']} school={$r['school_id']} card=[" . $r['card'] . "] face={$r['face_enrolled']} name={$r['fname']} {$r['lname']}\n";
	$stored = (string) $r['card'];
	echo "  stored_len=" . strlen($stored) . " hex=[" . strtoupper(preg_replace('/[^A-F0-9]/', '', $stored)) . "]\n";
	echo "  stored_variants=" . implode(',', card_uid_lookup_variants($stored)) . "\n";
}

$probes = ['074AF8DA', '74AF8DA', 'DAF84A07', '122353882', '07:4A:F8:DA'];
foreach ($probes as $p) {
	$v = card_uid_lookup_variants($p);
	$owner = CardRegistry::lookup(SCHOOL_ID, $p);
	echo "PROBE [$p] variants=" . implode(',', $v) . " owner=" . json_encode($owner) . "\n";
}

$dup = $db->query(
	"SELECT 'student' AS kind, id, CONCAT(fname,' ',lname) AS name, card FROM students WHERE school_id=? AND UPPER(TRIM(card)) IN ('074AF8DA','DAF84A07','74AF8DA')
	 UNION ALL
	 SELECT 'staff', id, CONCAT(fname,' ',lname), card FROM staffs WHERE school_id=? AND UPPER(TRIM(card)) IN ('074AF8DA','DAF84A07','74AF8DA')
	 UNION ALL
	 SELECT 'visitor', id, names, card FROM student_visitors WHERE school_id=? AND UPPER(TRIM(card)) IN ('074AF8DA','DAF84A07','74AF8DA')",
	[SCHOOL_ID, SCHOOL_ID, SCHOOL_ID]
)->getResultArray();
echo "EXACT_OWNERS=" . json_encode($dup) . "\n";

$all = $db->query(
	"SELECT id, CONCAT(fname,' ',lname) AS name, card FROM staffs WHERE school_id=? AND TRIM(IFNULL(card,'')) <> '' ORDER BY fname",
	[SCHOOL_ID]
)->getResultArray();
echo "STAFF_WITH_CARDS=" . count($all) . "\n";
foreach ($all as $r) {
	echo "  {$r['id']} {$r['card']} {$r['name']}\n";
}
echo "DONE\n";
