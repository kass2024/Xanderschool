#!/usr/bin/env php
<?php
/**
 * Background worker entry for curriculum analyse jobs.
 * Called via nohup / start after queue enqueue, or cron:
 *   php deploy/scripts/run_ai_analyse_jobs.php
 */
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$spark = $root . DIRECTORY_SEPARATOR . 'spark';
if (!is_file($spark)) {
	fwrite(STDERR, "spark not found at {$spark}\n");
	exit(1);
}

$php = PHP_BINARY ?: 'php';
$cmd = escapeshellarg($php) . ' ' . escapeshellarg($spark) . ' process:ai-analyse-jobs 30';
passthru($cmd, $code);
exit((int) $code);
