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
											<?= lang("app.staffMenu");?></h6>
										<a type="button" tabindex="0" href="javascript:void" class="dropdown-item" data-toggle="modal" data-target="#mdlStaff"><i
												class="typcn typcn-plus"> </i><span><?= lang("app.addnewStaff");?></span>
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
											   class=" table-hover table-striped table-bordered">
											<thead>
											<tr role="row">
												<th><?= lang("app.names");?></th>
												<th><?= lang("app.phone");?></th>
												<th><?= lang("app.email");?></th>
												<th><?= lang("app.privilege");?></th>
												<th><?= lang("app.shift");?></th>
												<th><?= lang("app.lastLogin");?></th>
												<th><?= lang("app.createdTime");?> </th>
												<th><?= lang("app.status");?></th>
												<th></th>
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
												?>
											<tr data-id="<?=$staff['id'];?>">
												<td><a href="<?=base_url('staff/'.$staff['id']);?>" class="link"><?=$staff['fname'].' '.$staff['lname'];?></a></td>
												<td><?=$staff['phone'];?></td>
												<td><?=$staff['email'];?></td>
												<td data-post="<?=$staff['post'];?>" data-post_title="<?=$staff['post_title'];?>"><?=$post;?></td>
												<td data-shift="<?=$staff['shift_id'];?>" data-shift_title="<?=$staff['shift_title'];?>"><?=$shift;?></td>
												<td><?=date("Y-d-m H:i:s",$staff['last_login']);?></td>
												<td><?=$staff['created_at'];?></td>
												<td><?=$status;?></td>
												<td>
													<label class="typcn typcn-delete text-danger link" data-toggle="delete" data-title="Staff #<?=$staff['fname'];?>"
														   data-target="<?=$staff['id'];?>" <?=$disable;?> data-href="delete_staff"><?= lang("app.del");?></label>
												</td>
											</tr>
												<?php
											}
											?>
											</tbody>
											<tfoot>
											<tr>
												<th><?= lang("app.names");?></th>
												<th><?= lang("app.phone");?></th>
												<th><?= lang("app.email");?></th>
												<th><?= lang("app.privilege");?></th>
												<th><?= lang("app.shift");?></th>
												<th><?= lang("app.lastLogin");?></th>
												<th><?= lang("app.createdTime");?> </th>
												<th><?= lang("app.status");?></th>
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
	})
</script>
