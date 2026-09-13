(function ($) {
	'use strict';

	var dragPayload = null;
	var dropHandled = false;

	function getSheet($el) {
		return $el.closest('.tt-sheet-editable');
	}

	function scheduleId($sheet) {
		return parseInt($sheet.data('schedule-id'), 10) || 0;
	}

	function urls($sheet) {
		return {
			check: $sheet.data('check-url'),
			move: $sheet.data('move-url')
		};
	}

	function readPayload($el) {
		return {
			entryId: parseInt($el.data('entry-id'), 10) || 0,
			staffId: parseInt($el.data('staff-id'), 10) || 0,
			classId: parseInt($el.data('class-id'), 10) || 0,
			course: $.trim($el.find('.tt-course').text() || $el.find('strong').text()),
			line2: $.trim($el.find('.tt-sub').text() || $el.find('span').last().text()),
			code: $.trim($el.find('.tt-code').text())
		};
	}

	function showConflict($sheet, messages) {
		var $banner = $sheet.find('#ttConflictBanner');
		if (!$banner.length) return;
		if (!messages || !messages.length) {
			$banner.addClass('d-none').empty();
			return;
		}
		$banner.removeClass('d-none').html('<strong>Conflict:</strong> ' + messages.join(' '));
	}

	function chipHtml(payload) {
		var sub = payload.line2
			? '<span>' + $('<span>').text(payload.line2).html() + '</span>'
			: '';
		return '<strong>' + $('<span>').text(payload.course || 'Lesson').html() + '</strong>' + sub;
	}

	function buildLessonInner(payload) {
		var grip = '<span class="tt-drag-grip" title="Drag to move">⋮⋮</span>';
		var code = payload.code ? '<div class="tt-code">' + $('<span>').text(payload.code).html() + '</div>' : '';
		var sub = payload.line2 ? '<div class="tt-sub">' + $('<span>').text(payload.line2).html() + '</div>' : '';
		return grip
			+ '<div class="tt-course">' + $('<span>').text(payload.course).html() + '</div>'
			+ sub + code;
	}

	function makeEmptyCell($cell) {
		$cell.removeClass('tt-draggable-lesson tt-cell-occupied tt-has-conflict tt-dragging')
			.removeAttr('draggable data-entry-id data-staff-id data-class-id')
			.addClass('tt-drop-target tt-cell-free')
			.empty();
	}

	function makeLessonCell($cell, payload, day, slotId) {
		$cell.removeClass('tt-drop-target tt-cell-free tt-drop-conflict tt-drag-over')
			.addClass('tt-lesson-cell tt-cell-occupied tt-draggable-lesson')
			.attr({
				draggable: 'true',
				'data-entry-id': payload.entryId,
				'data-staff-id': payload.staffId,
				'data-class-id': payload.classId,
				'data-day': day,
				'data-slot-id': slotId
			})
			.html(buildLessonInner(payload));
	}

	function primaryParking($sheet) {
		return $sheet.find('#ttStagingDockBottom .tt-staging-items').first();
	}

	function mirrorParking($sheet) {
		return $sheet.find('#ttStagingDock .tt-staging-items-mirror').first();
	}

	function hidePlaceholder($items) {
		$items.find('.tt-staging-placeholder').remove();
	}

	function syncParkingMirrors($sheet) {
		var $primary = primaryParking($sheet);
		var $mirror = mirrorParking($sheet);
		var chips = $primary.find('.tt-staging-chip');
		if (chips.length) {
			$mirror.empty();
			chips.clone(true).appendTo($mirror);
		} else if (!$mirror.find('.tt-staging-placeholder').length) {
			$mirror.html('<div class="tt-staging-placeholder">Drop a lesson from the grid here to unschedule it</div>');
		}
		if (!chips.length && !$primary.find('.tt-staging-placeholder').length) {
			$primary.html('<div class="tt-staging-placeholder">All assigned course periods are on the timetable</div>');
		}
	}

	function addStagingChip($sheet, payload) {
		var $items = primaryParking($sheet);
		hidePlaceholder($items);
		if ($sheet.find('.tt-staging-chip[data-entry-id="' + payload.entryId + '"]').length) {
			syncParkingMirrors($sheet);
			return;
		}
		var $chip = $('<div class="tt-staging-chip tt-lesson-chip" draggable="true"></div>');
		$chip.attr({
			'data-entry-id': payload.entryId,
			'data-staff-id': payload.staffId,
			'data-class-id': payload.classId,
			title: payload.course
		});
		$chip.html(chipHtml(payload));
		$items.append($chip);
		syncParkingMirrors($sheet);
	}

	function removeStagingChip($sheet, entryId) {
		$sheet.find('.tt-staging-chip[data-entry-id="' + entryId + '"]').remove();
		var $items = primaryParking($sheet);
		if (!$items.find('.tt-staging-chip').length) {
			$items.html('<div class="tt-staging-placeholder">All assigned course periods are on the timetable</div>');
		}
		syncParkingMirrors($sheet);
	}

	function checkMove($sheet, entryId, day, slotId) {
		return $.post(urls($sheet).check, {
			schedule_id: scheduleId($sheet),
			entry_id: entryId,
			day: day,
			slot_id: slotId
		});
	}

	function commitMove($sheet, entryId, day, slotId, onOk, onFail) {
		$.post(urls($sheet).move, {
			schedule_id: scheduleId($sheet),
			entry_id: entryId,
			day: day,
			slot_id: slotId
		}, function (r) {
			if (r.error) {
				var msgs = (r.conflicts || []).map(function (c) { return c.message; });
				showConflict($sheet, msgs.length ? msgs : [r.error]);
				if (typeof onFail === 'function') onFail();
				return;
			}
			showConflict($sheet, []);
			if (typeof onOk === 'function') onOk();
		}, 'json').fail(function () {
			showConflict($sheet, ['Save failed — please refresh and try again.']);
			if (typeof onFail === 'function') onFail();
		});
	}

	function clearHighlights($sheet) {
		$sheet.removeClass('tt-is-dragging');
		$sheet.find('.tt-drag-over, .tt-drop-conflict').removeClass('tt-drag-over tt-drop-conflict');
		$sheet.find('[data-drop-zone="parking"]').removeClass('tt-drag-over');
	}

	function isParkingTarget($el) {
		return $el.closest('[data-drop-zone="parking"]').length > 0;
	}

	function isGridDropTarget($el) {
		return $el.closest('.tt-drop-target').length > 0;
	}

	function parkInStaging($sheet, payload) {
		var $source = payload.source;
		commitMove($sheet, payload.entryId, -1, 0, function () {
			if ($source && $source.hasClass('tt-draggable-lesson')) {
				makeEmptyCell($source);
			} else if ($source && $source.hasClass('tt-staging-chip')) {
				/* already in parking */
			}
			addStagingChip($sheet, payload);
		});
	}

	function handleGridDrop($target, payload) {
		var $sheet = getSheet($target);
		if ($target.hasClass('tt-drop-conflict')) {
			showConflict($sheet, ['Cannot place here — teacher or class conflict.']);
			return;
		}
		var day = parseInt($target.data('day'), 10);
		var slotId = parseInt($target.data('slot-id'), 10);
		var $source = payload.source;

		commitMove($sheet, payload.entryId, day, slotId, function () {
			if ($source && $source.hasClass('tt-draggable-lesson')) {
				makeEmptyCell($source);
			} else if ($source && $source.hasClass('tt-staging-chip')) {
				$source.remove();
			}
			makeLessonCell($target, payload, day, slotId);
			removeStagingChip($sheet, payload.entryId);
		});
	}

	function initEditable($root) {
		var $scope = $root && $root.length ? $root : $(document);
		$scope.find('.tt-sheet-editable').each(function () {
			var $sheet = $(this);
			if ($sheet.data('tt-live-init')) return;
			$sheet.data('tt-live-init', true);
			syncParkingMirrors($sheet);
		});
	}

	/* --- drag start / end --- */
	$(document).on('dragstart', '.tt-sheet-editable .tt-draggable-lesson, .tt-sheet-editable .tt-staging-chip', function (e) {
		dropHandled = false;
		var $el = $(this);
		dragPayload = readPayload($el);
		dragPayload.source = $el;
		$el.addClass('tt-dragging');
		getSheet($el).addClass('tt-is-dragging');
		e.originalEvent.dataTransfer.setData('text/plain', String(dragPayload.entryId));
		e.originalEvent.dataTransfer.effectAllowed = 'move';
	});

	$(document).on('dragend', '.tt-sheet-editable .tt-draggable-lesson, .tt-sheet-editable .tt-staging-chip', function (e) {
		var $el = $(this);
		var $sheet = getSheet($el);
		$el.removeClass('tt-dragging');
		clearHighlights($sheet);

		if (!dropHandled && dragPayload && dragPayload.entryId && dragPayload.source) {
			var $target = $(document.elementFromPoint(e.originalEvent.clientX, e.originalEvent.clientY));
			if (!isGridDropTarget($target) && !isParkingTarget($target)) {
				/* dropped outside grid and outside parking — auto-park so lesson is not lost */
				if (dragPayload.source.hasClass('tt-draggable-lesson')) {
					parkInStaging($sheet, dragPayload);
					dropHandled = true;
				}
			}
		}

		dragPayload = null;
	});

	/* --- parking lot (top + bottom) — accept drops on any child --- */
	$(document).on('dragover dragenter', '.tt-sheet-editable [data-drop-zone="parking"], .tt-sheet-editable [data-drop-zone="parking"] *', function (e) {
		e.preventDefault();
		e.stopPropagation();
		if (e.type === 'dragover') {
			e.originalEvent.dataTransfer.dropEffect = 'move';
		}
		$(this).closest('[data-drop-zone="parking"]').addClass('tt-drag-over');
	});

	$(document).on('dragleave', '.tt-sheet-editable [data-drop-zone="parking"]', function (e) {
		var related = e.relatedTarget;
		if (!related || !this.contains(related)) {
			$(this).removeClass('tt-drag-over');
		}
	});

	$(document).on('drop', '.tt-sheet-editable [data-drop-zone="parking"], .tt-sheet-editable [data-drop-zone="parking"] *', function (e) {
		e.preventDefault();
		e.stopPropagation();
		if (!dragPayload || !dragPayload.entryId) return;
		dropHandled = true;
		var $sheet = getSheet($(this));
		clearHighlights($sheet);
		parkInStaging($sheet, dragPayload);
	});

	/* --- grid empty cells --- */
	$(document).on('dragover dragenter', '.tt-sheet-editable .tt-drop-target', function (e) {
		e.preventDefault();
		e.stopPropagation();
		if (e.type === 'dragover') {
			e.originalEvent.dataTransfer.dropEffect = 'move';
		}
		var $t = $(this);
		$t.addClass('tt-drag-over');
		if (dragPayload) {
			checkMove(getSheet($t), dragPayload.entryId, parseInt($t.data('day'), 10), parseInt($t.data('slot-id'), 10))
				.done(function (r) {
					$t.toggleClass('tt-drop-conflict', !r.ok);
				});
		}
	});

	$(document).on('dragleave', '.tt-sheet-editable .tt-drop-target', function (e) {
		var related = e.relatedTarget;
		if (!related || !this.contains(related)) {
			$(this).removeClass('tt-drag-over tt-drop-conflict');
		}
	});

	$(document).on('drop', '.tt-sheet-editable .tt-drop-target', function (e) {
		e.preventDefault();
		e.stopPropagation();
		if (!dragPayload || !dragPayload.entryId) return;
		dropHandled = true;
		clearHighlights(getSheet($(this)));
		handleGridDrop($(this), dragPayload);
	});

	window.TtLiveEdit = { init: initEditable };

	$(function () {
		initEditable($(document));
	});
})(jQuery);
