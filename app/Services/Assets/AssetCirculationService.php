<?php

namespace App\Services\Assets;

use App\Models\AssetModel;
use App\Models\AssetOpsSchema;
use App\Models\AssetStatusHistoryModel;

/**
 * Staff assignments and asset loans / check-out. PHP 7.4.
 */
class AssetCirculationService
{
	/** @var string[] */
	private static $checkoutAllowed = ['available', 'assigned', 'in_use'];

	/** @var string[] */
	private static $checkoutBlocked = [
		'retired', 'missing', 'stolen', 'under_maintenance', 'checked_out',
		'under_repair', 'damaged', 'disposed', 'sold', 'donated', 'written_off', 'archived',
	];

	/**
	 * @param int $schoolId
	 * @param int $assetId
	 * @param int $staffId
	 * @param string $role
	 * @param int $actorId
	 * @return array
	 */
	public function assignStaff($schoolId, $assetId, $staffId, $role, $actorId)
	{
		AssetOpsSchema::ensureAll();
		$schoolId = (int) $schoolId;
		$assetId = (int) $assetId;
		$staffId = (int) $staffId;
		$actorId = (int) $actorId;
		$role = $this->normalizeRole($role);

		$db = \Config\Database::connect();
		$now = date('Y-m-d H:i:s');

		$asset = $this->findAsset($schoolId, $assetId);
		if (!$asset) {
			return ['success' => false, 'error' => 'Asset not found'];
		}

		$staff = $db->table('staffs')
			->where('school_id', $schoolId)
			->where('id', $staffId)
			->get(1)->getRowArray();
		if (!$staff) {
			return ['success' => false, 'error' => 'Staff not found'];
		}
		if (isset($staff['status']) && (int) $staff['status'] === 0) {
			return ['success' => false, 'error' => 'Inactive staff cannot receive new assignments'];
		}

		$db->table('asset_assignments')
			->where('school_id', $schoolId)
			->where('asset_id', $assetId)
			->where('role', $role)
			->where('status', 'active')
			->update([
				'status' => 'ended',
				'ended_at' => $now,
				'updated_at' => $now,
			]);

		$db->table('asset_assignments')->insert([
			'school_id' => $schoolId,
			'asset_id' => $assetId,
			'staff_id' => $staffId,
			'role' => $role,
			'assigned_at' => $now,
			'status' => 'active',
			'created_by' => $actorId,
			'created_at' => $now,
			'updated_at' => $now,
		]);
		$id = (int) $db->insertID();

		if ($role === 'custodian') {
			$prevCustodian = (int) ($asset['custodian_staff_id'] ?? 0);
			(new AssetModel())->update($assetId, [
				'custodian_staff_id' => $staffId,
				'lifecycle_status' => 'assigned',
				'updated_by' => $actorId,
			]);
			(new AssetStatusHistoryModel())->logChange([
				'school_id' => $schoolId,
				'asset_id' => $assetId,
				'previous_status' => $asset['lifecycle_status'],
				'new_status' => 'assigned',
				'operation_type' => 'assignment',
				'actor_id' => $actorId,
				'previous_custodian_id' => $prevCustodian ?: null,
				'new_custodian_id' => $staffId,
			]);
		}

		return ['success' => true, 'assignment_id' => $id];
	}

	/**
	 * @param int $schoolId
	 * @param int $assignmentId
	 * @param int $actorId
	 * @param string|null $notes
	 * @return array
	 */
	public function endAssignment($schoolId, $assignmentId, $actorId, $notes = null)
	{
		AssetOpsSchema::ensureAll();
		$schoolId = (int) $schoolId;
		$assignmentId = (int) $assignmentId;
		$now = date('Y-m-d H:i:s');
		$db = \Config\Database::connect();

		$row = $db->table('asset_assignments')
			->where('school_id', $schoolId)
			->where('id', $assignmentId)
			->get(1)->getRowArray();

		if (!$row || $row['status'] !== 'active') {
			return ['success' => false, 'error' => 'Active assignment not found'];
		}

		$db->table('asset_assignments')->where('id', $assignmentId)->update([
			'status' => 'ended',
			'ended_at' => $now,
			'notes' => $notes,
			'updated_at' => $now,
		]);

		if ($row['role'] === 'custodian') {
			$asset = $this->findAsset($schoolId, (int) $row['asset_id']);
			if ($asset) {
				(new AssetModel())->update((int) $row['asset_id'], [
					'custodian_staff_id' => null,
					'lifecycle_status' => 'available',
					'updated_by' => (int) $actorId,
				]);
				(new AssetStatusHistoryModel())->logChange([
					'school_id' => $schoolId,
					'asset_id' => (int) $row['asset_id'],
					'previous_status' => $asset['lifecycle_status'],
					'new_status' => 'available',
					'operation_type' => 'assignment_end',
					'actor_id' => (int) $actorId,
					'previous_custodian_id' => (int) $row['staff_id'],
					'new_custodian_id' => null,
					'notes' => $notes,
				]);
			}
		}

		return ['success' => true];
	}

	/**
	 * @param int $schoolId
	 * @param array $filters
	 * @return array
	 */
	public function listAssignments($schoolId, array $filters = [])
	{
		AssetOpsSchema::ensureAll();
		$db = \Config\Database::connect();
		$builder = $db->table('asset_assignments aa')
			->select('aa.*, a.asset_code, a.name AS asset_name,
				CONCAT(st.fname, " ", st.lname) AS staff_name')
			->join('assets a', 'a.id = aa.asset_id', 'left')
			->join('staffs st', 'st.id = aa.staff_id', 'left')
			->where('aa.school_id', (int) $schoolId);

		if (!empty($filters['asset_id'])) {
			$builder->where('aa.asset_id', (int) $filters['asset_id']);
		}
		if (!empty($filters['staff_id'])) {
			$builder->where('aa.staff_id', (int) $filters['staff_id']);
		}
		if (!empty($filters['role'])) {
			$builder->where('aa.role', $filters['role']);
		}
		if (!empty($filters['status'])) {
			$builder->where('aa.status', $filters['status']);
		} else {
			$builder->where('aa.status', 'active');
		}

		return $builder->orderBy('aa.id', 'DESC')->get()->getResultArray();
	}

	/**
	 * @param int $schoolId
	 * @param int $assetId
	 * @param string $borrowerType student|staff
	 * @param int $borrowerId
	 * @param string|null $dueAt
	 * @param int $actorId
	 * @param string|null $condition
	 * @param string|null $notes
	 * @return array
	 */
	public function checkout($schoolId, $assetId, $borrowerType, $borrowerId, $dueAt, $actorId, $condition = null, $notes = null)
	{
		AssetOpsSchema::ensureAll();
		$schoolId = (int) $schoolId;
		$assetId = (int) $assetId;
		$borrowerId = (int) $borrowerId;
		$actorId = (int) $actorId;
		$borrowerType = in_array($borrowerType, ['student', 'staff'], true) ? $borrowerType : 'student';

		$asset = $this->findAsset($schoolId, $assetId);
		if (!$asset) {
			return ['success' => false, 'error' => 'Asset not found'];
		}

		$status = strtolower((string) ($asset['lifecycle_status'] ?? ''));
		if (in_array($status, self::$checkoutBlocked, true)) {
			return ['success' => false, 'error' => 'Asset is not available for checkout (status: ' . $status . ')'];
		}
		if (!in_array($status, self::$checkoutAllowed, true)) {
			return ['success' => false, 'error' => 'Asset status does not allow checkout: ' . $status];
		}

		$db = \Config\Database::connect();
		$openLoan = $db->table('asset_loans')
			->where('school_id', $schoolId)
			->where('asset_id', $assetId)
			->whereIn('status', ['open', 'overdue'])
			->countAllResults();
		if ($openLoan > 0) {
			return ['success' => false, 'error' => 'Asset already has an open loan'];
		}

		if (!$this->borrowerExists($db, $schoolId, $borrowerType, $borrowerId)) {
			return ['success' => false, 'error' => 'Borrower not found'];
		}

		$now = date('Y-m-d H:i:s');
		$due = $dueAt ? date('Y-m-d H:i:s', strtotime($dueAt)) : date('Y-m-d H:i:s', strtotime('+7 days'));

		$db->table('asset_loans')->insert([
			'school_id' => $schoolId,
			'asset_id' => $assetId,
			'borrower_type' => $borrowerType,
			'borrower_id' => $borrowerId,
			'issued_by' => $actorId,
			'issue_at' => $now,
			'due_at' => $due,
			'issue_condition' => $condition,
			'source_location_id' => $asset['location_id'],
			'notes' => $notes,
			'status' => 'open',
			'created_at' => $now,
			'updated_at' => $now,
		]);
		$loanId = (int) $db->insertID();

		(new AssetModel())->update($assetId, [
			'lifecycle_status' => 'checked_out',
			'updated_by' => $actorId,
		]);
		(new AssetStatusHistoryModel())->logChange([
			'school_id' => $schoolId,
			'asset_id' => $assetId,
			'previous_status' => $status,
			'new_status' => 'checked_out',
			'operation_type' => 'checkout',
			'actor_id' => $actorId,
			'notes' => $notes,
		]);

		return ['success' => true, 'loan_id' => $loanId];
	}

	/**
	 * @param int $schoolId
	 * @param int $loanId
	 * @param int $actorId
	 * @param string|null $returnCondition
	 * @param string|null $notes
	 * @return array
	 */
	public function checkin($schoolId, $loanId, $actorId, $returnCondition = null, $notes = null)
	{
		AssetOpsSchema::ensureAll();
		$schoolId = (int) $schoolId;
		$loanId = (int) $loanId;
		$db = \Config\Database::connect();
		$now = date('Y-m-d H:i:s');

		$loan = $db->table('asset_loans')
			->where('school_id', $schoolId)
			->where('id', $loanId)
			->get(1)->getRowArray();

		if (!$loan || !in_array($loan['status'], ['open', 'overdue'], true)) {
			return ['success' => false, 'error' => 'Open loan not found'];
		}

		$asset = $this->findAsset($schoolId, (int) $loan['asset_id']);
		$newStatus = 'available';
		if ($returnCondition === 'damaged') {
			$newStatus = 'damaged';
		}

		$db->table('asset_loans')->where('id', $loanId)->update([
			'return_at' => $now,
			'return_condition' => $returnCondition,
			'notes' => $notes !== null ? $notes : $loan['notes'],
			'status' => $returnCondition === 'lost' ? 'lost' : ($returnCondition === 'damaged' ? 'damaged' : 'returned'),
			'updated_at' => $now,
		]);

		$lifecycle = $returnCondition === 'lost' ? 'missing' : $newStatus;
		(new AssetModel())->update((int) $loan['asset_id'], [
			'lifecycle_status' => $lifecycle,
			'condition_code' => $returnCondition ?: ($asset['condition_code'] ?? 'good'),
			'updated_by' => (int) $actorId,
		]);
		(new AssetStatusHistoryModel())->logChange([
			'school_id' => $schoolId,
			'asset_id' => (int) $loan['asset_id'],
			'previous_status' => $asset ? $asset['lifecycle_status'] : 'checked_out',
			'new_status' => $lifecycle,
			'operation_type' => 'checkin',
			'actor_id' => (int) $actorId,
			'notes' => $notes,
		]);

		return ['success' => true];
	}

	/**
	 * @param int $schoolId
	 * @param int $loanId
	 * @param string $newDueAt
	 * @param int $actorId
	 * @return array
	 */
	public function renewLoan($schoolId, $loanId, $newDueAt, $actorId)
	{
		AssetOpsSchema::ensureAll();
		$db = \Config\Database::connect();
		$loan = $db->table('asset_loans')
			->where('school_id', (int) $schoolId)
			->where('id', (int) $loanId)
			->whereIn('status', ['open', 'overdue'])
			->get(1)->getRowArray();

		if (!$loan) {
			return ['success' => false, 'error' => 'Open loan not found'];
		}

		$due = date('Y-m-d H:i:s', strtotime($newDueAt));
		$db->table('asset_loans')->where('id', (int) $loanId)->update([
			'due_at' => $due,
			'status' => 'open',
			'updated_at' => date('Y-m-d H:i:s'),
		]);

		return ['success' => true, 'due_at' => $due];
	}

	/**
	 * @param int $schoolId
	 * @return array
	 */
	public function overdueLoans($schoolId)
	{
		AssetOpsSchema::ensureAll();
		$db = \Config\Database::connect();
		$now = date('Y-m-d H:i:s');

		$db->table('asset_loans')
			->where('school_id', (int) $schoolId)
			->where('status', 'open')
			->where('due_at <', $now)
			->update(['status' => 'overdue', 'updated_at' => $now]);

		return $db->table('asset_loans al')
			->select('al.*, a.asset_code, a.name AS asset_name')
			->join('assets a', 'a.id = al.asset_id', 'left')
			->where('al.school_id', (int) $schoolId)
			->where('al.status', 'overdue')
			->orderBy('al.due_at', 'ASC')
			->get()->getResultArray();
	}

	/**
	 * @param int $schoolId
	 * @param string $card
	 * @return array|null
	 */
	public function lookupPersonByCard($schoolId, $card)
	{
		AssetOpsSchema::ensureAll();
		$schoolId = (int) $schoolId;
		$cardRaw = trim((string) $card);
		if ($cardRaw === '') {
			return null;
		}

		$db = \Config\Database::connect();
		$normalized = $this->normalizeUID($cardRaw);
		$reversed = $this->reverseHex($normalized);

		$candidates = array_unique(array_filter([$normalized, $reversed, strtoupper($cardRaw)]));

		foreach ($candidates as $try) {
			$student = $db->table('students')
				->where('school_id', $schoolId)
				->groupStart()
					->where('UPPER(TRIM(card))', $try)
				->groupEnd()
				->get(1)->getRowArray();

			if ($student) {
				return $this->formatPerson('student', $student);
			}
		}

		if ($this->tableHasColumn($db, 'staffs', 'card')) {
			foreach ($candidates as $try) {
				$staff = $db->table('staffs')
					->where('school_id', $schoolId)
					->groupStart()
						->where('UPPER(TRIM(card))', $try)
					->groupEnd()
					->get(1)->getRowArray();

				if ($staff) {
					return $this->formatPerson('staff', $staff);
				}
			}
		}

		return null;
	}

	/**
	 * @param int $schoolId
	 * @param string $code
	 * @return array|null
	 */
	public function lookupAssetByScan($schoolId, $code)
	{
		AssetOpsSchema::ensureAll();
		$schoolId = (int) $schoolId;
		$code = strtoupper(trim((string) $code));
		if ($code === '') {
			return null;
		}

		$db = \Config\Database::connect();
		$row = $db->table('assets')
			->where('school_id', $schoolId)
			->where('archived_at', null)
			->groupStart()
				->where('UPPER(asset_code)', $code)
				->orWhere('UPPER(barcode)', $code)
				->orWhere('UPPER(rfid_tag)', $code)
			->groupEnd()
			->get(1)->getRowArray();

		if (!$row) {
			return null;
		}

		return [
			'id' => (int) $row['id'],
			'asset_code' => $row['asset_code'],
			'name' => $row['name'],
			'lifecycle_status' => $row['lifecycle_status'],
			'location_id' => $row['location_id'],
			'category_id' => $row['category_id'],
			'condition_code' => $row['condition_code'],
			'photo_path' => $row['photo_path'] ?? null,
		];
	}

	/**
	 * @param int $schoolId
	 * @param int $assetId
	 * @return array|null
	 */
	private function findAsset($schoolId, $assetId)
	{
		return (new AssetModel())
			->where('school_id', (int) $schoolId)
			->where('id', (int) $assetId)
			->where('archived_at', null)
			->first();
	}

	/**
	 * @param string $role
	 * @return string
	 */
	private function normalizeRole($role)
	{
		$allowed = ['custodian', 'owner', 'user', 'approver', 'auditor', 'maintenance'];
		$role = strtolower(trim((string) $role));
		return in_array($role, $allowed, true) ? $role : 'custodian';
	}

	/**
	 * @param \CodeIgniter\Database\BaseConnection $db
	 * @param int $schoolId
	 * @param string $type
	 * @param int $id
	 * @return bool
	 */
	private function borrowerExists($db, $schoolId, $type, $id)
	{
		if ($type === 'staff') {
			return $db->table('staffs')
				->where('school_id', $schoolId)
				->where('id', $id)
				->where('status', 1)
				->countAllResults() > 0;
		}
		return $db->table('students')
			->where('school_id', $schoolId)
			->where('id', $id)
			->where('status', 1)
			->countAllResults() > 0;
	}

	/**
	 * @param string $type
	 * @param array $row
	 * @return array
	 */
	private function formatPerson($type, array $row)
	{
		if ($type === 'student') {
			return [
				'type' => 'student',
				'id' => (int) $row['id'],
				'fname' => $row['fname'] ?? '',
				'lname' => $row['lname'] ?? '',
				'full_name' => trim(($row['fname'] ?? '') . ' ' . ($row['lname'] ?? '')),
				'reg_no' => $row['reg_no'] ?? null,
				'card' => $row['card'] ?? null,
				'phone' => $row['ft_phone'] ?? $row['gd_phone'] ?? null,
				'photo_path' => !empty($row['photo']) ? $row['photo'] : null,
			];
		}

		return [
			'type' => 'staff',
			'id' => (int) $row['id'],
			'fname' => $row['fname'] ?? '',
			'lname' => $row['lname'] ?? '',
			'full_name' => trim(($row['fname'] ?? '') . ' ' . ($row['lname'] ?? '')),
			'email' => $row['email'] ?? null,
			'phone' => $row['phone'] ?? null,
			'card' => isset($row['card']) ? $row['card'] : null,
			'photo_path' => !empty($row['photo']) ? $row['photo'] : null,
		];
	}

	/**
	 * @param \CodeIgniter\Database\BaseConnection $db
	 * @param string $table
	 * @param string $column
	 * @return bool
	 */
	private function tableHasColumn($db, $table, $column)
	{
		try {
			$fields = $db->getFieldNames($table);
			return in_array($column, $fields, true);
		} catch (\Throwable $e) {
			return false;
		}
	}

	/**
	 * @param string $uid
	 * @return string
	 */
	private function normalizeUID($uid)
	{
		$uid = trim($uid);
		if ($uid === '') {
			return '';
		}
		$uid = preg_replace('/\s+/', '', $uid);
		if (ctype_digit($uid)) {
			try {
				$uid = strtoupper(base_convert($uid, 10, 16));
			} catch (\Throwable $e) {
				return '';
			}
		}
		$uid = strtoupper(preg_replace('/[^A-F0-9]/', '', $uid));
		if ($uid === '' || strlen($uid) < 6) {
			return '';
		}
		return $uid;
	}

	/**
	 * @param string $card
	 * @return string
	 */
	private function reverseHex($card)
	{
		if ($card === '' || strlen($card) % 2 !== 0) {
			return '';
		}
		$bytes = str_split($card, 2);
		return implode('', array_reverse($bytes));
	}
}
