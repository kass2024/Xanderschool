<?php $a = $asset; ?>
<div class="mb-3">
	<a href="<?= base_url('asset_management/assets'); ?>" class="btn btn-sm btn-outline-secondary">&larr; Back to assets</a>
	<button type="button" class="btn btn-sm btn-outline-danger float-right" id="btnArchiveAsset"><i class="fa fa-archive"></i> Archive</button>
</div>
<ul class="nav nav-tabs" role="tablist">
	<li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#tabOverview">Overview</a></li>
	<li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tabFinancial">Financial</a></li>
	<li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tabLocation">Location</a></li>
	<li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tabHistory">History</a></li>
</ul>
<div class="tab-content border border-top-0 p-3 bg-white">
	<div class="tab-pane fade show active" id="tabOverview">
		<div class="row">
			<div class="col-md-6">
				<table class="table table-sm table-borderless">
					<tr><th>Code</th><td><?= esc($a['asset_code']); ?></td></tr>
					<tr><th>Name</th><td><?= esc($a['name']); ?></td></tr>
					<tr><th>Category</th><td><?= esc(($a['category_code'] ?? '') . ' ' . ($a['category_name'] ?? '—')); ?></td></tr>
					<tr><th>Brand / Model</th><td><?= esc(trim(($a['brand'] ?? '') . ' / ' . ($a['model'] ?? ''), ' /')); ?></td></tr>
					<tr><th>Serial</th><td><?= esc($a['serial_number'] ?: '—'); ?></td></tr>
					<tr><th>Barcode</th><td><?= esc($a['barcode'] ?: '—'); ?></td></tr>
					<tr><th>RFID</th><td><?= esc($a['rfid_tag'] ? (substr($a['rfid_tag'], 0, 4) . '••••' . substr($a['rfid_tag'], -2)) : '—'); ?></td></tr>
					<tr><th>Status</th><td><span class="badge badge-primary"><?= esc($a['lifecycle_status']); ?></span></td></tr>
					<tr><th>Condition</th><td><?= esc($a['condition_code']); ?></td></tr>
				</table>
			</div>
			<div class="col-md-6">
				<table class="table table-sm table-borderless">
					<tr><th>Custodian</th><td><?= esc($a['custodian_name'] ?: '—'); ?></td></tr>
					<tr><th>Responsible</th><td><?= esc($a['responsible_name'] ?: '—'); ?></td></tr>
					<tr><th>Supplier</th><td><?= esc($a['supplier'] ?: '—'); ?></td></tr>
					<tr><th>Invoice</th><td><?= esc($a['invoice_number'] ?: '—'); ?></td></tr>
					<tr><th>Version</th><td><?= (int)$a['version']; ?></td></tr>
					<tr><th>Created</th><td><?= esc($a['created_at']); ?></td></tr>
					<tr><th>Updated</th><td><?= esc($a['updated_at']); ?></td></tr>
				</table>
				<?php if (!empty($a['description'])) { ?>
					<p><strong>Description</strong><br><?= nl2br(esc($a['description'])); ?></p>
				<?php } ?>
				<?php if (!empty($a['notes'])) { ?>
					<p><strong>Notes</strong><br><?= nl2br(esc($a['notes'])); ?></p>
				<?php } ?>
			</div>
		</div>
	</div>
	<div class="tab-pane fade" id="tabFinancial">
		<table class="table table-bordered">
			<tr><th>Purchase date</th><td><?= esc($a['purchase_date'] ?: '—'); ?></td></tr>
			<tr><th>Purchase price</th><td><?= number_format((float)$a['purchase_price'], 2); ?> <?= esc($a['currency']); ?></td></tr>
			<tr><th>Additional cost</th><td><?= number_format((float)$a['additional_cost'], 2); ?></td></tr>
			<tr><th>Total acquisition</th><td><strong><?= number_format((float)$a['total_acquisition_cost'], 2); ?></strong></td></tr>
			<tr><th>Useful life</th><td><?= esc($a['useful_life_months'] ?: '—'); ?> months</td></tr>
			<tr><th>Residual value</th><td><?= number_format((float)$a['residual_value'], 2); ?></td></tr>
			<tr><th>Depreciation method</th><td><?= esc($a['depreciation_method']); ?></td></tr>
			<tr><th>Accumulated depreciation</th><td><?= number_format((float)$a['accumulated_depreciation'], 2); ?></td></tr>
			<tr><th>Net book value</th><td><strong><?= number_format((float)$a['net_book_value'], 2); ?></strong></td></tr>
			<tr><th>Replacement value</th><td><?= number_format((float)$a['replacement_value'], 2); ?></td></tr>
		</table>
	</div>
	<div class="tab-pane fade" id="tabLocation">
		<table class="table table-bordered">
			<tr><th>Location code</th><td><?= esc($a['location_code'] ?: '—'); ?></td></tr>
			<tr><th>Location name</th><td><?= esc($a['location_name'] ?: '—'); ?></td></tr>
			<tr><th>Department</th><td><?= esc($a['department'] ?: '—'); ?></td></tr>
			<tr><th>Cost centre</th><td><?= esc($a['cost_centre'] ?: '—'); ?></td></tr>
		</table>
	</div>
	<div class="tab-pane fade" id="tabHistory">
		<table class="table table-striped table-sm">
			<thead>
			<tr><th>When</th><th>From</th><th>To</th><th>Operation</th><th>By</th><th>Notes</th></tr>
			</thead>
			<tbody>
			<?php if (empty($history)) { ?>
				<tr><td colspan="6" class="text-muted text-center">No history yet.</td></tr>
			<?php } else { foreach ($history as $h) { ?>
				<tr>
					<td><?= esc($h['created_at']); ?></td>
					<td><?= esc($h['previous_status'] ?: '—'); ?></td>
					<td><?= esc($h['new_status']); ?></td>
					<td><?= esc($h['operation_type']); ?></td>
					<td><?= esc($h['actor_name'] ?: '—'); ?></td>
					<td><?= esc($h['notes'] ?: ''); ?></td>
				</tr>
			<?php } } ?>
			</tbody>
		</table>
	</div>
</div>
<script>
$('#btnArchiveAsset').on('click', function () {
	if (!confirm('Archive this asset? It will be soft-archived, not permanently deleted.')) return;
	$.post('<?= base_url('asset_management/archive_asset'); ?>', {id: <?= (int)$a['id']; ?>}, function (res) {
		if (res.error) { toastada.error(res.error); return; }
		toastada.success(res.success);
		setTimeout(function () { location.href = '<?= base_url('asset_management/assets'); ?>'; }, 700);
	}, 'json');
});
</script>
