<?php

namespace App\Services\Budget;

use Config\MenuClearance;

class BudgetPermissionService
{
	public function can($staffId, $postId, $permKey)
	{
		$permKey = trim((string) $permKey);
		if ($permKey === '') {
			return false;
		}
		$db = \Config\Database::connect();
		$row = $db->table('post_budget_permissions')
			->where('post_id', (int) $postId)
			->where('perm_key', $permKey)
			->countAllResults();
		if ($row <= 0) {
			return false;
		}

		$schoolId = (int) session('soma_school_id');
		$postId = (int) $postId;

		// Leaders: never prepare/edit/submit budgets (master or child)
		$prepareKeys = [
			'budget.prepare', 'budget.edit_own', 'budget.submit', 'budget.periods.manage',
			'budget.templates.upload', 'budget.templates.activate', 'budget.adjust', 'budget.transfer',
			'budget.edit_submitted',
		];
		if (in_array($postId, MenuClearance::CHILD_BUDGET_VIEW_POSTS, true)
			&& !in_array($postId, MenuClearance::CHILD_BUDGET_PREPARE_POSTS, true)) {
			$viewOk = ['budget.view_reports', 'budget.export', 'cash_request.view_audit'];
			if (in_array($permKey, $prepareKeys, true)) {
				return false;
			}
			if (strpos($permKey, 'budget.') === 0 || strpos($permKey, 'cash_request.') === 0) {
				// On child: strict view-only. On master: allow headteacher cash approve for HM/headmistress only
				if ($schoolId > 0 && MenuClearance::isChildSchoolId($schoolId)) {
					return in_array($permKey, $viewOk, true);
				}
				if (in_array($permKey, $prepareKeys, true)) {
					return false;
				}
			}
		}

		if ($schoolId > 0 && MenuClearance::isChildSchoolId($schoolId)) {
			if (in_array($permKey, $prepareKeys, true)
				&& !in_array($postId, MenuClearance::CHILD_BUDGET_PREPARE_POSTS, true)) {
				return false;
			}
		}

		return true;
	}

	public function denyRedirect($permKey)
	{
		$staffId = (int) session('soma_id');
		$postId = (int) session('soma_post');
		if (!$this->can($staffId, $postId, $permKey)) {
			session()->setFlashdata('error', 'You do not have permission for this action. Contact your administrator to enable Budget & Cash Flow for your role.');
			header('Location: ' . base_url('budget/dashboard'));
			exit;
		}
	}

	public function branchIdsForStaff($staffId, $postId, $schoolId = null)
	{
		$schoolId = $schoolId ?: (int) session('soma_school_id');
		$ctx = new BranchContextService();
		return $ctx->accessibleBranchIds($staffId, $postId, $schoolId);
	}

	public function primaryBranchId($staffId, $schoolId)
	{
		$db = \Config\Database::connect();
		$row = $db->query(
			"SELECT sba.branch_id FROM staff_branch_assignments sba
			INNER JOIN branches b ON b.id = sba.branch_id
			WHERE sba.staff_id = ? AND b.school_id = ?
			ORDER BY sba.is_primary DESC LIMIT 1",
			[(int) $staffId, (int) $schoolId]
		)->getRowArray();
		if ($row) {
			return (int) $row['branch_id'];
		}
		$branch = $db->table('branches')->where('school_id', (int) $schoolId)->get(1)->getRowArray();
		return $branch ? (int) $branch['id'] : 0;
	}

	public function canAccessBranch($staffId, $postId, $schoolId, $branchId)
	{
		return (new BranchContextService())->assertBranchAccess($staffId, $postId, $schoolId, $branchId);
	}
}
