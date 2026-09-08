<?php
/** @var list<array<string,mixed>> $timetable_slots */
/** @var array<string,mixed>|null $timetable_settings */
/** @var list<array<string,mixed>> $timetable_special_times */
/** @var list<string> $timetable_day_labels */
/** @var array<string,int> $timetable_day_map */
$slots = $timetable_slots ?? [];
$settings = $timetable_settings ?? [];
$specialTimes = $timetable_special_times ?? [];
$dayLabels = $timetable_day_labels ?? ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];
$dayMap = $timetable_day_map ?? ['Mon' => 0, 'Tue' => 1, 'Wed' => 2, 'Thu' => 3, 'Fri' => 4];
/** @var list<array{key:string,label:string}> $timetable_tracks */
/** @var string $timetable_track_key */
/** @var bool $timetable_shared */
/** @var array<string,string> $timetable_track_labels */
$tracks = $timetable_tracks ?? [];
$selectedTrack = \App\Libraries\TimetableTrack::normalize($timetable_track_key ?? 'all');
$sharedSchedule = !empty($timetable_shared);
$trackLabels = $timetable_track_labels ?? \App\Libraries\TimetableTrack::labels();

$includeSaturday = !empty($settings['include_saturday']);
$includeSunday = !empty($settings['include_sunday']);

$specialByKey = [];
foreach ($specialTimes as $st) {
	$specialByKey[(int) $st['day_of_week'] . ':' . (int) $st['slot_id']] = $st;
}

$colorOptions = [
	'yellow' => ['label' => 'Chapel', 'hint' => 'Chapel / Co-curricular'],
	'blue' => ['label' => 'Welcoming the Sabbath', 'hint' => 'Sabbath / Assembly'],
	'green' => ['label' => 'Remedial', 'hint' => 'Remedial / Extra help'],
	'orange' => ['label' => 'Assembly', 'hint' => 'Assembly / Gathering'],
	'purple' => ['label' => 'Co-curricular', 'hint' => 'Clubs / Sports'],
	'gray' => ['label' => 'Other', 'hint' => 'Custom activity'],
];
?>
<link rel="stylesheet" href="<?= base_url('assets/css/timetable.css'); ?>">

<div class="tt-settings-wrap">
	<div class="tt-level-bar mb-3 d-flex flex-wrap align-items-end">
		<div class="mr-4 mb-2">
			<label class="small font-weight-bold d-block mb-1">Schedule</label>
			<select id="ttScheduleMode" class="form-control form-control-sm" style="min-width:220px;">
				<option value="shared" <?= $sharedSchedule ? 'selected' : ''; ?>>Same for all categories</option>
				<option value="per_track" <?= !$sharedSchedule ? 'selected' : ''; ?>>Different per category</option>
			</select>
		</div>
		<div id="ttTrackPicker" class="mr-4 mb-2 <?= $sharedSchedule ? 'd-none' : ''; ?>">
			<label class="small font-weight-bold d-block mb-1" for="ttTrackSelect">Category</label>
			<select id="ttTrackSelect" class="form-control form-control-sm" style="min-width:200px;">
				<?php foreach (\App\Libraries\TimetableTrack::categoryKeys() as $key): ?>
					<option value="<?= esc($key); ?>" <?= $selectedTrack === $key ? 'selected' : ''; ?>>
						<?= esc($trackLabels[$key] ?? ucfirst($key)); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>
	</div>

	<ul class="nav nav-tabs tt-settings-tabs mb-3" role="tablist">
		<li class="nav-item">
			<a class="nav-link active" data-toggle="tab" href="#ttTabPeriods" role="tab">Periods &amp; breaks</a>
		</li>
		<li class="nav-item">
			<a class="nav-link" data-toggle="tab" href="#ttTabSpecial" role="tab">Special times (weekly grid)</a>
		</li>
	</ul>

	<div class="tab-content">
		<div class="tab-pane fade show active" id="ttTabPeriods" role="tabpanel">
			<p class="text-muted mb-3">Define bell periods and breaks for the selected category. Primary, nursery, and secondary-style categories can use different time allocations.</p>

			<form id="timetableSlotsForm">
				<input type="hidden" name="track_key" id="ttTrackKeyPeriods" value="<?= esc($selectedTrack); ?>">
				<input type="hidden" name="shared_timetable" id="ttSharedFlagPeriods" value="<?= $sharedSchedule ? '1' : '0'; ?>">
				<div class="table-responsive">
					<table class="table table-sm table-bordered tt-period-table" id="timetableSlotsTable">
						<thead class="thead-light">
						<tr>
							<th>#</th>
							<th>Label</th>
							<th>Start</th>
							<th>End</th>
							<th>Break?</th>
							<th>Break label</th>
							<th></th>
						</tr>
						</thead>
						<tbody>
						<?php foreach ($slots as $i => $slot): ?>
							<tr data-slot-id="<?= (int) ($slot['id'] ?? 0); ?>">
								<td><?= (int) ($i + 1); ?>
									<input type="hidden" name="slot_id[]" value="<?= (int) ($slot['id'] ?? 0); ?>">
								</td>
								<td><input type="text" class="form-control form-control-sm" name="slot_label[]" value="<?= esc($slot['label']); ?>"></td>
								<td><input type="time" class="form-control form-control-sm tt-slot-start" name="slot_start[]" value="<?= esc(substr((string) $slot['start_time'], 0, 5)); ?>"></td>
								<td><input type="time" class="form-control form-control-sm tt-slot-end" name="slot_end[]" value="<?= esc(substr((string) $slot['end_time'], 0, 5)); ?>"></td>
								<td class="text-center"><input type="checkbox" class="tt-slot-break" name="slot_is_break[<?= $i; ?>]" value="1" <?= !empty($slot['is_break']) ? 'checked' : ''; ?>></td>
								<td><input type="text" class="form-control form-control-sm" name="slot_break_label[]" value="<?= esc($slot['break_label'] ?? ''); ?>" placeholder="BREAK 1"></td>
								<td><button type="button" class="btn btn-sm btn-outline-danger tt-remove-row">&times;</button></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<button type="button" class="btn btn-sm btn-secondary mb-3" id="ttAddSlotRow">+ Add period</button>
				<div class="mb-3">
					<label class="small font-weight-bold d-block mb-2">Weekend columns</label>
					<div class="btn-group btn-group-toggle" data-toggle="buttons">
						<label class="btn btn-sm btn-outline-primary <?= $includeSaturday ? 'active' : ''; ?>">
							<input type="checkbox" id="includeSaturday" name="include_saturday" value="1" autocomplete="off" <?= $includeSaturday ? 'checked' : ''; ?>> Saturday
						</label>
						<label class="btn btn-sm btn-outline-primary <?= $includeSunday ? 'active' : ''; ?>">
							<input type="checkbox" id="includeSunday" name="include_sunday" value="1" autocomplete="off" <?= $includeSunday ? 'checked' : ''; ?>> Sunday
						</label>
					</div>
				</div>
				<button type="submit" class="btn btn-primary">Save periods</button>
			</form>
		</div>

		<div class="tab-pane fade" id="ttTabSpecial" role="tabpanel">
			<p class="text-muted mb-2">
				<strong>Drag</strong> blocks to move them, <strong>double-click</strong> text to rename,
				or <strong>click a color chip</strong> then click cells to paint.
				These blocks appear on all class timetables and are excluded from auto-generation.
			</p>
			<div class="tt-legend mb-2">
				<?php foreach ($colorOptions as $c => $opt): ?>
					<span class="tt-legend-chip tt-special-<?= esc($c); ?> tt-paint-chip"
						draggable="true"
						data-preset-color="<?= esc($c); ?>"
						data-preset-label="<?= esc($opt['label']); ?>"
						title="Drag onto a cell, or click then click a cell">
						<?= esc($opt['hint']); ?>
					</span>
				<?php endforeach; ?>
			</div>
			<div class="small text-muted mb-2" id="ttPaintHint">Tip: click a chip above to activate paint mode, then click empty cells.</div>

			<div id="ttSpecialDock" class="tt-special-dock tt-special-dock-idle">
				<div class="tt-dock-head">
					<div class="tt-dock-context">
						<span class="tt-dock-icon"><i class="fa fa-pencil"></i></span>
						<div>
							<div class="tt-dock-title" id="ttDockTitle">Cell editor</div>
							<div class="tt-dock-slot text-muted" id="ttDockSlot">Click any cell in the grid below to add or edit a special activity.</div>
						</div>
					</div>
					<button type="button" class="btn btn-sm btn-light tt-dock-close d-none" id="ttDockClose" title="Close">&times;</button>
				</div>
				<div class="tt-dock-body" id="ttDockBody">
					<div class="row align-items-end">
						<div class="col-md-5 mb-2 mb-md-0">
							<label class="small font-weight-bold mb-1" for="ttSpLabel">Activity name</label>
							<input type="text" class="form-control form-control-sm" id="ttSpLabel" placeholder="e.g. Chapel, Gusenga, Remedial">
						</div>
						<div class="col-md-4 mb-2 mb-md-0">
							<label class="small font-weight-bold mb-1">Quick presets</label>
							<div class="tt-sp-presets">
								<?php foreach ($colorOptions as $c => $opt): ?>
									<button type="button" class="btn btn-sm tt-sp-preset tt-special-<?= esc($c); ?>"
										data-label="<?= esc($opt['label']); ?>" data-color="<?= esc($c); ?>">
										<?= esc($opt['label']); ?>
									</button>
								<?php endforeach; ?>
							</div>
						</div>
						<div class="col-md-3">
							<label class="small font-weight-bold mb-1">Color</label>
							<div class="tt-color-swatches" id="ttColorSwatches">
								<?php foreach ($colorOptions as $c => $opt): ?>
									<button type="button" class="tt-color-swatch tt-special-<?= esc($c); ?>"
										data-color="<?= esc($c); ?>" title="<?= esc($opt['hint']); ?>"></button>
								<?php endforeach; ?>
							</div>
							<input type="hidden" id="ttSpColor" value="yellow">
						</div>
					</div>
					<div class="tt-dock-actions mt-2">
						<button type="button" class="btn btn-sm btn-primary" id="ttSpApply"><i class="fa fa-check"></i> Apply</button>
						<button type="button" class="btn btn-sm btn-outline-danger" id="ttSpClear"><i class="fa fa-trash"></i> Remove</button>
						<button type="button" class="btn btn-sm btn-light" id="ttSpCancel">Cancel</button>
					</div>
				</div>
			</div>

			<form id="timetableSpecialForm">
				<input type="hidden" name="track_key" id="ttTrackKeySpecial" value="<?= esc($selectedTrack); ?>">
				<div class="tt-week-scroll">
					<table class="tt-week-editor" id="ttWeekEditor">
						<thead>
						<tr>
							<th class="tt-we-time">Period</th>
							<?php foreach ($dayLabels as $dl): ?>
								<th><?= esc($dl); ?></th>
							<?php endforeach; ?>
						</tr>
						</thead>
						<tbody>
						<?php foreach ($slots as $slot):
							$isBreak = !empty($slot['is_break']);
							$slotId = (int) ($slot['id'] ?? 0);
							if ($isBreak): ?>
								<tr class="tt-we-break">
									<td class="tt-we-time"><?= esc(substr($slot['start_time'], 0, 5)); ?>–<?= esc(substr($slot['end_time'], 0, 5)); ?></td>
									<td colspan="<?= count($dayLabels); ?>"><?= esc($slot['break_label'] ?: $slot['label']); ?></td>
								</tr>
							<?php else: ?>
								<tr data-slot-id="<?= $slotId; ?>">
									<td class="tt-we-time">
										<strong><?= esc($slot['label']); ?></strong>
										<div class="tt-we-range"><?= esc(substr($slot['start_time'], 0, 5)); ?>–<?= esc(substr($slot['end_time'], 0, 5)); ?></div>
									</td>
									<?php foreach ($dayLabels as $dl):
										$dayNum = $dayMap[$dl] ?? 0;
										$key = $dayNum . ':' . $slotId;
										$sp = $specialByKey[$key] ?? null;
										$color = $sp['color'] ?? 'yellow';
										$label = $sp['label'] ?? '';
										?>
										<td class="tt-we-cell <?= $label !== '' ? 'has-special tt-special-' . esc($color) : ''; ?>"
											data-day="<?= (int) $dayNum; ?>"
											data-day-label="<?= esc($dl); ?>"
											data-slot="<?= $slotId; ?>"
											data-slot-label="<?= esc($slot['label']); ?>"
											data-slot-range="<?= esc(substr($slot['start_time'], 0, 5) . ' - ' . substr($slot['end_time'], 0, 5)); ?>"
											data-color="<?= esc($color); ?>"
											<?= $label !== '' ? 'draggable="true"' : ''; ?>
											title="<?= $label !== '' ? 'Drag to move · double-click text to edit' : 'Click to add special time'; ?>">
											<?php if ($label !== ''): ?>
												<span class="tt-we-grip" title="Drag">&#8942;&#8942;</span>
											<?php endif; ?>
											<span class="tt-we-label"><?= esc($label); ?></span>
											<input type="hidden" name="special_day[]" value="<?= $label !== '' ? (int) $dayNum : ''; ?>" disabled>
											<input type="hidden" name="special_slot[]" value="<?= $label !== '' ? $slotId : ''; ?>" disabled>
											<input type="hidden" name="special_label[]" value="<?= esc($label); ?>" disabled>
											<input type="hidden" name="special_color[]" value="<?= esc($color); ?>" disabled>
										</td>
									<?php endforeach; ?>
								</tr>
							<?php endif;
						endforeach; ?>
						</tbody>
					</table>
				</div>
				<button type="submit" class="btn btn-primary mt-3">Save special times</button>
			</form>
		</div>
	</div>
</div>

<script>
(function () {
	var $tbody = $('#timetableSlotsTable tbody');
	var activeCell = null;
	var dragSource = null;
	var paintMode = null;
	var colorClasses = 'tt-special-yellow tt-special-blue tt-special-green tt-special-orange tt-special-purple tt-special-gray';

	function toastErr(msg) {
		if (window.toastada) toastada.error(msg);
	}
	function toastOk(msg) {
		if (window.toastada) toastada.success(msg);
	}

	function nextTeachingLabel() {
		var max = 0;
		$tbody.find('tr').each(function () {
			if ($(this).find('.tt-slot-break').is(':checked')) {
				return;
			}
			var n = parseInt($(this).find('input[name="slot_label[]"]').val(), 10);
			if (!isNaN(n) && n > max) {
				max = n;
			}
		});
		return String(max + 1);
	}

	function addOneHour(timeStr) {
		var parts = (timeStr || '08:00').split(':');
		var h = parseInt(parts[0], 10) || 0;
		var m = parseInt(parts[1], 10) || 0;
		h = Math.min(23, h + 1);
		return (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m;
	}

	function lastRowEndTime() {
		var $rows = $tbody.find('tr');
		if (!$rows.length) {
			return '08:00';
		}
		return $rows.last().find('.tt-slot-end').val() || '08:00';
	}

	$('#ttAddSlotRow').on('click', function () {
		var n = $tbody.find('tr').length;
		var startVal = lastRowEndTime();
		var endVal = addOneHour(startVal);
		var labelVal = nextTeachingLabel();
		$tbody.append(
			'<tr><td>' + (n + 1) + '<input type="hidden" name="slot_id[]" value="0"></td>'
			+ '<td><input type="text" class="form-control form-control-sm" name="slot_label[]" value="' + labelVal + '"></td>'
			+ '<td><input type="time" class="form-control form-control-sm tt-slot-start" name="slot_start[]" value="' + startVal + '"></td>'
			+ '<td><input type="time" class="form-control form-control-sm tt-slot-end" name="slot_end[]" value="' + endVal + '"></td>'
			+ '<td class="text-center"><input type="checkbox" class="tt-slot-break" name="slot_is_break[' + n + ']" value="1"></td>'
			+ '<td><input type="text" class="form-control form-control-sm" name="slot_break_label[]" value=""></td>'
			+ '<td><button type="button" class="btn btn-sm btn-outline-danger tt-remove-row">&times;</button></td></tr>'
		);
	});
	$(document).on('click', '.tt-remove-row', function () {
		$(this).closest('tr').remove();
	});

		$('#timetableSlotsForm').on('submit', function (e) {
		e.preventDefault();
		syncScheduleModeFields();
		$.post('<?= site_url('timetable/save_slots'); ?>', $(this).serialize(), function (r) {
			if (r.success) { toastOk(r.success); setTimeout(function () { location.reload(); }, 800); }
			else toastErr(r.error || 'Save failed');
		}, 'json').fail(function () { toastErr('Save failed'); });
	});

	function stripColorClasses($el) {
		$el.removeClass(colorClasses);
	}

	function ensureGrip($cell, hasLabel) {
		if (hasLabel && !$cell.find('.tt-we-grip').length) {
			$cell.prepend('<span class="tt-we-grip" title="Drag">&#8942;&#8942;</span>');
		} else if (!hasLabel) {
			$cell.find('.tt-we-grip').remove();
		}
	}

	function syncCellInputs($cell, label, color) {
		var day = $cell.data('day');
		var slot = $cell.data('slot');
		var $inputs = $cell.find('input[type=hidden]');
		label = $.trim(label || '');
		if (label) {
			$cell.addClass('has-special');
			stripColorClasses($cell);
			$cell.addClass('tt-special-' + color);
			$cell.attr('draggable', 'true');
			$cell.attr('title', 'Drag to move · double-click text to edit');
			$cell.find('.tt-we-label').text(label);
			ensureGrip($cell, true);
			$inputs.eq(0).val(day).prop('disabled', false);
			$inputs.eq(1).val(slot).prop('disabled', false);
			$inputs.eq(2).val(label).prop('disabled', false);
			$inputs.eq(3).val(color).prop('disabled', false);
			$cell.data('color', color);
		} else {
			$cell.removeClass('has-special');
			stripColorClasses($cell);
			$cell.removeAttr('draggable');
			$cell.attr('title', 'Click to add special time');
			$cell.find('.tt-we-label').text('');
			ensureGrip($cell, false);
			$inputs.prop('disabled', true).val('');
			$cell.data('color', 'yellow');
		}
	}

	function setDockIdle() {
		activeCell = null;
		$('.tt-we-cell').removeClass('tt-cell-active');
		$('#ttSpecialDock').addClass('tt-special-dock-idle');
		$('#ttDockTitle').text('Cell editor');
		$('#ttDockSlot').text('Click any cell in the grid below to add or edit a special activity.');
		$('#ttDockClose').addClass('d-none');
		$('#ttSpLabel').val('');
		setColorSwatch('yellow');
	}

	function setColorSwatch(color) {
		color = color || 'yellow';
		$('#ttSpColor').val(color);
		$('.tt-color-swatch').removeClass('is-active');
		$('.tt-color-swatch[data-color="' + color + '"]').addClass('is-active');
	}

	function openDock($cell) {
		activeCell = $cell;
		$('.tt-we-cell').removeClass('tt-cell-active');
		$cell.addClass('tt-cell-active');
		$('#ttSpecialDock').removeClass('tt-special-dock-idle');
		$('#ttDockClose').removeClass('d-none');

		var dayLabel = $cell.data('day-label') || '';
		var slotLabel = $cell.data('slot-label') || '';
		var slotRange = $cell.data('slot-range') || '';
		var label = $cell.find('.tt-we-label').text();
		var color = $cell.data('color') || 'yellow';

		$('#ttDockTitle').text(label ? 'Editing: ' + label : 'New special activity');
		$('#ttDockSlot').text(dayLabel + ' · ' + slotLabel + ' · ' + slotRange);
		$('#ttSpLabel').val(label);
		setColorSwatch(color);
		$('#ttSpLabel').focus();
	}

	function applySpecial($cell, label, color) {
		if (!$cell || !$cell.length) return;
		syncCellInputs($cell, label, color || 'yellow');
	}

	setDockIdle();

	function setPaintMode(color, label) {
		paintMode = { color: color, label: label };
		$('.tt-paint-chip').removeClass('is-active');
		$('.tt-paint-chip[data-preset-color="' + color + '"]').addClass('is-active');
		$('#ttPaintHint').html('Paint mode: <strong>' + $('<span>').text(label).html() + '</strong> — click cells to apply. Click chip again to cancel.');
		setDockIdle();
	}

	function clearPaintMode() {
		paintMode = null;
		$('.tt-paint-chip').removeClass('is-active');
		$('#ttPaintHint').text('Tip: click a chip above to activate paint mode, then click empty cells.');
	}

	$('.tt-paint-chip').on('click', function (e) {
		if (e.originalEvent && e.originalEvent.defaultPrevented) return;
		var $chip = $(this);
		var color = $chip.data('preset-color');
		var label = $chip.data('preset-label');
		if (paintMode && paintMode.color === color) {
			clearPaintMode();
		} else {
			setPaintMode(color, label);
		}
	});

	$('.tt-paint-chip').on('dragstart', function (e) {
		var $chip = $(this);
		e.originalEvent.dataTransfer.setData('application/x-tt-special', JSON.stringify({
			label: $chip.data('preset-label'),
			color: $chip.data('preset-color'),
			source: 'preset'
		}));
		e.originalEvent.dataTransfer.effectAllowed = 'copy';
		$chip.addClass('tt-dragging');
	}).on('dragend', function () {
		$(this).removeClass('tt-dragging');
	});

	$('#ttWeekEditor').on('dragstart', '.tt-we-cell.has-special', function (e) {
		if ($(e.target).closest('.tt-we-grip').length === 0 && !$(e.target).hasClass('tt-we-cell')) {
			/* allow drag from grip or whole cell */
		}
		dragSource = $(this);
		var payload = {
			label: dragSource.find('.tt-we-label').text(),
			color: dragSource.data('color') || 'yellow',
			source: 'cell',
			day: dragSource.data('day'),
			slot: dragSource.data('slot')
		};
		e.originalEvent.dataTransfer.setData('application/x-tt-special', JSON.stringify(payload));
		e.originalEvent.dataTransfer.effectAllowed = 'move';
		dragSource.addClass('tt-dragging');
	}).on('dragend', '.tt-we-cell', function () {
		dragSource = null;
		$('.tt-we-cell').removeClass('tt-drag-over tt-dragging');
	}).on('dragover', '.tt-we-cell', function (e) {
		e.preventDefault();
		e.originalEvent.dataTransfer.dropEffect = dragSource ? 'move' : 'copy';
		$(this).addClass('tt-drag-over');
	}).on('dragleave', '.tt-we-cell', function () {
		$(this).removeClass('tt-drag-over');
	}).on('drop', '.tt-we-cell', function (e) {
		e.preventDefault();
		var $target = $(this);
		$target.removeClass('tt-drag-over');
		var raw = e.originalEvent.dataTransfer.getData('application/x-tt-special');
		if (!raw) return;
		var data;
		try { data = JSON.parse(raw); } catch (err) { return; }

		if (data.source === 'cell' && dragSource) {
			if ($target.is(dragSource)) return;
			var srcLabel = dragSource.find('.tt-we-label').text();
			var srcColor = dragSource.data('color') || 'yellow';
			var tgtLabel = $target.find('.tt-we-label').text();
			var tgtColor = $target.data('color') || 'yellow';
			if (tgtLabel) {
				syncCellInputs($target, srcLabel, srcColor);
				syncCellInputs(dragSource, tgtLabel, tgtColor);
			} else {
				syncCellInputs($target, srcLabel, srcColor);
				syncCellInputs(dragSource, '', '');
			}
		} else if (data.label) {
			syncCellInputs($target, data.label, data.color || 'yellow');
		}
		dragSource = null;
	});

	$('#ttWeekEditor').on('click', '.tt-we-cell', function (e) {
		if ($(e.target).closest('.tt-we-inline-edit, .tt-we-grip').length) return;
		var $cell = $(this);

		if (paintMode) {
			applySpecial($cell, paintMode.label, paintMode.color);
			return;
		}

		openDock($cell);
	});

	$('#ttWeekEditor').on('dblclick', '.tt-we-cell.has-special .tt-we-label', function (e) {
		e.stopPropagation();
		var $cell = $(this).closest('.tt-we-cell');
		openDock($cell);
		$('#ttSpLabel').focus().select();
	});

	$(document).on('click', '.tt-sp-preset', function () {
		$('#ttSpLabel').val($(this).data('label'));
		setColorSwatch($(this).data('color'));
	});

	$(document).on('click', '.tt-color-swatch', function () {
		setColorSwatch($(this).data('color'));
	});

	$('#ttSpApply').on('click', function () {
		if (!activeCell) { toastErr('Select a cell first'); return; }
		var label = $.trim($('#ttSpLabel').val());
		var color = $('#ttSpColor').val();
		if (!label) { toastErr('Enter a label'); return; }
		syncCellInputs(activeCell, label, color);
		openDock(activeCell);
	});

	$('#ttSpClear').on('click', function () {
		if (!activeCell) return;
		syncCellInputs(activeCell, '', '');
		setDockIdle();
	});
	$('#ttSpCancel, #ttDockClose').on('click', function () {
		setDockIdle();
	});

	$('#timetableSpecialForm').on('submit', function (e) {
		e.preventDefault();
		var payload = {
			track_key: $('#ttTrackKeySpecial').val(),
			special_day: [], special_slot: [], special_label: [], special_color: []
		};
		$('.tt-we-cell.has-special').each(function () {
			payload.special_day.push($(this).data('day'));
			payload.special_slot.push($(this).data('slot'));
			payload.special_label.push($(this).find('.tt-we-label').text());
			payload.special_color.push($(this).data('color') || 'yellow');
		});
		$.post('<?= site_url('timetable/save_special_times'); ?>', payload, function (r) {
			if (r.success) toastOk(r.success);
			else toastErr(r.error || 'Save failed');
		}, 'json').fail(function () { toastErr('Save failed'); });
	});

	function syncScheduleModeFields() {
		var shared = $('#ttScheduleMode').val() === 'shared';
		$('#ttSharedFlagPeriods').val(shared ? '1' : '0');
		if (shared) {
			$('#ttTrackKeyPeriods, #ttTrackKeySpecial').val('all');
			$('#ttTrackPicker').addClass('d-none');
		} else {
			var tk = $('#ttTrackSelect').val() || 'primary';
			$('#ttTrackKeyPeriods, #ttTrackKeySpecial').val(tk);
			$('#ttTrackPicker').removeClass('d-none');
		}
	}

	$('#ttScheduleMode').on('change', function () {
		syncScheduleModeFields();
		reloadTimetableSettings();
	});

	function reloadTimetableSettings() {
		var url = new URL(window.location.href);
		var shared = $('#ttScheduleMode').val() === 'shared';
		url.searchParams.set('tt_shared', shared ? '1' : '0');
		url.searchParams.set('tt_track', shared ? 'all' : ($('#ttTrackSelect').val() || 'primary'));
		url.hash = 'timetable-settings';
		window.location.href = url.toString();
	}

	$('#ttTrackSelect').on('change', reloadTimetableSettings);

	if (window.location.hash === '#timetable-settings') {
		$('a[href="#ttTabSpecial"]').tab('show');
	}

	if (window.location.hash === '#timetable-settings' || /[?&]tt_track=/.test(window.location.search)) {
		$('#collapseTimetable').collapse('show');
	}
	syncScheduleModeFields();
})();
</script>
