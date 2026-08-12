<?php

namespace App\Services\Budget;

use App\Services\SchoolHierarchyService;

/**
 * Branch = standalone school scope. Wisdom org is opt-in only (school name contains "wisdom").
 */
class BranchContextService
{
	public const WISDOM_ORG_SLUG = 'wisdom-schools';
	public const WISDOM_NAME_PREFIX = 'Wisdom ';

	public function isWisdomSchool($schoolRow)
	{
		if (!$schoolRow || !is_array($schoolRow)) {
			return false;
		}
		$haystack = strtolower(trim(
			($schoolRow['name'] ?? '') . ' ' . ($schoolRow['acronym'] ?? '')
		));
		return $haystack !== '' && strpos($haystack, 'wisdom') !== false;
	}

	public function isWisdomSchoolId($schoolId)
	{
		$db = \Config\Database::connect();
		$school = $db->table('schools')->where('id', (int) $schoolId)->get(1)->getRowArray();
		return $this->isWisdomSchool($school);
	}

	public function wisdomOrgId()
	{
		$row = \Config\Database::connect()->table('organizations')
			->where('slug', self::WISDOM_ORG_SLUG)->get(1)->getRowArray();
		return $row ? (int) $row['id'] : 0;
	}

	/** Display name: "Wisdom Musanze" on central dashboard; branch users see plain school/branch name. */
	public function displayBranchName(array $branch, $centralView = false)
	{
		$name = trim((string) ($branch['name'] ?? ''));
		if ($name === '') {
			return '—';
		}
		$orgId = (int) ($branch['organization_id'] ?? 0);
		if ($centralView && $orgId > 0 && $orgId === $this->wisdomOrgId()) {
			if (stripos($name, 'wisdom') !== 0) {
				return self::WISDOM_NAME_PREFIX . $name;
			}
		}
		return $name;
	}

	public function displaySchoolBranchLabel($schoolId, $branch, $centralView = false)
	{
		if (!empty($branch['school_id']) && (int) $branch['school_id'] === (int) $schoolId) {
			$db = \Config\Database::connect();
			$school = $db->table('schools')->where('id', (int) $schoolId)->get(1)->getRowArray();
			if ($school && !$centralView) {
				return (string) $school['name'];
			}
		}
		return $this->displayBranchName($branch, $centralView);
	}

	protected function homeSchoolId($schoolId)
	{
		if (function_exists('school_hierarchy_home_id')) {
			return school_hierarchy_home_id();
		}
		return (int) $schoolId;
	}

	/**
	 * Cross-branch Budget Dashboard: only Principal, Budget Manager, Director of Finance
	 * when logged in at the master school. Head master / Headmistress / Deans see own school only.
	 */
	public function hasCentralDashboard($staffId, $postId, $schoolId)
	{
		if (!\Config\MenuClearance::canSeeCrossBranchBudgetDashboard($postId)) {
			return false;
		}
		$homeId = $this->homeSchoolId($schoolId);
		$hierarchy = new SchoolHierarchyService();
		return $hierarchy->isMasterSchool($homeId);
	}

	/** Branch IDs this user may access (always scoped to their org). */
	public function accessibleBranchIds($staffId, $postId, $schoolId)
	{
		$db = \Config\Database::connect();
		$branchId = (new BudgetPermissionService())->primaryBranchId($staffId, $schoolId);
		$branch = $branchId ? $db->table('branches')->where('id', $branchId)->get(1)->getRowArray() : null;
		if (!$branch) {
			return [];
		}
		$orgId = (int) $branch['organization_id'];

		if ($this->hasCentralDashboard($staffId, $postId, $schoolId)) {
			$hierarchy = new SchoolHierarchyService();
			$homeId = $this->homeSchoolId($schoolId);
			if ($hierarchy->isMasterSchool($homeId)) {
				$childIds = array_column($hierarchy->childSchools($homeId), 'id');
				$schoolIds = array_values(array_unique(array_merge([$homeId], $childIds)));
				return $db->table('branches')->select('id')
					->where('organization_id', $orgId)
					->whereIn('school_id', $schoolIds)
					->where('status', 1)
					->get()->getResultArray();
			}
			return $db->table('branches')->select('id')
				->where('organization_id', $orgId)->where('status', 1)
				->get()->getResultArray();
		}

		return [['id' => (int) $branch['id']]];
	}

	public function accessibleBranches($staffId, $postId, $schoolId, $centralView = false)
	{
		$db = \Config\Database::connect();
		$ids = array_column($this->accessibleBranchIds($staffId, $postId, $schoolId), 'id');
		if (empty($ids)) {
			return [];
		}
		$rows = $db->table('branches')->whereIn('id', $ids)->where('status', 1)
			->orderBy('name')->get()->getResultArray();
		foreach ($rows as &$r) {
			$r['display_name'] = $this->displayBranchName($r, $centralView);
		}
		return $rows;
	}

	public function assertBranchAccess($staffId, $postId, $schoolId, $targetBranchId)
	{
		$allowed = array_map('intval', array_column(
			$this->accessibleBranchIds($staffId, $postId, $schoolId),
			'id'
		));
		return in_array((int) $targetBranchId, $allowed, true);
	}

	/** Match Wisdom branch by school name (e.g. "Wisdom Musanze" → Musanze). */
	public function matchWisdomBranchForSchool($orgId, $schoolRow)
	{
		$db = \Config\Database::connect();
		$schoolName = strtolower(preg_replace('/^wisdom\s+/i', '', trim($schoolRow['name'] ?? '')));
		$branches = $db->table('branches')->where('organization_id', (int) $orgId)->where('status', 1)->get()->getResultArray();
		foreach ($branches as $b) {
			if (strtolower($b['name']) === $schoolName) {
				return $b;
			}
		}
		foreach ($branches as $b) {
			if ($schoolName !== '' && strpos($schoolName, strtolower($b['name'])) !== false) {
				return $b;
			}
		}
		return null;
	}

	public function standaloneOrgSlug($schoolId)
	{
		return 'school-' . (int) $schoolId;
	}
}
