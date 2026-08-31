<?php
/**
 * Merge Kayonza's duplicate 2026-2027 academic-year record.
 *
 * The first import matched "2026 - 2027" and created a second year because
 * the live database already had the same year with different spacing.
 */
declare(strict_types=1);

define('FCPATH', __DIR__ . '/../public/');
chdir(FCPATH);
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/Config/Paths.php';

$paths = new Config\Paths();
require rtrim($paths->systemDirectory, '/ ') . '/bootstrap.php';

$db = \Config\Database::connect();
const SCHOOL_ID = 35;
const YEAR_TITLE = '2026-2027';

function normalized_year(string $title): string
{
    return preg_replace('/[^0-9]/', '', $title) ?? '';
}

$school = $db->table('schools')->where('id', SCHOOL_ID)->get(1)->getRowArray();
if (!$school || strpos(strtoupper((string) ($school['name'] ?? '')), 'KAYONZA') === false) {
    fwrite(STDERR, "Kayonza school 35 was not found\n");
    exit(1);
}

$wrongToKayonza = $db->query(
    'SELECT COUNT(*) AS n
     FROM class_records cr
     JOIN classes c ON c.id=cr.class
     JOIN students s ON s.id=cr.student
     WHERE c.school_id=? AND s.school_id<>?',
    [SCHOOL_ID, SCHOOL_ID]
)->getRowArray();
$kayonzaToWrong = $db->query(
    'SELECT COUNT(*) AS n
     FROM class_records cr
     JOIN classes c ON c.id=cr.class
     JOIN students s ON s.id=cr.student
     WHERE c.school_id<>? AND s.school_id=?',
    [SCHOOL_ID, SCHOOL_ID]
)->getRowArray();
if ((int) ($wrongToKayonza['n'] ?? 0) > 0 || (int) ($kayonzaToWrong['n'] ?? 0) > 0) {
    fwrite(STDERR, "Cross-school class records detected; refusing automatic cleanup\n");
    exit(1);
}

$activeTerm = $db->table('active_term')
    ->where('id', (int) ($school['active_term'] ?? 0))
    ->where('school_id', SCHOOL_ID)
    ->get(1)
    ->getRowArray();
$years = $db->table('academic_year')
    ->where('school_id', SCHOOL_ID)
    ->orderBy('id', 'ASC')
    ->get()
    ->getResultArray();
$target = null;
foreach ($years as $year) {
    if ((int) ($activeTerm['academic_year'] ?? 0) === (int) $year['id']
        && normalized_year((string) $year['title']) === normalized_year(YEAR_TITLE)) {
        $target = $year;
        break;
    }
}
if (!$target) {
    foreach ($years as $year) {
        if (normalized_year((string) $year['title']) === normalized_year(YEAR_TITLE)) {
            $target = $year;
            break;
        }
    }
}
if (!$target) {
    fwrite(STDERR, "No existing Kayonza 2026-2027 academic year found\n");
    exit(1);
}

$targetId = (int) $target['id'];
$moved = 0;
$deleted = 0;
$db->transStart();
foreach ($years as $year) {
    $duplicateId = (int) $year['id'];
    if ($duplicateId === $targetId
        || normalized_year((string) $year['title']) !== normalized_year(YEAR_TITLE)) {
        continue;
    }
    $result = $db->query(
        'UPDATE class_records cr
         JOIN classes c ON c.id=cr.class
         SET cr.year=?
         WHERE c.school_id=? AND cr.year=?',
        [(string) $targetId, SCHOOL_ID, (string) $duplicateId]
    );
    $moved += $db->affectedRows();
    $remaining = $db->table('class_records cr')
        ->join('classes c', 'c.id=cr.class')
        ->where('c.school_id', SCHOOL_ID)
        ->where('cr.year', (string) $duplicateId)
        ->countAllResults();
    if ($remaining === 0) {
        $db->table('academic_year')->where('id', $duplicateId)->delete();
        $deleted++;
    }
}
$db->transComplete();
if (!$db->transStatus()) {
    fwrite(STDERR, "Academic-year repair transaction failed\n");
    exit(1);
}

echo "Kayonza school id: " . SCHOOL_ID . PHP_EOL;
echo "Active academic year retained: {$targetId} ({$target['title']})" . PHP_EOL;
echo "Class records moved: {$moved}" . PHP_EOL;
echo "Duplicate academic years removed: {$deleted}" . PHP_EOL;
echo "Cross-school records: 0" . PHP_EOL;
exit(0);
