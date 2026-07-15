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
												<th>#</th>
												<th><?= lang("app.regno"); ?></th>
												<th><?= lang("app.names"); ?></th>
												<th><?= lang("app.mode"); ?></th>
												<th><?= lang("app.gender"); ?></th>
												<th><?= lang("app.activeParent"); ?></th>
												<th></th>
											</tr>
											</thead>
											<tbody>
											<?php
											$a = 1;
											foreach ($students as $student) {
												$status = $student['status'] == 1 || $student['status'] == 2 ? '<label class="text-success lnk" data-toggle="update" data-href="change_status/student/0" data-target="' . $student['id'] . '" data-target-record="' . $student['id'] . '">'.lang("app.active").'</label>'
													: '<label class="text-danger lnk" data-toggle="update" data-href="change_status/student/1" data-target="' . $student['id'] . '" data-target-record="' . $student['id'] . '">'.lang("app.locked").'</label>';
												$parent = '';
												if (strlen($student['father']) > 3) {
													$parent = "<span class='badge badge-pill badge-success'>".lang("app.father")."</span> " . $student['father'] . "<br><a href='tel:{$student['ft_phone']}'>{$student['ft_phone']}</a>";
												} else if (strlen($student['mother']) > 3) {
													$parent = "<span class='badge badge-pill badge-info'>".lang("app.mother")."</span> " . $student['mother'] . "<br><a href='tel:{$student['mt_phone']}'>{$student['mt_phone']}</a>";
												} else if (strlen($student['guardian']) > 3) {
													$parent = "<span class='badge badge-pill badge-primary'>".lang("app.guardian")."</span> " . $student['guardian'] . "<br><a href='tel:{$student['gd_phone']}'>{$student['gd_phone']}</a>";
												}
												?>
												<tr>
													<td><?= $a; ?></td>
													<td><a href="<?= base_url('student/' . $student['id']); ?>"
														   class="link"><?= $student['regno']; ?></a></td>
													<td><?= $student['fname'] . ' ' . $student['lname']; ?></td>
													<td><?= \App\Controllers\Home::ModeToStr($student['studying_mode']); ?></td>
													<td><?= $student['sex']; ?></td>
													<td><?= $parent; ?></td>
													<td>
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
												<th>#</th>
												<th><?= lang("app.regno"); ?></th>
												<th><?= lang("app.names"); ?></th>
												<th><?= lang("app.mode"); ?></th>
												<th><?= lang("app.gender"); ?></th>
												<th><?= lang("app.activeParent"); ?></th>
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
