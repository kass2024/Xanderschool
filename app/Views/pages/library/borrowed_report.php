<div class="row" style=" background-color: white">
	<div class="col-sm-6 pull-left" >
<!--		<label>--><?//= lang("app.viewByStudent"); ?><!--</label>-->
<!--		<input type="checkbox" id="byStudent">-->
		<label>View by book and students</label>
		<input type="radio" id="byBook" name="filterOption" checked="checked">
		&nbsp;&nbsp;
		<label><?= lang("app.viewByStudent"); ?></label>
		<input type="radio" id="byStudent" name="filterOption">
		&nbsp;&nbsp;
		<label>View by staff</label>
		<input type="radio" id="byStaff" name="filterOption">
	</div>
</div>
<div class="row" style=" background-color: white">
	<div class="col-sm-2 pull-left" style="display:none" id="classeDiv">
		<label><?= lang("app.sClass"); ?></label>
		<select class="select2" name="classe" id="select_class_book">
			<option disabled selected><?= lang("app.selectClass"); ?></option>
			<?php foreach ($classes as $classe) { ?>
				<option
					value="<?= $classe['id']; ?>"><?= $classe['level_name']; ?> <?= $classe['code']; ?> <?= $classe['title']; ?></option>
				<?php
			}
			?>
			?>
		</select>
	</div>
	<div class="col-sm-2 pull-left" style="display: none" id="studentDiv">
		<label><?= lang("app.student"); ?></label>
		<select class="select2" name="select_student_book" id="student">
			<option disabled selected><?= lang("app.selectStudent"); ?></option>
		</select>
	</div>
	<div class="col-sm-2 pull-left" id="bookDiv">
		<label><?= lang("app.book"); ?></label>
		<select class="select2" name="book" id="book">
			<option disabled selected><?= lang("app.selectBook"); ?></option>
			<?php foreach ($books as $book) { ?>
				<option
					value="<?= $book['id']; ?>"><?= $book['title']; ?></option>
				<?php
			}
			?>
		</select>
	</div>
	<div class="col-sm-2 pull-left" id="staffDiv" style="display: none">
			<label>Staff</label>
			<select class="select2" name="select_student_staff" id="selected_staff_book">
				<option disabled selected>Select staff</option>
				<?php
				foreach ($staffs as $staff):
					echo "<option value='".$staff['id']."'>".$staff['names']."</option>";
				endforeach;
				?>
			</select>
	</div>
	<div class="col-sm-2 pull-left" >
		<label><?= lang("app.fromDate"); ?></label>
		<input type="date" class="form-control" name="dateFrom" id="fromdate" required>
	</div>
	<div class="col-sm-2 pull-left" >
		<label><?= lang("app.toDate"); ?></label>
		<input type="date" class="form-control" name="dateTo" id="todate" required>
	</div>
	<div class="col-sm-2 pull-left" style="margin-top: 30px;">
		<button type="submit" class="btn btn-success" id="search"><?= lang("app.go"); ?></button>
	</div>
</div>
<div class="card-body" id="reportBody" style="display: none">
	<div id="example_wrapper" class="dataTables_wrapper dt-bootstrap4">
		<div class="row" style="background-color: white">
			<div class="col-sm-12">
				<table style="width: 100%;" id="example"
					   class="table table-hover table-striped table-bordered dataTable dtr-inline"
					   role="grid" aria-describedby="example_info">
					<thead>
					<tr role="row">
						<th><?= lang("app.no"); ?></th>
						<th class="mylable"><?= lang("app.student"); ?></th>
						<th class="myClass"><?= lang("app.sClass"); ?></th>
						<th><?= lang("app.borrowedDate"); ?></th>
						<th><?= lang("app.returnDueDate"); ?></th>
						<th><?= lang("app.returnDate"); ?></th>
						<th><?= lang("app.status"); ?></th>
					</tr>
					</thead>
					<tbody id="mydata">

					</tbody>
					<tfoot>
					<tr>
						<th><?= lang("app.no"); ?></th>
						<th class="mylable"><?= lang("app.student"); ?></th>
						<th class="myClass"><?= lang("app.sClass"); ?></th>
						<th><?= lang("app.borrowedDate"); ?></th>
						<th><?= lang("app.returnDueDate"); ?></th>
						<th><?= lang("app.returnDate"); ?></th>
						<th><?= lang("app.status"); ?></th>
					</tr>
					</tfoot>
				</table>
			</div>
		</div>
	</div>
</div>


<script>
	$(function () {
		var type=1;
		$("#byStudent").on("click", function () {
			if ($("#byStudent").prop("checked") == true) {
				type=2;
				$("#classeDiv").show()
				$("#studentDiv").show()
				$("#bookDiv").hide()
				$("#staffDiv").hide()
			}
		})
		$("#byBook").on("click",function (){
			if ($("#byBook").prop("checked") == true) {
				type=1;
				$("#studentDiv").hide()
				$("#classeDiv").hide()
				$("#bookDiv").show()
				$("#staffDiv").hide()
			}
		})
		$("#byStaff").on("click",function (){
			if ($("#byStaff").prop("checked") == true) {
				type=3;
				$("#studentDiv").hide()
				$("#classeDiv").hide()
				$("#bookDiv").hide()
				$("#staffDiv").show()
			}
		})
		$("#select_class_book").on("change",function () {
			var classe=$(this).val();
			var isclass="/1";
			var type="/7";
			$.get("<?=base_url();?>get_student/" + classe + isclass + type, function (data) {
				$("[name='select_student_book']").html(data);
			});
		});
		$("#search").on("click",function () {
			let book=$("#book").val();
			let student=$("#student").val();
			let from=$("#fromdate").val();
			let to=$("#todate").val();
			let staff=$("#selected_staff_book").val();
			// alert(book);
			if(type==1) {
				$.get("<?=base_url();?>get_borrowed_report/" + "0/" + type + "/" + book + "/" + from + "/" + to, function (data) {
					$("#mydata").html(data);
				});
			}else if (type==2){
				$.get("<?=base_url();?>get_borrowed_report/" + student + "/" + type + "/"  + "0/" + from + "/" + to, function (data) {
					$("#mydata").html(data);
				});
			}else if (type==3){
				$.get("<?=base_url();?>get_borrowed_report/" + staff + "/" + type + "/"  + "0/" + from + "/" + to, function (data) {
					$("#mydata").html(data);
				});
			}

		});
	});

</script>
