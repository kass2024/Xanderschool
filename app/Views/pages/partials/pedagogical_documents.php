<?php
$classes = $classes ?? [];
$pedDocs = $pedagogical_docs ?? [];
$pedRows = $pedagogical_rows ?? [];
$yearTitle = $academic_year_title ?? 'Current year';
$yearId = (int) ($academic_year_id ?? 0);

$byClass = [];
foreach ($pedDocs as $doc) {
	$cid = (int) $doc['class_id'];
	if (!isset($byClass[$cid])) {
		$byClass[$cid] = ['curriculum' => [], 'chronogram' => []];
	}
	if ($doc['doc_type'] === 'curriculum') {
		$byClass[$cid]['curriculum'][] = $doc;
	} elseif ($doc['doc_type'] === 'chronogram') {
		$byClass[$cid]['chronogram'][] = $doc;
	}
}

/** Merge & dedupe docs shared across a REB group (same file_name). */
$mergeDocs = static function (array $classIds, string $type) use ($byClass): array {
	$seen = [];
	$out = [];
	foreach ($classIds as $cid) {
		$cid = (int) $cid;
		$list = $byClass[$cid][$type] ?? [];
		foreach ($list as $doc) {
			$fn = (string) ($doc['file_name'] ?? '');
			$key = $fn !== '' ? $fn : ('id:' . (int) ($doc['id'] ?? 0));
			if (isset($seen[$key])) {
				continue;
			}
			$seen[$key] = true;
			$out[] = $doc;
		}
	}
	return $out;
};

$renderDocCell = static function (array $docs, array $classIds, $type, $uploadLabel) {
	$primaryId = (int) ($classIds[0] ?? 0);
	$idsAttr = implode(',', array_map('intval', $classIds));
	?>
	<div class="ped-file-list">
		<?php if ($docs === []): ?>
			<span class="ped-empty">No files yet</span>
		<?php else: ?>
			<?php foreach ($docs as $doc): ?>
				<div class="ped-file-row">
					<a class="ped-file" href="<?= base_url('assets/documents/pedagogical/' . $doc['file_name']); ?>" target="_blank" title="<?= esc($doc['original_name'], 'attr'); ?>">
						<i class="fa <?= $type === 'chronogram' ? 'fa-calendar' : 'fa-file-text-o'; ?>"></i>
						<span class="ped-file-name"><?= esc($doc['original_name']); ?></span>
					</a>
					<div class="ped-actions">
						<button type="button" class="btn btn-outline-primary btn-sm ped-replace"
								data-class="<?= $primaryId; ?>"
								data-class-ids="<?= esc($idsAttr, 'attr'); ?>"
								data-type="<?= esc($type, 'attr'); ?>"
								data-replace-id="<?= (int) $doc['id']; ?>">
							<i class="fa fa-refresh"></i> Replace
						</button>
						<button type="button" class="btn btn-outline-danger btn-sm ped-delete"
								data-id="<?= (int) $doc['id']; ?>">
							<i class="fa fa-trash"></i>
						</button>
					</div>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
	<div class="ped-actions ped-add-wrap">
		<button type="button" class="btn btn-primary btn-sm ped-add"
				data-class="<?= $primaryId; ?>"
				data-class-ids="<?= esc($idsAttr, 'attr'); ?>"
				data-type="<?= esc($type, 'attr'); ?>">
			<i class="fa fa-plus"></i> <?= esc($docs === [] ? $uploadLabel : 'Add more'); ?>
		</button>
	</div>
	<div class="ped-progress" hidden>
		<div class="ped-progress-meta">
			<span class="ped-progress-name">Uploading…</span>
			<span class="ped-progress-pct">0%</span>
		</div>
		<div class="ped-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
			<div class="ped-progress-fill"></div>
		</div>
	</div>
	<input type="file" class="ped-upload-input"
		   data-class="<?= $primaryId; ?>"
		   data-class-ids="<?= esc($idsAttr, 'attr'); ?>"
		   data-type="<?= esc($type, 'attr'); ?>"
		   multiple
		   accept="*/*">
	<?php
};

$rebRows = [];
$tvetRows = [];
foreach ($pedRows as $row) {
	if (($row['mode'] ?? '') === 'reb') {
		$rebRows[] = $row;
	} else {
		$tvetRows[] = $row;
	}
}
// Fallback if controller did not build rows (older cache): one row per class as TVET
if ($pedRows === [] && $classes !== []) {
	foreach ($classes as $class) {
		$cid = (int) ($class['id'] ?? 0);
		if ($cid <= 0) {
			continue;
		}
		$label = trim(($class['level_name'] ?? '') . ' ' . ($class['dept_code'] ?? $class['code'] ?? '') . ' ' . ($class['title'] ?? ''));
		$tvetRows[] = [
			'key' => 'tvet_' . $cid,
			'label' => $label,
			'mode' => 'tvet',
			'doc_labels' => ['primary' => 'Curriculum', 'secondary' => 'Chronogram'],
			'class_ids' => [$cid],
			'member_labels' => [$label],
		];
	}
}

$renderSection = static function (string $title, string $badge, array $rows, string $colA, string $colB, $mergeDocs, $renderDocCell) {
	if ($rows === []) {
		return;
	}
	?>
	<div class="ped-section">
		<div class="ped-section-head">
			<span class="ped-section-badge ped-badge-<?= esc($badge, 'attr'); ?>"><?= esc($title); ?></span>
		</div>
		<div class="table-responsive">
			<table class="ped-table">
				<thead>
				<tr>
					<th style="width:26%;">Group / Class</th>
					<th style="width:37%;"><?= esc($colA); ?></th>
					<th><?= esc($colB); ?></th>
				</tr>
				</thead>
				<tbody>
				<?php foreach ($rows as $row):
					$classIds = array_map('intval', $row['class_ids'] ?? []);
					if ($classIds === []) {
						continue;
					}
					$labels = $row['doc_labels'] ?? ['primary' => $colA, 'secondary' => $colB];
					$cur = $mergeDocs($classIds, 'curriculum');
					$chr = $mergeDocs($classIds, 'chronogram');
					$members = $row['member_labels'] ?? [];
					$showMembers = count($members) > 1 || (count($members) === 1 && $members[0] !== ($row['label'] ?? ''));
					?>
					<tr>
						<td>
							<span class="ped-class-name"><?= esc($row['label'] ?? ''); ?></span>
							<?php if ($showMembers): ?>
								<span class="ped-class-meta"><?= esc(implode(' · ', $members)); ?></span>
							<?php endif; ?>
						</td>
						<td><?php $renderDocCell($cur, $classIds, 'curriculum', 'Upload ' . strtolower($labels['primary'] ?? $colA)); ?></td>
						<td><?php $renderDocCell($chr, $classIds, 'chronogram', 'Upload ' . strtolower($labels['secondary'] ?? $colB)); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
	<?php
};
?>
<style>
	.ped-year-banner {
		display:flex; align-items:center; gap:.65rem; flex-wrap:wrap;
		margin:0 0 1.1rem; padding:.65rem 1rem; border-radius:12px;
		background:linear-gradient(135deg,#eff6ff 0%,#dbeafe 100%);
		border:1px solid #93c5fd; color:#1e3a8a; font-size:.92rem;
	}
	.ped-year-banner strong { color:#1d4ed8; }
	.ped-section { margin-bottom:1.35rem; }
	.ped-section:last-child { margin-bottom:0; }
	.ped-section-head { margin:0 0 .55rem; }
	.ped-section-badge {
		display:inline-flex; align-items:center; font-size:.72rem; font-weight:700;
		letter-spacing:.04em; text-transform:uppercase; padding:.28rem .7rem; border-radius:999px;
	}
	.ped-badge-reb { background:#ecfdf5; color:#047857; border:1px solid #a7f3d0; }
	.ped-badge-tvet { background:#eff6ff; color:#1d4ed8; border:1px solid #93c5fd; }
	.ped-table { width:100%; border-collapse:separate; border-spacing:0; }
	.ped-table th {
		background:#f8fafc; color:#475569; font-size:.78rem; text-transform:uppercase;
		letter-spacing:.03em; font-weight:650; padding:.75rem .85rem; border-bottom:1px solid #e2e8f0;
	}
	.ped-table td {
		padding:.9rem .85rem; border-bottom:1px solid #f1f5f9; vertical-align:top;
		font-size:.9rem; color:#0f172a;
	}
	.ped-table tr:hover td { background:#f8fafc; }
	.ped-class-name { font-weight:650; }
	.ped-class-meta { display:block; color:#94a3b8; font-size:.78rem; font-weight:500; margin-top:.2rem; line-height:1.35; }
	.ped-file-list { display:flex; flex-direction:column; gap:.55rem; }
	.ped-file-row {
		display:flex; flex-wrap:wrap; align-items:center; gap:.45rem;
		padding:.45rem .55rem; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px;
	}
	.ped-file {
		display:inline-flex; align-items:center; gap:.4rem; background:#ecfdf5; color:#047857;
		border:1px solid #a7f3d0; border-radius:8px; padding:.35rem .65rem; font-size:.82rem; font-weight:600;
		text-decoration:none; max-width:100%; flex:1 1 160px; min-width:0;
	}
	.ped-file:hover { background:#d1fae5; color:#065f46; text-decoration:none; }
	.ped-file-name { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; min-width:0; }
	.ped-empty { color:#94a3b8; font-size:.85rem; font-style:italic; }
	.ped-actions { display:flex; flex-wrap:wrap; gap:.35rem; align-items:center; }
	.ped-actions .btn { font-size:.78rem; padding:.25rem .55rem; }
	.ped-add-wrap { margin-top:.55rem; }
	.ped-upload-input { display:none; }
	.ped-progress {
		margin-top:.65rem; padding:.55rem .65rem; background:#eff6ff;
		border:1px solid #bfdbfe; border-radius:10px;
	}
	.ped-progress[hidden] { display:none !important; }
	.ped-progress-meta {
		display:flex; justify-content:space-between; align-items:baseline; gap:.5rem;
		margin-bottom:.4rem; font-size:.78rem; color:#1e40af; font-weight:600;
	}
	.ped-progress-name { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; min-width:0; }
	.ped-progress-pct { flex-shrink:0; font-variant-numeric:tabular-nums; }
	.ped-progress-track {
		height:8px; background:#dbeafe; border-radius:999px; overflow:hidden;
	}
	.ped-progress-fill {
		height:100%; width:0%; background:#2563eb; border-radius:999px;
		transition:width .12s linear;
	}
	.ped-progress.is-done .ped-progress-fill { background:#059669; }
	.ped-cell-busy .ped-add, .ped-cell-busy .ped-replace { pointer-events:none; opacity:.55; }
</style>

<div class="ped-year-banner">
	Academic year: <strong><?= esc($yearTitle); ?></strong>
	<?php if ($yearId > 0): ?>
		<span class="text-muted" style="font-size:.8rem;">(ID <?= $yearId; ?>)</span>
	<?php endif; ?>
</div>

<?php if ($yearId <= 0): ?>
	<div class="alert alert-warning">No active academic year found. Set an active term first.</div>
<?php elseif ($rebRows === [] && $tvetRows === []): ?>
	<div class="alert alert-light border">No classes found. Create classes first, then return here to upload documents.</div>
<?php else: ?>
	<?php
	$renderSection('REB', 'reb', $rebRows, 'Syllabus', 'Weeks breakdown', $mergeDocs, $renderDocCell);
	$renderSection('RTB / TVET', 'tvet', $tvetRows, 'Curriculum', 'Chronogram', $mergeDocs, $renderDocCell);
	?>
<?php endif; ?>

<script>
$(function () {
	var uploading = false;
	var pendingReplaceId = 0;

	function findInput(cls, type, classIds) {
		var ids = String(classIds || '');
		return $('.ped-upload-input').filter(function () {
			return String($(this).data('class')) === String(cls)
				&& String($(this).data('type')) === String(type)
				&& String($(this).data('class-ids') || $(this).attr('data-class-ids') || '') === ids;
		}).first();
	}

	function cellOf($el) {
		return $el.closest('td');
	}

	function formatBytes(n) {
		n = Number(n) || 0;
		if (n < 1024) return n + ' B';
		if (n < 1048576) return (n / 1024).toFixed(1) + ' KB';
		return (n / 1048576).toFixed(1) + ' MB';
	}

	function setProgress($cell, pct, label, done) {
		var $bar = $cell.find('.ped-progress');
		pct = Math.max(0, Math.min(100, Math.round(pct)));
		$bar.removeAttr('hidden').toggleClass('is-done', !!done);
		$bar.find('.ped-progress-fill').css('width', pct + '%');
		$bar.find('.ped-progress-track').attr('aria-valuenow', pct);
		$bar.find('.ped-progress-pct').text(pct + '%');
		if (label) $bar.find('.ped-progress-name').text(label);
	}

	function hideProgress($cell) {
		var $bar = $cell.find('.ped-progress');
		$bar.attr('hidden', true).removeClass('is-done');
		$bar.find('.ped-progress-fill').css('width', '0%');
		$cell.removeClass('ped-cell-busy');
	}

	$(document).off('click.pedAdd').on('click.pedAdd', '.ped-add', function () {
		pendingReplaceId = 0;
		var $btn = $(this);
		findInput($btn.data('class'), $btn.data('type'), $btn.attr('data-class-ids') || $btn.data('class-ids')).trigger('click');
	});

	$(document).off('click.pedReplace').on('click.pedReplace', '.ped-replace', function () {
		var $btn = $(this);
		pendingReplaceId = parseInt($btn.data('replace-id') || '0', 10) || 0;
		findInput($btn.data('class'), $btn.data('type'), $btn.attr('data-class-ids') || $btn.data('class-ids')).trigger('click');
	});

	$(document).off('change.pedUpload').on('change.pedUpload', '.ped-upload-input', function () {
		var input = this;
		if (!input.files || !input.files.length || uploading) return;
		uploading = true;
		var $input = $(input);
		var $cell = cellOf($input);
		$cell.addClass('ped-cell-busy');
		var names = [];
		var totalBytes = 0;
		var fd = new FormData();
		for (var i = 0; i < input.files.length; i++) {
			fd.append('documents[]', input.files[i]);
			names.push(input.files[i].name);
			totalBytes += input.files[i].size || 0;
		}
		var label = names.length === 1 ? names[0] : (names.length + ' files');
		if (totalBytes) label += ' · ' + formatBytes(totalBytes);
		setProgress($cell, 0, 'Uploading ' + label, false);
		fd.append('class_id', $input.data('class'));
		fd.append('class_ids', $input.attr('data-class-ids') || $input.data('class-ids') || $input.data('class'));
		fd.append('doc_type', $input.data('type'));
		if (pendingReplaceId > 0) {
			fd.append('replace_id', pendingReplaceId);
		}
		pendingReplaceId = 0;
		$.ajax({
			url: (window.base_url || '') + 'upload_pedagogical_document',
			type: 'POST',
			data: fd,
			processData: false,
			contentType: false,
			dataType: 'json',
			xhr: function () {
				var xhr = $.ajaxSettings.xhr();
				if (xhr.upload) {
					xhr.upload.addEventListener('progress', function (e) {
						if (!e.lengthComputable) return;
						var pct = Math.round((e.loaded / e.total) * 100);
						var stage = pct >= 100 ? 'Saving ' : 'Uploading ';
						setProgress($cell, pct, stage + label, pct >= 100);
					});
				}
				return xhr;
			},
			success: function (res) {
				uploading = false;
				input.value = '';
				if (res && res.success) {
					setProgress($cell, 100, 'Saved', true);
					if (window.toastada) toastada.success(res.success);
					window.location.hash = '#pedagogical-documents';
					window.location.reload();
				} else {
					hideProgress($cell);
					alert((res && res.error) || 'Upload failed');
				}
			},
			error: function (xhr) {
				uploading = false;
				input.value = '';
				hideProgress($cell);
				var msg = 'Upload failed';
				try {
					var j = xhr.responseJSON || JSON.parse(xhr.responseText || '{}');
					if (j && j.error) msg = j.error;
				} catch (e) {}
				alert(msg);
			}
		});
	});

	$(document).off('click.pedDelete').on('click.pedDelete', '.ped-delete', function () {
		if (!confirm('Delete this file for the current academic year?')) return;
		var id = $(this).data('id');
		$.post((window.base_url || '') + 'delete_pedagogical_document', { id: id }, function (res) {
			if (res && res.success) {
				if (window.toastada) toastada.success(res.success);
				window.location.hash = '#pedagogical-documents';
				window.location.reload();
			} else {
				alert((res && res.error) || 'Delete failed');
			}
		}, 'json').fail(function () { alert('Delete failed'); });
	});
});
</script>
