<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Schema bootstrap + shared helpers for Asset Management (PHP 7.4).
 */
class AssetSchemaModel extends Model
{
	protected $table = 'asset_settings';
	protected $primaryKey = 'id';
	protected $returnType = 'array';
	protected $allowedFields = [
		'school_id', 'code_pattern', 'seq_padding', 'default_currency',
	];
	protected $useTimestamps = true;

	/** @var bool */
	private static $schemaReady = false;

	public function ensureSchema()
	{
		if (self::$schemaReady) {
			return;
		}

		$db = \Config\Database::connect();

		$db->query("CREATE TABLE IF NOT EXISTS `asset_locations` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`school_id` INT UNSIGNED NOT NULL,
			`parent_location_id` INT UNSIGNED NULL DEFAULT NULL,
			`location_code` VARCHAR(40) NOT NULL,
			`name` VARCHAR(180) NOT NULL,
			`location_type` VARCHAR(60) NULL DEFAULT NULL,
			`description` TEXT NULL,
			`campus` VARCHAR(120) NULL DEFAULT NULL,
			`building` VARCHAR(120) NULL DEFAULT NULL,
			`floor` VARCHAR(60) NULL DEFAULT NULL,
			`room` VARCHAR(60) NULL DEFAULT NULL,
			`capacity` INT NULL DEFAULT NULL,
			`status` TINYINT(1) NOT NULL DEFAULT 1,
			`responsible_staff_id` INT UNSIGNED NULL DEFAULT NULL,
			`created_by` INT NULL DEFAULT NULL,
			`updated_by` INT NULL DEFAULT NULL,
			`created_at` DATETIME NULL DEFAULT NULL,
			`updated_at` DATETIME NULL DEFAULT NULL,
			`archived_at` DATETIME NULL DEFAULT NULL,
			PRIMARY KEY (`id`),
			UNIQUE KEY `uq_asset_loc_school_code` (`school_id`, `location_code`),
			KEY `idx_asset_loc_parent` (`school_id`, `parent_location_id`),
			KEY `idx_asset_loc_status` (`school_id`, `status`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

		$db->query("CREATE TABLE IF NOT EXISTS `asset_categories` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`school_id` INT UNSIGNED NOT NULL,
			`parent_category_id` INT UNSIGNED NULL DEFAULT NULL,
			`category_code` VARCHAR(40) NOT NULL,
			`name` VARCHAR(180) NOT NULL,
			`description` TEXT NULL,
			`asset_class` VARCHAR(40) NULL DEFAULT 'tangible',
			`tracking_mode` VARCHAR(40) NULL DEFAULT 'individual',
			`is_fixed_asset` TINYINT(1) NOT NULL DEFAULT 1,
			`is_consumable` TINYINT(1) NOT NULL DEFAULT 0,
			`default_useful_life` INT NULL DEFAULT NULL,
			`default_depreciation_method` VARCHAR(40) NULL DEFAULT 'straight_line',
			`default_residual_percent` DECIMAL(8,2) NULL DEFAULT 0.00,
			`inspection_frequency_days` INT NULL DEFAULT NULL,
			`maintenance_frequency_days` INT NULL DEFAULT NULL,
			`requires_serial_number` TINYINT(1) NOT NULL DEFAULT 0,
			`requires_rfid` TINYINT(1) NOT NULL DEFAULT 0,
			`requires_barcode` TINYINT(1) NOT NULL DEFAULT 0,
			`requires_warranty` TINYINT(1) NOT NULL DEFAULT 0,
			`status` TINYINT(1) NOT NULL DEFAULT 1,
			`created_by` INT NULL DEFAULT NULL,
			`updated_by` INT NULL DEFAULT NULL,
			`created_at` DATETIME NULL DEFAULT NULL,
			`updated_at` DATETIME NULL DEFAULT NULL,
			`archived_at` DATETIME NULL DEFAULT NULL,
			PRIMARY KEY (`id`),
			UNIQUE KEY `uq_asset_cat_school_code` (`school_id`, `category_code`),
			KEY `idx_asset_cat_parent` (`school_id`, `parent_category_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

		$db->query("CREATE TABLE IF NOT EXISTS `asset_category_fields` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`school_id` INT UNSIGNED NOT NULL,
			`category_id` INT UNSIGNED NOT NULL,
			`field_key` VARCHAR(80) NOT NULL,
			`field_label` VARCHAR(120) NOT NULL,
			`data_type` VARCHAR(40) NOT NULL DEFAULT 'text',
			`options_json` TEXT NULL,
			`is_required` TINYINT(1) NOT NULL DEFAULT 0,
			`sort_order` INT NOT NULL DEFAULT 0,
			`status` TINYINT(1) NOT NULL DEFAULT 1,
			`created_at` DATETIME NULL DEFAULT NULL,
			`updated_at` DATETIME NULL DEFAULT NULL,
			PRIMARY KEY (`id`),
			UNIQUE KEY `uq_asset_cat_field` (`category_id`, `field_key`),
			KEY `idx_acf_school_cat` (`school_id`, `category_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

		$db->query("CREATE TABLE IF NOT EXISTS `assets` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`school_id` INT UNSIGNED NOT NULL,
			`asset_code` VARCHAR(60) NOT NULL,
			`name` VARCHAR(200) NOT NULL,
			`description` TEXT NULL,
			`category_id` INT UNSIGNED NULL DEFAULT NULL,
			`location_id` INT UNSIGNED NULL DEFAULT NULL,
			`brand` VARCHAR(120) NULL DEFAULT NULL,
			`model` VARCHAR(120) NULL DEFAULT NULL,
			`manufacturer` VARCHAR(120) NULL DEFAULT NULL,
			`serial_number` VARCHAR(120) NULL DEFAULT NULL,
			`barcode` VARCHAR(120) NULL DEFAULT NULL,
			`rfid_tag` VARCHAR(120) NULL DEFAULT NULL,
			`part_number` VARCHAR(120) NULL DEFAULT NULL,
			`external_ref` VARCHAR(120) NULL DEFAULT NULL,
			`department` VARCHAR(120) NULL DEFAULT NULL,
			`cost_centre` VARCHAR(120) NULL DEFAULT NULL,
			`custodian_staff_id` INT UNSIGNED NULL DEFAULT NULL,
			`responsible_staff_id` INT UNSIGNED NULL DEFAULT NULL,
			`ownership_type` VARCHAR(60) NULL DEFAULT 'owned',
			`funding_source` VARCHAR(120) NULL DEFAULT NULL,
			`supplier` VARCHAR(180) NULL DEFAULT NULL,
			`purchase_date` DATE NULL DEFAULT NULL,
			`receipt_date` DATE NULL DEFAULT NULL,
			`commissioning_date` DATE NULL DEFAULT NULL,
			`purchase_price` DECIMAL(18,2) NULL DEFAULT 0.00,
			`currency` VARCHAR(10) NULL DEFAULT 'RWF',
			`additional_cost` DECIMAL(18,2) NULL DEFAULT 0.00,
			`total_acquisition_cost` DECIMAL(18,2) NULL DEFAULT 0.00,
			`po_number` VARCHAR(80) NULL DEFAULT NULL,
			`invoice_number` VARCHAR(80) NULL DEFAULT NULL,
			`useful_life_months` INT NULL DEFAULT NULL,
			`residual_value` DECIMAL(18,2) NULL DEFAULT 0.00,
			`depreciation_method` VARCHAR(40) NULL DEFAULT 'straight_line',
			`depreciation_start_date` DATE NULL DEFAULT NULL,
			`accumulated_depreciation` DECIMAL(18,2) NULL DEFAULT 0.00,
			`net_book_value` DECIMAL(18,2) NULL DEFAULT 0.00,
			`replacement_value` DECIMAL(18,2) NULL DEFAULT 0.00,
			`condition_code` VARCHAR(40) NULL DEFAULT 'good',
			`lifecycle_status` VARCHAR(40) NOT NULL DEFAULT 'draft',
			`criticality` VARCHAR(40) NULL DEFAULT 'normal',
			`warranty_start` DATE NULL DEFAULT NULL,
			`warranty_expiry` DATE NULL DEFAULT NULL,
			`insurance_policy` VARCHAR(120) NULL DEFAULT NULL,
			`insurance_expiry` DATE NULL DEFAULT NULL,
			`last_inspection_date` DATE NULL DEFAULT NULL,
			`next_inspection_date` DATE NULL DEFAULT NULL,
			`last_maintenance_date` DATE NULL DEFAULT NULL,
			`next_maintenance_date` DATE NULL DEFAULT NULL,
			`quantity` DECIMAL(18,2) NOT NULL DEFAULT 1.00,
			`tracking_mode` VARCHAR(40) NOT NULL DEFAULT 'individual',
			`photo_path` VARCHAR(255) NULL DEFAULT NULL,
			`notes` TEXT NULL,
			`custom_fields_json` LONGTEXT NULL,
			`version` INT NOT NULL DEFAULT 1,
			`approved_by` INT NULL DEFAULT NULL,
			`created_by` INT NULL DEFAULT NULL,
			`updated_by` INT NULL DEFAULT NULL,
			`created_at` DATETIME NULL DEFAULT NULL,
			`updated_at` DATETIME NULL DEFAULT NULL,
			`archived_at` DATETIME NULL DEFAULT NULL,
			PRIMARY KEY (`id`),
			UNIQUE KEY `uq_assets_school_code` (`school_id`, `asset_code`),
			KEY `idx_assets_school_status` (`school_id`, `lifecycle_status`),
			KEY `idx_assets_location` (`school_id`, `location_id`),
			KEY `idx_assets_category` (`school_id`, `category_id`),
			KEY `idx_assets_custodian` (`school_id`, `custodian_staff_id`),
			KEY `idx_assets_serial` (`school_id`, `serial_number`),
			KEY `idx_assets_barcode` (`school_id`, `barcode`),
			KEY `idx_assets_rfid` (`school_id`, `rfid_tag`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

		$db->query("CREATE TABLE IF NOT EXISTS `asset_status_history` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`school_id` INT UNSIGNED NOT NULL,
			`asset_id` INT UNSIGNED NOT NULL,
			`previous_status` VARCHAR(40) NULL DEFAULT NULL,
			`new_status` VARCHAR(40) NOT NULL,
			`operation_type` VARCHAR(60) NOT NULL DEFAULT 'status_change',
			`actor_id` INT NULL DEFAULT NULL,
			`source_location_id` INT UNSIGNED NULL DEFAULT NULL,
			`destination_location_id` INT UNSIGNED NULL DEFAULT NULL,
			`previous_custodian_id` INT UNSIGNED NULL DEFAULT NULL,
			`new_custodian_id` INT UNSIGNED NULL DEFAULT NULL,
			`notes` TEXT NULL,
			`attachment_path` VARCHAR(255) NULL DEFAULT NULL,
			`approval_ref` VARCHAR(80) NULL DEFAULT NULL,
			`created_at` DATETIME NULL DEFAULT NULL,
			PRIMARY KEY (`id`),
			KEY `idx_ash_asset` (`asset_id`),
			KEY `idx_ash_school` (`school_id`, `created_at`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

		$db->query("CREATE TABLE IF NOT EXISTS `asset_settings` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`school_id` INT UNSIGNED NOT NULL,
			`code_pattern` VARCHAR(80) NOT NULL DEFAULT 'AST-{CATEGORY}-{YEAR}-{SEQ}',
			`seq_padding` INT NOT NULL DEFAULT 6,
			`default_currency` VARCHAR(10) NOT NULL DEFAULT 'RWF',
			`created_at` DATETIME NULL DEFAULT NULL,
			`updated_at` DATETIME NULL DEFAULT NULL,
			PRIMARY KEY (`id`),
			UNIQUE KEY `uq_asset_settings_school` (`school_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

		self::$schemaReady = true;
	}

	/**
	 * Seed default categories and settings for a school once.
	 *
	 * @param int $schoolId
	 * @param int|null $actorId
	 */
	public function seedDefaults($schoolId, $actorId = null)
	{
		$schoolId = (int) $schoolId;
		if ($schoolId <= 0) {
			return;
		}

		$this->ensureSchema();
		$db = \Config\Database::connect();

		$settings = $db->table('asset_settings')->where('school_id', $schoolId)->get(1)->getRowArray();
		if (!$settings) {
			$db->table('asset_settings')->insert([
				'school_id' => $schoolId,
				'code_pattern' => 'AST-{CATEGORY}-{YEAR}-{SEQ}',
				'seq_padding' => 6,
				'default_currency' => 'RWF',
				'created_at' => date('Y-m-d H:i:s'),
				'updated_at' => date('Y-m-d H:i:s'),
			]);
		}

		$count = (int) $db->table('asset_categories')->where('school_id', $schoolId)->countAllResults();
		if ($count > 0) {
			return;
		}

		$defaults = [
			['ICT', 'Information Technology', null],
			['FURN', 'Furniture', null],
			['LAB', 'Laboratory Equipment', null],
			['DORM', 'Dormitory Equipment', null],
			['KITCH', 'Kitchen Equipment', null],
			['VEH', 'Vehicles', null],
			['SPORT', 'Sports Equipment', null],
			['BOOK', 'Books and Library Materials', null],
			['CONS', 'Consumable Supplies', null],
			['INFRA', 'Buildings and Infrastructure', null],
		];

		$now = date('Y-m-d H:i:s');
		$parentIds = [];
		foreach ($defaults as $row) {
			$db->table('asset_categories')->insert([
				'school_id' => $schoolId,
				'parent_category_id' => null,
				'category_code' => $row[0],
				'name' => $row[1],
				'description' => 'Default seeded category',
				'asset_class' => $row[0] === 'CONS' ? 'tangible' : 'tangible',
				'tracking_mode' => $row[0] === 'CONS' ? 'quantity' : 'individual',
				'is_fixed_asset' => $row[0] === 'CONS' ? 0 : 1,
				'is_consumable' => $row[0] === 'CONS' ? 1 : 0,
				'default_depreciation_method' => $row[0] === 'CONS' ? 'none' : 'straight_line',
				'status' => 1,
				'created_by' => $actorId,
				'created_at' => $now,
				'updated_at' => $now,
			]);
			$parentIds[$row[0]] = (int) $db->insertID();
		}

		$children = [
			['DESKTOP', 'Desktop Computer', 'ICT'],
			['LAPTOP', 'Laptop', 'ICT'],
			['PRINTER', 'Printer', 'ICT'],
			['ROUTER', 'Router', 'ICT'],
			['PROJ', 'Projector', 'ICT'],
			['DESK', 'Desk', 'FURN'],
			['CHAIR', 'Chair', 'FURN'],
			['CAB', 'Cabinet', 'FURN'],
		];
		foreach ($children as $c) {
			$parent = isset($parentIds[$c[2]]) ? $parentIds[$c[2]] : null;
			$db->table('asset_categories')->insert([
				'school_id' => $schoolId,
				'parent_category_id' => $parent,
				'category_code' => $c[0],
				'name' => $c[1],
				'description' => null,
				'asset_class' => 'tangible',
				'tracking_mode' => 'individual',
				'is_fixed_asset' => 1,
				'is_consumable' => 0,
				'requires_serial_number' => in_array($c[0], ['DESKTOP', 'LAPTOP', 'PRINTER', 'PROJ'], true) ? 1 : 0,
				'status' => 1,
				'created_by' => $actorId,
				'created_at' => $now,
				'updated_at' => $now,
			]);
		}

		$locDefaults = [
			['MAIN', 'Main Campus', 'campus', null],
			['ACAD', 'Academic Block', 'building', 'MAIN'],
			['BOARD', 'Boarding Area', 'building', 'MAIN'],
			['ADMIN', 'Administration Block', 'building', 'MAIN'],
			['COMPLAB', 'Computer Laboratory', 'room', 'ACAD'],
			['LIB', 'Library', 'room', 'ACAD'],
			['CLASS', 'Classroom', 'room', 'ACAD'],
			['STORE', 'Warehouse', 'store', 'MAIN'],
		];
		$locIds = [];
		foreach ($locDefaults as $l) {
			$parent = null;
			if (!empty($l[3]) && isset($locIds[$l[3]])) {
				$parent = $locIds[$l[3]];
			}
			$db->table('asset_locations')->insert([
				'school_id' => $schoolId,
				'parent_location_id' => $parent,
				'location_code' => $l[0],
				'name' => $l[1],
				'location_type' => $l[2],
				'status' => 1,
				'created_by' => $actorId,
				'created_at' => $now,
				'updated_at' => $now,
			]);
			$locIds[$l[0]] = (int) $db->insertID();
		}
	}

	/**
	 * Generate next asset code for school.
	 *
	 * @param int $schoolId
	 * @param string $categoryCode
	 * @return string
	 */
	public function nextAssetCode($schoolId, $categoryCode = 'GEN')
	{
		$this->ensureSchema();
		$db = \Config\Database::connect();
		$schoolId = (int) $schoolId;
		$categoryCode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $categoryCode));
		if ($categoryCode === '') {
			$categoryCode = 'GEN';
		}

		$settings = $db->table('asset_settings')->where('school_id', $schoolId)->get(1)->getRowArray();
		$pad = $settings ? (int) $settings['seq_padding'] : 6;
		if ($pad < 3) {
			$pad = 6;
		}
		$year = date('Y');
		$prefix = 'AST-' . $categoryCode . '-' . $year . '-';

		$row = $db->query(
			"SELECT asset_code FROM assets WHERE school_id = ? AND asset_code LIKE ? ORDER BY id DESC LIMIT 1",
			[$schoolId, $prefix . '%']
		)->getRowArray();

		$seq = 1;
		if ($row && preg_match('/(\d+)$/', $row['asset_code'], $m)) {
			$seq = ((int) $m[1]) + 1;
		}

		return $prefix . str_pad((string) $seq, $pad, '0', STR_PAD_LEFT);
	}
}
