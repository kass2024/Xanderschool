<?php

namespace App\Libraries;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Protection;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
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
	private const FONT = 'Calibri';
	private const DEFAULT_WEBMAIL_HOST = 'wisdomschoolrwanda.com';

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
	 */
	public static function webmailUrl(array $school = []): string
	{
		return 'https://' . self::DEFAULT_WEBMAIL_HOST . ':2096';
	}

	/**
	 * @param array<string,mixed> $school
	 * @param list<array{class:array<string,mixed>,students:list<array<string,mixed>>}> $sheets
	 */
	public static function build(array $school, array $sheets, string $yearTitle = ''): Spreadsheet
	{
		$spreadsheet = new Spreadsheet();
		$spreadsheet->getDefaultStyle()->getFont()->setName(self::FONT)->setSize(11);
		$spreadsheet->getDefaultStyle()->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

		$webmail = self::webmailUrl($school);
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
		self::fillSheet($sheet, $school, 'All classes', $all, $yearTitle, $webmail);

		$used = ['all emails' => 1];
		foreach ($sheets as $entry) {
			$class = $entry['class'] ?? [];
			$students = $entry['students'] ?? [];
			if ($students === []) {
				continue;
			}
			$className = SmartStudentSheetsExporter::classLabel($class);
			$title = SmartStudentSheetsExporter::sheetTitle($className, $used);
			$perClass = [];
			foreach ($students as $student) {
				$student['_class_label'] = $className;
				$perClass[] = $student;
			}
			$newSheet = $spreadsheet->createSheet();
			$newSheet->setTitle($title);
			self::fillSheet($newSheet, $school, $className, $perClass, $yearTitle, $webmail);
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
		string $yearTitle,
		string $webmail
	): void {
		$lastCol = self::LAST_COL;
		$headerEnd = SmartStudentSheetsExporter::writeSchoolHeader($sheet, $school);

		$titleRow = $headerEnd + 2;
		$infoRow = $titleRow + 1;
		$webRow = $infoRow + 1;
		$headerRow = $webRow + 2;
		$dataStart = $headerRow + 1;

		$sheet->mergeCells("A{$titleRow}:{$lastCol}{$titleRow}");
		$sheet->setCellValue("A{$titleRow}", 'STUDENT EMAIL LIST  ·  ' . strtoupper($title));
		$sheet->getStyle("A{$titleRow}:{$lastCol}{$titleRow}")->applyFromArray([
			'font' => ['name' => self::FONT, 'bold' => true, 'size' => 14, 'color' => ['rgb' => self::BRAND]],
			'alignment' => [
				'horizontal' => Alignment::HORIZONTAL_CENTER,
				'vertical' => Alignment::VERTICAL_CENTER,
			],
		]);
		$sheet->getRowDimension($titleRow)->setRowHeight(26);

		$sheet->mergeCells("A{$infoRow}:{$lastCol}{$infoRow}");
		$sheet->setCellValue("A{$infoRow}", implode('   |   ', array_filter([
			$yearTitle !== '' ? 'Academic Year: ' . $yearTitle : null,
			'Students: ' . count($students),
			'Exported: ' . date('d M Y H:i'),
		])));
		$sheet->getStyle("A{$infoRow}:{$lastCol}{$infoRow}")->applyFromArray([
			'font' => ['name' => self::FONT, 'size' => 10, 'color' => ['rgb' => '334155']],
			'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::BRAND_LIGHT]],
			'alignment' => [
				'horizontal' => Alignment::HORIZONTAL_LEFT,
				'vertical' => Alignment::VERTICAL_CENTER,
				'indent' => 1,
			],
		]);
		$sheet->getRowDimension($infoRow)->setRowHeight(22);

		$sheet->mergeCells("A{$webRow}:{$lastCol}{$webRow}");
		$sheet->setCellValue("A{$webRow}", 'Webmail: ' . $webmail);
		$sheet->getCell("A{$webRow}")->getHyperlink()->setUrl($webmail);
		$sheet->getStyle("A{$webRow}:{$lastCol}{$webRow}")->applyFromArray([
			'font' => [
				'name' => self::FONT,
				'bold' => true,
				'size' => 11,
				'color' => ['rgb' => '0563C1'],
				'underline' => Font::UNDERLINE_SINGLE,
			],
			'alignment' => [
				'horizontal' => Alignment::HORIZONTAL_LEFT,
				'vertical' => Alignment::VERTICAL_CENTER,
				'indent' => 1,
			],
		]);
		$sheet->getRowDimension($webRow)->setRowHeight(22);

		$col = 1;
		$maxLens = [];
		foreach (self::columnHeaders() as $header) {
			$sheet->setCellValueByColumnAndRow($col, $headerRow, $header);
			$maxLens[$col] = self::textLen($header);
			$col++;
		}
		$sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
			'font' => ['name' => self::FONT, 'bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
			'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::BRAND]],
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
			$names = preg_replace('/\s+/', ' ', trim(($student['fname'] ?? '') . ' ' . ($student['lname'] ?? ''))) ?? '';
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
				$maxLens[$col] = max($maxLens[$col] ?? 0, self::textLen((string) $value));
				$col++;
			}
			$sheet->getRowDimension($row)->setRowHeight(20);
			if ($num % 2 === 0) {
				$sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
					'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::ALT_ROW]],
				]);
			}
			$row++;
			$num++;
		}

		$lastData = $row > $dataStart ? $row - 1 : $dataStart;
		if ($row > $dataStart) {
			$sheet->getStyle("A{$dataStart}:{$lastCol}{$lastData}")->applyFromArray([
				'font' => ['name' => self::FONT, 'size' => 11, 'color' => ['rgb' => '0F172A']],
				'borders' => [
					'allBorders' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'E2E8F0']],
				],
				'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => false],
			]);
			$sheet->getStyle("A{$dataStart}:A{$lastData}")
				->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
			$sheet->getStyle("C{$dataStart}:C{$lastData}")
				->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
			$sheet->getStyle("F{$dataStart}:F{$lastData}")->applyFromArray([
				'font' => ['name' => 'Consolas', 'size' => 11, 'color' => ['rgb' => '0F172A']],
			]);
		} else {
			$sheet->mergeCells("A{$dataStart}:{$lastCol}{$dataStart}");
			$sheet->setCellValue("A{$dataStart}", 'No student emails in this class yet.');
			$sheet->getStyle("A{$dataStart}")->applyFromArray([
				'font' => ['name' => self::FONT, 'italic' => true, 'color' => ['rgb' => '64748B']],
			]);
		}

		self::fitColumns($sheet, $maxLens);
		self::lockHeader($sheet, $headerRow, $dataStart, $lastData);

		$page = $sheet->getPageSetup();
		$page->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
		$page->setFitToPage(true);
		$page->setFitToWidth(1);
		$page->setFitToHeight(0);
		$page->setRowsToRepeatAtTopByStartAndEnd(1, $headerRow);
		$sheet->getPageMargins()->setTop(0.4);
		$sheet->getPageMargins()->setBottom(0.4);
		$sheet->getPageMargins()->setLeft(0.4);
		$sheet->getPageMargins()->setRight(0.4);
		$sheet->getHeaderFooter()->setOddFooter('&L' . $webmail . '&RPage &P of &N');
	}

	/** @param array<int,int> $maxLens */
	private static function fitColumns(Worksheet $sheet, array $maxLens): void
	{
		$caps = [
			1 => [6, 8],
			2 => [12, 22],
			3 => [12, 16],
			4 => [22, 40],
			5 => [36, 52],
			6 => [16, 24],
		];
		foreach ($caps as $col => [$min, $max]) {
			$len = $maxLens[$col] ?? $min;
			$width = min($max, max($min, $len * 1.12 + 2.4));
			$sheet->getColumnDimensionByColumn($col)->setWidth($width);
			$sheet->getColumnDimensionByColumn($col)->setAutoSize(false);
		}
	}

	private static function lockHeader(
		Worksheet $sheet,
		int $headerRow,
		int $dataStart,
		int $lastData
	): void {
		$lastCol = self::LAST_COL;
		$sheet->getStyle("A1:{$lastCol}{$headerRow}")
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
		$protection->setFormatColumns(true);
	}

	private static function textLen(string $text): int
	{
		$text = trim($text);
		if (function_exists('mb_strlen')) {
			return (int) mb_strlen($text);
		}
		return strlen($text);
	}
}
