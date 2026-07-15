<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta http-equiv="Content-Language" content="en">
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<title>IOTXAD - <?= $title; ?></title>
	<meta name="viewport"
		  content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, shrink-to-fit=no">
	<meta name="description" content="School management system">
	<!--	<link rel="icon" href="https://qonics.com/assets/images/favicon.png">-->
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.11.2/css/all.css" rel="stylesheet">
	<link href="<?= base_url(); ?>assets/plugins/typicons/font/typicons.min.css" rel="stylesheet">
	<link rel="stylesheet" type="text/css" href="<?= base_url('assets/plugins/select2/css/select2.min.css'); ?>">

	<!-- Disable tap highlight on IE -->
	<meta name="msapplication-tap-highlight" content="no">

	<link href="<?= base_url(); ?>assets/css/main.css" rel="stylesheet">
	<link href="<?= base_url(); ?>assets/css/font-awesome-animation.min.css" rel="stylesheet">
	<script type="text/javascript" src="<?= base_url(); ?>assets/js/jquery-3.4.1.min.js"></script>
	<!-- Favicon -->
	<link rel="favorite icon" href="<?= base_url(); ?>assets/landing/images/favicon.jpeg">


</head>
<body cz-shortcut-listen="true" data-url="<?= base_url(); ?>">
<div class="app-container app-theme-gray app-sidebar-full">
	<div class="app-main">
		<div class="app-sidebar-wrapper">
			<div class="app-sidebar bg-asteroid sidebar-text-light">
				<div class="app-header__logo">
					<a href="#" data-toggle="tooltip" data-placement="bottom" title="" class="logo-src"
					   data-original-title="Somanet"></a>
					<button type="button" class="hamburger hamburger--elastic mobile-toggle-nav">
                                <span class="hamburger-box">
                                    <span class="hamburger-inner"></span>
                                </span>
					</button>
				</div>
				<div class="scrollbar-sidebar scrollbar-container ps ps--active-y">
					<div class="app-sidebar__inner">
						<ul class="vertical-nav-menu metismenu">
							<li class="app-sidebar__heading"><?= lang("app.menu"); ?></li>
							<li class="mm-active">
								<a href="<?= base_url('admin'); ?>" aria-expanded="true">
									<i class="metismenu-icon typcn typcn-th-large-outline"></i>
									<?= lang("app.dashboard"); ?>
								</a>
							</li>
							<li>
								<a href="#">
									<i class="metismenu-icon typcn typcn-user-outline"></i>
									<?= lang("app.schools"); ?>
									<i class="metismenu-state-icon fa fa-caret-down"></i>
								</a>
								<ul class="mm-collapse">
									<li>
										<a href="<?= base_url('add-school'); ?>">
											<i class="metismenu-icon"></i>
											<?= lang("app.registerNewSchool"); ?>
										</a>
									</li>
									<li>
										<a href="<?= base_url('schools'); ?>">
											<i class="metismenu-icon"></i>
											<?= lang("app.viewAllSchools"); ?>
										</a>
									</li>

								</ul>
							</li>
							<li>
								<a href="<?= base_url('packages'); ?>">
									<i class="metismenu-icon typcn typcn-group-outline"></i>
									<?= lang("app.packages"); ?>
								</a>
							</li>
							<li>
								<a href="<?= base_url('extra_sms'); ?>">
									<i class="metismenu-icon typcn typcn-group-outline"></i>
									<?= lang("app.extraSMS"); ?>
								</a>
							</li>
							<li>
								<a href="<?= base_url('users'); ?>">
									<i class="metismenu-icon typcn typcn-user-outline"></i>
									<?= lang("app.users"); ?>
								</a>
							</li>

						</ul>
					</div>
					<div class="ps__rail-x" style="left: 0px; bottom: 0px;">
						<div class="ps__thumb-x" tabindex="0" style="left: 0px; width: 0px;"></div>
					</div>
					<div class="ps__rail-y" style="top: 0px; height: 702px; right: 0px;">
						<div class="ps__thumb-y" tabindex="0" style="top: 0px; height: 384px;"></div>
					</div>
				</div>
			</div>
		</div>
		<div class="app-sidebar-overlay d-none animated fadeIn"></div>
		<div class="app-main__outer">
			<div class="app-main__inner">
				<div class="header-mobile-wrapper">
					<div class="app-header__logo">
						<a href="#" data-toggle="tooltip" data-placement="bottom" title="" class="logo-src"
						   data-original-title="KeroUI Admin Template"></a>
						<button type="button" class="hamburger hamburger--elastic mobile-toggle-sidebar-nav">
                                <span class="hamburger-box">
                                    <span class="hamburger-inner"></span>
                                </span>
						</button>
						<div class="app-header__menu">
                            <span>
                                <button type="button"
										class="btn-icon btn-icon-only btn btn-primary btn-sm mobile-toggle-header-nav">
                                    <span class="btn-icon-wrapper">
                                        <i class="fa fa-ellipsis-v fa-w-6"></i>
                                    </span>
                                </button>
                            </span>
						</div>
					</div>
				</div>
				<div class="slider">
					<div class="line"></div>
					<div class="subline inc"></div>
					<div class="subline dec"></div>
				</div>
				<div class="app-header">
					<div class="page-title-heading">
						<?= $title; ?>
						<div class="page-title-subheading">
							<?= $subtitle; ?>
						</div>
					</div>
					<div class="app-header-right">
						<div class="search-wrapper">
							<i class="search-icon-wrapper typcn typcn-zoom-outline"></i>
							<input type="text" placeholder="Search...">
						</div>
						<div class="header-btn-lg pr-0">
							<div class="header-dots">
								<div class="dropdown">
									<button type="button" aria-haspopup="true" aria-expanded="false"
											data-toggle="dropdown" class="p-0 btn btn-link">
										<i class="typcn typcn-bell"></i>
										<span class="badge badge-dot badge-dot-sm badge-danger"><?= lang("app.notifications"); ?></span>
									</button>
									<div tabindex="-1" role="menu" aria-hidden="true"
										 class="dropdown-menu-xl rm-pointers dropdown-menu dropdown-menu-right">
										<div class="dropdown-menu-header mb-0">
											<div class="dropdown-menu-header-inner bg-night-sky">
												<div class="menu-header-image opacity-5"></div>
												<div class="menu-header-content text-light">
													<h5 class="menu-header-title"><?= lang("app.notifications"); ?></h5>
													<h6 class="menu-header-subtitle"><?= lang("app.noUnread"); ?>
													</h6>
												</div>
											</div>
										</div>
										<ul class="tabs-animated-shadow tabs-animated nav nav-justified tabs-shadow-bordered p-3">
											<li class="nav-item">
												<a role="tab" class="nav-link active" data-toggle="tab"
												   href="#tab-messages-header">
													<span><?= lang("app.messages"); ?></span>
												</a>
											</li>
											<li class="nav-item">
												<a role="tab" class="nav-link" data-toggle="tab"
												   href="#tab-events-header">
													<span><?= lang("app.events"); ?></span>
												</a>
											</li>
											<li class="nav-item">
												<a role="tab" class="nav-link" data-toggle="tab"
												   href="#tab-errors-header">
													<span><?= lang("app.system"); ?></span>
												</a>
											</li>
										</ul>
										<div class="tab-content">
											<div class="tab-pane active" id="tab-messages-header" role="tabpanel">
												<div class="scroll-area-sm">
													<div class="scrollbar-container ps">
														<div class="p-3">
															<div class="notifications-box">
																<div
																		class="vertical-time-simple vertical-without-time vertical-timeline vertical-timeline--one-column">
																</div>
															</div>
														</div>
														<div class="ps__rail-x" style="left: 0px; bottom: 0px;">
															<div class="ps__thumb-x" tabindex="0"
																 style="left: 0px; width: 0px;"></div>
														</div>
														<div class="ps__rail-y" style="top: 0px; right: 0px;">
															<div class="ps__thumb-y" tabindex="0"
																 style="top: 0px; height: 0px;"></div>
														</div>
													</div>
												</div>
											</div>
											<div class="tab-pane" id="tab-events-header" role="tabpanel">
												<div class="scroll-area-sm">
													<div class="scrollbar-container ps">
														<div class="p-3">
															<div
																	class="vertical-without-time vertical-timeline vertical-timeline--animate vertical-timeline--one-column">
																<div
																		class="vertical-timeline-item vertical-timeline-element">
																	<div><span
																				class="vertical-timeline-element-icon bounce-in"><i
																					class="badge badge-dot badge-dot-xl badge-success"> </i></span>
																		<div
																				class="vertical-timeline-element-content bounce-in">
																			<h4 class="timeline-title"><?= lang("app.allHands"); ?></h4>
																			<p><?= lang("app.uyuMunsi"); ?><a
																						href="javascript:void(0);">4:00
																					PM</a></p><span
																					class="vertical-timeline-element-date"></span>
																		</div>
																	</div>
																</div>

															</div>
														</div>
														<div class="ps__rail-x" style="left: 0px; bottom: 0px;">
															<div class="ps__thumb-x" tabindex="0"
																 style="left: 0px; width: 0px;"></div>
														</div>
														<div class="ps__rail-y" style="top: 0px; right: 0px;">
															<div class="ps__thumb-y" tabindex="0"
																 style="top: 0px; height: 0px;"></div>
														</div>
													</div>
												</div>
											</div>
											<div class="tab-pane" id="tab-errors-header" role="tabpanel">
												<div class="scroll-area-sm">
													<div class="scrollbar-container ps">
														<div class="no-results pt-3 pb-0">
															<div
																	class="swal2-icon swal2-success swal2-animate-success-icon">
																<div class="swal2-success-circular-line-left"
																	 style="background-color: rgb(255, 255, 255);"></div>
																<span class="swal2-success-line-tip"></span>
																<span class="swal2-success-line-long"></span>
																<div class="swal2-success-ring"></div>
																<div class="swal2-success-fix"
																	 style="background-color: rgb(255, 255, 255);"></div>
																<div class="swal2-success-circular-line-right"
																	 style="background-color: rgb(255, 255, 255);"></div>
															</div>
															<div class="results-subtitle"><?= lang("app.allCaught"); ?></div>
															<div class="results-title"><?= lang("app.noSystem"); ?></div>
														</div>
														<div class="ps__rail-x" style="left: 0px; bottom: 0px;">
															<div class="ps__thumb-x" tabindex="0"
																 style="left: 0px; width: 0px;"></div>
														</div>
														<div class="ps__rail-y" style="top: 0px; right: 0px;">
															<div class="ps__thumb-y" tabindex="0"
																 style="top: 0px; height: 0px;"></div>
														</div>
													</div>
												</div>
											</div>
										</div>
										<ul class="nav flex-column">
											<li class="nav-item-divider nav-item"></li>
											<li class="nav-item-btn text-center nav-item">
												<button class="btn-shadow btn-pill btn btn-default btn-sm"><?= lang("app.cleaAll"); ?>
												</button>
											</li>
										</ul>
									</div>
								</div>
							</div>
						</div>
						<div class="header-btn-lg pr-0">
							<div class="widget-content p-0">
								<div class="widget-content-wrapper">
									<div class="widget-content-left">
										<div class="btn-group">
											<a data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"
											   class="p-0 btn">
												<img class="rounded" src="<?= base_url(); ?>assets/images/no_image.jpg"
													 alt="" width="42">
												<i class="fa fa-angle-down ml-2 opacity-8"></i>
											</a>
											<div tabindex="-1" role="menu" aria-hidden="true"
												 class="rm-pointers dropdown-menu-lg dropdown-menu dropdown-menu-right">
												<div class="dropdown-menu-header">
													<div class="dropdown-menu-header-inner bg-info">
														<div class="menu-header-image opacity-2"></div>
														<div class="menu-header-content text-left">
															<div class="widget-content p-0">
																<div class="widget-content-wrapper">
																	<div class="widget-content-left mr-3">
																		<img class="rounded-circle"
																			 src="<?= base_url(); ?>assets/images/no_image.jpg"
																			 alt=""
																			 width="42">
																	</div>
																	<div class="widget-content-left">
																		<div
																				class="widget-heading"><?= $_SESSION['soma_admin_name']; ?>
																		</div>
																		<div
																				class="widget-subheading opacity-8">
																			<?= lang("app.administrator"); ?>
																		</div>
																	</div>
																	<div class="widget-content-right mr-2">
																		<a href="<?= base_url('logout'); ?>"
																		   class="btn-pill btn-shadow btn-shine btn btn-focus">
																			<?= lang("app.logout"); ?>
																		</a>
																	</div>
																</div>
															</div>
														</div>
													</div>
												</div>
												<div class="scroll-area-xs" style="height: 150px;">
													<div class="scrollbar-container ps">
														<ul class="nav flex-column">
															<li class="nav-item-header nav-item"><?= lang("app.activity"); ?>
															</li>

															<li class="nav-item">
																<a href="javascript:void(0);" class="nav-link"
																   data-toggle="modal"
																   data-target="#mdlPass"><?= lang("app.changePassword"); ?>
																</a>
															</li>

														</ul>
														<div class="ps__rail-x" style="left: 0px; bottom: 0px;">
															<div class="ps__thumb-x" tabindex="0"
																 style="left: 0px; width: 0px;"></div>
														</div>
														<div class="ps__rail-y" style="top: 0px; right: 0px;">
															<div class="ps__thumb-y" tabindex="0"
																 style="top: 0px; height: 0px;"></div>
														</div>
													</div>
												</div>
												<ul class="nav flex-column">
													<li class="nav-item-divider mb-0 nav-item"></li>
												</ul>

											</div>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
					<div class="app-header-overlay d-none animated fadeIn"></div>
				</div>

				<?= $content; ?>
			</div>
			<div class="app-wrapper-footer">
				<div class="app-footer">
					<div class="">
						<div class="app-footer__inner">
							<div class="app-footer-left">
								<label><?= lang("app.poweredBy"); ?> <a href="http://www.bbdigitech.com" target="_blank"
																		class="alert-link">BDS Ltd</a></label>
							</div>
							<div class="app-footer-right">
								<ul class="header-megamenu nav">
									<li class="nav-item">
										<a data-placement="top" rel="popover-focus" data-offset="300"
										   data-toggle="popover-custom" class="nav-link" data-original-title=""
										   title="">
											IOTXAD <?= version; ?>
										</a>
										<div class="rm-max-width rm-pointers">
											<div class="d-none popover-custom-content">
												<div class="dropdown-mega-menu dropdown-mega-menu-sm">
													<div class="grid-menu grid-menu-2col">
														<div class="no-gutters row">
															<div class="col-sm-6 col-xl-6">
																<p>IOTXAD is complete school management software ... to
																	be added later</p>
															</div>
															<div class="col-sm-6 col-xl-6">
																<ul class="nav flex-column">
																	<li class="nav-item-header nav-item">Useful links
																	</li>
																	<li class="nav-item"><a href="javascript:void(0);"
																							class="nav-link">BDS</a>
																	</li>

																</ul>
															</div>
														</div>
													</div>
												</div>
											</div>
										</div>
									</li>
								</ul>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
<?php if ($page == "schools") { ?>

	<div class="modal fade" id="changeScklpackage" tabindex="-1" role="dialog">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<form action="<?= base_url('admin/changeSchoolPackge'); ?>" class="autoSubmit validate">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalLabel">change school package</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">×</span>
						</button>
					</div>
					<div class="modal-body">
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">

							<div class="form-group">
								<label>Package Name</label>
								<input class="form-control" type="hidden" name="fId">
								<select class="select2" name="package">
									<?php foreach ($packages as $package) { ?>
										<option value="<?= $package['id']; ?>"><?= $package['title']; ?></option>
										<?php
									}
									?>
								</select>
							</div>
						</div>
					</div>
					<div class="modal-footer">
						<label style="position: absolute;left: 20px;">Close this if you are done</label>
						<button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
						<button type="submit" class="btn btn-primary" data-target="reload">Save changes</button>
					</div>
				</form>
			</div>
		</div>
	</div>
	<?php
}
?>
<div class="modal fade" id="mdlpkg" tabindex="-1" role="dialog">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<form action="<?= base_url('admin/manipulate_package'); ?>" class="autoSubmit validate">
				<div class="modal-header">
					<h5 class="modal-title" id="exampleModalLabel">Create new package</h5>
					<button type="button" class="close" data-dismiss="modal" aria-label="Close">
						<span aria-hidden="true">×</span>
					</button>
				</div>
				<div class="modal-body">
					<div class="col-sm-12 col-md-12 col-lg-12 pull-left">

						<div class="form-group">
							<label>Package Name</label>
							<input class="form-control" type="text" name="name" required minlength="3">
							<input class="form-control" type="hidden" name="fId">
						</div>
						<div class="form-group">
							<label>SMS Per trimester</label>
							<input class="form-control" type="text" data-parsley-type="number" name="sms" required
								   minlength="2">
						</div>

					</div>
				</div>
				<div class="modal-footer">
					<label style="position: absolute;left: 20px;">Close this if you are done</label>
					<button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
					<button type="submit" class="btn btn-primary" data-target="reload">Save changes</button>
				</div>
			</form>
		</div>
	</div>
</div>
<?php
if ($page == 'extra_sms'):
	?>
	<div class="modal fade" id="mdlSms" tabindex="-1" role="dialog">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<form action="<?= base_url('admin/manipulate_extra_sms'); ?>" method="post" class="autoSubmit validate">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalLabel">Give extra SMS</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">×</span>
						</button>
					</div>
					<div class="modal-body">
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">

							<div class="form-group">
								<label>School:</label>
								<select class="select2" name="sid">
									<option disabled selected>Please select school</option>
									<?php foreach ($schools as $school) { ?>
										<option value="<?= $school['id']; ?>"><?= $school['name']; ?></option>
										<?php
									}
									?>
								</select>
							</div>
							<div class="form-group">
								<label>Given SMS</label>
								<input class="form-control" type="text" data-parsley-type="number" name="sms" required
									   minlength="2">
							</div>

						</div>
					</div>
					<div class="modal-footer">
						<label style="position: absolute;left: 20px;color:orangered">Please make sure that everything <br>is correct because no way back</label>
						<button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
						<button type="submit" class="btn btn-primary" data-target="reload">Save changes</button>
					</div>
				</form>
			</div>
		</div>
	</div>
<?php
endif;
if ($page == 'users'):
	?>
	<div class="modal fade" id="mdlUser" tabindex="-1" role="dialog">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<form action="<?= base_url('admin/manipulate_user'); ?>" class="autoSubmit validate">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalLabel">Create new User</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">×</span>
						</button>
					</div>
					<div class="modal-body">
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">

							<div class="form-group">
								<label>Name</label>
								<input class="form-control" type="text" name="name" required minlength="3">
							</div>
							<div class="form-group">
								<label>Email</label>
								<input class="form-control" type="email" name="email" required
									   minlength="3">
							</div>

						</div>
					</div>
					<div class="modal-footer">
						<label style="position: absolute;left: 20px;">Close this if you are done</label>
						<button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
						<button type="submit" class="btn btn-primary">Save changes</button>
					</div>
				</form>
			</div>
		</div>
	</div>
<?php
endif;
?>
<div class="modal fade" id="mdlPass" tabindex="-1" role="dialog">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<form action="<?= base_url('admin/change_password'); ?>" class="autoSubmit validate">
				<div class="modal-header">
					<h5 class="modal-title" id="exampleModalLabel">Change password</h5>
					<button type="button" class="close" data-dismiss="modal" aria-label="Close">
						<span aria-hidden="true">×</span>
					</button>
				</div>
				<div class="modal-body">
					<div class="col-sm-12 col-md-12 col-lg-12 pull-left">

						<div class="form-group">
							<label>Current password</label>
							<input class="form-control" type="password" name="current_password" required minlength="6">
						</div>
						<div class="form-group">
							<label>New password</label>
							<input class="form-control" type="password" name="password" id="password" required
								   minlength="6">
						</div>
						<div class="form-group">
							<label>Confirm password</label>
							<input class="form-control" type="password" data-parsley-equalto="#password" required
								   minlength="6" data-parsley-equalto-message="Password not match">
						</div>

					</div>
				</div>
				<div class="modal-footer">
					<label style="position: absolute;left: 20px;">Close this if you are done</label>
					<button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
					<button type="submit" class="btn btn-primary" data-target="#mdlPass">Change password</button>
				</div>
			</form>
		</div>
	</div>
</div>
<div class="app-drawer-overlay d-none animated fadeIn"></div>
<script type="text/javascript" src="<?= base_url(); ?>assets/plugins/bootstrap/bootstrap.min.js"></script>
<script type="text/javascript" src="<?= base_url(); ?>assets/js/main.js"></script>
<script type="text/javascript" src="<?= base_url(); ?>assets/js/toast.js"></script>
<script type="application/javascript" src="<?= base_url('assets/js/parsley.min.js'); ?>"></script>
<script type="application/javascript" src="<?= base_url('assets/plugins/select2/js/select2.min.js'); ?>"></script>
<script src="<?= base_url('assets/js/inputmask.bundle.min.js'); ?>"></script>
<script type="text/javascript" src="<?= base_url(); ?>assets/js/scripts_v1.1.1.js"></script>
<script src="<?= base_url(); ?>assets/js/Chart.js"></script>
<script src="<?= base_url(); ?>assets/js/Chart.min.js"></script>
<script src="<?= base_url(); ?>assets/js/jquery.flot.js"></script>
<script src="<?= base_url(); ?>assets/js/jquery.flot.pie.js"></script>
<script>
	$(function () {
		<?php
		if ($_SESSION['soma_admin_status'] == 2) {
			echo "$('#mdlPass').modal('show');";
		}
		?>

		$("#mdlpkg").on("show.bs.modal", function (e) {
			var id = $(e.relatedTarget).data("id");
			$.getJSON("<?=base_url();?>get-single-package/" + id, function (data) {
				$("#mdlpkg [name='fId']").val(data.id).change();
				$("#mdlpkg [name='name']").val(data.title).change();
				$("#mdlpkg [name='sms']").val(data.sms_limit).change();
			});
			return;
		});
		$("#changeScklpackage").on("show.bs.modal", function (e) {
			var id = $(e.relatedTarget).data("id");
			$.getJSON("<?=base_url();?>get-skl-package/" + id, function (data) {
				$("#changeScklpackage [name='fId']").val(data.id).change();
				$("#changeScklpackage [name='package']").val(data.package).trigger('change');

			});
			return;
		});
	});
</script>
</body>
</html>
