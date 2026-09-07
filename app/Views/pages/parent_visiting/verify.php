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
    font-family: monospace;
    text-transform: uppercase;
  }
  .pv-card-badge {
    display: inline-block; margin-top: 8px; padding: 4px 12px; border-radius: 999px;
    background: rgba(0,0,0,.06); font-family: monospace; font-size: .95rem; letter-spacing: .06em;
  }
  .pv-visitor-photo {
    width: 96px; height: 96px; border-radius: 50%; object-fit: cover;
    border: 3px solid rgba(255,255,255,.85); box-shadow: 0 4px 14px rgba(0,0,0,.15);
    margin: 14px auto 0; display: block;
  }
  .pv-result.allowed .pv-visitor-photo { border-color: #28a745; }
  .pv-result.denied .pv-visitor-photo { border-color: #dc3545; }
</style>

<div class="container-fluid mt-4">
  <div class="card shadow-sm border-0 pv-scan-wrap">
    <div class="card-body">
      <h4 class="card-title mb-1">
        <i class="fa fa-id-card"></i> Verify Parent Visit
      </h4>
      <p class="text-muted mb-3">Visiting day verification — scan visitor smart card (same lookup as assign-card / attendance-card).</p>

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
          <tr><th style="width:40%">Card UID</th><td id="dCard"><code></code></td></tr>
          <tr><th>Allowed visitor(s)</th><td id="dVisitor"></td></tr>
          <tr><th>Relationship(s)</th><td id="dRel"></td></tr>
          <tr><th>Student(s) to visit</th><td id="dStudent"></td></tr>
          <tr><th>Class(es)</th><td id="dClass"></td></tr>
          <tr><th>Action</th><td id="dAction"></td></tr>
          <tr><th>Time</th><td id="dTime"></td></tr>
        </table>
      </div>
    </div>
  </div>
</div>

<script src="<?= base_url('assets/js/card-uid.js') ?>"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
  const school_id = <?= json_encode(session('soma_school_id')) ?>;
  const operator  = <?= json_encode(session('soma_id')) ?>;
  const cardInput = document.getElementById("verifyCardInput");
  const resultBox = document.getElementById("scanResult");
  const details = document.getElementById("scanDetails");
  let buffer = "";
  let busy = false;

  /** Same byte-reversal as assign-card (NFC compatible storage form). */
  function normalizeUID(uid) {
    return (window.CardUid && CardUid.toStorage) ? CardUid.toStorage(uid) : String(uid || "").trim().toUpperCase();
  }

  function setResult(state, title, meta, cardUid, photoUrl) {
    resultBox.className = "pv-result " + state;
    let html = '<div class="pv-status">' + title + '</div><div class="pv-meta">' + meta + '</div>';
    if (photoUrl) {
      html += '<img src="' + photoUrl + '" alt="Visitor" class="pv-visitor-photo">';
    }
    if (cardUid) {
      html += '<div class="pv-card-badge"><i class="fa fa-id-card"></i> ' + cardUid + '</div>';
    }
    resultBox.innerHTML = html;
  }

  document.addEventListener("keypress", function (e) {
    if (busy) return;
    const tag = (e.target && e.target.tagName) ? e.target.tagName.toLowerCase() : "";
    if ((tag === "input" || tag === "textarea") && e.target.id !== "verifyCardInput") {
      if (!e.target.readOnly) return;
    }
    if (e.key === "Enter") {
      const uid = buffer.trim();
      buffer = "";
      if (uid.length >= 4) {
        const normalized = normalizeUID(uid);
        cardInput.value = normalized;
        scanCard(normalized);
      }
    } else {
      buffer += e.key;
    }
  });

  function visitorPhotoUrl(res) {
    if (!res) return null;
    if (res.visitor && res.visitor.photo && res.visitor.photo_url) {
      return res.visitor.photo_url;
    }
    if (Array.isArray(res.visitors)) {
      for (const visitor of res.visitors) {
        if (visitor && visitor.photo && visitor.photo_url) {
          return visitor.photo_url;
        }
      }
    }
    return null;
  }

  function escapeHtml(value) {
    return String(value == null ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function renderList(items, formatter) {
    if (!Array.isArray(items) || !items.length) {
      return "—";
    }
    return "<ul class=\"mb-0 pl-3\">" + items.map(function (item) {
      return "<li>" + formatter(item) + "</li>";
    }).join("") + "</ul>";
  }

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
        const storedCard = (res.visitor && res.visitor.card) || res.card || card;
        const photoUrl = visitorPhotoUrl(res);
        if (!res.allowed) {
          setResult("denied", "DENIED", res.error || "Visitor not allowed", storedCard, photoUrl);
          fillDetails(res, false, storedCard);
          return;
        }
        const actionLabel = (res.action === "out") ? "CHECKED OUT" : (res.action === "mixed" ? "UPDATED" : "CHECKED IN");
        setResult("allowed", "ALLOWED", actionLabel + " — " + (res.message || ""), storedCard, photoUrl);
        fillDetails(res, true, storedCard);
      })
      .catch(function (err) {
        busy = false;
        setResult("denied", "ERROR", err.message);
      });
  }

  function fillDetails(res, ok, cardUid) {
    const visitors = Array.isArray(res.visitors) && res.visitors.length
      ? res.visitors
      : ((res.visitor && (res.visitor.names || res.visitor.relationship)) ? [res.visitor] : []);
    const students = Array.isArray(res.students) && res.students.length
      ? res.students
      : ((res.student && (res.student.name || res.student.class)) ? [res.student] : []);

    details.classList.remove("d-none");
    document.getElementById("dCard").innerHTML = '<code>' + (cardUid || "—") + '</code>';
    document.getElementById("dVisitor").innerHTML = renderList(visitors, function (visitor) {
      return escapeHtml((visitor && visitor.names) || "—");
    });
    document.getElementById("dRel").innerHTML = renderList(visitors, function (visitor) {
      return escapeHtml((visitor && visitor.relationship) || "—");
    });
    document.getElementById("dStudent").innerHTML = renderList(students, function (student) {
      const regno = (student && student.regno) ? " <code>(" + escapeHtml(student.regno) + ")</code>" : "";
      return escapeHtml((student && student.name) || "—") + regno;
    });
    document.getElementById("dClass").innerHTML = renderList(students, function (student) {
      return escapeHtml((student && student.class) || "—");
    });
    document.getElementById("dAction").textContent = ok
      ? ((res.action === "out" ? "OUT" : (res.action === "mixed" ? "UPDATED" : "IN")) + (res.too_soon ? " (some already in)" : ""))
      : "DENIED";
    document.getElementById("dTime").textContent = res.time_label || new Date().toLocaleString();
  }

  cardInput.focus();
});
</script>
