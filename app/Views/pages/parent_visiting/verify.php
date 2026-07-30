<!-- Parent Visiting — Verify visit -->
<style>
  .pv-scan-wrap { max-width: 720px; margin: 0 auto; }
  .pv-result {
    border-radius: 12px;
    padding: 28px 24px;
    min-height: 180px;
    text-align: center;
    transition: background .2s, color .2s;
  }
  .pv-result.idle { background: #f5f7fa; color: #555; }
  .pv-result.allowed { background: #d4edda; color: #155724; border: 2px solid #28a745; }
  .pv-result.denied { background: #f8d7da; color: #721c24; border: 2px solid #dc3545; }
  .pv-result .pv-status { font-size: 2.2rem; font-weight: 700; letter-spacing: .04em; }
  .pv-result .pv-meta { font-size: 1.05rem; margin-top: 10px; }
  #verifyCardInput {
    font-size: 1.35rem;
    letter-spacing: .08em;
    text-align: center;
    height: 56px;
  }
</style>

<div class="container-fluid mt-4">
  <div class="card shadow-sm border-0 pv-scan-wrap">
    <div class="card-body">
      <h4 class="card-title mb-1">
        <i class="fa fa-id-card"></i> Verify Parent Visit
      </h4>
      <p class="text-muted mb-3">Visiting day verification — scan visitor smart card (keyboard RFID reader).</p>

      <div class="mb-3">
        <label class="form-label">Scan visitor card</label>
        <input type="text" id="verifyCardInput" class="form-control"
               placeholder="Waiting for card scan..." readonly>
      </div>

      <div id="scanResult" class="pv-result idle">
        <div class="pv-status">READY</div>
        <div class="pv-meta">Place visitor card on the reader</div>
      </div>

      <div id="scanDetails" class="mt-3 d-none">
        <table class="table table-bordered table-sm mb-0">
          <tr><th style="width:40%">Visitor</th><td id="dVisitor"></td></tr>
          <tr><th>Relationship</th><td id="dRel"></td></tr>
          <tr><th>Student</th><td id="dStudent"></td></tr>
          <tr><th>Class</th><td id="dClass"></td></tr>
          <tr><th>Action</th><td id="dAction"></td></tr>
          <tr><th>Time</th><td id="dTime"></td></tr>
        </table>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
  const school_id = <?= json_encode(session('soma_school_id')) ?>;
  const operator  = <?= json_encode(session('soma_id')) ?>;
  const cardInput = document.getElementById("verifyCardInput");
  const resultBox = document.getElementById("scanResult");
  const details = document.getElementById("scanDetails");
  let buffer = "";
  let busy = false;

  function normalizeUID(uid) {
    uid = uid.trim();
    if (!uid) return "";
    if (/^\d+$/.test(uid)) {
      try {
        const num = BigInt(uid);
        uid = num.toString(16).toUpperCase();
        uid = uid.padStart(8, "0");
      } catch (e) {}
    }
    uid = uid.replace(/[^A-Fa-f0-9]/g, "").toUpperCase();
    if (uid.length % 2 === 0) {
      const bytes = uid.match(/.{1,2}/g);
      bytes.reverse();
      uid = bytes.join("");
    }
    return uid.toUpperCase();
  }

  function setResult(state, title, meta) {
    resultBox.className = "pv-result " + state;
    resultBox.innerHTML = '<div class="pv-status">' + title + '</div><div class="pv-meta">' + meta + '</div>';
  }

  document.addEventListener("keypress", function (e) {
    if (busy) return;
    // Ignore if typing in another editable field (except our readonly input)
    const tag = (e.target && e.target.tagName) ? e.target.tagName.toLowerCase() : "";
    if ((tag === "input" || tag === "textarea") && e.target.id !== "verifyCardInput") {
      if (!e.target.readOnly) return;
    }
    if (e.key === "Enter") {
      const uid = buffer.trim();
      buffer = "";
      if (uid.length >= 5) {
        const normalized = normalizeUID(uid);
        cardInput.value = normalized;
        scanCard(normalized);
      }
    } else {
      buffer += e.key;
    }
  });

  function scanCard(card) {
    busy = true;
    setResult("idle", "SCANNING...", "Looking up visitor card");
    details.classList.add("d-none");

    fetch("<?= base_url('parent_visiting/scan') ?>", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: "card=" + encodeURIComponent(card) +
            "&school_id=" + encodeURIComponent(school_id) +
            "&operator=" + encodeURIComponent(operator) +
            "&source=web"
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        busy = false;
        if (!res.allowed) {
          setResult("denied", "DENIED", res.error || "Visitor not allowed");
          if (res.visitor) {
            fillDetails(res, false);
          }
          return;
        }
        const actionLabel = (res.action === "out") ? "CHECKED OUT" : "CHECKED IN";
        setResult("allowed", "ALLOWED", actionLabel + " — " + (res.message || ""));
        fillDetails(res, true);
      })
      .catch(function (err) {
        busy = false;
        setResult("denied", "ERROR", err.message);
      });
  }

  function fillDetails(res, ok) {
    details.classList.remove("d-none");
    document.getElementById("dVisitor").textContent = (res.visitor && res.visitor.names) || "—";
    document.getElementById("dRel").textContent = (res.visitor && res.visitor.relationship) || "—";
    document.getElementById("dStudent").textContent = (res.student && res.student.name) || "—";
    document.getElementById("dClass").textContent = (res.student && res.student.class) || "—";
    document.getElementById("dAction").textContent = ok
      ? ((res.action === "out" ? "OUT" : "IN") + (res.too_soon ? " (already in)" : ""))
      : "DENIED";
    document.getElementById("dTime").textContent = res.time_label || new Date().toLocaleString();
  }

  // Keep focus hint
  cardInput.focus();
});
</script>
