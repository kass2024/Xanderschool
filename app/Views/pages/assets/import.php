<div class="row mb-3">
	<div class="col-md-6">
		<a href="<?= base_url('asset_management/download_import_template'); ?>" class="btn btn-outline-primary mb-2">
			<i class="fa fa-download"></i> Download template
		</a>
		<a href="<?= base_url('asset_management/assets'); ?>" class="btn btn-light mb-2">Back to register</a>
	</div>
</div>

<div class="row">
	<div class="col-md-5">
		<div class="card mb-3">
			<div class="card-header">Upload spreadsheet</div>
			<div class="card-body">
				<form id="frmImport" enctype="multipart/form-data">
					<div class="form-group">
						<label>Excel / CSV file *</label>
						<input type="file" name="documents" class="form-control-file" accept=".xlsx,.xls,.csv" required>
					</div>
					<div class="form-group">
						<label>Import mode</label>
						<select name="mode" class="form-control">
							<option value="create_only">Create only (skip existing codes)</option>
							<option value="create_update">Create or update</option>
							<option value="validate_only">Validate only (no commit)</option>
						</select>
					</div>
					<button type="submit" class="btn btn-primary"><i class="fa fa-upload"></i> Upload &amp; validate</button>
				</form>
			</div>
		</div>
	</div>
	<div class="col-md-7">
		<div class="card">
			<div class="card-header">Recent imports</div>
			<div class="card-body p-0">
				<table id="tblImports" class="table table-sm table-striped mb-0" style="width:100%">
					<thead>
					<tr>
						<th>#</th>
						<th>File</th>
						<th>Mode</th>
						<th>Status</th>
						<th>Rows</th>
						<th>Valid</th>
						<th>Errors</th>
						<th>When</th>
						<th></th>
					</tr>
					</thead>
					<tbody>
					<?php foreach ($imports as $imp) { ?>
						<tr>
							<td><?= (int)$imp['id']; ?></td>
							<td><?= esc($imp['filename']); ?></td>
							<td><?= esc($imp['mode']); ?></td>
							<td><?= esc($imp['status']); ?></td>
							<td><?= (int)$imp['total_rows']; ?></td>
							<td><?= (int)$imp['valid_rows']; ?></td>
							<td><?= (int)$imp['error_rows']; ?></td>
							<td><?= esc($imp['created_at'] ?? '—'); ?></td>
							<td>
								<a class="btn btn-sm btn-outline-secondary" href="<?= base_url('asset_management/import_preview/' . $imp['id']); ?>">Preview</a>
							</td>
						</tr>
					<?php } ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>

<script>
$(function () {
	if ($.fn.DataTable) $('#tblImports').DataTable({ pageLength: 10, order: [[0, 'desc']] });
	$('#frmImport').on('submit', function (e) {
		e.preventDefault();
		var btn = $(this).find('[type=submit]');
		var txt = btn.html();
		btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Uploading…');
		var fd = new FormData(this);
		$.ajax({
			url: '<?= base_url('asset_management/upload_import'); ?>',
			type: 'POST',
			data: fd,
			processData: false,
			contentType: false,
			dataType: 'json'
		}).done(function (res) {
			btn.prop('disabled', false).html(txt);
			if (res.error) { toastada.error(res.error); return; }
			toastada.success('Import validated — opening preview…');
			if (res.preview_url) {
				setTimeout(function () { location.href = res.preview_url; }, 500);
			} else {
				setTimeout(function () { location.reload(); }, 800);
			}
		}).fail(function () {
			btn.prop('disabled', false).html(txt);
			toastada.error('Upload failed');
		});
	});
});
</script>
