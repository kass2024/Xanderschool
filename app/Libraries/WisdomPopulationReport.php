<?php

namespace App\Libraries;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * All-schools attendance workbook in the Wisdom population layout.
 * Student present/absent come from the daily register, not card in/out.
 * Support staff are everyone whose shift is not Academic staff.
 */
class WisdomPopulationReport
{
	private const NAVY = '012F6B';
	private const GREEN = '166534';
	private const RED = '991B1B';
	private const TEAL = '0F766E';
	private const AMBER = '92400E';
	private const LIGHT = 'E8EEF8';
	private const ALT = 'F8FAFC';
	private const TOTAL = 'E2E8F0';

	public static function exportFilename(string $date): string
	{
		return 'Wisdom_attendance_' . $date . '.xlsx';
	}

	public static function stream(int $masterId, int $yearId, string $date): void
	{
		$spreadsheet = self::build($masterId, $yearId, $date);
		$writer = new Xlsx($spreadsheet);
		$filename = self::exportFilename($date);
		while (ob_get_level() > 0) {
			ob_end_clean();
		}
		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Cache-Control: max-age=0');
		$writer->save('php://output');
		exit;
	}

	public static function build(int $masterId, int $yearId, string $date): Spreadsheet
	{
		$data = self::collect($masterId, $yearId, $date);
		$spreadsheet = new Spreadsheet();
		$spreadsheet->getProperties()
			->setCreator('Xander School')
			->setTitle('Wisdom schools attendance')
			->setSubject($date);
		self::writeSummary($spreadsheet->getActiveSheet(), $data);
		$used = ['all schools' => true];
		foreach ($data['schools'] as $school) {
			if ($school['np'] !== []) {
				$sheet = $spreadsheet->createSheet();
				$sheet->setTitle(self::sheetTitle($school['short'] . ' N&P', $used));
				self::writeClassSheet($sheet, $school, $date, 'np');
			}
			if ($school['hs'] !== []) {
				$sheet = $spreadsheet->createSheet();
				$sheet->setTitle(self::sheetTitle($school['short'] . ' HS', $used));
				self::writeClassSheet($sheet, $school, $date, 'hs');
			}
		}
		$teachers = $spreadsheet->createSheet();
		$teachers->setTitle(self::sheetTitle('TEACHERS', $used));
		self::writeTeachers($teachers, $data);
		$spreadsheet->setActiveSheetIndex(0);
		return $spreadsheet;
	}

	/**
	 * @return array{date:string,date_label:string,schools:list<array<string,mixed>>,totals:array<string,int>}
	 */
	private static function collect(int $masterId, int $yearId, string $date): array
	{
		$overview = new \App\Services\WisdomGroupOverview();
		$schools = $overview->schools($masterId);
		$ids = [];
		foreach ($schools as $school) {
			$id = (int) ($school['id'] ?? 0);
			if ($id > 0) {
				$ids[] = $id;
			}
		}
		$blankStaff = ['present' => 0, 'missing' => 0];
		$bySchool = [];
		foreach ($schools as $school) {
			$id = (int) ($school['id'] ?? 0);
			if ($id < 1) {
				continue;
			}
			$name = trim((string) ($school['name'] ?? 'School'));
			$bySchool[$id] = [
				'id' => $id,
				'name' => $name,
				'short' => self::shortName($name),
				'expected_f' => 0,
				'expected_m' => 0,
				'expected' => 0,
				'present_f' => 0,
				'present_m' => 0,
				'present' => 0,
				'teachers' => $blankStaff,
				'support' => $blankStaff,
				'np' => [],
				'hs' => [],
				'teacher_levels' => [],
			];
		}
		if ($bySchool !== []) {
			self::fillStudents($bySchool, $yearId, $date);
			self::fillStaff($bySchool, $yearId, $date);
		}
		$rows = array_values($bySchool);
		usort($rows, static function (array $a, array $b): int {
			return strcasecmp((string) $a['name'], (string) $b['name']);
		});
		$totals = [
			'expected_f' => 0, 'expected_m' => 0, 'expected' => 0,
			'present_f' => 0, 'present_m' => 0, 'present' => 0,
			'teachers_present' => 0, 'teachers_missing' => 0,
			'support_present' => 0, 'support_missing' => 0,
		];
		foreach ($rows as $row) {
			$totals['expected_f'] += $row['expected_f'];
			$totals['expected_m'] += $row['expected_m'];
			$totals['expected'] += $row['expected'];
			$totals['present_f'] += $row['present_f'];
			$totals['present_m'] += $row['present_m'];
			$totals['present'] += $row['present'];
			$totals['teachers_present'] += $row['teachers']['present'];
			$totals['teachers_missing'] += $row['teachers']['missing'];
			$totals['support_present'] += $row['support']['present'];
			$totals['support_missing'] += $row['support']['missing'];
		}
		return [
			'date' => $date,
			'date_label' => date('d/m/Y', strtotime($date)),
			'schools' => $rows,
			'totals' => $totals,
		];
	}

	/**
	 * @param array<int, array<string, mixed>> $bySchool
	 */
	private static function fillStudents(array &$bySchool, int $yearId, string $date): void
	{
		$db = \Config\Database::connect();
		if (!$db->tableExists('students') || !$db->tableExists('class_records')) {
			return;
		}
		$idList = implode(',', array_map('intval', array_keys($bySchool)));
		$day = $db->escape($date);
		$yearId = (int) $yearId;
		$presentJoin = $db->tableExists('daily_attendance')
			? "LEFT JOIN (SELECT DISTINCT student_id FROM daily_attendance WHERE DATE(datee) = {$day}) da ON da.student_id = s.id"
			: '';
		$presentSelect = $presentJoin !== '' ? 'CASE WHEN da.student_id IS NULL THEN 0 ELSE 1 END' : '0';
		$sql = "SELECT s.school_id, s.id, s.sex, s.studying_mode,
				c.id AS class_id, c.title AS class_title, l.title AS level_name, d.code AS dept_code, d.title AS dept_title,
				{$presentSelect} AS present
			FROM students s
			INNER JOIN schools sch ON sch.id = s.school_id
			LEFT JOIN active_term atr ON atr.id = sch.active_term
			INNER JOIN class_records cr ON cr.student = s.id
				AND cr.year = IFNULL(atr.academic_year, {$yearId})
			INNER JOIN classes c ON c.id = cr.class
			LEFT JOIN levels l ON l.id = c.level
			LEFT JOIN departments d ON d.id = c.department
			{$presentJoin}
			WHERE s.school_id IN ({$idList})
				AND s.status = 1
				AND IFNULL(c.title,'') NOT LIKE '%Holiday%'
				AND IFNULL(l.title,'') NOT LIKE '%Holiday%'
			ORDER BY c.id ASC";
		$seen = [];
		$classes = [];
		foreach ($db->query($sql)->getResultArray() as $row) {
			$sid = (int) ($row['school_id'] ?? 0);
			$studentId = (int) ($row['id'] ?? 0);
			if ($sid < 1 || $studentId < 1 || !isset($bySchool[$sid]) || isset($seen[$studentId])) {
				continue;
			}
			$seen[$studentId] = true;
			$gender = self::gender($row['sex'] ?? '');
			$present = (int) ($row['present'] ?? 0) === 1;
			$boarding = (int) ($row['studying_mode'] ?? 1) === 0;
			$bySchool[$sid]['expected']++;
			if ($present) {
				$bySchool[$sid]['present']++;
			}
			if ($gender === 'F') {
				$bySchool[$sid]['expected_f']++;
				if ($present) {
					$bySchool[$sid]['present_f']++;
				}
			} elseif ($gender === 'M') {
				$bySchool[$sid]['expected_m']++;
				if ($present) {
					$bySchool[$sid]['present_m']++;
				}
			}
			$group = (new \App\Models\HostelSchemaModel())->resolveLevelGroupFromTitle(
				trim((string) ($row['level_name'] ?? '') . ' ' . (string) ($row['class_title'] ?? ''))
			);
			$bucket = $group === 'high_school' ? 'hs' : 'np';
			$classId = (int) ($row['class_id'] ?? 0);
			$key = $sid . ':' . $classId;
			if (!isset($classes[$key])) {
				$label = \App\Models\SchoolFeesModel::displayLabel($row);
				$classes[$key] = [
					'school_id' => $sid,
					'bucket' => $bucket,
					'group' => $group === '' ? 'primary' : $group,
					'label' => $label !== '' ? $label : 'Class',
					'rank' => self::classRank($label, $group),
					'boys' => 0,
					'girls' => 0,
					'total' => 0,
					'absent' => 0,
					'boarders' => 0,
					'enrolled' => 0,
				];
			}
			$classes[$key]['enrolled']++;
			if ($present) {
				$classes[$key]['total']++;
				if ($gender === 'M') {
					$classes[$key]['boys']++;
				} elseif ($gender === 'F') {
					$classes[$key]['girls']++;
				}
				if ($boarding) {
					$classes[$key]['boarders']++;
				}
			}
		}
		foreach ($classes as $class) {
			$sid = (int) $class['school_id'];
			$class['absent'] = max(0, (int) $class['enrolled'] - (int) $class['total']);
			$bucket = $class['bucket'] === 'hs' ? 'hs' : 'np';
			$bySchool[$sid][$bucket][] = $class;
		}
		foreach ($bySchool as $sid => $school) {
			foreach (['np', 'hs'] as $bucket) {
				usort($bySchool[$sid][$bucket], static function (array $a, array $b): int {
					return $a['rank'] <=> $b['rank'];
				});
			}
		}
	}

	/**
	 * @param array<int, array<string, mixed>> $bySchool
	 */
	private static function fillStaff(array &$bySchool, int $yearId, string $date): void
	{
		$db = \Config\Database::connect();
		if (!$db->tableExists('staffs')) {
			return;
		}
		$idList = implode(',', array_map('intval', array_keys($bySchool)));
		$hasSex = in_array('sex', $db->getFieldNames('staffs'), true);
		$sexCol = $hasSex ? 's.sex' : "'' AS sex";
		$rows = $db->query(
			"SELECT s.id, s.school_id, {$sexCol}, sh.title AS shift_title, sh.options AS shift_options
			FROM staffs s
			LEFT JOIN shifts sh ON sh.id = s.shift_id
			WHERE s.school_id IN ({$idList}) AND IFNULL(s.status, 1) <> 0"
		)->getResultArray();
		$todayStart = strtotime($date . ' 00:00:00');
		$todayEnd = $todayStart + 86400;
		$noon = strtotime($date . ' 12:00:00');
		$clocks = [];
		if ($db->tableExists('attendance_records')) {
			$clockRows = $db->query(
				"SELECT user_id, MIN(time_in) AS time_in
				FROM attendance_records
				WHERE user_type = 1 AND school_id IN ({$idList})
					AND time_in >= {$todayStart} AND time_in < {$todayEnd}
				GROUP BY user_id"
			)->getResultArray();
			foreach ($clockRows as $clock) {
				$clocks[(int) $clock['user_id']] = (int) $clock['time_in'];
			}
		}
		$levels = self::teacherLevels($db, $idList, $yearId);
		foreach ($rows as $staff) {
			$sid = (int) ($staff['school_id'] ?? 0);
			$staffId = (int) ($staff['id'] ?? 0);
			if ($sid < 1 || !isset($bySchool[$sid])) {
				continue;
			}
			$shift = [
				'title' => (string) ($staff['shift_title'] ?? ''),
				'options' => (string) ($staff['shift_options'] ?? '[]'),
			];
			$academic = self::isAcademicShift($shift['title']);
			$bucket = $academic ? 'teachers' : 'support';
			$hasShift = trim($shift['options']) !== '' && $shift['options'] !== '[]';
			$window = StaffShiftClock::windowFor($shift, $noon);
			$timeIn = $clocks[$staffId] ?? 0;
			$due = $timeIn > 0 || !$hasShift || !empty($window['working']);
			if (!$due) {
				continue;
			}
			$present = $timeIn > 0;
			if ($present) {
				$bySchool[$sid][$bucket]['present']++;
			} else {
				$bySchool[$sid][$bucket]['missing']++;
			}
			if (!$academic) {
				continue;
			}
			$gender = self::gender($staff['sex'] ?? '');
			$groups = $levels[$staffId] ?? ['unassigned'];
			foreach ($groups as $group) {
				if (!isset($bySchool[$sid]['teacher_levels'][$group])) {
					$bySchool[$sid]['teacher_levels'][$group] = [
						'male' => 0, 'female' => 0, 'present' => 0, 'missing' => 0,
					];
				}
				if ($present) {
					$bySchool[$sid]['teacher_levels'][$group]['present']++;
					if ($gender === 'M') {
						$bySchool[$sid]['teacher_levels'][$group]['male']++;
					} elseif ($gender === 'F') {
						$bySchool[$sid]['teacher_levels'][$group]['female']++;
					}
				} else {
					$bySchool[$sid]['teacher_levels'][$group]['missing']++;
				}
			}
		}
	}

	/**
	 * @return array<int, list<string>>
	 */
	private static function teacherLevels($db, string $idList, int $yearId): array
	{
		$out = [];
		if (!$db->tableExists('course_records') || !$db->tableExists('classes') || $yearId < 1) {
			return $out;
		}
		$rows = $db->query(
			"SELECT cr.lecturer, l.title AS level_name, c.title AS class_title
			FROM course_records cr
			INNER JOIN classes c ON c.id = cr.class
			LEFT JOIN levels l ON l.id = c.level
			WHERE cr.year = " . (int) $yearId . " AND c.school_id IN ({$idList}) AND cr.lecturer > 0"
		)->getResultArray();
		$schema = new \App\Models\HostelSchemaModel();
		foreach ($rows as $row) {
			$lecturer = (int) ($row['lecturer'] ?? 0);
			if ($lecturer < 1) {
				continue;
			}
			$group = $schema->resolveLevelGroupFromTitle(
				trim((string) ($row['level_name'] ?? '') . ' ' . (string) ($row['class_title'] ?? ''))
			);
			if ($group === '') {
				$group = 'primary';
			}
			$out[$lecturer][$group] = $group;
		}
		foreach ($out as $id => $groups) {
			$out[$id] = array_values($groups);
		}
		return $out;
	}

	/**
	 * @param array{date:string,date_label:string,schools:list<array<string,mixed>>,totals:array<string,int>} $data
	 */
	private static function writeSummary(Worksheet $sheet, array $data): void
	{
		$sheet->setTitle('ALL SCHOOLS');
		$sheet->mergeCells('A1:O1');
		$sheet->setCellValue('A1', 'WISDOM SCHOOLS — ATTENDANCE');
		$sheet->mergeCells('A2:O2');
		$sheet->setCellValue('A2', 'Date ' . $data['date_label'] . '    ·    Expected students match the dashboard, holiday classes excluded    ·    Present and absent are from the daily register, not card in/out    ·    Support staff = shift is not Academic staff');
		$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB(self::NAVY);
		$sheet->getStyle('A2')->getFont()->setSize(9)->getColor()->setRGB('475569');
		$sheet->getRowDimension(1)->setRowHeight(24);
		$sheet->getRowDimension(2)->setRowHeight(18);

		$groups = [
			'C3:E3' => ['EXPECTED STUDENTS', self::NAVY],
			'F3:H3' => ['PRESENT', self::GREEN],
			'I3:K3' => ['ABSENT', self::RED],
			'L3:M3' => ['TEACHERS', self::TEAL],
			'N3:O3' => ['SUPPORT STAFF', self::AMBER],
		];
		foreach ($groups as $range => $meta) {
			$sheet->mergeCells($range);
			$cell = explode(':', $range)[0];
			$sheet->setCellValue($cell, $meta[0]);
			$sheet->getStyle($range)->applyFromArray([
				'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
				'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $meta[1]]],
				'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
			]);
		}
		$sheet->mergeCells('A3:A4');
		$sheet->mergeCells('B3:B4');
		$sheet->setCellValue('A3', 'S/N');
		$sheet->setCellValue('B3', 'SCHOOL');
		$sheet->getStyle('A3:B4')->applyFromArray([
			'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
			'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::NAVY]],
			'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
		]);
		$headers = ['C' => 'F', 'D' => 'M', 'E' => 'T', 'F' => 'F', 'G' => 'M', 'H' => 'T', 'I' => 'F', 'J' => 'M', 'K' => 'T', 'L' => 'PRESENT', 'M' => 'MISSING', 'N' => 'PRESENT', 'O' => 'MISSING'];
		foreach ($headers as $col => $label) {
			$sheet->setCellValue($col . '4', $label);
		}
		$sheet->getStyle('C4:O4')->applyFromArray([
			'font' => ['bold' => true, 'color' => ['rgb' => self::NAVY], 'size' => 9],
			'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::LIGHT]],
			'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
		]);
		$sheet->getRowDimension(3)->setRowHeight(20);
		$sheet->getRowDimension(4)->setRowHeight(18);
		$sheet->freezePane('A5');

		$row = 5;
		$n = 1;
		foreach ($data['schools'] as $school) {
			$expF = (int) $school['expected_f'];
			$expM = (int) $school['expected_m'];
			$exp = (int) $school['expected'];
			$preF = (int) $school['present_f'];
			$preM = (int) $school['present_m'];
			$pre = (int) $school['present'];
			$sheet->fromArray([
				$n,
				$school['short'],
				$expF,
				$expM,
				$exp,
				$preF,
				$preM,
				$pre,
				max(0, $expF - $preF),
				max(0, $expM - $preM),
				max(0, $exp - $pre),
				(int) $school['teachers']['present'],
				(int) $school['teachers']['missing'],
				(int) $school['support']['present'],
				(int) $school['support']['missing'],
			], null, 'A' . $row);
			if ($n % 2 === 0) {
				$sheet->getStyle("A{$row}:O{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::ALT);
			}
			$n++;
			$row++;
		}
		$t = $data['totals'];
		$exp = (int) $t['expected'];
		$pre = (int) $t['present'];
		$sheet->fromArray([
			'',
			'GENERAL TOTAL',
			$t['expected_f'],
			$t['expected_m'],
			$exp,
			$t['present_f'],
			$t['present_m'],
			$pre,
			max(0, $t['expected_f'] - $t['present_f']),
			max(0, $t['expected_m'] - $t['present_m']),
			max(0, $exp - $pre),
			$t['teachers_present'],
			$t['teachers_missing'],
			$t['support_present'],
			$t['support_missing'],
		], null, 'A' . $row);
		$sheet->getStyle("A{$row}:O{$row}")->applyFromArray([
			'font' => ['bold' => true],
			'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::TOTAL]],
		]);
		$pctRow = $row + 2;
		$sheet->setCellValue('G' . $pctRow, 'Present');
		$sheet->setCellValue('H' . $pctRow, $exp > 0 ? $pre / $exp : 0);
		$sheet->getStyle('H' . $pctRow)->getNumberFormat()->setFormatCode('0.0%');
		$sheet->getStyle('G' . $pctRow . ':H' . $pctRow)->getFont()->setBold(true)->getColor()->setRGB(self::NAVY);
		$last = $row;
		$sheet->getStyle("A3:O{$last}")->applyFromArray([
			'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
			'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
		]);
		$sheet->getStyle("C5:O{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
		$widths = ['A' => 6, 'B' => 28, 'C' => 8, 'D' => 8, 'E' => 8, 'F' => 8, 'G' => 8, 'H' => 8, 'I' => 8, 'J' => 8, 'K' => 8, 'L' => 12, 'M' => 12, 'N' => 12, 'O' => 12];
		foreach ($widths as $col => $width) {
			$sheet->getColumnDimension($col)->setWidth($width);
		}
		$sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
		$sheet->getPageSetup()->setFitToPage(true)->setFitToWidth(1)->setFitToHeight(0);
		$sheet->getHeaderFooter()->setOddFooter('&LWisdom schools&R&P / &N');
	}

	/**
	 * @param array<string, mixed> $school
	 */
	private static function writeClassSheet(Worksheet $sheet, array $school, string $date, string $kind): void
	{
		$isHs = $kind === 'hs';
		$title = $isHs ? 'HIGH SCHOOL ATTENDANCE' : 'NURSERY AND PRIMARY ATTENDANCE';
		$sheet->mergeCells('A1:F1');
		$sheet->setCellValue('A1', $school['name']);
		$sheet->mergeCells('A2:F2');
		$sheet->setCellValue('A2', $title);
		$sheet->mergeCells('A3:F3');
		$sheet->setCellValue('A3', 'Date: ' . date('d/m/Y', strtotime($date)) . '    ·    Present figures are from the daily register');
		$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB(self::NAVY);
		$sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
		$sheet->getStyle('A3')->getFont()->setSize(9)->getColor()->setRGB('475569');

		$headers = $isHs
			? ['S/N', 'Class', 'Boys', 'Girls', 'Total', 'Boarders']
			: ['S/N', 'Class', 'Boys', 'Girls', 'Total', 'Absent'];
		$sheet->fromArray($headers, null, 'A5');
		$sheet->getStyle('A5:F5')->applyFromArray([
			'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
			'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::NAVY]],
			'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
		]);
		$sheet->freezePane('A6');

		$classes = $isHs ? ($school['hs'] ?? []) : ($school['np'] ?? []);
		$row = 6;
		$n = 1;
		$sums = ['boys' => 0, 'girls' => 0, 'total' => 0, 'extra' => 0];
		$currentGroup = '';
		foreach ($classes as $class) {
			if (!$isHs && $class['group'] !== $currentGroup) {
				if ($currentGroup !== '') {
					self::classTotalRow($sheet, $row, $currentGroup === 'nursery' ? 'NURSERY TOTAL' : 'PRIMARY TOTAL', $sums);
					$row++;
					$sums = ['boys' => 0, 'girls' => 0, 'total' => 0, 'extra' => 0];
					$n = 1;
				}
				$currentGroup = (string) $class['group'];
			}
			$extra = $isHs ? (int) $class['boarders'] : (int) $class['absent'];
			$sheet->fromArray([$n, $class['label'], (int) $class['boys'], (int) $class['girls'], (int) $class['total'], $extra], null, 'A' . $row);
			if ($n % 2 === 0) {
				$sheet->getStyle("A{$row}:F{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::ALT);
			}
			$sums['boys'] += (int) $class['boys'];
			$sums['girls'] += (int) $class['girls'];
			$sums['total'] += (int) $class['total'];
			$sums['extra'] += $extra;
			$n++;
			$row++;
		}
		$label = $isHs ? 'TOTAL' : ($currentGroup === 'nursery' ? 'NURSERY TOTAL' : 'PRIMARY TOTAL');
		self::classTotalRow($sheet, $row, $label, $sums);
		$sheet->getStyle('A5:F' . $row)->applyFromArray([
			'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
			'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
		]);
		$sheet->getStyle('C6:F' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
		foreach (['A' => 6, 'B' => 28, 'C' => 12, 'D' => 12, 'E' => 12, 'F' => 14] as $col => $width) {
			$sheet->getColumnDimension($col)->setWidth($width);
		}
		$sheet->getPageSetup()->setFitToPage(true)->setFitToWidth(1)->setFitToHeight(0);
		$sheet->getHeaderFooter()->setOddFooter('&L' . $school['short'] . '&R&P / &N');
	}

	/**
	 * @param array{boys:int,girls:int,total:int,extra:int} $sums
	 */
	private static function classTotalRow(Worksheet $sheet, int $row, string $label, array $sums): void
	{
		$sheet->fromArray(['', $label, $sums['boys'], $sums['girls'], $sums['total'], $sums['extra']], null, 'A' . $row);
		$sheet->getStyle("A{$row}:F{$row}")->applyFromArray([
			'font' => ['bold' => true],
			'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::TOTAL]],
		]);
	}

	/**
	 * @param array{date_label:string,schools:list<array<string,mixed>>} $data
	 */
	private static function writeTeachers(Worksheet $sheet, array $data): void
	{
		$sheet->mergeCells('A1:E1');
		$sheet->setCellValue('A1', 'TEACHERS ATTENDANCE SUMMARY');
		$sheet->mergeCells('A2:E2');
		$sheet->setCellValue('A2', 'Date: ' . $data['date_label'] . '    ·    Academic staff only    ·    Male and female are those present today');
		$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB(self::NAVY);
		$sheet->getStyle('A2')->getFont()->setSize(9)->getColor()->setRGB('475569');
		$labels = [
			'nursery' => 'Nursery',
			'primary' => 'Primary',
			'high_school' => 'High school',
			'unassigned' => 'Not assigned to a class',
		];
		$row = 4;
		foreach ($data['schools'] as $school) {
			$levels = $school['teacher_levels'] ?? [];
			if ($levels === []) {
				continue;
			}
			$sheet->mergeCells("A{$row}:E{$row}");
			$sheet->setCellValue('A' . $row, $school['name']);
			$sheet->getStyle("A{$row}:E{$row}")->applyFromArray([
				'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
				'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::NAVY]],
			]);
			$row++;
			$sheet->fromArray(['School level', 'Male', 'Female', 'Present', 'Missing'], null, 'A' . $row);
			$sheet->getStyle("A{$row}:E{$row}")->applyFromArray([
				'font' => ['bold' => true, 'color' => ['rgb' => self::NAVY]],
				'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::LIGHT]],
			]);
			$header = $row;
			$row++;
			$order = ['nursery', 'primary', 'high_school', 'unassigned'];
			$seenPeopleNote = false;
			foreach ($order as $key) {
				if (!isset($levels[$key])) {
					continue;
				}
				$line = $levels[$key];
				$sheet->fromArray([
					$labels[$key],
					(int) $line['male'],
					(int) $line['female'],
					(int) $line['present'],
					(int) $line['missing'],
				], null, 'A' . $row);
				$seenPeopleNote = true;
				$row++;
			}
			if ($seenPeopleNote) {
				$sheet->fromArray([
					'TOTAL',
					'',
					'',
					(int) $school['teachers']['present'],
					(int) $school['teachers']['missing'],
				], null, 'A' . $row);
				$sheet->getStyle("A{$row}:E{$row}")->applyFromArray([
					'font' => ['bold' => true],
					'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::TOTAL]],
				]);
				$sheet->getStyle('A' . $header . ':E' . $row)->applyFromArray([
					'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
				]);
				$row += 2;
			}
		}
		if ($row === 4) {
			$sheet->setCellValue('A4', 'No academic staff are due today.');
		}
		foreach (['A' => 28, 'B' => 12, 'C' => 12, 'D' => 12, 'E' => 12] as $col => $width) {
			$sheet->getColumnDimension($col)->setWidth($width);
		}
		$sheet->getPageSetup()->setFitToPage(true)->setFitToWidth(1)->setFitToHeight(0);
		$sheet->getHeaderFooter()->setOddFooter('&LTeachers&R&P / &N');
	}

	private static function isAcademicShift(string $title): bool
	{
		return stripos($title, 'academic') !== false;
	}

	private static function gender($sex): string
	{
		$g = strtoupper(trim((string) $sex));
		if (in_array($g, ['F', 'FEMALE', 'GIRL', 'W', 'WOMAN'], true)) {
			return 'F';
		}
		if (in_array($g, ['M', 'MALE', 'BOY', 'MAN'], true)) {
			return 'M';
		}
		return '';
	}

	private static function shortName(string $name): string
	{
		$short = preg_replace('/^wisdom\s+school\s+/i', '', trim($name)) ?? $name;
		$short = trim((string) $short);
		return $short !== '' ? $short : $name;
	}

	/**
	 * @return array{0:int,1:int,2:string}
	 */
	private static function classRank(string $label, string $group): array
	{
		$l = strtolower($label);
		$band = $group === 'nursery' ? 0 : ($group === 'primary' ? 1 : 2);
		if (preg_match('/\b(?:n|p|s)\s*\.?\s*(\d+)/i', $label, $m) || preg_match('/(?:nursery|primary|senior|s)\s*(\d+)/i', $l, $m)) {
			return [$band, (int) $m[1], $l];
		}
		if (strpos($l, 'baby') !== false) {
			return [0, 1, $l];
		}
		if (strpos($l, 'middle') !== false) {
			return [0, 2, $l];
		}
		if (strpos($l, 'top') !== false) {
			return [0, 3, $l];
		}
		return [$band, 50, $l];
	}

	/**
	 * @param array<string, bool> $used
	 */
	private static function sheetTitle(string $name, array &$used): string
	{
		$clean = preg_replace('/[\\\\\\/\\?\\*\\:\\[\\]]/', ' ', $name) ?? 'Sheet';
		$clean = trim((string) preg_replace('/\s+/', ' ', $clean));
		if ($clean === '') {
			$clean = 'Sheet';
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
