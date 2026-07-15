<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta http-equiv="Content-Language" content="en">
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<title>IOTXAD - <?= lang("app.login")?></title>
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, shrink-to-fit=no">
	<meta name="description" content="School management system — attendance, academics, fees, staff and reports in one place.">
	<meta name="msapplication-tap-highlight" content="no">
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.11.2/css/all.css" rel="stylesheet">
	<meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
	<meta http-equiv="Pragma" content="no-cache">
	<meta http-equiv="Expires" content="0">
	<style>
		:root {
			--school-green: #145c3a;
			--school-green-dark: #0f4730;
			--school-accent: #d62828;
			--school-page: #eef1f4;
			--school-input: #f3f6f9;
			--school-text: #1c2430;
			--school-muted: #6b7785;
		}
		* { box-sizing: border-box; }
		html, body {
			margin: 0;
			min-height: 100%;
			font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
			background: var(--school-page);
			color: var(--school-text);
		}
		.login-page {
			min-height: 100vh;
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 24px 16px 56px;
		}
		.login-card {
			width: 100%;
			max-width: 980px;
			min-height: 560px;
			display: grid;
			grid-template-columns: 1fr 1fr;
			border-radius: 18px;
			overflow: hidden;
			background: #fff;
			box-shadow: 0 18px 48px rgba(20, 40, 30, 0.14);
		}
		.login-brand {
			background: linear-gradient(165deg, var(--school-green) 0%, var(--school-green-dark) 100%);
			color: #fff;
			padding: 42px 40px;
			display: flex;
			flex-direction: column;
			justify-content: center;
			gap: 18px;
		}
		.brand-logo {
			width: 88px;
			height: 88px;
			border-radius: 50%;
			object-fit: cover;
			background: #fff;
			border: 3px solid rgba(255,255,255,0.35);
			padding: 6px;
		}
		.brand-logo-fallback {
			width: 88px;
			height: 88px;
			border-radius: 50%;
			display: flex;
			align-items: center;
			justify-content: center;
			background: rgba(255,255,255,0.12);
			border: 2px solid rgba(255,255,255,0.35);
			font-weight: 700;
			font-size: 22px;
			letter-spacing: 0.5px;
		}
		.login-brand h1 {
			margin: 0;
			font-size: 2rem;
			line-height: 1.15;
			font-weight: 700;
		}
		.login-brand .lead {
			margin: 0;
			font-size: 0.98rem;
			line-height: 1.55;
			color: rgba(255,255,255,0.92);
		}
		.feature-list {
			list-style: none;
			margin: 4px 0 0;
			padding: 0;
			display: grid;
			gap: 8px;
		}
		.feature-list li {
			display: flex;
			align-items: flex-start;
			gap: 10px;
			font-size: 0.92rem;
			line-height: 1.4;
			color: rgba(255,255,255,0.95);
		}
		.feature-list i {
			margin-top: 3px;
			color: #ffd4d4;
			width: 16px;
			flex-shrink: 0;
		}
		.brand-pill {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			align-self: flex-start;
			margin-top: 8px;
			padding: 12px 18px;
			border: 0;
			border-radius: 10px;
			background: var(--school-accent);
			color: #fff;
			font-weight: 600;
			font-size: 0.92rem;
			cursor: default;
		}
		.login-panel {
			padding: 48px 42px;
			display: flex;
			flex-direction: column;
			justify-content: center;
			position: relative;
		}
		.login-panel h2 {
			margin: 0 0 6px;
			font-size: 1.65rem;
			font-weight: 700;
			color: var(--school-text);
		}
		.login-panel .sub {
			margin: 0 0 28px;
			color: var(--school-muted);
			font-size: 0.95rem;
		}
		.form-group { margin-bottom: 18px; }
		.form-group label {
			display: block;
			margin-bottom: 7px;
			font-size: 0.88rem;
			color: var(--school-muted);
			font-weight: 600;
		}
		.form-control {
			width: 100%;
			height: 48px;
			padding: 0 14px;
			border: 1.5px solid #d5dde6;
			border-radius: 10px;
			background: var(--school-input);
			font-size: 0.98rem;
			color: var(--school-text);
			outline: none;
			transition: border-color .15s ease, box-shadow .15s ease;
		}
		.form-control:focus {
			border-color: var(--school-green);
			box-shadow: 0 0 0 3px rgba(20, 92, 58, 0.12);
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
			color: #8a96a3;
			cursor: pointer;
			padding: 6px;
			font-size: 1rem;
		}
		.form-row {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 12px;
			margin: 6px 0 22px;
			flex-wrap: wrap;
		}
		.form-check {
			display: flex;
			align-items: center;
			gap: 8px;
			color: var(--school-muted);
			font-size: 0.9rem;
		}
		.form-check input { width: 16px; height: 16px; accent-color: var(--school-green); }
		.link-muted {
			color: var(--school-green);
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
			background: var(--school-green);
			color: #fff;
			font-size: 1.05rem;
			font-weight: 700;
			cursor: pointer;
			transition: background .15s ease;
		}
		.btn-login:hover { background: var(--school-green-dark); }
		.btn-login:disabled { opacity: 0.7; cursor: wait; }
		.alert {
			padding: 12px 14px;
			border-radius: 10px;
			margin-bottom: 16px;
			background: #fdecec;
			border: 1px solid #f5c2c2;
			color: #8a1f1f;
		}
		.alert-heading { margin: 0 0 4px; font-weight: 700; display: block; }
		.alert p { margin: 0; font-size: 0.9rem; }
		.panel-foot {
			margin-top: 22px;
			display: flex;
			justify-content: space-between;
			gap: 10px;
			font-size: 0.78rem;
			color: #93a0ad;
		}
		.panel-foot a { color: var(--school-green); text-decoration: none; }
		.lang {
			position: fixed;
			left: 16px;
			bottom: 14px;
			font-size: 0.85rem;
			color: var(--school-muted);
			background: rgba(255,255,255,0.9);
			padding: 8px 12px;
			border-radius: 999px;
			box-shadow: 0 4px 14px rgba(0,0,0,0.08);
		}
		.lang a {
			margin-left: 8px;
			color: var(--school-text);
			text-decoration: none;
		}
		.lang img {
			vertical-align: middle;
			margin-right: 4px;
			margin-top: -2px;
		}
		@media (max-width: 860px) {
			.login-card {
				grid-template-columns: 1fr;
				max-width: 460px;
				min-height: auto;
			}
			.login-brand { padding: 32px 28px; }
			.login-panel { padding: 32px 28px 36px; }
			.feature-list { display: none; }
		}
	</style>
</head>
<body>
<?php
if (!isset($type)) { $type = ''; }
if (!isset($email)) { $email = ''; }
if ($type == 'nhapa') {
	$logo = base_url('assets/images/schools/nhapa.jpg');
} else {
	$logo = base_url('assets/images/iotxad1.png');
}
?>
<div class="login-page">
	<div class="login-card">
		<aside class="login-brand">
			<img class="brand-logo" src="<?= $logo; ?>" alt="IOTXAD"
				 onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
			<div class="brand-logo-fallback" style="display:none;">IX</div>
			<h1>IOTXAD School MIS</h1>
			<p class="lead">
				Complete school management in one dashboard — students, staff, classes, attendance,
				fees, exams, discipline and reports for day-to-day school operations.
			</p>
			<ul class="feature-list">
				<li><i class="fas fa-user-graduate"></i><span>Student admissions, profiles, classes and academic records</span></li>
				<li><i class="fas fa-chalkboard-teacher"></i><span>Staff accounts, permissions and daily school workflows</span></li>
				<li><i class="fas fa-calendar-check"></i><span>Attendance tracking for students, boarding and courses</span></li>
				<li><i class="fas fa-coins"></i><span>Fees, payments, accounting and financial reporting</span></li>
				<li><i class="fas fa-book-open"></i><span>Marks, deliberation, library, transport and more</span></li>
			</ul>
			<span class="brand-pill">All-in-one School Management</span>
		</aside>

		<section class="login-panel">
			<h2>Sign in to your account</h2>
			<p class="sub">Access your IOTXAD school dashboard</p>

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
					<span>IOTXAD <?= version; ?></span>
					<span><?= lang("app.poweredBy"); ?><a href="http://www.bbdigitech.com" target="_blank" rel="noopener">BDS Ltd</a></span>
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
					<span>IOTXAD <?= version; ?></span>
					<span><?= lang("app.poweredBy"); ?> <a href="http://www.bbdigitech.com" target="_blank" rel="noopener">BDS Ltd</a></span>
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
				if (json.hasOwnProperty("success")) {
					window.location.reload();
				} else {
					alert("Changing language failed");
				}
			});
		});

		$(document).on("click", "form [type='submit']", function () {
			active_btn = $(this);
		});

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
				if (data.hasOwnProperty("error")) {
					toastada.error(data.error);
				} else if (data.hasOwnProperty("success")) {
					if (btn.data("target")) {
						toastada.success(data.success);
						var target = btn.data("target");
						if (target.startsWith("#")) {
							$(target).modal('hide');
							return;
						}
						if (target == "reload") {
							setTimeout(function () { window.location.reload(); }, 1500);
							return;
						}
						setTimeout(function () { window.location.href = btn.data("target"); }, 1500);
					} else {
						toastada.success(data.success);
						form.trigger("reset");
					}
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
