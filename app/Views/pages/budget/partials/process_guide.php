<?php
/** International school budget & cash-flow process guide (reusable partial) */
$compact = !empty($compact);
$ctx = $ctx ?? 'full'; // full | prep | execution
?>
<div class="bp-process-guide <?= $compact ? 'bp-process-compact' : ''; ?>">
<?php if (!$compact) { ?>
<div class="bp-process-head">
	<i class="fa fa-route"></i>
	<div>
		<strong>How budgeting works</strong>
		<span class="text-muted d-block small">Aligned with international school finance practice</span>
	</div>
</div>
<?php } ?>

<div class="bp-process-phases">
	<div class="bp-phase">
		<div class="bp-phase-num">1</div>
		<div class="bp-phase-body">
			<h6>Annual planning</h6>
			<p class="small text-muted mb-0">Head of school / finance team prepares the branch budget: enrollment assumptions, income (fees, grants), and expense lines (salaries, utilities, learning resources). Use <strong>Setup → Budget plan → Monthly spread → Summary</strong>, then submit for approval.</p>
		</div>
	</div>
	<div class="bp-phase">
		<div class="bp-phase-num">2</div>
		<div class="bp-phase-body">
			<h6>Budget approval chain</h6>
			<p class="small text-muted mb-0">Submitted budgets pass: <em>Procurement review</em> → <em>Budget Manager</em> (checks totals &amp; line structure) → <em>Deputy Director of Finance</em> (final sign-off). Only <strong>APPROVED</strong> budgets can be spent against.</p>
		</div>
	</div>
	<div class="bp-phase">
		<div class="bp-phase-num">3</div>
		<div class="bp-phase-body">
			<h6>Spending via cash requests</h6>
			<p class="small text-muted mb-0">Staff raise a <strong>cash request</strong> linked to an approved budget line (e.g. stationery, repairs). Attach supporting documents — invoice, quotation, memo. The system tracks <em>available</em> vs <em>committed</em> vs <em>paid</em> on each line.</p>
		</div>
	</div>
	<div class="bp-phase">
		<div class="bp-phase-num">4</div>
		<div class="bp-phase-body">
			<h6>Request approval &amp; payment</h6>
			<p class="small text-muted mb-0">Cash requests flow: Headteacher → Procurement Manager → <strong>Budget Manager</strong> (confirms budget availability) → <strong>Deputy Director of Finance</strong> (authorizes payment) → Accountant pays → receipt filed. Each step is logged with comments.</p>
		</div>
	</div>
</div>

<?php if ($ctx === 'full' && !$compact) { ?>
<div class="bp-roles mt-3">
	<div class="row small">
		<div class="col-md-6 mb-2">
			<span class="bp-role-badge budget-mgr"><i class="fa fa-user-check"></i> Budget Manager</span>
			Reviews budget submissions and, on each cash request, confirms the line has sufficient uncommitted funds before payment is authorized.
		</div>
		<div class="col-md-6 mb-2">
			<span class="bp-role-badge finance-dir"><i class="fa fa-stamp"></i> Deputy Director of Finance</span>
			Final approver for the annual budget and for high-value cash requests; authorizes the accountant to pay after budget availability is confirmed.
		</div>
	</div>
</div>
<?php } ?>
</div>
