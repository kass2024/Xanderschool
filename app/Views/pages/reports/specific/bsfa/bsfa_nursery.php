<style>
	.tablepage {
		width: 100%;

	}

	table.tablepage tbody tr td {
		font-size: 12px;
	}

	.colscenter {
		text-align: center;
		/*font-weight: bold;*/
	}

	.solid {
		/*border-style: solid;
		border-width: 2px;*/
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

	.cols3 {
		width: 30%;
		float: left;
		padding: 5px;
		display: inline;
	}

	th, td {
		min-width: 30px;
		padding: 2px 2px;
		/*border: 1px solid #777777;*/
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
		height: 10px
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
					<div style="margin-top: 2px;width: 97%;float:left;">
						<div style="margin-left: 27px;margin-right: 10px;width: 100%">
							<table id="report_table" class="table" border=0>
								<tr>
									<td style="width: 34%; border: 0px solid #000000;">
										<div style="margin: 0;">
											<img src="<?= base_url('assets/images/logo/' . $school_logo); ?>"
												 style="width: 100px;" alt="School Logo">
										</div>
									</td>
									<td colspan="2" style="border: 0px solid #000000;">
										<div style="font-family: helvetica; font-size: 17px; color: blue; border: 1px solid #000000; text-align: center;">
											<span style="font-size: 21px;"><?= ($school_name); ?>(<?= $school_acronym ?>)</span><br>
											<strong><?= ucwords($school_moto) ?></strong><br>
											<span><?= lang("app.phone"); ?> : 0788303713/<?= $school_phone; ?></span><br>
										</div>
									</td>
								</tr>
								<tr>
									<td colspan="2"
										style="text-align: right; padding-right: 15%; border: 0px solid #000000;">
										PUPIL'S REPORT
									</td>
									<td rowspan="4" style="width: 15%; text-align:center; border: 0px solid #000000;">
										<?php
										if (@getimagesize(base_url('assets/images/profile/' . $student['photo']))) {
											?>
											<div style="border:0px solid #555;min-height: 90px;">
												<img src="<?= base_url('assets/images/profile/' . $student['photo']); ?>"
													 style="height: 100px;" alt="">
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
						</div>
						<div class="col-md-12 col-sm-12 pull-left" style="margin-bottom: 2px">
							<div style="background:white;padding: 2px;/*overflow: hidden;*/">
								<div style="display: inline-block;width: 100%;float: left;font-size: 9pt">
									<div class="cols4">
										GUIDE:
									</div>
									<?php foreach ($grades as $grade): ?>
										<div class="cols<?= strlen($grade["color_title"]) >= 10 ? 3 : 4; ?>"
											 style="text-align: center;">
											<div style="background-color:<?= $grade["color"] ?>;border: solid black 1px;padding: 2px 0">
												<?= $grade["color_title"]; ?>
												(<?= grade_letter($grades, $grade['max_point']) ?>)
											</div>
											<strong><?= $grade["max_point"]; ?> - <?= $grade["min_point"]; ?></strong>
										</div>
									<?php endforeach; ?>
								</div>


								<?php
								$category = "";
								$cat = 0;
								$exam = 0;
								$totalCatColumn = 0;
								$totalExamColumn = 0;
								$totalCatColumnRaw = 0;
								$totalExamColumnRaw = 0;
								$countX = 0;
								$table_started = false;
								// var_dump("<pre>",$student['courses']);
								// die();
								foreach ($student['courses'] as $core) {
								if ($category != $core['category']){
								if ($table_started) {
									/**
									 * Close the first table
									 */
									?>
									</tbody>
									</table>
									<?php
									$table_started = false;
								}
								$table_started = true;
								?>
								<table class="tablepage" border="1" style="margin: 4px 7px;">
									<tbody id="disciplineTable">
									<tr>
										<td colspan="4"
											style="text-align: center; width: 34%;"><?= $core['category'] ?></td>
										<td class="colscenter"> <?= lang("app.test"); ?> </td>
										<td class="colscenter"> <?= lang("app.ex"); ?> </td>
										<td class="colscenter"> <?= lang("app.tot"); ?> </td>
										<td class="colscenter"> <?= lang("app.term") . $term; ?> </td>
									</tr>
									<?php
									$category = $core['category'];
									}
									?>
									<tr>
										<td colspan="4"><?= $core['title']; ?></td>
										<td class="colscenter"><?= $core['marks']; ?></td>
										<td class="colscenter"><?= $core['marks']; ?></td>
										<td class="colscenter"><?= $core['marks'] * 2; ?></td>
										<?php
										$datas = $core['result'];
										$total = $datas['exam_marks'];
										if (!is_null($datas['marks'])) {
											$total += $datas['marks'];
										}
										//total row exam
										$total_row_cat = $datas['marks'] / $core['marks'] * 100;
										$total_row_exam = $datas['exam_marks'] / $core['marks'] * 100;
										$total_row = NULL;
										if (!is_null($total)) {
											$total_row = $total / ($core['marks'] * 2) * 100;
										}

										echo "<!--<td  style='background-color: " . grade_color($grades, $total_row_cat) . "'></td>
									      <td style='background-color: " . grade_color($grades, $total_row_exam) . "' ></td>-->
										  <td class='colscenter' style='background-color: " . grade_color($grades, $total_row) . "' >" . grade_letter($grades, $total_row) . "</td>";
										$cat += $core['marks'];
										$exam += $core['marks'];
										$totalCatColumn += $datas['marks'] / $core['marks'] * 100;
										$totalExamColumn += $datas['exam_marks'] / $core['marks'] * 100;
										$totalCatColumnRaw += $datas['marks'];
										$totalExamColumnRaw += $datas['exam_marks'];
										$countX++;
										?>
									</tr>
									<?php
									}
									$total = $cat + $exam;

									$perc = ($totalCatColumnRaw + $totalExamColumnRaw) * 100 / $total;

									if ($table_started){
									/**
									 * Close the first table
									 */
									?>
									<tr>
										<th colspan="8" style="font-size: 9pt;text-align: left">PERCENTAGE<span
													style="float: right;min-width: 100px;text-align: right"><?= number_format($perc, 1); ?>%</span>
										</th>
									</tr>
									<tr>
										<th colspan="8" style="font-size: 9pt;text-align: left">POSITION<span
													style="float: right;min-width: 100px;text-align: right"><?= $i; ?> out of <?= count($students); ?></span>
										</th>
									</tr>
									</tbody>
								</table>
							<?php
							$table_started = false;
							}
							?>
								</table>
								<div class="report_footer">
									<div style="width: 60%;float: left">
										<div class="spacer"></div>
										<div style="display: inline-flex;width: 100%;margin-bottom: 10px">
											Comment:
										</div>
										<br><br>
										<div style="width: 100%;margin-bottom: 5px">
											Signature of <label style="font-weight: normal">School <?=$head_master_gender=='F'?'Headmistress':'Headmaster';?></label>
											<div class="spacer" style="height: 10px"></div>
											<p>
												<?php
												if (strlen($headmaster_signature) > 5) {
													?>
													<img src="<?= base_url('assets/images/signatures/' . $headmaster_signature); ?>"
														 style="height: 60px;"
														 alt="Headmaster signature">
													<?php
												} else {
													echo "______________________________________";
												}
												?>
											</p>
										</div>

									</div>
									<div style="width: 40%;float: right">
										<div style="text-align: center;margin-top: 20px;margin-bottom: 10px">
											<strong style="text-align: left;float: left;width:100%;">
												Done at <strong><?= $school_address; ?></strong>
												on <?= date('d / m / Y'); ?></strong>
										</div>
										<div class="spacer" style="height: 10px"></div>
										<div style="width: 100%;margin: 20px 0">
											<p>Class Teacher's signature</p>
											<div class="spacer" style="height: 4px"></div>
											<p>______________________________________</p>
										</div>
										<div style="width: 100%;margin: 20px 0">
											<p>Signature of Parent</p>
											<div class="spacer" style="height: 4px"></div>
											<p>______________________________________</p>
										</div>
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
}
// die();
