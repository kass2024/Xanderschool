<style>
	.dataTables_wrapper {
		width: 100%;
	}

	.class-statistics {
		width: 100%;
		display: flex;
		justify-content: center;
	}

	.class-statistics > h5 {
		display: inline-block;
		float: left;
		margin: 5px;
		border: 1px solid;
		border-radius: 5px;
		padding: 19px;
		background-color: white;
		box-shadow: 1px 2px 3px 1px #3333;
	}
</style>
<div class="row">
	<?php
	$pdf = $pdf??false;
	if(!$pdf) {
	if (isset($_SESSION['success'])) {
		?>
		<div class="alert alert-success col-sm-12">
			<p class="alert-heading mb-0"><?= $_SESSION['success']; ?></p>
		</div>
		<?php
	}
	?>
	<?php
	if (isset($_SESSION['error'])) {
		?>
		<div class="alert alert-danger col-sm-12">
			<p class="alert-heading mb-0"><?= $_SESSION['error']; ?></p>
		</div>
		<?php
	}
	?>
	<div class="row">
		<form action="<?= base_url('proclamation_list'); ?>" method="post">
			<div class="form-group mr-2 pull-left">
				<label><?= lang("app.year"); ?> :</label>
				<select class="select2" id="select_year" name="year" required>
					<option disabled selected><?= lang("app.academicYear"); ?> </option>
					<?php
					foreach ($years as $y):
						echo "<option value='{$y['id']}' " . ($y['id'] == ($year ?? '') ? 'selected' : '') . ">{$y['title']}</option>";
					endforeach;
					?>
				</select>
			</div>
			<div class="form-group mr-2 pull-left">
				<label><?= lang("app.term"); ?> :</label>
				<select class="select2" id="select_term" name="term" required>
					<option disabled <?=("" == ($term ?? '') ? 'selected' : '');?>><?= lang("app.selectTerm"); ?> </option>
					<option value="1" <?=("1" == ($term ?? '') ? 'selected' : '');?>><?= lang("app.term1"); ?> </option>
					<option value="2" <?=("2" == ($term ?? '') ? 'selected' : '');?>><?= lang("app.term2"); ?> </option>
					<option value="3" <?=("3" == ($term ?? '') ? 'selected' : '');?>><?= lang("app.term3"); ?> </option>
					<option value="4" <?=("4" == ($term ?? '') ? 'selected' : '');?>><?= lang("app.annual"); ?> </option>
				</select>
			</div>
			<div class="form-group mr-2 pull-left">
				<label><?= lang("app.sClass"); ?>:</label>
				<select class="form-control select2" name="class" id="select_class" required>
					<option selected disabled><?= lang("app.selectClass"); ?></option>
					<?php foreach ($classes as $classe) {
						?>
						<option value="<?= $classe['id']; ?>" <?= $classe['id'] == ($class_id ?? '') ? 'selected' : ''; ?>
								data-id=""> <?= $classe['level_name']; ?> <?= $classe['code']; ?> <?= $classe['title']; ?></option>
						<?php
					}
					?>
				</select>
			</div>
			<div class="form-group mr-2 pull-left" style="width: 200px">
				<label><?= lang("app.currentYear"); ?>:</label>
				<input type="text" class="form-control" readonly value="<?= $academic_year_title; ?>">
			</div>
			<div class="pull-left">
				<input type="submit" class="btn btn-success" onclick="$('form').prop('target','_self')" style="margin-top: 30px" value="Continue">
			</div>
			<div class="pull-left" style="margin-left: 10px">
				<button type="submit" name="pdf" onclick="$('form').prop('target','_blank')" class="btn btn-danger" style="margin-top: 30px" value="Export"><i class="fa fa-file-pdf"></i>
					 Export
				</button>
			</div>
		</form>
	</div>
	<hr style="width: 100%;margin-top: 0"/>
	<?php
	} else  {
		?>
			<style>
				table{
					width: 100%;
					border: 1px solid;
					border-collapse: collapse;
				}
				td,th{
					border: 1px solid;
				}
				.badge{
					border: 1px solid black;
					padding: 3px 5px;
					border-radius: 3px;
					margin: 2px;
					display: inline-block;
					font-size: 9pt;
				}
				.badge-success{
					background-color: #0ba360;
					color: white;
				}
				.badge-danger{
					background-color: #c0071d;
					color: white;
				}
				.badge-warning{
					background-color: #c06a07;
					color: white;
				}
			</style>
		<div style="width: 100%;">
			<div style="float: left;width:50%">
				<span><b><?= lang("app.republic"); ?></b></span><br>
				<span><b><?= lang("app.ministry"); ?> </b></span><br>
				<span><strong><?= $school_name; ?></strong></span><br>
				<span><b><?= lang("app.mail"); ?> </b> : <?= $school_email; ?></span><br>
				<span><b><?= lang("app.phone"); ?> </b>  : <?= $school_phone; ?></span><br>
			</div>
			<div style="margin-top: 10px;margin-right: 10px;float: right;">
				<div>
					<img src="<?= base_url('assets/images/logo/' . $school_logo); ?>"
						 style="width: 130px"><br>
				</div>
			</div>
			<br>
		</div>
		<h1 style="text-align: center;width: 100%;float: left;border-bottom: 1px solid #000000"><?= lang("app.proclamationList"); ?></h1>
			<?php
	}
	if (isset($students)) {
		if (count($students) == 0) {
			echo "<blockquote style='text-align: center;width: 62%;border-right: 4px solid #a6b094;border-left: 4px solid #a6b094;
margin: 67px auto;font-size: 15pt;'>No pending deliberation in this class</blockquote>";
			return;
		}
		?>
		<form method="post" action="<?= base_url('process_deliberation'); ?>" id="deliberation-form-0">
			<table class="table table-hover table-striped table-bordered dataTable dtr-inline" id="deliberation">
				<thead>
				<tr>
					<th>No</th>
					<th>Reg No</th>
					<th>Names</th>
					<th>Gender</th>
<!--					<th>Failed courses</th>-->
					<th>Percentages</th>
					<th>Discipline</th>
					<?php
					if ($term==4){
						echo "<th>Decision</th>";
					}
					?>
				</tr>
				</tr>
				</thead>
				<tbody>
				<?php
				$a = 1;
				$home = new \App\Controllers\Home();
				$average = 0;
				$male = 0;
				$female = 0;
				$firstMale = 0;
				$firstFemale = 0;
				$lastMale = 0;
				$lastFemale = 0;
				$maleCount = 0;
				$femaleCount = 0;
				foreach ($students as $student) {
					$failedCourses = '';
					$failedCoursesCount = 0;
					$total1 = 0;
					$total2 = 0;
					$total3 = 0;
					$max_total = 0;
					$single_total1 = 0;
					$single_total2 = 0;
					$single_total3 = 0;
					$perc1 = 0;
					$perc2 = 0;
					$perc3 = 0;
					foreach ($courses as $core) {
						$termCount = 0;
						$result = $home->__result($core['id'], $student['id'], $term, $year);
						$mCat1 = $result['cat'][1] ?? null;
						$mExam1 = $result['exam'][1] ?? null;
						$mCat2 = $result['cat'][2] ?? null;
						$mExam2 = $result['exam'][2] ?? null;
						$mCat3 = $result['cat'][3] ?? null;
						$mExam3 = $result['exam'][3] ?? null;
						$tot = $mCat1 + $mExam1 + $mCat2 + $mExam2 + $mCat3 + $mExam3;
						if (in_array('1', explode(',', $core['term1']))) {
							$single_total1 += $core['marks'] * 2;
							$perc1 = ($mCat1 + $mExam1) * 100 / ($core['marks'] * 2);
							$termCount++;
						}
						if (in_array('2', explode(',', $core['term1']))) {
							$single_total2 += $core['marks'] * 2;
							$perc2 = ($mCat2 + $mExam2) * 100 / ($core['marks'] * 2);
							$termCount++;
						}
						if (in_array('3', explode(',', $core['term1']))) {
							$single_total3 += $core['marks'] * 2;
							$perc3 = ($mCat3 + $mExam3) * 100 / ($core['marks'] * 2);
							$termCount++;
						}
						$total1 += $mCat1 + $mExam1;
						$total2 += $mCat2 + $mExam2;
						$total3 += $mCat3 + $mExam3;

						$max_total += $core['marks'] * 2 * 3;
						$perc = ($perc1 + $perc2 + $perc3) / $termCount;
						if ($perc < 50) {
							//failed
							$failedCoursesCount++;
							$failedCourses .= "<label class='badge badge-danger mr-2'>{$core['code']}:" . number_format($perc, 1) . "% </label>";
						}
					}
					$percentage1 = $total1 * 100 / $single_total1;
					$percentage2 = $total2 * 100 / $single_total2;
					$percentage3 = $total3 * 100 / $single_total3;
					$percentage = ($percentage1 + $percentage2 + $percentage3) / 3;
					$term1Disc = extractDisciplineMarks($student['displine_marks'], 1);
					$term2Disc = extractDisciplineMarks($student['displine_marks'], 2);
					$term3Disc = extractDisciplineMarks($student['displine_marks'], 3);
					$discipline = (($discipline_max * 3) - ($term1Disc + $term2Disc + $term3Disc)) * 100 / ($discipline_max * 3);
					$average += $percentage;
					if ($student['sex'] == 'M') {
						$male += $percentage;
						$maleCount++;
						if ($firstMale < $percentage) {
							$firstMale = $percentage;
						}
						if ($lastMale > $percentage || $lastMale == 0) {
							$lastMale = $percentage;
						}
					} else if ($student['sex'] == 'F') {
						$female += $percentage;
						$femaleCount++;
						if ($firstFemale < $percentage) {
							$firstFemale = $percentage;
						}
						if ($lastFemale > $percentage || $lastFemale == 0) {
							$lastFemale = $percentage;
						}
					}
					?>
					<tr>
						<td><?= $a; ?></td>
						<td><?= $student['regno']; ?></td>
						<td><?= $student['fname'] . ' ' . $student['lname']; ?></td>
						<td><?= $student['sex']; ?></td>
						<td>
							<?php
							if($term == 1 || $term == 4){
							?>
							<label class='badge <?= $percentage1 > 50 ? 'badge-success' : 'badge-danger'; ?> mr-2'>Term
								1: <?= number_format($percentage1, 1); ?>% </label>
							<?php
							}
							if($term == 2 || $term == 4){
							?>
							<label class='badge <?= $percentage2 > 50 ? 'badge-success' : 'badge-danger'; ?> mr-2'>Term
								2: <?= number_format($percentage2, 1); ?>% </label>
							<?php
							}
							if($term == 3 || $term == 4){
							?>
							<label class='badge <?= $percentage3 > 50 ? 'badge-success' : 'badge-danger'; ?> mr-2'>Term
								3: <?= number_format($percentage3, 1); ?>% </label>
							<?php
							}
							if($term == 4){
							?>
							<label class='badge <?= $percentage > 50 ? 'badge-success' : 'badge-danger'; ?> mr-2'>Overall: <?= number_format($percentage, 1); ?>
								% </label>
								<?php
							}
							?>
						</td>
						<td>
							<label class='badge <?= $discipline > 50 ? 'badge-success' : 'badge-danger'; ?> mr-2'>Overall: <?= number_format($discipline, 1); ?>
								% </label>
						</td>
						<?php
						if ($term==4){
							echo "<td>".verdictText($student['decision'])."</td>";
						}
						?>
					</tr>
					<?php
					$a++;
				}
				?>
				</tbody>
			</table>
			<div style="margin: 10px;padding: 10px;border: 1px solid;border-radius: 4px;display: inline-block;">
				<div style="width: 100%;float: left">
					<h4 style="float:left;">Class summary</h4>
					<?php
					if(!$pdf) {
					?>
					<a class="btn btn-sm btn-primary" style="float: right" target="_blank"
					   href="<?= base_url('student_report_slip/' . $class_id . '/' . $year . '/4/'); ?>">
						View annual report</a>
					<?php
					}
					?>
				</div>
				<hr style="width: 100%;float: left;" />
				<div class="class-statistics">
					<h5>Class average: <?= number_format($average / count($students), 1); ?>%</h5>
					<h5>Male: <?= ($maleCount==0?0:number_format($male / $maleCount, 1)); ?>% <span
								class="badge badge-dark"><?= $maleCount; ?></span></h5>
					<h5>Female: <?= ($femaleCount==0?0:number_format($female / $femaleCount, 1)); ?>% <span
								class="badge badge-success"><?= $femaleCount; ?></span></h5>
					<h5>First in Male: <?= number_format($firstMale, 1); ?>%</h5>
					<h5>First in Female: <?= number_format($firstFemale, 1); ?>%</h5>
					<h5>Last in Male: <?= number_format($lastMale, 1); ?>%</h5>
					<h5>Last in Female: <?= number_format($lastFemale, 1); ?>%</h5>
				</div>
			</div>
		</form>
		<?php
	}
	?>
</div>
