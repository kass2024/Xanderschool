-- Special educational path: faculty + department both named Nursing ANP
SET NAMES utf8mb4;

INSERT INTO `faculty` (`title`, `abbrev`, `type`, `status`)
SELECT 'Nursing ANP', 'ANP', 3, 1
FROM DUAL
WHERE NOT EXISTS (
	SELECT 1 FROM `faculty` WHERE `type` = 3
);

UPDATE `faculty`
SET `title` = 'Nursing ANP', `abbrev` = 'ANP', `status` = 1
WHERE `type` = 3;

INSERT INTO `departments` (`title`, `code`, `faculty_id`, `created_at`, `created_by`, `updated_at`, `updated_by`)
SELECT 'Nursing ANP', 'ANP', f.id, NOW(), 0, NOW(), 0
FROM `faculty` f
WHERE f.type = 3
  AND NOT EXISTS (
	SELECT 1 FROM `departments` d
	WHERE d.faculty_id = f.id AND (d.code = 'ANP' OR d.title IN ('Nursing', 'Nursing ANP'))
);

UPDATE `departments` d
JOIN `faculty` f ON f.id = d.faculty_id
SET d.title = 'Nursing ANP', d.code = 'ANP'
WHERE f.type = 3;

INSERT INTO `levels` (`title`, `type`, `faculty_id`, `status`)
SELECT v.title, 3, f.id, 1
FROM `faculty` f
CROSS JOIN (
	SELECT 'S4' AS title
	UNION ALL SELECT 'S5'
	UNION ALL SELECT 'S6'
) v
WHERE f.type = 3
  AND NOT EXISTS (
	SELECT 1 FROM `levels` l
	WHERE l.faculty_id = f.id AND l.title = v.title
);

SELECT 'faculty_special' AS kind, id, title, abbrev, type, status FROM faculty WHERE type = 3;
SELECT 'dept_special' AS kind, d.id, d.title, d.code, d.faculty_id
FROM departments d
JOIN faculty f ON f.id = d.faculty_id
WHERE f.type = 3;
SELECT 'level_special' AS kind, l.id, l.title, l.faculty_id
FROM levels l
JOIN faculty f ON f.id = l.faculty_id
WHERE f.type = 3
ORDER BY l.title;
