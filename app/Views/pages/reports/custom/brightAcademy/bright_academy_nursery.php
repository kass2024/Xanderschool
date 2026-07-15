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
	.col-md-12{
		width: 100%;
		float: left;
	}
	.col-md-6{
		width: 50%;
		float: left;
	}
	.cols4{
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

	table {
		border-collapse: collapse;
	}

	.pull-right {
		float: right
	}

	.pull-left {
		float: left;
	}
	.spacer{
		float: left;width: 100%;height: 20px
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
					<div style="margin-top: 15px;width: 97%;float:left;">
						<div style="margin-left: 27px;margin-right: 10px;width: 100%">
							<div style="float: left" class="col-md-6">
								<strong
									style="font-weight: 800;font-size: 18px;"><?= strtoupper($school_name); ?></strong>
								<br />
								<div style="margin: 10px 0;">
									<img src="<?= base_url('assets/images/logo/' . $school_logo); ?>" style="width: 110px;" alt="School Logo">
								</div>
								<!-- <br />
								<span><b>Kigali city</b></span><br>
								<span><b>Gasabo district</b></span><br>
								<span><b>Nduba sector</b></span><br>
								<span><b>Gasanze cell</span><br> -->
							</div>
							<div style="float: right;" class="col-md-6">
								<div style="border:solid 2px black;padding: 15px;width: 120px;height: 120px;text-align: center;float: right">
									<img src="<?= base_url('assets/images/profile/' . $student['photo']); ?>"
										 style="width: 100%" alt="">
									<?php if (strlen($student['photo'])<3): ?>
										<strong>Photo</strong>
									<?php endif;?>
								</div>
								<div class="spacer"></div>
								<div style="float: right;margin-top: 10px">
								<strong>Academic year <?= $academic_year_title; ?></strong><br>
								<strong>Student's
									names: </strong><span><?= $student['fname']; ?> &nbsp;  <?= $student['lname']; ?></span><br>
<!--								<strong>Date of birth: </strong><span>--><?//= $student['dob'] ?><!--</span><br>-->
								<strong>Class's
									names: </strong><span><?= $student['level_name'] ?> <?= $student['title'] ?> <?= $student['code'] ?></span>
								</div>
							</div>
						</div>
						<div class="col-md-12 col-sm-12 pull-left" style="margin-bottom: 15px">
							<div style="background:white;padding: 10px;overflow: hidden;">
								<div style="background:white;width:100%;padding: 5px;overflow: hidden;text-align: center">
									<b> Contact :<?= $school_phone; ?> </b>
									<div class="spacer"></div>
									<div style="margin-top: 10px"><u><strong>NURSERY SECTION REPORT FORM</strong></u>
									</div>
								</div>
								<table class="tablepage" border="1" style="margin: 10px;">
									<!--Table head-->
									<tbody id="disciplineTable">
									<tr>
										<td colspan="4" rowspan="2" width="40%" style="text-transform: uppercase"> <?= lang("app.subject"); ?> </td>
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
									$countX = 0;
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
										//total row exam
										$total_row_cat = $datas['marks'] / $core['marks'] * 100;
										$total_row_exam = $datas['exam_marks'] / $core['marks'] * 100;
										$total_row = $total / ($core['marks'] * 2) * 100;

										echo "<td  style='background-color: " . grade_color($grades, $total_row_cat) . "'></td>
								      <td style='background-color: " . grade_color($grades, $total_row_exam) . "' ></td>
									  <td style='background-color: " . grade_color($grades, $total_row) . "' ></td></tr>";
										$cat += $core['marks'];
										$exam += $core['marks'];
										$totalCatColumn += $datas['marks'] / $core['marks'] * 100;
										$totalExamColumn += $datas['exam_marks'] / $core['marks'] * 100;
										$countX++;
										}
										$total = $cat + $exam;
										?>
									<tr>
										<td colspan="4" width="40%"> <?= lang("app.genTot"); ?> </td>
										<td class="colscenter"><?= $cat; ?></td>
										<td class="colscenter"><?= $exam; ?></td>
										<td class="colscenter"><?= ($cat + $exam); ?></td>
										<?php
										$generalTotalCat = $totalCatColumn / $countX;
										$generalTotalExam = $totalExamColumn / $countX;
										$generalTotal = ($generalTotalCat + $generalTotalExam) / 2;
										?>
										<td colspan="3"
											style="text-align: center"> <?= number_format($generalTotal * $total / 100, 1) ?></td>
									</tr>
									<tr>
										<td colspan="4" width="40%"> <?= lang("app.per"); ?> </td>
										<td colspan="3" class="colscenter"></td>
										<td colspan="3" class="colscenter"><?= $generalTotal; ?>%</td>
									</tr>
									</tbody>
								</table>
								<div class="report_footer">
									<div style="display: inline-block;width: 100%;float: left">
										<div class="cols4">
											<strong>Colors:</strong>
										</div>
										<?php foreach ($grades as $grade): ?>
											<div class="cols4"  style="text-align: center;">
												<div style="background-color:<?= $grade["color"] ?>;border: solid black 2px;">
													<?= $grade["color_title"]; ?>
												</div>
												<strong><?= $grade["max_point"]; ?> - <?= $grade["min_point"]; ?></strong>
											</div>
										<?php endforeach; ?>
									</div>
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
										<p><?= $head_master; ?></p>
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
												<img src="<?= base_url('assets/images/signatures/' . $headmaster_signature); ?>" style="height: 70px;"
													 alt="Headmaster signature">
												<?php
											}else{
												echo "----------------------------------------";
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
		<?php
	}
	$i++;
} ?>
