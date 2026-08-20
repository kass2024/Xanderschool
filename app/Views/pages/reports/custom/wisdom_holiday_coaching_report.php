<style>
	.hc-page {
		width: 100%;
		float: left;
		page-break-inside: avoid;
		page-break-after: always;
		margin: 0 0 12px 0;
		font-family: "Times New Roman", Times, serif;
		color: #111;
	}
	.hc-card {
		border: 2px solid #1a365d;
		padding: 12px 16px 14px;
		background: #fff;
		min-height: 980px;
		box-sizing: border-box;
	}
	.hc-header {
		width: 100%;
		overflow: hidden;
		margin-bottom: 6px;
	}
	.hc-col {
		float: left;
		box-sizing: border-box;
	}
	.hc-col-left { width: 24%; text-align: center; }
	.hc-col-center { width: 52%; text-align: center; padding: 0 8px; }
	.hc-col-right { width: 24%; text-align: center; }
	.hc-gov { font-size: 11px; font-weight: bold; letter-spacing: .3px; line-height: 1.25; }
	.hc-school {
		font-size: 18px;
		font-weight: bold;
		color: #1a365d;
		text-transform: uppercase;
		margin: 4px 0 2px;
		line-height: 1.2;
	}
	.hc-meta { font-size: 12px; line-height: 1.35; }
	.hc-crest { width: 92px; height: auto; margin: 4px auto; display: block; }
	.hc-logo { width: 96px; height: auto; margin: 4px auto; display: block; }
	.hc-section {
		text-align: center;
		font-weight: bold;
		font-size: 15px;
		letter-spacing: 1px;
		margin: 8px 0 2px;
		text-transform: uppercase;
		color: #1a365d;
	}
	.hc-title {
		text-align: center;
		font-weight: bold;
		font-size: 14px;
		text-transform: uppercase;
		text-decoration: underline;
		margin: 2px 0 10px;
	}
	.hc-pupil {
		width: 100%;
		font-size: 13px;
		margin: 4px 0 8px;
		overflow: hidden;
	}
	.hc-pupil .nm { float: left; width: 68%; }
	.hc-pupil .cl { float: right; width: 32%; text-align: right; }
	.hc-banner {
		background: #f6e27a;
		border: 1px solid #d4af37;
		text-align: center;
		font-weight: bold;
		font-size: 13px;
		padding: 6px 8px;
		margin: 6px 0 8px;
		text-transform: uppercase;
	}
	.hc-sub { text-align: center; font-style: italic; margin: 0 0 8px; font-size: 13px; }
	.hc-table {
		width: 100%;
		border-collapse: collapse;
		font-size: 13px;
		margin-bottom: 8px;
	}
	.hc-table th, .hc-table td {
		border: 1px solid #333;
		padding: 6px 8px;
	}
	.hc-table th {
		background: #1a365d;
		color: #fff;
		text-align: center;
		font-size: 12px;
		text-transform: uppercase;
	}
	.hc-table td.ctr { text-align: center; font-weight: bold; }
	.hc-table tr.total td { background: #e8eef7; font-weight: bold; }
	.hc-summary { font-size: 13px; margin: 6px 0 10px; overflow: hidden; }
	.hc-summary .left { float: left; width: 50%; }
	.hc-summary .right { float: right; width: 50%; text-align: right; }
	.hc-conduct { margin: 4px 0 12px; font-size: 13px; font-weight: bold; }
	.hc-comment {
		font-size: 13px;
		margin-bottom: 10px;
		line-height: 1.55;
	}
	.hc-dots { border-bottom: 1px dotted #333; min-height: 22px; display: block; }
	.hc-nb { font-size: 12px; font-style: italic; margin: 10px 0 8px; }
	.hc-fees { font-size: 12.5px; line-height: 1.5; }
	.hc-stamp {
		margin-top: 8px;
		text-align: right;
		font-size: 12px;
	}
	.hc-stamp img { max-height: 70px; }
	.clear { clear: both; }
</style>
<?php
$student_reg = isset($_GET['student']) ? $_GET['student'] : false;
$studentsList = $students ?? [];
if (count($studentsList) == 0) {
	echo "<h1>" . lang("app.noStudentFound") . "</h1>";
	return;
}
$schoolLogoFile = trim((string) ($school_logo ?? ''));
$schoolLogoUrl = $schoolLogoFile !== ''
	? base_url('assets/images/logo/' . $schoolLogoFile)
	: base_url('assets/images/holiday_coaching/wisdom_logo.jpeg');
$crestUrl = base_url('assets/images/holiday_coaching/rwanda_coat_of_arms.jpeg');
$yearLabel = $report_year_label ?? date('Y');
$sectionLabel = $section_label ?? 'PRIMARY SECTION';
$startLabel = $coaching_start ?? '';
$endLabel = $coaching_end ?? '';
$dateBanner = ($startLabel !== '' && $endLabel !== '')
	? 'HOLIDAY COACHING STARTED ON ' . $startLabel . ' TO ' . $endLabel
	: 'END OF HOLIDAY COACHING REPORT CARD';
$pobox = trim((string) ($school_pobox ?? ''));
$moto = trim((string) ($school_moto ?? 'FEARING GOD IS KNOWLEDGE'));
$mentorName = trim((string) ($class_teacher ?? ''));
$mentorPhone = trim((string) ($class_teacher_phone ?? ''));
$dayFee = $fee_day ?? null;
$boardFee = $fee_boarding ?? null;

foreach ($studentsList as $student) {
	if (!isset($student['id'])) {
		continue;
	}
	if ($student_reg !== false && (string) $student['id'] !== (string) $student_reg) {
		continue;
	}
	$classLabel = trim(($student['level_name'] ?? '') . ' ' . ($student['title'] ?? '') . ' ' . ($student['code'] ?? ''));
	$pupil = trim(($student['fname'] ?? '') . ' ' . ($student['lname'] ?? ''));
	$conductMax = (int) ($discipline_max ?? 100);
	$conductScore = $conductMax - (float) extractDisciplineMarks($student['displine_marks'] ?? '', $term);
	?>
	<div class="hc-page" id="printable">
		<div class="hc-card">
			<div class="hc-header">
				<div class="hc-col hc-col-left">
					<div class="hc-gov">REPUBLIC OF RWANDA</div>
					<img src="<?= $crestUrl; ?>" class="hc-crest" alt="Republic of Rwanda">
					<div class="hc-meta"><?= esc($school_phone ?? ''); ?><br>D.O.S</div>
				</div>
				<div class="hc-col hc-col-center">
					<div class="hc-school"><?= esc(strtoupper((string) $school_name)); ?></div>
					<div class="hc-meta">
						<?php if ($pobox !== ''): ?>P.O BOX: <?= esc($pobox); ?><br><?php endif; ?>
						<b>SCHOOL MOTTO:</b> <?= esc($moto); ?><br>
						Slogan: Our children, our future.<br>
						Email: <?= esc($school_email ?? ''); ?>
					</div>
				</div>
				<div class="hc-col hc-col-right">
					<div class="hc-gov">MINISTRY OF EDUCATION</div>
					<img src="<?= $schoolLogoUrl; ?>" class="hc-logo" alt="School logo">
				</div>
			</div>
			<div class="clear"></div>
			<div class="hc-section"><?= esc($sectionLabel); ?></div>
			<div class="hc-title">END OF HOLIDAY COACHING PROGRESSIVE REPORT CARD, <?= esc($yearLabel); ?></div>
			<div class="hc-pupil">
				<div class="nm"><b>Pupil's name:</b> <?= esc($pupil); ?></div>
				<div class="cl"><b>Class:</b> <?= esc($classLabel); ?></div>
			</div>
			<div class="clear"></div>
			<div class="hc-banner"><?= esc($dateBanner); ?></div>
			<div class="hc-sub">End of holiday coaching report card</div>
			<table class="hc-table">
				<thead>
				<tr>
					<th style="width:46%;">SUBJECT</th>
					<th style="width:18%;">FULL MARKS</th>
					<th style="width:18%;">Score</th>
					<th style="width:18%;">INITIALS</th>
				</tr>
				</thead>
				<tbody>
				<?php
				$maxTotal = 0;
				$scoreTotal = 0;
				$hasAnyScore = false;
				foreach (($student['courses'] ?? []) as $core) {
					$full = (float) ($core['full_marks'] ?? $core['marks'] ?? 0);
					$score = $core['score'] ?? null;
					$initials = $core['initials'] ?? '';
					$maxTotal += $full;
					if ($score !== null && $score !== '') {
						$scoreTotal += (float) $score;
						$hasAnyScore = true;
						$scoreDisp = number_format((float) $score, 1);
					} else {
						$scoreDisp = '';
					}
					?>
					<tr>
						<td><?= esc(strtoupper((string) ($core['title'] ?? ''))); ?></td>
						<td class="ctr"><?= $full > 0 ? rtrim(rtrim(number_format($full, 1), '0'), '.') : ''; ?></td>
						<td class="ctr"><?= $scoreDisp; ?></td>
						<td class="ctr"><?= esc($initials); ?></td>
					</tr>
					<?php
				}
				$pct = ($maxTotal > 0 && $hasAnyScore) ? number_format(($scoreTotal * 100) / $maxTotal, 1) : '';
				$pos = $student['position'] ?? '';
				$outOf = $student['out_of'] ?? count($studentsList);
				?>
				<tr class="total">
					<td>TOTAL</td>
					<td class="ctr"><?= $maxTotal > 0 ? rtrim(rtrim(number_format($maxTotal, 1), '0'), '.') : ''; ?></td>
					<td class="ctr"><?= $hasAnyScore ? number_format($scoreTotal, 1) : ''; ?></td>
					<td></td>
				</tr>
				</tbody>
			</table>
			<div class="hc-summary">
				<div class="left"><b>Percentage:</b> <?= $pct !== '' ? $pct . '%' : '______'; ?></div>
				<div class="right"><b>Position:</b> <?= $pos !== '' ? $pos : '____'; ?> out of <?= (int) $outOf; ?></div>
			</div>
			<div class="clear"></div>
			<div class="hc-conduct">Conduct: <?= number_format($conductScore, 0); ?>/<?= $conductMax; ?></div>
			<div class="hc-comment">
				<b>Class teacher’s comment</b>
				<span class="hc-dots"></span>
				<span class="hc-dots"></span>
				<div style="margin-top:6px;">
					Tel <?= $mentorPhone !== '' ? esc($mentorPhone) : '07______'; ?>
					&nbsp;&nbsp;&nbsp; sign ______________
					<?php if ($mentorName !== ''): ?>
						&nbsp;&nbsp;(<?= esc($mentorName); ?>)
					<?php endif; ?>
				</div>
			</div>
			<div class="hc-comment">
				<b>Parent’s comments:</b>
				<span class="hc-dots"></span>
				<span class="hc-dots"></span>
				<div style="margin-top:6px;">Tel: ______________ &nbsp;&nbsp; sign: ______________</div>
			</div>
			<div class="hc-nb">
				<b>NB:</b> This report card is not valid without official signature and the school stamp.
			</div>
			<div class="hc-fees">
				<?php if (!empty($next_term_note)): ?>
					<?= $next_term_note; ?><br>
				<?php endif; ?>
				<?php if ($dayFee !== null): ?>
					School fees for day scholars: <?= number_format((float) $dayFee); ?> Frw per term.<br>
				<?php endif; ?>
				<?php if ($boardFee !== null): ?>
					School fees for boarders: <?= number_format((float) $boardFee); ?> Frw per term.
				<?php endif; ?>
			</div>
			<div class="hc-stamp">
				<?php if (!empty($headmaster_signature) && strlen((string) $headmaster_signature) > 5): ?>
					<img src="<?= base_url('assets/images/signatures/' . $headmaster_signature); ?>" alt="Signature">
				<?php endif; ?>
				<div><b><?= esc($head_master ?? ''); ?></b></div>
				<div><?= lang('app.' . (($head_master_gender ?? 'M') == 'F' ? 'schoolHeadmistress' : 'schoolHeadmaster')); ?></div>
			</div>
		</div>
	</div>
	<?php
}
?>
