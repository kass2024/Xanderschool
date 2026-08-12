<?php

namespace App\Services\Budget;

class BudgetAvailabilityService
{
	public function lineAvailability($budgetLineId)
	{
		$db = \Config\Database::connect();
		$line = $db->table('budget_lines')->where('id', (int) $budgetLineId)->get(1)->getRowArray();
		if (!$line) {
			return null;
		}
		$approved = (float) ($line['annual_amount'] ?? 0);
		if ($approved <= 0) {
			$approved = (float) (new BudgetCalculationService())->lineAnnualAmount($line);
		}
		$adjustments = 0.0; // extended in adjustments phase
		$transfersIn = (float) ($db->query(
			"SELECT COALESCE(SUM(amount),0) AS t FROM budget_transfers WHERE dest_line_id = ? AND status = 'APPROVED'",
			[$budgetLineId]
		)->getRowArray()['t'] ?? 0);
		$transfersOut = (float) ($db->query(
			"SELECT COALESCE(SUM(amount),0) AS t FROM budget_transfers WHERE source_line_id = ? AND status = 'APPROVED'",
			[$budgetLineId]
		)->getRowArray()['t'] ?? 0);
		$paid = (float) ($db->query(
			"SELECT COALESCE(SUM(p.amount),0) AS t FROM cash_request_payments p
			INNER JOIN cash_requests cr ON cr.id = p.cash_request_id
			INNER JOIN cash_request_lines crl ON crl.cash_request_id = cr.id AND crl.budget_line_id = ?
			WHERE p.status = 'completed' AND cr.status IN ('PAID','RECEIPT_CONFIRMED','CLOSED','PARTIALLY_PAID')",
			[$budgetLineId]
		)->getRowArray()['t'] ?? 0);
		$committed = (float) ($db->query(
			"SELECT COALESCE(SUM(amount),0) AS t FROM budget_commitments WHERE budget_line_id = ? AND status = 'open'",
			[$budgetLineId]
		)->getRowArray()['t'] ?? 0);
		$revised = $approved + $adjustments + $transfersIn - $transfersOut;
		$available = round($revised - $paid - $committed, 2);
		return [
			'original' => $approved,
			'revised' => $revised,
			'paid' => round($paid, 2),
			'committed' => round($committed, 2),
			'available' => $available,
			'utilization_pct' => $revised > 0 ? round((($paid + $committed) / $revised) * 100, 2) : 0,
		];
	}
}
