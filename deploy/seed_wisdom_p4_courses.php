<?php
/**
 * Seed P4 REB courses/categories from Wisdom School Musanze report card layout.
 * Source: AKARIZA NEZA LINCKA P4.pdf (Upper Primary P4, 2025/26).
 *
 * Run: docker exec xander_school_app php /var/www/html/deploy/seed_wisdom_p4_courses.php
 */
declare(strict_types=1);

define('FCPATH', __DIR__ . '/../public/');
chdir(FCPATH);
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/Config/Paths.php';

$paths = new Config\Paths();
require rtrim($paths->systemDirectory, '/ ') . '/bootstrap.php';

$db = \Config\Database::connect();

const WISDOM_RWANDA_NAMES = ['WISDOM SCHOOL RWANDA', 'Wisdom School Rwanda'];
const CREATED_BY = 1;
const PROGRAM_TYPE = 'reb';
const CREATE_SOURCE = 'manual';

/** @return array<string, array<int, array<string, mixed>>> */
function p4_course_catalog(): array
{
    return [
        'Examinable Subjects' => [
            ['title' => 'English', 'code' => 'P4-ENG', 'credit' => 20.0, 'marks' => 200],
            ['title' => 'Mathematics', 'code' => 'P4-MAT', 'credit' => 20.0, 'marks' => 200],
            ['title' => 'Science / SET', 'code' => 'P4-SCI', 'credit' => 20.0, 'marks' => 200],
            ['title' => 'Kinyarwanda', 'code' => 'P4-KIN', 'credit' => 20.0, 'marks' => 200],
            ['title' => 'Social & Religious Studies', 'code' => 'P4-SRS', 'credit' => 20.0, 'marks' => 200],
        ],
        'Non-Examinable Subjects' => [
            ['title' => 'French', 'code' => 'P4-FRE', 'credit' => 4.0, 'marks' => 40],
            ['title' => 'Behaviour', 'code' => 'P4-BEH', 'credit' => 10.0, 'marks' => 100],
        ],
    ];
}

function resolve_school_id(\CodeIgniter\Database\BaseConnection $db): ?int
{
    foreach (WISDOM_RWANDA_NAMES as $name) {
        $row = $db->table('schools')->where('name', $name)->get(1)->getRowArray();
        if ($row) {
            return (int) $row['id'];
        }
    }
    $row = $db->table('schools')->like('name', 'WISDOM SCHOOL RWANDA', 'both')->get(1)->getRowArray();
    return $row ? (int) $row['id'] : null;
}

function ensure_meta_columns(\CodeIgniter\Database\BaseConnection $db): void
{
    if (!$db->tableExists('courses')) {
        return;
    }
    $fields = $db->getFieldNames('courses');
    if (!in_array('program_type', $fields, true)) {
        $db->query("ALTER TABLE `courses` ADD COLUMN `program_type` varchar(16) NOT NULL DEFAULT 'tvet' AFTER `marks`");
        $fields[] = 'program_type';
    }
    if (!in_array('create_source', $fields, true)) {
        $db->query("ALTER TABLE `courses` ADD COLUMN `create_source` varchar(16) NOT NULL DEFAULT 'manual' AFTER `program_type`");
    }
}

function resolve_category_id(\CodeIgniter\Database\BaseConnection $db, int $schoolId, string $title): int
{
    $title = trim($title);
    $rows = $db->table('course_category')->where('school_id', $schoolId)->get()->getResultArray();
    foreach ($rows as $row) {
        if (strcasecmp(trim((string) ($row['title'] ?? '')), $title) === 0) {
            return (int) $row['id'];
        }
    }
    $db->table('course_category')->insert([
        'school_id' => $schoolId,
        'title' => $title,
        'status' => 1,
    ]);
    return (int) $db->insertID();
}

$schoolId = resolve_school_id($db);
if ($schoolId === null) {
    fwrite(STDERR, "WISDOM SCHOOL RWANDA not found.\n");
    exit(1);
}

ensure_meta_columns($db);

$school = $db->table('schools')->where('id', $schoolId)->get(1)->getRowArray();
echo 'School: ' . ($school['name'] ?? $schoolId) . " [{$schoolId}]\n";

$createdCategories = 0;
$createdCourses = 0;
$skippedCourses = 0;
$updatedCourses = 0;

foreach (p4_course_catalog() as $categoryTitle => $courses) {
    $before = $db->table('course_category')
        ->where('school_id', $schoolId)
        ->where('title', $categoryTitle)
        ->countAllResults();
    $categoryId = resolve_category_id($db, $schoolId, $categoryTitle);
    if ($before === 0) {
        $createdCategories++;
        echo "Created category: {$categoryTitle} [{$categoryId}]\n";
    } else {
        echo "Category exists: {$categoryTitle} [{$categoryId}]\n";
    }

    foreach ($courses as $course) {
        $code = (string) $course['code'];
        $title = (string) $course['title'];
        $credit = (float) $course['credit'];
        $marks = (int) $course['marks'];

        $existing = $db->table('courses')
            ->where('school_id', $schoolId)
            ->where('code', $code)
            ->get(1)
            ->getRowArray();

        if ($existing) {
            $needsUpdate = ((int) ($existing['category'] ?? 0) !== $categoryId)
                || ((string) ($existing['title'] ?? '') !== $title)
                || ((float) ($existing['credit'] ?? 0) !== $credit)
                || ((int) ($existing['marks'] ?? 0) !== $marks);

            if ($needsUpdate) {
                $db->table('courses')->where('id', (int) $existing['id'])->update([
                    'title' => $title,
                    'category' => $categoryId,
                    'credit' => $credit,
                    'marks' => $marks,
                    'program_type' => PROGRAM_TYPE,
                ]);
                $updatedCourses++;
                echo "Updated course: {$code} — {$title}\n";
            } else {
                $skippedCourses++;
                echo "Skipped (exists): {$code} — {$title}\n";
            }
            continue;
        }

        $db->table('courses')->insert([
            'school_id' => $schoolId,
            'title' => $title,
            'code' => $code,
            'category' => $categoryId,
            'credit' => $credit,
            'marks' => $marks,
            'program_type' => PROGRAM_TYPE,
            'create_source' => CREATE_SOURCE,
            'created_by' => CREATED_BY,
            'updated_by' => 0,
        ]);
        $createdCourses++;
        echo "Created course: {$code} — {$title} (cat {$categoryId}, marks {$marks})\n";
    }
}

echo "\nSummary: categories +{$createdCategories}, courses +{$createdCourses}, updated {$updatedCourses}, skipped {$skippedCourses}\n";

echo "\nFinal catalog:\n";
$rows = $db->query(
    'SELECT c.code, c.title, cc.title AS category, c.credit, c.marks, c.program_type
     FROM courses c
     JOIN course_category cc ON cc.id = c.category
     WHERE c.school_id = ?
     ORDER BY cc.title, c.title',
    [$schoolId]
)->getResultArray();
foreach ($rows as $row) {
    echo sprintf(
        "  %-8s  %-30s  %-24s  credit=%s  marks=%s  %s\n",
        $row['code'],
        $row['title'],
        $row['category'],
        $row['credit'],
        $row['marks'],
        $row['program_type'] ?? 'tvet'
    );
}

exit(0);
