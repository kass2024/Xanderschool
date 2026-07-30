<div class="card">
	<div class="card-header">Asset code &amp; defaults</div>
	<div class="card-body">
		<form id="frmAssetSettings">
			<div class="form-group">
				<label>Asset code pattern</label>
				<input type="text" class="form-control" name="code_pattern" value="<?= esc($settings['code_pattern'] ?? 'AST-{CATEGORY}-{YEAR}-{SEQ}'); ?>">
				<small class="text-muted">Tokens: {CATEGORY}, {YEAR}, {SEQ}. Current generator uses AST-{CATEGORY}-{YEAR}-{SEQ}.</small>
			</div>
			<div class="form-group">
				<label>Sequence padding</label>
				<input type="number" class="form-control" name="seq_padding" min="3" max="10" value="<?= (int)($settings['seq_padding'] ?? 6); ?>">
			</div>
			<div class="form-group">
				<label>Default currency</label>
				<input type="text" class="form-control" name="default_currency" value="<?= esc($settings['default_currency'] ?? 'RWF'); ?>">
			</div>
			<button type="submit" class="btn btn-primary">Save settings</button>
		</form>
	</div>
</div>
<script>
$('#frmAssetSettings').on('submit', function (e) {
	e.preventDefault();
	$.post('<?= base_url('asset_management/save_settings'); ?>', $(this).serialize(), function (res) {
		if (res.error) { toastada.error(res.error); return; }
		toastada.success(res.success);
	}, 'json');
});
</script>
