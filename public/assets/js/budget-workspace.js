/**

 * Wisdom Schools — web budget workspace (no Excel required)

 */

var BudgetWorkspace = (function () {

	var cfg = { canEdit: true };

	var saveTimer = null;

	var dirty = false;

	var tabOrder = ['setup', 'plan', 'monthly', 'summary'];



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



	function calcLineAnnual($line) {

		var mode = $line.find('.calc-mode-input').val() || 'manual';

		var qty = parseFloat($line.find('.inp-qty:visible').val()) || parseFloat($line.find('.inp-qty').first().val()) || 0;

		var cost = parseFloat($line.find('.inp-cost:visible').val()) || parseFloat($line.find('.inp-cost').first().val()) || 0;

		var freq = parseFloat($line.find('.inp-freq').val()) || 1;

		var monthly = parseFloat($line.find('.inp-monthly:visible').val()) || parseFloat($line.find('.inp-annual:visible').val()) || 0;

		if (mode === 'monthly_grid') {

			var sum = 0;

			$line.find('.inp-month').each(function () { sum += parseFloat($(this).val()) || 0; });

			return sum;

		}

		if (mode === 'monthly') return monthly * 12;

		if (mode === 'qty_unit_freq') return qty * cost * freq;

		return monthly;

	}



	function syncSpreadInputs(lineId) {

		$('.spread-month[data-line="' + lineId + '"]').each(function () {

			var m = $(this).data('month');

			var v = $(this).val();

			$('.budget-line[data-line-id="' + lineId + '"] .inp-month[data-month="' + m + '"]').val(v);

		});

	}



	function refreshUI() {

		var income = 0, expense = 0, filled = 0, total = 0;

		var sectionTotals = {};



		$('.budget-line').each(function () {

			total++;

			var annual = calcLineAnnual($(this));

			$(this).find('.line-annual-display').text(fmt(annual));

			if (annual > 0) filled++;

			var sec = $(this).data('section');

			if (!sectionTotals[sec]) sectionTotals[sec] = 0;

			sectionTotals[sec] += annual;

			if ($(this).data('income') == 1) income += annual; else expense += annual;

		});



		$.each(sectionTotals, function (sec, amt) {

			$('[data-section-total="' + sec + '"]').text(fmt(amt) + ' RWF');

		});



		var surplus = income - expense;

		$('#kpiIncome,#sumIncome').text(fmt(income));

		$('#kpiExpense,#sumExpense').text(fmt(expense));

		$('#kpiSurplus,#summarySurplus').text(fmt(surplus));

		$('#kpiSurplus').closest('.bp-kpi').toggleClass('pos', surplus >= 0).toggleClass('neg', surplus < 0);



		var pct = total ? Math.round((filled / total) * 100) : 0;

		$('#kpiProgress').text(pct + '%');

		$('#progressBar').css('width', pct + '%');



		$('.monthly-spread-row').each(function () {

			var lid = $(this).data('line-id');

			var sum = 0;

			$(this).find('.spread-month').each(function () { sum += parseFloat($(this).val()) || 0; });

			$(this).find('.spread-row-total').text(fmt(sum));

			var $plan = $('.budget-line[data-line-id="' + lid + '"]');

			if ($plan.length) {

				$plan.find('.line-annual-display').text(fmt(sum));

				$plan.find('.calc-mode-input').val('monthly_grid');

				$plan.find('.mode-pill[data-mode="monthly_grid"]').addClass('active').siblings().removeClass('active');

			}

		});



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



		$('#summaryAlert').toggleClass('alert-success', surplus >= 0).toggleClass('alert-warning', surplus < 0);

	}



	function markDirty() {

		if (!cfg.canEdit) return;

		dirty = true;

		$('#saveStatus').removeClass('saved').html('<i class="fa fa-pencil-alt"></i> Unsaved changes');

		clearTimeout(saveTimer);

		saveTimer = setTimeout(saveAll, 4000);

	}



	function saveAll(callback) {

		if (!cfg.canEdit) { if (callback) callback({}); return; }

		$('.monthly-spread-row').each(function () {

			syncSpreadInputs($(this).data('line-id'));

		});

		var $form = $('#frmBudgetWorkspace');

		$.post(cfg.saveUrl, $form.serialize(), function (r) {

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

			enrollment: $('#setupEnrollment').val(),

			opening_cash: $('#setupOpeningCash').val(),

			planning_notes: $('#setupNotes').val()

		}, function (r) {

			if (r.error) { toastada.error(r.error); return; }

			toastada.success(r.success);

		}, 'json');

	}



	function submitBudget() {

		if (!confirm('Submit this budget for approval?\n\nIt will go to Procurement → Budget Manager → Deputy Director of Finance.')) return;

		saveAll(function () {

			$.post(cfg.submitUrl, { budget_id: cfg.budgetId }, function (r) {

				if (r.error) { toastada.error(r.error); return; }

				toastada.success('Submitted for approval');

				location.href = cfg.redirectUrl;

			}, 'json');

		});

	}



	function setMode(lineId, mode) {

		var $line = $('.budget-line[data-line-id="' + lineId + '"]');

		$line.find('.calc-mode-input').val(mode);

		$line.find('.mode-pill').removeClass('active');

		$line.find('.mode-pill[data-mode="' + mode + '"]').addClass('active');

		$line.find('.mode-fields').hide().find('input, select').prop('disabled', true);

		$line.find('.mode-' + mode).show().find('input, select').prop('disabled', false);

		markDirty();

		refreshUI();

	}



	return {

		init: function (config) {

			cfg = config;

			if (cfg.canEdit === undefined) cfg.canEdit = true;



			$('.bp-step, .bp-tab').on('click', function () {

				goToTab($(this).data('tab'));

			});

			$('.btnNext').on('click', function () {

				var next = $(this).data('next');

				if (dirty && cfg.canEdit) {

					saveAll(function () { goToTab(next); });

				} else {

					goToTab(next);

				}

			});

			$('.btnPrev').on('click', function () {

				goToTab($(this).data('prev'));

			});



			$('.bp-section-head').on('click', function () {

				$(this).closest('.bp-section').toggleClass('collapsed');

			});

			$(document).on('click', '.mode-pill', function () {

				setMode($(this).data('line'), $(this).data('mode'));

			});

			$(document).on('input change', '.budget-line input, .budget-line select, .spread-month', function () {

				var $row = $(this).closest('.monthly-spread-row');

				if ($row.length) {

					var lid = $row.data('line-id');

					syncSpreadInputs(lid);

					$('.budget-line[data-line-id="' + lid + '"] .calc-mode-input').val('monthly_grid');

				}

				markDirty();

				refreshUI();

			});

			$('#btnSave').on('click', function () { saveAll(function () { toastada.success('Budget saved'); }); });

			$('#btnSaveSetup').on('click', saveSetup);

			$('#btnSubmit, #btnSubmitSummary').on('click', submitBudget);

			refreshUI();

			$('.budget-line').each(function () {

				var mode = $(this).find('.calc-mode-input').val();

				setMode($(this).data('line-id'), mode);

			});

		},

		goToTab: goToTab

	};

})();

