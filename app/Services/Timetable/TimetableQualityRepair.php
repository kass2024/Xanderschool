<?php

namespace App\Services\Timetable;

use App\Models\TimetableSchemaModel;

/**
 * Tighten a published timetable without moving part-time staff.
 *
 * - No class or teacher overlap is written.
 * - Examinable lessons move into empty morning cells before other lessons.
 * - A course with fewer than 4 periods is paired (3 periods = one double and one single).
 * - A course with more than 4 periods uses blocks of 2, or one block of 3 when the total is odd.
 * - Nursery, clinical attachment, and PE stay on their own rules.
 */
class TimetableQualityRepair
{
	/** @var \CodeIgniter\Database\BaseConnection */
	private $db;

	/** @var SecondaryTimetableCriteria */
	private $criteria;

	/** @var array<int,array<string,mixed>> */
	private $byId = [];

	/** @var array<int,array<string,mixed>> */
	private $parked = [];

	/** @var array<int,string> */
	private $tracks = [];

	/** @var array<string,list<array<string,mixed>>> */
	private $slotsByTrack = [];

	/** @var array<string,array<string,array<string,mixed>>> */
	private $slotByStart = [];

	/** @var array<string,list<array<string,mixed>>> */
	private $morning = [];

	/** @var array<string,array<string,true>> */
	private $special = [];

	/** @var array<int,true> */
	private $partTime = [];

	/** @var array<string,array<string,mixed>> */
	private $meta = [];

	/** @var array<int,true> */
	private $onlyClasses = [];

	/** @var list<array<string,mixed>> */
	private $undo = [];

	/** @var array<int,string> */
	private $frozenPartTime = [];

	/** @var array<int,true> */
	private $pinned = [];

	private $lockColumn = false;

	private $moved = 0;

	/**
	 * @param list<int> $onlyClassIds Empty means every class except nursery.
	 * @return array<string,int>
	 */
	public function repair(int $scheduleId, int $schoolId, TimetableSchemaModel $schema, array $onlyClassIds = []): array
	{
		$this->db = \Config\Database::connect();
		if ($scheduleId <= 0 || $schoolId <= 0) {
			return ['moved' => 0];
		}
		$this->onlyClasses = [];
		foreach ($onlyClassIds as $id) {
			$id = (int) $id;
			if ($id > 0) {
				$this->onlyClasses[$id] = true;
			}
		}
		$this->boot($scheduleId, $schoolId, $schema);
		$beforeHoles = $this->countHoles();
		$beforeBad = $this->countBadShapes();
		$beforeCollisions = $this->collisionCount();

		$this->db->transStart();
		try {
			for ($pass = 0; $pass < 8; $pass++) {
				$gained = 0;
				$gained += $this->seatClinical();
				$gained += $this->pullRuns();
				$gained += $this->fillHoles(false, false);
				$gained += $this->slideMorning();
				$gained += $this->chainFill();
				$gained += $this->seatByClearing();
				$gained += $this->splitOversized();
				$gained += $this->joinIsolates();
				$gained += $this->swapIntoBlock();
				$gained += $this->clusterSingles();
				if ($gained === 0) {
					break;
				}
			}
			$this->fillHoles(true, false);
			$this->fillHoles(true, true);
			$this->splitOversized();
			$this->joinIsolates();
			$this->fillHoles(false, false);
			if ($this->collisionCount() > $beforeCollisions || $this->partTimeMoved()) {
				throw new \RuntimeException('Timetable repair would add a collision or move part-time staff.');
			}
			$this->db->transComplete();
		} catch (\Throwable $e) {
			$this->db->transRollback();
			throw $e;
		}
		if ($this->db->transStatus() === false) {
			throw new \RuntimeException('Timetable repair did not commit.');
		}

		return [
			'moved' => $this->moved,
			'holes_before' => $beforeHoles,
			'holes_after' => $this->countHoles(),
			'bad_blocks_before' => $beforeBad,
			'bad_blocks_after' => $this->countBadShapes(),
			'collisions' => $this->collisionCount(),
		];
	}

	private function boot(int $scheduleId, int $schoolId, TimetableSchemaModel $schema): void
	{
		$this->byId = [];
		$this->parked = [];
		$this->undo = [];
		$this->pinned = [];
		$this->moved = 0;
		$this->criteria = new SecondaryTimetableCriteria();
		$this->lockColumn = $this->db->fieldExists('is_locked', 'timetable_entries');
		$this->partTime = [];
		foreach ($this->db->table('staffs')->select('id')->where('work_type', 2)->get()->getResultArray() as $row) {
			$this->partTime[(int) ($row['id'] ?? 0)] = true;
		}

		$schedule = $this->db->table('timetable_schedules')->where('id', $scheduleId)->get(1)->getRowArray();
		$year = (int) ($schedule['academic_year'] ?? 0);
		$term = (int) ($schedule['term'] ?? 0);
		$assignments = [];
		if ($year > 0 && $term > 0) {
			$assignments = $this->db->table('course_records cr')
				->select('cr.id AS course_record_id, cr.class AS class_id, cr.course AS course_id, cr.lecturer, c.credit, c.title AS course_title,
					cc.title AS category_title, l.title AS level_title, l.title AS level_name,
					d.code AS dept_code, d.title AS dept_title, cl.title AS class_title,
					CONCAT(s.fname, " ", s.lname) AS teacher_name')
				->join('classes cl', 'cl.id = cr.class')
				->join('levels l', 'l.id = cl.level', 'left')
				->join('departments d', 'd.id = cl.department', 'left')
				->join('courses c', 'c.id = cr.course')
				->join('course_category cc', 'cc.id = c.category', 'left')
				->join('staffs s', 's.id = cr.lecturer', 'left')
				->where('cl.school_id', $schoolId)
				->where('cr.year', $year)
				->where("find_in_set($term, cr.term) >", 0, false)
				->get()->getResultArray();
		}
		$this->meta = [];
		foreach ($assignments as $row) {
			$classId = (int) ($row['class_id'] ?? 0);
			$courseId = (int) ($row['course_id'] ?? 0);
			$staffId = (int) ($row['lecturer'] ?? 0);
			$row['track_key'] = $schema->trackForClass($schoolId, $classId);
			$this->meta[$classId . ':' . $courseId . ':' . $staffId] = $row;
			$this->meta[$classId . ':' . $courseId] = $row;
		}
		$this->criteria->hydrateFromAssignments(array_values($assignments));
		$this->criteria->hydrateCustomRules((new TimetableCriteriaStore())->listForSchool($schoolId, true));
		$this->criteria->setOverflowFill(false);

		$rows = $this->db->table('timetable_entries te')
			->select('te.*, SUBSTRING(ts.start_time,1,8) AS start_time, SUBSTRING(ts.end_time,1,8) AS end_time,
				c.title AS course_title, cc.title AS category_title, cl.title AS class_title,
				l.title AS level_name, l.title AS level_title, d.code AS dept_code, d.title AS dept_title,
				CONCAT(s.fname, " ", s.lname) AS teacher_name, s.work_type')
			->join('timetable_slots ts', 'ts.id = te.slot_id', 'left')
			->join('courses c', 'c.id = te.course_id', 'left')
			->join('course_category cc', 'cc.id = c.category', 'left')
			->join('classes cl', 'cl.id = te.class_id', 'left')
			->join('levels l', 'l.id = cl.level', 'left')
			->join('departments d', 'd.id = cl.department', 'left')
			->join('staffs s', 's.id = te.staff_id', 'left')
			->where('te.schedule_id', $scheduleId)
			->where('te.school_id', $schoolId)
			->where('te.entry_type', 'lesson')
			->get()->getResultArray();

		$this->frozenPartTime = [];
		foreach ($rows as $row) {
			$id = (int) ($row['id'] ?? 0);
			$classId = (int) ($row['class_id'] ?? 0);
			if ($id <= 0 || $classId <= 0) {
				continue;
			}
			if (!isset($this->tracks[$classId])) {
				$this->tracks[$classId] = (string) $schema->trackForClass($schoolId, $classId);
			}
			$row['track_key'] = $this->tracks[$classId];
			$day = (int) ($row['day_of_week'] ?? -1);
			$slotId = (int) ($row['slot_id'] ?? 0);
			if ($this->isPartTime((int) ($row['staff_id'] ?? 0))) {
				$this->frozenPartTime[$id] = $day . ':' . $slotId;
			}
			if ($day >= 0 && $slotId > 0) {
				$this->byId[$id] = $row;
			} else {
				$this->parked[$id] = $row;
			}
		}

		foreach (array_unique($this->tracks) as $track) {
			$slots = [];
			foreach ($schema->teachingSlots($schoolId, $track) as $slot) {
				if (!empty($slot['is_break'])) {
					continue;
				}
				$slots[] = $slot;
			}
			usort($slots, function (array $a, array $b): int {
				return $this->mins((string) ($a['start_time'] ?? '')) <=> $this->mins((string) ($b['start_time'] ?? ''));
			});
			$this->slotsByTrack[$track] = $slots;
			$this->slotByStart[$track] = [];
			$this->morning[$track] = [];
			foreach ($slots as $slot) {
				$start = substr((string) ($slot['start_time'] ?? ''), 0, 5);
				$this->slotByStart[$track][$start] = $slot;
				if ($this->mins($start) < 12 * 60) {
					$this->morning[$track][] = $slot;
				}
			}
			$this->special[$track] = [];
			foreach ($schema->specialTimesMap($schoolId, $track) as $key => $unused) {
				$this->special[$track][(string) $key] = true;
			}
		}
	}

	private function seatClinical(): int
	{
		$moved = 0;
		$seen = [];
		$pool = array_merge(array_values($this->parked), array_values($this->byId));
		foreach ($pool as $entry) {
			$entry = $this->current($entry);
			$id = (int) ($entry['id'] ?? 0);
			if ($id <= 0 || isset($seen[$id])) {
				continue;
			}
			$meta = $this->metaFor($entry);
			if (!$this->criteria->isClinicalAttachmentCourse($meta) || $this->isPartTime((int) ($entry['staff_id'] ?? 0))) {
				continue;
			}
			$placedDay = (int) ($entry['day_of_week'] ?? -1);
			if ($placedDay >= 0 && $this->criteria->isAnpClinicalWindow(
				$meta,
				$placedDay,
				(string) ($entry['start_time'] ?? ''),
				(string) ($entry['end_time'] ?? '')
			)) {
				$seen[$id] = true;
				continue;
			}
			$group = $placedDay >= 0 ? $this->clockGroup($entry) : [$entry];
			if ((int) ($this->lowestId($group)) !== $id) {
				continue;
			}
			$legal = true;
			foreach ($group as $member) {
				$seen[(int) ($member['id'] ?? 0)] = true;
				if ($this->isPartTime((int) ($member['staff_id'] ?? 0))
					|| !$this->criteria->isClinicalAttachmentCourse($this->metaFor($member))) {
					$legal = false;
				}
			}
			if (!$legal) {
				continue;
			}
			$classId = (int) ($entry['class_id'] ?? 0);
			$track = (string) ($this->tracks[$classId] ?? '');
			foreach ([0, 1, 2, 3, 4] as $day) {
				foreach ($this->morning[$track] ?? [] as $slot) {
					if (!$this->criteria->isAnpClinicalWindow(
						$meta,
						$day,
						(string) ($slot['start_time'] ?? ''),
						(string) ($slot['end_time'] ?? '')
					)) {
						continue;
					}
					if (!$this->canPlace($group, $day, $slot, [], false)) {
						continue;
					}
					$mark = $this->mark();
					$before = $this->countHoles();
					if (!$this->placeGroup($group, $day, $slot)
						|| !$this->idsClean($this->groupIds($group))
						|| $this->countHoles() >= $before) {
						$this->revertTo($mark);
						continue;
					}
					$moved++;
					break 2;
				}
			}
		}
		return $moved;
	}

	private function pullRuns(): int
	{
		$moved = 0;
		$seen = [];
		foreach ($this->byId as $entry) {
			if (!$this->isExam($entry) || $this->isMorning((string) ($entry['start_time'] ?? ''))) {
				continue;
			}
			if (!$this->movable($entry, false)) {
				continue;
			}
			$classId = (int) ($entry['class_id'] ?? 0);
			$courseId = (int) ($entry['course_id'] ?? 0);
			$day = (int) ($entry['day_of_week'] ?? -1);
			$key = $classId . ':' . $courseId . ':' . $day;
			if (isset($seen[$key]) || $this->courseFrozen($classId, $courseId)) {
				continue;
			}
			$seen[$key] = true;
			$run = $this->runContaining($classId, $courseId, $entry);
			if (count($run) < 2) {
				continue;
			}
			$leader = $this->clockGroup($run[0]);
			if ((int) ($leader[0]['class_id'] ?? 0) !== $classId && (int) ($run[0]['class_id'] ?? 0) !== $classId) {
				continue;
			}
			if ((int) ($this->clockGroup($run[0])[0]['id'] ?? 0) !== (int) ($run[0]['id'] ?? 0)
				&& (int) ($this->lowestId($this->clockGroup($run[0]))) !== (int) ($run[0]['id'] ?? 0)) {
				continue;
			}
			$groups = [];
			$ignore = [];
			foreach ($run as $period) {
				$group = $this->clockGroup($period);
				if ((int) ($this->lowestId($group)) !== (int) ($period['id'] ?? 0)) {
					$groups = [];
					break;
				}
				$groups[] = $group;
				foreach ($group as $member) {
					$ignore[(int) $member['id']] = true;
				}
			}
			if ($groups === [] || count($groups) !== count($run)) {
				continue;
			}
			$track = (string) ($this->tracks[$classId] ?? '');
			foreach ($this->morningSequences($track, count($run), $day) as $seq) {
				$ok = true;
				foreach ($groups as $i => $group) {
					if (!$this->canPlace($group, (int) $seq['day'], $seq['slots'][$i], $ignore, false)) {
						$ok = false;
						break;
					}
					foreach ($group as $member) {
						$memberDayCount = $this->dayCount((int) $member['class_id'], (int) $member['course_id'], (int) $seq['day'], $ignore);
						if ($memberDayCount > 0) {
							$ok = false;
							break 2;
						}
					}
				}
				if (!$ok) {
					continue;
				}
				$mark = $this->mark();
				foreach ($groups as $i => $group) {
					$this->placeGroup($group, (int) $seq['day'], $seq['slots'][$i]);
				}
				$ids = array_keys($ignore);
				if (!$this->idsClean($ids)) {
					$this->revertTo($mark);
					continue;
				}
				$moved += count($run);
				break;
			}
		}
		return $moved;
	}

	private function fillHoles(bool $allowLanguage, bool $useParked): int
	{
		$moved = 0;
		for ($guard = 0; $guard < 400; $guard++) {
			$filled = false;
			foreach ($this->holeList() as $hole) {
				if ($this->fillOneHole((int) $hole['class_id'], (int) $hole['day'], $hole['slot'], $allowLanguage, $useParked)) {
					$moved++;
					$filled = true;
					break;
				}
			}
			if (!$filled) {
				break;
			}
		}
		return $moved;
	}

	/** @param array<string,mixed> $slot */
	private function fillOneHole(int $classId, int $day, array $slot, bool $allowLanguage, bool $useParked): bool
	{
		if (!$this->classAllowed($classId)) {
			return false;
		}
		$candidates = [];
		foreach ($this->byId as $entry) {
			if ((int) ($entry['class_id'] ?? 0) !== $classId) {
				continue;
			}
			if ($this->isMorning((string) ($entry['start_time'] ?? ''))) {
				continue;
			}
			if (!$this->movable($entry, $allowLanguage)) {
				continue;
			}
			$candidates[] = $entry;
		}
		if ($useParked) {
			foreach ($this->parked as $entry) {
				if ((int) ($entry['class_id'] ?? 0) !== $classId) {
					continue;
				}
				if (!$this->movable($entry, $allowLanguage)) {
					continue;
				}
				if (!$this->underCredit($entry)) {
					continue;
				}
				$candidates[] = $entry;
			}
		}
		usort($candidates, function (array $a, array $b): int {
			return $this->candidateRank($a) <=> $this->candidateRank($b);
		});
		foreach ($candidates as $entry) {
			$group = $this->clockGroup($entry);
			if ((int) ($this->lowestId($group)) !== (int) ($entry['id'] ?? 0)) {
				continue;
			}
			$overflow = $this->prefersAfternoon($entry);
			if (!$this->canPlace($group, $day, $slot, [], $overflow)) {
				continue;
			}
			$mark = $this->mark();
			$this->placeGroup($group, $day, $slot);
			if (!$this->idsClean($this->groupIds($group))) {
				$this->revertTo($mark);
				continue;
			}
			return true;
		}
		return false;
	}

	/** @param array<string,mixed> $slot */
	private function fillOpenedHole(int $classId, int $day, array $slot): bool
	{
		return $this->fillOneHole($classId, $day, $slot, false, false)
			|| $this->fillOneHole($classId, $day, $slot, true, false)
			|| $this->fillOneHole($classId, $day, $slot, true, true);
	}

	private function slideMorning(): int
	{
		$moved = 0;
		foreach ($this->holeList() as $hole) {
			$classId = (int) $hole['class_id'];
			$day = (int) $hole['day'];
			$slot = $hole['slot'];
			if (!$this->classFree($classId, $day, $this->mins((string) ($slot['start_time'] ?? '')), $this->mins((string) ($slot['end_time'] ?? '')), [])) {
				continue;
			}
			$candidates = [];
			foreach ($this->byId as $entry) {
				if ((int) ($entry['class_id'] ?? 0) !== $classId || !$this->isMorning((string) ($entry['start_time'] ?? ''))) {
					continue;
				}
				if (!$this->movable($entry, false)) {
					continue;
				}
				$same = (int) ($entry['day_of_week'] ?? -1) === $day
					&& substr((string) ($entry['start_time'] ?? ''), 0, 5) === substr((string) ($slot['start_time'] ?? ''), 0, 5);
				if ($same) {
					continue;
				}
				$candidates[] = $entry;
			}
			usort($candidates, function (array $a, array $b): int {
				return $this->candidateRank($a) <=> $this->candidateRank($b);
			});
			foreach ($candidates as $entry) {
				$group = $this->clockGroup($entry);
				if ((int) ($this->lowestId($group)) !== (int) ($entry['id'] ?? 0)) {
					continue;
				}
				if (!$this->canPlace($group, $day, $slot, [], false)) {
					continue;
				}
				if ($this->relocate($group, $day, $slot)) {
					$moved++;
					break;
				}
			}
		}
		return $moved;
	}

	private function swapIntoBlock(): int
	{
		$moved = 0;
		foreach ($this->coursePairs() as $pair) {
			$classId = $pair[0];
			$courseId = $pair[1];
			if ($this->courseFrozen($classId, $courseId) || !$this->courseIsExam($classId, $courseId)) {
				continue;
			}
			$n = $this->placedCount($classId, $courseId);
			if ($n < 2) {
				continue;
			}
			$cap = $this->runCap($n);
			$singles = 0;
			$movers = [];
			foreach ($this->runs($classId, $courseId) as $run) {
				if (count($run) === 1) {
					$singles++;
					$movers[] = $run[0];
				} elseif (count($run) > $cap) {
					$movers[] = $run[count($run) - 1];
				}
			}
			$wantSingles = $n < 4 ? ($n % 2) : 0;
			foreach ($movers as $mover) {
				if ($singles <= $wantSingles && $this->runLengthOf($classId, $courseId, $mover) <= $cap) {
					continue;
				}
				$group = $this->clockGroup($mover);
				if ((int) ($this->lowestId($group)) !== (int) ($mover['id'] ?? 0)) {
					continue;
				}
				$swap = $this->findSwap($group, $classId, $courseId, $cap);
				if ($swap === null) {
					continue;
				}
				if ($this->commitSwap($group, $swap['occupant'], (int) $swap['day'], $swap['slot'])) {
					$moved++;
					break;
				}
			}
		}
		return $moved;
	}

	/**
	 * @param list<array<string,mixed>> $group
	 * @return array{day:int,slot:array<string,mixed>,occupant:list<array<string,mixed>>}|null
	 */
	private function findSwap(array $group, int $classId, int $courseId, int $cap): ?array
	{
		$ignore = [];
		foreach ($group as $member) {
			$ignore[(int) $member['id']] = true;
		}
		$track = (string) ($this->tracks[$classId] ?? '');
		$origin = $this->current($group[0]);
		$originDay = (int) ($origin['day_of_week'] ?? -1);
		$originSlot = $this->slotByStart[$track][substr((string) ($origin['start_time'] ?? ''), 0, 5)] ?? null;
		if ($originSlot === null) {
			return null;
		}
		foreach ($this->runs($classId, $courseId) as $run) {
			$kept = [];
			foreach ($run as $period) {
				if (!isset($ignore[(int) ($period['id'] ?? 0)])) {
					$kept[] = $period;
				}
			}
			if ($kept === [] || count($kept) >= $cap) {
				continue;
			}
			$day = (int) ($kept[0]['day_of_week'] ?? -1);
			foreach ($this->neighborSlots($track, $kept) as $slot) {
				$start = $this->mins((string) ($slot['start_time'] ?? ''));
				$end = $this->mins((string) ($slot['end_time'] ?? ''));
				$occId = 0;
				foreach ($this->byId as $id => $row) {
					if ((int) ($row['class_id'] ?? 0) !== $classId || (int) ($row['day_of_week'] ?? -1) !== $day) {
						continue;
					}
					if ($this->mins((string) ($row['start_time'] ?? '')) === $start) {
						$occId = (int) $id;
						break;
					}
				}
				if ($occId <= 0 || isset($ignore[$occId])) {
					continue;
				}
				$occupant = $this->clockGroup($this->byId[$occId]);
				if ((int) ($this->lowestId($occupant)) !== $occId) {
					continue;
				}
				if ($this->runLengthOf((int) ($occupant[0]['class_id'] ?? 0), (int) ($occupant[0]['course_id'] ?? 0), $occupant[0]) !== 1) {
					continue;
				}
				$movableOcc = true;
				foreach ($occupant as $member) {
					if (!$this->movable($member, true)) {
						$movableOcc = false;
						break;
					}
				}
				if (!$movableOcc) {
					continue;
				}
				$both = $ignore;
				foreach ($occupant as $member) {
					$both[(int) $member['id']] = true;
				}
				$overflow = !$this->isMorning((string) ($originSlot['start_time'] ?? ''));
				if ($this->canPlace($group, $day, $slot, $both, false)
					&& $this->canPlace($occupant, $originDay, $originSlot, $both, $overflow)) {
					return ['day' => $day, 'slot' => $slot, 'occupant' => $occupant];
				}
			}
		}
		return null;
	}

	/**
	 * @param list<array<string,mixed>> $group
	 * @param list<array<string,mixed>> $occupant
	 */
	private function commitSwap(array $group, array $occupant, int $day, array $slot): bool
	{
		$origin = $this->current($group[0]);
		$track = (string) ($this->tracks[(int) ($origin['class_id'] ?? 0)] ?? '');
		$originSlot = $this->slotByStart[$track][substr((string) ($origin['start_time'] ?? ''), 0, 5)] ?? null;
		if ($originSlot === null) {
			return false;
		}
		$originDay = (int) ($origin['day_of_week'] ?? -1);
		$mark = $this->mark();
		$beforeHoles = $this->countHoles();
		$this->placeGroup($occupant, $originDay, $originSlot);
		$this->placeGroup($group, $day, $slot);
		$ids = array_merge($this->groupIds($group), $this->groupIds($occupant));
		if (!$this->idsClean($ids) || $this->countHoles() > $beforeHoles) {
			$this->revertTo($mark);
			return false;
		}
		return true;
	}

	/** @param list<array<string,mixed>> $run @return list<array<string,mixed>> */
	private function neighborSlots(string $track, array $run): array
	{
		$slots = $this->slotsByTrack[$track] ?? [];
		if ($run === [] || $slots === []) {
			return [];
		}
		$first = substr((string) ($run[0]['start_time'] ?? ''), 0, 5);
		$last = substr((string) ($run[count($run) - 1]['start_time'] ?? ''), 0, 5);
		$out = [];
		foreach ($slots as $i => $slot) {
			$start = substr((string) ($slot['start_time'] ?? ''), 0, 5);
			if ($start !== $first && $start !== $last) {
				continue;
			}
			foreach ([$i - 1, $i + 1] as $j) {
				if (!isset($slots[$j])) {
					continue;
				}
				$gap = abs($this->mins((string) ($slots[$j]['start_time'] ?? '')) - $this->mins((string) ($slot['end_time'] ?? '')));
				$gap2 = abs($this->mins((string) ($slot['start_time'] ?? '')) - $this->mins((string) ($slots[$j]['end_time'] ?? '')));
				if (min($gap, $gap2) <= 35) {
					$out[$start . '>' . substr((string) ($slots[$j]['start_time'] ?? ''), 0, 5)] = $slots[$j];
				}
			}
		}
		return array_values($out);
	}

	private function runLengthOf(int $classId, int $courseId, array $entry): int
	{
		return count($this->runContaining($classId, $courseId, $entry));
	}

	private function clusterSingles(): int
	{
		$moved = 0;
		foreach ($this->coursePairs() as $pair) {
			$classId = $pair[0];
			$courseId = $pair[1];
			if ($this->courseFrozen($classId, $courseId) || !$this->courseIsExam($classId, $courseId)) {
				continue;
			}
			$n = $this->placedCount($classId, $courseId);
			if ($n < 2) {
				continue;
			}
			$cap = $this->runCap($n);
			$singles = [];
			foreach ($this->runs($classId, $courseId) as $run) {
				if (count($run) === 1) {
					$singles[] = $run[0];
				}
			}
			$wantSingles = $n < 4 ? ($n % 2) : 0;
			if (count($singles) <= $wantSingles) {
				continue;
			}
			foreach ($singles as $mover) {
				if (isset($this->pinned[(int) ($mover['id'] ?? 0)])) {
					continue;
				}
				$group = $this->clockGroup($mover);
				if ((int) ($this->lowestId($group)) !== (int) ($mover['id'] ?? 0)) {
					continue;
				}
				$plan = $this->planCluster($group, $classId, $courseId, $cap);
				if ($plan === null) {
					continue;
				}
				if ($this->applyCluster($plan)) {
					$moved++;
					break;
				}
			}
		}
		return $moved;
	}

	/**
	 * Move a single next to the rest of its course.
	 * The class already in that cell, and the teacher's other class at that time, take the single's old cell.
	 *
	 * @param list<array<string,mixed>> $group
	 * @return list<array{group:list<array<string,mixed>>,day:int,slot:array<string,mixed>}>|null
	 */
	private function planCluster(array $group, int $classId, int $courseId, int $cap): ?array
	{
		$ignore = [];
		foreach ($group as $member) {
			$ignore[(int) $member['id']] = true;
			if (isset($this->pinned[(int) $member['id']])) {
				return null;
			}
		}
		$origin = $this->current($group[0]);
		$originDay = (int) ($origin['day_of_week'] ?? -1);
		$track = (string) ($this->tracks[$classId] ?? '');
		$originSlot = $this->slotByStart[$track][substr((string) ($origin['start_time'] ?? ''), 0, 5)] ?? null;
		if ($originSlot === null) {
			return null;
		}
		foreach ($this->runs($classId, $courseId) as $run) {
			$kept = [];
			foreach ($run as $period) {
				if (!isset($ignore[(int) ($period['id'] ?? 0)])) {
					$kept[] = $period;
				}
			}
			if ($kept === [] || count($kept) >= $cap) {
				continue;
			}
			$day = (int) ($kept[0]['day_of_week'] ?? -1);
			foreach ($this->neighborSlots($track, $kept) as $slot) {
				$start = $this->mins((string) ($slot['start_time'] ?? ''));
				$end = $this->mins((string) ($slot['end_time'] ?? ''));
				$plan = [['group' => $group, 'day' => $day, 'slot' => $slot]];
				$both = $ignore;
				$occId = 0;
				foreach ($this->byId as $id => $row) {
					if ((int) ($row['class_id'] ?? 0) !== $classId || (int) ($row['day_of_week'] ?? -1) !== $day) {
						continue;
					}
					if ($this->mins((string) ($row['start_time'] ?? '')) === $start) {
						$occId = (int) $id;
						break;
					}
				}
				if ($occId > 0) {
					$occupant = $this->clockGroup($this->byId[$occId]);
					if (!$this->clusterMoverOk($occupant)) {
						continue;
					}
					foreach ($occupant as $member) {
						$both[(int) $member['id']] = true;
					}
					$plan[] = ['group' => $occupant, 'day' => $originDay, 'slot' => $originSlot];
				}
				$staffId = (int) ($origin['staff_id'] ?? 0);
				$seenStaff = [];
				foreach ($this->staffOverlapIds($staffId, $day, $start, $end, $both) as $hitId) {
					if (isset($seenStaff[$hitId])) {
						continue;
					}
					$hit = $this->clockGroup($this->byId[$hitId]);
					if (!$this->clusterMoverOk($hit)) {
						continue 2;
					}
					foreach ($hit as $member) {
						$seenStaff[(int) $member['id']] = true;
						$both[(int) $member['id']] = true;
					}
					$plan[] = ['group' => $hit, 'day' => $originDay, 'slot' => $originSlot];
				}
				if (!$this->sameSlotGroupsFit($plan, $originDay, $originSlot)) {
					continue;
				}
				$ok = true;
				foreach ($plan as $step) {
					$overflow = (int) $step['day'] === $originDay && !$this->isMorning((string) ($originSlot['start_time'] ?? ''));
					if (!$this->canPlace($step['group'], (int) $step['day'], $step['slot'], $both, $overflow)) {
						$ok = false;
						break;
					}
				}
				if ($ok) {
					return $plan;
				}
			}
		}
		return null;
	}

	/** @param list<array<string,mixed>> $group */
	private function clusterMoverOk(array $group): bool
	{
		if ($group === []) {
			return false;
		}
		foreach ($group as $member) {
			if (isset($this->pinned[(int) ($member['id'] ?? 0)]) || !$this->movable($member, true)) {
				return false;
			}
			if ($this->runLengthOf((int) ($member['class_id'] ?? 0), (int) ($member['course_id'] ?? 0), $member) !== 1) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Several lessons may arrive at the vacated cell only when they are different classes and different teachers.
	 *
	 * @param list<array{group:list<array<string,mixed>>,day:int,slot:array<string,mixed>}> $plan
	 */
	private function sameSlotGroupsFit(array $plan, int $day, array $slot): bool
	{
		$arriving = [];
		foreach ($plan as $step) {
			if ((int) $step['day'] !== $day) {
				continue;
			}
			if (substr((string) ($step['slot']['start_time'] ?? ''), 0, 5) !== substr((string) ($slot['start_time'] ?? ''), 0, 5)) {
				continue;
			}
			$arriving[] = $step['group'];
		}
		for ($i = 0; $i < count($arriving); $i++) {
			for ($j = $i + 1; $j < count($arriving); $j++) {
				foreach ($arriving[$i] as $a) {
					foreach ($arriving[$j] as $b) {
						if ((int) ($a['class_id'] ?? 0) === (int) ($b['class_id'] ?? 0)) {
							return false;
						}
						if ((int) ($a['staff_id'] ?? 0) > 0 && (int) ($a['staff_id'] ?? 0) === (int) ($b['staff_id'] ?? 0)) {
							return false;
						}
					}
				}
			}
		}
		return true;
	}

	/** @param list<array{group:list<array<string,mixed>>,day:int,slot:array<string,mixed>}> $plan */
	private function applyCluster(array $plan): bool
	{
		$mark = $this->mark();
		$beforeHoles = $this->countHoles();
		$ids = [];
		foreach ($plan as $step) {
			if (!$this->placeGroup($step['group'], (int) $step['day'], $step['slot'])) {
				$this->revertTo($mark);
				return false;
			}
			foreach ($this->groupIds($step['group']) as $id) {
				$ids[] = $id;
			}
		}
		if (!$this->idsClean($ids) || $this->countHoles() > $beforeHoles) {
			$this->revertTo($mark);
			return false;
		}
		foreach ($ids as $id) {
			$this->pinned[(int) $id] = true;
		}
		return true;
	}

	private function chainFill(): int
	{
		$moved = 0;
		foreach ($this->holeList() as $hole) {
			$classId = (int) $hole['class_id'];
			$day = (int) $hole['day'];
			$slot = $hole['slot'];
			if (!$this->classFree($classId, $day, $this->mins((string) ($slot['start_time'] ?? '')), $this->mins((string) ($slot['end_time'] ?? '')), [])) {
				continue;
			}
			$tried = 0;
			foreach ($this->byId as $entry) {
				if ($tried >= 8) {
					break;
				}
				if ((int) ($entry['class_id'] ?? 0) !== $classId || $this->isMorning((string) ($entry['start_time'] ?? ''))) {
					continue;
				}
				if (!$this->movable($entry, false) || !$this->isExam($entry)) {
					continue;
				}
				$group = $this->clockGroup($entry);
				if ((int) ($this->lowestId($group)) !== (int) ($entry['id'] ?? 0)) {
					continue;
				}
				$tried++;
				$start = $this->mins((string) ($slot['start_time'] ?? ''));
				$end = $this->mins((string) ($slot['end_time'] ?? ''));
				$ignore = [];
				foreach ($group as $member) {
					$ignore[(int) $member['id']] = true;
				}
				$staffId = (int) ($entry['staff_id'] ?? 0);
				$blockerIds = $this->staffOverlapIds($staffId, $day, $start, $end, $ignore);
				if ($blockerIds === []) {
					continue;
				}
				$blocker = $this->byId[$blockerIds[0]] ?? null;
				if (!is_array($blocker)) {
					continue;
				}
				$blockerGroup = $this->clockGroup($blocker);
				$blockerIdMap = [];
				foreach ($blockerGroup as $member) {
					$blockerIdMap[(int) $member['id']] = true;
				}
				$covered = true;
				foreach ($blockerIds as $blockerId) {
					if (!isset($blockerIdMap[$blockerId])) {
						$covered = false;
						break;
					}
				}
				if (!$covered) {
					continue;
				}
				foreach ($blockerGroup as $member) {
					if (!$this->movable($member, true)) {
						$covered = false;
						break;
					}
				}
				if (!$covered) {
					continue;
				}
				$destDay = (int) ($entry['day_of_week'] ?? -1);
				$destSlot = $this->slotByStart[(string) ($this->tracks[$classId] ?? '')][substr((string) ($entry['start_time'] ?? ''), 0, 5)] ?? null;
				if ($destSlot === null) {
					continue;
				}
				$both = $ignore + $blockerIdMap;
				if (!$this->canPlace($blockerGroup, $destDay, $destSlot, $both, true)) {
					continue;
				}
				if (!$this->canPlace($group, $day, $slot, $both, false)) {
					continue;
				}
				$mark = $this->mark();
				$this->placeGroup($blockerGroup, $destDay, $destSlot);
				$this->placeGroup($group, $day, $slot);
				$refilled = true;
				foreach ($blockerGroup as $member) {
					$memberClass = (int) ($member['class_id'] ?? 0);
					$memberTrack = (string) ($this->tracks[$memberClass] ?? '');
					$memberSlot = $this->slotByStart[$memberTrack][substr((string) ($slot['start_time'] ?? ''), 0, 5)] ?? null;
					if ($memberSlot === null) {
						$refilled = false;
						break;
					}
					if (!$this->classFree(
						$memberClass,
						$day,
						$this->mins((string) ($memberSlot['start_time'] ?? '')),
						$this->mins((string) ($memberSlot['end_time'] ?? '')),
						[]
					)) {
						continue;
					}
					if (!$this->fillOpenedHole($memberClass, $day, $memberSlot)) {
						$refilled = false;
						break;
					}
				}
				$check = array_merge(array_keys($both), $this->freshIdsSince($mark));
				if (!$refilled || !$this->idsClean($check)) {
					$this->revertTo($mark);
					continue;
				}
				$moved++;
				break;
			}
		}
		return $moved;
	}

	/**
	 * Fill a morning hole by moving the lessons that block a combined class
	 * or its teacher into an afternoon cell, then seating the waiting lesson.
	 * The move is kept only when the morning-hole count goes down.
	 */
	private function seatByClearing(): int
	{
		$moved = 0;
		for ($guard = 0; $guard < 40; $guard++) {
			$did = false;
			foreach ($this->holeList() as $hole) {
				if ($this->clearIntoHole((int) $hole['class_id'], (int) $hole['day'], $hole['slot'])) {
					$moved++;
					$did = true;
					break;
				}
			}
			if (!$did) {
				break;
			}
		}
		return $moved;
	}

	/** @param array<string,mixed> $slot */
	private function clearIntoHole(int $classId, int $day, array $slot): bool
	{
		$groups = [];
		$seen = [];
		foreach ($this->byId as $entry) {
			if ((int) ($entry['class_id'] ?? 0) !== $classId || $this->isMorning((string) ($entry['start_time'] ?? ''))) {
				continue;
			}
			if (!$this->movable($entry, true)) {
				continue;
			}
			$group = $this->clockGroup($entry);
			$lid = $this->lowestId($group);
			if (isset($seen[$lid])) {
				continue;
			}
			$seen[$lid] = true;
			$groups[] = $group;
		}
		usort($groups, function (array $a, array $b): int {
			$exam = ((int) $this->isExam($a[0])) <=> ((int) $this->isExam($b[0]));
			if ($exam !== 0) {
				return -$exam;
			}
			return count($a) <=> count($b);
		});
		foreach ($groups as $group) {
			if ($this->applyClear($group, $day, $slot)) {
				return true;
			}
		}
		return false;
	}

	/** @param list<array<string,mixed>> $group @param array<string,mixed> $slot */
	private function applyClear(array $group, int $day, array $slot): bool
	{
		$overflow = !$this->isExam($group[0]);
		$candIds = [];
		$candClass = [];
		foreach ($group as $member) {
			$candIds[(int) ($member['id'] ?? 0)] = true;
			$candClass[(int) ($member['class_id'] ?? 0)] = true;
		}
		$occupiers = [];
		$startHm = substr((string) ($slot['start_time'] ?? ''), 0, 5);
		foreach ($group as $member) {
			$memberClass = (int) ($member['class_id'] ?? 0);
			$track = (string) ($this->tracks[$memberClass] ?? '');
			$partnerSlot = $this->slotByStart[$track][$startHm] ?? null;
			if ($partnerSlot === null) {
				return false;
			}
			$start = $this->mins((string) ($partnerSlot['start_time'] ?? ''));
			$end = $this->mins((string) ($partnerSlot['end_time'] ?? ''));
			if ($this->classFree($memberClass, $day, $start, $end, $candIds)) {
				continue;
			}
			$occ = $this->occupantAt($memberClass, $day, $startHm);
			if ($occ === []) {
				return false;
			}
			$occupiers[$this->lowestId($occ)] = $occ;
		}
		$staffId = (int) ($group[0]['staff_id'] ?? 0);
		foreach ($this->staffOverlapIds(
			$staffId,
			$day,
			$this->mins((string) ($slot['start_time'] ?? '')),
			$this->mins((string) ($slot['end_time'] ?? '')),
			$candIds
		) as $overlapId) {
			$other = $this->byId[$overlapId] ?? null;
			if (!is_array($other)) {
				return false;
			}
			$joined = false;
			foreach ($group as $member) {
				if (SecondaryTimetableCriteria::entriesAreCombinedLesson($this->combineRow($member), $this->combineRow($other))) {
					$joined = true;
					break;
				}
			}
			if ($joined) {
				continue;
			}
			$occ = $this->clockGroup($other);
			$occupiers[$this->lowestId($occ)] = $occ;
		}
		if ($occupiers === [] || count($occupiers) > 4) {
			return false;
		}
		$used = $candIds;
		$moves = [];
		foreach ($occupiers as $occ) {
			foreach ($occ as $member) {
				if (!$this->movable($member, true)) {
					return false;
				}
				$used[(int) ($member['id'] ?? 0)] = true;
			}
			$exposed = [];
			foreach ($occ as $member) {
				$occClass = (int) ($member['class_id'] ?? 0);
				if (!isset($candClass[$occClass])) {
					$exposed[$occClass] = true;
				}
			}
			if ($exposed === []) {
				$seat = $this->firstAfternoonSeat($occ, $used, $moves);
				if ($seat === null) {
					return false;
				}
				$moves[] = ['group' => $occ, 'day' => (int) $seat['day'], 'slot' => $seat['slot']];
				continue;
			}
			$swap = $this->findReplacement($occ, $exposed, $day, $slot, $used);
			if ($swap === null) {
				return false;
			}
			foreach ($swap['group'] as $member) {
				if (!$this->movable($member, true)) {
					return false;
				}
				$used[(int) ($member['id'] ?? 0)] = true;
			}
			$moves[] = ['group' => $swap['group'], 'day' => $day, 'slot' => $slot];
			$moves[] = ['group' => $occ, 'day' => (int) $swap['day'], 'slot' => $swap['slot']];
		}
		if (!$this->canPlace($group, $day, $slot, $used, $overflow) || $this->planHits($group, $day, $slot, $moves)) {
			return false;
		}
		$mark = $this->mark();
		$before = $this->countHoles();
		foreach ($moves as $step) {
			if (!$this->placeGroup($step['group'], (int) $step['day'], $step['slot'])) {
				$this->revertTo($mark);
				return false;
			}
		}
		if (!$this->placeGroup($group, $day, $slot)) {
			$this->revertTo($mark);
			return false;
		}
		$ids = $this->groupIds($group);
		foreach ($moves as $step) {
			foreach ($this->groupIds($step['group']) as $id) {
				$ids[] = $id;
			}
		}
		if (!$this->idsClean($ids) || $this->countHoles() >= $before) {
			$this->revertTo($mark);
			return false;
		}
		return true;
	}

	/**
	 * @param list<array<string,mixed>> $group
	 * @param array<int,true> $ignore
	 * @param list<array{group:list<array<string,mixed>>,day:int,slot:array<string,mixed>}> $moves
	 * @return array{day:int,slot:array<string,mixed>}|null
	 */
	private function firstAfternoonSeat(array $group, array $ignore, array $moves): ?array
	{
		$track = (string) ($this->tracks[(int) ($group[0]['class_id'] ?? 0)] ?? '');
		$tried = 0;
		foreach ([0, 1, 2, 3, 4] as $day) {
			foreach ($this->slotsByTrack[$track] ?? [] as $slot) {
				if ($this->isMorning((string) ($slot['start_time'] ?? ''))) {
					continue;
				}
				if (++$tried > 40) {
					return null;
				}
				if ($this->planHits($group, $day, $slot, $moves)) {
					continue;
				}
				if ($this->canPlace($group, $day, $slot, $ignore, true)) {
					return ['day' => $day, 'slot' => $slot];
				}
			}
		}
		return null;
	}

	/**
	 * Afternoon lesson that can take the morning clock while the blocking
	 * lesson takes its old cell. Every exposed class must travel with it.
	 *
	 * @param list<array<string,mixed>> $occupier
	 * @param array<int,true> $exposed
	 * @param array<string,mixed> $morningSlot
	 * @param array<int,true> $ignore
	 * @return array{group:list<array<string,mixed>>,day:int,slot:array<string,mixed>}|null
	 */
	private function findReplacement(array $occupier, array $exposed, int $day, array $morningSlot, array $ignore): ?array
	{
		$anchor = (int) array_key_first($exposed);
		$seen = [];
		foreach ($this->byId as $entry) {
			if ((int) ($entry['class_id'] ?? 0) !== $anchor || $this->isMorning((string) ($entry['start_time'] ?? ''))) {
				continue;
			}
			if (!$this->movable($entry, true)) {
				continue;
			}
			$rep = $this->clockGroup($entry);
			$lid = $this->lowestId($rep);
			if (isset($seen[$lid]) || isset($ignore[$lid])) {
				continue;
			}
			$seen[$lid] = true;
			$covered = [];
			foreach ($rep as $member) {
				$covered[(int) ($member['class_id'] ?? 0)] = true;
			}
			foreach (array_keys($exposed) as $classId) {
				if (!isset($covered[$classId])) {
					continue 2;
				}
			}
			$repDay = (int) ($rep[0]['day_of_week'] ?? -1);
			$repTrack = (string) ($this->tracks[(int) ($rep[0]['class_id'] ?? 0)] ?? '');
			$repSlot = $this->slotByStart[$repTrack][substr((string) ($rep[0]['start_time'] ?? ''), 0, 5)] ?? null;
			if ($repSlot === null) {
				continue;
			}
			$repIgnore = $ignore;
			foreach ($rep as $member) {
				$repIgnore[(int) ($member['id'] ?? 0)] = true;
			}
			$repOverflow = !$this->isExam($rep[0]);
			if (!$this->canPlace($rep, $day, $morningSlot, $repIgnore, $repOverflow)) {
				continue;
			}
			if (!$this->canPlace($occupier, $repDay, $repSlot, $repIgnore, true)) {
				continue;
			}
			return ['group' => $rep, 'day' => $repDay, 'slot' => $repSlot];
		}
		return null;
	}

	/**
	 * @param list<array<string,mixed>> $group
	 * @param array<string,mixed> $slot
	 * @param list<array{group:list<array<string,mixed>>,day:int,slot:array<string,mixed>}> $moves
	 */
	private function planHits(array $group, int $day, array $slot, array $moves): bool
	{
		$start = substr((string) ($slot['start_time'] ?? ''), 0, 5);
		$staffId = (int) ($group[0]['staff_id'] ?? 0);
		$classes = [];
		foreach ($group as $member) {
			$classes[(int) ($member['class_id'] ?? 0)] = true;
		}
		foreach ($moves as $step) {
			if ((int) $step['day'] !== $day || substr((string) ($step['slot']['start_time'] ?? ''), 0, 5) !== $start) {
				continue;
			}
			if ($staffId > 0 && (int) ($step['group'][0]['staff_id'] ?? 0) === $staffId) {
				$joined = false;
				foreach ($group as $member) {
					if (SecondaryTimetableCriteria::entriesAreCombinedLesson($this->combineRow($member), $this->combineRow($step['group'][0]))) {
						$joined = true;
						break;
					}
				}
				if (!$joined) {
					return true;
				}
			}
			foreach ($step['group'] as $member) {
				if (isset($classes[(int) ($member['class_id'] ?? 0)])) {
					return true;
				}
			}
		}
		return false;
	}

	/** @return list<array<string,mixed>> */
	private function occupantAt(int $classId, int $day, string $startHm): array
	{
		foreach ($this->byId as $entry) {
			if ((int) ($entry['class_id'] ?? 0) !== $classId || (int) ($entry['day_of_week'] ?? -1) !== $day) {
				continue;
			}
			if (substr((string) ($entry['start_time'] ?? ''), 0, 5) !== $startHm) {
				continue;
			}
			return $this->clockGroup($entry);
		}
		return [];
	}

	private function splitOversized(): int
	{
		$moved = 0;
		foreach ($this->coursePairs() as $pair) {
			$classId = $pair[0];
			$courseId = $pair[1];
			if ($this->courseFrozen($classId, $courseId)) {
				continue;
			}
			$n = $this->placedCount($classId, $courseId);
			$cap = $this->runCap($n);
			foreach ($this->runs($classId, $courseId) as $run) {
				if (count($run) <= $cap) {
					continue;
				}
				$extra = $run[count($run) - 1];
				$group = $this->clockGroup($extra);
				if ((int) ($this->lowestId($group)) !== (int) ($extra['id'] ?? 0)) {
					continue;
				}
				$dest = $this->findSeat($group, $classId, $courseId, $cap, false);
				if ($dest === null) {
					continue;
				}
				if ($this->relocate($group, (int) $dest['day'], $dest['slot'])) {
					$moved++;
					break;
				}
			}
		}
		return $moved;
	}

	private function joinIsolates(): int
	{
		$moved = 0;
		foreach ($this->coursePairs() as $pair) {
			$classId = $pair[0];
			$courseId = $pair[1];
			if ($this->courseFrozen($classId, $courseId)) {
				continue;
			}
			$n = $this->placedCount($classId, $courseId);
			if ($n < 2) {
				continue;
			}
			$lengths = [];
			$singles = [];
			foreach ($this->runs($classId, $courseId) as $run) {
				$lengths[] = count($run);
				if (count($run) === 1) {
					$singles[] = $run[0];
				}
			}
			$wantSingles = $n < 4 ? ($n % 2) : 0;
			if (count($singles) <= $wantSingles) {
				continue;
			}
			usort($singles, function (array $a, array $b): int {
				$am = $this->isMorning((string) ($a['start_time'] ?? '')) ? 1 : 0;
				$bm = $this->isMorning((string) ($b['start_time'] ?? '')) ? 1 : 0;
				return $am <=> $bm;
			});
			foreach ($singles as $single) {
				$group = $this->clockGroup($single);
				if ((int) ($this->lowestId($group)) !== (int) ($single['id'] ?? 0)) {
					continue;
				}
				$dest = $this->findSeat($group, $classId, $courseId, $this->runCap($n), true);
				if ($dest === null) {
					continue;
				}
				if ($this->relocate($group, (int) $dest['day'], $dest['slot'])) {
					$moved++;
					break;
				}
			}
		}
		return $moved;
	}

	/**
	 * @param list<array<string,mixed>> $group
	 * @return array{day:int,slot:array<string,mixed>}|null
	 */
	private function findSeat(array $group, int $classId, int $courseId, int $cap, bool $mustTouch): ?array
	{
		$ignore = [];
		foreach ($group as $member) {
			$ignore[(int) $member['id']] = true;
		}
		$track = (string) ($this->tracks[$classId] ?? '');
		$best = null;
		$bestScore = 9999;
		foreach ([0, 1, 2, 3, 4] as $day) {
			foreach ($this->slotsByTrack[$track] ?? [] as $slot) {
				$overflow = $this->prefersAfternoon($group[0]);
				if (!$this->isMorning((string) ($slot['start_time'] ?? ''))) {
					$alreadyAfternoon = true;
					foreach ($group as $member) {
						$member = $this->current($member);
						if ((int) ($member['day_of_week'] ?? -1) >= 0 && $this->isMorning((string) ($member['start_time'] ?? ''))) {
							$alreadyAfternoon = false;
							break;
						}
					}
					if ($alreadyAfternoon) {
						$overflow = true;
					}
				}
				if (!$this->canPlace($group, $day, $slot, $ignore, $overflow)) {
					continue;
				}
				$touch = $this->touchesRun($classId, $courseId, $day, $slot, $ignore);
				$dayCount = $this->dayCount($classId, $courseId, $day, $ignore);
				if ($mustTouch && !$touch) {
					continue;
				}
				if ($dayCount > 0 && !$touch) {
					continue;
				}
				if ($touch) {
					$runLen = $this->touchedRunLength($classId, $courseId, $day, $slot, $ignore);
					if ($runLen + 1 > $cap) {
						continue;
					}
				} elseif ($dayCount + 1 > $cap) {
					continue;
				}
				$score = ($this->isMorning((string) ($slot['start_time'] ?? '')) ? 0 : 20) + ($touch ? 0 : 5);
				if ($this->isExam($group[0]) && !$this->isMorning((string) ($slot['start_time'] ?? ''))) {
					$score += 30;
				}
				if ($score < $bestScore) {
					$bestScore = $score;
					$best = ['day' => $day, 'slot' => $slot];
				}
			}
		}
		return $best;
	}

	/** @param list<array<string,mixed>> $group */
	private function relocate(array $group, int $day, array $slot): bool
	{
		$vacated = [];
		foreach ($group as $member) {
			if ($this->isMorning((string) ($member['start_time'] ?? ''))) {
				$track = (string) ($this->tracks[(int) ($member['class_id'] ?? 0)] ?? '');
				$old = $this->slotByStart[$track][substr((string) ($member['start_time'] ?? ''), 0, 5)] ?? null;
				if ($old !== null) {
					$vacated[] = [
						'class_id' => (int) $member['class_id'],
						'day' => (int) $member['day_of_week'],
						'slot' => $old,
					];
				}
			}
		}
		$mark = $this->mark();
		$this->placeGroup($group, $day, $slot);
		foreach ($vacated as $hole) {
			if (!$this->fillOpenedHole((int) $hole['class_id'], (int) $hole['day'], $hole['slot'])) {
				$this->revertTo($mark);
				return false;
			}
		}
		if (!$this->idsClean(array_merge($this->groupIds($group), $this->freshIdsSince($mark)))) {
			$this->revertTo($mark);
			return false;
		}
		return true;
	}

	/** @param list<array<string,mixed>> $group @param array<int,true> $ignore */
	private function canPlace(array $group, int $day, array $slotTemplate, array $ignore, bool $overflow): bool
	{
		if ($group === [] || $day < 0 || $day > 4) {
			return false;
		}
		$startHm = substr((string) ($slotTemplate['start_time'] ?? ''), 0, 5);
		$ids = $ignore;
		foreach ($group as $entry) {
			$ids[(int) ($entry['id'] ?? 0)] = true;
		}
		$staffId = (int) ($group[0]['staff_id'] ?? 0);
		if ($this->isPartTime($staffId)) {
			return false;
		}
		$staffStart = 0;
		$staffEnd = 0;
		$this->criteria->setOverflowFill($overflow);
		try {
			foreach ($group as $entry) {
				if ($this->isPartTime((int) ($entry['staff_id'] ?? 0))) {
					return false;
				}
				$classId = (int) ($entry['class_id'] ?? 0);
				if (!$this->classAllowed($classId)) {
					return false;
				}
				$track = (string) ($this->tracks[$classId] ?? '');
				if ($track === '' || $track === 'nursery') {
					return false;
				}
				$slot = $this->slotByStart[$track][$startHm] ?? null;
				if ($slot === null) {
					return false;
				}
				if (isset($this->special[$track][$day . ':' . (int) ($slot['id'] ?? 0)])) {
					return false;
				}
				$start = $this->mins((string) ($slot['start_time'] ?? ''));
				$end = $this->mins((string) ($slot['end_time'] ?? ''));
				if ($end <= $start) {
					return false;
				}
				if (!$this->classFree($classId, $day, $start, $end, $ids)) {
					return false;
				}
				if (!$this->criteria->slotAllowed($this->metaFor($entry), $day, (string) ($slot['start_time'] ?? ''), (string) ($slot['end_time'] ?? ''))) {
					return false;
				}
				$staffStart = $start;
				$staffEnd = $end;
			}
		} finally {
			$this->criteria->setOverflowFill(false);
		}
		return $this->staffAllowsGroup($group, $day, $staffStart, $staffEnd, $ids);
	}

	/**
	 * A teacher may share a clock with a combined partner class.
	 * Any other overlap is a real clash.
	 *
	 * @param list<array<string,mixed>> $group
	 * @param array<int,true> $ignore
	 */
	private function staffAllowsGroup(array $group, int $day, int $start, int $end, array $ignore): bool
	{
		$staffId = (int) ($group[0]['staff_id'] ?? 0);
		foreach ($this->staffOverlapIds($staffId, $day, $start, $end, $ignore) as $id) {
			$other = $this->byId[$id] ?? null;
			if (!is_array($other)) {
				return false;
			}
			$joined = false;
			foreach ($group as $entry) {
				if (SecondaryTimetableCriteria::entriesAreCombinedLesson($this->combineRow($entry), $this->combineRow($other))) {
					$joined = true;
					break;
				}
			}
			if (!$joined) {
				return false;
			}
		}
		return true;
	}

	/** @param list<array<string,mixed>> $group */
	private function placeGroup(array $group, int $day, array $slotTemplate): bool
	{
		$startHm = substr((string) ($slotTemplate['start_time'] ?? ''), 0, 5);
		$plans = [];
		foreach ($group as $entry) {
			$current = $this->current($entry);
			$track = (string) ($this->tracks[(int) ($current['class_id'] ?? 0)] ?? '');
			$slot = $this->slotByStart[$track][$startHm] ?? null;
			if ($slot === null) {
				return false;
			}
			$plans[] = [$current, $slot];
		}
		foreach ($plans as $plan) {
			$this->remember($plan[0]);
			$this->write($plan[0], $day, $plan[1]);
			$this->moved++;
		}
		return true;
	}

	/** @param array<string,mixed> $entry @param array<string,mixed> $slot */
	private function write(array $entry, int $day, array $slot): void
	{
		$id = (int) ($entry['id'] ?? 0);
		$start = substr((string) ($slot['start_time'] ?? ''), 0, 5);
		$end = (string) ($slot['end_time'] ?? '');
		$update = [
			'day_of_week' => $day,
			'slot_id' => (int) ($slot['id'] ?? 0),
		];
		if ($this->lockColumn) {
			$update['is_locked'] = 1;
		}
		$this->db->table('timetable_entries')->where('id', $id)->update($update);
		$row = $entry;
		$row['day_of_week'] = $day;
		$row['slot_id'] = (int) ($slot['id'] ?? 0);
		$row['start_time'] = $start . ':00';
		$row['end_time'] = strlen($end) === 5 ? $end . ':00' : $end;
		$row['is_locked'] = 1;
		$this->byId[$id] = $row;
		unset($this->parked[$id]);
	}

	/** @param array<string,mixed> $entry */
	private function remember(array $entry): void
	{
		$id = (int) ($entry['id'] ?? 0);
		$this->undo[] = [
			'id' => $id,
			'day' => (int) ($entry['day_of_week'] ?? -1),
			'slot_id' => (int) ($entry['slot_id'] ?? 0),
			'start_time' => (string) ($entry['start_time'] ?? ''),
			'end_time' => (string) ($entry['end_time'] ?? ''),
			'is_locked' => (int) ($entry['is_locked'] ?? 0),
			'was_parked' => (int) ($entry['day_of_week'] ?? -1) < 0 || (int) ($entry['slot_id'] ?? 0) <= 0,
			'entry' => $entry,
		];
	}

	private function mark(): int
	{
		return count($this->undo);
	}

	private function revertTo(int $mark): void
	{
		while (count($this->undo) > $mark) {
			$old = array_pop($this->undo);
			$id = (int) ($old['id'] ?? 0);
			if (!empty($old['was_parked'])) {
				$update = ['day_of_week' => -1, 'slot_id' => 0];
				if ($this->lockColumn) {
					$update['is_locked'] = (int) ($old['is_locked'] ?? 0);
				}
				$this->db->table('timetable_entries')->where('id', $id)->update($update);
				unset($this->byId[$id]);
				$entry = $old['entry'];
				$entry['day_of_week'] = -1;
				$entry['slot_id'] = 0;
				$this->parked[$id] = $entry;
				$this->moved = max(0, $this->moved - 1);
				continue;
			}
			$slot = [
				'id' => (int) $old['slot_id'],
				'start_time' => (string) $old['start_time'],
				'end_time' => (string) $old['end_time'],
			];
			$row = $this->byId[$id] ?? $old['entry'];
			$this->write($row, (int) $old['day'], $slot);
			if ($this->lockColumn) {
				$this->db->table('timetable_entries')->where('id', $id)->update(['is_locked' => (int) $old['is_locked']]);
				$this->byId[$id]['is_locked'] = (int) $old['is_locked'];
			}
			$this->moved = max(0, $this->moved - 1);
		}
	}

	/** @return list<int> */
	private function freshIdsSince(int $mark): array
	{
		$ids = [];
		for ($i = $mark; $i < count($this->undo); $i++) {
			$ids[] = (int) ($this->undo[$i]['id'] ?? 0);
		}
		return $ids;
	}

	/** @param list<int> $ids */
	private function idsClean(array $ids): bool
	{
		foreach ($ids as $id) {
			$entry = $this->byId[(int) $id] ?? null;
			if (!is_array($entry)) {
				continue;
			}
			$day = (int) ($entry['day_of_week'] ?? -1);
			$start = $this->mins((string) ($entry['start_time'] ?? ''));
			$end = $this->mins((string) ($entry['end_time'] ?? ''));
			if ($day < 0 || $end <= $start) {
				return false;
			}
			$ignore = [(int) $id => true];
			foreach ($this->clockGroup($entry) as $member) {
				$ignore[(int) $member['id']] = true;
			}
			if (!$this->classFree((int) $entry['class_id'], $day, $start, $end, $ignore)) {
				return false;
			}
			if (!$this->staffAllowsGroup([$entry], $day, $start, $end, $ignore)) {
				return false;
			}
		}
		return true;
	}

	/** @param array<int,true> $ignore */
	private function classFree(int $classId, int $day, int $start, int $end, array $ignore): bool
	{
		foreach ($this->byId as $id => $row) {
			if (isset($ignore[(int) $id])) {
				continue;
			}
			if ((int) ($row['class_id'] ?? 0) !== $classId || (int) ($row['day_of_week'] ?? -1) !== $day) {
				continue;
			}
			$otherStart = $this->mins((string) ($row['start_time'] ?? ''));
			$otherEnd = $this->mins((string) ($row['end_time'] ?? ''));
			if ($start < $otherEnd && $otherStart < $end) {
				return false;
			}
		}
		return true;
	}

	/** @param array<int,true> $ignore */
	private function staffFree(int $staffId, int $day, int $start, int $end, array $ignore): bool
	{
		return $this->staffOverlapIds($staffId, $day, $start, $end, $ignore) === [];
	}

	/**
	 * @param array<int,true> $ignore
	 * @return list<int>
	 */
	private function staffOverlapIds(int $staffId, int $day, int $start, int $end, array $ignore): array
	{
		if ($staffId <= 0) {
			return [];
		}
		$ids = [];
		foreach ($this->byId as $id => $row) {
			if (isset($ignore[(int) $id]) || (int) ($row['staff_id'] ?? 0) !== $staffId || (int) ($row['day_of_week'] ?? -1) !== $day) {
				continue;
			}
			$otherStart = $this->mins((string) ($row['start_time'] ?? ''));
			$otherEnd = $this->mins((string) ($row['end_time'] ?? ''));
			if ($start < $otherEnd && $otherStart < $end) {
				$ids[] = (int) $id;
			}
		}
		return $ids;
	}

	/** @return list<array{class_id:int,day:int,slot:array<string,mixed>}> */
	private function holeList(): array
	{
		$out = [];
		foreach ($this->tracks as $classId => $track) {
			if (!$this->classAllowed((int) $classId) || $track === 'nursery') {
				continue;
			}
			foreach ([0, 1, 2, 3, 4] as $day) {
				foreach ($this->morning[$track] ?? [] as $slot) {
					$slotId = (int) ($slot['id'] ?? 0);
					if (isset($this->special[$track][$day . ':' . $slotId])) {
						continue;
					}
					$start = $this->mins((string) ($slot['start_time'] ?? ''));
					$end = $this->mins((string) ($slot['end_time'] ?? ''));
					if ($this->classFree((int) $classId, $day, $start, $end, [])) {
						$out[] = ['class_id' => (int) $classId, 'day' => $day, 'slot' => $slot];
					}
				}
			}
		}
		return $out;
	}

	private function countHoles(): int
	{
		return count($this->holeList());
	}

	/**
	 * @return list<array{day:int,slots:list<array<string,mixed>>}>
	 */
	private function morningSequences(string $track, int $length, int $preferDay): array
	{
		$slots = $this->morning[$track] ?? [];
		$found = [];
		foreach ([0, 1, 2, 3, 4] as $day) {
			$count = count($slots);
			for ($i = 0; $i <= $count - $length; $i++) {
				$seq = array_slice($slots, $i, $length);
				if (!$this->consecutive($seq)) {
					continue;
				}
				$blocked = false;
				foreach ($seq as $slot) {
					if (isset($this->special[$track][$day . ':' . (int) ($slot['id'] ?? 0)])) {
						$blocked = true;
						break;
					}
				}
				if ($blocked) {
					continue;
				}
				$found[] = ['day' => $day, 'slots' => $seq, 'prefer' => $day === $preferDay ? 0 : 1];
			}
		}
		usort($found, static function (array $a, array $b): int {
			return ((int) $a['prefer']) <=> ((int) $b['prefer']);
		});
		return $found;
	}

	/** @param list<array<string,mixed>> $slots */
	private function consecutive(array $slots): bool
	{
		for ($i = 1, $n = count($slots); $i < $n; $i++) {
			$prevEnd = $this->mins((string) ($slots[$i - 1]['end_time'] ?? ''));
			$start = $this->mins((string) ($slots[$i]['start_time'] ?? ''));
			if ($start < $prevEnd || $start - $prevEnd > 35) {
				return false;
			}
		}
		return true;
	}

	/** @return list<array{0:int,1:int}> */
	private function coursePairs(): array
	{
		$seen = [];
		$out = [];
		foreach ($this->byId as $entry) {
			$classId = (int) ($entry['class_id'] ?? 0);
			$courseId = (int) ($entry['course_id'] ?? 0);
			$key = $classId . ':' . $courseId;
			if ($classId <= 0 || $courseId <= 0 || isset($seen[$key]) || !$this->classAllowed($classId)) {
				continue;
			}
			$seen[$key] = true;
			$out[] = [$classId, $courseId];
		}
		return $out;
	}

	/** @return list<list<array<string,mixed>>> */
	private function runs(int $classId, int $courseId): array
	{
		$rows = [];
		foreach ($this->byId as $entry) {
			if ((int) ($entry['class_id'] ?? 0) === $classId && (int) ($entry['course_id'] ?? 0) === $courseId) {
				$rows[] = $entry;
			}
		}
		usort($rows, function (array $a, array $b): int {
			return [(int) $a['day_of_week'], $this->mins((string) $a['start_time'])]
				<=> [(int) $b['day_of_week'], $this->mins((string) $b['start_time'])];
		});
		$runs = [];
		$current = [];
		foreach ($rows as $entry) {
			if ($current === []) {
				$current[] = $entry;
				continue;
			}
			$prev = $current[count($current) - 1];
			$sameDay = (int) $prev['day_of_week'] === (int) $entry['day_of_week'];
			$gap = $this->mins((string) $entry['start_time']) - $this->mins((string) $prev['end_time']);
			if ($sameDay && $gap >= 0 && $gap <= 35) {
				$current[] = $entry;
				continue;
			}
			$runs[] = $current;
			$current = [$entry];
		}
		if ($current !== []) {
			$runs[] = $current;
		}
		return $runs;
	}

	/** @return list<array<string,mixed>> */
	private function runContaining(int $classId, int $courseId, array $entry): array
	{
		foreach ($this->runs($classId, $courseId) as $run) {
			foreach ($run as $period) {
				if ((int) ($period['id'] ?? 0) === (int) ($entry['id'] ?? 0)) {
					return $run;
				}
			}
		}
		return [$entry];
	}

	private function placedCount(int $classId, int $courseId): int
	{
		$n = 0;
		foreach ($this->byId as $entry) {
			if ((int) ($entry['class_id'] ?? 0) === $classId && (int) ($entry['course_id'] ?? 0) === $courseId) {
				$n++;
			}
		}
		return $n;
	}

	/** @param array<int,true> $ignore */
	private function dayCount(int $classId, int $courseId, int $day, array $ignore): int
	{
		$n = 0;
		foreach ($this->byId as $id => $entry) {
			if (isset($ignore[(int) $id])) {
				continue;
			}
			if ((int) ($entry['class_id'] ?? 0) === $classId
				&& (int) ($entry['course_id'] ?? 0) === $courseId
				&& (int) ($entry['day_of_week'] ?? -1) === $day) {
				$n++;
			}
		}
		return $n;
	}

	/** @param array<int,true> $ignore */
	private function touchesRun(int $classId, int $courseId, int $day, array $slot, array $ignore): bool
	{
		return $this->touchedRunLength($classId, $courseId, $day, $slot, $ignore) > 0;
	}

	/** @param array<int,true> $ignore */
	private function touchedRunLength(int $classId, int $courseId, int $day, array $slot, array $ignore): int
	{
		$start = $this->mins((string) ($slot['start_time'] ?? ''));
		$end = $this->mins((string) ($slot['end_time'] ?? ''));
		foreach ($this->runs($classId, $courseId) as $run) {
			if ((int) ($run[0]['day_of_week'] ?? -1) !== $day) {
				continue;
			}
			$kept = [];
			foreach ($run as $period) {
				if (!isset($ignore[(int) ($period['id'] ?? 0)])) {
					$kept[] = $period;
				}
			}
			if ($kept === []) {
				continue;
			}
			$first = $this->mins((string) $kept[0]['start_time']);
			$last = $this->mins((string) $kept[count($kept) - 1]['end_time']);
			$before = $first - $end;
			$after = $start - $last;
			if (($before >= 0 && $before <= 35) || ($after >= 0 && $after <= 35)) {
				return count($kept);
			}
		}
		return 0;
	}

	private function runCap(int $periods): int
	{
		return $periods < 4 ? 2 : 3;
	}

	private function countBadShapes(): int
	{
		$bad = 0;
		foreach ($this->coursePairs() as $pair) {
			if ($this->courseFrozen($pair[0], $pair[1])) {
				continue;
			}
			$n = $this->placedCount($pair[0], $pair[1]);
			if ($n < 2 || !$this->courseIsExam($pair[0], $pair[1])) {
				continue;
			}
			$lengths = [];
			foreach ($this->runs($pair[0], $pair[1]) as $run) {
				$lengths[] = count($run);
			}
			if ($this->shapeBad($n, $lengths)) {
				$bad++;
			}
		}
		return $bad;
	}

	/** @param list<int> $lengths */
	private function shapeBad(int $n, array $lengths): bool
	{
		$singles = 0;
		$triples = 0;
		foreach ($lengths as $len) {
			if ($len >= 4 || ($n < 4 && $len >= 3)) {
				return true;
			}
			if ($len === 1) {
				$singles++;
			}
			if ($len === 3) {
				$triples++;
			}
		}
		if ($n >= 4 && $singles > 0) {
			return true;
		}
		if ($n < 4 && $singles > ($n % 2)) {
			return true;
		}
		if ($n > 4 && $n % 2 === 1 && $triples < 1) {
			return true;
		}
		return false;
	}

	private function courseIsExam(int $classId, int $courseId): bool
	{
		foreach ($this->byId as $entry) {
			if ((int) ($entry['class_id'] ?? 0) === $classId && (int) ($entry['course_id'] ?? 0) === $courseId) {
				return $this->isExam($entry);
			}
		}
		return false;
	}

	private function courseFrozen(int $classId, int $courseId): bool
	{
		if (($this->tracks[$classId] ?? '') === 'nursery' || !$this->classAllowed($classId)) {
			return true;
		}
		foreach ($this->byId as $entry) {
			if ((int) ($entry['class_id'] ?? 0) !== $classId || (int) ($entry['course_id'] ?? 0) !== $courseId) {
				continue;
			}
			if ($this->isPartTime((int) ($entry['staff_id'] ?? 0))) {
				return true;
			}
			$meta = $this->metaFor($entry);
			$title = (string) ($meta['course_title'] ?? '');
			if (TimetableGeneratorService::isPhysicalEducationSportTitle($title)) {
				return true;
			}
			if ($this->criteria->isClinicalAttachmentCourse($meta)) {
				return true;
			}
		}
		return false;
	}

	/** @param array<string,mixed> $entry */
	private function movable(array $entry, bool $allowLanguage): bool
	{
		if ($this->isPartTime((int) ($entry['staff_id'] ?? 0))) {
			return false;
		}
		$classId = (int) ($entry['class_id'] ?? 0);
		if (!$this->classAllowed($classId) || ($this->tracks[$classId] ?? '') === 'nursery') {
			return false;
		}
		$meta = $this->metaFor($entry);
		$title = (string) ($meta['course_title'] ?? '');
		if (SecondaryTimetableCriteria::isOmittedFromGridTitle($title)
			|| TimetableGeneratorService::isPhysicalEducationSportTitle($title)) {
			return false;
		}
		if ($this->criteria->isClinicalAttachmentCourse($meta)
			|| $this->criteria->requiresAfterLessons($meta)
			|| $this->criteria->isFixedEveningActivity($meta)) {
			return false;
		}
		if (!$allowLanguage && $this->prefersAfternoon($entry)) {
			return false;
		}
		return true;
	}

	/** @param array<string,mixed> $entry */
	private function isExam(array $entry): bool
	{
		if ($this->prefersAfternoon($entry)) {
			return false;
		}
		$meta = $this->metaFor($entry);
		$title = (string) ($meta['course_title'] ?? '');
		if (TimetableGeneratorService::isPhysicalEducationSportTitle($title)) {
			return false;
		}
		return SecondaryTimetableCriteria::isExaminableMainCategory((string) ($meta['category_title'] ?? $entry['category_title'] ?? ''));
	}

	/** @param array<string,mixed> $entry */
	private function prefersAfternoon(array $entry): bool
	{
		$meta = $this->metaFor($entry);
		return SecondaryTimetableCriteria::prefersAfternoonTitle((string) ($meta['course_title'] ?? ''));
	}

	/** @param array<string,mixed> $entry */
	private function candidateRank(array $entry): int
	{
		$rank = $this->isExam($entry) ? 0 : ($this->prefersAfternoon($entry) ? 2 : 1);
		$run = $this->runContaining((int) ($entry['class_id'] ?? 0), (int) ($entry['course_id'] ?? 0), $entry);
		if (count($run) === 1) {
			$rank -= 1;
		}
		if ((int) ($entry['day_of_week'] ?? -1) < 0) {
			$rank += 3;
		}
		return $rank;
	}

	/** @param array<string,mixed> $entry */
	private function underCredit(array $entry): bool
	{
		$meta = $this->metaFor($entry);
		$credit = (int) round((float) ($meta['credit'] ?? 0));
		if ($credit <= 0) {
			return true;
		}
		return $this->placedCount((int) ($entry['class_id'] ?? 0), (int) ($entry['course_id'] ?? 0)) < $credit;
	}

	/** @param array<string,mixed> $entry @return list<array<string,mixed>> */
	private function clockGroup(array $entry): array
	{
		$entry = $this->current($entry);
		if ((int) ($entry['day_of_week'] ?? -1) < 0) {
			return [$entry];
		}
		$group = [$entry];
		$staffId = (int) ($entry['staff_id'] ?? 0);
		$day = (int) ($entry['day_of_week'] ?? -1);
		$start = substr((string) ($entry['start_time'] ?? ''), 0, 5);
		$courseId = (int) ($entry['course_id'] ?? 0);
		foreach ($this->byId as $other) {
			if ((int) ($other['id'] ?? 0) === (int) ($entry['id'] ?? 0)) {
				continue;
			}
			if ((int) ($other['staff_id'] ?? 0) !== $staffId || (int) ($other['day_of_week'] ?? -1) !== $day) {
				continue;
			}
			if (substr((string) ($other['start_time'] ?? ''), 0, 5) !== $start) {
				continue;
			}
			$same = $courseId > 0 && (int) ($other['course_id'] ?? 0) === $courseId;
			$combined = SecondaryTimetableCriteria::entriesAreCombinedLesson($this->combineRow($entry), $this->combineRow($other));
			if ($same || $combined) {
				$group[] = $other;
			}
		}
		return $group;
	}

	/** @param list<array<string,mixed>> $group */
	private function lowestId(array $group): int
	{
		$best = 0;
		foreach ($group as $entry) {
			$id = (int) ($entry['id'] ?? 0);
			if ($best === 0 || $id < $best) {
				$best = $id;
			}
		}
		return $best;
	}

	/** @param list<array<string,mixed>> $group @return list<int> */
	private function groupIds(array $group): array
	{
		$ids = [];
		foreach ($group as $entry) {
			$ids[] = (int) ($entry['id'] ?? 0);
		}
		return $ids;
	}

	/** @param array<string,mixed> $entry */
	private function current(array $entry): array
	{
		$id = (int) ($entry['id'] ?? 0);
		return $this->byId[$id] ?? $this->parked[$id] ?? $entry;
	}

	/** @param array<string,mixed> $entry @return array<string,mixed> */
	private function metaFor(array $entry): array
	{
		$classId = (int) ($entry['class_id'] ?? 0);
		$courseId = (int) ($entry['course_id'] ?? 0);
		$staffId = (int) ($entry['staff_id'] ?? 0);
		$meta = $this->meta[$classId . ':' . $courseId . ':' . $staffId]
			?? $this->meta[$classId . ':' . $courseId]
			?? [];
		if ($meta === []) {
			$meta = $entry;
		}
		foreach (['course_title', 'category_title', 'class_title', 'level_name', 'level_title', 'dept_code', 'dept_title', 'teacher_name'] as $field) {
			if (trim((string) ($meta[$field] ?? '')) === '' && trim((string) ($entry[$field] ?? '')) !== '') {
				$meta[$field] = $entry[$field];
			}
		}
		$meta['class_id'] = $classId;
		$meta['course_id'] = $courseId;
		$meta['lecturer'] = (int) ($meta['lecturer'] ?? $staffId);
		$meta['staff_id'] = $staffId;
		$meta['track_key'] = (string) ($this->tracks[$classId] ?? ($meta['track_key'] ?? ''));
		$meta['_track_key'] = $meta['track_key'];
		return $meta;
	}

	/** @param array<string,mixed> $entry @return array<string,mixed> */
	private function combineRow(array $entry): array
	{
		$meta = $this->metaFor($entry);
		$meta['staff_id'] = (int) ($entry['staff_id'] ?? $meta['lecturer'] ?? 0);
		return $meta;
	}

	private function classAllowed(int $classId): bool
	{
		if ($classId <= 0) {
			return false;
		}
		if ($this->onlyClasses !== [] && !isset($this->onlyClasses[$classId])) {
			return false;
		}
		return true;
	}

	private function isPartTime(int $staffId): bool
	{
		return $staffId > 0 && isset($this->partTime[$staffId]);
	}

	private function isMorning(string $start): bool
	{
		return $start !== '' && $this->mins($start) < 12 * 60;
	}

	private function partTimeMoved(): bool
	{
		foreach ($this->frozenPartTime as $id => $signature) {
			$row = $this->byId[(int) $id] ?? $this->parked[(int) $id] ?? null;
			if (!is_array($row)) {
				return true;
			}
			$now = (int) ($row['day_of_week'] ?? -1) . ':' . (int) ($row['slot_id'] ?? 0);
			if ($now !== $signature) {
				return true;
			}
		}
		return false;
	}

	private function collisionCount(): int
	{
		$n = 0;
		$rows = array_values($this->byId);
		$count = count($rows);
		for ($i = 0; $i < $count; $i++) {
			$a = $rows[$i];
			$aStart = $this->mins((string) ($a['start_time'] ?? ''));
			$aEnd = $this->mins((string) ($a['end_time'] ?? ''));
			if ($aEnd <= $aStart) {
				continue;
			}
			for ($j = $i + 1; $j < $count; $j++) {
				$b = $rows[$j];
				if ((int) ($a['day_of_week'] ?? -1) !== (int) ($b['day_of_week'] ?? -2)) {
					continue;
				}
				$bStart = $this->mins((string) ($b['start_time'] ?? ''));
				$bEnd = $this->mins((string) ($b['end_time'] ?? ''));
				if ($aStart >= $bEnd || $bStart >= $aEnd) {
					continue;
				}
				$sameClass = (int) ($a['class_id'] ?? 0) > 0 && (int) ($a['class_id'] ?? 0) === (int) ($b['class_id'] ?? 0);
				$sameStaff = (int) ($a['staff_id'] ?? 0) > 0 && (int) ($a['staff_id'] ?? 0) === (int) ($b['staff_id'] ?? 0);
				if ($sameClass) {
					$n++;
					continue;
				}
				if ($sameStaff && !SecondaryTimetableCriteria::entriesAreCombinedLesson($this->combineRow($a), $this->combineRow($b))) {
					$n++;
				}
			}
		}
		return $n;
	}

	private function mins(string $time): int
	{
		$parts = explode(':', substr($time, 0, 8));
		return ((int) ($parts[0] ?? 0)) * 60 + (int) ($parts[1] ?? 0);
	}
}
