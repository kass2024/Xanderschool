<?php

namespace App\Services\Budget;

/**
 * In-app + contact helpers for budget workflow alerts.
 * SMS/email are sent by the controller using BaseController methods.
 */
class BudgetNotificationService
{
	/** Approver posts: Procurement, Budget Manager, Director of Finance */
	public const APPROVER_POSTS = [20, 19, 24];

	public function notifyStaff($staffId, $title, $body, $linkUrl = null, $branchId = null)
	{
		$db = \Config\Database::connect();
		$db->table('budget_notifications')->insert([
			'staff_id' => (int) $staffId,
			'branch_id' => $branchId ? (int) $branchId : null,
			'title' => $title,
			'body' => $body,
			'link_url' => $linkUrl,
			'is_read' => 0,
			'created_at' => date('Y-m-d H:i:s'),
		]);
	}

	public function notifyPost($postId, $title, $body, $linkUrl = null, $branchId = null)
	{
		foreach ($this->activeStaffByPost((int) $postId) as $s) {
			$this->notifyStaff((int) $s['id'], $title, $body, $linkUrl, $branchId);
		}
	}

	public function unreadCount($staffId)
	{
		return (int) \Config\Database::connect()->table('budget_notifications')
			->where('staff_id', (int) $staffId)->where('is_read', 0)->countAllResults();
	}

	/**
	 * Active staff for a post (status 1 or 2 = usable login accounts).
	 * @return array<int, array>
	 */
	public function activeStaffByPost(int $postId, ?int $schoolId = null): array
	{
		$db = \Config\Database::connect();
		$q = $db->table('staffs')
			->select('id, fname, lname, email, phone, school_id, post')
			->where('post', $postId)
			->whereIn('status', [1, 2]);
		$rows = $q->get()->getResultArray();
		if ($schoolId && $schoolId > 0 && $rows) {
			$same = array_values(array_filter($rows, static function ($s) use ($schoolId) {
				return (int) ($s['school_id'] ?? 0) === $schoolId;
			}));
			// Prefer same-school contacts when present; otherwise org-wide role holders
			if ($same) {
				return $same;
			}
		}
		return $rows;
	}

	public function staffById(int $staffId): ?array
	{
		if ($staffId < 1) {
			return null;
		}
		$row = \Config\Database::connect()->table('staffs')
			->select('id, fname, lname, email, phone, school_id, post')
			->where('id', $staffId)
			->get(1)->getRowArray();
		return $row ?: null;
	}

	/**
	 * Unique staff contacts for the three budget approver roles.
	 * @return array<int, array>
	 */
	public function approverContacts(?int $schoolId = null): array
	{
		$byId = [];
		foreach (self::APPROVER_POSTS as $postId) {
			foreach ($this->activeStaffByPost($postId, $schoolId) as $s) {
				$byId[(int) $s['id']] = $s;
			}
		}
		return array_values($byId);
	}

	public function displayName(array $staff): string
	{
		return trim(($staff['fname'] ?? '') . ' ' . ($staff['lname'] ?? '')) ?: ('Staff #' . ($staff['id'] ?? ''));
	}
}
