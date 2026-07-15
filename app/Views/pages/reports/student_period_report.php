
<style>
	.tablepage {
		width: 100%;

	}
	.colscenter{
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
		padding: 1px 5px;
		border: 1px solid #777777;
		font-size: 11pt;
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

	.no-printable {
		margin: 15px !important;
		width: calc(100% - 30px) !important;
	}
	.fail{
		color:red;text-decoration: underline
	}
</style>
<?php
$i=1;
$student_reg=isset($_GET['student'])?$_GET['student']:false;
if (count($students)==0)echo "<h1 style=''>".lang("app.noStudentFound")."</h1>";
$result = new \App\Controllers\Home();
//print_r($students); die();
foreach ($students as $student) {
	if($student['id']==$student_reg || $student_reg===false){
	?>
		<div style="margin-top: 15px;width: 100%;float:left;" class="<?= $pdf ? 'printable' : 'no-printable'; ?>">
			<div class="col-md-12 col-sm-12 pull-left" style="margin: 20px 15px;width: 98%">
				<div class="pull-left" style="margin: 10px 0 10px 10px;width: 47%;font-size: 12pt;">
					<span><b><?= lang("app.republic"); ?> </b></span><br>
					<span><b><?= lang("app.ministry"); ?> </b></span><br>
					<span style="font-weight: bold"><?= $school_name; ?></span><br>
					<img src="<?= base_url('assets/images/logo/' . $school_logo); ?>" style="height: 70px;"
						 alt="School Logo"><br>
					<span><b><?= lang("app.mail"); ?></b> : <?= $school_email; ?></span><br>
					<span><b><?= lang("app.phone"); ?> </b>  : <?= $school_phone; ?></span><br>
					<?php
					if(strlen($school_pobox)>2){
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
				<div class="pull-right" style="margin-top: 10px;margin-right:10px;width: auto;min-width: 250px;font-size: 12pt;">

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
					<h4 style="text-align: center;float: left;width: 100%;margin: 20px 0 16px 0;font-size: 16px;font-weight: bold;">
						PERIODIC REPORT</h4>
					<table class="tablepage" border="1">
						<!--Table head-->
						<tbody id="disciplineTable">
						<tr>
							<td colspan="4" rowspan="2" width="40%" style="text-transform: uppercase;font-weight: bold"><?= lang("app.subject"); ?> </td>
							<td colspan="2" class="colscenter"><?= lang("app.maxim"); ?> </td>
							<td colspan="2" class="colscenter"><?= lang("app.".termToStr($term)); ?> </td>
						</tr>
						<tr>
							<td class="colscenter">Period MAX</td>
							<td class="colscenter"><?= lang("app.tot"); ?> </td>
							<td class="colscenter">Period <?=$period;?> </td>
							<td class="colscenter"><?= lang("app.tot"); ?> </td>
						</tr>
<!--						<tr>-->
<!--							<td colspan="4">--><?//= lang("app.discipline"); ?><!-- </td>-->
<!--							<td colspan="2" class="colscenter">--><?//= $discipline_max; ?><!--</td>-->
<!--							<td colspan="2" class="colscenter">--><?//= $discipline_max - $student['displine_marks']; ?><!--</td>-->
<!--						</tr>-->
						<?php
						$category = "";
						$cat = 0;
						$exam = 0;
						$totalCatColumn = 0;
						$totalExamColumn = 0;
						foreach ($student['courses'] as $core) {
//						if ($category != $core['category']) {
//							if(in_array($school_id,[33]) && $student['department_id']==1){
//								echo "<tr>
//					<td colspan=\"10\" style=\"background-color: #f5e1b9\"><b>&nbsp;</b></td>
//				</tr>";
//							}else {
//								echo "<tr>
//					<td colspan=\"10\" style=\"background-color: #f5e1b9\"><b>" . strtoupper($core['category']) . "</b></td>
//				</tr>";
//							}
//							$category = $core['category'];
//						}
//						?>
						<tr>
							<td colspan="4"><?= $core['title']; ?></td>
							<td class="colscenter"><?= $core['marks'] ; ?></td>
							<td class="colscenter"><?= $core['marks']; ?></td>
							<?php
							$datas = $core['result'];
							$catEchec = ($datas['marks']<($core['marks']/2) && !strlen($datas['marks'])==0)?'fail':'';
							$total = $datas['marks'];
							$totalEchec = ($total<$core['marks']/2 && !strlen($datas['marks'])==0)?'fail':'';
							$cm = strlen($datas['marks'])==0?'-':number_format($datas['marks'], 1);
							$tm = strlen($datas['marks'])==0?'-':number_format($total, 1);
							echo "<td class='$catEchec'>" . $cm . "</td>
									  <td class='$totalEchec'>" . $tm . "</td></tr>";
							$cat += $core['marks'];
							$total = $cat;
							$totalCatColumn += $datas['marks'];
							}; ?>
						<tr>
							<?php
							$catGEchec = $totalCatColumn<($cat/2)?'fail':'';
							$totalGEchec = ($totalCatColumn)<$cat/2?'fail':'';
							?>
							<td colspan="4"><b><?= lang("app.total"); ?> </b></td>
							<td class="colscenter"><?= $cat; ?></td>
							<td class="colscenter"><?= ($cat); ?></td>
							<td class="<?=$catGEchec;?>"><?= number_format($totalCatColumn,1); ?></td>
							<td class="<?=$totalGEchec;?>"><?= number_format($totalCatColumn,1); ?></td>
						</tr>
						<tr style="background-color: #f5e1b9">
							<?php
							$perc = ($totalCatColumn)*100/$total;
							?>
							<td colspan="7"><b><?= lang("app.per"); ?> </b></td>
							<td colspan="3" class="colscenter <?=$perc<50?'fail':'';?>"><?=number_format($perc,1); ?>%</td>
						</tr>
						<?php if(strpos($student['level_name'], 'N') === false ){?>
							<tr style="background-color: #f5e1b9">
								<td colspan="7"><b><?= lang("app.position"); ?> </b></td>
								<td colspan="3" class="colscenter"><?=$i;?> out of <?=count($students );?></td>
							</tr>
						<?php } else {}?>
						<tr>
							<td colspan="4"><b><?= lang("app.tSignture"); ?> </b></td>
							<td colspan="6" style="height: 40px"></td>
						</tr>
						<tr>
							<td colspan="4"><b><?= lang("app.pSignture"); ?> </b></td>
							<td colspan="6" style="height: 40px"></td>
						</tr>
						<tr style="height: 100px;">

							<td colspan="10" class="" style="text-align: left;">
								<div style="width: 350px;float: right">
									<div style="width:100%;">
										<div style="text-align: center;margin-top: 20px;margin-bottom: 10px">
											<strong style="text-align: left;float: left;width:100%;">Done at <?=$school_address;?> on <?= date('d / m / Y'); ?></strong>
										</div>
									</div>
									<div style="width:100%;float: left">
										<p style="font-weight: bold;margin-top: 20px"><?= $head_master; ?></p>
										<div class="spacer" style="height: 0"></div>
										<label style="font-weight: normal">School Headmaster</label>
									</div>
									<div class="spacer" style="height: 20px"></div>
									<div style="width:100%;margin: 20px 0;float: left">
										<p>Signature and stamp</p>
										<div class="spacer" style="height: 20px"></div>
										<p style="margin-bottom: 60px">
											<?php
											if (strlen($headmaster_signature)>5){
												?>
												<img src="<?= base_url('assets/images/signatures/' . $headmaster_signature); ?>" style="height: 200px;margin-top:-20px;"
													 alt="Headmaster signature">
												<?php
											}else{
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
}?>
