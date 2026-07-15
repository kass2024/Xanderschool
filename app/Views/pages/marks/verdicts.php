<?php
function get_type($type){
	if($type==1){
		return "First Verdict";
	}
	else if($type==2){
		return "Second Verdict";
	}
}
?>
<div class="row">

	<div class="col-sm-12 pull-left" style="margin-left: 13px;" id="mannualUpload">
		<button class="btn btn-success" data-toggle="modal" data-target="#createVerdict"><i class="fa fa-plus"></i> <?= lang("app.newVerdict"); ?></button>
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
						<th><?= lang("app.no"); ?> </th>
						<th><?= lang("app.title"); ?> </th>
						<th><?= lang("app.type"); ?> </th>
						<th></th>
					</tr>
					</thead>
					<tbody>
					<?php $i=1; foreach ($verdicts as $verdict){?>
					<tr>
						<td><?=$i;?></td>
						<td><?=$verdict['title'];?></td>
						<td><?=get_type($verdict['type']);?></td>
						<td><center><button data-id="<?=$verdict['id'];?>" class="btn btn-sm btn-outline-secondary" data-toggle="modal" data-target="#EditVerdict"><?= lang("app.edit"); ?> </button></center></td>
					</tr>
					<?php
						$i++;
					}
					?>
					</tbody>
					<tfoot>
					<tr>
						<th><?= lang("app.no"); ?> </th>
						<th><?= lang("app.title"); ?> </th>
						<th><?= lang("app.type"); ?> </th>
						<th></th>
					</tr>
					</tfoot>
				</table>
			</div>
		</div>
	</div>
</div>
