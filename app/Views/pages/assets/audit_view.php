<?php $auditOpen = ($audit['status'] ?? '') !== 'closed'; ?>
<div class="mb-3">
	<a href="<?= base_url('asset_management/audits'); ?>" class="btn btn-light"><i class="fa fa-arrow-left"></i> Back to audits</a>
	<?php if ($auditOpen) { ?>
		<button type="button" class="btn btn-danger" id="btnCloseAudit"><i class="fa fa-lock"></i> Close audit</button>
	<?php } ?>
</div>

<div class="card mb-3">
	<div class="card-body">
		<h5 class="mb-1"><?= esc($audit['audit_no']); ?> — <?= esc($audit['title']); ?></h5>
		<p class="mb-0 text-muted">
			Status: <span class="badge badge-secondary"><?= esc($audit['status']); ?></span>
			&nbsp;|&nbsp; Items: <?= count($items); ?>
			<?php if (!empty($audit['closed_at'])) { ?>
				&nbsp;|&nbsp; Closed: <?= esc($audit['closed_at']); ?>
			<?php } ?>
		</p>
	</div>
</div>

<?php if (!empty($ai_summary)) { ?>
<div class="alert alert-info border-left border-primary" style="border-left-width:4px!important">
	<strong><i class="fa fa-robot"></i> AI suggestion</strong>
	<p class="mb-0 mt-2" style="white-space:pre-wrap"><?= esc($ai_summary); ?></p>
	<small class="text-muted">Review manually before acting on suggestions.</small>
</div>
<?php } ?>

<?php if ($auditOpen) { ?>
<div class="card mb-3">
	<div class="card-header">Scan asset</div>
	<div class="card-body">
		<form id="frmScan" class="form-inline">
			<input type="hidden" name="audit_id" value="<?= (int)$audit['id']; ?>">
			<input type="text" name="code" class="form-control mr-2 mb-2" placeholder="Asset code / barcode / RFID" required autofocus style="min-width:220px">
			<select name="condition_code" class="form-control mr-2 mb-2">
				<option value="">Condition —</option>
				<option value="good">good</option>
				<option value="fair">fair</option>
				<option value="poor">poor</option>
				<option value="damaged">damaged</option>
			</select>
			<input type="text" name="notes" class="form-control mr-2 mb-2" placeholder="Notes">
			<button type="submit" class="btn btn-primary mb-2"><i class="fa fa-barcode"></i> Scan</button>
		</form>
	</div>
</div>
<?php } ?>

<div class="card">
	<div class="card-body">
		<table id="tblAuditItems" class="table table-sm table-bordered table-striped" style="width:100%">
			<thead>
			<tr>
				<th>#</th>
				<th>Asset</th>
				<th>Scanned code</th>
				<th>Result</th>
				<th>Condition</th>
				<th>Notes</th>
			</tr>
			</thead>
			<tbody>
			<?php $i = 1; foreach ($items as $it) {
				$res = $it['result'] ?? 'pending';
				$badge = 'secondary';
				if ($res === 'found_ok') { $badge = 'success'; }
				elseif (in_array($res, ['wrong_location', 'wrong_custodian', 'damaged'], true)) { $badge = 'warning'; }
				elseif (in_array($res, ['not_found', 'unexpected', 'unregistered'], true)) { $badge = 'danger'; }
			?>
				<tr>
					<td><?= $i++; ?></td>
					<td><?= esc(($it['asset_code'] ?? '—') . ' — ' . ($it['asset_name'] ?? '')); ?></td>
					<td><?= esc($it['scanned_code'] ?? '—'); ?></td>
					<td><span class="badge badge-<?= $badge; ?>"><?= esc($res); ?></span></td>
					<td><?= esc($it['condition_code'] ?? '—'); ?></td>
					<td><small><?= esc($it['notes'] ?? ''); ?></small></td>
				</tr>
			<?php } ?>
			</tbody>
		</table>
	</div>
</div>

<script>
$(function () {
	if ($.fn.DataTable) $('#tblAuditItems').DataTable({ pageLength: 50, order: [[0, 'asc']] });
	$('#frmScan').on('submit', function (e) {
		e.preventDefault();
		$.post('<?= base_url('asset_management/audit_scan'); ?>', $(this).serialize(), function (res) {
			if (res.error) { toastada.error(res.error); return; }
			toastada.success(res.success || 'Scanned');
			setTimeout(function () { location.reload(); }, 500);
		}, 'json');
	});
	$('#btnCloseAudit').on('click', function () {
		if (!confirm('Close this audit? Unscanned items will be marked not found.')) return;
		$.post('<?= base_url('asset_management/close_audit'); ?>', { id: <?= (int)$audit['id']; ?> }, function (res) {
			if (res.error) { toastada.error(res.error); return; }
			toastada.success('Audit closed');
			setTimeout(function () { location.reload(); }, 600);
		}, 'json');
	});
});
</script>
