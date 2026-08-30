<?php

namespace App\Libraries;

/**
 * MySQL function shims + SQL rewrites so the existing school app can run on SQLite.
 */
class SqliteCompat
{
	/** @var bool */
	private static $installed = false;

	public static function isDesktop(): bool
	{
		$v = getenv('XANDER_DESKTOP');
		return $v === '1' || $v === 'true';
	}

	public static function install(): void
	{
		if (self::$installed || ! self::isDesktop()) {
			return;
		}
		try {
			$db = \Config\Database::connect();
		} catch (\Throwable $e) {
			return;
		}
		$conn = $db->connID ?? null;
		if (! $conn instanceof \SQLite3) {
			return;
		}
		self::bind($conn);
		self::$installed = true;
	}

	public static function bind(\SQLite3 $sqlite): void
	{
		@$sqlite->exec('PRAGMA journal_mode = WAL');
		@$sqlite->exec('PRAGMA synchronous = NORMAL');
		@$sqlite->exec('PRAGMA busy_timeout = 8000');
		@$sqlite->exec('PRAGMA foreign_keys = OFF');
		@$sqlite->exec('PRAGMA temp_store = MEMORY');
		@$sqlite->exec('PRAGMA cache_size = -80000');
		@$sqlite->exec('PRAGMA mmap_size = 268435456');

		$sqlite->createFunction('NOW', static function () {
			return date('Y-m-d H:i:s');
		}, 0);
		$sqlite->createFunction('CURDATE', static function () {
			return date('Y-m-d');
		}, 0);
		$sqlite->createFunction('CURTIME', static function () {
			return date('H:i:s');
		}, 0);
		$sqlite->createFunction('UNIX_TIMESTAMP', static function ($value = null) {
			if ($value === null || $value === '') {
				return time();
			}
			$ts = strtotime((string) $value);
			return $ts === false ? 0 : $ts;
		});
		$sqlite->createFunction('FROM_UNIXTIME', static function ($ts, $fmt = null) {
			$ts = (int) $ts;
			if ($fmt === null || $fmt === '') {
				return date('Y-m-d H:i:s', $ts);
			}
			return date(self::mysqlDateToPhp((string) $fmt), $ts);
		});
		$sqlite->createFunction('DATE_FORMAT', static function ($date, $fmt) {
			$ts = strtotime((string) $date);
			if ($ts === false) {
				return $date;
			}
			return date(self::mysqlDateToPhp((string) $fmt), $ts);
		}, 2);
		$sqlite->createFunction('CONCAT', static function (...$args) {
			return implode('', array_map(static function ($v) {
				return $v === null ? '' : (string) $v;
			}, $args));
		});
		$sqlite->createFunction('CONCAT_WS', static function ($sep, ...$args) {
			$parts = [];
			foreach ($args as $v) {
				if ($v !== null && $v !== '') {
					$parts[] = (string) $v;
				}
			}
			return implode((string) $sep, $parts);
		});
		$sqlite->createFunction('IF', static function ($cond, $a, $b) {
			return $cond ? $a : $b;
		}, 3);
		$sqlite->createFunction('IFNULL', static function ($a, $b) {
			return ($a === null || $a === '') ? $b : $a;
		}, 2);
		$sqlite->createFunction('NULLIF', static function ($a, $b) {
			return $a == $b ? null : $a;
		}, 2);
		$sqlite->createFunction('ISNULL', static function ($a) {
			return $a === null ? 1 : 0;
		}, 1);
		$sqlite->createFunction('YEAR', static function ($d) {
			$ts = strtotime((string) $d);
			return $ts ? (int) date('Y', $ts) : 0;
		}, 1);
		$sqlite->createFunction('MONTH', static function ($d) {
			$ts = strtotime((string) $d);
			return $ts ? (int) date('n', $ts) : 0;
		}, 1);
		$sqlite->createFunction('DAY', static function ($d) {
			$ts = strtotime((string) $d);
			return $ts ? (int) date('j', $ts) : 0;
		}, 1);
		$sqlite->createFunction('HOUR', static function ($d) {
			$ts = strtotime((string) $d);
			return $ts ? (int) date('G', $ts) : 0;
		}, 1);
		$sqlite->createFunction('DAYOFWEEK', static function ($d) {
			$ts = strtotime((string) $d);
			return $ts ? (int) date('w', $ts) + 1 : 0;
		}, 1);
		$sqlite->createFunction('DATEDIFF', static function ($a, $b) {
			$ta = strtotime((string) $a);
			$tb = strtotime((string) $b);
			if ($ta === false || $tb === false) {
				return 0;
			}
			return (int) round(($ta - $tb) / 86400);
		}, 2);
		$sqlite->createFunction('TIMESTAMPDIFF', static function ($unit, $a, $b) {
			$ta = strtotime((string) $a);
			$tb = strtotime((string) $b);
			if ($ta === false || $tb === false) {
				return 0;
			}
			$diff = $tb - $ta;
			$u = strtoupper((string) $unit);
			if ($u === 'SECOND') {
				return $diff;
			}
			if ($u === 'MINUTE') {
				return (int) floor($diff / 60);
			}
			if ($u === 'HOUR') {
				return (int) floor($diff / 3600);
			}
			if ($u === 'DAY') {
				return (int) floor($diff / 86400);
			}
			if ($u === 'MONTH') {
				return (int) floor($diff / 2592000);
			}
			if ($u === 'YEAR') {
				return (int) floor($diff / 31536000);
			}
			return $diff;
		}, 3);
		$sqlite->createFunction('FIND_IN_SET', static function ($needle, $haystack) {
			if ($haystack === null || $haystack === '') {
				return 0;
			}
			$parts = explode(',', (string) $haystack);
			$i = array_search((string) $needle, $parts, true);
			return $i === false ? 0 : $i + 1;
		}, 2);
		$sqlite->createFunction('FIELD', static function ($value, ...$list) {
			$i = array_search($value, $list, false);
			return $i === false ? 0 : $i + 1;
		});
		$sqlite->createFunction('SUBSTRING_INDEX', static function ($str, $delim, $count) {
			$str = (string) $str;
			$count = (int) $count;
			$parts = explode((string) $delim, $str);
			if ($count > 0) {
				return implode((string) $delim, array_slice($parts, 0, $count));
			}
			return implode((string) $delim, array_slice($parts, $count));
		}, 3);
		$sqlite->createFunction('RAND', static function () {
			return mt_rand() / mt_getrandmax();
		}, 0);
		$sqlite->createFunction('MD5', static function ($v) {
			return md5((string) $v);
		}, 1);
		$sqlite->createFunction('SHA1', static function ($v) {
			return sha1((string) $v);
		}, 1);
		$sqlite->createFunction('SLEEP', static function () {
			return 0;
		}, 1);
	}

	public static function rewrite(string $sql): string
	{
		$sql = preg_replace('/\s+AFTER\s+`[^`]+`/i', '', $sql) ?? $sql;
		$sql = preg_replace('/\s+AFTER\s+[A-Za-z0-9_]+/i', '', $sql) ?? $sql;
		$sql = preg_replace('/\s+UNSIGNED/i', '', $sql) ?? $sql;
		$sql = preg_replace('/\s+AUTO_INCREMENT/i', '', $sql) ?? $sql;
		$sql = preg_replace('/\s+ENGINE\s*=\s*\w+/i', '', $sql) ?? $sql;
		$sql = preg_replace('/\s+DEFAULT CHARSET\s*=\s*\w+/i', '', $sql) ?? $sql;
		$sql = preg_replace('/\s+CHARACTER SET\s+\w+/i', '', $sql) ?? $sql;
		$sql = preg_replace('/\s+COLLATE\s+\w+/i', '', $sql) ?? $sql;
		$sql = preg_replace('/\s+COMMENT\s+\'(?:\\\\\'|[^\'])*\'/i', '', $sql) ?? $sql;
		$sql = preg_replace('/TINYINT\s*\(\s*\d+\s*\)/i', 'INTEGER', $sql) ?? $sql;
		$sql = preg_replace('/INT\s*\(\s*\d+\s*\)/i', 'INTEGER', $sql) ?? $sql;
		$sql = preg_replace('/BIGINT\s*\(\s*\d+\s*\)/i', 'INTEGER', $sql) ?? $sql;
		$sql = preg_replace('/^INSERT\s+IGNORE\b/i', 'INSERT OR IGNORE', $sql) ?? $sql;
		$sql = preg_replace('/\bTRUE\b/i', '1', $sql) ?? $sql;
		$sql = preg_replace('/\bFALSE\b/i', '0', $sql) ?? $sql;
		return $sql;
	}

	private static function mysqlDateToPhp(string $fmt): string
	{
		$map = [
			'%Y' => 'Y', '%y' => 'y', '%m' => 'm', '%c' => 'n', '%d' => 'd', '%e' => 'j',
			'%H' => 'H', '%h' => 'h', '%i' => 'i', '%s' => 's', '%p' => 'A',
			'%M' => 'F', '%b' => 'M', '%W' => 'l', '%a' => 'D', '%w' => 'w',
			'%T' => 'H:i:s', '%r' => 'h:i:s A',
		];
		return strtr($fmt, $map);
	}
}
