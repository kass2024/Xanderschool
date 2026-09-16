<link rel="stylesheet" href="<?= base_url('assets/css/card-scan-ui.css') ?>">

<?php
$groups = $students_by_class ?? [];
$groups = array_filter($groups, static function ($className) {
    return stripos((string) $className, 'holiday') === false;
}, ARRAY_FILTER_USE_KEY);
$totalStudents = count($students ?? []);
$assignedCount = 0;
foreach ($students ?? [] as $s) {
    if (!empty($s['card_number'])) {
        $assignedCount++;
    }
}
?>

<div class="container-fluid mt-3 card-scan-page assign-card-page">
  <div class="card shadow-sm border-0">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
          <h4 class="card-title mb-1">
            <i class="bi bi-credit-card"></i> Assign RFID Card
          </h4>
          <small class="text-muted"><?= (int) $totalStudents ?> students · <?= (int) $assignedCount ?> cards assigned · <?= count($groups) ?> classes</small>
        </div>
        <small class="text-muted">Tap card on reader — same NFC format as Android app</small>
      </div>

      <div class="input-group mb-3 ac-search-wrap">
        <span class="input-group-text bg-primary text-white"><i class="fa fa-search"></i></span>
        <input type="text" id="searchStudent" class="form-control" autocomplete="off"
               placeholder="Search by name, class, or reg no (not case sensitive)...">
      </div>

      <div id="acClassList">
        <?php if (empty($groups)): ?>
          <div class="alert alert-info mb-0">No active students found for the current academic year.</div>
        <?php else: ?>
          <?php foreach ($groups as $className => $classStudents): ?>
            <?php
            if (stripos((string) $className, 'holiday') !== false) {
                continue;
            }
            $classAssigned = 0;
            foreach ($classStudents as $cs) {
                if (!empty($cs['card_number'])) {
                    $classAssigned++;
                }
            }
            ?>
            <div class="ac-class-block" data-class="<?= esc(strtolower($className)) ?>">
              <div class="ac-class-head" data-toggle-class>
                <div>
                  <h5><i class="fa fa-layer-group text-warning"></i> <?= esc($className) ?></h5>
                  <span class="meta"><?= count($classStudents) ?> students · <?= $classAssigned ?> cards assigned</span>
                </div>
                <i class="fa fa-chevron-down text-muted ac-chevron"></i>
              </div>
              <div class="ac-class-body">
                <div class="table-responsive">
                  <table class="table table-hover align-middle mb-0 ac-students-table">
                    <thead class="table-light">
                      <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Reg No.</th>
                        <th>Section</th>
                        <th>Card UID</th>
                        <th class="text-end">Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php $rowNum = 0; foreach ($classStudents as $s): ?>
                        <?php
                        $rowNum++;
                        $hasCard = !empty($s['card_number']);
                        $searchName = (string) ($s['name'] ?? '');
                        $searchRegno = (string) ($s['regno'] ?? '');
                        $searchSection = (string) ($s['section'] ?? '');
                        ?>
                        <tr data-id="<?= (int) $s['id'] ?>"
                            data-name="<?= esc($searchName) ?>"
                            data-regno="<?= esc($searchRegno) ?>"
                            data-section="<?= esc($searchSection) ?>"
                            data-class="<?= esc((string) $className) ?>">
                          <td><?= $rowNum ?></td>
                          <td class="fw-semibold"><?= esc($s['name']) ?></td>
                          <td><code class="small"><?= esc($s['regno'] ?? '') ?></code></td>
                          <td><?= esc($s['section'] ?? '') ?></td>
                          <td class="card-cell">
                            <?php if ($hasCard): ?>
                              <span class="badge bg-success card-scan-uid-badge"><?= esc($s['card_number']) ?></span>
                            <?php else: ?>
                              <span class="badge bg-secondary">NOT ASSIGNED</span>
                            <?php endif; ?>
                          </td>
                          <td class="text-end">
                            <div class="card-scan-actions">
                              <?php if ($hasCard): ?>
                                <button type="button" class="btn btn-warning btn-sm changeBtn"
                                        data-id="<?= (int) $s['id'] ?>"
                                        data-name="<?= esc($s['name']) ?>"
                                        data-card="<?= esc($s['card_number']) ?>">
                                  <i class="bi bi-arrow-repeat"></i> Change
                                </button>
                                <button type="button" class="btn btn-outline-danger btn-sm removeBtn"
                                        data-id="<?= (int) $s['id'] ?>"
                                        data-name="<?= esc($s['name']) ?>">
                                  <i class="bi bi-trash"></i> Remove
                                </button>
                              <?php else: ?>
                                <button type="button" class="btn btn-danger btn-sm assignBtn"
                                        data-id="<?= (int) $s['id'] ?>"
                                        data-name="<?= esc($s['name']) ?>">
                                  <i class="bi bi-wifi"></i> Assign
                                </button>
                              <?php endif; ?>
                            </div>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div id="assignModalBox" class="card-scan-modal-box d-none">
  <div class="card-scan-modal-head">
    <h6 class="mb-0">
      <i class="bi bi-wifi"></i> <span id="modalActionLabel">Assign card to</span>
      <span id="studentName" class="fw-bold"></span>
    </h6>
    <button type="button" id="closeModal" class="btn btn-sm btn-close btn-close-white"></button>
  </div>
  <div class="card-scan-modal-body">
    <p class="mb-2 text-muted" id="modalHint">Place the RFID card on your USB reader...</p>
    <input type="hidden" id="studentId">
    <input type="hidden" id="modalMode" value="assign">
    <?= view('pages/partials/card_scan_panel', [
      'input_id' => 'cardInput',
      'status_id' => 'cardStatus',
      'placeholder' => 'Tap your card...',
      'status_text' => 'Waiting for card...',
    ]) ?>
  </div>
  <div class="card-scan-modal-foot">
    <button class="btn btn-outline-secondary btn-sm" id="closeBtn"><i class="bi bi-x-circle"></i> Close</button>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= base_url('assets/js/card-uid.js') ?>"></script>
<script>
document.addEventListener("DOMContentLoaded", () => {
  const modalBox = document.getElementById("assignModalBox");
  const cardInput = document.getElementById("cardInput");
  const cardStatus = document.getElementById("cardStatus");
  const school_id = <?= json_encode(session('soma_school_id')) ?>;
  const operator = <?= json_encode(session('soma_id')) ?>;
  let buffer = "";

  document.querySelectorAll("[data-toggle-class]").forEach(head => {
    head.addEventListener("click", () => {
      const body = head.nextElementSibling;
      const open = body.style.display !== "none";
      body.style.display = open ? "none" : "";
      head.querySelector(".ac-chevron")?.classList.toggle("fa-rotate-180", !open);
    });
  });

  function setStatus(text, type) {
    cardStatus.textContent = text;
    cardStatus.className = "card-scan-status" + (type ? " " + type : "");
  }

  function normalizeUID(uid) {
    return (window.CardUid && CardUid.toStorage) ? CardUid.toStorage(uid) : String(uid || "").trim().toUpperCase();
  }

  function openModal(studentId, studentName, mode, currentCard) {
    document.getElementById("studentId").value = studentId;
    document.getElementById("studentName").textContent = studentName;
    document.getElementById("modalMode").value = mode;
    document.getElementById("modalActionLabel").textContent = mode === "change" ? "Change card for" : "Assign card to";
    document.getElementById("modalHint").textContent = mode === "change"
      ? (currentCard ? "Current: " + currentCard + " — tap the new card on the reader." : "Tap the new card on the reader.")
      : "Place the RFID card on your USB reader...";
    cardInput.value = "";
    setStatus("Waiting for card...", "");
    modalBox.classList.remove("d-none");
    cardInput.focus();
  }

  function closeModal() {
    modalBox.classList.add("d-none");
    buffer = "";
  }

  document.querySelectorAll(".assignBtn, .changeBtn").forEach(btn => {
    btn.addEventListener("click", () => {
      openModal(btn.dataset.id, btn.dataset.name, btn.classList.contains("changeBtn") ? "change" : "assign", btn.dataset.card || "");
    });
  });

  document.querySelectorAll(".removeBtn").forEach(btn => {
    btn.addEventListener("click", () => {
      Swal.fire({
        title: "Remove card?",
        text: "Unassign RFID card from " + btn.dataset.name + "?",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#dc3545",
        confirmButtonText: "Yes, remove"
      }).then(result => {
        if (!result.isConfirmed) return;
        fetch("<?= base_url('api/remove_student_card') ?>", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: `student_id=${encodeURIComponent(btn.dataset.id)}&school_id=${school_id}&operator=${operator}`
        })
          .then(r => r.json())
          .then(res => {
            if (res.success) {
              Swal.fire({ icon: "success", title: "Removed", text: res.success, timer: 1500, showConfirmButton: false });
              setTimeout(() => location.reload(), 1200);
            } else {
              Swal.fire({ icon: "error", title: "Error", text: res.error || "Could not remove card." });
            }
          });
      });
    });
  });

  document.getElementById("closeModal").addEventListener("click", closeModal);
  document.getElementById("closeBtn").addEventListener("click", closeModal);

  document.addEventListener("keypress", e => {
    if (modalBox.classList.contains("d-none")) return;
    if (e.key === "Enter") {
      const uid = buffer.trim();
      buffer = "";
      if (uid.length >= 4) {
        const normalized = normalizeUID(uid);
        cardInput.value = normalized;
        assignCard(normalized);
      }
    } else {
      buffer += e.key;
    }
  });

  function assignCard(card) {
    const student_id = document.getElementById("studentId").value;
    setStatus("Assigning card...", "busy");
    fetch("<?= base_url('api/assign_card') ?>", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: `card=${encodeURIComponent(card)}&student_id=${student_id}&school_id=${school_id}&operator=${operator}`
    })
      .then(r => r.json())
      .then(res => {
        if (res.success) {
          setStatus("✅ " + res.success, "ok");
          Swal.fire({ icon: "success", title: "Card Saved", text: res.success, timer: 1800, showConfirmButton: false });
          setTimeout(() => { closeModal(); location.reload(); }, 1400);
        } else {
          setStatus("❌ " + (res.error || "Failed"), "err");
          Swal.fire({ icon: "error", title: "Error", text: res.error || "Card assignment failed" });
        }
      })
      .catch(err => {
        setStatus("⚠️ " + err.message, "err");
        Swal.fire({ icon: "error", title: "Network Error", text: err.message });
      });
  }

  cardInput.addEventListener("focus", () => cardInput.blur());

  function foldSearch(value) {
    return String(value || "")
      .toLowerCase()
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .replace(/\s+/g, " ")
      .trim();
  }

  function searchTokens(value) {
    return foldSearch(value).split(/[^a-z0-9]+/).filter(Boolean);
  }

  function wordPrefixMatch(haystack, token) {
    return searchTokens(haystack).some(word => word.startsWith(token));
  }

  function rowMatchesSearch(row, tokens) {
    if (!tokens.length) return true;
    const name = row.getAttribute("data-name") || "";
    const regno = foldSearch(row.getAttribute("data-regno") || "").replace(/[^a-z0-9]/g, "");
    const section = row.getAttribute("data-section") || "";
    const klass = row.getAttribute("data-class") || "";
    return tokens.every(token => {
      const compact = token.replace(/[^a-z0-9]/g, "");
      return wordPrefixMatch(name, token)
        || wordPrefixMatch(section, token)
        || wordPrefixMatch(klass, token)
        || (compact !== "" && regno.startsWith(compact));
    });
  }

  function applyStudentSearch() {
    const input = document.getElementById("searchStudent");
    const tokens = searchTokens(input ? input.value : "");
    document.querySelectorAll(".ac-class-block").forEach(block => {
      let visible = 0;
      block.querySelectorAll("tbody tr").forEach(row => {
        const match = rowMatchesSearch(row, tokens);
        row.style.display = match ? "" : "none";
        if (match) visible++;
      });
      block.style.display = visible ? "" : "none";
      if (tokens.length && visible) {
        const body = block.querySelector(".ac-class-body");
        if (body) body.style.display = "";
        block.querySelector(".ac-chevron")?.classList.remove("fa-rotate-180");
      }
    });
  }

  const searchInput = document.getElementById("searchStudent");
  if (searchInput) {
    searchInput.addEventListener("input", applyStudentSearch);
    searchInput.addEventListener("keyup", applyStudentSearch);
  }
});
</script>
