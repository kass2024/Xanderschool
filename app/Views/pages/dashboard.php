
<div class="app-inner-layout app-inner-layout-page">
	<div class="app-inner-layout__wrapper">
		<div class="app-inner-layout__content">
			<div class="tab-content">
				<div class="container-fluid">
					<div class="card no-shadow bg-transparent no-border rm-borders mb-3">
						<div class="card">
							<div class="no-gutters row">
								<div class="col-md-12 col-lg-4">
									<ul class="list-group list-group-flush">
										<li class="bg-transparent list-group-item">
											<div class="widget-content p-0">
												<div class="widget-content-outer">
													<div class="widget-content-wrapper">
														<div class="widget-content-left">
															<div class="widget-heading"><?= lang("app.totalStudents"); ?>
															</div>
															<div class="widget-subheading"><?= lang("app.thisYearNumbers"); ?>
															</div>
														</div>
														<div class="widget-content-right">
															<div class="widget-numbers text-success">
																<?=$students; ?>
															</div>
														</div>
													</div>
												</div>
											</div>
										</li>
										<li class="bg-transparent list-group-item">
											<div class="widget-content p-0">
												<div class="widget-content-outer">
													<div class="widget-content-wrapper">
														<div class="widget-content-left">
															<div class="widget-heading"><?= lang("app.parents"); ?></div>
															<div class="widget-subheading"><?= lang("app.totalParents"); ?>
															</div>
														</div>
														<div class="widget-content-right">
															<div class="widget-numbers text-primary">
																<?=$parent; ?>
															</div>
														</div>
													</div>
												</div>
											</div>
										</li>
									</ul>
								</div>
								<div class="col-md-12 col-lg-4">
									<ul class="list-group list-group-flush">
										<li class="bg-transparent list-group-item">
											<div class="widget-content p-0">
												<div class="widget-content-outer">
													<div class="widget-content-wrapper">
														<div class="widget-content-left">
															<div class="widget-heading"><?= lang("app.staffs"); ?></div>
															<div class="widget-subheading"><?= lang("app.allEmployees"); ?>
															</div>
														</div>
														<div class="widget-content-right">
															<div class="widget-numbers text-danger">
																<?=$staff; ?>
															</div>
														</div>
													</div>
												</div>
											</div>
										</li>
										<li class="bg-transparent list-group-item">
											<div class="widget-content p-0">
												<div class="widget-content-outer">
													<div class="widget-content-wrapper">
														<div class="widget-content-left">
															<div class="widget-heading"><?= lang("app.permissionsGiven"); ?>
															</div>
															<div class="widget-subheading"><?= lang("app.totalPermissions"); ?>
															</div>
														</div>
														<div class="widget-content-right">
															<div class="widget-numbers text-warning">
																<?=count($permission);?>
															</div>
														</div>
													</div>
												</div>
											</div>
										</li>
									</ul>
								</div>
								<div class="col-md-12 col-lg-4">
									<ul class="list-group list-group-flush">
										<li class="bg-transparent list-group-item">
											<div class="widget-content p-0">
												<div class="widget-content-outer">
													<div class="widget-content-wrapper">
														<div class="widget-content-left">
															<div class="widget-heading"><?= lang("app.totalEvents"); ?>
															</div>
															<div class="widget-subheading"><?= lang("app.eventsCounts"); ?>
															</div>
														</div>
														<div class="widget-content-right">
															<div class="widget-numbers text-success">
																0
															</div>
														</div>
													</div>
												</div>
											</div>
										</li>
										<li class="bg-transparent list-group-item">
											<div class="widget-content p-0">
												<div class="widget-content-outer">
													<div class="widget-content-wrapper">
														<div class="widget-content-left">
															<div class="widget-heading"><?= lang("app.eSMS"); ?></div>
															<div class="widget-subheading"><?= lang("app.smsTotalUsed"); ?>
															</div>
														</div>
														<div class="widget-content-right">
															<div class="widget-numbers text-primary">
																<?=$sms_usage;?>
															</div>
														</div>
													</div>
												</div>
											</div>
										</li>
									</ul>
								</div>
							</div>
						</div>
					</div>
					<div class="mb-3 card">
						<div class="card-header-tab card-header">
							<div
								class="card-header-title font-size-lg text-capitalize font-weight-normal">
								<?= lang("app.leaveChart"); ?>
							</div>
							<div class="btn-actions-pane-right text-capitalize">
								<?= lang("app.leavesStatus"); ?>
							</div>
						</div>
						<div class="no-gutters row">
							<div class="col-sm-12 col-md-12 col-xl-12">
								<div class="row">
									<!-- DONUT CHART -->
									<div class="col-md-6 col-sm-6 pull-left" style="margin-bottom: 15px;background-color: white">
										<div class="box box-danger">
											<div class="box-header with-border">
												<span class="box-title" style="color: rgba(31, 10, 6, 0.6)">
													</span>
											</div>
											<div class="box-body">
												<div class="chart">
													<canvas id="lineChart" style="height:250px"></canvas>
												</div>
											</div>
										</div>
									</div>
									<div class="col-md-6 col-sm-6 pull-left" style="margin-bottom: 15px;background-color: white">
										<div class="box box-danger">
											<div class="box-body">
												<div id="donut-chart" style="height: 300px; width: 100%;"></div>
											</div>
											<!-- /.box-body -->
										</div>
									</div>
								</div>
								<div class="divider m-0 d-md-none d-sm-block"></div>
							</div>
						</div>

					</div>
					<div class="mb-3 card">
						<div class="card-header-tab card-header">
							<div
								class="card-header-title font-size-lg text-capitalize font-weight-normal">
								<i class="header-icon lnr-charts icon-gradient bg-happy-green"> </i>
								<?= lang("app.financeData"); ?>
							</div>
							<div class="btn-actions-pane-right text-capitalize">

							</div>
						</div>
						<div class="no-gutters row">
							<div class="col-sm-12 col-md-12 col-xl-12">
								<div class="row">
									<!-- DONUT CHART -->
									<?php $full=0; $half=0; $none=0; $schoolFeesDeposit=0; $extrafeesdeposit=0;
									foreach ($schoolfees as $fees){
										$expectedScl=$fees['expected'];
										if($fees['expected']==$fees['paid']) {
											$full++;
										}
										if($fees['paid']!=$fees['expected'] and $fees['paid']!=""){
											$half++;
										}
										if($fees['paid']==""){
											$none++;
										}
									}

									?>
									<?php $extrafull=0; $extrahalf=0; $extranone=0;
									foreach ($extrafees as $fees){
										$expectedExt=$fees['expected'];
										if($fees['expected']==$fees['paid']) {
											$extrafull++;
										}
										if($fees['paid']!=$fees['expected'] and $fees['paid']!=""){
											$extrahalf++;
										}
										if($fees['paid']==""){
											$extranone++;
										}
									}
									foreach ($schoolfeesdeposits as $scldeposit){
										$schoolFeesDeposit=$scldeposit['deposit'];
									}
									foreach ($extrafeesdeposits as $extdeposit){
										$extrafeesdeposit=$extdeposit['depositExt'];
									}
									//						print_r($extrafeesdeposits); die();
									?>
									<div class="col-md-6 col-sm-6 pull-left" style="margin-bottom: 15px;background-color: white">
										<div class="box box-danger">
											<div class="box-header with-border">
												<span class="box-title" style="color: rgba(31, 10, 6, 0.6)">
													<?= lang("app.schoolFeesPayments"); ?></span>
											</div>
											<div class="box-body">
												<canvas id="pieChart" style="height:250px"></canvas>
											</div>
											<i class="fa fa-circle" style="color:#3ac47d !important"></i> <a class="link" href="<?=base_url('school_fees_payments/1');?>"><label><?= lang("app.allPayments"); ?></label></a><br>
											<i class="fa fa-circle" style="color:#f7b924 !important"></i> <a class="link" href="<?=base_url('school_fees_payments/2');?>"><label><?= lang("app.payHalf"); ?></label></a><br>
											<i class="fa fa-circle" style="color:#d92550 !important"></i> <a class="link" href="<?=base_url('school_fees_payments/3');?>"><label><?= lang("app.nonePaymentMade"); ?></label></a>
										</div>
									</div>
									<div class="col-md-6 col-sm-6 pull-left" style="margin-bottom: 15px;background-color: white">
										<div class="box box-danger">
											<div class="box-header with-border">
												<span class="box-title" style="color: rgba(31, 10, 6, 0.6)">
													<?= lang("app.xtraFeesPayments"); ?></span>
											</div>
											<div class="box-body">
												<canvas id="pieChart2" style="height:250px"></canvas>
											</div>
											<i class="fa fa-circle" style="color:#3ac47d !important"></i> <a class="link" href="<?=base_url('extra_fees_payments/1');?>"><label><?= lang("app.allPayments"); ?></label></a><br>
											<i class="fa fa-circle" style="color:#f7b924 !important"></i> <a class="link" href="<?=base_url('extra_fees_payments/2');?>"><label><?= lang("app.payHalf"); ?></label></a><br>
											<i class="fa fa-circle" style="color:#d92550 !important"></i> <a class="link" href="<?=base_url('extra_fees_payments/3');?>"><label><?= lang("app.nonePaymentMade"); ?></label></a>
											<!-- /.box-body -->
										</div>
									</div>
								</div>
								<div class="divider m-0 d-md-none d-sm-block"></div>
							</div>
						</div>

					</div>
					<div class="mb-3 card">
						<div class="card-header-tab card-header">
							<div
								class="card-header-title font-size-lg text-capitalize font-weight-normal">
								<?= lang("app.sMStatus");?>
							</div>
							<div class="btn-actions-pane-right text-capitalize">
								<?= lang("app.sMSent");?>
							</div>
						</div>
						<div class="no-gutters row">
							<div class="col-sm-12 col-md-12 col-xl-12">
								<div class="row">
									<div class="col-md-6 col-sm-6 pull-left" style="margin-bottom: 15px;background-color: white">
										<div class="box box-danger">
											<div class="box-body">
												<div id="donut-chart2" style="height: 300px; width: 100%;"></div>
											</div>
											<!-- /.box-body -->
										</div>
									</div>
									<div class="col-md-6 col-sm-6 pull-left" style="margin-bottom: 15px;background-color: white">
										<div class="box box-danger">
											<div class="box-header with-border">
												<span class="box-title" style="color: rgba(31, 10, 6, 0.6)">
													</span>
											</div>
											<div class="box-body">
												<div class="chart">
													<canvas id="lineChart2" style="height:250px"></canvas>
												</div>
											</div>
										</div>
									</div>
								</div>
								<div class="divider m-0 d-md-none d-sm-block"></div>
							</div>
						</div>

					</div>
					<div class="mb-3 card">
						<div class="card-header-tab card-header">
							<div
								class="card-header-title font-size-lg text-capitalize font-weight-normal">
								<i class="header-icon lnr-charts icon-gradient bg-happy-green"> </i>
								<?= lang("app.activeDue-date");?>
							</div>
							<div class="btn-actions-pane-right text-capitalize">
								<button
									class="btn-wide btn-outline-2x mr-md-2 btn btn-outline-focus btn-sm">
									<?= lang("app.viewAll");?>
								</button>
							</div>
						</div>
						<div class="no-gutters row">
							<div class="col-sm-12 col-md-12 col-xl-12">
								<table style="width: 100%;" id="example"
									   class="table table-hover table-striped table-bordered dataTable dtr-inline"
									   role="grid" aria-describedby="example_info">
									<thead>
									<tr role="row">
										<th>#</th>
										<th><?= lang("app.regno");?></th>
										<th><?= lang("app.names");?></th>
										<th><?= lang("app.sClass");?></th>
										<th><?= lang("app.expected");?></th>
										<th><?= lang("app.paid");?></th>
										<th><?= lang("app.debt");?></th>
										<th><?= lang("app.dueDate");?></th>
									</tr>
									</thead>
									<tbody>
									<?php
									$i=1;
									foreach ($scl_due_dates as $due_date){
									?>
										<tr>
											<td><?=$i;?></td>
											<td><?=$due_date['regno'];?></td>
											<td><?=$due_date['student'];?></td>
											<td><?= $due_date['level_name']; ?> <?= $due_date['code']; ?> <?= $due_date['title']; ?></td>
											<td><?=$due_date['expected'];?></td>
											<td><?=$due_date['paid'];?></td>
											<td><?=$due_date['expected']-$due_date['paid'];?></td>
											<td><?=$due_date['due_date'];?></td>
										</tr>
									<?php
										$i++;
									}
									?>
									</tbody>
									<tfoot>
									<tr>
										<th>#</th>
										<th><?= lang("app.regno");?></th>
										<th><?= lang("app.names");?></th>
										<th><?= lang("app.sClass");?></th>
										<th><?= lang("app.expected");?></th>
										<th><?= lang("app.paid");?></th>
										<th><?= lang("app.debt");?></th>
										<th><?= lang("app.dueDate");?></th>
									</tr>
									</tfoot>
								</table>
								<div class="divider m-0 d-md-none d-sm-block"></div>
							</div>
						</div>

					</div>

					<div class="mb-3 card">
						<div class="card-header-tab card-header">
							<div
								class="card-header-title font-size-lg text-capitalize font-weight-normal">
								Weekly Attendance
							</div>
							<div class="btn-actions-pane-right text-capitalize">
								Grow Rate
							</div>
						</div>
						<div class="no-gutters row">
							<div class="col-sm-12 col-md-12 col-xl-12">
								<div class="row">
									<div class="col-md-6 col-sm-6 pull-left" style="margin-bottom: 15px;background-color: white">
										<div class="box box-danger">
											<div class="box-body">
												<div id="donut-chart3" style="height: 300px; width: 100%;"></div>
											</div>
											<!-- /.box-body -->
										</div>
									</div>
									<div class="col-md-6 col-sm-6 pull-left" style="margin-bottom: 15px;background-color: white">
										<div class="box box-danger">
											<div class="box-header with-border">
												<span class="box-title" style="color: rgba(31, 10, 6, 0.6)">
													</span>
											</div>
											<div class="box-body">
												<div class="chart">
													<canvas id="lineChart3" style="height:250px"></canvas>
												</div>
											</div>
										</div>
									</div>
								</div>
								<div class="divider m-0 d-md-none d-sm-block"></div>
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

		// Get context with jQuery - using jQuery's .get() method.
		var pieChartCanvas = $('#pieChart').get(0).getContext('2d')
		var pieChartCanvas2 = $('#pieChart2').get(0).getContext('2d')
		var pieChart       = new Chart(pieChartCanvas)
		var PieData        = [
			{
				value    : <?=$none;?>,
				color    : '#d92550',
				highlight: '#f56954',
				label    : 'None payments'
			},
			{
				value    : <?=$half;?>,
				color    : '#f7b924',
				highlight: '#f3f134',
				label    : 'Pay half payments'
			},
			{
				value    : <?=$full;?>,
				color    : '#3ac47d',
				highlight: '#20a61e',
				label    : 'Finish all payments'
			},
		]
		var pieOptions     = {
			//Boolean - Whether we should show a stroke on each segment
			segmentShowStroke    : true,
			//String - The colour of each segment stroke
			segmentStrokeColor   : '#fff',
			//Number - The width of each segment stroke
			segmentStrokeWidth   : 1,
			//Number - The percentage of the chart that we cut out of the middle
			percentageInnerCutout: 2, // This is 0 for Pie charts
			//Number - Amount of animation steps
			animationSteps       : 250,
			//String - Animation easing effect
			animationEasing      : 'easeOutBounce',
			//Boolean - Whether we animate the rotation of the Doughnut
			animateRotate        : true,
			//Boolean - Whether we animate scaling the Doughnut from the centre
			animateScale         : true,
			//Boolean - whether to make the chart responsive to window resizing
			responsive           : true,
			// Boolean - whether to maintain the starting aspect ratio or not when responsive, if set to false, will take up entire container
			maintainAspectRatio  : true,
			//String - A legend template
			legendTemplate       : '<ul class="<%=name.toLowerCase()%>-legend"><% for (var i=0; i<segments.length; i++){%><li><span style="background-color:<%=segments[i].fillColor%>"></span><%if(segments[i].label){%><%=segments[i].label%><%}%></li><%}%></ul>'
		}
		//Create pie or douhnut chart
		// You can switch between pie and douhnut using the method below.
		pieChart.Doughnut(PieData, pieOptions)
		var pieChart2       = new Chart(pieChartCanvas2)
		var PieData2        = [
			{
				value    : <?=$extranone;?>,
				color    : '#d92550',
				highlight: '#f56954',
				label    : 'None payments'
			},
			{
				value    : <?=$extrahalf; ?>,
				color    : '#f7b924',
				highlight: '#f3f134',
				label    : 'Pay half payments'
			},
			{
				value    : <?=$extrafull;?>,
				color    : '#3ac47d',
				highlight: '#20a61e',
				label    : 'Finish all payments'
			},

		]
		var pieOptions2     = {
			//Boolean - Whether we should show a stroke on each segment
			segmentShowStroke    : true,
			//String - The colour of each segment stroke
			segmentStrokeColor   : '#fff',
			//Number - The width of each segment stroke
			segmentStrokeWidth   : 3,
			//Number - The percentage of the chart that we cut out of the middle
			percentageInnerCutout: 40, // This is 0 for Pie charts
			//Number - Amount of animation steps
			animationSteps       : 700,
			//String - Animation easing effect
			animationEasing      : 'easeOutBounce',
			//Boolean - Whether we animate the rotation of the Doughnut
			animateRotate        : true,
			//Boolean - Whether we animate scaling the Doughnut from the centre
			animateScale         : false,
			//Boolean - whether to make the chart responsive to window resizing
			responsive           : true,
			// Boolean - whether to maintain the starting aspect ratio or not when responsive, if set to false, will take up entire container
			maintainAspectRatio  : true,
			//String - A legend template
			legendTemplate       : '<ul class="<%=name.toLowerCase()%>-legend"><% for (var i=0; i<segments.length; i++){%><li><span style="background-color:<%=segments[i].fillColor%>"></span><%if(segments[i].label){%><%=segments[i].label%><%}%></li><%}%></ul>'
		}
		//Create pie or douhnut chart
		// You can switch between pie and douhnut using the method below.
		pieChart2.Doughnut(PieData2, pieOptions2)
//--------------
		var areaChartData = {
			labels  : ['<?= lang("app.jan"); ?>', '<?= lang("app.feb");?>', '<?= lang("app.mar");?>', '<?= lang("app.apr");?>', '<?= lang("app.may");?>', '<?= lang("app.jun");?>', '<?= lang("app.jul");?>','<?= lang("app.aug");?>','<?= lang("app.sep");?>','<?= lang("app.oct");?>','<?= lang("app.nov");?>','<?= lang("app.dec");?>'],
			datasets: [
				{
					label               : 'Digital Goods',
					fillColor           : 'rgba(60,141,188,0.9)',
					strokeColor         : 'rgba(60,141,188,0.8)',
					pointColor          : '#3b8bba',
					pointStrokeColor    : 'rgba(60,141,188,1)',
					pointHighlightFill  : '#fff',
					pointHighlightStroke: 'rgba(60,141,188,1)',
					data                : <?=$leave_array;?>
				}
			]
		}

		var areaChartData2 = {
			labels  : ['<?= lang("app.jan"); ?>', '<?= lang("app.feb");?>', '<?= lang("app.mar");?>', '<?= lang("app.apr");?>', '<?= lang("app.may");?>', '<?= lang("app.jun");?>', '<?= lang("app.jul");?>','<?= lang("app.aug");?>','<?= lang("app.sep");?>','<?= lang("app.oct");?>','<?= lang("app.nov");?>','<?= lang("app.dec");?>'],
			datasets: [
				{
					label               : 'Digital Goods',
					fillColor           : 'rgba(60,141,188,0.9)',
					strokeColor         : 'rgba(9,188,11,0.8)',
					pointColor          : '#18ba17',
					pointStrokeColor    : 'rgba(60,141,188,1)',
					pointHighlightFill  : '#fff',
					pointHighlightStroke: 'rgba(60,141,188,1)',
					data                : <?=$sms_array;?>
				}
			]
		}

		var areaChartOptions = {
			//Boolean - If we should show the scale at all
			showScale               : true,
			//Boolean - Whether grid lines are shown across the chart
			scaleShowGridLines      : false,
			//String - Colour of the grid lines
			scaleGridLineColor      : 'rgba(0,0,0,.05)',
			//Number - Width of the grid lines
			scaleGridLineWidth      : 1,
			//Boolean - Whether to show horizontal lines (except X axis)
			scaleShowHorizontalLines: true,
			//Boolean - Whether to show vertical lines (except Y axis)
			scaleShowVerticalLines  : true,
			//Boolean - Whether the line is curved between points
			bezierCurve             : true,
			//Number - Tension of the bezier curve between points
			bezierCurveTension      : 0.2,
			//Boolean - Whether to show a dot for each point
			pointDot                : false,
			//Number - Radius of each point dot in pixels
			pointDotRadius          : 4,
			//Number - Pixel width of point dot stroke
			pointDotStrokeWidth     : 1,
			//Number - amount extra to add to the radius to cater for hit detection outside the drawn point
			pointHitDetectionRadius : 20,
			//Boolean - Whether to show a stroke for datasets
			datasetStroke           : true,
			//Number - Pixel width of dataset stroke
			datasetStrokeWidth      : 2,
			//Boolean - Whether to fill the dataset with a color
			datasetFill             : true,
			//String - A legend template
			legendTemplate          : '<ul class="<%=name.toLowerCase()%>-legend"><% for (var i=0; i<datasets.length; i++){%><li><span style="background-color:<%=datasets[i].lineColor%>"></span><%if(datasets[i].label){%><%=datasets[i].label%><%}%></li><%}%></ul>',
			//Boolean - whether to maintain the starting aspect ratio or not when responsive, if set to false, will take up entire container
			maintainAspectRatio     : true,
			//Boolean - whether to make the chart responsive to window resizing
			responsive              : true
		}


		//-------------
		//- LINE CHART -
		//--------------
		var lineChartCanvas          = $('#lineChart').get(0).getContext('2d')
		var lineChart                = new Chart(lineChartCanvas)
		var lineChartOptions         = areaChartOptions
		lineChartOptions.datasetFill = false
		lineChart.Line(areaChartData, lineChartOptions)


		var donutData = [
			{ label: '<?= lang("app.approved"); ?>: '+<?=count($approveds);?>, data: <?=count($approveds);?>, color: '#0073b7' },
			{ label: '<?= lang("app.denied"); ?>: '+<?=count($denieds);?>, data: <?=count($denieds);?>, color: '#3c8dbc' },
		]
		$.plot('#donut-chart', donutData, {
			series: {
				pie: {
					show       : true,
					radius     : 1,
					innerRadius: 0,
					label      : {
						show     : true,
						radius   : 2 / 3,
						formatter: labelFormatter,
						threshold: 0.1
					}

				}
			},
			legend: {
				show: true
			}
		})
		function labelFormatter(label, series) {
			return '<div style="font-size:13px; text-align:center; padding:2px; color: #fff; font-weight: 600;">'
				+ label
				+ '<br>'
		}
		/*
		 * END DONUT CHART
		 */
//-------------
		//- LINE CHART -
		//--------------
		var lineChartCanvas2          = $('#lineChart2').get(0).getContext('2d')
		var lineChart2                = new Chart(lineChartCanvas2)
		var lineChartOptions2         = areaChartOptions
		lineChartOptions2.datasetFill = false
		lineChart2.Line(areaChartData2, lineChartOptions2)


		var donutData2 = [
			{ label: '<?= lang("app.remain");?>: '+<?=$sms_limit-$sms_usage;?>, data: <?=$sms_limit-$sms_usage;?>, color: '#18ba17' },
			{ label: '<?= lang("app.usage");?>: '+<?=$sms_usage;?>, data: <?=$sms_usage;?>, color: 'rgba(11,217,69,0.4)' },
		]
		$.plot('#donut-chart2', donutData2, {
			series: {
				pie: {
					show       : true,
					radius     : 1,
					innerRadius: 0,
					label      : {
						show     : true,
						radius   : 1 / 2,
						formatter: labelFormatter,
						threshold: 0.1
					}

				}
			},
			legend: {
				show: true
			}
		})
		function labelFormatter(label, series) {
			return '<div style="font-size:13px; text-align:center; padding:2px; color: #fff; font-weight: 600;">'
				+ label
				+ '<br>'
		}
		/*
		 * END DONUT CHART
		 */

		var areaChartData3 = {
			labels  : ['Monday', 'Tuesday', 'wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
			datasets: [
				{
					label               : 'days',
					fillColor           : 'rgba(60,141,188,0.9)',
					strokeColor         : 'rgba(9,188,11,0.8)',
					pointColor          : '#18ba17',
					pointStrokeColor    : 'rgba(60,141,188,1)',
					pointHighlightFill  : '#fff',
					pointHighlightStroke: 'rgba(60,141,188,1)',
					data                :<?=$attend_day_array;?>
				}
			]
		}

		var lineChartCanvas3          = $('#lineChart3').get(0).getContext('2d')
		var lineChart3                = new Chart(lineChartCanvas3)
		var lineChartOptions2         = areaChartOptions
		lineChartOptions2.datasetFill = false
		lineChart3.Line(areaChartData3, lineChartOptions2)


		var donutData3 = [
			{ label: 'Present:<?=$present;?>', data: <?=$present;?>, color: '#18ba17' },
			{ label: 'Absent: <?=$absent-$present;?>', data: <?=$absent-$present;?>, color: 'rgba(11,217,69,0.4)' },
		]
		$.plot('#donut-chart3', donutData3, {
			series: {
				pie: {
					show       : true,
					radius     : 1,
					innerRadius: 0,
					label      : {
						show     : true,
						radius   : 1 / 2,
						formatter: labelFormatter,
						threshold: 0.1
					}

				}
			},
			legend: {
				show: true
			}
		})

	})
</script>
