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
					<option disabled selected><?= lang("app. selectCourse"); ?></option>
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
				<label><?= lang("app.startDate"); ?> :</label>
				<input type="date" placeholder="S<?= lang("app.startDate"); ?>" class="form-control" id="date1" name="date1">
			</div>
			<div class="col-md-3 pull-left">
				<label><?= lang("app.endDate"); ?>:</label>
				<input type="date" placeholder="<?= lang("app.endDate"); ?>" class="form-control" id="date2" name="date2">
			</div>
			<div class="col-md-3 pull-left" style="margin-top: 5px;">
				<button class="btn btn-secondary" id="btn_generate"><?= lang("app.generate"); ?> </button>
				<button class="btn btn-primary" type="submit"><i class="fa fa-file-pdf"></i> <?= lang("app.export"); ?>  </button>
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
					toastada.warning('<?= lang("app.classisRequired"); ?>');
					return;
				}
				if ($("#date1").val() == "") {
					toastada.warning('<?= lang("app.strtDateErr"); ?>');
					return;
				}
				if ($("#date2").val() == "") {
					toastada.warning('<?= lang("app.endDateErr"); ?>');
					return;
				}
				$("#btn_generate").text('<?= lang("app.pleaseWait"); ?>').prop("disabled", true);
				$("#report_content").load("<?=base_url('student_course_report_data');?>", $("#frm_report").serialize(), function () {
					$("#btn_generate").text('<?= lang("app.generate"); ?>').prop("disabled", false);
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
	$total_days = get_total_days($date1, $date2);
	?>
	<div style="margin-top: 15px;width: 100%;float:left;" id="printable">
		<div class="col-md-12 col-sm-12 pull-left" style="margin-bottom: 15px">
			<div style="background:white;padding: 10px;overflow: auto;">
				<div class="col-sm-12">
					<div class="col-md-6 pull-left">
						<span><b><?= lang("app.republic"); ?> </b></span><br>
						<span><b><?= lang("app.ministry"); ?> </b></span><br>
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
						<span><b><?= lang("app.class"); ?> : </b><?= $classe; ?></span><br>
						<span><b><?= lang("app.teacher"); ?> : </b><?= $course['lecturer']; ?></span><br>
						<span><b><?= lang("app.totalStudents"); ?> : </b> <?= count($students); ?></span><br>
					</div>
					<div class="pull-right" style="margin-top: 10px;margin-right: 10px">
						<span><b><?= lang("app.from"); ?>  </b> : <?= date("Y-m-d H:i"); ?></span><br>
						<span><b><?= lang("app.to"); ?>  </b> : <?= date("Y-m-d H:i"); ?></span><br>
						<span><b><?= lang("app.printedOn "); ?> </b> : <?= date("Y-m-d H:i"); ?></span><br>
					</div>
				</div>
				<table class="tablepage" border="1">
					<!--Table head-->
					<tbody id="disciplineTable">
					<tr>
						<td style="text-align: right"><strong>#</strong></td>
						<td><strong><?= lang("app.firstName"); ?> </strong></td>
						<td><strong><?= lang("app.lastName"); ?> </strong></td>
						<?php
						$a = 1;
						$last_day = date("t", strtotime($month."-01"));
						for ($a; $a <= $last_day; $a++) {
							echo "<td style='text-align: center'>" . sprintf("%02d", $a) . "</td>";
						}
						?>
					</tr>
					<?php
					$a1 = 1;
					foreach ($students as $item) {
						?>
						<tr>
							<td style="text-align: right"><?= $a1; ?></td>
							<td><?= $item['fname']; ?></td>
							<td><?= $item['lname']; ?></td>
							<?php
							$a2 = 1;
							for ($a2; $a2 <= $last_day; $a2++) {
								$ins = explode(",", $item['records']);
								$in = "";
								$in_data = "";
								$out = "";
								$out_data = "";
								$searchOut = false;
								foreach ($ins as $in1) {
									if (strlen($in1) == 0)
										continue;
									if (($dt = explode(" ", $in1))[0] == $a2 && !$searchOut) {
										$in = $dt[1] . "<br>";
										$in_data = $dt[1];
										$searchOut = true;
									}
									if (($dt = explode(" ", $in1))[0] == $a2 && $searchOut && $out_data < $dt[1] && $in_data != $dt[1])
										$out = "-<br>" . $dt[1];
									$out_data = $dt[1];
								}
								echo "<td class='td_date'>$in $out</td>";
							}
							?>
						</tr>
						<?php
						$a1++;
					}
					?>
					</tbody>
					<!--Table body-->
				</table>
				<!--Table-->
				<div class="col-md-12 col-sm-12 ">
					<footer class="pull-right" style="color: darkgrey"><?= lang("app.generatedbySomanet"); ?> </footer>
				</div>
			</div>
		</div>
	</div>
	<?php
}
