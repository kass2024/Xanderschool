<style>
.tt-bulk-page { page-break-after: always; padding-bottom: 12px; }
.tt-bulk-page:last-child { page-break-after: auto; }
.tt-bulk-cover { page-break-after: always; padding-bottom: 12px; }
</style>
<?php if (!empty($include_cover) && !empty($cover_title) && !empty($letterhead)): ?>
	<div class="tt-bulk-cover">
		<?php
		$subtitle = $cover_title;
		$title = strtoupper($school_name ?? 'SCHOOL');
		$generated_at = date('Y-m-d H:i:s');
		echo view('pages/timetable/_letterhead', get_defined_vars());
		?>
	</div>
<?php endif; ?>
<?php foreach ($sheets as $sheet): ?>
	<div class="tt-bulk-page">
		<?= view('pages/timetable/_grid_body', $sheet); ?>
	</div>
<?php endforeach; ?>
