<style>
.as-wrap { padding: 8px 4px 24px; }
.as-head { display:flex; flex-wrap:wrap; gap:12px; align-items:center; justify-content:space-between; margin-bottom:12px; }
.as-tabs { display:inline-flex; background:#f1f5f9; border-radius:10px; padding:4px; gap:4px; }
.as-tab { border:0; background:transparent; padding:8px 16px; border-radius:8px; font-weight:600; color:#64748b; cursor:pointer; }
.as-tab.active { background:#0EA5E9; color:#fff; }
.as-note { background:#f0f9ff; border:1px solid #bae6fd; color:#0c4a6e; border-radius:10px; padding:10px 14px; font-size:13px; margin-bottom:14px; }
.as-note strong { color:#075985; }
.as-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:14px; }
@media (max-width: 992px) { .as-grid { grid-template-columns:1fr; } }
.as-col { background:#fff; border:1px solid #e2e8f0; border-radius:12px; min-height:420px; display:flex; flex-direction:column; overflow:hidden; }
.as-col--levels { border-color:#99f6e4; }
.as-col__h { padding:12px 14px; border-bottom:1px solid #e2e8f0; background:#f8fafc; display:flex; align-items:center; justify-content:space-between; gap:8px; }
.as-col--levels .as-col__h { background:#f0fdfa; }
.as-col__h h5 { margin:0; font-size:15px; font-weight:700; color:#0f172a; }
.as-col__h small { display:block; color:#64748b; font-weight:500; margin-top:2px; }
.as-col__b { flex:1; overflow:auto; padding:8px; }
.as-item { border:1px solid transparent; border-radius:10px; padding:10px 12px; margin-bottom:6px; cursor:pointer; transition:.15s; }
.as-item:hover { background:#f8fafc; }
.as-item.active { border-color:#0EA5E9; background:rgba(14,165,233,.08); }
.as-item--level { cursor:default; }
.as-item__t { font-weight:650; color:#0f172a; font-size:14px; }
.as-item__m { color:#64748b; font-size:12px; margin-top:2px; }
.as-item__a { float:right; display:flex; gap:4px; }
.as-item__a .btn { padding:2px 8px; font-size:11px; }
.as-empty { color:#94a3b8; text-align:center; padding:28px 12px; font-size:13px; }
.as-breadcrumb { font-size:13px; color:#64748b; margin-bottom:10px; }
.as-breadcrumb strong { color:#0f172a; }
.as-badge { display:inline-block; font-size:11px; font-weight:600; padding:2px 8px; border-radius:999px; background:#ccfbf1; color:#0f766e; }
.as-modal .modal-header { border-bottom:1px solid #e2e8f0; }
.as-modal .modal-title { font-size:16px; font-weight:700; }
.as-modal .form-group label { font-size:12px; font-weight:600; color:#475569; }
.as-modal .modal-parent { font-size:13px; color:#64748b; margin-bottom:12px; }
.as-modal .modal-parent strong { color:#0f172a; }
.modal.as-modal .modal-content { position: relative; z-index: 1; background: #fff; }
</style>

<div class="app-inner-layout app-inner-layout-page">
	<div class="app-inner-layout__wrapper">
		<div class="app-inner-layout__content">
			<div class="container-fluid as-wrap">
				<div class="as-head">
					<div>
						<h4 style="margin:0 0 4px;">Academic structure</h4>
						<div class="as-breadcrumb">
							<strong>Faculty</strong> → <strong>Department</strong>
							&nbsp;·&nbsp; <strong>Levels</strong> are shared (picked when creating a class)
						</div>
					</div>
					<div class="as-tabs" role="tablist">
						<button type="button" class="as-tab active" data-program="2">REB (General)</button>
						<button type="button" class="as-tab" data-program="1">TVET (RTB)</button>
						<button type="button" class="as-tab" data-program="3">Special</button>
					</div>
				</div>

				<div class="as-note" id="asNote">
					<strong>REB:</strong> All departments under a faculty share the same levels (e.g. S4–S6). Choose the level when creating a class.
				</div>

				<div class="as-grid">
					<div class="as-col" id="colFaculty">
						<div class="as-col__h">
							<div>
								<h5>1. Faculty</h5>
								<small id="facHint">Sector / education stream</small>
							</div>
							<button type="button" class="btn btn-sm btn-info" id="btnAddFac">+ Add</button>
						</div>
						<div class="as-col__b" id="listFaculty"><div class="as-empty">Loading…</div></div>
					</div>

					<div class="as-col" id="colDept">
						<div class="as-col__h">
							<div>
								<h5>2. Department</h5>
								<small id="deptHint">Belongs to selected faculty</small>
							</div>
							<button type="button" class="btn btn-sm btn-info" id="btnAddDept" disabled>+ Add</button>
						</div>
						<div class="as-col__b" id="listDept"><div class="as-empty">Select a faculty</div></div>
					</div>

					<div class="as-col as-col--levels" id="colLevel">
						<div class="as-col__h">
							<div>
								<h5>3. Shared levels <span class="as-badge" id="levelBadge">shared</span></h5>
								<small id="levelHint">Used by all departments — pick at class creation</small>
							</div>
							<button type="button" class="btn btn-sm btn-info" id="btnAddLevel" disabled>+ Add</button>
						</div>
						<div class="as-col__b" id="listLevel"><div class="as-empty">Select a faculty (REB) or open TVET tab</div></div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- Faculty modal -->
<div class="modal fade as-modal" id="mdlFaculty" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="mdlFacultyTitle">Add faculty</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			</div>
			<div class="modal-body">
				<input type="hidden" id="facId" value="">
				<div class="form-group">
					<label>Faculty name <span class="text-danger">*</span></label>
					<input type="text" class="form-control" id="facTitle" placeholder="e.g. Information and Communication Technology (ICT)">
				</div>
				<div class="form-group mb-0">
					<label>Abbreviation</label>
					<input type="text" class="form-control" id="facAbbrev" placeholder="Optional">
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
				<button type="button" class="btn btn-success" id="btnSaveFac">Save faculty</button>
			</div>
		</div>
	</div>
</div>

<!-- Department modal -->
<div class="modal fade as-modal" id="mdlDepartment" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="mdlDeptTitle">Add department</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			</div>
			<div class="modal-body">
				<div class="modal-parent" id="mdlDeptParent"></div>
				<input type="hidden" id="deptId" value="">
				<div class="form-group">
					<label>Department name <span class="text-danger">*</span></label>
					<input type="text" class="form-control" id="deptTitle" placeholder="e.g. Office Management / PCM">
				</div>
				<div class="form-group mb-0">
					<label>Code</label>
					<input type="text" class="form-control" id="deptCode" placeholder="e.g. OMG / PCM">
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
				<button type="button" class="btn btn-success" id="btnSaveDept">Save department</button>
			</div>
		</div>
	</div>
</div>

<!-- Level modal -->
<div class="modal fade as-modal" id="mdlLevel" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="mdlLevelTitle">Add shared level</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			</div>
			<div class="modal-body">
				<div class="modal-parent" id="mdlLevelParent"></div>
				<input type="hidden" id="levelId" value="">
				<div class="form-group mb-0">
					<label>Level name <span class="text-danger">*</span></label>
					<input type="text" class="form-control" id="levelTitle" placeholder="TVET: Level 3 · REB: S4 / P1">
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
				<button type="button" class="btn btn-success" id="btnSaveLevel">Save level</button>
			</div>
		</div>
	</div>
</div>

<script>
(function ($) {
	var program = 2;
	var tree = [];
	var sharedLevels = [];
	var selectedFac = null;
	var API = '<?= site_url(!empty($structureApiPrefix) ? rtrim($structureApiPrefix, '/') . '/' : ''); ?>';
	var CSRF = {
		name: $('meta[name="csrf-token-name"]').attr('content'),
		hash: $('meta[name="csrf-token-value"]').attr('content')
	};
	function withCsrf(data) {
		data = data || {};
		if (CSRF.name && CSRF.hash) data[CSRF.name] = CSRF.hash;
		return data;
	}
	function toast(msg, ok) {
		if (window.toastada) {
			ok ? toastada.success(msg) : toastada.error(msg);
		} else {
			alert(msg);
		}
	}
	function openModal(id) {
		var $m = $(id);
		// Move out of nested layout so backdrop does not cover the dialog
		if ($m.parent()[0] !== document.body) {
			$m.appendTo('body');
		}
		if ($m.modal) $m.modal('show');
		else $m.show();
	}
	function closeModal(id) {
		var $m = $(id);
		if ($m.modal) $m.modal('hide');
		else $m.hide();
	}
	// Ensure modals are direct children of body (fixes overlay-on-top bug)
	$('#mdlFaculty, #mdlDepartment, #mdlLevel').appendTo('body');
	function esc(t) { return $('<div>').text(t || '').html(); }

	function updateNote() {
		if (program === 1) {
			$('#asNote').html('<strong>TVET:</strong> All departments share the same Level 1–5 pool. Pick the level when creating a class — not per trade/department.');
			$('#levelBadge').text('all TVET');
			$('#facHint').text('TVET sector');
		} else if (program === 3) {
			$('#asNote').html('<strong>Special:</strong> Faculty and department are both <strong>Nursing</strong> (code <strong>ANP</strong>). Choose S4–S6 when creating a class.');
			$('#levelBadge').text('per faculty');
			$('#facHint').text('Special path (Nursing / ANP)');
		} else {
			$('#asNote').html('<strong>REB:</strong> All departments under a faculty share the same levels (e.g. S4–S6). Choose the level when creating a class.');
			$('#levelBadge').text('per faculty');
			$('#facHint').text('REB stream (Primary / O’Level / A’Level…)');
		}
	}

	function loadTree(keepSelection) {
		var keepFac = keepSelection ? selectedFac : null;
		$('#listFaculty').html('<div class="as-empty">Loading…</div>');
		$.getJSON(API + 'getAcademicStructure/' + program, function (res) {
			tree = (res && res.faculties) ? res.faculties : [];
			sharedLevels = (res && res.shared_levels) ? res.shared_levels : [];
			renderFaculties();
			if (program === 1) {
				renderSharedLevels();
			}
			if (keepFac) {
				var f = tree.find(function (x) { return x.id === keepFac; });
				if (f) selectFaculty(f.id);
			}
		}).fail(function () {
			$('#listFaculty').html('<div class="as-empty">Failed to load</div>');
		});
	}

	function currentFaculty() {
		return tree.find(function (f) { return f.id === selectedFac; }) || null;
	}

	function renderFaculties() {
		var perFacultyLevels = (program === 2 || program === 3);
		var html = '';
		if (!tree.length) {
			html = '<div class="as-empty">No faculties yet. Click + Add</div>';
		} else {
			tree.forEach(function (f) {
				var active = selectedFac === f.id ? ' active' : '';
				var lvlCount = perFacultyLevels ? ((f.levels || []).length + ' shared levels') : 'uses TVET Level 1–5';
				html += '<div class="as-item' + active + '" data-id="' + f.id + '">' +
					'<div class="as-item__a">' +
					'<button type="button" class="btn btn-outline-secondary btn-edit-fac" data-id="' + f.id + '">Edit</button>' +
					'<button type="button" class="btn btn-outline-danger btn-del" data-kind="faculty" data-id="' + f.id + '">Del</button>' +
					'</div>' +
					'<div class="as-item__t">' + esc(f.title) + '</div>' +
					'<div class="as-item__m">' + esc(f.abbrev || '') + ' · ' + (f.departments || []).length + ' depts · ' + lvlCount + '</div>' +
					'</div>';
			});
		}
		$('#listFaculty').html(html);
		$('#listDept').html('<div class="as-empty">Select a faculty</div>');
		$('#btnAddDept').prop('disabled', true);
		$('#btnAddFac').toggle(program !== 3);
		if (perFacultyLevels) {
			$('#listLevel').html('<div class="as-empty">Select a faculty to manage shared levels</div>');
			$('#btnAddLevel').prop('disabled', true);
			$('#levelHint').text('Shared by all departments under the selected faculty');
		}
	}

	function renderDepartments() {
		var f = currentFaculty();
		$('#btnAddDept').prop('disabled', !f);
		if (program === 3) {
			$('#btnAddDept').hide();
		} else {
			$('#btnAddDept').show();
		}
		$('#deptHint').text(f ? ('Under: ' + f.title) : 'Belongs to selected faculty');
		var html = '';
		if (!f || !(f.departments || []).length) {
			html = '<div class="as-empty">No departments. Click + Add</div>';
		} else {
			f.departments.forEach(function (d) {
				html += '<div class="as-item" data-dept="' + d.id + '">' +
					'<div class="as-item__a">' +
					'<button type="button" class="btn btn-outline-secondary btn-edit-dept" data-id="' + d.id + '">Edit</button>' +
					'<button type="button" class="btn btn-outline-danger btn-del" data-kind="department" data-id="' + d.id + '">Del</button>' +
					'</div>' +
					'<div class="as-item__t">' + esc(d.title) + '</div>' +
					'<div class="as-item__m">' + esc(d.code || '') + ' · shares faculty levels</div>' +
					'</div>';
			});
		}
		$('#listDept').html(html);
	}

	function renderLevelsList(levels, emptyMsg) {
		var html = '';
		if (!levels || !levels.length) {
			html = '<div class="as-empty">' + emptyMsg + '</div>';
		} else {
			levels.forEach(function (l) {
				html += '<div class="as-item as-item--level">' +
					'<div class="as-item__a">' +
					'<button type="button" class="btn btn-outline-secondary btn-edit-level" data-id="' + l.id + '" data-title="' + esc(l.title) + '">Edit</button>' +
					'<button type="button" class="btn btn-outline-danger btn-del" data-kind="level" data-id="' + l.id + '">Del</button>' +
					'</div>' +
					'<div class="as-item__t">' + esc(l.title) + '</div>' +
					'<div class="as-item__m">Shared · choose at class creation</div>' +
					'</div>';
			});
		}
		$('#listLevel').html(html);
	}

	function renderSharedLevels() {
		$('#btnAddLevel').prop('disabled', false);
		$('#levelHint').text('Shared by all TVET departments (Level 1–5)');
		renderLevelsList(sharedLevels, 'No TVET levels yet. Click + Add (e.g. Level 1)');
	}

	function renderFacultyLevels() {
		var f = currentFaculty();
		if (!f) {
			$('#btnAddLevel').prop('disabled', true);
			$('#listLevel').html('<div class="as-empty">Select a faculty to manage shared levels</div>');
			return;
		}
		$('#btnAddLevel').prop('disabled', false);
		$('#levelHint').text('Shared by all departments under: ' + f.title);
		renderLevelsList(f.levels || [], 'No levels for this faculty. Click + Add (e.g. S4)');
	}

	function selectFaculty(id) {
		selectedFac = id;
		renderFaculties();
		$('.as-item[data-id="' + id + '"]').first().addClass('active');
		renderDepartments();
		if (program === 1) {
			renderSharedLevels();
		} else {
			renderFacultyLevels();
		}
	}

	$('.as-tab').on('click', function () {
		$('.as-tab').removeClass('active');
		$(this).addClass('active');
		program = parseInt($(this).data('program'), 10);
		selectedFac = null;
		closeModal('#mdlFaculty');
		closeModal('#mdlDepartment');
		closeModal('#mdlLevel');
		updateNote();
		loadTree(false);
	});

	$(document).on('click', '#listFaculty .as-item', function (e) {
		if ($(e.target).closest('button').length) return;
		selectFaculty(parseInt($(this).data('id'), 10));
	});

	$('#btnAddFac').on('click', function () {
		$('#facId').val(''); $('#facTitle').val(''); $('#facAbbrev').val('');
		$('#mdlFacultyTitle').text('Add faculty');
		openModal('#mdlFaculty');
	});
	$(document).on('click', '.btn-edit-fac', function (e) {
		e.stopPropagation();
		var id = parseInt($(this).data('id'), 10);
		var f = tree.find(function (x) { return x.id === id; });
		if (!f) return;
		$('#facId').val(f.id); $('#facTitle').val(f.title); $('#facAbbrev').val(f.abbrev || '');
		$('#mdlFacultyTitle').text('Edit faculty');
		openModal('#mdlFaculty');
	});
	$('#btnSaveFac').on('click', function () {
		var $btn = $(this).prop('disabled', true);
		$.post(API + 'saveAcademicFaculty', withCsrf({
			id: $('#facId').val(),
			title: $('#facTitle').val(),
			abbrev: $('#facAbbrev').val(),
			type: program
		}), function (res) {
			$btn.prop('disabled', false);
			if (res.error) return toast(res.error, false);
			toast(res.success || 'Saved', true);
			closeModal('#mdlFaculty');
			loadTree(true);
		}, 'json').fail(function () { $btn.prop('disabled', false); toast('Save failed', false); });
	});

	$('#btnAddDept').on('click', function () {
		if (!selectedFac) return toast('Select a faculty first', false);
		var f = currentFaculty();
		$('#deptId').val(''); $('#deptTitle').val(''); $('#deptCode').val('');
		$('#mdlDeptTitle').text('Add department');
		$('#mdlDeptParent').html(f ? ('Under faculty: <strong>' + esc(f.title) + '</strong>') : '');
		openModal('#mdlDepartment');
	});
	$(document).on('click', '.btn-edit-dept', function (e) {
		e.stopPropagation();
		var id = parseInt($(this).data('id'), 10);
		var f = currentFaculty();
		var d = f && (f.departments || []).find(function (x) { return x.id === id; });
		if (!d) return;
		$('#deptId').val(d.id); $('#deptTitle').val(d.title); $('#deptCode').val(d.code || '');
		$('#mdlDeptTitle').text('Edit department');
		$('#mdlDeptParent').html(f ? ('Under faculty: <strong>' + esc(f.title) + '</strong>') : '');
		openModal('#mdlDepartment');
	});
	$('#btnSaveDept').on('click', function () {
		var $btn = $(this).prop('disabled', true);
		$.post(API + 'saveAcademicDepartment', withCsrf({
			id: $('#deptId').val(),
			faculty_id: selectedFac,
			title: $('#deptTitle').val(),
			code: $('#deptCode').val()
		}), function (res) {
			$btn.prop('disabled', false);
			if (res.error) return toast(res.error, false);
			toast(res.success || 'Saved', true);
			closeModal('#mdlDepartment');
			loadTree(true);
		}, 'json').fail(function () { $btn.prop('disabled', false); toast('Save failed', false); });
	});

	$('#btnAddLevel').on('click', function () {
		if (program === 2 && !selectedFac) return toast('Select a faculty first', false);
		if (program === 3 && !selectedFac) return toast('Select a faculty first', false);
		$('#levelId').val('');
		$('#levelTitle').val(program === 1 ? 'Level 3' : '');
		$('#mdlLevelTitle').text('Add shared level');
		if (program === 1) {
			$('#mdlLevelParent').html('Shared across <strong>all TVET departments</strong>');
		} else {
			var f = currentFaculty();
			$('#mdlLevelParent').html(f ? ('Shared by all departments under <strong>' + esc(f.title) + '</strong>') : '');
		}
		openModal('#mdlLevel');
	});
	$(document).on('click', '.btn-edit-level', function (e) {
		e.stopPropagation();
		$('#levelId').val($(this).data('id'));
		$('#levelTitle').val($(this).data('title'));
		$('#mdlLevelTitle').text('Edit shared level');
		if (program === 1) {
			$('#mdlLevelParent').html('Shared across <strong>all TVET departments</strong>');
		} else {
			var f = currentFaculty();
			$('#mdlLevelParent').html(f ? ('Shared by all departments under <strong>' + esc(f.title) + '</strong>') : '');
		}
		openModal('#mdlLevel');
	});
	$('#btnSaveLevel').on('click', function () {
		var $btn = $(this).prop('disabled', true);
		$.post(API + 'saveAcademicLevel', withCsrf({
			id: $('#levelId').val(),
			faculty_id: program === 1 ? 0 : selectedFac,
			title: $('#levelTitle').val()
		}), function (res) {
			$btn.prop('disabled', false);
			if (res.error) return toast(res.error, false);
			toast(res.success || 'Saved', true);
			closeModal('#mdlLevel');
			loadTree(true);
		}, 'json').fail(function () { $btn.prop('disabled', false); toast('Save failed', false); });
	});

	$(document).on('click', '.btn-del', function (e) {
		e.stopPropagation();
		var kind = $(this).data('kind');
		var id = $(this).data('id');
		if (!confirm('Delete this ' + kind + '?')) return;
		$.post(API + 'deleteAcademicNode', withCsrf({ kind: kind, id: id }), function (res) {
			if (res.error) return toast(res.error, false);
			toast(res.success || 'Deleted', true);
			if (kind === 'faculty') selectedFac = null;
			loadTree(true);
		}, 'json');
	});

	updateNote();
	loadTree(false);
})(jQuery);
</script>
