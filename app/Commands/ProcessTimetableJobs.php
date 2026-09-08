<?php

namespace App\Commands;

use App\Controllers\TimetableManagement;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Services;

class ProcessTimetableJobs extends BaseCommand
{
	protected $group = 'Jobs';
	protected $name = 'process:timetable-jobs';
	protected $description = 'Process a background timetable generation job';
	protected $usage = 'process:timetable-jobs <jobId>';

	public function run(array $params)
	{
		$jobId = trim((string) ($params[0] ?? ''));
		if ($jobId === '') {
			CLI::error('Missing job id.');
			return;
		}

		@ini_set('max_execution_time', '0');
		@set_time_limit(0);

		$ctl = new TimetableManagement();
		$ctl->initController(Services::request(), Services::response(), Services::logger());
		$result = $ctl->processTimetableJobById($jobId);

		if (!empty($result['busy'])) {
			CLI::write('Timetable job is already running: ' . $jobId, 'yellow');
			return;
		}
		if (!empty($result['ok'])) {
			CLI::write('Timetable job complete: ' . $jobId, 'green');
			return;
		}
		CLI::error('Timetable job failed: ' . ($result['error'] ?? 'unknown'));
	}
}
