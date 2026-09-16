<div class="app-inner-layout app-inner-layout-page">
	<div class="app-inner-layout__wrapper" style="display: block;padding-left: 20px">
		<style>
			.vl {
				border-left: 3px solid #3ac47d;
			}
			.card-gen-filters .form-control {
				min-height: 38px;
			}
		</style>
		<div class="pull-left mb-2" style="width: 100%">
			<small class="text-muted">Wisdom High School template · photo + name, class, academic year, ID no · settings design ignored</small>
		</div>
		<div class="pull-left card-gen-filters" style="width: 100%">
			<div class="col-md-4 col-sm-12 pull-left mb-2">
				<input type="checkbox" name="sms" value="1" id="search_type"> <label for="search_type"><?= lang("app.Uses");?></label>
				<div id="search_student_dv">
					<select class="form-control select3" name="search_student" id="search_student">
					</select>
				</div>
				<div id="search_class_dv" style="display: none !important;">
					<select class="form-control select2" id="search_class">
						<option selected disabled><?= lang("app.selectClass");?></option>
						<option value="0"><?= lang("app.all");?></option>
						<?php
						foreach ($classes as $class) {
							echo "<option value='{$class['id']}'>{$class['level_name']} {$class['title']} {$class['code']} </option>";
						}
						?>
					</select>
				</div>
			</div>
			<div class="col-md-3 col-sm-12 pull-left mb-2">
				<label for="filter_studying_mode"><?= lang("app.studyingMode"); ?></label>
				<select class="form-control" id="filter_studying_mode">
					<option value=""><?= lang("app.allModes"); ?></option>
					<option value="1"><?= lang("app.dayScholar"); ?></option>
					<option value="0"><?= lang("app.boarding"); ?></option>
				</select>
			</div>
			<div class="col-md-3 col-sm-12 pull-left mb-2">
				<label for="filter_card_uid"><?= lang("app.cardUidFilter"); ?></label>
				<select class="form-control" id="filter_card_uid">
					<option value=""><?= lang("app.allCardUids"); ?></option>
					<option value="1"><?= lang("app.hasCardUid"); ?></option>
					<option value="0"><?= lang("app.noCardUid"); ?></option>
				</select>
			</div>
			<div class="col-md-5 col-sm-12 pull-left mb-2" style="padding-top: 24px;">
				<a href="<?= base_url('export_assigned_student_cards_excel'); ?>" id="btn_export_assigned_cards" class="btn btn-success mb-1">
					<i class="fa fa-file-excel"></i> <?= lang("app.exportAssignedCards"); ?>
				</a>
				<a href="<?= base_url('export_missing_student_cards_pdf'); ?>" id="btn_export_missing_cards_pdf" class="btn btn-danger mb-1" target="_blank">
					<i class="fa fa-file-pdf"></i> <?= lang("app.exportMissingCardUidPdf"); ?>
				</a>
			</div>
		</div>
		<div style="margin-top: 15px;width: 100%;float:left;">
			<form action="<?= base_url('generate_cards'); ?>" class="validate" target="_blank" method="POST">

				<div class="col-md-6 col-sm-12 pull-left" style="margin-bottom: 15px">
					<div style="background:white;padding: 10px;max-height: 500px;overflow: auto;">
						<table class="table table-hover table-fixed" id="studentsTable">
							<!--Table head-->
							<thead>
							<tr>
								<th><?= lang("app.regNo");?></th>
								<th><?= lang("app.studentName");?></th>
								<th><?= lang("app.sClass");?></th>
								<th><?= lang("app.studyingMode");?></th>
								<th><?= lang("app.photo");?></th>
								<th>Print</th>
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
							<?= lang("app.notBePrinted");?>

						</label>
						<!--Table-->
					</div>
				</div>
				<div class="col-md-5 col-sm-12 pull-left">
					<div style="background:white;padding: 10px">
						<p class="text-muted small mb-2">
							<?= lang("app.cardGenCriteriaHint"); ?>
						</p>
						<div class="row" style="margin-top: 20px;">
							<div class="col-md-12 pull-left">
								<center>
									<button type="submit" id="btn_generate" class="mb-2 mr-2 btn btn-dark"
											style="width: 50%;font-size: 14px;" disabled
											data-target="<?= base_url('/student-cards'); ?>">
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
		$(document).on('click', '#removerow', function () {
			$(this).closest('tr').remove();
			count_students();
		});
		$("#search_student").on('select2:select', function (selection) {
			formatRepoSelection(selection.params.data);
		});
		$("#search_class").on('select2:select', function (selection) {
			formatRepoSelection(selection.params.data, true);
		});
		$("#filter_studying_mode, #filter_card_uid").on("change", function () {
			syncExportLink();
			if ($("#search_type").is(":checked")) {
				var classId = $("#search_class").val();
				if (classId !== null && classId !== undefined && classId !== '') {
					formatRepoSelection({id: classId, text: $("#search_class option:selected").text()}, true);
				}
			}
		});
		$("#search_class").on("change", function () {
			syncExportLink();
		});
		syncExportLink();

	});

	function currentStudyingMode() {
		return $("#filter_studying_mode").val() || "";
	}

	function currentCardUid() {
		return $("#filter_card_uid").val() || "";
	}

	function cardListQueryParams(includeCardUid) {
		var params = [];
		if ($("#search_type").is(":checked")) {
			var classId = $("#search_class").val();
			if (classId !== null && classId !== undefined && classId !== '') {
				params.push("class_id=" + encodeURIComponent(classId));
			}
		}
		var mode = currentStudyingMode();
		if (mode !== "") {
			params.push("studying_mode=" + encodeURIComponent(mode));
		}
		if (includeCardUid) {
			var uid = currentCardUid();
			if (uid !== "") {
				params.push("card_uid=" + encodeURIComponent(uid));
			}
		}
		return params;
	}

	function syncExportLink() {
		var excelParams = cardListQueryParams(false);
		var pdfParams = cardListQueryParams(false);
		var excelBase = "<?= base_url('export_assigned_student_cards_excel'); ?>";
		var pdfBase = "<?= base_url('export_missing_student_cards_pdf'); ?>";
		$("#btn_export_assigned_cards").attr("href", excelParams.length ? (excelBase + "?" + excelParams.join("&")) : excelBase);
		$("#btn_export_missing_cards_pdf").attr("href", pdfParams.length ? (pdfBase + "?" + pdfParams.join("&")) : pdfBase);
	}

	function formatRepoSelection(repo, isClass = false) {
		var id = repo.id;
		var isError = false;
		var cl = "/0";
		var type = "/2";
		if (isClass) {
			cl = "/1"
		} else {
			$("#search_student").val(null).trigger('change');

			$('input[name^="discId"]').each(function () {
				if (this.value == id) {
					toastada.warning(repo.text + "<?= lang("app.alreadonList");?>");
					isError = true;
					return false;
				}
			});
			$("#studentsTable tbody tr.disc_row").each(function () {
				if (String($(this).attr("data-student-id")) === String(id)) {
					toastada.warning(repo.text + "<?= lang("app.alreadonList");?>");
					isError = true;
					return false;
				}
			});
		}
		if (isError)
			return;
		var mode = currentStudyingMode();
		var uid = currentCardUid();
		var qsParts = [];
		if (mode !== "") {
			qsParts.push("studying_mode=" + encodeURIComponent(mode));
		}
		if (uid !== "") {
			qsParts.push("card_uid=" + encodeURIComponent(uid));
		}
		var qs = qsParts.length ? ("?" + qsParts.join("&")) : "";
		$.get("<?=base_url();?>get_student/" + id + cl + type + qs, function (data) {
			if (isClass) {
				$("#studentsTable tbody").html(data);
			} else {
				$("#studentsTable tbody").append(data);
			}
			count_students();
		})
	}

	function count_students() {
		var images = $("input[name='stId[]']").length;
		if (images == 0) {
			$("#btn_generate").prop("disabled", true);
		} else {
			$("#btn_generate").prop("disabled", false);
		}
		$("#btn_generate span").text(images);
	}
</script>
