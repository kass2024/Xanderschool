<link href="<?= base_url('assets/css/budget-preparation.css'); ?>?v=3" rel="stylesheet">

<?php $tab = $tab ?? 'budgets'; ?>

<div class="budget-prep-list">

<div class="bp-hero mb-3">
	<h2><i class="fa fa-calculator"></i> Prepare Budget</h2>
	<p class="bp-meta mb-0"><?= esc($branch_label ?? ''); ?> — secondary school budget (fees, expenses, monthly spread) with approval workflow.</p>
</div>

<?= view('pages/budget/partials/hub_nav', ['hub' => 'prepare', 'tab' => $tab]); ?>

<?php if ($tab === 'budgets') { ?>

<div class="bp-steps-row mb-3">
	<div class="bp-step-card"><i class="fa fa-cog d-block"></i><h6>1. Setup</h6><small class="text-muted">Enrollment &amp; fees</small></div>
	<div class="bp-step-card"><i class="fa fa-list-alt d-block"></i><h6>2. Budget plan</h6><small class="text-muted">Revenue &amp; expenses</small></div>
	<div class="bp-step-card"><i class="fa fa-calendar-alt d-block"></i><h6>3. Monthly spread</h6><small class="text-muted">Cash flow phasing</small></div>
	<div class="bp-step-card"><i class="fa fa-paper-plane d-block"></i><h6>4. Submit</h6><small class="text-muted">School approval</small></div>
</div>

<div class="row mb-4">
	<div class="col-lg-8">
		<?php if (function_exists('budget_permission_allowed') && budget_permission_allowed('budget.prepare')) { ?>
		<button class="btn btn-primary btn-lg shadow-sm" data-toggle="modal" data-target="#mdlBudget"><i class="fa fa-plus-circle"></i> Start new budget</button>
		<?php } ?>
		<?php if (empty($periods) && budget_menu_any(['budget_periods'])) { ?>
		<a href="<?= base_url('budget/prepare?tab=periods'); ?>" class="btn btn-outline-warning ml-2">Create budget period first</a>
		<?php } ?>
	</div>
	<div class="col-lg-4 text-lg-right text-muted small pt-2">
		<i class="fa fa-check-circle text-success"></i> Secondary school template
	</div>
</div>

<div class="row mb-4">
	<div class="col-lg-7">
<?php if (empty($budgets)) { ?>
<div class="bp-empty">
	<i class="fa fa-file-invoice-dollar d-block"></i>
	<h5>No budgets yet</h5>
	<p class="text-muted mb-3">Create your school budget online — same structure as the Excel template (Detailed Budget + Fees &amp; Enrollment).</p>
	<button class="btn btn-primary" data-toggle="modal" data-target="#mdlBudget">Create your first budget</button>
</div>
<?php } else { ?>
<?php foreach ($budgets as $b) {
	$pct = 0;
	if ((float)$b['total_income'] > 0 || (float)$b['total_expenses'] > 0) {
		$filled = min(100, round(((float)$b['total_income'] + (float)$b['total_expenses']) / max((float)$b['total_income'], 1) * 50));
		$pct = max(15, min(100, $filled));
	}
	$statusClass = $b['status'] === 'DRAFT' ? 'secondary' : ($b['status'] === 'APPROVED' ? 'success' : 'info');
?>
<div class="bp-budget-card">
	<div class="d-flex justify-content-between align-items-start mb-2">
		<div>
			<h5 class="mb-1 font-weight-bold"><?= esc($b['title']); ?></h5>
			<span class="badge badge-<?= $statusClass; ?>"><?= esc($b['status']); ?></span>
		</div>
		<?php if (in_array($b['status'], ['DRAFT','RETURNED'], true)) { ?>
		<a href="<?= base_url('budget/edit_budget/'.$b['id']); ?>" class="btn btn-sm btn-primary"><i class="fa fa-edit"></i> Open workspace</a>
		<?php } elseif ($b['status'] === 'APPROVED') { ?>
		<a href="<?= base_url('budget/cash_request_form'); ?>" class="btn btn-sm btn-success"><i class="fa fa-money-bill"></i> New request</a>
		<?php } ?>
	</div>
	<div class="row small text-muted mb-2">
		<div class="col-4">Income<br><strong class="text-success"><?= number_format((float)$b['total_income'], 0); ?></strong></div>
		<div class="col-4">Expenses<br><strong class="text-danger"><?= number_format((float)$b['total_expenses'], 0); ?></strong></div>
		<div class="col-4">Surplus<br><strong class="<?= (float)$b['surplus_deficit'] >= 0 ? 'text-success' : 'text-danger'; ?>"><?= number_format((float)$b['surplus_deficit'], 0); ?></strong></div>
	</div>
	<div class="bp-progress"><div class="bp-progress-bar" style="width:<?= (int)$pct; ?>%"></div></div>
</div>
<?php } ?>
<?php } ?>
	</div>
	<div class="col-lg-5"><?= view('pages/budget/partials/process_guide', ['ctx' => 'full']); ?></div>
</div>

<div class="modal fade" id="mdlBudget"><div class="modal-dialog modal-lg"><form class="modal-content" id="frmBudget">
<div class="modal-header bg-primary text-white"><h5 class="modal-title"><i class="fa fa-magic"></i> New budget workspace</h5><button type="button" class="close text-white" data-dismiss="modal">&times;</button></div>
<div class="modal-body">
	<div class="alert alert-light border"><i class="fa fa-info-circle text-primary"></i> Loads the <strong>Secondary School Budget Template</strong> — school fees, salaries, utilities, learning materials, and monthly cash flow.</div>
	<div class="form-group"><label class="font-weight-bold">Budget title</label><input class="form-control form-control-lg" name="title" placeholder="e.g. Annual Budget 2026" required value="Budget <?= date('Y'); ?>"></div>
	<div class="form-group"><label class="font-weight-bold">Budget period</label>
	<select name="budget_period_id" class="form-control" required>
		<?php if (empty($periods)) { ?><option value="">— Create a period first —</option><?php } ?>
		<?php foreach ($periods as $p) { ?><option value="<?= (int)$p['id']; ?>"><?= esc($p['title']); ?> (<?= esc($p['start_date'] ?? ''); ?>)</option><?php } ?>
	</select></div>
	<input type="hidden" name="template_id" value="<?= (int)($active_template['id'] ?? 0); ?>">
	<?php if (!empty($active_template)) { ?>
	<p class="small text-muted mb-0"><i class="fa fa-check text-success"></i> Template: <?= esc($active_template['name']); ?></p>
	<?php } ?>
</div>
<div class="modal-footer"><button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary btn-lg">Open workspace <i class="fa fa-arrow-right"></i></button></div>
</form></div></div>

<script>
$('#frmBudget').on('submit',function(e){
	e.preventDefault();
	var $btn=$(this).find('[type=submit]').prop('disabled',true).html('<i class="fa fa-spinner fa-spin"></i> Creating...');
	$.post('<?= base_url('budget/create_budget'); ?>',$(this).serialize(),function(r){
		if(r.error){toastada.error(r.error);$btn.prop('disabled',false).html('Open workspace <i class="fa fa-arrow-right"></i>');return;}
		location.href='<?= base_url('budget/edit_budget/'); ?>'+r.budget_id;
	},'json').fail(function(){$btn.prop('disabled',false).html('Open workspace <i class="fa fa-arrow-right"></i>');});
});
</script>

<?php } elseif ($tab === 'periods') { ?>
<?php
$periods = $periods ?? [];
$branches = $branches ?? [];
echo view('pages/budget/periods', compact('periods', 'branches'));
?>

<?php } elseif ($tab === 'templates') { ?>
<?php
$templates = $all_templates ?? [];
$can_install_official = $can_install_official ?? false;
echo view('pages/budget/templates', compact('templates', 'can_install_official'));
?>

<?php } elseif ($tab === 'review') { ?>
<?php $budgets = $review_budgets ?? []; echo view('pages/budget/budget_review', compact('budgets')); ?>

<?php } else { /* approved */ ?>
<?php $budgets = $approved_budgets ?? []; echo view('pages/budget/approved_budgets', compact('budgets')); ?>
<?php } ?>

</div>
