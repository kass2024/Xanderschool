<link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/sweetalert2@7.12.15/dist/sweetalert2.min.css'>
<div class="row">
	<div class="col-sm-12 pull-left" style="margin-left: 13px;">
		<input type="checkbox" id="checkSheet">
		<label><?= lang("app.uploadExcel"); ?></label>
	</div>
</div><br>
<div class="row" style="display: none;background-color: white" id="execelUpload">
	<form action="<?=base_url("download_library_template");?>" method="POST" class="validate" id="formuploadExcelBook">
		<div class="col-sm-4" style="max-width: 100%">
			<label><i class="fa fa-download"></i> <?= lang("app.downloadExcel"); ?></label>
			<button type="submit" class="btn btn-primary form-control" data-target="<?= base_url('book_management'); ?>"><i class="fa fa-download"></i><?= lang("app.download");?> </button>
		</div>
	</form>
</form>
<div class="col-sm-4" style="max-height: 500px;overflow: auto">
	<form action="<?= base_url('uploadBookExcel'); ?>" method="POST" enctype="multipart/form-data" class="validate" id="formUploadExecel">
		<label><i class="fa fa-upload"></i><?= lang("app.chooseFile"); ?></label><br>
		<input type="file" name="documents" class="btn btn-success">
		<input type="hidden" name="check_class" id="check_class_up">
		<input type="hidden" name="course_id" id="check_course_up">
		<input type="hidden" name="course_marks" id="course_marks">
</div>
<div class="col-sm-4" style="max-height: 500px; overflow: auto">
	<button type="submit" class="btn btn-success btn-lg" style="margin-top: 28px;"><i class="fa fa-check"></i> <?= lang("app.Upload"); ?></button>
	</form>
</div>
</div>
<br>
<div class="row">

<div class="col-sm-12 pull-left" style="margin-left: 13px;" id="mannualUpload">
	<button class="btn btn-success" data-toggle="modal" data-target="#createBook"><i class="fa fa-plus"></i> <?= lang("app.newBook"); ?></button>
</div>
</div>
<div class="card-body">
	<div id="example_wrapper" class="dataTables_wrapper dt-bootstrap4">
		<div class="row" style="background-color: white">
			<div class="col-sm-12">
				<table style="width: 100%;" id="example"
					   class="table table-hover table-striped table-bordered dataTable dtr-inline"
					   role="grid" aria-describedby="example_info">
					<thead>
					<tr role="row">
						<th><?= lang("app.no"); ?></th>
						<th><?= lang("app.title"); ?></th>
						<th><?= lang("app.author"); ?></th>
						<th><?= lang("app.category"); ?></th>
						<th><?= lang("app.quantity"); ?></th>
						<th><?= lang("app.borrowed"); ?></th>
						<th><?= lang("app.available"); ?></th>
						<th></th>
					</tr>
					</thead>
					<tbody>
					<?php $i=1; foreach ($books as $book) { ?>
						<tr>
							<td><?=$i;?></td>
							<td><?=$book['title'];?></td>
							<td><?=$book['author'];?></td>
							<td><?=$book['category'];?></td>
							<td><?=$book['quantity'];?></td>
							<td><?=$book['borrowed'];?></td>
							<td><?=($book['quantity']-$book['borrowed']);?></td>
							<td ><center>
									<button class="btn btn-sm btn-outline-warning" data-id="<?=$book['id'];?>" data-toggle="modal" data-target="#editBook" ><i class="fa fa-pencil-alt"  ></i></button>
									<?php if($book['borrowed']==$book['quantity']){?>
									<button style="display: none" class="btn btn-sm btn-outline-success" data-id="<?=$book['id'];?>"  data-toggle="modal" data-target="#borrowBook">Borrow</button>
									<?php
									}else{?>
										<button  class="btn btn-sm btn-outline-success" data-id="<?=$book['id'];?>"  data-toggle="modal" data-target="#borrowBook"><?= lang("app.borrow"); ?></button>
									<?php
									}
									?>
									<a href="<?=base_url('book_history/');?><?=$book['id'];?>"><button class="btn btn-sm btn-outline-secondary"><?= lang("app.viewHistory"); ?></button></a></center>
							</td>
						</tr>
						<?php
						$i++;
					}
					?>
					</tbody>
					<tfoot>
					<tr>
						<th><?= lang("app.no"); ?></th>
						<th><?= lang("app.title"); ?></th>
						<th><?= lang("app.author"); ?></th>
						<th><?= lang("app.category"); ?></th>
						<th><?= lang("app.quantity"); ?></th>
						<th><?= lang("app.borrowed"); ?></th>
						<th><?= lang("app.available"); ?></th>
						<th></th>
					</tr>
					</tfoot>
				</table>
			</div>
		</div>
	</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@7.12.15/dist/sweetalert2.all.min.js"></script>
<script>
	$(function () {
		$("#checkSheet").on("change",function () {
			if($("#checkSheet").prop("checked")!=true){
				$("#execelUpload").hide();
				$("#mannualUpload").show();
			}
			if($("#checkSheet").prop("checked")==true){
				$("#mannualUpload").hide();
				$("#execelUpload").show();
			}
		})

		});


	$(function(){
		$(document).on('submit', '#formUploadExecel', function (event) {
			event.preventDefault();
			$.ajax({
				url: "<?php echo base_url('uploadBookExcel') ?>",
				method: 'POST',
				data: new FormData(this),
				dataType: "json",
				contentType: false,
				processData: false,
				cache:false,
				async:false,
				success: function (data) {
					try {
						// json = JSON.parse(data);
						if (data.hasOwnProperty("error")) {
							notdone(data.error);
						} else {
							done(data.success);
							$('#formUploadExecel')[0].reset();
							window.location.reload();
						}
					} catch (e) {
						alert("System error please try again later");
						console.log(e);
					}
				}
			});
		});

	}) ;


	function done(value)
	{
		swal({
			title: "well done!!",
			text:value ,
			type: "success",
			closeOnConfirm:false
		});
		setTimeout(function () {
			window.location.reload();
		}, 2000);
	}
	function notdone(value)
	{
		swal({
			title: "Oops!!",
			text:value ,
			type: "error",
			closeOnConfirm:false

		});

	}

</script>
