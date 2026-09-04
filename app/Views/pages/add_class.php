<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.20/css/dataTables.bootstrap4.min.css">
<style>
	.class-inline-hint { font-size: 12px; color: #64748b; margin: 0 0 10px 10px; }
	.class-title-spedit {
		min-width: 40px;
		cursor: pointer;
		display: inline-block;
	}
	.class-title-spedit:hover { background: #f1f5f9; border-radius: 4px; }
	.class-mentor-cell { white-space: nowrap; }
	.class-mentor-label.empty { color: #94a3b8; font-style: italic; }
	.class-mentor-pick {
		cursor: pointer;
		margin-left: 6px;
		font-size: 16px;
		vertical-align: middle;
		color: #64748b;
	}
	.class-mentor-pick:hover { color: #0f766e; }
	.class-mentor-cell .select2-container { min-width: 200px; text-align: left; }
	.class-mentor-cell .select2-container--default .select2-selection--single {
		height: 34px;
		border-radius: 8px;
		border-color: #cbd5e1;
	}
	.class-mentor-cell .select2-container--default .select2-selection--single .select2-selection__rendered {
		line-height: 32px;
		font-size: 13px;
	}
	.class-mentor-cell .select2-container--default .select2-selection--single .select2-selection__arrow {
		height: 32px;
	}
</style>
<button class="btn btn-success btn-lg" data-toggle="modal" data-target="#exampleModal" style="margin-left: 10px"><?= lang("app.addNewClass");?></button>
<p class="class-inline-hint"><i class="fa fa-info-circle"></i> Double-click <strong>Title</strong> to rename. Use the <i class="typcn typcn-edit"></i> icon to change <strong>Mentor</strong>.</p>

<div class="boxed">
	<table class="table table-striped table-bordered" id="classTable" style="margin: 0; text-align:center;">
		<tbody>
			<tr>
				<th><?= lang("app.type");?></th>
				<th><?= lang("app.code");?></th>
				<th><?= lang("app.faculity");?></th>
				<th><?= lang("app.level");?></th>
				<th><?= lang("app.title");?></th>
				<th><?= lang("app.mentor");?></th>
				<th><?= lang("app.course");?></th>
				<th><?= lang("app.student");?></th>
				<th><?= lang("app.action");?></th>
			</tr>
		<?php
		foreach ($classes as $class) {
			$mentorId = (int) ($class['idstf'] ?? 0);
			$mentorName = trim((string) ($class['mentor_name'] ?? ''));
			$mentorLabelClass = $mentorName === '' ? 'class-mentor-label empty' : 'class-mentor-label';
			$mentorLabel = $mentorName !== '' ? esc($mentorName) : 'Not assigned';
			$titleVal = esc($class['title'], 'attr');
			echo "<tr>
<td>".\App\Controllers\Home::typeToStr($class['type'])."</td>
<td>".esc($class['faculty_code'])."</td>
<td>".esc($class['department_name'])."</td>
<td>".esc($class['level_name'])."</td>
<td>
<span class='class-title-spedit' data-id='".(int)$class['id']."' data-value='{$titleVal}' title='Double-click to edit'>&nbsp;".esc($class['title'])."</span>
</td>
<td class='class-mentor-cell'>
<span class='{$mentorLabelClass}'>{$mentorLabel}</span>
<i class='typcn typcn-edit btn-link class-mentor-pick' data-id='".(int)$class['id']."' data-mentor='{$mentorId}' title='Select staff'></i>
</td>
<td><span class='text-success'>Courses: ".(int)$class['courses']."</span></td>
<td><span class='text-warning'>Students: ".(int)$class['students']."</span></td>
<td><label class='typcn typcn-delete text-danger link' data-toggle='delete' data-title=' class #".esc($class['level_name'].' '.$class['code'].' '.$class['title'])."'
														   data-target='".(int)$class['id']."'  data-href='delete_class'>". lang('app.del') ."</label></td>
</tr>";
		}
		?>
		</tbody>
	</table>
</div>

<script type="text/javascript" src="https://cdn.datatables.net/1.10.20/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.10.20/js/dataTables.bootstrap4.min.js"></script>

<script type="text/javascript">
	var schoolStaff = <?= json_encode(array_values(array_map(static function ($s) {
		return ['id' => (int) $s['id'], 'name' => trim($s['fname'] . ' ' . $s['lname'])];
	}, $staffs ?? []))); ?>;

	function staffSelectHtml(selectedId) {
		var html = "<select class='form-control form-control-sm class-mentor-select select2'>";
		html += "<option value=''>— Select staff —</option>";
		schoolStaff.forEach(function (st) {
			var sel = parseInt(selectedId, 10) === st.id ? " selected" : "";
			html += "<option value='" + st.id + "'" + sel + ">" + st.name + "</option>";
		});
		html += "</select>";
		return html;
	}

	function initMentorSelect2($sel) {
		if (!$sel.length || typeof $.fn.select2 !== 'function') return;
		$sel.select2({
			width: '220px',
			placeholder: 'Search staff…',
			allowClear: true,
			dropdownParent: $(document.body)
		});
		$sel.on('select2:open', function () {
			setTimeout(function () {
				$('.select2-container--open .select2-search__field').focus();
			}, 0);
		});
	}

	function destroyMentorSelect2($sel) {
		if ($sel.length && $sel.data('select2')) {
			try { $sel.select2('destroy'); } catch (e) { /* ignore */ }
		}
	}

	$(document).ready(function () {
		$('#classTable').DataTable({ searching: true, pageLength: 25 });
	});

	$(function () {
		var titleSp, titleOldHtml, titleVal, titleId;

		// Title: same spedit pattern as school settings
		$('#classTable').on('dblclick', '.class-title-spedit', function (e) {
			e.preventDefault();
			e.stopPropagation();
			titleSp = $(this);
			if (titleSp.find('input').length) return;
			titleVal = titleSp.data('value');
			if (titleVal === undefined || titleVal === null) {
				titleVal = $.trim(titleSp.text());
			}
			titleOldHtml = titleSp.html();
			titleId = titleSp.data('id');
			var editVal = String(titleVal);
			if (editVal === '-----') editVal = '';
			titleSp.html("<input type='text' class='form-control form-control-sm class-title-input' value='" + editVal.replace(/'/g, '&#39;') + "' placeholder='Optional (e.g. A, B)'>");
			titleSp.find('.class-title-input').focus().select();
		});

		$(document).on('keydown blur focusout', '.class-title-input', function (e) {
			if (e.type === 'keydown' && e.which === 27) {
				titleSp.html(titleOldHtml);
				return;
			}
			if (e.type === 'keydown' && e.which !== 13) return;
			if (e.type === 'keydown') e.preventDefault();

			var $input = $(this);
			if ($input.data('saving')) return;
			var val = $.trim($input.val());
			var prev = String(titleVal == null ? '' : titleVal).trim();
			// Empty and ----- are the same "cleared" title in the list view.
			var sameCleared = (val === '' || val === '-----') && (prev === '' || prev === '-----');
			if (val === prev || sameCleared) {
				titleSp.html(titleOldHtml);
				return;
			}
			$input.data('saving', true);
			var display = (val === '' || val === '-----') ? '-----' : val;
			var saveVal = (val === '-----') ? '' : val;
			titleSp.html('&nbsp;' + $('<div>').text(display).html());
			titleSp.data('value', display);
			$.post("<?= base_url('manipulateClassChanges'); ?>", { key: titleId, value: saveVal, field: 'title' })
				.done(function (res) {
					var shown = (res && res.display_title) ? res.display_title : display;
					titleSp.html('&nbsp;' + $('<div>').text(shown).html());
					titleSp.data('value', shown);
					if (window.toastada) toastada.success(res.success);
					else if (typeof toastMsg === 'function') toastMsg(1, res.success);
				})
				.fail(function (xhr) {
					titleSp.html(titleOldHtml);
					titleSp.data('value', titleVal);
					var msg = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'Save failed';
					if (window.toastada) toastada.error(msg);
					else if (typeof toastMsg === 'function') toastMsg(0, msg);
				});
		});

		// Mentor: pen icon opens staff dropdown
		$('#classTable').on('click', '.class-mentor-pick', function (e) {
			e.preventDefault();
			e.stopPropagation();
			var $btn = $(this);
			var $td = $btn.closest('td');
			if ($td.find('.class-mentor-select').length) return;
			var classId = $btn.data('id');
			var mentorId = $btn.data('mentor') || '';
			var $label = $td.find('.class-mentor-label');
			$label.hide();
			$btn.hide();
			var $sel = $(staffSelectHtml(mentorId));
			$sel.data('class-id', classId);
			$sel.data('prev-label', $label.text());
			$sel.data('prev-mentor', mentorId);
			$td.append($sel);
			initMentorSelect2($sel);
			$sel.select2('open');
		});

		function closeMentorEditor($sel, restore) {
			var $td = $sel.closest('td');
			var $label = $td.find('.class-mentor-label');
			var $btn = $td.find('.class-mentor-pick');
			if (restore) {
				$label.text($sel.data('prev-label'));
				if (!$sel.data('prev-mentor')) {
					$label.addClass('empty');
				}
			}
			$label.show();
			$btn.show();
			destroyMentorSelect2($sel);
			$sel.remove();
		}

		$(document).on('change', '.class-mentor-select', function () {
			var $sel = $(this);
			var staffId = parseInt($sel.val(), 10);
			var classId = $sel.data('class-id');
			if (!staffId) return;
			$sel.data('committed', true);
			var staffName = $sel.find('option:selected').text();
			$.post("<?= base_url('manipulateClassChanges'); ?>", { key: classId, value: staffId, field: 'mentor' })
				.done(function (res) {
					var $td = $sel.closest('td');
					var $label = $td.find('.class-mentor-label');
					var $btn = $td.find('.class-mentor-pick');
					$label.text(staffName).removeClass('empty').show();
					$btn.data('mentor', staffId).show();
					destroyMentorSelect2($sel);
					$sel.remove();
					if (window.toastada) toastada.success(res.success);
					else if (typeof toastMsg === 'function') toastMsg(1, res.success);
				})
				.fail(function (xhr) {
					$sel.data('committed', false);
					closeMentorEditor($sel, true);
					var msg = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'Save failed';
					if (window.toastada) toastada.error(msg);
					else if (typeof toastMsg === 'function') toastMsg(0, msg);
				});
		});

		$(document).on('select2:close', '.class-mentor-select', function () {
			var $sel = $(this);
			setTimeout(function () {
				if ($sel.data('committed')) return;
				if ($sel.closest('td').find('.class-mentor-select').length) {
					closeMentorEditor($sel, true);
				}
			}, 120);
		});

		$('#select_faculty').hide();

		$("#type_select").on("change", function () {
			$('#select_dept').hide();
			$('#select_level').hide();
			$('#select_teacher').hide();
			$('#select_sub').hide();
			var value = $(this).val();
			if (value == 1) {
				$('#labels').text('Sector');
				$('#depts').text('Trades');
				$('#levels').text('RTQFs');
				$.get("<?=base_url();?>get_levels/" + value, function (data) {
					$("[name='levels']").html(data);
				});
			}
			if (value == 2) {
				$('#labels').text('Levels');
				$('#depts').text('Combination');
				$('#levels').text('Class');
			}
			if (value == 3) {
				$('#labels').text('<?= lang("app.faculty"); ?>');
				$('#depts').text('<?= lang("app.dept"); ?>');
				$('#levels').text('<?= lang("app.level"); ?>');
			}
			$.get("<?=base_url();?>get_faculty/" + value, function (data) {
				$("[name='faculty']").html(data);
				$('#select_faculty').show();
				if (value == 3) {
					var $fac = $("#faculty_select");
					var firstVal = $fac.find("option[value]").not(":disabled").first().val();
					if (firstVal) {
						$fac.val(firstVal).trigger("change");
					}
				}
			});
		});
	});

	$(function () {
		$('#select_dept').hide();
		$("#faculty_select").on("change", function () {
			var value = $("#faculty_select").val();
			$('#select_level').hide();
			$('#select_sub').hide();
			$('#select_teacher').hide();
			if ($("#type_select").val() == 3) {
				$.get("<?=base_url();?>get_dept/" + value, function (data) {
					$("[name='depts']").html(data);
					$('#select_dept').show();
					$.get("<?=base_url();?>get_levels/" + value + "/1", function (lv) {
						$("[name='levels']").html(lv);
					});
					var $dept = $("#dept_select");
					var firstDept = $dept.find("option[value]").not(":disabled").first().val();
					if (firstDept) {
						$dept.val(firstDept).trigger("change");
					}
				});
				return;
			}
			if (value == 2) {
				$('#select_dept').hide();
				$('#select_level').show();
				$('#select_sub').show();
				$('#select_teacher').show();
				$.get("<?=base_url();?>get_levels/" + value + "/1", function (data) {
					$("[name='depts']").html("<option value='1' selected>O Level</option>");
					$("[name='levels']").html(data);
				});
				return;
			}
			if (value == 3) {
				$('#select_dept').hide();
				$('#select_level').show();
				$('#select_sub').show();
				$('#select_teacher').show();
				$.get("<?=base_url();?>get_levels/" + value + "/1", function (data) {
					$("[name='depts']").html("<option value='2' selected>Primary</option>");
					$("[name='levels']").html(data);
				});
				return;
			}
			if (value == 19) {
				$('#select_dept').hide();
				$('#select_level').show();
				$('#select_sub').show();
				$('#select_teacher').show();
				$.get("<?=base_url();?>get_levels/" + value + "/1", function (data) {
					$("[name='depts']").html("<option value='110' selected>Nursery</option>");
					$("[name='levels']").html(data);
				});
				return;
			}
			if (value == 1) {
				$.get("<?=base_url();?>get_levels/" + value + "/1", function (data) {
					$("[name='levels']").html(data);
				});
			}
			$.get("<?=base_url();?>get_dept/" + value, function (data) {
				$("[name='depts']").html(data);
			});
			$('#select_dept').show();
		});
	});

	$(function () {
		$('#select_level').hide();
		$('#select_sub').hide();
		$('#select_teacher').hide();
		$("#dept_select").on("change", function () {
			$('#select_level').show();
			$('#select_sub').show();
			$('#select_teacher').show();
		});
	});
</script>
