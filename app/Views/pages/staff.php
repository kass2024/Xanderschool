<style>
	.settings span {
		font-weight: 600;
	}
	.spedit {
		min-width: 100px;
		cursor: pointer;
		display: inline-block;
	}
	.boxed{
		padding: 20px;
		border-radius: 5px;
		background: #e1e0e0;
	}
	.ihelp{
		font-size: 30pt;position: absolute;top: 10px;left:59%;color:#333333;cursor: pointer;
	}
	span{
		font-weight: 600;
	}
	@media all and (max-width: 1249px){
		.ihelp{
			left:50%;color:#ffffff;
		}
	}
	.sid-card { background:#fff; border:1px solid #e4ebf4; border-radius:16px; padding:18px 18px 8px; margin:0 15px 16px; box-shadow:0 1px 2px rgba(7,22,51,.04); }
	.sid-card h4 { margin:0 0 4px; font-weight:700; }
	.sid-card .sid-lead { color:#5c6b80; font-size:13px; margin:0 0 14px; font-weight:500; }
	.sid-switch { display:flex; gap:8px; margin-bottom:14px; flex-wrap:wrap; }
	.sid-switch label { margin:0; border:1px solid #d7e0ec; border-radius:999px; padding:8px 14px; cursor:pointer; font-weight:600; background:#f7f9fc; }
	.sid-switch input { margin-right:6px; }
	.sid-switch label.is-on { background:#071633; color:#fff; border-color:#071633; }
	.sid-grid { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:12px 16px; }
	.sid-grid .full { grid-column:1 / -1; }
	.sid-grid label { display:block; font-size:12px; color:#5c6b80; font-weight:600; margin-bottom:4px; }
	.sid-grid .form-control, .sid-grid select { width:100%; }
	.sid-file { display:flex; gap:12px; align-items:center; flex-wrap:wrap; }
	.sid-file a { font-weight:600; }
	.sid-actions { margin:14px 0 10px; }
	.sp-layout { display:grid; grid-template-columns:240px minmax(0,1fr); gap:22px; align-items:start; }
	.sp-photo { background:#f6f8fb; border:1px solid #e4ebf4; border-radius:16px; padding:18px 16px 14px; text-align:center; }
	.sp-avatar-wrap { position:relative; width:148px; height:148px; margin:0 auto 14px; }
	.sp-avatar { width:148px; height:148px; border-radius:50%; object-fit:cover; background:#fff; border:4px solid #fff; box-shadow:0 0 0 1px #d5deea, 0 10px 24px rgba(7,22,51,.12); display:block; }
	.sp-avatar-remove { position:absolute; top:6px; right:6px; width:28px; height:28px; border:2px solid #fff; border-radius:50%; background:#dc3545; color:#fff; font-size:14px; line-height:24px; padding:0; cursor:pointer; box-shadow:0 2px 6px rgba(0,0,0,.18); }
	.sp-drop { display:block; border:1.5px dashed #8ea4bf; border-radius:12px; background:#fff; padding:14px 10px; cursor:pointer; margin:0; transition:border-color .15s, background .15s; }
	.sp-drop:hover, .sp-drop.is-over { border-color:#071633; background:#eef3fb; }
	.sp-drop strong { display:block; font-size:13px; color:#071633; font-weight:700; }
	.sp-drop span { display:block; margin-top:4px; color:#5c6b80; font-size:12px; font-weight:500; }
	.sp-photo-note { margin:10px 0 0; color:#5c6b80; font-size:12px; font-weight:500; line-height:1.45; }
	@media (max-width: 800px) { .sid-grid, .sp-layout { grid-template-columns:1fr; } }
</style>
<?php
$canEditStaff = !empty($can_edit_staff);
$identityKind = (string) ($staff['identity_kind'] ?? '');
$idCopyName = basename((string) ($staff['id_copy'] ?? ''));
$idCopyUrl = $idCopyName !== '' ? base_url('staff_id_copy/' . (int) $staff['id']) : '';
$dobValue = trim((string) ($staff['dob'] ?? ''));
if ($dobValue === '0000-00-00') {
	$dobValue = '';
}
$field = static function (string $key) use ($staff): string {
	return esc((string) ($staff[$key] ?? ''));
};
helper('qonics');
$resolved = resolve_profile_photo($staff['photo'] ?? '');
$hasPhoto = $resolved !== null;
$photo = $hasPhoto ? profile_photo_url($resolved) : profile_photo_url(null);
$fallbackPhoto = profile_photo_url(null);
$editingSelf = (int) ($staff['id'] ?? 0) === (int) ($_SESSION['soma_id'] ?? 0);
$lock = $canEditStaff ? '' : 'disabled';
?>
<div class="sid-card" id="staff-profile">
	<h4>Staff profile</h4>
	<p class="sid-lead">Update the name, email, phone, address, and profile photo. Email and phone are used for login and messages.</p>
	<form id="staff-profile-form">
		<input type="hidden" name="staff_id" value="<?= (int) $staff['id']; ?>">
		<div class="sp-layout">
			<div class="sp-photo">
				<div class="sp-avatar-wrap">
					<img src="<?= esc($photo); ?>" id="img_photo" class="sp-avatar" alt="Staff photo">
					<?php if ($canEditStaff): ?>
						<button type="button" id="btn_remove_photo" class="sp-avatar-remove" title="<?= esc(lang('app.removePhoto')); ?>" <?= $hasPhoto ? '' : 'style="display:none;"'; ?>>
							<i class="fa fa-times"></i>
						</button>
					<?php endif; ?>
				</div>
				<?php if ($canEditStaff): ?>
					<label class="sp-drop" id="dv_select_img">
						<input type="file" id="in_student_photo" accept="image/jpeg,image/jpg,image/png" hidden>
						<strong id="sp-photo-status">Click or drop a photo</strong>
						<span>JPG or PNG, up to 5 MB</span>
					</label>
					<p class="sp-photo-note">The photo is cropped to a head-and-shoulders portrait on a white background.</p>
				<?php elseif (!$hasPhoto): ?>
					<p class="sp-photo-note">No profile photo yet.</p>
				<?php endif; ?>
			</div>
			<div>
				<div class="sid-grid">
					<div>
						<label for="sp-fname">First name</label>
						<input class="form-control" id="sp-fname" name="fname" value="<?= $field('fname'); ?>" maxlength="100" <?= $canEditStaff ? 'required' : 'disabled'; ?>>
					</div>
					<div>
						<label for="sp-lname">Last name</label>
						<input class="form-control" id="sp-lname" name="lname" value="<?= $field('lname'); ?>" maxlength="100" <?= $canEditStaff ? 'required' : 'disabled'; ?>>
					</div>
					<div>
						<label for="sp-email">Email</label>
						<input class="form-control" id="sp-email" type="email" name="email" value="<?= $field('email'); ?>" maxlength="100" placeholder="name@school.com" <?= $lock; ?>>
					</div>
					<?php if (trim((string) ($staff['school_email'] ?? '')) !== ''): ?>
					<div class="full">
						<label for="sp-school-email">School email</label>
						<input class="form-control" id="sp-school-email" type="email" value="<?= $field('school_email'); ?>" readonly>
						<p class="sid-lead" style="margin:6px 0 0;">School mailbox on wisdomschoolrwanda.com. Sign-in still uses the email above.</p>
					</div>
					<?php endif; ?>
					<div>
						<label for="sp-phone">Phone number</label>
						<input class="form-control" id="sp-phone" name="phone" value="<?= $field('phone'); ?>" maxlength="20" inputmode="tel" placeholder="07..." <?= $lock; ?>>
					</div>
					<div>
						<label for="staff_country">Country</label>
						<?= view('pages/partials/country_select_staff', [
							'country_selected' => (string) ($staff['country'] ?? ''),
							'country_select_id' => 'staff_country',
							'country_required' => false,
							'country_disabled' => !$canEditStaff,
						]); ?>
					</div>
					<div>
						<label for="sp-city">City</label>
						<input class="form-control" id="sp-city" name="city" value="<?= $field('city'); ?>" maxlength="100" <?= $lock; ?>>
					</div>
					<div class="full">
						<label for="sp-address">Address</label>
						<input class="form-control" id="sp-address" name="address" value="<?= $field('address'); ?>" maxlength="500" <?= $lock; ?>>
					</div>
				</div>
				<?php if ($canEditStaff): ?>
					<div class="sid-actions sp-save">
						<button type="submit" class="btn btn-primary" id="sp-save">Save profile</button>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</form>
</div>
<div class="sid-card" id="staff-identity">
	<h4>Identity</h4>
	<p class="sid-lead">Choose nationality and upload the identification document. An accountant, Chief Accountant, or Head Teacher can fill this for any staff member.</p>
	<form id="staff-identity-form" enctype="multipart/form-data">
		<input type="hidden" name="staff_id" value="<?= (int) $staff['id']; ?>">
		<div class="sid-switch">
			<label class="<?= $identityKind === 'rwandan' ? 'is-on' : ''; ?>">
				<input type="radio" name="identity_kind" value="rwandan" <?= $identityKind === 'rwandan' ? 'checked' : ''; ?> <?= $canEditStaff ? '' : 'disabled'; ?>>
				Rwandan
			</label>
			<label class="<?= $identityKind === 'foreign' ? 'is-on' : ''; ?>">
				<input type="radio" name="identity_kind" value="foreign" <?= $identityKind === 'foreign' ? 'checked' : ''; ?> <?= $canEditStaff ? '' : 'disabled'; ?>>
				Non-Rwandan
			</label>
		</div>
		<?php $rwOn = $identityKind === 'rwandan' && $canEditStaff; $fnOn = $identityKind === 'foreign' && $canEditStaff; ?>
		<div class="sid-panel" data-kind="rwandan" <?= $identityKind === 'rwandan' ? '' : 'style="display:none;"'; ?>>
			<div class="sid-grid" style="margin-top:12px;">
				<div>
					<label>National ID</label>
					<input class="form-control" name="national_id" inputmode="numeric" maxlength="16" value="<?= $field('national_id'); ?>" placeholder="16 digits" <?= $rwOn ? '' : 'disabled'; ?>>
				</div>
				<div class="full">
					<label>Upload of ID or passport</label>
					<div class="sid-file">
						<?php if ($canEditStaff): ?>
							<input type="file" name="id_copy" accept="image/jpeg,image/png,application/pdf" <?= $rwOn ? '' : 'disabled'; ?>>
						<?php endif; ?>
						<?php if ($idCopyUrl !== ''): ?>
							<a href="<?= esc($idCopyUrl); ?>" target="_blank" rel="noopener">View uploaded ID</a>
						<?php elseif (!$canEditStaff): ?>
							<span class="text-muted">No ID uploaded</span>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
		<div class="sid-panel" data-kind="foreign" <?= $identityKind === 'foreign' ? '' : 'style="display:none;"'; ?>>
			<div class="sid-grid" style="margin-top:12px;">
				<div>
					<label>Sex</label>
					<select class="form-control" name="sex" <?= $fnOn ? '' : 'disabled'; ?>>
						<option value="">Select</option>
						<option value="M" <?= ($staff['sex'] ?? '') === 'M' ? 'selected' : ''; ?>>Male</option>
						<option value="F" <?= ($staff['sex'] ?? '') === 'F' ? 'selected' : ''; ?>>Female</option>
					</select>
				</div>
				<div>
					<label>Date of birth</label>
					<input class="form-control" type="date" name="dob" value="<?= esc($dobValue); ?>" <?= $fnOn ? '' : 'disabled'; ?>>
				</div>
				<div>
					<label>Nature of identification</label>
					<select class="form-control" name="id_nature" <?= $fnOn ? '' : 'disabled'; ?>>
						<option value="">Select</option>
						<option value="id" <?= ($staff['id_nature'] ?? '') === 'id' ? 'selected' : ''; ?>>ID</option>
						<option value="passport" <?= ($staff['id_nature'] ?? '') === 'passport' ? 'selected' : ''; ?>>Passport</option>
					</select>
				</div>
				<div>
					<label>Passport / NID No</label>
					<input class="form-control" name="document_no" value="<?= $field('document_no'); ?>" <?= $fnOn ? '' : 'disabled'; ?>>
				</div>
				<div>
					<label>Father's name</label>
					<input class="form-control" name="father_name" value="<?= $field('father_name'); ?>" <?= $fnOn ? '' : 'disabled'; ?>>
				</div>
				<div>
					<label>Mother's name</label>
					<input class="form-control" name="mother_name" value="<?= $field('mother_name'); ?>" <?= $fnOn ? '' : 'disabled'; ?>>
				</div>
				<div>
					<label>Province of residence</label>
					<select class="form-control" name="res_province" id="sid_province" <?= $fnOn ? '' : 'disabled'; ?>>
						<option value="">Select</option>
						<?php foreach ($identity_provinces ?? [] as $province): ?>
							<option value="<?= (int) $province['id']; ?>" <?= (int) ($staff['res_province'] ?? 0) === (int) $province['id'] ? 'selected' : ''; ?>><?= esc($province['title']); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div>
					<label>District of residence</label>
					<select class="form-control" name="res_district" id="sid_district" <?= $fnOn ? '' : 'disabled'; ?>>
						<option value="">Select</option>
						<?php foreach ($identity_districts ?? [] as $district): ?>
							<option value="<?= (int) $district['id']; ?>" <?= (int) ($staff['res_district'] ?? 0) === (int) $district['id'] ? 'selected' : ''; ?>><?= esc($district['title']); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div>
					<label>Sector of residence</label>
					<select class="form-control" name="res_sector" id="sid_sector" <?= $fnOn ? '' : 'disabled'; ?>>
						<option value="">Select</option>
						<?php foreach ($identity_sectors ?? [] as $sector): ?>
							<option value="<?= (int) $sector['id']; ?>" <?= (int) ($staff['res_sector'] ?? 0) === (int) $sector['id'] ? 'selected' : ''; ?>><?= esc($sector['title']); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="full">
					<label>Upload of ID or passport</label>
					<div class="sid-file">
						<?php if ($canEditStaff): ?>
							<input type="file" name="id_copy" accept="image/jpeg,image/png,application/pdf" <?= $fnOn ? '' : 'disabled'; ?>>
						<?php endif; ?>
						<?php if ($idCopyUrl !== ''): ?>
							<a href="<?= esc($idCopyUrl); ?>" target="_blank" rel="noopener">View uploaded ID or passport</a>
						<?php elseif (!$canEditStaff): ?>
							<span class="text-muted">No ID or passport uploaded</span>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
		<?php if ($canEditStaff): ?>
			<div class="sid-actions">
				<button type="submit" class="btn btn-primary" id="sid-save">Save identity</button>
			</div>
		<?php endif; ?>
	</form>
</div>
<div class="row" id="staff_section" data-id="<?= $staff['id']; ?>">
		<hr style="width: 100%;float: left"/>
		<div class="col-sm-12 col-md-6 col-lg-6 pull-left">
			<div class="boxed" style="background:white;padding: 20px">
				<h4>Contract</h4>
				<?php
				$contractLabel = static function ($value): string {
					$value = trim((string) $value);
					if ($value === '' || $value === '0000-00-00') {
						return '';
					}
					$ts = strtotime($value);
					return $ts ? date('d M Y', $ts) : '';
				};
				$contractStart = $contractLabel($staff['contract_start'] ?? '');
				$contractEnd = $contractLabel($staff['contract_end'] ?? '');
				?>
				<div class="form-group">
					<label>Contract start:</label>
					<span><?= $contractStart !== '' ? esc($contractStart) : '—'; ?></span>
				</div>
				<div class="form-group">
					<label>Contract end:</label>
					<span><?= $contractEnd !== '' ? esc($contractEnd) : '—'; ?></span>
				</div>
			</div>
		</div>
	<div class="col-sm-12 col-md-5 col-lg-5 pull-left">
		<div class="boxed">
			<h4><?= lang("app.schoolInformation");?></h4>
			<div style="margin-left: 20px">
				<label><?= lang("app.post");?>: <?= $staff['post_title']; ?></label><br/>
				<label><?= lang("app.school");?>: <?= $_SESSION['soma_school']; ?></label><br/>
			</div>
		</div>
		<?php
		$signatureFile = staff_signature_file($staff['signature'] ?? '');
		$signatureSrc = $signatureFile !== ''
			? base_url('assets/images/signatures/' . rawurlencode($signatureFile)) . '?v=' . filemtime(FCPATH . 'assets/images/signatures/' . $signatureFile)
			: '';
		$canSignStaff = (int) ($staff['id'] ?? 0) === (int) ($_SESSION['soma_id'] ?? 0)
			|| is_head_master_equivalent()
			|| \Config\MenuClearance::canSetStaffContract((int) ($_SESSION['soma_post'] ?? 0));
		?>
		<div class="boxed" style="background:#fff;clear:both;">
			<h4>Electronic signature</h4>
			<p class="text-muted" style="font-size:10pt;margin-bottom:8px;">This signature is used on expense requests, approvals, and reports. Draw it below, or upload a picture of a signature.</p>
			<?php if ($signatureSrc !== ''): ?>
				<img id="staff-signature-preview" src="<?= esc($signatureSrc); ?>" alt="Saved signature" style="display:block;height:52px;max-width:100%;background:#fff;border:1px solid #e5e7eb;margin-bottom:8px;">
			<?php else: ?>
				<img id="staff-signature-preview" alt="" style="display:none;height:52px;max-width:100%;background:#fff;border:1px solid #e5e7eb;margin-bottom:8px;">
			<?php endif; ?>
			<?php if ($canSignStaff): ?>
				<canvas id="staff-sign-pad" width="640" height="160" style="width:100%;height:140px;border:1px dashed #94a3b8;background:#fff;touch-action:none;cursor:crosshair;display:block;"></canvas>
				<div style="margin-top:8px;">
					<button type="button" class="btn btn-sm btn-light" id="staff-sign-clear">Clear</button>
					<button type="button" class="btn btn-sm btn-primary" id="staff-sign-save">Save drawn signature</button>
					<button type="button" class="btn btn-sm btn-outline-primary" id="staff-sign-upload">Upload signature</button>
					<input type="file" id="staff-sign-file" accept="image/png,image/jpeg,image/jpg,image/webp" style="display:none;">
					<button type="button" class="btn btn-sm btn-outline-danger" id="staff-sign-remove" <?= $signatureSrc === '' ? 'style="display:none;"' : ''; ?>>Remove</button>
				</div>
			<?php elseif ($signatureSrc === ''): ?>
				<span>—</span>
			<?php endif; ?>
		</div>
	</div>
</div>
<div class="row">
	<div class="mb-3 card" style="width: 100%">
		<div class="card-header-tab card-header">

			<ul class="nav" style="margin-left: 0">
				<li class="nav-item"><a data-toggle="tab" href="#tab-course" class="nav-link active"><?= lang("app.courseRecord");?></a></li>
				<li class="nav-item"><a data-toggle="tab" href="#tab-sms" class="nav-link"><?= lang("app.communicationHistory");?></a></li>
				<li class="nav-item"><a data-toggle="tab" href="#tab-leave" class="nav-link"><?= lang("app.leaveHistory");?></a></li>
			</ul>
		</div>
		<div class="card-body">
			<div class="tab-content">
				<div class="tab-pane active" id="tab-course" role="tabpanel"><p><?= lang("app.courseRecord");?></p></div>
				<div class="tab-pane" id="tab-sms" role="tabpanel"><p><?= lang("app.communicationActivity");?></p></div>
				<div class="tab-pane" id="tab-leave" role="tabpanel"><p><?= lang("app.leaveHistory");?></p></div>
			</div>
		</div>
		<div class="d-block text-right card-footer">
			<?php
			if ($staff['id']==$_SESSION["soma_id"]) {
				?>
				<a href="javascript:void(0);" class="btn-wide btn-shadow btn btn-info" data-toggle="modal"
				   data-target="#mdlPass"><?= lang("app.changePassword");?></a>
				<?php
			}
			?>
			<?php
			if (is_head_master_equivalent()) {
				?>
				<a href="javascript:void(0);" class="btn-wide btn-shadow btn btn-dark"><?= lang("app.changePost");?></a>
				<a href="javascript:void(0);" class="btn-wide btn-shadow btn btn-danger"><?= lang("app.del");?></a>
				<?php
			}
			?>
		</div>
	</div>
</div>
<script>
	$(function () {
		var sp, value, old_data, target, type = null;
		$(".spedit").on("dblclick", function () {
			sp = $(this);
			value = sp.data("value");
			old_data = sp.html();
			target = sp.data("target");
			type = sp.data("type") == undefined ? "text" : sp.data("type");
			if (type == "text")
				sp.html("<input type='text' value='" + value + "' class='sptxt'>");
			if (type == "number" || type == "digit")
				sp.html("<input type='text' data-parsley-type='number' value='" + value + "' class='sptxt'>");
			if (type == "status") {
				sp.html("<input type='checkbox' value='1' class='spchk'>");
				if (value == 1) {
					$(".spchk").prop("checked", true);
				}
			}
			if (type == "select") {
				sp.html("<select class='select2_auto' style='width:200px !important' data-value='" + value + "' data-href='" + sp.data("href") + "' class='spselect'>");
				load_select(sp.data("href"), value);
			}
			$(".sptxt").focus();
		});
		$(document).on("keydown blur", ".sptxt", function (e) {
			var sptxt = $(this);
			var id = $("#staff_section").data("id");
			var val = sptxt.val();
			if (e.which == 13 || e.type == 'focusout') {
				//enter is pressed
				if (val == value) {
					//no changes made, cancel
					sp.html(old_data);
					return;
				}
				$.post("<?=base_url('edit_staff/');?>" + type, "id=" + id + "&target=" + target + "&val=" + val, function (data) {
					if (data.hasOwnProperty("error")) {
						toastada.error('<?= lang("app.saveStaffFail");?>' + (data.error || data.msg || ''));
					} else if (data.hasOwnProperty("success")) {
						sp.html(data.result);
						sp.data("value", val);
						toastada.success('<?= lang("app.staffSaved");?>');
					} else {
						toastada.error('<?= lang("app.fatalErr");?>');
					}
				}).fail(function () {
					//unknown error
					toastada.error('<?= lang("app.systemErr");?>');
				});
			}
			if (e.which == 27) {
				//escape is pressed
				sp.html(old_data);
			}
		});
		$(document).on("change", ".spchk", function (e) {
			var spchk = $(this);
			var id = $("#staff_section").data("id");
			var val = spchk.is(":checked") ? 1 : 0;
			$.post("<?=base_url('edit_staff/');?>" + type, "id=" + id + "&target=" + target + "&val=" + val, function (data) {
				if (data.hasOwnProperty("error")) {
					toastada.error('<?= lang("app.saveStaffFail");?>' + (data.error || data.msg || ''));
				} else if (data.hasOwnProperty("success")) {
					sp.html(data.result);
					sp.data("value", val);
					toastada.success('<?= lang("app.staffSaved");?>');
				} else {
					//unknown error
					toastada.error('<?= lang("app.fatalErr");?>');
				}
			}).fail(function () {
				//unknown error
				toastada.error('<?= lang("app.systemErr");?>');
			});
		});
		window.staffEditingSelf = <?= $editingSelf ? 'true' : 'false'; ?>;
		window.staffPhotoFallback = <?= json_encode($fallbackPhoto); ?>;
		function toggleStaffPhotoRemove(show) {
			$("#btn_remove_photo").toggle(!!show);
		}
		window.toggleStaffPhotoRemove = toggleStaffPhotoRemove;
		function applyStaffPhoto(src) {
			$("#img_photo").prop("src", src);
			if (window.staffEditingSelf) {
				$(".js-session-avatar").attr("src", src);
			}
		}
		function removeStaffPhoto() {
			if (!confirm("<?= esc(lang('app.removeStaffPhotoConfirm'), 'js'); ?>")) {
				return;
			}
			var id = $("#staff_section").data("id");
			$.post(window.base_url + "remove_staff_photo", { id: id }, function (data) {
				if (data.hasOwnProperty("error")) {
					toastada.error(data.error);
				} else if (data.hasOwnProperty("success")) {
					applyStaffPhoto(window.staffPhotoFallback);
					$("#in_student_photo").val("");
					toggleStaffPhotoRemove(false);
					toastada.success(data.success);
				} else {
					toastada.error('<?= lang("app.fatalErr");?>');
				}
			}, "json").fail(function () {
				toastada.error('<?= lang("app.systemErr");?>');
			});
		}
		$("#btn_remove_photo").on("click", function (e) {
			e.preventDefault();
			e.stopPropagation();
			removeStaffPhoto();
		});
		function uploadStaffPhotoFile(file) {
			if (!file) {
				return;
			}
			var upload = new Upload(file);
			var type = (upload.getType() || '').toLowerCase();
			var name = (upload.getName() || '').toLowerCase();
			var typeOk = type === "image/jpg" || type === "image/jpeg" || type === "image/png" || /\.(jpe?g|png)$/.test(name);
			if (!typeOk) {
				toastada.error('<?= lang("app.allowedOnly");?>');
				return;
			}
			if (upload.getSize() > 5 * 1024 * 1024) {
				toastada.error('<?= lang("app.sizeNeeded");?>');
				return;
			}
			upload.previousSrc = $("#img_photo").attr("src");
			$("#img_photo").prop("src", upload.getSource());
			upload.doUpload("upload_image/staff_picture", $("#sp-photo-status"), $("#img_photo"), $("#staff_section").data("id"));
		}
		$("#in_student_photo").on("change", function () {
			uploadStaffPhotoFile(this.files && this.files[0]);
			this.value = "";
		});
		var dropZone = document.getElementById("dv_select_img");
		if (dropZone) {
			["dragenter", "dragover"].forEach(function (name) {
				dropZone.addEventListener(name, function (e) {
					e.preventDefault();
					dropZone.classList.add("is-over");
				});
			});
			["dragleave", "drop"].forEach(function (name) {
				dropZone.addEventListener(name, function (e) {
					e.preventDefault();
					dropZone.classList.remove("is-over");
				});
			});
			dropZone.addEventListener("drop", function (e) {
				var file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
				uploadStaffPhotoFile(file);
			});
		}
		if ($.fn.select2 && $("#staff_country").length) {
			$("#staff_country").select2({ width: "100%" });
		}
		$("#staff-profile-form").on("submit", function (e) {
			e.preventDefault();
			if (!canEditIdentity) {
				return;
			}
			var btn = $("#sp-save");
			btn.prop("disabled", true);
			$.post(window.base_url + "save_staff_profile", $(this).serialize(), function (data) {
				if (data && data.error) {
					toastada.error(data.error);
					btn.prop("disabled", false);
					return;
				}
				toastada.success((data && data.success) ? data.success : "Saved");
				window.location.reload();
			}, "json").fail(function () {
				toastada.error("Could not save the staff profile.");
				btn.prop("disabled", false);
			});
		});
	});

	var Upload = function (file) {
		this.file = file;
	};

	Upload.prototype.getType = function() {
		return this.file.type;
	};
	Upload.prototype.getSize = function() {
		return this.file.size;
	};
	Upload.prototype.getName = function() {
		return this.file.name;
	};
	Upload.prototype.getSource = function() {
		return URL.createObjectURL(this.file);
	};
	Upload.prototype.doUpload = function (url,loader,img,id=0) {
		var that = this;
		var formData = new FormData();

		// add assoc key values, this will be posts values
		formData.append("file", this.file, this.getName());
		formData.append("id", id);
		loader.text("Uploading...");
		$.ajax({
			type: "POST",
			url: window.base_url+url,
			xhr: function () {
				var myXhr = new window.XMLHttpRequest();
				if (myXhr.upload) {
					myXhr.upload.addEventListener('progress', that.progressHandling, false);
				}
				return myXhr;
			},
			success: function (data) {
				loader.text("Click or drop a photo");
				if (data.hasOwnProperty("error")){
					toastada.error('<?= lang("app.upLoadErr");?>' +data.error);
					if (that.previousSrc) img.prop("src", that.previousSrc);
				}else if (data.hasOwnProperty("success")){
					toastada.success(data.success);
					if (data.photo) {
						img.prop("src", data.photo);
						if (window.staffEditingSelf) {
							$(".js-session-avatar").attr("src", data.photo);
						}
					}
					if (window.toggleStaffPhotoRemove) window.toggleStaffPhotoRemove(true);
				}else{
					toastada.error('<?= lang("app.fatalErr");?>');
					if (that.previousSrc) img.prop("src", that.previousSrc);
				}
			},
			error: function (error) {
				toastada.error('<?= lang("app.systemErr");?>');
				if (that.previousSrc) img.prop("src", that.previousSrc);
				loader.text("Click or drop a photo");
			},
			async: true,
			data: formData,
			dataType: "json",
			cache: false,
			contentType: false,
			processData: false,
			timeout: 60000
		});
	};

	Upload.prototype.progressHandling = function (event) {

	};

	(function () {
		var canvas = document.getElementById('staff-sign-pad');
		if (!canvas) return;
		var ctx = canvas.getContext('2d');
		var drawing = false;
		var last = null;
		function fit() {
			var rect = canvas.getBoundingClientRect();
			var ratio = window.devicePixelRatio || 1;
			var w = Math.max(1, Math.round(rect.width * ratio));
			var h = Math.max(1, Math.round(rect.height * ratio));
			if (canvas.width !== w || canvas.height !== h) {
				canvas.width = w;
				canvas.height = h;
			}
			ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
			ctx.lineWidth = 2.4;
			ctx.lineCap = 'round';
			ctx.lineJoin = 'round';
			ctx.strokeStyle = '#1d4ed8';
		}
		fit();
		function point(e) {
			var rect = canvas.getBoundingClientRect();
			var src = e.touches && e.touches[0] ? e.touches[0] : e;
			return { x: src.clientX - rect.left, y: src.clientY - rect.top };
		}
		function start(e) {
			drawing = true;
			last = point(e);
			if (e.cancelable) e.preventDefault();
		}
		function move(e) {
			if (!drawing) return;
			var p = point(e);
			ctx.beginPath();
			ctx.moveTo(last.x, last.y);
			ctx.lineTo(p.x, p.y);
			ctx.stroke();
			last = p;
			if (e.cancelable) e.preventDefault();
		}
		function stop() {
			drawing = false;
			last = null;
		}
		canvas.addEventListener('mousedown', start);
		canvas.addEventListener('mousemove', move);
		window.addEventListener('mouseup', stop);
		canvas.addEventListener('touchstart', start, { passive: false });
		canvas.addEventListener('touchmove', move, { passive: false });
		canvas.addEventListener('touchend', stop);
		document.getElementById('staff-sign-clear').addEventListener('click', function () {
			fit();
			ctx.clearRect(0, 0, canvas.getBoundingClientRect().width, canvas.getBoundingClientRect().height);
		});
		function postSignature(payload) {
			$.post('<?= base_url('save_staff_signature'); ?>', payload, function (data) {
				if (data && data.error) {
					toastada.error(data.error);
					return;
				}
				toastada.success(data && data.success ? data.success : 'Saved');
				var preview = document.getElementById('staff-signature-preview');
				var removeBtn = document.getElementById('staff-sign-remove');
				if (data && data.src && preview) {
					preview.src = data.src;
					preview.style.display = 'block';
					if (removeBtn) removeBtn.style.display = '';
				}
				if (payload.clear === '1' && preview) {
					preview.style.display = 'none';
					preview.removeAttribute('src');
					if (removeBtn) removeBtn.style.display = 'none';
				}
			}, 'json').fail(function () {
				toastada.error('Could not save the signature.');
			});
		}
		document.getElementById('staff-sign-upload').addEventListener('click', function () {
			document.getElementById('staff-sign-file').click();
		});
		document.getElementById('staff-sign-file').addEventListener('change', function () {
			var file = this.files && this.files[0];
			this.value = '';
			if (!file) return;
			if (!/^image\/(png|jpeg|jpg|webp)$/i.test(file.type || '')) {
				toastada.error('Upload a PNG or JPG picture of the signature.');
				return;
			}
			var reader = new FileReader();
			reader.onload = function () {
				var img = new Image();
				img.onload = function () {
					var maxW = 1200;
					var scale = img.width > maxW ? maxW / img.width : 1;
					var w = Math.max(1, Math.round(img.width * scale));
					var h = Math.max(1, Math.round(img.height * scale));
					var canvasUp = document.createElement('canvas');
					canvasUp.width = w;
					canvasUp.height = h;
					var ctxUp = canvasUp.getContext('2d');
					ctxUp.fillStyle = '#ffffff';
					ctxUp.fillRect(0, 0, w, h);
					ctxUp.drawImage(img, 0, 0, w, h);
					postSignature({
						staff_id: '<?= (int) ($staff['id'] ?? 0); ?>',
						signature: canvasUp.toDataURL('image/png')
					});
				};
				img.onerror = function () {
					toastada.error('That picture could not be read.');
				};
				img.src = reader.result;
			};
			reader.readAsDataURL(file);
		});
		document.getElementById('staff-sign-save').addEventListener('click', function () {
			var rect = canvas.getBoundingClientRect();
			var out = document.createElement('canvas');
			out.width = Math.max(1, Math.round(rect.width));
			out.height = Math.max(1, Math.round(rect.height));
			var ink = out.getContext('2d');
			ink.fillStyle = '#ffffff';
			ink.fillRect(0, 0, out.width, out.height);
			ink.drawImage(canvas, 0, 0, out.width, out.height);
			postSignature({
				staff_id: $('#staff_section').data('id'),
				signature: out.toDataURL('image/png')
			});
		});
		var removeBtn = document.getElementById('staff-sign-remove');
		if (removeBtn) {
			removeBtn.addEventListener('click', function () {
				postSignature({ staff_id: $('#staff_section').data('id'), clear: '1' });
				fit();
				ctx.clearRect(0, 0, canvas.getBoundingClientRect().width, canvas.getBoundingClientRect().height);
			});
		}
	})();
	var canEditIdentity = <?= $canEditStaff ? 'true' : 'false'; ?>;
	function applyIdentityKind(kind) {
		$('.sid-panel').each(function () {
			var on = $(this).data('kind') === kind;
			$(this).toggle(on);
			$(this).find(':input').prop('disabled', !(on && canEditIdentity));
		});
		$('.sid-switch label').removeClass('is-on');
		$('.sid-switch input[value="' + kind + '"]').closest('label').addClass('is-on');
	}
	$('.sid-switch input').on('change', function () {
		applyIdentityKind($(this).val());
	});
	var pickedKind = $('.sid-switch input:checked').val() || '';
	if (pickedKind) {
		applyIdentityKind(pickedKind);
	}
	$('#sid_province').on('change', function () {
		var val = $(this).val();
		$('#sid_district').html('<option value="">Select</option>');
		$('#sid_sector').html('<option value="">Select</option>');
		if (!val) {
			return;
		}
		$.get(window.base_url + 'get_address/district', { key: 'province', val: val }, function (html) {
			$('#sid_district').html(html);
		});
	});
	$('#sid_district').on('change', function () {
		var val = $(this).val();
		$('#sid_sector').html('<option value="">Select</option>');
		if (!val) {
			return;
		}
		$.get(window.base_url + 'get_address/sector', { key: 'district', val: val }, function (html) {
			$('#sid_sector').html(html);
		});
	});
	$('#staff-identity-form').on('submit', function (e) {
		e.preventDefault();
		if (!canEditIdentity) {
			return;
		}
		var kind = $('.sid-switch input:checked').val();
		if (!kind) {
			toastada.error('Choose Rwandan or Non-Rwandan.');
			return;
		}
		var btn = $('#sid-save');
		btn.prop('disabled', true);
		$.ajax({
			url: window.base_url + 'save_staff_identity',
			type: 'POST',
			data: new FormData(this),
			processData: false,
			contentType: false,
			dataType: 'json'
		}).done(function (data) {
			if (data && data.error) {
				toastada.error(data.error);
				btn.prop('disabled', false);
				return;
			}
			toastada.success((data && data.success) ? data.success : 'Saved');
			window.location.reload();
		}).fail(function () {
			toastada.error('Could not save the identity details.');
			btn.prop('disabled', false);
		});
	});
</script>
