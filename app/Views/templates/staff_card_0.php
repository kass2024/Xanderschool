<?php
$bg = isset($background) && strlen($background)>3?"background-image:url(".base_url('assets/images/background/'.$background).")":"";
$capital =$capitalize?"uppercase":"unset";
?>
<style>
	html, body {
		margin: 0;
		padding: 0;
	}

	h4 {
		font-size: 11pt;
		margin: 2px 0;
		padding: 3px 5px;
	}

	.page-break {
		height: 0;
		page-break-after: always;
		margin: 0;
		border-top: none;
	}
	.header{
		position: relative;float: left;width:100%;margin: 10px 0 5px;display:inline-block;color:<?= $header_color; ?>
	}
	.footer{
		color:<?= $footer_color; ?>;
		position: absolute;
		bottom: 0;
		left: 0;
		width: 100%;
	}
	.body{
		margin-top: 5px;display: inline-block;width: 100%;text-transform: <?=$capital;?>
	}
</style>
<?php
foreach ($staffs as $staff) {
	$phone = $staff['phone'];
	?>
	<div style="width: 100%;height:100%;float:left;margin:0;position:relative;<?=$bg;?>;background-size: 100% 100%;background-repeat: no-repeat;">
		<div style="width: 170px;height:100px;margin: 30px 0 0 100px;display: inline-block;float: left;">
			<img src="<?= base_url('assets/images/logo/'.$logo); ?>"
			 style="height: 100%;margin: 0 auto;display:block;">
		</div>
		<div class="header">
			<h5 style="text-align: center;margin: 0 0 5px;font-size: 16pt;font-weight: 600;float:left;width: 100%;display: inline-block"><?= $school_name; ?></h5>
<!--			<label-->
<!--				style="text-align: center;display: inline-block;width: 100%;float:left;font-size: 12pt">--><?//= $header1; ?><!--</label>-->

		</div>
		<h4 style="font-size: 13pt;text-align: center;background: <?= $main_color; ?>;color: white;padding: 4px;display: block;float:left;width: 100%;margin-top: 10px">
			STAFF IDENTIFICATION CARD</h4>
		<div class="body">
			<img src="<?= base_url('assets/images/profile/'.$staff['photo']); ?>"
				 style="float:left;width: 140px;height: 140px;border-radius: 15px;border: 2px solid <?= $main_color; ?>;margin-left: 110px">

			<div style="float:left;width: 100%;margin-top: 5px">
				<div style="text-align: justify;margin: 3px 10px">
					<h4 style="width: auto;margin: 0 auto;font-size: 11pt"><span style="color: <?= $main_color; ?>">Names</span>: <?= $staff['fname']. ' '.$staff['lname']; ?></h4>
					<h4 style="width: auto;margin: 0 auto;"><span style="color: <?= $main_color; ?>">Phone</span>: <?= $phone; ?></h4>
					<h4 style="width: auto;margin: 0 auto;"><span style="color: <?= $main_color; ?>">Occupation</span>: <?= $staff['post_title']; ?></h4>
					<h4 style="width: auto;margin: 0 auto;"><span style="color: <?= $main_color; ?>">Issued on</span>: <?= date('Y'); ?></h4>
				</div>
			</div>
		</div>
		<div class="footer">
		<label
			style="font-size: 13pt;text-align: center;background: <?= $main_color; ?>;color: white;padding: 4px;display: block;float:left;width: 100%"><?= $moto; ?></label>
		</div>
	</div>
	<div class="page-break"></div>
	<?php
}
?>
