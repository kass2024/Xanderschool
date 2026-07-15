<div class="row">
	<div class="col-sm-12 pull-left" style="margin-left: 13px;background-color: white">
		<b><?= lang("app.school");?>: <i><?=$school_name;?></i></b><br>
		<b><?= lang("app.studentName");?>: <i><?=$student['names'];?> </i></b><br>
		<b><?= lang("app.regno");?>: <i><?=$student['regno'];?> </i></b><br>
		<b><?= lang("app.sClass");?>: <i><?=$student['level_name'];?> <?=$student['title'];?> <?=$student['code'];?> </i></b><br>
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
						<th><?= lang("app.paidAmount");?></th>
						<th><?= lang("app.paidDate");?></th>
					</tr>
					</thead>
					<tbody>
					<?php
					$i=1;
					foreach ($transports as $trans) {
						?>
						<tr>
							<td><?= $i; ?></td>
							<td><?= $trans['paid_amount']; ?></td>
							<td><?= $trans['created_at']; ?></td>
						</tr>
						<?php
						$i++;
					}
					?>
					</tbody>
					<tfoot>
					<tr>
						<th><?= lang("app.no");?></th>
						<th><?= lang("app.paidAmount");?></th>
						<th><?= lang("app.paidDate");?></th>
					</tr>
					</tfoot>
				</table>
			</div>
		</div>
	</div>
</div>



