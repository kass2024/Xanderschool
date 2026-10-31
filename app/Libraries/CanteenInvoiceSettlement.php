<?php

namespace App\Libraries;

/**
 * One canteen card per selling day. The Director of Finance marks the card
 * paid only when the whole amount for that day is settled.
 */
class CanteenInvoiceSettlement
{
	public function ensure(): void
	{
		$db = \Config\Database::connect();
		$db->query(
			"CREATE TABLE IF NOT EXISTS canteen_day_settlements (
				id INT UNSIGNED NOT NULL AUTO_INCREMENT,
				school_id INT UNSIGNED NOT NULL,
				sale_date DATE NOT NULL,
				amount INT NOT NULL,
				paid_by INT UNSIGNED NOT NULL DEFAULT 0,
				paid_at DATETIME NOT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY school_day (school_id, sale_date)
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
		$grouped = [];
		foreach ($rows as $row) {
			$stamp = strtotime((string) ($row['at'] ?? ''));
			$date = $stamp ? date('Y-m-d', $stamp) : '';
			if ($date === '') {
				continue;
			}
			if (!isset($grouped[$date])) {
				$grouped[$date] = [];
			}
			$grouped[$date][] = $row;
		}
		$marks = $this->dayMap($schoolId, array_keys($grouped));
		$cards = [];
		$paidSales = 0;
		$unpaidSales = 0;
		$paidCount = 0;
		foreach ($grouped as $date => $lines) {
			usort($lines, static function ($a, $b) {
				return strcmp((string) ($a['at'] ?? ''), (string) ($b['at'] ?? ''));
			});
			$amount = 0;
			$students = [];
			foreach ($lines as $line) {
				$amount += (int) ($line['amount'] ?? 0);
				$students[(string) ($line['regno'] ?? '') . '|' . (string) ($line['name'] ?? '')] = true;
			}
			$mark = $marks[$date] ?? null;
			$settled = (int) ($mark['amount'] ?? 0);
			$paid = $mark !== null && $settled === $amount && $amount > 0;
			if ($paid) {
				$paidSales += $amount;
				$paidCount++;
			} else {
				$unpaidSales += $amount;
			}
			$stamp = strtotime($date . ' 12:00:00');
			$cards[] = [
				'date' => $date,
				'label' => $stamp ? date('l, j M Y', $stamp) : $date,
				'amount' => $amount,
				'sales' => count($lines),
				'students' => count($students),
				'paid' => $paid,
				'paid_at' => $paid ? (string) ($mark['paid_at'] ?? '') : '',
				'paid_by_name' => $paid ? trim((string) ($mark['paid_by_name'] ?? '')) : '',
				'settled_amount' => $mark !== null ? $settled : 0,
				'lines' => $lines,
			];
		}
		usort($cards, static function ($a, $b) {
			return strcmp((string) $b['date'], (string) $a['date']);
		});
		$desk['dayCards'] = $cards;
		$desk['paidSales'] = $paidSales;
		$desk['unpaidSales'] = $unpaidSales;
		$desk['paidCount'] = $paidCount;
		$desk['unpaidCount'] = count($cards) - $paidCount;
		$desk['dayCount'] = count($cards);

		return $desk;
	}

	/**
	 * @return array{paid:bool,paid_at:string,paid_by_name:string,amount:int}
	 */
	public function setDayPaid(int $schoolId, string $date, int $staffId, bool $paid): array
	{
		$this->ensure();
		if ($schoolId < 1 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
			throw new \RuntimeException('Choose one selling day.');
		}
		$amount = $this->dayTotal($schoolId, $date);
		$db = \Config\Database::connect();
		$table = $db->table('canteen_day_settlements');
		if ($paid) {
			if ($amount < 1) {
				throw new \RuntimeException('That day has no sales to mark paid.');
			}
			$existing = $table->where('school_id', $schoolId)->where('sale_date', $date)->get()->getRowArray();
			$payload = [
				'amount' => $amount,
				'paid_by' => $staffId,
				'paid_at' => date('Y-m-d H:i:s'),
			];
			if ($existing) {
				$table->where('id', (int) $existing['id'])->update($payload);
			} else {
				$payload['school_id'] = $schoolId;
				$payload['sale_date'] = $date;
				$table->insert($payload);
			}
		} else {
			$table->where('school_id', $schoolId)->where('sale_date', $date)->delete();
		}
		$mark = $this->dayMap($schoolId, [$date]);
		$row = $mark[$date] ?? null;
		$isPaid = $row !== null && (int) ($row['amount'] ?? 0) === $amount && $amount > 0;

		return [
			'paid' => $isPaid,
			'paid_at' => $isPaid ? (string) ($row['paid_at'] ?? '') : '',
			'paid_by_name' => $isPaid ? trim((string) ($row['paid_by_name'] ?? '')) : '',
			'amount' => $amount,
		];
	}

	public function sendExcel(array $card, string $schoolName): void
	{
		$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
		$sheet->setTitle('Day card');
		$sheet->setCellValue('A1', $schoolName !== '' ? $schoolName : 'Canteen');
		$sheet->setCellValue('A2', 'Canteen card · ' . (string) ($card['label'] ?? ''));
		$sheet->setCellValue('A3', !empty($card['paid']) ? 'Paid' : 'Unpaid');
		$sheet->setCellValue('A4', 'Total (Rwf)');
		$sheet->setCellValue('B4', (int) ($card['amount'] ?? 0));
		$headers = ['When', 'Student', 'Class', 'Reg no', 'What they took', 'Amount', 'Left on card'];
		$col = 'A';
		foreach ($headers as $header) {
			$sheet->setCellValue($col . '6', $header);
			$col++;
		}
		$rowNo = 7;
		foreach ($card['lines'] ?? [] as $line) {
			$stamp = strtotime((string) ($line['at'] ?? ''));
			$sheet->setCellValue('A' . $rowNo, $stamp ? date('d M Y H:i', $stamp) : '');
			$sheet->setCellValue('B' . $rowNo, (string) ($line['name'] ?? ''));
			$sheet->setCellValue('C' . $rowNo, (string) ($line['class_name'] ?? ''));
			$sheet->setCellValue('D' . $rowNo, (string) ($line['regno'] ?? ''));
			$sheet->setCellValue('E' . $rowNo, (string) ($line['items'] ?? ''));
			$sheet->setCellValue('F' . $rowNo, (int) ($line['amount'] ?? 0));
			$sheet->setCellValue('G' . $rowNo, (int) ($line['balance'] ?? 0));
			$rowNo++;
		}
		$sheet->setCellValue('E' . $rowNo, 'TOTAL');
		$sheet->setCellValue('F' . $rowNo, (int) ($card['amount'] ?? 0));
		foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G'] as $column) {
			$sheet->getColumnDimension($column)->setAutoSize(true);
		}
		$date = preg_replace('/[^0-9-]/', '', (string) ($card['date'] ?? date('Y-m-d')));
		$filename = 'canteen-card-' . $date . '.xlsx';
		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Cache-Control: max-age=0');
		$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
		$writer->save('php://output');
		exit;
	}

	private function dayTotal(int $schoolId, string $date): int
	{
		$db = \Config\Database::connect();
		$row = $db->query(
			"SELECT COALESCE(SUM(p.amount), 0) AS total
			 FROM payment_transactions p
			 INNER JOIN students s ON s.id = p.student_id
			 WHERE s.school_id = ? AND p.type = 1 AND p.status = 1 AND p.source = 'POS'
			   AND p.created_at >= ? AND p.created_at <= ?",
			[$schoolId, $date . ' 00:00:00', $date . ' 23:59:59']
		)->getRowArray();

		return (int) round((float) ($row['total'] ?? 0));
	}

	/**
	 * @param string[] $dates
	 * @return array<string,array<string,mixed>>
	 */
	private function dayMap(int $schoolId, array $dates): array
	{
		$dates = array_values(array_filter($dates, static function ($date) {
			return is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
		}));
		if ($dates === []) {
			return [];
		}
		$db = \Config\Database::connect();
		$found = $db->table('canteen_day_settlements s')
			->select("s.sale_date, s.amount, s.paid_at, TRIM(CONCAT(IFNULL(st.fname,''), ' ', IFNULL(st.lname,''))) AS paid_by_name", false)
			->join('staffs st', 'st.id = s.paid_by', 'left')
			->where('s.school_id', $schoolId)
			->whereIn('s.sale_date', $dates)
			->get()
			->getResultArray();
		$map = [];
		foreach ($found as $row) {
			$date = substr((string) ($row['sale_date'] ?? ''), 0, 10);
			if ($date !== '') {
				$map[$date] = $row;
			}
		}

		return $map;
	}
}
