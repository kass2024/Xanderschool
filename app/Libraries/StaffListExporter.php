<?php

namespace App\Libraries;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Staff list Excel/PDF export helpers.
 * Excludes Methode / platform support accounts and never includes post/privilege.
 */
class StaffListExporter
{
	private const BRAND = '012F6B';
	private const BRAND_LIGHT = 'E8F0FA';
	private const ALT_ROW = 'F7FAFD';
	private const LOGO_COL = 'A';
	private const TEXT_COL = 'C';

	/** @return list<string> */
	public static function columnHeaders(): array
	{
		return [
			'#',
			'Names',
			'Phone',
			'Email',
			'RFID card',
			'Face',
			'Shift',
			'Last login',
			'Created time',
			'Status',
		];
	}

	public static function lastColumn(): string
	{
		return self::columnLetter(count(self::columnHeaders()));
	}

	public static function exportFilename(string $schoolName, string $ext = 'xlsx'): string
	{
		$base = trim(preg_replace('/\s+/', ' ', $schoolName));
		$base = $base !== '' ? $base . ' staff list' : 'staff list';
		$safe = preg_replace('/[\\\\\\/:*?"<>|]+/', '', $base);
		$safe = trim(preg_replace('/\s{2,}/', ' ', (string) $safe));
		$safe = $safe !== '' ? $safe : 'staff list';
		$safe = str_replace(' ', '_', $safe);

		return $safe . '_' . date('Y-m-d') . '.' . ltrim($ext, '.');
	}

	/**
	 * True for Methode / Xander support accounts that must not appear on school exports.
	 *
	 * @param array<string,mixed> $staff
	 */
	public static function isMethodeStaff(array $staff): bool
	{
		$name = strtolower(trim(($staff['fname'] ?? '') . ' ' . ($staff['lname'] ?? '')));
		$email = strtolower(trim((string) ($staff['email'] ?? '')));
		$hay = $name . ' ' . $email;

		if ($hay === ' ') {
			return false;
		}

		return strpos($hay, 'methode') !== false
			|| strpos($email, 'visaconsultantcanada') !== false
			|| strpos($email, '@xandertech') !== false;
	}

	/**
	 * @param list<array<string,mixed>> $staffs
	 * @return list<array<string,mixed>>
	 */
	public static function filterForExport(array $staffs): array
	{
		$out = [];
		foreach ($staffs as $staff) {
			if (self::isMethodeStaff($staff)) {
				continue;
			}
			$out[] = $staff;
		}

		return $out;
	}

	/**
	 * @param array<string,mixed> $staff
	 */
	public static function rowValues(array $staff, int $num): array
	{
		$lastLogin = (int) ($staff['last_login'] ?? 0);
		$lastLoginLabel = $lastLogin > 0 ? date('Y-m-d H:i:s', $lastLogin) : '—';
		$created = trim((string) ($staff['created_at'] ?? ''));
		if ($created === '' || $created === '0000-00-00 00:00:00') {
			$created = '—';
		}
		$card = trim((string) ($staff['card'] ?? ''));
		$shift = trim((string) ($staff['shift_title'] ?? ''));
		$status = (int) ($staff['status'] ?? 0);

		return [
			$num,
			trim(($staff['fname'] ?? '') . ' ' . ($staff['lname'] ?? '')),
			self::formatIdentifier($staff['phone'] ?? ''),
			trim((string) ($staff['email'] ?? '')),
			$card !== '' ? $card : 'NOT ASSIGNED',
			!empty($staff['face_enrolled']) ? 'ENROLLED' : 'NO FACE',
			$shift !== '' ? $shift : 'Not assigned',
			$lastLoginLabel,
			$created,
			($status === 1 || $status === 2) ? 'Active' : 'Locked',
		];
	}

	/**
	 * @param array<string,mixed> $school
	 * @param list<array<string,mixed>> $staffs
	 */
	public static function buildExcel(
		array $school,
		array $staffs,
		string $yearTitle = '',
		string $termLabel = ''
	): Spreadsheet {
		$staffs = self::filterForExport($staffs);
		$spreadsheet = new Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
		$sheet->setTitle('Staff');
		$lastCol = self::lastColumn();

		$headerEndRow = self::writeSchoolHeader($sheet, $school, $lastCol);
		$titleRow = $headerEndRow + 2;
		$infoRow = $titleRow + 1;
		$headerRow = $infoRow + 2;
		$dataStart = $headerRow + 1;

		$sheet->mergeCells(self::TEXT_COL . "{$titleRow}:{$lastCol}{$titleRow}");
		$sheet->setCellValue(self::TEXT_COL . $titleRow, 'STAFF LIST');
		$sheet->getStyle(self::TEXT_COL . "{$titleRow}:{$lastCol}{$titleRow}")->applyFromArray([
			'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => self::BRAND]],
			'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
		]);
		$sheet->getRowDimension($titleRow)->setRowHeight(26);

		$faceMissing = 0;
		foreach ($staffs as $s) {
			if (empty($s['face_enrolled'])) {
				$faceMissing++;
			}
		}
		$metaParts = array_filter([
			'Academic Year: ' . ($yearTitle !== '' ? $yearTitle : '—'),
			$termLabel !== '' ? $termLabel : null,
			'Exported: ' . date('d M Y H:i'),
			'Total Staff: ' . count($staffs),
			'Without face: ' . $faceMissing,
		]);
		$sheet->mergeCells(self::TEXT_COL . "{$infoRow}:{$lastCol}{$infoRow}");
		$sheet->setCellValue(self::TEXT_COL . $infoRow, implode('   |   ', $metaParts));
		$sheet->getStyle(self::TEXT_COL . "{$infoRow}:{$lastCol}{$infoRow}")->applyFromArray([
			'font' => ['size' => 11, 'color' => ['rgb' => '334155']],
			'fill' => [
				'fillType' => Fill::FILL_SOLID,
				'startColor' => ['rgb' => self::BRAND_LIGHT],
			],
			'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'wrapText' => true],
		]);
		$sheet->getRowDimension($infoRow)->setRowHeight(28);

		$headers = self::columnHeaders();
		$col = 1;
		foreach ($headers as $header) {
			$sheet->setCellValueByColumnAndRow($col, $headerRow, $header);
			$col++;
		}
		$sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
			'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
			'fill' => [
				'fillType' => Fill::FILL_SOLID,
				'startColor' => ['rgb' => self::BRAND],
			],
			'alignment' => [
				'horizontal' => Alignment::HORIZONTAL_CENTER,
				'vertical' => Alignment::VERTICAL_CENTER,
				'wrapText' => true,
			],
			'borders' => [
				'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']],
			],
		]);
		$sheet->getRowDimension($headerRow)->setRowHeight(30);
		$sheet->freezePane("A{$dataStart}");

		$row = $dataStart;
		$num = 1;
		foreach ($staffs as $staff) {
			$values = self::rowValues($staff, $num);
			$col = 1;
			foreach ($values as $value) {
				$cell = $sheet->getCellByColumnAndRow($col, $row);
				if ($col === 3) {
					$cell->setValueExplicit(self::formatIdentifier($value), DataType::TYPE_STRING);
				} else {
					$cell->setValue($value);
				}
				$col++;
			}
			if ($num % 2 === 0) {
				$sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
					'fill' => [
						'fillType' => Fill::FILL_SOLID,
						'startColor' => ['rgb' => self::ALT_ROW],
					],
				]);
			}
			$faceVal = (string) ($values[5] ?? '');
			if ($faceVal === 'NO FACE') {
				$sheet->getStyle("F{$row}")->applyFromArray([
					'font' => ['bold' => true, 'color' => ['rgb' => 'B45309']],
				]);
			} elseif ($faceVal === 'ENROLLED') {
				$sheet->getStyle("F{$row}")->applyFromArray([
					'font' => ['bold' => true, 'color' => ['rgb' => '15803D']],
				]);
			}
			$row++;
			$num++;
		}

		if ($row > $dataStart) {
			$lastDataRow = $row - 1;
			$sheet->getStyle("A{$dataStart}:{$lastCol}{$lastDataRow}")->applyFromArray([
				'borders' => [
					'allBorders' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'E2E8F0']],
				],
				'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
			]);
			$sheet->getStyle("C{$dataStart}:C{$lastDataRow}")
				->getNumberFormat()
				->setFormatCode('@');
		}

		self::autoSizeColumns($sheet, count($headers));
		$sheet->getColumnDimension('A')->setWidth(5);
		$sheet->getColumnDimension('B')->setWidth(24);
		$sheet->getColumnDimension('C')->setWidth(14);
		$sheet->getColumnDimension('D')->setWidth(28);
		$sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
		$sheet->getPageSetup()->setFitToWidth(1);
		$sheet->getPageSetup()->setFitToHeight(0);

		return $spreadsheet;
	}

	/**
	 * @param array<string,mixed> $school
	 */
	private static function writeSchoolHeader(Worksheet $sheet, array $school, string $lastCol): int
	{
		$name = trim((string) ($school['name'] ?? 'School'));
		$slogan = trim((string) ($school['slogan'] ?? ''));
		$address = trim((string) ($school['address'] ?? ''));
		$pobox = trim((string) ($school['pobox'] ?? ''));
		$phone = trim((string) ($school['phone'] ?? ''));
		$email = trim((string) ($school['email'] ?? ''));
		$website = trim((string) ($school['website'] ?? ''));

		$contact = array_filter([
			$address !== '' ? $address : null,
			$pobox !== '' ? 'P.O. Box ' . $pobox : null,
			$phone !== '' ? 'Tel: ' . $phone : null,
			$email !== '' ? 'Email: ' . $email : null,
			$website !== '' ? $website : null,
		]);

		$sheet->getColumnDimension(self::LOGO_COL)->setWidth(10);
		$sheet->getColumnDimension('B')->setWidth(8);
		$sheet->getRowDimension(1)->setRowHeight(38);

		$sheet->mergeCells(self::TEXT_COL . "1:{$lastCol}1");
		$sheet->setCellValue(self::TEXT_COL . '1', strtoupper($name));
		$sheet->getStyle(self::TEXT_COL . "1:{$lastCol}1")->applyFromArray([
			'font' => ['bold' => true, 'size' => 20, 'color' => ['rgb' => self::BRAND]],
			'alignment' => [
				'horizontal' => Alignment::HORIZONTAL_LEFT,
				'vertical' => Alignment::VERTICAL_CENTER,
				'indent' => 1,
			],
		]);

		$row2 = 2;
		if ($slogan !== '') {
			$sheet->mergeCells(self::TEXT_COL . "{$row2}:{$lastCol}{$row2}");
			$sheet->setCellValue(self::TEXT_COL . $row2, $slogan);
			$sheet->getStyle(self::TEXT_COL . "{$row2}:{$lastCol}{$row2}")->applyFromArray([
				'font' => ['italic' => true, 'size' => 11, 'color' => ['rgb' => '475569']],
				'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'indent' => 1],
			]);
			$sheet->getRowDimension($row2)->setRowHeight(20);
			$row2++;
		}

		if ($contact !== []) {
			$sheet->mergeCells(self::TEXT_COL . "{$row2}:{$lastCol}{$row2}");
			$sheet->setCellValue(self::TEXT_COL . $row2, implode('  •  ', $contact));
			$sheet->getStyle(self::TEXT_COL . "{$row2}:{$lastCol}{$row2}")->applyFromArray([
				'font' => ['size' => 10, 'color' => ['rgb' => '64748B']],
				'alignment' => ['wrapText' => true, 'horizontal' => Alignment::HORIZONTAL_LEFT, 'indent' => 1],
			]);
			$sheet->getRowDimension($row2)->setRowHeight(22);
			$row2++;
		}

		self::placeLogo($sheet, $school);

		$sheet->getStyle(self::LOGO_COL . "1:{$lastCol}{$row2}")->applyFromArray([
			'borders' => [
				'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => self::BRAND]],
			],
		]);

		return $row2;
	}

	/**
	 * @param array<string,mixed> $school
	 */
	private static function placeLogo(Worksheet $sheet, array $school): void
	{
		$logoFile = trim((string) ($school['logo'] ?? ''));
		if ($logoFile === '') {
			return;
		}
		$logoPath = FCPATH . 'assets/images/logo/' . $logoFile;
		if (!is_file($logoPath)) {
			return;
		}
		try {
			$drawing = new Drawing();
			$drawing->setPath($logoPath);
			$drawing->setCoordinates(self::LOGO_COL . '1');
			$drawing->setHeight(58);
			$drawing->setOffsetX(4);
			$drawing->setOffsetY(8);
			$drawing->setWorksheet($sheet);
		} catch (\Throwable $e) {
			// skip broken logo
		}
	}

	private static function autoSizeColumns(Worksheet $sheet, int $count): void
	{
		for ($i = 1; $i <= $count; $i++) {
			$letter = self::columnLetter($i);
			$sheet->getColumnDimension($letter)->setAutoSize(true);
		}
	}

	private static function columnLetter(int $index): string
	{
		$letter = '';
		while ($index > 0) {
			$index--;
			$letter = chr(65 + ($index % 26)) . $letter;
			$index = intdiv($index, 26);
		}

		return $letter;
	}

	private static function formatIdentifier($value): string
	{
		if ($value === null || $value === '') {
			return '';
		}
		if (is_int($value) || is_float($value)) {
			return number_format((float) $value, 0, '', '');
		}
		$text = trim((string) $value);
		if ($text === '') {
			return '';
		}
		if (preg_match('/^[\d.eE+\-]+$/', $text) && stripos($text, 'e') !== false) {
			$num = (float) $text;
			if ($num > 0) {
				return number_format($num, 0, '', '');
			}
		}

		return $text;
	}
}
