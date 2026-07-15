<div class="app-inner-layout app-inner-layout-page">
	<div class="app-inner-layout__wrapper" style="display: block;padding-left: 20px">
		<style>
			.vl {
				border-left: 3px solid #3ac47d;
			}
		</style>
		<div class="pull-left" style="width: 100%">
			<div class="col-md-6 col-sm-12 col-lg-4 pull-left">
				<div id="search_class_dv" >
					<select class="form-control select2" id="search_class">
						<option selected disabled><?= lang("app.selectClass");?> </option>
						<?php
						foreach ($classes as $class) {
							echo "<option value='{$class['id']}'>{$class['level_name']} {$class['title']} {$class['code']} </option>";
						}
						?>
					</select>
				</div>
			</div>
		</div>




		<div style="margin-top: 15px;width: 100%;float:left;" id="printable">
			<div class="col-md-12 col-sm-12 pull-left" style="margin-bottom: 15px">
				<div style="background:white;padding: 10px;overflow: auto;">
					<table class="table table-hover table-fixed" >
						<!--Table head-->
						<thead>

						<tr>
							<td colspan="4" >
								<h3 align="center"><?=$subtitle;?></h3>
								<div class="col-md-6 col-sm-12 pull-left"  style="margin-top: 10px";>
									<div>
										<span ><b><?= lang("app.sClass");?> </b> : <label id="class_text"></label></span><br>
									</div>
								</div>
								<div class="col-md-6 col-sm-12 pull-left"  style="margin-top: 10px";>
									<div class="pull-right">
										<span><b><?= lang("app.term");?> </b> : <?=\App\Controllers\Home::TermToStr($active_term);?></span><br>
										<span><b><?= lang("app.academicYear");?> </b> :<?=date("Y");?></span>
									</div>
								</div>
							</td>
						</tr>
						<tr>
							<th># </th>
							<th><?= lang("app.regNo");?> </th>
							<th><?= lang("app.studentName");?> </th>
							<th></th>
						</tr>
						</thead>
						<!--Table head-->
						<!--Table body-->
						<tbody id="disciplineTable">

						</tbody>
						<!--Table body-->
					</table>
					<!--Table-->
					<div class="col-md-12 col-sm-12 ">
						<footer class="pull-right" style="color: darkgrey"><?= lang("app.printedBy");?> </footer>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
<script>
	$(function () {
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
				placeholder: <?= lang("app.searchBy");?> ,
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
		var cl = "";
		var type ="/6";
		if (isClass) {
			cl = "/1"
		} else {
			$("#search_student").val(null).trigger('change');

			$('input[name^="discId"]').each(function () {
				if (this.value == id) {
					//student already exists
					toastada.warning(repo.text + <?= lang("app.alreadonList");?>);
					isError = true;
					return false;
				}
			});
		}
		if (isError)
			return;
		$.get("<?=base_url();?>get_student/" + id + cl +type, function (data) {
			if (isClass) {
				$("#disciplineTable").html(data);
			} else {
				$("#disciplineTable").append(data);
			}
		})
	}
	$(function () {
		$(".viewreport").on("click",function (e) {
			// var id=e.data('id');
			alert("mmm");
		})
	});
</script>





