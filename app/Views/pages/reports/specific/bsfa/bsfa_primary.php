<style>
	.tablepage {
		width: 100%;

	}

	.colscenter {
		text-align: center;
		font-weight: bold;
	}

	.solid {
		border: 2px solid;
	}

	.solid2 {
		border: 1px solid;
	}

	.col-md-12 {
		width: 99%;
		float: left;
	}

	.col-md-6 {
		width: 50%;
		float: left;
	}

	.cols4 {
		width: 15%;
		float: left;
		padding: 5px;
		display: inline;
	}

	th, td {
		min-width: 30px;
		padding: 3px 5px;
		border: 1px solid #777777;
	}

	.report_footer {
		padding-right: 50px;
		padding-left: 50px;
		margin-top: -10px;
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

	.spacer {
		float: left;
		width: 100%;
		height: 20px;;
	}

	#printable {
		page-break-inside: avoid !important;
		page-break-after: avoid !important;
		page-break-before: avoid !important;
		max-height: 100%;
		margin-top: 0 !important;
	}

	#printable .solid {
		height: 99%;
	}

    table#report_table td{
        border-top: 0px solid #000000;
        padding: 0px;
        border: 0px solid #000000;
    }
	.fail{
		color:red;text-decoration: underline
	}
</style>
<?php
$i = 1;
$student_reg = isset($_GET['student']) ? $_GET['student'] : false;
if (count($students) == 0) echo "<h1 style=''>" . lang("app.noStudentFound") . "</h1>";
$result = new \App\Controllers\Home();
//print_r($students); die();
foreach ($students as $student) {
	if ($student['id'] == $student_reg || $student_reg === false) {
		?>
		<pagebreak style="page-break-before: always">
			<div style="margin-top: 15px;width: 100%;float:left;" id="printable">
				<div class="col-md-12 col-sm-12 pull-left " style="margin-bottom: 15px">
					<div style="background:white;padding: 10px;overflow: hidden;" class="solid">
						<div style="margin-top: 15px;width: 100%;float:left;">
							<div class="col-md-12 col-sm-12 pull-left" style="margin-bottom: 15px">
								<div style="background:white;padding: 10px;overflow: hidden;">
									<table id="report_table" class="table" border=0>
										<tr>
											<td style="width: 34%; border: 0px solid #000000;">
												<div style="margin: 10px 0;">
													<img src="<?= base_url('assets/images/logo/' . $school_logo); ?>" style="width: 110px;" alt="School Logo">
												</div>
											</td>
											<td colspan="2" style="border: 0px solid #000000;">
												<div style="font-family: helvetica; font-size: 20px; color: blue; border: 1px solid #000000; text-align: center;">
													<span style="font-size: 26px;"><?= ($school_name); ?>(<?= $school_acronym ?>)</span><br>
													<strong><?= ucwords($school_moto) ?></strong><br>
													<span><?= lang("app.phone"); ?> : 0788303713/<?= $school_phone; ?></span><br>
												</div>
											</td>
										</tr>
										<tr>
											<td colspan="2" style="text-align: right; padding-right: 15%; border: 0px solid #000000;">
												STUDENT'S REPORT
											</td>
											<td rowspan="4" style="width: 15%; text-align:center; border: 0px solid #000000;">
												<?php
												if (@getimagesize(base_url('assets/images/profile/' . $student['photo']))) {
													?>
													<div style="border:0px solid #555;max-height: 120px; max-width: 120px;">
														<img src="<?= base_url('assets/images/profile/' . $student['photo']); ?>"
																style="height: 120px;" alt="">
													</div>
													<?php
												}
												?>
											</td>
										</tr>
										<tr>
											<td colspan="2" style="border: 0px solid #000000;">
											<span><b> <?= lang("app.student"); ?> </b>  :  <?= $student['fname']; ?> &nbsp;  <?= $student['lname']; ?></span>
											</td>
										</tr>
										<tr>
											<td colspan="2" style="border: 0px solid #000000;">
											<span><b> <?= lang("app.sClass"); ?> </b>   : <?= $student['level_name'] ?> <?= $student['title'] ?> <?= $student['code'] ?></span>
											</td>
										</tr>
										<tr>
											<td colspan="2" style="border: 0px solid #000000;">
												<span><b> <?= lang("app.academicYear"); ?> </b> :<?= $academic_year_title; ?></span>
											</td>
										</tr>
									</table>
									<?php /* ?>
									<div class="pull-left" style="width: 45%">
										<span><?= strtoupper($school_name); ?></span><br>
										<div class="pull-left">
											<div style="margin: 10px 0;">
												<img src="<?= base_url('assets/images/logo/' . $school_logo); ?>"
													style="width: 110px;" alt="School Logo">
											</div>
										</div>
										<div class="spacer" style="height: auto">
											<span><b> <?= lang("app.mail"); ?> </b> : <?= $school_email; ?></span><br>
											<span><b> <?= lang("app.phone"); ?> </b>  : <?= $school_phone; ?></span><br>
										</div>
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
									<div class="pull-right" style="max-width: 45%">
										<div class="pull-right">
											<span><b> <?= lang("app.student"); ?> </b>  :  <?= $student['fname']; ?> &nbsp;  <?= $student['lname']; ?></span><br>
											<span><b> <?= lang("app.sClass"); ?> </b>   : <?= $student['level_name'] ?> <?= $student['title'] ?> <?= $student['code'] ?></span><br>
											<span><b> <?= lang("app.academicYear"); ?> </b> :<?= $academic_year_title; ?></span><br>
										</div>
									</div>
									<div style="padding: 5px;display: block;margin:0 0 10px 1%;text-align: center"
										class="solid2 col-md-12">
										<b> <?= lang("app.schoolReport"); ?> </b>
									</div>
									<?php */ ?>
									<table class="tablepage" border="1" style="margin: 10px;">
										<!--Table head-->
										<tbody id="disciplineTable">
										<tr>
											<td colspan="4" rowspan="2" width="40%"
												style="text-transform: uppercase"> <?= lang("app.subject"); ?> </td>
											<td colspan="3" class="colscenter"> <?= lang("app.maxim"); ?> </td>
											<td colspan="3"
												class="colscenter"> <?= lang("app." . termToStr($term)); ?> </td>
										</tr>
										<tr>
											<td class="colscenter"> <?= lang("app.test"); ?> </td>
											<td class="colscenter"> <?= lang("app.ex"); ?> </td>
											<td class="colscenter"> <?= lang("app.tot"); ?> </td>
											<td class="colscenter"> <?= lang("app.test"); ?> </td>
											<td class="colscenter"> <?= lang("app.ex"); ?> </td>
											<td class="colscenter"> <?= lang("app.tot"); ?> </td>
										</tr>
										<?php
										$category = "";
										$cat = 0;
										$exam = 0;
										$totalCatColumn = 0;
										$totalExamColumn = 0;

										foreach ($student['courses'] as $core) {
										//							if ($category != $core['category']) {
										//								echo "<tr>
										//					<td colspan=\"10\" style=\"background-color: #f5e1b9\"><b>" . strtoupper($core['category']) . "</b></td>
										//				</tr>";
										//								$category = $core['category'];
										//							}
										?>
										<tr>
											<td colspan="4"><?= $core['title']; ?></td>
											<td class="colscenter"><?= $core['marks']; ?></td>
											<td class="colscenter"><?= $core['marks']; ?></td>
											<td class="colscenter"><?= $core['marks'] * 2; ?></td>
											<?php
											$datas = $core['result'];
											$total = $datas['exam_marks'] + $datas['marks'];
											$catEchec = ($datas['marks']<($core['marks']/2) && !strlen($datas['marks'])==0)?'fail':'';
											$ExamEchec = ($datas['exam_marks']<($core['marks']/2) && !strlen($datas['exam_marks'])==0)?'fail':'';
											$total = $datas['exam_marks'] + $datas['marks'];
											$totalEchec = ($total<$core['marks'] && (!strlen($datas['exam_marks'])==0 || !strlen($datas['marks'])==0))?'fail':'';
											echo "<td class='$catEchec'>" . number_format($datas['marks'], 1) . "</td>
										<td class='$ExamEchec'>" . number_format($datas['exam_marks'], 1) . "</td>
										<td class='$totalEchec'>" . number_format($total, 1) . "</td></tr>";
											$cat += $core['marks'];
											$exam += $core['marks'];
											$totalCatColumn += $datas['marks'];
											$totalExamColumn += $datas['exam_marks'];
											};
											$total = $cat + $exam;
											$catGEchec = $totalCatColumn<($cat/2)?'fail':'';
											$ExamGEchec = $totalExamColumn<($cat/2)?'fail':'';
											$totalGEchec = ($totalExamColumn+ $totalCatColumn)<$cat?'fail':'';
											?>
										<tr>
											<td colspan="4" width="40%" style="text-transform: uppercase"> Discipline</td>
											<td class="colscenter" colspan="3"><?= $discipline_max; ?></td>
											<td class="colscenter"
												colspan="3"><?= $discipline_max - extractDisciplineMarks($student['displine_marks'],$term); ?></td>
										</tr>
										<tr>
											<td colspan="4" width="40%"> <?= lang("app.genTot"); ?> </td>
											<td class="colscenter"><?= $cat; ?></td>
											<td class="colscenter"><?= $exam; ?></td>
											<td class="colscenter"><?= ($cat + $exam); ?></td>
											<td class="<?=$catGEchec;?>"><?= number_format($totalCatColumn, 1); ?></td>
											<td class="<?=$ExamGEchec;?>"><?= number_format($totalExamColumn, 1); ?></td>
											<td class="<?=$totalGEchec;?>"><?= number_format($totalExamColumn + $totalCatColumn, 1); ?></td>
										</tr>
										<tr style="background-color: #f5e1b9">
											<td colspan="7"><b> <?= lang("app.per"); ?> </b></td>
											<?php
											$perc = ($totalExamColumn + $totalCatColumn) * 100 / $total;
											?>
											<td colspan="3"
												class="colscenter <?=$perc<50?'fail':'';?>"><?= number_format($perc, 1); ?>
												%
											</td>
										</tr>

										<tr>
											<td colspan="7" width="40%"> <?= lang("app.position"); ?> </td>
											<td colspan="3" class="colscenter"><?= $i; ?> out
												of <?= count($students); ?></td>
										</tr>

										</tbody>
									</table>
									<div class="report_footer">
										<div class="spacer"></div>
										<div style="display: inline-flex;width: 100%;margin-bottom: 20px">
											<strong>CLASS TEACHER SIGNATURE</strong>
										</div>
										<br><br>
										<div style="display: inline-flex;width: 100%;margin-bottom: 20px">
											<strong>PARENT'S SIGNATURE</strong>
										</div>
										<br>
										<div style="width: 100%;">
											<div style="width: 100%;text-align: center">
												<strong>Done at <strong><?=$school_address;?></strong> on <?= date('d / m / Y'); ?></strong>
											</div>
										</div>
										<div style="width: 100%;margin: 20px 0">
											<p style="font-weight: bold"><?= $head_master; ?></p>
											<div class="spacer" style="height: 0"></div>
											<label style="font-weight: normal">School Headmaster</label>
										</div>
										<div class="spacer" style="height: 30px"></div>
										<div style="width: 100%;margin: 20px 0">
											<p>Signature and stamp</p>
											<div class="spacer" style="height: 0"></div>
											<p>
												<?php
												if (strlen($headmaster_signature)>5){
													?>
													<img src="<?= base_url('assets/images/signatures/' . $headmaster_signature); ?>" style="height: 50px;"
														 alt="Headmaster signature">
													<?php
												}else{
													echo "______________________________________";
												}
												?>
											</p>
										</div>
									</div>

								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</pagebreak>
		<?php
	}
	$i++;
} ?>
