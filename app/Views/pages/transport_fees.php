<div class="row">
	<div class="col-sm-12 pull-left" style="margin-left: 13px;">
		<button class="btn btn-success" data-toggle="modal" data-target="#createTransport_fees"><i class="fa fa-plus"></i> <?= lang("app.NewRecord");?></button>
	</div>
</div>
<div class="card-body">
	<div id="example_wrapper" class="dataTables_wrapper dt-bootstrap4">
		<div class="row" style="background-color: white">
			<div class="col-sm-12">
				<table style="width: 100%;" id="example"
					   class="table table-hover table-striped table-bordered dataTable dtr-inline"
					   role="grid" aria-describedby="example_info">
					<thead>
					<tr role="row">
						<th><?= lang("app.no");?></th>
						<th><?= lang("app.regNo");?></th>
						<th><?= lang("app.student");?></th>
						<th><?= lang("app.sClass");?></th>
						<th><?= lang("app.paidAmount");?></th>
						<th><?= lang("app.cardBalance");?></th>
					</tr>
					</thead>
					<tbody>
					<?php
					$i=1;
					foreach ($transports as $trans) {
						?>
						<tr>
							<td><?= $i; ?></td>
							<td><a href="<?=base_url('transport_fees_history/');?><?=$trans['student_id'];?>"  class="link" style="color:#0a66b7;"><b><u><?= $trans['regno']; ?></u></b></a></td>
							<td><?= $trans['student']; ?></td>
							<td><?=$trans['level_name'];?> <?=$trans['title'];?> <?=$trans['code'];?> </td>
							<td><?= $trans['paid_amount']; ?></td>
							<td><?= $trans['transport_money']; ?></td>
						</tr>
						<?php
						$i++;
					}
					?>
					</tbody>
					<tfoot>
					<tr>
						<th><?= lang("app.no");?></th>
						<th><?= lang("app.regNo");?></th>
						<th><?= lang("app.student");?></th>
						<th><?= lang("app.sClass");?></th>
						<th><?= lang("app.paidAmount");?></th>
						<th><?= lang("app.cardBalance");?></th>
					</tr>
					</tfoot>
				</table>
			</div>
		</div>
	</div>
</div>

