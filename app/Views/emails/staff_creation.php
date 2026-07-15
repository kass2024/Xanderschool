<style>
	.boxed {
		background: #cdcdcd;
		border: 1px solid #558d8e;
		border-radius: 5px;
		margin: 5px;
		padding: 5px;
		display: inline-block;
	}

	.mail-footer {
		font-size: 10pt;
		font-style: italic;
		color: #c81717;
	}

	.mail-content {
		border: 2px solid #CDCDCD;
		border-bottom: 3px solid #cd3330;
		padding: 5px;
		width: 80%;
		display: block;
		margin: 10px auto;
	}
</style>
<div class="mail-content">
	<h5><?= lang("app.dear"); ?> Dear, <?= $name; ?></h5>
	<p><?= lang("app.emailContent");?><br>
		<span class="boxed"><?= lang("app.phone"); ?> : <?= $phone; ?></span><br>
		<span class="boxed"><?= lang("app.mail"); ?> : <?= $email; ?></span><br>
		<span class="boxed"><?= lang("app.defaultPassword"); ?> : <?= $default_password; ?></span>
	</p><br>
	<a href="<?=base_url();?>" target="_blank"><?= lang("app.canStart"); ?> </a>
	<br>
	<br>
	<label><?= lang("app.thankyou"); ?> </label>
	<br><br><br>
	<p class="mail-footer"><?= lang("app.notReply"); ?></p>
</div>
<span
	style="text-align: center;color:#8e9b98;font-size: 10pt;display: block;margin-top:5px"><?= lang("app.Copyright"); ?> &copy; <?= date("Y"); ?> BDS Ltd</span>
