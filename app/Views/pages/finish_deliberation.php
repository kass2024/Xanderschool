<style>
	.dataTables_wrapper {
		width: 100%;
	}
	.pending-class{
		border-right: 1px solid #cdcdcd;
		padding-right: 30px;
		width: 200px;
	}
	.pending-class > li{
		list-style: none;
		padding: 6px;
		border: 1px solid #a49c9c;
		min-width: 100px;
		border-radius: 3px;
		font-size: 12pt;
		font-weight: bold;
		margin-top: 7px;
		cursor: pointer;
	}
	.pending-class > li:hover, .pending-class > li.active{
		background-color: black;
		color: white;
	}
	.summary-tag {
		border: 1px solid;
		display: inline-block;
		float: left;
		text-align: center;
		padding: 5px;
		border-radius: 5px;
		color: wheat;
		margin-left: 5px;
		background: green;
	}
	.summary-tag > span{
		border: 1px solid;
		text-align: center;
		padding: 2px;
		border-radius: 50%;
		color: green;
		margin-left: 5px;
		width: 29px;
		display: inline-block;
		background: white;
	}
</style>
<div class="row">
	<?php
	if (isset($_SESSION['success'])) {
		?>
		<div class="alert alert-success col-sm-12">
			<p class="alert-heading mb-0"><?= $_SESSION['success']; ?></p>
		</div>
		<?php
	}
	?>
	<?php
	if (isset($_SESSION['error'])) {
		?>
		<div class="alert alert-danger col-sm-12">
			<p class="alert-heading mb-0"><?= $_SESSION['error']; ?></p>
		</div>
		<?php
	}
	if (count($classes) == 0) {
		echo "<blockquote style='text-align: center;width: 62%;border-right: 4px solid #a6b094;border-left: 4px solid #a6b094;
margin: 67px auto;font-size: 15pt;'>No pending classes</blockquote>";
		return;
	}
	?>
	<ul class="pending-class">
		<span>Pending classes</span>
		<?php
		foreach ($classes as $classe) {
			?>
			<li data-id="<?= $classe['id']; ?>"> <?= $classe['level_name']; ?> <?= $classe['code']; ?> <?= $classe['title']; ?></li>
			<?php
		}
		?>
	</ul>
	<div id="deliberation-container" style="width: calc(100% - 200px);">
		<p style="text-align: center;margin-top: 20%;" class="label-info">Click on class to view lists of students and other information</p>
		<div class="content" style="display: none">
			<h4 class="class-name"></h4>
			<h4 class="new-class-name"></h4>
			<h4 class="operator-name"></h4>
			<ul class="summary" style="display: inline">

			</ul>
			<table class="table table-bordered table-striped">
				<thead>
				<tr>
					<th>#</th>
					<th><?=lang('app.name');?></th>
					<th><?=lang('app.decision');?></th>
					<th><?=lang('app.decisionType');?></th>
					<th><?=lang('app.createdAt');?></th>
					<th><?=lang('app.action');?></th>
				</tr>
				</thead>
				<tbody>

				</tbody>
			</table>
			<p><i class="fa fa-exclamation-triangle" style="color: orangered;font-size: 13pt;margin-left: 20px;">
				</i> <?=lang('app.finishDeliberationWarning');?> </p>
			<form method="post" action="<?= base_url('process_finish_deliberation'); ?>" id="deliberation-form-0" style="margin-left: 20px">
				<div class="form-group mr-2 pull-left" style="width: 200px">
					<label>Next academic year:</label>
					<input type="text" class="form-control" readonly
						   value="<?= $academic_year_title; ?>">
					<input type="hidden" name="class">
				</div>
				<div class="form-group mr-2 pull-left" style="width: 200px">
					<label>Password for confirmation:</label>
					<input type="password" id="txt-passwd-0" class="form-control" name="password"
						   placeholder="Enter your password here">
				</div>
				<div class="pull-left">
					<button type="button" class="btn btn-warning" id="btn-save-deliberation"
							style="margin-top: 30px">Finish deliberation
					</button>
				</div>
				<br />
				<label style="width: 100%">Please  make sure that active term is on new academic year, otherwise the process will fail</label>

			</form>
		</div>
	</div>
</div>
<script>
	$(function () {
		let classId
		$("#deliberation").dataTable({
			// "scrollY":        "200px",
			"scrollCollapse": true,
			"paging": false
		});
		$(".pending-class > li").on("click", function () {
			$(".pending-class > li").removeClass('active');
			classId = $(this).data('id')
			$(this).addClass('active');
			$("[name='class']").val(classId);
			fetchStudents(classId)
		});
		$(document).on("click",".btn-revoke",function () {
			if (!confirm("Confirm deliberation revoke of "+$(this).data('name'))) {
				return;
			}
			const record_id = $(this).data('id');
			$.post(base_url + "revoke_deliberation", "id=" + record_id, function (data) {
				if (data.hasOwnProperty('success') && data.success == 1) {
					toastada.success(data.message);
					fetchStudents(classId)
				} else {
					toastada.error(data.message);
				}
			}).fail(function (error) {
				toastada.error(error.responseJSON.message)
			});
		});
		$("#btn-save-deliberation").on("click", function () {
			if ($("[name='class']").val() == null) {
				toastada.warning("Invalid class selected");
				$(this).focus();
				return;
			}

			if ($("#txt-passwd-0").val().length < 6) {
				toastada.warning("Invalid password");
				$(this).focus();
				return;
			}
			if (!confirm("Confirm deliberation processing")) {
				return;
			}
			$.post(base_url + "verify_password", "password=" + $("#txt-passwd-0").val(), function (data) {
				if (data.hasOwnProperty('success') && data.success == 1) {
					$("#deliberation-form-0").submit();
				} else {
					toastada.error(data.message);
				}
			}).fail(function (error) {
				toastada.error(error.responseJSON.message)
			});
		});
	});
	function fetchStudents(id){
		$("#deliberation-container > .label-info").hide();
		$("#deliberation-container > .content").slideDown();
		$("#deliberation-container table > tbody").html("");
		$("#deliberation-container .summary").html("");
		$.getJSON(base_url+"get_deliberation_records/"+id,"",function (data) {
			const students = data.students;
			$("#deliberation-container .class-name").text("Current class: "+data.oldClass)
			$("#deliberation-container .new-class-name").text("Promotion class: "+data.newClass)
			$("#deliberation-container .operator-name").text("Done by: "+data.operator)
			$.each(students, function (index, student){
				$("#deliberation-container table > tbody").append("<tr><td>"+(index+1)+"</td>" +
					"<td>"+student.fname+' '+student.lname+"</td>" +
					"<td>"+student.decision+"</td>" +
					"<td>"+student.decisionType+"</td>" +
					"<td>"+student.created_at+"</td>" +
					"<td><button class='btn btn-sm btn-primary btn-revoke' data-student='"+student.fname+"' data-id='"+student.id+"'>Revoke</button></td></tr>"
				);
			});
			$.each(data.summaries ,function (key, summary) {
				$("#deliberation-container .summary").append("<ul class='summary-tag'>"+key+" <span>"+summary+"</span></ul>");
			});
		});
	}
</script>
