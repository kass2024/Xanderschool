<?php
/**
 * Seed periodic CAT + Exam marks for all P4 A students and courses (Term 1, 2026-2027).
 * Run: docker exec xander_school_app php /var/www/html/deploy/seed_wisdom_p4a_marks.php
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
const TERM_NUMBER = 1;
const PERIODS = [1, 2, 3, 4];
const CAT_TYPE = '';
const CREATED_BY = 39;
const EXAM_DATE_BASE = 1713139200; // 2026-04-15

function resolve_active_term_id(\CodeIgniter\Database\BaseConnection $db): ?int
{
    $row = $db->table('active_term')
        ->where('school_id', SCHOOL_ID)
        ->where('academic_year', ACADEMIC_YEAR_ID)
        ->where('term', TERM_NUMBER)
        ->get(1)
        ->getRowArray();

    return $row ? (int) $row['id'] : null;
}

function sample_mark(int $studentId, int $courseId, string $kind, int $outOf, int $period): float
{
    if ($outOf <= 0) {
        return 0.0;
    }
    $seed = crc32($studentId . ':' . $courseId . ':' . $kind . ':' . $period);
    $base = 0.40 + ($seed % 45) / 100;
    if ($kind === 'exam') {
        $base += 0.04;
    }
    $base -= ($period - 1) * 0.015;
    if ($base < 0.35) {
        $base = 0.35;
    }
    if ($base > 0.96) {
        $base = 0.96;
    }
    $mark = round($outOf * $base, 1);
    if ($mark < 0) {
        $mark = 0.0;
    }
    if ($mark > $outOf) {
        $mark = (float) $outOf;
    }

    return $mark;
}

function upsert_mark(
    \CodeIgniter\Database\BaseConnection $db,
    int $termId,
    int $studentId,
    int $courseId,
    int $classId,
    int $markType,
    int $period,
    float $mark,
    int $outOf,
    int $examDate
): string {
    $existing = $db->table('marks')
        ->where('student_id', $studentId)
        ->where('term', $termId)
        ->where('course_id', $courseId)
        ->where('class_id', $classId)
        ->where('mark_type', $markType)
        ->where('cat_type', CAT_TYPE)
        ->where('period', $period)
        ->get(1)
        ->getRowArray();

    $payload = [
        'student_id' => $studentId,
        'term' => $termId,
        'examDate' => $examDate,
        'course_id' => $courseId,
        'class_id' => $classId,
        'mark_type' => $markType,
        'marks' => $mark,
        'outof' => $outOf,
        'cat_type' => CAT_TYPE,
        'period' => $period,
        'created_by' => CREATED_BY,
    ];

    if ($existing) {
        $db->table('marks')->where('id', (int) $existing['id'])->update([
            'marks' => $mark,
            'outof' => $outOf,
            'examDate' => $examDate,
        ]);

        return 'updated';
    }

    $db->table('marks')->insert($payload);

    return 'created';
}

$termId = resolve_active_term_id($db);
if ($termId === null) {
    fwrite(STDERR, "Active term not found for school " . SCHOOL_ID . ", year " . ACADEMIC_YEAR_ID . ", term " . TERM_NUMBER . ".\n");
    exit(1);
}

$students = $db->table('students st')
    ->select('st.id, st.regno, st.fname, st.lname')
    ->join('class_records cr', 'cr.student = st.id')
    ->where('st.school_id', SCHOOL_ID)
    ->where('st.status', 1)
    ->where('cr.class', CLASS_ID)
    ->where('cr.year', (string) ACADEMIC_YEAR_ID)
    ->where('cr.status', 1)
    ->orderBy('st.regno', 'ASC')
    ->get()
    ->getResultArray();

$courses = $db->table('courses c')
    ->select('c.id, c.code, c.title, c.marks')
    ->join('course_records cr', 'cr.course = c.id')
    ->where('c.school_id', SCHOOL_ID)
    ->where('cr.class', CLASS_ID)
    ->where('cr.year', ACADEMIC_YEAR_ID)
    ->orderBy('c.title', 'ASC')
    ->orderBy('c.code', 'ASC')
    ->get()
    ->getResultArray();

if ($students === [] || $courses === []) {
    fwrite(STDERR, "No students or courses found for P4 A.\n");
    exit(1);
}

$removed = (int) $db->table('marks')
    ->where('class_id', CLASS_ID)
    ->where('term', $termId)
    ->countAllResults();

$db->table('marks')
    ->where('class_id', CLASS_ID)
    ->where('term', $termId)
    ->delete();

echo "Removed {$removed} old mark row(s) for P4 A term {$termId}.\n";
echo 'Seeding periodic marks: P4 A, term id ' . $termId . ', periods ' . implode(',', PERIODS) . ', ';
echo count($students) . ' students, ' . count($courses) . " courses\n";

$created = 0;
$updated = 0;

foreach (PERIODS as $period) {
    echo "Period {$period}:\n";
    $examDate = EXAM_DATE_BASE + (($period - 1) * 14 * 86400);

    foreach ($courses as $course) {
        $courseId = (int) $course['id'];
        $outOf = max(1, (int) round((float) ($course['marks'] ?? 0)));

        foreach ($students as $student) {
            $studentId = (int) $student['id'];

            foreach ([1 => 'cat', 2 => 'exam'] as $markType => $kind) {
                $mark = sample_mark($studentId, $courseId, $kind, $outOf, $period);
                $result = upsert_mark(
                    $db,
                    $termId,
                    $studentId,
                    $courseId,
                    CLASS_ID,
                    $markType,
                    $period,
                    $mark,
                    $outOf,
                    $examDate
                );
                if ($result === 'created') {
                    $created++;
                } else {
                    $updated++;
                }
            }
        }

        echo "  {$course['code']} - {$course['title']} (out of {$outOf})\n";
    }
}

echo "\nSummary: created {$created}, updated {$updated}\n";

$summary = $db->query(
    'SELECT m.period,
            COUNT(DISTINCT m.student_id) AS students,
            SUM(m.mark_type = 1) AS cat_rows,
            SUM(m.mark_type = 2) AS exam_rows
     FROM marks m
     WHERE m.class_id = ? AND m.term = ? AND m.period > 0
     GROUP BY m.period
     ORDER BY m.period',
    [CLASS_ID, $termId]
)->getResultArray();

echo "\nBy period:\n";
foreach ($summary as $row) {
    echo sprintf(
        "  Period %s: students=%s  CAT=%s  EXAM=%s\n",
        $row['period'],
        $row['students'],
        $row['cat_rows'],
        $row['exam_rows']
    );
}

$periodZero = $db->table('marks')
    ->where('class_id', CLASS_ID)
    ->where('term', $termId)
    ->where('period', 0)
    ->countAllResults();

$total = $db->table('marks')
    ->where('class_id', CLASS_ID)
    ->where('term', $termId)
    ->where('period >', 0)
    ->countAllResults();

echo "\nPeriod 0 rows remaining: {$periodZero}\n";
echo "Total periodic mark rows: {$total}\n";

exit(0);
