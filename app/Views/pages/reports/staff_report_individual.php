<style>
	.tablepage {
		width: 100%;

	}
	@media (max-width: 768px) {
		.staff-summary {
			width: 100% !important;
		}
		.staff-records {
			width: 100% !important;
			margin-top: 20px;
		}
		.staff-records table td{
			line-height: 18px !important;
		}
	}

	.td_date {
		text-align: center;
		line-height: 0.7;
		padding: 5px 0;
	}
	.single-page {
		page-break-inside: avoid !important;
		page-break-after: avoid !important;
		page-break-before: avoid !important;
		width: 100% !important;
		height: 100% !important;
		/*max-height: 100%;*/
		margin-top: 0 !important;
		border-bottom: 1px solid #cdcdcd;
		display: inline-block;
		padding-bottom: 30px;
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
			  action="<?= base_url('staff_individual_report_data/true'); ?>">
			<div class="col-md-3 pull-left">
				<label><?= lang("app.selectStaff"); ?>:</label>
				<select class="select2 form-control" id="select_staff" name="staff">
					<option disabled selected><?= lang("app.selectStaff"); ?></option>
					<option value="0"><?= lang("app.allStaffs"); ?></option>
					<?php
					foreach ($staffs as $staff):
						echo "<option value='{$staff['id']}'>{$staff['id']}: {$staff['name']}</option>";
					endforeach;
					?>
				</select>
			</div>
			<div class="col-md-3 pull-left">
				<label><?= lang("app.startDate"); ?>:</label>
				<input type="date" placeholder="Start date" class="form-control" id="date1" name="date1">
			</div>
			<div class="col-md-3 pull-left">
				<label><?= lang("app.endDate"); ?>:</label>
				<input type="date" placeholder="End date" class="form-control" id="date2" name="date2">
			</div>
			<div class="col-md-3 pull-left" style="margin-top: 30px;">
				<button class="btn btn-secondary" id="btn_generate"><?= lang("app.generate"); ?></button>
				<button class="btn btn-primary" type="submit"><i class="fa fa-file-pdf"></i><?= lang("app.export"); ?> </button>
			</div>
		</form>

	</div>
	<div id="report_content">

	</div>
	<script>
		$(function () {
			$("#btn_generate").on("click", function (e) {
				e.preventDefault();
				if ($("#select_staff").val() == null) {
					toastada.warning("<?= lang("app.pleaseSelectStaff"); ?>");
					return;
				}
				if ($("#date1").val() == "") {
					toastada.warning("<?= lang("app.strtDateErr"); ?>");
					return;
				}
				if ($("#date2").val() == "") {
					toastada.warning("<?= lang("app.endDateErr"); ?>");
					return;
				}
				$("#btn_generate").text("<?= lang("app.pleaseWait"); ?>").prop("disabled", true);
				$("#report_content").load("<?=base_url('staff_individual_report_data');?>", $("#frm_report").serialize(), function () {
					$("#btn_generate").text("<?= lang("app.generate"); ?>").prop("disabled", false);
				})
			});
		});
	</script>
	<?php
} else {
//	$total_days = get_total_days($date1, $date2);
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
							<span><b><?= lang("app.mail"); ?> </b> : <?= $school_email; ?></span><br>
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
				<h4 style="text-decoration: underline;width: 100%;float: left;text-align: center;"><?= lang("app.individualReportAtt"); ?> </h4>
				<?php
				$staffs = $staffs ?? [];
				$a = 0;
				foreach ($staffs as $staff):
					$a++;
					$attMdl = new \App\Models\AttendanceRecordsModel();
					$date1_unix = strtotime($date1);
					$date2_unix = strtotime($date2) + 86399;
					$records = $attMdl->select("time_in,coalesce(time_out,0) as time_out")
							->where("user_id", $staff['id'])
							->where("user_type", 1)
							->where("time_in>='$date1_unix' and time_in<='$date2_unix'")
							->groupBy("user_id")
							->groupBy("date_format(from_unixtime(time_in),'%d-%m-%Y')")
							->orderBy("time_in", "ASC")
							->get()->getResultArray();
//					if (count($records) == 0) {
//						echo "<h4 style='width: 100%;float: left;text-align: center;margin-top: 15px'>No attendance records found</h4>";
//						continue;
//					}
					?>
					<div style="width: 100%;<?=$a==1?'height: 70% !important':'';?>" class="single-page">
						<div style="float:left;width: 28%" class="staff-summary">
							<div class="boxed2">
								<!--							<h4>--><?//= lang("app.rReportInfo");
								?><!--</h4>-->
								<h2><?= $staff['fname'] . ' ' . $staff['lname']; ?></span></h2>
								<span><b><?= lang("app.mFrom"); ?>: </b><?= $date1; ?></span><br>
								<span><b><?= lang("app.mTo"); ?>: </b><?= $date2; ?></span><br>
								<span><b><?= lang("app.post"); ?>: </b> <?= $staff['post_title']; ?></span><br>
								<span><b><?= lang("app.email"); ?>: </b> <?= $staff['email']; ?></span><br>
								<span><b><?= lang("app.phone"); ?>: </b> <?= $staff['phone']; ?></span><br>
								<span><b><?= lang("app.printedOn"); ?></b> : <?= date("Y-m-d H:i"); ?></span><br>
							</div>
							<div class="boxed2" style="margin-top: 20px">
								<?php
								$present = count($records);
								$shift_options = json_decode($staff['options'], true);
								$created_at = date("Y-m-d", strtotime($staff['created_at']));
								if ($date1 < $created_at) {
									$date1 = $created_at;
								}
								$leave_days = 0;
								$totalLateMin = 0;
								$daysOff = $daysOff ?? [];
								$total_days = get_total_days($date1, $date2, $shift_options, $daysOff);
								if ($staff['leave_start'] > strtotime($date2) || $staff['leave_end'] < strtotime($date1) || ($staff['leave_start'] > time())) {
									//current range not match with leave, or day till to come
									$leave_days = 0;
								} else {
									//calculate leave days
									$lv_start = $staff['leave_start'] < strtotime($date1) ? $date1 : date("Y-m-d", $staff['leave_start']);
									$lv_end = $staff['leave_end'] > strtotime($date2) ? $date2 : date("Y-m-d", $staff['leave_end']);
									$lv_end = strtotime($lv_end) > time() ? date("Y-m-d") : $lv_end;//check if leave end is ahead and block it to today
									$leave_days = get_total_days2($lv_start, $lv_end);
								}
								?>

								<?php
								$html = "";
								$a1 = 1;
								$color = 'green';
								$latestDate = count($records) == 0 ? (strtotime($date1) - 86400) : 0;
								$endDate = strtotime($date2) > strtotime(date("Y-m-d")) ? date("Y-m-d") : $date2;
								$endDate = strtotime($endDate);
								foreach ($records as $item) {
									$dateIn = strtotime(date('Y-m-d', $item['time_in']));
									if ($a1 == 1) {
										$ddd = strtotime($date1);
									} else {
										$ddd = $latestDate + 86400;
									}
									if ($ddd < $dateIn) {
										if ($reportType != 1) {
											$html .= generateAbsentDays($ddd, $dateIn, $shift_options, $daysOff, $a1);
										}
									}
									$latestDate = $dateIn;
									$a2 = 1;
									$in_time = $item["time_in"];
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
									$date = date("F d, Y", $in_time);
									$leave_time = $item["time_out"];
									$date_simple = date("Y-m-d", $in_time);
									$flag = "";
									$style = "";
									$late = 0;
									if (($in_time - 59) > (strtotime($date_simple . " " . $start_time))) {
										$flag = "L";
										$late = $in_time - (strtotime($date_simple . " " . $start_time));
									}
									if ($start_time == 0) {
										//days not available on shift, skip
										$style = "text-decoration: line-through;";
										$present--;
										$flag = "OFF";
									} else {
										$lm = round($late / 60);
										$totalLateMin += $lm;
										$color = $lm > 5 ? 'red' : ($lm == 0 ? 'green' : 'orange');
									}
									if ($reportType != 2) {
										$html .= "<tr style='color: $color'>";
										$html .= "<td style='text-align: right'>$a1</td>";
										$html .= "<td class='td_date' style='$style'>$date</td>";
										$html .= "<td class='td_date' style='$style'>" . date("H:i", $in_time) . "</td>";
									}
									$early = 0;
									if ($leave_time != 0) {
										if ($leave_time < (strtotime($date_simple . " " . $end_time))) {
											$flag .= (strlen($flag) == 0 ? '' : ' | ') . "E";
											$early = (strtotime($date_simple . " " . $end_time)) - $leave_time;
										}
										$early = round($early / 60);
										if ($reportType != 2) {
											$html .= "<td class='td_date' style='$style'>" . date("H:i", $leave_time) . "</td>";
										}
									} else {
										$flag .= (strlen($flag) == 0 ? '' : ' | ') . "NCO";
										if ($reportType != 2) {
											$html .= "<td class='td_date' style='$style'>No Checkout</td>";
										}
									}
									if ($reportType != 2) {
										$html .= "<td class='td_date' style='$style'>$lm</td>";
										$html .= "<td class='td_date' style='$style'>$early</td>";
										$html .= "<td class='td_date' style='$style'>$flag</td>";
										$html .= "</tr>";
									}
									?>
									<?php
									$a1++;
								}
								if ($latestDate < $endDate) {
									//check absent days after last clock in
									if ($reportType != 1) {
										$html .= generateAbsentDays($latestDate, $endDate, $shift_options, $daysOff, $a1, 1);
									}
								}
								?>
								<h4><?= lang("app.rSummary"); ?></h4>
								<span><b><?= lang("app.totalDays"); ?>: </b><?= $total_days; ?></span><br>
								<span><b><?= lang("app.workedDays"); ?>: </b><?= $present; ?></span><br>
								<span style="color: orangered"><b><?= lang("app.absentDays"); ?>: </b> <?= $total_days - $present - $leave_days; ?></span><br>
								<span><b><?= lang("app.leaveDays"); ?>: </b><?= $leave_days; ?></span><br>
								<span style="color: orangered"><b><?= lang("app.lateMin"); ?>: </b><?= $totalLateMin; ?></span><br>
								<label
										style="font-size: 30pt;font-weight: bold;margin-top: 20px;text-align: center;display: block;"><?= round(($present + $leave_days) / $total_days * 100); ?>
									%</label>
							</div>
						</div>
						<div style="width: 70%;float:left;margin-left: 1%;" class="staff-records">
							<table class="tablepage" border="1">
								<!--Table head-->

								<tbody id="disciplineTable">
								<tr>
									<td style="text-align: right"><strong>#</strong></td>
									<td><strong><?= lang("app.date"); ?></strong></td>
									<td><strong><?= lang("app.checkIn"); ?></strong></td>
									<td><strong><?= lang("app.checkOut"); ?></strong></td>
									<td><strong><?= lang("app.lateMin"); ?></strong></td>
									<td><strong><?= lang("app.rEarlyMin"); ?></strong></td>
									<td><strong><?= lang("app.rFLAG"); ?></strong></td>
								</tr>
								<?= $html; ?>
								</tbody>
								<!--Table body-->
							</table>
							<span><strong><?= lang("app.LEGEND"); ?>: </strong>L= <?= lang("app.arriveLate"); ?>, E= <?= lang("app.leaveEarly"); ?>, NCO= No checkout</span>
						</div>
					</div>
					<!--Table-->
				<?php
				endforeach;
				?>

				<!--Table-->
				<div class="col-md-12 col-sm-12 " style="position: absolute;bottom: 10px;right: 10px">
					<footer class="pull-right" style="color: darkgrey"><?= lang("app.generatedbySomanet"); ?> </footer>
				</div>
			</div>
		</div>
	</div>
	<?php
}
