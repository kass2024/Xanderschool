<link rel="stylesheet" href="<?= base_url('assets/css/inout-report.css'); ?>?v=4">
<div class="card">
	<div class="card-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
		<h5 class="mb-0">Prep attendance</h5>
		<a class="btn btn-outline-primary btn-sm" href="<?= base_url('prep_invigilation'); ?>">Edit rota</a>
	</div>
	<div class="card-body">
		<p class="text-muted">Morning prep and evening prep. Each day lists invigilators who tapped their card and those who were on duty but did not tap.</p>
		<form method="get" action="<?= base_url('prep_invigilation_report'); ?>" class="form-inline" style="gap:12px;margin-bottom:16px;display:flex;flex-wrap:wrap;align-items:flex-end;">
			<div>
				<label for="prepDate1" style="display:block;font-weight:600;">From</label>
				<input type="date" class="form-control" id="prepDate1" name="date1" value="<?= esc($date1 ?? ''); ?>">
			</div>
			<div>
				<label for="prepDate2" style="display:block;font-weight:600;">To</label>
				<input type="date" class="form-control" id="prepDate2" name="date2" value="<?= esc($date2 ?? ''); ?>">
			</div>
			<button type="submit" class="btn btn-success">Show report</button>
			<button type="button" class="btn btn-outline-primary" onclick="window.print();">Print</button>
		</form>
		<div id="printable">
			<?php
			$days = $prep_report['days'] ?? [];
			if ($days === []) {
				echo '<div class="io-empty">No invigilators are assigned for these dates. Set the rota under Preps invigilation.</div>';
			} else {
				echo view('pages/reports/_prep_invigilators', [
					'prep_days' => $days,
					'default_day' => 0,
				]);
			}
			?>
		</div>
	</div>
</div>
