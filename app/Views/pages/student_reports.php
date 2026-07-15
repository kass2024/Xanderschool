<form method="get" target="_blank" action="<?=base_url('student_report_slip');?>" id="form" class="validate" >
	<div class="row" style="background-color: white;height: auto;padding: 10px">
		<label style="margin-left: 14px;margin-bottom: 10px;"><?= lang("app.singleStudentReport");?></label>
		<input type="checkbox" id="useStudent" style="margin-left: 14px;margin-bottom: 7px;">
		<div class="clearfix" style="width: 100%"></div>
		<div class="form-group col-sm-4 col-md-2 col-lg-2">

			<label><?= lang("app.year");?>:</label>
			<select class="select2" id="select_year" name="year" required>
				<option disabled selected><?= lang("app.academicYear");?></option>
				<?php
				foreach ($years as $year) {

					?>
					<option value="<?= $year['id']; ?>"><?= $year['title']; ?></option>;
					<?php
				}
				?>
			</select>
		</div>
		<div class="form-group col-sm-4 col-md-2 col-lg-2">
			<label><?= lang("app.term");?>:</label>
			<select class="form-control select2" id="select_term" name="term" required>
				<option selected disabled><?= lang("app.selectTerm");?></option>
				<option value="1"><?= lang("app.term1");?></option>
				<option value="2"><?= lang("app.term2");?></option>
				<option value="3"><?= lang("app.term3");?></option>
				<option value="4">Annual</option>
			</select>
		</div>

		<div class="form-group col-sm-4 col-md-2 col-lg-2">
			<label><?= lang("app.sClass");?>:</label>
			<select class="form-control select2" name="class" id="select_class" required>
				<option selected disabled><?= lang("app.selectClass");?></option>
				<?php
				foreach ($classes as $class) {
					?>
					<option
						data-id="<?= $class['facul_id']; ?>" id="faculty<?= $class['id']; ?>" value="<?= $class['id']; ?>"> <?= $class['level_name'] . " " . $class['code'] . " " . $class['title']; ?></option>
					<?php
				} ?>
			</select>
		</div>
		<div class="form-group col-sm-4 col-md-2 col-lg-2" id="studentDiv" style="display: none">
			<label><?= lang("app.student");?>:</label>
			<select class="form-control select2" id="select_student" name="student">

			</select>
		</div>
		<div class="form-group" style="margin-top: 30px">
			<button class="btn btn-success" id="btn_generate"><?= lang("app.generate");?></button>
			<button type="submit" value="true" name="pdf" class="btn btn-primary"><?= lang("app.export");?></button>
			<?php
			if (is_allowed(1, 3)) {
				?>
				<button type="submit" value="true" id="sms_publish" class="btn btn-warning"><?= lang("app.publishviaSMS"); ?> </button>
				<?php
			}
			?>
		</div>
		<br>
		<br>

	</div>

	<div class="card-body">
		<div id="example_wrapper" class="dataTables_wrapper dt-bootstrap4">
			<div class="row" id="report_content">
				<!-- Here we Go -->
			</div>
		</div>
	</div>
</form>

<div class="row">
	<div class="col-sm-12">
		<div class="card">
		  <div class="card-body">
		   	<?php
		   	if($error){
		   		?>
		   		<div class="alert alert-danger"><?= $error ?></div>
		   		<?php
		   	}
		   	?>
		   	<table class="table table-striped table-hover">
		   		<thead>
		   			<tr>
		   				<th>#</th>
		   				<th>Date</th>
		   				<th>Class</th>
		   				<th>Term</th>
		   				<th>Status</th>
		   				<th></th>
		   			</tr>
		   		</thead>
		   		<tbody>
		   			<?php
		   			if(count($reports) > 0){
		   				$counter = 1;
		   				foreach($reports AS $report){
		   					?>
		   					<tr>
		   						<td><?= $counter++; ?></td>
		   						<td><?= (new DateTime($report->created_at))->format('Y-m-d') ?></td>
		   						<td><?= $report->classe->level->title ?> <?= $report->classe->department->code ?> <?= $report->classe->title ?></td>
		   						<td><?= $report->term ?></td>
		   						<td><?= $report->status ?></td>
		   						<td>
		   							<?php
		   							if(!is_null($report->report_file)){
		   								?>
		   								<a target="_blank" href="/student_report?download=true&report_id=<?= $report->id ?>" class="btn btn-success"><i class="fa fa-download"></i> Download</a>
		   								<?php
		   							}
		   							?>
		   							<a href="/student_report?delete=true&report_id=<?= $report->id ?>" class="btn btn-danger"><i class="fa fa-trash"></i> Delete</a>
		   						</td>
		   					</tr>
		   					<?php
		   				}
		   			}
		   			?>
		   		</tbody>
		   	</table>
		  </div>
		</div>
	</div>
</div>
<script>
	$(function () {
		$("#useStudent").on("click",function () {
			if($(this).prop("checked")==true){
				$("#studentDiv").show();
			}else {
				$("#studentDiv").hide();
			}
		});

		$("#select_class").on("change",function () {
			var classe=$(this).val();
			var isclass="/1";
			var type="/7";
			$.get("<?=base_url();?>get_student/" + classe + isclass + type, function (data) {
				$("#select_student").html(data);
			});
		});

		$("#btn_generate").on("click", function (e) {
			e.preventDefault();
			var classe = $("#select_class").val();
			var year = $("#select_year").val();
			var term = $("#select_term").val();
			var std=$("#select_student").val();
			// alert(std);
			if(std==null){
				window.location.href = "<?=base_url('student_report_slip/');?>"+classe+"/"+year+"/"+term+"/";
			}else {
				window.location.href = "<?=base_url('student_report_slip/');?>"+classe+"/"+year+"/"+term+"/"+"?student="+std;
			}

		});
		$("#sms_publish").on("click", function (e) {
			e.preventDefault();
			var form = $("#form");
			if(!form.parsley().validate()){
				return;
			}
			// var btn = $(this).find("[type='submit']");
			var btn_txt = $(this).text();
			$("button").prop("disabled", true);
			var btn = $(this);
			btn.text("<?= lang("app.pleaseWait"); ?>").prop("disabled", true);
			var classe = $("#select_class").val();
			var year = $("#select_year").val();
			var term = $("#select_term").val();
			var std=$("#select_student").val();
			//window.location.href = "<?//=base_url('student_report_slip/');?>//"+classe+"/"+year+"/"+term+"/?publish=sms";
			$.get("<?=base_url('student_report_slip/');?>"+classe+"/"+year+"/"+term+"/?publish=sms", '', function (data) {
				btn.text(btn_txt).prop("disabled", false);
				$("button").prop("disabled", false);
				if (data.hasOwnProperty("error")) {
					toastada.error(data.error);
					// alert(data.error);
				} else if (data.hasOwnProperty("success")) {
					toastada.success(data.success);
				} else {
					toastada.error('<?= lang("app.fatalErr"); ?>');
				}
			}).fail(function () {
				//unknown error
				$("button").prop("disabled", false);
				btn.text(btn_txt).prop("disabled", false);
				toastada.error('<?= lang("app.systemErr"); ?>');
			});
		});
	});
</script>
