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
		font-size: 7pt;
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
		font-size: 7pt;
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
		/*page-break-inside: avoid !important;*/
		/*page-break-after: avoid !important;*/
		page-break-before: always;
		width: 100%;
		max-height: 100%;
		margin-top: 0 !important;
	}

	.printable .solid {
		width: 100%;
		height: 100%;
	}

	.no-printable {
		margin: 15px !important;
		width: calc(100% - 30px) !important;
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
if (count($students) === 0) {
	echo "<h1 style=''>" . lang('app.noStudentFound') . '</h1>';
}
$result = new \App\Controllers\Home();
//print_r($students); die();
$courseRows = 0;
foreach ($students as $student) {
	if (!isset($student['id'])) {
		//positional data
		break;
	}
	$courseRows = 0;
	if ($student['id'] === $student_reg || $student_reg === false) {
		?>
		<div style="margin-top:15px;width: 100%;position: relative" class="<?= $pdf ? 'printable' : 'no-printable'; ?>">
			<div class="col-md-12 col-sm-12 pull-left solid" style="position: relative">
				<div style="background:white;padding: 10px;overflow: auto;font-size: 10pt">
					<div class="pull-left" style="width:46.5%;margin-top: 10px">
						<span><b><?= lang('app.republic'); ?> </b></span><br>
						<span><b><?= lang('app.ministry'); ?></b></span><br>
						<span><?= $school_name; ?></span><br>
						<img src="<?= base_url('assets/images/logo/' . $school_logo); ?>" style="height: 90px;" alt=" "><br>
						<span><b><?= lang('app.mail'); ?></b> : <?= $school_email; ?></span><br>
						<span><b><?= lang('app.phone'); ?> </b>  : <?= $school_phone; ?></span><br>
					</div>
					<?php
					if (@getimagesize(base_url('assets/images/profile/' . $student['photo']))) {
						?>
						<div class="pull-left"
							 style="margin-top: 10px;margin-bottom:10px;width: 120px;border:1px solid #555;min-height: 60px">
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
						<span><b><?= lang('app.academicYear'); ?></b> :<?= $academic_year_title; ?></span><br>
						<span><b><?= lang('app.firstName'); ?></b>  : <?= $student['fname']; ?> </span><br>
						<span><b><?= lang('app.lastName'); ?></b>   : <?= $student['lname']; ?></span><br>
						<span><b><?= lang('app.sClass'); ?> </b>   :  <?= $student['level_name'] ?> <?= $student['code'] ?> <?= $student['title'] ?></span><br>
						<span><b><?= lang('app.regNo'); ?></b>   : <?= $student['regno']; ?></span><br>
					</div>
					<?php
					?>
					<table class="tablepage" border="1">
						<tbody id="disciplineTable">
						<tr>
							<td class="colscenter" colspan="8"><?= lang('app.AssementReporrt'); ?> </td>

						</tr>
						<tr>
							<td colspan="2"><?= lang('app.trades'); ?> </td>
							<td class="colscenter" colspan="2"><?= $student['fac_title']; ?></td>
							<td colspan="2"><?= lang('app.qualTitle'); ?> </td>
							<td class="colscenter" colspan="2"><?= $student['department_name']; ?> </td>
						</tr>
						<tr>
							<td colspan="2"><?= lang('app.qualCode'); ?></td>
							<td class="colscenter" colspan="2"><?= $student['code']; ?> </td>
							<td colspan="2"><?= lang('app.rtqf'); ?> </td>
							<td class="colscenter" colspan="2"><?= $student['level_name']; ?> </td>
						</tr>

						<tr>
							<td class="colscenter" colspan="8">
								ANNUAL <?= lang('app.assStatus'); ?> </td>

						</tr>
						<tr>
							<td class="colscenter"><?= lang('app.modCode'); ?> </td>
							<td class="colscenter"><?= lang('app.cmopTitle'); ?> </td>
							<td class="colscenter" style="min-width: 90px"><?= lang('app.creditHour'); ?> </td>
							<td class="colscenter" style="width: 40px"><?= lang('app.fAssess'); ?> </td>
							<td class="colscenter" style="width: 30px"><?= lang('app.iAssess'); ?> </td>
							<td class="colscenter"><?= lang('app.avg'); ?> </td>
							<td class="colscenter"><?= lang('app.reAssess'); ?> </td>
							<td class="colscenter"><?= lang('app.obser'); ?> </td>
						</tr>
						<?php
						//fetch courses
						$category = '';
						$cat = 0;
						$exam = 0;
						$totalCatColumn = 0;
						$totalExamColumn = 0;
						$credit = 0;
						$markCounter = 0;
						$total1 = 0;
						$total2 = 0;
						$total3 = 0;
						$max_total1 = 0;
						$max_total2 = 0;
						$max_total3 = 0;
						$term1Html = '';
						$term2Html = '';
						$term3Html = '';
						$totalCat1Column = 0;
						$totalCat2Column = 0;
						$totalCat3Column = 0;
						$totalCat4Column = 0;
						$totalExam1Column = 0;
						$totalExam2Column = 0;
						$totalExam3Column = 0;
						$totalExam4Column = 0;
						foreach ($student['courses'] as $core) {
							if ($category !== $core['category']) {
								//                              echo "<tr>
								//                  <td colspan=\"10\" style=\"background-color: #7c7c7c\"><b>" . strtoupper($core['category']) . "</b></td>
								//              </tr>";
								$category = $core['category'];
							}
							$datas = $core['result'];

							if (in_array('1', explode(',', $core['term1']))) {
								$max_total1 += $core['marks'];
								$term1Html .= wdaTermMarks($student, $core, $datas, 1, $year);
								$totalCat1Column += $datas['cat'][1] ?? 0;
								$totalExam1Column += $datas['exam'][1] ?? 0;
								$courseRows++;
							}
							if (in_array('2', explode(',', $core['term1']))) {
								$max_total2 += $core['marks'];
								$term2Html .= wdaTermMarks($student, $core, $datas, 2, $year);
								$totalCat2Column += $datas['cat'][2] ?? 0;
								$totalExam2Column += $datas['exam'][2] ?? 0;
								$courseRows++;
							}
							if (in_array('3', explode(',', $core['term1']))) {
								$max_total3 += $core['marks'];
								$term3Html .= wdaTermMarks($student, $core, $datas, 3, $year);
								$totalCat3Column += $datas['cat'][3] ?? 0;
								$totalExam3Column += $datas['exam'][3] ?? 0;
								$courseRows++;
							}
							$credit += $core['credit'];
							$markCounter++;
						}
						$term1Html .= '</tr>
						<tr>
							<td colspan="3" style="text-align: center"><b>AVERAGE</b></td>
							<td class="colscenter">' . number_format(($totalCat1Column * 100 / $max_total1), 1) . '</td>
							<td class="colscenter">' . number_format(($totalExam1Column * 100 / $max_total1), 1) . '</td>
							<td class="colscenter">' . number_format(($totalExam1Column + $totalCat1Column) * 100 / ($max_total1 * 2), 1) . '
								%
							</td>
							<td class="colscenter">&nbsp;</td>
							<td class="colscenter">' . (array_search($student['id'], $students['terms_total']['1']) + 1) . ' out of ' . (count($students) - 1) . '</td>
						</tr>
						<tr>
							<td colspan="7" style="text-align: center"><b>CONDUCT</b></td>
							<td class="colscenter">' . ($discipline_max - extractDisciplineMarks($student['displine_marks'], 1)) . '/' . $discipline_max . '</td>
						</tr>';
						$term2Html .= '</tr>
						<tr>
							<td colspan="3" style="text-align: center"><b>AVERAGE</b></td>
							<td class="colscenter">' . number_format(($totalCat2Column * 100 / $max_total2), 1) . '</td>
							<td class="colscenter">' . number_format(($totalExam2Column * 100 / $max_total2), 1) . '</td>
							<td class="colscenter">' . number_format(($totalExam2Column + $totalCat2Column) * 100 / ($max_total2 * 2), 1) . '
								%
							</td>
							<td class="colscenter">&nbsp;</td>
							<td class="colscenter">' . (array_search($student['id'], $students['terms_total']['2']) + 1) . ' out of ' . (count($students) - 1) . '</td>
						</tr>
						<tr>
							<td colspan="7" style="text-align: center"><b>CONDUCT</b></td>
							<td class="colscenter">' . ($discipline_max - extractDisciplineMarks($student['displine_marks'], 2)) . '/' . $discipline_max . '</td>
						</tr>';
						$term3Html .= '</tr>
						<tr>
							<td colspan="3" style="text-align: center"><b>AVERAGE</b></td>
							<td class="colscenter">' . number_format(($totalCat3Column * 100 / $max_total3), 1) . '</td>
							<td class="colscenter">' . number_format(($totalExam3Column * 100 / $max_total3), 1) . '</td>
							<td class="colscenter">' . number_format(($totalExam3Column + $totalCat3Column) * 100 / ($max_total3 * 2), 1) . '
								%
							</td>
							<td class="colscenter">&nbsp;</td>
							<td class="colscenter">' . (array_search($student['id'], $students['terms_total']['3']) + 1) . ' out of ' . (count($students) - 1) . '</td>
						</tr>
						<tr>
							<td colspan="7" style="text-align: center"><b>CONDUCT</b></td>
							<td class="colscenter">' . ($discipline_max - extractDisciplineMarks($student['displine_marks'], 3)) . '/' . $discipline_max . '</td>
						</tr>';
						$annualCat = $totalCat1Column + $totalCat2Column + $totalCat3Column;
						$annualExam = $totalExam1Column + $totalExam2Column + $totalExam3Column;
						$annualTotal = $max_total1 + $max_total2 + $max_total3;
						$annualDiscipline = (($discipline_max * 3) - (extractDisciplineMarks($student['displine_marks'], 1)
										+ extractDisciplineMarks($student['displine_marks'], 2)
										+ extractDisciplineMarks($student['displine_marks'], 3)
								));
						$annual = '</tr>
						<tr>
							<td colspan="3" style="text-align: center"><b>ANNUAL AVERAGE</b></td>
							<td class="colscenter">' . number_format(($annualCat * 100 / $annualTotal), 1) . '</td>
							<td class="colscenter">' . number_format(($annualExam * 100 / $annualTotal), 1) . '</td>
							<td class="colscenter">' . number_format(($annualCat + $annualExam) * 100 / ($annualTotal * 2), 1) . '
								%
							</td>
							<td class="colscenter">&nbsp;</td>
							<td class="colscenter">' . $i . ' out of ' . (count($students) - 1) . '</td>
						</tr>
						<tr>
							<td colspan="7" style="text-align: center"><b>ANNUAL CONDUCT</b></td>
							<td class="colscenter">' . $annualDiscipline . '/' . ($discipline_max * 3) . '</td>
						</tr>';
						echo $term1Html;
						echo $term2Html;
						echo $term3Html;
						echo $annual;
						?>

						<tr>
							<td colspan="8"><b><?= lang('app.feedBac'); ?> </b></td>
						</tr>
						<tr>
							<td colspan="8"><b><?= lang('app.teacherComment'); ?> </b></td>
						</tr>
						<tr>
							<td colspan="2"><b><?= lang('app.remark'); ?> :</b>
								<p><?= lang('app.remarkIntro'); ?></p>
								<?php
								if (!$isFinalClass) {
									?>
									<p style="font-weight: bold;margin-bottom: 0">Deliberation Decision:
										<span class="badge badge-dark" style="font-weight: normal;font-size: 8pt">
											<?php
											if (!empty($student['decision'])) {
												echo verdictText($student['decision']);
											} else {
												echo 'PENDING...';
											}
											?>
										</span></p>
									<?php
								}
								?>
							</td>
							<td colspan="6">
								<p style="margin-top: 2px;"><?= lang('app.done'); ?>
									<b> <?= lang('app.on'); ?> <?= date('M-d-Y'); ?></b></p>
								<p>
									<?= lang('app.SchoolDirector'); ?> : <b></b><br>
									<b><?= $head_master; ?></b><br>
									<?= lang('app.SignatureStamp'); ?>
									<br/>
									<?php
									if (strlen($headmaster_signature) > 5) {
										?>
										<img src="<?= base_url('assets/images/signatures/' . $headmaster_signature); ?>"
											 style="height: 70px;"
											 alt="Headmaster signature">
										<?php
									} else {
										echo '----------------------------------------';
									}
									?>
								</p>
							</td>
						</tr>
						</tbody>
						<!--Table body-->
					</table>
					<!--Table-->
					<footer style="
  position: absolute;
  bottom: 20px;
  right: 30px;
  font-size: 12px;
  font-style: italic;
  color: #555;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  opacity: 0.8;
">
  <i>Generated by IOTXAD</i>
</footer>

				</div>
			</div>
		</div>
		<?php
	}
	$i++;
}
if ($courseRows > 40) {
	; ?>
	<style>
		th, td {
			font-size: 6pt !important;
		}
	</style>
	<?php
}
?>
