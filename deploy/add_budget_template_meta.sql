-- Extra metadata for Wisdom professional budget template lines
ALTER TABLE `budget_template_lines`
  ADD COLUMN IF NOT EXISTS `calculation_mode` VARCHAR(40) NULL DEFAULT 'manual',
  ADD COLUMN IF NOT EXISTS `default_unit` VARCHAR(40) NULL,
  ADD COLUMN IF NOT EXISTS `default_frequency` DECIMAL(10,4) NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS `priority` VARCHAR(20) NULL,
  ADD COLUMN IF NOT EXISTS `funding_source` VARCHAR(80) NULL;

-- Monthly calculation mode for budget line annualization
ALTER TABLE `budget_lines`
  MODIFY COLUMN `calculation_mode` ENUM('qty_unit_freq','term_sum','manual','monthly') NOT NULL DEFAULT 'manual';
