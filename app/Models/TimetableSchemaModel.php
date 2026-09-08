<?php

namespace App\Models;

use App\Libraries\TimetableTrack;
use CodeIgniter\Model;

class TimetableSchemaModel extends Model
{
	protected $table = 'timetable_settings';
	protected $primaryKey = 'id';
	protected $returnType = 'array';

	private static $ready = false;

	public function ensureSchema(): void
	{
		if (self::$ready) {
			return;
		}
		$sqlFile = ROOTPATH . 'deploy/add_timetable.sql';
		if (is_file($sqlFile)) {
			$db = \Config\Database::connect();
			$sql = file_get_contents($sqlFile);
			$statements = array_filter(array_map('trim', preg_split('/;\s*\n/', $sql)));
			foreach ($statements as $stmt) {
				if ($stmt === '' || stripos($stmt, 'CREATE TABLE') === false) {
					continue;
				}
				try {
					$db->query($stmt);
				} catch (\Throwable $e) {
					// table may exist
				}
			}
		}
		$this->ensureTrackColumns();
		self::$ready = true;
	}

	private function ensureTrackColumns(): void
	{
		$db = \Config\Database::connect();
		foreach (['timetable_slots', 'timetable_special_times'] as $table) {
			if (!$db->tableExists($table)) {
				continue;
			}
			$fields = $db->getFieldNames($table);
			if (!in_array('track_key', $fields, true)) {
				try {
					$db->query("ALTER TABLE `{$table}` ADD COLUMN `track_key` varchar(20) NOT NULL DEFAULT 'all' AFTER `school_id`");
				} catch (\Throwable $e) {
					// column may exist
				}
			}
			if (!in_array('level_id', $fields, true)) {
				try {
					$db->query("ALTER TABLE `{$table}` ADD COLUMN `level_id` int(11) NOT NULL DEFAULT 0 AFTER `school_id`");
				} catch (\Throwable $e) {
					// column may exist
				}
			}
		}
		if ($db->tableExists('timetable_settings')) {
			$fields = $db->getFieldNames('timetable_settings');
			if (!in_array('include_saturday', $fields, true)) {
				try {
					$db->query("ALTER TABLE `timetable_settings` ADD COLUMN `include_saturday` tinyint(1) NOT NULL DEFAULT 0 AFTER `include_sunday`");
				} catch (\Throwable $e) {
					// column may exist
				}
			}
			if (!in_array('shared_timetable', $fields, true)) {
				try {
					$db->query("ALTER TABLE `timetable_settings` ADD COLUMN `shared_timetable` tinyint(1) NOT NULL DEFAULT 1 AFTER `include_saturday`");
				} catch (\Throwable $e) {
					// column may exist
				}
			}
		}
		// Backfill track_key from legacy level_id rows
		try {
			$db->query("UPDATE timetable_slots SET track_key='all' WHERE track_key='' OR track_key IS NULL");
			$db->query("UPDATE timetable_special_times SET track_key='all' WHERE track_key='' OR track_key IS NULL");
		} catch (\Throwable $e) {
			// ignore
		}
	}

	public function isSharedSchedule(int $schoolId): bool
	{
		$this->ensureSchema();
		$row = \Config\Database::connect()->table('timetable_settings')
			->where('school_id', $schoolId)->get(1)->getRowArray();
		return $row === null || !empty($row['shared_timetable']);
	}

	public function trackForClass(int $schoolId, int $classId): string
	{
		if ($this->isSharedSchedule($schoolId) || $classId <= 0) {
			return TimetableTrack::ALL;
		}
		return TimetableTrack::resolveForClassId($classId);
	}

	/** @return list<array{key:string,label:string}> */
	public function schoolTracks(int $schoolId): array
	{
		$labels = TimetableTrack::labels();
		if ($this->isSharedSchedule($schoolId)) {
			return [['key' => TimetableTrack::ALL, 'label' => $labels[TimetableTrack::ALL]]];
		}
		$out = [];
		foreach (TimetableTrack::tracksForSchool($schoolId) as $key) {
			$out[] = ['key' => $key, 'label' => $labels[$key] ?? ucfirst($key)];
		}
		if ($out === []) {
			$out[] = ['key' => TimetableTrack::PRIMARY, 'label' => $labels[TimetableTrack::PRIMARY]];
		}
		return $out;
	}

	public function seedDefaultSlots(int $schoolId, string $trackKey = TimetableTrack::ALL): void
	{
		$this->ensureTrackSlots($schoolId, $trackKey);
	}

	/**
	 * Apply the primary 1-hour slot template to an existing track (updates rows by sort_order).
	 */
	public function applyPrimarySlotTemplate(int $schoolId, string $trackKey = TimetableTrack::ALL): int
	{
		$this->ensureSchema();
		$schoolId = (int) $schoolId;
		$trackKey = TimetableTrack::normalize($trackKey);
		if ($schoolId <= 0) {
			return 0;
		}

		$db = \Config\Database::connect();
		$rows = $db->table('timetable_slots')
			->where('school_id', $schoolId)
			->where('track_key', $trackKey)
			->orderBy('sort_order', 'ASC')
			->get()->getResultArray();

		$template = self::primarySlotTemplate();
		if ($rows === []) {
			$this->insertSlotSet($schoolId, $trackKey, $template);
			return count($template);
		}

		$updated = 0;
		foreach ($template as $i => $slot) {
			if (!isset($rows[$i])) {
				$db->table('timetable_slots')->insert([
					'school_id' => $schoolId,
					'track_key' => $trackKey,
					'level_id' => 0,
					'sort_order' => $i,
					'label' => $slot['label'],
					'start_time' => $slot['start'],
					'end_time' => $slot['end'],
					'is_break' => $slot['break'],
					'break_label' => $slot['break_label'],
				]);
				$updated++;
				continue;
			}
			$row = $rows[$i];
			$db->table('timetable_slots')->where('id', (int) $row['id'])->update([
				'sort_order' => $i,
				'label' => $slot['label'],
				'start_time' => $slot['start'],
				'end_time' => $slot['end'],
				'is_break' => $slot['break'],
				'break_label' => $slot['break_label'],
			]);
			$updated++;
		}

		// Remove extra trailing rows beyond the default template
		if (count($rows) > count($template)) {
			$extraIds = array_map(static fn ($r) => (int) $r['id'], array_slice($rows, count($template)));
			if ($extraIds !== []) {
				$db->table('timetable_slots')->whereIn('id', $extraIds)->delete();
			}
		}

		return $updated;
	}

	public function ensureTrackSlots(int $schoolId, string $trackKey = TimetableTrack::ALL): void
	{
		$this->ensureSchema();
		$schoolId = (int) $schoolId;
		$trackKey = TimetableTrack::normalize($trackKey);
		if ($schoolId <= 0) {
			return;
		}

		$db = \Config\Database::connect();
		$count = (int) $db->table('timetable_slots')
			->where('school_id', $schoolId)
			->where('track_key', $trackKey)
			->countAllResults();
		if ($count > 0) {
			return;
		}

		if ($trackKey === TimetableTrack::ALL) {
			$this->insertSlotSet($schoolId, $trackKey, self::primarySlotTemplate());
		} elseif ($trackKey === TimetableTrack::NURSERY) {
			$this->insertSlotSet($schoolId, $trackKey, self::nurserySlotTemplate());
		} elseif (in_array($trackKey, [TimetableTrack::O_LEVEL, TimetableTrack::A_LEVEL, TimetableTrack::SPECIAL, TimetableTrack::RTB], true)) {
			$this->insertSlotSet($schoolId, $trackKey, self::secondarySlotTemplate());
		} else {
			$sharedCount = (int) $db->table('timetable_slots')
				->where('school_id', $schoolId)->where('track_key', TimetableTrack::ALL)->countAllResults();
			if ($sharedCount > 0) {
				$this->copySlotsFromTrack($schoolId, TimetableTrack::ALL, $trackKey);
			} else {
				$this->insertSlotSet($schoolId, $trackKey, self::defaultTemplateForTrack($trackKey));
			}
		}

		if ((int) $db->table('timetable_settings')->where('school_id', $schoolId)->countAllResults() === 0) {
			$db->table('timetable_settings')->insert([
				'school_id' => $schoolId,
				'days_json' => json_encode(['Mon', 'Tue', 'Wed', 'Thu', 'Fri']),
				'include_sunday' => 0,
				'include_saturday' => 0,
				'shared_timetable' => 1,
				'updated_at' => date('Y-m-d H:i:s'),
			]);
		}
	}

	/** @return list<array{label:string,start:string,end:string,break:int,break_label:?string}> */
	private static function primarySlotTemplate(): array
	{
		return [
			['label' => '1', 'start' => '08:00:00', 'end' => '09:00:00', 'break' => 0, 'break_label' => null],
			['label' => '2', 'start' => '09:00:00', 'end' => '10:00:00', 'break' => 0, 'break_label' => null],
			['label' => '3', 'start' => '10:00:00', 'end' => '11:00:00', 'break' => 0, 'break_label' => null],
			['label' => '4', 'start' => '11:00:00', 'end' => '12:00:00', 'break' => 0, 'break_label' => null],
			['label' => 'BREAK 1', 'start' => '12:00:00', 'end' => '12:20:00', 'break' => 1, 'break_label' => 'BREAK 1'],
			['label' => '5', 'start' => '12:20:00', 'end' => '13:20:00', 'break' => 0, 'break_label' => null],
			['label' => '6', 'start' => '13:20:00', 'end' => '14:20:00', 'break' => 0, 'break_label' => null],
			['label' => '7', 'start' => '14:20:00', 'end' => '15:20:00', 'break' => 0, 'break_label' => null],
			['label' => 'BREAK 2', 'start' => '15:20:00', 'end' => '16:20:00', 'break' => 1, 'break_label' => 'BREAK 2'],
			['label' => '8', 'start' => '16:20:00', 'end' => '17:20:00', 'break' => 0, 'break_label' => null],
		];
	}

	/** @return list<array{label:string,start:string,end:string,break:int,break_label:?string}> */
	private static function nurserySlotTemplate(): array
	{
		return [
			['label' => '1', 'start' => '08:00:00', 'end' => '08:30:00', 'break' => 0, 'break_label' => null],
			['label' => '2', 'start' => '08:30:00', 'end' => '09:00:00', 'break' => 0, 'break_label' => null],
			['label' => '3', 'start' => '09:00:00', 'end' => '09:30:00', 'break' => 0, 'break_label' => null],
			['label' => '4', 'start' => '09:30:00', 'end' => '10:00:00', 'break' => 0, 'break_label' => null],
			['label' => 'SNACK', 'start' => '10:00:00', 'end' => '10:20:00', 'break' => 1, 'break_label' => 'SNACK BREAK'],
			['label' => '5', 'start' => '10:20:00', 'end' => '10:50:00', 'break' => 0, 'break_label' => null],
			['label' => '6', 'start' => '10:50:00', 'end' => '11:20:00', 'break' => 0, 'break_label' => null],
			['label' => '7', 'start' => '11:20:00', 'end' => '11:50:00', 'break' => 0, 'break_label' => null],
			['label' => 'LUNCH', 'start' => '11:50:00', 'end' => '12:30:00', 'break' => 1, 'break_label' => 'LUNCH'],
			['label' => '8', 'start' => '12:30:00', 'end' => '13:00:00', 'break' => 0, 'break_label' => null],
		];
	}

	/** @return list<array{label:string,start:string,end:string,break:int,break_label:?string}> */
	private static function secondarySlotTemplate(): array
	{
		return [
			['label' => '1', 'start' => '07:00:00', 'end' => '07:40:00', 'break' => 0, 'break_label' => null],
			['label' => '2', 'start' => '07:40:00', 'end' => '08:20:00', 'break' => 0, 'break_label' => null],
			['label' => '3', 'start' => '08:20:00', 'end' => '09:00:00', 'break' => 0, 'break_label' => null],
			['label' => '4', 'start' => '09:00:00', 'end' => '09:40:00', 'break' => 0, 'break_label' => null],
			['label' => 'BREAK TIME', 'start' => '09:40:00', 'end' => '10:00:00', 'break' => 1, 'break_label' => 'BREAK TIME'],
			['label' => '5', 'start' => '10:00:00', 'end' => '10:40:00', 'break' => 0, 'break_label' => null],
			['label' => '6', 'start' => '10:40:00', 'end' => '11:20:00', 'break' => 0, 'break_label' => null],
			['label' => '7', 'start' => '11:20:00', 'end' => '12:00:00', 'break' => 0, 'break_label' => null],
			['label' => 'LUNCH TIME', 'start' => '12:00:00', 'end' => '13:00:00', 'break' => 1, 'break_label' => 'LUNCH TIME'],
			['label' => '8', 'start' => '13:00:00', 'end' => '13:40:00', 'break' => 0, 'break_label' => null],
			['label' => '9', 'start' => '13:40:00', 'end' => '14:20:00', 'break' => 0, 'break_label' => null],
			['label' => '10', 'start' => '14:20:00', 'end' => '15:00:00', 'break' => 0, 'break_label' => null],
			['label' => '11', 'start' => '15:00:00', 'end' => '15:40:00', 'break' => 0, 'break_label' => null],
			['label' => '12', 'start' => '15:40:00', 'end' => '16:20:00', 'break' => 0, 'break_label' => null],
			['label' => '13', 'start' => '16:20:00', 'end' => '16:40:00', 'break' => 0, 'break_label' => null],
			['label' => '14', 'start' => '16:40:00', 'end' => '17:30:00', 'break' => 0, 'break_label' => null],
			['label' => '15', 'start' => '17:30:00', 'end' => '20:00:00', 'break' => 0, 'break_label' => null],
			['label' => '16', 'start' => '20:00:00', 'end' => '21:00:00', 'break' => 0, 'break_label' => null],
		];
	}

	/** @return list<array{label:string,start:string,end:string,break:int,break_label:?string}> */
	private static function defaultTemplateForTrack(string $trackKey): array
	{
		$trackKey = TimetableTrack::normalize($trackKey);
		if ($trackKey === TimetableTrack::NURSERY) {
			return self::nurserySlotTemplate();
		}
		if (in_array($trackKey, [TimetableTrack::O_LEVEL, TimetableTrack::A_LEVEL, TimetableTrack::SPECIAL, TimetableTrack::RTB], true)) {
			return self::secondarySlotTemplate();
		}
		return self::primarySlotTemplate();
	}

	/** @param list<array{label:string,start:string,end:string,break:int,break_label:?string}> $template */
	private function insertSlotSet(int $schoolId, string $trackKey, array $template): void
	{
		$db = \Config\Database::connect();
		$order = 0;
		foreach ($template as $row) {
			$db->table('timetable_slots')->insert([
				'school_id' => $schoolId,
				'track_key' => $trackKey,
				'level_id' => 0,
				'sort_order' => $order++,
				'label' => $row['label'],
				'start_time' => $row['start'],
				'end_time' => $row['end'],
				'is_break' => $row['break'],
				'break_label' => $row['break_label'],
			]);
		}
	}

	private function copySlotsFromTrack(int $schoolId, string $fromTrack, string $toTrack): void
	{
		$db = \Config\Database::connect();
		$rows = $db->table('timetable_slots')
			->where('school_id', $schoolId)
			->where('track_key', $fromTrack)
			->orderBy('sort_order', 'ASC')
			->get()->getResultArray();
		foreach ($rows as $row) {
			unset($row['id']);
			$row['track_key'] = $toTrack;
			$row['level_id'] = 0;
			$db->table('timetable_slots')->insert($row);
		}
	}

	/** @return list<array<string,mixed>> */
	public function teachingSlots(int $schoolId, string $trackKey = TimetableTrack::ALL): array
	{
		$this->ensureTrackSlots($schoolId, $trackKey);
		$this->sanitizeTrackSlots($schoolId, $trackKey);
		return \Config\Database::connect()->table('timetable_slots')
			->where('school_id', $schoolId)
			->where('track_key', TimetableTrack::normalize($trackKey))
			->where('is_break', 0)
			->orderBy('sort_order', 'ASC')
			->get()->getResultArray();
	}

	/** @return list<array<string,mixed>> */
	public function allSlots(int $schoolId, string $trackKey = TimetableTrack::ALL): array
	{
		$this->ensureTrackSlots($schoolId, $trackKey);
		$this->sanitizeTrackSlots($schoolId, $trackKey);
		return \Config\Database::connect()->table('timetable_slots')
			->where('school_id', $schoolId)
			->where('track_key', TimetableTrack::normalize($trackKey))
			->orderBy('sort_order', 'ASC')
			->get()->getResultArray();
	}

	public function resetTrackSlots(int $schoolId, string $trackKey = TimetableTrack::ALL): void
	{
		$this->ensureSchema();
		$trackKey = TimetableTrack::normalize($trackKey);
		$db = \Config\Database::connect();
		$db->table('timetable_slots')->where('school_id', $schoolId)->where('track_key', $trackKey)->delete();
		$template = self::defaultTemplateForTrack($trackKey);
		$this->insertSlotSet($schoolId, $trackKey, $template);
	}

	public function sanitizeTrackSlots(int $schoolId, string $trackKey = TimetableTrack::ALL): void
	{
		$this->ensureSchema();
		$trackKey = TimetableTrack::normalize($trackKey);
		$rows = \Config\Database::connect()->table('timetable_slots')
			->where('school_id', $schoolId)
			->where('track_key', $trackKey)
			->orderBy('sort_order', 'ASC')
			->get()->getResultArray();
		if ($this->isCorruptSlotSet($rows)) {
			$this->resetTrackSlots($schoolId, $trackKey);
		}
	}

	/** @param list<array<string,mixed>> $rows */
	private function isCorruptSlotSet(array $rows): bool
	{
		if ($rows === []) {
			return false;
		}
		$teaching = array_values(array_filter($rows, static fn ($r) => empty($r['is_break'])));
		// Only reset on obvious corruption (e.g. import bug duplicated one label for every period).
		if (count($teaching) >= 4) {
			$labels = array_column($teaching, 'label');
			if (count(array_unique($labels)) === 1) {
				return true;
			}
		}
		return false;
	}

	/** @return list<array<string,mixed>> */
	public function specialTimes(int $schoolId, string $trackKey = TimetableTrack::ALL): array
	{
		$this->ensureSchema();
		$this->ensureSpecialTimesTable();
		$this->ensureTrackSpecialTimes($schoolId, $trackKey);
		$db = \Config\Database::connect();
		return $db->table('timetable_special_times st')
			->select('st.*, ts.label AS slot_label, ts.start_time, ts.end_time')
			->join('timetable_slots ts', 'ts.id = st.slot_id', 'left')
			->where('st.school_id', $schoolId)
			->where('st.track_key', TimetableTrack::normalize($trackKey))
			->orderBy('st.day_of_week')->orderBy('ts.sort_order')
			->get()->getResultArray();
	}

	/** @return array<string,array<string,mixed>> */
	public function specialTimesMap(int $schoolId, string $trackKey = TimetableTrack::ALL): array
	{
		$map = [];
		foreach ($this->specialTimes($schoolId, $trackKey) as $row) {
			$key = (int) $row['day_of_week'] . ':' . (int) $row['slot_id'];
			$map[$key] = $row;
		}
		return $map;
	}

	private function ensureSpecialTimesTable(): void
	{
		$db = \Config\Database::connect();
		if ($db->tableExists('timetable_special_times')) {
			return;
		}
		$db->query("CREATE TABLE IF NOT EXISTS `timetable_special_times` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`school_id` int(11) NOT NULL,
			`track_key` varchar(20) NOT NULL DEFAULT 'all',
			`level_id` int(11) NOT NULL DEFAULT 0,
			`day_of_week` tinyint(4) NOT NULL,
			`slot_id` int(11) NOT NULL,
			`label` varchar(120) NOT NULL,
			`color` varchar(20) NOT NULL DEFAULT 'yellow',
			`sort_order` int(11) NOT NULL DEFAULT 0,
			`created_at` datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (`id`),
			UNIQUE KEY `school_track_day_slot` (`school_id`,`track_key`,`day_of_week`,`slot_id`),
			KEY `school_id` (`school_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
	}

	private function ensureTrackSpecialTimes(int $schoolId, string $trackKey): void
	{
		$trackKey = TimetableTrack::normalize($trackKey);
		if (!in_array($trackKey, [TimetableTrack::O_LEVEL, TimetableTrack::A_LEVEL, TimetableTrack::SPECIAL, TimetableTrack::RTB], true)) {
			return;
		}

		$db = \Config\Database::connect();
		$exists = (int) $db->table('timetable_special_times')
			->where('school_id', $schoolId)
			->where('track_key', $trackKey)
			->countAllResults();
		if ($exists > 0) {
			return;
		}

		$slots = $db->table('timetable_slots')
			->select('id, label')
			->where('school_id', $schoolId)
			->where('track_key', $trackKey)
			->where('is_break', 0)
			->orderBy('sort_order', 'ASC')
			->get()->getResultArray();
		if ($slots === []) {
			return;
		}

		$slotIdByLabel = [];
		foreach ($slots as $slot) {
			$slotIdByLabel[(string) ($slot['label'] ?? '')] = (int) ($slot['id'] ?? 0);
		}

		$order = 0;
		foreach (self::secondarySpecialTemplate() as $row) {
			$slotId = $slotIdByLabel[$row['slot_label']] ?? 0;
			if ($slotId <= 0) {
				continue;
			}
			$db->table('timetable_special_times')->insert([
				'school_id' => $schoolId,
				'track_key' => $trackKey,
				'level_id' => 0,
				'day_of_week' => $row['day'],
				'slot_id' => $slotId,
				'label' => $row['label'],
				'color' => $row['color'],
				'sort_order' => $order++,
			]);
		}
	}

	/** @return list<array{day:int,slot_label:string,label:string,color:string}> */
	private static function secondarySpecialTemplate(): array
	{
		return [
			['day' => 0, 'slot_label' => '13', 'label' => 'Assembly', 'color' => 'orange'],
			['day' => 0, 'slot_label' => '15', 'label' => 'PREPS', 'color' => 'green'],
			['day' => 0, 'slot_label' => '16', 'label' => 'SUPPER', 'color' => 'gray'],

			['day' => 1, 'slot_label' => '13', 'label' => 'PERSONAL ADMIN', 'color' => 'gray'],
			['day' => 1, 'slot_label' => '14', 'label' => 'CHAPEL', 'color' => 'yellow'],
			['day' => 1, 'slot_label' => '15', 'label' => 'PREPS', 'color' => 'green'],
			['day' => 1, 'slot_label' => '16', 'label' => 'SUPPER', 'color' => 'gray'],

			['day' => 2, 'slot_label' => '15', 'label' => 'DEBATE', 'color' => 'yellow'],
			['day' => 2, 'slot_label' => '16', 'label' => 'SUPPER', 'color' => 'gray'],

			['day' => 3, 'slot_label' => '15', 'label' => 'PREPS', 'color' => 'green'],
			['day' => 3, 'slot_label' => '16', 'label' => 'SUPPER', 'color' => 'gray'],

			['day' => 4, 'slot_label' => '10', 'label' => 'CPD', 'color' => 'gray'],
			['day' => 4, 'slot_label' => '11', 'label' => 'TESTS/HW', 'color' => 'gray'],
			['day' => 4, 'slot_label' => '12', 'label' => 'TESTS/HW', 'color' => 'gray'],
			['day' => 4, 'slot_label' => '15', 'label' => 'SABBATH', 'color' => 'blue'],
			['day' => 4, 'slot_label' => '16', 'label' => 'SUPPER', 'color' => 'gray'],

			['day' => 6, 'slot_label' => '9', 'label' => 'CO-CURR.', 'color' => 'purple'],
			['day' => 6, 'slot_label' => '10', 'label' => 'CO-CURR.', 'color' => 'purple'],
			['day' => 6, 'slot_label' => '11', 'label' => 'ACTIVITIES', 'color' => 'purple'],
			['day' => 6, 'slot_label' => '12', 'label' => 'ACTIVITIES', 'color' => 'purple'],
			['day' => 6, 'slot_label' => '15', 'label' => 'PREPS', 'color' => 'green'],
			['day' => 6, 'slot_label' => '16', 'label' => 'SUPPER', 'color' => 'gray'],
		];
	}

	/** @return list<string> */
	public function dayLabels(bool $includeSunday = false, bool $includeSaturday = false): array
	{
		return self::dayLabelsFromSettings([
			'include_saturday' => $includeSaturday ? 1 : 0,
			'include_sunday' => $includeSunday ? 1 : 0,
		]);
	}

	/** @return array<string,int> */
	public function dayMap(bool $includeSunday = false, bool $includeSaturday = false): array
	{
		return self::dayMapFromSettings([
			'include_saturday' => $includeSaturday ? 1 : 0,
			'include_sunday' => $includeSunday ? 1 : 0,
		]);
	}

	/** @param array<string,mixed>|null $settings */
	public static function dayLabelsFromSettings(?array $settings): array
	{
		$labels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];
		if (!empty($settings['include_saturday'])) {
			$labels[] = 'Sat';
		}
		if (!empty($settings['include_sunday'])) {
			$labels[] = 'Sun';
		}
		return $labels;
	}

	/** @param array<string,mixed>|null $settings */
	public static function dayLabelsForTrack(?array $settings, string $trackKey): array
	{
		$days = self::weekDaysForTrack($settings, $trackKey);
		$map = [0 => 'Mon', 1 => 'Tue', 2 => 'Wed', 3 => 'Thu', 4 => 'Fri', 5 => 'Sat', 6 => 'Sun'];
		$labels = [];
		foreach ($days as $day) {
			if (isset($map[$day])) {
				$labels[] = $map[$day];
			}
		}
		return $labels;
	}

	/** @param array<string,mixed>|null $settings */
	public static function dayMapFromSettings(?array $settings): array
	{
		$map = ['Mon' => 0, 'Tue' => 1, 'Wed' => 2, 'Thu' => 3, 'Fri' => 4];
		if (!empty($settings['include_saturday'])) {
			$map['Sat'] = 5;
		}
		if (!empty($settings['include_sunday'])) {
			$map['Sun'] = 6;
		}
		return $map;
	}

	/** @param array<string,mixed>|null $settings */
	public static function dayMapForTrack(?array $settings, string $trackKey): array
	{
		$labels = self::dayLabelsForTrack($settings, $trackKey);
		$fullMap = ['Mon' => 0, 'Tue' => 1, 'Wed' => 2, 'Thu' => 3, 'Fri' => 4, 'Sat' => 5, 'Sun' => 6];
		$map = [];
		foreach ($labels as $label) {
			if (isset($fullMap[$label])) {
				$map[$label] = $fullMap[$label];
			}
		}
		return $map;
	}

	/** @param array<string,mixed>|null $settings */
	public static function weekDaysFromSettings(?array $settings): array
	{
		$days = [0, 1, 2, 3, 4];
		if (!empty($settings['include_saturday'])) {
			$days[] = 5;
		}
		if (!empty($settings['include_sunday'])) {
			$days[] = 6;
		}
		return $days;
	}

	/** @param array<string,mixed>|null $settings @return list<int> */
	public static function weekDaysForTrack(?array $settings, string $trackKey): array
	{
		$trackKey = TimetableTrack::normalize($trackKey);
		$days = self::weekDaysFromSettings($settings);
		if (in_array($trackKey, [TimetableTrack::PRIMARY, TimetableTrack::NURSERY], true)) {
			$days = array_values(array_filter($days, static fn (int $day): bool => $day !== 6));
		}
		return $days;
	}

	/** @return list<array<string,mixed>> */
	public function slotsForEntries(array $entries): array
	{
		if ($entries === []) {
			return [];
		}
		$db = \Config\Database::connect();
		$slotIds = array_values(array_filter(array_unique(array_map(static fn ($e) => (int) ($e['slot_id'] ?? 0), $entries))));
		if ($slotIds === []) {
			return [];
		}
		return $db->table('timetable_slots')
			->whereIn('id', $slotIds)
			->orderBy('start_time', 'ASC')
			->orderBy('sort_order', 'ASC')
			->get()->getResultArray();
	}

	public function repairOrphanEntrySlots(int $schoolId): int
	{
		$this->ensureSchema();
		$db = \Config\Database::connect();
		$scheduleIds = array_map(
			static fn ($r) => (int) $r['id'],
			$db->table('timetable_schedules')->select('id')->where('school_id', $schoolId)->get()->getResultArray()
		);
		if ($scheduleIds === []) {
			return 0;
		}
		$allValidIds = array_map(
			static fn ($r) => (int) $r['id'],
			$db->table('timetable_slots')->select('id')->where('school_id', $schoolId)->get()->getResultArray()
		);
		if ($allValidIds === []) {
			return 0;
		}
		$orphans = $db->table('timetable_entries')
			->select('id, slot_id')
			->whereIn('schedule_id', $scheduleIds)
			->whereNotIn('slot_id', $allValidIds)
			->get()->getResultArray();
		if ($orphans === []) {
			return 0;
		}
		$updated = 0;
		foreach ([TimetableTrack::ALL, TimetableTrack::PRIMARY, TimetableTrack::NURSERY, TimetableTrack::O_LEVEL, TimetableTrack::A_LEVEL, TimetableTrack::SPECIAL, TimetableTrack::RTB] as $track) {
			$teaching = $this->teachingSlots($schoolId, $track);
			foreach ($orphans as $row) {
				$idx = self::legacyTeachingIndexFromSlotId((int) $row['slot_id']);
				if ($idx === null || !isset($teaching[$idx])) {
					continue;
				}
				$db->table('timetable_entries')->where('id', (int) $row['id'])->update([
					'slot_id' => (int) $teaching[$idx]['id'],
				]);
				$updated++;
			}
		}
		return $updated;
	}

	private static function legacyTeachingIndexFromSlotId(int $slotId): ?int
	{
		if ($slotId >= 1 && $slotId <= 4) {
			return $slotId - 1;
		}
		if ($slotId >= 6 && $slotId <= 8) {
			return $slotId - 2;
		}
		if ($slotId >= 10 && $slotId <= 12) {
			return $slotId - 3;
		}
		return null;
	}

	// --- Legacy aliases ---
	public static function normalizeLevelId($levelId): int
	{
		return 0;
	}

	public function schoolLevels(int $schoolId): array
	{
		return $this->schoolTracks($schoolId);
	}

	public function ensureLevelSlots(int $schoolId, $levelOrTrack = 0): void
	{
		$track = is_string($levelOrTrack) ? $levelOrTrack : TimetableTrack::ALL;
		$this->ensureTrackSlots($schoolId, $track);
	}

	public function resolveLevelIdForClass(int $classId): int
	{
		return 0;
	}
}
