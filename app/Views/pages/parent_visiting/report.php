<!-- Parent Visiting — Report -->
<style>
  @media print {
    .no-print, .app-header, .app-sidebar, .app-footer { display: none !important; }
    .pv-report-print { box-shadow: none !important; border: none !important; }
    body { background: #fff !important; }
  }
</style>

<div class="container-fluid mt-4">
  <div class="card shadow-sm border-0 pv-report-print">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap no-print">
        <h4 class="card-title mb-0">
          <i class="fa fa-list-alt"></i> Parent Visiting Report
        </h4>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">
          <i class="fa fa-print"></i> Print
        </button>
      </div>

      <div class="row g-2 mb-3 no-print">
        <div class="col-md-2">
          <label>From</label>
          <input type="date" id="fromDate" class="form-control" value="<?= esc($from_date ?? date('Y-m-d')) ?>">
        </div>
        <div class="col-md-2">
          <label>To</label>
          <input type="date" id="toDate" class="form-control" value="<?= esc($to_date ?? date('Y-m-d')) ?>">
        </div>
        <div class="col-md-3">
          <label>Class</label>
          <select id="filterClass" class="form-control">
            <option value="0">All classes</option>
            <?php foreach (($classes ?? []) as $c): ?>
              <option value="<?= (int) $c['id'] ?>">
                <?= esc(($c['level_name'] ?? '') . ' ' . ($c['title'] ?? '') . ' ' . ($c['code'] ?? '')) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label>Student (optional id)</label>
          <input type="number" id="filterStudent" class="form-control" placeholder="Student ID" min="1">
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <button type="button" id="btnFilter" class="btn btn-success w-100">Filter</button>
        </div>
      </div>

      <div class="mb-2">
        <strong><?= esc($school_name ?? '') ?></strong>
        — Visiting report
        <span id="reportRangeLabel"></span>
      </div>

      <div class="table-responsive">
        <table class="table table-bordered table-sm table-hover" id="reportTable">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Date</th>
              <th>Time in</th>
              <th>Time out</th>
              <th>Visitor</th>
              <th>Relationship</th>
              <th>Student</th>
              <th>Class</th>
              <th>Source</th>
            </tr>
          </thead>
          <tbody id="reportBody">
            <?php if (empty($visits)): ?>
              <tr><td colspan="9" class="text-center text-muted">No visits for selected filters.</td></tr>
            <?php else: ?>
              <?php $n = 1; foreach ($visits as $v): ?>
                <tr>
                  <td><?= $n++ ?></td>
                  <td><?= esc($v['visit_date']) ?></td>
                  <td><?= !empty($v['time_in']) ? date('H:i', (int) $v['time_in']) : '—' ?></td>
                  <td><?= !empty($v['time_out']) ? date('H:i', (int) $v['time_out']) : '—' ?></td>
                  <td><?= esc($v['visitor_name']) ?></td>
                  <td><?= esc($v['relationship'] ?? '') ?></td>
                  <td><?= esc($v['student_name']) ?></td>
                  <td><?= esc($v['class_name'] ?? '') ?></td>
                  <td><?= esc($v['source'] ?? '') ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
  function reload() {
    const from = document.getElementById("fromDate").value;
    const to = document.getElementById("toDate").value;
    const classId = document.getElementById("filterClass").value || "0";
    const studentId = document.getElementById("filterStudent").value || "";
    let url = "<?= base_url('parent_visiting/report') ?>?from=" + encodeURIComponent(from) +
              "&to=" + encodeURIComponent(to) +
              "&class_id=" + encodeURIComponent(classId);
    if (studentId) url += "&student_id=" + encodeURIComponent(studentId);
    window.location.href = url;
  }
  document.getElementById("btnFilter").addEventListener("click", reload);
  const from = document.getElementById("fromDate").value;
  const to = document.getElementById("toDate").value;
  document.getElementById("reportRangeLabel").textContent = " (" + from + " → " + to + ")";
});
</script>
