<?php

namespace App\Services\Timetable;

/**
 * Parking-lot staging: one DB row per weekly period still not on the grid.
 */
class TimetableStagingService
{
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

		$classBusy = [];
		$staffBusy = [];
		foreach ($scheduled as $entry) {
			$key = (int) $entry['day_of_week'] . ':' . (int) $entry['slot_id'];
			$classBusy[(int) $entry['class_id'] . ':' . $key] = true;
			if ((int) ($entry['staff_id'] ?? 0) > 0) {
				$staffBusy[(int) $entry['staff_id'] . ':' . $key] = true;
			}
		}

		$placed = 0;
		foreach ($parking as $entry) {
			$classId = (int) ($entry['class_id'] ?? 0);
			$staffId = (int) ($entry['staff_id'] ?? 0);
			$trackKey = $schema->trackForClass($schoolId, $classId);
			$slots = array_values(array_filter(
				$schema->teachingSlots($schoolId, $trackKey),
				static fn ($s) => empty($s['is_break'])
			));
			$blocked = $schema->specialTimesMap($schoolId, $trackKey);
			$found = null;

			foreach ($days as $day) {
				foreach ($slots as $slot) {
					$slotId = (int) ($slot['id'] ?? 0);
					if ($slotId <= 0) {
						continue;
					}
					$key = $day . ':' . $slotId;
					if (!empty($blocked[$key])) {
						continue;
					}
					if (!empty($classBusy[$classId . ':' . $key])) {
						continue;
					}
					if ($staffId > 0 && !empty($staffBusy[$staffId . ':' . $key])) {
						continue;
					}
					$found = ['day' => $day, 'slot_id' => $slotId];
					break 2;
				}
			}

			if ($found === null) {
				continue;
			}

			$db->table('timetable_entries')->where('id', (int) $entry['id'])->update([
				'day_of_week' => $found['day'],
				'slot_id' => $found['slot_id'],
			]);
			$key = $found['day'] . ':' . $found['slot_id'];
			$classBusy[$classId . ':' . $key] = true;
			if ($staffId > 0) {
				$staffBusy[$staffId . ':' . $key] = true;
			}
			$placed++;
		}

		return $placed;
	}
}
