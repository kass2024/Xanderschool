ALTER TABLE `budget_lines`
  ADD COLUMN `monthly_json` TEXT NULL;

ALTER TABLE `budget_lines`
  MODIFY COLUMN `calculation_mode` ENUM('qty_unit_freq','term_sum','manual','monthly','monthly_grid') NOT NULL DEFAULT 'manual';
