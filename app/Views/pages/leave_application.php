<?php $obj=new App\Controllers\Home();?>
<button class="btn btn-success btn-lg" data-toggle="modal" data-target="#apply_leave_model" style="margin-left: 30px"><span class="fa fa-plus"></span> <?= lang("app.applyforLeave");?></button>
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
							<div class="btn-actions-pane-right actions-icon-btn">
							
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
												<th><?= lang("app.names");?></th>
												<th><?= lang("app.leaveType");?></th>
												<th><?= lang("app.reason");?></th>
												<th><?= lang("app.leaveFrom");?> </th>
												<th><?= lang("app.leaveTo");?></th>
												<th><?= lang("app.address");?></th>
												<th><?= lang("app.status");?></th>
											</tr>
											</thead>
											<tbody>
											<?php
											$i=1;
											foreach ($leaves as $leave) {
												if ($leave['status']==0){
													echo "<tr style='color:#0b5885;'>";
												}else if($leave['status']==1){
													echo "<tr class='text-success'>";
												}
												else{
													echo "<tr class='text-warning'>";
												}
												?>
												<td><?=$i;?></td>
												<td><?= $leave['staff']; ?></td>
												<td><?= $obj->leaveTypeTostr($leave['type']); ?></td>
												<td><?= $leave['reason']; ?></td>
												<td><?= date("Y-d-m",$leave['fromDate']); ?></td>
												<td><?= date("Y-d-m",$leave['toDate']); ?></td>
												<td><?= $leave['address']; ?></td>
												<td><?= $obj->leaveStatusTostr($leave['status']); ?></td>
												</tr>
												<?php
												$i++;
											}
											?>
											</tbody>
											<tfoot>
											<tr>
												<th>#</th>
												<th><?= lang("app.names");?></th>
												<th><?= lang("app.leaveType");?></th>
												<th><?= lang("app.reason");?></th>
												<th><?= lang("app.leaveFrom");?> </th>
												<th><?= lang("app.leaveTo");?></th>
												<th><?= lang("app.address");?></th>
												<th><?= lang("app.status");?></th>
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

