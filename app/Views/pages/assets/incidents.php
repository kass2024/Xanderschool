<div class="mb-3">
	<button class="btn btn-success" data-toggle="modal" data-target="#mdlIncident"><i class="fa fa-plus"></i> Report incident</button>
</div>

<div class="card">
	<div class="card-body">
		<table id="tblInc" class="table table-striped table-bordered" style="width:100%">
			<thead>
			<tr>
				<th>#</th>
				<th>Incident no</th>
				<th>Asset</th>
				<th>Type</th>
				<th>When</th>
				<th>Est. loss</th>
				<th>Status</th>
				<th>Description</th>
			</tr>
			</thead>
			<tbody>
			<?php $i = 1; foreach ($incidents as $inc) { ?>
				<tr>
					<td><?= $i++; ?></td>
					<td><?= esc($inc['incident_no']); ?></td>
					<td><?= esc(($inc['asset_code'] ?? '') . ' — ' . ($inc['asset_name'] ?? '')); ?></td>
					<td><?= esc($inc['incident_type']); ?></td>
					<td><?= esc($inc['incident_at'] ?? '—'); ?></td>
					<td><?= number_format((float)($inc['estimated_loss'] ?? 0), 0); ?></td>
					<td><?= esc($inc['status']); ?></td>
					<td><small><?= esc($inc['description'] ?? ''); ?></small></td>
				</tr>
			<?php } ?>
			</tbody>
		</table>
	</div>
</div>

<div class="modal fade" id="mdlIncident" tabindex="-1">
	<div class="modal-dialog modal-lg">
		<form class="modal-content" id="frmIncident">
			<div class="modal-header">
				<h5 class="modal-title">Report incident / loss</h5>
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
						<select name="incident_type" class="form-control">
							<?php foreach (['damage', 'loss', 'theft', 'misuse', 'accident', 'safety', 'data_security', 'insurance'] as $t) { ?>
								<option value="<?= $t; ?>"><?= esc($t); ?></option>
							<?php } ?>
						</select>
					</div>
					<div class="col-md-4 form-group">
						<label>When</label>
						<input type="datetime-local" name="incident_at" class="form-control" value="<?= date('Y-m-d\TH:i'); ?>">
					</div>
					<div class="col-md-4 form-group">
						<label>Location</label>
						<select name="location_id" class="form-control">
							<option value="">—</option>
							<?php foreach ($locations as $l) { ?>
								<option value="<?= (int)$l['id']; ?>"><?= esc($l['location_code'] . ' — ' . $l['name']); ?></option>
							<?php } ?>
						</select>
					</div>
					<div class="col-md-12 form-group">
						<label>Description *</label>
						<textarea name="description" class="form-control" rows="2" required></textarea>
					</div>
					<div class="col-md-12 form-group">
						<label>Immediate action</label>
						<textarea name="immediate_action" class="form-control" rows="2"></textarea>
					</div>
					<div class="col-md-4 form-group"><label>Estimated loss</label><input type="number" step="0.01" name="estimated_loss" class="form-control" value="0"></div>
					<div class="col-md-4 form-group"><label>Police ref</label><input type="text" name="police_ref" class="form-control"></div>
					<div class="col-md-4 form-group"><label>Insurance ref</label><input type="text" name="insurance_ref" class="form-control"></div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
				<button type="submit" class="btn btn-primary">Submit report</button>
			</div>
		</form>
	</div>
</div>

<script>
$(function () {
	if ($.fn.DataTable) $('#tblInc').DataTable({ pageLength: 25, order: [[0, 'desc']] });
	$('#frmIncident').on('submit', function (e) {
		e.preventDefault();
		$.post('<?= base_url('asset_management/save_incident'); ?>', $(this).serialize(), function (res) {
			if (res.error) { toastada.error(res.error); return; }
			toastada.success('Incident reported');
			setTimeout(function () { location.reload(); }, 600);
		}, 'json');
	});
});
</script>
