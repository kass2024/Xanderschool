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
			<h6>Budget approval chain (per school)</h6>
			<p class="small text-muted mb-0">Master school defines the shared budget line list (Director of Finance can add section titles and rows). Each child school fills its own term amounts and stays <strong>DRAFT</strong> until submit. After submit, all three must approve: <em>Procurement</em> → <em>Budget Manager</em> → <em>Director of Finance</em>. Only then is the budget <strong>APPROVED</strong>. Approvers and the preparer (e.g. accountant) get SMS and email.</p>
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
			<p class="small text-muted mb-0">Cash requests flow: Headteacher → Procurement Manager → <strong>Budget Manager</strong> (confirms budget availability) → <strong>Director of Finance</strong> (authorizes payment) → Accountant pays → receipt filed. Each step is logged with comments.</p>
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
			<span class="bp-role-badge finance-dir"><i class="fa fa-stamp"></i> Director of Finance</span>
			Final of the three mandatory budget approvals; may also edit submitted/approved amounts and add budget titles/rows. Authorizes payment on cash requests after budget availability is confirmed.
		</div>
		<div class="col-md-12 mb-2">
			<span class="bp-role-badge"><i class="fa fa-bell"></i> Notifications</span>
			On submit, Procurement, Budget Manager, and Director of Finance get in-app, SMS, and email alerts. The preparer (accountant) is notified on submit and when the budget is returned, rejected, or fully approved.
		</div>
	</div>
</div>
<?php } ?>
</div>
