<link rel="stylesheet" type="text/css" href="<?= base_url('assets/plugins/dropzone/dropzone.min.css'); ?>">
<style>
	.dropzone {
		border: 2px dashed rgba(0,0,0,0.3);
		margin: 16px;
	}
	.dropzone .dz-preview .dz-error-message::after {
		border-bottom: 6px solid #ec2e51;
	}
	.dropzone .dz-preview .dz-error-message {
		opacity: 1;
		top: 93px;
		left: -10px;
		width: 140px;
		background: #be2626;
		border-radius: 4px;
		background: linear-gradient(to bottom, #f7587d, #5b0707);
		text-align: center;
	}
	.dropzone .dz-preview .dz-image {
		border-radius: 4px;
		border: 1px solid;
	}
</style>
<div>
	<form action="<?= base_url('upload_pictures'); ?>" class="dropzone" id="myDropzone">
		<div class="fallback">
			<input name="file" type="file" multiple/>
		</div>
	</form>
</div>
<script src="<?= base_url('assets/plugins/dropzone/dropzone.min.js'); ?>"></script>
<script>
	Dropzone.autoDiscover = false;
	var myDropzone = new Dropzone("#myDropzone", { // Make the whole body a dropzone
		dictDefaultMessage: '<i class="fa fa-mouse-pointer"></i> Select or drag students picture <strong>named as registration number</strong>',
	});

</script>
