<div class="row mb-3">
	<div class="col-md-12">
		<form class="form-inline" method="get" action="<?= base_url('asset_management/assets'); ?>">
			<input type="text" name="q" class="form-control mb-2 mr-sm-2" placeholder="Search code, name, serial, RFID…" value="<?= esc($filters['q'] ?? ''); ?>">
			<select name="status" class="form-control mb-2 mr-sm-2">
				<option value="">All statuses</option>
				<?php foreach ($statuses as $st) { ?>
					<option value="<?= esc($st); ?>" <?= (($filters['status'] ?? '') === $st) ? 'selected' : ''; ?>><?= esc($st); ?></option>
				<?php } ?>
			</select>
			<select name="category_id" class="form-control mb-2 mr-sm-2">
				<option value="">All categories</option>
				<?php foreach ($categories as $c) { ?>
					<option value="<?= (int)$c['id']; ?>" <?= ((int)($filters['category_id'] ?? 0) === (int)$c['id']) ? 'selected' : ''; ?>><?= esc($c['category_code'] . ' — ' . $c['name']); ?></option>
				<?php } ?>
			</select>
			<select name="location_id" class="form-control mb-2 mr-sm-2">
				<option value="">All locations</option>
				<?php foreach ($locations as $l) { ?>
					<option value="<?= (int)$l['id']; ?>" <?= ((int)($filters['location_id'] ?? 0) === (int)$l['id']) ? 'selected' : ''; ?>><?= esc($l['location_code'] . ' — ' . $l['name']); ?></option>
				<?php } ?>
			</select>
			<button class="btn btn-primary mb-2 mr-2" type="submit"><i class="fa fa-search"></i> Filter</button>
			<button class="btn btn-success mb-2" type="button" data-toggle="modal" data-target="#mdlAsset"><i class="fa fa-plus"></i> Register asset</button>
		</form>
	</div>
</div>

<div class="card">
	<div class="card-body">
		<table id="tblAssets" class="table table-striped table-bordered table-hover" style="width:100%">
			<thead>
			<tr>
				<th>#</th>
				<th>Code</th>
				<th>Name</th>
				<th>Category</th>
				<th>Location</th>
				<th>Custodian</th>
				<th>Status</th>
				<th>Condition</th>
				<th>Cost</th>
				<th></th>
			</tr>
			</thead>
			<tbody>
			<?php $i = 1; foreach ($assets as $a) { ?>
				<tr>
					<td><?= $i++; ?></td>
					<td><?= esc($a['asset_code']); ?></td>
					<td><?= esc($a['name']); ?></td>
					<td><?= esc($a['category_name'] ?? '—'); ?></td>
					<td><?= esc($a['location_name'] ?? '—'); ?></td>
					<td><?= esc($a['custodian_name'] ?? '—'); ?></td>
					<td><?= esc($a['lifecycle_status']); ?></td>
					<td><?= esc($a['condition_code']); ?></td>
					<td><?= number_format((float)$a['total_acquisition_cost'], 0); ?></td>
					<td class="text-nowrap">
						<a class="btn btn-sm btn-outline-secondary" href="<?= base_url('asset_management/asset_view/' . $a['id']); ?>"><i class="fa fa-eye"></i></a>
						<button type="button" class="btn btn-sm btn-outline-warning btn-edit-asset"
							data-id="<?= (int)$a['id']; ?>"
							data-json="<?= esc(json_encode($a), 'attr'); ?>"><i class="fa fa-pencil-alt"></i></button>
					</td>
				</tr>
			<?php } ?>
			</tbody>
		</table>
	</div>
</div>

<div class="modal fade" id="mdlAsset" tabindex="-1" role="dialog">
	<div class="modal-dialog modal-lg" role="document">
		<form id="frmAsset" class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Register / edit asset</h5>
				<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body">
				<input type="hidden" name="id" id="asset_id" value="">
				<div class="row">
					<div class="col-md-4 form-group">
						<label>Asset code <small class="text-muted">(blank = auto)</small></label>
						<input type="text" class="form-control" name="asset_code" id="asset_code" placeholder="AST-ICT-2026-000001">
					</div>
					<div class="col-md-8 form-group">
						<label>Name *</label>
						<input type="text" class="form-control" name="name" id="asset_name" required>
					</div>
					<div class="col-md-12 form-group">
						<label>Description</label>
						<textarea class="form-control" name="description" id="asset_description" rows="2"></textarea>
					</div>
					<div class="col-md-6 form-group">
						<label>Category</label>
						<select class="form-control" name="category_id" id="asset_category_id">
							<option value="">— Select —</option>
							<?php foreach ($categories as $c) { if ((int)$c['status'] !== 1) continue; ?>
								<option value="<?= (int)$c['id']; ?>"><?= esc($c['category_code'] . ' — ' . $c['name']); ?></option>
							<?php } ?>
						</select>
					</div>
					<div class="col-md-6 form-group">
						<label>Location</label>
						<select class="form-control" name="location_id" id="asset_location_id">
							<option value="">— Select —</option>
							<?php foreach ($locations as $l) { if ((int)$l['status'] !== 1) continue; ?>
								<option value="<?= (int)$l['id']; ?>"><?= esc($l['location_code'] . ' — ' . $l['name']); ?></option>
							<?php } ?>
						</select>
					</div>
					<div class="col-md-4 form-group"><label>Brand</label><input class="form-control" name="brand" id="asset_brand"></div>
					<div class="col-md-4 form-group"><label>Model</label><input class="form-control" name="model" id="asset_model"></div>
					<div class="col-md-4 form-group"><label>Manufacturer</label><input class="form-control" name="manufacturer" id="asset_manufacturer"></div>
					<div class="col-md-4 form-group"><label>Serial number</label><input class="form-control" name="serial_number" id="asset_serial"></div>
					<div class="col-md-4 form-group"><label>Barcode</label><input class="form-control" name="barcode" id="asset_barcode"></div>
					<div class="col-md-4 form-group"><label>RFID tag</label><input class="form-control" name="rfid_tag" id="asset_rfid"></div>
					<div class="col-md-6 form-group">
						<label>Custodian</label>
						<select class="form-control" name="custodian_staff_id" id="asset_custodian">
							<option value="">— None —</option>
							<?php foreach ($staffs as $s) { ?>
								<option value="<?= (int)$s['id']; ?>"><?= esc($s['names']); ?></option>
							<?php } ?>
						</select>
					</div>
					<div class="col-md-6 form-group">
						<label>Responsible staff</label>
						<select class="form-control" name="responsible_staff_id" id="asset_responsible">
							<option value="">— None —</option>
							<?php foreach ($staffs as $s) { ?>
								<option value="<?= (int)$s['id']; ?>"><?= esc($s['names']); ?></option>
							<?php } ?>
						</select>
					</div>
					<div class="col-md-4 form-group"><label>Purchase date</label><input type="date" class="form-control" name="purchase_date" id="asset_purchase_date"></div>
					<div class="col-md-4 form-group"><label>Purchase price</label><input type="number" step="0.01" class="form-control" name="purchase_price" id="asset_purchase_price" value="0"></div>
					<div class="col-md-4 form-group"><label>Additional cost</label><input type="number" step="0.01" class="form-control" name="additional_cost" id="asset_additional_cost" value="0"></div>
					<div class="col-md-4 form-group"><label>Currency</label><input class="form-control" name="currency" id="asset_currency" value="RWF"></div>
					<div class="col-md-4 form-group"><label>Invoice #</label><input class="form-control" name="invoice_number" id="asset_invoice"></div>
					<div class="col-md-4 form-group"><label>Supplier</label><input class="form-control" name="supplier" id="asset_supplier"></div>
					<div class="col-md-4 form-group">
						<label>Condition</label>
						<select class="form-control" name="condition_code" id="asset_condition">
							<option value="new">new</option>
							<option value="good" selected>good</option>
							<option value="fair">fair</option>
							<option value="poor">poor</option>
							<option value="unserviceable">unserviceable</option>
						</select>
					</div>
					<div class="col-md-4 form-group">
						<label>Lifecycle status</label>
						<select class="form-control" name="lifecycle_status" id="asset_status">
							<?php foreach ($statuses as $st) { ?>
								<option value="<?= esc($st); ?>" <?= $st === 'draft' ? 'selected' : ''; ?>><?= esc($st); ?></option>
							<?php } ?>
						</select>
					</div>
					<div class="col-md-4 form-group"><label>Useful life (months)</label><input type="number" class="form-control" name="useful_life_months" id="asset_life"></div>
					<div class="col-md-4 form-group"><label>Residual value</label><input type="number" step="0.01" class="form-control" name="residual_value" id="asset_residual" value="0"></div>
					<div class="col-md-4 form-group">
						<label>Depreciation</label>
						<select class="form-control" name="depreciation_method" id="asset_depr">
							<option value="straight_line">straight_line</option>
							<option value="reducing_balance">reducing_balance</option>
							<option value="units_of_production">units_of_production</option>
							<option value="none">none</option>
						</select>
					</div>
					<div class="col-md-12 form-group"><label>Notes</label><textarea class="form-control" name="notes" id="asset_notes" rows="2"></textarea></div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
				<button type="submit" class="btn btn-primary">Save asset</button>
			</div>
		</form>
	</div>
</div>

<script>
$(function () {
	if ($.fn.DataTable) {
		$('#tblAssets').DataTable({ pageLength: 25, order: [[0, 'asc']] });
	}
	$('#mdlAsset').on('show.bs.modal', function (e) {
		var btn = $(e.relatedTarget);
		if (!btn || !btn.hasClass('btn-edit-asset')) {
			$('#frmAsset')[0].reset();
			$('#asset_id').val('');
			$('#asset_code').prop('readonly', false);
			return;
		}
	});
	$(document).on('click', '.btn-edit-asset', function () {
		var a = $(this).data('json');
		if (typeof a === 'string') { try { a = JSON.parse(a); } catch (e) { a = {}; } }
		$('#asset_id').val(a.id || '');
		$('#asset_code').val(a.asset_code || '').prop('readonly', true);
		$('#asset_name').val(a.name || '');
		$('#asset_description').val(a.description || '');
		$('#asset_category_id').val(a.category_id || '');
		$('#asset_location_id').val(a.location_id || '');
		$('#asset_brand').val(a.brand || '');
		$('#asset_model').val(a.model || '');
		$('#asset_manufacturer').val(a.manufacturer || '');
		$('#asset_serial').val(a.serial_number || '');
		$('#asset_barcode').val(a.barcode || '');
		$('#asset_rfid').val(a.rfid_tag || '');
		$('#asset_custodian').val(a.custodian_staff_id || '');
		$('#asset_responsible').val(a.responsible_staff_id || '');
		$('#asset_purchase_date').val(a.purchase_date || '');
		$('#asset_purchase_price').val(a.purchase_price || 0);
		$('#asset_additional_cost').val(a.additional_cost || 0);
		$('#asset_currency').val(a.currency || 'RWF');
		$('#asset_invoice').val(a.invoice_number || '');
		$('#asset_supplier').val(a.supplier || '');
		$('#asset_condition').val(a.condition_code || 'good');
		$('#asset_status').val(a.lifecycle_status || 'draft');
		$('#asset_life').val(a.useful_life_months || '');
		$('#asset_residual').val(a.residual_value || 0);
		$('#asset_depr').val(a.depreciation_method || 'straight_line');
		$('#asset_notes').val(a.notes || '');
		$('#mdlAsset').modal('show');
	});
	$('#frmAsset').on('submit', function (e) {
		e.preventDefault();
		var btn = $(this).find('[type=submit]');
		var txt = btn.text();
		btn.prop('disabled', true).text('Saving…');
		$.post('<?= base_url('asset_management/save_asset'); ?>', $(this).serialize(), function (res) {
			btn.prop('disabled', false).text(txt);
			if (res.error) { toastada.error(res.error); return; }
			toastada.success(res.success || 'Saved');
			setTimeout(function () { location.reload(); }, 600);
		}, 'json').fail(function () {
			btn.prop('disabled', false).text(txt);
			toastada.error('Server error');
		});
	});
});
</script>
