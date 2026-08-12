<link rel="stylesheet" href="<?= base_url('assets/css/extra-fees.css'); ?>">
<div class="app-inner-layout app-inner-layout-page">
	<div class="app-inner-layout__wrapper" style="display: block;padding-left: 20px">
		<style>
			.vl {
				border-left: 3px solid #3ac47d;
			}
			.mef-mode-badge {
				display: inline-block;
				padding: .2rem .55rem;
				border-radius: 999px;
				font-size: .75rem;
				font-weight: 600;
				white-space: nowrap;
			}
			.mef-mode-boarding { background: #e0e7ff; color: #3730a3; }
			.mef-mode-day { background: #ecfdf5; color: #047857; }
			.mef-amount-grid .form-control { margin-bottom: 6px; }
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
							<thead>
							<tr>
								<th><?= lang("app.regNo");?>.</th>
								<th><?= lang("app.studentName");?></th>
								<th><?= lang("app.sClass");?></th>
								<th><?= lang("app.studyingMode");?></th>
								<th><?= lang("app.amount");?></th>
								<th style="align-content: center;"><?= lang("app.remove");?></th>
							</tr>
							</thead>
							<tbody id="disciplineTable">

							</tbody>
						</table>
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
								<label><?= lang("app.selectTerms"); ?> </label>
							</div>
							<div class="col-md-9 pull-left">
								<div class="mef-term-picker">
									<label class="mef-term-all">
										<input type="checkbox" id="mefAllTerms">
										<span>All terms (same amount)</span>
									</label>
									<div class="mef-term-chips" id="mefTermChips">
										<label class="mef-term-chip t1">
											<input type="checkbox" name="term[]" value="1" class="mef-term-cb">
											<span><?= lang("app.term1"); ?></span>
										</label>
										<label class="mef-term-chip t2">
											<input type="checkbox" name="term[]" value="2" class="mef-term-cb">
											<span><?= lang("app.term2"); ?></span>
										</label>
										<label class="mef-term-chip t3">
											<input type="checkbox" name="term[]" value="3" class="mef-term-cb">
											<span><?= lang("app.term3"); ?></span>
										</label>
									</div>
									<small class="text-muted d-block mt-1">Select one or more terms — a fee record is created per term for each student.</small>
								</div>
							</div>
						</div>
						<div class="row" style="margin-top: 15px;">
							<div class="col-md-3 pull-left">
								<label><?= lang("app.amount");?></label>
							</div>
							<div class="col-md-9 pull-left mef-amount-grid">
								<label class="small font-weight-bold mb-0"><?= lang("app.boarding"); ?> students</label>
								<input type="number" id="btn-boarding-amount" min="0" step="1" placeholder="Amount for boarding students" class="form-control">
								<label class="small font-weight-bold mb-0 mt-2"><?= lang("app.day"); ?> students</label>
								<input type="number" id="btn-day-amount" min="0" step="1" placeholder="Amount for day students" class="form-control">
								<p class="text text-muted small mb-0 mt-1">Changing these updates matching students in the list. You can still edit any row amount.</p>
							</div>
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

		function mefApplyModeAmounts() {
			var boarding = $("#btn-boarding-amount").val();
			var day = $("#btn-day-amount").val();
			if (boarding !== '') {
				$(".txt-fees-inputs[data-mode='0']").val(boarding);
			}
			if (day !== '') {
				$(".txt-fees-inputs[data-mode='1']").val(day);
			}
		}

		$("#btn-boarding-amount").on('keyup change', function () {
			$(".txt-fees-inputs[data-mode='0']").val($(this).val());
		});
		$("#btn-day-amount").on('keyup change', function () {
			$(".txt-fees-inputs[data-mode='1']").val($(this).val());
		});

		function mefSyncTermChips() {
			$('.mef-term-chip').each(function () {
				const on = $(this).find('.mef-term-cb').is(':checked');
				$(this).toggleClass('active', on);
			});
			const allOn = $('.mef-term-cb').length === $('.mef-term-cb:checked').length;
			$('#mefAllTerms').prop('checked', allOn);
		}

		$('#mefAllTerms').on('change', function () {
			$('.mef-term-cb').prop('checked', $(this).is(':checked'));
			mefSyncTermChips();
		});

		$(document).on('change', '.mef-term-cb', mefSyncTermChips);

		$('form.autoSubmit.validate').on('submit', function (e) {
			if ($('.mef-term-cb:checked').length === 0) {
				e.preventDefault();
				e.stopImmediatePropagation();
				toastada.error('Please select at least one term.');
				return false;
			}
			if ($("#disciplineTable .disc_row").length === 0) {
				e.preventDefault();
				e.stopImmediatePropagation();
				toastada.error('Please add at least one student.');
				return false;
			}
			var missing = false;
			$(".txt-fees-inputs").each(function () {
				if ($(this).val() === '' || Number($(this).val()) < 0) {
					missing = true;
					return false;
				}
			});
			if (missing) {
				e.preventDefault();
				e.stopImmediatePropagation();
				toastada.error('Enter boarding and day amounts (or fill each student amount).');
				return false;
			}
		});

		mefSyncTermChips();

		$("#search_type").prop("checked",false);
		$("#search_type").on("change", function () {
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
							searchTerm: params.term
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

		window.mefApplyModeAmounts = mefApplyModeAmounts;
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
			if (typeof window.mefApplyModeAmounts === 'function') {
				window.mefApplyModeAmounts();
			}
		})
	}
</script>
