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

	/** @var array<int,list<array{day:int,start:int,end:int,scope:?string}>> */
	private $windowsByStaffId = [];

	/** @var array<int,list<int>> staff_id => allowed days (empty = any) */
	private $allowedDaysByStaffId = [];

	/** @var array<string,list<int>> teacher name needle => blocked days */
	private $blockedDaysByName = [];

	/** @var list<array<string,mixed>> */
	private $customRules = [];

	/** @var list<array<string,mixed>> */
	private $sundayRules = [];

	/** @var list<array<string,mixed>> */
	private $afterLessonRules = [];

	/** @var array<int,true> */
	private $relaxedStaffIds = [];

	/** @var array<string,true> */
	private $relaxedNameNeedles = [];

	/** @var array<int,true> */
	private $heavyStaffIds = [];

	/** @var array<int,int> */
	private $weeklyPeriodsByStaffId = [];

	/** @var list<array{course_id:int,class_ids:list<int>}> */
	private $customCombineGroups = [];

	public function __construct()
	{
		$this->teacherWindows = $this->defaultTeacherWindows();
		$this->anpMorningTeachers = ['rinea', 'linear', 'linea', 'kubahoni', 'yaliette', 'yaliet', 'valiette', 'valiet', 'varlette', 'varliette', 'margueritte', 'marguerite'];
		$this->blockedDaysByName = [
			'alice' => [0], // Alice must not teach on Monday
		];
	}

	/**
	 * @param list<array<string,mixed>> $rules
	 */
	public function hydrateCustomRules(array $rules): void
	{
		$this->customRules = $rules;
		$this->windowsByStaffId = [];
		$this->allowedDaysByStaffId = [];
		$this->sundayRules = [];
		$this->afterLessonRules = [];
		$this->relaxedStaffIds = [];
		$this->relaxedNameNeedles = [];
		$this->heavyStaffIds = [];
		$this->customCombineGroups = [];
		foreach ($rules as $rule) {
			if (empty($rule['enabled']) && isset($rule['enabled'])) {
				continue;
			}
			$type = (string) ($rule['rule_type'] ?? '');
			$staffId = (int) ($rule['teacher_id'] ?? 0);
			$days = $this->decodeDays($rule['days'] ?? null);
			if ($type === 'teacher_window' && $staffId > 0 && $days !== []) {
				$start = $this->timeToMinutes((string) ($rule['start_time'] ?? '00:00'));
				$end = $this->timeToMinutes((string) ($rule['end_time'] ?? '00:00'));
				if ($end <= $start) {
					continue;
				}
				foreach ($days as $day) {
					$this->windowsByStaffId[$staffId][] = [
						'day' => $day,
						'start' => $start,
						'end' => $end,
						'scope' => null,
					];
				}
			}
			if ($type === 'teacher_days' && $staffId > 0 && $days !== []) {
				$this->allowedDaysByStaffId[$staffId] = $days;
			}
			if ($type === 'teach_sunday') {
				$this->sundayRules[] = $rule;
			}
			if ($type === 'after_lessons') {
				$this->afterLessonRules[] = $rule;
			}
			if ($type === 'combine_classes') {
				$ids = $this->decodeClassIds($rule['class_ids'] ?? $rule['days'] ?? null);
				if (count($ids) >= 2) {
					$this->customCombineGroups[] = [
						'course_id' => (int) ($rule['course_id'] ?? 0),
						'class_ids' => $ids,
					];
				}
			}
		}
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

	/**
	 * Document special criteria apply to high school only
	 * (O Level / A Level / TVET / Special) — never nursery or primary.
	 */
	public static function isSecondaryTrack(array $row): bool
	{
		$track = strtolower(trim((string) ($row['_track_key'] ?? $row['track_key'] ?? '')));
		if ($track === '') {
			$phase = strtolower(trim((string) ($row['_phase'] ?? $row['generation_phase'] ?? '')));
			if ($phase === 'nursery' || $phase === 'primary') {
				return false;
			}
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

	public function prefersLastHour(array $row): bool
	{
		if (self::isSecondaryTrack($row)
			&& TimetableGeneratorService::isPhysicalEducationSportTitle((string) ($row['course_title'] ?? ''))) {
			return true;
		}
		$courseId = (int) ($row['course_id'] ?? 0);
		$staffId = (int) ($row['lecturer'] ?? $row['staff_id'] ?? 0);
		$classId = (int) ($row['class_id'] ?? 0);
		foreach ($this->customRules as $rule) {
			if (($rule['rule_type'] ?? '') !== 'last_hour') {
				continue;
			}
			if (!$this->ruleMatchesRow($rule, $courseId, $staffId, $classId)) {
				continue;
			}
			return true;
		}
		return false;
	}

	public function prefersMorning(array $row): bool
	{
		$courseId = (int) ($row['course_id'] ?? 0);
		$staffId = (int) ($row['lecturer'] ?? $row['staff_id'] ?? 0);
		$classId = (int) ($row['class_id'] ?? 0);
		foreach ($this->customRules as $rule) {
			if (($rule['rule_type'] ?? '') === 'morning' && $this->ruleMatchesRow($rule, $courseId, $staffId, $classId)) {
				return true;
			}
		}
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
		// Teachers not named in the document: fill from morning first.
		if (!$this->isDocumentSpecialTeacher($row)) {
			return true;
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
	 * Hard slot gate used by generation and parking for every track.
	 * Custom teacher days/windows apply school-wide; document windows, Alice,
	 * clinical, and ANP class mornings apply to high school only.
	 */
	public function slotAllowed(array $row, int $day, ?string $slotStart, ?string $slotEnd): bool
	{
		if ($this->requiresAfterLessons($row)) {
			if (!\App\Models\TimetableSchemaModel::isAfterLessonSlotTimes($slotStart, $slotEnd)) {
				return false;
			}
			if ($day === 6 && !$this->allowsSunday($row, $slotStart, $slotEnd)) {
				return false;
			}
			if ($this->clinicalBlocksClass($row, $day, $slotStart, $slotEnd)) {
				return false;
			}
			return $this->teacherAllows($row, $day, $slotStart, $slotEnd);
		}
		if ($this->prefersLastHour($row)) {
			if (!\App\Models\TimetableSchemaModel::isLastTeachingHourSlotTimes($slotStart, $slotEnd)) {
				return false;
			}
		}
		if ($day === 6) {
			if (!self::isSecondaryTrack($row) || !$this->allowsSunday($row, $slotStart, $slotEnd)) {
				return false;
			}
			if ($this->clinicalBlocksClass($row, $day, $slotStart, $slotEnd)) {
				return false;
			}
			return $this->teacherAllows($row, $day, $slotStart, $slotEnd);
		}
		if (self::isSecondaryTrack($row) && (
			\App\Models\TimetableSchemaModel::isAfterLessonSlotTimes($slotStart, $slotEnd)
			|| \App\Models\TimetableSchemaModel::isNightSlotTimes($slotStart, $slotEnd)
		)) {
			return false;
		}
		if ($this->clinicalBlocksClass($row, $day, $slotStart, $slotEnd)) {
			return false;
		}
		return $this->teacherAllows($row, $day, $slotStart, $slotEnd);
	}

	/** Farming / Library and Clubs: only after 15:40, never night. */
	public static function isAfterLessonCourseTitle(string $title): bool
	{
		$t = strtolower(trim(preg_replace('/\s+/', ' ', $title)));
		if ($t === '') {
			return false;
		}
		if (strpos($t, 'farming') !== false || strpos($t, 'library') !== false) {
			return true;
		}
		return $t === 'club' || $t === 'clubs' || preg_match('/\blibrary\b.*\bclubs?\b/', $t) === 1;
	}

	public function requiresAfterLessons(array $row): bool
	{
		if (self::isAfterLessonCourseTitle((string) ($row['course_title'] ?? ''))) {
			return true;
		}
		$courseId = (int) ($row['course_id'] ?? $row['course'] ?? 0);
		$staffId = (int) ($row['lecturer'] ?? $row['staff_id'] ?? 0);
		$classId = (int) ($row['class_id'] ?? 0);
		foreach ($this->afterLessonRules as $rule) {
			if ($this->ruleMatchesRow($rule, $courseId, $staffId, $classId)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Sunday is reserved: only courses saved as Teach on Sunday may be generated there.
	 */
	public function allowsSunday(array $row, ?string $slotStart = null, ?string $slotEnd = null): bool
	{
		if (!self::isSecondaryTrack($row)) {
			return false;
		}
		$courseId = (int) ($row['course_id'] ?? $row['course'] ?? 0);
		$staffId = (int) ($row['lecturer'] ?? $row['staff_id'] ?? 0);
		$classId = (int) ($row['class_id'] ?? 0);
		$matched = [];
		foreach ($this->sundayRules as $rule) {
			if ($this->ruleMatchesRow($rule, $courseId, $staffId, $classId)) {
				$matched[] = $rule;
			}
		}
		if ($matched === []) {
			$teacher = strtolower(trim(preg_replace('/\s+/', ' ', (string) ($row['teacher_name'] ?? ''))));
			return $this->isIzabayoPatience($teacher);
		}
		// Place into the school's saved teaching periods only — ignore any leftover times on the rule.
		return true;
	}

	public function prefersSunday(array $row): bool
	{
		if (!self::isSecondaryTrack($row)) {
			return false;
		}
		// Ordered-fill teachers (Patience) may use Sunday as overflow, but weekday windows come first.
		if ($this->hasOrderedFillWindows($row)) {
			return false;
		}
		$courseId = (int) ($row['course_id'] ?? $row['course'] ?? 0);
		$staffId = (int) ($row['lecturer'] ?? $row['staff_id'] ?? 0);
		$classId = (int) ($row['class_id'] ?? 0);
		foreach ($this->sundayRules as $rule) {
			if ($this->ruleMatchesRow($rule, $courseId, $staffId, $classId)) {
				return true;
			}
		}
		return false;
	}

	/** Soft score: Sunday-rule courses go to Sunday first; everyone else never lands there. */
	public function sundayScoreDelta(array $row, int $day): int
	{
		if ($day !== 6) {
			return $this->prefersSunday($row) ? 350 : 0;
		}
		if ($this->prefersSunday($row)) {
			return -2500;
		}
		// Named overflow (Patience) may sit on Sunday after weekday windows fill.
		if ($this->allowsSunday($row)) {
			return 0;
		}
		return 50000;
	}

	/** Soft score: Farming / Library stay in 15:40–17:30, never night. Prefer 15:40 first. */
	public function afterLessonScoreDelta(array $row, ?string $slotStart, ?string $slotEnd): int
	{
		if (!$this->requiresAfterLessons($row)) {
			return 0;
		}
		if (!\App\Models\TimetableSchemaModel::isAfterLessonSlotTimes($slotStart, $slotEnd)) {
			return 20000;
		}
		$startMin = $this->timeToMinutes((string) ($slotStart ?? '00:00:00'));
		$lessonEnd = 15 * 60 + 40;
		return max(0, $startMin - $lessonEnd) - 800;
	}

	/** Soft score: PE last teaching hour (15:00–15:40) beats 14:20–15:00. Never after 15:40. */
	public function lastHourScoreDelta(array $row, ?string $slotStart, ?string $slotEnd): int
	{
		if (!$this->prefersLastHour($row)) {
			return 0;
		}
		if (!\App\Models\TimetableSchemaModel::isLastTeachingHourSlotTimes($slotStart, $slotEnd)) {
			return 20000;
		}
		$endMin = $this->timeToMinutes((string) ($slotEnd ?? '00:00:00'));
		$lessonEnd = 15 * 60 + 40;
		return max(0, $lessonEnd - $endMin);
	}

	/**
	 * Hard: saved teacher windows/days (all tracks) + document named windows (high school).
	 * ANP *classes* only on Tuesday–Thursday mornings; named ANP teachers keep their own windows.
	 */
	public function teacherAllows(array $row, int $day, ?string $slotStart, ?string $slotEnd): bool
	{
		$teacher = strtolower(trim(preg_replace('/\s+/', ' ', (string) ($row['teacher_name'] ?? ''))));
		$staffId = (int) ($row['lecturer'] ?? $row['staff_id'] ?? 0);
		$start = $this->timeToMinutes((string) ($slotStart ?? '00:00:00'));
		$end = $this->timeToMinutes((string) ($slotEnd ?? '00:00:00'));
		$personalRelaxed = $this->isPersonalRestrictionRelaxed($staffId, $teacher);

		if (!$personalRelaxed && $staffId > 0 && isset($this->allowedDaysByStaffId[$staffId]) && !in_array($day, $this->allowedDaysByStaffId[$staffId], true)) {
			return false;
		}
		if (!$personalRelaxed && $staffId > 0 && !empty($this->windowsByStaffId[$staffId])) {
			$dayWindows = array_values(array_filter($this->windowsByStaffId[$staffId], static function (array $w) use ($day): bool {
				return (int) $w['day'] === $day;
			}));
			if ($dayWindows === []) {
				return false;
			}
			$ok = false;
			foreach ($dayWindows as $w) {
				if ($start >= (int) $w['start'] && $end <= (int) $w['end']) {
					$ok = true;
					break;
				}
			}
			if (!$ok) {
				return false;
			}
		}

		if (!self::isSecondaryTrack($row)) {
			return true;
		}

		$meta = $this->classMeta[(string) ((int) ($row['class_id'] ?? 0))] ?? null;
		if ($meta && ($meta['dept'] ?? '') === 'ANP' && !$this->isPinnedTeacherName($teacher) && $this->windowsForTeacher($teacher) === []) {
			// ANP teaching is required Tuesday–Thursday, mornings only.
			if (!in_array($day, [1, 2, 3], true) || !$this->isMorningSlotByTimes($slotStart, $slotEnd)) {
				return false;
			}
		}

		if ($teacher === '') {
			return true;
		}
		if ($personalRelaxed) {
			return true;
		}
		foreach ($this->blockedDaysByName as $needle => $blocked) {
			if ($needle !== '' && strpos($teacher, $needle) !== false && in_array($day, $blocked, true)) {
				return false;
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
	 * Clinical attachment reserved (no regular lessons) for ANP only.
	 * Tuesday 07:00–12:00: S4 ANP + S5 ANP.
	 * Wednesday full day 07:00–16:00: S6 ANP.
	 */
	public function clinicalBlocksClass(array $row, int $day, ?string $slotStart, ?string $slotEnd): bool
	{
		if (!self::isSecondaryTrack($row)) {
			return false;
		}
		$meta = $this->classMeta[(string) ((int) ($row['class_id'] ?? 0))] ?? null;
		$dept = (string) ($meta['dept'] ?? '');
		if ($dept !== 'ANP') {
			return false;
		}
		$level = (string) ($meta['level'] ?? $this->normalizeLevel((string) ($row['level_name'] ?? '')));
		$start = $this->timeToMinutes((string) ($slotStart ?? '00:00:00'));
		$end = $this->timeToMinutes((string) ($slotEnd ?? '00:00:00'));
		if ($end <= $start) {
			$end = $start + 40;
		}
		if ($day === 1 && ($level === 'S4' || $level === 'S5')) {
			return $start < (12 * 60) && $end > (7 * 60);
		}
		if ($day === 2 && $level === 'S6') {
			return $start < (16 * 60) && $end > (7 * 60);
		}
		return false;
	}

	/**
	 * Partner assignments for one combined lesson.
	 * Requires the Word-file group AND the same teacher AND the same subject.
	 * Different subjects never share a clock (ICT S4 ST1+ST2 is not Physics S4 ST1+ST2).
	 *
	 * @return list<array<string,mixed>>
	 */
	public function combinePartners(array $row): array
	{
		if (!self::isSecondaryTrack($row)) {
			return [];
		}
		$classId = (int) ($row['class_id'] ?? 0);
		$staffId = (int) ($row['lecturer'] ?? $row['staff_id'] ?? 0);
		$meta = $this->classMeta[(string) $classId] ?? null;
		if ($meta === null || $classId <= 0 || $staffId <= 0) {
			return [];
		}
		$subject = $this->normalizeSubject((string) ($row['course_title'] ?? ''));
		if ($subject === '') {
			return [];
		}
		$wantedDepts = $this->combineDeptsFor($subject, (string) ($meta['level'] ?? ''), (string) ($meta['dept'] ?? ''));
		if ($wantedDepts === []) {
			return [];
		}
		return $this->collectCombinePartners($row, $classId, $subject, (string) ($meta['level'] ?? ''), $wantedDepts, $staffId, 0);
	}

	/**
	 * @param list<string>|null $wantedDepts
	 * @return list<array<string,mixed>>
	 */
	private function collectCombinePartners(
		array $row,
		int $classId,
		string $subject,
		string $level,
		?array $wantedDepts,
		int $sameStaff,
		int $sameCourse
	): array {
		if ($sameStaff <= 0 || $subject === '') {
			return [];
		}
		$out = [];
		$seen = [];
		foreach ($this->assignmentsByKey as $cand) {
			$cid = (int) ($cand['class_id'] ?? 0);
			if ($cid === $classId || isset($seen[$cid])) {
				continue;
			}
			$cm = $this->classMeta[(string) $cid] ?? null;
			if ($cm === null || ($cm['level'] ?? '') !== $level) {
				continue;
			}
			if ($this->normalizeSubject((string) ($cand['course_title'] ?? '')) !== $subject) {
				continue;
			}
			if ($wantedDepts !== null && !in_array((string) ($cm['dept'] ?? ''), $wantedDepts, true)) {
				continue;
			}
			$cStaff = (int) ($cand['lecturer'] ?? $cand['staff_id'] ?? 0);
			if ($cStaff !== $sameStaff) {
				continue;
			}
			$needCourse = (int) $sameCourse;
			if ($needCourse > 0 && (int) ($cand['course_id'] ?? $cand['course'] ?? 0) !== $needCourse) {
				continue;
			}
			$seen[$cid] = true;
			$out[] = $cand;
		}
		return $out;
	}

	public function combinePartner(array $row): ?array
	{
		$partners = $this->combinePartners($row);
		return $partners[0] ?? null;
	}

	/** Stable key so a combined group counts as one teacher load (one class of sessions). */
	public function combineGroupKey(array $row): string
	{
		$partners = $this->combinePartners($row);
		if ($partners === []) {
			return '';
		}
		$staffId = (int) ($row['lecturer'] ?? $row['staff_id'] ?? 0);
		$subject = $this->normalizeSubject((string) ($row['course_title'] ?? ''));
		$classId = (int) ($row['class_id'] ?? 0);
		$meta = $this->classMeta[(string) $classId] ?? null;
		if ($meta === null || $staffId <= 0 || $subject === '') {
			return '';
		}
		$group = $this->combineGroupDepts($subject, (string) ($meta['level'] ?? ''), (string) ($meta['dept'] ?? ''));
		if (count($group) < 2) {
			return '';
		}
		sort($group);
		return $staffId . '|' . $subject . '|' . $meta['level'] . '|' . implode('-', $group);
	}

	/** 5 weekly hours → 3 teaching sessions (2+2+1). Combined load uses this, not class-count × hours. */
	public static function teacherSessionCount(int $weeklyHours): int
	{
		$blocks = TimetableGeneratorService::distributeWeeklyHours($weeklyHours);
		return max(1, count($blocks));
	}

	/** True when this assignment shares one teacher lesson with at least one partner class. */
	public function isCombinedAssignment(array $row): bool
	{
		return $this->combineGroupKey($row) !== '';
	}

	public static function subjectFamily(string $title): string
	{
		$c = new self();
		return $c->normalizeSubject($title);
	}

	/**
	 * Distinct highlight for a combined lesson (same subject + same teacher).
	 *
	 * @return array{bg:string,fg:string,accent:string,slug:string}
	 */
	public static function combinedHighlight(string $family): array
	{
		$family = strtolower(trim($family));
		$map = [
			'mathematics' => ['bg' => '#ede9fe', 'fg' => '#4c1d95', 'accent' => '#7c3aed'],
			'physics' => ['bg' => '#dbeafe', 'fg' => '#1e3a8a', 'accent' => '#2563eb'],
			'chemistry' => ['bg' => '#ccfbf1', 'fg' => '#115e59', 'accent' => '#0d9488'],
			'biology' => ['bg' => '#dcfce7', 'fg' => '#14532d', 'accent' => '#16a34a'],
			'computer' => ['bg' => '#cffafe', 'fg' => '#155e75', 'accent' => '#0891b2'],
			'english' => ['bg' => '#fef3c7', 'fg' => '#92400e', 'accent' => '#d97706'],
			'kinyarwanda' => ['bg' => '#ffedd5', 'fg' => '#9a3412', 'accent' => '#ea580c'],
			'entrepreneurship' => ['bg' => '#fce7f3', 'fg' => '#9d174d', 'accent' => '#db2777'],
			'general_studies' => ['bg' => '#e0e7ff', 'fg' => '#312e81', 'accent' => '#4f46e5'],
			'geography' => ['bg' => '#ecfccb', 'fg' => '#3f6212', 'accent' => '#65a30d'],
			'economics' => ['bg' => '#fae8ff', 'fg' => '#86198f', 'accent' => '#c026d3'],
		];
		$tone = $map[$family] ?? ['bg' => '#ccfbf1', 'fg' => '#134e4a', 'accent' => '#0f766e'];
		$slug = preg_replace('/[^a-z0-9]+/', '-', $family);
		$tone['slug'] = ($slug !== null && $slug !== '') ? $slug : 'combined';
		return $tone;
	}

	/** Same teacher + same document combine group = one lesson, not a clash. */
	public static function entriesAreCombinedLesson(array $a, array $b): bool
	{
		$staffA = (int) ($a['staff_id'] ?? $a['lecturer'] ?? 0);
		$staffB = (int) ($b['staff_id'] ?? $b['lecturer'] ?? 0);
		if ($staffA <= 0 || $staffA !== $staffB) {
			return false;
		}
		if ((int) ($a['class_id'] ?? 0) === (int) ($b['class_id'] ?? 0)) {
			return false;
		}
		$famA = self::subjectFamily((string) ($a['course_title'] ?? ''));
		$famB = self::subjectFamily((string) ($b['course_title'] ?? ''));
		if ($famA === '' || $famA !== $famB) {
			return false;
		}
		$c = new self();
		$levelA = $c->normalizeLevel((string) ($a['level_name'] ?? $a['level_title'] ?? ''));
		$levelB = $c->normalizeLevel((string) ($b['level_name'] ?? $b['level_title'] ?? ''));
		$deptA = $c->normalizeDept(
			(string) ($a['dept_code'] ?? ''),
			(string) ($a['dept_title'] ?? $a['dept_name'] ?? ''),
			(string) ($a['class_title'] ?? '')
		);
		$deptB = $c->normalizeDept(
			(string) ($b['dept_code'] ?? ''),
			(string) ($b['dept_title'] ?? $b['dept_name'] ?? ''),
			(string) ($b['class_title'] ?? '')
		);
		if ($levelA === '' || $levelA !== $levelB || $deptA === '' || $deptB === '') {
			return false;
		}
		foreach (self::documentCombineGroups() as $group) {
			if (($group['subject'] ?? '') !== $famA || ($group['level'] ?? '') !== $levelA) {
				continue;
			}
			$depts = $group['depts'] ?? [];
			if (in_array($deptA, $depts, true) && in_array($deptB, $depts, true)) {
				return true;
			}
		}
		return false;
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
	 * Days this teacher may teach, or null when they may use the whole week.
	 *
	 * @return list<int>|null
	 */
	public function restrictedTeachingDays(array $row): ?array
	{
		$staffId = (int) ($row['lecturer'] ?? $row['staff_id'] ?? 0);
		$teacher = strtolower(trim(preg_replace('/\s+/', ' ', (string) ($row['teacher_name'] ?? ''))));
		if ($this->isPersonalRestrictionRelaxed($staffId, $teacher)) {
			return null;
		}
		if ($staffId > 0 && isset($this->allowedDaysByStaffId[$staffId]) && $this->allowedDaysByStaffId[$staffId] !== []) {
			return array_values(array_unique(array_map('intval', $this->allowedDaysByStaffId[$staffId])));
		}
		if ($staffId > 0 && !empty($this->windowsByStaffId[$staffId])) {
			$days = [];
			foreach ($this->windowsByStaffId[$staffId] as $window) {
				$days[(int) ($window['day'] ?? -1)] = true;
			}
			unset($days[-1]);
			return $days === [] ? null : array_map('intval', array_keys($days));
		}
		$teacher = strtolower(trim(preg_replace('/\s+/', ' ', (string) ($row['teacher_name'] ?? ''))));
		if ($teacher === '') {
			return null;
		}
		$windows = $this->windowsForTeacher($teacher);
		if ($windows === []) {
			return null;
		}
		$days = [];
		foreach ($windows as $window) {
			$days[(int) ($window['day'] ?? -1)] = true;
		}
		unset($days[-1]);
		return $days === [] ? null : array_map('intval', array_keys($days));
	}

	/** Raise the daily cap so weekly Manage Course periods can still fit on a short teacher window. */
	public function packedDailyCap(array $row, int $weeklyHours, int $fallback): int
	{
		$peMax = $this->peMaxPerDay($row, $weeklyHours);
		if ($peMax !== null) {
			return $peMax;
		}
		if ($weeklyHours <= 0) {
			return $fallback;
		}
		// Keep doubles (2/day). Never dump a whole week's periods onto one day.
		if ($this->hasOrderedFillWindows($row)) {
			return $fallback;
		}
		$days = $this->restrictedTeachingDays($row);
		if ($days === null || $days === []) {
			return $fallback;
		}
		$n = count($days);
		$pack = (int) ceil($weeklyHours / max(1, $n));
		return max($fallback, min($pack, 3));
	}

	/**
	 * Documented combined-class groups. Each row is one subject + listed classes
	 * sharing one teacher clock. ICT S4 ST1+ST2 is not scheduled with Physics
	 * S4 ST1+ST2; those are independent groups.
	 *
	 * @return list<array{subject:string,level:string,depts:list<string>,label:string}>
	 */
	public static function documentCombineGroups(): array
	{
		return [
			['subject' => 'computer', 'level' => 'S6', 'depts' => ['MPC', 'MCE'], 'label' => 'Computer Science S6 MPC + MCE'],
			['subject' => 'computer', 'level' => 'S5', 'depts' => ['ST1', 'ST2'], 'label' => 'ICT S5 Stream 1 + Stream 2'],
			['subject' => 'computer', 'level' => 'S4', 'depts' => ['ST1', 'ST2'], 'label' => 'ICT S4 Stream 1 + Stream 2'],
			['subject' => 'chemistry', 'level' => 'S5', 'depts' => ['ANP', 'ST1'], 'label' => 'Chemistry S5 ANP + Stream 1'],
			['subject' => 'chemistry', 'level' => 'S6', 'depts' => ['MCB', 'PCB'], 'label' => 'Chemistry S6 MCB + PCB'],
			['subject' => 'physics', 'level' => 'S4', 'depts' => ['ST1', 'ST2'], 'label' => 'Physics S4 Stream 1 + Stream 2'],
			['subject' => 'physics', 'level' => 'S5', 'depts' => ['ANP', 'ST1', 'ST2'], 'label' => 'Physics S5 ANP + Stream 1 + Stream 2'],
			['subject' => 'physics', 'level' => 'S6', 'depts' => ['PCB', 'PCM', 'MPC', 'ANP', 'MPG'], 'label' => 'Physics S6 PCB + PCM + MPC + ANP + MPG'],
			['subject' => 'mathematics', 'level' => 'S4', 'depts' => ['ST1', 'ST2'], 'label' => 'Mathematics S4 Stream 1 + Stream 2'],
			['subject' => 'mathematics', 'level' => 'S5', 'depts' => ['ST1', 'ST2'], 'label' => 'Mathematics S5 Stream 1 + Stream 2 (morning)'],
			['subject' => 'mathematics', 'level' => 'S6', 'depts' => ['ANP', 'PCB'], 'label' => 'Mathematics S6 ANP + PCB'],
			['subject' => 'mathematics', 'level' => 'S6', 'depts' => ['MCB', 'MCE', 'MEG', 'MPC', 'MPG', 'PCM'], 'label' => 'Mathematics S6 MCB + MCE + MEG + MPC + MPG + PCM'],
			['subject' => 'economics', 'level' => 'S6', 'depts' => ['MCE', 'MEG'], 'label' => 'Economics S6 MCE + MEG'],
			['subject' => 'entrepreneurship', 'level' => 'S4', 'depts' => ['ST1', 'ST2'], 'label' => 'Entrepreneurship S4 Stream 1 + Stream 2'],
			['subject' => 'entrepreneurship', 'level' => 'S5', 'depts' => ['ST1', 'ST2'], 'label' => 'Entrepreneurship S5 Stream 1 + Stream 2'],
			['subject' => 'entrepreneurship', 'level' => 'S6', 'depts' => ['MCE', 'MPG', 'PCB', 'MCB', 'MEG', 'MPC', 'PCM'], 'label' => 'Entrepreneurship S6 MCE + MPG + PCB + MCB + MEG + MPC + PCM'],
			['subject' => 'general_studies', 'level' => 'S4', 'depts' => ['ST1', 'ST2'], 'label' => 'General Studies S4 Stream 1 + Stream 2'],
			['subject' => 'general_studies', 'level' => 'S5', 'depts' => ['ST1', 'ST2'], 'label' => 'General Studies S5 Stream 1 + Stream 2'],
			['subject' => 'general_studies', 'level' => 'S6', 'depts' => ['MPC', 'MCB', 'MCE', 'MPG', 'MEG', 'PCM', 'PCB'], 'label' => 'General Studies S6 MPC + MCB + MCE + MPG + MEG + PCM + PCB'],
			['subject' => 'geography', 'level' => 'S6', 'depts' => ['MEG', 'MPG'], 'label' => 'Geography S6 MEG + MPG'],
			['subject' => 'biology', 'level' => 'S5', 'depts' => ['ANP', 'ST1'], 'label' => 'Biology S5 ANP + Stream 1'],
			['subject' => 'biology', 'level' => 'S5', 'depts' => ['PCB', 'HCB'], 'label' => 'Biology S5 PCB + HCB'],
			['subject' => 'biology', 'level' => 'S6', 'depts' => ['ANP', 'MCB', 'PCB'], 'label' => 'Biology S6 ANP + MCB + PCB'],
			['subject' => 'kinyarwanda', 'level' => 'S4', 'depts' => ['ST1', 'ST2'], 'label' => 'Kinyarwanda S4 Stream 1 + Stream 2'],
			['subject' => 'kinyarwanda', 'level' => 'S5', 'depts' => ['ST1', 'ST2'], 'label' => 'Kinyarwanda S5 Stream 1 + Stream 2'],
			['subject' => 'kinyarwanda', 'level' => 'S6', 'depts' => ['MCE', 'MPC', 'PCB', 'PCM', 'MEG'], 'label' => 'Kinyarwanda S6 MCE + MPC + PCB + PCM + MEG'],
			['subject' => 'english', 'level' => 'S4', 'depts' => ['ST1', 'ST2'], 'label' => 'English S4 Stream 1 + Stream 2'],
			['subject' => 'english', 'level' => 'S5', 'depts' => ['ST1', 'ST2'], 'label' => 'English S5 Stream 1 + Stream 2'],
			['subject' => 'english', 'level' => 'S6', 'depts' => ['MCE', 'MPC', 'PCB', 'PCM', 'MEG', 'MCB', 'MPG'], 'label' => 'English S6 MCE + MPC + PCB + PCM + MEG + MCB + MPG'],
		];
	}

	/**
	 * Locked document rules shown in Special criteria (always applied on generate).
	 *
	 * @return list<array{title:string,detail:string,group:string}>
	 */
	public static function documentCriteriaForDisplay(): array
	{
		$out = [
			['group' => 'Scope', 'title' => 'High school only', 'detail' => 'These locked rules apply to O Level, A Level, TVET and Special. Nursery and primary are never included.'],
			['group' => 'Blocks', 'title' => '4 and 6 periods', 'detail' => 'At least two periods together (doubles).'],
			['group' => 'Blocks', 'title' => '3, 5 and 7 periods', 'detail' => 'Put 2 together and 1 separately (5 periods → 3 teaching sessions).'],
			['group' => 'Blocks', 'title' => '2 periods', 'detail' => 'Schedule the two periods on separate days.'],
			['group' => 'PE', 'title' => 'Physical Education Sport', 'detail' => 'Always the last teaching hours of the day (14:20–15:40, preferring 15:00–15:40). Never after 15:40, never mid-morning or just after lunch. GISUBIZO SAMUEL / PE is locked there. At most one PE period per class day.'],
			['group' => 'After lessons', 'title' => 'Farming / Library and Clubs', 'detail' => 'Always after lessons end (15:40–17:30). Never during the teaching day, never night preps or supper.'],
			['group' => 'Alice', 'title' => 'Teacher Alice', 'detail' => 'Must not teach on Monday. Computer Science for S6 MPC and MCE is combined.'],
			['group' => 'Morning', 'title' => 'Mathematics and Physics', 'detail' => 'Prefer 07:00–12:00.'],
			['group' => 'Morning', 'title' => 'ANP teachers', 'detail' => 'Linea, Varlette and Marguerite teach ANP in the morning, Tuesday to Thursday.'],
			['group' => 'Morning', 'title' => 'S6 ANP', 'detail' => 'Prefer morning 07:00–12:00.'],
			['group' => 'Morning', 'title' => 'Other teachers', 'detail' => 'Teachers not named in this document use normal placement and fill from morning periods first.'],
			['group' => 'Combine', 'title' => 'No auto-combine', 'detail' => 'Only the listed groups are combined, each as its own subject + same teacher. ICT ST1+ST2 is a different clock from Physics ST1+ST2. Different subjects or different teachers are never combined. Courses not in the Word file stay separate.'],
			['group' => 'Clinical', 'title' => 'S4 and S5 ANP clinical', 'detail' => 'Tuesday 07:00–12:00.'],
			['group' => 'Clinical', 'title' => 'S6 ANP clinical', 'detail' => 'Wednesday full day 07:00–16:00.'],
			['group' => 'Windows', 'title' => 'Innocent', 'detail' => 'Monday 10:00–12:00, Friday 10:00–12:00, Wednesday 07:00–10:00.'],
			['group' => 'Windows', 'title' => 'Eric (L3 SOD)', 'detail' => 'Monday and Tuesday 07:00–10:00.'],
			['group' => 'Windows', 'title' => 'Olivier', 'detail' => 'Friday 07:00–10:00.'],
			['group' => 'Windows', 'title' => 'Varlette', 'detail' => 'Thursday 09:00–12:00, Friday 08:00–12:00, plus ANP mornings.'],
			['group' => 'Windows', 'title' => 'Marguerite', 'detail' => 'Monday morning, Wednesday morning, Thursday morning plus one after lunch.'],
			['group' => 'Windows', 'title' => 'Linea / Linear', 'detail' => 'Thursday 09:20–15:40, Friday 09:20–12:00, plus ANP mornings.'],
			['group' => 'Windows', 'title' => 'IZABAYO Patience', 'detail' => 'Tuesday before lunch is full (07:00–12:00), Friday after break (10:00–12:00), remaining periods on Sunday.'],
		];
		foreach (self::documentCombineGroups() as $group) {
			$out[] = [
				'group' => 'Combine',
				'title' => $group['label'],
				'detail' => 'This subject only, same teacher, listed classes share one clock. Other subjects keep their own times.',
			];
		}
		return $out;
	}

	/**
	 * @return list<array{level:string,a:string,b:string}>
	 */
	private function combinePairsForSubject(string $subject): array
	{
		$pairs = [];
		foreach (self::documentCombineGroups() as $group) {
			if (($group['subject'] ?? '') !== $subject) {
				continue;
			}
			$depts = array_values($group['depts'] ?? []);
			$level = (string) ($group['level'] ?? '');
			for ($i = 0; $i < count($depts); $i++) {
				for ($j = $i + 1; $j < count($depts); $j++) {
					$pairs[] = ['level' => $level, 'a' => $depts[$i], 'b' => $depts[$j]];
				}
			}
		}
		return $pairs;
	}

	/**
	 * @return list<string>
	 */
	private function combineGroupDepts(string $subject, string $level, string $dept): array
	{
		foreach (self::documentCombineGroups() as $group) {
			if (($group['subject'] ?? '') !== $subject || ($group['level'] ?? '') !== $level) {
				continue;
			}
			$depts = array_values($group['depts'] ?? []);
			if (in_array($dept, $depts, true)) {
				return $depts;
			}
		}
		return [];
	}

	/**
	 * @return list<string>
	 */
	private function combineDeptsFor(string $subject, string $level, string $dept): array
	{
		$group = $this->combineGroupDepts($subject, $level, $dept);
		if ($group === []) {
			return [];
		}
		return array_values(array_filter($group, static function ($d) use ($dept) {
			return $d !== $dept;
		}));
	}

	/**
	 * Saved UI combine_classes rules are not used for generation.
	 * Only Word-file groups with the same teacher and same subject combine.
	 *
	 * @return list<array<string,mixed>>
	 */
	private function customCombinePartners(array $row): array
	{
		return [];
	}

	/** @return list<int> */
	private function customGroupClassIds(array $row): array
	{
		$classId = (int) ($row['class_id'] ?? 0);
		$courseId = (int) ($row['course_id'] ?? $row['course'] ?? 0);
		foreach ($this->customCombineGroups as $group) {
			$ids = $group['class_ids'];
			if (!in_array($classId, $ids, true)) {
				continue;
			}
			$needCourse = (int) ($group['course_id'] ?? 0);
			if ($needCourse > 0 && $needCourse !== $courseId) {
				continue;
			}
			return $ids;
		}
		return [];
	}

	/** @return list<int> */
	private function decodeClassIds($raw): array
	{
		if (is_string($raw)) {
			$decoded = json_decode($raw, true);
			$raw = is_array($decoded) ? $decoded : explode(',', $raw);
		}
		if (!is_array($raw)) {
			return [];
		}
		$ids = [];
		foreach ($raw as $id) {
			$id = (int) $id;
			if ($id > 0) {
				$ids[] = $id;
			}
		}
		return array_values(array_unique($ids));
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
		if (strpos($t, 'econ') !== false) {
			return 'economics';
		}
		if (strpos($t, 'entrepreneur') !== false) {
			return 'entrepreneurship';
		}
		if (preg_match('/\bphysics\b/', $t) === 1) {
			return 'physics';
		}
		if (strpos($t, 'general stud') !== false || preg_match('/\bg\.?\s*s\.?\b/', $t) || strpos($t, 'gen stud') !== false) {
			return 'general_studies';
		}
		if (strpos($t, 'geograph') !== false || preg_match('/\bgeo\b/', $t)) {
			return 'geography';
		}
		if (strpos($t, 'kinyarwanda') !== false || strpos($t, 'ikinyarwanda') !== false) {
			return 'kinyarwanda';
		}
		if (strpos($t, 'english') !== false) {
			return 'english';
		}
		return $t;
	}

	private function ruleMatchesRow(array $rule, int $courseId, int $staffId, int $classId): bool
	{
		$rc = (int) ($rule['course_id'] ?? 0);
		$rt = (int) ($rule['teacher_id'] ?? 0);
		$rl = (int) ($rule['class_id'] ?? 0);
		if ($rc > 0 && $rc !== $courseId) {
			return false;
		}
		if ($rt > 0 && $rt !== $staffId) {
			return false;
		}
		if ($rl > 0 && $rl !== $classId) {
			return false;
		}
		return $rc > 0 || $rt > 0 || $rl > 0;
	}

	/** @return list<int> */
	private function decodeDays($raw): array
	{
		if (is_array($raw)) {
			return array_values(array_unique(array_map('intval', $raw)));
		}
		$s = trim((string) $raw);
		if ($s === '') {
			return [];
		}
		$decoded = json_decode($s, true);
		if (is_array($decoded)) {
			return array_values(array_unique(array_map('intval', $decoded)));
		}
		return array_values(array_unique(array_map('intval', explode(',', $s))));
	}

	private function normalizeLevel(string $level): string
	{
		$l = strtoupper(trim(preg_replace('/\s+/', ' ', $level)));
		if (preg_match('/\bS(?:ENIOR)?\s*([1-6])\b/', $l, $m)) {
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
			'MBC' => 'MCB', 'MEG' => 'MEG', 'MPG' => 'MPG', 'ACC' => 'ACC',
		];
		if (isset($map[$c])) {
			return $map[$c];
		}
		$hay = strtoupper($code . ' ' . $deptTitle . ' ' . $classTitle);
		if (strpos($hay, 'ANP') !== false || strpos($hay, 'NURS') !== false) {
			return 'ANP';
		}
		if (strpos($hay, 'STREAM 2') !== false || strpos($hay, 'STREAM II') !== false
			|| preg_match('/\bST(?:R(?:EAM)?)?\s*2\b/', $hay) || preg_match('/\bSTR\.?\s*II\b/', $hay)) {
			return 'ST2';
		}
		if (strpos($hay, 'STREAM 1') !== false || strpos($hay, 'STREAM I') !== false
			|| preg_match('/\bST(?:R(?:EAM)?)?\s*1\b/', $hay) || preg_match('/\bSTR\.?\s*I\b/', $hay)) {
			return 'ST1';
		}
		if (strpos($hay, 'MEG') !== false) {
			return 'MEG';
		}
		if (strpos($hay, 'MPG') !== false) {
			return 'MPG';
		}
		if (strpos($hay, 'MCB') !== false || strpos($hay, 'MBC') !== false) {
			return 'MCB';
		}
		if (strpos($hay, 'PCM') !== false) {
			return 'PCM';
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
	 * Document criteria stay locked during generate. Heavy teachers may pack
	 * more periods per day, but windows / Alice Monday / named days are never dropped.
	 *
	 * @param list<array<string,mixed>> $assignments
	 * @param list<array<string,mixed>> $teachingSlots
	 * @param list<int> $days
	 * @return list<string>
	 */
	public function relaxOverloadedRestrictions(array $assignments, array $teachingSlots, array $days): array
	{
		$slotsPerDay = 0;
		foreach ($teachingSlots as $slot) {
			if (!empty($slot['is_break'])) {
				continue;
			}
			$start = (string) ($slot['start_time'] ?? '');
			$end = (string) ($slot['end_time'] ?? '');
			if (\App\Models\TimetableSchemaModel::isAfterLessonSlotTimes($start, $end)
				|| \App\Models\TimetableSchemaModel::isNightSlotTimes($start, $end)) {
				continue;
			}
			$slotsPerDay++;
		}
		if ($slotsPerDay <= 0) {
			$slotsPerDay = 8;
		}

		$openDays = [];
		foreach ($days as $day) {
			$day = (int) $day;
			if ($day !== 6) {
				$openDays[] = $day;
			}
		}
		$openCount = max(1, count(array_unique($openDays)));
		$fullCap = $openCount * $slotsPerDay;

		$this->weeklyPeriodsByStaffId = $this->weeklyPeriodsByStaff($assignments);
		foreach ($this->weeklyPeriodsByStaffId as $staffId => $load) {
			if ($load > (int) floor($fullCap * 0.70)) {
				$this->heavyStaffIds[$staffId] = true;
			}
		}

		return [];
	}

	public function isPersonalRestrictionRelaxed(int $staffId, string $teacherName = ''): bool
	{
		if ($this->isPinnedTeacherName($teacherName)) {
			return false;
		}
		if ($staffId > 0 && isset($this->relaxedStaffIds[$staffId])) {
			return true;
		}
		$teacher = strtolower(trim(preg_replace('/\s+/', ' ', $teacherName) ?? ''));
		if ($teacher === '') {
			return false;
		}
		foreach (array_keys($this->relaxedNameNeedles) as $needle) {
			if ($this->teacherNameMatches($teacher, (string) $needle) || strpos($teacher, (string) $needle) !== false) {
				return true;
			}
		}

		return false;
	}

	public function isHeavyStaff(int $staffId): bool
	{
		return $staffId > 0 && isset($this->heavyStaffIds[$staffId]);
	}

	/**
	 * @param list<array<string,mixed>> $assignments
	 * @return array<int,int>
	 */
	private function weeklyPeriodsByStaff(array $assignments): array
	{
		$seenCombine = [];
		$out = [];
		foreach ($assignments as $row) {
			$staffId = (int) ($row['lecturer'] ?? $row['staff_id'] ?? 0);
			if ($staffId <= 0) {
				continue;
			}
			$hours = TimetableGeneratorService::weeklyHoursFromCourse($row);
			$combineKey = $this->combineGroupKey($row);
			if ($combineKey !== '' && isset($seenCombine[$combineKey])) {
				continue;
			}
			if ($combineKey !== '') {
				$seenCombine[$combineKey] = true;
				$out[$staffId] = (int) ($out[$staffId] ?? 0) + self::teacherSessionCount($hours);
				continue;
			}
			$out[$staffId] = (int) ($out[$staffId] ?? 0) + $hours;
		}

		return $out;
	}

	/** @param list<array<string,mixed>> $assignments */
	private function weeklyPeriodsForName(array $assignments, string $needle): int
	{
		$seen = [];
		$seenCombine = [];
		$total = 0;
		$needle = strtolower(trim($needle));
		foreach ($assignments as $row) {
			$teacher = strtolower(trim(preg_replace('/\s+/', ' ', (string) ($row['teacher_name'] ?? '')) ?? ''));
			if ($teacher === '' || (!$this->teacherNameMatches($teacher, $needle) && strpos($teacher, $needle) === false)) {
				continue;
			}
			$combineKey = $this->combineGroupKey($row);
			if ($combineKey !== '' && isset($seenCombine[$combineKey])) {
				continue;
			}
			if ($combineKey !== '') {
				$seenCombine[$combineKey] = true;
			}
			$staffId = (int) ($row['lecturer'] ?? $row['staff_id'] ?? 0);
			$key = $staffId . ':' . (int) ($row['class_id'] ?? 0) . ':' . (int) ($row['course_id'] ?? $row['course'] ?? 0);
			if (isset($seen[$key])) {
				continue;
			}
			$seen[$key] = true;
			$total += TimetableGeneratorService::weeklyHoursFromCourse($row);
		}

		return $total;
	}

	/** @param list<array<string,mixed>> $assignments */
	private function teacherLabel(array $assignments, int $staffId): string
	{
		foreach ($assignments as $row) {
			if ((int) ($row['lecturer'] ?? $row['staff_id'] ?? 0) !== $staffId) {
				continue;
			}
			$name = trim((string) ($row['teacher_name'] ?? ''));
			if ($name !== '') {
				return $name;
			}
		}

		return 'Teacher #' . $staffId;
	}

	/**
	 * @return list<array{day:int,start:int,end:int,scope:?string}>
	 */
	private function windowsForTeacher(string $teacher): array
	{
		foreach ($this->teacherWindows as $needle => $windows) {
			if ($this->teacherNameMatches($teacher, (string) $needle)) {
				return $windows;
			}
		}
		return [];
	}

	private function teacherNameMatches(string $teacher, string $needle): bool
	{
		$needle = strtolower(trim($needle));
		if ($needle === '') {
			return false;
		}
		$parts = preg_split('/\s+/', $needle) ?: [];
		foreach ($parts as $part) {
			if ($part !== '' && strpos($teacher, $part) === false) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @return array<string,list<array{day:int,start:int,end:int,scope:?string}>>
	 */
	private function defaultTeacherWindows(): array
	{
		// Day: Mon=0 … Fri=4. Times in minutes from midnight.
		$vallette = [
			['day' => 1, 'start' => 7 * 60, 'end' => 12 * 60, 'scope' => null],
			['day' => 2, 'start' => 7 * 60, 'end' => 12 * 60, 'scope' => null],
			['day' => 3, 'start' => 9 * 60, 'end' => 12 * 60, 'scope' => null],
			['day' => 4, 'start' => 8 * 60, 'end' => 12 * 60, 'scope' => null],
		];
		$linear = [
			['day' => 1, 'start' => 7 * 60, 'end' => 12 * 60, 'scope' => null],
			['day' => 2, 'start' => 7 * 60, 'end' => 12 * 60, 'scope' => null],
			['day' => 3, 'start' => 9 * 60 + 20, 'end' => 15 * 60 + 40, 'scope' => null],
			['day' => 4, 'start' => 9 * 60 + 20, 'end' => 12 * 60, 'scope' => null],
		];
		// IZABAYO PATIENCE: Tuesday before lunch full, Friday after break,
		// remaining periods on Sunday.
		$patienceWindows = [
			['day' => 1, 'start' => 7 * 60, 'end' => 12 * 60, 'scope' => null, 'priority' => 1],
			['day' => 4, 'start' => 10 * 60, 'end' => 12 * 60, 'scope' => null, 'priority' => 2],
			['day' => 6, 'start' => 7 * 60, 'end' => 15 * 60 + 40, 'scope' => null, 'priority' => 3],
		];
		return [
			'innocent' => [
				['day' => 0, 'start' => 10 * 60, 'end' => 12 * 60, 'scope' => null],
				['day' => 4, 'start' => 10 * 60, 'end' => 12 * 60, 'scope' => null],
				['day' => 2, 'start' => 7 * 60, 'end' => 10 * 60, 'scope' => null],
			],
			'eric' => [
				['day' => 0, 'start' => 7 * 60, 'end' => 10 * 60, 'scope' => 'sod_l3'],
				['day' => 1, 'start' => 7 * 60, 'end' => 10 * 60, 'scope' => 'sod_l3'],
			],
			'olivier' => [
				['day' => 4, 'start' => 7 * 60, 'end' => 10 * 60, 'scope' => null],
			],
			'varlette' => $vallette,
			'varliette' => $vallette,
			'vallette' => $vallette,
			'valiette' => $vallette,
			'yaliette' => $vallette,
			'yaliet' => $vallette,
			'valiet' => $vallette,
			'margueritte' => [
				['day' => 0, 'start' => 7 * 60, 'end' => 12 * 60, 'scope' => null],
				['day' => 2, 'start' => 7 * 60, 'end' => 12 * 60, 'scope' => null],
				['day' => 3, 'start' => 7 * 60, 'end' => 16 * 60, 'scope' => null],
			],
			'marguerite' => [
				['day' => 0, 'start' => 7 * 60, 'end' => 12 * 60, 'scope' => null],
				['day' => 2, 'start' => 7 * 60, 'end' => 12 * 60, 'scope' => null],
				['day' => 3, 'start' => 7 * 60, 'end' => 16 * 60, 'scope' => null],
			],
			'linear' => $linear,
			'linea' => $linear,
			'kubahoni' => $linear,
			'rinea' => $linear,
			// IZABAYO PATIENCE: Tuesday before lunch, Friday after break, Sunday.
			'izabayo patience' => $patienceWindows,
			'patience izabayo' => $patienceWindows,
			'izabayo gihanga' => $patienceWindows,
		];
	}

	public function isDocumentSpecialTeacher(array $row): bool
	{
		$teacher = strtolower(trim(preg_replace('/\s+/', ' ', (string) ($row['teacher_name'] ?? '')) ?? ''));
		if ($teacher === '') {
			return false;
		}
		if ($this->isPinnedTeacherName($teacher) || $this->windowsForTeacher($teacher) !== []) {
			return true;
		}
		foreach (array_keys($this->blockedDaysByName) as $needle) {
			if ($needle !== '' && strpos($teacher, (string) $needle) !== false) {
				return true;
			}
		}
		foreach ($this->anpMorningTeachers as $needle) {
			if ($needle !== '' && strpos($teacher, (string) $needle) !== false) {
				return true;
			}
		}
		return false;
	}

	private function isIzabayoPatience(string $teacher): bool
	{
		return $this->isPinnedTeacherName($teacher);
	}

	private function isPinnedTeacherName(string $teacher): bool
	{
		$teacher = strtolower(trim(preg_replace('/\s+/', ' ', $teacher) ?? ''));
		if ($teacher === '') {
			return false;
		}
		foreach (['izabayo patience', 'patience izabayo', 'izabayo gihanga'] as $needle) {
			if ($this->teacherNameMatches($teacher, $needle)) {
				return true;
			}
		}
		return strpos($teacher, 'izabayo') !== false && strpos($teacher, 'patience') !== false;
	}

	/** @param list<array<string,mixed>> $assignments */
	private function staffIsPinned(array $assignments, int $staffId): bool
	{
		if ($staffId <= 0) {
			return false;
		}
		foreach ($assignments as $row) {
			if ((int) ($row['lecturer'] ?? $row['staff_id'] ?? 0) !== $staffId) {
				continue;
			}
			if ($this->isPinnedTeacherName((string) ($row['teacher_name'] ?? ''))) {
				return true;
			}
		}
		return false;
	}

	public function isWindowFillTeacher(array $row): bool
	{
		return $this->fillPriorityBands($row) !== [];
	}

	public function hasOrderedFillWindows(array $row): bool
	{
		$priorities = [];
		foreach ($this->fillPriorityBands($row) as $band) {
			$priorities[(int) ($band['priority'] ?? 1)] = true;
		}
		return count($priorities) > 1;
	}

	/**
	 * Named / saved teacher windows, lowest priority number first.
	 *
	 * @return list<array{day:int,start:int,end:int,priority:int,scope:?string}>
	 */
	public function fillPriorityBands(array $row): array
	{
		$staffId = (int) ($row['lecturer'] ?? $row['staff_id'] ?? 0);
		$teacher = strtolower(trim(preg_replace('/\s+/', ' ', (string) ($row['teacher_name'] ?? '')) ?? ''));
		$windows = [];
		if ($staffId > 0 && !empty($this->windowsByStaffId[$staffId])) {
			foreach ($this->windowsByStaffId[$staffId] as $window) {
				$windows[] = $window;
			}
		}
		foreach ($this->windowsForTeacher($teacher) as $window) {
			$windows[] = $window;
		}
		if ($windows === [] && $staffId > 0 && isset($this->allowedDaysByStaffId[$staffId])) {
			foreach ($this->allowedDaysByStaffId[$staffId] as $day) {
				$windows[] = [
					'day' => (int) $day,
					'start' => 0,
					'end' => 24 * 60,
					'scope' => null,
					'priority' => 1,
				];
			}
		}
		$out = [];
		$seen = [];
		foreach ($windows as $window) {
			$day = (int) ($window['day'] ?? -1);
			$start = (int) ($window['start'] ?? 0);
			$end = (int) ($window['end'] ?? 0);
			if ($day < 0 || $end <= $start) {
				continue;
			}
			$key = $day . ':' . $start . ':' . $end;
			if (isset($seen[$key])) {
				continue;
			}
			$seen[$key] = true;
			$out[] = [
				'day' => $day,
				'start' => $start,
				'end' => $end,
				'priority' => (int) ($window['priority'] ?? 1),
				'scope' => $window['scope'] ?? null,
			];
		}
		usort($out, static function (array $a, array $b): int {
			return (int) $a['priority'] <=> (int) $b['priority'];
		});
		return $out;
	}

	/**
	 * Lock special-criteria cells that already sit in the right window.
	 * Overflow (Sunday for Patience) locks only after earlier windows are full.
	 *
	 * @param list<array{day:int,start:int,end:int}> $staffOccupied
	 * @param list<array<string,mixed>> $teachingSlots
	 */
	public function shouldLockPlacement(
		array $row,
		int $day,
		?string $slotStart,
		?string $slotEnd,
		array $staffOccupied,
		array $teachingSlots
	): bool {
		$bands = $this->fillPriorityBands($row);
		if ($bands === []) {
			return false;
		}
		if (!$this->teacherAllows($row, $day, $slotStart, $slotEnd)) {
			return false;
		}
		$start = $this->timeToMinutes((string) ($slotStart ?? '00:00:00'));
		$end = $this->timeToMinutes((string) ($slotEnd ?? '00:00:00'));
		$matchedPriority = null;
		foreach ($bands as $band) {
			if ((int) $band['day'] === $day && $start >= (int) $band['start'] && $end <= (int) $band['end']) {
				$matchedPriority = (int) $band['priority'];
				break;
			}
		}
		if ($matchedPriority === null) {
			return false;
		}
		foreach ($bands as $band) {
			if ((int) $band['priority'] >= $matchedPriority) {
				continue;
			}
			$cap = $this->countSlotsInBand($band, $teachingSlots);
			$filled = $this->countOccupiedInBand($band, $staffOccupied);
			if ($cap > 0 && $filled < $cap) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @param array{day:int,start:int,end:int} $band
	 * @param list<array<string,mixed>> $teachingSlots
	 */
	public function countSlotsInBand(array $band, array $teachingSlots): int
	{
		$count = 0;
		foreach ($teachingSlots as $slot) {
			if (!empty($slot['is_break'])) {
				continue;
			}
			$startRaw = (string) ($slot['start_time'] ?? $slot['start'] ?? '');
			$endRaw = (string) ($slot['end_time'] ?? $slot['end'] ?? '');
			$start = $this->timeToMinutes($startRaw);
			$end = $this->timeToMinutes($endRaw);
			if ($end <= $start) {
				continue;
			}
			if (\App\Models\TimetableSchemaModel::isAfterLessonSlotTimes($startRaw, $endRaw)
				|| \App\Models\TimetableSchemaModel::isNightSlotTimes($startRaw, $endRaw)) {
				continue;
			}
			if ($start >= (int) $band['start'] && $end <= (int) $band['end']) {
				$count++;
			}
		}
		return $count;
	}

	/**
	 * @param array{day:int,start:int,end:int} $band
	 * @param list<array{day:int,start:int,end:int}> $occupied
	 */
	public function countOccupiedInBand(array $band, array $occupied): int
	{
		$count = 0;
		$day = (int) ($band['day'] ?? -1);
		foreach ($occupied as $row) {
			if ((int) ($row['day'] ?? -1) !== $day) {
				continue;
			}
			$start = (int) ($row['start'] ?? 0);
			$end = (int) ($row['end'] ?? 0);
			if ($end <= $start) {
				continue;
			}
			if ($start >= (int) $band['start'] && $end <= (int) $band['end']) {
				$count++;
			}
		}
		return $count;
	}

	private function timeToMinutes(string $time): int
	{
		$parts = explode(':', substr($time, 0, 8));
		return ((int) ($parts[0] ?? 0)) * 60 + (int) ($parts[1] ?? 0);
	}
}
