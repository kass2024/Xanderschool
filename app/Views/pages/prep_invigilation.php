<div class="card">
	<div class="card-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
		<h5 class="mb-0">Preps invigilation</h5>
		<a class="btn btn-outline-primary btn-sm" href="<?= base_url('prep_invigilation_report'); ?>">Attendance report</a>
	</div>
	<div class="card-body" id="prepInvigilation">
		<p class="text-muted">Morning and evening prep duty only. This is not the class timetable. Staff on duty that day must tap their card at the Morning prep or Evening prep reader. The attendance report shows who was present and who was absent.</p>
		<?= view('pages/partials/prep_timetable_settings', [
			'prep_timetable' => $prep_timetable ?? [],
			'prep_staff' => $prep_staff ?? [],
		]); ?>
	</div>
</div>
