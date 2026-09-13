<?php

namespace App\Libraries;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Daily visiting report Excel/PDF export.
 * Same school header as student/class list exports. No card column.
 */
class DailyVisitorsExporter
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
			'Date',
			'Name',
			'ID number',
			'Phone',
			'Reason',
			'Materials with you',
			'In',
			'Out',
			'Duration',
			'Status',
		];
	}

	public static function lastColumn(): string
	{
		return 'J';
	}

	public static function exportFilename(string $schoolName, string $from, string $to, string $ext = 'xlsx'): string
	{
		$base = trim(preg_replace('/\s+/', ' ', $schoolName));
		$base = $base !== '' ? $base . ' visiting report' : 'visiting report';
		$safe = preg_replace('/[\\\\\\/:*?"<>|]+/', '', $base);
		$safe = trim(preg_replace('/\s{2,}/', ' ', (string) $safe));
		$safe = $safe !== '' ? $safe : 'visiting_report';
		$safe = str_replace(' ', '_', $safe);
		$range = $from === $to ? $from : $from . '_' . $to;

		return $safe . '_' . $range . '.' . ltrim($ext, '.');
	}

	/**
	 * @param array<string,mixed> $row
	 * @return list<string>
	 */
	public static function rowValues(array $row): array
	{
		return [
			(string) ($row['visit_date'] ?? ''),
			(string) ($row['names'] ?? ''),
			(string) ($row['id_number'] ?? '') !== '' ? (string) $row['id_number'] : '—',
			(string) ($row['phone'] ?? ''),
			(string) ($row['reason'] ?? ''),
			(string) ($row['materials'] ?? '') !== '' ? (string) $row['materials'] : '—',
			(string) ($row['time_in_label'] ?? ''),
			(string) ($row['time_out_label'] ?? '') !== '' ? (string) $row['time_out_label'] : '—',
			((int) ($row['duration_minutes'] ?? 0)) . ' min',
			!empty($row['inside']) ? 'Inside' : 'Left',
		];
	}

	/**
	 * @param array<string,mixed> $school
	 * @param list<array<string,mixed>> $visits
	 * @param array{total:int,inside:int,checked_out:int} $summary
	 */
	public static function buildExcel(
		array $school,
		array $visits,
		array $summary,
		string $fromDate,
		string $toDate,
		string $yearTitle = '',
		string $termLabel = ''
	): Spreadsheet {
		$spreadsheet = new Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
		$sheet->setTitle('Visiting report');
		$lastCol = self::lastColumn();

		$headerEndRow = self::writeSchoolHeader($sheet, $school, $lastCol);
		$titleRow = $headerEndRow + 2;
		$infoRow = $titleRow + 1;
		$headerRow = $infoRow + 2;
		$dataStart = $headerRow + 1;

		$sheet->mergeCells(self::TEXT_COL . "{$titleRow}:{$lastCol}{$titleRow}");
		$sheet->setCellValue(self::TEXT_COL . $titleRow, 'DAILY VISITING REPORT');
		$sheet->getStyle(self::TEXT_COL . "{$titleRow}:{$lastCol}{$titleRow}")->applyFromArray([
			'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => self::BRAND]],
			'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
		]);
		$sheet->getRowDimension($titleRow)->setRowHeight(26);

		$period = $fromDate === $toDate ? $fromDate : $fromDate . ' — ' . $toDate;
		$metaParts = array_filter([
			'Period: ' . $period,
			'Academic Year: ' . ($yearTitle !== '' ? $yearTitle : '—'),
			$termLabel !== '' ? $termLabel : null,
			'Exported: ' . date('d M Y H:i'),
			'Visits: ' . (int) ($summary['total'] ?? 0),
			'Still inside: ' . (int) ($summary['inside'] ?? 0),
			'Checked out: ' . (int) ($summary['checked_out'] ?? 0),
		]);
		$sheet->mergeCells(self::TEXT_COL . "{$infoRow}:{$lastCol}{$infoRow}");
		$sheet->setCellValue(self::TEXT_COL . $infoRow, implode('   |   ', $metaParts));
		$sheet->getStyle(self::TEXT_COL . "{$infoRow}:{$lastCol}{$infoRow}")->applyFromArray([
			'font' => ['size' => 10, 'color' => ['rgb' => '334155']],
			'fill' => [
				'fillType' => Fill::FILL_SOLID,
				'startColor' => ['rgb' => self::BRAND_LIGHT],
			],
			'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'wrapText' => true],
		]);
		$sheet->getRowDimension($infoRow)->setRowHeight(28);

		$col = 1;
		foreach (self::columnHeaders() as $header) {
			$sheet->setCellValueByColumnAndRow($col, $headerRow, $header);
			$col++;
		}
		$sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
			'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
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
		$sheet->getRowDimension($headerRow)->setRowHeight(28);
		$sheet->freezePane("A{$dataStart}");

		$row = $dataStart;
		$num = 1;
		foreach ($visits as $visit) {
			$values = self::rowValues($visit);
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
				'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
			]);
			$sheet->getStyle("A{$dataStart}:A{$lastDataRow}")
				->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
			$sheet->getStyle("G{$dataStart}:J{$lastDataRow}")
				->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
		}

		$sheet->getColumnDimension('A')->setWidth(13);
		$sheet->getColumnDimension('B')->setWidth(24);
		$sheet->getColumnDimension('C')->setWidth(16);
		$sheet->getColumnDimension('D')->setWidth(14);
		$sheet->getColumnDimension('E')->setWidth(22);
		$sheet->getColumnDimension('F')->setWidth(22);
		$sheet->getColumnDimension('G')->setWidth(9);
		$sheet->getColumnDimension('H')->setWidth(9);
		$sheet->getColumnDimension('I')->setWidth(12);
		$sheet->getColumnDimension('J')->setWidth(11);
		$sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
		$sheet->getPageSetup()->setFitToWidth(1);
		$sheet->getPageSetup()->setFitToHeight(0);
		$sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);

		return $spreadsheet;
	}

	/**
	 * Same school letterhead as student/class list Excel exports.
	 *
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
}
