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
 * Each school sheet lists only the classes that school has.
 * Child schools stop at primary: nursery (N1–N3 or Baby / Middle / Top) and primary (P1–P6).
 * High school (S1–S6) and TVET appear only where those classes exist.
 * Musanze present uses the school gate for day scholars and the boarding device for boarders.
 * Other schools present uses the daily register.
 * Girls and boys follow the dashboard: a student who is not a girl is counted as a boy.
 * On the all-schools sheet, a teacher is any staff member with courses assigned this year.
 * Everyone else who is due today is support staff.
 * Each school sheet lists staff under the post they hold.
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
			if (!self::schoolHasClasses($school) && ($school['posts'] ?? []) === []) {
				continue;
			}
			$sheet = $spreadsheet->createSheet();
			$sheet->setTitle(self::sheetTitle((string) $school['short'], $used));
			self::writeSchoolSheet($sheet, $school, $date);
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
				'is_master' => $id === (int) $masterId,
				'bands' => [
					'nursery' => [],
					'primary' => [],
					'high_school' => [],
					'tvet' => [],
					'other' => [],
				],
				'teacher_levels' => [],
				'posts' => [],
			];
		}
		if ($bySchool !== []) {
			self::fillStudents($bySchool, (int) $masterId, $yearId, $date);
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
	private static function fillStudents(array &$bySchool, int $masterId, int $yearId, string $date): void
	{
		$db = \Config\Database::connect();
		if (!$db->tableExists('students') || !$db->tableExists('class_records')) {
			return;
		}
		$idList = implode(',', array_map('intval', array_keys($bySchool)));
		$yearId = (int) $yearId;
		$presentIds = self::presentStudentIds($db, $bySchool, $masterId, $date);
		$sql = "SELECT s.school_id, s.id, s.sex, s.studying_mode,
				c.id AS class_id, c.title AS class_title, l.title AS level_name, d.code AS dept_code, d.title AS dept_title
			FROM students s
			INNER JOIN schools sch ON sch.id = s.school_id
			LEFT JOIN active_term atr ON atr.id = sch.active_term
			INNER JOIN class_records cr ON cr.student = s.id
				AND cr.year = IFNULL(atr.academic_year, {$yearId})
			INNER JOIN classes c ON c.id = cr.class
			LEFT JOIN levels l ON l.id = c.level
			LEFT JOIN departments d ON d.id = c.department
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
			$girl = self::isGirl($row['sex'] ?? '');
			$present = isset($presentIds[$studentId]);
			$bySchool[$sid]['expected']++;
			if ($girl) {
				$bySchool[$sid]['expected_f']++;
			} else {
				$bySchool[$sid]['expected_m']++;
			}
			if ($present) {
				$bySchool[$sid]['present']++;
				if ($girl) {
					$bySchool[$sid]['present_f']++;
				} else {
					$bySchool[$sid]['present_m']++;
				}
			}
			$band = self::classBand(
				(string) ($row['level_name'] ?? ''),
				(string) ($row['class_title'] ?? ''),
				(string) ($row['dept_title'] ?? '')
			);
			$classId = (int) ($row['class_id'] ?? 0);
			$key = $sid . ':' . $classId;
			if (!isset($classes[$key])) {
				$label = \App\Models\SchoolFeesModel::displayLabel($row);
				$classes[$key] = [
					'school_id' => $sid,
					'band' => $band,
					'label' => $label !== '' ? $label : 'Class',
					'rank' => self::classRank($label, $band),
					'boys' => 0,
					'girls' => 0,
					'present' => 0,
					'enrolled' => 0,
				];
			}
			$classes[$key]['enrolled']++;
			if ($girl) {
				$classes[$key]['girls']++;
			} else {
				$classes[$key]['boys']++;
			}
			if ($present) {
				$classes[$key]['present']++;
			}
		}
		foreach ($classes as $class) {
			$sid = (int) $class['school_id'];
			$band = (string) $class['band'];
			if (!isset($bySchool[$sid]['bands'][$band])) {
				$band = 'other';
			}
			$bySchool[$sid]['bands'][$band][] = $class;
		}
		foreach ($bySchool as $sid => $school) {
			foreach (array_keys($school['bands']) as $band) {
				usort($bySchool[$sid]['bands'][$band], static function (array $a, array $b): int {
					return $a['rank'] <=> $b['rank'];
				});
			}
		}
	}

	/**
	 * Present students, using the same rule as the dashboard.
	 * Master: day scholars at the school gate, boarders on the boarding device.
	 * Child schools: the daily register. Child schools stop at primary, so no high-school device rule.
	 *
	 * @param array<int, array<string, mixed>> $bySchool
	 * @return array<int, true>
	 */
	private static function presentStudentIds($db, array $bySchool, int $masterId, string $date): array
	{
		$present = [];
		$childIds = [];
		foreach (array_keys($bySchool) as $sid) {
			$sid = (int) $sid;
			if ($sid > 0 && $sid !== $masterId) {
				$childIds[] = $sid;
			}
		}
		if ($childIds !== [] && $db->tableExists('daily_attendance')) {
			$idList = implode(',', $childIds);
			$day = $db->escape($date);
			$rows = $db->query(
				"SELECT DISTINCT da.student_id
				FROM daily_attendance da
				INNER JOIN students s ON s.id = da.student_id
				WHERE s.school_id IN ({$idList}) AND DATE(da.datee) = {$day}"
			)->getResultArray();
			foreach ($rows as $row) {
				$id = (int) ($row['student_id'] ?? 0);
				if ($id > 0) {
					$present[$id] = true;
				}
			}
		}
		if ($masterId < 1 || !isset($bySchool[$masterId])) {
			return $present;
		}
		$start = strtotime($date . ' 00:00:00');
		$end = $start + 86400;
		$gateIds = self::gateAreaIds($db, $masterId);
		if ($gateIds !== [] && $db->tableExists('attendance_records')) {
			$rows = $db->query(
				'SELECT DISTINCT ar.user_id
				FROM attendance_records ar
				INNER JOIN students s ON s.id = ar.user_id
				WHERE ar.school_id = ' . (int) $masterId . '
					AND ar.user_type = 0
					AND IFNULL(s.studying_mode, 1) = 1
					AND ar.area_id IN (' . implode(',', $gateIds) . ')
					AND ar.time_in >= ' . (int) $start . '
					AND ar.time_in < ' . (int) $end
			)->getResultArray();
			foreach ($rows as $row) {
				$id = (int) ($row['user_id'] ?? 0);
				if ($id > 0) {
					$present[$id] = true;
				}
			}
		}
		if ($db->tableExists('boarding_attendance')) {
			$day = $db->escape($date);
			$rows = $db->query(
				'SELECT DISTINCT ba.student_id
				FROM boarding_attendance ba
				INNER JOIN students s ON s.id = ba.student_id
				WHERE s.school_id = ' . (int) $masterId . '
					AND IFNULL(s.studying_mode, 1) = 0
					AND DATE(ba.datee) = ' . $day
			)->getResultArray();
			foreach ($rows as $row) {
				$id = (int) ($row['student_id'] ?? 0);
				if ($id > 0) {
					$present[$id] = true;
				}
			}
		}
		return $present;
	}

	/** @return list<int> */
	private static function gateAreaIds($db, int $schoolId): array
	{
		if ($schoolId < 1 || !$db->tableExists('attendance_areas')) {
			return [];
		}
		$ids = [];
		foreach ($db->table('attendance_areas')->select('id, name')->where('school_id', $schoolId)->where('active', 1)->get()->getResultArray() as $area) {
			$name = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', ' ', (string) ($area['name'] ?? ''))));
			if ($name === 'gate' || $name === 'school gate' || strpos($name, 'school gate') !== false || substr($name, -5) === ' gate') {
				$ids[] = (int) $area['id'];
			}
		}
		return $ids;
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
		$fields = $db->getFieldNames('staffs');
		$hasSex = in_array('sex', $fields, true);
		$sexCol = $hasSex ? 's.sex' : "'' AS sex";
		$rows = $db->query(
			"SELECT s.id, s.school_id, s.fname, s.lname, {$sexCol}, p.title AS post_title, sh.title AS shift_title, sh.options AS shift_options
			FROM staffs s
			LEFT JOIN posts p ON p.id = s.post
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
			$hasCourses = isset($levels[$staffId]);
			$bucket = $hasCourses ? 'teachers' : 'support';
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
			$post = trim((string) ($staff['post_title'] ?? ''));
			if ($post === '') {
				$post = 'No post';
			}
			$name = trim(trim((string) ($staff['fname'] ?? '')) . ' ' . trim((string) ($staff['lname'] ?? '')));
			$bySchool[$sid]['posts'][$post][] = [
				'name' => $name !== '' ? $name : 'Staff',
				'present' => $present ? 1 : 0,
			];
			if (!$hasCourses) {
				continue;
			}
			$sexKnown = trim((string) ($staff['sex'] ?? '')) !== '';
			$groups = $levels[$staffId];
			foreach ($groups as $group) {
				if (!isset($bySchool[$sid]['teacher_levels'][$group])) {
					$bySchool[$sid]['teacher_levels'][$group] = [
						'male' => 0, 'female' => 0, 'present' => 0, 'missing' => 0,
					];
				}
				if ($present) {
					$bySchool[$sid]['teacher_levels'][$group]['present']++;
					if ($sexKnown && self::isGirl($staff['sex'] ?? '')) {
						$bySchool[$sid]['teacher_levels'][$group]['female']++;
					} elseif ($sexKnown) {
						$bySchool[$sid]['teacher_levels'][$group]['male']++;
					}
				} else {
					$bySchool[$sid]['teacher_levels'][$group]['missing']++;
				}
			}
		}
		foreach ($bySchool as $sid => $school) {
			if (($school['posts'] ?? []) === []) {
				continue;
			}
			uksort($bySchool[$sid]['posts'], static function (string $a, string $b): int {
				return strcasecmp($a, $b);
			});
			foreach ($bySchool[$sid]['posts'] as $post => $people) {
				usort($people, static function (array $a, array $b): int {
					return strcasecmp((string) $a['name'], (string) $b['name']);
				});
				$bySchool[$sid]['posts'][$post] = $people;
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
			"SELECT cr.lecturer, l.title AS level_name, c.title AS class_title, d.title AS dept_title
			FROM course_records cr
			INNER JOIN classes c ON c.id = cr.class
			INNER JOIN schools sch ON sch.id = c.school_id
			LEFT JOIN active_term atr ON atr.id = sch.active_term
			LEFT JOIN levels l ON l.id = c.level
			LEFT JOIN departments d ON d.id = c.department
			WHERE c.school_id IN ({$idList})
				AND cr.lecturer > 0
				AND cr.year = IFNULL(atr.academic_year, " . (int) $yearId . ")"
		)->getResultArray();
		foreach ($rows as $row) {
			$lecturer = (int) ($row['lecturer'] ?? 0);
			if ($lecturer < 1) {
				continue;
			}
			$group = self::classBand(
				(string) ($row['level_name'] ?? ''),
				(string) ($row['class_title'] ?? ''),
				(string) ($row['dept_title'] ?? '')
			);
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
		$sheet->setCellValue('A2', 'Date ' . $data['date_label'] . '    ·    Enrolled students match the dashboard, holiday classes excluded    ·    Musanze present = school gate for day scholars and boarding device for boarders    ·    Other schools present = daily register    ·    Teachers = staff with assigned courses this year    ·    Support staff = everyone else due today');
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
	private static function schoolHasClasses(array $school): bool
	{
		foreach (self::bandOrder() as $band) {
			if (($school['bands'][$band] ?? []) !== []) {
				return true;
			}
		}
		return false;
	}

	/** @return list<string> */
	private static function bandOrder(): array
	{
		return ['nursery', 'primary', 'tvet', 'high_school', 'other'];
	}

	private static function bandLabel(string $band): string
	{
		$labels = [
			'nursery' => 'NURSERY',
			'primary' => 'PRIMARY',
			'tvet' => 'TVET',
			'high_school' => 'HIGH SCHOOL',
			'other' => 'OTHER CLASSES',
		];
		return $labels[$band] ?? strtoupper($band);
	}

	/**
	 * One sheet per school, with a section only for a stage that school actually has.
	 *
	 * @param array<string, mixed> $school
	 */
	private static function writeSchoolSheet(Worksheet $sheet, array $school, string $date): void
	{
		$sheet->mergeCells('A1:G1');
		$sheet->setCellValue('A1', $school['name']);
		$sheet->mergeCells('A2:G2');
		$sheet->setCellValue('A2', 'ATTENDANCE BY CLASS AND STAFF POST');
		$presentNote = !empty($school['is_master'])
			? 'Present = school gate for day scholars and boarding device for boarders'
			: 'Present = daily register';
		$sheet->mergeCells('A3:G3');
		$sheet->setCellValue('A3', 'Date: ' . date('d/m/Y', strtotime($date)) . '    ·    Girls and boys are enrolled students    ·    ' . $presentNote);
		$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB(self::NAVY);
		$sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
		$sheet->getStyle('A3')->getFont()->setSize(9)->getColor()->setRGB('475569');
		$sheet->getRowDimension(1)->setRowHeight(22);
		$sheet->getRowDimension(3)->setRowHeight(18);

		$row = 5;
		$firstTable = 0;
		$lastTable = 0;
		foreach (self::bandOrder() as $band) {
			$classes = $school['bands'][$band] ?? [];
			if ($classes === []) {
				continue;
			}
			$sheet->mergeCells("A{$row}:G{$row}");
			$sheet->setCellValue('A' . $row, self::bandLabel($band));
			$sheet->getStyle("A{$row}:G{$row}")->applyFromArray([
				'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
				'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::NAVY]],
			]);
			$row++;
			$header = $row;
			if ($firstTable === 0) {
				$firstTable = $header;
			}
			$sheet->fromArray(['S/N', 'Class', 'Girls', 'Boys', 'Enrolled', 'Present', 'Absent'], null, 'A' . $row);
			$sheet->getStyle("A{$row}:G{$row}")->applyFromArray([
				'font' => ['bold' => true, 'color' => ['rgb' => self::NAVY], 'size' => 9],
				'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::LIGHT]],
				'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
			]);
			$row++;
			$n = 1;
			$sums = ['girls' => 0, 'boys' => 0, 'enrolled' => 0, 'present' => 0, 'absent' => 0];
			foreach ($classes as $class) {
				$enrolled = (int) $class['enrolled'];
				$present = (int) $class['present'];
				$absent = max(0, $enrolled - $present);
				$sheet->fromArray([
					$n,
					$class['label'],
					(int) $class['girls'],
					(int) $class['boys'],
					$enrolled,
					$present,
					$absent,
				], null, 'A' . $row);
				if ($n % 2 === 0) {
					$sheet->getStyle("A{$row}:G{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::ALT);
				}
				$sums['girls'] += (int) $class['girls'];
				$sums['boys'] += (int) $class['boys'];
				$sums['enrolled'] += $enrolled;
				$sums['present'] += $present;
				$sums['absent'] += $absent;
				$n++;
				$row++;
			}
			self::classTotalRow($sheet, $row, self::bandLabel($band) . ' TOTAL', $sums);
			$sheet->getStyle('A' . $header . ':G' . $row)->applyFromArray([
				'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
				'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
			]);
			$sheet->getStyle('C' . ($header + 1) . ':G' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
			$lastTable = $row;
			$row += 2;
		}
		self::writeStaffPosts($sheet, $school, $row, $firstTable);
		if ($firstTable > 0) {
			$sheet->freezePane('A' . ($firstTable + 1));
		}
		foreach (['A' => 6, 'B' => 36, 'C' => 12, 'D' => 12, 'E' => 12, 'F' => 12, 'G' => 12] as $col => $width) {
			$sheet->getColumnDimension($col)->setWidth($width);
		}
		$sheet->getPageSetup()->setFitToPage(true)->setFitToWidth(1)->setFitToHeight(0);
		$sheet->getHeaderFooter()->setOddFooter('&L' . $school['short'] . '&R&P / &N');
		unset($lastTable);
	}

	/**
	 * Staff due today, grouped by the post they hold.
	 *
	 * @param array<string, mixed> $school
	 */
	private static function writeStaffPosts(Worksheet $sheet, array $school, int $row, int &$firstTable): void
	{
		$posts = $school['posts'] ?? [];
		if ($posts === []) {
			return;
		}
		$sheet->mergeCells("A{$row}:D{$row}");
		$sheet->setCellValue('A' . $row, 'STAFF BY POST');
		$sheet->getStyle("A{$row}:D{$row}")->applyFromArray([
			'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 12],
			'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::TEAL]],
		]);
		$row += 2;
		foreach ($posts as $post => $people) {
			$sheet->mergeCells("A{$row}:D{$row}");
			$sheet->setCellValue('A' . $row, (string) $post);
			$sheet->getStyle("A{$row}:D{$row}")->applyFromArray([
				'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
				'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::NAVY]],
			]);
			$row++;
			$header = $row;
			if ($firstTable === 0) {
				$firstTable = $header;
			}
			$sheet->fromArray(['S/N', 'Name', 'Present', 'Absent'], null, 'A' . $row);
			$sheet->getStyle("A{$row}:D{$row}")->applyFromArray([
				'font' => ['bold' => true, 'color' => ['rgb' => self::NAVY], 'size' => 9],
				'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::LIGHT]],
				'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
			]);
			$row++;
			$n = 1;
			$present = 0;
			$absent = 0;
			foreach ($people as $person) {
				$isIn = (int) ($person['present'] ?? 0) === 1;
				$sheet->fromArray([$n, (string) $person['name'], $isIn ? 1 : 0, $isIn ? 0 : 1], null, 'A' . $row);
				if ($n % 2 === 0) {
					$sheet->getStyle("A{$row}:D{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::ALT);
				}
				if ($isIn) {
					$present++;
				} else {
					$absent++;
				}
				$n++;
				$row++;
			}
			$sheet->fromArray(['', strtoupper((string) $post) . ' TOTAL', $present, $absent], null, 'A' . $row);
			$sheet->getStyle("A{$row}:D{$row}")->applyFromArray([
				'font' => ['bold' => true],
				'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::TOTAL]],
			]);
			$sheet->getStyle('A' . $header . ':D' . $row)->applyFromArray([
				'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
				'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
			]);
			$sheet->getStyle('A' . ($header + 1) . ':A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
			$sheet->getStyle('C' . ($header + 1) . ':D' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
			$row += 2;
		}
	}

	/**
	 * @param array{girls:int,boys:int,enrolled:int,present:int,absent:int} $sums
	 */
	private static function classTotalRow(Worksheet $sheet, int $row, string $label, array $sums): void
	{
		$sheet->fromArray(['', $label, $sums['girls'], $sums['boys'], $sums['enrolled'], $sums['present'], $sums['absent']], null, 'A' . $row);
		$sheet->getStyle("A{$row}:G{$row}")->applyFromArray([
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
		$sheet->setCellValue('A2', 'Date: ' . $data['date_label'] . '    ·    Staff with assigned courses this year    ·    Male and female are those present today');
		$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB(self::NAVY);
		$sheet->getStyle('A2')->getFont()->setSize(9)->getColor()->setRGB('475569');
		$labels = [
			'nursery' => 'Nursery',
			'primary' => 'Primary',
			'tvet' => 'TVET',
			'high_school' => 'High school',
			'other' => 'Other classes',
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
			$order = ['nursery', 'primary', 'tvet', 'high_school', 'other', 'unassigned'];
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

	private static function isGirl($sex): bool
	{
		$g = strtoupper(trim((string) $sex));
		if ($g === '') {
			return false;
		}
		if (in_array($g, ['F', 'FEMALE', 'GIRL', 'GIRLS', 'FEMININ', 'FEMININE', 'GORE'], true)) {
			return true;
		}
		return $g[0] === 'F';
	}

	private static function gender($sex): string
	{
		return self::isGirl($sex) ? 'F' : 'M';
	}

	private static function shortName(string $name): string
	{
		$short = preg_replace('/^wisdom\s+school\s+/i', '', trim($name)) ?? $name;
		$short = trim((string) $short);
		return $short !== '' ? $short : $name;
	}

	/**
	 * Nursery, primary, TVET, or high school. High school is only S1–S6 and senior labels.
	 * N1–N3, Baby, Middle, and Top stay nursery. Child schools stop at primary.
	 */
	private static function classBand(string $level, string $class, string $dept = ''): string
	{
		$level = strtolower(trim($level));
		$class = strtolower(trim($class));
		$dept = strtolower(trim($dept));
		$hay = trim($level . ' ' . $class . ' ' . $dept);
		if ($hay === '') {
			return 'other';
		}
		$nursery = strpos($hay, 'nursery') !== false
			|| strpos($hay, 'maternelle') !== false
			|| strpos($hay, 'baby') !== false
			|| strpos($hay, 'middle class') !== false
			|| strpos($hay, 'top class') !== false
			|| preg_match('/(^|[^a-z0-9])n[1-3]([^0-9]|$)/', $hay) === 1;
		if ($nursery) {
			return 'nursery';
		}
		if (strpos($level, 'primary') !== false || strpos($class, 'primary') !== false || preg_match('/(^|[^a-z0-9])p[1-6]([^0-9]|$)/', $level . ' ' . $class) === 1) {
			return 'primary';
		}
		if (preg_match('/\blevel\s*[0-9]/', $hay) === 1 || strpos($hay, 'tvet') !== false) {
			return 'tvet';
		}
		if (
			preg_match('/(^|[^a-z0-9])s[1-6]([^0-9]|$)/', $level . ' ' . $class) === 1
			|| strpos($hay, 'senior') !== false
			|| strpos($hay, 'high school') !== false
			|| strpos($hay, 'secondary') !== false
			|| preg_match('/\bo[\s\']*-?level\b/', $hay) === 1
			|| preg_match('/\ba[\s\']*-?level\b/', $hay) === 1
		) {
			return 'high_school';
		}
		return 'other';
	}

	/**
	 * @return array{0:int,1:int,2:string}
	 */
	private static function classRank(string $label, string $group): array
	{
		$l = strtolower($label);
		$order = ['nursery' => 0, 'primary' => 1, 'tvet' => 2, 'high_school' => 3, 'other' => 4];
		$band = $order[$group] ?? 4;
		if (preg_match('/\b(?:level|n|p|s)\s*\.?\s*(\d+)/i', $label, $m)) {
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
