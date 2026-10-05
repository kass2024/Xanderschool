<style>
	.comm-portal .card-body > form { display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-start; }
	.comm-portal .comm-recipients, .comm-portal .comm-compose { float: none !important; width: auto; max-width: 100%; padding: 0; }
	.comm-portal .comm-recipients { flex: 1 1 340px; min-width: 0; }
	.comm-portal .comm-compose { flex: 1 1 280px; background: #fff; border: 1px solid #e6e8ee; border-radius: 12px; padding: 14px; }
	.comm-progress { flex: 1 1 100%; background: #fff; border: 1px solid #e6e8ee; border-radius: 12px; padding: 14px; }
	.comm-kpis { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; margin-bottom: 10px; }
	.comm-kpi { border-radius: 10px; padding: 10px 12px; background: #f8fafc; border-top: 4px solid #1d4ed8; }
	.comm-kpi b { display: block; font-size: 1.35rem; line-height: 1.1; }
	.comm-kpi span { color: #6b7280; font-size: .78rem; }
	.comm-kpi.green { border-top-color: #16a34a; } .comm-kpi.green b { color: #16a34a; }
	.comm-kpi.red { border-top-color: #dc2626; } .comm-kpi.red b { color: #dc2626; }
	.comm-kpi.blue b { color: #1d4ed8; }
	.comm-bar-track { height: 14px; background: #e5e7eb; border-radius: 999px; overflow: hidden; }
	.comm-bar { height: 100%; width: 0; background: #1d4ed8; color: #fff; font-size: 11px; text-align: center; line-height: 14px; }
	.comm-failed { list-style: none; margin: 10px 0 0; padding: 0; max-height: 240px; overflow: auto; }
	.comm-failed li { display: flex; flex-wrap: wrap; gap: 6px 12px; padding: 8px 0; border-bottom: 1px solid #fee2e2; color: #991b1b; }
	.comm-failed li span { color: #6b7280; }
	@media (max-width: 700px) {
		.comm-kpis { grid-template-columns: 1fr; }
		.comm-portal .card-header .nav { display: flex; flex-wrap: wrap; gap: 6px; }
		.comm-portal #btn_send_sms { width: 100% !important; }
	}
</style>
<div class="main-card mb-3 card col-sm-12 col-md-12 col-lg-12 comm-portal">
	<div class="card-header">
		<div class="btn-actions-pane-left">
			<div class="nav">
				<a data-toggle="tab" href="#tab-department"
				   class="btn-pill btn-sms-categ btn-wide mr-1 ml-1 btn btn-outline-alternate btn-sm active" data-type="dep"><?= lang("app.sendtoCombinations"); ?></a>
				<a data-toggle="tab" href="#tab-class"
				   class="btn-pill btn-sms-categ btn-wide btn btn-outline-alternate btn-sm" data-type="class"><?= lang("app.sendToClass"); ?></a>
				<a data-toggle="tab" href="#tab-student"
				   class="btn-pill btn-sms-categ btn-wide btn btn-outline-alternate btn-sm" data-type="student"><?= lang("app.sendToStudents"); ?></a>
			</div>
		</div>
		<span class="pull-right"><?= lang("app.parentMessaging"); ?></span>
	</div>
	<div class="card-body">
		<form class="validate comm-sms-form" action="<?=base_url('send_multiple_sms');?>">
			<input type="hidden" id="type" value="dep" name="type">
			<div class="tab-content comm-recipients">
				<div class="tab-pane active" id="tab-department" role="tabpanel">
					<div class="card-body">
						<div class="position-relative form-group">
							<div>
								<?php
								foreach ($departments as $dept) {
									$phoneLines = (int) $dept['boarding_phone'] + (int) $dept['day_phone'];
									$missingParents = (int) $dept['boarding'] + (int) $dept['day'] - (int) ($dept['boarding_with'] ?? 0) - (int) ($dept['day_with'] ?? 0);
									if ($missingParents < 0) {
										$missingParents = 0;
									}
									?>
									<div class="custom-checkbox custom-control" style="margin-bottom: 10px">
										<input type="checkbox" name="dept_id[]"
											   data-boarding="<?= (int) $dept['boarding_phone']; ?>"
											   data-day="<?= (int) $dept['day_phone']; ?>"
											   id="chk_dept<?= $dept['id'];?>" value="<?= $dept['id']; ?>" class="custom-control-input chk_item">
										<label class="custom-control-label" for="chk_dept<?= $dept['id']; ?>"><?= lang("app.fromParent"); ?> <?= $dept['title']; ?>
											<span class="text-boxed text-success"><i
													class="fa fa-phone"><?= $phoneLines; ?></i> </span>
											<span class="text-boxed text-danger"><i
													class="fa fa-times"><?= $missingParents; ?></i> </span>
										</label>
									</div>
									<?php
								}
								?>
							</div>
						</div>
					</div>
				</div>
				<div class="tab-pane" id="tab-class" role="tabpanel">
					<div class="card-body">
						<div class="position-relative form-group">
							<div>
								<?php
								foreach ($classes as $class) {
									$phoneLines = (int) $class['boarding_phone'] + (int) $class['day_phone'];
									$missingParents = (int) $class['boarding'] + (int) $class['day'] - (int) ($class['boarding_with'] ?? 0) - (int) ($class['day_with'] ?? 0);
									if ($missingParents < 0) {
										$missingParents = 0;
									}
									?>
									<div class="custom-checkbox custom-control" style="margin-bottom: 10px">
										<input type="checkbox" name="class_id[]"
											   data-boarding="<?= (int) $class['boarding_phone']; ?>"
											   data-day="<?= (int) $class['day_phone']; ?>"
											   id="chk_class<?= $class['id'];?>" value="<?= $class['id']; ?>" class="custom-control-input chk_item">
										<label class="custom-control-label" for="chk_class<?= $class['id']; ?>"><?= lang("app.fromParent"); ?><?= $class['level'].' '.$class['code'].' '.$class['class']; ?>
											<span class="text-boxed text-success"><i
													class="fa fa-phone"><?= $phoneLines; ?></i> </span>
											<span class="text-boxed text-danger"><i
													class="fa fa-times"><?= $missingParents; ?></i> </span>
										</label>
									</div>
									<?php
								}
								?>
							</div>
						</div>
					</div>
				</div>
				<div class="tab-pane" id="tab-student" role="tabpanel">
					<div id="search_student_dv" style="width: 300px">
						<select class="form-control select3" name="search_student" id="search_student">
						</select>
					</div>

					<div style="background:white;padding: 10px;max-height: 500px;overflow: auto;">
						<table class="table table-hover table-fixed">
							<!--Table head-->
							<thead>
							<tr>
								<th><?= lang("app.regNo"); ?></th>
								<th><?= lang("app.studentName"); ?></th>
								<th><?= lang("app.sClass"); ?></th>
								<th><?= lang("app.phone"); ?></th>
								<th style="align-content: center;"><?= lang("app.remove"); ?></th>
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
							<?= lang("app.studentwhnotparent"); ?>

						</label>

						<!--Table-->
					</div>
				</div>
				<div style="display: inline-grid;margin-left: 40%;"><?= lang("app.TotalSMS"); ?>
					<span class="badge badge-primary" id="sms_to_send" style="font-size: 25pt">0</span>
				</div>
			</div>
			<div class="comm-compose">
				<div style="background:white;padding: 10px">
					<div class="row" style="">
						<div class="position-relative form-group">
							<div>
								<div class="custom-checkbox custom-control custom-control-inline">
									<input type="checkbox" id="chk-boarding" class="custom-control-input chk_mode"
										   checked name="mode_boarding" value="1">
									<label class="custom-control-label" for="chk-boarding"><?= lang("app.boarding"); ?></label>
								</div>
								<div class="custom-checkbox custom-control custom-control-inline chk_mode">
									<input type="checkbox" id="chk-day" class="custom-control-input"
										   checked name="mode_day" value="1">
									<label class="custom-control-label" for="chk-day"><?= lang("app.day"); ?></label>
								</div>
							</div>
						</div>
					</div>

					<div class="row" style="margin-top: 15px;">
						<div class="col-md-3 pull-left">
							<label><?= lang("app.message"); ?> </label>
						</div>
						<div class="col-md-12 pull-left">
							<input type="hidden" name="estimation" id="txt-estimation">
							<textarea class="form-control" name="message" id="txt-msg"
									  style="min-height: 200px"></textarea>
							<label class="pull-right" id="lbl-count">0/1</label>
						</div>
					</div>
					<div class="row" style="margin-top: 20px;">
						<div class="col-md-12 pull-left">

							<center>
								<button type="submit" class="btn btn-success btn-lg"
										style="width: 50%;font-size: 14px;" id="btn_send_sms" disabled><i
										class="typcn typcn-messages"></i>
									<?= lang("app.endSMS"); ?>
								</button>
							</center>
						</div>
					</div>
				</div>
			</div>
			<div class="comm-progress" id="commProgress" style="display:none">
				<strong id="commStatusLabel">Sending messages…</strong>
				<div class="comm-kpis">
					<div class="comm-kpi blue"><b id="commPending">0</b><span>Waiting</span></div>
					<div class="comm-kpi green"><b id="commSent">0</b><span>Delivered</span></div>
					<div class="comm-kpi red"><b id="commFailed">0</b><span>Failed</span></div>
				</div>
				<div class="comm-bar-track"><div class="comm-bar" id="commBar">0%</div></div>
				<ul class="comm-failed" id="commFailedList"></ul>
			</div>
		</form>
	</div>

	<div class="d-block text-right card-footer" style="display: none !important;overflow: hidden">
		<a href="javascript:void(0);" class="btn-wide btn btn-success">Save</a>
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
					url: "<?=base_url('search_student');?>",
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
				placeholder: "<?= lang("app.searchBy"); ?>",
				minimumInputLength: 3
			});
		});
		$("#messagingTable").on('click', '#removerow', function () {
			$(this).closest('tr').remove();
			populate_sms_count();
		});
		$("#search_student").on('select2:select', function (selection) {
			formatRepoSelection(selection.params.data);
		});
	});
	function populate_sms_count() {
		total_sms_count = 0;
		$.each($(".chk_item"), function () {
			if ($(this).is(":checked")) {
				if (type=="student"){
					var lines = parseInt($(this).attr("data-lines"), 10);
					total_sms_count += (lines > 0 ? lines : 1);
				}else {
					var boarding = $("#chk-boarding").is(":checked") ? $(this).data("boarding") : 0;
					var day = $("#chk-day").is(":checked") ? $(this).data("day") : 0;
					total_sms_count += boarding + day;
				}
			}
		});
		$("#sms_to_send").text(total_sms_count * sms_count);
		$("#txt-estimation").val(total_sms_count * sms_count);
		if (total_sms_count == 0) {
			$("#btn_send_sms").prop("disabled", true);
		} else {
			$("#btn_send_sms").prop("disabled", false);
		}
	}
	var commPoll = null;
	$(".comm-sms-form").on("submit", function (e) {
		e.preventDefault();
		e.stopImmediatePropagation();
		var form = $(this);
		var btn = $("#btn_send_sms");
		var original = btn.html();
		btn.prop("disabled", true).html('<i class="fa fa-spinner fa-spin"></i> Sending…');
		$.ajax({
			url: form.attr("action"),
			type: "POST",
			data: form.serialize(),
			dataType: "json"
		}).done(function (data) {
			btn.html(original);
			if (data.error) {
				toastada.error(data.error);
				btn.prop("disabled", false);
				return;
			}
			toastada.success(data.success || "Messages queued.");
			if (data.sms_id) {
				commWatch(data.sms_id);
			} else {
				btn.prop("disabled", false);
			}
		}).fail(function () {
			btn.html(original).prop("disabled", false);
			toastada.error("Could not send messages.");
		});
	});
	function commWatch(smsId) {
		if (commPoll) clearInterval(commPoll);
		$("#commProgress").show();
		$("#commStatusLabel").text("Sending messages…");
		$("#commFailedList").empty();
		function tick() {
			$.getJSON("<?= base_url('sms_delivery_status/'); ?>" + smsId, function (res) {
				if (!res || res.error) return;
				$("#commPending").text(res.pending);
				$("#commSent").text(res.sent);
				$("#commFailed").text(res.failed);
				$("#commBar").css("width", res.percent + "%").text(res.percent + "%");
				var html = "";
				$.each(res.failed_list || [], function (_, row) {
					html += "<li><b>" + $("<div>").text(row.name).html() + "</b> " + $("<div>").text(row.phone).html() + " <span>" + $("<div>").text(row.reason).html() + "</span></li>";
				});
				$("#commFailedList").html(html);
				if (res.pending === 0) {
					clearInterval(commPoll);
					commPoll = null;
					$("#commStatusLabel").text(res.failed > 0 ? "Finished. Some messages failed." : "All messages delivered.");
					$("#btn_send_sms").prop("disabled", false);
				}
			});
		}
		tick();
		commPoll = setInterval(tick, 2000);
	}
	function formatRepoSelection(repo, isClass = false) {
		var id = repo.id;
		var isError = false;
			$("#search_student").val(null).trigger('change');

			$('input[name^="discId"]').each(function () {
				if (this.value == id) {
					//student already exists
					toastada.warning(repo.text + '<?= lang("app.alreadonList"); ?>');
					isError = true;
					return false;
				}
			});

		if (isError)
			return;
		$.get("<?=base_url();?>get_student/" + id + "/0/3", function (data) {
			$("#messagingTable").append(data);
			populate_sms_count();
		})
	}
</script>
