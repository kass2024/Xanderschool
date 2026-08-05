-- Restore real REB/WDA faculty + class levels from cranerw_school.sql
-- Safe: REPLACE by primary key; keeps extra local rows (e.g. Senior 6 / Languages) if present.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

REPLACE INTO `faculty` (`id`, `title`, `abbrev`, `type`, `status`) VALUES
(1, 'Advanced Level', 'A'' Level', 2, 1),
(2, 'Ordinary Level', 'O'' Level', 2, 1),
(3, 'Primary', 'Primary', 2, 1),
(4, 'Administration', '', 1, 1),
(5, 'Agriculture and Food Processing', '', 1, 1),
(6, 'Arts and Craft', '', 1, 1),
(7, 'Business Services', '', 1, 1),
(8, 'Construction and Building Services', '', 1, 1),
(9, 'Energy', '', 1, 1),
(10, 'Hospitality and Tourism', '', 1, 1),
(11, 'Information and Communication Technology (ICT)', '', 1, 1),
(12, 'Manufucturing and Mining', '', 1, 1),
(13, 'Media and Film Making', '', 1, 1),
(14, 'Natural Resources Management', '', 1, 1),
(15, 'Physical fitness and sports services', '', 1, 1),
(16, 'Technical Services', '', 1, 1),
(17, 'Transport and Logistics', '', 1, 1),
(18, 'Welfare, Health & Social Services', '', 1, 1),
(19, 'Nursery', 'Nursery', 2, 1),
(20, 'Building and Construction', '', 1, 1);

-- Hide non-standard extras from General Education list (keep rows; departments may still reference them)
UPDATE `faculty`
SET `type` = 0
WHERE `id` IN (21, 22)
  AND `title` IN ('Languages', 'Humanities and Art');

REPLACE INTO `levels` (`id`, `title`, `type`, `faculty_id`, `status`) VALUES
(1, 'level 3', 1, 0, 1),
(2, 'level 4', 1, 0, 1),
(3, 'level 5', 1, 0, 1),
(4, 'S1', 2, 2, 1),
(5, 'S2', 2, 2, 1),
(6, 'S3', 2, 2, 1),
(7, 'S4', 2, 1, 1),
(8, 'S5', 2, 1, 1),
(9, 'S6', 2, 1, 1),
(10, 'P1', 2, 3, 1),
(11, 'P2', 2, 3, 1),
(12, 'P3', 2, 3, 1),
(13, 'P4', 2, 3, 1),
(14, 'P5', 2, 3, 1),
(15, 'P6', 2, 3, 1),
(16, 'Baby class', 2, 19, 0),
(17, 'Middle class', 2, 19, 0),
(18, 'Top class', 2, 19, 0),
(19, 'N1', 2, 19, 1),
(20, 'N2', 2, 19, 1),
(21, 'N3', 2, 19, 1),
(22, 'level 1', 1, 0, 1),
(23, 'level 2', 1, 0, 1),
(24, 'Senior 4', 1, 0, 1),
(25, 'Senior 5', 1, 0, 1),
(34, 'Stream one', 2, 1, 1),
(35, 'Stream two', 2, 1, 1),
(36, 'Stream three', 2, 1, 1);

SET FOREIGN_KEY_CHECKS = 1;

-- Quick verify
SELECT 'faculty_reb' AS kind, id, title FROM faculty WHERE type = 2 ORDER BY id;
SELECT 'levels_reb' AS kind, id, title, faculty_id FROM levels WHERE type = 2 AND status = 1 ORDER BY id;
