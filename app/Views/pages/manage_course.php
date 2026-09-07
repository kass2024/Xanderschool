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
	$(function () {
		$("#choose_class").on("change",function () {
			get_courses();
		})
		$("#select_year").on("change",function () {
			get_courses();
		})

		$("#choose_class").on("change",function (e) {
        		var id =$("#choose_class").val();
          		$("#addCourseModal [name='fId']").val(id).change();
  			});

		// Active year is pre-selected; load courses once a class is chosen
	})

function get_courses()
	{
		var value = $("#choose_class").val();
		var year = $("#select_year").val();
		if (value!=null && year!=null) {
		$.get("<?=base_url();?>get_course/"+value+"/"+year,function (data) {
				$("#ctlTableBody").html(data);
				$("#addNewCourse").show();
			})
	}
	}
</script>
