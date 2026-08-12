<link href="<?= base_url('assets/css/budget-preparation.css'); ?>?v=9" rel="stylesheet">

<?php
$statusBadge = $budget['status'] === 'DRAFT' ? 'secondary' : ($budget['status'] === 'APPROVED' ? 'success' : 'warning');
$canEdit = isset($can_edit) ? (bool) $can_edit : in_array($budget['status'], ['DRAFT', 'RETURNED'], true);
$isFinanceAdjust = !empty($is_finance_adjust);
$canSubmit = isset($can_submit) ? (bool) $can_submit : ($canEdit && in_array($budget['status'], ['DRAFT', 'RETURNED'], true));
$canAddLines = !empty($can_add_lines);
$canManageStructure = $canAddLines && empty($budget_branch_fill);
$sectionOptions = $section_options ?? ['INCOME', 'OPERATING EXPENSES', 'ADMINISTRATIVE COSTS', 'FINANCE COSTS'];
$academicYear = $setup['academic_year'] ?? '';
$branchFillMode = !empty($budget_branch_fill);
$enrollment = (int) ($setup['enrollment'] ?? 0);
?>

<div class="budget-workspace bp-ui-v2" id="budgetWorkspace" data-budget-id="<?= (int)$budget['id']; ?>">

<div class="bp-hero">
	<div class="d-flex flex-wrap justify-content-between align-items-start">
		<div>
			<h2><i class="fa fa-chart-pie"></i> <?= esc($budget['title']); ?></h2>
			<p class="bp-meta mb-0"><?= esc($branch_label); ?> · <?= esc($academicYear ?: ($budget['period_title'] ?? 'Annual')); ?> · <?= esc($budget['currency'] ?? 'RWF'); ?>
			<span class="badge badge-<?= $statusBadge; ?> ml-2"><?= esc($budget['status']); ?></span>
			<?php if ($isFinanceAdjust) { ?>
			<span class="badge badge-warning ml-1">Director of Finance edit</span>
			<?php } ?>
			</p>
		</div>
		<a href="<?= base_url('budget/prepare'); ?>" class="btn btn-sm btn-light"><i class="fa fa-arrow-left"></i> All budgets</a>
	</div>
	<div class="bp-kpi-row bp-kpi-main">
		<div class="bp-kpi income"><label>Annual income</label><strong id="kpiIncome"><?= number_format((float)$budget['total_income'], 0); ?></strong><small>RWF</small></div>
		<div class="bp-kpi expense"><label>Annual expenses</label><strong id="kpiExpense"><?= number_format((float)$budget['total_expenses'], 0); ?></strong><small>RWF</small></div>
		<div class="bp-kpi surplus <?= (float)$budget['surplus_deficit'] >= 0 ? 'pos' : 'neg'; ?>"><label>Surplus / deficit</label><strong id="kpiSurplus"><?= number_format((float)$budget['surplus_deficit'], 0); ?></strong><small>RWF</small></div>
		<div class="bp-kpi"><label>Completion</label><strong id="kpiProgress">0%</strong><small>lines with amounts</small></div>
		<?php if ($enrollment > 0) { ?>
		<div class="bp-kpi"><label>Enrollment</label><strong id="kpiEnrollment"><?= number_format($enrollment); ?></strong><small>students</small></div>
		<?php } ?>
	</div>
	<div class="bp-kpi-row bp-kpi-terms" id="kpiTermStrip">
		<div class="bp-kpi-term t1"><label>Term I net</label><strong id="kpiT1Net">0</strong></div>
		<div class="bp-kpi-term t2"><label>Term II net</label><strong id="kpiT2Net">0</strong></div>
		<div class="bp-kpi-term t3"><label>Term III net</label><strong id="kpiT3Net">0</strong></div>
	</div>
	<div class="bp-progress mt-2"><div class="bp-progress-bar" id="progressBar" style="width:0%"></div></div>
</div>

<div class="bp-stepper" id="bpStepper">
	<button type="button" class="bp-step active" data-tab="setup" data-step="1">
		<span class="bp-step-icon">1</span>
		<span><span class="bp-step-label">Setup</span><span class="bp-step-desc">Academic year &amp; assumptions</span></span>
	</button>
	<button type="button" class="bp-step" data-tab="plan" data-step="2">
		<span class="bp-step-icon">2</span>
		<span><span class="bp-step-label">Three-term budget</span><span class="bp-step-desc">Term I · II · III amounts</span></span>
	</button>
	<button type="button" class="bp-step" data-tab="summary" data-step="3">
		<span class="bp-step-icon">3</span>
		<span><span class="bp-step-label">Summary &amp; submit</span><span class="bp-step-desc">Review full year</span></span>
	</button>
</div>

<div class="bp-toolbar">
	<div class="bp-save-status saved" id="saveStatus"><i class="fa fa-check"></i> All changes saved</div>
	<div>
		<?php if ($canAddLines) { ?>
		<button type="button" class="btn btn-outline-warning btn-sm mr-1" id="btnAddBudgetLine" title="Add section title or budget row"><i class="fa fa-plus"></i> Add title / row</button>
		<?php } ?>
		<?php if ($canEdit) { ?>
		<?php if ($isFinanceAdjust) { ?>
		<span class="badge badge-warning mr-2 p-2"><i class="fa fa-user-tie"></i> Finance adjust — status stays <?= esc($budget['status']); ?></span>
		<button type="button" class="btn btn-outline-danger btn-sm mr-1" id="btnResetEmptyAmounts"
			title="Restore all line items and clear amounts so you can fill them. School Fees stays from fees × students.">
			<i class="fa fa-eraser"></i> Restore empty lines
		</button>
		<?php } elseif (!$branchFillMode) { ?>
		<button type="button" class="btn btn-outline-secondary btn-sm mr-1" id="btnResetEmptyAmounts"
			title="Restore all line items with blank amounts (School Fees auto-fills).">
			<i class="fa fa-eraser"></i> Restore empty lines
		</button>
		<?php } else { ?>
		<span class="badge badge-info mr-2 p-2">Branch fill — lines from master; enter your term amounts</span>
		<?php } ?>
		<button type="button" class="btn btn-primary btn-sm" id="btnSave"><i class="fa fa-save"></i> Save</button>
		<?php if ($canSubmit) { ?>
		<button type="button" class="btn btn-success btn-sm" id="btnSubmit"><i class="fa fa-paper-plane"></i> Submit</button>
		<?php } ?>
		<?php } else { ?>
		<span class="badge badge-info p-2">Read-only — <?= esc($budget['status']); ?></span>
		<?php } ?>
	</div>
</div>

<div class="bp-layout">
<div class="bp-main">

<form id="frmBudgetWorkspace">
<input type="hidden" name="budget_id" value="<?= (int)$budget['id']; ?>">

<!-- SETUP -->
<div class="bp-panel active" id="panel-setup">
<div class="bp-panel-help"><i class="fa fa-info-circle"></i> <strong>Step 1 — Academic year setup.</strong> Record enrollment, opening cash, and planning notes before entering term amounts.</div>
<div class="bp-setup-grid">
	<div class="bp-setup-card"><label class="small font-weight-bold text-muted">BRANCH</label><div class="h6 mb-0"><?= esc($branch_label); ?></div></div>
	<div class="bp-setup-card"><label class="small font-weight-bold text-muted">PERIOD</label><div class="h6 mb-0"><?= esc($budget['period_title'] ?? '—'); ?></div></div>
	<div class="bp-setup-card"><label class="small font-weight-bold text-muted">CURRENCY</label><div class="h6 mb-0"><?= esc($budget['currency'] ?? 'RWF'); ?></div></div>
</div>
<div class="card border-0 shadow-sm mt-3"><div class="card-body">
	<div class="row">
		<div class="col-md-6 form-group"><label class="font-weight-bold">Budget title</label><input class="form-control" name="setup_title" id="setupTitle" value="<?= esc($budget['title']); ?>" <?= $canEdit ? '' : 'readonly'; ?>></div>
		<div class="col-md-3 form-group"><label class="font-weight-bold">Academic year</label><input class="form-control" name="academic_year" id="setupAcademicYear" value="<?= esc($academicYear); ?>" placeholder="2025-26" <?= $canEdit ? '' : 'readonly'; ?>></div>
		<div class="col-md-3 form-group"><label class="font-weight-bold">Expected enrollment</label><input type="number" class="form-control" name="enrollment" id="setupEnrollment" value="<?= esc($setup['enrollment'] ?? ''); ?>" <?= $canEdit ? '' : 'readonly'; ?>></div>
		<div class="col-md-3 form-group"><label class="font-weight-bold">Opening cash (RWF)</label><input type="number" step="0.01" class="form-control" name="opening_cash" id="setupOpeningCash" value="<?= esc($setup['opening_cash'] ?? ''); ?>" <?= $canEdit ? '' : 'readonly'; ?>></div>
		<div class="col-12 form-group"><label class="font-weight-bold">Planning notes</label><textarea class="form-control" rows="3" name="planning_notes" id="setupNotes" <?= $canEdit ? '' : 'readonly'; ?>><?= esc($setup['planning_notes'] ?? ''); ?></textarea></div>
	</div>
	<?php if ($canEdit) { ?>
	<button type="button" class="btn btn-outline-primary btn-sm" id="btnSaveSetup"><i class="fa fa-check"></i> Save setup</button>
	<?php } ?>
</div></div>
<div class="bp-nav-footer"><span></span><button type="button" class="btn btn-primary btnNext" data-next="plan">Continue to three-term budget <i class="fa fa-arrow-right"></i></button></div>
</div>

<!-- THREE-TERM BUDGET GRID -->
<div class="bp-panel" id="panel-plan">
<div class="bp-panel-help"><i class="fa fa-table"></i> <strong>Step 2 — Full-year budget by term.</strong> Enter RWF for Term I, Term II and Term III. <em>Annual = T1 + T2 + T3</em><?php if ($branchFillMode) { ?> — amounts reflect your branch only; master school defines the line list.<?php } ?>
	<?php if ($canEdit) { ?>
	<small class="d-block mt-1 text-muted"><i class="fa fa-sync"></i> School Fees auto-updates from fees settings × boarding/day students. Other lines stay blank until you enter amounts.</small>
	<?php } ?>
</div>

<?php foreach ($sections as $secKey => $sec) {
	if (empty($sec['lines'])) continue;
	$isIncome = !empty($sec['is_income']);
	$editableInSec = [];
	foreach ($sec['lines'] as $_ln) {
		if ((int)($_ln['is_total_row'] ?? 0) !== 1) {
			$editableInSec[] = (int)$_ln['id'];
		}
	}
?>
<div class="bp-section bp-term-section" data-section="<?= esc($secKey); ?>">
	<div class="bp-section-head <?= $isIncome ? 'income' : 'expense'; ?>">
		<span><i class="fa fa-<?= $isIncome ? 'arrow-down' : 'arrow-up'; ?>"></i> <?= esc($secKey); ?></span>
		<span class="d-flex align-items-center">
			<span class="bp-section-total mr-2" data-section-total="<?= esc($secKey); ?>">0 RWF</span>
			<?php if ($canAddLines) { ?>
			<button type="button" class="btn btn-sm btn-light bp-btn-add-line" data-section="<?= esc($secKey); ?>" title="Add row in this section"><i class="fa fa-plus"></i></button>
			<?php } ?>
		</span>
	</div>
	<div class="bp-lines-stack">
			<?php
			$editPos = 0;
			$editCount = count($editableInSec);
			foreach ($sec['lines'] as $ln) {
				$lid = (int)$ln['id'];
				$isTotal = (int)($ln['is_total_row'] ?? 0) === 1;
				$t1 = (float)($ln['term_1_amount'] ?? 0);
				$t2 = (float)($ln['term_2_amount'] ?? 0);
				$t3 = (float)($ln['term_3_amount'] ?? 0);
				$annual = (float)($ln['annual_amount'] ?? ($t1 + $t2 + $t3));
				if ($isTotal) { ?>
				<div class="bp-line-total total-row" data-section="<?= esc($secKey); ?>">
					<span><?= esc($ln['category']); ?></span>
					<strong class="line-annual-display"><?= number_format($annual, 0); ?></strong>
				</div>
				<?php continue; }
				$ro = $canEdit ? '' : 'readonly disabled';
				$catLower = strtolower(trim((string)($ln['category'] ?? '')));
				$isSchoolFees = strpos($catLower, 'school fee') !== false;
				$canMoveUp = $canManageStructure && $editPos > 0;
				$canMoveDown = $canManageStructure && $editPos < ($editCount - 1);
				$editPos++;
			?>
				<article class="budget-line bp-line-entry<?= $isSchoolFees ? ' is-school-fees' : ''; ?>"
					data-line-id="<?= $lid; ?>"
					data-income="<?= $isIncome ? '1' : '0'; ?>"
					data-section="<?= esc($secKey); ?>"
					data-category="<?= esc($catLower); ?>"
					<?= $canManageStructure ? 'draggable="true"' : ''; ?>>
					<div class="bp-line-top">
						<div class="bp-line-name">
							<strong><?= esc($ln['category']); ?></strong>
							<?php if ($isSchoolFees) { ?><span class="bp-chip auto">Auto</span><?php } ?>
						</div>
						<?php if ($canManageStructure) { ?>
						<div class="bp-line-actions">
							<button type="button" class="bp-icon-btn bp-drag-handle" title="Drag to reorder" draggable="false"><i class="fa fa-bars"></i></button>
							<button type="button" class="bp-icon-btn btn-move-line" data-dir="up" title="Move up" <?= $canMoveUp ? '' : 'disabled'; ?>><i class="fa fa-arrow-up"></i></button>
							<button type="button" class="bp-icon-btn btn-move-line" data-dir="down" title="Move down" <?= $canMoveDown ? '' : 'disabled'; ?>><i class="fa fa-arrow-down"></i></button>
							<?php if (!$isSchoolFees) { ?>
							<button type="button" class="bp-icon-btn danger btn-delete-line" title="Delete (also removes from child schools)"><i class="fa fa-trash"></i></button>
							<?php } ?>
						</div>
						<?php } ?>
					</div>
					<input type="hidden" class="calc-mode-input" name="lines[<?= $lid; ?>][calculation_mode]" value="term_sum">
					<input type="text" class="form-control form-control-sm bp-line-notes" name="lines[<?= $lid; ?>][assumptions]" value="<?= esc($ln['assumptions'] ?? ''); ?>" placeholder="Notes (optional)" <?= $ro; ?>>
					<div class="bp-term-grid">
						<label class="bp-term-field t1"><span>Term I</span>
							<input type="number" step="0.01" min="0" class="form-control form-control-sm text-right inp-term inp-term-1" name="lines[<?= $lid; ?>][term_1_amount]" value="<?= $t1 > 0 ? esc($t1) : ''; ?>" placeholder="0" <?= $ro; ?>>
						</label>
						<label class="bp-term-field t2"><span>Term II</span>
							<input type="number" step="0.01" min="0" class="form-control form-control-sm text-right inp-term inp-term-2" name="lines[<?= $lid; ?>][term_2_amount]" value="<?= $t2 > 0 ? esc($t2) : ''; ?>" placeholder="0" <?= $ro; ?>>
						</label>
						<label class="bp-term-field t3"><span>Term III</span>
							<input type="number" step="0.01" min="0" class="form-control form-control-sm text-right inp-term inp-term-3" name="lines[<?= $lid; ?>][term_3_amount]" value="<?= $t3 > 0 ? esc($t3) : ''; ?>" placeholder="0" <?= $ro; ?>>
						</label>
						<div class="bp-term-annual">
							<span>Annual</span>
							<strong class="line-annual-display"><?= number_format($annual, 0); ?></strong>
						</div>
					</div>
				</article>
			<?php } ?>
			<div class="bp-section-foot">
				<span>Section subtotal</span>
				<span class="bp-foot-terms">
					<span class="section-t1" data-section="<?= esc($secKey); ?>">0</span>
					<span class="section-t2" data-section="<?= esc($secKey); ?>">0</span>
					<span class="section-t3" data-section="<?= esc($secKey); ?>">0</span>
				</span>
				<strong data-section-total-foot="<?= esc($secKey); ?>">0</strong>
			</div>
	</div>
</div>
<?php } ?>

<div class="card border-primary mt-3"><div class="card-body py-2">
	<div class="row text-center small">
		<div class="col-3"><span class="text-muted">Term I total (expenses)</span><br><strong id="footTerm1">0</strong></div>
		<div class="col-3"><span class="text-muted">Term II total (expenses)</span><br><strong id="footTerm2">0</strong></div>
		<div class="col-3"><span class="text-muted">Term III total (expenses)</span><br><strong id="footTerm3">0</strong></div>
		<div class="col-3"><span class="text-muted">Full year expenses</span><br><strong class="text-danger" id="footAnnualExp">0</strong></div>
	</div>
</div></div>

<div class="bp-nav-footer">
	<button type="button" class="btn btn-outline-secondary btnPrev" data-prev="setup"><i class="fa fa-arrow-left"></i> Back</button>
	<button type="button" class="btn btn-primary btnNext" data-next="summary">Continue to summary <i class="fa fa-arrow-right"></i></button>
</div>
</div>

<!-- SUMMARY -->
<div class="bp-panel" id="panel-summary">
<div class="bp-panel-help"><i class="fa fa-check-double"></i> <strong>Step 3 — Review full year.</strong> Confirm annual income covers expenses across all three terms, then submit for approval.</div>
<div class="row">
<div class="col-lg-6"><div class="card border-0 shadow-sm"><div class="card-body">
<h6 class="font-weight-bold mb-3">Budget by section (annual)</h6>
<div class="bp-summary-chart" id="summaryChart"></div>
</div></div></div>
<div class="col-lg-6"><div class="card border-0 shadow-sm"><div class="card-body">
<h6 class="font-weight-bold mb-3">Term overview</h6>
<table class="table table-sm">
	<thead><tr><th></th><th class="text-right">Income</th><th class="text-right">Expenses</th><th class="text-right">Net</th></tr></thead>
	<tbody>
		<tr><td>Term I</td><td class="text-right text-success" id="sumT1Inc">0</td><td class="text-right text-danger" id="sumT1Exp">0</td><td class="text-right" id="sumT1Net">0</td></tr>
		<tr><td>Term II</td><td class="text-right text-success" id="sumT2Inc">0</td><td class="text-right text-danger" id="sumT2Exp">0</td><td class="text-right" id="sumT2Net">0</td></tr>
		<tr><td>Term III</td><td class="text-right text-success" id="sumT3Inc">0</td><td class="text-right text-danger" id="sumT3Exp">0</td><td class="text-right" id="sumT3Net">0</td></tr>
		<tr class="font-weight-bold table-light"><td>Full year</td><td class="text-right text-success" id="sumIncome">0</td><td class="text-right text-danger" id="sumExpense">0</td><td class="text-right" id="summarySurplus">0</td></tr>
	</tbody>
</table>
<?php if ($canSubmit) { ?>
<button type="button" class="btn btn-success btn-block mt-2" id="btnSubmitSummary"><i class="fa fa-paper-plane"></i> Submit budget for approval</button>
<p class="small text-muted mt-2 mb-0">Child/school budgets stay <strong>DRAFT</strong> until you submit. Approval requires all three: Procurement → Budget Manager → Director of Finance. You only need one amount filled to save; submit needs at least one line with an amount.</p>
<?php } elseif ($isFinanceAdjust) { ?>
<div class="alert alert-warning small mt-2 mb-0"><i class="fa fa-info-circle"></i> Director of Finance adjustment — status stays <strong><?= esc($budget['status']); ?></strong>. New submissions still need Procurement, Budget Manager, and Director of Finance.</div>
<?php } ?>
</div></div></div>
</div>
<div class="bp-nav-footer">
	<button type="button" class="btn btn-outline-secondary btnPrev" data-prev="plan"><i class="fa fa-arrow-left"></i> Back to budget grid</button>
	<span></span>
</div>
</div>

</form>
</div>

<div class="bp-sidebar">
	<?= view('pages/budget/partials/process_guide', ['ctx' => 'prep', 'compact' => true]); ?>
	<div class="mt-3 small text-muted"><p class="mb-0"><i class="fa fa-info-circle"></i> Cash requests spend against <strong>annual</strong> budget lines after approval.</p></div>
</div>
</div>
</div>

<?php if ($canAddLines) { ?>
<div class="modal fade" id="mdlAddBudgetLine" tabindex="-1" role="dialog">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<form class="modal-content bp-add-line-modal" id="frmAddBudgetLine">
			<div class="modal-header">
				<h5 class="modal-title"><i class="fa fa-plus-circle text-warning"></i> Add budget title / row</h5>
				<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body">
				<p class="small text-muted">Director of Finance can add section titles and line items. Amounts are optional — save even a single line.</p>
				<div class="bp-add-mode mb-3">
					<button type="button" class="bp-mode-chip is-active" data-mode="line"><i class="fa fa-list"></i> Budget row</button>
					<button type="button" class="bp-mode-chip" data-mode="section"><i class="fa fa-folder-plus"></i> New section title</button>
				</div>
				<input type="hidden" name="mode" id="addLineMode" value="line">
				<input type="hidden" name="budget_id" value="<?= (int) $budget['id']; ?>">
				<div class="form-group">
					<label class="font-weight-bold">Section</label>
					<select class="form-control" name="section_label" id="addLineSection">
						<?php foreach ($sectionOptions as $opt) { ?>
						<option value="<?= esc($opt); ?>"><?= esc($opt); ?></option>
						<?php } ?>
					</select>
					<input type="text" class="form-control mt-2 d-none" id="addLineSectionCustom" placeholder="Or type a new section title…">
				</div>
				<div class="form-group" id="addLineTitleWrap">
					<label class="font-weight-bold">Line title</label>
					<input type="text" class="form-control" name="category" id="addLineCategory" placeholder="e.g. Laboratory supplies" required>
				</div>
				<div class="form-group mb-0">
					<label class="font-weight-bold">Note <span class="text-muted font-weight-normal">(optional)</span></label>
					<input type="text" class="form-control" name="assumptions" id="addLineAssumptions" placeholder="Short note for this line">
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
				<button type="submit" class="btn btn-warning" id="btnAddLineSubmit"><i class="fa fa-plus"></i> Add</button>
			</div>
		</form>
	</div>
</div>
<?php } ?>

<script src="<?= base_url('assets/js/budget-workspace.js'); ?>?v=9"></script>
<script>BudgetWorkspace.init({
	budgetId: <?= (int)$budget['id']; ?>,
	canEdit: <?= $canEdit ? 'true' : 'false'; ?>,
	canAddLines: <?= $canAddLines ? 'true' : 'false'; ?>,
	canManageStructure: <?= !empty($canManageStructure) ? 'true' : 'false'; ?>,
	saveUrl: '<?= base_url('budget/save_budget_lines'); ?>',
	setupUrl: '<?= base_url('budget/save_budget_setup'); ?>',
	submitUrl: '<?= base_url('budget/submit_budget'); ?>',
	addLineUrl: '<?= base_url('budget/add_budget_line'); ?>',
	deleteLineUrl: '<?= base_url('budget/delete_budget_line'); ?>',
	moveLineUrl: '<?= base_url('budget/move_budget_line'); ?>',
	reorderLineUrl: '<?= base_url('budget/reorder_budget_lines'); ?>',
	fillExcelUrl: '<?= base_url('budget/fill_budget_from_excel'); ?>',
	fillSchoolFeesUrl: '<?= base_url('budget/fill_school_fees_income'); ?>',
	resetEmptyUrl: '<?= base_url('budget/reset_budget_empty_amounts'); ?>',
	redirectUrl: '<?= base_url('budget/prepare'); ?>'
});</script>
