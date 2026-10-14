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
	Green LED = staff found, red LED = not found. Capture each face in HeyStar. Clocks go to staff IN/OUT reports. Staff card photos are uploaded on View Staff — the terminal camera is not used as a profile picture.
	Enrolled staff match by face. The live camera stays ready. Clocks go to staff IN/OUT reports. Staff card photos are uploaded on View Staff — the terminal camera is not used as a profile picture.
</p>
<ol class="small text-muted pl-3 mb-3">
	<li>On HeyStar: Settings (password 123456) → Communication → LAN + HTTP.</li>
	<li>Paste the three VPS URLs below (they include the school ID). Turn <strong>snapshot upload</strong> on. Identification mode: face on, card off.</li>
	<li>On the school Wi-Fi, Update sends new staff, new students, edited names, and new cards from this computer. A progress bar shows each person as it is sent. Match score, faces, cards that already match, and the school name and logo stay as they are.</li>
	<li>On HeyStar, register a face for each staff member. Leave the live camera on — do not tap Check-In / Check-Out. IN/OUT follows the same staff-shift rules as the web scanner.</li>
</ol>
<div class="small" style="max-width:640px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:.85rem 1rem;margin-bottom:1rem">
	<strong>How to use (staff face clock)</strong>
	<ol class="mb-2 pl-3 mt-2">
		<li><strong>Daily IN/OUT:</strong> stand still in front of the camera. One <strong>IN</strong> per day. Wait <strong>5 minutes</strong> after IN before the next look can be <strong>OUT</strong>. Later looks overwrite the out time. The screen shows IN or OUT and the terminal speaks it. Green = found. Red = not found.</li>
		<li><strong>First-time face:</strong> HeyStar Settings (password <code>123456</code>) → User Management → pick the staff name → register face (look at the camera until it saves).</li>
		<li><strong>School:</strong> this terminal uses the School ID below. WISDOM SCHOOL RWANDA is <strong>27</strong>. Clocks appear on Staff IN/OUT reports on the web.</li>
		<li><strong>New staff:</strong> add them on Xander. Names auto-sync to this terminal when the school network is up. Then register their face on the terminal.</li>
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
	<label class="d-block mb-1">Device IP
		<input type="text" class="form-control" name="device_ip" id="hsIp" value="<?= esc($hsIp); ?>" placeholder="Found automatically on the school Wi-Fi">
	</label>
	<p class="small text-muted mb-2" id="hsIpNote">Checking for the terminal…</p>
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
	<button type="button" class="btn btn-primary" id="hsSync">Update infos</button>
	<div id="hsProgressWrap" class="mt-3" style="display:none;max-width:560px">
		<div style="background:#e2e8f0;border-radius:999px;height:12px;overflow:hidden">
			<div id="hsProgressBar" style="width:0%;height:100%;background:#2563eb;transition:width .2s ease"></div>
		</div>
		<div id="hsProgressLabel" class="small mt-1 text-muted"></div>
	</div>
	<div id="hsMsg" class="small mt-2"></div>
	<ul id="hsList" class="small mt-1 mb-0 pl-3"></ul>
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
	function hsEsc(text) {
		return $('<div>').text(text == null ? '' : String(text)).html();
	}
	function hsTell(ok, text) {
		$('#hsMsg').css('color', ok ? '#166534' : '#b91c1c').text(text);
	}
	function hsSetProgress(sent, total, label) {
		$('#hsProgressWrap').show();
		var pct = 8;
		if (total > 0) {
			pct = Math.max(4, Math.min(100, Math.round((sent / total) * 100)));
		}
		$('#hsProgressBar').css('width', pct + '%');
		var count = total > 0 ? (' (' + sent + ' / ' + total + ')') : '';
		$('#hsProgressLabel').text((label || 'Sending…') + count);
	}
	function hsShowUpload(res) {
		var lines = (res && res.changes) ? res.changes : [];
		var extra = (res && res.errors && res.errors.length) ? (' ' + res.errors.join('; ')) : '';
		var ok = !!(res && (res.success === 1 || res.success === true || res.type === 'done' && res.success !== 0));
		if (res && res.success === 0) ok = false;
		hsTell(ok, ((res && (res.message || res.error)) || 'Done') + extra);
		if (!$('#hsList').children().length) {
			var html = '';
			lines.forEach(function (line) {
				html += '<li>' + hsEsc(line) + '</li>';
			});
			$('#hsList').html(html);
		}
		if (ok) {
			$('#hsProgressBar').css('width', '100%');
		}
	}
	function hsApplyEvent(ev) {
		if (!ev) return;
		if (ev.type === 'progress') {
			if (ev.device_ip && !hsIpTouched) $('#hsIp').val(ev.device_ip);
			hsSetProgress(ev.sent || 0, ev.total || 0, ev.label || '');
			if (ev.line) {
				$('#hsList').append('<li>' + hsEsc(ev.line) + '</li>');
			}
			return;
		}
		if (ev.type === 'error') {
			hsTell(false, ev.message || 'Update stopped.');
			return;
		}
		if (ev.type === 'done' || ev.message) {
			hsShowUpload(ev);
		}
	}
	async function hsReadStream(resp) {
		var ct = (resp.headers.get('content-type') || '').toLowerCase();
		if (ct.indexOf('ndjson') === -1) {
			var json = await resp.json();
			if (json && (json.lan_client || json.queued)) return 'lan';
			hsShowUpload(json);
			return 'done';
		}
		var reader = resp.body && resp.body.getReader ? resp.body.getReader() : null;
		if (!reader) {
			hsShowUpload(await resp.json());
			return 'done';
		}
		var dec = new TextDecoder();
		var buf = '';
		var mode = 'done';
		while (true) {
			var chunk = await reader.read();
			if (chunk.done) break;
			buf += dec.decode(chunk.value, {stream: true});
			var nl;
			while ((nl = buf.indexOf('\n')) >= 0) {
				var line = buf.slice(0, nl).trim();
				buf = buf.slice(nl + 1);
				if (!line) continue;
				var ev = JSON.parse(line);
				if (ev.type === 'lan_client') {
					mode = 'lan';
					hsSetProgress(0, 0, 'Terminal is on this Wi-Fi. Sending from this computer…');
					continue;
				}
				hsApplyEvent(ev);
			}
		}
		if (buf.trim()) {
			try {
				var tail = JSON.parse(buf.trim());
				if (tail.type === 'lan_client') mode = 'lan';
				else hsApplyEvent(tail);
			} catch (e) {}
		}
		return mode;
	}
	async function hsLocalSync() {
		hsSetProgress(0, 0, 'Sending staff and students from this computer…');
		var resp;
		try {
			resp = await fetch('http://127.0.0.1:8788/sync', {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify({
					school_id: hsSchoolId(),
					device_ip: $('#hsIp').val().trim(),
					device_key: $('#hsKey').val().trim(),
					password: $('#hsPwd').val().trim(),
					web: hsBase
				})
			});
		} catch (e) {
			hsTell(false, 'This computer is on the same Wi-Fi as the terminal, but the update helper is not running. Start start_lan_sync.bat in the HeyStar API lan folder, then press Update infos again.');
			return;
		}
		if (!resp.ok && (resp.headers.get('content-type') || '').indexOf('ndjson') === -1) {
			hsTell(false, 'The update helper on this computer could not start the send.');
			return;
		}
		await hsReadStream(resp);
	}
	var hsIpTouched = false;
	$('#hsIp').on('input', function () { hsIpTouched = true; });
	function hsRememberIp(res) {
		if (!res || !res.device_ip || hsIpTouched) return;
		var prev = $('#hsIp').val().trim();
		if (res.source === 'lan' || !prev) {
			$('#hsIp').val(res.device_ip);
		}
		if (res.device_key && !$('#hsKey').val().trim()) {
			$('#hsKey').val(res.device_key);
		}
		if (res.source === 'lan' && prev && prev !== res.device_ip) {
			$.post('<?= base_url('manipulate_heystar_device'); ?>', hsPayload('save'));
		}
	}
	function hsServerDiscover() {
		$.ajax({
			url: '<?= base_url('manipulate_heystar_device'); ?>',
			type: 'POST',
			data: {
				action: 'discover_ip',
				school_id: hsSchoolId(),
				device_key: $('#hsKey').val().trim(),
				password: $('#hsPwd').val().trim()
			},
			dataType: 'json',
			timeout: 20000,
			success: function (res) {
				hsRememberIp(res);
				if (!$('#hsIpNote').text() || ($('#hsIpNote').text().indexOf('Checking') === 0)) {
					$('#hsIpNote').text((res && res.message) ? res.message : '');
				}
			},
			error: function () {
				if (!$('#hsIpNote').text()) {
					$('#hsIpNote').text('Could not check the school network. The last saved address stays in the box.');
				}
			}
		});
	}
	function hsLocalDiscover() {
		$('#hsIpNote').text('Checking the terminal on this Wi-Fi…');
		return fetch('http://127.0.0.1:8788/discover', {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify({
				device_ip: $('#hsIp').val().trim(),
				device_key: $('#hsKey').val().trim(),
				password: $('#hsPwd').val().trim()
			})
		}).then(function (resp) { return resp.json(); }).then(function (res) {
			hsRememberIp(res);
			$('#hsIpNote').text((res && res.message) ? res.message : '');
			return res;
		});
	}
	function hsDiscover() {
		hsLocalDiscover().catch(function () {
			hsServerDiscover();
		});
	}
	hsDiscover();
	setInterval(hsDiscover, 30000);
	$('#hsSchoolId').on('input change', function () {
		hsRefreshUrls();
		hsIpTouched = false;
		hsDiscover();
	});
	$('#hsWisdom').on('click', function () {
		$('#hsSchoolId').val(<?= (int) $hsWisdomId; ?>);
		hsRefreshUrls();
		hsIpTouched = false;
		hsDiscover();
	});
	$('#hsSave').on('click', function () {
		$.post('<?= base_url('manipulate_heystar_device'); ?>', hsPayload('save'), function (res) {
			hsTell(!!res.success, res.success || res.error || 'Saved');
		}, 'json');
	});
	$('#hsSync').on('click', function () {
		var $btn = $(this);
		if ($btn.prop('disabled')) return;
		$btn.prop('disabled', true);
		$('#hsList').empty();
		$('#hsProgressBar').css('width', '8%');
		hsSetProgress(0, 0, 'Checking the terminal on this Wi-Fi…');
		hsTell(true, '');
		var startSend = function () {
			var body = hsPayload('sync_changes');
			body.stream = '1';
			fetch('<?= base_url('manipulate_heystar_device'); ?>', {
				method: 'POST',
				headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
				body: new URLSearchParams(body).toString(),
				credentials: 'same-origin'
			}).then(function (resp) {
				return hsReadStream(resp);
			}).then(function (mode) {
				if (mode === 'lan') return hsLocalSync();
			}).catch(function () {
				hsTell(false, 'Could not reach the server to start the update.');
			}).then(function () {
				$btn.prop('disabled', false);
			});
		};
		hsLocalDiscover().catch(function () { return null; }).then(startSend);
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
