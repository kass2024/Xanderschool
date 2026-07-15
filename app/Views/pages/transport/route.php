<div class="row">
	<div class="col-sm-12 pull-left" style="margin-left: 13px;">
		<button class="btn btn-success" data-toggle="modal" data-target="#createRoute"><i class="fa fa-plus"></i> <?= lang("app.NewRoute");?></button>
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
						<th><?= lang("app.title");?></th>
						<th><?= lang("app.price");?></th>
						<th><?= lang("app.detail");?></th>
						<th></th>
					</tr>
					</thead>
					<tbody>
					<?php
					$i=1;
					foreach ($routes AS $route) {
						?>
						<tr>
							<td><?= $i; ?></td>
							<td><?= $route['title']; ?></td>
							<td><?= $route['price']; ?></td>
							<td><?= $route['details']; ?></td>
							<td><center><button class="btn btn-sm btn-outline-dark" data-id="<?= $route['id']; ?>" data-toggle="modal" data-target="#editRoute"><i class="fa fa-pencil-alt"></i></button></td>
						</tr>
						<?php
						$i++;
					}
					?>
					</tbody>
					<tfoot>
					<tr>
						<th><?= lang("app.no");?></th>
						<th><?= lang("app.title");?></th>
						<th><?= lang("app.price");?></th>
						<th><?= lang("app.detail");?></th>
						<th></th>
					</tr>
					</tfoot>
				</table>
			</div>
		</div>
	</div>
</div>


