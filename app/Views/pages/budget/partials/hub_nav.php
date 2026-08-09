<?php
/** Top tab navigation inside Budget & Cash Flow hubs (4 main menus). */
$hub = $hub ?? 'prepare';
$tab = $tab ?? 'budgets';

$tabs = [];
$base = base_url('budget/prepare');
if ($hub === 'prepare') {
	$tabs = [
		'budgets' => ['label' => 'My budgets', 'keys' => ['budget_prepare']],
		'periods' => ['label' => 'Periods', 'keys' => ['budget_periods']],
		'templates' => ['label' => 'Template', 'keys' => ['budget_templates']],
		'review' => ['label' => 'Review', 'keys' => ['budget_review']],
		'approved' => ['label' => 'Approved', 'keys' => ['budget_approved']],
	];
	$base = base_url('budget/prepare');
} elseif ($hub === 'requests') {
	$tabs = [
		'all' => ['label' => 'All requests', 'keys' => ['budget_cash_requests']],
		'pending' => ['label' => 'My pending', 'keys' => ['budget_pending', 'budget_procurement', 'budget_availability', 'budget_final_approval']],
		'payments' => ['label' => 'Payments', 'keys' => ['budget_payments']],
		'receipts' => ['label' => 'Receipts', 'keys' => ['budget_filing']],
	];
	$base = base_url('budget/requests');
} else {
	$tabs = [
		'summary' => ['label' => 'Budget summary', 'keys' => ['budget_reports']],
		'cashflow' => ['label' => 'Cash flow', 'keys' => ['budget_reports']],
		'actuals' => ['label' => 'Actuals', 'keys' => ['budget_reports']],
		'audit' => ['label' => 'Audit', 'keys' => ['budget_audit', 'budget_settings']],
	];
	$base = base_url('budget/reports');
}
?>
<div class="budget-hub-nav mb-3">
	<nav class="nav nav-pills flex-wrap">
		<?php foreach ($tabs as $key => $meta) {
			if (!function_exists('budget_menu_any') || !budget_menu_any($meta['keys'])) {
				continue;
			}
			$active = ($tab === $key) ? ' active' : '';
			$href = $base;
			if (($hub === 'prepare' && $key !== 'budgets')
				|| ($hub === 'requests' && $key !== 'all')
				|| ($hub === 'reports' && $key !== 'summary')) {
				$href = $base . '?tab=' . $key;
			}
		?>
		<a class="nav-link<?= $active; ?>" href="<?= esc($href); ?>"><?= esc($meta['label']); ?></a>
		<?php } ?>
	</nav>
</div>
