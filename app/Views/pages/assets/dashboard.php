<style>
.am-stat{border-radius:12px;padding:18px 16px;background:#fff;border:1px solid #e8edf5;box-shadow:0 1px 2px rgba(15,23,42,.04);height:100%;}
.am-stat h3{margin:0;font-size:1.55rem;font-weight:700;color:#0b1f4a;}
.am-stat span{display:block;color:#64748b;font-size:.85rem;margin-top:4px;}
.am-stat.gold{border-top:3px solid #d4af37;}
.am-stat.navy{border-top:3px solid #0b1f4a;}
.am-stat.warn{border-top:3px solid #f59e0b;}
.am-stat.danger{border-top:3px solid #ef4444;}
.am-stat.ok{border-top:3px solid #10b981;}
.am-muted{color:#64748b;}
</style>
<div class="row mb-3">
	<div class="col-md-12">
		<div class="alert alert-light border">
			<strong>Asset Management</strong> —
			Designed to support standards-aligned asset-management processes.
			Library borrowing remains available under this menu.
		</div>
	</div>
</div>
<div class="row">
	<div class="col-md-3 mb-3"><div class="am-stat navy"><h3><?= number_format((int)$stats['total_count']); ?></h3><span>Registered assets</span></div></div>
	<div class="col-md-3 mb-3"><div class="am-stat gold"><h3><?= number_format((float)$stats['total_cost'], 0); ?></h3><span>Total acquisition cost (<?= esc($school_acronym ?? 'RWF'); ?>)</span></div></div>
	<div class="col-md-3 mb-3"><div class="am-stat ok"><h3><?= number_format((float)$stats['total_nbv'], 0); ?></h3><span>Net book value</span></div></div>
	<div class="col-md-3 mb-3"><div class="am-stat warn"><h3><?= (int)$stats['draft']; ?></h3><span>Draft / incomplete</span></div></div>
</div>
<div class="row">
	<div class="col-md-2 mb-3"><div class="am-stat"><h3><?= (int)$stats['available']; ?></h3><span>Available</span></div></div>
	<div class="col-md-2 mb-3"><div class="am-stat"><h3><?= (int)$stats['assigned']; ?></h3><span>Assigned / in use</span></div></div>
	<div class="col-md-2 mb-3"><div class="am-stat"><h3><?= (int)$stats['checked_out']; ?></h3><span>Checked out</span></div></div>
	<div class="col-md-2 mb-3"><div class="am-stat warn"><h3><?= (int)$stats['maintenance']; ?></h3><span>Maintenance</span></div></div>
	<div class="col-md-2 mb-3"><div class="am-stat danger"><h3><?= (int)$stats['damaged']; ?></h3><span>Damaged</span></div></div>
	<div class="col-md-2 mb-3"><div class="am-stat danger"><h3><?= (int)$stats['missing']; ?></h3><span>Missing / stolen</span></div></div>
</div>
<div class="row mb-3">
	<div class="col-md-6">
		<div class="card">
			<div class="card-header">Quick actions</div>
			<div class="card-body">
				<a class="btn btn-primary btn-sm mr-1 mb-1" href="<?= base_url('asset_management/assets'); ?>"><i class="fa fa-plus"></i> Register asset</a>
				<a class="btn btn-outline-secondary btn-sm mr-1 mb-1" href="<?= base_url('asset_management/locations'); ?>"><i class="fa fa-map-marker-alt"></i> Locations (<?= (int)$location_count; ?>)</a>
				<a class="btn btn-outline-secondary btn-sm mr-1 mb-1" href="<?= base_url('asset_management/categories'); ?>"><i class="fa fa-tags"></i> Categories (<?= (int)$category_count; ?>)</a>
				<a class="btn btn-outline-info btn-sm mb-1" href="<?= base_url('book_management'); ?>"><i class="fa fa-book"></i> Library</a>
			</div>
		</div>
	</div>
	<div class="col-md-6">
		<div class="card">
			<div class="card-header">Catalogue summary</div>
			<div class="card-body am-muted">
				<p class="mb-1"><?= (int)$location_count; ?> active locations · <?= (int)$category_count; ?> categories</p>
				<p class="mb-0">Phase 1 covers register, locations, categories and history. Circulation, RFID kiosk, transfers and audits follow in later phases.</p>
			</div>
		</div>
	</div>
</div>
<div class="card">
	<div class="card-header d-flex justify-content-between align-items-center">
		<span>Recent assets</span>
		<a href="<?= base_url('asset_management/assets'); ?>">View all</a>
	</div>
	<div class="card-body p-0">
		<div class="table-responsive">
			<table class="table table-striped mb-0">
				<thead>
				<tr>
					<th>Code</th>
					<th>Name</th>
					<th>Category</th>
					<th>Location</th>
					<th>Status</th>
					<th>Cost</th>
				</tr>
				</thead>
				<tbody>
				<?php if (empty($recent)) { ?>
					<tr><td colspan="6" class="text-center text-muted py-4">No assets registered yet.</td></tr>
				<?php } else { foreach ($recent as $a) { ?>
					<tr>
						<td><a href="<?= base_url('asset_management/asset_view/' . $a['id']); ?>"><?= esc($a['asset_code']); ?></a></td>
						<td><?= esc($a['name']); ?></td>
						<td><?= esc($a['category_name'] ?? '—'); ?></td>
						<td><?= esc($a['location_name'] ?? '—'); ?></td>
						<td><span class="badge badge-secondary"><?= esc($a['lifecycle_status']); ?></span></td>
						<td><?= number_format((float)$a['total_acquisition_cost'], 0); ?></td>
					</tr>
				<?php } } ?>
				</tbody>
			</table>
		</div>
	</div>
</div>
