<?php

namespace App\Services;

use App\Models\SchoolHierarchyModel;

/**
 * Master / child school groups for cross-school dashboard and budget control.
 */
class SchoolHierarchyService
{
	/** Default central posts (used until configured in Level clearance). */
	const DEFAULT_CENTRAL_POSTS = [1, 3, 18, 21, 24, 25, 29]; // + Director (same tier as Head master)

	/** @deprecated Use MasterCentralPostModel::centralPostIds() */
	const CENTRAL_POSTS = self::DEFAULT_CENTRAL_POSTS;

	const WISDOM_RWANDA_NAMES = ['WISDOM SCHOOL RWANDA', 'Wisdom School Rwanda'];

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
	 * @return array{master_id:int, children:int, names:string[]}
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
	 * Export child school admin credentials to a text file.
	 *
	 * @return string Absolute path to generated file
	 */
	public function exportChildCredentialsTxt($masterId, $defaultPassword = 'Wisdom@2026')
	{
		$this->ensureSchema();
		$db = \Config\Database::connect();
		$master = $db->table('schools')->where('id', (int) $masterId)->get(1)->getRowArray();
		$children = $this->childSchools((int) $masterId);

		$lines = [];
		$lines[] = 'WISDOM SCHOOLS — CHILD SCHOOL CREDENTIALS';
		$lines[] = 'Generated: ' . date('Y-m-d H:i:s');
		$lines[] = 'Master school: ' . ($master['name'] ?? ('#' . $masterId));
		$lines[] = str_repeat('=', 72);
		$lines[] = '';

		foreach ($children as $school) {
			$sid = (int) $school['id'];
			$lines[] = 'School: ' . ($school['name'] ?? '');
			$lines[] = 'Acronym: ' . ($school['acronym'] ?? '');
			$lines[] = 'School ID: ' . $sid;
			$lines[] = 'School email: ' . ($school['email'] ?? '');

			$staff = $db->table('staffs')
				->where('school_id', $sid)
				->where('status', 1)
				->orderBy('post', 'ASC')
				->orderBy('id', 'ASC')
				->get()->getResultArray();

			if (!$staff) {
				$lines[] = '  (no active staff found)';
			}
			foreach ($staff as $st) {
				$post = $db->table('posts')->where('id', (int) ($st['post'] ?? 0))->get(1)->getRowArray();
				$postTitle = $post['title'] ?? ('Post #' . ($st['post'] ?? ''));
				$lines[] = '  Staff: ' . trim(($st['fname'] ?? '') . ' ' . ($st['lname'] ?? ''));
				$lines[] = '    Post: ' . $postTitle;
				$lines[] = '    Login: ' . ($st['email'] ?? $st['phone'] ?? '(see phone)');
				$lines[] = '    Phone: ' . ($st['phone'] ?? '');
				if ((int) ($st['post'] ?? 0) === 1) {
					$lines[] = '    Default password (if seeded): ' . $defaultPassword;
				}
			}
			$lines[] = str_repeat('-', 72);
		}

		$dir = WRITEPATH . 'exports';
		if (!is_dir($dir)) {
			mkdir($dir, 0755, true);
		}
		$file = $dir . DIRECTORY_SEPARATOR . 'wisdom_child_schools_credentials_' . date('Ymd_His') . '.txt';
		file_put_contents($file, implode("\n", $lines));

		return $file;
	}
}
