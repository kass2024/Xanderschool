<!DOCTYPE html>
<html lang="<?= esc($_COOKIE['lang'] ?? 'en'); ?>">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<title>XanderTech SmartSMS — Admin sign in</title>
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="description" content="Sign in to the XanderTech SmartSMS platform admin.">
	<meta name="theme-color" content="#f6f1e6">
	<link rel="icon" href="<?= base_url('assets/images/xander-x-3d.png'); ?>">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@700;800&display=swap" rel="stylesheet">
	<meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
	<style>
		:root {
			--ink: #1b2744;
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
		}
		.stage { position: fixed; inset: 0; pointer-events: none; overflow: hidden; }
		.band { position: absolute; border-radius: 28px; transform: rotate(32deg); }
		.b1 { width: 340px; height: 150%; left: -180px; top: -22%; background: linear-gradient(160deg, #fff 0%, #f8edd4 48%, #e7c86e 100%); }
		.b2 { width: 150px; height: 90%; left: 40px; top: -6%; background: linear-gradient(180deg, rgba(255,255,255,.15), #f6ead0 40%, #edd7a0); opacity: .85; }
		.b3 { width: 280px; height: 140%; right: -150px; top: -8%; background: linear-gradient(200deg, #fff 10%, #f4e6c4 55%, #e8d08a); }
		.b4 { width: 120px; height: 55%; right: 40px; bottom: -16%; background: linear-gradient(180deg, #fbf6ea, #e8d7a6); opacity: .75; }
		.page {
			position: relative;
			min-height: 100vh;
			min-height: 100dvh;
			display: flex;
			align-items: center;
			justify-content: center;
			padding: calc(28px + var(--safe-t)) calc(16px + var(--safe-r)) calc(28px + var(--safe-b)) calc(16px + var(--safe-l));
		}
		.auth-wrap { width: 100%; max-width: 440px; }
		.auth {
			background: #fffefb;
			border-radius: 30px;
			padding: 34px 32px 30px;
			border: 1.5px solid #e4c56a;
			box-shadow: 0 0 0 8px rgba(255, 252, 245, .72), 0 28px 60px rgba(176, 140, 48, .18);
		}
		.brand {
			display: block;
			height: 62px;
			width: auto;
			margin: 2px auto 22px;
			border-radius: 12px;
			box-shadow: 0 12px 24px rgba(180, 140, 40, .32);
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
		.auth .sub { margin: 8px 0 26px; text-align: center; color: #8d95a3; font-size: 15px; }
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
		.input-wrap .ico { position: absolute; left: 16px; width: 18px; height: 18px; color: #c6a035; pointer-events: none; }
		.form-control {
			width: 100%;
			border: 1.5px solid #ece4d4;
			background: #fff;
			border-radius: 14px;
			padding: 14px 48px;
			font: inherit;
			font-size: 16px;
			color: var(--ink);
			min-height: 54px;
		}
		.form-control::placeholder { color: #b0b6c2; }
		.form-control:focus { outline: none; border-color: #e0b84a; box-shadow: 0 0 0 4px rgba(224, 184, 74, .18); }
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
		.btn-login {
			position: relative;
			width: 100%;
			border: 0;
			border-radius: 16px;
			min-height: 56px;
			padding: 14px 56px;
			font: inherit;
			font-weight: 800;
			font-size: 17px;
			color: #fff;
			cursor: pointer;
			background: linear-gradient(180deg, #f0d078 0%, #e0b44a 42%, #c9962a 100%);
			box-shadow: 0 12px 28px rgba(201, 150, 42, .35);
		}
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
		.extra { margin-top: 18px; text-align: center; font-size: 13px; }
		.extra a { color: #8a6a22; font-weight: 700; text-decoration: none; }
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
		@media (max-width: 480px) {
			.auth { padding: 28px 18px 22px; border-radius: 22px; }
			.auth h1 { font-size: 28px; }
			.brand { height: 50px; }
		}
	</style>
</head>
<body>
<?php if (!isset($email)) { $email = ''; } ?>
<div class="stage" aria-hidden="true">
	<i class="band b1"></i><i class="band b2"></i><i class="band b3"></i><i class="band b4"></i>
</div>
<div class="page">
	<div class="auth-wrap">
		<section class="auth">
			<img class="brand" src="<?= base_url('assets/images/xander-tech-wordmark.jpg'); ?>" alt="Xander Tech LLC">
			<h1>Welcome back</h1>
			<p class="sub"><?= lang('app.account'); ?></p>
			<form method="post" action="<?= base_url('admin/login_pro'); ?>">
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
						<input name="email" id="email" type="email" class="form-control" placeholder="<?= lang('app.email'); ?>" required minlength="4" value="<?= esc($email); ?>" autocomplete="username">
					</div>
				</div>
				<div class="field">
					<label class="sr" for="examplePassword"><?= lang('app.password'); ?></label>
					<div class="input-wrap">
						<svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10.5" width="14" height="9" rx="2" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M8 10.5V8a4 4 0 0 1 8 0v2.5" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
						<input name="password" id="examplePassword" type="password" class="form-control" placeholder="<?= lang('app.password'); ?>" required minlength="6" autocomplete="current-password">
						<button type="button" class="toggle-pass" id="togglePass" aria-label="Show password" aria-pressed="false">
							<svg class="eye-on" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12z" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="2.5" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
							<svg class="eye-off" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4l18 16M10.5 6.2A10 10 0 0 1 12 6c6.5 0 10 6 10 6a18 18 0 0 1-4.1 4.6M6.1 8.4C3.8 10 2 12 2 12s3.5 6 10 6c1.2 0 2.3-.2 3.3-.6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
						</button>
					</div>
				</div>
				<button class="btn-login" type="submit">
					<?= lang('app.login'); ?>
					<span class="arr" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
				</button>
			</form>
		</section>
		<p class="extra"><a href="<?= base_url('login'); ?>">School sign in</a></p>
	</div>
</div>
<script>
	document.getElementById('togglePass').addEventListener('click', function () {
		var input = document.getElementById('examplePassword');
		var show = input.type === 'password';
		input.type = show ? 'text' : 'password';
		this.classList.toggle('is-shown', show);
		this.setAttribute('aria-pressed', show ? 'true' : 'false');
		this.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
	});
</script>
</body>
</html>
