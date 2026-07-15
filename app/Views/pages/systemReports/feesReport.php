<style>
	.small-badge{
		border: 1px solid;border-radius: 3px;padding: 2px;font-size: 8pt
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
							<form id="view_students_form" style="width: 100%">
								<div class="form-group col-sm-2" style="margin-top: 18px;display: inline-block">
									<select class="select2" id="choose_class" name="c">
										<option disabled selected><?= lang("app.chooseClass"); ?></option>
										<?php
										foreach ($classes as $classe):
											echo "<option value='{$classe['id']}' ".($classe['id']==$class_id?'selected':'').">{$classe['level_name']} {$classe['dept_code']} {$classe['title']}</option>";
										endforeach;
										?>
									</select>
								</div>
								<div class="form-group col-sm-2" style="margin-top: 18px;display: inline-block">
									<select class="select2" name="academic">
										<option disabled selected><?= lang("app.academicYear"); ?></option>
										<?php
										foreach ($years as $year):
											echo "<option value='{$year['id']}' ".($year['id']==$year_id?'selected':'').">{$year['title']}</option>";
										endforeach;
										?>
									</select>
								</div>
								<div class="form-group col-sm-2" style="margin-top: 18px;display: inline-block">
									<select class="select2"  name="term">
										<option disabled selected><?= lang("app.selectTerm"); ?></option>
										<option value="1" <?=($term=="1"?'selected':'');?> >Term one</option>
										<option value="2" <?=($term=="2"?'selected':'');?> >Term Two</option>
										<option value="3" <?=($term=="3"?'selected':'');?>>Term Three</option>
									</select>
								</div>
								<div class="form-group col-sm-2" style="margin-top: 18px;display: inline-block">
									<select class="select2"  name="filter">
										<option value="0" <?=($filter=="0"?'selected':'');?>>All</option>
										<option value="1" <?=($filter=="1"?'selected':'');?>>Full paid</option>
										<option value="2" <?=($filter=="2"?'selected':'');?>>Partial paid</option>
										<option value="3" <?=($filter=="3"?'selected':'');?>>Zero payment</option>
									</select>
								</div>
								<button type="submit" value="true" class="btn btn-primary">
									<?= lang("app.viewStudents"); ?>
								</button>
							</form>
							<div class="btn-actions-pane-right actions-icon-btn">
								<div class="btn-group dropdown">
									<button type="button" data-toggle="dropdown" aria-haspopup="true"
											aria-expanded="false"
											class="btn-icon btn-icon-only btn btn-link"><i
											class="typcn typcn-th-menu-outline" style="font-size: 16pt"></i></button>
									<div tabindex="-1" role="menu" aria-hidden="true"
										 class="dropdown-menu-right rm-pointers dropdown-menu-shadow dropdown-menu-hover-link dropdown-menu">
										<h6 tabindex="-1" class="dropdown-header">
											<?= lang("app.studentMenu"); ?></h6>
										<a type="button" tabindex="0" href="<?= base_url('register-student'); ?>"
										   class="dropdown-item"><i
												class="typcn typcn-plus"> </i><span><?= lang("app.AddnewStudent"); ?></span>
										</a>
									</div>
								</div>
							</div>
						</div>
						<div class="col-sm-12">
							<?php if (isset($_SESSION['success'])) {
								?>
								<div class="alert alert-success">
									<h5><?= lang("app.success");?></h5>
									<p><?= $_SESSION['success']; ?></p>
								</div>
								<?php
							}
							?>
							<?php if (isset($_SESSION['error'])) {
								?>
								<div class="alert alert-danger">
									<h5><?= lang("app.sError");?></h5>
									<p><?= $_SESSION['error']; ?></p>
								</div>
								<?php
							}
							?>
						</div>
						<?php
						if (count($students) == 0)
							return;
						?>
						<div class="card-body">
							<div id="example_wrapper" class="dataTables_wrapper dt-bootstrap4">
								<div class="row">
									<div class="col-sm-12">
										<div class="col-sm-12">
											<div class="pull-right">
												<?php
												if ($filter != 1){
												?>
												<a href="#" class="btn btn-gradient-info" id="btn-send-fees-sms">
													Send bulk SMS with remaining amount
												</a>
												<?php
												}
												?>
												<a href="<?=base_url('feesReport/1?c='.$_GET['c'].'&academic='.$_GET['academic'].'&term='.$_GET['term'].'&filter='.$_GET['filter']);?>" target="_blank" class="btn btn-danger">
													Export in pdf
												</a>
											</div>
										</div>
										<table style="width: 100%;" id="example"
											   class="table table-hover table-striped table-bordered dataTable dtr-inline"
											   role="grid" aria-describedby="example_info">
											<thead>
											<tr role="row">
												<th><?= lang("app.no"); ?></th>
												<th><?= lang("app.regNo"); ?></th>
												<th><?= lang("app.names"); ?></th>
												<th><?= lang("app.mode"); ?></th>
												<th><?= lang("app.gender"); ?></th>
												<th><?= lang("app.sClass"); ?></th>
												<th>Expected amount</th>
												<th>Paid amount</th>
												<th>Payment status</th>
											</tr>
											</thead>
											<tbody>
											<?php
											$a = 1;
											function moneyStatement($amount,$paid){
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
													<td><a href="<?= base_url('student/' . $student['student_id']); ?>"
														   class="link"><?= $student['regno']; ?></a></td>
													<td><?= $student['student']; ?></td>
													<td><?= \App\Controllers\Home::ModeToStr($student['studying_mode']); ?></td>
													<td><?= $student['sex']; ?></td>
													<td><?= $student['level_name'] . ' ' . $student['dept_code'] . ' ' . $student['class']; ?></td>
													<td><?= number_format($student['amount']); ?></td>
													<td><?= number_format($student['paid']); ?></td>
													<td><?= moneyStatement($student['amount'],$student['paid']); ?></td>
												</tr>
												<?php
												$a++;
											}
											?>
											</tbody>
											<tfoot>
											<tr>
												<th><?= lang("app.no"); ?></th>
												<th><?= lang("app.regNo"); ?></th>
												<th><?= lang("app.names"); ?></th>
												<th><?= lang("app.mode"); ?></th>
												<th><?= lang("app.gender"); ?></th>
												<th><?= lang("app.sClass"); ?></th>
												<th>Expected amount</th>
												<th>Payment status</th>
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
	$(function () {
		$("#btn-send-fees-sms").on('click', function () {
			const btn = $(this);
			btn.val("Please wait...").prop('disabled',true)
			if (confirm("Please confirm bulk SMS send")) {
				$.getJSON("<?=base_url('feesReport/2?c=' . $_GET['c'] . '&academic=' . $_GET['academic'] . '&term=' . $_GET['term'] . '&filter=' . $_GET['filter']);?>", function (data) {
					btn.val("Send bulk SMS with remaining amount").prop('disabled',false)
					if (data.hasOwnProperty('success')) {
						toastada.success(data.success)
					} else {
						toastada.error(data.error)
					}
				})
			}
		});
	});
</script>
