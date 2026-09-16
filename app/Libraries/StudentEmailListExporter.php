<?php

namespace App\Libraries;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Student email/password list for class distribution.
 */
class StudentEmailListExporter
{
	private const BRAND = '012F6B';
	private const BRAND_LIGHT = 'E8F0FA';
	private const ALT_ROW = 'F7FAFD';
	private const LAST_COL = 'F';

	/** @return list<string> */
	public static function columnHeaders(): array
	{
		return ['#', 'Class', 'Reg No', 'Names', 'Email', 'Password'];
	}

	public static function exportFilename(string $schoolName, string $yearTitle = ''): string
	{
		$parts = array_filter([
			trim(preg_replace('/\s+/', ' ', $schoolName)),
			trim(preg_replace('/\s+/', ' ', $yearTitle)),
			'student emails',
		]);
		$base = $parts !== [] ? implode(' ', $parts) : 'student emails';
		$safe = preg_replace('/[\\\\\\/:*?"<>|]+/', '', $base);
		$safe = trim(preg_replace('/\s{2,}/', ' ', (string) $safe));
		$safe = $safe !== '' ? $safe : 'student_emails';
		$safe = str_replace(' ', '_', $safe);

		return $safe . '_' . date('Y-m-d') . '.xlsx';
	}

	/**
	 * @param array<string,mixed> $school
	 * @param list<array{class:array<string,mixed>,students:list<array<string,mixed>>}> $sheets
	 */
	public static function build(array $school, array $sheets, string $yearTitle = ''): Spreadsheet
	{
		$spreadsheet = new Spreadsheet();
		$all = [];
		foreach ($sheets as $entry) {
			$class = $entry['class'] ?? [];
			$className = SmartStudentSheetsExporter::classLabel($class);
			foreach ($entry['students'] ?? [] as $student) {
				$student['_class_label'] = $className;
				$all[] = $student;
			}
		}

		$sheet = $spreadsheet->getActiveSheet();
		$sheet->setTitle('All emails');
		self::fillSheet($sheet, $school, 'All classes', $all, $yearTitle);

		$used = ['all emails' => 1];
		foreach ($sheets as $entry) {
			$class = $entry['class'] ?? [];
			$students = $entry['students'] ?? [];
			$className = SmartStudentSheetsExporter::classLabel($class);
			$title = SmartStudentSheetsExporter::sheetTitle($className, $used);
			$perClass = [];
			foreach ($students as $student) {
				$student['_class_label'] = $className;
				$perClass[] = $student;
			}
			$newSheet = $spreadsheet->createSheet();
			$newSheet->setTitle($title);
			self::fillSheet($newSheet, $school, $className, $perClass, $yearTitle);
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
		string $title,
		array $students,
		string $yearTitle
	): void {
		$lastCol = self::LAST_COL;
		$name = trim((string) ($school['name'] ?? 'School'));
		$sheet->mergeCells("A1:{$lastCol}1");
		$sheet->setCellValue('A1', strtoupper($name));
		$sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
			'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => self::BRAND]],
			'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
		]);
		$sheet->mergeCells("A2:{$lastCol}2");
		$sheet->setCellValue('A2', 'STUDENT EMAIL LIST — ' . strtoupper($title));
		$sheet->getStyle("A2:{$lastCol}2")->applyFromArray([
			'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => self::BRAND]],
			'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
		]);
		$sheet->mergeCells("A3:{$lastCol}3");
		$sheet->setCellValue('A3', implode('  |  ', array_filter([
			$yearTitle !== '' ? 'Academic Year: ' . $yearTitle : null,
			'Students: ' . count($students),
			'Webmail: https://wisdomschoolrwanda.com:2096',
			'Exported: ' . date('d M Y H:i'),
		])));
		$sheet->getStyle("A3:{$lastCol}3")->applyFromArray([
			'font' => ['size' => 10, 'color' => ['rgb' => '334155']],
			'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::BRAND_LIGHT]],
		]);

		$headerRow = 5;
		$col = 1;
		foreach (self::columnHeaders() as $header) {
			$sheet->setCellValueByColumnAndRow($col, $headerRow, $header);
			$col++;
		}
		$sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
			'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
			'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::BRAND]],
			'alignment' => [
				'horizontal' => Alignment::HORIZONTAL_CENTER,
				'vertical' => Alignment::VERTICAL_CENTER,
			],
		]);
		$sheet->freezePane('A6');

		$row = 6;
		$num = 1;
		foreach ($students as $student) {
			$names = trim(($student['fname'] ?? '') . ' ' . ($student['lname'] ?? ''));
			$values = [
				$num,
				(string) ($student['_class_label'] ?? ''),
				(string) ($student['regno'] ?? ''),
				$names !== '' ? $names : '-',
				trim((string) ($student['email'] ?? '')),
				trim((string) ($student['email_password'] ?? '')),
			];
			$col = 1;
			foreach ($values as $value) {
				$sheet->setCellValueByColumnAndRow($col, $row, $value);
				$col++;
			}
			if ($num % 2 === 0) {
				$sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
					'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::ALT_ROW]],
				]);
			}
			$row++;
			$num++;
		}

		$lastData = $row > 6 ? $row - 1 : 6;
		if ($row > 6) {
			$sheet->getStyle("A6:{$lastCol}{$lastData}")->applyFromArray([
				'borders' => [
					'allBorders' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'E2E8F0']],
				],
			]);
		} else {
			$sheet->mergeCells("A6:{$lastCol}6");
			$sheet->setCellValue('A6', 'No student emails in this class yet.');
		}

		$sheet->getColumnDimension('A')->setWidth(8);
		$sheet->getColumnDimension('B')->setWidth(22);
		$sheet->getColumnDimension('C')->setWidth(14);
		$sheet->getColumnDimension('D')->setWidth(32);
		$sheet->getColumnDimension('E')->setWidth(38);
		$sheet->getColumnDimension('F')->setWidth(18);
		$sheet->getRowDimension(1)->setRowHeight(24);
		$sheet->getRowDimension(2)->setRowHeight(22);
		$sheet->getRowDimension(3)->setRowHeight(28);
		$sheet->getRowDimension($headerRow)->setRowHeight(24);
	}
}
