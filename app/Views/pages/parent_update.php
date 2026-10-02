<?php
$isRw = ($lang ?? 'en') === 'rw';
$t = $isRw ? [
	'title' => 'Hindura amakuru y\'ababyeyi',
	'intro' => 'Urashobora guhindura amazina y\'ababyeyi, telefoni, n\'aderesi gusa. Iyi link irangira mu masaha 48.',
	'expired' => 'Iyi link yarangiye. Saba ishuri indi link nshya.',
	'saved' => 'Amakuru yabitswe. Urashobora kongera kuyahindura kugeza iyi link irangira.',
	'student' => 'Umunyeshuri',
	'father' => 'Amazina ya papa',
	'fatherPhone' => 'Telefoni ya papa',
	'mother' => 'Amazina ya mama',
	'motherPhone' => 'Telefoni ya mama',
	'guardian' => 'Amazina y\'umurezi',
	'guardianPhone' => 'Telefoni y\'umurezi',
	'address' => 'Aderesi',
	'province' => 'Intara',
	'district' => 'Akarere',
	'sector' => 'Umurenge',
	'cell' => 'Akagari',
	'village' => 'Umudugudu',
	'select' => 'Hitamo',
	'save' => 'Bika',
	'expires' => 'Irangira',
] : [
	'title' => 'Update parent details',
	'intro' => 'You can change parent names, phone numbers, and the address only. This link expires in 48 hours.',
	'expired' => 'This link has expired. Ask the school for a new one.',
	'saved' => 'Saved. You can still correct it until this link expires.',
	'student' => 'Student',
	'father' => 'Father names',
	'fatherPhone' => 'Father phone',
	'mother' => 'Mother names',
	'motherPhone' => 'Mother phone',
	'guardian' => 'Guardian names',
	'guardianPhone' => 'Guardian phone',
	'address' => 'Address',
	'province' => 'Province',
	'district' => 'District',
	'sector' => 'Sector',
	'cell' => 'Cell',
	'village' => 'Village',
	'select' => 'Select',
	'save' => 'Save',
	'expires' => 'Expires',
];
$st = $student ?? [];
$base = base_url('parent-update/' . ($token ?? ''));
$opt = static function ($rows, $selected) {
	$html = '';
	foreach ($rows as $row) {
		$id = (int) ($row['id'] ?? 0);
		$html .= '<option value="' . $id . '"' . ($id === (int) $selected ? ' selected' : '') . '>' . esc($row['title'] ?? '') . '</option>';
	}
	return $html;
};
?>
<!doctype html>
<html lang="<?= $isRw ? 'rw' : 'en'; ?>">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?= esc($t['title']); ?></title>
	<link rel="stylesheet" href="<?= base_url('assets/plugins/bootstrap/bootstrap.min.css'); ?>">
	<style>
		body { margin: 0; background: #eef4f8; font-family: "Segoe UI", sans-serif; color: #0f172a; }
		.pu-wrap { max-width: 720px; margin: 24px auto; padding: 0 14px 32px; }
		.pu-card { background: #fff; border-radius: 18px; box-shadow: 0 16px 40px rgba(15,23,42,.08); overflow: hidden; }
		.pu-head { background: linear-gradient(135deg, #0f172a, #0f766e); color: #fff; padding: 20px 22px; display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; }
		.pu-head h1 { margin: 0 0 6px; font-size: 22px; }
		.pu-head p { margin: 0; opacity: .9; font-size: 14px; }
		.pu-lang { display: flex; background: rgba(255,255,255,.14); border-radius: 999px; padding: 3px; }
		.pu-lang a { color: #fff; text-decoration: none; font-weight: 700; font-size: 13px; padding: 6px 12px; border-radius: 999px; }
		.pu-lang a.active { background: #fff; color: #0f766e; }
		.pu-body { padding: 18px 22px 22px; }
		.pu-student { background: #f0fdfa; border: 1px solid #99f6e4; border-radius: 12px; padding: 12px 14px; margin-bottom: 14px; }
		.pu-student strong { display: block; font-size: 16px; }
		.pu-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
		.pu-grid .full { grid-column: 1 / -1; }
		label { font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 4px; }
		.form-control { border-radius: 10px; }
		.pu-section { margin: 16px 0 8px; font-size: 13px; letter-spacing: .04em; text-transform: uppercase; color: #0f766e; font-weight: 800; }
		.btn-save { background: #0f766e; border: 0; color: #fff; font-weight: 800; border-radius: 999px; padding: 10px 22px; }
		.pu-note { font-size: 13px; color: #64748b; margin-top: 10px; }
		@media (max-width: 640px) { .pu-grid { grid-template-columns: 1fr; } .pu-head { flex-direction: column; } }
	</style>
</head>
<body>
<div class="pu-wrap">
	<div class="pu-card">
		<div class="pu-head">
			<div>
				<h1><?= esc($school_name ?? ''); ?></h1>
				<p><?= esc($t['title']); ?></p>
			</div>
			<div class="pu-lang">
				<a href="<?= esc($base . '?lang=en'); ?>" class="<?= $isRw ? '' : 'active'; ?>">English</a>
				<a href="<?= esc($base . '?lang=rw'); ?>" class="<?= $isRw ? 'active' : ''; ?>">Kinyarwanda</a>
			</div>
		</div>
		<div class="pu-body">
			<?php if (!empty($expired)): ?>
				<div class="alert alert-warning"><?= esc($t['expired']); ?></div>
			<?php else: ?>
				<?php if (!empty($saved)): ?>
					<div class="alert alert-success"><?= esc($t['saved']); ?></div>
				<?php endif; ?>
				<?php if (!empty($error)): ?>
					<div class="alert alert-danger"><?= esc($error); ?></div>
				<?php endif; ?>
				<p><?= esc($t['intro']); ?></p>
				<div class="pu-student">
					<span><?= esc($t['student']); ?></span>
					<strong><?= esc(trim(($st['fname'] ?? '') . ' ' . ($st['lname'] ?? ''))); ?></strong>
					<span><?= esc($st['regno'] ?? ''); ?></span>
				</div>
				<form method="post" action="<?= esc($base . '?lang=' . ($isRw ? 'rw' : 'en')); ?>">
					<input type="hidden" name="lang" value="<?= $isRw ? 'rw' : 'en'; ?>">
					<div class="pu-grid">
						<div>
							<label><?= esc($t['father']); ?></label>
							<input class="form-control" name="father" value="<?= esc($st['father'] ?? ''); ?>">
						</div>
						<div>
							<label><?= esc($t['fatherPhone']); ?></label>
							<input class="form-control" name="ft_phone" value="<?= esc($st['ft_phone'] ?? ''); ?>">
						</div>
						<div>
							<label><?= esc($t['mother']); ?></label>
							<input class="form-control" name="mother" value="<?= esc($st['mother'] ?? ''); ?>">
						</div>
						<div>
							<label><?= esc($t['motherPhone']); ?></label>
							<input class="form-control" name="mt_phone" value="<?= esc($st['mt_phone'] ?? ''); ?>">
						</div>
						<div>
							<label><?= esc($t['guardian']); ?></label>
							<input class="form-control" name="guardian" value="<?= esc($st['guardian'] ?? ''); ?>">
						</div>
						<div>
							<label><?= esc($t['guardianPhone']); ?></label>
							<input class="form-control" name="gd_phone" value="<?= esc($st['gd_phone'] ?? ''); ?>">
						</div>
					</div>
					<div class="pu-section"><?= esc($t['address']); ?></div>
					<div class="pu-grid">
						<div>
							<label><?= esc($t['province']); ?></label>
							<select class="form-control address_select" data-target="district" name="province">
								<option value=""><?= esc($t['select']); ?></option>
								<?= $opt($provinces ?? [], $st['province_id'] ?? 0); ?>
							</select>
						</div>
						<div>
							<label><?= esc($t['district']); ?></label>
							<select class="form-control address_select" data-target="sector" name="district">
								<option value=""><?= esc($t['select']); ?></option>
								<?= $opt($districts ?? [], $st['district_id'] ?? 0); ?>
							</select>
						</div>
						<div>
							<label><?= esc($t['sector']); ?></label>
							<select class="form-control address_select" data-target="cell" name="sector">
								<option value=""><?= esc($t['select']); ?></option>
								<?= $opt($sectors ?? [], $st['sector_id'] ?? 0); ?>
							</select>
						</div>
						<div>
							<label><?= esc($t['cell']); ?></label>
							<select class="form-control address_select" data-target="village" name="cell">
								<option value=""><?= esc($t['select']); ?></option>
								<?= $opt($cells ?? [], $st['cell_id'] ?? 0); ?>
							</select>
						</div>
						<div class="full">
							<label><?= esc($t['village']); ?></label>
							<select class="form-control" name="village">
								<option value=""><?= esc($t['select']); ?></option>
								<?= $opt($villages ?? [], $st['village_id'] ?? 0); ?>
							</select>
						</div>
					</div>
					<button class="btn-save" type="submit"><?= esc($t['save']); ?></button>
					<p class="pu-note"><?= esc($t['expires']); ?>: <?= esc($expires_at ?? ''); ?></p>
				</form>
			<?php endif; ?>
		</div>
	</div>
</div>
<script src="<?= base_url('assets/js/jquery-3.4.1.min.js'); ?>"></script>
<script>
	var addrSelectLabel = <?= json_encode($t['select']); ?>;
	$(".address_select").on("change", function () {
		var target = $(this).data("target");
		var value = $(this).val();
		var $next = $("[name='" + target + "']");
		var order = ["district", "sector", "cell", "village"];
		var start = order.indexOf(target);
		for (var i = start; i < order.length; i++) {
			$("[name='" + order[i] + "']").html('<option value="">' + addrSelectLabel + '</option>');
		}
		if (!value) {
			return;
		}
		$.get("<?= base_url('get_address'); ?>/" + target, { key: $(this).attr("name"), val: value }, function (data) {
			$next.html(data);
		});
	});
</script>
</body>
</html>
