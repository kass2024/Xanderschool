<div class="app-inner-layout app-inner-layout-page">
	<div class="app-inner-layout__wrapper" style="display: block;padding-left: 20px">
		<style>
			.vl {
				border-left: 3px solid #3ac47d;
			}
		</style>
		<div class="pull-left" style="width: 100%">
			<div class="col-md-6 col-sm-12 col-lg-4 pull-left">
				<input type="checkbox" name="sms" value="1" id="search_type"> <label for="search_type"><?= lang("app.usePost");?></label>
				<div id="search_student_dv">
					<select class="form-control select3" name="search_student" id="search_student">
					</select>
				</div>
				<div id="search_class_dv" style="display: none !important;">
					<select class="form-control select2" id="search_class">
						<option selected disabled><?= lang("app.selectPost");?></option>
						<?php
						foreach ($posts as $post) {
							echo "<option value='{$post['id']}'>{$post['title']} </option>";
						}
						?>
					</select>
				</div>
			</div>
		</div>
		<div style="margin-top: 15px;width: 100%;float:left;">
			<form action="<?= base_url('generate_staff_cards'); ?>" class="validate" target="_blank" method="POST">

				<div class="col-md-6 col-sm-12 pull-left" style="margin-bottom: 15px">
					<div style="background:white;padding: 10px;max-height: 500px;overflow: auto;">
						<table class="table table-hover table-fixed" id="studentsTable">
							<!--Table head-->
							<thead>
							<tr>
								<th><?= lang("app.Id");?>.</th>
								<th><?= lang("app.staffName");?></th>
								<th><?= lang("app.post");?></th>
								<th><?= lang("app.photo");?></th>
								<th style="align-content: center;"><?= lang("app.remove");?></th>
							</tr>
							</thead>
							<!--Table head-->
							<!--Table body-->
							<tbody>

							</tbody>
							<!--Table body-->
						</table>
						<label><strong><?= lang("app.legend");?>: </strong><span class="badge badge-primary"
															  style="background-color: orangered !important;"> </span>
							<?= lang("app.Staffcard");?>

						</label>
						<!--Table-->
					</div>
				</div>
				<div class="col-md-5 col-sm-12 pull-left">
					<div style="background:white;padding: 10px">
						<div class="row" style="margin-top: 20px;">
							<div class="col-md-12 pull-left">
								<center>
									<button type="submit" id="btn_generate" class="mb-2 mr-2 btn btn-dark"
											style="width: 50%;font-size: 14px;" disabled
											data-target="<?= base_url('/staff-cards'); ?>">
										<?= lang("app.generateCards");?><span class="badge badge-pill badge-light">0</span>
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
		$("#search_type").prop("checked", false);//reset checkbox
		$("#search_type").on("change", function () {
			//check if table has unsaved data then notify before clear
			if ($("#studentsTable tbody").has(".disc_row").length) {
				if (!confirm("Remember, while changing option or current work will be cleared")) {
					var check_status = $("#search_type").is(":checked") ? true : false;
					$("#search_type").prop("checked", !check_status);
					return false;
				}
				$("#studentsTable tbody").html("");
				count_students();
			}
			$("#search_student_dv").toggle();
			$("#search_class_dv").toggle();

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
				placeholder: "<?= lang("app.searchBy");?>",
				minimumInputLength: 3
			});
		});
		$(document).on('click', '#removerow', function () {
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
		var type = "/1";
		if (isClass) {
			cl = "/1"
		} else {
			$("#search_student").val(null).trigger('change');

			$('input[name^="discId"]').each(function () {
				if (this.value == id) {
					//student already exists
					toastada.warning(repo.text + "<?= lang("app.alreadonList");?>");
					isError = true;
					return false;
				}
			});
		}
		if (isError)
			return;
		$.get("<?=base_url();?>get_staffs/" + id + cl + type, function (data) {
			if (isClass) {
				$("#studentsTable tbody").html(data);
			} else {
				$("#studentsTable tbody").append(data);
			}
			count_students();
		})
	}

	function count_students() {
		var images = $("[name='stId[]'").length;
		if (images==0){
			$("#btn_generate").prop("disabled",true);
		}else{
			$("#btn_generate").prop("disabled",false);
		}
		$("#btn_generate span").text(images);
	}
</script>
