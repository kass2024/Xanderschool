<?php
$accounts = $accounts ?? [];
$q = $q ?? '';
$relationLabel = static function (string $relation): string {
	if ($relation === 'father') {
		return 'Father';
	}
	if ($relation === 'mother') {
		return 'Mother';
	}
	if ($relation === 'guardian') {
		return 'Guardian';
	}

	return 'Parent';
};
?>
<style>
.pa-head { display:flex; justify-content:space-between; gap:16px; align-items:flex-end; flex-wrap:wrap; }
.pa-search { display:flex; gap:8px; }
.pa-search input { min-width:240px; }
.pa-card { border:1px solid #e6ebf2; border-radius:14px; margin-bottom:14px; overflow:hidden; background:#fff; }
.pa-card-h { display:flex; justify-content:space-between; gap:12px; align-items:center; padding:14px 16px; background:#f7f9fc; }
.pa-name { font-weight:700; font-size:16px; color:#10233f; }
.pa-phone { color:#5c6b80; font-size:13px; }
.pa-badge { display:inline-block; border-radius:999px; padding:3px 10px; font-size:12px; font-weight:700; }
.pa-on { background:#e7f6ee; color:#0f7a45; }
.pa-off { background:#fde8e8; color:#b42318; }
.pa-card table { margin:0; }
.pa-card td, .pa-card th { vertical-align:middle; }
.pa-actions { display:flex; gap:8px; flex-wrap:wrap; padding:12px 16px; border-top:1px solid #eef2f6; }
.pa-empty { color:#5c6b80; padding:8px 0 4px; }
</style>
<div class="app-page-title">
	<div class="page-title-wrapper">
		<div class="page-title-heading">
			<div>Parents account
				<div class="page-title-subheading">Pocket accounts for students in a class at this school. Disable a parent, or delete the account. Card balances stay on the student.</div>
			</div>
		</div>
	</div>
</div>
<?php if (session()->getFlashdata('success')) { ?>
	<div class="alert alert-success"><?= esc(session()->getFlashdata('success')); ?></div>
<?php } ?>
<?php if (session()->getFlashdata('error')) { ?>
	<div class="alert alert-danger"><?= esc(session()->getFlashdata('error')); ?></div>
<?php } ?>
<div class="pa-head mb-3">
	<div><strong><?= count($accounts); ?></strong> parent account<?= count($accounts) === 1 ? '' : 's'; ?></div>
	<form class="pa-search" method="get" action="<?= base_url('parents_account'); ?>">
		<input class="form-control" name="q" value="<?= esc($q); ?>" placeholder="Name, phone, or registration number">
		<button class="btn btn-primary" type="submit">Search</button>
		<?php if ($q !== '') { ?><a class="btn btn-light" href="<?= base_url('parents_account'); ?>">Clear</a><?php } ?>
	</form>
</div>
<?php if ($accounts === []) { ?>
	<p class="pa-empty">No parent accounts are linked to students in a class at this school.</p>
<?php } ?>
<?php foreach ($accounts as $account) { ?>
	<div class="pa-card">
		<div class="pa-card-h">
			<div>
				<div class="pa-name"><?= esc($account['name']); ?></div>
				<div class="pa-phone"><?= esc($account['phone']); ?> · created <?= esc($account['createdAt']); ?></div>
			</div>
			<span class="pa-badge <?= (int) $account['status'] === 1 ? 'pa-on' : 'pa-off'; ?>">
				<?= (int) $account['status'] === 1 ? 'Active' : 'Disabled'; ?>
			</span>
		</div>
		<table class="table mb-0">
			<tr>
				<th>Student</th>
				<th>Reg no</th>
				<th>Class</th>
				<th>Relation</th>
			</tr>
			<?php foreach ($account['students'] as $student) { ?>
				<tr>
					<td><?= esc($student['name']); ?></td>
					<td><?= esc($student['regno']); ?></td>
					<td><?= esc($student['className']); ?></td>
					<td><?= esc($relationLabel((string) $student['relation'])); ?></td>
				</tr>
			<?php } ?>
			<?php if ($account['students'] === []) { ?>
				<tr><td colspan="4" class="text-muted">No student in a class is linked to this account at this school.</td></tr>
			<?php } ?>
		</table>
		<div class="pa-actions">
			<form method="post" action="<?= base_url('parents_account_status'); ?>">
				<input type="hidden" name="parent_id" value="<?= (int) $account['id']; ?>">
				<?php if ((int) $account['status'] === 1) { ?>
					<input type="hidden" name="status" value="0">
					<button class="btn btn-outline-warning" type="submit" onclick="return confirm(<?= htmlspecialchars(json_encode('Disable ' . $account['name'] . '? They will be signed out and cannot open the pocket app.'), ENT_QUOTES); ?>);">Disable</button>
				<?php } else { ?>
					<input type="hidden" name="status" value="1">
					<button class="btn btn-outline-success" type="submit">Enable</button>
				<?php } ?>
			</form>
			<form method="post" action="<?= base_url('parents_account_delete'); ?>" onsubmit="return confirm(<?= htmlspecialchars(json_encode('Permanently delete ' . $account['name'] . '? They must register again. Student card balances stay.'), ENT_QUOTES); ?>);">
				<input type="hidden" name="parent_id" value="<?= (int) $account['id']; ?>">
				<button class="btn btn-outline-danger" type="submit">Delete permanently</button>
			</form>
		</div>
	</div>
<?php } ?>
