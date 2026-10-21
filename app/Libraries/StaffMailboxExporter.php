<?php

namespace App\Libraries;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * School mailbox list: webmail link, address, and the mailbox password.
 * The sign-in email is not included.
 */
class StaffMailboxExporter
{
	private const BRAND = '012F6B';
	private const BRAND_LIGHT = 'E8F0FA';
	private const ALT_ROW = 'F7FAFD';
	private const LAST_COL = 'E';
	private const FONT = 'Calibri';
	public const WEBMAIL = 'https://wisdomschoolrwanda.com:2096';

	public static function exportFilename(string $schoolName): string
	{
		$base = trim(preg_replace('/\s+/', ' ', $schoolName));
		$base = $base !== '' ? $base . ' staff mailbox' : 'staff mailbox';
		$safe = preg_replace('/[\\\\\\/:*?"<>|]+/', '', $base);
		$safe = trim(preg_replace('/\s{2,}/', ' ', (string) $safe));
		$safe = str_replace(' ', '_', $safe !== '' ? $safe : 'staff_mailbox');

		return $safe . '_' . date('Y-m-d') . '.xlsx';
	}

	/**
	 * @param array<string,mixed> $school
	 * @param list<array<string,mixed>> $staffs
	 */
	public static function build(array $school, array $staffs, string $yearTitle = ''): Spreadsheet
	{
		$spreadsheet = new Spreadsheet();
		$spreadsheet->getDefaultStyle()->getFont()->setName(self::FONT)->setSize(11);
		$spreadsheet->getDefaultStyle()->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
		$sheet = $spreadsheet->getActiveSheet();
		$sheet->setTitle('Mailbox');
		self::fillSheet($sheet, $school, $staffs, $yearTitle);

		return $spreadsheet;
	}

	/**
	 * @param array<string,mixed> $school
	 * @param list<array<string,mixed>> $staffs
	 */
	private static function fillSheet(Worksheet $sheet, array $school, array $staffs, string $yearTitle): void
	{
		$lastCol = self::LAST_COL;
		$headerEnd = SmartStudentSheetsExporter::writeSchoolHeader($sheet, $school);
		$titleRow = $headerEnd + 2;
		$infoRow = $titleRow + 1;
		$headerRow = $infoRow + 2;
		$dataStart = $headerRow + 1;
		$webmail = self::WEBMAIL;

		$sheet->mergeCells("A{$titleRow}:{$lastCol}{$titleRow}");
		$sheet->setCellValue("A{$titleRow}", 'STAFF MAILBOX LIST');
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
			'Staff: ' . count($staffs),
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

		$headers = ['#', 'Names', 'Login link', 'Email', 'Default password'];
		$maxLens = [];
		foreach ($headers as $index => $header) {
			$col = $index + 1;
			$sheet->setCellValueByColumnAndRow($col, $headerRow, $header);
			$maxLens[$col] = self::textLen($header);
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
		foreach ($staffs as $staff) {
			$email = trim((string) ($staff['school_email'] ?? ''));
			if ($email === '') {
				continue;
			}
			$names = preg_replace('/\s+/', ' ', trim(($staff['fname'] ?? '') . ' ' . ($staff['lname'] ?? ''))) ?? '';
			$password = trim((string) ($staff['school_email_password'] ?? ''));
			$values = [
				(string) $num,
				$names !== '' ? $names : '-',
				$webmail,
				$email,
				$password,
			];
			foreach ($values as $index => $value) {
				$col = $index + 1;
				$cell = $sheet->getCellByColumnAndRow($col, $row);
				$cell->setValueExplicit($value, DataType::TYPE_STRING);
				$maxLens[$col] = max($maxLens[$col] ?? 0, self::textLen($value));
			}
			$sheet->getCellByColumnAndRow(3, $row)->getHyperlink()->setUrl($webmail);
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
			$sheet->getStyle("E{$dataStart}:E{$lastData}")->applyFromArray([
				'font' => ['name' => 'Consolas', 'size' => 11, 'color' => ['rgb' => '0F172A']],
			]);
			$sheet->getStyle("C{$dataStart}:C{$lastData}")->applyFromArray([
				'font' => [
					'name' => self::FONT,
					'size' => 11,
					'color' => ['rgb' => '0563C1'],
					'underline' => Font::UNDERLINE_SINGLE,
				],
			]);
		} else {
			$sheet->mergeCells("A{$dataStart}:{$lastCol}{$dataStart}");
			$sheet->setCellValue("A{$dataStart}", 'No school mailboxes for this school yet.');
			$sheet->getStyle("A{$dataStart}")->applyFromArray([
				'font' => ['name' => self::FONT, 'italic' => true, 'color' => ['rgb' => '64748B']],
			]);
		}

		$caps = [
			1 => [6, 8],
			2 => [18, 36],
			3 => [28, 42],
			4 => [28, 46],
			5 => [16, 24],
		];
		foreach ($caps as $col => [$min, $max]) {
			$len = $maxLens[$col] ?? $min;
			$width = min($max, max($min, $len * 1.12 + 2.4));
			$sheet->getColumnDimensionByColumn($col)->setWidth($width);
		}

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
		$sheet->getProtection()->setSheet(false);
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
