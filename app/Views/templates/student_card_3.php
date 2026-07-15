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
		position: relative;float: left;width:420px;margin: 10px 10px 5px 0;display:inline-block;color:<?= $header_color; ?>
	}
	.footer{
		color:<?= $footer_color; ?>
	}
	.body{
		margin-top: 5px;display: inline-block;width: 100%;text-transform: <?=$capital;?>
	}
</style>
<?php
foreach ($students as $student) {
	$mode = $student['studying_mode']==0?"BOARDING":"DAY";
	$phone = strlen(trim($student["ft_phone"])) > 4 ? $student["ft_phone"] : (strlen(trim($student["mt_phone"])) > 4 ? $student["mt_phone"] :
		(strlen(trim($student["gd_phone"])) > 4 ? $student["gd_phone"] : ""));
	?>
	<div style="width: 100%;height:100%;float:left;margin:0;position:relative;<?=$bg;?>;background-size: 100% 100%;background-repeat: no-repeat;">
		<img src="<?= base_url('assets/images/logo/'.$logo); ?>"
			 style="width: 80px;margin: 10px 0 0 20px;display: inline-block;float: left;">
		<div class="header">
			<h5 style="text-align: center;margin: 0 0 5px;font-size: 16pt;font-weight: 600;float:left;width: 100%;display: inline-block"><?= $school_name; ?></h5>
			<label
				style="text-align: center;display: inline-block;width: 100%;float:left;font-size: 12pt"><?= $header1; ?></label>
			<label
				style="text-align: center;display: inline-block;width: 100%;float:left;font-size: 12pt"><?= $header2; ?></label>
			<h4 style="font-size: 13pt;text-align: center;background: <?= $main_color; ?>;color: white;padding: 4px;display: block;float:left;width: 80%;margin-left: 10%;margin-top: 10px">
				STUDENT CARD</h4>
		</div>

		<div class="body">
			<img src="<?= base_url('assets/images/profile/'.$student['photo']); ?>"
				 style="float:left;width: 140px;height: 140px;border-radius: 15px;border: 2px solid <?= $main_color; ?>;margin-left: 20px">

			<div style="float:left;width: 370px;margin-top: 15px">
				<div style="margin-left: 10px">
					<h4 style="width: auto;margin: 0 auto;font-size: 11pt"><span style="color: <?= $main_color; ?>">Names</span>: <?= $student['name']; ?></h4>
					<h4 style="width: auto;margin: 0 auto;"><span style="color: <?= $main_color; ?>">Student ID</span>: <?= $student['regno']; ?></h4>
					<h4 style="width: auto;margin: 0 auto;"><span style="color: <?= $main_color; ?>">Class</span>: <?= $student['class']; ?></h4>
					<h4 style="width: auto;margin: 0 auto;"><span style="color: <?= $main_color; ?>">Date of Birth</span>: <?= $student['dob']; ?></h4>
					<h4 style="width: auto;margin: 0 auto;"><span style="color: <?= $main_color; ?>">Issued on</span>: <?= date('F Y'); ?></h4>
				</div>
			</div>
		</div>
		<div class="footer">
			<label
				style="font-size: 11pt;position:absolute;bottom: 15px;left: 10px;font-size: 14pt;font-weight: 600"><?= $moto; ?></label>
		</div>
	</div>
	<div class="page-break"></div>
	<?php
}
?>
