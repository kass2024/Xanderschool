/**
 * Wisdom Schools — web budget workspace (3-term annual, no Excel)
 */
var BudgetWorkspace = (function () {
	var cfg = { canEdit: true, canAddLines: false };
	var saveTimer = null;
	var dirty = false;
	var tabOrder = ['setup', 'plan', 'summary'];

	function fmt(n) {
		return Math.round(n || 0).toLocaleString();
	}

	function goToTab(tab) {
		$('.bp-tab, .bp-step').removeClass('active done');
		var idx = tabOrder.indexOf(tab);
		tabOrder.forEach(function (t, i) {
			var $step = $('.bp-step[data-tab="' + t + '"]');
			if (i < idx) $step.addClass('done');
			if (t === tab) $step.addClass('active');
		});
		$('.bp-panel').removeClass('active');
		$('#panel-' + tab).addClass('active');
		window.scrollTo({ top: 0, behavior: 'smooth' });
	}

	function lineTerms($row) {
		var t1 = parseFloat($row.find('.inp-term-1').val()) || 0;
		var t2 = parseFloat($row.find('.inp-term-2').val()) || 0;
		var t3 = parseFloat($row.find('.inp-term-3').val()) || 0;
		return { t1: t1, t2: t2, t3: t3, annual: t1 + t2 + t3 };
	}

	function anyAmountFilled() {
		var any = false;
		$('.budget-line').each(function () {
			if (lineTerms($(this)).annual > 0) { any = true; return false; }
		});
		return any;
	}

	function refreshUI() {
		var income = 0, expense = 0, filled = 0, total = 0;
		var sectionTotals = {};
		var termExp = { t1: 0, t2: 0, t3: 0 };
		var termInc = { t1: 0, t2: 0, t3: 0 };

		$('.budget-line').each(function () {
			total++;
			var terms = lineTerms($(this));
			if (terms.annual > 0) filled++;
			$(this).find('.line-annual-display').text(fmt(terms.annual));

			var sec = $(this).data('section');
			if (!sectionTotals[sec]) sectionTotals[sec] = 0;
			sectionTotals[sec] += terms.annual;

			var isInc = $(this).data('income') == 1;
			if (isInc) {
				income += terms.annual;
				termInc.t1 += terms.t1; termInc.t2 += terms.t2; termInc.t3 += terms.t3;
			} else {
				expense += terms.annual;
				termExp.t1 += terms.t1; termExp.t2 += terms.t2; termExp.t3 += terms.t3;
			}

			var $sec = $(this).closest('.bp-term-section');
			if ($sec.length) {
				var sKey = sec;
				var st1 = 0, st2 = 0, st3 = 0;
				$sec.find('.budget-line').each(function () {
					var t = lineTerms($(this));
					st1 += t.t1; st2 += t.t2; st3 += t.t3;
				});
				$sec.find('.section-t1[data-section="' + sKey + '"]').text(fmt(st1));
				$sec.find('.section-t2[data-section="' + sKey + '"]').text(fmt(st2));
				$sec.find('.section-t3[data-section="' + sKey + '"]').text(fmt(st3));
				$sec.find('[data-section-total-foot="' + sKey + '"]').text(fmt(st1 + st2 + st3));
			}
		});

		$.each(sectionTotals, function (sec, amt) {
			$('[data-section-total="' + sec + '"]').text(fmt(amt) + ' RWF');
			$('.total-row[data-section="' + sec + '"] .line-annual-display').text(fmt(amt));
		});

		var surplus = income - expense;
		$('#kpiIncome,#sumIncome').text(fmt(income));
		$('#kpiExpense,#sumExpense').text(fmt(expense));
		$('#kpiSurplus,#summarySurplus').text(fmt(surplus));
		$('#kpiSurplus').closest('.bp-kpi').toggleClass('pos', surplus >= 0).toggleClass('neg', surplus < 0);

		$('#footTerm1').text(fmt(termExp.t1));
		$('#footTerm2').text(fmt(termExp.t2));
		$('#footTerm3').text(fmt(termExp.t3));
		$('#footAnnualExp').text(fmt(expense));

		$('#sumT1Inc').text(fmt(termInc.t1)); $('#sumT1Exp').text(fmt(termExp.t1)); $('#sumT1Net').text(fmt(termInc.t1 - termExp.t1));
		$('#sumT2Inc').text(fmt(termInc.t2)); $('#sumT2Exp').text(fmt(termExp.t2)); $('#sumT2Net').text(fmt(termInc.t2 - termExp.t2));
		$('#sumT3Inc').text(fmt(termInc.t3)); $('#sumT3Exp').text(fmt(termExp.t3)); $('#sumT3Net').text(fmt(termInc.t3 - termExp.t3));
		$('#kpiT1Net').text(fmt(termInc.t1 - termExp.t1));
		$('#kpiT2Net').text(fmt(termInc.t2 - termExp.t2));
		$('#kpiT3Net').text(fmt(termInc.t3 - termExp.t3));

		var pct = total ? Math.round((filled / total) * 100) : 0;
		$('#kpiProgress').text(pct + '%');
		$('#progressBar').css('width', pct + '%');

		var $chart = $('#summaryChart').empty();
		$.each(sectionTotals, function (sec, amt) {
			if (amt <= 0) return;
			var isInc = sec.toUpperCase().indexOf('INCOME') >= 0;
			var max = Math.max(income, expense, 1);
			var w = Math.max(4, (amt / max) * 100);
			$chart.append(
				'<div class="bp-bar-row"><div class="bp-bar-label">' + sec + '</div>' +
				'<div class="bp-bar-track"><div class="bp-bar-fill ' + (isInc ? 'income' : 'expense') + '" style="width:' + w + '%">' + fmt(amt) + '</div></div></div>'
			);
		});
	}

	function markDirty() {
		if (!cfg.canEdit) return;
		dirty = true;
		$('#saveStatus').removeClass('saved').html('<i class="fa fa-pencil-alt"></i> Unsaved changes');
		clearTimeout(saveTimer);
		saveTimer = setTimeout(saveAll, 3000);
	}

	function saveAll(callback) {
		if (!cfg.canEdit) { if (callback) callback({}); return; }
		$.post(cfg.saveUrl, $('#frmBudgetWorkspace').serialize(), function (r) {
			if (r.error) { if (typeof toastada !== 'undefined') toastada.error(r.error); return; }
			dirty = false;
			$('#saveStatus').addClass('saved').html('<i class="fa fa-check"></i> Saved online');
			if (r.totals) refreshUI();
			if (callback) callback(r);
		}, 'json');
	}

	function saveSetup() {
		$.post(cfg.setupUrl, {
			budget_id: cfg.budgetId,
			title: $('#setupTitle').val(),
			academic_year: $('#setupAcademicYear').val(),
			enrollment: $('#setupEnrollment').val(),
			opening_cash: $('#setupOpeningCash').val(),
			planning_notes: $('#setupNotes').val()
		}, function (r) {
			if (r.error) { toastada.error(r.error); return; }
			toastada.success(r.success);
		}, 'json');
	}

	function submitBudget() {
		if (!anyAmountFilled()) {
			toastada.error('Enter at least one term amount before submitting. Empty lines can stay blank.');
			return;
		}
		if (!confirm('Submit this budget for approval?\n\nIt will stay in review until ALL THREE approve:\n1) Procurement\n2) Budget Manager\n3) Director of Finance\n\nApprovers will get SMS and email.')) return;
		saveAll(function () {
			$.post(cfg.submitUrl, { budget_id: cfg.budgetId }, function (r) {
				if (r.error) { toastada.error(r.error); return; }
				toastada.success(r.success || r.status ? 'Submitted — approvers notified' : 'Submitted for approval');
				location.href = cfg.redirectUrl;
			}, 'json');
		});
	}

	function setAddMode(mode) {
		$('#addLineMode').val(mode);
		$('.bp-mode-chip').removeClass('is-active');
		$('.bp-mode-chip[data-mode="' + mode + '"]').addClass('is-active');
		if (mode === 'section') {
			$('#addLineTitleWrap label').text('Optional first line title');
			$('#addLineCategory').prop('required', false).attr('placeholder', 'Optional — leave blank for section only');
			$('#addLineSectionCustom').removeClass('d-none');
		} else {
			$('#addLineTitleWrap label').text('Line title');
			$('#addLineCategory').prop('required', true).attr('placeholder', 'e.g. Laboratory supplies');
			$('#addLineSectionCustom').addClass('d-none');
		}
	}

	function openAddModal(section) {
		if (!cfg.canAddLines) return;
		setAddMode('line');
		if (section) {
			var $sel = $('#addLineSection');
			if ($sel.find('option[value="' + section.replace(/"/g, '\\"') + '"]').length) {
				$sel.val(section);
			} else {
				$sel.append($('<option>', { value: section, text: section, selected: true }));
			}
			$('#addLineSectionCustom').val('').addClass('d-none');
		}
		$('#addLineCategory').val('');
		$('#addLineAssumptions').val('');
		$('#mdlAddBudgetLine').modal('show');
		setTimeout(function () { $('#addLineCategory').focus(); }, 350);
	}

	function submitAddLine(e) {
		e.preventDefault();
		var mode = $('#addLineMode').val() || 'line';
		var customSec = $.trim($('#addLineSectionCustom').val() || '');
		var section = customSec !== '' ? customSec : ($('#addLineSection').val() || '');
		var category = $.trim($('#addLineCategory').val() || '');
		if (!section) { toastada.error('Section title is required'); return; }
		if (mode === 'line' && !category) { toastada.error('Line title is required'); return; }
		var $btn = $('#btnAddLineSubmit').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Adding…');
		$.post(cfg.addLineUrl, {
			budget_id: cfg.budgetId,
			mode: mode,
			section_label: section,
			category: category,
			assumptions: $('#addLineAssumptions').val() || ''
		}, function (r) {
			$btn.prop('disabled', false).html('<i class="fa fa-plus"></i> Add');
			if (r.error) { toastada.error(r.error); return; }
			toastada.success(r.success || 'Added');
			$('#mdlAddBudgetLine').modal('hide');
			if (r.reload) location.reload();
		}, 'json').fail(function () {
			$btn.prop('disabled', false).html('<i class="fa fa-plus"></i> Add');
			toastada.error('Could not add line');
		});
	}

	return {
		init: function (config) {
			cfg = config;
			if (cfg.canEdit === undefined) cfg.canEdit = true;
			if (cfg.canAddLines === undefined) cfg.canAddLines = false;

			$('.bp-step, .bp-tab').on('click', function () { goToTab($(this).data('tab')); });
			$('.btnNext').on('click', function () {
				var next = $(this).data('next');
				if (dirty && cfg.canEdit) saveAll(function () { goToTab(next); });
				else goToTab(next);
			});
			$('.btnPrev').on('click', function () { goToTab($(this).data('prev')); });

			$(document).on('input change', '.budget-line .inp-term, .budget-line input[name*="assumptions"]', function () {
				markDirty();
				refreshUI();
			});

			$('#btnSave').on('click', function () { saveAll(function () { toastada.success('Budget saved — empty lines are fine'); }); });
			$('#btnSaveSetup').on('click', saveSetup);
			$('#btnSubmit, #btnSubmitSummary').on('click', submitBudget);

			$('#btnFillExcel').on('click', function () {
				if (!cfg.fillExcelUrl || !confirm('Load amounts from Excel template into this budget?\nExisting term amounts will be overwritten for matching lines.')) return;
				$.post(cfg.fillExcelUrl, { budget_id: cfg.budgetId }, function (r) {
					if (r.error) { toastada.error(r.error); return; }
					toastada.success(r.success);
					location.reload();
				}, 'json');
			});

			$('#btnResetEmptyAmounts').on('click', function () {
				if (!cfg.resetEmptyUrl) return;
				if (!confirm('Restore all budget line items and clear ALL amounts (except School Fees from fees × students)?\n\nYou can then enter Term I–III amounts yourself.')) return;
				var $btn = $(this).prop('disabled', true);
				$.post(cfg.resetEmptyUrl, { budget_id: cfg.budgetId }, function (r) {
					if (r.error) {
						toastada.error(r.error);
						$btn.prop('disabled', false);
						return;
					}
					toastada.success(r.success || 'Lines restored with empty amounts');
					location.reload();
				}, 'json').fail(function () {
					toastada.error('Reset failed.');
					$btn.prop('disabled', false);
				});
			});

			$(document).on('click', '.btn-delete-line', function () {
				if (!cfg.deleteLineUrl || !cfg.canManageStructure) return;
				var $row = $(this).closest('.budget-line');
				var id = $row.data('line-id');
				var name = $row.find('.bp-line-name strong').text() || 'this line';
				if (!confirm('Delete “' + name + '”?\n\nThis also removes the same line from all child-school budgets.')) return;
				var $btn = $(this).prop('disabled', true);
				$.post(cfg.deleteLineUrl, { budget_id: cfg.budgetId, line_id: id }, function (r) {
					if (r.error) {
						toastada.error(r.error);
						$btn.prop('disabled', false);
						return;
					}
					toastada.success(r.success || 'Deleted');
					$row.slideUp(180, function () {
						$(this).remove();
						refreshUI();
					});
				}, 'json').fail(function () {
					toastada.error('Delete failed.');
					$btn.prop('disabled', false);
				});
			});

			$(document).on('click', '.btn-move-line', function () {
				if (!cfg.moveLineUrl || !cfg.canManageStructure || $(this).prop('disabled')) return;
				var dir = $(this).data('dir');
				var $row = $(this).closest('.budget-line');
				var id = $row.data('line-id');
				$.post(cfg.moveLineUrl, { budget_id: cfg.budgetId, line_id: id, direction: dir }, function (r) {
					if (r.error) { toastada.error(r.error); return; }
					location.reload();
				}, 'json').fail(function () {
					toastada.error('Move failed.');
				});
			});

			// Silent auto-sync School Fees from fees settings × students (always refresh on open)
			function autoRefreshSchoolFees() {
				if (!cfg.fillSchoolFeesUrl || !cfg.canEdit) return;
				var $row = $('.budget-line').filter(function () {
					var cat = String($(this).data('category') || '');
					return cat.indexOf('school fee') !== -1 || cat === 'fees';
				}).first();
				if (!$row.length) return;
				$.post(cfg.fillSchoolFeesUrl, { budget_id: cfg.budgetId, apply: 1 }, function (r) {
					if (r.error || !r.projection) return;
					var p = r.projection;
					var t1 = p.term_1 > 0 ? p.term_1 : '';
					var t2 = p.term_2 > 0 ? p.term_2 : '';
					var t3 = p.term_3 > 0 ? p.term_3 : '';
					var changed =
						String($row.find('.inp-term-1').val() || '') !== String(t1) ||
						String($row.find('.inp-term-2').val() || '') !== String(t2) ||
						String($row.find('.inp-term-3').val() || '') !== String(t3);
					$row.find('.inp-term-1').val(t1);
					$row.find('.inp-term-2').val(t2);
					$row.find('.inp-term-3').val(t3);
					if (p.notes) $row.find('input[name*="[assumptions]"]').val(p.notes);
					if (p.total_students && (!$('#setupEnrollment').val() || parseInt($('#setupEnrollment').val(), 10) === 0)) {
						$('#setupEnrollment').val(p.total_students);
					}
					refreshUI();
					if (changed) {
						dirty = false;
						$('#saveStatus').addClass('saved').html('<i class="fa fa-check"></i> School Fees synced from fees settings');
					}
				}, 'json');
			}
			autoRefreshSchoolFees();

			if (cfg.canAddLines) {
				$('#btnAddBudgetLine').on('click', function () { openAddModal(null); });
				$(document).on('click', '.bp-btn-add-line', function () {
					openAddModal($(this).data('section') || null);
				});
				$('.bp-mode-chip').on('click', function () { setAddMode($(this).data('mode')); });
				$('#frmAddBudgetLine').on('submit', submitAddLine);
				$('#addLineSection').on('change', function () {
					$('#addLineSectionCustom').val('');
				});
			}

			refreshUI();
		},
		goToTab: goToTab
	};
})();
