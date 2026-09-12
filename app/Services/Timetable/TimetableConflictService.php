<?php

namespace App\Services\Timetable;

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
		$entry = $db->table('timetable_entries')->where('id', $entryId)
			->where('schedule_id', $scheduleId)->get(1)->getRowArray();
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
				->select('te.id, te.slot_id, c.title AS course_title, cl.title AS class_title')
				->join('courses c', 'c.id = te.course_id', 'left')
				->join('classes cl', 'cl.id = te.class_id', 'left')
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
				if ($otherSlotId === $slotId) {
					$conflicts[] = [
						'type' => 'teacher',
						'message' => 'Teacher is already teaching ' . ($row['course_title'] ?? 'a lesson')
							. ' (' . ($row['class_title'] ?? '') . ') in this period.',
						'entry_id' => (int) $row['id'],
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
						'message' => 'Teacher time overlap with ' . ($row['course_title'] ?? 'another class') . '.',
						'entry_id' => (int) $row['id'],
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
			->select('te.*, c.title AS course_title, cl.title AS class_title, ts.start_time, ts.end_time, ts.label AS slot_label, CONCAT(s.fname, " ", s.lname) AS teacher_name')
			->join('courses c', 'c.id = te.course_id', 'left')
			->join('classes cl', 'cl.id = te.class_id', 'left')
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
						'day' => $day,
						'slot_id' => $slotId,
						'message' => $sameTeacher
							? trim($entry['class_title'] ?? 'Class') . ' has two lessons on ' . $when
								. ': ' . ($other['course_title'] ?? 'lesson') . ' and ' . ($entry['course_title'] ?? 'lesson') . '.'
							: 'Two teachers in the same class on ' . $when . ' — '
								. trim((string) ($other['teacher_name'] ?? 'Teacher')) . ' (' . ($other['course_title'] ?? '') . ')'
								. ' and ' . trim((string) ($entry['teacher_name'] ?? 'Teacher')) . ' (' . ($entry['course_title'] ?? '') . ')'
								. ' in ' . ($entry['class_title'] ?? 'class') . '.',
						'fix' => 'Open the class timetable, drag one of these two lessons to a free period so only one teacher is in the room.',
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
				$key = $staffId . ':' . $day;
				foreach ($staffMap[$key] ?? [] as $other) {
					if ($other['slot_id'] === $slotId || $this->rangesOverlap($range['start'], $range['end'], $other['start'], $other['end'])) {
						$issues[] = [
							'type' => 'teacher',
							'entry_id' => $id,
							'other_id' => (int) $other['id'],
							'class_id' => $classId,
							'day' => $day,
							'slot_id' => $slotId,
							'message' => trim((string) ($entry['teacher_name'] ?? 'Teacher')) . ' is double-booked on ' . $when
								. ': ' . ($other['course'] ?? 'lesson') . ' (' . ($other['class'] ?? '') . ')'
								. ' and ' . ($entry['course_title'] ?? 'lesson') . ' (' . ($entry['class_title'] ?? '') . ').',
							'fix' => 'Move one of this teacher’s lessons to another free period on their teacher timetable.',
						];
					}
				}
				$staffMap[$key][] = [
					'id' => $id,
					'slot_id' => $slotId,
					'start' => $range['start'],
					'end' => $range['end'],
					'course' => $entry['course_title'] ?? '',
					'class' => $entry['class_title'] ?? '',
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
