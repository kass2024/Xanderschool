<?php

namespace Config;

/**
 * Budget & Cash Flow permission keys.
 */
class BudgetPermissions
{
	public const ALL = [
		'budget.settings.manage',
		'budget.templates.upload',
		'budget.templates.view',
		'budget.templates.activate',
		'budget.templates.archive',
		'budget.periods.manage',
		'budget.prepare',
		'budget.edit_own',
		'budget.submit',
		'budget.review_branch',
		'budget.review_procurement',
		'budget.review_budget',
		'budget.final_approve',
		'budget.reject',
		'budget.return',
		'budget.adjust',
		'budget.edit_submitted',
		'budget.transfer',
		'budget.view_all_branches',
		'budget.view_reports',
		'budget.export',
		'cash_request.create',
		'cash_request.edit_own',
		'cash_request.submit',
		'cash_request.headteacher_approve',
		'cash_request.procurement_review',
		'cash_request.budget_review',
		'cash_request.final_approve',
		'cash_request.reject',
		'cash_request.return',
		'cash_request.process_payment',
		'cash_request.confirm_receipt',
		'cash_request.close',
		'cash_request.cancel',
		'cash_request.view_audit',
		'cash_request.manage_documents',
		'cash_request.override_budget',
	];

	/** Posts that bypass menu but NOT finance action permissions. */
	public const FINANCE_FULL_ACCESS_POSTS = [];

	public static function labels()
	{
		return [
			'budget.settings.manage' => 'Manage budget settings',
			'budget.templates.upload' => 'Upload budget templates',
			'budget.templates.view' => 'View budget templates',
			'budget.templates.activate' => 'Activate templates',
			'budget.templates.archive' => 'Archive templates',
			'budget.periods.manage' => 'Manage budget periods',
			'budget.prepare' => 'Prepare budgets',
			'budget.edit_own' => 'Edit own budgets',
			'budget.submit' => 'Submit budgets',
			'budget.review_branch' => 'Review branch budgets',
			'budget.review_procurement' => 'Procurement budget review',
			'budget.review_budget' => 'Budget manager review',
			'budget.final_approve' => 'Final finance approval',
			'budget.reject' => 'Reject budgets',
			'budget.return' => 'Return budgets',
			'budget.adjust' => 'Budget adjustments',
			'budget.edit_submitted' => 'Edit submitted/approved budgets (Director of Finance)',
			'budget.transfer' => 'Budget transfers',
			'budget.view_all_branches' => 'View all branches',
			'budget.view_reports' => 'View budget reports',
			'budget.export' => 'Export budgets',
			'cash_request.create' => 'Create cash requests',
			'cash_request.edit_own' => 'Edit own cash requests',
			'cash_request.submit' => 'Submit cash requests',
			'cash_request.headteacher_approve' => 'Headteacher approve requests',
			'cash_request.procurement_review' => 'Procurement review',
			'cash_request.budget_review' => 'Budget availability review',
			'cash_request.final_approve' => 'Final authorize cash requests',
			'cash_request.reject' => 'Reject cash requests',
			'cash_request.return' => 'Return cash requests',
			'cash_request.process_payment' => 'Process payments',
			'cash_request.confirm_receipt' => 'Confirm receipt',
			'cash_request.close' => 'Close cash requests',
			'cash_request.cancel' => 'Cancel cash requests',
			'cash_request.view_audit' => 'View audit trail',
			'cash_request.manage_documents' => 'Manage request documents',
			'cash_request.override_budget' => 'Override budget limits',
		];
	}

	public static function defaultForPost($postId)
	{
		$postId = (int) $postId;
		$map = [
			9 => [ // Accountant
				'budget.prepare', 'budget.edit_own', 'budget.submit', 'budget.periods.manage',
				'budget.templates.view', 'cash_request.create', 'cash_request.edit_own',
				'cash_request.submit', 'cash_request.confirm_receipt', 'cash_request.close',
				'cash_request.manage_documents', 'budget.view_reports',
			],
			1 => [ // Head master — branch budget & cash requests
				'budget.prepare', 'budget.edit_own', 'budget.submit', 'budget.templates.view',
				'budget.view_reports', 'cash_request.create', 'cash_request.edit_own', 'cash_request.submit',
				'cash_request.headteacher_approve', 'cash_request.view_audit',
			],
			18 => [ // Headmistress
				'budget.prepare', 'budget.edit_own', 'budget.submit', 'budget.templates.view',
				'budget.view_reports', 'cash_request.create', 'cash_request.edit_own', 'cash_request.submit',
				'cash_request.headteacher_approve', 'cash_request.view_audit',
			],
			8 => [ // Cashier
				'cash_request.process_payment', 'cash_request.manage_documents', 'budget.view_reports',
			],
		];
		// Dynamic posts seeded at runtime: 19 Budget Manager, 20 Procurement, 21 Deputy Director, 22 Finance Officer, 23 Auditor
		$dynamic = [
			19 => ['budget.review_budget', 'budget.return', 'budget.reject', 'cash_request.budget_review', 'cash_request.return', 'budget.view_reports', 'budget.adjust', 'budget.transfer'],
			20 => ['budget.review_procurement', 'budget.return', 'cash_request.procurement_review', 'cash_request.return', 'budget.view_reports'],
			21 => ['budget.final_approve', 'budget.view_all_branches', 'cash_request.final_approve', 'cash_request.reject', 'cash_request.return', 'budget.view_reports', 'budget.export', 'cash_request.view_audit', 'cash_request.override_budget', 'budget.return', 'budget.reject'],
			22 => ['cash_request.process_payment', 'cash_request.manage_documents', 'budget.view_reports'],
			23 => ['cash_request.view_audit', 'budget.view_all_branches', 'budget.view_reports', 'budget.export'],
			// Director of Finance — may edit already-submitted / approved school budgets
			24 => [
				'budget.edit_submitted', 'budget.adjust', 'budget.prepare', 'budget.edit_own',
				'budget.view_all_branches', 'budget.view_reports', 'budget.export',
				'budget.final_approve', 'budget.return', 'budget.reject',
				'cash_request.final_approve', 'cash_request.reject', 'cash_request.return',
				'cash_request.view_audit', 'cash_request.override_budget',
			],
		];
		return array_values(array_unique(array_merge($map[$postId] ?? [], $dynamic[$postId] ?? [])));
	}
}
