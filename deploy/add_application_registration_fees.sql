-- Online registration: fee mode + per-level / per-class fees
ALTER TABLE `application_settings`
  ADD COLUMN IF NOT EXISTS `fee_mode` VARCHAR(20) NOT NULL DEFAULT 'flat' COMMENT 'flat|level|class' AFTER `registration_fees`;

CREATE TABLE IF NOT EXISTS `application_registration_fees` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `school_id` INT NOT NULL,
  `settings_id` INT NOT NULL,
  `ref_type` VARCHAR(10) NOT NULL COMMENT 'level|class',
  `ref_id` INT NOT NULL,
  `fee_amount` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_app_reg_fee` (`settings_id`, `ref_type`, `ref_id`),
  KEY `idx_app_reg_fee_school` (`school_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `applications`
  ADD COLUMN IF NOT EXISTS `medical_status` VARCHAR(255) NULL DEFAULT 'Normal' AFTER `religion`,
  ADD COLUMN IF NOT EXISTS `cell_id` INT NULL DEFAULT NULL AFTER `medical_status`,
  ADD COLUMN IF NOT EXISTS `class_id` INT NULL DEFAULT NULL AFTER `cell_id`;
