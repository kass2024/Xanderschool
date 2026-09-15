(function (window, $) {
	function wordsMatch(hay, query) {
		hay = String(hay || '').toLowerCase();
		return String(query || '').toLowerCase().trim().split(/\s+/).every(function (word) {
			return !word || hay.indexOf(word) !== -1;
		});
	}

	function esc(text) {
		return $('<div>').text(String(text == null ? '' : text)).html();
	}

	function bind($root) {
		var $select = $root.find('select').first();
		var $input = $root.find('.tt-live-pick-q');
		var $menu = $root.find('.tt-live-pick-menu');
		if (!$select.length || !$input.length || !$menu.length) {
			return;
		}

		function options() {
			return $select.find('option').map(function () {
				return {
					value: String(this.value || ''),
					label: $(this).text(),
					search: String($(this).attr('data-search') || $(this).text())
				};
			}).get();
		}

		function selectedLabel() {
			var $opt = $select.find('option:selected');
			return $opt.length ? $.trim($opt.text()) : '';
		}

		function render(query) {
			var html = '';
			var count = 0;
			options().forEach(function (opt) {
				if (!wordsMatch(opt.search + ' ' + opt.label, query)) {
					return;
				}
				count += 1;
				var active = String($select.val() || '') === opt.value ? ' is-active' : '';
				html += '<button type="button" class="tt-live-pick-item' + active + '" data-value="' + esc(opt.value) + '">'
					+ esc(opt.label) + '</button>';
			});
			if (!html) {
				html = '<div class="tt-live-pick-empty">No match. Try another name.</div>';
			}
			$menu.html(html).prop('hidden', false);
			$root.attr('data-match-count', String(count));
		}

		function choose(value) {
			$select.val(String(value));
			$input.val(selectedLabel());
			$menu.prop('hidden', true);
			// Always notify — preview must reload even when the same class is re-picked
			// after a failed/empty server render.
			$select.trigger('change');
		}

		$input.val(selectedLabel());
		$input.on('focus', function () {
			this.select();
			render($input.val() === selectedLabel() ? '' : $input.val());
		});
		$input.on('input', function () {
			render(this.value);
		});
		$input.on('keydown', function (e) {
			if (e.key === 'Escape') {
				$menu.prop('hidden', true);
				$input.val(selectedLabel());
				$input.blur();
				return;
			}
			if (e.key !== 'Enter' && e.key !== 'ArrowDown') {
				return;
			}
			e.preventDefault();
			var $first = $menu.find('.tt-live-pick-item').first();
			if ($first.length) {
				choose($first.attr('data-value'));
			}
		});
		$menu.on('mousedown', '.tt-live-pick-item', function (e) {
			e.preventDefault();
			choose($(this).attr('data-value'));
		});
		$input.on('blur', function () {
			setTimeout(function () {
				$menu.prop('hidden', true);
				$input.val(selectedLabel());
			}, 180);
		});
		$select.on('change.ttLivePick', function () {
			$input.val(selectedLabel());
		});
	}

	window.TtLivePick = {
		init: function (selector) {
			$(selector || '[data-tt-live-pick]').each(function () {
				bind($(this));
			});
		}
	};
})(window, window.jQuery);
