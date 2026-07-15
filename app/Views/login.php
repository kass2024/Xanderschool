<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta http-equiv="Content-Language" content="en">
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<title>SmartSMS — XanderTech Smart School Management System</title>
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, shrink-to-fit=no">
	<meta name="description" content="XanderTech Smart School Management System (SmartSMS)">
	<meta name="msapplication-tap-highlight" content="no">
	<link rel="icon" href="<?= base_url('assets/images/smartsms-mark-web.png'); ?>">
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.11.2/css/all.css" rel="stylesheet">
	<meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
	<style>
		:root {
			--brand: #0EA5E9;
			--brand-dark: #0284C7;
			--navy: #0B1220;
			--navy-800: #1A2336;
			--input: #F1F5F9;
			--text: #0F172A;
			--muted: #64748B;
			--violet: #6366F1;
			--gap: 28px;
		}
		* { box-sizing: border-box; }
		html, body {
			margin: 0;
			min-height: 100%;
			font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
			color: var(--text);
			overflow-x: hidden;
		}

		/* Full-page smart animated background */
		.bg-scene {
			position: fixed;
			inset: 0;
			z-index: 0;
			background:
				radial-gradient(1200px 700px at 12% -8%, rgba(14,165,233,0.38), transparent 55%),
				radial-gradient(900px 600px at 88% 8%, rgba(99,102,241,0.28), transparent 50%),
				radial-gradient(800px 500px at 50% 110%, rgba(34,211,238,0.18), transparent 45%),
				linear-gradient(160deg, #07101f 0%, #0f172a 42%, #12263f 100%);
		}
		.bg-grid {
			position: absolute;
			inset: 0;
			background-image:
				linear-gradient(rgba(148,163,184,0.08) 1px, transparent 1px),
				linear-gradient(90deg, rgba(148,163,184,0.08) 1px, transparent 1px);
			background-size: 48px 48px;
			mask-image: radial-gradient(ellipse at center, #000 35%, transparent 78%);
			animation: gridDrift 28s linear infinite;
		}
		.orb {
			position: absolute;
			border-radius: 50%;
			filter: blur(2px);
			opacity: 0.55;
			animation: floatY 12s ease-in-out infinite;
		}
		.orb-a {
			width: 340px; height: 340px;
			left: -80px; top: 18%;
			background: radial-gradient(circle, rgba(14,165,233,0.55), transparent 68%);
			animation-duration: 14s;
		}
		.orb-b {
			width: 280px; height: 280px;
			right: -60px; top: 8%;
			background: radial-gradient(circle, rgba(99,102,241,0.5), transparent 68%);
			animation-duration: 16s;
			animation-delay: -4s;
		}
		.orb-c {
			width: 220px; height: 220px;
			left: 42%; bottom: -40px;
			background: radial-gradient(circle, rgba(34,211,238,0.42), transparent 70%);
			animation-duration: 11s;
			animation-delay: -2s;
		}
		.scanline {
			position: absolute;
			left: 0; right: 0;
			height: 120px;
			background: linear-gradient(180deg, transparent, rgba(56,189,248,0.08), transparent);
			animation: scan 9s linear infinite;
			pointer-events: none;
		}

		.login-page {
			position: relative;
			z-index: 1;
			min-height: 100vh;
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 28px 18px 72px;
		}
		.login-shell {
			width: 100%;
			max-width: 1120px;
			display: grid;
			grid-template-columns: 1.05fr 0.95fr;
			gap: var(--gap);
			align-items: stretch;
		}

		.panel {
			border-radius: 22px;
			min-height: 620px;
			box-shadow:
				0 24px 60px rgba(2, 8, 23, 0.45),
				0 0 0 1px rgba(148, 163, 184, 0.12);
			animation: riseIn 0.75s cubic-bezier(.2,.8,.2,1) both;
			transition: transform .25s ease, box-shadow .25s ease;
		}
		.panel:hover {
			transform: translateY(-3px);
			box-shadow:
				0 30px 70px rgba(2, 8, 23, 0.55),
				0 0 0 1px rgba(56, 189, 248, 0.22);
		}
		.login-panel { animation-delay: 0.12s; }

		.login-brand {
			background: linear-gradient(165deg, rgba(11,18,32,0.92) 0%, rgba(15,23,42,0.96) 55%, rgba(26,35,54,0.96) 100%);
			backdrop-filter: blur(10px);
			color: #fff;
			padding: 34px 36px 36px;
			display: flex;
			flex-direction: column;
			gap: 14px;
			position: relative;
			overflow: hidden;
		}
		.login-brand::before {
			content: "";
			position: absolute;
			inset: -40% auto auto -20%;
			width: 280px; height: 280px;
			border-radius: 50%;
			background: radial-gradient(circle, rgba(14,165,233,0.35), transparent 70%);
			animation: pulseSoft 6s ease-in-out infinite;
		}
		.login-brand::after {
			content: "";
			position: absolute;
			width: 260px; height: 260px;
			right: -90px; bottom: -100px;
			border-radius: 50%;
			background: radial-gradient(circle, rgba(99,102,241,0.28), transparent 70%);
			pointer-events: none;
		}

		.brand-logo-wrap {
			position: relative;
			z-index: 1;
			width: fit-content;
			margin-bottom: 4px;
		}
		.brand-logo {
			width: 96px;
			height: 96px;
			border-radius: 22px;
			object-fit: contain;
			background: #fff;
			padding: 8px;
			box-shadow:
				0 10px 28px rgba(0,0,0,0.35),
				0 0 0 1px rgba(56,189,248,0.35);
			animation: logoFloat 5.5s ease-in-out infinite;
		}
		.brand-logo-glow {
			position: absolute;
			inset: -10px;
			border-radius: 28px;
			background: radial-gradient(circle, rgba(14,165,233,0.45), transparent 70%);
			z-index: -1;
			filter: blur(8px);
			animation: pulseSoft 4s ease-in-out infinite;
		}

		.lead {
			margin: 2px 0 0;
			font-size: 0.98rem;
			line-height: 1.55;
			color: #E2E8F0;
			position: relative;
			z-index: 1;
		}
		.problem {
			margin: 0;
			font-size: 0.9rem;
			line-height: 1.5;
			color: #CBD5E1;
			position: relative;
			z-index: 1;
			padding: 12px 14px;
			border-radius: 12px;
			background: rgba(255,255,255,0.05);
			border: 1px solid rgba(148,163,184,0.2);
		}
		.section-label {
			margin: 4px 0 0;
			font-size: 0.72rem;
			letter-spacing: 0.12em;
			text-transform: uppercase;
			color: #38BDF8;
			font-weight: 700;
			position: relative;
			z-index: 1;
		}
		.feature-grid {
			display: grid;
			grid-template-columns: 1fr 1fr;
			gap: 10px;
			position: relative;
			z-index: 1;
			margin: 0;
			padding: 0;
		}
		.feature-grid li {
			list-style: none;
			margin: 0;
			padding: 10px 12px;
			border-radius: 12px;
			background: rgba(14,165,233,0.1);
			border: 1px solid rgba(56,189,248,0.22);
			font-size: 0.86rem;
			line-height: 1.35;
			display: flex;
			gap: 8px;
			align-items: flex-start;
			transition: transform .2s ease, background .2s ease, border-color .2s ease;
		}
		.feature-grid li:hover {
			transform: translateY(-2px);
			background: rgba(14,165,233,0.18);
			border-color: rgba(56,189,248,0.45);
		}
		.feature-grid i {
			color: #38BDF8;
			margin-top: 2px;
			width: 14px;
		}
		.benefits {
			margin: 0;
			padding: 0;
			list-style: none;
			display: grid;
			gap: 6px;
			position: relative;
			z-index: 1;
		}
		.benefits li {
			font-size: 0.88rem;
			color: #E2E8F0;
			display: flex;
			gap: 8px;
			align-items: center;
		}
		.benefits i { color: #22D3EE; font-size: 0.75rem; }
		.brand-pill {
			display: inline-flex;
			align-self: flex-start;
			margin-top: 4px;
			padding: 11px 16px;
			border-radius: 10px;
			background: linear-gradient(90deg, #0EA5E9, #0284C7);
			color: #fff;
			font-weight: 700;
			font-size: 0.86rem;
			letter-spacing: 0.04em;
			text-transform: uppercase;
			position: relative;
			z-index: 1;
			box-shadow: 0 8px 22px rgba(14,165,233,0.35);
		}
		.slogan {
			margin-top: auto;
			font-size: 0.78rem;
			letter-spacing: 0.18em;
			text-transform: uppercase;
			color: #94A3B8;
			position: relative;
			z-index: 1;
		}

		.login-panel {
			background: rgba(255,255,255,0.96);
			backdrop-filter: blur(12px);
			padding: 48px 42px;
			display: flex;
			flex-direction: column;
			justify-content: center;
		}
		.login-panel h2 {
			margin: 0 0 6px;
			font-size: 1.7rem;
			font-weight: 800;
			color: var(--text);
		}
		.login-panel .sub {
			margin: 0 0 26px;
			color: var(--muted);
			font-size: 0.95rem;
		}
		.form-group { margin-bottom: 16px; }
		.form-group label {
			display: block;
			margin-bottom: 7px;
			font-size: 0.86rem;
			color: var(--muted);
			font-weight: 600;
		}
		.form-control {
			width: 100%;
			height: 48px;
			padding: 0 14px;
			border: 1.5px solid #CBD5E1;
			border-radius: 10px;
			background: var(--input);
			font-size: 0.98rem;
			color: var(--text);
			outline: none;
			transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease;
		}
		.form-control:focus {
			border-color: var(--brand);
			box-shadow: 0 0 0 3px rgba(14,165,233,0.18);
			background: #fff;
			transform: translateY(-1px);
		}
		.password-wrap { position: relative; }
		.password-wrap .form-control { padding-right: 46px; }
		.toggle-pass {
			position: absolute;
			right: 12px;
			top: 50%;
			transform: translateY(-50%);
			border: 0;
			background: transparent;
			color: #94A3B8;
			cursor: pointer;
			padding: 6px;
		}
		.form-row {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 12px;
			margin: 4px 0 20px;
			flex-wrap: wrap;
		}
		.form-check {
			display: flex;
			align-items: center;
			gap: 8px;
			color: var(--muted);
			font-size: 0.9rem;
		}
		.form-check input { width: 16px; height: 16px; accent-color: var(--brand); }
		.link-muted {
			color: var(--brand-dark);
			text-decoration: none;
			font-weight: 600;
			font-size: 0.9rem;
		}
		.link-muted:hover { text-decoration: underline; }
		.btn-login {
			width: 100%;
			height: 50px;
			border: 0;
			border-radius: 10px;
			background: linear-gradient(90deg, #0EA5E9, #0284C7);
			color: #fff;
			font-size: 1.05rem;
			font-weight: 700;
			cursor: pointer;
			box-shadow: 0 10px 24px rgba(14,165,233,0.35);
			transition: transform .15s ease, filter .15s ease, box-shadow .15s ease;
		}
		.btn-login:hover {
			filter: brightness(1.03);
			transform: translateY(-1px);
			box-shadow: 0 14px 28px rgba(14,165,233,0.42);
		}
		.btn-login:disabled { opacity: 0.7; cursor: wait; }
		.alert {
			padding: 12px 14px;
			border-radius: 10px;
			margin-bottom: 16px;
			background: #FEF2F2;
			border: 1px solid #FECACA;
			color: #991B1B;
			animation: shakeIn .35s ease;
		}
		.alert-heading { margin: 0 0 4px; font-weight: 700; display: block; }
		.alert p { margin: 0; font-size: 0.9rem; }
		.panel-foot {
			margin-top: 22px;
			display: flex;
			justify-content: space-between;
			gap: 10px;
			font-size: 0.78rem;
			color: #94A3B8;
		}
		.panel-foot a { color: var(--brand-dark); text-decoration: none; }
		.lang {
			position: fixed;
			left: 16px;
			bottom: 14px;
			z-index: 2;
			font-size: 0.85rem;
			color: #E2E8F0;
			background: rgba(15,23,42,0.72);
			padding: 8px 12px;
			border-radius: 999px;
			box-shadow: 0 4px 14px rgba(0,0,0,0.25);
			border: 1px solid rgba(148,163,184,0.25);
			backdrop-filter: blur(8px);
		}
		.lang a { margin-left: 8px; color: #F8FAFC; text-decoration: none; }
		.lang img { vertical-align: middle; margin-right: 4px; margin-top: -2px; }

		@keyframes riseIn {
			from { opacity: 0; transform: translateY(22px) scale(0.98); }
			to { opacity: 1; transform: translateY(0) scale(1); }
		}
		@keyframes floatY {
			0%, 100% { transform: translateY(0); }
			50% { transform: translateY(-28px); }
		}
		@keyframes logoFloat {
			0%, 100% { transform: translateY(0); }
			50% { transform: translateY(-6px); }
		}
		@keyframes pulseSoft {
			0%, 100% { opacity: 0.55; transform: scale(1); }
			50% { opacity: 0.9; transform: scale(1.06); }
		}
		@keyframes gridDrift {
			from { transform: translateY(0); }
			to { transform: translateY(48px); }
		}
		@keyframes scan {
			0% { top: -20%; }
			100% { top: 110%; }
		}
		@keyframes shakeIn {
			0% { transform: translateX(-6px); opacity: 0; }
			60% { transform: translateX(3px); opacity: 1; }
			100% { transform: translateX(0); }
		}

		@media (max-width: 960px) {
			.login-shell {
				grid-template-columns: 1fr;
				max-width: 480px;
				gap: 18px;
			}
			.panel { min-height: auto; }
			.feature-grid { grid-template-columns: 1fr; }
			.login-brand { padding: 28px; }
			.login-panel { padding: 32px 28px 36px; }
			.slogan { margin-top: 12px; }
		}
		@media (prefers-reduced-motion: reduce) {
			*, *::before, *::after {
				animation: none !important;
				transition: none !important;
			}
		}
	</style>
</head>
<body>
<?php
if (!isset($email)) { $email = ''; }
$logo = base_url('assets/images/smartsms-mark-web.png');
?>

<div class="bg-scene" aria-hidden="true">
	<div class="bg-grid"></div>
	<div class="orb orb-a"></div>
	<div class="orb orb-b"></div>
	<div class="orb orb-c"></div>
	<div class="scanline"></div>
</div>

<div class="login-page">
	<div class="login-shell">
		<aside class="panel login-brand">
			<div class="brand-logo-wrap">
				<span class="brand-logo-glow" aria-hidden="true"></span>
				<img class="brand-logo" src="<?= $logo; ?>" alt="SmartSMS">
			</div>

			<p class="lead">
				Cloud-based solution for admissions, attendance, examinations, fees, report cards,
				and parent communication — built by XanderTech to digitize everyday school operations
				from one secure dashboard.
			</p>

			<p class="problem">
				Schools often rely on disconnected spreadsheets and paper processes, making administration
				slow and difficult. SmartSMS centralizes records so teams can work faster with clear academic
				and financial visibility.
			</p>

			<div class="section-label">Core modules</div>
			<ul class="feature-grid">
				<li><i class="fas fa-user-plus"></i><span><strong>Admissions</strong> &amp; student management</span></li>
				<li><i class="fas fa-calendar-check"></i><span><strong>Attendance</strong> tracking</span></li>
				<li><i class="fas fa-file-alt"></i><span><strong>Examinations</strong> &amp; grades</span></li>
				<li><i class="fas fa-coins"></i><span><strong>Fees</strong> &amp; payments</span></li>
				<li><i class="fas fa-clipboard-list"></i><span><strong>Report cards</strong> &amp; academics</span></li>
				<li><i class="fas fa-comments"></i><span><strong>Parent</strong> communication</span></li>
			</ul>

			<div class="section-label">Benefits</div>
			<ul class="benefits">
				<li><i class="fas fa-check-circle"></i> Centralized student and school records</li>
				<li><i class="fas fa-check-circle"></i> Faster daily administration</li>
				<li><i class="fas fa-check-circle"></i> Better parent communication</li>
				<li><i class="fas fa-check-circle"></i> Accurate fee tracking</li>
				<li><i class="fas fa-check-circle"></i> Clear academic visibility</li>
			</ul>

			<span class="brand-pill">Manage. Monitor. Empower Education.</span>
			<div class="slogan">XanderTech · Smart IT Solutions &amp; Digital Services</div>
		</aside>

		<section class="panel login-panel">
			<h2>Sign in to your account</h2>
			<p class="sub">Access your XanderTech SmartSMS school dashboard</p>

			<form method="post" action="<?= base_url('login_pro'); ?>" id="frm_login">
				<?php if (!empty($error)) { ?>
					<div class="alert">
						<label class="alert-heading"><?= lang("app.loginFailed"); ?></label>
						<p><?= $error; ?></p>
					</div>
				<?php } ?>

				<div class="form-group">
					<label for="email"><?= lang("app.email"); ?></label>
					<input name="email" id="email" placeholder="<?= lang("app.enterEmail"); ?>" type="text"
						   class="form-control" required minlength="4" value="<?= esc($email); ?>">
				</div>

				<div class="form-group">
					<label for="examplePassword"><?= lang("app.password"); ?></label>
					<div class="password-wrap">
						<input name="password" id="examplePassword" placeholder="<?= lang("app.enterPass"); ?>"
							   type="password" class="form-control" required minlength="6">
						<button type="button" class="toggle-pass" id="togglePass" aria-label="Show password">
							<i class="fas fa-eye"></i>
						</button>
					</div>
				</div>

				<div class="form-row">
					<label class="form-check">
						<input name="check" id="exampleCheck" type="checkbox">
						<span><?= lang("app.keep"); ?></span>
					</label>
					<a href="javascript:void(0);" class="link-muted btnrecover"><?= lang("app.recover"); ?></a>
				</div>

				<button class="btn-login" type="submit"><?= lang("app.loginDashboard"); ?></button>

				<div class="panel-foot">
					<span>SmartSMS <?= version; ?></span>
					<span><?= lang("app.poweredBy"); ?><a href="https://xandertech.rw" target="_blank" rel="noopener">XanderTech</a></span>
				</div>
			</form>

			<form class="autoSubmit validate" method="post" action="<?= base_url('reset_password'); ?>" id="frm_reset" style="display:none;">
				<div class="form-group">
					<label for="reset_email"><?= lang("app.email"); ?></label>
					<input name="email" id="reset_email" placeholder="<?= lang("app.enterEmail"); ?>" type="text"
						   class="form-control" required minlength="4" value="<?= esc($email); ?>">
				</div>
				<div class="form-row">
					<a href="javascript:void(0);" class="link-muted btnback"><?= lang("app.backLogin"); ?></a>
				</div>
				<button class="btn-login" type="submit"><?= lang("app.resetlink"); ?></button>
				<div class="panel-foot">
					<span>SmartSMS <?= version; ?></span>
					<span><?= lang("app.poweredBy"); ?> <a href="https://xandertech.rw" target="_blank" rel="noopener">XanderTech</a></span>
				</div>
			</form>
		</section>
	</div>
</div>

<div class="lang">
	<strong><?= lang("app.languages"); ?></strong>
	<a href="javascript:void(0)" class="lang_switcher" data-target="en">
		<img src="<?= base_url('assets/images/en-flag.png'); ?>" width="20" height="20" alt="">English
	</a>|
	<a href="javascript:void(0)" class="lang_switcher" data-target="fr">
		<img src="<?= base_url('assets/images/fr-flag.png'); ?>" width="22" height="22" alt="">French
	</a>
</div>

<script src="<?= base_url('assets/js/jquery-3.4.1.min.js'); ?>"></script>
<script src="<?= base_url('assets/js/parsley.min.js'); ?>"></script>
<script src="<?= base_url(); ?>assets/js/toast.js"></script>
<script>
	$(function () {
		var active_btn = null;
		$("#togglePass").on("click", function () {
			var input = $("#examplePassword");
			var icon = $(this).find("i");
			if (input.attr("type") === "password") {
				input.attr("type", "text");
				icon.removeClass("fa-eye").addClass("fa-eye-slash");
			} else {
				input.attr("type", "password");
				icon.removeClass("fa-eye-slash").addClass("fa-eye");
			}
		});
		$(document).on("click", ".lang_switcher", function () {
			var lang = $(this).data("target");
			$.getJSON("<?= base_url('set_lang/'); ?>" + lang, function (json) {
				if (json.hasOwnProperty("success")) window.location.reload();
				else alert("Changing language failed");
			});
		});
		$(document).on("click", "form [type='submit']", function () { active_btn = $(this); });
		$("form").parsley();
		$(".btnrecover").on("click", function () {
			$("#frm_reset").slideDown(300);
			$("#frm_login").slideUp(300);
		});
		$(".btnback").on("click", function () {
			$("#frm_reset").slideUp(300);
			$("#frm_login").slideDown(300);
		});
		$(".autoSubmit").on("submit", function (e) {
			e.preventDefault();
			var form = $(this);
			var btn = active_btn || form.find("[type='submit']");
			var btn_txt = btn.text();
			btn.text("Please wait...").prop("disabled", true);
			$.post(form.prop("action"), form.serialize(), function (data) {
				btn.text(btn_txt).prop("disabled", false);
				if (data.hasOwnProperty("error")) toastada.error(data.error);
				else if (data.hasOwnProperty("success")) {
					toastada.success(data.success);
					form.trigger("reset");
				} else {
					toastada.error("System error occurred, if the problem persist please contact system admin");
				}
			}).fail(function () {
				btn.text(btn_txt).prop("disabled", false);
				toastada.error("System server error, please try again later");
			});
		});
	});
</script>
</body>
</html>
