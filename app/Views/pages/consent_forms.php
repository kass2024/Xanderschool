<?php
$catalog = $catalog ?? ['ready' => false, 'total' => 0, 'signed' => 0, 'groups' => []];
$groups = $catalog['groups'] ?? [];
?>
<div class="card">
	<div class="card-body">
		<div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center;margin-bottom:14px;">
			<div>
				<h4 style="margin:0 0 4px;">Consent signing</h4>
				<p style="margin:0;color:#64748b;">One link lists every student. Open a name, sign that form, and the name turns green. Parents can still get their own link by SMS. Links last 30 days.</p>
			</div>
			<div style="display:flex;gap:8px;flex-wrap:wrap;">
				<button type="button" class="btn btn-outline-dark" id="consentCreate">Create links</button>
				<button type="button" class="btn btn-dark" id="consentSmsAll">SMS all parents</button>
				<a class="btn btn-outline-primary" href="<?= base_url('consent-forms/download-all'); ?>">Download all</a>
			</div>
		</div>
		<?php if (!empty($boardUrl)): ?>
			<div class="consent-link-box" style="border:1px solid #e2a400;background:#fff8e1;border-radius:12px;padding:14px;margin-bottom:14px;">
				<strong>One link for every student</strong>
				<p style="margin:6px 0 8px;color:#64748b;">The sidebar lists all students. Choose one, sign, and that student is highlighted in green.</p>
				<input class="form-control consent-url" readonly value="<?= esc($boardUrl); ?>" style="margin-bottom:8px;">
				<button type="button" class="btn btn-sm btn-dark consent-copy">Copy link</button>
				<a class="btn btn-sm btn-outline-dark" href="<?= esc($boardUrl); ?>" target="_blank" rel="noopener">Open</a>
			</div>
		<?php endif; ?>
		<div id="consentAlert" class="alert d-none" role="alert"></div>
		<p style="font-weight:600;"><?= (int) $catalog['signed']; ?> signed of <?= (int) $catalog['total']; ?> forms</p>
		<?php if (empty($catalog['ready'])): ?>
			<div class="alert alert-warning">The consent files are not on this server yet.</div>
		<?php elseif ($groups === []): ?>
			<div class="alert alert-warning">No student in this school matches the consent files.</div>
		<?php else: ?>
			<input id="consentSearch" class="form-control" style="max-width:360px;margin-bottom:12px;" placeholder="Search parent or student">
			<div class="table-responsive">
				<table class="table table-bordered table-hover" id="consentTable">
					<thead>
						<tr>
							<th>Parent</th>
							<th>Forms</th>
							<th>Phone</th>
							<th>Link</th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ($groups as $group): ?>
						<?php
						$signed = 0;
						foreach ($group['docs'] as $doc) {
							if (!empty($doc['signed'])) {
								$signed++;
							}
						}
						$ids = [];
						foreach ($group['docs'] as $doc) {
							$ids[] = (int) $doc['student_id'];
						}
						?>
						<tr>
							<td>
								<strong><?= esc($group['parent'] !== '' ? $group['parent'] : 'Parent'); ?></strong>
								<div style="color:#64748b;font-size:12px;"><?= (int) $signed; ?> / <?= count($group['docs']); ?> signed</div>
							</td>
							<td>
								<?php foreach ($group['docs'] as $doc): ?>
									<div style="margin-bottom:4px;">
										<?= esc($doc['student']); ?>
										<span style="color:#64748b;">· <?= esc($doc['regno']); ?> · <?= !empty($doc['signed']) ? 'Signed' : 'Waiting'; ?></span>
										<a href="<?= base_url('consent-forms/download/' . (int) $doc['student_id']); ?>">Download</a>
									</div>
								<?php endforeach; ?>
							</td>
							<td><?= esc($group['phones'] !== [] ? implode(', ', $group['phones']) : 'No phone'); ?></td>
							<td style="min-width:240px;">
								<?php if ($group['url'] !== ''): ?>
									<input class="form-control consent-url" readonly value="<?= esc($group['url']); ?>" style="margin-bottom:6px;">
									<button type="button" class="btn btn-sm btn-outline-dark consent-copy">Copy</button>
								<?php else: ?>
									<span style="color:#64748b;">No link yet</span>
								<?php endif; ?>
								<button type="button" class="btn btn-sm btn-dark consent-sms" data-ids="<?= esc(implode(',', $ids)); ?>">SMS</button>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>
</div>
<script>
(function ($) {
	var API = "<?= site_url('consent-sign/share'); ?>";
	function say(kind, message) {
		$('#consentAlert').removeClass('d-none alert-info alert-success alert-danger alert-warning')
			.addClass('alert-' + kind).text(message);
	}
	function share(ids, sms, $btn) {
		var orig = $btn.html();
		$btn.prop('disabled', true);
		say('info', sms ? 'Sending signing links…' : 'Creating signing links…');
		$.ajax({
			url: API,
			type: 'POST',
			dataType: 'json',
			data: { studentIds: ids || '', send_sms: sms ? '1' : '0' }
		}).done(function (res) {
			if (res && res.success) {
				say('success', res.message || 'Done.');
				window.setTimeout(function () { window.location.reload(); }, 900);
			} else {
				say('danger', (res && (res.error || res.message)) || 'Could not prepare the links.');
				$btn.prop('disabled', false).html(orig);
			}
		}).fail(function () {
			say('danger', 'Could not prepare the links.');
			$btn.prop('disabled', false).html(orig);
		});
	}
	$('#consentCreate').on('click', function () {
		share('', false, $(this));
	});
	$('#consentSmsAll').on('click', function () {
		if (!confirm('Send the signing link by SMS to every parent who has a phone number?')) return;
		share('', true, $(this));
	});
	$(document).on('click', '.consent-sms', function () {
		var ids = $(this).data('ids');
		if (!confirm('Send this parent their signing link by SMS?')) return;
		share(String(ids), true, $(this));
	});
	$(document).on('click', '.consent-copy', function () {
		var input = $(this).closest('td, .consent-link-box').find('.consent-url')[0];
		if (!input) return;
		input.select();
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(input.value);
		} else {
			document.execCommand('copy');
		}
		$(this).text('Copied');
	});
	$('#consentSearch').on('input', function () {
		var q = $(this).val().toLowerCase();
		$('#consentTable tbody tr').each(function () {
			$(this).toggle($(this).text().toLowerCase().indexOf(q) !== -1);
		});
	});
})(jQuery);
</script>
