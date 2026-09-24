<?php
$types = student_attendance_report_types();
if (!$types) {
	return;
}
$uri = trim(uri_string(), '/');
$wisdomCampuses = [];
$currentSchoolId = (int) ($_SESSION['soma_school_id'] ?? 0);
try {
	$wisdomOverview = new \App\Services\WisdomGroupOverview();
	$homeSchoolId = function_exists('school_hierarchy_home_id') ? (int) school_hierarchy_home_id() : $currentSchoolId;
	$postId = (int) ($_SESSION['soma_post'] ?? 0);
	if ($wisdomOverview->isLeaderPost($postId) && $wisdomOverview->isWisdomMaster($homeSchoolId)) {
		$wisdomCampuses = $wisdomOverview->schools($homeSchoolId);
	}
} catch (\Throwable $e) {
	$wisdomCampuses = [];
}
?>
<div class="col-12 attendance-report-type" style="clear:both;margin:0 0 16px;padding:0 15px;">
	<?php if (count($wisdomCampuses) > 1): ?>
	<label for="attendanceReportSchool" style="display:block;font-weight:600;margin-bottom:6px;">School</label>
	<select id="attendanceReportSchool" class="form-control" style="max-width:420px;margin-bottom:12px;">
		<?php foreach ($wisdomCampuses as $campus): ?>
			<option value="<?= (int) $campus['id']; ?>" <?= (int) $campus['id'] === $currentSchoolId ? 'selected' : ''; ?>><?= esc($campus['name']); ?></option>
		<?php endforeach; ?>
	</select>
	<?php endif; ?>
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
		$("#attendanceReportSchool").on("change", function () {
			var id = $(this).val();
			if (!id) {
				return;
			}
			window.location = "<?= base_url('switch-school'); ?>/" + id + "?next=" + encodeURIComponent("<?= esc(trim(uri_string(), '/'), 'js'); ?>");
		});
	});
</script>
