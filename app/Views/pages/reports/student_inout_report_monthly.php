<link rel="stylesheet" href="<?= base_url('assets/css/inout-report.css'); ?>">
<?php
if ($show_header) {
	$months = get_months();
	$defaultMonth = (int) ($default_month ?? date('n'));
	$defaultYear = (int) ($default_year ?? date('Y'));
	$years = $report_years ?? [$defaultYear];
	?>
	<div class="io-page">
		<div class="io-filters">
			<div class="io-filters-row">
				<div class="io-field">
					<label><?= lang("app.sClass"); ?></label>
					<select class="select2 form-control" id="select_class" name="class">
						<option value="0" selected>All classes</option>
						<?php foreach ($classes as $classe): ?>
							<option value="<?= (int) $classe['id']; ?>">
								<?= esc(($classe['level_name'] ?? '') . ' ' . ($classe['code'] ?? '') . ' ' . ($classe['title'] ?? '')); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="io-field">
					<label><?= lang("app.attendanceArea"); ?></label>
					<select class="select2 form-control" id="select_area" name="area">
						<option value="0" selected>All areas</option>
						<?php foreach (($attendance_areas ?? []) as $area):
							$label = $area['name'];
							if ((int) ($area['active'] ?? 1) !== 1) {
								$label .= ' (inactive)';
							}
							?>
							<option value="<?= (int) $area['id']; ?>"><?= esc($label); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="io-field">
					<label><?= lang("app.month"); ?></label>
					<select class="select2 form-control" id="choose_month" name="months">
						<?php for ($a = 0; $a < count($months); $a++): ?>
							<option value="<?= $a + 1; ?>" <?= ($a + 1) === $defaultMonth ? 'selected' : ''; ?>><?= esc($months[$a]); ?></option>
						<?php endfor; ?>
					</select>
				</div>
				<div class="io-field" style="max-width:120px;">
					<label>Year</label>
					<select class="form-control" id="choose_year" name="year">
						<?php foreach ($years as $yr): ?>
							<option value="<?= (int) $yr; ?>" <?= (int) $yr === $defaultYear ? 'selected' : ''; ?>><?= (int) $yr; ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="io-actions">
					<button class="btn btn-success" id="btn_generate"><i class="fa fa-chart-bar"></i> <?= lang("app.generate"); ?></button>
					<button class="btn btn-outline-primary" id="btn_print" type="button"><i class="fa fa-print"></i> <?= lang("app.print"); ?></button>
				</div>
			</div>
		</div>
		<div id="report_content">
			<div class="io-empty">Choose class, area, month and year, then Generate. Use <strong>All classes</strong> and <strong>All areas</strong> to track every NFC IN/OUT scan.</div>
		</div>
	</div>
	<script>
		$(function () {
			function params() {
				return "class=" + ($("#select_class").val() || 0)
					+ "&area=" + ($("#select_area").val() || 0)
					+ "&month=" + ($("#choose_month").val() || "")
					+ "&year=" + ($("#choose_year").val() || "");
			}
			$("#btn_generate").on("click", function () {
				if (!$("#choose_month").val()) {
					toastada.warning('<?= lang("app.pleaseSelectMonth"); ?>');
					return;
				}
				$("#btn_generate").text('<?= lang("app.pleaseWait"); ?>').prop("disabled", true);
				$("#report_content").load("<?= base_url('student_inout_monthly_report_data/false'); ?>?" + params(), function () {
					$("#btn_generate").html('<i class="fa fa-chart-bar"></i> <?= lang("app.generate"); ?>').prop("disabled", false);
				});
			});
			$("#btn_print").on("click", function () {
				if (!$("#printable").length) {
					toastada.warning('Generate the report first.');
					return;
				}
				window.print();
			});
			$("#report_content").on("click", ".io-area-card[data-area]", function () {
				var id = $(this).data("area");
				$("#select_area").val(String(id)).trigger("change");
				$("#btn_generate").click();
			});
			$("#report_content").on("click", ".io-tab", function () {
				var pane = $(this).data("pane");
				$(".io-tab").removeClass("active");
				$(this).addClass("active");
				$(".io-pane").attr("hidden", true);
				$("#" + pane).removeAttr("hidden");
			});
		});
	</script>
	<?php
	return;
}

$kpi = $kpi ?? [];
$students = $students ?? [];
$areaStats = $area_stats ?? [];
$lastDay = (int) ($last_day ?? 31);
$singleArea = !empty($single_area);
$showClassCol = (($classe ?? '') === 'All classes');
$never = $never_scanned ?? [];
$missing = $missing_out ?? [];
?>
<div id="printable">
	<div class="io-kpi-grid">
		<div class="io-kpi"><div class="lbl">Students</div><div class="val"><?= (int) ($kpi['students'] ?? 0); ?></div></div>
		<div class="io-kpi blue"><div class="lbl">Scanned</div><div class="val"><?= (int) ($kpi['scanned'] ?? 0); ?></div></div>
		<div class="io-kpi in"><div class="lbl">IN taps</div><div class="val"><?= (int) ($kpi['in_count'] ?? 0); ?></div></div>
		<div class="io-kpi out"><div class="lbl">OUT taps</div><div class="val"><?= (int) ($kpi['out_count'] ?? 0); ?></div></div>
		<div class="io-kpi warn"><div class="lbl">Missing OUT</div><div class="val"><?= (int) ($kpi['missing_out'] ?? 0); ?></div></div>
		<div class="io-kpi"><div class="lbl">Coverage</div><div class="val"><?= (int) ($kpi['coverage'] ?? 0); ?>%</div></div>
	</div>

	<?php if (count($areaStats) > 0) : ?>
		<div class="io-area-map">
			<?php foreach ($areaStats as $as) : ?>
				<button type="button" class="io-area-card<?= $singleArea ? ' active' : ''; ?>" data-area="<?= (int) $as['id']; ?>">
					<div class="an"><?= esc($as['name']); ?></div>
					<div class="as">
						<span><b><?= (int) $as['students']; ?></b> students</span>
						<span><b><?= (int) $as['in_count']; ?></b> IN</span>
						<span><b><?= (int) $as['out_count']; ?></b> OUT</span>
					</div>
				</button>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<div class="io-tabs">
		<button type="button" class="io-tab active" data-pane="ioPaneGrid">Day grid</button>
		<button type="button" class="io-tab" data-pane="ioPaneMissing">Missing OUT (<?= count($missing); ?>)</button>
		<button type="button" class="io-tab" data-pane="ioPaneNever">Never scanned (<?= count($never); ?>)</button>
	</div>

	<div class="io-pane" id="ioPaneGrid">
		<div class="io-letter">
			<div class="io-meta">
				<div>
					<strong><?= esc($school_name ?? ''); ?></strong><br>
					<?= lang("app.sClass"); ?>: <?= esc($classe ?? ''); ?><br>
					<?= lang("app.attendanceArea"); ?>: <?= esc($attendance_area ?? ''); ?><br>
					<?= lang("app.month"); ?>: <?= esc($month_label ?? $month ?? ''); ?>
				</div>
				<div>
					<?= lang("app.printedOn"); ?>: <?= date("Y-m-d H:i"); ?><br>
					<?= lang("app.totalStudents"); ?>: <?= count($students); ?>
				</div>
			</div>
			<h4><?= lang("app.StudentInOutmonthlyReport"); ?><?= !empty($attendance_area) ? ' — ' . esc($attendance_area) : ''; ?></h4>
			<?php if (count($students) === 0) : ?>
				<div class="io-empty">No students found for this filter.</div>
			<?php else : ?>
				<table class="tablepage">
					<thead>
					<tr>
						<th>#</th>
						<th><?= lang("app.firstName"); ?></th>
						<th><?= lang("app.lastName"); ?></th>
						<?php if ($showClassCol) : ?>
							<th>Class</th>
						<?php endif; ?>
						<?php for ($d = 1; $d <= $lastDay; $d++) : ?>
							<th style="text-align:center;"><?= sprintf('%02d', $d); ?></th>
						<?php endfor; ?>
					</tr>
					</thead>
					<tbody>
					<?php
					$n = 1;
					foreach ($students as $item) :
						$days = $item['days'] ?? [];
						?>
						<tr>
							<td style="text-align:right;"><?= $n; ?></td>
							<td><?= esc($item['fname'] ?? ''); ?></td>
							<td><?= esc($item['lname'] ?? ''); ?></td>
							<?php if ($showClassCol) : ?>
								<td><?= esc($item['class_name'] ?? ''); ?></td>
							<?php endif; ?>
							<?php for ($d = 1; $d <= $lastDay; $d++) :
								$visits = $days[$d] ?? [];
								$cls = $visits ? 'td_date' : 'td_date td_empty';
								$cell = '';
								foreach ($visits as $v) {
									$hasOut = ($v['out'] ?? '') !== '';
									if (!$hasOut) {
										$cls = 'td_date td_miss';
									}
									$cell .= '<span class="io-in">' . esc($v['in']) . '</span>';
									if ($hasOut) {
										$cell .= '<br><span class="io-out">-' . esc($v['out']) . '</span>';
									}
									if (!$singleArea) {
										$cell .= '<span class="io-area">' . esc($v['area']) . '</span>';
									}
									$cell .= '<br>';
								}
								echo "<td class='{$cls}'>{$cell}</td>";
							endfor; ?>
						</tr>
						<?php
						$n++;
					endforeach;
					?>
					</tbody>
				</table>
			<?php endif; ?>
			<div style="text-align:right;color:#94a3b8;margin-top:8px;font-size:.8rem;"><?= lang("app.generatedbySomanet"); ?></div>
		</div>
	</div>

	<div class="io-pane" id="ioPaneMissing" hidden>
		<?php if (count($missing) === 0) : ?>
			<div class="io-empty">No missing check-outs for this filter.</div>
		<?php else : ?>
			<table class="io-ex-table">
				<thead>
				<tr>
					<th>#</th>
					<th>Student</th>
					<th>Reg</th>
					<th>Class</th>
					<th>Area</th>
					<th>Day</th>
					<th>IN</th>
				</tr>
				</thead>
				<tbody>
				<?php $i = 1; foreach ($missing as $row) : ?>
					<tr>
						<td><?= $i++; ?></td>
						<td><?= esc(($row['fname'] ?? '') . ' ' . ($row['lname'] ?? '')); ?></td>
						<td><?= esc($row['regno'] ?? ''); ?></td>
						<td><?= esc($row['class_name'] ?? ''); ?></td>
						<td><?= esc($row['area'] ?? ''); ?></td>
						<td><?= (int) ($row['day'] ?? 0); ?></td>
						<td><?= esc($row['in'] ?? ''); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<div class="io-pane" id="ioPaneNever" hidden>
		<?php if (count($never) === 0) : ?>
			<div class="io-empty">Every student in this filter has at least one scan.</div>
		<?php else : ?>
			<table class="io-ex-table">
				<thead>
				<tr>
					<th>#</th>
					<th>Student</th>
					<th>Reg</th>
					<th>Class</th>
				</tr>
				</thead>
				<tbody>
				<?php $i = 1; foreach ($never as $row) : ?>
					<tr>
						<td><?= $i++; ?></td>
						<td><?= esc(($row['fname'] ?? '') . ' ' . ($row['lname'] ?? '')); ?></td>
						<td><?= esc($row['regno'] ?? ''); ?></td>
						<td><?= esc($row['class_name'] ?? ''); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>
