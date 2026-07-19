-- Pedagogical documents per class per academic year (multiple curriculum + chronogram files allowed)
CREATE TABLE IF NOT EXISTS `class_pedagogical_docs` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `academic_year` int(11) NOT NULL,
  `doc_type` varchar(32) NOT NULL COMMENT 'curriculum|chronogram',
  `term` tinyint(4) DEFAULT NULL COMMENT 'unused - full year docs',
  `file_name` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_school_year_type` (`school_id`,`academic_year`,`doc_type`),
  KEY `idx_school_class` (`school_id`,`class_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- If upgrading from single-file unique constraint:
-- ALTER TABLE `class_pedagogical_docs` DROP INDEX `uniq_class_year_type`;
