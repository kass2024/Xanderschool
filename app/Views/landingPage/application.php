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
		max-width: min(1100px, 100%);
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
	#msform form > fieldset:not(:first-of-type) { display: none; }
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
	#progressbar #family:before,
	#progressbar #personal:before,
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

	.ss-visitor-grid {
		display: grid;
		grid-template-columns: 1fr 1fr;
		gap: 1rem;
		margin-bottom: 1rem;
	}
	@media (max-width: 768px) {
		.ss-visitor-grid { grid-template-columns: 1fr; }
	}
	.ss-visitor-card {
		background: var(--app-paper);
		border: 1px solid var(--app-line);
		border-radius: 14px;
		padding: 1rem 1.1rem;
	}
	.ss-visitor-card h6 {
		font-size: .95rem;
		font-weight: 700;
		color: var(--app-navy);
		margin: 0 0 .75rem;
	}
	.ss-visitor-card .badge-req {
		font-size: .68rem;
		background: #dbeafe;
		color: #1d4ed8;
		padding: 2px 8px;
		border-radius: 999px;
		margin-left: 6px;
		vertical-align: middle;
	}
	.ss-form-row {
		display: grid;
		grid-template-columns: 1fr 1fr;
		gap: .85rem 1rem;
	}
	@media (max-width: 768px) {
		.ss-form-row { grid-template-columns: 1fr; gap: .65rem; }
	}
	.ss-section-title {
		font-size: .95rem;
		font-weight: 700;
		color: var(--app-navy);
		margin: 1rem 0 .65rem;
		padding-top: .5rem;
		border-top: 1px solid var(--app-line);
	}
	.ss-section-title:first-child { border-top: 0; padding-top: 0; margin-top: 0; }
	.ss-panel {
		background: linear-gradient(180deg, #fff 0%, #fafbfc 100%);
		border: 1px solid var(--app-line);
		border-radius: 16px;
		padding: 1rem 1.1rem 1.15rem;
		margin-bottom: 1rem;
		box-shadow: 0 6px 20px rgba(15, 23, 42, .05);
	}
	.ss-panel-head {
		display: flex;
		align-items: center;
		gap: .55rem;
		font-size: .98rem;
		font-weight: 700;
		color: var(--app-navy);
		margin: 0 0 .9rem;
		padding-bottom: .7rem;
		border-bottom: 1px solid var(--app-line);
	}
	.ss-panel-head i {
		color: var(--app-cyan);
		font-size: 1.05rem;
		width: 1.35rem;
		text-align: center;
		flex-shrink: 0;
	}
	.ss-panel .form-group:last-child { margin-bottom: 0; }
	.ss-nationality-wrap {
		display: flex;
		flex-direction: column;
		gap: .5rem;
	}
	.ss-search-field {
		position: relative;
	}
	.ss-search-field i {
		position: absolute;
		left: .85rem;
		top: 50%;
		transform: translateY(-50%);
		color: #94a3b8;
		pointer-events: none;
		font-size: .85rem;
	}
	.ss-search-field input { padding-left: 2.35rem !important; }
	.ss-app .registration-data .form-group { margin-bottom: .85rem; }
	.ss-app .registration-data.is-visible { display: block !important; }
	@media (max-width: 768px) {
		.ss-app {
			padding: calc(64px + .85rem) .65rem 2.5rem;
		}
		.ss-app .card {
			padding: 1.15rem 1rem 1.35rem;
			border-radius: 14px;
		}
		.ss-app .fs-title { font-size: 1.35rem; }
		#msform .form-control,
		#msform input[type="text"],
		#msform input[type="email"],
		#msform input[type="number"],
		#msform input[type="tel"],
		#msform input[type="date"],
		#msform select,
		#msform textarea {
			font-size: 16px;
			min-height: 48px;
			padding: .72rem 1rem;
		}
		#progressbar { margin-bottom: 1.25rem; gap: .15rem; }
		#progressbar li { font-size: .62rem; }
		#progressbar li strong { font-size: .62rem; line-height: 1.2; }
		#progressbar li:before {
			width: 34px;
			height: 34px;
			line-height: 34px;
			font-size: .72rem;
			box-shadow: 0 0 0 3px #fff;
		}
		#progressbar li:after { top: 16px; height: 2px; }
		.ss-app .fieldset-actions {
			position: sticky;
			bottom: 0;
			left: 0;
			right: 0;
			background: linear-gradient(180deg, rgba(255,255,255,.92) 0%, #fff 35%);
			padding: .85rem 0 .35rem;
			margin-top: .75rem;
			border-top: 1px solid var(--app-line);
			z-index: 20;
			backdrop-filter: blur(6px);
		}
		#msform .action-button,
		#msform .action-button-previous,
		#msform #btn-pay {
			flex: 1 1 auto;
			min-width: 0;
			margin: 0;
			min-height: 48px;
		}
		.ss-panel { padding: .95rem .95rem 1rem; border-radius: 14px; }
		.ss-app .form-check { padding: .75rem .85rem; }
	}
	@media (max-width: 420px) {
		#progressbar li strong { display: none; }
		#progressbar li:before { margin-bottom: 0; }
	}
	.ss-hint-box {
		background: #f0f9ff;
		border: 1px solid #bae6fd;
		border-radius: 10px;
		padding: .65rem .85rem;
		font-size: .82rem;
		color: #0369a1;
		margin-bottom: 1rem;
	}
	.ss-panel-loading {
		position: relative;
		min-height: 80px;
	}
	.ss-panel-loading::after {
		content: '';
		position: absolute;
		inset: 0;
		background: rgba(255, 255, 255, .78);
		border-radius: inherit;
		z-index: 2;
	}
	.ss-panel-loading::before {
		content: 'Loading…';
		position: absolute;
		top: 50%;
		left: 50%;
		transform: translate(-50%, -50%);
		z-index: 3;
		font-size: .85rem;
		font-weight: 600;
		color: var(--app-cyan-d);
		white-space: nowrap;
	}
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
								<li id="family" class="<?= isset($applicationId) ? 'active' : ''; ?>"><strong>Family</strong></li>
								<li id="documents" class="<?= isset($applicationId) ? 'active' : ''; ?>"><strong>Documents</strong></li>
								<li id="confirm" class="<?= isset($applicationId) ? 'active' : ''; ?>"><strong>Finish</strong></li>
							</ul>

							<!-- fieldsets -->
							<?php if (!isset($applicationId)) : ?>
								<form method="post" id="autoSave"
									  action="<?= site_url('manipulateStudentSelfRegistration'); ?>"
									  class="validates" enctype="multipart/form-data">
									<input type="hidden" name="religion" value="Not specified">
									<input type="hidden" name="applicationId" value="">
									<input type="hidden" name="applicationSettings" value="">
									<input type="hidden" name="paymentMethod" value="deferred">
									<input type="hidden" id="registration_fee_mode" value="flat">
									<!-- STEP 1: PERSONAL -->
									<fieldset>
										<div class="form-card ">
											<div class="row">
												<div class="col-7">
													<h2 class="fs-title">Student self application</h2>
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
															<div class="ss-panel" id="classSchoolPanel">
																<div class="ss-panel-head"><i class="fa fa-graduation-cap"></i> Class &amp; school</div>
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
																	<label for="classOptions" class="control-label mb-1">Class</label>
																	<select class="form-control" name="class_id" required id="classOptions">
																		<option disabled selected value="">— Choose department first —</option>
																	</select>
																	<input type="hidden" name="level" id="levelHidden" value="">
																</div>
															</div>

															<div class="ss-panel">
																<div class="ss-panel-head"><i class="fa fa-user"></i> Student details</div>
																<div class="ss-form-row">
																	<div class="form-group">
																		<label class="control-label mb-1">First name</label>
																		<input required name="firstName" type="text" class="form-control" placeholder="First name" autocomplete="given-name">
																	</div>
																	<div class="form-group">
																		<label class="control-label mb-1">Last name</label>
																		<input required name="lastName" type="text" class="form-control" placeholder="Last name" autocomplete="family-name">
																	</div>
																</div>
																<div class="ss-hint-box">
																	<strong>Reg No</strong> is generated automatically when the school admits the student (same as Excel mass upload).
																</div>
																<div class="ss-form-row">
																	<div class="form-group">
																		<label class="control-label mb-1">Gender</label>
																		<select class="form-control" name="gender" required>
																			<option disabled selected value="">— Choose gender —</option>
																			<option value="M">Male</option>
																			<option value="F">Female</option>
																		</select>
																	</div>
																	<div class="form-group">
																		<label class="control-label mb-1">Birth date</label>
																		<input name="dateOfBirth" type="date" class="form-control" required autocomplete="bday">
																	</div>
																</div>
																<div class="ss-form-row">
																	<div class="form-group has-success">
																		<label class="control-label mb-1">Studying mode</label>
																		<select class="form-control" name="studingMode" required>
																			<option disabled selected value="">— Choose mode —</option>
																			<option value="0">Boarding</option>
																			<option value="1">Day</option>
																		</select>
																	</div>
																	<div class="form-group has-success">
																		<label class="control-label mb-1">Student phone</label>
																		<input id="studentPhone" name="phoneNumber" placeholder="Parent phone if none" type="tel" inputmode="tel" required class="form-control" autocomplete="tel">
																	</div>
																</div>
																<div class="form-group">
																	<label class="control-label mb-1">Nationality</label>
																	<div class="ss-nationality-wrap">
																		<div class="ss-search-field">
																			<i class="fa fa-search"></i>
																			<input type="text" id="nationalitySearch" class="form-control" placeholder="Search country…" autocomplete="off">
																		</div>
																		<select class="form-control" name="nationality" id="nationalitySelect" required>
																			<option value="" disabled selected>— Choose country —</option>
																			<?php
																			helper('form_options');
																			foreach (form_country_options() as $country):
																			?>
																				<option value="<?= esc($country) ?>"><?= esc($country) ?></option>
																			<?php endforeach; ?>
																		</select>
																	</div>
																</div>
															</div>

															<div class="ss-panel">
																<div class="ss-panel-head"><i class="fa fa-heartbeat"></i> Medical information</div>
																<div class="ss-form-row">
																	<div class="form-group">
																		<label class="control-label mb-1">Medical status</label>
																		<select class="form-control" name="medical_status" id="medicalStatus">
																			<option value="Normal" selected>Normal</option>
																			<option value="Asthma">Asthma</option>
																			<option value="Diabetes">Diabetes</option>
																			<option value="Epilepsy">Epilepsy</option>
																			<option value="Allergies">Allergies</option>
																			<option value="Other">Other condition</option>
																		</select>
																	</div>
																	<div class="form-group" id="medicalDetailWrap" style="display:none;">
																		<label class="control-label mb-1">Describe condition</label>
																		<input type="text" class="form-control" name="medical_detail" id="medicalDetail" placeholder="Brief description">
																	</div>
																</div>
															</div>

															<div class="ss-panel">
																<div class="ss-panel-head"><i class="fa fa-map-marker"></i> Home location</div>
																<div class="ss-form-row">
																	<div class="form-group">
																		<label class="control-label mb-1">Province</label>
																		<select class="form-control address_select" data-target="district" name="province" required>
																			<option value="" disabled selected>Select province</option>
																			<?php foreach (($provinces ?? []) as $province): ?>
																			<option value="<?= (int) $province['id']; ?>"><?= esc($province['title']); ?></option>
																			<?php endforeach; ?>
																		</select>
																	</div>
																	<div class="form-group">
																		<label class="control-label mb-1">District</label>
																		<select class="form-control address_select" data-target="sector" name="district" required>
																			<option value="" disabled selected>Select district</option>
																		</select>
																	</div>
																</div>
																<div class="ss-form-row">
																	<div class="form-group">
																		<label class="control-label mb-1">Sector</label>
																		<select class="form-control address_select" data-target="cell" name="sector" required>
																			<option value="" disabled selected>Select sector</option>
																		</select>
																	</div>
																	<div class="form-group">
																		<label class="control-label mb-1">Cell</label>
																		<select class="form-control address_select" data-target="village" name="cell" id="cellSelect" required>
																			<option value="" disabled selected>Select cell</option>
																		</select>
																	</div>
																</div>
																<div class="ss-form-row">
																	<div class="form-group">
																		<label class="control-label mb-1">Village</label>
																		<select class="form-control" name="village" id="villageSelect" required>
																			<option value="" disabled selected>Select village</option>
																		</select>
																	</div>
																</div>
															</div>
														</div><!-- /.registration-data -->
											</div><!-- /.newApplicant -->
										</div><!-- /.form-card -->
										<div class="fieldset-actions">
										<input type="button" name="next" class="next action-button newApplicant" value="Next" style="display:none;"/>
										</div>
									</fieldset>

									<!-- STEP 2: FAMILY + VISITORS (matches Excel template) -->
									<fieldset>
										<div class="form-card">
											<h6 class="fs-title" style="font-size:1.15rem;margin-bottom:.35rem;">Family &amp; visitors</h6>
											<p class="text-muted" style="font-size:.9rem;margin-bottom:1rem;">
												Same fields as the student Excel template — used for student record, parent visiting &amp; RFID cards.
											</p>

											<div class="ss-panel">
												<div class="ss-panel-head"><i class="fa fa-male"></i> Father</div>
												<div class="ss-form-row">
													<div class="form-group">
														<label class="control-label mb-1">Father names</label>
														<input name="father" id="father" type="text" class="form-control" placeholder="Father full names">
													</div>
													<div class="form-group">
														<label class="control-label mb-1">Father phone</label>
														<input name="ft_phone" id="ft_phone" type="tel" inputmode="tel" class="form-control" placeholder="0780000000">
													</div>
												</div>
											</div>

											<div class="ss-panel">
												<div class="ss-panel-head"><i class="fa fa-female"></i> Mother</div>
												<div class="ss-form-row">
													<div class="form-group">
														<label class="control-label mb-1">Mother names</label>
														<input name="mother" id="mother" type="text" class="form-control" placeholder="Mother full names">
													</div>
													<div class="form-group">
														<label class="control-label mb-1">Mother phone</label>
														<input name="mt_phone" id="mt_phone" type="tel" inputmode="tel" class="form-control" placeholder="0780000000">
													</div>
												</div>
											</div>

											<div class="ss-panel">
												<div class="ss-panel-head"><i class="fa fa-users"></i> Guardian</div>
												<div class="ss-form-row">
													<div class="form-group">
														<label class="control-label mb-1">Guardian names</label>
														<input name="guardian" id="guardian" type="text" class="form-control" placeholder="Guardian full names">
													</div>
													<div class="form-group">
														<label class="control-label mb-1">Guardian phone</label>
														<input name="gd_phone" id="gd_phone" type="tel" inputmode="tel" class="form-control" placeholder="0780000000">
													</div>
												</div>
											</div>

											<div class="ss-panel">
												<div class="ss-panel-head"><i class="fa fa-id-badge"></i> Visitors <span class="badge-req">Required for visiting</span></div>
												<div class="ss-visitor-grid">
													<div class="ss-visitor-card">
														<h6>Visitor 1</h6>
														<div class="form-group">
															<label class="control-label mb-1">Visitor 1 name</label>
															<input name="visitor1Names" id="visitor1Names" type="text" class="form-control" placeholder="Same as father/mother if applicable">
														</div>
														<div class="form-group">
															<label class="control-label mb-1">Visitor 1 phone</label>
															<input name="visitor1Phone" id="visitor1Phone" type="tel" inputmode="tel" class="form-control" placeholder="0780000000">
														</div>
														<div class="form-group">
															<label class="control-label mb-1">Visitor 1 relationship</label>
															<select class="form-control" name="visitor1Relationship" id="visitor1Relationship">
																<option value="">— Choose relationship —</option>
																<option value="Father">Father</option>
																<option value="Mother">Mother</option>
																<option value="Guardian">Guardian</option>
																<option value="Sibling">Sibling</option>
																<option value="Relative">Relative</option>
																<option value="Other">Other</option>
															</select>
														</div>
													</div>
													<div class="ss-visitor-card">
														<h6>Visitor 2</h6>
														<div class="form-group">
															<label class="control-label mb-1">Visitor 2 name</label>
															<input name="visitor2Names" id="visitor2Names" type="text" class="form-control" placeholder="Second visitor">
														</div>
														<div class="form-group">
															<label class="control-label mb-1">Visitor 2 phone</label>
															<input name="visitor2Phone" id="visitor2Phone" type="tel" inputmode="tel" class="form-control" placeholder="0780000000">
														</div>
														<div class="form-group">
															<label class="control-label mb-1">Visitor 2 relationship</label>
															<select class="form-control" name="visitor2Relationship" id="visitor2Relationship">
																<option value="">— Choose relationship —</option>
																<option value="Father">Father</option>
																<option value="Mother">Mother</option>
																<option value="Guardian">Guardian</option>
																<option value="Sibling">Sibling</option>
																<option value="Relative">Relative</option>
																<option value="Other">Other</option>
															</select>
														</div>
													</div>
												</div>
											</div>

											<div class="ss-panel">
												<div class="ss-panel-head"><i class="fa fa-envelope"></i> Contact</div>
												<input type="hidden" name="parentNames" id="parentNamesHidden" value="">
												<input type="hidden" name="parentPhone" id="parentPhoneHidden" value="">
												<input type="hidden" name="relationship" id="relationshipHidden" value="1">
												<div class="form-group" style="margin-bottom:0;">
													<label class="control-label mb-1">Contact email (optional)</label>
													<input name="email" type="email" class="form-control" placeholder="parent@email.com" autocomplete="email">
												</div>
											</div>
										</div>
										<div class="fieldset-actions">
										<input type="button" name="next" class="next action-button" value="Next"/>
										<input type="button" name="previous" class="previous action-button-previous" value="Previous"/>
										</div>
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

											<div class="ss-hint-box">
												Registration fee is collected when the school <strong>approves</strong> your application. You can submit now without paying.
											</div>
											<div class="form-group">
												<div class="form-check">
													<input type="checkbox" class="form-check-input" name="confirm" id="exampleCheck1" onchange="agreeTerms()">
													<label class="form-check-label" for="exampleCheck1">I agree to the <a href="#">terms &amp; conditions</a></label>
												</div>
											</div>
										</div>
										<div class="fieldset-actions">
										<input type="button" name="previous" class="previous action-button-previous" value="Previous"/>
										<button type="submit" class="action-button" id="btn-pay" disabled>Submit application</button>
										</div>
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
		function ssBootApplication($) {
			if (window.__ssAppBooted) return;
			window.__ssAppBooted = true;
			(function initNationalitySearch() {
				var $search = $('#nationalitySearch');
				var $select = $('#nationalitySelect');
				if (!$search.length || !$select.length) return;
				var $opts = $select.find('option').not('[value=""]');
				$search.on('input', function () {
					var q = $(this).val().toLowerCase().trim();
					var first = null;
					$opts.each(function () {
						var match = q === '' || $(this).text().toLowerCase().indexOf(q) >= 0;
						$(this).prop('hidden', !match);
						if (match && first === null) first = $(this).val();
					});
					if (q !== '' && first) {
						$select.val(first);
					}
				});
				$select.on('change', function () {
					var txt = $select.find('option:selected').text();
					if (txt && txt.indexOf('—') !== 0) {
						$search.val(txt);
					}
				});
			})();

			function getSchoolIdForReg() {
				var locked = parseInt($("#locked_school_id").val(), 10) || 0;
				if (locked > 0) return locked;
				return parseInt($("#schoolOptions").val(), 10) || 0;
			}

			function missingRequiredDocs() {
				var missing = null;
				var $inputs = $("#dynamicDocsContainer .app-doc-input");
				$inputs.each(function () {
					if ($(this).attr('data-required') === '1') {
						if (!this.files || !this.files[0]) {
							missing = $(this).attr('data-label') || this.name;
							return false;
						}
					}
				});
				return missing;
			}

			$('#autoSave').on('submit', function (e) {
				e.preventDefault();
				var father = ($('#father').val() || '').trim();
				var ftPhone = ($('#ft_phone').val() || '').trim();
				var mother = ($('#mother').val() || '').trim();
				var mtPhone = ($('#mt_phone').val() || '').trim();
				if (!$('#visitor1Names').val().trim() && father) {
					$('#visitor1Names').val(father);
					if (!$('#visitor1Phone').val().trim()) $('#visitor1Phone').val(ftPhone);
					if (!$('#visitor1Relationship').val()) $('#visitor1Relationship').val('Father');
				}
				if (!$('#visitor2Names').val().trim() && mother) {
					$('#visitor2Names').val(mother);
					if (!$('#visitor2Phone').val().trim()) $('#visitor2Phone').val(mtPhone);
					if (!$('#visitor2Relationship').val()) $('#visitor2Relationship').val('Mother');
				}
				var v1n = ($('#visitor1Names').val() || '').trim();
				var v1p = ($('#visitor1Phone').val() || '').trim();
				var v1r = ($('#visitor1Relationship').val() || 'Father');
				var hasFamily = (father && ftPhone) || (mother && mtPhone) || (($('#guardian').val() || '').trim() && ($('#gd_phone').val() || '').trim());
				if ((!v1n || !v1p) && !hasFamily) {
					toastada.error('Fill Visitor 1 or at least one family contact (name + phone).');
					return;
				}
				var primaryName = v1n || father || mother || ($('#guardian').val() || '').trim();
				var primaryPhone = v1p || ftPhone || mtPhone || ($('#gd_phone').val() || '').trim();
				$('#parentNamesHidden').val(primaryName);
				$('#parentPhoneHidden').val(primaryPhone);
				var relMap = { 'Father': '1', 'Mother': '2', 'Guardian': '3', 'Sibling': '3', 'Relative': '3', 'Other': '3' };
				$('#relationshipHidden').val(relMap[v1r] || (mother && !father ? '2' : '1'));
				var missingDoc = missingRequiredDocs();
				if (missingDoc) {
					toastada.error("Please upload: " + missingDoc);
					return;
				}
				if (!$("#exampleCheck1").is(":checked")) {
					toastada.error("Please agree to the terms & conditions");
					return;
				}
				var btn = $(this).find("[type='submit']");
				btn.text("Please wait...").prop("disabled", true);
				var formData = new FormData(this);
				formData.set('paymentMethod', 'deferred');
				formData.set('school', String(getSchoolIdForReg()));
				$.ajax({
					type: 'POST',
					url: $(this).attr('action'),
					data: formData,
					cache: false,
					contentType: false,
					processData: false,
					success: function (res) {
						if (res && res.success && res.code) {
							toastada.success(res.success || "Application submitted");
							window.location.href = "<?= site_url('application'); ?>/" + res.code;
							return;
						}
						if (res && res.error) {
							toastada.error("Registration failed, " + res.error);
							btn.prop("disabled", false).text("Submit application");
						}
					},
					error: function () {
						toastada.error("Could not submit application. Please try again.");
						btn.prop("disabled", false).text("Submit application");
					}
				});
			});

			function notifyError(msg) {
				if (window.toastada && toastada.error) {
					toastada.error(msg);
				} else {
					alert(msg);
				}
			}

			function showRegistrationForm() {
				$(".registration-data").stop(true, true).addClass("is-visible").css("display", "block");
				$(".action-button.newApplicant").css("display", "inline-flex");
			}

			function showRegistrationError(msg) {
				$(".registration-error").show();
				$(".registration-error p").text(msg || "Could not load classes for this school.");
			}

			function setClassPanelLoading(loading) {
				var $panel = $("#classSchoolPanel");
				if (loading) {
					$panel.addClass("ss-panel-loading");
				} else {
					$panel.removeClass("ss-panel-loading");
				}
				$("#facultyOptions, #departmentOptions, #classOptions").prop("disabled", !!loading);
			}

			function resetClassSchoolFields(message) {
				var facultyMsg = message || "-- Choose faculty --";
				$("#facultyOptions").html("<option disabled selected>" + facultyMsg + "</option>");
				$("#departmentOptions").html('<option disabled selected>-- Choose department --</option>');
				$("#classOptions").html('<option disabled selected value="">— Choose department first —</option>');
				$("#levelHidden").val("");
			}

			function applyFacultyResponse(data) {
				setClassPanelLoading(false);
				if (data && data.success) {
					if (data.has_requirement_document && data.requirement_document) {
						$(".requirement-doc").show();
						$(".requirement-doc a").prop("href", "<?= base_url('assets/documents/'); ?>" + data.requirement_document);
					} else {
						$(".requirement-doc").hide();
					}
					$("[name='applicationSettings']").val(data.settings_id);
					$("#dynamicDocsContainer").html("<p class=\"text-muted\">Choose faculty and class to load required documents.</p>");
					$("#docsHintText").text("Select a faculty and class — required uploads will appear here.");
					var options = "<option disabled selected>-- Choose faculty --</option>";
					$.each(data.faculties || [], function (i, obj) {
						options += "<option value='" + obj.id + "'>" + obj.name + "</option>";
					});
					$("#facultyOptions").html(options);
					showRegistrationForm();
					$("#registration_fee_mode").val(data.fee_mode || "flat");
				} else if (data && data.error) {
					$(".requirement-doc").hide();
					showRegistrationForm();
					showRegistrationError(data.error);
					notifyError(data.error);
				}
			}

			function facultyApi(schoolId, program) {
				return "<?= site_url('getFacultyBySchool'); ?>/" + schoolId + "/" + program;
			}

			function loadRegistrationForSchool(schoolId, program) {
				schoolId = parseInt(schoolId, 10) || 0;
				program = parseInt(program, 10) || 0;
				if (!schoolId || !program) return;
				setClassPanelLoading(true);
				$(".registration-error").hide();
				showRegistrationForm();
				function applyOrFallback(data) {
					if (data && data.success && data.faculties && data.faculties.length) {
						applyFacultyResponse(data);
						return;
					}
					$.getJSON(facultyApi(schoolId, 0), function (allData) {
						if (allData && allData.success && allData.faculties && allData.faculties.length) {
							applyFacultyResponse(allData);
							return;
						}
						applyFacultyResponse(data || allData || { error: "No classes found for this school program" });
					}).fail(function () {
						applyFacultyResponse(data || { error: "Could not load classes for this school. Please try again." });
					});
				}
				$.getJSON(facultyApi(schoolId, program), applyOrFallback).fail(function (xhr) {
					console.error("Faculties load failed:", xhr.status, xhr.responseText);
					$.getJSON(facultyApi(schoolId, 0), applyOrFallback).fail(function () {
						setClassPanelLoading(false);
						showRegistrationForm();
						showRegistrationError("Could not load classes for this school. Please try again.");
						notifyError("Could not load classes for this school. Please try again.");
					});
				});
			}

			// --- Program -> Schools (show form immediately; load classes in parallel)
			$(document).on("change", "#schoolProgram", function () {
				let program = $(this).val();
				let lockedId = parseInt($("#locked_school_id").val(), 10) || 0;
				resetClassSchoolFields();
				$(".registration-error").hide();

				if (!program) {
					$(".registration-data").removeClass("is-visible").hide();
					$(".action-button.newApplicant").hide();
					return;
				}

				showRegistrationForm();

				if (lockedId > 0) {
					loadRegistrationForSchool(lockedId, program);
					return;
				}

				let options = "<option disabled selected>-- Choose school --</option>";
				$("#schoolOptions").html(options);
				resetClassSchoolFields("— Select school first —");
				setClassPanelLoading(true);

				$.getJSON("<?= site_url('getSchoolsHavingSelectedProgram'); ?>/" + program, function (data) {
					setClassPanelLoading(false);
					if (Array.isArray(data)) {
						$.each(data, function (i, obj) {
							options += "<option value='" + obj.id + "'>" + obj.name + "</option>";
						});
						$("#schoolOptions").html(options);
					} else if (data && data.error) {
						toastada.error(data.error);
					}
				}).fail(function (xhr) {
					setClassPanelLoading(false);
					console.error("Schools load failed:", xhr.status, xhr.responseText);
					toastada.error("Could not load schools for this program.");
				});
			});

			$("[name='studingMode']").on("change", function () {
				refreshRegistrationFee();
			});

			$("#medicalStatus").on("change", function () {
				var v = $(this).val();
				if (v && v !== 'Normal') {
					$("#medicalDetailWrap").slideDown(200);
				} else {
					$("#medicalDetailWrap").hide();
					$("#medicalDetail").val('');
				}
			});

			$(".address_select").on("change", function () {
				var target = $(this).data("target");
				var val = $(this).val();
				if (!target || !val) return;
				$.get("<?= site_url('get_address'); ?>/" + target, { key: $(this).attr('name'), val: val }, function (html) {
					$("[name='" + target + "']").html(html);
					if (target === 'district') {
						$("[name='sector']").html('<option value="" disabled selected>Select sector</option>');
						$("#cellSelect").html('<option value="" disabled selected>Select cell</option>');
						$("#villageSelect").html('<option value="" disabled selected>Select village</option>');
					}
					if (target === 'sector') {
						$("#cellSelect").html('<option value="" disabled selected>Select cell</option>');
						$("#villageSelect").html('<option value="" disabled selected>Select village</option>');
					}
					if (target === 'cell') {
						$("#villageSelect").html('<option value="" disabled selected>Select village</option>');
					}
				});
			});

			function refreshRegistrationFee() {
				return;
			}

			$("#schoolOptions").on("change", function () {
				let id = $(this).val();
				let program = $("#schoolProgram").val();
				if (!program) {
					toastada.error("Please choose school program first");
					return;
				}
				resetClassSchoolFields();
				loadRegistrationForSchool(id, program);
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
				var levelId = $("#levelHidden").val();
				var schoolId = getSchoolIdForReg();
				if (!facultyId || !levelId) {
					$("#dynamicDocsContainer").html('<p class="text-muted" id="docsEmptyMsg">Choose school, faculty and class on the Personal step to load the correct document list.</p>');
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
				let school_id = getSchoolIdForReg();
				let options = '<option disabled selected>-- Choose department --</option>';
				$("#departmentOptions").html(options);
				$("#classOptions").html('<option disabled selected value="">— Choose department first —</option>');
				$("#levelHidden").val('');
				$("#dynamicDocsContainer").html('<p class="text-muted">Choose a class to load required documents.</p>');
				if (!id || !school_id) {
					notifyError("Please choose school program first so faculty can load.");
					return;
				}
				$.getJSON("<?= site_url('getDepartmentBySchool'); ?>/" + id + "/" + school_id, function (data) {
					if (Array.isArray(data) && data.length) {
						$.each(data, function (i, obj) {
							options += "<option value='" + obj.id + "'>" + obj.name + "</option>";
						});
						$("#departmentOptions").html(options);
					} else if (data && data.error) {
						notifyError(data.error);
					} else {
						notifyError("No departments found for this faculty.");
					}
				}).fail(function(xhr){
					console.error('Departments load failed:', xhr.status, xhr.responseText);
					notifyError("Could not load departments. Please try again.");
				});
			});

			// --- Department -> Classes (school-specific)
			$("#departmentOptions").on("change", function () {
				let deptId = $(this).val();
				let school_id = getSchoolIdForReg();
				let options = '<option disabled selected value="">— Select class —</option>';
				$("#classOptions").html(options);
				$("#levelHidden").val('');
				$("#dynamicDocsContainer").html('<p class="text-muted">Choose a class to load required documents.</p>');
				if (!deptId || !school_id) return;
				$.getJSON("<?= site_url('getClassesByDepartment'); ?>/" + deptId + "/" + school_id, function (data) {
					if (data && data.success && data.classes) {
						$.each(data.classes, function (i, obj) {
							options += "<option value='" + obj.id + "' data-level='" + obj.level + "' data-fee='" + obj.fee + "'>" + obj.label + "</option>";
						});
						$("#classOptions").html(options);
					} else if (data && data.error) {
						toastada.error(data.error);
					}
				}).fail(function(xhr){
					console.error('Classes load failed:', xhr.status, xhr.responseText);
					notifyError("Could not load classes. Please try again.");
				});
			});

			$("#classOptions").on("change", function () {
				var $opt = $(this).find(':selected');
				var levelId = $opt.data('level') || '';
				$("#levelHidden").val(levelId);
				loadRequiredDocs();
			});

			let current_fs, next_fs, previous_fs;
			let opacity;
			let current = 1;
			let steps = $("#autoSave > fieldset").length;

			function setProgressBar(curStep) {
				var percent = parseFloat(100 / steps) * curStep;
				percent = percent.toFixed();
				$(".progress-bar").css("width", percent + "%");
			}
			setProgressBar(current);

			function validateFieldset($fieldset) {
				var valid = true;
				$fieldset.find('input, select, textarea').each(function () {
					if (!this.checkValidity()) {
						this.reportValidity();
						valid = false;
						return false;
					}
				});
				return valid;
			}

			$(".next").click(function () {
				current_fs = $(this).closest('fieldset');
				next_fs = current_fs.next('fieldset');
				if (!next_fs.length) {
					return false;
				}
				window.scrollTo(0, 0);
				if (!validateFieldset(current_fs)) {
					return false;
				}
				if (current == 1) {
					if (!$("#classOptions").val()) {
						toastada.error("Please select a class first");
						return false;
					}
					if (!$("#villageSelect").val()) {
						toastada.error("Please select your village");
						return false;
					}
					var names = ($('input[name=firstName]').val() || '') + ' ' + ($('input[name=lastName]').val() || '');
					var gender = $('select[name=gender]').val();
					var phone = $('input[name=phoneNumber]').val();
					var parentPhone = $('input[name=ft_phone]').val() || $('input[name=mt_phone]').val() || '';
					var level = $('#levelHidden').val();
					localStorage.setItem('data', JSON.stringify({ names, gender, phone, parentPhone, level }));
					loadRequiredDocs();
				}
				$("#progressbar li").eq($("#autoSave > fieldset").index(next_fs)).addClass("active");
				next_fs.css("display", "block");
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
				current_fs = $(this).closest('fieldset');
				previous_fs = current_fs.prev('fieldset');
				if (!previous_fs.length) {
					return false;
				}
				$("#progressbar li").eq($("#autoSave > fieldset").index(current_fs)).removeClass("active");
				previous_fs.css("display", "block");
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
		}

		function ssStartApplication() {
			if (window.__ssAppBooted || typeof window.jQuery === 'undefined') return;
			window.jQuery(ssBootApplication);
		}
		ssStartApplication();
		window.addEventListener('load', ssStartApplication);

		function agreeTerms(){
			$("#btn-pay").prop("disabled", !$("#exampleCheck1").is(":checked"));
		}
	</script>
</section>
