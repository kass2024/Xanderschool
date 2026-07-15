<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta http-equiv="Content-Language" content="en">
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<title>IOTXAD admin login</title>
	<meta name="viewport"
		  content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, shrink-to-fit=no">
	<meta name="description" content="school management system">

	<!-- Disable tap highlight on IE -->
	<meta name="msapplication-tap-highlight" content="no">

	<link href="<?=base_url();?>assets/css/main.css" rel="stylesheet">
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.11.2/css/all.css" rel="stylesheet">
	<link href="<?=base_url();?>assets/plugins/typicons/font/typicons.min.css" rel="stylesheet">
</head>

<body cz-shortcut-listen="true">
<div class="app-container app-theme-white body-tabs-shadow">
	<div class="app-container">
		<div class="h-100">
			<div class="h-100 no-gutters row">
				<div class="h-100 d-flex bg-white justify-content-center align-items-center col-md-12 col-lg-12">
					<div class="mx-auto app-login-box" style="border: 1px solid#cdcdcd;padding: 20px;width: 420px;box-shadow: 1px 1px 10px 1px;border-radius: 8px;background-color:#f2f2f2;position:relative;">
						<div class="app-logo"></div>
						<h4 class="mb-0">
							<span class="d-block"><?= lang("app.welcomeBack"); ?></span>
							<span><?= lang("app.account"); ?></span></h4>
						</h6>
						<div class="divider row"></div>
						<div style="margin-bottom: 20px;">
							<form class="" method="post" action="<?=base_url('admin/login_pro');?>">
								<?php
								if (!empty($error)) {
									?>
									<div class="alert alert-danger">
										<label class="alert-heading"><?= lang("app.loginFailed"); ?></label>
										<p><?= $error; ?></p>
									</div>
									<?php
								}
								?>
									<div class="form-group">
										<div class="position-relative form-group"><label for="username" class=""><?= lang("app.email"); ?></label><input
												name="email" id="email" placeholder="<?= lang("app.enterEmail"); ?>" type="email"
												class="form-control" required minlength="4" value="<?=$email;?>"></div>
									</div>
									<div class="form-group">
										<div class="position-relative form-group"><label for="examplePassword" class=""><?= lang("app.password"); ?></label><input
												name="password" id="examplePassword" placeholder="<?= lang("app.enterPass"); ?>"
												type="password" class="form-control" required minlength="6"></div>
									</div>
								<div class="position-relative form-check"><input name="check" id="exampleCheck"
																				 type="checkbox"
																				 class="form-check-input"><label
										for="exampleCheck" class="form-check-label"><?= lang("app.keep"); ?></label></div>
								<div class="divider row"></div>
								<div class="d-flex align-items-center">
									<div class="ml-auto"><a href="javascript:void(0);" class="btn-lg btn btn-link"><?= lang("app.recover"); ?></a>
										<button class="btn btn-primary btn-lg"><?= lang("app.loginDashboard"); ?></button>
									</div>
								</div>
								<label style="position: absolute;font-size: 10pt;bottom: 0;">IOTXAD <?=version;?></label>
								<label style="position: absolute;font-size: 10pt;bottom: 0;right:10px"><?= lang("app.poweredBy"); ?> <a href="http://www.bbdigitech.com" target="_blank" class="alert-link">BDS Ltd</a></label>
							</form>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
<script type="application/javascript" src="<?=base_url('assets/js/jquery-3.4.1.min.js');?>"></script>
<script type="application/javascript" src="<?=base_url('assets/js/parsley.min.js');?>"></script>
<script type="text/javascript">
	$(function () {
		$("form").parsley();
	})
</script>

<svg id="SvgjsSvg1001" width="2" height="0" xmlns="http://www.w3.org/2000/svg" version="1.1"
	 xlink="http://www.w3.org/1999/xlink" svgjs="http://svgjs.com/svgjs"
	 style="overflow: hidden; top: -100%; left: -100%; position: absolute; opacity: 0;">
	<defs id="SvgjsDefs1002"></defs>
	<polyline id="SvgjsPolyline1003" points="0,0"></polyline>
	<path id="SvgjsPath1004" d="M0 0 "></path>
</svg>
<div class="jvectormap-tip"></div>
</body>
</html>
