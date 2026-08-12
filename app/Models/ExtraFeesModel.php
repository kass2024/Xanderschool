<?php
namespace App\Models;

use CodeIgniter\Model;

class ExtraFeesModel extends Model
{
	protected $table="extra_fees";
	protected $allowedFields = ["school_id","title","academic_year","type_id","type","term","amount","created_by"];
	protected $useTimestamps = true;
	protected $primaryKey = 'id';
	protected $createdField  = 'created_at';
	protected $updatedField  = 'updated_at';

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
