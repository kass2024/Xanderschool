<div class="mb-3">
	<button class="btn btn-success" data-toggle="modal" data-target="#mdlMaint"><i class="fa fa-plus"></i> New work order</button>
</div>

<?php if (!empty($overdue)) { ?>
<div class="alert alert-warning">Overdue maintenance: <?= count($overdue); ?> work order(s) past scheduled date.</div>
<?php } ?>

<div class="card">
	<div class="card-body">
		<table id="tblMaint" class="table table-striped table-bordered" style="width:100%">
			<thead>
			<tr>
				<th>#</th>
				<th>WO no</th>
				<th>Asset</th>
				<th>Type</th>
				<th>Priority</th>
				<th>Scheduled</th>
				<th>Status</th>
				<th>Cost</th>
				<th>Actions</th>
			</tr>
			</thead>
			<tbody>
			<?php $i = 1; foreach ($orders as $o) {
				$st = $o['status'] ?? '';
			?>
				<tr>
					<td><?= $i++; ?></td>
					<td><?= esc($o['work_order_no']); ?></td>
					<td><?= esc(($o['asset_code'] ?? '') . ' — ' . ($o['asset_name'] ?? '')); ?></td>
					<td><?= esc($o['maintenance_type']); ?></td>
					<td><?= esc($o['priority']); ?></td>
					<td><?= esc($o['scheduled_date'] ?? '—'); ?></td>
					<td><span class="badge badge-secondary"><?= esc($st); ?></span></td>
					<td><?= number_format((float)($o['total_cost'] ?? 0), 0); ?></td>
					<td class="text-nowrap">
						<?php if (in_array($st, ['requested', 'approved'], true)) { ?>
							<button class="btn btn-sm btn-outline-info btn-mstat" data-id="<?= (int)$o['id']; ?>" data-status="scheduled">Scheduled</button>
						<?php } ?>
						<?php if (in_array($st, ['scheduled', 'approved', 'requested'], true)) { ?>
							<button class="btn btn-sm btn-outline-primary btn-mstat" data-id="<?= (int)$o['id']; ?>" data-status="in_progress">In progress</button>
						<?php } ?>
						<?php if (in_array($st, ['in_progress', 'scheduled', 'waiting_parts'], true)) { ?>
							<button class="btn btn-sm btn-outline-success btn-mstat" data-id="<?= (int)$o['id']; ?>" data-status="completed">Completed</button>
						<?php } ?>
					</td>
				</tr>
			<?php } ?>
			</tbody>
		</table>
	</div>
</div>

<div class="modal fade" id="mdlMaint" tabindex="-1">
	<div class="modal-dialog modal-lg">
		<form class="modal-content" id="frmMaint">
			<div class="modal-header">
				<h5 class="modal-title">Maintenance work order</h5>
				<button type="button" class="close" data-dismiss="modal">&times;</button>
			</div>
			<div class="modal-body">
				<div class="row">
					<div class="col-md-12 form-group">
						<label>Asset *</label>
						<select name="asset_id" class="form-control" required>
							<option value="">— Select —</option>
							<?php foreach ($assets as $a) { ?>
								<option value="<?= (int)$a['id']; ?>"><?= esc($a['asset_code'] . ' — ' . $a['name']); ?></option>
							<?php } ?>
						</select>
					</div>
					<div class="col-md-4 form-group">
						<label>Type</label>
						<select name="maintenance_type" class="form-control">
							<option value="corrective">corrective</option>
							<option value="preventive">preventive</option>
							<option value="calibration">calibration</option>
						</select>
					</div>
					<div class="col-md-4 form-group">
						<label>Priority</label>
						<select name="priority" class="form-control">
							<option value="low">low</option>
							<option value="normal" selected>normal</option>
							<option value="high">high</option>
							<option value="critical">critical</option>
						</select>
					</div>
					<div class="col-md-4 form-group">
						<label>Scheduled date</label>
						<input type="date" name="scheduled_date" class="form-control">
					</div>
					<div class="col-md-6 form-group">
						<label>Assigned to</label>
						<select name="assigned_to" class="form-control">
							<option value="">—</option>
							<?php foreach ($staffs as $s) { ?>
								<option value="<?= (int)$s['id']; ?>"><?= esc($s['names']); ?></option>
							<?php } ?>
						</select>
					</div>
					<div class="col-md-6 form-group">
						<label>Provider</label>
						<select name="provider_type" class="form-control">
							<option value="internal">internal</option>
							<option value="external">external</option>
						</select>
					</div>
					<div class="col-md-12 form-group">
						<label>Problem description</label>
						<textarea name="problem" class="form-control" rows="2" required></textarea>
					</div>
					<div class="col-md-4 form-group"><label>Labour cost</label><input type="number" step="0.01" name="labour_cost" class="form-control" value="0"></div>
					<div class="col-md-4 form-group"><label>Parts cost</label><input type="number" step="0.01" name="parts_cost" class="form-control" value="0"></div>
					<div class="col-md-4 form-group"><label>Other cost</label><input type="number" step="0.01" name="other_cost" class="form-control" value="0"></div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
				<button type="submit" class="btn btn-primary">Create</button>
			</div>
		</form>
	</div>
</div>

<script>
$(function () {
	if ($.fn.DataTable) $('#tblMaint').DataTable({ pageLength: 25, order: [[0, 'desc']] });
	$('#frmMaint').on('submit', function (e) {
		e.preventDefault();
		$.post('<?= base_url('asset_management/save_maintenance'); ?>', $(this).serialize(), function (res) {
			if (res.error) { toastada.error(res.error); return; }
			toastada.success('Work order created');
			setTimeout(function () { location.reload(); }, 600);
		}, 'json');
	});
	$(document).on('click', '.btn-mstat', function () {
		$.post('<?= base_url('asset_management/maintenance_status'); ?>', { id: $(this).data('id'), status: $(this).data('status') }, function (res) {
			if (res.error) { toastada.error(res.error); return; }
			toastada.success('Status updated');
			setTimeout(function () { location.reload(); }, 500);
		}, 'json');
	});
});
</script>
