<!DOCTYPE html>
<html lang="<?= esc($_COOKIE['lang'] ?? 'en'); ?>">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta http-equiv="Content-Language" content="en">
	<title>XanderTech SmartSMS — Sign in</title>
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="description" content="Sign in to XanderTech SmartSMS.">
	<meta name="theme-color" content="#0b1f4a">
	<link rel="icon" href="<?= base_url('assets/images/xander-x-3d.png'); ?>">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
	<noscript><link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet"></noscript>
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.11.2/css/all.css" rel="stylesheet">
	<meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
	<style>
		:root {
			--navy: #0b1f4a;
			--navy-mid: #132a5c;
			--gold: #d4af37;
			--gold-soft: #f0d77a;
			--gold-deep: #b8941f;
			--bg: #f4f1ea;
			--bg-soft: #faf8f4;
			--ink: #1e293b;
			--muted: #64748b;
			--line: #e2e8f0;
			--card: #ffffff;
			--shadow: 0 18px 48px rgba(11, 31, 74, .12);
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
			font-family: "Outfit", "Segoe UI", sans-serif;
			color: var(--ink);
			background: var(--bg);
			overflow-x: hidden;
			-webkit-text-size-adjust: 100%;
		}
		.deco {
			position: fixed;
			inset: 0;
			pointer-events: none;
			z-index: 0;
			overflow: hidden;
		}
		.deco span {
			position: absolute;
			border-radius: 50%;
			opacity: .4;
		}
		.deco .d1 { width: 10px; height: 10px; background: #fde68a; top: 10%; left: 8%; }
		.deco .d2 { width: 8px; height: 8px; background: #93c5fd; top: 78%; right: 12%; }
		.deco .d3 { width: 220px; height: 220px; background: rgba(212, 175, 55, .12); top: -80px; right: -60px; }
		.deco .d4 { width: 180px; height: 180px; background: rgba(11, 31, 74, .08); bottom: -70px; left: -50px; }

		.page {
			position: relative;
			z-index: 1;
			min-height: 100vh;
			min-height: 100dvh;
			display: flex;
			align-items: center;
			justify-content: center;
			padding: calc(20px + var(--safe-t)) calc(16px + var(--safe-r)) calc(72px + var(--safe-b)) calc(16px + var(--safe-l));
		}
		.auth-wrap {
			width: 100%;
			max-width: 440px;
			animation: rise .5s ease both;
		}
		.auth {
			background: var(--card);
			border-radius: 22px;
			padding: 28px 24px 22px;
			box-shadow: var(--shadow);
			border: 1px solid var(--line);
		}
		.auth-head {
			display: flex;
			align-items: center;
			gap: 12px;
			margin-bottom: 4px;
		}
		.auth-head img { width: 42px; height: 42px; object-fit: contain; flex-shrink: 0; }
		.auth h2 {
			margin: 0;
			font-size: 1.35rem;
			font-weight: 800;
			color: var(--navy);
			line-height: 1.2;
		}
		.auth .sub {
			margin: 0 0 20px;
			color: var(--muted);
			font-size: .9rem;
		}

		.field { margin-bottom: 14px; }
		.field label {
			display: block;
			font-size: .75rem;
			font-weight: 700;
			color: var(--navy);
			margin-bottom: 6px;
			text-transform: uppercase;
			letter-spacing: .04em;
		}
		.input-wrap {
			position: relative;
			display: flex;
			align-items: center;
		}
		.input-wrap > i.left {
			position: absolute;
			left: 14px;
			color: #94a3b8;
			font-size: .9rem;
			pointer-events: none;
		}
		.form-control {
			width: 100%;
			border: 1.5px solid var(--line);
			background: var(--bg-soft);
			border-radius: 14px;
			padding: 13px 44px 13px 42px;
			font-size: 16px;
			font-family: inherit;
			color: var(--ink);
			min-height: 48px;
			transition: border-color .2s, box-shadow .2s, background .2s;
		}
		.form-control:focus {
			outline: none;
			border-color: var(--gold);
			background: #fff;
			box-shadow: 0 0 0 3px rgba(212, 175, 55, .2);
		}
		.toggle-pass {
			position: absolute;
			right: 8px;
			border: 0;
			background: transparent;
			color: #94a3b8;
			cursor: pointer;
			padding: 10px;
			min-width: 44px;
			min-height: 44px;
		}
		.field-row {
			display: flex;
			justify-content: space-between;
			align-items: center;
			gap: 10px;
			margin: 4px 0 16px;
			font-size: .86rem;
			flex-wrap: wrap;
		}
		.form-check {
			display: flex;
			align-items: center;
			gap: 8px;
			color: var(--muted);
			cursor: pointer;
			min-height: 32px;
		}
		.form-check input { width: 16px; height: 16px; }
		.link-muted {
			color: var(--navy-mid);
			font-weight: 600;
			text-decoration: none;
			font-size: .86rem;
			padding: 4px 0;
		}
		.link-muted:hover { color: var(--navy); text-decoration: underline; }

		.btn-login {
			width: 100%;
			border: 0;
			border-radius: 14px;
			padding: 14px 18px;
			min-height: 50px;
			font-family: inherit;
			font-weight: 700;
			font-size: 1rem;
			color: #0b1220;
			cursor: pointer;
			background: linear-gradient(135deg, var(--gold-soft) 0%, var(--gold) 50%, var(--gold-deep) 100%);
			box-shadow: 0 10px 28px rgba(184, 148, 31, .35);
			display: flex;
			align-items: center;
			justify-content: center;
			gap: 10px;
			transition: transform .15s, box-shadow .15s;
		}
		.btn-login:hover {
			transform: translateY(-1px);
			box-shadow: 0 14px 32px rgba(184, 148, 31, .42);
		}
		.btn-login:disabled { opacity: .7; cursor: wait; transform: none; }
		.btn-login .arr {
			width: 28px; height: 28px;
			border-radius: 50%;
			background: rgba(255,255,255,.35);
			display: grid; place-items: center;
			font-size: .75rem;
			flex-shrink: 0;
		}

		.divider {
			display: flex;
			align-items: center;
			gap: 12px;
			margin: 20px 0 14px;
			color: var(--muted);
			font-size: .75rem;
			font-weight: 600;
			text-transform: uppercase;
			letter-spacing: .06em;
		}
		.divider::before, .divider::after {
			content: "";
			flex: 1;
			height: 1px;
			background: var(--line);
		}

		.quick-grid {
			display: grid;
			grid-template-columns: repeat(3, minmax(0, 1fr));
			gap: 8px;
		}
		.quick-btn {
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			gap: 6px;
			padding: 12px 6px;
			min-height: 72px;
			border: 1.5px solid var(--line);
			border-radius: 14px;
			background: var(--bg-soft);
			color: var(--navy);
			text-decoration: none;
			font-size: .7rem;
			font-weight: 600;
			text-align: center;
			line-height: 1.25;
			transition: border-color .2s, background .2s;
			cursor: pointer;
			font-family: inherit;
		}
		.quick-btn:hover, .quick-btn:focus {
			border-color: var(--gold);
			background: rgba(212,175,55,.08);
			color: var(--navy);
			outline: none;
		}
		.quick-btn i {
			font-size: 1.05rem;
			color: var(--gold-deep);
		}

		.auth-foot {
			margin-top: 16px;
			text-align: center;
			font-size: .82rem;
			color: var(--muted);
			line-height: 1.5;
		}
		.auth-foot a {
			color: var(--navy);
			font-weight: 700;
			text-decoration: none;
		}
		.auth-foot a:hover { text-decoration: underline; }
		.panel-foot {
			display: flex;
			justify-content: space-between;
			gap: 8px;
			margin-top: 16px;
			padding-top: 14px;
			border-top: 1px solid var(--line);
			font-size: .72rem;
			color: #94a3b8;
			flex-wrap: wrap;
		}
		.panel-foot a { color: var(--navy-mid); font-weight: 600; text-decoration: none; }

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
			right: calc(12px + var(--safe-r));
			bottom: calc(12px + var(--safe-b));
			font-size: .8rem;
			color: var(--muted);
			display: flex;
			gap: 8px;
			align-items: center;
			background: rgba(255,255,255,.92);
			padding: 6px 12px;
			border-radius: 999px;
			border: 1px solid var(--line);
			box-shadow: 0 4px 12px rgba(0,0,0,.06);
		}
		.lang a {
			color: var(--navy);
			text-decoration: none;
			display: inline-flex;
			gap: 5px;
			align-items: center;
			font-weight: 600;
			min-height: 28px;
		}

		@keyframes rise {
			from { opacity: 0; transform: translateY(12px); }
			to { opacity: 1; transform: translateY(0); }
		}

		@media (max-width: 480px) {
			.page { padding: calc(12px + var(--safe-t)) 12px calc(68px + var(--safe-b)); }
			.auth {
				padding: 22px 16px 18px;
				border-radius: 18px;
			}
			.auth h2 { font-size: 1.2rem; }
			.quick-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
			.quick-btn { min-height: 68px; font-size: .68rem; }
			.field-row { margin-bottom: 14px; }
		}
		@media (max-width: 360px) {
			.auth-head img { width: 36px; height: 36px; }
			.btn-login { font-size: .95rem; }
		}
		@media (max-height: 720px) and (max-width: 480px) {
			.page { align-items: flex-start; }
			.auth { padding-top: 18px; }
			.quick-btn { min-height: 60px; padding: 10px 6px; }
		}
		@media (prefers-reduced-motion: reduce) {
			*, *::before, *::after { animation: none !important; transition: none !important; }
		}
	</style>
</head>
<body>
<?php
if (!isset($email)) { $email = ''; }
$logoX = base_url('assets/images/xander-x-3d.png');
?>

<div class="deco" aria-hidden="true">
	<span class="d1"></span><span class="d2"></span><span class="d3"></span><span class="d4"></span>
</div>

<div class="page">
	<div class="auth-wrap">
		<section class="auth" id="loginCard">
			<div class="auth-head">
				<img src="<?= $logoX; ?>" alt="XanderTech">
				<h2>Welcome back</h2>
			</div>
			<p class="sub"><?= lang('app.account'); ?></p>

			<form method="post" action="<?= base_url('login_pro'); ?>" id="frm_login">
				<?php if (!empty($error)) { ?>
					<div class="alert">
						<label class="alert-heading"><?= lang('app.loginFailed'); ?></label>
						<p><?= esc($error); ?></p>
					</div>
				<?php } ?>

				<div class="field">
					<label for="email"><?= lang('app.email'); ?></label>
					<div class="input-wrap">
						<i class="fas fa-envelope left"></i>
						<input name="email" id="email" type="text" class="form-control"
							   placeholder="<?= lang('app.enterEmail'); ?>" required minlength="4"
							   value="<?= esc($email); ?>" autocomplete="username" inputmode="email">
					</div>
				</div>

				<div class="field">
					<div class="field-row" style="margin-bottom:6px;">
						<label for="examplePassword" style="margin:0;"><?= lang('app.password'); ?></label>
						<a href="javascript:void(0);" class="link-muted btnrecover"><?= lang('app.recover'); ?></a>
					</div>
					<div class="input-wrap">
						<i class="fas fa-lock left"></i>
						<input name="password" id="examplePassword" type="password" class="form-control"
							   placeholder="<?= lang('app.enterPass'); ?>" required minlength="6" autocomplete="current-password">
						<button type="button" class="toggle-pass" id="togglePass" aria-label="Show password">
							<i class="fas fa-eye"></i>
						</button>
					</div>
				</div>

				<div class="field-row">
					<label class="form-check">
						<input name="check" id="exampleCheck" type="checkbox">
						<span><?= lang('app.keep'); ?></span>
					</label>
				</div>

				<button class="btn-login" type="submit">
					<?= lang('app.loginDashboard'); ?>
					<span class="arr"><i class="fas fa-arrow-right"></i></span>
				</button>

				<div class="panel-foot">
					<span>SmartSMS <?= version; ?></span>
					<span><?= lang('app.poweredBy'); ?><a href="https://xandertech.rw" target="_blank" rel="noopener">XanderTech</a></span>
				</div>
			</form>

			<form class="autoSubmit validate" method="post" action="<?= base_url('reset_password'); ?>" id="frm_reset" style="display:none;">
				<div class="field">
					<label for="reset_email"><?= lang('app.email'); ?></label>
					<div class="input-wrap">
						<i class="fas fa-envelope left"></i>
						<input name="email" id="reset_email" type="text" class="form-control"
							   placeholder="<?= lang('app.enterEmail'); ?>" required minlength="4"
							   value="<?= esc($email); ?>" inputmode="email">
					</div>
				</div>
				<div class="field-row">
					<a href="javascript:void(0);" class="link-muted btnback"><?= lang('app.backLogin'); ?></a>
				</div>
				<button class="btn-login" type="submit"><?= lang('app.resetlink'); ?></button>
			</form>

			<div id="quickAccessBlock">
				<div class="divider">Or quick access</div>
				<div class="quick-grid">
					<a class="quick-btn is-link" href="<?= base_url('admin/login'); ?>" title="XanderTech platform admin">
						<i class="fas fa-crown"></i>
						Platform Admin
					</a>
					<button type="button" class="quick-btn" data-focus="login" title="Head Master, Secretary, DOS">
						<i class="fas fa-user-tie"></i>
						School Admin
					</button>
					<button type="button" class="quick-btn" data-focus="login" title="Teachers sign in with staff email">
						<i class="fas fa-chalkboard-teacher"></i>
						Teacher
					</button>
					<button type="button" class="quick-btn" data-focus="login" title="Accountant, Cashier (post 8–9)">
						<i class="fas fa-calculator"></i>
						Accountant
					</button>
					<a class="quick-btn is-link" href="<?= base_url('student-marks'); ?>">
						<i class="fas fa-graduation-cap"></i>
						<?= lang('app.studentMarks'); ?>
					</a>
					<a class="quick-btn is-link" href="<?= base_url('application'); ?>">
						<i class="fas fa-file-signature"></i>
						<?= lang('app.onlineRegistration'); ?>
					</a>
				</div>
				<p class="auth-foot">
					New to SmartSMS?
					<a href="<?= base_url('application'); ?>">Apply for online registration</a>
					<br>
					<a href="<?= base_url('/'); ?>">Public home</a>
				</p>
			</div>
		</section>
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
			var icon = $(this).find("i");
			if (input.attr("type") === "password") {
				input.attr("type", "text");
				icon.removeClass("fa-eye").addClass("fa-eye-slash");
			} else {
				input.attr("type", "password");
				icon.removeClass("fa-eye-slash").addClass("fa-eye");
			}
		});

		$("[data-focus='login']").on("click", function () {
			$("#email").focus();
			$("#loginCard")[0].scrollIntoView({ behavior: "smooth", block: "center" });
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
