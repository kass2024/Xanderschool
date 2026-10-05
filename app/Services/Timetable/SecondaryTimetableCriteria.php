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
	/**
	 * Stay in Manage Course. Never placed on a class or teacher timetable.
	 * L3 SOD: Occupation and learning process, Maintain SHE at Workplace.
	 *
	 * @var list<int>
	 */
	public const MANAGER_ONLY_COURSE_IDS = [476, 478];

	/** Locked ruleset used by every generation after the final Version 2 checkup. */
	public const RULES_VERSION = 2;

	/** When true, a leftover lesson may use an empty Mon–Thu 15:40–16:20 cell. */
	private $overflowFill = false;

	public function setOverflowFill(bool $on): void
	{
		$this->overflowFill = $on;
	}

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
				'level' => $this->entryLevel($row),
				'dept' => $this->entryDept($row),
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
	 * and clinical windows apply to high school only.
	 * S4, S5 and S6 ANP teach 07:00–16:20.
	 */
	public function slotAllowed(array $row, int $day, ?string $slotStart, ?string $slotEnd): bool
	{
		if (!$this->mathWindowAllows($row, $slotStart, $slotEnd)) {
			return false;
		}
		if ($this->isMorningHomeScience($row)) {
			$start = $this->timeToMinutes((string) ($slotStart ?? '00:00:00'));
			$end = $this->timeToMinutes((string) ($slotEnd ?? '00:00:00'));
			if ($day < 0 || $day > 4 || $start < (10 * 60) || $end > (12 * 60) || $end <= $start) {
				return false;
			}
			if (!\App\Models\TimetableSchemaModel::isTeachingDayLessonSlotTimes($slotStart, $slotEnd)) {
				return false;
			}
			return $this->teacherAllows($row, $day, $slotStart, $slotEnd);
		}
		if ($this->isSodLevel34($row) && $day >= 0 && $day <= 4) {
			if (!\App\Models\TimetableSchemaModel::isTeachingDayLessonSlotTimes($slotStart, $slotEnd)) {
				return false;
			}
			$start = $this->timeToMinutes((string) ($slotStart ?? '00:00:00'));
			$end = $this->timeToMinutes((string) ($slotEnd ?? '00:00:00'));
			if ($start < (7 * 60) || $end > (15 * 60 + 40)) {
				return false;
			}
			if ($this->clinicalBlocksClass($row, $day, $slotStart, $slotEnd)) {
				return false;
			}
			return $this->teacherAllows($row, $day, $slotStart, $slotEnd);
		}
		if ($this->isFixedEveningActivity($row)) {
			if (!\App\Models\TimetableSchemaModel::isLibraryHomeScienceClock($slotStart, $slotEnd)) {
				return false;
			}
			if ($day >= 5 && !$this->allowsSunday($row, $slotStart, $slotEnd)) {
				return false;
			}
			if ($this->clinicalBlocksClass($row, $day, $slotStart, $slotEnd)) {
				return false;
			}
			return $this->teacherAllows($row, $day, $slotStart, $slotEnd);
		}
		if ($this->isClinicalAttachmentCourse($row)) {
			if ($day >= 5 || !$this->isAnpClinicalWindow($row, $day, $slotStart, $slotEnd)) {
				return false;
			}
			return $this->teacherAllows($row, $day, $slotStart, $slotEnd);
		}
		if ($this->isAnpDept($row) && $this->isAnpClassHourSlot($slotStart, $slotEnd, $day)) {
			if ($this->clinicalBlocksClass($row, $day, $slotStart, $slotEnd)) {
				return false;
			}
			return $this->teacherAllows($row, $day, $slotStart, $slotEnd);
		}
		if ($this->requiresAfterLessons($row)) {
			if (!\App\Models\TimetableSchemaModel::isAfterLessonSlotTimes($slotStart, $slotEnd)) {
				return false;
			}
			if ($day >= 5 && !$this->allowsSunday($row, $slotStart, $slotEnd)) {
				return false;
			}
			if ($this->clinicalBlocksClass($row, $day, $slotStart, $slotEnd)) {
				return false;
			}
			return $this->teacherAllows($row, $day, $slotStart, $slotEnd);
		}
		if ($day >= 5) {
			if (!self::isSecondaryTrack($row) || !$this->allowsSunday($row, $slotStart, $slotEnd)) {
				return false;
			}
			if (!\App\Models\TimetableSchemaModel::isNormalCourseSlotForDay($slotStart, $slotEnd, $day)) {
				return false;
			}
			if ($this->clinicalBlocksClass($row, $day, $slotStart, $slotEnd)) {
				return false;
			}
			return $this->teacherAllows($row, $day, $slotStart, $slotEnd);
		}
		if ($this->overflowFill && $this->isOverflowLateLesson($row, $day, $slotStart, $slotEnd)) {
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
		if (self::isSecondaryTrack($row)
			&& !\App\Models\TimetableSchemaModel::isNormalCourseSlotForDay($slotStart, $slotEnd, $day)) {
			return false;
		}
		if ($this->clinicalBlocksClass($row, $day, $slotStart, $slotEnd)) {
			return false;
		}
		return $this->teacherAllows($row, $day, $slotStart, $slotEnd);
	}

	/**
	 * Empty Mon–Thu 15:40–16:20 cell. Used only after the normal day is full,
	 * so a leftover period is not left off the grid while that row is blank.
	 * Friday stays at 15:00. Library, chapel, dinner and preps are not this row.
	 */
	private function isOverflowLateLesson(array $row, int $day, ?string $slotStart, ?string $slotEnd): bool
	{
		if (!self::isSecondaryTrack($row) || $day < 0 || $day > 3) {
			return false;
		}
		if ($this->isSodLevel34($row)) {
			return false;
		}
		if ($this->isFixedEveningActivity($row) || $this->requiresAfterLessons($row) || $this->isClinicalAttachmentCourse($row)) {
			return false;
		}
		return \App\Models\TimetableSchemaModel::slotClock($slotStart) === '15:40:00'
			&& \App\Models\TimetableSchemaModel::slotClock($slotEnd) === '16:20:00';
	}

	/** S1–S3 Home Science is a morning lesson (10:00–12:00), not the 16:40 activity. */
	private function isMorningHomeScience(array $row): bool
	{
		if (self::afterLessonFamily((string) ($row['course_title'] ?? '')) !== 'home_science') {
			return false;
		}
		$level = $this->entryLevel($row);
		if (!in_array($level, ['S1', 'S2', 'S3'], true)) {
			$meta = $this->classMeta[(string) ((int) ($row['class_id'] ?? 0))] ?? null;
			$level = (string) ($meta['level'] ?? $level);
		}
		return in_array($level, ['S1', 'S2', 'S3'], true);
	}

	/** Level 3 and Level 4 Software Development stay inside 07:00–15:40. */
	private function isSodLevel34(array $row): bool
	{
		if (!self::isSecondaryTrack($row)) {
			return false;
		}
		$meta = $this->classMeta[(string) ((int) ($row['class_id'] ?? 0))] ?? null;
		$dept = $meta !== null ? (string) ($meta['dept'] ?? '') : $this->entryDept($row);
		$level = $meta !== null ? (string) ($meta['level'] ?? '') : $this->entryLevel($row);
		if ($dept !== 'SOD') {
			$dept = $this->entryDept($row);
		}
		if (!in_array($level, ['L3', 'L4'], true)) {
			$level = $this->entryLevel($row);
		}
		return $dept === 'SOD' && in_array($level, ['L3', 'L4'], true);
	}

	/** On Level 3 and Level 4 SOD, French and Chinese follow the core courses. */
	private function isSodLanguageAfterCore(array $row): bool
	{
		if (!$this->isSodLevel34($row)) {
			return false;
		}
		$t = $this->courseTitle($row);
		return strpos($t, 'french') !== false || strpos($t, 'chinese') !== false;
	}

	/** Library and Clubs, and Home Science from S4 up, sit only at 16:40–17:30. */
	public function isFixedEveningActivity(array $row): bool
	{
		if ($this->isMorningHomeScience($row)) {
			return false;
		}
		$family = self::afterLessonFamily((string) ($row['course_title'] ?? ''));
		return $family === 'library_clubs' || $family === 'home_science';
	}

	/** Farming / Library and Clubs / Home Science: after the teaching day, never night. */
	public static function isAfterLessonCourseTitle(string $title): bool
	{
		return self::afterLessonFamily($title) !== '';
	}

	/** farming | library_clubs | home_science | '' — same-teacher classes share one clock. */
	public static function afterLessonFamily(string $title): string
	{
		$t = strtolower(trim(preg_replace('/\s+/', ' ', $title)));
		if ($t === '') {
			return '';
		}
		if (strpos($t, 'farming') !== false) {
			return 'farming';
		}
		if (strpos($t, 'home science') !== false) {
			return 'home_science';
		}
		if (strpos($t, 'library') !== false || $t === 'club' || $t === 'clubs'
			|| preg_match('/\blibrary\b.*\bclubs?\b/', $t) === 1) {
			return 'library_clubs';
		}
		return '';
	}

	public function requiresAfterLessons(array $row): bool
	{
		if ($this->isMorningHomeScience($row)) {
			return false;
		}
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
			return false;
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
	 * Clinical attachment follows its class window, not the teacher's other days.
	 * Named ANP teachers keep their own windows. Other ANP lessons may use 07:00–16:20.
	 */
	public function teacherAllows(array $row, int $day, ?string $slotStart, ?string $slotEnd): bool
	{
		if ($this->isClinicalAttachmentCourse($row)) {
			return true;
		}
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

		// Olivier’s other ANP courses stay outside his Friday-only note.
		// Clinical attachment already has its own day. Named availability
		// for Varlette, Marguerite, Linea and the other teachers is kept.
		if ($this->isAnpDept($row) && ($this->isPrioritySubject($row) || $this->isAnpMainExtra($row))
			&& strpos($teacher, 'olivier') !== false) {
			return true;
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
			if ($scope === 'home_science_s123' && $this->isMorningHomeScience($row)) {
				$scopedApplicable[] = $w;
			}
			if ($scope === 'kiswahili_s1') {
				$title = $this->courseTitle($row);
				$kisLevel = $this->entryLevel($row);
				if ($kisLevel === '') {
					$kisLevel = $level;
				}
				if (strpos($title, 'kiswahili') !== false && $kisLevel === 'S1') {
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
	 * Mathematics windows that are fixed, not only preferred.
	 * S4 ST1 and ST2: 07:00–12:00 only.
	 * S1: after break (10:00) or after lunch, never 07:00–09:40.
	 */
	private function mathWindowAllows(array $row, ?string $slotStart, ?string $slotEnd): bool
	{
		if (!$this->isPlainMathematics($row) || !self::isSecondaryTrack($row)) {
			return true;
		}
		$meta = $this->classMeta[(string) ((int) ($row['class_id'] ?? 0))] ?? null;
		$level = $meta !== null ? (string) ($meta['level'] ?? '') : $this->entryLevel($row);
		$dept = $meta !== null ? (string) ($meta['dept'] ?? '') : $this->entryDept($row);
		$start = $this->timeToMinutes((string) ($slotStart ?? '00:00:00'));
		$end = $this->timeToMinutes((string) ($slotEnd ?? '00:00:00'));
		if ($end <= $start) {
			return false;
		}
		if ($level === 'S4' && in_array($dept, ['ST1', 'ST2'], true)) {
			return $start >= (7 * 60) && $end <= (12 * 60);
		}
		if ($level === 'S1') {
			return $start >= (10 * 60);
		}
		return true;
	}

	private function isPlainMathematics(array $row): bool
	{
		$t = $this->courseTitle($row);
		if ($t === '' || strpos($t, 'sub math') !== false) {
			return false;
		}
		return strpos($t, 'mathematics') !== false || preg_match('/\bmaths?\b/', $t) === 1;
	}

	/**
	 * Clinical attachment reserved (no regular lessons) for ANP only.
	 * Tuesday 07:00–12:00: S4 ANP and S5 ANP together.
	 * Wednesday full teaching day 07:00–16:20: S6 ANP.
	 * The clinical course itself is not blocked; it is the only lesson in that window.
	 */
	public function clinicalBlocksClass(array $row, int $day, ?string $slotStart, ?string $slotEnd): bool
	{
		if ($this->isClinicalAttachmentCourse($row)) {
			return false;
		}
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
		// Tuesday morning is the combined S4 and S5 clinical class.
		if ($day === 1 && in_array($level, ['S4', 'S5'], true)) {
			return $start < (12 * 60) && $end > (7 * 60);
		}
		if ($day === 2 && $level === 'S6') {
			return $start < (16 * 60 + 20) && $end > (7 * 60);
		}
		return false;
	}

	public function isClinicalAttachmentCourse(array $row): bool
	{
		return strpos($this->courseTitle($row), 'clinical attachment') !== false;
	}

	public function isAnpDept(array $row): bool
	{
		if (!self::isSecondaryTrack($row)) {
			return false;
		}
		$meta = $this->classMeta[(string) ((int) ($row['class_id'] ?? 0))] ?? null;
		if ($meta !== null) {
			return (string) ($meta['dept'] ?? '') === 'ANP';
		}
		return $this->entryDept($row) === 'ANP';
	}

	/**
	 * S4 and S5 together: Tuesday 07:00–12:00 (7 periods, one class).
	 * S6: Wednesday 07:00–16:20.
	 */
	public function isAnpClinicalWindow(array $row, int $day, ?string $slotStart, ?string $slotEnd): bool
	{
		if (!$this->isAnpDept($row)) {
			return false;
		}
		$level = $this->anpLevel($row);
		if ($level === 'S4' || $level === 'S5') {
			if ($day !== 1) {
				return false;
			}
			$start = $this->timeToMinutes((string) ($slotStart ?? '00:00:00'));
			$end = $this->timeToMinutes((string) ($slotEnd ?? '00:00:00'));
			return $start >= (7 * 60) && $end <= (12 * 60) && $end > $start
				&& \App\Models\TimetableSchemaModel::isTeachingDayLessonSlotTimes($slotStart, $slotEnd);
		}
		if ($level === 'S6') {
			return $day === 2 && $this->isAnpClassHourSlot($slotStart, $slotEnd, $day);
		}
		return false;
	}

	/**
	 * Lower numbers are placed first.
	 * 0 evening, 1 clinical, 2 Linea (no spare slots), 3 other named windows,
	 * 4 ANP priority subjects, 9 languages and ICT after those.
	 */
	public function placementRank(array $row): int
	{
		if ($this->isFixedEveningActivity($row) || self::isAfterLessonCourseTitle((string) ($row['course_title'] ?? ''))) {
			return 0;
		}
		if ($this->isClinicalAttachmentCourse($row)) {
			return 1;
		}
		$teacher = strtolower(trim(preg_replace('/\s+/', ' ', (string) ($row['teacher_name'] ?? ''))));
		if ($teacher !== '' && (strpos($teacher, 'linea') !== false || strpos($teacher, 'linear') !== false)) {
			return 2;
		}
		foreach (['varlette', 'varliette', 'vallette', 'ntabanganyimana', 'margueritte', 'marguerite', 'bunezero', 'ntazika', 'izabayo', 'patience'] as $needle) {
			if ($teacher !== '' && strpos($teacher, $needle) !== false) {
				return 3;
			}
		}
		if ($this->isPrioritySubject($row) || $this->isAnpMainExtra($row)) {
			return 4;
		}
		if ($this->isAnpSecondWave($row) || $this->isSodLanguageAfterCore($row)) {
			return 9;
		}
		return 5;
	}

	public function isPrioritySubject(array $row): bool
	{
		$t = $this->courseTitle($row);
		if ($t === '' || strpos($t, 'sub math') !== false) {
			return false;
		}
		foreach (['medical pathology', 'surgical pathology', 'pharmacology', 'fundamentals of nursing', 'ethics'] as $phrase) {
			if (strpos($t, $phrase) !== false) {
				return true;
			}
		}
		return (bool) preg_match('/\bmch\b|\bbiology\b|\bchemistry\b|\bphysics\b|\bmathematics\b|\bmaths\b|\benglish\b/', $t);
	}

	/** S4 Kinyarwanda is placed with the main courses, not with the later languages. */
	public function isAnpMainExtra(array $row): bool
	{
		return $this->isAnpDept($row)
			&& $this->anpLevel($row) === 'S4'
			&& strpos($this->courseTitle($row), 'kinyarwanda') !== false;
	}

	public function isAnpSecondWave(array $row): bool
	{
		if (!$this->isAnpDept($row)) {
			return false;
		}
		$names = [
			'S6' => ['french', 'kinyarwanda', 'ict'],
			'S5' => ['french', 'citizenship', 'kinyarwanda', 'ict'],
			'S4' => ['ict', 'french', 'citizenship', 'entrepreneurship'],
		];
		$level = $this->anpLevel($row);
		if (!isset($names[$level])) {
			return false;
		}
		$t = $this->courseTitle($row);
		foreach ($names[$level] as $name) {
			if ($name === 'ict') {
				if (preg_match('/\bict\b/', $t)) {
					return true;
				}
				continue;
			}
			if (strpos($t, $name) !== false) {
				return true;
			}
		}
		return false;
	}

	private function courseTitle(array $row): string
	{
		return strtolower(trim(preg_replace('/\s+/', ' ', (string) ($row['course_title'] ?? ''))));
	}

	private function anpLevel(array $row): string
	{
		$meta = $this->classMeta[(string) ((int) ($row['class_id'] ?? 0))] ?? null;
		if ($meta !== null && (string) ($meta['level'] ?? '') !== '') {
			return (string) $meta['level'];
		}
		return $this->entryLevel($row);
	}

	/** Teaching cells from 07:00 through the period that ends at 16:20. Not Sunday. */
	private function isAnpClassHourSlot(?string $slotStart, ?string $slotEnd, int $day): bool
	{
		if ($day >= 5) {
			return false;
		}
		$start = $this->timeToMinutes((string) ($slotStart ?? '00:00:00'));
		$end = $this->timeToMinutes((string) ($slotEnd ?? '00:00:00'));
		if ($end <= $start || $start < (7 * 60) || $end > (16 * 60 + 20)) {
			return false;
		}
		if (\App\Models\TimetableSchemaModel::isTeachingDayLessonSlotTimes($slotStart, $slotEnd)) {
			return true;
		}
		return \App\Models\TimetableSchemaModel::slotClock($slotStart) === '15:40:00'
			&& \App\Models\TimetableSchemaModel::slotClock($slotEnd) === '16:20:00';
	}

	/**
	 * Partner assignments for one combined lesson.
	 * Word-file groups: same teacher AND same subject AND listed classes.
	 * After-lesson (Farming / Library and Clubs): same teacher AND same activity
	 * across classes, so one clock fills the 15:40+ grid instead of parking.
	 *
	 * @return list<array<string,mixed>>
	 */
	public function combinePartners(array $row): array
	{
		if (!self::isSecondaryTrack($row)) {
			return [];
		}
		$doc = $this->documentCombinePartners($row);
		if ($doc !== []) {
			return $doc;
		}
		$clinical = $this->clinicalCombinePartners($row);
		if ($clinical !== []) {
			return $clinical;
		}
		$mch = $this->mchCombinePartners($row);
		if ($mch !== []) {
			return $mch;
		}
		return $this->afterLessonCombinePartners($row);
	}

	/**
	 * S4 ANP and S5 ANP clinical share Tuesday 07:00–12:00 as one class.
	 * S6 clinical stays on its own Wednesday.
	 *
	 * @return list<array<string,mixed>>
	 */
	private function clinicalCombinePartners(array $row): array
	{
		if (!$this->isClinicalAttachmentCourse($row) || !$this->isAnpDept($row)) {
			return [];
		}
		$level = $this->anpLevel($row);
		if (!in_array($level, ['S4', 'S5'], true)) {
			return [];
		}
		$out = [];
		$seen = [];
		foreach ($this->assignmentsByKey as $cand) {
			$cid = (int) ($cand['class_id'] ?? 0);
			if ($cid <= 0 || $cid === (int) ($row['class_id'] ?? 0) || isset($seen[$cid])) {
				continue;
			}
			if (!$this->isClinicalAttachmentCourse($cand) || !$this->isAnpDept($cand)) {
				continue;
			}
			$other = $this->anpLevel($cand);
			if (!in_array($other, ['S4', 'S5'], true) || $other === $level) {
				continue;
			}
			if (!$this->isSameCombineTeacher($row, $cand)) {
				continue;
			}
			$seen[$cid] = true;
			$out[] = $cand;
		}
		return $out;
	}

	/**
	 * S4, S5 and S6 MCH are three different classes.
	 *
	 * @return list<array<string,mixed>>
	 */
	private function mchCombinePartners(array $row): array
	{
		return [];
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	private function documentCombinePartners(array $row): array
	{
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
	 * Same Farming teacher (or same Library teacher) → one after-lesson clock for every class.
	 *
	 * @return list<array<string,mixed>>
	 */
	private function afterLessonCombinePartners(array $row): array
	{
		if (!$this->requiresAfterLessons($row)) {
			return [];
		}
		$classId = (int) ($row['class_id'] ?? 0);
		$family = self::afterLessonFamily((string) ($row['course_title'] ?? ''));
		if ($classId <= 0 || $family === '') {
			return [];
		}
		$staffId = (int) ($row['lecturer'] ?? $row['staff_id'] ?? 0);
		$teacher = strtolower(trim(preg_replace('/\s+/', ' ', (string) ($row['teacher_name'] ?? ''))));
		if ($staffId <= 0 && $teacher === '') {
			return [];
		}
		$out = [];
		$seen = [];
		foreach ($this->assignmentsByKey as $cand) {
			$cid = (int) ($cand['class_id'] ?? 0);
			$courseId = (int) ($cand['course_id'] ?? $cand['course'] ?? 0);
			$seenKey = $cid . ':' . $courseId;
			if ($cid === $classId || $cid <= 0 || isset($seen[$seenKey])) {
				continue;
			}
			if (!self::isSecondaryTrack($cand) || !$this->requiresAfterLessons($cand)) {
				continue;
			}
			if (self::afterLessonFamily((string) ($cand['course_title'] ?? '')) !== $family) {
				continue;
			}
			if (!$this->isSameCombineTeacher($row, $cand)) {
				continue;
			}
			$seen[$seenKey] = true;
			$out[] = $cand;
		}
		return $out;
	}

	public function isSameCombineTeacher(array $a, array $b): bool
	{
		$staffA = (int) ($a['lecturer'] ?? $a['staff_id'] ?? 0);
		$staffB = (int) ($b['lecturer'] ?? $b['staff_id'] ?? 0);
		if ($staffA > 0 && $staffA === $staffB) {
			return true;
		}
		$nameA = strtolower(trim(preg_replace('/\s+/', ' ', (string) ($a['teacher_name'] ?? ''))));
		$nameB = strtolower(trim(preg_replace('/\s+/', ' ', (string) ($b['teacher_name'] ?? ''))));
		return $nameA !== '' && $nameA === $nameB;
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
		$family = self::afterLessonFamily((string) ($row['course_title'] ?? ''));
		if ($family !== '') {
			$staffId = (int) ($row['lecturer'] ?? $row['staff_id'] ?? 0);
			$teacher = strtolower(trim(preg_replace('/\s+/', ' ', (string) ($row['teacher_name'] ?? ''))));
			$who = $staffId > 0 ? (string) $staffId : ('n:' . $teacher);
			if ($who === 'n:') {
				return '';
			}
			return 'after|' . $who . '|' . $family;
		}
		if ($this->isClinicalAttachmentCourse($row)) {
			$level = $this->anpLevel($row);
			$staffId = (int) ($row['lecturer'] ?? $row['staff_id'] ?? 0);
			if (in_array($level, ['S4', 'S5'], true) && $staffId > 0) {
				return $staffId . '|clinical|S4-S5';
			}
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

	/** How many timetable blocks a weekly load splits into (5 hours → 3 blocks of 2+2+1). Period totals still use the full Manage Course credit. */
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
			'farming' => ['bg' => '#d9f99d', 'fg' => '#365314', 'accent' => '#65a30d'],
			'library_clubs' => ['bg' => '#fde68a', 'fg' => '#78350f', 'accent' => '#d97706'],
		];
		$tone = $map[$family] ?? ['bg' => '#ccfbf1', 'fg' => '#134e4a', 'accent' => '#0f766e'];
		$slug = preg_replace('/[^a-z0-9]+/', '-', $family);
		$tone['slug'] = ($slug !== null && $slug !== '') ? $slug : 'combined';
		return $tone;
	}

	/** Same teacher + same document combine group = one lesson, not a clash. */
	public static function entriesAreCombinedLesson(array $a, array $b): bool
	{
		if ((int) ($a['class_id'] ?? 0) === (int) ($b['class_id'] ?? 0)) {
			return false;
		}
		$staffA = (int) ($a['staff_id'] ?? $a['lecturer'] ?? 0);
		$staffB = (int) ($b['staff_id'] ?? $b['lecturer'] ?? 0);
		$titleA = (string) ($a['course_title'] ?? $a['custom_label'] ?? $a['course'] ?? '');
		$titleB = (string) ($b['course_title'] ?? $b['custom_label'] ?? $b['course'] ?? '');
		$afterA = self::afterLessonFamily($titleA);
		$afterB = self::afterLessonFamily($titleB);
		$sameTeacher = ($staffA > 0 && $staffA === $staffB);
		if (!$sameTeacher) {
			$nameA = strtolower(trim(preg_replace('/\s+/', ' ', (string) ($a['teacher_name'] ?? ''))));
			$nameB = strtolower(trim(preg_replace('/\s+/', ' ', (string) ($b['teacher_name'] ?? ''))));
			$sameTeacher = $nameA !== '' && $nameA === $nameB;
		}
		if ($afterA !== '' && $afterA === $afterB) {
			return $sameTeacher;
		}
		if (!$sameTeacher || $staffA <= 0) {
			return false;
		}
		$famA = self::subjectFamily($titleA);
		$famB = self::subjectFamily($titleB);
		if ($famA === '' || $famA !== $famB) {
			return false;
		}
		$c = new self();
		$levelA = $c->entryLevel($a);
		$levelB = $c->entryLevel($b);
		$deptA = $c->entryDept($a);
		$deptB = $c->entryDept($b);
		if ($levelA !== '' && $levelA === $levelB && $deptA !== '' && $deptB !== '') {
			foreach (self::documentCombineGroups() as $group) {
				if (($group['subject'] ?? '') !== $famA || ($group['level'] ?? '') !== $levelA) {
					continue;
				}
				$depts = $group['depts'] ?? [];
				if (in_array($deptA, $depts, true) && in_array($deptB, $depts, true)) {
					return true;
				}
			}
		}
		if ($famA === 'clinical') {
			return in_array($levelA, ['S4', 'S5'], true)
				&& in_array($levelB, ['S4', 'S5'], true)
				&& $levelA !== $levelB;
		}
		if ($levelA !== '' && $levelB !== '' && $levelA !== $levelB) {
			return false;
		}
		$titleNormA = strtolower(trim(preg_replace('/\s+/', ' ', $titleA)));
		$titleNormB = strtolower(trim(preg_replace('/\s+/', ' ', $titleB)));
		return $titleNormA !== '' && $titleNormA === $titleNormB;
	}

	public function entryLevel(array $row): string
	{
		$raw = trim(implode(' ', array_filter([
			(string) ($row['level_name'] ?? ''),
			(string) ($row['level_title'] ?? ''),
			(string) ($row['class_title'] ?? ''),
			(string) ($row['class'] ?? ''),
		])));
		return $this->normalizeLevel($raw);
	}

	public function entryDept(array $row): string
	{
		return $this->normalizeDept(
			(string) ($row['dept_code'] ?? $row['code'] ?? ''),
			(string) ($row['dept_title'] ?? $row['dept_name'] ?? ''),
			(string) ($row['class_title'] ?? $row['class'] ?? '')
		);
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
		if ($this->isClinicalAttachmentCourse($row)) {
			return max($weeklyHours, $fallback);
		}
		$peMax = $this->peMaxPerDay($row, $weeklyHours);
		if ($peMax !== null) {
			return $peMax;
		}
		if ($weeklyHours <= 0) {
			return $fallback;
		}
		$days = $this->restrictedTeachingDays($row);
		if ($days === null || $days === []) {
			return $fallback;
		}
		$n = count($days);
		$pack = (int) ceil($weeklyHours / max(1, $n));
		// A two- or three-day window has to hold the whole course.
		// Tuesday 07:00–10:00 is four periods, so the old cap of 3 was too small.
		if ($n <= 3 || $this->hasOrderedFillWindows($row)) {
			return $weeklyHours;
		}
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
			['subject' => 'chemistry', 'level' => 'S6', 'depts' => ['ANP', 'MCB', 'PCB'], 'label' => 'Chemistry S6 ANP + MCB + PCB'],
			['subject' => 'chemistry', 'level' => 'S4', 'depts' => ['ANP', 'ST1'], 'label' => 'Chemistry S4 ANP + Stream 1'],
			['subject' => 'physics', 'level' => 'S4', 'depts' => ['ST1', 'ST2'], 'label' => 'Physics S4 Stream 1 + Stream 2'],
			['subject' => 'physics', 'level' => 'S5', 'depts' => ['ST1', 'ST2'], 'label' => 'Physics S5 Stream 1 + Stream 2'],
			['subject' => 'physics', 'level' => 'S6', 'depts' => ['PCB', 'PCM', 'MPC', 'ANP', 'MPG'], 'label' => 'Physics S6 PCB + PCM + MPC + ANP + MPG'],
			['subject' => 'mathematics', 'level' => 'S4', 'depts' => ['ST1', 'ST2'], 'label' => 'Mathematics S4 Stream 1 + Stream 2 (07:00–12:00)'],
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
			['subject' => 'biology', 'level' => 'S5', 'depts' => ['PCB', 'HCB'], 'label' => 'Biology S5 PCB + HCB'],
			['subject' => 'biology', 'level' => 'S6', 'depts' => ['ANP', 'MCB', 'PCB'], 'label' => 'Biology S6 ANP + MCB + PCB'],
			['subject' => 'kinyarwanda', 'level' => 'S4', 'depts' => ['ST1', 'ST2'], 'label' => 'Kinyarwanda S4 Stream 1 + Stream 2'],
			['subject' => 'kinyarwanda', 'level' => 'S5', 'depts' => ['ST1', 'ST2'], 'label' => 'Kinyarwanda S5 Stream 1 + Stream 2'],
			['subject' => 'kinyarwanda', 'level' => 'S6', 'depts' => ['MCE', 'MPC', 'PCB', 'PCM', 'MEG', 'MCB', 'MPG'], 'label' => 'Kinyarwanda S6 MCE + MPC + PCB + PCM + MEG + MCB + MPG'],
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
			['group' => 'Day end', 'title' => 'Normal courses', 'detail' => 'Normal courses finish by 15:40 Monday to Thursday. On Friday they finish by 15:00, so 15:00–15:40 is not a normal lesson. Special activities, farming, library, and night periods stay on their own bells.'],
			['group' => 'Day end', 'title' => 'Level 3 and Level 4 SOD', 'detail' => 'These courses start at 07:00 and finish by 15:40, including Friday. The 15:40–16:20 row is not used. Core courses are placed before French and Chinese.'],
			['group' => 'Day end', 'title' => 'S4, S5 and S6 ANP', 'detail' => 'These classes teach 07:00–16:20, including 15:40–16:20, Monday to Friday.'],
			['group' => 'Priority', 'title' => 'Schedule first', 'detail' => 'Clinical attachment, medical pathology, surgical pathology, pharmacology, MCH, fundamentals of nursing, ethics, biology, chemistry, physics, mathematics, and English. Medical and surgical pathology are not taught in S4 ANP. S4 Kinyarwanda is with these main courses. Named teacher availability is kept.'],
			['group' => 'Priority', 'title' => 'S6 ANP after main courses', 'detail' => 'French, Kinyarwanda, and ICT.'],
			['group' => 'Priority', 'title' => 'S5 ANP after main courses', 'detail' => 'French, citizenship, Kinyarwanda, and ICT.'],
			['group' => 'Priority', 'title' => 'S4 ANP after main courses', 'detail' => 'ICT, French, citizenship, and entrepreneurship.'],
			['group' => 'Blocks', 'title' => '4 and 6 periods', 'detail' => 'At least two periods together (doubles).'],
			['group' => 'Blocks', 'title' => '3, 5 and 7 periods', 'detail' => 'Put 2 together and 1 separately (5 periods → 3 teaching sessions).'],
			['group' => 'Blocks', 'title' => '2 periods', 'detail' => 'Schedule the two periods on separate days.'],
			['group' => 'PE', 'title' => 'Physical Education Sport', 'detail' => 'Always the last teaching period of the class (15:00–15:40; on Friday 14:20–15:00). For S4, S5 and S6 ANP the last period is 15:40–16:20. If that cell is taken, the other lesson is moved earlier. Never 13:40. At most one PE period per class day.'],
			['group' => 'After lessons', 'title' => 'Library and Clubs / Home Science', 'detail' => '16:40–17:30 only, for the listed classes. S1 A, S1 B, S1 C, S1 D, S2 A, S2 B and S3 Home Science is the exception: NDAGIJIMANA John teaches it at 10:00–12:00. Same teacher shares one clock. Not at chapel, dinner, or preps.'],
			['group' => 'After lessons', 'title' => 'Evening bells', 'detail' => '17:30–18:00 chapel, 18:00–19:00 dinner, 19:00–21:00 preps.'],
			['group' => 'After lessons', 'title' => 'Farming', 'detail' => 'After lessons (15:40–17:30), never night. Same teacher shares one clock.'],
			['group' => 'Alice', 'title' => 'Teacher Alice', 'detail' => 'Must not teach on Monday. Computer Science for S6 MPC and MCE is combined.'],
			['group' => 'Morning', 'title' => 'Mathematics and Physics', 'detail' => 'Prefer 07:00–12:00. S4 ST1 and ST2 Mathematics stay inside 07:00–12:00. S1 Mathematics is after break or after lunch.'],
			['group' => 'Morning', 'title' => 'ANP teachers', 'detail' => 'Varlette, Marguerite and Linea teach S4, S5 and S6 ANP only inside the times listed under Windows.'],
			['group' => 'Morning', 'title' => 'S6 ANP', 'detail' => 'Prefer morning 07:00–12:00.'],
			['group' => 'Morning', 'title' => 'Other teachers', 'detail' => 'Teachers not named in this document use normal placement and fill from morning periods first.'],
			['group' => 'Combine', 'title' => 'No auto-combine', 'detail' => 'Word-file groups stay one subject + same teacher. Farming and Library and Clubs also combine when the teacher is the same (each activity keeps its own clock). Different academic subjects or different teachers are never combined.'],
			['group' => 'Clinical', 'title' => 'S4 and S5 ANP clinical', 'detail' => 'One combined class, Tuesday 07:00–12:00, 7 periods. No other S4 or S5 ANP lesson in that window.'],
			['group' => 'Clinical', 'title' => 'S6 ANP clinical', 'detail' => 'Wednesday full teaching day 07:00–16:20. No other S6 ANP lesson on Wednesday.'],
			['group' => 'Windows', 'title' => 'Innocent', 'detail' => 'Monday 10:00–12:00, Friday 10:00–12:00, Wednesday 07:00–10:00.'],
			['group' => 'Windows', 'title' => 'Eric (L3 SOD)', 'detail' => 'Monday and Tuesday 07:00–10:00.'],
			['group' => 'Windows', 'title' => 'Olivier', 'detail' => 'Friday 07:00–10:00.'],
			['group' => 'Windows', 'title' => 'Varlette', 'detail' => 'Thursday 09:00–12:00 and Friday 08:00–12:00. S4, S5 and S6 MCH are three different classes.'],
			['group' => 'Windows', 'title' => 'Marguerite', 'detail' => 'Monday 07:00–10:00, Wednesday 08:00–10:00, and Thursday 07:00–16:20.'],
			['group' => 'Windows', 'title' => 'Linea / Linear', 'detail' => 'Thursday 09:00–15:40. Friday 09:20–10:00 is the 09:00–09:40 lesson.'],
			['group' => 'Windows', 'title' => 'Jean Pierre Bunezero', 'detail' => 'Every weekday 10:00–12:00 and 13:40–15:40.'],
			['group' => 'Windows', 'title' => 'NDAGIJIMANA John', 'detail' => 'Home Science for S1, S2 and S3, every weekday 10:00–12:00.'],
			['group' => 'Windows', 'title' => 'ISHARA NYORHA CHRISPIN', 'detail' => 'Kiswahili for S1 A–D. Tuesday 10:40–12:00, Wednesday 07:40–08:20, Friday 10:00–14:20. The Friday 13:40 bell ends at 14:20 so the period covering 14:10 can be used.'],
			['group' => 'Windows', 'title' => 'NTAZIKA Elias', 'detail' => 'Management Accounting, every weekday 10:00–12:00.'],
			['group' => 'Windows', 'title' => 'IZABAYO Patience', 'detail' => 'Taxation: Tuesday 07:00–10:00, then Friday 13:00–15:00.'],
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
		if (strpos($t, 'clinical attachment') !== false) {
			return 'clinical';
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
		if (strpos($t, 'farming') !== false) {
			return 'farming';
		}
		if (strpos($t, 'library') !== false || $t === 'club' || $t === 'clubs') {
			return 'library_clubs';
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
			if ($combineKey !== '') {
				$previous = $seenCombine[$combineKey] ?? null;
				if ($previous === null) {
					$seenCombine[$combineKey] = $hours;
					$out[$staffId] = (int) ($out[$staffId] ?? 0) + $hours;
				} elseif ($hours > $previous) {
					$out[$staffId] = (int) ($out[$staffId] ?? 0) + ($hours - $previous);
					$seenCombine[$combineKey] = $hours;
				}
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
			$hours = TimetableGeneratorService::weeklyHoursFromCourse($row);
			$combineKey = $this->combineGroupKey($row);
			if ($combineKey !== '') {
				$previous = $seenCombine[$combineKey] ?? null;
				if ($previous === null) {
					$seenCombine[$combineKey] = $hours;
					$total += $hours;
				} elseif ($hours > $previous) {
					$total += $hours - $previous;
					$seenCombine[$combineKey] = $hours;
				}
				continue;
			}
			$staffId = (int) ($row['lecturer'] ?? $row['staff_id'] ?? 0);
			$key = $staffId . ':' . (int) ($row['class_id'] ?? 0) . ':' . (int) ($row['course_id'] ?? $row['course'] ?? 0);
			if (isset($seen[$key])) {
				continue;
			}
			$seen[$key] = true;
			$total += $hours;
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
			['day' => 3, 'start' => 9 * 60, 'end' => 12 * 60, 'scope' => null],
			['day' => 4, 'start' => 8 * 60, 'end' => 12 * 60, 'scope' => null],
		];
		$marguerite = [
			['day' => 0, 'start' => 7 * 60, 'end' => 10 * 60, 'scope' => null],
			['day' => 2, 'start' => 8 * 60, 'end' => 10 * 60, 'scope' => null],
			['day' => 3, 'start' => 7 * 60, 'end' => 16 * 60 + 20, 'scope' => null],
		];
		$linear = [
			['day' => 3, 'start' => 9 * 60, 'end' => 15 * 60 + 40, 'scope' => null],
			['day' => 4, 'start' => 9 * 60, 'end' => 9 * 60 + 40, 'scope' => null],
		];
		$midMorning = [];
		$bunezero = [];
		$johnHome = [];
		foreach ([0, 1, 2, 3, 4] as $day) {
			$midMorning[] = ['day' => $day, 'start' => 10 * 60 + 40, 'end' => 12 * 60, 'scope' => null];
			$bunezero[] = ['day' => $day, 'start' => 10 * 60, 'end' => 12 * 60, 'scope' => null];
			$bunezero[] = ['day' => $day, 'start' => 13 * 60 + 40, 'end' => 15 * 60 + 40, 'scope' => null];
			$johnHome[] = ['day' => $day, 'start' => 10 * 60, 'end' => 12 * 60, 'scope' => 'home_science_s123'];
		}
		$ishara = [
			['day' => 1, 'start' => 10 * 60 + 40, 'end' => 12 * 60, 'scope' => 'kiswahili_s1'],
			['day' => 2, 'start' => 7 * 60 + 40, 'end' => 8 * 60 + 20, 'scope' => 'kiswahili_s1'],
			['day' => 4, 'start' => 10 * 60, 'end' => 14 * 60 + 20, 'scope' => 'kiswahili_s1'],
		];
		$lateMorning = [];
		foreach ([0, 1, 2, 3, 4] as $day) {
			$lateMorning[] = ['day' => $day, 'start' => 10 * 60, 'end' => 12 * 60, 'scope' => null];
		}
		// IZABAYO PATIENCE: Tuesday 07:00–10:00, then Friday 13:00–15:00.
		$patienceWindows = [
			['day' => 1, 'start' => 7 * 60, 'end' => 10 * 60, 'scope' => null, 'priority' => 1],
			['day' => 4, 'start' => 13 * 60, 'end' => 15 * 60, 'scope' => null, 'priority' => 2],
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
			'ntabanganyimana' => $vallette,
			'margueritte' => $marguerite,
			'marguerite' => $marguerite,
			'linear' => $linear,
			'linea' => $linear,
			'kubahoni' => $linear,
			'rinea' => $linear,
			'bunezero' => $bunezero,
			'ndagijimana john' => $johnHome,
			'ishara' => $ishara,
			'nyorha' => $ishara,
			'ntazika' => $lateMorning,
			// IZABAYO PATIENCE: Tuesday 07:00–10:00, Friday 13:00–15:00.
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
