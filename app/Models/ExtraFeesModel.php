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
