<link href="<?= base_url('assets/css/budget-preparation.css'); ?>?v=11" rel="stylesheet">

<?php
$tiers = $approval_tiers ?? \App\Services\Budget\CashRequestApprovalPolicy::defaultTiers();
$chainLabels = $chain_labels ?? \App\Services\Budget\CashRequestApprovalPolicy::chainLabels();
$settings = $settings ?? [];
?>

<div class="bp-hero mb-3">
	<h2><i class="fa fa-sliders-h"></i> Cash flow settings</h2>
	<p class="bp-meta mb-0"><?= esc($branch_label ?? 'Master school'); ?> — amount-based approval chains</p>
</div>

<div class="alert alert-light border mb-3 py-2">
	<strong>Who creates requests:</strong> Cashier / Accountant only.
	<strong class="ml-2">Who configures:</strong> Master school only (applies to all child schools).
</div>

<form id="frmCashFlowSettings">
	<input type="hidden" name="default_currency" value="<?= esc($settings['default_currency'] ?? 'RWF'); ?>">
	<input type="hidden" name="headteacher_approval_mode" value="<?= esc($settings['headteacher_approval_mode'] ?? 'evidence'); ?>">
	<input type="hidden" name="budget_utilization_alert_pct" value="<?= esc($settings['budget_utilization_alert_pct'] ?? '80'); ?>">
	<?php if (!empty($settings['ai_enabled'])) { ?><input type="hidden" name="ai_enabled" value="1"><?php } ?>

	<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
		<h5 class="mb-0"><i class="fa fa-route text-primary"></i> Approval by amount</h5>
		<button type="button" class="btn btn-outline-primary btn-sm" id="btnAddTier"><i class="fa fa-plus"></i> Add amount band</button>
	</div>

	<div class="bp-kpi-row mb-3" id="tierKpiPreview">
		<?php
		$prevMax = null;
		foreach ($tiers as $i => $tier) {
			$band = \App\Services\Budget\CashRequestApprovalPolicy::amountBandLabel($tier, $prevMax);
			$chain = $tier['chain'] ?? 'full';
			$steps = \App\Services\Budget\CashRequestApprovalPolicy::chainStepNames($chain);
			$cls = $chain === 'short' ? 'income' : ($chain === 'medium' ? '' : 'expense');
		?>
		<div class="bp-kpi <?= esc($cls); ?>">
			<label><?= esc($tier['label'] ?: $band); ?></label>
			<strong class="small d-block"><?= esc($band); ?></strong>
			<small class="text-muted d-block mt-1"><?= esc(implode(' → ', $steps)); ?></small>
		</div>
		<?php
			$prevMax = $tier['max_amount'];
		}
		?>
	</div>

	<div class="card border-0 shadow-sm mb-3">
		<div class="card-header bg-white"><strong>Amount bands</strong></div>
		<div class="card-body p-0">
			<table class="table mb-0" id="tblTiers">
				<thead class="thead-light">
					<tr>
						<th style="width:22%">Label</th>
						<th style="width:22%">Max amount (RWF)</th>
						<th>Approval chain</th>
						<th style="width:60px"></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ($tiers as $i => $tier) { ?>
				<tr class="tier-row">
					<td><input class="form-control form-control-sm tier-label" name="tier_label[]" value="<?= esc($tier['label'] ?? ''); ?>" placeholder="e.g. Low value"></td>
					<td>
						<input type="number" min="0" step="1" class="form-control form-control-sm tier-max" name="tier_max[]"
							value="<?= $tier['max_amount'] === null ? '' : (float) $tier['max_amount']; ?>"
							placeholder="Leave blank = no upper limit">
					</td>
					<td>
						<select class="form-control form-control-sm tier-chain" name="tier_chain[]">
							<?php foreach ($chainLabels as $ck => $clab) { ?>
							<option value="<?= esc($ck); ?>" <?= ($tier['chain'] ?? '') === $ck ? 'selected' : ''; ?>><?= esc($clab); ?></option>
							<?php } ?>
						</select>
					</td>
					<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger btn-rm-tier" title="Remove">&times;</button></td>
				</tr>
				<?php } ?>
				</tbody>
			</table>
		</div>
		<div class="card-footer bg-white small text-muted">
			Example: max <strong>60,000</strong> → Headmaster → Director of Finance.
			Leave the last band max blank for all higher amounts (full chain).
		</div>
	</div>

	<button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save cash flow settings</button>
	<a href="<?= base_url('budget/dashboard'); ?>" class="btn btn-light">Dashboard</a>
</form>

<template id="tplTierRow">
<tr class="tier-row">
	<td><input class="form-control form-control-sm tier-label" name="tier_label[]" value="" placeholder="e.g. Mid value"></td>
	<td><input type="number" min="0" step="1" class="form-control form-control-sm tier-max" name="tier_max[]" value="" placeholder="Leave blank = no upper limit"></td>
	<td>
		<select class="form-control form-control-sm tier-chain" name="tier_chain[]">
			<?php foreach ($chainLabels as $ck => $clab) { ?>
			<option value="<?= esc($ck); ?>"><?= esc($clab); ?></option>
			<?php } ?>
		</select>
	</td>
	<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger btn-rm-tier">&times;</button></td>
</tr>
</template>

<script>
(function () {
	var chainSteps = <?= json_encode(array_map(function ($c) {
		return \App\Services\Budget\CashRequestApprovalPolicy::chainStepNames($c);
	}, array_keys($chainLabels))); ?>;
	var chainKeys = <?= json_encode(array_keys($chainLabels)); ?>;

	function refreshPreview() {
		var rows = [];
		$('#tblTiers tbody tr').each(function () {
			var maxVal = $(this).find('.tier-max').val();
			rows.push({
				label: $(this).find('.tier-label').val() || '',
				max_amount: maxVal === '' ? null : Number(maxVal),
				chain: $(this).find('.tier-chain').val() || 'full'
			});
		});
		rows.sort(function (a, b) {
			if (a.max_amount === null && b.max_amount === null) return 0;
			if (a.max_amount === null) return 1;
			if (b.max_amount === null) return -1;
			return a.max_amount - b.max_amount;
		});
		var html = '';
		var prev = null;
		rows.forEach(function (t) {
			var band;
			if (prev === null && t.max_amount !== null) band = 'Up to ' + Number(t.max_amount).toLocaleString() + ' RWF';
			else if (t.max_amount === null && prev !== null) band = 'Above ' + Number(prev).toLocaleString() + ' RWF';
			else if (t.max_amount === null) band = 'Any amount';
			else band = Number(prev || 0).toLocaleString() + ' – ' + Number(t.max_amount).toLocaleString() + ' RWF';
			var idx = chainKeys.indexOf(t.chain);
			var steps = (idx >= 0 ? chainSteps[idx] : ['Full chain']).join(' → ');
			var cls = t.chain === 'short' ? 'income' : (t.chain === 'medium' ? '' : 'expense');
			html += '<div class="bp-kpi ' + cls + '"><label>' + $('<div>').text(t.label || band).html() + '</label>'
				+ '<strong class="small d-block">' + $('<div>').text(band).html() + '</strong>'
				+ '<small class="text-muted d-block mt-1">' + $('<div>').text(steps).html() + '</small></div>';
			prev = t.max_amount;
		});
		$('#tierKpiPreview').html(html || '<div class="text-muted p-3">Add at least one amount band.</div>');
	}

	$('#btnAddTier').on('click', function () {
		$('#tblTiers tbody').append($('#tplTierRow').html());
		refreshPreview();
	});
	$(document).on('click', '.btn-rm-tier', function () {
		if ($('#tblTiers tbody tr').length <= 1) { toastada.error('Keep at least one band.'); return; }
		$(this).closest('tr').remove();
		refreshPreview();
	});
	$(document).on('input change', '.tier-max, .tier-chain, .tier-label', refreshPreview);

	$('#frmCashFlowSettings').on('submit', function (e) {
		e.preventDefault();
		var $btn = $(this).find('[type=submit]').prop('disabled', true);
		$.post('<?= base_url('budget/save_settings'); ?>', $(this).serialize(), function (r) {
			$btn.prop('disabled', false);
			if (r.error) { toastada.error(r.error); return; }
			toastada.success(r.success || 'Saved');
			refreshPreview();
		}, 'json').fail(function () { $btn.prop('disabled', false); toastada.error('Save failed'); });
	});
})();
</script>
