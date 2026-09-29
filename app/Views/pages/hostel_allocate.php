<link rel="stylesheet" href="<?= base_url('assets/css/hostels.css'); ?>?v=8">
<?php
$hostels = $hostels ?? [];
$roster = $roster ?? [];
$years = $years ?? [];
$yearId = (int) ($academic_year_id ?? 0);
$normSex = static function ($sex): string {
	$g = strtoupper(trim((string) $sex));
	if ($g === 'F' || $g === 'FEMALE' || $g === 'GIRL' || $g === 'WOMAN') return 'F';
	if ($g === 'M' || $g === 'MALE' || $g === 'BOY' || $g === 'MAN') return 'M';
	return '';
};
$totalBeds = 0; $totalOcc = 0; $totalFree = 0;
foreach ($hostels as $h) {
	$totalBeds += (int) ($h['max_beds'] ?? 0);
	$totalOcc += (int) ($h['occupied'] ?? 0);
	$totalFree += (int) ($h['free_beds'] ?? 0);
}
$classLabel = static function (array $row): string {
	$label = \App\Models\SchoolFeesModel::displayLabel([
		'level_name' => $row['level_name'] ?? '',
		'class_title' => $row['class_title'] ?? '',
		'dept_code' => $row['dept_code'] ?? '',
		'dept_title' => $row['dept_title'] ?? '',
	]);
	if (preg_match('/^(P\d+|S\d+)\s+([A-Z])$/i', $label, $m)) {
		return strtoupper($m[1]) . strtoupper($m[2]);
	}
	return $label;
};
$classRank = static function (string $label): array {
	$l = strtolower(trim($label));
	if (strpos($l, 'baby') !== false) return [0, 1, 0, $l];
	if (strpos($l, 'middle') !== false) return [0, 2, 0, $l];
	if (strpos($l, 'top') !== false) return [0, 3, 0, $l];
	if (preg_match('/nursery|\bn\s*([1-3])\b/', $l, $m)) return [0, 4, (int) ($m[1] ?? 0), $l];
	if (preg_match('/level\s*(\d+)/', $l, $m)) return [1, (int) $m[1], 0, $l];
	if (preg_match('/^p\s*(\d+)\s*([a-z])?/', $l, $m)) return [2, (int) $m[1], $m[2] ?? '', $l];
	if (preg_match('/^s\s*(\d+)\s*(.*)$/', $l, $m)) return [3, (int) $m[1], $m[2], $l];
	return [9, 0, 0, $l];
};
$groups = [];
$assignedCount = 0;
foreach ($roster as $row) {
	$cid = (int) ($row['class_id'] ?? 0);
	if (!isset($groups[$cid])) {
		$label = $classLabel($row);
		$groups[$cid] = [
			'id' => $cid,
			'label' => $label,
			'rank' => $classRank($label),
			'students' => [],
		];
	}
	if ((int) ($row['hostel_id'] ?? 0) > 0) $assignedCount++;
	$groups[$cid]['students'][] = $row;
}
uasort($groups, static function ($a, $b) {
	return $a['rank'] <=> $b['rank'];
});
$studentCount = count($roster);
$openCount = max(0, $studentCount - $assignedCount);
?>
<div class="drm-page" id="drmPage">
	<section class="drm-hero">
		<div class="drm-hero-copy">
			<div class="drm-kicker"><i class="fa fa-bed"></i> Boarding housing</div>
			<h4>Dormitories</h4>
			<p>Boarding students only, folded by class. Day scholars stay off this list. Search by name, registration number, or class.</p>
		</div>
		<div class="drm-year">
			<label for="drmYear">Academic year</label>
			<select class="form-control form-control-sm" id="drmYear">
				<?php foreach ($years as $yr) : ?>
					<option value="<?= (int) $yr['id']; ?>" <?= (int) $yr['id'] === $yearId ? 'selected' : ''; ?>><?= esc($yr['title']); ?></option>
				<?php endforeach; ?>
			</select>
			<div class="drm-export">
				<a class="drm-export-btn drm-export-xls" id="drmExcel" href="<?= base_url('export_dormitory_roster_excel'); ?>?year=<?= (int) $yearId; ?>">Excel</a>
				<a class="drm-export-btn drm-export-pdf" id="drmPdf" href="<?= base_url('export_dormitory_roster_pdf'); ?>?year=<?= (int) $yearId; ?>" target="_blank">PDF</a>
			</div>
		</div>
	</section>
	<section class="drm-kpi" id="drmKpi">
		<article class="drm-kpi-card"><i class="fa fa-users drm-kpi-ico"></i><span>Boarding</span><strong id="drmKpiStudents"><?= (int) $studentCount; ?></strong><small>This year</small></article>
		<article class="drm-kpi-card drm-kpi-ok"><i class="fa fa-check drm-kpi-ico"></i><span>Assigned</span><strong id="drmKpiAssigned"><?= (int) $assignedCount; ?></strong><small>Have a dormitory</small></article>
		<article class="drm-kpi-card drm-kpi-warn"><i class="fa fa-clock-o drm-kpi-ico"></i><span>Unassigned</span><strong id="drmKpiOpen"><?= (int) $openCount; ?></strong><small>Still need a bed</small></article>
		<article class="drm-kpi-card"><i class="fa fa-bed drm-kpi-ico"></i><span>Dormitories</span><strong id="drmKpiDorms"><?= count($hostels); ?></strong><small>From school settings</small></article>
		<article class="drm-kpi-card"><i class="fa fa-bar-chart drm-kpi-ico"></i><span>Beds used</span><strong id="drmKpiUsed"><?= (int) $totalOcc; ?></strong><small id="drmKpiCap">of <?= (int) $totalBeds; ?> beds</small></article>
		<article class="drm-kpi-card drm-kpi-free"><i class="fa fa-inbox drm-kpi-ico"></i><span>Free beds</span><strong id="drmKpiFree"><?= (int) $totalFree; ?></strong><small>Across all dormitories</small></article>
	</section>
	<?php if (empty($hostels)) : ?>
		<div class="drm-empty">
			<strong>No dormitories yet.</strong>
			<p>Add them in Settings, then come back to assign students.</p>
			<a class="btn btn-sm btn-primary" href="<?= base_url('school_settings'); ?>#hostels-settings">Open dormitory settings</a>
		</div>
	<?php else : ?>
		<section class="drm-strip" aria-label="Dormitory occupancy">
			<?php foreach ($hostels as $h) :
				$gid = strtoupper((string) ($h['gender'] ?? 'M')) === 'F' ? 'F' : 'M';
				$max = max(1, (int) ($h['max_beds'] ?? 0));
				$pct = (int) min(100, round(((int) ($h['occupied'] ?? 0) / $max) * 100));
			?>
				<article class="drm-chip" data-id="<?= (int) $h['id']; ?>">
					<div class="drm-chip-top">
						<strong><?= esc($h['name'] ?? 'Dormitory'); ?></strong>
						<span class="hst-gender-badge <?= $gid === 'F' ? 'hst-gender-f' : 'hst-gender-m'; ?>"><?= $gid === 'F' ? 'Female' : 'Male'; ?></span>
					</div>
					<div class="drm-bar"><span style="width:<?= $pct; ?>%"></span></div>
					<small class="drm-chip-count"><?= (int) ($h['occupied'] ?? 0); ?> / <?= (int) ($h['max_beds'] ?? 0); ?> beds</small>
				</article>
			<?php endforeach; ?>
		</section>
	<?php endif; ?>
	<section class="drm-board">
		<div class="drm-tools">
			<div class="drm-search">
				<i class="fa fa-search"></i>
				<input type="search" id="drmSearch" placeholder="Search by student name, registration number, or class" autocomplete="off">
			</div>
			<div class="drm-filters">
				<button type="button" class="drm-filter is-on" data-filter="all">All</button>
				<button type="button" class="drm-filter" data-filter="open">Unassigned</button>
				<button type="button" class="drm-filter" data-filter="assigned">Assigned</button>
			</div>
			<div class="drm-fold">
				<button type="button" class="btn btn-sm btn-light" id="drmExpand">Expand all</button>
				<button type="button" class="btn btn-sm btn-light" id="drmCollapse">Fold all</button>
			</div>
		</div>
		<p class="drm-result" id="drmResult" hidden></p>
		<?php if (empty($groups)) : ?>
			<div class="drm-empty">
				<strong>No boarding students in this academic year.</strong>
				<p>Day scholars are not listed here. Choose another year if this looks empty.</p>
			</div>
		<?php else : ?>
			<div class="drm-classes" id="drmClasses">
				<?php foreach ($groups as $group) :
					$gAssigned = 0;
					foreach ($group['students'] as $st) {
						if ((int) ($st['hostel_id'] ?? 0) > 0) $gAssigned++;
					}
					$classSearch = strtolower($group['label']);
				?>
					<section class="drm-class" data-search="<?= esc($classSearch, 'attr'); ?>">
						<button type="button" class="drm-class-head">
							<span class="drm-class-name"><?= esc($group['label']); ?></span>
							<span class="drm-class-meta">
								<span class="drm-class-total"><?= count($group['students']); ?></span> boarding
								<span class="drm-sep"></span>
								<span class="drm-class-assigned"><?= (int) $gAssigned; ?></span> assigned
							</span>
							<i class="fa fa-chevron-down"></i>
						</button>
						<div class="drm-class-body">
							<?php foreach ($group['students'] as $st) :
								$sid = (int) ($st['id'] ?? 0);
								$sex = $normSex($st['sex'] ?? '');
								$hid = (int) ($st['hostel_id'] ?? 0);
								$name = trim((string) ($st['fname'] ?? '') . ' ' . (string) ($st['lname'] ?? ''));
								$hay = strtolower($name . ' ' . (string) ($st['regno'] ?? '') . ' ' . $classSearch);
							?>
								<div class="drm-student" data-search="<?= esc($hay, 'attr'); ?>" data-assigned="<?= $hid > 0 ? '1' : '0'; ?>">
									<div class="drm-who">
										<span class="drm-avatar <?= $sex === 'F' ? 'is-f' : 'is-m'; ?>"><?= esc(strtoupper(substr($name !== '' ? $name : 'S', 0, 1))); ?></span>
										<div>
											<strong><?= esc($name !== '' ? $name : 'Student'); ?></strong>
											<small><?= esc((string) ($st['regno'] ?? '')); ?></small>
										</div>
										<?php if ($sex === 'F' || $sex === 'M') : ?>
											<span class="hst-gender-badge <?= $sex === 'F' ? 'hst-gender-f' : 'hst-gender-m'; ?>"><?= $sex === 'F' ? 'Female' : 'Male'; ?></span>
										<?php endif; ?>
									</div>
									<select class="form-control form-control-sm drm-assign" data-student="<?= $sid; ?>" data-current="<?= $hid; ?>">
										<option value="">Unassigned</option>
										<?php foreach ($hostels as $h) :
											$hg = strtoupper((string) ($h['gender'] ?? 'M')) === 'F' ? 'F' : 'M';
											if ($sex !== '' && $hg !== $sex) continue;
											$optId = (int) $h['id'];
										?>
											<option value="<?= $optId; ?>" data-hostel="<?= $optId; ?>" data-name="<?= esc((string) ($h['name'] ?? ''), 'attr'); ?>" data-gender="<?= $hg === 'F' ? 'Female' : 'Male'; ?>" <?= $optId === $hid ? 'selected' : ''; ?>>
												<?= esc((string) ($h['name'] ?? 'Dormitory')); ?> · <?= $hg === 'F' ? 'Female' : 'Male'; ?> · <?= (int) ($h['free_beds'] ?? 0); ?> free
											</option>
										<?php endforeach; ?>
									</select>
								</div>
							<?php endforeach; ?>
						</div>
					</section>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</section>
</div>
<div class="drm-toast" id="drmToast" hidden></div>
<script>
(function ($) {
	var yearId = <?= (int) $yearId; ?>;
	var assignUrl = '<?= base_url('hostel_assign'); ?>';
	var unassignUrl = '<?= base_url('hostel_unassign'); ?>';
	var pageUrl = '<?= base_url('hostel_allocate'); ?>';
	var filter = 'all';
	var toastTimer;
	function toast(msg, ok) {
		var $t = $('#drmToast');
		$t.text(msg).toggleClass('is-bad', !ok).prop('hidden', false);
		clearTimeout(toastTimer);
		toastTimer = setTimeout(function () { $t.prop('hidden', true); }, 2800);
	}
	function recount() {
		var assigned = 0;
		$('.drm-student').each(function () { if ($(this).attr('data-assigned') === '1') assigned++; });
		$('#drmKpiAssigned').text(assigned);
		$('#drmKpiOpen').text(Math.max(0, $('.drm-student').length - assigned));
		$('.drm-class').each(function () {
			$(this).find('.drm-class-assigned').text($(this).find('.drm-student[data-assigned="1"]').length);
		});
	}
	function applyBeds(res) {
		if (!res || !res.hostels) return;
		res.hostels.forEach(function (h) {
			var $chip = $('.drm-chip[data-id="' + h.id + '"]');
			$chip.find('.drm-chip-count').text(h.occupied + ' / ' + h.max_beds + ' beds');
			var pct = h.max_beds > 0 ? Math.min(100, Math.round((h.occupied / h.max_beds) * 100)) : 0;
			$chip.find('.drm-bar > span').css('width', pct + '%');
			$('option[data-hostel="' + h.id + '"]').each(function () {
				$(this).text(($(this).attr('data-name') || 'Dormitory') + ' · ' + ($(this).attr('data-gender') || '') + ' · ' + h.free_beds + ' free');
			});
		});
		if (res.beds) {
			$('#drmKpiUsed').text(res.beds.used);
			$('#drmKpiFree').text(res.beds.free);
			$('#drmKpiCap').text('of ' + res.beds.capacity + ' beds');
		}
	}
	function applyFilter() {
		var q = ($('#drmSearch').val() || '').toLowerCase().trim();
		var shownStudents = 0, shownClasses = 0;
		$('.drm-class').each(function () {
			var $cls = $(this);
			var classHit = q !== '' && (($cls.attr('data-search') || '').indexOf(q) !== -1);
			var visible = 0;
			$cls.find('.drm-student').each(function () {
				var $st = $(this);
				var textHit = q === '' || classHit || (($st.attr('data-search') || '').indexOf(q) !== -1);
				var state = $st.attr('data-assigned') === '1' ? 'assigned' : 'open';
				var show = textHit && (filter === 'all' || filter === state);
				$st.toggle(show);
				if (show) visible++;
			});
			$cls.toggle(visible > 0);
			if (visible > 0) {
				shownClasses++;
				shownStudents += visible;
				if (q !== '') $cls.addClass('is-open');
			}
		});
		if (q === '' && filter === 'all') $('#drmResult').prop('hidden', true);
		else $('#drmResult').prop('hidden', false).text(shownStudents + ' student' + (shownStudents === 1 ? '' : 's') + ' in ' + shownClasses + ' class' + (shownClasses === 1 ? '' : 'es'));
	}
	function exportHref(path) {
		return path + '?year=' + encodeURIComponent($('#drmYear').val() || yearId);
	}
	$('#drmYear').on('change', function () {
		$('#drmExcel').attr('href', exportHref('<?= base_url('export_dormitory_roster_excel'); ?>'));
		$('#drmPdf').attr('href', exportHref('<?= base_url('export_dormitory_roster_pdf'); ?>'));
		window.location = pageUrl + '?year=' + encodeURIComponent(this.value);
	});
	$('#drmSearch').on('input', applyFilter);
	$('.drm-filter').on('click', function () {
		filter = $(this).data('filter');
		$('.drm-filter').removeClass('is-on');
		$(this).addClass('is-on');
		applyFilter();
	});
	$('#drmClasses').on('click', '.drm-class-head', function () { $(this).closest('.drm-class').toggleClass('is-open'); });
	$('#drmExpand').on('click', function () { $('.drm-class:visible').addClass('is-open'); });
	$('#drmCollapse').on('click', function () { $('.drm-class').removeClass('is-open'); });
	$('#drmClasses').on('change', '.drm-assign', function () {
		var $sel = $(this);
		var studentId = parseInt($sel.data('student'), 10) || 0;
		var next = parseInt($sel.val(), 10) || 0;
		var prev = parseInt($sel.attr('data-current'), 10) || 0;
		if (!studentId || next === prev) return;
		$sel.prop('disabled', true);
		var payload = { student_id: studentId, year: yearId };
		var url = unassignUrl;
		if (next > 0) { url = assignUrl; payload.hostel_id = next; }
		$.post(url, payload).done(function (res) {
			if (res && res.error) { $sel.val(prev ? String(prev) : ''); toast(res.error, false); return; }
			$sel.attr('data-current', next);
			$sel.closest('.drm-student').attr('data-assigned', next > 0 ? '1' : '0');
			applyBeds(res || {});
			recount();
			applyFilter();
			toast((res && res.success) || 'Saved.', true);
		}).fail(function () {
			$sel.val(prev ? String(prev) : '');
			toast('Could not save this assignment.', false);
		}).always(function () { $sel.prop('disabled', false); });
	});
})(jQuery);
</script>
