<?php

namespace App\Services\Timetable;

/**
 * Constraint-based timetable generator (aSc-style weekly grid).
 *
 * Primary/Nursery: mostly one period per day (math may cluster).
 * Secondary (O/A Level, RTB, Special): 2h → singles on different days;
 * 3h+ → at least one adjacent double, with subject blocks on non-adjacent days
 * (e.g. Mon+Wed, not Mon+Tue).
 */
class TimetableGeneratorService
{
	/** @var list<int> Mon=0 .. Fri=4, Sun=6 */
	private $days = [0, 1, 2, 3, 4];

	/** @var list<array<string,mixed>> */
	private $teachingSlots = [];

	/** @var array<int,array{start:string,end:string}> */
	private $slotTimes = [];

	/** @var array<string,bool> */
	private $classBusy = [];

	/** @var array<string,bool> */
	private $staffBusy = [];

	/** @var array<int,array<int,list<array{start:int,end:int}>>> */
	private $staffTimeBookings = [];

	/** @var array<string,int> */
	private $subjectDayCount = [];

	/** @var array<string,int> */
	private $classDayUsage = [];

	/** @var array<int,int> */
	private $globalDayUsage = [];

	/** @var array<string,bool> */
	private $blocked = [];

	/** @var list<string> */
	private $warnings = [];

	public static function distributeWeeklyHours(int $hours): array
	{
		$hours = max(0, min(20, $hours));
		if ($hours === 0) {
			return [];
		}
		if ($hours === 1) {
			return [1];
		}
		// Never put both periods of a 2h/week course on the same day.
		if ($hours === 2) {
			return [1, 1];
		}
		// 3+ hours: prefer adjacent doubles (2+1, 2+2, 2+2+1, …).
		$blocks = [];
		$remaining = $hours;
		while ($remaining >= 2) {
			$blocks[] = 2;
			$remaining -= 2;
		}
		if ($remaining === 1) {
			$blocks[] = 1;
		}

		return $blocks;
	}

	public static function weeklyHoursFromCourse(array $course): int
	{
		$explicit = (int) ($course['weekly_hours'] ?? 0);
		if ($explicit > 0 && $explicit <= 20) {
			return $explicit;
		}

		// Credit = weekly periods on the timetable (e.g. credit 3 → 3 lessons per week).
		$credit = (float) ($course['credit'] ?? 0);
		if ($credit >= 1 && $credit <= 20) {
			return (int) round($credit);
		}

		return self::defaultWeeklyHoursByTitle((string) ($course['course_title'] ?? ''));
	}

	public static function defaultWeeklyHoursByTitle(string $title): int
	{
		$t = strtolower(trim(preg_replace('/\s+/', ' ', $title)));
		if ($t === '') {
			return 3;
		}

		$rules = [
			'english' => 5,
			'mathematics' => 5,
			'math' => 5,
			'science' => 4,
			'set' => 4,
			'kinyarwanda' => 4,
			'social' => 3,
			'religious' => 3,
			'french' => 3,
			'physical education' => 2,
			'physical' => 2,
			'art' => 2,
			'craft' => 2,
			'behaviour' => 1,
			'behavior' => 1,
			'library' => 2,
			'pastoral' => 2,
			'life skills' => 2,
			'co-curricular' => 2,
		];

		foreach ($rules as $needle => $hours) {
			if (strpos($t, $needle) !== false) {
				return $hours;
			}
		}

		return 3;
	}

	/**
	 * @param list<array<string,mixed>> $assignments
	 * @param list<array<string,mixed>> $teachingSlots
	 * @param list<int> $days
	 * @param array<string,bool> $blocked
	 * @return array{entries:list<array<string,mixed>>,warnings:list<string>}
	 */
	public function generate(array $assignments, array $teachingSlots, array $days = [0, 1, 2, 3, 4], array $blocked = [], bool $resetState = true): array
	{
		if ($resetState) {
			$this->classBusy = [];
			$this->staffBusy = [];
			$this->staffTimeBookings = [];
			$this->subjectDayCount = [];
			$this->classDayUsage = [];
			$this->globalDayUsage = [];
			$this->warnings = [];
		}

		$this->teachingSlots = $teachingSlots;
		$this->days = $days;
		$this->blocked = $blocked;
		$this->slotTimes = [];
		foreach ($teachingSlots as $slot) {
			$id = (int) ($slot['id'] ?? 0);
			if ($id > 0) {
				$this->slotTimes[$id] = [
					'start' => (string) ($slot['start_time'] ?? '00:00:00'),
					'end' => (string) ($slot['end_time'] ?? '00:00:00'),
				];
			}
		}

		$entries = [];
		$lessonNeeds = [];

		foreach ($assignments as $row) {
			$hours = self::weeklyHoursFromCourse($row);
			$blocks = $this->lessonBlocksForCourse($row, $hours);
			foreach ($blocks as $blockSize) {
				$lessonNeeds[] = [
					'assignment' => $row,
					'block_size' => (int) $blockSize,
					'hours' => $hours,
				];
			}
		}

		while ($lessonNeeds !== []) {
			foreach ($lessonNeeds as $i => $need) {
				$lessonNeeds[$i]['candidate_count'] = $this->countPlacementCandidates(
					$need['assignment'],
					(int) $need['block_size'],
					(int) $need['hours']
				);
			}
			usort($lessonNeeds, static function ($a, $b) {
				$ca = (int) ($a['candidate_count'] ?? PHP_INT_MAX);
				$cb = (int) ($b['candidate_count'] ?? PHP_INT_MAX);
				if ($ca !== $cb) {
					return $ca <=> $cb;
				}
				$bs = (int) $b['block_size'] <=> (int) $a['block_size'];
				if ($bs !== 0) {
					return $bs;
				}
				$ha = (int) $a['hours'];
				$hb = (int) $b['hours'];
				if ($ha <= 2 && $hb > 2) {
					return -1;
				}
				if ($hb <= 2 && $ha > 2) {
					return 1;
				}
				return $hb <=> $ha;
			});

			$need = array_shift($lessonNeeds);
			$placed = $this->placeLesson($need['assignment'], (int) $need['block_size'], (int) $need['hours']);
			if ($placed) {
				foreach ($placed as $entry) {
					$entries[] = $entry;
				}
			} else {
				$this->warnings[] = 'Could not place ' . ($need['assignment']['course_title'] ?? 'course')
					. ' (' . ($need['assignment']['class_title'] ?? '') . ') — ' . $need['block_size'] . ' period(s)';
			}
		}

		return ['entries' => $entries, 'warnings' => $this->warnings];
	}

	/** @return list<string> */
	public function warnings(): array
	{
		return $this->warnings;
	}

	/** @return list<array<string,mixed>>|null */
	private function placeLesson(array $row, int $blockSize, int $weeklyHours = 0): ?array
	{
		$classId = (int) ($row['class_id'] ?? 0);
		$staffId = (int) ($row['lecturer'] ?? 0);
		$courseId = (int) ($row['course_id'] ?? 0);
		$subjectKey = $classId . ':' . $courseId;
		$maxPerDay = $this->maxPerDayForCourse($row, $weeklyHours);
		$occupiedDays = $this->subjectOccupiedDays($subjectKey);
		$enforceGap = $this->requiresNonAdjacentDays($row, $weeklyHours) && $occupiedDays !== [];

		$candidates = $this->collectPlacementCandidates(
			$row,
			$blockSize,
			$weeklyHours,
			$maxPerDay,
			$enforceGap
		);
		// Soft fallback if non-adjacent days are fully blocked.
		if ($candidates === [] && $enforceGap) {
			$candidates = $this->collectPlacementCandidates(
				$row,
				$blockSize,
				$weeklyHours,
				$maxPerDay,
				false
			);
		}

		if ($candidates === []) {
			return null;
		}

		usort($candidates, static function ($a, $b) {
			return $a['score'] <=> $b['score'];
		});

		$pick = $candidates[0];
		$out = [];
		foreach ($pick['slot_ids'] as $slotId) {
			$this->markBusy($classId, $staffId, $pick['day'], $slotId);
			$out[] = [
				'class_id' => $classId,
				'staff_id' => $staffId,
				'course_id' => $courseId,
				'course_record_id' => (int) ($row['course_record_id'] ?? 0),
				'day_of_week' => $pick['day'],
				'slot_id' => $slotId,
				'entry_type' => 'lesson',
			];
		}
		$this->subjectDayCount[$subjectKey . ':' . $pick['day']] =
			(int) ($this->subjectDayCount[$subjectKey . ':' . $pick['day']] ?? 0) + count($pick['slot_ids']);

		return $out;
	}

	/**
	 * @return list<array{score:int,day:int,slot_ids:list<int>}>
	 */
	private function collectPlacementCandidates(
		array $row,
		int $blockSize,
		int $weeklyHours,
		int $maxPerDay,
		bool $enforceNonAdjacentDays
	): array {
		$classId = (int) ($row['class_id'] ?? 0);
		$staffId = (int) ($row['lecturer'] ?? 0);
		$courseId = (int) ($row['course_id'] ?? 0);
		$subjectKey = $classId . ':' . $courseId;
		$occupiedDays = $this->subjectOccupiedDays($subjectKey);
		$candidates = [];

		$orderedDays = $this->days;
		usort($orderedDays, function ($a, $b) use ($classId) {
			$ua = (int) ($this->classDayUsage[$classId . ':' . $a] ?? 0);
			$ub = (int) ($this->classDayUsage[$classId . ':' . $b] ?? 0);
			if ($ua !== $ub) {
				return $ua <=> $ub;
			}
			return (int) ($this->globalDayUsage[$a] ?? 0) <=> (int) ($this->globalDayUsage[$b] ?? 0);
		});

		foreach ($orderedDays as $day) {
			$already = (int) ($this->subjectDayCount[$subjectKey . ':' . $day] ?? 0);
			if ($already + $blockSize > $maxPerDay) {
				continue;
			}
			if ($enforceNonAdjacentDays && $this->dayTouchesOccupiedDays($day, $occupiedDays)) {
				continue;
			}
			for ($i = 0; $i < count($this->teachingSlots); $i++) {
				if ($blockSize === 2) {
					// Prefer consecutive teaching slots; also allow any clock-adjacent pair
					// (e.g. across a short tea break) so doubles still form.
					for ($j = $i + 1; $j < count($this->teachingSlots); $j++) {
						$slotA = (int) $this->teachingSlots[$i]['id'];
						$slotB = (int) $this->teachingSlots[$j]['id'];
						if (!$this->slotsTemporallyAdjacent($slotA, $slotB)) {
							continue;
						}
						$slotIds = [$slotA, $slotB];
						if (!$this->slotsFree($classId, $staffId, $day, $slotIds)) {
							continue;
						}
						$score = $this->scorePlacement($classId, $staffId, $courseId, $day, $i, $weeklyHours, $row);
						// Slightly prefer true back-to-back over short-break doubles.
						if ($j !== $i + 1) {
							$score += 15;
						}
						$candidates[] = ['score' => $score, 'day' => $day, 'slot_ids' => $slotIds];
					}
					continue;
				}

				$slotIds = [(int) $this->teachingSlots[$i]['id']];
				if (!$this->slotsFree($classId, $staffId, $day, $slotIds)) {
					continue;
				}

				$score = $this->scorePlacement($classId, $staffId, $courseId, $day, $i, $weeklyHours, $row);
				$candidates[] = ['score' => $score, 'day' => $day, 'slot_ids' => $slotIds];
			}
		}

		return $candidates;
	}

	private function scorePlacement(
		int $classId,
		int $staffId,
		int $courseId,
		int $day,
		int $slotIndex,
		int $weeklyHours = 0,
		array $row = []
	): int {
		$score = (int) ($this->classDayUsage[$classId . ':' . $day] ?? 0) * 80;
		$score += (int) ($this->globalDayUsage[$day] ?? 0) * 15;
		$score += $slotIndex;

		$subjectKey = $classId . ':' . $courseId;
		$sameDayPenalty = ($weeklyHours > 0 && $weeklyHours <= 2) ? 200 : 40;
		$occupied = $this->subjectOccupiedDays($subjectKey);
		$gapCourse = $this->requiresNonAdjacentDays($row, $weeklyHours);

		// Anchor multi-hour secondary courses on Mon/Wed/Fri so later blocks can gap.
		if ($gapCourse && $occupied === []) {
			$score += in_array($day, [0, 2, 4], true) ? -70 : 55;
		}

		foreach ($occupied as $d) {
			if ($d === $day) {
				$score += $sameDayPenalty;
				continue;
			}
			$dist = abs($day - (int) $d);
			$score -= min(30, $dist * 10);
			if ($gapCourse && $dist === 1) {
				// Strongly prefer gap days (Mon↔Wed) over neighbour days (Mon↔Tue).
				$score += 5000;
			}
		}

		return $score;
	}

	private function countPlacementCandidates(array $row, int $blockSize, int $weeklyHours = 0): int
	{
		$maxPerDay = $this->maxPerDayForCourse($row, $weeklyHours);
		$subjectKey = (int) ($row['class_id'] ?? 0) . ':' . (int) ($row['course_id'] ?? 0);
		$occupied = $this->subjectOccupiedDays($subjectKey);
		$enforce = $this->requiresNonAdjacentDays($row, $weeklyHours) && $occupied !== [];
		$count = count($this->collectPlacementCandidates($row, $blockSize, $weeklyHours, $maxPerDay, $enforce));
		if ($count === 0 && $enforce) {
			$count = count($this->collectPlacementCandidates($row, $blockSize, $weeklyHours, $maxPerDay, false));
		}
		return $count;
	}

	/** @param list<int> $slotIds */
	private function slotsFree(int $classId, int $staffId, int $day, array $slotIds): bool
	{
		foreach ($slotIds as $slotId) {
			if (!empty($this->blocked[$day . ':' . $slotId])) {
				return false;
			}
			if (isset($this->classBusy[$this->busyKey($classId, $day, $slotId)])) {
				return false;
			}
			if ($staffId > 0 && $this->staffHasTimeConflict($staffId, $day, $slotId)) {
				return false;
			}
		}
		return true;
	}

	private function staffHasTimeConflict(int $staffId, int $day, int $slotId): bool
	{
		if (isset($this->staffBusy[$this->busyStaffKey($staffId, $day, $slotId)])) {
			return true;
		}
		$range = $this->slotTimeRange($slotId);
		if ($range === null) {
			return false;
		}
		foreach ($this->staffTimeBookings[$staffId][$day] ?? [] as $booked) {
			if ($this->rangesOverlap($range['start'], $range['end'], $booked['start'], $booked['end'])) {
				return true;
			}
		}
		return false;
	}

	private function markBusy(int $classId, int $staffId, int $day, int $slotId): void
	{
		$this->classBusy[$this->busyKey($classId, $day, $slotId)] = true;
		$this->classDayUsage[$classId . ':' . $day] = (int) ($this->classDayUsage[$classId . ':' . $day] ?? 0) + 1;
		$this->globalDayUsage[$day] = (int) ($this->globalDayUsage[$day] ?? 0) + 1;
		if ($staffId > 0) {
			$this->staffBusy[$this->busyStaffKey($staffId, $day, $slotId)] = true;
			$range = $this->slotTimeRange($slotId);
			if ($range !== null) {
				$this->staffTimeBookings[$staffId][$day][] = $range;
			}
		}
	}

	/** @return array{start:int,end:int}|null */
	private function slotTimeRange(int $slotId): ?array
	{
		$times = $this->slotTimes[$slotId] ?? null;
		if ($times === null) {
			return null;
		}
		return [
			'start' => $this->timeToMinutes($times['start']),
			'end' => $this->timeToMinutes($times['end']),
		];
	}

	private function timeToMinutes(string $time): int
	{
		$parts = explode(':', substr($time, 0, 8));
		return ((int) ($parts[0] ?? 0)) * 60 + (int) ($parts[1] ?? 0);
	}

	private function rangesOverlap(int $aStart, int $aEnd, int $bStart, int $bEnd): bool
	{
		return $aStart < $bEnd && $bStart < $aEnd;
	}

	/**
	 * True when two teaching slots form a usable double.
	 * Allows short changeover/tea break (<=25 min) but not lunch (>=45 min).
	 */
	private function slotsTemporallyAdjacent(int $slotA, int $slotB): bool
	{
		$a = $this->slotTimeRange($slotA);
		$b = $this->slotTimeRange($slotB);
		if ($a === null || $b === null) {
			return true;
		}
		$gap = min(abs($a['end'] - $b['start']), abs($b['end'] - $a['start']));
		return $gap <= 25;
	}

	private function busyKey(int $classId, int $day, int $slotId): string
	{
		return 'c' . $classId . 'd' . $day . 's' . $slotId;
	}

	private function busyStaffKey(int $staffId, int $day, int $slotId): string
	{
		return 't' . $staffId . 'd' . $day . 's' . $slotId;
	}

	/** @return list<int> */
	private function lessonBlocksForCourse(array $row, int $hours): array
	{
		if ($hours <= 0) {
			return [];
		}
		if ($this->requiresSpreadAcrossDays($row)) {
			return array_fill(0, $hours, 1);
		}
		return self::distributeWeeklyHours($hours);
	}

	private function maxPerDayForCourse(array $row, int $weeklyHours): int
	{
		if ($this->requiresSpreadAcrossDays($row)) {
			return 1;
		}
		return ($weeklyHours > 0 && $weeklyHours <= 2) ? 1 : 2;
	}

	private function requiresSpreadAcrossDays(array $row): bool
	{
		if (!$this->isPrimaryOrNursery($row)) {
			return false;
		}
		return !$this->isMathematicsCourse((string) ($row['course_title'] ?? ''));
	}

	/** Secondary classes with 3+ weekly periods: doubles + non-adjacent days. */
	private function requiresNonAdjacentDays(array $row, int $weeklyHours): bool
	{
		return !$this->isPrimaryOrNursery($row) && $weeklyHours >= 3;
	}

	/** @return list<int> */
	private function subjectOccupiedDays(string $subjectKey): array
	{
		$out = [];
		foreach ($this->days as $day) {
			if ((int) ($this->subjectDayCount[$subjectKey . ':' . $day] ?? 0) > 0) {
				$out[] = (int) $day;
			}
		}
		return $out;
	}

	/** @param list<int> $occupied */
	private function dayTouchesOccupiedDays(int $day, array $occupied): bool
	{
		foreach ($occupied as $od) {
			if ((int) $od === $day || $this->isCalendarAdjacentDay($day, (int) $od)) {
				return true;
			}
		}
		return false;
	}

	private function isCalendarAdjacentDay(int $a, int $b): bool
	{
		return abs($a - $b) === 1;
	}

	private function isPrimaryOrNursery(array $row): bool
	{
		$track = strtolower(trim((string) ($row['_track_key'] ?? $row['track_key'] ?? '')));
		return in_array($track, ['primary', 'nursery'], true);
	}

	private function isMathematicsCourse(string $title): bool
	{
		$title = strtolower(trim(preg_replace('/\s+/', ' ', $title)));
		return $title !== '' && (strpos($title, 'mathematics') !== false || preg_match('/\bmath\b/', $title) === 1);
	}
}
