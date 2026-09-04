<div class="app-inner-layout app-inner-layout-page">
	<div class="app-inner-layout__wrapper" style="display: block;padding-left: 20px">
		<div class="pull-left mb-2" style="width: 100%">
			<a href="<?= base_url('school_settings'); ?>#collapseOne2" class="btn btn-outline-primary btn-sm float-right">
				<i class="fa fa-paint-brush"></i> Customize design
			</a>
			<small class="text-muted d-block mb-2">Portrait visitor pass · student name + registration number (no photo)</small>
		</div>
		<div class="pull-left" style="width: 100%">
			<div class="col-md-6 col-sm-12 col-lg-4 pull-left">
				<input type="checkbox" name="search_type" value="1" id="pv_search_type">
				<label for="pv_search_type"><?= lang('app.Uses'); ?></label>
				<div id="pv_search_student_dv">
					<select class="form-control select3" name="search_student" id="pv_search_student"></select>
				</div>
				<div id="pv_search_class_dv" style="display: none !important;">
					<select class="form-control select2" id="pv_search_class">
						<option selected disabled><?= lang('app.selectClass'); ?></option>
						<?php foreach ($classes as $class): ?>
							<option value="<?= (int) $class['id']; ?>">
								<?= esc(trim(($class['level_name'] ?? '') . ' ' . ($class['title'] ?? '') . ' ' . ($class['code'] ?? $class['dept_code'] ?? ''))); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
		</div>
		<div style="margin-top: 15px;width: 100%;float:left;">
			<form action="<?= base_url('generate_visitor_cards'); ?>" method="POST" target="_blank" id="pvPrintForm">
				<input type="hidden" name="class_id" id="pv_class_id" value="">
				<div id="pvPrintIds" aria-hidden="true"></div>
				<div class="col-md-6 col-sm-12 pull-left" style="margin-bottom: 15px">
					<div style="background:white;padding: 10px;max-height: 500px;overflow: auto;">
						<table class="table table-hover table-fixed" id="pvPrintTable">
							<thead>
							<tr>
								<th>Visitor</th>
								<th>Relationship</th>
								<th><?= lang('app.studentName'); ?></th>
								<th><?= lang('app.sClass'); ?></th>
								<th>Code (reg no)</th>
								<th>Print</th>
								<th style="align-content: center;"><?= lang('app.remove'); ?></th>
							</tr>
							</thead>
							<tbody>
							<tr><td colspan="7" class="text-center text-muted py-4">Search a student or select a class to load visitors.</td></tr>
							</tbody>
						</table>
						<label>
							<strong><?= lang('app.legend'); ?>: </strong>
							Each printed pass shows the visited student name and registration number.
							Assign RFID on
							<a href="<?= base_url('parent_visiting/assign'); ?>">Assign visitors</a> for gate access.
						</label>
					</div>
				</div>
				<div class="col-md-5 col-sm-12 pull-left">
					<div style="background:white;padding: 10px">
						<div class="row" style="margin-top: 20px;">
							<div class="col-md-12 pull-left">
								<center>
									<button type="button" id="pvPrintBtn" class="mb-2 mr-2 btn btn-dark"
											style="width: 50%;font-size: 14px;" disabled>
										Generate PDF <span class="badge badge-pill badge-light">0</span>
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
	var pvSelectedClassId = null;

	$("#pv_search_type").prop("checked", false);
	$("#pv_search_class").select2({ width: "100%" });

	$("#pv_search_type").on("change", function () {
		if ($("#pvPrintTable tbody").find(".pv-visitor-row").length) {
			if (!confirm("Remember, while changing option your current list will be cleared")) {
				$("#pv_search_type").prop("checked", !$("#pv_search_type").is(":checked"));
				return false;
			}
			$("#pvPrintTable tbody").html("");
			pvSelectedClassId = null;
			$("#pv_class_id").val("");
			countPrintable();
		}
		$("#pv_search_student_dv").toggle();
		$("#pv_search_class_dv").toggle();
	});

	$("#pv_search_student").select2({
		ajax: {
			url: "<?= base_url('search_student'); ?>",
			type: "post",
			dataType: "json",
			delay: 250,
			data: function (params) {
				return { searchTerm: params.term };
			},
			processResults: function (response) {
				return { results: response };
			},
			cache: true
		},
		placeholder: "<?= lang('app.searchBy'); ?>",
		minimumInputLength: 3
	});

	$(document).on("click", "#removerow", function () {
		$(this).closest("tr").remove();
		countPrintable();
	});

	$("#pv_search_student").on("select2:select", function (selection) {
		loadVisitors(selection.params.data, false);
	});
	$("#pv_search_class").on("select2:select", function (selection) {
		loadVisitors(selection.params.data, true);
	});
	$("#pv_search_class").on("change", function () {
		var val = $(this).val();
		if (val) {
			pvSelectedClassId = String(val);
			$("#pv_class_id").val(pvSelectedClassId);
		}
	});

	function loadVisitors(repo, isClass) {
		var id = repo.id;
		var isError = false;
		var cl = isClass ? "/1" : "/0";
		if (isClass) {
			pvSelectedClassId = String(id);
			$("#pv_class_id").val(pvSelectedClassId);
		}
		if (!isClass) {
			$("#pv_search_student").val(null).trigger("change");
			$(".pv-visitor-row").each(function () {
				if ($(this).data("student-id") == id) {
					toastada.warning(repo.text + " — visitors already loaded for this student.");
					isError = true;
					return false;
				}
			});
		}
		if (isError) return;

		if (isClass) {
			$("#pvPrintTable tbody").html('<tr><td colspan="7" class="text-center py-4"><i class="fa fa-spinner fa-spin"></i> Loading…</td></tr>');
		}

		$.get("<?= base_url('get_visitors'); ?>/" + id + cl + "/1", function (html) {
			if (isClass) {
				$("#pvPrintTable tbody").html(html);
			} else {
				if (!$("#pvPrintTable tbody .pv-visitor-row").length) {
					$("#pvPrintTable tbody").html("");
				}
				$("#pvPrintTable tbody").append(html);
			}
			countPrintable();
		});
	}

	function uniqueStudentIds() {
		var seen = {};
		var ids = [];
		$("#pvPrintTable tbody tr.pv-visitor-row").each(function () {
			var sid = String($(this).attr("data-student-id") || "");
			if (!sid || sid === "0" || seen[sid]) {
				return;
			}
			seen[sid] = true;
			ids.push(sid);
		});
		return ids;
	}

	function countPrintable() {
		var n = uniqueStudentIds().length;
		$("#pvPrintBtn").prop("disabled", n === 0);
		$("#pvPrintBtn span").text(n);
	}

	$("#pvPrintBtn").on("click", function () {
		var baseUrl = "<?= base_url('generate_visitor_cards'); ?>";
		var isClassMode = $("#pv_search_type").is(":checked");

		if (isClassMode) {
			var classId = $("#pv_class_id").val() || pvSelectedClassId || ($("#pv_search_class").val() || "");
			if (!classId) {
				toastada.warning("Select a class first.");
				return;
			}
			window.open(baseUrl + "?class_id=" + encodeURIComponent(String(classId)), "_blank");
			return;
		}

		var studentIds = uniqueStudentIds();
		if (!studentIds.length) {
			toastada.warning("No students to print.");
			return;
		}
		window.open(baseUrl + "?student_ids=" + encodeURIComponent(studentIds.join(",")), "_blank");
	});
});
</script>
