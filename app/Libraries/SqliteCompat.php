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
		@$sqlite->exec('PRAGMA busy_timeout = 60000');
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
		$sqlite->createFunction('REGEXP', static function ($pattern, $value) {
			$pattern = (string) $pattern;
			$value = (string) ($value ?? '');
			if ($pattern === '') {
				return 0;
			}
			$regex = '~' . str_replace('~', '\~', $pattern) . '~iu';
			$result = @preg_match($regex, $value);
			return $result === 1 ? 1 : 0;
		}, 2);
		$sqlite->createFunction('SLEEP', static function () {
			return 0;
		}, 1);
		$sqlite->createFunction('DATABASE', static function () {
			return 'main';
		}, 0);
	}

	public static function rewrite(string $sql): string
	{
		$trim = ltrim($sql);

		if (preg_match('/^SHOW\s+(INDEX|INDEXES|KEYS)\s+FROM/i', $trim)) {
			return 'SELECT NULL AS `Key_name` WHERE 0';
		}
		if (preg_match('/^SHOW\s+COLUMNS\s+FROM\s+`?([A-Za-z0-9_]+)`?/i', $trim, $m)) {
			$table = str_replace("'", "''", $m[1]);
			return "SELECT name AS Field, type AS Type, CASE WHEN notnull = 0 THEN 'YES' ELSE 'NO' END AS `Null`, '' AS `Key`, dflt_value AS `Default`, '' AS Extra FROM pragma_table_info('{$table}')";
		}
		if (preg_match('/^SHOW\s+TABLES/i', $trim)) {
			return "SELECT name AS `Tables_in_db` FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'";
		}
		if (preg_match('/^ALTER\s+TABLE.+\s+ADD\s+(UNIQUE\s+)?(KEY|INDEX|FULLTEXT)/i', $trim)) {
			return 'SELECT 1 WHERE 0';
		}
		if (preg_match('/^ALTER\s+TABLE.+\s+DROP\s+(INDEX|KEY)/i', $trim)) {
			return 'SELECT 1 WHERE 0';
		}

		if (preg_match('/information_schema\.COLUMNS/i', $sql) && preg_match("/TABLE_NAME\s*=\s*'([^']+)'/i", $sql, $tm) && preg_match("/COLUMN_NAME\s*=\s*'([^']+)'/i", $sql, $cm)) {
			$table = str_replace("'", "''", $tm[1]);
			$col = str_replace("'", "''", $cm[1]);
			return "SELECT COUNT(*) AS c FROM pragma_table_info('{$table}') WHERE name = '{$col}'";
		}

		$sql = preg_replace('/\s+AFTER\s+`[^`]+`/i', '', $sql) ?? $sql;
		$sql = preg_replace('/\s+AFTER\s+[A-Za-z0-9_]+/i', '', $sql) ?? $sql;
		$sql = preg_replace('/\s+FIRST\b/i', '', $sql) ?? $sql;
		$sql = preg_replace('/\s+UNSIGNED/i', '', $sql) ?? $sql;
		$sql = preg_replace('/\s+ZEROFILL/i', '', $sql) ?? $sql;
		$sql = preg_replace('/\s+AUTO_INCREMENT/i', '', $sql) ?? $sql;
		$sql = preg_replace('/\s+ENGINE\s*=\s*\w+/i', '', $sql) ?? $sql;
		$sql = preg_replace('/\s+DEFAULT CHARSET\s*=\s*\w+/i', '', $sql) ?? $sql;
		$sql = preg_replace('/\s+DEFAULT CHARACTER SET\s+\w+/i', '', $sql) ?? $sql;
		$sql = preg_replace('/\s+CHARACTER SET\s+\w+/i', '', $sql) ?? $sql;
		$sql = preg_replace('/\s+COLLATE\s+\w+/i', '', $sql) ?? $sql;
		$sql = preg_replace('/\s+COMMENT\s+\'(?:\\\\\'|[^\'])*\'/i', '', $sql) ?? $sql;
		$sql = preg_replace('/\s+ON\s+UPDATE\s+CURRENT_TIMESTAMP(?:\(\))?/i', '', $sql) ?? $sql;
		$sql = preg_replace_callback(
			'/GROUP_CONCAT\s*\(\s*DISTINCT\s+(.*?)\s+ORDER\s+BY\s+[^)]*?\s+SEPARATOR\s+((?:\'(?:\'\'|[^\'])*\'|"(?:""|[^"])*"))\s*\)/is',
			static function (array $match): string {
				return "REPLACE(GROUP_CONCAT(DISTINCT {$match[1]}), ',', {$match[2]})";
			},
			$sql,
		) ?? $sql;
		$sql = preg_replace('/TINYINT\s*\(\s*\d+\s*\)/i', 'INTEGER', $sql) ?? $sql;
		$sql = preg_replace('/SMALLINT\s*\(\s*\d+\s*\)/i', 'INTEGER', $sql) ?? $sql;
		$sql = preg_replace('/MEDIUMINT\s*\(\s*\d+\s*\)/i', 'INTEGER', $sql) ?? $sql;
		$sql = preg_replace('/BIGINT\s*\(\s*\d+\s*\)/i', 'INTEGER', $sql) ?? $sql;
		$sql = preg_replace('/INT\s*\(\s*\d+\s*\)/i', 'INTEGER', $sql) ?? $sql;
		$sql = preg_replace('/ENUM\s*\([^)]+\)/i', 'TEXT', $sql) ?? $sql;
		$sql = preg_replace('/SET\s*\([^)]+\)/i', 'TEXT', $sql) ?? $sql;
		$sql = preg_replace('/(?:DECIMAL|NUMERIC|DOUBLE|FLOAT)\s*\(\s*\d+\s*,\s*\d+\s*\)/i', 'REAL', $sql) ?? $sql;
		$sql = preg_replace('/\bLONGTEXT\b/i', 'TEXT', $sql) ?? $sql;
		$sql = preg_replace('/\bMEDIUMTEXT\b/i', 'TEXT', $sql) ?? $sql;
		$sql = preg_replace('/\bTINYTEXT\b/i', 'TEXT', $sql) ?? $sql;
		$sql = preg_replace('/^INSERT\s+IGNORE\b/i', 'INSERT OR IGNORE', $sql) ?? $sql;
		$sql = preg_replace('/^REPLACE\s+INTO\b/i', 'INSERT OR REPLACE INTO', $sql) ?? $sql;
		$sql = preg_replace('/\bTRUE\b/i', '1', $sql) ?? $sql;
		$sql = preg_replace('/\bFALSE\b/i', '0', $sql) ?? $sql;
		$sql = preg_replace('/DATE_SUB\s*\(\s*NOW\s*\(\s*\)\s*,\s*INTERVAL\s+(\d+)\s+MINUTE\s*\)/i', "datetime('now', '-$1 minutes')", $sql) ?? $sql;
		$sql = preg_replace('/DATE_SUB\s*\(\s*NOW\s*\(\s*\)\s*,\s*INTERVAL\s+(\d+)\s+DAY\s*\)/i', "datetime('now', '-$1 days')", $sql) ?? $sql;
		$sql = preg_replace('/\s+ORDER\s+BY\s+[A-Za-z0-9_`.]+\s+SEPARATOR\s+/i', ', ', $sql) ?? $sql;
		$sql = preg_replace('/\s+SEPARATOR\s+/i', ', ', $sql) ?? $sql;

		if (preg_match('/^\s*CREATE\s+TABLE/i', $sql)) {
			$sql = self::rewriteCreateTable($sql);
		}

		return $sql;
	}

	/**
	 * SQLite allows only one ADD COLUMN per ALTER TABLE.
	 *
	 * @return list<string>
	 */
	public static function expandStatements(string $sql): array
	{
		if (! preg_match('/^\s*ALTER\s+TABLE\s+(`?[A-Za-z0-9_]+`?)\s+(ADD\s+COLUMN\b.+)$/is', $sql, $m)) {
			return [$sql];
		}
		$table = $m[1];
		$rest = trim($m[2]);
		if (! preg_match('/,\s*ADD\s+COLUMN\b/i', $rest)) {
			return [$sql];
		}
		$chunks = preg_split('/,\s*(?=ADD\s+COLUMN\b)/i', $rest) ?: [$rest];
		$out = [];
		foreach ($chunks as $chunk) {
			$out[] = 'ALTER TABLE ' . $table . ' ' . trim($chunk);
		}
		return $out;
	}

	private static function rewriteCreateTable(string $sql): string
	{
		$sql = preg_replace('/\s+UNIQUE\s+KEY\s+`?[\w]+`?\s*/i', ' UNIQUE ', $sql) ?? $sql;
		$sql = preg_replace('/\s+UNIQUE\s+INDEX\s+`?[\w]+`?\s*/i', ' UNIQUE ', $sql) ?? $sql;
		$sql = preg_replace('/\s+USING\s+(?:BTREE|HASH)/i', '', $sql) ?? $sql;
		$sql = preg_replace('/,\s*(?:CONSTRAINT\s+`?[\w]+`?\s*)?(?:FULLTEXT\s+|SPATIAL\s+)?KEY\s+(?:`?[\w]+`?\s*)?\([^)]+\)/i', '', $sql) ?? $sql;
		$sql = preg_replace('/,\s*(?:FULLTEXT\s+|SPATIAL\s+)?INDEX\s+(?:`?[\w]+`?\s*)?\([^)]+\)/i', '', $sql) ?? $sql;
		$sql = preg_replace('/,\s*CONSTRAINT\s+`?[\w]+`?\s*FOREIGN\s+KEY\s*\([^)]+\)\s*REFERENCES\s+`?[\w]+`?\s*\([^)]+\)(?:\s+ON\s+DELETE\s+\w+)?(?:\s+ON\s+UPDATE\s+\w+)?/i', '', $sql) ?? $sql;
		$sql = preg_replace('/,(\s*\))/', '$1', $sql) ?? $sql;
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

	public static function fontAwesomeHref(): string
	{
		if (self::isDesktop()) {
			return base_url('assets/vendor/fontawesome/css/all.min.css');
		}
		return 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.11.2/css/all.css';
	}
}
