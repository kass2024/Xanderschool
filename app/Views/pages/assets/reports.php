<?php
$sections = [
	'by_location' => 'Assets by location',
	'by_category' => 'Assets by category',
	'by_custodian' => 'Assets by custodian',
	'overdue_loans' => 'Overdue loans',
	'maintenance_due' => 'Maintenance due (30 days)',
	'warranty_expiry' => 'Warranty expiring (30 days)',
	'missing_damaged' => 'Missing / damaged assets',
	'depreciation_schedule' => 'Depreciation schedule',
];
?>

<div class="card mb-3">
	<div class="card-body">
		<form id="frmDepr" class="form-inline">
			<label class="mr-2">Run straight-line depreciation for period:</label>
			<input type="month" name="period_ym" class="form-control mr-2" value="<?= esc($period_ym ?? date('Y-m')); ?>" required>
			<button type="submit" class="btn btn-warning"><i class="fa fa-calculator"></i> Run depreciation</button>
		</form>
	</div>
</div>

<?php foreach ($sections as $key => $label) {
	$rows = isset($report[$key]) ? $report[$key] : [];
	$count = count($rows);
?>
<div class="card mb-3">
	<div class="card-header d-flex justify-content-between align-items-center">
		<span><?= esc($label); ?> <span class="badge badge-light"><?= $count; ?></span></span>
		<a href="<?= base_url('asset_management/export_report_csv?type=' . $key); ?>" class="btn btn-sm btn-outline-secondary"><i class="fa fa-download"></i> CSV</a>
	</div>
	<div class="card-body p-0">
		<?php if (empty($rows)) { ?>
			<p class="text-muted p-3 mb-0">No data.</p>
		<?php } else { ?>
			<div class="table-responsive">
				<table class="table table-sm table-striped mb-0 tbl-report">
					<thead>
					<tr>
						<?php foreach (array_keys($rows[0]) as $col) { ?>
							<th><?= esc(str_replace('_', ' ', $col)); ?></th>
						<?php } ?>
					</tr>
					</thead>
					<tbody>
					<?php foreach (array_slice($rows, 0, 50) as $row) { ?>
						<tr>
							<?php foreach ($row as $val) {
								if (is_numeric($val) && strpos((string)$val, '.') !== false) {
									$display = number_format((float)$val, 0);
								} else {
									$display = $val;
								}
							?>
								<td><?= esc((string)$display); ?></td>
							<?php } ?>
						</tr>
					<?php } ?>
					</tbody>
				</table>
			</div>
			<?php if ($count > 50) { ?>
				<p class="text-muted small p-2 mb-0">Showing first 50 of <?= $count; ?> rows — use CSV export for full data.</p>
			<?php } ?>
		<?php } ?>
	</div>
</div>
<?php } ?>

<script>
$(function () {
	if ($.fn.DataTable) {
		$('.tbl-report').each(function () {
			if ($(this).find('tbody tr').length > 5) {
				$(this).DataTable({ pageLength: 10, searching: true, ordering: true });
			}
		});
	}
	$('#frmDepr').on('submit', function (e) {
		e.preventDefault();
		if (!confirm('Run depreciation for ' + $(this).find('[name=period_ym]').val() + '?')) return;
		$.post('<?= base_url('asset_management/run_depreciation'); ?>', $(this).serialize(), function (res) {
			if (res.error) { toastada.error(res.error); return; }
			toastada.success(res.success || 'Depreciation run completed');
			setTimeout(function () { location.reload(); }, 800);
		}, 'json');
	});
});
</script>
