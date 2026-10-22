<div class="bp-term-switch mb-3" id="budgetTermSwitch">
	<span class="small font-weight-bold text-muted mr-1">View</span>
	<button type="button" class="btn btn-sm btn-primary" data-view-term="0">Full year</button>
	<button type="button" class="btn btn-sm btn-outline-primary" data-view-term="1">Term I</button>
	<button type="button" class="btn btn-sm btn-outline-primary" data-view-term="2">Term II</button>
	<button type="button" class="btn btn-sm btn-outline-primary" data-view-term="3">Term III</button>
</div>
<script>
(function () {
	if (window.wisdomTermViewReady) return;
	window.wisdomTermViewReady = true;
	function money(n) {
		n = Math.round(Number(n) || 0);
		return n.toLocaleString('en-US');
	}
	function applyTermView(term) {
		term = parseInt(term, 10) || 0;
		if (term < 0 || term > 3) term = 0;
		document.body.classList.add('bp-term-live');
		$('#budgetTermSwitch button').each(function () {
			var on = parseInt($(this).attr('data-view-term'), 10) === term;
			$(this).toggleClass('btn-primary', on).toggleClass('btn-outline-primary', !on);
		});
		$('.bp-term-card').each(function () {
			var n = parseInt($(this).attr('data-term'), 10);
			$(this).toggleClass('is-on', term > 0 && n === term);
			$(this).toggleClass('is-off', term > 0 && n !== term);
		});
		var key = term === 0 ? 'y' : ('t' + term);
		$('.term-money').each(function () {
			var raw = $(this).attr('data-' + key);
			if (raw === undefined || raw === '') return;
			$(this).text(money(raw));
		});
		$('.term-surplus').each(function () {
			var raw = Number($(this).attr('data-' + key) || 0);
			$(this).toggleClass('pos', raw >= 0).toggleClass('neg', raw < 0);
			$(this).toggleClass('text-success', raw >= 0).toggleClass('text-danger', raw < 0);
		});
		$('.js-surplus-kpi').each(function () {
			var raw = Number($(this).find('.term-money').attr('data-' + key) || 0);
			$(this).toggleClass('income', raw >= 0).toggleClass('expense', raw < 0);
		});
		$('.term-label').each(function () {
			var text = $(this).attr(term === 0 ? 'data-label-y' : ('data-label-' + term));
			if (text) $(this).text(text);
		});
		$('.term-line').each(function () {
			var $tr = $(this);
			var budget = Number($tr.attr(term === 0 ? 'data-budget-y' : ('data-budget-t' + term)) || 0);
			var used = Number($tr.attr(term === 0 ? 'data-used-y' : ('data-used-t' + term)) || 0);
			var variance = budget - used;
			var pct = budget > 0 ? Math.round((used / budget) * 1000) / 10 : 0;
			$tr.find('.js-budget').text(money(budget));
			$tr.find('.js-used').text(money(used));
			$tr.find('.js-var').text(money(variance)).toggleClass('text-danger', variance < 0).toggleClass('text-success', variance >= 0);
			$tr.find('.js-pct').text(pct + '%');
			$tr.toggle(term === 0 || budget !== 0);
		});
		$('a[href*="budget/dashboard"]').each(function () {
			var href = String($(this).attr('href') || '').split('?')[0];
			$(this).attr('href', term > 0 ? (href + '?term=' + term) : href);
		});
		try { sessionStorage.setItem('wisdomBudgetTerm', String(term)); } catch (e) {}
		if (window.history && window.history.replaceState) {
			var url = new URL(window.location.href);
			if (term > 0) url.searchParams.set('term', String(term));
			else url.searchParams.delete('term');
			window.history.replaceState(null, '', url.pathname + url.search);
		}
	}
	$(document).on('click', '#budgetTermSwitch button', function () {
		applyTermView($(this).attr('data-view-term'));
	});
	$(document).on('click', '.bp-term-card[data-term]', function () {
		if (!document.body.classList.contains('bp-term-live')) return;
		var n = parseInt($(this).attr('data-term'), 10) || 0;
		var current = parseInt($('#budgetTermSwitch button.btn-primary').attr('data-view-term'), 10) || 0;
		applyTermView(current === n ? 0 : n);
	});
	window.applyBudgetTermView = applyTermView;
	$(function () {
		var start = 0;
		try {
			var q = new URLSearchParams(window.location.search).get('term');
			start = parseInt(q || sessionStorage.getItem('wisdomBudgetTerm') || '0', 10) || 0;
		} catch (e2) {}
		if (start >= 1 && start <= 3) applyTermView(start);
		else document.body.classList.add('bp-term-live');
	});
})();
</script>
