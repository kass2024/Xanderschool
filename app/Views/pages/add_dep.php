<link rel="stylesheet" type="text/css" href="<?=base_url('assets/plugins/select2/css/select2.min.css');?>" >
<script type="application/javascript" src="<?=base_url('assets/plugins/select2/js/select2.min.js');?>" ></script>
<script type="application/javascript" src="<?=base_url('assets/js/parsley.min.js');?>" ></script>
<div class="app-inner-layout app-inner-layout-page">
	<div class="app-inner-layout__wrapper">
		<div class="app-inner-layout__content">
			<form action="<?=base_url('manipulate_dept');?>" class="validate autoSubmit" style="width: 70%;min-width: 500px">
				<div class="form-group col-sm-6" style="float:left;">
					<label><?= lang("app.title");?></label>
					<input class="form-control" type="text" name="title" required minlength="3">
				</div>
				<div class="form-group col-sm-6" style="float:left;">
					<label><?= lang("app.code");?></label>
					<input class="form-control" type="text" name="code" required minlength="2">
				</div>

				<hr style="width: 100%;float: left" />
				<div>
					<button type="submit" class="btn btn-success"><?= lang("app.saveDepartment");?></button>
					<button type="reset" class="btn btn-light"><?= lang("app.cancel");?></button>
				</div>
			</form>
		</div>
	</div>
</div>
<script>
	$(function () {
		$(".validate").parsley();
		$(".select2").select2();
	})
</script>
