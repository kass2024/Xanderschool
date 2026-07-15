
<div class="app-inner-layout app-inner-layout-page">
	<div class="app-inner-bar">
		<div class="inner-bar-center">
			<ul class="nav">
				<li class="nav-item">
					<a role="tab" data-toggle="tab" id="tab-nav-0" class="nav-link active" href="#tab-content-0"
					   aria-selected="false">
						<span><?= lang("app.singleStudentRegistration");?></span>
					</a>
				</li>
				<li class="nav-item">
					<a role="tab" data-toggle="tab" id="tab-nav-1" class="nav-link" href="#tab-content-1"
					   aria-selected="true" >
						<span><?= lang("app.massUploading");?></span>
					</a>
				</li>

			</ul>
		</div>

	</div>
	<div class="app-inner-layout__wrapper">
		<div class="app-inner-layout__content">
			<div class="tab-content">
				<div id="tab-content-0" class="tab-pane tabs-animation fade active show" role="tabpanel">
					<div class="container-fluid">
						<div class="row">
							<form action="<?= base_url('manipulate_student'); ?>" class="validate autoSubmit">
								<div class="pull-left" style="margin-bottom: 20px">
									<button type="submit" class="btn btn-success"><?= lang("app.saveStudent");?></button>
									<button type="submit" class="btn btn-info"><?= lang("app.saveGoback");?></button>
									<button type="reset" class="btn btn-light"><?= lang("app.reset");?></button>
								</div>
								<h4 class="pull-right"><?= lang("app.StudentRegForm");?></h4>
								<hr style="width: 100%;float: left"/>
								<div class="col-sm-12 col-md-6 col-lg-6 pull-left">
									<div class="main-card mb-3 card" style="padding: 20px">
										<div class="form-group">
											<label><?= lang("app.regno");?></label><span class="required">*</span>
											<input class="form-control" type="text" value="<?=$regno;?>" readonly required minlength="4">
										</div>
										<div class="form-group">
											<label><?= lang("app.firstName");?></label><span class="required">*</span>
											<input class="form-control" type="text" name="fname" required minlength="3">
										</div>
										<div class="form-group">
											<label><?= lang("app.lastName");?></label><span class="required">*</span>
											<input class="form-control" type="text" name="lname" required minlength="3">
										</div>

										<div class="form-group">
											<label><?= lang("app.email");?></label>
											<input class="form-control" type="email" name="email">
										</div>

										<div class="form-group">
											<label><?= lang("app.birthDate");?></label><span class="required">*</span>
											<input class="form-control date_mask" type="text" name="dob"  input-mask="d/m/y" placeholder="__/__/____" required minlength="4">
										</div>
										<div class="form-group">
											<label><?= lang("app.sex");?></label><span class="required">*</span>
											<label class="form-check-label" style="margin-left: 40px"><input
													class="form-check-input"
													type="radio" name="sex"
													value="F"
													required><?= lang("app.female");?></label>
											<label class="form-check-label" style="margin-left: 40px"><input
													class="form-check-input"
													type="radio" name="sex"
													value="M"
													required><?= lang("app.male");?></label>
										</div>
										<div class="form-group">
											<label><?= lang("app.nationality");?></label>
											<input class="form-control" type="text" name="nationality"
												   minlength="4">
										</div>
										<div class="form-group">
											<label><?= lang("app.province");?></label>
											<select class="form-control select2 address_select" data-target="district" name="province">
												<option selected disabled><?= lang("app.selectProvince");?></option>
												<?php
												foreach ($provinces as $province):
													echo "<option value='{$province['id']}'>{$province['title']}</option>";
												endforeach;
												?>
											</select>
										</div>
										<div class="form-group">
											<label><?= lang("app.district");?></label>
											<select class="form-control select2 address_select" data-target="sector" name="district" >
												<option selected disabled><?= lang("app.selectDistrict");?></option>
											</select>
										</div>
										<div class="form-group">
											<label><?= lang("app.sector");?></label>
											<select class="form-control select2 address_select" data-target="cell" name="sector" >
												<option selected disabled><?= lang("app.selectSector");?></option>
											</select>
										</div>
										<div class="form-group">
											<label><?= lang("app.cell");?></label>
											<select class="form-control select2 address_select" data-target="village" name="cell" >
												<option selected disabled><?= lang("app.selectCell");?></option>
											</select>
										</div>
										<div class="form-group">
											<label><?= lang("app.village");?></label>
											<select class="form-control select2" name="village" >
												<option selected disabled><?= lang("app.selectVillage");?></option>
											</select>
										</div>
									</div>
								</div>
								<div class="col-sm-12 col-md-6 col-lg-6 pull-left ">
									<div class="main-card mb-3 card" style="padding: 20px">
										<div class="form-group">
											<label><?= lang("app.sClass");?></label><span class="required">*</span>
											<select class="form-control select2" name="class" required>
												<option selected disabled><?= lang("app.selectClass");?></option>
												<?php
												foreach ($classes as $class)
												{
													echo "<option value='{$class['id']}'>{$class['level_name']} {$class['code']} {$class['title']}</option>";
												}
												?>
											</select>
										</div>
										<div class="form-group">
											<label><?= lang("app.studyingMode");?></label><span class="required">*</span>
											<select class="form-control select2" name="mode" required>
												<option selected disabled><?= lang("app.selectStudyingMode"); ?></option>
												<option value="0"><?= lang("app.boarding"); ?></option>
												<option value="1"><?= lang("app.day"); ?></option>
											</select>
										</div>
										<div class="form-group">
											<label><?= lang("app.religion"); ?></label>
											<select class="form-control select2" name="religion">
												<option selected disabled><?= lang("app.selectReligion"); ?></option>
												<option><?= lang("app.islam"); ?></option>
												<option><?= lang("app.catholics"); ?></option>
												<option><?= lang("app.adventist"); ?></option>
												<option><?= lang("app.jehovahWitness"); ?></option>
												<option><?= lang("app.otherChristians"); ?></option>
											</select>
										</div>
									</div>
								</div>
								<div class="col-sm-12 col-md-6 col-lg-6 pull-left ">
									<div class="main-card mb-3 card" style="padding: 20px">
										<div class="form-group">
											<label><?= lang("app.fatherNames"); ?></label>
											<input type="text" class="form-control" name="father"/>
										</div>
										<div class="form-group">
											<label><?= lang("app.fPhoneNumber"); ?></label>
											<input type="text" class="form-control" name="father_phone"
												   data-parsley-lenght="[10,13]"
												   data-parsley-lenght-message="<?= lang("app.fphonenotvalid");?>"/>
										</div>
										<div class="form-group">
											<label><?= lang("app.motherNames"); ?></label>
											<input type="text" class="form-control" name="mother"/>
										</div>
										<div class="form-group">
											<label><?= lang("app.motherPhoneNumber"); ?></label>
											<input type="text" class="form-control" name="mother_phone"
												   data-parsley-lenght="[10,13]"
												   data-parsley-lenght-message="<?= lang("app.fphonenotvalid");?>"/>
										</div>
										<div class="form-group">
											<label><?= lang("app.guardiaNames");?></label>
											<input type="text" class="form-control" name="guardian"/>
										</div>
										<div class="form-group">
											<label><?= lang("app.guardianPhoneNumber"); ?></label>
											<input type="text" class="form-control" name="guardian_phone"
												   data-parsley-lenght="[10,13]"
												   data-parsley-lenght-message="<?= lang("app.fphonenotvalid");?>"/>
										</div>
									</div>
								</div>
								<hr style="width: 100%;float: left"/>
								<div class="pull-left" style="margin-bottom: 20px">
									<button type="submit" class="btn btn-success" data-target="<?=base_url('register-student');?>"><?= lang("app.saveStudent"); ?></button>
									<button type="submit" class="btn btn-info" data-target="<?=base_url('students');?>"><?= lang("app.saveGoback"); ?></button>
									<button type="reset" class="btn btn-light"><?= lang("app.reset"); ?></button>
								</div>
							</form>
						</div>
					</div>
				</div>
				<div id="tab-content-1" class="tab-pane tabs-animation fade" role="tabpanel">
					<div class="container-fluid">
						<form action="<?=base_url('download_student_template'); ?>" method="POST" >
							<div class="row">
								<div class="form-group">
									<label><i class="fa fa-school"></i> <?= lang("app.sClass"); ?></label>
									<select class="form-control select2" id="select_class" name="class_id_name">
										<option selected disabled><?= lang("app.selectClass"); ?></option>
										<?php
										foreach ($classes as $classe)
										{
											?>
											<option value="<?=$classe['id'];?>-<?=$classe['level_name'];?>_<?=$classe['code'];?>"> <?=$classe['level_name'];?> <?=$classe['code'];?> <?=$classe['title'];?></option>
											<?php
										}?>
									</select>
								</div>

								<div class="form-group" style="margin-left: 30px; display: none;" id="download">
									<label><i class="fa fa-download"></i> <?= lang("app.exceltemplate"); ?> </label>
									<button type="submit" class="btn btn-primary form-control"><i class="fa fa-download"></i> <?= lang("app.download"); ?></button></a>
								</div>
						</form>
						<div class="form-group" style="margin-left: 30px; display: none;height: 15px;" id="upload">
							<form action="<?= base_url('upload_student_template'); ?>" method="POST" enctype="multipart/form-data" class="validate">
								<label><i class="fa fa-upload"></i><?= lang("app.chooseFile"); ?></label><br>
								<input type="file" name="documents">
								<input type="hidden" name="check_class" id="check_class">
						</div>

						<div class="form-group" style="margin-left: 10px; display: none;" id="save">
							<button type="submit" class="btn btn-success" data-target="<?=base_url('register-student');?>" style="font-size:12px;margin-top: 20px;cursor: pointer;"><i class="fa fa-check"></i> <?= lang("app.save"); ?></button>
						</div>
						</form>
					</div>
				</div>
			</div>
			</div>
		</div>
	</div>
</div>
<script>
	$(function () {
		$(".address_select").on("change",function () {
			var target = $(this).data("target");
			var current = $(this).prop("name");
			var value = $(this).val();
			$.get("<?=base_url();?>get_address/"+target,"key="+current+"&val="+value,function (data) {
				$("[name='"+target+"']").html(data);
			});
		});
		$("#select_class").on("change",function(){
			var id=$(this).val();
			$("#check_class").val(id);
			$("#download").show();
			$("#upload").show();
			$("#save").show();
		})
	})
</script>
