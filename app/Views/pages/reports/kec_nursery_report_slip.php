<style>
	.tablepage {
		width: 90%;
		color: #000000;
		margin-left: 70px!important;
	}

	.colscenter {
		text-align: center;
		font-weight: bold;
	}

	.solid {
		border-style: solid;
		border-width: 2px;
	}

	.solid2 {
		border-style: solid;
		border-width: 1.5px;
		margin-right: 5px;
		margin-left: 10px;
	}

	.report_footer {
		padding-right: 50px;
		padding-left: 50px;
		margin-top: -10px;
	}

	.col-md-12 {
		width: 100%;
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

	.col4 {
		width: 17%;
		float: left;
		padding: 5px;
		display: inline;
	}

	th, td {
		min-width: 30px;
		padding: 3px 5px;
		border: 1px solid #777777;
		font-size: 10pt;
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
		height: 20px
	}

	.watermarkreport {
		background-image:url(<?= base_url('assets/images/schools/border_frames.png'); ?>);
		background-position: center;
		background-size: cover;
		background-repeat:no-repeat;
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

</style>
<?php
$i           = 1;
$student_reg = isset($_GET['student']) ? $_GET['student'] : false;
if (count($students) === 0)
{
	echo "<h1 style=''>" . lang('app.noStudentFound') . '</h1>';
}
$result = new \App\Controllers\Home();
//print_r($students); die();
foreach ($students as $student)
{
	if ($student['id'] === $student_reg || $student_reg === false)
	{
		?>
		<div style="margin-top: 15px;width: 100%;float:left;" id="printable">
			<div class="col-md-12 col-sm-12 pull-left " style="margin-bottom: 0px">
				<div style="padding: 10px;overflow: hidden;" class="solid watermarkreport">
					<div style="margin-top: 15px;width: 97%;float:left;">
						<div style="margin-left: 27px;margin-right: 10px;width: 100%">
							<div style="float: left" class="col-md-12">
								<div class="pull-left" style="width: 40%;background-color: white;border-radius: 20px; padding: 5px;">
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
								<div class="pull-right" style="max-width: 45%;background-color: #ffffff66;border-radius: 20px; padding: 5px;margin-right: 50px;">
									<div class="pull-right">
										<span><b> <?= lang("app.student"); ?> </b>  :  <?= $student['fname']; ?> &nbsp;  <?= $student['lname']; ?></span><br>
										<span><b> <?= lang("app.sClass"); ?> </b>   : <?= $student['level_name'] ?> <?= $student['title'] ?> <?= $student['code'] ?></span><br>
										<span><b> <?= lang("app.academicYear"); ?> </b> :<?= $academic_year_title; ?></span><br>
										<span><b><?= lang("app.regNo"); ?></b>   :<?= $student['regno'] ?></span><br>
									</div>
								</div>
							</div>
						</div>
						<div class="col-md-6 col-sm-6 pull-left" style="margin-bottom: 15px">
							<div style="padding: 10px;overflow: hidden;">
								<div style="width:100%;padding: 5px;overflow: hidden;text-align: center">
									<!--									<b> Contact :--><?//= $school_phone; ?><!-- </b>-->
									<div class="spacer" style="height: 5px"></div>
									<div style="margin-top: 1px"><u><strong><?= lang('app.nurserySectionReport'); ?></strong></u>
									</div>
								</div>
								<table class="tablepage" border="1" style="margin: 10px;">
									<!--Table head-->
									<tbody id="disciplineTable">
									<tr>
										<td rowspan="2" style="text-transform: uppercase;font-weight: bold;"> <?= lang('app.subject'); ?> </td>
										<!--<td colspan="1" class="colscenter"> <?= lang('app.maxim'); ?> </td>-->
										<td class="colscenter"> <?= lang('app.' . termToStr($term)); ?> </td>
									</tr>
									<tr>
										<!--<td class="colscenter"> <?= lang('app.test'); ?> </td>-->
										<!--<td class="colscenter"> <?= lang('app.theex'); ?> </td>-->
										<!--<td class="colscenter"> <?= lang('app.tot'); ?> </td>-->
										<!--<td class="colscenter"> <?= lang('app.test'); ?> </td>-->
										<td class="colscenter"> <?= lang('app.grades'); ?> </td>
										<!--<td class="colscenter"> <?= lang('app.tot'); ?> </td>-->
									</tr>
									<?php
									$category        = '';
									$cat             = 0;
									$exam            = 0;
									$totalCatColumn  = 0;
									$totalExamColumn = 0;
									$countX          = 0;
									foreach ($student['courses'] as $core)
									{
									//							if ($category != $core['category']) {
									//								echo "<tr>
									//					<td colspan=\"10\" style=\"background-color: #f5e1b9\"><b>" . strtoupper($core['category']) . "</b></td>
									//				</tr>";
									//								$category = $core['category'];
									//							}
										if ($core['category']=="ENGLISH") {
									?>
									<tr>
										<td style="font-weight: bold"><?= $core['title']; ?></td>
										<!--<td class="colscenter"><?= $core['marks']; ?></td>-->
										<!--<td class="colscenter"><?= $core['marks']; ?></td>-->
										<!--<td class="colscenter"><?= $core['marks']; ?></td>-->
										<?php
										$datas = $core['result'];
										//$total = $datas['exam_marks'] + $datas['marks'];
										$total = $datas['exam_marks'] ;
										//total row exam
										$total_row_cat  = $datas['marks'] / $core['marks'] * 100;
										$total_row_exam = $datas['exam_marks'] / $core['marks'] * 100;
										$total_row      = $total / ($core['marks'] * 2) * 100;
										//echo "<td >" . grade_name($grades, $total_row_cat) . "</td><td >" . grade_name($grades, $total_row_exam) . "</td><td >" . grade_name($grades, $total_row) . "</td></tr>";

										echo "<td >" . grade_name($grades, $total_row_exam) . "</td></tr>";

										$category = $core['category'];
										}

										$cat  += $core['marks'];
										$exam += $core['marks'];
										//                                      $totalCatColumn  += $datas['marks'] / $core['marks'] * 100;
										$totalCatColumn += $datas['marks'];
										//                                      $totalExamColumn += $datas['exam_marks'] / $core['marks'] * 100;
										$totalExamColumn += $datas['exam_marks'];
										$countX++;
										}
										//$total = $cat + $exam;
										$total = $exam;
										?>
									<tr>
										<!--<td width="40%"> <?= lang('app.genTot'); ?> </td>-->
										<!--<td class="colscenter"><?= $cat; ?></td>-->
										<!--<td class="colscenter"><?= $exam; ?></td>-->
										<!--<td class="colscenter"><?= ($exam); ?></td>-->
										<?php
										$generalTotalCat  = $totalCatColumn / $countX;
										$generalTotalExam = $totalExamColumn / $countX;
										//$generalTotal     = $totalCatColumn + $totalExamColumn;
										$generalTotal     = $totalExamColumn;
										?>
										<!--<td colspan="1"
											style='text-align: center;'><?=grade_name($grades, $generalTotal * 100 / $total);?></td>-->
									</tr>
									<!--<tr>
										<td colspan="4" width="40%"> <?= lang('app.per'); ?> </td>
											<td colspan="2"
												class="colscenter"><?= number_format($generalTotal * 100 / $total, 1); ?>
												%
											</td>

									</tr>-->
									<tr>
										<td ><b>Class teacher's Remarks </b></td>
										<td style="height: 40px"></td>
									</tr>
									<tr>
										<td><b><?= lang('app.tSignture'); ?> </b></td>
										<td style="height: 40px"></td>
									</tr>
									<tr>
										<td><b><?= lang('app.pSignture'); ?> </b></td>
										<td style="height: 40px"></td>
									</tr>

									</tbody>
								</table>
							</div>
						</div>
						<div class="col-md-6 col-sm-6 pull-right" style="margin-bottom: 15px">
							<div style="padding: 10px;overflow: hidden;">
								<div style="width:100%;padding: 5px;overflow: hidden;text-align: center">
									<!--									<b> Contact :--><?//= $school_phone; ?><!-- </b>-->
									<div class="spacer" style="height: 5px"></div>
									<div style="margin-top: 1px"><u><strong> <?= strtoupper("Section Maternelle - Bulletin");?></strong></u>
									</div>
								</div>
								<table class="tablepage" border="1" style="margin: 10px;">
									<!--Table head-->
									<tbody id="disciplineTable">
									<tr>
										<td rowspan="2" style="text-transform: uppercase;font-weight: bold;"> Branches </td>
										<!--<td colspan="1" class="colscenter"> <?= lang('app.maxim'); ?> </td>-->
										<td class="colscenter"> Premier Trimestre </td>
									</tr>
									<tr>
										<!--<td class="colscenter"> <?= lang('app.test'); ?> </td>-->
										<!--<td class="colscenter"> <?= lang('app.theex'); ?> </td>-->
										<!--<td class="colscenter"> <?= lang('app.tot'); ?> </td>-->
										<!--<td class="colscenter"> <?= lang('app.test'); ?> </td>-->
										<td class="colscenter"> <?= lang('app.grades'); ?> </td>
										<!--<td class="colscenter"> <?= lang('app.tot'); ?> </td>-->
									</tr>
									<?php
									$category        = '';
									$cat             = 0;
									$exam            = 0;
									$totalCatColumn  = 0;
									$totalExamColumn = 0;
									$countX          = 0;
									foreach ($student['courses'] as $core)
									{
									//							if ($category != $core['category']) {
									//								echo "<tr>
									//					<td colspan=\"10\" style=\"background-color: #f5e1b9\"><b>" . strtoupper($core['category']) . "</b></td>
									//				</tr>";
									//								$category = $core['category'];
									//							}

									if ($core['category']=="FRENCH") {

									?>
									<tr>
										<td style="font-weight: bold"><?= $core['title']; ?></td>
										<!--<td class="colscenter"><?= $core['marks']; ?></td>-->
										<!--<td class="colscenter"><?= $core['marks']; ?></td>-->
										<!--<td class="colscenter"><?= $core['marks']; ?></td>-->
										<?php
										$datas = $core['result'];
										//$total = $datas['exam_marks'] + $datas['marks'];
										$total = $datas['exam_marks'] ;
										//total row exam
										$total_row_cat  = $datas['marks'] / $core['marks'] * 100;
										$total_row_exam = $datas['exam_marks'] / $core['marks'] * 100;
										$total_row      = $total / ($core['marks'] * 2) * 100;
										//echo "<td >" . grade_name($grades, $total_row_cat) . "</td><td >" . grade_name($grades, $total_row_exam) . "</td><td >" . grade_name($grades, $total_row) . "</td></tr>";

										if (grade_name($grades, $total_row_exam)=="EXCELLENT"){
											echo "<td >Excellent</td>";
										}elseif (grade_name($grades, $total_row_exam)=="VERY GOOD"){
											echo "<td >Très Bien</td>";
										}elseif (grade_name($grades, $total_row_exam)=="GOOD"){
											echo "<td >Bon</td>";
										}elseif (grade_name($grades, $total_row_exam)=="BELOW AVERAGE"){
											echo "<td >Sous la Moyenne</td>";
										}

										echo "</tr>";



										$category = $core['category'];
										}

										$cat  += $core['marks'];
										$exam += $core['marks'];
										//                                      $totalCatColumn  += $datas['marks'] / $core['marks'] * 100;
										$totalCatColumn += $datas['marks'];
										//                                      $totalExamColumn += $datas['exam_marks'] / $core['marks'] * 100;
										$totalExamColumn += $datas['exam_marks'];
										$countX++;
										}
										//$total = $cat + $exam;
										$total = $exam;
										?>
									<tr>
										<!--<td width="40%"> <?= lang('app.genTot'); ?> </td>-->
										<!--<td class="colscenter"><?= $cat; ?></td>-->
										<!--<td class="colscenter"><?= $exam; ?></td>-->
										<!--<td class="colscenter"><?= ($exam); ?></td>-->
										<?php
										$generalTotalCat  = $totalCatColumn / $countX;
										$generalTotalExam = $totalExamColumn / $countX;
										//$generalTotal     = $totalCatColumn + $totalExamColumn;
										$generalTotal     = $totalExamColumn;
										?>
										<!--<td colspan="1"
											style='text-align: center;'><?=grade_name($grades, $generalTotal * 100 / $total);?></td>-->
									</tr>
									<!--<tr>
										<td colspan="4" width="40%"> <?= lang('app.per'); ?> </td>
											<td colspan="2"
												class="colscenter"><?= number_format($generalTotal * 100 / $total, 1); ?>
												%
											</td>

									</tr>-->
									<tr>
										<td ><b>Remarques du tutulaire</b></td>
										<td style="height: 40px"></td>
									</tr>
									<tr>
										<td ><b>Signature du titulaire</b></td>
										<td style="height: 40px"></td>
									</tr>
									<tr>
										<td ><b>Signature des parents</b></td>
										<td style="height: 40px"></td>
									</tr>

									</tbody>
								</table>
							</div>
						</div>
						<table class="col-md-12" style="border: 0px solid white;border-collapse: collapse;border-style: dotted;">
							<tr style="height: 100px;">
								<td style="position: relative;width: 60%!important;">
								<div style="margin-left: 70px;">
									<div style="position: absolute;top: 20px;left: 20px;width: 100%">
										<div style="display: block;width: 100%;float: left">
											<!--													<div class="cols4">-->
											<!--														<strong>Colors:</strong>-->
											<!--													</div>-->
											<strong><?= lang('app.grading'); ?>: </strong>
											<ul>
												<?php foreach ($grades as $grade): ?>
													<li><strong><?= $grade['color_title']; ?>: <?= $grade['min_point']; ?> - <?= $grade['max_point']; ?></strong></li>
													<!--<div class="col4" style="text-align: center;">
															<div style="background-color:<?= $grade['color'] ?>;border: solid black 2px;">
																<?= $grade['color_title']; ?>
															</div>
															<strong><?= $grade['color_title']; ?>: <?= $grade['max_point']; ?> - <?= $grade['min_point']; ?></strong>
														</div><br>-->
												<?php endforeach; ?>
											</ul>
										</div>

									</div>
									<br><br><br><br>
									<span style="font-size: 12pt;"><?= lang('app.nextTermStarts'); ?> ..../..../......<br><?= lang('app.nextTermEnds'); ?> ..../..../......</span>
									<br><br>

									<span style="font-size: 12pt;">Le prochain trimestre commence le ..../..../......<br>Se termine le ..../..../......</span>
								</div>
								</td>
								<td class="" style="text-align: left;">
									<div style="width: 200px;margin-left:50px;float: left">
										<div style="width:100%;">
											<div style="text-align: center;margin-top: 20px;margin-bottom: 10px">
												<strong style="text-align: left;float: left;width:100%;">
													<?= lang('app.doneAt'); ?> <strong><?= $school_address; ?></strong>
													<?= lang('app.on'); ?> <?= date('d / m / Y'); ?></strong>
											</div>
										</div>
										<div style="width:100%;float: left">
											<p style="font-weight: bold;margin-top: 20px"><?= $head_master; ?></p>
											<div class="spacer" style="height: 0"></div>
											<label style="font-weight: normal"><?= lang('app.' . ($head_master_gender === 'F' ? 'schoolHeadmistress' : 'schoolHeadmaster')); ?></label>
										</div>
										<div style="width:100%;margin: 20px 0;float: left">
											<p><?= lang('app.SignatureStamp'); ?></p>
											<p style="margin-bottom: 0">
												<?php
												if (strlen($headmaster_signature) > 5)
												{
													?>
													<img src="<?= base_url('assets/images/signatures/' . $headmaster_signature); ?>"
														 style="max-height: 85px;"
														 alt="">
													<?php
												}
												else
												{
													echo "<p style='height: 80px'>----------------------------------------</p>";
												}
												?>
											</p>
										</div>
									</div>
								</td>
							</tr>
						</table>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
	$i++;
} ?>
