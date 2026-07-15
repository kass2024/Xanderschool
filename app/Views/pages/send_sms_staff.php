<div class="main-card mb-3 card col-sm-12 col-md-12 col-lg-12">
	<div class="card-header">
		<div class="btn-actions-pane-left">
			<div class="nav">
				<a data-toggle="tab" href="#tab-post"
				   class="btn-pill btn-sms-categ btn-wide mr-1 ml-1 btn btn-outline-alternate btn-sm active" data-type="post"><?= lang("app.sendByPost"); ?></a>
				<a data-toggle="tab" href="#tab-staff"
				   class="btn-pill btn-sms-categ btn-wide btn btn-outline-alternate btn-sm" data-type="staff"><?= lang("app.sendSoStaff"); ?></a>
			</div>
		</div>
		<span class="pull-right"><?= lang("app.employeeMessaging"); ?></span>
	</div>
	<div class="card-body">
		<form class="validate autoSubmit" action="<?=base_url('send_multiple_sms_staff');?>">
			<input type="hidden" id="type" value="post" name="type">
			<div class="tab-content col-sm-12 col-lg-7 col-md-7 pull-left">
				<div class="tab-pane active" id="tab-post" role="tabpanel">
					<div class="card-body">
						<div class="position-relative form-group">
							<div>
								<?php
								foreach ($posts as $post) {
									?>
									<div class="custom-checkbox custom-control" style="margin-bottom: 10px">
										<input type="checkbox" name="post_id[]" data-phone="<?= $post['phone']; ?>"
											   id="chk_post<?= $post['id'];?>" value="<?= $post['id']; ?>" class="custom-control-input chk_item">
										<label class="custom-control-label" for="chk_post<?= $post['id']; ?>"><?= lang("app.toEmp"); ?> <?= $post['title']; ?>
											<span class="text-boxed text-success"><i
													class="fa fa-phone"><?= $post['phone']; ?></i> </span>
											<span class="text-boxed text-danger"><i
													class="fa fa-times"><?= $post['no_phone']; ?></i> </span>
										</label>
									</div>
									<?php
								}
								?>
							</div>
						</div>
					</div>
				</div>
				<div class="tab-pane" id="tab-staff" role="tabpanel">
					<div id="search_staff_dv" style="width: 300px">
						<select class="form-control select3" name="search_staff" id="search_staff">
						</select>
					</div>

					<div style="background:white;padding: 10px;max-height: 500px;overflow: auto;">
						<table class="table table-hover table-fixed">
							<!--Table head-->
							<thead>
							<tr>
								<th></th>
								<th><?= lang("app.employeeID"); ?></th>
								<th><?= lang("app.employeeName"); ?></th>
								<th><?= lang("app.post"); ?></th>
								<th><?= lang("app.phone"); ?></th>
								<th style="align-content: center;"><?= lang("app.remove");?></th>
							</tr>
							</thead>
							<!--Table head-->
							<!--Table body-->
							<tbody id="messagingTable">

							</tbody>
							<!--Table body-->
						</table>
						<label><strong><?= lang("app.legend"); ?>: </strong><span class="badge badge-primary"
															  style="background-color: orangered !important;"> </span>
							<?= lang("app.Stafwhohas"); ?>

						</label>

						<!--Table-->
					</div>
				</div>
				<div style="display: inline-grid;margin-left: 40%;"><?= lang("app.TotalSMS"); ?>
					<span class="badge badge-primary" id="sms_to_send" style="font-size: 25pt">0</span>
				</div>
			</div>
			<div class="col-lg-5 col-md-5 col-sm-12 pull-left">
				<div style="background:white;padding: 10px">
					<div class="row" style="margin-top: 15px;">
						<div class="col-md-3 pull-left">
							<label><?= lang("app.message"); ?> </label>
						</div>
						<div class="col-md-12 pull-left">
							<textarea class="form-control" name="message" id="txt-msg"
									  style="min-height: 200px"></textarea>
							<label class="pull-right" id="lbl-count">0/1</label>
						</div>
					</div>
					<div class="row" style="margin-top: 20px;">
						<div class="col-md-12 pull-left">

							<center>
								<button type="submit" class="btn btn-success btn-lg"
										style="width: 50%;font-size: 14px;" id="btn_send_sms" disabled
										data-target="<?= base_url('messaging/employees'); ?>"><i
										class="typcn typcn-messages"></i>
									<?= lang("app.endSMS"); ?>
								</button>
							</center>
						</div>
					</div>
				</div>
			</div>
		</form>
	</div>

	<div class="d-block text-right card-footer" style="display: none !important;overflow: hidden">
		<a href="javascript:void(0);" class="btn-wide btn btn-success"><?= lang("app.save");?></a>
	</div>
</div>
<script>
	var total_sms_count = 0, sms_count = 0,type="";
	$(function () {
		$("#txt-msg").on("keyup", function () {
			var count = $(this).val().length;
			var msgs = Math.ceil(count / 160);
			if (msgs != sms_count) {
				//prevent looping on every typing
				sms_count = msgs;
				populate_sms_count();
			}
			$("#lbl-count").text(count + "/" + sms_count);
		});
		$(document).on("change", ".chk_item,.chk_mode", function () {
			populate_sms_count();
		});
		$(document).on("click", ".btn-sms-categ", function () {
			$(".chk_item").prop("checked", false);
			$("#sms_to_send").text("0");
			total_sms_count = 0;
			sms_count = 0;
			$("#btn_send_sms").prop("disabled", true);
			type = $(this).data("type");
			$("#type").val(type);
		});

		$(document).ready(function () {
			$(".select3").select2({
				ajax: {
					url: "<?=base_url('search_staff');?>",
					type: "post",
					dataType: 'json',
					delay: 250,
					data: function (params) {
						return {
							searchTerm: params.term // search term
						};
					},
					processResults: function (response) {
						return {
							results: response
						};
					},
					cache: true
				},
				placeholder: '<?= lang("app.searchBy"); ?>',
				minimumInputLength: 3
			});
		});
		$("#messagingTable").on('click', '#removerow', function () {
			$(this).closest('tr').remove();
			populate_sms_count();
		});
		$("#search_staff").on('select2:select', function (selection) {
			formatRepoSelection(selection.params.data);
		});
	});
	function populate_sms_count() {
		total_sms_count = 0;
		$.each($(".chk_item"), function () {
			if ($(this).is(":checked")) {
				if (type=="staff"){
					total_sms_count += 1;
				}else {
					var phone = $(".chk_item").is(":checked") ? $(this).data("phone") : 0;
					total_sms_count += phone;
				}
			}
		});
		$("#sms_to_send").text(total_sms_count * sms_count);
		if (total_sms_count == 0) {
			$("#btn_send_sms").prop("disabled", true);
		} else {
			$("#btn_send_sms").prop("disabled", false);
		}
	}
	function formatRepoSelection(repo, isClass = false) {
		var id = repo.id;
		var isError = false;
			$("#search_staff").val(null).trigger('change');

			$('input[name^="discId"]').each(function () {
				if (this.value == id) {
					//staff already exists
					toastada.warning(repo.text + '<?= lang("app.alreadonList"); ?>');
					isError = true;
					return false;
				}
			});

		if (isError)
			return;
		$.get("<?=base_url();?>get_staff/" + id + "/0/3", function (data) {
			$("#messagingTable").append(data);
			populate_sms_count();
		})
	}
</script>
