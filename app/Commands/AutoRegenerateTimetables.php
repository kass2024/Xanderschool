<?php

namespace App\Commands;

use App\Controllers\TimetableManagement;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Services;

/**
 * Continuously keep timetables in sync with course assignments.
 * Intended for cron: every 5 minutes.
 *
 * Usage: php spark timetable:auto-regenerate [schoolId]
 */
class AutoRegenerateTimetables extends BaseCommand
{
	protected $group = 'Timetable';
	protected $name = 'timetable:auto-regenerate';
	protected $description = 'Queue timetable regeneration for schools with changed course assignments';
	protected $usage = 'timetable:auto-regenerate [schoolId]';

	public function run(array $params)
	{
		@ini_set('max_execution_time', '0');
		@set_time_limit(0);

		$onlySchool = isset($params[0]) && $params[0] !== '' ? (int) $params[0] : null;
		$ctl = new TimetableManagement();
		$ctl->initController(Services::request(), Services::response(), Services::logger());
		$result = $ctl->autoRegenerateStaleSchools($onlySchool);

		CLI::write(
			'Timetable auto-regen: queued=' . (int) ($result['queued'] ?? 0)
			. ' skipped=' . (int) ($result['skipped'] ?? 0),
			'green'
		);
		foreach (($result['schools'] ?? []) as $row) {
			$job = $row['result']['job_id'] ?? ($row['result']['error'] ?? 'n/a');
			CLI::write(
				'  school ' . (int) ($row['school_id'] ?? 0)
				. ' y' . (int) ($row['year'] ?? 0)
				. ' t' . (int) ($row['term'] ?? 0)
				. ' → ' . $job
			);
		}

		$processed = $ctl->processQueuedTimetableJobs(5);
		CLI::write(
			'Processed jobs: ok=' . (int) ($processed['processed'] ?? 0)
			. ' failed=' . (int) ($processed['failed'] ?? 0)
			. ' busy=' . (int) ($processed['busy'] ?? 0),
			'green'
		);
	}
}
