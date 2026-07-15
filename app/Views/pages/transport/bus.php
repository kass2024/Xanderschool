<div class="row">
	<div class="col-sm-12 pull-left" style="margin-left: 13px;">
		<button class="btn btn-success" data-toggle="modal" data-target="#createBus"><i class="fa fa-plus"></i> <?= lang("app."); ?></button>
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
						<th><?= lang("app.no"); ?></th>
						<th><?= lang("app.plateNumber"); ?></th>
						<th><?= lang("app.carMaker"); ?></th>
						<th><?= lang("app.carModel"); ?></th>
						<th><?= lang("app.carYear"); ?></th>
						<th><?= lang("app.noofPlaces"); ?></th>
						<th><?= lang("app.driver"); ?></th>
						<th></th>
					</tr>
					</thead>
					<tbody>
					<?php
					$i=1;
					foreach ($bus as $bu) {
						?>
						<tr>
							<td><?= $i; ?></td>
							<td><?= $bu['plate']; ?></td>
							<td><?= $bu['car_maker']; ?></td>
							<td><?= $bu['car_model']; ?></td>
							<td><?= $bu['car_year']; ?></td>
							<td><?= $bu['places']; ?></td>
							<td><?= $bu['driver']; ?>&nbsp;<i class="link fa fa-pencil-alt" data-id="<?=$bu['id'];?>" style="color: black"  data-toggle="modal" data-target="#editBusDriver"></i></td>
							<td><button data-toggle="modal" data-target="#editBus"   data-id="<?=$bu['id'];?>" class="fa fa-pencil-alt btn btn-outline-success btn-sm"></button></td>
						</tr>
						<?php
						$i++;
					}
					?>
					</tbody>
					<tfoot>
					<tr>
						<th><?= lang("app.no"); ?></th>
						<th><?= lang("app.plateNumber"); ?></th>
						<th><?= lang("app.carMaker"); ?></th>
						<th><?= lang("app.carModel"); ?></th>
						<th><?= lang("app.carYear"); ?></th>
						<th><?= lang("app.noofPlaces"); ?></th>
						<th><?= lang("app.driver"); ?></th>
						<th></th>
					</tr>
					</tfoot>
				</table>
			</div>
		</div>
	</div>
</div>


