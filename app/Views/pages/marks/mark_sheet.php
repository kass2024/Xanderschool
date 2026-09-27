<?php
/** @var array $years */
/** @var bool $sees_all */
/** @var int $current_year */
?>
<style>
	.ms-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:1.1rem 1.2rem; max-width:920px; }
	.ms-card h4 { margin:0 0 .35rem; font-weight:700; color:#0f2744; }
	.ms-card p { margin:0 0 1rem; color:#64748b; font-size:.9rem; }
	.ms-grid { display:flex; flex-wrap:wrap; gap:.75rem; align-items:flex-end; }
	.ms-grid .form-group { margin:0; min-width:180px; flex:1; }
</style>
<div class="ms-card">
	<h4>Continuous assessment mark sheet</h4>
	<p><?= $sees_all
		? 'Choose the academic year, term, and any teacher’s course. The sheet lists that class and every quiz, homework, and test saved for the term.'
		: 'Choose the academic year, term, and one of your courses. You only see classes and subjects assigned to you.'; ?></p>
	<form action="<?= base_url('get_uploaded_marks/1'); ?>" method="post" target="_blank" id="markSheetForm">
		<div class="ms-grid">
			<div class="form-group">
				<label>Academic year</label>
				<select class="form-control" name="year" id="msYear" required>
					<option value="" disabled <?= $current_year ? '' : 'selected'; ?>>Select year</option>
					<?php foreach ($years as $year): ?>
						<option value="<?= (int) $year['id']; ?>" <?= (int) $year['id'] === (int) $current_year ? 'selected' : ''; ?>><?= esc($year['title']); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="form-group">
				<label>Term</label>
				<select class="form-control" name="term" id="msTerm" required>
					<option value="1">Term 1</option>
					<option value="2">Term 2</option>
					<option value="3">Term 3</option>
				</select>
			</div>
			<?php if ($sees_all): ?>
			<div class="form-group">
				<label>Teacher</label>
				<select class="form-control" id="msTeacher">
					<option value="0">All teachers</option>
				</select>
			</div>
			<?php endif; ?>
			<div class="form-group" style="flex:2;min-width:260px;">
				<label>Course</label>
				<select class="form-control" name="record_id" id="msCourse" required>
					<option value="">Select a course</option>
				</select>
			</div>
			<div class="form-group">
				<button class="btn btn-success" type="submit">Generate mark sheet</button>
			</div>
		</div>
	</form>
</div>
<script>
(function () {
	var seesAll = <?= $sees_all ? 'true' : 'false'; ?>;
	var courses = [];
	function termMatches(raw, term) {
		var parts = String(raw || '').split(',');
		if (parts.length === 1 && parts[0].trim() === '') return true;
		for (var i = 0; i < parts.length; i++) {
			if (parts[i].trim() === String(term)) return true;
		}
		return false;
	}
	function paintTeachers() {
		if (!seesAll) return;
		var term = $('#msTerm').val();
		var current = String($('#msTeacher').val() || '0');
		var teachers = {};
		courses.forEach(function (row) {
			if (!termMatches(row.term, term)) return;
			teachers[String(row.lecturer)] = row.teacher;
		});
		var html = '<option value="0">All teachers</option>';
		Object.keys(teachers).sort(function (a, b) {
			return String(teachers[a]).localeCompare(String(teachers[b]));
		}).forEach(function (id) {
			html += '<option value="' + id + '">' + teachers[id] + '</option>';
		});
		$('#msTeacher').html(html);
		if ($('#msTeacher option[value="' + current + '"]').length) {
			$('#msTeacher').val(current);
		}
	}
	function paintCourses() {
		var term = $('#msTerm').val();
		var teacher = seesAll ? String($('#msTeacher').val() || '0') : '0';
		var options = '<option value="">Select a course</option>';
		courses.forEach(function (row) {
			if (!termMatches(row.term, term)) return;
			if (teacher !== '0' && String(row.lecturer) !== teacher) return;
			options += '<option value="' + row.id + '">' + row.label + '</option>';
		});
		$('#msCourse').html(options);
	}
	function paint() {
		paintTeachers();
		paintCourses();
	}
	function load() {
		var year = $('#msYear').val();
		if (!year) return;
		$.getJSON('<?= base_url('mark_sheet_courses'); ?>', { year: year }).done(function (res) {
			courses = (res && res.courses) ? res.courses : [];
			paint();
		});
	}
	$('#msYear').on('change', load);
	$('#msTerm').on('change', paint);
	$('#msTeacher').on('change', paintCourses);
	load();
})();
</script>
