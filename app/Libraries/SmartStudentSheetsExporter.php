<?php

namespace App\Libraries;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Protection;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Smart student list: one workbook, one sheet per class (Level+title).
 * Each sheet has a locked school header + columns: #, Names, Gender, Studying.
 */
class SmartStudentSheetsExporter
{
	private const BRAND = '012F6B';
	private const BRAND_LIGHT = 'E8F0FA';
	private const ALT_ROW = 'F7FAFD';
	private const LAST_COL = 'D';
	/** Extra columns give the school header room so contact text does not clip. */
	private const HEADER_LAST_COL = 'F';

	/** @return list<string> */
	public static function columnHeaders(): array
	{
		return ['#', 'Names', 'Gender', 'Studying'];
	}

	public static function exportFilename(string $schoolName, string $yearTitle = ''): string
	{
		$parts = array_filter([
			trim(preg_replace('/\s+/', ' ', $schoolName)),
			trim(preg_replace('/\s+/', ' ', $yearTitle)),
			'students by class',
		]);
		$base = $parts !== [] ? implode(' ', $parts) : 'students by class';
		$safe = preg_replace('/[\\\\\\/:*?"<>|]+/', '', $base);
		$safe = trim(preg_replace('/\s{2,}/', ' ', (string) $safe));
		$safe = $safe !== '' ? $safe : 'students_by_class';
		$safe = str_replace(' ', '_', $safe);

		return $safe . '_' . date('Y-m-d') . '.xlsx';
	}

	/**
	 * Excel sheet title (max 31 chars, unique, safe characters).
	 *
	 * @param array<string, int> $used
	 */
	public static function sheetTitle(string $className, array &$used): string
	{
		$base = str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $className);
		$base = trim((string) preg_replace('/\s+/', ' ', $base));
		if ($base === '') {
			$base = 'Class';
		}
		$base = function_exists('mb_substr') ? mb_substr($base, 0, 31) : substr($base, 0, 31);
		$title = $base;
		$i = 2;
		while (isset($used[strtolower($title)])) {
			$suffix = ' (' . $i . ')';
			$max = max(1, 31 - strlen($suffix));
			$cut = function_exists('mb_substr') ? mb_substr($base, 0, $max) : substr($base, 0, $max);
			$title = $cut . $suffix;
			$i++;
		}
		$used[strtolower($title)] = 1;

		return $title;
	}

	/**
	 * @param array<string,mixed> $student
	 * @return list<int|string>
	 */
	public static function rowValues(array $student, int $num): array
	{
		$names = trim(($student['fname'] ?? '') . ' ' . ($student['lname'] ?? ''));
		$gender = strtoupper(trim((string) ($student['sex'] ?? '')));
		if ($gender === '') {
			$gender = '-';
		}
		$modeRaw = $student['studying_mode'] ?? 0;
		if ((string) $modeRaw === '1' || $modeRaw === 1 || (is_string($modeRaw) && strcasecmp($modeRaw, 'Day') === 0)) {
			$mode = 'Day';
		} else {
			$mode = 'Boarding';
		}

		return [$num, $names !== '' ? $names : '-', $gender, $mode];
	}

	/**
	 * @param array<string,mixed> $school
	 * @param list<array{class:array<string,mixed>,students:list<array<string,mixed>>}> $sheets
	 */
	public static function build(
		array $school,
		array $sheets,
		string $yearTitle = '',
		string $termLabel = ''
	): Spreadsheet {
		$spreadsheet = new Spreadsheet();
		$usedTitles = [];
		$first = true;

		foreach ($sheets as $entry) {
			$class = $entry['class'] ?? [];
			$students = $entry['students'] ?? [];
			$className = ClassListExporter::className($class);
			$title = self::sheetTitle($className, $usedTitles);

			if ($first) {
				$sheet = $spreadsheet->getActiveSheet();
				$first = false;
			} else {
				$sheet = $spreadsheet->createSheet();
			}
			$sheet->setTitle($title);
			self::fillSheet($sheet, $school, $className, $students, $yearTitle, $termLabel);
		}

		if ($first) {
			$sheet = $spreadsheet->getActiveSheet();
			$sheet->setTitle('No classes');
			self::fillSheet($sheet, $school, 'No classes', [], $yearTitle, $termLabel);
		}

		$spreadsheet->setActiveSheetIndex(0);

		return $spreadsheet;
	}

	/**
	 * @param array<string,mixed> $school
	 * @param list<array<string,mixed>> $students
	 */
	private static function fillSheet(
		Worksheet $sheet,
		array $school,
		string $className,
		array $students,
		string $yearTitle,
		string $termLabel
	): void {
		$lastCol = self::LAST_COL;
		$headerSpan = self::HEADER_LAST_COL;
		$headerEnd = self::writeSchoolHeader($sheet, $school);

		$titleRow = $headerEnd + 2;
		$infoRow = $titleRow + 1;
		$headerRow = $infoRow + 2;
		$dataStart = $headerRow + 1;

		$sheet->mergeCells("A{$titleRow}:{$headerSpan}{$titleRow}");
		$sheet->setCellValue("A{$titleRow}", strtoupper($className));
		$sheet->getStyle("A{$titleRow}:{$headerSpan}{$titleRow}")->applyFromArray([
			'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => self::BRAND]],
			'alignment' => [
				'horizontal' => Alignment::HORIZONTAL_CENTER,
				'vertical' => Alignment::VERTICAL_CENTER,
				'wrapText' => true,
			],
		]);
		$sheet->getRowDimension($titleRow)->setRowHeight(26);

		$metaParts = array_filter([
			'Academic Year: ' . ($yearTitle !== '' ? $yearTitle : '—'),
			$termLabel !== '' ? $termLabel : null,
			'Students: ' . count($students),
			'Exported: ' . date('d M Y H:i'),
		]);
		$sheet->mergeCells("A{$infoRow}:{$headerSpan}{$infoRow}");
		$sheet->setCellValue("A{$infoRow}", implode('  |  ', $metaParts));
		$sheet->getStyle("A{$infoRow}:{$headerSpan}{$infoRow}")->applyFromArray([
			'font' => ['size' => 10, 'color' => ['rgb' => '334155']],
			'fill' => [
				'fillType' => Fill::FILL_SOLID,
				'startColor' => ['rgb' => self::BRAND_LIGHT],
			],
			'alignment' => [
				'horizontal' => Alignment::HORIZONTAL_LEFT,
				'vertical' => Alignment::VERTICAL_CENTER,
				'wrapText' => true,
			],
		]);
		$sheet->getRowDimension($infoRow)->setRowHeight(32);

		$col = 1;
		foreach (self::columnHeaders() as $header) {
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
			],
			'borders' => [
				'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']],
			],
		]);
		$sheet->getRowDimension($headerRow)->setRowHeight(24);
		$sheet->freezePane('A' . $dataStart);

		$row = $dataStart;
		$num = 1;
		foreach ($students as $student) {
			$values = self::rowValues($student, $num);
			$col = 1;
			foreach ($values as $value) {
				$sheet->setCellValueByColumnAndRow($col, $row, $value);
				$col++;
			}
			$sheet->getRowDimension($row)->setRowHeight(18);
			if ($num % 2 === 0) {
				$sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
					'fill' => [
						'fillType' => Fill::FILL_SOLID,
						'startColor' => ['rgb' => self::ALT_ROW],
					],
				]);
			}
			$row++;
			$num++;
		}

		$lastData = $row > $dataStart ? $row - 1 : $dataStart;
		if ($row > $dataStart) {
			$sheet->getStyle("A{$dataStart}:{$lastCol}{$lastData}")->applyFromArray([
				'borders' => [
					'allBorders' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'E2E8F0']],
				],
				'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
			]);
			$sheet->getStyle("A{$dataStart}:A{$lastData}")
				->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
			$sheet->getStyle("C{$dataStart}:D{$lastData}")
				->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
		} else {
			$sheet->mergeCells("A{$dataStart}:{$lastCol}{$dataStart}");
			$sheet->setCellValue("A{$dataStart}", 'No students in this class.');
			$sheet->getStyle("A{$dataStart}")->applyFromArray([
				'font' => ['italic' => true, 'color' => ['rgb' => '64748B']],
			]);
		}

		$sheet->getColumnDimension('A')->setWidth(8);
		$sheet->getColumnDimension('B')->setWidth(36);
		$sheet->getColumnDimension('C')->setWidth(12);
		$sheet->getColumnDimension('D')->setWidth(14);
		$sheet->getColumnDimension('E')->setWidth(18);
		$sheet->getColumnDimension('F')->setWidth(18);

		self::lockHeader($sheet, $headerRow, $dataStart, $lastData);
	}

	/**
	 * Freeze is already set; lock branding/meta/column headers so they cannot be edited.
	 * Student data rows stay editable.
	 */
	private static function lockHeader(
		Worksheet $sheet,
		int $headerRow,
		int $dataStart,
		int $lastData
	): void {
		$headerSpan = self::HEADER_LAST_COL;
		$lastCol = self::LAST_COL;

		$sheet->getStyle("A1:{$headerSpan}{$headerRow}")
			->getProtection()
			->setLocked(Protection::PROTECTION_PROTECTED);

		$unlockEnd = max($lastData, $dataStart + 40);
		$sheet->getStyle("A{$dataStart}:{$lastCol}{$unlockEnd}")
			->getProtection()
			->setLocked(Protection::PROTECTION_UNPROTECTED);

		$protection = $sheet->getProtection();
		$protection->setSheet(true);
		$protection->setPassword('');
		$protection->setSort(false);
		$protection->setInsertRows(false);
		$protection->setInsertColumns(false);
		$protection->setDeleteRows(false);
		$protection->setDeleteColumns(false);
		$protection->setFormatCells(false);
		$protection->setFormatRows(false);
		$protection->setFormatColumns(false);
	}

	/**
	 * @param array<string,mixed> $school
	 */
	private static function writeSchoolHeader(Worksheet $sheet, array $school): int
	{
		$headerSpan = self::HEADER_LAST_COL;
		$name = trim((string) ($school['name'] ?? 'School'));
		$slogan = trim((string) ($school['slogan'] ?? ''));
		$address = trim((string) ($school['address'] ?? ''));
		$pobox = trim((string) ($school['pobox'] ?? ''));
		$phone = trim((string) ($school['phone'] ?? ''));
		$email = trim((string) ($school['email'] ?? ''));
		$website = trim((string) ($school['website'] ?? ''));

		$line1 = array_filter([
			$address !== '' ? $address : null,
			$pobox !== '' ? 'P.O. Box ' . $pobox : null,
			$phone !== '' ? 'Tel: ' . $phone : null,
		]);
		$line2 = array_filter([
			$email !== '' ? 'Email: ' . $email : null,
			$website !== '' ? $website : null,
		]);

		$sheet->getRowDimension(1)->setRowHeight(28);
		$sheet->mergeCells("B1:{$headerSpan}1");
		$sheet->setCellValue('B1', strtoupper($name));
		$sheet->getStyle("B1:{$headerSpan}1")->applyFromArray([
			'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => self::BRAND]],
			'alignment' => [
				'horizontal' => Alignment::HORIZONTAL_LEFT,
				'vertical' => Alignment::VERTICAL_CENTER,
				'wrapText' => true,
				'indent' => 1,
			],
		]);

		$row = 2;
		if ($slogan !== '') {
			$sheet->mergeCells("B{$row}:{$headerSpan}{$row}");
			$sheet->setCellValue("B{$row}", $slogan);
			$sheet->getStyle("B{$row}:{$headerSpan}{$row}")->applyFromArray([
				'font' => ['italic' => true, 'size' => 10, 'color' => ['rgb' => '475569']],
				'alignment' => [
					'horizontal' => Alignment::HORIZONTAL_LEFT,
					'vertical' => Alignment::VERTICAL_CENTER,
					'wrapText' => true,
					'indent' => 1,
				],
			]);
			$sheet->getRowDimension($row)->setRowHeight(18);
			$row++;
		}

		if ($line1 !== []) {
			$sheet->mergeCells("B{$row}:{$headerSpan}{$row}");
			$sheet->setCellValue("B{$row}", implode('  •  ', $line1));
			$sheet->getStyle("B{$row}:{$headerSpan}{$row}")->applyFromArray([
				'font' => ['size' => 9, 'color' => ['rgb' => '64748B']],
				'alignment' => [
					'wrapText' => true,
					'horizontal' => Alignment::HORIZONTAL_LEFT,
					'vertical' => Alignment::VERTICAL_CENTER,
					'indent' => 1,
				],
			]);
			$sheet->getRowDimension($row)->setRowHeight(20);
			$row++;
		}

		if ($line2 !== []) {
			$sheet->mergeCells("B{$row}:{$headerSpan}{$row}");
			$sheet->setCellValue("B{$row}", implode('  •  ', $line2));
			$sheet->getStyle("B{$row}:{$headerSpan}{$row}")->applyFromArray([
				'font' => ['size' => 9, 'color' => ['rgb' => '64748B']],
				'alignment' => [
					'wrapText' => true,
					'horizontal' => Alignment::HORIZONTAL_LEFT,
					'vertical' => Alignment::VERTICAL_CENTER,
					'indent' => 1,
				],
			]);
			$sheet->getRowDimension($row)->setRowHeight(20);
			$row++;
		}

		self::placeLogo($sheet, $school);

		$underlineRow = max(1, $row - 1);
		$sheet->getStyle("A1:{$headerSpan}{$underlineRow}")->applyFromArray([
			'borders' => [
				'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => self::BRAND]],
			],
		]);

		return $underlineRow;
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
			$drawing->setCoordinates('A1');
			$drawing->setHeight(58);
			$drawing->setOffsetX(4);
			$drawing->setOffsetY(6);
			$drawing->setWorksheet($sheet);
		} catch (\Throwable $e) {
			// skip broken logo
		}
	}
}
