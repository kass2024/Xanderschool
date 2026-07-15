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
			  action="<?= base_url('staff_monthly_report_data/true'); ?>">
			<div class="col-md-3 pull-left">
				<label><?= lang("app.selectYear"); ?> :</label>
				<select class="select2 form-control" id="select_year" name="year">
					<option disabled selected><?= lang("app.selectYear"); ?>  </option>
					<?php
					$months = get_months();
					foreach ($years as $year):
						echo "<option value='{$year}'>{$year}</option>";
					endforeach;
					?>
				</select>
			</div>
			<div class="col-md-3 pull-left">
				<label><?= lang("app.selectMonth"); ?> :</label>
				<select class="select2 form-control" id="choose_month" name="month">
					<option disabled selected><?= lang("app.chooseMonth"); ?> </option>
					<?php
					$months = get_months();
					for ($a = 0; $a < count($months); $a++):
						echo "<option value='" . ($a + 1) . "'>{$months[$a]}</option>";
					endfor;
					?>
				</select>
			</div>
			<div class="col-md-3 pull-left" style="margin-top: 30px;">
				<button class="btn btn-secondary" id="btn_generate"><?= lang("app.generate"); ?></button>
				<button class="btn btn-primary" type="submit"><i class="fa fa-file-pdf"></i> <?= lang("app.export"); ?>
				</button>
			</div>
		</form>

	</div>
	<div id="report_content">

	</div>
	<script>
		$(function () {
			$("#btn_generate").on("click", function (e) {
				e.preventDefault();
				if ($("#select_year").val() == null) {
					toastada.warning("<?= lang("app.pleaseSelectYear"); ?>");
					return;
				}
				if ($("#choose_month").val() == null) {
					toastada.warning("<?= lang("app.pleaseSelectMonth"); ?>");
					return;
				}

				$("#btn_generate").text("<?= lang("app.pleaseWait"); ?>").prop("disabled", true);
				$("#report_content").load("<?=base_url('staff_monthly_report_data');?>", $("#frm_report").serialize(), function () {
					$("#btn_generate").text("<?= lang("app.generate"); ?>").prop("disabled", false);
				})
			});
		});
	</script>
	<?php
} else {
	$first_day = $month . "-01";
	$last_day = $month . "-" . date("t", strtotime($first_day));
	?>
	<div style="margin-top: 15px;width: 100%;float:left;" id="printable">
		<div class="pull-left" style="margin-bottom: 15px;width: 100%">
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
					</div>
					<?php
				}
				?>
				<h4 style="text-decoration: underline;width: 100%;float: left;text-align: center;"><?= lang("app.employeesMonthlReport"); ?> </h4>
				<div style="width: 100%">
					<div style="float:left;width: 100%">
						<div class="col-sm-12">
							<div class="col-md-6 pull-left">
								<span><b><?= lang("app.month"); ?> : </b><?= $month; ?></span><br>
								<span><b><?= lang("app.totalStaffs"); ?> : </b> <?= count($staffs); ?></span><br>
							</div>
							<div class="pull-right" style="margin-top: 10px;margin-right: 10px">
								<span><b><?= lang("app.printedOn"); ?> </b> : <?= date("Y-m-d H:i"); ?></span><br>
							</div>
						</div>
					</div>
					<div style="width: auto;float:left;width: 100%;margin-top: 10px;">
						<table class="tablepage" border="1">
							<!--Table head-->

							<tbody id="disciplineTable">
							<tr>
								<td style="text-align: right"><strong>#</strong></td>
								<td><strong><?= lang("app.names"); ?> </strong></td>
								<td><strong><?= lang("app.StaffID"); ?> </strong></td>
								<td><strong><?= lang("app.totalDays"); ?> </strong></td>
								<td><strong><?= lang("app.leaveDays");?></strong></td>
								<td><strong><?= lang("app.presentDays"); ?> </strong></td>
								<td><strong><?= lang("app.absentDays"); ?> </strong></td>
								<td><strong><?= lang("app.lateMin"); ?> </strong></td>
								<td><strong><?= lang("app.earlyMin"); ?> </strong></td>
								<td><strong><?= lang("app.cin"); ?> </strong></td>
								<td><strong><?= lang("app.cout"); ?> </strong></td>
								<td><strong><?= lang("app.rate"); ?> </strong></td>
							</tr>
							<?php
							$a1 = 1;
							foreach ($staffs as $item) {
								$first_day = $month . "-01";
								$shift_options = json_decode($item['options'], true);
								$created_at = date("Y-m-d", strtotime($item['created_at']));
								if ($first_day < $created_at) {
									$first_day = $created_at;
								}
								$total_days = get_total_days($first_day, $last_day, $shift_options);
								?>
								<tr>
									<td style="text-align: right"><?= $a1; ?></td>
									<?php
									$a2 = 1;
									$p = 0;
									$ab = 0;
									$lm = 0;
									$lv = 0;
									$em = 0;
									$ci = 0;
									$co = 0;
									$rt = 0;

									$duplicator = "";
									if (strlen($item['records']) == 0 || $total_days == 0) {

									} else {
										if ($item['leave_start']>strtotime($last_day) || $item['leave_end']<strtotime($first_day) || ($item['leave_start']>time())){
											//current range not match with leave
											$lv = 0;
										}else{
											//calculate leave days
											$lv_start = $item['leave_start']<strtotime($first_day)?$first_day:date("Y-m-d",$item['leave_start']);
											$lv_end = $item['leave_end']>strtotime($last_day)?$last_day:date("Y-m-d",$item['leave_end']);
											$lv_end = strtotime($lv_end)>time()?date("Y-m-d"):$lv_end;//check if leave end is ahead and block it to today
											$lv = get_days_difference($lv_start, $lv_end);
										}
										$ins = explode(",", $item['records']);
										$leave_time = 0;
										foreach ($ins as $in) {
											$tttt = explode(":", $in);
											$in_time = $tttt[0];
											if ($duplicator == $in_time . '' . $item['id']) {
												//duplicate found, escape
												continue;
											}
											$duplicator = $in_time . '' . $item['id'];
											$start_time = 0;
											$end_time = 0;
											foreach ($shift_options as $shift) {
												$opp = explode(" ", $shift);
												if (strtolower(date("D", $in_time)) == strtolower(days_mini($opp[0]))) {
													$start_time = hours($opp[1], 1);
													$end_time = hours($opp[2], 1);
													break;
												}
											}

											if ($start_time == 0 || strlen($start_time) < 2) {
												//days not available on shift, skip
												continue;
											} else {
												$date_simple = date("Y-m-d", $in_time);
												$late = $in_time - (strtotime($date_simple . " " . $start_time));
												$late = $late > 0 ? $late : 0;
												$lm += round($late / 60);
												$ci++;
												$p++;
												$leave_time = $tttt[1];
												if ($leave_time != 0) {
													$date_simple1 = date("Y-m-d", $leave_time);
													$early = (strtotime($date_simple1 . " " . $end_time)) - $leave_time;
													$early = $early > 0 ? $early : 0;
													$em += round($early / 60);
													$co++;
												}
											}
										}
									}
									$ab = ($total_days - $p-$lv);
									$rt = $total_days == 0 ? 0 : round(($p+$lv) / $total_days * 100);
									echo "<td>" . $item['fname'] . " " . $item['lname'] . "</td>";
									echo "<td class='td_date'>" . $item['id'] . "</td>";
									echo "<td class='td_date'>" . $total_days . "</td>";
									echo "<td class='td_date'>" . $lv . "</td>";
									echo "<td class='td_date'>" . $p . "</td>";
									echo "<td class='td_date'>" . $ab . "</td>";
									echo "<td class='td_date'>" . number_format($lm) . "</td>";
									echo "<td class='td_date'>" . number_format($em) . "</td>";
									echo "<td class='td_date'>" . $ci . "</td>";
									echo "<td class='td_date'>" . $co . "</td>";
									echo "<td class='td_date'>$rt%</td>";
									?>
								</tr>
								<?php
								$a1++;
							}
							?>
							</tbody>
							<!--Table body-->
						</table>
						<span><strong> <?= lang("app.legend"); ?> : </strong>C/IN # = <?= lang("app.lcin"); ?> , C/OUT # = <?= lang("app.lcout"); ?></span>
					</div>
				</div>
				<!--Table-->
				<div class="col-md-12 col-sm-12 " style="position: absolute;bottom: 10px;right: 10px">
					<footer class="pull-right" style="color: darkgrey"> <?= lang("app.generatedbySomanet"); ?></footer>
				</div>
			</div>
		</div>
	</div>
	<?php
}
