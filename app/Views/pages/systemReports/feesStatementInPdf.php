<style>
	.tablepage {
		width: 100%;

	}

	.colscenter {
		text-align: center;
		font-weight: bold;
	}

	.td_date {
		text-align: center;
		line-height: 0.7;
		padding: 5px 0;
	}

	.boxed2 {
		padding: 10px;
		border: 1px solid #333;
		border-radius: 5px;
		height: 99%;
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
	.small-badge{
		border: 1px solid;border-radius: 3px;padding: 2px;font-size: 8pt
	}
</style>
<?php
function getJsTermToString($term){
	if ($term==1){
		return "First term";
		}else if ($term==2){
		return "Second term";
		}else if($term==3){
		return "Third term";
		}else{
		return  $term;
	}
}
?>
<div style="margin-top: 15px;width: 100%;float:left;">
	<div class="col-md-12 col-sm-12 pull-left" style="margin-bottom: 15px;width: 100%">
		<div style="margin-bottom:15px;border: solid 1px black;width: 100%;display: flow-root">
			<div class="pull-left" style="padding:5px;">
				<span><b><?= lang("app.republic"); ?> </b></span><br>
				<span><b><?= lang("app.ministry"); ?> </b></span><br>
				<span><?= $school_name; ?></span><br>
				<img src="<?= base_url('assets/images/logo/' . $school_logo); ?>" style="width: 110px;"
					 alt="School Logo"><br>
				<span><b><?= lang("app.mail"); ?></b> : <?= $school_email; ?></span><br>
				<span><b><?= lang("app.phone"); ?> </b>  : <?= $school_phone; ?></span><br>
			</div>
			<div class="pull-right" style="padding:5px;">
				<span><b>Academic year:</b>  <?=$years['title']; ?></span><br>
				<span><b>Term: </b> <?= getJsTermToString($term); ?></span><br>
				<span><b>Class:  </b> <?php echo $classe['level_name']." ".$classe['dept_code']." ".$classe['title']?></span><br>
				<span><b>Class mentor:</b> <?=$classe['mentor_name'];?></span><br>
				<span><b>Total listed students: </b> <?= count($students); ?></span><br>
			</div>
		</div>
		<div style="background:white;padding-bottom:15px;font-size: 18px">
			<div style="text-align: center"><strong><?= $title ?></strong></div>
		</div>
		<div style="height: 98%">
			<table class="tablepage" border="1">
				<tr>
					<th><?= lang("app.no"); ?></th>
					<th><?= lang("app.regNo"); ?></th>
					<th><?= lang("app.names"); ?></th>
					<th><?= lang("app.mode"); ?></th>
					<th><?= lang("app.gender"); ?></th>
					<th>Expected amount</th>
					<th>Paid amount</th>
					<th>Payment status</th>
				</tr>
				<tbody>
				<?php
				$a = 1;
				function moneyStatement($amount, $paid)
				{
					if ($paid > $amount){
						return "<strong> +".number_format($paid - $amount). "</strong>
								<strong class='small-badge' style='color: #b3970a;'>Overpay</strong>";
					}else if ($paid > 0 && $paid !=$amount){
						return "<strong>".number_format($amount - $paid)."</strong>";
					}else if($paid == $amount){
						return "<strong class='small-badge' style='color: #3ac47d'>Full paid</strong>";
					}else if($paid ==0){
						return "<strong class='small-badge' style='color: #dc3545'>Zero payment</strong>";
					}
				}

				foreach ($students as $student) {
					?>
					<tr>
						<td><?= $a; ?></td>
						<td><?= $student['regno']; ?></td>
						<td><?= $student['student']; ?></td>
						<td><?= \App\Controllers\Home::ModeToStr($student['studying_mode']); ?></td>
						<td><?= $student['sex']; ?></td>
						<td><?= number_format($student['amount']); ?></td>
						<td><?= number_format($student['paid']); ?></td>
						<td><?= moneyStatement($student['amount'], $student['paid']); ?></td>
					</tr>
					<?php
					$a++;
				}
				?>
				</tbody>
			</table>
		</div>
		<!--Table-->
		<div class="col-md-12 col-sm-12 ">
			<footer class="pull-right" style="color: darkgrey"><?= lang("app.printedBy"); ?> </footer>
		</div>
	</div>
</div>
</div>

