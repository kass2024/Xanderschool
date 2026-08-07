<?php
/**
 * Assign all REB P4 courses to P4 A with different teachers (WISDOM SCHOOL RWANDA).
 * Run: docker exec xander_school_app php /var/www/html/deploy/seed_wisdom_p4a_course_assignments.php
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
const ALL_TERMS = '1,2,3';
const TEACHER_POST = 2;

/** @return list<array{course_code:string,teacher_id:int}> */
function assignment_plan(): array
{
    return [
        ['course_code' => 'P4-ENG', 'teacher_id' => 61], // TAYEBWA PRETTY
        ['course_code' => 'P4-FRE', 'teacher_id' => 59], // DUSHIME EDNAH
        ['course_code' => 'P4-KIN', 'teacher_id' => 64], // NKUNZWENIMANA PETRONILLE
        ['course_code' => 'math', 'teacher_id' => 56],   // MBONIGABA PONTIEN
        ['course_code' => 'P4-MAT', 'teacher_id' => 55], // DUSABIMANA EMMANUEL
        ['course_code' => 'P4-SCI', 'teacher_id' => 58], // IRUMVA PATRICK
        ['course_code' => 'P4-SRS', 'teacher_id' => 62], // BUGINGO PATIENCE
    ];
}

function teacher_name(\CodeIgniter\Database\BaseConnection $db, int $teacherId): string
{
    $row = $db->table('staffs')->select('fname,lname')->where('id', $teacherId)->get(1)->getRowArray();
    if (!$row) {
        return (string) $teacherId;
    }

    return trim(($row['fname'] ?? '') . ' ' . ($row['lname'] ?? ''));
}

$class = $db->table('classes c')
    ->select('c.id, c.title, l.title AS level_name')
    ->join('levels l', 'l.id = c.level')
    ->where('c.id', CLASS_ID)
    ->where('c.school_id', SCHOOL_ID)
    ->get(1)
    ->getRowArray();

if (!$class) {
    fwrite(STDERR, "Class " . CLASS_ID . " not found.\n");
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

echo 'Assigning courses to ' . ($class['level_name'] ?? 'P4') . ' ' . ($class['title'] ?? 'A');
echo ' — year ' . ($year['title'] ?? ACADEMIC_YEAR_ID) . "\n";

$created = 0;
$updated = 0;
$skipped = 0;

foreach (assignment_plan() as $item) {
    $courseCode = (string) $item['course_code'];
    $teacherId = (int) $item['teacher_id'];

    $course = $db->table('courses')
        ->where('school_id', SCHOOL_ID)
        ->where('code', $courseCode)
        ->get(1)
        ->getRowArray();

    if (!$course) {
        echo "Missing course: {$courseCode}\n";
        continue;
    }

    $teacher = $db->table('staffs')
        ->where('id', $teacherId)
        ->where('school_id', SCHOOL_ID)
        ->where('post', TEACHER_POST)
        ->get(1)
        ->getRowArray();

    if (!$teacher) {
        echo "Missing teacher {$teacherId} for {$courseCode}\n";
        continue;
    }

    $courseId = (int) $course['id'];
    $existing = $db->table('course_records')
        ->where('course', $courseId)
        ->where('class', CLASS_ID)
        ->where('year', ACADEMIC_YEAR_ID)
        ->get(1)
        ->getRowArray();

    $payload = [
        'course' => $courseId,
        'lecturer' => $teacherId,
        'class' => CLASS_ID,
        'year' => ACADEMIC_YEAR_ID,
        'term' => ALL_TERMS,
    ];

    $teacherLabel = teacher_name($db, $teacherId);

    if ($existing) {
        if ((int) ($existing['lecturer'] ?? 0) === $teacherId && (string) ($existing['term'] ?? '') === ALL_TERMS) {
            $skipped++;
            echo "Skipped (ok): {$courseCode} - {$course['title']} -> {$teacherLabel}\n";
            continue;
        }
        $db->table('course_records')->where('id', (int) $existing['id'])->update([
            'lecturer' => $teacherId,
            'term' => ALL_TERMS,
        ]);
        $updated++;
        echo "Updated: {$courseCode} - {$course['title']} -> {$teacherLabel}\n";
        continue;
    }

    $db->table('course_records')->insert($payload);
    $created++;
    echo "Assigned: {$courseCode} - {$course['title']} -> {$teacherLabel}\n";
}

echo "\nSummary: created {$created}, updated {$updated}, skipped {$skipped}\n";

$rows = $db->query(
    'SELECT c.code, c.title, CONCAT(s.fname, " ", s.lname) AS teacher, cr.term
     FROM course_records cr
     JOIN courses c ON c.id = cr.course
     JOIN staffs s ON s.id = cr.lecturer
     WHERE cr.class = ? AND cr.year = ? AND c.school_id = ?
     ORDER BY c.title, c.code',
    [CLASS_ID, ACADEMIC_YEAR_ID, SCHOOL_ID]
)->getResultArray();

echo "\nP4 A assignments (" . count($rows) . "):\n";
foreach ($rows as $row) {
    echo sprintf("  %-8s  %-28s  %-28s  terms %s\n", $row['code'], $row['title'], $row['teacher'], $row['term']);
}

exit(0);
