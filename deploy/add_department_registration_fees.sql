-- Per-department registration fees split by Day (1) vs Boarding (0)
ALTER TABLE `application_settings`
  MODIFY COLUMN `fee_mode` VARCHAR(20) NOT NULL DEFAULT 'flat' COMMENT 'flat|level|class|department';

ALTER TABLE `application_registration_fees`
  ADD COLUMN IF NOT EXISTS `studying_mode` TINYINT NOT NULL DEFAULT -1 COMMENT '-1=N/A, 0=boarding, 1=day' AFTER `ref_id`;

ALTER TABLE `application_registration_fees`
  MODIFY COLUMN `ref_type` VARCHAR(12) NOT NULL COMMENT 'level|class|department';

-- Widen unique key to include studying_mode (safe if index already updated)
ALTER TABLE `application_registration_fees` DROP INDEX IF EXISTS `uq_app_reg_fee`;
ALTER TABLE `application_registration_fees`
  ADD UNIQUE KEY `uq_app_reg_fee` (`settings_id`, `ref_type`, `ref_id`, `studying_mode`);
