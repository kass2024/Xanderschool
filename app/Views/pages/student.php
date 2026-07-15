<style>
	.settings span {
		font-weight: 600;
	}
	.spedit {
		min-width: 100px;
		cursor: pointer;
		display: inline-block;
	}
	.boxed{
		padding: 20px;
		border-radius: 5px;
		background: #e1e0e0;
	}
	.ihelp{
		font-size: 30pt;position: absolute;top: 10px;left:59%;color:#333333;cursor: pointer;
	}
	span{
		font-weight: 600;
	}
	@media all and (max-width: 1249px){
		.ihelp{
			left:50%;color:#ffffff;
		}
	}

</style>
<i class="fa fa-question-circle ihelp" data-toggle="tooltip" title="Double click value to enable edit mode then press enter or outside to save or escape key to cancel"></i>
<div class="row" id="student_section" data-id="<?= $student['id']; ?>">
		<hr style="width: 100%;float: left"/>
		<div class="col-sm-12 col-md-6 col-lg-6 pull-left">
			<div class="boxed" style="background:white;padding: 20px">
				<h4><?= lang("app.studentInformation");?></h4>
				<div class="form-group">
					<label><?= lang("app.firstName");?>:</label>
					<span data-value="<?= $student['fname']; ?>" data-target="fname"
						  class="spedit">&nbsp;<?= $student['fname']; ?></span>
				</div>
				<div class="form-group">
					<label><?= lang("app.lastName");?>:</label>
					<span data-value="<?= $student['lname']; ?>" data-target="lname"
						  class="spedit">&nbsp;<?= $student['lname']; ?></span>
				</div>
				<div class="form-group">
					<label><?= lang("app.sex");?>:</label>
					<span data-value="<?= $student['sex']; ?>" data-target="sex"
						  class="spedit">&nbsp;<?= $student['sex']; ?></span>
				</div>
				<div class="form-group">
					<label><?= lang("app.dob");?>:</label>
					<span data-value="<?= $student['dob']; ?>" data-target="dob"
						  class="spedit">&nbsp;<?= $student['dob']; ?></span>
				</div>
				<div class="form-group">
					<label><?= lang("app.nationality");?>:</label>
					<span data-value="<?= $student['nationality']; ?>" data-target="nationality"
						  class="spedit">&nbsp;<?= $student['nationality']; ?></span>
				</div>
				<div class="form-group">
					<label><?= lang("app.religion");?>:</label>
					<span data-value="<?= $student['religion']; ?>" data-target="religion"
						  class="spedit">&nbsp;<?= $student['religion']; ?></span>
				</div>
			</div>
		</div>
	<div class="col-sm-12 col-md-5 col-lg-5 pull-left">
		<div class="boxed">
			<h4>School information</h4>
			<div style="margin-left: 20px">
				<label><?= lang("app.RegNO");?>: <?= $student['regno']; ?></label><br/>
				<label><?= lang("app.option");?>: <?= $student['dept_title']; ?></label><br/>
				<label><?= lang("app.sClass");?>: <?= $student['level'].' '.$student['dept_code'].' '.$student['class']; ?></label><br/>
				<label><?= lang("app.studyingMode");?>: <?= \App\Controllers\Home::ModeToStr($student['studying_mode']); ?></label><br/>
			</div>
		</div>
		<div class="boxed" style="background-color: white;display: flow-root;">
			<h4><?= lang("app.studentPhoto");?></h4>
			<?php
			$photo = strlen($student['photo']) > 4 ? base_url('assets/images/profile/' . $student['photo']) : '';
			?>
			<img src="<?=$photo;?>" id="img_photo" style="width: 100px;height: 100px;border-radius: 50%;background: #FFFFFF;float:left;border: 1px solid #4C5B5C;">
			<input type="file" id="in_student_photo" style="display: none;overflow: hidden">
			<div style="border: 1px dashed #bababa;float: left;width: calc(100% - 110px);margin-left: 10px;height: 100px;cursor: pointer;text-align: center;" id="dv_select_img">
				<p style="margin: 30px 0 0;font-weight: 600;"><?= lang("app.uploadPhoto");?></p>
				<label class="text-muted" style="font-style: italic;font-size: 10pt;"><?= lang("app.sizeNeeded");?></label>
			</div>
		</div>
	</div>
</div>
<div class="row">
	<div class="mb-3 card" style="width: 100%">
		<div class="card-header-tab card-header">

			<ul class="nav" style="margin-left: 0">
				<li class="nav-item"><a data-toggle="tab" href="#tab-parent"  class="nav-link active"><?= lang("app.parents");?></a></li>
				<li class="nav-item"><a data-toggle="tab" href="#tab-class"      data-id="<?= $student['regno']; ?>"  class="nav-link" id="classtab"><?= lang("app.classRecord");?></a></li>
				<li class="nav-item"><a data-toggle="tab" href="#tab-discipline" data-id="<?= $student['regno']; ?>"  class="nav-link" id="displinetab"><?= lang("app.disciplineRecord");?></a></li>
				<li class="nav-item"><a data-toggle="tab" href="#tab-permission" data-id="<?= $student['regno']; ?>"  class="nav-link" id="permissiontab"><?= lang("app.permissionRecord");?></a></li>
				<li class="nav-item"><a data-toggle="tab" href="#libraryTab"    data-id="<?= $student['id']; ?>"     class="nav-link" id="tab-library"><?= lang("app.libraryActivity");?></a></li>
				<li class="nav-item"><a data-toggle="tab" href="#tab-sms" class="nav-link"><?= lang("app.communicationHistory");?></a></li>
			</ul>
		</div>
		<div class="card-body">
			<div class="tab-content">
				<div class="tab-pane active" id="tab-parent" role="tabpanel">
					<h4><?= lang("app.father");?></h4>
					<div class="form-group">
						<label><?= lang("app.fatherNames");?>:</label>
						<span data-value="<?= $student['father']; ?>" data-target="father"
							  class="spedit">&nbsp;<?= $student['father']; ?></span>
					</div>
					<div class="form-group">
						<label><?= lang("app.fPhoneNumber");?></label>
						<span data-value="<?= $student['ft_phone']; ?>" data-target="ft_phone"
							  class="spedit">&nbsp;<?= $student['ft_phone']; ?></span>
					</div>
					<hr>
					<h4><?= lang("app.mother");?></h4>
					<div class="form-group">
						<label><?= lang("app.motherNames");?>:</label>
						<span data-value="<?= $student['mother']; ?>" data-target="father"
							  class="spedit">&nbsp;<?= $student['father']; ?></span>
					</div>
					<div class="form-group">
						<label><?= lang("app.motherPhoneNumber");?>:</label>
						<span data-value="<?= $student['mt_phone']; ?>" data-target="mt_phone"
							  class="spedit">&nbsp;<?= $student['mt_phone']; ?></span>
					</div>
					<hr>
					<h4><?= lang("app.guardian");?></h4>
					<div class="form-group">
						<label><?= lang("app.guardiaNames");?>:</label>
						<span data-value="<?= $student['guardian']; ?>" data-target="guardian"
							  class="spedit">&nbsp;<?= $student['guardian']; ?></span>
					</div>
					<div class="form-group">
						<label><?= lang("app.guardianPhoneNumber");?>:</label>
						<span data-value="<?= $student['gd_phone']; ?>" data-target="gd_phone"
							  class="spedit">&nbsp;<?= $student['gd_phone']; ?></span>
					</div>
				</div>
				<div class="tab-pane" id="tab-class" role="tabpanel">
					<div class="col-md-4 col-sm-12 pull-left" style="margin-bottom: 15px">
						<div style="background:white;padding: 10px;overflow: auto;">
							<table class="table table-hover table-fixed" >
								<!--Table head-->
								<thead>


								<tr>
									<th>#</th>
									<th><?= lang("app.sClass");?></th>
									<th><?= lang("app.academicYear");?></th>
								</tr>
								</thead>
								<!--Table head-->
								<!--Table body-->
								<tbody id="classrecordTable">

								</tbody>
								<!--Table body-->
							</table>

							<!--Table-->

						</div>
					</div>
				</div>
				<div class="tab-pane" id="tab-discipline" role="tabpanel">
					<div class="col-md-12 col-sm-12 pull-left" style="margin-bottom: 15px">
						<div style="background:white;padding: 10px;overflow: auto;">
							<table class="table table-hover table-fixed" >
								<!--Table head-->
								<thead>


								<tr>
									<th>#</th>
									<th><?= lang("app.date");?></th>
									<th><?= lang("app.removedMarks");?></th>
									<th><?= lang("app.removedBy");?></th>
								</tr>
								</thead>
								<!--Table head-->
								<!--Table body-->
								<tbody id="disciplineTable">

								</tbody>
								<!--Table body-->
							</table>

							<!--Table-->

						</div>
					</div>
				</div>
				<div class="tab-pane" id="tab-permission" role="tabpanel">
					<div class="col-md-12 col-sm-12 pull-left" style="margin-bottom: 15px">
						<div style="background:white;padding: 10px;overflow: auto;">
							<table class="table table-hover table-fixed" >
								<!--Table head-->
								<thead>


								<tr>
									<th>#</th>
									<th><?= lang("app.date");?></th>
									<th><?= lang("app.destination");?></th>
									<th><?= lang("app.reason");?> </th>
									<th><?= lang("app.leaveTime");?></th>
									<th><?= lang("app.returnTime");?> </th>
									<th><?= lang("app.permittedby");?> </th>
								</tr>
								</thead>
								<!--Table head-->
								<!--Table body-->
								<tbody id="permissionTable">

								</tbody>
								<!--Table body-->
							</table>

							<!--Table-->

						</div>
					</div>
				</div>
				<div class="tab-pane" id="libraryTab" role="tabpanel">
					<div class="col-md-12 col-sm-12 pull-left" style="margin-bottom: 15px">
						<div style="background:white;padding: 10px;overflow: auto;">
							<table class="table table-hover table-fixed" >
								<!--Table head-->
								<thead>


								<tr>
									<th>#</th>
									<th><?= lang("app.book");?></th>
									<th><?= lang("app.author");?></th>
									<th><?= lang("app.borrowDate");?> </th>
									<th><?= lang("app.returnDueDate");?></th>
									<th><?= lang("app.returnDate");?> </th>
									<th><?= lang("app.status");?></th>
								</tr>
								</thead>
								<!--Table head-->
								<!--Table body-->
								<tbody id="libraryTable">

								</tbody>
								<!--Table body-->
							</table>

							<!--Table-->

						</div>
					</div>
				</div>
				<div class="tab-pane" id="tab-sms" role="tabpanel">
					<div class="col-md-2">
					<select class="select2">
						<option selected disabled><?= lang("app.chooseAcademicYear");?></option>
						<?php foreach ($academic as $year){?>
							<option value="<?=$year['academic_year'];?>"><?=$year['academic_year'];?></option>
						<?php
						}?>
					</select>
					</div>
					<div class="col-md-12 col-sm-12 pull-left" style="margin-bottom: 15px">
						<div style="background:white;padding: 10px;overflow: auto;">
							<table class="table table-hover table-fixed" >
								<!--Table head-->
								<thead>


								<tr>
									<th>#</th>
									<th><?= lang("app.sent");?></th>
									<th><?= lang("app.outbox");?></th>
									<th><?= lang("app.to");?> </th>
									<th><?= lang("app.date");?></th>
									<th><?= lang("app.doneBy");?> </th>
								</tr>
								</thead>
								<!--Table head-->
								<!--Table body-->
								<tbody id="permissionTable">

								</tbody>
								<!--Table body-->
							</table>

							<!--Table-->

						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="d-block text-right card-footer">
			<a href="javascript:void(0);" class="btn-wide btn-shadow btn btn-success" data-id="<?= $student['id']; ?>" data-toggle="modal" data-target="#Change_student_Mode" ><?= lang("app.changeMode")?></a>
			<a href="javascript:void(0);" class="btn-wide btn-shadow btn btn-info" data-id="<?= $student['id']; ?>"  data-toggle="modal" data-target="#Change_student_Class" ><?= lang("app.changeClass")?></a>
			<a href="javascript:void(0);" class="btn-wide btn-shadow btn btn-danger"><?= lang("app.del")?></a>
		</div>
	</div>
</div>
<script>
	$(function () {
		var sp, value, old_data, target, type = null;
		$(".spedit").on("dblclick", function () {
			sp = $(this);
			value = sp.data("value");
			old_data = sp.html();
			target = sp.data("target");
			type = sp.data("type") == undefined ? "text" : sp.data("type");
			if (type == "text")
				sp.html("<input type='text' value='" + value + "' class='sptxt'>");
			if (type == "number" || type == "digit")
				sp.html("<input type='text' data-parsley-type='number' value='" + value + "' class='sptxt'>");
			if (type == "status") {
				sp.html("<input type='checkbox' value='1' class='spchk'>");
				if (value == 1) {
					$(".spchk").prop("checked", true);
				}
			}
			if (type == "select") {
				sp.html("<select class='select2_auto' style='width:200px !important' data-value='" + value + "' data-href='" + sp.data("href") + "' class='spselect'>");
				load_select(sp.data("href"), value);
			}
			$(".sptxt").focus();
		});
		$(document).on("keydown blur", ".sptxt", function (e) {
			var sptxt = $(this);
			var id = $("#student_section").data("id");
			var val = sptxt.val();
			if (e.which == 13 || e.type == 'focusout') {
				//enter is pressed
				if (val == value) {
					//no changes made, cancel
					sp.html(old_data);
					return;
				}
				$.post("<?=base_url('edit_student/');?>" + type, "id=" + id + "&target=" + target + "&val=" + val, function (data) {
					if (data.hasOwnProperty("error")) {
						toastada.error('<?= lang("app.studentFailed")?>' + data.msg);
					} else if (data.hasOwnProperty("success")) {
						sp.html(data.result);
						sp.data("value", val);
						toastada.success('<?= lang("app.studentSaved")?>');
					} else {
						//unknown error
						toastada.error('<?= lang("app.fatalErr")?>');
					}
				}).fail(function () {
					//unknown error
					toastada.error('<?= lang("app.systemErr")?>');
				});
			}
			if (e.which == 27) {
				//escape is pressed
				sp.html(old_data);
			}
		});
		$(document).on("change", ".spchk", function (e) {
			var spchk = $(this);
			var id = $("#student_section").data("id");
			var val = spchk.is(":checked") ? 1 : 0;
			$.post("<?=base_url('edit_student/');?>" + type, "id=" + id + "&target=" + target + "&val=" + val, function (data) {
				if (data.hasOwnProperty("error")) {
					toastada.error('<?= lang("app.studentFailed")?>' + data.msg);
				} else if (data.hasOwnProperty("success")) {
					sp.html(data.result);
					sp.data("value", val);
					toastada.success("<?= lang("app.studentSaved")?>");
				} else {
					//unknown error
					toastada.error("<?= lang("app.fatalErr")?>");
				}
			}).fail(function () {
				//unknown error
				toastada.error("<?= lang("app.systemErr")?>");
			});
		});
		$(document).on("click","#dv_select_img",function () {
			$("#in_student_photo")[0].click();
		});
		$("#in_student_photo").on("change", function (e) {
			var file = $(this)[0].files[0];
			var upload = new Upload(file);
			// maby check size or type here with upload.getSize() and upload.getType()
			if (upload.getType()!="image/jpg" && upload.getType()!="image/jpeg" && upload.getType()!="image/png"){
				toastada.error("<?= lang("app.allowedOnly")?>")
				return;
			}
			if (upload.getSize()> 512*1024){
				toastada.error("<?= lang("app.sizeNeeded")?>");
				return;
			}
			// execute upload
			$("#img_photo").prop("src",upload.getSource());
			var id = $("#student_section").data("id");
			upload.doUpload("upload_image/student_picture",$("#dv_select_img p"),$("#img_photo"),id);
		});
		$("#displinetab").on("click",function (e) {
			var id=$(this).data("id");
			// alert(id);
			$.get("<?=base_url();?>displine_single_student/" + id , function (data) {

					$("#disciplineTable").html(data);
				})
		});
		$("#permissiontab").on("click",function (e) {
			var id=$(this).data("id");
			// alert(id);
			$.get("<?=base_url();?>permission_single_student/" + id , function (data) {

				$("#permissionTable").html(data);
			})
		});
		$("#tab-library").on("click",function (e) {
			var id=$(this).data("id");
			// alert(id);
			$.get("<?=base_url();?>library_single_student/" + id , function (data) {

				$("#libraryTable").html(data);
			})
		});
		$("#classtab").on("click",function (e) {
			var id=$(this).data("id");
			// alert(id);
			$.get("<?=base_url();?>class_record_single_student/" + id , function (data) {

				$("#classrecordTable").html(data);
			})
		});
	});

	var Upload = function (file) {
		this.file = file;
	};

	Upload.prototype.getType = function() {
		return this.file.type;
	};
	Upload.prototype.getSize = function() {
		return this.file.size;
	};
	Upload.prototype.getName = function() {
		return this.file.name;
	};
	Upload.prototype.getSource = function() {
		return URL.createObjectURL(this.file);
	};
	Upload.prototype.doUpload = function (url,loader,img,id=0) {
		var that = this;
		var formData = new FormData();

		// add assoc key values, this will be posts values
		formData.append("file", this.file, this.getName());
		formData.append("id", id);
		loader.text("Uploading...");
		$.ajax({
			type: "POST",
			url: window.base_url+url,
			xhr: function () {
				var myXhr = new window.XMLHttpRequest();
				if (myXhr.upload) {
					myXhr.upload.addEventListener('progress', that.progressHandling, false);
				}
				return myXhr;
			},
			success: function (data) {
				// your callback here
				loader.text("<?= lang("app.upLoad");?>");
				if (data.hasOwnProperty("error")){
					toastada.error("<?= lang("app.upLoadErr");?>" +data.error);
					img.prop("src","");
				}else if (data.hasOwnProperty("success")){
					toastada.success(data.success);
				}else{
					toastada.error("<?= lang("app.fatalErr");?>");
					img.prop("src","");
				}
			},
			error: function (error) {
				// handle error
				toastada.error("<?= lang("app.systemErr");?>");
				img.prop("src","");
				loader.text("<?= lang("app.uploadPhoto");?>");
			},
			async: true,
			data: formData,
			dataType: "json",
			cache: false,
			contentType: false,
			processData: false,
			timeout: 60000
		});
	};

	Upload.prototype.progressHandling = function (event) {

	};
</script>
