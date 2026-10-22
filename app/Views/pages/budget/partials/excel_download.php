<?php
$budget = $budget ?? [];
$meta = \App\Services\Budget\WisdomBudgetWorkbookImportService::workbookFile($budget);
if (!$meta) {
	return;
}
$btnClass = $btn_class ?? 'btn btn-sm btn-outline-success mb-1';
$budgetId = (int) ($budget['id'] ?? $budget['budget_id'] ?? 0);
$uploader = trim((string) ($budget['uploader_name'] ?? ''));
$showName = !empty($show_name);
?>
<?php if ($showName) { ?>
<div class="small text-muted mb-2">
	<i class="fa fa-file-excel text-success"></i> <?= esc($meta['name']); ?>
	<?php if ($uploader !== '') { ?> · <?= esc($uploader); ?><?php } ?>
</div>
<?php } ?>
<a class="<?= esc($btnClass, 'attr'); ?>" href="<?= base_url('budget/download_prepared_excel/' . $budgetId); ?>" title="<?= esc($uploader !== '' ? ($meta['name'] . ' · ' . $uploader) : $meta['name']); ?>">
	<i class="fa fa-download"></i> Download Excel
</a>
