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
<form action="<?= base_url('get_uploaded_marks/1'); ?>" method="post" class="validate" target="_blank" id="form">
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
		<div class="form-group col-sm-3 col-md-2 col-lg-2">
			<label><?= lang("app.term"); ?>:</label>
			<select class="form-control select2" id="term" name="term" required>
				<option selected disabled><?= lang("app.selectTerm"); ?> </option>
				<option value="1"><?= lang("app.term1"); ?> </option>
				<option value="2"><?= lang("app.term2"); ?> </option>
				<option value="3"><?= lang("app.term3"); ?> </option>
			</select>
		</div>
		<div class="form-group col-sm-3 col-md-2 col-lg-2">
			<label><?= lang("app.period"); ?> :</label>
			<select class="form-control select2" id="period" name="period" required>
				<option selected disabled><?= lang("app.selectPeriod"); ?> </option>
				<option value="1"><?= lang("app.period1"); ?> </option>
				<option value="2"><?= lang("app.period2"); ?> </option>
				<option value="3"><?= lang("app.period3"); ?> </option>
				<option value="4"><?= lang("app.period4"); ?> </option>
			</select>
		</div>

		<div class="form-group col-sm-3 col-md-2 col-lg-2">
			<label><?= lang("app.sClass"); ?> :</label>
			<select class="form-control select2" name="class" id="select_class" required>
				<option selected disabled><?= lang("app.chooseClass"); ?></option>
				<?php
				foreach ($classes as $class) {
					?>
					<option
						value="<?= $class['id']; ?>"> <?= $class['level_name'] . " " . $class['code'] . " " . $class['title']; ?></option>
					<?php
				} ?>
			</select>
		</div>
		<div class="form-group col-sm-3 col-md-2 col-lg-2">
			<label><?= lang("app.course"); ?> :</label>
			<select class="form-control select2" id="select_course" name="course" required>
				<option value="0" selected><?= lang("app.allCourses"); ?> </option>
			</select>
		</div>
		<br>
		<div class="form-group" style="margin-top: 5px">
			<button class="btn btn-success" id="btn_generate"><?= lang("app.generate"); ?> </button>
			<button type="submit" value="true" name="pdf" class="btn btn-primary"><?= lang("app.export"); ?> </button>
			<?php
			if (is_allowed(1, 3)) {
				?>
				<button type="submit" value="true" id="sms_publish" class="btn btn-warning"><?= lang("app.publishviaSMS"); ?> </button>
				<?php
			}
			?>
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
		$("#select_class").on("change", function (e) {
			var val = $(this).val();
			var year = $("#select_year").val();
			$("#select_course").load("<?= base_url(); ?>get_course/" + val + "/" + year + "/1");
		});
		$(document).on("click","#btn_generate",function (e) {
			e.preventDefault();
			$.post($("#form").prop("action"), $("#form").serialize(), function (data) {
				$("#report_content").html(data);
			})
		});
		$("#sms_publish").on("click", function (e) {
			e.preventDefault();
			var form = $("#form");
			if(!form.parsley().validate()){
				return;
			}
			// var btn = $(this).find("[type='submit']");
			var btn_txt = $(this).text();
			$("button").prop("disabled", true);
			var btn = $(this);
			btn.text("<?= lang("app.pleaseWait"); ?>").prop("disabled", true);
			$.post(form.prop("action")+"?publish=sms", form.serialize(), function (data) {
				btn.text(btn_txt).prop("disabled", false);
				$("button").prop("disabled", false);
				if (data.hasOwnProperty("error")) {
					toastada.error(data.error);
					// alert(data.error);
				} else if (data.hasOwnProperty("success")) {
					toastada.success(data.success);
				} else {
					toastada.error('<?= lang("app.fatalErr"); ?>');
				}
			}).fail(function () {
				//unknown error
				$("button").prop("disabled", false);
				btn.text(btn_txt).prop("disabled", false);
				toastada.error('<?= lang("app.systemErr"); ?>');
			});
		});
	})

</script>



