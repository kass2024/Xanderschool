<?php
/** @var list<array<string,mixed>> $discipline_codes */
$discipline_codes = $discipline_codes ?? [];
$grouped = [];
foreach ($discipline_codes as $row) {
	$key = (string) ($row['category_key'] ?? 'other');
	if (!isset($grouped[$key])) {
		$grouped[$key] = [
			'en' => (string) ($row['category_en'] ?? ''),
			'rw' => (string) ($row['category_rw'] ?? ''),
			'items' => [],
		];
	}
	$grouped[$key]['items'][] = $row;
}
?>
<style>
.dcode-wrap { margin-top: 22px; border-top: 1px solid #e2e8f0; padding-top: 18px; }
.dcode-head { display: flex; align-items: center; gap: 10px; width: 100%; border: 1px solid #e2e8f0; background: #f8fafc; border-radius: 10px; padding: 10px 12px; cursor: pointer; text-align: left; }
.dcode-head:hover { background: #f1f5f9; }
.dcode-head .dcode-head-title { font-weight: 700; margin: 0; flex: 1; font-size: 15px; color: #0f172a; }
.dcode-head .fa-chevron-down { transition: transform .15s ease; color: #64748b; }
.dcode-wrap.is-open > .dcode-head .fa-chevron-down { transform: rotate(180deg); }
.dcode-body { display: none; padding: 10px 2px 0; }
.dcode-wrap.is-open > .dcode-body { display: block; }
.dcode-toolbar { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 10px; }
.dcode-cat { margin-top: 8px; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
.dcode-cat-btn { display: flex; align-items: center; gap: 8px; width: 100%; border: 0; background: #f8fafc; padding: 9px 12px; font-size: 13px; font-weight: 700; color: #0f172a; text-align: left; cursor: pointer; }
.dcode-cat-btn:hover { background: #f1f5f9; }
.dcode-cat-btn .fa-chevron-right { color: #64748b; font-size: 11px; transition: transform .15s ease; width: 12px; }
.dcode-cat.is-open .dcode-cat-btn .fa-chevron-right { transform: rotate(90deg); }
.dcode-cat-count { margin-left: auto; background: #e2e8f0; color: #334155; border-radius: 999px; font-size: 11px; font-weight: 700; padding: 1px 8px; }
.dcode-cat-panel { display: none; padding: 0; }
.dcode-cat.is-open .dcode-cat-panel { display: block; }
.dcode-table { font-size: 12px; margin-bottom: 0; }
.dcode-table td, .dcode-table th { vertical-align: middle; }
.dcode-table textarea { min-height: 52px; font-size: 12px; }
.dcode-add { background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 10px; padding: 12px; margin-top: 12px; }
.dcode-hidden { opacity: 0.45; }
</style>
<div class="dcode-wrap" id="dcodeWrap">
	<button type="button" class="dcode-head" id="dcodeToggle" aria-expanded="false">
		<span class="dcode-head-title"><i class="fa fa-gavel"></i> Discipline law / Amabwiriza</span>
		<span class="dcode-cat-count"><?= count($discipline_codes); ?> laws</span>
		<i class="fa fa-chevron-down"></i>
	</button>
	<div class="dcode-body">
	<p class="text-muted small mb-2 mt-2">
		School conduct codes from the 2026 discipline document. Edit, add, or deactivate a law.
		Behaviour entry uses these codes only — marks for 1st, 2nd and 3rd time are applied automatically.
	</p>
	<div class="dcode-toolbar">
		<button type="button" class="btn btn-outline-secondary btn-sm" id="dcodeExpandAll">Expand all</button>
		<button type="button" class="btn btn-outline-secondary btn-sm" id="dcodeCollapseAll">Collapse all</button>
		<button type="button" class="btn btn-outline-success btn-sm" id="dcodeShowAdd"><i class="fa fa-plus"></i> Add a new law</button>
	</div>
	<?php if (empty($grouped)) : ?>
		<p class="text-muted">No laws yet. Reload this page to seed the Wisdom 2026 catalog, then edit.</p>
	<?php endif; ?>
	<?php foreach ($grouped as $catKey => $cat) : ?>
		<div class="dcode-cat" data-cat="<?= esc($catKey, 'attr'); ?>">
			<button type="button" class="dcode-cat-btn">
				<i class="fa fa-chevron-right"></i>
				<span><?= esc($cat['en']); ?> <span class="text-muted">/ <?= esc($cat['rw']); ?></span></span>
				<span class="dcode-cat-count"><?= count($cat['items']); ?></span>
			</button>
			<div class="dcode-cat-panel">
			<div class="table-responsive">
				<table class="table table-sm table-bordered dcode-table mb-0">
					<thead>
					<tr>
						<th style="width:40px">#</th>
						<th>English</th>
						<th>Kinyarwanda</th>
						<th style="width:70px">1st</th>
						<th style="width:70px">2nd</th>
						<th style="width:70px">3rd</th>
						<th style="width:120px"></th>
					</tr>
					</thead>
					<tbody>
					<?php foreach ($cat['items'] as $c) : ?>
						<?php $hidden = (int) ($c['active'] ?? 1) !== 1; ?>
						<tr data-id="<?= (int) $c['id']; ?>" class="<?= $hidden ? 'dcode-hidden' : ''; ?>">
							<td><?= (int) $c['code_no']; ?></td>
							<td><?= esc($c['title_en']); ?></td>
							<td><?= esc($c['title_rw']); ?></td>
							<td><?= (int) $c['first_marks']; ?></td>
							<td><?= (int) $c['second_marks']; ?></td>
							<td><?= (int) $c['third_marks']; ?></td>
							<td class="text-nowrap">
								<button type="button" class="btn btn-outline-primary btn-sm dcode-edit" data-id="<?= (int) $c['id']; ?>">Edit</button>
								<?php if ($hidden) : ?>
									<button type="button" class="btn btn-outline-success btn-sm dcode-restore" data-id="<?= (int) $c['id']; ?>">Restore</button>
								<?php else : ?>
									<button type="button" class="btn btn-outline-danger btn-sm dcode-del" data-id="<?= (int) $c['id']; ?>">Hide</button>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			</div>
		</div>
	<?php endforeach; ?>

	<form id="dcodeForm" class="dcode-add">
		<input type="hidden" name="action" value="save">
		<input type="hidden" name="id" id="dcodeId" value="0">
		<div class="row">
			<div class="col-md-3 mb-2">
				<label class="small font-weight-bold">Category (English)</label>
				<input type="text" class="form-control form-control-sm" name="category_en" id="dcodeCatEn" required>
			</div>
			<div class="col-md-3 mb-2">
				<label class="small font-weight-bold">Icyiciro (Kinyarwanda)</label>
				<input type="text" class="form-control form-control-sm" name="category_rw" id="dcodeCatRw" required>
			</div>
			<div class="col-md-2 mb-2">
				<label class="small font-weight-bold">No.</label>
				<input type="number" class="form-control form-control-sm" name="code_no" id="dcodeNo" min="1" value="1" required>
			</div>
			<div class="col-md-2 mb-2">
				<label class="small font-weight-bold">1st marks</label>
				<input type="number" class="form-control form-control-sm" name="first_marks" id="dcodeM1" min="0" value="0">
			</div>
			<div class="col-md-2 mb-2">
				<label class="small font-weight-bold">2nd marks</label>
				<input type="number" class="form-control form-control-sm" name="second_marks" id="dcodeM2" min="0" value="0">
			</div>
		</div>
		<div class="row">
			<div class="col-md-2 mb-2">
				<label class="small font-weight-bold">3rd marks</label>
				<input type="number" class="form-control form-control-sm" name="third_marks" id="dcodeM3" min="0" value="0">
			</div>
			<div class="col-md-5 mb-2">
				<label class="small font-weight-bold">Law (English)</label>
				<textarea class="form-control" name="title_en" id="dcodeEn" required></textarea>
			</div>
			<div class="col-md-5 mb-2">
				<label class="small font-weight-bold">Itegeko (Kinyarwanda)</label>
				<textarea class="form-control" name="title_rw" id="dcodeRw" required></textarea>
			</div>
		</div>
		<div class="row">
			<div class="col-md-4 mb-2">
				<label class="small font-weight-bold">1st sanction EN / RW</label>
				<input type="text" class="form-control form-control-sm mb-1" name="first_sanction_en" id="dcodeS1e" placeholder="English">
				<input type="text" class="form-control form-control-sm" name="first_sanction_rw" id="dcodeS1r" placeholder="Kinyarwanda">
			</div>
			<div class="col-md-4 mb-2">
				<label class="small font-weight-bold">2nd sanction EN / RW</label>
				<input type="text" class="form-control form-control-sm mb-1" name="second_sanction_en" id="dcodeS2e">
				<input type="text" class="form-control form-control-sm" name="second_sanction_rw" id="dcodeS2r">
			</div>
			<div class="col-md-4 mb-2">
				<label class="small font-weight-bold">3rd sanction EN / RW</label>
				<input type="text" class="form-control form-control-sm mb-1" name="third_sanction_en" id="dcodeS3e">
				<input type="text" class="form-control form-control-sm" name="third_sanction_rw" id="dcodeS3r">
			</div>
		</div>
		<button type="submit" class="btn btn-success btn-sm" id="dcodeSaveBtn"><i class="fa fa-plus"></i> Save law</button>
		<button type="button" class="btn btn-secondary btn-sm" id="dcodeReset">New</button>
	</form>
	</div>
</div>
<script>
(function ($) {
	var codes = <?= json_encode(array_values($discipline_codes), JSON_UNESCAPED_UNICODE); ?>;
	var $wrap = $('#dcodeWrap');
	function byId(id) {
		id = parseInt(id, 10) || 0;
		for (var i = 0; i < codes.length; i++) {
			if (parseInt(codes[i].id, 10) === id) return codes[i];
		}
		return null;
	}
	function toast(msg, ok) {
		if (typeof toastr !== 'undefined') { ok ? toastr.success(msg) : toastr.error(msg); return; }
		alert(msg);
	}
	function setOpen(on) {
		$wrap.toggleClass('is-open', !!on);
		$('#dcodeToggle').attr('aria-expanded', on ? 'true' : 'false');
	}
	$('#dcodeToggle').on('click', function () { setOpen(!$wrap.hasClass('is-open')); });
	$(document).on('click', '.dcode-cat-btn', function () {
		$(this).closest('.dcode-cat').toggleClass('is-open');
	});
	$('#dcodeExpandAll').on('click', function () {
		setOpen(true);
		$wrap.find('.dcode-cat').addClass('is-open');
	});
	$('#dcodeCollapseAll').on('click', function () {
		$wrap.find('.dcode-cat').removeClass('is-open');
	});
	$('#dcodeShowAdd').on('click', function () {
		setOpen(true);
		$('#dcodeForm')[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
		$('#dcodeCatEn').trigger('focus');
	});
	function fillForm(c) {
		$('#dcodeId').val(c ? c.id : 0);
		$('#dcodeCatEn').val(c ? (c.category_en || '') : '');
		$('#dcodeCatRw').val(c ? (c.category_rw || '') : '');
		$('#dcodeNo').val(c ? c.code_no : 1);
		$('#dcodeEn').val(c ? (c.title_en || '') : '');
		$('#dcodeRw').val(c ? (c.title_rw || '') : '');
		$('#dcodeM1').val(c ? c.first_marks : 0);
		$('#dcodeM2').val(c ? c.second_marks : 0);
		$('#dcodeM3').val(c ? c.third_marks : 0);
		$('#dcodeS1e').val(c ? (c.first_sanction_en || '') : '');
		$('#dcodeS1r').val(c ? (c.first_sanction_rw || '') : '');
		$('#dcodeS2e').val(c ? (c.second_sanction_en || '') : '');
		$('#dcodeS2r').val(c ? (c.second_sanction_rw || '') : '');
		$('#dcodeS3e').val(c ? (c.third_sanction_en || '') : '');
		$('#dcodeS3r').val(c ? (c.third_sanction_rw || '') : '');
		$('#dcodeSaveBtn').html(c ? '<i class="fa fa-save"></i> Update law' : '<i class="fa fa-plus"></i> Save law');
	}
	$(document).on('click', '.dcode-edit', function () {
		fillForm(byId($(this).data('id')));
		setOpen(true);
		$(this).closest('.dcode-cat').addClass('is-open');
		$('#dcodeForm')[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
	});
	$('#dcodeReset').on('click', function () { fillForm(null); });
	$(document).on('click', '.dcode-restore', function () {
		var id = $(this).data('id');
		if (!id) return;
		$.post('<?= base_url('manipulate_discipline_code'); ?>', { action: 'restore', id: id }).done(function (res) {
			if (res.error) { toast(res.error, false); return; }
			toast(res.success || 'Restored.', true);
			location.reload();
		}).fail(function () { toast('Could not restore law.', false); });
	});
	$(document).on('click', '.dcode-del', function () {
		var id = $(this).data('id');
		if (!id || !confirm('Hide this law from behaviour entry?')) return;
		$.post('<?= base_url('manipulate_discipline_code'); ?>', { action: 'delete', id: id }).done(function (res) {
			if (res.error) { toast(res.error, false); return; }
			toast(res.success || 'Hidden.', true);
			location.reload();
		}).fail(function () { toast('Could not hide law.', false); });
	});
	$('#dcodeForm').on('submit', function (e) {
		e.preventDefault();
		$.post('<?= base_url('manipulate_discipline_code'); ?>', $(this).serialize()).done(function (res) {
			if (res.error) { toast(res.error, false); return; }
			toast(res.success || 'Saved.', true);
			location.reload();
		}).fail(function () { toast('Could not save law.', false); });
	});
})(jQuery);
</script>
