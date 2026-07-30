<div class="mb-3">
	<button class="btn btn-success" data-toggle="modal" data-target="#mdlDisposal"><i class="fa fa-plus"></i> Request disposal</button>
	<a href="<?= base_url('asset_management/assets'); ?>" class="btn btn-light">Asset register</a>
</div>

<div class="card">
	<div class="card-body">
		<table id="tblDisp" class="table table-striped table-bordered" style="width:100%">
			<thead>
			<tr>
				<th>#</th>
				<th>Disposal no</th>
				<th>Asset</th>
				<th>Method</th>
				<th>Status</th>
				<th>Reason</th>
				<th>Proceeds</th>
				<th>Actions</th>
			</tr>
			</thead>
			<tbody>
			<?php $i = 1; foreach ($disposals as $d) {
				$st = $d['status'] ?? '';
			?>
				<tr>
					<td><?= $i++; ?></td>
					<td><?= esc($d['disposal_no']); ?></td>
					<td><?= esc(($d['asset_code'] ?? '') . ' — ' . ($d['asset_name'] ?? '')); ?></td>
					<td><?= esc($d['method']); ?></td>
					<td><span class="badge badge-secondary"><?= esc($st); ?></span></td>
					<td><small><?= esc($d['reason'] ?? ''); ?></small></td>
					<td><?= number_format((float)($d['proceeds'] ?? 0), 0); ?></td>
					<td class="text-nowrap">
						<?php if ($st === 'requested') { ?>
							<button class="btn btn-sm btn-outline-success btn-disp" data-id="<?= (int)$d['id']; ?>" data-action="approve">Approve</button>
						<?php } ?>
						<?php if ($st === 'approved') { ?>
							<button class="btn btn-sm btn-outline-primary btn-disp" data-id="<?= (int)$d['id']; ?>" data-action="complete">Complete</button>
						<?php } ?>
					</td>
				</tr>
			<?php } ?>
			</tbody>
		</table>
	</div>
</div>

<div class="modal fade" id="mdlDisposal" tabindex="-1">
	<div class="modal-dialog">
		<form class="modal-content" id="frmDisposal">
			<div class="modal-header">
				<h5 class="modal-title">Request asset disposal</h5>
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
					<label>Method</label>
					<select name="method" class="form-control">
						<option value="write_off">write_off</option>
						<option value="sale">sale</option>
						<option value="donation">donation</option>
						<option value="recycle">recycle</option>
					</select>
				</div>
				<div class="form-group">
					<label>Reason *</label>
					<textarea name="reason" class="form-control" rows="2" required></textarea>
				</div>
				<div class="form-group">
					<label>Proceeds (if sale)</label>
					<input type="number" step="0.01" name="proceeds" class="form-control" value="0">
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
				<button type="submit" class="btn btn-primary">Submit request</button>
			</div>
		</form>
	</div>
</div>

<script>
$(function () {
	if ($.fn.DataTable) $('#tblDisp').DataTable({ pageLength: 25, order: [[0, 'desc']] });
	$('#frmDisposal').on('submit', function (e) {
		e.preventDefault();
		$.post('<?= base_url('asset_management/save_disposal'); ?>', $(this).serialize(), function (res) {
			if (res.error) { toastada.error(res.error); return; }
			toastada.success('Disposal requested');
			setTimeout(function () { location.reload(); }, 600);
		}, 'json');
	});
	$(document).on('click', '.btn-disp', function () {
		var action = $(this).data('action');
		if (!confirm('Confirm ' + action + '?')) return;
		$.post('<?= base_url('asset_management/disposal_action'); ?>', { id: $(this).data('id'), action: action }, function (res) {
			if (res.error) { toastada.error(res.error); return; }
			toastada.success('Updated');
			setTimeout(function () { location.reload(); }, 500);
		}, 'json');
	});
});
</script>
