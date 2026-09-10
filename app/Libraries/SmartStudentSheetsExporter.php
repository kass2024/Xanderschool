<?php

namespace App\Libraries;

use App\Controllers\Home;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Smart student list: one workbook, one sheet per class (Level+title).
 * Columns: #, Names, Gender, Studying.
 */
class SmartStudentSheetsExporter
{
	private const BRAND = '012F6B';
	private const ALT_ROW = 'F7FAFD';

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
		$base = preg_replace('/[\\\\\\\/\?\*\[\]:]+/', ' ', $className);
		$base = trim(preg_replace('/\s+/', ' ', (string) $base));
		if ($base === '') {
			$base = 'Class';
		}
		$base = mb_substr($base, 0, 31);
		$title = $base;
		$i = 2;
		while (isset($used[strtolower($title)])) {
			$suffix = ' (' . $i . ')';
			$title = mb_substr($base, 0, max(1, 31 - strlen($suffix))) . $suffix;
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
			$gender = '—';
		}
		$mode = Home::ModeToStr($student['studying_mode'] ?? 0);

		return [$num, $names !== '' ? $names : '—', $gender, $mode];
	}

	/**
	 * @param list<array{class:array<string,mixed>,students:list<array<string,mixed>>}> $sheets
	 */
	public static function build(array $sheets): Spreadsheet
	{
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
			self::fillSheet($sheet, $className, $students);
		}

		if ($first) {
			$sheet = $spreadsheet->getActiveSheet();
			$sheet->setTitle('No classes');
			self::fillSheet($sheet, 'No classes', []);
		}

		$spreadsheet->setActiveSheetIndex(0);

		return $spreadsheet;
	}

	/**
	 * @param list<array<string,mixed>> $students
	 */
	private static function fillSheet(Worksheet $sheet, string $className, array $students): void
	{
		$headers = self::columnHeaders();
		$lastCol = 'D';

		$sheet->mergeCells("A1:{$lastCol}1");
		$sheet->setCellValue('A1', $className);
		$sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
			'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => self::BRAND]],
			'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
		]);
		$sheet->getRowDimension(1)->setRowHeight(24);

		$sheet->mergeCells("A2:{$lastCol}2");
		$sheet->setCellValue('A2', 'Students: ' . count($students) . '   |   Exported: ' . date('d M Y H:i'));
		$sheet->getStyle("A2:{$lastCol}2")->applyFromArray([
			'font' => ['size' => 10, 'color' => ['rgb' => '64748B']],
		]);

		$headerRow = 4;
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
			],
			'borders' => [
				'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']],
			],
		]);
		$sheet->getRowDimension($headerRow)->setRowHeight(22);
		$sheet->freezePane('A5');

		$row = $headerRow + 1;
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
			$row++;
			$num++;
		}

		if ($row > $headerRow + 1) {
			$lastData = $row - 1;
			$sheet->getStyle('A' . ($headerRow + 1) . ":{$lastCol}{$lastData}")->applyFromArray([
				'borders' => [
					'allBorders' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'E2E8F0']],
				],
				'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
			]);
			$sheet->getStyle('A' . ($headerRow + 1) . ":A{$lastData}")
				->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
			$sheet->getStyle('C' . ($headerRow + 1) . ":D{$lastData}")
				->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
		} else {
			$sheet->mergeCells("A5:{$lastCol}5");
			$sheet->setCellValue('A5', 'No students in this class.');
			$sheet->getStyle('A5')->getFont()->setItalic(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('64748B'));
		}

		$sheet->getColumnDimension('A')->setWidth(5);
		$sheet->getColumnDimension('B')->setWidth(32);
		$sheet->getColumnDimension('C')->setWidth(10);
		$sheet->getColumnDimension('D')->setWidth(14);
	}
}
