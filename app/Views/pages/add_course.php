<?php
/**
 * Smart Create Course — extracted modules per class (TVET / REB)
 * @var array $classes
 * @var array $courses
 * @var array $categories
 * @var array $staffs
 * @var array $smart_by_class
 */
$smartByClass = $smart_by_class ?? [];
$classesByType = ['1' => [], '2' => []];
foreach ($classes as $c) {
	$ft = (string) ((int) ($c['faculty_type'] ?? $c['type'] ?? 1));
	if ($ft !== '2') {
		$ft = '1';
	}
	$classesByType[$ft][] = $c;
}
?>
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.20/css/dataTables.bootstrap4.min.css">

<button class="btn btn-success btn-lg" id="createcoursebtn" style="margin-left: 10px" type="button"><?= lang("app.createNewCourse"); ?></button>
<button class="btn btn-outline-primary btn-lg" id="btnSmartCourses" style="margin-left: 8px" type="button">
	<i class="fa fa-magic"></i> Smart create from analysis
</button>

<div class="modal fade" id="chooseCourseTypeModal" tabindex="-1" role="dialog" aria-labelledby="chooseCourseTypeLabel" aria-hidden="true">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="chooseCourseTypeLabel"><?= lang("app.chooseType"); ?></h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<p class="mb-3 text-muted">Choose programme type, then create courses manually or from extracted curriculum.</p>
				<button type="button" class="btn btn-outline-primary btn-block btn-lg course-type-pick mb-2" data-type="1" data-mode="manual"><?= lang("app.wda"); ?> (TVET)</button>
				<button type="button" class="btn btn-outline-primary btn-block btn-lg course-type-pick" data-type="2" data-mode="manual"><?= lang("app.reb"); ?> (REB)</button>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-dismiss="modal"><?= lang("app.close"); ?></button>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="smartTypeModal" tabindex="-1" role="dialog">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Smart create — choose type</h5>
				<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body">
				<p class="text-muted mb-3">Show extracted courses for classes of this type.</p>
				<button type="button" class="btn btn-outline-primary btn-block btn-lg smart-type-pick mb-2" data-type="1"><?= lang("app.wda"); ?> (TVET)</button>
				<button type="button" class="btn btn-outline-primary btn-block btn-lg smart-type-pick" data-type="2"><?= lang("app.reb"); ?> (REB)</button>
			</div>
		</div>
	</div>
</div>

<style>
	.smart-wrap { margin: 12px 10px 0; display:none; }
	.smart-wrap.is-on { display:block; }
	.smart-banner {
		background:linear-gradient(135deg,#ecfeff,#f0fdfa); border:1px solid #99f6e4; border-radius:12px;
		padding:.85rem 1rem; margin-bottom:1rem; color:#134e4a;
	}
	.smart-class {
		border:1px solid #e2e8f0; border-radius:12px; margin-bottom:.75rem; background:#fff; overflow:hidden;
	}
	.smart-class-head {
		display:flex; align-items:center; justify-content:space-between; gap:.75rem; flex-wrap:wrap;
		padding:.75rem 1rem; background:#f8fafc; cursor:pointer; user-select:none;
	}
	.smart-class-head strong { color:#0f172a; }
	.smart-class-meta { color:#64748b; font-size:.82rem; }
	.smart-class-body { display:none; padding:.5rem 1rem 1rem; border-top:1px solid #e2e8f0; }
	.smart-class.is-open .smart-class-body { display:block; }
	.smart-table { width:100%; font-size:.88rem; }
	.smart-table th { font-size:.75rem; text-transform:uppercase; color:#64748b; border-bottom:1px solid #e2e8f0; padding:.4rem; }
	.smart-table td { padding:.45rem .4rem; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
	.smart-badge { display:inline-block; font-size:.7rem; font-weight:700; padding:.12rem .4rem; border-radius:999px; background:#e0f2fe; color:#0369a1; }
	.smart-badge.exists { background:#fef3c7; color:#b45309; }
	.smart-badge.ok { background:#dcfce7; color:#15803d; }
	.smart-actions { display:flex; gap:.5rem; flex-wrap:wrap; margin-top:.65rem; }
	.hours-input, .marks-input { width:72px; display:inline-block; }
</style>

<div class="smart-wrap" id="smartWrap">
	<div class="smart-banner">
		<strong id="smartTypeLabel">TVET</strong> —
		Extracted courses from Pedagogical Documents analysis, arranged per class.
		Hours/week come from the chronogram; <b>Marks = Hours/week × 10</b>.
		Category is taken from the curriculum (Specific / General / Complementary) and created if missing.
		<a href="<?= base_url('ped_analyse'); ?>">Analyse first</a> if a class is empty.
	</div>
	<div id="smartClassList"></div>
	<p class="text-muted" id="smartEmpty" style="display:none;">No extracted courses for this type yet. Run Analyse Curriculum &amp; Chronogram for those classes first.</p>
</div>

<div class="boxed" id="createCourseDiv" style="display: none;">
<form action="<?= base_url('manipulate_course'); ?>" class="validate autoSubmit" id="manualCourseForm">
	<table class="table table-striped table-bordered" style="margin: 0">
		<tbody>
<tr>
<td><div class="form-group">
		<label><?= lang("app.title"); ?></label>
		<input class="form-control" type="text" name="title" required minlength="3">
	</div>
</td>
<td><div class="form-group">
		<label><?= lang("app.code"); ?></label>
		<input class="form-control" type="text" name="code" required minlength="3">
	</div>
</td>
<td><div class="form-group">
		<label><?= lang("app.category"); ?></label>
		<a href="javascript:void" class="pull-right" data-toggle="modal" data-target="#addCourseCategory"><i class="fa fa-plus"></i> <?= lang("app.createNewCategory"); ?></a>
		<a href="javascript:void" class="pull-right" data-toggle="refresh" data-href="<?=base_url('get_course_category');?>" data-target="category" style="margin: 0 10px"><i class="fa fa-sync faa-spin"></i> </a>
		<select class="form-control select2" name="category" id="category" required>
			<option disabled selected><?= lang("app.chooseCategory"); ?></option>
			<?php
				foreach ($categories as $category):
					echo"<option value='{$category['id']}'>{$category['title']}</option>";
				endforeach;
	?>
		</select>
	</div>
</td>
<td id="creditDiv">
	<div class="form-group" >
		<label id="credits">Hours / week</label>
		<input class="form-control" type="number" step="0.1" min="0" name="credit" id="manualHours" minlength="1">
	</div>
</td>
<td><div class="form-group">
		<label><?= lang("app.maxPoints"); ?> <small class="text-muted">(auto = hours×10)</small></label>
		<input class="form-control" type="number" name="marks" id="manualMarks" required minlength="1">
	</div>
</td>


<td><button type="submit" class="btn btn-success btn-lg" data-target="<?=base_url('add_course');?>"><?= lang("app.create"); ?></button>
</td>
</tr>
		</tbody>
	</table>
</form>
</div>

<div class="boxd" style="margin-top: 10px;">
	<table class="table table-hover table-striped table-bordered dataTable dtr-inline" id="example">
		<thead>
		<tr>
			<th><?= lang("app.title"); ?></th>
				<th><?= lang("app.code"); ?></th>
				<th><?= lang("app.category"); ?></th>
				<th>Hours / week</th>
				<th><?= lang("app.marks"); ?></th>
				<th><?= lang("app.use"); ?></th>
		</tr>
		</thead>
		<tbody>
		<?php
		$i=1;
		foreach ($courses as $course) {
			$titleEsc = htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8');
			echo "<tr>

<td >" . $course['title'] . "</td>
<td>" . $course['code'] . "</td>
<td>" . $course['category'] . "</td>
<td>" . $course['credit'] . "</td>
<td>" . $course['marks'] . "</td>
<td>
<label class='typcn typcn-document-add text-primary link' data-id='" . $course['id'] . "' data-title='" . $titleEsc . "' data-toggle='modal' data-target='#assignModal'>" . lang("app.assign") . "</label>&nbsp;&nbsp;
<label class='typcn typcn-edit text-success link'  data-toggle='modal' data-target='#editCourseModal' data-id='" . $course['id'] . "'>" . lang("app.edit") . "</label>&nbsp;&nbsp;
<label class='typcn typcn-delete text-danger link' data-title='" . $titleEsc . "' data-toggle='delete'
																		   data-target='" . $course['id'] . "'  data-href='delete_course/" . $course['id'] . "'>" . lang("app.del") . "</label>
</td>
</tr>";
			$i++;
		}
		?>
		</tbody>
	</table>

</div>


<script type="text/javascript" src="https://cdn.datatables.net/1.10.20/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.10.20/js/dataTables.bootstrap4.min.js"></script>

<style>
	.course-type-pick { text-align: left; white-space: normal; }
</style>
<script type="text/javascript">
	$(document).ready(function () {
		var classesByType = <?= json_encode($classesByType, JSON_UNESCAPED_UNICODE); ?>;
		var smartByClass = <?= json_encode($smartByClass, JSON_UNESCAPED_UNICODE); ?>;
		var currentType = null;
		var staffOptions = <?= json_encode(array_map(static function ($s) {
			return ['id' => (int)$s['id'], 'name' => trim(($s['fname'] ?? '') . ' ' . ($s['lname'] ?? ''))];
		}, $staffs ?? []), JSON_UNESCAPED_UNICODE); ?>;

		$('#example').DataTable();
		$('#createCourseDiv').hide();

		var $typeModal = $("#chooseCourseTypeModal");
		var $smartTypeModal = $("#smartTypeModal");

		function applyCourseType(value) {
			if (!value) {
				if (window.toastada) toastada.error("<?= lang("app.chooseType"); ?>");
				return false;
			}
			currentType = String(value);
			$('#credits').text("Hours / week");
			$('#creditDiv').show();
			$('#createCourseDiv').show();
			$('#smartWrap').removeClass('is-on');
			$typeModal.modal("hide");
			return true;
		}

		$("#createcoursebtn").on("click", function () {
			$typeModal.modal("show");
		});
		$typeModal.on("click", ".course-type-pick", function () {
			applyCourseType($(this).data("type"));
		});

		$("#btnSmartCourses").on("click", function () {
			$smartTypeModal.modal("show");
		});
		$smartTypeModal.on("click", ".smart-type-pick", function () {
			currentType = String($(this).data("type"));
			$smartTypeModal.modal("hide");
			$('#createCourseDiv').hide();
			renderSmart(currentType);
		});

		function classLabel(c) {
			return [c.level_name || '', c.dept_code || c.code || '', c.title || ''].join(' ').replace(/\s+/g, ' ').trim();
		}

		function renderSmart(type) {
			var list = classesByType[type] || [];
			var $box = $('#smartClassList').empty();
			var shown = 0;
			$('#smartTypeLabel').text(type === '2' ? 'REB (General Education)' : 'TVET (Rwanda TVET Board)');
			list.forEach(function (c) {
				var pack = smartByClass[String(c.id)] || smartByClass[c.id];
				if (!pack || !pack.modules || !pack.modules.length) return;
				shown++;
				var pending = pack.modules.filter(function (m) { return !m.already_exists; }).length;
				var $card = $('<div class="smart-class" data-class-id="' + c.id + '"></div>');
				$card.append(
					'<div class="smart-class-head">'
					+ '<div><strong>' + $('<div>').text(classLabel(c)).html() + '</strong>'
					+ '<div class="smart-class-meta">' + pack.modules.length + ' extracted · ' + pending + ' new</div></div>'
					+ '<div><span class="smart-badge">' + (type === '2' ? 'REB' : 'TVET') + '</span> '
					+ '<i class="fa fa-chevron-down"></i></div></div>'
				);
				var $body = $('<div class="smart-class-body"></div>');
				var $tbl = $('<table class="smart-table"><thead><tr>'
					+ '<th><input type="checkbox" class="smart-check-all"></th>'
					+ '<th>Title</th><th>Code</th><th>Category</th><th>Hours/week</th><th>Marks</th><th>Status</th>'
					+ '</tr></thead><tbody></tbody></table>');
				pack.modules.forEach(function (m, idx) {
					var checked = m.already_exists ? '' : 'checked';
					var disabled = m.already_exists ? 'disabled' : '';
					var status = m.already_exists
						? '<span class="smart-badge exists">Already in courses</span>'
						: '<span class="smart-badge ok">Ready to create</span>';
					var hours = m.hours_per_week != null ? m.hours_per_week : 0;
					var marks = Math.round((parseFloat(hours) || 0) * 10);
					$tbl.find('tbody').append(
						'<tr data-idx="' + idx + '">'
						+ '<td><input type="checkbox" class="smart-row-check" ' + checked + ' ' + disabled + '></td>'
						+ '<td><input type="text" class="form-control form-control-sm smart-title" value="' + $('<div>').text(m.title || '').html() + '"></td>'
						+ '<td><input type="text" class="form-control form-control-sm smart-code" value="' + $('<div>').text(m.code || '').html() + '"></td>'
						+ '<td><input type="text" class="form-control form-control-sm smart-cat" value="' + $('<div>').text(m.category_title || 'General').html() + '"></td>'
						+ '<td><input type="number" step="0.1" min="0" class="form-control form-control-sm hours-input smart-hours" value="' + hours + '"></td>'
						+ '<td><input type="number" class="form-control form-control-sm marks-input smart-marks" value="' + marks + '" readonly></td>'
						+ '<td>' + status + '</td></tr>'
					);
				});
				$body.append($tbl);
				var teacherSelect = '<select class="form-control form-control-sm smart-teacher" style="max-width:220px;display:inline-block;"><option value="0">— Teacher (optional) —</option>';
				staffOptions.forEach(function (s) {
					teacherSelect += '<option value="' + s.id + '">' + $('<div>').text(s.name).html() + '</option>';
				});
				teacherSelect += '</select>';
				$body.append(
					'<div class="smart-actions">'
					+ '<label class="mb-0 mr-2"><input type="checkbox" class="smart-assign" checked> Assign to this class (needs teacher)</label> '
					+ teacherSelect + ' '
					+ '<button type="button" class="btn btn-success btn-sm btn-smart-create"><i class="fa fa-plus"></i> Create selected</button>'
					+ '</div>'
				);
				$card.append($body);
				$box.append($card);
			});
			$('#smartEmpty').toggle(shown === 0);
			$('#smartWrap').addClass('is-on');
			if (shown) {
				$('#smartClassList .smart-class').first().addClass('is-open');
			}
		}

		$(document).on('click', '.smart-class-head', function () {
			$(this).closest('.smart-class').toggleClass('is-open');
		});
		$(document).on('change', '.smart-check-all', function () {
			var on = $(this).is(':checked');
			$(this).closest('table').find('.smart-row-check:not(:disabled)').prop('checked', on);
		});
		$(document).on('input change', '.smart-hours', function () {
			var h = parseFloat($(this).val()) || 0;
			$(this).closest('tr').find('.smart-marks').val(Math.round(h * 10));
		});
		$('#manualHours').on('input change', function () {
			var h = parseFloat($(this).val()) || 0;
			$('#manualMarks').val(Math.round(h * 10));
		});

		$(document).on('click', '.btn-smart-create', function () {
			var $card = $(this).closest('.smart-class');
			var classId = parseInt($card.data('class-id'), 10) || 0;
			var courses = [];
			$card.find('tbody tr').each(function () {
				var $tr = $(this);
				if (!$tr.find('.smart-row-check').is(':checked')) return;
				var hours = parseFloat($tr.find('.smart-hours').val()) || 0;
				courses.push({
					title: $tr.find('.smart-title').val(),
					code: $tr.find('.smart-code').val(),
					category_title: $tr.find('.smart-cat').val() || 'General',
					hours_per_week: hours
				});
			});
			if (!courses.length) {
				if (window.toastada) toastada.error('Select at least one course');
				else alert('Select at least one course');
				return;
			}
			var assign = $card.find('.smart-assign').is(':checked');
			var teacherId = parseInt($card.find('.smart-teacher').val() || '0', 10) || 0;
			if (assign && !teacherId) {
				if (window.toastada) toastada.error('Select a teacher to assign courses to this class (or uncheck Assign)');
				else alert('Select a teacher to assign courses to this class (or uncheck Assign)');
				return;
			}
			var $btn = $(this).prop('disabled', true).text('Creating…');
			$.ajax({
				url: '<?= base_url('smart_create_courses'); ?>',
				type: 'POST',
				dataType: 'json',
				data: {
					class_id: classId,
					assign: assign ? 1 : 0,
					teacher_id: teacherId,
					courses: JSON.stringify(courses)
				}
			}).done(function (res) {
				if (res && res.error) {
					if (window.toastada) toastada.error(res.error);
					else alert(res.error);
					$btn.prop('disabled', false).html('<i class="fa fa-plus"></i> Create selected');
					return;
				}
				if (window.toastada) toastada.success(res.success || 'Done');
				else alert(res.success || 'Done');
				window.location.reload();
			}).fail(function () {
				if (window.toastada) toastada.error('Create failed');
				$btn.prop('disabled', false).html('<i class="fa fa-plus"></i> Create selected');
			});
		});

		$("#assignModal, #editCourseModal, #addCourseCategory").on("shown.bs.modal", function () {
			var $modal = $(this);
			$modal.find("select.select2").each(function () {
				var $el = $(this);
				if ($el.hasClass("select2-hidden-accessible")) {
					$el.select2("destroy");
				}
				$el.select2({ width: "100%", dropdownParent: $modal });
			});
		});

		window.refreshCourseAssignments = function (courseId) {
			courseId = courseId || $("#assignModal [name='fId']").val();
			var $body = $("#assignSmartBody");
			if (!courseId) {
				$body.html('<tr class="text-muted"><td colspan="4" class="text-center">No course selected</td></tr>');
				return;
			}
			$body.html('<tr class="text-muted"><td colspan="4" class="text-center">Loading…</td></tr>');
			$.getJSON("<?= base_url('get_course_assignments'); ?>/" + courseId, function (data) {
				var rows = (data && data.assignments) ? data.assignments : [];
				if (!rows.length) {
					$body.html('<tr class="text-muted"><td colspan="4" class="text-center">No classes assigned yet</td></tr>');
					$("#assignSmartMeta").text("0 assignments");
					return;
				}
				$("#assignSmartMeta").text(rows.length + " assignment" + (rows.length === 1 ? "" : "s"));
				var html = "";
				$.each(rows, function (_, row) {
					var term = row.term || "—";
					html += "<tr>"
						+ "<td>" + $("<div>").text(row.class_name || "").html() + "</td>"
						+ "<td>" + $("<div>").text(row.teacher_name || "").html() + "</td>"
						+ "<td>" + $("<div>").text(String(term)).html() + "</td>"
						+ "<td><button type='button' class='btn btn-sm btn-outline-danger btn-unassign' data-id='" + row.id + "' title='Remove'><i class='fa fa-trash'></i></button></td>"
						+ "</tr>";
				});
				$body.html(html);
			}).fail(function () {
				$body.html('<tr class="text-danger"><td colspan="4" class="text-center">Could not load assignments</td></tr>');
			});
		};

		$(document).on("click", "#assignSmartBody .btn-unassign", function () {
			var id = $(this).data("id");
			if (!id || !confirm("Remove this course assignment?")) return;
			var $btn = $(this).prop("disabled", true);
			$.getJSON("<?= base_url('delete_course_assign'); ?>/" + id, function (data) {
				if (data && data.error) {
					if (window.toastada) toastada.error(data.error);
					$btn.prop("disabled", false);
					return;
				}
				if (window.toastada) toastada.success((data && data.success) || "Removed");
				window.refreshCourseAssignments();
			}).fail(function () {
				if (window.toastada) toastada.error("System error");
				$btn.prop("disabled", false);
			});
		});
	});
</script>
