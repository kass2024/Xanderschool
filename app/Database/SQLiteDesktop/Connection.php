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
		return parent::query($sql, $binds, $setEscapeFlags, $queryClass);
	}
}
