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
											<?= lang("app.SchoolMenu") ?> </h6>
										<a type="button" tabindex="0" class="dropdown-item" data-toggle="modal" data-target="#mdlpkg"><i
												class="typcn typcn-plus"> </i><span><?= lang("app.AddNewPackage") ?> </span>
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
												<th><?= lang("app.title") ?> </th>
												<th><?= lang("app.Smslimit") ?></th>
												<th></th>
											</tr>
											</thead>
											<tbody>
											<?php
											foreach ($packages as $package) {
												?>
											<tr>
												<td><?=$package['title'];?></td>
												<td><?=$package['sms_limit'];?></td>
												<td>
													<center>
													<label class="typcn typcn-edit text-info link"  data-id="<?=$package['id'];?>"  data-toggle="modal" data-target="#mdlpkg">Edit</label>
													<label class="typcn typcn-delete text-danger link" data-toggle="delete"
														   data-target="<?=$package['id'];?>" data-href="admin/delete_package"><?= lang("app.del") ?> </label></center>
												</td>
											</tr>
												<?php
											}
											?>
											</tbody>
											<tfoot>
											<tr>
												<th><?= lang("app.title") ?> </th>
												<th><?= lang("app.Smslimit") ?></th>
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
