<div class="app-inner-layout app-inner-layout-page">
	<div class="app-inner-layout__wrapper" style="display: block;padding-left: 20px">
		<style>
			.vl {
				border-left: 3px solid #3ac47d;
			}
		</style>
		<div class="pull-left" style="width: 100%">
			<div class="col-md-6 col-sm-12 col-lg-4 pull-left">
				<input type="checkbox" name="sms" value="1" id="search_type"> <label for="search_type"><?= lang("app.Uses");?></label>
				<div id="search_student_dv">
					<select class="form-control select3" name="search_student" id="search_student">
					</select>
				</div>
				<div id="search_class_dv" style="display: none !important;">
					<select class="form-control select2" id="search_class">
						<option selected disabled><?= lang("app.selectClass");?></option>
						<?php
						foreach ($classes as $class) {
							echo "<option value='{$class['id']}'>{$class['level_name']} {$class['title']} {$class['code']} </option>";
						}
						?>
					</select>
				</div>
			</div>
		</div>
		<div style="margin-top: 15px;width: 100%;float:left;">
			<form action="<?= base_url('manipulate_multiple_fees'); ?>" class="autoSubmit validate" method="POST">
				<div class="col-md-6 col-sm-12 pull-left" style="margin-bottom: 15px">
					<div style="background:white;padding: 10px;max-height: 500px;overflow: auto;">
						<table class="table table-hover table-fixed">
							<!--Table head-->
							<thead>
							<tr>
								<th><?= lang("app.regNo");?>.</th>
								<th><?= lang("app.studentName");?></th>
								<th><?= lang("app.sClass");?></th>
								<th style="align-content: center;"><?= lang("app.remove");?></th>
							</tr>
							</thead>
							<!--Table head-->
							<!--Table body-->
							<tbody id="disciplineTable">

							</tbody>
							<!--Table body-->
						</table>

						<!--Table-->
					</div>
				</div>
				<div class="col-md-5 col-sm-12 pull-left">
					<div style="background:white;padding: 10px">
						<div class="row" style="margin-top: 15px;">
							<div class="col-md-3 pull-left">
								<label><?= lang("app.title");?></label>
							</div>
							<div class="col-md-9 pull-left">
								<input type="text" name="title" required placeholder="enter extra fees title" class="form-control">
							</div>
						</div>
						<div class="row" style="margin-top: 15px;">
							<div class="col-md-3 pull-left">
								<label><?= lang("app.selectTerm");?> </label>
							</div>
							<div class="col-md-9 pull-left">
								<select class="form-control select2"  required name="term">
									<option value="1"><?= lang("app.term1"); ?></option>
									<option value="2"><?= lang("app.term2"); ?></option>
									<option value="3"><?= lang("app.term3"); ?></option>
								</select>
							</div>
						</div>
						<div class="row" style="margin-top: 15px;">
							<div class="col-md-3 pull-left">
								<label><?= lang("app.amount");?></label>
							</div>
							<div class="col-md-9 pull-left">
								<input type="number" id="btn-global-amount" name="amount" required placeholder="Change extra amount for all" class="form-control">
							</div>
							<p class="text text-muted">Any changes make on this amount it will update all amount field</p>
						</div>

						<div class="row" style="margin-top: 15px;">
							<div class="col-md-3 pull-left">
								<label><?= lang("app.password");?></label>
							</div>
							<div class="col-md-9 pull-left">
								<input type="password" name="password" required placeholder="Enter password to confirm action" autocomplete="off" class="form-control"  readonly
									   onfocus="this.removeAttribute('readonly');">
							</div>
							<p class="text text-muted">Please before confirmation make sure that everything is fine because to revert it you must loop on every student</p>
						</div>


						<div class="row" style="margin-top: 20px;">
							<div class="col-md-12 pull-left">

								<center>
									<button type="submit" class="btn btn-success btn-lg"
											style="width: 50%;font-size: 14px;"
											data-target="reload"><i
											class="fa fa-check"></i>
										<?= lang("app.save");?>
									</button>
								</center>
							</div>
						</div>
					</div>
				</div>
			</form>
		</div>
	</div>
</div>
<script>
	$(function () {
		$("#choose_disc_type").on("change", function () {
			var value = $(this).val();
			if (value == 0) {
				$("#send_sms").hide();
				$("#reduce_marks").hide();
			} else {
				$("#send_sms").show();
				$("#reduce_marks").show();
			}
		});
		$("#btn-global-amount").on('keyup',function () {
			$(".txt-fees-inputs").val($(this).val())
		});
		$("#search_type").prop("checked",false);//reset checkbox
		$("#search_type").on("change", function () {
			//check if table has unsaved data then notify before clear
			if ($("#disciplineTable").has(".disc_row").length) {
				if (!confirm("Remember, while changing option or current work will be cleared")) {
					var check_status = $("#search_type").is(":checked") ? true : false;
					$("#search_type").prop("checked", !check_status);
					return false;
				}
				$("#disciplineTable").html("");
			}
			$("#search_student_dv").toggle();
			$("#search_class_dv").toggle();

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
				placeholder: "<?= lang("app.searchBy");?>",
				minimumInputLength: 3
			});
		});
		$("#disciplineTable").on('click', '#removerow', function () {
			$(this).closest('tr').remove();
		});
		$("#search_student").on('select2:select', function (selection) {
			formatRepoSelection(selection.params.data);
		});
		$("#search_class").on('select2:select', function (selection) {
			formatRepoSelection(selection.params.data, true);
		});
	});

	function formatRepoSelection(repo, isClass = false) {
		var id = repo.id;
		var isError = false;
		var cl = "/0";
		if (isClass) {
			cl = "/1"
		} else {
			$("#search_student").val(null).trigger('change');

			$('input[name^="discId"]').each(function () {
				if (this.value == id) {
					//student already exists
					toastada.warning(repo.text + " <?= lang("app.alreadonList");?>");
					isError = true;
					return false;
				}
			});
		}
		if (isError)
			return;
		$.get("<?=base_url();?>get_student/" + id + cl+"/10", function (data) {
			if (isClass) {
				$("#disciplineTable").html(data);
			} else {
				$("#disciplineTable").append(data);
			}
		})
	}
</script>
