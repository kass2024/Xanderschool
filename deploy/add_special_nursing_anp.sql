-- Special educational path: Nursing faculty + ANP department + Year 1–3 classes
SET NAMES utf8mb4;

INSERT INTO `faculty` (`title`, `abbrev`, `type`, `status`)
SELECT 'Nursing', 'ANP', 3, 1
FROM DUAL
WHERE NOT EXISTS (
	SELECT 1 FROM `faculty` WHERE `type` = 3
);

UPDATE `faculty`
SET `title` = 'Nursing', `abbrev` = 'ANP', `status` = 1
WHERE `type` = 3;

INSERT INTO `departments` (`title`, `code`, `faculty_id`, `created_at`, `created_by`, `updated_at`, `updated_by`)
SELECT 'Nursing', 'ANP', f.id, NOW(), 0, NOW(), 0
FROM `faculty` f
WHERE f.type = 3
  AND NOT EXISTS (
	SELECT 1 FROM `departments` d
	WHERE d.faculty_id = f.id AND (d.code = 'ANP' OR d.title IN ('Nursing', 'Nursing ANP'))
);

UPDATE `departments` d
JOIN `faculty` f ON f.id = d.faculty_id
SET d.title = 'Nursing', d.code = 'ANP'
WHERE f.type = 3;

INSERT INTO `levels` (`title`, `type`, `faculty_id`, `status`)
SELECT v.title, 3, f.id, 1
FROM `faculty` f
CROSS JOIN (
	SELECT 'Year 1' AS title
	UNION ALL SELECT 'Year 2'
	UNION ALL SELECT 'Year 3'
) v
WHERE f.type = 3
  AND NOT EXISTS (
	SELECT 1 FROM `levels` l
	WHERE l.faculty_id = f.id AND l.title = v.title
);

INSERT INTO `classes` (`school_id`, `level`, `department`, `title`, `mentor`, `created_by`, `created_at`, `updated_at`)
SELECT 27, l.id, d.id, '', COALESCE((SELECT s.id FROM staffs s WHERE s.school_id = 27 ORDER BY s.id LIMIT 1), 0), 0, NOW(), NOW()
FROM `faculty` f
JOIN `departments` d ON d.faculty_id = f.id AND d.code = 'ANP'
JOIN `levels` l ON l.faculty_id = f.id AND l.title IN ('Year 1', 'Year 2', 'Year 3')
WHERE f.type = 3
  AND NOT EXISTS (
	SELECT 1 FROM `classes` c
	WHERE c.school_id = 27 AND c.level = l.id AND c.department = d.id
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
SELECT 'class_special' AS kind, c.id, c.school_id, l.title AS level_name, d.title AS dept, d.code
FROM classes c
JOIN levels l ON l.id = c.level
JOIN departments d ON d.id = c.department
WHERE d.code = 'ANP' AND c.school_id = 27
ORDER BY l.title;
