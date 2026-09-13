<?php

namespace App\Services\Timetable;

use App\Libraries\TimetableClassLabel;
use App\Libraries\TimetableTrack;
use App\Models\TimetableSchemaModel;

/**
 * Courses / teachers that still have Manage Course periods in the parking lot,
 * plus free slots that would accept them without a collision.
 */
class TimetableUnplacedReport
{
	private const DAY_LABELS = [0 => 'Monday', 1 => 'Tuesday', 2 => 'Wednesday', 3 => 'Thursday', 4 => 'Friday', 5 => 'Saturday', 6 => 'Sunday'];

	/**
	 * @param list<array<string,mixed>> $assignments
	 * @return array<string,mixed>
	 */
	public function build(
		int $scheduleId,
		int $schoolId,
		TimetableSchemaModel $schema,
		array $assignments,
		string $phase = 'all',
		string $schoolName = ''
	): array {
		$db = \Config\Database::connect();
		$settings = $db->table('timetable_settings')->where('school_id', $schoolId)->get(1)->getRowArray();
		$criteria = new SecondaryTimetableCriteria();
		$criteria->hydrateFromAssignments($assignments);
		$criteria->hydrateCustomRules((new TimetableCriteriaStore())->listForSchool($schoolId, true));

		$entries = $db->table('timetable_entries te')
			->select('te.*, ts.start_time, ts.end_time, ts.label AS slot_label')
			->join('timetable_slots ts', 'ts.id = te.slot_id', 'left')
			->where('te.schedule_id', $scheduleId)
			->where('te.school_id', $schoolId)
			->where('te.entry_type', 'lesson')
			->get()->getResultArray();

		$placedByKey = [];
		$classBusy = [];
		$staffBusy = [];
		foreach ($entries as $entry) {
			$day = (int) ($entry['day_of_week'] ?? -1);
			$slotId = (int) ($entry['slot_id'] ?? 0);
			$key = $this->assignmentKey($entry);
			if ($day >= 0 && $slotId > 0) {
				$placedByKey[$key] = ($placedByKey[$key] ?? 0) + 1;
				$classBusy[(int) ($entry['class_id'] ?? 0) . ':' . $day . ':' . $slotId] = true;
				$staffId = (int) ($entry['staff_id'] ?? 0);
				if ($staffId > 0) {
					$staffBusy[$staffId . ':' . $day . ':' . $slotId] = true;
				}
			}
		}

		$courses = [];
		$teachers = [];
		$missedPeriods = 0;
		foreach ($assignments as $row) {
			$needed = TimetableGeneratorService::weeklyHoursFromCourse($row);
			if ($needed <= 0) {
				continue;
			}
			$key = $this->assignmentKey($row);
			$placed = (int) ($placedByKey[$key] ?? 0);
			$missed = max(0, $needed - $placed);
			if ($missed <= 0) {
				continue;
			}
			$missedPeriods += $missed;
			$classId = (int) ($row['class_id'] ?? 0);
			$staffId = (int) ($row['lecturer'] ?? $row['staff_id'] ?? 0);
			$track = (string) ($row['_track_key'] ?? $row['track_key'] ?? $schema->trackForClass($schoolId, $classId));
			$row['_track_key'] = $track;
			$suggestions = $this->suggestSlots(
				$row,
				$classId,
				$staffId,
				$track,
				$schema,
				$schoolId,
				$settings,
				$criteria,
				$classBusy,
				$staffBusy
			);
			$courseRow = [
				'level' => TimetableTrack::generationPhaseLabel(TimetableTrack::generationPhaseKey($track)),
				'class_id' => $classId,
				'class' => TimetableClassLabel::fromRow($row) ?: (string) ($row['class_title'] ?? 'Class'),
				'course' => (string) ($row['course_title'] ?? 'Course'),
				'teacher' => trim((string) ($row['teacher_name'] ?? '')) ?: 'Unassigned',
				'needed' => $needed,
				'placed' => $placed,
				'missed' => $missed,
				'suggestions' => $suggestions,
				'reason' => $suggestions === []
					? 'No free slot for both class and teacher (criteria / availability / full grid). Parked to avoid a collision.'
					: 'Placed as many periods as possible without colliding. Remaining hours are parked — drag onto a listed free slot.',
			];
			$courses[] = $courseRow;

			$teacherName = $courseRow['teacher'];
			if (!isset($teachers[$teacherName])) {
				$teachers[$teacherName] = [
					'teacher' => $teacherName,
					'missed' => 0,
					'courses' => 0,
					'items' => [],
				];
			}
			$teachers[$teacherName]['missed'] += $missed;
			$teachers[$teacherName]['courses']++;
			$teachers[$teacherName]['items'][] = $courseRow;
		}

		usort($courses, static function (array $a, array $b): int {
			return strcasecmp($a['class'], $b['class']) ?: ($b['missed'] <=> $a['missed']);
		});
		$teacherList = array_values($teachers);
		usort($teacherList, static function (array $a, array $b): int {
			return ($b['missed'] <=> $a['missed']) ?: strcasecmp($a['teacher'], $b['teacher']);
		});

		$byClass = [];
		foreach ($courses as $courseRow) {
			$label = trim((string) ($courseRow['class'] ?? '')) ?: 'Class';
			$cid = (int) ($courseRow['class_id'] ?? 0);
			$key = $cid > 0 ? ('id:' . $cid) : ('n:' . strtolower($label));
			if (!isset($byClass[$key])) {
				$byClass[$key] = [
					'class' => $label,
					'class_id' => $cid,
					'level' => (string) ($courseRow['level'] ?? ''),
					'missed' => 0,
					'courses' => 0,
					'items' => [],
				];
			}
			$byClass[$key]['missed'] += (int) ($courseRow['missed'] ?? 0);
			$byClass[$key]['courses']++;
			$byClass[$key]['items'][] = $courseRow;
		}
		$classList = array_values($byClass);
		usort($classList, static function (array $a, array $b): int {
			return strcasecmp($a['class'], $b['class']);
		});

		return [
			'school' => $schoolName,
			'phase' => $phase,
			'phase_label' => TimetableTrack::generationPhaseLabel($phase),
			'schedule_id' => $scheduleId,
			'generated_at' => date('Y-m-d H:i'),
			'missed_courses' => count($courses),
			'missed_teachers' => count($teacherList),
			'missed_classes' => count($classList),
			'missed_periods' => $missedPeriods,
			'courses' => $courses,
			'teachers' => $teacherList,
			'by_class' => $classList,
		];
	}

	/**
	 * @param array<string,mixed> $row
	 * @param array<string,mixed>|null $settings
	 * @param array<string,bool> $classBusy
	 * @param array<string,bool> $staffBusy
	 * @return list<string>
	 */
	private function suggestSlots(
		array $row,
		int $classId,
		int $staffId,
		string $track,
		TimetableSchemaModel $schema,
		int $schoolId,
		?array $settings,
		SecondaryTimetableCriteria $criteria,
		array $classBusy,
		array $staffBusy
	): array {
		$days = TimetableSchemaModel::weekDaysForTrack($settings, $track);
		$slots = array_values(array_filter(
			$schema->teachingSlots($schoolId, $track),
			static fn ($s) => empty($s['is_break'])
		));
		$blocked = $schema->specialTimesMap($schoolId, $track);
		$out = [];
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
				if (!empty($classBusy[$classId . ':' . $day . ':' . $slotId])) {
					continue;
				}
				if ($staffId > 0 && !empty($staffBusy[$staffId . ':' . $day . ':' . $slotId])) {
					continue;
				}
				$start = (string) ($slot['start_time'] ?? '');
				$end = (string) ($slot['end_time'] ?? '');
				if (!$criteria->slotAllowed($row, (int) $day, $start, $end)) {
					continue;
				}
				$label = trim((string) ($slot['label'] ?? ''));
				$time = substr($start, 0, 5);
				$out[] = (self::DAY_LABELS[(int) $day] ?? ('Day ' . $day))
					. ' · ' . ($label !== '' ? $label : $time);
				if (count($out) >= 8) {
					return $out;
				}
			}
		}
		return $out;
	}

	/** @param array<string,mixed> $row */
	private function assignmentKey(array $row): string
	{
		$cr = (int) ($row['course_record_id'] ?? 0);
		if ($cr > 0) {
			return 'cr:' . $cr;
		}
		return 'c:' . (int) ($row['class_id'] ?? 0)
			. ':' . (int) ($row['course_id'] ?? 0)
			. ':' . (int) ($row['lecturer'] ?? $row['staff_id'] ?? 0);
	}
}
