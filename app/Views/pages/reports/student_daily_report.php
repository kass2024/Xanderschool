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

	td {
		text-align: center;
		font-size: 10pt;
	}

	.right_border {
		border-right: 2px solid #000;
	}

	.total td {
		font-weight: bold;
		background-color: #dcdcdc;
	}
</style>
<?php
if ($show_header) {
	?>
	<div class="col-12">
		<form id="frm_report" method="get" target="_blank"
			  action="<?= base_url('student_daily_report_data/true'); ?>">
			<div class="col-md-3 pull-left">
				<label><?= lang("app.date"); ?> :</label>
				<input type="date" placeholder="<?= lang("app.startDate"); ?> " class="form-control" id="date1"
					   name="date1">
			</div>
			<div class="col-md-3 pull-left" style="margin-top: 30px;">
				<button class="btn btn-secondary" id="btn_generate"><?= lang("app.generate"); ?> </button>
				<button class="btn btn-primary" type="submit"><i class="fa fa-file-pdf"></i> <?= lang("app.export"); ?>
				</button>
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
				if ($("#date1").val() == "") {
					toastada.warning('<?= lang("app.strtDateErr"); ?>');
					return;
				}
				$("#btn_generate").text('<?= lang("app.pleaseWait"); ?>').prop("disabled", true);
				$("#report_content").load("<?=base_url('student_daily_report_data');?>", $("#frm_report").serialize(), function () {
					$("#btn_generate").text('<?= lang("app.generate"); ?>').prop("disabled", false);
				})
			});
		});
	</script>
	<?php
} else {
	if (count($classes) == 0) {
		echo "<h4 style='width: 100%;float: left;text-align: center;margin-top: 15px'>" . lang("app.noDailyAttendance") . "</h4>";
		die();
	}
	?>
	<div style="margin-top: 15px;width: 100%;float:left;" id="printable">
		<div class="col-md-12 col-sm-12 pull-left" style="margin-bottom: 15px">
			<div style="background:white;padding: 10px;overflow: auto;">
				<?php
				if ($pdf) {
					?>
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
								<img src="<?= base_url('assets/images/logo/' . $school_logo); ?>"
									 style="width: 110px"><br>
							</div>
						</div>
						<br>
						<h4 style="text-decoration: underline;width: 100%;float: left;text-align: center;"><?= lang("app.classDailyAttendance"); ?> </h4>
					</div>
					<?php
				}
				?>
				<div class="col-sm-12">
					<div class="col-md-6 pull-left">
						<span><b><?= lang("app.date"); ?>  </b> : <?= $date1; ?></span><br>
					</div>
					<div class="pull-right" style="margin-top: 10px;margin-right: 10px">
						<span><b><?= lang("app.printedOn"); ?>  </b> : <?= date("Y-m-d H:i"); ?></span><br>
					</div>
				</div>
				<table class="tablepage" border="1">
					<!--Table head-->
					<tbody id="disciplineTable">
					<tr>
						<td class="right_border" style="min-width: 80px"><strong><?= lang("app.sClass"); ?> </strong>
						</td>
						<?php
						if (!in_array($school_id, [28])) {
							?>
							<td colspan="9" class="right_border"><strong><?= lang("app.boarding"); ?> </strong></td>
							<?php
						}
						?>
						<td colspan="9" class="right_border"><strong><?= lang("app.day"); ?> </strong></td>
	<?php
	if (!in_array($school_id, [28])) {
		?>
						<td colspan="9" class="right_border"><strong><?= lang("app.totBoardDay"); ?> </strong></td>
		<?php
	}
		?>
					</tr>
					<tr>
						<td class="right_border"></td>
	<?php
	if (!in_array($school_id, [28])) {
		?>
						<td colspan="3"><strong><?= lang("app.registered"); ?> </strong></td>
						<td colspan="3"><strong><?= lang("app.present"); ?> </strong></td>
						<td colspan="3" class="right_border"><strong><?= lang("app.absents"); ?> </strong></td>
		<?php
	}
		?>
						<td colspan="3"><strong><?= lang("app.registered"); ?> </strong></td>
						<td colspan="3"><strong><?= lang("app.present"); ?> </strong></td>
						<td colspan="3" class="right_border"><strong><?= lang("app.absents"); ?> </strong></td>
	<?php
	if (!in_array($school_id, [28])) {
		?>
						<td colspan="3"><strong><?= lang("app.registered"); ?> </strong></td>
						<td colspan="3"><strong><?= lang("app.present"); ?> </strong></td>
						<td colspan="3" class="right_border"><strong><?= lang("app.absents"); ?> </strong></td>
		<?php
	}
		?>
					</tr>
					<tr>
						<td class="right_border"></td>
	<?php
	if (!in_array($school_id, [28])) {
		?>
						<td><strong><?= lang("app.male"); ?> </strong></td>
						<td><strong><?= lang("app.female"); ?> </strong></td>
						<td><strong><?= lang("app.tot"); ?> </strong></td>
						<td><strong><?= lang("app.male"); ?> </strong></td>
						<td><strong><?= lang("app.female"); ?> </strong></td>
						<td><strong><?= lang("app.tot"); ?> </strong></td>
						<td><strong><?= lang("app.male"); ?> </strong></td>
						<td><strong><?= lang("app.female"); ?> </strong></td>
						<td class="right_border"><strong><?= lang("app.tot"); ?> </strong></td>
		<?php
	}
		?>
						<td><strong><?= lang("app.male"); ?> </strong></td>
						<td><strong><?= lang("app.female"); ?> </strong></td>
						<td><strong><?= lang("app.tot"); ?> </strong></td>
						<td><strong><?= lang("app.male"); ?> </strong></td>
						<td><strong><?= lang("app.female"); ?> </strong></td>
						<td><strong><?= lang("app.tot"); ?> </strong></td>
						<td><strong><?= lang("app.male"); ?> </strong></td>
						<td><strong><?= lang("app.female"); ?> </strong></td>
						<td class="right_border"><strong><?= lang("app.tot"); ?> </strong></td>
						<?php
						if (!in_array($school_id, [28])) {
							?>
							<td><strong><?= lang("app.male"); ?> </strong></td>
							<td><strong><?= lang("app.female"); ?> </strong></td>
							<td><strong><?= lang("app.tot"); ?> </strong></td>
							<td><strong><?= lang("app.male"); ?> </strong></td>
							<td><strong><?= lang("app.female"); ?> </strong></td>
							<td><strong><?= lang("app.tot"); ?> </strong></td>
							<td><strong><?= lang("app.male"); ?> </strong></td>
							<td><strong><?= lang("app.female"); ?> </strong></td>
							<td class="right_border"><strong><?= lang("app.tot"); ?> </strong></td>
							<?php
						}
						?>
					</tr>
					<?php
					$a1 = 1;
					$b_male_tot = 0;
					$b_female_tot = 0;
					$d_male_tot = 0;
					$d_female_tot = 0;
					$male_tot = 0;
					$female_tot = 0;

					$b1_male_tot = 0;
					$b1_female_tot = 0;
					$d1_male_tot = 0;
					$d1_female_tot = 0;
					$male1_tot = 0;
					$female1_tot = 0;
					foreach ($classes as $item) {
						$b_male = 0;
						$b_female = 0;
						$d_male = 0;
						$d_female = 0;
						$male = 0;
						$female = 0;

						$b1_male = 0;
						$b1_female = 0;
						$d1_male = 0;
						$d1_female = 0;
						$male1 = 0;
						$female1 = 0;
						$dailyMdl = new \App\Models\DailyAttendanceModel();
						$stMdl = new \App\Models\StudentModel();
						$students = $stMdl->select("concat(students.studying_mode,':',students.sex,':',count(students.id)) as sex")
								->join("class_records cr", "cr.student=students.id")
								->where("cr.class", $item['id'])
								->where("cr.year", $academic_year)
								->where("school_id", $_SESSION["soma_school_id"])
								->groupBy("students.sex")
								->groupBy("students.studying_mode")
								->get()->getResultArray();
						foreach ($students as $student) {
							$sexs = explode(":", $student['sex']);
							if ($sexs[1] == "M") {
								//male
								$b_male += $sexs[0] == 0 ? $sexs[2] : 0;
								$d_male += $sexs[0] == 1 ? $sexs[2] : 0;
								$male += $sexs[2];

								$b_male_tot += $sexs[0] == 0 ? $sexs[2] : 0;
								$d_male_tot += $sexs[0] == 1 ? $sexs[2] : 0;
								$male_tot += $sexs[2];
							} else {
								//female
								$b_female += $sexs[0] == 0 ? $sexs[2] : 0;
								$d_female += $sexs[0] == 1 ? $sexs[2] : 0;
								$female += $sexs[2];

								$b_female_tot += $sexs[0] == 0 ? $sexs[2] : 0;
								$d_female_tot += $sexs[0] == 1 ? $sexs[2] : 0;
								$female_tot += $sexs[2];
							}
						}
						$records = $dailyMdl->select("concat(st.studying_mode,':',st.sex,':',coalesce(count(daily_attendance.datee),0)) as attendance")
								->join("students st", "st.id=daily_attendance.student_id")
								->join("class_records cr", "cr.student=st.id")
								->where("cr.class", $item['id'])
								->where("cr.year", $academic_year)
								->where("daily_attendance.datee", $date1)
								->where("school_id", $_SESSION["soma_school_id"])
								->groupBy("st.sex")
								->groupBy("st.studying_mode")
								->get()->getResultArray();
						foreach ($records as $record) {
							$attendance = explode(":", $record['attendance']);
							if (count($attendance) > 1) {
								if ($attendance[1] == "M") {
									//male
									$b1_male += $attendance[0] == 0 ? $attendance[2] : 0;
									$d1_male += $attendance[0] == 1 ? $attendance[2] : 0;
									$male1 += $attendance[2];

									$b1_male_tot += $attendance[0] == 0 ? $attendance[2] : 0;
									$d1_male_tot += $attendance[0] == 1 ? $attendance[2] : 0;
									$male1_tot += $attendance[2];
								} else {
									//female
									$b1_female += $attendance[0] == 0 ? $attendance[2] : 0;
									$d1_female += $attendance[0] == 1 ? $attendance[2] : 0;
									$female1 += $attendance[2];

									$b1_female_tot += $attendance[0] == 0 ? $attendance[2] : 0;
									$d1_female_tot += $attendance[0] == 1 ? $attendance[2] : 0;
									$female1_tot += $attendance[2];
								}
							}
						}
						?>
						<tr>
							<td class="right_border"><?= $item['level_name'] . ' ' . $item['code'] . ' ' . $item['title']; ?></td>
							<?php
							if (!in_array($school_id, [28])) {
								?>
								<!-- boarding total -->
								<td><?= $b_male; ?></td>
								<td><?= $b_female; ?></td>
								<td><?= $b_male + $b_female; ?></td>

								<!-- boarding present -->
								<td><?= $b1_male; ?></td>
								<td><?= $b1_female; ?></td>
								<td><?= $b1_male + $b1_female; ?></td>

								<!-- boarding absent -->
								<td><?= $b_male - $b1_male; ?></td>
								<td><?= $b_female - $b1_female; ?></td>
								<td class="right_border"><?= ($b_male + $b_female) - ($b1_male + $b1_female); ?></td>
								<?php
							}
							?>
							<!-- day total -->
							<td><?= $d_male; ?></td>
							<td><?= $d_female; ?></td>
							<td><?= $d_male + $d_female; ?></td>

							<!-- day present -->
							<td><?= $d1_male; ?></td>
							<td><?= $d1_female; ?></td>
							<td><?= $d1_male + $d1_female; ?></td>

							<!-- day absent -->
							<td><?= $d_male - $d1_male; ?></td>
							<td><?= $d_female - $d1_female; ?></td>
							<td class="right_border"><?= ($d_male + $d_female) - ($d1_male + $d1_female); ?></td>
							<?php
							if (!in_array($school_id, [28])) {
								?>
								<!-- day & boarding total -->
								<td><?= $male; ?></td>
								<td><?= $female; ?></td>
								<td><?= $male + $female; ?></td>

								<!-- day present -->
								<td><?= $male1; ?></td>
								<td><?= $female1; ?></td>
								<td><?= $male1 + $female1; ?></td>

								<!-- day absent -->
								<td><?= $male - $male1; ?></td>
								<td><?= $female - $female1; ?></td>
								<td class="right_border"><?= ($male + $female) - ($male1 + $female1); ?></td>
								<?php
							}
							?>
						</tr>
						<?php
						$a1++;
					}
					?>
					<tr class="total">
						<td class="right_border"><?= lang("app.total"); ?> </td>
						<?php
						if (!in_array($school_id, [28])) {
							?>
							<!-- boarding total -->
							<td><?= $b_male_tot; ?></td>
							<td><?= $b_female_tot; ?></td>
							<td><?= $b_male_tot + $b_female_tot; ?></td>

							<!-- boarding present -->
							<td><?= $b1_male_tot; ?></td>
							<td><?= $b1_female_tot; ?></td>
							<td><?= $b1_male_tot + $b1_female_tot; ?></td>

							<!-- boarding absent -->
							<td><?= $b_male_tot - $b1_male_tot; ?></td>
							<td><?= $b_female_tot - $b1_female_tot; ?></td>
							<td class="right_border"><?= ($b_male_tot + $b_female_tot) - ($b1_male_tot + $b1_female_tot); ?></td>
							<?php
						}
						?>
						<!-- day total -->
						<td><?= $d_male_tot; ?></td>
						<td><?= $d_female_tot; ?></td>
						<td><?= $d_male_tot + $d_female_tot; ?></td>

						<!-- day present -->
						<td><?= $d1_male_tot; ?></td>
						<td><?= $d1_female_tot; ?></td>
						<td><?= $d1_male_tot + $d1_female_tot; ?></td>

						<!-- day absent -->
						<td><?= $d_male_tot - $d1_male_tot; ?></td>
						<td><?= $d_female_tot - $d1_female_tot; ?></td>
						<td class="right_border"><?= ($d_male_tot + $d_female_tot) - ($d1_male_tot + $d1_female_tot); ?></td>
						<?php
						if (!in_array($school_id, [28])) {
							?>
							<!-- day & boarding total -->
							<td><?= $male_tot; ?></td>
							<td><?= $female_tot; ?></td>
							<td><?= $male_tot + $female_tot; ?></td>

							<!-- day present -->
							<td><?= $male1_tot; ?></td>
							<td><?= $female1_tot; ?></td>
							<td><?= $male1_tot + $female1_tot; ?></td>

							<!-- day absent -->
							<td><?= $male_tot - $male1_tot; ?></td>
							<td><?= $female_tot - $female1_tot; ?></td>
							<td class="right_border"><?= ($male_tot + $female_tot) - ($male1_tot + $female1_tot); ?></td>
							<?php
						}
						?>
					</tr>
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
