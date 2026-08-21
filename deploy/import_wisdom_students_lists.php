<?php
/**
 * Place Wisdom students from STUDENTS LISTS.xlsx into classes named by the sheet
 * (N2, N3, P1–P6, S2, S3, Stream S5, Stream S6). Reuses existing students/classes.
 *
 * Run: docker exec xander_school_app php /var/www/html/deploy/import_wisdom_students_lists.php
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
const ACADEMIC_YEAR_ID = 16;
const CREATED_BY = 1;
const YEAR_CODE = '26';
const DEFAULT_VILLAGE_ID = 1202;
const MENTOR_PRIMARY = 55;
const MENTOR_SECONDARY = 39;
const DEPT_STREAM = 200;

$jsonPath = __DIR__ . '/_students_lists_parsed.json';
if (!is_file($jsonPath)) {
    fwrite(STDERR, "Missing {$jsonPath}\n");
    exit(1);
}
$raw = json_decode((string) file_get_contents($jsonPath), true);
if (!is_array($raw) || empty($raw['students'])) {
    fwrite(STDERR, "Invalid JSON\n");
    exit(1);
}

function name_tokens(string $s): array
{
    preg_match_all('/[A-Za-z]+/', strtoupper($s), $m);
    $out = [];
    foreach ($m[0] as $t) {
        if (strlen($t) >= 2) {
            $out[$t] = true;
        }
    }
    return array_keys($out);
}

function compact_name(string $s): string
{
    return preg_replace('/[^A-Z]/', '', strtoupper($s)) ?? '';
}

function names_match(string $a, string $b): bool
{
    $ca = compact_name($a);
    $cb = compact_name($b);
    if ($ca !== '' && $ca === $cb) {
        return true;
    }
    $ta = name_tokens($a);
    $tb = name_tokens($b);
    if ($ta === [] || $tb === []) {
        return false;
    }
    if ($ta[0] !== $tb[0]) {
        return false;
    }
    $inter = array_values(array_intersect($ta, $tb));
    if (count($inter) >= 3) {
        return true;
    }
    $smaller = count($ta) <= count($tb) ? $ta : $tb;
    $larger = count($ta) <= count($tb) ? $tb : $ta;
    if (count($smaller) < 2) {
        return false;
    }
    foreach ($smaller as $t) {
        if (!in_array($t, $larger, true)) {
            return false;
        }
    }
    return true;
}

function map_class_label(string $sheet): string
{
    $s = strtoupper(trim(preg_replace('/\s+/', ' ', $sheet) ?? $sheet));
    if ($s === 'STREAM S5' || $s === 'S5 STREAM') {
        return 'S5';
    }
    if ($s === 'STREAM S6' || $s === 'S6 STREAM') {
        return 'S6';
    }
    return $s;
}

function default_dob(string $label): string
{
    if (in_array($label, ['N1', 'N2', 'N3', 'BABY CLASS'], true)) {
        return '2019-01-15';
    }
    if (preg_match('/^P[1-6]$/', $label)) {
        $n = (int) substr($label, 1);
        return sprintf('%d-01-15', 2019 - $n);
    }
    if (in_array($label, ['S1', 'S2', 'S3'], true)) {
        return '2012-01-15';
    }
    return '2008-01-15';
}

function make_regno(\CodeIgniter\Database\BaseConnection $db, int &$nextSeq): string
{
    do {
        $regno = YEAR_CODE . sprintf('%03d', SCHOOL_ID) . sprintf('%04d', $nextSeq);
        $nextSeq++;
        $exists = $db->table('students')->where('school_id', SCHOOL_ID)->where('regno', $regno)->countAllResults();
    } while ($exists > 0);
    return $regno;
}

function bump_reg_counter(\CodeIgniter\Database\BaseConnection $db, int $nextNumber): void
{
    $row = $db->table('reg_number')
        ->where('school_id', SCHOOL_ID)
        ->where('academic_year', YEAR_CODE)
        ->get(1)
        ->getRowArray();
    if ($row) {
        if ($nextNumber > (int) ($row['next_number'] ?? 1)) {
            $db->table('reg_number')->where('id', (int) $row['id'])->update(['next_number' => $nextNumber]);
        }
        return;
    }
    $db->table('reg_number')->insert([
        'school_id' => SCHOOL_ID,
        'academic_year' => YEAR_CODE,
        'next_number' => $nextNumber,
    ]);
}

/** @return array{id:int,created:bool,label:string} */
function ensure_class(\CodeIgniter\Database\BaseConnection $db, string $classLabel, string $now): array
{
    $levelTitle = $classLabel;
    $isAlevel = in_array($classLabel, ['S4', 'S5', 'S6'], true);
    $mentor = ($isAlevel || preg_match('/^S[1-6]$/', $classLabel)) ? MENTOR_SECONDARY : MENTOR_PRIMARY;

    $rows = $db->table('classes c')
        ->select('c.id, c.title, c.department, d.code, d.title AS dept_name, l.title AS level_name')
        ->join('levels l', 'l.id = c.level')
        ->join('departments d', 'd.id = c.department')
        ->where('c.school_id', SCHOOL_ID)
        ->where('l.title', $levelTitle)
        ->get()
        ->getResultArray();

    $regular = [];
    foreach ($rows as $row) {
        if (strcasecmp((string) $row['title'], 'Holiday') === 0) {
            continue;
        }
        $regular[] = $row;
    }

    if ($isAlevel) {
        foreach ($regular as $row) {
            if ((int) $row['department'] === DEPT_STREAM || strcasecmp((string) $row['code'], 'STR') === 0) {
                return ['id' => (int) $row['id'], 'created' => false, 'label' => $row['level_name'] . ' Stream'];
            }
        }
        $level = $db->table('levels')->where('title', $levelTitle)->get(1)->getRowArray();
        if (!$level) {
            throw new RuntimeException("Level not found: {$levelTitle}");
        }
        $db->table('classes')->insert([
            'school_id' => SCHOOL_ID,
            'level' => (int) $level['id'],
            'department' => DEPT_STREAM,
            'title' => '',
            'mentor' => $mentor,
            'created_at' => $now,
            'created_by' => CREATED_BY,
            'updated_at' => $now,
            'updated_by' => CREATED_BY,
        ]);
        $id = (int) $db->insertID();
        echo "CLASS created: {$classLabel} Stream id={$id}\n";
        return ['id' => $id, 'created' => true, 'label' => $classLabel . ' Stream'];
    }

    foreach ($regular as $row) {
        if (trim((string) $row['title']) === '') {
            return ['id' => (int) $row['id'], 'created' => false, 'label' => $row['level_name']];
        }
    }
    foreach ($regular as $row) {
        return ['id' => (int) $row['id'], 'created' => false, 'label' => trim($row['level_name'] . ' ' . $row['title'])];
    }

    $level = $db->table('levels')->where('title', $levelTitle)->get(1)->getRowArray();
    if (!$level) {
        throw new RuntimeException("Level not found: {$levelTitle}");
    }
    $deptId = 2;
    if (in_array($classLabel, ['N1', 'N2', 'N3', 'BABY CLASS'], true)) {
        $deptId = 110;
    } elseif (preg_match('/^S[1-3]$/', $classLabel)) {
        $deptId = 1;
    }
    $db->table('classes')->insert([
        'school_id' => SCHOOL_ID,
        'level' => (int) $level['id'],
        'department' => $deptId,
        'title' => '',
        'mentor' => $mentor,
        'created_at' => $now,
        'created_by' => CREATED_BY,
        'updated_at' => $now,
        'updated_by' => CREATED_BY,
    ]);
    $id = (int) $db->insertID();
    echo "CLASS created: {$classLabel} id={$id}\n";
    return ['id' => $id, 'created' => true, 'label' => $classLabel];
}

function enroll(\CodeIgniter\Database\BaseConnection $db, int $studentId, int $classId): string
{
    $rows = $db->table('class_records cr')
        ->select('cr.id, cr.class, cr.status, c.title')
        ->join('classes c', 'c.id = cr.class')
        ->where('cr.student', $studentId)
        ->where('cr.year', (string) ACADEMIC_YEAR_ID)
        ->get()
        ->getResultArray();

    $already = false;
    foreach ($rows as $row) {
        if ((int) $row['class'] === $classId) {
            if ((int) ($row['status'] ?? 0) !== 1) {
                $db->table('class_records')->where('id', (int) $row['id'])->update(['status' => 1]);
            }
            $already = true;
            continue;
        }
        if (strcasecmp((string) $row['title'], 'Holiday') === 0) {
            continue;
        }
        if ((int) ($row['status'] ?? 0) === 1) {
            $db->table('class_records')->where('id', (int) $row['id'])->update(['status' => 0]);
        }
    }
    if ($already) {
        return 'already';
    }
    $db->table('class_records')->insert([
        'student' => $studentId,
        'year' => (string) ACADEMIC_YEAR_ID,
        'class' => $classId,
        'status' => 1,
    ]);
    return 'enrolled';
}

$now = date('Y-m-d H:i:s');
$students = $raw['students'];
echo 'List students: ' . count($students) . "\n";

$existingRows = $db->table('students')
    ->where('school_id', SCHOOL_ID)
    ->where('status', 1)
    ->get()
    ->getResultArray();
$existing = [];
foreach ($existingRows as $row) {
    $existing[] = $row;
}

$counterRow = $db->table('reg_number')
    ->where('school_id', SCHOOL_ID)
    ->where('academic_year', YEAR_CODE)
    ->get(1)
    ->getRowArray();
$nextSeq = (int) ($counterRow['next_number'] ?? 1);
$seqStart = $nextSeq;

$classCache = [];
$usedIds = [];
$created = 0;
$reused = 0;
$enrolled = 0;
$already = 0;
$classesCreated = 0;
$byClass = [];

foreach ($students as $row) {
    $full = trim((string) ($row['full_name'] ?? ''));
    if ($full === '' || strcasecmp($full, 'TOTAL') === 0) {
        continue;
    }
    $label = map_class_label((string) ($row['class_label'] ?? ''));
    if ($label === '') {
        echo "SKIP no class: {$full}\n";
        continue;
    }
    if (!isset($classCache[$label])) {
        $classCache[$label] = ensure_class($db, $label, $now);
        if ($classCache[$label]['created']) {
            $classesCreated++;
        }
    }
    $classId = $classCache[$label]['id'];
    $className = $classCache[$label]['label'];

    $match = null;
    foreach ($existing as $cand) {
        $cid = (int) $cand['id'];
        if (isset($usedIds[$cid])) {
            continue;
        }
        $hay = trim($cand['fname'] . ' ' . $cand['lname']);
        if (names_match($full, $hay)) {
            $match = $cand;
            break;
        }
    }

    if ($match) {
        $studentId = (int) $match['id'];
        $usedIds[$studentId] = true;
        $action = enroll($db, $studentId, $classId);
        $reused++;
        if ($action === 'already') {
            $already++;
        } else {
            $enrolled++;
        }
        echo "USE {$match['regno']}  {$full}  -> {$className} ({$classId}) [{$action}]\n";
    } else {
        $regno = make_regno($db, $nextSeq);
        $fname = trim((string) ($row['fname'] ?? 'UNKNOWN'));
        $lname = trim((string) ($row['lname'] ?? ''));
        $db->table('students')->insert([
            'school_id' => SCHOOL_ID,
            'fname' => $fname !== '' ? $fname : 'UNKNOWN',
            'lname' => $lname,
            'phone' => '',
            'email' => '',
            'regno' => $regno,
            'sex' => 'M',
            'dob' => default_dob($label),
            'photo' => '',
            'village_id' => DEFAULT_VILLAGE_ID,
            'studying_mode' => 1,
            'religion' => '',
            'nationality' => 'rwanda',
            'card' => '',
            'transport_money' => 0,
            'wallet_balance' => 0,
            'father' => '',
            'ft_phone' => '',
            'mother' => '',
            'mt_phone' => '',
            'guardian' => '',
            'gd_phone' => '',
            'created_at' => $now,
            'created_by' => CREATED_BY,
            'updated_at' => $now,
            'updated_by' => CREATED_BY,
            'status' => 1,
            'updateVersion' => 1,
        ]);
        $studentId = (int) $db->insertID();
        $usedIds[$studentId] = true;
        enroll($db, $studentId, $classId);
        $created++;
        $enrolled++;
        echo "NEW {$regno}  {$full}  -> {$className} ({$classId})\n";
        $existing[] = ['id' => $studentId, 'fname' => $fname, 'lname' => $lname, 'regno' => $regno];
    }
    $byClass[$className] = ($byClass[$className] ?? 0) + 1;
}

if ($nextSeq > $seqStart) {
    bump_reg_counter($db, $nextSeq);
}

echo "\n=== Summary ===\n";
echo "Created: {$created}\n";
echo "Reused existing: {$reused}\n";
echo "Newly enrolled: {$enrolled}\n";
echo "Already in class: {$already}\n";
echo "Classes created: {$classesCreated}\n";
echo "By class:\n";
ksort($byClass);
foreach ($byClass as $name => $n) {
    echo "  {$name}: {$n}\n";
}

$counts = $db->query(
    "SELECT l.title AS level_name, IFNULL(NULLIF(c.title,''),'—') AS stream, COUNT(cr.id) AS n, c.id
     FROM classes c
     JOIN levels l ON l.id = c.level
     LEFT JOIN class_records cr ON cr.class = c.id AND cr.year = '16' AND cr.status = 1
     WHERE c.school_id = 27 AND IFNULL(c.title,'') <> 'Holiday'
     GROUP BY c.id
     HAVING n > 0
     ORDER BY l.title, c.title"
)->getResultArray();
echo "\nRegular class enrollments:\n";
foreach ($counts as $row) {
    echo "  {$row['level_name']} {$row['stream']} (id {$row['id']}): {$row['n']}\n";
}

exit(0);
