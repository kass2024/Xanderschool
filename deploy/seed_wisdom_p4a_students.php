<?php
/**
 * Seed / complete sample students in WISDOM SCHOOL RWANDA — P4 A (class 180), year 2026-2027.
 * Fills full registration fields and two parent visitors per student (parent visiting module).
 *
 * Run: docker exec xander_school_app php /var/www/html/deploy/seed_wisdom_p4a_students.php
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
const CLASS_ID = 180;
const ACADEMIC_YEAR_ID = 16;
const CREATED_BY = 1;
const YEAR_CODE = '26';
const VILLAGE_ID = 1202; // Cyasure, Musanze

/** @return list<array<string,mixed>> */
function sample_students(): array
{
    return [
        ['fname' => 'AKALIZA', 'lname' => 'NEZA', 'sex' => 'F', 'dob' => '2016-03-14', 'religion' => 'Catholics', 'father' => 'HABIMANA Theogene', 'ft_phone' => '0788123456', 'mother' => 'NYIRAMANA Marie', 'mt_phone' => '0788234567', 'guardian' => 'HABIMANA Valens', 'gd_phone' => '0788343210'],
        ['fname' => 'MUGISHA', 'lname' => 'INNOCENT', 'sex' => 'M', 'dob' => '2016-07-22', 'religion' => 'Other Christians', 'father' => 'MUGISHA Pierre', 'ft_phone' => '0788345678', 'mother' => 'MUKAMURENZI Alice', 'mt_phone' => '0788456789', 'guardian' => '', 'gd_phone' => ''],
        ['fname' => 'UWASE', 'lname' => 'MARY', 'sex' => 'F', 'dob' => '2016-01-08', 'religion' => 'Catholics', 'father' => 'UWASE Emmanuel', 'ft_phone' => '0788567890', 'mother' => 'UWASE Claudine', 'mt_phone' => '0788678901', 'guardian' => 'UWASE Devote', 'gd_phone' => '0788789010'],
        ['fname' => 'HABIMANA', 'lname' => 'ERIC', 'sex' => 'M', 'dob' => '2015-11-30', 'religion' => 'Catholics', 'father' => 'HABIMANA Jean', 'ft_phone' => '0788789012', 'mother' => 'NYIRANDEGE Donatile', 'mt_phone' => '0788890123', 'guardian' => '', 'gd_phone' => ''],
        ['fname' => 'NIYONSABA', 'lname' => 'DIVINE', 'sex' => 'F', 'dob' => '2016-05-17', 'religion' => 'Adventist', 'father' => 'NIYONSABA Claude', 'ft_phone' => '0788901234', 'mother' => 'MUKANDUTIYE Vestine', 'mt_phone' => '0788012345', 'guardian' => 'NIYONSABA Ange', 'gd_phone' => '0788123001'],
        ['fname' => 'IRADUKUNDA', 'lname' => 'JEAN', 'sex' => 'M', 'dob' => '2016-09-03', 'religion' => 'Catholics', 'father' => 'IRADUKUNDA Francois', 'ft_phone' => '0788123987', 'mother' => 'UMUTONI Esperance', 'mt_phone' => '0788234876', 'guardian' => '', 'gd_phone' => ''],
        ['fname' => 'MUKAMURENZI', 'lname' => 'GRACE', 'sex' => 'F', 'dob' => '2016-02-25', 'religion' => 'Other Christians', 'father' => 'MUKAMURENZI Samuel', 'ft_phone' => '0788345765', 'mother' => 'NYIRABAZUNGU Angelique', 'mt_phone' => '0788456654', 'guardian' => 'MUKAMURENZI Olive', 'gd_phone' => '0788567002'],
        ['fname' => 'NDAYISENGA', 'lname' => 'KEVIN', 'sex' => 'M', 'dob' => '2015-12-11', 'religion' => 'Catholics', 'father' => 'NDAYISENGA Robert', 'ft_phone' => '0788567543', 'mother' => 'MUKAMANA Consolee', 'mt_phone' => '0788678432', 'guardian' => '', 'gd_phone' => ''],
        ['fname' => 'UMUHIRE', 'lname' => 'SANDRINE', 'sex' => 'F', 'dob' => '2016-06-19', 'religion' => 'Catholics', 'father' => 'UMUHIRE Daniel', 'ft_phone' => '0788789321', 'mother' => 'NYIRANEZA Beatrice', 'mt_phone' => '0788890210', 'guardian' => 'UMUHIRE Chantal', 'gd_phone' => '0788901203'],
        ['fname' => 'BYIRINGIRO', 'lname' => 'PATRICK', 'sex' => 'M', 'dob' => '2016-04-02', 'religion' => 'Other Christians', 'father' => 'BYIRINGIRO Elie', 'ft_phone' => '0788901098', 'mother' => 'MUKESHIMANA Josephine', 'mt_phone' => '0788010987', 'guardian' => '', 'gd_phone' => ''],
        ['fname' => 'ISHIMWE', 'lname' => 'ALINE', 'sex' => 'F', 'dob' => '2016-08-27', 'religion' => 'Catholics', 'father' => 'ISHIMWE Emmanuel', 'ft_phone' => '0788129876', 'mother' => 'UWIMANA Jeanne', 'mt_phone' => '0788238765', 'guardian' => 'ISHIMWE Solange', 'gd_phone' => '0788345004'],
        ['fname' => 'HATEGEKIMANA', 'lname' => 'BOSCO', 'sex' => 'M', 'dob' => '2015-10-15', 'religion' => 'Catholics', 'father' => 'HATEGEKIMANA Jean de Dieu', 'ft_phone' => '0788347654', 'mother' => 'NYIRAMBARUBU Speciose', 'mt_phone' => '0788456543', 'guardian' => '', 'gd_phone' => ''],
        ['fname' => 'MUKANDUTIYE', 'lname' => 'CHANTAL', 'sex' => 'F', 'dob' => '2016-03-08', 'religion' => 'Adventist', 'father' => 'MUKANDUTIYE Vincent', 'ft_phone' => '0788565432', 'mother' => 'MUKAMANA Delphine', 'mt_phone' => '0788674321', 'guardian' => 'MUKANDUTIYE Odette', 'gd_phone' => '0788789005'],
        ['fname' => 'NSHUTI', 'lname' => 'SAMUEL', 'sex' => 'M', 'dob' => '2016-01-21', 'religion' => 'Other Christians', 'father' => 'NSHUTI John Bosco', 'ft_phone' => '0788783210', 'mother' => 'MUKAMURENZI Olive', 'mt_phone' => '0788892109', 'guardian' => '', 'gd_phone' => ''],
        ['fname' => 'UWIMANA', 'lname' => 'JOY', 'sex' => 'F', 'dob' => '2016-11-05', 'religion' => 'Catholics', 'father' => 'UWIMANA Alexis', 'ft_phone' => '0788902108', 'mother' => 'NYIRABAGENZI Francine', 'mt_phone' => '0788012098', 'guardian' => 'UWIMANA Solange', 'gd_phone' => '0788123406'],
    ];
}

function make_regno(int $seq): string
{
    return YEAR_CODE . sprintf('%03d', SCHOOL_ID) . sprintf('%04d', $seq);
}

function student_email(string $fname, string $lname): string
{
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '.', trim($fname . '.' . $lname), -1, $count));
    $slug = trim($slug, '.');
    if ($slug === '') {
        $slug = 'student';
    }

    return $slug . '@wisdom.sample';
}

/** @param array<string,mixed> $student */
function student_payload(array $student, string $regno, string $now): array
{
    return [
        'school_id' => SCHOOL_ID,
        'fname' => $student['fname'],
        'lname' => $student['lname'],
        'phone' => '',
        'email' => student_email((string) $student['fname'], (string) $student['lname']),
        'regno' => $regno,
        'sex' => $student['sex'],
        'dob' => $student['dob'],
        'photo' => '',
        'village_id' => VILLAGE_ID,
        'studying_mode' => 1,
        'religion' => (string) ($student['religion'] ?? 'Catholics'),
        'nationality' => 'rwanda',
        'card' => '',
        'transport_money' => 0,
        'wallet_balance' => 0.00,
        'wallet_pin' => null,
        'father' => (string) $student['father'],
        'ft_phone' => (string) $student['ft_phone'],
        'mother' => (string) $student['mother'],
        'mt_phone' => (string) $student['mt_phone'],
        'guardian' => (string) ($student['guardian'] ?? ''),
        'gd_phone' => (string) ($student['gd_phone'] ?? ''),
        'updated_at' => $now,
        'updated_by' => 0,
        'status' => 1,
    ];
}

/** @param array<string,mixed> $student */
function visitor_rows(array $student): array
{
    return [
        [
            'names' => (string) $student['father'],
            'phone' => (string) $student['ft_phone'],
            'relationship' => 'Father',
        ],
        [
            'names' => (string) $student['mother'],
            'phone' => (string) $student['mt_phone'],
            'relationship' => 'Mother',
        ],
    ];
}

function ensure_class_record(\CodeIgniter\Database\BaseConnection $db, int $studentId): void
{
    $classRecord = $db->table('class_records')
        ->where('student', $studentId)
        ->where('year', (string) ACADEMIC_YEAR_ID)
        ->where('class', CLASS_ID)
        ->get(1)
        ->getRowArray();

    if (!$classRecord) {
        $db->table('class_records')->insert([
            'student' => $studentId,
            'year' => (string) ACADEMIC_YEAR_ID,
            'class' => CLASS_ID,
            'status' => 1,
        ]);
    }
}

function ensure_visitors(int $studentId, array $student): int
{
    $visitorMdl = new \App\Models\StudentVisitorModel();
    $visitorMdl->ensureSchema();

    $visitorMdl->purgeForStudent(SCHOOL_ID, $studentId);

    $rows = visitor_rows($student);
    foreach ($rows as $visitor) {
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
    }

    return count($rows);
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

function ensure_visitor_settings(\CodeIgniter\Database\BaseConnection $db): void
{
    $visitorMdl = new \App\Models\StudentVisitorModel();
    $visitorMdl->ensureSchema();
    $exists = $db->table('visitor_settings')->where('school_id', SCHOOL_ID)->countAllResults();
    if ($exists === 0) {
        $visitorMdl->saveSettings(SCHOOL_ID, [
            'card_sharing' => 1,
            'min_visitors' => 2,
            'max_per_card' => 2,
        ]);
    }
}

$now = date('Y-m-d H:i:s');
ensure_visitor_settings($db);

$class = $db->table('classes c')
    ->select('c.id, c.title, l.title AS level_name')
    ->join('levels l', 'l.id = c.level')
    ->where('c.id', CLASS_ID)
    ->where('c.school_id', SCHOOL_ID)
    ->get(1)
    ->getRowArray();

if (!$class) {
    fwrite(STDERR, "Class " . CLASS_ID . " not found for school " . SCHOOL_ID . ".\n");
    exit(1);
}

$year = $db->table('academic_year')
    ->where('id', ACADEMIC_YEAR_ID)
    ->where('school_id', SCHOOL_ID)
    ->get(1)
    ->getRowArray();

if (!$year) {
    fwrite(STDERR, "Academic year " . ACADEMIC_YEAR_ID . " not found.\n");
    exit(1);
}

$village = $db->table('soma_village')->where('id', VILLAGE_ID)->get(1)->getRowArray();
if (!$village) {
    fwrite(STDERR, "Village " . VILLAGE_ID . " not found.\n");
    exit(1);
}

echo 'Completing sample students for ' . ($class['level_name'] ?? 'P4') . ' ' . ($class['title'] ?? 'A');
echo ' — year ' . ($year['title'] ?? ACADEMIC_YEAR_ID) . "\n";
echo 'Address village: ' . ($village['title'] ?? VILLAGE_ID) . " (Musanze)\n";

$created = 0;
$updated = 0;
$visitorsAdded = 0;
$nextSeq = 1;

foreach (sample_students() as $student) {
    $regno = make_regno($nextSeq);

    $existing = $db->table('students')
        ->where('school_id', SCHOOL_ID)
        ->where('regno', $regno)
        ->get(1)
        ->getRowArray();

    $payload = student_payload($student, $regno, $now);

    if ($existing) {
        $studentId = (int) $existing['id'];
        $db->table('students')->where('id', $studentId)->update($payload);
        ensure_class_record($db, $studentId);
        $added = ensure_visitors($studentId, $student);
        $visitorsAdded += $added;
        $updated++;
        echo "Updated: {$regno} — {$student['fname']} {$student['lname']} (+{$added} visitors)\n";
    } else {
        $payload['created_at'] = $now;
        $payload['created_by'] = CREATED_BY;
        $payload['updateVersion'] = 1;
        $db->table('students')->insert($payload);
        $studentId = (int) $db->insertID();
        ensure_class_record($db, $studentId);
        $added = ensure_visitors($studentId, $student);
        $visitorsAdded += $added;
        $created++;
        echo "Created: {$regno} — {$student['fname']} {$student['lname']} (+{$added} visitors)\n";
    }

    $nextSeq++;
}

bump_reg_counter($db, $nextSeq);

echo "\nSummary: created {$created}, updated {$updated}, visitors added {$visitorsAdded}\n";

$count = $db->table('class_records')
    ->where('class', CLASS_ID)
    ->where('year', (string) ACADEMIC_YEAR_ID)
    ->countAllResults();

$visitorCount = (int) $db->query(
    'SELECT COUNT(*) AS c FROM student_visitors sv
     INNER JOIN students s ON s.id = sv.student_id
     WHERE s.school_id = ? AND s.regno LIKE ? AND sv.status = 1',
    [SCHOOL_ID, '26027%']
)->getRowArray()['c'];

echo "P4 A students for {$year['title']}: {$count}\n";
echo "Registered visitors for sample students: {$visitorCount}\n";

exit(0);
