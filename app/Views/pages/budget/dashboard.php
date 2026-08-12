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

<link href="<?= base_url('assets/css/budget-preparation.css'); ?>?v=6" rel="stylesheet">
<style>
.bd-ai-card{border:1px solid #c5d8f0;border-radius:10px;overflow:hidden;background:#fff;}
.bd-ai-head{display:flex;justify-content:space-between;align-items:center;padding:.75rem 1rem;background:linear-gradient(90deg,#eef5ff,#f8fbff);}
.bd-ai-score{font-size:1.6rem;font-weight:700;line-height:1;}
.bd-ai-badge{display:inline-block;padding:.15rem .55rem;border-radius:999px;font-size:.72rem;font-weight:600;text-transform:uppercase;}
.bd-ai-badge.high{background:#fde8e8;color:#b42318;}
.bd-ai-badge.medium{background:#fef3c7;color:#92400e;}
.bd-ai-badge.low{background:#e7f8ef;color:#067647;}
.bd-ai-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem;}
@media (max-width:768px){.bd-ai-grid{grid-template-columns:1fr;}}
.bd-ai-list{margin:0;padding-left:1.1rem;}
.bd-ai-list li{margin-bottom:.35rem;}
.bd-ai-follow{background:#f4f8ff;border-left:3px solid #2f6fed;padding:.65rem .85rem;border-radius:0 6px 6px 0;}
</style>

<div class="bp-hero mb-3">
	<h2><i class="fa fa-chart-line"></i> Budget Dashboard</h2>
	<p class="bp-meta mb-0"><?= esc($branch_label ?? 'Your school'); ?></p>
</div>

<?php if (!empty($ai_enabled) || !empty($gemini_enabled)) { ?>
<div class="bd-ai-card mb-4" id="aiPanel">
	<div class="bd-ai-head">
		<div>
			<strong><i class="fa fa-robot text-info"></i> Smart follow-up</strong>
		</div>
		<button type="button" class="btn btn-sm btn-outline-info" id="btnRunAi"><i class="fa fa-sync"></i> Refresh</button>
	</div>
	<div class="card-body" id="aiBody">
		<p class="text-muted mb-0 small"><i class="fa fa-spinner fa-spin"></i> Preparing smart follow-up…</p>
	</div>
</div>
<?php } ?>

<?php
$fp = $fees_projection ?? null;
if (is_array($fp)) {
	$fpOk = !empty($fp['success']);
?>
<div class="card mb-4 border-success">
	<div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center">
		<span><i class="fa fa-graduation-cap text-success"></i> School fees projection <small class="text-muted">(fees settings × boarding/day students)</small></span>
		<?php if ($fpOk) { ?>
		<small class="text-muted"><?= esc($fp['academic_year_title'] ?? ''); ?> · <?= (int)($fp['total_students'] ?? 0); ?> students (<?= (int)($fp['boarding_students'] ?? 0); ?> boarding · <?= (int)($fp['day_students'] ?? 0); ?> day)</small>
		<?php } ?>
	</div>
	<div class="card-body">
		<?php if (!$fpOk) { ?>
		<p class="text-muted mb-0 small"><i class="fa fa-info-circle"></i> <?= esc($fp['error'] ?? 'Configure school fees and enroll students to project income.'); ?></p>
		<?php } else { ?>
		<div class="bp-kpi-row mb-3">
			<div class="bp-kpi income"><label>Term I</label><strong><?= number_format((float)$fp['term_1'], 0); ?></strong><small class="text-muted d-block">RWF</small></div>
			<div class="bp-kpi income"><label>Term II</label><strong><?= number_format((float)$fp['term_2'], 0); ?></strong><small class="text-muted d-block">RWF</small></div>
			<div class="bp-kpi income"><label>Term III</label><strong><?= number_format((float)$fp['term_3'], 0); ?></strong><small class="text-muted d-block">RWF</small></div>
			<div class="bp-kpi surplus pos"><label>Annual school fees</label><strong><?= number_format((float)$fp['annual'], 0); ?></strong><small class="text-muted d-block">RWF · T1+T2+T3</small></div>
		</div>
		<?php if (!empty($fp['breakdown'])) { ?>
		<div class="table-responsive">
			<table class="table table-sm table-hover mb-0">
				<thead class="thead-light"><tr><th>Class</th><th>Term</th><th class="text-right">Boarding</th><th class="text-right">Day</th><th class="text-right">Total</th></tr></thead>
				<tbody>
				<?php foreach (array_slice($fp['breakdown'], 0, 40) as $b) { ?>
				<tr>
					<td><?= esc($b['class'] ?? ''); ?></td>
					<td>T<?= (int)($b['term'] ?? 0); ?></td>
					<td class="text-right"><?= (int)($b['boarding_students'] ?? 0); ?> × <?= number_format((float)($b['boarding_rate'] ?? 0), 0); ?></td>
					<td class="text-right"><?= (int)($b['day_students'] ?? 0); ?> × <?= number_format((float)($b['day_rate'] ?? 0), 0); ?></td>
					<td class="text-right font-weight-bold"><?= number_format((float)($b['total'] ?? 0), 0); ?></td>
				</tr>
				<?php } ?>
				</tbody>
			</table>
		</div>
		<?php if (count($fp['breakdown']) > 40) { ?>
		<p class="small text-muted mb-0 mt-2">Showing first 40 class/term lines of <?= count($fp['breakdown']); ?>.</p>
		<?php } ?>
		<?php } ?>
		<?php } ?>
	</div>
</div>
<?php } ?>

<?php if (!empty($is_central) && !empty($fees_projection_branches)) { ?>
<div class="card mb-4">
	<div class="card-header">School fees projection — all branches</div>
	<div class="card-body p-0 table-responsive">
		<table class="table table-sm mb-0">
			<thead class="thead-light"><tr><th>Branch</th><th class="text-right">Students</th><th class="text-right">Boarding</th><th class="text-right">Day</th><th class="text-right">Term I</th><th class="text-right">Term II</th><th class="text-right">Term III</th><th class="text-right">Annual</th></tr></thead>
			<tbody>
			<?php
			$sumAnnual = 0;
			foreach ($fees_projection_branches as $fb) {
				$sumAnnual += (float)($fb['annual'] ?? 0);
			?>
			<tr>
				<td><strong><?= esc($fb['display_name'] ?? ''); ?></strong><?php if (empty($fb['success']) && !empty($fb['error'])) { ?> <small class="text-muted">(<?= esc($fb['error']); ?>)</small><?php } ?></td>
				<td class="text-right"><?= (int)($fb['total_students'] ?? 0); ?></td>
				<td class="text-right"><?= (int)($fb['boarding_students'] ?? 0); ?></td>
				<td class="text-right"><?= (int)($fb['day_students'] ?? 0); ?></td>
				<td class="text-right"><?= number_format((float)($fb['term_1'] ?? 0), 0); ?></td>
				<td class="text-right"><?= number_format((float)($fb['term_2'] ?? 0), 0); ?></td>
				<td class="text-right"><?= number_format((float)($fb['term_3'] ?? 0), 0); ?></td>
				<td class="text-right font-weight-bold"><?= number_format((float)($fb['annual'] ?? 0), 0); ?></td>
			</tr>
			<?php } ?>
			</tbody>
			<tfoot class="thead-light"><tr><th colspan="7" class="text-right">Group total</th><th class="text-right"><?= number_format($sumAnnual, 0); ?></th></tr></tfoot>
		</table>
	</div>
</div>
<?php } ?>

<?php if (!empty($is_central) && !empty($branch_stats)) { ?>
<div class="card mb-3"><div class="card-header">All branches</div><div class="card-body p-0">
<table class="table table-sm mb-0"><thead><tr><th>Branch</th><th>Draft</th><th>In approval</th><th>Approved</th><th>Active requests</th><th>Awaiting payment</th></tr></thead><tbody>
<?php foreach ($branch_stats as $bs) { ?>
<tr><td><strong><?= esc($bs['display_name']); ?></strong></td>
<td><?= (int)$bs['draft_budgets']; ?></td>
<td><?= (int)($bs['submitted_budgets'] ?? 0); ?></td>
<td><?= (int)($bs['approved_budgets'] ?? 0); ?></td>
<td><?= (int)$bs['pending_cash']; ?></td>
<td><?= (int)$bs['awaiting_payment']; ?></td></tr>
<?php } ?>
</tbody></table></div></div>
<?php } ?>

<?php if (!empty($financials)) {
	$f = $financials;
?>
<div class="bp-kpi-row mb-4">
	<div class="bp-kpi"><label>Total budget (expenses)</label><strong><?= number_format((float)$f['total_budget'], 0); ?></strong><small class="text-muted d-block">RWF</small></div>
	<div class="bp-kpi expense"><label>Total used</label><strong><?= number_format((float)$f['total_actual'], 0); ?></strong><small class="text-muted d-block">RWF</small></div>
	<div class="bp-kpi <?= (float)$f['variance'] >= 0 ? 'surplus pos' : 'surplus neg'; ?>"><label>Variance</label><strong><?= number_format((float)$f['variance'], 0); ?></strong><small class="text-muted d-block"><?= (float)$f['variance_pct']; ?>% remaining</small></div>
	<div class="bp-kpi income"><label>Fee revenue (plan)</label><strong><?= number_format((float)$f['total_income'], 0); ?></strong><small class="text-muted d-block">RWF</small></div>
	<div class="bp-kpi"><label>Period</label><strong class="small"><?= esc($f['period_title'] ?: '—'); ?></strong></div>
</div>
<?php if (empty($f['budget'])) { ?>
<div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between">
	<span><i class="fa fa-exclamation-triangle"></i> No approved budget yet</span>
	<a href="<?= base_url('budget/prepare'); ?>" class="btn btn-sm btn-warning">Prepare budget</a>
</div>
<?php } elseif (!empty($f['line_variances'])) { ?>
<div class="card mb-4">
	<div class="card-header d-flex justify-content-between align-items-center">
		<span><i class="fa fa-table"></i> Budget utilization by line</span>
		<small class="text-muted">Budget · Used · Variance (B − C)</small>
	</div>
	<div class="card-body p-0 table-responsive">
		<table class="table table-sm table-hover mb-0">
			<thead class="thead-light"><tr><th>Section</th><th>Budget line</th><th class="text-right">Budget</th><th class="text-right">Used</th><th class="text-right">Variance</th><th class="text-right">%</th></tr></thead>
			<tbody>
			<?php foreach ($f['line_variances'] as $lv) {
				$rowClass = !empty($lv['is_total_row']) ? 'font-weight-bold table-secondary' : '';
				$varClass = (float)$lv['variance'] < 0 ? 'text-danger' : 'text-success';
			?>
			<tr class="<?= $rowClass; ?>">
				<td><?= esc($lv['section']); ?></td>
				<td><?= esc($lv['category']); ?></td>
				<td class="text-right"><?= number_format((float)$lv['budget'], 0); ?></td>
				<td class="text-right"><?= number_format((float)$lv['used'], 0); ?></td>
				<td class="text-right <?= $varClass; ?>"><?= number_format((float)$lv['variance'], 0); ?></td>
				<td class="text-right"><?= (float)$lv['utilization_pct']; ?>%</td>
			</tr>
			<?php } ?>
			</tbody>
		</table>
	</div>
</div>
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
<?php if (!empty($budget_view_only)) { ?>
<span class="badge badge-secondary mb-3 p-2"><i class="fa fa-eye"></i> View only</span>
<?php } ?>

<?php if (!empty($budget_pipeline) || !empty($cash_pipeline)) { ?>
<div class="card mb-3">
	<div class="card-header"><i class="fa fa-project-diagram"></i> Approval progress</div>
	<div class="card-body">
		<?php if (!empty($budget_pipeline)) { ?>
		<h6 class="font-weight-bold small text-uppercase text-muted">Annual budgets</h6>
		<div class="d-flex flex-wrap mb-3">
			<?php
			$bpLabels = [
				'DRAFT' => 'Draft',
				'SUBMITTED' => 'Submitted',
				'PROCUREMENT_REVIEW' => 'Procurement',
				'BUDGET_MANAGER_REVIEW' => 'Budget Mgr',
				'DEPUTY_DIRECTOR_REVIEW' => 'Dir. Finance',
				'APPROVED' => 'Approved',
				'RETURNED' => 'Returned',
			];
			foreach ($bpLabels as $st => $lab) {
				$n = (int) ($budget_pipeline[$st] ?? 0);
				if ($n < 1 && !in_array($st, ['DRAFT', 'APPROVED', 'SUBMITTED'], true)) continue;
			?>
			<div class="border rounded px-3 py-2 mr-2 mb-2 text-center" style="min-width:88px;">
				<div class="h5 mb-0"><?= $n; ?></div>
				<small class="text-muted"><?= esc($lab); ?></small>
			</div>
			<?php } ?>
		</div>
		<?php } ?>
		<?php if (!empty($cash_pipeline)) { ?>
		<h6 class="font-weight-bold small text-uppercase text-muted">Cash requests</h6>
		<div class="d-flex flex-wrap">
			<?php
			$cpLabels = [
				'SUBMITTED' => 'Submitted',
				'HEADTEACHER_APPROVED' => 'Headteacher',
				'PROCUREMENT_APPROVED' => 'Procurement',
				'BUDGET_APPROVED' => 'Budget OK',
				'FINANCE_AUTHORIZED' => 'Finance auth',
				'PAID' => 'Paid',
			];
			foreach ($cpLabels as $st => $lab) {
				$n = (int) ($cash_pipeline[$st] ?? 0);
			?>
			<div class="border rounded px-3 py-2 mr-2 mb-2 text-center" style="min-width:88px;">
				<div class="h5 mb-0"><?= $n; ?></div>
				<small class="text-muted"><?= esc($lab); ?></small>
			</div>
			<?php } ?>
		</div>
		<?php } ?>
	</div>
</div>
<?php } ?>

<div class="card">
	<div class="card-header">Quick actions</div>
	<div class="card-body">
		<?php if (!empty($can_prepare_budget) && budget_menu_any(['budget_prepare'])) { ?>
		<a class="btn btn-primary mr-2 mb-2" href="<?= base_url('budget/prepare'); ?>"><i class="fa fa-edit"></i> Prepare annual budget</a>
		<?php } ?>
		<?php if (empty($budget_view_only) && budget_menu_any(['budget_cash_requests']) && budget_permission_allowed('cash_request.create')) { ?>
		<a class="btn btn-success mr-2 mb-2" href="<?= base_url('budget/cash_request_form'); ?>"><i class="fa fa-plus"></i> New cash request</a>
		<?php } ?>
		<?php if (empty($budget_view_only) && budget_menu_any(['budget_pending', 'budget_procurement', 'budget_availability', 'budget_final_approval'])) { ?>
		<a class="btn btn-outline-warning mr-2 mb-2" href="<?= base_url('budget/requests?tab=pending'); ?>"><i class="fa fa-tasks"></i> Approve requests</a>
		<?php } elseif (!empty($budget_view_only) && budget_menu_any(['budget_cash_requests', 'budget_pending'])) { ?>
		<a class="btn btn-outline-info mr-2 mb-2" href="<?= base_url('budget/requests'); ?>"><i class="fa fa-stream"></i> View requests &amp; approvals</a>
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

<?php if (!empty($ai_enabled) || !empty($gemini_enabled)) { ?>
<script>
(function(){
	function esc(s){
		var d=document.createElement('div');
		d.textContent=s==null?'':String(s);
		return d.innerHTML;
	}
	function render(a){
		var score = a.health_score!=null ? a.health_score : '—';
		var pri = (a.priority||'medium').toLowerCase();
		var html = '<div class="d-flex align-items-center mb-3">'
			+'<div class="mr-3"><div class="bd-ai-score">'+(score)+'<small class="text-muted" style="font-size:.75rem;">/100</small></div>'
			+'<span class="bd-ai-badge '+esc(pri)+'">'+esc(pri)+' priority</span></div>'
			+'<p class="mb-0">'+esc(a.summary||'')+'</p></div>';
		html += '<div class="bd-ai-grid">';
		html += '<div>';
		if (a.alerts && a.alerts.length) {
			html += '<p class="mb-1 font-weight-bold small text-danger"><i class="fa fa-exclamation-triangle"></i> Alerts</p><ul class="bd-ai-list small">'+a.alerts.map(function(x){return '<li>'+esc(x)+'</li>';}).join('')+'</ul>';
		}
		if (a.branches_to_watch && a.branches_to_watch.length) {
			html += '<p class="mb-1 mt-3 font-weight-bold small"><i class="fa fa-school"></i> Branches to watch</p><ul class="bd-ai-list small">'+a.branches_to_watch.map(function(x){return '<li>'+esc(x)+'</li>';}).join('')+'</ul>';
		}
		html += '</div><div>';
		if (a.recommendations && a.recommendations.length) {
			html += '<p class="mb-1 font-weight-bold small"><i class="fa fa-lightbulb"></i> Recommendations</p><ul class="bd-ai-list small">'+a.recommendations.map(function(x){return '<li>'+esc(x)+'</li>';}).join('')+'</ul>';
		}
		var follow = a.follow_up_actions || [];
		if (follow.length) {
			html += '<div class="bd-ai-follow mt-3"><p class="mb-1 font-weight-bold small"><i class="fa fa-tasks"></i> This week — follow up</p><ul class="bd-ai-list small mb-0">'+follow.map(function(x){return '<li>'+esc(x)+'</li>';}).join('')+'</ul></div>';
		}
		html += '</div></div>';
		if (a.cashflow_outlook) html += '<p class="small text-muted mt-3 mb-0"><i class="fa fa-chart-line"></i> '+esc(a.cashflow_outlook)+'</p>';
		return html;
	}
	function runAi(){
		var body = document.getElementById('aiBody');
		if (!body) return;
		body.innerHTML = '<p class="text-muted mb-0 small"><i class="fa fa-spinner fa-spin"></i> Analyzing budgets &amp; cash flow…</p>';
		fetch('<?= base_url('budget/dashboard_ai_json'); ?>')
			.then(function(r){ return r.json(); })
			.then(function(d){
				if (d.error) { body.innerHTML = '<div class="alert alert-warning mb-0">'+esc(d.error)+'</div>'; return; }
				body.innerHTML = render(d.analysis || {});
			})
			.catch(function(){ body.innerHTML = '<div class="alert alert-danger mb-0">Could not reach AI service.</div>'; });
	}
	var btn = document.getElementById('btnRunAi');
	if (btn) btn.addEventListener('click', runAi);
	<?php if (!empty($ai_auto) || !empty($gemini_auto)) { ?>runAi();<?php } ?>
})();
</script>
<?php } ?>
