<?php
/** @var array $attendance_areas */
$areas = $attendance_areas ?? [];
?>
<style>
	.aa-intro { margin-bottom: 1rem; }
	.aa-add-form { display: flex; gap: .5rem; align-items: stretch; max-width: 520px; margin-bottom: 1rem; }
	.aa-add-form input { flex: 1; }
	.aa-list { max-width: 560px; }
	.aa-item {
		display: flex; align-items: center; justify-content: space-between; gap: .75rem;
		padding: .65rem .85rem; background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: .45rem;
	}
	.aa-item strong { color: #0f172a; }
	.aa-empty { color: #64748b; padding: .5rem 0; }
</style>

<div id="aaSettings">
	<p class="text-muted aa-intro">
		Define locations used on <strong>Student IN/OUT Attendance</strong> (NFC card scanner), for example
		Library, Cafeteria, School gate. Staff must choose a location before scanning.
		The monthly in/out report uses the same layout for every location — pick the location to filter.
	</p>

	<form id="aaAddForm" class="aa-add-form">
		<input type="text" class="form-control" id="aaName" placeholder="Location name (e.g. Library)" required maxlength="120">
		<button type="submit" class="btn btn-success"><i class="fa fa-plus"></i> Add</button>
	</form>

	<div class="aa-list" id="aaList">
		<?php if (empty($areas)) : ?>
			<p class="aa-empty">No locations yet. Add Library, Cafeteria, or any location above.</p>
		<?php else : ?>
			<?php foreach ($areas as $a) : ?>
				<div class="aa-item" data-id="<?= (int) $a['id']; ?>">
					<strong><?= esc($a['name']); ?></strong>
					<button type="button" class="btn btn-link btn-sm text-danger aa-del" data-id="<?= (int) $a['id']; ?>" title="Remove">
						<i class="fa fa-trash"></i>
					</button>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
</div>

<script>
$(function () {
	let areas = <?= json_encode(array_map(static function ($a) {
		return ['id' => (int) $a['id'], 'name' => $a['name']];
	}, $areas), JSON_UNESCAPED_UNICODE); ?>;

	function renderList() {
		const $el = $('#aaList');
		if (!areas.length) {
			$el.html('<p class="aa-empty">No locations yet. Add Library, Cafeteria, or any location above.</p>');
			return;
		}
		let html = '';
		areas.forEach(function (a) {
			html += '<div class="aa-item" data-id="' + a.id + '">' +
				'<strong>' + $('<span>').text(a.name).html() + '</strong>' +
				'<button type="button" class="btn btn-link btn-sm text-danger aa-del" data-id="' + a.id + '"><i class="fa fa-trash"></i></button></div>';
		});
		$el.html(html);
	}

	$('#aaAddForm').on('submit', function (e) {
		e.preventDefault();
		const name = $('#aaName').val().trim();
		if (!name) return;
		$.post('<?= base_url('manipulate_attendance_area'); ?>', { action: 'add', name: name }, function (res) {
			if (res.success && res.area) {
				areas.push(res.area);
				renderList();
				$('#aaName').val('');
				if (window.toastada) toastada.success(res.success);
			} else if (window.toastada) {
				toastada.error((res && res.error) || 'Could not add location.');
			}
		}, 'json');
	});

	$(document).on('click', '.aa-del', function () {
		const id = $(this).data('id');
		if (!confirm('Remove this attendance location? Existing scans stay in reports.')) return;
		$.post('<?= base_url('manipulate_attendance_area'); ?>', { action: 'delete', id: id }, function (res) {
			if (res.success) {
				areas = areas.filter(function (a) { return a.id !== id; });
				renderList();
				if (window.toastada) toastada.success(res.success);
			} else if (window.toastada) {
				toastada.error((res && res.error) || 'Could not remove location.');
			}
		}, 'json');
	});
});
</script>
