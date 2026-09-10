<?php
/**
 * Probe Wisdom P2A / P2B for section dispatch (split A into A+B).
 */
declare(strict_types=1);

define('FCPATH', __DIR__ . '/../public/');
chdir(FCPATH);
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/Config/Paths.php';
$paths = new Config\Paths();
require rtrim($paths->systemDirectory, '/ ') . '/bootstrap.php';

$db = \Config\Database::connect();
const SCHOOL_ID = 27;

$at = $db->query(
	'SELECT academic_year, term FROM active_term WHERE school_id=? ORDER BY id DESC LIMIT 1',
	[SCHOOL_ID]
)->getRowArray();
$yearId = (string) ($at['academic_year'] ?? 0);
$year = $db->query('SELECT id, title FROM academic_year WHERE id=? LIMIT 1', [$yearId])->getRowArray();
echo 'Active year: ' . json_encode($year) . ' term=' . ($at['term'] ?? '?') . PHP_EOL;

$rows = $db->query(
	"SELECT c.id, c.title AS stream, l.title AS level_title, IFNULL(d.title,'') AS dept,
	        (SELECT COUNT(*) FROM class_records cr WHERE cr.class=c.id AND cr.year=?) AS cnt
	 FROM classes c
	 JOIN levels l ON l.id=c.level
	 LEFT JOIN departments d ON d.id=c.department
	 WHERE c.school_id=?
	   AND UPPER(TRIM(l.title))='P2'
	   AND LOWER(IFNULL(c.title,'')) NOT LIKE '%holiday%'
	 ORDER BY c.title",
	[$yearId, SCHOOL_ID]
)->getResultArray();

echo "P2 classes:\n";
foreach ($rows as $r) {
	echo sprintf(
		"  id=%d stream=%s dept=%s students=%d\n",
		$r['id'],
		$r['stream'] !== '' ? $r['stream'] : '-',
		$r['dept'] !== '' ? $r['dept'] : '-',
		$r['cnt']
	);
}

$p2a = null;
foreach ($rows as $r) {
	if (strtoupper(trim((string) $r['stream'])) === 'A') {
		$p2a = $r;
		break;
	}
}
if (!$p2a) {
	fwrite(STDERR, "P2A not found\n");
	exit(1);
}

$stats = $db->query(
	"SELECT UPPER(TRIM(IFNULL(s.sex,''))) AS sex,
	        IFNULL(s.studying_mode, -1) AS mode,
	        COUNT(*) AS c
	 FROM class_records cr
	 JOIN students s ON s.id=cr.student
	 WHERE cr.class=? AND cr.year=? AND s.school_id=?
	 GROUP BY sex, mode
	 ORDER BY sex, mode",
	[(int) $p2a['id'], $yearId, SCHOOL_ID]
)->getResultArray();
echo "P2A breakdown (sex / studying_mode 0=boarding 1=day):\n";
foreach ($stats as $s) {
	echo sprintf("  sex=%s mode=%s count=%d\n", $s['sex'] !== '' ? $s['sex'] : '?', $s['mode'], $s['c']);
}

$sample = $db->query(
	"SELECT s.id, s.regno, CONCAT(s.fname,' ',s.lname) AS names,
	        UPPER(TRIM(IFNULL(s.sex,''))) AS sex, IFNULL(s.studying_mode,-1) AS mode
	 FROM class_records cr
	 JOIN students s ON s.id=cr.student
	 WHERE cr.class=? AND cr.year=? AND s.school_id=?
	 ORDER BY s.lname, s.fname
	 LIMIT 5",
	[(int) $p2a['id'], $yearId, SCHOOL_ID]
)->getResultArray();
echo "Sample P2A students:\n";
foreach ($sample as $s) {
	echo sprintf("  id=%d %s sex=%s mode=%s\n", $s['id'], $s['names'], $s['sex'], $s['mode']);
}
