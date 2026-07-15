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

	.watermark {
		display: block;
		position: relative;
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

	.watermarkreport {
		background-image:url(<?= base_url('assets/images/schools/kec_logo.jpg'); ?>);
		background-position: center;
		background-size: 70%;
		background-repeat:no-repeat;
	}
	.watermark::after {
		content: "";
		background:url(<?= base_url('assets/images/schools/kec_logo.jpg'); ?>);
		opacity: 0.2;
		top: 0;
		left: 0;
		bottom: 0;
		right: 0;
		position: absolute;
		z-index: -1;
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
		<div style="margin-top: 15px;width: 100%;float:left;" id="printable">
			<div class="col-md-12 col-sm-12 pull-left " style="margin-bottom: 15px">
				<div style="background:white;padding: 10px;overflow: hidden;" class="solid">
					<div style="margin-top: 15px;width: 100%;float:left;">
						<div class="col-md-12 col-sm-12 pull-left" style="margin-bottom: 15px">
							<div style="padding: 10px;overflow: hidden;" class="watermarkreport">
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
										<span><b> <?= lang("app.website"); ?> </b>  : <?= $school_website; ?></span><br>
									</div>
								</div>
								<?php
								if (@getimagesize(base_url('assets/images/profile/' . $student['photo']))) {
									?>
									<div class="pull-left"
										 style="margin-top: 30px;margin-bottom:10px;width: 120px;min-height: 120px">
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
										<span><b><?= lang("app.regNo"); ?></b>   :<?= $student['regno'] ?></span><br>
									</div>
								</div>
								<div class="spacer"></div>
								<div style="padding: 5px;display: block;margin:0 0 10px 1%;text-align: center" class="solid2 col-md-12">
									<b> <?= lang("app.primarySectionReport"); ?> </b>
								</div>

								<table class="tablepage" border="1" style="margin: 10px;">
									<!--Table head-->
									<tbody id="disciplineTable">
									<tr>
										<td colspan="4" rowspan="2" width="40%"
											style="text-transform: uppercase"> <?= lang("app.subject"); ?> </td>
										<td colspan="3" class="colscenter"> <?= lang("app.maxim"); ?> </td>
										<td colspan="3"
											class="colscenter"> <?= lang("app." . termToStr($term)); ?> </td>
										<td class="colscenter"> Remarks </td>
									</tr>
									<tr>
										<td class="colscenter"> <?= lang("app.test"); ?> </td>
										<td class="colscenter"> <?= lang("app.theex"); ?> </td>
										<td class="colscenter"> <?= lang("app.tot"); ?> </td>
										<td class="colscenter"> <?= lang("app.test"); ?> </td>
										<td class="colscenter"> <?= lang("app.theex"); ?> </td>
										<td class="colscenter"> <?= lang("app.tot"); ?> </td>
										<td class="colscenter"></td>
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
										echo "<td>" . ($datas['marks']==null?'-':number_format($datas['marks'], 1)) . "</td>
								      <td>" . ($datas['exam_marks']==null?'-':number_format($datas['exam_marks'], 1)) . "</td>
									  <td>" . ($total==null?'-':number_format($total, 1)) . "</td><td></td></tr>";
										$cat += $core['marks'];
										$exam += $core['marks'];
										$totalCatColumn += $datas['marks'];
										$totalExamColumn += $datas['exam_marks'];
										};
										$total = $cat + $exam;
										?>

									<!--<tr>
										<td colspan="4" width="40%"> <?=lang('app.discipline');?></td>
										<td class="colscenter" colspan="3"><?= $discipline_max; ?></td>
										<td class="colscenter"
											colspan="3"><?= number_format(($discipline_max - extractDisciplineMarks($student['displine_marks'],$term))); ?></td>
										<td class="colscenter"></td>
									</tr>-->
									<tr>
										<td colspan="4" width="40%"> <?= lang("app.genTot"); ?> </td>
										<td class="colscenter"><?= $cat; ?></td>
										<td class="colscenter"><?= $exam; ?></td>
										<td class="colscenter"><?= ($cat + $exam); ?></td>
										<td><?= number_format($totalCatColumn, 1); ?></td>
										<td><?= number_format($totalExamColumn, 1); ?></td>
										<td><?= number_format($totalExamColumn + $totalCatColumn, 1); ?></td>
										<td class="colscenter"></td>
									</tr>
									<tr style="background-color: #f5e1b9">
										<td colspan="7"><b> <?= lang("app.per"); ?> </b></td>
										<td colspan="4"
											class="colscenter"><?= number_format(($totalExamColumn + $totalCatColumn) * 100 / $total, 1); ?>
											%
										</td>
									</tr>

									<tr>
										<td colspan="7" width="40%" style="font-weight: bold;"> <?= lang("app.position"); ?> </td>
										<td style="color:red;" class="colscenter"><?= $my_position[termToStr($term)]['cat'][$student['id']] ?>/<?= count($my_position[termToStr($term)]['cat']) ?></td>
										<td style="color:red;" class="colscenter"><?= $my_position[termToStr($term)]['exam'][$student['id']] ?>/<?= count($my_position[termToStr($term)]['exam']) ?></td>
										<td style="color:red;" class="colscenter"><?= $my_position[termToStr($term)]['total'][$student['id']] ?>/<?= count($my_position[termToStr($term)]['total']) ?></td>
										<!-- <td style="color:red;" class="colscenter"><?= $i; ?> / <?= count($students); ?></td> -->
										<td></td>
									</tr>
									<tr>
										<td colspan="4"><b>Class teacher's Remarks </b></td>
										<td colspan="7" style="height: 40px"></td>
									</tr>

									<tr>
										<td colspan="4"><b><?= lang("app.tSignture"); ?> </b></td>
										<td colspan="7" style="height: 40px"></td>
									</tr>
									<tr>
										<td colspan="4"><b>Headteacher's Remarks </b></td>
										<td colspan="7" style="height: 40px"></td>
									</tr>
									<tr>
										<td colspan="4"><b><?= lang("app.pSignture"); ?> </b></td>
										<td colspan="7" style="height: 40px"></td>
									</tr>
									<tr style="height: 100px;">
										<td colspan="5" style="position: relative;">
											<div style="width:100%;position: absolute;top: 0;bottom: 0">
												<div style="text-align: center;margin-top: 20px;margin-bottom: 10px">
													<strong style="text-align: left;float: left;width:100%;">
														<?=lang('app.doneAt');?> <strong><?= $school_address; ?></strong>
														<?=lang('app.on');?> <?= date('d / m / Y'); ?></strong>
													<br><br><br><br>
													<span style="font-size: 12pt;"><?= lang('app.nextTermStarts'); ?> ..../..../......<br><?= lang('app.nextTermEnds'); ?> ..../..../......</span>
												</div>
											</div>
										</td>
										<td colspan="7" class="" style="text-align: left;">
											<div style="width: 350px;float: right">

												<div style="width:100%;float: left">
													<p style="font-weight: bold;margin-top: 20px"><?= $head_master; ?></p>
													<div class="spacer" style="height: 0"></div>
													<label style="font-weight: normal"><?= lang('app.'.($head_master_gender == 'F' ? 'schoolHeadmistress' : 'schoolHeadmaster')); ?></label>
												</div>
												<div class="spacer" style="height: 20px"></div>
												<div style="width:100%;margin: 20px 0;float: left">
													<p><?=lang('app.SignatureStamp');?></p>
													<div class="spacer" style="height: 0"></div>
													<p style="margin-bottom: 10px">
														<?php
														if (strlen($headmaster_signature) > 5) {
															?>
															<img src="<?= base_url('assets/images/signatures/' . $headmaster_signature); ?>"
																 style="max-height: 150px;"
																 alt="">
															<?php
														} else {
															echo "<p style='height: 100px'>----------------------------------------</p>";
														}
														?>
													</p>
												</div>
											</div>
										</td>
									</tr>
									</tbody>
								</table>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
	$i++;
} ?>
