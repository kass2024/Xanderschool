<?php

namespace App\Libraries;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Per-dormitory student list (Excel). One summary sheet, then one sheet per dormitory.
 */
class HostelDormitoryReport
{
	private const BRAND = '012F6B';
	private const BRAND_LIGHT = 'E8EEF8';
	private const ALT_ROW = 'F8FAFC';

	/**
	 * @param array<string,mixed> $school
	 * @param list<array<string,mixed>> $dorms
	 */
	public static function buildExcel(array $school, array $dorms, string $yearTitle): Spreadsheet
	{
		$spreadsheet = new Spreadsheet();
		$spreadsheet->getProperties()
			->setCreator((string) ($school['name'] ?? 'School'))
			->setTitle('Dormitory student lists')
			->setSubject($yearTitle);

		self::writeSummary($spreadsheet->getActiveSheet(), $school, $dorms, $yearTitle);

		$used = ['summary' => true];
		foreach ($dorms as $dorm) {
			$sheet = $spreadsheet->createSheet();
			$title = self::uniqueSheetTitle((string) ($dorm['name'] ?? 'Dormitory'), $used);
			$sheet->setTitle($title);
			self::writeDormitory($sheet, $school, $dorm, $yearTitle);
		}

		$spreadsheet->setActiveSheetIndex(0);
		return $spreadsheet;
	}

	public static function exportFilename(string $schoolName, string $yearTitle, string $ext): string
	{
		$slug = preg_replace('/[^A-Za-z0-9]+/', '_', trim($schoolName)) ?: 'School';
		$year = preg_replace('/[^A-Za-z0-9]+/', '_', trim($yearTitle)) ?: 'year';
		$ext = $ext === 'pdf' ? 'pdf' : 'xlsx';
		return $slug . '_dormitory_lists_' . $year . '.' . $ext;
	}

	/**
	 * @param list<array<string,mixed>> $dorms
	 */
	private static function writeSummary(Worksheet $sheet, array $school, array $dorms, string $yearTitle): void
	{
		$sheet->setTitle('Summary');
		$schoolName = trim((string) ($school['name'] ?? 'School'));
		$sheet->mergeCells('A1:E1');
		$sheet->setCellValue('A1', $schoolName !== '' ? $schoolName : 'School');
		$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB(self::BRAND);
		$sheet->getRowDimension(1)->setRowHeight(24);

		$total = 0;
		foreach ($dorms as $dorm) {
			$total += count($dorm['students'] ?? []);
		}
		$meta = array_filter([
			'Dormitory student lists',
			$yearTitle !== '' ? $yearTitle : null,
			count($dorms) . ' dormitor' . (count($dorms) === 1 ? 'y' : 'ies'),
			$total . ' student' . ($total === 1 ? '' : 's'),
		]);
		$sheet->mergeCells('A2:E2');
		$sheet->setCellValue('A2', implode('  ·  ', $meta));
		$sheet->getStyle('A2:E2')->applyFromArray([
			'font' => ['size' => 10, 'color' => ['rgb' => '334155']],
			'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::BRAND_LIGHT]],
		]);
		$sheet->getRowDimension(2)->setRowHeight(22);

		$sheet->fromArray(['Dormitory', 'Gender', 'Students', 'Beds', 'Free beds'], null, 'A4');
		self::headerRow($sheet, 'A4:E4');
		$sheet->freezePane('A5');

		$row = 5;
		$n = 0;
		foreach ($dorms as $dorm) {
			$sheet->setCellValue('A' . $row, (string) ($dorm['name'] ?? ''));
			$sheet->setCellValue('B' . $row, (string) ($dorm['gender_label'] ?? ''));
			$sheet->setCellValue('C' . $row, (int) ($dorm['occupied'] ?? count($dorm['students'] ?? [])));
			$sheet->setCellValue('D' . $row, (int) ($dorm['max_beds'] ?? 0));
			$sheet->setCellValue('E' . $row, (int) ($dorm['free_beds'] ?? 0));
			if ($n % 2 === 1) {
				$sheet->getStyle("A{$row}:E{$row}")->getFill()
					->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::ALT_ROW);
			}
			$n++;
			$row++;
		}
		$last = max(5, $row - 1);
		$sheet->getStyle("A4:E{$last}")->applyFromArray([
			'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
			'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
		]);
		foreach (['A' => 28, 'B' => 14, 'C' => 14, 'D' => 12, 'E' => 14] as $col => $width) {
			$sheet->getColumnDimension($col)->setWidth($width);
		}
		$sheet->getPageSetup()->setFitToPage(true)->setFitToWidth(1)->setFitToHeight(0);
		$sheet->getHeaderFooter()->setOddFooter('&L' . $schoolName . '&R&P / &N');
	}

	/**
	 * @param array<string,mixed> $dorm
	 */
	private static function writeDormitory(Worksheet $sheet, array $school, array $dorm, string $yearTitle): void
	{
		$schoolName = trim((string) ($school['name'] ?? 'School'));
		$name = (string) ($dorm['name'] ?? 'Dormitory');
		$students = $dorm['students'] ?? [];
		$occupied = (int) ($dorm['occupied'] ?? count($students));
		$beds = (int) ($dorm['max_beds'] ?? 0);

		$sheet->mergeCells('A1:E1');
		$sheet->setCellValue('A1', $schoolName !== '' ? $schoolName : 'School');
		$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB(self::BRAND);
		$sheet->getRowDimension(1)->setRowHeight(22);

		$meta = array_filter([
			$name,
			(string) ($dorm['gender_label'] ?? ''),
			$yearTitle !== '' ? $yearTitle : null,
			$occupied . ' student' . ($occupied === 1 ? '' : 's'),
			$beds > 0 ? ($occupied . ' / ' . $beds . ' beds') : null,
		]);
		$sheet->mergeCells('A2:E2');
		$sheet->setCellValue('A2', implode('  ·  ', $meta));
		$sheet->getStyle('A2:E2')->applyFromArray([
			'font' => ['size' => 10, 'color' => ['rgb' => '334155']],
			'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::BRAND_LIGHT]],
		]);
		$sheet->getRowDimension(2)->setRowHeight(22);

		$sheet->fromArray(['#', 'Student', 'Registration no.', 'Gender', 'Class'], null, 'A4');
		self::headerRow($sheet, 'A4:E4');
		$sheet->freezePane('A5');

		$row = 5;
		$i = 1;
		foreach ($students as $student) {
			$sheet->setCellValue('A' . $row, $i);
			$sheet->setCellValue('B' . $row, (string) ($student['name'] ?? ''));
			$sheet->setCellValueExplicit('C' . $row, (string) ($student['regno'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
			$sheet->setCellValue('D' . $row, (string) ($student['gender'] ?? ''));
			$sheet->setCellValue('E' . $row, (string) ($student['class'] ?? ''));
			if ($i % 2 === 0) {
				$sheet->getStyle("A{$row}:E{$row}")->getFill()
					->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::ALT_ROW);
			}
			$i++;
			$row++;
		}
		if ($students === []) {
			$sheet->mergeCells('A5:E5');
			$sheet->setCellValue('A5', 'No students assigned to this dormitory.');
			$sheet->getStyle('A5')->getFont()->setItalic(true)->getColor()->setRGB('64748B');
			$row = 6;
		}
		$last = max(5, $row - 1);
		$sheet->getStyle("A4:E{$last}")->applyFromArray([
			'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
			'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
		]);
		$sheet->getStyle('A5:A' . $last)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
		foreach (['A' => 6, 'B' => 32, 'C' => 20, 'D' => 12, 'E' => 28] as $col => $width) {
			$sheet->getColumnDimension($col)->setWidth($width);
		}
		$sheet->getPageSetup()->setFitToPage(true)->setFitToWidth(1)->setFitToHeight(0);
		$sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
		$sheet->getHeaderFooter()->setOddFooter('&L' . $name . '&R&P / &N');
	}

	private static function headerRow(Worksheet $sheet, string $range): void
	{
		$sheet->getStyle($range)->applyFromArray([
			'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
			'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::BRAND]],
			'alignment' => [
				'horizontal' => Alignment::HORIZONTAL_CENTER,
				'vertical' => Alignment::VERTICAL_CENTER,
			],
		]);
		$sheet->getRowDimension(4)->setRowHeight(20);
	}

	/**
	 * @param array<string,bool> $used
	 */
	private static function uniqueSheetTitle(string $name, array &$used): string
	{
		$clean = preg_replace('/[\\\\\\/\\?\\*\\:\\[\\]]/', ' ', $name) ?? 'Dormitory';
		$clean = trim((string) preg_replace('/\s+/', ' ', $clean));
		if ($clean === '') {
			$clean = 'Dormitory';
		}
		$base = function_exists('mb_substr') ? mb_substr($clean, 0, 28) : substr($clean, 0, 28);
		$title = $base;
		$n = 2;
		while (isset($used[strtolower($title)])) {
			$suffix = ' ' . $n;
			$room = 31 - strlen($suffix);
			$stem = function_exists('mb_substr') ? mb_substr($base, 0, $room) : substr($base, 0, $room);
			$title = $stem . $suffix;
			$n++;
		}
		$used[strtolower($title)] = true;
		return $title;
	}
}
