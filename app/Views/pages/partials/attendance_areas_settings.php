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
$hsPwd = (string) ($hs['password'] ?? '123456');
$hsArea = (int) ($hs['area_id'] ?? 0);
$hsSchoolId = (int) ($school_id ?? session()->get('soma_school_id') ?? 0);
$hsWisdomId = (int) ($wisdom_school_id ?? 0);
if ($hsSchoolId < 1 && $hsWisdomId > 0) {
	$hsSchoolId = $hsWisdomId;
}
$hsBase = rtrim(base_url(), '/');
$hsRecord = $hsBase . '/api/heystar_record?school_id=' . $hsSchoolId;
$hsPerson = $hsBase . '/api/heystar_person?school_id=' . $hsSchoolId;
$hsBeat = $hsBase . '/api/heystar_heartbeat?school_id=' . $hsSchoolId;
?>
<hr class="my-4">
<h6>HeyStar terminal (staff face only)</h6>
<p class="text-muted">
	This terminal is <strong>staff face only</strong>. Enter the <strong>school ID</strong> so clocks and
	staff names go to the right school. Sync sends staff names and puts your
	<strong>school name and logo</strong> on the HeyStar screen (official UI API — the licensed APK is not rebuilt).
	Green LED = staff found, red LED = not found. Capture each face in HeyStar. The camera JPEG is stored on the VPS and clocks go to staff IN/OUT reports.
</p>
<ol class="small text-muted pl-3 mb-3">
	<li>On HeyStar: Settings (password 123456) → Communication → LAN + HTTP.</li>
	<li>Paste the three VPS URLs below (they include the school ID). Turn <strong>snapshot upload</strong> on. Identification mode: face on, card off.</li>
	<li>Save the device IP here, then Sync staff names from a PC on the school LAN.</li>
	<li>On HeyStar, register a face for each staff member. Leave the live camera on — do not tap Check-In / Check-Out. IN/OUT follows the same staff-shift rules as the web scanner.</li>
</ol>
<div class="small" style="max-width:640px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:.85rem 1rem;margin-bottom:1rem">
	<strong>How to use (staff face clock)</strong>
	<ol class="mb-2 pl-3 mt-2">
		<li><strong>Daily IN/OUT:</strong> stand still in front of the camera (always ready — no Check-In/Out buttons). Green = found and clocked like the web staff scanner (first look IN, next OUT, using the staff shift). Red = not found.</li>
		<li><strong>First-time face:</strong> HeyStar Settings (password <code>123456</code>) → User Management → pick the staff name → register face (look at the camera until it saves).</li>
		<li><strong>School:</strong> this terminal uses the School ID below. WISDOM SCHOOL RWANDA is <strong>27</strong>. Clocks appear on Staff IN/OUT reports on the web.</li>
		<li><strong>New staff:</strong> add them on Xander first, then tap <em>Sync staff names to HeyStar</em> from a PC on the school Wi‑Fi, then register their face on the terminal.</li>
	</ol>
	<p class="mb-0 text-muted">Keep the terminal on the school Wi‑Fi so clocks upload to the VPS. Card tap is for students on the other Xander app, not this HeyStar.
		<a href="<?= base_url('heystar-staff-guide.html'); ?>" target="_blank" rel="noopener">Open full guide</a>
	</p>
</div>
<form id="hsForm" class="mb-3" style="max-width:560px">
	<label class="d-block mb-2">School ID
		<input type="number" class="form-control" name="school_id" id="hsSchoolId" min="1" step="1" value="<?= (int) $hsSchoolId; ?>" placeholder="27">
	</label>
	<?php if ($hsWisdomId > 0) : ?>
		<button type="button" class="btn btn-outline-secondary btn-sm mb-3" id="hsWisdom">Use WISDOM SCHOOL RWANDA (ID <?= (int) $hsWisdomId; ?>)</button>
	<?php endif; ?>
	<label class="d-block mb-2">Device IP
		<input type="text" class="form-control" name="device_ip" id="hsIp" value="<?= esc($hsIp); ?>" placeholder="192.168.1.78">
	</label>
	<label class="d-block mb-2">Device key
		<input type="text" class="form-control" name="device_key" id="hsKey" value="<?= esc($hsKey); ?>" placeholder="87cf2ca20392d2494a">
	</label>
	<label class="d-block mb-2">Communication password
		<input type="text" class="form-control" name="password" id="hsPwd" value="<?= esc($hsPwd); ?>">
	</label>
	<input type="hidden" name="area_id" id="hsArea" value="0">
	<p class="small mb-1">Identification record (clock + face photo):</p>
	<code class="d-block small mb-2" id="hsRecordUrl" style="word-break:break-all"><?= esc($hsRecord); ?></code>
	<p class="small mb-1">Heartbeat:</p>
	<code class="d-block small mb-2" id="hsBeatUrl" style="word-break:break-all"><?= esc($hsBeat); ?></code>
	<p class="small mb-1">Registered person (after you add a face on the terminal):</p>
	<code class="d-block small mb-2" id="hsPersonUrl" style="word-break:break-all"><?= esc($hsPerson); ?></code>
	<button type="button" class="btn btn-secondary" id="hsSave">Save</button>
	<button type="button" class="btn btn-primary" id="hsSync">Sync staff names to HeyStar</button>
	<div id="hsMsg" class="small mt-2"></div>
</form>

<script>
$(function () {
	var hsBase = <?= json_encode($hsBase); ?>;
	function hsSchoolId() {
		return parseInt($('#hsSchoolId').val(), 10) || 0;
	}
	function hsRefreshUrls() {
		var id = hsSchoolId();
		$('#hsRecordUrl').text(hsBase + '/api/heystar_record?school_id=' + id);
		$('#hsBeatUrl').text(hsBase + '/api/heystar_heartbeat?school_id=' + id);
		$('#hsPersonUrl').text(hsBase + '/api/heystar_person?school_id=' + id);
	}
	function hsPayload(action) {
		return {
			action: action,
			school_id: hsSchoolId(),
			device_ip: $('#hsIp').val().trim(),
			device_key: $('#hsKey').val().trim(),
			password: $('#hsPwd').val().trim(),
			area_id: $('#hsArea').val()
		};
	}
	function hsTell(ok, text) {
		$('#hsMsg').css('color', ok ? '#166534' : '#b91c1c').text(text);
	}
	$('#hsSchoolId').on('input change', hsRefreshUrls);
	$('#hsWisdom').on('click', function () {
		$('#hsSchoolId').val(<?= (int) $hsWisdomId; ?>);
		hsRefreshUrls();
	});
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
			hsTell(false, 'Sync failed. Is HeyStar running on the terminal (port 8090)?');
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
