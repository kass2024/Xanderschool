<div class="row">
<div class="col-sm-2 pull-left">
	<label><?= lang("app.chooseAcademicYear"); ?></label>
	<select class="select2" id="selectYear">
		<?php foreach ($years as $year) { ?>
			<option value="<?=$year['title'];?>"><?=$year['title'];?></option>
			<?php
		}
		?>
		?>
	</select>
</div>
</div>
<br>
<div class="row">
	<div class="mb-3 card" style="width: 100%">
		<div class="card-header-tab card-header">

			<ul class="nav" style="margin-left: 0">
				<li class="nav-item"><a data-toggle="tab" href="#tab-deliberation" data-id=""  class="nav-link active" id="deliberationtab"><?= lang("app.deliberationCriteria"); ?></a></li>
				<li class="nav-item"><a data-toggle="tab" href="#tab-decision" data-id=""  class="nav-link" id="decisiontab"><?= lang("app.manualDecision"); ?></a></li>
			</ul>
		</div>
		<div class="card-body">
			<div class="tab-content">
				<div class="tab-pane active" id="tab-deliberation" role="tabpanel">
					<div class="col-md-7 col-sm-12 pull-left" style="margin-bottom: 15px">
						<div style="background:white;padding: 10px;overflow: auto;">
							<form method="POST" action="<?=base_url('manipulate_delib_criteria');?>" class="validate autoSubmit">
							<table class="table table-hover table-fixed" >
								<!--Table head-->
								<thead>


								<tr>
									<th>#</th>
									<th><?= lang("app.sClass"); ?></th>
									<th><?= lang("app.minMarks"); ?></th>
									<th><?= lang("app.courses"); ?></th>
									<th><?= lang("app.minDiscip"); ?></th>
								</tr>
								</thead>
								<!--Table head-->
								<!--Table body-->
								<tbody id="DeliberationTable">

								</tbody>
								<!--Table body-->
							</table>
							<center>
							<div class="row">
								<div class="col-sm-12">
									<button type="submit" class="btn btn-success" style="width: 300px;" data-target="reload"><b><?= lang("app.save"); ?></b></button>
								</div>
							</div>
							</center>
							</form>
						</div>
					</div>
					<div class="col-md-5 col-sm-12 pull-left" style="margin-bottom: 15px">
						<div style="background:white;padding: 10px;overflow: auto;">
							<p><b><u><?= lang("app.description"); ?></u></b></p>
							<p><b><?= lang("app.minMarks"); ?></b> <?= lang("app.minimumAademicPercentage"); ?></p>
							<p><b><?= lang("app.courses"); ?> :</b>  <?= lang("app.numberAllowedFailed"); ?></p>
						<p><b><?= lang("app.minDiscip"); ?></b><?= lang("app.minimumDiscipline"); ?></p>
						</div>
					</div>
				</div>

				<div class="tab-pane" id="tab-decision" role="tabpanel">
					<form action="<?=base_url('manipulate_delib_manual');?>" method="POST" class="validate autoSubmit">
					<div class="col-md-12 col-sm-12 pull-left" style="margin-bottom: 15px">
						<div style="background:white;padding: 10px;overflow: auto;">
							<table class="table table-hover table-fixed" >
								<thead>
								<tr>
									<th>#</th>
									<th><?= lang("app.regNo"); ?></th>
									<th><?= lang("app.studentName"); ?></th>
									<th><?= lang("app.firstVerdict"); ?></th>
									<th><?= lang("app.secondVerdict"); ?></th>
								</tr>
								</thead>
								<tbody id="StudentVerdict">

								</tbody>
							</table>
						</div>
					</div>
					<div class="col-md-4 col-sm-4 pull-left" style="margin-bottom: 15px">
						<select class="select3">
						</select><br>
						<table class="table table-hover table-fixed" >
							<tbody id="VerdictTable">

							</tbody>
						</table>
					</div>
					<div class="col-md-2 col-sm-2 pull-left" style="margin-bottom: 15px">

					</div>
					<div class="col-md-6 col-sm-6 pull-left" style="margin-bottom: 15px">
						<label><b><?= lang("app.firstVerdict"); ?></b></label><br>
						<div style="background:white;padding: 10px;overflow: auto;">
							<table class="table table-hover table-fixed" >
								<thead>

								</thead>
								<tbody id="disciplineTable">
								<?php foreach ($firstverdicts as $verdict) { ?>
									<tr>
										<td><?=$verdict['title'];?></td>
										<td><input type="radio" class="form-control" name="first_verdict" value="<?=$verdict['id'];?>"></td>
									</tr>
									<?php
								}
								?>
								</tbody>
							</table>
							<div class="row">
								<div class="col-sm-12">
									<center><button type="submit" class="btn btn-success" style="width: 300px;" data-target="reload"><b><?= lang("app.save"); ?></b></button></center>
								</div>
							</div>
						</div>
					</div>
					</form>
				</div>

			</div>

		</div>
		<div class="d-block text-right card-footer">

		</div>
	</div>
</div>
<script>
	$(function () {
		$("#deliberationtab").on("click",function (e) {
			var year=$("#selectYear").val();
			// alert(year);
			$.get("<?=base_url();?>get_deliberation_criteria/" + year , function (data) {

				$("#DeliberationTable").html(data);
			});
		});
		$("#deliberationtab").click();//trigger loading deliberation data on page load
		$("#decisiontab").on("click",function (e) {
			var year=$("#selectYear").val();
			// alert(year);
			$.get("<?=base_url();?>get_manual_student/" + year , function (data) {

				$("#StudentVerdict").html(data);
			});
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
				placeholder: '<?= lang("app.searchBy"); ?>',
				minimumInputLength: 3
			});
		});
		$(".select3").on("change",function () {
			var id=$(this).val();
			var cl = "/0";
			var type ="/8";
			$.get("<?=base_url();?>get_student/" + id + cl +type, function (data) {

					$("#VerdictTable").append(data);
			})
		})
		$("#VerdictTable").on('click', '#removerow', function () {
			$(this).closest('tr').remove();
		});
	});
</script>
