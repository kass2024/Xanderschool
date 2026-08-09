<?php if (session()->getFlashdata('error')) { ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
	<i class="fa fa-exclamation-circle"></i> <?= esc(session()->getFlashdata('error')); ?>
	<button type="button" class="close" data-dismiss="alert">&times;</button>
</div>
<?php } ?>
<?php if (session()->getFlashdata('success')) { ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
	<i class="fa fa-check-circle"></i> <?= esc(session()->getFlashdata('success')); ?>
	<button type="button" class="close" data-dismiss="alert">&times;</button>
</div>
<?php } ?>

<link href="<?= base_url('assets/css/budget-preparation.css'); ?>?v=3" rel="stylesheet">

<div class="bp-hero mb-3">
	<h2><i class="fa fa-chart-line"></i> Budget Dashboard</h2>
	<p class="bp-meta mb-0"><?= esc($branch_label ?? 'Your school'); ?> — budget vs actual at a glance.</p>
</div>

<?php if (!empty($is_central) && !empty($branch_stats)) { ?>
<div class="alert alert-primary mb-3">
	<strong>Central overview.</strong> Each branch prepares and approves its own budget; spending requests are checked against that approved budget.
</div>
<div class="card mb-3"><div class="card-header">All branches</div><div class="card-body p-0">
<table class="table table-sm mb-0"><thead><tr><th>Branch</th><th>Draft budgets</th><th>Active requests</th><th>Awaiting payment</th></tr></thead><tbody>
<?php foreach ($branch_stats as $bs) { ?>
<tr><td><strong><?= esc($bs['display_name']); ?></strong></td>
<td><?= (int)$bs['draft_budgets']; ?></td><td><?= (int)$bs['pending_cash']; ?></td><td><?= (int)$bs['awaiting_payment']; ?></td></tr>
<?php } ?>
</tbody></table></div></div>
<?php } ?>

<?php if (!empty($financials)) {
	$f = $financials;
?>
<div class="bp-kpi-row mb-4">
	<div class="bp-kpi"><label>Total budget (expenses)</label><strong><?= number_format((float)$f['total_budget'], 0); ?></strong><small class="text-muted d-block">RWF · approved plan</small></div>
	<div class="bp-kpi expense"><label>Total actual (paid)</label><strong><?= number_format((float)$f['total_actual'], 0); ?></strong><small class="text-muted d-block">RWF · cash out</small></div>
	<div class="bp-kpi <?= (float)$f['variance'] >= 0 ? 'surplus pos' : 'surplus neg'; ?>"><label>Budget variance</label><strong><?= number_format((float)$f['variance'], 0); ?></strong><small class="text-muted d-block"><?= (float)$f['variance_pct']; ?>% remaining</small></div>
	<div class="bp-kpi income"><label>Fee revenue (plan)</label><strong><?= number_format((float)$f['total_income'], 0); ?></strong><small class="text-muted d-block">RWF · income budget</small></div>
	<div class="bp-kpi"><label>Expected students</label><strong><?= (int)$f['enrollment']; ?></strong><small class="text-muted d-block">from budget setup</small></div>
</div>
<?php if (empty($f['budget'])) { ?>
<div class="alert alert-warning"><i class="fa fa-exclamation-triangle"></i> No <strong>approved budget</strong> yet. Prepare and submit your school budget first — cash requests cannot be approved without it.</div>
<?php } ?>
<?php } ?>

<div class="row mb-3">
	<div class="col-md-3"><div class="card"><div class="card-body text-center"><h3><?= (int)($stats['draft_budgets'] ?? 0); ?></h3><small class="text-muted">Draft budgets</small></div></div></div>
	<div class="col-md-3"><div class="card border-primary"><div class="card-body text-center"><h3><?= (int)($stats['pending_cash'] ?? 0); ?></h3><small class="text-muted">Active requests</small></div></div></div>
	<div class="col-md-3"><div class="card border-warning"><div class="card-body text-center"><h3><?= (int)($stats['awaiting_payment'] ?? 0); ?></h3><small class="text-muted">Awaiting payment</small></div></div></div>
	<div class="col-md-3"><div class="card border-success"><div class="card-body text-center"><h3><?= (int)($stats['awaiting_receipt'] ?? 0); ?></h3><small class="text-muted">Awaiting receipt</small></div></div></div>
</div>

<div class="row mb-3">
	<div class="col-lg-7">
<div class="card">
	<div class="card-header">Quick actions</div>
	<div class="card-body">
		<?php if (budget_menu_any(['budget_prepare'])) { ?>
		<a class="btn btn-primary mr-2 mb-2" href="<?= base_url('budget/prepare'); ?>"><i class="fa fa-edit"></i> Prepare budget</a>
		<?php } ?>
		<?php if (budget_menu_any(['budget_cash_requests']) && budget_permission_allowed('cash_request.create')) { ?>
		<a class="btn btn-success mr-2 mb-2" href="<?= base_url('budget/cash_request_form'); ?>"><i class="fa fa-plus"></i> New cash request</a>
		<?php } ?>
		<?php if (budget_menu_any(['budget_pending', 'budget_procurement', 'budget_availability', 'budget_final_approval'])) { ?>
		<a class="btn btn-outline-warning mr-2 mb-2" href="<?= base_url('budget/requests?tab=pending'); ?>"><i class="fa fa-tasks"></i> My pending actions</a>
		<?php } ?>
		<?php if (budget_menu_any(['budget_reports'])) { ?>
		<a class="btn btn-outline-secondary mb-2" href="<?= base_url('budget/reports'); ?>"><i class="fa fa-chart-bar"></i> Reports</a>
		<?php } ?>
	</div>
</div>
	</div>
	<div class="col-lg-5">
		<?= view('pages/budget/partials/process_guide', ['ctx' => 'full', 'compact' => true]); ?>
	</div>
</div>
