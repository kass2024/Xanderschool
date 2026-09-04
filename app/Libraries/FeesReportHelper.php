<?php

namespace App\Libraries;

use App\Models\ExtraFeesModel;
use App\Models\FacultyModel;
use App\Models\StudentModel;

/**
 * Fees report helpers: report types, student enrichment, income summary.
 */
class FeesReportHelper
{
	public const TYPE_DETAILED = 'detailed';
	public const TYPE_CLASS_BALANCE = 'class_balance';
	public const TYPE_INCOME_SUMMARY = 'income_summary';

	public const FEES_BOTH = 'both';
	public const FEES_SCHOOL = 'school';
	public const FEES_EXTRA = 'extra';

	public static function normalizeReportType(?string $rtype): string
	{
		$rtype = strtolower(trim((string) $rtype));
		if (in_array($rtype, [self::TYPE_CLASS_BALANCE, self::TYPE_INCOME_SUMMARY], true)) {
			return $rtype;
		}
		return self::TYPE_DETAILED;
	}

	public static function normalizeFeesScope(?string $scope): string
	{
		$scope = strtolower(trim((string) $scope));
		if (in_array($scope, [self::FEES_SCHOOL, self::FEES_EXTRA], true)) {
			return $scope;
		}
		return self::FEES_BOTH;
	}

	/** @return array<string, string> */
	public static function reportTypeLabels(): array
	{
		return [
			self::TYPE_DETAILED => 'Detailed fees report',
			self::TYPE_CLASS_BALANCE => 'Class balance list',
			self::TYPE_INCOME_SUMMARY => 'Summary of income',
		];
	}

	/** @return array<string, string> */
	public static function feesScopeLabels(): array
	{
		return [
			self::FEES_BOTH => 'Both',
			self::FEES_SCHOOL => 'School fees',
			self::FEES_EXTRA => 'Extra fees',
		];
	}

	public static function needsClassFilter(string $reportType): bool
	{
		return $reportType !== self::TYPE_INCOME_SUMMARY;
	}

	public static function classDisplayLabel(array $classRow): string
	{
		$parts = [];
		$level = self::normalizeLevelLabel(trim((string) ($classRow['level_name'] ?? '')));
		$dept = trim((string) ($classRow['dept_code'] ?? ''));
		$title = trim((string) ($classRow['title'] ?? ''));
		if ($title === '-----') {
			$title = '';
		}
		if ($level !== '') {
			$parts[] = $level;
		}
		if ($dept !== '') {
			$parts[] = $dept;
		}
		if ($title !== '') {
			$parts[] = $title;
		}
		if ($parts === []) {
			$fallback = trim((string) ($classRow['department_name'] ?? ''));
			if ($fallback !== '') {
				$parts[] = $fallback;
			}
		}

		return strtoupper(trim(implode(' ', $parts)));
	}

	/** e.g. "level 3" → "L3" for RTB classes like SOD. */
	public static function normalizeLevelLabel(string $level): string
	{
		if ($level === '') {
			return '';
		}
		if (preg_match('/^level\s*(\d+)$/i', $level, $m)) {
			return 'L' . $m[1];
		}

		return $level;
	}

	/** Faculty type from a class row (fees report uses f.type without alias). */
	public static function classFacultyType(array $classRow): int
	{
		return (int) ($classRow['faculty_type'] ?? $classRow['type'] ?? 0);
	}

	/**
	 * Holiday / vacation programme classes are excluded from fees reports.
	 */
	public static function isHolidayClass(array $classRow): bool
	{
		$hay = strtolower(trim(
			($classRow['level_name'] ?? '') . ' '
			. ($classRow['dept_code'] ?? '') . ' '
			. ($classRow['title'] ?? '') . ' '
			. ($classRow['department_name'] ?? '')
		));
		return (bool) preg_match('/\b(holiday|vacation|vacances)\b/', $hay);
	}

	/**
	 * @param list<array<string,mixed>> $classes
	 * @return list<array<string,mixed>>
	 */
	public static function filterReportClasses(array $classes): array
	{
		return array_values(array_filter($classes, static function ($classRow) {
			return !self::isHolidayClass($classRow);
		}));
	}

	public static function formatPercent(float $pct): string
	{
		return number_format(max(0, $pct), 2) . '%';
	}

	public static function percentLevel(float $pct): string
	{
		$pct = max(0, $pct);
		if ($pct >= 100) {
			return 'full';
		}
		if ($pct >= 50) {
			return 'good';
		}
		if ($pct > 0) {
			return 'partial';
		}
		return 'zero';
	}

	/**
	 * Normalize one student's expected vs paid — never negative totals.
	 *
	 * @return array{due:float,paid:float,balance:float,percent:float}
	 */
	public static function normalizeCollectionAmounts(float $expected, float $paid): array
	{
		$expected = max(0.0, $expected);
		$paid = max(0.0, $paid);
		$collected = min($paid, $expected);
		$balance = max(0.0, $expected - $collected);
		$percent = $expected > 0
			? min(100.0, ($collected / $expected) * 100)
			: ($collected > 0 ? 100.0 : 0.0);

		return [
			'due' => $expected,
			'paid' => $collected,
			'balance' => $balance,
			'percent' => $percent,
		];
	}

	/**
	 * @return array{total_due:float,total_paid:float,balance:float,percent:float}
	 */
	public static function finalizeClassTotals(float $totalDue, float $totalPaid): array
	{
		$totalDue = max(0.0, $totalDue);
		$totalPaid = max(0.0, $totalPaid);
		if ($totalPaid > $totalDue) {
			$totalPaid = $totalDue;
		}
		$balance = max(0.0, $totalDue - $totalPaid);
		$percent = $totalDue > 0
			? min(100.0, ($totalPaid / $totalDue) * 100)
			: ($totalPaid > 0 ? 100.0 : 0.0);

		return [
			'total_due' => $totalDue,
			'total_paid' => $totalPaid,
			'balance' => $balance,
			'percent' => $percent,
		];
	}

	/**
	 * @param list<array<string,mixed>> $rows
	 * @return array{total_due:float,total_paid:float,balance:float,percent:float}
	 */
	public static function summarizeIncomeRows(array $rows): array
	{
		$due = 0.0;
		$paid = 0.0;
		foreach ($rows as $row) {
			if (!empty($row['is_subtotal'])) {
				continue;
			}
			$due += max(0.0, (float) ($row['total_due'] ?? 0));
			$paid += max(0.0, (float) ($row['total_paid'] ?? 0));
		}

		return self::finalizeClassTotals($due, $paid);
	}

	public static function sectionHeadingLabel(string $section): string
	{
		switch ($section) {
			case 'nursery':
				return 'Nursery';
			case 'primary':
				return 'Primary';
			case 'reb':
				return 'REB';
			case 'rtb':
				return 'RTB';
			case 'anp':
				return 'Nursing ANP';
			default:
				return strtoupper($section);
		}
	}

	public static function schoolSection(array $classRow): string
	{
		$facType = self::classFacultyType($classRow);
		$hay = strtolower(trim(
			($classRow['level_name'] ?? '') . ' '
			. ($classRow['dept_code'] ?? '') . ' '
			. ($classRow['title'] ?? '') . ' '
			. ($classRow['department_name'] ?? '') . ' '
			. ($classRow['faculty_code'] ?? '') . ' '
			. ($classRow['faculty_title'] ?? '')
		));

		if ($facType === FacultyModel::TYPE_SPECIAL
			|| strcasecmp(trim((string) ($classRow['dept_code'] ?? '')), 'ANP') === 0
			|| preg_match('/\b(anp|nursing anp)\b/', $hay)) {
			return 'anp';
		}
		if ($facType === FacultyModel::TYPE_TVET) {
			return 'rtb';
		}
		if (preg_match('/\b(nursery|baby class|middle class|top class|n1|n2|n3)\b/', $hay)) {
			return 'nursery';
		}
		if (preg_match('/\b(primary|p1|p2|p3|p4|p5|p6)\b/', $hay)) {
			return 'primary';
		}
		if ($facType === FacultyModel::TYPE_REB) {
			return 'reb';
		}

		return 'reb';
	}

	public static function sectionLabel(string $section): string
	{
		switch ($section) {
			case 'nursery':
				return 'TOTAL FOR NURSERY';
			case 'primary':
				return 'TOTAL FOR PRIMARY';
			case 'reb':
				return 'TOTAL FOR REB';
			case 'rtb':
				return 'TOTAL FOR RTB';
			case 'anp':
				return 'TOTAL FOR ANP';
			default:
				return 'TOTAL FOR ' . strtoupper($section);
		}
	}

	public static function classSortKey(array $classRow): string
	{
		$level = self::normalizeLevelLabel(trim((string) ($classRow['level_name'] ?? '')));
		$dept = trim((string) ($classRow['dept_code'] ?? ''));
		$title = trim((string) ($classRow['title'] ?? ''));
		if ($title === '-----') {
			$title = '';
		}
		$levelNum = 999;
		if (preg_match('/\bL?\s*(\d+)\b/i', $level, $m)) {
			$levelNum = (int) $m[1];
		} elseif (preg_match('/^([A-Z]+)(\d+)$/i', $level, $m)) {
			$levelNum = (int) $m[2];
		}
		return sprintf('L-%05d-%s-%s', $levelNum, strtoupper($dept), strtoupper($title));
	}

	/**
	 * @return \CodeIgniter\Database\BaseBuilder
	 */
	public static function studentsQuery(
		StudentModel $studentMdl,
		int $classId,
		int $schoolId,
		int $academic,
		string $termsIn,
		array $termsList,
		string $feesScope = self::FEES_BOTH
	) {
		$feesScope = self::normalizeFeesScope($feesScope);
		$schoolAmtExpr = '(COALESCE(sf.amount,0) + coalesce(fd.amount,0))';
		$extraAmtExpr = '((CASE WHEN students.studying_mode = 0 THEN COALESCE(ex.boarding_amount,0) ELSE COALESCE(ex.day_amount,0) END) + COALESCE(student.amount,0))';
		$schoolPaidExpr = '(COALESCE(fr.amount,0))';
		$extraPaidExpr = '(COALESCE(extraPaid.amount,0) + COALESCE(extraPaidSingle.amount,0))';
		if ($feesScope === self::FEES_SCHOOL) {
			$amountExpr = $schoolAmtExpr;
			$paidExpr = $schoolPaidExpr;
		} elseif ($feesScope === self::FEES_EXTRA) {
			$amountExpr = $extraAmtExpr;
			$paidExpr = $extraPaidExpr;
		} else {
			$amountExpr = '(' . $schoolAmtExpr . ' + ' . $extraAmtExpr . ')';
			$paidExpr = '(' . $schoolPaidExpr . ' + ' . $extraPaidExpr . ')';
		}

		return $studentMdl->select("concat(students.fname,' ',students.lname) as student,students.id as student_id,
		students.studying_mode,ft_phone,mt_phone,gd_phone,
		students.regno,
		students.sex,
		cl.id,cl.title as class,
		d.title as department_name,
		d.code as dept_code,
		l.title as level_name,
		,f.type,f.abbrev as faculty_code,
		{$schoolAmtExpr} as school_amount,
		{$extraAmtExpr} as extra_amount,
		{$amountExpr} as amount,
		{$schoolPaidExpr} as school_paid,
		{$extraPaidExpr} as extra_paid,
		{$paidExpr} as paid")
			->join('class_records cr', 'cr.student=students.id')
			->join('classes cl', 'cl.id=cr.class')
			->join('departments d', 'd.id=cl.department')
			->join('levels l', 'l.id=cl.level')
			->join('faculty f', 'f.id=d.faculty_id')
			->join("(select sum(sf.amount) as amount,sf.level,sf.department from school_fees sf where sf.term IN ($termsIn) and
			sf.academic_year=$academic and sf.school_id = $schoolId group by sf.level,sf.department) sf", 'sf.level=l.id and sf.department=d.id', 'LEFT')
			->join("(select sum(fd.amount) as amount,fd.student,sf.level,sf.department from school_fees_discount fd inner join school_fees sf on sf.id=fd.feesId where sf.term IN ($termsIn) and sf.academic_year=$academic and sf.school_id = $schoolId group by fd.student,sf.level,sf.department) fd", 'fd.level=l.id and fd.department=d.id AND fd.student=students.id', 'LEFT')
			->join("(select sum(COALESCE(ex.amount_boarding, ex.amount)) as boarding_amount, sum(COALESCE(ex.amount_day, ex.amount)) as day_amount,ex.type_id from extra_fees ex where ex.type=0 and ex.term IN ($termsIn) and
			ex.academic_year=$academic and ex.school_id = $schoolId group by ex.type_id) ex", 'ex.type_id=cl.id', 'LEFT')
			->join("(select sum(ex.amount) as amount,ex.type_id from extra_fees ex where ex.type=1 and ex.term IN ($termsIn) and
			ex.academic_year=$academic and ex.school_id = $schoolId
			AND NOT EXISTS (
				SELECT 1 FROM extra_fees cx
				INNER JOIN class_records crx ON crx.student = ex.type_id AND crx.year = ex.academic_year
				WHERE cx.school_id = ex.school_id AND cx.academic_year = ex.academic_year
					AND cx.term = ex.term AND cx.type = 0 AND cx.type_id = crx.class
					AND LOWER(cx.title) = LOWER(ex.title)
			)
			group by ex.type_id) student", 'student.type_id=students.id', 'LEFT')
			->join("(select fr.student_id,sum(fr.amount) as amount from fees_records fr inner join school_fees sc ON sc.id = fr.fees_id
			where fr.fees_type=0 and fr.status=1 and fr.amount > 1 and sc.term IN ($termsIn) and sc.academic_year=$academic and sc.school_id = $schoolId group by fr.student_id) fr", 'fr.student_id=students.id', 'LEFT')
			->join("(select fr.student_id,sum(fr.amount) as amount from fees_records fr inner join extra_fees ex ON ex.id = fr.fees_id
			where fr.fees_type=1 and fr.status=1 and fr.amount > 1 and ex.type_id=$classId and ex.type=0 and ex.term IN ($termsIn) and ex.academic_year=$academic and ex.school_id = $schoolId group by fr.student_id) extraPaid", 'extraPaid.student_id=students.id', 'LEFT')
			->join("(select fr.student_id,sum(fr.amount) as amount from fees_records fr
			inner join extra_fees ex ON ex.id = fr.fees_id and ex.type_id = fr.student_id
			where fr.fees_type=1 and fr.status=1 and fr.amount > 1 and ex.type=1 and ex.term IN ($termsIn) and ex.academic_year=$academic and ex.school_id = $schoolId group by fr.student_id) extraPaidSingle", 'extraPaidSingle.student_id=students.id', 'LEFT')
			->where('cr.year', $academic)
			->where('cl.id', $classId)
			->groupBy('students.id');
	}

	/**
	 * @param list<array<string,mixed>> $students
	 * @return array{students: list<array<string,mixed>>, extraFeeColumns: list<string>}
	 */
	public static function enrichStudents(
		array $students,
		int $schoolId,
		int $academic,
		array $termsList,
		int $classId,
		bool $withDetailed = true,
		bool $withLastPayment = false,
		string $feesScope = self::FEES_BOTH
	): array {
		$feesScope = self::normalizeFeesScope($feesScope);
		$includeSchool = ($feesScope !== self::FEES_EXTRA);
		$includeExtra = ($feesScope !== self::FEES_SCHOOL);
		$refMap = [];
		$actorMap = [];
		$paymentModeMap = [];
		$lastPaymentMap = [];
		$extraFeeColumns = [];
		$classExtraDefs = [];
		$studentExtraDefs = [];
		$extraPaidMap = [];
		$studentIds = array_values(array_unique(array_filter(array_map('intval', array_column($students, 'student_id')))));
		$db = \Config\Database::connect();

		if ($withDetailed && $includeExtra && $classId > 0) {
			$classExtras = $db->table('extra_fees')
				->select('id, title, term, amount, amount_boarding, amount_day')
				->where('school_id', $schoolId)
				->where('academic_year', $academic)
				->where('type', 0)
				->where('type_id', $classId)
				->whereIn('term', $termsList)
				->orderBy('title', 'ASC')
				->orderBy('term', 'ASC')
				->get()->getResultArray();
			foreach ($classExtras as $ex) {
				$title = trim((string) ($ex['title'] ?? ''));
				if ($title === '') {
					continue;
				}
				if (!isset($classExtraDefs[$title])) {
					$classExtraDefs[$title] = [];
					$extraFeeColumns[] = $title;
				}
				$classExtraDefs[$title][] = $ex;
			}
		}

		if ($studentIds !== []) {
			$refSelect = "fr.student_id,
					GROUP_CONCAT(DISTINCT NULLIF(fr.refNo, '') ORDER BY fr.id SEPARATOR ', ') AS ref_nos,
					GROUP_CONCAT(DISTINCT NULLIF(TRIM(CONCAT(COALESCE(st.fname,''),' ',COALESCE(st.lname,''))), '') ORDER BY fr.id SEPARATOR ', ') AS recorded_by_names";
			if ($withDetailed) {
				$refSelect .= ", GROUP_CONCAT(DISTINCT fr.payment_mode ORDER BY fr.payment_mode SEPARATOR ',') AS payment_modes";
			}
			$refQuery = $db->table('fees_records fr')
				->select($refSelect, false)
				->join('school_fees sc', 'sc.id = fr.fees_id AND fr.fees_type = 0', 'left')
				->join('extra_fees ex', 'ex.id = fr.fees_id AND fr.fees_type = 1', 'left')
				->join('staffs st', 'st.id = fr.created_by', 'left')
				->where('fr.status', 1)
				->whereIn('fr.student_id', $studentIds)
				->groupStart();
			if ($includeSchool) {
				$refQuery->groupStart()
					->where('fr.fees_type', 0)
					->whereIn('sc.term', $termsList)
					->where('sc.academic_year', $academic)
					->where('sc.school_id', $schoolId)
				->groupEnd();
			}
			if ($includeSchool && $includeExtra) {
				$refQuery->orGroupStart();
			}
			if ($includeExtra) {
				if (!$includeSchool) {
					$refQuery->groupStart();
				}
				$refQuery->where('fr.fees_type', 1)
					->whereIn('ex.term', $termsList)
					->where('ex.academic_year', $academic)
					->where('ex.school_id', $schoolId);
				$refQuery->groupEnd();
			}
			$refQuery->groupEnd()
				->groupBy('fr.student_id');
			$refRows = $refQuery->get()->getResultArray();
			foreach ($refRows as $refRow) {
				$id = (int) ($refRow['student_id'] ?? 0);
				if ($id < 1) {
					continue;
				}
				$refs = trim((string) ($refRow['ref_nos'] ?? ''));
				if ($refs !== '') {
					$refMap[$id] = $refs;
				}
				$actors = trim((string) ($refRow['recorded_by_names'] ?? ''));
				if ($actors !== '') {
					$actorMap[$id] = $actors;
				}
				if ($withDetailed) {
					$modeCodes = array_filter(array_map('trim', explode(',', (string) ($refRow['payment_modes'] ?? ''))));
					$modeLabels = [];
					foreach ($modeCodes as $code) {
						$label = paymentModeToString((string) $code);
						if ($label !== '' && !in_array($label, $modeLabels, true)) {
							$modeLabels[] = $label;
						}
					}
					if ($modeLabels !== []) {
						$paymentModeMap[$id] = implode(', ', $modeLabels);
					}
				}
			}

			if ($withLastPayment) {
				$lastQuery = $db->table('fees_records fr')
					->select('fr.student_id, MAX(fr.created_at) AS last_payment', false)
					->join('school_fees sc', 'sc.id = fr.fees_id AND fr.fees_type = 0', 'left')
					->join('extra_fees ex', 'ex.id = fr.fees_id AND fr.fees_type = 1', 'left')
					->where('fr.status', 1)
					->whereIn('fr.student_id', $studentIds)
					->groupStart();
				if ($includeSchool) {
					$lastQuery->groupStart()
						->where('fr.fees_type', 0)
						->whereIn('sc.term', $termsList)
						->where('sc.academic_year', $academic)
						->where('sc.school_id', $schoolId)
					->groupEnd();
				}
				if ($includeSchool && $includeExtra) {
					$lastQuery->orGroupStart();
				}
				if ($includeExtra) {
					if (!$includeSchool) {
						$lastQuery->groupStart();
					}
					$lastQuery->where('fr.fees_type', 1)
						->whereIn('ex.term', $termsList)
						->where('ex.academic_year', $academic)
						->where('ex.school_id', $schoolId);
					$lastQuery->groupEnd();
				}
				$lastRows = $lastQuery->groupEnd()
					->groupBy('fr.student_id')
					->get()->getResultArray();
				foreach ($lastRows as $lastRow) {
					$id = (int) ($lastRow['student_id'] ?? 0);
					if ($id > 0 && !empty($lastRow['last_payment'])) {
						$lastPaymentMap[$id] = (string) $lastRow['last_payment'];
					}
				}
			}

			if ($withDetailed && $includeExtra) {
				$studentExtras = $db->table('extra_fees')
					->select('id, title, term, amount, amount_boarding, amount_day, type_id')
					->where('school_id', $schoolId)
					->where('academic_year', $academic)
					->where('type', 1)
					->whereIn('type_id', $studentIds)
					->whereIn('term', $termsList)
					->orderBy('title', 'ASC')
					->orderBy('term', 'ASC')
					->get()->getResultArray();
				foreach ($studentExtras as $ex) {
					$title = trim((string) ($ex['title'] ?? ''));
					$sid = (int) ($ex['type_id'] ?? 0);
					if ($title === '' || $sid < 1) {
						continue;
					}
					if (!isset($classExtraDefs[$title]) && !in_array($title, $extraFeeColumns, true)) {
						$extraFeeColumns[] = $title;
					}
					$studentExtraDefs[$sid][$title][] = $ex;
				}

				$paidRows = $db->table('fees_records fr')
					->select('fr.student_id, ex.title, SUM(fr.amount) AS paid', false)
					->join('extra_fees ex', 'ex.id = fr.fees_id')
					->where('fr.fees_type', 1)
					->where('fr.status', 1)
					->where('fr.amount >', 1)
					->whereIn('fr.student_id', $studentIds)
					->whereIn('ex.term', $termsList)
					->where('ex.academic_year', $academic)
					->where('ex.school_id', $schoolId)
					->groupBy('fr.student_id')
					->groupBy('ex.title')
					->get()->getResultArray();
				foreach ($paidRows as $paidRow) {
					$sid = (int) ($paidRow['student_id'] ?? 0);
					$title = trim((string) ($paidRow['title'] ?? ''));
					if ($sid < 1 || $title === '') {
						continue;
					}
					$extraPaidMap[$sid][$title] = (float) ($paidRow['paid'] ?? 0);
					if (!in_array($title, $extraFeeColumns, true)) {
						$extraFeeColumns[] = $title;
					}
				}
			}
		}

		sort($extraFeeColumns, SORT_NATURAL | SORT_FLAG_CASE);
		foreach ($students as &$stRow) {
			$sid = (int) ($stRow['student_id'] ?? 0);
			$mode = (int) ($stRow['studying_mode'] ?? 1);
			$stRow['ref_nos'] = $refMap[$sid] ?? '';
			$stRow['recorded_by_names'] = $actorMap[$sid] ?? '';
			$stRow['payment_modes'] = $paymentModeMap[$sid] ?? '';
			$stRow['last_payment'] = $lastPaymentMap[$sid] ?? '';
			$breakdown = [];
			if ($withDetailed && $includeExtra) {
				foreach ($extraFeeColumns as $title) {
					$expected = 0.0;
					foreach ($classExtraDefs[$title] ?? [] as $ex) {
						$expected += ExtraFeesModel::expectedForMode($ex, $mode);
					}
					foreach ($studentExtraDefs[$sid][$title] ?? [] as $ex) {
						if (isset($classExtraDefs[$title])) {
							continue;
						}
						$expected += ExtraFeesModel::expectedForMode($ex, $mode);
					}
					$paid = (float) ($extraPaidMap[$sid][$title] ?? 0);
					$breakdown[$title] = [
						'expected' => $expected,
						'paid' => $paid,
						'balance' => max(0, $expected - $paid),
					];
				}
			}
			$stRow['extra_breakdown'] = $breakdown;
		}
		unset($stRow);

		return [
			'students' => $students,
			'extraFeeColumns' => $extraFeeColumns,
		];
	}

	/**
	 * @param list<array<string,mixed>> $classes
	 * @return list<array<string,mixed>>
	 */
	public static function buildIncomeSummary(
		array $classes,
		StudentModel $studentMdl,
		int $schoolId,
		int $academic,
		string $termsIn,
		array $termsList,
		string $feesScope = self::FEES_BOTH
	): array {
		$feesScope = self::normalizeFeesScope($feesScope);
		$sections = ['nursery', 'primary', 'reb', 'rtb', 'anp'];
		$bySection = array_fill_keys($sections, []);
		$rows = [];

		foreach ($classes as $classRow) {
			if (self::isHolidayClass($classRow)) {
				continue;
			}
			$classId = (int) ($classRow['id'] ?? 0);
			if ($classId < 1) {
				continue;
			}
			$classStudents = self::studentsQuery($studentMdl, $classId, $schoolId, $academic, $termsIn, $termsList, $feesScope)
				->get()->getResultArray();
			$totalDue = 0.0;
			$totalPaid = 0.0;
			foreach ($classStudents as $row) {
				$norm = self::normalizeCollectionAmounts(
					(float) ($row['amount'] ?? 0),
					(float) ($row['paid'] ?? 0)
				);
				$totalDue += $norm['due'];
				$totalPaid += $norm['paid'];
			}
			$totals = self::finalizeClassTotals($totalDue, $totalPaid);
			$section = self::schoolSection($classRow);
			$item = [
				'class_id' => $classId,
				'class_label' => self::classDisplayLabel($classRow),
				'section' => $section,
				'total_due' => $totals['total_due'],
				'total_paid' => $totals['total_paid'],
				'balance' => $totals['balance'],
				'percent' => $totals['percent'],
				'sort_key' => self::classSortKey($classRow),
			];
			$bySection[$section][] = $item;
		}

		foreach ($sections as $section) {
			if ($bySection[$section] === []) {
				continue;
			}
			usort($bySection[$section], static function ($a, $b) {
				return strcmp((string) $a['sort_key'], (string) $b['sort_key']);
			});
			foreach ($bySection[$section] as $item) {
				$rows[] = $item;
			}
			$subDue = array_sum(array_column($bySection[$section], 'total_due'));
			$subPaid = array_sum(array_column($bySection[$section], 'total_paid'));
			$subTotals = self::finalizeClassTotals($subDue, $subPaid);
			$rows[] = [
				'is_subtotal' => true,
				'section' => $section,
				'class_label' => self::sectionLabel($section),
				'total_due' => $subTotals['total_due'],
				'total_paid' => $subTotals['total_paid'],
				'balance' => $subTotals['balance'],
				'percent' => $subTotals['percent'],
			];
		}

		return $rows;
	}

	public static function formatPaymentDate(?string $raw): string
	{
		if ($raw === null || trim($raw) === '') {
			return '';
		}
		$ts = strtotime($raw);
		if ($ts === false) {
			return '';
		}
		return date('d/m/Y', $ts);
	}
}
