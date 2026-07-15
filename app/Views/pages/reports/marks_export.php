<style>input {
		border: 0;
		width: 100px
	}

	th, td {
		min-width: 30px;
		padding: 3px 5px;
		border: 1px solid #777777;
	}

	table {
		border-collapse: collapse;
	}
	.pull-right {
		float: right
	}

	.pull-left {
		float: left;
	}
</style>
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
	<h1 style="text-align: center;width: 100%;float: left;border-bottom: 1px solid #000000"><?= lang("app.marksExport"); ?></h1>
	<div style="width: 100%">
		<div style="float:left;width: 100%">
			<div class="col-sm-12">
				<div class="col-md-6 pull-left">
					<span><b><?= lang("app.sClass"); ?> : </b><?= $class; ?></span><br>
					<span><b><?= lang("app.course"); ?> : </b> <?= $course; ?></span><br>
					<span><b><?= lang("app.teacher"); ?> : </b> <?= $teacher; ?></span><br>
				</div>
				<div class="pull-right" style="margin-top: 10px;margin-right: 10px">
					<span><b><?= lang("app.term"); ?> : </b> <?= \App\Controllers\Home::TermToStr($term); ?></span><br>
					<span><b><?= lang("app.academicYear"); ?> : </b> <?= $academic_year; ?></span><br>
					<span><b><?= lang("app.printedOn"); ?> </b> : <?= date("Y-m-d H:i"); ?></span><br>
				</div>
			</div>
		</div>
		<div style="width: auto;float:left;width: 100%;margin-top: 10px;">
			<?=$content;?>
		</div>
	</div>
</div>
