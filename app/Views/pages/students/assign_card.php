<!-- ==========================
  ASSIGN RFID CARD PAGE
=========================== -->
<div class="container-fluid mt-4">
  <div class="card shadow-sm border-0">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="card-title mb-0">
          <i class="bi bi-credit-card"></i> Assign RFID Card
        </h4>
      </div>

      <!-- Search box -->
      <div class="input-group mb-3">
        <span class="input-group-text bg-primary text-white">
          <i class="bi bi-search"></i>
        </span>
        <input type="text" id="searchStudent" class="form-control"
               placeholder="Search student by name, class or reg no...">
      </div>

      <!-- Student Table -->
      <div class="table-responsive">
        <table class="table table-hover align-middle" id="studentsTable">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Name</th>
              <th>Class</th>
              <th>Section</th>
              <th>Card</th>
              <th class="text-end">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php $i=1; foreach ($students as $s): ?>
              <tr>
                <td><?= $i++ ?></td>
                <td><?= esc($s['name']) ?></td>
                <td><?= esc($s['class']) ?></td>
                <td><?= esc($s['section']) ?></td>
                <td>
                  <?php if (!empty($s['card_number'])): ?>
                    <span class="badge bg-success"><?= esc($s['card_number']) ?></span>
                  <?php else: ?>
                    <span class="badge bg-secondary">NOT ASSIGNED</span>
                  <?php endif; ?>
                </td>
                <td class="text-end">
                  <button type="button"
                          class="btn btn-sm btn-danger assignBtn"
                          data-id="<?= $s['id'] ?>"
                          data-name="<?= esc($s['name']) ?>">
                    Assign
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

<!-- ==========================
  ASSIGN MODAL (NO OVERLAY)
=========================== -->
<div id="assignModalBox"
     class="d-none"
     style="
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        z-index: 9999;
        width: 400px;
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
     ">
  <div class="bg-danger text-white d-flex justify-content-between align-items-center p-2 rounded-top">
    <h6 class="mb-0">
      <i class="bi bi-wifi"></i> Assign Card to 
      <span id="studentName" class="fw-bold"></span>
    </h6>
    <button type="button" id="closeModal" class="btn btn-sm btn-close btn-close-white"></button>
  </div>

  <div class="p-3 text-center">
    <p class="mb-3">Place the RFID card on your USB reader...</p>
    <input type="hidden" id="studentId">
    <input type="text" id="cardInput"
           class="form-control text-center"
           placeholder="Waiting for card UID..." readonly>
    <div id="cardStatus" class="mt-3"></div>
  </div>

  <div class="p-2 border-top text-end">
    <button class="btn btn-outline-secondary btn-sm" id="closeBtn">
      <i class="bi bi-x-circle"></i> Close
    </button>
  </div>
</div>

<!-- SweetAlert -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
console.log("✅ Script loaded and ready");

document.addEventListener("DOMContentLoaded", () => {
  const modalBox = document.getElementById("assignModalBox");
  const cardInput = document.getElementById("cardInput");
  const closeModalBtn = document.getElementById("closeModal");
  const closeBtn = document.getElementById("closeBtn");
  const school_id = <?= json_encode(session('soma_school_id')) ?>;
  const operator  = <?= json_encode(session('soma_id')) ?>;

  // Assign button listeners
  document.querySelectorAll(".assignBtn").forEach(btn => {
    btn.addEventListener("click", () => {
      openAssignModal(btn.dataset.id, btn.dataset.name);
    });
  });

  function openAssignModal(studentId, studentName) {
    document.getElementById("studentId").value = studentId;
    document.getElementById("studentName").innerText = studentName;
    document.getElementById("cardStatus").innerHTML = "";
    cardInput.value = "";
    modalBox.classList.remove("d-none");
    cardInput.focus();
  }

  function closeAssignModal() {
    modalBox.classList.add("d-none");
  }

  closeModalBtn.addEventListener("click", closeAssignModal);
  closeBtn.addEventListener("click", closeAssignModal);

  // ===============================
  // 🧠 Universal UID Detection System
  // ===============================
  function normalizeUID(uid) {
    uid = uid.trim();
    if (!uid) return "";

    // Case 1: Decimal (only digits)
    if (/^\d+$/.test(uid)) {
      try {
        const num = BigInt(uid);
        uid = num.toString(16).toUpperCase();
        uid = uid.padStart(8, "0");
      } catch (e) {
        console.warn("⚠️ Decimal to Hex conversion failed:", e);
      }
    }

    // Case 2: Hexadecimal (letters & digits)
    uid = uid.replace(/[^A-Fa-f0-9]/g, '').toUpperCase();

    // Reverse byte order if even length (to match Android NFC)
    if (uid.length % 2 === 0) {
      const bytes = uid.match(/.{1,2}/g);
      bytes.reverse();
      uid = bytes.join('');
    }

    return uid.toUpperCase();
  }

  // RFID Reader (keyboard emulation)
  let buffer = "";
  document.addEventListener("keypress", e => {
    if (modalBox.classList.contains("d-none")) return;
    if (e.key === "Enter") {
      let uid = buffer.trim();
      if (uid.length >= 5) { // handle short decimal too
        const normalized = normalizeUID(uid);
        console.log("🔹 UID raw:", uid, "→ normalized:", normalized);
        cardInput.value = normalized;
        assignCard(normalized);
      }
      buffer = "";
    } else {
      buffer += e.key;
    }
  });

  // Assign card to server
  function assignCard(card) {
    const student_id = document.getElementById("studentId").value;
    const cardStatus = document.getElementById("cardStatus");
    cardStatus.innerHTML = `<div class="text-info">
      <i class="spinner-border spinner-border-sm"></i> Assigning card...
    </div>`;

    fetch("<?= base_url('api/assign_card') ?>", {
      method: "POST",
      headers: {"Content-Type": "application/x-www-form-urlencoded"},
      body: `card=${encodeURIComponent(card)}&student_id=${student_id}&school_id=${school_id}&operator=${operator}`
    })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        Swal.fire({
          icon: "success",
          title: "Card Assigned",
          text: res.success,
          timer: 2000,
          showConfirmButton: false
        });
        setTimeout(() => {
          closeAssignModal();
          location.reload();
        }, 1500);
      } else {
        Swal.fire({
          icon: "error",
          title: "Error",
          text: res.error || "Card assignment failed"
        });
        cardStatus.innerHTML = `<div class="text-danger mt-2">✗ ${res.error || 'Failed'}</div>`;
      }
    })
    .catch(err => {
      Swal.fire({
        icon: "error",
        title: "Network Error",
        text: err.message
      });
    });
  }

  // Search filter
  document.getElementById("searchStudent").addEventListener("keyup", function() {
    const term = this.value.toLowerCase();
    document.querySelectorAll("#studentsTable tbody tr").forEach(row => {
      row.style.display = row.textContent.toLowerCase().includes(term) ? "" : "none";
    });
  });
});
</script>
