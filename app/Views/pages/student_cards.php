<div class="app-inner-layout app-inner-layout-page">
	<div class="app-inner-layout__wrapper" style="display: block;padding-left: 20px">
		<?php
		$kpis = (isset($card_kpis) && is_array($card_kpis)) ? $card_kpis : [];
		$kpi = static function (string $key, $fallback = 0) use ($kpis) {
			return isset($kpis[$key]) ? $kpis[$key] : $fallback;
		};
		?>
		<style>
			.vl {
				border-left: 3px solid #3ac47d;
			}
			.card-gen-filters .form-control {
				min-height: 38px;
			}
			.card-kpi-wrap {
				width: 100%;
				float: left;
				background: #fff;
				border: 1px solid #e9ecef;
				border-radius: 10px;
				padding: 14px 16px 12px;
				margin: 0 0 14px;
				box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
			}
			.card-kpi-head {
				display: flex;
				flex-wrap: wrap;
				align-items: baseline;
				justify-content: space-between;
				gap: 6px 16px;
				margin-bottom: 10px;
			}
			.card-kpi-head strong {
				font-size: 15px;
				color: #1f2a44;
			}
			.card-kpi-grid {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
				gap: 10px;
			}
			.card-kpi-tile {
				border: 1px solid #edf1f5;
				border-radius: 8px;
				padding: 10px 12px 8px;
				background: #fbfcfe;
				cursor: pointer;
				min-height: 84px;
				width: 100%;
				text-align: left;
				appearance: none;
				-webkit-appearance: none;
				transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease;
			}
			.card-kpi-tile:hover,
			.card-kpi-tile.is-active {
				border-color: #3f6ad8;
				box-shadow: 0 0 0 2px rgba(63, 106, 216, 0.12);
				transform: translateY(-1px);
			}
			.card-kpi-tile .kpi-label {
				font-size: 11px;
				text-transform: uppercase;
				letter-spacing: .04em;
				color: #6c757d;
				margin-bottom: 4px;
			}
			.card-kpi-tile .kpi-value {
				font-size: 26px;
				font-weight: 700;
				line-height: 1.1;
				color: #1f2a44;
			}
			.card-kpi-tile .kpi-sub {
				font-size: 12px;
				color: #6c757d;
				margin-top: 4px;
			}
			.card-kpi-tile.is-green .kpi-value { color: #1e7e34; }
			.card-kpi-tile.is-red .kpi-value { color: #c82333; }
			.card-kpi-tile.is-blue .kpi-value { color: #1a56c4; }
			.card-kpi-tile.is-slate .kpi-value { color: #495057; }
			.card-kpi-tile.is-dark .kpi-value { color: #111827; }
			.card-kpi-bars {
				display: grid;
				grid-template-columns: 1fr 1fr;
				gap: 10px 18px;
				margin-top: 12px;
			}
			@media (max-width: 767px) {
				.card-kpi-bars { grid-template-columns: 1fr; }
			}
			.card-kpi-bar label {
				display: flex;
				justify-content: space-between;
				font-size: 12px;
				color: #495057;
				margin-bottom: 4px;
			}
			.card-kpi-bar .bar {
				height: 8px;
				background: #eef2f6;
				border-radius: 99px;
				overflow: hidden;
			}
			.card-kpi-bar .bar > span {
				display: block;
				height: 100%;
				border-radius: 99px;
			}
			.card-kpi-bar .bar-photo { background: #28a745; }
			.card-kpi-bar .bar-uid { background: #3f6ad8; }
		</style>
		<div class="pull-left mb-2" style="width: 100%">
			<small class="text-muted">Wisdom High School template · photo + name, class, academic year, ID no · settings design ignored</small>
		</div>
		<div class="card-kpi-wrap">
			<div class="card-kpi-head">
				<strong><?= lang("app.cardKpiTitle"); ?></strong>
				<small class="text-muted"><?= lang("app.cardKpiHint"); ?></small>
			</div>
			<div class="card-kpi-grid">
				<button type="button" class="card-kpi-tile" data-kpi="total" title="<?= lang("app.cardKpiHint"); ?>">
					<div class="kpi-label"><?= lang("app.cardKpiTotal"); ?></div>
					<div class="kpi-value"><?= (int) $kpi('total'); ?></div>
					<div class="kpi-sub"><?= (int) $kpi('day'); ?> <?= lang("app.cardKpiDay"); ?> · <?= (int) $kpi('boarding'); ?> <?= lang("app.cardKpiBoarding"); ?></div>
				</button>
				<button type="button" class="card-kpi-tile is-green" data-kpi="photo" data-photo="1">
					<div class="kpi-label"><?= lang("app.cardKpiWithPhoto"); ?></div>
					<div class="kpi-value"><?= (int) $kpi('with_photo'); ?></div>
					<div class="kpi-sub"><?= (int) $kpi('photo_pct'); ?>%</div>
				</button>
				<button type="button" class="card-kpi-tile is-red" data-kpi="nophoto" data-photo="0">
					<div class="kpi-label"><?= lang("app.cardKpiNoPhoto"); ?></div>
					<div class="kpi-value"><?= (int) $kpi('without_photo'); ?></div>
					<div class="kpi-sub"><?= lang("app.notBePrinted"); ?></div>
				</button>
				<button type="button" class="card-kpi-tile is-blue" data-kpi="uid" data-uid="1">
					<div class="kpi-label"><?= lang("app.cardKpiWithUid"); ?></div>
					<div class="kpi-value"><?= (int) $kpi('with_uid'); ?></div>
					<div class="kpi-sub"><?= (int) $kpi('uid_pct'); ?>%</div>
				</button>
				<button type="button" class="card-kpi-tile is-slate" data-kpi="nouid" data-uid="0">
					<div class="kpi-label"><?= lang("app.cardKpiNoUid"); ?></div>
					<div class="kpi-value"><?= (int) $kpi('without_uid'); ?></div>
					<div class="kpi-sub"><?= lang("app.noCardUid"); ?></div>
				</button>
				<button type="button" class="card-kpi-tile is-dark" data-kpi="ready" data-photo="1">
					<div class="kpi-label"><?= lang("app.cardKpiPrintReady"); ?></div>
					<div class="kpi-value"><?= (int) $kpi('print_ready'); ?></div>
					<div class="kpi-sub"><?= lang("app.cardKpiWithPhoto"); ?></div>
				</button>
			</div>
			<div class="card-kpi-bars">
				<div class="card-kpi-bar">
					<label>
						<span><?= lang("app.cardKpiPhotoCoverage"); ?></span>
						<span><?= (int) $kpi('photo_pct'); ?>%</span>
					</label>
					<div class="bar"><span class="bar-photo" style="width: <?= (int) $kpi('photo_pct'); ?>%"></span></div>
				</div>
				<div class="card-kpi-bar">
					<label>
						<span><?= lang("app.cardKpiUidCoverage"); ?></span>
						<span><?= (int) $kpi('uid_pct'); ?>%</span>
					</label>
					<div class="bar"><span class="bar-uid" style="width: <?= (int) $kpi('uid_pct'); ?>%"></span></div>
				</div>
			</div>
		</div>
		<div class="pull-left card-gen-filters" style="width: 100%">
			<div class="col-md-3 col-sm-12 pull-left mb-2">
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
			<div class="col-md-2 col-sm-12 pull-left mb-2">
				<label for="filter_studying_mode"><?= lang("app.studyingMode"); ?></label>
				<select class="form-control" id="filter_studying_mode">
					<option value=""><?= lang("app.allModes"); ?></option>
					<option value="1"><?= lang("app.dayScholar"); ?></option>
					<option value="0"><?= lang("app.boarding"); ?></option>
				</select>
			</div>
			<div class="col-md-2 col-sm-12 pull-left mb-2">
				<label for="filter_card_uid"><?= lang("app.cardUidFilter"); ?></label>
				<select class="form-control" id="filter_card_uid">
					<option value=""><?= lang("app.allCardUids"); ?></option>
					<option value="1"><?= lang("app.hasCardUid"); ?></option>
					<option value="0"><?= lang("app.noCardUid"); ?></option>
				</select>
			</div>
			<div class="col-md-2 col-sm-12 pull-left mb-2">
				<label for="filter_has_photo"><?= lang("app.photoFilter"); ?></label>
				<select class="form-control" id="filter_has_photo">
					<option value=""><?= lang("app.allPhotos"); ?></option>
					<option value="1"><?= lang("app.withPhoto"); ?></option>
					<option value="0"><?= lang("app.withoutPhoto"); ?></option>
				</select>
			</div>
			<div class="col-md-3 col-sm-12 pull-left mb-2" style="padding-top: 24px;">
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
		$("#filter_studying_mode, #filter_card_uid, #filter_has_photo").on("change", function () {
			highlightCardKpis();
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
		$(document).on("click", ".card-kpi-tile", function () {
			var $tile = $(this);
			var kpi = $tile.attr("data-kpi") || "";
			if (kpi === "total") {
				$("#filter_has_photo").val("");
				$("#filter_card_uid").val("");
				$("#filter_studying_mode").val("");
			} else {
				if ($tile.attr("data-photo") !== undefined) {
					$("#filter_has_photo").val(String($tile.attr("data-photo")));
				}
				if ($tile.attr("data-uid") !== undefined) {
					$("#filter_card_uid").val(String($tile.attr("data-uid")));
				}
			}
			highlightCardKpis();
			loadAllStudentsForCards();
		});
		syncExportLink();
		highlightCardKpis();

	});

	function currentStudyingMode() {
		return $("#filter_studying_mode").val() || "";
	}

	function currentCardUid() {
		return $("#filter_card_uid").val() || "";
	}

	function currentHasPhoto() {
		return $("#filter_has_photo").val() || "";
	}

	function highlightCardKpis() {
		var photo = currentHasPhoto();
		var uid = currentCardUid();
		var mode = currentStudyingMode();
		$(".card-kpi-tile").removeClass("is-active");
		if (photo === "" && uid === "" && mode === "") {
			$(".card-kpi-tile[data-kpi='total']").addClass("is-active");
			return;
		}
		if (photo === "1") {
			$(".card-kpi-tile[data-kpi='photo']").addClass("is-active");
			$(".card-kpi-tile[data-kpi='ready']").addClass("is-active");
		} else if (photo === "0") {
			$(".card-kpi-tile[data-kpi='nophoto']").addClass("is-active");
		}
		if (uid === "1") {
			$(".card-kpi-tile[data-kpi='uid']").addClass("is-active");
		} else if (uid === "0") {
			$(".card-kpi-tile[data-kpi='nouid']").addClass("is-active");
		}
	}

	function ensureCardClassMode() {
		$("#search_type").prop("checked", true);
		$("#search_student_dv").hide();
		$("#search_class_dv").attr("style", "display: block !important");
	}

	function loadAllStudentsForCards() {
		ensureCardClassMode();
		$("#search_class").val("0");
		if ($("#search_class").hasClass("select2-hidden-accessible")) {
			$("#search_class").trigger("change.select2");
		}
		syncExportLink();
		formatRepoSelection({id: "0", text: "All"}, true);
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
		var photo = currentHasPhoto();
		var qsParts = [];
		if (mode !== "") {
			qsParts.push("studying_mode=" + encodeURIComponent(mode));
		}
		if (uid !== "") {
			qsParts.push("card_uid=" + encodeURIComponent(uid));
		}
		if (photo !== "") {
			qsParts.push("has_photo=" + encodeURIComponent(photo));
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
		var seen = {};
		var printable = 0;
		$("#studentsTable tbody tr.disc_row[data-has-photo='1']").each(function () {
			var id = String($(this).attr("data-student-id") || "");
			if (id !== "" && !seen[id]) {
				seen[id] = true;
				printable++;
			}
		});
		if (printable === 0) {
			$("input[name='stId[]']").each(function () {
				var id = String($(this).val() || "");
				if (id !== "" && !seen[id]) {
					seen[id] = true;
					printable++;
				}
			});
		}
		$("#btn_generate").prop("disabled", printable === 0);
		$("#btn_generate span").text(printable);
	}
</script>
