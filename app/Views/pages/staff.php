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
<div class="row" id="staff_section" data-id="<?= $staff['id']; ?>">
		<hr style="width: 100%;float: left"/>
		<div class="col-sm-12 col-md-6 col-lg-6 pull-left">
			<div class="boxed" style="background:white;padding: 20px">
				<h4><?= lang("app.staffInformation");?></h4>
				<div class="form-group">
					<label><?= lang("app.firstName");?>:</label>
					<span data-value="<?= $staff['fname']; ?>" data-target="fname"
						  class="spedit">&nbsp;<?= $staff['fname']; ?></span>
				</div>
				<div class="form-group">
					<label><?= lang("app.lastName");?>:</label>
					<span data-value="<?= $staff['lname']; ?>" data-target="lname"
						  class="spedit">&nbsp;<?= $staff['lname']; ?></span>
				</div>
				<div class="form-group">
					<label><?= lang("app.phone");?>:</label>
					<span data-value="<?= $staff['phone']; ?>" data-target="phone"
						  class="spedit">&nbsp;<?= $staff['phone']; ?></span>
				</div>
				<div class="form-group">
					<label><?= lang("app.email");?>:</label>
					<span data-value="<?= $staff['email']; ?>" data-target="email"
						  class="spedit">&nbsp;<?= $staff['email']; ?></span>
				</div>
				<div class="form-group">
					<label><?= lang("app.address");?>:</label>
					<span data-value="<?= $staff['address']; ?>" data-target="address"
						  class="spedit">&nbsp;<?= $staff['address']; ?></span>
				</div>
			</div>
		</div>
	<div class="col-sm-12 col-md-5 col-lg-5 pull-left">
		<div class="boxed">
			<h4><?= lang("app.schoolInformation");?></h4>
			<div style="margin-left: 20px">
				<label><?= lang("app.post");?>: <?= $staff['post_title']; ?></label><br/>
				<label><?= lang("app.school");?>: <?= $_SESSION['soma_school']; ?></label><br/>
			</div>
		</div>
		<div class="boxed" style="background-color: white;display: flow-root;">
			<h4><?= lang("app.staffPhoto");?></h4>
			<?php
			$photo = strlen($staff['photo']) > 4 ? base_url('assets/images/profile/' . $staff['photo']) : '';
			?>
			<img src="<?=$photo;?>" id="img_photo" style="width: 100px;height: 100px;border-radius: 50%;background: #FFFFFF;float:left;border: 1px solid #4C5B5C;">
			<input type="file" id="in_student_photo" style="display: none;overflow: hidden">
			<div style="border: 1px dashed #bababa;float: left;width: calc(100% - 110px);margin-left: 10px;height: 100px;cursor: pointer;text-align: center;" id="dv_select_img">
				<p style="margin: 30px 0 0;font-weight: 600;"><?= lang("app.uploadPhoto");?></p>
				<label class="text-muted" style="font-style: italic;font-size: 10pt;"<?= lang("app.totalSize");?>></label>
			</div>
		</div>
	</div>
</div>
<div class="row">
	<div class="mb-3 card" style="width: 100%">
		<div class="card-header-tab card-header">

			<ul class="nav" style="margin-left: 0">
				<li class="nav-item"><a data-toggle="tab" href="#tab-course" class="nav-link active"><?= lang("app.courseRecord");?></a></li>
				<li class="nav-item"><a data-toggle="tab" href="#tab-sms" class="nav-link"><?= lang("app.communicationHistory");?></a></li>
				<li class="nav-item"><a data-toggle="tab" href="#tab-leave" class="nav-link"><?= lang("app.leaveHistory");?></a></li>
			</ul>
		</div>
		<div class="card-body">
			<div class="tab-content">
				<div class="tab-pane active" id="tab-course" role="tabpanel"><p><?= lang("app.courseRecord");?></p></div>
				<div class="tab-pane" id="tab-sms" role="tabpanel"><p><?= lang("app.communicationActivity");?></p></div>
				<div class="tab-pane" id="tab-leave" role="tabpanel"><p><?= lang("app.leaveHistory");?></p></div>
			</div>
		</div>
		<div class="d-block text-right card-footer">
			<?php
			if ($staff['id']==$_SESSION["soma_id"]) {
				?>
				<a href="javascript:void(0);" class="btn-wide btn-shadow btn btn-info" data-toggle="modal"
				   data-target="#mdlPass"><?= lang("app.changePassword");?></a>
				<?php
			}
			?>
			<?php
			if ($_SESSION["soma_post"]==1) {
				?>
				<a href="javascript:void(0);" class="btn-wide btn-shadow btn btn-dark"><?= lang("app.changePost");?></a>
				<a href="javascript:void(0);" class="btn-wide btn-shadow btn btn-danger"><?= lang("app.del");?></a>
				<?php
			}
			?>
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
			var id = $("#staff_section").data("id");
			var val = sptxt.val();
			if (e.which == 13 || e.type == 'focusout') {
				//enter is pressed
				if (val == value) {
					//no changes made, cancel
					sp.html(old_data);
					return;
				}
				$.post("<?=base_url('edit_staff/');?>" + type, "id=" + id + "&target=" + target + "&val=" + val, function (data) {
					if (data.hasOwnProperty("error")) {
						toastada.error('<?= lang("app.saveStaffFail");?>' + data.msg);
					} else if (data.hasOwnProperty("success")) {
						sp.html(data.result);
						sp.data("value", val);
						toastada.success('<?= lang("app.staffSaved");?>');
					} else {
						//unknown error
						toastada.error('<?= lang("app.fatalErr");?>');
					}
				}).fail(function () {
					//unknown error
					toastada.error('<?= lang("app.systemErr");?>');
				});
			}
			if (e.which == 27) {
				//escape is pressed
				sp.html(old_data);
			}
		});
		$(document).on("change", ".spchk", function (e) {
			var spchk = $(this);
			var id = $("#staff_section").data("id");
			var val = spchk.is(":checked") ? 1 : 0;
			$.post("<?=base_url('edit_staff/');?>" + type, "id=" + id + "&target=" + target + "&val=" + val, function (data) {
				if (data.hasOwnProperty("error")) {
					toastada.error('<?= lang("app.saveStaffFail");?>' + data.msg);
				} else if (data.hasOwnProperty("success")) {
					sp.html(data.result);
					sp.data("value", val);
					toastada.success('<?= lang("app.staffSaved");?>');
				} else {
					//unknown error
					toastada.error('<?= lang("app.fatalErr");?>');
				}
			}).fail(function () {
				//unknown error
				toastada.error('<?= lang("app.systemErr");?>');
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
				toastada.error('<?= lang("app.allowedOnly");?>')
				return;
			}
			if (upload.getSize()> 512*1024){
				toastada.error('<?= lang("app.sizeNeeded");?>');
				return;
			}
			// execute upload
			$("#img_photo").prop("src",upload.getSource());
			var id = $("#staff_section").data("id");
			upload.doUpload("upload_image/staff_picture",$("#dv_select_img p"),$("#img_photo"),id);
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
				loader.text('<?= lang("app.upLoad");?>');
				if (data.hasOwnProperty("error")){
					toastada.error('<?= lang("app.upLoadErr");?>' +data.error);
					img.prop("src","");
				}else if (data.hasOwnProperty("success")){
					toastada.success(data.success);
				}else{
					toastada.error('<?= lang("app.fatalErr");?>');
					img.prop("src","");
				}
			},
			error: function (error) {
				// handle error
				toastada.error('<?= lang("app.systemErr");?>');
				img.prop("src","");
				loader.text('<?= lang("app.uploadPhoto");?>');
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
