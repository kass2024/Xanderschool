<style>
	.nav-tabs .nav-link.active {
		color: #495057;
		background-color: #fff;
		border-color: #fff;
		border-bottom: #d92550 solid 3px;
	}
</style>
<div class="row" style="background-color: white;height: auto;padding: 10px">
	<div class="form-group col-sm-4 col-md-2 col-lg-2">
		<label><?= lang("app.year"); ?>:</label>
		<select class="select2" id="select_year" name="year" required>
			<option disabled selected><?= lang("app.academicYear"); ?></option>
			<?php
			foreach ($years as $year):
				echo "<option value='{$year['id']}'>{$year['title']}</option>";
			endforeach;
			?>
		</select>
	</div>

	<div class="form-group col-sm-4 col-md-2 col-lg-2">
		<label><?= lang("app.sClass"); ?>:</label>
		<select class="form-control select2" name="class" id="select_class" required>
			<option selected disabled><?= lang("app.selectClass"); ?></option>
			<?php foreach ($classes as $classe) {
				?>
				<option value="<?= $classe['id']; ?>"
						data-id=""> <?= $classe['level_name']; ?> <?= $classe['code']; ?> <?= $classe['title']; ?></option>
				<?php
			}
			?>
		</select>
	</div>
	<div class="form-group col-sm-4 col-md-2 col-lg-2" id="studentDiv">
		<label><?= lang("app.student"); ?>:</label>
		<select class="form-control select2" id="select_student" name="select_student" required>


		</select>
	</div>
	<div class="form-group" style="margin-top: 30px">
		<button class="btn btn-success" id="btn_generate"><?= lang("app.go"); ?></button>
	</div>
</div>
<br>
<br>

<div class="paidContent" style="display: none">
	<div style="background-color: white;padding: 10px;margin: 15px 15px;display: flow-root;">
		<div class="pull-right" style="margin-bottom: 10px">
			<button class="btn btn-gradient-info btn-lg pull-left" id="btn-add-fees" style="margin-right: 10px">
				<i class="fa fa-plus"> <?= lang("app.addExtra"); ?></i></button>
			<button class="btn btn-success btn-lg pull-left" data-toggle="modal" data-target="#mdlfeesEntry"
					id="btnfees">
				<i class="fa fa-plus"> <?= lang("app.recordInvoice"); ?></i></button>
		</div>
		<div style="width: 100%;float: right">
			<ul class="nav nav-tabs" id="myTab" role="tablist">
				<li class="nav-item" style="width: 50%">
					<a class="nav-link active" id="summary-tab" data-toggle="tab" href="#fees-summary-tab"
					   role="tab"
					   aria-controls="home" aria-selected="true" style="font-size: 16px;font-weight: 800">Fees summary
						report</a>
				</li>
				<li class="nav-item" style="width: 50%">
					<a class="nav-link" id="history-tab" data-toggle="tab" style="font-size: 16px;font-weight: 800"
					   href="#fees-historical-report-tab"
					   role="tab"
					   aria-controls="profile" aria-selected="false">Fees historical report</a>
				</li>
			</ul>
			<div class="tab-content" id="myTabContent">
				<div class="tab-pane fade show active" id="fees-summary-tab" role="tabpanel"
					 aria-labelledby="home-tab">
					<div class="pull-left" style="width: 100%;background-color: white;">
						<table border="1" style="width: 100%">
							<th>#</th>
							<th><?= lang("app.item"); ?></th>
							<th><?= lang("app.term"); ?></th>
							<th><?= lang("app.expectedAmount"); ?></th>
							<th><?= lang("app.paidAmount"); ?></th>
							<th><?= lang("app.remainAmount"); ?></th>
							<th><?= lang("app.dueDate"); ?></th>
							<tbody name="tblfees">

							</tbody>
						</table>
					</div>
				</div>
				<div class="tab-pane fade" id="fees-historical-report-tab" role="tab-pane"
					 aria-labelledby="profile-tab">
					<form action="<?= base_url('printFeesHistory'); ?>" method="post" id="printForm">
						<div class="pull-right" style="margin-bottom: 10px">
							<button type="submit" class="btn btn-outline-success btn-lg pull-right">
								<i class="fa fa-print"></i> Print selected items
							</button>
						</div>
						<div class="table-responsive">
							<table class="table" id="historyTable">
								<thead>
								<tr style="background-color: #da2953;color: #fff;">
									<th scope="col"></th>
									<th scope="col">No</th>
									<th scope="col">Term</th>
									<th scope="col">Item</th>
									<th scope="col">Amount</th>
									<th scope="col">Payment mode</th>
									<th scope="col">date</th>
									<th scope="col"></th>
								</tr>
								</thead>
								<tbody>

								</tbody>
							</table>
						</div>
					</form>
					<a target="_blank" id="printLink" style="display: none"></a>
				</div>
			</div>
		</div>
	</div>
</div>
<script>
	$(function () {
		let tempInvoice=[]
		$(document).on("click", ".removeBtn", (e) => {
			let id = e.currentTarget.dataset.id
			let type = e.currentTarget.dataset.type
			$.each(tempInvoice, (index, record) => {
				if (record.id == id && record.type == type) {
					tempInvoice.splice(index,1)
					$(e.currentTarget).parent().parent().remove()
					return
				}
			})
		})
		let itemType, itemExtraId, itemTypeText, itemAmount, itemMode, itemSchoolFeesTerm, itemText, itemModeText,itemToSave
		let tblRow = ''
		let rowNum = 1
		$(document).on('submit', '.autoSubmits', (function (e) {
			e.preventDefault()
			$("#frmSaveFeesRecords").slideDown();
			itemType = $("#select_fees_type").val()
			itemExtraId = $("#ExtrafeeType").val()
			itemSchoolFeesTerm = $("#schoolfeesType option:selected").val()
			itemAmount = $("#receivedAmount").val()
			itemMode = $("#paymentMode option:selected").val()
			itemModeText = $("#paymentMode option:selected").text()
			itemTypeText = $("#select_fees_type option:selected").text()
			if (itemType == 0) {
				itemText = $("#schoolfeesType option:selected").text()
				itemToSave=itemSchoolFeesTerm
				tblRow = '<tr>' +
					'<td>' + rowNum + '</td>' +
					'<td><input type="hidden" name="items[]" value="' + itemSchoolFeesTerm + '">' + itemText + '</td>' +
					'<td><input type="hidden" name="feeTypes[]" value="' + itemType + '">' + itemTypeText + '</td>' +
					'<td><input type="hidden" name="amounts[]" value="' + itemAmount + '">' + itemAmount + '</td>' +
					'<td><input type="hidden" name="modes[]" value="' + itemMode + '">' + itemModeText + '</td>' +
					'<td style="text-align: center"><a class="btn btn-sm btn-danger removeBtn" data-type="'+itemType+'" data-id="'+itemSchoolFeesTerm+'" style="color: #ffffff">Remove</a></td>' +
					'</tr>'
			}
			if (itemType == 1) {
				itemText = $("#ExtrafeeType option:selected").text()
				itemToSave=itemExtraId
				tblRow = '<tr>' +
					'<td>' + rowNum + '</td>' +
					'<td><input type="hidden" name="items[]" value="' + itemExtraId + '">' + itemText + '</td>' +
					'<td><input type="hidden" name="feeTypes[]" value="' + itemType + '">' + itemTypeText + '</td>' +
					'<td><input type="hidden" name="amounts[]" value="' + itemAmount + '">' + itemAmount + '</td>' +
					'<td><input type="hidden" name="modes[]" value="' + itemMode + '">' + itemModeText + '</td>' +
					'<td style="text-align: center"><a class="btn btn-sm btn-danger removeBtn" data-type="'+itemType+'" data-id="'+itemExtraId+'" style="color: #ffffff">Remove</a></td>' +
					'</tr>'
			}

			let verify=true
			$.each(tempInvoice, (index, record) => {
				if (record.id == itemToSave && record.type == itemType) {
					verify=false
					alert("Item already exit")
					return 0
				}
			})
			if (verify) {
				$("#pendingTbl tbody").append(tblRow)
				$(".autoSubmits")[0].reset()
				$('.select2').select2();
				tempInvoice.push({"type": itemType, "id": itemToSave})
				rowNum++
			}
		}))
		let checkedItems = []
		$(document).on("change", ".checkedItem", function () {
			let checked = $(this).val()
			if ($(this).is(":checked")) {
				if (checkedItems.indexOf(checked) == -1) {
					checkedItems.push(checked)
				}
			} else {
				if ((index = checkedItems.indexOf(checked)) !== -1) {
					checkedItems.splice(index, 1)
				}
			}
			console.log(checkedItems)
		})
		$(document).on("click", ".btn-del-fee", function () {
			const title = $(this).parent('td').children('span').text()
			const id = $(this).parent('td').data('id')
			if (confirm("Please confirm Extra fee removal #"+title)){
				$.post("<?=base_url();?>remove_extra_fee", "id="+id, function (data) {
					if (data.hasOwnProperty('success')) {
						toastada.success(data.success);
						reloadSummaryReport();
					} else {
						toastada.error(data.error);
					}
				});
			}
		})

		$("#printForm").submit(function (e) {
			let student = $("#select_student").val()
			var year = $("#select_year").val();
			e.preventDefault();
			let form = $(this).serializeArray();
			form.push({name: "student", value: student})
			form.push({name: "year", value: year})
			let rows = checkedItems.join('-')
			$("#printLink").attr("href", "<?=base_url('printFeesHistory');?>" + "/" + rows + "/" + student)[0].click();
		})
		$(".paidContent").hide()
		$("#select_class").on("change", function () {
			var classe = $(this).val();
			var isclass = "/1";
			var type = "/7/";
			let academicYear=$('#select_year').val()
			$.get("<?=base_url();?>get_student/" + classe + isclass + type + academicYear, function (data) {
				$("[name='select_student']").html(data);
			});
		});

		$("#btn-add-fees").on("click", function (e) {
			var std = $("#select_student").val();
			$.getJSON("<?=base_url();?>get_student_json/" + std, function (data) {
				if (data.hasOwnProperty('success')) {
					$("#mdlExtraFeesStudent [name='studentName']").val(data.student.names);
					$("#mdlExtraFeesStudent [name='studentId']").val(data.student.id);
					$("#mdlExtraFeesStudent").modal();
				} else {
					toastada.error("Invalid student: " + data.message)
				}
			});
		});
		$(document).on("click",".btn-append-fees", function (e) {
			var std = $("#select_student").val();
			const btn = $(this);
			$.getJSON("<?=base_url();?>get_student_json/" + std, function (data) {
				if (data.hasOwnProperty('success')) {
					$("#mdlDiscountFeesStudent [name='studentName']").val(data.student.names);
					$("#mdlDiscountFeesStudent [name='studentId']").val(data.student.id);
					$("#mdlDiscountFeesStudent [name='feeId']").val(btn.data('id'));
					$("#mdlDiscountFeesStudent [name='feeAmount']").val(btn.data('amount'));
					$("#mdlDiscountFeesStudent").modal();
				} else {
					toastada.error("Invalid student: " + data.message)
				}
			});
		});
		$(document).on("click",".btn-edit-extra-fees", function (e) {
			var std = $("#select_student").val();
			const btn = $(this);
			$.getJSON("<?=base_url();?>get_student_json/" + std, function (data) {
				if (data.hasOwnProperty('success')) {
					$("#mdlEditExtraFeesStudent [name='studentName']").val(data.student.names);
					$("#mdlEditExtraFeesStudent [name='studentId']").val(data.student.id);
					$("#mdlEditExtraFeesStudent [name='feeId']").val(btn.data('id'));
					$("#mdlEditExtraFeesStudent [name='feeAmount']").val(btn.data('amount'));
					$("#mdlEditExtraFeesStudent").modal();
				} else {
					toastada.error("Invalid student: " + data.message)
				}
			});
		});
		$(document).on("blur","#feeNewAmount", function (e) {
			const amount = $("#feeNewAmount").val() - $("#feeOldAmount").val();
			$("#spAmountChangeDiscount").hide();
			$("#spAmountChangeIncrease").hide();
			if (amount>0) {
				$("#spAmountChangeIncrease").show();
			}else {
				$("#spAmountChangeDiscount").show();
			}
			$("#spAmountChange").text(amount);
		});
		$("#btn_generate").on("click", function (e) {
			reloadSummaryReport();
		});
		$("#mdlfeesEntry").on("shown.bs.modal", function (e) {
			var std = $("#select_student").val();
			$("#mdlfeesEntry [name='studentid']").val(std).change();
		});

		$("#select_fees_type").on("change", function () {
			var schltype = $(this).val();
			var classe = $("#select_class").val();
			var year = $("#select_year").val();
			var std = $("#select_student").val();
			if (schltype == 1) {
				$.get("<?=base_url();?>get_extra_fees/" + classe + "/" + std, function (data) {
					$("[name='ExtrafeeType']").html(data);
					$("#extrafeesType").show();
					$("#schoolfeesType").hide();
				});
			} else {
				$.get("<?=base_url();?>get_school_fees/" + year + "/" + std + "/" + classe, function (data) {
					$("[name='select_term']").html(data);
					$("#extrafeesType").hide();
					$("#schoolfeesType").show();
				});
				$("#schoolfeesType").show();
				$("#extrafeesType").hide();
			}

		});
		$("#select_fees_term").on("change", function () {
			var sdt = $("#select_student").val();
			var term = $(this).val();
			$.getJSON("<?=base_url();?>get_schoolfees_single_record/" + term + "/" + sdt, function (data) {
				var remain = data.schlfee_amt - data.paid_amt;
				var expected = data.schlfee_amt;
				var paid = data.paid_amt
				if (expected == paid) {
					$("#remainDiv").hide();
					$("#recievedDiv").hide();
					$("#duedateDiv").hide();
					$("#btnSave").prop("disabled", true);
				} else {
					$("#remainDiv").show();
					$("#recievedDiv").show();
					$("#duedateDiv").show();
					$("#btnSave").prop("disabled", false);
				}
				$("#mdlfeesEntry [name='expected_amount']").val(data.schlfee_amt).change();
				$("#mdlfeesEntry [name='paid_amount']").val(data.paid_amt).change();
				$("#mdlfeesEntry [name='remain_amount']").val(remain).change();
			});

		});
		$("#ExtrafeeType").on("change", function () {
			let extra = $(this).val();
			let sdt = $("#studentId").val();
			$.getJSON("<?=base_url();?>get_extra_single_record/" + extra + "/" + sdt, function (data) {
				let remain = Number(data.extra_amt - data.paid_amt)
				let expected = data.extra_amt;
				let paid = data.paid_amt
				if (expected == paid) {
					$("#remainDiv").hide();
					$("#recievedDiv").hide();
					$("#duedateDiv").hide();
					$("#btnSave").prop("disabled", true);
				} else {
					$("#remainDiv").show();
					$("#recievedDiv").show();
					$("#duedateDiv").show();
					$("#btnSave").prop("disabled", false);
				}
				$("#mdlfeesEntry [name='expected_amount']").val(data.extra_amt).change();
				$("#mdlfeesEntry [name='paid_amount']").val(data.paid_amt)
				$("#mdlfeesEntry [name='remain_amount']").val(remain)
			});

		});
		let year1, student1, rows1
		$('#history-tab').on('click', function (e) {
			year1 = $("#select_year").val()
			student1 = $("#select_student").val()
			$("#historyTable tbody").html(" ")
			$.getJSON("<?=base_url();?>getFeesHistoricalAjax/" + student1 + "/" + year1, (data) => {
				rows1 = ""
				$.each(data, (index, record) => {
					const dt = encodeURIComponent(record.id + ':' + record.type);
					const style = record.status == -1 ? "color: red;text-decoration: line-through;" : "";
					rows1 += "<tr style='" + style + "'>" +
						"<td>" + (record.status == -1 ? '' : "<input type='checkbox' name='toPrint[]' class='checkedItem' value='" + dt + "'>") + "</td>" +
						"<td>" + (index + 1) + "</td>" +
						"<td>" + getJsTermToString(record.term) + "</td> " +
						"<td>" + record.item + "</td> " +
						"<td>" + record.amount + " Rwf</td>" +
						"<td>" + paymentModeToString(record.payment_mode) + "</td>" +
						"<td>" + record.date + "</td>" +
						"<td>" + (record.status == -1 ? '' : '<button type="button" class="btn btn-warning" id="btn-cancel-invoice" ' +
							'data-title="' + record.item + ' of ' + getJsTermToString(record.term) + '" data-toggle="delete" ' +
							'data-target="' + record.id + '"  data-href="cancel_fee_record/' + record.id + '">Cancel invoice</button>') + "</td>" +
						"</tr>"
				})
				$("#historyTable tbody").html(rows1)
			});
		})
	})
	function reloadSummaryReport() {
		var classe = $("#select_class").val();
		var year = $("#select_year").val();
		var std = $("#select_student").val();
		// alert(classe);
		$.get("<?=base_url();?>get_student_fees/" + year + "/" + std + "/" + classe, function (data) {
			$("[name='tblfees']").html(data);
			$(".paidContent").show();
		});
	}

	function getJsTermToString(term) {
		switch (term) {
			case '1':
				return "First term"
			case '2':
				return "Second term"
			case '3':
				return "Third term"
			default:
				return term
		}
	}

	function paymentModeToString(mode) {
		switch (mode) {
			case '1':
				return "Bank slip"
			case '2':
				return "Cash"
			case '3':
				return "Cheque"
			case '4':
				return "MTN Momo"
			case '5':
				return "Airtel Money"
			default:
				return mode
		}
	}
</script>
