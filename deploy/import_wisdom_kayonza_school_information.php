<?php
/**
 * Import Wisdom School Kayonza's school-information workbook data.
 *
 * The source workbook is parsed locally into
 * _wisdom_kayonza_school_information.json. This importer is intentionally
 * scoped to WIS-KAY and the 2026-2027 academic year so it cannot touch the
 * Musanze branch or another school's records.
 *
 * Usage:
 *   php deploy/import_wisdom_kayonza_school_information.php
 *   php deploy/import_wisdom_kayonza_school_information.php --dry-run
 */
declare(strict_types=1);

define('FCPATH', __DIR__ . '/../public/');
chdir(FCPATH);
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/Config/Paths.php';

$paths = new Config\Paths();
require rtrim($paths->systemDirectory, '/ ') . '/bootstrap.php';

$db = \Config\Database::connect();
$jsonPath = __DIR__ . '/_wisdom_kayonza_school_information.json';
$dryRun = in_array('--dry-run', $argv ?? [], true);

if (!is_file($jsonPath)) {
    fwrite(STDERR, "Missing {$jsonPath}\n");
    exit(1);
}

$data = json_decode((string) file_get_contents($jsonPath), true);
if (!is_array($data) || !is_array($data['school'] ?? null)
    || !is_array($data['classes'] ?? null) || !is_array($data['staff'] ?? null)) {
    fwrite(STDERR, "Invalid Kayonza import JSON\n");
    exit(1);
}

const TARGET_ACRONYM = 'WIS-KAY';
const TARGET_SCHOOL_ID = 35;
const ACADEMIC_YEAR_TITLE = '2026-2027';
const CREATED_BY = 1;
const DEFAULT_VILLAGE_ID = 1202;
const YEAR_CODE = '26';
const DEFAULT_PASSWORD = 'Wisdom@2026';

function compact_name(string $value): string
{
    return preg_replace('/[^A-Z0-9]/', '', strtoupper($value)) ?? '';
}

function clean_text($value, int $max = 100): string
{
    $text = trim(preg_replace('/\s+/', ' ', (string) ($value ?? '')) ?? '');
    return substr($text, 0, $max);
}

function staff_email(string $fname, string $lname, \CodeIgniter\Database\BaseConnection $db): string
{
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '.', trim($fname . '.' . $lname)) ?? 'staff');
    $slug = trim($slug, '.');
    if ($slug === '') {
        $slug = 'staff';
    }
    $base = $slug . '@wisdomschoolkayonza.rw';
    $email = $base;
    $suffix = 1;
    while ($db->table('staffs')->where('email', $email)->countAllResults() > 0) {
        $suffix++;
        $email = $slug . '.' . $suffix . '@wisdomschoolkayonza.rw';
    }
    return $email;
}

function post_id(\CodeIgniter\Database\BaseConnection $db, string $title): int
{
    $row = $db->table('posts')->where('title', $title)->get(1)->getRowArray();
    if ($row) {
        if ((int) ($row['status'] ?? 0) !== 1) {
            $db->table('posts')->where('id', (int) $row['id'])->update(['status' => 1]);
        }
        return (int) $row['id'];
    }
    $db->table('posts')->insert(['title' => $title, 'status' => 1]);
    return (int) $db->insertID();
}

function position_post(\CodeIgniter\Database\BaseConnection $db, string $position): int
{
    $position = strtoupper(clean_text($position));
    if (strpos($position, 'HEAD TEACHER') !== false) {
        return post_id($db, 'Head Teacher');
    }
    if ($position === 'DOS' || strpos($position, 'DIRECTOR OF STUDIES') !== false) {
        return post_id($db, 'Director of studies');
    }
    if (strpos($position, 'ACCOUNTANT') !== false) {
        return post_id($db, 'Accountant');
    }
    return post_id($db, 'Teacher');
}

function class_level_label(string $workbookLabel): string
{
    return strtoupper(trim($workbookLabel));
}

function class_department_label(string $classLabel): string
{
    return preg_match('/^N[1-3]$/', $classLabel) ? 'Nursery' : 'Primary';
}

function default_dob(string $classLabel): string
{
    $years = [
        'N1' => 2021,
        'N2' => 2020,
        'N3' => 2019,
        'P1' => 2018,
        'P2' => 2017,
        'P3' => 2016,
        'P4' => 2015,
        'P5' => 2014,
        'P6' => 2013,
    ];
    return sprintf('%d-01-01', $years[$classLabel] ?? 2000);
}

function regno(\CodeIgniter\Database\BaseConnection $db, int &$next): string
{
    do {
        $value = YEAR_CODE . sprintf('%03d', TARGET_SCHOOL_ID) . sprintf('%04d', $next++);
        $exists = $db->table('students')
            ->where('school_id', TARGET_SCHOOL_ID)
            ->where('regno', $value)
            ->countAllResults();
    } while ($exists > 0);
    return $value;
}

function ensure_class(
    \CodeIgniter\Database\BaseConnection $db,
    string $classLabel,
    int $mentor,
    string $now
): array {
    $levelTitle = $classLabel;
    $departmentTitle = class_department_label($classLabel);

    // The application calls the first nursery level "Baby class"; this
    // workbook starts at N1, so all workbook labels have a matching level.
    $level = $db->table('levels l')
        ->select('l.id')
        ->join('faculty f', 'f.id = l.faculty_id', 'left')
        ->where('l.title', $levelTitle)
        ->where('l.status', 1)
        ->where('f.type', 2)
        ->get(1)
        ->getRowArray();
    if (!$level) {
        throw new RuntimeException("General-education level not found: {$levelTitle}");
    }

    $department = $db->table('departments d')
        ->select('d.id')
        ->join('faculty f', 'f.id = d.faculty_id', 'left')
        ->where('d.title', $departmentTitle)
        ->where('f.type', 2)
        ->get(1)
        ->getRowArray();
    if (!$department) {
        throw new RuntimeException("General-education department not found: {$departmentTitle}");
    }

    $existing = $db->table('classes')
        ->where('school_id', TARGET_SCHOOL_ID)
        ->where('level', (int) $level['id'])
        ->where('department', (int) $department['id'])
        ->where('title', '')
        ->get(1)
        ->getRowArray();
    if ($existing) {
        return ['id' => (int) $existing['id'], 'created' => false];
    }

    $db->table('classes')->insert([
        'school_id' => TARGET_SCHOOL_ID,
        'level' => (int) $level['id'],
        'department' => (int) $department['id'],
        'title' => '',
        'mentor' => $mentor,
        'created_at' => $now,
        'created_by' => CREATED_BY,
        'updated_at' => $now,
        'updated_by' => CREATED_BY,
    ]);
    return ['id' => (int) $db->insertID(), 'created' => true];
}

function enroll(
    \CodeIgniter\Database\BaseConnection $db,
    int $studentId,
    int $classId,
    int $academicYearId
): void {
    // Keep one active regular class for the academic year, matching the
    // normal student-registration workflow.
    $db->table('class_records')
        ->where('student', $studentId)
        ->where('year', (string) $academicYearId)
        ->where('status', 1)
        ->update(['status' => 0]);

    $record = $db->table('class_records')
        ->where('student', $studentId)
        ->where('year', (string) $academicYearId)
        ->where('class', $classId)
        ->get(1)
        ->getRowArray();
    if ($record) {
        $db->table('class_records')->where('id', (int) $record['id'])->update(['status' => 1]);
        return;
    }
    $db->table('class_records')->insert([
        'student' => $studentId,
        'year' => (string) $academicYearId,
        'class' => $classId,
        'status' => 1,
    ]);
}

$school = $db->table('schools')->where('id', TARGET_SCHOOL_ID)->get(1)->getRowArray();
if (!$school || strpos(compact_name((string) ($school['name'] ?? '')), 'KAYONZA') === false) {
    $school = $db->table('schools')->where('acronym', TARGET_ACRONYM)->get(1)->getRowArray();
}
if (!$school || (int) $school['id'] !== TARGET_SCHOOL_ID
    || strpos(compact_name((string) ($school['name'] ?? '')), 'KAYONZA') === false) {
    fwrite(STDERR, "Target Kayonza school was not found at expected school id 35\n");
    exit(1);
}

$year = $db->table('academic_year')
    ->where('school_id', TARGET_SCHOOL_ID)
    ->where('title', ACADEMIC_YEAR_TITLE)
    ->get(1)
    ->getRowArray();

$now = date('Y-m-d H:i:s');
$db->transStart();

if (!$year) {
    $db->table('academic_year')->insert([
        'school_id' => TARGET_SCHOOL_ID,
        'title' => ACADEMIC_YEAR_TITLE,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $academicYearId = (int) $db->insertID();
} else {
    $academicYearId = (int) $year['id'];
}

$schoolInfo = $data['school'];
$db->table('schools')->where('id', TARGET_SCHOOL_ID)->update([
    'name' => clean_text($schoolInfo['name'] ?? '', 100),
    'phone' => clean_text($schoolInfo['phones'] ?? '', 50),
    'email' => clean_text($schoolInfo['email'] ?? '', 100),
    'slogan' => clean_text($schoolInfo['slogan'] ?? '', 100),
    'head_master' => clean_text($schoolInfo['director'] ?? '', 100),
    'updated_at' => $now,
]);

$staffByName = [];
$staffByRole = [];
$staffCreated = 0;
$staffUpdated = 0;
foreach ($data['staff'] as $staff) {
    $fname = clean_text($staff['fname'] ?? '');
    $lname = clean_text($staff['lname'] ?? '');
    $fullName = trim($fname . ' ' . $lname);
    if ($fullName === '') {
        continue;
    }
    $phone = clean_text($staff['phone'] ?? '', 20);
    $post = position_post($db, (string) ($staff['position'] ?? ''));
    $existing = $db->table('staffs')
        ->where('school_id', TARGET_SCHOOL_ID)
        ->groupStart()
            ->where('fname', $fname)
            ->where('lname', $lname)
        ->groupEnd()
        ->get(1)
        ->getRowArray();
    if (!$existing && $phone !== '') {
        $existing = $db->table('staffs')
            ->where('school_id', TARGET_SCHOOL_ID)
            ->where('phone', $phone)
            ->get(1)
            ->getRowArray();
    }

    if ($existing) {
        $staffId = (int) $existing['id'];
        $db->table('staffs')->where('id', $staffId)->update([
            'fname' => $fname,
            'lname' => $lname,
            'phone' => $phone,
            'post' => $post,
            'status' => 2,
            'updated_at' => $now,
            'updated_by' => CREATED_BY,
        ]);
        $staffUpdated++;
    } else {
        $email = staff_email($fname, $lname, $db);
        $db->table('staffs')->insert([
            'school_id' => TARGET_SCHOOL_ID,
            'fname' => $fname,
            'lname' => $lname,
            'phone' => $phone,
            'password' => password_hash(DEFAULT_PASSWORD, PASSWORD_DEFAULT),
            'status' => 2,
            'last_login' => 0,
            'email' => $email,
            'post' => $post,
            'shift_id' => 0,
            'country' => 'Rwanda',
            'city' => 'Kayonza',
            'address' => 'Kayonza, Rwanda',
            'photo' => '',
            'lang' => 'en',
            'next_login' => 0,
            'reset_exp' => 0,
            'created_at' => $now,
            'created_by' => CREATED_BY,
            'updated_at' => $now,
            'updated_by' => CREATED_BY,
            'updateVersion' => 1,
        ]);
        $staffId = (int) $db->insertID();
        $staffCreated++;
    }
    $staffByName[compact_name($fullName)] = $staffId;
    $staffByRole[strtoupper(clean_text($staff['position'] ?? ''))] = $staffId;
}

$mentor = $staffByRole['HEAD TEACHER'] ?? null;
if (!$mentor) {
    foreach ($staffByRole as $role => $staffId) {
        if (strpos($role, 'HEAD TEACHER') !== false) {
            $mentor = $staffId;
            break;
        }
    }
}
$mentor = $mentor
    ?? ($staffByRole['DOS'] ?? null)
    ?? ($staffByRole['ACCOUNTANT'] ?? null)
    ?? (reset($staffByName) ?: 0);
if ((int) $mentor <= 0) {
    throw new RuntimeException('No staff member is available to mentor the classes');
}

$classCache = [];
$classesCreated = 0;
$studentCreated = 0;
$studentUpdated = 0;
$enrolled = 0;
$counterRow = $db->table('reg_number')
    ->where('school_id', TARGET_SCHOOL_ID)
    ->where('academic_year', YEAR_CODE)
    ->get(1)
    ->getRowArray();
$nextReg = (int) ($counterRow['next_number'] ?? 1);
$startReg = $nextReg;

$existingStudents = $db->table('students')
    ->where('school_id', TARGET_SCHOOL_ID)
    ->get()
    ->getResultArray();
$studentsByName = [];
foreach ($existingStudents as $existingStudent) {
    $key = compact_name(trim($existingStudent['fname'] . ' ' . $existingStudent['lname']));
    if ($key !== '') {
        $studentsByName[$key] = $existingStudent;
    }
}

foreach ($data['classes'] as $student) {
    $classLabel = class_level_label((string) ($student['class_label'] ?? ''));
    $fullName = clean_text($student['full_name'] ?? '');
    if ($classLabel === '' || $fullName === '') {
        continue;
    }
    if (!isset($classCache[$classLabel])) {
        $classCache[$classLabel] = ensure_class($db, $classLabel, (int) $mentor, $now);
        if ($classCache[$classLabel]['created']) {
            $classesCreated++;
        }
    }
    $classId = (int) $classCache[$classLabel]['id'];
    $fname = clean_text($student['fname'] ?? '');
    $lname = clean_text($student['lname'] ?? '');
    $key = compact_name($fullName);
    $existing = $studentsByName[$key] ?? null;

    if ($existing) {
        $studentId = (int) $existing['id'];
        $db->table('students')->where('id', $studentId)->update([
            'status' => 1,
            'updated_at' => $now,
            'updated_by' => CREATED_BY,
        ]);
        $studentUpdated++;
    } else {
        $studentRegno = regno($db, $nextReg);
        $db->table('students')->insert([
            'school_id' => TARGET_SCHOOL_ID,
            'fname' => $fname !== '' ? $fname : 'UNKNOWN',
            'lname' => $lname,
            'phone' => '',
            'email' => '',
            'regno' => $studentRegno,
            'sex' => 'U',
            'dob' => default_dob($classLabel),
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
        $studentCreated++;
        $studentsByName[$key] = [
            'id' => $studentId,
            'fname' => $fname,
            'lname' => $lname,
        ];
    }
    enroll($db, $studentId, $classId, $academicYearId);
    $enrolled++;
}

if ($nextReg > $startReg) {
    if ($counterRow) {
        $db->table('reg_number')->where('id', (int) $counterRow['id'])
            ->update(['next_number' => $nextReg]);
    } else {
        $db->table('reg_number')->insert([
            'school_id' => TARGET_SCHOOL_ID,
            'academic_year' => YEAR_CODE,
            'next_number' => $nextReg,
        ]);
    }
}

if ($dryRun) {
    $db->transRollback();
    echo "DRY RUN — no database changes committed\n";
} else {
    $db->transComplete();
    if (!$db->transStatus()) {
        fwrite(STDERR, "Import transaction failed\n");
        exit(1);
    }
}

echo "School: " . ($school['name'] ?? TARGET_ACRONYM) . " (id " . TARGET_SCHOOL_ID . ")\n";
echo "Academic year: " . ACADEMIC_YEAR_TITLE . " (id {$academicYearId})\n";
echo "Staff created: {$staffCreated}; updated: {$staffUpdated}\n";
echo "Classes created: {$classesCreated}\n";
echo "Students created: {$studentCreated}; existing reactivated: {$studentUpdated}\n";
echo "Enrollments ensured: {$enrolled}\n";
echo "Source students: " . count($data['classes']) . "; source staff: " . count($data['staff']) . "\n";

exit(0);
