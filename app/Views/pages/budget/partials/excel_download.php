<?php
$budget = $budget ?? [];
$meta = \App\Services\Budget\WisdomBudgetWorkbookImportService::workbookFile($budget);
if (!$meta) {
	return;
}
$budgetId = (int) ($budget['id'] ?? $budget['budget_id'] ?? 0);
$uploader = trim((string) ($budget['uploader_name'] ?? ''));
$href = base_url('budget/download_prepared_excel/' . $budgetId);
$showName = !empty($show_name);
$btnClass = $btn_class ?? 'btn btn-sm btn-success mb-1';
?>
<?php if ($showName) { ?>
<a class="d-inline-block small mt-1 mb-1" href="<?= $href; ?>" style="color:#157347;font-weight:700;" title="Download this Excel">
	<i class="fa fa-download"></i> <?= esc($meta['name']); ?><?php if ($uploader !== '') { ?> · <?= esc($uploader); ?><?php } ?>
</a>
<?php } else { ?>
<a class="<?= esc($btnClass, 'attr'); ?>" href="<?= $href; ?>" style="color:#fff;background:#198754;border-color:#198754;" title="<?= esc($uploader !== '' ? ($meta['name'] . ' · ' . $uploader) : $meta['name']); ?>">
	<i class="fa fa-download"></i> Download
</a>
<?php } ?>
