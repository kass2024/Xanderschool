<?php if (!empty($is_central) && !empty($branch_stats)) { ?>
<div class="alert alert-primary mb-3">
	<strong>Wisdom Schools — central overview.</strong> Each branch operates as its own school. Names below use the <em>Wisdom</em> prefix for head-office reporting only.
</div>
<div class="card mb-3"><div class="card-header">All Wisdom branches</div><div class="card-body p-0">
<table class="table table-sm mb-0"><thead><tr><th>Branch</th><th>Draft budgets</th><th>Active requests</th><th>Awaiting payment</th></tr></thead><tbody>
<?php foreach ($branch_stats as $bs) { ?>
<tr><td><strong><?= esc($bs['display_name']); ?></strong></td>
<td><?= (int)$bs['draft_budgets']; ?></td><td><?= (int)$bs['pending_cash']; ?></td><td><?= (int)$bs['awaiting_payment']; ?></td></tr>
<?php } ?>
</tbody></table></div></div>
<?php } else { ?>
<div class="alert alert-light border mb-3">
	<strong><?= esc($branch_label ?? 'Your school'); ?></strong> — budget and cash flow for this school only.
</div>
<?php } ?>

<div class="row mb-3">
	<div class="col-md-3"><div class="card"><div class="card-body text-center"><h3><?= (int)($stats['draft_budgets'] ?? 0); ?></h3><small class="text-muted">Draft budgets</small></div></div></div>
	<div class="col-md-3"><div class="card border-primary"><div class="card-body text-center"><h3><?= (int)($stats['pending_cash'] ?? 0); ?></h3><small class="text-muted">Active cash requests</small></div></div></div>
	<div class="col-md-3"><div class="card border-warning"><div class="card-body text-center"><h3><?= (int)($stats['awaiting_payment'] ?? 0); ?></h3><small class="text-muted">Awaiting payment</small></div></div></div>
	<div class="col-md-3"><div class="card border-success"><div class="card-body text-center"><h3><?= (int)($stats['awaiting_receipt'] ?? 0); ?></h3><small class="text-muted">Awaiting receipt</small></div></div></div>
</div>
<div class="card">
	<div class="card-header">Quick links</div>
	<div class="card-body">
		<a class="btn btn-primary mr-2 mb-2" href="<?= base_url('budget/prepare'); ?>">Prepare budget</a>
		<a class="btn btn-success mr-2 mb-2" href="<?= base_url('budget/cash_request_form'); ?>">New cash request</a>
		<a class="btn btn-outline-secondary mr-2 mb-2" href="<?= base_url('budget/pending_actions'); ?>">My pending actions</a>
		<a class="btn btn-outline-info mb-2" href="<?= base_url('budget/templates'); ?>">Budget templates</a>
	</div>
</div>
