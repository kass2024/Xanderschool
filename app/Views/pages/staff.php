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
				<?php
				$contractLabel = static function ($value): string {
					$value = trim((string) $value);
					if ($value === '' || $value === '0000-00-00') {
						return '';
					}
					$ts = strtotime($value);
					return $ts ? date('d M Y', $ts) : '';
				};
				$contractStart = $contractLabel($staff['contract_start'] ?? '');
				$contractEnd = $contractLabel($staff['contract_end'] ?? '');
				?>
				<div class="form-group">
					<label>Contract start:</label>
					<span><?= $contractStart !== '' ? esc($contractStart) : '—'; ?></span>
				</div>
				<div class="form-group">
					<label>Contract end:</label>
					<span><?= $contractEnd !== '' ? esc($contractEnd) : '—'; ?></span>
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
			helper('qonics');
			$resolved = resolve_profile_photo($staff['photo'] ?? '');
			$hasPhoto = $resolved !== null;
			$photo = $hasPhoto ? profile_photo_url($resolved) : profile_photo_url(null);
			$fallbackPhoto = profile_photo_url(null);
			?>
			<div style="position:relative;float:left;width:100px;height:100px;">
				<img src="<?= esc($photo); ?>" id="img_photo" alt="" style="width:100px;height:100px;border-radius:50%;background:#FFFFFF;border:1px solid #4C5B5C;object-fit:cover;display:block;">
				<button type="button" id="btn_remove_photo" title="<?= esc(lang('app.removePhoto')); ?>"
					style="position:absolute;top:0;right:0;width:24px;height:24px;border:none;border-radius:50%;background:#dc3545;color:#fff;font-size:12px;line-height:24px;padding:0;cursor:pointer;<?= $hasPhoto ? '' : 'display:none;'; ?>">
					<i class="fa fa-times"></i>
				</button>
			</div>
			<input type="file" id="in_student_photo" accept="image/jpeg,image/jpg,image/png" style="display: none;overflow: hidden">
			<div style="border: 1px dashed #bababa;float: left;width: calc(100% - 110px);margin-left: 10px;height: 100px;cursor: pointer;text-align: center;" id="dv_select_img">
				<p style="margin: 30px 0 0;font-weight: 600;"><?= lang("app.uploadPhoto");?></p>
				<label class="text-muted" style="font-style: italic;font-size: 10pt;"><?= lang("app.totalSize");?></label>
			</div>
		</div>
		<?php
		$signatureFile = staff_signature_file($staff['signature'] ?? '');
		$signatureSrc = $signatureFile !== ''
			? base_url('assets/images/signatures/' . rawurlencode($signatureFile)) . '?v=' . filemtime(FCPATH . 'assets/images/signatures/' . $signatureFile)
			: '';
		$canSignStaff = (int) ($staff['id'] ?? 0) === (int) ($_SESSION['soma_id'] ?? 0)
			|| is_head_master_equivalent()
			|| \Config\MenuClearance::canSetStaffContract((int) ($_SESSION['soma_post'] ?? 0));
		?>
		<div class="boxed" style="background:#fff;clear:both;">
			<h4>Electronic signature</h4>
			<p class="text-muted" style="font-size:10pt;margin-bottom:8px;">This signature is printed on the periodic report when this person is the class teacher or the head teacher.</p>
			<?php if ($signatureSrc !== ''): ?>
				<img id="staff-signature-preview" src="<?= esc($signatureSrc); ?>" alt="Saved signature" style="display:block;height:52px;max-width:100%;background:#fff;border:1px solid #e5e7eb;margin-bottom:8px;">
			<?php else: ?>
				<img id="staff-signature-preview" alt="" style="display:none;height:52px;max-width:100%;background:#fff;border:1px solid #e5e7eb;margin-bottom:8px;">
			<?php endif; ?>
			<?php if ($canSignStaff): ?>
				<canvas id="staff-sign-pad" width="640" height="160" style="width:100%;height:140px;border:1px dashed #94a3b8;background:#fff;touch-action:none;cursor:crosshair;display:block;"></canvas>
				<div style="margin-top:8px;">
					<button type="button" class="btn btn-sm btn-light" id="staff-sign-clear">Clear</button>
					<button type="button" class="btn btn-sm btn-primary" id="staff-sign-save">Save signature</button>
					<button type="button" class="btn btn-sm btn-outline-danger" id="staff-sign-remove" <?= $signatureSrc === '' ? 'style="display:none;"' : ''; ?>>Remove</button>
				</div>
			<?php elseif ($signatureSrc === ''): ?>
				<span>—</span>
			<?php endif; ?>
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
			if (is_head_master_equivalent()) {
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
						toastada.error('<?= lang("app.saveStaffFail");?>' + (data.error || data.msg || ''));
					} else if (data.hasOwnProperty("success")) {
						sp.html(data.result);
						sp.data("value", val);
						toastada.success('<?= lang("app.staffSaved");?>');
					} else {
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
					toastada.error('<?= lang("app.saveStaffFail");?>' + (data.error || data.msg || ''));
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
		function toggleStaffPhotoRemove(show) {
			$("#btn_remove_photo").toggle(!!show);
		}
		function removeStaffPhoto() {
			if (!confirm("<?= esc(lang('app.removeStaffPhotoConfirm'), 'js'); ?>")) {
				return;
			}
			var id = $("#staff_section").data("id");
			$.post(window.base_url + "remove_staff_photo", { id: id }, function (data) {
				if (data.hasOwnProperty("error")) {
					toastada.error(data.error);
				} else if (data.hasOwnProperty("success")) {
					$("#img_photo").prop("src", <?= json_encode($fallbackPhoto) ?>);
					$("#in_student_photo").val("");
					toggleStaffPhotoRemove(false);
					toastada.success(data.success);
				} else {
					toastada.error('<?= lang("app.fatalErr");?>');
				}
			}, "json").fail(function () {
				toastada.error('<?= lang("app.systemErr");?>');
			});
		}
		$("#btn_remove_photo").on("click", function (e) {
			e.preventDefault();
			e.stopPropagation();
			removeStaffPhoto();
		});
		$("#in_student_photo").on("change", function (e) {
			var file = $(this)[0].files[0];
			var upload = new Upload(file);
			// maby check size or type here with upload.getSize() and upload.getType()
			if (upload.getType()!="image/jpg" && upload.getType()!="image/jpeg" && upload.getType()!="image/png"){
				toastada.error('<?= lang("app.allowedOnly");?>')
				return;
			}
			if (upload.getSize()> 5*1024*1024){
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
					toggleStaffPhotoRemove(true);
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

	(function () {
		var canvas = document.getElementById('staff-sign-pad');
		if (!canvas) return;
		var ctx = canvas.getContext('2d');
		var drawing = false;
		var last = null;
		function fit() {
			var rect = canvas.getBoundingClientRect();
			var ratio = window.devicePixelRatio || 1;
			var w = Math.max(1, Math.round(rect.width * ratio));
			var h = Math.max(1, Math.round(rect.height * ratio));
			if (canvas.width !== w || canvas.height !== h) {
				canvas.width = w;
				canvas.height = h;
			}
			ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
			ctx.lineWidth = 2.4;
			ctx.lineCap = 'round';
			ctx.lineJoin = 'round';
			ctx.strokeStyle = '#1d4ed8';
		}
		fit();
		function point(e) {
			var rect = canvas.getBoundingClientRect();
			var src = e.touches && e.touches[0] ? e.touches[0] : e;
			return { x: src.clientX - rect.left, y: src.clientY - rect.top };
		}
		function start(e) {
			drawing = true;
			last = point(e);
			if (e.cancelable) e.preventDefault();
		}
		function move(e) {
			if (!drawing) return;
			var p = point(e);
			ctx.beginPath();
			ctx.moveTo(last.x, last.y);
			ctx.lineTo(p.x, p.y);
			ctx.stroke();
			last = p;
			if (e.cancelable) e.preventDefault();
		}
		function stop() {
			drawing = false;
			last = null;
		}
		canvas.addEventListener('mousedown', start);
		canvas.addEventListener('mousemove', move);
		window.addEventListener('mouseup', stop);
		canvas.addEventListener('touchstart', start, { passive: false });
		canvas.addEventListener('touchmove', move, { passive: false });
		canvas.addEventListener('touchend', stop);
		document.getElementById('staff-sign-clear').addEventListener('click', function () {
			fit();
			ctx.clearRect(0, 0, canvas.getBoundingClientRect().width, canvas.getBoundingClientRect().height);
		});
		function postSignature(payload) {
			$.post('<?= base_url('save_staff_signature'); ?>', payload, function (data) {
				if (data && data.error) {
					toastada.error(data.error);
					return;
				}
				toastada.success(data && data.success ? data.success : 'Saved');
				var preview = document.getElementById('staff-signature-preview');
				var removeBtn = document.getElementById('staff-sign-remove');
				if (data && data.src && preview) {
					preview.src = data.src;
					preview.style.display = 'block';
					if (removeBtn) removeBtn.style.display = '';
				}
				if (payload.clear === '1' && preview) {
					preview.style.display = 'none';
					preview.removeAttribute('src');
					if (removeBtn) removeBtn.style.display = 'none';
				}
			}, 'json').fail(function () {
				toastada.error('Could not save the signature.');
			});
		}
		document.getElementById('staff-sign-save').addEventListener('click', function () {
			var rect = canvas.getBoundingClientRect();
			var out = document.createElement('canvas');
			out.width = Math.max(1, Math.round(rect.width));
			out.height = Math.max(1, Math.round(rect.height));
			var ink = out.getContext('2d');
			ink.fillStyle = '#ffffff';
			ink.fillRect(0, 0, out.width, out.height);
			ink.drawImage(canvas, 0, 0, out.width, out.height);
			postSignature({
				staff_id: $('#staff_section').data('id'),
				signature: out.toDataURL('image/png')
			});
		});
		var removeBtn = document.getElementById('staff-sign-remove');
		if (removeBtn) {
			removeBtn.addEventListener('click', function () {
				postSignature({ staff_id: $('#staff_section').data('id'), clear: '1' });
				fit();
				ctx.clearRect(0, 0, canvas.getBoundingClientRect().width, canvas.getBoundingClientRect().height);
			});
		}
	})();
</script>
