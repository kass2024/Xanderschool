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
		<?php if (is_wisdom_school()): ?>
		<div class="form-group col-sm-4 col-md-2 col-lg-2">
			<label><?= lang("app.type"); ?>:</label>
			<select class="form-control select2" name="report_type" id="select_report_type">
				<option value="regular"><?= lang("app.resultRecord"); ?></option>
				<option value="holiday_coaching"><?= lang("app.holidayCoaching"); ?></option>
			</select>
		</div>
		<?php endif; ?>
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

<?php if (!empty($error)): ?>
<div class="row">
	<div class="col-sm-12">
		<div class="alert alert-danger"><?= esc($error) ?></div>
	</div>
</div>
<?php endif; ?>
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

		function reportTypeBase() {
			var reportType = $("#select_report_type").val() || "regular";
			return reportType === "holiday_coaching"
				? "<?=base_url('holiday_coaching_report');?>"
				: "<?=base_url('student_report_slip');?>";
		}
		function syncHolidayReportUi() {
			var holiday = ($("#select_report_type").val() || "regular") === "holiday_coaching";
			$("#form").attr("action", reportTypeBase());
			$("#select_term option[value='4']").prop("disabled", holiday);
			if (holiday && $("#select_term").val() === "4") {
				$("#select_term").val("1").trigger("change");
			}
			$("#sms_publish").toggle(!holiday);
		}
		$("#select_report_type").on("change", syncHolidayReportUi);
		syncHolidayReportUi();

		$("#btn_generate").on("click", function (e) {
			e.preventDefault();
			var classe = $("#select_class").val();
			var year = $("#select_year").val();
			var term = $("#select_term").val();
			var std=$("#select_student").val();
			var reportType = $("#select_report_type").val() || "regular";
			var base = reportType === "holiday_coaching"
				? "<?=base_url('holiday_coaching_report/');?>"
				: "<?=base_url('student_report_slip/');?>";
			if(std==null){
				window.location.href = base+classe+"/"+year+"/"+term+"/";
			}else {
				window.location.href = base+classe+"/"+year+"/"+term+"/"+"?student="+std;
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
