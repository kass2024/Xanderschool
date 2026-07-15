<style>
	.hours {
		border: 1px solid
		#4ec4b3;
		margin: 5px;
		padding: 5px;
		border-radius: 5px;
	}
	.hours .remove-hours{
		float: right;
		color: orangered;
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
											<?= lang("app.sShiftMenu"); ?></h6>
										<a type="button" tabindex="0" href="javascript:void" class="dropdown-item" data-toggle="modal" data-target="#mdlAddShift"><i
												class="typcn typcn-plus"> </i><span><?= lang("app.addNewShift"); ?></span>
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
												<th>#</th>
												<th><?= lang("app.title"); ?></th>
												<th><?= lang("app.days"); ?></th>
												<th><?= lang("app.staffs"); ?></th>
												<th><?= lang("app.createdTime"); ?></th>
												<th><?= lang("app.status"); ?></th>
												<th></th>
											</tr>
											</thead>
											<tbody>
											<?php
											$a = 1;
											foreach ($shifts as $shift) {
												$status = $shift['status'] == 1 ? '<label class="text-success lnk" data-toggle="update" data-href="change_status/shift/0" data-target="' . $shift["id"] . '">'.lang("app.active").'</label>'
													: '<label class="text-danger lnk" data-toggle="update" data-href="change_status/shift/1" data-target="' . $shift["id"] . '">'.lang("app.locked").'</label>';
												$days = "";
												$hours = json_decode($shift['options'], true);
												$t_day = ((int)date("N", time()) - 1);
												$t_hour = (int)date("H", time());
												?>
											<tr>
												<td><?=$a;?></td>
												<td><?=$shift['title'];?></td>
												<td><div class="hours-view">
													<?php
//													for ($a = 0; $a < 7; $a++):
//														$b = 0;
														foreach ($hours as $hour):
															$hhh = explode(" ", $hour);
															$weekday = $hhh[0];
															$start = $hhh[1];
															$end = $hhh[2];
															?>
																<div class='hours' style="font-size: 9pt">
																	<span class='dayy'><?=days_mini($weekday);?></span>:
																	<span class='openn'><?=hours($start);?></span> -
																	<span class='closee'><?=hours($end);?></span>
																</div>
														<?php
														endforeach;
//													endfor;
													?>
													</div>
												</td>
												<td><label class="badge badge-success" style="font-size: 14pt"> <?=$shift['staffs'];?></label></td>
												<td><?=$shift['created_at'];?></td>
												<td><?=$status;?></td>
												<td>
													<label class="typcn typcn-delete text-danger link" data-toggle="delete" data-title="shift #<?=$shift['title'];?>"
														   data-target="<?=$shift['id'];?>" data-href="delete_shift"><?= lang("app.del"); ?></label>
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
												<th><?= lang("app.title"); ?></th>
												<th><?= lang("app.days"); ?></th>
												<th><?= lang("app.staffs"); ?></th>
												<th><?= lang("app.createdTime"); ?></th>
												<th><?= lang("app.status"); ?></th>
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
		var hourview = $(".hours-view");
		$(".addhours").click(function () {
			var weekday = $(this).closest(".add-hours").children().children(".weekday");
			var open = $(this).closest(".add-hours").children().children(".hours-start");
			var close = $(this).closest(".add-hours").children().children(".hours-end");
			var weekdayVal = weekday.val();
			var weekdayText = weekday.find('option:selected').text();
			var openVal = open.val();
			var openText = open.find('option:selected').text();
			var closeVal = close.val();
			var closeText = close.find('option:selected').text();
			hourview.append("<div class='hours'><span class='dayy'>"+weekdayText+"</span><span class='openn'>"+openText+" </span><span>-</span>"+
				"<span class='closee'> "+closeText+" </span><a href='javascript:void(0)' class='remove-hours'>Remove</a>"+
				"<input name='hours[]' value='"+weekdayVal+" "+openVal+" "+closeVal+"' type='hidden'> </div>");
			if(weekdayVal=="6")
				weekday.val(0);
			else
				weekday.val(parseInt(weekdayVal)+1);
		});
		$(document).on("click",".remove-hours",function () {
			var hours = $(this).closest(".hours");
			hours.remove();
		});
	})
</script>
