<?php

namespace App\Libraries;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Excel handover list of students who already have an assigned card.
 * School header + empty signature column so the student can sign on receipt.
 */
class CardAssignedListExporter
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
			'Registration No',
			"Student's Name",
			'Class',
			'Studying mode',
			'Card UID',
			'Date received',
			'Student signature',
		];
	}

	public static function lastColumn(): string
	{
		return self::columnLetter(count(self::columnHeaders()));
	}

	public static function exportFilename(string $schoolName, string $ext = 'xlsx'): string
	{
		$base = trim(preg_replace('/\s+/', ' ', $schoolName));
		$base = $base !== '' ? $base . ' assigned cards' : 'assigned cards';
		$safe = preg_replace('/[\\\\\\/:*?"<>|]+/', '', $base);
		$safe = trim(preg_replace('/\s{2,}/', ' ', (string) $safe));
		$safe = $safe !== '' ? $safe : 'assigned_cards';
		$safe = str_replace(' ', '_', $safe);

		return $safe . '_' . date('Y-m-d') . '.' . ltrim($ext, '.');
	}

	/**
	 * @param array<string,mixed> $student
	 * @return list<int|string>
	 */
	public static function rowValues(array $student, int $num): array
	{
		$card = trim((string) ($student['card_number'] ?? $student['card'] ?? ''));

		return [
			$num,
			trim((string) ($student['regno'] ?? '')),
			trim((string) ($student['name'] ?? $student['stdnames'] ?? '')),
			trim((string) ($student['class'] ?? '')),
			trim((string) ($student['mode_label'] ?? '')),
			$card !== '' ? $card : '—',
			'',
			'',
		];
	}

	/**
	 * @param array<string,mixed> $school
	 * @param list<array<string,mixed>> $students
	 */
	public static function buildExcel(
		array $school,
		array $students,
		string $yearTitle = '',
		string $termLabel = '',
		string $filterLabel = ''
	): Spreadsheet {
		$spreadsheet = new Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
		$sheet->setTitle('Assigned cards');
		$lastCol = self::lastColumn();

		$headerEndRow = self::writeSchoolHeader($sheet, $school, $lastCol);
		$titleRow = $headerEndRow + 2;
		$infoRow = $titleRow + 1;
		$noteRow = $infoRow + 1;
		$headerRow = $noteRow + 2;
		$dataStart = $headerRow + 1;

		$sheet->mergeCells(self::TEXT_COL . "{$titleRow}:{$lastCol}{$titleRow}");
		$sheet->setCellValue(self::TEXT_COL . $titleRow, 'STUDENT CARD HANDOVER LIST');
		$sheet->getStyle(self::TEXT_COL . "{$titleRow}:{$lastCol}{$titleRow}")->applyFromArray([
			'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => self::BRAND]],
			'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
		]);
		$sheet->getRowDimension($titleRow)->setRowHeight(26);

		$metaParts = array_filter([
			'Academic Year: ' . ($yearTitle !== '' ? $yearTitle : '—'),
			$termLabel !== '' ? $termLabel : null,
			$filterLabel !== '' ? $filterLabel : null,
			'Exported: ' . date('d M Y H:i'),
			'Assigned cards: ' . count($students),
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

		$sheet->mergeCells('A' . $noteRow . ":{$lastCol}{$noteRow}");
		$sheet->setCellValue('A' . $noteRow, 'Each student must sign to confirm that they received their student card.');
		$sheet->getStyle('A' . $noteRow . ":{$lastCol}{$noteRow}")->applyFromArray([
			'font' => ['italic' => true, 'size' => 10, 'color' => ['rgb' => '475569']],
		]);

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
		$sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($headerRow, $headerRow);

		$row = $dataStart;
		$num = 1;
		foreach ($students as $student) {
			$values = self::rowValues($student, $num);
			$col = 1;
			foreach ($values as $value) {
				$sheet->setCellValueByColumnAndRow($col, $row, $value);
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
			$sheet->getRowDimension($row)->setRowHeight(32);
			$row++;
			$num++;
		}

		if ($row > $dataStart) {
			$lastDataRow = $row - 1;
			$sheet->getStyle("A{$dataStart}:{$lastCol}{$lastDataRow}")->applyFromArray([
				'borders' => [
					'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']],
				],
				'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
			]);
			$sheet->getStyle("H{$dataStart}:H{$lastDataRow}")->applyFromArray([
				'borders' => [
					'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '64748B']],
				],
			]);
			$sheet->getStyle("G{$dataStart}:G{$lastDataRow}")->applyFromArray([
				'borders' => [
					'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '64748B']],
				],
			]);
		} else {
			$sheet->mergeCells("A{$dataStart}:{$lastCol}{$dataStart}");
			$sheet->setCellValue("A{$dataStart}", 'No students with an assigned card match the selected criteria.');
			$sheet->getStyle("A{$dataStart}:{$lastCol}{$dataStart}")->applyFromArray([
				'font' => ['italic' => true, 'color' => ['rgb' => '64748B']],
				'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
			]);
			$row = $dataStart + 1;
		}

		$signRow = $row + 2;
		$sheet->mergeCells("A{$signRow}:D{$signRow}");
		$sheet->setCellValue("A{$signRow}", 'Distributed by: ________________________________');
		$sheet->mergeCells("E{$signRow}:F{$signRow}");
		$sheet->setCellValue("E{$signRow}", 'Signature: ________________');
		$sheet->mergeCells("G{$signRow}:H{$signRow}");
		$sheet->setCellValue("G{$signRow}", 'Date: ________________');
		$sheet->getRowDimension($signRow)->setRowHeight(28);
		$sheet->getStyle("A{$signRow}:H{$signRow}")->applyFromArray([
			'font' => ['size' => 11, 'color' => ['rgb' => '1E293B']],
		]);

		$sheet->getColumnDimension('A')->setWidth(6);
		$sheet->getColumnDimension('B')->setWidth(16);
		$sheet->getColumnDimension('C')->setWidth(28);
		$sheet->getColumnDimension('D')->setWidth(18);
		$sheet->getColumnDimension('E')->setWidth(16);
		$sheet->getColumnDimension('F')->setWidth(18);
		$sheet->getColumnDimension('G')->setWidth(16);
		$sheet->getColumnDimension('H')->setWidth(24);
		$sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
		$sheet->getPageSetup()->setFitToWidth(1);
		$sheet->getPageSetup()->setFitToHeight(0);
		$sheet->getPageSetup()->setHorizontalCentered(true);
		$sheet->getHeaderFooter()->setOddFooter('&LStudent must sign on receipt  &C&P / &N  &RPrinted by Xander School');

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
}
