<?php

namespace App\Models;

/**
 * Asset Management Phases 2–5 schema bootstrap (imports, circulation, ops, finance).
 * PHP 7.4 — KEY indexes only, no hard FK constraints.
 */
class AssetOpsSchema
{
	/** @var bool */
	private static $ready = false;

	public static function ensureAll()
	{
		if (self::$ready) {
			return;
		}

		$db = \Config\Database::connect();

		$db->query("CREATE TABLE IF NOT EXISTS `asset_imports` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`school_id` INT UNSIGNED NOT NULL,
			`filename` VARCHAR(255) NOT NULL,
			`mode` VARCHAR(40) NOT NULL DEFAULT 'create_only' COMMENT 'create_only|create_update|validate_only',
			`status` VARCHAR(40) NOT NULL DEFAULT 'draft' COMMENT 'draft|preview|committed|failed',
			`total_rows` INT NOT NULL DEFAULT 0,
			`valid_rows` INT NOT NULL DEFAULT 0,
			`warning_rows` INT NOT NULL DEFAULT 0,
			`error_rows` INT NOT NULL DEFAULT 0,
			`created_by` INT NULL DEFAULT NULL,
			`summary_json` LONGTEXT NULL,
			`created_at` DATETIME NULL DEFAULT NULL,
			`updated_at` DATETIME NULL DEFAULT NULL,
			PRIMARY KEY (`id`),
			KEY `idx_asset_imports_school` (`school_id`, `status`),
			KEY `idx_asset_imports_created` (`school_id`, `created_at`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

		$db->query("CREATE TABLE IF NOT EXISTS `asset_import_rows` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`import_id` INT UNSIGNED NOT NULL,
			`school_id` INT UNSIGNED NOT NULL,
			`row_number` INT NOT NULL DEFAULT 0,
			`status` VARCHAR(40) NOT NULL DEFAULT 'valid' COMMENT 'valid|warning|error|imported',
			`asset_code` VARCHAR(60) NULL DEFAULT NULL,
			`payload_json` LONGTEXT NULL,
			`errors_json` LONGTEXT NULL,
			`created_at` DATETIME NULL DEFAULT NULL,
			PRIMARY KEY (`id`),
			KEY `idx_air_import` (`import_id`),
			KEY `idx_air_school` (`school_id`, `import_id`),
			KEY `idx_air_status` (`import_id`, `status`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

		$db->query("CREATE TABLE IF NOT EXISTS `asset_assignments` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`school_id` INT UNSIGNED NOT NULL,
			`asset_id` INT UNSIGNED NOT NULL,
			`staff_id` INT UNSIGNED NOT NULL,
			`role` VARCHAR(40) NOT NULL DEFAULT 'custodian' COMMENT 'custodian|owner|user|approver|auditor|maintenance',
			`assigned_at` DATETIME NULL DEFAULT NULL,
			`acknowledged_at` DATETIME NULL DEFAULT NULL,
			`ended_at` DATETIME NULL DEFAULT NULL,
			`status` VARCHAR(40) NOT NULL DEFAULT 'active' COMMENT 'active|ended',
			`notes` TEXT NULL,
			`created_by` INT NULL DEFAULT NULL,
			`created_at` DATETIME NULL DEFAULT NULL,
			`updated_at` DATETIME NULL DEFAULT NULL,
			PRIMARY KEY (`id`),
			KEY `idx_aa_school_asset` (`school_id`, `asset_id`),
			KEY `idx_aa_staff` (`school_id`, `staff_id`),
			KEY `idx_aa_status` (`school_id`, `status`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

		$db->query("CREATE TABLE IF NOT EXISTS `asset_loans` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`school_id` INT UNSIGNED NOT NULL,
			`asset_id` INT UNSIGNED NOT NULL,
			`borrower_type` VARCHAR(20) NOT NULL DEFAULT 'student' COMMENT 'student|staff',
			`borrower_id` INT UNSIGNED NOT NULL,
			`issued_by` INT NULL DEFAULT NULL,
			`issue_at` DATETIME NULL DEFAULT NULL,
			`due_at` DATETIME NULL DEFAULT NULL,
			`return_at` DATETIME NULL DEFAULT NULL,
			`issue_condition` VARCHAR(80) NULL DEFAULT NULL,
			`return_condition` VARCHAR(80) NULL DEFAULT NULL,
			`source_location_id` INT UNSIGNED NULL DEFAULT NULL,
			`intended_use` VARCHAR(255) NULL DEFAULT NULL,
			`notes` TEXT NULL,
			`penalty_amount` DECIMAL(18,2) NULL DEFAULT 0.00,
			`status` VARCHAR(40) NOT NULL DEFAULT 'open' COMMENT 'open|returned|overdue|lost|damaged',
			`created_at` DATETIME NULL DEFAULT NULL,
			`updated_at` DATETIME NULL DEFAULT NULL,
			PRIMARY KEY (`id`),
			KEY `idx_aloan_school_asset` (`school_id`, `asset_id`),
			KEY `idx_aloan_borrower` (`school_id`, `borrower_type`, `borrower_id`),
			KEY `idx_aloan_status` (`school_id`, `status`),
			KEY `idx_aloan_due` (`school_id`, `due_at`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

		$db->query("CREATE TABLE IF NOT EXISTS `asset_transfers` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`school_id` INT UNSIGNED NOT NULL,
			`transfer_no` VARCHAR(60) NOT NULL,
			`status` VARCHAR(40) NOT NULL DEFAULT 'draft' COMMENT 'draft|pending_approval|approved|dispatched|completed|rejected|cancelled',
			`transfer_type` VARCHAR(40) NOT NULL DEFAULT 'location' COMMENT 'location|staff|department|campus',
			`is_temporary` TINYINT(1) NOT NULL DEFAULT 0,
			`from_location_id` INT UNSIGNED NULL DEFAULT NULL,
			`to_location_id` INT UNSIGNED NULL DEFAULT NULL,
			`from_custodian_id` INT UNSIGNED NULL DEFAULT NULL,
			`to_custodian_id` INT UNSIGNED NULL DEFAULT NULL,
			`notes` TEXT NULL,
			`created_by` INT NULL DEFAULT NULL,
			`approved_by` INT NULL DEFAULT NULL,
			`received_by` INT NULL DEFAULT NULL,
			`created_at` DATETIME NULL DEFAULT NULL,
			`updated_at` DATETIME NULL DEFAULT NULL,
			`completed_at` DATETIME NULL DEFAULT NULL,
			PRIMARY KEY (`id`),
			UNIQUE KEY `uq_atransfer_school_no` (`school_id`, `transfer_no`),
			KEY `idx_atransfer_school_status` (`school_id`, `status`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

		$db->query("CREATE TABLE IF NOT EXISTS `asset_transfer_items` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`transfer_id` INT UNSIGNED NOT NULL,
			`school_id` INT UNSIGNED NOT NULL,
			`asset_id` INT UNSIGNED NOT NULL,
			`status` VARCHAR(40) NOT NULL DEFAULT 'pending' COMMENT 'pending|accepted|rejected|missing|damaged',
			`notes` TEXT NULL,
			`created_at` DATETIME NULL DEFAULT NULL,
			PRIMARY KEY (`id`),
			KEY `idx_ati_transfer` (`transfer_id`),
			KEY `idx_ati_school_asset` (`school_id`, `asset_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

		$db->query("CREATE TABLE IF NOT EXISTS `asset_maintenance` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`school_id` INT UNSIGNED NOT NULL,
			`work_order_no` VARCHAR(60) NOT NULL,
			`asset_id` INT UNSIGNED NOT NULL,
			`maintenance_type` VARCHAR(40) NOT NULL DEFAULT 'corrective' COMMENT 'preventive|corrective|calibration',
			`problem` TEXT NULL,
			`priority` VARCHAR(20) NOT NULL DEFAULT 'normal' COMMENT 'low|normal|high|critical',
			`requested_by` INT NULL DEFAULT NULL,
			`assigned_to` INT NULL DEFAULT NULL,
			`provider_type` VARCHAR(20) NULL DEFAULT 'internal' COMMENT 'internal|external',
			`scheduled_date` DATE NULL DEFAULT NULL,
			`start_date` DATE NULL DEFAULT NULL,
			`completion_date` DATE NULL DEFAULT NULL,
			`labour_cost` DECIMAL(18,2) NULL DEFAULT 0.00,
			`parts_cost` DECIMAL(18,2) NULL DEFAULT 0.00,
			`other_cost` DECIMAL(18,2) NULL DEFAULT 0.00,
			`total_cost` DECIMAL(18,2) NULL DEFAULT 0.00,
			`work_performed` TEXT NULL,
			`result` TEXT NULL,
			`next_maintenance_date` DATE NULL DEFAULT NULL,
			`status` VARCHAR(40) NOT NULL DEFAULT 'requested' COMMENT 'requested|approved|scheduled|in_progress|waiting_parts|completed|verified|cancelled',
			`created_at` DATETIME NULL DEFAULT NULL,
			`updated_at` DATETIME NULL DEFAULT NULL,
			PRIMARY KEY (`id`),
			UNIQUE KEY `uq_amaint_school_wo` (`school_id`, `work_order_no`),
			KEY `idx_amaint_school_asset` (`school_id`, `asset_id`),
			KEY `idx_amaint_status` (`school_id`, `status`),
			KEY `idx_amaint_scheduled` (`school_id`, `scheduled_date`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

		$db->query("CREATE TABLE IF NOT EXISTS `asset_inspections` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`school_id` INT UNSIGNED NOT NULL,
			`asset_id` INT UNSIGNED NOT NULL,
			`inspected_by` INT NULL DEFAULT NULL,
			`inspection_date` DATE NULL DEFAULT NULL,
			`result` VARCHAR(40) NOT NULL DEFAULT 'pass' COMMENT 'pass|fail|conditional',
			`condition_code` VARCHAR(40) NULL DEFAULT NULL,
			`notes` TEXT NULL,
			`next_inspection_date` DATE NULL DEFAULT NULL,
			`created_at` DATETIME NULL DEFAULT NULL,
			PRIMARY KEY (`id`),
			KEY `idx_ainsp_school_asset` (`school_id`, `asset_id`),
			KEY `idx_ainsp_date` (`school_id`, `inspection_date`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

		$db->query("CREATE TABLE IF NOT EXISTS `asset_incidents` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`school_id` INT UNSIGNED NOT NULL,
			`incident_no` VARCHAR(60) NOT NULL,
			`asset_id` INT UNSIGNED NOT NULL,
			`reported_by` INT NULL DEFAULT NULL,
			`incident_at` DATETIME NULL DEFAULT NULL,
			`location_id` INT UNSIGNED NULL DEFAULT NULL,
			`incident_type` VARCHAR(40) NOT NULL DEFAULT 'damage' COMMENT 'damage|loss|theft|misuse|accident|safety|data_security|insurance',
			`description` TEXT NULL,
			`people_involved` TEXT NULL,
			`immediate_action` TEXT NULL,
			`estimated_loss` DECIMAL(18,2) NULL DEFAULT 0.00,
			`police_ref` VARCHAR(120) NULL DEFAULT NULL,
			`insurance_ref` VARCHAR(120) NULL DEFAULT NULL,
			`findings` TEXT NULL,
			`decision` TEXT NULL,
			`financial_recovery` DECIMAL(18,2) NULL DEFAULT 0.00,
			`status` VARCHAR(40) NOT NULL DEFAULT 'open' COMMENT 'open|investigating|resolved|closed',
			`created_at` DATETIME NULL DEFAULT NULL,
			`updated_at` DATETIME NULL DEFAULT NULL,
			PRIMARY KEY (`id`),
			UNIQUE KEY `uq_ainc_school_no` (`school_id`, `incident_no`),
			KEY `idx_ainc_school_asset` (`school_id`, `asset_id`),
			KEY `idx_ainc_status` (`school_id`, `status`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

		$db->query("CREATE TABLE IF NOT EXISTS `asset_audits` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`school_id` INT UNSIGNED NOT NULL,
			`audit_no` VARCHAR(60) NOT NULL,
			`title` VARCHAR(200) NOT NULL,
			`status` VARCHAR(40) NOT NULL DEFAULT 'draft' COMMENT 'draft|in_progress|review|closed',
			`location_id` INT UNSIGNED NULL DEFAULT NULL,
			`category_id` INT UNSIGNED NULL DEFAULT NULL,
			`custodian_id` INT UNSIGNED NULL DEFAULT NULL,
			`snapshot_json` LONGTEXT NULL,
			`created_by` INT NULL DEFAULT NULL,
			`closed_by` INT NULL DEFAULT NULL,
			`created_at` DATETIME NULL DEFAULT NULL,
			`updated_at` DATETIME NULL DEFAULT NULL,
			`closed_at` DATETIME NULL DEFAULT NULL,
			PRIMARY KEY (`id`),
			UNIQUE KEY `uq_aaudit_school_no` (`school_id`, `audit_no`),
			KEY `idx_aaudit_school_status` (`school_id`, `status`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

		$db->query("CREATE TABLE IF NOT EXISTS `asset_audit_items` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`audit_id` INT UNSIGNED NOT NULL,
			`school_id` INT UNSIGNED NOT NULL,
			`asset_id` INT UNSIGNED NULL DEFAULT NULL,
			`expected_location_id` INT UNSIGNED NULL DEFAULT NULL,
			`found_location_id` INT UNSIGNED NULL DEFAULT NULL,
			`result` VARCHAR(40) NOT NULL DEFAULT 'pending' COMMENT 'found_ok|wrong_location|wrong_custodian|damaged|not_found|unexpected|duplicate_tag|unregistered|pending|reconciled',
			`condition_code` VARCHAR(40) NULL DEFAULT NULL,
			`notes` TEXT NULL,
			`scanned_code` VARCHAR(120) NULL DEFAULT NULL,
			`created_at` DATETIME NULL DEFAULT NULL,
			PRIMARY KEY (`id`),
			KEY `idx_aai_audit` (`audit_id`),
			KEY `idx_aai_school` (`school_id`, `audit_id`),
			KEY `idx_aai_result` (`audit_id`, `result`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

		$db->query("CREATE TABLE IF NOT EXISTS `asset_disposals` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`school_id` INT UNSIGNED NOT NULL,
			`asset_id` INT UNSIGNED NOT NULL,
			`disposal_no` VARCHAR(60) NOT NULL,
			`method` VARCHAR(40) NOT NULL DEFAULT 'write_off' COMMENT 'sale|donation|recycle|write_off',
			`status` VARCHAR(40) NOT NULL DEFAULT 'requested' COMMENT 'requested|approved|completed|rejected',
			`reason` TEXT NULL,
			`proceeds` DECIMAL(18,2) NULL DEFAULT 0.00,
			`requested_by` INT NULL DEFAULT NULL,
			`approved_by` INT NULL DEFAULT NULL,
			`completed_at` DATETIME NULL DEFAULT NULL,
			`created_at` DATETIME NULL DEFAULT NULL,
			`updated_at` DATETIME NULL DEFAULT NULL,
			PRIMARY KEY (`id`),
			UNIQUE KEY `uq_adisp_school_no` (`school_id`, `disposal_no`),
			KEY `idx_adisp_school_asset` (`school_id`, `asset_id`),
			KEY `idx_adisp_status` (`school_id`, `status`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

		$db->query("CREATE TABLE IF NOT EXISTS `asset_depreciation_entries` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`school_id` INT UNSIGNED NOT NULL,
			`asset_id` INT UNSIGNED NOT NULL,
			`period_ym` CHAR(7) NOT NULL,
			`amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
			`accumulated` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
			`net_book_value` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
			`method` VARCHAR(40) NULL DEFAULT NULL,
			`notes` TEXT NULL,
			`created_by` INT NULL DEFAULT NULL,
			`created_at` DATETIME NULL DEFAULT NULL,
			PRIMARY KEY (`id`),
			UNIQUE KEY `uq_ade_asset_period` (`school_id`, `asset_id`, `period_ym`),
			KEY `idx_ade_school_period` (`school_id`, `period_ym`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

		$db->query("CREATE TABLE IF NOT EXISTS `asset_location_history` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`school_id` INT UNSIGNED NOT NULL,
			`asset_id` INT UNSIGNED NOT NULL,
			`from_location_id` INT UNSIGNED NULL DEFAULT NULL,
			`to_location_id` INT UNSIGNED NULL DEFAULT NULL,
			`moved_by` INT NULL DEFAULT NULL,
			`notes` TEXT NULL,
			`created_at` DATETIME NULL DEFAULT NULL,
			PRIMARY KEY (`id`),
			KEY `idx_alh_school_asset` (`school_id`, `asset_id`),
			KEY `idx_alh_created` (`school_id`, `created_at`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

		self::$ready = true;
	}
}
