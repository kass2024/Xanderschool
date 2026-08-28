<div class="app-inner-layout app-inner-layout-page">
<link rel="stylesheet" href="<?= base_url('assets/css/card-scan-ui.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/staff-share-access.css') ?>">
	<div class="app-inner-layout__wrapper">
		<div class="app-inner-layout__content">
			<div class="tab-content">
				<div class="container-fluid">
					<div class="card mb-3">
						<div class="card-header-tab card-header">
							<div class="card-header-title font-size-lg text-capitalize font-weight-normal">
								<i class="header-icon typcn typcn-home-outline text-muted opacity-6"> </i><?=$title;?>
							</div>
							<div class="btn-actions-pane-right actions-icon-btn">
								<div class="btn-group dropdown">
									<button type="button" data-toggle="dropdown" aria-haspopup="true"
											aria-expanded="false"
											class="btn-icon btn-icon-only btn btn-link"><i
											class="typcn typcn-th-menu-outline" style="font-size: 16pt"></i></button>
									<div tabindex="-1" role="menu" aria-hidden="true"
										 class="dropdown-menu-right rm-pointers dropdown-menu-shadow dropdown-menu-hover-link dropdown-menu">
										<h6 tabindex="-1" class="dropdown-header">
											<?= lang("app.staffMenu");?></h6>
										<a type="button" tabindex="0" href="javascript:void" class="dropdown-item" data-toggle="modal" data-target="#mdlStaff"><i
												class="typcn typcn-plus"> </i><span><?= lang("app.addnewStaff");?></span>
										</a>
										<div class="dropdown-divider"></div>
										<h6 tabindex="-1" class="dropdown-header">Share access (all staff)</h6>
										<a href="javascript:void(0)" class="dropdown-item btn-share-all-staff" data-channel="sms"><i class="fa fa-sms"></i> Reset &amp; share via SMS</a>
										<a href="javascript:void(0)" class="dropdown-item btn-share-all-staff" data-channel="email"><i class="fa fa-envelope"></i> Reset &amp; share via Email</a>
										<a href="javascript:void(0)" class="dropdown-item btn-share-all-staff" data-channel="both"><i class="fa fa-share-alt"></i> Reset &amp; share via SMS + Email</a>
									</div>
								</div>
							</div>
						</div>
						<div class="card-body">
							<div id="example_wrapper" class="dataTables_wrapper dt-bootstrap4">
								<div class="row">
									<div class="col-sm-12">
										<table style="width: 100%;" id="example"
											   class=" table-hover table-striped table-bordered">
											<thead>
											<tr role="row">
												<th><?= lang("app.names");?></th>
												<th><?= lang("app.phone");?></th>
												<th><?= lang("app.email");?></th>
												<th>RFID card</th>
												<th>Face</th>
												<th><?= lang("app.privilege");?></th>
												<th><?= lang("app.shift");?></th>
												<th><?= lang("app.lastLogin");?></th>
												<th><?= lang("app.createdTime");?> </th>
												<th><?= lang("app.status");?></th>
												<th class="staff-actions-col">Actions</th>
											</tr>
											</thead>
											<tbody>
											<?php
											foreach ($staffs as $staff) {
												$status = $staff['status']==1 || $staff['status']==2?'<label class="text-success lnk" data-toggle="update" data-href="change_status/staff/0" data-target="'.$staff["id"].'">'.lang("app.active").'</label>'
													:'<label class="text-danger lnk" data-toggle="update" data-href="change_status/staff/1" data-target="'.$staff["id"].'">'.lang("app.locked").'</label>';
												$disable = $_SESSION['soma_id']==$staff['id']?"onclick='return false'":"";
												$shift = $staff['shift_id']==0?"<button href='#' style='text-align: center' data-target='#assignShiftMdl' data-toggle='modal' class='btn btn-sm btn-inverse'><i class='fa fa-plus'>".lang("app.assignShift")."</i></button>"
													:"<label data-target='#assignShiftMdl' data-toggle='modal'>".$staff['shift_title']." <i class='fa fa-pen'></i> </label>";

												$post = $staff['post']==0?"<button href='#' style='text-align: center' data-target='#changePostMdl' data-toggle='modal' class='btn btn-sm btn-inverse'><i class='fa fa-plus'>".lang("app.add")."</i></button>"
													:"<label data-target='#changePostMdl' data-toggle='modal'>".$staff['post_title']." <i class='fa fa-pen'></i> </label>";

												$cardCell = !empty($staff['card'])
													? '<span class="badge badge-success card-scan-uid-badge">'.esc($staff['card']).'</span>'
													: '<span class="badge badge-secondary">NOT ASSIGNED</span>';
												$hasCard = !empty($staff['card']);
												$hasFace = !empty($staff['face_enrolled']);
												$staffName = esc($staff['fname'].' '.$staff['lname']);
												?>
											<tr data-id="<?=$staff['id'];?>">
												<td><a href="<?=base_url('staff/'.$staff['id']);?>" class="link"><?=$staffName;?></a></td>
												<td><?=$staff['phone'];?></td>
												<td><?=$staff['email'];?></td>
												<td><?=$cardCell;?></td>
												<td><?= $hasFace
													? '<span class="badge badge-success">ENROLLED</span>'
													: '<span class="badge badge-secondary">NO FACE</span>'; ?></td>
												<td data-post="<?=$staff['post'];?>" data-post_title="<?=$staff['post_title'];?>"><?=$post;?></td>
												<td data-shift="<?=$staff['shift_id'];?>" data-shift_title="<?=$staff['shift_title'];?>"><?=$shift;?></td>
												<td><?=date("Y-d-m H:i:s",$staff['last_login']);?></td>
												<td><?=$staff['created_at'];?></td>
												<td><?=$status;?></td>
												<td class="staff-actions-cell">
													<div class="staff-actions-wrap">
														<div class="staff-card-btns">
															<?php if ($hasCard): ?>
																<button type="button" class="btn btn-sm btn-warning btn-staff-change-card"
																	data-id="<?= (int)$staff['id']; ?>"
																	data-name="<?= $staffName; ?>"
																	data-card="<?= esc($staff['card']); ?>"
																	title="Change RFID card">
																	<i class="fa fa-sync"></i> Change
																</button>
																<button type="button" class="btn btn-sm btn-outline-danger btn-staff-remove-card"
																	data-id="<?= (int)$staff['id']; ?>"
																	data-name="<?= $staffName; ?>"
																	title="Remove RFID card">
																	<i class="fa fa-id-card"></i> Remove card
																</button>
															<?php else: ?>
																<button type="button" class="btn btn-sm btn-primary btn-staff-assign-card"
																	data-id="<?= (int)$staff['id']; ?>"
																	data-name="<?= $staffName; ?>"
																	title="Assign RFID card">
																	<i class="fa fa-wifi"></i> Assign card
																</button>
															<?php endif; ?>
															<?php if ($hasFace): ?>
																<button type="button" class="btn btn-sm btn-outline-danger btn-staff-remove-face"
																	data-id="<?= (int)$staff['id']; ?>"
																	data-name="<?= $staffName; ?>"
																	title="Remove enrolled face">
																	<i class="fa fa-user-times"></i> Remove face
																</button>
															<?php endif; ?>
														</div>
														<button type="button" class="btn btn-sm btn-info btn-staff-share-access"
															data-id="<?= (int)$staff['id']; ?>"
															data-name="<?= $staffName; ?>"
															title="Reset password and share login">
															<i class="fa fa-share-alt"></i> Share access
														</button>
														<label class="staff-delete-link typcn typcn-delete link" data-toggle="delete"
															data-title="Staff #<?=$staff['fname'];?>"
															data-target="<?=$staff['id'];?>" <?=$disable;?> data-href="delete_staff"
															title="Delete staff member">
															<?= lang("app.del");?>
														</label>
													</div>
												</td>
											</tr>
												<?php
											}
											?>
											</tbody>
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

<div id="staffCardModal" class="card-scan-modal-box d-none">
	<div class="card-scan-modal-head">
		<h6 class="mb-0">
			<i class="fa fa-wifi"></i> <span id="staffCardActionLabel">Assign card to</span>
			<span id="staffCardName" class="fw-bold"></span>
		</h6>
		<button type="button" id="staffCardClose" class="btn btn-sm btn-close btn-close-white"></button>
	</div>
	<div class="card-scan-modal-body">
		<p class="mb-2 small text-muted" id="staffCardHint">Any staff role (admin, teacher, bursar, etc.) can have an RFID card. Cards cannot be shared with students or visitors.</p>
		<input type="hidden" id="staffCardId">
		<input type="hidden" id="staffCardMode" value="assign">
		<?= view('pages/partials/card_scan_panel', [
			'input_id' => 'staffCardInput',
			'status_id' => 'staffCardStatus',
			'placeholder' => 'Tap your card...',
			'status_text' => 'Waiting for card...',
		]) ?>
	</div>
	<div class="card-scan-modal-foot">
		<button type="button" class="btn btn-outline-secondary btn-sm" id="staffCardCancel"><i class="fa fa-times"></i> Close</button>
	</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= base_url('assets/js/card-uid.js') ?>"></script>
<script>
	$(function () {
		$("#assignShiftMdl").on("show.bs.modal",function (e) {
			var parent = $(e.relatedTarget).parent();
			var shift_id = parent.data("shift");
			var staff_id = parent.parent().data("id");
			var shift_title = parent.data("shift_title");
			shift_title = shift_title.length==0?"Current shift: None":"Current shift: "+shift_title;
			$("#lbl_shift").text(shift_title);
			$("#sh_shift_id").val(shift_id);
			$(".sh_staff_id").val(staff_id);
		});

		$("#changePostMdl").on("show.bs.modal",function (e) {
			$(".select2").select2();
			$("#refrs_privilege").click();
			var parent = $(e.relatedTarget).parent();
			var post_id = parent.data("post");
			var staff_id = parent.parent().data("id");
			var post_title = parent.data("post_title");
			post_title = post_title.length==0?"Current post: None":"Current post: "+post_title;
			$("#lbl_post").text(post_title);
			$("#sh_post_id").val(post_id);
			$(".sh_staff_id").val(staff_id);
		});

		var modal = document.getElementById('staffCardModal');
		var cardInput = document.getElementById('staffCardInput');
		var cardStatus = document.getElementById('staffCardStatus');
		var school_id = <?= json_encode(session('soma_school_id')) ?>;
		var operator = <?= json_encode(session('soma_id')) ?>;
		var buffer = '';

		function setStatus(text, type) {
			cardStatus.textContent = text;
			cardStatus.className = 'card-scan-status' + (type ? ' ' + type : '');
		}

		function openStaffCard(id, name, mode, currentCard) {
			document.getElementById('staffCardId').value = id;
			document.getElementById('staffCardName').textContent = name;
			document.getElementById('staffCardMode').value = mode;
			document.getElementById('staffCardActionLabel').textContent = mode === 'change' ? 'Change card for' : 'Assign card to';
			document.getElementById('staffCardHint').textContent = mode === 'change'
				? (currentCard ? 'Current: ' + currentCard + ' — tap the new card on the reader.' : 'Tap the new card on the reader.')
				: 'Any staff role can have an RFID card. Tap card on reader — same NFC format as Android.';
			cardInput.value = '';
			setStatus('Waiting for card...', '');
			modal.classList.remove('d-none');
			cardInput.focus();
		}

		function closeStaffCard() {
			modal.classList.add('d-none');
			buffer = '';
		}

		document.querySelectorAll('.btn-staff-assign-card, .btn-staff-change-card').forEach(function (btn) {
			btn.addEventListener('click', function () {
				openStaffCard(btn.dataset.id, btn.dataset.name, btn.classList.contains('btn-staff-change-card') ? 'change' : 'assign', btn.dataset.card || '');
			});
		});

		document.querySelectorAll('.btn-staff-remove-card').forEach(function (btn) {
			btn.addEventListener('click', function () {
				Swal.fire({
					title: 'Remove card?',
					text: 'Unassign RFID card from ' + btn.dataset.name + '?',
					icon: 'warning',
					showCancelButton: true,
					confirmButtonColor: '#dc3545',
					confirmButtonText: 'Yes, remove'
				}).then(function (result) {
					if (!result.isConfirmed) return;
					fetch('<?= base_url('api/remove_staff_card') ?>', {
						method: 'POST',
						headers: {'Content-Type': 'application/x-www-form-urlencoded'},
						body: 'staff_id=' + encodeURIComponent(btn.dataset.id) + '&school_id=' + school_id + '&operator=' + operator
					}).then(function (r) { return r.json(); }).then(function (res) {
						if (res.success) {
							Swal.fire({ icon: 'success', title: 'Removed', text: res.success, timer: 1500, showConfirmButton: false });
							setTimeout(function () { location.reload(); }, 1200);
						} else {
							Swal.fire({ icon: 'error', title: 'Error', text: res.error || 'Could not remove card.' });
						}
					});
				});
			});
		});

		document.querySelectorAll('.btn-staff-remove-face').forEach(function (btn) {
			btn.addEventListener('click', function () {
				Swal.fire({
					title: 'Remove face?',
					text: 'Clear the enrolled face for ' + btn.dataset.name + '? They must record a new live face on the kiosk.',
					icon: 'warning',
					showCancelButton: true,
					confirmButtonColor: '#dc3545',
					confirmButtonText: 'Yes, remove face'
				}).then(function (result) {
					if (!result.isConfirmed) return;
					fetch('<?= base_url('api/remove_staff_face') ?>', {
						method: 'POST',
						headers: {'Content-Type': 'application/x-www-form-urlencoded'},
						body: 'staff_id=' + encodeURIComponent(btn.dataset.id) + '&school_id=' + school_id + '&operator=' + operator
					}).then(function (r) { return r.json(); }).then(function (res) {
						if (res.success) {
							Swal.fire({ icon: 'success', title: 'Face removed', text: res.success, timer: 1500, showConfirmButton: false });
							setTimeout(function () { location.reload(); }, 1200);
						} else {
							Swal.fire({ icon: 'error', title: 'Error', text: res.error || 'Could not remove face.' });
						}
					});
				});
			});
		});

		document.getElementById('staffCardClose').addEventListener('click', closeStaffCard);
		document.getElementById('staffCardCancel').addEventListener('click', closeStaffCard);

		function normalizeUID(uid) {
			return (window.CardUid && CardUid.toStorage) ? CardUid.toStorage(uid) : String(uid || '').trim().toUpperCase();
		}

		document.addEventListener('keypress', function (e) {
			if (modal.classList.contains('d-none')) return;
			if (e.key === 'Enter') {
				var uid = buffer.trim();
				buffer = '';
				if (uid.length >= 4) {
					var normalized = normalizeUID(uid);
					cardInput.value = normalized;
					assignStaffCard(normalized);
				}
			} else {
				buffer += e.key;
			}
		});

		function assignStaffCard(card) {
			var staff_id = document.getElementById('staffCardId').value;
			setStatus('Assigning card...', 'busy');
			fetch('<?= base_url('api/assign_staff_card') ?>', {
				method: 'POST',
				headers: {'Content-Type': 'application/x-www-form-urlencoded'},
				body: 'card=' + encodeURIComponent(card) + '&staff_id=' + staff_id + '&school_id=' + school_id + '&operator=' + operator
			}).then(function (r) { return r.json(); }).then(function (res) {
				if (res.success) {
					setStatus('✅ ' + res.success, 'ok');
					Swal.fire({ icon: 'success', title: 'Card Saved', text: res.success, timer: 1800, showConfirmButton: false });
					setTimeout(function () { closeStaffCard(); location.reload(); }, 1400);
				} else {
					setStatus('❌ ' + (res.error || 'Failed'), 'err');
					Swal.fire({ icon: 'error', title: 'Error', text: res.error || 'Card assignment failed.' });
				}
			}).catch(function (err) {
				setStatus('⚠️ ' + err.message, 'err');
				Swal.fire({ icon: 'error', title: 'Network Error', text: err.message });
			});
		}

		cardInput.addEventListener('focus', function () { cardInput.blur(); });

		var ssaSwalBase = {
			customClass: {
				popup: 'ssa-swal',
				confirmButton: 'ssa-swal-confirm',
				cancelButton: 'ssa-swal-cancel',
				actions: 'ssa-swal-actions'
			},
			buttonsStyling: false
		};

		function escapeHtml(str) {
			return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
		}

		function shareChannelPickerHtml(staffName, scope) {
			var who = scope === 'all' ? 'All active staff' : staffName;
			var scopeNote = scope === 'all'
				? '<div class="ssa-note"><i class="fa fa-users"></i> This will reset passwords for every active staff member in your school.</div>'
				: '<div class="ssa-note"><i class="fa fa-key"></i> A new password will be generated and sent immediately. The old password will stop working.</div>';
			return ''
				+ '<div class="ssa-head">'
				+ '  <div class="ssa-head-icon"><i class="fa fa-share-alt"></i></div>'
				+ '  <h3>Share access</h3>'
				+ '  <p>Reset login credentials and deliver them securely to staff.</p>'
				+ '</div>'
				+ '<div class="ssa-body">'
				+ '  <div class="ssa-staff-chip"><i class="fa fa-user"></i> <strong>' + escapeHtml(who) + '</strong></div>'
				+ '  <p class="ssa-label">Delivery method</p>'
				+ '  <div class="ssa-channels">'
				+ '    <button type="button" class="ssa-channel" data-channel="sms">'
				+ '      <span class="ssa-channel-icon"><i class="fa fa-sms"></i></span>'
				+ '      <span class="ssa-channel-title">SMS only</span>'
				+ '    </button>'
				+ '    <button type="button" class="ssa-channel" data-channel="email">'
				+ '      <span class="ssa-channel-icon"><i class="fa fa-envelope"></i></span>'
				+ '      <span class="ssa-channel-title">Email only</span>'
				+ '    </button>'
				+ '    <button type="button" class="ssa-channel is-active" data-channel="both">'
				+ '      <span class="ssa-channel-icon"><i class="fa fa-paper-plane"></i></span>'
				+ '      <span class="ssa-channel-title">SMS + Email</span>'
				+ '    </button>'
				+ '  </div>'
				+ scopeNote
				+ '</div>';
		}

		function bindShareChannelPicker(popup, defaultChannel) {
			var selected = defaultChannel || 'both';
			popup.querySelectorAll('.ssa-channel').forEach(function (el) {
				el.classList.toggle('is-active', el.dataset.channel === selected);
				el.addEventListener('click', function () {
					selected = el.dataset.channel;
					popup.querySelectorAll('.ssa-channel').forEach(function (c) {
						c.classList.toggle('is-active', c.dataset.channel === selected);
					});
				});
			});
			popup._ssaGetChannel = function () { return selected; };
		}

		function openShareAccessDialog(staffId, staffName, scope, presetChannel) {
			if (presetChannel) {
				var labels = { sms: 'SMS', email: 'Email', both: 'SMS and Email' };
				var who = scope === 'all' ? 'ALL active staff' : staffName;
				return Swal.fire(Object.assign({}, ssaSwalBase, {
					customClass: Object.assign({}, ssaSwalBase.customClass, { popup: 'ssa-swal ssa-swal--compact' }),
					title: 'Confirm share access',
					html: 'Reset login password for <strong>' + escapeHtml(who) + '</strong> and send credentials via <strong>' + (labels[presetChannel] || presetChannel) + '</strong>?',
					icon: 'question',
					showCancelButton: true,
					confirmButtonText: '<i class="fa fa-share-alt"></i> Reset & share',
					cancelButtonText: 'Cancel'
				})).then(function (choice) {
					if (choice.isConfirmed) {
						return runShareStaffAccess(staffId, staffName, presetChannel, scope);
					}
				});
			}

			return Swal.fire(Object.assign({}, ssaSwalBase, {
				html: shareChannelPickerHtml(staffName, scope),
				showCancelButton: true,
				confirmButtonText: '<i class="fa fa-key"></i> Reset & share',
				cancelButtonText: 'Cancel',
				focusConfirm: false,
				didOpen: function () {
					bindShareChannelPicker(Swal.getPopup(), 'both');
				},
				preConfirm: function () {
					return Swal.getPopup()._ssaGetChannel();
				}
			})).then(function (result) {
				if (result.isConfirmed && result.value) {
					return runShareStaffAccess(staffId, staffName, result.value, scope);
				}
			});
		}

		function parseShareResultMessage(msg) {
			if (!msg) return '';
			var parts = [];
			var pwdMatch = msg.match(/Password reset[^.]*/i);
			var smsMatch = msg.match(/\d+\s+SMS[^.]*/i);
			var emailMatch = msg.match(/\d+\s+email[^.]*/i);
			if (/password reset/i.test(msg)) parts.push('<div class="ssa-result-stat"><i class="fa fa-key"></i><span>' + escapeHtml(pwdMatch ? pwdMatch[0] : 'Password reset') + '</span></div>');
			if (/(\d+)\s+sms/i.test(msg)) parts.push('<div class="ssa-result-stat"><i class="fa fa-sms"></i><span>' + escapeHtml(smsMatch ? smsMatch[0] : '') + '</span></div>');
			if (/(\d+)\s+email/i.test(msg)) parts.push('<div class="ssa-result-stat"><i class="fa fa-envelope"></i><span>' + escapeHtml(emailMatch ? emailMatch[0] : '') + '</span></div>');
			return parts.length ? '<div class="ssa-result">' + parts.join('') + '</div>' : '<p style="padding:0 20px 8px;margin:0;color:#64748b;font-size:0.9rem;">' + escapeHtml(msg) + '</p>';
		}

		function runShareStaffAccess(staffId, staffName, channel, scope) {
			Swal.fire(Object.assign({}, ssaSwalBase, {
				customClass: Object.assign({}, ssaSwalBase.customClass, { popup: 'ssa-swal ssa-swal--loading' }),
				title: 'Sending credentials…',
				html: '<div class="ssa-loading-wrap"><div class="ssa-spinner"></div><p>Resetting password and delivering login details</p></div>',
				showConfirmButton: false,
				allowOutsideClick: false
			}));

			var body = 'channel=' + encodeURIComponent(channel) + '&scope=' + encodeURIComponent(scope);
			if (scope !== 'all') body += '&staff_id=' + encodeURIComponent(staffId);

			return fetch('<?= base_url('share_staff_access'); ?>', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
					'Accept': 'application/json',
					'X-Requested-With': 'XMLHttpRequest'
				},
				credentials: 'same-origin',
				body: body
			}).then(function (r) {
				return r.text().then(function (text) {
					var res;
					try { res = JSON.parse(text); } catch (e) {
						throw new Error('Server returned an invalid response (' + r.status + ').');
					}
					return res;
				});
			}).then(function (res) {
				if (res.success) {
					return Swal.fire(Object.assign({}, ssaSwalBase, {
						customClass: Object.assign({}, ssaSwalBase.customClass, { popup: 'ssa-swal ssa-swal--compact' }),
						icon: 'success',
						title: 'Access shared',
						html: parseShareResultMessage(res.success),
						confirmButtonText: 'Done'
					}));
				}
				if (res.warning) {
					var detail = (res.failed || []).join('\n');
					return Swal.fire(Object.assign({}, ssaSwalBase, {
						customClass: Object.assign({}, ssaSwalBase.customClass, { popup: 'ssa-swal ssa-swal--compact' }),
						icon: 'warning',
						title: 'Partially sent',
						html: parseShareResultMessage(res.warning) + (detail ? '<p style="padding:0 20px;font-size:0.8rem;color:#92400e;">' + escapeHtml(detail) + '</p>' : ''),
						confirmButtonText: 'OK'
					}));
				}
				return Swal.fire(Object.assign({}, ssaSwalBase, {
					customClass: Object.assign({}, ssaSwalBase.customClass, { popup: 'ssa-swal ssa-swal--compact' }),
					icon: 'error',
					title: 'Could not share',
					text: res.error || 'Could not share access.',
					confirmButtonText: 'Close'
				}));
			}).catch(function (err) {
				return Swal.fire(Object.assign({}, ssaSwalBase, {
					customClass: Object.assign({}, ssaSwalBase.customClass, { popup: 'ssa-swal ssa-swal--compact' }),
					icon: 'error',
					title: 'Network error',
					text: err.message || 'Request failed.',
					confirmButtonText: 'Close'
				}));
			});
		}

		document.querySelectorAll('.btn-staff-share-access').forEach(function (btn) {
			btn.addEventListener('click', function () {
				openShareAccessDialog(btn.dataset.id, btn.dataset.name, 'single');
			});
		});

		document.querySelectorAll('.btn-share-all-staff').forEach(function (lnk) {
			lnk.addEventListener('click', function () {
				openShareAccessDialog(0, '', 'all', lnk.dataset.channel);
			});
		});
	});
</script>
