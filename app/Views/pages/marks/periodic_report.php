<style>
	.form-group {
		margin-right: 5px;
	}

	th.rotate-45 {
		height: 100px;
		width: 50px;
		min-width: 40px;
		max-width: 40px;
		position: relative;
		vertical-align: bottom;
		padding: 0;
		font-size: 13px;
		line-height: 0.8;
	}

	th.rotate-45 label {
		-ms-transform: skew(45deg, 0deg) rotate(315deg);
		-moz-transform: skew(45deg, 0deg) rotate(315deg);
		-webkit-transform: skew(45deg, 0deg) rotate(315deg);
		-o-transform: skew(45deg, 0deg) rotate(315deg);
		transform: skew(45deg, 0deg) rotate(315deg);
		position: absolute;
		bottom: 65px;
		left: -65px;
		display: inline-block;
		width: 170px;
		text-align: left;
	}

	th.rotate-45 > div {
		position: relative;
		top: 0;
		left: 50px;
		height: 100%;
		-ms-transform: skew(-45deg, 0deg);
		-moz-transform: skew(-45deg, 0deg);
		-webkit-transform: skew(-45deg, 0deg);
		-o-transform: skew(-45deg, 0deg);
		transform: skew(-45deg, 0deg);
		overflow: hidden;
		border-left: 1px solid #000;
		border-right: 1px solid #000;
		border-top: 1px solid #000;
		margin-left: -2px;
	}

	th > label {
		position: absolute;
		bottom: 0;
	}

	.periodic-single-toggle {
		display: flex;
		align-items: center;
		gap: 6px;
		margin-bottom: 4px;
		font-weight: 600;
		position: static;
	}
	.periodic-single-toggle input { margin: 0; }
	.periodic-student-search { position: relative; }
	.periodic-student-search .form-control { padding-right: 28px; }
	.periodic-student-clear {
		position: absolute;
		right: 6px;
		top: 4px;
		border: 0;
		background: transparent;
		color: #64748b;
		font-size: 18px;
		line-height: 1;
	}
	.periodic-student-results {
		display: none;
		position: absolute;
		z-index: 30;
		left: 0;
		right: 0;
		max-height: 280px;
		overflow: auto;
		background: #fff;
		border: 1px solid #dbe3ef;
		border-radius: 8px;
		box-shadow: 0 12px 28px rgba(15, 23, 42, .14);
	}
	.periodic-student-results.is-open { display: block; }
	.periodic-student-item {
		display: block;
		width: 100%;
		text-align: left;
		border: 0;
		border-bottom: 1px solid #eef2f7;
		background: #fff;
		padding: 8px 10px;
	}
	.periodic-student-item:hover { background: #eff6ff; }
	.periodic-student-item strong { display: block; }
	.periodic-student-item small { color: #64748b; }

	th {
		position: relative;
		min-width: 50px;
		border: 0px;
	}

	td {
		min-width: 50px;
		padding: 8px;
		border-color: #000;
	}
</style>
<form action="<?= base_url('get_periodic_slip'); ?>" method="post" class="validate" target="_blank" id="form">
	<div class="row" style="background-color: white;height: auto;padding: 10px">
		<div class="form-group col-sm-3 col-md-2 col-lg-2">
			<label><?= lang("app.year"); ?> :</label>
			<select class="select2" id="select_year" name="year" required>
				<option disabled selected><?= lang("app.academicYear"); ?> </option>
				<?php
				foreach ($years as $year):
					echo "<option value='{$year['id']}'>{$year['title']}</option>";
				endforeach;
				?>
			</select>
		</div>
		<div class="form-group col-sm-6 col-md-3 col-lg-3">
			<label class="periodic-single-toggle">
				<input type="checkbox" id="periodicSingleStudent"> Single student
			</label>
			<div id="periodicClassWrap">
				<label><?= lang("app.sClass"); ?> :</label>
				<select class="form-control select2" name="class" id="select_class" required>
					<option selected disabled><?= lang("app.chooseClass"); ?></option>
					<?php echo view('pages/partials/class_stream_options', ['classes' => $classes]); ?>
				</select>
			</div>
			<div id="periodicStudentWrap" hidden>
				<div class="periodic-student-search">
					<input type="search" class="form-control" id="periodicStudentSearch" placeholder="Name or registration number" autocomplete="off">
					<input type="hidden" name="student_id" id="periodicStudentId" value="" disabled>
					<button type="button" class="periodic-student-clear" id="periodicStudentClear" hidden aria-label="Clear student">&times;</button>
					<div class="periodic-student-results" id="periodicStudentResults"></div>
				</div>
				<small class="text-muted" id="periodicStudentHint">Type a name or registration number.</small>
			</div>
		</div>
		<div class="form-group col-sm-3 col-md-2 col-lg-2">
			<label><?= lang("app.term"); ?>:</label>
			<select class="form-control select2" id="term" name="term" required data-active="<?= (int) ($term ?? 0); ?>">
				<option selected disabled><?= lang("app.selectTerm"); ?> </option>
				<option value="1"><?= lang("app.term1"); ?> </option>
				<option value="2"><?= lang("app.term2"); ?> </option>
				<option value="3"><?= lang("app.term3"); ?> </option>
			</select>
		</div>
		<div class="form-group col-sm-3 col-md-2 col-lg-2" id="period_wrap">
			<label id="period_label"><?= lang("app.period"); ?> :</label>
			<select class="form-control select2" id="period" name="period" required>
				<option selected disabled><?= lang("app.selectPeriod"); ?> </option>
				<option value="1" data-generic="<?= esc(lang("app.period1")); ?>"><?= lang("app.period1"); ?> </option>
				<option value="2" data-generic="<?= esc(lang("app.period2")); ?>"><?= lang("app.period2"); ?> </option>
				<option value="3" data-generic="<?= esc(lang("app.period3")); ?>"><?= lang("app.period3"); ?> </option>
				<option value="4" data-generic="<?= esc(lang("app.period4")); ?>"><?= lang("app.period4"); ?> </option>
			</select>
		</div>
		<br>
		<div class="form-group" style="margin-top: 5px">
			<button class="btn btn-success" id="btn_generate"><?= lang("app.generate"); ?> </button>
			<button type="submit" value="true" name="pdf" class="btn btn-primary"><?= lang("app.export"); ?> </button>
		</div>
	</div>

	<div class="card-body">
		<div id="example_wrapper" class="dataTables_wrapper dt-bootstrap4">
			<div class="row" id="report_content">

			</div>
		</div>
	</div>
</form>
<script>
	$(function () {
		var genericPeriodHtml = $("#period").html();
		var periodLabel = <?= json_encode(lang("app.period")); ?>;
		function rebuildPeriod($period, html) {
			var enhanced = $period.hasClass("select2-hidden-accessible");
			if (enhanced) {
				$period.select2("destroy");
			}
			$period.html(html);
			$period.prop("disabled", false).prop("required", true);
			if (enhanced) {
				$period.select2();
			}
		}
		function syncPeriod() {
			var fac = parseInt($("#select_class option:selected").data("fac"), 10);
			var nursery = fac === 19;
			var $period = $("#period");
			var $term = $("#term");
			$("#period_wrap").show();
			if (nursery) {
				$("#period_label").text(periodLabel);
				rebuildPeriod($period, <?= json_encode(
					'<option selected disabled>' . lang('app.selectPeriod') . '</option>'
					. '<option value="1">End of Month 1 Exam</option>'
					. '<option value="2">End of Month 2 Exam</option>'
					. '<option value="3">Midterm</option>'
				); ?>);
				var active = String($term.data("active") || "");
				if (active === "1" || active === "2" || active === "3") {
					$term.val(active).trigger("change");
				}
			} else {
				$("#period_label").text(periodLabel);
				rebuildPeriod($period, genericPeriodHtml);
			}
		}
		$("#select_class").on("change", function () {
			syncPeriod();
		});
		var periodicBase = "<?= base_url('get_periodic_slip'); ?>";
		var searchTimer;
		function setSingleStudentMode(on) {
			$("#periodicClassWrap").toggle(!on);
			$("#periodicStudentWrap").prop("hidden", !on);
			$("#select_class").prop("required", !on).prop("disabled", on);
			$("#periodicStudentId").prop("disabled", !on);
			if (!on) {
				clearPeriodicStudent();
			}
		}
		function clearPeriodicStudent() {
			$("#periodicStudentId").val("");
			$("#periodicStudentSearch").val("");
			$("#periodicStudentClear").prop("hidden", true);
			$("#periodicStudentHint").text("Type a name or registration number.");
			$("#periodicStudentResults").removeClass("is-open").empty();
		}
		function pickPeriodicStudent(st) {
			$("#periodicStudentId").val(st.id);
			$("#periodicStudentSearch").val(st.name + (st.regno ? " - " + st.regno : ""));
			$("#periodicStudentClear").prop("hidden", false);
			$("#periodicStudentHint").text(st.class_label ? ("This report only · " + st.class_label) : "This report only.");
			$("#periodicStudentResults").removeClass("is-open").empty();
		}
		$("#periodicSingleStudent").on("change", function () {
			setSingleStudentMode(this.checked);
		});
		$("#periodicStudentSearch").on("input", function () {
			var q = $.trim(this.value);
			$("#periodicStudentId").val("");
			$("#periodicStudentClear").prop("hidden", q === "");
			$("#periodicStudentHint").text("Type a name or registration number.");
			clearTimeout(searchTimer);
			if (q.length < 2) {
				$("#periodicStudentResults").removeClass("is-open").empty();
				return;
			}
			searchTimer = setTimeout(function () {
				$.getJSON("<?= base_url('periodic_report_student_search'); ?>", {
					q: q,
					year: $("#select_year").val() || ""
				}).done(function (res) {
					var rows = (res && res.students) || [];
					var $box = $("#periodicStudentResults").empty();
					if (!rows.length) {
						$box.append("<div class='periodic-student-item'><small>No student found.</small></div>");
					}
					rows.forEach(function (st) {
						var $btn = $("<button type='button' class='periodic-student-item'></button>");
						$btn.append($("<strong></strong>").text(st.name || "Student"));
						$btn.append($("<small></small>").text([st.regno, st.class_label].filter(Boolean).join(" · ")));
						$btn.on("click", function () { pickPeriodicStudent(st); });
						$box.append($btn);
					});
					$box.addClass("is-open");
				});
			}, 220);
		});
		$("#periodicStudentClear").on("click", clearPeriodicStudent);
		$(document).on("click", function (e) {
			if (!$(e.target).closest(".periodic-student-search").length) {
				$("#periodicStudentResults").removeClass("is-open");
			}
		});
		$("#form").on("submit", function (e) {
			var single = $("#periodicSingleStudent").is(":checked");
			var hasStudent = single && parseInt($("#periodicStudentId").val(), 10) > 0;
			if (single && !hasStudent) {
				e.preventDefault();
				$("#periodicStudentHint").text("Choose a student from the search list.");
				$("#periodicStudentSearch").trigger("focus");
				return;
			}
			$("#select_class").prop("required", !single).prop("disabled", single);
			$("#periodicStudentId").prop("disabled", !single);
			var submitter = e.originalEvent && e.originalEvent.submitter;
			var isPdf = submitter && submitter.name === "pdf";
			var studentLabel = hasStudent ? $.trim($("#periodicStudentSearch").val()) : "";
			var label = studentLabel || $.trim($("#select_class option:selected").text());
			label = label.replace(/[<>:"/\\|?*]+/g, " ").replace(/\s+/g, " ");
			$(this).attr("action", (isPdf && label) ? periodicBase + "/" + encodeURIComponent(label) : periodicBase);
		});
		// $(document).on("click","#btn_generate",function (e) {
		// 	e.preventDefault();
		// 	$.post($("#form").prop("action"), $("#form").serialize(), function (data) {
		// 		$("#report_content").html(data);
		// 	})
		// });

	})

</script>



