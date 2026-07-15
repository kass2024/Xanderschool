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
		max-width: 600px;

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

	th {
		text-transform: uppercase;
	}

	.right_border {
		border-right: 2px solid #000;
	}

	.date-header {
		float: left;
		width: 100%;
		border-top: 1px solid #555555;
		margin-top: 15px
	}
	.date-header > h5{
		margin: 10px 0;
		font-size: 13pt;
		text-align: left;
	}
	#printable,.col-md-12,.col-sm-12{
		width: 100%;
	}
</style>
<?php
if ($show_header) {
	?>
	<div class="col-12">
		<form id="frm_report" method="get" target="_blank"
			  action="<?= base_url('student_details_daily_report_data/true'); ?>">
			<div class="col-md-3 pull-left">
				<label><?= lang("app.sClass"); ?> :</label>
				<select class="select2 form-control" id="select_class" name="class">
					<option disabled selected><?= lang("app.selectClass"); ?> </option>
					<?php
					foreach ($classes as $class):
						echo "<option value='{$class['id']}'>{$class['level_name']} {$class['code']} {$class['title']}</option>";
					endforeach;
					?>
				</select>
			</div>

			<div class="col-md-3 pull-left">
				<label><?= lang("app.startDate"); ?> :</label>
				<input type="date" placeholder="Start date" class="form-control" id="date1" name="date1">
			</div>
			<div class="col-md-3 pull-left">
				<label><?= lang("app.endDate"); ?>:</label>
				<input type="date" placeholder="End date" class="form-control" id="date2" name="date2">
			</div>
			<div class="col-md-3 pull-left" style="margin-top: 30px;">
				<button class="btn btn-secondary" id="btn_generate"><?= lang("app.generate"); ?> </button>
				<button class="btn btn-primary" type="submit"><i class="fa fa-file-pdf"></i><?= lang("app.export"); ?>
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
				if ($("#select_class").val() == null) {
					toastada.warning("<?= lang("app.classisRequired"); ?>");
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
				$("#report_content").load("<?=base_url('student_details_daily_report_data');?>", $("#frm_report").serialize(), function () {
					$("#btn_generate").text("<?= lang("app.generate");?>").prop("disabled", false);
				})
			});
		});
	</script>
	<?php
} else {
	if (count($dates) == 0) {
		echo "<h4 style='width: 100%;float: left;text-align: center;margin-top: 15px'>" . lang("app.noDailyAttendance") . " </h4>";
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
						<span><b><?= lang("app.sClass"); ?> : </b><?= $classe; ?></span><br>
						<span><b><?= lang("app.from"); ?>  </b> : <?= $date1; ?></span><br>
						<span><b><?= lang("app.to"); ?>  </b> : <?= $date2; ?></span><br>
					</div>
					<div class="pull-right" style="margin-top: 10px;margin-right: 10px">
						<span><b><?= lang("app.printedOn"); ?>  </b> : <?= date("Y-m-d H:i"); ?></span><br>
					</div>
				</div>

				<?php
				$a1 = 1;
				$last_date = "";
				foreach ($dates as $item) {
					if ($last_date != $item['datee']) {
						$last_date = $item['datee'];
						if ($a1 > 1) {
							echo "</tbody></table>";
						}
						$a1 = 1;
						?>
						<div class="date-header">
							<h5>Date: <?= $last_date; ?></h5>
						</div>
						<table class="tablepage" border="1">
						<!--Table head-->
						<thead>
						<tr>
							<th class="right_border" style="min-width: 40px"><strong># </strong></th>
							<th class="right_border" style="min-width: 80px">
								<strong><?= lang("app.student"); ?> </strong></th>
							<th colspan="9" class="right_border"><strong><?= lang("app.observation"); ?> </strong>
							</th>
						</tr>
						</thead>
						<tbody id="disciplineTable">
						<?php
					}
					$stMdl = new \App\Models\StudentModel();
					$records = $stMdl->select("fname,lname,coalesce(d.datee,0) as observation")
							->join("daily_attendance d", "students.id=d.student_id AND d.datee='{$item['datee']}'", "LEFT")
							->join("class_records cr", "cr.student=students.id")
							->where("cr.class", $class_id)
							->where("cr.year", $academic_year)
							->where("school_id", $_SESSION["soma_school_id"])
							->orderBy("fname", "ASC")
							->get()->getResultArray();
					foreach ($records as $record) {
						$observation = $record['observation'] == "0" ? "<span style='color:red'>A</span>" : "P";
						?>
						<tr>
							<td class="right_border"><?= $a1; ?></td>
							<td class="right_border"
								style="text-align: left"><?= $record['fname'] . ' ' . $record['lname']; ?></td>
							<td class="right_border"><?= $observation; ?></td>
						</tr>
						<?php
						$a1++;
					}
					?>
					</tbody>
					<!--Table body-->
					</table>
					<!--Table-->
					<?php
				}
				?>
				<div class="col-md-12 col-sm-12 ">
					<footer class="pull-right"
							style="color: darkgrey"><?= lang("app.generatedbySomanet"); ?></footer>
				</div>
			</div>
		</div>
	</div>
	<?php
}
