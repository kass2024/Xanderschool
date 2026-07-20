<?php
/**
 * Inline-edit Scheme of Work / academic plan before Word/PDF download.
 * @var array $plan
 */
include __DIR__ . '/_nav.php';
$html = (string) ($plan['content_html'] ?? '');
$bodyHtml = $html;
if (preg_match('/<body[^>]*>([\s\S]*)<\/body>/i', $html, $m)) {
	$bodyHtml = $m[1];
}
?>
<style>
	.sow-edit-wrap { max-width: 1280px; }
	.sow-edit-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:1rem 1.15rem; margin-bottom:1rem; }
	.sow-edit-toolbar { display:flex; flex-wrap:wrap; gap:.5rem; align-items:center; margin-bottom:.75rem; }
	.sow-edit-status { font-size:.88rem; color:#64748b; }
	#sowEditor {
		min-height: 520px; border:1px solid #cbd5e1; border-radius:8px; padding:1rem;
		background:#fff; overflow:auto; outline:none;
	}
	#sowEditor:focus { border-color:#0ea5e9; box-shadow:0 0 0 3px rgba(14,165,233,.15); }
	#sowEditor table { width:100%; border-collapse:collapse; }
	#sowEditor td, #sowEditor th { border:1px solid #333; padding:5px; vertical-align:top; font-size:11px; }
	#sowEditor th { background:#f3f3f3; }
	#sowEditor h1 { text-align:center; font-size:18px; }
	.sow-hint { font-size:.84rem; color:#64748b; margin:0 0 .75rem; }
</style>

<div class="sow-edit-wrap">
	<div class="sow-edit-card">
		<h5 class="mb-2">Edit before download</h5>
		<p class="sow-hint">
			Click any cell or text to edit inline. Save to update the database cache, then download Word or PDF.
		</p>
		<div class="form-group">
			<label>Title</label>
			<input type="text" class="form-control" id="sowTitle" value="<?= esc($plan['title'] ?? ''); ?>">
		</div>
		<div class="sow-edit-toolbar">
			<button type="button" class="btn btn-primary" id="btnSaveSow"><i class="fa fa-save"></i> Save</button>
			<a class="btn btn-outline-success" id="btnWord" href="<?= base_url('download_academic_plan/' . (int)$plan['id']); ?>">
				<i class="fa fa-file-word-o"></i> Download Word
			</a>
			<a class="btn btn-outline-danger" id="btnPdf" href="<?= base_url('download_academic_plan_pdf/' . (int)$plan['id']); ?>">
				<i class="fa fa-file-pdf-o"></i> Download PDF
			</a>
			<a class="btn btn-outline-secondary" target="_blank" href="<?= base_url('view_academic_plan/' . (int)$plan['id']); ?>">Preview</a>
			<a class="btn btn-link" href="<?= base_url('ped_scheme_of_work'); ?>">← Back to Scheme of Work</a>
		</div>
		<p class="sow-edit-status mb-2" id="sowEditStatus">Double-click a cell to focus editing.</p>
		<div id="sowEditor" contenteditable="true"><?= $bodyHtml; ?></div>
	</div>
</div>

<script>
(function ($) {
	var planId = <?= (int) ($plan['id'] ?? 0); ?>;
	function status(msg, err) {
		$('#sowEditStatus').css('color', err ? '#b91c1c' : '#0f766e').text(msg || '');
	}
	$('#btnSaveSow').on('click', function () {
		var $btn = $(this).prop('disabled', true);
		status('Saving…');
		$.post('<?= base_url('save_academic_plan'); ?>', {
			plan_id: planId,
			title: $('#sowTitle').val(),
			content_html: $('#sowEditor').html()
		}, function (res) {
			$btn.prop('disabled', false);
			if (res && res.error) { status(res.error, true); return; }
			status('Saved. You can download Word or PDF now.');
			if (res.word_url) $('#btnWord').attr('href', res.word_url);
			if (res.pdf_url) $('#btnPdf').attr('href', res.pdf_url);
		}, 'json').fail(function (xhr) {
			$btn.prop('disabled', false);
			status((xhr.responseJSON && xhr.responseJSON.error) || 'Save failed', true);
		});
	});
})(jQuery);
</script>
