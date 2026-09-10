<?php

namespace App\Commands;

use App\Controllers\TimetableManagement;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Services;

/**
 * Previously used by cron to auto-regen stale timetables.
 * Disabled: use Timetable → Generate smart timetable (manual, with progress).
 *
 * Usage: php spark timetable:auto-regenerate [schoolId]
 */
class AutoRegenerateTimetables extends BaseCommand
{
	protected $group = 'Timetable';
	protected $name = 'timetable:auto-regenerate';
	protected $description = 'DISABLED — automatic timetable regeneration is off; generate from the dashboard';
	protected $usage = 'timetable:auto-regenerate [schoolId]';

	public function run(array $params)
	{
		$ctl = new TimetableManagement();
		$ctl->initController(Services::request(), Services::response(), Services::logger());
		$result = $ctl->autoRegenerateStaleSchools(
			isset($params[0]) && $params[0] !== '' ? (int) $params[0] : null
		);

		CLI::write('Timetable auto-regen is DISABLED.', 'yellow');
		CLI::write((string) ($result['message'] ?? 'Generate from the Timetable dashboard.'), 'yellow');
		CLI::write(
			'queued=' . (int) ($result['queued'] ?? 0)
			. ' skipped=' . (int) ($result['skipped'] ?? 0)
			. ' disabled=' . (!empty($result['disabled']) ? '1' : '0'),
			'green'
		);
	}
}
