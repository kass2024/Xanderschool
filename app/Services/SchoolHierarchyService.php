<?php

namespace App\Services;

use App\Models\SchoolHierarchyModel;

/**
 * Master / child school groups for cross-school dashboard and budget control.
 */
class SchoolHierarchyService
{
	/** Default central posts (used until configured in Level clearance). */
	const DEFAULT_CENTRAL_POSTS = [1, 3, 18, 21, 24, 25, 29, 30]; // + Director / Deputy Director (same tier as Head master)

	/** @deprecated Use MasterCentralPostModel::centralPostIds() */
	const CENTRAL_POSTS = self::DEFAULT_CENTRAL_POSTS;

	const WISDOM_RWANDA_NAMES = ['WISDOM SCHOOL RWANDA', 'Wisdom School Rwanda'];
	const WISDOM_CHILD_DEFAULT_PASSWORD = 'Wisdom@2026';
	const WISDOM_LOGIN_URL = 'https://schoolmis.xanderglobalacademy.com/login';

	public function ensureSchema()
	{
		$mdl = new SchoolHierarchyModel();
		$mdl->ensureSchema();
	}

	public function isMasterSchool($schoolId)
	{
		$this->ensureSchema();
		$row = \Config\Database::connect()->table('schools')->where('id', (int) $schoolId)->get(1)->getRowArray();
		return is_array($row) && !empty($row['is_master']);
	}

	public function isChildSchool($schoolId)
	{
		$this->ensureSchema();
		$row = \Config\Database::connect()->table('schools')->where('id', (int) $schoolId)->get(1)->getRowArray();
		return is_array($row) && (int) ($row['master_school_id'] ?? 0) > 0;
	}

	public function masterSchoolId($schoolId)
	{
		$mdl = new SchoolHierarchyModel();
		$master = $mdl->findMasterSchool((int) $schoolId);
		return $master ? (int) $master['id'] : 0;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function childSchools($masterId)
	{
		$mdl = new SchoolHierarchyModel();
		return $mdl->childSchools((int) $masterId);
	}

	/**
	 * Schools the user may view (master + children when central post at master).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function accessibleSchools($staffSchoolId, $postId)
	{
		$this->ensureSchema();
		$staffSchoolId = (int) $staffSchoolId;
		$postId = (int) $postId;
		$db = \Config\Database::connect();

		$home = $db->table('schools')->where('id', $staffSchoolId)->get(1)->getRowArray();
		if (!$home) {
			return [];
		}

		$masterId = !empty($home['is_master']) ? $staffSchoolId : (int) ($home['master_school_id'] ?? 0);
		if ($masterId < 1 && $this->isCentralPost($postId) && !empty($home['is_master'])) {
			$masterId = $staffSchoolId;
		}

		if ($masterId < 1 || !$this->canAccessChildSchools($staffSchoolId, $postId)) {
			return [$home];
		}

		$master = $db->table('schools')->where('id', $masterId)->get(1)->getRowArray();
		$children = $this->childSchools($masterId);
		$list = [];
		if ($master) {
			$list[] = $master;
		}
		foreach ($children as $c) {
			$list[] = $c;
		}
		return $list;
	}

	public function canAccessChildSchools($staffSchoolId, $postId)
	{
		if (!$this->isCentralPost((int) $postId)) {
			return false;
		}
		$this->ensureSchema();
		$db = \Config\Database::connect();
		$row = $db->table('schools')->where('id', (int) $staffSchoolId)->get(1)->getRowArray();
		if (!$row) {
			return false;
		}
		return !empty($row['is_master']);
	}

	public function canViewSchool($staffSchoolId, $postId, $targetSchoolId)
	{
		$targetSchoolId = (int) $targetSchoolId;
		foreach ($this->accessibleSchools((int) $staffSchoolId, (int) $postId) as $s) {
			if ((int) ($s['id'] ?? 0) === $targetSchoolId) {
				return true;
			}
		}
		return false;
	}

	public function isCentralPost($postId)
	{
		try {
			return (new \App\Models\MasterCentralPostModel())->isCentralPost((int) $postId);
		} catch (\Throwable $e) {
			return in_array((int) $postId, self::DEFAULT_CENTRAL_POSTS, true);
		}
	}

	/** Only master (or non-child) schools may define budget line structure. */
	public function canManageBudgetLineStructure($schoolId)
	{
		return !$this->isChildSchool((int) $schoolId);
	}

	/** Child schools fill quantities and amounts on master-prepared lines (totals differ per branch). */
	public function isBudgetBranchFillSchool($schoolId)
	{
		return $this->isChildSchool((int) $schoolId);
	}

	/** @deprecated Use isBudgetBranchFillSchool() */
	public function isBudgetQuantityOnlySchool($schoolId)
	{
		return $this->isBudgetBranchFillSchool($schoolId);
	}

	/**
	 * Seed WISDOM SCHOOL RWANDA as master for all Wisdom-named schools.
	 *
	 * @return array
	 */
	public function seedWisdomMasterGroup()
	{
		$this->ensureSchema();
		$db = \Config\Database::connect();
		$master = null;
		foreach (self::WISDOM_RWANDA_NAMES as $name) {
			$master = $db->table('schools')->where('name', $name)->get(1)->getRowArray();
			if ($master) {
				break;
			}
		}
		if (!$master) {
			$master = $db->table('schools')->like('name', 'WISDOM SCHOOL RWANDA', 'both')->get(1)->getRowArray();
		}
		if (!$master) {
			return ['master_id' => 0, 'children' => 0, 'names' => []];
		}

		$masterId = (int) $master['id'];
		$mdl = new SchoolHierarchyModel();
		$mdl->setMaster($masterId, true);

		$wisdomSchools = $db->table('schools')
			->groupStart()
				->like('name', 'Wisdom', 'both')
				->orLike('name', 'WISDOM', 'both')
				->orLike('acronym', 'WIS-', 'after')
			->groupEnd()
			->where('id !=', $masterId)
			->get()->getResultArray();

		$childIds = array_map(static function ($r) {
			return (int) $r['id'];
		}, $wisdomSchools);
		$mdl->assignChildren($masterId, $childIds);

		return [
			'master_id' => $masterId,
			'children' => count($childIds),
			'names' => array_column($wisdomSchools, 'name'),
		];
	}

	/**
	 * Create/reset Head master login for every Wisdom child school.
	 * Never creates or changes a login for WISDOM SCHOOL RWANDA (master).
	 *
	 * @return array
	 */
	public function resetWisdomChildDefaultPasswords($defaultPassword = self::WISDOM_CHILD_DEFAULT_PASSWORD, $loginUrl = self::WISDOM_LOGIN_URL)
	{
		$this->ensureSchema();
		$seeded = $this->seedWisdomMasterGroup();
		$masterId = (int) ($seeded['master_id'] ?? 0);
		if ($masterId < 1) {
			return [
				'master_id' => 0,
				'password' => (string) $defaultPassword,
				'login_url' => (string) $loginUrl,
				'file' => '',
				'created' => [],
				'updated' => [],
				'skipped' => [['school' => 'WISDOM SCHOOL RWANDA', 'reason' => 'master school not found']],
			];
		}

		$db = \Config\Database::connect();
		$hash = password_hash((string) $defaultPassword, PASSWORD_DEFAULT);
		$now = date('Y-m-d H:i:s');
		$created = [];
		$updated = [];
		$skipped = [];

		foreach ($this->childSchools($masterId) as $school) {
			$sid = (int) ($school['id'] ?? 0);
			$name = trim((string) ($school['name'] ?? ''));
			if ($sid < 1 || $this->isWisdomMasterSchoolRow($school, $masterId)) {
				$skipped[] = ['school' => $name !== '' ? $name : ('#' . $sid), 'reason' => 'master school - login not created'];
				continue;
			}

			$staff = $db->table('staffs')
				->where('school_id', $sid)
				->where('post', 1)
				->orderBy('id', 'ASC')
				->get(1)->getRowArray();

			if ($staff) {
				$db->table('staffs')->where('id', (int) $staff['id'])->update([
					'password' => $hash,
					'status' => 1,
					'reset_exp' => 0,
					'updated_at' => $now,
				]);
				$updated[] = [
					'school' => $name,
					'staff_id' => (int) $staff['id'],
					'email' => (string) ($staff['email'] ?? ''),
				];
				continue;
			}

			$email = trim((string) ($school['email'] ?? ''));
			if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
				$acronym = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '', (string) ($school['acronym'] ?? 'child')));
				$email = 'admin.' . ($acronym !== '' ? $acronym : ('s' . $sid)) . '@wisdomschools.rw';
			}

			$existingEmail = $db->table('staffs')->where('email', $email)->get(1)->getRowArray();
			if ($existingEmail && (int) ($existingEmail['school_id'] ?? 0) !== $sid) {
				$email = 'admin.s' . $sid . '@wisdomschools.rw';
			}

			$headMaster = trim((string) ($school['head_master'] ?? ''));
			if ($headMaster === '') {
				$headMaster = 'Head Teacher';
			}
			$parts = preg_split('/\s+/', $headMaster, 2) ?: [];
			$fname = trim((string) ($parts[0] ?? 'Head'));
			$lname = trim((string) ($parts[1] ?? 'Teacher'));
			$phone = trim((string) ($school['phone'] ?? ''));

			$db->table('staffs')->insert([
				'school_id' => $sid,
				'fname' => $fname !== '' ? $fname : 'Head',
				'lname' => $lname !== '' ? $lname : 'Teacher',
				'phone' => $phone,
				'password' => $hash,
				'status' => 1,
				'last_login' => 0,
				'email' => $email,
				'post' => 1,
				'shift_id' => 0,
				'country' => (string) ($school['country'] ?? 'Rwanda'),
				'city' => '',
				'address' => (string) ($school['address'] ?? ''),
				'photo' => '',
				'lang' => 'en',
				'next_login' => 0,
				'reset_exp' => 0,
				'created_at' => $now,
				'created_by' => 1,
				'updated_at' => $now,
				'updated_by' => 1,
				'updateVersion' => 1,
			]);
			$created[] = [
				'school' => $name,
				'staff_id' => (int) $db->insertID(),
				'email' => $email,
			];
		}

		$file = $this->exportChildCredentialsTxt($masterId, (string) $defaultPassword, (string) $loginUrl);

		return [
			'master_id' => $masterId,
			'password' => (string) $defaultPassword,
			'login_url' => (string) $loginUrl,
			'file' => $file,
			'created' => $created,
			'updated' => $updated,
			'skipped' => $skipped,
		];
	}

	/**
	 * Export child school admin credentials to a text file (master school excluded).
	 *
	 * @return string Absolute path to generated file
	 */
	public function exportChildCredentialsTxt($masterId, $defaultPassword = self::WISDOM_CHILD_DEFAULT_PASSWORD, $loginUrl = self::WISDOM_LOGIN_URL)
	{
		$this->ensureSchema();
		$db = \Config\Database::connect();
		$master = $db->table('schools')->where('id', (int) $masterId)->get(1)->getRowArray();
		$children = $this->childSchools((int) $masterId);
		$loginUrl = rtrim((string) $loginUrl, '/');

		$lines = [];
		$lines[] = 'WISDOM SCHOOLS - CHILD SCHOOL LOGIN CREDENTIALS';
		$lines[] = 'Generated: ' . date('Y-m-d H:i:s');
		$lines[] = 'Master school (NO login created/reset): ' . ($master['name'] ?? ('#' . $masterId));
		$lines[] = 'Login link: ' . $loginUrl;
		$lines[] = 'Default password for all child schools: ' . $defaultPassword;
		$lines[] = str_repeat('=', 72);
		$lines[] = '';

		foreach ($children as $school) {
			if ($this->isWisdomMasterSchoolRow($school, (int) $masterId)) {
				continue;
			}
			$sid = (int) $school['id'];
			$staff = $db->table('staffs')
				->where('school_id', $sid)
				->where('post', 1)
				->orderBy('id', 'ASC')
				->get()->getResultArray();

			$lines[] = 'School: ' . ($school['name'] ?? '');
			$lines[] = 'Acronym: ' . ($school['acronym'] ?? '');
			$lines[] = 'School ID: ' . $sid;
			$head = $staff[0] ?? [];
			$lines[] = 'Login link: ' . $loginUrl;
			$lines[] = 'Username / email: ' . ((string) ($head['email'] ?? '') !== '' ? $head['email'] : ($school['email'] ?? ''));
			$lines[] = 'Password: ' . $defaultPassword;
			$lines[] = 'Phone: ' . ((string) ($head['phone'] ?? '') !== '' ? $head['phone'] : ($school['phone'] ?? ''));

			if (!$staff) {
				$lines[] = '  (no Head master account found)';
			}
			foreach ($staff as $st) {
				$post = $db->table('posts')->where('id', (int) ($st['post'] ?? 0))->get(1)->getRowArray();
				$postTitle = $post['title'] ?? ('Post #' . ($st['post'] ?? ''));
				$lines[] = '  Staff: ' . trim(($st['fname'] ?? '') . ' ' . ($st['lname'] ?? ''));
				$lines[] = '    Post: ' . $postTitle;
				$lines[] = '    Username / email: ' . ($st['email'] ?? '');
				$lines[] = '    Password: ' . $defaultPassword;
				$lines[] = '    Phone: ' . ($st['phone'] ?? '');
				$lines[] = '    Login link: ' . $loginUrl;
			}
			$lines[] = str_repeat('-', 72);
		}

		$dir = WRITEPATH . 'exports';
		if (!is_dir($dir)) {
			mkdir($dir, 0755, true);
		}
		$file = $dir . DIRECTORY_SEPARATOR . 'wisdom_child_schools_credentials_' . date('Ymd_His') . '.txt';
		file_put_contents($file, implode("\n", $lines) . "\n");

		return $file;
	}

	private function isWisdomMasterSchoolRow($school, $masterId)
	{
		if (!is_array($school)) {
			return false;
		}
		if ((int) ($school['id'] ?? 0) === $masterId) {
			return true;
		}
		if (!empty($school['is_master'])) {
			return true;
		}
		$name = strtoupper(trim((string) ($school['name'] ?? '')));
		return $name === 'WISDOM SCHOOL RWANDA';
	}
}
