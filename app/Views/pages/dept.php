<link rel="stylesheet" type="text/css" href="<?= base_url('assets/plugins/select2/css/select2.min.css'); ?>">
<script type="application/javascript" src="<?= base_url('assets/plugins/select2/js/select2.min.js'); ?>"></script>
<script type="application/javascript" src="<?= base_url('assets/js/parsley.min.js'); ?>"></script>
<div class="app-inner-layout app-inner-layout-page">
	<div class="app-inner-layout__wrapper">
		<div class="app-inner-layout__content">
			<div>
				<a href="<?= base_url('add_dept'); ?>" class="btn btn-info"><?= lang("app.addDept");?></a>
			</div>
		</div>
	</div>
</div>
<script>
	$(function () {
		$(".validate").parsley();
		$(".select2").select2();
	})
</script>
