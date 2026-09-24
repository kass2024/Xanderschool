<?php
$types = student_attendance_report_types();
if (!$types) {
	return;
}
$uri = trim(uri_string(), '/');
?>
<div class="col-12 attendance-report-type" style="clear:both;margin:0 0 16px;padding:0 15px;">
	<label for="attendanceReportType" style="display:block;font-weight:600;margin-bottom:6px;"><?= lang('app.reportType'); ?></label>
	<select id="attendanceReportType" class="form-control" style="max-width:420px;">
		<?php foreach ($types as $type):
			$path = trim($type['path'], '/');
			$active = $uri === $path || strpos($uri, $path . '/') === 0;
			?>
			<option value="<?= base_url($type['path']); ?>" <?= $active ? 'selected' : ''; ?>><?= esc($type['label']); ?></option>
		<?php endforeach; ?>
	</select>
</div>
<script>
	$(function () {
		$("#attendanceReportType").on("change", function () {
			var url = $(this).val();
			if (url) {
				window.location = url;
			}
		});
	});
</script>
