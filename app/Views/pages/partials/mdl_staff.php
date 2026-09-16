<div class="modal fade" id="mdlStaff" tabindex="-1" role="dialog">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header" style="background:#012F6B;color:#fff;border-bottom:1px solid rgba(255,255,255,.12);">
				<h5 class="modal-title" style="color:#fff;"><?= lang("app.createNewStaff"); ?></h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color:#fff;opacity:.9;">
					<span aria-hidden="true">×</span>
				</button>
			</div>
			<div class="modal-body">
				<ul class="nav nav-tabs mb-3" role="tablist" style="border-bottom: 1px solid rgba(1,47,107,.15);">
					<li class="nav-item">
						<a class="nav-link active" data-toggle="tab" href="#staffTabSingle" role="tab" style="color:#012F6B;font-weight:700;border-bottom:3px solid #012F6B;">Single staff</a>
					</li>
					<li class="nav-item">
						<a class="nav-link" data-toggle="tab" href="#staffTabBulk" role="tab" style="color:#012F6B;font-weight:600;"><?= lang("app.massUploading"); ?></a>
					</li>
				</ul>
				<div class="tab-content">
					<div class="tab-pane fade show active" id="staffTabSingle" role="tabpanel">
						<form action="<?= base_url('manipulate_staff'); ?>" class="autoSubmit validate" id="frmStaffSingle">
							<div class="row">
								<div class="col-md-6">
									<div class="form-group">
										<label><?= lang("app.firstName"); ?></label>
										<input type="text" class="form-control" name="fname" required placeholder="First name">
									</div>
								</div>
								<div class="col-md-6">
									<div class="form-group">
										<label><?= lang("app.fastName"); ?></label>
										<input type="text" class="form-control" name="lname" required placeholder="Last name">
									</div>
								</div>
							</div>
							<div class="form-group">
								<label><?= lang("app.phone"); ?></label>
								<input class="form-control" type="text" name="phone" required minlength="3">
							</div>
							<div class="form-group">
								<label><?= lang("app.email"); ?></label>
								<input class="form-control" type="email" name="email" required minlength="3">
							</div>
							<div class="form-group">
								<label><?= lang("app.privilege"); ?></label>
								<a href="javascript:void" class="pull-right" data-toggle="refresh"
								   data-href="<?= base_url('get_posts'); ?>" data-target="staff_privilege"
								   style="margin: 0 10px"><i class="fa fa-sync faa-spin"></i> </a>
								<select class="form-control select2" style="width: 100%" name="privilege" id="staff_privilege" required>
									<option selected disabled><?= lang("app.selectPrivilege"); ?></option>
								</select>
							</div>
							<div class="form-group">
								<label><?= lang("app.createNewPost"); ?></label>
								<div class="input-group">
									<input type="text" class="form-control new-post-title" maxlength="80"
										   placeholder="<?= lang("app.newPostPlaceholder"); ?>">
									<div class="input-group-append">
										<button type="button" class="btn btn-outline-primary btn-create-post"><?= lang("app.add"); ?></button>
									</div>
								</div>
							</div>
							<p class="text-muted small mb-3">
								Work shift can be assigned later from <strong>View all staffs</strong> or
								<strong>School settings → Staff attendance settings</strong>.
							</p>
							<div class="row">
								<div class="col-md-6">
									<div class="form-group">
										<label><?= lang("app.country"); ?></label>
										<?= view('pages/partials/country_select_staff'); ?>
									</div>
								</div>
								<div class="col-md-6">
									<div class="form-group">
										<label><?= lang("app.city"); ?></label>
										<input class="form-control" type="text" name="city" required minlength="3" placeholder="Ex: Kigali">
									</div>
								</div>
							</div>
							<div class="form-group mb-0">
								<label><?= lang("app.address2"); ?></label>
								<input class="form-control" type="text" name="address" minlength="3" placeholder="Ex: Street no...">
							</div>
						</form>
					</div>
					<div class="tab-pane fade" id="staffTabBulk" role="tabpanel">
						<p class="text-muted">
							Download the Excel template, fill in staff rows, then upload. In the <strong>Privilege</strong> column, use the dropdown to pick a post (loaded from your school database). See the <strong>Privileges</strong> sheet for the full list.
							Shifts are not set during import — assign them later per staff member.
						</p>
						<a href="<?= base_url('download_staff_template'); ?>" class="btn btn-outline-primary btn-sm mb-3">
							<i class="fa fa-download"></i> <?= lang("app.exceltemplate"); ?>
						</a>
						<form action="<?= base_url('upload_staff_template'); ?>" method="post" enctype="multipart/form-data" id="frmStaffBulk">
							<div class="form-group staff-bulk-upload-zone mb-0">
								<label class="d-block mb-2"><i class="fa fa-upload"></i> <?= lang("app.chooseFile"); ?></label>
								<input type="file" name="documents" class="form-control staff-bulk-file-input" accept=".xlsx,.xls,.csv" required>
							</div>
						</form>
					</div>
				</div>
			</div>
			<div class="modal-footer" id="mdlStaffFooter">
				<button type="button" class="btn btn-secondary" data-dismiss="modal"><?= lang("app.close"); ?></button>
				<button type="submit" form="frmStaffSingle" id="mdlStaffSaveBtn" class="btn btn-gradient-primary"><?= lang("app.saveChanges"); ?></button>
			</div>
		</div>
	</div>
</div>
