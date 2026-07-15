<title>Student Term results</title>
<style>
	.form-group{
		margin-right: 5px;
	}
	th.cbd{
		border: 1px solid #000;
	}
	th.rotate-45 {
		height: 150px;
		width: 50px;
		min-width: 40px;
		max-width: 40px;
		position: relative;
		vertical-align: bottom;
		padding: 0;
		font-size: 13px;
		line-height: 0.8;
	}
	th.rotate-45 label {
		-ms-transform: skew(45deg, 0deg) rotate(315deg);
		-moz-transform: skew(45deg, 0deg) rotate(315deg);
		-webkit-transform: skew(45deg, 0deg) rotate(315deg);
		-o-transform: skew(45deg, 0deg) rotate(315deg);
		transform: skew(45deg, 0deg) rotate(315deg);
		position: absolute;
		bottom: 59px;
		left: -41px;
		display: inline-block;
		width: 170px;
		text-align: left;
		line-height: 15px;
	}
	th.rotate-45 > div {
		position: relative;
		top: 0;
		left: 75px;
		height: 100%;
		-ms-transform: skew(-45deg,0deg);
		-moz-transform: skew(-45deg,0deg);
		-webkit-transform: skew(-45deg,0deg);
		-o-transform: skew(-45deg,0deg);
		transform: skew(-45deg,0deg);
		overflow: hidden;
		border-left: 1px solid
		#000;
		border-right: 1px solid
		#000;
		border-top: 1px solid
		#000;
		margin-left: -2px;
	}
	th > label{
		position: absolute;
		bottom: 0;
	}
	th {
		position: relative;
		min-width: 40px;
		border: 0px;
		padding-left: 7px;
		text-align: left;
	}
	td {
		/*min-width: 80px;*/
		padding: 3px;
		border-color: #000;
		border: 1px solid;
	}
	table{
		border-collapse: collapse;
	}
</style>
<div style="width: 100%;padding: 15px">
	<div style="float:left;">
		<span><b>REPUBLIC OF RWANDA</b></span><br>
		<span><b>MINISTRY OF EDUCATION</b></span><br>
		<span><strong><?= $school_name; ?></strong></span><br>
		<span><b>E-mail</b> : <?= $school_email; ?></span><br>
		<span><b>Phone</b>  : <?= $school_phone; ?></span><br>
	</div>
	<div style="margin-top: 10px;margin-right: 30px;float: right">
		<div>
			<img src="<?= base_url('assets/images/logo/' . $school_logo); ?>" style="width: 110px"><br>
		</div>
	</div>
	<br>
	<h2 style="text-decoration: underline;width: 100%;float: left;text-align: center;">Student <?=termToStr($term);?> result from <?=$class;?></h2>
</div>
<div style="width: 100%;float: left;margin-top: 10px">
<?=$content;?>
</div>
<div style="position: absolute;bottom: -22px;right:10px">
	<span><b>Printed on </b> : <?= date("Y-m-d H:i"); ?></span>
</div>
