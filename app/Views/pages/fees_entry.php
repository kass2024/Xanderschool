<link rel="stylesheet" href="<?= base_url('assets/css/fees-entry.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/card-scan-ui.css') ?>">

<div class="fe-page card-scan-page">
	<div class="fe-center">

		<div class="fe-layout">
			<aside class="fe-scan-panel">
				<?= view('pages/partials/card_scan_search', [
					'classes' => $classes,
					'use_lang' => true,
					'default_mode' => 'card',
					'student_placeholder' => 'Type student name or reg no...',
				]) ?>

				<div class="fe-year-field mt-3">
					<label for="select_year"><?= lang('app.year') ?></label>
					<select class="form-control select2" id="select_year" name="year" required>
						<?php foreach ($years as $year):
							$yrId = (int) $year['id'];
							$selected = ($yrId === (int) ($selectedYear ?? 0)) ? ' selected' : '';
							?>
							<option value="<?= $yrId ?>"<?= $selected ?>><?= esc($year['title']) ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</aside>

			<main class="fe-workspace">
				<div class="fe-workspace-card" id="feWorkspaceCard">
					<div class="fe-student-header" id="feStudentCard">
						<div class="fe-student-empty">
							<i class="fa fa-id-card"></i>
							<p>Scan a student card, search by name, or pick from a class to begin.</p>
						</div>
					</div>

					<div class="fe-class-pick" id="feClassPick">
						<h4><?= lang('app.student') ?> — tap to load fees</h4>
						<div class="fe-student-chips" id="feClassChips"></div>
					</div>

					<div class="paidContent fe-fees-section" style="display:none">
						<div class="fe-actions-bar">
							<button type="button" class="btn btn-info" id="btn-add-fees">
								<i class="fa fa-plus"></i> <?= lang('app.addExtra') ?>
							</button>
							<button type="button" class="btn btn-success" data-toggle="modal" data-target="#mdlfeesEntry" id="btnfees">
								<i class="fa fa-plus"></i> <?= lang('app.recordInvoice') ?>
							</button>
						</div>

						<ul class="nav nav-tabs fe-tabs" id="myTab" role="tablist">
							<li class="nav-item">
								<a class="nav-link active" id="summary-tab" data-toggle="tab" href="#fees-summary-tab" role="tab">
									Fees summary
								</a>
							</li>
							<li class="nav-item">
								<a class="nav-link" id="history-tab" data-toggle="tab" href="#fees-historical-report-tab" role="tab">
									Payment history
								</a>
							</li>
						</ul>

						<div class="tab-content" id="myTabContent">
							<div class="tab-pane fade show active" id="fees-summary-tab" role="tabpanel">
								<div class="fe-table-wrap">
									<table class="table table-hover mb-0">
										<thead>
										<tr>
											<th>#</th>
											<th><?= lang('app.item') ?></th>
											<th><?= lang('app.term') ?></th>
											<th><?= lang('app.expectedAmount') ?></th>
											<th><?= lang('app.paidAmount') ?></th>
											<th><?= lang('app.remainAmount') ?></th>
											<th><?= lang('app.dueDate') ?></th>
										</tr>
										</thead>
										<tbody name="tblfees"></tbody>
									</table>
								</div>
							</div>

							<div class="tab-pane fade" id="fees-historical-report-tab" role="tabpanel">
								<form action="<?= base_url('printFeesHistory') ?>" method="post" id="printForm">
									<div class="fe-actions-bar fe-actions-bar--compact">
										<button type="submit" class="btn btn-outline-success ml-auto">
											<i class="fa fa-print"></i> Print selected items
										</button>
									</div>
									<div class="fe-table-wrap">
										<table class="table table-hover mb-0" id="historyTable">
											<thead>
											<tr>
												<th scope="col"></th>
												<th scope="col">No</th>
												<th scope="col">Term</th>
												<th scope="col">Item</th>
												<th scope="col">Amount</th>
												<th scope="col">Payment mode</th>
												<th scope="col">Reference</th>
												<th scope="col">Date</th>
												<th scope="col">Status</th>
												<th scope="col"></th>
											</tr>
											</thead>
											<tbody></tbody>
										</table>
									</div>
								</form>
								<a target="_blank" id="printLink" style="display:none"></a>
							</div>
						</div>
					</div>
				</div>
			</main>
		</div>

		<!-- Legacy hidden selects — kept for modals and existing fee APIs -->
		<div class="sr-only" aria-hidden="true" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);">
			<select id="select_class" name="class">
				<option value=""><?= lang('app.selectClass') ?></option>
				<?php foreach ($classes as $classe): ?>
					<option value="<?= (int) $classe['id'] ?>">
						<?= esc("{$classe['level_name']} {$classe['code']} {$classe['title']}") ?>
					</option>
				<?php endforeach; ?>
			</select>
			<select id="select_student" name="select_student"></select>
		</div>

	</div>
</div>

<script src="<?= base_url('assets/js/card-uid.js') ?>"></script>
<script>
$(function () {
	let checkedItems = [];
	let year1, student1, rows1;
	let cardBuffer = '';

	function setScanStatus(text, type) {
		const $s = $('#cardScanStatus');
		$s.text(text).removeClass('ok err busy');
		if (type) $s.addClass(type);
	}

	function renderStudentCard(st) {
		const html = `
			<div class="fe-student-photo">${st.photo_html || ''}</div>
			<div class="fe-student-info">
				<h3>${st.name || ''}</h3>
				<div class="fe-meta">
					<span><strong>Reg:</strong> ${st.regno || ''}</span>
					<span class="fe-meta-sep">&middot;</span>
					<span><strong>Class:</strong> ${st.class_label || ''}</span>
				</div>
			</div>`;
		$('#feStudentCard').addClass('has-student').html(html);
		$('#feWorkspaceCard').addClass('has-fees-ready');
	}

	function clearStudentCard() {
		$('#feStudentCard').removeClass('has-student').html(`
			<div class="fe-student-empty">
				<i class="fa fa-id-card"></i>
				<p>Scan a student card, search by name, or pick from a class to begin.</p>
			</div>`);
		$('#feWorkspaceCard').removeClass('has-fees-ready');
		$('.paidContent').hide();
	}

	function syncHiddenSelects(st) {
		$('#select_class').val(st.class_id);
		$('#select_student').html(`<option value="${st.id}" selected>${st.regno} ${st.name}</option>`);
	}

	function loadStudent(studentId) {
		const year = $('#select_year').val();
		if (!studentId || !year) {
			toastada.error('Select academic year first.');
			return;
		}
		setScanStatus('⏳ Loading student...', 'busy');
		$.getJSON(`<?= base_url('fees_entry_student_context/') ?>${studentId}?year=${year}`, function (res) {
			if (!res.success) {
				setScanStatus('❌ ' + (res.error || 'Student not found'), 'err');
				toastada.error(res.error || 'Could not load student.');
				return;
			}
			syncHiddenSelects(res.student);
			renderStudentCard(res.student);
			reloadSummaryReport();
			$('#successAlert').show();
			setScanStatus('✅ ' + res.student.name + ' loaded', 'ok');
			$('.fe-student-chip').removeClass('active');
			$(`.fe-student-chip[data-id="${res.student.id}"]`).addClass('active');
		}).fail(function () {
			setScanStatus('⚠️ Network error', 'err');
			toastada.error('Unable to load student.');
		});
	}

	function handleCardScan(uid) {
		setScanStatus('⏳ Checking card...', 'busy');
		fetch('<?= base_url('api/permission_card_scan') ?>', {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: 'card=' + encodeURIComponent(uid) + '&school_id=<?= (int) session('soma_school_id') ?>'
		})
			.then(r => r.json())
			.then(res => {
				if (!res.success || res.error) {
					setScanStatus('❌ ' + (res.error || 'Card not found'), 'err');
					return;
				}
				if (res.student) loadStudent(res.student.id);
			})
			.catch(err => setScanStatus('⚠️ ' + err.message, 'err'));
	}

	$('#search_mode').on('change', function () {
		$('#successAlert').hide();
		if ($(this).val() === 'card') setScanStatus('Waiting for card...', '');
		if ($(this).val() !== 'class') $('#feClassPick').removeClass('visible');
	});

	$('#select_year').on('change', function () {
		const sid = $('#select_student').val();
		if (sid) loadStudent(sid);
		else clearStudentCard();
	});

	$('#student_search_input').on('keyup', function () {
		const term = $(this).val().trim();
		if (term.length < 2) {
			$('#student_search_box').hide().empty();
			return;
		}
		$.ajax({
			url: '<?= base_url('search_student') ?>',
			type: 'POST',
			dataType: 'json',
			data: { searchTerm: term },
			success: function (data) {
				let html = '';
				if (!data.length) {
					html = "<div class='text-muted text-center p-2'>No students found</div>";
				} else {
					data.forEach(st => {
						html += `<div class='card-scan-student-item student-item' data-id='${st.id}'>${st.text}</div>`;
					});
				}
				$('#student_search_box').html(html).show();
			}
		});
	});

	$(document).on('click', '.student-item', function () {
		const id = $(this).data('id');
		$('#student_search_input').val('');
		$('#student_search_box').hide();
		loadStudent(id);
	});

	$('#search_class').on('select2:select', function (e) {
		const classId = e.params.data.id;
		const year = $('#select_year').val();
		$.get(`<?= base_url('get_student/') ?>${classId}/1/7/${year}`, function (html) {
			const $tmp = $('<select>').html(html);
			let chips = '';
			$tmp.find('option').each(function () {
				const v = $(this).val();
				const t = $(this).text().trim();
				if (v && t) {
					chips += `<button type="button" class="fe-student-chip" data-id="${v}">${t}</button>`;
				}
			});
			$('#feClassChips').html(chips || '<span class="text-muted">No students in this class.</span>');
			$('#feClassPick').addClass('visible');
		});
	});

	$(document).on('click', '.fe-student-chip', function () {
		loadStudent($(this).data('id'));
	});

	document.addEventListener('keypress', function (e) {
		if ($('#search_mode').val() !== 'card') return;
		if (['receivedAmount', 'reason', 'destination'].includes(document.activeElement.id)) return;
		if (e.key === 'Enter') {
			const uid = cardBuffer.trim();
			cardBuffer = '';
			if (uid.length >= 4) {
				handleCardScan((window.CardUid && CardUid.forScan) ? CardUid.forScan(uid) : uid.replace(/[^A-Fa-f0-9]/g, '').toUpperCase());
			}
		} else {
			cardBuffer += e.key;
		}
	});

	$('#cardInput').on('focus', function () { $(this).blur(); });

	// ---- smart multi-line invoice modal ----

	function formatRwf(n) {
		return Number(n || 0).toLocaleString();
	}

	function feInvoiceUpdateTotal() {
		let total = 0;
		let count = 0;
		$('#feInvoiceBody .fe-inv-check:checked').each(function () {
			const $row = $(this).closest('tr');
			const amt = parseFloat($row.find('.fe-inv-amount').val()) || 0;
			const max = parseFloat($row.data('remain')) || 0;
			if (amt > 0 && amt <= max + 0.001) {
				total += amt;
				count++;
			}
		});
		const mode = $('#feInvoicePaymentMode').val();
		const slipOk = mode !== '1' || ($('#feInvoiceSlipRef').val() || '').trim().length > 0;
		$('#feInvoiceTotal').text(formatRwf(total));
		$('#feSaveCount').text(count > 0 ? '(' + count + ' item' + (count > 1 ? 's' : '') + ')' : '');
		$('#btnSave').prop('disabled', count === 0 || !mode || !slipOk);
	}

	function feToggleSlipRef() {
		const isBank = $('#feInvoicePaymentMode').val() === '1';
		$('#feSlipRefWrap').toggle(isBank);
		if (!isBank) {
			$('#feInvoiceSlipRef').val('');
		}
		feInvoiceUpdateTotal();
	}

	function feInvoiceRenderItems(items) {
		let html = '';
		let lastCat = '';
		items.forEach(function (it) {
			if (it.category !== lastCat) {
				html += '<tr class="fe-inv-section"><td colspan="7">' + it.category + '</td></tr>';
				lastCat = it.category;
			}
			html += '<tr class="fe-inv-row" data-id="' + it.id + '" data-type="' + it.fee_type + '" data-remain="' + it.remain + '">' +
				'<td><input type="checkbox" class="fe-inv-check"></td>' +
				'<td>' + it.label + '</td>' +
				'<td>' + it.term + '</td>' +
				'<td class="text-right">' + formatRwf(it.expected) + '</td>' +
				'<td class="text-right">' + formatRwf(it.paid) + '</td>' +
				'<td class="text-right fe-amount-due">' + formatRwf(it.remain) + '</td>' +
				'<td class="text-right"><input type="number" class="form-control form-control-sm fe-inv-amount text-right" min="1" step="1" max="' + it.remain + '" placeholder="0" disabled></td>' +
				'</tr>';
		});
		$('#feInvoiceBody').html(html);
	}

	function feInvoiceLoadItems() {
		const std = $('#select_student').val();
		const year = $('#select_year').val();
		const classe = $('#select_class').val();
		if (!std || !year || !classe) {
			toastada.error('Select a student first.');
			$('#mdlfeesEntry').modal('hide');
			return;
		}
		$('#studentId').val(std);
		$('#feInvoiceLoading').show();
		$('#feInvoiceEmpty').hide();
		$('#feInvoiceWrap').hide();
		$('#btnSave').prop('disabled', true);
		$('#feInvoicePaymentMode').val('');
		$('#feInvoiceDueDate').val('');
		$('#feInvoiceSlipRef').val('');
		feToggleSlipRef();

		$.getJSON('<?= base_url('get_fee_invoice_items/') ?>' + year + '/' + std + '/' + classe, function (res) {
			$('#feInvoiceLoading').hide();
			if (!res.success) {
				toastada.error(res.error || 'Could not load items.');
				return;
			}
			if (!res.items || !res.items.length) {
				$('#feInvoiceEmpty').show();
				return;
			}
			feInvoiceRenderItems(res.items);
			$('#feInvoiceWrap').show();
			feInvoiceUpdateTotal();
		}).fail(function () {
			$('#feInvoiceLoading').hide();
			toastada.error('Unable to load invoice items.');
		});
	}

	$('#mdlfeesEntry').on('shown.bs.modal', feInvoiceLoadItems);

	$(document).on('change', '.fe-inv-check', function () {
		const $row = $(this).closest('tr');
		const $amt = $row.find('.fe-inv-amount');
		if ($(this).is(':checked')) {
			$amt.prop('disabled', false);
			if (!$amt.val()) {
				$amt.val($row.data('remain'));
			}
			$amt.focus().select();
		} else {
			$amt.prop('disabled', true).val('');
		}
		feInvoiceUpdateTotal();
	});

	$(document).on('input', '.fe-inv-amount', feInvoiceUpdateTotal);

	$('#feInvoicePaymentMode').on('change', feToggleSlipRef);
	$('#feInvoiceSlipRef').on('input', feInvoiceUpdateTotal);

	$('#feInvSelectAll').on('click', function () {
		$('#feInvoiceBody .fe-inv-check').each(function () {
			$(this).prop('checked', true).trigger('change');
		});
	});

	$('#feInvFillBalance').on('click', function () {
		$('#feInvoiceBody .fe-inv-check:checked').each(function () {
			const $row = $(this).closest('tr');
			$row.find('.fe-inv-amount').val($row.data('remain'));
		});
		feInvoiceUpdateTotal();
	});

	$('#frmSaveFeesRecords').on('submit', function (e) {
		e.preventDefault();
		const mode = $('#feInvoicePaymentMode').val();
		if (!mode) {
			toastada.error('Select payment mode.');
			return;
		}
		const slipRef = ($('#feInvoiceSlipRef').val() || '').trim();
		if (mode === '1' && !slipRef) {
			toastada.error('<?= esc(lang('app.slipReferenceRequired')); ?>');
			return;
		}
		const payload = {
			studentid: $('#studentId').val(),
			dueDate: $('#feInvoiceDueDate').val(),
			slipRef: slipRef,
			'items[]': [],
			'feeTypes[]': [],
			'amounts[]': [],
			'modes[]': []
		};
		let hasError = false;
		let count = 0;
		$('#feInvoiceBody .fe-inv-row').each(function () {
			const $cb = $(this).find('.fe-inv-check');
			if (!$cb.is(':checked')) return;
			const amount = parseFloat($(this).find('.fe-inv-amount').val()) || 0;
			const max = parseFloat($(this).data('remain')) || 0;
			if (amount <= 0) return;
			if (amount > max + 0.001) {
				toastada.error('Amount exceeds balance for ' + $(this).find('td').eq(1).text());
				hasError = true;
				return false;
			}
			payload['items[]'].push($(this).data('id'));
			payload['feeTypes[]'].push($(this).data('type'));
			payload['amounts[]'].push(amount);
			payload['modes[]'].push(mode);
			count++;
		});
		if (hasError || count === 0) {
			if (!hasError) toastada.error('Select at least one item with a valid amount.');
			return;
		}
		const $btn = $('#btnSave');
		const btnHtml = $btn.html();
		$btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving…');
		$.ajax({
			url: '<?= base_url('manipulate_fee_entry') ?>',
			type: 'POST',
			data: payload,
			traditional: true,
			dataType: 'json',
			success: function (data) {
			$btn.prop('disabled', false).html(btnHtml);
			if (data.error) {
				toastada.error(data.error);
				return;
			}
			toastada.success(data.success);
			$('#mdlfeesEntry').modal('hide');
			reloadSummaryReport();
			if (data.url || data.print_url) {
				const w = window.open(data.url || data.print_url, '_blank', 'width=420,height=720');
				if (w) w.focus();
			}
			},
			error: function () {
				toastada.error('Save failed. Please try again.');
			},
			complete: function () {
				$btn.prop('disabled', false).html(btnHtml);
			}
		});
	});

	// ---- other fee entry handlers ----

	$(document).on('change', '.checkedItem', function () {
		let checked = $(this).val();
		if ($(this).is(':checked')) {
			if (checkedItems.indexOf(checked) === -1) checkedItems.push(checked);
		} else {
			const index = checkedItems.indexOf(checked);
			if (index !== -1) checkedItems.splice(index, 1);
		}
	});

	$(document).on('click', '.btn-del-fee', function () {
		const title = $(this).parent('td').children('span').text();
		const id = $(this).parent('td').data('id');
		if (confirm('Please confirm Extra fee removal #' + title)) {
			$.post('<?= base_url('remove_extra_fee') ?>', 'id=' + id, function (data) {
				if (data.hasOwnProperty('success')) {
					toastada.success(data.success);
					reloadSummaryReport();
				} else {
					toastada.error(data.error);
				}
			});
		}
	});

	$('#printForm').submit(function (e) {
		e.preventDefault();
		const student = $('#select_student').val();
		const selected = [];
		$('#historyTable .checkedItem:checked').each(function () {
			selected.push(decodeURIComponent($(this).val()));
		});
		if (!selected.length) {
			toastada.error('Select at least one approved payment to print.');
			return;
		}
		const rows = selected.join('-');
		$('#printLink').attr('href', '<?= base_url('printFeesHistory') ?>/' + rows + '/' + student)[0].click();
	});

	$('#btn-add-fees').on('click', function () {
		var std = $('#select_student').val();
		if (!std) { toastada.error('Select a student first.'); return; }
		$.getJSON('<?= base_url('get_student_json/') ?>' + std, function (data) {
			if (data.hasOwnProperty('success')) {
				$('#mdlExtraFeesStudent [name="studentName"]').val(data.student.names);
				$('#mdlExtraFeesStudent [name="studentId"]').val(data.student.id);
				$('#mdlExtraFeesStudent').modal();
			} else {
				toastada.error('Invalid student: ' + data.message);
			}
		});
	});

	$(document).on('click', '.btn-append-fees', function () {
		var std = $('#select_student').val();
		const btn = $(this);
		$.getJSON('<?= base_url('get_student_json/') ?>' + std, function (data) {
			if (data.hasOwnProperty('success')) {
				$('#mdlDiscountFeesStudent [name="studentName"]').val(data.student.names);
				$('#mdlDiscountFeesStudent [name="studentId"]').val(data.student.id);
				$('#mdlDiscountFeesStudent [name="feeId"]').val(btn.data('id'));
				$('#mdlDiscountFeesStudent [name="feeAmount"]').val(btn.data('amount'));
				$('#mdlDiscountFeesStudent').modal();
			} else {
				toastada.error('Invalid student: ' + data.message);
			}
		});
	});

	$(document).on('click', '.btn-edit-extra-fees', function () {
		var std = $('#select_student').val();
		const btn = $(this);
		$.getJSON('<?= base_url('get_student_json/') ?>' + std, function (data) {
			if (data.hasOwnProperty('success')) {
				$('#mdlEditExtraFeesStudent [name="studentName"]').val(data.student.names);
				$('#mdlEditExtraFeesStudent [name="studentId"]').val(data.student.id);
				$('#mdlEditExtraFeesStudent [name="feeId"]').val(btn.data('id'));
				$('#mdlEditExtraFeesStudent [name="feeAmount"]').val(btn.data('amount'));
				$('#mdlEditExtraFeesStudent').modal();
			} else {
				toastada.error('Invalid student: ' + data.message);
			}
		});
	});

	$(document).on('blur', '#feeNewAmount', function () {
		const amount = $('#feeNewAmount').val() - $('#feeOldAmount').val();
		$('#spAmountChangeDiscount').hide();
		$('#spAmountChangeIncrease').hide();
		if (amount > 0) {
			$('#spAmountChangeIncrease').show();
		} else {
			$('#spAmountChangeDiscount').show();
		}
		$('#spAmountChange').text(amount);
	});

	function reloadSummaryReport() {
		var classe = $('#select_class').val();
		var year = $('#select_year').val();
		var std = $('#select_student').val();
		if (!classe || !year || !std) return;
		$.get('<?= base_url('get_student_fees/') ?>' + year + '/' + std + '/' + classe, function (data) {
			$('[name="tblfees"]').html(data);
			$('.paidContent').show();
			loadPaymentHistory();
		});
	}

	function loadPaymentHistory() {
		const studentId = $('#select_student').val();
		const year = $('#select_year').val();
		if (!studentId || !year) {
			$('#historyTable tbody').html('');
			return;
		}
		$.getJSON('<?= base_url('getFeesHistoricalAjax/') ?>' + studentId + '/' + year, function (data) {
			let rows1 = '';
			checkedItems = [];
			$.each(data, function (index, record) {
				const dt = encodeURIComponent(record.id + ':' + record.type);
				const statusNum = parseInt(record.status, 10);
				const isCancelled = statusNum === -1;
				const canPrint = !isCancelled;
				let style = '';
				if (isCancelled) style = 'color:red;text-decoration:line-through;';
				const refCell = record.refNo ? record.refNo : '—';
				let statusCell = isCancelled ? '—' : "<span class='badge badge-success'><?= esc(lang('app.feeStatusApproved')); ?></span>";
				let actions = '';
				if (canPrint) {
					const printUrl = '<?= base_url('print_fee_receipt/') ?>' + dt + '/' + studentId + '?autoprint=1';
					actions = "<button type='button' class='btn btn-sm btn-success btn-print-receipt' data-url='" + printUrl + "'>" +
						"<i class='fa fa-print'></i> <?= esc(lang('app.printReceipt')); ?></button>";
				}
				rows1 += "<tr style='" + style + "'>" +
					'<td>' + (canPrint ? "<input type='checkbox' name='toPrint[]' class='checkedItem' value='" + dt + "'>" : '') + '</td>' +
					'<td>' + (index + 1) + '</td>' +
					'<td>' + getJsTermToString(record.term) + '</td> ' +
					'<td>' + record.item + '</td> ' +
					'<td>' + record.amount + ' Rwf</td>' +
					'<td>' + paymentModeToString(record.payment_mode) + '</td>' +
					'<td>' + refCell + '</td>' +
					'<td>' + record.date + '</td>' +
					'<td>' + statusCell + '</td>' +
					'<td>' + actions + '</td>' +
					'</tr>';
			});
			$('#historyTable tbody').html(rows1);
		});
	}

	$(document).on('click', '.btn-print-receipt', function () {
		const url = $(this).data('url');
		if (url) {
			const w = window.open(url, '_blank', 'width=420,height=720');
			if (w) w.focus();
		}
	});

	$('a[href="#fees-historical-report-tab"]').on('shown.bs.tab', loadPaymentHistory);
});

function getJsTermToString(term) {
	switch (term) {
		case '1': return 'First term';
		case '2': return 'Second term';
		case '3': return 'Third term';
		default: return term;
	}
}

function paymentModeToString(mode) {
	switch (mode) {
		case '1': return 'Bank slip';
		case '2': return 'Cash';
		case '3': return 'Cheque';
		case '4': return 'MTN Momo';
		case '5': return 'Airtel Money';
		default: return mode;
	}
}
</script>
