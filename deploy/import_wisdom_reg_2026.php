<?php
/**
 * Import Wisdom School Musanze 2026-2027 registration from Excel parse JSON.
 * Reuses existing regular classes (not Holiday). Creates a class only when missing.
 *
 * Run: docker exec xander_school_app php /var/www/html/deploy/import_wisdom_reg_2026.php
 */
declare(strict_types=1);

define('FCPATH', __DIR__ . '/../public/');
chdir(FCPATH);
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/Config/Paths.php';

$paths = new Config\Paths();
require rtrim($paths->systemDirectory, '/ ') . '/bootstrap.php';

if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle): bool
    {
        return $needle === '' || strpos((string) $haystack, (string) $needle) !== false;
    }
}
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle): bool
    {
        $needle = (string) $needle;
        return $needle === '' || strncmp((string) $haystack, $needle, strlen($needle)) === 0;
    }
}

$db = \Config\Database::connect();

const SCHOOL_ID = 27;
const ACADEMIC_YEAR_ID = 16;
const CREATED_BY = 1;
const YEAR_CODE = '26';
const DEFAULT_VILLAGE_ID = 1202;
const MENTOR_PRIMARY = 55; // DUSABIMANA EMMANUEL
const MENTOR_SECONDARY = 39; // NDUWAYESU ELIE
const DEPT_STREAM = 200;

$jsonPath = __DIR__ . '/_reg_2026_parsed.json';
if (!is_file($jsonPath)) {
    fwrite(STDERR, "Missing {$jsonPath}\n");
    exit(1);
}

$raw = json_decode((string) file_get_contents($jsonPath), true);
if (!is_array($raw)) {
    fwrite(STDERR, "Invalid JSON\n");
    exit(1);
}

function norm_class(string $label): string
{
    $s = strtoupper(trim(preg_replace('/\s+/', ' ', $label) ?? $label));
    $s = str_replace(['BABY CLASS', 'BABYCLASS'], 'BABY CLASS', $s);
    if ($s === 'BABY' || $s === 'BABY CLASS') {
        return 'BABY CLASS';
    }
    return $s;
}

function name_tokens(string $s): array
{
    preg_match_all('/[A-Za-z]+/', strtoupper($s), $m);
    $stop = ['OF', 'THE', 'AND', 'DE', 'LA'];
    $out = [];
    foreach ($m[0] as $t) {
        if (strlen($t) < 2 || in_array($t, $stop, true)) {
            continue;
        }
        $out[$t] = true;
    }
    return array_keys($out);
}

function names_match(string $a, string $b): bool
{
    $ta = name_tokens($a);
    $tb = name_tokens($b);
    if ($ta === [] || $tb === []) {
        return false;
    }
    $inter = array_values(array_intersect($ta, $tb));
    if ($inter === []) {
        return false;
    }
    if (count($inter) >= 3) {
        return true;
    }
    $fullA = implode(' ', $ta);
    $fullB = implode(' ', $tb);
    if ($fullA === $fullB) {
        return true;
    }
    $smaller = count($ta) <= count($tb) ? $ta : $tb;
    $larger = count($ta) <= count($tb) ? $tb : $ta;
    $hit = 0;
    foreach ($smaller as $t) {
        if (in_array($t, $larger, true)) {
            $hit++;
        }
    }
    return count($smaller) >= 2 && $hit === count($smaller);
}

function clean_phone(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '' || preg_match('/x{3,}|n\/a|none|ntawe|ntayo|xxx|placeholder/i', $raw)) {
        return '';
    }
    $digits = preg_replace('/\D+/', '', $raw) ?? '';
    if ($digits === '') {
        return '';
    }
    if (strlen($digits) === 9 && str_starts_with($digits, '7')) {
        $digits = '0' . $digits;
    }
    if (strlen($digits) === 12 && str_starts_with($digits, '2507')) {
        $digits = '0' . substr($digits, 3);
    }
    return substr($digits, 0, 20);
}

function clean_email(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '' || !str_contains($raw, '@')) {
        return '';
    }
    if (preg_match('/x{3,}|placeholder|example\.com/i', $raw)) {
        return '';
    }
    return substr($raw, 0, 100);
}

function clean_text(string $raw, int $max = 100): string
{
    $raw = trim(preg_replace('/\s+/', ' ', $raw) ?? $raw);
    if (preg_match('/^(ntawe|ntayo|n\/a|none|-+)$/i', $raw)) {
        return '';
    }
    return substr($raw, 0, $max);
}

function clean_nationality(string $raw): string
{
    $s = strtolower($raw);
    if ($s === '' || str_contains($s, 'rwanda') || str_contains($s, 'munyarwanda')) {
        return 'rwanda';
    }
    return 'other';
}

function clean_sex(string $raw): string
{
    $s = strtoupper(trim($raw));
    if ($s === 'F' || str_contains($s, 'GORE') || str_contains($s, 'FEMALE')) {
        return 'F';
    }
    return 'M';
}

function full_name(array $s): string
{
    if (!empty($s['full_name'])) {
        return trim((string) $s['full_name']);
    }
    return trim(($s['fname'] ?? '') . ' ' . ($s['lname'] ?? ''));
}

function student_email(string $fname, string $lname, string $regno): string
{
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '.', trim($fname . '.' . $lname)) ?? 'student');
    $slug = trim($slug, '.');
    if ($slug === '') {
        $slug = 'student';
    }
    return substr($slug . '.' . $regno . '@wisdom.rw', 0, 100);
}

/**
 * @param array<string,mixed> $row
 * @return array<string,mixed>
 */
function normalize_student(array $row): array
{
    $fname = clean_text((string) ($row['fname'] ?? ''), 100);
    $lname = clean_text((string) ($row['lname'] ?? ''), 100);
    $mode = (string) ($row['studying_mode'] ?? '');
    if ($mode !== '0' && $mode !== '1') {
        $mode = '1';
    }
    $dob = (string) ($row['dob'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob) || $dob < '1990-01-01' || $dob > '2026-12-31') {
        $dob = '2016-01-01';
    }
    $parentEmail = clean_email((string) ($row['ft_email'] ?? '')) ?: clean_email((string) ($row['mt_email'] ?? ''));
    return [
        'class_label' => norm_class((string) ($row['class_label'] ?? '')),
        'regno' => preg_replace('/\D+/', '', (string) ($row['regno'] ?? '')) ?: '',
        'fname' => $fname !== '' ? $fname : 'UNKNOWN',
        'lname' => $lname,
        'sex' => clean_sex((string) ($row['gender'] ?? 'M')),
        'dob' => $dob,
        'nationality' => clean_nationality((string) ($row['nationality'] ?? 'rwanda')),
        'father' => clean_text((string) ($row['father'] ?? '')),
        'ft_phone' => clean_phone((string) ($row['ft_phone'] ?? '')),
        'mother' => clean_text((string) ($row['mother'] ?? '')),
        'mt_phone' => clean_phone((string) ($row['mt_phone'] ?? '')),
        'guardian' => clean_text((string) ($row['guardian'] ?? '')),
        'gd_phone' => clean_phone((string) ($row['gd_phone'] ?? '')),
        'district' => clean_text((string) ($row['district'] ?? ''), 80),
        'sector' => clean_text((string) ($row['sector'] ?? ''), 80),
        'cell' => clean_text((string) ($row['cell'] ?? ''), 80),
        'village' => clean_text((string) ($row['village'] ?? ''), 80),
        'studying_mode' => (int) $mode,
        'parent_email' => $parentEmail,
        'source' => (string) ($row['source'] ?? ''),
        'full_name' => full_name($row),
    ];
}

/**
 * @param list<array<string,mixed>> $structured
 * @param list<array<string,mixed>> $allSys
 * @return list<array<string,mixed>>
 */
function merge_students(array $structured, array $allSys): array
{
    $out = [];
    foreach ($structured as $row) {
        $out[] = normalize_student($row);
    }
    foreach ($allSys as $row) {
        $cand = normalize_student($row);
        $matched = false;
        foreach ($out as $i => $existing) {
            $sameClass = $existing['class_label'] === $cand['class_label']
                || ($existing['class_label'] === 'S2' && $cand['class_label'] === 'S2');
            if ($sameClass && names_match($existing['full_name'], $cand['full_name'])) {
                if ($existing['regno'] === '' && $cand['regno'] !== '') {
                    $out[$i]['regno'] = $cand['regno'];
                }
                if ($cand['studying_mode'] === 0) {
                    $out[$i]['studying_mode'] = 0;
                }
                foreach (['father', 'ft_phone', 'mother', 'mt_phone', 'guardian', 'gd_phone', 'parent_email'] as $k) {
                    if ($out[$i][$k] === '' && $cand[$k] !== '') {
                        $out[$i][$k] = $cand[$k];
                    }
                }
                $out[$i]['source'] .= '+ALL IN SYSTEM';
                $matched = true;
                break;
            }
        }
        if (!$matched) {
            $out[] = $cand;
        }
    }
    return $out;
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
        $current = (int) ($row['next_number'] ?? 1);
        if ($nextNumber > $current) {
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

function resolve_village(\CodeIgniter\Database\BaseConnection $db, array $student): int
{
    $village = strtolower((string) $student['village']);
    $district = strtolower((string) $student['district']);
    if ($village === '' || $village === 'option 1') {
        return DEFAULT_VILLAGE_ID;
    }
    try {
        $row = $db->query(
            "SELECT v.id
             FROM soma_village v
             LEFT JOIN soma_cell c ON c.id = v.cell
             LEFT JOIN soma_sector s ON s.id = c.sector
             LEFT JOIN soma_district d ON d.id = s.district
             WHERE LOWER(v.title) = ?
             AND (? = '' OR LOWER(d.title) = ?)
             LIMIT 1",
            [$village, $district, $district]
        )->getRowArray();
        if ($row) {
            return (int) $row['id'];
        }
        $row = $db->query(
            'SELECT id FROM soma_village WHERE LOWER(title) = ? LIMIT 1',
            [$village]
        )->getRowArray();
        if ($row) {
            return (int) $row['id'];
        }
    } catch (\Throwable $e) {
        // keep default
    }
    return DEFAULT_VILLAGE_ID;
}

/**
 * Find existing regular (non-Holiday) class or create one.
 * @return array{id:int,created:bool,label:string}
 */
function ensure_class(\CodeIgniter\Database\BaseConnection $db, string $classLabel, string $now): array
{
    $levelTitle = $classLabel === 'BABY CLASS' ? 'Baby class' : $classLabel;
    $isAlevel = in_array($classLabel, ['S4', 'S5', 'S6'], true);
    $mentor = $isAlevel || preg_match('/^S[1-6]$/', $classLabel) ? MENTOR_SECONDARY : MENTOR_PRIMARY;

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
                return ['id' => (int) $row['id'], 'created' => false, 'label' => $row['level_name'] . ' ' . $row['dept_name']];
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
        return ['id' => (int) $row['id'], 'created' => false, 'label' => $row['level_name'] . ' ' . $row['title']];
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

function find_existing_student(\CodeIgniter\Database\BaseConnection $db, array $student): ?array
{
    if ($student['regno'] !== '') {
        $row = $db->table('students')
            ->where('school_id', SCHOOL_ID)
            ->where('regno', $student['regno'])
            ->get(1)
            ->getRowArray();
        if ($row) {
            return $row;
        }
    }
    $candidates = $db->query(
        "SELECT s.*
         FROM students s
         LEFT JOIN class_records cr ON cr.student = s.id AND cr.year = ? AND cr.status = 1
         LEFT JOIN classes c ON c.id = cr.class
         LEFT JOIN levels l ON l.id = c.level
         WHERE s.school_id = ? AND s.status = 1
           AND (
             cr.id IS NULL
             OR IFNULL(c.title, '') <> 'Holiday'
           )",
        [(string) ACADEMIC_YEAR_ID, SCHOOL_ID]
    )->getResultArray();
    $needle = $student['full_name'];
    foreach ($candidates as $row) {
        $hay = trim($row['fname'] . ' ' . $row['lname']);
        if (names_match($needle, $hay) || strcasecmp($hay, $needle) === 0) {
            return $row;
        }
    }
    return null;
}

function ensure_class_record(\CodeIgniter\Database\BaseConnection $db, int $studentId, int $classId): void
{
    $existing = $db->table('class_records')
        ->where('student', $studentId)
        ->where('year', (string) ACADEMIC_YEAR_ID)
        ->where('class', $classId)
        ->get(1)
        ->getRowArray();
    if ($existing) {
        if ((int) ($existing['status'] ?? 0) !== 1) {
            $db->table('class_records')->where('id', (int) $existing['id'])->update(['status' => 1]);
        }
        return;
    }
    $db->table('class_records')->insert([
        'student' => $studentId,
        'year' => (string) ACADEMIC_YEAR_ID,
        'class' => $classId,
        'status' => 1,
    ]);
}

function ensure_visitors(int $studentId, array $student): int
{
    $visitorMdl = new \App\Models\StudentVisitorModel();
    $visitorMdl->ensureSchema();
    $added = 0;
    $pairs = [
        ['names' => $student['father'], 'phone' => $student['ft_phone'], 'relationship' => 'Father'],
        ['names' => $student['mother'], 'phone' => $student['mt_phone'], 'relationship' => 'Mother'],
    ];
    if ($student['guardian'] !== '') {
        $pairs[] = ['names' => $student['guardian'], 'phone' => $student['gd_phone'], 'relationship' => 'Guardian'];
    }
    foreach ($pairs as $visitor) {
        if ($visitor['names'] === '') {
            continue;
        }
        $exists = $visitorMdl->where('school_id', SCHOOL_ID)
            ->where('student_id', $studentId)
            ->where('names', $visitor['names'])
            ->where('relationship', $visitor['relationship'])
            ->first();
        if ($exists) {
            continue;
        }
        $visitorMdl->insert([
            'school_id' => SCHOOL_ID,
            'student_id' => $studentId,
            'names' => $visitor['names'],
            'phone' => $visitor['phone'],
            'relationship' => $visitor['relationship'],
            'status' => 1,
            'created_by' => CREATED_BY,
            'updated_by' => CREATED_BY,
        ]);
        $added++;
    }
    return $added;
}

$now = date('Y-m-d H:i:s');
$students = merge_students($raw['structured'] ?? [], $raw['all_in_system'] ?? []);

echo "Merged students: " . count($students) . "\n";

$counterRow = $db->table('reg_number')
    ->where('school_id', SCHOOL_ID)
    ->where('academic_year', YEAR_CODE)
    ->get(1)
    ->getRowArray();
$nextSeq = (int) ($counterRow['next_number'] ?? 1);
$generatedSeqStart = $nextSeq;

$classCache = [];
$created = 0;
$updated = 0;
$classesCreated = 0;
$visitorsAdded = 0;
$byClass = [];

foreach ($students as $student) {
    $label = $student['class_label'];
    if ($label === '') {
        echo "SKIP no class: {$student['full_name']}\n";
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

    $existing = find_existing_student($db, $student);
    $regno = $student['regno'];
    if ($regno === '') {
        if ($existing) {
            $regno = (string) $existing['regno'];
        } else {
            $regno = make_regno($db, $nextSeq);
        }
    }

    $email = $student['parent_email'] !== '' ? $student['parent_email'] : student_email($student['fname'], $student['lname'], $regno);
    $payload = [
        'school_id' => SCHOOL_ID,
        'fname' => $student['fname'],
        'lname' => $student['lname'],
        'phone' => $student['ft_phone'] !== '' ? $student['ft_phone'] : $student['mt_phone'],
        'email' => $email,
        'regno' => $regno,
        'sex' => $student['sex'],
        'dob' => $student['dob'],
        'photo' => $existing['photo'] ?? '',
        'village_id' => resolve_village($db, $student),
        'studying_mode' => $student['studying_mode'],
        'religion' => $existing['religion'] ?? '',
        'nationality' => $student['nationality'],
        'card' => $existing['card'] ?? '',
        'transport_money' => $existing['transport_money'] ?? 0,
        'wallet_balance' => $existing['wallet_balance'] ?? 0,
        'father' => $student['father'],
        'ft_phone' => $student['ft_phone'],
        'mother' => $student['mother'],
        'mt_phone' => $student['mt_phone'],
        'guardian' => $student['guardian'],
        'gd_phone' => $student['gd_phone'],
        'updated_at' => $now,
        'updated_by' => CREATED_BY,
        'status' => 1,
    ];

    if ($existing) {
        $studentId = (int) $existing['id'];
        $db->table('students')->where('id', $studentId)->update($payload);
        ensure_class_record($db, $studentId, $classId);
        $visitorsAdded += ensure_visitors($studentId, $student);
        $updated++;
        echo "UPD {$regno}  {$student['fname']} {$student['lname']}  -> {$className} ({$classId})\n";
    } else {
        $payload['created_at'] = $now;
        $payload['created_by'] = CREATED_BY;
        $payload['updateVersion'] = 1;
        $db->table('students')->insert($payload);
        $studentId = (int) $db->insertID();
        ensure_class_record($db, $studentId, $classId);
        $visitorsAdded += ensure_visitors($studentId, $student);
        $created++;
        echo "NEW {$regno}  {$student['fname']} {$student['lname']}  -> {$className} ({$classId})\n";
    }
    $byClass[$className] = ($byClass[$className] ?? 0) + 1;
}

if ($nextSeq > $generatedSeqStart) {
    bump_reg_counter($db, $nextSeq);
}

echo "\n=== Summary ===\n";
echo "Students created: {$created}\n";
echo "Students updated: {$updated}\n";
echo "Classes created: {$classesCreated}\n";
echo "Visitors added: {$visitorsAdded}\n";
echo "By class:\n";
ksort($byClass);
foreach ($byClass as $name => $n) {
    echo "  {$name}: {$n}\n";
}

echo "\nExisting regular class enrollments (year 16, non-Holiday):\n";
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
foreach ($counts as $row) {
    echo "  {$row['level_name']} {$row['stream']} (id {$row['id']}): {$row['n']}\n";
}

exit(0);
