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
			->select('te.*, c.title AS course_title, cl.title AS class_title, ts.start_time, ts.end_time')
			->join('courses c', 'c.id = te.course_id', 'left')
			->join('classes cl', 'cl.id = te.class_id', 'left')
			->join('timetable_slots ts', 'ts.id = te.slot_id', 'left')
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

			$ck = $classId . ':' . $day . ':' . $slotId;
			if ($classId > 0) {
				if (isset($classMap[$ck])) {
					$issues[] = [
						'type' => 'class',
						'entry_id' => $id,
						'other_id' => $classMap[$ck],
						'message' => ($entry['class_title'] ?? 'Class') . ' has two lessons on '
							. $this->dayName($day) . ' period ' . $slotId,
					];
				} else {
					$classMap[$ck] = $id;
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
							'other_id' => $other['id'],
							'message' => 'Teacher double-booked on ' . $this->dayName($day),
						];
					}
				}
				$staffMap[$key][] = [
					'id' => $id,
					'slot_id' => $slotId,
					'start' => $range['start'],
					'end' => $range['end'],
				];
			}
		}

		return $issues;
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
