<?php
$obj = new \App\Controllers\Home();
?>
<div class="card-body">
	<div id="example_wrapper" class="dataTables_wrapper dt-bootstrap4">
		<div class="row" style="background-color: white">
			<div class="col-sm-12">
				<table style="width: 100%;" id="example"
					   class="table table-hover table-striped table-bordered dataTable dtr-inline"
					   role="grid" aria-describedby="example_info">
					<thead>
					<tr role="row">
						<th><?= lang("app.no"); ?></th>
						<th><?= lang("app.student"); ?></th>
						<th>Type</th>
						<th><?= lang("app.title"); ?></th>
						<th><?= lang("app.author"); ?></th>
						<th><?= lang("app.borrowDate"); ?></th>
						<th><?= lang("app.returnDueDate"); ?></th>
						<th><?= lang("app.returnDate"); ?></th>
						<th><?= lang("app.satus"); ?></th>
						<th></th>
					</tr>
					</thead>
					<tbody>
					<?php $i = 1;
					foreach ($books as $book) { ?>
						<tr>
							<td><?= $i; ?></td>
							<td><?= $book['student']; ?></td>
							<th><?= $book['type']; ?></th>
							<td><?= $book['title']; ?></td>
							<td><?= $book['author']; ?></td>
							<td><?= date('d-m-Y', $book['borrow_date']); ?></td>
							<td><?= date('d-m-Y', $book['return_due_date']); ?></td>
							<td><?= $obj->get_returndate($book['return_date']); ?></td>
							<td><?= $obj->get_status($book['status']); ?></td>
							<td>
								<center>
									<?php if ($book['status'] == 1) { ?>
										<button style="display: none" class="btn btn-sm btn-outline-success"
												data-id="<?= $book['id']; ?>" data-toggle="modal"
												data-target="#borrowBook"><?= lang("app.return"); ?></button>
										<?php
									} else { ?>
										<button class="btn btn-sm btn-outline-success"
												data-id="<?= $book['record_id']; ?>" data-toggle="modal"
												data-target="#returnBook"><?= lang("app.return"); ?></button>
										<?php
									}
									?>
								</center>
							</td>
						</tr>
						<?php
						$i++;
					}
					?>
					</tbody>
					<tfoot>
					<tr>
						<th><?= lang("app.no"); ?></th>
						<th><?= lang("app.student"); ?></th>
						<th>Type</th>
						<th><?= lang("app.title"); ?></th>
						<th><?= lang("app.author"); ?></th>
						<th><?= lang("app.borrowDate"); ?></th>
						<th><?= lang("app.returnDueDate"); ?></th>
						<th><?= lang("app.returnDate"); ?></th>
						<th><?= lang("app.satus"); ?></th>
						<th></th>
					</tr>
					</tfoot>
				</table>
			</div>
		</div>
	</div>
</div>

