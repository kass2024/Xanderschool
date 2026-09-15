<?php

namespace App\Services\Timetable;

use App\Libraries\TimetableClassLabel;
use App\Libraries\TimetableTrack;
use App\Models\TimetableSchemaModel;

/**
 * Validates timetable moves: one class / one teacher per slot, no time overlap for staff.
 */
class TimetableConflictService
{
	/** @return list<array{type:string,message:string,entry_id?:int}> */
	public function checkMove(
		int $scheduleId,
		int $schoolId,
		int $entryId,
		int $day,
		int $slotId,
		TimetableSchemaModel $schema
	): array {
		if ($day < 0 || $slotId <= 0) {
			return [];
		}

		$db = \Config\Database::connect();
		$entry = $db->table('timetable_entries te')
			->select('te.*, c.title AS course_title')
			->join('courses c', 'c.id = te.course_id', 'left')
			->where('te.id', $entryId)
			->where('te.schedule_id', $scheduleId)->get(1)->getRowArray();
		if (!$entry) {
			return [['type' => 'error', 'message' => 'Lesson not found.']];
		}

		$classId = (int) ($entry['class_id'] ?? 0);
		$staffId = (int) ($entry['staff_id'] ?? 0);
		$conflicts = [];

		$slot = $db->table('timetable_slots')->where('id', $slotId)->where('school_id', $schoolId)->get(1)->getRowArray();
		if (!$slot || !empty($slot['is_break'])) {
			return [['type' => 'error', 'message' => 'Cannot place a lesson on a break period.']];
		}

		$trackKey = $schema->trackForClass($schoolId, $classId);
		$specialMap = $schema->specialTimesMap($schoolId, $trackKey);
		if (!empty($specialMap[$day . ':' . $slotId])) {
			$conflicts[] = [
				'type' => 'special',
				'message' => 'This slot is reserved for: ' . ($specialMap[$day . ':' . $slotId]['label'] ?? 'special activity'),
			];
		}

		if ($classId > 0) {
			$classHit = $db->table('timetable_entries te')
				->select('te.id, c.title AS course_title')
				->join('courses c', 'c.id = te.course_id', 'left')
				->where('te.schedule_id', $scheduleId)
				->where('te.class_id', $classId)
				->where('te.day_of_week', $day)
				->where('te.slot_id', $slotId)
				->where('te.entry_type', 'lesson')
				->where('te.id !=', $entryId)
				->get(1)->getRowArray();
			if ($classHit) {
				$conflicts[] = [
					'type' => 'class',
					'message' => 'This class already has ' . ($classHit['course_title'] ?? 'another lesson') . ' in this period.',
					'entry_id' => (int) $classHit['id'],
				];
			}
		}

		if ($staffId > 0) {
			$staffRows = $db->table('timetable_entries te')
				->select('te.id, te.slot_id, te.class_id, te.course_id, c.title AS course_title, cl.title AS class_title, l.title AS level_name, d.code AS dept_code, d.title AS dept_title')
				->join('courses c', 'c.id = te.course_id', 'left')
				->join('classes cl', 'cl.id = te.class_id', 'left')
				->join('levels l', 'l.id = cl.level', 'left')
				->join('departments d', 'd.id = cl.department', 'left')
				->where('te.schedule_id', $scheduleId)
				->where('te.staff_id', $staffId)
				->where('te.day_of_week', $day)
				->where('te.entry_type', 'lesson')
				->where('te.id !=', $entryId)
				->get()->getResultArray();

			$newStart = $this->timeToMinutes((string) ($slot['start_time'] ?? '00:00'));
			$newEnd = $this->timeToMinutes((string) ($slot['end_time'] ?? '00:00'));

			foreach ($staffRows as $row) {
				$otherSlotId = (int) ($row['slot_id'] ?? 0);
				$otherRow = [
					'staff_id' => $staffId,
					'class_id' => (int) ($row['class_id'] ?? 0),
					'course_id' => (int) ($row['course_id'] ?? 0),
					'course_title' => (string) ($row['course_title'] ?? ''),
				];
				$moving = [
					'staff_id' => $staffId,
					'class_id' => $classId,
					'course_id' => (int) ($entry['course_id'] ?? 0),
					'course_title' => (string) ($entry['course_title'] ?? ''),
				];
				if (SecondaryTimetableCriteria::entriesAreCombinedLesson($moving, $otherRow)) {
					continue;
				}
				if ($otherSlotId === $slotId) {
						$conflicts[] = [
							'type' => 'teacher',
							'message' => 'Teacher is already teaching ' . ($row['course_title'] ?? 'a lesson')
								. ' in ' . $this->classLabel($row) . ' in this period.',
							'entry_id' => (int) $row['id'],
							'class' => $this->classLabel($row),
						];
					continue;
				}
				$otherSlot = $db->table('timetable_slots')->where('id', $otherSlotId)->get(1)->getRowArray();
				if (!$otherSlot) {
					continue;
				}
				$oStart = $this->timeToMinutes((string) ($otherSlot['start_time'] ?? '00:00'));
				$oEnd = $this->timeToMinutes((string) ($otherSlot['end_time'] ?? '00:00'));
				if ($newStart < $oEnd && $oStart < $newEnd) {
					$conflicts[] = [
						'type' => 'teacher_time',
						'message' => 'Teacher time overlap with ' . ($row['course_title'] ?? 'another class')
							. ' in ' . $this->classLabel($row) . '.',
						'entry_id' => (int) $row['id'],
						'class' => $this->classLabel($row),
					];
				}
			}
		}

		return $conflicts;
	}

	/** @return list<array<string,mixed>> */
	public function findScheduleConflicts(int $scheduleId, int $schoolId, TimetableSchemaModel $schema): array
	{
		$db = \Config\Database::connect();
		$entries = $db->table('timetable_entries te')
			->select('te.*, c.title AS course_title, cl.title AS class_title, l.title AS level_name, d.code AS dept_code, d.title AS dept_title, ts.start_time, ts.end_time, ts.label AS slot_label, CONCAT(s.fname, " ", s.lname) AS teacher_name')
			->join('courses c', 'c.id = te.course_id', 'left')
			->join('classes cl', 'cl.id = te.class_id', 'left')
			->join('levels l', 'l.id = cl.level', 'left')
			->join('departments d', 'd.id = cl.department', 'left')
			->join('timetable_slots ts', 'ts.id = te.slot_id', 'left')
			->join('staffs s', 's.id = te.staff_id', 'left')
			->where('te.schedule_id', $scheduleId)
			->where('te.entry_type', 'lesson')
			->where('te.day_of_week >=', 0)
			->where('te.slot_id >', 0)
			->orderBy('te.day_of_week')->orderBy('te.slot_id')
			->get()->getResultArray();

		$issues = [];
		$classMap = [];
		$staffMap = [];

		foreach ($entries as $entry) {
			$id = (int) $entry['id'];
			$day = (int) $entry['day_of_week'];
			$slotId = (int) $entry['slot_id'];
			$classId = (int) ($entry['class_id'] ?? 0);
			$staffId = (int) ($entry['staff_id'] ?? 0);
			$when = $this->whenLabel($day, $entry);
			$className = $this->classLabel($entry);

			$ck = $classId . ':' . $day . ':' . $slotId;
			if ($classId > 0) {
				if (isset($classMap[$ck])) {
					$other = $classMap[$ck];
					$sameTeacher = (int) ($other['staff_id'] ?? 0) > 0 && (int) $other['staff_id'] === $staffId;
					$issues[] = [
						'type' => $sameTeacher ? 'class' : 'two_teachers',
						'entry_id' => $id,
						'other_id' => (int) $other['id'],
						'class_id' => $classId,
						'class' => $className,
						'day' => $day,
						'slot_id' => $slotId,
						'message' => $sameTeacher
							? $className . ' has two lessons on ' . $when
								. ': ' . ($other['course_title'] ?? 'lesson') . ' and ' . ($entry['course_title'] ?? 'lesson') . '.'
							: 'Two teachers in ' . $className . ' on ' . $when . ' — '
								. trim((string) ($other['teacher_name'] ?? 'Teacher')) . ' (' . ($other['course_title'] ?? '') . ')'
								. ' and ' . trim((string) ($entry['teacher_name'] ?? 'Teacher')) . ' (' . ($entry['course_title'] ?? '') . ').',
						'fix' => $sameTeacher
							? 'Correct Manage Course: this class has two lessons in the same period. Split the hours or change one teacher.'
							: 'Correct Manage Course: two teachers are assigned to ' . $className . ' in a way that forced them into the same period. Reassign one subject.',
					];
				} else {
					$classMap[$ck] = $entry;
				}
			}

			if ($staffId > 0) {
				$range = [
					'start' => $this->timeToMinutes((string) ($entry['start_time'] ?? '00:00')),
					'end' => $this->timeToMinutes((string) ($entry['end_time'] ?? '00:00')),
				];
				$hasTime = $range['end'] > $range['start'];
				$key = $staffId . ':' . $day;
				foreach ($staffMap[$key] ?? [] as $other) {
					$otherHasTime = (int) ($other['end'] ?? 0) > (int) ($other['start'] ?? 0);
					$sameSlot = (int) $other['slot_id'] === $slotId;
					$timeClash = $hasTime && $otherHasTime && $this->rangesOverlap($range['start'], $range['end'], $other['start'], $other['end']);
					if (!($sameSlot || $timeClash)) {
						continue;
					}
					$otherRow = [
						'staff_id' => $staffId,
						'class_id' => (int) ($other['class_id'] ?? 0),
						'course_id' => (int) ($other['course_id'] ?? 0),
						'course_title' => (string) ($other['course'] ?? ''),
					];
					if (SecondaryTimetableCriteria::entriesAreCombinedLesson($entry, $otherRow)) {
						continue;
					}
					$issues[] = [
						'type' => 'teacher',
						'entry_id' => $id,
						'other_id' => (int) $other['id'],
						'class_id' => $classId,
						'class' => $className,
						'other_class' => (string) ($other['class'] ?? ''),
						'day' => $day,
						'slot_id' => $slotId,
						'message' => trim((string) ($entry['teacher_name'] ?? 'Teacher')) . ' cannot teach two classes at once on ' . $when
							. ': ' . ($other['course'] ?? 'lesson') . ' in ' . ($other['class'] ?: 'a class')
							. ' and ' . ($entry['course_title'] ?? 'lesson') . ' in ' . $className . '.',
						'fix' => 'Correct Manage Course: give one of these subjects to another teacher, or reduce weekly periods so both fit in different slots.',
					];
				}
				$staffMap[$key][] = [
					'id' => $id,
					'slot_id' => $slotId,
					'start' => $range['start'],
					'end' => $range['end'],
					'course' => $entry['course_title'] ?? '',
					'class' => $className,
					'class_id' => $classId,
					'course_id' => (int) ($entry['course_id'] ?? 0),
				];
			}
		}

		return $issues;
	}

	/**
	 * @param list<array<string,mixed>> $issues
	 * @return array{total:int,two_teachers:int,class:int,teacher:int,items:list<array<string,mixed>>}
	 */
	public function summarizeConflicts(array $issues): array
	{
		$items = [];
		$counts = ['two_teachers' => 0, 'class' => 0, 'teacher' => 0];
		$seen = [];
		foreach ($issues as $issue) {
			$pair = [min((int) ($issue['entry_id'] ?? 0), (int) ($issue['other_id'] ?? 0)), max((int) ($issue['entry_id'] ?? 0), (int) ($issue['other_id'] ?? 0))];
			$key = ($issue['type'] ?? '') . ':' . $pair[0] . ':' . $pair[1];
			if (isset($seen[$key])) {
				continue;
			}
			$seen[$key] = true;
			$type = (string) ($issue['type'] ?? 'class');
			if (isset($counts[$type])) {
				$counts[$type]++;
			}
			$items[] = $issue;
		}
		return [
			'total' => count($items),
			'two_teachers' => $counts['two_teachers'],
			'class' => $counts['class'],
			'teacher' => $counts['teacher'],
			'items' => $items,
		];
	}

	/**
	 * Drop any generated row that would put two teachers in one class or one teacher in two classes.
	 *
	 * @param list<array<string,mixed>> $entries
	 * @param array<int,array{start?:string,end?:string,start_time?:string,end_time?:string}> $slotTimesById
	 * @param list<array<string,mixed>> $occupied
	 * @return array{kept:list<array<string,mixed>>,rejected:list<array<string,mixed>>}
	 */
	public function filterCollisionFreeEntries(array $entries, array $slotTimesById, array $occupied = []): array
	{
		$classBusy = [];
		$staffSlots = [];
		$staffTimes = [];
		foreach ($occupied as $entry) {
			$this->rememberOccupancy($entry, $slotTimesById, $classBusy, $staffSlots, $staffTimes);
		}

		$kept = [];
		$rejected = [];
		foreach ($entries as $entry) {
			$day = (int) ($entry['day_of_week'] ?? -1);
			$slotId = (int) ($entry['slot_id'] ?? 0);
			if ($day < 0 || $slotId <= 0) {
				$kept[] = $entry;
				continue;
			}
			$classId = (int) ($entry['class_id'] ?? 0);
			$staffId = (int) ($entry['staff_id'] ?? 0);
			$classKey = $classId . ':' . $day . ':' . $slotId;
			if ($classId > 0 && isset($classBusy[$classKey])) {
				$entry['_reject_reason'] = 'Another teacher or lesson already occupies this class period.';
				$rejected[] = $entry;
				continue;
			}
			$staffKey = $staffId . ':' . $day . ':' . $slotId;
			if ($staffId > 0 && isset($staffSlots[$staffKey])) {
				$other = is_array($staffSlots[$staffKey]) ? $staffSlots[$staffKey] : [];
				if (!SecondaryTimetableCriteria::entriesAreCombinedLesson($entry, $other)) {
					$entry['_reject_reason'] = 'This teacher is already in another class in this period.';
					$rejected[] = $entry;
					continue;
				}
			}
			$range = $this->slotRangeFromMap($slotId, $slotTimesById, $entry);
			if ($staffId > 0 && $range !== null) {
				foreach ($staffTimes[$staffId][$day] ?? [] as $booked) {
					if (!$this->rangesOverlap($range['start'], $range['end'], $booked['start'], $booked['end'])) {
						continue;
					}
					$other = is_array($booked['entry'] ?? null) ? $booked['entry'] : [];
					if ($other !== [] && SecondaryTimetableCriteria::entriesAreCombinedLesson($entry, $other)) {
						continue;
					}
					$entry['_reject_reason'] = 'This teacher is already teaching at this clock time.';
					$rejected[] = $entry;
					continue 2;
				}
			}
			$this->rememberOccupancy($entry, $slotTimesById, $classBusy, $staffSlots, $staffTimes);
			$kept[] = $entry;
		}

		return ['kept' => $kept, 'rejected' => $rejected];
	}

	/**
	 * Teachers / classes whose Manage Course load cannot fit without a collision.
	 *
	 * @param list<array<string,mixed>> $assignments
	 * @return list<array<string,mixed>>
	 */
	public function assignmentLoadAlerts(array $assignments, TimetableSchemaModel $schema, int $schoolId, ?array $settings): array
	{
		$uniqueClocks = [];
		$tracks = TimetableTrack::tracksForSchool($schoolId) ?: [TimetableTrack::ALL];
		foreach ($tracks as $track) {
			foreach ($schema->teachingSlots($schoolId, (string) $track) as $slot) {
				if (!empty($slot['is_break'])) {
					continue;
				}
				$start = substr((string) ($slot['start_time'] ?? ''), 0, 5);
				$end = substr((string) ($slot['end_time'] ?? ''), 0, 5);
				if ($start === '' || $end === '') {
					continue;
				}
				$uniqueClocks[$start . '-' . $end] = true;
			}
		}
		$days = count(TimetableSchemaModel::weekDaysFromSettings($settings));
		if ($days <= 0) {
			$days = 5;
		}
		$available = max(1, count($uniqueClocks) * $days);

		$teachers = [];
		$classes = [];
		$criteria = new SecondaryTimetableCriteria();
		$criteria->hydrateFromAssignments($assignments);
		$seenCombine = [];
		foreach ($assignments as $row) {
			$hours = TimetableGeneratorService::weeklyHoursFromCourse($row);
			if ($hours <= 0) {
				continue;
			}
			$teacher = trim((string) ($row['teacher_name'] ?? '')) ?: 'Unassigned';
			$staffId = (int) ($row['lecturer'] ?? $row['staff_id'] ?? 0);
			$tKey = $staffId > 0 ? ('s:' . $staffId) : ('n:' . strtolower($teacher));
			$combineKey = $criteria->combineGroupKey($row);
			$teacherHours = $hours;
			$combineNote = '';
			if ($combineKey !== '') {
				if (isset($seenCombine[$combineKey])) {
					$teacherHours = 0;
					$combineNote = ' combined (same period)';
				} else {
					$seenCombine[$combineKey] = true;
					$combineNote = ' combined';
				}
			}
			if (!isset($teachers[$tKey])) {
				$teachers[$tKey] = [
					'type' => 'teacher_overload',
					'teacher' => $teacher,
					'assigned' => 0,
					'available' => $available,
					'items' => [],
				];
			}
			$teachers[$tKey]['assigned'] += $teacherHours;
			$teachers[$tKey]['items'][] = [
				'class' => TimetableClassLabel::fromRow($row),
				'course' => (string) ($row['course_title'] ?? 'Course') . $combineNote,
				'hours' => $teacherHours,
			];

			$classId = (int) ($row['class_id'] ?? 0);
			$className = TimetableClassLabel::fromRow($row);
			$cKey = $classId > 0 ? ('c:' . $classId) : ('n:' . strtolower($className));
			if (!isset($classes[$cKey])) {
				$classes[$cKey] = [
					'type' => 'class_overload',
					'class' => $className,
					'assigned' => 0,
					'available' => $available,
					'items' => [],
				];
			}
			$classes[$cKey]['assigned'] += $hours;
			$classes[$cKey]['items'][] = [
				'teacher' => $teacher,
				'course' => (string) ($row['course_title'] ?? 'Course'),
				'hours' => $hours,
			];
		}

		$alerts = [];
		foreach ($teachers as $row) {
			if ((int) $row['assigned'] <= (int) $row['available']) {
				continue;
			}
			$row['message'] = $row['teacher'] . ' is assigned ' . $row['assigned']
				. ' weekly periods, but the week has only ' . $row['available']
				. ' teaching slots. Correct Manage Course: reduce credits or share subjects with another teacher.';
			$alerts[] = $row;
		}
		foreach ($classes as $row) {
			if ((int) $row['assigned'] <= (int) $row['available']) {
				continue;
			}
			$row['message'] = $row['class'] . ' is assigned ' . $row['assigned']
				. ' weekly periods, but the week has only ' . $row['available']
				. ' teaching slots. Correct Manage Course: reduce credits for this class.';
			$alerts[] = $row;
		}
		usort($alerts, static function (array $a, array $b): int {
			return ((int) $b['assigned'] - (int) $b['available']) <=> ((int) $a['assigned'] - (int) $a['available']);
		});
		return $alerts;
	}

	/**
	 * @param array<string,mixed> $entry
	 * @param array<int,array<string,mixed>> $slotTimesById
	 * @param array<string,bool> $classBusy
	 * @param array<string,bool> $staffSlots
	 * @param array<int,array<int,list<array{start:int,end:int}>>> $staffTimes
	 */
	private function rememberOccupancy(
		array $entry,
		array $slotTimesById,
		array &$classBusy,
		array &$staffSlots,
		array &$staffTimes
	): void {
		$day = (int) ($entry['day_of_week'] ?? -1);
		$slotId = (int) ($entry['slot_id'] ?? 0);
		if ($day < 0 || $slotId <= 0) {
			return;
		}
		$classId = (int) ($entry['class_id'] ?? 0);
		$staffId = (int) ($entry['staff_id'] ?? 0);
		if ($classId > 0) {
			$classBusy[$classId . ':' . $day . ':' . $slotId] = $entry;
		}
		if ($staffId > 0) {
			$staffSlots[$staffId . ':' . $day . ':' . $slotId] = $entry;
			$range = $this->slotRangeFromMap($slotId, $slotTimesById, $entry);
			if ($range !== null) {
				$range['entry'] = $entry;
				$staffTimes[$staffId][$day][] = $range;
			}
		}
	}

	/**
	 * @param array<int,array<string,mixed>> $slotTimesById
	 * @param array<string,mixed> $entry
	 * @return array{start:int,end:int}|null
	 */
	private function slotRangeFromMap(int $slotId, array $slotTimesById, array $entry): ?array
	{
		$times = $slotTimesById[$slotId] ?? null;
		$start = (string) ($times['start'] ?? $times['start_time'] ?? $entry['start_time'] ?? '');
		$end = (string) ($times['end'] ?? $times['end_time'] ?? $entry['end_time'] ?? '');
		if ($start === '' || $end === '') {
			return null;
		}
		$range = [
			'start' => $this->timeToMinutes($start),
			'end' => $this->timeToMinutes($end),
		];
		return $range['end'] > $range['start'] ? $range : null;
	}

	/** @param array<string,mixed> $row */
	private function classLabel(array $row): string
	{
		$label = TimetableClassLabel::fromRow($row);
		return $label !== '' ? $label : 'Class';
	}

	private function whenLabel(int $day, array $entry): string
	{
		$slot = trim((string) ($entry['slot_label'] ?? ''));
		$time = substr((string) ($entry['start_time'] ?? ''), 0, 5);
		$end = substr((string) ($entry['end_time'] ?? ''), 0, 5);
		$clock = ($time !== '' && $end !== '') ? ($time . '–' . $end) : $time;
		$period = $slot !== '' ? $slot : ('period ' . (int) ($entry['slot_id'] ?? 0));
		return $this->dayName($day) . ' ' . $period . ($clock !== '' ? ' (' . $clock . ')' : '');
	}

	private function dayName(int $day): string
	{
		$names = [0 => 'Mon', 1 => 'Tue', 2 => 'Wed', 3 => 'Thu', 4 => 'Fri', 5 => 'Sat', 6 => 'Sun'];
		return $names[$day] ?? ('Day ' . $day);
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
}
