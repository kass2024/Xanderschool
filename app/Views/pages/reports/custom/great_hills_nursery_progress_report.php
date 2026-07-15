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
		height: 20px
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

	.subHeader {
		margin: 10px 55px 10px 20px;
		padding: 6px;
		border: solid 2px;
	}
	.blue-subHeader {
		margin: 10px 55px -8px 20px;
		border: solid 2px #0e9de7;
		height: 6px;
	}
	.titleHead {
		text-align: center;
		margin: 10px auto 10px auto;
		color: #0e9de7;
		font-size: 24px;
	}
	.sm-col{
		background-color: #e4cfaa;
		width: 1%;
	}
	.padding-10{
		padding: 10px 0px 10px 0px;
	}
	.footer-tr{
		background-color:#e4cfaa ;
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
				<div style="border: solid cornflowerblue 2px">
					<div style="border: solid indianred 2px;margin: 3px">
						<div style="background:white;padding: 10px;overflow: hidden;" class="solid">
							<div>
								<img src="<?= base_url(); ?>/assets/images/ghs.jpeg" style="width: 100%;height: 270px">
							</div>
							<div style="text-align: center">
								<b>Tel: <?=$school_phone;?></b><br>
								<b>Email: <?=$school_email;?></b>
							</div>
							<div class="blue-subHeader">&nbsp;
							</div>
							<div class="subHeader">
								<span><strong>Nom et Prenom:&nbsp;&nbsp;&nbsp;</strong><?= $student['fname']; ?> &nbsp;  <?= $student['lname']; ?></span><br>
								<span><strong>Date de naisance: </strong><?=$student['dob'];?></span><br>
								<span><strong>Classes: </strong><?= $student['level_name'] ?> <?= $student['title'] ?> <?= $student['code'] ?></span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span><strong>Annee scolaire </strong> <?= $academic_year_title; ?></span><br>
							</div>
							<div style="align-content: center">
								<div class="titleHead"><u>RAPPORT D'ETUDE PROGRESSIVE DE L'ENFANT</u></div>
							</div>
							<div style="margin-top: 2px;width: 97%;float:left;">
								<div style="margin-left: 27px;margin-right: 10px;width: 100%">
								</div>
								<div class="col-md-12 col-sm-12 pull-left" style="margin-bottom: 2px">
									<div style="background:white;padding: 2px;/*overflow: hidden;*/">
										<table class="tablepage" border="1" style="margin: 7px;">
											<tbody id="disciplineTable">
											<tr style="background-color:#e4cfaa ">
												<td colspan="2" class="colscenter" style="width: 50%"><b>ACTIVITIES</b></td>
												<td class="colscenter"><b><?= lang("app.term").' '.$term; ?> </b></td>
											</tr>
											<?php
											$category = "";
											$cat = 0;
											$exam = 0;
											$totalCatColumn = 0;
											$totalExamColumn = 0;
											$countX = 0;
											$table_started = false;
											// var_dump("<pre>",$student['courses']);
											// die();
											$romain = 0;
											foreach ($student['courses'] as $key => $core) {
												if ($category != $core['category']){
													$romain++;
													if ($table_started) {
														/**
														 * Close the first table
														 */
														?>
														<?php
														$table_started = false;
													}
													$table_started = true;
													?>
													<tr>
														<td class="sm-col" style="text-align: center"><span style="color: #0e9de7"><?= chiffreRomain($romain) ?></span></td>
														<td colspan="2"><b
																	style="color: #0e9de7">
																<?= $core['category'] ?></b></td>
													</tr>
													<?php
													$category = $core['category'];
												}
												?>
												<tr>
													<td class="sm-col">&nbsp;</td>
													<td><?= $core['title']; ?></td>
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
										  <td  class='colscenter' >" . grade_letter($grades, $total_row) . "</td>";
													$cat += $core['marks'];
													$exam += $core['marks'];
													$totalCatColumn += $datas['marks'] / $core['marks'] * 100;
													$totalExamColumn += $datas['exam_marks'] / $core['marks'] * 100;
													$countX++;
													?>
												</tr>
												<?php
											}
											$total = $cat + $exam;
											if ($table_started){
												/**
												 * Close the first table
												 */
												?>

												<?php
												$table_started = false;
											}
											?>
											<tr class="footer-tr">
												<td colspan="2" class="padding-10">APPRECIATION GENERAL</td>
												<td >..................</td>
											</tr>
											<tr class="footer-tr">
												<td colspan="2">Signature de la directrice</td>
												<td colspan="2">..................</td>
											</tr>
											<tr class="footer-tr">
												<td colspan="2">Signature du parent</td>
												<td>..................</td>
											</tr>
											<tr class="footer-tr">
												<td colspan="2" class="padding-10">Fait a Kigali, le </td>
												<td>.............</td>
											</tr>
											</tbody>
										</table>
										<div class="report_footer">
											<div style="width: 100%;margin: 20px 0;text-align: center">
												<strong style="width: 200px">"Voice le commencement de la sagesse: Aquires la sagesse , au prix de tout ce que possedes, aqueirs l'intelligence . (Proverbes) 4:7"</strong>
											</div>
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
