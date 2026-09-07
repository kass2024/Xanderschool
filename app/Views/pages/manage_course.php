<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.20/css/dataTables.bootstrap4.min.css">
<div class="cols-md-4">
<form style="width: 125px; margin-left: 12px;" class="pull-left" id="chooseClass">
<select class="select2" id="choose_class" name="classes">
	<option disabled selected><?= lang("app.selectClass"); ?></option>
	<?php
	foreach ($classes as $classe):
		$deptCode = $classe['dept_code'] ?? ($classe['code'] ?? '');
		echo "<option value='{$classe['id']}'>{$classe['level_name']} {$deptCode} {$classe['title']}</option>";
	endforeach;
	?>
</select>
</form>
</div>
<div class="cols-md-4">
<form style="width: 125px; margin-left: 12px;" class="pull-left" id="chooseYear">
<select class="select2" id="select_year" name="year">
	<?php
	$activeYear = (int) ($active_academic_year ?? 0);
	$yearIds = array_map(static function ($y) {
		return (int) ($y['id'] ?? 0);
	}, $years ?? []);
	$hasActive = $activeYear > 0 && in_array($activeYear, $yearIds, true);
	if (!$hasActive):
	?>
	<option disabled selected><?= lang("app.academicYear"); ?></option>
	<?php
	endif;
	foreach ($years as $year):
		$selected = ((int) $year['id'] === $activeYear) ? ' selected' : '';
		echo "<option value='{$year['id']}'{$selected}>{$year['title']}</option>";
	endforeach;
	?>
</select>
</form>
</div>

<div class="cols-md-4 pull-right">
<button class="btn btn-success btn-lg" style="margin-right: 10px;display: none;" id="addNewCourse" data-toggle='modal' data-target='#addCourseModal'><i class="fa fa-plus"></i> <?= lang("app.addNewCourse"); ?></button>
</div>
<div class="boxed" style="margin-top: 50px;">
	<table class="table table-striped table-bordered" style="margin: 0">
		<tbody id="ctlTableBody">


		</tbody>
	</table>

</div>


<script type="text/javascript" src="https://cdn.datatables.net/1.10.20/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.10.20/js/dataTables.bootstrap4.min.js"></script>

<script type="text/javascript">
	$(document).ready(function () {
		$('#classTable').DataTable({
			"searching": false
		});
});

</script>
<script>
	var manageCourseStateKey = "manage_courses:selected_state";

	function persistManageCourseState() {
		if (!window.sessionStorage) return;
		sessionStorage.setItem(manageCourseStateKey, JSON.stringify({
			classId: $("#choose_class").val() || "",
			yearId: $("#select_year").val() || ""
		}));
	}

	function restoreManageCourseState() {
		if (!window.sessionStorage) return false;
		var raw = sessionStorage.getItem(manageCourseStateKey);
		if (!raw) return false;
		try {
			var saved = JSON.parse(raw);
			if (!saved || (!saved.classId && !saved.yearId)) return false;
			if (saved.yearId && $("#select_year option[value='" + saved.yearId + "']").length) {
				$("#select_year").val(saved.yearId).trigger("change.select2");
			}
			if (saved.classId && $("#choose_class option[value='" + saved.classId + "']").length) {
				$("#choose_class").val(saved.classId).trigger("change.select2");
			}
			return !!(saved.classId && saved.yearId);
		} catch (e) {
			return false;
		}
	}

	$(function () {
		$("#choose_class").on("change",function () {
			persistManageCourseState();
			get_courses();
		})
		$("#select_year").on("change",function () {
			persistManageCourseState();
			get_courses();
		})

		$("#choose_class").on("change",function (e) {
        		var id =$("#choose_class").val();
          		$("#addCourseModal [name='fId']").val(id).change();
  			});

		if (!restoreManageCourseState()) {
			persistManageCourseState();
		}
		if ($("#choose_class").val() && $("#select_year").val()) {
			get_courses();
		}
	})

function get_courses()
	{
		var value = $("#choose_class").val();
		var year = $("#select_year").val();
		if (value!=null && year!=null) {
		persistManageCourseState();
		$.get("<?=base_url();?>get_course/"+value+"/"+year,function (data) {
				$("#ctlTableBody").html(data);
				$("#addNewCourse").show();
			})
	}
	}
</script>
