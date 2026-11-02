<?php

namespace App\Services\Timetable;

/**
 * Parking-lot staging: one DB row per weekly period still not on the grid.
 */
class TimetableStagingService
{
	/** @var array<string,array<string,mixed>> */
	private $assignmentMeta = [];

	/** @var array<string,mixed>|null */
	private $timetableSettings = null;

	/** @var SecondaryTimetableCriteria|null */
	private $secondaryCriteria = null;

	/** @var array<int,string> */
	private $slotClockCache = [];

	/** @return string */
	private function assignmentKey(int $courseRecordId, int $classId, int $courseId, int $staffId): string
	{
		if ($courseRecordId > 0) {
			return 'cr:' . $courseRecordId;
		}

		return 'c:' . $classId . ':' . $courseId . ':' . $staffId;
	}

	/** @param array<string,mixed> $entry */
	/** Version 2 lock: leftovers may use an empty Mon–Thu 15:40–16:20 cell. */
	private $version2Overflow = false;

	private function entryIsLocked(array $entry): bool
	{
		return (int) ($entry['is_locked'] ?? 0) === 1;
	}

	/** @var array<int,true>|null */
	private $partTimeStaffIds = null;

	/** @var array<int,true> */
	private $morningPackedIds = [];

	/** @var array<string,array<string,mixed>> */
	private $classCourseMeta = [];

	/** @var array<string,list<array<string,mixed>>> */
	private $groupingSlots = [];

	/** @var array<string,array<string,mixed>> */
	private $groupingBlocked = [];

	/** @var array<string,int>|null */
	private $coursePeriodCount = null;

	/** @var array<int,true> */
	private $groupingMovedIds = [];

	private $lastSwapReject = '';

	private function isPartTimeStaff(int $staffId): bool
	{
		if ($staffId <= 0) {
			return false;
		}
		if ($this->partTimeStaffIds === null) {
			$this->partTimeStaffIds = [];
			$rows = \Config\Database::connect()->table('staffs')
				->select('id')
				->where('work_type', 2)
				->get()->getResultArray();
			foreach ($rows as $row) {
				$this->partTimeStaffIds[(int) ($row['id'] ?? 0)] = true;
			}
		}
		return isset($this->partTimeStaffIds[$staffId]);
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
		/** @var array<string,list<int>> */
		$scheduledIds = [];
		/** @var array<string,list<int>> */
		$stagedIds = [];
		foreach ($entries as $entry) {
			$key = $this->keyFromEntry($entry);
			$day = (int) ($entry['day_of_week'] ?? 0);
			$slotId = (int) ($entry['slot_id'] ?? 0);
			$id = (int) ($entry['id'] ?? 0);
			if ($day >= 0 && $slotId > 0) {
				$scheduled[$key] = ($scheduled[$key] ?? 0) + 1;
				if ($id > 0) {
					$scheduledIds[$key][] = $id;
				}
			} elseif ($day === -1 && $slotId === 0) {
				$staged[$key] = ($staged[$key] ?? 0) + 1;
				if ($id > 0) {
					$stagedIds[$key][] = $id;
				}
			}
		}

		$created = 0;
		foreach ($filtered as $assignment) {
			$key = $this->keyFromAssignment($assignment);
			$needed = TimetableGeneratorService::weeklyHoursFromCourse($assignment);
			$haveScheduled = (int) ($scheduled[$key] ?? 0);
			$haveStaged = (int) ($staged[$key] ?? 0);

			// Never keep more grid periods than the course credit / weekly allocation.
			if ($haveScheduled > $needed && !empty($scheduledIds[$key])) {
				$surplus = $haveScheduled - $needed;
				$toDelete = array_slice($scheduledIds[$key], -$surplus);
				if ($toDelete !== []) {
					$db->table('timetable_entries')->whereIn('id', $toDelete)->delete();
					$haveScheduled = $needed;
					$scheduled[$key] = $haveScheduled;
				}
			}

			$allowedStaged = max(0, $needed - $haveScheduled);
			if ($haveStaged > $allowedStaged && !empty($stagedIds[$key])) {
				$surplus = $haveStaged - $allowedStaged;
				$toDelete = array_slice($stagedIds[$key], -$surplus);
				if ($toDelete !== []) {
					$db->table('timetable_entries')->whereIn('id', $toDelete)->delete();
					$haveStaged = $allowedStaged;
					$staged[$key] = $haveStaged;
				}
			}

			$have = $haveScheduled + $haveStaged;
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
	 * Drop timetable lessons that would put a course above its Manage Course periods.
	 * A combined group shares one total: distinct clocks cannot exceed that credit.
	 * An unaligned extra is moved onto a shared clock when the class cell is free.
	 *
	 * @return int rows removed
	 */
	public function capPeriodsToManageCourse(int $scheduleId, int $schoolId): int
	{
		if ($scheduleId <= 0 || $schoolId <= 0) {
			return 0;
		}
		$this->dropLessonsNotInManageCourse($scheduleId, $schoolId);
		$db = \Config\Database::connect();
		$this->assignmentMeta = $this->loadAssignmentMeta($scheduleId, $schoolId);
		if ($this->assignmentMeta === []) {
			return 0;
		}
		$this->secondaryCriteria = new SecondaryTimetableCriteria();
		$this->secondaryCriteria->hydrateFromAssignments(array_values($this->assignmentMeta));
		$this->secondaryCriteria->hydrateCustomRules((new TimetableCriteriaStore())->listForSchool($schoolId, true));

		$scheduled = $this->scheduledEntries($scheduleId, $schoolId);
		if ($scheduled === []) {
			return 0;
		}

		$removed = 0;
		$removed += $this->capSingleCourseRows($db, $scheduled);
		$scheduled = $this->scheduledEntries($scheduleId, $schoolId);
		$removed += $this->capCombinedClocks($db, $scheduled);

		return $removed;
	}

	/**
	 * A lesson stays on the timetable only when that class still has the course
	 * in Manage Course for this schedule's year and term. Locked rows are removed too.
	 */
	public function dropLessonsNotInManageCourse(int $scheduleId, int $schoolId): int
	{
		if ($scheduleId <= 0 || $schoolId <= 0) {
			return 0;
		}
		$db = \Config\Database::connect();
		$schedule = $db->table('timetable_schedules')->where('id', $scheduleId)->where('school_id', $schoolId)->get(1)->getRowArray();
		if (!$schedule) {
			return 0;
		}
		$year = (int) ($schedule['academic_year'] ?? 0);
		$term = (int) ($schedule['term'] ?? 0);
		if ($year <= 0 || $term <= 0) {
			return 0;
		}

		$allowed = [];
		$rows = $db->table('course_records cr')
			->select('cr.class AS class_id, cr.course AS course_id')
			->join('classes cl', 'cl.id = cr.class')
			->join('levels l', 'l.id = cl.level', 'left')
			->join('departments d', 'd.id = cl.department', 'left')
			->where('cl.school_id', $schoolId)
			->where('cr.year', $year)
			->where("find_in_set({$term}, cr.term) >", 0, false)
			->where(SecondaryTimetableCriteria::excludeEmptyS6PcmSql('cl', 'l', 'd', $year), null, false)
			->whereNotIn('cr.course', SecondaryTimetableCriteria::MANAGER_ONLY_COURSE_IDS)
			->get()->getResultArray();
		foreach ($rows as $row) {
			$classId = (int) ($row['class_id'] ?? 0);
			$courseId = (int) ($row['course_id'] ?? 0);
			if ($classId > 0 && $courseId > 0) {
				$allowed[$classId . ':' . $courseId] = true;
			}
		}

		$lessons = $db->table('timetable_entries te')
			->select('te.id, te.class_id, te.course_id')
			->join('classes cl', 'cl.id = te.class_id')
			->where('te.schedule_id', $scheduleId)
			->where('cl.school_id', $schoolId)
			->where('te.entry_type', 'lesson')
			->get()->getResultArray();
		$deleteIds = [];
		foreach ($lessons as $lesson) {
			$key = (int) ($lesson['class_id'] ?? 0) . ':' . (int) ($lesson['course_id'] ?? 0);
			if (!isset($allowed[$key])) {
				$deleteIds[] = (int) $lesson['id'];
			}
		}

		return $this->deleteEntryIds($db, $deleteIds);
	}

	/**
	 * @param list<array<string,mixed>> $scheduled
	 */
	private function capSingleCourseRows($db, array $scheduled): int
	{
		$byKey = [];
		foreach ($scheduled as $entry) {
			$key = $this->keyFromEntry($entry);
			$byKey[$key][] = $entry;
		}
		$deleteIds = [];
		foreach ($byKey as $key => $rows) {
			$meta = $this->assignmentMeta[$key] ?? $this->metaForEntry($rows[0]);
			if ($this->secondaryCriteria !== null && $this->secondaryCriteria->combineGroupKey($meta) !== '') {
				continue;
			}
			$needed = TimetableGeneratorService::weeklyHoursFromCourse($meta);
			if ($needed <= 0 || count($rows) <= $needed) {
				continue;
			}
			foreach ($this->rowsBeyondLimit($rows, $needed) as $id) {
				$deleteIds[] = $id;
			}
		}
		return $this->deleteEntryIds($db, $deleteIds);
	}

	/**
	 * @param list<array<string,mixed>> $scheduled
	 */
	private function capCombinedClocks($db, array $scheduled): int
	{
		if ($this->secondaryCriteria === null) {
			return 0;
		}
		$groups = [];
		foreach ($this->assignmentMeta as $row) {
			$groupKey = $this->secondaryCriteria->combineGroupKey($row);
			if ($groupKey === '') {
				continue;
			}
			$hours = TimetableGeneratorService::weeklyHoursFromCourse($row);
			if (!isset($groups[$groupKey])) {
				$groups[$groupKey] = ['target' => $hours, 'members' => []];
			} elseif ($hours > (int) $groups[$groupKey]['target']) {
				$groups[$groupKey]['target'] = $hours;
			}
			$classId = (int) ($row['class_id'] ?? 0);
			$courseId = (int) ($row['course_id'] ?? 0);
			if ($classId > 0 && $courseId > 0) {
				$groups[$groupKey]['members'][$classId . ':' . $courseId] = true;
			}
		}

		$busy = [];
		foreach ($scheduled as $entry) {
			$classId = (int) ($entry['class_id'] ?? 0);
			$day = (int) ($entry['day_of_week'] ?? -1);
			$slotId = (int) ($entry['slot_id'] ?? 0);
			if ($classId > 0 && $day >= 0 && $slotId > 0) {
				$busy[$classId . ':' . $day . ':' . $slotId] = (int) ($entry['id'] ?? 0);
			}
		}

		$removed = 0;
		foreach ($groups as $group) {
			$target = (int) ($group['target'] ?? 0);
			$members = $group['members'] ?? [];
			if ($target <= 0 || $members === []) {
				continue;
			}
			$entries = [];
			foreach ($scheduled as $entry) {
				$member = (int) ($entry['class_id'] ?? 0) . ':' . (int) ($entry['course_id'] ?? 0);
				if (isset($members[$member])) {
					$entries[] = $entry;
				}
			}
			if ($entries === []) {
				continue;
			}

			$byClock = [];
			foreach ($entries as $entry) {
				$clock = $this->entryClockKey($entry);
				$byClock[$clock][] = $entry;
			}
			if (count($byClock) <= $target) {
				continue;
			}

			$ranked = [];
			foreach ($byClock as $clock => $rows) {
				$classes = [];
				$locked = 0;
				foreach ($rows as $row) {
					$classes[(int) ($row['class_id'] ?? 0)] = true;
					if ($this->entryIsLocked($row)) {
						$locked++;
					}
				}
				$ranked[] = [
					'clock' => $clock,
					'cover' => count($classes),
					'locked' => $locked,
					'rows' => $rows,
				];
			}
			usort($ranked, static function (array $a, array $b): int {
				if ($a['cover'] !== $b['cover']) {
					return $b['cover'] <=> $a['cover'];
				}
				if ($a['locked'] !== $b['locked']) {
					return $b['locked'] <=> $a['locked'];
				}
				return strcmp((string) $a['clock'], (string) $b['clock']);
			});

			$keep = array_slice($ranked, 0, $target);
			$drop = array_slice($ranked, $target);
			$hostByClock = [];
			$classClocks = [];
			foreach ($keep as $bucket) {
				$hostByClock[$bucket['clock']] = $bucket['rows'][0];
				foreach ($bucket['rows'] as $row) {
					$classClocks[(int) ($row['class_id'] ?? 0)][$bucket['clock']] = true;
				}
			}

			$deleteIds = [];
			foreach ($drop as $bucket) {
				foreach ($bucket['rows'] as $row) {
					$entryId = (int) ($row['id'] ?? 0);
					$classId = (int) ($row['class_id'] ?? 0);
					$moved = false;
					if ($entryId > 0 && $classId > 0) {
						foreach ($hostByClock as $clock => $host) {
							if (!empty($classClocks[$classId][$clock])) {
								continue;
							}
							$day = (int) ($host['day_of_week'] ?? -1);
							$slotId = (int) ($host['slot_id'] ?? 0);
							if ($day < 0 || $slotId <= 0) {
								continue;
							}
							$busyKey = $classId . ':' . $day . ':' . $slotId;
							$occupant = (int) ($busy[$busyKey] ?? 0);
							if ($occupant > 0 && $occupant !== $entryId) {
								continue;
							}
							$db->table('timetable_entries')->where('id', $entryId)->update([
								'day_of_week' => $day,
								'slot_id' => $slotId,
							]);
							$busy[$busyKey] = $entryId;
							$classClocks[$classId][$clock] = true;
							$moved = true;
							break;
						}
					}
					if (!$moved && $entryId > 0) {
						$deleteIds[] = $entryId;
					}
				}
			}
			$removed += $this->deleteEntryIds($db, $deleteIds);
		}

		return $removed;
	}

	/**
	 * @param list<array<string,mixed>> $rows
	 * @return list<int>
	 */
	private function rowsBeyondLimit(array $rows, int $keep): array
	{
		if (count($rows) <= $keep) {
			return [];
		}
		usort($rows, function (array $a, array $b): int {
			$lock = (int) $this->entryIsLocked($b) <=> (int) $this->entryIsLocked($a);
			if ($lock !== 0) {
				return $lock;
			}
			return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
		});
		$ids = [];
		foreach (array_slice($rows, $keep) as $row) {
			$id = (int) ($row['id'] ?? 0);
			if ($id > 0) {
				$ids[] = $id;
			}
		}
		return $ids;
	}

	/** @param list<int> $ids */
	private function deleteEntryIds($db, array $ids): int
	{
		$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
		if ($ids === []) {
			return 0;
		}
		$db->table('timetable_entries')->whereIn('id', $ids)->delete();
		return count($ids);
	}

	/** @param array<string,mixed> $entry */
	private function entryClockKey(array $entry): string
	{
		return (int) ($entry['day_of_week'] ?? -1)
			. '|' . substr((string) ($entry['start_time'] ?? ''), 0, 5)
			. '|' . substr((string) ($entry['end_time'] ?? ''), 0, 5);
	}

	/**
	 * Combined classes share one Manage Course total. A new clock past that total is refused.
	 *
	 * @param array<string,mixed> $entry
	 * @param list<array<string,mixed>> $scheduled
	 */
	private function placingCombinedWouldExceed(array $entry, int $day, int $slotId, array $scheduled): bool
	{
		if ($this->secondaryCriteria === null || $this->assignmentMeta === [] || $day < 0 || $slotId <= 0) {
			return false;
		}
		$meta = $this->metaForEntry($entry);
		$groupKey = $this->secondaryCriteria->combineGroupKey($meta);
		if ($groupKey === '') {
			return false;
		}
		$target = 0;
		$members = [];
		foreach ($this->assignmentMeta as $row) {
			if ($this->secondaryCriteria->combineGroupKey($row) !== $groupKey) {
				continue;
			}
			$hours = TimetableGeneratorService::weeklyHoursFromCourse($row);
			if ($hours > $target) {
				$target = $hours;
			}
			$classId = (int) ($row['class_id'] ?? 0);
			$courseId = (int) ($row['course_id'] ?? 0);
			if ($classId > 0 && $courseId > 0) {
				$members[$classId . ':' . $courseId] = true;
			}
		}
		if ($target <= 0 || $members === []) {
			return false;
		}
		$newClock = $day . '|' . $this->slotClockLabel($slotId);
		$clocks = [];
		foreach ($scheduled as $row) {
			$member = (int) ($row['class_id'] ?? 0) . ':' . (int) ($row['course_id'] ?? 0);
			if (!isset($members[$member])) {
				continue;
			}
			$clock = $this->entryClockKey($row);
			if ($clock !== '-1||') {
				$clocks[$clock] = true;
			}
		}
		if (isset($clocks[$newClock])) {
			return false;
		}
		return count($clocks) >= $target;
	}

	private function slotClockLabel(int $slotId): string
	{
		if ($slotId <= 0) {
			return '|';
		}
		if (!isset($this->slotClockCache[$slotId])) {
			$row = \Config\Database::connect()->table('timetable_slots')
				->select('start_time, end_time')
				->where('id', $slotId)
				->get(1)->getRowArray();
			$this->slotClockCache[$slotId] = substr((string) ($row['start_time'] ?? ''), 0, 5)
				. '|' . substr((string) ($row['end_time'] ?? ''), 0, 5);
		}
		return $this->slotClockCache[$slotId];
	}

	/**
	 * @param list<array<string,mixed>> $scheduled
	 * @return list<array<string,mixed>>
	 */
	private function rememberPlacedClock(array $scheduled, array $entry, int $day, int $slotId): array
	{
		$clock = $this->slotClockLabel($slotId);
		$parts = explode('|', $clock);
		$scheduled[] = [
			'class_id' => (int) ($entry['class_id'] ?? 0),
			'course_id' => (int) ($entry['course_id'] ?? 0),
			'day_of_week' => $day,
			'start_time' => $parts[0] ?? '',
			'end_time' => $parts[1] ?? '',
		];
		return $scheduled;
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
		int $filterStaffId = 0,
		bool $allowRelocate = true
	): int {
		if ($scheduleId <= 0 || $schoolId <= 0) {
			return 0;
		}

		$db = \Config\Database::connect();
		$settings = $db->table('timetable_settings')->where('school_id', $schoolId)->get(1)->getRowArray();
		$this->timetableSettings = $settings;
		$days = \App\Models\TimetableSchemaModel::weekDaysFromSettings($settings);
		$this->assignmentMeta = $this->loadAssignmentMeta($scheduleId, $schoolId);
		$this->secondaryCriteria = new SecondaryTimetableCriteria();
		$this->secondaryCriteria->hydrateFromAssignments(array_values($this->assignmentMeta));
		$this->secondaryCriteria->hydrateCustomRules((new TimetableCriteriaStore())->listForSchool($schoolId, true));

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

		$scheduled = $db->table('timetable_entries te')
			->select('te.*, ts.start_time, ts.end_time')
			->join('timetable_slots ts', 'ts.id = te.slot_id', 'left')
			->where('schedule_id', $scheduleId)
			->where('te.entry_type', 'lesson')
			->where('te.day_of_week >=', 0)
			->where('te.slot_id >', 0)
			->get()->getResultArray();

		$state = $this->buildScheduleState($scheduled);
		/** @var array<string,int> */
		$scheduledByKey = [];
		foreach ($scheduled as $row) {
			$key = $this->keyFromEntry($row);
			$scheduledByKey[$key] = ($scheduledByKey[$key] ?? 0) + 1;
		}

		usort($parking, function (array $a, array $b) use ($days, $schema, $schoolId, $state): int {
			return $this->countDirectCandidates($a, $days, $schema, $schoolId, $state)
				<=> $this->countDirectCandidates($b, $days, $schema, $schoolId, $state);
		});

		$placed = 0;
		foreach ($parking as $entry) {
			$key = $this->keyFromEntry($entry);
			$meta = $this->metaForEntry($entry);
			$needed = TimetableGeneratorService::weeklyHoursFromCourse($meta);
			$have = (int) ($scheduledByKey[$key] ?? 0);
			if ($needed > 0 && $have >= $needed) {
				// Drop surplus parking — already at allocated weekly periods.
				$db->table('timetable_entries')->where('id', (int) $entry['id'])->delete();
				continue;
			}

			$found = $this->findBestDirectPlacement($entry, $days, $schema, $schoolId, $state);
			if ($found === null && $allowRelocate) {
				$found = $this->placeByRelocatingOneBlocker($db, $entry, $days, $schema, $schoolId, $state);
			}
			if ($found === null) {
				continue;
			}
			if ($this->placingCombinedWouldExceed($entry, (int) $found['day'], (int) $found['slot_id'], $scheduled)) {
				continue;
			}

			$db->table('timetable_entries')->where('id', (int) $entry['id'])->update([
				'day_of_week' => $found['day'],
				'slot_id' => $found['slot_id'],
			]);
			$entry['day_of_week'] = $found['day'];
			$entry['slot_id'] = $found['slot_id'];
			$this->addScheduledEntry($state, $entry);
			$scheduled = $this->rememberPlacedClock($scheduled, $entry, (int) $found['day'], (int) $found['slot_id']);
			$scheduledByKey[$key] = $have + 1;
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

			$park = $db->table('timetable_entries')->whereIn('id', $idsToParking);
			if ($db->fieldExists('is_locked', 'timetable_entries')) {
				$park->groupStart()->where('is_locked', 0)->orWhere('is_locked IS NULL', null, false)->groupEnd();
			}
			$park->update([
				'day_of_week' => -1,
				'slot_id' => 0,
			]);
			$totalMoved += count($idsToParking);
			// Place only into free cells — never relocate a legal lesson to force a fit.
			$totalReplaced += $this->autoPlaceStaging($scheduleId, $schoolId, $schema, $filterClassId, $filterStaffId, false);
		}

		$totalMoved += $this->parkAllConflicts($scheduleId, $schoolId, $filterClassId, $filterStaffId);
		$remaining = count($this->collectConflictEntryIds(
			$this->scheduledEntries($scheduleId, $schoolId, $filterClassId, $filterStaffId)
		));

		return [
			'moved_to_parking' => $totalMoved,
			'replaced' => $totalReplaced,
			'remaining_conflicts' => $remaining,
		];
	}

	/**
	 * Move parked or afternoon lessons into empty morning teaching slots for every class.
	 */
	public function fillMorningGaps(
		int $scheduleId,
		int $schoolId,
		\App\Models\TimetableSchemaModel $schema
	): int {
		if ($scheduleId <= 0 || $schoolId <= 0) {
			return 0;
		}
		$db = \Config\Database::connect();
		$settings = $db->table('timetable_settings')->where('school_id', $schoolId)->get(1)->getRowArray();
		$this->timetableSettings = $settings;
		$this->assignmentMeta = $this->loadAssignmentMeta($scheduleId, $schoolId);
		$this->secondaryCriteria = new SecondaryTimetableCriteria();
		$this->secondaryCriteria->hydrateFromAssignments(array_values($this->assignmentMeta));
		$this->secondaryCriteria->hydrateCustomRules((new TimetableCriteriaStore())->listForSchool($schoolId, true));

		$scheduled = $this->scheduledEntries($scheduleId, $schoolId);
		$state = $this->buildScheduleState($scheduled);
		$parking = $db->table('timetable_entries')
			->where('schedule_id', $scheduleId)
			->where('school_id', $schoolId)
			->where('entry_type', 'lesson')
			->where('day_of_week', -1)
			->where('slot_id', 0)
			->orderBy('id')
			->get()->getResultArray();

		$parkedByClass = [];
		foreach ($parking as $row) {
			$parkedByClass[(int) ($row['class_id'] ?? 0)][] = $row;
		}
		$placedByClass = [];
		foreach ($scheduled as $row) {
			$placedByClass[(int) ($row['class_id'] ?? 0)][] = $row;
		}

		$filled = 0;
		$classIds = array_unique(array_merge(array_keys($parkedByClass), array_keys($placedByClass)));
		foreach ($classIds as $classId) {
			if ($classId <= 0) {
				continue;
			}
			$trackKey = $schema->trackForClass($schoolId, $classId);
			$days = \App\Models\TimetableSchemaModel::weekDaysForTrack($settings, $trackKey);
			$slots = array_values(array_filter(
				$schema->teachingSlots($schoolId, $trackKey),
				static fn ($s) => empty($s['is_break'])
			));
			$blocked = $schema->specialTimesMap($schoolId, $trackKey);
			foreach ($days as $day) {
				foreach ($slots as $slot) {
					$slotId = (int) ($slot['id'] ?? 0);
					if ($slotId <= 0 || !TimetableGeneratorService::isMorningClock((string) ($slot['start_time'] ?? ''))) {
						continue;
					}
					if (!empty($blocked[$day . ':' . $slotId])) {
						continue;
					}
					if (!empty($state['class_busy'][$classId . ':' . $day . ':' . $slotId])) {
						continue;
					}

					$moved = $this->fillOneMorningSlot(
						$db,
						(int) $day,
						$slot,
						$parkedByClass[$classId] ?? [],
						$placedByClass[$classId] ?? [],
						$state
					);
					if ($moved === null) {
						continue;
					}
					$filled++;
					if (($moved['from'] ?? '') === 'parking') {
						$parkedByClass[$classId] = array_values(array_filter(
							$parkedByClass[$classId] ?? [],
							static fn (array $row): bool => (int) ($row['id'] ?? 0) !== (int) $moved['id']
						));
						$placedByClass[$classId][] = $moved['entry'];
					} else {
						foreach ($placedByClass[$classId] as $i => $row) {
							if ((int) ($row['id'] ?? 0) === (int) $moved['id']) {
								$placedByClass[$classId][$i] = $moved['entry'];
								break;
							}
						}
					}
				}
			}
		}

		return $filled;
	}

	/**
	 * Pull afternoon lessons into empty 07:00–12:00 cells for every high-school
	 * class and every full-time teacher. Combined partners move together.
	 * Part-time staff, clinical, PE, and after-lesson courses stay put.
	 */
	public function packAllMorningHoles(
		int $scheduleId,
		int $schoolId,
		\App\Models\TimetableSchemaModel $schema
	): int {
		if ($scheduleId <= 0 || $schoolId <= 0) {
			return 0;
		}
		$db = \Config\Database::connect();
		$this->assignmentMeta = $this->loadAssignmentMeta($scheduleId, $schoolId);
		$this->secondaryCriteria = new SecondaryTimetableCriteria();
		$this->secondaryCriteria->hydrateFromAssignments(array_values($this->assignmentMeta));
		$this->secondaryCriteria->hydrateCustomRules((new TimetableCriteriaStore())->listForSchool($schoolId, true));
		$this->secondaryCriteria->setOverflowFill(false);
		$this->morningPackedIds = [];

		$scheduled = $this->scheduledEntries($scheduleId, $schoolId);
		$state = $this->buildScheduleState($scheduled);
		$tracks = [];
		$classTrack = [];
		foreach ($state['by_id'] as $entry) {
			$classId = (int) ($entry['class_id'] ?? 0);
			if ($classId <= 0 || isset($classTrack[$classId])) {
				continue;
			}
			$track = (string) $schema->trackForClass($schoolId, $classId);
			$classTrack[$classId] = $track;
			if ($this->isHighSchoolTrack($track)) {
				$tracks[$track] = true;
			}
		}
		$morningByTrack = [];
		foreach (array_keys($tracks) as $track) {
			$morningByTrack[$track] = [];
			foreach ($schema->teachingSlots($schoolId, $track) as $slot) {
				if (!empty($slot['is_break'])) {
					continue;
				}
				if (!TimetableGeneratorService::isMorningClock((string) ($slot['start_time'] ?? ''))) {
					continue;
				}
				$morningByTrack[$track][] = $slot;
			}
		}
		$clocks = [];
		foreach ($morningByTrack as $slots) {
			foreach ($slots as $slot) {
				$clocks[substr((string) ($slot['start_time'] ?? ''), 0, 5)] = $slot;
			}
		}
		ksort($clocks);

		$moved = 0;
		for ($pass = 0; $pass < 12; $pass++) {
			$gained = $this->packMorningDirect($db, $state, $schema, $schoolId, $classTrack, $morningByTrack, $clocks, false);
			if ($gained === 0) {
				$gained = $this->packMorningDisplace($db, $state, $schema, $schoolId, $classTrack, $clocks, false);
			}
			if ($gained === 0) {
				$this->secondaryCriteria->setOverflowFill(true);
				$gained = $this->packMorningDirect($db, $state, $schema, $schoolId, $classTrack, $morningByTrack, $clocks, true);
				$this->secondaryCriteria->setOverflowFill(false);
			}
			if ($gained === 0) {
				break;
			}
			$moved += $gained;
		}
		return $moved;
	}

	/**
	 * Join isolated Examinable Subjects periods into doubles.
	 * A 3, 5 or 7 period course keeps one single. Two-period courses stay on separate days.
	 * Part-time, clinical, PE, and evening courses stay put.
	 *
	 * @return array{moved:int,isolated_before:int,isolated_after:int}
	 */
	public function pairExaminableDoubles(
		int $scheduleId,
		int $schoolId,
		\App\Models\TimetableSchemaModel $schema
	): array {
		if ($scheduleId <= 0 || $schoolId <= 0) {
			return ['moved' => 0, 'isolated_before' => 0, 'isolated_after' => 0];
		}
		$db = \Config\Database::connect();
		$this->assignmentMeta = $this->loadAssignmentMeta($scheduleId, $schoolId);
		$this->classCourseMeta = [];
		foreach ($this->assignmentMeta as $row) {
			$this->classCourseMeta[(int) ($row['class_id'] ?? 0) . ':' . (int) ($row['course_id'] ?? 0)] = $row;
		}
		$this->secondaryCriteria = new SecondaryTimetableCriteria();
		$this->secondaryCriteria->hydrateFromAssignments(array_values($this->assignmentMeta));
		$this->secondaryCriteria->hydrateCustomRules((new TimetableCriteriaStore())->listForSchool($schoolId, true));
		$this->secondaryCriteria->setOverflowFill(false);

		$scheduled = $this->scheduledEntries($scheduleId, $schoolId);
		$state = $this->buildScheduleState($scheduled);
		$classTrack = [];
		foreach ($state['by_id'] as $entry) {
			$classId = (int) ($entry['class_id'] ?? 0);
			if ($classId <= 0 || isset($classTrack[$classId])) {
				continue;
			}
			$classTrack[$classId] = (string) $schema->trackForClass($schoolId, $classId);
		}
		$before = $this->countIsolatedExaminable($state);
		$moved = 0;
		for ($pass = 0; $pass < 40; $pass++) {
			$gained = $this->unstackOneExaminable($db, $state, $schema, $schoolId, $classTrack);
			if ($gained === 0) {
				break;
			}
			$moved += $gained;
		}
		for ($pass = 0; $pass < 80; $pass++) {
			$gained = $this->pairOneExaminableDouble($db, $state, $schema, $schoolId, $classTrack);
			if ($gained === 0) {
				break;
			}
			$moved += $gained;
		}

		return [
			'moved' => $moved,
			'isolated_before' => $before,
			'isolated_after' => $this->countIsolatedExaminable($state),
		];
	}

	/**
	 * Pull isolated Examinable Subjects periods onto a neighbouring period of the same course.
	 * Two or more weekly periods become consecutive blocks of two. An odd total keeps one block of three.
	 * Part-time staff rows are never moved.
	 *
	 * @return array{moved:int,isolated_before:int,isolated_after:int,part_time_singles:int}
	 */
	public function groupExaminableConsecutive(
		int $scheduleId,
		int $schoolId,
		\App\Models\TimetableSchemaModel $schema
	): array {
		$empty = ['moved' => 0, 'isolated_before' => 0, 'isolated_after' => 0, 'part_time_singles' => 0];
		if ($scheduleId <= 0 || $schoolId <= 0) {
			return $empty;
		}
		$db = \Config\Database::connect();
		$this->groupingSlots = [];
		$this->groupingBlocked = [];
		$this->coursePeriodCount = null;
		$this->groupingMovedIds = [];
		$this->assignmentMeta = $this->loadAssignmentMeta($scheduleId, $schoolId);
		$this->classCourseMeta = [];
		foreach ($this->assignmentMeta as $row) {
			$this->classCourseMeta[(int) ($row['class_id'] ?? 0) . ':' . (int) ($row['course_id'] ?? 0)] = $row;
		}
		$this->secondaryCriteria = new SecondaryTimetableCriteria();
		$this->secondaryCriteria->hydrateFromAssignments(array_values($this->assignmentMeta));
		$this->secondaryCriteria->hydrateCustomRules((new TimetableCriteriaStore())->listForSchool($schoolId, true));
		$this->secondaryCriteria->setOverflowFill(false);

		$scheduled = $this->scheduledEntries($scheduleId, $schoolId);
		$state = $this->buildScheduleState($scheduled);
		$classTrack = [];
		foreach ($state['by_id'] as $entry) {
			$classId = (int) ($entry['class_id'] ?? 0);
			if ($classId <= 0 || isset($classTrack[$classId])) {
				continue;
			}
			$classTrack[$classId] = (string) $schema->trackForClass($schoolId, $classId);
		}
		$before = $this->countGroupableIsolates($state);
		$partSingles = $this->countPartTimeExaminableSingles($state);
		$moved = 0;
		foreach ([1, 2] as $anchorLen) {
			for ($pass = 0; $pass < 400; $pass++) {
				$gained = $this->joinOneExaminableRun($db, $state, $schema, $schoolId, $classTrack, $anchorLen, false);
				if ($gained === 0) {
					$gained = $this->joinOneExaminableRun($db, $state, $schema, $schoolId, $classTrack, $anchorLen, true);
				}
				if ($gained === 0) {
					break;
				}
				$moved += $gained;
			}
		}
		$this->secondaryCriteria->setOverflowFill(false);

		return [
			'moved' => $moved,
			'isolated_before' => $before,
			'isolated_after' => $this->countGroupableIsolates($state),
			'part_time_singles' => $partSingles,
		];
	}

	/** @param array<string,mixed> $state */
	private function countGroupableIsolates(array $state): int
	{
		$n = 0;
		foreach ($state['by_id'] as $entry) {
			if ($this->isGroupableExaminable($state, $entry) && $this->courseClockIsolated($state, $entry)) {
				$n++;
			}
		}
		return $n;
	}

	/** @param array<string,mixed> $state */
	private function countPartTimeExaminableSingles(array $state): int
	{
		$n = 0;
		foreach ($state['by_id'] as $entry) {
			if (!$this->isPartTimeStaff((int) ($entry['staff_id'] ?? 0))) {
				continue;
			}
			$meta = $this->metaForEntry($entry);
			$cat = (string) ($entry['category_title'] ?? $meta['category_title'] ?? '');
			if (!SecondaryTimetableCriteria::isExaminableMainCategory($cat)) {
				continue;
			}
			if ($this->courseClockIsolated($state, $entry)) {
				$n++;
			}
		}
		return $n;
	}

	/**
	 * @param array<string,mixed> $state
	 * @param array<int,string> $classTrack
	 */
	private function joinOneExaminableRun(
		$db,
		array &$state,
		\App\Models\TimetableSchemaModel $schema,
		int $schoolId,
		array $classTrack,
		int $anchorLen,
		bool $allowSwap
	): int {
		foreach ($state['by_id'] as $entry) {
			if (!$this->isGroupableExaminable($state, $entry) || !$this->courseClockIsolated($state, $entry)) {
				continue;
			}
			$group = $this->sameCourseClockGroup($state, $entry);
			if (!$this->groupCanRelocate($state, $group)) {
				continue;
			}
			$leader = $group[0];
			foreach ($group as $member) {
				if ((int) ($member['class_id'] ?? 0) < (int) ($leader['class_id'] ?? 0)) {
					$leader = $member;
				}
			}
			if ((int) ($leader['id'] ?? 0) !== (int) ($entry['id'] ?? 0)) {
				continue;
			}
			if (isset($this->groupingMovedIds[(int) ($leader['id'] ?? 0)])) {
				continue;
			}
			foreach ($this->sharedNeighborTargets($state, $schema, $schoolId, $classTrack, $group, $anchorLen) as $target) {
				$ignore = $this->groupIds($group);
				if ($this->groupMaySitAt($state, $schema, $schoolId, $classTrack, $group, (int) $target['day'], $target['slot'], $ignore)) {
					$this->writeGroupAt($db, $state, $schema, $schoolId, $classTrack, $group, (int) $target['day'], $target['slot'], false);
					return 1;
				}
				if ($allowSwap && $this->swapOntoTarget($db, $state, $schema, $schoolId, $classTrack, $group, $leader, (int) $target['day'], $target['slot'])) {
					return 1;
				}
				if ($allowSwap && $this->displaceOntoTarget($db, $state, $schema, $schoolId, $classTrack, $group, $leader, (int) $target['day'], $target['slot'])) {
					return 1;
				}
				if ($allowSwap && $this->pullBlockersAndSit($db, $state, $schema, $schoolId, $classTrack, $group, $leader, (int) $target['day'], $target['slot'])) {
					return 1;
				}
			}
		}
		return 0;
	}

	/** @param array<string,mixed> $state @param array<string,mixed> $entry */
	private function isGroupableExaminable(array $state, array $entry): bool
	{
		if ($this->isPartTimeStaff((int) ($entry['staff_id'] ?? 0))) {
			return false;
		}
		$meta = $this->metaForEntry($entry);
		$cat = (string) ($entry['category_title'] ?? $meta['category_title'] ?? '');
		if (!SecondaryTimetableCriteria::isExaminableMainCategory($cat)) {
			return false;
		}
		$title = (string) ($entry['course_title'] ?? $meta['course_title'] ?? '');
		if (SecondaryTimetableCriteria::isOmittedFromGridTitle($title)
			|| TimetableGeneratorService::isPhysicalEducationSportTitle($title)) {
			return false;
		}
		if ($this->secondaryCriteria !== null) {
			if ($this->secondaryCriteria->isClinicalAttachmentCourse($meta)
				|| $this->secondaryCriteria->requiresAfterLessons($meta)
				|| $this->secondaryCriteria->isFixedEveningActivity($meta)) {
				return false;
			}
		}
		$classId = (int) ($entry['class_id'] ?? 0);
		$courseId = (int) ($entry['course_id'] ?? 0);
		if ($classId <= 0 || $courseId <= 0) {
			return false;
		}
		$teacher = strtolower(trim(preg_replace('/\s+/', ' ', (string) ($entry['teacher_name'] ?? $meta['teacher_name'] ?? ''))));
		if (strpos($teacher, 'ayobikeka') !== false && strpos(strtolower($title), 'econ') !== false) {
			return false;
		}
		if ($this->coursePeriodCount === null) {
			$this->coursePeriodCount = [];
			foreach ($state['by_id'] as $other) {
				$key = (int) ($other['class_id'] ?? 0) . ':' . (int) ($other['course_id'] ?? 0);
				$this->coursePeriodCount[$key] = (int) ($this->coursePeriodCount[$key] ?? 0) + 1;
			}
		}
		return (int) ($this->coursePeriodCount[$classId . ':' . $courseId] ?? 0) >= 2;
	}

	/**
	 * Same teacher, same clock, same course. Combined copies stay together.
	 *
	 * @param array<string,mixed> $state
	 * @param array<string,mixed> $entry
	 * @return list<array<string,mixed>>
	 */
	private function sameCourseClockGroup(array $state, array $entry): array
	{
		$group = [];
		foreach ($this->sameClockGroup($state, $entry) as $member) {
			$sameCourse = (int) ($member['course_id'] ?? 0) > 0
				&& (int) ($member['course_id'] ?? 0) === (int) ($entry['course_id'] ?? 0);
			$combined = $this->secondaryCriteria !== null
				&& SecondaryTimetableCriteria::entriesAreCombinedLesson($entry, $member);
			if ((int) ($member['id'] ?? 0) === (int) ($entry['id'] ?? 0) || $sameCourse || $combined) {
				$group[] = $member;
			}
		}
		return $group === [] ? [$entry] : $group;
	}

	/**
	 * @param array<string,mixed> $state
	 * @param list<array<string,mixed>> $group
	 */
	private function groupCanRelocate(array $state, array $group): bool
	{
		if ($group === []) {
			return false;
		}
		foreach ($group as $member) {
			if ($this->isPartTimeStaff((int) ($member['staff_id'] ?? 0))) {
				return false;
			}
			if (!$this->isGroupableExaminable($state, $member) || !$this->courseClockIsolated($state, $member)) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @param array<string,mixed> $state
	 * @param array<int,string> $classTrack
	 * @param list<array<string,mixed>> $group
	 * @return list<array{day:int,slot:array<string,mixed>}>
	 */
	private function sharedNeighborTargets(
		array $state,
		\App\Models\TimetableSchemaModel $schema,
		int $schoolId,
		array $classTrack,
		array $group,
		int $anchorLen
	): array {
		$leader = $group[0];
		$runs = $this->courseRuns($state, (int) ($leader['class_id'] ?? 0), (int) ($leader['course_id'] ?? 0), $this->groupIds($group));
		$track = (string) ($classTrack[(int) ($leader['class_id'] ?? 0)] ?? '');
		$targets = [];
		foreach ($runs as $run) {
			if (count($run) !== $anchorLen) {
				continue;
			}
			$day = (int) ($run[0]['day_of_week'] ?? -1);
			if ($day < 0 || $day > 4) {
				continue;
			}
			foreach ($this->endNeighborSlots($schema, $schoolId, $track, $run) as $slot) {
				$start = substr((string) ($slot['start_time'] ?? ''), 0, 5);
				$ok = true;
				foreach ($group as $member) {
					$memberRuns = $this->courseRuns(
						$state,
						(int) ($member['class_id'] ?? 0),
						(int) ($member['course_id'] ?? 0),
						$this->groupIds($group)
					);
					$matched = false;
					foreach ($memberRuns as $memberRun) {
						if (count($memberRun) !== $anchorLen) {
							continue;
						}
						if ((int) ($memberRun[0]['day_of_week'] ?? -1) !== $day) {
							continue;
						}
						if ($this->courseCountOnDay($state, (int) ($member['class_id'] ?? 0), (int) ($member['course_id'] ?? 0), $day, $this->groupIds($group)) !== $anchorLen) {
							continue;
						}
						foreach ($this->endNeighborSlots($schema, $schoolId, (string) ($classTrack[(int) ($member['class_id'] ?? 0)] ?? $track), $memberRun) as $neighbor) {
							if (substr((string) ($neighbor['start_time'] ?? ''), 0, 5) === $start) {
								$matched = true;
								break 2;
							}
						}
					}
					if (!$matched) {
						$ok = false;
						break;
					}
				}
				if ($ok) {
					$targets[] = ['day' => $day, 'slot' => $slot];
				}
			}
		}
		return $targets;
	}

	/**
	 * @param array<string,mixed> $state
	 * @param list<int> $ignoreIds
	 * @return list<list<array<string,mixed>>>
	 */
	private function courseRuns(array $state, int $classId, int $courseId, array $ignoreIds): array
	{
		$ignore = array_fill_keys($ignoreIds, true);
		$byDay = [];
		foreach ($state['by_id'] as $other) {
			if (isset($ignore[(int) ($other['id'] ?? 0)])) {
				continue;
			}
			if ((int) ($other['class_id'] ?? 0) !== $classId || (int) ($other['course_id'] ?? 0) !== $courseId) {
				continue;
			}
			$day = (int) ($other['day_of_week'] ?? -1);
			if ($day < 0) {
				continue;
			}
			$byDay[$day][] = $other;
		}
		$runs = [];
		foreach ($byDay as $rows) {
			usort($rows, function (array $a, array $b): int {
				return $this->timeToMinutes((string) ($a['start_time'] ?? ''))
					<=> $this->timeToMinutes((string) ($b['start_time'] ?? ''));
			});
			$run = [];
			$prevEnd = null;
			foreach ($rows as $row) {
				$start = $this->timeToMinutes((string) ($row['start_time'] ?? ''));
				$end = $this->timeToMinutes((string) ($row['end_time'] ?? ''));
				if ($run !== [] && $prevEnd !== null && abs($start - $prevEnd) > 35) {
					$runs[] = $run;
					$run = [];
				}
				$run[] = $row;
				$prevEnd = $end;
			}
			if ($run !== []) {
				$runs[] = $run;
			}
		}
		return $runs;
	}

	/**
	 * @param list<array<string,mixed>> $run
	 * @return list<array<string,mixed>>
	 */
	private function endNeighborSlots(
		\App\Models\TimetableSchemaModel $schema,
		int $schoolId,
		string $track,
		array $run
	): array {
		if ($run === []) {
			return [];
		}
		$ends = [$run[0], $run[count($run) - 1]];
		$inside = [];
		foreach ($run as $row) {
			$inside[substr((string) ($row['start_time'] ?? ''), 0, 5)] = true;
		}
		$out = [];
		$seen = [];
		foreach ($ends as $end) {
			foreach ($this->cachedNeighborSlots($schema, $schoolId, $track, (string) ($end['start_time'] ?? '')) as $slot) {
				$start = substr((string) ($slot['start_time'] ?? ''), 0, 5);
				if ($start === '' || isset($inside[$start]) || isset($seen[$start])) {
					continue;
				}
				$seen[$start] = true;
				$out[] = $slot;
			}
		}
		return $out;
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	private function cachedNeighborSlots(
		\App\Models\TimetableSchemaModel $schema,
		int $schoolId,
		string $track,
		string $start
	): array {
		if (!isset($this->groupingSlots[$track])) {
			$slots = [];
			foreach ($schema->teachingSlots($schoolId, $track) as $slot) {
				if (empty($slot['is_break'])) {
					$slots[] = $slot;
				}
			}
			usort($slots, function (array $a, array $b): int {
				return $this->timeToMinutes((string) ($a['start_time'] ?? ''))
					<=> $this->timeToMinutes((string) ($b['start_time'] ?? ''));
			});
			$this->groupingSlots[$track] = $slots;
		}
		$want = substr($start, 0, 5);
		$slots = $this->groupingSlots[$track];
		$index = null;
		foreach ($slots as $i => $slot) {
			if (substr((string) ($slot['start_time'] ?? ''), 0, 5) === $want) {
				$index = $i;
				break;
			}
		}
		if ($index === null) {
			return [];
		}
		$out = [];
		foreach ([$index - 1, $index + 1] as $j) {
			if (!isset($slots[$j])) {
				continue;
			}
			$gap = min(
				abs($this->timeToMinutes((string) ($slots[$index]['end_time'] ?? '')) - $this->timeToMinutes((string) ($slots[$j]['start_time'] ?? ''))),
				abs($this->timeToMinutes((string) ($slots[$j]['end_time'] ?? '')) - $this->timeToMinutes((string) ($slots[$index]['start_time'] ?? '')))
			);
			if ($gap <= 35) {
				$out[] = $slots[$j];
			}
		}
		return $out;
	}

	/**
	 * @param array<string,mixed> $state
	 * @param list<int> $ignoreIds
	 */
	private function courseCountOnDay(array $state, int $classId, int $courseId, int $day, array $ignoreIds): int
	{
		$ignore = array_fill_keys($ignoreIds, true);
		$n = 0;
		foreach ($state['by_id'] as $other) {
			if (isset($ignore[(int) ($other['id'] ?? 0)])) {
				continue;
			}
			if ((int) ($other['class_id'] ?? 0) === $classId
				&& (int) ($other['course_id'] ?? 0) === $courseId
				&& (int) ($other['day_of_week'] ?? -1) === $day) {
				$n++;
			}
		}
		return $n;
	}

	/**
	 * @param array<string,mixed> $state
	 * @param list<array<string,mixed>> $group
	 * @param array<int,string> $classTrack
	 * @param array<string,mixed> $slot
	 * @param list<int> $ignoreIds
	 */
	private function groupMaySitAt(
		array $state,
		\App\Models\TimetableSchemaModel $schema,
		int $schoolId,
		array $classTrack,
		array $group,
		int $day,
		array $slot,
		array $ignoreIds
	): bool {
		if ($group === [] || $day < 0 || $day > 4) {
			return false;
		}
		$start = substr((string) ($slot['start_time'] ?? ''), 0, 5);
		$end = (string) ($slot['end_time'] ?? '');
		$startMin = $this->timeToMinutes($start);
		$endMin = $this->timeToMinutes($end);
		if ($start === '' || $endMin <= $startMin) {
			return false;
		}
		$ignore = array_fill_keys($ignoreIds, true);
		$staffChecked = [];
		$destAfternoon = $startMin >= (12 * 60);
		try {
			foreach ($group as $entry) {
				if ($this->isPartTimeStaff((int) ($entry['staff_id'] ?? 0))) {
					return false;
				}
				$fromMin = $this->timeToMinutes((string) ($entry['start_time'] ?? ''));
				$meta = $this->metaForEntry($entry);
				$cat = (string) ($entry['category_title'] ?? $meta['category_title'] ?? '');
				if ($fromMin < (12 * 60) && $destAfternoon && SecondaryTimetableCriteria::isExaminableMainCategory($cat)) {
					return false;
				}
				$classId = (int) ($entry['class_id'] ?? 0);
				$track = (string) ($classTrack[$classId] ?? '');
				$destId = $this->slotIdForStart($schema, $schoolId, $track, $start);
				if ($destId <= 0 || !empty($this->blockedSlot($schema, $schoolId, $track)[$day . ':' . $destId])) {
					return false;
				}
				if ($this->classOverlaps($state, $classId, $day, $startMin, $endMin, $ignore)) {
					return false;
				}
				$staffId = (int) ($entry['staff_id'] ?? 0);
				if ($staffId > 0 && !isset($staffChecked[$staffId])) {
					$staffChecked[$staffId] = true;
					if ($this->staffOverlaps($state, $staffId, $day, $startMin, $endMin, $ignore)) {
						return false;
					}
				}
				if ($this->secondaryCriteria !== null) {
					$meta['_track_key'] = $track;
					$this->secondaryCriteria->setOverflowFill($destAfternoon);
					$allowed = $this->secondaryCriteria->slotAllowed($meta, $day, $start . ':00', $end);
					$this->secondaryCriteria->setOverflowFill(false);
					if (!$allowed) {
						return false;
					}
				}
			}
		} finally {
			if ($this->secondaryCriteria !== null) {
				$this->secondaryCriteria->setOverflowFill(false);
			}
		}
		return true;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function blockedSlot(\App\Models\TimetableSchemaModel $schema, int $schoolId, string $track): array
	{
		if (!isset($this->groupingBlocked[$track])) {
			$this->groupingBlocked[$track] = $schema->specialTimesMap($schoolId, $track);
		}
		return $this->groupingBlocked[$track];
	}

	/**
	 * @param array<string,mixed> $state
	 * @param array<int,true> $ignore
	 */
	private function classOverlaps(array $state, int $classId, int $day, int $start, int $end, array $ignore): bool
	{
		foreach ($state['by_id'] as $other) {
			if ((int) ($other['class_id'] ?? 0) !== $classId || (int) ($other['day_of_week'] ?? -1) !== $day) {
				continue;
			}
			if (isset($ignore[(int) ($other['id'] ?? 0)])) {
				continue;
			}
			$range = $this->entryTimeRange($other);
			if ($range !== null && $start < $range['end'] && $range['start'] < $end) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param array<string,mixed> $state
	 * @param array<int,true> $ignore
	 */
	private function staffOverlaps(array $state, int $staffId, int $day, int $start, int $end, array $ignore): bool
	{
		foreach ($state['staff_time'][$staffId][$day] ?? [] as $row) {
			if (isset($ignore[(int) ($row['id'] ?? 0)])) {
				continue;
			}
			$otherStart = (int) ($row['start'] ?? 0);
			$otherEnd = (int) ($row['end'] ?? 0);
			if ($start < $otherEnd && $otherStart < $end) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param array<string,mixed> $state
	 * @param list<array<string,mixed>> $group
	 * @param array<int,string> $classTrack
	 * @param array<string,mixed> $leader
	 * @param array<string,mixed> $slot
	 */
	private function swapOntoTarget(
		$db,
		array &$state,
		\App\Models\TimetableSchemaModel $schema,
		int $schoolId,
		array $classTrack,
		array $group,
		array $leader,
		int $day,
		array $slot
	): bool {
		$start = substr((string) ($slot['start_time'] ?? ''), 0, 5);
		$moverIds = array_fill_keys($this->groupIds($group), true);
		$occupantIds = [];
		foreach ($group as $member) {
			$occId = $this->occupantAt($state, (int) ($member['class_id'] ?? 0), $day, $start, $moverIds);
			if ($occId <= 0) {
				continue;
			}
			if (isset($moverIds[$occId])) {
				return false;
			}
			$occupantIds[$occId] = true;
		}
		if ($occupantIds === []) {
			return false;
		}
		$occGroup = [];
		$seen = [];
		foreach ($occupantIds as $occId => $unused) {
			$occupant = $state['by_id'][(int) $occId] ?? null;
			if (!is_array($occupant)) {
				return false;
			}
			foreach ($this->sameClockGroup($state, $occupant) as $row) {
				$rowId = (int) ($row['id'] ?? 0);
				if ($rowId <= 0 || isset($seen[$rowId])) {
					continue;
				}
				$seen[$rowId] = true;
				$occGroup[] = $row;
			}
		}
		foreach ($occupantIds as $occId => $unused) {
			if (!isset($seen[(int) $occId])) {
				return false;
			}
		}
		foreach ($occGroup as $row) {
			if (isset($moverIds[(int) ($row['id'] ?? 0)])) {
				return false;
			}
			if ($this->isPartTimeStaff((int) ($row['staff_id'] ?? 0)) || !$this->doubleSwapMovable($row)) {
				return false;
			}
			if ($this->isGroupableExaminable($state, $row) && !$this->courseClockIsolated($state, $row)) {
				return false;
			}
			if ((int) ($row['course_id'] ?? 0) > 0 && (int) ($row['course_id'] ?? 0) === (int) ($leader['course_id'] ?? 0)
				&& (int) ($row['class_id'] ?? 0) === (int) ($leader['class_id'] ?? 0)) {
				return false;
			}
		}
		$oldSlot = [
			'start_time' => (string) ($leader['start_time'] ?? ''),
			'end_time' => (string) ($leader['end_time'] ?? ''),
		];
		$oldDay = (int) ($leader['day_of_week'] ?? -1);
		$ignore = array_values(array_unique(array_merge($this->groupIds($group), $this->groupIds($occGroup))));
		if (!$this->groupMaySitAt($state, $schema, $schoolId, $classTrack, $group, $day, $slot, $ignore)) {
			return false;
		}
		if (!$this->groupMaySitAt($state, $schema, $schoolId, $classTrack, $occGroup, $oldDay, $oldSlot, $ignore)) {
			return false;
		}
		foreach (array_merge($group, $occGroup) as $row) {
			$this->removeScheduledEntry($state, $state['by_id'][(int) ($row['id'] ?? 0)] ?? $row);
		}
		$this->writeGroupAt($db, $state, $schema, $schoolId, $classTrack, $group, $day, $slot, true, true);
		$this->writeGroupAt($db, $state, $schema, $schoolId, $classTrack, $occGroup, $oldDay, $oldSlot, true, false);
		return true;
	}

	/**
	 * The neighbour cell is taken. Move that lesson into any other legal free cell,
	 * then sit this examinable group in the neighbour so the double can form.
	 *
	 * @param array<string,mixed> $state
	 * @param list<array<string,mixed>> $group
	 * @param array<int,string> $classTrack
	 * @param array<string,mixed> $leader
	 * @param array<string,mixed> $slot
	 */
	private function displaceOntoTarget(
		$db,
		array &$state,
		\App\Models\TimetableSchemaModel $schema,
		int $schoolId,
		array $classTrack,
		array $group,
		array $leader,
		int $day,
		array $slot
	): bool {
		$start = substr((string) ($slot['start_time'] ?? ''), 0, 5);
		$moverIds = array_fill_keys($this->groupIds($group), true);
		$occupantIds = [];
		foreach ($group as $member) {
			$occId = $this->occupantAt($state, (int) ($member['class_id'] ?? 0), $day, $start, $moverIds);
			if ($occId <= 0) {
				continue;
			}
			if (isset($moverIds[$occId])) {
				return false;
			}
			$occupantIds[$occId] = true;
		}
		if ($occupantIds === []) {
			return false;
		}
		$occGroup = [];
		$seen = [];
		foreach ($occupantIds as $occId => $unused) {
			$occupant = $state['by_id'][(int) $occId] ?? null;
			if (!is_array($occupant)) {
				return false;
			}
			foreach ($this->sameClockGroup($state, $occupant) as $row) {
				$rowId = (int) ($row['id'] ?? 0);
				if ($rowId <= 0 || isset($seen[$rowId])) {
					continue;
				}
				$seen[$rowId] = true;
				$occGroup[] = $row;
			}
		}
		foreach ($occGroup as $row) {
			if (isset($moverIds[(int) ($row['id'] ?? 0)])) {
				return false;
			}
			if ($this->isPartTimeStaff((int) ($row['staff_id'] ?? 0)) || !$this->doubleSwapMovable($row)) {
				return false;
			}
			if ($this->isGroupableExaminable($state, $row) && !$this->courseClockIsolated($state, $row)) {
				return false;
			}
		}
		$ignore = array_values(array_unique(array_merge($this->groupIds($group), $this->groupIds($occGroup))));
		if (!$this->groupMaySitAt($state, $schema, $schoolId, $classTrack, $group, $day, $slot, $ignore)) {
			return false;
		}
		$occLeader = $occGroup[0];
		$occTrack = (string) ($classTrack[(int) ($occLeader['class_id'] ?? 0)] ?? '');
		$homeDay = (int) ($occLeader['day_of_week'] ?? -1);
		$days = [$homeDay];
		foreach ([0, 1, 2, 3, 4] as $candidateDay) {
			if ($candidateDay !== $homeDay) {
				$days[] = $candidateDay;
			}
		}
		$this->cachedNeighborSlots($schema, $schoolId, $occTrack, substr((string) ($occLeader['start_time'] ?? ''), 0, 5));
		$slots = $this->groupingSlots[$occTrack] ?? [];
		foreach ($days as $freeDay) {
			foreach ($slots as $freeSlot) {
				$freeStart = substr((string) ($freeSlot['start_time'] ?? ''), 0, 5);
				if ($freeDay === $day && $freeStart === $start) {
					continue;
				}
				if ($freeDay === (int) ($leader['day_of_week'] ?? -1)
					&& $freeStart === substr((string) ($leader['start_time'] ?? ''), 0, 5)) {
					continue;
				}
				if (!$this->groupMaySitAt($state, $schema, $schoolId, $classTrack, $occGroup, $freeDay, $freeSlot, $ignore)) {
					continue;
				}
				foreach (array_merge($group, $occGroup) as $row) {
					$this->removeScheduledEntry($state, $state['by_id'][(int) ($row['id'] ?? 0)] ?? $row);
				}
				$this->writeGroupAt($db, $state, $schema, $schoolId, $classTrack, $occGroup, $freeDay, $freeSlot, true, false);
				$this->writeGroupAt($db, $state, $schema, $schoolId, $classTrack, $group, $day, $slot, true, true);
				return true;
			}
		}
		return false;
	}

	/**
	 * Move every lesson that blocks this neighbour (the class already there, and
	 * the same teacher in another class) into some other free cell, then sit the group.
	 *
	 * @param array<string,mixed> $state
	 * @param list<array<string,mixed>> $group
	 * @param array<int,string> $classTrack
	 * @param array<string,mixed> $leader
	 * @param array<string,mixed> $slot
	 */
	private function pullBlockersAndSit(
		$db,
		array &$state,
		\App\Models\TimetableSchemaModel $schema,
		int $schoolId,
		array $classTrack,
		array $group,
		array $leader,
		int $day,
		array $slot
	): bool {
		$start = substr((string) ($slot['start_time'] ?? ''), 0, 5);
		$endMin = $this->timeToMinutes((string) ($slot['end_time'] ?? ''));
		$startMin = $this->timeToMinutes($start);
		if ($start === '' || $endMin <= $startMin) {
			return false;
		}
		$moverIds = array_fill_keys($this->groupIds($group), true);
		$blockerIds = [];
		foreach ($group as $member) {
			$occId = $this->occupantAt($state, (int) ($member['class_id'] ?? 0), $day, $start, $moverIds);
			if ($occId > 0) {
				$blockerIds[$occId] = true;
			}
			$staffId = (int) ($member['staff_id'] ?? 0);
			if ($staffId <= 0) {
				continue;
			}
			foreach ($this->staffTimeConflictIds($state, $staffId, $day, $startMin, $endMin) as $conflictId) {
				if (!isset($moverIds[$conflictId])) {
					$blockerIds[$conflictId] = true;
				}
			}
		}
		if ($blockerIds === []) {
			return false;
		}
		$blockerGroups = [];
		$seen = [];
		foreach ($blockerIds as $blockerId => $unused) {
			if (isset($seen[(int) $blockerId])) {
				continue;
			}
			$blocker = $state['by_id'][(int) $blockerId] ?? null;
			if (!is_array($blocker)) {
				return false;
			}
			$one = [];
			foreach ($this->sameClockGroup($state, $blocker) as $row) {
				$rowId = (int) ($row['id'] ?? 0);
				if ($rowId <= 0 || isset($moverIds[$rowId])) {
					return false;
				}
				if (isset($seen[$rowId])) {
					continue;
				}
				if ($this->isPartTimeStaff((int) ($row['staff_id'] ?? 0)) || !$this->doubleSwapMovable($row)) {
					return false;
				}
				if ($this->isGroupableExaminable($state, $row) && !$this->courseClockIsolated($state, $row)) {
					return false;
				}
				$seen[$rowId] = true;
				$one[] = $row;
			}
			if ($one !== []) {
				$blockerGroups[] = $one;
			}
		}
		if ($blockerGroups === []) {
			return false;
		}
		$ignore = array_values(array_unique(array_merge($this->groupIds($group), array_keys($seen))));
		if (!$this->groupMaySitAt($state, $schema, $schoolId, $classTrack, $group, $day, $slot, $ignore)) {
			return false;
		}
		$plan = [];
		$taken = [$day . '|' . $start => true];
		$leaderDay = (int) ($leader['day_of_week'] ?? -1);
		$leaderStart = substr((string) ($leader['start_time'] ?? ''), 0, 5);
		$taken[$leaderDay . '|' . $leaderStart] = true;
		foreach ($blockerGroups as $blockerGroup) {
			$track = (string) ($classTrack[(int) ($blockerGroup[0]['class_id'] ?? 0)] ?? '');
			$this->cachedNeighborSlots($schema, $schoolId, $track, substr((string) ($blockerGroup[0]['start_time'] ?? ''), 0, 5));
			$placed = null;
			foreach ($this->groupingSlots[$track] ?? [] as $freeSlot) {
				$freeStart = substr((string) ($freeSlot['start_time'] ?? ''), 0, 5);
				foreach ([0, 1, 2, 3, 4] as $freeDay) {
					$key = $freeDay . '|' . $freeStart;
					if (isset($taken[$key])) {
						continue;
					}
					if (!$this->groupMaySitAt($state, $schema, $schoolId, $classTrack, $blockerGroup, $freeDay, $freeSlot, $ignore)) {
						continue;
					}
					$placed = ['day' => $freeDay, 'slot' => $freeSlot, 'key' => $key];
					break 2;
				}
			}
			if ($placed === null) {
				return false;
			}
			$taken[$placed['key']] = true;
			$plan[] = ['group' => $blockerGroup, 'day' => $placed['day'], 'slot' => $placed['slot']];
		}
		$moving = $group;
		foreach ($blockerGroups as $blockerGroup) {
			$moving = array_merge($moving, $blockerGroup);
		}
		foreach ($moving as $row) {
			$this->removeScheduledEntry($state, $state['by_id'][(int) ($row['id'] ?? 0)] ?? $row);
		}
		foreach ($plan as $step) {
			$this->writeGroupAt($db, $state, $schema, $schoolId, $classTrack, $step['group'], (int) $step['day'], $step['slot'], true, false);
		}
		$this->writeGroupAt($db, $state, $schema, $schoolId, $classTrack, $group, $day, $slot, true, true);
		return true;
	}

	/**
	 * @param array<string,mixed> $state
	 * @param array<int,true> $ignore
	 */
	private function occupantAt(array $state, int $classId, int $day, string $start, array $ignore): int
	{
		$startMin = $this->timeToMinutes($start);
		foreach ($state['by_id'] as $other) {
			if ((int) ($other['class_id'] ?? 0) !== $classId || (int) ($other['day_of_week'] ?? -1) !== $day) {
				continue;
			}
			if (isset($ignore[(int) ($other['id'] ?? 0)])) {
				continue;
			}
			if ($this->timeToMinutes((string) ($other['start_time'] ?? '')) === $startMin) {
				return (int) ($other['id'] ?? 0);
			}
		}
		return 0;
	}

	/**
	 * @param array<string,mixed> $state
	 * @param list<array<string,mixed>> $group
	 * @param array<int,string> $classTrack
	 * @param array<string,mixed> $slot
	 */
	private function writeGroupAt(
		$db,
		array &$state,
		\App\Models\TimetableSchemaModel $schema,
		int $schoolId,
		array $classTrack,
		array $group,
		int $day,
		array $slot,
		bool $alreadyRemoved,
		bool $markMoved = true
	): void {
		if (!$alreadyRemoved) {
			foreach ($group as $entry) {
				$current = $state['by_id'][(int) ($entry['id'] ?? 0)] ?? $entry;
				$this->removeScheduledEntry($state, $current);
			}
		}
		$start = substr((string) ($slot['start_time'] ?? ''), 0, 5);
		$end = (string) ($slot['end_time'] ?? '');
		$lockColumn = $db->fieldExists('is_locked', 'timetable_entries');
		foreach ($group as $entry) {
			$id = (int) ($entry['id'] ?? 0);
			$classId = (int) ($entry['class_id'] ?? 0);
			$track = (string) ($classTrack[$classId] ?? '');
			$destId = $this->slotIdForStart($schema, $schoolId, $track, $start);
			$update = [
				'day_of_week' => $day,
				'slot_id' => $destId,
			];
			if ($lockColumn) {
				$update['is_locked'] = 1;
			}
			$db->table('timetable_entries')->where('id', $id)->update($update);
			$entry['day_of_week'] = $day;
			$entry['slot_id'] = $destId;
			$entry['start_time'] = strlen($start) === 5 ? $start . ':00' : $start;
			$entry['end_time'] = $end;
			$entry['is_locked'] = 1;
			if ($markMoved && $id > 0) {
				$this->groupingMovedIds[$id] = true;
			}
			$this->addScheduledEntry($state, $entry);
			$state['by_id'][$id] = $entry;
		}
	}

	/** @param array<string,mixed> $state */
	private function countIsolatedExaminable(array $state): int
	{
		$n = 0;
		foreach ($state['by_id'] as $entry) {
			if ($this->entryIsExaminableMain($entry) && $this->courseClockIsolated($state, $entry)) {
				$n++;
			}
		}
		return $n;
	}

	/**
	 * A main course keeps at most one double on a day. Move the extra periods off that day.
	 *
	 * @param array<string,mixed> $state
	 * @param array<int,string> $classTrack
	 */
	private function unstackOneExaminable(
		$db,
		array &$state,
		\App\Models\TimetableSchemaModel $schema,
		int $schoolId,
		array $classTrack
	): int {
		$byDay = [];
		foreach ($state['by_id'] as $entry) {
			if (!$this->entryIsExaminableMain($entry)) {
				continue;
			}
			$classId = (int) ($entry['class_id'] ?? 0);
			$track = (string) ($classTrack[$classId] ?? '');
			if (!$this->isHighSchoolTrack($track)) {
				continue;
			}
			$key = $classId . ':' . (int) ($entry['course_id'] ?? 0) . ':' . (int) ($entry['day_of_week'] ?? -1);
			$byDay[$key][] = $entry;
		}
		foreach ($byDay as $rows) {
			// A consecutive triple is the odd-hour block. Only peel a fourth period off the day.
			if (count($rows) < 4) {
				continue;
			}
			usort($rows, function (array $a, array $b): int {
				return $this->timeToMinutes((string) ($a['start_time'] ?? ''))
					<=> $this->timeToMinutes((string) ($b['start_time'] ?? ''));
			});
			$keep = [];
			$count = count($rows);
			for ($i = 0; $i < $count - 1; $i++) {
				$end = $this->timeToMinutes((string) ($rows[$i]['end_time'] ?? ''));
				$start = $this->timeToMinutes((string) ($rows[$i + 1]['start_time'] ?? ''));
				if (abs($start - $end) <= 25) {
					$keep[(int) $rows[$i]['id']] = true;
					$keep[(int) $rows[$i + 1]['id']] = true;
					break;
				}
			}
			if ($keep === []) {
				$keep[(int) $rows[0]['id']] = true;
			}
			foreach ($rows as $extra) {
				if (isset($keep[(int) ($extra['id'] ?? 0)])) {
					continue;
				}
				if ($this->moveExtraOffStackedDay($db, $state, $schema, $schoolId, $classTrack, $extra)) {
					return 1;
				}
			}
		}
		return 0;
	}

	/**
	 * @param array<string,mixed> $state
	 * @param array<int,string> $classTrack
	 * @param array<string,mixed> $extra
	 */
	private function moveExtraOffStackedDay(
		$db,
		array &$state,
		\App\Models\TimetableSchemaModel $schema,
		int $schoolId,
		array $classTrack,
		array $extra
	): bool {
		$group = $this->sameClockGroup($state, $extra);
		foreach ($group as $member) {
			if (!$this->entryIsExaminableMain($member)) {
				return false;
			}
			$track = (string) ($classTrack[(int) ($member['class_id'] ?? 0)] ?? '');
			if (!$this->isHighSchoolTrack($track)) {
				return false;
			}
			$onDay = 0;
			foreach ($state['by_id'] as $other) {
				if ((int) ($other['class_id'] ?? 0) !== (int) ($member['class_id'] ?? 0)) {
					continue;
				}
				if ((int) ($other['course_id'] ?? 0) !== (int) ($member['course_id'] ?? 0)) {
					continue;
				}
				if ((int) ($other['day_of_week'] ?? -1) === (int) ($member['day_of_week'] ?? -2)) {
					$onDay++;
				}
			}
			if ($onDay < 3) {
				return false;
			}
		}
		$leader = $group[0];
		foreach ($group as $member) {
			if ((int) ($member['class_id'] ?? 0) < (int) ($leader['class_id'] ?? 0)) {
				$leader = $member;
			}
		}
		if ((int) ($leader['id'] ?? 0) !== (int) ($extra['id'] ?? 0)) {
			return false;
		}
		$track = (string) ($classTrack[(int) ($leader['class_id'] ?? 0)] ?? '');
		$slots = [];
		foreach ($schema->teachingSlots($schoolId, $track) as $slot) {
			if (empty($slot['is_break'])) {
				$slots[] = $slot;
			}
		}
		$ignore = $this->groupIds($group);
		$candidates = [];
		for ($day = 0; $day <= 4; $day++) {
			foreach ($slots as $slot) {
				$start = substr((string) ($slot['start_time'] ?? ''), 0, 5);
				$samePlace = $day === (int) ($leader['day_of_week'] ?? -1)
					&& $start === substr((string) ($leader['start_time'] ?? ''), 0, 5);
				if ($samePlace || !$this->examinableDayRoom($state, $group, $day, $ignore)) {
					continue;
				}
				if (!$this->groupCanUseClock($state, $schema, $schoolId, $classTrack, $group, $day, $slot, $ignore)) {
					continue;
				}
				$score = 1;
				foreach ($group as $member) {
					foreach ($state['by_id'] as $other) {
						if ((int) ($other['class_id'] ?? 0) !== (int) ($member['class_id'] ?? 0)) {
							continue;
						}
						if ((int) ($other['course_id'] ?? 0) !== (int) ($member['course_id'] ?? 0)) {
							continue;
						}
						if ((int) ($other['day_of_week'] ?? -1) !== $day || isset(array_flip($ignore)[(int) ($other['id'] ?? 0)])) {
							continue;
						}
						$gap = min(
							abs($this->timeToMinutes((string) ($other['end_time'] ?? '')) - $this->timeToMinutes((string) ($slot['start_time'] ?? ''))),
							abs($this->timeToMinutes((string) ($slot['end_time'] ?? '')) - $this->timeToMinutes((string) ($other['start_time'] ?? '')))
						);
						if ($gap <= 25) {
							$score = 0;
						}
					}
				}
				$candidates[] = ['day' => $day, 'slot' => $slot, 'score' => $score];
			}
		}
		usort($candidates, static function (array $a, array $b): int {
			return ((int) $a['score']) <=> ((int) $b['score']);
		});
		if ($candidates !== []) {
			$pick = $candidates[0];
			$this->applyGroupMove($db, $state, $schema, $schoolId, $classTrack, $group, (int) $pick['day'], $pick['slot']);
			return true;
		}
		usort($slots, function (array $a, array $b) use ($state, $group): int {
			$score = function (array $slot) use ($state, $group): int {
				$best = 1;
				foreach ($group as $member) {
					foreach ($state['by_id'] as $other) {
						if ((int) ($other['class_id'] ?? 0) !== (int) ($member['class_id'] ?? 0)) {
							continue;
						}
						if ((int) ($other['course_id'] ?? 0) !== (int) ($member['course_id'] ?? 0)) {
							continue;
						}
						$gap = min(
							abs($this->timeToMinutes((string) ($other['end_time'] ?? '')) - $this->timeToMinutes((string) ($slot['start_time'] ?? ''))),
							abs($this->timeToMinutes((string) ($slot['end_time'] ?? '')) - $this->timeToMinutes((string) ($other['start_time'] ?? '')))
						);
						if ($gap <= 25 && (int) ($other['day_of_week'] ?? -1) >= 0) {
							$best = 0;
						}
					}
				}
				return $best;
			};
			return $score($a) <=> $score($b);
		});
		for ($day = 0; $day <= 4; $day++) {
			foreach ($slots as $slot) {
				$start = substr((string) ($slot['start_time'] ?? ''), 0, 5);
				$samePlace = $day === (int) ($leader['day_of_week'] ?? -1)
					&& $start === substr((string) ($leader['start_time'] ?? ''), 0, 5);
				if ($samePlace || !$this->examinableDayRoom($state, $group, $day, $ignore)) {
					continue;
				}
				if ($this->swapGroupIntoSlot($db, $state, $schema, $schoolId, $classTrack, $group, $leader, $day, $slot)) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * @param array<string,mixed> $state
	 * @param array<int,string> $classTrack
	 */
	private function pairOneExaminableDouble(
		$db,
		array &$state,
		\App\Models\TimetableSchemaModel $schema,
		int $schoolId,
		array $classTrack
	): int {
		foreach ($state['by_id'] as $entry) {
			if (!$this->entryIsExaminableMain($entry) || !$this->courseClockIsolated($state, $entry)) {
				continue;
			}
			$group = $this->sameClockGroup($state, $entry);
			$leader = $group[0];
			foreach ($group as $member) {
				if ((int) ($member['class_id'] ?? 0) < (int) ($leader['class_id'] ?? 0)) {
					$leader = $member;
				}
			}
			if ((int) ($leader['id'] ?? 0) !== (int) ($entry['id'] ?? 0)) {
				continue;
			}
			$groupOk = true;
			foreach ($group as $member) {
				if (!$this->entryIsExaminableMain($member) || !$this->courseClockIsolated($state, $member)) {
					$groupOk = false;
					break;
				}
			}
			if (!$groupOk) {
				continue;
			}
			$track = (string) ($classTrack[(int) ($leader['class_id'] ?? 0)] ?? '');
			if (!$this->isHighSchoolTrack($track)) {
				continue;
			}
			$classId = (int) ($leader['class_id'] ?? 0);
			$courseId = (int) ($leader['course_id'] ?? 0);
			$anchors = [];
			foreach ($state['by_id'] as $other) {
				if ((int) ($other['class_id'] ?? 0) !== $classId || (int) ($other['course_id'] ?? 0) !== $courseId) {
					continue;
				}
				if ((int) ($other['id'] ?? 0) === (int) ($leader['id'] ?? 0)) {
					continue;
				}
				if (!$this->courseClockIsolated($state, $other)) {
					continue;
				}
				$anchors[] = $other;
			}
			foreach ($anchors as $anchor) {
				$anchorDay = (int) ($anchor['day_of_week'] ?? -1);
				foreach ($this->neighborTeachingSlots($schema, $schoolId, $track, (string) ($anchor['start_time'] ?? '')) as $slot) {
					$targetStart = substr((string) ($slot['start_time'] ?? ''), 0, 5);
					$moverDay = (int) ($leader['day_of_week'] ?? -1);
					$moverStart = substr((string) ($leader['start_time'] ?? ''), 0, 5);
					if ($anchorDay === $moverDay && $targetStart === $moverStart) {
						continue;
					}
					if (!$this->examinableDayRoom($state, $group, $anchorDay, $this->groupIds($group))) {
						continue;
					}
					$ignore = $this->groupIds($group);
					if ($this->groupCanUseClock($state, $schema, $schoolId, $classTrack, $group, $anchorDay, $slot, $ignore)) {
						$this->applyGroupMove($db, $state, $schema, $schoolId, $classTrack, $group, $anchorDay, $slot);
						return 1;
					}
					if ($this->swapGroupIntoSlot($db, $state, $schema, $schoolId, $classTrack, $group, $leader, $anchorDay, $slot)) {
						return 1;
					}
				}
			}
		}
		return 0;
	}

	/**
	 * @param array<string,mixed> $state
	 * @param list<array<string,mixed>> $group
	 * @param array<int,string> $classTrack
	 * @param array<string,mixed> $leader
	 * @param array<string,mixed> $slot
	 */
	private function swapGroupIntoSlot(
		$db,
		array &$state,
		\App\Models\TimetableSchemaModel $schema,
		int $schoolId,
		array $classTrack,
		array $group,
		array $leader,
		int $day,
		array $slot
	): bool {
		$start = substr((string) ($slot['start_time'] ?? ''), 0, 5);
		$moverIds = array_fill_keys($this->groupIds($group), true);
		$occupantIds = [];
		foreach ($group as $member) {
			$occId = $this->classBusyAtClock($state, (int) ($member['class_id'] ?? 0), $day, $start);
			if ($occId <= 0 || isset($moverIds[$occId])) {
				return false;
			}
			$occupantIds[$occId] = true;
		}
		$firstId = 0;
		foreach ($occupantIds as $occId => $unused) {
			$firstId = (int) $occId;
			break;
		}
		$occupant = $state['by_id'][$firstId] ?? null;
		if (!is_array($occupant)) {
			return false;
		}
		$occGroup = $this->sameClockGroup($state, $occupant);
		foreach ($occupantIds as $occId => $_) {
			$found = false;
			foreach ($occGroup as $row) {
				if ((int) ($row['id'] ?? 0) === (int) $occId) {
					$found = true;
					break;
				}
			}
			if (!$found) {
				return false;
			}
		}
		foreach ($occGroup as $row) {
			if (!$this->doubleSwapMovable($row)) {
				return false;
			}
			if ($this->entryIsExaminableMain($row) && !$this->courseClockIsolated($state, $row)) {
				return false;
			}
		}
		$oldSlot = [
			'start_time' => (string) ($leader['start_time'] ?? ''),
			'end_time' => (string) ($leader['end_time'] ?? ''),
		];
		$oldDay = (int) ($leader['day_of_week'] ?? -1);
		$ignore = array_values(array_unique(array_merge($this->groupIds($group), $this->groupIds($occGroup))));
		if (!$this->groupCanUseClock($state, $schema, $schoolId, $classTrack, $group, $day, $slot, $ignore)) {
			return false;
		}
		if (!$this->groupCanUseClock($state, $schema, $schoolId, $classTrack, $occGroup, $oldDay, $oldSlot, $ignore)) {
			return false;
		}
		if (!$this->examinableDayRoom($state, $group, $day, $this->groupIds($group))) {
			return false;
		}
		$this->applyGroupMove($db, $state, $schema, $schoolId, $classTrack, $group, $day, $slot);
		$this->applyGroupMove($db, $state, $schema, $schoolId, $classTrack, $occGroup, $oldDay, $oldSlot);
		return true;
	}

	/**
	 * Target day must stay at two periods of this course (the double), not three.
	 *
	 * @param array<string,mixed> $state
	 * @param list<array<string,mixed>> $group
	 * @param list<int> $ignoreIds
	 */
	private function examinableDayRoom(array $state, array $group, int $day, array $ignoreIds): bool
	{
		$ignore = array_fill_keys($ignoreIds, true);
		foreach ($group as $entry) {
			$classId = (int) ($entry['class_id'] ?? 0);
			$courseId = (int) ($entry['course_id'] ?? 0);
			$count = 0;
			foreach ($state['by_id'] as $other) {
				if (isset($ignore[(int) ($other['id'] ?? 0)])) {
					continue;
				}
				if ((int) ($other['class_id'] ?? 0) !== $classId || (int) ($other['course_id'] ?? 0) !== $courseId) {
					continue;
				}
				if ((int) ($other['day_of_week'] ?? -1) !== $day) {
					continue;
				}
				$count++;
			}
			if ($count >= 2) {
				return false;
			}
		}
		return true;
	}

	/** @param array<string,mixed> $entry */
	private function entryIsExaminableMain(array $entry): bool
	{
		if ($this->isPartTimeStaff((int) ($entry['staff_id'] ?? 0))) {
			return false;
		}
		$meta = $this->metaForEntry($entry);
		$classId = (int) ($entry['class_id'] ?? 0);
		$courseId = (int) ($entry['course_id'] ?? 0);
		$fallback = $this->classCourseMeta[$classId . ':' . $courseId] ?? [];
		$cat = (string) ($entry['category_title'] ?? $meta['category_title'] ?? $fallback['category_title'] ?? '');
		if (!SecondaryTimetableCriteria::isExaminableMainCategory($cat)) {
			return false;
		}
		$hours = (int) round((float) ($meta['credit'] ?? 0));
		if ($hours < 1) {
			$hours = (int) round((float) ($fallback['credit'] ?? 0));
		}
		if ($hours < 3) {
			return false;
		}
		$title = (string) ($entry['course_title'] ?? $meta['course_title'] ?? '');
		if (SecondaryTimetableCriteria::isOmittedFromGridTitle($title)
			|| TimetableGeneratorService::isPhysicalEducationSportTitle($title)) {
			return false;
		}
		if ($this->secondaryCriteria !== null) {
			if ($this->secondaryCriteria->isClinicalAttachmentCourse($meta)
				|| $this->secondaryCriteria->requiresAfterLessons($meta)
				|| $this->secondaryCriteria->isFixedEveningActivity($meta)
				|| $this->secondaryCriteria->prefersLastHour($meta)) {
				return false;
			}
		}
		return $classId > 0 && $courseId > 0;
	}

	/** @param array<string,mixed> $entry */
	private function doubleSwapMovable(array $entry): bool
	{
		if ($this->isPartTimeStaff((int) ($entry['staff_id'] ?? 0))) {
			return false;
		}
		$title = (string) ($entry['course_title'] ?? '');
		if (SecondaryTimetableCriteria::isOmittedFromGridTitle($title)
			|| TimetableGeneratorService::isPhysicalEducationSportTitle($title)) {
			return false;
		}
		if ($this->secondaryCriteria !== null) {
			$meta = $this->metaForEntry($entry);
			if ($this->secondaryCriteria->requiresAfterLessons($meta)
				|| $this->secondaryCriteria->isFixedEveningActivity($meta)
				|| $this->secondaryCriteria->prefersLastHour($meta)
				|| $this->secondaryCriteria->isClinicalAttachmentCourse($meta)) {
				return false;
			}
		}
		return true;
	}

	/** @param array<string,mixed> $state */
	private function courseClockIsolated(array $state, array $entry): bool
	{
		$classId = (int) ($entry['class_id'] ?? 0);
		$courseId = (int) ($entry['course_id'] ?? 0);
		$day = (int) ($entry['day_of_week'] ?? -1);
		$start = $this->timeToMinutes((string) ($entry['start_time'] ?? ''));
		$end = $this->timeToMinutes((string) ($entry['end_time'] ?? ''));
		if ($classId <= 0 || $courseId <= 0 || $day < 0 || $end <= $start) {
			return false;
		}
		foreach ($state['by_id'] as $other) {
			if ((int) ($other['id'] ?? 0) === (int) ($entry['id'] ?? 0)) {
				continue;
			}
			if ((int) ($other['class_id'] ?? 0) !== $classId || (int) ($other['course_id'] ?? 0) !== $courseId) {
				continue;
			}
			if ((int) ($other['day_of_week'] ?? -1) !== $day) {
				continue;
			}
			$otherStart = $this->timeToMinutes((string) ($other['start_time'] ?? ''));
			$otherEnd = $this->timeToMinutes((string) ($other['end_time'] ?? ''));
			$gap = min(abs($end - $otherStart), abs($otherEnd - $start));
			if ($gap <= 35) {
				return false;
			}
		}
		return true;
	}

	/** @return list<array<string,mixed>> */
	private function neighborTeachingSlots(
		\App\Models\TimetableSchemaModel $schema,
		int $schoolId,
		string $track,
		string $start
	): array {
		$want = substr($start, 0, 5);
		$slots = [];
		foreach ($schema->teachingSlots($schoolId, $track) as $slot) {
			if (!empty($slot['is_break'])) {
				continue;
			}
			$slots[] = $slot;
		}
		usort($slots, function (array $a, array $b): int {
			return $this->timeToMinutes((string) ($a['start_time'] ?? ''))
				<=> $this->timeToMinutes((string) ($b['start_time'] ?? ''));
		});
		$index = null;
		foreach ($slots as $i => $slot) {
			if (substr((string) ($slot['start_time'] ?? ''), 0, 5) === $want) {
				$index = $i;
				break;
			}
		}
		if ($index === null) {
			return [];
		}
		$out = [];
		foreach ([$index - 1, $index + 1] as $j) {
			if (!isset($slots[$j])) {
				continue;
			}
			$a = $slots[$index];
			$b = $slots[$j];
			$aStart = $this->timeToMinutes((string) ($a['start_time'] ?? ''));
			$aEnd = $this->timeToMinutes((string) ($a['end_time'] ?? ''));
			$bStart = $this->timeToMinutes((string) ($b['start_time'] ?? ''));
			$bEnd = $this->timeToMinutes((string) ($b['end_time'] ?? ''));
			$gap = min(abs($aEnd - $bStart), abs($bEnd - $aStart));
			if ($gap <= 25) {
				$out[] = $b;
			}
		}
		return $out;
	}

	/**
	 * @param array<string,mixed> $state
	 * @param array<int,string> $classTrack
	 * @param array<string,list<array<string,mixed>>> $morningByTrack
	 * @param array<string,array<string,mixed>> $clocks
	 */
	private function packMorningDirect(
		$db,
		array &$state,
		\App\Models\TimetableSchemaModel $schema,
		int $schoolId,
		array $classTrack,
		array $morningByTrack,
		array $clocks,
		bool $allowLanguage
	): int {
		$moved = 0;
		foreach ($classTrack as $classId => $track) {
			if (!$this->isHighSchoolTrack($track)) {
				continue;
			}
			foreach ([0, 1, 2, 3, 4] as $day) {
				foreach ($morningByTrack[$track] ?? [] as $slot) {
					$start = substr((string) ($slot['start_time'] ?? ''), 0, 5);
					if ($this->classBusyAtClock($state, (int) $classId, $day, $start)) {
						continue;
					}
					foreach ($this->afternoonLessonsForClass($state, (int) $classId, $allowLanguage) as $lesson) {
						$group = $this->sameClockGroup($state, $lesson);
						if ($this->moveGroupToClock($db, $state, $schema, $schoolId, $classTrack, $group, $day, $clocks[$start] ?? $slot)) {
							$moved++;
							break;
						}
					}
				}
			}
		}
		foreach ($this->fullTimeStaffIds($state) as $staffId) {
			foreach ([0, 1, 2, 3, 4] as $day) {
				foreach ($clocks as $start => $slot) {
					if ($this->staffBusyAtClock($state, $staffId, $day, $start, [])) {
						continue;
					}
					foreach ($this->afternoonLessonsForStaff($state, $staffId, $allowLanguage) as $lesson) {
						$group = $this->sameClockGroup($state, $lesson);
						if ($this->moveGroupToClock($db, $state, $schema, $schoolId, $classTrack, $group, $day, $slot)) {
							$moved++;
							break;
						}
					}
				}
			}
		}
		return $moved;
	}

	/**
	 * The class is already taught at this morning clock by someone else.
	 * Move that lesson into the afternoon and bring this teacher's afternoon
	 * lesson forward, so the class stays full and the free teacher is used.
	 *
	 * @param array<string,mixed> $state
	 * @param array<int,string> $classTrack
	 * @param array<string,array<string,mixed>> $clocks
	 */
	private function packMorningDisplace(
		$db,
		array &$state,
		\App\Models\TimetableSchemaModel $schema,
		int $schoolId,
		array $classTrack,
		array $clocks,
		bool $allowLanguage
	): int {
		$moved = 0;
		foreach ($this->fullTimeStaffIds($state) as $staffId) {
			foreach ([0, 1, 2, 3, 4] as $day) {
				foreach ($clocks as $start => $morningSlot) {
					if ($this->staffBusyAtClock($state, $staffId, $day, $start, [])) {
						continue;
					}
					foreach ($this->afternoonLessonsForStaff($state, $staffId, $allowLanguage) as $lesson) {
						$group = $this->sameClockGroup($state, $lesson);
						$covered = [];
						foreach ($group as $entry) {
							$covered[(int) ($entry['class_id'] ?? 0)] = true;
						}
						$occupantGroups = [];
						$blocked = false;
						foreach ($group as $entry) {
							$classId = (int) ($entry['class_id'] ?? 0);
							$occId = $this->classBusyAtClock($state, $classId, $day, $start);
							if ($occId <= 0) {
								continue;
							}
							$occ = $state['by_id'][$occId] ?? null;
							if (!is_array($occ) || !$this->morningPackMovable($occ, $allowLanguage)) {
								$blocked = true;
								break;
							}
							$occGroup = $this->sameClockGroup($state, $occ);
							foreach ($occGroup as $member) {
								if (!isset($covered[(int) ($member['class_id'] ?? 0)])) {
									$blocked = true;
									break 2;
								}
								if (!$this->morningPackMovable($member, $allowLanguage)) {
									$blocked = true;
									break 2;
								}
							}
							$occupantGroups[(int) ($occ['staff_id'] ?? 0) . '|' . substr((string) ($occ['start_time'] ?? ''), 0, 5)] = $occGroup;
						}
						if ($blocked || $occupantGroups === []) {
							continue;
						}
						$afternoonSlot = [
							'start_time' => (string) ($lesson['start_time'] ?? ''),
							'end_time' => (string) ($lesson['end_time'] ?? ''),
						];
						$afternoonDay = (int) ($lesson['day_of_week'] ?? -1);
						$occIds = [];
						foreach ($occupantGroups as $occGroup) {
							foreach ($this->groupIds($occGroup) as $id) {
								$occIds[] = $id;
							}
						}
						$ignoreBoth = array_merge($occIds, $this->groupIds($group));
						$canLeave = true;
						foreach ($occupantGroups as $occGroup) {
							if (!$this->groupCanUseClock($state, $schema, $schoolId, $classTrack, $occGroup, $afternoonDay, $afternoonSlot, $ignoreBoth)) {
								$canLeave = false;
								break;
							}
						}
						if (!$canLeave || !$this->groupCanUseClock($state, $schema, $schoolId, $classTrack, $group, $day, $morningSlot, $ignoreBoth)) {
							continue;
						}
						$already = false;
						foreach ($ignoreBoth as $packedId) {
							if (isset($this->morningPackedIds[(int) $packedId])) {
								$already = true;
								break;
							}
						}
						if ($already) {
							continue;
						}
						foreach ($occupantGroups as $occGroup) {
							foreach ($occGroup as $occ) {
								$this->removeScheduledEntry($state, $occ);
							}
						}
						$this->applyGroupMove($db, $state, $schema, $schoolId, $classTrack, $group, $day, $morningSlot);
						foreach ($occupantGroups as $occGroup) {
							$this->applyGroupMove($db, $state, $schema, $schoolId, $classTrack, $occGroup, $afternoonDay, $afternoonSlot);
						}
						foreach ($ignoreBoth as $packedId) {
							$this->morningPackedIds[(int) $packedId] = true;
						}
						$moved++;
						break;
					}
				}
			}
		}
		return $moved;
	}

	/**
	 * @param array<string,mixed> $state
	 * @param list<array<string,mixed>> $group
	 * @param array<int,string> $classTrack
	 */
	private function moveGroupToClock(
		$db,
		array &$state,
		\App\Models\TimetableSchemaModel $schema,
		int $schoolId,
		array $classTrack,
		array $group,
		int $day,
		array $slot
	): bool {
		foreach ($group as $entry) {
			if (isset($this->morningPackedIds[(int) ($entry['id'] ?? 0)])) {
				return false;
			}
		}
		if (!$this->groupCanUseClock($state, $schema, $schoolId, $classTrack, $group, $day, $slot, $this->groupIds($group))) {
			return false;
		}
		$this->applyGroupMove($db, $state, $schema, $schoolId, $classTrack, $group, $day, $slot);
		foreach ($group as $entry) {
			$this->morningPackedIds[(int) ($entry['id'] ?? 0)] = true;
		}
		return true;
	}

	/**
	 * @param array<string,mixed> $state
	 * @param list<array<string,mixed>> $group
	 * @param array<int,string> $classTrack
	 * @param list<int> $ignoreIds
	 */
	private function groupCanUseClock(
		array $state,
		\App\Models\TimetableSchemaModel $schema,
		int $schoolId,
		array $classTrack,
		array $group,
		int $day,
		array $slot,
		array $ignoreIds
	): bool {
		if ($group === [] || $day < 0) {
			return false;
		}
		$start = substr((string) ($slot['start_time'] ?? ''), 0, 5);
		$end = (string) ($slot['end_time'] ?? '');
		$ignore = array_fill_keys($ignoreIds, true);
		$staffId = (int) ($group[0]['staff_id'] ?? 0);
		if ($this->staffBusyAtClock($state, $staffId, $day, $start, $ignore)) {
			return false;
		}
		foreach ($group as $entry) {
			if ($this->isPartTimeStaff((int) ($entry['staff_id'] ?? 0))) {
				return false;
			}
			$classId = (int) ($entry['class_id'] ?? 0);
			$track = (string) ($classTrack[$classId] ?? '');
			if (!$this->isHighSchoolTrack($track)) {
				return false;
			}
			$destId = $this->slotIdForStart($schema, $schoolId, $track, $start);
			if ($destId <= 0) {
				return false;
			}
			$occId = $this->classBusyAtClock($state, $classId, $day, $start);
			if ($occId > 0 && !isset($ignore[$occId])) {
				return false;
			}
			if ($this->secondaryCriteria !== null) {
				$meta = $this->metaForEntry($entry);
				if (!$this->secondaryCriteria->slotAllowed($meta, $day, $start . ':00', $end)) {
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * @param array<string,mixed> $state
	 * @param list<array<string,mixed>> $group
	 * @param array<int,string> $classTrack
	 */
	private function applyGroupMove(
		$db,
		array &$state,
		\App\Models\TimetableSchemaModel $schema,
		int $schoolId,
		array $classTrack,
		array $group,
		int $day,
		array $slot
	): void {
		$start = substr((string) ($slot['start_time'] ?? ''), 0, 5);
		$end = (string) ($slot['end_time'] ?? '');
		foreach ($group as $entry) {
			$this->removeScheduledEntry($state, $entry);
		}
		foreach ($group as $entry) {
			$classId = (int) ($entry['class_id'] ?? 0);
			$track = (string) ($classTrack[$classId] ?? '');
			$destId = $this->slotIdForStart($schema, $schoolId, $track, $start);
			$db->table('timetable_entries')->where('id', (int) $entry['id'])->update([
				'day_of_week' => $day,
				'slot_id' => $destId,
			]);
			$entry['day_of_week'] = $day;
			$entry['slot_id'] = $destId;
			$entry['start_time'] = $start . ':00';
			$entry['end_time'] = $end;
			$this->addScheduledEntry($state, $entry);
			$state['by_id'][(int) $entry['id']] = $entry;
		}
	}

	/** @param array<string,mixed> $state @return list<array<string,mixed>> */
	private function sameClockGroup(array $state, array $entry): array
	{
		$group = [$entry];
		$staffId = (int) ($entry['staff_id'] ?? 0);
		$day = (int) ($entry['day_of_week'] ?? -1);
		$start = substr((string) ($entry['start_time'] ?? ''), 0, 5);
		$courseId = (int) ($entry['course_id'] ?? 0);
		foreach ($state['by_id'] as $other) {
			if ((int) ($other['id'] ?? 0) === (int) ($entry['id'] ?? 0)) {
				continue;
			}
			if ((int) ($other['staff_id'] ?? 0) !== $staffId || (int) ($other['day_of_week'] ?? -1) !== $day) {
				continue;
			}
			if (substr((string) ($other['start_time'] ?? ''), 0, 5) !== $start) {
				continue;
			}
			$sameCourse = $courseId > 0 && (int) ($other['course_id'] ?? 0) === $courseId;
			$combined = $this->secondaryCriteria !== null
				&& SecondaryTimetableCriteria::entriesAreCombinedLesson($entry, $other);
			if ($sameCourse || $combined) {
				$group[] = $other;
			}
		}
		return $group;
	}

	/** @param array<string,mixed> $state @return list<array<string,mixed>> */
	private function afternoonLessonsForClass(array $state, int $classId, bool $allowLanguage): array
	{
		$rows = [];
		foreach ($state['by_id'] as $entry) {
			if ((int) ($entry['class_id'] ?? 0) !== $classId || !$this->morningPackMovable($entry, $allowLanguage)) {
				continue;
			}
			if (TimetableGeneratorService::isMorningClock((string) ($entry['start_time'] ?? ''))) {
				continue;
			}
			$rows[] = $entry;
		}
		usort($rows, static function (array $a, array $b): int {
			return TimetableGeneratorService::clockMinutesFromString((string) ($b['start_time'] ?? ''))
				<=> TimetableGeneratorService::clockMinutesFromString((string) ($a['start_time'] ?? ''));
		});
		return $rows;
	}

	/** @param array<string,mixed> $state @return list<array<string,mixed>> */
	private function afternoonLessonsForStaff(array $state, int $staffId, bool $allowLanguage): array
	{
		$rows = [];
		foreach ($state['by_id'] as $entry) {
			if ((int) ($entry['staff_id'] ?? 0) !== $staffId || !$this->morningPackMovable($entry, $allowLanguage)) {
				continue;
			}
			if (TimetableGeneratorService::isMorningClock((string) ($entry['start_time'] ?? ''))) {
				continue;
			}
			$rows[] = $entry;
		}
		usort($rows, static function (array $a, array $b): int {
			return TimetableGeneratorService::clockMinutesFromString((string) ($b['start_time'] ?? ''))
				<=> TimetableGeneratorService::clockMinutesFromString((string) ($a['start_time'] ?? ''));
		});
		return $rows;
	}

	private function morningPackMovable(array $entry, bool $allowLanguage): bool
	{
		$staffId = (int) ($entry['staff_id'] ?? 0);
		if ($this->isPartTimeStaff($staffId)) {
			return false;
		}
		$title = (string) ($entry['course_title'] ?? '');
		if (SecondaryTimetableCriteria::isOmittedFromGridTitle($title)) {
			return false;
		}
		if (!$allowLanguage && SecondaryTimetableCriteria::prefersAfternoonTitle($title)) {
			return false;
		}
		if (TimetableGeneratorService::isPhysicalEducationSportTitle($title)) {
			return false;
		}
		if ($this->secondaryCriteria !== null) {
			$meta = $this->metaForEntry($entry);
			if ($this->secondaryCriteria->requiresAfterLessons($meta)
				|| $this->secondaryCriteria->isFixedEveningActivity($meta)
				|| $this->secondaryCriteria->prefersLastHour($meta)
				|| $this->secondaryCriteria->isClinicalAttachmentCourse($meta)) {
				return false;
			}
		}
		return true;
	}

	/** @param array<string,mixed> $state */
	private function classBusyAtClock(array $state, int $classId, int $day, string $start): int
	{
		$minutes = TimetableGeneratorService::clockMinutesFromString($start);
		return (int) ($state['class_clock'][$classId . ':' . $day . ':' . $minutes] ?? 0);
	}

	/**
	 * @param array<string,mixed> $state
	 * @param array<int,true> $ignore
	 */
	private function staffBusyAtClock(array $state, int $staffId, int $day, string $start, array $ignore): bool
	{
		$minutes = TimetableGeneratorService::clockMinutesFromString($start);
		foreach ($state['staff_time'][$staffId][$day] ?? [] as $row) {
			$id = (int) ($row['id'] ?? 0);
			if (isset($ignore[$id])) {
				continue;
			}
			$otherStart = (int) ($row['start'] ?? 0);
			$otherEnd = (int) ($row['end'] ?? 0);
			if ($minutes < $otherEnd && $otherStart < $minutes + 40) {
				return true;
			}
		}
		return false;
	}

	/** @param array<string,mixed> $state @return list<int> */
	private function fullTimeStaffIds(array $state): array
	{
		$ids = [];
		foreach ($state['by_id'] as $entry) {
			$staffId = (int) ($entry['staff_id'] ?? 0);
			if ($staffId > 0 && !$this->isPartTimeStaff($staffId)) {
				$ids[$staffId] = true;
			}
		}
		return array_map('intval', array_keys($ids));
	}

	/** @param list<array<string,mixed>> $group @return list<int> */
	private function groupIds(array $group): array
	{
		$ids = [];
		foreach ($group as $entry) {
			$id = (int) ($entry['id'] ?? 0);
			if ($id > 0) {
				$ids[] = $id;
			}
		}
		return $ids;
	}

	private function isHighSchoolTrack(string $track): bool
	{
		$phase = \App\Libraries\TimetableTrack::generationPhaseKey($track);
		return $phase === 'high_school';
	}

	/**
	 * Place remaining Manage Course periods into any legal free slot (morning or afternoon).
	 * Never leave leftover while the teacher and class still have a legal hole.
	 */
	public function fillWeeklyPeriodGaps(
		int $scheduleId,
		int $schoolId,
		\App\Models\TimetableSchemaModel $schema
	): int {
		if ($scheduleId <= 0 || $schoolId <= 0) {
			return 0;
		}
		$total = 0;
		for ($pass = 0; $pass < 8; $pass++) {
			$attached = $this->attachParkedCombinedCopies($scheduleId, $schoolId, $schema);
			$direct = $this->placeParkingDirect($scheduleId, $schoolId, $schema, false);
			$relocated = $this->placeParkingDirect($scheduleId, $schoolId, $schema, true);
			$gained = $attached + $direct + $relocated;
			$total += $gained;
			if ($gained === 0) {
				break;
			}
		}
		return $total;
	}

	/**
	 * Version 2 lock. Place every leftover into a cell where that class and that
	 * teacher are both free, including an empty Monday–Thursday 15:40–16:20 row.
	 * A single class lesson may move into that row so the leftover can take its old cell.
	 * Gemini picks among the legal cells when the API key is set. Illegal moves are dropped.
	 */
	public function fillVersion2Gaps(
		int $scheduleId,
		int $schoolId,
		\App\Models\TimetableSchemaModel $schema
	): int {
		if ($scheduleId <= 0 || $schoolId <= 0) {
			return 0;
		}
		$this->version2Overflow = true;
		$placed = $this->applyGeminiOverflowChoices($scheduleId, $schoolId, $schema);
		for ($pass = 0; $pass < 8; $pass++) {
			$gained = $this->placeParkingDirect($scheduleId, $schoolId, $schema, true);
			$placed += $gained;
			if ($gained === 0) {
				break;
			}
		}
		$this->parkAllConflicts($scheduleId, $schoolId);
		$this->version2Overflow = false;
		return $placed;
	}

	/**
	 * @return int placements accepted from Gemini
	 */
	private function applyGeminiOverflowChoices(
		int $scheduleId,
		int $schoolId,
		\App\Models\TimetableSchemaModel $schema
	): int {
		$gemini = new \App\Libraries\GeminiTimetable();
		if (!$gemini->isConfigured()) {
			return 0;
		}
		$db = \Config\Database::connect();
		$settings = $db->table('timetable_settings')->where('school_id', $schoolId)->get(1)->getRowArray();
		$this->timetableSettings = $settings;
		$this->assignmentMeta = $this->loadAssignmentMeta($scheduleId, $schoolId);
		$this->secondaryCriteria = new SecondaryTimetableCriteria();
		$this->secondaryCriteria->hydrateFromAssignments(array_values($this->assignmentMeta));
		$this->secondaryCriteria->hydrateCustomRules((new TimetableCriteriaStore())->listForSchool($schoolId, true));
		$this->secondaryCriteria->setOverflowFill(true);

		$scheduled = $this->scheduledEntries($scheduleId, $schoolId);
		$state = $this->buildScheduleState($scheduled);
		$days = \App\Models\TimetableSchemaModel::weekDaysFromSettings($settings);
		$parking = $db->table('timetable_entries te')
			->select('te.*, c.title AS course_title, cl.title AS class_title, l.title AS level_name,
				d.code AS dept_code, CONCAT(s.fname, " ", s.lname) AS teacher_name')
			->join('courses c', 'c.id = te.course_id', 'left')
			->join('classes cl', 'cl.id = te.class_id', 'left')
			->join('levels l', 'l.id = cl.level', 'left')
			->join('departments d', 'd.id = cl.department', 'left')
			->join('staffs s', 's.id = te.staff_id', 'left')
			->where('te.schedule_id', $scheduleId)
			->where('te.school_id', $schoolId)
			->where('te.entry_type', 'lesson')
			->where('te.day_of_week', -1)
			->where('te.slot_id', 0)
			->orderBy('te.id')
			->get()->getResultArray();
		if ($parking === []) {
			return 0;
		}

		$pending = [];
		$allowed = [];
		foreach (array_slice($parking, 0, 40) as $entry) {
			$options = [];
			foreach ($this->candidateSlots($entry, $days, $schema, $schoolId, $state, false, true) as $candidate) {
				if (($candidate['blockers'] ?? []) !== []) {
					continue;
				}
				$day = (int) $candidate['day'];
				$slotId = (int) $candidate['slot_id'];
				$options[] = ['day' => $day, 'slot_id' => $slotId];
				$allowed[(int) $entry['id']][$day . ':' . $slotId] = true;
				if (count($options) >= 8) {
					break;
				}
			}
			if ($options === []) {
				continue;
			}
			$pending[] = [
				'entry_id' => (int) $entry['id'],
				'course' => (string) ($entry['course_title'] ?? ''),
				'class_id' => (int) ($entry['class_id'] ?? 0),
				'staff_id' => (int) ($entry['staff_id'] ?? 0),
				'options' => $options,
			];
		}
		if ($pending === []) {
			return 0;
		}

		$choices = $gemini->choosePlacements($pending, [
			'rules' => 'Version 2. One class period, one teacher. Combined courses may share a clock. Do not invent slots.',
		]);
		$used = [];
		$placed = 0;
		$byId = [];
		foreach ($parking as $entry) {
			$byId[(int) $entry['id']] = $entry;
		}
		foreach ($choices as $choice) {
			$entryId = (int) ($choice['entry_id'] ?? 0);
			$day = (int) ($choice['day'] ?? -1);
			$slotId = (int) ($choice['slot_id'] ?? 0);
			if (empty($allowed[$entryId][$day . ':' . $slotId]) || empty($byId[$entryId])) {
				continue;
			}
			$entry = $byId[$entryId];
			$classId = (int) ($entry['class_id'] ?? 0);
			$staffId = (int) ($entry['staff_id'] ?? 0);
			$slotKey = $day . ':' . $slotId;
			if (isset($used['c' . $classId . ':' . $slotKey]) || ($staffId > 0 && isset($used['s' . $staffId . ':' . $slotKey]))) {
				continue;
			}
			if (!empty($state['class_busy'][$classId . ':' . $slotKey])) {
				continue;
			}
			if ($staffId > 0 && !empty($state['staff_busy'][$staffId . ':' . $slotKey])) {
				continue;
			}
			$db->table('timetable_entries')->where('id', $entryId)->update([
				'day_of_week' => $day,
				'slot_id' => $slotId,
			]);
			$entry['day_of_week'] = $day;
			$entry['slot_id'] = $slotId;
			$this->addScheduledEntry($state, $entry);
			$used['c' . $classId . ':' . $slotKey] = true;
			if ($staffId > 0) {
				$used['s' . $staffId . ':' . $slotKey] = true;
			}
			$placed++;
		}
		return $placed;
	}

	/**
	 * @param array<string,mixed> $blocker
	 * @param array<string,mixed> $state
	 */
	private function blockerMustStay(array $blocker, array $state): bool
	{
		$meta = $this->metaForEntry($blocker);
		if ($this->secondaryCriteria !== null && (
			$this->secondaryCriteria->isClinicalAttachmentCourse($meta)
			|| $this->secondaryCriteria->isFixedEveningActivity($meta)
			|| $this->secondaryCriteria->requiresAfterLessons($meta)
		)) {
			return true;
		}
		$self = (int) ($blocker['id'] ?? 0);
		$staffId = (int) ($blocker['staff_id'] ?? 0);
		$day = (int) ($blocker['day_of_week'] ?? -1);
		foreach ($state['by_id'] as $other) {
			if ((int) ($other['id'] ?? 0) === $self || (int) ($other['staff_id'] ?? 0) !== $staffId) {
				continue;
			}
			if ((int) ($other['day_of_week'] ?? -1) !== $day) {
				continue;
			}
			if (!SecondaryTimetableCriteria::entriesAreCombinedLesson($blocker, $other)) {
				continue;
			}
			$a = $this->entryTimeRange($blocker);
			$b = $this->entryTimeRange($other);
			if ($a !== null && $b !== null && $a['start'] < $b['end'] && $b['start'] < $a['end']) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Keep draining parking after collision cleanup so leftover cannot beat a free teacher cell.
	 */
	public function hardenLeftoverPlacement(
		int $scheduleId,
		int $schoolId,
		\App\Models\TimetableSchemaModel $schema
	): int {
		$placed = $this->fillWeeklyPeriodGaps($scheduleId, $schoolId, $schema);
		$placed += $this->autoPlaceStaging($scheduleId, $schoolId, $schema, 0, 0, true);
		$placed += $this->fillWeeklyPeriodGaps($scheduleId, $schoolId, $schema);
		$placed += $this->fillVersion2Gaps($scheduleId, $schoolId, $schema);
		return $placed;
	}

	/**
	 * Put a parked combined partner onto the same clock as its already-placed partner.
	 */
	public function attachParkedCombinedCopies(
		int $scheduleId,
		int $schoolId,
		\App\Models\TimetableSchemaModel $schema
	): int {
		if ($scheduleId <= 0 || $schoolId <= 0) {
			return 0;
		}
		$db = \Config\Database::connect();
		$settings = $db->table('timetable_settings')->where('school_id', $schoolId)->get(1)->getRowArray();
		$this->timetableSettings = $settings;
		$this->assignmentMeta = $this->loadAssignmentMeta($scheduleId, $schoolId);
		$this->secondaryCriteria = new SecondaryTimetableCriteria();
		$this->secondaryCriteria->hydrateFromAssignments(array_values($this->assignmentMeta));
		$this->secondaryCriteria->hydrateCustomRules((new TimetableCriteriaStore())->listForSchool($schoolId, true));

		$scheduled = $this->scheduledEntries($scheduleId, $schoolId);
		$state = $this->buildScheduleState($scheduled);
		$parking = $db->table('timetable_entries te')
			->select('te.*, c.title AS course_title, cl.title AS class_title, l.title AS level_name,
				d.code AS dept_code, CONCAT(s.fname, " ", s.lname) AS teacher_name')
			->join('courses c', 'c.id = te.course_id', 'left')
			->join('classes cl', 'cl.id = te.class_id', 'left')
			->join('levels l', 'l.id = cl.level', 'left')
			->join('departments d', 'd.id = cl.department', 'left')
			->join('staffs s', 's.id = te.staff_id', 'left')
			->where('te.schedule_id', $scheduleId)
			->where('te.school_id', $schoolId)
			->where('te.entry_type', 'lesson')
			->where('te.day_of_week', -1)
			->where('te.slot_id', 0)
			->orderBy('te.id')
			->get()->getResultArray();
		if ($parking === []) {
			return 0;
		}

		$placed = 0;
		foreach ($parking as $entry) {
			$classId = (int) ($entry['class_id'] ?? 0);
			$staffId = (int) ($entry['staff_id'] ?? 0);
			if ($classId <= 0 || $staffId <= 0) {
				continue;
			}
			$host = $this->combinedHostClock($entry, $scheduled, $state, $schema, $schoolId);
			if ($host === null) {
				continue;
			}
			$db->table('timetable_entries')->where('id', (int) $entry['id'])->update([
				'day_of_week' => $host['day'],
				'slot_id' => $host['slot_id'],
				'is_locked' => 1,
			]);
			$entry['day_of_week'] = $host['day'];
			$entry['slot_id'] = $host['slot_id'];
			$entry['start_time'] = $host['start_time'] ?? ($entry['start_time'] ?? '');
			$entry['end_time'] = $host['end_time'] ?? ($entry['end_time'] ?? '');
			$this->addScheduledEntry($state, $entry);
			$scheduled[] = $entry;
			$placed++;
		}
		return $placed;
	}

	/**
	 * @param array<string,mixed> $entry
	 * @param list<array<string,mixed>> $scheduled
	 * @param array<string,mixed> $state
	 * @return array{day:int,slot_id:int,start_time?:string,end_time?:string}|null
	 */
	private function combinedHostClock(
		array $entry,
		array $scheduled,
		array $state,
		\App\Models\TimetableSchemaModel $schema,
		int $schoolId
	): ?array {
		$classId = (int) ($entry['class_id'] ?? 0);
		$moving = $this->combineCheckRow($entry);
		$trackKey = $schema->trackForClass($schoolId, $classId);
		$blocked = $schema->specialTimesMap($schoolId, $trackKey);
		foreach ($scheduled as $host) {
			if ((int) ($host['staff_id'] ?? 0) !== (int) ($entry['staff_id'] ?? 0)) {
				continue;
			}
			if ((int) ($host['class_id'] ?? 0) === $classId) {
				continue;
			}
			$day = (int) ($host['day_of_week'] ?? -1);
			$slotId = (int) ($host['slot_id'] ?? 0);
			if ($day < 0 || $slotId <= 0) {
				continue;
			}
			if (!SecondaryTimetableCriteria::entriesAreCombinedLesson($moving, $this->combineCheckRow($host))) {
				continue;
			}
			$start = (string) ($host['start_time'] ?? '');
			$end = (string) ($host['end_time'] ?? '');
			$guestSlot = $this->slotIdForStart($schema, $schoolId, $trackKey, $start);
			if ($guestSlot <= 0) {
				continue;
			}
			$key = $day . ':' . $guestSlot;
			if (!empty($blocked[$key])) {
				continue;
			}
			if (!empty($state['class_busy'][$classId . ':' . $key])) {
				continue;
			}
			$startMin = $this->timeToMinutes(substr($start, 0, 5));
			if (!empty($state['class_clock'][$classId . ':' . $day . ':' . $startMin])) {
				continue;
			}
			if ($this->secondaryCriteria !== null) {
				$meta = $this->metaForEntry($entry);
				$meta['_track_key'] = $trackKey;
				if (!$this->secondaryCriteria->slotAllowed($meta, $day, $start, $end)) {
					continue;
				}
			}
			return [
				'day' => $day,
				'slot_id' => $guestSlot,
				'start_time' => $start,
				'end_time' => $end,
			];
		}
		return null;
	}

	private function placeParkingDirect(
		int $scheduleId,
		int $schoolId,
		\App\Models\TimetableSchemaModel $schema,
		bool $allowRelocate
	): int {
		$db = \Config\Database::connect();
		$settings = $db->table('timetable_settings')->where('school_id', $schoolId)->get(1)->getRowArray();
		$this->timetableSettings = $settings;
		$this->assignmentMeta = $this->loadAssignmentMeta($scheduleId, $schoolId);
		$this->secondaryCriteria = new SecondaryTimetableCriteria();
		$this->secondaryCriteria->hydrateFromAssignments(array_values($this->assignmentMeta));
		$this->secondaryCriteria->hydrateCustomRules((new TimetableCriteriaStore())->listForSchool($schoolId, true));
		if ($this->version2Overflow) {
			$this->secondaryCriteria->setOverflowFill(true);
		}

		$days = \App\Models\TimetableSchemaModel::weekDaysFromSettings($settings);
		$scheduled = $this->scheduledEntries($scheduleId, $schoolId);
		$state = $this->buildScheduleState($scheduled);
		$parking = $db->table('timetable_entries te')
			->select('te.*, c.title AS course_title, cl.title AS class_title, l.title AS level_name,
				d.code AS dept_code, CONCAT(s.fname, " ", s.lname) AS teacher_name')
			->join('courses c', 'c.id = te.course_id', 'left')
			->join('classes cl', 'cl.id = te.class_id', 'left')
			->join('levels l', 'l.id = cl.level', 'left')
			->join('departments d', 'd.id = cl.department', 'left')
			->join('staffs s', 's.id = te.staff_id', 'left')
			->where('te.schedule_id', $scheduleId)
			->where('te.school_id', $schoolId)
			->where('te.entry_type', 'lesson')
			->where('te.day_of_week', -1)
			->where('te.slot_id', 0)
			->orderBy('te.id')
			->get()->getResultArray();
		if ($parking === []) {
			return 0;
		}

		$scheduledByKey = [];
		foreach ($scheduled as $row) {
			$key = $this->keyFromEntry($row);
			$scheduledByKey[$key] = ($scheduledByKey[$key] ?? 0) + 1;
		}

		$placed = 0;
		foreach ($parking as $entry) {
			$key = $this->keyFromEntry($entry);
			$meta = $this->metaForEntry($entry);
			$needed = TimetableGeneratorService::weeklyHoursFromCourse($meta);
			$have = (int) ($scheduledByKey[$key] ?? 0);
			if ($needed > 0 && $have >= $needed) {
				$db->table('timetable_entries')->where('id', (int) $entry['id'])->delete();
				continue;
			}
			$found = $this->findBestDirectPlacement($entry, $days, $schema, $schoolId, $state, true);
			if ($found === null && $allowRelocate) {
				$found = $this->placeByRelocatingOneBlocker($db, $entry, $days, $schema, $schoolId, $state);
			}
			if ($found === null) {
				continue;
			}
			if ($this->placingCombinedWouldExceed($entry, (int) $found['day'], (int) $found['slot_id'], $scheduled)) {
				continue;
			}
			$lock = $this->secondaryCriteria !== null && (
				$this->secondaryCriteria->isCombinedAssignment($meta)
				|| $this->secondaryCriteria->requiresAfterLessons($meta)
				|| TimetableGeneratorService::isPhysicalEducationSportTitle((string) ($meta['course_title'] ?? ''))
			);
			$update = [
				'day_of_week' => $found['day'],
				'slot_id' => $found['slot_id'],
			];
			if ($lock && $db->fieldExists('is_locked', 'timetable_entries')) {
				$update['is_locked'] = 1;
			}
			$db->table('timetable_entries')->where('id', (int) $entry['id'])->update($update);
			$entry['day_of_week'] = $found['day'];
			$entry['slot_id'] = $found['slot_id'];
			$this->addScheduledEntry($state, $entry);
			$scheduled = $this->rememberPlacedClock($scheduled, $entry, (int) $found['day'], (int) $found['slot_id']);
			$scheduledByKey[$key] = $have + 1;
			$placed++;
		}
		return $placed;
	}

	/**
	 * Move PE onto 15:00–15:40 (swap the occupant to an earlier free cell).
	 */
	public function promotePeToLastHour(
		int $scheduleId,
		int $schoolId,
		\App\Models\TimetableSchemaModel $schema
	): int {
		if ($scheduleId <= 0 || $schoolId <= 0) {
			return 0;
		}
		$db = \Config\Database::connect();
		$settings = $db->table('timetable_settings')->where('school_id', $schoolId)->get(1)->getRowArray();
		$this->timetableSettings = $settings;
		$this->assignmentMeta = $this->loadAssignmentMeta($scheduleId, $schoolId);
		$this->secondaryCriteria = new SecondaryTimetableCriteria();
		$this->secondaryCriteria->hydrateFromAssignments(array_values($this->assignmentMeta));
		$this->secondaryCriteria->hydrateCustomRules((new TimetableCriteriaStore())->listForSchool($schoolId, true));

		$scheduled = $this->scheduledEntries($scheduleId, $schoolId);
		$state = $this->buildScheduleState($scheduled);
		$parking = $db->table('timetable_entries te')
			->select('te.*, c.title AS course_title')
			->join('courses c', 'c.id = te.course_id', 'left')
			->where('te.schedule_id', $scheduleId)
			->where('te.school_id', $schoolId)
			->where('te.entry_type', 'lesson')
			->where('te.day_of_week', -1)
			->where('te.slot_id', 0)
			->orderBy('te.id')
			->get()->getResultArray();

		$peRows = [];
		foreach (array_merge($scheduled, $parking) as $row) {
			$meta = $this->metaForEntry($row);
			$title = (string) ($meta['course_title'] ?? $row['course_title'] ?? '');
			if (TimetableGeneratorService::isPhysicalEducationSportTitle($title)) {
				$peRows[] = $row;
			}
		}

		$moved = 0;
		foreach ($peRows as $pe) {
			$start = (string) ($pe['start_time'] ?? '');
			$end = (string) ($pe['end_time'] ?? '');
			if ((int) ($pe['day_of_week'] ?? -1) >= 0 && (int) ($pe['slot_id'] ?? 0) > 0
				&& \App\Models\TimetableSchemaModel::isFinalTeachingPeriodSlotTimes($start, $end)) {
				continue;
			}
			if ($this->promoteOnePeEntry($db, $pe, $schema, $schoolId, $state)) {
				$moved++;
			}
		}
		return $moved;
	}

	/**
	 * @param array<string,mixed> $state
	 */
	private function promoteOnePeEntry(
		\CodeIgniter\Database\BaseConnection $db,
		array $pe,
		\App\Models\TimetableSchemaModel $schema,
		int $schoolId,
		array &$state
	): bool {
		$classId = (int) ($pe['class_id'] ?? 0);
		$staffId = (int) ($pe['staff_id'] ?? 0);
		$peId = (int) ($pe['id'] ?? 0);
		if ($classId <= 0 || $peId <= 0) {
			return false;
		}
		$trackKey = $schema->trackForClass($schoolId, $classId);
		$days = \App\Models\TimetableSchemaModel::weekDaysForTrack($this->timetableSettings, $trackKey);
		$slots = array_values(array_filter(
			$schema->generationSlots($schoolId, $trackKey),
			static fn ($s) => empty($s['is_break'])
		));
		$blocked = $schema->specialTimesMap($schoolId, $trackKey);
		$peMeta = $this->metaForEntry($pe);
		$peMeta['_track_key'] = $trackKey;
		$targets = [];
		$overflow = [];
		foreach ($days as $day) {
			foreach ($slots as $slot) {
				$slotId = (int) ($slot['id'] ?? 0);
				$start = (string) ($slot['start_time'] ?? '');
				$end = (string) ($slot['end_time'] ?? '');
				$row = ['day' => (int) $day, 'slot' => $slot];
				if (\App\Models\TimetableSchemaModel::isFinalTeachingPeriodSlotTimes($start, $end)) {
					$targets[] = $row;
				} elseif (\App\Models\TimetableSchemaModel::isLastTeachingHourSlotTimes($start, $end)) {
					$overflow[] = $row;
				}
			}
		}
		foreach (array_merge($targets, $overflow) as $target) {
			$day = (int) $target['day'];
			$slot = $target['slot'];
			$slotId = (int) ($slot['id'] ?? 0);
			$key = $day . ':' . $slotId;
			if ($slotId <= 0 || !empty($blocked[$key])) {
				continue;
			}
			if (!$this->secondaryCriteria->slotAllowed(
				$peMeta,
				$day,
				(string) ($slot['start_time'] ?? ''),
				(string) ($slot['end_time'] ?? '')
			)) {
				continue;
			}
			if ((int) ($pe['day_of_week'] ?? -1) === $day && (int) ($pe['slot_id'] ?? 0) === $slotId) {
				continue;
			}
			$staffBlocker = $staffId > 0 ? (int) ($state['staff_busy'][$staffId . ':' . $key] ?? 0) : 0;
			if ($staffBlocker > 0 && $staffBlocker !== $peId) {
				continue;
			}
			$classBlocker = (int) ($state['class_busy'][$classId . ':' . $key] ?? 0);
			if ($classBlocker <= 0 || $classBlocker === $peId) {
				$this->applyEntryMove($db, $state, $pe, $day, $slot);
				$pe['day_of_week'] = $day;
				$pe['slot_id'] = $slotId;
				return true;
			}
			$occupant = $state['by_id'][$classBlocker] ?? null;
			if (!is_array($occupant)) {
				continue;
			}
			$occMeta = $this->metaForEntry($occupant);
			$occTitle = (string) ($occMeta['course_title'] ?? $occupant['course_title'] ?? '');
			if (TimetableGeneratorService::isPhysicalEducationSportTitle($occTitle)
				|| $this->secondaryCriteria->requiresAfterLessons($occMeta)) {
				continue;
			}
			$this->removeScheduledEntry($state, $occupant);
			if ((int) ($pe['day_of_week'] ?? -1) >= 0 && (int) ($pe['slot_id'] ?? 0) > 0) {
				$this->removeScheduledEntry($state, $pe);
			}
			$state['class_busy'][$classId . ':' . $key] = $peId;
			if ($staffId > 0) {
				$state['staff_busy'][$staffId . ':' . $key] = $peId;
			}
			$relocation = $this->findBestDirectPlacement($occupant, $days, $schema, $schoolId, $state, true);
			unset($state['class_busy'][$classId . ':' . $key], $state['staff_busy'][$staffId . ':' . $key]);
			if ($relocation === null) {
				$this->addScheduledEntry($state, $occupant);
				if ((int) ($pe['day_of_week'] ?? -1) >= 0 && (int) ($pe['slot_id'] ?? 0) > 0) {
					$this->addScheduledEntry($state, $pe);
				}
				continue;
			}
			$escapeSlot = null;
			foreach ($slots as $s) {
				if ((int) ($s['id'] ?? 0) === (int) $relocation['slot_id']) {
					$escapeSlot = $s;
					break;
				}
			}
			if ($escapeSlot === null) {
				$this->addScheduledEntry($state, $occupant);
				if ((int) ($pe['day_of_week'] ?? -1) >= 0 && (int) ($pe['slot_id'] ?? 0) > 0) {
					$this->addScheduledEntry($state, $pe);
				}
				continue;
			}
			$db->table('timetable_entries')->where('id', (int) $occupant['id'])->update([
				'day_of_week' => (int) $relocation['day'],
				'slot_id' => (int) $relocation['slot_id'],
			]);
			$occupant['day_of_week'] = (int) $relocation['day'];
			$occupant['slot_id'] = (int) $relocation['slot_id'];
			$occupant['start_time'] = (string) ($escapeSlot['start_time'] ?? '');
			$occupant['end_time'] = (string) ($escapeSlot['end_time'] ?? '');
			$this->addScheduledEntry($state, $occupant);
			$db->table('timetable_entries')->where('id', $peId)->update([
				'day_of_week' => $day,
				'slot_id' => $slotId,
				'is_locked' => 1,
			]);
			$pe['day_of_week'] = $day;
			$pe['slot_id'] = $slotId;
			$pe['start_time'] = (string) ($slot['start_time'] ?? '');
			$pe['end_time'] = (string) ($slot['end_time'] ?? '');
			$pe['is_locked'] = 1;
			$this->addScheduledEntry($state, $pe);
			return true;
		}
		return false;
	}

	/**
	 * @param array<string,mixed> $state
	 * @param array<string,mixed> $slot
	 */
	private function applyEntryMove(
		\CodeIgniter\Database\BaseConnection $db,
		array &$state,
		array $entry,
		int $day,
		array $slot
	): void {
		$slotId = (int) ($slot['id'] ?? 0);
		$entryId = (int) ($entry['id'] ?? 0);
		if ($entryId <= 0 || $slotId <= 0) {
			return;
		}
		if ((int) ($entry['day_of_week'] ?? -1) >= 0 && (int) ($entry['slot_id'] ?? 0) > 0) {
			$this->removeScheduledEntry($state, $entry);
		}
		$db->table('timetable_entries')->where('id', $entryId)->update([
			'day_of_week' => $day,
			'slot_id' => $slotId,
			'is_locked' => 1,
		]);
		$entry['day_of_week'] = $day;
		$entry['slot_id'] = $slotId;
		$entry['start_time'] = (string) ($slot['start_time'] ?? $entry['start_time'] ?? '');
		$entry['end_time'] = (string) ($slot['end_time'] ?? $entry['end_time'] ?? '');
		$entry['is_locked'] = 1;
		$this->addScheduledEntry($state, $entry);
	}

	/**
	 * @param list<array<string,mixed>> $parked
	 * @param list<array<string,mixed>> $placed
	 * @param array<string,mixed> $state
	 * @return array{id:int,from:string,entry:array<string,mixed>}|null
	 */
	private function fillOneMorningSlot(
		\CodeIgniter\Database\BaseConnection $db,
		int $day,
		array $slot,
		array $parked,
		array $placed,
		array &$state
	): ?array {
		$slotId = (int) ($slot['id'] ?? 0);
		$parkedOrder = $parked;
		usort($parkedOrder, function (array $a, array $b) use ($state, $day): int {
			$aNew = $this->dayHasCourse($state, $a, $day) ? 1 : 0;
			$bNew = $this->dayHasCourse($state, $b, $day) ? 1 : 0;
			if ($aNew !== $bNew) {
				return $aNew <=> $bNew;
			}
			return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
		});
		foreach ($parkedOrder as $entry) {
			$parkMeta = $this->metaForEntry($entry);
			$parkPe = TimetableGeneratorService::isPhysicalEducationSportTitle((string) ($parkMeta['course_title'] ?? ''));
			$parkAfter = $this->secondaryCriteria !== null && $this->secondaryCriteria->requiresAfterLessons($parkMeta);
			$parkLast = $parkPe || ($this->secondaryCriteria !== null && $this->secondaryCriteria->prefersLastHour($parkMeta));
			if ($parkLast || $parkAfter) {
				continue;
			}
			if (!$this->entryMayOccupySlot($entry, $day, $slot, $state)) {
				continue;
			}
			$db->table('timetable_entries')->where('id', (int) $entry['id'])->update([
				'day_of_week' => $day,
				'slot_id' => $slotId,
			]);
			$entry['day_of_week'] = $day;
			$entry['slot_id'] = $slotId;
			$entry['start_time'] = (string) ($slot['start_time'] ?? '');
			$entry['end_time'] = (string) ($slot['end_time'] ?? '');
			$this->addScheduledEntry($state, $entry);
			return ['id' => (int) $entry['id'], 'from' => 'parking', 'entry' => $entry];
		}

		$movers = $placed;
		usort($movers, function (array $a, array $b): int {
			$aPe = TimetableGeneratorService::isPhysicalEducationSportTitle((string) ($this->metaForEntry($a)['course_title'] ?? '')) ? 1 : 0;
			$bPe = TimetableGeneratorService::isPhysicalEducationSportTitle((string) ($this->metaForEntry($b)['course_title'] ?? '')) ? 1 : 0;
			if ($aPe !== $bPe) {
				return $aPe <=> $bPe;
			}
			return TimetableGeneratorService::clockMinutesFromString((string) ($b['start_time'] ?? '00:00'))
				<=> TimetableGeneratorService::clockMinutesFromString((string) ($a['start_time'] ?? '00:00'));
		});
		foreach ($movers as $entry) {
			if ($this->entryIsLocked($entry)) {
				continue;
			}
			$fromDay = (int) ($entry['day_of_week'] ?? -1);
			$fromSlot = (int) ($entry['slot_id'] ?? 0);
			if ($fromDay < 0 || $fromSlot <= 0) {
				continue;
			}
			if (TimetableGeneratorService::isMorningClock((string) ($entry['start_time'] ?? ''))) {
				continue;
			}
			$meta = $this->metaForEntry($entry);
			$lastHour = TimetableGeneratorService::isPhysicalEducationSportTitle((string) ($meta['course_title'] ?? ''))
				|| ($this->secondaryCriteria !== null && $this->secondaryCriteria->prefersLastHour($meta));
			$afterLessons = $this->secondaryCriteria !== null && $this->secondaryCriteria->requiresAfterLessons($meta);
			$customLabel = (string) ($entry['custom_label'] ?? '');
			$homework = NurseryTimetableCriteria::isNurseryRow($meta) && (
				NurseryTimetableCriteria::isHomeworkCourse((string) ($meta['course_title'] ?? ''))
				|| NurseryTimetableCriteria::isHomeworkCourse($customLabel)
				|| NurseryTimetableCriteria::slotOverlapsHomeworkWindow(
					(string) ($entry['start_time'] ?? ''),
					(string) ($entry['end_time'] ?? '')
				)
			);
			if ($lastHour || $afterLessons || $homework) {
				continue;
			}
			$this->removeScheduledEntry($state, $entry);
			if (!$this->entryMayOccupySlot($entry, $day, $slot, $state)) {
				$this->addScheduledEntry($state, $entry);
				continue;
			}
			$db->table('timetable_entries')->where('id', (int) $entry['id'])->update([
				'day_of_week' => $day,
				'slot_id' => $slotId,
			]);
			$entry['day_of_week'] = $day;
			$entry['slot_id'] = $slotId;
			$entry['start_time'] = (string) ($slot['start_time'] ?? '');
			$entry['end_time'] = (string) ($slot['end_time'] ?? '');
			$this->addScheduledEntry($state, $entry);
			return ['id' => (int) $entry['id'], 'from' => 'afternoon', 'entry' => $entry];
		}

		return null;
	}

	/**
	 * @param array<string,mixed> $entry
	 * @param array<string,mixed> $slot
	 * @param array<string,mixed> $state
	 */
	private function entryMayOccupySlot(array $entry, int $day, array $slot, array $state): bool
	{
		$classId = (int) ($entry['class_id'] ?? 0);
		$staffId = (int) ($entry['staff_id'] ?? 0);
		$slotId = (int) ($slot['id'] ?? 0);
		if ($this->isPartTimeStaff($staffId)) {
			return false;
		}
		$title = (string) ($entry['course_title'] ?? '');
		if ($title === '' && $this->secondaryCriteria !== null) {
			$title = (string) ($this->metaForEntry($entry)['course_title'] ?? '');
		}
		if (SecondaryTimetableCriteria::isOmittedFromGridTitle($title)) {
			return false;
		}
		$key = $day . ':' . $slotId;
		if ($classId > 0 && !empty($state['class_busy'][$classId . ':' . $key])) {
			return false;
		}
		if ($staffId > 0 && !empty($state['staff_busy'][$staffId . ':' . $key])) {
			return false;
		}
		$range = $this->slotTimeRange($slot);
		if ($staffId > 0 && $range !== null && $this->staffTimeConflictIds($state, $staffId, $day, $range['start'], $range['end']) !== []) {
			return false;
		}
		if ($day === 6 && $this->secondaryCriteria === null) {
			return false;
		}
		if ($this->secondaryCriteria !== null) {
			$meta = $this->metaForEntry($entry);
			if (!$this->secondaryCriteria->slotAllowed(
				$meta,
				$day,
				(string) ($slot['start_time'] ?? ''),
				(string) ($slot['end_time'] ?? '')
			)) {
				return false;
			}
		}
		$meta = $this->metaForEntry($entry);
		if (NurseryTimetableCriteria::isNurseryRow($meta)) {
			$title = (string) ($meta['course_title'] ?? '');
			$homework = NurseryTimetableCriteria::isHomeworkCourse($title);
			$start = (string) ($slot['start_time'] ?? '');
			$end = (string) ($slot['end_time'] ?? '');
			if ($homework && TimetableGeneratorService::isMorningClock($start)) {
				return false;
			}
			if (!$homework && NurseryTimetableCriteria::slotIsAfterLunch($start, $end)) {
				return false;
			}
			if ($this->wouldExceedSubjectDayLimit($state, $entry, $day)) {
				return false;
			}
		}
		return true;
	}

	/** Park leftover colliding rows. Never write them back onto the grid. */
	public function parkAllConflicts(
		int $scheduleId,
		int $schoolId,
		int $filterClassId = 0,
		int $filterStaffId = 0
	): int {
		if ($this->assignmentMeta === []) {
			$this->assignmentMeta = $this->loadAssignmentMeta($scheduleId, $schoolId);
			$this->secondaryCriteria = new SecondaryTimetableCriteria();
			$this->secondaryCriteria->hydrateFromAssignments(array_values($this->assignmentMeta));
		}
		$ids = $this->collectConflictEntryIds(
			$this->scheduledEntries($scheduleId, $schoolId, $filterClassId, $filterStaffId)
		);
		$protected = [];
		foreach ($this->scheduledEntries($scheduleId, $schoolId, $filterClassId, $filterStaffId) as $entry) {
			$entryId = (int) ($entry['id'] ?? 0);
			if ($entryId <= 0) {
				continue;
			}
			if ($this->entryIsLocked($entry) || $this->isPartTimeStaff((int) ($entry['staff_id'] ?? 0))) {
				$protected[$entryId] = true;
			}
		}
		$ids = array_values(array_filter($ids, static function (int $id) use ($protected): bool {
			return $id > 0 && !isset($protected[$id]);
		}));
		if ($ids === []) {
			return 0;
		}
		$db = \Config\Database::connect();
		$db->table('timetable_entries')->whereIn('id', $ids)->update([
			'day_of_week' => -1,
			'slot_id' => 0,
			'is_locked' => 0,
		]);
		return count($ids);
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
			->select('cr.id AS course_record_id, cr.class AS class_id, cr.course AS course_id, cr.lecturer, c.credit, c.title AS course_title,
				cc.title AS category_title,
				l.title AS level_title, l.title AS level_name, l.faculty_id AS level_faculty_id,
				f.title AS faculty_title, f.abbrev AS faculty_abbrev, f.type AS faculty_type,
				d.faculty_id AS dept_faculty_id, d.code AS dept_code, d.title AS dept_title,
				cl.title AS class_title,
				CONCAT(s.fname, " ", s.lname) AS teacher_name')
			->join('classes cl', 'cl.id = cr.class')
			->join('levels l', 'l.id = cl.level', 'left')
			->join('departments d', 'd.id = cl.department', 'left')
			->join('faculty f', 'f.id = d.faculty_id', 'left')
			->join('courses c', 'c.id = cr.course')
			->join('course_category cc', 'cc.id = c.category', 'left')
			->join('staffs s', 's.id = cr.lecturer', 'left')
			->where('cl.school_id', $schoolId)
			->where('cr.year', $year)
			->where("find_in_set($term, cr.term) >", 0, false)
			->where(SecondaryTimetableCriteria::excludeEmptyS6PcmSql('cl', 'l', 'd', $year), null, false)
			->whereNotIn('cr.course', SecondaryTimetableCriteria::MANAGER_ONLY_COURSE_IDS)
			->get()->getResultArray();

		$out = [];
		foreach ($rows as $row) {
			if (SecondaryTimetableCriteria::isOmittedFromGridTitle((string) ($row['course_title'] ?? ''))) {
				continue;
			}
			$row['track_key'] = \App\Libraries\TimetableTrack::resolveFromRow($row);
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
			'class_clock' => [],
			'staff_busy' => [],
			'staff_time' => [],
			'subject_day_count' => [],
			'class_day_usage' => [],
			'class_day_courses' => [],
			'nursery_homework_done' => [],
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
		$range = $this->entryTimeRange($entry);
		if ($range !== null && $classId > 0) {
			$state['class_clock'][$classId . ':' . $day . ':' . $range['start']] = $entryId;
		}
		if ($staffId > 0) {
			$state['staff_busy'][$staffId . ':' . $key] = $entryId;
			$range = $this->entryTimeRange($entry);
			if ($range !== null) {
				$state['staff_time'][$staffId][$day][] = [
					'id' => $entryId,
					'start' => $range['start'],
					'end' => $range['end'],
				];
			}
		}
		$subjectKey = $classId . ':' . (int) ($entry['course_id'] ?? 0) . ':' . $day;
		$state['subject_day_count'][$subjectKey] = (int) ($state['subject_day_count'][$subjectKey] ?? 0) + 1;
		$state['class_day_usage'][$classId . ':' . $day] = (int) ($state['class_day_usage'][$classId . ':' . $day] ?? 0) + 1;
		$courseId = (int) ($entry['course_id'] ?? 0);
		if ($classId > 0 && $courseId > 0) {
			$state['class_day_courses'][$classId . ':' . $day][$courseId] = true;
			if (NurseryTimetableCriteria::slotOverlapsHomeworkWindow(
				(string) ($entry['start_time'] ?? ''),
				(string) ($entry['end_time'] ?? '')
			)) {
				$state['nursery_homework_done'][$classId . ':' . $courseId] = true;
			}
		}
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
		$range = $this->entryTimeRange($entry);
		if ($range !== null && $classId > 0) {
			$clockKey = $classId . ':' . $day . ':' . $range['start'];
			if ((int) ($state['class_clock'][$clockKey] ?? 0) === $entryId) {
				unset($state['class_clock'][$clockKey]);
			}
		}
		if ($staffId > 0) {
			unset($state['staff_busy'][$staffId . ':' . $key]);
			if (!empty($state['staff_time'][$staffId][$day])) {
				$state['staff_time'][$staffId][$day] = array_values(array_filter(
					$state['staff_time'][$staffId][$day],
					static fn (array $row): bool => (int) ($row['id'] ?? 0) !== $entryId
				));
			}
		}
		$subjectKey = $classId . ':' . (int) ($entry['course_id'] ?? 0) . ':' . $day;
		$state['subject_day_count'][$subjectKey] = max(0, (int) ($state['subject_day_count'][$subjectKey] ?? 0) - 1);
		$state['class_day_usage'][$classId . ':' . $day] = max(0, (int) ($state['class_day_usage'][$classId . ':' . $day] ?? 0) - 1);
		$courseId = (int) ($entry['course_id'] ?? 0);
		if ($classId > 0 && $courseId > 0) {
			unset($state['class_day_courses'][$classId . ':' . $day][$courseId]);
			if (($state['subject_day_count'][$subjectKey] ?? 0) > 0) {
				$state['class_day_courses'][$classId . ':' . $day][$courseId] = true;
			}
			$hwKey = $classId . ':' . $courseId;
			unset($state['nursery_homework_done'][$hwKey]);
			foreach ($state['by_id'] as $other) {
				if ((int) ($other['class_id'] ?? 0) !== $classId || (int) ($other['course_id'] ?? 0) !== $courseId) {
					continue;
				}
				if (NurseryTimetableCriteria::slotOverlapsHomeworkWindow(
					(string) ($other['start_time'] ?? ''),
					(string) ($other['end_time'] ?? '')
				)) {
					$state['nursery_homework_done'][$hwKey] = true;
					break;
				}
			}
		}
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
	private function findBestDirectPlacement(
		array $entry,
		array $days,
		\App\Models\TimetableSchemaModel $schema,
		int $schoolId,
		array $state,
		bool $ignoreDayLimit = false
	): ?array {
		$candidates = $this->candidateSlots($entry, $days, $schema, $schoolId, $state, false, $ignoreDayLimit);
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
			if ($this->entryIsLocked($blocker) && !$this->version2Overflow) {
				continue;
			}
			if ($this->version2Overflow && $this->blockerMustStay($blocker, $state)) {
				continue;
			}
			$this->removeScheduledEntry($state, $blocker);
			$relocation = $this->findBestDirectPlacement(
				$blocker,
				$days,
				$schema,
				$schoolId,
				$state,
				$this->version2Overflow
			);
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
		bool $allowSingleBlocker,
		bool $ignoreDayLimit = false
	): array {
		$classId = (int) ($entry['class_id'] ?? 0);
		$staffId = (int) ($entry['staff_id'] ?? 0);
		$trackKey = $schema->trackForClass($schoolId, $classId);
		$days = \App\Models\TimetableSchemaModel::weekDaysForTrack($this->timetableSettings, $trackKey);
		$slots = array_values(array_filter(
			$schema->generationSlots($schoolId, $trackKey),
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
			if (!$ignoreDayLimit && $this->wouldExceedSubjectDayLimit($state, $entry, (int) $day)) {
				continue;
			}
			foreach ($slots as $slotIndex => $slot) {
				$slotId = (int) ($slot['id'] ?? 0);
				if ($slotId <= 0) {
					continue;
				}
				$range = $this->slotTimeRange($slot);
				if ($range === null) {
					continue;
				}
				$key = $day . ':' . $slotId;
				if (!empty($blocked[$key])) {
					continue;
				}
				$meta = $this->metaForEntry($entry);
				$meta['_track_key'] = $trackKey;
				if ($this->secondaryCriteria !== null) {
					$start = (string) ($slot['start_time'] ?? '');
					$end = (string) ($slot['end_time'] ?? '');
					if (!$this->secondaryCriteria->slotAllowed($meta, (int) $day, $start, $end)) {
						continue;
					}
				}
				if (NurseryTimetableCriteria::isNurseryRow($meta)
					&& NurseryTimetableCriteria::isHomeworkCourse((string) ($meta['course_title'] ?? ''))
					&& TimetableGeneratorService::isMorningClock((string) ($slot['start_time'] ?? ''))) {
					continue;
				}
				$classBlocker = (int) ($state['class_busy'][$classId . ':' . $key] ?? 0);
				$clockBlocker = (int) ($state['class_clock'][$classId . ':' . $day . ':' . $range['start']] ?? 0);
				$blockers = array_values(array_unique(array_filter([$classBlocker, $clockBlocker])));
				$combinedAttach = false;
				if ($staffId > 0) {
					$slotStaffBlocker = (int) ($state['staff_busy'][$staffId . ':' . $key] ?? 0);
					if ($slotStaffBlocker > 0 && $this->staffBlockerIsCombined($entry, $state, $slotStaffBlocker)) {
						$combinedAttach = true;
					} elseif ($slotStaffBlocker > 0) {
						$blockers[] = $slotStaffBlocker;
					}
					foreach ($this->staffTimeConflictIds($state, $staffId, (int) $day, $range['start'], $range['end']) as $staffBlockerId) {
						if ($this->staffBlockerIsCombined($entry, $state, $staffBlockerId)) {
							$combinedAttach = true;
							continue;
						}
						$blockers[] = $staffBlockerId;
					}
				}
				$blockers = array_values(array_unique(array_filter($blockers)));
				if ($blockers !== [] && (!$allowSingleBlocker || count($blockers) > 1)) {
					continue;
				}
				$score = $this->scoreCandidate(
					$state,
					$entry,
					(int) $day,
					$slotId,
					count($blockers),
					$range,
					(int) $slotIndex,
					count($slots)
				);
				if ($combinedAttach) {
					$score -= 8000;
				}
				$candidates[] = [
					'day' => (int) $day,
					'slot_id' => $slotId,
					'score' => $score,
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
	private function scoreCandidate(
		array $state,
		array $entry,
		int $day,
		int $slotId,
		int $blockerCount,
		?array $candidateRange = null,
		int $slotIndex = 0,
		int $slotCount = 0
	): int {
		$classId = (int) ($entry['class_id'] ?? 0);
		$courseId = (int) ($entry['course_id'] ?? 0);
		$meta = $this->metaForEntry($entry);
		$hours = TimetableGeneratorService::weeklyHoursFromCourse($meta);
		$track = strtolower(trim((string) ($meta['track_key'] ?? '')));
		$useDoubles = $hours >= 3 && !NurseryTimetableCriteria::isNurseryRow($meta);
		$peSport = TimetableGeneratorService::isPhysicalEducationSportTitle((string) ($meta['course_title'] ?? ''));
		$lastHour = $peSport || ($this->secondaryCriteria !== null && $this->secondaryCriteria->prefersLastHour($meta));
		$afterLessons = $this->secondaryCriteria !== null && $this->secondaryCriteria->requiresAfterLessons($meta);

		$score = $blockerCount * 1000;
		$sameDay = $this->subjectDayCountForState($state, $entry, $day);

		if ($afterLessons) {
			$start = $candidateRange !== null
				? sprintf('%02d:%02d:00', intdiv((int) $candidateRange['start'], 60), ((int) $candidateRange['start']) % 60)
				: null;
			$end = $candidateRange !== null
				? sprintf('%02d:%02d:00', intdiv((int) $candidateRange['end'], 60), ((int) $candidateRange['end']) % 60)
				: null;
			$score += $this->secondaryCriteria->afterLessonScoreDelta($meta, $start, $end);
		} elseif ($lastHour && $slotCount > 0) {
			$start = $candidateRange !== null
				? sprintf('%02d:%02d:00', intdiv((int) $candidateRange['start'], 60), ((int) $candidateRange['start']) % 60)
				: null;
			$end = $candidateRange !== null
				? sprintf('%02d:%02d:00', intdiv((int) $candidateRange['end'], 60), ((int) $candidateRange['end']) % 60)
				: null;
			if ($this->secondaryCriteria !== null) {
				$score += $this->secondaryCriteria->lastHourScoreDelta($meta, $start, $end);
			}
			if ($start !== null && !\App\Models\TimetableSchemaModel::isLastTeachingHourSlotTimes($start, $end)) {
				$score += 20000;
			}
		} elseif ($candidateRange !== null && TimetableGeneratorService::isMorningClock(
			sprintf('%02d:%02d:00', intdiv((int) $candidateRange['start'], 60), ((int) $candidateRange['start']) % 60)
		)) {
			$score -= 2500;
		} elseif ($slotCount > 0 && $candidateRange !== null
			&& \App\Models\TimetableSchemaModel::isLastTeachingHourSlotTimes(
				sprintf('%02d:%02d:00', intdiv((int) $candidateRange['start'], 60), ((int) $candidateRange['start']) % 60),
				sprintf('%02d:%02d:00', intdiv((int) $candidateRange['end'], 60), ((int) $candidateRange['end']) % 60)
			)) {
			// Leave 14:20–15:40 freer for PE when staging non-PE subjects.
			$score += 900;
		} else {
			$score += 4000;
		}

		if ($useDoubles) {
			// Complete a double when this subject already has one period today.
			if ($sameDay === 1) {
				$score -= 900;
				if ($candidateRange !== null && $this->rangeTouchesExistingSubjectPeriod($state, $entry, $day, $candidateRange)) {
					$score -= 700;
				}
			} else {
				$score += $sameDay * 300;
			}

			$occupied = $this->occupiedSubjectDays($state, $classId, $courseId);
			if ($sameDay === 0) {
				if ($occupied === []) {
					$score += in_array($day, [0, 2, 4], true) ? -50 : 40;
				}
				foreach ($occupied as $od) {
					$dist = abs($day - (int) $od);
					if ($dist === 1) {
						$score += 2500;
					} else {
						$score -= min(40, $dist * 12);
					}
				}
			}
		} else {
			$score += $sameDay * 300;
		}

		$score += (int) ($state['class_day_usage'][$classId . ':' . $day] ?? 0) * 80;
		$score += $peSport ? 0 : $slotId;
		if ($this->secondaryCriteria !== null && $candidateRange !== null) {
			$startH = intdiv((int) $candidateRange['start'], 60);
			$startM = ((int) $candidateRange['start']) % 60;
			$endH = intdiv((int) $candidateRange['end'], 60);
			$endM = ((int) $candidateRange['end']) % 60;
			$start = sprintf('%02d:%02d:00', $startH, $startM);
			$end = sprintf('%02d:%02d:00', $endH, $endM);
			$meta['_track_key'] = $track;
			$score += $this->secondaryCriteria->morningScoreDelta($meta, $start, $end);
		}
		if (NurseryTimetableCriteria::isNurseryRow($meta) && $candidateRange !== null) {
			$startH = intdiv((int) $candidateRange['start'], 60);
			$startM = ((int) $candidateRange['start']) % 60;
			$endH = intdiv((int) $candidateRange['end'], 60);
			$endM = ((int) $candidateRange['end']) % 60;
			$start = sprintf('%02d:%02d:00', $startH, $startM);
			$end = sprintf('%02d:%02d:00', $endH, $endM);
			$score += NurseryTimetableCriteria::varietyScoreDelta(
				$this->uniqueCoursesOnDay($state, $classId, $day),
				$this->dayHasCourse($state, $entry, $day)
			);
			$hwOnDay = 0;
			foreach (($state['by_id'] ?? []) as $existing) {
				if ((int) ($existing['class_id'] ?? 0) !== $classId) {
					continue;
				}
				if ((int) ($existing['day_of_week'] ?? -1) !== $day) {
					continue;
				}
				$label = strtolower((string) ($existing['custom_label'] ?? $existing['course_title'] ?? ''));
				if (strpos($label, 'homework') !== false || strpos($label, 'home work') !== false) {
					$hwOnDay++;
				}
			}
			$score += NurseryTimetableCriteria::homeworkScoreDelta(
				$meta,
				$start,
				$end,
				!empty($state['nursery_homework_done'][$classId . ':' . (int) ($entry['course_id'] ?? 0)]),
				$hwOnDay,
				$this->uniqueCoursesOnDay($state, $classId, $day)
			);
		}
		return $score;
	}

	/**
	 * @param array<string,mixed> $state
	 * @return list<int>
	 */
	private function occupiedSubjectDays(array $state, int $classId, int $courseId): array
	{
		$out = [];
		foreach ($state['subject_day_count'] ?? [] as $key => $count) {
			if ((int) $count <= 0) {
				continue;
			}
			$parts = explode(':', (string) $key);
			if (count($parts) !== 3) {
				continue;
			}
			if ((int) $parts[0] === $classId && (int) $parts[1] === $courseId) {
				$out[] = (int) $parts[2];
			}
		}
		return $out;
	}

	/**
	 * @param array<string,mixed> $state
	 * @param array{start:int,end:int} $range
	 */
	private function rangeTouchesExistingSubjectPeriod(array $state, array $entry, int $day, array $range): bool
	{
		$classId = (int) ($entry['class_id'] ?? 0);
		$courseId = (int) ($entry['course_id'] ?? 0);
		foreach ($state['by_id'] ?? [] as $row) {
			if ((int) ($row['class_id'] ?? 0) !== $classId || (int) ($row['course_id'] ?? 0) !== $courseId) {
				continue;
			}
			if ((int) ($row['day_of_week'] ?? -1) !== $day) {
				continue;
			}
			$start = (string) ($row['start_time'] ?? '');
			$end = (string) ($row['end_time'] ?? '');
			if ($start === '' || $end === '') {
				continue;
			}
			$existing = [
				'start' => $this->timeToMinutes($start),
				'end' => $this->timeToMinutes($end),
			];
			$gap = min(
				abs($range['end'] - $existing['start']),
				abs($existing['end'] - $range['start'])
			);
			if ($gap <= 25) {
				return true;
			}
		}
		return false;
	}

	/** @param array<string,mixed> $state */
	private function subjectDayCountForState(array $state, array $entry, int $day): int
	{
		$classId = (int) ($entry['class_id'] ?? 0);
		$courseId = (int) ($entry['course_id'] ?? 0);
		return (int) ($state['subject_day_count'][$classId . ':' . $courseId . ':' . $day] ?? 0);
	}

	/** @param array<string,mixed> $state */
	private function uniqueCoursesOnDay(array $state, int $classId, int $day): int
	{
		return count($state['class_day_courses'][$classId . ':' . $day] ?? []);
	}

	/** @param array<string,mixed> $state */
	private function dayHasCourse(array $state, array $entry, int $day): bool
	{
		$classId = (int) ($entry['class_id'] ?? 0);
		$courseId = (int) ($entry['course_id'] ?? 0);
		return $classId > 0 && $courseId > 0 && !empty($state['class_day_courses'][$classId . ':' . $day][$courseId]);
	}

	/** @param array<string,mixed> $state */
	private function wouldExceedSubjectDayLimit(array $state, array $entry, int $day): bool
	{
		$meta = $this->metaForEntry($entry);
		$hours = TimetableGeneratorService::weeklyHoursFromCourse($meta);
		$maxPerDay = $this->maxPerDayForEntry($meta, $hours, $state, $entry, $day);
		return $this->subjectDayCountForState($state, $entry, $day) + 1 > $maxPerDay;
	}

	/** @return array<string,mixed> */
	private function metaForEntry(array $entry): array
	{
		$key = $this->keyFromEntry($entry);
		$meta = $this->assignmentMeta[$key] ?? [];
		if ($meta === []) {
			$meta = [
				'course_title' => (string) ($entry['course_title'] ?? $entry['custom_label'] ?? ''),
				'credit' => 0,
				'track_key' => (string) ($entry['track_key'] ?? ''),
				'class_id' => (int) ($entry['class_id'] ?? 0),
				'course_id' => (int) ($entry['course_id'] ?? 0),
				'lecturer' => (int) ($entry['staff_id'] ?? 0),
				'staff_id' => (int) ($entry['staff_id'] ?? 0),
				'class_title' => (string) ($entry['class_title'] ?? ''),
				'level_name' => (string) ($entry['level_name'] ?? ''),
				'dept_code' => (string) ($entry['dept_code'] ?? ''),
				'teacher_name' => (string) ($entry['teacher_name'] ?? ''),
			];
		}
		foreach ([
			'course_title', 'class_title', 'level_name', 'level_title', 'dept_code',
			'dept_title', 'teacher_name', 'track_key', 'category_title',
		] as $field) {
			if (trim((string) ($meta[$field] ?? '')) === '' && trim((string) ($entry[$field] ?? '')) !== '') {
				$meta[$field] = $entry[$field];
			}
		}
		if (trim((string) ($meta['course_title'] ?? '')) === '') {
			$meta['course_title'] = (string) ($entry['custom_label'] ?? '');
		}
		return $meta;
	}

	private function maxPerDayForEntry(array $meta, int $hours, array $state = [], array $entry = [], int $day = -1): int
	{
		if (NurseryTimetableCriteria::isNurseryRow($meta)) {
			if ($state !== [] && $entry !== [] && $day >= 0) {
				return NurseryTimetableCriteria::maxPerDay(
					$this->uniqueCoursesOnDay($state, (int) ($entry['class_id'] ?? 0), $day),
					$this->dayHasCourse($state, $entry, $day),
					NurseryTimetableCriteria::isCoreCourse($meta)
				);
			}
			return 1;
		}
		if ($this->secondaryCriteria !== null) {
			$peMax = $this->secondaryCriteria->peMaxPerDay($meta, $hours);
			if ($peMax !== null) {
				return $peMax;
			}
		}
		if ($this->requiresSpreadAcrossDays($meta)) {
			return 1;
		}
		$fallback = ($hours > 0 && $hours <= 2) ? 1 : 2;
		if ($this->secondaryCriteria !== null) {
			return $this->secondaryCriteria->packedDailyCap($meta, $hours, $fallback);
		}
		return $fallback;
	}

	private function requiresSpreadAcrossDays(array $meta): bool
	{
		$track = strtolower(trim((string) ($meta['track_key'] ?? '')));
		if (!in_array($track, ['primary', 'nursery'], true)) {
			return false;
		}
		$title = strtolower(trim(preg_replace('/\s+/', ' ', (string) ($meta['course_title'] ?? ''))));
		return strpos($title, 'mathematics') === false && preg_match('/\bmath\b/', $title) !== 1;
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	private function scheduledEntries(int $scheduleId, int $schoolId, int $filterClassId = 0, int $filterStaffId = 0): array
	{
		$builder = \Config\Database::connect()->table('timetable_entries')
			->select('timetable_entries.*, ts.start_time, ts.end_time, c.title AS course_title,
				cc.title AS category_title,
				cl.title AS class_title, l.title AS level_name, d.code AS dept_code, d.title AS dept_title,
				CONCAT(s.fname, " ", s.lname) AS teacher_name')
			->join('timetable_slots ts', 'ts.id = timetable_entries.slot_id', 'left')
			->join('courses c', 'c.id = timetable_entries.course_id', 'left')
			->join('course_category cc', 'cc.id = c.category', 'left')
			->join('classes cl', 'cl.id = timetable_entries.class_id', 'left')
			->join('levels l', 'l.id = cl.level', 'left')
			->join('departments d', 'd.id = cl.department', 'left')
			->join('staffs s', 's.id = timetable_entries.staff_id', 'left')
			->where('timetable_entries.schedule_id', $scheduleId)
			->where('timetable_entries.school_id', $schoolId)
			->where('timetable_entries.entry_type', 'lesson')
			->where('timetable_entries.day_of_week >=', 0)
			->where('timetable_entries.slot_id >', 0);
		if ($filterClassId > 0) {
			$builder->where('timetable_entries.class_id', $filterClassId);
		}
		if ($filterStaffId > 0) {
			$builder->where('timetable_entries.staff_id', $filterStaffId);
		}
		return $builder->orderBy('timetable_entries.id', 'ASC')->get()->getResultArray();
	}

	/**
	 * @param list<array<string,mixed>> $scheduled
	 * @return list<int>
	 */
	private function collectConflictEntryIds(array $scheduled): array
	{
		$byClassSlot = [];
		$teacherDayRows = [];
		foreach ($scheduled as $entry) {
			$entryId = (int) ($entry['id'] ?? 0);
			$classId = (int) ($entry['class_id'] ?? 0);
			$staffId = (int) ($entry['staff_id'] ?? 0);
			$day = (int) ($entry['day_of_week'] ?? -1);
			$slotId = (int) ($entry['slot_id'] ?? 0);
			if ($entryId <= 0 || $classId <= 0 || $day < 0 || $slotId <= 0) {
				continue;
			}
			$clock = substr((string) ($entry['start_time'] ?? ''), 0, 5)
				. '|' . substr((string) ($entry['end_time'] ?? ''), 0, 5);
			$byClassSlot[$classId . ':' . $day . ':' . ($clock !== '|' ? $clock : (string) $slotId)][] = $entry;
			if ($staffId > 0) {
				$teacherDayRows[$staffId . ':' . $day][] = $entry;
			}
		}

		$ids = [];
		$byClassDay = [];
		foreach ($byClassSlot as $group) {
			if (count($group) <= 1) {
				continue;
			}
			usort($group, static function (array $a, array $b): int {
				$la = (int) ($a['is_locked'] ?? 0);
				$lb = (int) ($b['is_locked'] ?? 0);
				if ($la !== $lb) {
					return $lb <=> $la;
				}
				$pa = stripos((string) ($a['course_title'] ?? ''), 'physical education') !== false ? 1 : 0;
				$pb = stripos((string) ($b['course_title'] ?? ''), 'physical education') !== false ? 1 : 0;
				if ($pa !== $pb) {
					return $pa <=> $pb;
				}
				return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
			});
			$drop = array_slice($group, 1);
			foreach ($drop as $entry) {
				$ids[(int) $entry['id']] = (int) $entry['id'];
			}
		}
		foreach ($scheduled as $entry) {
			$classId = (int) ($entry['class_id'] ?? 0);
			$day = (int) ($entry['day_of_week'] ?? -1);
			if ($classId <= 0 || $day < 0 || (int) ($entry['slot_id'] ?? 0) <= 0) {
				continue;
			}
			$byClassDay[$classId . ':' . $day][] = $entry;
		}
		foreach ($byClassDay as $group) {
			usort($group, function (array $a, array $b): int {
				$la = (int) ($a['is_locked'] ?? 0);
				$lb = (int) ($b['is_locked'] ?? 0);
				if ($la !== $lb) {
					return $lb <=> $la;
				}
				return $this->timeToMinutes((string) ($a['start_time'] ?? ''))
					<=> $this->timeToMinutes((string) ($b['start_time'] ?? ''));
			});
			$keptRanges = [];
			foreach ($group as $entry) {
				$range = $this->entryTimeRange($entry);
				if ($range === null) {
					continue;
				}
				$overlaps = false;
				foreach ($keptRanges as $other) {
					if ($range['start'] < $other['end'] && $other['start'] < $range['end']) {
						$overlaps = true;
						break;
					}
				}
				if ($overlaps) {
					$ids[(int) $entry['id']] = (int) $entry['id'];
					continue;
				}
				$keptRanges[] = $range;
			}
		}
		foreach ($teacherDayRows as $group) {
			if (count($group) <= 1) {
				continue;
			}
			usort($group, static function (array $a, array $b): int {
				$la = (int) ($a['is_locked'] ?? 0);
				$lb = (int) ($b['is_locked'] ?? 0);
				if ($la !== $lb) {
					return $lb <=> $la;
				}
				return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
			});
			$kept = [];
			foreach ($group as $entry) {
				$entryId = (int) ($entry['id'] ?? 0);
				$slotId = (int) ($entry['slot_id'] ?? 0);
				$start = $this->timeToMinutes((string) ($entry['start_time'] ?? ''));
				$end = $this->timeToMinutes((string) ($entry['end_time'] ?? ''));
				$hasTime = $end > $start;
				$collides = false;
				foreach ($kept as $other) {
					$sameSlot = (int) ($other['slot_id'] ?? 0) === $slotId;
					$otherHasTime = (int) ($other['end'] ?? 0) > (int) ($other['start'] ?? 0);
					$timeClash = $hasTime && $otherHasTime
						&& $start < (int) $other['end'] && (int) $other['start'] < $end;
					if (!($sameSlot || $timeClash)) {
						continue;
					}
					if (SecondaryTimetableCriteria::entriesAreCombinedLesson(
						$this->combineCheckRow($entry),
						$this->combineCheckRow($other['row'] ?? [])
					)) {
						continue;
					}
					$collides = true;
					break;
				}
				if ($collides) {
					$ids[$entryId] = $entryId;
					continue;
				}
				$kept[] = [
					'id' => $entryId,
					'slot_id' => $slotId,
					'start' => $start,
					'end' => $end,
					'row' => $entry,
				];
			}
		}

		return array_values($ids);
	}

	/** @return array{start:int,end:int}|null */
	private function entryTimeRange(array $entry): ?array
	{
		$start = trim((string) ($entry['start_time'] ?? ''));
		$end = trim((string) ($entry['end_time'] ?? ''));
		if ($start === '' || $end === '') {
			return null;
		}
		return [
			'start' => $this->timeToMinutes($start),
			'end' => $this->timeToMinutes($end),
		];
	}

	/** @param array<string,mixed> $slot @return array{start:int,end:int}|null */
	private function slotTimeRange(array $slot): ?array
	{
		$start = trim((string) ($slot['start_time'] ?? ''));
		$end = trim((string) ($slot['end_time'] ?? ''));
		if ($start === '' || $end === '') {
			return null;
		}
		return [
			'start' => $this->timeToMinutes($start),
			'end' => $this->timeToMinutes($end),
		];
	}

	/** @param array<string,mixed> $state @return list<int> */
	private function staffTimeConflictIds(array $state, int $staffId, int $day, int $start, int $end): array
	{
		$ids = [];
		foreach ($state['staff_time'][$staffId][$day] ?? [] as $row) {
			$otherStart = (int) ($row['start'] ?? 0);
			$otherEnd = (int) ($row['end'] ?? 0);
			if ($start < $otherEnd && $otherStart < $end) {
				$ids[] = (int) ($row['id'] ?? 0);
			}
		}
		return array_values(array_unique(array_filter($ids)));
	}

	private function slotIdForStart(
		\App\Models\TimetableSchemaModel $schema,
		int $schoolId,
		string $trackKey,
		string $start
	): int {
		$want = substr($start, 0, 5);
		if ($want === '') {
			return 0;
		}
		foreach ($schema->teachingSlots($schoolId, $trackKey) as $slot) {
			if (!empty($slot['is_break'])) {
				continue;
			}
			if (substr((string) ($slot['start_time'] ?? ''), 0, 5) === $want) {
				return (int) ($slot['id'] ?? 0);
			}
		}
		return 0;
	}

	private function timeToMinutes(string $time): int
	{
		$parts = explode(':', substr($time, 0, 8));
		return ((int) ($parts[0] ?? 0)) * 60 + (int) ($parts[1] ?? 0);
	}

	/** @param array<string,mixed> $state */
	private function staffBlockerIsCombined(array $entry, array $state, int $blockerId): bool
	{
		if ($blockerId <= 0) {
			return false;
		}
		$other = $state['by_id'][$blockerId] ?? null;
		if (!is_array($other)) {
			return false;
		}
		return SecondaryTimetableCriteria::entriesAreCombinedLesson(
			$this->combineCheckRow($entry),
			$this->combineCheckRow($other)
		);
	}

	/** @return array<string,mixed> */
	private function combineCheckRow(array $entry): array
	{
		$meta = $this->metaForEntry($entry);
		return [
			'staff_id' => (int) ($entry['staff_id'] ?? $meta['lecturer'] ?? 0),
			'lecturer' => (int) ($meta['lecturer'] ?? $entry['staff_id'] ?? 0),
			'class_id' => (int) ($entry['class_id'] ?? $meta['class_id'] ?? 0),
			'course_id' => (int) ($entry['course_id'] ?? $meta['course_id'] ?? 0),
			'course_title' => (string) ($meta['course_title'] ?? $entry['course_title'] ?? $entry['custom_label'] ?? ''),
			'teacher_name' => (string) ($meta['teacher_name'] ?? $entry['teacher_name'] ?? ''),
			'level_name' => (string) ($meta['level_name'] ?? $entry['level_name'] ?? ''),
			'level_title' => (string) ($meta['level_title'] ?? ''),
			'dept_code' => (string) ($meta['dept_code'] ?? ''),
			'dept_title' => (string) ($meta['dept_title'] ?? ''),
			'class_title' => (string) ($meta['class_title'] ?? $entry['class_title'] ?? ''),
		];
	}
}
