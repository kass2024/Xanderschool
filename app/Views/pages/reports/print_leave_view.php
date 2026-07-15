<style>
	.layout{
		border: 0px solid #0c0b0f;
		margin: 20px;
		width: 98%
	}

	.section{
		width: 30%;
		margin-left: 250px;
	}
</style>
<link href="<?= base_url(); ?>assets/css/main.css" rel="stylesheet">
<?php $obj=new App\Controllers\Home(); foreach ($leaves as $leave){}?>
<div class="layout" style="background-color: white;padding-right: 15px">
	<div style="width: 100%;">
		<div style="float: left;width:50%">
			<span><b><?= lang("app.republic"); ?></b></span><br>
			<span><b><?= lang("app.ministry"); ?> </b></span><br>
			<span><strong><?= $school_name; ?></strong></span><br>
			<span><b><?= lang("app.mail"); ?> </b> : <?= $school_email; ?></span><br>
			<span><b><?= lang("app.phone"); ?> </b>  : <?= $school_phone; ?></span><br>
		</div>
		<div style="margin-top: 10px;margin-right: 10px;float: right;">
			<div>
				<img src="<?= base_url('assets/images/logo/' . $school_logo); ?>"
					 style="width: 180px"><br>
			</div>
		</div>
		<br>
	</div>
	<h1 style="text-align: center;width: 100%;float: left;border-bottom: 1px solid #000000"><?= lang("app.leaveRequest"); ?> </h1>
<table width="50%" border="1">
	<tr><td colspan="2"><?= lang("app.NamesofEmployee"); ?> <b><?=$leaves['staff'];?></b></td></tr>
	<tr><td colspan="2"><?= lang("app.PhoneNumber"); ?> <b><?=$leaves['staff_phone'];?></b></td></tr>
	<tr><td colspan="2"><?= lang("app.mail"); ?> :<b><?=$leaves['staff_email'];?></b></td></tr>
</table><br>

	<table width="100%" border="1">
		<tr><td colspan="2">
				<b><?= lang("app.wish"); ?> </b>

					<div class="section">
						<?= $obj->get_leave($leaves['type']);?>
						<input type="checkbox" checked></div><br>
				<?php if($leaves['type']==4){?>
				<div><b><?= lang("app.appReason"); ?> </b> <span><?=$leaves['reason'];?></span></div><br>
				<?php }?>
				<div>
					<i><?= lang("app.note"); ?> :</i>
					<p><i<?= lang("app.execess1day"); ?> ></i></p>
					<p><i><?= lang("app.attached"); ?> </i></p>
				</div>

			</td></tr>
	</table><br>
	<table width="100%" border="1">
		<tr><td colspan="2">
				<p><?= lang("app.daysRequest"); ?>  <?=$leaves['days'];?> <?= lang("app.dayss");?></p>
				<div style="width: 50%" class="pull-left"><p><?=lang("app.lDate"); ?> <?=date("d-M-Y",$leaves['fromDate']);?></p></div>
				<div style="width: 50%" class="pull-left"><p> <?= lang("app.to");?>:<?=date("d-M-Y",$leaves['toDate']);?></p></div>
				<p><?= lang("app.addresDuringLeave"); ?> : <?=$leaves['address'];?> </p>
				<div style="width: 50%" class="pull-left"><p><?= lang("app.tel"); ?> :<?=$leaves['staff_phone'];?> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</p></div>
				<div style="width: 50%" class="pull-left"><p><?= lang("app.fax"); ?> :</p></div>
				<div style="width: 50%" class="pull-left"><p><?= lang("app.appSign"); ?>:................ &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</p></div>
				<div style="width: 50%" class="pull-left"><p<?= lang("app.date"); ?> >:<?=date("d-M-Y");?></p></div>
			</td></tr>
	</table><br>
	<table width="100%" border="1" style="background-color: #d6dbdd">
		<tr><td colspan="2"><b><?= lang("app.approvedBy"); ?> : <?=$approver['staff'];?></b></td></tr>
		<tr><td >
				<p><?= lang("app.director"); ?> :..................</p>
				<span><?= lang("app.on"); ?> :..................</span>
			</td>
			<td><span><?= lang("app.signature"); ?> </span></td></tr>
	</table>
</div>


