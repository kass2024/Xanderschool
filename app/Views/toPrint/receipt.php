<style>
	* {
		font-family: "Work Sans", sans-serif;
	}
	.mainDiv{
		width: 90%;
		float: left;
		margin: 10px;
		border-bottom: dashed 1px #151515;
		padding:14px 14px 22px 14px;
	}

	table {
		border-collapse: collapse;
	}

	@page {
		margin: 20px 20px 20px 20px !important;
		padding: 0px 0px 0px 0px !important;
	}

	footer {
		position: absolute;
		bottom: 20px;
		left: 20px;
	}

	.info {
		margin-bottom: 8px;
	}

	.row {
		width: 100%;
		float: left;
		margin-top: 10px;
		/*padding: 10px;*/
		/*display: inline-flex;*/
	}

	.col-3 {
		width: 25%;
		float: left;
	}
</style>
<div style="margin: 15px;width: 100%">
	<div id='head'>
		<div class="mainDiv">
			<div>
				<img src='<?= base_url('assets/images/logo/' . $school_logo); ?>' style="width: 70px;">
				<div class="info"><strong>School : </strong><span> <?= $school_name; ?></span></div>
				<div class="info"><strong>Phone : </strong><span> <?= $school_phone; ?></span></div>
				<div class="info"><strong>Email : </strong><span> <?= $school_email; ?></span></div>
			</div>
		</div>
		<div style="text-align: center;margin-top: 10px;font-size:25px;font-weight: 700">CASH DEPOSIT RECEIPT</div>
		<div class="mainDiv">
			<div class="info"><strong>Student names : </strong><span><?= $student['stdnames']; ?></span></div>
			<div class="info"><strong>Class
					: </strong><span><?= $student['level_name'] ?> <?= $student['title'] ?> <?= $student['code'] ?></span>
			</div>
			<div class="info"><strong>Reg no : </strong><span><?= $student['regno']; ?></span></div>
			<div class="info"><strong>Academic year : </strong><span><?= date('Y') - 1; ?> - <?= date('Y'); ?></span>
			</div>
			<div class="info"><strong>Date: </strong><span><?= date('d-m-Y'); ?></span></div>
		</div>

		<div class="mainDiv">
			<?php $i = 1;
			$total = 0;
			foreach ($records as $record) {
				$total = $record['amount'] + $total; ?>
				<div class="row">
					<div class="col-3" style="text-align: left"><?= '<strong>'.$i.'.</strong> '.$record['item'].
						' <span style="border: 1px dotted;padding: 2px;border-radius: 3px">'.paymentModeToString($record['payment_mode']).'</span>'; ?></div>
					<div class="col-3" style="text-align: center"><?= number_format($record['amount']); ?> Rwf</div>
					<div class="col-3" style="text-align: center"><?= lang("app." . termToStr($record['term'])); ?></div>
					<div class="col-3" style="text-align: right"><?= date('d-M-Y', strtotime($record['date'])); ?></div>
				</div>
				<?php
				$i++;
			}
			?>
		</div>
	</div>
	<div style="margin: 0 15px">
		<div style="float: left">Total</div>
		<div style="float: right;font-weight: bold;min-width: 200px;text-align: right;margin-right: 15px"><?= number_format($total); ?> Rwf</div>
	</div>

