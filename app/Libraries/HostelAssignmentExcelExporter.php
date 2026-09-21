<?php

namespace App\Libraries;

use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\NamedRange;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Short hostel-assignment workbook: one sheet per class and gender
 * (P1 Girls, P1 Boys). Columns: Name, Class, Hostel dropdown.
 */
class HostelAssignmentExcelExporter
{
	private const BRAND = '012F6B';
	private const BRAND_LIGHT = 'E8F0FA';
	private const ALT_ROW = 'F7FAFD';
	private const LISTS_SHEET = '_Hostels';
	private const GROUPS = ['nursery', 'primary', 'high_school'];

	public static function exportFilename(string $schoolName, string $yearTitle = ''): string
	{
		$parts = array_filter([
			trim(preg_replace('/\s+/', ' ', $schoolName)),
			trim(preg_replace('/\s+/', ' ', $yearTitle)),
			'hostel assignment',
		]);
		$base = $parts !== [] ? implode(' ', $parts) : 'hostel assignment';
		$safe = preg_replace('/[\\\\\\/:*?"<>|]+/', '', $base);
		$safe = trim((string) preg_replace('/\s{2,}/', ' ', (string) $safe));
		$safe = $safe !== '' ? $safe : 'hostel_assignment';
		$safe = str_replace(' ', '_', $safe);

		return $safe . '_' . date('Y-m-d') . '.xlsx';
	}

	/**
	 * @param array<string, int> $used
	 */
	public static function sheetTitle(string $className, string $genderWord, array &$used): string
	{
		$base = trim($className . ' ' . $genderWord);
		$base = str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $base);
		$base = trim((string) preg_replace('/\s+/', ' ', $base));
		if ($base === '') {
			$base = $genderWord !== '' ? $genderWord : 'Class';
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

	public static function genderWord(string $gender): string
	{
		return strtoupper($gender) === 'F' ? 'Girls' : 'Boys';
	}

	/**
	 * @param array<string,mixed> $school
	 * @param list<array{class_label:string,level_group:string,gender:string,students:list<array<string,mixed>>}> $sheets
	 * @param array<string, list<string>> $hostelLists keyed like F_primary, M_all
	 */
	public static function build(
		array $school,
		array $sheets,
		array $hostelLists,
		string $yearTitle = ''
	): Spreadsheet {
		$spreadsheet = new Spreadsheet();
		self::writeHostelLists($spreadsheet, $hostelLists);

		$usedTitles = [];
		$classSheetCount = 0;
		foreach ($sheets as $entry) {
			$classLabel = trim((string) ($entry['class_label'] ?? 'Class'));
			$gender = strtoupper((string) ($entry['gender'] ?? 'M')) === 'F' ? 'F' : 'M';
			$levelGroup = (string) ($entry['level_group'] ?? 'high_school');
			$students = $entry['students'] ?? [];
			$title = self::sheetTitle($classLabel, self::genderWord($gender), $usedTitles);
			$sheet = $spreadsheet->createSheet();
			$sheet->setTitle($title);
			$listKey = self::listKeyFor($gender, $levelGroup, $hostelLists);
			self::fillClassSheet(
				$sheet,
				$school,
				$classLabel,
				self::genderWord($gender),
				$students,
				$listKey,
				$yearTitle
			);
			$classSheetCount++;
		}

		if ($classSheetCount === 0) {
			$sheet = $spreadsheet->createSheet();
			$sheet->setTitle('No students');
			self::fillClassSheet($sheet, $school, 'No students', '', [], '', $yearTitle);
			$classSheetCount++;
		}

		$lists = $spreadsheet->getSheetByName(self::LISTS_SHEET);
		if ($lists) {
			$lists->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
		}

		$spreadsheet->setActiveSheetIndex(1);

		return $spreadsheet;
	}

	/**
	 * @param array<string, list<string>> $hostelLists
	 */
	private static function listKeyFor(string $gender, string $levelGroup, array $hostelLists): string
	{
		$group = in_array($levelGroup, self::GROUPS, true) ? $levelGroup : 'high_school';
		$specific = $gender . '_' . $group;
		if (!empty($hostelLists[$specific])) {
			return $specific;
		}
		$all = $gender . '_all';
		if (!empty($hostelLists[$all])) {
			return $all;
		}
		return '';
	}

	/**
	 * @param array<string, list<string>> $hostelLists
	 */
	private static function writeHostelLists(Spreadsheet $spreadsheet, array $hostelLists): void
	{
		$sheet = $spreadsheet->getActiveSheet();
		$sheet->setTitle(self::LISTS_SHEET);

		$keys = [];
		foreach (['M', 'F'] as $gender) {
			foreach (array_merge(self::GROUPS, ['all']) as $group) {
				$keys[] = $gender . '_' . $group;
			}
		}

		$col = 1;
		foreach ($keys as $key) {
			$names = array_values(array_unique(array_filter($hostelLists[$key] ?? [])));
			$sheet->setCellValueByColumnAndRow($col, 1, $key);
			$row = 2;
			foreach ($names as $name) {
				$sheet->setCellValueByColumnAndRow($col, $row, $name);
				$row++;
			}
			if ($names !== []) {
				$last = $row - 1;
				$colLetter = self::columnLetter($col);
				$range = '$' . $colLetter . '$2:$' . $colLetter . '$' . $last;
				$spreadsheet->addNamedRange(new NamedRange('hst_' . $key, $sheet, $range));
			}
			$col++;
		}
		$sheet->getStyle('A1:H1')->getFont()->setBold(true);
	}

	/**
	 * @param array<string,mixed> $school
	 * @param list<array<string,mixed>> $students
	 */
	private static function fillClassSheet(
		Worksheet $sheet,
		array $school,
		string $classLabel,
		string $genderWord,
		array $students,
		string $listKey,
		string $yearTitle
	): void {
		$schoolName = trim((string) ($school['name'] ?? 'School'));
		$heading = trim($classLabel . ($genderWord !== '' ? ' ' . $genderWord : ''));

		$sheet->mergeCells('A1:C1');
		$sheet->setCellValue('A1', $schoolName !== '' ? $schoolName : 'School');
		$sheet->getStyle('A1:C1')->applyFromArray([
			'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => self::BRAND]],
			'alignment' => [
				'horizontal' => Alignment::HORIZONTAL_LEFT,
				'vertical' => Alignment::VERTICAL_CENTER,
			],
		]);
		$sheet->getRowDimension(1)->setRowHeight(22);

		$meta = array_filter([
			$heading !== '' ? $heading : null,
			$yearTitle !== '' ? $yearTitle : null,
			count($students) . ' student' . (count($students) === 1 ? '' : 's'),
			'Choose hostel from the dropdown',
		]);
		$sheet->mergeCells('A2:C2');
		$sheet->setCellValue('A2', implode('  ·  ', $meta));
		$sheet->getStyle('A2:C2')->applyFromArray([
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
		$sheet->getRowDimension(2)->setRowHeight(28);

		$sheet->fromArray(['Name', 'Class', 'Hostel'], null, 'A3');
		$sheet->getStyle('A3:C3')->applyFromArray([
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
		$sheet->getRowDimension(3)->setRowHeight(22);
		$sheet->freezePane('A4');

		$row = 4;
		$n = 0;
		foreach ($students as $student) {
			$name = trim((string) ($student['name'] ?? ''));
			$class = trim((string) ($student['class'] ?? $classLabel));
			$hostel = trim((string) ($student['hostel'] ?? ''));
			$sheet->setCellValue('A' . $row, $name !== '' ? $name : '—');
			$sheet->setCellValue('B' . $row, $class !== '' ? $class : $classLabel);
			$sheet->setCellValue('C' . $row, $hostel);
			if ($n % 2 === 1) {
				$sheet->getStyle("A{$row}:C{$row}")->applyFromArray([
					'fill' => [
						'fillType' => Fill::FILL_SOLID,
						'startColor' => ['rgb' => self::ALT_ROW],
					],
				]);
			}
			$n++;
			$row++;
		}

		$lastData = $row > 4 ? $row - 1 : 4;
		$sheet->getStyle("A3:C{$lastData}")->applyFromArray([
			'borders' => [
				'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']],
			],
			'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
		]);

		if ($listKey !== '' && $row > 4) {
			$formula = '=hst_' . $listKey;
			$validation = $sheet->getCell('C4')->getDataValidation();
			$validation->setType(DataValidation::TYPE_LIST);
			$validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
			$validation->setAllowBlank(true);
			$validation->setShowDropDown(true);
			$validation->setShowErrorMessage(true);
			$validation->setShowInputMessage(true);
			$validation->setErrorTitle('Hostel');
			$validation->setError('Pick a hostel from the list for this gender.');
			$validation->setPromptTitle('Assign hostel');
			$validation->setPrompt('Select a hostel created for this gender.');
			$validation->setFormula1($formula);
			$validation->setSqref('C4:C' . $lastData);
		}

		$sheet->getColumnDimension('A')->setWidth(36);
		$sheet->getColumnDimension('B')->setWidth(18);
		$sheet->getColumnDimension('C')->setWidth(28);
		$sheet->getPageSetup()->setFitToPage(true);
		$sheet->getPageSetup()->setFitToWidth(1);
		$sheet->getPageSetup()->setFitToHeight(0);
		$sheet->getHeaderFooter()->setOddFooter('&L' . $heading . '&R&P / &N');
	}

	private static function columnLetter(int $index): string
	{
		$letter = '';
		while ($index > 0) {
			$index--;
			$letter = chr(65 + ($index % 26)) . $letter;
			$index = intdiv($index, 26);
		}
		return $letter !== '' ? $letter : 'A';
	}
}
