<?php

namespace App\Database\SQLiteDesktop;

use App\Libraries\SqliteCompat;

class Connection extends \CodeIgniter\Database\SQLite3\Connection
{
	public $DBDriver = 'SQLite3';

	public function initialize()
	{
		parent::initialize();
		if ($this->connID instanceof \SQLite3) {
			SqliteCompat::bind($this->connID);
		}
	}

	public function query(string $sql, $binds = null, bool $setEscapeFlags = true, string $queryClass = 'CodeIgniter\\Database\\Query')
	{
		$sql = SqliteCompat::rewrite($sql);
		$parts = SqliteCompat::expandStatements($sql);
		$result = null;
		foreach ($parts as $stmt) {
			$result = $this->runWithBusyRetry(function () use ($stmt, $binds, $setEscapeFlags, $queryClass) {
				return parent::query($stmt, $binds, $setEscapeFlags, $queryClass);
			}, $stmt);
		}
		return $result;
	}

	public function simpleQuery(string $sql)
	{
		$sql = SqliteCompat::rewrite($sql);
		$parts = SqliteCompat::expandStatements($sql);
		$result = false;
		foreach ($parts as $stmt) {
			$result = $this->runWithBusyRetry(function () use ($stmt) {
				return parent::simpleQuery($stmt);
			}, $stmt);
		}
		return $result;
	}

	private function runWithBusyRetry(callable $fn, string $stmt)
	{
		$last = null;
		for ($i = 0; $i < 10; $i++) {
			try {
				$result = $fn();
				$err = '';
				if ($this->connID instanceof \SQLite3) {
					$err = (string) $this->connID->lastErrorMsg();
				}
				if ($result === false && $this->isBusy($err)) {
					usleep(150000 * ($i + 1));
					continue;
				}
				if ($result === false && $err !== '' && strcasecmp($err, 'not an error') !== 0 && ! $this->isBusy($err)) {
					throw new \RuntimeException('SQLite: ' . $err);
				}
				return $result;
			} catch (\Throwable $e) {
				$last = $e;
				if ($this->isBusy($e->getMessage())) {
					usleep(150000 * ($i + 1));
					continue;
				}
				throw $e;
			}
		}
		if ($last instanceof \Throwable) {
			throw $last;
		}
		throw new \RuntimeException('SQLite is busy. Try again. ' . substr($stmt, 0, 180));
	}

	private function isBusy(string $message): bool
	{
		$m = strtolower($message);
		return strpos($m, 'busy') !== false || strpos($m, 'locked') !== false;
	}
}
