<link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/sweetalert2@7.12.15/dist/sweetalert2.min.css'>
<form action="<?= base_url('manipulate_marks'); ?>" class="validate autoSubmit" id="form"
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
		<div class="row" style="background-color: white;">
			<?php
			$type = $_GET['marktype'];
			if ($type == 4) {
				?>
				<div style="width: 10%;"><input type="checkbox"
												id="checkSheet"><label><?= lang("app.uploadExcel"); ?> </label></div>
			<?php } ?>
			<div style="margin-top: 20px;">
				<div class="form-group" style="width: 300px">
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
			</div>
			<?php
			$type = $_GET['marktype'];
			if ($type == 1) {
				?>

				<div style="margin-top: 20px;margin-left: 20px">
					<div class="form-group" style="width: 300px">
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
				</div>
				<?php
			} else { ?>
				<div class="col-sm-1" style="margin-top: 20px;display: none;">
				</div>
				<?php
			} ?>
			<div style="margin-top: 20px;margin-left: 20px" id="select_class_div">
				<div class="form-group" style="width: 300px">
					<select class="form-control select2" name="class_id_name" id="select_class" required>
						<option selected disabled><?= lang("app.sClass"); ?> </option>

					</select>
				</div>
			</div>
			<input type="hidden" value="<?php echo $_GET['marktype']; ?>" name="marktype" id="marktype">
			<input type="hidden" value="<?php echo isset($_GET['period']) ? $_GET['period'] : ''; ?>" name="period"
				   id="period1">
			<input type="hidden" value="<?php echo $_GET['term']; ?>" name="term" id="term">
		</div>
		<?php
	}
	?>
	<div class="card-body" id="mannualUpload">
		<div id="example_wrapper" class="dataTables_wrapper dt-bootstrap4">
			<div class="row">
				<div class="col-sm-4">
					<div style="background-color: white;width:100%;">
						<?php
						$period_str = !isset($_GET['period']) || $_GET['period'] == 0 ? "" : "#" . lang("app.period") . ':' . $_GET['period'];
						?>
						<h4 style="width: 100%;float:left;border-bottom: 1px solid #cdcdcd;padding: 10px;"><?= \App\Controllers\Home::marksTypeToStr($_GET['marktype']) . ' ' . $period_str ?></h4>
						<table style="width:100%">
							<tr>
								<td><?= lang("app.selectedAcademic"); ?> </td>
								<td><strong><?= $academic_year ?></strong></td>
							</tr>
							<tr>
								<td><?= lang("app.selectedTerm"); ?> </td>
								<td><strong><?= \App\Controllers\Home::TermToStr($term) ?></strong></td>
							</tr>
							<tr>
								<td><?= lang("app.teacher"); ?> </td>
								<td><strong><?= $soma_name; ?></strong></td>
							</tr>
							<tr>
								<td><?= lang("app.totalMarks"); ?> </td>
								<td><input type="number" class="form-control" name="outofmarks" required
										   id="outofmarks">
								</td>
							</tr>
							<tr>
								<td><?= lang("app.dateGiven"); ?> </td>
								<td><input type="date" class="form-control" name="examDate" required id="examDate"></td>
								<td><input type="hidden" class="form-control" name="year" required value="<?=$academic_year_id;?>"></td>
							</tr>
							<tr>
								<td colspan="2">
									<center>
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
									</center>
								</td>
							</tr>
						</table>
					</div>
				</div>
				<div class="col-sm-8" style="background-color: white;max-height: 500px;overflow: auto" id="dv_marks">
					<h3 style="text-align: center;margin-top: 50px"><?= lang("app.selectCourseAndClass"); ?> </h3>
				</div>
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
			$("#select_class").load("<?= base_url(); ?>get_class/" + val+"/"+$("[name='year']").val(), function () {
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
		$('#examDate').val('');
		$('#btn-del-marks').prop('disabled',true);
		$('#examDate').prop('disabled', false);
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
		});
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
