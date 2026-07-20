<?php
/**
 * Smart Create Course — extracted modules per class (TVET / REB)
 * @var array $classes
 * @var array $courses
 * @var array $categories
 * @var array $staffs
 * @var array $smart_by_class
 * @var array $courses_grouped
 */
$smartByClass = $smart_by_class ?? [];
$coursesGrouped = $courses_grouped ?? ['tvet' => [], 'reb' => []];
$courseCategoriesJson = [];
foreach (($categories ?? []) as $cat) {
	$courseCategoriesJson[] = [
		'id' => (int) ($cat['id'] ?? 0),
		'title' => (string) ($cat['title'] ?? ''),
	];
}
$renderCourseRows = static function (array $rows): string {
	$html = '';
	foreach ($rows as $course) {
		$id = (int) ($course['id'] ?? 0);
		$title = (string) ($course['title'] ?? '');
		$code = (string) ($course['code'] ?? '');
		$catTitle = (string) ($course['category'] ?? '');
		$catId = (int) ($course['category_id'] ?? 0);
		$credit = (string) ($course['credit'] ?? '0');
		$marks = (string) ($course['marks'] ?? '0');
		$prog = (($course['program_type'] ?? '') === 'reb') ? 'reb' : 'tvet';
		$titleEsc = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
		$codeEsc = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
		$catEsc = htmlspecialchars($catTitle, ENT_QUOTES, 'UTF-8');
		$source = (($course['create_source'] ?? '') === 'ai') ? 'ai' : 'manual';
		$sourceLabel = $source === 'ai' ? 'AI' : 'Manual';
		$sourceClass = $source === 'ai' ? 'source-ai' : 'source-manual';
		$html .= '<tr class="course-row"'
			. ' data-id="' . $id . '"'
			. ' data-title="' . $titleEsc . '"'
			. ' data-code="' . $codeEsc . '"'
			. ' data-category-id="' . $catId . '"'
			. ' data-category="' . $catEsc . '"'
			. ' data-credit="' . htmlspecialchars($credit, ENT_QUOTES, 'UTF-8') . '"'
			. ' data-program-type="' . $prog . '">'
			. '<td class="course-inline" data-field="title" title="Double-click to edit">' . $titleEsc . '</td>'
			. '<td class="course-inline" data-field="code" title="Double-click to edit">' . $codeEsc . '</td>'
			. '<td class="course-inline" data-field="category" title="Double-click to edit">' . $catEsc . '</td>'
			. '<td><span class="course-source-badge ' . $sourceClass . '">' . $sourceLabel . '</span></td>'
			. '<td class="course-inline" data-field="credit" title="Double-click to edit">' . htmlspecialchars($credit, ENT_QUOTES, 'UTF-8') . '</td>'
			. '<td class="course-marks">' . htmlspecialchars($marks, ENT_QUOTES, 'UTF-8') . '</td>'
			. '<td>'
			. "<label class='typcn typcn-document-add text-primary link' data-id='" . $id . "' data-title='" . $titleEsc . "' data-toggle='modal' data-target='#assignModal'>" . lang('app.assign') . "</label>&nbsp;&nbsp;"
			. "<label class='typcn typcn-delete text-danger link' data-title='" . $titleEsc . "' data-toggle='delete' data-target='" . $id . "' data-href='delete_course/" . $id . "'>" . lang('app.del') . "</label>"
			. '</td></tr>';
	}
	return $html;
};
$classesByType = ['1' => [], '2' => []];
foreach ($classes as $c) {
	$ft = (string) ((int) ($c['faculty_type'] ?? $c['type'] ?? 1));
	if ($ft !== '2') {
		$ft = '1';
	}
	$classesByType[$ft][] = $c;
}
?>
<button class="btn btn-success btn-lg" id="createcoursebtn" style="margin-left: 10px" type="button"><?= lang("app.createNewCourse"); ?></button>
<button class="btn btn-outline-primary btn-lg" id="btnSmartCourses" style="margin-left: 8px" type="button">
	<i class="fa fa-magic"></i> Smart create from analysis
</button>

<div class="modal fade" id="chooseCourseTypeModal" tabindex="-1" role="dialog" aria-labelledby="chooseCourseTypeLabel" aria-hidden="true">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="chooseCourseTypeLabel"><?= lang("app.chooseType"); ?></h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<p class="mb-3 text-muted">Choose programme type, then create courses manually or from extracted curriculum.</p>
				<button type="button" class="btn btn-outline-primary btn-block btn-lg course-type-pick mb-2" data-type="1" data-mode="manual"><?= lang("app.wda"); ?> (TVET)</button>
				<button type="button" class="btn btn-outline-primary btn-block btn-lg course-type-pick" data-type="2" data-mode="manual"><?= lang("app.reb"); ?> (REB)</button>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-dismiss="modal"><?= lang("app.close"); ?></button>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="smartTypeModal" tabindex="-1" role="dialog">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Smart create — choose type</h5>
				<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body">
				<p class="text-muted mb-3">Show extracted courses for classes of this type.</p>
				<button type="button" class="btn btn-outline-primary btn-block btn-lg smart-type-pick mb-2" data-type="1"><?= lang("app.wda"); ?> (TVET)</button>
				<button type="button" class="btn btn-outline-primary btn-block btn-lg smart-type-pick" data-type="2"><?= lang("app.reb"); ?> (REB)</button>
			</div>
		</div>
	</div>
</div>

<style>
	.smart-wrap { margin: 12px 10px 0; display:none; }
	.smart-wrap.is-on { display:block; }
	.smart-banner {
		background:linear-gradient(135deg,#ecfeff,#f0fdfa); border:1px solid #99f6e4; border-radius:12px;
		padding:.85rem 1rem; margin-bottom:1rem; color:#134e4a;
	}
	.smart-class {
		border:1px solid #e2e8f0; border-radius:12px; margin-bottom:.75rem; background:#fff; overflow:hidden;
	}
	.smart-class-head {
		display:flex; align-items:center; justify-content:space-between; gap:.75rem; flex-wrap:wrap;
		padding:.75rem 1rem; background:#f8fafc; cursor:pointer; user-select:none;
	}
	.smart-class-head strong { color:#0f172a; }
	.smart-class-meta { color:#64748b; font-size:.82rem; }
	.smart-class-body { display:none; padding:.5rem 1rem 1rem; border-top:1px solid #e2e8f0; }
	.smart-class.is-open .smart-class-body { display:block; }
	.smart-table { width:100%; font-size:.88rem; }
	.smart-table th { font-size:.75rem; text-transform:uppercase; color:#64748b; border-bottom:1px solid #e2e8f0; padding:.4rem; }
	.smart-table td { padding:.45rem .4rem; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
	.smart-badge { display:inline-block; font-size:.7rem; font-weight:700; padding:.12rem .4rem; border-radius:999px; background:#e0f2fe; color:#0369a1; }
	.smart-badge.exists { background:#fef3c7; color:#b45309; }
	.smart-badge.ok { background:#dcfce7; color:#15803d; }
	.smart-actions { display:flex; gap:.5rem; flex-wrap:wrap; margin-top:.65rem; }
	.credit-input, .marks-input { width:72px; display:inline-block; }
	.course-list-wrap {
		margin: 16px 10px 0;
		border: 1px solid #e2e8f0;
		border-radius: 14px;
		background: #fff;
		overflow: hidden;
		box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
	}
	.course-prog-switch {
		display: flex;
		gap: 0;
		border-bottom: 1px solid #e2e8f0;
		background: #f8fafc;
		padding: .5rem .75rem 0;
	}
	.course-prog-tab {
		appearance: none;
		border: 1px solid transparent;
		border-bottom: none;
		background: transparent;
		color: #64748b;
		font-weight: 700;
		font-size: .95rem;
		padding: .7rem 1.15rem;
		border-radius: 10px 10px 0 0;
		cursor: pointer;
		min-width: 140px;
		text-align: center;
	}
	.course-prog-tab:hover { color: #0f172a; background: #fff; }
	.course-prog-tab.is-active {
		background: #fff;
		color: #0f172a;
		border-color: #e2e8f0;
		box-shadow: 0 -1px 0 #fff;
		position: relative;
		top: 1px;
	}
	.course-prog-tab .tab-count {
		display: inline-block;
		margin-left: .35rem;
		font-size: .75rem;
		font-weight: 700;
		padding: .1rem .4rem;
		border-radius: 999px;
		background: #e2e8f0;
		color: #334155;
	}
	.course-prog-tab.is-active.rtb .tab-count { background: #dcfce7; color: #15803d; }
	.course-prog-tab.is-active.reb .tab-count { background: #fef3c7; color: #b45309; }
	.course-panel-meta {
		padding: .85rem 1.1rem 0;
		color: #64748b;
		font-size: .9rem;
	}
	.course-group-body {
		padding: .75rem 1.1rem 1.15rem;
	}
	.course-group-panel { display: none; }
	.course-group-panel.is-active { display: block; }
	.course-source-badge {
		display: inline-block;
		font-size: .78rem;
		font-weight: 700;
		padding: .2rem .55rem;
		border-radius: 999px;
	}
	.course-source-badge.source-ai { background: #dbeafe; color: #1d4ed8; }
	.course-source-badge.source-manual { background: #f3f4f6; color: #374151; }
	.course-inline {
		cursor: pointer;
		position: relative;
	}
	.course-inline:hover {
		outline: 1px dashed #94a3b8;
		outline-offset: -2px;
		background: #f8fafc;
	}
	.course-inline.is-editing {
		padding: .25rem !important;
		outline: 2px solid #0f766e;
		background: #fff;
	}
	.course-inline-input, .course-inline-select {
		width: 100%;
		min-width: 80px;
		height: 36px;
		padding: .3rem .5rem;
		border: 1px solid #cbd5e1;
		border-radius: 6px;
		font-size: .95rem;
	}
	.course-panel-meta .inline-hint {
		display: block;
		margin-top: .25rem;
		font-size: .8rem;
		color: #94a3b8;
	}

	/* DataTables controls — larger + readable */
	.course-list-wrap .dataTables_wrapper {
		font-size: .95rem;
	}
	.course-list-wrap .dataTables_length,
	.course-list-wrap .dataTables_filter {
		margin-bottom: .85rem;
	}
	.course-list-wrap .dataTables_length label,
	.course-list-wrap .dataTables_filter label {
		font-weight: 600;
		color: #334155;
		display: inline-flex;
		align-items: center;
		gap: .45rem;
		margin: 0;
	}
	.course-list-wrap .dataTables_length select {
		min-width: 72px;
		height: 38px;
		padding: .35rem .55rem;
		border: 1px solid #cbd5e1;
		border-radius: 8px;
		background: #fff;
	}
	.course-list-wrap .dataTables_filter input {
		height: 38px;
		min-width: 220px;
		padding: .4rem .7rem;
		border: 1px solid #cbd5e1;
		border-radius: 8px;
		margin-left: .35rem;
	}
	.course-list-wrap table.course-list-table {
		width: 100% !important;
		font-size: .95rem;
	}
	.course-list-wrap table.course-list-table thead th {
		font-size: .8rem;
		text-transform: uppercase;
		letter-spacing: .02em;
		color: #64748b;
		padding: .7rem .55rem;
		white-space: nowrap;
	}
	.course-list-wrap table.course-list-table tbody td {
		padding: .7rem .55rem;
		vertical-align: middle;
	}
	.course-list-wrap .dataTables_info {
		padding-top: .85rem !important;
		color: #64748b;
		font-size: .9rem;
	}
	.course-list-wrap .dataTables_paginate {
		padding-top: .65rem !important;
		float: right;
	}
	.course-list-wrap .dataTables_paginate .paginate_button {
		display: inline-block !important;
		box-sizing: border-box;
		min-width: 38px;
		padding: .45rem .75rem !important;
		margin: 0 .2rem !important;
		border: 1px solid #cbd5e1 !important;
		border-radius: 8px !important;
		background: #fff !important;
		color: #0f172a !important;
		font-weight: 600 !important;
		line-height: 1.25 !important;
		text-decoration: none !important;
		cursor: pointer;
		vertical-align: middle;
	}
	.course-list-wrap .dataTables_paginate .paginate_button:hover {
		background: #f1f5f9 !important;
		border-color: #94a3b8 !important;
		color: #0f172a !important;
	}
	.course-list-wrap .dataTables_paginate .paginate_button.current,
	.course-list-wrap .dataTables_paginate .paginate_button.current:hover {
		background: #0f766e !important;
		border-color: #0f766e !important;
		color: #fff !important;
	}
	.course-list-wrap .dataTables_paginate .paginate_button.disabled,
	.course-list-wrap .dataTables_paginate .paginate_button.disabled:hover {
		opacity: .45;
		cursor: default;
		background: #f8fafc !important;
		color: #94a3b8 !important;
	}
	.course-list-wrap .dataTables_paginate span {
		display: inline-block;
		vertical-align: middle;
	}
</style>
<link rel="stylesheet" type="text/css" href="<?= base_url('assets/plugins/datatables/jquery.dataTables.min.css'); ?>">


<div class="smart-wrap" id="smartWrap">
	<div class="smart-banner">
		<strong id="smartTypeLabel">TVET</strong> —
		Extracted courses from Pedagogical Documents analysis, arranged per class.
		<b>Credit</b> comes from the curriculum competences table (e.g. 1.5, 3, 6); <b>Marks = Credit × 10</b>.
		Category is taken from the curriculum (Specific / General / Complementary) and created if missing.
		<a href="<?= base_url('ped_analyse'); ?>">Analyse first</a> if a class is empty.
	</div>
	<div id="smartClassList"></div>
	<p class="text-muted" id="smartEmpty" style="display:none;">No extracted courses for this type yet. Run Analyse Curriculum &amp; Chronogram for those classes first.</p>
</div>

<div class="boxed" id="createCourseDiv" style="display: none;">
<form action="<?= base_url('manipulate_course'); ?>" class="validate autoSubmit" id="manualCourseForm">
	<input type="hidden" name="program_type" id="manualProgramType" value="tvet">
	<table class="table table-striped table-bordered" style="margin: 0">
		<tbody>
<tr>
<td><div class="form-group">
		<label><?= lang("app.title"); ?></label>
		<input class="form-control" type="text" name="title" required minlength="3">
	</div>
</td>
<td><div class="form-group">
		<label><?= lang("app.code"); ?></label>
		<input class="form-control" type="text" name="code" required minlength="3">
	</div>
</td>
<td><div class="form-group">
		<label><?= lang("app.category"); ?></label>
		<a href="javascript:void" class="pull-right" data-toggle="modal" data-target="#addCourseCategory"><i class="fa fa-plus"></i> <?= lang("app.createNewCategory"); ?></a>
		<a href="javascript:void" class="pull-right" data-toggle="refresh" data-href="<?=base_url('get_course_category');?>" data-target="category" style="margin: 0 10px"><i class="fa fa-sync faa-spin"></i> </a>
		<select class="form-control select2" name="category" id="category" required>
			<option disabled selected><?= lang("app.chooseCategory"); ?></option>
			<?php
				foreach ($categories as $category):
					echo"<option value='{$category['id']}'>{$category['title']}</option>";
				endforeach;
	?>
		</select>
	</div>
</td>
<td id="creditDiv">
	<div class="form-group" >
		<label id="credits"><?= lang("app.credits"); ?></label>
		<input class="form-control" type="number" step="0.1" min="0" name="credit" id="manualCredit" minlength="1">
	</div>
</td>
<td><div class="form-group">
		<label><?= lang("app.maxPoints"); ?> <small class="text-muted">(auto = credit×10)</small></label>
		<input class="form-control" type="number" name="marks" id="manualMarks" required minlength="1" readonly>
	</div>
</td>


<td><button type="submit" class="btn btn-success btn-lg" data-target="<?=base_url('add_course');?>"><?= lang("app.create"); ?></button>
</td>
</tr>
		</tbody>
	</table>
</form>
</div>

<?php
$groupDefs = [
	'tvet' => [
		'title' => lang('app.wda') . ' / RTB (TVET)',
		'table_id' => 'courseTableRtb',
		'tab_class' => 'rtb',
		'label' => 'RTB',
	],
	'reb' => [
		'title' => lang('app.reb') . ' (REB)',
		'table_id' => 'courseTableReb',
		'tab_class' => 'reb',
		'label' => 'REB',
	],
];
$defaultProg = !empty($coursesGrouped['tvet']) ? 'tvet' : (!empty($coursesGrouped['reb']) ? 'reb' : 'tvet');
?>
<div class="course-list-wrap" id="courseListWrap">
	<div class="course-prog-switch" role="tablist" aria-label="Programme type">
		<?php foreach ($groupDefs as $progKey => $groupDef):
			$rows = $coursesGrouped[$progKey] ?? [];
			$active = $progKey === $defaultProg ? ' is-active' : '';
		?>
		<button type="button"
			class="course-prog-tab <?= esc($groupDef['tab_class']); ?><?= $active; ?>"
			data-prog="<?= esc($progKey); ?>"
			role="tab"
			aria-selected="<?= $progKey === $defaultProg ? 'true' : 'false'; ?>">
			<?= esc($groupDef['label']); ?>
			<span class="tab-count"><?= count($rows); ?></span>
		</button>
		<?php endforeach; ?>
	</div>

	<?php foreach ($groupDefs as $progKey => $groupDef):
		$rows = $coursesGrouped[$progKey] ?? [];
		$aiCount = count(array_filter($rows, static function ($c) {
			return ($c['create_source'] ?? '') === 'ai';
		}));
		$manualCount = count($rows) - $aiCount;
		$active = $progKey === $defaultProg ? ' is-active' : '';
	?>
	<div class="course-group-panel<?= $active; ?>" data-prog-panel="<?= esc($progKey); ?>" role="tabpanel">
		<div class="course-panel-meta">
			<strong><?= esc($groupDef['title']); ?></strong>
			— <?= count($rows); ?> course(s) · <?= $manualCount; ?> manual · <?= $aiCount; ?> AI
			<span class="inline-hint">Double-click Title, Code, Category or Credits to edit inline. Marks = credit × 10.</span>
		</div>
		<div class="course-group-body">
			<table class="table table-hover table-striped table-bordered course-list-table" id="<?= esc($groupDef['table_id']); ?>" style="width:100%">
				<thead>
				<tr>
					<th><?= lang("app.title"); ?></th>
					<th><?= lang("app.code"); ?></th>
					<th><?= lang("app.category"); ?></th>
					<th>Source</th>
					<th><?= lang("app.credits"); ?></th>
					<th><?= lang("app.marks"); ?></th>
					<th><?= lang("app.use"); ?></th>
				</tr>
				</thead>
				<tbody>
				<?= $renderCourseRows($rows); ?>
				</tbody>
			</table>
		</div>
	</div>
	<?php endforeach; ?>
</div>

<style>
	.course-type-pick { text-align: left; white-space: normal; }
</style>
<script type="text/javascript">
	$(document).ready(function () {
		var classesByType = <?= json_encode($classesByType, JSON_UNESCAPED_UNICODE); ?>;
		var smartByClass = <?= json_encode($smartByClass, JSON_UNESCAPED_UNICODE); ?>;
		var courseCategories = <?= json_encode($courseCategoriesJson, JSON_UNESCAPED_UNICODE); ?>;
		var currentType = null;
		var inlineSaving = false;

		function saveCourseRow($tr, done) {
			if (inlineSaving) return;
			var id = parseInt($tr.data('id'), 10) || 0;
			if (!id) return;
			var credit = parseFloat($tr.attr('data-credit')) || 0;
			if (credit < 0) credit = 0;
			var marks = Math.round(credit * 10);
			inlineSaving = true;
			$.ajax({
				url: '<?= base_url('manipulate_course'); ?>',
				method: 'POST',
				dataType: 'json',
				data: {
					courseId: id,
					title: $tr.attr('data-title') || '',
					code: $tr.attr('data-code') || '',
					category: $tr.attr('data-category-id') || '',
					credit: credit,
					marks: marks,
					program_type: $tr.attr('data-program-type') || 'tvet'
				}
			}).done(function (res) {
				if (res && res.error) {
					if (window.toastada) toastada.error(res.error);
					else alert(res.error);
					if (done) done(false);
					return;
				}
				$tr.find('.course-marks').text(String(marks));
				$tr.find('[data-toggle="modal"][data-target="#assignModal"]').attr('data-title', $tr.attr('data-title') || '');
				$tr.find('[data-toggle="delete"]').attr('data-title', $tr.attr('data-title') || '');
				if (window.toastada) toastada.success((res && res.success) ? res.success : 'Saved');
				if (done) done(true);
			}).fail(function (xhr) {
				var msg = (xhr.responseJSON && (xhr.responseJSON.error || xhr.responseJSON.message)) || 'Save failed';
				if (window.toastada) toastada.error(msg);
				else alert(msg);
				if (done) done(false);
			}).always(function () {
				inlineSaving = false;
			});
		}

		function finishInlineEdit($td, newVal, displayText, extra) {
			var $tr = $td.closest('tr');
			var field = $td.data('field');
			var prev = {
				title: $tr.attr('data-title'),
				code: $tr.attr('data-code'),
				categoryId: $tr.attr('data-category-id'),
				category: $tr.attr('data-category'),
				credit: $tr.attr('data-credit')
			};
			if (field === 'title') {
				$tr.attr('data-title', newVal);
			} else if (field === 'code') {
				$tr.attr('data-code', newVal);
			} else if (field === 'category') {
				$tr.attr('data-category-id', String(extra && extra.id != null ? extra.id : newVal));
				$tr.attr('data-category', displayText);
			} else if (field === 'credit') {
				var c = parseFloat(newVal);
				if (isNaN(c) || c < 0) c = 0;
				newVal = String(c);
				displayText = newVal;
				$tr.attr('data-credit', newVal);
				$tr.find('.course-marks').text(String(Math.round(c * 10)));
			}
			$td.removeClass('is-editing').text(displayText);
			saveCourseRow($tr, function (ok) {
				if (!ok) {
					$tr.attr('data-title', prev.title);
					$tr.attr('data-code', prev.code);
					$tr.attr('data-category-id', prev.categoryId);
					$tr.attr('data-category', prev.category);
					$tr.attr('data-credit', prev.credit);
					if (field === 'title') $td.text(prev.title || '');
					else if (field === 'code') $td.text(prev.code || '');
					else if (field === 'category') $td.text(prev.category || '');
					else if (field === 'credit') {
						$td.text(prev.credit || '0');
						$tr.find('.course-marks').text(String(Math.round((parseFloat(prev.credit) || 0) * 10)));
					}
				}
			});
		}

		function startInlineEdit($td) {
			if ($td.hasClass('is-editing')) return;
			$('#courseListWrap .course-inline.is-editing').each(function () {
				var $o = $(this);
				var $inp = $o.find('.course-inline-input, .course-inline-select');
				if ($inp.length) $inp.trigger('blur');
			});
			var field = $td.data('field');
			var $tr = $td.closest('tr');
			$td.addClass('is-editing').empty();
			if (field === 'category') {
				var $sel = $('<select class="course-inline-select"></select>');
				var currentId = String($tr.attr('data-category-id') || '');
				(courseCategories || []).forEach(function (c) {
					var $opt = $('<option></option>').val(c.id).text(c.title);
					if (String(c.id) === currentId) $opt.prop('selected', true);
					$sel.append($opt);
				});
				$td.append($sel);
				$sel.focus();
				var committed = false;
				function commitSelect() {
					if (committed) return;
					committed = true;
					var id = $sel.val();
					var text = $sel.find('option:selected').text() || '';
					finishInlineEdit($td, id, text, { id: id });
				}
				$sel.on('change', commitSelect);
				$sel.on('blur', function () { setTimeout(commitSelect, 120); });
				$sel.on('keydown', function (e) {
					if (e.key === 'Escape') {
						committed = true;
						$td.removeClass('is-editing').text($tr.attr('data-category') || '');
					} else if (e.key === 'Enter') {
						e.preventDefault();
						commitSelect();
					}
				});
				return;
			}
			var $inp = $('<input class="course-inline-input" />');
			if (field === 'credit') {
				$inp.attr({ type: 'number', step: '0.1', min: '0' });
				$inp.val($tr.attr('data-credit') || '0');
			} else if (field === 'code') {
				$inp.attr({ type: 'text' }).val($tr.attr('data-code') || '');
			} else {
				$inp.attr({ type: 'text' }).val($tr.attr('data-title') || '');
			}
			$td.append($inp);
			$inp.focus().select();
			var committed = false;
			function commitInput() {
				if (committed) return;
				committed = true;
				var val = $.trim($inp.val() || '');
				if (field === 'title' && val.length < 1) {
					$td.removeClass('is-editing').text($tr.attr('data-title') || '');
					return;
				}
				if (field === 'code' && val.length < 1) {
					$td.removeClass('is-editing').text($tr.attr('data-code') || '');
					return;
				}
				finishInlineEdit($td, val, val);
			}
			$inp.on('blur', function () { setTimeout(commitInput, 80); });
			$inp.on('keydown', function (e) {
				if (e.key === 'Enter') {
					e.preventDefault();
					commitInput();
				} else if (e.key === 'Escape') {
					committed = true;
					var restore = field === 'credit' ? ($tr.attr('data-credit') || '0')
						: (field === 'code' ? ($tr.attr('data-code') || '') : ($tr.attr('data-title') || ''));
					$td.removeClass('is-editing').text(restore);
				}
			});
		}

		$(document).on('dblclick', '#courseListWrap td.course-inline', function (e) {
			e.preventDefault();
			e.stopPropagation();
			startInlineEdit($(this));
		});

		function initCourseTable(selector) {
			var $table = $(selector);
			if (!$table.length) return null;
			if ($.fn.DataTable && $.fn.DataTable.isDataTable($table)) {
				$table.DataTable().destroy();
			}
			$table.removeClass('dataTable dtr-inline');
			return $table.DataTable({
				autoWidth: false,
				pageLength: 25,
				lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
				order: [[0, 'asc']],
				dom: '<"row align-items-center mb-2"<"col-sm-6"l><"col-sm-6 text-sm-right"f>>rt<"row align-items-center"<"col-sm-5"i><"col-sm-7"p>>',
				language: {
					lengthMenu: 'Show _MENU_ entries',
					search: 'Search:',
					paginate: { previous: 'Previous', next: 'Next' },
					info: 'Showing _START_ to _END_ of _TOTAL_ entries',
					infoEmpty: 'Showing 0 to 0 of 0 entries',
					zeroRecords: 'No matching courses found'
				}
			});
		}

		var courseTables = {
			tvet: null,
			reb: null
		};

		function ensureTable(prog) {
			var id = prog === 'reb' ? '#courseTableReb' : '#courseTableRtb';
			if (!courseTables[prog]) {
				courseTables[prog] = initCourseTable(id);
			} else if (courseTables[prog] && courseTables[prog].columns) {
				courseTables[prog].columns.adjust();
			}
		}

		function switchCourseProg(prog) {
			prog = prog === 'reb' ? 'reb' : 'tvet';
			$('.course-prog-tab').removeClass('is-active').attr('aria-selected', 'false');
			$('.course-prog-tab[data-prog="' + prog + '"]').addClass('is-active').attr('aria-selected', 'true');
			$('.course-group-panel').removeClass('is-active');
			$('.course-group-panel[data-prog-panel="' + prog + '"]').addClass('is-active');
			ensureTable(prog);
		}

		$('#createCourseDiv').hide();
		ensureTable('<?= $defaultProg; ?>');

		$(document).on('click', '.course-prog-tab', function () {
			switchCourseProg($(this).data('prog'));
		});

		var $typeModal = $("#chooseCourseTypeModal");
		var $smartTypeModal = $("#smartTypeModal");

		function applyCourseType(value) {
			if (!value) {
				if (window.toastada) toastada.error("<?= lang("app.chooseType"); ?>");
				return false;
			}
			currentType = String(value);
			$('#credits').text("<?= lang("app.credits"); ?>");
			$('#creditDiv').show();
			$('#manualProgramType').val(currentType === '2' ? 'reb' : 'tvet');
			$('#createCourseDiv').show();
			$('#smartWrap').removeClass('is-on');
			$typeModal.modal("hide");
			return true;
		}

		$("#createcoursebtn").on("click", function () {
			$typeModal.modal("show");
		});
		$typeModal.on("click", ".course-type-pick", function () {
			applyCourseType($(this).data("type"));
		});

		$("#btnSmartCourses").on("click", function () {
			$smartTypeModal.modal("show");
		});
		$smartTypeModal.on("click", ".smart-type-pick", function () {
			currentType = String($(this).data("type"));
			$smartTypeModal.modal("hide");
			$('#createCourseDiv').hide();
			renderSmart(currentType);
		});

		function classLabel(c) {
			return [c.level_name || '', c.dept_code || c.code || '', c.title || ''].join(' ').replace(/\s+/g, ' ').trim();
		}

		function renderSmart(type) {
			var list = classesByType[type] || [];
			var $box = $('#smartClassList').empty();
			var shown = 0;
			$('#smartTypeLabel').text(type === '2' ? 'REB (General Education)' : 'TVET (Rwanda TVET Board)');
			list.forEach(function (c) {
				var pack = smartByClass[String(c.id)] || smartByClass[c.id];
				if (!pack || !pack.modules || !pack.modules.length) return;
				shown++;
				var pending = pack.modules.filter(function (m) { return !m.already_exists; }).length;
				var $card = $('<div class="smart-class" data-class-id="' + c.id + '"></div>');
				$card.append(
					'<div class="smart-class-head">'
					+ '<div><strong>' + $('<div>').text(classLabel(c)).html() + '</strong>'
					+ '<div class="smart-class-meta">' + pack.modules.length + ' extracted · ' + pending + ' new</div></div>'
					+ '<div><span class="smart-badge">' + (type === '2' ? 'REB' : 'TVET') + '</span> '
					+ '<i class="fa fa-chevron-down"></i></div></div>'
				);
				var $body = $('<div class="smart-class-body"></div>');
				var $tbl = $('<table class="smart-table"><thead><tr>'
					+ '<th><input type="checkbox" class="smart-check-all"></th>'
					+ '<th>Title</th><th>Code</th><th>Category</th><th>Credit</th><th>Marks</th><th>Status</th>'
					+ '</tr></thead><tbody></tbody></table>');
				pack.modules.forEach(function (m, idx) {
					var checked = !m.already_exists;
					var status = m.already_exists
						? '<span class="smart-badge exists">Already in courses</span>'
						: '<span class="smart-badge ok">Ready to create</span>';
					var credit = m.credit != null ? m.credit : (m.credits != null ? m.credits : 0);
					var marks = Math.round((parseFloat(credit) || 0) * 10);
					var $tr = $('<tr data-idx="' + idx + '"></tr>');
					var $check = $('<input type="checkbox" class="smart-row-check">').prop('checked', checked).prop('disabled', !!m.already_exists);
					$tr.append($('<td></td>').append($check));
					$tr.append($('<td></td>').append($('<input type="text" class="form-control form-control-sm smart-title">').val(m.title || m.code || '')));
					$tr.append($('<td></td>').append($('<input type="text" class="form-control form-control-sm smart-code">').val(m.code || '')));
					$tr.append($('<td></td>').append($('<input type="text" class="form-control form-control-sm smart-cat">').val(m.category_title || 'General')));
					$tr.append($('<td></td>').append($('<input type="number" step="0.1" min="0" class="form-control form-control-sm credit-input smart-credit">').val(credit)));
					$tr.append($('<td></td>').append($('<input type="number" class="form-control form-control-sm marks-input smart-marks" readonly>').val(marks)));
					$tr.append($('<td></td>').html(status));
					$tbl.find('tbody').append($tr);
				});
				$body.append($tbl);
				$body.append(
					'<div class="smart-actions">'
					+ '<span class="text-muted mr-2" style="font-size:12px;">Courses only — assign teachers/classes later per course</span> '
					+ '<button type="button" class="btn btn-success btn-sm btn-smart-create"><i class="fa fa-plus"></i> Create selected</button>'
					+ '</div>'
				);
				$card.append($body);
				$box.append($card);
			});
			$('#smartEmpty').toggle(shown === 0);
			$('#smartWrap').addClass('is-on');
			if (shown) {
				$('#smartClassList .smart-class').first().addClass('is-open');
			}
		}

		$(document).on('click', '.smart-class-head', function () {
			$(this).closest('.smart-class').toggleClass('is-open');
		});
		$(document).on('change', '.smart-check-all', function () {
			var on = $(this).is(':checked');
			$(this).closest('table').find('.smart-row-check:not(:disabled)').prop('checked', on);
		});
		$(document).on('input change', '.smart-credit', function () {
			var c = parseFloat($(this).val()) || 0;
			$(this).closest('tr').find('.smart-marks').val(Math.round(c * 10));
		});
		$('#manualCredit').on('input change', function () {
			var c = parseFloat($(this).val()) || 0;
			$('#manualMarks').val(Math.round(c * 10));
		});

		$(document).on('click', '.btn-smart-create', function () {
			var $card = $(this).closest('.smart-class');
			var classId = parseInt($card.data('class-id'), 10) || 0;
			var courses = [];
			$card.find('tbody tr').each(function () {
				var $tr = $(this);
				if (!$tr.find('.smart-row-check').is(':checked')) return;
				var credit = parseFloat($tr.find('.smart-credit').val()) || 0;
				courses.push({
					title: $tr.find('.smart-title').val(),
					code: $tr.find('.smart-code').val(),
					category_title: $tr.find('.smart-cat').val() || 'General',
					credit: credit
				});
			});
			if (!courses.length) {
				if (window.toastada) toastada.error('Select at least one course');
				else alert('Select at least one course');
				return;
			}
			var $btn = $(this).prop('disabled', true).text('Creating…');
			$.ajax({
				url: '<?= base_url('smart_create_courses'); ?>',
				type: 'POST',
				dataType: 'json',
				data: {
					class_id: classId,
					program_type: currentType === '2' ? 'reb' : 'tvet',
					courses: JSON.stringify(courses)
				}
			}).done(function (res) {
				if (res && res.error) {
					if (window.toastada) toastada.error(res.error);
					else alert(res.error);
					$btn.prop('disabled', false).html('<i class="fa fa-plus"></i> Create selected');
					return;
				}
				if (window.toastada) toastada.success(res.success || 'Done');
				else alert(res.success || 'Done');
				window.location.reload();
			}).fail(function () {
				if (window.toastada) toastada.error('Create failed');
				$btn.prop('disabled', false).html('<i class="fa fa-plus"></i> Create selected');
			});
		});

		$("#assignModal, #editCourseModal, #addCourseCategory").on("shown.bs.modal", function () {
			var $modal = $(this);
			$modal.find("select.select2").each(function () {
				var $el = $(this);
				if ($el.hasClass("select2-hidden-accessible")) {
					$el.select2("destroy");
				}
				$el.select2({ width: "100%", dropdownParent: $modal });
			});
		});

		window.refreshCourseAssignments = function (courseId) {
			courseId = courseId || $("#assignModal [name='fId']").val();
			var $body = $("#assignSmartBody");
			if (!courseId) {
				$body.html('<tr class="text-muted"><td colspan="4" class="text-center">No course selected</td></tr>');
				return;
			}
			$body.html('<tr class="text-muted"><td colspan="4" class="text-center">Loading…</td></tr>');
			$.getJSON("<?= base_url('get_course_assignments'); ?>/" + courseId, function (data) {
				var rows = (data && data.assignments) ? data.assignments : [];
				if (!rows.length) {
					$body.html('<tr class="text-muted"><td colspan="4" class="text-center">No classes assigned yet</td></tr>');
					$("#assignSmartMeta").text("0 assignments");
					return;
				}
				$("#assignSmartMeta").text(rows.length + " assignment" + (rows.length === 1 ? "" : "s"));
				var html = "";
				$.each(rows, function (_, row) {
					var term = row.term || "—";
					html += "<tr>"
						+ "<td>" + $("<div>").text(row.class_name || "").html() + "</td>"
						+ "<td>" + $("<div>").text(row.teacher_name || "").html() + "</td>"
						+ "<td>" + $("<div>").text(String(term)).html() + "</td>"
						+ "<td><button type='button' class='btn btn-sm btn-outline-danger btn-unassign' data-id='" + row.id + "' title='Remove'><i class='fa fa-trash'></i></button></td>"
						+ "</tr>";
				});
				$body.html(html);
			}).fail(function () {
				$body.html('<tr class="text-danger"><td colspan="4" class="text-center">Could not load assignments</td></tr>');
			});
		};

		$(document).on("click", "#assignSmartBody .btn-unassign", function () {
			var id = $(this).data("id");
			if (!id || !confirm("Remove this course assignment?")) return;
			var $btn = $(this).prop("disabled", true);
			$.getJSON("<?= base_url('delete_course_assign'); ?>/" + id, function (data) {
				if (data && data.error) {
					if (window.toastada) toastada.error(data.error);
					$btn.prop("disabled", false);
					return;
				}
				if (window.toastada) toastada.success((data && data.success) || "Removed");
				window.refreshCourseAssignments();
			}).fail(function () {
				if (window.toastada) toastada.error("System error");
				$btn.prop("disabled", false);
			});
		});
	});
</script>
