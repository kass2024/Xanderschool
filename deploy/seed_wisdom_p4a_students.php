<?php
/**
 * Seed sample students in WISDOM SCHOOL RWANDA — P4 A (class 180), year 2026-2027.
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

/** @return list<array{fname:string,lname:string,sex:string,dob:string,father:string,ft_phone:string,mother:string,mt_phone:string}> */
function sample_students(): array
{
    return [
        ['fname' => 'AKALIZA', 'lname' => 'NEZA', 'sex' => 'F', 'dob' => '2016-03-14', 'father' => 'HABIMANA Theogene', 'ft_phone' => '0788123456', 'mother' => 'NYIRAMANA Marie', 'mt_phone' => '0788234567'],
        ['fname' => 'MUGISHA', 'lname' => 'INNOCENT', 'sex' => 'M', 'dob' => '2016-07-22', 'father' => 'MUGISHA Pierre', 'ft_phone' => '0788345678', 'mother' => 'MUKAMURENZI Alice', 'mt_phone' => '0788456789'],
        ['fname' => 'UWASE', 'lname' => 'MARY', 'sex' => 'F', 'dob' => '2016-01-08', 'father' => 'UWASE Emmanuel', 'ft_phone' => '0788567890', 'mother' => 'UWASE Claudine', 'mt_phone' => '0788678901'],
        ['fname' => 'HABIMANA', 'lname' => 'ERIC', 'sex' => 'M', 'dob' => '2015-11-30', 'father' => 'HABIMANA Jean', 'ft_phone' => '0788789012', 'mother' => 'NYIRANDEGE Donatile', 'mt_phone' => '0788890123'],
        ['fname' => 'NIYONSABA', 'lname' => 'DIVINE', 'sex' => 'F', 'dob' => '2016-05-17', 'father' => 'NIYONSABA Claude', 'ft_phone' => '0788901234', 'mother' => 'MUKANDUTIYE Vestine', 'mt_phone' => '0788012345'],
        ['fname' => 'IRADUKUNDA', 'lname' => 'JEAN', 'sex' => 'M', 'dob' => '2016-09-03', 'father' => 'IRADUKUNDA Francois', 'ft_phone' => '0788123987', 'mother' => 'UMUTONI Esperance', 'mt_phone' => '0788234876'],
        ['fname' => 'MUKAMURENZI', 'lname' => 'GRACE', 'sex' => 'F', 'dob' => '2016-02-25', 'father' => 'MUKAMURENZI Samuel', 'ft_phone' => '0788345765', 'mother' => 'NYIRABAZUNGU Angelique', 'mt_phone' => '0788456654'],
        ['fname' => 'NDAYISENGA', 'lname' => 'KEVIN', 'sex' => 'M', 'dob' => '2015-12-11', 'father' => 'NDAYISENGA Robert', 'ft_phone' => '0788567543', 'mother' => 'MUKAMANA Consolee', 'mt_phone' => '0788678432'],
        ['fname' => 'UMUHIRE', 'lname' => 'SANDRINE', 'sex' => 'F', 'dob' => '2016-06-19', 'father' => 'UMUHIRE Daniel', 'ft_phone' => '0788789321', 'mother' => 'NYIRANEZA Beatrice', 'mt_phone' => '0788890210'],
        ['fname' => 'BYIRINGIRO', 'lname' => 'PATRICK', 'sex' => 'M', 'dob' => '2016-04-02', 'father' => 'BYIRINGIRO Elie', 'ft_phone' => '0788901098', 'mother' => 'MUKESHIMANA Josephine', 'mt_phone' => '0788010987'],
        ['fname' => 'ISHIMWE', 'lname' => 'ALINE', 'sex' => 'F', 'dob' => '2016-08-27', 'father' => 'ISHIMWE Emmanuel', 'ft_phone' => '0788129876', 'mother' => 'UWIMANA Jeanne', 'mt_phone' => '0788238765'],
        ['fname' => 'HATEGEKIMANA', 'lname' => 'BOSCO', 'sex' => 'M', 'dob' => '2015-10-15', 'father' => 'HATEGEKIMANA Jean de Dieu', 'ft_phone' => '0788347654', 'mother' => 'NYIRAMBARUBU Speciose', 'mt_phone' => '0788456543'],
        ['fname' => 'MUKANDUTIYE', 'lname' => 'CHANTAL', 'sex' => 'F', 'dob' => '2016-03-08', 'father' => 'MUKANDUTIYE Vincent', 'ft_phone' => '0788565432', 'mother' => 'MUKAMANA Delphine', 'mt_phone' => '0788674321'],
        ['fname' => 'NSHUTI', 'lname' => 'SAMUEL', 'sex' => 'M', 'dob' => '2016-01-21', 'father' => 'NSHUTI John Bosco', 'ft_phone' => '0788783210', 'mother' => 'MUKAMURENZI Olive', 'mt_phone' => '0788892109'],
        ['fname' => 'UWIMANA', 'lname' => 'JOY', 'sex' => 'F', 'dob' => '2016-11-05', 'father' => 'UWIMANA Alexis', 'ft_phone' => '0788902108', 'mother' => 'NYIRABAGENZI Francine', 'mt_phone' => '0788012098'],
    ];
}

function make_regno(int $seq): string
{
    return YEAR_CODE . sprintf('%03d', SCHOOL_ID) . sprintf('%04d', $seq);
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

$now = date('Y-m-d H:i:s');
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

echo 'Seeding sample students for ' . ($class['level_name'] ?? 'P4') . ' ' . ($class['title'] ?? 'A');
echo ' — year ' . ($year['title'] ?? ACADEMIC_YEAR_ID) . "\n";

$created = 0;
$skipped = 0;
$nextSeq = 1;

foreach (sample_students() as $student) {
    $regno = make_regno($nextSeq);

    $existing = $db->table('students')
        ->where('school_id', SCHOOL_ID)
        ->where('regno', $regno)
        ->get(1)
        ->getRowArray();

    if ($existing) {
        $skipped++;
        echo "Skipped (exists): {$regno} — {$student['fname']} {$student['lname']}\n";
        $nextSeq++;
        continue;
    }

    $db->table('students')->insert([
        'school_id' => SCHOOL_ID,
        'fname' => $student['fname'],
        'lname' => $student['lname'],
        'phone' => '',
        'email' => '',
        'regno' => $regno,
        'sex' => $student['sex'],
        'dob' => $student['dob'],
        'photo' => '',
        'village_id' => null,
        'studying_mode' => 1,
        'religion' => '',
        'nationality' => 'rwanda',
        'card' => '',
        'transport_money' => 0,
        'wallet_balance' => 0.00,
        'wallet_pin' => null,
        'father' => $student['father'],
        'ft_phone' => $student['ft_phone'],
        'mother' => $student['mother'],
        'mt_phone' => $student['mt_phone'],
        'guardian' => '',
        'gd_phone' => '',
        'created_at' => $now,
        'created_by' => CREATED_BY,
        'updated_at' => $now,
        'updated_by' => 0,
        'status' => 1,
        'updateVersion' => 1,
    ]);

    $studentId = (int) $db->insertID();

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

    $created++;
    echo "Created: {$regno} — {$student['fname']} {$student['lname']}\n";
    $nextSeq++;
}

bump_reg_counter($db, $nextSeq);

echo "\nSummary: created {$created}, skipped {$skipped}\n";

$count = $db->table('class_records')
    ->where('class', CLASS_ID)
    ->where('year', (string) ACADEMIC_YEAR_ID)
    ->countAllResults();

echo "P4 A students for {$year['title']}: {$count}\n";

exit(0);
