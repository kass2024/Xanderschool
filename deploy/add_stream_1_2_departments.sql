-- Add Stream 1 (ST1) and Stream 2 (ST2) as A-Level combinations.
SET NAMES utf8mb4;

INSERT INTO `departments` (`title`, `code`, `faculty_id`, `created_at`, `created_by`, `updated_at`, `updated_by`)
SELECT 'Stream 1', 'ST1', COALESCE((SELECT f.faculty_id FROM (SELECT faculty_id FROM departments WHERE title = 'Stream' LIMIT 1) f), 1), NOW(), 1, NOW(), 0
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM departments WHERE title = 'Stream 1' OR code = 'ST1');

INSERT INTO `departments` (`title`, `code`, `faculty_id`, `created_at`, `created_by`, `updated_at`, `updated_by`)
SELECT 'Stream 2', 'ST2', COALESCE((SELECT f.faculty_id FROM (SELECT faculty_id FROM departments WHERE title = 'Stream' LIMIT 1) f), 1), NOW(), 1, NOW(), 0
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM departments WHERE title = 'Stream 2' OR code = 'ST2');

SELECT id, code, title, faculty_id FROM departments WHERE code IN ('STR','ST1','ST2') OR title LIKE 'Stream%' ORDER BY title;
