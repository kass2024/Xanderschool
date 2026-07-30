<!-- Parent Visiting — Assign visitors -->
<div class="container-fluid mt-4">
  <div class="card shadow-sm border-0">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
        <h4 class="card-title mb-0">
          <i class="fa fa-users"></i> Assign Parent Visitors
        </h4>
        <small class="text-muted">Each student should have at least <strong>2</strong> allowed visitors.</small>
      </div>

      <div class="input-group mb-3">
        <span class="input-group-text bg-primary text-white"><i class="fa fa-search"></i></span>
        <input type="text" id="searchStudent" class="form-control"
               placeholder="Search student by name, class or reg no...">
      </div>

      <div class="table-responsive">
        <table class="table table-hover align-middle" id="studentsTable">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Name</th>
              <th>Reg No</th>
              <th>Class</th>
              <th>Visitors</th>
              <th class="text-end">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php $i = 1; foreach ($students as $s):
              $vc = (int) ($s['visitor_count'] ?? 0);
              $ready = $vc >= 2;
            ?>
              <tr data-id="<?= (int) $s['id'] ?>">
                <td><?= $i++ ?></td>
                <td><?= esc($s['name']) ?></td>
                <td><?= esc($s['regno'] ?? '') ?></td>
                <td><?= esc($s['class']) ?></td>
                <td class="visitor-count-cell">
                  <?php if ($ready): ?>
                    <span class="badge bg-success"><?= $vc ?> ready</span>
                  <?php else: ?>
                    <span class="badge bg-warning text-dark"><?= $vc ?> / 2</span>
                  <?php endif; ?>
                </td>
                <td class="text-end">
                  <button type="button"
                          class="btn btn-sm btn-primary manageBtn"
                          data-id="<?= (int) $s['id'] ?>"
                          data-name="<?= esc($s['name']) ?>"
                          data-class="<?= esc($s['class']) ?>">
                    Manage visitors
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Visitors panel -->
<div id="visitorsPanel"
     class="d-none"
     style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:9998;width:640px;max-width:96vw;max-height:90vh;overflow:auto;background:#fff;border-radius:10px;box-shadow:0 4px 16px rgba(0,0,0,.35);">
  <div class="bg-primary text-white d-flex justify-content-between align-items-center p-2 rounded-top">
    <h6 class="mb-0">
      Visitors for <span id="panelStudentName" class="fw-bold"></span>
      <small id="panelStudentClass" class="d-block opacity-75"></small>
    </h6>
    <button type="button" id="closePanel" class="btn btn-sm btn-light">&times;</button>
  </div>
  <div class="p-3">
    <div id="visitorAlert" class="alert alert-warning py-2 d-none"></div>
    <div id="visitorsList"></div>
    <hr>
    <h6>Add visitor</h6>
    <form id="addVisitorForm" class="row g-2">
      <input type="hidden" id="panelStudentId" name="student_id" value="">
      <div class="col-md-5">
        <input type="text" class="form-control" id="vNames" name="names" placeholder="Full names *" required>
      </div>
      <div class="col-md-3">
        <input type="text" class="form-control" id="vPhone" name="phone" placeholder="Phone">
      </div>
      <div class="col-md-3">
        <input type="text" class="form-control" id="vRel" name="relationship" placeholder="Relationship">
      </div>
      <div class="col-md-1">
        <button type="submit" class="btn btn-success w-100" title="Save"><i class="fa fa-plus"></i></button>
      </div>
    </form>
  </div>
</div>

<!-- Assign card modal -->
<div id="assignModalBox"
     class="d-none"
     style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:9999;width:400px;max-width:95vw;background:#fff;border-radius:10px;box-shadow:0 4px 12px rgba(0,0,0,.3);">
  <div class="bg-danger text-white d-flex justify-content-between align-items-center p-2 rounded-top">
    <h6 class="mb-0">
      <i class="fa fa-wifi"></i> Assign Card to
      <span id="visitorCardName" class="fw-bold"></span>
    </h6>
    <button type="button" id="closeCardModal" class="btn btn-sm btn-light">&times;</button>
  </div>
  <div class="p-3 text-center">
    <p class="mb-3">Place the RFID card on your USB reader...</p>
    <input type="hidden" id="visitorId">
    <input type="text" id="cardInput" class="form-control text-center" placeholder="Waiting for card UID..." readonly>
    <div id="cardStatus" class="mt-3"></div>
  </div>
  <div class="p-2 border-top text-end">
    <button class="btn btn-outline-secondary btn-sm" id="closeCardBtn">Close</button>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
  const school_id = <?= json_encode(session('soma_school_id')) ?>;
  const operator  = <?= json_encode(session('soma_id')) ?>;
  const panel = document.getElementById("visitorsPanel");
  const modalBox = document.getElementById("assignModalBox");
  const cardInput = document.getElementById("cardInput");
  let cardBuffer = "";

  document.getElementById("searchStudent").addEventListener("keyup", function () {
    const term = this.value.toLowerCase();
    document.querySelectorAll("#studentsTable tbody tr").forEach(function (row) {
      row.style.display = row.textContent.toLowerCase().includes(term) ? "" : "none";
    });
  });

  document.querySelectorAll(".manageBtn").forEach(function (btn) {
    btn.addEventListener("click", function () {
      openPanel(btn.dataset.id, btn.dataset.name, btn.dataset.class);
    });
  });

  document.getElementById("closePanel").addEventListener("click", function () {
    panel.classList.add("d-none");
  });

  function openPanel(studentId, name, className) {
    document.getElementById("panelStudentId").value = studentId;
    document.getElementById("panelStudentName").innerText = name;
    document.getElementById("panelStudentClass").innerText = className || "";
    document.getElementById("addVisitorForm").reset();
    document.getElementById("panelStudentId").value = studentId;
    panel.classList.remove("d-none");
    loadVisitors(studentId);
  }

  function loadVisitors(studentId) {
    const list = document.getElementById("visitorsList");
    const alertBox = document.getElementById("visitorAlert");
    list.innerHTML = '<div class="text-muted">Loading...</div>';

    fetch("<?= base_url('parent_visiting/student_visitors') ?>/" + studentId)
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res.success) {
          list.innerHTML = '<div class="text-danger">' + (res.error || "Failed") + "</div>";
          return;
        }
        const visitors = res.visitors || [];
        const count = visitors.length;
        if (count < 2) {
          alertBox.classList.remove("d-none");
          alertBox.innerText = "This student has fewer than 2 visitors (" + count + "/2). Please add more.";
        } else {
          alertBox.classList.add("d-none");
        }
        updateRowBadge(studentId, count);

        if (!visitors.length) {
          list.innerHTML = '<div class="text-muted">No visitors yet.</div>';
          return;
        }

        let html = '<table class="table table-sm"><thead><tr><th>Names</th><th>Phone</th><th>Relation</th><th>Card</th><th></th></tr></thead><tbody>';
        visitors.forEach(function (v) {
          const cardBadge = v.card
            ? '<span class="badge bg-success">' + escapeHtml(v.card) + '</span>'
            : '<span class="badge bg-secondary">NOT ASSIGNED</span>';
          html += '<tr>' +
            '<td>' + escapeHtml(v.names) + '</td>' +
            '<td>' + escapeHtml(v.phone || '') + '</td>' +
            '<td>' + escapeHtml(v.relationship || '') + '</td>' +
            '<td>' + cardBadge + '</td>' +
            '<td class="text-nowrap text-end">' +
              '<button type="button" class="btn btn-xs btn-danger assignVisitorCardBtn" data-id="' + v.id + '" data-name="' + escapeAttr(v.names) + '">Assign Card</button> ' +
              '<button type="button" class="btn btn-xs btn-outline-danger deleteVisitorBtn" data-id="' + v.id + '">Remove</button>' +
            '</td></tr>';
        });
        html += '</tbody></table>';
        list.innerHTML = html;

        list.querySelectorAll(".assignVisitorCardBtn").forEach(function (b) {
          b.addEventListener("click", function () {
            openAssignModal(b.dataset.id, b.dataset.name);
          });
        });
        list.querySelectorAll(".deleteVisitorBtn").forEach(function (b) {
          b.addEventListener("click", function () {
            deleteVisitor(b.dataset.id, studentId);
          });
        });
      })
      .catch(function (err) {
        list.innerHTML = '<div class="text-danger">' + err.message + '</div>';
      });
  }

  function updateRowBadge(studentId, count) {
    const row = document.querySelector('#studentsTable tr[data-id="' + studentId + '"] .visitor-count-cell');
    if (!row) return;
    if (count >= 2) {
      row.innerHTML = '<span class="badge bg-success">' + count + ' ready</span>';
    } else {
      row.innerHTML = '<span class="badge bg-warning text-dark">' + count + ' / 2</span>';
    }
  }

  document.getElementById("addVisitorForm").addEventListener("submit", function (e) {
    e.preventDefault();
    const studentId = document.getElementById("panelStudentId").value;
    const names = document.getElementById("vNames").value.trim();
    if (!names) {
      Swal.fire({ icon: "error", title: "Names required" });
      return;
    }
    const body = new URLSearchParams({
      student_id: studentId,
      names: names,
      phone: document.getElementById("vPhone").value.trim(),
      relationship: document.getElementById("vRel").value.trim()
    });
    fetch("<?= base_url('parent_visiting/save_visitor') ?>", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: body.toString()
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res.success) {
          if (res.warning) {
            Swal.fire({ icon: "warning", title: "Saved", text: res.warning });
          } else {
            Swal.fire({ icon: "success", title: "Visitor saved", timer: 1400, showConfirmButton: false });
          }
          document.getElementById("addVisitorForm").reset();
          document.getElementById("panelStudentId").value = studentId;
          loadVisitors(studentId);
        } else {
          Swal.fire({ icon: "error", title: "Error", text: res.error || "Failed" });
        }
      });
  });

  function deleteVisitor(id, studentId) {
    Swal.fire({
      title: "Remove visitor?",
      text: "Visitor will be deactivated.",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Remove"
    }).then(function (result) {
      if (!result.isConfirmed) return;
      fetch("<?= base_url('parent_visiting/delete_visitor') ?>", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "visitor_id=" + encodeURIComponent(id)
      })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (res.success) {
            loadVisitors(studentId);
          } else {
            Swal.fire({ icon: "error", title: "Error", text: res.error || "Failed" });
          }
        });
    });
  }

  function openAssignModal(visitorId, visitorName) {
    document.getElementById("visitorId").value = visitorId;
    document.getElementById("visitorCardName").innerText = visitorName;
    document.getElementById("cardStatus").innerHTML = "";
    cardInput.value = "";
    cardBuffer = "";
    modalBox.classList.remove("d-none");
    cardInput.focus();
  }

  function closeAssignModal() {
    modalBox.classList.add("d-none");
  }
  document.getElementById("closeCardModal").addEventListener("click", closeAssignModal);
  document.getElementById("closeCardBtn").addEventListener("click", closeAssignModal);

  function normalizeUID(uid) {
    uid = uid.trim();
    if (!uid) return "";
    if (/^\d+$/.test(uid)) {
      try {
        const num = BigInt(uid);
        uid = num.toString(16).toUpperCase();
        uid = uid.padStart(8, "0");
      } catch (e) {
        console.warn("Decimal to Hex conversion failed:", e);
      }
    }
    uid = uid.replace(/[^A-Fa-f0-9]/g, "").toUpperCase();
    if (uid.length % 2 === 0) {
      const bytes = uid.match(/.{1,2}/g);
      bytes.reverse();
      uid = bytes.join("");
    }
    return uid.toUpperCase();
  }

  document.addEventListener("keypress", function (e) {
    if (modalBox.classList.contains("d-none")) return;
    if (e.key === "Enter") {
      let uid = cardBuffer.trim();
      if (uid.length >= 5) {
        const normalized = normalizeUID(uid);
        cardInput.value = normalized;
        assignCard(normalized);
      }
      cardBuffer = "";
    } else {
      cardBuffer += e.key;
    }
  });

  function assignCard(card) {
    const visitor_id = document.getElementById("visitorId").value;
    const cardStatus = document.getElementById("cardStatus");
    cardStatus.innerHTML = '<div class="text-info"><i class="spinner-border spinner-border-sm"></i> Assigning card...</div>';

    fetch("<?= base_url('parent_visiting/assign_card') ?>", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: "card=" + encodeURIComponent(card) +
            "&visitor_id=" + encodeURIComponent(visitor_id) +
            "&school_id=" + encodeURIComponent(school_id) +
            "&operator=" + encodeURIComponent(operator)
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res.success) {
          Swal.fire({ icon: "success", title: "Card Assigned", text: res.success, timer: 1800, showConfirmButton: false });
          setTimeout(function () {
            closeAssignModal();
            loadVisitors(document.getElementById("panelStudentId").value);
          }, 1200);
        } else {
          Swal.fire({ icon: "error", title: "Error", text: res.error || "Card assignment failed" });
          cardStatus.innerHTML = '<div class="text-danger mt-2">✗ ' + (res.error || "Failed") + "</div>";
        }
      })
      .catch(function (err) {
        Swal.fire({ icon: "error", title: "Network Error", text: err.message });
      });
  }

  function escapeHtml(str) {
    return String(str == null ? "" : str)
      .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }
  function escapeAttr(str) {
    return escapeHtml(str).replace(/'/g, "&#39;");
  }
});
</script>
