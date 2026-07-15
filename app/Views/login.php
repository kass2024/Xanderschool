<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta http-equiv="Content-Language" content="en">
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<title>SmartSMS — XanderTech Smart School Management System</title>
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, shrink-to-fit=no">
	<meta name="description" content="XanderTech Smart School Management System (SmartSMS) — cloud-based admissions, attendance, examinations, fees, report cards, and parent communication.">
	<meta name="msapplication-tap-highlight" content="no">
	<link rel="icon" href="<?= base_url('assets/images/smartsms-logo-web.png'); ?>">
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.11.2/css/all.css" rel="stylesheet">
	<meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
	<style>
		:root {
			--brand: #0EA5E9;
			--brand-dark: #0284C7;
			--navy: #0B1220;
			--navy-800: #1A2336;
			--page: #F8FAFC;
			--input: #F1F5F9;
			--text: #0F172A;
			--muted: #64748B;
			--violet: #6366F1;
		}
		* { box-sizing: border-box; }
		html, body {
			margin: 0;
			min-height: 100%;
			font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
			background:
				radial-gradient(900px 420px at 10% -10%, rgba(14,165,233,0.18), transparent 60%),
				radial-gradient(700px 360px at 95% 0%, rgba(99,102,241,0.14), transparent 55%),
				var(--page);
			color: var(--text);
		}
		.login-page {
			min-height: 100vh;
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 24px 16px 64px;
		}
		.login-card {
			width: 100%;
			max-width: 1080px;
			min-height: 620px;
			display: grid;
			grid-template-columns: 1.08fr 0.92fr;
			border-radius: 20px;
			overflow: hidden;
			background: #fff;
			box-shadow: 0 22px 55px rgba(11, 18, 32, 0.16);
			border: 1px solid #E2E8F0;
		}
		.login-brand {
			background: linear-gradient(165deg, var(--navy) 0%, #0F172A 52%, var(--navy-800) 100%);
			color: #fff;
			padding: 36px 40px 40px;
			display: flex;
			flex-direction: column;
			gap: 14px;
			position: relative;
			overflow: hidden;
		}
		.login-brand::after {
			content: "";
			position: absolute;
			width: 280px;
			height: 280px;
			right: -80px;
			bottom: -90px;
			border-radius: 50%;
			background: radial-gradient(circle, rgba(14,165,233,0.35), transparent 68%);
			pointer-events: none;
		}
		.brand-top {
			display: flex;
			align-items: center;
			gap: 14px;
			position: relative;
			z-index: 1;
		}
		.brand-logo {
			width: 72px;
			height: 72px;
			border-radius: 16px;
			object-fit: cover;
			background: #fff;
			padding: 4px;
			box-shadow: 0 8px 24px rgba(0,0,0,0.25);
		}
		.brand-titles h1 {
			margin: 0;
			font-size: 1.55rem;
			line-height: 1.15;
			font-weight: 800;
			letter-spacing: -0.02em;
		}
		.brand-titles h1 span { color: #38BDF8; }
		.brand-titles .tag {
			margin-top: 4px;
			font-size: 0.72rem;
			letter-spacing: 0.12em;
			text-transform: uppercase;
			color: #94A3B8;
			font-weight: 600;
		}
		.lead {
			margin: 6px 0 0;
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
		}
		.form-control:focus {
			border-color: var(--brand);
			box-shadow: 0 0 0 3px rgba(14,165,233,0.18);
			background: #fff;
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
		}
		.btn-login:hover { filter: brightness(0.96); }
		.btn-login:disabled { opacity: 0.7; cursor: wait; }
		.alert {
			padding: 12px 14px;
			border-radius: 10px;
			margin-bottom: 16px;
			background: #FEF2F2;
			border: 1px solid #FECACA;
			color: #991B1B;
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
			font-size: 0.85rem;
			color: var(--muted);
			background: rgba(255,255,255,0.92);
			padding: 8px 12px;
			border-radius: 999px;
			box-shadow: 0 4px 14px rgba(15,23,42,0.08);
		}
		.lang a { margin-left: 8px; color: var(--text); text-decoration: none; }
		.lang img { vertical-align: middle; margin-right: 4px; margin-top: -2px; }
		@media (max-width: 920px) {
			.login-card { grid-template-columns: 1fr; max-width: 480px; min-height: auto; }
			.feature-grid { grid-template-columns: 1fr; }
			.login-brand { padding: 28px; }
			.login-panel { padding: 32px 28px 36px; }
			.slogan { margin-top: 12px; }
		}
	</style>
</head>
<body>
<?php
if (!isset($email)) { $email = ''; }
$logo = base_url('assets/images/smartsms-logo-web.png');
?>
<div class="login-page">
	<div class="login-card">
		<aside class="login-brand">
			<div class="brand-top">
				<img class="brand-logo" src="<?= $logo; ?>" alt="XanderTech SmartSMS">
				<div class="brand-titles">
					<h1>XanderTech <span>SmartSMS</span></h1>
					<div class="tag">Smart School Management System</div>
				</div>
			</div>

			<p class="lead">
				Cloud-based solution for admissions, attendance, examinations, fees, report cards,
				and parent communication — built by XanderTech to digitize every day school operations
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

		<section class="login-panel">
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
					toastada.error("Fatal error occurred, if the problem persist please contact system admin");
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
