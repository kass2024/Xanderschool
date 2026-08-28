<link rel="stylesheet" href="<?= base_url('assets/css/staff-share-access.css') ?>">
<div class="app-inner-layout app-inner-layout-page">
	<div class="app-inner-layout__wrapper">
		<div class="app-inner-layout__content">
			<div class="tab-content">
				<div class="container-fluid">
					<div class="card mb-3">
						<div class="card-header-tab card-header">
							<div
								class="card-header-title font-size-lg text-capitalize font-weight-normal">
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
											<?= lang("app.SchoolMenu");?></h6>
										<a type="button" tabindex="0" href="<?=base_url('add-school');?>" class="dropdown-item"><i
												class="typcn typcn-plus"> </i><span><?= lang("app.AddNewSchools");?> </span>
										</a>

									</div>
								</div>
							</div>
						</div>
						<div class="card-body">
							<div id="example_wrapper" class="dataTables_wrapper dt-bootstrap4">
								<div class="row">
									<div class="col-sm-12">
										<table style="width: 100%;" id="example"
											   class="table table-hover table-striped table-bordered dataTable dtr-inline"
											   role="grid" aria-describedby="example_info">
											<thead>
											<tr role="row">
												<th><?= lang("app.logo");?> </th>
												<th><?= lang("app.names");?> </th>
												<th><?= lang("app.acronym");?> </th>
												<th><?= lang("app.phone");?> </th>
												<th><?= lang("app.mail");?> </th>
												<th><?= lang("app.headMaster");?></th>
												<th>Package </th>
												<th><?= lang("app.remainSMS");?> </th>
												<th><?= lang("app.address");?> </th>
												<th>Group </th>
												<th><?= lang("app.status");?> </th>
												<th></th>
											</tr>
											</thead>
											<tbody>
											<?php
											foreach ($schools as $school) {
												$status = $school['status']==1?'<label class="text-success" data-toggle="update" data-href="admin/change_status/school/0" data-target="'.$school["id"].'">'. lang("app.active") .'</label>'
													:'<label class="text-danger" data-toggle="update" data-href="admin/change_status/school/1" data-target="'.$school["id"].'">'. lang("app.locked") .'</label>';
												$groupLabel = '—';
												if (!empty($school['is_master'])) {
													$groupLabel = '<span class="badge badge-success">Master</span>';
												} elseif (!empty($school['master_school_id'])) {
													$mn = htmlspecialchars($school['master_name'] ?? ('#' . $school['master_school_id']), ENT_QUOTES, 'UTF-8');
													$groupLabel = '<span class="badge badge-info">Child</span> <small class="text-muted">' . $mn . '</small>';
												}
												$hmName = htmlspecialchars(trim((string) ($school['head_master'] ?? '')), ENT_QUOTES, 'UTF-8');
												$schoolName = htmlspecialchars((string) ($school['name'] ?? ''), ENT_QUOTES, 'UTF-8');
												$remainSms = max(0, (int) ($school['sms_limit'] ?? 0) - (int) ($school['sms_usage'] ?? 0))
													+ max(0, (int) ($school['extra_sms'] ?? 0));
												?>
											<tr>
												<td></td>
												<td><?=$school['name'];?></td>
												<td><?=$school['acronym'];?></td>
												<td><?=$school['phone'];?></td>
												<td><?=$school['email'];?></td>
												<td><?=$school['head_master'];?></td>
												<td><?=$school['package_title'];?>
													<i class="typcn typcn-edit btn-link lnk spedit" data-toggle="modal"
													   data-target="#changeScklpackage" data-id="<?=$school['id'];?>"
													   style="color:#112d81;font-size: 14pt"></i>
												</td>
												<td><?=$remainSms;?></td>
												<td><?=$school['country'];?></td>
												<td><?=$groupLabel;?></td>
												<td><?=$status;?> </td>
												<td style="white-space:nowrap;">
													<a href="<?= base_url('edit-school/' . (int) $school['id']); ?>"
													   class="btn btn-sm btn-primary"
													   title="<?= lang('app.edit'); ?>">
														<i class="fa fa-edit"></i> <?= lang('app.edit'); ?>
													</a>
													<button type="button" class="btn btn-sm btn-info btn-staff-share-access btn-school-share-access"
														data-id="<?= (int) $school['id']; ?>"
														data-name="<?= $hmName !== '' ? $hmName : $schoolName; ?>"
														data-school="<?= $schoolName; ?>"
														title="Reset headmaster password and share login">
														<i class="fa fa-share-alt"></i> Share access
													</button>
													<label class="typcn typcn-delete text-danger link ml-2" data-toggle="delete"
														   data-target="<?=$school['id'];?>" data-href="admin/delete_school" data-title="school #<?=$school["name"];?>"><?= lang("app.del");?> </label>
												</td>
											</tr>
												<?php
											}
											?>
											</tbody>
											<tfoot>
											<tr>
												<th><?= lang("app.logo");?> </th>
												<th><?= lang("app.names");?> </th>
												<th><?= lang("app.acronym");?> </th>
												<th><?= lang("app.phone");?> </th>
												<th><?= lang("app.mail");?> </th>
												<th><?= lang("app.headMaster");?></th>
												<th>Package </th>
												<th><?= lang("app.remainSMS");?> </th>
												<th><?= lang("app.address");?> </th>
												<th>Group </th>
												<th><?= lang("app.status");?> </th>
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function () {
	var shareUrl = <?= json_encode(base_url('admin/share_school_access')) ?>;
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

	function shareChannelPickerHtml(headName, schoolName) {
		return ''
			+ '<div class="ssa-head">'
			+ '  <div class="ssa-head-icon"><i class="fa fa-share-alt"></i></div>'
			+ '  <h3>Share access</h3>'
			+ '  <p>Reset headmaster login and deliver credentials via SMS and/or email.</p>'
			+ '</div>'
			+ '<div class="ssa-body">'
			+ '  <div class="ssa-staff-chip"><i class="fa fa-user"></i> <strong>' + escapeHtml(headName) + '</strong></div>'
			+ '  <div class="ssa-staff-chip"><i class="fa fa-university"></i> <strong>' + escapeHtml(schoolName) + '</strong></div>'
			+ '  <p class="ssa-label">Delivery method</p>'
			+ '  <div class="ssa-channels">'
			+ '    <button type="button" class="ssa-channel" data-channel="sms">'
			+ '      <span class="ssa-channel-icon"><i class="fa fa-mobile-alt"></i></span>'
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
			+ '  <div class="ssa-note"><i class="fa fa-key"></i> A new password will be generated and sent to the school phone and email. The old password will stop working.</div>'
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

	function parseShareResultMessage(msg) {
		if (!msg) return '';
		var parts = [];
		var pwdMatch = msg.match(/Password reset[^.]*/i);
		var smsMatch = msg.match(/\d+\s+SMS[^.]*/i);
		var emailMatch = msg.match(/\d+\s+email[^.]*/i);
		if (/password reset/i.test(msg)) parts.push('<div class="ssa-result-stat"><i class="fa fa-key"></i><span>' + escapeHtml(pwdMatch ? pwdMatch[0] : 'Password reset') + '</span></div>');
		if (/(\d+)\s+sms/i.test(msg)) parts.push('<div class="ssa-result-stat"><i class="fa fa-mobile-alt"></i><span>' + escapeHtml(smsMatch ? smsMatch[0] : '') + '</span></div>');
		if (/(\d+)\s+email/i.test(msg)) parts.push('<div class="ssa-result-stat"><i class="fa fa-envelope"></i><span>' + escapeHtml(emailMatch ? emailMatch[0] : '') + '</span></div>');
		return parts.length ? '<div class="ssa-result">' + parts.join('') + '</div>' : '<p style="padding:0 20px 8px;margin:0;color:#64748b;font-size:0.9rem;">' + escapeHtml(msg) + '</p>';
	}

	function runShareSchoolAccess(schoolId, channel) {
		if (typeof Swal === 'undefined') {
			alert('Share dialog failed to load. Please refresh the page.');
			return;
		}
		Swal.fire(Object.assign({}, ssaSwalBase, {
			customClass: Object.assign({}, ssaSwalBase.customClass, { popup: 'ssa-swal ssa-swal--loading' }),
			title: 'Sending credentials…',
			html: '<div class="ssa-loading-wrap"><div class="ssa-spinner"></div><p>Resetting headmaster password and delivering login details</p></div>',
			showConfirmButton: false,
			allowOutsideClick: false
		}));

		return fetch(shareUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
				'Accept': 'application/json',
				'X-Requested-With': 'XMLHttpRequest'
			},
			credentials: 'same-origin',
			body: 'school_id=' + encodeURIComponent(schoolId) + '&channel=' + encodeURIComponent(channel)
		}).then(function (r) {
			return r.text().then(function (text) {
				var res;
				try { res = JSON.parse(text); } catch (e) {
					throw new Error(r.status === 401 || /login/i.test(text)
						? 'Please login to the admin panel again.'
						: 'Server returned an invalid response (' + r.status + ').');
				}
				if (!r.ok && res.error) {
					return res;
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

	function openShareAccessDialog(schoolId, headName, schoolName) {
		if (typeof Swal === 'undefined') {
			alert('Share dialog failed to load. Please refresh the page.');
			return;
		}
		return Swal.fire(Object.assign({}, ssaSwalBase, {
			html: shareChannelPickerHtml(headName, schoolName),
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
				return runShareSchoolAccess(schoolId, result.value);
			}
		});
	}

	// Delegated click so DataTables redraws keep the button working
	document.addEventListener('click', function (e) {
		var btn = e.target.closest('.btn-school-share-access');
		if (!btn) return;
		e.preventDefault();
		e.stopPropagation();
		openShareAccessDialog(btn.getAttribute('data-id'), btn.getAttribute('data-name') || '', btn.getAttribute('data-school') || '');
	}, true);

	$(function () {
		var sp, value, sp_td, old_data, target, type = null;
		$(".spedit").on("dblclick", function () {
			sp = $(this);
			sp_td = $(this).parent("td");
			value = sp.data("value");
			old_data = sp.html();
			target = sp.data("target");
			type = sp.data("type") == undefined ? "text" : sp.data("type");
			if (type == "text") {
				sp.html("<input type='text' value='" + value + "' class='sptxt'>");
				$(".sptxt").focus();
			}
			if (type == "number" || type == "digit") {
				sp.html("<input type='text' data-parsley-type='number' value='" + value + "' class='sptxt'>");
				$(".sptxt").focus();
			}
			if (type == "select") {
				sp.html("<select class='select2_auto' style='width:200px !important' data-value='" + value + "' data-href='" + sp.data("href") + "' class='spselect'>");
				load_select(sp_td.data("href"), value);
			}
		});
		$(document).on('select2:select', ".select2_auto", function () {
			var id = $("#settingsCnt").data("id");
			var val = $(this).val();
			$.post("<?=base_url('manipulate_school/');?>" + type + "/" + $(this).data("href"), "id=" + id + "&target=" + target + "&val=" + val, function (data) {
				if (data.hasOwnProperty("error")) {
					toastada.error("Save settings failed: " + data.msg);
				} else if (data.hasOwnProperty("success")) {
					sp_td.html(data.result);
					sp_td.data("value", val);
				} else {
					toastada.error(<?= json_encode(lang("app.fatalErr")); ?>);
				}
			}).fail(function () {
				toastada.error(<?= json_encode(lang("app.systemErr")); ?>);
			});
		});

		function load_select(href, value) {
			$.ajax({
				type: "GET",
				dataType: "json",
				async: true,
				url: "<?=base_url();?>admin/" + href,
				data: ({}),
				success: function (data) {
					$('.select2_auto').select2({
						data: data,
						escapeMarkup: function (state) { return state; },
						placeholder: "Select..."
					});
					if (value.length > 0)
						$('.select2_auto').val(value).trigger("change");
				}
			});
		}
	});
})();
</script>
