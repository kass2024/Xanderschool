<style>
	.tablepage {
		width: 100%;

	}
	.td_date{
		text-align: center;
		line-height: 0.7;
		padding: 5px 0;
	}
</style>
<?php
if ($show_header) {
	?>
	<div class="col-12">
		<div class="col-md-3 pull-left">
			<select class="select2 form-control" id="select_class" name="class">
				<option disabled selected><?= lang("app.selectClass"); ?> </option>
				<?php
				foreach ($classes as $classe):
					echo "<option value='{$classe['id']}'>{$classe['level_name']} {$classe['code']} {$classe['title']}</option>";
				endforeach;
				?>
			</select>
		</div>
		<div class="col-md-3 pull-left">
			<select class="select2 form-control" id="choose_month" name="months">
				<option disabled selected><?= lang("app.chooseMonth"); ?> </option>
				<?php
				$months = get_months();
				for ($a = 0; $a < count($months); $a++):
					echo "<option value='".($a+1)."'>{$months[$a]}</option>";
				endfor;
				?>
			</select>
		</div>
		<div class="col-md-3 pull-left">
			<button class="btn btn-secondary" id="btn_generate"><?= lang("app.generate"); ?> </button>
		</div>
	</div>
	<div id="report_content">

	</div>
	<script>
		$(function () {
			$("#btn_generate").on("click",function () {
				if ($("#select_class").val()==null){
					toastada.warning('<?= lang("app.pleasSelectClass"); ?>');
					return;
				}
				if ($("#choose_month").val()==null){
					toastada.warning('<?= lang("app.pleaseSelectMonth"); ?>');
					return;
				}
				$("#btn_generate").text('<?= lang("app.pleaseWait"); ?>').prop("disabled",true);
				$("#report_content").load("<?=base_url('student_inout_monthly_report_data/false');?>","class="+$("#select_class").val()
					+"&month="+$("#choose_month").val(),function () {
					$("#btn_generate").text('<?= lang("app.generate"); ?>').prop("disabled",false);
				})
			});
		});
	</script>
	<?php
}else {
	?>
	<div style="margin-top: 15px;width: 100%;float:left;" id="printable">
		<div class="col-md-12 col-sm-12 pull-left" style="margin-bottom: 15px">
			<div style="background:white;padding: 10px;overflow: auto;">
				<div class="col-sm-12">
					<div class="col-md-6 pull-left">
						<span><b><?= lang("app.republic"); ?> </b></span><br>
						<span><b><?= lang("app.ministry"); ?> </b></span><br>
						<span><strong><?= $school_name; ?></strong></span><br>
						<span><b><?= lang("app.mail"); ?></b> : <?= $school_email; ?></span><br>
						<span><b><?= lang("app.phone"); ?> </b>  : <?= $school_phone; ?></span><br>
					</div>
					<div class="pull-right" style="margin-top: 10px;margin-right: 10px">
						<div>
							<img src="<?= base_url('assets/images/logo/' . $school_logo); ?>" style="width: 110px"><br>
						</div>
					</div>
					<br>
					<h4 style="text-decoration: underline;width: 100%;float: left;text-align: center;"><?= lang("app.StudentInOutmonthlyReport"); ?> </h4>
				</div>
				<div class="col-sm-12">
					<div class="col-md-6 pull-left">
						<span><b><?= lang("app.sClass"); ?> : </b><?= $classe; ?></span><br>
						<span><b><?= lang("app.month"); ?> : </b><?= $month; ?></span><br>
						<span><b><?= lang("app.totalStudents"); ?> : </b> <?= count($students); ?></span><br>
					</div>
					<div class="pull-right" style="margin-top: 10px;margin-right: 10px">
						<span><b><?= lang("app.printedOn"); ?>  </b> : <?= date("Y-m-d H:i"); ?></span><br>
					</div>
				</div>
				<table class="tablepage" border="1">
					<!--Table head-->
					<tbody id="disciplineTable">
					<tr>
						<td style="text-align: right"><strong>#</strong></td>
						<td><strong><?= lang("app.firstName"); ?> </strong></td>
						<td><strong><?= lang("app.lastName"); ?> </strong></td>
						<?php
						$a = 1;
						$last_day = date("t", strtotime($month."-01"));
						for ($a; $a <= $last_day; $a++) {
							echo "<td style='text-align: center'>" . sprintf("%02d", $a) . "</td>";
						}
						?>
					</tr>
					<?php
					$a1 = 1;
					foreach ($students as $item) {
						?>
						<tr>
							<td style="text-align: right"><?= $a1; ?></td>
							<td><?= $item['fname']; ?></td>
							<td><?= $item['lname']; ?></td>
							<?php
							$a2 = 1;
							for ($a2; $a2 <= $last_day; $a2++) {
								$ins = explode(",", $item['records']);
								$in = "";
								$in_data = "";
								$out = "";
								$out_data = "";
								$searchOut = false;
								foreach ($ins as $in1) {
									if (strlen($in1) == 0)
										continue;
									$datas = explode(";",$in1);
									$in1 = $datas[0];
									$out1 = $datas[1];
									if (($dt = explode(" ", $in1))[0] == $a2) {
										$in = $dt[1] . "<br>";
										$in_data = $dt[1];
										if (strlen($out1)>0){
											$dt1 = explode(" ", $out1);
											$out = "-<br>" . $dt1[1];
										}
										break;
									}
//
//									if (($dt = explode(" ", $in1))[0] == $a2 && !$searchOut) {
//										$in = $dt[1] . "<br>";
//										$in_data = $dt[1];
//										$searchOut = true;
//									}
//									if (($dt = explode(" ", $in1))[0] == $a2 && $searchOut && $out_data < $dt[1] && $in_data != $dt[1])
//										$out = "-<br>" . $dt[1];
//									$out_data = $dt[1];
								}
								echo "<td class='td_date'>$in $out</td>";
							}
							?>
						</tr>
						<?php
						$a1++;
					}
					?>
					</tbody>
					<!--Table body-->
				</table>
				<!--Table-->
				<div class="col-md-12 col-sm-12 ">
					<footer class="pull-right" style="color: darkgrey"><?= lang("app.generatedbySomanet"); ?> </footer>
				</div>
			</div>
		</div>
	</div>
	<?php
}
