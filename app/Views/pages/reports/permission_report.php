<div class="app-inner-layout app-inner-layout-page">
	<div class="app-inner-layout__wrapper" style="display: block;padding-left: 20px">
		<style>
			.vl {
				border-left: 3px solid #3ac47d;
			}
		</style>
		<div class="pull-left" style="width: 100%">
			<div class="col-md-6 col-sm-12 col-lg-4 pull-left">
				<label><?= lang("app.searchStudent");?> </label>
				<div id="search_student_dv">
					<select class="form-control select3" name="search_student" id="search_student">
					</select>
				</div>
				<div id="search_class_dv" style="display: none !important;">
					<select class="form-control select2" id="search_class">
						<option selected disabled><?= lang("app.selectClass");?> </option>
						<?php
						foreach ($classes as $class) {
							echo "<option value='{$class['id']}'>{$class['level_name']} {$class['title']} {$class['code']} </option>";
						}
						?>
					</select>
				</div>
			</div>
			<div class="col-sm-2 pull-left" >
				<label><?= lang("app.fromDate");?> </label>
				<input type="date" class="form-control" name="dateFrom" id="fromdate" required>
			</div>
			<div class="col-sm-2 pull-left" >
				<label><?= lang("app.toDate");?> </label>
				<input type="date" class="form-control" name="dateTo" id="todate" required>
			</div>
			<div class="col-sm-2 pull-left" style="margin-top: 30px;">
				<button type="submit" class="btn btn-success" id="search"><?= lang("app.go");?> </button>
			</div>
		</div>
		<div style="margin-top: 15px;width: 100%;float:left;">
			<form action="<?= base_url('manipulate_permissions'); ?>" class="autoSubmit validate" method="POST">

				<div class="col-md-12 col-sm-12 pull-left" style="margin-bottom: 15px;display: none ! important" id="myView">
					<div style="background:white;padding: 10px;max-height: 500px;overflow: auto;">
						<table class="table table-hover table-fixed">
							<!--Table head-->
							<thead>
							<tr>
								<th>#</th>
								<th><?= lang("app.destination");?> </th>
								<th><?= lang("app.reason");?> </th>
								<th><?= lang("app.leaveTime");?> </th>
								<th><?= lang("app.returnTime");?> </th>
								<th></th>
							</tr>
							</thead>
							<!--Table head-->
							<!--Table body-->
							<tbody id="mydata">

							</tbody>
							<!--Table body-->
						</table>
						<!--Table-->
					</div>
				</div>
			</form>
		</div>
	</div>
</div>
<script>

	$(function () {
		$(document).ready(function () {
			$(".select3").select2({
				ajax: {
					url: "<?=base_url('search_student');?>",
					type: "post",
					dataType: 'json',
					delay: 250,
					data: function (params) {
						return {
							searchTerm: params.term // search term
						};
					},
					processResults: function (response) {
						return {
							results: response
						};
					},
					cache: true
				},
				placeholder: "<?= lang("app.searchBy");?>",
				minimumInputLength: 3
			});
		});
		$("#search_type").prop("checked",false);//reset checkbox
		$("#search_type").on("change", function () {
			//check if table has unsaved data then notify before clear

			$("#search_student_dv").toggle();
			$("#search_class_dv").toggle();

		});
		var type=2;
		$("#search_type").on("click", function () {
			if ($("#byStudent").prop("checked") == true) {
				type=1;
			} else {
				type=2;
			}
		});
		$("#search").on("click",function () {
			var Student=$("#search_student").val();
			var from=$("#fromdate").val();
			var to=$("#todate").val();
			// alert(Student);
				$.get("<?=base_url();?>get_permission_report/"  + Student + "/" + from + "/" + to, function (data) {
					$("#mydata").html(data);
				});

		});
	});
</script>
