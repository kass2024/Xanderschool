<?php namespace App\Models;
use CodeIgniter\Model;

class DisciplineModel extends Model{
	protected $table = "disciplines";
	protected $allowedFields = ["school_id","type","code_id","occurrence","student_id","active_term","comment","marks","notify_parent","created_by","voided","void_reason","voided_by","voided_name","voided_at","lateness_key"];
	protected $useTimestamps = true;
	protected $primaryKey = 'id';
	protected $createdField  = 'created_at';
	protected $updatedField  = 'updated_at';

	public function ensureVoidColumns(): void
	{
		static $done = false;
		if ($done) {
			return;
		}
		$db = \Config\Database::connect();
		$add = [
			'voided' => 'ALTER TABLE disciplines ADD COLUMN voided TINYINT(1) NOT NULL DEFAULT 0',
			'void_reason' => 'ALTER TABLE disciplines ADD COLUMN void_reason VARCHAR(255) NULL',
			'voided_by' => 'ALTER TABLE disciplines ADD COLUMN voided_by INT NULL',
			'voided_name' => 'ALTER TABLE disciplines ADD COLUMN voided_name VARCHAR(120) NULL',
			'voided_at' => 'ALTER TABLE disciplines ADD COLUMN voided_at DATETIME NULL',
		];
		foreach ($add as $col => $sql) {
			if (!$db->fieldExists($col, 'disciplines')) {
				$db->query($sql);
			}
		}
		$done = true;
	}

	public const LATENESS_MARKS = 5;

	public function ensureLatenessColumn(): void
	{
		static $done = false;
		if ($done) {
			return;
		}
		$this->ensureVoidColumns();
		$db = \Config\Database::connect();
		if ($db->tableExists('disciplines') && !$db->fieldExists('lateness_key', 'disciplines')) {
			$db->query('ALTER TABLE disciplines ADD COLUMN lateness_key VARCHAR(80) NULL DEFAULT NULL');
		}
		if ($db->tableExists('disciplines')) {
			try {
				$idx = $db->query("SHOW INDEX FROM disciplines WHERE Key_name = 'uniq_disc_lateness'")->getResultArray();
				if ($idx === []) {
					$db->query('ALTER TABLE disciplines ADD UNIQUE KEY uniq_disc_lateness (lateness_key)');
				}
			} catch (\Throwable $e) {
				// index may already exist
			}
		}
		$done = true;
	}

	/**
	 * Deduct 5 discipline marks for one late arrival. Parents are not notified.
	 * The same student, location, and day is charged once.
	 *
	 * @param array<string,mixed> $area
	 */
	public function recordLateness(int $schoolId, int $studentId, array $area, int $eventTime): bool
	{
		if ($schoolId < 1 || $studentId < 1 || $eventTime < 1) {
			return false;
		}
		if (!\App\Models\AttendanceAreaModel::isLate($area, $eventTime)) {
			return false;
		}
		$this->ensureLatenessColumn();
		$areaId = (int) ($area['id'] ?? 0);
		$day = date('Y-m-d', $eventTime);
		$key = $schoolId . ':' . $studentId . ':' . $areaId . ':' . $day;
		$db = \Config\Database::connect();
		$exists = $db->table('disciplines')->where('lateness_key', $key)->countAllResults();
		if ($exists > 0) {
			return false;
		}
		$term = $db->table('schools')->select('active_term')->where('id', $schoolId)->get()->getRow();
		$activeTerm = (int) ($term->active_term ?? 0);
		if ($activeTerm < 1) {
			return false;
		}
		$name = trim((string) ($area['name'] ?? ''));
		if ($name === '') {
			$name = 'Location';
		}
		$cut = \App\Models\AttendanceAreaModel::normalizeDailyStart((string) ($area['consider_lateness'] ?? ''));
		$comment = 'Late at ' . $name . '. Arrived ' . date('H:i', $eventTime) . '. Consider lateness ' . $cut . '.';
		try {
			$this->insert([
				'school_id' => $schoolId,
				'type' => 1,
				'code_id' => 0,
				'occurrence' => 1,
				'student_id' => $studentId,
				'active_term' => $activeTerm,
				'comment' => $comment,
				'marks' => self::LATENESS_MARKS,
				'notify_parent' => 0,
				'created_by' => 0,
				'voided' => 0,
				'lateness_key' => $key,
			]);
		} catch (\Throwable $e) {
			return false;
		}
		return true;
	}

}
