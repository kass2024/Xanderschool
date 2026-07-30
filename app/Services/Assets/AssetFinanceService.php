<?php

namespace App\Services\Assets;

use App\Models\AssetModel;
use App\Models\AssetOpsSchema;

/**
 * Depreciation runs and asset register reports. PHP 7.4.
 */
class AssetFinanceService
{
	/**
	 * Run straight-line depreciation for one calendar month.
	 *
	 * @param int $schoolId
	 * @param string $periodYm YYYY-MM
	 * @param int $actorId
	 * @return array
	 */
	public function runStraightLineMonth($schoolId, $periodYm, $actorId)
	{
		AssetOpsSchema::ensureAll();
		(new AssetModel())->ensureSchema();

		$schoolId = (int) $schoolId;
		$actorId = (int) $actorId;
		$periodYm = trim((string) $periodYm);
		if (!preg_match('/^\d{4}-\d{2}$/', $periodYm)) {
			return ['success' => false, 'error' => 'Invalid period_ym; use YYYY-MM'];
		}

		$db = \Config\Database::connect();
		$now = date('Y-m-d H:i:s');
		$processed = 0;
		$skipped = 0;

		$assets = $db->table('assets')
			->where('school_id', $schoolId)
			->where('archived_at', null)
			->where('depreciation_method', 'straight_line')
			->where('useful_life_months >', 0)
			->get()->getResultArray();

		$db->transStart();

		foreach ($assets as $asset) {
			$assetId = (int) $asset['id'];
			$exists = $db->table('asset_depreciation_entries')
				->where('school_id', $schoolId)
				->where('asset_id', $assetId)
				->where('period_ym', $periodYm)
				->countAllResults();

			if ($exists > 0) {
				$skipped++;
				continue;
			}

			$cost = (float) ($asset['total_acquisition_cost'] ?? $asset['purchase_price'] ?? 0);
			$residual = (float) ($asset['residual_value'] ?? 0);
			$life = (int) $asset['useful_life_months'];
			if ($life <= 0 || $cost <= 0) {
				$skipped++;
				continue;
			}

			$monthly = round(($cost - $residual) / $life, 2);
			if ($monthly <= 0) {
				$skipped++;
				continue;
			}

			$accumulated = (float) ($asset['accumulated_depreciation'] ?? 0);
			$nbv = (float) ($asset['net_book_value'] ?? $cost);

			if ($nbv <= $residual) {
				$skipped++;
				continue;
			}

			$amount = $monthly;
			if ($nbv - $amount < $residual) {
				$amount = round($nbv - $residual, 2);
			}
			if ($amount <= 0) {
				$skipped++;
				continue;
			}

			$newAccum = round($accumulated + $amount, 2);
			$newNbv = round(max($residual, $cost - $newAccum), 2);

			$db->table('asset_depreciation_entries')->insert([
				'school_id' => $schoolId,
				'asset_id' => $assetId,
				'period_ym' => $periodYm,
				'amount' => $amount,
				'accumulated' => $newAccum,
				'net_book_value' => $newNbv,
				'method' => 'straight_line',
				'notes' => 'Monthly straight-line run',
				'created_by' => $actorId,
				'created_at' => $now,
			]);

			$db->table('assets')->where('id', $assetId)->update([
				'accumulated_depreciation' => $newAccum,
				'net_book_value' => $newNbv,
				'updated_at' => $now,
			]);

			$processed++;
		}

		$db->transComplete();

		return [
			'success' => $db->transStatus() !== false,
			'period_ym' => $periodYm,
			'processed' => $processed,
			'skipped' => $skipped,
		];
	}

	/**
	 * Asset register report data arrays.
	 *
	 * @param int $schoolId
	 * @return array
	 */
	public function assetRegisterReport($schoolId)
	{
		AssetOpsSchema::ensureAll();
		$db = \Config\Database::connect();
		$schoolId = (int) $schoolId;
		$today = date('Y-m-d');
		$in30 = date('Y-m-d', strtotime('+30 days'));

		$byLocation = $db->table('assets a')
			->select('l.location_code, l.name AS location_name,
				COUNT(a.id) AS asset_count,
				COALESCE(SUM(a.total_acquisition_cost),0) AS total_cost,
				COALESCE(SUM(a.net_book_value),0) AS total_nbv')
			->join('asset_locations l', 'l.id = a.location_id', 'left')
			->where('a.school_id', $schoolId)
			->where('a.archived_at', null)
			->groupBy('a.location_id, l.location_code, l.name')
			->orderBy('l.name', 'ASC')
			->get()->getResultArray();

		$byCategory = $db->table('assets a')
			->select('c.category_code, c.name AS category_name,
				COUNT(a.id) AS asset_count,
				COALESCE(SUM(a.total_acquisition_cost),0) AS total_cost,
				COALESCE(SUM(a.net_book_value),0) AS total_nbv')
			->join('asset_categories c', 'c.id = a.category_id', 'left')
			->where('a.school_id', $schoolId)
			->where('a.archived_at', null)
			->groupBy('a.category_id, c.category_code, c.name')
			->orderBy('c.name', 'ASC')
			->get()->getResultArray();

		$byCustodian = $db->table('assets a')
			->select('a.custodian_staff_id,
				CONCAT(st.fname, " ", st.lname) AS custodian_name,
				COUNT(a.id) AS asset_count,
				COALESCE(SUM(a.net_book_value),0) AS total_nbv')
			->join('staffs st', 'st.id = a.custodian_staff_id', 'left')
			->where('a.school_id', $schoolId)
			->where('a.archived_at', null)
			->where('a.custodian_staff_id IS NOT NULL', null, false)
			->groupBy('a.custodian_staff_id, st.fname, st.lname')
			->orderBy('custodian_name', 'ASC')
			->get()->getResultArray();

		$overdueLoans = (new AssetCirculationService())->overdueLoans($schoolId);

		$maintenanceDue = $db->table('assets')
			->select('id, asset_code, name, next_maintenance_date, lifecycle_status')
			->where('school_id', $schoolId)
			->where('archived_at', null)
			->where('next_maintenance_date IS NOT NULL', null, false)
			->where('next_maintenance_date <=', $in30)
			->orderBy('next_maintenance_date', 'ASC')
			->get()->getResultArray();

		$warrantyExpiry = $db->table('assets')
			->select('id, asset_code, name, warranty_expiry, supplier')
			->where('school_id', $schoolId)
			->where('archived_at', null)
			->where('warranty_expiry IS NOT NULL', null, false)
			->where('warranty_expiry <=', $in30)
			->where('warranty_expiry >=', $today)
			->orderBy('warranty_expiry', 'ASC')
			->get()->getResultArray();

		$missingDamaged = $db->table('assets')
			->select('id, asset_code, name, lifecycle_status, condition_code, location_id')
			->where('school_id', $schoolId)
			->where('archived_at', null)
			->groupStart()
				->whereIn('lifecycle_status', ['missing', 'stolen', 'damaged'])
				->orWhere('condition_code', 'damaged')
			->groupEnd()
			->orderBy('lifecycle_status', 'ASC')
			->get()->getResultArray();

		$depreciationSchedule = $db->table('asset_depreciation_entries de')
			->select('de.*, a.asset_code, a.name AS asset_name')
			->join('assets a', 'a.id = de.asset_id', 'left')
			->where('de.school_id', $schoolId)
			->orderBy('de.period_ym', 'DESC')
			->orderBy('a.asset_code', 'ASC')
			->limit(500)
			->get()->getResultArray();

		return [
			'by_location' => $byLocation,
			'by_category' => $byCategory,
			'by_custodian' => $byCustodian,
			'overdue_loans' => $overdueLoans,
			'maintenance_due' => $maintenanceDue,
			'warranty_expiry' => $warrantyExpiry,
			'missing_damaged' => $missingDamaged,
			'depreciation_schedule' => $depreciationSchedule,
		];
	}
}
