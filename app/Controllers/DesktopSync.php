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
	/** Shared lookup tables with no school_id — copy in full. */
	private const GLOBAL_TABLES = [
		'packages',
		'posts',
		'faculty',
		'levels',
		'countries',
		'provinces',
		'districts',
		'sectors',
		'cells',
		'villages',
		'soma_cell',
		'soma_village',
		'ubudehe',
		'permissions',
		'type_permission',
		'master_central_posts',
		'course_category',
		'budget_permissions',
		'post_budget_permissions',
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
		// Ensure newer feature tables exist so desktop can sync them offline
		try {
			(new \App\Models\StudentMaterialSchemaModel())->ensureSchema();
			(new \App\Models\HostelSchemaModel())->ensureSchema();
		} catch (\Throwable $e) {
			log_message('error', 'Desktop schema ensure failed: ' . $e->getMessage());
		}
		$db = \Config\Database::connect();
		$tables = [];
		foreach ($this->listTables($db) as $table) {
			$fields = $db->getFieldNames($table);
			$tables[] = [
				'name' => $table,
				'columns' => $this->describeTable($db, $table),
				'writable' => $this->isWritableTable($table, $fields),
				'priority' => $this->syncPriority($table),
			];
		}
		usort($tables, static function (array $a, array $b): int {
			return ($a['priority'] ?? 100) <=> ($b['priority'] ?? 100)
				?: strcmp((string) $a['name'], (string) $b['name']);
		});
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
		$pk = $this->primaryKey($db, $table, $fields);
		$builder = $db->table($table);
		$scope = $this->applyScope($builder, $db, $table, $fields, (int) $auth['school_id']);
		if ($scope === null) {
			return $this->response->setJSON([
				'ok' => true,
				'table' => $table,
				'pk' => $pk,
				'count' => 0,
				'rows' => [],
				'next_after_id' => 0,
				'has_more' => false,
				'skipped' => true,
			]);
		}
		$full = (string) $this->request->getGet('full') === '1';
		$timeCol = in_array('updated_at', $fields, true) ? 'updated_at' : (in_array('created_at', $fields, true) ? 'created_at' : '');
		if (! $full && $updatedSince !== '' && $timeCol !== '') {
			$timestamp = strtotime($updatedSince);
			if ($timestamp === false) {
				return $this->fail('Invalid updated_since value.', 422);
			}
			$updatedSince = date('Y-m-d H:i:s', $timestamp);
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
			'skipped' => false,
			'scoped' => $scope,
		]);
	}

	public function ids()
	{
		$auth = $this->requireToken();
		if ($auth instanceof ResponseInterface) {
			return $auth;
		}
		$table = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $this->request->getGet('table'));
		$afterId = (int) $this->request->getGet('after_id');
		$limit = (int) $this->request->getGet('limit');
		if ($limit < 1 || $limit > 5000) {
			$limit = 2000;
		}
		if ($table === '' || in_array($table, self::SKIP_TABLES, true)) {
			return $this->fail('Invalid table.', 422);
		}
		$db = \Config\Database::connect();
		if (! $db->tableExists($table)) {
			return $this->fail('Table not found.', 404);
		}
		$fields = $db->getFieldNames($table);
		$pk = $this->primaryKey($db, $table, $fields);
		if ($pk === '') {
			return $this->response->setJSON([
				'ok' => true,
				'table' => $table,
				'pk' => '',
				'ids' => [],
				'next_after_id' => 0,
				'has_more' => false,
				'skipped' => true,
			]);
		}
		$builder = $db->table($table);
		$scope = $this->applyScope($builder, $db, $table, $fields, (int) $auth['school_id']);
		if ($scope === null) {
			return $this->response->setJSON([
				'ok' => true,
				'table' => $table,
				'pk' => $pk,
				'ids' => [],
				'next_after_id' => 0,
				'has_more' => false,
				'skipped' => true,
			]);
		}
		$builder->select($pk);
		if ($afterId > 0) {
			$builder->where($pk . ' >', $afterId);
		}
		$builder->orderBy($pk, 'ASC');
		$rows = $builder->limit($limit)->get()->getResultArray();
		$ids = [];
		foreach ($rows as $row) {
			$ids[] = $row[$pk] ?? null;
		}
		$next = 0;
		if ($rows) {
			$last = end($rows);
			$next = (int) ($last[$pk] ?? 0);
		}
		return $this->response->setJSON([
			'ok' => true,
			'table' => $table,
			'pk' => $pk,
			'ids' => $ids,
			'next_after_id' => $next,
			'has_more' => count($rows) >= $limit,
			'skipped' => false,
		]);
	}

	public function photo()
	{
		$auth = $this->requireToken();
		if ($auth instanceof ResponseInterface) {
			return $auth;
		}
		$name = basename(trim((string) $this->request->getGet('name')));
		if ($name === '' || $name === '.' || $name === '..' || ! preg_match('/^[A-Za-z0-9._-]+$/', $name)) {
			return $this->fail('Invalid photo name.', 422);
		}
		$db = \Config\Database::connect();
		if (! $db->tableExists('students') || $db->table('students')
			->where('school_id', (int) $auth['school_id'])
			->like('photo', $name, 'after')
			->countAllResults() < 1) {
			return $this->fail('Photo not found.', 404);
		}
		$path = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'profile' . DIRECTORY_SEPARATOR . $name;
		if (! is_file($path)) {
			$matches = array_values(array_filter(glob($path . '*') ?: [], 'is_file'));
			if (count($matches) === 1) {
				$path = $matches[0];
			}
		}
		if (! is_file($path)) {
			return $this->fail('Photo not found.', 404);
		}
		$mime = function_exists('mime_content_type') ? mime_content_type($path) : 'application/octet-stream';
		if (! in_array($mime, ['image/jpeg', 'image/png'], true)) {
			return $this->fail('Unsupported photo type.', 415);
		}
		return $this->response
			->setHeader('Content-Type', $mime)
			->setHeader('Content-Length', (string) filesize($path))
			->setBody((string) file_get_contents($path));
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
		if (count($changes) > 500) {
			return $this->fail('Too many changes in one request.', 422);
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
			$pk = $this->primaryKey($db, $table, $fields);
			if (! $this->isWritableTable($table, $fields)) {
				$errors[] = ['index' => $i, 'table' => $table, 'error' => 'Table is read-only for desktop sync'];
				continue;
			}
			if (! in_array($op, ['upsert', 'delete'], true)) {
				$errors[] = ['index' => $i, 'table' => $table, 'error' => 'Unsupported operation'];
				continue;
			}
			if (in_array('school_id', $fields, true) && is_array($row)) {
				$row['school_id'] = (int) $auth['school_id'];
			}
			try {
				if ($op === 'delete') {
					if ($this->isFinanceProtectedTable($table)) {
						throw new \RuntimeException('Finance records cannot be deleted via desktop sync');
					}
					if ($pk === '' || $pkVal === null || $pkVal === '') {
						throw new \RuntimeException('Missing primary key for delete');
					}
					$del = $db->table($table)->where($pk, $pkVal);
					$scope = $this->applyScope($del, $db, $table, $fields, (int) $auth['school_id']);
					if ($scope === null) {
						throw new \RuntimeException('Change is outside this school');
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
				$exists = false;
				$existingRow = null;
				if ($pk !== '' && isset($clean[$pk]) && $clean[$pk] !== '' && $clean[$pk] !== null) {
					$target = $db->table($table)->where($pk, $clean[$pk]);
					$scope = $this->applyScope($target, $db, $table, $fields, (int) $auth['school_id']);
					if ($scope === null) {
						throw new \RuntimeException('Change is outside this school');
					}
					$existingRow = $target->get(1)->getRowArray();
					$exists = ! empty($existingRow);
				}
				if ($exists) {
					$id = $clean[$pk];
					unset($clean[$pk]);
					$clean = $this->mergeSafeUpsert($table, $existingRow ?: [], $clean);
					$upd = $db->table($table)->where($pk, $id);
					if ($this->applyScope($upd, $db, $table, $fields, (int) $auth['school_id']) === null) {
						throw new \RuntimeException('Change is outside this school');
					}
					if ($clean !== []) {
						$upd->update($clean);
					}
				} else {
					if (! $this->insertBelongsToSchool($db, $table, $fields, $clean, (int) $auth['school_id'])) {
						throw new \RuntimeException('New row is outside this school');
					}
					$db->table($table)->insert($clean);
				}
				if ($table === 'students' && isset($change['photo_base64'], $clean['photo'])) {
					$name = basename(trim((string) $clean['photo']));
					$encoded = preg_replace('/\s+/', '', (string) $change['photo_base64']);
					$image = base64_decode($encoded, true);
					$isJpeg = is_string($image) && strncmp($image, "\xFF\xD8\xFF", 3) === 0;
					$isPng = is_string($image) && strncmp($image, "\x89PNG", 4) === 0;
					if ($name === '' || ! preg_match('/^[A-Za-z0-9._-]+$/', $name) || $image === false || strlen($image) > 8 * 1024 * 1024 || (! $isJpeg && ! $isPng)) {
						throw new \RuntimeException('Invalid student photo');
					}
					$profilePath = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'profile' . DIRECTORY_SEPARATOR;
					if (! is_dir($profilePath)) {
						@mkdir($profilePath, 0775, true);
					}
					if (file_put_contents($profilePath . $name, $image) === false) {
						throw new \RuntimeException('Student photo could not be saved');
					}
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

	private function primaryKey($db, string $table, array $fields): string
	{
		try {
			foreach ($db->getFieldData($table) as $f) {
				if (! empty($f->primary_key)) {
					return (string) $f->name;
				}
			}
		} catch (\Throwable $e) {
			// fall through
		}
		return in_array('id', $fields, true) ? 'id' : ($fields[0] ?? '');
	}

	/**
	 * Restrict a pull/ids query to this school. Returns true if filtered or global,
	 * null if the table should not be copied (other tenants' data).
	 */
	private function applyScope($builder, $db, string $table, array $fields, int $schoolId): ?bool
	{
		$sid = (int) $schoolId;
		if ($sid < 1) {
			return null;
		}
		if (in_array('school_id', $fields, true)) {
			$builder->where('school_id', $sid);
			return true;
		}
		if (in_array('schoolId', $fields, true)) {
			$builder->where('schoolId', $sid);
			return true;
		}
		if ($table === 'schools') {
			$builder->where('id', $sid);
			return true;
		}
		if (in_array('branch_id', $fields, true) && $db->tableExists('branches')) {
			if (in_array('organization_id', $fields, true)) {
				$builder->groupStart()
					->where("branch_id IN (SELECT id FROM branches WHERE school_id = {$sid})", null, false)
					->orWhere("organization_id IN (SELECT organization_id FROM branches WHERE school_id = {$sid} AND organization_id IS NOT NULL)", null, false)
					->groupEnd();
			} else {
				$builder->where("branch_id IN (SELECT id FROM branches WHERE school_id = {$sid})", null, false);
			}
			return true;
		}
		if (in_array('organization_id', $fields, true) && $db->tableExists('branches')) {
			$builder->where("organization_id IN (SELECT organization_id FROM branches WHERE school_id = {$sid} AND organization_id IS NOT NULL)");
			return true;
		}
		if ($table === 'organizations' && $db->tableExists('branches')) {
			$builder->where("id IN (SELECT organization_id FROM branches WHERE school_id = {$sid} AND organization_id IS NOT NULL)");
			return true;
		}
		if (in_array($table, self::GLOBAL_TABLES, true)) {
			return true;
		}
		if ((in_array('student_id', $fields, true) || in_array('student', $fields, true)) && $db->tableExists('students')) {
			$column = in_array('student_id', $fields, true) ? 'student_id' : 'student';
			$builder->where("{$column} IN (SELECT id FROM students WHERE school_id = {$sid})");
			return true;
		}
		if ((in_array('class_id', $fields, true) || in_array('class', $fields, true)) && $db->tableExists('classes')) {
			$column = in_array('class_id', $fields, true) ? 'class_id' : 'class';
			$builder->where("{$column} IN (SELECT id FROM classes WHERE school_id = {$sid})");
			return true;
		}
		if ((in_array('staff_id', $fields, true) || in_array('lecturer', $fields, true)) && $db->tableExists('staffs')) {
			$column = in_array('staff_id', $fields, true) ? 'staff_id' : 'lecturer';
			$builder->where("{$column} IN (SELECT id FROM staffs WHERE school_id = {$sid})");
			return true;
		}
		if ($table === 'departments' && $db->tableExists('classes')) {
			$builder->where("id IN (SELECT department FROM classes WHERE school_id = {$sid})");
			return true;
		}
		if (in_array('department_id', $fields, true) && $db->tableExists('classes')) {
			$builder->where("department_id IN (SELECT department FROM classes WHERE school_id = {$sid})");
			return true;
		}
		if (in_array('parent_id', $fields, true) && $db->tableExists('students')) {
			$builder->where("parent_id IN (SELECT parent_id FROM students WHERE school_id = {$sid} AND parent_id IS NOT NULL AND parent_id <> 0)");
			return true;
		}
		if ($table === 'parents' && $db->tableExists('students')) {
			$builder->where("id IN (SELECT parent_id FROM students WHERE school_id = {$sid} AND parent_id IS NOT NULL AND parent_id <> 0)");
			return true;
		}
		if (in_array('user_id', $fields, true) && $db->tableExists('staffs')) {
			$builder->where("user_id IN (SELECT id FROM staffs WHERE school_id = {$sid})");
			return true;
		}
		if (in_array('budget_id', $fields, true) && $db->tableExists('budgets')) {
			$builder->where("budget_id IN (SELECT id FROM budgets WHERE branch_id IN (SELECT id FROM branches WHERE school_id = {$sid}))");
			return true;
		}
		if (in_array('budget_period_id', $fields, true) && $db->tableExists('budget_periods')) {
			$builder->where("budget_period_id IN (SELECT id FROM budget_periods WHERE branch_id IN (SELECT id FROM branches WHERE school_id = {$sid}))");
			return true;
		}
		if (in_array('cash_request_id', $fields, true) && $db->tableExists('cash_requests')) {
			$builder->where("cash_request_id IN (SELECT id FROM cash_requests WHERE branch_id IN (SELECT id FROM branches WHERE school_id = {$sid}))");
			return true;
		}
		if (in_array('applicationId', $fields, true) && $db->tableExists('applications')) {
			$builder->where("applicationId IN (SELECT id FROM applications WHERE schoolId = {$sid})");
			return true;
		}
		if (in_array('template_id', $fields, true) && $db->tableExists('budget_templates')) {
			$builder->where("template_id IN (SELECT id FROM budget_templates WHERE organization_id IN (SELECT organization_id FROM branches WHERE school_id = {$sid} AND organization_id IS NOT NULL))");
			return true;
		}
		if (in_array('version_id', $fields, true) && $db->tableExists('budget_template_versions')) {
			$builder->where("version_id IN (SELECT id FROM budget_template_versions WHERE template_id IN (SELECT id FROM budget_templates WHERE organization_id IN (SELECT organization_id FROM branches WHERE school_id = {$sid} AND organization_id IS NOT NULL)))");
			return true;
		}
		if (in_array('payment_id', $fields, true) && $db->tableExists('cash_request_payments')) {
			$builder->where("payment_id IN (SELECT id FROM cash_request_payments WHERE cash_request_id IN (SELECT id FROM cash_requests WHERE branch_id IN (SELECT id FROM branches WHERE school_id = {$sid})))");
			return true;
		}
		return null;
	}

	private function isWritableTable(string $table, array $fields): bool
	{
		if (in_array($table, self::SKIP_TABLES, true) || in_array($table, self::GLOBAL_TABLES, true)) {
			return false;
		}
		if ($table === 'schools') {
			return false;
		}
		return in_array('school_id', $fields, true)
			|| in_array('schoolId', $fields, true)
			|| in_array('student_id', $fields, true)
			|| in_array('student', $fields, true)
			|| in_array('class_id', $fields, true)
			|| in_array('class', $fields, true)
			|| in_array('staff_id', $fields, true)
			|| in_array('lecturer', $fields, true)
			|| in_array('department_id', $fields, true)
			|| in_array('parent_id', $fields, true)
			|| in_array('user_id', $fields, true)
			|| in_array('branch_id', $fields, true)
			|| in_array('organization_id', $fields, true)
			|| in_array('budget_id', $fields, true)
			|| in_array('budget_period_id', $fields, true)
			|| in_array('cash_request_id', $fields, true)
			|| in_array('template_id', $fields, true)
			|| in_array('version_id', $fields, true)
			|| in_array('payment_id', $fields, true)
			|| in_array('hostel_id', $fields, true)
			|| in_array('material_id', $fields, true);
	}

	/** Lower = sync earlier (finance + students first). */
	private function syncPriority(string $table): int
	{
		$map = [
			'students' => 1,
			'classes' => 2,
			'staffs' => 3,
			'academic_year' => 4,
			'school_fees' => 5,
			'extra_fees' => 6,
			'fees_records' => 7,
			'cash_requests' => 8,
			'cash_request_payments' => 9,
			'cash_request_approvals' => 10,
			'budgets' => 11,
			'budget_periods' => 12,
			'budget_lines' => 13,
			'required_materials' => 14,
			'class_required_materials' => 15,
			'student_material_checks' => 16,
			'hostels' => 17,
			'hostel_allocations' => 18,
			'hostel_settings' => 19,
		];
		return $map[$table] ?? 100;
	}

	private function isFinanceProtectedTable(string $table): bool
	{
		return in_array($table, [
			'fees_records',
			'cash_request_payments',
			'school_fees',
			'extra_fees',
		], true);
	}

	/**
	 * Keep local/online finance & material progress from going backwards on conflict.
	 *
	 * @param array<string,mixed> $existing
	 * @param array<string,mixed> $incoming
	 * @return array<string,mixed>
	 */
	private function mergeSafeUpsert(string $table, array $existing, array $incoming): array
	{
		if ($table === 'student_material_checks') {
			$ex = (float) ($existing['quantity_brought'] ?? 0);
			$in = (float) ($incoming['quantity_brought'] ?? 0);
			$incoming['quantity_brought'] = max($ex, $in);
			return $incoming;
		}
		if ($table === 'fees_records') {
			$ex = (float) ($existing['amount'] ?? 0);
			$in = (float) ($incoming['amount'] ?? 0);
			// Never shrink a fee payment amount via sync conflict
			$incoming['amount'] = max($ex, $in);
			return $incoming;
		}
		if (in_array($table, ['extra_fees', 'school_fees'], true)) {
			foreach (['paid', 'expected'] as $col) {
				if (array_key_exists($col, $existing) || array_key_exists($col, $incoming)) {
					$ex = (float) ($existing[$col] ?? 0);
					$in = (float) ($incoming[$col] ?? 0);
					// Prefer the higher paid/expected so offline posts are not lost
					if ($col === 'paid') {
						$incoming[$col] = max($ex, $in);
					}
				}
			}
			if (isset($incoming['expected'], $incoming['paid'])) {
				$incoming['balance'] = max(0, (float) $incoming['expected'] - (float) $incoming['paid']);
			}
			return $incoming;
		}
		return $incoming;
	}

	private function insertBelongsToSchool($db, string $table, array $fields, array $row, int $schoolId): bool
	{
		if (in_array('school_id', $fields, true)) {
			return (int) ($row['school_id'] ?? 0) === $schoolId;
		}
		$relations = [
			'student_id' => 'students',
			'student' => 'students',
			'class_id' => 'classes',
			'class' => 'classes',
			'staff_id' => 'staffs',
			'lecturer' => 'staffs',
			'department_id' => 'classes',
			'parent_id' => 'students',
			'user_id' => 'staffs',
			'branch_id' => 'branches',
			'organization_id' => 'organizations',
			'budget_id' => 'budgets',
			'budget_period_id' => 'budget_periods',
			'cash_request_id' => 'cash_requests',
			'applicationId' => 'applications',
			'template_id' => 'budget_templates',
			'version_id' => 'budget_template_versions',
			'payment_id' => 'cash_request_payments',
		];
		$checked = false;
		foreach ($relations as $field => $parentTable) {
			if (! in_array($field, $fields, true) || ! isset($row[$field]) || $row[$field] === '' || $row[$field] === null) {
				continue;
			}
			if (! $db->tableExists($parentTable)) {
				return false;
			}
			$parentFields = $db->getFieldNames($parentTable);
			$parent = $db->table($parentTable)->where('id', $row[$field]);
			if ($this->applyScope($parent, $db, $parentTable, $parentFields, $schoolId) === null || $parent->countAllResults() < 1) {
				return false;
			}
			$checked = true;
		}
		return $checked;
	}

	private function fail(string $message, int $code)
	{
		return $this->response->setStatusCode($code)->setJSON([
			'ok' => false,
			'error' => $message,
		]);
	}
}
