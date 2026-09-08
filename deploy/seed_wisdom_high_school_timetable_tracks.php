<?php
declare(strict_types=1);

/**
 * Apply the TIME_TABLE_24H.docx high-school slot allocation to
 * Wisdom Musanze secondary-style tracks:
 * - O Level
 * - A Level
 * - RTB / TVET
 * - Special
 *
 * The source document includes Sunday, so this also enables the Sunday column.
 *
 * Usage:
 *   php deploy/seed_wisdom_high_school_timetable_tracks.php [--dry-run]
 */

define('FCPATH', __DIR__ . '/../public/');
chdir(FCPATH);
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/Config/Paths.php';

$paths = new Config\Paths();
require rtrim($paths->systemDirectory, '/ ') . '/bootstrap.php';

use App\Libraries\TimetableTrack;

const SCHOOL_ID = 27;

$dryRun = in_array('--dry-run', $argv ?? [], true);
$db = \Config\Database::connect();

$school = $db->table('schools')->where('id', SCHOOL_ID)->get(1)->getRowArray();
if (!$school) {
	fwrite(STDERR, "School " . SCHOOL_ID . " not found.\n");
	exit(1);
}

$tracks = [
	TimetableTrack::O_LEVEL,
	TimetableTrack::A_LEVEL,
	TimetableTrack::SPECIAL,
	TimetableTrack::RTB,
];

$template = [
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

echo 'School: ' . ($school['name'] ?? SCHOOL_ID) . PHP_EOL;
echo 'Mode: ' . ($dryRun ? 'DRY-RUN' : 'APPLY') . PHP_EOL . PHP_EOL;

$settingsPayload = [
	'days_json' => json_encode(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sun']),
	'include_saturday' => 0,
	'include_sunday' => 1,
	'shared_timetable' => 0,
	'updated_at' => date('Y-m-d H:i:s'),
];
$settings = $db->table('timetable_settings')->where('school_id', SCHOOL_ID)->get(1)->getRowArray();
if ($dryRun) {
	echo "Would enable Sunday and keep Different per category mode." . PHP_EOL . PHP_EOL;
} elseif ($settings) {
	$db->table('timetable_settings')->where('school_id', SCHOOL_ID)->update($settingsPayload);
	echo "Updated timetable settings." . PHP_EOL . PHP_EOL;
} else {
	$settingsPayload['school_id'] = SCHOOL_ID;
	$db->table('timetable_settings')->insert($settingsPayload);
	echo "Created timetable settings." . PHP_EOL . PHP_EOL;
}

foreach ($tracks as $trackKey) {
	echo "=== {$trackKey} ===" . PHP_EOL;
	$rows = $db->table('timetable_slots')
		->where('school_id', SCHOOL_ID)
		->where('track_key', $trackKey)
		->orderBy('sort_order', 'ASC')
		->get()->getResultArray();

	if ($dryRun) {
		echo 'Existing slots: ' . count($rows) . '; target slots: ' . count($template) . PHP_EOL;
		foreach ($template as $i => $slot) {
			echo sprintf(
				"  [%d] %s %s-%s%s\n",
				$i,
				$slot['label'],
				$slot['start'],
				$slot['end'],
				$slot['break'] ? ' [break]' : ''
			);
		}
		echo PHP_EOL;
		continue;
	}

	$keepIds = [];
	foreach ($template as $i => $slot) {
		$payload = [
			'school_id' => SCHOOL_ID,
			'track_key' => $trackKey,
			'level_id' => 0,
			'sort_order' => $i,
			'label' => $slot['label'],
			'start_time' => $slot['start'],
			'end_time' => $slot['end'],
			'is_break' => $slot['break'],
			'break_label' => $slot['break_label'],
		];
		if (isset($rows[$i])) {
			$id = (int) $rows[$i]['id'];
			$db->table('timetable_slots')->where('id', $id)->update($payload);
			$keepIds[] = $id;
		} else {
			$db->table('timetable_slots')->insert($payload);
			$keepIds[] = (int) $db->insertID();
		}
	}

	if ($keepIds !== []) {
		$extraRows = $db->table('timetable_slots')
			->select('id')
			->where('school_id', SCHOOL_ID)
			->where('track_key', $trackKey)
			->whereNotIn('id', $keepIds)
			->get()->getResultArray();
		$extraIds = array_values(array_filter(array_map(static fn($r): int => (int) ($r['id'] ?? 0), $extraRows)));
		if ($extraIds !== []) {
			$db->table('timetable_special_times')->whereIn('slot_id', $extraIds)->delete();
			$db->table('timetable_slots')->whereIn('id', $extraIds)->delete();
		}
	}

	echo 'Applied ' . count($template) . " slots." . PHP_EOL . PHP_EOL;
}

echo "Done." . PHP_EOL;
