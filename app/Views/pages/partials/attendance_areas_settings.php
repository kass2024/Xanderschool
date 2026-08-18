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

<?php
$hs = $heystar_device ?? [];
$hsIp = (string) ($hs['device_ip'] ?? '');
$hsKey = (string) ($hs['device_key'] ?? '');
$hsPwd = (string) ($hs['password'] ?? 'HFSecurity');
$hsArea = (int) ($hs['area_id'] ?? 0);
$hsUpload = rtrim(base_url(), '/') . '/api/heystar_record';
?>
<hr class="my-4">
<h6>HeyStar terminal (assigned web cards)</h6>
<p class="text-muted">
	Students are identified only by the <strong>card assigned in Xander</strong> (Assign card).
	Staff are identified only by their <strong>school photo as a face</strong> — staff cards are not sent to the terminal.
	Sync needs the kiosk on the LAN with HeyStar running (port 8090). Both face and card clocks land in the same attendance reports.
</p>
<form id="hsForm" class="mb-3" style="max-width:560px">
	<label class="d-block mb-2">Device IP
		<input type="text" class="form-control" name="device_ip" id="hsIp" value="<?= esc($hsIp); ?>" placeholder="192.168.1.78">
	</label>
	<label class="d-block mb-2">Device key
		<input type="text" class="form-control" name="device_key" id="hsKey" value="<?= esc($hsKey); ?>" placeholder="87cf2ca20392d2494a">
	</label>
	<label class="d-block mb-2">Communication password
		<input type="text" class="form-control" name="password" id="hsPwd" value="<?= esc($hsPwd); ?>">
	</label>
	<label class="d-block mb-2">Student location for this kiosk
		<select class="form-control" name="area_id" id="hsArea">
			<option value="0">Select location</option>
			<?php foreach ($areas as $a): ?>
				<option value="<?= (int) $a['id']; ?>" <?= $hsArea === (int) $a['id'] ? 'selected' : ''; ?>><?= esc($a['name']); ?></option>
			<?php endforeach; ?>
		</select>
	</label>
	<p class="small text-muted mb-2">Upload URL (set automatically on sync): <code><?= esc($hsUpload); ?></code></p>
	<button type="button" class="btn btn-secondary" id="hsSave">Save</button>
	<button type="button" class="btn btn-primary" id="hsSync">Sync assigned cards &amp; staff faces</button>
	<div id="hsMsg" class="small mt-2"></div>
</form>

<script>
$(function () {
	function hsPayload(action) {
		return {
			action: action,
			device_ip: $('#hsIp').val().trim(),
			device_key: $('#hsKey').val().trim(),
			password: $('#hsPwd').val().trim(),
			area_id: $('#hsArea').val()
		};
	}
	function hsTell(ok, text) {
		$('#hsMsg').css('color', ok ? '#166534' : '#b91c1c').text(text);
	}
	$('#hsSave').on('click', function () {
		$.post('<?= base_url('manipulate_heystar_device'); ?>', hsPayload('save'), function (res) {
			hsTell(!!res.success, res.success || res.error || 'Saved');
		}, 'json');
	});
	$('#hsSync').on('click', function () {
		hsTell(true, 'Syncing…');
		$.post('<?= base_url('manipulate_heystar_device'); ?>', hsPayload('sync'), function (res) {
			var extra = (res.errors && res.errors.length) ? (' ' + res.errors.join('; ')) : '';
			hsTell(!!res.success, (res.message || res.error || 'Done') + extra);
		}, 'json').fail(function () {
			hsTell(false, 'Sync failed. Is HeyStar running on the kiosk?');
		});
	});
});
</script>

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
