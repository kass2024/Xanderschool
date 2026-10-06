<style>
	.lock-marks-page { padding: 8px 4px 24px; }
	.lock-marks-page h4 { margin: 0 0 14px; }
	.lock-school-bar {
		display: flex;
		justify-content: space-between;
		align-items: center;
		gap: 12px;
		flex-wrap: wrap;
		margin-bottom: 14px;
	}
	.lock-school-actions { display: flex; gap: 8px; flex-wrap: wrap; }
	.lock-school-actions .btn { color: #fff; }
	.lock-marks-search { max-width: 360px; margin: 0 0 14px; }
	.lock-school-bar .lock-marks-search { margin: 0; }
	.lock-teacher {
		background: #fff;
		border: 1px solid #e5e7eb;
		border-radius: 10px;
		margin-bottom: 12px;
		overflow: hidden;
	}
	.lock-teacher-head {
		display: flex;
		justify-content: space-between;
		align-items: center;
		gap: 12px;
		padding: 12px 14px;
		background: #0f2744;
		color: #fff;
	}
	.lock-teacher-head strong { font-size: 15px; }
	.lock-teacher-actions { display: flex; gap: 8px; flex-wrap: wrap; }
	.lock-teacher-actions .btn { color: #fff; }
	.lock-course {
		display: flex;
		justify-content: space-between;
		align-items: center;
		gap: 12px;
		padding: 12px 14px;
		border-top: 1px solid #eef2f7;
	}
	.lock-course.is-locked { background: #fff7ed; }
	.lock-course small { display: block; color: #6b7280; }
	.lock-pill {
		display: inline-block;
		font-size: 12px;
		font-weight: 700;
		border-radius: 999px;
		padding: 2px 8px;
		margin-left: 6px;
	}
	.lock-pill.on { background: #fee2e2; color: #991b1b; }
	.lock-pill.off { background: #dcfce7; color: #166534; }
</style>
<div class="lock-marks-page">
	<?php if (!empty($denied)): ?>
		<div class="alert alert-danger">Only the Coordinator, Director, or Head Teacher can lock marks editing.</div>
	<?php else: ?>
		<h4>Lock Marks editing</h4>
		<?php if (!empty($teachers)): ?>
			<div class="lock-school-bar">
				<input type="search" class="form-control lock-marks-search" id="lockMarksSearch" placeholder="Search teacher or course">
				<div class="lock-school-actions">
					<button type="button" class="btn btn-danger btn-lock-school" data-locked="1">Lock all teachers</button>
					<button type="button" class="btn btn-success btn-lock-school" data-locked="0">Unlock all teachers</button>
				</div>
			</div>
		<?php else: ?>
			<input type="search" class="form-control lock-marks-search" id="lockMarksSearch" placeholder="Search teacher or course">
		<?php endif; ?>
		<?php if (empty($teachers)): ?>
			<div class="alert alert-info">No teachers have courses assigned for this term.</div>
		<?php endif; ?>
		<?php foreach ($teachers as $teacher): ?>
			<section class="lock-teacher" data-teacher="<?= esc(strtolower($teacher['name'])); ?>">
				<div class="lock-teacher-head">
					<strong><?= esc($teacher['name']); ?></strong>
					<div class="lock-teacher-actions">
						<button type="button" class="btn btn-sm btn-danger btn-lock-all" data-staff="<?= (int) $teacher['id']; ?>" data-locked="1">Lock all courses</button>
						<button type="button" class="btn btn-sm btn-success btn-lock-all" data-staff="<?= (int) $teacher['id']; ?>" data-locked="0">Unlock all courses</button>
					</div>
				</div>
				<?php foreach ($teacher['courses'] as $course):
					$isLocked = !empty($course['locked']);
					$hay = strtolower($teacher['name'] . ' ' . $course['title'] . ' ' . $course['code'] . ' ' . $course['classes']);
					?>
					<div class="lock-course<?= $isLocked ? ' is-locked' : ''; ?>" data-hay="<?= esc($hay); ?>" data-course-row="<?= (int) $teacher['id']; ?>-<?= (int) $course['id']; ?>">
						<div>
							<strong><?= esc($course['title']); ?></strong>
							<?php if ($course['code'] !== ''): ?>
								<span><?= esc($course['code']); ?></span>
							<?php endif; ?>
							<span class="lock-pill <?= $isLocked ? 'on' : 'off'; ?>"><?= $isLocked ? 'Locked' : 'Open'; ?></span>
							<small><?= esc($course['classes']); ?></small>
						</div>
						<button type="button"
								class="btn btn-sm <?= $isLocked ? 'btn-primary' : 'btn-warning'; ?> btn-lock-course"
								data-staff="<?= (int) $teacher['id']; ?>"
								data-course="<?= (int) $course['id']; ?>"
								data-locked="<?= $isLocked ? '0' : '1'; ?>">
							<?= $isLocked ? 'Unlock' : 'Lock'; ?>
						</button>
					</div>
				<?php endforeach; ?>
			</section>
		<?php endforeach; ?>
	<?php endif; ?>
</div>
<script>
	$("#lockMarksSearch").on("input", function () {
		var q = String($(this).val() || "").toLowerCase().trim();
		$(".lock-teacher").each(function () {
			var shown = 0;
			$(this).find(".lock-course").each(function () {
				var hit = q === "" || String($(this).data("hay") || "").indexOf(q) !== -1;
				$(this).toggle(hit);
				if (hit) shown++;
			});
			$(this).toggle(shown > 0);
		});
	});

	function lockFlag($btn) {
		return String($btn.attr("data-locked")) === "1" ? 1 : 0;
	}

	function paintCourseLock(staffId, courseId, locked) {
		var $row = $('[data-course-row="' + staffId + '-' + courseId + '"]');
		if (!$row.length) return;
		$row.toggleClass("is-locked", !!locked);
		$row.find(".lock-pill").toggleClass("on", !!locked).toggleClass("off", !locked).text(locked ? "Locked" : "Open");
		var $btn = $row.find(".btn-lock-course");
		$btn.toggleClass("btn-warning", !locked).toggleClass("btn-primary", !!locked)
			.attr("data-locked", locked ? "0" : "1")
			.text(locked ? "Unlock" : "Lock");
		$btn.removeData("locked");
	}

	function postLock(staffId, courseId, locked, $btn, scope) {
		if ($btn.data("busy")) return;
		$btn.data("busy", 1).prop("disabled", true);
		var payload = {
			staff_id: staffId,
			course_id: courseId,
			locked: String(locked)
		};
		if (scope) payload.scope = scope;
		$.post("<?= base_url('toggle_marks_edit_lock'); ?>", payload, function (res) {
			if (res && res.success) {
				if (window.toastada) toastada.success(res.success);
				var lockedOn = parseInt(res.locked, 10) === 1;
				var rows = res.rows || [];
				if (rows.length) {
					rows.forEach(function (row) {
						paintCourseLock(row.staff_id, row.course_id, lockedOn);
					});
				} else {
					var ids = res.course_ids || [];
					if (!ids.length && courseId) ids = [courseId];
					ids.forEach(function (cid) {
						paintCourseLock(staffId, cid, lockedOn);
					});
				}
			} else {
				var err = (res && res.error) ? res.error : "Could not update the lock";
				if (window.toastada) toastada.error(err);
				else alert(err);
			}
		}, "json").fail(function () {
			if (window.toastada) toastada.error("Could not update the lock");
			else alert("Could not update the lock");
		}).always(function () {
			$btn.data("busy", 0).prop("disabled", false);
		});
	}

	$(document).on("click", ".btn-lock-course", function (e) {
		e.preventDefault();
		var $btn = $(this);
		postLock($btn.attr("data-staff"), $btn.attr("data-course"), lockFlag($btn), $btn);
	});
	$(document).on("click", ".btn-lock-all", function (e) {
		e.preventDefault();
		var $btn = $(this);
		postLock($btn.attr("data-staff"), 0, lockFlag($btn), $btn);
	});
	$(document).on("click", ".btn-lock-school", function (e) {
		e.preventDefault();
		var $btn = $(this);
		var locked = lockFlag($btn);
		var word = locked ? "lock marks editing for every teacher" : "unlock marks editing for every teacher";
		if (!window.confirm("This will " + word + " for the active term. Continue?")) return;
		postLock(0, 0, locked, $btn, "school");
	});
</script>
