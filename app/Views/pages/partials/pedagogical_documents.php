<?php
$classes = $classes ?? [];
$pedDocs = $pedagogical_docs ?? [];
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

$renderDocCell = function (array $docs, $cid, $type, $uploadLabel) {
	?>
	<div class="ped-file-list">
		<?php if ($docs === []): ?>
			<span class="ped-empty">No files for this academic year</span>
		<?php else: ?>
			<?php foreach ($docs as $doc): ?>
				<div class="ped-file-row">
					<a class="ped-file" href="<?= base_url('assets/documents/pedagogical/' . $doc['file_name']); ?>" target="_blank" title="<?= esc($doc['original_name'], 'attr'); ?>">
						<i class="fa <?= $type === 'chronogram' ? 'fa-calendar' : 'fa-file-text-o'; ?>"></i>
						<span class="ped-file-name"><?= esc($doc['original_name']); ?></span>
					</a>
					<div class="ped-actions">
						<button type="button" class="btn btn-outline-primary btn-sm ped-replace"
								data-class="<?= $cid; ?>" data-type="<?= esc($type, 'attr'); ?>" data-replace-id="<?= (int) $doc['id']; ?>">
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
				data-class="<?= $cid; ?>" data-type="<?= esc($type, 'attr'); ?>">
			<i class="fa fa-plus"></i> <?= esc($docs === [] ? $uploadLabel : 'Add more files'); ?>
		</button>
		<span class="ped-multi-hint">Select multiple files at once</span>
	</div>
	<input type="file" class="ped-upload-input" data-class="<?= $cid; ?>" data-type="<?= esc($type, 'attr'); ?>" multiple
		   accept=".pdf,.doc,.docx,.zip,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/zip">
	<?php
};
?>
<style>
	.ped-intro { color:#64748b; margin:0 0 .85rem; font-size:.92rem; }
	.ped-year-banner {
		display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap;
		margin:0 0 1rem; padding:.75rem 1rem; border-radius:12px;
		background:linear-gradient(135deg,#eff6ff 0%,#dbeafe 100%);
		border:1px solid #93c5fd; color:#1e3a8a;
	}
	.ped-year-banner strong { color:#1d4ed8; }
	.ped-year-banner .ped-year-note { font-size:.82rem; color:#3b82f6; }
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
	.ped-class-meta { display:block; color:#94a3b8; font-size:.78rem; font-weight:500; margin-top:.15rem; }
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
	.ped-multi-hint { font-size:.75rem; color:#94a3b8; }
	.ped-upload-input { display:none; }
</style>

<div class="ped-year-banner">
	<div>
		Academic year: <strong><?= esc($yearTitle); ?></strong>
		<?php if ($yearId > 0): ?>
			<span class="text-muted" style="font-size:.8rem;">(ID <?= $yearId; ?>)</span>
		<?php endif; ?>
	</div>
	<div class="ped-year-note">Documents belong only to this year. Switch academic year to start a new set.</div>
</div>

<p class="ped-intro">
	Upload <strong>multiple curriculum</strong> and <strong>multiple chronogram</strong> files per class
	(example: General curriculum PDF + Specific module PDFs + chronogram PDF). You can select several files at once.<br>
	Accepted: PDF or Word (.doc/.docx). ZIP is optional.
</p>

<?php if ($yearId <= 0): ?>
	<div class="alert alert-warning">No active academic year found. Set an active term first.</div>
<?php elseif (empty($classes)): ?>
	<div class="alert alert-light border">No classes found. Create classes first, then return here to upload documents.</div>
<?php else: ?>
<div class="table-responsive">
	<table class="ped-table">
		<thead>
		<tr>
			<th style="width:22%;">Class</th>
			<th style="width:39%;">Curriculum (multiple files)</th>
			<th>Chronogram (multiple files)</th>
		</tr>
		</thead>
		<tbody>
		<?php foreach ($classes as $class):
			$cid = (int) $class['id'];
			$label = trim(($class['level_name'] ?? '') . ' ' . ($class['dept_code'] ?? $class['code'] ?? '') . ' ' . ($class['title'] ?? ''));
			$docs = $byClass[$cid] ?? ['curriculum' => [], 'chronogram' => []];
			?>
			<tr data-class-id="<?= $cid; ?>">
				<td>
					<span class="ped-class-name"><?= esc($label); ?></span>
					<span class="ped-class-meta">ID <?= $cid; ?>
						· Cur <?= count($docs['curriculum']); ?>
						· Chr <?= count($docs['chronogram']); ?>
					</span>
				</td>
				<td><?php $renderDocCell($docs['curriculum'], $cid, 'curriculum', 'Upload curriculum'); ?></td>
				<td><?php $renderDocCell($docs['chronogram'], $cid, 'chronogram', 'Upload chronogram'); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
<?php endif; ?>

<script>
$(function () {
	var uploading = false;
	var pendingReplaceId = 0;

	function findInput(cls, type) {
		return $('.ped-upload-input').filter(function () {
			return String($(this).data('class')) === String(cls)
				&& String($(this).data('type')) === String(type);
		}).first();
	}

	$(document).off('click.pedAdd').on('click.pedAdd', '.ped-add', function () {
		pendingReplaceId = 0;
		var $btn = $(this);
		findInput($btn.data('class'), $btn.data('type')).trigger('click');
	});

	$(document).off('click.pedReplace').on('click.pedReplace', '.ped-replace', function () {
		var $btn = $(this);
		pendingReplaceId = parseInt($btn.data('replace-id') || '0', 10) || 0;
		findInput($btn.data('class'), $btn.data('type')).trigger('click');
	});

	$(document).off('change.pedUpload').on('change.pedUpload', '.ped-upload-input', function () {
		var input = this;
		if (!input.files || !input.files.length || uploading) return;
		uploading = true;
		var fd = new FormData();
		for (var i = 0; i < input.files.length; i++) {
			fd.append('documents[]', input.files[i]);
		}
		fd.append('class_id', $(input).data('class'));
		fd.append('doc_type', $(input).data('type'));
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
			success: function (res) {
				uploading = false;
				input.value = '';
				if (res && res.success) {
					if (window.toastada) toastada.success(res.success);
					else alert(res.success);
					window.location.hash = '#pedagogical-documents';
					window.location.reload();
				} else {
					alert((res && res.error) || 'Upload failed');
				}
			},
			error: function () {
				uploading = false;
				input.value = '';
				alert('Upload failed');
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
