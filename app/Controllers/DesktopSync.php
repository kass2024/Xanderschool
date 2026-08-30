<?php

namespace App\Controllers;

use App\Models\StaffModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Bidirectional sync API for the Windows desktop app.
 *
 * Server (MySQL): login / schema / pull / push
 * Desktop (SQLite): localStatus / localTick
 */
class DesktopSync extends BaseController
{
	private const TOKEN_TTL = 86400 * 90;
	private const PULL_LIMIT = 400;
	private const SKIP_TABLES = [
		'ci_sessions',
		'sessions',
		'desktop_sync_tokens',
		'sqlite_sequence',
		'sqlite_master',
	];

	public function health()
	{
		return $this->response->setJSON([
			'ok' => true,
			'service' => 'xander-school-desktop-sync',
			'desktop' => \App\Libraries\SqliteCompat::isDesktop(),
			'time' => date('c'),
		]);
	}

	public function login()
	{
		$email = trim((string) $this->request->getPost('email'));
		$password = (string) $this->request->getPost('password');
		$device = trim((string) $this->request->getPost('device_name'));
		if ($email === '' || $password === '') {
			$body = $this->request->getJSON(true);
			if (is_array($body)) {
				$email = trim((string) ($body['email'] ?? $email));
				$password = (string) ($body['password'] ?? $password);
				$device = trim((string) ($body['device_name'] ?? $device));
			}
		}
		if ($email === '' || strlen($password) < 6) {
			return $this->fail('Email and password are required.', 422);
		}

		$model = new StaffModel();
		$result = $model->checkUser($email);
		if ($result === null || ! password_verify($password, $result->password)) {
			return $this->fail('Invalid email or password.', 401);
		}
		if (! in_array((int) $result->status, [1, 2], true)) {
			return $this->fail('Account is not active.', 403);
		}
		if ((int) $result->school_status === 0) {
			return $this->fail('School account is locked.', 403);
		}

		$this->ensureTokenTable();
		$token = bin2hex(random_bytes(32));
		$hash = hash('sha256', $token);
		$db = \Config\Database::connect();
		$expires = date('Y-m-d H:i:s', time() + self::TOKEN_TTL);
		$now = date('Y-m-d H:i:s');
		$db->table('desktop_sync_tokens')->insert([
			'staff_id' => (int) $result->id,
			'school_id' => (int) $result->school_id,
			'token_hash' => $hash,
			'device_name' => $device !== '' ? substr($device, 0, 120) : 'Xander School Desktop',
			'last_seen' => $now,
			'created_at' => $now,
			'expires_at' => $expires,
		]);

		return $this->response->setJSON([
			'ok' => true,
			'token' => $token,
			'expires_at' => $expires,
			'staff' => [
				'id' => (int) $result->id,
				'name' => trim($result->fname . ' ' . $result->lname),
				'email' => $result->email,
				'post_title' => $result->post_title ?? '',
			],
			'school' => [
				'id' => (int) $result->school_id,
				'name' => $result->school_name ?? '',
			],
		]);
	}

	public function schema()
	{
		$auth = $this->requireToken();
		if ($auth instanceof ResponseInterface) {
			return $auth;
		}
		$db = \Config\Database::connect();
		$tables = [];
		foreach ($this->listTables($db) as $table) {
			$tables[] = [
				'name' => $table,
				'columns' => $this->describeTable($db, $table),
			];
		}
		return $this->response->setJSON([
			'ok' => true,
			'school_id' => (int) $auth['school_id'],
			'tables' => $tables,
		]);
	}

	public function pull()
	{
		$auth = $this->requireToken();
		if ($auth instanceof ResponseInterface) {
			return $auth;
		}
		$table = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $this->request->getGet('table'));
		$afterId = (int) $this->request->getGet('after_id');
		$updatedSince = trim((string) $this->request->getGet('updated_since'));
		$limit = (int) $this->request->getGet('limit');
		if ($limit < 1 || $limit > 800) {
			$limit = self::PULL_LIMIT;
		}
		if ($table === '' || in_array($table, self::SKIP_TABLES, true)) {
			return $this->fail('Invalid table.', 422);
		}

		$db = \Config\Database::connect();
		if (! $db->tableExists($table)) {
			return $this->fail('Table not found.', 404);
		}

		$fields = $db->getFieldNames($table);
		$pk = $this->primaryKey($fields);
		$builder = $db->table($table);
		if (in_array('school_id', $fields, true)) {
			$builder->where('school_id', (int) $auth['school_id']);
		} elseif ($table === 'schools') {
			$builder->where('id', (int) $auth['school_id']);
		}
		$timeCol = in_array('updated_at', $fields, true) ? 'updated_at' : (in_array('created_at', $fields, true) ? 'created_at' : '');
		if ($updatedSince !== '' && $timeCol !== '') {
			$builder->where($timeCol . ' >=', $updatedSince);
			if ($pk !== '' && $afterId > 0) {
				$builder->where($pk . ' >', $afterId);
			}
		} elseif ($pk !== '' && $afterId > 0) {
			$builder->where($pk . ' >', $afterId);
		}
		if ($pk !== '') {
			$builder->orderBy($pk, 'ASC');
		}
		$rows = $builder->limit($limit)->get()->getResultArray();
		$next = 0;
		if ($pk !== '' && $rows) {
			$last = end($rows);
			$next = (int) ($last[$pk] ?? 0);
		}

		return $this->response->setJSON([
			'ok' => true,
			'table' => $table,
			'pk' => $pk,
			'count' => count($rows),
			'rows' => $rows,
			'next_after_id' => $next,
			'has_more' => count($rows) >= $limit,
		]);
	}

	public function push()
	{
		$auth = $this->requireToken();
		if ($auth instanceof ResponseInterface) {
			return $auth;
		}
		$body = $this->request->getJSON(true);
		if (! is_array($body)) {
			$body = $this->request->getPost();
		}
		$changes = $body['changes'] ?? [];
		if (! is_array($changes)) {
			return $this->fail('Invalid payload.', 422);
		}

		$db = \Config\Database::connect();
		$applied = 0;
		$errors = [];
		foreach ($changes as $i => $change) {
			if (! is_array($change)) {
				continue;
			}
			$table = preg_replace('/[^a-zA-Z0-9_]/', '', (string) ($change['table'] ?? ''));
			$op = strtolower((string) ($change['op'] ?? 'upsert'));
			$row = $change['row'] ?? [];
			$pkVal = $change['pk'] ?? ($row['id'] ?? null);
			if ($table === '' || in_array($table, self::SKIP_TABLES, true) || ! $db->tableExists($table)) {
				$errors[] = ['index' => $i, 'error' => 'Unknown table'];
				continue;
			}
			$fields = $db->getFieldNames($table);
			if (in_array('school_id', $fields, true) && is_array($row)) {
				$row['school_id'] = (int) $auth['school_id'];
			}
			try {
				if ($op === 'delete') {
					$pk = $this->primaryKey($fields);
					if ($pk === '' || $pkVal === null || $pkVal === '') {
						throw new \RuntimeException('Missing primary key for delete');
					}
					$del = $db->table($table)->where($pk, $pkVal);
					if (in_array('school_id', $fields, true)) {
						$del->where('school_id', (int) $auth['school_id']);
					}
					$del->delete();
					$applied++;
					continue;
				}
				if (! is_array($row) || $row === []) {
					throw new \RuntimeException('Empty row');
				}
				$clean = [];
				foreach ($row as $k => $v) {
					if (in_array($k, $fields, true)) {
						$clean[$k] = $v;
					}
				}
				if ($clean === []) {
					throw new \RuntimeException('No matching columns');
				}
				$pk = $this->primaryKey($fields);
				$exists = false;
				if ($pk !== '' && isset($clean[$pk]) && $clean[$pk] !== '' && $clean[$pk] !== null) {
					$exists = $db->table($table)->where($pk, $clean[$pk])->countAllResults() > 0;
				}
				if ($exists) {
					$id = $clean[$pk];
					unset($clean[$pk]);
					$upd = $db->table($table)->where($pk, $id);
					if (in_array('school_id', $fields, true)) {
						$upd->where('school_id', (int) $auth['school_id']);
					}
					if ($clean !== []) {
						$upd->update($clean);
					}
				} else {
					$db->table($table)->insert($clean);
				}
				$applied++;
			} catch (\Throwable $e) {
				$errors[] = ['index' => $i, 'table' => $table, 'error' => $e->getMessage()];
			}
		}

		return $this->response->setJSON([
			'ok' => true,
			'applied' => $applied,
			'errors' => $errors,
		]);
	}

	public function localStatus()
	{
		if (! \App\Libraries\SqliteCompat::isDesktop()) {
			return $this->fail('Not a desktop instance.', 404);
		}
		$db = \Config\Database::connect();
		$pending = 0;
		$lastPull = null;
		$lastPush = null;
		try {
			if ($db->tableExists('_sync_queue')) {
				$pending = (int) $db->table('_sync_queue')->where('status', 'pending')->countAllResults();
			}
			if ($db->tableExists('_sync_meta')) {
				$rows = $db->table('_sync_meta')->get()->getResultArray();
				foreach ($rows as $row) {
					if (($row['k'] ?? '') === 'last_pull') {
						$lastPull = $row['v'] ?? null;
					}
					if (($row['k'] ?? '') === 'last_push') {
						$lastPush = $row['v'] ?? null;
					}
				}
			}
		} catch (\Throwable $e) {
			// empty first-run database
		}
		return $this->response->setJSON([
			'ok' => true,
			'pending' => $pending,
			'last_pull' => $lastPull,
			'last_push' => $lastPush,
			'db' => getenv('XANDER_SQLITE_PATH') ?: '',
		]);
	}

	public function localTick()
	{
		if (! \App\Libraries\SqliteCompat::isDesktop()) {
			return $this->fail('Not a desktop instance.', 404);
		}
		return $this->response->setJSON([
			'ok' => true,
			'message' => 'Sync is handled by the desktop engine.',
		]);
	}

	private function requireToken()
	{
		$header = (string) $this->request->getHeaderLine('Authorization');
		$token = '';
		if (stripos($header, 'Bearer ') === 0) {
			$token = trim(substr($header, 7));
		}
		if ($token === '') {
			$token = (string) $this->request->getGet('token');
		}
		if ($token === '') {
			return $this->fail('Missing token.', 401);
		}
		$this->ensureTokenTable();
		$hash = hash('sha256', $token);
		$db = \Config\Database::connect();
		$row = $db->table('desktop_sync_tokens')
			->where('token_hash', $hash)
			->where('expires_at >', date('Y-m-d H:i:s'))
			->get()
			->getRowArray();
		if (! $row) {
			return $this->fail('Invalid or expired token.', 401);
		}
		$db->table('desktop_sync_tokens')->where('id', $row['id'])->update([
			'last_seen' => date('Y-m-d H:i:s'),
		]);
		return [
			'staff_id' => (int) $row['staff_id'],
			'school_id' => (int) $row['school_id'],
		];
	}

	private function ensureTokenTable(): void
	{
		$db = \Config\Database::connect();
		if ($db->tableExists('desktop_sync_tokens')) {
			return;
		}
		$db->query("CREATE TABLE IF NOT EXISTS `desktop_sync_tokens` (
			`id` INT NOT NULL AUTO_INCREMENT,
			`staff_id` INT NOT NULL,
			`school_id` INT NOT NULL,
			`token_hash` VARCHAR(64) NOT NULL,
			`device_name` VARCHAR(120) DEFAULT NULL,
			`last_seen` DATETIME DEFAULT NULL,
			`created_at` DATETIME DEFAULT NULL,
			`expires_at` DATETIME DEFAULT NULL,
			PRIMARY KEY (`id`),
			UNIQUE KEY `token_hash` (`token_hash`),
			KEY `school_id` (`school_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8");
	}

	private function listTables($db): array
	{
		$out = [];
		foreach ($db->listTables() as $t) {
			$t = (string) $t;
			if ($t === '' || in_array($t, self::SKIP_TABLES, true) || strpos($t, '_') === 0) {
				continue;
			}
			$out[] = $t;
		}
		sort($out);
		return $out;
	}

	private function describeTable($db, string $table): array
	{
		$cols = [];
		foreach ($db->getFieldData($table) as $f) {
			$cols[] = [
				'name' => $f->name,
				'type' => $f->type ?? 'TEXT',
				'max_length' => $f->max_length ?? null,
				'nullable' => (bool) ($f->nullable ?? true),
				'default' => $f->default ?? null,
				'primary_key' => ! empty($f->primary_key),
			];
		}
		return $cols;
	}

	private function primaryKey(array $fields): string
	{
		return in_array('id', $fields, true) ? 'id' : ($fields[0] ?? '');
	}

	private function fail(string $message, int $code)
	{
		return $this->response->setStatusCode($code)->setJSON([
			'ok' => false,
			'error' => $message,
		]);
	}
}
