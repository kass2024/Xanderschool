<div class="row mb-3">
	<div class="col-md-5">
		<div class="card">
			<div class="card-header">Start new audit</div>
			<div class="card-body">
				<form id="frmAudit">
					<div class="form-group">
						<label>Title</label>
						<input type="text" name="title" class="form-control" placeholder="Audit <?= date('Y-m-d'); ?>">
					</div>
					<div class="form-group">
						<label>Location scope</label>
						<select name="location_id" class="form-control">
							<option value="">All locations</option>
							<?php foreach ($locations as $l) { if ((int)$l['status'] !== 1) continue; ?>
								<option value="<?= (int)$l['id']; ?>"><?= esc($l['location_code'] . ' — ' . $l['name']); ?></option>
							<?php } ?>
						</select>
					</div>
					<div class="form-group">
						<label>Category scope</label>
						<select name="category_id" class="form-control">
							<option value="">All categories</option>
							<?php foreach ($categories as $c) { ?>
								<option value="<?= (int)$c['id']; ?>"><?= esc($c['category_code'] . ' — ' . $c['name']); ?></option>
							<?php } ?>
						</select>
					</div>
					<button type="submit" class="btn btn-primary"><i class="fa fa-play"></i> Create audit</button>
				</form>
			</div>
		</div>
	</div>
	<div class="col-md-7">
		<div class="card">
			<div class="card-header">Audit history</div>
			<div class="card-body p-0">
				<table id="tblAudits" class="table table-striped table-sm mb-0" style="width:100%">
					<thead>
					<tr>
						<th>#</th>
						<th>Audit no</th>
						<th>Title</th>
						<th>Status</th>
						<th>Items</th>
						<th>Created</th>
						<th></th>
					</tr>
					</thead>
					<tbody>
					<?php $i = 1; foreach ($audits as $au) { ?>
						<tr>
							<td><?= $i++; ?></td>
							<td><?= esc($au['audit_no']); ?></td>
							<td><?= esc($au['title']); ?></td>
							<td><?= esc($au['status']); ?></td>
							<td><?= (int)($au['item_count'] ?? 0); ?></td>
							<td><?= esc($au['created_at'] ?? '—'); ?></td>
							<td>
								<a class="btn btn-sm btn-outline-primary" href="<?= base_url('asset_management/audit_view/' . $au['id']); ?>">Open</a>
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
	if ($.fn.DataTable) $('#tblAudits').DataTable({ pageLength: 15, order: [[0, 'desc']] });
	$('#frmAudit').on('submit', function (e) {
		e.preventDefault();
		$.post('<?= base_url('asset_management/save_audit'); ?>', $(this).serialize(), function (res) {
			if (res.error) { toastada.error(res.error); return; }
			toastada.success('Audit created');
			if (res.audit_id) {
				setTimeout(function () { location.href = '<?= base_url('asset_management/audit_view/'); ?>' + res.audit_id; }, 600);
			} else {
				setTimeout(function () { location.reload(); }, 600);
			}
		}, 'json');
	});
});
</script>
