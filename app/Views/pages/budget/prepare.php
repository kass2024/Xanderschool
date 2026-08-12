<link href="<?= base_url('assets/css/budget-preparation.css'); ?>?v=5" rel="stylesheet">

<?php $tab = $tab ?? 'budgets'; ?>

<div class="budget-prep-list">

<div class="bp-hero mb-3">
	<h2><i class="fa fa-calculator"></i> Prepare Annual Budget</h2>
	<p class="bp-meta mb-0"><?= esc($branch_label ?? ''); ?> — full-year budget in the browser, split across <strong>Term I, Term II &amp; Term III</strong>. No Excel required.</p>
</div>

<?= view('pages/budget/partials/hub_nav', ['hub' => 'prepare', 'tab' => $tab]); ?>

<?php if ($tab === 'budgets') { ?>

<div class="bp-steps-row mb-3">
	<div class="bp-step-card"><i class="fa fa-cog d-block"></i><h6>1. Setup</h6><small class="text-muted">Year &amp; assumptions</small></div>
	<div class="bp-step-card"><i class="fa fa-table d-block"></i><h6>2. Three-term plan</h6><small class="text-muted">Income &amp; expenses per term</small></div>
	<div class="bp-step-card"><i class="fa fa-paper-plane d-block"></i><h6>3. Submit</h6><small class="text-muted">Approval workflow</small></div>
</div>

<div class="row mb-4">
	<div class="col-lg-8">
		<?php if (\Config\MenuClearance::canPrepareBudgetAtSchool((int) ($_SESSION['soma_post'] ?? 0)) && function_exists('budget_permission_allowed') && budget_permission_allowed('budget.prepare')) { ?>
		<button class="btn btn-primary btn-lg shadow-sm" id="btnNewBudget"><i class="fa fa-plus-circle"></i> Start annual budget (3 terms)</button>
		<?php } elseif (\Config\MenuClearance::isBudgetViewOnlyPost((int) ($_SESSION['soma_post'] ?? 0))) { ?>
		<div class="alert alert-secondary border"><i class="fa fa-eye"></i> <strong>View only.</strong> Head master and school leaders can monitor budgets after Cashier/Accountant prepare them. Open the <a href="<?= base_url('budget/dashboard'); ?>">Budget Dashboard</a>.</div>
		<?php } ?>
		<p class="small text-muted mt-2 mb-0">Opens the online budget grid: INCOME, OPERATING EXPENSES, ADMINISTRATIVE COSTS, and FINANCE COSTS — each line with Term I / II / III amounts.</p>
	</div>
</div>

<div class="row mb-4">
	<div class="col-lg-7">
<?php if (empty($budgets)) { ?>
<div class="bp-empty">
	<i class="fa fa-file-invoice-dollar d-block"></i>
	<h5>No annual budget yet</h5>
	<p class="text-muted mb-3">Prepare your full-year school budget online. Enter amounts for each term; the system totals automatically.</p>
	<?php if (\Config\MenuClearance::canPrepareBudgetAtSchool((int) ($_SESSION['soma_post'] ?? 0))) { ?>
	<button class="btn btn-primary" id="btnNewBudget2">Start annual budget</button>
	<?php } ?>
</div>
<?php } else { ?>
<?php
$postIdSession = (int) ($_SESSION['soma_post'] ?? 0);
$canPrepareUi = \Config\MenuClearance::canPrepareBudgetAtSchool($postIdSession);
$viewOnlyUi = \Config\MenuClearance::isBudgetViewOnlyPost($postIdSession);
foreach ($budgets as $b) {
	$statusClass = $b['status'] === 'DRAFT' ? 'secondary' : ($b['status'] === 'APPROVED' ? 'success' : 'info');
?>
<div class="bp-budget-card">
	<div class="d-flex justify-content-between align-items-start mb-2">
		<div>
			<h5 class="mb-1 font-weight-bold"><?= esc($b['title']); ?></h5>
			<span class="badge badge-<?= $statusClass; ?>"><?= esc($b['status']); ?></span>
		</div>
		<div class="text-right">
			<?php
			$canFinanceAdjust = function_exists('budget_permission_allowed') && budget_permission_allowed('budget.edit_submitted');
			$isPreparerEdit = in_array($b['status'], ['DRAFT', 'RETURNED'], true);
			$isSubmittedPipeline = in_array($b['status'], ['SUBMITTED', 'PROCUREMENT_REVIEW', 'BUDGET_MANAGER_REVIEW', 'DEPUTY_DIRECTOR_REVIEW', 'APPROVED', 'REJECTED'], true);
			?>
			<?php if ($viewOnlyUi) { ?>
			<a href="<?= base_url('budget/dashboard'); ?>" class="btn btn-sm btn-outline-secondary mb-1"><i class="fa fa-eye"></i> View on dashboard</a>
			<?php } elseif ($canPrepareUi && $isPreparerEdit) { ?>
			<a href="<?= base_url('budget/edit_budget/'.$b['id']); ?>" class="btn btn-sm btn-primary mb-1"><i class="fa fa-edit"></i> Open budget</a>
			<?php } elseif ($canFinanceAdjust && $isSubmittedPipeline) { ?>
			<a href="<?= base_url('budget/edit_budget/'.$b['id']); ?>" class="btn btn-sm btn-warning mb-1" title="Director of Finance — edit submitted / approved budget"><i class="fa fa-edit"></i> Edit</a>
			<?php } ?>
			<?php if (!$viewOnlyUi && $b['status'] === 'APPROVED') { ?>
			<a href="<?= base_url('budget/cash_request_form'); ?>" class="btn btn-sm btn-success mb-1"><i class="fa fa-money-bill"></i> New request</a>
			<?php } elseif (!$viewOnlyUi && !$isPreparerEdit && $b['status'] !== 'APPROVED') { ?>
			<a href="<?= base_url('budget/prepare?tab=review'); ?>" class="btn btn-sm btn-outline-info mb-1"><i class="fa fa-tasks"></i> In approval</a>
			<?php } ?>
			<?php if ($canPrepareUi && function_exists('budget_permission_allowed') && (budget_permission_allowed('budget.prepare') || budget_permission_allowed('budget.edit_own') || budget_permission_allowed('budget.final_approve') || budget_permission_allowed('budget.edit_submitted'))) { ?>
			<button type="button" class="btn btn-sm btn-outline-danger mb-1 btn-del-budget" data-id="<?= (int)$b['id']; ?>" data-title="<?= esc($b['title']); ?>"><i class="fa fa-trash"></i> Delete</button>
			<?php } ?>
		</div>
	</div>
	<div class="row small text-muted mb-0">
		<div class="col-4">Income (year)<br><strong class="text-success"><?= number_format((float)$b['total_income'], 0); ?></strong></div>
		<div class="col-4">Expenses (year)<br><strong class="text-danger"><?= number_format((float)$b['total_expenses'], 0); ?></strong></div>
		<div class="col-4">Surplus<br><strong class="<?= (float)$b['surplus_deficit'] >= 0 ? 'text-success' : 'text-danger'; ?>"><?= number_format((float)$b['surplus_deficit'], 0); ?></strong></div>
	</div>
</div>
<?php } ?>
<?php } ?>
	</div>
	<div class="col-lg-5"><?= view('pages/budget/partials/process_guide', ['ctx' => 'full', 'compact' => true]); ?></div>
</div>

<div class="modal fade" id="mdlBudget"><div class="modal-dialog"><form class="modal-content" id="frmBudget">
<div class="modal-header bg-primary text-white"><h5 class="modal-title"><i class="fa fa-calendar-check"></i> New annual budget</h5><button type="button" class="close text-white" data-dismiss="modal">&times;</button></div>
<div class="modal-body">
	<div class="alert alert-light border small"><i class="fa fa-info-circle text-primary"></i> You will enter budget amounts for <strong>Term I, Term II and Term III</strong>. Annual total = sum of the three terms.</div>
	<div class="form-group"><label class="font-weight-bold">Budget title</label><input class="form-control" name="title" placeholder="e.g. Annual Budget 2025-26" value="Annual Budget <?= date('Y'); ?>-<?= substr((string)(date('Y')+1), -2); ?>"></div>
	<div class="form-group"><label class="font-weight-bold">Academic year</label><input class="form-control" name="academic_year" value="<?= date('Y'); ?>-<?= substr((string)(date('Y')+1), -2); ?>" placeholder="2025-26"></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Open budget grid <i class="fa fa-arrow-right"></i></button></div>
</form></div></div>

<script>
function openBudgetModal(){ $('#mdlBudget').modal('show'); }
$('#btnNewBudget, #btnNewBudget2').on('click', openBudgetModal);
$('#frmBudget').on('submit',function(e){
	e.preventDefault();
	var $btn=$(this).find('[type=submit]').prop('disabled',true).html('<i class="fa fa-spinner fa-spin"></i> Creating...');
	$.post('<?= base_url('budget/create_budget'); ?>',$(this).serialize(),function(r){
		if(r.error){ toastada.error(r.error); $btn.prop('disabled',false).html('Open budget grid <i class="fa fa-arrow-right"></i>'); return; }
		location.href='<?= base_url('budget/edit_budget/'); ?>'+r.budget_id;
	},'json').fail(function(){ $btn.prop('disabled',false).html('Open budget grid <i class="fa fa-arrow-right"></i>'); });
});
$(document).on('click', '.btn-del-budget', function () {
	var id = $(this).data('id');
	var title = $(this).data('title') || 'this budget';
	if (!confirm('Delete "' + title + '" for this school?\n\nLines and approval history will be removed. Budgets with cash requests cannot be deleted.')) return;
	var $btn = $(this).prop('disabled', true);
	$.post('<?= base_url('budget/delete_budget'); ?>', { budget_id: id }, function (r) {
		if (r.error) { toastada.error(r.error); $btn.prop('disabled', false); return; }
		toastada.success(r.success || 'Deleted');
		location.reload();
	}, 'json').fail(function () { $btn.prop('disabled', false); toastada.error('Delete failed'); });
});
</script>

<?php } elseif ($tab === 'periods') { ?>
<?php
$periods = $periods ?? [];
$branches = $branches ?? [];
echo view('pages/budget/periods', compact('periods', 'branches'));
?>

<?php } elseif ($tab === 'review') { ?>
<?php $budgets = $review_budgets ?? []; echo view('pages/budget/budget_review', compact('budgets')); ?>

<?php } else { ?>
<?php $budgets = $approved_budgets ?? []; echo view('pages/budget/approved_budgets', compact('budgets')); ?>
<?php } ?>

</div>
