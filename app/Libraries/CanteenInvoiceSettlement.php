<?php

namespace App\Libraries;

/**
 * Finance mark on a canteen card sale. The card was already charged;
 * this records that the school has settled that invoice.
 */
class CanteenInvoiceSettlement
{
	public function ensure(): void
	{
		$db = \Config\Database::connect();
		$db->query(
			"CREATE TABLE IF NOT EXISTS canteen_invoice_settlements (
				id INT UNSIGNED NOT NULL AUTO_INCREMENT,
				school_id INT UNSIGNED NOT NULL,
				transaction_id INT UNSIGNED NOT NULL,
				paid_by INT UNSIGNED NOT NULL DEFAULT 0,
				paid_at DATETIME NOT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY school_tx (school_id, transaction_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
		);
	}

	/**
	 * @param array<string,mixed> $desk
	 * @return array<string,mixed>
	 */
	public function apply(int $schoolId, array $desk): array
	{
		$this->ensure();
		$rows = isset($desk['invoiceRows']) && is_array($desk['invoiceRows']) ? $desk['invoiceRows'] : [];
		$ids = [];
		foreach ($rows as $row) {
			$id = (int) ($row['id'] ?? 0);
			if ($id > 0) {
				$ids[] = $id;
			}
		}
		$paid = $this->paidMap($schoolId, $ids);
		$paidSales = 0;
		$unpaidSales = 0;
		$paidCount = 0;
		foreach ($rows as &$row) {
			$id = (int) ($row['id'] ?? 0);
			$mark = $paid[$id] ?? null;
			$row['paid'] = $mark !== null;
			$row['paid_at'] = $mark['paid_at'] ?? '';
			$row['paid_by_name'] = trim((string) ($mark['paid_by_name'] ?? ''));
			if ($row['paid']) {
				$paidSales += (int) ($row['amount'] ?? 0);
				$paidCount++;
			} else {
				$unpaidSales += (int) ($row['amount'] ?? 0);
			}
		}
		unset($row);
		$desk['invoiceRows'] = $rows;
		$desk['paidSales'] = $paidSales;
		$desk['unpaidSales'] = $unpaidSales;
		$desk['paidCount'] = $paidCount;
		$desk['unpaidCount'] = count($rows) - $paidCount;

		return $desk;
	}

	/**
	 * @return array{paid:bool,paid_at:string,paid_by_name:string}
	 */
	public function setPaid(int $schoolId, int $transactionId, int $staffId, bool $paid): array
	{
		$this->ensure();
		if ($transactionId < 1 || $schoolId < 1) {
			throw new \RuntimeException('That invoice was not found.');
		}
		$db = \Config\Database::connect();
		$tx = $db->query(
			"SELECT p.id
			 FROM payment_transactions p
			 INNER JOIN students s ON s.id = p.student_id
			 WHERE p.id = ? AND s.school_id = ? AND p.type = 1 AND p.status = 1 AND p.source = 'POS'
			 LIMIT 1",
			[$transactionId, $schoolId]
		)->getRowArray();
		if (!$tx) {
			throw new \RuntimeException('That invoice was not found.');
		}
		$table = $db->table('canteen_invoice_settlements');
		if ($paid) {
			$existing = $table->where('school_id', $schoolId)->where('transaction_id', $transactionId)->get()->getRowArray();
			if (!$existing) {
				$table->insert([
					'school_id' => $schoolId,
					'transaction_id' => $transactionId,
					'paid_by' => $staffId,
					'paid_at' => date('Y-m-d H:i:s'),
				]);
			}
		} else {
			$table->where('school_id', $schoolId)->where('transaction_id', $transactionId)->delete();
		}
		$mark = $this->paidMap($schoolId, [$transactionId]);
		$row = $mark[$transactionId] ?? null;

		return [
			'paid' => $row !== null,
			'paid_at' => $row['paid_at'] ?? '',
			'paid_by_name' => trim((string) ($row['paid_by_name'] ?? '')),
		];
	}

	/**
	 * @param int[] $ids
	 * @return array<int,array<string,mixed>>
	 */
	private function paidMap(int $schoolId, array $ids): array
	{
		if ($ids === []) {
			return [];
		}
		$db = \Config\Database::connect();
		$found = $db->table('canteen_invoice_settlements s')
			->select("s.transaction_id, s.paid_at, TRIM(CONCAT(IFNULL(st.fname,''), ' ', IFNULL(st.lname,''))) AS paid_by_name", false)
			->join('staffs st', 'st.id = s.paid_by', 'left')
			->where('s.school_id', $schoolId)
			->whereIn('s.transaction_id', $ids)
			->get()
			->getResultArray();
		$map = [];
		foreach ($found as $row) {
			$map[(int) $row['transaction_id']] = $row;
		}

		return $map;
	}
}
