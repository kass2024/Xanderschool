<style>
	.product-list-in-card > .item {
		border-radius: 0;
		border-bottom: 1px solid rgba(0, 0, 0, .125);
		padding-top: 5px;
	}

	.products-list > .item {
		background: #fff;
		max-width: 500px;
		border-left: solid 2px #133645;
		border-radius: 3px 3px 3px 3px;
		padding-right: 10px;
	}

	*, ::after, ::before {
		box-sizing: border-box;
	}

	.products-list {
		list-style: none;
	}

	.pad {
		padding-right: 0px;
		padding-left: 0px;
	}

	.card {

		box-shadow: 2px 4px 2px 2px #bdb6b633;
	}
</style>
<div class="row">
	<div class="col-sm-12 col-md-7">
		<div class="row">
			<div class="col-sm-12 col-md-6 pad">
				<ul class="products-list product-list-in-card pl-2 pr-2" id="farmerDiv">
					<li class="item remove">
						<div class="product-info" style="padding-left:10px">
							<span href="javascript:void(0)"
								  class="product-title"><strong><?=number_format($money['transfer']); ?> RWF</strong></span>
							<span class="badge badge-warning float-right"
								  style="margin-top: 10px;"><strong></strong><?= $money['transferNum']; ?></span>
							<div style="height: 4px"></div>
							<span class="product-description">
                <span style="color: #adb5bd;font-size: 14px">Transfer</span>
                </span>
						</div>
					</li>
				</ul>
			</div>
			<div class="col-sm-12 col-md-6 pad">
				<ul class="products-list product-list-in-card pl-2 pr-2" id="farmerDiv">
					<li class="item remove">
						<div class="product-info" style="padding-left:10px">
							<span href="javascript:void(0)"
								  class="product-title"><strong><?=number_format($money['payment']); ?> RWF</strong></span>
							<span class="badge badge-warning float-right"
								  style="margin-top: 10px;"><strong></strong><?= $money['paymentNum']; ?></span>
							<div style="height: 4px"></div>
							<span class="product-description">
                <span style="color: #adb5bd;font-size: 14px">Payment</span>
                </span>
						</div>
					</li>
				</ul>
			</div>
		</div>
		<div class="row">
			<div class="col-sm-12 col-md-6 pad">
				<ul class="products-list product-list-in-card pl-2 pr-2" id="farmerDiv">
					<li class="item remove">
						<div class="product-info" style="padding-left:10px">
							<span href="javascript:void(0)"
								  class="product-title"><strong><?= number_format($money['withdraw']); ?> RWF</strong></span>
							<span class="badge badge-warning float-right"
								  style="margin-top: 10px;"><strong></strong><?= $money['withdrawNum']; ?></span>
							<div style="height: 4px"></div>
							<span class="product-description">
                <span style="color: #adb5bd;font-size: 14px">Withdraw</span>
                </span>
						</div>
					</li>
				</ul>
			</div>
			<div class="col-sm-12 col-md-6 pad">
				<ul class="products-list product-list-in-card pl-2 pr-2" id="farmerDiv">
					<li class="item remove">
						<div class="product-info" style="padding-left:10px">
							<span href="javascript:void(0)" class="product-title"><strong>Active</strong></span>
							<span class="badge badge-warning float-right"
								  style="margin-top: 10px;"><strong></strong><?= $activeStudent; ?></span>
							<div style="height: 4px"></div>
							<span class="product-description">
                <span style="color: #adb5bd;font-size: 14px">Students</span>
                </span>
						</div>
					</li>
				</ul>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12 col-sm-12">
				<div class="card" style="align-content: center;">
					<div class="card-header-tab card-header">
						<div
							class="card-header-title font-size-lg text-capitalize font-weight-normal text-center">
							<i class="header-icon lnr-charts icon-gradient bg-happy-green"> </i>
							Most Active Students
						</div>
					</div>
					<canvas id="pieChart" style="padding:20px"></canvas>
				</div>
			</div>
		</div>
	</div>
	<div class="col-sm-12 col-md-5" style="border: 1px solid #333333;border-radius: 5px 5px 5px 5px;padding: 10px">
	<strong class="float-right">Latest Transactions</strong><br>
		<?php $color = "text-success";
		foreach ($transactions as $transaction):
			if ($transaction['type'] == 0) {
				$color = "text-success";
			} else if ($transaction['type'] == 1) {
				$color = "text-info";
			} else if ($transaction['type'] == 2) {
				$color = "text-danger";
			} else {
				$color = "text-dark";
			}
			?>
			<div class="card" style="padding: 10px">
				<div class="row">
					<div class="col-sm-12 col-md-6 pad">
						<strong
							style="text-transform: uppercase;"><?= date("d M Y | h:s", strtotime($transaction['created_at'])); ?></strong>
					</div>
					<div class="col-sm-12 col-md-6 pad text-right">
						<strong class="<?= $color; ?>"><?= number_format($transaction['amount']); ?> RWF</strong>
					</div>
				</div>
				<div class="row">
					<div class="col-sm-12 col-md-12 pad">
						<b class="text-muted"><small><?= transactions_words($transaction['type']); ?>
								made(successfully)</small></b>
					</div>
					<div class="col-sm-12 col-md-12 pad">
						<div style="border-left: #133645 solid 2px;margin-left: 10px">
							<div class="row" style="margin-left: 5px">
								<div class="col-sm-12 col-md-6 pad">
									<strong class="text-muted">Txn ID:</strong>
								</div>
								<div class="col-sm-12 col-md-6 pad text-right">
									<strong class="text-muted"><?= $transaction['reference_id']; ?></strong>
								</div>
							</div>
							<div class="row" style="margin-left: 5px">
								<div class="col-sm-12 col-md-6 pad">
									<strong class="text-muted">Txn fee:</strong>
								</div>
								<div class="col-sm-12 col-md-6 pad text-right">
									<strong class="text-muted"><?= $transaction['txn_fee']; ?></strong>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div style="height: 8px"></div>
		<?php
		endforeach;
		?>
		<a href="<?=base_url('transactions');?>" class="btn btn-success btn-sm float-right">View All</a>
	</div>
</div>
<script>
	$(function () {
		let PieData = []
		let colors;
		let highlights
		$.getJSON("<?=base_url();?>getMostActiveStudents", function (data) {
			$.each(data, function (index, obj) {
				if (index==0){
					colors="#3ac47d"
					highlights="#20a61e"
				}else if (index==1){
					colors="#f7b924"
					highlights="#f3f134"
				}else if (index==2){
					colors="#d92550"
					highlights="#f56954"
				}
				PieData.push({value:obj.times,color: colors,highlight:highlights,label: obj.student})
			})
			// Get context with jQuery - using jQuery's .get() method.
			var pieChartCanvas = $('#pieChart').get(0).getContext('2d')
			var pieChart = new Chart(pieChartCanvas)

			var pieOptions = {
				//Boolean - Whether we should show a stroke on each segment
				segmentShowStroke: true,
				//String - The colour of each segment stroke
				segmentStrokeColor: '#fff',
				//Number - The width of each segment stroke
				segmentStrokeWidth: 1,
				//Number - The percentage of the chart that we cut out of the middle
				percentageInnerCutout: 2, // This is 0 for Pie charts
				//Number - Amount of animation steps
				animationSteps: 250,
				//String - Animation easing effect
				animationEasing: 'easeOutBounce',
				//Boolean - Whether we animate the rotation of the Doughnut
				animateRotate: true,
				//Boolean - Whether we animate scaling the Doughnut from the centre
				animateScale: true,
				//Boolean - whether to make the chart responsive to window resizing
				responsive: true,
				// Boolean - whether to maintain the starting aspect ratio or not when responsive, if set to false, will take up entire container
				maintainAspectRatio: true,
				//String - A legend template
				legendTemplate: '<ul class="<%=name.toLowerCase()%>-legend"><% for (var i=0; i<segments.length; i++){%><li><span style="background-color:<%=segments[i].fillColor%>"></span><%if(segments[i].label){%><%=segments[i].label%><%}%></li><%}%></ul>'
			}
			//Create pie or douhnut chart
			// You can switch between pie and douhnut using the method below.
			pieChart.Doughnut(PieData, pieOptions)
		})
	})
</script>
