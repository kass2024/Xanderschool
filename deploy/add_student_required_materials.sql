-- Student required materials (also created at runtime via StudentMaterialSchemaModel::ensureSchema)
CREATE TABLE IF NOT EXISTS `required_materials` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `school_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(200) NOT NULL,
  `unit` VARCHAR(60) NOT NULL DEFAULT 'pcs',
  `sort_order` INT NOT NULL DEFAULT 0,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_rm_school` (`school_id`, `active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `class_required_materials` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `school_id` INT UNSIGNED NOT NULL,
  `class_id` INT UNSIGNED NOT NULL,
  `material_id` INT UNSIGNED NOT NULL,
  `academic_year` INT UNSIGNED NOT NULL,
  `quantity` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_class_mat_year` (`school_id`, `class_id`, `material_id`, `academic_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `student_material_checks` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `school_id` INT UNSIGNED NOT NULL,
  `student_id` INT UNSIGNED NOT NULL,
  `class_id` INT UNSIGNED NOT NULL,
  `material_id` INT UNSIGNED NOT NULL,
  `academic_year` INT UNSIGNED NOT NULL,
  `quantity_required` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `quantity_brought` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `notes` VARCHAR(500) NULL DEFAULT NULL,
  `checked_by` INT UNSIGNED NULL DEFAULT NULL,
  `checked_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_student_mat_year` (`student_id`, `material_id`, `academic_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
