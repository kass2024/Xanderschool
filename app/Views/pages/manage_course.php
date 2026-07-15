<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.20/css/dataTables.bootstrap4.min.css">
<div class="cols-md-4">
<form style="width: 125px; margin-left: 12px;" class="pull-left" id="chooseClass">
<select class="select2" id="choose_class" name="classes">
	<option disabled selected><?= lang("app.selectClass"); ?></option>
	<?php
		foreach ($classes as $classe):
		echo "<option value='{$classe['id']}'>{$classe['level_name']} {$classe['dept_code']} {$classe['title']}</option>";
		endforeach;
		?>
</select>
</form>
</div>
<div class="cols-md-4">
<form style="width: 125px; margin-left: 12px;" class="pull-left" id="chooseClass">
<select class="select2" id="select_year" name="year">
	<option disabled selected><?= lang("app.academicYear"); ?></option>
	<?php
	foreach ($years as $year):
		echo "<option value='{$year['id']}'>{$year['title']}</option>";
	endforeach;
	?>
</select>
</form>
</div>

<div class="cols-md-4 pull-right">
<button class="btn btn-success btn-lg" style="margin-right: 10px;display: none;" id="addNewCourse" data-toggle='modal' data-target='#addCourseModal' "><i class="fa fa-plus"></i> <?= lang("app.addNewCourse"); ?></button>
</div>
<div class="boxed" style="margin-top: 50px;">
	<table class="table table-striped table-bordered" style="margin: 0" name="ctlTable">
		<tbody>


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
        		// alert(id);
          		$("#addCourseModal [name='fId']").val(id).change();
  			});


	})

function get_courses()
	{
		var value = $("#choose_class").val();
		var year = $("#select_year").val();
		// alert(year);
		if (value!=null && year!=null) {
		$.get("<?=base_url();?>get_course/"+value+"/"+year,function (data) {
				$("[name='ctlTable']").html(data);
				$("#addNewCourse").show();
			})
	}
	}
</script>
