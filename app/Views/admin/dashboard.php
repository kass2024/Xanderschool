<div class="app-inner-layout app-inner-layout-page">
	<div class="app-inner-layout__wrapper">
		<div class="app-inner-layout__content">
			<div class="tab-content">
				<div class="container-fluid">
					<div class="mb-3 card">
						<div class="card-header-tab card-header">
							<div
								class="card-header-title font-size-lg text-capitalize font-weight-normal">
								<i class="header-icon lnr-charts icon-gradient bg-happy-green"> </i>
								<?= lang("app.SomanetRecords"); ?>
							</div>
							<div class="btn-actions-pane-right text-capitalize">
								<button
									class="btn-wide btn-outline-2x mr-md-2 btn btn-outline-focus btn-sm">
									<?= lang("app.viewAll"); ?>
								</button>
							</div>
						</div>
						<div class="no-gutters row">
							<div class="col-sm-6 col-md-4 col-xl-4">
								<div
									class="card no-shadow rm-border bg-transparent widget-chart text-left">
									<div class="icon-wrapper rounded-circle">
										<div class="icon-wrapper-bg opacity-10 bg-warning"></div>
										<i class="typcn typcn-messages text-dark opacity-8"></i></div>
									<div class="widget-chart-content">
										<div class="widget-subheading"><?= lang("app.SMSentToday"); ?> </div>
										<div class="widget-numbers text-warning"><?php if($sms==null) echo 0; else echo $sms;?></div>
										<div class="widget-description opacity-8 text-focus">
											<?= lang("app.from"); ?>
											<div class="d-inline text-success pr-1">
												<span class="pl-1"><?php if($from_schools==null) echo 0; else echo $from_schools;?></span>
											</div>
											<?= lang("app.school"); ?>
										</div>
									</div>
								</div>
								<div class="divider m-0 d-md-none d-sm-block"></div>
							</div>
							<div class="col-sm-6 col-md-4 col-xl-4">
								<div
									class="card no-shadow rm-border bg-transparent widget-chart text-left">
									<div class="icon-wrapper rounded-circle">
										<div class="icon-wrapper-bg opacity-9 bg-asteroid"></div>
										<i class="typcn typcn-home-outline text-white"></i></div>
									<div class="widget-chart-content">
										<div class="widget-subheading"><?= lang("app.TodayActiveSchools"); ?> </div>
										<div class="widget-numbers"><span><?=$schools;?></span></div>

									</div>
								</div>
								<div class="divider m-0 d-md-none d-sm-block"></div>
							</div>
							<div class="col-sm-12 col-md-4 col-xl-4">
								<div
									class="card no-shadow rm-border bg-transparent widget-chart text-left">
									<div class="icon-wrapper rounded-circle">
										<div class="icon-wrapper-bg opacity-9 bg-premium-dark"></div>
										<i class="typcn typcn-input-checked text-white"></i></div>
									<div class="widget-chart-content">
										<div class="widget-subheading"><?= lang("app.package"); ?> </div>
										<div class="widget-numbers text-dark"><span><?=$package;?></span></div>

									</div>
								</div>
							</div>
						</div>

					</div>
					<div class="mb-3 card">
						<div class="card-header-tab card-header">
							<div
								class="card-header-title font-size-lg text-capitalize font-weight-normal">
								<?= lang("Top 4 Schools with many students");?>
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
												<canvas id="pieChart" style="height:250px"></canvas>
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
					<div class="card mb-3">
						<div class="card-header-tab card-header">
							<div
								class="card-header-title font-size-lg text-capitalize font-weight-normal">
								<i class="header-icon typcn typcn-user text-muted opacity-6"> </i><?= lang("app.RecentSchools"); ?>
							</div>
							<div class="btn-actions-pane-right actions-icon-btn" style="display: none !important;">
								<div class="btn-group dropdown">
									<button type="button" data-toggle="dropdown" aria-haspopup="true"
											aria-expanded="false"
											class="btn-icon btn-icon-only btn btn-link"><i
											class="pe-7s-menu btn-icon-wrapper"></i></button>
									<div tabindex="-1" role="menu" aria-hidden="true"
										 class="dropdown-menu-right rm-pointers dropdown-menu-shadow dropdown-menu-hover-link dropdown-menu">
										<h6 tabindex="-1" class="dropdown-header">
											<?= lang("app.Header"); ?> </h6>
										<button type="button" tabindex="0" class="dropdown-item"><i
												class="dropdown-icon lnr-inbox"> </i><span><?= lang("app.Menus"); ?> </span>
										</button>
										<button type="button" tabindex="0" class="dropdown-item"><i
												class="dropdown-icon lnr-file-empty"> </i><span><?= lang("app.settings"); ?> </span>
										</button>
										<button type="button" tabindex="0" class="dropdown-item"><i
												class="dropdown-icon lnr-book"> </i><span><?= lang("app.Actions"); ?> </span>
										</button>
										<div tabindex="-1" class="dropdown-divider"></div>
										<div class="p-3 text-right">
											<button class="mr-2 btn-shadow btn-sm btn btn-link"><?= lang("app.ViewDetails"); ?>
											</button>
											<button class="mr-2 btn-shadow btn-sm btn btn-primary">
												<?= lang("app.Actions"); ?>
											</button>
										</div>
									</div>
								</div>
							</div>
						</div>
						<div class="card-body">
							<div id="example_wrapper" class="dataTables_wrapper dt-bootstrap4">
								<div class="row">
									<div class="col-sm-12">
										<table style="width: 100%;" id="example"
											   class="table table-hover table-striped table-bordered dataTable dtr-inline"
											   role="grid" aria-describedby="example_info">
											<thead>
											<tr role="row">
												<th><?= lang("app.logo"); ?> </th>
												<th><?= lang("app.names"); ?> </th>
												<th><?= lang("app.acronym"); ?> </th>
												<th><?= lang("app.phone"); ?> </th>
												<th><?= lang("app.mail"); ?> </th>
												<th><?= lang("app.headMaster"); ?></th>
												<th><?= lang("app.country"); ?> </th>
											</tr>
											</thead>
											<tbody>
											<?php $i=0; foreach ($recentSchoolS as $school) { if ($i==4) break; ?>

												<tr>
													<td><img src="<?=base_url('assets/logo/');?><?=$school['logo'];?>" style="width:80px;" alt="Logo"></td>
													<td><?=$school['name'];?></td>
													<td><?=$school['acronym'];?></td>
													<td><?=$school['phone'];?></td>
													<td><?=$school['email'];?></td>
													<td><?=$school['head_master'];?></td>
													<td><?=ucfirst("Isa".$school['country']);?></td>
												</tr>
												<?php
												$i++;
											}

											?>
											</tbody>
											<tfoot>
											<tr>
												<th><?= lang("app.logo"); ?> </th>
												<th><?= lang("app.names"); ?> </th>
												<th><?= lang("app.acronym"); ?> </th>
												<th><?= lang("app.phone"); ?> </th>
												<th><?= lang("app.mail"); ?> </th>
												<th><?= lang("app.headMaster"); ?></th>
												<th><?= lang("app.country"); ?> </th>
											</tr>
											</tfoot>
										</table>
									</div>
								</div>
							</div>
						</div>
					</div>
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
															<div class="widget-heading">Total Students
															</div>
															<div class="widget-subheading">This year
																numbers
															</div>
														</div>
														<div class="widget-content-right">
															<div class="widget-numbers text-success">
																<?=$students??0;?>
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
															<div class="widget-heading"><?= lang("app.school"); ?></div>
															<div class="widget-subheading"><?= lang("app.Totalschools"); ?>
															</div>
														</div>
														<div class="widget-content-right">
															<div class="widget-numbers text-primary">
																<?=$totalSchools??0;?>
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
															<div class="widget-subheading"><?= lang("app.AllEmployeesFromschools"); ?>
															</div>
														</div>
														<div class="widget-content-right">
															<div class="widget-numbers text-danger">
																<?=$staffs??0;?>
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
															<div class="widget-heading"><?= lang("app.Users"); ?>
															</div>
															<div class="widget-subheading"><?= lang("app.Somanetusers"); ?>
															</div>
														</div>
														<div class="widget-content-right">
															<div class="widget-numbers text-warning">
																<?=$users??0;?>
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
															<div class="widget-heading"><?= lang("app.Totalvents"); ?>
															</div>
															<div class="widget-subheading"><?= lang("app.Eventscounts"); ?>
															</div>
														</div>
														<div class="widget-content-right">
															<div class="widget-numbers text-success">
																2
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
															<div class="widget-heading">SMS</div>
															<div class="widget-subheading">Total used sms
															</div>
														</div>
														<div class="widget-content-right">
															<div class="widget-numbers text-primary">
																<?=$totalSms??0;?>
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
				</div>
			</div>
		</div>
	</div>
</div>
<script>
	$(function () {

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
					data                : <?=$sms_array??[];?>
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

		var lineChartCanvas2          = $('#lineChart2').get(0).getContext('2d')
		var lineChart2                = new Chart(lineChartCanvas2)
		var lineChartOptions2         = areaChartOptions
		lineChartOptions2.datasetFill = false
		lineChart2.Line(areaChartData2, lineChartOptions2)

		function labelFormatter(label, series) {
			return '<div style="font-size:13px; text-align:center; padding:2px; color: #fff; font-weight: 600;">'
				+ label
				+ '<br>'
		}

		// Get context with jQuery - using jQuery's .get() method.
		var pieChartCanvas = $('#pieChart').get(0).getContext('2d')
		var pieChart       = new Chart(pieChartCanvas)
		var PieData        = [
			{
				value    : <?=$first['students']??'';?>,
				color    : 'green',
				highlight: '#f56954',
				label    :" <?=$first['school']??'';?>"
			},
			{
				value    : <?=$second['students']??'';?>,
				color    : 'orange',
				highlight: '#f3f134',
				label    : " <?=$second['school']??'';?>"
			},
			{
				value    :<?php if($third==null || $third['students']==null) echo 0; else echo $third['students']?>,
				color    : 'indigo',
				highlight: '#20a61e',
				label    : "<?=$third['school']??'';?>"
			},
			{
				value    :<?php if($fourth==null || $fourth['students']==null) echo 0; else echo $third['students']?>,
				color    : 'red',
				highlight: '#20a61e',
				label    : "<?=$fourth['school']??'';?>"
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

	})
</script>
