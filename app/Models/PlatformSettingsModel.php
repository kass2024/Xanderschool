<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Global platform registration fees (super-admin).
 * Single-row table: service_fee + platform_fee (RWF).
 */
class PlatformSettingsModel extends Model
{
	protected $table = 'platform_settings';
	protected $primaryKey = 'id';
	protected $allowedFields = [
		'service_fee',
		'platform_fee',
		'updated_by',
	];
	protected $useTimestamps = true;

	public function ensureSchema(): void
	{
		$db = \Config\Database::connect();
		$db->query("CREATE TABLE IF NOT EXISTS `platform_settings` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`service_fee` INT NOT NULL DEFAULT 0,
			`platform_fee` INT NOT NULL DEFAULT 0,
			`updated_by` INT NULL DEFAULT NULL,
			`created_at` DATETIME NULL DEFAULT NULL,
			`updated_at` DATETIME NULL DEFAULT NULL,
			PRIMARY KEY (`id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

		$row = $this->orderBy('id', 'ASC')->first();
		if ($row === null) {
			$this->insert([
				'service_fee' => 600,
				'platform_fee' => 0,
				'updated_by' => 0,
			]);
		}
	}

	/**
	 * @return array{id:int,service_fee:int,platform_fee:int}
	 */
	public function getFees(): array
	{
		$this->ensureSchema();
		$row = $this->orderBy('id', 'ASC')->first();
		if (!is_array($row)) {
			return ['id' => 0, 'service_fee' => 0, 'platform_fee' => 0];
		}

		return [
			'id' => (int) ($row['id'] ?? 0),
			'service_fee' => max(0, (int) ($row['service_fee'] ?? 0)),
			'platform_fee' => max(0, (int) ($row['platform_fee'] ?? 0)),
		];
	}

	/**
	 * @return array{id:int,service_fee:int,platform_fee:int}
	 */
	public function saveFees(int $serviceFee, int $platformFee, int $updatedBy = 0): array
	{
		$this->ensureSchema();
		$current = $this->getFees();
		$id = (int) ($current['id'] ?? 0);
		$payload = [
			'service_fee' => max(0, $serviceFee),
			'platform_fee' => max(0, $platformFee),
			'updated_by' => $updatedBy,
		];
		if ($id > 0) {
			$payload['id'] = $id;
			$this->save($payload);
		} else {
			$this->insert($payload);
		}

		return $this->getFees();
	}
}
