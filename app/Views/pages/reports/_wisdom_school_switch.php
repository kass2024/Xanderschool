<?php
helper('qonics');
$wisdomCampuses = wisdom_export_schools();
$currentSchoolId = (int) ($_SESSION['soma_school_id'] ?? 0);
if (count($wisdomCampuses) < 2) {
	return;
}
$next = trim(uri_string(), '/');
if ($next === '' || $next === 'dashboard') {
	$next = 'staffs';
}
?>
<div class="col-12" style="clear:both;margin:0 0 12px;padding:0 15px;">
	<label for="wisdomSchoolSwitch" style="display:block;font-weight:600;margin-bottom:6px;">Export from school</label>
	<select id="wisdomSchoolSwitch" class="form-control" style="max-width:420px;">
		<?php foreach ($wisdomCampuses as $campus): ?>
			<option value="<?= (int) $campus['id']; ?>" <?= (int) $campus['id'] === $currentSchoolId ? 'selected' : ''; ?>><?= esc($campus['name']); ?></option>
		<?php endforeach; ?>
	</select>
</div>
<script>
	$(function () {
		$("#wisdomSchoolSwitch").on("change", function () {
			var id = $(this).val();
			if (!id) {
				return;
			}
			window.location = "<?= base_url('switch-school'); ?>/" + id + "?next=" + encodeURIComponent("<?= esc($next, 'js'); ?>");
		});
	});
</script>
