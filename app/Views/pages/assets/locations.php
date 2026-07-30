<div class="mb-3">
	<button class="btn btn-success" data-toggle="modal" data-target="#mdlLocation"><i class="fa fa-plus"></i> Add location</button>
</div>
<div class="row">
	<div class="col-md-5">
		<div class="card">
			<div class="card-header">Location tree</div>
			<div class="card-body">
				<?php
				$renderTree = function ($nodes, $depth = 0) use (&$renderTree) {
					if (empty($nodes)) {
						echo '<p class="text-muted mb-0">No active locations.</p>';
						return;
					}
					echo '<ul class="list-unstyled mb-0" style="padding-left:' . ($depth * 14) . 'px">';
					foreach ($nodes as $n) {
						echo '<li class="mb-1"><strong>' . esc($n['location_code']) . '</strong> — ' . esc($n['name']);
						if (!empty($n['children'])) {
							$renderTree($n['children'], $depth + 1);
						}
						echo '</li>';
					}
					echo '</ul>';
				};
				$renderTree($location_tree);
				?>
			</div>
		</div>
	</div>
	<div class="col-md-7">
		<div class="card">
			<div class="card-body">
				<table id="tblLoc" class="table table-bordered table-striped table-sm" style="width:100%">
					<thead>
					<tr>
						<th>Code</th>
						<th>Name</th>
						<th>Type</th>
						<th>Custodian</th>
						<th>Assets</th>
						<th>Value</th>
						<th>Status</th>
						<th></th>
					</tr>
					</thead>
					<tbody>
					<?php foreach ($locations as $l) { ?>
						<tr>
							<td><?= esc($l['location_code']); ?></td>
							<td><?= esc($l['name']); ?></td>
							<td><?= esc($l['location_type'] ?: '—'); ?></td>
							<td><?= esc($l['custodian_name'] ?: '—'); ?></td>
							<td><?= (int)$l['asset_count']; ?></td>
							<td><?= number_format((float)$l['asset_value'], 0); ?></td>
							<td><?= ((int)$l['status'] === 1) ? 'Active' : 'Archived'; ?></td>
							<td class="text-nowrap">
								<button type="button" class="btn btn-sm btn-outline-warning btn-edit-loc" data-json="<?= esc(json_encode($l), 'attr'); ?>"><i class="fa fa-pencil-alt"></i></button>
								<?php if ((int)$l['status'] === 1) { ?>
									<button type="button" class="btn btn-sm btn-outline-danger btn-arch-loc" data-id="<?= (int)$l['id']; ?>"><i class="fa fa-archive"></i></button>
								<?php } else { ?>
									<button type="button" class="btn btn-sm btn-outline-success btn-rest-loc" data-id="<?= (int)$l['id']; ?>"><i class="fa fa-undo"></i></button>
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

<div class="modal fade" id="mdlLocation" tabindex="-1">
	<div class="modal-dialog modal-lg">
		<form class="modal-content" id="frmLocation">
			<div class="modal-header">
				<h5 class="modal-title">Location</h5>
				<button type="button" class="close" data-dismiss="modal">&times;</button>
			</div>
			<div class="modal-body">
				<input type="hidden" name="id" id="loc_id">
				<div class="row">
					<div class="col-md-4 form-group"><label>Code *</label><input class="form-control" name="location_code" id="loc_code" required></div>
					<div class="col-md-8 form-group"><label>Name *</label><input class="form-control" name="name" id="loc_name" required></div>
					<div class="col-md-6 form-group">
						<label>Parent location</label>
						<select class="form-control" name="parent_location_id" id="loc_parent">
							<option value="">— Root —</option>
							<?php foreach ($locations as $l) { if ((int)$l['status'] !== 1) continue; ?>
								<option value="<?= (int)$l['id']; ?>"><?= esc($l['location_code'] . ' — ' . $l['name']); ?></option>
							<?php } ?>
						</select>
					</div>
					<div class="col-md-6 form-group"><label>Type</label><input class="form-control" name="location_type" id="loc_type" placeholder="campus, building, room…"></div>
					<div class="col-md-4 form-group"><label>Campus</label><input class="form-control" name="campus" id="loc_campus"></div>
					<div class="col-md-4 form-group"><label>Building</label><input class="form-control" name="building" id="loc_building"></div>
					<div class="col-md-2 form-group"><label>Floor</label><input class="form-control" name="floor" id="loc_floor"></div>
					<div class="col-md-2 form-group"><label>Room</label><input class="form-control" name="room" id="loc_room"></div>
					<div class="col-md-4 form-group"><label>Capacity</label><input type="number" class="form-control" name="capacity" id="loc_capacity"></div>
					<div class="col-md-8 form-group">
						<label>Custodian staff</label>
						<select class="form-control" name="responsible_staff_id" id="loc_staff">
							<option value="">— None —</option>
							<?php foreach ($staffs as $s) { ?>
								<option value="<?= (int)$s['id']; ?>"><?= esc($s['names']); ?></option>
							<?php } ?>
						</select>
					</div>
					<div class="col-md-12 form-group"><label>Description</label><textarea class="form-control" name="description" id="loc_desc" rows="2"></textarea></div>
				</div>
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
	if ($.fn.DataTable) $('#tblLoc').DataTable({pageLength: 25});
	$('#mdlLocation').on('show.bs.modal', function (e) {
		if (!$(e.relatedTarget).hasClass('btn-edit-loc')) {
			$('#frmLocation')[0].reset();
			$('#loc_id').val('');
		}
	});
	$(document).on('click', '.btn-edit-loc', function () {
		var l = $(this).data('json');
		if (typeof l === 'string') try { l = JSON.parse(l); } catch (e) { l = {}; }
		$('#loc_id').val(l.id || '');
		$('#loc_code').val(l.location_code || '');
		$('#loc_name').val(l.name || '');
		$('#loc_parent').val(l.parent_location_id || '');
		$('#loc_type').val(l.location_type || '');
		$('#loc_campus').val(l.campus || '');
		$('#loc_building').val(l.building || '');
		$('#loc_floor').val(l.floor || '');
		$('#loc_room').val(l.room || '');
		$('#loc_capacity').val(l.capacity || '');
		$('#loc_staff').val(l.responsible_staff_id || '');
		$('#loc_desc').val(l.description || '');
		$('#mdlLocation').modal('show');
	});
	$('#frmLocation').on('submit', function (e) {
		e.preventDefault();
		$.post('<?= base_url('asset_management/save_location'); ?>', $(this).serialize(), function (res) {
			if (res.error) { toastada.error(res.error); return; }
			toastada.success(res.success);
			setTimeout(function () { location.reload(); }, 500);
		}, 'json');
	});
	$(document).on('click', '.btn-arch-loc', function () {
		if (!confirm('Archive this location?')) return;
		$.post('<?= base_url('asset_management/archive_location'); ?>', {id: $(this).data('id')}, function (res) {
			if (res.error) { toastada.error(res.error); return; }
			toastada.success(res.success);
			setTimeout(function () { location.reload(); }, 500);
		}, 'json');
	});
	$(document).on('click', '.btn-rest-loc', function () {
		$.post('<?= base_url('asset_management/restore_location'); ?>', {id: $(this).data('id')}, function (res) {
			if (res.error) { toastada.error(res.error); return; }
			toastada.success(res.success);
			setTimeout(function () { location.reload(); }, 500);
		}, 'json');
	});
});
</script>
