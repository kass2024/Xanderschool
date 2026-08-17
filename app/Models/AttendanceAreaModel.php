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

	/**
	 * Today's IN/OUT totals for one area.
	 *
	 * @return array{inside:int,checked_in:int,checked_out:int,scans:int,still_in_pct:int}
	 */
	public function todayKpi(int $schoolId, int $areaId): array
	{
		$this->ensureSchema();
		$empty = [
			'inside' => 0,
			'checked_in' => 0,
			'checked_out' => 0,
			'scans' => 0,
			'still_in_pct' => 0,
		];
		if ($schoolId <= 0 || $areaId <= 0) {
			return $empty;
		}

		$db = \Config\Database::connect();
		if (!$db->tableExists('attendance_records')) {
			return $empty;
		}

		$todayStart = strtotime('today');
		$todayEnd = strtotime('tomorrow') - 1;
		$row = $db->table('attendance_records')
			->select('COUNT(*) AS checked_in, SUM(CASE WHEN COALESCE(time_out, 0) = 0 THEN 1 ELSE 0 END) AS inside, SUM(CASE WHEN COALESCE(time_out, 0) > 0 THEN 1 ELSE 0 END) AS checked_out', false)
			->where('school_id', $schoolId)
			->where('area_id', $areaId)
			->where('user_type', 0)
			->where('time_in >=', $todayStart)
			->where('time_in <=', $todayEnd)
			->get()
			->getRowArray();

		$in = (int) ($row['checked_in'] ?? 0);
		$inside = (int) ($row['inside'] ?? 0);
		$out = (int) ($row['checked_out'] ?? 0);
		return [
			'inside' => $inside,
			'checked_in' => $in,
			'checked_out' => $out,
			'scans' => $in + $out,
			'still_in_pct' => $in > 0 ? (int) round($inside / $in * 100) : 0,
		];
	}

	/**
	 * Inside / checked-in counts for every active area today.
	 *
	 * @return list<array{id:int,name:string,inside:int,checked_in:int}>
	 */
	public function todayAreaSummaries(int $schoolId): array
	{
		$areas = $this->listAreas($schoolId, true);
		if ($areas === []) {
			return [];
		}

		$db = \Config\Database::connect();
		$counts = [];
		if ($db->tableExists('attendance_records')) {
			$todayStart = strtotime('today');
			$todayEnd = strtotime('tomorrow') - 1;
			$rows = $db->table('attendance_records')
				->select('area_id, COUNT(*) AS checked_in, SUM(CASE WHEN COALESCE(time_out, 0) = 0 THEN 1 ELSE 0 END) AS inside', false)
				->where('school_id', $schoolId)
				->where('user_type', 0)
				->where('time_in >=', $todayStart)
				->where('time_in <=', $todayEnd)
				->groupBy('area_id')
				->get()
				->getResultArray();
			foreach ($rows as $r) {
				$counts[(int) $r['area_id']] = [
					'inside' => (int) ($r['inside'] ?? 0),
					'checked_in' => (int) ($r['checked_in'] ?? 0),
				];
			}
		}

		$out = [];
		foreach ($areas as $a) {
			$id = (int) $a['id'];
			$out[] = [
				'id' => $id,
				'name' => (string) ($a['name'] ?? ''),
				'inside' => $counts[$id]['inside'] ?? 0,
				'checked_in' => $counts[$id]['checked_in'] ?? 0,
			];
		}
		return $out;
	}

	/**
	 * Latest IN/OUT events for the area today (newest first).
	 *
	 * @return list<array<string,mixed>>
	 */
	public function recentEvents(int $schoolId, int $areaId, int $limit = 8): array
	{
		$this->ensureSchema();
		if ($schoolId <= 0 || $areaId <= 0) {
			return [];
		}

		$db = \Config\Database::connect();
		if (!$db->tableExists('attendance_records')) {
			return [];
		}

		$todayStart = strtotime('today');
		$todayEnd = strtotime('tomorrow') - 1;
		$rows = $db->table('attendance_records ar')
			->select('ar.time_in, ar.time_out, s.fname, s.lname, s.regno, s.photo')
			->join('students s', 's.id = ar.user_id')
			->where('ar.school_id', $schoolId)
			->where('ar.area_id', $areaId)
			->where('ar.user_type', 0)
			->where('ar.time_in >=', $todayStart)
			->where('ar.time_in <=', $todayEnd)
			->orderBy('ar.id', 'DESC')
			->limit(20)
			->get()
			->getResultArray();

		$events = [];
		foreach ($rows as $r) {
			$name = trim((string) ($r['fname'] ?? '') . ' ' . (string) ($r['lname'] ?? ''));
			$base = [
				'name' => $name !== '' ? $name : 'Student',
				'regno' => (string) ($r['regno'] ?? ''),
				'photo' => $r['photo'] ?? null,
			];
			$outTs = (int) ($r['time_out'] ?? 0);
			if ($outTs > 0) {
				$events[] = $base + ['status' => 'OUT', 'time' => $outTs];
			}
			$events[] = $base + ['status' => 'IN', 'time' => (int) ($r['time_in'] ?? 0)];
		}

		usort($events, static function ($a, $b) {
			return (int) $b['time'] <=> (int) $a['time'];
		});

		return array_slice($events, 0, $limit);
	}
}
