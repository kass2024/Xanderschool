<?php
$amQty = (float) ($totals['qty'] ?? 0);
$amGood = (float) ($totals['good'] ?? 0);
$amDmg = (float) ($totals['damaged'] ?? 0);
$amGoodPct = $amQty > 0 ? (int) round($amGood / $amQty * 100) : 0;
$amDmgPct = $amQty > 0 ? (int) round($amDmg / $amQty * 100) : 0;
$amTypes = is_array($grouped ?? null) ? count($grouped) : 0;
$amLocs = is_array($locations ?? null) ? count($locations) : 0;
?>
<style>
.am-wrap{max-width:1200px}
.am-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:16px;flex-wrap:wrap}
.am-head h4{margin:0;font-weight:700;color:#0b1f4a}
.am-head p{margin:4px 0 0;color:#64748b;font-size:.88rem}
.am-actions{display:flex;flex-wrap:wrap;gap:8px}
.am-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:14px}
@media (max-width:991px){.am-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}}
.am-stat{border-radius:14px;padding:16px 16px 14px;background:#fff;border:1px solid #e8edf5;box-shadow:0 8px 20px rgba(15,23,42,.04);min-height:118px;position:relative;overflow:hidden}
.am-stat .am-ico{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:15px;margin-bottom:10px}
.am-stat h3{margin:0;font-size:1.85rem;font-weight:750;letter-spacing:-.03em;line-height:1}
.am-stat span{display:block;color:#64748b;font-size:.8rem;margin-top:6px}
.am-bar{height:6px;border-radius:99px;background:#eef2f7;margin-top:12px;overflow:hidden}
.am-bar i{display:block;height:100%;border-radius:99px}
.am-stat.navy h3{color:#0b1f4a}.am-stat.navy .am-ico{background:#e8eefc;color:#1d4ed8}.am-stat.navy .am-bar i{background:#1d4ed8}
.am-stat.ok h3{color:#047857}.am-stat.ok .am-ico{background:#d1fae5;color:#047857}.am-stat.ok .am-bar i{background:#10b981}
.am-stat.warn h3{color:#b45309}.am-stat.warn .am-ico{background:#ffedd5;color:#c2410c}.am-stat.warn .am-bar i{background:#f59e0b}
.am-stat.info h3{color:#1e3a8a}.am-stat.info .am-ico{background:#dbeafe;color:#1d4ed8}.am-stat.info .am-bar i{background:#2563eb}
.am-types{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px}
.am-chip{background:#fff;border:1px solid #e8edf5;border-radius:999px;padding:6px 12px;font-size:.8rem;color:#334155}
.am-chip b{color:#0b1f4a}
.am-card{border:1px solid #e8edf5;border-radius:14px;background:#fff;box-shadow:0 1px 2px rgba(15,23,42,.04)}
.am-type{background:#f8fafc;font-weight:600;color:#0b1f4a}
.am-good{color:#047857;font-weight:600}
.am-bad{color:#b91c1c;font-weight:600}
.am-pill{display:inline-block;padding:2px 8px;border-radius:999px;font-size:.75rem;background:#eef2ff;color:#3730a3}
.am-pill.old{background:#f1f5f9;color:#475569}
#tblSimpleAssets thead th{border-top:0;color:#64748b;font-size:.75rem;text-transform:uppercase;letter-spacing:.04em}
</style>
<div class="am-wrap">
	<div class="am-head">
		<div>
			<h4>Fixed asset register</h4>
			<p>Stock in good condition and damaged items, by type and location. Download the Excel sheet, fill it, and upload the full register.</p>
		</div>
		<div class="am-actions">
			<a class="btn btn-outline-primary btn-sm" href="<?= base_url('asset_management/download_simple_template'); ?>"><i class="fa fa-download"></i> Excel template</a>
			<button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#mdlExcel"><i class="fa fa-file-excel"></i> Upload Excel</button>
			<button type="button" class="btn btn-outline-secondary btn-sm" data-toggle="modal" data-target="#mdlLocation"><i class="fa fa-map-marker-alt"></i> New location</button>
			<button type="button" class="btn btn-success btn-sm" data-toggle="collapse" data-target="#amForm"><i class="fa fa-plus"></i> Record asset</button>
		</div>
	</div>

	<div class="am-kpis">
		<div class="am-stat navy">
			<div class="am-ico"><i class="fa fa-boxes"></i></div>
			<h3><?= number_format($amQty, 0); ?></h3>
			<span>Total quantity</span>
			<div class="am-bar"><i style="width:100%"></i></div>
		</div>
		<div class="am-stat ok">
			<div class="am-ico"><i class="fa fa-check"></i></div>
			<h3><?= number_format($amGood, 0); ?></h3>
			<span>In good condition · <?= $amGoodPct; ?>%</span>
			<div class="am-bar"><i style="width:<?= $amGoodPct; ?>%"></i></div>
		</div>
		<div class="am-stat warn">
			<div class="am-ico"><i class="fa fa-exclamation-triangle"></i></div>
			<h3><?= number_format($amDmg, 0); ?></h3>
			<span>Damaged (ibyangiye) · <?= $amDmgPct; ?>%</span>
			<div class="am-bar"><i style="width:<?= $amDmgPct; ?>%"></i></div>
		</div>
		<div class="am-stat info">
			<div class="am-ico"><i class="fa fa-map-marker-alt"></i></div>
			<h3><?= $amLocs; ?></h3>
			<span><?= (int) ($totals['lots'] ?? 0); ?> records · <?= $amTypes; ?> asset type<?= $amTypes === 1 ? '' : 's'; ?></span>
			<div class="am-bar"><i style="width:<?= $amLocs > 0 ? 100 : 0; ?>%"></i></div>
		</div>
	</div>
	<?php if (!empty($grouped)) { ?>
	<div class="am-types">
		<?php foreach ($grouped as $g) {
			$gQty = (float) ($g['qty'] ?? 0);
			$gShare = $amQty > 0 ? (int) round($gQty / $amQty * 100) : 0;
		?>
			<span class="am-chip"><?= esc($g['type']); ?> <b><?= number_format($gQty, 0); ?></b> · <?= $gShare; ?>%</span>
		<?php } ?>
	</div>
	<?php } ?>

	<div class="collapse mb-3" id="amForm">
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
					<tr><td colspan="8" class="text-center text-muted py-4">No assets yet. Upload the Excel template, or record desks, chairs, kitchen items, and electronics.</td></tr>
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
							<button type="button" class="btn btn-sm btn-success btn-stock" data-dir="in"
								data-id="<?= (int)$a['id']; ?>"
								data-name="<?= esc($a['name'], 'attr'); ?>"
								data-loc="<?= esc($a['location_name'] ?? '', 'attr'); ?>"
								data-good="<?= $good; ?>">In</button>
							<button type="button" class="btn btn-sm btn-danger btn-stock" data-dir="out"
								data-id="<?= (int)$a['id']; ?>"
								data-name="<?= esc($a['name'], 'attr'); ?>"
								data-loc="<?= esc($a['location_name'] ?? '', 'attr'); ?>"
								data-good="<?= $good; ?>">Out</button>
							<button type="button" class="btn btn-sm btn-outline-primary btn-dist"
								data-id="<?= (int)$a['id']; ?>"
								data-name="<?= esc($a['name'], 'attr'); ?>"
								data-loc="<?= esc($a['location_name'] ?? '', 'attr'); ?>"
								data-good="<?= $good; ?>">Move</button>
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
		<strong>Recent stock movements</strong>
		<ul class="mb-0 mt-2 pl-3">
			<?php foreach ($moves as $m) {
				$dir = strtolower((string) ($m['direction'] ?? 'move'));
				$qtyN = number_format((float)$m['quantity'], 0);
				$name = esc($m['asset_name'] ?? 'item');
				if ($dir === 'in') {
					$line = 'IN ' . $qtyN . ' ' . $name . ' at ' . esc($m['to_name'] ?? $m['from_name'] ?? '—');
				} elseif ($dir === 'out') {
					$line = 'OUT ' . $qtyN . ' ' . $name . ' from ' . esc($m['from_name'] ?? '—');
				} else {
					$line = 'MOVE ' . $qtyN . ' ' . $name . ' from ' . esc($m['from_name'] ?? '—') . ' → ' . esc($m['to_name'] ?? '—');
				}
			?>
				<li class="mb-1"><?= $line; ?> <small class="text-muted"><?= esc($m['created_at'] ?? ''); ?></small></li>
			<?php } ?>
		</ul>
	</div>
	<?php } ?>
</div>

<div class="modal fade" id="mdlStock" tabindex="-1">
	<div class="modal-dialog">
		<form class="modal-content" id="frmStock">
			<div class="modal-header">
				<h5 class="modal-title" id="stock_title">Stock in</h5>
				<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body">
				<input type="hidden" name="asset_id" id="stock_asset_id">
				<input type="hidden" name="direction" id="stock_dir" value="in">
				<p class="mb-2" id="stock_label"></p>
				<div class="form-group">
					<label>Quantity</label>
					<input type="number" min="1" step="1" class="form-control" name="quantity" id="stock_qty" required>
					<small class="text-muted" id="stock_avail"></small>
				</div>
				<div class="form-group mb-0">
					<label>Note (optional)</label>
					<input class="form-control" name="notes" id="stock_notes" placeholder="Received from supplier / issued to P4">
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
				<button type="submit" class="btn btn-primary" id="stock_save">Save</button>
			</div>
		</form>
	</div>
</div>

<div class="modal fade" id="mdlDistribute" tabindex="-1">
	<div class="modal-dialog">
		<form class="modal-content" id="frmDistribute">
			<div class="modal-header">
				<h5 class="modal-title">Move stock</h5>
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

<div class="modal fade" id="mdlExcel" tabindex="-1">
	<div class="modal-dialog">
		<form class="modal-content" id="frmExcel" enctype="multipart/form-data">
			<div class="modal-header">
				<h5 class="modal-title">Upload full asset Excel</h5>
				<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body">
				<p class="text-muted mb-2">Download the template first. In Excel, <strong>Asset type</strong> is a dropdown. For <strong>Location</strong>, type the place the first time. After this upload, that location is in the dropdown the next time you download the sheet.</p>
				<div class="form-group mb-0">
					<label>Excel file</label>
					<input type="file" class="form-control" name="excel" id="excel_file" accept=".xlsx,.xls" required>
				</div>
				<div class="small text-danger mt-2" id="excel_errors" style="display:none"></div>
			</div>
			<div class="modal-footer">
				<a class="btn btn-light" href="<?= base_url('asset_management/download_simple_template'); ?>">Download template</a>
				<button type="submit" class="btn btn-primary" id="btnUploadExcel"><i class="fa fa-upload"></i> Upload</button>
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

	$(document).on('click', '.btn-stock', function () {
		var dir = $(this).data('dir') === 'out' ? 'out' : 'in';
		var good = parseFloat($(this).data('good') || 0);
		$('#stock_asset_id').val($(this).data('id'));
		$('#stock_dir').val(dir);
		$('#stock_title').text(dir === 'out' ? 'Stock out' : 'Stock in');
		$('#stock_save').text(dir === 'out' ? 'Issue stock' : 'Receive stock');
		$('#stock_label').text($(this).data('name') + ' — ' + ($(this).data('loc') || ''));
		$('#stock_avail').text(dir === 'out' ? (good + ' good items available to issue') : 'Will add to current quantity');
		$('#stock_qty').attr('max', dir === 'out' ? good : null).val(1);
		$('#stock_notes').val('');
		$('#mdlStock').modal('show');
	});

	$('#frmStock').on('submit', function (e) {
		e.preventDefault();
		$.post('<?= base_url('asset_management/stock_move'); ?>', $(this).serialize(), function (res) {
			if (res.error) { toastErr(res.error); return; }
			toastOk(res.success || 'Saved');
			$('#mdlStock').modal('hide');
			reloadSoon();
		}, 'json').fail(function () { toastErr('Could not update stock'); });
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

	$('#frmExcel').on('submit', function (e) {
		e.preventDefault();
		var file = $('#excel_file')[0].files[0];
		if (!file) { toastErr('Choose an Excel file'); return; }
		var data = new FormData();
		data.append('excel', file);
		$('#btnUploadExcel').prop('disabled', true);
		$('#excel_errors').hide().text('');
		$.ajax({
			url: '<?= base_url('asset_management/upload_simple_excel'); ?>',
			type: 'POST',
			data: data,
			processData: false,
			contentType: false,
			dataType: 'json'
		}).done(function (res) {
			if (res.error && !res.success) { toastErr(res.error); $('#btnUploadExcel').prop('disabled', false); return; }
			if (res.errors && res.errors.length) {
				$('#excel_errors').text(res.errors.slice(0, 6).join(' ')).show();
			}
			toastOk(res.success || 'Uploaded');
			reloadSoon();
		}).fail(function () {
			toastErr('Could not upload the Excel file');
			$('#btnUploadExcel').prop('disabled', false);
		});
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
