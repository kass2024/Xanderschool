-- Budget & Cash Flow module — Wisdom Schools / multi-branch
-- Run once on production; also applied via BudgetSchemaModel::ensureSchema()

CREATE TABLE IF NOT EXISTS `organizations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(180) NOT NULL,
  `slug` VARCHAR(80) NOT NULL,
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_org_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `branches` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `organization_id` INT UNSIGNED NOT NULL,
  `school_id` INT UNSIGNED NULL DEFAULT NULL,
  `branch_code` VARCHAR(20) NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_branch_org_code` (`organization_id`, `branch_code`),
  KEY `idx_branch_school` (`school_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `staff_branch_assignments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_id` INT UNSIGNED NOT NULL,
  `branch_id` INT UNSIGNED NOT NULL,
  `is_primary` TINYINT(1) NOT NULL DEFAULT 1,
  `can_cross_branch` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_staff_branch` (`staff_id`, `branch_id`),
  KEY `idx_sba_branch` (`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `budget_permissions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `perm_key` VARCHAR(80) NOT NULL,
  `label` VARCHAR(180) NOT NULL,
  `group_name` VARCHAR(60) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_budget_perm_key` (`perm_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `post_budget_permissions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id` INT UNSIGNED NOT NULL,
  `perm_key` VARCHAR(80) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_post_budget_perm` (`post_id`, `perm_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `budget_settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `organization_id` INT UNSIGNED NOT NULL,
  `branch_id` INT UNSIGNED NULL DEFAULT NULL,
  `default_currency` VARCHAR(10) NOT NULL DEFAULT 'RWF',
  `headteacher_approval_mode` ENUM('system','evidence') NOT NULL DEFAULT 'evidence',
  `ai_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `budget_utilization_alert_pct` DECIMAL(5,2) NOT NULL DEFAULT 80.00,
  `created_by` INT NULL,
  `updated_by` INT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_budget_settings_scope` (`organization_id`, `branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `budget_periods` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `organization_id` INT UNSIGNED NOT NULL,
  `branch_id` INT UNSIGNED NOT NULL,
  `academic_year_id` INT UNSIGNED NULL,
  `title` VARCHAR(120) NOT NULL,
  `period_type` ENUM('annual','termly','monthly') NOT NULL DEFAULT 'annual',
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `status` ENUM('draft','open','closed') NOT NULL DEFAULT 'draft',
  `created_by` INT NULL,
  `updated_by` INT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_bp_branch` (`branch_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `budget_templates` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `organization_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(180) NOT NULL,
  `status` ENUM('draft','active','archived') NOT NULL DEFAULT 'draft',
  `current_version_id` INT UNSIGNED NULL,
  `created_by` INT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `budget_template_versions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `template_id` INT UNSIGNED NOT NULL,
  `version_no` INT NOT NULL DEFAULT 1,
  `original_filename` VARCHAR(255) NULL,
  `stored_filename` VARCHAR(255) NULL,
  `checksum` VARCHAR(64) NULL,
  `uploaded_by` INT NULL,
  `uploaded_at` DATETIME NULL,
  `status` ENUM('draft','active','archived') NOT NULL DEFAULT 'draft',
  PRIMARY KEY (`id`),
  KEY `idx_btv_template` (`template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `budget_template_sections` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `version_id` INT UNSIGNED NOT NULL,
  `section_key` VARCHAR(80) NOT NULL,
  `section_label` VARCHAR(180) NOT NULL,
  `section_type` ENUM('income','expense','summary') NOT NULL DEFAULT 'expense',
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_total_row` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_bts_version` (`version_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `budget_template_lines` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `version_id` INT UNSIGNED NOT NULL,
  `section_id` INT UNSIGNED NULL,
  `line_key` VARCHAR(80) NOT NULL,
  `original_label` VARCHAR(255) NOT NULL,
  `normalized_label` VARCHAR(255) NOT NULL,
  `account_code` VARCHAR(40) NULL,
  `is_editable` TINYINT(1) NOT NULL DEFAULT 1,
  `is_total_row` TINYINT(1) NOT NULL DEFAULT 0,
  `sort_order` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_btl_version` (`version_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `budget_template_assignments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `template_id` INT UNSIGNED NOT NULL,
  `branch_id` INT UNSIGNED NULL,
  `academic_year_id` INT UNSIGNED NULL,
  `budget_period_id` INT UNSIGNED NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `budgets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `organization_id` INT UNSIGNED NOT NULL,
  `branch_id` INT UNSIGNED NOT NULL,
  `budget_period_id` INT UNSIGNED NOT NULL,
  `template_version_id` INT UNSIGNED NULL,
  `academic_year_id` INT UNSIGNED NULL,
  `title` VARCHAR(180) NOT NULL,
  `currency` VARCHAR(10) NOT NULL DEFAULT 'RWF',
  `status` ENUM('DRAFT','SUBMITTED','PROCUREMENT_REVIEW','BUDGET_MANAGER_REVIEW','DEPUTY_DIRECTOR_REVIEW','APPROVED','RETURNED','REJECTED','CANCELLED','SUPERSEDED') NOT NULL DEFAULT 'DRAFT',
  `version_no` INT NOT NULL DEFAULT 1,
  `prepared_by` INT NULL,
  `prepared_at` DATETIME NULL,
  `notes` TEXT NULL,
  `total_income` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `total_expenses` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `surplus_deficit` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `created_by` INT NULL,
  `updated_by` INT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_budget_branch_status` (`branch_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `budget_lines` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `budget_id` INT UNSIGNED NOT NULL,
  `template_line_id` INT UNSIGNED NULL,
  `section_label` VARCHAR(180) NULL,
  `category` VARCHAR(180) NOT NULL,
  `subcategory` VARCHAR(180) NULL,
  `account_code` VARCHAR(40) NULL,
  `description` TEXT NULL,
  `assumptions` TEXT NULL,
  `quantity` DECIMAL(18,4) NULL,
  `unit` VARCHAR(40) NULL,
  `unit_cost` DECIMAL(18,2) NULL,
  `frequency` DECIMAL(10,4) NULL DEFAULT 1,
  `calculation_mode` ENUM('qty_unit_freq','term_sum','manual') NOT NULL DEFAULT 'manual',
  `term_1_amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `term_2_amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `term_3_amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `annual_amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `user_amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `ai_suggested_amount` DECIMAL(18,2) NULL,
  `ai_accepted` TINYINT(1) NULL,
  `is_total_row` TINYINT(1) NOT NULL DEFAULT 0,
  `is_editable` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_bl_budget` (`budget_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `budget_approval_actions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `budget_id` INT UNSIGNED NOT NULL,
  `actor_id` INT NOT NULL,
  `actor_post_id` INT NULL,
  `action` VARCHAR(40) NOT NULL,
  `previous_status` VARCHAR(40) NULL,
  `new_status` VARCHAR(40) NOT NULL,
  `comment` TEXT NULL,
  `ip_address` VARCHAR(45) NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_baa_budget` (`budget_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `budget_documents` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `budget_id` INT UNSIGNED NOT NULL,
  `doc_type` VARCHAR(60) NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `stored_path` VARCHAR(500) NOT NULL,
  `uploaded_by` INT NULL,
  `created_at` DATETIME NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `budget_adjustments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `organization_id` INT UNSIGNED NOT NULL,
  `branch_id` INT UNSIGNED NOT NULL,
  `budget_id` INT UNSIGNED NOT NULL,
  `adjustment_type` ENUM('supplementary','reduction','correction') NOT NULL,
  `reason` TEXT NOT NULL,
  `status` ENUM('DRAFT','SUBMITTED','APPROVED','REJECTED') NOT NULL DEFAULT 'DRAFT',
  `total_amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `created_by` INT NULL,
  `created_at` DATETIME NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `budget_transfers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `organization_id` INT UNSIGNED NOT NULL,
  `branch_id` INT UNSIGNED NOT NULL,
  `budget_id` INT UNSIGNED NOT NULL,
  `source_line_id` INT UNSIGNED NOT NULL,
  `dest_line_id` INT UNSIGNED NOT NULL,
  `amount` DECIMAL(18,2) NOT NULL,
  `reason` TEXT NOT NULL,
  `status` ENUM('DRAFT','SUBMITTED','APPROVED','REJECTED') NOT NULL DEFAULT 'DRAFT',
  `effective_date` DATE NULL,
  `created_by` INT NULL,
  `created_at` DATETIME NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cash_request_sequences` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `branch_id` INT UNSIGNED NOT NULL,
  `year` INT NOT NULL,
  `last_sequence` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_crs_branch_year` (`branch_id`, `year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cash_requests` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `organization_id` INT UNSIGNED NOT NULL,
  `branch_id` INT UNSIGNED NOT NULL,
  `request_no` VARCHAR(40) NOT NULL,
  `budget_id` INT UNSIGNED NULL,
  `budget_period_id` INT UNSIGNED NULL,
  `request_date` DATE NOT NULL,
  `required_payment_date` DATE NULL,
  `payee_name` VARCHAR(180) NOT NULL,
  `payee_type` VARCHAR(60) NULL,
  `purpose` TEXT NOT NULL,
  `currency` VARCHAR(10) NOT NULL DEFAULT 'RWF',
  `requested_amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `authorized_amount` DECIMAL(18,2) NULL,
  `paid_amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `payment_method` VARCHAR(60) NULL,
  `urgency` ENUM('normal','urgent') NOT NULL DEFAULT 'normal',
  `status` ENUM('DRAFT','SUBMITTED','HEADTEACHER_APPROVED','PROCUREMENT_APPROVED','BUDGET_APPROVED','FINANCE_AUTHORIZED','RETURNED_TO_ACCOUNTANT','REJECTED','PARTIALLY_PAID','PAID','RECEIPT_CONFIRMED','CLOSED','CANCELLED','VOIDED') NOT NULL DEFAULT 'DRAFT',
  `headteacher_approval_mode` ENUM('system','evidence') NULL,
  `headteacher_approved_at` DATETIME NULL,
  `headteacher_approved_by` INT NULL,
  `internal_notes` TEXT NULL,
  `exception_override` TINYINT(1) NOT NULL DEFAULT 0,
  `exception_reason` TEXT NULL,
  `created_by` INT NOT NULL,
  `updated_by` INT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cr_request_no` (`request_no`),
  KEY `idx_cr_branch_status` (`branch_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cash_request_lines` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cash_request_id` INT UNSIGNED NOT NULL,
  `budget_line_id` INT UNSIGNED NULL,
  `description` TEXT NOT NULL,
  `amount` DECIMAL(18,2) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_crl_request` (`cash_request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cash_request_documents` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cash_request_id` INT UNSIGNED NOT NULL,
  `doc_type` VARCHAR(60) NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `stored_path` VARCHAR(500) NOT NULL,
  `uploaded_by` INT NULL,
  `created_at` DATETIME NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cash_request_actions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cash_request_id` INT UNSIGNED NOT NULL,
  `actor_id` INT NOT NULL,
  `actor_post_id` INT NULL,
  `action` VARCHAR(40) NOT NULL,
  `previous_status` VARCHAR(40) NULL,
  `new_status` VARCHAR(40) NOT NULL,
  `comment` TEXT NULL,
  `ip_address` VARCHAR(45) NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cra_request` (`cash_request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `budget_commitments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `organization_id` INT UNSIGNED NOT NULL,
  `branch_id` INT UNSIGNED NOT NULL,
  `budget_line_id` INT UNSIGNED NOT NULL,
  `cash_request_id` INT UNSIGNED NOT NULL,
  `cash_request_line_id` INT UNSIGNED NULL,
  `amount` DECIMAL(18,2) NOT NULL,
  `status` ENUM('open','released','paid') NOT NULL DEFAULT 'open',
  `created_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_bc_line_status` (`budget_line_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cash_request_payments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cash_request_id` INT UNSIGNED NOT NULL,
  `payment_date` DATE NOT NULL,
  `amount` DECIMAL(18,2) NOT NULL,
  `payment_method` VARCHAR(60) NOT NULL,
  `payment_reference` VARCHAR(120) NOT NULL,
  `status` ENUM('completed','reversed','voided') NOT NULL DEFAULT 'completed',
  `reversal_reason` TEXT NULL,
  `reversed_payment_id` INT UNSIGNED NULL,
  `processed_by` INT NULL,
  `created_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payment_ref` (`payment_reference`),
  KEY `idx_crp_request` (`cash_request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cash_request_payment_documents` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_id` INT UNSIGNED NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `stored_path` VARCHAR(500) NOT NULL,
  `uploaded_by` INT NULL,
  `created_at` DATETIME NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `receipt_confirmations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cash_request_id` INT UNSIGNED NOT NULL,
  `confirmed_by` INT NOT NULL,
  `confirmed_at` DATETIME NOT NULL,
  `filing_reference` VARCHAR(120) NULL,
  `notes` TEXT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rc_request` (`cash_request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `financial_audit_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `organization_id` INT UNSIGNED NULL,
  `branch_id` INT UNSIGNED NULL,
  `entity_type` VARCHAR(60) NOT NULL,
  `entity_id` INT UNSIGNED NOT NULL,
  `action` VARCHAR(80) NOT NULL,
  `actor_id` INT NULL,
  `before_json` TEXT NULL,
  `after_json` TEXT NULL,
  `ip_address` VARCHAR(45) NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_fal_entity` (`entity_type`, `entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `budget_notifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_id` INT UNSIGNED NOT NULL,
  `branch_id` INT UNSIGNED NULL,
  `title` VARCHAR(180) NOT NULL,
  `body` TEXT NULL,
  `link_url` VARCHAR(500) NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_bn_staff_read` (`staff_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ai_budget_suggestions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `budget_id` INT UNSIGNED NOT NULL,
  `budget_line_id` INT UNSIGNED NULL,
  `suggestion_type` VARCHAR(60) NOT NULL,
  `suggested_value` DECIMAL(18,2) NULL,
  `reason` TEXT NULL,
  `confidence` DECIMAL(5,2) NULL,
  `status` ENUM('pending','accepted','modified','ignored') NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
