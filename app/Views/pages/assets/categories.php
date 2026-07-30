<div class="mb-3">
	<button class="btn btn-success" data-toggle="modal" data-target="#mdlCategory"><i class="fa fa-plus"></i> Add category</button>
	<button class="btn btn-outline-primary" data-toggle="modal" data-target="#mdlField"><i class="fa fa-plus"></i> Custom field</button>
</div>
<div class="row">
	<div class="col-md-5">
		<div class="card">
			<div class="card-header">Category tree</div>
			<div class="card-body">
				<?php
				$renderTree = function ($nodes, $depth = 0) use (&$renderTree) {
					if (empty($nodes)) {
						echo '<p class="text-muted mb-0">No categories.</p>';
						return;
					}
					echo '<ul class="list-unstyled mb-0" style="padding-left:' . ($depth * 14) . 'px">';
					foreach ($nodes as $n) {
						echo '<li class="mb-1"><strong>' . esc($n['category_code']) . '</strong> — ' . esc($n['name']);
						if (!empty($n['children'])) {
							$renderTree($n['children'], $depth + 1);
						}
						echo '</li>';
					}
					echo '</ul>';
				};
				$renderTree($category_tree);
				?>
			</div>
		</div>
	</div>
	<div class="col-md-7">
		<div class="card">
			<div class="card-body">
				<table id="tblCat" class="table table-bordered table-striped table-sm" style="width:100%">
					<thead>
					<tr>
						<th>Code</th>
						<th>Name</th>
						<th>Tracking</th>
						<th>Fixed</th>
						<th>Serial?</th>
						<th>RFID?</th>
						<th>Status</th>
						<th></th>
					</tr>
					</thead>
					<tbody>
					<?php foreach ($categories as $c) { ?>
						<tr>
							<td><?= esc($c['category_code']); ?></td>
							<td><?= esc($c['name']); ?></td>
							<td><?= esc($c['tracking_mode']); ?></td>
							<td><?= ((int)$c['is_fixed_asset']) ? 'Yes' : 'No'; ?></td>
							<td><?= ((int)$c['requires_serial_number']) ? 'Yes' : 'No'; ?></td>
							<td><?= ((int)$c['requires_rfid']) ? 'Yes' : 'No'; ?></td>
							<td><?= ((int)$c['status'] === 1) ? 'Active' : 'Archived'; ?></td>
							<td>
								<button type="button" class="btn btn-sm btn-outline-warning btn-edit-cat" data-json="<?= esc(json_encode($c), 'attr'); ?>"><i class="fa fa-pencil-alt"></i></button>
								<?php if ((int)$c['status'] === 1) { ?>
									<button type="button" class="btn btn-sm btn-outline-danger btn-arch-cat" data-id="<?= (int)$c['id']; ?>"><i class="fa fa-archive"></i></button>
								<?php } ?>
							</td>
						</tr>
					<?php } ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="mdlCategory" tabindex="-1">
	<div class="modal-dialog modal-lg">
		<form class="modal-content" id="frmCategory">
			<div class="modal-header"><h5 class="modal-title">Category</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
			<div class="modal-body">
				<input type="hidden" name="id" id="cat_id">
				<div class="row">
					<div class="col-md-4 form-group"><label>Code *</label><input class="form-control" name="category_code" id="cat_code" required></div>
					<div class="col-md-8 form-group"><label>Name *</label><input class="form-control" name="name" id="cat_name" required></div>
					<div class="col-md-6 form-group">
						<label>Parent</label>
						<select class="form-control" name="parent_category_id" id="cat_parent">
							<option value="">— Root —</option>
							<?php foreach ($categories as $c) { if ((int)$c['status'] !== 1) continue; ?>
								<option value="<?= (int)$c['id']; ?>"><?= esc($c['category_code'] . ' — ' . $c['name']); ?></option>
							<?php } ?>
						</select>
					</div>
					<div class="col-md-3 form-group">
						<label>Class</label>
						<select class="form-control" name="asset_class" id="cat_class">
							<option value="tangible">tangible</option>
							<option value="intangible">intangible</option>
						</select>
					</div>
					<div class="col-md-3 form-group">
						<label>Tracking</label>
						<select class="form-control" name="tracking_mode" id="cat_track">
							<option value="individual">individual</option>
							<option value="quantity">quantity</option>
						</select>
					</div>
					<div class="col-md-4 form-group"><label>Default useful life (months)</label><input type="number" class="form-control" name="default_useful_life" id="cat_life"></div>
					<div class="col-md-4 form-group">
						<label>Depreciation</label>
						<select class="form-control" name="default_depreciation_method" id="cat_depr">
							<option value="straight_line">straight_line</option>
							<option value="reducing_balance">reducing_balance</option>
							<option value="units_of_production">units_of_production</option>
							<option value="none">none</option>
						</select>
					</div>
					<div class="col-md-12 form-group"><label>Description</label><textarea class="form-control" name="description" id="cat_desc" rows="2"></textarea></div>
					<div class="col-md-3 form-check ml-3"><input type="checkbox" class="form-check-input" name="is_fixed_asset" id="cat_fixed" value="1" checked><label class="form-check-label" for="cat_fixed">Fixed asset</label></div>
					<div class="col-md-3 form-check"><input type="checkbox" class="form-check-input" name="is_consumable" id="cat_cons" value="1"><label class="form-check-label" for="cat_cons">Consumable</label></div>
					<div class="col-md-3 form-check"><input type="checkbox" class="form-check-input" name="requires_serial_number" id="cat_serial" value="1"><label class="form-check-label" for="cat_serial">Requires serial</label></div>
					<div class="col-md-3 form-check"><input type="checkbox" class="form-check-input" name="requires_rfid" id="cat_rfid" value="1"><label class="form-check-label" for="cat_rfid">Requires RFID</label></div>
					<div class="col-md-3 form-check ml-3 mt-2"><input type="checkbox" class="form-check-input" name="requires_barcode" id="cat_barcode" value="1"><label class="form-check-label" for="cat_barcode">Requires barcode</label></div>
					<div class="col-md-3 form-check mt-2"><input type="checkbox" class="form-check-input" name="requires_warranty" id="cat_warranty" value="1"><label class="form-check-label" for="cat_warranty">Requires warranty</label></div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
				<button type="submit" class="btn btn-primary">Save</button>
			</div>
		</form>
	</div>
</div>

<div class="modal fade" id="mdlField" tabindex="-1">
	<div class="modal-dialog">
		<form class="modal-content" id="frmField">
			<div class="modal-header"><h5 class="modal-title">Category custom field</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
			<div class="modal-body">
				<div class="form-group">
					<label>Category</label>
					<select class="form-control" name="category_id" required>
						<?php foreach ($categories as $c) { if ((int)$c['status'] !== 1) continue; ?>
							<option value="<?= (int)$c['id']; ?>"><?= esc($c['category_code'] . ' — ' . $c['name']); ?></option>
						<?php } ?>
					</select>
				</div>
				<div class="form-group"><label>Field key</label><input class="form-control" name="field_key" placeholder="processor" required></div>
				<div class="form-group"><label>Label</label><input class="form-control" name="field_label" placeholder="Processor" required></div>
				<div class="form-group">
					<label>Data type</label>
					<select class="form-control" name="data_type">
						<option value="text">text</option>
						<option value="number">number</option>
						<option value="date">date</option>
						<option value="boolean">boolean</option>
						<option value="select">select</option>
					</select>
				</div>
				<div class="form-check"><input type="checkbox" class="form-check-input" name="is_required" id="fld_req" value="1"><label class="form-check-label" for="fld_req">Required</label></div>
				<input type="hidden" name="sort_order" value="0">
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
				<button type="submit" class="btn btn-primary">Add field</button>
			</div>
		</form>
	</div>
</div>
<script>
$(function () {
	if ($.fn.DataTable) $('#tblCat').DataTable({pageLength: 25});
	$('#mdlCategory').on('show.bs.modal', function (e) {
		if (!$(e.relatedTarget).hasClass('btn-edit-cat')) {
			$('#frmCategory')[0].reset();
			$('#cat_id').val('');
			$('#cat_fixed').prop('checked', true);
		}
	});
	$(document).on('click', '.btn-edit-cat', function () {
		var c = $(this).data('json');
		if (typeof c === 'string') try { c = JSON.parse(c); } catch (e) { c = {}; }
		$('#cat_id').val(c.id || '');
		$('#cat_code').val(c.category_code || '');
		$('#cat_name').val(c.name || '');
		$('#cat_parent').val(c.parent_category_id || '');
		$('#cat_class').val(c.asset_class || 'tangible');
		$('#cat_track').val(c.tracking_mode || 'individual');
		$('#cat_life').val(c.default_useful_life || '');
		$('#cat_depr').val(c.default_depreciation_method || 'straight_line');
		$('#cat_desc').val(c.description || '');
		$('#cat_fixed').prop('checked', +c.is_fixed_asset === 1);
		$('#cat_cons').prop('checked', +c.is_consumable === 1);
		$('#cat_serial').prop('checked', +c.requires_serial_number === 1);
		$('#cat_rfid').prop('checked', +c.requires_rfid === 1);
		$('#cat_barcode').prop('checked', +c.requires_barcode === 1);
		$('#cat_warranty').prop('checked', +c.requires_warranty === 1);
		$('#mdlCategory').modal('show');
	});
	$('#frmCategory').on('submit', function (e) {
		e.preventDefault();
		$.post('<?= base_url('asset_management/save_category'); ?>', $(this).serialize(), function (res) {
			if (res.error) { toastada.error(res.error); return; }
			toastada.success(res.success);
			setTimeout(function () { location.reload(); }, 500);
		}, 'json');
	});
	$('#frmField').on('submit', function (e) {
		e.preventDefault();
		$.post('<?= base_url('asset_management/save_category_field'); ?>', $(this).serialize(), function (res) {
			if (res.error) { toastada.error(res.error); return; }
			toastada.success(res.success);
			$('#mdlField').modal('hide');
		}, 'json');
	});
	$(document).on('click', '.btn-arch-cat', function () {
		if (!confirm('Archive this category?')) return;
		$.post('<?= base_url('asset_management/archive_category'); ?>', {id: $(this).data('id')}, function (res) {
			if (res.error) { toastada.error(res.error); return; }
			toastada.success(res.success);
			setTimeout(function () { location.reload(); }, 500);
		}, 'json');
	});
});
</script>
