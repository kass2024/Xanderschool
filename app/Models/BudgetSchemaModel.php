<?php

namespace App\Models;

use App\Services\Budget\BranchContextService;
use CodeIgniter\Model;

class BudgetSchemaModel extends Model
{
	protected $table = 'budget_settings';
	protected $primaryKey = 'id';
	protected $returnType = 'array';

	private static $ready = false;

	public function ensureSchema()
	{
		if (self::$ready) {
			return;
		}
		$sqlFile = ROOTPATH . 'deploy/add_budget_cashflow.sql';
		if (!is_file($sqlFile)) {
			return;
		}
		$db = \Config\Database::connect();
		$sql = file_get_contents($sqlFile);
		$statements = array_filter(array_map('trim', preg_split('/;\s*\n/', $sql)));
		foreach ($statements as $stmt) {
			if ($stmt === '' || stripos($stmt, 'CREATE TABLE') === false) {
				continue;
			}
			try {
				$db->query($stmt);
			} catch (\Throwable $e) {
				// Table may already exist
			}
		}
		self::$ready = true;
	}

	public function seedFoundation($schoolId, $staffId = 0)
	{
		$this->ensureSchema();
		$schoolId = (int) $schoolId;
		if ($schoolId <= 0) {
			return;
		}
		$db = \Config\Database::connect();
		$ctx = new BranchContextService();

		$this->seedPermissionsCatalog($db);
		$this->seedFinancePosts($db);
		$this->seedPostPermissions($db);

		$school = $db->table('schools')->where('id', $schoolId)->get(1)->getRowArray();
		if (!$school) {
			return;
		}

		if ($ctx->isWisdomSchool($school)) {
			$this->ensureWisdomSchoolBranch($db, $ctx, $school, $schoolId, $staffId);
		} else {
			$this->ensureStandaloneSchoolBranch($db, $ctx, $school, $schoolId, $staffId);
		}
	}

	private function seedPermissionsCatalog($db)
	{
		$labels = \Config\BudgetPermissions::labels();
		foreach ($labels as $key => $label) {
			if ($db->table('budget_permissions')->where('perm_key', $key)->countAllResults()) {
				continue;
			}
			$db->table('budget_permissions')->insert([
				'perm_key' => $key,
				'label' => $label,
				'group_name' => strpos($key, 'cash_') === 0 ? 'cash' : 'budget',
			]);
		}
	}

	private function seedFinancePosts($db)
	{
		$newPosts = [
			19 => 'Budget Manager',
			20 => 'Procurement Manager',
			21 => 'Deputy Director of Finance',
			22 => 'Finance Officer',
			23 => 'Internal Auditor',
			24 => 'Director of Finance',
		];
		foreach ($newPosts as $id => $title) {
			$row = $db->table('posts')->where('id', $id)->get(1)->getRowArray();
			if ($row) {
				$db->table('posts')->where('id', $id)->update(['title' => $title, 'status' => 1]);
				continue;
			}
			$db->table('posts')->insert(['id' => $id, 'title' => $title, 'status' => 1]);
		}
	}

	/** Ensure finance posts + budget permissions exist (safe to run anytime). */
	public function seedFinanceRoles()
	{
		$this->ensureSchema();
		$db = \Config\Database::connect();
		$this->seedPermissionsCatalog($db);
		$this->seedFinancePosts($db);
		$this->seedPostPermissions($db);
	}

	private function seedPostPermissions($db)
	{
		for ($pid = 1; $pid <= 24; $pid++) {
			foreach (\Config\BudgetPermissions::defaultForPost($pid) as $perm) {
				if ($db->table('post_budget_permissions')->where('post_id', $pid)->where('perm_key', $perm)->countAllResults()) {
					continue;
				}
				$db->table('post_budget_permissions')->insert(['post_id' => $pid, 'perm_key' => $perm]);
			}
		}
	}

	/** Wisdom Schools Ltd — 15 branches; each linked school behaves as standalone branch. */
	private function ensureWisdomSchoolBranch($db, BranchContextService $ctx, array $school, $schoolId, $staffId)
	{
		$org = $db->table('organizations')->where('slug', BranchContextService::WISDOM_ORG_SLUG)->get(1)->getRowArray();
		if (!$org) {
			$db->table('organizations')->insert([
				'name' => 'Wisdom Schools Ltd',
				'slug' => BranchContextService::WISDOM_ORG_SLUG,
				'status' => 1,
				'created_at' => date('Y-m-d H:i:s'),
				'updated_at' => date('Y-m-d H:i:s'),
			]);
			$orgId = (int) $db->insertID();
		} else {
			$orgId = (int) $org['id'];
		}

		$this->seedWisdomBranchCatalog($db, $orgId);

		$branch = $db->table('branches')->where('school_id', $schoolId)->get(1)->getRowArray();
		if (!$branch) {
			$matched = $ctx->matchWisdomBranchForSchool($orgId, $school);
			if ($matched) {
				$db->table('branches')->where('id', (int) $matched['id'])->update([
					'school_id' => $schoolId,
					'updated_at' => date('Y-m-d H:i:s'),
				]);
				$branchId = (int) $matched['id'];
			} else {
				$locName = preg_replace('/^wisdom\s+/i', '', trim($school['name']));
				$code = strtoupper(preg_replace('/[^A-Z0-9]/', '', substr($school['acronym'] ?? $locName, 0, 6)));
				if ($code === '') {
					$code = 'W' . $schoolId;
				}
				$db->table('branches')->insert([
					'organization_id' => $orgId,
					'school_id' => $schoolId,
					'branch_code' => $code,
					'name' => $locName ?: $school['name'],
					'status' => 1,
					'created_at' => date('Y-m-d H:i:s'),
					'updated_at' => date('Y-m-d H:i:s'),
				]);
				$branchId = (int) $db->insertID();
			}
		} else {
			$branchId = (int) $branch['id'];
		}

		$this->assignStaffToBranch($db, $staffId, $branchId);
		$this->ensureOrgSettings($db, $orgId, $staffId);
		$this->ensureDefaultBudgetPeriod($db, $orgId, $branchId, $staffId);
	}

	/** Non-Wisdom schools: own org + one branch = standalone school. Never touches Wisdom org. */
	private function ensureStandaloneSchoolBranch($db, BranchContextService $ctx, array $school, $schoolId, $staffId)
	{
		$slug = $ctx->standaloneOrgSlug($schoolId);
		$org = $db->table('organizations')->where('slug', $slug)->get(1)->getRowArray();
		if (!$org) {
			$db->table('organizations')->insert([
				'name' => $school['name'],
				'slug' => $slug,
				'status' => 1,
				'created_at' => date('Y-m-d H:i:s'),
				'updated_at' => date('Y-m-d H:i:s'),
			]);
			$orgId = (int) $db->insertID();
		} else {
			$orgId = (int) $org['id'];
		}

		$branch = $db->table('branches')->where('school_id', $schoolId)->get(1)->getRowArray();
		if (!$branch) {
			$code = strtoupper(preg_replace('/[^A-Z0-9]/', '', substr($school['acronym'] ?? 'SCH', 0, 6)));
			if ($code === '') {
				$code = 'S' . $schoolId;
			}
			$db->table('branches')->insert([
				'organization_id' => $orgId,
				'school_id' => $schoolId,
				'branch_code' => $code,
				'name' => $school['name'],
				'status' => 1,
				'created_at' => date('Y-m-d H:i:s'),
				'updated_at' => date('Y-m-d H:i:s'),
			]);
			$branchId = (int) $db->insertID();
		} else {
			$branchId = (int) $branch['id'];
		}

		$this->assignStaffToBranch($db, $staffId, $branchId);
		$this->ensureOrgSettings($db, $orgId, $staffId);
		$this->ensureDefaultBudgetPeriod($db, $orgId, $branchId, $staffId);
	}

	private function assignStaffToBranch($db, $staffId, $branchId)
	{
		if ($staffId <= 0 || $branchId <= 0) {
			return;
		}
		if ($db->table('staff_branch_assignments')->where('staff_id', $staffId)->where('branch_id', $branchId)->countAllResults()) {
			return;
		}
		$db->table('staff_branch_assignments')->insert([
			'staff_id' => $staffId,
			'branch_id' => $branchId,
			'is_primary' => 1,
			'can_cross_branch' => 0,
			'created_at' => date('Y-m-d H:i:s'),
		]);
	}

	private function ensureOrgSettings($db, $orgId, $staffId)
	{
		if ($db->table('budget_settings')->where('organization_id', $orgId)->where('branch_id', null)->countAllResults()) {
			return;
		}
		$db->table('budget_settings')->insert([
			'organization_id' => $orgId,
			'branch_id' => null,
			'default_currency' => 'RWF',
			'headteacher_approval_mode' => 'evidence',
			'ai_enabled' => 0,
			'budget_utilization_alert_pct' => 80,
			'created_by' => $staffId ?: null,
			'created_at' => date('Y-m-d H:i:s'),
			'updated_at' => date('Y-m-d H:i:s'),
		]);
	}

	/** Open annual period so headmasters can start budget prep immediately. */
	private function ensureDefaultBudgetPeriod($db, $orgId, $branchId, $staffId)
	{
		if ($branchId <= 0) {
			return;
		}
		$open = $db->table('budget_periods')->where('branch_id', $branchId)->where('status', 'open')->countAllResults();
		if ($open > 0) {
			return;
		}
		$year = (int) date('Y');
		$title = $year . '-' . ($year + 1) . ' Annual Budget';
		$exists = $db->table('budget_periods')->where('branch_id', $branchId)->where('title', $title)->countAllResults();
		if ($exists) {
			$db->table('budget_periods')->where('branch_id', $branchId)->where('title', $title)->update([
				'status' => 'open',
				'updated_at' => date('Y-m-d H:i:s'),
			]);
			return;
		}
		$db->table('budget_periods')->insert([
			'organization_id' => (int) $orgId,
			'branch_id' => (int) $branchId,
			'title' => $title,
			'period_type' => 'annual',
			'start_date' => $year . '-01-01',
			'end_date' => $year . '-12-31',
			'status' => 'open',
			'created_by' => $staffId ?: null,
			'created_at' => date('Y-m-d H:i:s'),
			'updated_at' => date('Y-m-d H:i:s'),
		]);
	}

	private function seedWisdomBranchCatalog($db, $orgId)
	{
		$branches = [
			['MUS', 'Musanze'], ['NYB', 'Nyabihu'], ['RUB', 'Rubavu'], ['RUN', 'Runda'],
			['NYM', 'Nyamasheke'], ['RBE', 'Rubengera'], ['FUM', 'Fumbwe'], ['KAY', 'Kayonza'],
			['KIR', 'Kiramuruzi'], ['MUY', 'Muyumbu'], ['SUS', 'Susa'], ['KAN', 'Kanzenze'],
			['BUR', 'Burera'], ['NGO', 'Ngororero'], ['KAB', 'Kabarore'],
		];
		foreach ($branches as $b) {
			if ($db->table('branches')->where('organization_id', $orgId)->where('branch_code', $b[0])->countAllResults()) {
				continue;
			}
			$db->table('branches')->insert([
				'organization_id' => $orgId,
				'school_id' => null,
				'branch_code' => $b[0],
				'name' => $b[1],
				'status' => 1,
				'created_at' => date('Y-m-d H:i:s'),
				'updated_at' => date('Y-m-d H:i:s'),
			]);
		}
	}
}
