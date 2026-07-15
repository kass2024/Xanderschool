<style>
	.tablepage {
		width: 100%;

	}

	.colscenter {
		text-align: center;
		font-weight: bold;
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
		height: 99%;
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

	.col-md-6 {
		width: 48%;
	}

	.printable {
		page-break-inside: avoid !important;
		page-break-after: avoid !important;
		page-break-before: avoid !important;
		width: 100% !important;
		max-height: 100%;
		margin-top: 0 !important;
	}

	.printable .solid {
		width: 100%;
		height: 100%;
	}

	.bold-right {
		border-right: 2px solid black;
	}

	.no-printable {
		margin: 15px !important;
		width: calc(100% - 30px) !important;
	}

	.fail {
		color: red;
		text-decoration: underline
	}

	.badge {
		font-weight: bold;
		text-transform: uppercase;
		padding: 8px 16px;
		min-width: 19px;
	}

	.badge-dark {
		color: #fff;
		background-color: #343a40;
	}

	.badge-dark {
		color: #fff;
		background-color: #343a40;
	}

	.badge {
		display: inline-block;
		/*padding: .25em .4em;*/
		font-size: 75%;
		font-weight: 700;
		line-height: 1;
		text-align: center;
		white-space: nowrap;
		vertical-align: baseline;
		border-radius: .25rem;
	}
</style>
<?php
$i = 1;
$student_reg = isset($_GET['student']) ? $_GET['student'] : false;
if (count($students) == 0) echo "<h1 style=''>" . lang("app.noStudentFound") . "</h1>";
$result = new \App\Controllers\Home();
//print_r($students); die();
foreach ($students as $student) {
	if (!isset($student['id'])) {
		//positional data
		break;
	}
	if ($student['id'] == $student_reg || $student_reg === false) {
		?>
		<div style="margin-top: 15px;width: 100%;float:left;" class="<?= $pdf ? 'printable' : 'no-printable'; ?>">
			<div class="col-md-12 col-sm-12 pull-left" style="margin: 20px 15px;width: 98%">
				<div class="pull-left" style="margin: 10px 0 10px 10px;width: 47%">
					<span><b><?= lang("app.republic"); ?> </b></span><br>
					<span><b><?= lang("app.ministry"); ?> </b></span><br>
					<span style="font-weight: bold"><?= $school_name; ?></span><br>
					<img src="<?= base_url('assets/images/logo/' . $school_logo); ?>" style="height: 70px;"
						 alt="School Logo"><br>
					<span><b><?= lang("app.mail"); ?></b> : <?= $school_email; ?></span><br>
					<span><b><?= lang("app.phone"); ?> </b>  : <?= $school_phone; ?></span><br>
					<?php
					if (strlen($school_pobox) > 2) {
						?>
						<span><b>P.O BOX </b>  : <?= $school_pobox; ?></span><br>
						<?php
					}
					?>
				</div>
				<?php
				if (@getimagesize(base_url('assets/images/profile/' . $student['photo']))) {
					?>
					<div style="width: 120px;float:left;margin-top: 20px">
						<img src="<?= base_url('assets/images/profile/' . $student['photo']); ?>"
							 style="width: 100%" alt=" ">
					</div>
					<?php
				}
				?>
				<div class="pull-right" style="margin-top: 10px;margin-right:10px;width: auto;min-width: 250px">

					<div>
						<span><b><?= lang("app.academicYear"); ?> </b> :<?= $academic_year_title; ?></span><br>
						<span><b><?= lang("app.firstName"); ?></b>  : <?= $student['fname']; ?> </span><br>
						<span><b><?= lang("app.lastName"); ?></b>   : <?= $student['lname']; ?></span><br>
						<span><b><?= lang("app.sClass"); ?> </b>   : <?= $student['level_name'] ?> <?= $student['title'] ?> <?= $student['code'] ?></span><br>
						<span><b><?= lang("app.regNo"); ?></b>   :<?= $student['regno'] ?></span><br>
					</div>
				</div>
				<div style="background:white;">
					<div style="height: 98%">
						<h4 style="text-align: center;float: left;width: 100%;margin: 20px 0 16px 0;font-size: 19px;font-weight: bold;">
							PROGRESSIVE ANNUAL SCHOOL REPORT</h4>
						<table class="tablepage" border="1">
							<!--Table head-->
							<tbody id="disciplineTable">
							<tr>
								<td colspan="4" rowspan="2" width="40%"
									style="text-transform: uppercase;font-weight: bold"
									class="bold-right"><?= lang("app.subject"); ?> </td>
								<td colspan="3" class="colscenter bold-right"><?= lang("app.maxim"); ?> </td>
								<td colspan="3" class="colscenter bold-right">Term 1</td>
								<td colspan="3" class="colscenter bold-right">Term 2</td>
								<td colspan="3" class="colscenter bold-right">Term 3</td>
								<td colspan="3" class="colscenter bold-right">Annual</td>
								<td colspan="1" class="colscenter">Second sitting</td>
							</tr>
							<tr>
								<td class="colscenter">TEST</td>
								<td class="colscenter"><?= lang("app.ex"); ?> </td>
								<td class="colscenter bold-right"><?= lang("app.tot"); ?> </td>
								<?php for ($a = 0; $a < 4; $a++) {
									?>
									<td class="colscenter">TEST</td>
									<td class="colscenter"><?= lang("app.ex"); ?> </td>
									<td class="colscenter bold-right"><?= lang("app.tot"); ?> </td>
								<?php }; ?>
								<td class="colscenter" rowspan="2">/100</td>
							</tr>
							<!--							<tr>-->
							<!--								<td colspan="4" class="bold-right">-->
							<?//= lang("app.discipline");
							?><!-- </td>-->
							<!--								<td colspan="3" class="colscenter bold-right">-->
							<?//= $discipline_max;
							?><!--</td>-->
							<!--								--><?php
							//								$term1 = extractDisciplineMarks($student['displine_marks'],1);
							//								$term2 = extractDisciplineMarks($student['displine_marks'],2);
							//								$term3 = extractDisciplineMarks($student['displine_marks'],3);
							//
							?>
							<!--								<td colspan="3" class="colscenter bold-right">-->
							<?//= $discipline_max - $term1;
							?><!--</td>-->
							<!--								<td colspan="3" class="colscenter bold-right">-->
							<?//= $discipline_max - $term2;
							?><!--</td>-->
							<!--								<td colspan="3" class="colscenter bold-right">-->
							<?//= $discipline_max - $term3;
							?><!--</td>-->
							<!--								<td colspan="3" class="colscenter bold-right">-->
							<?//= ($discipline_max*3) - ($term1 + $term2 + $term3);
							?><!--</td>-->
							<!--							</tr>-->
							<?php
							$category = "";
							$cat = 0;
							$exam = 0;
							$totalCat1Column = 0;
							$totalCat2Column = 0;
							$totalCat3Column = 0;
							$totalCat4Column = 0;
							$totalExam1Column = 0;
							$totalExam2Column = 0;
							$totalExam3Column = 0;
							$totalExam4Column = 0;
							foreach ($student['courses'] as $core) {
							if ($category != $core['category']) {
								if (in_array($school_id, [33]) && $student['department_id'] == 1) {
									echo "<tr>
					<td colspan=\"20\" style=\"background-color: #f5e1b9\"><b>&nbsp;</b></td>
				</tr>";
								} else {
									echo "<tr>
					<td colspan=\"20\" style=\"background-color: #f5e1b9\">&nbsp;</td>
				</tr>";
								}
								$category = $core['category'];
							}
							?>
							<tr>
								<td colspan="4" class="bold-right"><?= $core['title']; ?></td>
								<td class="colscenter"><?= $core['marks']; ?></td>
								<td class="colscenter"><?= $core['marks']; ?></td>
								<td class="colscenter bold-right"><?= $core['marks'] * 2; ?></td>
								<?php
								$datas = $core['result'];
								$mCat1 = $datas['cat'][1] ?? null;
								$mCat2 = $datas['cat'][2] ?? null;
								$mCat3 = $datas['cat'][3] ?? null;
								$mCat4 = $mCat1 + $mCat2 + $mCat3;
								$mEx1 = $datas['exam'][1] ?? null;
								$mEx2 = $datas['exam'][2] ?? null;
								$mEx3 = $datas['exam'][3] ?? null;
								$mEx4 = $mEx1 + $mEx2 + $mEx3;
								$cat1Echec = ($mCat1 < ($core['marks'] / 2) && !strlen($mCat1) == 0) ? 'fail' : '';
								$cat2Echec = ($mCat2 < ($core['marks'] / 2) && !strlen($mCat2) == 0) ? 'fail' : '';
								$cat3Echec = ($mCat3 < ($core['marks'] / 2) && !strlen($mCat3) == 0) ? 'fail' : '';
								$cat4Echec = ($mCat4 < ($core['marks'] * 3 / 2) && !strlen($mCat4) == 0) ? 'fail' : '';
								$Exam1Echec = ($mEx1 < ($core['marks'] / 2) && !strlen($mEx1) == 0) ? 'fail' : '';
								$Exam2Echec = ($mEx2 < ($core['marks'] / 2) && !strlen($mEx2) == 0) ? 'fail' : '';
								$Exam3Echec = ($mEx3 < ($core['marks'] / 2) && !strlen($mEx3) == 0) ? 'fail' : '';
								$Exam4Echec = ($mEx4 < ($core['marks'] * 3 / 2) && !strlen($mEx4) == 0) ? 'fail' : '';
								$total1 = $mEx1 + $mCat1;
								$total2 = $mEx2 + $mCat2;
								$total3 = $mEx3 + $mCat3;
								$total4 = $total1 + $total2 + $total3;
								$total1Echec = ($total1 < $core['marks'] && (!strlen($mEx1) == 0 || !strlen($mCat1) == 0)) ? 'fail' : '';
								$total2Echec = ($total2 < $core['marks'] && (!strlen($mEx2) == 0 || !strlen($mCat2) == 0)) ? 'fail' : '';
								$total3Echec = ($total3 < $core['marks'] && (!strlen($mEx3) == 0 || !strlen($mCat3) == 0)) ? 'fail' : '';
								$total4Echec = ($total4 < $core['marks'] * 3 && (!strlen($mEx4) == 0 || !strlen($mCat4) == 0)) ? 'fail' : '';
								$cm1 = strlen($mCat1) == 0 ? '-' : number_format($mCat1, 1);
								$em1 = strlen($mEx1) == 0 ? '-' : number_format($mEx1, 1);
								$tm1 = (strlen($mEx1) == 0 && strlen($mCat1) == 0) ? '-' : number_format($total1, 1);
								$cm2 = strlen($mCat2) == 0 ? '-' : number_format($mCat2, 1);
								$em2 = strlen($mEx2) == 0 ? '-' : number_format($mEx2, 1);
								$tm2 = (strlen($mEx2) == 0 && strlen($mCat2) == 0) ? '-' : number_format($total2, 1);
								$cm3 = strlen($mCat3) == 0 ? '-' : number_format($mCat3, 1);
								$em3 = strlen($mEx3) == 0 ? '-' : number_format($mEx3, 1);
								$tm3 = (strlen($mEx3) == 0 && strlen($mCat3) == 0) ? '-' : number_format($total3, 1);
								$cm4 = strlen($mCat4) == 0 ? '-' : number_format($mCat4, 1);
								$em4 = strlen($mEx4) == 0 ? '-' : number_format($mEx4, 1);
								$tm4 = (strlen($mEx4) == 0 && strlen($mCat4) == 0) ? '-' : number_format($total4, 1);
								echo "<td class='$cat1Echec'>" . $cm1 . "</td>
								      <td class='$Exam1Echec'>" . $em1 . "</td>
									  <td class='$total1Echec bold-right'>" . $tm1 . "</td>
									  <td class='$cat2Echec'>" . $cm2 . "</td>
								      <td class='$Exam2Echec'>" . $em2 . "</td>
									  <td class='$total2Echec bold-right'>" . $tm2 . "</td>
									  <td class='$cat3Echec'>" . $cm3 . "</td>
								      <td class='$Exam3Echec'>" . $em3 . "</td>
									  <td class='$total3Echec bold-right'>" . $tm3 . "</td>
									  <td class='$cat4Echec'>" . $cm4 . "</td>
								      <td class='$Exam4Echec'>" . $em4 . "</td>
									  <td class='$total4Echec bold-right'>" . $tm4 . "</td>
									  <td></td>
									  </tr>";
								$cat += $core['marks'];
								$exam += $core['marks'];
								$total = $cat + $exam;
								$totalCat1Column += $mCat1;
								$totalCat2Column += $mCat2;
								$totalCat3Column += $mCat3;
								$totalCat4Column += $mCat1 + $mCat2 + $mCat3;
								$totalExam1Column += $mEx1;
								$totalExam2Column += $mEx2;
								$totalExam3Column += $mEx3;
								$totalExam4Column += $mEx1 + $mEx2 + $mEx3;
								}; ?>
							<tr>
								<?php
								$cat1GEchec = $totalCat1Column < ($cat / 2) ? 'fail' : '';
								$cat2GEchec = $totalCat2Column < ($cat / 2) ? 'fail' : '';
								$cat3GEchec = $totalCat3Column < ($cat / 2) ? 'fail' : '';
								$cat4GEchec = $totalCat4Column < ($cat / 2) ? 'fail' : '';
								$Exam1GEchec = $totalExam1Column < ($cat / 2) ? 'fail' : '';
								$Exam2GEchec = $totalExam2Column < ($cat / 2) ? 'fail' : '';
								$Exam3GEchec = $totalExam3Column < ($cat / 2) ? 'fail' : '';
								$Exam4GEchec = $totalExam4Column < ($cat / 2) ? 'fail' : '';
								$total1GEchec = ($totalExam1Column + $totalCat1Column) < $cat ? 'fail' : '';
								$total2GEchec = ($totalExam2Column + $totalCat2Column) < $cat ? 'fail' : '';
								$total3GEchec = ($totalExam3Column + $totalCat3Column) < $cat ? 'fail' : '';
								$total4GEchec = ($totalExam4Column + $totalCat4Column) < $cat ? 'fail' : '';
								?>
								<td colspan="4" class="bold-right"><b><?= lang("app.total"); ?> </b></td>
								<td class="colscenter"><?= $cat; ?></td>
								<td class="colscenter"><?= $exam; ?></td>
								<td class="colscenter bold-right"><?= ($cat + $exam); ?></td>
								<td class="<?= $cat1GEchec; ?>"><?= number_format($totalCat1Column, 1); ?></td>
								<td class="<?= $Exam1GEchec; ?>"><?= number_format($totalExam1Column, 1); ?></td>
								<td class="<?= $total1GEchec; ?> bold-right"><?= number_format(($totalExam1Column + $totalCat1Column), 1); ?></td>
								<td class="<?= $cat2GEchec; ?>"><?= number_format($totalCat2Column, 1); ?></td>
								<td class="<?= $Exam2GEchec; ?>"><?= number_format($totalExam2Column, 1); ?></td>
								<td class="<?= $total2GEchec; ?> bold-right"><?= number_format(($totalExam2Column + $totalCat2Column), 1); ?></td>
								<td class="<?= $cat3GEchec; ?>"><?= number_format($totalCat3Column, 1); ?></td>
								<td class="<?= $Exam3GEchec; ?>"><?= number_format($totalExam3Column, 1); ?></td>
								<td class="<?= $total3GEchec; ?> bold-right"><?= number_format(($totalExam3Column + $totalCat3Column), 1); ?></td>
								<td class="<?= $cat4GEchec; ?>"><?= number_format($totalCat4Column, 1); ?></td>
								<td class="<?= $Exam4GEchec; ?>"><?= number_format($totalExam4Column, 1); ?></td>
								<td class="<?= $total4GEchec; ?>  bold-right"><?= number_format(($totalExam4Column + $totalCat4Column), 1); ?></td>
								<td></td>
							</tr>
							<tr style="background-color: #f5e1b9">
								<?php
								$perc1 = ($totalExam1Column + $totalCat1Column) * 100 / $total;
								$perc2 = ($totalExam2Column + $totalCat2Column) * 100 / $total;
								$perc3 = ($totalExam3Column + $totalCat3Column) * 100 / $total;
								$perc4 = ($perc1 + $perc2 + $perc3) / 3;
								?>
								<td colspan="7"><b><?= lang("app.per"); ?> </b></td>
								<td colspan="3"
									class="colscenter <?= $perc1 < 50 ? 'fail' : ''; ?> bold-right"><?= number_format($perc1, 1); ?>
									%
								</td>
								<td colspan="3"
									class="colscenter <?= $perc2 < 50 ? 'fail' : ''; ?> bold-right"><?= number_format($perc2, 1); ?>
									%
								</td>
								<td colspan="3"
									class="colscenter <?= $perc3 < 50 ? 'fail' : ''; ?> bold-right"><?= number_format($perc3, 1); ?>
									%
								</td>
								<td colspan="4"
									class="colscenter <?= $perc4 < 50 ? 'fail' : ''; ?>"><?= number_format($perc4, 1); ?>
									%
								</td>
							</tr>
							<tr style="background-color: #f5e1b9">
								<td colspan="7"><b><?= lang("app.position"); ?> </b></td>
								<td colspan="3"
									class="colscenter bold-right"><?= array_search($student['id'], $students['terms_total']['1']) + 1; ?>
									out of <?= (count($students) - 1); ?></td>
								<td colspan="3"
									class="colscenter bold-right"><?= array_search($student['id'], $students['terms_total']['2']) + 1; ?>
									out of <?= (count($students) - 1); ?></td>
								<td colspan="3"
									class="colscenter bold-right"><?= array_search($student['id'], $students['terms_total']['3']) + 1; ?>
									out of <?= (count($students) - 1); ?></td>
								<td colspan="4" class="colscenter"><?= $i; ?> out of <?= (count($students) - 1); ?></td>
							</tr>
							<tr>
								<td colspan="4"><b><?= lang("app.tSignture"); ?> </b></td>
								<td colspan="16" style="height: 40px"></td>
							</tr>
							<tr>
								<td colspan="4"><b><?= lang("app.pSignture"); ?> </b></td>
								<td colspan="16" style="height: 40px"></td>
							</tr>
							<tr style="height: 100px;">
								<td colspan="10" style="position: relative;">
									<?php
									if (!$isFinalClass) {
										?>
										<div style="position: absolute;top: 20px;left: 20px">
											<p style="font-weight: bold;margin-bottom: 0">Deliberation Decision:
												<span class="badge badge-dark" style="font-weight: normal">
											<?php
											if (!empty($student['decision'])) {
												echo verdictText($student['decision']);
											} else {
												echo 'PENDING...';
											}
											?>
										</span></p>
										</div>
										<?php
									}
									?>
								</td>
								<td colspan="10" class="" style="text-align: left;">
									<div style="width: 350px;float: right">
										<div style="width:100%;">
											<div style="text-align: center;margin-top: 20px;margin-bottom: 10px">
												<strong style="text-align: left;float: left;width:100%;">
													Done at <strong><?= $school_address; ?></strong>
													on <?= date('d / m / Y'); ?></strong>
											</div>
										</div>
										<div style="width:100%;float: left">
											<p style="font-weight: bold;margin-top: 20px"><?= $head_master; ?></p>
											<div class="spacer" style="height: 0"></div>
											<label style="font-weight: normal">School <?= $head_master_gender == 'F' ? 'Headmistress' : 'Headmaster'; ?></label>
										</div>
										<div class="spacer" style="height: 20px"></div>
										<div style="width:100%;margin: 20px 0;float: left">
											<p>Signature and stamp</p>
											<div class="spacer" style="height: 20px"></div>
											<p style="margin-bottom: 60px">
												<?php
												if (strlen($headmaster_signature) > 5) {
													?>
													<img src="<?= base_url('assets/images/signatures/' . $headmaster_signature); ?>"
														 style="height: 70px;"
														 alt="Headmaster signature">
													<?php
												} else {
													echo "----------------------------------------";
												}
												?>
											</p>
										</div>
									</div>
								</td>
							</tr>
							</tbody>
							<!--Table body-->
						</table>
					</div>
					<!--Table-->
					<div class="col-md-12 col-sm-12 ">
						<footer class="pull-right" style="color: darkgrey"><?= lang("app.printedBy"); ?> </footer>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
	$i++;
} ?>
