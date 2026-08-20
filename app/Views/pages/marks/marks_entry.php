<link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/sweetalert2@7.12.15/dist/sweetalert2.min.css'>
<style>
.marks-entry-page { width: 100%; max-width: 100%; }
.marks-entry-input::placeholder {
	color: #b5b5b5;
	opacity: 1;
}
.marks-entry-input::-webkit-input-placeholder { color: #b5b5b5; }
.marks-entry-input::-moz-placeholder { color: #b5b5b5; opacity: 1; }
.marks-entry-input.is-over-max {
	border-color: #dc3545 !important;
	background: #fff5f5;
}
.marks-live-hint {
	color: #dc3545;
	font-size: 12px;
	font-weight: 600;
	line-height: 1.3;
	margin-top: 4px;
}
.marks-max-live {
	display: block;
	margin-top: 6px;
	color: #1d4ed8;
	font-size: 13px;
	font-weight: 600;
}
.marks-filters {
	display: flex;
	flex-wrap: wrap;
	gap: 12px;
	background: #fff;
	padding: 12px;
	margin: 0 0 12px;
	border-radius: 10px;
	overflow: visible;
}
.marks-filter-item {
	flex: 1 1 100%;
	min-width: 0;
	width: 100%;
	margin: 0;
	position: relative;
}
.marks-filter-item label {
	display: block;
	font-weight: 700;
	font-size: 13px;
	margin-bottom: 6px;
	color: #334155;
}
.marks-entry-page .select2-container,
.marks-filters .select2-container {
	width: 100% !important;
	display: block;
}
.marks-entry-page .select2-container .select2-selection--single {
	min-height: 44px;
	padding: 6px 8px;
	border: 1px solid #cbd5e1;
	border-radius: 8px;
}
.marks-entry-page .select2-selection__rendered {
	white-space: normal !important;
	word-break: break-word;
	line-height: 1.35;
}
.marks-entry-page .select2-dropdown,
.marks-filter-item .select2-dropdown,
body.marks-entry-body .select2-dropdown {
	min-width: 100% !important;
	z-index: 20000 !important;
}
.marks-entry-page .select2-results__option,
body.marks-entry-body .select2-results__option {
	white-space: normal;
	word-break: break-word;
	padding: 10px 12px;
	font-size: 15px;
}
.marks-entry-page .select2-search__field,
body.marks-entry-body .select2-search__field {
	width: 100% !important;
	min-height: 40px;
	font-size: 16px;
}
.marks-entry-grid {
	display: flex;
	flex-wrap: wrap;
	gap: 12px;
	align-items: flex-start;
}
.marks-entry-card {
	background: #fff;
	width: 100%;
	border-radius: 10px;
	padding: 12px;
	box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
}
.marks-entry-card h4 {
	width: 100%;
	float: none;
	border-bottom: 1px solid #e2e8f0;
	padding: 4px 0 10px;
	margin: 0 0 12px;
	font-size: 1.05rem;
}
.marks-field {
	margin-bottom: 12px;
}
.marks-field > span,
.marks-field > label {
	display: block;
	font-weight: 700;
	font-size: 13px;
	color: #334155;
	margin-bottom: 6px;
}
.marks-field strong { font-size: 15px; }
.marks-help {
	display: block;
	background: #f8fafc;
	border: 1px solid #e2e8f0;
	border-radius: 8px;
	padding: 10px 12px;
	font-size: 13px;
	line-height: 1.45;
	color: #475569;
	margin: 0 0 12px;
}
.marks-entry-actions {
	display: flex;
	flex-direction: column;
	gap: 8px;
	margin-top: 8px;
}
.marks-entry-actions .btn {
	width: 100%;
	margin: 0;
	min-height: 46px;
	font-size: 16px;
}
#dv_marks {
	background: #fff;
	width: 100%;
	border-radius: 10px;
	padding: 8px;
	-webkit-overflow-scrolling: touch;
}
#dv_marks table {
	width: 100% !important;
	margin-bottom: 0;
}
#marks_table thead th {
	position: sticky;
	top: 0;
	z-index: 2;
	background: #0ba360;
	color: #fff;
	white-space: nowrap;
}
#marks_table td,
#marks_table th {
	vertical-align: middle;
}
#marks_table .sorting:before,
#marks_table .sorting:after,
#marks_table .sorting_asc:before,
#marks_table .sorting_asc:after,
#marks_table .sorting_desc:before,
#marks_table .sorting_desc:after {
	display: none !important;
	content: none !important;
}
.marks-entry-input {
	min-height: 44px;
	font-size: 16px;
	text-align: center;
	-moz-appearance: textfield;
	width: 100%;
}
.marks-entry-input::-webkit-outer-spin-button,
.marks-entry-input::-webkit-inner-spin-button,
#outofmarks::-webkit-outer-spin-button,
#outofmarks::-webkit-inner-spin-button {
	-webkit-appearance: none;
	margin: 0;
}
#outofmarks, #examDate {
	-moz-appearance: textfield;
	min-height: 44px;
	font-size: 16px;
	width: 100%;
}
.dataTables_wrapper .dataTables_info,
.dataTables_wrapper .dataTables_filter,
.dataTables_wrapper .dataTables_paginate,
.dataTables_wrapper .dataTables_length,
.dataTables_empty {
	display: none !important;
}
@media (max-width: 767px) {
	.marks-entry-body .app-header {
		flex-direction: column !important;
		align-items: stretch !important;
		height: auto !important;
		min-height: 0;
		gap: 2px;
		padding: 8px 12px !important;
	}
	.marks-entry-body .app-header > div {
		margin: 0 !important;
		text-align: left !important;
		width: 100% !important;
		max-width: 100% !important;
	}
	.marks-entry-body .page-title-heading {
		font-size: 1.15rem;
		line-height: 1.25;
	}
	.marks-entry-body .page-title-subheading { display: none; }
	.marks-entry-body .app-header-right { display: none; }
	.marks-entry-body .app-main__inner {
		padding-left: 8px !important;
		padding-right: 8px !important;
	}
	#dv_marks { max-height: none; overflow: visible; padding-bottom: 28px; }
	#marks_table td:last-child { width: 92px; min-width: 92px; }
}
@media (min-width: 768px) {
	.marks-filter-item { flex: 1 1 280px; max-width: 420px; }
	.marks-entry-grid { flex-wrap: nowrap; }
	.marks-entry-card { flex: 0 0 340px; width: 340px; }
	#dv_marks {
		flex: 1 1 auto;
		max-height: min(70vh, 640px);
		overflow: auto;
	}
	.marks-entry-actions {
		flex-direction: row;
		flex-wrap: wrap;
	}
	.marks-entry-actions .btn { flex: 1 1 30%; width: auto; }
}
</style>
<form action="<?= base_url('manipulate_marks'); ?>" class="validate autoSubmit marks-entry-page" id="form"
	  xmlns="http://www.w3.org/1999/html">
	<div class="col-sm-12">
		<?php if (isset($_SESSION['success'])) {
			?>
			<div class="alert alert-success">
				<h5><?= lang("app.success"); ?> </h5>
				<p><?= $_SESSION['success']; ?></p>
			</div>
			<?php
		}
		?>
		<?php if (isset($_SESSION['error'])) {
			?>
			<div class="alert alert-danger">
				<h5><?= lang("app.sError"); ?> </h5>
				<p><?= $_SESSION['error']; ?></p>
			</div>
			<?php
		}
		?>
		<?php if (isset($error)) {
			?>
			<div class="alert alert-danger">
				<h5><?= lang("app.sError"); ?> </h5>
				<p><?= $error; ?></p>
			</div>
			<?php
		}
		?>
	</div>
	<?php if (!isset($error)) {
		?>
		<div class="marks-filters">
			<?php
			$type = $_GET['marktype'];
			if ($type == 4) {
				?>
				<div class="marks-filter-item">
					<label class="mb-0">
						<input type="checkbox" id="checkSheet"> <?= lang("app.uploadExcel"); ?>
					</label>
				</div>
			<?php } ?>
			<div class="marks-filter-item">
				<label for="select_course"><?= lang("app.course"); ?></label>
				<select class="form-control select2" id="select_course" name="course" required>
					<option value="" selected disabled><?= lang("app.course"); ?> </option>
					<?php
					foreach ($courses as $course) {
						?>
						<option id="course_marks<?= $course['id']; ?>" data-course="<?= $course['marks']; ?>"
								value="<?= $course['id']; ?>"> <?= $course['title']; ?>
							-<?= $course['code']; ?></option>
						<?php
					} ?>
				</select>
			</div>
			<?php
			$type = $_GET['marktype'];
			if ($type == 1) {
				?>
				<div class="marks-filter-item">
					<label for="catype"><?= lang("app.catType"); ?></label>
					<select class="form-control select2" id="catype" name="catType">
						<option selected disabled><?= lang("app.catType"); ?> </option>
						<option disabled><?= lang("app.quiz"); ?> </option>
						<option value="Q1"><?= lang("app.quiz1"); ?> </option>
						<option value="Q2"><?= lang("app.quiz2"); ?> </option>
						<option value="Q3"><?= lang("app.quiz3"); ?> </option>
						<option value="Q4"><?= lang("app.quiz4"); ?> </option>
						<option value="Q5"><?= lang("app.quiz5"); ?> </option>
						<option disabled><?= lang("app.test"); ?> </option>
						<option value="T1"><?= lang("app.test1"); ?> </option>
						<option value="T2"><?= lang("app.test2"); ?> </option>
						<option value="T3"><?= lang("app.test3"); ?> </option>
						<option value="T4"><?= lang("app.test4"); ?> </option>
						<option value="T5"><?= lang("app.test5"); ?> </option>
						<option disabled><?= lang("app.homework"); ?> </option>
						<option value="H1"><?= lang("app.homework1"); ?> </option>
						<option value="H2"><?= lang("app.homework2"); ?> </option>
						<option value="H3"><?= lang("app.homework3"); ?> </option>
						<option value="H4"><?= lang("app.homework4"); ?> </option>
						<option value="H5"><?= lang("app.homework5"); ?> </option>
					</select>
				</div>
				<?php
			} ?>
			<div class="marks-filter-item" id="select_class_div">
				<label for="select_class"><?= lang("app.sClass"); ?></label>
				<select class="form-control select2" name="class_id_name" id="select_class" required>
					<option selected disabled><?= lang("app.sClass"); ?> </option>
				</select>
			</div>
			<input type="hidden" value="<?php echo $_GET['marktype']; ?>" name="marktype" id="marktype">
			<input type="hidden" value="<?php echo isset($_GET['period']) ? $_GET['period'] : ''; ?>" name="period"
				   id="period1">
			<input type="hidden" value="<?php echo $_GET['term']; ?>" name="term" id="term">
		</div>
		<?php
	}
	?>
	<div class="card-body" id="mannualUpload" style="padding: 0;">
		<div class="marks-entry-grid">
			<div class="marks-entry-card">
				<?php
				$period_str = !isset($_GET['period']) || $_GET['period'] == 0 ? "" : "#" . lang("app.period") . ':' . $_GET['period'];
				?>
				<h4><?= \App\Controllers\Home::marksTypeToStr($_GET['marktype']) . ' ' . $period_str ?></h4>
				<div class="marks-field">
					<span><?= lang("app.selectedAcademic"); ?></span>
					<strong><?= $academic_year ?></strong>
				</div>
				<div class="marks-field">
					<span><?= lang("app.selectedTerm"); ?></span>
					<strong><?= \App\Controllers\Home::TermToStr($term) ?></strong>
				</div>
				<div class="marks-field">
					<span><?= lang("app.teacher"); ?></span>
					<strong><?= $soma_name; ?></strong>
				</div>
				<div class="marks-field">
					<label for="outofmarks"><?= lang("app.totalMarks"); ?></label>
					<input type="number" min="0" step="any" class="form-control" name="outofmarks" required
						   id="outofmarks">
					<small class="marks-max-live" id="marksMaxLive"></small>
				</div>
				<p class="marks-help">Leave empty (grey <strong>-</strong>) if the student did not sit the test — it counts as 0 in totals. Enter <strong>0</strong> only when they scored zero.</p>
				<div class="marks-field">
					<label for="examDate"><?= lang("app.dateGiven"); ?></label>
					<input type="date" class="form-control" name="examDate" required id="examDate" value="<?= date('Y-m-d'); ?>">
					<input type="hidden" class="form-control" name="year" required value="<?=$academic_year_id;?>">
				</div>
				<div class="marks-entry-actions">
					<button type="submit" class="btn btn-success btn-lg" data-target="reload"
							disabled><?= lang("app.save"); ?> </button>
					<?php
					if (is_allowed(1, 3)) {
						?>
						<a href="<?= base_url('get_student_marks'); ?>"
						   class="btn btn-primary btn-lg disabled" id="export_pdf"
						   target="_blank"><i class="fa fa-file-pdf"></i> <?= lang("app.export"); ?>
						</a>
						<button class="btn btn-warning btn-lg" type="button" id="btn-del-marks"
								disabled>
							<i class="fa fa-trash"></i> <?= lang("app.del"); ?> </button>
						<?php
					}
					?>
				</div>
			</div>
			<div id="dv_marks">
				<h3 style="text-align: center;margin: 24px 8px"><?= lang("app.selectCourseAndClass"); ?> </h3>
			</div>
		</div>
	</div>
</form>
<div class="card-body" id="execelUpload" style="display: none">
	<div id="example_wrapper" class="dataTables_wrapper dt-bootstrap4">
		<div class="row" style="background-color: white;">
			<form action="<?= base_url("down_student_marks_template"); ?>" method="POST" class="validate" id="form">
				<div class="col-sm-4" style="max-width: 100%">
					<label><i class="fa fa-download"></i> <?= lang("app.exceltemplate"); ?>  </label>
					<input type="hidden" name="check_class" id="check_class">
					<input type="hidden" name="check_class_name" id="check_class_name">
					<input type="hidden" name="course_id" id="course_id">
					<input type="hidden" name="ids" id="ids">
					<input type="hidden" name="course_name" id="course_name">
					<input type="hidden" name="year" value="<?=$academic_year_id;?>">
					<input type="hidden" name="course_marks" id="course_marks_up">
					<button type="submit" class="btn btn-primary form-control"
							data-target="<?= base_url('manage_courses'); ?>"><i
								class="fa fa-download"></i> <?= lang("app.download"); ?> </button>
				</div>
			</form>
			<div class="col-sm-4" style="max-height: 500px;overflow: auto">
				<form action="<?= base_url('uploadExcelMarks'); ?>" method="POST" enctype="multipart/form-data"
					  class="validate" id="formUploadExecel">
					<label><i class="fa fa-upload"></i><?= lang("app.chooseFile"); ?> </label><br>
					<input type="file" name="documents" class="btn btn-success">
					<input type="hidden" name="check_class" id="check_class_up">
					<input type="hidden" name="course_id" id="check_course_up">
					<input type="hidden" name="year" value="<?=$academic_year_id;?>">
					<input type="hidden" name="term" value="<?=$term;?>">
					<input type="hidden" name="course_marks" id="course_marks">
			</div>
			<div class="col-sm-4" style="max-height: 500px; overflow: auto">
				<button type="submit" class="btn btn-success btn-lg" style="margin-top: 28px;"><i
							class="fa fa-check"></i> <?= lang("app.Upload"); ?> </button>
				</form>
			</div>
		</div>
	</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@7.12.15/dist/sweetalert2.all.min.js"></script>
<script>
	$(function () {
		$('body').addClass('marks-entry-body');
		function initMarksSelect2($scope) {
			($scope || $('.marks-entry-page')).find('select.select2').each(function () {
				var $el = $(this);
				var $parent = $el.closest('.marks-filter-item');
				if ($el.data('select2')) {
					$el.select2('destroy');
				}
				$el.select2({
					width: '100%',
					dropdownParent: $parent.length ? $parent : $el.parent()
				});
			});
		}
		initMarksSelect2();
		$('#btn-del-marks').on("click",function (){
			if(confirm("Do you want to delete current marks?")){
				console.log("Deleting marks...");
				$.post(base_url+"delete_marks","data="+$("[name='marks_id[]']").map(function () {
					return $(this).val();
				}).get()+"&data1="+$("[name='marks_id1[]']").map(function () {
					return $(this).val();
				}).get()+"&term=<?=$term;?>"+"&year=<?=$academic_year_id;?>",function (data) {
					if (data.hasOwnProperty('success')){
						done(data.success);
					}else if (data.hasOwnProperty('error')){
						notdone("Oops, Marks not deleted "+data.error)
					}else{
						notdone("Oops, Marks not deleted, please try again later")
					}
				}).fail(function () {
					notdone("Oops, system error occurred, please try again later")
				});
			}
		});
		$("#select_course").on("change", function (e) {
			let val = $(this).val();
			let course_name = $("#select_course option:selected").text();
			// alert(course_name);
			$("#course_name").val(course_name);
			$("#course_id").val(val);
			//let course_marks = $("#course_marks<?//= $course['id'];?>//").data("course");
			let course_marks = $("#course_marks"+val).data("course");
			$("#course_marks").val(course_marks);
			$("#check_course_up").val(val);
			$("#course_marks_up").val(course_marks);
			if ($("#select_class").data("select2")) {
				$("#select_class").select2("destroy");
			}
			$("#select_class").load("<?= base_url(); ?>get_class/" + val+"/"+$("[name='year']").val(), function () {
				initMarksSelect2($("#select_class_div"));
				populate_marks();
			});
		});

		$("#select_class").on("change", function () {
			populate_marks();
		})
		$("#catype").on("change", function () {
			populate_marks();
		})
		$("#checkSheet").on("click", function () {
			if ($(this).prop("checked") == true) {
				$("#mannualUpload").hide();
				$('[type="submit"]').prop("disabled", false);
				$("#select_class").on("change", function () {
					var id = $(this).val();
					$("#check_class").val(id);
					$("#check_class_up").val(id);
					var cls = $("#select_class option:selected").text();
					$("#check_class_name").val(cls);
					$("#execelUpload").show();
				})
			} else {
				window.location.reload();
				$("#mannualUpload").show();
				$("#execelUpload").hide();

			}
		})

	})

	function resetView() {
		$('[type="submit"]').prop("disabled", true);
		$("#dv_marks").html("");
		// $("#form")[0].reset();
		$('#outofmarks').val("").prop('disabled', false).prop('readonly', false);
		var today = new Date();
		var yyyy = today.getFullYear();
		var mm = String(today.getMonth() + 1).padStart(2, '0');
		var dd = String(today.getDate()).padStart(2, '0');
		$('#examDate').val(yyyy + '-' + mm + '-' + dd).prop('readonly', false).prop('disabled', false);
		$('#btn-del-marks').prop('disabled',true);
	}

	function populate_marks() {
		var id = $("#select_class").val() + "/";
		var mt = $("#marktype").val() + "/";
		var ct = $("#catype").val() + "/";
		var course = $("#select_course").val() + "/";
		var period = $("#period1").val().length == 0 ? '0/' : ($("#period1").val() + "/");
		var term = $("#term").val();
		if ($("#select_class").val() == null || $("#select_course").val() == null)
			return;
		resetView();
		$("#export_pdf").prop("href", "<?= base_url(''); ?>get_student_marks/" + mt + ct + id + course + period + term+"/"+$("[name='year']").val() + "?pdf").removeClass("disabled");
		$("#dv_marks").load("<?= base_url(''); ?>get_student_marks/" + mt + ct + id + course + period + term+"/"+$("[name='year']").val(), function () {
			bindMarksEntryInputs();
		});
	}

	function bindMarksEntryInputs() {
		if (window.jQuery && $.fn.DataTable && $('#marks_table').length && $.fn.DataTable.isDataTable('#marks_table')) {
			$('#marks_table').DataTable().destroy();
			$('#marks_table').removeClass('dataTable dtr-inline no-footer');
			$('#marks_table').closest('.dataTables_wrapper').find('.dataTables_info, .dataTables_filter, .dataTables_paginate, .dataTables_empty').remove();
		}
		function marksOutOf() {
			var n = parseFloat($("#outofmarks").val());
			return (isNaN(n) || n < 0) ? null : n;
		}
		function setMarkHint($input, msg) {
			var $hint = $input.next(".marks-live-hint");
			if (!$hint.length) {
				$hint = $("<div class=\"marks-live-hint\"></div>");
				$input.after($hint);
			}
			$hint.text(msg || "");
			$input.toggleClass("is-over-max", !!msg);
		}
		function checkMarkAgainstTotal($input, capOver) {
			var raw = String($input.val() == null ? "" : $input.val());
			if (raw === "-" || raw === "--") {
				$input.val("");
				setMarkHint($input, "");
				return true;
			}
			raw = $.trim(raw);
			if (raw === "") {
				setMarkHint($input, "");
				return true;
			}
			if (raw === "0" || raw === "0.0") {
				$input.val("0");
				setMarkHint($input, "");
				return true;
			}
			if (!isFinite(Number(raw))) {
				setMarkHint($input, "Enter a number");
				return false;
			}
			var n = Number(raw);
			var max = marksOutOf();
			if (max === null) {
				setMarkHint($input, "Set Total Marks first");
				return false;
			}
			if (n > max) {
				if (capOver) {
					$input.val(String(max));
				}
				setMarkHint($input, "Cannot be more than " + max);
				return false;
			}
			if (n < 0) {
				$input.val("");
				setMarkHint($input, "");
				return true;
			}
			setMarkHint($input, "");
			return true;
		}
		function refreshMarksMaxLive() {
			var max = marksOutOf();
			$("#marksMaxLive").text(max === null ? "" : ("Each mark must be 0–" + max));
			$(".marks-entry-input").each(function () {
				checkMarkAgainstTotal($(this), true);
			});
		}
		$(document).off(".marksEntry");
		$(document).on("focus.marksEntry", ".marks-entry-input", function () {
			var v = String($(this).val()).trim();
			if (v === "-" || v === "--") {
				$(this).val("");
			}
		});
		$(document).on("input.marksEntry", ".marks-entry-input", function () {
			checkMarkAgainstTotal($(this), true);
		});
		$(document).on("blur.marksEntry", ".marks-entry-input", function () {
			checkMarkAgainstTotal($(this), true);
		});
		$(document).off(".marksOutOf").on("input.marksOutOf change.marksOutOf", "#outofmarks", refreshMarksMaxLive);
		refreshMarksMaxLive();
	}
</script>


<script>
	$(function () {
		$(document).on('submit', '#formUploadExecel', function (event) {
			event.preventDefault();
			$.ajax({
				url: "<?php echo base_url('uploadExcelMarks') ?>",
				method: 'POST',
				data: new FormData(this),
				dataType: "json",
				contentType: false,
				processData: false,
				cache: false,
				async: false,
				success: function (data) {
					try {
						// json = JSON.parse(data);
						if (data.hasOwnProperty("error")) {
							notdone(data.error);
						} else {
							done(data.success);
							$('#formUploadExecel')[0].reset();
						}
					} catch (e) {
						alert("System error please try again later");
						console.log(e);
					}
				}
			});
		});

	});


	function done(value) {
		swal({
			title: "well done!!",
			text: value,
			type: "success",
			closeOnConfirm: false
		});
		setTimeout(function () {
			window.location.reload();
		}, 2000);
	}

	function notdone(value) {
		swal({
			title: "Oops!!",
			text: value,
			type: "error",
			closeOnConfirm: false

		});

	}

</script>
