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
												<th><?= lang("app.status");?> </th>
												<th></th>
											</tr>
											</thead>
											<tbody>
											<?php
											foreach ($schools as $school) {
												$status = $school['status']==1?'<label class="text-success" data-toggle="update" data-href="admin/change_status/school/0" data-target="'.$school["id"].'">'. lang("app.active") .'</label>'
													:'<label class="text-danger" data-toggle="update" data-href="admin/change_status/school/1" data-target="'.$school["id"].'">'. lang("app.locked") .'</label>';
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
												<td><?=($school['sms_limit']-$school['sms_usage']);?></td>
												<td><?=$school['country'];?></td>
												<td><?=$status;?> </td>
												<td><label class="typcn typcn-delete text-danger link" data-toggle="delete"
														   data-target="<?=$school['id'];?>" data-href="admin/delete_school" data-title="school #<?=$school["name"];?>"><?= lang("app.del");?> </label></td>
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
<script>
	$(function () {
		var sp, value,sp_td, old_data,target,type = null;
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
			}if (type == "number" || type == "digit") {
				sp.html("<input type='text' data-parsley-type='number' value='" + value + "' class='sptxt'>");
				$(".sptxt").focus();
			}if (type == "select") {
				sp.html("<select class='select2_auto' style='width:200px !important' data-value='" + value + "' data-href='" + sp.data("href") + "' class='spselect'>");
				load_select(sp_td.data("href"),value);
			}
		});
		$(document).on('select2:select',".select2_auto", function (selection) {
			var id = $("#settingsCnt").data("id");
			var val = $(this).val();
			$.post("<?=base_url('manipulate_school/');?>" + type+"/"+$(this).data("href"), "id=" + id + "&target=" + target + "&val=" + val, function (data) {
				if (data.hasOwnProperty("error")) {
					toastada.error("Save settings failed: " + data.msg);
				} else if (data.hasOwnProperty("success")) {
					sp_td.html(data.result);
					sp_td.data("value", val);
				} else {
					//unknown error
					toastada.error(<?= lang("app.fatalErr"); ?>);
				}
			}).fail(function () {
				//unknown error
				toastada.error(<?= lang("app.systemErr"); ?>);
			});
		});

		function load_select(href,value) {
			$.ajax({
				type: "GET",
				dataType: "json",
				async: true,
				url: "<?=base_url();?>admin/"+href,
				data: ({
				}),
				success: function (data) {
					$('.select2_auto').select2({
						data: data,
						//templateSelection: selection,
						//templateResult: formatState,
						escapeMarkup: function (state) {
							return state;
						},
						placeholder: "Select..."
					});
					if (value.length>0)
						$('.select2_auto').val(value).trigger("change");
				}
			});
		}
	});
	function formatState (state) {
	    if (!state.id) {
	        return state.text;
	    }
	    var state1 = state.text + '<h6>(' + state.id  + ') ' + state.text + '</h6>';
	    return state1;
	};

	function selection(state) {
	    return state.text;
	}
</script>
