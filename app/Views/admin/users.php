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
											<?= lang("app.SchoolMenu");?> </h6>
										<a type="button" tabindex="0" href="javascript:void" class="dropdown-item" data-toggle="modal" data-target="#mdlUser"><i
												class="typcn typcn-plus"> </i><span><?= lang("app.sddNewUsers");?> </span>
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
												<th><?= lang("app.names");?> </th>
												<th><?= lang("app.email");?> </th>
												<th><?= lang("app.lastLogin");?> </th>
												<th><?= lang("app.createdTime");?> </th>
												<th><?= lang("app.status");?> </th>
												<th></th>
											</tr>
											</thead>
											<tbody>
											<?php
											foreach ($users as $user) {
												$status = $user['status']==1 || $user['status']==2?'<label class="text-success" data-toggle="update" data-href="admin/change_status/user/0" data-target="'.$user["id"].'">'. lang("app.active") .'</label>'
													:'<label class="text-danger" data-toggle="update" data-href="admin/change_status/user/1" data-target="'.$user["id"].'">'. lang("app.locked") .'</label>';
												$disable = $_SESSION['soma_admin_id']==$user['id']?"onclick='return false'":"";
												?>
											<tr>
												<td><?=$user['names'];?></td>
												<td><?=$user['email'];?></td>
												<td><?=date("Y-d-m H:i:s",$user['last_login']);?></td>
												<td><?=$user['created_at'];?></td>
												<td><?=$status;?></td>
												<td>
													<label class="typcn typcn-delete text-danger link" data-toggle="delete"
														   data-target="<?=$user['id'];?>" <?=$disable;?> data-href="admin/delete_user"><?= lang("app.del");?> </label>
												</td>
											</tr>
												<?php
											}
											?>
											</tbody>
											<tfoot>
											<tr>
												<th><?= lang("app.names");?> </th>
												<th><?= lang("app.email");?> </th>
												<th><?= lang("app.lastLogin");?> </th>
												<th><?= lang("app.createdTime");?> </th>
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

