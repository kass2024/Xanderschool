<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * School-defined locations for student IN/OUT NFC attendance (Library, Cafeteria, …).
 */
class AttendanceAreaModel extends Model
{
	protected $table = 'attendance_areas';
	protected $primaryKey = 'id';
	protected $returnType = 'array';
	protected $allowedFields = ['school_id', 'name', 'sort_order', 'active'];
	protected $useTimestamps = true;

	/** @var bool */
	private static $schemaReady = false;

	public function ensureSchema(): void
	{
		if (self::$schemaReady) {
			return;
		}

		$db = \Config\Database::connect();

		$db->query("CREATE TABLE IF NOT EXISTS `attendance_areas` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`school_id` INT UNSIGNED NOT NULL,
			`name` VARCHAR(120) NOT NULL,
			`sort_order` INT NOT NULL DEFAULT 0,
			`active` TINYINT(1) NOT NULL DEFAULT 1,
			`created_at` DATETIME NULL DEFAULT NULL,
			`updated_at` DATETIME NULL DEFAULT NULL,
			PRIMARY KEY (`id`),
			KEY `idx_aa_school` (`school_id`, `active`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

		if ($db->tableExists('attendance_records')) {
			$fields = $db->getFieldNames('attendance_records');
			if (!in_array('area_id', $fields, true)) {
				try {
					$db->query("ALTER TABLE `attendance_records` ADD COLUMN `area_id` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `school_id`");
				} catch (\Throwable $e) {
					// column may exist
				}
			}
			try {
				$idx = $db->query("SHOW INDEX FROM `attendance_records` WHERE Key_name = 'idx_att_user_area'")->getResultArray();
				if (empty($idx)) {
					$db->query("ALTER TABLE `attendance_records` ADD KEY `idx_att_user_area` (`user_id`, `school_id`, `area_id`, `time_in`)");
				}
			} catch (\Throwable $e) {
				// ignore
			}
		}

		self::$schemaReady = true;
	}

	public function listAreas(int $schoolId, bool $activeOnly = true): array
	{
		$this->ensureSchema();
		$this->ensureDefaultArea($schoolId);
		$b = $this->where('school_id', $schoolId);
		if ($activeOnly) {
			$b->where('active', 1);
		}
		return $b->orderBy('sort_order', 'ASC')->orderBy('name', 'ASC')->findAll();
	}

	public function getActiveForSchool(int $schoolId, int $areaId): ?array
	{
		$row = $this->getForSchool($schoolId, $areaId);
		if (!$row || (int) ($row['active'] ?? 0) !== 1) {
			return null;
		}
		return $row;
	}

	public function getForSchool(int $schoolId, int $areaId): ?array
	{
		$this->ensureSchema();
		if ($areaId <= 0 || $schoolId <= 0) {
			return null;
		}
		$row = $this->where('school_id', $schoolId)
			->where('id', $areaId)
			->first();
		return $row ?: null;
	}

	public function ensureDefaultArea(int $schoolId): int
	{
		$this->ensureSchema();
		if ($schoolId <= 0) {
			return 0;
		}

		$gate = null;
		foreach ($this->where('school_id', $schoolId)->findAll() as $row) {
			if (strcasecmp(trim((string) ($row['name'] ?? '')), 'School gate') === 0) {
				$gate = $row;
				break;
			}
		}

		if (!$gate) {
			$active = $this->where('school_id', $schoolId)->where('active', 1)->first();
			if ($active) {
				return (int) $active['id'];
			}
			$gateId = (int) $this->insert([
				'school_id' => $schoolId,
				'name' => 'School gate',
				'sort_order' => 0,
				'active' => 1,
			]);
			if ($gateId > 0) {
				$this->backfillLegacyRecords($schoolId, $gateId);
			}
			return $gateId;
		}

		$gateId = (int) $gate['id'];
		if ((int) ($gate['active'] ?? 0) !== 1) {
			$this->update($gateId, ['active' => 1]);
		}
		$this->backfillLegacyRecords($schoolId, $gateId);
		return $gateId;
	}

	private function backfillLegacyRecords(int $schoolId, int $areaId): void
	{
		if ($areaId <= 0) {
			return;
		}
		$db = \Config\Database::connect();
		if (!$db->tableExists('attendance_records')) {
			return;
		}
		$fields = $db->getFieldNames('attendance_records');
		if (!in_array('area_id', $fields, true)) {
			return;
		}
		$db->table('attendance_records')
			->where('school_id', $schoolId)
			->where('area_id', 0)
			->update(['area_id' => $areaId]);
	}
}
