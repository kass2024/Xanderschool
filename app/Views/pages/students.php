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
#classEditModal .modal-dialog { max-width: 96vw; width: 96vw; margin: 10px auto; }
#classEditModal .modal-content {
	border: 0; border-radius: 16px; overflow: hidden;
	box-shadow: 0 24px 64px rgba(15, 23, 42, .28);
}
#classEditModal .modal-header {
	background: linear-gradient(135deg, #0f172a, #1d4ed8 62%, #2563eb);
	color: #fff; border: 0; padding: 16px 20px; align-items: center;
}
#classEditModal .modal-header .close { color: #fff; opacity: .85; text-shadow: none; }
#classEditModal .modal-title { font-weight: 700; font-size: 18px; }
#classEditModal .ce-subtitle { display:block; font-size:12px; font-weight:500; opacity:.85; margin-top:3px; }
#classEditModal .modal-body { padding: 14px 16px 10px; background: #f8fafc; }
#classEditModal .modal-footer {
	background: #fff; border-top: 1px solid #e2e8f0; padding: 10px 16px;
	display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;
}
#classEditModal .ce-toolbar {
	display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 10px;
}
#classEditModal .ce-toolbar input[type="search"] {
	max-width: 320px; border-radius: 999px; border-color: #cbd5e1; padding-left: 14px;
}
#classEditCount, #classEditDirtyHint {
	font-size: 12px; font-weight: 600; color: #475569; background: #fff;
	border: 1px solid #e2e8f0; border-radius: 999px; padding: 4px 10px;
}
#classEditDirtyHint.ce-has-dirty { color: #b45309; background: #fffbeb; border-color: #fcd34d; }
#classEditStatus {
	display: flex; align-items: center; gap: 8px; font-size: 13px; color: #334155; font-weight: 600;
}
#classEditStatus .ce-dot {
	width: 9px; height: 9px; border-radius: 50%; background: #22c55e; box-shadow: 0 0 0 3px rgba(34,197,94,.18);
}
#classEditStatus.ce-busy .ce-dot { background: #f59e0b; box-shadow: 0 0 0 3px rgba(245,158,11,.2); }
#classEditStatus.ce-err .ce-dot { background: #ef4444; box-shadow: 0 0 0 3px rgba(239,68,68,.2); }
#classEditWrap {
	overflow: auto; max-height: calc(100vh - 250px); border: 1px solid #dbe3ef; border-radius: 12px;
	background: #fff;
}
#classEditTable { margin: 0; font-size: 12px; min-width: 1780px; }
#classEditTable thead th {
	position: sticky; top: 0; z-index: 3; background: #0f172a; color: #e2e8f0; white-space: nowrap;
	font-size: 11px; text-transform: uppercase; letter-spacing: .04em; border-color: #1e293b; padding: 8px 8px;
}
#classEditTable thead th.ce-grp-student { background: #1e3a8a; }
#classEditTable thead th.ce-grp-mode { background: #0f766e; }
#classEditTable thead th.ce-grp-father { background: #1d4ed8; }
#classEditTable thead th.ce-grp-mother { background: #7c3aed; }
#classEditTable thead th.ce-grp-guardian { background: #0369a1; }
#classEditTable td { vertical-align: middle; padding: 6px 7px; }
#classEditTable .form-control, #classEditTable select.form-control {
	height: 32px; padding: 3px 8px; font-size: 12px; border-radius: 8px; border-color: #cbd5e1;
}
#classEditTable .form-control:focus, #classEditTable select.form-control:focus {
	border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.15);
}
#classEditTable .ce-regno { font-weight: 800; white-space: nowrap; color: #0f172a; }
#classEditTable tr.ce-dirty { background: #fffbeb; }
#classEditTable tr.ce-saved { background: #ecfdf5; }
#classEditTable tr.ce-saving { background: #eff6ff; }
#classEditTable .ce-w-name { min-width: 124px; }
#classEditTable .ce-w-phone { min-width: 114px; }
#classEditTable .ce-w-nid { min-width: 134px; }
#classEditAlert { margin-bottom: 8px; }
#classEditAlert.alert-success {
	background: #dcfce7; border-color: #86efac; color: #166534;
	font-weight: 700; border-radius: 10px; padding: 10px 14px;
}
#classEditAlert.alert-success i { margin-right: 6px; }
#btnSaveClassStudents.btn-success { min-width: 130px; font-weight: 700; }
.ce-mode-toggle {
	display: inline-flex; border: 1px solid #cbd5e1; border-radius: 999px; overflow: hidden;
	background: #fff; min-width: 168px;
}
.ce-mode-toggle button {
	flex: 1; border: 0; background: transparent; padding: 6px 10px; font-size: 11px; font-weight: 800;
	cursor: pointer; color: #64748b; letter-spacing: .02em; white-space: nowrap;
}
.ce-mode-toggle button.active[data-value="0"] { background: #2563eb; color: #fff; }
.ce-mode-toggle button.active[data-value="1"] { background: #d97706; color: #fff; }
.ce-row-mark { font-size: 11px; color: #94a3b8; }
.ce-row-mark.saved { color: #16a34a; }
.ce-row-mark.busy { color: #2563eb; }
#btnSaveClassStudents { min-width: 130px; font-weight: 700; }
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
												<button type="button" id="btnEditClassStudents" class="btn btn-warning">
													<i class="fa fa-edit"></i> Edit class
												</button>
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
												<tr data-student-id="<?= (int) $student['id']; ?>">
													<td><input type="checkbox" class="st-sms-check" value="<?= (int) $student['id']; ?>"></td>
													<td><?= $a; ?></td>
													<td><a href="<?= base_url('student/' . $student['id']); ?>"
														   class="link"><?= $student['regno']; ?></a></td>
													<td class="ce-list-names"><?= $student['fname'] . ' ' . $student['lname']; ?></td>
													<td class="ce-list-mode"><?= \App\Controllers\Home::ModeToStr($student['studying_mode']); ?></td>
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
<?php
$classEditStudents = [];
foreach ($students as $st) {
	$dob = (string) ($st['dob'] ?? '');
	if ($dob === '0000-00-00' || strpos($dob, '0000') === 0) {
		$dob = '';
	} elseif (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $dob, $m)) {
		$dob = $m[3] . '-' . $m[2] . '-' . $m[1];
	}
	$modeRaw = strtolower(trim((string) ($st['studying_mode'] ?? '0')));
	$modeNorm = ($modeRaw === '1' || $modeRaw === 'day') ? '1' : '0';
	$sexNorm = strtoupper(trim((string) ($st['sex'] ?? '')));
	if ($sexNorm !== 'F' && $sexNorm !== 'M') {
		$sexNorm = '';
	}
	$classEditStudents[] = [
		'id' => (int) $st['id'],
		'regno' => (string) ($st['regno'] ?? ''),
		'fname' => (string) ($st['fname'] ?? ''),
		'lname' => (string) ($st['lname'] ?? ''),
		'sex' => $sexNorm,
		'dob' => $dob,
		'studying_mode' => $modeNorm,
		'phone' => (string) ($st['phone'] ?? ''),
		'nationality' => (string) ($st['nationality'] ?? ''),
		'religion' => (string) ($st['religion'] ?? ''),
		'father' => (string) ($st['father'] ?? ''),
		'ft_phone' => (string) ($st['ft_phone'] ?? ''),
		'father_nid' => (string) ($st['father_nid'] ?? ''),
		'mother' => (string) ($st['mother'] ?? ''),
		'mt_phone' => (string) ($st['mt_phone'] ?? ''),
		'mother_nid' => (string) ($st['mother_nid'] ?? ''),
		'guardian' => (string) ($st['guardian'] ?? ''),
		'gd_phone' => (string) ($st['gd_phone'] ?? ''),
		'guardian_nid' => (string) ($st['guardian_nid'] ?? ''),
	];
}
?>
<div class="modal fade" id="classEditModal" tabindex="-1" role="dialog" aria-labelledby="classEditTitle">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<div>
					<h5 class="modal-title" id="classEditTitle">Edit class — <?= esc($currentClassLabel); ?></h5>
					<span class="ce-subtitle">Click outside a cell to auto-save. Study mode (Boarding / Day) saves instantly. Works for every school.</span>
				</div>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			</div>
			<div class="modal-body">
				<div id="classEditAlert" class="alert d-none" role="alert"></div>
				<div class="ce-toolbar">
					<input type="search" class="form-control form-control-sm" id="classEditSearch" placeholder="Filter by name, reg no, parent…">
					<span id="classEditCount"></span>
					<span id="classEditDirtyHint">All saved</span>
				</div>
				<div id="classEditWrap">
					<table class="table table-sm table-bordered table-hover mb-0" id="classEditTable">
						<thead>
						<tr>
							<th class="ce-grp-student">#</th>
							<th class="ce-grp-student">Reg no</th>
							<th class="ce-grp-student">First name</th>
							<th class="ce-grp-student">Last name</th>
							<th class="ce-grp-student">Sex</th>
							<th class="ce-grp-student">Birth date</th>
							<th class="ce-grp-mode">Study mode</th>
							<th class="ce-grp-student">Student phone</th>
							<th class="ce-grp-student">Nationality</th>
							<th class="ce-grp-student">Religion</th>
							<th class="ce-grp-father">Father names</th>
							<th class="ce-grp-father">Father phone</th>
							<th class="ce-grp-father">Father national ID</th>
							<th class="ce-grp-mother">Mother names</th>
							<th class="ce-grp-mother">Mother phone</th>
							<th class="ce-grp-mother">Mother national ID</th>
							<th class="ce-grp-guardian">Guardian names</th>
							<th class="ce-grp-guardian">Guardian phone</th>
							<th class="ce-grp-guardian">Guardian national ID</th>
						</tr>
						</thead>
						<tbody></tbody>
					</table>
				</div>
			</div>
			<div class="modal-footer">
				<div id="classEditStatus"><span class="ce-dot"></span><span id="classEditStatusText">Ready — leave a cell to save it</span></div>
				<div>
					<button type="button" class="btn btn-secondary" data-dismiss="modal">Done</button>
					<button type="button" class="btn btn-primary" id="btnSaveClassStudents">Save all</button>
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
	var EDIT_FIELD_API = "<?= site_url('edit_student/text'); ?>";
	var SAVE_CLASS_API = "<?= site_url('saveClassStudents'); ?>";
	var YEAR_ID = "<?= (int) $academic_year; ?>";
	var FROM_CLASS = <?= (int) $class_id; ?>;
	var CLASS_STUDENTS = <?= json_encode($classEditStudents ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
	var MODE_BOARDING = <?= json_encode(lang('app.boarding')); ?>;
	var MODE_DAY = <?= json_encode(lang('app.day')); ?>;
	var CE_FIELDS = ['fname','lname','sex','dob','studying_mode','phone','nationality','religion','father','ft_phone','father_nid','mother','mt_phone','mother_nid','guardian','gd_phone','guardian_nid'];
	var RELIGIONS = <?= json_encode([
		lang('app.islam'),
		lang('app.catholics'),
		lang('app.adventist'),
		lang('app.jehovahWitness'),
		lang('app.otherChristians'),
	]); ?>;
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

	var ceOrigMap = {};
	var ceSaving = {};
	var ceAllowHide = false;
	var ceFlushing = false;
	var ceClosingAfterSave = false;

	function ceEsc(s) {
		return String(s == null ? '' : s)
			.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function ceNormMode(v) {
		v = String(v == null ? '' : v).trim().toLowerCase();
		if (v === '1' || v === 'day') {
			return '1';
		}
		return '0';
	}

	function ceNormField(field, v) {
		v = String(v == null ? '' : v);
		if (field === 'studying_mode') {
			return ceNormMode(v);
		}
		if (field === 'sex') {
			v = v.trim().toUpperCase();
			return (v === 'F' || v === 'M') ? v : '';
		}
		return v;
	}

	function ceModeLabel(v) {
		return ceNormMode(v) === '1' ? MODE_DAY : MODE_BOARDING;
	}

	function ceOpt(value, selected, label) {
		return '<option value="' + ceEsc(value) + '"' + (String(selected) === String(value) ? ' selected' : '') + '>' + ceEsc(label) + '</option>';
	}

	function ceInput(id, field, value, extraClass, type) {
		type = type || 'text';
		return '<input type="' + type + '" class="form-control ce-field ' + (extraClass || '') + '" data-id="' + id + '" data-field="' + field + '" value="' + ceEsc(value) + '">';
	}

	function ceModeToggle(id, value) {
		var mode = ceNormMode(value);
		return '<div class="ce-mode-toggle ce-field" data-id="' + id + '" data-field="studying_mode" tabindex="0">' +
			'<button type="button" data-value="0"' + (mode === '0' ? ' class="active"' : '') + '>' + ceEsc(MODE_BOARDING) + '</button>' +
			'<button type="button" data-value="1"' + (mode === '1' ? ' class="active"' : '') + '>' + ceEsc(MODE_DAY) + '</button>' +
			'</div>';
	}

	function ceRead($el) {
		if ($el.hasClass('ce-mode-toggle')) {
			var active = $el.find('button.active').attr('data-value');
			return ceNormMode(active);
		}
		return String($el.val() == null ? '' : $el.val());
	}

	function ceFindStudent(id) {
		for (var i = 0; i < CLASS_STUDENTS.length; i++) {
			if (CLASS_STUDENTS[i].id === id) {
				return CLASS_STUDENTS[i];
			}
		}
		return null;
	}

	function ceSnapshot(st) {
		var snap = {};
		CE_FIELDS.forEach(function (field) {
			snap[field] = ceNormField(field, st[field]);
		});
		return snap;
	}

	function ceInitOrig() {
		ceOrigMap = {};
		CLASS_STUDENTS.forEach(function (st) {
			st.studying_mode = ceNormMode(st.studying_mode);
			st.sex = ceNormField('sex', st.sex);
			ceOrigMap[st.id] = ceSnapshot(st);
		});
	}

	function ceSyncFromDom() {
		$('#classEditTable .ce-field').each(function () {
			var $el = $(this);
			var id = parseInt($el.attr('data-id'), 10);
			var field = $el.attr('data-field');
			if (!id || !field) {
				return;
			}
			var st = ceFindStudent(id);
			if (st) {
				st[field] = ceNormField(field, ceRead($el));
			}
		});
	}

	function ceDirtyCount() {
		var n = 0;
		CLASS_STUDENTS.forEach(function (st) {
			var orig = ceOrigMap[st.id] || {};
			for (var i = 0; i < CE_FIELDS.length; i++) {
				var field = CE_FIELDS[i];
				if (ceNormField(field, st[field]) !== ceNormField(field, orig[field])) {
					n++;
					break;
				}
			}
		});
		return n;
	}

	function ceDirtyRows() {
		var rows = [];
		CLASS_STUDENTS.forEach(function (st) {
			var orig = ceOrigMap[st.id] || {};
			var patch = { id: st.id, regno: st.regno };
			var changed = false;
			CE_FIELDS.forEach(function (field) {
				var cur = ceNormField(field, st[field]);
				var old = ceNormField(field, orig[field]);
				if (cur !== old) {
					patch[field] = cur;
					changed = true;
				}
			});
			if (changed) {
				rows.push(patch);
			}
		});
		return rows;
	}

	function ceMarkRow(id) {
		var $tr = $('#classEditTable tr[data-id="' + id + '"]');
		if (!$tr.length) {
			return;
		}
		var st = ceFindStudent(id);
		var orig = ceOrigMap[id] || {};
		var dirty = false;
		if (st) {
			CE_FIELDS.forEach(function (field) {
				if (ceNormField(field, st[field]) !== ceNormField(field, orig[field])) {
					dirty = true;
				}
			});
		}
		var busy = false;
		Object.keys(ceSaving).forEach(function (k) {
			if (k.indexOf(id + ':') === 0) {
				busy = true;
			}
		});
		$tr.toggleClass('ce-dirty', dirty && !busy).toggleClass('ce-saving', busy).toggleClass('ce-saved', !dirty && !busy);
	}

	function ceRefreshHints() {
		if (ceClosingAfterSave) {
			return;
		}
		var dirty = ceDirtyCount();
		var busy = Object.keys(ceSaving).length;
		var $hint = $('#classEditDirtyHint');
		if (dirty) {
			$hint.addClass('ce-has-dirty').text(dirty + ' unsaved student' + (dirty === 1 ? '' : 's'));
		} else {
			$hint.removeClass('ce-has-dirty').text('All saved');
		}
		$('#btnSaveClassStudents').text(dirty ? ('Save all (' + dirty + ')') : 'Save all');
		var $st = $('#classEditStatus');
		$st.removeClass('ce-busy ce-err');
		if (busy) {
			$st.addClass('ce-busy');
			$('#classEditStatusText').text('Saving…');
		} else if (dirty) {
			$('#classEditStatusText').text('Click outside a cell to save, or Save all');
		} else {
			$('#classEditStatusText').text('All student info saved');
		}
	}

	function ceSetStatus(kind, msg) {
		var $st = $('#classEditStatus');
		$st.removeClass('ce-busy ce-err');
		if (kind === 'busy') {
			$st.addClass('ce-busy');
		} else if (kind === 'err') {
			$st.addClass('ce-err');
		}
		$('#classEditStatusText').text(msg);
	}

	function ceUpdateListRow(id, field, val) {
		var $row = $('#example tbody tr[data-student-id="' + id + '"]');
		if (!$row.length) {
			var dt = tableApi();
			if (dt) {
				dt.rows().every(function () {
					var $n = $(this.node());
					if (parseInt($n.attr('data-student-id'), 10) === id) {
						$row = $n;
					}
				});
			}
		}
		if (!$row.length) {
			return;
		}
		if (field === 'studying_mode') {
			$row.find('.ce-list-mode').text(ceModeLabel(val));
		}
		if (field === 'fname' || field === 'lname') {
			var st = ceFindStudent(id);
			if (st) {
				$row.find('.ce-list-names').text($.trim((st.fname || '') + ' ' + (st.lname || '')));
			}
		}
	}

	function renderClassEditRows(filter) {
		filter = String(filter || '').toLowerCase().trim();
		var html = '';
		var shown = 0;
		var boarding = 0;
		var day = 0;
		CLASS_STUDENTS.forEach(function (st) {
			if (ceNormMode(st.studying_mode) === '1') {
				day++;
			} else {
				boarding++;
			}
			var hay = [st.regno, st.fname, st.lname, st.father, st.mother, st.guardian, st.ft_phone, st.father_nid, st.mother_nid, st.guardian_nid].join(' ').toLowerCase();
			if (filter && hay.indexOf(filter) === -1) {
				return;
			}
			shown++;
			var relOpts = '<option value=""></option>';
			RELIGIONS.forEach(function (r) {
				relOpts += ceOpt(r, st.religion, r);
			});
			if (st.religion && RELIGIONS.indexOf(st.religion) === -1) {
				relOpts += ceOpt(st.religion, st.religion, st.religion);
			}
			html += '<tr data-id="' + st.id + '">' +
				'<td>' + shown + '</td>' +
				'<td class="ce-regno">' + ceEsc(st.regno) + '</td>' +
				'<td class="ce-w-name">' + ceInput(st.id, 'fname', st.fname, 'ce-w-name') + '</td>' +
				'<td class="ce-w-name">' + ceInput(st.id, 'lname', st.lname, 'ce-w-name') + '</td>' +
				'<td><select class="form-control ce-field" data-id="' + st.id + '" data-field="sex">' +
					'<option value=""></option>' + ceOpt('F', st.sex, 'F') + ceOpt('M', st.sex, 'M') + '</select></td>' +
				'<td>' + ceInput(st.id, 'dob', st.dob, '', 'date') + '</td>' +
				'<td>' + ceModeToggle(st.id, st.studying_mode) + '</td>' +
				'<td class="ce-w-phone">' + ceInput(st.id, 'phone', st.phone, 'ce-w-phone') + '</td>' +
				'<td>' + ceInput(st.id, 'nationality', st.nationality) + '</td>' +
				'<td><select class="form-control ce-field" data-id="' + st.id + '" data-field="religion">' + relOpts + '</select></td>' +
				'<td class="ce-w-name">' + ceInput(st.id, 'father', st.father, 'ce-w-name') + '</td>' +
				'<td class="ce-w-phone">' + ceInput(st.id, 'ft_phone', st.ft_phone, 'ce-w-phone') + '</td>' +
				'<td class="ce-w-nid">' + ceInput(st.id, 'father_nid', st.father_nid, 'ce-w-nid') + '</td>' +
				'<td class="ce-w-name">' + ceInput(st.id, 'mother', st.mother, 'ce-w-name') + '</td>' +
				'<td class="ce-w-phone">' + ceInput(st.id, 'mt_phone', st.mt_phone, 'ce-w-phone') + '</td>' +
				'<td class="ce-w-nid">' + ceInput(st.id, 'mother_nid', st.mother_nid, 'ce-w-nid') + '</td>' +
				'<td class="ce-w-name">' + ceInput(st.id, 'guardian', st.guardian, 'ce-w-name') + '</td>' +
				'<td class="ce-w-phone">' + ceInput(st.id, 'gd_phone', st.gd_phone, 'ce-w-phone') + '</td>' +
				'<td class="ce-w-nid">' + ceInput(st.id, 'guardian_nid', st.guardian_nid, 'ce-w-nid') + '</td>' +
				'</tr>';
		});
		$('#classEditTable tbody').html(html || '<tr><td colspan="19" class="text-center text-muted py-3">No students match.</td></tr>');
		$('#classEditCount').text(shown + ' of ' + CLASS_STUDENTS.length + ' · ' + boarding + ' ' + MODE_BOARDING + ' · ' + day + ' ' + MODE_DAY);
		CLASS_STUDENTS.forEach(function (st) { ceMarkRow(st.id); });
		ceRefreshHints();
	}

	function ceShowAlert(kind, msg) {
		var $el = $('#classEditAlert');
		$el.removeClass('d-none alert-info alert-danger alert-success alert-warning')
			.addClass('alert-' + kind)
			.empty();
		if (kind === 'success') {
			$el.append($('<i class="fa fa-check-circle"></i>')).append(document.createTextNode(' ' + msg));
		} else {
			$el.text(msg);
		}
		$el.show();
	}

	function ceFinishSaveAllSuccess(msg) {
		ceClosingAfterSave = true;
		var $btn = $('#btnSaveClassStudents');
		$btn.prop('disabled', true).removeClass('btn-primary').addClass('btn-success').text('Saved ✓');
		ceShowAlert('success', msg || 'Saved successfully.');
		ceSetStatus('ok', 'Saved successfully — closing…');
		window.setTimeout(function () {
			ceAllowHide = true;
			$('#classEditModal').modal('hide');
			showAlert('success', msg || 'Class student info saved.');
			window.setTimeout(function () {
				ceClosingAfterSave = false;
				$btn.prop('disabled', false).removeClass('btn-success').addClass('btn-primary').text('Save all');
			}, 400);
		}, 850);
	}

	function ceSaveField($el) {
		var id = parseInt($el.attr('data-id'), 10);
		var field = $el.attr('data-field');
		if (!id || !field) {
			return;
		}
		var val = ceNormField(field, ceRead($el));
		var orig = ceOrigMap[id] ? ceNormField(field, ceOrigMap[id][field]) : '';
		var st = ceFindStudent(id);
		if (st) {
			st[field] = val;
		}
		ceMarkRow(id);
		ceRefreshHints();
		if (val === orig) {
			return;
		}
		var key = id + ':' + field;
		if (ceSaving[key]) {
			return;
		}
		ceSaving[key] = true;
		ceMarkRow(id);
		ceSetStatus('busy', 'Saving ' + (st && st.fname ? st.fname : 'student') + '…');
		$.ajax({
			url: EDIT_FIELD_API,
			type: 'POST',
			dataType: 'json',
			data: withCsrf({ id: id, target: field, val: val })
		}).done(function (res) {
			if (res && res.success) {
				if (!ceOrigMap[id]) {
					ceOrigMap[id] = {};
				}
				ceOrigMap[id][field] = val;
				ceUpdateListRow(id, field, val);
				ceMarkRow(id);
				ceRefreshHints();
			} else {
				ceShowAlert('danger', (res && (res.error || res.msg)) || 'Could not save.');
				ceSetStatus('err', 'Could not save. Use Save all to retry.');
			}
		}).fail(function () {
			ceShowAlert('danger', 'Could not save. Please try again or use Save all.');
			ceSetStatus('err', 'Could not save. Use Save all to retry.');
		}).always(function () {
			delete ceSaving[key];
			ceMarkRow(id);
			ceRefreshHints();
		});
	}

	function ceWhenIdle() {
		var d = $.Deferred();
		function tick() {
			if (Object.keys(ceSaving).length === 0) {
				d.resolve();
			} else {
				window.setTimeout(tick, 80);
			}
		}
		tick();
		return d.promise();
	}

	function ceSaveAll(done) {
		ceSyncFromDom();
		var rows = ceDirtyRows();
		if (!rows.length) {
			ceShowAlert('success', 'Saved successfully.');
			ceSetStatus('ok', 'All student info saved');
			if (done) {
				done(true, 'Saved successfully.');
			}
			return;
		}
		var $btn = $('#btnSaveClassStudents');
		$btn.prop('disabled', true).text('Saving…');
		ceSetStatus('busy', 'Saving ' + rows.length + ' student' + (rows.length === 1 ? '' : 's') + '…');
		$.ajax({
			url: SAVE_CLASS_API,
			type: 'POST',
			dataType: 'json',
			data: withCsrf({ students: JSON.stringify(rows), studentsJson: JSON.stringify(rows) })
		}).done(function (res) {
			if (res && res.success) {
				rows.forEach(function (row) {
					var st = ceFindStudent(row.id);
					if (!st) {
						return;
					}
					if (!ceOrigMap[row.id]) {
						ceOrigMap[row.id] = {};
					}
					CE_FIELDS.forEach(function (field) {
						if (Object.prototype.hasOwnProperty.call(row, field)) {
							st[field] = ceNormField(field, row[field]);
							ceOrigMap[row.id][field] = st[field];
							ceUpdateListRow(row.id, field, st[field]);
						}
					});
					ceMarkRow(row.id);
				});
				ceShowAlert('success', res.message || 'Saved successfully.');
				ceRefreshHints();
				if (done) {
					done(true, res.message || 'Saved successfully.');
				}
			} else {
				ceShowAlert('danger', (res && res.error) || 'Could not save.');
				ceSetStatus('err', 'Save all failed. Please try again.');
				if (done) {
					done(false);
				}
			}
		}).fail(function (xhr) {
			var msg = 'Could not save. Please try again.';
			if (xhr && xhr.responseJSON && (xhr.responseJSON.error || xhr.responseJSON.message)) {
				msg = xhr.responseJSON.error || xhr.responseJSON.message;
			}
			ceShowAlert('danger', msg);
			ceSetStatus('err', 'Save all failed. Please try again.');
			if (done) {
				done(false);
			}
		}).always(function () {
			if (!ceClosingAfterSave) {
				$btn.prop('disabled', false);
				ceRefreshHints();
			}
		});
	}

	$(document).on('click', '#btnEditClassStudents', function () {
		ceClosingAfterSave = false;
		$('#btnSaveClassStudents').prop('disabled', false).removeClass('btn-success').addClass('btn-primary').text('Save all');
		ceInitOrig();
		$('#classEditAlert').addClass('d-none').text('');
		$('#classEditSearch').val('');
		renderClassEditRows('');
		$('#classEditModal').modal('show');
	});

	$(document).on('input', '#classEditSearch', function () {
		ceSyncFromDom();
		renderClassEditRows($(this).val());
	});

	$(document).on('input', '#classEditTable input.ce-field', function () {
		var $el = $(this);
		var id = parseInt($el.attr('data-id'), 10);
		var field = $el.attr('data-field');
		var st = ceFindStudent(id);
		if (st && field) {
			st[field] = ceNormField(field, ceRead($el));
		}
		ceMarkRow(id);
		ceRefreshHints();
	});

	$(document).on('change', '#classEditTable select.ce-field', function () {
		ceSaveField($(this));
	});

	$(document).on('focusout', '#classEditTable .ce-field', function (e) {
		var $el = $(this);
		if ($el.hasClass('ce-mode-toggle')) {
			return;
		}
		window.setTimeout(function () {
			ceSaveField($el);
		}, 40);
	});

	$(document).on('click', '#classEditTable .ce-mode-toggle button', function (e) {
		e.preventDefault();
		e.stopPropagation();
		var $btn = $(this);
		var $wrap = $btn.closest('.ce-mode-toggle');
		$wrap.find('button').removeClass('active');
		$btn.addClass('active');
		ceSaveField($wrap);
	});

	$(document).on('mousedown', '#classEditModal', function (e) {
		var $t = $(e.target);
		if ($t.closest('.ce-field, .ce-mode-toggle button').length) {
			return;
		}
		var $active = $('#classEditTable input.ce-field:focus, #classEditTable select.ce-field:focus');
		if ($active.length) {
			ceSaveField($active);
		}
	});

	$(document).on('click', '#btnSaveClassStudents', function () {
		var $btn = $(this);
		if ($btn.prop('disabled') || ceClosingAfterSave) {
			return;
		}
		ceWhenIdle().done(function () {
			ceSaveAll(function (ok, msg) {
				if (ok) {
					ceFinishSaveAllSuccess(msg || 'Saved successfully.');
				}
			});
		});
	});

	$('#classEditModal').on('hide.bs.modal', function (e) {
		if (ceAllowHide) {
			ceAllowHide = false;
			return;
		}
		ceSyncFromDom();
		if (ceFlushing) {
			e.preventDefault();
			return;
		}
		if (!ceDirtyCount() && Object.keys(ceSaving).length === 0) {
			return;
		}
		e.preventDefault();
		ceFlushing = true;
		ceWhenIdle().done(function () {
			ceSaveAll(function (ok) {
				ceFlushing = false;
				if (ok) {
					ceAllowHide = true;
					$('#classEditModal').modal('hide');
				}
			});
		});
	});
})(jQuery);
</script>
