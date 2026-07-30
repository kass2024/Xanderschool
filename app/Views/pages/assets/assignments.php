<div class="mb-3">
	<button class="btn btn-success" data-toggle="modal" data-target="#mdlAssign"><i class="fa fa-plus"></i> Assign asset</button>
</div>

<div class="card">
	<div class="card-body">
		<table id="tblAssign" class="table table-striped table-bordered" style="width:100%">
			<thead>
			<tr>
				<th>#</th>
				<th>Asset</th>
				<th>Staff</th>
				<th>Role</th>
				<th>Assigned</th>
				<th>Status</th>
				<th></th>
			</tr>
			</thead>
			<tbody>
			<?php $i = 1; foreach ($assignments as $a) { ?>
				<tr>
					<td><?= $i++; ?></td>
					<td><?= esc($a['asset_code'] . ' — ' . ($a['asset_name'] ?? '')); ?></td>
					<td><?= esc($a['staff_name'] ?? '—'); ?></td>
					<td><?= esc($a['role']); ?></td>
					<td><?= esc($a['assigned_at'] ?? $a['created_at'] ?? '—'); ?></td>
					<td><?= esc($a['status']); ?></td>
					<td>
						<?php if (($a['status'] ?? '') === 'active') { ?>
							<button type="button" class="btn btn-sm btn-outline-danger btn-end-assign" data-id="<?= (int)$a['id']; ?>">End</button>
						<?php } ?>
					</td>
				</tr>
			<?php } ?>
			</tbody>
		</table>
	</div>
</div>

<div class="modal fade" id="mdlAssign" tabindex="-1">
	<div class="modal-dialog">
		<form class="modal-content" id="frmAssign">
			<div class="modal-header">
				<h5 class="modal-title">Assign asset to staff</h5>
				<button type="button" class="close" data-dismiss="modal">&times;</button>
			</div>
			<div class="modal-body">
				<div class="form-group">
					<label>Asset *</label>
					<select name="asset_id" class="form-control" required>
						<option value="">— Select —</option>
						<?php foreach ($assets as $a) { ?>
							<option value="<?= (int)$a['id']; ?>"><?= esc($a['asset_code'] . ' — ' . $a['name']); ?></option>
						<?php } ?>
					</select>
				</div>
				<div class="form-group">
					<label>Staff *</label>
					<select name="staff_id" class="form-control" required>
						<option value="">— Select —</option>
						<?php foreach ($staffs as $s) { ?>
							<option value="<?= (int)$s['id']; ?>"><?= esc($s['names']); ?></option>
						<?php } ?>
					</select>
				</div>
				<div class="form-group">
					<label>Role</label>
					<select name="role" class="form-control">
						<?php foreach (['custodian', 'owner', 'user', 'approver', 'auditor', 'maintenance'] as $role) { ?>
							<option value="<?= $role; ?>" <?= $role === 'custodian' ? 'selected' : ''; ?>><?= esc($role); ?></option>
						<?php } ?>
					</select>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
				<button type="submit" class="btn btn-primary">Save assignment</button>
			</div>
		</form>
	</div>
</div>

<script>
$(function () {
	if ($.fn.DataTable) $('#tblAssign').DataTable({ pageLength: 25, order: [[0, 'asc']] });
	$('#frmAssign').on('submit', function (e) {
		e.preventDefault();
		$.post('<?= base_url('asset_management/save_assignment'); ?>', $(this).serialize(), function (res) {
			if (res.error) { toastada.error(res.error); return; }
			toastada.success('Assignment saved');
			setTimeout(function () { location.reload(); }, 500);
		}, 'json');
	});
	$(document).on('click', '.btn-end-assign', function () {
		if (!confirm('End this assignment?')) return;
		$.post('<?= base_url('asset_management/end_assignment'); ?>', { id: $(this).data('id') }, function (res) {
			if (res.error) { toastada.error(res.error); return; }
			toastada.success('Assignment ended');
			setTimeout(function () { location.reload(); }, 500);
		}, 'json');
	});
});
</script>
