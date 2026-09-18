<?php

namespace App\Libraries;

use App\Controllers\Home;
use App\Models\AddressModel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StudentListExcelExporter
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
			'Reg No',
			'First Name',
			'Last Name',
			'Gender',
			'Date of Birth',
			'Age',
			'Nationality',
			'Father',
			'Father Phone',
			'Father NID',
			'Mother',
			'Mother Phone',
			'Mother NID',
			'Guardian',
			'Guardian Phone',
			'Guardian NID',
			'Active Parent',
			'Study Mode',
			'Level',
			'Option',
			'Class',
			'Province',
			'District',
			'Sector',
			'Cell',
			'Village',
			'RFID Card',
			'Has Photo',
			'Status',
		];
	}

	public static function lastColumn(): string
	{
		return self::columnLetter(count(self::columnHeaders()));
	}

	public static function exportFilename(string $classLabel): string
	{
		$label = trim(preg_replace('/\s+/', ' ', $classLabel));
		$base = $label !== '' ? $label . ' student list' : 'student list';
		$safe = preg_replace('/[\\\\\\/:*?"<>|]+/', '', $base);
		$safe = trim(preg_replace('/\s{2,}/', ' ', (string) $safe));
		return ($safe !== '' ? $safe : 'student list') . '.xlsx';
	}

	/** @return list<int> */
	private static function nidColumnIndexes(): array
	{
		$indexes = [];
		foreach (self::columnHeaders() as $i => $header) {
			if (preg_match('/NID|Phone|Reg No|RFID/i', $header)) {
				$indexes[] = $i + 1;
			}
		}
		return $indexes;
	}

	/**
	 * @param array<string,mixed> $school
	 * @param array<string,mixed> $classMeta
	 * @param list<array<string,mixed>> $students
	 */
	public static function build(
		array $school,
		array $classMeta,
		array $students,
		string $yearTitle,
		string $termLabel = ''
	): Spreadsheet {
		return self::buildMany($school, [
			['class' => $classMeta, 'students' => $students],
		], $yearTitle, $termLabel);
	}

	/**
	 * One worksheet per class. Used when exporting without choosing a class.
	 *
	 * @param array<string,mixed> $school
	 * @param list<array{class:array<string,mixed>,students:list<array<string,mixed>>}> $sheets
	 */
	public static function buildMany(
		array $school,
		array $sheets,
		string $yearTitle,
		string $termLabel = ''
	): Spreadsheet {
		$spreadsheet = new Spreadsheet();
		$usedTitles = [];
		$first = true;
		$addressModel = new AddressModel();

		foreach ($sheets as $entry) {
			$classMeta = $entry['class'] ?? [];
			$students = $entry['students'] ?? [];
			$classLabel = trim((string) ($classMeta['classe'] ?? ''));
			if ($classLabel === '') {
				$classLabel = SmartStudentSheetsExporter::classLabel($classMeta);
			}
			$title = SmartStudentSheetsExporter::sheetTitle($classLabel !== '' ? $classLabel : 'Class', $usedTitles);
			if ($first) {
				$sheet = $spreadsheet->getActiveSheet();
				$first = false;
			} else {
				$sheet = $spreadsheet->createSheet();
			}
			$sheet->setTitle($title);
			self::fillSheet($sheet, $school, $classMeta, $students, $yearTitle, $termLabel, $addressModel);
		}

		if ($first) {
			$sheet = $spreadsheet->getActiveSheet();
			$sheet->setTitle('No classes');
			self::fillSheet($sheet, $school, ['classe' => 'All classes', 'mentor_name' => ''], [], $yearTitle, $termLabel, $addressModel);
		}

		$spreadsheet->setActiveSheetIndex(0);
		return $spreadsheet;
	}

	/**
	 * @param array<string,mixed> $school
	 * @param array<string,mixed> $classMeta
	 * @param list<array<string,mixed>> $students
	 */
	private static function fillSheet(
		Worksheet $sheet,
		array $school,
		array $classMeta,
		array $students,
		string $yearTitle,
		string $termLabel,
		?AddressModel $addressModel = null
	): void {
		if ($addressModel === null) {
			$addressModel = new AddressModel();
		}
		$lastCol = self::lastColumn();

		$headerEndRow = self::writeSchoolHeader($sheet, $school, $lastCol);
		$titleRow = $headerEndRow + 2;
		$infoRow = $titleRow + 1;
		$headerRow = $infoRow + 2;
		$dataStart = $headerRow + 1;

		$classLabel = trim((string) ($classMeta['classe'] ?? ''));
		$mentor = trim((string) ($classMeta['mentor_name'] ?? ''));
		if ($mentor === '') {
			$mentor = '—';
		}

		$sheet->mergeCells(self::TEXT_COL . "{$titleRow}:{$lastCol}{$titleRow}");
		$sheet->setCellValue(self::TEXT_COL . $titleRow, 'STUDENT LIST');
		$sheet->getStyle(self::TEXT_COL . "{$titleRow}:{$lastCol}{$titleRow}")->applyFromArray([
			'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => self::BRAND]],
			'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
		]);
		$sheet->getRowDimension($titleRow)->setRowHeight(26);

		$metaParts = [
			'Class: ' . ($classLabel !== '' ? $classLabel : '—'),
			'Mentor: ' . $mentor,
			'Academic Year: ' . ($yearTitle !== '' ? $yearTitle : '—'),
			'Exported: ' . date('d M Y H:i'),
			'Total Students: ' . count($students),
		];
		$sheet->mergeCells(self::TEXT_COL . "{$infoRow}:{$lastCol}{$infoRow}");
		$sheet->setCellValue(self::TEXT_COL . $infoRow, implode('   |   ', $metaParts));
		$sheet->getStyle(self::TEXT_COL . "{$infoRow}:{$lastCol}{$infoRow}")->applyFromArray([
			'font' => ['size' => 12, 'color' => ['rgb' => '334155']],
			'fill' => [
				'fillType' => Fill::FILL_SOLID,
				'startColor' => ['rgb' => self::BRAND_LIGHT],
			],
			'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'wrapText' => true],
		]);
		$sheet->getRowDimension($infoRow)->setRowHeight(30);

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
		$sheet->getRowDimension($headerRow)->setRowHeight(32);
		$sheet->freezePane("A{$dataStart}");
		$nidCols = self::nidColumnIndexes();

		$row = $dataStart;
		$num = 1;
		foreach ($students as $student) {
			$values = self::rowValues($student, $num, $addressModel);
			$col = 1;
			foreach ($values as $value) {
				$cell = $sheet->getCellByColumnAndRow($col, $row);
				if (in_array($col, $nidCols, true)) {
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
			$row++;
			$num++;
		}

		if ($row > $dataStart) {
			$lastDataRow = $row - 1;
			foreach ($nidCols as $nidCol) {
				$letter = self::columnLetter($nidCol);
				$sheet->getStyle("{$letter}{$dataStart}:{$letter}{$lastDataRow}")
					->getNumberFormat()
					->setFormatCode('@');
			}
			$sheet->getStyle("A{$dataStart}:{$lastCol}{$lastDataRow}")->applyFromArray([
				'borders' => [
					'allBorders' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'E2E8F0']],
				],
				'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => false],
			]);
		}

		self::autoSizeColumns($sheet, count($headers));
		$sheet->getColumnDimension('A')->setWidth(5);
		$sheet->getColumnDimension('B')->setWidth(14);
		$sheet->getColumnDimension('C')->setWidth(16);
		$sheet->getColumnDimension('D')->setWidth(16);
		$sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
		$sheet->getPageSetup()->setFitToWidth(1);
		$sheet->getPageSetup()->setFitToHeight(0);
	}

	/**
	 * @param array<string,mixed> $student
	 * @return list<int|string>
	 */
	public static function rowValues(array $student, int $num, ?AddressModel $addressModel = null): array
	{
		if ($addressModel === null) {
			$addressModel = new AddressModel();
		}
		$provinceId = (int) ($student['province_id'] ?? 0);
		$fname = trim((string) ($student['fname'] ?? ''));
		$lname = trim((string) ($student['lname'] ?? ''));
		$classLabel = trim((string) ($student['class'] ?? ''));
		if ($classLabel === '') {
			$classLabel = trim(
				($student['level_name'] ?? ($student['level'] ?? '')) . ' ' .
				($student['dept_code'] ?? '') . ' ' .
				($student['class_stream'] ?? '')
			);
		}

		return [
			$num,
			self::formatIdentifier($student['regno'] ?? ''),
			$fname,
			$lname,
			self::genderLabel($student['sex'] ?? ''),
			self::formatDob($student['dob'] ?? ''),
			self::formatAge($student['dob'] ?? ''),
			$student['nationality'] ?? '',
			$student['father'] ?? '',
			self::formatIdentifier($student['ft_phone'] ?? ''),
			self::formatIdentifier($student['father_nid'] ?? ''),
			$student['mother'] ?? '',
			self::formatIdentifier($student['mt_phone'] ?? ''),
			self::formatIdentifier($student['mother_nid'] ?? ''),
			$student['guardian'] ?? '',
			self::formatIdentifier($student['gd_phone'] ?? ''),
			self::formatIdentifier($student['guardian_nid'] ?? ''),
			self::activeParentLabel($student),
			Home::ModeToStr((int) ($student['studying_mode'] ?? 0)),
			$student['level_name'] ?? ($student['level'] ?? ''),
			$student['dept_title'] ?? '',
			$classLabel,
			$provinceId > 0 ? $addressModel->getOneProvince($provinceId) : '',
			$student['district_name'] ?? '',
			$student['sector_name'] ?? '',
			$student['cell_name'] ?? '',
			$student['village_title'] ?? '',
			self::formatIdentifier($student['card'] ?? ''),
			self::hasPhotoLabel($student['photo'] ?? ''),
			self::statusLabel($student['status'] ?? 1),
		];
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
			'font' => ['bold' => true, 'size' => 22, 'color' => ['rgb' => self::BRAND]],
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
				'font' => ['italic' => true, 'size' => 12, 'color' => ['rgb' => '475569']],
				'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'indent' => 1],
			]);
			$sheet->getRowDimension($row2)->setRowHeight(22);
			$row2++;
		}

		if ($contact !== []) {
			$sheet->mergeCells(self::TEXT_COL . "{$row2}:{$lastCol}{$row2}");
			$sheet->setCellValue(self::TEXT_COL . $row2, implode('  •  ', $contact));
			$sheet->getStyle(self::TEXT_COL . "{$row2}:{$lastCol}{$row2}")->applyFromArray([
				'font' => ['size' => 11, 'color' => ['rgb' => '64748B']],
				'alignment' => ['wrapText' => true, 'horizontal' => Alignment::HORIZONTAL_LEFT, 'indent' => 1],
			]);
			$sheet->getRowDimension($row2)->setRowHeight(24);
			$row2++;
		}

		self::placeLogo($sheet, $school, $row2 - 1);

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
	private static function placeLogo(Worksheet $sheet, array $school, int $headerRows): void
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

	private static function formatDob($dob): string
	{
		$dob = trim((string) $dob);
		if ($dob === '' || $dob === '0000-00-00' || strpos($dob, '0000') === 0) {
			return '';
		}
		$ts = strtotime($dob);
		return $ts ? date('d M Y', $ts) : $dob;
	}

	private static function formatAge($dob): string
	{
		$dob = trim((string) $dob);
		if ($dob === '' || $dob === '0000-00-00' || strpos($dob, '0000') === 0) {
			return '';
		}
		$ts = strtotime($dob);
		if (!$ts) {
			return '';
		}
		$age = (int) date('Y') - (int) date('Y', $ts);
		if ((int) date('md') < (int) date('md', $ts)) {
			$age--;
		}
		if ($age < 0 || $age > 99) {
			return '';
		}
		return (string) $age;
	}

	private static function genderLabel($sex): string
	{
		$s = strtoupper(trim((string) $sex));
		if ($s === 'M' || $s === 'MALE') {
			return 'Male';
		}
		if ($s === 'F' || $s === 'FEMALE') {
			return 'Female';
		}
		return trim((string) $sex);
	}

	private static function hasPhotoLabel($photo): string
	{
		$stored = trim((string) $photo);
		if ($stored === '' || strlen($stored) < 3) {
			return 'No';
		}
		$base = basename(str_replace(["\0", '\\'], '', $stored));
		if ($base === '' || preg_match('/^face_staff_/i', $base)) {
			return 'No';
		}
		return 'Yes';
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

	/**
	 * @param array<string,mixed> $student
	 */
	private static function activeParentLabel(array $student): string
	{
		if (strlen(trim((string) ($student['father'] ?? ''))) > 3) {
			return 'Father';
		}
		if (strlen(trim((string) ($student['mother'] ?? ''))) > 3) {
			return 'Mother';
		}
		if (strlen(trim((string) ($student['guardian'] ?? ''))) > 3) {
			return 'Guardian';
		}
		return '';
	}

	private static function statusLabel($status): string
	{
		$status = (int) $status;
		if ($status === 1 || $status === 2) {
			return 'Active';
		}
		if ($status === 0) {
			return 'Dismissed';
		}
		return 'Locked';
	}
}
