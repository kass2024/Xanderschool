<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta http-equiv="Content-Language" content="en">
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<title>IOTXAD - login</title>
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
				<div class="d-none d-lg-block col-lg-4">
					<div class="slider-light">
						<div class="slick-slider slick-initialized slick-dotted">
							<button class="slick-prev slick-arrow fa fa-chevron-left" aria-label="Previous" type="button" style="">
								Previous
							</button>
							<div class="slick-list draggable">
								<div class="slick-track"
									 style="opacity: 1; width: 3360px; transform: translate3d(-480px, 0px, 0px);">
									<div class="slick-slide slick-current slick-active" data-slick-index="-1" aria-hidden="true"
										 style="width: 480px;" tabindex="-1">
										<div>
											<div style="width: 100%; display: inline-block;">
												<div
													class="h-100 d-flex justify-content-center align-items-center bg-sunny-morning"
													tabindex="-1">
													<div class="slide-img-bg"
														 style="background-color: #e2c527"></div>
													<div class="slider-content"><h3>Smart, lightweight school</h3>
														<p>IOTXAD system include all required to driver modern school</p></div>
												</div>
											</div>
										</div>
									</div>
									<div class="slick-slide" data-slick-index="0"
										 aria-hidden="false" style="width: 480px;" role="tabpanel" id="slick-slide00">
										<div>
											<div style="width: 100%; display: inline-block;">
												<div
													class="h-100 d-flex justify-content-center align-items-center bg-plum-plate"
													tabindex="-1">
													<div class="slide-img-bg"
														 style="background-color: #16b643;"></div>
													<div class="slider-content"><h3>Perfect Features</h3>
														<p>IOTXAD perfect features</p></div>
												</div>
											</div>
										</div>
									</div>
									<div class="slick-slide" data-slick-index="1" aria-hidden="true"
										 style="width: 480px;" tabindex="-1" role="tabpanel" id="slick-slide01">
										<div>
											<div style="width: 100%; display: inline-block;">
												<div
													class="h-100 d-flex justify-content-center align-items-center bg-premium-dark"
													tabindex="-1">
													<div class="slide-img-bg"
														 style="background-color: #b6191a;"></div>
													<div class="slider-content"><h3>Consistent and efficiency</h3>
														<p>IOTXAD consistent features</p></div>
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>
							<button class="slick-next slick-arrow fa fa-chevron-right" aria-label="Next" type="button" style="">Next
							</button>
							<ul class="slick-dots" style="" role="tablist">
								<li class="slick-active" role="presentation">
									<button type="button" role="tab" id="slick-slide-control00"
											aria-controls="slick-slide00" aria-label="1 of 3" tabindex="0"
											aria-selected="true">1
									</button>
								</li>
								<li role="presentation">
									<button type="button" role="tab" id="slick-slide-control01"
											aria-controls="slick-slide01" aria-label="2 of 3" tabindex="-1">2
									</button>
								</li>
								<li role="presentation">
									<button type="button" role="tab" id="slick-slide-control02"
											aria-controls="slick-slide02" aria-label="3 of 3" tabindex="-1">3
									</button>
								</li>
							</ul>
						</div>
					</div>
				</div>
				<div class="h-100 d-flex bg-white justify-content-center align-items-center col-md-12 col-lg-8">
					<div class="mx-auto app-login-box col-sm-12 col-md-10 col-lg-9">
						<div class="app-logo"></div>
						<h4 class="mb-0">
							<span class="d-block">Welcome back,</span>
							<span>Please sign in to your account.</span></h4>
						</h6>
						<div class="divider row"></div>
						<div>
							<form class="" method="post" action="<?=base_url('login_pro');?>">
								<?php
								if (!empty($error)) {
									?>
									<div class="alert alert-danger">
										<label class="alert-heading">Login failed</label>
										<p><?= $error; ?></p>
									</div>
									<?php
								}
								?>
								<div class="form-row">
									<div class="col-md-6">
										<div class="position-relative form-group"><label for="username" class="">Username</label><input
												name="username" id="username" placeholder="Username goes here..." type="text"
												class="form-control" required minlength="4" value="<?=$username;?>"></div>
									</div>
									<div class="col-md-6">
										<div class="position-relative form-group"><label for="examplePassword" class="">Password</label><input
												name="password" id="examplePassword" placeholder="Password goes here..."
												type="password" class="form-control" required minlength="6"></div>
									</div>
								</div>
								<div class="position-relative form-check"><input name="check" id="exampleCheck"
																				 type="checkbox"
																				 class="form-check-input"><label
										for="exampleCheck" class="form-check-label">Keep me logged in</label></div>
								<div class="divider row"></div>
								<div class="d-flex align-items-center">
									<div class="ml-auto"><a href="javascript:void(0);" class="btn-lg btn btn-link">Recover
											Password</a>
										<button class="btn btn-primary btn-lg">Login to Dashboard</button>
									</div>
								</div>
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
