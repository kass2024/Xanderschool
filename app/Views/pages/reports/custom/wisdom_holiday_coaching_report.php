<style>
	@page { margin: 8mm; }
	.hc-page {
		width: 100%;
		float: left;
		page-break-inside: avoid;
		page-break-after: always;
		margin: 0 0 14px 0;
		font-family: "Times New Roman", Times, serif;
		color: #111;
	}
	.hc-paper {
		border: 4px double #000;
		padding: 8px;
		background: #fff;
		box-sizing: border-box;
	}
	.hc-inner {
		border: 1px solid #000;
		padding: 10px 14px 12px;
		min-height: 980px;
		box-sizing: border-box;
	}
	.hc-head { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
	.hc-head td { border: 0; vertical-align: top; padding: 2px 6px; }
	.hc-gov {
		font-size: 12px;
		font-weight: bold;
		letter-spacing: .4px;
		text-transform: uppercase;
		line-height: 1.25;
	}
	.hc-school {
		font-size: 18px;
		font-weight: bold;
		text-transform: uppercase;
		margin: 3px 0 2px;
		line-height: 1.15;
	}
	.hc-meta { font-size: 12.5px; line-height: 1.4; }
	.hc-crest, .hc-logo { width: 88px; height: auto; margin: 4px auto; display: block; }
	.hc-phone-box {
		display: inline-block;
		border: 1px solid #111;
		padding: 2px 8px;
		font-weight: bold;
		background: #fff59d;
		margin-top: 6px;
		font-size: 12px;
	}
	.hc-phone-hi { background: #fff59d; padding: 1px 4px; font-weight: bold; }
	.hc-motto-label { background: #fff59d; font-weight: bold; padding: 0 3px; }
	.hc-motto { color: #0b57d0; font-weight: bold; }
	.hc-slogan { color: #1b7a2f; font-weight: bold; }
	.hc-mail { color: #0b57d0; }
	.hc-section {
		text-align: center;
		font-weight: bold;
		font-size: 15px;
		letter-spacing: 1px;
		margin: 6px 0 2px;
		text-transform: uppercase;
	}
	.hc-section span { background: #fff59d; padding: 1px 10px; }
	.hc-title {
		text-align: center;
		font-weight: bold;
		font-size: 14px;
		text-transform: uppercase;
		text-decoration: underline;
		margin: 2px 0 8px;
	}
	.hc-pupil { width: 100%; font-size: 13.5px; margin: 4px 0 8px; border-collapse: collapse; }
	.hc-pupil td { border: 0; padding: 0 2px 4px; }
	.hc-dots { border-bottom: 1px dotted #333; display: inline-block; min-width: 220px; }
	.hc-table {
		width: 100%;
		border-collapse: collapse;
		font-size: 13px;
		margin: 4px 0 6px;
	}
	.hc-table th, .hc-table td {
		border: 1px solid #222;
		padding: 5px 7px;
	}
	.hc-table th {
		text-align: center;
		font-size: 12px;
		text-transform: uppercase;
	}
	.hc-th-sub { background: #fff2a8; width: 42%; text-align: left; }
	.hc-th-full { background: #c6efce; width: 16%; }
	.hc-th-score { background: #bdd7ee; width: 26%; }
	.hc-th-init { background: #bdd7ee; width: 16%; }
	.hc-dates {
		background: #f6e27a;
		text-align: center;
		font-weight: bold;
		font-size: 12.5px;
		text-transform: uppercase;
	}
	.hc-subhead {
		background: #fff8dc;
		text-align: center;
		font-style: italic;
		font-size: 12.5px;
	}
	.hc-table td.ctr { text-align: center; font-weight: bold; }
	.hc-table tr.total td { background: #fff2a8; font-weight: bold; }
	.hc-table tr.meta td { background: #f7f7f7; }
	.hc-conduct { margin: 8px 0 10px; font-size: 13.5px; font-weight: bold; }
	.hc-comment { font-size: 13px; margin-bottom: 10px; line-height: 1.5; }
	.hc-dotline { border-bottom: 1px dotted #333; min-height: 18px; display: block; margin: 4px 0; }
	.hc-nb { font-size: 12.5px; font-style: italic; margin: 10px 0 8px; }
	.hc-note {
		font-size: 12.5px;
		line-height: 1.55;
		background: #fff59d;
		padding: 4px 6px;
		margin: 3px 0;
	}
	.hc-stamp { margin-top: 8px; text-align: right; font-size: 12.5px; }
	.hc-stamp img { max-height: 68px; }
	.ctr { text-align: center; }
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
$moto = trim((string) ($school_moto ?? ''));
if ($moto === '') {
	$moto = 'FEARING GOD IS KNOWLEDGE';
}
$mentorName = trim((string) ($class_teacher ?? ''));
$mentorPhone = trim((string) ($class_teacher_phone ?? ''));
$dayFee = $fee_day ?? null;
$boardFee = $fee_boarding ?? null;
$schoolPhone = trim((string) ($school_phone ?? ''));

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
	$maxTotal = 0;
	$scoreTotal = 0;
	$hasAnyScore = false;
	$courseRows = $student['courses'] ?? [];
	foreach ($courseRows as $core) {
		$maxTotal += (float) ($core['full_marks'] ?? $core['marks'] ?? 0);
		if (($core['score'] ?? null) !== null && $core['score'] !== '') {
			$scoreTotal += (float) $core['score'];
			$hasAnyScore = true;
		}
	}
	$pct = ($maxTotal > 0 && $hasAnyScore) ? number_format(($scoreTotal * 100) / $maxTotal, 1) : '';
	$pos = $student['position'] ?? '';
	$outOf = $student['out_of'] ?? count($studentsList);
	?>
	<div class="hc-page" id="printable">
		<div class="hc-paper">
			<div class="hc-inner">
				<table class="hc-head">
					<tr>
						<td style="width:22%;text-align:center;">
							<img src="<?= $crestUrl; ?>" class="hc-crest" alt="Republic of Rwanda">
							<div class="hc-phone-hi"><?= esc($schoolPhone); ?></div>
						</td>
						<td style="width:56%;text-align:center;">
							<div class="hc-gov">REPUBLIC OF RWANDA</div>
							<div class="hc-gov">MINISTRY OF EDUCATION</div>
							<div class="hc-school"><?= esc(strtoupper((string) $school_name)); ?></div>
							<div class="hc-meta">
								<?php if ($pobox !== ''): ?>P.O BOX: <?= esc($pobox); ?><br><?php endif; ?>
								<span class="hc-motto-label">SCHOOL MOTTO:</span>
								<span class="hc-motto"><?= esc($moto); ?></span><br>
								<span class="hc-slogan">Slogan: Our children, our future.</span><br>
								Email: <span class="hc-mail"><?= esc($school_email ?? ''); ?></span>
							</div>
						</td>
						<td style="width:22%;text-align:center;">
							<img src="<?= $schoolLogoUrl; ?>" class="hc-logo" alt="School logo">
							<div class="hc-phone-box"><?= esc($schoolPhone); ?> DOS</div>
						</td>
					</tr>
				</table>

				<div class="hc-section"><span><?= esc($sectionLabel); ?></span></div>
				<div class="hc-title">END OF HOLIDAY COACHING PROGRESSIVE REPORT CARD, <?= esc($yearLabel); ?></div>

				<table class="hc-pupil">
					<tr>
						<td style="width:70%;"><b>Pupil's name:</b> <?= esc($pupil); ?></td>
						<td style="width:30%;text-align:right;"><b>Class:</b> <?= esc($classLabel); ?></td>
					</tr>
				</table>

				<table class="hc-table">
					<tr>
						<td colspan="4" class="hc-dates"><?= esc($dateBanner); ?></td>
					</tr>
					<tr>
						<th class="hc-th-sub">SUBJECT</th>
						<th class="hc-th-full">FULL MARKS</th>
						<th class="hc-th-score">Score</th>
						<th class="hc-th-init">INITIALS</th>
					</tr>
					<tr>
						<td colspan="4" class="hc-subhead">End of holiday coaching report card</td>
					</tr>
					<?php foreach ($courseRows as $core):
						$full = (float) ($core['full_marks'] ?? $core['marks'] ?? 0);
						$score = $core['score'] ?? null;
						$initials = $core['initials'] ?? '';
						$scoreDisp = ($score !== null && $score !== '') ? rtrim(rtrim(number_format((float) $score, 1), '0'), '.') : '';
						$fullDisp = $full > 0 ? rtrim(rtrim(number_format($full, 1), '0'), '.') : '';
						?>
						<tr>
							<td><?= esc(strtoupper((string) ($core['title'] ?? ''))); ?></td>
							<td class="ctr"><?= $fullDisp; ?></td>
							<td class="ctr"><?= $scoreDisp; ?></td>
							<td class="ctr"><?= esc($initials); ?></td>
						</tr>
					<?php endforeach; ?>
					<tr class="total">
						<td>TOTAL</td>
						<td class="ctr"><?= $maxTotal > 0 ? rtrim(rtrim(number_format($maxTotal, 1), '0'), '.') : ''; ?></td>
						<td class="ctr"><?= $hasAnyScore ? rtrim(rtrim(number_format($scoreTotal, 1), '0'), '.') : '............'; ?></td>
						<td></td>
					</tr>
					<tr class="meta">
						<td><b>Percentage:</b></td>
						<td colspan="3"><?= $pct !== '' ? $pct . '%' : '...........'; ?></td>
					</tr>
					<tr class="meta">
						<td><b>Position:</b></td>
						<td colspan="3"><?= $pos !== '' ? $pos : '......'; ?> out of <?= (int) $outOf; ?></td>
					</tr>
				</table>

				<div class="hc-conduct">Conduct: <?= number_format($conductScore, 0); ?>/<?= $conductMax; ?></div>
				<div class="hc-comment">
					<b>Class teacher’s comment</b>
					<span class="hc-dotline"></span>
					<span class="hc-dotline"></span>
					<div>Tel <?= $mentorPhone !== '' ? esc($mentorPhone) : '07........'; ?>
						&nbsp;&nbsp; sign ........
						<?php if ($mentorName !== ''): ?>&nbsp;(<?= esc($mentorName); ?>)<?php endif; ?>
					</div>
				</div>
				<div class="hc-comment">
					<b>Parent’s comments:</b>
					<span class="hc-dotline"></span>
					<span class="hc-dotline"></span>
					<div>Tel: ........ &nbsp;&nbsp; sign: ........</div>
				</div>
				<div class="hc-nb">
					<b>NB:</b> This report card is not valid without official signature and the school stamp.
				</div>
				<?php if (!empty($next_term_note)): ?>
					<div class="hc-note"><?= $next_term_note; ?></div>
				<?php endif; ?>
				<?php if ($dayFee !== null || $boardFee !== null): ?>
					<div class="hc-note">
						<?php if ($dayFee !== null): ?>
							School fees for day scholars: <?= number_format((float) $dayFee); ?> Frw per term.<br>
						<?php endif; ?>
						<?php if ($boardFee !== null): ?>
							School fees for boarders: <?= number_format((float) $boardFee); ?> Frw per term.
						<?php endif; ?>
					</div>
				<?php endif; ?>
				<div class="hc-stamp">
					<?php if (!empty($headmaster_signature) && strlen((string) $headmaster_signature) > 5): ?>
						<img src="<?= base_url('assets/images/signatures/' . $headmaster_signature); ?>" alt="Signature">
					<?php endif; ?>
					<div><b><?= esc($head_master ?? ''); ?></b></div>
					<div><?= lang('app.' . (($head_master_gender ?? 'M') == 'F' ? 'schoolHeadmistress' : 'schoolHeadmaster')); ?></div>
				</div>
			</div>
		</div>
	</div>
	<?php
}
?>
