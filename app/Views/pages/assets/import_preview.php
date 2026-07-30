<?php
$canCommit = ($import['mode'] ?? '') !== 'validate_only' && ($import['status'] ?? '') !== 'committed';
?>
<div class="mb-3">
	<a href="<?= base_url('asset_management/import'); ?>" class="btn btn-light"><i class="fa fa-arrow-left"></i> Back to import</a>
	<?php if ($canCommit) { ?>
		<button type="button" class="btn btn-success" id="btnCommit"><i class="fa fa-check"></i> Commit import</button>
	<?php } ?>
</div>

<div class="row mb-3">
	<div class="col-md-3"><div class="card text-center"><div class="card-body py-3"><h4 class="mb-0"><?= (int)$import['total_rows']; ?></h4><small class="text-muted">Total rows</small></div></div></div>
	<div class="col-md-3"><div class="card text-center border-success"><div class="card-body py-3"><h4 class="mb-0 text-success"><?= (int)$import['valid_rows']; ?></h4><small class="text-muted">Valid</small></div></div></div>
	<div class="col-md-3"><div class="card text-center border-warning"><div class="card-body py-3"><h4 class="mb-0 text-warning"><?= (int)$import['warning_rows']; ?></h4><small class="text-muted">Warnings</small></div></div></div>
	<div class="col-md-3"><div class="card text-center border-danger"><div class="card-body py-3"><h4 class="mb-0 text-danger"><?= (int)$import['error_rows']; ?></h4><small class="text-muted">Errors</small></div></div></div>
</div>

<div class="card mb-3">
	<div class="card-body py-2">
		<strong>Batch #<?= (int)$import['id']; ?></strong> — <?= esc($import['filename']); ?>
		&nbsp;|&nbsp; Mode: <code><?= esc($import['mode']); ?></code>
		&nbsp;|&nbsp; Status: <span class="badge badge-secondary"><?= esc($import['status']); ?></span>
	</div>
</div>

<div class="card">
	<div class="card-body">
		<table id="tblPreview" class="table table-sm table-bordered table-striped" style="width:100%">
			<thead>
			<tr>
				<th>Row</th>
				<th>Status</th>
				<th>Asset code</th>
				<th>Errors</th>
				<th>Payload</th>
			</tr>
			</thead>
			<tbody>
			<?php foreach ($rows as $r) {
				$errs = [];
				if (!empty($r['errors_json'])) {
					$decoded = json_decode($r['errors_json'], true);
					if (is_array($decoded)) { $errs = $decoded; }
				}
				$snippet = '—';
				if (!empty($r['payload_json'])) {
					$payload = json_decode($r['payload_json'], true);
					if (is_array($payload)) {
						$parts = [];
						foreach (['name', 'category_code', 'location_code', 'serial_number'] as $k) {
							if (!empty($payload[$k])) { $parts[] = $k . ': ' . $payload[$k]; }
						}
						$snippet = $parts ? implode('; ', $parts) : substr($r['payload_json'], 0, 120);
					}
				}
				$st = $r['status'] ?? '';
				$badge = 'secondary';
				if ($st === 'valid' || $st === 'imported') { $badge = 'success'; }
				elseif ($st === 'warning') { $badge = 'warning'; }
				elseif ($st === 'error') { $badge = 'danger'; }
			?>
				<tr>
					<td><?= (int)$r['row_number']; ?></td>
					<td><span class="badge badge-<?= $badge; ?>"><?= esc($st); ?></span></td>
					<td><?= esc($r['asset_code'] ?? '—'); ?></td>
					<td><small><?= esc(is_array($errs) ? implode('; ', $errs) : (string)$errs); ?></small></td>
					<td><small><?= esc($snippet); ?></small></td>
				</tr>
			<?php } ?>
			</tbody>
		</table>
	</div>
</div>

<script>
$(function () {
	if ($.fn.DataTable) {
		$('#tblPreview').DataTable({ pageLength: 50, order: [[0, 'asc']] });
	}
	$('#btnCommit').on('click', function () {
		if (!confirm('Commit this import to the asset register?')) return;
		var btn = $(this);
		btn.prop('disabled', true);
		$.post('<?= base_url('asset_management/commit_import'); ?>', { import_id: <?= (int)$import['id']; ?> }, function (res) {
			btn.prop('disabled', false);
			if (res.error) { toastada.error(res.error); return; }
			toastada.success(res.success || 'Import committed');
			setTimeout(function () { location.reload(); }, 800);
		}, 'json').fail(function () {
			btn.prop('disabled', false);
			toastada.error('Commit failed');
		});
	});
});
</script>
