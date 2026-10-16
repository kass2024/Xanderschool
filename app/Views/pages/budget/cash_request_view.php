<link href="<?= base_url('assets/css/budget-preparation.css'); ?>?v=15" rel="stylesheet">

<?php
$r = $request;
$chain = $approval_chain ?? ($r['approval_chain'] ?? 'full');
$statusFlow = ['DRAFT','SUBMITTED','HEADTEACHER_APPROVED','PROCUREMENT_APPROVED','BUDGET_APPROVED','FINANCE_AUTHORIZED','PAID','RECEIPT_CONFIRMED','CLOSED'];
if ($chain === 'wisdom') {
	$statusFlow = ['DRAFT','SUBMITTED','HEADTEACHER_APPROVED','CHIEF_ACCOUNTANT_APPROVED','FINANCE_AUTHORIZED','PAID'];
}
$currentIdx = array_search($r['status'], $statusFlow, true);
if ($currentIdx === false) {
	$currentIdx = 0;
}
$steps = $approval_flow ?? \App\Services\Budget\CashRequestApprovalPolicy::flowLabels($chain);
$wfActions = $wf_actions ?? \App\Services\Budget\CashRequestWorkflowService::uiActionsForRequest($r);
$wsName = strtoupper(trim((string) ($school_name ?? 'Wisdom School')));
$wsMotto = trim((string) ($school_moto ?? ''));
if ($wsMotto === '' && strpos($wsName, 'WISDOM') !== false) {
	$wsMotto = 'Fearing God is knowledge';
}
$wsWeb = trim((string) ($school_website ?? ''));
if ($wsWeb === '' && strpos($wsName, 'WISDOM') !== false) {
	$wsWeb = 'www.wisdomschoolrwanda.com';
}
$wsLogo = trim((string) ($school_logo ?? ''));
$creator = $creator ?? [];
$prepName = trim((string) ($r['prepared_by_name'] ?? ''));
if ($prepName === '') {
	$prepName = trim((string) (($creator['fname'] ?? '') . ' ' . ($creator['lname'] ?? '')));
}
$prepPos = trim((string) ($r['prepared_position'] ?? ''));
if ($prepPos === '') {
	$prepPos = trim((string) ($creator['post_title'] ?? ''));
}
$prepPhone = trim((string) ($r['prepared_phone'] ?? ''));
if ($prepPhone === '') {
	$prepPhone = trim((string) ($creator['phone'] ?? ''));
}
$prepSig = function_exists('staff_signature_file') ? staff_signature_file($creator['signature'] ?? '') : '';
$sigBase = base_url('assets/images/signatures/');
$actWhen = [];
foreach ($actions as $act) {
	$actWhen[$act['action']] = $act['created_at'] ?? '';
}
$fmtDate = static function ($value) {
	$value = trim((string) $value);
	if ($value === '') {
		return '';
	}
	$ts = strtotime($value);
	return $ts ? date('d/m/Y', $ts) : $value;
};
if (!function_exists('ws_frw_words')) {
	function ws_frw_words($amount): string
	{
		$n = (int) round((float) $amount);
		if ($n <= 0) {
			return '';
		}
		$ones = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
		$tens = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];
		$chunk = null;
		$chunk = static function ($num) use (&$chunk, $ones, $tens) {
			$num = (int) $num;
			if ($num < 20) {
				return $ones[$num];
			}
			if ($num < 100) {
				return $tens[(int) floor($num / 10)] . ($num % 10 ? '-' . $ones[$num % 10] : '');
			}
			return $ones[(int) floor($num / 100)] . ' hundred' . ($num % 100 ? ' ' . $chunk($num % 100) : '');
		};
		$parts = [];
		foreach ([1000000000 => 'billion', 1000000 => 'million', 1000 => 'thousand'] as $scale => $label) {
			$v = (int) floor($n / $scale);
			if ($v > 0) {
				$parts[] = $chunk($v) . ' ' . $label;
				$n %= $scale;
			}
		}
		if ($n > 0) {
			$parts[] = $chunk($n);
		}
		$text = implode(' ', $parts);
		return ucfirst($text) . ' Rwandan francs';
	}
}
$lineNames = [];
$itemTotal = 0.0;
foreach ($lines as $ln) {
	$itemTotal += (float) ($ln['amount'] ?? 0);
	$cat = trim((string) ($ln['budget_category'] ?? ''));
	if ($cat !== '' && !in_array($cat, $lineNames, true)) {
		$lineNames[] = $cat;
	}
}
$sigCell = static function ($file) use ($sigBase) {
	$file = trim((string) $file);
	if (strlen($file) < 5) {
		return 'Signature: ____________';
	}
	return '<img class="ws-sig-img" src="' . esc($sigBase . $file, 'attr') . '" alt="Signature">';
};
$submitted = !in_array($r['status'], ['DRAFT', 'CANCELLED'], true);
?>

<div class="budget-cr-view budget-cr-form">

<div class="cr-hero ws-no-print">
	<div class="d-flex flex-wrap justify-content-between align-items-start">
		<div>
			<h4 class="mb-1"><?= esc($r['request_no']); ?></h4>
			<span class="badge badge-light"><?= esc($r['status']); ?></span>
			<span class="ml-2"><?= number_format((float)$r['requested_amount'], 0); ?> RWF</span>
			<span class="badge badge-info ml-2"><?= esc($approval_steps_label ?? $chain); ?></span>
		</div>
		<div>
			<button type="button" class="btn btn-sm btn-light mr-1" onclick="window.print()"><i class="fa fa-print"></i> Print</button>
			<a href="<?= base_url('budget/requests'); ?>" class="btn btn-sm btn-light"><i class="fa fa-arrow-left"></i> All requests</a>
		</div>
	</div>
</div>

<div class="cr-flow-steps mb-3">
<?php foreach ($steps as $s) {
	$sIdx = array_search($s[0], $statusFlow, true);
	$done = $sIdx !== false && $sIdx <= $currentIdx;
	$active = $r['status'] === $s[0];
?>
<span class="cr-flow-step <?= ($active || $done) ? 'active' : ''; ?>"><?= esc($s[1]); ?></span>
<?php } ?>
</div>

<div class="row">
<div class="col-lg-7">

<div class="ws-erf">
<table class="ws-head">
	<tr>
		<td style="width:90px">
			<?php if ($wsLogo !== '') { ?>
			<img class="ws-logo" src="<?= base_url('assets/images/logo/' . $wsLogo); ?>" alt="">
			<?php } ?>
		</td>
		<td>
			<div class="ws-school"><?= esc($wsName); ?></div>
			<?php if ($wsMotto !== '') { ?><div class="ws-motto">"<?= esc($wsMotto); ?>"</div><?php } ?>
			<?php if ($wsWeb !== '') { ?><div class="ws-web"><?= esc($wsWeb); ?></div><?php } ?>
		</td>
		<td style="width:90px"></td>
	</tr>
</table>
<hr class="ws-rule">
<div class="ws-title">EXPENSE REQUEST FORM</div>
<table class="ws-grid">
	<tr>
		<td class="ws-lab narrow">School</td>
		<td><?= esc($wsName); ?></td>
		<td class="ws-lab narrow">Date</td>
		<td><?= esc($fmtDate($r['request_date'] ?? '')); ?></td>
	</tr>
</table>
<div class="ws-banner">1. Department Request Details</div>
<table class="ws-grid">
	<tr><td class="ws-lab">Prepared by (Names)</td><td colspan="3"><?= esc($prepName); ?></td></tr>
	<tr><td class="ws-lab">Position</td><td colspan="3"><?= esc($prepPos); ?></td></tr>
	<tr>
		<td class="ws-lab">Tel.</td>
		<td><?= esc($prepPhone); ?></td>
		<td class="ws-lab narrow">Date</td>
		<td><?= esc($fmtDate($r['request_date'] ?? '')); ?></td>
	</tr>
	<tr>
		<td class="ws-lab">Signature</td>
		<td colspan="3"><?= $prepSig !== '' ? '<img class="ws-sig-img" src="' . esc($sigBase . $prepSig, 'attr') . '" alt="Signature">' : '______________________________'; ?></td>
	</tr>
</table>
<div class="ws-banner">2. Purpose of Purchase</div>
<table class="ws-grid"><tr><td style="min-height:64px"><?= nl2br(esc($r['purpose'] ?? '')); ?></td></tr></table>
<div class="ws-banner">3. Description of Materials / Items and Amounts</div>
<table class="ws-grid ws-items">
	<thead><tr><th class="ws-no">No.</th><th>Description of Materials / Items</th><th>Amount (FRW)</th></tr></thead>
	<tbody>
	<?php
	$n = 0;
	foreach ($lines as $ln) {
		$n++;
		$desc = trim((string) ($ln['description'] ?? ''));
		$cat = trim((string) ($ln['budget_category'] ?? ''));
		if ($cat !== '' && strcasecmp($cat, $desc) !== 0) {
			$desc = $cat . ($desc !== '' ? ' — ' . $desc : '');
		}
	?>
	<tr>
		<td class="ws-no"><?= $n; ?></td>
		<td><?= esc($desc); ?></td>
		<td><?= number_format((float) ($ln['amount'] ?? 0), 0); ?></td>
	</tr>
	<?php } ?>
	<?php for ($pad = $n + 1; $pad <= 4; $pad++) { ?>
	<tr><td class="ws-no"><?= $pad; ?></td><td></td><td></td></tr>
	<?php } ?>
	</tbody>
	<tfoot><tr><td colspan="2" style="text-align:right">TOTAL</td><td><?= number_format($itemTotal, 0); ?></td></tr></tfoot>
</table>
<div class="ws-banner">4. Budget Information</div>
<table class="ws-grid">
	<tr><td class="ws-lab">Budget line description</td><td><?= esc($lineNames ? implode('; ', $lineNames) : '—'); ?></td></tr>
	<tr><td class="ws-lab">Total amount in FRW requested (in figures)</td><td><?= number_format($itemTotal, 0); ?></td></tr>
	<tr><td class="ws-lab">Total amount in FRW (in words)</td><td><?= esc(ws_frw_words($itemTotal)); ?></td></tr>
</table>
<div class="ws-banner">5. Payment Details</div>
<table class="ws-grid">
	<tr><td class="ws-lab">Payment to be made to</td><td><?= esc($r['payee_name'] ?? ''); ?></td></tr>
	<tr><td class="ws-lab">Contact Info:</td><td><?= esc($r['payee_contact'] ?? ''); ?></td></tr>
	<tr><td class="ws-lab">Payment / Account Number:</td><td><?= esc($r['payee_account'] ?? ''); ?></td></tr>
	<tr><td class="ws-lab">Bank Name:</td><td><?= esc($r['payee_bank'] ?? ''); ?></td></tr>
</table>
<div class="ws-banner">6. Verification and Approval</div>
<table class="ws-grid">
	<tr>
		<td class="ws-lab">Verified by the Accountant</td>
		<td><?= $submitted ? $sigCell($prepSig) : 'Signature: ____________'; ?></td>
		<td><?= $submitted && !empty($actWhen['submit']) ? 'Date: ' . esc($fmtDate($actWhen['submit'])) : 'Date: ____________'; ?></td>
	</tr>
	<tr>
		<td class="ws-lab">Verified by the Headteacher</td>
		<td><?= $sigCell($r['headteacher_signature'] ?? ''); ?></td>
		<td><?= !empty($actWhen['headteacher_approve']) ? 'Date: ' . esc($fmtDate($actWhen['headteacher_approve'])) : 'Date: ____________'; ?></td>
	</tr>
	<tr>
		<td class="ws-lab">Approved by the Chief Accountant</td>
		<td><?= $sigCell($r['chief_accountant_signature'] ?? ''); ?></td>
		<td><?= !empty($actWhen['chief_accountant_approve']) ? 'Date: ' . esc($fmtDate($actWhen['chief_accountant_approve'])) : 'Date: ____________'; ?></td>
	</tr>
	<tr>
		<td class="ws-lab">Authorized by the Director of Finance</td>
		<td><?= $sigCell($r['finance_signature'] ?? ''); ?></td>
		<td><?= !empty($actWhen['final_approve']) ? 'Date: ' . esc($fmtDate($actWhen['final_approve'])) : 'Date: ____________'; ?></td>
	</tr>
</table>
</div>

<?php if (!empty($documents)) { ?>
<div class="cr-section ws-no-print">
	<div class="cr-section-title"><i class="fa fa-paperclip"></i> Supporting documents</div>
	<ul class="cr-doc-list">
		<?php foreach ($documents as $doc) { ?>
		<li><span><i class="fa fa-file-pdf"></i> <?= esc($doc['original_name']); ?> <small class="text-muted">(<?= esc($doc['doc_type']); ?>)</small></span>
		<a href="<?= base_url('budget/cash_request_document/'.$doc['id']); ?>" class="btn btn-sm btn-outline-primary" target="_blank"><i class="fa fa-download"></i> Download</a></li>
		<?php } ?>
	</ul>
</div>
<?php } ?>

<?php if (!empty($payments)) { ?>
<div class="cr-section ws-no-print">
	<div class="cr-section-title"><i class="fa fa-money-check"></i> Payments</div>
	<ul class="list-group list-group-flush">
	<?php foreach ($payments as $p) { ?>
	<li class="list-group-item px-0"><?= esc($p['payment_date']); ?> — <?= number_format((float)$p['amount'], 0); ?> RWF (<?= esc($p['payment_reference'] ?? '—'); ?>)</li>
	<?php } ?>
	</ul>
</div>
<?php } ?>

</div>

<div class="col-lg-5 ws-no-print">

<div class="cr-section">
	<div class="cr-section-title"><i class="fa fa-gavel"></i> Your actions</div>
	<?php foreach ($wfActions as $key => $label) { ?>
	<button type="button" class="btn btn-sm btn-primary mr-1 mb-2 btn-wf btn-block text-left" data-action="<?= esc($key, 'attr'); ?>"><i class="fa fa-check"></i> <?= esc($label); ?></button>
	<?php } ?>
	<?php if (empty($wfActions)) { ?><p class="text-muted mb-0">No actions available at status <strong><?= esc($r['status']); ?></strong>.</p><?php } ?>
</div>

<div class="cr-section">
	<div class="cr-section-title"><i class="fa fa-history"></i> Approval timeline</div>
	<div class="cr-timeline">
	<?php foreach ($actions as $act) { ?>
	<div class="cr-timeline-item">
		<strong><?= esc($act['action']); ?></strong><br>
		<?= esc($act['previous_status']); ?> → <?= esc($act['new_status']); ?>
		<?php if (!empty($act['comment'])) { ?><br><em><?= esc($act['comment']); ?></em><?php } ?>
		<br><span class="text-muted"><?= esc($act['created_at']); ?></span>
	</div>
	<?php } ?>
	<?php if (empty($actions)) { ?><p class="text-muted small mb-0">No actions recorded yet.</p><?php } ?>
	</div>
</div>

<?= view('pages/budget/partials/process_guide', ['ctx' => 'execution', 'compact' => true]); ?>
</div>
</div>
</div>

<script>
$('.btn-wf').on('click', function () {
	var action = $(this).data('action');
	var comment = prompt('Comment (required for return/reject):') || '';
	if ((action === 'return' || action === 'reject') && !comment.trim()) {
		toastada.error('Comment is required.');
		return;
	}
	if (action === 'budget_approve' && !confirm('Confirm budget line has sufficient uncommitted funds for this request?')) return;
	if (action === 'final_approve' && !confirm('Authorize payment for this request?')) return;
	var $btn = $(this).prop('disabled', true);
	$.post('<?= base_url('budget/cash_request_action'); ?>', {
		request_id: <?= (int)$r['id']; ?>,
		action: action,
		comment: comment
	}, function (res) {
		if (res.error) { toastada.error(res.error); $btn.prop('disabled', false); return; }
		toastada.success('Status: ' + res.status);
		location.reload();
	}, 'json').fail(function () { $btn.prop('disabled', false); toastada.error('Request failed'); });
});
</script>
