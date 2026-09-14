<style>
.am-wrap{max-width:1200px}
.am-stat{border-radius:12px;padding:16px 18px;background:#fff;border:1px solid #e8edf5;height:100%}
.am-stat h3{margin:0;font-size:1.6rem;font-weight:700;color:#0b1f4a}
.am-stat span{display:block;color:#64748b;font-size:.82rem;margin-top:2px}
.am-stat.navy{border-top:3px solid #0b1f4a}
.am-stat.ok{border-top:3px solid #10b981}
.am-stat.warn{border-top:3px solid #f59e0b}
.am-stat.info{border-top:3px solid #2563eb}
.am-card{border:1px solid #e8edf5;border-radius:12px;background:#fff;box-shadow:0 1px 2px rgba(15,23,42,.04)}
.am-type{background:#f8fafc;font-weight:600;color:#0b1f4a}
.am-good{color:#047857;font-weight:600}
.am-bad{color:#b91c1c;font-weight:600}
.am-pill{display:inline-block;padding:2px 8px;border-radius:999px;font-size:.75rem;background:#eef2ff;color:#3730a3}
.am-pill.old{background:#f1f5f9;color:#475569}
</style>
<div class="am-wrap">
	<div class="d-flex justify-content-between align-items-center mb-3">
		<div>
			<h4 class="mb-0">Fixed asset register</h4>
			<small class="text-muted">Record quantities by location. Distribute stock to classrooms and offices.</small>
		</div>
		<div>
			<button type="button" class="btn btn-outline-secondary btn-sm mr-1" data-toggle="modal" data-target="#mdlLocation"><i class="fa fa-map-marker-alt"></i> New location</button>
			<button type="button" class="btn btn-primary btn-sm" data-toggle="collapse" data-target="#amForm"><i class="fa fa-plus"></i> Record asset</button>
		</div>
	</div>

	<div class="row">
		<div class="col-md-3 mb-3"><div class="am-stat navy"><h3><?= number_format((float)$totals['qty'], 0); ?></h3><span>Total quantity</span></div></div>
		<div class="col-md-3 mb-3"><div class="am-stat ok"><h3><?= number_format((float)$totals['good'], 0); ?></h3><span>In good condition</span></div></div>
		<div class="col-md-3 mb-3"><div class="am-stat warn"><h3><?= number_format((float)$totals['damaged'], 0); ?></h3><span>Damaged (ibyangiye)</span></div></div>
		<div class="col-md-3 mb-3"><div class="am-stat info"><h3><?= (int)$totals['lots']; ?></h3><span>Records · <?= count($locations); ?> locations</span></div></div>
	</div>

	<div class="collapse mb-3 show" id="amForm">
		<div class="am-card p-3">
			<form id="frmSimpleAsset">
				<input type="hidden" name="id" id="sa_id" value="">
				<div class="row">
					<div class="col-md-3 form-group">
						<label>Asset type</label>
						<input class="form-control" name="asset_type" id="sa_type" list="dlTypes" placeholder="Furniture, kitchen material…">
						<datalist id="dlTypes">
							<?php foreach ($type_suggestions as $t) { ?>
								<option value="<?= esc($t); ?>">
							<?php } ?>
							<?php foreach ($categories as $c) { ?>
								<option value="<?= esc($c['name']); ?>">
							<?php } ?>
						</datalist>
					</div>
					<div class="col-md-4 form-group">
						<label>Description *</label>
						<input class="form-control" name="name" id="sa_name" required placeholder="e.g. desks, HP laptop, plates">
					</div>
					<div class="col-md-2 form-group">
						<label>Quantity *</label>
						<input type="number" min="1" step="1" class="form-control" name="quantity" id="sa_qty" value="1" required>
					</div>
					<div class="col-md-3 form-group">
						<label>Damaged (ibyangiye)</label>
						<input type="number" min="0" step="1" class="form-control" name="qty_damaged" id="sa_dmg" value="0">
					</div>
					<div class="col-md-4 form-group">
						<label>Location *</label>
						<select class="form-control" name="location_id" id="sa_location">
							<option value="">— Select or type a new one —</option>
							<?php foreach ($locations as $l) { ?>
								<option value="<?= (int)$l['id']; ?>"><?= esc($l['name']); ?></option>
							<?php } ?>
						</select>
						<input class="form-control mt-1" name="location_name" id="sa_location_name" placeholder="Or type a new location (Staff room, P3…)">
					</div>
					<div class="col-md-3 form-group">
						<label>Age</label>
						<select class="form-control" name="age_flag" id="sa_age">
							<option value="old">Old</option>
							<option value="new">New</option>
						</select>
					</div>
					<div class="col-md-5 form-group d-flex align-items-end">
						<button type="submit" class="btn btn-success mr-2" id="btnSaveAsset"><i class="fa fa-save"></i> Save</button>
						<button type="button" class="btn btn-light" id="btnResetAsset">Clear</button>
					</div>
				</div>
			</form>
		</div>
	</div>

	<div class="am-card mb-3">
		<div class="table-responsive">
			<table class="table table-hover mb-0" id="tblSimpleAssets">
				<thead>
				<tr>
					<th>Type</th>
					<th>Description</th>
					<th class="text-right">Qty</th>
					<th class="text-right">Good</th>
					<th class="text-right">Damaged</th>
					<th>Location</th>
					<th>Age</th>
					<th></th>
				</tr>
				</thead>
				<tbody>
				<?php if (empty($grouped)) { ?>
					<tr><td colspan="8" class="text-center text-muted py-4">No assets yet. Record desks, chairs, kitchen items, and electronics above.</td></tr>
				<?php } ?>
				<?php foreach ($grouped as $g) { ?>
					<tr class="am-type">
						<td colspan="2"><?= esc($g['type']); ?></td>
						<td class="text-right"><?= number_format((float)$g['qty'], 0); ?></td>
						<td class="text-right am-good"><?= number_format((float)$g['qty'] - (float)$g['damaged'], 0); ?></td>
						<td class="text-right am-bad"><?= number_format((float)$g['damaged'], 0); ?></td>
						<td colspan="3" class="text-muted"><?= count($g['rows']); ?> location<?= count($g['rows']) === 1 ? '' : 's'; ?></td>
					</tr>
					<?php foreach ($g['rows'] as $a) {
						$qty = (float) ($a['quantity'] ?? 1);
						$dmg = (float) ($a['qty_damaged'] ?? 0);
						$good = max(0, $qty - $dmg);
						$age = (($a['condition_code'] ?? '') === 'new') ? 'new' : 'old';
					?>
					<tr>
						<td></td>
						<td><?= esc($a['name']); ?></td>
						<td class="text-right"><?= number_format($qty, 0); ?></td>
						<td class="text-right am-good"><?= number_format($good, 0); ?></td>
						<td class="text-right <?= $dmg > 0 ? 'am-bad' : ''; ?>"><?= number_format($dmg, 0); ?></td>
						<td><?= esc($a['location_name'] ?? '—'); ?></td>
						<td><span class="am-pill <?= $age === 'old' ? 'old' : ''; ?>"><?= $age === 'new' ? 'New' : 'Old'; ?></span></td>
						<td class="text-nowrap">
							<button type="button" class="btn btn-sm btn-outline-primary btn-dist"
								data-id="<?= (int)$a['id']; ?>"
								data-name="<?= esc($a['name'], 'attr'); ?>"
								data-loc="<?= esc($a['location_name'] ?? '', 'attr'); ?>"
								data-good="<?= $good; ?>">Distribute</button>
							<button type="button" class="btn btn-sm btn-outline-secondary btn-edit"
								data-json="<?= esc(json_encode($a), 'attr'); ?>">Edit</button>
						</td>
					</tr>
					<?php } ?>
				<?php } ?>
				</tbody>
			</table>
		</div>
	</div>

	<?php if (!empty($moves)) { ?>
	<div class="am-card p-3">
		<strong>Recent distributions</strong>
		<ul class="mb-0 mt-2 pl-3">
			<?php foreach ($moves as $m) { ?>
				<li class="mb-1">
					<?= number_format((float)$m['quantity'], 0); ?>
					<?= esc($m['asset_name'] ?? 'item'); ?>
					from <?= esc($m['from_name'] ?? '—'); ?>
					→ <?= esc($m['to_name'] ?? '—'); ?>
					<small class="text-muted"><?= esc($m['created_at'] ?? ''); ?></small>
				</li>
			<?php } ?>
		</ul>
	</div>
	<?php } ?>
</div>

<div class="modal fade" id="mdlDistribute" tabindex="-1">
	<div class="modal-dialog">
		<form class="modal-content" id="frmDistribute">
			<div class="modal-header">
				<h5 class="modal-title">Distribute stock</h5>
				<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body">
				<input type="hidden" name="asset_id" id="dist_asset_id">
				<p class="mb-2" id="dist_label"></p>
				<div class="form-group">
					<label>Quantity to send</label>
					<input type="number" min="1" step="1" class="form-control" name="quantity" id="dist_qty" required>
					<small class="text-muted" id="dist_avail"></small>
				</div>
				<div class="form-group">
					<label>Send to location</label>
					<select class="form-control" name="to_location_id" id="dist_to">
						<option value="">— Select —</option>
						<?php foreach ($locations as $l) { ?>
							<option value="<?= (int)$l['id']; ?>"><?= esc($l['name']); ?></option>
						<?php } ?>
					</select>
					<input class="form-control mt-1" name="to_location_name" placeholder="Or type a new location">
				</div>
				<div class="form-group mb-0">
					<label>Note (optional)</label>
					<input class="form-control" name="notes" placeholder="e.g. P4 classroom">
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
				<button type="submit" class="btn btn-primary">Send</button>
			</div>
		</form>
	</div>
</div>

<div class="modal fade" id="mdlLocation" tabindex="-1">
	<div class="modal-dialog modal-sm">
		<form class="modal-content" id="frmLocation">
			<div class="modal-header">
				<h5 class="modal-title">New location</h5>
				<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body">
				<input class="form-control" name="name" required placeholder="Staff room, Kitchen, P3…">
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
				<button type="submit" class="btn btn-primary">Save</button>
			</div>
		</form>
	</div>
</div>

<script>
$(function () {
	function toastOk(m) { if (window.toastada) toastada.success(m); else alert(m); }
	function toastErr(m) { if (window.toastada) toastada.error(m); else alert(m); }
	function reloadSoon() { setTimeout(function () { location.reload(); }, 500); }

	$('#btnResetAsset').on('click', function () {
		$('#frmSimpleAsset')[0].reset();
		$('#sa_id').val('');
		$('#btnSaveAsset').html('<i class="fa fa-save"></i> Save');
	});

	$(document).on('click', '.btn-edit', function () {
		var a = $(this).data('json');
		if (typeof a === 'string') { try { a = JSON.parse(a); } catch (e) { a = {}; } }
		$('#sa_id').val(a.id || '');
		$('#sa_type').val(a.category_name || '');
		$('#sa_name').val(a.name || '');
		$('#sa_qty').val(a.quantity || 1);
		$('#sa_dmg').val(a.qty_damaged || 0);
		$('#sa_location').val(a.location_id || '');
		$('#sa_location_name').val('');
		$('#sa_age').val((a.condition_code === 'new') ? 'new' : 'old');
		$('#amForm').collapse('show');
		$('#btnSaveAsset').html('<i class="fa fa-save"></i> Update');
		$('html,body').animate({scrollTop: $('#amForm').offset().top - 80}, 200);
	});

	$('#frmSimpleAsset').on('submit', function (e) {
		e.preventDefault();
		$.post('<?= base_url('asset_management/save_simple_asset'); ?>', $(this).serialize(), function (res) {
			if (res.error) { toastErr(res.error); return; }
			toastOk(res.success || 'Saved');
			reloadSoon();
		}, 'json').fail(function () { toastErr('Could not save'); });
	});

	$(document).on('click', '.btn-dist', function () {
		var good = parseFloat($(this).data('good') || 0);
		$('#dist_asset_id').val($(this).data('id'));
		$('#dist_label').text($(this).data('name') + ' — currently at ' + ($(this).data('loc') || '—'));
		$('#dist_avail').text(good + ' good items available');
		$('#dist_qty').attr('max', good).val(good > 0 ? 1 : 0);
		$('#mdlDistribute').modal('show');
	});

	$('#frmDistribute').on('submit', function (e) {
		e.preventDefault();
		$.post('<?= base_url('asset_management/distribute_asset'); ?>', $(this).serialize(), function (res) {
			if (res.error) { toastErr(res.error); return; }
			toastOk(res.success || 'Distributed');
			$('#mdlDistribute').modal('hide');
			reloadSoon();
		}, 'json').fail(function () { toastErr('Could not distribute'); });
	});

	$('#frmLocation').on('submit', function (e) {
		e.preventDefault();
		$.post('<?= base_url('asset_management/save_quick_location'); ?>', $(this).serialize(), function (res) {
			if (res.error) { toastErr(res.error); return; }
			toastOk(res.success || 'Location saved');
			$('#mdlLocation').modal('hide');
			reloadSoon();
		}, 'json').fail(function () { toastErr('Could not save location'); });
	});
});
</script>
