<?php

namespace App\Services;

use App\Models\ApplicationSettingsModel;

class ApplicationRegistrationFeeService
{
	private static $schemaReady = false;

	public function ensureSchema(): void
	{
		if (self::$schemaReady) {
			return;
		}
		$db = \Config\Database::connect();
		foreach (['add_application_registration_fees.sql', 'add_department_registration_fees.sql'] as $file) {
			$sqlFile = ROOTPATH . 'deploy/' . $file;
			if (!is_file($sqlFile)) {
				continue;
			}
			foreach (array_filter(array_map('trim', explode(';', file_get_contents($sqlFile)))) as $stmt) {
				if ($stmt === '') {
					continue;
				}
				try {
					$db->query($stmt);
				} catch (\Throwable $e) {
					// column/table/index may exist
				}
			}
		}
		if (!$db->fieldExists('fee_mode', 'application_settings')) {
			try {
				$db->query("ALTER TABLE `application_settings` ADD COLUMN `fee_mode` VARCHAR(20) NOT NULL DEFAULT 'flat' AFTER `registration_fees`");
			} catch (\Throwable $e) {
			}
		}
		if (!$db->fieldExists('studying_mode', 'application_registration_fees')) {
			try {
				$db->query("ALTER TABLE `application_registration_fees` ADD COLUMN `studying_mode` TINYINT NOT NULL DEFAULT -1 AFTER `ref_id`");
			} catch (\Throwable $e) {
			}
		}
		self::$schemaReady = true;
	}

	/**
	 * @return array{level: array<int,int>, class: array<int,int>, department: array<int,array{day:int,boarding:int}>}
	 */
	public function getTierMap(int $settingsId): array
	{
		$this->ensureSchema();
		$db = \Config\Database::connect();
		$map = ['level' => [], 'class' => [], 'department' => []];
		$rows = $db->table('application_registration_fees')
			->where('settings_id', $settingsId)
			->get()->getResultArray();
		foreach ($rows as $row) {
			$refType = (string) ($row['ref_type'] ?? '');
			$refId = (int) ($row['ref_id']);
			$amount = (int) ($row['fee_amount'] ?? 0);
			if ($refType === 'department') {
				if (!isset($map['department'][$refId])) {
					$map['department'][$refId] = ['day' => 0, 'boarding' => 0];
				}
				$sm = (int) ($row['studying_mode'] ?? -1);
				if ($sm === 1) {
					$map['department'][$refId]['day'] = $amount;
				} elseif ($sm === 0) {
					$map['department'][$refId]['boarding'] = $amount;
				}
				continue;
			}
			$type = $refType === 'class' ? 'class' : 'level';
			$map[$type][$refId] = $amount;
		}
		return $map;
	}

	public function saveTierFees(int $schoolId, int $settingsId, string $feeMode, array $levelFees, array $classFees, array $departmentFees = []): void
	{
		$this->ensureSchema();
		$db = \Config\Database::connect();
		$db->table('application_registration_fees')->where('settings_id', $settingsId)->delete();
		$insert = function (string $type, int $refId, int $amount, int $studyingMode = -1) use ($db, $schoolId, $settingsId) {
			if ($refId <= 0) {
				return;
			}
			$db->table('application_registration_fees')->insert([
				'school_id' => $schoolId,
				'settings_id' => $settingsId,
				'ref_type' => $type,
				'ref_id' => $refId,
				'studying_mode' => $studyingMode,
				'fee_amount' => max(0, (int) $amount),
			]);
		};
		if ($feeMode === 'level') {
			foreach ($levelFees as $refId => $amount) {
				$insert('level', (int) $refId, (int) $amount);
			}
		} elseif ($feeMode === 'class') {
			foreach ($classFees as $refId => $amount) {
				$insert('class', (int) $refId, (int) $amount);
			}
		} elseif ($feeMode === 'department') {
			foreach ($departmentFees as $refId => $pair) {
				$refId = (int) $refId;
				if ($refId <= 0 || !is_array($pair)) {
					continue;
				}
				$insert('department', $refId, (int) ($pair['boarding'] ?? 0), 0);
				$insert('department', $refId, (int) ($pair['day'] ?? 0), 1);
			}
		}
	}

	/**
	 * Resolve registration fee for an applicant.
	 *
	 * @param int|string|null $studyingMode 0=boarding, 1=day
	 */
	public function resolveFee(int $settingsId, ?int $classId = null, ?int $levelId = null, ?int $departmentId = null, $studyingMode = null): int
	{
		$this->ensureSchema();
		$appMdl = new ApplicationSettingsModel();
		$settings = $appMdl->find($settingsId);
		if (!$settings) {
			return 0;
		}
		$flat = (int) ($settings['registration_fees'] ?? 0);
		$mode = $settings['fee_mode'] ?? 'flat';
		if ($mode === 'flat' || $mode === '') {
			return $flat;
		}
		$db = \Config\Database::connect();
		if ($mode === 'department' && $departmentId > 0) {
			$sm = ($studyingMode === '1' || $studyingMode === 1) ? 1 : 0;
			$row = $db->table('application_registration_fees')
				->where('settings_id', $settingsId)
				->where('ref_type', 'department')
				->where('ref_id', $departmentId)
				->where('studying_mode', $sm)
				->get(1)->getRowArray();
			if ($row) {
				return (int) $row['fee_amount'];
			}
			return $flat;
		}
		if ($mode === 'class' && $classId > 0) {
			$row = $db->table('application_registration_fees')
				->where('settings_id', $settingsId)
				->where('ref_type', 'class')
				->where('ref_id', $classId)
				->get(1)->getRowArray();
			if ($row) {
				return (int) $row['fee_amount'];
			}
		}
		if ($mode === 'level' && $levelId > 0) {
			$row = $db->table('application_registration_fees')
				->where('settings_id', $settingsId)
				->where('ref_type', 'level')
				->where('ref_id', $levelId)
				->get(1)->getRowArray();
			if ($row) {
				return (int) $row['fee_amount'];
			}
		}
		return $flat;
	}
}
