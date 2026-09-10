<?php

namespace App\Libraries;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Smart class list Excel/PDF export.
 * Columns: Class name (Level+title), Stream teacher, Students — no type/faculty/courses.
 */
class ClassListExporter
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
			'Class name',
			'Stream teacher',
			'Students',
		];
	}

	public static function lastColumn(): string
	{
		return self::columnLetter(count(self::columnHeaders()));
	}

	public static function exportFilename(string $schoolName, string $ext = 'xlsx'): string
	{
		$base = trim(preg_replace('/\s+/', ' ', $schoolName));
		$base = $base !== '' ? $base . ' class list' : 'class list';
		$safe = preg_replace('/[\\\\\\/:*?"<>|]+/', '', $base);
		$safe = trim(preg_replace('/\s{2,}/', ' ', (string) $safe));
		$safe = $safe !== '' ? $safe : 'class list';
		$safe = str_replace(' ', '_', $safe);

		return $safe . '_' . date('Y-m-d') . '.' . ltrim($ext, '.');
	}

	/**
	 * Concatenate level + stream title (e.g. P6A, S1B, Middle Class A).
	 *
	 * @param array<string,mixed> $class
	 */
	public static function className(array $class): string
	{
		$level = trim((string) ($class['level_name'] ?? ''));
		$title = trim((string) ($class['title'] ?? ''));
		if ($title === '' || $title === '-----') {
			return $level !== '' ? $level : '—';
		}
		if ($level === '') {
			return $title;
		}
		// Single-letter / short stream codes → P6A, S2B
		if (preg_match('/^[A-Za-z0-9]{1,3}$/', $title)) {
			return $level . strtoupper($title);
		}

		return $level . ' ' . $title;
	}

	/**
	 * @param array<string,mixed> $class
	 */
	public static function streamTeacher(array $class): string
	{
		$name = trim((string) ($class['mentor_name'] ?? ''));

		return $name !== '' ? $name : 'Not assigned';
	}

	/**
	 * @param array<string,mixed> $class
	 * @return list<int|string>
	 */
	public static function rowValues(array $class, int $num): array
	{
		return [
			$num,
			self::className($class),
			self::streamTeacher($class),
			(int) ($class['students'] ?? 0),
		];
	}

	/**
	 * @param list<array<string,mixed>> $classes
	 * @return array{classes:int,students:int,unassigned:int}
	 */
	public static function totals(array $classes): array
	{
		$students = 0;
		$unassigned = 0;
		foreach ($classes as $class) {
			$students += (int) ($class['students'] ?? 0);
			if (trim((string) ($class['mentor_name'] ?? '')) === '') {
				$unassigned++;
			}
		}

		return [
			'classes' => count($classes),
			'students' => $students,
			'unassigned' => $unassigned,
		];
	}

	/**
	 * @param array<string,mixed> $school
	 * @param list<array<string,mixed>> $classes
	 */
	public static function buildExcel(
		array $school,
		array $classes,
		string $yearTitle = '',
		string $termLabel = ''
	): Spreadsheet {
		$spreadsheet = new Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
		$sheet->setTitle('Classes');
		$lastCol = self::lastColumn();
		$totals = self::totals($classes);

		$headerEndRow = self::writeSchoolHeader($sheet, $school, $lastCol);
		$titleRow = $headerEndRow + 2;
		$infoRow = $titleRow + 1;
		$headerRow = $infoRow + 2;
		$dataStart = $headerRow + 1;

		$sheet->mergeCells(self::TEXT_COL . "{$titleRow}:{$lastCol}{$titleRow}");
		$sheet->setCellValue(self::TEXT_COL . $titleRow, 'CLASS LIST');
		$sheet->getStyle(self::TEXT_COL . "{$titleRow}:{$lastCol}{$titleRow}")->applyFromArray([
			'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => self::BRAND]],
			'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
		]);
		$sheet->getRowDimension($titleRow)->setRowHeight(26);

		$metaParts = array_filter([
			'Academic Year: ' . ($yearTitle !== '' ? $yearTitle : '—'),
			$termLabel !== '' ? $termLabel : null,
			'Exported: ' . date('d M Y H:i'),
			'Classes: ' . $totals['classes'],
			'Students: ' . $totals['students'],
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
		foreach ($classes as $class) {
			$values = self::rowValues($class, $num);
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

		if ($row > $dataStart) {
			$lastDataRow = $row - 1;
			$sheet->getStyle("A{$dataStart}:{$lastCol}{$lastDataRow}")->applyFromArray([
				'borders' => [
					'allBorders' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'E2E8F0']],
				],
				'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
			]);
			$sheet->getStyle("A{$dataStart}:A{$lastDataRow}")
				->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
			$sheet->getStyle("D{$dataStart}:D{$lastDataRow}")
				->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
		}

		$sheet->getColumnDimension('A')->setWidth(5);
		$sheet->getColumnDimension('B')->setWidth(18);
		$sheet->getColumnDimension('C')->setWidth(32);
		$sheet->getColumnDimension('D')->setWidth(12);
		$sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT);
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
