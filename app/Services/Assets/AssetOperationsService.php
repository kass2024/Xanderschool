<?php

namespace App\Services\Assets;

use App\Models\AssetModel;
use App\Models\AssetOpsSchema;
use App\Models\AssetStatusHistoryModel;

/**
 * Transfers, maintenance, inspections, incidents, audits, disposals. PHP 7.4.
 */
class AssetOperationsService
{
	/**
	 * @param int $schoolId
	 * @param array $data transfer fields + asset_ids[]
	 * @param int $actorId
	 * @return array
	 */
	public function createTransfer($schoolId, array $data, $actorId)
	{
		AssetOpsSchema::ensureAll();
		$db = \Config\Database::connect();
		$schoolId = (int) $schoolId;
		$now = date('Y-m-d H:i:s');
		$transferNo = $this->nextDocNo($schoolId, 'asset_transfers', 'transfer_no', 'TRF');

		$db->table('asset_transfers')->insert([
			'school_id' => $schoolId,
			'transfer_no' => $transferNo,
			'status' => 'draft',
			'transfer_type' => $data['transfer_type'] ?? 'location',
			'is_temporary' => !empty($data['is_temporary']) ? 1 : 0,
			'from_location_id' => !empty($data['from_location_id']) ? (int) $data['from_location_id'] : null,
			'to_location_id' => !empty($data['to_location_id']) ? (int) $data['to_location_id'] : null,
			'from_custodian_id' => !empty($data['from_custodian_id']) ? (int) $data['from_custodian_id'] : null,
			'to_custodian_id' => !empty($data['to_custodian_id']) ? (int) $data['to_custodian_id'] : null,
			'notes' => $data['notes'] ?? null,
			'created_by' => (int) $actorId,
			'created_at' => $now,
			'updated_at' => $now,
		]);
		$transferId = (int) $db->insertID();

		$assetIds = isset($data['asset_ids']) && is_array($data['asset_ids']) ? $data['asset_ids'] : [];
		foreach ($assetIds as $aid) {
			$db->table('asset_transfer_items')->insert([
				'transfer_id' => $transferId,
				'school_id' => $schoolId,
				'asset_id' => (int) $aid,
				'status' => 'pending',
				'created_at' => $now,
			]);
		}

		return ['success' => true, 'transfer_id' => $transferId, 'transfer_no' => $transferNo];
	}

	/**
	 * @param int $schoolId
	 * @param int $transferId
	 * @param int $actorId
	 * @return array
	 */
	public function submitTransfer($schoolId, $transferId, $actorId)
	{
		return $this->updateTransferStatus((int) $schoolId, (int) $transferId, 'pending_approval', (int) $actorId);
	}

	/**
	 * @param int $schoolId
	 * @param int $transferId
	 * @param int $actorId
	 * @return array
	 */
	public function approveTransfer($schoolId, $transferId, $actorId)
	{
		AssetOpsSchema::ensureAll();
		$db = \Config\Database::connect();
		$now = date('Y-m-d H:i:s');
		$row = $this->getTransfer((int) $schoolId, (int) $transferId);
		if (!$row) {
			return ['success' => false, 'error' => 'Transfer not found'];
		}
		if ($row['status'] !== 'pending_approval') {
			return ['success' => false, 'error' => 'Transfer is not pending approval'];
		}

		$db->table('asset_transfers')->where('id', (int) $transferId)->update([
			'status' => 'approved',
			'approved_by' => (int) $actorId,
			'updated_at' => $now,
		]);

		return ['success' => true];
	}

	/**
	 * @param int $schoolId
	 * @param int $transferId
	 * @param int $actorId
	 * @param array $itemResults asset_id => status (accepted|rejected|missing|damaged)
	 * @return array
	 */
	public function receiveTransfer($schoolId, $transferId, $actorId, array $itemResults = [])
	{
		AssetOpsSchema::ensureAll();
		$db = \Config\Database::connect();
		$schoolId = (int) $schoolId;
		$transferId = (int) $transferId;
		$now = date('Y-m-d H:i:s');

		$transfer = $this->getTransfer($schoolId, $transferId);
		if (!$transfer) {
			return ['success' => false, 'error' => 'Transfer not found'];
		}
		if (!in_array($transfer['status'], ['approved', 'dispatched'], true)) {
			return ['success' => false, 'error' => 'Transfer must be approved or dispatched before receive'];
		}

		$items = $db->table('asset_transfer_items')
			->where('transfer_id', $transferId)
			->where('school_id', $schoolId)
			->get()->getResultArray();

		$assetMdl = new AssetModel();
		$histMdl = new AssetStatusHistoryModel();

		$db->transStart();

		foreach ($items as $item) {
			$assetId = (int) $item['asset_id'];
			$itemStatus = isset($itemResults[$assetId])
				? $itemResults[$assetId]
				: (isset($itemResults[(string) $assetId]) ? $itemResults[(string) $assetId] : 'accepted');

			$db->table('asset_transfer_items')->where('id', $item['id'])->update([
				'status' => $itemStatus,
			]);

			if ($itemStatus !== 'accepted') {
				continue;
			}

			$asset = $assetMdl->where('school_id', $schoolId)->where('id', $assetId)->first();
			if (!$asset) {
				continue;
			}

			$updates = ['updated_by' => (int) $actorId];
			$fromLoc = $asset['location_id'];
			$fromCust = $asset['custodian_staff_id'];

			if (!empty($transfer['to_location_id'])) {
				$updates['location_id'] = (int) $transfer['to_location_id'];
			}
			if (!empty($transfer['to_custodian_id'])) {
				$updates['custodian_staff_id'] = (int) $transfer['to_custodian_id'];
				$updates['lifecycle_status'] = 'assigned';
			}

			$assetMdl->update($assetId, $updates);

			if (!empty($transfer['to_location_id']) && (int) $transfer['to_location_id'] !== (int) $fromLoc) {
				$db->table('asset_location_history')->insert([
					'school_id' => $schoolId,
					'asset_id' => $assetId,
					'from_location_id' => $fromLoc,
					'to_location_id' => (int) $transfer['to_location_id'],
					'moved_by' => (int) $actorId,
					'notes' => 'Transfer ' . $transfer['transfer_no'],
					'created_at' => $now,
				]);
			}

			$histMdl->logChange([
				'school_id' => $schoolId,
				'asset_id' => $assetId,
				'previous_status' => $asset['lifecycle_status'],
				'new_status' => $updates['lifecycle_status'] ?? $asset['lifecycle_status'],
				'operation_type' => 'transfer_complete',
				'actor_id' => (int) $actorId,
				'source_location_id' => $fromLoc,
				'destination_location_id' => $transfer['to_location_id'],
				'previous_custodian_id' => $fromCust,
				'new_custodian_id' => $transfer['to_custodian_id'],
				'approval_ref' => $transfer['transfer_no'],
			]);
		}

		$db->table('asset_transfers')->where('id', $transferId)->update([
			'status' => 'completed',
			'received_by' => (int) $actorId,
			'completed_at' => $now,
			'updated_at' => $now,
		]);

		$db->transComplete();

		return ['success' => $db->transStatus() !== false];
	}

	/**
	 * @param int $schoolId
	 * @param array $filters
	 * @return array
	 */
	public function listTransfers($schoolId, array $filters = [])
	{
		AssetOpsSchema::ensureAll();
		$db = \Config\Database::connect();
		$builder = $db->table('asset_transfers t')
			->select('t.*, COUNT(ti.id) AS item_count')
			->join('asset_transfer_items ti', 'ti.transfer_id = t.id', 'left')
			->where('t.school_id', (int) $schoolId)
			->groupBy('t.id');

		if (!empty($filters['status'])) {
			$builder->where('t.status', $filters['status']);
		}

		return $builder->orderBy('t.id', 'DESC')->get()->getResultArray();
	}

	/**
	 * @param int $schoolId
	 * @param array $data
	 * @param int $actorId
	 * @return array
	 */
	public function createMaintenance($schoolId, array $data, $actorId)
	{
		AssetOpsSchema::ensureAll();
		$db = \Config\Database::connect();
		$schoolId = (int) $schoolId;
		$now = date('Y-m-d H:i:s');
		$woNo = $this->nextDocNo($schoolId, 'asset_maintenance', 'work_order_no', 'WO');
		$assetId = (int) ($data['asset_id'] ?? 0);

		$labour = (float) ($data['labour_cost'] ?? 0);
		$parts = (float) ($data['parts_cost'] ?? 0);
		$other = (float) ($data['other_cost'] ?? 0);
		$total = $labour + $parts + $other;

		$db->table('asset_maintenance')->insert([
			'school_id' => $schoolId,
			'work_order_no' => $woNo,
			'asset_id' => $assetId,
			'maintenance_type' => $data['maintenance_type'] ?? 'corrective',
			'problem' => $data['problem'] ?? null,
			'priority' => $data['priority'] ?? 'normal',
			'requested_by' => (int) $actorId,
			'assigned_to' => !empty($data['assigned_to']) ? (int) $data['assigned_to'] : null,
			'provider_type' => $data['provider_type'] ?? 'internal',
			'scheduled_date' => $data['scheduled_date'] ?? null,
			'labour_cost' => $labour,
			'parts_cost' => $parts,
			'other_cost' => $other,
			'total_cost' => $total,
			'status' => 'requested',
			'created_at' => $now,
			'updated_at' => $now,
		]);
		$id = (int) $db->insertID();

		$asset = (new AssetModel())->where('school_id', $schoolId)->where('id', $assetId)->first();
		if ($asset) {
			(new AssetModel())->update($assetId, ['lifecycle_status' => 'under_maintenance']);
			(new AssetStatusHistoryModel())->logChange([
				'school_id' => $schoolId,
				'asset_id' => $assetId,
				'previous_status' => $asset['lifecycle_status'],
				'new_status' => 'under_maintenance',
				'operation_type' => 'maintenance_request',
				'actor_id' => (int) $actorId,
				'approval_ref' => $woNo,
			]);
		}

		return ['success' => true, 'maintenance_id' => $id, 'work_order_no' => $woNo];
	}

	/**
	 * @param int $schoolId
	 * @param int $maintenanceId
	 * @param string $status
	 * @param int $actorId
	 * @param array $extra
	 * @return array
	 */
	public function updateMaintenanceStatus($schoolId, $maintenanceId, $status, $actorId, array $extra = [])
	{
		AssetOpsSchema::ensureAll();
		$db = \Config\Database::connect();
		$now = date('Y-m-d H:i:s');

		$row = $db->table('asset_maintenance')
			->where('school_id', (int) $schoolId)
			->where('id', (int) $maintenanceId)
			->get(1)->getRowArray();

		if (!$row) {
			return ['success' => false, 'error' => 'Maintenance record not found'];
		}

		$update = array_merge([
			'status' => $status,
			'updated_at' => $now,
		], $extra);

		if ($status === 'in_progress' && empty($row['start_date'])) {
			$update['start_date'] = date('Y-m-d');
		}
		if (in_array($status, ['completed', 'verified'], true)) {
			$update['completion_date'] = date('Y-m-d');
			if (!empty($extra['next_maintenance_date'])) {
				(new AssetModel())->update((int) $row['asset_id'], [
					'last_maintenance_date' => date('Y-m-d'),
					'next_maintenance_date' => $extra['next_maintenance_date'],
					'lifecycle_status' => 'available',
				]);
			} else {
				(new AssetModel())->update((int) $row['asset_id'], [
					'last_maintenance_date' => date('Y-m-d'),
					'lifecycle_status' => 'available',
				]);
			}
		}

		$db->table('asset_maintenance')->where('id', (int) $maintenanceId)->update($update);
		return ['success' => true];
	}

	/**
	 * @param int $schoolId
	 * @param array $filters
	 * @return array
	 */
	public function listMaintenance($schoolId, array $filters = [])
	{
		AssetOpsSchema::ensureAll();
		$db = \Config\Database::connect();
		$builder = $db->table('asset_maintenance m')
			->select('m.*, a.asset_code, a.name AS asset_name')
			->join('assets a', 'a.id = m.asset_id', 'left')
			->where('m.school_id', (int) $schoolId);

		if (!empty($filters['status'])) {
			$builder->where('m.status', $filters['status']);
		}
		if (!empty($filters['asset_id'])) {
			$builder->where('m.asset_id', (int) $filters['asset_id']);
		}

		return $builder->orderBy('m.id', 'DESC')->get()->getResultArray();
	}

	/**
	 * @param int $schoolId
	 * @return array
	 */
	public function overdueMaintenance($schoolId)
	{
		AssetOpsSchema::ensureAll();
		$db = \Config\Database::connect();
		$today = date('Y-m-d');

		return $db->table('asset_maintenance m')
			->select('m.*, a.asset_code, a.name AS asset_name')
			->join('assets a', 'a.id = m.asset_id', 'left')
			->where('m.school_id', (int) $schoolId)
			->whereIn('m.status', ['requested', 'approved', 'scheduled', 'in_progress', 'waiting_parts'])
			->where('m.scheduled_date <', $today)
			->where('m.scheduled_date IS NOT NULL', null, false)
			->orderBy('m.scheduled_date', 'ASC')
			->get()->getResultArray();
	}

	/**
	 * @param int $schoolId
	 * @param array $data
	 * @param int $actorId
	 * @return array
	 */
	public function createInspection($schoolId, array $data, $actorId)
	{
		AssetOpsSchema::ensureAll();
		$db = \Config\Database::connect();
		$now = date('Y-m-d H:i:s');
		$assetId = (int) ($data['asset_id'] ?? 0);

		$db->table('asset_inspections')->insert([
			'school_id' => (int) $schoolId,
			'asset_id' => $assetId,
			'inspected_by' => (int) $actorId,
			'inspection_date' => $data['inspection_date'] ?? date('Y-m-d'),
			'result' => $data['result'] ?? 'pass',
			'condition_code' => $data['condition_code'] ?? null,
			'notes' => $data['notes'] ?? null,
			'next_inspection_date' => $data['next_inspection_date'] ?? null,
			'created_at' => $now,
		]);
		$id = (int) $db->insertID();

		$assetUpdate = [
			'last_inspection_date' => $data['inspection_date'] ?? date('Y-m-d'),
		];
		if (!empty($data['next_inspection_date'])) {
			$assetUpdate['next_inspection_date'] = $data['next_inspection_date'];
		}
		if (!empty($data['condition_code'])) {
			$assetUpdate['condition_code'] = $data['condition_code'];
		}
		(new AssetModel())->update($assetId, $assetUpdate);

		return ['success' => true, 'inspection_id' => $id];
	}

	/**
	 * @param int $schoolId
	 * @param array $filters
	 * @return array
	 */
	public function listInspections($schoolId, array $filters = [])
	{
		AssetOpsSchema::ensureAll();
		$db = \Config\Database::connect();
		$builder = $db->table('asset_inspections i')
			->select('i.*, a.asset_code, a.name AS asset_name')
			->join('assets a', 'a.id = i.asset_id', 'left')
			->where('i.school_id', (int) $schoolId);

		if (!empty($filters['asset_id'])) {
			$builder->where('i.asset_id', (int) $filters['asset_id']);
		}

		return $builder->orderBy('i.inspection_date', 'DESC')->get()->getResultArray();
	}

	/**
	 * @param int $schoolId
	 * @param array $data
	 * @param int $actorId
	 * @return array
	 */
	public function createIncident($schoolId, array $data, $actorId)
	{
		AssetOpsSchema::ensureAll();
		$db = \Config\Database::connect();
		$schoolId = (int) $schoolId;
		$now = date('Y-m-d H:i:s');
		$incNo = $this->nextDocNo($schoolId, 'asset_incidents', 'incident_no', 'INC');

		$db->table('asset_incidents')->insert([
			'school_id' => $schoolId,
			'incident_no' => $incNo,
			'asset_id' => (int) ($data['asset_id'] ?? 0),
			'reported_by' => (int) $actorId,
			'incident_at' => $data['incident_at'] ?? $now,
			'location_id' => !empty($data['location_id']) ? (int) $data['location_id'] : null,
			'incident_type' => $data['incident_type'] ?? 'damage',
			'description' => $data['description'] ?? null,
			'people_involved' => $data['people_involved'] ?? null,
			'immediate_action' => $data['immediate_action'] ?? null,
			'estimated_loss' => (float) ($data['estimated_loss'] ?? 0),
			'status' => 'open',
			'created_at' => $now,
			'updated_at' => $now,
		]);
		$id = (int) $db->insertID();

		return ['success' => true, 'incident_id' => $id, 'incident_no' => $incNo];
	}

	/**
	 * @param int $schoolId
	 * @param int $incidentId
	 * @param array $data
	 * @param int $actorId
	 * @return array
	 */
	public function updateIncident($schoolId, $incidentId, array $data, $actorId)
	{
		AssetOpsSchema::ensureAll();
		$db = \Config\Database::connect();
		$allowed = [
			'status', 'findings', 'decision', 'financial_recovery',
			'police_ref', 'insurance_ref', 'description', 'immediate_action',
		];
		$update = ['updated_at' => date('Y-m-d H:i:s')];
		foreach ($allowed as $f) {
			if (array_key_exists($f, $data)) {
				$update[$f] = $data[$f];
			}
		}

		$db->table('asset_incidents')
			->where('school_id', (int) $schoolId)
			->where('id', (int) $incidentId)
			->update($update);

		return ['success' => true];
	}

	/**
	 * @param int $schoolId
	 * @param array $filters
	 * @return array
	 */
	public function listIncidents($schoolId, array $filters = [])
	{
		AssetOpsSchema::ensureAll();
		$db = \Config\Database::connect();
		$builder = $db->table('asset_incidents i')
			->select('i.*, a.asset_code, a.name AS asset_name')
			->join('assets a', 'a.id = i.asset_id', 'left')
			->where('i.school_id', (int) $schoolId);

		if (!empty($filters['status'])) {
			$builder->where('i.status', $filters['status']);
		}

		return $builder->orderBy('i.id', 'DESC')->get()->getResultArray();
	}

	/**
	 * @param int $schoolId
	 * @param array $data
	 * @param int $actorId
	 * @return array
	 */
	public function createAudit($schoolId, array $data, $actorId)
	{
		AssetOpsSchema::ensureAll();
		$db = \Config\Database::connect();
		$schoolId = (int) $schoolId;
		$now = date('Y-m-d H:i:s');
		$auditNo = $this->nextDocNo($schoolId, 'asset_audits', 'audit_no', 'AUD');

		$scope = [
			'location_id' => !empty($data['location_id']) ? (int) $data['location_id'] : null,
			'category_id' => !empty($data['category_id']) ? (int) $data['category_id'] : null,
			'custodian_id' => !empty($data['custodian_id']) ? (int) $data['custodian_id'] : null,
		];

		$assetQuery = $db->table('assets')
			->where('school_id', $schoolId)
			->where('archived_at', null);
		if ($scope['location_id']) {
			$assetQuery->where('location_id', $scope['location_id']);
		}
		if ($scope['category_id']) {
			$assetQuery->where('category_id', $scope['category_id']);
		}
		if ($scope['custodian_id']) {
			$assetQuery->where('custodian_staff_id', $scope['custodian_id']);
		}
		$expectedAssets = $assetQuery->get()->getResultArray();

		$db->table('asset_audits')->insert([
			'school_id' => $schoolId,
			'audit_no' => $auditNo,
			'title' => $data['title'] ?? ('Audit ' . $auditNo),
			'status' => 'in_progress',
			'location_id' => $scope['location_id'],
			'category_id' => $scope['category_id'],
			'custodian_id' => $scope['custodian_id'],
			'snapshot_json' => json_encode(['expected_count' => count($expectedAssets), 'scope' => $scope]),
			'created_by' => (int) $actorId,
			'created_at' => $now,
			'updated_at' => $now,
		]);
		$auditId = (int) $db->insertID();

		foreach ($expectedAssets as $asset) {
			$db->table('asset_audit_items')->insert([
				'audit_id' => $auditId,
				'school_id' => $schoolId,
				'asset_id' => (int) $asset['id'],
				'expected_location_id' => $asset['location_id'],
				'result' => 'pending',
				'scanned_code' => $asset['asset_code'],
				'created_at' => $now,
			]);
		}

		return ['success' => true, 'audit_id' => $auditId, 'audit_no' => $auditNo, 'expected' => count($expectedAssets)];
	}

	/**
	 * @param int $schoolId
	 * @param int $auditId
	 * @param string $scannedCode
	 * @param int $actorId
	 * @param array $extra found_location_id, condition_code, notes
	 * @return array
	 */
	public function scanAuditItem($schoolId, $auditId, $scannedCode, $actorId, array $extra = [])
	{
		AssetOpsSchema::ensureAll();
		$db = \Config\Database::connect();
		$schoolId = (int) $schoolId;
		$auditId = (int) $auditId;
		$code = strtoupper(trim((string) $scannedCode));
		$now = date('Y-m-d H:i:s');

		$audit = $db->table('asset_audits')
			->where('school_id', $schoolId)
			->where('id', $auditId)
			->get(1)->getRowArray();
		if (!$audit || $audit['status'] === 'closed') {
			return ['success' => false, 'error' => 'Audit not open'];
		}

		$asset = $db->table('assets')
			->where('school_id', $schoolId)
			->groupStart()
				->where('UPPER(asset_code)', $code)
				->orWhere('UPPER(barcode)', $code)
				->orWhere('UPPER(rfid_tag)', $code)
			->groupEnd()
			->get(1)->getRowArray();

		if (!$asset) {
			$db->table('asset_audit_items')->insert([
				'audit_id' => $auditId,
				'school_id' => $schoolId,
				'asset_id' => null,
				'result' => 'unregistered',
				'scanned_code' => $code,
				'notes' => $extra['notes'] ?? 'Scanned code not in register',
				'created_at' => $now,
			]);
			return ['success' => true, 'result' => 'unregistered', 'scanned_code' => $code];
		}

		$item = $db->table('asset_audit_items')
			->where('audit_id', $auditId)
			->where('asset_id', (int) $asset['id'])
			->get(1)->getRowArray();

		$foundLoc = !empty($extra['found_location_id'])
			? (int) $extra['found_location_id']
			: (int) ($asset['location_id'] ?? 0);

		$result = 'found_ok';
		if ($item && (int) ($item['expected_location_id'] ?? 0) !== $foundLoc) {
			$result = 'wrong_location';
		}
		if (!$item) {
			$result = 'unexpected';
		}
		if (!empty($extra['condition_code']) && in_array($extra['condition_code'], ['damaged', 'poor'], true)) {
			$result = 'damaged';
		}

		if ($item) {
			$db->table('asset_audit_items')->where('id', $item['id'])->update([
				'found_location_id' => $foundLoc,
				'result' => $result,
				'condition_code' => $extra['condition_code'] ?? null,
				'notes' => $extra['notes'] ?? null,
				'scanned_code' => $code,
			]);
		} else {
			$db->table('asset_audit_items')->insert([
				'audit_id' => $auditId,
				'school_id' => $schoolId,
				'asset_id' => (int) $asset['id'],
				'expected_location_id' => null,
				'found_location_id' => $foundLoc,
				'result' => $result,
				'condition_code' => $extra['condition_code'] ?? null,
				'notes' => $extra['notes'] ?? null,
				'scanned_code' => $code,
				'created_at' => $now,
			]);
		}

		return ['success' => true, 'result' => $result, 'asset_id' => (int) $asset['id']];
	}

	/**
	 * @param int $schoolId
	 * @param int $auditId
	 * @param int $actorId
	 * @return array
	 */
	public function closeAudit($schoolId, $auditId, $actorId)
	{
		AssetOpsSchema::ensureAll();
		$db = \Config\Database::connect();
		$now = date('Y-m-d H:i:s');
		$schoolId = (int) $schoolId;
		$auditId = (int) $auditId;

		$db->table('asset_audit_items')
			->where('audit_id', $auditId)
			->where('school_id', $schoolId)
			->where('result', 'pending')
			->update(['result' => 'not_found']);

		$db->table('asset_audits')->where('id', $auditId)->update([
			'status' => 'closed',
			'closed_by' => (int) $actorId,
			'closed_at' => $now,
			'updated_at' => $now,
		]);

		return ['success' => true];
	}

	/**
	 * @param int $schoolId
	 * @param array $filters
	 * @return array
	 */
	public function listAudits($schoolId, array $filters = [])
	{
		AssetOpsSchema::ensureAll();
		$db = \Config\Database::connect();
		$builder = $db->table('asset_audits a')
			->select('a.*, COUNT(ai.id) AS item_count')
			->join('asset_audit_items ai', 'ai.audit_id = a.id', 'left')
			->where('a.school_id', (int) $schoolId)
			->groupBy('a.id');

		if (!empty($filters['status'])) {
			$builder->where('a.status', $filters['status']);
		}

		return $builder->orderBy('a.id', 'DESC')->get()->getResultArray();
	}

	/**
	 * @param int $schoolId
	 * @param array $data
	 * @param int $actorId
	 * @return array
	 */
	public function requestDisposal($schoolId, array $data, $actorId)
	{
		AssetOpsSchema::ensureAll();
		$db = \Config\Database::connect();
		$schoolId = (int) $schoolId;
		$now = date('Y-m-d H:i:s');
		$dspNo = $this->nextDocNo($schoolId, 'asset_disposals', 'disposal_no', 'DSP');
		$assetId = (int) ($data['asset_id'] ?? 0);

		$db->table('asset_disposals')->insert([
			'school_id' => $schoolId,
			'asset_id' => $assetId,
			'disposal_no' => $dspNo,
			'method' => $data['method'] ?? 'write_off',
			'status' => 'requested',
			'reason' => $data['reason'] ?? null,
			'requested_by' => (int) $actorId,
			'created_at' => $now,
			'updated_at' => $now,
		]);
		$id = (int) $db->insertID();

		(new AssetModel())->update($assetId, ['lifecycle_status' => 'pending_disposal']);

		return ['success' => true, 'disposal_id' => $id, 'disposal_no' => $dspNo];
	}

	/**
	 * @param int $schoolId
	 * @param int $disposalId
	 * @param int $actorId
	 * @return array
	 */
	public function approveDisposal($schoolId, $disposalId, $actorId)
	{
		AssetOpsSchema::ensureAll();
		$db = \Config\Database::connect();
		$db->table('asset_disposals')
			->where('school_id', (int) $schoolId)
			->where('id', (int) $disposalId)
			->where('status', 'requested')
			->update([
				'status' => 'approved',
				'approved_by' => (int) $actorId,
				'updated_at' => date('Y-m-d H:i:s'),
			]);

		return ['success' => true];
	}

	/**
	 * @param int $schoolId
	 * @param int $disposalId
	 * @param int $actorId
	 * @param float|null $proceeds
	 * @return array
	 */
	public function completeDisposal($schoolId, $disposalId, $actorId, $proceeds = null)
	{
		AssetOpsSchema::ensureAll();
		$db = \Config\Database::connect();
		$now = date('Y-m-d H:i:s');

		$row = $db->table('asset_disposals')
			->where('school_id', (int) $schoolId)
			->where('id', (int) $disposalId)
			->get(1)->getRowArray();

		if (!$row || $row['status'] !== 'approved') {
			return ['success' => false, 'error' => 'Approved disposal not found'];
		}

		$method = $row['method'];
		$lifecycleMap = [
			'sale' => 'sold',
			'donation' => 'donated',
			'recycle' => 'disposed',
			'write_off' => 'written_off',
		];
		$newStatus = isset($lifecycleMap[$method]) ? $lifecycleMap[$method] : 'disposed';

		$db->table('asset_disposals')->where('id', (int) $disposalId)->update([
			'status' => 'completed',
			'proceeds' => $proceeds !== null ? (float) $proceeds : (float) ($row['proceeds'] ?? 0),
			'completed_at' => $now,
			'updated_at' => $now,
		]);

		$asset = (new AssetModel())->find((int) $row['asset_id']);
		if ($asset) {
			(new AssetModel())->update((int) $row['asset_id'], [
				'lifecycle_status' => $newStatus,
				'archived_at' => $now,
			]);
			(new AssetStatusHistoryModel())->logChange([
				'school_id' => (int) $schoolId,
				'asset_id' => (int) $row['asset_id'],
				'previous_status' => $asset['lifecycle_status'],
				'new_status' => $newStatus,
				'operation_type' => 'disposal',
				'actor_id' => (int) $actorId,
				'approval_ref' => $row['disposal_no'],
			]);
		}

		return ['success' => true];
	}

	/**
	 * @param int $schoolId
	 * @param array $filters
	 * @return array
	 */
	public function listDisposals($schoolId, array $filters = [])
	{
		AssetOpsSchema::ensureAll();
		$db = \Config\Database::connect();
		$builder = $db->table('asset_disposals d')
			->select('d.*, a.asset_code, a.name AS asset_name')
			->join('assets a', 'a.id = d.asset_id', 'left')
			->where('d.school_id', (int) $schoolId)
			->orderBy('d.id', 'DESC');
		if (!empty($filters['status'])) {
			$builder->where('d.status', $filters['status']);
		}
		return $builder->get()->getResultArray();
	}

	/**
	 * @param int $schoolId
	 * @param int $transferId
	 * @param string $status
	 * @param int $actorId
	 * @return array
	 */
	public function updateTransferStatus($schoolId, $transferId, $status, $actorId)
	{
		AssetOpsSchema::ensureAll();
		$db = \Config\Database::connect();
		$row = $this->getTransfer((int) $schoolId, (int) $transferId);
		if (!$row) {
			return ['success' => false, 'error' => 'Transfer not found'];
		}

		$db->table('asset_transfers')->where('id', (int) $transferId)->update([
			'status' => $status,
			'updated_at' => date('Y-m-d H:i:s'),
		]);

		return ['success' => true];
	}

	/**
	 * @param int $schoolId
	 * @param int $transferId
	 * @return array|null
	 */
	private function getTransfer($schoolId, $transferId)
	{
		return \Config\Database::connect()->table('asset_transfers')
			->where('school_id', (int) $schoolId)
			->where('id', (int) $transferId)
			->get(1)->getRowArray();
	}

	/**
	 * @param int $schoolId
	 * @param string $table
	 * @param string $column
	 * @param string $prefix TRF|WO|INC|AUD|DSP
	 * @return string
	 */
	private function nextDocNo($schoolId, $table, $column, $prefix)
	{
		$db = \Config\Database::connect();
		$year = date('Y');
		$fullPrefix = $prefix . '-' . $year . '-';

		$row = $db->query(
			"SELECT `{$column}` FROM `{$table}` WHERE school_id = ? AND `{$column}` LIKE ? ORDER BY id DESC LIMIT 1",
			[(int) $schoolId, $fullPrefix . '%']
		)->getRowArray();

		$seq = 1;
		if ($row && preg_match('/(\d+)$/', $row[$column], $m)) {
			$seq = ((int) $m[1]) + 1;
		}

		return $fullPrefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
	}
}
