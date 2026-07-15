 <style>
	.settings span {
		font-weight: 600;
	}

	.spedit {
		min-width: 100px;
		cursor: pointer;
		display: inline-block;
	}

	.boxed {
		padding: 20px;
		border-radius: 5px;
		background: #e1e0e0;
	}

	.ihelp {
		font-size: 30pt;
		position: absolute;
		top: 10px;
		left: 59%;
		color: #333333;
		cursor: pointer;
	}

	span {
		font-weight: 600;
	}

	@media all and (max-width: 1249px) {
		.ihelp {
			left: 50%;
			color: #ffffff;
		}
	}

</style>
<script src="<?=base_url('assets/plugins/spectrum/spectrum.js');?>"></script>
<link href="<?=base_url('assets/plugins/spectrum/spectrum.css');?>" type="text/css" rel="stylesheet"/>
<i class="fa fa-question-circle ihelp" data-toggle="tooltip"
   title="Double click value to enable edit mode then press enter or outside to save or escape key to cancel"></i>
<div class="settings" id="settings_section" data-id="<?= $settings['id']; ?>">
	<div id="accordion" class="accordion-wrapper mb-3">
		<div class="">
			<div id="headingOne" class="card-header">
				<button type="button" data-toggle="collapse" data-target="#collapseOne1" aria-expanded="true"
						aria-controls="collapseOne" class="text-left m-0 p-0 btn btn-link btn-block">
					<h5 class="m-0 p-0"><?= lang("app.basicSchoolInfo"); ?></h5>
				</button>
			</div>
			<div data-parent="#accordion" id="collapseOne1" aria-labelledby="headingOne" class="collapse show">
				<div class="card-body">

					<div class="col-sm-12 col-md-7 col-lg-6 pull-left" style="padding-left: 40px">
						<div class="form-group">
							<label><?= lang("app.schoolName"); ?>:</label>
							<span data-value="<?= $settings['name']; ?>" data-target="name"
								  class="spedit">&nbsp;<?= $settings['name']; ?></span>
						</div>
						<div class="form-group">
							<label><?= lang("app.acronym"); ?>:</label>
							<span data-value="<?= $settings['acronym']; ?>" data-target="acronym"
								  class="spedit">&nbsp;<?= $settings['acronym']; ?></span>
						</div>
						<div class="form-group">
							<label><?= lang("app.sLogan"); ?>:</label>
							<span data-value="<?= $settings['slogan']; ?>" data-target="slogan"
								  class="spedit">&nbsp;<?= $settings['slogan']; ?></span>
						</div>
						<div class="form-group">
							<label><?= lang("app.phone"); ?>:</label>
							<span data-value="<?= $settings['phone']; ?>" data-target="phone"
								  class="spedit">&nbsp;<?= $settings['phone']; ?></span>
						</div>
						<div class="form-group">
							<label><?= lang("app.email"); ?>:</label>
							<span data-value="<?= $settings['email']; ?>" data-target="email"
								  class="spedit">&nbsp;<?= $settings['email']; ?></span>
						</div>
						<div class="form-group">
							<label><?= lang("app.headMaster"); ?>:</label>
							<span data-value="<?= $settings['head_master']; ?>" data-target="head_master"
								  class="spedit">&nbsp;<?= $settings['head_master']; ?></span>
						</div>
						<div class="form-group">
							<label><?= lang("app.website"); ?>:</label>
							<span data-value="<?= $settings['website']; ?>" data-target="website"
								  class="spedit">&nbsp;<?= $settings['website']; ?></span>
						</div>
						<div class="form-group">
							<label>P.O BOX:</label>
							<span data-value="<?= $settings['pobox']; ?>" data-target="pobox"
								  class="spedit">&nbsp;<?= $settings['pobox']; ?></span>
						</div>
						<div class="form-group">
							<label>Address:</label>
							<span data-value="<?= $settings['address']; ?>" data-target="address"
								  class="spedit">&nbsp;<?= $settings['address']; ?></span>
						</div>
						<div class="form-group">
							<label><?= lang("app.disciplineMax"); ?>:</label>
							<span data-value="<?= $settings['discipline_max']; ?>" data-target="discipline_max"
								  class="spedit">&nbsp;<?= $settings['discipline_max']; ?></span>
						</div>
						<div class="form-group">
							<label>Bank Name:</label>
							<span data-value="<?= $settings['bank_name']; ?>" data-target="bank_name"
								  class="spedit">&nbsp;<?= $settings['bank_name']; ?></span>
						</div>
						<div class="form-group">
							<label>Bank account:</label>
							<span data-value="<?= $settings['bank_account']; ?>" data-target="bank_account"
								  class="spedit">&nbsp;<?= $settings['bank_account']; ?></span>
						</div>
						<div class="form-group">
							<label>MOMO account:</label>
							<span data-value="<?= $settings['mtn_momo_phone']; ?>" data-target="mtn_momo_phone"
								  class="spedit">&nbsp;<?= $settings['mtn_momo_phone']; ?></span>
							<label class="text-muted">MTN phone number that is registered in MOMO pay that will be used to receive School and registration fees paid by parents</label>

						</div>
						<div class="form-group">
							<label>MOMO account:</label>
							<span data-value="<?= $settings['pocket_money_phone']; ?>" data-target="pocket_money_phone"
								  class="spedit">&nbsp;<?= $settings['pocket_money_phone']; ?></span>
							<label class="text-muted">MTN phone number that is registered in MOMO pay that will be used to receive student money (Pocket money) sent by parents</label>

						</div>
					</div>
					<div class="col-sm-12 col-md-5 col-lg-5 pull-left">
						<div class="boxed">
							<h4><?= lang("app.activeTerm"); ?></h4>
							<div style="margin-left: 20px">
								<label>Academic year: <?= $academic_year_title; ?></label><br/>
								<label>Term: <?= \App\Controllers\Home::TermToStr($settings['term']); ?></label><br/>
								<label><?= lang("app.usePeriodicSystem"); ?> <?= $settings['use_period'] == 1 ? lang("app.yes") : lang("app.no"); ?></label><br/>
								<label><?= lang("app.usedSMS"); ?> <?= $settings['sms_usage']; ?></label><br/>
<!--								<label>--><?//= lang("app.remainSMS"); ?><!-- --><?//= $settings['sms_limit'] - $settings['sms_usage'] + $settings['extra_sms']; ?><!--</label><br/>-->
								<label><?= lang("app.remainSMS"); ?> <?= $settings['extra_sms']; ?></label><br/>
							</div>
						</div>
						<div class="boxed" style="background-color: white;display: flow-root;">
							<h4><?= lang("app.schoolLogo"); ?></h4>
							<?php
							$logo = strlen($settings['logo']) > 4 ? base_url('assets/images/logo/' . $settings['logo']) : '';
							?>
							<img src="<?= $logo; ?>" id="img_logo"
								 style="width: 100px;height: 100px;border-radius: 50%;background: #FFFFFF;float:left;border: 1px solid #4C5B5C;">
							<input type="file" id="in_school_logo" style="display: none;overflow: hidden">
							<div
								style="border: 1px dashed #bababa;float: left;width: calc(100% - 110px);margin-left: 10px;height: 100px;cursor: pointer;text-align: center;"
								id="dv_select_img">
								<p style="margin: 30px 0 0;font-weight: 600;"><?= lang("app.uploadLogo"); ?></p>
								<label class="text-muted" style="font-style: italic;font-size: 10pt;"><?= lang("app.totalSize"); ?></label>
							</div>
						</div>
						<div class="boxed" style="background-color: white;display: flow-root;position:relative;">
							<h4><?= lang('app.' . ($head_master_gender === 'F' ? 'schoolHeadmistress' : 'schoolHeadmaster')); ?></h4>
							<?php
							if (strlen($settings['headmaster_signature']) > 4){
								echo "<span id='btn-remove-signature' style='cursor:pointer;position:absolute;right: 15px;top:20px;color: orangered'><i class='fa fa-times'></i> Delete</span>";
							}
							$signature = strlen($settings['headmaster_signature']) > 4 ? base_url('assets/images/signatures/' . $settings['headmaster_signature']) : '';
							?>
							<img src="<?= $signature; ?>" id="img_headmaster_signature"
								 style="width: 100px;height: 100px;border-radius: 50%;background: #FFFFFF;float:left;border: 1px solid #4C5B5C;">
							<input type="file" id="in_headmaster_signature" style="display: none;overflow: hidden">
							<div
									style="border: 1px dashed #bababa;float: left;width: calc(100% - 110px);margin-left: 10px;height: 100px;cursor: pointer;text-align: center;"
									id="dv_select_headmaster_signature">
								<p style="margin: 30px 0 0;font-weight: 600;">Upload signature</p>
								<label class="text-muted" style="font-style: italic;font-size: 10pt;"><?= lang("app.totalSize"); ?></label>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<!-- Matron Signature -->
<div class="boxed" style="background-color: white;display: flow-root;position:relative;">
  <h4>Matron Signature</h4>
  <?php
  if (strlen($settings['matron_signature']) > 4){
    echo "<span id='btn-remove-matron' style='cursor:pointer;position:absolute;right: 15px;top:20px;color: orangered'><i class='fa fa-times'></i> Delete</span>";
  }
  $matron_signature = strlen($settings['matron_signature']) > 4 ? base_url('assets/images/signatures/' . $settings['matron_signature']) : '';
  ?>
  <img src="<?= $matron_signature; ?>" id="img_matron_signature"
       style="width: 100px;height: 100px;border-radius: 50%;background: #FFFFFF;float:left;border: 1px solid #4C5B5C;">
  <input type="file" id="in_matron_signature" style="display: none;overflow: hidden">
  <div
       style="border: 1px dashed #bababa;float: left;width: calc(100% - 110px);margin-left: 10px;height: 100px;cursor: pointer;text-align: center;"
       id="dv_select_matron_signature">
    <p style="margin: 30px 0 0;font-weight: 600;">Upload Signature</p>
    <label class="text-muted" style="font-style: italic;font-size: 10pt;">Max 512KB (jpg, jpeg, png)</label>
  </div>
</div>

<!-- Patron Signature -->
<div class="boxed" style="background-color: white;display: flow-root;position:relative;">
  <h4>Patron Signature</h4>
  <?php
  if (strlen($settings['patron_signature']) > 4){
    echo "<span id='btn-remove-patron' style='cursor:pointer;position:absolute;right: 15px;top:20px;color: orangered'><i class='fa fa-times'></i> Delete</span>";
  }
  $patron_signature = strlen($settings['patron_signature']) > 4 ? base_url('assets/images/signatures/' . $settings['patron_signature']) : '';
  ?>
  <img src="<?= $patron_signature; ?>" id="img_patron_signature"
       style="width: 100px;height: 100px;border-radius: 50%;background: #FFFFFF;float:left;border: 1px solid #4C5B5C;">
  <input type="file" id="in_patron_signature" style="display: none;overflow: hidden">
  <div
       style="border: 1px dashed #bababa;float: left;width: calc(100% - 110px);margin-left: 10px;height: 100px;cursor: pointer;text-align: center;"
       id="dv_select_patron_signature">
    <p style="margin: 30px 0 0;font-weight: 600;">Upload Signature</p>
    <label class="text-muted" style="font-style: italic;font-size: 10pt;">Max 512KB (jpg, jpeg, png)</label>
  </div>
</div>

<!-- Head of Discipline Signature -->
<div class="boxed" style="background-color: white;display: flow-root;position:relative;">
  <h4>Head of Discipline Signature</h4>
  <?php
  if (strlen($settings['discipline_signature']) > 4){
    echo "<span id='btn-remove-discipline' style='cursor:pointer;position:absolute;right: 15px;top:20px;color: orangered'><i class='fa fa-times'></i> Delete</span>";
  }
  $discipline_signature = strlen($settings['discipline_signature']) > 4 ? base_url('assets/images/signatures/' . $settings['discipline_signature']) : '';
  ?>
  <img src="<?= $discipline_signature; ?>" id="img_discipline_signature"
       style="width: 100px;height: 100px;border-radius: 50%;background: #FFFFFF;float:left;border: 1px solid #4C5B5C;">
  <input type="file" id="in_discipline_signature" style="display: none;overflow: hidden">
  <div
       style="border: 1px dashed #bababa;float: left;width: calc(100% - 110px);margin-left: 10px;height: 100px;cursor: pointer;text-align: center;"
       id="dv_select_discipline_signature">
    <p style="margin: 30px 0 0;font-weight: 600;">Upload Signature</p>
    <label class="text-muted" style="font-style: italic;font-size: 10pt;">Max 512KB (jpg, jpeg, png)</label>
  </div>
</div>

		<div class="">
			<div id="headingTwo" class="b-radius-0 card-header">
				<button type="button" data-toggle="collapse" data-target="#collapseOne2" aria-expanded="false"
						aria-controls="collapseTwo" class="text-left m-0 p-0 btn btn-link btn-block"><h5
						class="m-0 p-0"><?= lang("app.studentCardOptions"); ?></h5></button>
			</div>
			<div data-parent="#accordion" id="collapseOne2" class="collapse">
				<div class="card-body">
					<div class="col-sm-12 col-md-7 col-lg-6" style="float:left;">
						<div class="form-group">
							<label><?= lang("app.head1"); ?></label>
							<span data-value="<?= $settings['header_text_1']; ?>" data-target="header_text_1"
								  class="spedit">&nbsp;<?= $settings['header_text_1']; ?></span>
						</div>
						<div class="form-group">
							<label><?= lang("app.head2"); ?></label>
							<span data-value="<?= $settings['header_text_2']; ?>" data-target="header_text_2"
								  class="spedit">&nbsp;<?= $settings['header_text_2']; ?></span>
						</div>
						<div class="form-group">
							<label><?= lang("app.headColor"); ?></label>
							<input data-value="<?= $settings['header_color']; ?>" data-type="color" data-target="header_color"
								   class="spedit" value="<?= $settings['header_color']; ?>">
						</div>
						<div class="form-group">
							<label><?= lang("app.mainColor"); ?></label>
							<input data-value="<?= $settings['main_color']; ?>" data-type="color" data-target="main_color"
								   class="spedit" value="<?= $settings['main_color']; ?>">
						</div>
						<div class="form-group">
							<label><?= lang("app.footerColor"); ?></label>
							<input data-value="<?= $settings['footer_color']; ?>" data-type="color" data-target="footer_color"
								  class="spedit" value="<?= $settings['footer_color']; ?>">
						</div>
						<div class="form-group">
							<label><?= lang("app.capitals"); ?></label>
							<span data-value="<?= $settings['capitalize']; ?>" data-type="status"
								  data-target="capitalize"
								  class="spedit">&nbsp;<?= $settings['capitalize'] == 1 ? "<span class='text-success'>".lang("app.enabled")."</span>"
										: "<span class='text-danger'>".lang("app.disabled")."</span>"; ?></span>
						</div>
					</div>
					<div class="col-sm-12 col-md-5 col-lg-6" style="float:left;">
						<div class="boxed" style="background-color: white;display: flow-root;">
							<h4><?= lang("app.studentCardsBackground"); ?></h4>
							<?php
							$bk = strlen($settings['card_background']) > 4 ? base_url('assets/images/background/' . $settings['card_background']) : '';
							?>
							<img src="<?= $bk; ?>" id="img_backg"
								 style="width: 150px;height: 100px;border-radius: 5px;background: #FFFFFF;float:left;border: 1px solid #4C5B5C;">
							<input type="file" class="in_card_backg" style="display: none;overflow: hidden" data-href="card_background" data-imageview="#img_backg" data-target="#dv_select_img_backg">
							<div
								style="border: 1px dashed #bababa;float: left;width: calc(100% - 160px);margin-left: 10px;height: 100px;cursor: pointer;text-align: center;"
								id="dv_select_img_backg" class="dv_select_img_backg">
								<p style="margin: 30px 0 0;font-weight: 600;"><?= lang("app.uploadBackground"); ?></p>
								<label class="text-muted" style="font-style: italic;font-size: 10pt;"><?= lang("app.totalSize"); ?></label>
							</div>
							<?php
								if(strlen($settings['card_background'])>5) {
									?>
									<span class="lnk text-danger" id="clr_bg" style="font-weight: 500"><i class="fa fa-times"> </i> <?= lang("app.clearBackground"); ?></span>
									<?php
								}
							?>
						</div>
					</div>

					<div class="col-sm-12 col-md-5 col-lg-6" style="float:left;">
						<div class="boxed" style="background-color: white;display: flow-root;">
							<h4><?= lang("app.staffCardsBackground"); ?></h4>
							<?php
							$bk = strlen($settings['sf_card_background']) > 4 ? base_url('assets/images/background/' . $settings['sf_card_background']) : '';
							?>
							<img src="<?= $bk; ?>" id="img_backg_sf"
								 style="width: 110px;height: 150px;border-radius: 5px;background: #FFFFFF;float:left;border: 1px solid #4C5B5C;">
							<input type="file" class="in_card_backg" style="display: none;overflow: hidden" data-href="sf_card_background" data-imageview="#img_backg_sf" data-target="#dv_select_img_backg_sf">
							<div
									style="border: 1px dashed #bababa;float: left;width: calc(100% - 160px);margin-left: 10px;height: 100px;cursor: pointer;text-align: center;"
									id="dv_select_img_backg_sf" class="dv_select_img_backg">
								<p style="margin: 30px 0 0;font-weight: 600;"><?= lang("app.uploadBackground"); ?></p>
								<label class="text-muted" style="font-style: italic;font-size: 10pt;"><?= lang("app.totalSize"); ?></label>
							</div>
							<?php
							if(strlen($settings['sf_card_background'])>5) {
								?>
								<span class="lnk text-danger" id="clr_bg_sf" style="font-weight: 500;margin: 20px 0 0 10px;;display: inline-block;"><i class="fa fa-times"> </i> <?= lang("app.clearBackground"); ?></span>
								<?php
							}
							?>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="">
			<div id="headingTwo" class="b-radius-0 card-header">
				<button type="button" data-toggle="collapse" data-target="#collapseOne3" aria-expanded="false"
						aria-controls="collapseTwo" class="text-left m-0 p-0 btn btn-link btn-block"><h5
						class="m-0 p-0"><?= lang("app.gradeSetting"); ?></h5></button>
			</div>
			<div data-parent="#accordion" id="collapseOne3" class="collapse">
				<form method="POST" action="<?=base_url('manipulate_grade');?>" class="validate autoSubmit">
				<div class="card-body">
					<div class="col-sm-12 col-md-6 col-lg-5" style="float:left;">

						<div class="form-group">
							<label><?= lang("app.colorTitle"); ?></label>
							<input class="form-control" type="text" name="color_title">
						</div>
						<div class="form-group">
							<label><?= lang("app.maxPoint"); ?></label>
							<input class="form-control" type="text" name="max_point">
						</div>
						<div class="form-group">
							<label><?= lang("app.minPoint"); ?></label>
							<input class="form-control" type="text" name="min_point">
						</div>
						<div class="form-group">
							<select  class="select2" name="faculite">
								<option disabled selected><?= lang("app.selectFaculty"); ?></option>
								<?php
								foreach ($faculities as $fac) {
									?>
									<option value="<?= $fac['id']; ?>"><?= $fac['title']; ?></option>
									<?php
								}
								?>
								?>
							</select>
						</div>
						<div class="form-group">
							<label><?= lang("app.chooseColor"); ?></label>
							<input type="text" id="custom" name="color">
						</div>
						<div class="form-group">
							<center><button type="submit" class="btn btn-success btn-lg" data-target="reload"><b><?= lang("app.saveChanges"); ?></b></button></center>
						</div>
					</div>
				</form>
					<div class="col-sm-12 col-md-6 col-lg-7" style="float:left;">
						<label><b><?= lang("app.gradeSettingView"); ?></b></label>
						<div  style="background-color: white;display: flow-root;">
							<table width="100%" border="1">
								<tr>
									<th><?= lang("app.colorTitle"); ?></th>
									<th><?= lang("app.maxPoint"); ?></th>
									<th><?= lang("app.minPoint"); ?></th>
									<th><?= lang("app.faculty"); ?></th>
									<th><?= lang("app.color"); ?></th>
									<th></th>
								</tr>
								<?php foreach ($colors as $color){?>
								<tr>
									<td><?=$color['color_title'];?></td>
									<td><?=$color['max_point'];?></td>
									<td><?=$color['min_point'];?></td>
									<td><?=$color['title'];?></td>
									<td style="background-color: <?=$color['color'];?>"></td>
									<td><center>
											<a class="btn btn-danger" data-toggle="modal" data-target="#DeleteGradeModal" data-id="<?=$color['id'];?>">
												<i class="fa fa-trash" style="color: white"></i>
											</a>
										</center>
									</td>
								</tr>
								<?php
								}
								?>
							</table>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="">
			<div id="headingTwo" class="b-radius-0 card-header">
				<button type="button" data-toggle="collapse" data-target="#collapseOne4" aria-expanded="false"
						aria-controls="collapseTwo" class="text-left m-0 p-0 btn btn-link btn-block"><h5
						class="m-0 p-0"><?= lang("app.intouchSetting"); ?></h5></button>
			</div>
			<div data-parent="#accordion" id="collapseOne4" class="collapse">
				<form method="POST" action="<?=base_url('manipulate_intouch');?>/<?= $intouch_info['school_id'] ?>" class="validate autoSubmit">
					<div class="card-body">
						<div class="col-sm-12 col-md-6 col-lg-5">

							<div class="form-group">
								<label><?= lang("app.titleUsername"); ?></label>
								<input class="form-control" type="text" name="intouch_username" value="<?= $intouch_info['username'] ?>">
							</div>
							<div class="form-group">
								<label><?= lang("app.password"); ?></label>
								<input class="form-control" type="password" name="intouch_password" value="<?= $intouch_info['password'] ?>">
							</div>
							<div class="form-group">
								<center><button type="submit" class="btn btn-success btn-lg" data-target="reload"><b><?= lang("app.saveChanges"); ?></b></button></center>
							</div>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>

</div>
<script>
  window.base_url = "<?= base_url(); ?>";
</script>

<script>
	$(function () {
		var sp, value, old_data, target, type = null;
		$("[data-type='color']").spectrum({preferredFormat: "hex"});
		$("[data-type='color']").on("change",function () {
			var sptxt = $(this);
			var id = $("#settings_section").data("id");
			var val = sptxt.val();
			var targ = sptxt.data("target");
				$.post("<?=base_url('manipulate_settings/');?>color" , "id=" + id + "&target=" + targ + "&val=" + val, function (data) {
					if (data.hasOwnProperty("error")) {
						toastada.error('<?= lang("app.saveFail");?>' + data.msg);
					} else if (data.hasOwnProperty("success")) {
						toastada.success('<?= lang("app.saveSuccess");?>');
					} else {
						//unknown error
						toastada.error('<?= lang("app.fatalErr"); ?>');
					}
				}).fail(function () {
					//unknown error
					toastada.error('<?= lang("app.systemErr"); ?>');
				});

		});
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
			var id = $("#settings_section").data("id");
			var val = sptxt.val();
			if (e.which == 13 || e.type == 'focusout') {
				//enter is pressed
				if (val == value) {
					//no changes made, cancel
					sp.html(old_data);
					return;
				}
				$.post("<?=base_url('manipulate_settings/');?>" + type, "id=" + id + "&target=" + target + "&val=" + val, function (data) {
					if (data.hasOwnProperty("error")) {
						toastada.error('<?= lang("app.saveFail");?>' + data.msg);
					} else if (data.hasOwnProperty("success")) {
						sp.html(data.result);
						sp.data("value", val);
						toastada.success('<?= lang("app.saveSuccess");?>');
					} else {
						//unknown error
						toastada.error('<?= lang("app.fatalErr"); ?>');
					}
				}).fail(function () {
					//unknown error
					toastada.error('<?= lang("app.systemErr"); ?>');
				});
			}
			if (e.which == 27) {
				//escape is pressed
				sp.html(old_data);
			}
		});
		$(document).on("change", ".spchk", function (e) {
			var spchk = $(this);
			var id = $("#settings_section").data("id");
			var val = spchk.is(":checked") ? 1 : 0;
			$.post("<?=base_url('manipulate_settings/');?>" + type, "id=" + id + "&target=" + target + "&val=" + val, function (data) {
				if (data.hasOwnProperty("error")) {
					toastada.error('<?= lang("app.saveFail"); ?>' + data.msg);
				} else if (data.hasOwnProperty("success")) {
					sp.html(data.result);
					sp.data("value", val);
					toastada.success('<?= lang("app.saveSuccess"); ?>');
				} else {
					//unknown error
					toastada.error('<?= lang("app.fatalErr"); ?>');
				}
			}).fail(function () {
				//unknown error
				toastada.error('<?= lang("app.systemErr"); ?>');
			});
		});
		$(document).on("click", "#dv_select_img", function () {
			$("#in_school_logo")[0].click();
		});
		$("#in_school_logo").on("change", function (e) {
			var file = $(this)[0].files[0];
			var upload = new Upload(file);
			// maby check size or type here with upload.getSize() and upload.getType()
			if (upload.getType() != "image/jpg" && upload.getType() != "image/jpeg" && upload.getType() != "image/png") {
				toastada.error('<?= lang("app.allowedOnly"); ?>')
				return;
			}
			if (upload.getSize() > 512 * 1024) {
				toastada.error('<?= lang("app.sizeNeeded"); ?>');
				return;
			}
			// execute upload
			$("#img_logo").prop("src", upload.getSource());
			upload.doUpload("upload_image/school_logo", $("#dv_select_img p"), $("#img_logo"));
		});
		$(document).on("click", "#dv_select_headmaster_signature", function () {
			$("#in_headmaster_signature")[0].click();
		});
		$("#in_headmaster_signature").on("change", function (e) {
			var file = $(this)[0].files[0];
			var upload = new Upload(file);
			// maby check size or type here with upload.getSize() and upload.getType()
			if (upload.getType() != "image/jpg" && upload.getType() != "image/jpeg" && upload.getType() != "image/png") {
				toastada.error('<?= lang("app.allowedOnly"); ?>')
				return;
			}
			if (upload.getSize() > 512 * 1024) {
				toastada.error('<?= lang("app.sizeNeeded"); ?>');
				return;
			}
			// execute upload
			$("#img_headmaster_signature").prop("src", upload.getSource());
			upload.doUpload("upload_image/headmaster_signature", $("#dv_select_headmaster_signature p"), $("#img_headmaster_signature"));
		});
// === MATRON SIGNATURE ===
$(document).on("click", "#dv_select_matron_signature", function () {
  $("#in_matron_signature")[0].click();
});
$("#in_matron_signature").on("change", function () {
  var file = this.files[0];
  var upload = new Upload(file);
  if (upload.getType() != "image/jpg" && upload.getType() != "image/jpeg" && upload.getType() != "image/png") {
    toastada.error('<?= lang("app.allowedOnly"); ?>');
    return;
  }
  if (upload.getSize() > 512 * 1024) {
    toastada.error('<?= lang("app.sizeNeeded"); ?>');
    return;
  }
  $("#img_matron_signature").prop("src", upload.getSource());
  upload.doUpload("upload_image/matron_signature", $("#dv_select_matron_signature p"), $("#img_matron_signature"));
});

// === PATRON SIGNATURE ===
$(document).on("click", "#dv_select_patron_signature", function () {
  $("#in_patron_signature")[0].click();
});
$("#in_patron_signature").on("change", function () {
  var file = this.files[0];
  var upload = new Upload(file);
  if (upload.getType() != "image/jpg" && upload.getType() != "image/jpeg" && upload.getType() != "image/png") {
    toastada.error('<?= lang("app.allowedOnly"); ?>');
    return;
  }
  if (upload.getSize() > 512 * 1024) {
    toastada.error('<?= lang("app.sizeNeeded"); ?>');
    return;
  }
  $("#img_patron_signature").prop("src", upload.getSource());
  upload.doUpload("upload_image/patron_signature", $("#dv_select_patron_signature p"), $("#img_patron_signature"));
});

// === HEAD OF DISCIPLINE SIGNATURE ===
$(document).on("click", "#dv_select_discipline_signature", function () {
  $("#in_discipline_signature")[0].click();
});
$("#in_discipline_signature").on("change", function () {
  var file = this.files[0];
  var upload = new Upload(file);
  if (upload.getType() != "image/jpg" && upload.getType() != "image/jpeg" && upload.getType() != "image/png") {
    toastada.error('<?= lang("app.allowedOnly"); ?>');
    return;
  }
  if (upload.getSize() > 512 * 1024) {
    toastada.error('<?= lang("app.sizeNeeded"); ?>');
    return;
  }
  $("#img_discipline_signature").prop("src", upload.getSource());
  upload.doUpload("upload_image/discipline_signature", $("#dv_select_discipline_signature p"), $("#img_discipline_signature"));
});

		$(document).on("click", ".dv_select_img_backg", function () {
			$(this).parent().find(".in_card_backg").click();
		});
		$(".in_card_backg").on("change", function (e) {
			var file = $(this)[0].files[0];
			var upload = new Upload(file);
			// maby check size or type here with upload.getSize() and upload.getType()
			if (upload.getType() != "image/jpg" && upload.getType() != "image/jpeg" && upload.getType() != "image/png") {
				toastada.error('<?= lang("app.allowedOnly"); ?>')
				return;
			}
			if (upload.getSize() > 512 * 1024) {
				toastada.error('<?= lang("app.sizeNeeded"); ?>');
				return;
			}
			// execute upload
			$($(this).data('imageview')).prop("src", upload.getSource());
			upload.doUpload("upload_image/"+$(this).data("href"), $($(this).data('target')+" p"), $($(this).data('imageview')),"card background");
		});
		$(document).on("click","#clr_bg",function () {
			if (!confirm('<?= lang("app.removal"); ?>'))
				return;
			var id = $("#settings_section").data("id");
			$.post("<?=base_url('manipulate_settings/');?>text", "id=" + id + "&target=card_background&val=", function (data) {
				if (data.hasOwnProperty("error")) {
					toastada.error('<?= lang("app.removalFail");?>' + data.msg);
				} else if (data.hasOwnProperty("success")) {
					$("#img_backg").prop("src","");
					$("#clr_bg").hide();
					toastada.success('<?=lang("app.removalSuccess"); ?>');
				} else {
					//unknown error
					toastada.error('<?= lang("app.fatalErr"); ?>');
				}
			}).fail(function () {
				//unknown error
				toastada.error('<?= lang("app.systemErr"); ?>');
			});
		});
		$(document).on("click","#clr_bg_sf",function () {
			if (!confirm('<?= lang("app.removal"); ?>'))
				return;
			var id = $("#settings_section").data("id");
			$.post("<?=base_url('manipulate_settings/');?>text", "id=" + id + "&target=sf_card_background&val=", function (data) {
				if (data.hasOwnProperty("error")) {
					toastada.error('<?= lang("app.removalFail");?>' + data.msg);
				} else if (data.hasOwnProperty("success")) {
					$("#img_backg_sf").prop("src","");
					$("#clr_bg_sf").hide();
					toastada.success('<?=lang("app.removalSuccess"); ?>');
				} else {
					//unknown error
					toastada.error('<?= lang("app.fatalErr"); ?>');
				}
			}).fail(function () {
				//unknown error
				toastada.error('<?= lang("app.systemErr"); ?>');
			});
		});
		$(document).on("click","#btn-remove-signature",function () {
			if (!confirm('<?= lang("app.removalSignature"); ?>'))
				return;
			var id = $("#settings_section").data("id");
			$.post("<?=base_url('manipulate_settings/');?>text", "id=" + id + "&target=headmaster_signature&val=", function (data) {
				if (data.hasOwnProperty("error")) {
					toastada.error('<?= lang("app.removalSignatureFail");?>' + data.msg);
				} else if (data.hasOwnProperty("success")) {
					$("#img_headmaster_signature").prop("src","");
					$("#btn-remove-signature").hide();
					toastada.success('<?=lang("app.removalSignatureSuccess"); ?>');
				} else {
					//unknown error
					toastada.error('<?= lang("app.fatalErr"); ?>');
				}
			}).fail(function () {
				//unknown error
				toastada.error('<?= lang("app.systemErr"); ?>');
			});
		});
	});
// Remove Matron Signature
$(document).on("click","#btn-remove-matron",function () {
  if (!confirm('<?= lang("app.removalSignature"); ?>')) return;
  var id = $("#settings_section").data("id");
  $.post("<?=base_url('manipulate_settings/text'); ?>", "id=" + id + "&target=matron_signature&val=", function (data) {
    if (data.hasOwnProperty("success")) {
      $("#img_matron_signature").prop("src","");
      $("#btn-remove-matron").hide();
      toastada.success('Matron signature removed');
    }
  });
});

// Remove Patron Signature
$(document).on("click","#btn-remove-patron",function () {
  if (!confirm('<?= lang("app.removalSignature"); ?>')) return;
  var id = $("#settings_section").data("id");
  $.post("<?=base_url('manipulate_settings/text'); ?>", "id=" + id + "&target=patron_signature&val=", function (data) {
    if (data.hasOwnProperty("success")) {
      $("#img_patron_signature").prop("src","");
      $("#btn-remove-patron").hide();
      toastada.success('Patron signature removed');
    }
  });
});

// Remove Discipline Signature
$(document).on("click","#btn-remove-discipline",function () {
  if (!confirm('<?= lang("app.removalSignature"); ?>')) return;
  var id = $("#settings_section").data("id");
  $.post("<?=base_url('manipulate_settings/text'); ?>", "id=" + id + "&target=discipline_signature&val=", function (data) {
    if (data.hasOwnProperty("success")) {
      $("#img_discipline_signature").prop("src","");
      $("#btn-remove-discipline").hide();
      toastada.success('Discipline signature removed');
    }
  });
});

	var Upload = function (file) {
		this.file = file;
	};

	Upload.prototype.getType = function () {
		return this.file.type;
	};
	Upload.prototype.getSize = function () {
		return this.file.size;
	};
	Upload.prototype.getName = function () {
		return this.file.name;
	};
	Upload.prototype.getSource = function () {
		return URL.createObjectURL(this.file);
	};
	Upload.prototype.doUpload = function (url, loader, img,txt='logo') {
		var that = this;
		var formData = new FormData();

		// add assoc key values, this will be posts values
		formData.append("file", this.file, this.getName());
		formData.append("upload_file", true);
		loader.text("Uploading...");
		$.ajax({
			type: "POST",
			url: window.base_url + url,
			xhr: function () {
				var myXhr = new window.XMLHttpRequest();
				if (myXhr.upload) {
					myXhr.upload.addEventListener('progress', that.progressHandling, false);
				}
				return myXhr;
			},
			success: function (data) {
				// your callback here
				loader.text('<?= lang("app.upLoad"); ?>'+txt);
				if (data.hasOwnProperty("error")) {
					toastada.error('<?= lang("app.upLoadErr"); ?>' + data.error);
					img.prop("src", "");
				} else if (data.hasOwnProperty("success")) {
					toastada.success(data.success);
				} else {
					toastada.error('<?= lang("app.fatalErr"); ?>');
					img.prop("src", "");
				}
			},
			error: function (error) {
				// handle error
				toastada.error('<?= lang("app.systemErr"); ?>');
				img.prop("src", "");
				loader.text("<?= lang("app.upLoadSucc"); ?>"+txt);
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
 <script>
	 $(function () {
		 $("#custom").spectrum({

		 });
		 $("#custom").on('change',function () {
			var color=$(this).spectrum('get').toHexString();
			 $(this).val(color);
		 })
	 })

 </script>
