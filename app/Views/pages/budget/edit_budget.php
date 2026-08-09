<link href="<?= base_url('assets/css/budget-preparation.css'); ?>?v=2" rel="stylesheet">

<?php

$months = ['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec'];

$monthLabels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

$statusBadge = $budget['status'] === 'DRAFT' ? 'secondary' : ($budget['status'] === 'APPROVED' ? 'success' : 'warning');

$canEdit = in_array($budget['status'], ['DRAFT', 'RETURNED'], true);

?>

<div class="budget-workspace" id="budgetWorkspace" data-budget-id="<?= (int)$budget['id']; ?>">

<div class="bp-hero">

	<div class="d-flex flex-wrap justify-content-between align-items-start">

		<div>

			<h2><i class="fa fa-chart-pie"></i> <?= esc($budget['title']); ?></h2>

			<p class="bp-meta mb-0"><?= esc($branch_label); ?> · <?= esc($budget['period_title'] ?? 'Period'); ?> · <?= esc($budget['currency'] ?? 'RWF'); ?>

			<span class="badge badge-<?= $statusBadge; ?> ml-2"><?= esc($budget['status']); ?></span></p>

		</div>

		<a href="<?= base_url('budget/prepare'); ?>" class="btn btn-sm btn-light"><i class="fa fa-arrow-left"></i> All budgets</a>

	</div>

	<div class="bp-kpi-row">

		<div class="bp-kpi income"><label>Total income</label><strong id="kpiIncome"><?= number_format((float)$budget['total_income'], 0); ?></strong></div>

		<div class="bp-kpi expense"><label>Total expenses</label><strong id="kpiExpense"><?= number_format((float)$budget['total_expenses'], 0); ?></strong></div>

		<div class="bp-kpi surplus <?= (float)$budget['surplus_deficit'] >= 0 ? 'pos' : 'neg'; ?>"><label>Surplus / deficit</label><strong id="kpiSurplus"><?= number_format((float)$budget['surplus_deficit'], 0); ?></strong></div>

		<div class="bp-kpi"><label>Completion</label><strong id="kpiProgress">0%</strong></div>

	</div>

	<div class="bp-progress mt-2"><div class="bp-progress-bar" id="progressBar" style="width:0%"></div></div>

</div>



<div class="bp-stepper" id="bpStepper">

	<button type="button" class="bp-step active" data-tab="setup" data-step="1">

		<span class="bp-step-icon">1</span>

		<span><span class="bp-step-label">Setup</span><span class="bp-step-desc">Assumptions &amp; opening cash</span></span>

	</button>

	<button type="button" class="bp-step" data-tab="plan" data-step="2">

		<span class="bp-step-icon">2</span>

		<span><span class="bp-step-label">Budget plan</span><span class="bp-step-desc">Income &amp; expense lines</span></span>

	</button>

	<button type="button" class="bp-step" data-tab="monthly" data-step="3">

		<span class="bp-step-icon">3</span>

		<span><span class="bp-step-label">Monthly spread</span><span class="bp-step-desc">Term-by-term phasing</span></span>

	</button>

	<button type="button" class="bp-step" data-tab="summary" data-step="4">

		<span class="bp-step-icon">4</span>

		<span><span class="bp-step-label">Summary &amp; submit</span><span class="bp-step-desc">Review &amp; send for approval</span></span>

	</button>

</div>



<div class="bp-toolbar">

	<div class="bp-save-status saved" id="saveStatus"><i class="fa fa-check"></i> All changes saved online</div>

	<div>

		<?php if ($canEdit) { ?>

		<button type="button" class="btn btn-primary btn-sm" id="btnSave"><i class="fa fa-save"></i> Save</button>

		<button type="button" class="btn btn-success btn-sm" id="btnSubmit"><i class="fa fa-paper-plane"></i> Submit for approval</button>

		<?php } else { ?>

		<span class="badge badge-info p-2">Read-only — budget is <?= esc($budget['status']); ?></span>

		<?php } ?>

	</div>

</div>



<div class="bp-layout">

<div class="bp-main">



<form id="frmBudgetWorkspace">

<input type="hidden" name="budget_id" value="<?= (int)$budget['id']; ?>">



<!-- SETUP -->

<div class="bp-panel active" id="panel-setup">

<div class="bp-panel-help"><i class="fa fa-info-circle"></i> <strong>Step 1 — Planning assumptions.</strong> International schools typically start with enrollment projections, opening cash balance, and written assumptions (fee increases, staffing, capital projects). This feeds your income and expense planning.</div>

<div class="bp-setup-grid">

	<div class="bp-setup-card"><label class="small font-weight-bold text-muted">BRANCH</label><div class="h6 mb-0"><?= esc($branch_label); ?></div></div>

	<div class="bp-setup-card"><label class="small font-weight-bold text-muted">PERIOD</label><div class="h6 mb-0"><?= esc($budget['period_title'] ?? '—'); ?></div><small class="text-muted"><?= esc($budget['start_date'] ?? ''); ?> – <?= esc($budget['end_date'] ?? ''); ?></small></div>

	<div class="bp-setup-card"><label class="small font-weight-bold text-muted">CURRENCY</label><div class="h6 mb-0"><?= esc($budget['currency'] ?? 'RWF'); ?></div></div>

</div>

<div class="card border-0 shadow-sm mt-3"><div class="card-body">

	<div class="row">

		<div class="col-md-6 form-group"><label class="font-weight-bold">Budget title</label><input class="form-control" name="setup_title" id="setupTitle" value="<?= esc($budget['title']); ?>" <?= $canEdit ? '' : 'readonly'; ?>></div>

		<div class="col-md-3 form-group"><label class="font-weight-bold">Expected enrollment</label><input type="number" class="form-control" name="enrollment" id="setupEnrollment" value="<?= esc($setup['enrollment'] ?? ''); ?>" placeholder="Students" <?= $canEdit ? '' : 'readonly'; ?>></div>

		<div class="col-md-3 form-group"><label class="font-weight-bold">Opening cash (RWF)</label><input type="number" step="0.01" class="form-control" name="opening_cash" id="setupOpeningCash" value="<?= esc($setup['opening_cash'] ?? ''); ?>" <?= $canEdit ? '' : 'readonly'; ?>></div>

		<div class="col-12 form-group"><label class="font-weight-bold">Planning notes</label><textarea class="form-control" rows="3" name="planning_notes" id="setupNotes" placeholder="Key assumptions: fee structure, new hires, utility inflation, capital projects..." <?= $canEdit ? '' : 'readonly'; ?>><?= esc($setup['planning_notes'] ?? ''); ?></textarea></div>

	</div>

	<?php if ($canEdit) { ?><button type="button" class="btn btn-outline-primary btn-sm" id="btnSaveSetup"><i class="fa fa-check"></i> Save setup</button><?php } ?>

</div></div>

<div class="bp-nav-footer">

	<span></span>

	<button type="button" class="btn btn-primary btnNext" data-next="plan">Continue to Budget plan <i class="fa fa-arrow-right"></i></button>

</div>

</div>



<!-- BUDGET PLAN -->

<div class="bp-panel" id="panel-plan">

<div class="bp-panel-help"><i class="fa fa-lightbulb"></i> <strong>Step 2 — Line-item budget.</strong> Enter tuition &amp; other income, then operating expenses (salaries, utilities, learning materials). Use <em>Monthly ×12</em> for recurring costs or <em>Qty × Unit</em> for consumables. Section totals update automatically.</div>

<?php foreach ($sections as $secKey => $sec) {

	if (empty($sec['lines'])) continue;

	$isIncome = !empty($sec['is_income']);

?>

<div class="bp-section" data-section="<?= esc($secKey); ?>">

	<div class="bp-section-head <?= $isIncome ? 'income' : 'expense'; ?>">

		<span><i class="fa fa-<?= $isIncome ? 'arrow-down' : 'arrow-up'; ?>"></i> <?= esc($secKey); ?></span>

		<span class="bp-section-total" data-section-total="<?= esc($secKey); ?>">0 RWF</span>

	</div>

	<div class="bp-section-body">

	<?php foreach ($sec['lines'] as $ln) {

		if ((int)$ln['is_editable'] !== 1) { ?>

		<div class="d-flex justify-content-between align-items-center py-2 px-2 bg-light rounded mb-2 total-display-row" data-section="<?= esc($secKey); ?>">

			<strong><?= esc($ln['category']); ?></strong>

			<strong class="section-total-line"><?= number_format((float)$ln['annual_amount'], 0); ?> RWF</strong>

		</div>

		<?php continue; }

		$lid = (int)$ln['id'];

		$mode = $ln['calculation_mode'] ?: 'manual';

		$monthlyVal = $mode === 'monthly' && (float)$ln['user_amount'] > 0 ? (float)$ln['user_amount'] : ((float)$ln['annual_amount'] > 0 && $mode === 'monthly' ? round((float)$ln['annual_amount']/12, 2) : (float)$ln['user_amount']);

		$ro = $canEdit ? '' : 'readonly disabled';

	?>

	<div class="bp-line-card budget-line" data-line-id="<?= $lid; ?>" data-income="<?= $isIncome ? '1' : '0'; ?>" data-section="<?= esc($secKey); ?>">

		<div class="bp-line-main">

			<div class="bp-line-title"><span class="bp-line-id"><?= esc($ln['line_key'] ?? ('L'.$lid)); ?></span><?= esc($ln['category']); ?></div>

			<?php if ($canEdit) { ?>

			<div class="bp-mode-pills">

				<?php foreach (['manual'=>'Annual','monthly'=>'Monthly x12','qty_unit_freq'=>'Qty x Unit','monthly_grid'=>'Month grid'] as $mv=>$ml) { ?>

				<button type="button" class="bp-mode-pill mode-pill <?= $mode===$mv?'active':''; ?>" data-mode="<?= $mv; ?>" data-line="<?= $lid; ?>"><?= $ml; ?></button>

				<?php } ?>

			</div>

			<?php } ?>

			<input type="hidden" class="calc-mode-input" name="lines[<?= $lid; ?>][calculation_mode]" value="<?= esc($mode); ?>">

			<div class="bp-fields mode-fields mode-manual" data-line="<?= $lid; ?>" style="display:<?= $mode==='manual'?'grid':'none'; ?>">

				<div><label>Annual amount</label><input type="number" step="0.01" class="inp-annual" name="lines[<?= $lid; ?>][user_amount]" value="<?= $mode==='manual' ? esc($ln['user_amount'] ?: $ln['annual_amount']) : ''; ?>" placeholder="RWF" <?= $ro; ?>></div>

			</div>

			<div class="bp-fields mode-fields mode-monthly" data-line="<?= $lid; ?>" style="display:<?= $mode==='monthly'?'grid':'none'; ?>">

				<div><label>Monthly amount</label><input type="number" step="0.01" class="inp-monthly" name="lines[<?= $lid; ?>][user_amount]" value="<?= $mode==='monthly' ? esc($monthlyVal) : ''; ?>" placeholder="Per month" <?= $ro; ?>></div>

				<div><label>Qty</label><input type="number" step="0.0001" class="inp-qty" name="lines[<?= $lid; ?>][quantity]" value="<?= esc($ln['quantity'] ?? ''); ?>" <?= $ro; ?>></div>

				<div><label>Unit cost</label><input type="number" step="0.01" class="inp-cost" name="lines[<?= $lid; ?>][unit_cost]" value="<?= esc($ln['unit_cost'] ?? ''); ?>" <?= $ro; ?>></div>

			</div>

			<div class="bp-fields mode-fields mode-qty_unit_freq" data-line="<?= $lid; ?>" style="display:<?= $mode==='qty_unit_freq'?'grid':'none'; ?>">

				<div><label>Quantity</label><input type="number" step="0.0001" class="inp-qty" name="lines[<?= $lid; ?>][quantity]" value="<?= esc($ln['quantity'] ?? ''); ?>" <?= $ro; ?>></div>

				<div><label>Unit</label><select class="inp-unit" name="lines[<?= $lid; ?>][unit]" <?= $canEdit ? '' : 'disabled'; ?>><option value="">—</option><?php foreach ($units as $u) { ?><option value="<?= esc($u); ?>" <?= ($ln['unit']??'')===$u?'selected':''; ?>><?= esc($u); ?></option><?php } ?></select></div>

				<div><label>Unit cost</label><input type="number" step="0.01" class="inp-cost" name="lines[<?= $lid; ?>][unit_cost]" value="<?= esc($ln['unit_cost'] ?? ''); ?>" <?= $ro; ?>></div>

				<div><label>Frequency</label><input type="number" step="0.01" class="inp-freq" name="lines[<?= $lid; ?>][frequency]" value="<?= esc($ln['frequency'] ?: 1); ?>" <?= $ro; ?>></div>

			</div>

			<div class="mode-fields mode-monthly_grid" data-line="<?= $lid; ?>" style="display:<?= $mode==='monthly_grid'?'block':'none'; ?>">

				<small class="text-muted">Use the <strong>Monthly spread</strong> tab for Jan–Dec entry, or enter below:</small>

				<div class="bp-month-grid mt-1"><?php foreach ($months as $i=>$m) { ?><div class="bp-month-cell"><label><?= $monthLabels[$i]; ?></label><input type="number" step="0.01" class="inp-month" name="lines[<?= $lid; ?>][months][<?= $m; ?>]" value="<?= esc($ln['months'][$m] ?? 0); ?>" data-month="<?= $m; ?>" <?= $ro; ?>></div><?php } ?></div>

			</div>

			<div class="mt-2"><input type="text" class="form-control form-control-sm" name="lines[<?= $lid; ?>][assumptions]" value="<?= esc($ln['assumptions'] ?? ''); ?>" placeholder="Notes / assumptions for this line" <?= $ro; ?>></div>

		</div>

		<div class="bp-line-annual"><span class="amount line-annual-display"><?= number_format((float)$ln['annual_amount'], 0); ?></span><small>annual RWF</small></div>

	</div>

	<?php } ?>

	</div>

</div>

<?php } ?>

<div class="bp-nav-footer">

	<button type="button" class="btn btn-outline-secondary btnPrev" data-prev="setup"><i class="fa fa-arrow-left"></i> Back to Setup</button>

	<button type="button" class="btn btn-primary btnNext" data-next="monthly">Continue to Monthly spread <i class="fa fa-arrow-right"></i></button>

</div>

</div>



<!-- MONTHLY SPREAD -->

<div class="bp-panel" id="panel-monthly">

<div class="bp-panel-help"><i class="fa fa-calendar-alt"></i> <strong>Step 3 — Cash-flow phasing.</strong> International schools often align spending with terms (Sep–Dec, Jan–Apr, May–Jul). Enter monthly amounts here to reflect when income is received and expenses are paid.</div>

<div class="table-responsive"><table class="table table-sm table-bordered bp-cash-table">

<thead><tr><th style="text-align:left">Category</th><?php foreach ($monthLabels as $ml) { ?><th><?= $ml; ?></th><?php } ?><th>Total</th></tr></thead>

<tbody>

<?php foreach ($sections as $sec) {

	foreach ($sec['lines'] as $ln) {

		if ((int)$ln['is_editable'] !== 1) continue;

		$lid = (int)$ln['id'];

		$ro = $canEdit ? '' : 'readonly disabled';

?>

<tr class="monthly-spread-row" data-line-id="<?= $lid; ?>">

	<td style="text-align:left"><small class="text-muted"><?= esc($ln['line_key'] ?? ''); ?></small> <?= esc($ln['category']); ?></td>

	<?php foreach ($months as $m) { ?><td><input type="number" step="0.01" class="form-control form-control-sm spread-month inp-month" data-line="<?= $lid; ?>" data-month="<?= $m; ?>" value="<?= esc($ln['months'][$m] ?? 0); ?>" style="width:72px" <?= $ro; ?>></td><?php } ?>

	<td class="spread-row-total font-weight-bold">0</td>

</tr>

<?php } } ?>

</tbody></table></div>

<div class="bp-nav-footer">

	<button type="button" class="btn btn-outline-secondary btnPrev" data-prev="plan"><i class="fa fa-arrow-left"></i> Back to Budget plan</button>

	<button type="button" class="btn btn-primary btnNext" data-next="summary">Continue to Summary <i class="fa fa-arrow-right"></i></button>

</div>

</div>



<!-- SUMMARY -->

<div class="bp-panel" id="panel-summary">

<div class="bp-panel-help"><i class="fa fa-check-double"></i> <strong>Step 4 — Review &amp; submit.</strong> Confirm income covers expenses (or document planned deficit funding). After submission, Procurement → Budget Manager → Deputy Director of Finance will review before the budget becomes spendable.</div>

<div class="row">

<div class="col-lg-6"><div class="card border-0 shadow-sm"><div class="card-body">

<h6 class="font-weight-bold mb-3">Budget by section</h6>

<div class="bp-summary-chart" id="summaryChart"></div>

</div></div></div>

<div class="col-lg-6"><div class="card border-0 shadow-sm"><div class="card-body">

<h6 class="font-weight-bold mb-3">Submission checklist</h6>

<ul class="small text-muted pl-3 mb-3">

<li>Enrollment and opening cash recorded on Setup tab</li>

<li>All major income streams (tuition, boarding, other) entered</li>

<li>Staff costs, utilities, and learning resources budgeted</li>

<li>Monthly spread reflects term timing where relevant</li>

<li>Surplus/deficit is intentional and documented in notes</li>

</ul>

<div class="alert alert-<?= (float)$budget['surplus_deficit'] >= 0 ? 'success' : 'warning'; ?> mb-3" id="summaryAlert">

	Surplus / deficit: <strong id="summarySurplus"><?= number_format((float)$budget['surplus_deficit'], 0); ?></strong> RWF

</div>

<?php if ($canEdit) { ?>

<button type="button" class="btn btn-success btn-block" id="btnSubmitSummary"><i class="fa fa-paper-plane"></i> Submit budget for approval</button>

<?php } ?>

</div></div></div>

</div>

<div class="bp-nav-footer">

	<button type="button" class="btn btn-outline-secondary btnPrev" data-prev="monthly"><i class="fa fa-arrow-left"></i> Back to Monthly spread</button>

	<span></span>

</div>

</div>



</form>

</div>



<div class="bp-sidebar">

	<?= view('pages/budget/partials/process_guide', ['ctx' => 'prep']); ?>

	<div class="mt-3 small text-muted">

		<p class="mb-1"><i class="fa fa-unlock-alt text-success"></i> After approval, staff spend via <a href="<?= base_url('budget/cash_request_form'); ?>">cash requests</a> linked to budget lines.</p>

	</div>

</div>

</div>

</div>



<script src="<?= base_url('assets/js/budget-workspace.js'); ?>?v=2"></script>

<script>BudgetWorkspace.init({

	budgetId: <?= (int)$budget['id']; ?>,

	canEdit: <?= $canEdit ? 'true' : 'false'; ?>,

	saveUrl: '<?= base_url('budget/save_budget_lines'); ?>',

	setupUrl: '<?= base_url('budget/save_budget_setup'); ?>',

	submitUrl: '<?= base_url('budget/submit_budget'); ?>',

	redirectUrl: '<?= base_url('budget/prepare'); ?>'

});</script>

