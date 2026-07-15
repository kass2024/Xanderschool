<?php if ($type==1) { ?>
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
							<th><?= lang("app.student");?></th>
							<th><?= lang("app.sClass");?></th>
							<th><?= lang("app.expectedAmount");?></th>
							<th><?= lang("app.paidAmount");?></th>
						</tr>
						</thead>
						<tbody>
						<?php $i = 1;
						foreach ($schoolfees as $book) {
							if ($book['expected'] == $book['paid']) {

								?>

								<tr>
									<td><?= $i; ?></td>
									<td><?= $book['student']; ?></td>
									<td><?= $book['level_name']; ?> <?= $book['code']; ?> <?= $book['title']; ?></td>
									<td><?= $book['expected']; ?></td>
									<td><?= $book['paid']; ?></td>
								</tr>
								<?php
								$i++;
							}
						}
						?>
						</tbody>
						<tfoot>
						<tr>
							<th><?= lang("app.no");?></th>
							<th><?= lang("app.student");?></th>
							<th><?= lang("app.sClass");?></th>
							<th><?= lang("app.expectedAmount");?></th>
							<th><?= lang("app.paidAmount");?></th>
						</tr>
						</tfoot>
					</table>
				</div>
			</div>
		</div>
	</div>
	<?php
}
?>
<?php if ($type==2) { ?>
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
							<th><?= lang("app.student");?></th>
							<th><?= lang("app.sClass");?></th>
							<th><?= lang("app.expectedAmount");?></th>
							<th><?= lang("app.paidAmount");?></th>
							<th><?= lang("app.remainAmount");?></th>
						</tr>
						</thead>
						<tbody>
						<?php $i = 1;
						foreach ($schoolfees as $book) {
							if ($book['paid']!=$book['expected'] and $book['paid']!="") {

								?>

								<tr>
									<td><?= $i; ?></td>
									<td><?= $book['student']; ?></td>
									<td><?= $book['level_name']; ?> <?= $book['code']; ?> <?= $book['title']; ?></td>
									<td><?= $book['expected']; ?></td>
									<td><?= $book['paid']; ?></td>
									<td><?= $book['expected']-$book['paid']; ?></td>
								</tr>
								<?php
								$i++;
							}
						}
						?>
						</tbody>
						<tfoot>
						<tr>
							<th><?= lang("app.no");?></th>
							<th><?= lang("app.student");?></th>
							<th><?= lang("app.sClass");?></th>
							<th><?= lang("app.expectedAmount");?></th>
							<th><?= lang("app.paidAmount");?></th>
							<th><?= lang("app.remainAmount");?></th>
						</tr>
						</tfoot>
					</table>
				</div>
			</div>
		</div>
	</div>
	<?php
}
?>
<?php if ($type==3) { ?>
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
							<th><?= lang("app.student");?></th>
							<th><?= lang("app.sClass");?></th>
							<th><?= lang("app.expectedAmount");?></th>
							<th><?= lang("app.paidAmount");?></th>
							<th><?= lang("app.remainAmount");?></th>
						</tr>
						</thead>
						<tbody>
						<?php $i = 1;
						foreach ($schoolfees as $book) {
							if ($book['paid']=="") {

								?>

								<tr>
									<td><?= $i; ?></td>
									<td><?= $book['student']; ?></td>
									<td><?= $book['level_name']; ?> <?= $book['code']; ?> <?= $book['title']; ?></td>
									<td><?= $book['expected']; ?></td>
									<td><?= $book['paid']; ?></td>
									<td><?= $book['expected']-$book['paid']; ?></td>
								</tr>
								<?php
								$i++;
							}
						}
						?>
						</tbody>
						<tfoot>
						<tr>
							<th><?= lang("app.no");?></th>
							<th><?= lang("app.student");?></th>
							<th><?= lang("app.sClass");?></th>
							<th><?= lang("app.expectedAmount");?></th>
							<th><?= lang("app.paidAmount");?></th>
							<th><?= lang("app.remainAmount");?></th>
						</tr>
						</tfoot>
					</table>
				</div>
			</div>
		</div>
	</div>
	<?php
}
?>
