<?php

namespace App\Services\Budget;

class FinancialAuditService
{
	public function log($entityType, $entityId, $action, $actorId, $before = null, $after = null, $orgId = null, $branchId = null)
	{
		$db = \Config\Database::connect();
		$db->table('financial_audit_logs')->insert([
			'organization_id' => $orgId,
			'branch_id' => $branchId,
			'entity_type' => (string) $entityType,
			'entity_id' => (int) $entityId,
			'action' => (string) $action,
			'actor_id' => $actorId ? (int) $actorId : null,
			'before_json' => $before !== null ? json_encode($before) : null,
			'after_json' => $after !== null ? json_encode($after) : null,
			'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
			'created_at' => date('Y-m-d H:i:s'),
		]);
	}
}
