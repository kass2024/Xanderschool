<?php

namespace App\Services\Budget;

/**
 * Amount-based cash request approval chains (master-school cash flow settings).
 *
 * Chains:
 * - short: Headmaster → Director of Finance
 * - medium: Headmaster → Procurement → Director of Finance
 * - full: Headmaster → Procurement → Budget Manager → Director of Finance
 */
class CashRequestApprovalPolicy
{
	public const CHAIN_SHORT = 'short';
	public const CHAIN_MEDIUM = 'medium';
	public const CHAIN_FULL = 'full';

	/** Default when master has not configured tiers yet. */
	public static function defaultTiers(): array
	{
		return [
			[
				'max_amount' => 60000,
				'chain' => self::CHAIN_SHORT,
				'label' => 'Low value',
			],
			[
				'max_amount' => null,
				'chain' => self::CHAIN_FULL,
				'label' => 'Standard',
			],
		];
	}

	public static function chainLabels(): array
	{
		return [
			self::CHAIN_SHORT => 'Headmaster → Director of Finance',
			self::CHAIN_MEDIUM => 'Headmaster → Procurement → Director of Finance',
			self::CHAIN_FULL => 'Headmaster → Procurement → Budget Manager → Director of Finance',
		];
	}

	public static function chainStepNames(string $chain): array
	{
		$map = [
			self::CHAIN_SHORT => ['Headmaster', 'Director of Finance', 'Pay'],
			self::CHAIN_MEDIUM => ['Headmaster', 'Procurement', 'Director of Finance', 'Pay'],
			self::CHAIN_FULL => ['Headmaster', 'Procurement', 'Budget Manager', 'Director of Finance', 'Pay'],
		];
		return $map[$chain] ?? $map[self::CHAIN_FULL];
	}

	public static function ensureSchema(): void
	{
		$db = \Config\Database::connect();
		try {
			$cols = $db->query('SHOW COLUMNS FROM budget_settings')->getResultArray();
			$names = array_column($cols, 'Field');
			if (!in_array('approval_tiers_json', $names, true)) {
				$db->query('ALTER TABLE budget_settings ADD COLUMN approval_tiers_json TEXT NULL AFTER budget_utilization_alert_pct');
			}
		} catch (\Throwable $e) {
			// table may not exist yet
		}
		try {
			$cols = $db->query('SHOW COLUMNS FROM cash_requests')->getResultArray();
			$names = array_column($cols, 'Field');
			if (!in_array('approval_chain', $names, true)) {
				$db->query("ALTER TABLE cash_requests ADD COLUMN approval_chain VARCHAR(20) NOT NULL DEFAULT 'full' AFTER requested_amount");
			}
		} catch (\Throwable $e) {
			// ignore
		}
	}

	public static function loadTiersForOrg(int $orgId): array
	{
		self::ensureSchema();
		$db = \Config\Database::connect();
		$row = $db->table('budget_settings')
			->where('organization_id', $orgId)
			->where('branch_id', null)
			->get(1)->getRowArray();
		$raw = $row['approval_tiers_json'] ?? null;
		$tiers = self::parseTiers($raw);
		return $tiers ?: self::defaultTiers();
	}

	public static function parseTiers($raw): array
	{
		if (is_array($raw)) {
			$decoded = $raw;
		} else {
			$decoded = json_decode((string) $raw, true);
		}
		if (!is_array($decoded) || $decoded === []) {
			return [];
		}
		$out = [];
		foreach ($decoded as $t) {
			if (!is_array($t)) {
				continue;
			}
			$chain = strtolower(trim((string) ($t['chain'] ?? self::CHAIN_FULL)));
			if (!in_array($chain, [self::CHAIN_SHORT, self::CHAIN_MEDIUM, self::CHAIN_FULL], true)) {
				$chain = self::CHAIN_FULL;
			}
			$max = $t['max_amount'] ?? null;
			$max = ($max === '' || $max === null) ? null : (float) $max;
			$out[] = [
				'max_amount' => $max,
				'chain' => $chain,
				'label' => trim((string) ($t['label'] ?? '')) ?: self::chainLabels()[$chain],
			];
		}
		usort($out, static function ($a, $b) {
			$am = $a['max_amount'];
			$bm = $b['max_amount'];
			if ($am === null && $bm === null) {
				return 0;
			}
			if ($am === null) {
				return 1;
			}
			if ($bm === null) {
				return -1;
			}
			return $am <=> $bm;
		});
		return $out;
	}

	public static function resolveChain(int $orgId, float $amount): array
	{
		$tiers = self::loadTiersForOrg($orgId);
		$amount = max(0, $amount);
		$matched = end($tiers) ?: [
			'max_amount' => null,
			'chain' => self::CHAIN_FULL,
			'label' => 'Standard',
		];
		foreach ($tiers as $tier) {
			$max = $tier['max_amount'];
			if ($max === null || $amount <= (float) $max) {
				$matched = $tier;
				break;
			}
		}
		$chain = $matched['chain'] ?? self::CHAIN_FULL;
		return [
			'chain' => $chain,
			'label' => $matched['label'] ?? (self::chainLabels()[$chain] ?? $chain),
			'steps' => self::chainStepNames($chain),
			'steps_label' => self::chainLabels()[$chain] ?? $chain,
			'max_amount' => $matched['max_amount'] ?? null,
		];
	}

	/**
	 * Allowed workflow actions at a status for a given chain.
	 * @return string[] action keys (approve-type only; return/reject added by caller)
	 */
	public static function allowedApproveActions(string $chain, string $status): array
	{
		$chain = strtolower($chain) ?: self::CHAIN_FULL;
		if ($status === 'SUBMITTED') {
			return ['headteacher_approve'];
		}
		if ($status === 'HEADTEACHER_APPROVED') {
			if ($chain === self::CHAIN_SHORT) {
				return ['final_approve'];
			}
			return ['procurement_approve'];
		}
		if ($status === 'PROCUREMENT_APPROVED') {
			if ($chain === self::CHAIN_MEDIUM) {
				return ['final_approve'];
			}
			return ['budget_approve'];
		}
		if ($status === 'BUDGET_APPROVED') {
			return ['final_approve'];
		}
		return [];
	}

	public static function flowStatuses(string $chain): array
	{
		if ($chain === self::CHAIN_SHORT) {
			return ['SUBMITTED', 'HEADTEACHER_APPROVED', 'FINANCE_AUTHORIZED', 'PAID'];
		}
		if ($chain === self::CHAIN_MEDIUM) {
			return ['SUBMITTED', 'HEADTEACHER_APPROVED', 'PROCUREMENT_APPROVED', 'FINANCE_AUTHORIZED', 'PAID'];
		}
		return ['SUBMITTED', 'HEADTEACHER_APPROVED', 'PROCUREMENT_APPROVED', 'BUDGET_APPROVED', 'FINANCE_AUTHORIZED', 'PAID'];
	}

	public static function flowLabels(string $chain): array
	{
		if ($chain === self::CHAIN_SHORT) {
			return [
				['SUBMITTED', 'Submitted'],
				['HEADTEACHER_APPROVED', 'Headmaster'],
				['FINANCE_AUTHORIZED', 'Dir. Finance'],
				['PAID', 'Paid'],
			];
		}
		if ($chain === self::CHAIN_MEDIUM) {
			return [
				['SUBMITTED', 'Submitted'],
				['HEADTEACHER_APPROVED', 'Headmaster'],
				['PROCUREMENT_APPROVED', 'Procurement'],
				['FINANCE_AUTHORIZED', 'Dir. Finance'],
				['PAID', 'Paid'],
			];
		}
		return [
			['SUBMITTED', 'Submitted'],
			['HEADTEACHER_APPROVED', 'Headmaster'],
			['PROCUREMENT_APPROVED', 'Procurement'],
			['BUDGET_APPROVED', 'Budget Mgr'],
			['FINANCE_AUTHORIZED', 'Dir. Finance'],
			['PAID', 'Paid'],
		];
	}

	public static function amountBandLabel(array $tier, ?float $prevMax = null): string
	{
		$max = $tier['max_amount'];
		if ($prevMax === null && $max !== null) {
			return 'Up to ' . number_format((float) $max, 0) . ' RWF';
		}
		if ($max === null && $prevMax !== null) {
			return 'Above ' . number_format((float) $prevMax, 0) . ' RWF';
		}
		if ($max === null) {
			return 'Any amount';
		}
		return number_format((float) ($prevMax ?? 0) + 0.01, 0) . ' – ' . number_format((float) $max, 0) . ' RWF';
	}
}
