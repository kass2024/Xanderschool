<?php

namespace App\Services\Budget;

class BudgetNotificationService
{
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
		$db = \Config\Database::connect();
		$staffs = $db->table('staffs')
			->select('id')
			->where('post', (int) $postId)
			->where('status', 1)
			->get()->getResultArray();
		foreach ($staffs as $s) {
			$this->notifyStaff((int) $s['id'], $title, $body, $linkUrl, $branchId);
		}
	}

	public function unreadCount($staffId)
	{
		return (int) \Config\Database::connect()->table('budget_notifications')
			->where('staff_id', (int) $staffId)->where('is_read', 0)->countAllResults();
	}
}
