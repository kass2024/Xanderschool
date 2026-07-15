<?php $obj = new App\Controllers\Home() ?>
<style>
	.terms {
		border: 2px solid #0b0b0b;
		margin-left: 4px;
	}
</style>
<div class="app-inner-layout app-inner-layout-page">
	<div class="app-inner-layout__wrapper">
		<div class="app-inner-layout__content">
			<div class="tab-content">
				<div class="container-fluid">
					<div class="card mb-3">
						<div class="card-header-tab card-header">
							<div
									class="card-header-title font-size-lg text-capitalize font-weight-normal">
								<i class="header-icon typcn typcn-home-outline text-muted opacity-6"> </i><?= $title; ?>
							</div>
							<div class="col-sm-2">
								<select class="form-control select2 " id="academicYearSelect">
									<option selected disabled>--Academic year filter--</option>
									<?php
									foreach ($years as $year):
										echo "<option value='".$year['id']."'>".$year['title']."</option>";
									endforeach;
									?>
								</select>
							</div>
							<div class="btn-actions-pane-right actions-icon-btn">
								<div class="btn-group dropdown">
									<button type="button" data-toggle="dropdown" aria-haspopup="true"
											aria-expanded="false"
											class="btn-icon btn-icon-only btn btn-link"><i
												class="typcn typcn-th-menu-outline" style="font-size: 16pt"></i>
									</button>
									<div tabindex="-1" role="menu" aria-hidden="true"
										 class="dropdown-menu-right rm-pointers dropdown-menu-shadow dropdown-menu-hover-link dropdown-menu">
										<h6 tabindex="-1" class="dropdown-header">
											<?= lang("app.feeMenu"); ?></h6>
										<a type="button" tabindex="0" href="javascript:void" class="dropdown-item"
										   data-toggle="modal" data-target="#mdlfees"><i
													class="typcn typcn-plus"> </i><span><?= lang("app.addNewFee"); ?></span>
										</a>
									</div>
								</div>
							</div>
						</div>
						<div class="card-body">
							<div id="example_wrapper" class="dataTables_wrapper dt-bootstrap4">
								<div class="row">
									<div class="col-sm-12">
										<table style="width: 100%;" id="example"
											   class=" table-hover table-striped table-bordered">
											<thead>
											<tr role="row">
												<th><?= lang("app.level"); ?></th>
												<th><?= lang("app.amount"); ?></th>
												<th><?= lang("app.term"); ?></th>
												<th><?= lang("app.academicYear"); ?></th>
												<th></th>
											</tr>
											</thead>
											<tbody>
											<?php foreach ($fees as $fee) { ?>
												<tr>
													<td><?= $fee['title']; ?> <?= $fee['dept_code']; ?></td>
													<td><?= $fee['amount']; ?></td>
													<td><b class="terms"><?= lang("app.term1"); ?>Term one </b> <b
																class="terms"><?= lang("app.term2"); ?>Term two</b> <b
																class="terms"><?= lang("app.term3"); ?>Term three</b>
													</td>
													<td><?= $fee['academic_year']; ?></td>
													<td style="text-align: center">
														<button data-id="<?= $fee['id']; ?>"
																class="btn btn-danger delButton btn-sm">Delete
														</button>
													</td>
												</tr>
												<?php
											}
											?>
											</tbody>
											<tfoot>
											<tr>
												<th><?= lang("app.level"); ?></th>
												<th><?= lang("app.amount"); ?></th>
												<th><?= lang("app.term"); ?></th>
												<th><?= lang("app.academicYear"); ?></th>
												<th></th>
											</tr>
											</tfoot>
										</table>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
<script>

	$(document).on("change","#academicYearSelect",function (){
		let val=$(this).val()
		window.location.href="<?=base_url('school_fees_management?year=');?>"+val
	})
	$(document).on("click", ".delButton", function () {
		var r = confirm("Are sure you want delete ?")
		if (r == true) {
			var id = $(this).data('id')
			$.ajax({
				url: "<?php echo base_url('deleteSchoolFee') ?>/" + id,
				method: 'POST',
				contentType: false,
				processData: false,
				cache: false,
				async: false,
				success: function (res) {
					console.log(res)
					toastada.success(res.success);
					setTimeout(function () {
						window.location.reload();
					}, 1500);
					return;
				},
				error: function (e) {
					toastada.error(e.responseJSON.error);
				}
			})
		}
	})
</script>
