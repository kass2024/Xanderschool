<?php

namespace App\Services\Timetable;

/**
 * Parking-lot staging: one DB row per weekly period still not on the grid.
 */
class TimetableStagingService
{
	/** @var array<string,array<string,mixed>> */
	private $assignmentMeta = [];

	/** @return string */
	private function assignmentKey(int $courseRecordId, int $classId, int $courseId, int $staffId): string
	{
		if ($courseRecordId > 0) {
			return 'cr:' . $courseRecordId;
		}

		return 'c:' . $classId . ':' . $courseId . ':' . $staffId;
	}

	/**
	 * @param array<string,mixed> $assignment
	 */
	private function keyFromAssignment(array $assignment): string
	{
		return $this->assignmentKey(
			(int) ($assignment['course_record_id'] ?? 0),
			(int) ($assignment['class_id'] ?? 0),
			(int) ($assignment['course_id'] ?? 0),
			(int) ($assignment['lecturer'] ?? 0)
		);
	}

	/**
	 * @param array<string,mixed> $entry
	 */
	private function keyFromEntry(array $entry): string
	{
		return $this->assignmentKey(
			(int) ($entry['course_record_id'] ?? 0),
			(int) ($entry['class_id'] ?? 0),
			(int) ($entry['course_id'] ?? 0),
			(int) ($entry['staff_id'] ?? 0)
		);
	}

	/**
	 * Create staging rows (day=-1, slot=0) for assignment periods not yet scheduled.
	 *
	 * @param list<array<string,mixed>> $assignments
	 */
	public function reconcile(
		int $scheduleId,
		int $schoolId,
		array $assignments,
		int $filterClassId = 0,
		int $filterStaffId = 0
	): int {
		if ($scheduleId <= 0 || $schoolId <= 0 || $assignments === []) {
			return 0;
		}

		$db = \Config\Database::connect();
		$filtered = array_values(array_filter($assignments, static function (array $row) use ($filterClassId, $filterStaffId): bool {
			if ($filterClassId > 0 && (int) ($row['class_id'] ?? 0) !== $filterClassId) {
				return false;
			}
			if ($filterStaffId > 0 && (int) ($row['lecturer'] ?? 0) !== $filterStaffId) {
				return false;
			}

			return true;
		}));

		if ($filtered === []) {
			return 0;
		}

		$entries = $db->table('timetable_entries')
			->where('schedule_id', $scheduleId)
			->where('school_id', $schoolId)
			->where('entry_type', 'lesson')
			->get()->getResultArray();

		$scheduled = [];
		$staged = [];
		foreach ($entries as $entry) {
			$key = $this->keyFromEntry($entry);
			$day = (int) ($entry['day_of_week'] ?? 0);
			$slotId = (int) ($entry['slot_id'] ?? 0);
			if ($day >= 0 && $slotId > 0) {
				$scheduled[$key] = ($scheduled[$key] ?? 0) + 1;
			} elseif ($day === -1 && $slotId === 0) {
				$staged[$key] = ($staged[$key] ?? 0) + 1;
			}
		}

		$created = 0;
		foreach ($filtered as $assignment) {
			$key = $this->keyFromAssignment($assignment);
			$needed = TimetableGeneratorService::weeklyHoursFromCourse($assignment);
			$have = (int) ($scheduled[$key] ?? 0) + (int) ($staged[$key] ?? 0);
			$deficit = $needed - $have;
			if ($deficit <= 0) {
				continue;
			}

			for ($i = 0; $i < $deficit; $i++) {
				$db->table('timetable_entries')->insert([
					'schedule_id' => $scheduleId,
					'school_id' => $schoolId,
					'class_id' => (int) ($assignment['class_id'] ?? 0),
					'staff_id' => (int) ($assignment['lecturer'] ?? 0),
					'course_id' => (int) ($assignment['course_id'] ?? 0),
					'course_record_id' => (int) ($assignment['course_record_id'] ?? 0) ?: null,
					'day_of_week' => -1,
					'slot_id' => 0,
					'entry_type' => 'lesson',
				]);
				$created++;
			}
		}

		return $created;
	}

	/**
	 * @param list<array<string,mixed>> $assignments
	 * @return array{scheduled:int,staging:int,remaining:int}
	 */
	public function counts(int $scheduleId, array $assignments, int $filterClassId = 0, int $filterStaffId = 0): array
	{
		$db = \Config\Database::connect();
		$needed = 0;
		foreach ($assignments as $assignment) {
			if ($filterClassId > 0 && (int) ($assignment['class_id'] ?? 0) !== $filterClassId) {
				continue;
			}
			if ($filterStaffId > 0 && (int) ($assignment['lecturer'] ?? 0) !== $filterStaffId) {
				continue;
			}
			$needed += TimetableGeneratorService::weeklyHoursFromCourse($assignment);
		}

		$scheduled = (int) $db->table('timetable_entries')
			->where('schedule_id', $scheduleId)
			->where('entry_type', 'lesson')
			->where('day_of_week >=', 0)
			->where('slot_id >', 0)
			->countAllResults();

		if ($filterClassId > 0) {
			$scheduled = (int) $db->table('timetable_entries')
				->where('schedule_id', $scheduleId)
				->where('class_id', $filterClassId)
				->where('entry_type', 'lesson')
				->where('day_of_week >=', 0)
				->where('slot_id >', 0)
				->countAllResults();
		} elseif ($filterStaffId > 0) {
			$scheduled = (int) $db->table('timetable_entries')
				->where('schedule_id', $scheduleId)
				->where('staff_id', $filterStaffId)
				->where('entry_type', 'lesson')
				->where('day_of_week >=', 0)
				->where('slot_id >', 0)
				->countAllResults();
		}

		$staging = (int) $db->table('timetable_entries')
			->where('schedule_id', $scheduleId)
			->where('entry_type', 'lesson')
			->where('day_of_week', -1)
			->where('slot_id', 0)
			->countAllResults();

		return [
			'scheduled' => $scheduled,
			'staging' => $staging,
			'remaining' => max(0, $needed - $scheduled),
		];
	}

	/**
	 * Move parking-lot lessons onto free grid cells (greedy placement).
	 */
	public function autoPlaceStaging(
		int $scheduleId,
		int $schoolId,
		\App\Models\TimetableSchemaModel $schema,
		int $filterClassId = 0,
		int $filterStaffId = 0
	): int {
		if ($scheduleId <= 0 || $schoolId <= 0) {
			return 0;
		}

		$db = \Config\Database::connect();
		$settings = $db->table('timetable_settings')->where('school_id', $schoolId)->get(1)->getRowArray();
		$days = \App\Models\TimetableSchemaModel::weekDaysFromSettings($settings);
		$this->assignmentMeta = $this->loadAssignmentMeta($scheduleId, $schoolId);

		$builder = $db->table('timetable_entries')
			->where('schedule_id', $scheduleId)
			->where('school_id', $schoolId)
			->where('entry_type', 'lesson')
			->where('day_of_week', -1)
			->where('slot_id', 0);
		if ($filterClassId > 0) {
			$builder->where('class_id', $filterClassId);
		}
		if ($filterStaffId > 0) {
			$builder->where('staff_id', $filterStaffId);
		}
		$parking = $builder->orderBy('id')->get()->getResultArray();
		if ($parking === []) {
			return 0;
		}

		$scheduled = $db->table('timetable_entries')
			->where('schedule_id', $scheduleId)
			->where('entry_type', 'lesson')
			->where('day_of_week >=', 0)
			->where('slot_id >', 0)
			->get()->getResultArray();

		$state = $this->buildScheduleState($scheduled);
		usort($parking, function (array $a, array $b) use ($days, $schema, $schoolId, $state): int {
			return $this->countDirectCandidates($a, $days, $schema, $schoolId, $state)
				<=> $this->countDirectCandidates($b, $days, $schema, $schoolId, $state);
		});

		$placed = 0;
		foreach ($parking as $entry) {
			$found = $this->findBestDirectPlacement($entry, $days, $schema, $schoolId, $state);
			if ($found === null) {
				$found = $this->placeByRelocatingOneBlocker($db, $entry, $days, $schema, $schoolId, $state);
			}
			if ($found === null) {
				continue;
			}

			$db->table('timetable_entries')->where('id', (int) $entry['id'])->update([
				'day_of_week' => $found['day'],
				'slot_id' => $found['slot_id'],
			]);
			$entry['day_of_week'] = $found['day'];
			$entry['slot_id'] = $found['slot_id'];
			$this->addScheduledEntry($state, $entry);
			$placed++;
		}

		return $placed;
	}

	/**
	 * Remove any class-slot or teacher-slot collisions, then try to place the extra rows legally.
	 *
	 * @return array{moved_to_parking:int,replaced:int,remaining_conflicts:int}
	 */
	public function normalizeScheduleConflicts(
		int $scheduleId,
		int $schoolId,
		\App\Models\TimetableSchemaModel $schema,
		int $filterClassId = 0,
		int $filterStaffId = 0,
		int $maxPasses = 4
	): array {
		$db = \Config\Database::connect();
		$totalMoved = 0;
		$totalReplaced = 0;
		$remaining = 0;

		for ($pass = 0; $pass < $maxPasses; $pass++) {
			$scheduled = $this->scheduledEntries($scheduleId, $schoolId, $filterClassId, $filterStaffId);
			$idsToParking = $this->collectConflictEntryIds($scheduled);
			$remaining = count($idsToParking);
			if ($idsToParking === []) {
				$remaining = 0;
				break;
			}

			$db->table('timetable_entries')
				->whereIn('id', $idsToParking)
				->update([
					'day_of_week' => -1,
					'slot_id' => 0,
				]);
			$totalMoved += count($idsToParking);
			$totalReplaced += $this->autoPlaceStaging($scheduleId, $schoolId, $schema, $filterClassId, $filterStaffId);
		}

		$remaining = count($this->collectConflictEntryIds(
			$this->scheduledEntries($scheduleId, $schoolId, $filterClassId, $filterStaffId)
		));

		return [
			'moved_to_parking' => $totalMoved,
			'replaced' => $totalReplaced,
			'remaining_conflicts' => $remaining,
		];
	}

	/** @return array<string,array<string,mixed>> */
	private function loadAssignmentMeta(int $scheduleId, int $schoolId): array
	{
		$db = \Config\Database::connect();
		$schedule = $db->table('timetable_schedules')->where('id', $scheduleId)->get(1)->getRowArray();
		$year = (int) ($schedule['academic_year'] ?? 0);
		$term = (int) ($schedule['term'] ?? 0);
		if ($year <= 0 || $term <= 0) {
			return [];
		}

		$rows = $db->table('course_records cr')
			->select('cr.id AS course_record_id, cr.class AS class_id, cr.course AS course_id, cr.lecturer, c.credit, c.title AS course_title')
			->join('classes cl', 'cl.id = cr.class')
			->join('courses c', 'c.id = cr.course')
			->where('cl.school_id', $schoolId)
			->where('cr.year', $year)
			->where("find_in_set($term, cr.term) >", 0, false)
			->get()->getResultArray();

		$out = [];
		foreach ($rows as $row) {
			$out[$this->assignmentKey(
				(int) ($row['course_record_id'] ?? 0),
				(int) ($row['class_id'] ?? 0),
				(int) ($row['course_id'] ?? 0),
				(int) ($row['lecturer'] ?? 0)
			)] = $row;
		}

		return $out;
	}

	/**
	 * @param list<array<string,mixed>> $scheduled
	 * @return array<string,mixed>
	 */
	private function buildScheduleState(array $scheduled): array
	{
		$state = [
			'by_id' => [],
			'class_busy' => [],
			'staff_busy' => [],
			'subject_day_count' => [],
			'class_day_usage' => [],
		];
		foreach ($scheduled as $entry) {
			$this->addScheduledEntry($state, $entry);
		}
		return $state;
	}

	/** @param array<string,mixed> $state */
	private function addScheduledEntry(array &$state, array $entry): void
	{
		$entryId = (int) ($entry['id'] ?? 0);
		$day = (int) ($entry['day_of_week'] ?? -1);
		$slotId = (int) ($entry['slot_id'] ?? 0);
		if ($entryId <= 0 || $day < 0 || $slotId <= 0) {
			return;
		}
		$classId = (int) ($entry['class_id'] ?? 0);
		$staffId = (int) ($entry['staff_id'] ?? 0);
		$key = $day . ':' . $slotId;
		$state['by_id'][$entryId] = $entry;
		$state['class_busy'][$classId . ':' . $key] = $entryId;
		if ($staffId > 0) {
			$state['staff_busy'][$staffId . ':' . $key] = $entryId;
		}
		$subjectKey = $classId . ':' . (int) ($entry['course_id'] ?? 0) . ':' . $day;
		$state['subject_day_count'][$subjectKey] = (int) ($state['subject_day_count'][$subjectKey] ?? 0) + 1;
		$state['class_day_usage'][$classId . ':' . $day] = (int) ($state['class_day_usage'][$classId . ':' . $day] ?? 0) + 1;
	}

	/** @param array<string,mixed> $state */
	private function removeScheduledEntry(array &$state, array $entry): void
	{
		$entryId = (int) ($entry['id'] ?? 0);
		$day = (int) ($entry['day_of_week'] ?? -1);
		$slotId = (int) ($entry['slot_id'] ?? 0);
		if ($entryId <= 0 || $day < 0 || $slotId <= 0) {
			return;
		}
		$classId = (int) ($entry['class_id'] ?? 0);
		$staffId = (int) ($entry['staff_id'] ?? 0);
		$key = $day . ':' . $slotId;
		unset($state['by_id'][$entryId], $state['class_busy'][$classId . ':' . $key]);
		if ($staffId > 0) {
			unset($state['staff_busy'][$staffId . ':' . $key]);
		}
		$subjectKey = $classId . ':' . (int) ($entry['course_id'] ?? 0) . ':' . $day;
		$state['subject_day_count'][$subjectKey] = max(0, (int) ($state['subject_day_count'][$subjectKey] ?? 0) - 1);
		$state['class_day_usage'][$classId . ':' . $day] = max(0, (int) ($state['class_day_usage'][$classId . ':' . $day] ?? 0) - 1);
	}

	/** @param array<string,mixed> $state */
	private function countDirectCandidates(array $entry, array $days, \App\Models\TimetableSchemaModel $schema, int $schoolId, array $state): int
	{
		$count = 0;
		foreach ($this->candidateSlots($entry, $days, $schema, $schoolId, $state, false) as $_candidate) {
			$count++;
		}
		return $count;
	}

	/**
	 * @param array<string,mixed> $state
	 * @return array{day:int,slot_id:int}|null
	 */
	private function findBestDirectPlacement(array $entry, array $days, \App\Models\TimetableSchemaModel $schema, int $schoolId, array $state): ?array
	{
		$candidates = $this->candidateSlots($entry, $days, $schema, $schoolId, $state, false);
		return $candidates[0] ?? null;
	}

	/**
	 * @param array<string,mixed> $state
	 * @return array{day:int,slot_id:int}|null
	 */
	private function placeByRelocatingOneBlocker(
		\CodeIgniter\Database\BaseConnection $db,
		array $entry,
		array $days,
		\App\Models\TimetableSchemaModel $schema,
		int $schoolId,
		array &$state
	): ?array {
		$candidates = $this->candidateSlots($entry, $days, $schema, $schoolId, $state, true);
		foreach ($candidates as $candidate) {
			$blockers = $candidate['blockers'] ?? [];
			if (count($blockers) !== 1) {
				continue;
			}
			$blockerId = (int) $blockers[0];
			$blocker = $state['by_id'][$blockerId] ?? null;
			if (!is_array($blocker)) {
				continue;
			}
			$this->removeScheduledEntry($state, $blocker);
			$relocation = $this->findBestDirectPlacement($blocker, $days, $schema, $schoolId, $state);
			if ($relocation === null) {
				$this->addScheduledEntry($state, $blocker);
				continue;
			}
			$db->table('timetable_entries')->where('id', $blockerId)->update([
				'day_of_week' => $relocation['day'],
				'slot_id' => $relocation['slot_id'],
			]);
			$blocker['day_of_week'] = $relocation['day'];
			$blocker['slot_id'] = $relocation['slot_id'];
			$this->addScheduledEntry($state, $blocker);
			return ['day' => (int) $candidate['day'], 'slot_id' => (int) $candidate['slot_id']];
		}
		return null;
	}

	/**
	 * @param array<string,mixed> $state
	 * @return list<array{day:int,slot_id:int,score:int,blockers?:list<int>}>
	 */
	private function candidateSlots(
		array $entry,
		array $days,
		\App\Models\TimetableSchemaModel $schema,
		int $schoolId,
		array $state,
		bool $allowSingleBlocker
	): array {
		$classId = (int) ($entry['class_id'] ?? 0);
		$staffId = (int) ($entry['staff_id'] ?? 0);
		$trackKey = $schema->trackForClass($schoolId, $classId);
		$slots = array_values(array_filter(
			$schema->teachingSlots($schoolId, $trackKey),
			static fn ($s) => empty($s['is_break'])
		));
		$blocked = $schema->specialTimesMap($schoolId, $trackKey);
		$candidates = [];

		usort($days, function ($a, $b) use ($state, $classId, $entry): int {
			$subA = $this->subjectDayCountForState($state, $entry, (int) $a);
			$subB = $this->subjectDayCountForState($state, $entry, (int) $b);
			if ($subA !== $subB) {
				return $subA <=> $subB;
			}
			return (int) ($state['class_day_usage'][$classId . ':' . $a] ?? 0)
				<=> (int) ($state['class_day_usage'][$classId . ':' . $b] ?? 0);
		});

		foreach ($days as $day) {
			if ($this->wouldExceedSubjectDayLimit($state, $entry, (int) $day)) {
				continue;
			}
			foreach ($slots as $slot) {
				$slotId = (int) ($slot['id'] ?? 0);
				if ($slotId <= 0) {
					continue;
				}
				$key = $day . ':' . $slotId;
				if (!empty($blocked[$key])) {
					continue;
				}
				$classBlocker = (int) ($state['class_busy'][$classId . ':' . $key] ?? 0);
				$staffBlocker = ($staffId > 0) ? (int) ($state['staff_busy'][$staffId . ':' . $key] ?? 0) : 0;
				$blockers = array_values(array_unique(array_filter([$classBlocker, $staffBlocker])));
				if ($blockers !== [] && (!$allowSingleBlocker || count($blockers) > 1)) {
					continue;
				}
				$candidates[] = [
					'day' => (int) $day,
					'slot_id' => $slotId,
					'score' => $this->scoreCandidate($state, $entry, (int) $day, $slotId, count($blockers)),
					'blockers' => $blockers,
				];
			}
		}

		usort($candidates, static function (array $a, array $b): int {
			return $a['score'] <=> $b['score'];
		});
		return $candidates;
	}

	/** @param array<string,mixed> $state */
	private function scoreCandidate(array $state, array $entry, int $day, int $slotId, int $blockerCount): int
	{
		$classId = (int) ($entry['class_id'] ?? 0);
		$score = $blockerCount * 1000;
		$score += $this->subjectDayCountForState($state, $entry, $day) * 300;
		$score += (int) ($state['class_day_usage'][$classId . ':' . $day] ?? 0) * 80;
		$score += $slotId;
		return $score;
	}

	/** @param array<string,mixed> $state */
	private function subjectDayCountForState(array $state, array $entry, int $day): int
	{
		$classId = (int) ($entry['class_id'] ?? 0);
		$courseId = (int) ($entry['course_id'] ?? 0);
		return (int) ($state['subject_day_count'][$classId . ':' . $courseId . ':' . $day] ?? 0);
	}

	/** @param array<string,mixed> $state */
	private function wouldExceedSubjectDayLimit(array $state, array $entry, int $day): bool
	{
		$hours = TimetableGeneratorService::weeklyHoursFromCourse($this->metaForEntry($entry));
		$maxPerDay = ($hours > 0 && $hours <= 2) ? 1 : 2;
		return $this->subjectDayCountForState($state, $entry, $day) + 1 > $maxPerDay;
	}

	/** @return array<string,mixed> */
	private function metaForEntry(array $entry): array
	{
		$key = $this->keyFromEntry($entry);
		return $this->assignmentMeta[$key] ?? [
			'course_title' => '',
			'credit' => 0,
		];
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	private function scheduledEntries(int $scheduleId, int $schoolId, int $filterClassId = 0, int $filterStaffId = 0): array
	{
		$builder = \Config\Database::connect()->table('timetable_entries')
			->where('schedule_id', $scheduleId)
			->where('school_id', $schoolId)
			->where('entry_type', 'lesson')
			->where('day_of_week >=', 0)
			->where('slot_id >', 0);
		if ($filterClassId > 0) {
			$builder->where('class_id', $filterClassId);
		}
		if ($filterStaffId > 0) {
			$builder->where('staff_id', $filterStaffId);
		}
		return $builder->orderBy('id', 'ASC')->get()->getResultArray();
	}

	/**
	 * @param list<array<string,mixed>> $scheduled
	 * @return list<int>
	 */
	private function collectConflictEntryIds(array $scheduled): array
	{
		$byClassSlot = [];
		$byTeacherSlot = [];
		foreach ($scheduled as $entry) {
			$entryId = (int) ($entry['id'] ?? 0);
			$classId = (int) ($entry['class_id'] ?? 0);
			$staffId = (int) ($entry['staff_id'] ?? 0);
			$day = (int) ($entry['day_of_week'] ?? -1);
			$slotId = (int) ($entry['slot_id'] ?? 0);
			if ($entryId <= 0 || $classId <= 0 || $day < 0 || $slotId <= 0) {
				continue;
			}
			$byClassSlot[$classId . ':' . $day . ':' . $slotId][] = $entry;
			if ($staffId > 0) {
				$byTeacherSlot[$staffId . ':' . $day . ':' . $slotId][] = $entry;
			}
		}

		$ids = [];
		foreach ($byClassSlot as $group) {
			if (count($group) <= 1) {
				continue;
			}
			$drop = array_slice($group, 1);
			foreach ($drop as $entry) {
				$ids[(int) $entry['id']] = (int) $entry['id'];
			}
		}
		foreach ($byTeacherSlot as $group) {
			if (count($group) <= 1) {
				continue;
			}
			$drop = array_slice($group, 1);
			foreach ($drop as $entry) {
				$ids[(int) $entry['id']] = (int) $entry['id'];
			}
		}

		return array_values($ids);
	}
}
