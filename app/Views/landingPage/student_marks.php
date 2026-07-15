<style>
	.form-control {
		padding: 10px 5px;
	}

	.btn {
		padding-top: 10px;
		padding-bottom: 10px;
	}

	.full {
		width: 100%;
		float: left;
	}

	.text-danger {
		color: orangered;
	}

	.student-info > label {
		width: 100%;
	}
	.table-condensed th,.table-condensed td{
		padding: 3px !important;
	}
	.boxed {
		background-color: #eefbf5;
		padding: 10px;
		margin-top: 10px;
		border-radius: 3px;
		box-shadow: 0 0 6px 2px #0002;
	}

	* {
		font-size: 12pt;
	}

	button {
		cursor: pointer;
	}

	@media (max-width: 766px) {
		.col-md-8 {
			padding: 0;
		}
	}
	.sticky-wrapper {
		background-color: #144857;
	}
</style>
<div class="col-md-12" style="display: inline-block;<?=empty($type)?'padding-top: 100px':'';?>">
	<div class="col-sm-12 col-md-5 col-lg-4">
		<div class="form-group pull-left" style="width: calc(100% - 160px)">
			<label>Enter student reg no</label>
			<input type="text" class="form-control" placeholder="Enter student reg no here" id="txt-regno-0">
			<label class="text-danger" id="lbl-error-0"></label>
		</div>
		<button class="btn btn-primary pull-left" style="max-width: 160px;margin-top: 27px" id="btn-get-student-0">
			Continue
		</button>
	</div>
	<hr class="full clearfix"/>
	<div class="no-data-0 full"
		 style="height: 400px;background: url('<?= base_url('assets/images/no-data.jpg'); ?>') no-repeat 50% 30px;background-size: 480px;">
		<p class="text-center">Please enter registration number and press continue</p>
	</div>
	<div class="dv-student-0 full" style="display: none">
		<div class="col-sm-12 col-md-4 pull-left boxed">
			<img src="" style="width: 100px;float:left;margin-right: 10px;border-radius: 10px;
border: 1px solid;" id="img-student">
			<div style="width: calc(100% - 110px);float: left" class="student-info">
				<label id="lbl-name">Name: <strong></strong></label>
				<label id="lbl-regno">Regno: <strong></strong></label>
				<label id="lbl-gender">Gender: <strong></strong></label>
				<label id="lbl-mode">Mode: <strong></strong></label>
				<label id="lbl-phone">Parent phone: <strong></strong></label>
				<label id="lbl-department">Department: <strong></strong></label>
				<label id="lbl-class">Class: <strong></strong></label>
				<label id="lbl-school">School: <strong></strong></label>
				<label id="lbl-school-phone">School Phone: <strong></strong></label>
				<label id="lbl-discipline">Discipline marks: <strong></strong></label>
			</div>
		</div>
		<div class="col-sm-12 col-md-8 pull-left">
			<div class="boxed full">
				<div class="form-group pull-left">
					<label for="sl-year">Academic year</label>
					<select id="sl-year">
						<option selected disabled value="">Select academic year</option>
					</select>
				</div>
				<div class="form-group pull-left ml-2">
					<label for="sl-term">Term</label>
					<select id="sl-term">
						<option selected disabled value="">Select term</option>
						<option value="1">First term</option>
						<option value="2">Second term</option>
						<option value="3">Third term</option>
						<option value="4">Annual</option>
					</select>
				</div>
				<button class="btn btn-primary pull-left" style="padding: 6px 10px;margin-left: 10px"
						id="btn-get-marks-0">
					Fetch
				</button>
				<a href="#" target="_blank" class="btn btn-warning pull-left" style="padding: 6px 10px;color:darkorange;float: right;border: 1px solid;display: none"
						id="btn-generate-report">
					Generate full report
				</a>
				<label class="text-danger full" id="lbl-error-1"></label>
				<table class="table table-striped table-condensed table-marks" style="display: none">
					<thead>
						<tr>
							<th>Course</th>
							<th>Code</th>
							<th>CAT</th>
							<th>EXAM</th>
							<th>TOTAL</th>
						</tr>
					</thead>
					<tbody>

					</tbody>
					<tfoot>

					</tfoot>
				</table>
			</div>
		</div>
	</div>
</div>
<script src="<?= base_url('assets/js/jquery-3.4.1.min.js'); ?>"></script>
<script>
	$(function () {
		var selected_student = '';
		var selected_class = '';
		var selected_school = '';
		$('#btn-get-student-0').on('click', function () {
			const regno = $('#txt-regno-0');
			const lblError = $('#lbl-error-0');
			lblError.text('');
			$('.table-marks').hide();
			$('#btn-generate-report').hide().prop("href","#");
			if (regno.val().trim().length < 4) {
				lblError.text("Please add a valid reg no");
			} else {
				const btn = $(this);
				btn.text('Please wait...').prop('disabled', true);
				$.ajax({
					type: "GET",
					url: '<?=base_url("get_student_json2/");?>' + regno.val().trim(),
					success: function (data) {
						// your callback here
						selected_student = data.id;
						selected_class = data.class_id;
						selected_school = data.school_id;
						$('.no-data-0').fadeOut(400);
						$('.dv-student-0').slideDown(500);
						var profile = 'no_image.jpg';
						if (data.photo.length > 3) {
							profile = 'profile/' + data.photo;
						}
						$('#img-student').prop('src', '<?=base_url('assets/images/');?>' + profile);
						$('#lbl-name > strong').text(data.stdnames);
						$('#lbl-regno > strong').text(data.regno);
						$('#lbl-class > strong').text(data.level_name + ' ' + data.code + ' ' + data.title);
						$('#lbl-gender > strong').text(data.sex);
						$('#lbl-mode > strong').text(data.studying_mode);
						$('#lbl-school > strong').text(data.school);
						$('#lbl-school-phone > strong').text(data.school_phone);
						$('#lbl-department > strong').text(data.department_name);
						$('#lbl-phone > strong').text(data.phone);
						$('#lbl-discipline > strong').text((data.discipline_max - data.total_marks) + '/' + data.discipline_max);
						$('#sl-year').html('')
						$.each(data.academic_years, function (index, item) {
							$('#sl-year').append('<option value="'+item.id+'">'+item.title+'</option>');
							console.log(index+":"+item)
						});
						btn.text('CONTINUE').prop('disabled', false);
					},
					error: function (error) {
						// handle error
						$('.no-data-0').fadeIn();
						$('.dv-student-0').hide(500);
						console.log(error)
						btn.text('CONTINUE').prop('disabled', false);
						lblError.text("Error: " + error.responseJSON.message);
					},
					async: true,
					dataType: "json",
					cache: false,
					contentType: false,
					processData: false
				});
			}
		})
		$('#btn-get-marks-0').on('click', function () {
			const year = $('#sl-year');
			const term = $('#sl-term');
			const lblError = $('#lbl-error-1');
			$('#btn-generate-report').hide();
			lblError.text('');
			if (year.val() === null) {
				lblError.text("Please select academic year");
			} else if (term.val() === null) {
				lblError.text("Please select term");
			} else {
				const btn = $(this);
				btn.text('Please wait...').prop('disabled', true);
				$.ajax({
					type: "GET",
					url: '<?=base_url("student_marks_json/");?>' + selected_student + '/' + selected_class + '/'
							+ year.val().trim() + '/' + term.val().trim()+ '/' + selected_school,
					success: function (data) {
						// your callback here
						btn.text('FETCH').prop('disabled', false);
						$('.table-marks').show();
						//$('#btn-generate-report').show()
						//		.prop("href",'<?//=base_url();?>//'+"student_report_slip?year="+year.val()+"&term="+term.val()
						//				+"&class="+selected_class+"&student="+selected_student+"&school="+selected_school+"&pdf=true");
						$('.table-marks > tbody').html('');
						$('.table-marks > tfoot').html('');
						var cat_tot = 0;
						var exam_tot = 0;
						var bot_tot = 0;
						var cw_tot = 0;
						var mid_tot = 0;
						var eot_tot = 0;
						var tot = 0;
						var grand_tot = 0;
						var isCambridge = false;
						var isSetHeader = true;
						$.each(data.marks,function (index,dt){
							const res = dt.result;
							if(res.hasOwnProperty('BOT')){
								//cambridge
								if(isSetHeader){
									$('.table-marks > thead').html("<tr>" +
											"<th>Course</th>" +
											"<th>Code</th>" +
											"<th>BOT<br>10%</th>" +
											"<th>CW<br>10%</th>" +
											"<th>MID<br>20%</th>" +
											"<th>EOT<br>60%</th>" +
											"<th>TOTAL<br>100%</th>" +
											"</tr>");
									isSetHeader = false;
								}
								isCambridge = true;
								// res.BOT = res.BOT * 10 / dt.marks
								// res.CW = res.CW * 10 / dt.marks
								// res.MID = res.MID * 20 / dt.marks
								// res.EOT = res.EOT * 60 / dt.marks
								const bot = number_format(res.BOT,1);
								const cw = number_format(res.CW,1);
								const mid = number_format(res.MID,1);
								const eot = number_format(res.EOT,1);
								bot_tot += parseFloat(res.BOT)
								cw_tot += parseFloat(res.CW)
								mid_tot += parseFloat(res.MID)
								eot_tot += parseFloat(res.EOT)
								const row_tot = parseFloat(res.BOT)+parseFloat(res.CW)+parseFloat(res.MID)+parseFloat(res.EOT)
								tot += row_tot
								grand_tot += parseFloat(dt.marks)
								$('.table-marks > tbody').append("<tr><td>" + dt.title + "</td>" +
										"<td>" + dt.code + "</td>" +
										"<td>" + bot + "</td>" +
										"<td>" + cw + "</td>" +
										"<td>" + mid + "</td>" +
										"<td>" + eot + "</td>" +
										"<td>" + number_format(row_tot,1) + "</td></tr>");
							}else{
								if(isSetHeader){
									$('.table-marks > thead').html("<tr>" +
											"<th>Course</th>" +
											"<th>Code</th>" +
											"<th>CAT</th>" +
											"<th>EXAM</th>" +
											"<th>TOTAL</th>" +
											"</tr>");
									isSetHeader = false;
								}
								const cat = res.marks===null?' - ':number_format(res.marks,1) + "/" + dt.marks;
								const exam = res.exam_marks===null?' - ':number_format(res.exam_marks,1) + "/" + dt.marks;
								res.marks = res.marks===null?0:res.marks;
								res.exam_marks = res.exam_marks===null?0:res.exam_marks;
								cat_tot += parseFloat(res.marks)
								exam_tot += parseFloat(res.exam_marks)
								tot += parseFloat(res.exam_marks)+parseFloat(res.marks)
								grand_tot += parseFloat(dt.marks)
								$('.table-marks > tbody').append("<tr><td>" + dt.title + "</td>" +
										"<td>" + dt.code + "</td>" +
										"<td>" + cat + "</td>" +
										"<td>" + exam + "</td>" +
										"<td>" + number_format((parseFloat(res.marks) + parseFloat(res.exam_marks)),1) + "/" + (dt.marks * 2) + "</td></tr>");
							}

						});
						if(isCambridge){
							// $('.table-marks > tfoot').append("<tr><th colspan='6'>Percentage</th>" +
							// 		"<th>" + number_format(tot/(grand_tot*4)*100,1) + "%</th></tr>");
						}else{
							$('.table-marks > tfoot').append("<tr><th colspan='2'>Total</th>" +
									"<th>" + number_format(cat_tot,1)+"/"+grand_tot + "</th>" +
									"<th>" + number_format(exam_tot,1)+"/"+grand_tot + "</th>" +
									"<th>" + number_format(tot,1)+"/"+(grand_tot*2) + "</th></tr>");
							$('.table-marks > tfoot').append("<tr><th colspan='4'>Percentage</th>" +
									"<th>" + number_format(tot/(grand_tot*2)*100,1) + "%</th></tr>");
						}

					},
					error: function (error) {
						// handle error
						$('.table-marks').hide();
						btn.text('FETCH').prop('disabled', false);
						lblError.text("Error: " + error.responseJSON.message);
					},
					async: true,
					dataType: "json",
					cache: false,
					contentType: false,
					processData: false
				});
			}
		})
	})
	number_format = function (number, decimals) {
		number = parseFloat(number);
		number = number.toFixed(decimals);
		const dec_point = '.';
		const thousands_sep = ',';
		var nstr = number.toString();
		nstr += '';
		x = nstr.split('.');
		x1 = x[0];
		x2 = x.length > 1 ? dec_point + x[1] : '';
		var rgx = /(\d+)(\d{3})/;

		while (rgx.test(x1))
			x1 = x1.replace(rgx, '$1' + thousands_sep + '$2');

		return x1 + x2;
	}
</script>
