-- Add Stream combination (A' Level) and Stream one/two/three class levels for class creation.
SET NAMES utf8mb4;

INSERT IGNORE INTO `departments` (`id`, `title`, `code`, `faculty_id`, `created_at`, `created_by`, `updated_at`, `updated_by`) VALUES
(113, 'Stream', 'STR', 1, NOW(), 1, NOW(), 0);

INSERT IGNORE INTO `levels` (`id`, `title`, `type`, `faculty_id`, `status`) VALUES
(34, 'Stream one', 2, 1, 1),
(35, 'Stream two', 2, 1, 1),
(36, 'Stream three', 2, 1, 1);

SELECT 'departments' AS kind, id, code, title FROM departments WHERE title = 'Stream';
SELECT 'levels' AS kind, id, title, faculty_id FROM levels WHERE title IN ('Stream one', 'Stream two', 'Stream three');
