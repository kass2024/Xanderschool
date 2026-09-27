<?php
/** @var array $years */
/** @var bool $sees_all */
/** @var int $current_year */
/** @var int $current_term */
$current_term = (int) ($current_term ?? 1);
?>
<style>
	.ms-dash { width:100%; color:#0f2744; }
	.ms-filters { display:flex; flex-wrap:wrap; gap:.75rem; margin-bottom:1rem; }
	.ms-filters .form-group { margin:0; min-width:160px; }
	.ms-kpis { display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:.75rem; margin-bottom:1rem; }
	.ms-kpi { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:.9rem 1rem; box-shadow:0 6px 18px rgba(15,39,68,.04); }
	.ms-kpi button { all:unset; cursor:pointer; display:block; width:100%; }
	.ms-kpi span { display:block; color:#64748b; font-size:.75rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase; }
	.ms-kpi strong { display:block; margin-top:.2rem; font-size:1.7rem; line-height:1; }
	.ms-kpi.ok strong { color:#15803d; }
	.ms-kpi.warn strong { color:#b45309; }
	.ms-kpi.bad strong { color:#b91c1c; }
	.ms-kpi.info strong { color:#0f2744; }
	.ms-layout { display:grid; grid-template-columns:minmax(280px, 420px) minmax(0, 1fr); gap:.9rem; align-items:start; }
	.ms-panel { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1rem 1.05rem 1.1rem; }
	.ms-panel h4 { margin:0 0 .75rem; font-size:1rem; font-weight:800; }
	.ms-panel .form-group { margin-bottom:.7rem; }
	.ms-search { position:relative; }
	.ms-search input { width:100%; }
	.ms-menu { position:absolute; z-index:8; left:0; right:0; top:calc(100% + 4px); max-height:240px; overflow:auto; background:#fff; border:1px solid #d6deea; border-radius:10px; box-shadow:0 10px 24px rgba(15,39,68,.12); }
	.ms-menu button { display:block; width:100%; text-align:left; border:0; background:#fff; padding:.55rem .7rem; color:#0f2744; }
	.ms-menu button:hover, .ms-menu button.active { background:#eef4fb; }
	.ms-menu .muted { color:#64748b; font-size:.8rem; }
	.ms-chip { display:inline-flex; align-items:center; gap:.4rem; margin-top:.4rem; background:#e8eef8; color:#0f2744; border-radius:999px; padding:.2rem .55rem .2rem .7rem; font-size:.82rem; font-weight:700; }
	.ms-chip button { border:0; background:transparent; color:#64748b; font-weight:800; cursor:pointer; }
	.ms-list { display:flex; flex-direction:column; gap:.45rem; max-height:420px; overflow:auto; }
	.ms-person, .ms-miss { border:1px solid #e2e8f0; border-radius:10px; padding:.55rem .7rem; background:#f8fafc; }
	.ms-person b, .ms-miss b { display:block; }
	.ms-person span, .ms-miss span { color:#64748b; font-size:.82rem; }
	.ms-empty { color:#64748b; margin:0; }
	.ms-subhead { margin:0 0 .55rem; color:#64748b; font-size:.86rem; }
	@media (max-width:900px) {
		.ms-layout { grid-template-columns:1fr; }
	}
</style>
<div class="ms-dash">
	<div class="ms-filters">
		<div class="form-group">
			<label>Academic year</label>
			<select class="form-control" id="msYear">
				<?php foreach ($years as $year): ?>
					<option value="<?= (int) $year['id']; ?>" <?= (int) $year['id'] === (int) $current_year ? 'selected' : ''; ?>><?= esc($year['title']); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="form-group">
			<label>Term</label>
			<select class="form-control" id="msTerm">
				<?php for ($t = 1; $t <= 3; $t++): ?>
					<option value="<?= $t; ?>" <?= $t === $current_term ? 'selected' : ''; ?>>Term <?= $t; ?></option>
				<?php endfor; ?>
			</select>
		</div>
	</div>
	<div class="ms-kpis" id="msKpis"></div>
	<div class="ms-layout">
		<section class="ms-panel">
			<h4>Open a marks sheet</h4>
			<form action="<?= base_url('get_uploaded_marks/1'); ?>" method="post" target="_blank" id="markSheetForm">
				<input type="hidden" name="year" id="msYearPost">
				<input type="hidden" name="term" id="msTermPost">
				<?php if ($sees_all): ?>
				<div class="form-group ms-search">
					<label>Teacher</label>
					<input type="search" class="form-control" id="msTeacherQ" placeholder="Search teacher..." autocomplete="off">
					<div class="ms-menu" id="msTeacherMenu" hidden></div>
					<div id="msTeacherChip"></div>
				</div>
				<?php endif; ?>
				<div class="form-group">
					<label>Course with marks</label>
					<select class="form-control" name="record_id" id="msCourse" required>
						<option value="">Select a course</option>
					</select>
				</div>
				<button class="btn btn-success" type="submit" id="msGo" disabled>Generate mark sheet</button>
			</form>
		</section>
		<section class="ms-panel" id="msGapPanel">
			<h4 id="msGapTitle"><?= $sees_all ? 'Teachers with no marks' : 'Courses still empty'; ?></h4>
			<p class="ms-subhead" id="msGapHint"></p>
			<div class="ms-list" id="msGapList"></div>
		</section>
	</div>
	<section class="ms-panel" id="msMissingPanel" style="margin-top:.9rem;<?= $sees_all ? '' : 'display:none;'; ?>">
		<h4>Courses with no marks entered</h4>
		<p class="ms-subhead">Assigned this term, and still empty.</p>
		<div class="ms-list" id="msMissingList"></div>
	</section>
</div>
<script>
(function () {
	var seesAll = <?= $sees_all ? 'true' : 'false'; ?>;
	var payload = { courses: [], missing_teachers: [], missing_courses: [], summary: {} };
	var teacherId = '0';
	function esc(value) {
		return $('<div>').text(value == null ? '' : String(value)).html();
	}
	function summary() { return payload.summary || {}; }
	function paintKpis() {
		var s = summary();
		var cards = [
			{ cls: 'ok', n: s.with_marks || 0, label: 'Courses with marks', target: '' },
			{ cls: 'warn', n: s.missing || 0, label: 'Missing any entry', target: seesAll ? '#msMissingPanel' : '#msGapPanel' }
		];
		if (seesAll) {
			cards.push({ cls: 'info', n: s.teachers_filled || 0, label: 'Teachers who entered marks', target: '' });
			cards.push({ cls: 'bad', n: s.teachers_missing || 0, label: 'Teachers with no marks', target: '#msGapPanel' });
		}
		var html = '';
		cards.forEach(function (card) {
			html += '<article class="ms-kpi ' + card.cls + '"><button type="button" data-target="' + card.target + '"><span>' + esc(card.label) + '</span><strong>' + esc(card.n) + '</strong></button></article>';
		});
		$('#msKpis').html(html);
	}
	function coursesWithMarks() {
		return (payload.courses || []).filter(function (row) {
			if (!row.has_marks) return false;
			if (!seesAll || teacherId === '0') return true;
			return String(row.lecturer) === teacherId;
		});
	}
	function paintCourses() {
		var rows = coursesWithMarks();
		var html = '<option value="">' + (rows.length ? 'Select a course' : 'No marks recorded for this filter') + '</option>';
		rows.forEach(function (row) {
			html += '<option value="' + esc(row.id) + '">' + esc(row.label) + '</option>';
		});
		$('#msCourse').html(html);
		$('#msGo').prop('disabled', true);
	}
	function teacherRows(query) {
		var q = String(query || '').toLowerCase().trim();
		var map = {};
		(payload.courses || []).forEach(function (row) {
			var id = String(row.lecturer);
			if (!map[id]) map[id] = { id: id, name: row.teacher, withMarks: 0, missing: 0 };
			if (row.has_marks) map[id].withMarks++; else map[id].missing++;
		});
		return Object.keys(map).map(function (id) { return map[id]; }).filter(function (row) {
			return q === '' || String(row.name).toLowerCase().indexOf(q) !== -1;
		}).sort(function (a, b) { return String(a.name).localeCompare(String(b.name)); });
	}
	function paintTeacherMenu() {
		if (!seesAll) return;
		var rows = teacherRows($('#msTeacherQ').val()).slice(0, 12);
		var html = '<button type="button" data-id="0" data-name="">All teachers</button>';
		rows.forEach(function (row) {
			html += '<button type="button" data-id="' + esc(row.id) + '" data-name="' + esc(row.name) + '">' + esc(row.name) + ' <span class="muted">' + esc(row.withMarks) + ' with marks</span></button>';
		});
		if (!rows.length) html += '<button type="button" disabled>No teacher matches</button>';
		$('#msTeacherMenu').html(html).removeAttr('hidden');
	}
	function paintChip() {
		if (!seesAll) return;
		if (teacherId === '0') { $('#msTeacherChip').empty(); return; }
		var name = 'Teacher';
		(payload.courses || []).some(function (row) {
			if (String(row.lecturer) === teacherId) { name = row.teacher; return true; }
			return false;
		});
		$('#msTeacherChip').html('<span class="ms-chip">' + esc(name) + '<button type="button" id="msClearTeacher">&times;</button></span>');
	}
	function paintGaps() {
		if (seesAll) {
			var people = payload.missing_teachers || [];
			$('#msGapHint').text(people.length ? 'Assigned this term and none of their courses have a saved mark.' : 'Every assigned teacher has entered at least one mark.');
			if (!people.length) {
				$('#msGapList').html('<p class="ms-empty">No teacher is completely empty.</p>');
			} else {
				var html = '';
				people.forEach(function (person) {
					var extra = (person.missing_labels || []).join(' · ');
					html += '<article class="ms-person"><b>' + esc(person.name) + '</b><span>' + esc(person.missing) + ' course' + (person.missing === 1 ? '' : 's') + (extra ? ' — ' + esc(extra) : '') + '</span></article>';
				});
				$('#msGapList').html(html);
			}
			var missing = payload.missing_courses || [];
			if (!missing.length) {
				$('#msMissingList').html('<p class="ms-empty">Every assigned course has marks.</p>');
			} else {
				var list = '';
				missing.forEach(function (row) {
					list += '<article class="ms-miss"><b>' + esc(row.label) + '</b><span>' + esc(row.teacher) + '</span></article>';
				});
				$('#msMissingList').html(list);
			}
			return;
		}
		var ownMissing = payload.missing_courses || [];
		$('#msGapHint').text(ownMissing.length ? 'These assigned courses still have no saved mark.' : 'All of your courses for this term have marks.');
		if (!ownMissing.length) {
			$('#msGapList').html('<p class="ms-empty">Nothing missing.</p>');
			return;
		}
		var own = '';
		ownMissing.forEach(function (row) {
			own += '<article class="ms-miss"><b>' + esc(row.label) + '</b></article>';
		});
		$('#msGapList').html(own);
	}
	function paint() {
		paintKpis();
		paintCourses();
		paintChip();
		paintGaps();
	}
	function load() {
		var year = $('#msYear').val();
		var term = $('#msTerm').val();
		$('#msYearPost').val(year);
		$('#msTermPost').val(term);
		if (!year) return;
		$.getJSON('<?= base_url('mark_sheet_courses'); ?>', { year: year, term: term }).done(function (res) {
			payload = res || payload;
			if (teacherId !== '0' && !teacherRows('').some(function (row) { return row.id === teacherId; })) {
				teacherId = '0';
				$('#msTeacherQ').val('');
			}
			paint();
		});
	}
	$('#msYear, #msTerm').on('change', load);
	$('#msCourse').on('change', function () { $('#msGo').prop('disabled', !$(this).val()); });
	$('#msTeacherQ').on('input focus', paintTeacherMenu);
	$('#msTeacherMenu').on('click', 'button[data-id]', function () {
		teacherId = String($(this).attr('data-id'));
		$('#msTeacherQ').val(teacherId === '0' ? '' : ($(this).attr('data-name') || ''));
		$('#msTeacherMenu').attr('hidden', true);
		paintCourses();
		paintChip();
	});
	$('#msTeacherChip').on('click', '#msClearTeacher', function () {
		teacherId = '0';
		$('#msTeacherQ').val('');
		paintCourses();
		paintChip();
	});
	$(document).on('click', function (event) {
		if (!$(event.target).closest('.ms-search').length) $('#msTeacherMenu').attr('hidden', true);
	});
	$('#msKpis').on('click', 'button[data-target]', function () {
		var target = $(this).data('target');
		if (target && $(target).length) $('html, body').animate({ scrollTop: $(target).offset().top - 80 }, 200);
	});
	load();
})();
</script>
