<style type="text/css">
	/* Online registration — matches SmartSMS landing brand */
	.ss-app {
		--app-cyan: #0EA5E9;
		--app-cyan-d: #0284C7;
		--app-navy: #0B1220;
		--app-ink: #0F172A;
		--app-muted: #64748B;
		--app-line: #E2E8F0;
		--app-paper: #F8FAFC;
		--app-ok: #0F766E;
		padding: calc(72px + 2rem) 1rem 3rem;
		min-height: 70vh;
		background:
			radial-gradient(900px 420px at 10% -10%, rgba(14,165,233,.18), transparent 55%),
			radial-gradient(700px 360px at 100% 0%, rgba(3,105,161,.12), transparent 50%),
			var(--app-paper);
		font-family: "Outfit", "Segoe UI", sans-serif;
		color: var(--app-ink);
	}
	.ss-app .progress-card {
		margin: 0 auto !important;
		max-width: 720px;
		width: 100%;
		float: none !important;
	}
	.ss-app .card {
		z-index: 0;
		border: 1px solid var(--app-line);
		border-radius: 18px;
		background: #fff;
		box-shadow: 0 18px 48px rgba(15, 23, 42, .08);
		padding: 1.5rem 1.35rem 1.75rem;
		position: relative;
		overflow: hidden;
	}
	.ss-app .card::before {
		content: "";
		position: absolute;
		left: 0; right: 0; top: 0;
		height: 4px;
		background: linear-gradient(90deg, var(--app-cyan), var(--app-cyan-d));
	}

	#msform { width: 100%; position: relative; }
	#msform fieldset {
		border: 0;
		padding: 0;
		margin: 0;
		background: transparent;
		width: 100%;
		position: relative;
	}
	#msform fieldset:not(:first-of-type) { display: none; }
	.form-card { text-align: left; }

	.ss-app .fs-title {
		font-family: "Fraunces", Georgia, serif;
		font-size: 1.55rem;
		font-weight: 650;
		color: var(--app-navy);
		margin: 0 0 1rem;
		line-height: 1.25;
		text-align: left;
	}
	.ss-app .newApplicant {
		font-size: .95rem;
		font-weight: 600;
		color: var(--app-ink);
		margin: 1.1rem 0 .65rem;
	}
	.ss-app hr.newApplicant {
		border: 0;
		border-top: 1px solid var(--app-line);
		margin: 0 0 1.1rem;
	}

	.ss-app .form-group { margin-bottom: 1rem; }
	.ss-app .control-label,
	.ss-app label.control-label,
	.ss-app .form-check-label {
		display: block;
		font-size: .82rem;
		font-weight: 600;
		color: var(--app-muted);
		margin-bottom: .4rem;
		letter-spacing: .01em;
	}
	.ss-app .form-check {
		display: flex;
		align-items: flex-start;
		gap: .6rem;
		padding: .85rem 1rem;
		background: var(--app-paper);
		border: 1px solid var(--app-line);
		border-radius: 12px;
		margin-bottom: 1rem;
	}
	.ss-app .form-check-input {
		width: 1.05rem;
		height: 1.05rem;
		margin: .15rem 0 0;
		accent-color: var(--app-cyan);
		flex-shrink: 0;
	}
	.ss-app .form-check-label {
		margin: 0;
		font-size: .92rem;
		font-weight: 500;
		color: var(--app-ink);
		cursor: pointer;
	}

	.ss-app .form-control,
	#msform input[type="text"],
	#msform input[type="email"],
	#msform input[type="number"],
	#msform input[type="tel"],
	#msform input[type="file"],
	#msform select,
	#msform textarea {
		display: block;
		width: 100%;
		max-width: 100%;
		box-sizing: border-box;
		appearance: none;
		-webkit-appearance: none;
		background: #fff;
		border: 1px solid #CBD5E1;
		border-radius: 10px;
		padding: .7rem .9rem;
		font-size: .95rem;
		font-family: inherit;
		color: var(--app-ink);
		line-height: 1.4;
		transition: border-color .15s ease, box-shadow .15s ease;
	}
	#msform select {
		background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%2364748B' d='M1.4.6 6 5.2 10.6.6 12 2 6 8 0 2z'/%3E%3C/svg%3E");
		background-repeat: no-repeat;
		background-position: right .9rem center;
		padding-right: 2.4rem;
	}
	#msform input[type="file"] {
		padding: .55rem .75rem;
		background: var(--app-paper);
		cursor: pointer;
	}
	#msform input:focus,
	#msform select:focus,
	#msform textarea:focus {
		outline: none !important;
		border-color: var(--app-cyan) !important;
		box-shadow: 0 0 0 3px rgba(14,165,233,.2) !important;
	}
	#msform input::placeholder { color: #94A3B8; }

	#msform .action-button,
	#msform .action-button-previous,
	.ss-app .search-button,
	#msform #btn-pay {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		min-width: 118px;
		height: 44px;
		padding: 0 1.15rem;
		margin: 1.25rem .4rem 0 0;
		border: 0;
		border-radius: 999px;
		font-family: inherit;
		font-size: .9rem;
		font-weight: 650;
		cursor: pointer;
		float: none;
		transition: transform .12s ease, background .15s ease, opacity .15s ease;
	}
	#msform .action-button,
	.ss-app .search-button,
	#msform #btn-pay {
		background: linear-gradient(135deg, var(--app-cyan), var(--app-cyan-d));
		color: #fff;
		box-shadow: 0 8px 20px rgba(14,165,233,.28);
	}
	#msform .action-button:hover,
	.ss-app .search-button:hover,
	#msform #btn-pay:hover { filter: brightness(1.05); transform: translateY(-1px); }
	#msform .action-button-previous {
		background: #E2E8F0;
		color: var(--app-ink);
		box-shadow: none;
	}
	#msform .action-button-previous:hover { background: #CBD5E1; }
	#msform #btn-pay:disabled {
		opacity: .45;
		cursor: not-allowed;
		transform: none;
		box-shadow: none;
	}
	.ss-app .fieldset-actions {
		display: flex;
		flex-wrap: wrap;
		justify-content: flex-end;
		gap: .35rem;
		margin-top: .5rem;
	}

	#progressbar {
		display: flex;
		justify-content: space-between;
		gap: .25rem;
		list-style: none;
		margin: 0 0 1.75rem;
		padding: 0;
		overflow: visible;
		color: #94A3B8;
		counter-reset: step;
	}
	#progressbar li {
		list-style: none;
		flex: 1;
		float: none;
		width: auto;
		position: relative;
		font-size: .72rem;
		font-weight: 600;
		text-align: center;
		line-height: 1.25;
		color: #94A3B8;
	}
	#progressbar li strong {
		display: block;
		margin-top: .15rem;
		font-weight: 600;
	}
	#progressbar li:before {
		counter-increment: step;
		content: counter(step);
		width: 38px;
		height: 38px;
		line-height: 38px;
		display: block;
		font-size: .85rem;
		font-weight: 700;
		font-family: "Outfit", sans-serif;
		color: #fff;
		background: #CBD5E1;
		border-radius: 50%;
		margin: 0 auto .45rem;
		padding: 0;
		position: relative;
		z-index: 2;
		box-shadow: 0 0 0 4px #fff;
	}
	#progressbar li:after {
		content: "";
		width: 100%;
		height: 3px;
		background: #E2E8F0;
		position: absolute;
		left: -50%;
		top: 18px;
		z-index: 1;
	}
	#progressbar li:first-child:after { display: none; }
	#progressbar li.active { color: var(--app-cyan-d); }
	#progressbar li.active:before,
	#progressbar li.active:after {
		background: linear-gradient(135deg, var(--app-cyan), var(--app-cyan-d));
	}
	#progressbar #payment:before,
	#progressbar #personal:before,
	#progressbar #complete:before,
	#progressbar #confirm:before,
	#progressbar #documents:before {
		font-family: inherit;
		content: counter(step);
	}

	.ss-app .requirement-doc {
		display: none;
		border: 1px dashed rgba(14,165,233,.55) !important;
		background: rgba(14,165,233,.06);
		padding: 14px !important;
		color: var(--app-cyan-d) !important;
		margin: .85rem 0 1rem !important;
		border-radius: 12px;
	}
	.ss-app .requirement-doc a { color: var(--app-cyan-d); font-weight: 650; }
	.ss-app .requirement-doc .text-desc { color: var(--app-muted); margin: .4rem 0 0; font-size: .85rem; }

	.ss-app .alert {
		border-radius: 12px;
		padding: 12px 14px;
		font-size: .88rem;
		line-height: 1.45;
		margin-bottom: 1rem;
	}
	.ss-app .alert-info {
		background: rgba(14,165,233,.08);
		border: 1px solid rgba(14,165,233,.25);
		color: var(--app-ink);
	}
	.ss-app .alert-warning {
		background: #FFF7ED;
		border: 1px solid #FDBA74;
		color: #9A3412;
	}
	.ss-app .reg-fee-box {
		background: var(--app-paper) !important;
		border: 1px solid var(--app-line) !important;
		border-radius: 14px !important;
		padding: 16px 18px !important;
		margin-bottom: 1.1rem !important;
	}
	.ss-app .text-muted { color: var(--app-muted) !important; }
	.ss-app .text-danger { color: #DC2626 !important; }

	.payment_pending p,
	.confirmPay p,
	.failedPay p {
		padding: 16px 18px;
		border-radius: 12px;
		font-size: .9rem;
	}
	.payment_pending p { background: #FFFBEB; border: 1px solid #F59E0B; color: #92400E; }
	.confirmPay p { background: #ECFDF5; border: 1px solid #34D399; color: #065F46; }
	.failedPay p { background: #FEF2F2; border: 1px solid #F87171; color: #991B1B; }
	#pending { display: none; }
	#confirmPay { display: none; }

	.ss-app .row { display: flex; flex-wrap: wrap; margin: 0 -6px; }
	.ss-app .row > [class*="col-"] { padding: 0 6px; box-sizing: border-box; }
	.ss-app .col-7, .ss-app .col-11, .ss-app .col-10, .ss-app .col-12 { width: 100%; }
	.ss-app .col-sm-12 { width: 100%; }
	.ss-app .justify-content-center { justify-content: center; }
	.ss-app .text-center { text-align: center; }
	@media (min-width: 640px) {
		.ss-app .card { padding: 1.85rem 1.85rem 2rem; }
		#progressbar li { font-size: .78rem; }
		#progressbar li:before { width: 42px; height: 42px; line-height: 42px; }
		#progressbar li:after { top: 20px; }
	}

	.toast-container{ position:fixed; z-index:999999999; max-width:300px; }
	.toast{ font-family:inherit; font-weight:500; letter-spacing:.02em; opacity:1; position:relative; right:0; color:#fff; background:rgba(15,23,42,.92); padding:14px 16px; margin-bottom:8px; border-radius:10px; transition:.3s all ease; }
	.toast.toast-exit{ transition:.4s all ease; transform:translate3d(0,0,0); right:-300px; opacity:0; }
	.toast-success{ background:rgba(15,118,110,.95); }
	.toast-info{ background:rgba(2,132,199,.95); }
	.toast-error{ background:rgba(185,28,28,.95); }
	.toast-warn{ background:rgba(180,83,9,.95); }

	.purple-text { color: var(--app-cyan-d); font-weight: 650; }
</style>

<section class="ss-app" id="home">
	<div class="container-fluid" style="padding:0;">
		<div class="row justify-content-center" style="margin:0;">
			<div class="progress-card">
				<div class="card">
					<?php if (isset($error)) : ?>
						<div class="failedPay">
							<div class="row justify-content-center">
								<div class="col-12">
									<p><?= $error; ?></p>
								</div>
							</div>
						</div>
					<?php else: ?>
						<div id="msform">
							<!-- progressbar -->
							<ul id="progressbar">
								<li id="personal" class="active"><strong>Personal</strong></li>
								<li id="payment" class="<?= isset($applicationId) ? 'active' : ''; ?>"><strong>Parent</strong></li>
								<li id="documents" class="<?= isset($applicationId) ? 'active' : ''; ?>"><strong>Documents</strong></li>
								<li id="complete" class="<?= isset($applicationId) ? 'active' : ''; ?>"><strong>Payment</strong></li>
								<li id="confirm" class="<?= isset($applicationId) ? 'active' : ''; ?>"><strong>Finish</strong></li>
							</ul>

							<!-- fieldsets -->
							<?php if (!isset($applicationId)) : ?>
								<form method="post" id="autoSave"
									  action="<?= site_url('manipulateStudentSelfRegistration'); ?>"
									  class="validates" enctype="multipart/form-data">
									<!-- STEP 1: PERSONAL -->
									<fieldset>
										<div class="form-card ">
											<div class="row">
												<div class="col-7">
													<h2 class="fs-title">Student self application</h2>
												</div>
											</div>
											<div class="row">
												<div class="col-11">
													<div class="form-check">
														<input type="checkbox" class="form-check-input" id="confirmBox">
														<label class="form-check-label" for="confirmBox">Have you paid and have a registration code?</label>
													</div>
													<div class="row" id="currentApplicant" style="display: none">
														<div class="col-sm-12">
															<div class="form-group">
																<label class="control-label mb-1">Enter your registration code</label>
																<input name="code" type="text" class="form-control" placeholder="Enter your code">
														</div>
														<input type="button" name="search" id="searchBtn" class="search-button" value="Search"/>
													</div>
													</div>
													<h6 class="newApplicant">Fill this form to start a new application</h6>
													<hr class="newApplicant">
													<div class="newApplicant">
														<?php if (!empty($private_link_error)): ?>
															<div class="alert alert-warning"><?= esc($private_link_error); ?></div>
														<?php endif; ?>
														<?php
														$lockedSchoolId = (int) ($locked_school_id ?? 0);
														$lockedSchoolName = (string) ($locked_school_name ?? '');
														?>
														<input type="hidden" id="locked_school_id" value="<?= $lockedSchoolId; ?>">
														<?php if ($lockedSchoolId > 0 && $lockedSchoolName !== ''): ?>
															<div class="alert alert-info" style="background:#ecfeff;border-color:#a5f3fc;color:#155e75;padding:10px 12px;border-radius:8px;">
																<strong>School:</strong> <?= esc($lockedSchoolName); ?>
																<br><small>You opened this school's private registration link.</small>
															</div>
														<?php endif; ?>
														<div class="form-group">
															<label class="control-label mb-1">School program</label>
															<select class="form-control" name="schoolProgram" id="schoolProgram">
																<option disabled selected>-- Choose program --</option>
																<option value="2">REB</option>
																<option value="1">RTB</option>
															</select>
														</div>
														<div class="form-group" id="schoolSelectWrap" <?= $lockedSchoolId > 0 ? 'style="display:none;"' : ''; ?>>
															<label for="schoolOptions" class="control-label mb-1">Schools</label>
															<select class="form-control" name="school" id="schoolOptions">
																<?php if ($lockedSchoolId > 0): ?>
																	<option value="<?= $lockedSchoolId; ?>" selected><?= esc($lockedSchoolName); ?></option>
																<?php else: ?>
																	<option disabled selected>-- Choose school --</option>
																<?php endif; ?>
															</select>
														</div>

														<div class="requirement-doc">
															<h5 style="text-align: center; margin:0;">
																<a target="_blank">
																	<i class="fa fa-exclamation-triangle"></i> Requirement document <i class="fa fa-exclamation-triangle"></i>
																</a>
															</h5>
															<p class="text-desc" style="text-align: center">Please read all requirements before you continue</p>
														</div>

														<div class="failedPay registration-error" style="display: none">
															<div class="row justify-content-center">
																<div class="col-12">
																	<p></p>
																</div>
															</div>
														</div>

														<div class="registration-data" style="display: none">
															<div class="form-group">
																<label for="facultyOptions" class="control-label mb-1">Faculty</label>
																<select class="form-control" name="faculty" id="facultyOptions">
																	<option disabled selected>-- Choose faculty --</option>
																</select>
															</div>
															<div class="form-group">
																<label for="departmentOptions" class="control-label mb-1">Department</label>
																<select class="form-control" name="department" id="departmentOptions">
																	<option disabled selected>-- Choose department --</option>
																</select>
															</div>
															<div class="form-group has-success">
																<label for="levelOptions" class="control-label mb-1">Level</label>
																<select class="form-control" name="level" required id="levelOptions">
																	<option disabled selected>-- Choose Level --</option>
																</select>
															</div>
															<div class="form-group">
																<label class="control-label mb-1">First name</label>
																<input required name="firstName" type="text" class="form-control" placeholder="First name">
															</div>
															<div class="form-group">
																<label class="control-label mb-1">Last name</label>
																<input required name="lastName" type="text" class="form-control" placeholder="Last name">
															</div>
															<div class="form-group">
																<label class="control-label mb-1">Gender</label>
																<select class="form-control" name="gender" required>
																	<option disabled selected>-- Choose Gender --</option>
																	<option value="M">Male</option>
																	<option value="F">Female</option>
																</select>
															</div>
															<div class="form-group has-success">
																<label class="control-label mb-1">Phone number (Enter parent's phone if you don't have phone)</label>
																<input id="cc-name" name="phoneNumber" placeholder="Phone number" type="text" required class="form-control">
															</div>

															<div class="form-group has-success">
																<label class="control-label mb-1">Studying mode</label>
																<select class="form-control" name="studingMode">
																	<option disabled selected>-- Choose mode --</option>
																	<option value="0">Boarding</option>
																	<option value="1">Day</option>
																</select>
															</div>
														</div><!-- /.registration-data -->
													</div><!-- /.newApplicant -->
												</div>
											</div>
										</div>
										<input type="button" name="next" class="next action-button newApplicant" value="Next" style="display:none;"/>
									</fieldset>

									<!-- STEP 2: PARENT INFORMATION -->
									<fieldset>
										<div class="form-card">
											<div class="form-group">
												<label class="control-label mb-1">Parent relationship</label>
												<select class="form-control" name="relationship" id="relationship" required>
													<option disabled selected>-- Choose relationship --</option>
													<option value="1">Father</option>
													<option value="2">Mother</option>
													<option value="3">Guardian</option>
												</select>
											</div>
											<div class="form-group">
												<label class="control-label mb-1">Names</label>
												<input name="parentNames" type="text" class="form-control" required>
											</div>
											<div class="form-group has-success">
												<label class="control-label mb-1">Phone number</label>
												<input name="parentPhone" type="text" class="form-control" required>
											</div>
											<div class="form-group">
												<label class="control-label mb-1">Email</label>
												<input name="email" type="email" class="form-control">
											</div>
										</div>
										<input type="button" name="next" class="next action-button" value="Next"/>
										<input type="button" name="previous" class="previous action-button-previous" value="Previous"/>
									</fieldset>

									<!-- STEP 3: DOCUMENTS (dynamic by faculty + level) -->
									<fieldset>
										<div class="form-card">
											<h6 class="fs-title">Upload required documents</h6>
											<p class="text-muted">Accepted types: PDF, JPG, PNG. Max 5 MB per file.</p>

											<div id="docsHint" class="alert alert-info" style="padding:10px; border-radius:8px;">
												<i class="fa fa-info-circle"></i>
												<span id="docsHintText">Select a faculty and level first — required uploads will appear here.</span>
											</div>

											<div id="dynamicDocsContainer">
												<p class="text-muted" id="docsEmptyMsg">Choose school, faculty and level on the Personal step to load the correct document list.</p>
											</div>

											<div class="form-group" style="display:none;">
												<input type="file" name="documents[]" id="legacyDocuments" class="form-control" multiple accept=".pdf,.jpg,.jpeg,.png">
											</div>
										</div>
										<input type="button" name="next" class="next action-button" value="Next"/>
										<input type="button" name="previous" class="previous action-button-previous" value="Previous"/>
									</fieldset>

									<!-- STEP 4: PAYMENT -->
									<fieldset>
										<div class="form-card">
											<div class="reg-fee-box" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:14px 16px;margin-bottom:16px;">
												<h5 class="fs-title" style="font-size:16px;margin:0 0 10px;">Registration fee summary</h5>
												<table style="width:100%;font-size:14px;margin:0;">
													<tr>
														<td style="padding:4px 0;">Registration fee</td>
														<td style="padding:4px 0;text-align:right;"><strong id="registration_amount">—</strong></td>
													</tr>
													<tr class="fee-row-gateway" id="rowServiceCharges">
														<td style="padding:4px 0;color:#64748b;">Service charges</td>
														<td style="padding:4px 0;text-align:right;color:#64748b;" id="registration_charges">600 Rwf</td>
													</tr>
													<tr class="fee-row-gateway" id="rowPlatformFee">
														<td style="padding:4px 0;color:#64748b;">Platform fee</td>
														<td style="padding:4px 0;text-align:right;color:#64748b;" id="registration_platform">100 Rwf</td>
													</tr>
													<tr style="border-top:1px solid #cbd5e1;">
														<td style="padding:8px 0 0;font-weight:700;">Total due</td>
														<td style="padding:8px 0 0;text-align:right;font-weight:700;color:#0f766e;" id="registration_due">—</td>
													</tr>
												</table>
												<input type="hidden" id="fee_raw_registration" value="0">
												<input type="hidden" id="fee_raw_charges" value="600">
												<input type="hidden" id="fee_raw_platform" value="100">
												<div id="payment_bypass_note" class="alert alert-warning" style="display:none;margin:12px 0 0;padding:10px;border-radius:8px;font-size:13px;">
													<strong>Live MOMO is unavailable right now.</strong>
													You can still submit and upload another payment proof (bank slip, receipt, etc.), or ask the school to enable MoPay and set the MOMO receive number in Basic Settings.
												</div>
												<div id="payment_momo_ready_note" class="alert alert-info" style="display:none;margin:12px 0 0;padding:10px;border-radius:8px;font-size:13px;background:#ecfeff;border-color:#a5f3fc;color:#155e75;">
													Approve the MTN MOMO prompt on your phone. The school receives the registration fee on the MOMO number configured in Basic Settings.
												</div>
												</div>
												<input type="hidden" name="applicationId">
												<input type="hidden" name="applicationSettings">
											<input type="hidden" id="payment_bypass_flag" value="0">

											<div class="form-group">
												<label class="control-label mb-2">How will you pay?</label>
												<div class="form-check" style="margin-bottom:8px;">
													<input class="form-check-input" type="radio" name="paymentMethod" id="payMethodMomo" value="momo" checked>
													<label class="form-check-label" for="payMethodMomo">Live payment with MTN MOMO</label>
											</div>
												<div class="form-check">
													<input class="form-check-input" type="radio" name="paymentMethod" id="payMethodProof" value="proof">
													<label class="form-check-label" for="payMethodProof">Other payment — attach proof (receipt / bank slip)</label>
												</div>
											</div>

											<div id="payPanelMomo" class="form-group has-success">
												<label class="control-label mb-1">
													MOMO phone number
													<span style="font-size: 10px;color: red !important;" id="momo_phone_hint">*Phone that will be charged via MTN MOMO</span>
												</label>
												<input id="cc-name" name="momoPhoneNumber" type="number" class="form-control">
											</div>

											<div id="payPanelProof" class="form-group" style="display:none;">
												<label class="control-label mb-1">Payment proof (PDF, JPG, PNG) <span class="text-danger">*</span></label>
												<input type="file" name="paymentProof" id="paymentProof" class="form-control" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png">
												<small class="text-muted d-block">This file will appear under Uploaded documents for school review.</small>
												<small class="text-muted d-block" id="preview-paymentProof" style="margin-top:6px;"></small>
												<label class="control-label mb-1" style="margin-top:10px;">Contact phone</label>
												<input name="proofPhoneNumber" type="number" class="form-control" placeholder="Phone we can reach you on">
											</div>

											<div class="form-group">
												<div class="form-check">
													<input type="checkbox" class="form-check-input" name="confirm" id="exampleCheck1" onchange="agreeTerms()">
													<label class="form-check-label" for="exampleCheck1">I agree to the <a href="#">terms &amp; conditions</a></label>
												</div>
												<div class="row justify-content-center">
													<button type="submit" class="btn btn-md btn-info" id="btn-pay" disabled>Submit application</button>
												</div>
												<div id="pending" class="payment_pending">
													<div class="row row justify-content-center">
														<div class="col-10">
															<p>Complete payment on your phone and continue with your application, if you didn't receive payment popup dial *182*7*1#</p>
															<img src="<?= base_url('assets/images/loading.gif'); ?>" alt="Pending">
														</div>
													</div>
												</div>
											</div>
										</div>
										<input type="button" name="previous" class="previous action-button-previous finalPrev" value="Previous"/>
									</fieldset>
								</form>
							<?php else: ?>
								<fieldset>
									<div class="form-card">
										<div class="row justify-content-center">
											<h2 class="purple-text text-center" style="width: 100%;"><strong>SUCCESS !</strong></h2> <br>
											<div class="row justify-content-center">
												<div class="col-3"><img src="https://i.imgur.com/GwStPmg.png" class="fit-image"></div>
											</div>
											<br>
											<div class="row justify-content-center">
												<div class="col-11 text-center">
													<h5 class="purple-text text-center" style="margin-bottom:16px;">
														Your application is successfully received.<br>
														If you don't get an SMS within 24 hours, please contact us:
													</h5>
													<div class="reg-success-contact" style="text-align:left;max-width:420px;margin:0 auto;background:#0b1f3a;color:#e8eef7;border-radius:12px;padding:16px 18px;">
														<div style="font-weight:700;letter-spacing:.06em;color:#d97706;margin-bottom:10px;">CONTACT</div>
														<div style="margin-bottom:8px;">
															<a href="mailto:info@xanderglobalacademy.com" style="color:#e8eef7;text-decoration:none;">info@xanderglobalacademy.com</a>
														</div>
														<div style="margin-bottom:6px;"><a href="tel:+250788797673" style="color:#e8eef7;text-decoration:none;">+250 788 797 673</a></div>
														<div style="margin-bottom:6px;"><a href="tel:+12704387305" style="color:#e8eef7;text-decoration:none;">+1 (270) 438-7305</a></div>
														<div style="margin-bottom:10px;"><a href="tel:+250788242069" style="color:#e8eef7;text-decoration:none;">+250 788 242 069</a></div>
														<div style="color:#cbd5e1;font-size:13px;">San Francisco Office | Rwanda Office (Kigali)</div>
													</div>
												</div>
											</div>
										</div>
									</div>
								</fieldset>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>

	<!-- jQuery FIRST (local + CDN fallback) -->
	<script src="<?= base_url('assets/js/jquery.min.js'); ?>"></script>
	<script> if (!window.jQuery) { document.write('<script src="https://code.jquery.com/jquery-3.7.1.min.js"><\/script>'); } </script>

	<!-- Parsley AFTER jQuery (local + CDN fallback) -->
	<script src="<?= base_url('assets/js/parsley.min.js'); ?>"></script>
	<script> if (typeof window.jQuery === 'undefined' || typeof $.fn.parsley === 'undefined') { document.write('<script src="https://cdn.jsdelivr.net/npm/parsleyjs@2.9.3/dist/parsley.min.js"><\/script>'); } </script>

	<!-- Toast AFTER jQuery -->
	<script type="text/javascript" src="<?= base_url('assets/js/toast.js'); ?>"></script>

	<!-- Main inline script (safe: jQuery is now present) -->
	<script type="text/javascript">
		let checkInterval;

		$(document).ready(function () {
			$("[name='phoneNumber']").change(function () {
				$("[name='momoPhoneNumber']").val($(this).val());
				$("[name='proofPhoneNumber']").val($(this).val());
			});

			function formatRwf(n) {
				n = parseInt(n, 10) || 0;
				return n.toLocaleString('en-US') + ' Rwf';
			}
			function syncFeeSummary() {
				var method = $("input[name='paymentMethod']:checked").val() || 'momo';
				var fee = parseInt($("#fee_raw_registration").val(), 10) || 0;
				var charges = parseInt($("#fee_raw_charges").val(), 10) || 0;
				var platform = parseInt($("#fee_raw_platform").val(), 10) || 0;
				if (method === 'proof') {
					// Proof / bank slip: school registration fee only (no gateway charges)
					$(".fee-row-gateway").hide();
					$("#registration_due").text(fee > 0 ? formatRwf(fee) : '—');
				} else {
					$(".fee-row-gateway").show();
					$("#registration_charges").text(formatRwf(charges));
					$("#registration_platform").text(formatRwf(platform));
					$("#registration_due").text(fee > 0 ? formatRwf(fee + charges + platform) : '—');
				}
			}
			function syncPaymentMethodUI() {
				var method = $("input[name='paymentMethod']:checked").val() || 'momo';
				var bypass = $("#payment_bypass_flag").val() === '1';
				$("#pending").hide();
				if (method === 'proof') {
					$("#payPanelMomo").hide();
					$("#payPanelProof").show();
					$("[name='momoPhoneNumber']").prop('required', false);
					$("#btn-pay").text("Submit with payment proof");
				} else {
					$("#payPanelProof").hide();
					$("#payPanelMomo").show();
					$("[name='momoPhoneNumber']").prop('required', true);
					if (bypass) {
						$("#btn-pay").text("Submit application");
						$("#momo_phone_hint").text("*Contact / MOMO phone for your application");
					} else {
						$("#btn-pay").text("Pay with MOMO");
						$("#momo_phone_hint").text("*Phone that will be charged via MTN MOMO");
					}
				}
				syncFeeSummary();
			}
			$("input[name='paymentMethod']").on('change', syncPaymentMethodUI);
			syncPaymentMethodUI();

			(function bindPaymentProofPreview() {
				var inp = document.getElementById('paymentProof');
				var out = document.getElementById('preview-paymentProof');
				if (!inp) return;
				inp.addEventListener('change', function () {
					if (!this.files || !this.files[0]) { if (out) out.textContent = ''; return; }
					var f = this.files[0];
					var ok = ['application/pdf','image/jpeg','image/png'].indexOf(f.type) !== -1;
					if (!ok) {
						this.value = '';
						if (out) out.innerHTML = '<span style="color:#be2626;">Invalid file type. Use PDF/JPG/PNG.</span>';
						return;
					}
					if (f.size > 5 * 1024 * 1024) {
						this.value = '';
						if (out) out.innerHTML = '<span style="color:#be2626;">File too large. Max 5 MB.</span>';
						return;
					}
					if (out) out.textContent = 'Selected: ' + f.name + ' (' + Math.round(f.size/1024) + ' KB)';
				});
			})();

			$('#autoSave').on('submit', function (e) {
				e.preventDefault();
				var method = $("input[name='paymentMethod']:checked").val() || 'momo';
				if (method === 'proof') {
					var pf = document.getElementById('paymentProof');
					if (!pf || !pf.files || !pf.files[0]) {
						toastada.error("Please attach payment proof");
						return;
					}
				} else {
					var momo = $("[name='momoPhoneNumber']").val();
					if (!momo || String(momo).length < 9) {
						toastada.error("Please enter a valid MOMO phone number");
						return;
					}
				}
				let btn = $(this).find("[type='submit']");
				$(".finalPrev").hide();
				btn.text("Please wait...").prop("disabled", true);
				let formData = new FormData(this);
				formData.set('paymentMethod', method);
				$.ajax({
					type: 'POST',
					url: $(this).attr('action'),
					data: formData,
					cache: false,
					contentType: false,
					processData: false,
					success: function (res) {
						if (res && res.success) {
							$("[name='applicationId']").val(res.applicationId);
							// Payment gateway bypass / proof upload: go straight to finish / code URL
							if ((res.payment_bypass || res.payment_proof) && res.code) {
								toastada.success(res.success || "Application submitted");
								window.location.href = "<?= site_url('application'); ?>/" + res.code;
								return;
							}
							$("#pending").show();
							btn.hide();
							setTimeout(function () {
								$("#loading").hide();
								toastada.error("Payment failed, timeout");
								syncPaymentMethodUI();
								btn.prop("disabled", false).show();
								$("#pending").hide();
								clearInterval(checkInterval);
							}, 1000 * 60 * 5);
							checkInterval = setInterval(function () {
								checkPendingPayment(res.applicationId, btn);
							}, 3000);
						}
						if (res && res.error) {
							toastada.error("Registration failed, " + res.error);
							syncPaymentMethodUI();
							btn.prop("disabled", false).show();
							$("#loading").hide();
						}
					},
					error: function () {
						toastada.error("Payment failed, system error");
						syncPaymentMethodUI();
						btn.prop("disabled", false).show();
						$("#loading").hide();
					}
				});
			});

			// --- Program -> Schools
			$("#schoolProgram").on("change", function () {
				let program = $(this).val();
				let lockedId = parseInt($("#locked_school_id").val(), 10) || 0;
				let options = '<option disabled selected>-- Choose school --</option>';
				if (!lockedId) {
					$("#schoolOptions").html(options);
				}
				var schoolsUrl = "<?= site_url('getSchoolsHavingSelectedProgram'); ?>/" + program;
				if (lockedId > 0) {
					schoolsUrl += "?school=" + lockedId;
				}
				$.getJSON(schoolsUrl, function (data) {
					if (Array.isArray(data)) {
						if (lockedId > 0) {
							var match = null;
							$.each(data, function (i, obj) {
								if (parseInt(obj.id, 10) === lockedId) {
									match = obj;
									return false;
								}
							});
							if (!match) {
								toastada.error("This school has no classes for the selected program");
								$(".registration-data").hide();
								return;
							}
							$("#schoolOptions").html("<option value='" + match.id + "' selected>" + match.name + "</option>");
							$("#schoolOptions").trigger("change");
						} else {
							$.each(data, function (i, obj) {
								options += "<option value='" + obj.id + "'>" + obj.name + "</option>";
							});
							$("#schoolOptions").html(options);
						}
					} else if (data && data.error) {
						toastada.error(data.error);
					}
				}).fail(function(xhr){
					console.error('Schools load failed:', xhr.status, xhr.responseText);
					if (lockedId > 0) {
						toastada.error("This school is not available for the selected program");
					}
				});
			});

			// --- School -> Faculties (+ settings + requirement doc), filtered by REB/RTB program
			$("#schoolOptions").on("change", function () {
				let id = $(this).val();
				let program = $("#schoolProgram").val();
				let options = '<option disabled selected>-- Choose faculty --</option>';
				$("#facultyOptions").html(options);
				$("#departmentOptions").html('<option disabled selected>-- Choose department --</option>');
				$("#levelOptions").html('<option disabled selected>-- Choose Level --</option>');
				if (!program) {
					toastada.error("Please choose school program first");
					return;
				}
				$.getJSON("<?= site_url('getFacultyBySchool'); ?>/" + id + "/" + program, function (data) {
					if (data && data.success) {
						if (data.has_requirement_document && data.requirement_document) {
						$(".requirement-doc").slideDown(300);
						$(".requirement-doc a").prop('href', '<?= base_url("assets/documents/"); ?>' + data.requirement_document);
						} else {
							$(".requirement-doc").hide();
						}
						$("[name='applicationSettings']").val(data.settings_id);
						$("#fee_raw_registration").val(parseInt(data.settings_fees_raw, 10) || 0);
						$("#fee_raw_charges").val(parseInt(data.settings_charges_raw, 10) || 600);
						$("#fee_raw_platform").val(parseInt(data.settings_platform_raw, 10) || 100);
						$("#registration_amount").text(data.settings_fees || '—');
						var bypass = parseInt(data.payment_bypass, 10) === 1;
						$("#payment_bypass_flag").val(bypass ? '1' : '0');
						if (bypass) {
							$("#payment_bypass_note").show();
							$("#payment_momo_ready_note").hide();
						} else {
							$("#payment_bypass_note").hide();
							$("#payment_momo_ready_note").show();
						}
						syncPaymentMethodUI();
						$("#dynamicDocsContainer").html('<p class="text-muted">Choose faculty and level to load required documents.</p>');
						$("#docsHintText").text('Select a faculty and level — required uploads will appear here.');
						$.each(data.faculties, function (i, obj) {
							options += "<option value='" + obj.id + "'>" + obj.name + "</option>";
						});
						$("#facultyOptions").html(options);
						$(".action-button.newApplicant").show();
						$(".registration-data").slideDown(300);
						$(".registration-error").hide();
					} else if (data && data.error) {
						$(".requirement-doc").hide();
						$(".action-button.newApplicant").hide();
						$(".registration-data").hide();
						$(".registration-error").slideDown(300);
						$(".registration-error p").text(data.error);
					}
				}).fail(function(xhr){
					console.error('Faculties load failed:', xhr.status, xhr.responseText);
				});
			});

			function bindDocPreview(inputId) {
				const MAX_SIZE_MB = 5;
				const ACCEPTED = ['application/pdf','image/jpeg','image/png'];
				const inp = document.getElementById(inputId);
				if (!inp) return;
				const out = document.getElementById('preview-' + inputId);
				inp.addEventListener('change', function() {
					if (!this.files || !this.files[0]) { if (out) out.textContent = ''; return; }
					const f = this.files[0];
					if (ACCEPTED.indexOf(f.type) === -1) {
						this.value = '';
						if (out) out.innerHTML = '<span style="color:#be2626;">Invalid file type. Use PDF/JPG/PNG.</span>';
						return;
					}
					if (f.size > MAX_SIZE_MB * 1024 * 1024) {
						this.value = '';
						if (out) out.innerHTML = '<span style="color:#be2626;">File too large. Max '+MAX_SIZE_MB+' MB.</span>';
						return;
					}
					if (out) out.textContent = 'Selected: ' + f.name + ' (' + Math.round(f.size/1024) + ' KB)';
				});
			}

			function renderRequiredDocs(pack) {
				var html = '';
				if (!pack || !pack.docs || !pack.docs.length) {
					$("#dynamicDocsContainer").html('<p class="text-muted">No documents configured for this selection.</p>');
					return;
				}
				$("#docsHintText").text(pack.hint || 'Upload the documents listed below.');
				pack.docs.forEach(function (doc) {
					var star = doc.required ? ' <span class="text-danger">*</span>' : '';
					html += '<div class="form-group" data-doc-field="'+doc.field+'">'
						+ '<label class="control-label mb-1">'+doc.label+star+'</label>'
						+ '<input type="file" name="'+doc.field+'" id="'+doc.field+'" class="form-control app-doc-input"'
						+ ' accept="'+(doc.accept || '.pdf,.jpg,.jpeg,.png')+'"'
						+ (doc.required ? ' required data-required="1"' : ' data-required="0"')
						+ ' data-label="'+String(doc.label).replace(/"/g, '&quot;')+'">'
						+ (doc.hint ? '<small class="text-muted d-block">'+doc.hint+'</small>' : '')
						+ '<small class="text-muted d-block" id="preview-'+doc.field+'" style="margin-top:6px;"></small>'
						+ '</div>';
				});
				$("#dynamicDocsContainer").html(html);
				pack.docs.forEach(function (doc) { bindDocPreview(doc.field); });
			}

			function loadRequiredDocs() {
				var facultyId = $("#facultyOptions").val();
				var levelId = $("#levelOptions").val();
				var schoolId = $("#schoolOptions").val();
				if (!facultyId || !levelId) {
					$("#dynamicDocsContainer").html('<p class="text-muted" id="docsEmptyMsg">Choose school, faculty and level on the Personal step to load the correct document list.</p>');
					return;
				}
				$("#dynamicDocsContainer").html('<p class="text-muted">Loading required documents…</p>');
				$.getJSON("<?= site_url('getApplicationRequiredDocs'); ?>/" + facultyId + "/" + levelId + "/" + (schoolId || 0), function (data) {
					if (data && data.success) {
						renderRequiredDocs(data);
					} else {
						$("#dynamicDocsContainer").html('<p class="text-danger">'+(data && data.error ? data.error : 'Could not load documents')+'</p>');
					}
				}).fail(function () {
					$("#dynamicDocsContainer").html('<p class="text-danger">Could not load required documents. Please try again.</p>');
				});
			}

			// --- Faculty -> Departments
			$("#facultyOptions").on("change", function () {
				let id = $(this).val();
				let school_id = $("#schoolOptions").val();
				let options = '<option disabled selected>-- Choose department --</option>';
				$("#departmentOptions").html(options);
				$("#levelOptions").html('<option disabled selected>-- Choose Level --</option>');
				$("#dynamicDocsContainer").html('<p class="text-muted">Choose a level to load required documents.</p>');
				$.getJSON("<?= site_url('getDepartmentBySchool'); ?>/" + id + "/" + school_id, function (data) {
					if (Array.isArray(data)) {
						$.each(data, function (i, obj) {
							options += "<option value='" + obj.id + "'>" + obj.name + "</option>";
						});
						$("#departmentOptions").html(options);
					}
				}).fail(function(xhr){
					console.error('Departments load failed:', xhr.status, xhr.responseText);
				});
			});

			// --- Department -> Levels
			$("#departmentOptions").on("change", function () {
				let id = $("#facultyOptions").val();
				let programId = $("#schoolProgram").val();
				let options = '<option disabled selected>-- Choose level --</option>';
				$("#levelOptions").html(options);
				$("#dynamicDocsContainer").html('<p class="text-muted">Choose a level to load required documents.</p>');
				$.getJSON("<?= site_url('getLevelByFaculty'); ?>/" + id + "/" + programId, function (data) {
					if (Array.isArray(data)) {
						$.each(data, function (i, obj) {
							options += "<option value='" + obj.id + "'>" + obj.name + "</option>";
						});
						$("#levelOptions").html(options);
					}
				}).fail(function(xhr){
					console.error('Levels load failed:', xhr.status, xhr.responseText);
				});
			});

			$("#levelOptions").on("change", function () {
				loadRequiredDocs();
			});

			$('#confirmBox').click(function () {
				if ($(this).is(":checked")) {
					$("#currentApplicant").show();
					$(".newApplicant").hide();
				} else {
					$(".newApplicant").show();
					$("#currentApplicant").hide();
				}
			});

			let current_fs, next_fs, previous_fs;
			let opacity;
			let current = 1;
			let steps = $("fieldset").length;

			function setProgressBar(curStep) {
				var percent = parseFloat(100 / steps) * curStep;
				percent = percent.toFixed();
				$(".progress-bar").css("width", percent + "%");
			}
			setProgressBar(current);

			$("#searchBtn").click(function () {
				const code = $('input[name=code]').val();
				if (code.length > 5)
					window.location.href = "<?= base_url('application/'); ?>" + encodeURIComponent(code);
				else
					toastada.error("Invalid registration code");
			});

			$(document).on('submit', '#completeForm', function (event) {
				current_fs = $(this).parent().parent();
				next_fs = current_fs.next();
				event.preventDefault();
				$.ajax({
					url: "<?= site_url('completeStudentApplication'); ?>",
					method: 'POST',
					data: new FormData(this),
					contentType: false,
					processData: false,
					cache: false,
					async: false,
					success: function (data) {
						var json = null;
						try {
							json = JSON.parse(data);
							if (json.error) {
								console.log("json.error");
							} else {
								alert("json.success");
								$('#completeForm')[0].reset();
								$("#progressbar li").eq($("fieldset").index(next_fs)).addClass("active");
								next_fs.show();
								current_fs.animate({opacity: 0}, {
									step: function (now) {
										opacity = 1 - now;
										current_fs.css({ 'display': 'none', 'position': 'relative' });
										next_fs.css({'opacity': opacity});
									},
									duration: 500
								});
								setProgressBar(4);
							}
						} catch (e) { console.log(e); }
					}
				});
			});

			$(".next").click(function () {
				current_fs = $(this).parent();
				next_fs = $(this).parent().next();
				window.scrollTo(0, 0);
				if (current == 1) {
					var names = $('input[name=studentNames]').val();
					var gender = $('select[name=gender]').val();
					var phone = $('input[name=phoneNumber]').val();
					var parentPhone = $('input[name=parentPhoneNumber]').val();
					var level = $('select[name=level]').val();
					const data = { names, gender, phone, parentPhone, level };
					localStorage.setItem('data', JSON.stringify(data));
					if (!$("#levelOptions").val()) {
						toastada.error("Please select a level first");
						return false;
					}
					loadRequiredDocs();
				}
				// Documents step validation (dynamic fields)
				if (current == 3) {
					var missing = null;
					$("#dynamicDocsContainer .app-doc-input").each(function () {
						if ($(this).attr('data-required') === '1') {
							if (!this.files || !this.files[0]) {
								missing = $(this).attr('data-label') || this.name;
								return false;
							}
						}
					});
					if (missing) {
						toastada.error("Please upload: " + missing);
						return false;
					}
					if (!$("#dynamicDocsContainer .app-doc-input").length) {
						toastada.error("Please select faculty and level so required documents can load");
						return false;
					}
				}
				$("#progressbar li").eq($("fieldset").index(next_fs)).addClass("active");
				next_fs.show();
				current_fs.animate({opacity: 0}, {
					step: function (now) {
						opacity = 1 - now;
						current_fs.css({ 'display': 'none', 'position': 'relative' });
						next_fs.css({'opacity': opacity});
					},
					duration: 500
				});
				setProgressBar(++current);
			});

			$(".previous").click(function () {
				current_fs = $(this).parent();
				previous_fs = $(this).parent().prev();
				$("#progressbar li").eq($("fieldset").index(current_fs)).removeClass("active");
				previous_fs.show();
				current_fs.animate({opacity: 0}, {
					step: function (now) {
						opacity = 1 - now;
						current_fs.css({ 'display': 'none', 'position': 'relative' });
						previous_fs.css({'opacity': opacity});
					},
					duration: 500
				});
				setProgressBar(--current);
			});

			$(".submit").click(function () { return false; });
		});

		function checkPendingPayment(applicationId, btn) {
			$.getJSON('<?= site_url('get_registration_status'); ?>', 'applicationId=' + applicationId, function (data) {
				if (data && data.success) {
					clearInterval(checkInterval);
					window.location.href = "<?= base_url('application/'); ?>" + data.code;
				}
				if (data && data.error) {
					$("#loading").hide();
					toastada.error("Payment failed, timeout");
					btn.text("Pay").prop("disabled", false).show();
					$("#pending").hide();
					clearInterval(checkInterval);
					toastada.error(data.error);
				}
			});
		}

		function agreeTerms(){
			$("#btn-pay").prop("disabled", !$("#exampleCheck1").is(":checked"));
		}
	</script>
</section>
