<?php

namespace App\Libraries;

class HeyStarDeviceStore
{
	public static function ensureSchema(): void
	{
		static $ready = false;
		if ($ready) {
			return;
		}
		$db = \Config\Database::connect();
		$db->query("CREATE TABLE IF NOT EXISTS `heystar_devices` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`school_id` INT UNSIGNED NOT NULL,
			`device_key` VARCHAR(64) NOT NULL DEFAULT '',
			`device_ip` VARCHAR(64) NOT NULL DEFAULT '',
			`password` VARCHAR(120) NOT NULL DEFAULT 'HFSecurity',
			`area_id` INT UNSIGNED NOT NULL DEFAULT 0,
			`last_seen` INT UNSIGNED NOT NULL DEFAULT 0,
			`updated_at` DATETIME NULL DEFAULT NULL,
			PRIMARY KEY (`id`),
			UNIQUE KEY `uk_heystar_school` (`school_id`),
			KEY `idx_heystar_key` (`device_key`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
		$db->query("CREATE TABLE IF NOT EXISTS `heystar_records` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`school_id` INT UNSIGNED NOT NULL,
			`record_id` VARCHAR(80) NOT NULL,
			`created_at` INT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY (`id`),
			UNIQUE KEY `uk_heystar_rec` (`school_id`, `record_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
		$ready = true;
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public static function forSchool(int $schoolId): ?array
	{
		self::ensureSchema();
		if ($schoolId <= 0) {
			return null;
		}
		$row = \Config\Database::connect()->table('heystar_devices')
			->where('school_id', $schoolId)
			->get()
			->getRowArray();
		return $row ?: null;
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public static function forDeviceKey(string $deviceKey): ?array
	{
		self::ensureSchema();
		$deviceKey = trim($deviceKey);
		if ($deviceKey === '') {
			return null;
		}
		$row = \Config\Database::connect()->table('heystar_devices')
			->where('device_key', $deviceKey)
			->get()
			->getRowArray();
		return $row ?: null;
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function save(int $schoolId, array $data): void
	{
		self::ensureSchema();
		$db = \Config\Database::connect();
		$now = date('Y-m-d H:i:s');
		$existing = self::forSchool($schoolId);
		$row = [
			'school_id' => $schoolId,
			'device_key' => trim((string) ($data['device_key'] ?? ($existing['device_key'] ?? ''))),
			'device_ip' => trim((string) ($data['device_ip'] ?? ($existing['device_ip'] ?? ''))),
			'password' => trim((string) ($data['password'] ?? ($existing['password'] ?? 'HFSecurity'))),
			'area_id' => (int) ($data['area_id'] ?? ($existing['area_id'] ?? 0)),
			'updated_at' => $now,
		];
		if (isset($data['last_seen'])) {
			$row['last_seen'] = (int) $data['last_seen'];
		}
		if ($existing) {
			$db->table('heystar_devices')->where('id', $existing['id'])->update($row);
			return;
		}
		$db->table('heystar_devices')->insert($row);
	}

	public static function seenRecord(int $schoolId, string $recordId): bool
	{
		self::ensureSchema();
		$recordId = trim($recordId);
		if ($recordId === '') {
			return false;
		}
		$db = \Config\Database::connect();
		$hit = $db->table('heystar_records')
			->where('school_id', $schoolId)
			->where('record_id', $recordId)
			->get()
			->getRowArray();
		if ($hit) {
			return true;
		}
		$db->table('heystar_records')->insert([
			'school_id' => $schoolId,
			'record_id' => $recordId,
			'created_at' => time(),
		]);
		return false;
	}
}
