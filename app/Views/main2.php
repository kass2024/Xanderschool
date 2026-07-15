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
	<script type="text/javascript" src="<?= base_url(); ?>assets/js/jquery-3.4.1.min.js"></script>
	<!-- Favicon -->
	<link rel="favorite icon" href="<?= base_url(); ?>assets/landing/images/about.png">
	<style>
		.dataTables_filter input[type='search'] {
			border: 1px solid #cdcdcd
		}

		.show-staff {
			display: none;
		}

		#academic-creation-overlay {
			background-color: #0c0c0c8f;
			position: absolute;
			height: 150%;
			width: 117%;
			left: -40px;
			z-index: 2;
			top: 125px;
			color: wheat;
			text-align: center;
			font-size: 13pt;
		}

		#mdlTerm .modal-content {
			overflow: hidden !important;
		}
	</style>
</head>
<body cz-shortcut-listen="true" data-url="<?= base_url(); ?>">
<a id="anchorID" href="#" target="_blank"></a>
<div class="app-container app-theme-gray app-sidebar-full">
	<div class="app-main">
		<div class="app-sidebar-wrapper">
			<div class="app-sidebar bg-danger sidebar-text-light">
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
								<a href="<?= base_url('dashboard'); ?>" aria-expanded="true">
									<i class="metismenu-icon typcn typcn-th-large-outline"></i>
									<?= lang("app.dashboard"); ?>
								</a>
							</li>
							<?php
							if (!is_blocked(2)) {
								?>
								<li>
									<a href="#">
										<i class="metismenu-icon typcn typcn-user-outline"></i>
										<?= lang("app.students"); ?>
										<i class="metismenu-state-icon fa fa-caret-down"></i>
									</a>
									<ul class="mm-collapse">
										<li>
											<a href="<?= base_url('pendingRegistrations'); ?>">
												<i class="metismenu-icon typcn typcn-group-outline"></i>
												<?= lang("app.pendingRegistration"); ?>
											</a>
										</li>
										<li>
											<a href="<?= base_url('register-student'); ?>">
												<i class="metismenu-icon"></i>
												<?= lang("app.newStudent"); ?>
											</a>
										</li>
										<li>
											<a href="<?= base_url('students'); ?>">
												<i class="metismenu-icon"></i>
												<?= lang("app.viewStudents"); ?>
											</a>
										</li>
										<li>
											<a href="<?= base_url('dismissedStudent'); ?>">
												<i class="metismenu-icon"></i>
												<?= lang("app.DismissedStudents"); ?>
											</a>
										</li>
										<li>
											<a href="<?= base_url('student-cards'); ?>">
												<i class="metismenu-icon"></i>
												<?= lang("app.studentCards"); ?>
											</a>
										</li>
										<li>
											<a href="<?= base_url('student-photo'); ?>">
												<i class="metismenu-icon"></i>
												Student Photo
											</a>
										</li>
									</ul>
								</li>
								<?php
							}
							?>
							<?php
							if (is_allowed(1, 3)) {
								?>
								<li>
									<a href="<?= base_url('classes'); ?>">
										<i class="metismenu-icon typcn typcn-home-outline"></i>
										<?= lang("app.classes"); ?>
									</a>
								</li>
								<?php
							}
							?>
							<?php
							if (!is_blocked(2, 4, 5, 6)) {
								?>
								<li>
									<a href="#">
										<i class="metismenu-icon typcn typcn-user-outline"></i>
										<?= lang("app.course"); ?>
										<i class="metismenu-state-icon fa fa-caret-down"></i>
									</a>
									<ul class="mm-collapse">
										<li>
											<a href="<?= base_url('course-category'); ?>">
												<i class="metismenu-icon"></i>
												<?= lang("app.courseCategory"); ?>
											</a>
										</li>
										<li>
											<a href="<?= base_url('add_course'); ?>">
												<i class="metismenu-icon"></i>
												<?= lang("app.createCourse"); ?>
											</a>
										</li>
										<li>
											<a href="<?= base_url('manage_courses'); ?>">
												<i class="metismenu-icon"></i>
												<?= lang("app.manageCourse"); ?>
											</a>
										</li>

									</ul>
								</li>
								<?php
							}
							?>
							<li>
								<a href="#">
									<i class="metismenu-icon typcn typcn-user-outline"></i>
									<?= lang("app.discipline"); ?>
									<i class="metismenu-state-icon fa fa-caret-down"></i>
								</a>
								<ul class="mm-collapse">
									<li>
										<a href="<?= base_url('discipline_record_entry'); ?>">
											<i class="metismenu-icon"></i>
											<?= lang("app.recordEntry"); ?>
										</a>
									</li>
									<li>
										<a href="<?= base_url('completedDisciplineMarks'); ?>">
											<i class="metismenu-icon"></i>
											<?= lang("app.completedDisciplineMarks"); ?>
										</a>
									</li>
									<li>
										<a href="<?= base_url('discipline_record'); ?>">
											<i class="metismenu-icon"></i>
											<?= lang("app.disciplineRecord"); ?>
										</a>
									</li>
								</ul>
							</li>
							<?php
							if (is_allowed(1, 3, 4, 5, 6)) {
								?>
								<li>
									<a href="#">
										<i class="metismenu-icon typcn typcn-film"></i>
										<?= lang("app.permissions"); ?>
										<i class="metismenu-state-icon fa fa-caret-down"></i>
									</a>
									<ul class="mm-collapse fa-com">
										<li>
											<a href="<?= base_url('permission_entry'); ?>">
												<i class="metismenu-icon"></i>
												<?= lang("app.permissionEntry"); ?>
											</a>
										</li>
										<li>
											<a href="<?= base_url('permission_report'); ?>">
												<i class="metismenu-icon"></i>
												<?= lang("app.permissionReport"); ?>
											</a>
										</li>

									</ul>
								</li>
								<?php
							}
							?>
							<li class="app-sidebar__heading"><?= lang("app.marks"); ?></li>
							<li>
								<a href="#">
									<i class="metismenu-icon typcn typcn-document"></i>
									<?= lang("app.marks"); ?>
									<i class="metismenu-state-icon fa fa-caret-down"></i>
								</a>
								<ul class="mm-collapse fa-com hidden">
									<li>
										<a href="#" data-toggle="modal" data-target="#mdlmarks">
											<i class="metismenu-icon"></i>
											<?= lang("app.marksEntry"); ?>
										</a>
									</li>
									<?php
									if (is_allowed(1, 3)) {
										?>
										<li>
											<a href="<?= base_url('get_uploaded_marks');?>">
												<i class="metismenu-icon"></i>
												<?= lang("app.marksList"); ?>
											</a>
										</li>
										<li>
											<a href="<?= base_url('student_report'); ?>">
												<i class="metismenu-icon typcn typcn-user-outline"></i>
												<?= lang("app.progressReports"); ?>
											</a>
										</li>
										<li>
											<a href="<?= base_url('get_periodic_report'); ?>">
												<i class="metismenu-icon"></i>
												Periodic report
											</a>
										</li>
										<li>
											<a href="javascript:void">
												<i class="metismenu-icon typcn typcn-group-outline"></i>
												<?= lang("app.parmaless"); ?>
												<i class="metismenu-state-icon fa fa-caret-down"></i>
											</a>

											<ul class="mm-collapse">
												<li>
													<a href="<?= base_url('get_periodic_marks'); ?>">
														<i class="metismenu-icon"></i>
														<?= lang("app.periodicResult"); ?>
													</a>
												</li>
												<li>
													<a href="<?= base_url('proclamation_list'); ?>">
														<i class="metismenu-icon"></i>
														<?= lang("app.proclamationList"); ?>
													</a>
												</li>
												<li>
													<a href="<?= base_url('student_term_results'); ?>">
														<i class="metismenu-icon"></i>
														<?= lang("app.termProclamationList"); ?>
													</a>
												</li>

											</ul>
										</li>
										<li>
											<a href="javascript:void">
												<i class="metismenu-icon typcn typcn-group-outline"></i>
												<?= lang("app.deliberation"); ?>
												<i class="metismenu-state-icon fa fa-caret-down"></i>
											</a>

											<ul class="mm-collapse">
												<li>
													<a href="<?= base_url('class-deliberation'); ?>">
														<i class="metismenu-icon"></i>
														<?= lang("app.deliberation"); ?>
													</a>
												</li>
												<li>
													<a href="<?= base_url('finish_deliberation'); ?>">
														<i class="metismenu-icon"></i>
														<?= lang("app.finishDeliberation"); ?>
													</a>
												</li>
												<li>
													<a href="<?= base_url('deliberation_settings'); ?>">
														<i class="metismenu-icon"></i>
														<?= lang("app.deliberationSettings"); ?>
													</a>
												</li>
											</ul>
										</li>
										<?php
									}
									?>
								</ul>
							</li>

							<?php
							if (is_allowed(1, 3, 4, 5, 6)) {
								?>
								<li class="app-sidebar__heading"><?= lang("app.messaging"); ?></li>
								<li>
									<a href="javascript:void">
										<i class="metismenu-icon typcn typcn-group-outline"></i>
										<?= lang("app.messaging"); ?>
										<i class="metismenu-state-icon fa fa-caret-down"></i>
									</a>
									<ul class="mm-collapse">
										<li>
											<a href="<?= base_url('messaging/parents'); ?>">
												<i class="metismenu-icon"></i>
												<?= lang("app.parentsMessaging"); ?>
											</a>
										</li>
										<li>
											<a href="<?= base_url('messaging/employees'); ?>">
												<i class="metismenu-icon"></i>
												<?= lang("app.employeesMessaging"); ?>
											</a>
										</li>
									</ul>
								</li>
								<?php
							}
							?>
							<?php
							if (!is_blocked(2, 5, 6)) {
								?>
								<li class="app-sidebar__heading"><?= lang("app.attendance"); ?></li>
								<li>
									<a href="javascript:void">
										<i class="metismenu-icon typcn typcn-group-outline"></i>
										<?= lang("app.studentAttendance"); ?>
										<i class="metismenu-state-icon fa fa-caret-down"></i>
									</a>
									<ul class="mm-collapse">
										<li>
											<a href="javascript:void" data-target="#attendanceMdl" data-toggle="modal">
												<i class="fa fa-plus"></i>
												<?= lang("app.recordAttendance"); ?>
											</a>
										</li>
										<li>
											<a href="<?= base_url('student-report/inout/monthly'); ?>">
												<i class="metismenu-icon"></i>
												<?= lang("app.studentInOut"); ?>
											</a>
										</li>
										<li>
											<a href="<?= base_url('student-report/course/monthly/'); ?>">
												<i class="metismenu-icon"></i>
												<?= lang("app.studentCourse"); ?>
											</a>
										</li>
										<li style="display: none">
											<a href="<?= base_url('student-report/course/summary/'); ?>">
												<i class="metismenu-icon"></i>
												<?= lang("app.courseSummary"); ?>
											</a>
										</li>
										<li>
											<a href="<?= base_url('student-report/daily/class'); ?>">
												<i class="metismenu-icon"></i>
												<?= lang("app.studentDailyAttendance"); ?>
											</a>
										</li>
										<li>
											<a href="<?= base_url('student-report/daily/all'); ?>">
												<i class="metismenu-icon"></i>
												<?= lang("app.dailyAttendance"); ?>
											</a>
										</li>
										<li>
											<a href="<?= base_url('student-report/daily/details'); ?>">
												<i class="metismenu-icon"></i>
												<?= lang("app.dailyGeneralAttendance"); ?>
											</a>
										</li>
									</ul>
								</li>
								<li>
									<a href="javascript:void">
										<i class="metismenu-icon typcn typcn-group-outline"></i>
										<?= lang("app.staffAttendance"); ?>
										<i class="metismenu-state-icon fa fa-caret-down"></i>
									</a>
									<ul class="mm-collapse">
										<li>
											<a href="<?= base_url('staff-report/monthly'); ?>">
												<i class="metismenu-icon"></i>
												<?= lang("app.monthlyReport"); ?>
											</a>
										</li>
										<li>
											<a href="<?= base_url('staff-report/individual'); ?>">
												<i class="metismenu-icon"></i>
												<?= lang("app.individualReport"); ?>
											</a>
										</li>
									</ul>
								</li>
								<?php
							}
							?>

							<?php
							if (is_allowed(1, 3)) {
								?>
								<li class="app-sidebar__heading"><?= lang("app.hrDept"); ?></li>
								<li>
									<a href="#">
										<i class="metismenu-icon typcn typcn-group-outline"></i>
										<?= lang("app.staffs"); ?>
										<i class="metismenu-state-icon fa fa-caret-down"></i>
									</a>
									<ul class="mm-collapse">
										<li>
											<a href="javascript:void" data-toggle="modal" data-target="#mdlStaff">
												<i class="metismenu-icon"></i>
												<?= lang("app.addStaff"); ?>
											</a>
										</li>
										<li>
											<a href="<?= base_url('staffs'); ?>">
												<i class="metismenu-icon"></i>
												<?= lang("app.allStaffs"); ?>
											</a>
										</li>
										<li>
											<a href="<?= base_url('staff-cards'); ?>">
												<i class="metismenu-icon"></i>
												<?= lang("app.staffCards"); ?>
											</a>
										</li>
										<li>
											<a href="<?= base_url('shifts'); ?>">
												<i class="metismenu-icon"></i>
												<?= lang("app.staffShifts"); ?>
											</a>
										</li>
									</ul>
								</li>
								<?php
							}
							?>
							<?php
							if (is_allowed(1, 9, 3)) {
								?>
								<li class="app-sidebar__heading"><?= lang("app.feesManagement"); ?></li>
								<li>
									<a href="javascript:void">
										<i class="metismenu-icon typcn typcn-group-outline"></i>
										<?= lang("app.feesManagement"); ?>
										<i class="metismenu-state-icon fa fa-caret-down"></i>
									</a>

									<ul class="mm-collapse">
										<li>
											<a href="<?= base_url('fees_entry'); ?>">
												<i class="metismenu-icon"></i>
												<?= lang("app.feesEntry"); ?>
											</a>
										</li>
										<li>
											<a href="<?= base_url('school_fees_management'); ?>">
												<i class="metismenu-icon"></i>
												<?= lang("app.schoolFeesManagement"); ?>
											</a>
										</li>
										<li>
											<a href="<?= base_url('extra_fees_management'); ?>">
												<i class="metismenu-icon"></i>
												<?= lang("app.extraFeesManagement"); ?>
											</a>
										</li>
										<li>
											<a href="<?= base_url('transport_fees_management'); ?>">
												<i class="metismenu-icon"></i>
												<?= lang("app.transportFeesManagement"); ?>
											</a>
										</li>
										<li>
											<a href="<?= base_url('finance_records'); ?>">
												<i class="metismenu-icon"></i>
												Self service transactions
											</a>
										</li>
										<li>
											<a href="<?= base_url('system-report/fees'); ?>">
												<i class="metismenu-icon"></i>
												<?= lang("app.feesReport"); ?>
											</a>
										</li>
									</ul>
								</li>
								<?php
							}
							if (is_allowed(1, 7, 13, 3)) {
								?>
								<li class="app-sidebar__heading"><?= lang("app.libraryManagement"); ?></li>
								<li>
									<a href="javascript:void">
										<i class="metismenu-icon typcn typcn-group-outline"></i>
										<?= lang("app.libraryManagement"); ?>
										<i class="metismenu-state-icon fa fa-caret-down"></i>
									</a>

									<ul class="mm-collapse">

										<li>
											<a href="<?= base_url('book_management'); ?>">
												<i class="metismenu-icon"></i>
												<?= lang("app.booksManagement"); ?>
											</a>
										</li>
										<li>
											<a href="<?= base_url('borrowed_report'); ?>">
												<i class="metismenu-icon"></i>
												<?= lang("app.borrowedReport"); ?>
											</a>
										</li>
									</ul>
								</li>
								<?php
							}

							if (is_allowed(1, 5, 6, 7, 3)) {
								?>
								<li class="app-sidebar__heading"><?= lang("app.transportManagement"); ?></li>
								<li>
									<a href="javascript:void">
										<i class="metismenu-icon typcn typcn-group-outline"></i>
										<?= lang("app.transportManagement"); ?>
										<i class="metismenu-state-icon fa fa-caret-down"></i>
									</a>

									<ul class="mm-collapse">

										<li>
											<a href="<?= base_url('bus_management'); ?>">
												<i class="metismenu-icon"></i>
												<?= lang("app.busManagement"); ?>
											</a>
										</li>
										<li>
											<a href="<?= base_url('route_management'); ?>">
												<i class="metismenu-icon"></i>
												<?= lang("app.routeManagement"); ?>
											</a>
										</li>
									</ul>
								</li>
								<?php
							}
							if (is_allowed(1, 3, 14)) {
								?>
								<li class="app-sidebar__heading"><?= lang("app.PocketMoney"); ?></li>
								<li>
									<a href="<?= base_url('pocket_money'); ?>">
										<i class="metismenu-icon typcn typcn-group-outline"></i>
										<?= lang("app.PocketMoney"); ?>
									</a>
								</li>
								<?php
							}
							?>
							<li>
								<a href="<?= base_url('leave_application'); ?>">
									<i class="metismenu-icon typcn typcn-chart-pie-outline"></i>
									<?= lang("app.leaveApplication"); ?>
								</a>
							</li>
							<?php
							if (is_allowed(1, 3)) {
								?>
								<li>
									<a href="<?= base_url('leave_management'); ?>">
										<i class="metismenu-icon typcn typcn-chart-pie-outline"></i>
										<?= lang("app.leaveManagement"); ?>
									</a>
								</li>
								<?php
							}
							?>
							<!--
							<li class="app-sidebar__heading">MISCELLANIOUS</li>
							<li>
								<a href="#">
									<i class="metismenu-icon typcn typcn-document-add"></i>
									Notice board management
								</a>
								<a href="#">
									<i class="metismenu-icon typcn typcn-social-at-circular"></i>
									Event management
								</a>

							</li>
							-->
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
						   data-original-title="IOTXAD"></a>
						<button type="button" class="hamburger hamburger--elastic mobile-toggle-sidebar-nav">
                                <span class="hamburger-box">
                                    <span class="hamburger-inner"></span>
                                </span>
						</button>
						<div class="app-header__menu">
                            <span>
                                <button type="button"
										class="btn-icon btn-icon-only btn btn-gradient-primary btn-sm mobile-toggle-header-nav">
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
					<div style="display: flex;flex-direction: column;margin: 0 auto;text-align: center">
						<h5 style="font-weight: bold"><?= $school_name; ?></h5>
						<h5><?= $academic_year_title; ?> - Term <?= $term; ?></h5>
					</div>
					<div class="app-header-right">
						<div class="search-wrapper">
							<i class="search-icon-wrapper typcn typcn-zoom-outline"></i>
							<input type="text" placeholder="<?= lang("app.search"); ?>">
						</div>
						<div class="header-btn-lg pr-0">
							<div class="header-dots">
								<div class="dropdown">
									<button type="button" aria-haspopup="true" aria-expanded="false"
											data-toggle="dropdown" class="p-0 btn btn-link">
										<i class="typcn typcn-bell"></i>
										<span
												class="badge badge-dot badge-dot-sm badge-danger"><?= lang("app.notifications"); ?></span>
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
															<div
																	class="results-subtitle"><?= lang("app.allCaught"); ?></div>
															<div
																	class="results-title"><?= lang("app.noSystem"); ?></div>
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
												<button
														class="btn-shadow btn-pill btn btn-default btn-sm"><?= lang("app.cleaAll"); ?>
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
												<img class="rounded"
													 src="<?= base_url('assets/images/profile/' . $_SESSION['soma_picture']); ?>"
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
																			 src="<?= base_url('assets/images/profile/' . $_SESSION['soma_picture']); ?>"
																			 alt=""
																			 width="42">
																	</div>
																	<div class="widget-content-left">
																		<div
																				class="widget-heading"><?= $_SESSION['soma_name']; ?>
																		</div>
																		<div
																				class="widget-subheading opacity-8"><?= $_SESSION['soma_post_title']; ?>
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
															<?php
															if (is_allowed(1, 3)) {
																?>
																<li class="nav-item">
																	<a href="<?= base_url('settings'); ?>"
																	   class="nav-link"><i class="fa fa-cog"></i>&nbsp;&nbsp;
																		<?= lang("app.settings"); ?>
																	</a>
																</li>
																<?php
															}
															?>
															<li class="nav-item">
																<a href="<?= base_url('profile'); ?>"
																   class="nav-link"><i class="fa fa-user-alt"></i>&nbsp;&nbsp;
																	<?= lang("app.myProfile"); ?>
																</a>
															</li>
															<?php
															if (is_allowed(1, 3)) {
																?>
																<li class="nav-item">
																	<a href="javascript:void(0);" class="nav-link"
																	   data-toggle="modal"
																	   data-target="#mdlTerm"><i
																				class="fa fa-angle-right"></i>&nbsp;&nbsp;
																		<?= lang("app.changeActiveTerm"); ?>
																	</a>
																</li>
																<?php
															}
															?>
															<li class="nav-item">
																<a href="javascript:void(0);" class="nav-link"
																   data-toggle="modal"
																   data-target="#mdlPass"><i class="fa fa-lock"></i>&nbsp;&nbsp;
																	<?= lang("app.changePassword"); ?>
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
								<label><?= lang("app.poweredBy"); ?><a href="http://www.bbdigitech.com" target="_blank"
																	   class="alert-link">BDS Ltd</a></label>
							</div>
							<div class="app-footer-right">
								<ul class="header-megamenu nav">
									<li class="nav-item">
										<a data-placement="top" rel="popover-focus" data-offset="300"
										   data-toggle="popover-custom" class="nav-link" data-original-title=""
										   title="">
											Somanet <?= version; ?>
										</a>
										<div class="rm-max-width rm-pointers">
											<div class="d-none popover-custom-content">
												<div class="dropdown-mega-menu dropdown-mega-menu-sm">
													<div class="grid-menu grid-menu-2col">
														<div class="no-gutters row">
															<div class="col-sm-6 col-xl-6">
																<p>Somanet is complete school management software ... to
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
<div class="modal fade" id="mdlPass" tabindex="-1" role="dialog">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<form action="<?= base_url('change_password'); ?>" class="autoSubmit validate">
				<div class="modal-header">
					<h5 class="modal-title" id="exampleModalLabel"><?= lang("app.changePassword"); ?></h5>
					<button type="button" class="close" data-dismiss="modal" aria-label="Close">
						<span aria-hidden="true">×</span>
					</button>
				</div>
				<div class="modal-body">
					<div class="col-sm-12 col-md-12 col-lg-12 pull-left">

						<div class="form-group">
							<label><?= lang("app.currentPassword"); ?></label>
							<input class="form-control" type="password" name="current_password" required minlength="6">
						</div>
						<div class="form-group">
							<label><?= lang("app.newPassword"); ?></label>
							<input class="form-control" type="password" name="password" id="password" required
								   minlength="6">
						</div>
						<div class="form-group">
							<label><?= lang("app.confirmPassword"); ?></label>
							<input class="form-control" type="password" data-parsley-equalto="#password" required
								   minlength="6" data-parsley-equalto-message="<?= lang("app.passwordNotMatch"); ?>">
						</div>

					</div>
				</div>
				<div class="modal-footer">
					<label style="position: absolute;left: 20px;"><?= lang("app.youAreDone"); ?></label>
					<button type="button" class="btn btn-secondary"
							data-dismiss="modal"><?= lang("app.close"); ?></button>
					<button type="submit" class="btn btn-gradient-primary"
							data-target="#mdlPass"><?= lang("app.changePassword"); ?></button>
				</div>
			</form>
		</div>
	</div>
</div>
<div class="modal fade" id="attendanceMdl" tabindex="-1" role="dialog">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<form action="<?= base_url('record_attendance'); ?>" class="autoSubmit validate">
				<div class="modal-header">
					<h5 class="modal-title" id="exampleModalLabel"><?= lang("app.recordDailyAttendance"); ?></h5>
					<button type="button" class="close" data-dismiss="modal" aria-label="Close">
						<span aria-hidden="true">×</span>
					</button>
				</div>
				<div class="modal-body">
					<div class="col-sm-12 col-md-12 col-lg-12 pull-left">

						<div class="form-group">
							<label><?= lang("app.student"); ?></label>
							<select class="select31" name="search_student" id="search_student31">
							</select>
							<input class="form-control" type="hidden" name="student_id" required>
						</div>
						<div class="form-group">
							<label><?= lang("app.date"); ?></label>
							<input class="form-control" type="date" name="date" required
								   minlength="6">
						</div>

					</div>
				</div>
				<div class="modal-footer">
					<label style="position: absolute;left: 20px;"><?= lang("app.youAreDone"); ?></label>
					<button type="button" class="btn btn-secondary"
							data-dismiss="modal"><?= lang("app.close"); ?></button>
					<button type="submit" class="btn btn-gradient-primary"><?= lang("app.saveRecord"); ?></button>
				</div>
			</form>
		</div>
	</div>
</div>
<div class="modal fade" id="mdlmarks" tabindex="-1" role="dialog">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<form action="<?= base_url('marks_entry'); ?>" class="validate">
				<div class="modal-header">
					<h5 class="modal-title" id="exampleModalLabel"><?= lang("app.marksEntry"); ?></h5>
					<button type="button" class="close" data-dismiss="modal" aria-label="Close">
						<span aria-hidden="true">×</span>
					</button>
				</div>
				<div class="modal-body">
					<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
						<div class="form-group">
							<?php
							$p = $periodic == 0 ? '<label id="use_label" class="text-danger">' . lang("app.assessmentIsDisabled") . '</label>' : '<label id="use_label" class="text-success">' . lang("app.assessmentIsEnabled") . '</label>';
							echo $p;
							?>
						</div>
						<div class="form-group">
							<label><?= lang("app.year"); ?></label>
							<?php
							if (in_array($_SESSION['soma_post'], [1, 3])) {
								?>
								<select class="form-control select2" name="academic_year">
									<?php
									foreach ($academicYears as $year) {
										echo '<option value="' . $year['id'] . '" ' . ($year['id'] == $academic_year ? "selected" : "") . '>' . $year['title'] . '</option>';
									}
									?>
								</select>
								<?php
							} else {
								?>
								<input class="form-control" type="text" readonly value="<?= $academic_year_title; ?>">
								<?php
							}
							?>
						</div>
						<div class="form-group" id="active_term">
							<label><?= lang("app.activeTerm"); ?></label>
							<?php
							if (in_array($_SESSION['soma_post'], [1, 3])) {
								?>
								<select class="form-control select2" name="term">
									<option
											value="1" <?= $term == '1' ? 'selected' : ''; ?>><?= lang("app.term1"); ?> </option>
									<option
											value="2" <?= $term == '2' ? 'selected' : ''; ?>><?= lang("app.term2"); ?> </option>
									<option
											value="3" <?= $term == '3' ? 'selected' : ''; ?>><?= lang("app.term3"); ?> </option>
								</select>
								<?php
							} else {
								?>
								<input class="form-control" type="text" readonly
									   value="<?= \App\Controllers\Home::TermToStr($term); ?>">
								<input type="hidden" name="term" readonly value="<?= $term; ?>">
								<?php
							}
							?>
						</div>
						<div class="form-group">
							<label><?= lang("app.type"); ?></label>
							<select required class="select2" name="marktype" id="marks_type">
								<option disabled selected><?= lang("app.marksTtype"); ?></option>
								<option value="1"><?= lang("app.cat").(in_array($_SESSION['soma_school_id'], [55])?" ".lang("app.or")." ".lang("app.assessmentFormative"):""); ?></option>
								<option value="2"><?= lang("app.exam").(in_array($_SESSION['soma_school_id'], [55])?" ".lang("app.or")." ". lang("app.assessmentComprehensive"):""); ?></option>
								<?php
								if(in_array($_SESSION['soma_school_id'], [55])){
									?>
									<option value="10"><?= lang("app.assessmentIntegrated"); ?></option>
									<?php
								}
								?>
								<option value="4"><?= lang("app.catExam"); ?></option>
								<option value="3"><?= lang("app.secondSitting"); ?></option>
								<option value="9"><?= lang("app.reAssess"); ?></option>
							</select>
						</div>
						<div class="form-group" id="periodd" style="display: none">
							<label><?= lang("app.period"); ?></label>
							<select class="select2" name="period">
								<option disabled selected><?= lang("app.selectPeriod"); ?></option>
								<option value="1"><?= lang("app.period1"); ?> </option>
								<option value="2"><?= lang("app.period2"); ?> </option>
								<option value="3"><?= lang("app.period3"); ?> </option>
								<option value="4"><?= lang("app.period4"); ?> </option>
							</select>
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary"
							data-dismiss="modal"><?= lang("app.close"); ?></button>
					<button type="submit" class="btn btn-gradient-primary"
							data-target="#mdlPass"><?= lang("app.go"); ?></button>
				</div>
			</form>
		</div>
	</div>
</div>

<?php if ($page == "shifts") { ?>
	<div class="modal fade" id="mdlAddShift" tabindex="-1" role="dialog">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<form action="<?= base_url('manipulate_shift'); ?>" class="autoSubmit validate">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalLabel"><?= lang("app.newShift"); ?></h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">×</span>
						</button>
					</div>
					<div class="modal-body">
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group">
								<label><?= lang("app.title"); ?></label>
								<input class="form-control" type="text" name="title" required
									   minlength="3">
							</div>
							<div class="form-group">
								<label><?= lang("app.school"); ?></label>
								<input class="form-control" type="text" name="school" required
									   minlength="3" value="<?= $school_name; ?>" disabled="true">
							</div>
							<div class="form-group">
								<label><?= lang("app.days"); ?></label>
								<div>
									<div class="hours-view">

									</div>
									<div class="add-hours">
										<div class="form-group" style="width: 25%;float: left;margin-right: 1%">
											<select class="weekday form-control" style="border: 1px solid #cccccc">
												<option value="0"><?= lang("app.monday"); ?></option>
												<option value="1"><?= lang("app.tuesday"); ?></option>
												<option value="2"><?= lang("app.wednesday"); ?></option>
												<option value="3"><?= lang("app.thursday"); ?></option>
												<option value="4"><?= lang("app.friday"); ?></option>
												<option value="5"><?= lang("app.saturday"); ?></option>
												<option value="6"><?= lang("app.sunday"); ?></option>
											</select>
										</div><!-- /.form-group -->
										<div class="form-group" style="width: 25%;float: left;margin-right: 1%">
											<select class="hours-start form-control" style="border: 1px solid #cccccc">

												<option value="0.0">12:00 am <?= lang("app.midnight"); ?></option>

												<option value="0.5">12:30 am</option>

												<option value="1.0">1:00 am</option>

												<option value="1.5">1:30 am</option>

												<option value="2.0">2:00 am</option>

												<option value="2.5">2:30 am</option>

												<option value="3.0">3:00 am</option>

												<option value="3.5">3:30 am</option>

												<option value="4.0">4:00 am</option>

												<option value="4.5">4:30 am</option>

												<option value="5.0">5:00 am</option>

												<option value="5.5">5:30 am</option>

												<option value="6.0">6:00 am</option>

												<option value="6.5">6:30 am</option>

												<option value="7.0">7:00 am</option>

												<option value="7.5">7:30 am</option>

												<option value="8.0">8:00 am</option>

												<option value="8.5">8:30 am</option>

												<option value="9.0" selected="selected">9:00 am</option>

												<option value="9.5">9:30 am</option>

												<option value="10.0">10:00 am</option>

												<option value="10.5">10:30 am</option>

												<option value="11.0">11:00 am</option>

												<option value="11.5">11:30 am</option>

												<option value="12.0">12:00 pm <?= lang("app.noon"); ?></option>

												<option value="12.5">12:30 pm</option>

												<option value="13.0">1:00 pm</option>

												<option value="13.5">1:30 pm</option>

												<option value="14.0">2:00 pm</option>

												<option value="14.5">2:30 pm</option>

												<option value="15.0">3:00 pm</option>

												<option value="15.5">3:30 pm</option>

												<option value="16.0">4:00 pm</option>

												<option value="16.5">4:30 pm</option>

												<option value="17.0">5:00 pm</option>

												<option value="17.5">5:30 pm</option>

												<option value="18.0">6:00 pm</option>

												<option value="18.5">6:30 pm</option>

												<option value="19.0">7:00 pm</option>

												<option value="19.5">7:30 pm</option>

												<option value="20.0">8:00 pm</option>

												<option value="20.5">8:30 pm</option>

												<option value="21.0">9:00 pm</option>

												<option value="21.5">9:30 pm</option>

												<option value="22.0">10:00 pm</option>

												<option value="22.5">10:30 pm</option>

												<option value="23.0">11:00 pm</option>

												<option value="23.5">11:30 pm</option>
											</select>

										</div><!-- /.form-group -->
										<div class="form-group" style="width: 25%;float: left;margin-right: 1%">
											<select class="hours-end form-control" style="border: 1px solid #cccccc">

												<option value="0.5">12:30 am</option>

												<option value="1.0">1:00 am</option>

												<option value="1.5">1:30 am</option>

												<option value="2.0">2:00 am</option>

												<option value="2.5">2:30 am</option>

												<option value="3.0">3:00 am</option>

												<option value="3.5">3:30 am</option>

												<option value="4.0">4:00 am</option>

												<option value="4.5">4:30 am</option>

												<option value="5.0">5:00 am</option>

												<option value="5.5">5:30 am</option>

												<option value="6.0">6:00 am</option>

												<option value="6.5">6:30 am</option>

												<option value="7.0">7:00 am</option>

												<option value="7.5">7:30 am</option>

												<option value="8.0">8:00 am</option>

												<option value="8.5">8:30 am</option>

												<option value="9.0">9:00 am</option>

												<option value="9.5">9:30 am</option>

												<option value="10.0">10:00 am</option>

												<option value="10.5">10:30 am</option>

												<option value="11.0">11:00 am</option>

												<option value="11.5">11:30 am</option>

												<option value="12.0">12:00 pm <?= lang("app.noon"); ?></option>

												<option value="12.5">12:30 pm</option>

												<option value="13.0">1:00 pm</option>

												<option value="13.5">1:30 pm</option>

												<option value="14.0">2:00 pm</option>

												<option value="14.5">2:30 pm</option>

												<option value="15.0">3:00 pm</option>

												<option value="15.5">3:30 pm</option>

												<option value="16.0">4:00 pm</option>

												<option value="16.5">4:30 pm</option>

												<option value="17.0" selected="selected">5:00 pm</option>

												<option value="17.5">5:30 pm</option>

												<option value="18.0">6:00 pm</option>

												<option value="18.5">6:30 pm</option>

												<option value="19.0">7:00 pm</option>

												<option value="19.5">7:30 pm</option>

												<option value="20.0">8:00 pm</option>

												<option value="20.5">8:30 pm</option>

												<option value="21.0">9:00 pm</option>

												<option value="21.5">9:30 pm</option>

												<option value="22.0">10:00 pm</option>

												<option value="22.5">10:30 pm</option>

												<option value="23.0">11:00 pm</option>

												<option value="23.5">11:30 pm</option>

												<option value="0.0">12:00
													am <?= lang("app.midnightNextDay"); ?></option>

												<option value="0.5">12:30 am <?= lang("app.nextDay"); ?></option>

												<option value="1.0">1:00 am <?= lang("app.nextDay"); ?></option>

												<option value="1.5">1:30 am <?= lang("app.nextDay"); ?></option>

												<option value="2.0">2:00 am <?= lang("app.nextDay"); ?></option>

												<option value="2.5">2:30 am <?= lang("app.nextDay"); ?></option>

												<option value="3.0">3:00 am <?= lang("app.nextDay"); ?></option>

												<option value="3.5">3:30 am <?= lang("app.nextDay"); ?></option>

												<option value="4.0">4:00 am <?= lang("app.nextDay"); ?></option>

												<option value="4.5">4:30 am <?= lang("app.nextDay"); ?></option>

												<option value="5.0">5:00 am <?= lang("app.nextDay"); ?></option>

												<option value="5.5">5:30 am <?= lang("app.nextDay"); ?></option>

												<option value="6.0">6:00 am <?= lang("app.nextDay"); ?></option>
											</select>

										</div><!-- /.form-group -->
										<div class="form-group" style="width: 22%;float: left;">
											<button type="button" value="submit"
													class="btn btn-gradient-primary addhours"
													style="">
												<span><?= lang("app.addDay"); ?></span>
											</button>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary"
								data-dismiss="modal"><?= lang("app.close"); ?></button>
						<button type="submit" class="btn btn-gradient-primary"
								data-target="reload"><?= lang("app.save"); ?></button>
					</div>
				</form>
			</div>
		</div>
	</div>
	<?php
}
?>
<?php if ($page == "add_course") {
	?>
	<div class="modal fade" id="assignModal" tabindex="-1" role="dialog">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<form action="<?= base_url('manipulate_assign_course'); ?>" class="autoSubmit validate">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalLabel"><?= lang("app.assign"); ?></h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">×</span>
						</button>
					</div>
					<div class="modal-body">
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group">
								<label id="mentor"><?= lang("app.sClass"); ?></label> <i style="color: red;">*</i>
								<input type="hidden" name="fId">
								<input type="hidden" name="status" value="1">
								<select class="form-control select2" name="classes">
									<option selected disabled><?= lang("app.selectClass"); ?></option>
									<?php
									foreach ($classes as $classe):
										echo "<option value='{$classe['id']}'>{$classe['level_name']} {$classe['code']} {$classe['title']}</option>";
									endforeach;
									?>
								</select>
							</div>
							<div class="form-group">
								<label id="mentor"><?= lang("app.schoolYear"); ?></label> <i style="color: red;">*</i>
								<input type="text" name="year" class="form-control" readonly
									   value="<?= $academic_year_title; ?>">
							</div>
							<div class="form-group">
								<label id="mentor"><?= lang("app.term"); ?></label> <i style="color: red;">*</i>
								<select class="form-control select2" name="term[]" multiple>
									<option value="1"><?= lang("app.term1"); ?> </option>
									<option value="2"><?= lang("app.term2"); ?> </option>
									<option value="3"><?= lang("app.term3"); ?> </option>
								</select>
							</div>
							<div class="form-group">
								<label id="mentor"><?= lang("app.subjectTeacher"); ?></label> <i
										style="color: red;">*</i>
								<select class="form-control select2" name="teacher">
									<option selected disabled><?= lang("app.selectSubject"); ?> </option>
									<?php
									foreach ($staffs as $staff):
										echo "<option value='{$staff['id']}'>{$staff['fname']} {$staff['lname']}</option>";
									endforeach;
									?>
								</select>
							</div>

						</div>
					</div>
					<div class="modal-footer">
						<label style="position: absolute;left: 20px;"><?= lang("app.youAreDone"); ?></label>
						<button type="button" class="btn btn-secondary"
								data-dismiss="modal"><?= lang("app.close"); ?></button>
						<button type="submit" class="btn btn-gradient-primary"
								data-target="<?= base_url('manage_courses'); ?>">
							<?= lang("app.save"); ?>
						</button>
					</div>
				</form>
			</div>
		</div>
	</div>
	<div class="modal fade" id="editCourseModal" tabindex="-1" role="dialog">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<form action="<?= base_url('manipulate_course'); ?>" class="autoSubmit validate">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalLabel">Edit course</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">×</span>
						</button>
					</div>
					<div class="modal-body">
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group">
								<label id="mentor">Title</label> <i style="color: red;">*</i>
								<input type="text" name="title" class="form-control">
								<input type="hidden" name="courseId">
							</div>
							<div class="form-group">
								<label id="mentor">Code</label> <i style="color: red;">*</i>
								<input type="text" name="code" class="form-control">
							</div>
							<div class="form-group">
								<label id="mentor">Category</label> <i style="color: red;">*</i>
								<select class="form-control select2" name="category" required>
									<option disabled selected><?= lang("app.chooseCategory"); ?></option>
									<?php
									foreach ($categories as $category):
										echo "<option value='{$category['id']}'>{$category['title']}</option>";
									endforeach;
									?>
								</select>
							</div>
							<div class="form-group">
								<label id="mentor">Credits</label> <i style="color: red;">*</i>
								<input type="number" name="credit" class="form-control">
							</div>
							<div class="form-group">
								<label id="mentor">Marks</label> <i style="color: red;">*</i>
								<input type="text" name="marks" class="form-control">
							</div>
						</div>
					</div>
					<div class="modal-footer">
						<label style="position: absolute;left: 20px;"><?= lang("app.youAreDone"); ?></label>
						<button type="button" class="btn btn-secondary"
								data-dismiss="modal"><?= lang("app.close"); ?></button>
						<button type="submit" class="btn btn-gradient-primary"
								data-target="<?= base_url('add_course'); ?>">
							<?= lang("app.save"); ?>
						</button>
					</div>
				</form>
			</div>
		</div>
	</div>
	<?php
} ?>

<?php if ($page == "manage_Course") { ?>
	<div class="modal fade" id="addCourseModal" tabindex="-1" role="dialog">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<form action="<?= base_url('manipulate_assign_course'); ?>" class="autoSubmit validate">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalLabel"><?= lang("app.addNewCourse"); ?></h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">×</span>
						</button>
					</div>
					<div class="modal-body">
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">

							<div class="form-group">
								<label id="mentor"><?= lang("app.course"); ?></label> <i style="color: red;">*</i>
								<input type="hidden" name="fId">
								<input type="hidden" name="status" value="2">
								<select class="form-control select2" name="classes">
									<option selected disabled><?= lang("app.selectCourse"); ?></option>
									<?php
									foreach ($courses as $course):
										echo "<option value='{$course['id']}'>{$course['title']}</option>";
									endforeach;
									?>
								</select>
							</div>
							<div class="form-group">
								<label id="mentor"><?= lang("app.schoolYear"); ?></label> <i style="color: red;">*</i>
								<input type="text" name="year" class="form-control" readonly
									   value="<?= $academic_year_title; ?>">
							</div>
							<div class="form-group">
								<label id="mentor"><?= lang("app.terms"); ?></label> <i style="color: red;">*</i>
								<select class="form-control select2" name="term[]" multiple>
									<option value="1"> <?= lang("app.term1"); ?></option>
									<option value="2"> <?= lang("app.term2"); ?></option>
									<option value="3"> <?= lang("app.term3"); ?></option>
								</select>
							</div>
							<div class="form-group">
								<label id="mentor"><?= lang("app.subjectTeacher"); ?></label> <i
										style="color: red;">*</i>
								<select class="form-control select2" name="teacher">
									<option selected disabled><?= lang("app.selectSubject"); ?></option>
									<?php
									foreach ($staffs as $staff):
										echo "<option value='{$staff['id']}'>{$staff['fname']} {$staff['lname']}</option>";
									endforeach;
									?>
								</select>
							</div>

						</div>
					</div>
					<div class="modal-footer">
						<label style="position: absolute;left: 20px;"><?= lang("app.youAreDone"); ?></label>
						<button type="button" class="btn btn-secondary"
								data-dismiss="modal"><?= lang("app.close"); ?></button>
						<button type="submit" class="btn btn-gradient-primary"
								data-target="<?= base_url('manage_courses'); ?>">
							<?= lang("app.save"); ?>
						</button>
					</div>
				</form>
			</div>
		</div>
	</div>
	<!-- edit course teacher modal" -->
	<div class="modal fade" id="editLecCourseModal" tabindex="-1" role="dialog">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<form action="<?= base_url('manipulate_assign_course'); ?>" class="autoSubmit validate">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalLabel"><?= lang("app.editSubjectTeacher"); ?></h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">×</span>
						</button>
					</div>
					<div class="modal-body">
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">

							<div class="form-group">
								<label id="mentor"><?= lang("app.subjectTeacher"); ?></label> <i
										style="color: red;">*</i>
								<input type="hidden" name="fid" value="">
								<select class="form-control select2" name="teacher">
									<option selected disabled><?= lang("app.selectSubject"); ?></option>
									<?php
									foreach ($staffs as $staff):
										echo "<option value='" . $staff['id'] . "'>{$staff['fname']} {$staff['lname']}</option>";
									endforeach;
									?>
								</select>
							</div>

						</div>
					</div>
					<div class="modal-footer">
						<label style="position: absolute;left: 20px;"><?= lang("app.youAreDone"); ?></label>
						<button type="button" class="btn btn-secondary"
								data-dismiss="modal"><?= lang("app.close"); ?></button>
						<button type="submit" class="btn btn-gradient-primary"
								data-target="<?= base_url('manage_courses'); ?>">
							<?= lang("app.save"); ?>
						</button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<!-- edit term-->
	<div class="modal fade" id="editTermModal" tabindex="-1" role="dialog">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<form action="<?= base_url('change_course_data'); ?>" class="autoSubmit validate">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalLabel"><?= lang("app.editTerm"); ?></h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">×</span>
						</button>
					</div>
					<div class="modal-body">
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">

							<div class="form-group">
								<label><?= lang("app.term"); ?> </label> <i style="color: red;">*</i>
								<input type="hidden" name="fId">
								<select class="form-control select2" name="Term[]" multiple>
									<option selected disabled><?= lang("app.selectTerms"); ?></option>
									<option value="1"><?= lang("app.term1"); ?></option>
									<option value="2"><?= lang("app.term2"); ?></option>
									<option value="3"><?= lang("app.term3"); ?></option>
								</select>
							</div>

						</div>
					</div>
					<div class="modal-footer">
						<label style="position: absolute;left: 20px;"><?= lang("app.youAreDone"); ?></label>
						<button type="button" class="btn btn-secondary"
								data-dismiss="modal"><?= lang("app.close"); ?></button>
						<button type="submit" class="btn btn-gradient-primary"
								data-target="<?= base_url('manage_courses'); ?>">
							<?= lang("app.save"); ?>
						</button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<!-- edit course name-->
	<div class="modal fade" id="editCourseModal" tabindex="-1" role="dialog">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<form action="<?= base_url('change_course_data/title'); ?>" class="autoSubmit validate">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalLabel"><?= lang("app.editCourse"); ?></h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">×</span>
						</button>
					</div>
					<div class="modal-body">
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">

							<div class="form-group">
								<label><?= lang("app.course"); ?> </label> <i style="color: red;">*</i>
								<input type="hidden" name="fId">
								<input type="text" class="form-control" name="courseName"
									   placeholder="Edit course title">
							</div>

						</div>
					</div>
					<div class="modal-footer">
						<label style="position: absolute;left: 20px;"><?= lang("app.youAreDone"); ?></label>
						<button type="button" class="btn btn-secondary"
								data-dismiss="modal"><?= lang("app.close"); ?></button>
						<button type="submit" class="btn btn-gradient-primary"
								data-target="<?= base_url('manage_courses'); ?>">
							<?= lang("app.save"); ?>
						</button>
					</div>
				</form>
			</div>
		</div>
	</div>
	<?php
}
?>

<?php if ($page == "course_category" || $page == "add_course") { ?>
	<div class="modal fade" id="addCourseCategory" tabindex="-1" role="dialog">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<form action="<?= base_url('manipulate_course_category'); ?>" class="autoSubmit validate">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalLabel"><?= lang("app.addNewCourseCategory"); ?></h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">×</span>
						</button>
					</div>
					<div class="modal-body">
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group">
								<label><?= lang("app.title"); ?></label>
								<input class="form-control" type="text" name="title" required
									   minlength="3">
							</div>
						</div>
					</div>
					<div class="modal-footer">
						<label style="position: absolute;left: 20px;"><?= lang("app.youAreDone"); ?></label>
						<button type="button" class="btn btn-secondary"
								data-dismiss="modal"><?= lang("app.close"); ?></button>
						<button type="submit" class="btn btn-gradient-primary"><?= lang("app.save"); ?></button>
					</div>
				</form>
			</div>
		</div>
	</div>
	<?php
}
?>

<?php if ($page == "staffs") { ?>
	<div class="modal fade" id="assignShiftMdl" tabindex="-1" role="dialog">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<form action="<?= base_url('assign_shift'); ?>" class="autoSubmit validate">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalLabel"><?= lang("app.assignShift"); ?> </h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">×</span>
						</button>
					</div>
					<div class="modal-body">
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group">
								<label id="lbl_shift"><?= lang("app.currentShiftNone"); ?></label>
								<input class="form-control" type="hidden" name="shift_id" id="sh_shift_id">
								<input class="form-control sh_staff_id" type="hidden" name="staff">
							</div>
							<div class="form-group">
								<label><?= lang("app.newShift"); ?></label>
								<select class="form-control select2" required name="shift">
									<option selected disabled><?= lang("app.selectShift"); ?></option>
									<?php foreach ($shifts as $data) {
										$days = count(json_decode($data['options'], true));
										echo "<option value='{$data['id']}'>{$data['title']} - {$days} Days</option>";
									} ?>
								</select>
							</div>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary"
								data-dismiss="modal"><?= lang("app.close"); ?></button>
						<button type="submit" class="btn btn-gradient-primary"
								data-target="reload"><?= lang("app.save"); ?></button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<div class="modal fade" id="changePostMdl" tabindex="-1" role="dialog">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<form action="<?= base_url('change_post'); ?>" class="autoSubmit validate">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalLabel"><?= lang("app.changeStaffPost"); ?></h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">×</span>
						</button>
					</div>
					<div class="modal-body">
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group">
								<label id="lbl_post"><?= lang("app.currentPost"); ?> </label>
								<input class="form-control" type="hidden" name="post_id" id="sh_post_id">
								<input class="form-control sh_staff_id" type="hidden" name="staff">
							</div>
							<div class="form-group">
								<label><?= lang("app.privilege"); ?></label>
								<a href="javascript:void" class="pull-right" id="refrs_privilege" data-toggle="refresh"
								   data-href="<?= base_url('get_posts'); ?>" data-target="privilege"
								   style="margin: 0 10px"><i class="fa fa-sync faa-spin"></i></a>
								<select class="form-control select2" style="width: 100%" name="privilege"
										id="privilege" required>
									<option selected disabled><?= lang("app.selectPrivilege"); ?></option>
								</select>
							</div>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary"
								data-dismiss="modal"><?= lang("app.close"); ?></button>
						<button type="submit" class="btn btn-gradient-primary"
								data-target="reload"><?= lang("app.save"); ?></button>
					</div>
				</form>
			</div>
		</div>
	</div>
	<?php
}
?>
<?php if ($page == "School_fees") { ?>
	<div class="modal fade" id="mdlfees" tabindex="-1" role="dialog">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<form action="<?= base_url('manipulate_school_fee'); ?>" class="autoSubmit validate">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalLabel"><?= lang("app.createFee"); ?></h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">×</span>
						</button>
					</div>
					<div class="modal-body">
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group">
								<label><?= lang("app.selectDepartment"); ?></label>
								<select class="form-control select2" required name="dept" id="select_dept">
									<option selected disabled><?= lang("app.selectDepartment"); ?></option>
									<?php foreach ($depts as $dept) { ?>
										<option
												value="<?= $dept['id']; ?>"><?= $dept['title']; ?> <?= $dept['code']; ?></option>
										<?php
									}
									?>
								</select>
							</div>
						</div>
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group">
								<label><?= lang("app.selectLevel"); ?></label>
								<select class="form-control select2" required name="level">

								</select>
							</div>
						</div>
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group">
								<label><?= lang("app.singleTermAmount"); ?></label>
								<input type="text" name="amount" class="form-control">
							</div>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary"
								data-dismiss="modal"><?= lang("app.close"); ?></button>
						<button type="submit" class="btn btn-gradient-primary"
								data-target="reload"><?= lang("app.save"); ?></button>
					</div>
				</form>
			</div>
		</div>
	</div>
	<?php
}
?>
<?php if ($page == "Extra_fees") { ?>
	<div class="modal fade" id="mdlextrafees" tabindex="-1" role="dialog">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<form action="<?= base_url('manipulate_extra_fee'); ?>" class="autoSubmit validate">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalLabel"><?= lang("app.createFee"); ?></h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">×</span>
						</button>
					</div>

					<div class="modal-body">
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group">
								<label><?= lang("app.title"); ?></label>
								<input type="text" name="title" class="form-control">
							</div>
						</div>
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group">
								<label><?= lang("app.selectClass"); ?></label>
								<select class="form-control select2" required name="classe">
									<option selected disabled><?= lang("app.selectLevel"); ?></option>
									<?php foreach ($classes as $classe) {
										?>
										<option
												value="<?= $classe['id']; ?>"> <?= $classe['level_name']; ?> <?= $classe['code']; ?> <?= $classe['title']; ?></option>
										<?php
									}
									?>
								</select>
							</div>
						</div>
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group">
								<label><?= lang("app.amount"); ?>:</label>
								<input type="text" name="amount" class="form-control">
							</div>
						</div>
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group">
								<label><?= lang("app.selectTerms"); ?></label>
								<select class="form-control select2" required name="term[]" multiple>
									<option value="1"><?= lang("app.term1"); ?></option>
									<option value="2"><?= lang("app.term2"); ?></option>
									<option value="3"><?= lang("app.term3"); ?></option>
								</select>
							</div>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary"
								data-dismiss="modal"><?= lang("app.close"); ?></button>
						<button type="submit" class="btn btn-gradient-primary"
								data-target="reload"><?= lang("app.save"); ?></button>
					</div>
				</form>
			</div>
		</div>
	</div>
	<?php
}
?>

<?php if ($page == "Fees_Entry") { ?>
	<div class="modal fade" id="mdlfeesEntry" tabindex="-1" role="dialog">
		<div class="modal-dialog modal-lg" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="exampleModalLabel"><?= lang("app.recordNewInvoice"); ?></h5>
					<button type="button" class="close" data-dismiss="modal" aria-label="Close">
						<span aria-hidden="true">×</span>
					</button>
				</div>
				<div class="modal-body">
					<form class="autoSubmits validate">
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group">
								<label><?= lang("app.feeType"); ?></label>
								<select class="form-control select2" name="feetype" id="select_fees_type">
									<option selected disabled><?= lang("app.selectType"); ?></option>
									<option value="0"><?= lang("app.schoolFees"); ?></option>
									<option value="1"><?= lang("app.extraFees"); ?></option>
								</select>
							</div>
						</div>
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left" id="schoolfeesType" style="display: none">
							<div class="form-group">
								<label><?= lang("app.schoolFeesTerm"); ?></label>
								<select class="form-control select2" name="select_term" id="select_fees_term">

								</select>
							</div>
						</div>
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left" id="extrafeesType" style="display: none">
							<div class="form-group">
								<label><?= lang("app.extraFees"); ?></label>
								<select class="form-control select2" name="ExtrafeeType" id="ExtrafeeType">

								</select>
							</div>
						</div>
						<div class="col-sm-6 col-md-6 col-lg-6 pull-left">
							<div class="form-group">
								<label><?= lang("app.expectedAmount"); ?></label>
								<input type="text" name="expected_amount" class="form-control" readonly>
							</div>
						</div>
						<div class="col-sm-6 col-md-6 col-lg-6 pull-right">
							<div class="form-group">
								<label><?= lang("app.paidAmount"); ?></label>
								<input type="text" name="paid_amount" id="paidAmount" class="form-control" disabled>
							</div>
						</div>
						<div class="col-sm-6 col-md-6 col-lg-6 pull-left" id="remainDiv">
							<div class="form-group">
								<label><?= lang("app.remainAmount"); ?></label>
								<input type="number" name="remain_amount" class="form-control" id="remainField"
									   readonly>
							</div>
						</div>
						<div class="col-sm-6 col-md-6 col-lg-6 pull-right" id="recievedDiv">
							<div class="form-group">
								<label><?= lang("app.receivedAmount"); ?></label>
								<input type="number" name="received_amount" class="form-control"
									   data-parsley-lt-message="Received amount must be less than remaining amount"
									   required id="receivedAmount">
							</div>
						</div>
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left" id="paymentModeDiv">
							<div class="form-group">
								<label><?= lang("app.paymentMode"); ?></label>
								<select class="form-control select2" name="payment_mode" id="paymentMode" required>
									<option selected disabled><?= lang("app.selectPaymentMode"); ?></option>
									<option value="1"><?= lang("app.bankSlip"); ?></option>
									<option value="2"><?= lang("app.cash"); ?></option>
									<option value="3"><?= lang("app.cheque"); ?></option>
									<option value="4"><?= lang("app.momo"); ?></option>
									<option value="5"><?= lang("app.airtelMoney"); ?></option>
								</select>
							</div>
							<button type="submit" class="btn btn-success btn-sm float-right"
									style="margin-bottom: 13px;margin-top:-12px;color: #ffffff" id="addItemBtn"
									data-target="open">Add item
							</button>
						</div>
					</form>

					<div class="modal-footer">
						<form action="<?= base_url('manipulate_fee_entry'); ?>" id="frmSaveFeesRecords"
							  class="autoSubmit validate" style="display:none;">
							<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
								<input type="hidden" name="studentid" id="studentId">
								<table class="table table-bordered" id="pendingTbl">
									<thead>
									<tr>
										<th scope="col">#</th>
										<th scope="col">Item</th>
										<th scope="col">Type</th>
										<th scope="col">Amount</th>
										<th scope="col">Payment Mode</th>
										<th scope="col"></th>
									</tr>
									</thead>
									<tbody>

									</tbody>
								</table>
							</div>
							<div class="col-sm-12 col-md-12 col-lg-12 pull-left" id="duedateDiv">
								<div class="form-group">
									<label><?= lang("app.dueDate"); ?></label>
									<input type="date" name="dueDate" class="form-control">
								</div>
							</div>
					</div>
					<button type="button" class="btn btn-secondary"
							data-dismiss="modal"><?= lang("app.close"); ?></button>
					<button type="submit" class="btn btn-gradient-primary" data-target="open"
							id="btnSave"><?= lang("app.save"); ?></button>
					</form>
				</div>
			</div>
		</div>
	</div>
	<div class="modal fade" id="mdlExtraFeesStudent" tabindex="-1" role="dialog">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<form action="<?= base_url('manipulate_extra_fee/1'); ?>" class="autoSubmit validate">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalLabel"><?= lang("app.createFee"); ?></h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">×</span>
						</button>
					</div>

					<div class="modal-body">
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group">
								<label><?= lang("app.student"); ?></label>
								<input type="text" name="studentName" class="form-control" readonly>
								<input type="hidden" name="studentId" class="form-control">
							</div>
						</div>
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group">
								<label><?= lang("app.title"); ?></label>
								<input type="text" name="title" class="form-control">
							</div>
						</div>
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group">
								<label><?= lang("app.amount"); ?>:</label>
								<input type="text" name="amount" class="form-control">
							</div>
						</div>
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group">
								<label><?= lang("app.selectTerms"); ?></label>
								<select class="form-control select2" required name="term[]">
									<option value="1"><?= lang("app.term1"); ?></option>
									<option value="2"><?= lang("app.term2"); ?></option>
									<option value="3"><?= lang("app.term3"); ?></option>
								</select>
							</div>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary"
								data-dismiss="modal"><?= lang("app.close"); ?></button>
						<button type="submit" class="btn btn-gradient-primary"
								data-target="reload"><?= lang("app.save"); ?></button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<div class="modal fade" id="mdlDiscountFeesStudent" tabindex="-1" role="dialog">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<form action="<?= base_url('manipulate_fee_discount/1'); ?>" class="autoSubmit validate">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalLabel">School fee change</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">×</span>
						</button>
					</div>

					<div class="modal-body">
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group">
								<label><?= lang("app.student"); ?></label>
								<input type="text" name="studentName" class="form-control" readonly>
								<input type="hidden" name="studentId" class="form-control">
								<input type="hidden" name="feeId" class="form-control">
							</div>
						</div>
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group">
								<label>Current amount</label>
								<input type="number" name="feeAmount" id="feeOldAmount" class="form-control" readonly>
							</div>
						</div>
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group">
								<label>New amount</label>
								<input type="number" name="feeNewAmount" id="feeNewAmount" required class="form-control">
							</div>
							<h5>Amount change: <span id="spAmountChange"></span>
								<span id="spAmountChangeIncrease" class="badge badge-success" style="display: none">Increase</span>
								<span id="spAmountChangeDiscount" class="badge badge-danger" style="display: none">Discount</span>
							</h5>
						</div>
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group">
								<label>Comment</label>
								<textarea type="text" name="comment" class="form-control" required minlength="5"></textarea>
							</div>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary"
								data-dismiss="modal"><?= lang("app.close"); ?></button>
						<button type="submit" class="btn btn-gradient-primary"
								data-target="reload"><?= lang("app.save"); ?></button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<div class="modal fade" id="mdlEditExtraFeesStudent" tabindex="-1" role="dialog">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<form action="<?= base_url('manipulate_extra_fee/1'); ?>" class="autoSubmit validate">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalLabel">School fee change</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">×</span>
						</button>
					</div>

					<div class="modal-body">
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group">
								<label><?= lang("app.student"); ?></label>
								<input type="text" name="studentName" class="form-control" readonly>
								<input type="hidden" name="studentId" class="form-control">
								<input type="hidden" name="feeId" class="form-control">
							</div>
						</div>
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group">
								<label>Current amount</label>
								<input type="number" name="feeAmount" id="feeOldAmount" class="form-control" readonly>
							</div>
						</div>
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group">
								<label>New amount</label>
								<input type="number" name="feeNewAmount" id="feeNewAmount" required class="form-control">
							</div>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary"
								data-dismiss="modal"><?= lang("app.close"); ?></button>
						<button type="submit" class="btn btn-gradient-primary"
								data-target="reload"><?= lang("app.save"); ?></button>
					</div>
				</form>
			</div>
		</div>
	</div>
	<?php
}
?>

<?php
if ($page == "add_classes") {
	?>
	<!-- Modal -->
	<div class="modal fade" id="exampleModal" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="exampleModalLabel"><i
								class="fa fa-plus"></i> <?= lang("app.addNewClass"); ?></h5>
					<button type="button" class="close" data-dismiss="modal" aria-label="Close">
						<span aria-hidden="true">&times;</span>
					</button>
				</div>
				<div class="modal-body">
					<form action="<?= base_url('manipulate_cl'); ?>" class="validate autoSubmit">
						<div class="modal-body">
							<div class="form-group row">
								<div class="col-sm-12">
									<label><?= lang("app.type"); ?> <i style="color: red;">*</i></label>
									<select class="form-control select2 " name="type" data-target="faculty"
											id="type_select">
										<option selected disabled><?= lang("app.selectFaculty"); ?></option>
										<option value="1"><?= lang("app.wda"); ?></option>
										<option value="2"><?= lang("app.reb"); ?></option>
									</select>
								</div>
								<div class="col-sm-12" id="select_faculty">
									<label id="labels"><?= lang("app.faculty"); ?> </label> <i style="color: red;">*</i>
									<select class="form-control select2 " name="faculty" id="faculty_select">
									</select>
								</div>

								<div class="col-sm-12" id="select_dept">
									<label id="depts"><?= lang("app.dept"); ?></label> <i style="color: red;">*</i>
									<select class="form-control select2 " name="depts" id="dept_select">

									</select>
								</div>

								<div class="col-sm-7" id="select_level">
									<label id="levels"><?= lang("app.level"); ?></label> <i style="color: red;">*</i>
									<select class="form-control select2 " name="levels">
                                  
									</select>
								</div>
								<div class="col-sm-5" id="select_sub">
									<label id="subclass"><?= lang("app.subClass"); ?></label>
									<input type="text" class="form-control" name="subclass" placeholder="Ex: A,B,C">
								</div>
								<div class="col-sm-12" id="select_teacher">
									<label id="mentor"><?= lang("app.classMentor"); ?></label> <i
											style="color: red;">*</i>
									<select class="form-control select2" name="teacher" required>
										<option selected disabled><?= lang("app.classMentor"); ?></option>
										<?php
										foreach ($staffs as $item) {
											echo "<option value='{$item['id']}'>{$item['fname']} {$item['lname']}</option>";
										}
										?>
									</select>
								</div>
							</div>
						</div>
						<div class="modal-footer">
							<button type="button" class="btn btn-secondary"
									data-dismiss="modal"><?= lang("app.close"); ?></button>
							<button type="submit" class="btn btn-success"
									data-target="<?= base_url('classes'); ?>"><?= lang("app.saveChanges"); ?>
							</button>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
	<?php
}
?>
<div class="modal fade" id="mdlStaff" tabindex="-1" role="dialog">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<form action="<?= base_url('manipulate_staff'); ?>" class="autoSubmit validate">
				<div class="modal-header">
					<h5 class="modal-title" id="exampleModalLabel"><?= lang("app.createNewStaff"); ?></h5>
					<button type="button" class="close" data-dismiss="modal" aria-label="Close">
						<span aria-hidden="true">×</span>
					</button>
				</div>
				<div class="modal-body">
					<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
						<div class="form-group" style="display: flex">
							<div style="width: 50%;float:left;padding-right: 5px;">
								<label><?= lang("app.firstName"); ?></label>
								<input type="text" class="form-control" name="fname" required
									   placeholder="First name">
							</div>
							<div style="width: 50%;float:left;padding-left: 5px">
								<label><?= lang("app.fastName"); ?></label>
								<input type="text" class="form-control" name="lname" required
									   placeholder="Last name">
							</div>
						</div>
						<div class="form-group">
							<label><?= lang("app.phone"); ?></label>
							<input class="form-control" type="text" name="phone" required
								   minlength="3">
						</div>
						<div class="form-group">
							<label><?= lang("app.email"); ?></label>
							<input class="form-control" type="email" name="email" required
								   minlength="3">
						</div>
						<div class="form-group">
							<label><?= lang("app.privilege"); ?></label>
							<a href="javascript:void" class="pull-right" data-toggle="refresh"
							   data-href="<?= base_url('get_posts'); ?>" data-target="privilege"
							   style="margin: 0 10px"><i class="fa fa-sync faa-spin"></i> </a>
							<select class="form-control select2" style="width: 100%" name="privilege"
									id="privilege" required>
								<option selected disabled><?= lang("app.selectPrivilege"); ?></option>
							</select>
						</div>
						<div class="form-group" style="display: flex">
							<div style="width: 100%;float:left;padding-right: 5px;">
								<label><?= lang("app.shift"); ?></label>
								<select class="form-control select2" required name="shift">
									<option selected disabled><?= lang("app.selectShift"); ?></option>
									<?php foreach ($shifts as $data) {
										$days = count(json_decode($data['options'], true));
										echo "<option value='{$data['id']}'>{$data['title']} - {$days} Days</option>";
									} ?>
								</select>
							</div>
						</div>
						<div class="form-group" style="display: flex">
							<div style="width: 50%;float:left;padding-right: 5px;">
								<label><?= lang("app.country"); ?></label>
								<select id="country" name="country" required class=" select2 form-control">
									<option disabled selected><?= lang("app.selectCountry"); ?></option>
									<option value="Afghanistan">Afghanistan</option>
									<option value="Åland Islands">Åland Islands</option>
									<option value="Albania">Albania</option>
									<option value="Algeria">Algeria</option>
									<option value="American Samoa">American Samoa</option>
									<option value="Andorra">Andorra</option>
									<option value="Angola">Angola</option>
									<option value="Anguilla">Anguilla</option>
									<option value="Antarctica">Antarctica</option>
									<option value="Antigua and Barbuda">Antigua and Barbuda</option>
									<option value="Argentina">Argentina</option>
									<option value="Armenia">Armenia</option>
									<option value="Aruba">Aruba</option>
									<option value="Australia">Australia</option>
									<option value="Austria">Austria</option>
									<option value="Azerbaijan">Azerbaijan</option>
									<option value="Bahamas">Bahamas</option>
									<option value="Bahrain">Bahrain</option>
									<option value="Bangladesh">Bangladesh</option>
									<option value="Barbados">Barbados</option>
									<option value="Belarus">Belarus</option>
									<option value="Belgium">Belgium</option>
									<option value="Belize">Belize</option>
									<option value="Benin">Benin</option>
									<option value="Bermuda">Bermuda</option>
									<option value="Bhutan">Bhutan</option>
									<option value="Bolivia">Bolivia</option>
									<option value="Bosnia and Herzegovina">Bosnia and Herzegovina</option>
									<option value="Botswana">Botswana</option>
									<option value="Bouvet Island">Bouvet Island</option>
									<option value="Brazil">Brazil</option>
									<option value="British Indian Ocean Territory">British Indian Ocean Territory
									</option>
									<option value="Brunei Darussalam">Brunei Darussalam</option>
									<option value="Bulgaria">Bulgaria</option>
									<option value="Burkina Faso">Burkina Faso</option>
									<option value="Burundi">Burundi</option>
									<option value="Cambodia">Cambodia</option>
									<option value="Cameroon">Cameroon</option>
									<option value="Canada">Canada</option>
									<option value="Cape Verde">Cape Verde</option>
									<option value="Cayman Islands">Cayman Islands</option>
									<option value="Central African Republic">Central African Republic</option>
									<option value="Chad">Chad</option>
									<option value="Chile">Chile</option>
									<option value="China">China</option>
									<option value="Christmas Island">Christmas Island</option>
									<option value="Cocos (Keeling) Islands">Cocos (Keeling) Islands</option>
									<option value="Colombia">Colombia</option>
									<option value="Comoros">Comoros</option>
									<option value="Congo">Congo</option>
									<option value="Congo, The Democratic Republic of The">Congo, The Democratic
										Republic of The
									</option>
									<option value="Cook Islands">Cook Islands</option>
									<option value="Costa Rica">Costa Rica</option>
									<option value="Cote D'ivoire">Cote D'ivoire</option>
									<option value="Croatia">Croatia</option>
									<option value="Cuba">Cuba</option>
									<option value="Cyprus">Cyprus</option>
									<option value="Czech Republic">Czech Republic</option>
									<option value="Denmark">Denmark</option>
									<option value="Djibouti">Djibouti</option>
									<option value="Dominica">Dominica</option>
									<option value="Dominican Republic">Dominican Republic</option>
									<option value="Ecuador">Ecuador</option>
									<option value="Egypt">Egypt</option>
									<option value="El Salvador">El Salvador</option>
									<option value="Equatorial Guinea">Equatorial Guinea</option>
									<option value="Eritrea">Eritrea</option>
									<option value="Estonia">Estonia</option>
									<option value="Ethiopia">Ethiopia</option>
									<option value="Falkland Islands (Malvinas)">Falkland Islands (Malvinas)</option>
									<option value="Faroe Islands">Faroe Islands</option>
									<option value="Fiji">Fiji</option>
									<option value="Finland">Finland</option>
									<option value="France">France</option>
									<option value="French Guiana">French Guiana</option>
									<option value="French Polynesia">French Polynesia</option>
									<option value="French Southern Territories">French Southern Territories</option>
									<option value="Gabon">Gabon</option>
									<option value="Gambia">Gambia</option>
									<option value="Georgia">Georgia</option>
									<option value="Germany">Germany</option>
									<option value="Ghana">Ghana</option>
									<option value="Gibraltar">Gibraltar</option>
									<option value="Greece">Greece</option>
									<option value="Greenland">Greenland</option>
									<option value="Grenada">Grenada</option>
									<option value="Guadeloupe">Guadeloupe</option>
									<option value="Guam">Guam</option>
									<option value="Guatemala">Guatemala</option>
									<option value="Guernsey">Guernsey</option>
									<option value="Guinea">Guinea</option>
									<option value="Guinea-bissau">Guinea-bissau</option>
									<option value="Guyana">Guyana</option>
									<option value="Haiti">Haiti</option>
									<option value="Heard Island and Mcdonald Islands">Heard Island and Mcdonald
										Islands
									</option>
									<option value="Holy See (Vatican City State)">Holy See (Vatican City State)
									</option>
									<option value="Honduras">Honduras</option>
									<option value="Hong Kong">Hong Kong</option>
									<option value="Hungary">Hungary</option>
									<option value="Iceland">Iceland</option>
									<option value="India">India</option>
									<option value="Indonesia">Indonesia</option>
									<option value="Iran, Islamic Republic of">Iran, Islamic Republic of</option>
									<option value="Iraq">Iraq</option>
									<option value="Ireland">Ireland</option>
									<option value="Isle of Man">Isle of Man</option>
									<option value="Israel">Israel</option>
									<option value="Italy">Italy</option>
									<option value="Jamaica">Jamaica</option>
									<option value="Japan">Japan</option>
									<option value="Jersey">Jersey</option>
									<option value="Jordan">Jordan</option>
									<option value="Kazakhstan">Kazakhstan</option>
									<option value="Kenya">Kenya</option>
									<option value="Kiribati">Kiribati</option>
									<option value="Korea, Democratic People's Republic of">Korea, Democratic
										People's Republic of
									</option>
									<option value="Korea, Republic of">Korea, Republic of</option>
									<option value="Kuwait">Kuwait</option>
									<option value="Kyrgyzstan">Kyrgyzstan</option>
									<option value="Lao People's Democratic Republic">Lao People's Democratic
										Republic
									</option>
									<option value="Latvia">Latvia</option>
									<option value="Lebanon">Lebanon</option>
									<option value="Lesotho">Lesotho</option>
									<option value="Liberia">Liberia</option>
									<option value="Libyan Arab Jamahiriya">Libyan Arab Jamahiriya</option>
									<option value="Liechtenstein">Liechtenstein</option>
									<option value="Lithuania">Lithuania</option>
									<option value="Luxembourg">Luxembourg</option>
									<option value="Macao">Macao</option>
									<option value="Macedonia, The Former Yugoslav Republic of">Macedonia, The Former
										Yugoslav Republic of
									</option>
									<option value="Madagascar">Madagascar</option>
									<option value="Malawi">Malawi</option>
									<option value="Malaysia">Malaysia</option>
									<option value="Maldives">Maldives</option>
									<option value="Mali">Mali</option>
									<option value="Malta">Malta</option>
									<option value="Marshall Islands">Marshall Islands</option>
									<option value="Martinique">Martinique</option>
									<option value="Mauritania">Mauritania</option>
									<option value="Mauritius">Mauritius</option>
									<option value="Mayotte">Mayotte</option>
									<option value="Mexico">Mexico</option>
									<option value="Micronesia, Federated States of">Micronesia, Federated States
										of
									</option>
									<option value="Moldova, Republic of">Moldova, Republic of</option>
									<option value="Monaco">Monaco</option>
									<option value="Mongolia">Mongolia</option>
									<option value="Montenegro">Montenegro</option>
									<option value="Montserrat">Montserrat</option>
									<option value="Morocco">Morocco</option>
									<option value="Mozambique">Mozambique</option>
									<option value="Myanmar">Myanmar</option>
									<option value="Namibia">Namibia</option>
									<option value="Nauru">Nauru</option>
									<option value="Nepal">Nepal</option>
									<option value="Netherlands">Netherlands</option>
									<option value="Netherlands Antilles">Netherlands Antilles</option>
									<option value="New Caledonia">New Caledonia</option>
									<option value="New Zealand">New Zealand</option>
									<option value="Nicaragua">Nicaragua</option>
									<option value="Niger">Niger</option>
									<option value="Nigeria">Nigeria</option>
									<option value="Niue">Niue</option>
									<option value="Norfolk Island">Norfolk Island</option>
									<option value="Northern Mariana Islands">Northern Mariana Islands</option>
									<option value="Norway">Norway</option>
									<option value="Oman">Oman</option>
									<option value="Pakistan">Pakistan</option>
									<option value="Palau">Palau</option>
									<option value="Palestinian Territory, Occupied">Palestinian Territory,
										Occupied
									</option>
									<option value="Panama">Panama</option>
									<option value="Papua New Guinea">Papua New Guinea</option>
									<option value="Paraguay">Paraguay</option>
									<option value="Peru">Peru</option>
									<option value="Philippines">Philippines</option>
									<option value="Pitcairn">Pitcairn</option>
									<option value="Poland">Poland</option>
									<option value="Portugal">Portugal</option>
									<option value="Puerto Rico">Puerto Rico</option>
									<option value="Qatar">Qatar</option>
									<option value="Reunion">Reunion</option>
									<option value="Romania">Romania</option>
									<option value="Russian Federation">Russian Federation</option>
									<option value="Rwanda">Rwanda</option>
									<option value="Saint Helena">Saint Helena</option>
									<option value="Saint Kitts and Nevis">Saint Kitts and Nevis</option>
									<option value="Saint Lucia">Saint Lucia</option>
									<option value="Saint Pierre and Miquelon">Saint Pierre and Miquelon</option>
									<option value="Saint Vincent and The Grenadines">Saint Vincent and The
										Grenadines
									</option>
									<option value="Samoa">Samoa</option>
									<option value="San Marino">San Marino</option>
									<option value="Sao Tome and Principe">Sao Tome and Principe</option>
									<option value="Saudi Arabia">Saudi Arabia</option>
									<option value="Senegal">Senegal</option>
									<option value="Serbia">Serbia</option>
									<option value="Seychelles">Seychelles</option>
									<option value="Sierra Leone">Sierra Leone</option>
									<option value="Singapore">Singapore</option>
									<option value="Slovakia">Slovakia</option>
									<option value="Slovenia">Slovenia</option>
									<option value="Solomon Islands">Solomon Islands</option>
									<option value="Somalia">Somalia</option>
									<option value="South Africa">South Africa</option>
									<option value="South Georgia and The South Sandwich Islands">South Georgia and
										The South Sandwich Islands
									</option>
									<option value="Spain">Spain</option>
									<option value="Sri Lanka">Sri Lanka</option>
									<option value="Sudan">Sudan</option>
									<option value="Suriname">Suriname</option>
									<option value="Svalbard and Jan Mayen">Svalbard and Jan Mayen</option>
									<option value="Swaziland">Swaziland</option>
									<option value="Sweden">Sweden</option>
									<option value="Switzerland">Switzerland</option>
									<option value="Syrian Arab Republic">Syrian Arab Republic</option>
									<option value="Taiwan, Province of China">Taiwan, Province of China</option>
									<option value="Tajikistan">Tajikistan</option>
									<option value="Tanzania, United Republic of">Tanzania, United Republic of
									</option>
									<option value="Thailand">Thailand</option>
									<option value="Timor-leste">Timor-leste</option>
									<option value="Togo">Togo</option>
									<option value="Tokelau">Tokelau</option>
									<option value="Tonga">Tonga</option>
									<option value="Trinidad and Tobago">Trinidad and Tobago</option>
									<option value="Tunisia">Tunisia</option>
									<option value="Turkey">Turkey</option>
									<option value="Turkmenistan">Turkmenistan</option>
									<option value="Turks and Caicos Islands">Turks and Caicos Islands</option>
									<option value="Tuvalu">Tuvalu</option>
									<option value="Uganda">Uganda</option>
									<option value="Ukraine">Ukraine</option>
									<option value="United Arab Emirates">United Arab Emirates</option>
									<option value="United Kingdom">United Kingdom</option>
									<option value="United States">United States</option>
									<option value="United States Minor Outlying Islands">United States Minor
										Outlying Islands
									</option>
									<option value="Uruguay">Uruguay</option>
									<option value="Uzbekistan">Uzbekistan</option>
									<option value="Vanuatu">Vanuatu</option>
									<option value="Venezuela">Venezuela</option>
									<option value="Viet Nam">Viet Nam</option>
									<option value="Virgin Islands, British">Virgin Islands, British</option>
									<option value="Virgin Islands, U.S.">Virgin Islands, U.S.</option>
									<option value="Wallis and Futuna">Wallis and Futuna</option>
									<option value="Western Sahara">Western Sahara</option>
									<option value="Yemen">Yemen</option>
									<option value="Zambia">Zambia</option>
									<option value="Zimbabwe">Zimbabwe</option>
								</select>
							</div>

							<div style="width: 50%;float:left;padding-right: 5px;">
								<label><?= lang("app.city"); ?></label>
								<input class="form-control" type="text" name="city" required
									   minlength="3" placeholder="Ex: Kigali">
							</div>
						</div>
						<div class="form-group">
							<label><?= lang("app.address2"); ?></label>
							<input class="form-control" type="text" name="address"
								   minlength="3" placeholder="Ex: Street no...">
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<label style="position: absolute;left: 20px;"><?= lang("app.youAreDone"); ?></label>
					<button type="button" class="btn btn-secondary"
							data-dismiss="modal"><?= lang("app.close"); ?></button>
					<button type="submit" class="btn btn-gradient-primary"><?= lang("app.saveChanges"); ?></button>
				</div>
			</form>
		</div>
	</div>
</div>
<div class="modal fade" id="mdlTerm" tabindex="-1" role="dialog">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<form action="<?= base_url('manipulate_term'); ?>" class="autoSubmit validate">
				<div class="modal-header">
					<h5 class="modal-title" id="exampleModalLabel"><?= lang("app.changeActiveTerm"); ?></h5>
					<button type="button" class="close" data-dismiss="modal" aria-label="Close">
						<span aria-hidden="true">×</span>
					</button>
				</div>
				<div class="modal-body">
					<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
						<label class="text text-danger"><?= lang("app.rememberIfYou"); ?></label>
						<div class="form-group" id="dv-existing-academic"
							 style="display: <?= count($academicYears) == 0 ? 'none' : 'inline-block'; ?>;width: 100%;">
							<div style="width: 50%;float:left;padding-right: 5px;position:relative;">
								<label><?= lang("app.academicYear"); ?></label>
								<a href="javascript:void(0)" class="pull-right"
								   id="a-create-academic"><?= lang("app.addNew"); ?></a>
								<select class="form-control select2" required style="width: 100%" name="academic_year">
									<option selected disabled><?= lang("app.selectAcademic"); ?></option>
									<?php
									foreach ($academicYears as $academicYear) {
										echo "<option value='{$academicYear['id']}'>{$academicYear['title']}</option>";
									}
									?>
								</select>
							</div>
							<div style="width: 50%;float:left;padding-left: 5px;position:relative;">
								<label><?= lang("app.term"); ?></label>
								<select class="form-control select2" required style="width: 100%" name="term">
									<option selected disabled><?= lang("app.selectTerm"); ?></option>
									<option value="1"> <?= lang("app.term1"); ?></option>
									<option value="2"> <?= lang("app.term2"); ?></option>
									<option value="3"> <?= lang("app.term3"); ?></option>
								</select>
							</div>
						</div>
						<div id="dv-new-academic"
							 style="display: <?= count($academicYears) == 0 ? 'inline-block' : 'none'; ?>;width: 100%;margin-bottom: 20px">
							<div style="width: 50%;float:left;padding-right: 5px;position:relative;">
								<label><?= lang("app.createAcademic"); ?></label>
								<input class="form-control" type="text" name="academic_title"
									   value="<?= $academicYearSuggestion; ?>">
							</div>
							<div style="width: 50%;float:left;padding-left: 5px;position:relative;">
								<label style="width: 100%;">&nbsp;</label>
								<button type="button" class="btn btn-gradient-info" id="btn-save-academic"
										data-target="reload"><?= lang("app.saveAcademic"); ?></button>
							</div>
						</div>
						<div id="academic-creation-overlay"
							 style="display: <?= count($academicYears) == 0 ? 'inline-block' : 'none'; ?>">
							<label style="margin-top: 16%;width: 100%;">Wait while creating new academic
								year....</label>
							<?php
							if (count($academicYears) > 0) {
								echo '<label style="color: orangered;cursor: pointer" id="lbl-cancel-academic">' . lang('app.cancelCreation') . '</label>';
							}
							?>
						</div>
						<div class="form-group">
							<input id="periods" type="checkbox" name="period"
								   value="1"><label for="periods"><?= lang("app.usePeriodic"); ?></label>
						</div>
						<div class="form-group">
							<label><?= lang("app.sMSLimit"); ?></label>
							<input class="form-control" type="text" readonly value="<?= $sms_limit; ?>">
						</div>
						<div class="form-group">
							<label><?= lang("app.schoolName"); ?></label>
							<input class="form-control" type="text" readonly
								   value="<?= $_SESSION['soma_school']; ?>">
						</div>
						<div class="form-group">
							<label><?= lang("app.password"); ?></label>
							<input class="form-control" type="password" minlength="6"
								   data-parsley-minlength-message="<?= lang("app.passErr"); ?>" required
								   placeholder="<?= lang("app.passConfirm"); ?> action" value="" name="password">
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary"
							data-dismiss="modal"><?= lang("app.close"); ?></button>
					<button type="submit" class="btn btn-gradient-primary"
							data-target="reload"><?= lang("app.saveChanges"); ?></button>
				</div>
			</form>
		</div>
	</div>
</div>
<?php if ($page == "apply_leave") { ?>
	<div class="modal fade" id="apply_leave_model" tabindex="-1" role="dialog">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<form action="<?= base_url('manipulate_leave'); ?>" class="autoSubmit validate">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalLabel"><?= lang("app.leaveApplication"); ?></h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">×</span>
						</button>
					</div>
					<div class="modal-body">
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group" style="display: flex">
								<div style="width: 100%;float:left;padding-right: 5px;">
									<label><?= lang("app.typeofleave"); ?></label>
									<select class="form-control select2" required id="leave_type" name="type">
										<option selected disabled><?= lang("app.selectLeaveType"); ?></option>
										<option value="1"><?= lang("app.annualLeave"); ?></option>
										<option value="2"><?= lang("app.sickLeave"); ?></option>
										<option value="3"><?= lang("app.maternityLeave"); ?></option>
										<option value="4"><?= lang("app.unpaidLeave"); ?></option>
									</select>
								</div>
							</div>
							<div class="form-group" style="display: flex;" id="reason">
								<div style="width: 100%;float:left;padding-right: 5px;">
									<label><?= lang("app.unpaidReason"); ?></label>
									<textarea class="form-control" placeholder="Reason" name="reason"></textarea>
								</div>
							</div>
							<div class="form-group" style="display: flex">
								<div style="width: 100%;float:left;padding-right: 5px;">
									<div>
										<i><?= lang("app.note"); ?><br>
											<?= lang("app.execess1day"); ?><br>
											<?= lang("app.attached"); ?></i>
									</div>
								</div>
							</div>
							<div class="form-group" style="display: flex">
								<div style="width: 100%;float:left;padding-right: 5px;">
									<label><?= lang("app.daysRequest"); ?></label>
									<input type="number" class="form-control" name="days">
								</div>
							</div>
							<div class="form-group" style="display: flex">
								<div style="width: 50%;float:left;padding-right: 5px;">
									<label><?= lang("app.from"); ?></label>
									<input type="date" class="form-control" name="fdate" required>
								</div>
								<div style="width: 50%;float:left;padding-left: 5px">
									<label><?= lang("app.to"); ?></label>
									<input type="date" class="form-control" name="tdate" required>
								</div>
							</div>

							<div class="form-group" style="display: flex">
								<div style="width: 100%;float:left;padding-right: 5px;">
									<label><?= lang("app.addresDuringLeave"); ?></label>
									<input type="text" name="address" class="form-control">
								</div>
							</div>

						</div>
					</div>
					<div class="modal-footer">
						<!--						<label style="position: absolute;left: 20px;">Close this if you are done</label>-->
						<button type="button" class="btn btn-secondary"
								data-dismiss="modal"><?= lang("app.close"); ?></button>
						<button type="submit" class="btn btn-gradient-primary"
								data-target="reload"><?= lang("app.saveChanges"); ?></button>
					</div>
				</form>
			</div>
		</div>
	</div>
	<?php
}
?>
<?php if ($page == "leave") { ?>
	<!-- deny modal-->
	<div class="modal fade" id="deny_Modal" tabindex="-1" role="dialog">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<form action="<?= base_url('manipulate_leaveOrDeny'); ?>" class="autoSubmit validate">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalLabel"><?= lang("app.denyLeave"); ?></h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">×</span>
						</button>
					</div>
					<div class="modal-body">
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group" style="display: flex;" id="reason">
								<div style="width: 100%;float:left;padding-right: 5px;">
									<label><?= lang("app.reasonForDeny"); ?></label>
									<textarea class="form-control" placeholder="Reason" name="denyReason"
											  required></textarea>
									<input type="hidden" value="" name="fId">
									<input type="hidden" value="2" name="type">
									<input type="hidden" value="2" name="email">
								</div>
							</div>
						</div>
					</div>
					<div class="modal-footer">
						<!--						<label style="position: absolute;left: 20px;">Close this if you are done</label>-->
						<button type="button" class="btn btn-secondary"
								data-dismiss="modal"><?= lang("app.cancel"); ?></button>
						<button type="submit" class="btn btn-gradient-primary"
								data-target="reload"><?= lang("app.deny"); ?></button>
					</div>
				</form>
			</div>
		</div>
	</div>
	<!-- approve modal-->
	<div class="modal fade" id="approve_Modal" tabindex="-1" role="dialog">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<form action="<?= base_url('manipulate_leaveOrDeny'); ?>" class="autoSubmit validate">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalLabel"><?= lang("app.please"); ?></h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">×</span>
						</button>
					</div>
					<div class="modal-body">
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group" style="display: flex;" id="reason">
								<div style="width: 100%;float:left;padding-right: 5px;">
									<center><h5><i><?= lang("app.areYouSureApprove"); ?></i></h5>
										<p><i><b id="stafflabel"></b></i> <i><?= lang("app.leavess"); ?></i></p>
										<input type="hidden" value="" name="fId"></center>
									<input type="hidden" value="1" name="type">
									<input type="hidden" value="2" name="email">
									</center>
								</div>
							</div>
						</div>
					</div>
					<div class="modal-footer">
						<!--						<label style="position: absolute;left: 20px;">Close this if you are done</label>-->
						<button type="button" class="btn btn-secondary"
								data-dismiss="modal"><?= lang("app.cancel"); ?></button>
						<button type="submit" class="btn btn-gradient-primary"
								data-target="reload"><?= lang("app.approve"); ?></button>
					</div>
				</form>
			</div>
		</div>
	</div>
	<?php
}
?>

<?php if ($page == "Book") { ?>
	<div class="modal fade" id="createBook" tabindex="-1" role="dialog">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<form action="<?= base_url('manipulate_book_entry'); ?>" class="autoSubmit validate">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalLabel"><?= lang("app.createnewBook"); ?> </h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">×</span>
						</button>
					</div>
					<div class="modal-body">
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group">
								<label><?= lang("app.title"); ?>:</label>
								<input class="form-control" type="text" name="title" required>
							</div>
						</div>
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group">
								<label><?= lang("app.author"); ?>:</label>
								<input class="form-control" type="text" name="author">
							</div>
						</div>

						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group">
								<label><?= lang("app.category"); ?>:</label> <a data-toggle="modal"
																				data-target="#createBookCategory"><i
											class="pull-right fa fa-plus"><?= lang("app.new"); ?></i></a>
								<select class="form-control select2" required name="category">
									<option selected disabled><?= lang("app.selectCategory"); ?></option>
									<?php foreach ($categories as $cat) { ?>
										<option value="<?= $cat['id']; ?>"><?= $cat['title']; ?></option>
										<?php
									}
									?>
								</select>
							</div>
						</div>
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group">
								<label><?= lang("app.quantity"); ?>:</label>
								<input class="form-control" type="number" name="quantity" required>
							</div>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary"
								data-dismiss="modal"><?= lang("app.close"); ?></button>
						<button type="submit" class="btn btn-gradient-primary"
								data-target="reload"><?= lang("app.save"); ?></button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<!-- edit book record -->
	<div class="modal fade" id="editBook" tabindex="-1" role="dialog">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<form action="<?= base_url('manipulate_book_entry'); ?>" class="autoSubmit validate">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalLabel"><?= lang("app.editBook"); ?></h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">×</span>
						</button>
					</div>
					<div class="modal-body">
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group">
								<label><?= lang("app.title"); ?>:</label>
								<input class="form-control" type="hidden" name="fId" required>
								<input class="form-control" type="text" name="title" required>
							</div>
						</div>
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group">
								<label><?= lang("app.author"); ?>:</label>
								<input class="form-control" type="text" name="author">
							</div>
						</div>

						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group">
								<label><?= lang("app.category"); ?>:</label> <a data-toggle="modal"
																				data-target="#createBookCategory"><i
											class="pull-right fa fa-plus"><?= lang("app.new"); ?></i></a>
								<select class="form-control select2" required name="category">
									<option selected disabled><?= lang("app.selectCategory"); ?></option>
									<?php foreach ($categories as $cat) { ?>
										<option value="<?= $cat['id']; ?>"><?= $cat['title']; ?></option>
										<?php
									}
									?>
								</select>
							</div>
						</div>
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group">
								<label><?= lang("app.currentQuantity"); ?>:</label>
								<input class="form-control" type="number" name="quantity" required disabled>
							</div>
						</div>
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group">
								<label><?= lang("app.newQuantity"); ?>:</label>
								<input class="form-control" type="number" name="newquantity">
							</div>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary"
								data-dismiss="modal"><?= lang("app.close"); ?></button>
						<button type="submit" class="btn btn-gradient-primary"
								data-target="reload"><?= lang("app.save"); ?></button>
					</div>
				</form>
			</div>
		</div>
	</div>
	<div class="modal fade" id="createBookCategory" tabindex="-1" role="dialog">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<form action="<?= base_url('manipulate_bookCategory'); ?>" class="autoSubmit validate">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalLabel"><?= lang("app.newBookCategory"); ?></h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">×</span>
						</button>
					</div>
					<div class="modal-body">
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group">
								<label><?= lang("app.title"); ?>:</label>
								<input class="form-control" type="text" name="title" required>
							</div>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary"
								data-dismiss="modal"><?= lang("app.close"); ?></button>
						<button type="submit" class="btn btn-gradient-primary"
								data-target="reload"><?= lang("app.save"); ?></button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<div class="modal fade" id="borrowBook" role="dialog">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<form action="<?= base_url('manipulate_borrow_book'); ?>" class="autoSubmit validate">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalLabel"><?= lang("app.borrowBook"); ?></h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">×</span>
						</button>
					</div>
					<div class="modal-body">
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group">
								<label><?= lang("app.title"); ?>:</label>
								<input class="form-control" type="hidden" name="bookId" required>
								<input class="form-control" type="text" name="title" disabled>
							</div>
						</div>
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group">
								<label>Type</label>
								<select class="select2" name="borrowType" required id="borrowType">
									<option value="1">Student</option>
									<option value="2">Staff</option>
								</select>
							</div>
						</div>
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left show-student">
							<div class="form-group">
								<label><?= lang("app.sClass"); ?>:</label>
								<select class="select2" name="classe" id="select_class_book">
									<option disabled selected><?= lang("app.selectClass"); ?></option>
									<?php foreach ($classes as $classe) { ?>
										<option
												value="<?= $classe['id']; ?>"><?= $classe['level_name']; ?> <?= $classe['code']; ?> <?= $classe['title']; ?></option>
										<?php
									}
									?>
									?>
								</select>
							</div>
						</div>
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left show-student">
							<div class="form-group">
								<label><?= lang("app.student"); ?>:</label>
								<select class="select2" name="select_student_book" id="select_student_book">
									<option disabled selected><?= lang("app.selectStudent"); ?></option>
								</select>
							</div>
						</div>
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left show-staff">
							<div class="form-group">
								<label>Staff:</label>
								<select class="select2" name="staff" id="selected_staff_book">
									<option disabled selected>Select staff</option>
									<?php
									foreach ($staffs as $staff):
										echo "<option value='" . $staff['id'] . "'>" . $staff['names'] . "</option>";
									endforeach;
									?>
								</select>
							</div>
						</div>
						<div class="col-sm-6 col-md-6 col-lg-6 pull-left">
							<div class="form-group">
								<label><?= lang("app.borrowDate"); ?>:</label>
								<input class="form-control" type="date" name="borrow_date" required>
							</div>
						</div>
						<div class="col-sm-6 col-md-6 col-lg-6 pull-left ">
							<div class="form-group">
								<label><?= lang("app.returnDueDate"); ?>:</label>
								<input class="form-control" type="date" name="return_due_date" required>
							</div>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary"
								data-dismiss="modal"><?= lang("app.close"); ?></button>
						<button type="submit" class="btn btn-gradient-primary"
								data-target="reload"><?= lang("app.save"); ?></button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<?php
}
if ($page == "Book_history") {
	?>
	<div class="modal fade" id="returnBook" tabindex="-1" role="dialog">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<form action="<?= base_url('returing_book'); ?>" class="autoSubmit validate">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalLabel"><?= lang("app.message"); ?></h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">×</span>
						</button>
					</div>
					<div class="modal-body">
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group">
								<center><b><?= lang("app.areYoSure"); ?></b></center>
								<input type="hidden" name="record_id">
							</div>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary"
								data-dismiss="modal"><?= lang("app.no"); ?></button>
						<button type="submit" class="btn btn-gradient-primary"
								data-target="reload"><?= lang("app.yes"); ?></button>
					</div>
				</form>
			</div>
		</div>
	</div>
	<?php
}
if ($page == "student") {
	?>
	<div class="modal fade" id="Change_student_Mode" tabindex="-1" role="dialog">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<form action="<?= base_url('change_studing_mode'); ?>" class="autoSubmit validate">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalLabel"><?= lang("app.changeMode"); ?></h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">×</span>
						</button>
					</div>
					<div class="modal-body">
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group">
								<input class="form-control" type="hidden" name="fId" required>
								<label><?= lang("app.studingMode"); ?>:</label>
								<select class="select2" name="mode" id="selectMode">
									<option disabled selected><?= lang("app.selectMode"); ?></option>
									<option value="0"><?= lang("app.boarding"); ?></option>
									<option value="1"><?= lang("app.day"); ?></option>
								</select>
							</div>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary"
								data-dismiss="modal"><?= lang("app.close"); ?></button>
						<button type="submit" class="btn btn-gradient-primary"
								data-target="reload"><?= lang("app.save"); ?></button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<div class="modal fade" id="Change_student_Class" tabindex="-1" role="dialog">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<form action="<?= base_url('change_student_class'); ?>" class="autoSubmit validate">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalLabel"><?= lang("app.changeClass"); ?></h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">×</span>
						</button>
					</div>
					<div class="modal-body">
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group">
								<input class="form-control" type="hidden" name="fId" required>
								<label><?= lang("app.selectClass"); ?>:</label>
								<select class="select2" name="classe" required>
									<option disabled selected><?= lang("app.selectClass"); ?></option>
									<?php foreach ($classes as $classe) { ?>
										<option
												value="<?= $classe['id']; ?>"><?= $classe['level_name']; ?> <?= $classe['code']; ?> <?= $classe['title']; ?></option>
										<?php
									}
									?>
									?>
								</select>
							</div>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary"
								data-dismiss="modal"><?= lang("app.close"); ?></button>
						<button type="submit" class="btn btn-gradient-primary"
								data-target="reload"><?= lang("app.save"); ?></button>
					</div>
				</form>
			</div>
		</div>
	</div>
	<?php
}
if ($page == "Bus_management") {
?>
<div class="modal fade" id="createBus" tabindex="-1" role="dialog">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<form action="<?= base_url('manipulate_bus'); ?>" class="autoSubmit validate">
				<div class="modal-header">
					<h5 class="modal-title" id="exampleModalLabel"><?= lang("app.editBus"); ?></h5>
					<button type="button" class="close" data-dismiss="modal" aria-label="Close">
						<span aria-hidden="true">×</span>
					</button>
				</div>
				<div class="modal-body">
					<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
						<div class="form-group">
							<label><?= lang("app.plateNumber"); ?>:</label>
							<input class="form-control" type="hidden" name="fId" required>
							<input class="form-control" type="text" name="plate" required>
						</div>
					</div>
					<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
						<div class="form-group">
							<label><?= lang("app.carMaker"); ?>:</label>
							<input class="form-control" type="text" name="car_maker">
						</div>
					</div>

					<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
						<div class="form-group">
							<label><?= lang("app.carModel"); ?>:</label>
							<input class="form-control" type="text" name="car_model">
						</div>
					</div>
					<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
						<div class="form-group">
							<label><?= lang("app.carYear"); ?>:</label>
							<input class="form-control" type="number" name="car_year" required maxlength="4"
								   minlength="4">
						</div>
					</div>
					<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
						<div class="form-group">
							<label><?= lang("app.noofPlaces"); ?>:</label>
							<input class="form-control" type="number" name="places" required maxlength="3"
								   minlength="2">
						</div>
					</div>
					<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
						<div class="form-group">
							<label><?= lang("app.driver"); ?>:</label>
							<select class="select2" name="driver" required>
								<option disabled selected><?= lang("app.selectDriver"); ?></option>
								<?php
								foreach ($drivers as $driver) {
									?>
									<option value="<?= $driver['id']; ?>"><?= $driver['driver']; ?></option>
									<?php
								}
								?>
							</select>
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary"
							data-dismiss="modal"><?= lang("app.close"); ?></button>
					<button type="submit" class="btn btn-gradient-primary"
							data-target="reload"><?= lang("app.save"); ?></button>
				</div>
			</form>
		</div>
	</div>
</div>

<div class="modal fade" id="editBus" tabindex="-1" role="dialog">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<form action="<?= base_url('manipulate_bus'); ?>" class="autoSubmit validate">
				<div class="modal-header">
					<h5 class="modal-title" id="exampleModalLabel"><?= lang("app.createNewBus"); ?></h5>
					<button type="button" class="close" data-dismiss="modal" aria-label="Close">
						<span aria-hidden="true">×</span>
					</button>
				</div>
				<div class="modal-body">
					<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
						<div class="form-group">
							<label><?= lang("app.plateNumber"); ?>:</label>
							<input class="form-control" type="hidden" name="fId" required>
							<input class="form-control" type="text" name="plate" required>
						</div>
					</div>
					<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
						<div class="form-group">
							<label><?= lang("app.carMaker"); ?>:</label>
							<input class="form-control" type="text" name="car_maker">
						</div>
					</div>

					<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
						<div class="form-group">
							<label><?= lang("app.carModel"); ?>:</label>
							<input class="form-control" type="text" name="car_model">
						</div>
					</div>
					<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
						<div class="form-group">
							<label><?= lang("app.carYear"); ?>:</label>
							<input class="form-control" type="number" name="car_year" required maxlength="4"
								   minlength="4">
						</div>
					</div>
					<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
						<div class="form-group">
							<label><?= lang("app.places"); ?>:</label>
							<input class="form-control" type="number" name="places" required maxlength="3"
								   minlength="2">
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary"
							data-dismiss="modal"><?= lang("app.close"); ?></button>
					<button type="submit" class="btn btn-gradient-primary"
							data-target="reload"><?= lang("app.save"); ?></button>
				</div>
			</form>
		</div>
	</div>
</div>

<div class="modal fade" id="editBusDriver" tabindex="-1" role="dialog">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<form action="<?= base_url('manipulate_bus'); ?>" class="autoSubmit validate">
				<div class="modal-header">
					<h5 class="modal-title" id="exampleModalLabel"><?= lang("app.changeDriver"); ?></h5>
					<button type="button" class="close" data-dismiss="modal" aria-label="Close">
						<span aria-hidden="true">×</span>
					</button>
				</div>
				<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
					<div class="form-group">
						<input type="hidden" name="fId">
						<label><?= lang("app.driver"); ?>:</label>
						<select class="select2 form-control" name="staff" required>
							<option disabled selected><?= lang("app.selectDriver"); ?></option>
							<?php
							foreach ($drivers as $driver) {
								?>
								<option value="<?= $driver['id']; ?>"><?= $driver['driver']; ?></option>
								<?php
							}
							?>
						</select>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary"
							data-dismiss="modal"><?= lang("app.close"); ?></button>
					<button type="submit" class="btn btn-gradient-primary"
							data-target="reload"><?= lang("app.save"); ?></button>
				</div>
			</form>
		</div>
	</div>
<?php
}
if ($page == "Route_management") {
		?>
		<div class="modal fade" id="createRoute" tabindex="-1" role="dialog">
			<div class="modal-dialog" role="document">
				<div class="modal-content">
					<form action="<?= base_url('manipulate_route'); ?>" class="autoSubmit validate">
						<div class="modal-header">
							<h5 class="modal-title" id="exampleModalLabel"><?= lang("app.createRoute"); ?></h5>
							<button type="button" class="close" data-dismiss="modal" aria-label="Close">
								<span aria-hidden="true">×</span>
							</button>
						</div>
						<div class="modal-body">
							<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
								<div class="form-group">
									<label><?= lang("app.title"); ?>:</label>
									<input class="form-control" type="text" name="title" required>
								</div>
							</div>
							<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
								<div class="form-group">
									<label><?= lang("app.price"); ?>:</label>
									<input class="form-control" type="number" name="price"
										   placeholder="price per a single ride" required>
								</div>
							</div>

							<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
								<div class="form-group">
									<label><?= lang("app.details"); ?>:</label>
									<textarea class="form-control" name="details"></textarea>
								</div>
							</div>

						</div>
						<div class="modal-footer">
							<button type="button" class="btn btn-secondary"
									data-dismiss="modal"><?= lang("app.close"); ?></button>
							<button type="submit" class="btn btn-gradient-primary"
									data-target="reload"><?= lang("app.save"); ?></button>
						</div>
					</form>
				</div>
			</div>
		</div>

		<div class="modal fade" id="editRoute" tabindex="-1" role="dialog">
			<div class="modal-dialog" role="document">
				<div class="modal-content">
					<form action="<?= base_url('manipulate_route'); ?>" class="autoSubmit validate">
						<div class="modal-header">
							<h5 class="modal-title" id="exampleModalLabel"><?= lang("app.editRoute"); ?></h5>
							<button type="button" class="close" data-dismiss="modal" aria-label="Close">
								<span aria-hidden="true">×</span>
							</button>
						</div>
						<div class="modal-body">
							<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
								<div class="form-group">
									<label><?= lang("app.title"); ?>:</label>
									<input class="form-control" type="hidden" name="fId" required>
									<input class="form-control" type="text" name="title" required>
								</div>
							</div>
							<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
								<div class="form-group">
									<label><?= lang("app.price"); ?>:</label>
									<input class="form-control" type="number" name="price" required>
								</div>
							</div>

							<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
								<div class="form-group">
									<label><?= lang("app.price"); ?>:</label>
									<textarea class="form-control" name="details"></textarea>
								</div>
							</div>

						</div>
						<div class="modal-footer">
							<button type="button" class="btn btn-secondary"
									data-dismiss="modal"><?= lang("app.close"); ?></button>
							<button type="submit" class="btn btn-gradient-primary"
									data-target="reload"><?= lang("app.saveChanges"); ?></button>
						</div>
					</form>
				</div>
			</div>
		</div>
<?php
}
if ($page == "Transport_management") {
	?>
	<div class="modal fade" id="createTransport_fees" tabindex="-1" role="dialog">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<form action="<?= base_url('manipulate_transport_fees'); ?>" class="autoSubmit validate">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalLabel"><?= lang("app.transportFee"); ?></h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">×</span>
						</button>
					</div>
					<div class="modal-body">
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group">
								<label><?= lang("app.sClass"); ?>:</label>
								<select class="select2 form-control" name="classe" id="select_class_trans">
									<option selected disabled><?= lang("app.selectClass"); ?></option>
									<?php
									foreach ($classes as $class) {
										echo "<option value='{$class['id']}'>{$class['level_name']} {$class['title']} {$class['code']} </option>";
									}
									?>
								</select>
							</div>
						</div>
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<label><?= lang("app.student"); ?></label>
							<select class="select2" name="select_student_btrans" id="student">
								<option disabled selected><?= lang("app.selectStudent"); ?></option>
							</select>
						</div>
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group">
								<label><?= lang("app.recievedAmount"); ?>:</label>
								<input class="form-control" type="number" name="recieved_amount" required>
							</div>
						</div>

					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary"
								data-dismiss="modal"><?= lang("app.close"); ?></button>
						<button type="submit" class="btn btn-gradient-primary"
								data-target="reload"><?= lang("app.saveChanges"); ?></button>
					</div>
				</form>
			</div>
		</div>
	</div>
	<div class="app-drawer-overlay d-none animated fadeIn"></div>
</body>
<?php
}
if ($page == "Deliberation") {
	?>
	<div class="modal fade" id="changeVerdictModal" tabindex="-1" role="dialog">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<form action="<?= base_url('manipulate_change_second_verdict'); ?>" class="autoSubmit validate">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalLabel"><?= lang("app.secondVerdict"); ?></h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">×</span>
						</button>
					</div>
					<div class="modal-body">
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group">
								<input type="hidden" name="fId">
								<label><?= lang("app.verdict"); ?>:</label>
								<select class="select2 form-control" name="second_verdict">
									<option selected disabled><?= lang("app.selectVerdict"); ?></option>
									<?php foreach ($secondverdicts as $secondverdict) { ?>
										<option
												value="<?= $secondverdict['id']; ?>"><?= $secondverdict['title']; ?></option>
										<?php
									}
									?>
								</select>
							</div>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary"
								data-dismiss="modal"><?= lang("app.close"); ?></button>
						<button type="submit" class="btn btn-gradient-primary"
								data-target="reload"><?= lang("app.saveChanges"); ?></button>
					</div>
				</form>
			</div>
		</div>
	</div>
	<?php
}
if ($page == "Verdicts") {
	?>
	<div class="modal fade" id="createVerdict" tabindex="-1" role="dialog">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<form action="<?= base_url('manipulate_verdicts'); ?>" class="autoSubmit validate">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalLabel"><?= lang("app.verdict"); ?></h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">×</span>
						</button>
					</div>
					<div class="modal-body">
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group">
								<label><?= lang("app.title"); ?>:</label>
								<input type="text" name="title" class="form-control">
							</div>
						</div>
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group">
								<label><?= lang("app.verdict"); ?>:</label>
								<select class="select2 form-control" name="type">
									<option selected disabled><?= lang("app.selectType"); ?></option>
									<option value="1"><?= lang("app.firstVerdict"); ?></option>
									<option value="2"><?= lang("app.secondVerdict"); ?></option>
								</select>
							</div>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary"
								data-dismiss="modal"><?= lang("app.close"); ?></button>
						<button type="submit" class="btn btn-gradient-primary"
								data-target="reload"><?= lang("app.save"); ?></button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<div class="modal fade" id="EditVerdict" tabindex="-1" role="dialog">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<form action="<?= base_url('manipulate_verdicts'); ?>" class="autoSubmit validate">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalLabel"><?= lang("app.verdict"); ?></h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">×</span>
						</button>
					</div>
					<div class="modal-body">
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group">
								<input type="hidden" name="fId" required>
								<label><?= lang("app.title"); ?>:</label>
								<input type="text" name="title" class="form-control" required>
							</div>
						</div>
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group">
								<label><?= lang("app.verdict"); ?>:</label>
								<select class="select2 form-control" name="type" required>
									<option selected disabled><?= lang("app.selectType"); ?></option>
									<option value="1"><?= lang("app.firstVerdict"); ?> </option>
									<option value="2"><?= lang("app.secondverdict"); ?> </option>
								</select>
							</div>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary"
								data-dismiss="modal"><?= lang("app.close"); ?></button>
						<button type="submit" class="btn btn-gradient-primary"
								data-target="reload"><?= lang("app.save"); ?></button>
					</div>
				</form>
			</div>
		</div>
	</div>
	<?php
}
if ($page == "settings") {

	?>
	<div class="modal fade" id="DeleteGradeModal" tabindex="-1" role="dialog">
		<div class="modal-dialog modal-sm" role="document">
			<div class="modal-content">
				<form action="<?= base_url('delete_grade'); ?>" class="autoSubmit validate">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalLabel">attention !</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">×</span>
						</button>
					</div>
					<div class="modal-body">
						<input type="hidden" name="fId">
						<center><b>Are you sure to delete ?</b></center>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary"
								data-dismiss="modal">No
						</button>
						<button type="submit" class="btn btn-gradient-primary"
								data-target="reload">Yes
						</button>
					</div>
				</form>
			</div>
		</div>
	</div>
	<?php
}
if ($page == "pendingRegistration") {
	?>
	<!-- edit course -->
	<div class="modal fade" id="documentModal" tabindex="-1" role="dialog">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<form action="<?= base_url('manipulate_course_category'); ?>" class="autoSubmit validate">
					<div class="modal-header">
						<h5 class="modal-title">Applicant document</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">×</span>
						</button>
					</div>
					<div class="modal-body">
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<table class="table">
								<tr>
									<th>#</th>
									<th style='text-align: center'>Name</th>
									<th style='text-align: center'>Download document</th>
								</tr>
								<tbody id="documentTbl">

								</tbody>
							</table>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary"
								data-dismiss="modal"><?= lang("app.close"); ?></button>
					</div>
				</form>
			</div>
		</div>
	</div>
	<div class="modal fade" id="approveRegistrationModal" tabindex="-1" role="dialog">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<form action="<?= base_url('manipulateApproveStudentsRegistration'); ?>" class="autoSubmit validate">
					<div class="modal-header">
						<h5 class="modal-title">Approve applicant</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">×</span>
						</button>
					</div>
					<div class="modal-body">
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group">
								<label><?= lang("app.faculty"); ?>:</label>
								<input type="text" class="form-control" name="faculty" readonly>
								<input type="hidden" name="applicationId">
							</div>
						</div>
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group">
								<label>Department:</label>
								<input type="text" class="form-control" name="dpt" readonly>
							</div>
						</div>
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group">
								<label><?= lang("app.level"); ?>:</label>
								<input type="text" class="form-control" name="level" readonly>
							</div>
						</div>
						<div class="col-sm-12 col-md-12 col-lg-12 pull-left">
							<div class="form-group">
								<label><?= lang("app.classes"); ?>:</label>
								<select class="select2 form-control" name="classId" required id="classesOptions">

								</select>
							</div>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary"
								data-dismiss="modal"><?= lang("app.close"); ?></button>
						<button type="submit" class="btn btn-primary"
								data-target="reload">Approve
						</button>
					</div>
				</form>
			</div>
		</div>
	</div>
	<?php
}
?>
<script type="text/javascript" src="<?= base_url(); ?>assets/plugins/bootstrap/bootstrap.min.js"></script>
<script type="text/javascript" src="<?= base_url(); ?>assets/plugins/datatables/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="<?= base_url(); ?>assets/js/main.js"></script>
<script type="text/javascript" src="<?= base_url(); ?>assets/js/toast.js"></script>
<script type="application/javascript" src="<?= base_url('assets/js/parsley.min.js'); ?>"></script>
<script type="application/javascript" src="<?= base_url('assets/js/parsley-extra-validators.js'); ?>"></script>
<script type="application/javascript" src="<?= base_url('assets/plugins/select2/js/select2.min.js'); ?>"></script>
<script src="<?= base_url('assets/js/inputmask.bundle.min.js'); ?>"></script>
<script type="text/javascript" src="<?= base_url(); ?>assets/js/scripts_v1.1.1.js"></script>
<script src="<?= base_url(); ?>assets/js/Chart.js"></script>
<script src="<?= base_url(); ?>assets/js/Chart.min.js"></script>
<script src="<?= base_url(); ?>assets/js/jquery.flot.js"></script>
<script src="<?= base_url(); ?>assets/js/jquery.flot.pie.js"></script>
<script>
	$(function () {
		$("#borrowType").on("change", function () {
			let type = $(this).val()
			if (type == 2) {
				$(".show-student").hide()
				$(".show-staff").show()
				$("#select_class_book").val('').change()
			} else if (type == 1) {
				$(".show-student").show()
				$(".show-staff").hide()
				$("#selected_staff_book").val('').change()
			}
		})
		let row
		$("#documentModal").on("show.bs.modal", function (e) {
			let id = $(e.relatedTarget).data("id")
			$("#documentTbl").html(" ")
			row = " "
			$.getJSON("<?=base_url();?>registrationsDocument/" + id, function (data) {
				$.each(data, function (index, obj) {
					let docsUrl = "<?=base_url('assets/documents/');?>" + obj.fileName
					row += "<tr>" +
						"<td>" + (index + 1) + "</td>" +
						"<td style='text-align: center'>" + obj.documentName + "</td>" +
						"<td style='text-align: center'><a target='_blank' href=" + docsUrl + ">Click here to download</a</td>" +
						"</tr>"
				})
				$("#documentTbl").html(row)
			})
		})
		$("#approveRegistrationModal").on("show.bs.modal", function (e) {
			let id = $(e.relatedTarget).data("id")
			let options
			$("#classesOptions").html("")
			$.getJSON("<?=base_url();?>getApproveStudentInformation/" + id, function (data) {
				$("#approveRegistrationModal [name='applicationId']").val(id).change()
				$("#approveRegistrationModal [name='faculty']").val(data.structure.faculty).change()
				$("#approveRegistrationModal [name='dpt']").val(data.structure.dpt).change()
				$("#approveRegistrationModal [name='level']").val(data.structure.level).change()

				options += "<option selected disabled>Choose class</option>"
				$.each(data.classes, function (index, obj) {
					options += "<option value='" + obj.id + "'>" + obj.level_name + " " + obj.dept_code + " " + obj.title + "</option>"
				})
				$("#classesOptions").append(options)
			})
		})
		$('#marks_table').dataTable({paging: false});
		$('#example1').dataTable({
			paging: true
		});
		<?php
		if ($active_term == 0) {
			echo "$('#mdlTerm').modal('show');";
		} else if ($_SESSION['soma_status'] == 2) {
			echo "$('#mdlPass').modal('show');";
		}
		?>
		$("#mdlStaff").on("shown.bs.modal", function () {
			$(this).find('[data-toggle="refresh"]').click();
		});
		$("#mdlTerm").on("hidden.bs.modal", function () {
			window.location.reload();
		});
		$("#assignModal").on("show.bs.modal", function (e) {
			var id = $(e.relatedTarget).data("id");
			// alert(id);
			$("#assignModal [name='fId']").val(id).change();
		});

		$(document).on("change", "#select_class_trans", function () {
			var classe = $(this).val();
			var isclass = "/1";
			var type = "/7";
			$.get("<?=base_url();?>get_student/" + classe + isclass + type, function (data) {
				$("[name='select_student_btrans']").html(data);
			});
		});

		$("#mdlmarks").on("show.bs.modal", function (e) {
			var per = $("#useperiod").val();
			if (per == 1) {
				$("#useperiod").show();
				$("#use_label").text("Use period is Enabled")
			} else {
				$("#useperiod").hide();
			}
		});

		$("#marks_type").on("change", function () {
			var mark = $(this).val();
			if (mark == 1 <?=$periodic == 0 ? ' && false' : ' && true';?>
		)
			{
				$('[name="period"]').prop("required", true);
				$("#periodd").show();
			}
		else
			{
				$('[name="period"]').prop("required", false);
				$("#periodd").hide();
			}
		})

		$("#editLecCourseModal").on("shown.bs.modal", function (e) {
			var id = $(e.relatedTarget).data("id");
			$("#editLecCourseModal [name='fid']").val(id).change();
		});
		$("#select_dept").on("change", function () {
			var dept = $(this).val();
			$.get("<?=base_url();?>get_level/" + dept, function (data) {
				$("[name='level']").html(data);
			});
		});

		//search student
		$(document).ready(function () {
			$(".select31").select2({
				dropdownParent: $('#attendanceMdl'),
				ajax: {
					url: "<?=base_url('search_student');?>",
					type: "post",
					dataType: 'json',
					delay: 250,
					data: function (params) {
						return {
							searchTerm: params.term // search term
						};
					},
					processResults: function (response) {
						return {
							results: response
						};
					},
					cache: true
				},
				placeholder: "Search by student name or Reg noo",
				minimumInputLength: 3
			});
		});
		$("#search_student31").on('select2:select', function (selection) {
			formatRepoSelection2(selection.params.data);
		});
		$("#deny_Modal").on("show.bs.modal", function (e) {
			var id = $(e.relatedTarget).data("id");
			var email = $(e.relatedTarget).data("mail");
			// $("#approvebtn"+id).prop("disabled",true);
			$("#deny_Modal [name='fId']").val(id).change();
			$("#deny_Modal [name='email']").val(email).change();
			// alert(email);
			return;
		});
		$("#approve_Modal").on("show.bs.modal", function (e) {
			var id = $(e.relatedTarget).data("id");
			var staff = $(e.relatedTarget).data("name");
			var email = $(e.relatedTarget).data("mail");
			// alert(email);
			$("#approve_Modal [name='fId']").val(id).change();
			$("#approve_Modal [name='email']").val(email).change();
			$("#stafflabel").text(staff);
			return;
		});
		// $("#reason").hide();
		$("#leave_type").on("change", function (e) {
			var types = $(this).val();
			if (types == 4) {
				$("#reason").show();
			} else {
				$("#reason").hide();
			}
		});
		// $("#select2Input").select2({ dropdownParent: "#modal-container" });
		$("#editBook").on("show.bs.modal", function (e) {
			var id = $(e.relatedTarget).data("id");
			$.getJSON("<?=base_url();?>get_book/" + id, function (data) {
				// alert(id);
				$("#editBook [name='fId']").val(data.id).change();
				$("#editBook [name='title']").val(data.title).change();
				$("#editBook [name='author']").val(data.author).change();
				$("#editBook [name='quantity']").val(data.quantity).change();
				$("#editBook [name='category']").val(data.category).trigger('change');
			});
			return;
		});

		$("#returnBook").on("show.bs.modal", function (e) {
			var id = $(e.relatedTarget).data("id");
			// alert(id);
			$("#returnBook [name='record_id']").val(id).change();
		});

		$("#borrowBook").on("show.bs.modal", function (e) {
			var id = $(e.relatedTarget).data("id");
			$.getJSON("<?=base_url();?>get_book/" + id, function (data) {
				// alert(id);
				$("#borrowBook [name='bookId']").val(data.id).change();
				$("#borrowBook [name='title']").val(data.title).change();
			});
			return;
		});
		$("#select_class_book").on("change", function () {
			var classe = $(this).val();
			var isclass = "/1";
			var type = "/7";
			$.get("<?=base_url();?>get_student/" + classe + isclass + type, function (data) {
				$("[name='select_student_book']").html(data);
			});
		});

		$("#Change_student_Mode").on("show.bs.modal", function (e) {
			var id = $(e.relatedTarget).data("id");
			// alert(id);
			$.getJSON("<?=base_url();?>get_student_change/" + id, function (data) {
				$("#Change_student_Mode [name='fId']").val(data.id).change();
				$("#Change_student_Mode [name='mode']").val(data.studying_mode).trigger('change');
			});
			return;
		});

		$("#Change_student_Class").on("show.bs.modal", function (e) {
			var id = $(e.relatedTarget).data("id");
			$.getJSON("<?=base_url();?>get_student_change/" + id, function (data) {
				// alert(data.class_id);
				$("#Change_student_Class [name='fId']").val(data.id).change();
				$("#Change_student_Class [name='classe']").val(data.classe).trigger('change');
			});
			return;
		});
		$("#editRoute").on("show.bs.modal", function (e) {
			var id = $(e.relatedTarget).data("id");
			$.getJSON("<?=base_url();?>get_route/" + id, function (data) {
				// alert(data.class_id);
				$("#editRoute [name='fId']").val(data.id).change();
				$("#editRoute [name='title']").val(data.title).change();
				$("#editRoute [name='price']").val(data.price).change();
				$("#editRoute [name='details']").val(data.details).change();
			});
			return;
		});
	});

	$(function () {
		$("#editBusDriver").on("show.bs.modal", function (e) {
			var id = $(e.relatedTarget).data("id");
			$.getJSON("<?=base_url();?>get_bus/" + id, function (data) {
				// alert(data.class_id);
				$("#editBusDriver [name='fId']").val(data.id).change();
				$("#editBusDriver [name='staff']").val(data.staff_id).trigger('change');
			});
			return;
		});
		$("#editBus").on("show.bs.modal", function (e) {
			var id = $(e.relatedTarget).data("id");
			$.getJSON("<?=base_url();?>get_bus/" + id, function (data) {
				// alert(data.class_id);
				$("#editBus [name='fId']").val(data.id).change();
				$("#editBus [name='plate']").val(data.plate).change();
				$("#editBus [name='car_maker']").val(data.car_maker).change();
				$("#editBus [name='car_model']").val(data.car_model).change();
				$("#editBus [name='car_year']").val(data.car_year).change();
				$("#editBus [name='places']").val(data.places).change();
			});
			return;
		});
		$("#editTermModal").on("show.bs.modal", function (e) {
			var id = $(e.relatedTarget).data("id");
			var classe = $(e.relatedTarget).data("name");
			$.getJSON("<?=base_url();?>get_coure_term/" + id + "/" + classe, function (data) {
				// alert(data.class_id);
				$("#editTermModal [name='fId']").val(data.id).change();
				var Values = new Array();
				for (i = 0; i <= data.term.length; i++) {
					Values.push(data.term[i]);
				}
				$("#editTermModal [name='Term[]']").val(Values).trigger('change');
			});
			return;
		});
		$("#editCourseModal").on("show.bs.modal", function (e) {
			var id = $(e.relatedTarget).data("id");
			var course = $(e.relatedTarget).data("name");
			$("#editCourseModal [name='fId']").val(id);
			$("#editCourseModal [name='courseName']").val(course);
			return;
		});
		$("#changeVerdictModal").on("show.bs.modal", function (e) {
			var id = $(e.relatedTarget).data("id");
			$.getJSON("<?=base_url();?>get_student_verdit/" + id, function (data) {
				$("#changeVerdictModal [name='fId']").val(data.id).change();
				$("#changeVerdictModal [name='second_verdict']").val(data.second_verdict).trigger('change');
			});
			return;
		});
		$("#EditVerdict").on("show.bs.modal", function (e) {
			var id = $(e.relatedTarget).data("id");
			$.getJSON("<?=base_url();?>get_verdit/" + id, function (data) {
				$("#EditVerdict [name='fId']").val(data.id).change();
				$("#EditVerdict [name='title']").val(data.title).change();
				$("#EditVerdict [name='type']").val(data.type).trigger('change');
			});
			return;
		});

		$("#editLecCourseModal").on("show.bs.modal", function (e) {
			var id = $(e.relatedTarget).data("id");
			$.getJSON("<?=base_url();?>get_course_record/" + id, function (data) {
				$("#editLecCourseModal [name='fId']").val(data.id).change();
				$("#editLecCourseModal [name='teacher']").val(data.lecturer).trigger('change');
			});
			return;
		});
		$("#DeleteGradeModal").on("show.bs.modal", function (e) {
			var id = $(e.relatedTarget).data("id");
			$("#DeleteGradeModal [name='fId']").val(id).change();
		});
		$("#a-create-academic").on('click', function () {
			$('#dv-existing-academic').hide();
			$('#dv-new-academic').css('display', 'inline-block');
			$('#academic-creation-overlay').show();
		});
		$("#lbl-cancel-academic").on('click', function () {
			$('#dv-existing-academic').css('display', 'inline-block');
			$('#dv-new-academic').hide();
			$('#academic-creation-overlay').slideUp();
		});
		$('#btn-save-academic').on('click', function () {
			const btn = $(this);
			const btn_txt = btn.text();
			btn.text('Please wait...').prop('disabled', true);
			$.post(base_url + 'save_academic_year', 'title=' + $('#dv-new-academic [name="academic_title"]').val(), function (data) {
				btn.text(btn_txt).prop("disabled", false);
				toastada.success(data.message);
				$('#dv-existing-academic').css('display', 'inline-block');
				$('#dv-new-academic').hide();
				$('#academic-creation-overlay').slideUp();
				$('#dv-existing-academic [name="academic_year"]').append('<option value="' + data.id + '" selected>' + data.title + '</option>')
			}).fail(function (data) {
				//unknown error
				btn.text(btn_txt).prop("disabled", false);
				console.log(data);
				if (data.hasOwnProperty('responseJSON')) {
					toastada.error(data.responseJSON.message);
				} else
					toastada.error("System server error, please try again later");
			});
		});
		$("#editCourseModal").on("show.bs.modal", function (e) {
			var id = $(e.relatedTarget).data("id");
			$.getJSON("<?=base_url();?>getSingleCourseAjax/" + id, function (data) {
				$("#editCourseModal [name='courseId']").val(data.id).change();
				$("#editCourseModal [name='title']").val(data.title).change();
				$("#editCourseModal [name='code']").val(data.code).change();
				$("#editCourseModal [name='category']").val(data.category).trigger('change');
				$("#editCourseModal [name='credit']").val(data.credit).change();
				$("#editCourseModal [name='marks']").val(data.marks).change();
			});
			return;
		});
	});

	function formatRepoSelection2(repo) {
		var id = repo.id;
		$('input[name="student_id"]').val(id);

	}
</script>

</body>
</html>
