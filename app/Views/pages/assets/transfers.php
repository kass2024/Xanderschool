<div class="mb-3">
	<button class="btn btn-success" data-toggle="modal" data-target="#mdlTransfer"><i class="fa fa-plus"></i> New transfer</button>
</div>

<div class="card">
	<div class="card-body">
		<table id="tblTransfers" class="table table-striped table-bordered" style="width:100%">
			<thead>
			<tr>
				<th>#</th>
				<th>Transfer no</th>
				<th>Type</th>
				<th>Status</th>
				<th>Items</th>
				<th>Created</th>
				<th>Actions</th>
			</tr>
			</thead>
			<tbody>
			<?php $i = 1; foreach ($transfers as $t) {
				$st = $t['status'] ?? 'draft';
			?>
				<tr>
					<td><?= $i++; ?></td>
					<td><?= esc($t['transfer_no']); ?></td>
					<td><?= esc($t['transfer_type']); ?><?= !empty($t['is_temporary']) ? ' <span class="badge badge-info">temp</span>' : ''; ?></td>
					<td><span class="badge badge-secondary"><?= esc($st); ?></span></td>
					<td><?= (int)($t['item_count'] ?? 0); ?></td>
					<td><?= esc($t['created_at'] ?? '—'); ?></td>
					<td class="text-nowrap">
						<?php if ($st === 'draft') { ?>
							<button class="btn btn-sm btn-outline-primary btn-xfer" data-id="<?= (int)$t['id']; ?>" data-action="submit">Submit</button>
						<?php } ?>
						<?php if (in_array($st, ['pending_approval', 'draft'], true)) { ?>
							<button class="btn btn-sm btn-outline-success btn-xfer" data-id="<?= (int)$t['id']; ?>" data-action="approve">Approve</button>
						<?php } ?>
						<?php if ($st === 'approved') { ?>
							<button class="btn btn-sm btn-outline-info btn-xfer" data-id="<?= (int)$t['id']; ?>" data-action="dispatch">Dispatch</button>
						<?php } ?>
						<?php if ($st === 'dispatched') { ?>
							<button class="btn btn-sm btn-outline-success btn-xfer" data-id="<?= (int)$t['id']; ?>" data-action="receive">Receive</button>
						<?php } ?>
						<?php if (!in_array($st, ['completed', 'rejected', 'cancelled'], true)) { ?>
							<button class="btn btn-sm btn-outline-danger btn-xfer" data-id="<?= (int)$t['id']; ?>" data-action="reject">Reject</button>
						<?php } ?>
					</td>
				</tr>
			<?php } ?>
			</tbody>
		</table>
	</div>
</div>

<div class="modal fade" id="mdlTransfer" tabindex="-1">
	<div class="modal-dialog modal-lg">
		<form class="modal-content" id="frmTransfer">
			<div class="modal-header">
				<h5 class="modal-title">Create transfer</h5>
				<button type="button" class="close" data-dismiss="modal">&times;</button>
			</div>
			<div class="modal-body">
				<div class="row">
					<div class="col-md-12 form-group">
						<label>Assets * <small class="text-muted">(multi-select or comma-separated IDs below)</small></label>
						<select name="asset_ids[]" id="xferAssets" class="form-control" multiple size="6">
							<?php foreach ($assets as $a) { ?>
								<option value="<?= (int)$a['id']; ?>"><?= esc($a['asset_code'] . ' — ' . $a['name']); ?></option>
							<?php } ?>
						</select>
					</div>
					<div class="col-md-12 form-group">
						<label>Or asset IDs (comma-separated)</label>
						<input type="text" name="asset_ids_csv" class="form-control" placeholder="1,2,3">
					</div>
					<div class="col-md-6 form-group">
						<label>From location</label>
						<select name="from_location_id" class="form-control">
							<option value="">—</option>
							<?php foreach ($locations as $l) { if ((int)$l['status'] !== 1) continue; ?>
								<option value="<?= (int)$l['id']; ?>"><?= esc($l['location_code'] . ' — ' . $l['name']); ?></option>
							<?php } ?>
						</select>
					</div>
					<div class="col-md-6 form-group">
						<label>To location</label>
						<select name="to_location_id" class="form-control">
							<option value="">—</option>
							<?php foreach ($locations as $l) { if ((int)$l['status'] !== 1) continue; ?>
								<option value="<?= (int)$l['id']; ?>"><?= esc($l['location_code'] . ' — ' . $l['name']); ?></option>
							<?php } ?>
						</select>
					</div>
					<div class="col-md-6 form-group">
						<label>From custodian</label>
						<select name="from_custodian_id" class="form-control">
							<option value="">—</option>
							<?php foreach ($staffs as $s) { ?>
								<option value="<?= (int)$s['id']; ?>"><?= esc($s['names']); ?></option>
							<?php } ?>
						</select>
					</div>
					<div class="col-md-6 form-group">
						<label>To custodian</label>
						<select name="to_custodian_id" class="form-control">
							<option value="">—</option>
							<?php foreach ($staffs as $s) { ?>
								<option value="<?= (int)$s['id']; ?>"><?= esc($s['names']); ?></option>
							<?php } ?>
						</select>
					</div>
					<div class="col-md-12 form-group">
						<label>Notes</label>
						<textarea name="notes" class="form-control" rows="2"></textarea>
					</div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
				<button type="submit" class="btn btn-primary">Create transfer</button>
			</div>
		</form>
	</div>
</div>

<script>
$(function () {
	if ($.fn.DataTable) $('#tblTransfers').DataTable({ pageLength: 25, order: [[0, 'desc']] });
	$('#frmTransfer').on('submit', function (e) {
		e.preventDefault();
		var data = $(this).serializeArray();
		var csv = $(this).find('[name=asset_ids_csv]').val();
		if (csv && !$('#xferAssets').val()) {
			data.push({ name: 'asset_ids', value: csv });
		}
		$.post('<?= base_url('asset_management/save_transfer'); ?>', $.param(data), function (res) {
			if (res.error) { toastada.error(res.error); return; }
			toastada.success('Transfer created');
			setTimeout(function () { location.reload(); }, 600);
		}, 'json');
	});
	$(document).on('click', '.btn-xfer', function () {
		var action = $(this).data('action');
		if (action === 'reject' && !confirm('Reject this transfer?')) return;
		$.post('<?= base_url('asset_management/transfer_action'); ?>', { id: $(this).data('id'), action: action }, function (res) {
			if (res.error) { toastada.error(res.error); return; }
			toastada.success('Updated');
			setTimeout(function () { location.reload(); }, 500);
		}, 'json');
	});
});
</script>
