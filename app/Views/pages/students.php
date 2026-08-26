<style>
.students-active-year {
	display: inline-flex;
	align-items: center;
	gap: 8px;
	margin: 14px 0 0 4px;
	padding: 8px 14px;
	background: linear-gradient(135deg, #eff6ff, #dbeafe);
	border: 1px solid #93c5fd;
	border-radius: 10px;
	font-size: 13px;
	font-weight: 600;
	color: #1e40af;
}
.students-active-year i { opacity: .85; }
.students-visitor-alert {
	display: inline-flex;
	align-items: center;
	gap: 10px;
	margin: 10px 0 0 4px;
	padding: 10px 16px;
	background: #fffbeb;
	border: 1px solid #fcd34d;
	border-radius: 10px;
	font-size: 13px;
	color: #92400e;
}
.students-visitor-alert .counter {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-width: 28px;
	height: 28px;
	padding: 0 8px;
	border-radius: 999px;
	background: #f59e0b;
	color: #fff;
	font-weight: 700;
	font-size: 14px;
}
.students-visitor-alert a {
	color: #b45309;
	font-weight: 600;
	text-decoration: underline;
}
.st-visitor-badge {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	margin-top: 4px;
	padding: 2px 8px;
	border-radius: 999px;
	background: #fef3c7;
	color: #92400e;
	font-size: 11px;
	font-weight: 700;
	white-space: nowrap;
}
.st-visitor-badge i { font-size: 10px; }
.st-sms-actions { display:flex; gap:8px; align-items:center; justify-content:flex-end; flex-wrap:wrap; }
.btn-send-admission-sms { white-space:nowrap; }
.btn-resend-admission-sms { white-space:nowrap; }
.st-sms-check { width:16px; height:16px; cursor:pointer; }
#admissionSmsAlert { margin: 8px 0 12px; }
.btn-move-student { white-space:nowrap; }
#moveStudentClassModal .move-note {
	font-size: 13px;
	color: #475569;
	background: #f8fafc;
	border: 1px solid #e2e8f0;
	border-radius: 8px;
	padding: 10px 12px;
	margin-bottom: 14px;
}
.admission-sms-approved {
	display: inline-block;
	font-size: 12px;
	font-weight: 700;
	padding: 5px 10px;
	border-radius: 999px;
	background: #16a34a;
	color: #fff;
	vertical-align: middle;
}
</style>
<div class="app-inner-layout app-inner-layout-page">
	<div class="app-inner-layout__wrapper">
		<div class="app-inner-layout__content">
			<div class="tab-content">
				<div class="container-fluid">
					<div class="card mb-3">
						<div class="card-header-tab card-header">
							<div
								class="card-header-title font-size-lg text-capitalize font-weight-normal">
								<i class="header-icon typcn typcn-home-outline text-muted opacity-6"> </i><?= $title; ?>
							</div>
							<form id="view_students_form" style="width: 100%">
								<div class="form-group col-sm-3" style="margin-top: 18px;display: inline-block">
									<select class="select2" id="choose_class" name="c">
										<option disabled <?= ($class_id == '-1' || $class_id === '') ? 'selected' : '' ?>><?= lang("app.chooseClass"); ?></option>
										<?php
										foreach ($classes as $classe):
											echo "<option value='{$classe['id']}' ".($class_id==$classe['id']?'selected':'').">{$classe['level_name']} {$classe['dept_code']} {$classe['title']}</option>";
										endforeach;
										?>
									</select>
								</div>
								<div class="form-group col-sm-3" style="margin-top: 18px;display: inline-block">
									<select class="select2" id="choose_year" name="y">
										<option disabled <?= ($academic_year == '-1' || $academic_year === '') ? 'selected' : '' ?>><?= lang("app.academicYear"); ?></option>
										<?php
										foreach ($years as $year):
											$sel = ((string)$academic_year === (string)$year['id']) ? 'selected' : '';
											$activeMark = ((int)($active_year_id ?? 0) === (int)$year['id']) ? ' ★' : '';
											echo "<option value='{$year['id']}' {$sel}>{$year['title']}{$activeMark}</option>";
										endforeach;
										?>
									</select>
								</div>
								<button type="submit" value="true" class="btn btn-primary">
									<?= lang("app.viewStudents"); ?>
								</button>
							</form>
							<?php if (!empty($active_year_title)): ?>
								<div class="students-active-year">
									<i class="fa fa-calendar-check-o"></i>
									<span>Active academic year: <strong><?= esc($active_year_title) ?></strong><?php if (!empty($active_term_label)): ?> — <?= esc($active_term_label) ?><?php endif; ?></span>
								</div>
							<?php endif; ?>
							<?php if (!empty($visitors_no_card_total) && (int)$visitors_no_card_total > 0): ?>
								<div class="students-visitor-alert">
									<span class="counter"><?= (int) $visitors_no_card_total ?></span>
									<span>
										visitor<?= (int)$visitors_no_card_total === 1 ? '' : 's' ?> registered without RFID card.
										<a href="<?= base_url('parent_visiting/assign') ?>">Assign cards →</a>
									</span>
								</div>
							<?php endif; ?>
							<div class="btn-actions-pane-right actions-icon-btn">
								<div class="btn-group dropdown">
									<button type="button" data-toggle="dropdown" aria-haspopup="true"
											aria-expanded="false"
											class="btn-icon btn-icon-only btn btn-link"><i
											class="typcn typcn-th-menu-outline" style="font-size: 16pt"></i></button>
									<div tabindex="-1" role="menu" aria-hidden="true"
										 class="dropdown-menu-right rm-pointers dropdown-menu-shadow dropdown-menu-hover-link dropdown-menu">
										<h6 tabindex="-1" class="dropdown-header">
											<?= lang("app.studentMenu"); ?></h6>
										<a type="button" tabindex="0" href="<?= base_url('register-student'); ?>"
										   class="dropdown-item"><i
												class="typcn typcn-plus"> </i><span><?= lang("app.AddnewStudent"); ?></span>
										</a>
									</div>
								</div>
							</div>
						</div>
						<div class="col-sm-12">
							<?php if (isset($_SESSION['success'])) {
								?>
								<div class="alert alert-success">
									<h5><?= lang("app.success");?></h5>
									<p><?= $_SESSION['success']; ?></p>
								</div>
								<?php
							}
							?>
							<?php if (isset($_SESSION['error'])) {
								?>
								<div class="alert alert-danger">
									<h5><?= lang("app.sError");?></h5>
									<p><?= $_SESSION['error']; ?></p>
								</div>
								<?php
							}
							?>
						</div>
						<?php
						if (count($students) == 0)
							return;
						$visitorsNoCardMap = $visitors_no_card_map ?? [];
						$admission_sms_map = $admission_sms_map ?? [];
						$currentClassLabel = '';
						foreach ($classes as $cRow) {
							if ((string) $cRow['id'] === (string) $class_id) {
								$currentClassLabel = trim(($cRow['level_name'] ?? '') . ' ' . ($cRow['dept_code'] ?? '') . ' ' . ($cRow['title'] ?? ''));
								break;
							}
						}
						?>
						<div class="card-body">
							<div id="example_wrapper" class="dataTables_wrapper dt-bootstrap4">
								<div class="row">
									<div class="col-sm-12">
										<div class="col-sm-12">
											<div id="admissionSmsAlert" class="alert d-none" role="alert"></div>
											<div class="st-sms-actions pull-right">
												<button type="button" id="btnMoveStudentsSelected" class="btn btn-secondary">
													<i class="fa fa-exchange-alt"></i> Move selected
												</button>
												<button type="button" id="btnSendAdmissionSmsSelected" class="btn btn-info">
													<i class="fa fa-paper-plane"></i> Send to selected
												</button>
												<a href="<?=base_url('export_student_list/'.$class_id.'/'.$academic_year);?>" target="_blank" class="btn btn-success">
													<?= lang("app.exporttoExcel"); ?>
												</a>
											</div>
										</div>
										<table style="width: 100%;" id="example"
											   class="table table-hover table-striped table-bordered dataTable dtr-inline"
											   role="grid" aria-describedby="example_info">
											<thead>
											<tr role="row">
												<th data-orderable="false"><input type="checkbox" id="stSmsSelectAll" class="st-sms-check" title="Select all"></th>
												<th><?= lang("app.no"); ?></th>
												<th><?= lang("app.regno"); ?></th>
												<th><?= lang("app.names"); ?></th>
												<th><?= lang("app.mode"); ?></th>
												<th><?= lang("app.gender"); ?></th>
												<th><?= lang("app.sClass"); ?></th>
												<th><?= lang("app.activeParent"); ?></th>
												<th>Visitors</th>
												<th></th>
											</tr>
											</thead>
											<tbody>
											<?php
											$a = 1;
											foreach ($students as $student) {
												$status = $student['status'] == 1 || $student['status'] == 2 ? '<label class="text-success lnk" data-toggle="update" data-href="change_status/student/0" data-target="' . $student['id'] . '" data-target-record="' . $student['record_id'] . '">'.lang("app.active").'</label>'
													: '<label class="text-danger lnk" data-toggle="update" data-href="change_status/student/1" data-target="' . $student['id'] . '" data-target-record="' . $student['record_id'] . '">'.lang("app.locked").'</label>';
												$parent = '';
												if (strlen($student['father']) > 3) {
													$parent = "<span class='badge badge-pill badge-success'>".lang("app.father")."</span> " . $student['father'] . "<br><a href='tel:{$student['ft_phone']}'>{$student['ft_phone']}</a>";
												} else if (strlen($student['mother']) > 3) {
													$parent = "<span class='badge badge-pill badge-info'>".lang("app.mother")."</span> " . $student['mother'] . "<br><a href='tel:{$student['mt_phone']}'>{$student['mt_phone']}</a>";
												} else if (strlen($student['guardian']) > 3) {
													$parent = "<span class='badge badge-pill badge-primary'>".lang("app.guardian")."</span> " . $student['guardian'] . "<br><a href='tel:{$student['gd_phone']}'>{$student['gd_phone']}</a>";
												}
												$noCard = (int) ($visitorsNoCardMap[(int)$student['id']] ?? 0);
												$visitorCell = '—';
												if ($noCard > 0) {
													$visitorCell = '<span class="st-visitor-badge" title="Visitors without RFID card">'
														. '<i class="fa fa-id-card"></i> '
														. $noCard . ' no card</span>';
												}
												$smsApproved = !empty($admission_sms_map[(int)$student['id']]);
												?>
												<tr>
													<td><input type="checkbox" class="st-sms-check" value="<?= (int) $student['id']; ?>"></td>
													<td><?= $a; ?></td>
													<td><a href="<?= base_url('student/' . $student['id']); ?>"
														   class="link"><?= $student['regno']; ?></a></td>
													<td><?= $student['fname'] . ' ' . $student['lname']; ?></td>
													<td><?= \App\Controllers\Home::ModeToStr($student['studying_mode']); ?></td>
													<td><?= $student['sex']; ?></td>
													<td><?= $student['level'] . ' ' . $student['dept_code'] . ' ' . $student['class']; ?></td>
													<td><?= $parent; ?></td>
													<td><?= $visitorCell; ?></td>
													<td>
														<button type="button"
																class="btn btn-sm btn-outline-secondary btn-move-student"
																data-id="<?= (int) $student['id']; ?>"
																data-name="<?= esc($student['fname'] . ' ' . $student['lname']); ?>"
																title="Move to another class">Move</button>
														<?php if ($smsApproved): ?>
															<span class="admission-sms-approved" data-id="<?= (int) $student['id']; ?>">Approved</span>
														<?php else: ?>
														<button type="button"
																class="btn btn-sm btn-primary btn-send-admission-sms"
																data-id="<?= (int) $student['id']; ?>"
																title="Send admission confirmation SMS">Approve</button>
														<?php endif; ?>
														<button type="button"
																class="btn btn-sm btn-outline-info btn-resend-admission-sms"
																data-id="<?= (int) $student['id']; ?>"
																title="Resend admission confirmation SMS">
															<i class="fa fa-paper-plane"></i> Resend
														</button>
														<label class="typcn typcn-delete text-danger link"
															   data-toggle="delete"
															   data-title="Student #<?= $student['fname']; ?>"
															   data-target="<?= $student['id']; ?>"
															   data-href="delete_student"><?= lang("app.del");?></label> |
														<?=$status;?>
													</td>
												</tr>
												<?php
												$a++;
											}
											?>
											</tbody>
											<tfoot>
											<tr>
												<th></th>
												<th><?= lang("app.no"); ?></th>
												<th><?= lang("app.regNo"); ?></th>
												<th><?= lang("app.names"); ?></th>
												<th><?= lang("app.mode"); ?></th>
												<th><?= lang("app.gender"); ?></th>
												<th><?= lang("app.sClass"); ?></th>
												<th><?= lang("app.activeParent"); ?></th>
												<th>Visitors</th>
												<th></th>
											</tr>
											</tfoot>
										</table>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
<div class="modal fade" id="moveStudentClassModal" tabindex="-1" role="dialog" aria-labelledby="moveStudentClassTitle">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="moveStudentClassTitle">Move to another class</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<div id="moveStudentModalAlert" class="alert d-none" role="alert"></div>
				<div class="move-note">
					Marks, paid fees, attendance, visitors, cards and other student records stay with the student.
					Only the class enrollment for this academic year changes.
				</div>
				<p id="moveStudentSummary" style="font-weight:600;margin-bottom:12px;"></p>
				<div class="form-group">
					<label>From</label>
					<input type="text" class="form-control" value="<?= esc($currentClassLabel); ?>" readonly>
				</div>
				<div class="form-group">
					<label>To class</label>
					<select id="moveStudentToClass" class="form-control" style="width:100%">
						<option value="">Select destination class</option>
						<?php foreach ($classes as $classe): ?>
							<?php if ((string) $classe['id'] === (string) $class_id) { continue; } ?>
							<option value="<?= (int) $classe['id']; ?>">
								<?= esc(trim(($classe['level_name'] ?? '') . ' ' . ($classe['dept_code'] ?? '') . ' ' . ($classe['title'] ?? ''))); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
				<button type="button" class="btn btn-primary" id="btnConfirmMoveStudents">Move</button>
			</div>
		</div>
	</div>
</div>
<script>
(function ($) {
	var SMS_API = "<?= site_url('sendStudentAdmissionSms'); ?>";
	var MOVE_API = "<?= site_url('move_students_class'); ?>";
	var YEAR_ID = "<?= (int) $academic_year; ?>";
	var FROM_CLASS = <?= (int) $class_id; ?>;
	var csrfName = $('meta[name="csrf-token-name"]').attr('content');
	var csrfHash = $('meta[name="csrf-token-value"]').attr('content');

	function withCsrf(data) {
		data = data || {};
		if (csrfName && csrfHash) {
			data[csrfName] = csrfHash;
		}
		return data;
	}

	function tableApi() {
		if ($.fn.DataTable && $.fn.DataTable.isDataTable('#example')) {
			return $('#example').DataTable();
		}
		return null;
	}

	function selectedIds() {
		var ids = [];
		var dt = tableApi();
		var $boxes = dt ? dt.$('input.st-sms-check') : $('#example input.st-sms-check');
		$boxes.filter(':checked').each(function () {
			var id = parseInt($(this).val(), 10);
			if (id > 0) {
				ids.push(id);
			}
		});
		return ids;
	}

	function showAlert(kind, msg) {
		var $el = $('#admissionSmsAlert');
		$el.removeClass('d-none alert-info alert-danger alert-success alert-warning')
			.addClass('alert-' + kind)
			.text(msg)
			.show();
	}

	function markApproved(ids) {
		ids = ids || [];
		var dt = tableApi();
		ids.forEach(function (id) {
			id = parseInt(id, 10);
			if (!id) {
				return;
			}
			var $btns = dt ? dt.$('.btn-send-admission-sms[data-id="' + id + '"]') : $('.btn-send-admission-sms[data-id="' + id + '"]');
			$btns.replaceWith('<span class="admission-sms-approved" data-id="' + id + '">Approved</span>');
		});
	}

	function sendSms(ids, $btn) {
		if (!ids.length) {
			showAlert('warning', 'Select at least one student.');
			return;
		}
		var isApproveBtn = $btn && $btn.hasClass('btn-send-admission-sms');
		var isResendBtn = $btn && $btn.hasClass('btn-resend-admission-sms');
		var origHtml = $btn ? $btn.html() : '';
		if ($btn) {
			$btn.prop('disabled', true).html(isResendBtn
				? '<i class="fa fa-spinner fa-spin"></i> Sending…'
				: 'Sending…');
		}
		$('#btnSendAdmissionSmsSelected').prop('disabled', true);
		showAlert('info', isResendBtn ? 'Resending admission SMS…' : 'Sending admission SMS…');
		$.ajax({
			url: SMS_API,
			type: 'POST',
			dataType: 'json',
			data: withCsrf({ studentIds: ids, year: YEAR_ID })
		}).done(function (res) {
			if (res && res.success) {
				showAlert('success', res.message || (isResendBtn ? 'Admission SMS resent.' : 'Admission SMS sent.'));
				markApproved(res.sentIds && res.sentIds.length ? res.sentIds : ids);
			} else {
				showAlert('danger', (res && (res.error || res.message)) || 'SMS was not sent.');
				if (isApproveBtn) {
					$btn.prop('disabled', false).text('Approve');
				}
			}
		}).fail(function () {
			showAlert('danger', 'Could not send SMS. Please try again.');
			if (isApproveBtn) {
				$btn.prop('disabled', false).text('Approve');
			}
		}).always(function () {
			$('#btnSendAdmissionSmsSelected').prop('disabled', false).text('Send to selected');
			if (isResendBtn && $btn && $btn.length) {
				$btn.prop('disabled', false).html(origHtml);
			}
		});
	}

	$(document).on('change', '#stSmsSelectAll', function () {
		var checked = this.checked;
		var dt = tableApi();
		var $boxes = dt ? dt.$('input.st-sms-check') : $('#example tbody input.st-sms-check');
		$boxes.prop('checked', checked);
	});

	$(document).on('click', '#btnSendAdmissionSmsSelected', function () {
		var ids = selectedIds();
		if (!ids.length) {
			showAlert('warning', 'Select students first, then click Send to selected.');
			return;
		}
		if (!confirm('Send the admission SMS to ' + ids.length + ' selected student(s)?')) {
			return;
		}
		sendSms(ids, $(this));
	});

	$(document).on('click', '.btn-send-admission-sms', function () {
		var id = parseInt($(this).data('id'), 10);
		if (!id) {
			return;
		}
		if (!confirm('Send the admission confirmation SMS for this student?')) {
			return;
		}
		sendSms([id], $(this));
	});

	$(document).on('click', '.btn-resend-admission-sms', function () {
		var id = parseInt($(this).data('id'), 10);
		if (!id) {
			return;
		}
		if (!confirm('Resend the admission confirmation SMS for this student?')) {
			return;
		}
		sendSms([id], $(this));
	});

	var pendingMoveIds = [];
	var moveInFlight = false;

	function showMoveModalAlert(kind, msg) {
		var $el = $('#moveStudentModalAlert');
		$el.removeClass('d-none alert-info alert-danger alert-success alert-warning')
			.addClass('alert-' + kind)
			.text(msg)
			.show();
	}

	function openMoveModal(ids, names) {
		pendingMoveIds = ids || [];
		var summary = pendingMoveIds.length === 1
			? ('Move ' + (names && names[0] ? names[0] : 'this student') + ' to another class.')
			: ('Move ' + pendingMoveIds.length + ' selected students to another class.');
		$('#moveStudentSummary').text(summary);
		$('#moveStudentToClass').val('');
		$('#moveStudentModalAlert').addClass('d-none').text('');
		$('#moveStudentClassModal').modal('show');
	}

	function submitMove() {
		if (moveInFlight) {
			return;
		}
		var toClass = parseInt($('#moveStudentToClass').val(), 10);
		if (!pendingMoveIds.length) {
			showMoveModalAlert('warning', 'Select at least one student.');
			return;
		}
		if (!toClass) {
			showMoveModalAlert('warning', 'Choose the destination class.');
			return;
		}
		moveInFlight = true;
		var $btn = $('#btnConfirmMoveStudents');
		$btn.prop('disabled', true).text('Moving…');
		showMoveModalAlert('info', 'Moving student(s)…');
		$.ajax({
			url: MOVE_API,
			type: 'POST',
			dataType: 'json',
			data: withCsrf({
				studentIds: pendingMoveIds.join(','),
				fromClass: FROM_CLASS,
				toClass: toClass,
				year: YEAR_ID
			})
		}).done(function (res) {
			if (res && res.success) {
				$('#moveStudentClassModal').modal('hide');
				showAlert('success', res.message || 'Students moved.');
				window.setTimeout(function () {
					window.location.reload();
				}, 700);
			} else {
				moveInFlight = false;
				showMoveModalAlert('danger', (res && (res.error || res.message)) || 'Could not move students.');
			}
		}).fail(function () {
			moveInFlight = false;
			showMoveModalAlert('danger', 'Could not move students. Please try again.');
		}).always(function () {
			$btn.prop('disabled', false).text('Move');
		});
	}

	$(document).on('click', '#btnMoveStudentsSelected', function () {
		var ids = selectedIds();
		if (!ids.length) {
			showAlert('warning', 'Select students first, then click Move selected.');
			return;
		}
		openMoveModal(ids);
	});

	$(document).on('click', '.btn-move-student', function () {
		var id = parseInt($(this).data('id'), 10);
		var name = $(this).data('name') || '';
		if (!id) {
			return;
		}
		openMoveModal([id], [name]);
	});

	$(document).on('click', '#btnConfirmMoveStudents', function () {
		submitMove();
	});
})(jQuery);
</script>
