<?php

namespace App\Commands;

use App\Controllers\Home;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Services;

/**
 * Process pending academic AI analyse jobs one-by-one (background worker).
 *
 * Usage: php spark process:ai-analyse-jobs
 */
class ProcessAiAnalyseJobs extends BaseCommand
{
	protected $group = 'Jobs';
	protected $name = 'process:ai-analyse-jobs';
	protected $description = 'Process queued curriculum analyse jobs sequentially';
	protected $usage = 'process:ai-analyse-jobs [max]';

	public function run(array $params)
	{
		$max = isset($params[0]) ? (int) $params[0] : 25;
		if ($max < 1) {
			$max = 25;
		}
		if ($max > 100) {
			$max = 100;
		}

		@ini_set('max_execution_time', '0');
		@set_time_limit(0);

		$home = new Home();
		$home->initController(Services::request(), Services::response(), Services::logger());

		$processed = 0;
		for ($i = 0; $i < $max; $i++) {
			$result = $home->processNextAiAnalyseJob();
			if (!empty($result['busy'])) {
				CLI::write('Worker lock busy — another process is running.', 'yellow');
				return;
			}
			if (!empty($result['empty'])) {
				if ($processed === 0) {
					CLI::write('No pending analyse jobs.', 'green');
				} else {
					CLI::write('Queue empty after ' . $processed . ' job(s).', 'green');
				}
				return;
			}
			$processed++;
			$classId = (int) ($result['class_id'] ?? 0);
			if (!empty($result['ok'])) {
				CLI::write('Done class #' . $classId . ' job #' . ($result['job_id'] ?? '?'), 'green');
			} else {
				CLI::error('Failed class #' . $classId . ': ' . ($result['error'] ?? 'unknown'));
			}
		}
		CLI::write('Processed ' . $processed . ' job(s) (max ' . $max . ').', 'green');
	}
}
