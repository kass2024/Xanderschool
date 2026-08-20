<style>
	.hc-report-bar {
		display: none;
		width: 100%;
		margin: 6px 14px 12px;
		padding: 12px 14px;
		border: 1px solid #dbeafe;
		background: #f8fbff;
		border-radius: 10px;
	}
	.hc-report-bar.is-on { display: block; }
	.hc-mode {
		display: inline-flex;
		border: 1px solid #93c5fd;
		border-radius: 999px;
		overflow: hidden;
		margin-right: 12px;
		vertical-align: middle;
	}
	.hc-mode-btn {
		appearance: none;
		border: 0;
		background: #fff;
		color: #1e3a8a;
		font-weight: 700;
		padding: 7px 14px;
		cursor: pointer;
	}
	.hc-mode-btn.is-on { background: #1d4ed8; color: #fff; }
	.hc-assign {
		display: inline-block;
		margin-top: 8px;
		font-size: .92rem;
	}
	.hc-assign.ok { color: #166534; }
	.hc-assign.bad { color: #b91c1c; }
	.hc-assign.wait { color: #64748b; }
</style>
<form method="get" target="_blank" action="<?=base_url('student_report_slip');?>" id="form" class="validate" >
	<div class="row" style="background-color: white;height: auto;padding: 10px">
		<label style="margin-left: 14px;margin-bottom: 10px;" id="singleStudentLabel"><?= lang("app.singleStudentReport");?></label>
		<input type="checkbox" id="useStudent" style="margin-left: 14px;margin-bottom: 7px;">
		<div class="hc-report-bar" id="hcReportBar">
			<strong>Holiday coaching report</strong>
			<div style="margin-top:8px;">
				<span class="hc-mode" role="group" aria-label="Report audience">
					<button type="button" class="hc-mode-btn is-on" data-mode="class">Whole class</button>
					<button type="button" class="hc-mode-btn" data-mode="student">Single student</button>
				</span>
				<span class="text-muted">Same as other progress reports: generate on screen or export PDF.</span>
			</div>
			<div class="hc-assign wait" id="hcAssignStatus">Select academic year and class.</div>
		</div>
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
		<?php if (is_wisdom_school()): ?>
		<div class="form-group col-sm-4 col-md-2 col-lg-2" id="report_type_group">
			<label><?= lang("app.type"); ?>:</label>
			<select class="form-control select2" name="report_type" id="select_report_type">
				<option value="regular"><?= lang("app.resultRecord"); ?></option>
				<option value="holiday_coaching"><?= lang("app.holidayCoaching"); ?></option>
			</select>
		</div>
		<?php endif; ?>
		<div class="form-group col-sm-4 col-md-2 col-lg-2" id="report_term_group">
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
			<button type="button" class="btn btn-success" id="btn_generate"><?= lang("app.generate");?></button>
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
			if (typeof refreshHolidayAssignment === "function") {
				refreshHolidayAssignment();
			}
		});

		function reportTypeBase() {
			var reportType = $("#select_report_type").val() || "regular";
			return reportType === "holiday_coaching"
				? "<?=base_url('holiday_coaching_report');?>"
				: "<?=base_url('student_report_slip');?>";
		}
		var hcReady = true;
		function isHolidayReport() {
			return ($("#select_report_type").val() || "regular") === "holiday_coaching";
		}
		function setHcStatus(kind, text) {
			$("#hcAssignStatus").removeClass("ok bad wait").addClass(kind).text(text);
		}
		function refreshHolidayAssignment() {
			if (!isHolidayReport()) {
				hcReady = true;
				return;
			}
			var classe = $("#select_class").val();
			var year = $("#select_year").val();
			if (!classe || !year) {
				hcReady = false;
				setHcStatus("wait", "Select academic year and class.");
				return;
			}
			setHcStatus("wait", "Checking holiday coaching courses for this class...");
			hcReady = false;
			$.getJSON("<?=base_url('holiday_coaching_ready');?>/" + classe + "/" + year, function (data) {
				hcReady = !!(data && data.ok);
				if (hcReady) {
					var names = (data.courses || []).join(", ");
					setHcStatus("ok", (data.count || 0) + " holiday course(s) assigned" + (names ? ": " + names : "") + ".");
				} else {
					setHcStatus("bad", (data && data.error) ? data.error : "No holiday coaching courses assigned to this class.");
				}
			}).fail(function () {
				hcReady = false;
				setHcStatus("bad", "Could not check holiday course assignments.");
			});
		}
		function syncHolidayReportUi() {
			var holiday = isHolidayReport();
			var $term = $("#select_term");
			$("#form").attr("action", reportTypeBase());
			$("#report_term_group").toggle(!holiday);
			$term.prop("disabled", holiday);
			$term.prop("required", !holiday);
			if (holiday) {
				$term.removeAttr("required");
				$term.attr("data-parsley-required", "false");
			} else {
				$term.attr("required", "required");
				$term.removeAttr("data-parsley-required");
			}
			$("#select_term option[value='4']").prop("disabled", holiday);
			$("#sms_publish").toggle(!holiday);
			$("#hcReportBar").toggleClass("is-on", holiday);
			$("#singleStudentLabel, #useStudent").toggle(!holiday);
			if (holiday) {
				refreshHolidayAssignment();
			} else {
				hcReady = true;
			}
		}
		function setReportMode(mode) {
			var single = mode === "student";
			$("#useStudent").prop("checked", single);
			$(".hc-mode-btn").removeClass("is-on");
			$(".hc-mode-btn[data-mode='" + (single ? "student" : "class") + "']").addClass("is-on");
			$("#studentDiv").toggle(single);
			if (!single) {
				$("#select_student").val(null).trigger("change");
			}
		}
		$("#select_report_type").on("change select2:select", syncHolidayReportUi);
		$("#select_year").on("change", refreshHolidayAssignment);
		$(".hc-mode-btn").on("click", function () {
			setReportMode($(this).data("mode"));
		});
		syncHolidayReportUi();

		$("#form").on("submit", function (e) {
			if (isHolidayReport()) {
				$("#select_term").prop("required", false).prop("disabled", true).removeAttr("required");
				if (!hcReady) {
					e.preventDefault();
					if (window.toastada) toastada.error($("#hcAssignStatus").text() || "Assign holiday coaching courses to this class first.");
					return false;
				}
			}
		});

		$("#btn_generate").on("click", function (e) {
			e.preventDefault();
			var classe = $("#select_class").val();
			var year = $("#select_year").val();
			var term = $("#select_term").val();
			var std=$("#select_student").val();
			var reportType = $("#select_report_type").val() || "regular";
			if (!classe || !year) {
				if (window.toastada) toastada.error("Select academic year and class");
				return;
			}
			if (reportType !== "holiday_coaching" && !term) {
				if (window.toastada) toastada.error("Select term");
				return;
			}
			if (reportType === "holiday_coaching") {
				term = "1";
			}
			if (reportType === "holiday_coaching" && !hcReady) {
				if (window.toastada) toastada.error($("#hcAssignStatus").text() || "Assign holiday coaching courses to this class first.");
				return;
			}
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
