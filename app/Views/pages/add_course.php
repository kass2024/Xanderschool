<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.20/css/dataTables.bootstrap4.min.css">
<form style="width: 250px; margin-right: 12px;" class="pull-right" id="chooseClass">
<select class="select2" id="select_class" style="display: none;">
	<option disabled selected> <?= lang("app.chooseType"); ?> </option>
	<option value="1"><?= lang("app.wda"); ?></option>
    <option value="2"><?= lang("app.reb"); ?></option>
<!--    <option value="3">Cambridge-Cambridge International School</option>-->
</select>
</form>
<button class="btn btn-success btn-lg" id="createcoursebtn" style="margin-left: 10px"  onclick="switch_classes();"><?= lang("app.createNewCourse"); ?></button>

<div class="boxed" id="createCourseDiv" style="display: none;">

<form action="<?= base_url('manipulate_course'); ?>" class="validate autoSubmit">
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
		<label id="credits"><?= lang("app.credits"); ?></label>
		<input class="form-control" type="text" name="credit"  minlength="1">
	</div>
</td>
<td><div class="form-group">
		<label><?= lang("app.maxPoints"); ?></label>
		<input class="form-control" type="number" name="marks" required minlength="1">
	</div>
</td>


<td><button type="submit" class="btn btn-success btn-lg" data-target="<?=base_url('add_course');?>"><?= lang("app.create"); ?></button>
</td>
</tr>
		</tbody>
	</table>

</div>
</form>
<div class="boxd" style="margin-top: 10px;">
	<table class="table table-hover table-striped table-bordered dataTable dtr-inline" id="example">
		<thead>
		<tr>
			<th><?= lang("app.title"); ?></th>
				<th><?= lang("app.code"); ?></th>
				<th><?= lang("app.category"); ?></th>
				<th><?= lang("app.credits"); ?></th>
				<th><?= lang("app.marks"); ?></th>
				<th><?= lang("app.use"); ?></th>
		</tr>
		</thead>
		<tbody>
		<?php
		$i=1;
		foreach ($courses as $course) {
			echo "<tr>

<td >" . $course['title'] . "</td>
<td>" . $course['code'] . "</td>
<td>" . $course['category'] . "</td>
<td>" . $course['credit'] . "</td>
<td>" . $course['marks'] . "</td>
<td>
<label class='typcn typcn-document-add text-primary link' data-id=" . $course['id'] . " data-toggle='modal' data-target='#assignModal'
														   data-target='" . $course['id'] . "'  >" . lang("app.assign") . "</label>&nbsp;&nbsp;
<label class='typcn typcn-edit text-success link'  data-toggle='modal' data-target='#editCourseModal' data-id='" . $course['id'] . "'>" . lang("app.edit") . "</label>&nbsp;&nbsp;
<label class='typcn typcn-delete text-danger link' data-title='" . $course['title'] . "' data-toggle='delete'
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

<script type="text/javascript">
	$(document).ready(function () {
		$('#classTable').DataTable({
			"searching": false
		});
		$("#select_class").on("change", function () {
			var value = $(this).val();
			// alert(value);
			if (value == 2) {
				$('#credits').text("Hours");
			}
			if (value == 1) {
				$('#creditDiv').show();
			}
			if (value == 3) {
				$('#creditDiv').show();
			}
			$('#createCourseDiv').show();
		});
		$('#chooseClass').hide();
		$('#createCourseDiv').hide();
		$("#courseid").on("click", function (e) {
			var course = $(this).val();
			alert(course);
		})
	});

	function switch_classes() {
		$('#chooseClass').show();
	}

</script>
