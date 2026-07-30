<div class="mb-3">
	<button class="btn btn-success" data-toggle="modal" data-target="#mdlInsp"><i class="fa fa-plus"></i> Record inspection</button>
</div>

<div class="card">
	<div class="card-body">
		<table id="tblInsp" class="table table-striped table-bordered" style="width:100%">
			<thead>
			<tr>
				<th>#</th>
				<th>Date</th>
				<th>Asset</th>
				<th>Result</th>
				<th>Condition</th>
				<th>Next inspection</th>
				<th>Notes</th>
			</tr>
			</thead>
			<tbody>
			<?php $i = 1; foreach ($inspections as $in) { ?>
				<tr>
					<td><?= $i++; ?></td>
					<td><?= esc($in['inspection_date'] ?? '—'); ?></td>
					<td><?= esc(($in['asset_code'] ?? '') . ' — ' . ($in['asset_name'] ?? '')); ?></td>
					<td><span class="badge badge-<?= ($in['result'] ?? '') === 'pass' ? 'success' : (($in['result'] ?? '') === 'fail' ? 'danger' : 'warning'); ?>"><?= esc($in['result']); ?></span></td>
					<td><?= esc($in['condition_code'] ?? '—'); ?></td>
					<td><?= esc($in['next_inspection_date'] ?? '—'); ?></td>
					<td><small><?= esc($in['notes'] ?? ''); ?></small></td>
				</tr>
			<?php } ?>
			</tbody>
		</table>
	</div>
</div>

<div class="modal fade" id="mdlInsp" tabindex="-1">
	<div class="modal-dialog">
		<form class="modal-content" id="frmInsp">
			<div class="modal-header">
				<h5 class="modal-title">New inspection</h5>
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
					<label>Inspection date</label>
					<input type="date" name="inspection_date" class="form-control" value="<?= date('Y-m-d'); ?>">
				</div>
				<div class="form-group">
					<label>Result</label>
					<select name="result" class="form-control">
						<option value="pass">pass</option>
						<option value="fail">fail</option>
						<option value="conditional">conditional</option>
					</select>
				</div>
				<div class="form-group">
					<label>Condition</label>
					<select name="condition_code" class="form-control">
						<option value="good">good</option>
						<option value="fair">fair</option>
						<option value="poor">poor</option>
						<option value="damaged">damaged</option>
					</select>
				</div>
				<div class="form-group">
					<label>Next inspection date</label>
					<input type="date" name="next_inspection_date" class="form-control">
				</div>
				<div class="form-group">
					<label>Notes</label>
					<textarea name="notes" class="form-control" rows="2"></textarea>
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
	if ($.fn.DataTable) $('#tblInsp').DataTable({ pageLength: 25, order: [[1, 'desc']] });
	$('#frmInsp').on('submit', function (e) {
		e.preventDefault();
		$.post('<?= base_url('asset_management/save_inspection'); ?>', $(this).serialize(), function (res) {
			if (res.error) { toastada.error(res.error); return; }
			toastada.success('Inspection recorded');
			setTimeout(function () { location.reload(); }, 600);
		}, 'json');
	});
});
</script>
