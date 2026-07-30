<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta http-equiv="Content-Language" content="en">
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<title>XanderTech SmartSMS — Sign in</title>
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, shrink-to-fit=no">
	<meta name="description" content="XanderTech SmartSMS — cloud school OS for admissions, attendance, exams, fees, and parents.">
	<meta name="msapplication-tap-highlight" content="no">
	<link rel="icon" href="<?= base_url('assets/images/xander-x-3d.png'); ?>">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Syne:wght@600;700;800&display=swap" rel="stylesheet">
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.11.2/css/all.css" rel="stylesheet">
	<meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
	<style>
		:root {
			--ink: #060b16;
			--navy: #0b1f4a;
			--navy-mid: #132a5c;
			--gold: #d4af37;
			--gold-soft: #f0d77a;
			--paper: #f7f4ec;
			--muted: #9aa6bf;
			--line: rgba(212, 175, 55, 0.28);
			--text: #e8eefc;
		}
		* { box-sizing: border-box; }
		html, body {
			margin: 0;
			min-height: 100%;
			font-family: "Outfit", "Segoe UI", sans-serif;
			color: var(--text);
			background: var(--ink);
			overflow-x: hidden;
		}
		.scene {
			position: fixed;
			inset: 0;
			z-index: 0;
			background:
				radial-gradient(900px 520px at 8% 12%, rgba(212,175,55,.16), transparent 55%),
				radial-gradient(800px 480px at 92% 18%, rgba(19,42,92,.9), transparent 50%),
				radial-gradient(700px 420px at 50% 100%, rgba(11,31,74,.75), transparent 55%),
				linear-gradient(155deg, #04070f 0%, #0a1630 48%, #081228 100%);
		}
		.scene::before {
			content: "";
			position: absolute;
			inset: 0;
			background-image:
				linear-gradient(rgba(212,175,55,.05) 1px, transparent 1px),
				linear-gradient(90deg, rgba(212,175,55,.05) 1px, transparent 1px);
			background-size: 56px 56px;
			mask-image: radial-gradient(ellipse at 40% 30%, #000 20%, transparent 72%);
			animation: drift 36s linear infinite;
			pointer-events: none;
		}
		.glow {
			position: absolute;
			border-radius: 50%;
			filter: blur(40px);
			opacity: .45;
			pointer-events: none;
		}
		.glow-a {
			width: 280px; height: 280px; left: -40px; top: 30%;
			background: radial-gradient(circle, rgba(212,175,55,.35), transparent 70%);
			animation: float 14s ease-in-out infinite;
		}
		.glow-b {
			width: 320px; height: 320px; right: -60px; top: 8%;
			background: radial-gradient(circle, rgba(30,64,120,.55), transparent 70%);
			animation: float 18s ease-in-out infinite reverse;
		}

		.page {
			position: relative;
			z-index: 1;
			min-height: 100vh;
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 28px 18px 56px;
		}
		.shell {
			width: 100%;
			max-width: 1180px;
			display: grid;
			grid-template-columns: 1.15fr 0.85fr;
			gap: 28px;
			align-items: stretch;
		}

		.hero {
			position: relative;
			padding: 36px 40px 40px;
			border-radius: 28px;
			border: 1px solid var(--line);
			background:
				linear-gradient(165deg, rgba(19,42,92,.55), rgba(6,11,22,.72)),
				rgba(8,16,36,.55);
			backdrop-filter: blur(10px);
			overflow: hidden;
			animation: rise .8s cubic-bezier(.2,.8,.2,1) both;
		}
		.hero::after {
			content: "";
			position: absolute;
			right: -80px; bottom: -90px;
			width: 260px; height: 260px;
			background: url("<?= base_url('assets/images/xander-x-3d.png'); ?>") center / contain no-repeat;
			opacity: .12;
			pointer-events: none;
			animation: markSpin 28s linear infinite;
		}
		.brand-mark {
			display: block;
			width: min(100%, 420px);
			height: auto;
			margin: 0 0 22px;
			filter: drop-shadow(0 18px 40px rgba(0,0,0,.45));
			animation: brandIn 1s cubic-bezier(.2,.8,.2,1) both;
		}
		.hero h1 {
			font-family: "Syne", "Outfit", sans-serif;
			font-weight: 800;
			font-size: clamp(1.6rem, 2.6vw, 2.35rem);
			line-height: 1.15;
			letter-spacing: -.02em;
			margin: 0 0 12px;
			max-width: 18ch;
			color: #fff;
		}
		.hero .lead {
			margin: 0 0 26px;
			max-width: 42ch;
			font-size: 1.05rem;
			line-height: 1.55;
			color: var(--muted);
		}
		.hero .lead strong { color: var(--gold-soft); font-weight: 600; }

		.kicker {
			display: inline-block;
			font-size: .72rem;
			letter-spacing: .18em;
			text-transform: uppercase;
			color: var(--gold);
			margin-bottom: 8px;
			font-weight: 600;
		}
		.why {
			margin: 0 0 26px;
			padding: 16px 18px;
			border-left: 3px solid var(--gold);
			background: rgba(212,175,55,.06);
			border-radius: 0 14px 14px 0;
			color: #c9d4ea;
			line-height: 1.5;
			font-size: .95rem;
			animation: rise .9s .12s cubic-bezier(.2,.8,.2,1) both;
		}

		.modules {
			display: grid;
			grid-template-columns: 1fr 1fr;
			gap: 10px 18px;
			margin: 0 0 22px;
			padding: 0;
			list-style: none;
		}
		.modules li {
			display: flex;
			gap: 10px;
			align-items: flex-start;
			padding: 10px 0;
			border-bottom: 1px solid rgba(255,255,255,.06);
			animation: rise .85s cubic-bezier(.2,.8,.2,1) both;
		}
		.modules li:nth-child(1) { animation-delay: .08s; }
		.modules li:nth-child(2) { animation-delay: .12s; }
		.modules li:nth-child(3) { animation-delay: .16s; }
		.modules li:nth-child(4) { animation-delay: .2s; }
		.modules li:nth-child(5) { animation-delay: .24s; }
		.modules li:nth-child(6) { animation-delay: .28s; }
		.modules .ico {
			width: 34px; height: 34px;
			border-radius: 10px;
			display: grid; place-items: center;
			background: rgba(212,175,55,.12);
			color: var(--gold-soft);
			flex: 0 0 auto;
			font-size: .85rem;
		}
		.modules b { display: block; color: #fff; font-weight: 600; font-size: .92rem; }
		.modules small { color: var(--muted); font-size: .8rem; }

		.benefits {
			list-style: none;
			margin: 0;
			padding: 0;
			display: grid;
			gap: 8px;
		}
		.benefits li {
			display: flex;
			gap: 10px;
			align-items: center;
			color: #cfd8ec;
			font-size: .9rem;
		}
		.benefits i {
			color: var(--gold);
			font-size: .75rem;
		}
		.tagline {
			margin-top: 22px;
			font-size: .82rem;
			letter-spacing: .04em;
			color: var(--gold-soft);
			opacity: .9;
		}

		.auth {
			padding: 40px 36px 34px;
			border-radius: 28px;
			background: linear-gradient(180deg, #fffef9 0%, #f4efe3 100%);
			color: #0f172a;
			border: 1px solid rgba(212,175,55,.35);
			box-shadow:
				0 30px 70px rgba(0,0,0,.4),
				0 0 0 1px rgba(255,255,255,.06) inset;
			animation: rise .85s .1s cubic-bezier(.2,.8,.2,1) both;
			position: relative;
			overflow: hidden;
		}
		.auth::before {
			content: "";
			position: absolute;
			top: 0; left: 0; right: 0;
			height: 4px;
			background: linear-gradient(90deg, var(--navy), var(--gold), var(--navy));
			background-size: 200% 100%;
			animation: shimmer 5s ease infinite;
		}
		.auth-x {
			width: 54px;
			height: 54px;
			object-fit: contain;
			margin-bottom: 14px;
			display: block;
		}
		.auth h2 {
			font-family: "Syne", "Outfit", sans-serif;
			font-size: 1.55rem;
			margin: 0 0 6px;
			color: var(--navy);
			font-weight: 800;
			letter-spacing: -.02em;
		}
		.auth .sub {
			margin: 0 0 22px;
			color: #64748b;
			font-size: .95rem;
		}
		.form-group { margin-bottom: 16px; }
		.form-group label {
			display: block;
			font-size: .82rem;
			font-weight: 600;
			color: #334155;
			margin-bottom: 6px;
		}
		.form-control {
			width: 100%;
			border: 1px solid #e2e8f0;
			background: #fff;
			border-radius: 12px;
			padding: 12px 14px;
			font-size: .95rem;
			font-family: inherit;
			color: #0f172a;
			transition: border-color .2s, box-shadow .2s;
		}
		.form-control:focus {
			outline: none;
			border-color: var(--gold);
			box-shadow: 0 0 0 3px rgba(212,175,55,.22);
		}
		.password-wrap { position: relative; }
		.toggle-pass {
			position: absolute;
			right: 10px; top: 50%;
			transform: translateY(-50%);
			border: 0; background: transparent;
			color: #94a3b8; cursor: pointer; padding: 6px;
		}
		.form-row {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin: 8px 0 18px;
			font-size: .88rem;
		}
		.form-check {
			display: flex;
			align-items: center;
			gap: 8px;
			color: #475569;
			cursor: pointer;
		}
		.link-muted { color: var(--navy-mid); font-weight: 600; text-decoration: none; }
		.link-muted:hover { color: #0b1f4a; text-decoration: underline; }
		.btn-login {
			width: 100%;
			border: 0;
			border-radius: 12px;
			padding: 13px 16px;
			font-family: "Syne", "Outfit", sans-serif;
			font-weight: 700;
			font-size: 1rem;
			letter-spacing: .02em;
			color: #0b1220;
			cursor: pointer;
			background: linear-gradient(135deg, #f0d77a 0%, #d4af37 48%, #b8941f 100%);
			box-shadow: 0 10px 24px rgba(184,148,31,.35);
			transition: transform .15s ease, box-shadow .15s ease;
		}
		.btn-login:hover {
			transform: translateY(-1px);
			box-shadow: 0 14px 28px rgba(184,148,31,.42);
		}
		.btn-login:disabled { opacity: .7; cursor: wait; transform: none; }
		.panel-foot {
			display: flex;
			justify-content: space-between;
			margin-top: 18px;
			font-size: .78rem;
			color: #94a3b8;
		}
		.panel-foot a { color: var(--navy); font-weight: 600; text-decoration: none; }
		.alert {
			background: #fef2f2;
			border: 1px solid #fecaca;
			color: #991b1b;
			border-radius: 12px;
			padding: 12px 14px;
			margin-bottom: 14px;
			animation: shake .45s ease both;
		}
		.alert-heading { font-weight: 700; display: block; margin-bottom: 4px; }
		.alert p { margin: 0; font-size: .9rem; }

		.lang {
			position: fixed;
			z-index: 2;
			right: 18px; bottom: 14px;
			font-size: .82rem;
			color: var(--muted);
			display: flex;
			gap: 8px;
			align-items: center;
		}
		.lang a {
			color: var(--gold-soft);
			text-decoration: none;
			display: inline-flex;
			gap: 5px;
			align-items: center;
		}

		@keyframes rise {
			from { opacity: 0; transform: translateY(18px); }
			to { opacity: 1; transform: translateY(0); }
		}
		@keyframes brandIn {
			from { opacity: 0; transform: translateY(12px) scale(.98); }
			to { opacity: 1; transform: translateY(0) scale(1); }
		}
		@keyframes float {
			0%, 100% { transform: translateY(0); }
			50% { transform: translateY(-18px); }
		}
		@keyframes drift {
			from { transform: translateY(0); }
			to { transform: translateY(56px); }
		}
		@keyframes shimmer {
			0%, 100% { background-position: 0% 50%; }
			50% { background-position: 100% 50%; }
		}
		@keyframes markSpin {
			from { transform: rotate(0deg); }
			to { transform: rotate(360deg); }
		}
		@keyframes shake {
			0% { transform: translateX(-5px); }
			60% { transform: translateX(3px); }
			100% { transform: translateX(0); }
		}
		@media (max-width: 960px) {
			.shell { grid-template-columns: 1fr; max-width: 480px; }
			.hero { padding: 28px 24px; }
			.modules { grid-template-columns: 1fr; }
			.hero::after { opacity: .08; width: 180px; height: 180px; }
			.auth { padding: 32px 24px 28px; }
			.brand-mark { width: min(100%, 320px); }
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
$logoWord = base_url('assets/images/xander-wordmark-3d.png');
$logoX = base_url('assets/images/xander-x-3d.png');
?>

<div class="scene" aria-hidden="true">
	<div class="glow glow-a"></div>
	<div class="glow glow-b"></div>
</div>

<div class="page">
	<div class="shell">
		<aside class="hero">
			<img class="brand-mark" src="<?= $logoWord; ?>" alt="XanderTech">
			<span class="kicker">SmartSMS</span>
			<h1>School operations, one secure dashboard</h1>
			<p class="lead">
				<strong>Cloud-based school OS</strong> for admissions, attendance, examinations,
				fees, report cards, and parent communication — digitize everyday work with clarity.
			</p>

			<div class="why">
				<span class="kicker">Why SmartSMS</span>
				Schools often rely on disconnected spreadsheets and paper. SmartSMS centralizes
				records so teams move faster with clear academic and financial visibility.
			</div>

			<span class="kicker">Core modules</span>
			<ul class="modules">
				<li>
					<span class="ico"><i class="fas fa-user-plus"></i></span>
					<span><b>Admissions</b><small>Student lifecycle &amp; records</small></span>
				</li>
				<li>
					<span class="ico"><i class="fas fa-calendar-check"></i></span>
					<span><b>Attendance</b><small>Daily &amp; course tracking</small></span>
				</li>
				<li>
					<span class="ico"><i class="fas fa-file-alt"></i></span>
					<span><b>Exams &amp; grades</b><small>Assessments made simple</small></span>
				</li>
				<li>
					<span class="ico"><i class="fas fa-coins"></i></span>
					<span><b>Fees &amp; payments</b><small>Accurate fee tracking</small></span>
				</li>
				<li>
					<span class="ico"><i class="fas fa-clipboard-list"></i></span>
					<span><b>Report cards</b><small>Academic visibility</small></span>
				</li>
				<li>
					<span class="ico"><i class="fas fa-comments"></i></span>
					<span><b>Parents</b><small>Built-in communication</small></span>
				</li>
			</ul>

			<span class="kicker">Benefits</span>
			<ul class="benefits">
				<li><i class="fas fa-check"></i> Centralized student and school records</li>
				<li><i class="fas fa-check"></i> Faster daily administration</li>
				<li><i class="fas fa-check"></i> Better parent communication</li>
				<li><i class="fas fa-check"></i> Accurate fee tracking</li>
				<li><i class="fas fa-check"></i> Clear academic visibility</li>
			</ul>
			<p class="tagline">Manage. Monitor. Empower Education. · XanderTech · Smart IT Solutions</p>
		</aside>

		<section class="auth">
			<img class="auth-x" src="<?= $logoX; ?>" alt="">
			<h2>Sign in</h2>
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
					<span><?= lang("app.poweredBy"); ?> <a href="https://xandertech.rw" target="_blank" rel="noopener">XanderTech</a></span>
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
		<img src="<?= base_url('assets/images/en-flag.png'); ?>" width="20" height="20" alt=""> English
	</a>
	|
	<a href="javascript:void(0)" class="lang_switcher" data-target="fr">
		<img src="<?= base_url('assets/images/fr-flag.png'); ?>" width="22" height="22" alt=""> French
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
