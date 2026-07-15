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
						<?php
						$completed = explode(":",$money['completed']);
						$bank = explode(":",$money['bank_pending']);
						$failed = explode(":",$money['failed']);
						$pending = explode(":",$money['pending']);
						?>
						<div class="product-info" style="padding-left:10px">
							<span href="javascript:void(0)"
								  class="product-title"><strong><?=number_format($completed[0]); ?> RWF</strong></span>
							<span class="badge badge-warning float-right"
								  style="margin-top: 10px;"><strong></strong><?= $completed[1]; ?></span>
							<div style="height: 4px"></div>
							<span class="product-description">
                <span style="color: #adb5bd;font-size: 14px">Completed</span>
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
								  class="product-title"><strong><?=number_format($bank[0]); ?> RWF</strong></span>
							<span class="badge badge-warning float-right"
								  style="margin-top: 10px;"><strong></strong><?= $bank[1]; ?></span>
							<div style="height: 4px"></div>
							<span class="product-description">
                <span style="color: #adb5bd;font-size: 14px">Pending bank transfer</span>
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
								  class="product-title"><strong><?= number_format($pending[0]); ?> RWF</strong></span>
							<span class="badge badge-warning float-right"
								  style="margin-top: 10px;"><strong></strong><?= $pending[1]; ?></span>
							<div style="height: 4px"></div>
							<span class="product-description">
                <span style="color: #adb5bd;font-size: 14px">Pending transactions</span>
                </span>
						</div>
					</li>
				</ul>
			</div>
			<div class="col-sm-12 col-md-6 pad">
				<ul class="products-list product-list-in-card pl-2 pr-2" id="farmerDiv">
					<li class="item remove">
						<div class="product-info" style="padding-left:10px">
							<span href="javascript:void(0)" class="product-title"><strong><?= number_format($failed[0]); ?> RWF</strong></span>
							<span class="badge badge-warning float-right"
								  style="margin-top: 10px;"><strong></strong><?= $failed[1]; ?></span>
							<div style="height: 4px"></div>
							<span class="product-description">
                <span style="color: #adb5bd;font-size: 14px">Failed</span>
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
							Payment chart
						</div>
						<div style="position: absolute;right: 20px;display: none">
							<select>
								<option value="1">Term 1, 2021</option>
							</select>
						</div>
					</div>
					<canvas id="pieChart" style="padding:20px"></canvas>
				</div>
			</div>
		</div>
	</div>
	<div class="col-sm-12 col-md-5" style="border: 1px solid #333333;border-radius: 5px 5px 5px 5px;padding: 10px">
		<div class="btn-group" style="width: 100%;margin-bottom: 10px">
			<input type="search" id="txt-txn-search" placeholder="search transaction" class="form-control" style="width: calc(100% - 180px)">
			<button class="btn btn-warning btn-sm" id="btn-txn-refresh" style="width: 90px"><i class="fa fa-sync"></i> Refresh</button>
			<button class="btn btn-success dropdown-toggle" type="button" id="dropdownMenuButton"
					data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" style="width: 90px;">
				<i class="fa fa-filter"></i> Filter
			</button>
			<div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
				<a class="dropdown-item" href="#" onclick="transactionFilter(1)">Failed</a>
				<a class="dropdown-item" href="#" onclick="transactionFilter(2)">Pending bank transfer</a>
				<a class="dropdown-item" href="#" onclick="transactionFilter(3)">Completed</a>
			</div>
		</div>
		<div id="transaction-container">

		</div>
	</div>
</div>
<script>
	let filter = 0;
	$(function () {
		$("#transaction-container").load("<?=base_url();?>getPaymentTransactions");
		$("#btn-txn-refresh").on("click",function(){
			$("#txt-txn-search").val('');
			$("#transaction-container").load("<?=base_url();?>getPaymentTransactions/"+filter);
		});
		$("#txt-txn-search").on("keyup",function(e){
			if(e.which == 8 && window.filter != 0){
				//remove filter
				$(this).val('');
				filter = 0;
				$("#transaction-container").load("<?=base_url();?>getPaymentTransactions/"+filter);

			}
			if(e.which == 13){
				$("#transaction-container").load("<?=base_url();?>getPaymentTransactions/"+filter+"/"+encodeURIComponent($(this).val()));
			}
		})
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
	function transactionFilter(status){
		filter = status;
		var filterText = "";
		if (status == 1){
			filterText = "Failed";
		}else if (status == 2){
			filterText = "Pending bank transfer";
		}else if (status == 3){
			filterText = "Completed";
		}
		$("#txt-txn-search").val(":"+filterText);
		$("#transaction-container").load("<?=base_url();?>getPaymentTransactions/"+status);
		$(".dropdown-menu").removeClass("show");
	}
</script>
