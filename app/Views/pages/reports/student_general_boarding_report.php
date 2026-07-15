<style>
	/* ===============================
	   Main Table
	=================================*/
	.tablepage {
		width: 100%;
		border-collapse: collapse;
		font-size: 8pt;
		background: #fff;
		table-layout: fixed;
		page-break-inside: auto;
	}

	th, td {
		padding: 3px 4px;
		border: 1px solid #ccc;
		text-align: center;
		vertical-align: middle;
		word-wrap: break-word;
		white-space: normal;
	}

	.tablepage thead th {
		background: #f4f4f4;
		font-weight: bold;
		text-transform: uppercase;
		font-size: 7pt;
		white-space: nowrap;
		line-height: 1.2;
		padding: 4px 3px;
		overflow: hidden;
	}

	td { font-size: 7.3pt; min-height: 18px; }

	/* Column widths */
	th:first-child, td:first-child { width: 20px; }
	th:nth-child(2), td:nth-child(2) { width: 120px; text-align: left; }
	th:nth-child(3), td:nth-child(3) { width: 40px; }
	.tablepage th.total-col, .tablepage td.total-col { width: 40px; }
	th.day-col, td.day-col { width: 22px; max-width: 22px; }

	/* ✅ Header only once in PDF */
	.tablepage thead { display: table-row-group !important; }
	.tablepage tfoot { display: table-row-group !important; }

	/* ✅ Prevent splitting rows across PDF pages */
	.tablepage tr,
	.tablepage td,
	.tablepage th,
	.tablepage tbody {
		page-break-inside: avoid !important;
		break-inside: avoid !important;
		page-break-before: auto;
		page-break-after: auto;
	}

	/* ✅ Force row to move entirely to next page if it doesn’t fit */
	.tablepage tr {
		display: table-row;
		page-break-inside: avoid !important;
		break-inside: avoid !important;
	}

	@media screen {
		.tablepage thead th {
			position: sticky;
			top: 0;
			background: #f9f9f9;
			z-index: 2;
		}
	}

	/* Weekend column styling */
	.weekend { background-color: #faf2f2; }

	/* Total column styling */
	.total-col { background: #f0f7f9; font-weight: bold; }

	/* ===============================
	   Badge Styling
	=================================*/
	.badge {
		display: inline-block;
		padding: 1px 3px;
		border-radius: 6px;
		color: #fff;
		font-size: 6pt;
		font-weight: bold;
		min-width: 14px;
		line-height: 1.2;
		margin-bottom: 1px;
	}
	.badge-count { background: #28a745; }
	.badge-absent { background: #dc3545; }

	/* Printable container */
	#printable { width: 100%; margin-top: 20px; }

	/* ===============================
	   Header Styling
	=================================*/
	.report-header {
		width: 100%;
		display: table;
		table-layout: fixed;
		margin-bottom: 10px;
	}
	.report-left {
		display: table-cell;
		width: 70%;
		vertical-align: top;
	}
	.report-right {
		display: table-cell;
		width: 30%;
		text-align: right;
		vertical-align: top;
	}
	.report-title {
		text-align: center;
		text-decoration: underline;
		font-weight: bold;
		margin: 10px 0;
	}
</style>


<?php if ($show_header) { ?>
	<!-- Filters -->
	<div class="col-12">
		<form id="frm_report" method="get" target="_blank"
			  action="<?= base_url('student_details_boarding_report_data/true'); ?>" 
			  class="row g-3 align-items-end">

			<div class="col-md-3">
				<label><?= lang("app.sClass"); ?> :</label>
				<select class="form-control select2" id="select_class" name="class" required>
					<option disabled selected><?= lang("app.selectClass"); ?></option>
					<?php foreach ($classes as $class): ?>
						<option value="<?= $class['id']; ?>">
							<?= $class['level_name'].' '.$class['code'].' '.$class['title']; ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="col-md-3">
				<label><?= lang("app.gender"); ?> :</label>
				<select class="form-control select2" id="gender_filter" name="gender_filter">
					<option value=""><?= lang("app.all"); ?></option>
					<option value="M"><?= lang("app.male"); ?></option>
					<option value="F"><?= lang("app.female"); ?></option>
				</select>
			</div>

			<div class="col-md-3">
				<label><?= lang("app.month"); ?> :</label>
				<select class="form-control select2" id="month" name="month" required>
					<?php for ($m=1; $m<=12; $m++): ?>
						<option value="<?= $m; ?>" <?= $m==date("n")?"selected":""; ?>>
							<?= date("F", mktime(0,0,0,$m,1)); ?>
						</option>
					<?php endfor; ?>
				</select>
			</div>

			<div class="col-md-3 d-flex gap-2">
				<button class="btn btn-secondary w-50" id="btn_generate"><?= lang("app.generate"); ?></button>
				<button class="btn btn-primary w-50" type="submit">
					<i class="fa fa-file-pdf"></i> <?= lang("app.export"); ?>
				</button>
			</div>
		</form>
	</div>

	<div class="clearfix"></div>
	<div id="report_content" style="margin-top:20px;"></div>

	<script>
		$(function(){
			$('#month, #select_class, #gender_filter').select2({
				width: '100%',
				minimumResultsForSearch: Infinity
			});

			$("#btn_generate").on("click", function(e){
				e.preventDefault();
				if(!$("#select_class").val()){ toastada.warning("<?= lang('app.classisRequired'); ?>"); return; }
				if(!$("#month").val()){ toastada.warning("<?= lang('app.monthRequired'); ?>"); return; }
				$("#btn_generate").text("<?= lang('app.pleaseWait'); ?>").prop("disabled", true);
				$("#report_content").load("<?= base_url('student_details_boarding_report_data'); ?>",
					$("#frm_report").serialize(),
					function(){ $("#btn_generate").text("<?= lang('app.generate'); ?>").prop("disabled", false); }
				);
			});
		});
	</script>
<?php
} else {
	$gender_filter = $_GET['gender_filter'] ?? '';
	$month = $_GET['month'] ?? date("n");
	$year  = date("Y");
	$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

	$stMdl = new \App\Models\StudentModel();
	$stMdl->select("students.id, fname, lname, sex")
		->join("class_records cr", "cr.student=students.id")
		->where("cr.class", $class_id)
		->where("cr.year", $academic_year)
		->where("school_id", $_SESSION["soma_school_id"])
		->where("studying_mode", 0);
	if ($gender_filter != '') $stMdl->where("students.sex", $gender_filter);
	$students = $stMdl->orderBy("fname","ASC")->findAll();

	$studentIds = array_column($students, 'id');

	$db = \Config\Database::connect();
	$attendanceData = [];
	if (!empty($studentIds)) {
		$attendanceData = $db->table("boarding_attendance")
			->select("student_id, datee, COUNT(id) as total_sessions")
			->whereIn("student_id", $studentIds)
			->where("MONTH(datee)", $month)
			->where("YEAR(datee)", $year)
			->groupBy("student_id, datee")
			->get()->getResultArray();
	}

	$attendanceMap = [];
	$classAttendanceDates = [];
	foreach($attendanceData as $row){
		$attendanceMap[$row['student_id']][$row['datee']] = $row['total_sessions'];
		$classAttendanceDates[$row['datee']] = true;
	}
	?>

	<div id="printable">
		<div class="col-md-12 col-sm-12">
			<div style="background:white;padding:10px;overflow:auto;">

				<!-- Report Header -->
				<div class="report-header">
					<div class="report-left">
						<span><b><?= lang("app.republic"); ?></b></span><br>
						<span><b><?= lang("app.ministry"); ?></b></span><br>
						<span><strong><?= $school_name; ?></strong></span><br>
						<span><b><?= lang("app.mail"); ?></b> : <?= $school_email; ?></span><br>
						<span><b><?= lang("app.phone"); ?></b> : <?= $school_phone; ?></span>
					</div>
					<div class="report-right">
						<img src="<?= base_url('assets/images/logo/' . $school_logo); ?>" style="width:110px;">
					</div>
				</div>

				<div class="report-title">
					<?= lang("app.boardingGeneralAttendance"); ?>
				</div>

				<table class="tablepage">
					<thead>
						<tr>
							<th>#</th>
							<th><?= lang("app.student"); ?></th>
							<th><?= lang("app.gender"); ?></th>
							<?php for($d=1;$d<=$daysInMonth;$d++): 
								$dayName = date("D", strtotime("$year-$month-$d"));
								$classWeekend = ($dayName=="Sat"||$dayName=="Sun")?"weekend":""; ?>
								<th class="day-col <?= $classWeekend; ?>"><?= $d; ?></th>
							<?php endfor; ?>
							<th class="total-col"><?= lang("app.total"); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php $counter = 1; ?>
						<?php foreach($students as $stu): $total=0; ?>
							<tbody> <!-- wrap row in tbody for PDF engines -->
							<tr>
								<td><?= $counter++; ?></td>
								<td><?= $stu['fname']." ".$stu['lname']; ?></td>
								<td><?= $stu['sex']=='M'?lang("app.male"):lang("app.female"); ?></td>

								<?php for($d=1;$d<=$daysInMonth;$d++): 
									$dateStr = sprintf("%04d-%02d-%02d",$year,$month,$d);

									if(isset($attendanceMap[$stu['id']][$dateStr])){
										$count = $attendanceMap[$stu['id']][$dateStr];
										$total += $count;
										echo "<td class='day-col'>P <span class='badge badge-count'>{$count}</span></td>";
									}else{
										if(isset($classAttendanceDates[$dateStr])){
											echo "<td class='day-col'>A <span class='badge badge-absent'>0</span></td>";
										}else{
											echo "<td class='day-col'></td>";
										}
									}
								endfor; ?>

								<td class="total-col"><?= $total; ?></td>
							</tr>
							</tbody>
						<?php endforeach; ?>
					</tbody>
				</table>

				<div class="col-md-12 col-sm-12">
					<footer class="pull-right" style="color:darkgrey">
						<?= lang("app.generatedbySomanet"); ?>
					</footer>
				</div>

			</div>
		</div>
	</div>
<?php } ?>
