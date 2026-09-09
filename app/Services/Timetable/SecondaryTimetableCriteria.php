<?php

namespace App\Services\Timetable;

/**
 * Secondary (non-primary / non-nursery) timetable rules from
 * Timetable_Generation_Criteria_and_Teaching_Notes.
 *
 * Universal secondary: period blocks, Math/Physics morning bias, PE end-of-day.
 * Structure-aware (ANP / Stream / MPC…): combined classes, clinical mornings,
 * named-teacher windows, ANP Tue–Thu mornings.
 */
class SecondaryTimetableCriteria
{
	/** @var array<string,array<string,mixed>> classId => meta */
	private $classMeta = [];

	/** @var array<string,array<string,mixed>> assignment index key => row */
	private $assignmentsByKey = [];

	/** @var array<string,list<array{day:int,start:int,end:int,scope:?string}>> */
	private $teacherWindows = [];

	/** @var list<string> */
	private $anpMorningTeachers = [];

	public function __construct()
	{
		$this->teacherWindows = $this->defaultTeacherWindows();
		$this->anpMorningTeachers = ['rinea', 'yaliette', 'yaliet', 'margueritte', 'marguerite'];
	}

	/**
	 * @param list<array<string,mixed>> $assignments
	 */
	public function hydrateFromAssignments(array $assignments): void
	{
		$this->classMeta = [];
		$this->assignmentsByKey = [];
		foreach ($assignments as $row) {
			$classId = (int) ($row['class_id'] ?? 0);
			if ($classId <= 0) {
				continue;
			}
			$this->classMeta[(string) $classId] = [
				'level' => $this->normalizeLevel((string) ($row['level_name'] ?? $row['level_title'] ?? '')),
				'dept' => $this->normalizeDept(
					(string) ($row['dept_code'] ?? ''),
					(string) ($row['dept_title'] ?? ''),
					(string) ($row['class_title'] ?? '')
				),
				'class_title' => (string) ($row['class_title'] ?? ''),
			];
			$key = $this->subjectIndexKey($row);
			if ($key !== '') {
				$this->assignmentsByKey[$key] = $row;
			}
		}
	}

	public static function isSecondaryTrack(array $row): bool
	{
		$track = strtolower(trim((string) ($row['_track_key'] ?? $row['track_key'] ?? '')));
		if ($track === '') {
			return true;
		}
		return !in_array($track, ['primary', 'nursery'], true);
	}

	/**
	 * Document block rules for secondary weekly hours.
	 * 2 → separate singles; 3/5/7 → doubles + leftover single; 4/6 → doubles.
	 *
	 * @return list<int>
	 */
	public static function distributeSecondaryHours(int $hours): array
	{
		return TimetableGeneratorService::distributeWeeklyHours($hours);
	}

	public function prefersMorning(array $row): bool
	{
		if (!self::isSecondaryTrack($row)) {
			return false;
		}
		$title = strtolower(trim(preg_replace('/\s+/', ' ', (string) ($row['course_title'] ?? ''))));
		if ($title !== '' && (strpos($title, 'mathematics') !== false || preg_match('/\bmath\b/', $title) === 1)) {
			return true;
		}
		if ($title !== '' && strpos($title, 'physics') !== false) {
			return true;
		}
		$meta = $this->classMeta[(string) ((int) ($row['class_id'] ?? 0))] ?? null;
		if ($meta && ($meta['dept'] ?? '') === 'ANP') {
			return true;
		}
		$teacher = strtolower(trim((string) ($row['teacher_name'] ?? '')));
		foreach ($this->anpMorningTeachers as $needle) {
			if ($needle !== '' && strpos($teacher, $needle) !== false) {
				return true;
			}
		}
		return false;
	}

	public function isMorningSlotByTimes(?string $start, ?string $end): bool
	{
		$endMin = $this->timeToMinutes((string) ($end ?? '00:00:00'));
		$startMin = $this->timeToMinutes((string) ($start ?? '00:00:00'));
		// Before noon (7:00–12:00 teaching window).
		return $startMin < (12 * 60) && $endMin <= (12 * 60);
	}

	/**
	 * Soft score adjustment: lower is better.
	 */
	public function morningScoreDelta(array $row, ?string $slotStart, ?string $slotEnd): int
	{
		if (!$this->prefersMorning($row)) {
			return 0;
		}
		return $this->isMorningSlotByTimes($slotStart, $slotEnd) ? -450 : 700;
	}

	/**
	 * Hard: teacher named windows + ANP Tue–Thu for named ANP teachers.
	 */
	public function teacherAllows(array $row, int $day, ?string $slotStart, ?string $slotEnd): bool
	{
		if (!self::isSecondaryTrack($row)) {
			return true;
		}
		$teacher = strtolower(trim(preg_replace('/\s+/', ' ', (string) ($row['teacher_name'] ?? ''))));
		if ($teacher === '') {
			return true;
		}

		$start = $this->timeToMinutes((string) ($slotStart ?? '00:00:00'));
		$end = $this->timeToMinutes((string) ($slotEnd ?? '00:00:00'));

		foreach ($this->anpMorningTeachers as $needle) {
			if (strpos($teacher, $needle) !== false) {
				// ANP teaching required Tuesday–Thursday only.
				if (!in_array($day, [1, 2, 3], true)) {
					return false;
				}
			}
		}

		$windows = $this->windowsForTeacher($teacher);
		if ($windows === []) {
			return true;
		}

		$meta = $this->classMeta[(string) ((int) ($row['class_id'] ?? 0))] ?? null;
		$dept = (string) ($meta['dept'] ?? '');
		$level = (string) ($meta['level'] ?? '');
		$classTitle = strtolower((string) ($meta['class_title'] ?? ''));

		$unscoped = [];
		$scopedApplicable = [];
		$hasScoped = false;
		foreach ($windows as $w) {
			$scope = $w['scope'] ?? null;
			if ($scope === null || $scope === '') {
				$unscoped[] = $w;
				continue;
			}
			$hasScoped = true;
			if ($scope === 'sod_l3') {
				$isSodL3 = $dept === 'SOD' && (
					$level === 'L3'
					|| strpos($classTitle, 'level 3') !== false
					|| $level === 'LEVEL3'
				);
				if ($isSodL3) {
					$scopedApplicable[] = $w;
				}
			}
		}

		// Scoped-only windows (e.g. Eric @ SOD L3) do not restrict other classes.
		if ($unscoped === [] && $hasScoped && $scopedApplicable === []) {
			return true;
		}

		$applicable = array_merge($unscoped, $scopedApplicable);
		$dayWindows = array_values(array_filter($applicable, static function (array $w) use ($day): bool {
			return (int) $w['day'] === $day;
		}));
		if ($dayWindows === []) {
			return false;
		}

		foreach ($dayWindows as $w) {
			if ($start >= (int) $w['start'] && $end <= (int) $w['end']) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Clinical attachment mornings reserved (no regular lessons).
	 * Tue 7–12: S4 + S6; Wed 7–12: S5 + S6.
	 */
	public function clinicalBlocksClass(array $row, int $day, ?string $slotStart, ?string $slotEnd): bool
	{
		if (!self::isSecondaryTrack($row)) {
			return false;
		}
		if (!$this->isMorningSlotByTimes($slotStart, $slotEnd) && $this->timeToMinutes((string) ($slotStart ?? '')) >= (12 * 60)) {
			return false;
		}
		// Only morning slots.
		if ($this->timeToMinutes((string) ($slotStart ?? '99:00:00')) >= (12 * 60)) {
			return false;
		}

		$meta = $this->classMeta[(string) ((int) ($row['class_id'] ?? 0))] ?? null;
		$level = (string) ($meta['level'] ?? $this->normalizeLevel((string) ($row['level_name'] ?? '')));
		if ($day === 1 && ($level === 'S4' || $level === 'S6')) {
			return true;
		}
		if ($day === 2 && ($level === 'S5' || $level === 'S6')) {
			return true;
		}
		return false;
	}

	/**
	 * Partner assignment for combined lessons (same subject, paired classes).
	 *
	 * @return array<string,mixed>|null
	 */
	public function combinePartner(array $row): ?array
	{
		if (!self::isSecondaryTrack($row)) {
			return null;
		}
		$classId = (int) ($row['class_id'] ?? 0);
		$meta = $this->classMeta[(string) $classId] ?? null;
		if ($meta === null) {
			return null;
		}
		$subject = $this->normalizeSubject((string) ($row['course_title'] ?? ''));
		if ($subject === '') {
			return null;
		}

		$pairs = $this->combinePairsForSubject($subject);
		$level = $meta['level'];
		$dept = $meta['dept'];
		$partnerDept = null;
		foreach ($pairs as $pair) {
			if (($pair['level'] ?? '') !== $level) {
				continue;
			}
			$a = $pair['a'];
			$b = $pair['b'];
			if ($dept === $a) {
				$partnerDept = $b;
				break;
			}
			if ($dept === $b) {
				$partnerDept = $a;
				break;
			}
		}
		if ($partnerDept === null) {
			return null;
		}

		foreach ($this->assignmentsByKey as $cand) {
			$cid = (int) ($cand['class_id'] ?? 0);
			if ($cid === $classId) {
				continue;
			}
			$cm = $this->classMeta[(string) $cid] ?? null;
			if ($cm === null) {
				continue;
			}
			if (($cm['level'] ?? '') !== $level || ($cm['dept'] ?? '') !== $partnerDept) {
				continue;
			}
			if ($this->normalizeSubject((string) ($cand['course_title'] ?? '')) !== $subject) {
				continue;
			}
			return $cand;
		}
		return null;
	}

	/** PE: at most one period per class day (spread across week). */
	public function peMaxPerDay(array $row, int $weeklyHours): ?int
	{
		if (!TimetableGeneratorService::isPhysicalEducationSportTitle((string) ($row['course_title'] ?? ''))) {
			return null;
		}
		return 1;
	}

	/**
	 * @return list<array{level:string,a:string,b:string}>
	 */
	private function combinePairsForSubject(string $subject): array
	{
		// subject already normalized
		if ($subject === 'chemistry') {
			return [
				['level' => 'S5', 'a' => 'ANP', 'b' => 'ST1'],
				['level' => 'S6', 'a' => 'ANP', 'b' => 'GE'],
			];
		}
		if ($subject === 'biology') {
			return [
				['level' => 'S5', 'a' => 'ANP', 'b' => 'ST1'],
				['level' => 'S6', 'a' => 'ANP', 'b' => 'GE'],
				['level' => 'S6', 'a' => 'PCB', 'b' => 'HCB'],
			];
		}
		if ($subject === 'computer') {
			return [
				['level' => 'S6', 'a' => 'MPC', 'b' => 'MCE'],
			];
		}
		if ($subject === 'mathematics') {
			return [
				['level' => 'S6', 'a' => 'ANP', 'b' => 'PCB'],
				['level' => 'S5', 'a' => 'ST1', 'b' => 'ST2'],
			];
		}
		return [];
	}

	private function normalizeSubject(string $title): string
	{
		$t = strtolower(trim(preg_replace('/\s+/', ' ', $title)));
		if ($t === '') {
			return '';
		}
		if (strpos($t, 'chem') !== false) {
			return 'chemistry';
		}
		if (strpos($t, 'bio') !== false) {
			return 'biology';
		}
		if (strpos($t, 'computer') !== false || preg_match('/\bict\b/', $t) || strpos($t, 'software') !== false) {
			return 'computer';
		}
		if (strpos($t, 'mathematics') !== false || preg_match('/\bmath\b/', $t)) {
			return 'mathematics';
		}
		return $t;
	}

	private function normalizeLevel(string $level): string
	{
		$l = strtoupper(trim(preg_replace('/\s+/', ' ', $level)));
		if (preg_match('/\bS\s*([456])\b/', $l, $m)) {
			return 'S' . $m[1];
		}
		if (preg_match('/\bLEVEL\s*([345])\b/', $l, $m)) {
			return 'L' . $m[1];
		}
		if (preg_match('/\bL\s*([345])\b/', $l, $m)) {
			return 'L' . $m[1];
		}
		return $l;
	}

	private function normalizeDept(string $code, string $deptTitle, string $classTitle): string
	{
		$c = strtoupper(trim($code));
		$map = [
			'ANP' => 'ANP', 'ST1' => 'ST1', 'ST2' => 'ST2', 'STR' => 'ST1',
			'MPC' => 'MPC', 'MCE' => 'MCE', 'PCB' => 'PCB', 'HCB' => 'HCB',
			'GE' => 'GE', 'SOD' => 'SOD', 'PCM' => 'PCM', 'MCB' => 'MCB',
			'MEG' => 'MEG', 'MPG' => 'MPG', 'ACC' => 'ACC',
		];
		if (isset($map[$c])) {
			return $map[$c];
		}
		$hay = strtoupper($code . ' ' . $deptTitle . ' ' . $classTitle);
		if (strpos($hay, 'ANP') !== false || strpos($hay, 'NURS') !== false) {
			return 'ANP';
		}
		if (strpos($hay, 'STREAM 1') !== false || preg_match('/\bST\s*1\b/', $hay)) {
			return 'ST1';
		}
		if (strpos($hay, 'STREAM 2') !== false || preg_match('/\bST\s*2\b/', $hay)) {
			return 'ST2';
		}
		if (strpos($hay, 'MPC') !== false) {
			return 'MPC';
		}
		if (strpos($hay, 'MCE') !== false) {
			return 'MCE';
		}
		if (strpos($hay, 'HCB') !== false) {
			return 'HCB';
		}
		if (strpos($hay, 'PCB') !== false) {
			return 'PCB';
		}
		if (strpos($hay, 'GENERAL') !== false || preg_match('/\bGE\b/', $hay)) {
			return 'GE';
		}
		if (strpos($hay, 'SOD') !== false || strpos($hay, 'SOFTWARE') !== false) {
			return 'SOD';
		}
		return $c;
	}

	private function subjectIndexKey(array $row): string
	{
		$classId = (int) ($row['class_id'] ?? 0);
		$subject = $this->normalizeSubject((string) ($row['course_title'] ?? ''));
		if ($classId <= 0 || $subject === '') {
			return '';
		}
		return $classId . '|' . $subject;
	}

	/**
	 * @return list<array{day:int,start:int,end:int,scope:?string}>
	 */
	private function windowsForTeacher(string $teacher): array
	{
		foreach ($this->teacherWindows as $needle => $windows) {
			if (strpos($teacher, $needle) !== false) {
				return $windows;
			}
		}
		return [];
	}

	/**
	 * @return array<string,list<array{day:int,start:int,end:int,scope:?string}>>
	 */
	private function defaultTeacherWindows(): array
	{
		// Day: Mon=0 … Fri=4. Times in minutes from midnight.
		return [
			'innocent' => [
				['day' => 0, 'start' => 10 * 60, 'end' => 12 * 60, 'scope' => null],
				['day' => 1, 'start' => 10 * 60, 'end' => 12 * 60, 'scope' => null],
				['day' => 2, 'start' => 7 * 60, 'end' => 10 * 60, 'scope' => null],
			],
			'eric' => [
				['day' => 0, 'start' => 7 * 60, 'end' => 10 * 60, 'scope' => 'sod_l3'],
				['day' => 1, 'start' => 7 * 60, 'end' => 10 * 60, 'scope' => 'sod_l3'],
			],
			'olivier' => [
				['day' => 4, 'start' => 7 * 60, 'end' => 10 * 60, 'scope' => null],
			],
			'varlette' => [
				['day' => 0, 'start' => 7 * 60, 'end' => 12 * 60, 'scope' => null],
				['day' => 2, 'start' => 7 * 60, 'end' => 12 * 60, 'scope' => null],
				['day' => 3, 'start' => 9 * 60, 'end' => 12 * 60, 'scope' => null],
			],
			'varliette' => [
				['day' => 4, 'start' => 8 * 60 + 30, 'end' => 12 * 60, 'scope' => null],
			],
			'margueritte' => [
				['day' => 3, 'start' => 7 * 60, 'end' => 16 * 60, 'scope' => null],
			],
			'marguerite' => [
				['day' => 3, 'start' => 7 * 60, 'end' => 16 * 60, 'scope' => null],
			],
			'linear' => [
				['day' => 3, 'start' => 7 * 60, 'end' => 16 * 60, 'scope' => null],
				['day' => 4, 'start' => 9 * 60, 'end' => 12 * 60, 'scope' => null],
			],
		];
	}

	private function timeToMinutes(string $time): int
	{
		$parts = explode(':', substr($time, 0, 8));
		return ((int) ($parts[0] ?? 0)) * 60 + (int) ($parts[1] ?? 0);
	}
}
