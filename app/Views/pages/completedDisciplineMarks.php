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
			<form action="<?= base_url('manipulateCompletedDisciplineEntry'); ?>" class="autoSubmit validate" method="POST">
				<div class="col-md-6 col-sm-12 pull-left" style="margin-bottom: 15px">
					<div style="background:white;padding: 10px;max-height: 500px;overflow: auto;">
						<table class="table table-hover table-fixed">
							<!--Table head-->
							<thead>
							<tr>
						<span><b><?= lang("app.discMaxPoints");?> : <span
									class="badge badge-success"><?= $discipline_max; ?></span> </b> </span>
								<th><?= lang("app.regNo");?>.</th>
								<th><?= lang("app.studentName");?></th>
								<th><?= lang("app.sClass");?></th>
								<th><?= lang("app.remaining");?></th>
								<th><?= lang("app.marks");?></th>
								<th style="align-content: center;"><?= lang("app.remove");?></th>
							</tr>
							</thead>
							<!--Table head-->
							<!--Table body-->
							<tbody id="disciplineTable">

							</tbody>
							<!--Table body-->
						</table>
						<label><strong><?= lang("app.legend");?>: </strong><span class="badge badge-primary"
																				 style="background-color: orangered !important;"> </span>
							<?= lang("app.halfoftotalmax");?><br>
							<span class="badge badge-dark" style="margin-left: 70px"> </span> <?= lang("app.studenthasnormal");?>
						</label>
						<!--Table-->
					</div>
				</div>
				<div class="col-md-5 col-sm-12 pull-left">
					<div style="background:white;padding: 10px">
						<div class="row" style="margin-top: 15px;">
							<div class="col-md-3 pull-left">
								<label><?= lang("app.activeTerm");?> </label>
							</div>
							<div class="col-md-9 pull-left">
								<input type="text" class="form-control" readonly
									   value="<?= \App\Controllers\Home::TermToStr($activeTerm['term']); ?>">
								<input type="hidden" value="<?= $activeTerm['id']; ?>"
									   name="active_term">
							</div>
						</div>
						<div class="row" style="margin-top: 15px;" id="send_sms">
							<div class="col-md-3 pull-left">

							</div>
							<div class="col-md-9 pull-left">
								<input type="checkbox" name="allowDelete" value="1"> <label
									for="notify_parent">Delete Existing marks</label>
							</div>
						</div>
						<div class="row" style="margin-top: 20px;">
							<div class="col-md-12 pull-left">

								<center>
									<button type="submit" class="btn btn-success btn-lg"
											style="width: 50%;font-size: 14px;"
											data-target="<?= base_url('/completedDisciplineMarks'); ?>"><i
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
		let id = repo.id;
		let isError = false;
		let cl = "/0";
		let type="/9"
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
		$.get("<?=base_url();?>get_student/" + id + cl + type, function (data) {
			if (isClass) {
				$("#disciplineTable").html(data);
			} else {
				$("#disciplineTable").append(data);
			}
		})
	}
</script>
