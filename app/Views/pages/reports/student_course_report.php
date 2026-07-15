<style>
	.tablepage {
		width: 100%;

	}

	.td_date {
		text-align: center;
		line-height: 0.7;
		padding: 5px 0;
	}

	.boxed2 {
		padding: 10px;
		border: 1px solid #333;
		border-radius: 5px;
	}

	th, td {
		min-width: 30px;
		padding: 3px 5px;
		border: 1px solid #777777;
	}

	table {
		border-collapse: collapse;
	}

	.pull-right {
		float: right
	}

	.pull-left {
		float: left;
	}
	.text-danger{
		color: orangered;
	}
</style>
<?php
if ($show_header) {
	?>
	<div class="col-12">
		<form id="frm_report" method="get" target="_blank"
			  action="<?= base_url('student_course_report_data/true'); ?>">
			<div class="col-md-3 pull-left">
				<label><?= lang("app.course"); ?> :</label>
				<select class="select2 form-control" id="select_course" name="course">
					<option disabled selected><?= lang("app.selectCourse"); ?> </option>
					<?php
					foreach ($courses as $course):
						echo "<option value='{$course['id']}'>{$course['code']}: {$course['title']}</option>";
					endforeach;
					?>
				</select>
			</div>
			<div class="col-md-3 pull-left">
			<label><?= lang("app.sClass"); ?> :</label>
			<select class="select2 form-control" id="select_class" name="class">
				<option disabled selected><?= lang("app.selectClass"); ?> </option>
			</select>
	</div>
			<div class="col-md-3 pull-left">
				<label><?= lang("app.month"); ?> :</label>
				<select class="select2 form-control" id="choose_month" name="months">
					<option disabled selected><?= lang("app.chooseMonth"); ?> </option>
					<?php
					$months = get_months();
					for ($a = 0; $a < count($months); $a++):
						echo "<option value='".($a+1)."'>{$months[$a]}</option>";
					endfor;
					?>
				</select>
			</div>
			<div class="col-md-3 pull-left" style="margin-top: 30px;">
				<button class="btn btn-secondary" id="btn_generate"><?= lang("app.generate"); ?> </button>
				<button class="btn btn-primary" type="submit"><i class="fa fa-file-pdf"></i> <?= lang("app.export"); ?></button>
			</div>
		</form>

	</div>
	<div id="report_content">

	</div>
	<script>
		$(function () {
			$("#select_course").on("change", function (e) {
				var val = $(this).val();
				$("#select_class").load("<?= base_url(); ?>get_class/" + val);
			});
			$("#btn_generate").on("click", function (e) {
				e.preventDefault();
				if ($("#select_course").val() == null) {
					toastada.warning('<?= lang("app.pleaseSelectCourse"); ?>');
					return;
				}
				if ($("#select_class").val() == null) {
					toastada.warning("<?= lang("app.classisRequired"); ?>");
					return;
				}
				if ($("#choose_month").val()==null){
					toastada.warning("<?= lang("app.pleaseSelectMonth"); ?>");
					return;
				}
				$("#btn_generate").text("<?= lang("app.pleaseWait"); ?>" ).prop("disabled", true);
				$("#report_content").load("<?=base_url('student_course_report_data');?>", $("#frm_report").serialize(), function () {
					$("#btn_generate").text("<?= lang("app.generate"); ?>").prop("disabled", false);
				})
			});
		});
	</script>
	<?php
} else {
	if (count($students) == 0) {
		echo "<h4 style='width: 100%;float: left;text-align: center;margin-top: 15px'>".lang("app.noAttFound")."</h4>";
		die();
	}
	?>
	<div style="margin-top: 15px;width: 100%;float:left;" id="printable">
		<div class="col-md-12 col-sm-12 pull-left" style="margin-bottom: 15px">
			<div style="background:white;padding: 10px;overflow: auto;">
				<div class="col-sm-12">
					<div class="col-md-6 pull-left">
						<span><b><?= lang("app.republic"); ?> </b></span><br>
						<span><b><?= lang("app.ministry"); ?></b></span><br>
						<span><strong><?= $school_name; ?></strong></span><br>
						<span><b><?= lang("app.mail"); ?></b> : <?= $school_email; ?></span><br>
						<span><b><?= lang("app.phone"); ?> </b>  : <?= $school_phone; ?></span><br>
					</div>
					<div class="pull-right" style="margin-top: 10px;margin-right: 10px">
						<div>
							<img src="<?= base_url('assets/images/logo/' . $school_logo); ?>" style="width: 110px"><br>
						</div>
					</div>
					<br>
					<h4 style="text-decoration: underline;width: 100%;float: left;text-align: center;"><?= lang("app.studentCourse"); ?> </h4>
				</div>
				<div class="col-sm-12">
					<div class="col-md-6 pull-left">
						<span><b><?= lang("app.course"); ?> : </b><?= $course['course']; ?></span><br>
						<span><b><?= lang("app.sClass"); ?> : </b><?= $classe; ?></span><br>
						<span><b><?= lang("app.teacher"); ?> : </b><?= $course['lecturer']; ?></span><br>
						<span><b><?= lang("app.totalStudents"); ?> : </b> <?= count($students); ?></span><br>
					</div>
					<div class="pull-right" style="margin-top: 10px;margin-right: 10px">
						<span><b><?= lang("app.month"); ?> : </b><?= $month; ?></span><br>
						<span><b><?= lang("app.printedOn"); ?>  </b> : <?= date("Y-m-d H:i"); ?></span><br>
					</div>
				</div>
				<table class="tablepage" border="1">
					<!--Table head-->
					<tbody id="disciplineTable">
					<tr>
						<td style="text-align: right"><strong>#</strong></td>
						<td><strong><?= lang("app.firstName"); ?> </strong></td>
						<td><strong><?= lang("app.lastName"); ?></strong></td>
						<?php
						$a = 1;
						$last_day = date("t", strtotime($month."-01"));
						for ($a; $a <= $last_day; $a++) {
							echo "<td style='text-align: center'>" . sprintf("%02d", $a) . "</td>";
						}
						?>
						<td><strong> %</strong></td>
					</tr>
					<?php
					$a1 = 1;
					foreach ($students as $item) {
						$tot = 0;
						$st_tot = 0;
						?>
						<tr>
							<td style="text-align: right"><?= $a1; ?></td>
							<td><?= $item['fname']; ?></td>
							<td><?= $item['lname']; ?></td>
							<?php
							$a2 = 1;
							for ($a2; $a2 <= $last_day; $a2++) {
								$caMdl = new \App\Models\CourseAttendanceModel();
								$records = $caMdl->select("course_attendance.created_at,coalesce(car.student_id,'') as dt")
									->join("course_attendance_records car","car.attendance_id=course_attendance.id AND car.student_id=".$item['id'],"left")
									->where("course_attendance.course_id",$course_id)
									->where("course_attendance.class_id",$class_id)
									->where("date_format(course_attendance.created_at,'%Y-%m-%d')",$month2."-".sprintf("%02d", $a2))
									->get(1)->getRow();
//								var_dump($records);
								if ($records == null){
									//no attendance made
									$att = "<label> - </label>";
								}else{
									$tot += 1;
									$att = $records->dt==''?"<label class='text-danger'>A</label>":"<label>P</label>";
									$st_tot += $records->dt==''?0:1;
								}

								//check if is weekend
								$day_str = date("D", strtotime($month2."-".sprintf("%02d", $a2)));
								$weekend = array("Sat","Sun");
								$style = "";
								if (in_array($day_str,$weekend)){
									$style = 'background-color: #cdcdcd';
								}
								echo "<td class='td_date' style='$style'>$att</td>";
							}
							$percentage = $tot==0?0:number_format($st_tot/$tot*100,1);
							?>
							<td><?=$percentage;?></td>
						</tr>
						<?php
						$a1++;
					}
					?>
					</tbody>
					<!--Table body-->
				</table>
				<div style="margin-top: 5px;width: 100%"></div>
				<span style="width: 20px;height: 20px;background-color: #cdcdcd;display: table-cell;text-align: center"> - </span><span style="display: table-cell">: <?= lang("app.weekend"); ?>  </span> <span style="display: table-cell;padding-left: 10px"><span class="text-danger">A</span>: <?= lang("app.absents"); ?>  </span> <span style="display: table-cell;padding-left: 10px">P:<?= lang("app.present"); ?>   </span>
				<!--Table-->
				<div class="col-md-12 col-sm-12 ">
					<footer class="pull-right" style="color: darkgrey"><?= lang("app.generatedbySomanet"); ?></footer>
				</div>
			</div>
		</div>
	</div>
	<?php
}
