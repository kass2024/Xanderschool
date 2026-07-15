<style>
	.conditionDiv {
		border: 1px gray solid;
		padding: 12px;
		border-radius: 5px;
		margin-bottom: 10px;
	}

	.zero-m {
		margin-left: 0px;
		margin-right: 0px;
	}

	.zero-p {
		padding-left: 0px;
		/*padding-right: 0px;*/
	}
</style>
<div>
	<form action="<?= base_url('manipulateDeliberationSettings'); ?>" class="autoSubmit validate">
		<div class="row">
			<div class="col-sm-2">
				<label><?= lang("app.chooseAcademicYear"); ?></label>
				<select class="select2" id="selectYear" name="academicYear">
					<option disabled selected>Select academic year</option>
					<?php foreach ($years as $year) { ?>
						<option value="<?= $year['id']; ?>"><?= $year['title']; ?></option>
						<?php
					}
					?>
					?>
				</select>
			</div>
			<div class="col-sm-2">
				<label>Types</label>
				<select class="select2" id="typeSelect" name="educationType">
					<option disabled selected>Select type</option>
					<option value="1">REB</option>
					<option value="2">RTB</option>
				</select>
			</div>
			<div class="col-sm-2">
				<div id="levelsDiv">
					<label>Levels</label>
					<select class="select2" name="levels" required id="levelSelect">
						<option disabled selected>Select level</option>
						<option value="1">Advanced Level</option>
						<option value="2">Ordinary Level</option>
						<option value="3">Primary</option>
						<option value="19">Nursery</option>
					</select>
				</div>
			</div>
			<div class="col-sm-2">
				<a href="javascript:void(0)" class="btn btn-sm btn-success" style="margin-top: 32px;" id="continueBtn">
					<b>Continue</b>
				</a>
			</div>
			<div class="col-sm-4">
				<a href="javascript:void(0)" id="createNewConditionBtn" class="btn btn-sm btn-outline-secondary"
				   style="margin-top: 32px;float: right;display: none">
					<b><i class="fa fa-plus"></i> Create new condition</b>
				</a>
			</div>
		</div>
		<br>
		<div class="row ">
			<div class="col-md-6">
				<div class="accordion resultDiv" id="accordionExample">

				</div>
			</div>
			<div class="col-md-6" style="display: none" id="createDiv">
				<div class="mb-3 card">
					<div class="card-header-tab card-header">
						<div class="col-md-3 zero-p">
							<select class="select2" name="conditionType" required>
								<option value="1">Promotion</option>
								<option value="2">Repeat</option>
								<option value="3">Second sitting</option>
								<option value="4">Dismiss</option>
								<option value="5">Reoriented</option>
							</select>
						</div>
						<div class="col-md-9 zero-p">
							<button class="btn btn-success" type="submit" data-target="reload" style="float: right"><b>Save
									settings</b></button>
						</div>
					</div>
					<div class="card-body">
						<div class="conditionDiv">
							<div style="margin-bottom: 5px"><strong>Overall percentages</strong></div>
							<div class="row zero-m">
								<div class="col-sm-6 zero-p">
									<label>Condition</label>
									<select class="select2" name="conditions[]" required>
										<option value=">">Greater than</option>
										<option value="<">Less than</option>
										<option value="==">Equal to</option>
										<option value="<=">Less than or Equal</option>
										<option value=">=">Greater than or Equal</option>
									</select>
								</div>
								<div class="col-sm-6 zero-p">
									<label>Marks</label>
									<input type="number" required name="marks[]" class="form-control"/>
									<input type="hidden" required value="0" name="cTypes[]"/>
								</div>
							</div>
						</div>
						<div class="conditionDiv">
							<div style="margin-bottom: 5px"><strong>Discipline</strong></div>
							<div class="row zero-m">
								<div class="col-sm-6 zero-p">
									<label>Condition</label>
									<select class="select2" name="conditions[]" required>
										<option value=">">Greater than</option>
										<option value="<">Less than</option>
										<option value="==">Equal to</option>
										<option value="<=">Less than or Equal</option>
										<option value=">=">Greater than or Equal</option>
									</select>
								</div>
								<div class="col-sm-6 zero-p">
									<label>Marks</label>
									<input type="number" name="marks[]" class="form-control" required/>
									<input type="hidden" required value="1" name="cTypes[]"/>
								</div>
							</div>
						</div>
						<div class="conditionDiv">
							<div style="margin-bottom: 5px"><strong>Failed courses</strong></div>
							<div class="row zero-m">
								<div class="col-sm-6 zero-p">
									<label>Category</label>
									<select class="select2" id="selectCategory">
										<?php foreach ($categories as $category): ?>
											<option value="<?= $category['id']; ?>"><?= $category['title']; ?></option>
										<?php
										endforeach;
										?>
									</select>
								</div>
								<div class="col-sm-4 zero-p">
									<label>Courses</label>
									<input type="number" class="form-control" id="numberOfCourse"/>
								</div>
								<div class="col-sm-2 zero-p">
									<a href="javascript:void(0)" id="addToListBtn" class="btn btn-sm btn-success"
									   style="margin-top: 32px;width: 100%">
										<b>Add to list</b>
									</a
								</div>
							</div>
							<div style="margin-top: 10px;width: 100%">
								<table class="table" id="listTbl">
									<tbody>
									<tr>
										<th>Category</th>
										<th>Failed courses</th>
										<th></th>
									</tr>
									</tbody>
								</table>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</form>
</div>

<script>
	$(function () {
		$("#typeSelect").on("change", function () {
			let type = $(this).val()
			if (type == 2) {
				$("#levelsDiv").hide()
				$("#levelSelect").prop("required", false)
				$("#levelSelect").val(0)
			} else {
				$("#levelsDiv").show()
			}
		})
		let row = ""
		let categoryText, category
		let selectedCat = []
		category = $("#selectCategory").val()
		categoryText = $("#selectCategory option:selected").text();
		$("#selectCategory").on("change", function () {
			category = $("#selectCategory").val()
			categoryText = $("#selectCategory option:selected").text();
		})
		$("#addToListBtn").on("click", function () {
			let courseNum = $("#numberOfCourse").val()
			let check = selectedCat.find(element => element == category)
			if (courseNum != "") {
				console.log(courseNum)
				if (check == category) {
					alert("Already Exist")
					return
				} else {
					row = '<tr><td>' + categoryText + '<input type="hidden" name="categories[]" value="' + category + '"></td><td><input type="hidden" name="coursesNums[]" value="' + courseNum + '">' + courseNum + '</td><td><button class="btn btn-danger btn-sm deleteBtn">Delete</button></td></tr>'
					$("#listTbl tbody").append(row)
					selectedCat.push(category)
				}
			}
		})
		let academic = $("#selectYear").val()
		let eduType = $("#typeSelect").val()
		let eduLevel = $("#levelSelect").val()
		$("#continueBtn").on("click", function () {
			eduLevel = $("#levelSelect").val()
			eduType = $("#typeSelect").val()
			academic = $("#selectYear").val()
			$.get("<?=base_url();?>getDeliberationSettings/" + academic + "/" + eduType + "/" + eduLevel, function (data) {
				console.log(data)
				$(".resultDiv").html(data)
				$("#createNewConditionBtn").show()
			});
			return;
		})
		$("#createNewConditionBtn").on("click", function () {
			$("#createDiv").show()
		})
		$(".deleteBtn").on("click", function () {
			$(this).parent().parent().remove()
		})
		$(document).on("click",".deleteConditionBtn",function(e){
			let tender = e.target.dataset.id
				let r = confirm("Are you sure ?");
				if (r == true) {
					$.post("<?=base_url('deleteDeliberation');?>/"+tender, function (data) {
						if (data.hasOwnProperty("error")) {
							toastada.error(data.error);
						} else if (data.hasOwnProperty("success")) {
							toastada.success(data.success);
							setTimeout(function () {
								window.location.reload();
							}, 1500);
						} else {
							toastada.error("Fatal error occurred, if the problem persist please contact system admin");
						}
					})
				}
		})
	})
</script>
