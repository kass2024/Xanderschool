<!-- Parent Visiting — Smart Report -->
<?php
$summary = $report_summary ?? [];
$classSections = $class_sections ?? [];
$filterClassId = (int) ($filter_class_id ?? 0);
$viewClassId = (int) ($view_class_id ?? 0);
$fromDate = $from_date ?? date('Y-m-d');
$toDate = $to_date ?? date('Y-m-d');
$yearTitle = $academic_year_title ?? '';
$termLabel = $term ?? '';

$reportJson = json_encode([
	'school' => [
		'name' => $school_name ?? 'School',
		'year' => $yearTitle,
		'term' => $termLabel,
		'address' => $school_address ?? '',
		'phone' => $school_phone ?? '',
	],
	'range' => ['from' => $fromDate, 'to' => $toDate],
	'summary' => $summary,
	'sections' => $classSections,
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
if ($reportJson === false) {
	$reportJson = '{"school":{},"range":{},"summary":{},"sections":[]}';
}
?>
<style>
.pv-rpt { --navy:#0b1f4a; --gold:#c9a227; --ok:#166534; --warn:#b45309; --bg:#f1f5f9; }
.pv-rpt-toolbar {
  background:#fff; border-radius:12px; padding:16px; margin-bottom:16px;
  border:1px solid #e2e8f0; box-shadow:0 2px 8px rgba(15,23,42,.05);
}
.pv-rpt-actions { display:flex; flex-wrap:wrap; gap:8px; }
.pv-rpt-stats {
  display:grid; grid-template-columns:repeat(auto-fit,minmax(130px,1fr)); gap:10px; margin-bottom:16px;
}
.pv-rpt-stat {
  background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:12px 14px; text-align:center;
}
.pv-rpt-stat b { display:block; font-size:1.4rem; color:var(--navy); line-height:1.1; }
.pv-rpt-stat span { font-size:.72rem; color:#64748b; text-transform:uppercase; letter-spacing:.03em; }
.pv-rpt-stat.ok b { color:var(--ok); }
.pv-rpt-stat.warn b { color:var(--warn); }

.pv-overview-wrap {
  background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; margin-bottom:16px;
}
.pv-overview-wrap h6 {
  margin:0; padding:12px 16px; background:#f8fafc; border-bottom:1px solid #e2e8f0;
  font-size:.8rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:var(--navy);
}
.pv-overview { width:100%; margin:0; font-size:.84rem; }
.pv-overview th, .pv-overview td { padding:10px 12px; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
.pv-overview th { background:#fafafa; font-size:.72rem; text-transform:uppercase; color:#64748b; }
.pv-overview tr:hover td { background:#f8fafc; }
.pv-overview tr.active td { background:#eff6ff; }
.pv-rate-bar { height:8px; background:#e2e8f0; border-radius:999px; overflow:hidden; min-width:80px; }
.pv-rate-fill { height:100%; background:linear-gradient(90deg,#22c55e,#16a34a); border-radius:999px; }
.pv-rate-fill.low { background:linear-gradient(90deg,#fbbf24,#f59e0b); }

.pv-detail-panel {
  background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; margin-bottom:16px;
}
.pv-detail-head {
  display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:10px;
  padding:12px 16px; background:#f8fafc; border-bottom:1px solid #e2e8f0;
}
.pv-detail-head h5 { margin:0; font-size:1rem; font-weight:700; color:var(--navy); }
.pv-detail-body { padding:0; }
.pv-detail-tabs { display:flex; border-bottom:1px solid #e2e8f0; }
.pv-detail-tabs button {
  flex:1; border:0; background:#fff; padding:10px; font-size:.78rem; font-weight:700; color:#64748b; cursor:pointer;
}
.pv-detail-tabs button.active { color:var(--navy); border-bottom:2px solid var(--navy); background:#f8fafc; }
.pv-detail-tabs button.tab-warn.active { color:var(--warn); border-bottom-color:var(--warn); }
.pv-detail-tabs button.tab-ok.active { color:var(--ok); border-bottom-color:var(--ok); }

.pv-compact-table { width:100%; font-size:.8rem; margin:0; }
.pv-compact-table th, .pv-compact-table td { padding:7px 10px; border-bottom:1px solid #f1f5f9; }
.pv-compact-table th { background:#fafafa; font-size:.7rem; text-transform:uppercase; color:#64748b; position:sticky; top:0; }
.pv-table-scroll { max-height:420px; overflow:auto; }
.pv-empty { padding:24px; text-align:center; color:#94a3b8; font-size:.88rem; }

.pv-search-inline {
  padding:10px 12px; border-bottom:1px solid #f1f5f9; background:#fafafa;
}
.pv-search-inline input {
  width:100%; max-width:320px; border:1px solid #e2e8f0; border-radius:8px; padding:6px 10px; font-size:.84rem;
}
</style>

<div class="container-fluid mt-3 pv-rpt">
  <div class="pv-rpt-toolbar no-print">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
      <div>
        <h4 class="mb-1"><i class="fa fa-bar-chart"></i> Parent Visiting Report</h4>
        <p class="text-muted small mb-0">Browse by class — optimized for large schools. Print one class or the whole school.</p>
      </div>
      <div class="pv-rpt-actions">
        <button type="button" class="btn btn-primary btn-sm" id="btnPrintSchool">
          <i class="fa fa-print"></i> Print whole school
        </button>
        <button type="button" class="btn btn-outline-primary btn-sm" id="btnPrintClass" disabled>
          <i class="fa fa-print"></i> Print this class
        </button>
      </div>
    </div>
    <div class="row g-2">
      <div class="col-md-2">
        <label class="small mb-1">From</label>
        <input type="date" id="fromDate" class="form-control form-control-sm" value="<?= esc($fromDate) ?>">
      </div>
      <div class="col-md-2">
        <label class="small mb-1">To</label>
        <input type="date" id="toDate" class="form-control form-control-sm" value="<?= esc($toDate) ?>">
      </div>
      <div class="col-md-3">
        <label class="small mb-1">Scope (filter)</label>
        <select id="filterClass" class="form-control form-control-sm">
          <option value="0">All classes — school overview</option>
          <?php foreach (($classes ?? []) as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= $filterClassId === (int) $c['id'] ? 'selected' : '' ?>>
              <?= esc(trim(($c['level_name'] ?? '') . ' ' . ($c['code'] ?? '') . ' ' . ($c['title'] ?? ''))) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="small mb-1">Student ID (optional)</label>
        <input type="number" id="filterStudent" class="form-control form-control-sm" placeholder="Student ID" min="1"
               value="<?= (int) ($filter_student_id ?? 0) ?: '' ?>">
      </div>
      <div class="col-md-2 d-flex align-items-end">
        <button type="button" id="btnFilter" class="btn btn-success btn-sm w-100">Apply</button>
      </div>
    </div>
  </div>

  <div class="pv-rpt-stats no-print">
    <div class="pv-rpt-stat"><b><?= (int) ($summary['classes_count'] ?? 0) ?></b><span>Classes</span></div>
    <div class="pv-rpt-stat"><b><?= (int) ($summary['total_students'] ?? 0) ?></b><span>Students</span></div>
    <div class="pv-rpt-stat ok"><b><?= (int) ($summary['visited_students'] ?? 0) ?></b><span>Visited</span></div>
    <div class="pv-rpt-stat warn"><b><?= (int) ($summary['not_visited_students'] ?? 0) ?></b><span>Not visited</span></div>
    <div class="pv-rpt-stat"><b><?= (int) ($summary['total_visits'] ?? 0) ?></b><span>Check-ins</span></div>
  </div>

  <div class="pv-overview-wrap no-print">
    <h6><i class="fa fa-th-list"></i> Class overview — click a row to open details</h6>
    <div class="table-responsive">
      <table class="pv-overview" id="pvOverviewTable">
        <thead>
          <tr>
            <th>Class</th>
            <th>Students</th>
            <th>Visited</th>
            <th>Not visited</th>
            <th>Rate</th>
            <th></th>
          </tr>
        </thead>
        <tbody id="pvOverviewBody">
          <tr><td colspan="6" class="text-center text-muted py-4">Loading…</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <div class="pv-detail-panel no-print" id="pvDetailPanel" style="display:none;">
    <div class="pv-detail-head">
      <h5 id="pvDetailTitle">Class details</h5>
      <div class="pv-rpt-actions">
        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnCloseDetail">Close</button>
        <button type="button" class="btn btn-primary btn-sm" id="btnPrintClass2"><i class="fa fa-print"></i> Print class</button>
      </div>
    </div>
    <div class="pv-detail-tabs">
      <button type="button" class="tab-ok active" data-tab="visited">Visited</button>
      <button type="button" class="tab-warn" data-tab="not_visited">Not visited</button>
      <button type="button" data-tab="log">Visit log</button>
    </div>
    <div class="pv-search-inline">
      <input type="search" id="pvDetailSearch" placeholder="Search name or reg no in this class…" autocomplete="off">
    </div>
    <div class="pv-detail-body">
      <div class="pv-table-scroll" id="pvDetailTableWrap"></div>
    </div>
  </div>
</div>

<script>
(function () {
  var REPORT = <?= $reportJson ?: '{}' ?>;
  var activeClassId = <?= (int) $viewClassId ?>;
  var activeTab = 'visited';

  function esc(s) {
    if (s == null) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;');
  }

  function sectionById(id) {
    id = parseInt(id, 10);
    return (REPORT.sections || []).find(function (s) { return parseInt(s.class_id, 10) === id; }) || null;
  }

  function renderOverview() {
    var tbody = document.getElementById('pvOverviewBody');
    var sections = REPORT.sections || [];
    if (!sections.length) {
      tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No data for selected filters.</td></tr>';
      return;
    }
    var html = '';
    sections.forEach(function (sec) {
      var cid = parseInt(sec.class_id, 10);
      var total = sec.total_students || 0;
      var visited = sec.visited_count || 0;
      var notV = sec.not_visited_count || 0;
      var rate = sec.visit_rate || 0;
      var active = (activeClassId === cid) ? ' active' : '';
      html += '<tr class="pv-class-row' + active + '" data-class-id="' + cid + '">' +
        '<td><strong>' + esc(sec.class_label) + '</strong></td>' +
        '<td>' + total + '</td>' +
        '<td class="text-success">' + visited + '</td>' +
        '<td class="text-warning">' + notV + '</td>' +
        '<td><div class="d-flex align-items-center gap-2"><div class="pv-rate-bar"><div class="pv-rate-fill' + (rate < 50 ? ' low' : '') + '" style="width:' + rate + '%"></div></div><span class="small">' + rate + '%</span></div></td>' +
        '<td><button type="button" class="btn btn-sm btn-outline-primary pv-open-class">View</button></td>' +
        '</tr>';
    });
    tbody.innerHTML = html;
  }

  function filterRows(rows, q) {
    q = (q || '').toLowerCase().trim();
    if (!q) return rows;
    return rows.filter(function (r) {
      return (r.student_name || '').toLowerCase().indexOf(q) >= 0 ||
             (r.regno || '').toLowerCase().indexOf(q) >= 0;
    });
  }

  function renderDetailTable(sec, tab, q) {
    var wrap = document.getElementById('pvDetailTableWrap');
    if (!sec) {
      wrap.innerHTML = '<div class="pv-empty">Select a class from the overview.</div>';
      return;
    }
    var html = '';
    if (tab === 'visited') {
      var rows = filterRows(sec.visited || [], q);
      if (!rows.length) {
        wrap.innerHTML = '<div class="pv-empty">No visited students' + (q ? ' matching search' : '') + '.</div>';
        return;
      }
      html = '<table class="pv-compact-table"><thead><tr><th>#</th><th>Reg</th><th>Student</th><th>Visits</th><th>Visitors</th><th>Received by</th><th>First in</th><th>Last out</th></tr></thead><tbody>';
      rows.forEach(function (r, i) {
        html += '<tr><td>' + (i+1) + '</td><td>' + esc(r.regno || '—') + '</td><td>' + esc(r.student_name) + '</td>' +
          '<td>' + (r.visit_count || 0) + '</td><td>' + esc(r.visitor_summary || '—') + '</td>' +
          '<td>' + esc(r.received_summary || '—') + '</td>' +
          '<td>' + esc(r.first_check_in || '—') + '</td><td>' + esc(r.last_check_out || '—') + '</td></tr>';
      });
      html += '</tbody></table>';
    } else if (tab === 'not_visited') {
      var nrows = filterRows(sec.not_visited || [], q);
      if (!nrows.length) {
        wrap.innerHTML = '<div class="pv-empty">All students visited' + (q ? ' (or none match search)' : '') + '.</div>';
        return;
      }
      html = '<table class="pv-compact-table"><thead><tr><th>#</th><th>Reg</th><th>Student</th></tr></thead><tbody>';
      nrows.forEach(function (r, i) {
        html += '<tr><td>' + (i+1) + '</td><td>' + esc(r.regno || '—') + '</td><td>' + esc(r.student_name) + '</td></tr>';
      });
      html += '</tbody></table>';
    } else {
      var logs = [];
      (sec.visited || []).forEach(function (st) {
        (st.visits || []).forEach(function (v) {
          logs.push({
            student_name: st.student_name,
            regno: st.regno,
            visit_date: v.visit_date,
            time_in: v.time_in,
            time_out: v.time_out,
            visitor_name: v.visitor_name,
            relationship: v.relationship,
            received_by: v.received_by || '',
            source: v.source
          });
        });
      });
      if (q) {
        logs = logs.filter(function (l) {
          return (l.student_name || '').toLowerCase().indexOf(q) >= 0 ||
                 (l.regno || '').toLowerCase().indexOf(q) >= 0 ||
                 (l.visitor_name || '').toLowerCase().indexOf(q) >= 0;
        });
      }
      if (!logs.length) {
        wrap.innerHTML = '<div class="pv-empty">No visit log entries' + (q ? ' matching search' : '') + '.</div>';
        return;
      }
      html = '<table class="pv-compact-table"><thead><tr><th>#</th><th>Date</th><th>In</th><th>Out</th><th>Visitor</th><th>Rel.</th><th>Received by</th><th>Student</th><th>Reg</th><th>Src</th></tr></thead><tbody>';
      logs.forEach(function (l, i) {
        html += '<tr><td>' + (i+1) + '</td><td>' + esc(l.visit_date) + '</td>' +
          '<td>' + fmtTime(l.time_in) + '</td><td>' + fmtTime(l.time_out) + '</td>' +
          '<td>' + esc(l.visitor_name || '') + '</td><td>' + esc(l.relationship || '') + '</td>' +
          '<td>' + esc(l.received_by || '—') + '</td>' +
          '<td>' + esc(l.student_name) + '</td><td>' + esc(l.regno || '') + '</td><td>' + esc(l.source || '') + '</td></tr>';
      });
      html += '</tbody></table>';
    }
    wrap.innerHTML = html;
  }

  function fmtTime(ts) {
    if (!ts) return '—';
    var d = new Date(parseInt(ts, 10) * 1000);
    if (isNaN(d.getTime())) return '—';
    return ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2);
  }

  function fmtDateTime(ts) {
    if (!ts) return '—';
    var d = new Date(parseInt(ts, 10) * 1000);
    if (isNaN(d.getTime())) return '—';
    return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2) +
      ' ' + ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2);
  }

  function visitRowsForSection(sec) {
    var rows = [];
    (sec.visited || []).forEach(function (st) {
      var visits = st.visits || [];
      if (!visits.length) {
        rows.push({
          regno: st.regno || '',
          student_name: st.student_name || '',
          visit_date: '—',
          time_in: null,
          time_out: null,
          visitor_name: st.visitor_summary || '—',
          relationship: '—',
          received_by: st.received_summary || '—',
          source: '—'
        });
        return;
      }
      visits.forEach(function (v) {
        rows.push({
          regno: st.regno || '',
          student_name: st.student_name || '',
          visit_date: v.visit_date || '—',
          time_in: v.time_in || null,
          time_out: v.time_out || null,
          visitor_name: v.visitor_name || '—',
          relationship: v.relationship || '—',
          received_by: v.received_by || '—',
          source: v.source || '—'
        });
      });
    });
    return rows;
  }

  function buildPrintHtml(classId) {
    var sch = REPORT.school || {};
    var range = REPORT.range || {};
    var sections = REPORT.sections || [];
    if (classId) {
      sections = sections.filter(function (s) { return parseInt(s.class_id, 10) === parseInt(classId, 10); });
    }
    if (!sections.length) {
      return '<!DOCTYPE html><html><body><p>No data to print.</p></body></html>';
    }

    var totalPages = sections.length;

    var css =
      '@page{size:A4 portrait;margin:10mm 12mm 12mm}' +
      'body{font-family:Segoe UI,Arial,sans-serif;font-size:8.5pt;color:#111;margin:0;line-height:1.35}' +
      '.print-page{page-break-after:always}.print-page:last-child{page-break-after:auto}' +
      '.print-head{border-bottom:2px solid #0b1f4a;padding-bottom:8px;margin-bottom:6px}' +
      '.print-head h1{margin:0;font-size:14pt;color:#0b1f4a}.print-head h2{margin:2px 0 0;font-size:11pt;font-weight:600;color:#334155}' +
      '.print-head p{margin:4px 0 0;font-size:8pt;color:#475569}' +
      '.print-meta{display:flex;justify-content:space-between;flex-wrap:wrap;gap:4px;font-size:7.5pt;color:#64748b;margin-bottom:10px}' +
      '.print-stats{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px}.stat{font-size:7.5pt;padding:3px 8px;border:1px solid #cbd5e1;border-radius:4px;background:#f8fafc}' +
      '.section{margin-top:10px}.section h3{margin:0 0 5px;font-size:9.5pt;padding:5px 8px;color:#fff;-webkit-print-color-adjust:exact;print-color-adjust:exact}' +
      '.section h3.visited{background:#166534}.section h3.not-visited{background:#b45309}' +
      'table{width:100%;border-collapse:collapse;margin:0 0 8px;font-size:7.5pt}' +
      'th,td{border:1px solid #94a3b8;padding:3px 5px;text-align:left;vertical-align:top}' +
      'th{background:#e2e8f0;font-size:7pt;text-transform:uppercase;letter-spacing:.02em}' +
      'tr:nth-child(even) td{background:#f8fafc}' +
      '.empty td{text-align:center;color:#64748b;font-style:italic}' +
      '.foot{margin-top:10px;padding-top:6px;border-top:1px solid #cbd5e1;font-size:7pt;color:#64748b;text-align:center}';

    var html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Parent Visiting Report</title><style>' + css + '</style></head><body>';

    sections.forEach(function (sec, idx) {
      var pageNum = idx + 1;
      var visitRows = visitRowsForSection(sec);
      var notVisited = sec.not_visited || [];

      html += '<div class="print-page">';
      html += '<div class="print-head">';
      html += '<h1>' + esc(sch.name || 'School') + '</h1>';
      html += '<h2>Parent visiting report — ' + esc(sec.class_label || 'Class') + '</h2>';
      html += '<p>' + esc(range.from || '') + ' → ' + esc(range.to || '');
      if (sch.year) html += ' &nbsp;|&nbsp; ' + esc(sch.year);
      if (sch.term) html += ' &nbsp;|&nbsp; ' + esc(sch.term);
      if (sch.phone) html += ' &nbsp;|&nbsp; Tel: ' + esc(sch.phone);
      html += '</p></div>';

      html += '<div class="print-meta">';
      html += '<span>Page ' + pageNum + ' of ' + totalPages + '</span>';
      html += '<span>Printed: ' + esc(new Date().toLocaleString()) + '</span>';
      html += '</div>';

      html += '<div class="print-stats">' +
        '<span class="stat">Students: ' + (sec.total_students || 0) + '</span>' +
        '<span class="stat">Visited: ' + (sec.visited_count || 0) + '</span>' +
        '<span class="stat">Not visited: ' + (sec.not_visited_count || 0) + '</span>' +
        '<span class="stat">Visit rate: ' + (sec.visit_rate || 0) + '%</span>' +
        '</div>';

      html += '<div class="section"><h3 class="visited">Visited — ' + (sec.visited_count || 0) + ' student(s), ' + visitRows.length + ' visit record(s)</h3>';
      html += '<table><thead><tr>' +
        '<th>#</th><th>Reg</th><th>Student</th><th>Date</th><th>Time in</th><th>Time out</th><th>Visitor</th><th>Relationship</th><th>Received by</th><th>Source</th>' +
        '</tr></thead><tbody>';
      if (!visitRows.length) {
        html += '<tr class="empty"><td colspan="10">No visited students for this period</td></tr>';
      } else {
        visitRows.forEach(function (r, i) {
          html += '<tr><td>' + (i + 1) + '</td><td>' + esc(r.regno || '—') + '</td><td>' + esc(r.student_name) + '</td>' +
            '<td>' + esc(r.visit_date) + '</td><td>' + fmtDateTime(r.time_in) + '</td><td>' + fmtDateTime(r.time_out) + '</td>' +
            '<td>' + esc(r.visitor_name) + '</td><td>' + esc(r.relationship) + '</td><td>' + esc(r.received_by || '—') + '</td><td>' + esc(r.source) + '</td></tr>';
        });
      }
      html += '</tbody></table></div>';

      html += '<div class="section"><h3 class="not-visited">Not visited — ' + (sec.not_visited_count || 0) + ' student(s)</h3>';
      html += '<table><thead><tr><th>#</th><th>Reg</th><th>Student</th></tr></thead><tbody>';
      if (!notVisited.length) {
        html += '<tr class="empty"><td colspan="3">All students in this class were visited</td></tr>';
      } else {
        notVisited.forEach(function (r, i) {
          html += '<tr><td>' + (i + 1) + '</td><td>' + esc(r.regno || '—') + '</td><td>' + esc(r.student_name) + '</td></tr>';
        });
      }
      html += '</tbody></table></div>';

      html += '<div class="foot">Generated by Xander School — Parent Visiting Module</div>';
      html += '</div>';
    });

    html += '</body></html>';
    return html;
  }

  function openClass(classId) {
    activeClassId = parseInt(classId, 10);
    var sec = sectionById(activeClassId);
    var panel = document.getElementById('pvDetailPanel');
    if (!sec) {
      panel.style.display = 'none';
      document.getElementById('btnPrintClass').disabled = true;
      renderOverview();
      return;
    }
    panel.style.display = 'block';
    document.getElementById('pvDetailTitle').textContent = sec.class_label + ' — ' +
      (sec.visited_count || 0) + ' visited, ' + (sec.not_visited_count || 0) + ' not visited';
    document.getElementById('btnPrintClass').disabled = false;
    document.getElementById('pvDetailSearch').value = '';
    renderDetailTable(sec, activeTab, '');
    renderOverview();
    panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  function printReport(classId) {
    var w = window.open('', '_blank', 'width=900,height=700');
    if (!w) { alert('Allow pop-ups to print the report.'); return; }
    w.document.open();
    w.document.write(buildPrintHtml(classId || null));
    w.document.close();
    w.focus();
    setTimeout(function () { w.print(); }, 600);
  }

  document.getElementById('pvOverviewBody').addEventListener('click', function (e) {
    var row = e.target.closest('.pv-class-row');
    if (!row) return;
    openClass(row.getAttribute('data-class-id'));
  });

  document.querySelectorAll('.pv-detail-tabs button').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.querySelectorAll('.pv-detail-tabs button').forEach(function (b) { b.classList.remove('active'); });
      btn.classList.add('active');
      activeTab = btn.getAttribute('data-tab');
      renderDetailTable(sectionById(activeClassId), activeTab, document.getElementById('pvDetailSearch').value);
    });
  });

  document.getElementById('pvDetailSearch').addEventListener('input', function () {
    renderDetailTable(sectionById(activeClassId), activeTab, this.value);
  });

  document.getElementById('btnCloseDetail').addEventListener('click', function () {
    activeClassId = 0;
    document.getElementById('pvDetailPanel').style.display = 'none';
    document.getElementById('btnPrintClass').disabled = true;
    renderOverview();
  });

  document.getElementById('btnPrintSchool').addEventListener('click', function () { printReport(null); });
  document.getElementById('btnPrintClass').addEventListener('click', function () {
    if (activeClassId) printReport(activeClassId);
  });
  document.getElementById('btnPrintClass2').addEventListener('click', function () {
    if (activeClassId) printReport(activeClassId);
  });

  document.getElementById('btnFilter').addEventListener('click', function () {
    var from = document.getElementById('fromDate').value;
    var to = document.getElementById('toDate').value;
    var classId = document.getElementById('filterClass').value || '0';
    var studentId = document.getElementById('filterStudent').value || '';
    var url = '<?= base_url('parent_visiting/report') ?>?from=' + encodeURIComponent(from) +
      '&to=' + encodeURIComponent(to) + '&class_id=' + encodeURIComponent(classId);
    if (studentId) url += '&student_id=' + encodeURIComponent(studentId);
    if (activeClassId) url += '&view_class=' + encodeURIComponent(activeClassId);
    window.location.href = url;
  });

  renderOverview();
  if (activeClassId) {
    openClass(activeClassId);
  } else if ((REPORT.sections || []).length === 1) {
    openClass(REPORT.sections[0].class_id);
  }
})();
</script>
