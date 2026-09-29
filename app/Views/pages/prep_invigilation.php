<div class="card">
	<div class="card-header">
		<h5 class="mb-0">Preps invigilation</h5>
	</div>
	<div class="card-body" id="prepInvigilation">
		<p class="text-muted">Morning and evening prep duty only. This is not the class timetable.</p>
		<?= view('pages/partials/prep_timetable_settings', [
			'prep_timetable' => $prep_timetable ?? [],
			'prep_staff' => $prep_staff ?? [],
		]); ?>
	</div>
</div>
