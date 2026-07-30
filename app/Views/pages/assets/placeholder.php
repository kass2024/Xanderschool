<div class="card">
	<div class="card-body text-center py-5">
		<h3 class="mb-2"><?= esc($section_label); ?></h3>
		<p class="text-muted mb-3">This screen is reserved for <strong>Phase <?= (int)$phase; ?></strong> of Asset Management.</p>
		<p class="mb-4">Foundation (locations, categories, asset register) is available now. Circulation, RFID kiosk, transfers, maintenance, audits and financial reports will unlock here without changing menu structure.</p>
		<a href="<?= base_url('asset_management/dashboard'); ?>" class="btn btn-primary">Back to dashboard</a>
		<a href="<?= base_url('asset_management/assets'); ?>" class="btn btn-outline-secondary">Open asset register</a>
	</div>
</div>
