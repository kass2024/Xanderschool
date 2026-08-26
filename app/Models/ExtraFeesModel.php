<?php
namespace App\Models;

use CodeIgniter\Model;

class ExtraFeesModel extends Model
{
	protected $table="extra_fees";
	protected $allowedFields = ["school_id","title","academic_year","type_id","type","term","amount","amount_boarding","amount_day","created_by"];
	protected $useTimestamps = true;
	protected $primaryKey = 'id';
	protected $createdField  = 'created_at';
	protected $updatedField  = 'updated_at';

	public function ensureSchema(): void
	{
		$db = \Config\Database::connect();
		if (!$db->fieldExists('amount_boarding', 'extra_fees')) {
			$db->query('ALTER TABLE `extra_fees` ADD COLUMN `amount_boarding` decimal(15,2) DEFAULT NULL AFTER `amount`');
		}
		if (!$db->fieldExists('amount_day', 'extra_fees')) {
			$db->query('ALTER TABLE `extra_fees` ADD COLUMN `amount_day` decimal(15,2) DEFAULT NULL AFTER `amount_boarding`');
		}
	}

	/**
	 * @return array{boarding:?float,day:?float,legacy:float}
	 */
	public static function modeAmounts(array $row): array
	{
		$legacy = (float) ($row['amount'] ?? 0);
		$boarding = $row['amount_boarding'] ?? null;
		$day = $row['amount_day'] ?? null;
		$boarding = ($boarding === null || $boarding === '') ? null : (float) $boarding;
		$day = ($day === null || $day === '') ? null : (float) $day;
		if ($boarding === null && $day === null && $legacy > 0) {
			$boarding = $legacy;
			$day = $legacy;
		}
		return [
			'boarding' => $boarding,
			'day' => $day,
			'legacy' => $legacy,
		];
	}

	public static function expectedForMode(array $row, int $studyingMode): float
	{
		$modes = self::modeAmounts($row);
		$amount = ($studyingMode === 0) ? $modes['boarding'] : $modes['day'];
		if ($amount === null) {
			$amount = $modes['legacy'];
		}
		return max(0, (float) $amount);
	}

	public static function isTrackRegistrationDepartment(?string $title, ?string $code = null): bool
	{
		$c = strtoupper(trim((string) $code));
		if (in_array($c, ['SOD', 'SOF', 'ACC', 'ACCT', 'ACCNT', 'STR', 'ST1', 'ST2'], true)) {
			return true;
		}
		$t = strtolower(trim((string) $title));
		if ($t === '') {
			return false;
		}
		if (preg_match('/software\s*dev|software development|\bsod\b/', $t)) {
			return true;
		}
		if (preg_match('/account/', $t)) {
			return true;
		}
		return (bool) preg_match('/^stream(\s*(one|two|1|2))?$/', $t);
	}

	public function upsertClassModeFee(
		int $schoolId,
		int $yearId,
		int $classId,
		string $title,
		int $term,
		?float $boarding,
		?float $day,
		int $createdBy
	): int {
		if ($schoolId < 1 || $yearId < 1 || $classId < 1 || $term < 1 || $term > 3 || $title === '') {
			return 0;
		}
		$candidates = array_filter([$boarding, $day], static function ($v) {
			return $v !== null;
		});
		$base = $candidates ? max($candidates) : 0;
		$existing = $this->where('school_id', $schoolId)
			->where('academic_year', $yearId)
			->where('type', 0)
			->where('type_id', $classId)
			->where('title', $title)
			->where('term', $term)
			->get(1)->getRowArray();
		$payload = [
			'school_id' => $schoolId,
			'title' => $title,
			'academic_year' => $yearId,
			'type_id' => $classId,
			'type' => 0,
			'term' => $term,
			'amount' => $base,
			'amount_boarding' => $boarding,
			'amount_day' => $day,
			'created_by' => $createdBy,
		];
		if ($existing) {
			$this->update((int) $existing['id'], $payload);
			return (int) $existing['id'];
		}
		return (int) $this->insert($payload);
	}

	/**
	 * Class extra fees for Software Development, Accounting and Stream even when empty.
	 */
	public function ensureTrackRegistrationFees(
		int $schoolId,
		int $yearId,
		int $createdBy,
		float $boarding = 50000,
		float $day = 30000,
		string $title = 'Registration',
		int $term = 1
	): int {
		$this->ensureSchema();
		if ($schoolId < 1 || $yearId < 1) {
			return 0;
		}
		$db = \Config\Database::connect();
		$classes = $db->table('classes c')
			->select('c.id, c.title, l.title as level_name, d.title as dept_title, d.code as dept_code')
			->join('departments d', 'd.id = c.department')
			->join('levels l', 'l.id = c.level', 'left')
			->where('c.school_id', $schoolId)
			->get()->getResultArray();
		$saved = 0;
		foreach ($classes as $cls) {
			$hay = strtolower(trim(($cls['title'] ?? '') . ' ' . ($cls['level_name'] ?? '')));
			if (strpos($hay, 'holiday') !== false) {
				continue;
			}
			if (!self::isTrackRegistrationDepartment($cls['dept_title'] ?? '', $cls['dept_code'] ?? '')) {
				continue;
			}
			$id = $this->upsertClassModeFee(
				$schoolId,
				$yearId,
				(int) $cls['id'],
				$title,
				$term,
				$boarding,
				$day,
				$createdBy
			);
			if ($id > 0) {
				$saved++;
			}
		}
		return $saved;
	}

	/**
	 * Delete an extra fee and linked payment records (fees_type=1).
	 *
	 * @return array{ok:bool,error?:string,payments:int}
	 */
	public function deleteWithLinkedData(int $feeId, int $schoolId): array
	{
		$feeId = (int) $feeId;
		$schoolId = (int) $schoolId;
		if ($feeId < 1 || $schoolId < 1) {
			return ['ok' => false, 'error' => 'Invalid fee.', 'payments' => 0];
		}

		$row = $this->where('id', $feeId)->where('school_id', $schoolId)->first();
		if (!$row) {
			return ['ok' => false, 'error' => 'Fee not found.', 'payments' => 0];
		}

		$db = \Config\Database::connect();
		$db->transStart();

		$paymentCount = (int) $db->table('fees_records')
			->where('fees_id', $feeId)
			->where('fees_type', 1)
			->countAllResults();
		$db->table('fees_records')
			->where('fees_id', $feeId)
			->where('fees_type', 1)
			->delete();

		$this->delete($feeId);

		$db->transComplete();
		if ($db->transStatus() === false) {
			return ['ok' => false, 'error' => 'Delete failed. Please try again.', 'payments' => 0];
		}

		return ['ok' => true, 'payments' => $paymentCount];
	}
}
