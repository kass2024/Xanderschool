<?php /** @var array<string,mixed> $sheet */ ?>
<?php
$ttBack = site_url('dashboard');
$ttBackLabel = 'Back';
if (function_exists('menu_clearance_allowed') && menu_clearance_allowed('timetable_dashboard')) {
	$ttBack = site_url('timetable/dashboard');
	$ttBackLabel = 'Back to timetable';
} elseif (function_exists('pedagogical_own_work_only') && pedagogical_own_work_only()) {
	$ttBack = site_url('timetable/mine');
	$ttBackLabel = 'My timetable';
}
?>
<div class="tt-overall-actions mb-2">
	<a href="<?= $ttBack; ?>" class="btn btn-sm btn-outline-secondary"><?= esc($ttBackLabel); ?></a>
</div>
<?= view('pages/timetable/_overall_body', $sheet ?? []); ?>
