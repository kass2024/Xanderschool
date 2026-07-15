<style>
	.tablepage {
		width: 100%;

	}

	.colscenter {
		text-align: center;
		font-weight: bold;
	}

	.solid {
		border-style: solid;
		border-width: 2px;
	}
</style>
<style>
	.tablepage {
		width: 100%;
		font-size: 13px;
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

	.printable {
		page-break-inside: avoid !important;
		page-break-after: avoid !important;
		page-break-before: avoid !important;
		width: 100%;
		max-height: 100%;
		margin-top: 0 !important;
	}

	.printable .solid {
		width: 100%;
		max-height: 100%;
	}

	.no-printable {
		margin: 15px !important;
		width: calc(100% - 30px) !important;
	}
</style>
<?php
$i = 1;
$student_reg=isset($_GET['student'])?$_GET['student']:false;
if (count($students) == 0) echo "<h1 style=''>" . lang("app.noStudentFound") . "</h1>";
$result = new \App\Controllers\Home();
//print_r($students); die();
$courseRows = 0;
foreach ($students as $student) {
	$courseRows = 0;
	if ($student['id'] == $student_reg || $student_reg === false) {
		?>
		<div style="margin-top:15px;width: 100%;position: relative" class="<?= $pdf ? 'printable' : 'no-printable'; ?>">
			<div class="col-md-12 col-sm-12 pull-left solid" style="height: 100%;position: relative">
				<div style="background:white;padding: 10px;overflow: auto">
					<div class="pull-left" style="width:46.5%;margin-top: 10px">
						<span><b><?= lang("app.republic"); ?> </b></span><br>
						<span><b><?= lang("app.ministry"); ?></b></span><br>
						<span><?= $school_name; ?></span><br>
						<img src="<?= base_url('assets/images/logo/' . $school_logo); ?>" style="width: 110px;" alt=" "><br>
						<span><b><?= lang("app.mail"); ?></b> : <?= $school_email; ?></span><br>
						<span><b><?= lang("app.phone"); ?> </b>  : <?= $school_phone; ?></span><br>
					</div>
					<?php
					if (@getimagesize(base_url('assets/images/profile/' . $student['photo']))) {
						?>
						<div class="pull-left"
							 style="margin-top: 30px;margin-bottom:10px;width: 120px;border:1px solid #555;min-height: 120px">
							<div>

								<img src="<?= base_url('assets/images/profile/' . $student['photo']); ?>"
									 style="width: 100%" alt=" ">
							</div>
						</div>
						<?php
					}
					?>
					<div class="pull-left"
						 style="margin-top: 10px;width: auto;min-width: 240px;float: right">
						<span><b><?= lang("app.academicYear"); ?></b> :<?= $academic_year_title; ?></span><br>
						<span><b><?= lang("app.firstName"); ?></b>  : <?= $student['fname']; ?> </span><br>
						<span><b><?= lang("app.lastName"); ?></b>   : <?= $student['lname']; ?></span><br>
						<span><b><?= lang("app.sClass"); ?> </b>   :  <?= $student['level_name'] ?> <?= $student['title'] ?> <?= $student['code'] ?></span><br>
						<span><b><?= lang("app.regNo"); ?></b>   : <?= $student['regno']; ?></span><br>
					</div>

					<table class="tablepage" border="1">
						<tbody id="disciplineTable">
						<tr>
							<td class="colscenter" colspan="8"><?= lang("app.AssementReporrt"); ?> </td>

						</tr>
						<tr>
							<td colspan="2"><?= lang("app.trades"); ?> </td>
							<td class="colscenter" colspan="2"><?= $student['fac_title']; ?></td>
							<td colspan="2"><?= lang("app.qualTitle"); ?> </td>
							<td class="colscenter" colspan="2"><?= $student['department_name']; ?> </td>
						</tr>
						<tr>
							<td colspan="2"><?= lang("app.qualCode"); ?></td>
							<td class="colscenter" colspan="2"><?= $student['code']; ?> </td>
							<td colspan="2"><?= lang("app.rtqf"); ?> </td>
							<td class="colscenter" colspan="2"><?= $student['level_name']; ?> </td>
						</tr>

						<tr>
							<td class="colscenter" colspan="8">
								<?= lang("app.".termToStr($term)); ?> <?= lang("app.assStatus"); ?> </td>

						</tr>
						<tr>
							<td class="colscenter"><?= lang("app.modCode"); ?> </td>
							<td class="colscenter"><?= lang("app.cmopTitle"); ?> </td>
							<td class="colscenter"><?= lang("app.creditHour"); ?> </td>
							<td class="colscenter"><?= lang("app.fAssessITR"); ?> </td>
							<td class="colscenter"><?= lang("app.iAssessITR"); ?> </td>
							<td class="colscenter"><?= lang("app.avgITR"); ?> </td>
							<td class="colscenter"><?= lang("app.reAssess"); ?> </td>
							<td class="colscenter"><?= lang("app.obser"); ?> </td>
						</tr>
						<?php
						//fetch courses
						$category = "";
						$cat = 0;
						$exam = 0;
						$totalCatColumn = 0;
						$totalExamColumn = 0;
						$totals = 0;
						$credit = 0;
						$markCounter = 0;
						$total = 0;
						$max_total = 0;
						$the_max_total = 0;
						foreach ($student['courses'] as $core) {
						$courseRows++;
						if ($category != $core['category']) {
							echo "<tr>
					<td colspan=\"10\" style=\"background-color: #7c7c7c\"><b>" . strtoupper($core['category']) . "</b></td>
				</tr>";
							$category = $core['category'];
						}
						?>
						<tr>
							<td><?= $core['code']; ?></td>
							<td><?= $core['title']; ?></td>
							<td><?= $core['credit']; ?></td>
							<?php
							$datas = $core['result'];
							$row_exam = $datas['exam_marks'] * 100 / $core['marks'];
							$row_exams = $datas['exam_marks'];
							$row_cat = $datas['marks'] * 100 / $core['marks'];
							$row_cats = $datas['marks'];
							$row_total = $row_exams==null?$row_cats:($row_exams + $row_cats) / 2;
							$row_totals = $row_exams==null?$row_cat:($row_exam + $row_cat) / 2;
							$reAssessment = \App\Controllers\Home::reAssessment($core['id'],$student['id'],$term,$year);
							$observationMarks = $reAssessment==null?$row_totals:$reAssessment['marks'];
							//						$row_total=($datas['marks'] + $datas['exam_marks']);
							//<td>" . (number_format($row_total, 1)==0?'-': number_format($row_total, 1)). "</td>
							echo "<td>" . (number_format($row_cats, 1)==0?'-': number_format($row_cats, 1)). "</td>
								      <td>" . (number_format($row_exams, 1)==0?'-':number_format($row_exams, 1)) . "</td>
									  <td>" . (number_format($row_total, 1)==0?'-': number_format($row_total, 1)). "</td>
									  <td>" . ($reAssessment==null?'':number_format($reAssessment['marks'],1)). "</td>
									  <td>" . ($observationMarks>=70?'C':'NYC') . "</td>
									 ";
							//						$total += ($datas['marks'] + $datas['exam_marks']);
							$max_total += $core['marks'];
							if ($row_exams!=null){
								$the_max_total += $core['marks'];
							}
							$totalCatColumn += $datas['marks'];
							$totalExamColumn += $datas['exam_marks'];
							$totals += $row_total;
							$credit += $core['credit'];
							$markCounter++;
							} ?>
						</tr>

						<tr>
							<td colspan="8" style="background-color: #7c7c7c">&nbsp;</td>
						</tr>
						<tr>
							<td colspan="2"><b><?= lang("app.total"); ?></b></td>
							<td class="colscenter"><?= $credit; ?></td>
							<td class="colscenter"><?= $max_total!=0?number_format(($totalCatColumn * 100 / $max_total), 1):'N/A'; ?> %</td>
							<td class="colscenter"><?= $the_max_total!=0?number_format(($totalExamColumn * 100 / $the_max_total), 1):'N/A'; ?> %</td>
							<td class="colscenter"><?= $max_total!=0?number_format((($totals) * 100 / ($max_total)), 1):'N/A'; ?> %</td>
							<td class="colscenter">&nbsp;</td>
							<td class="colscenter"><?= $i; ?> out of <?= count($students); ?></td>
						</tr>
						<tr>
							<td colspan="2"><b><?= lang("app.Behaviour"); ?></b></td>
							<td class="colscenter"><?= $discipline_max; ?></td>
							<td class="colscenter"><?= $discipline_max - extractDisciplineMarks($student['displine_marks'],$term); ?></td>
							<td class="colscenter">&nbsp;</td>
							<td class="colscenter">&nbsp;</td>
							<td class="colscenter">&nbsp;</td>
							<td class="colscenter">&nbsp;</td>
						</tr>
						<tr>
							<td colspan="8"><b><?= lang("app.feedBac"); ?> </b></td>
						</tr>
						<tr>
							<td colspan="8"><b><?= lang("app.teacherComment"); ?> </b></td>
						</tr>
						<tr>
							<td colspan="2"><b><?= lang("app.remark"); ?> :</b>
								<p><?= lang("app.remarkIntro"); ?></p></td>
							<td colspan="6">
								<p style="margin-top: 2px;"><?= lang("app.done"); ?>
									<b> <?= lang("app.on"); ?> <?= date('M-d-Y'); ?></b></p>
								<p>
									<?= lang("app.SchoolDirector"); ?> : <b></b><br>
									<b><?= $head_master; ?></b><br>
									<?= lang("app.SignatureStamp"); ?><br>
									<?php
									if (strlen($headmaster_signature)>5){
										?>
										<img src="<?= base_url('assets/images/signatures/' . $headmaster_signature); ?>" style="height: 70px;"
											 alt="Headmaster signature">
										<?php
									}else{
										echo "----------------------------------------";
									}
									?>
								</p>
							</td>
						</tr>
						</tbody>
						<!--Table body-->
					</table>
					<!--Table-->
					<footer style="color: darkgrey;position: absolute;bottom: 10px;right: 10px">printed by Somanet</footer>
				</div>
			</div>
		</div>
		<?php
	}
	$i++;
}
if ($courseRows > 20) {
	; ?>
	<style>
		th, td {
			font-size: 8pt !important;
		}
	</style>
	<?php
}
?>
