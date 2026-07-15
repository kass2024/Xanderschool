<style>
	.dataTables_wrapper {
		width: 100%;
	}
</style>
<div class="row">
	<?php
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
		<form action="<?= base_url('class-deliberation'); ?>" method="post">
			<div class="form-group mr-2 pull-left">
				<label><?= lang("app.sClass"); ?>:</label>
				<select class="form-control select2" name="class" id="select_class" required>
					<option selected disabled><?= lang("app.selectClass"); ?></option>
					<?php foreach ($classes as $classe) {
						?>
						<option value="<?= $classe['id']; ?>" <?= $classe['id'] == ($class_id??'')?'selected':''; ?>
								data-id=""> <?= $classe['level_name']; ?> <?= $classe['code']; ?> <?= $classe['title']; ?></option>
						<?php
					}
					?>
				</select>
			</div>
			<div class="form-group mr-2 pull-left" style="width: 200px">
				<label><?= lang("app.year"); ?>:</label>
				<input type="text" class="form-control" readonly value="<?= $academic_year_title; ?>">
			</div>
			<div class="pull-left">
				<input type="submit" class="btn btn-success" style="margin-top: 30px" value="Continue">
			</div>
		</form>
	</div>
	<hr style="width: 100%;margin-top: 0"/>
	<?php
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
					<th>Failed courses</th>
					<th>Percentages</th>
					<th>Discipline</th>
					<th>Decision</th>
				</tr>
				</tr>
				</thead>
				<tbody>
				<?php
				$a = 1;
				$home = new \App\Controllers\Home();
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
							$perc1 = ($mCat1 + $mExam1)*100/ ($core['marks'] * 2);
							$termCount++;
						}
						if (in_array('2', explode(',', $core['term1']))) {
							$single_total2 += $core['marks'] * 2;
							$perc2 = ($mCat2 + $mExam2)*100/ ($core['marks'] * 2);
							$termCount++;
						}
						if (in_array('3', explode(',', $core['term1']))) {
							$single_total3 += $core['marks'] * 2;
							$perc3 = ($mCat3 + $mExam3)*100/ ($core['marks'] * 2);
							$termCount++;
						}
						$total1 += $mCat1 + $mExam1;
						$total2 += $mCat2 + $mExam2;
						$total3 += $mCat3 + $mExam3;

						$max_total += $core['marks'] * 2 * 3;
						$perc = ($perc1 + $perc2 + $perc3)/$termCount;
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
					$verdictData = getDeliberationVerdict($deliberation_data, $percentage, $discipline);
					$verdict = null;
					$verdictId = '_0';
					if (is_array($verdictData)) {
						$verdict = $verdictData['verdict'];
						$verdictId = '_' . $verdictData['id'];
					}; ?>
					<tr>
						<td><?= $a; ?></td>
						<td><?= $student['regno']; ?></td>
						<td><?= $student['fname'] . ' ' . $student['lname'] .
							($failedCoursesCount > 0 ? "<label class='badge badge-danger ml-2'>{$failedCoursesCount}</label>" : ''); ?></td>
						<td><?= $failedCourses; ?></td>
						<td>
							<label class='badge <?= $percentage1 > 50 ? 'badge-success' : 'badge-danger'; ?> mr-2'>Term
								1: <?= number_format($percentage1, 1); ?>% </label>
							<label class='badge <?= $percentage2 > 50 ? 'badge-success' : 'badge-danger'; ?> mr-2'>Term
								2: <?= number_format($percentage2, 1); ?>% </label>
							<label class='badge <?= $percentage3 > 50 ? 'badge-success' : 'badge-danger'; ?> mr-2'>Term
								3: <?= number_format($percentage3, 1); ?>% </label>
							<label class='badge <?= $percentage > 50 ? 'badge-success' : 'badge-danger'; ?> mr-2'>Overall: <?= number_format($percentage, 1); ?>
								% </label>
						</td>
						<td>
							<label class='badge <?= $discipline > 50 ? 'badge-success' : 'badge-danger'; ?> mr-2'>Overall: <?= number_format($discipline, 1); ?>
								% </label>
						</td>
						<td>
							<?php
							if ($verdict != null) {
								?>
								<label class="badge badge-info" style="font-size: 6pt"><i class="fa fa-star"></i>
									Automatic
									decision</label>
								<?php
							}
							?>
							<select name="decisions[]" <?= $verdict != null ? 'readonly' : ''; ?> required
									class="select-decision-0" data-verdict="<?= $verdict; ?>">
								<option>Select a decision</option>
								<option <?= $verdict == 1 ? 'selected' : ''; ?>
										value="<?= $student['id'] . '_'; ?>1<?= $verdictId; ?>_<?= $verdict == 1 ? '1' : '0'; ?>">PROMOTED
								</option>
								<option <?= $verdict == 2 ? 'selected' : ''; ?>
										value="<?= $student['id'] . '_'; ?>2<?= $verdictId; ?>_<?= $verdict == 2 ? '1' : '0'; ?>">REPEAT
								</option>
								<option <?= $verdict == 3 ? 'selected' : ''; ?>
										value="<?= $student['id'] . '_'; ?>3<?= $verdictId; ?>_<?= $verdict == 3 ? '1' : '0'; ?>">SECOND SITTING
								</option>
								<option <?= $verdict == 4 ? 'selected' : ''; ?>
										value="<?= $student['id'] . '_'; ?>4<?= $verdictId; ?>_<?= $verdict == 4 ? '1' : '0'; ?>">DISMISSED
								</option>
								<option <?= $verdict == 5 ? 'selected' : ''; ?>
										value="<?= $student['id'] . '_'; ?>5<?= $verdictId; ?>_<?= $verdict == 5 ? '1' : '0'; ?>">REORIENTED
								</option>
							</select>
						</td>
					</tr>
					<?php
					$a++;
				}
				?>
				</tbody>
			</table>
			<div class="row" style="border: 1px solid;display: inline-block;width: 100%;padding: 10px;
		margin: 10px 0;border-radius: 4px;background-color: #110f0f;color: white;">
				<?php
				if (count($next_classes) == 0) {
					echo "<p style='color: orange'>There no upper class for promotion, may be it is the final level</p>";
				} else {
					?>
					<p>Please make sure that all action
						are
						collect</p>
					<div class="form-group mr-2 pull-left">
						<label>Promotion class:</label>

						<select class="form-control select2" name="next_class" required>
							<option selected disabled><?= lang("app.selectClass"); ?></option>
							<?php foreach ($next_classes as $classe) {
								?>
								<option value="<?= $classe['id']; ?>"
										data-id=""> <?= $classe['level_name']; ?> <?= $classe['code']; ?> <?= $classe['title']; ?></option>
								<?php
							}
							?>
						</select>
						<input type="hidden" name="class" value="<?= $class_id; ?>">
					</div>
					<div class="form-group mr-2 pull-left" style="width: 200px">
						<label>Next academic year:</label>
						<input type="text" class="form-control" name="next_academic"
							   value="<?= $academic_year_title; ?>">
					</div>
					<div class="form-group mr-2 pull-left" style="width: 200px">
						<label>Password for confirmation:</label>
						<input type="password" id="txt-passwd-0" class="form-control"
							   placeholder="Enter your password here">
					</div>
					<div class="pull-left">
						<button type="button" class="btn btn-warning" id="btn-save-deliberation"
								style="margin-top: 30px">Confirm
							deliberation
						</button>
					</div>
					<?php
				}
				?>
			</div>
		</form>
		<?php
	}
	?>
</div>
<script>
	$(function () {
		$("#deliberation").dataTable({
			// "scrollY":        "200px",
			"scrollCollapse": true,
			"paging": false
		});
		$(".select-decision-0").on("change", function () {
			const dt = $(this).val().split('_');
			if (dt[dt.length-1] == 1){
				$(this).closest('td').children('.badge-info').show();
			}else{
				$(this).closest('td').children('.badge-info').hide();
			}
		});
		$("#btn-save-deliberation").on("click", function () {
			if ($("[name='next_class']").val() == null) {
				toastada.warning("Please select new class to be promoted to");
				$(this).focus();
				return;
			}
			if ($("[name='next_academic']").val().length == 0) {
				toastada.warning("Please enter next academic year");
				$(this).focus();
				return;
			}
			if ($("#txt-passwd-0").val().length < 6) {
				toastada.warning("Invalid password");
				$(this).focus();
				return;
			}
			if (!confirm("Confirm deliberation processing")) {
				return;
			}
			$.post(base_url + "verify_password", "password=" + $("#txt-passwd-0").val(), function (data) {
				if (data.hasOwnProperty('success') && data.success == 1) {
					$("#deliberation-form-0").submit();
				} else {
					toastada.error(data.message);
				}
			}).fail(function (error) {
				toastada.error(error.responseJSON.message)
			});
		});
	});
</script>
