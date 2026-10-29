<!DOCTYPE html>
<html lang="<?= esc($_COOKIE['lang'] ?? 'en'); ?>">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta http-equiv="Content-Language" content="en">
	<title>XanderTech SmartSMS — Sign in</title>
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="description" content="Sign in to XanderTech SmartSMS.">
	<meta name="theme-color" content="#f6f1e6">
	<link rel="icon" href="<?= base_url('assets/images/xander-x-3d.png'); ?>">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@700;800&display=swap" rel="stylesheet">
	<meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
	<style>
		:root {
			--navy: #1b2744;
			--gold: #e0b84a;
			--gold-deep: #c9962a;
			--ink: #1b2744;
			--muted: #8b93a3;
			--line: #ece4d4;
			--safe-t: env(safe-area-inset-top, 0px);
			--safe-b: env(safe-area-inset-bottom, 0px);
			--safe-l: env(safe-area-inset-left, 0px);
			--safe-r: env(safe-area-inset-right, 0px);
		}
		* { box-sizing: border-box; }
		html, body {
			margin: 0;
			min-height: 100%;
			min-height: 100dvh;
			font-family: "DM Sans", "Segoe UI", sans-serif;
			color: var(--ink);
			background: #f6f1e6;
			overflow-x: hidden;
			-webkit-text-size-adjust: 100%;
		}
		.stage {
			position: fixed;
			inset: 0;
			pointer-events: none;
			overflow: hidden;
			z-index: 0;
		}
		.band {
			position: absolute;
			border-radius: 28px;
			transform: rotate(32deg);
		}
		.b1 { width: 280px; height: 140%; left: -150px; top: -18%; background: linear-gradient(180deg, #fff 0%, #f4e7c6 55%, #e4c56a 100%); opacity: .95; }
		.b2 { width: 120px; height: 80%; left: 70px; top: 8%; background: linear-gradient(180deg, rgba(255,255,255,.2), #f8f1df 30%, #edd9a2); opacity: .8; }
		.b3 { width: 240px; height: 130%; right: -130px; top: -12%; background: linear-gradient(200deg, #fff, #f3e6c2 40%, #e8d08a); }
		.b4 { width: 90px; height: 46%; right: 90px; bottom: -8%; background: linear-gradient(180deg, #f8f1df, #e7d7a4); opacity: .7; }

		.page {
			position: relative;
			z-index: 1;
			min-height: 100vh;
			min-height: 100dvh;
			display: flex;
			align-items: center;
			justify-content: center;
			padding: calc(28px + var(--safe-t)) calc(16px + var(--safe-r)) calc(72px + var(--safe-b)) calc(16px + var(--safe-l));
		}
		.auth-wrap { width: 100%; max-width: 440px; animation: rise .45s ease both; }
		.auth {
			position: relative;
			background: #fff;
			border-radius: 28px;
			padding: 36px 32px 28px;
			border: 1px solid #f0e4c8;
			box-shadow: 0 22px 50px rgba(176, 140, 48, .14), inset 0 3px 0 #e0b84a;
		}
		.brand {
			display: block;
			height: 58px;
			width: auto;
			margin: 4px auto 22px;
			border-radius: 10px;
			box-shadow: 0 10px 22px rgba(180, 140, 40, .28);
		}
		.auth h1 {
			margin: 0;
			text-align: center;
			font-family: Manrope, "DM Sans", sans-serif;
			font-size: 34px;
			font-weight: 800;
			letter-spacing: -.6px;
			color: #1b2744;
		}
		.auth .sub {
			margin: 8px 0 26px;
			text-align: center;
			color: #8d95a3;
			font-size: 15px;
		}

		.field { margin-bottom: 14px; }
		.sr {
			position: absolute;
			width: 1px;
			height: 1px;
			padding: 0;
			margin: -1px;
			overflow: hidden;
			clip: rect(0, 0, 0, 0);
			border: 0;
		}
		.input-wrap { position: relative; display: flex; align-items: center; }
		.input-wrap .ico {
			position: absolute;
			left: 16px;
			width: 18px;
			height: 18px;
			color: #c6a035;
			pointer-events: none;
		}
		.form-control {
			width: 100%;
			border: 1.5px solid #ece4d4;
			background: #fff;
			border-radius: 14px;
			padding: 14px 48px;
			font-size: 16px;
			font-family: inherit;
			color: var(--ink);
			min-height: 54px;
			transition: border-color .2s, box-shadow .2s;
		}
		.form-control::placeholder { color: #b0b6c2; }
		.form-control:focus {
			outline: none;
			border-color: #e0b84a;
			box-shadow: 0 0 0 4px rgba(224, 184, 74, .18);
		}
		.toggle-pass {
			position: absolute;
			right: 8px;
			border: 0;
			background: transparent;
			color: #c6a035;
			cursor: pointer;
			width: 40px;
			height: 40px;
			display: grid;
			place-items: center;
			padding: 0;
		}
		.toggle-pass svg { width: 18px; height: 18px; }
		.toggle-pass .eye-off { display: none; }
		.toggle-pass.is-shown .eye-on { display: none; }
		.toggle-pass.is-shown .eye-off { display: block; }
		.field-row {
			display: flex;
			justify-content: space-between;
			align-items: center;
			gap: 10px;
			margin: 2px 2px 16px;
			font-size: 13px;
		}
		.form-check {
			display: flex;
			align-items: center;
			gap: 8px;
			color: #8d95a3;
			cursor: pointer;
		}
		.form-check input { accent-color: #c6a035; width: 15px; height: 15px; }
		.link-muted {
			color: #a7842a;
			font-weight: 700;
			text-decoration: none;
		}
		.link-muted:hover { text-decoration: underline; }
		.btn-login {
			position: relative;
			width: 100%;
			border: 0;
			border-radius: 16px;
			min-height: 56px;
			padding: 14px 56px;
			font-family: inherit;
			font-weight: 800;
			font-size: 17px;
			color: #fff;
			cursor: pointer;
			text-align: center;
			background: linear-gradient(180deg, #f0d078 0%, #e0b44a 42%, #c9962a 100%);
			box-shadow: 0 12px 28px rgba(201, 150, 42, .35);
		}
		.btn-login:hover { filter: brightness(1.03); }
		.btn-login:disabled { opacity: .7; cursor: wait; }
		.btn-login .arr {
			position: absolute;
			right: 12px;
			top: 50%;
			transform: translateY(-50%);
			width: 32px;
			height: 32px;
			border-radius: 50%;
			background: rgba(255,255,255,.28);
			border: 1px solid rgba(255,255,255,.55);
			display: grid;
			place-items: center;
		}
		.btn-login .arr svg { width: 16px; height: 16px; }

		.extra {
			margin-top: 18px;
			display: flex;
			flex-wrap: wrap;
			justify-content: center;
			gap: 8px 14px;
			font-size: 13px;
		}
		.extra a {
			color: #8a6a22;
			font-weight: 700;
			text-decoration: none;
		}
		.extra a:hover { text-decoration: underline; }
		.panel-foot {
			display: flex;
			justify-content: space-between;
			gap: 8px;
			margin-top: 16px;
			font-size: 12px;
			color: #a0a7b4;
		}
		.panel-foot a { color: #8a6a22; font-weight: 700; text-decoration: none; }
		.alert {
			background: #fef2f2;
			border: 1px solid #fecaca;
			color: #991b1b;
			border-radius: 14px;
			padding: 12px 14px;
			margin-bottom: 14px;
		}
		.alert-heading { font-weight: 700; display: block; margin-bottom: 4px; }
		.alert p { margin: 0; font-size: .88rem; }
		.lang {
			position: fixed;
			z-index: 2;
			right: calc(14px + var(--safe-r));
			bottom: calc(14px + var(--safe-b));
			font-size: 13px;
			color: #8d95a3;
			display: flex;
			gap: 8px;
			align-items: center;
			background: rgba(255,255,255,.92);
			padding: 8px 12px;
			border-radius: 999px;
			border: 1px solid #efe4cc;
			box-shadow: 0 8px 20px rgba(176, 140, 48, .12);
		}
		.lang a {
			color: #1b2744;
			text-decoration: none;
			display: inline-flex;
			gap: 5px;
			align-items: center;
			font-weight: 700;
		}
		@keyframes rise {
			from { opacity: 0; transform: translateY(10px); }
			to { opacity: 1; transform: none; }
		}
		@media (max-width: 480px) {
			.auth { padding: 28px 18px 22px; border-radius: 22px; }
			.auth h1 { font-size: 28px; }
			.brand { height: 50px; }
		}
		@media (prefers-reduced-motion: reduce) {
			*, *::before, *::after { animation: none !important; transition: none !important; }
		}
	</style>
</head>
<body>
<?php
if (!isset($email)) { $email = ''; }
$logo = base_url('assets/images/xander-tech-wordmark.jpg');
?>

<div class="stage" aria-hidden="true">
	<i class="band b1"></i><i class="band b2"></i><i class="band b3"></i><i class="band b4"></i>
</div>

<div class="page">
	<div class="auth-wrap">
		<section class="auth" id="loginCard">
			<img class="brand" src="<?= $logo; ?>" alt="Xander Tech LLC">
			<h1>Welcome back</h1>
			<p class="sub"><?= lang('app.account'); ?></p>

			<form method="post" action="<?= base_url('login_pro'); ?>" id="frm_login">
				<?php if (!empty($error)) { ?>
					<div class="alert">
						<label class="alert-heading"><?= lang('app.loginFailed'); ?></label>
						<p><?= esc($error); ?></p>
					</div>
				<?php } ?>

				<div class="field">
					<label class="sr" for="email"><?= lang('app.email'); ?></label>
					<div class="input-wrap">
						<svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="5.5" width="17" height="13" rx="2" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M4 7l8 6 8-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
						<input name="email" id="email" type="text" class="form-control"
							   placeholder="<?= lang('app.email'); ?>" required minlength="4"
							   value="<?= esc($email); ?>" autocomplete="username" inputmode="email">
					</div>
				</div>

				<div class="field">
					<label class="sr" for="examplePassword"><?= lang('app.password'); ?></label>
					<div class="input-wrap">
						<svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10.5" width="14" height="9" rx="2" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M8 10.5V8a4 4 0 0 1 8 0v2.5" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
						<input name="password" id="examplePassword" type="password" class="form-control"
							   placeholder="<?= lang('app.password'); ?>" required minlength="6" autocomplete="current-password">
						<button type="button" class="toggle-pass" id="togglePass" aria-label="Show password" aria-pressed="false">
							<svg class="eye-on" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12z" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="2.5" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
							<svg class="eye-off" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4l18 16M10.5 6.2A10 10 0 0 1 12 6c6.5 0 10 6 10 6a18 18 0 0 1-4.1 4.6M6.1 8.4C3.8 10 2 12 2 12s3.5 6 10 6c1.2 0 2.3-.2 3.3-.6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
						</button>
					</div>
				</div>

				<div class="field-row">
					<label class="form-check">
						<input name="check" id="exampleCheck" type="checkbox">
						<span><?= lang('app.keep'); ?></span>
					</label>
					<a href="javascript:void(0);" class="link-muted btnrecover"><?= lang('app.recover'); ?></a>
				</div>

				<button class="btn-login" type="submit">
					<?= lang('app.login'); ?>
					<span class="arr" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
				</button>

				<div class="panel-foot">
					<span>SmartSMS <?= version; ?></span>
					<span><?= lang('app.poweredBy'); ?><a href="https://xandertech.rw" target="_blank" rel="noopener">XanderTech</a></span>
				</div>
			</form>

			<form class="autoSubmit validate" method="post" action="<?= base_url('reset_password'); ?>" id="frm_reset" style="display:none;">
				<div class="field">
					<label class="sr" for="reset_email"><?= lang('app.email'); ?></label>
					<div class="input-wrap">
						<svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="5.5" width="17" height="13" rx="2" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M4 7l8 6 8-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
						<input name="email" id="reset_email" type="text" class="form-control"
							   placeholder="<?= lang('app.email'); ?>" required minlength="4"
							   value="<?= esc($email); ?>" inputmode="email">
					</div>
				</div>
				<div class="field-row">
					<a href="javascript:void(0);" class="link-muted btnback"><?= lang('app.backLogin'); ?></a>
				</div>
				<button class="btn-login" type="submit"><?= lang('app.resetlink'); ?></button>
			</form>

		</section>
		<div id="quickAccessBlock" class="extra">
			<a href="<?= base_url('admin/login'); ?>">Platform admin</a>
			<a href="<?= base_url('student-marks'); ?>"><?= lang('app.studentMarks'); ?></a>
			<a href="<?= base_url('application'); ?>"><?= lang('app.onlineRegistration'); ?></a>
			<a href="<?= base_url('/'); ?>">Home</a>
		</div>
	</div>
</div>

<div class="lang">
	<strong><?= lang('app.languages'); ?></strong>
	<a href="javascript:void(0)" class="lang_switcher" data-target="en">
		<img src="<?= base_url('assets/images/en-flag.png'); ?>" width="18" height="18" alt=""> En
	</a>
	|
	<a href="javascript:void(0)" class="lang_switcher" data-target="fr">
		<img src="<?= base_url('assets/images/fr-flag.png'); ?>" width="20" height="20" alt=""> Fr
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
			var show = input.attr("type") === "password";
			input.attr("type", show ? "text" : "password");
			$(this).toggleClass("is-shown", show).attr("aria-pressed", show ? "true" : "false");
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
			$("#quickAccessBlock").slideUp(200);
			$("#frm_reset").slideDown(300);
			$("#frm_login").slideUp(300);
		});
		$(".btnback").on("click", function () {
			$("#frm_reset").slideUp(300);
			$("#frm_login").slideDown(300);
			$("#quickAccessBlock").slideDown(200);
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
