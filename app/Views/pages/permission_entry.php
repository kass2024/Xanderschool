<div class="app-inner-layout app-inner-layout-page card-scan-page">
  <div class="app-inner-layout__wrapper" style="display:block;padding-left:20px">

    <style>.vl { border-left:3px solid #3ac47d; }</style>

    <!-- ================== SEARCH AREA ================== -->
    <div class="pull-left" style="width:100%">
      <div class="col-md-6 col-sm-12 col-lg-4 pull-left">
        <?= view('pages/partials/card_scan_search', ['classes' => $classes, 'use_lang' => true]) ?>
      </div>
    </div>

    <!-- ================== TABLE + FORM ================== -->
    <div class="card-scan-workspace">
      <form id="permissionForm" method="POST" action="<?= base_url('manipulate_permissions'); ?>" class="validate" novalidate>

        <div class="col-md-6 col-sm-12 pull-left">
          <div class="card-scan-table-wrap">
            <table class="table table-hover table-fixed mb-0">
              <thead>
                <tr>
                  <th><?= lang("app.regNo"); ?>.</th>
                  <th><?= lang("app.studentName"); ?></th>
                  <th><?= lang("app.sClass"); ?></th>
                  <th><?= lang("app.remove"); ?></th>
                </tr>
              </thead>
              <tbody id="disciplineTable"></tbody>
            </table>
            <label class="mt-2 mb-0"><strong><?= lang("app.legend"); ?>: </strong>
              <span class="badge badge-primary" style="background-color:orangered!important;"></span>
              <?= lang("app.justification"); ?>
            </label>
          </div>
        </div>

        <div class="col-md-5 col-sm-12 pull-left">
          <div class="card-scan-form-wrap">

            <div class="row mb-3">
              <div class="col-md-3"><label><?= lang("app.activeTerm"); ?></label></div>
              <div class="col-md-9">
                <input type="text" class="form-control" readonly value="<?= \App\Controllers\Home::TermToStr($activeTerm['term']); ?>">
                <input type="hidden" name="active_term" value="<?= $activeTerm['id']; ?>">
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-md-3"><label><?= lang("app.leaveRutern"); ?></label></div>
              <div class="col-md-9">
                <input type="text" class="form-control" placeholder="Leave & return time" required name="datetimes">
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-md-3"><label><?= lang("app.destination"); ?></label></div>
              <div class="col-md-9">
                <input type="text" class="form-control" minlength="3" required placeholder="<?= lang("app.destination"); ?>" name="destination">
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-md-3"><label><?= lang("app.reason"); ?></label></div>
              <div class="col-md-9">
                <textarea class="form-control" name="reason" required minlength="5"></textarea>
              </div>
            </div>

            <div class="row mb-3" id="send_sms">
              <div class="col-md-9 offset-md-3">
                <?php
                if ($remaining_sms == 0) {
                    echo "<label class='text-danger'>" . lang("app.sendSMS") . "</label><br>";
                } elseif ($remaining_sms < 10) {
                    echo "<label class='text-warning'>" . lang("app.remainSMS") . " <span class='badge badge-pill badge-warning'>{$remaining_sms}</span></label><br>";
                }
                ?>
                <input type="checkbox" name="sms" value="1" id="notify_parent">
                <label for="notify_parent"><?= lang("app.notify"); ?></label>
              </div>
            </div>

            <div class="text-center">
              <button type="submit" class="btn btn-success btn-lg btn-save-main">
                <i class="fa fa-check"></i> <?= lang("app.save"); ?>
              </button>
            </div>

          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= base_url('assets/js/card-uid.js') ?>"></script>
<script>
$(function () {

  function setScanStatus(text, type) {
    const $s = $("#cardScanStatus");
    $s.text(text).removeClass("ok err busy");
    if (type) $s.addClass(type);
  }

  $("#search_mode").on("change", function () {
    $("#successAlert").hide();
    setScanStatus("Waiting for card...", "");
  });

  $("#student_search_input").on("keyup", function () {
    const term = $(this).val().trim();
    if (term.length < 2) { $("#student_search_box").hide().empty(); return; }
    $.ajax({
      url: "<?= base_url('search_student'); ?>",
      type: "POST",
      dataType: "json",
      data: { searchTerm: term },
      success: function (data) {
        let html = "";
        if (!data.length) {
          html = "<div class='text-muted text-center p-2'>No students found</div>";
        } else {
          data.forEach(st => {
            html += `<div class='card-scan-student-item student-item' data-id='${st.id}'>${st.text}</div>`;
          });
        }
        $("#student_search_box").html(html).show();
      }
    });
  });

  $(document).on("click", ".student-item", function () {
    const id = $(this).data("id");
    $("#student_search_input").val("");
    $("#student_search_box").hide();
    checkUnjustifiedPermissions(id);
  });

  function checkUnjustifiedPermissions(studentId) {
    $.get(`<?= base_url('api/check_permission/'); ?>${studentId}`, function (res) {
      if (res.error === "0" || res.length === 0) return appendStudent(studentId);
      let html = `<table class="table table-bordered table-sm"><thead><tr>
        <th>Destination</th><th>Reason</th><th>Leave</th><th>Return</th>
      </tr></thead><tbody>`;
      res.forEach(r => {
        html += `<tr><td>${r.destination}</td><td>${r.reason}</td><td>${r.leave_time}</td><td>${r.return_time}</td></tr>`;
      });
      html += `</tbody></table>`;
      Swal.fire({
        title: `⚠️ ${res.length} Unjustified Permission(s) Found`,
        html: html + "<p class='mt-2'>You must justify them before issuing a new permission.</p>",
        icon: 'warning',
        confirmButtonText: 'Justify Now',
        showCancelButton: true,
        cancelButtonText: 'Cancel'
      }).then(result => {
        if (result.isConfirmed) justifyNextPermission(res, studentId);
      });
    }).fail(() => Swal.fire({ icon: 'error', title: 'Error', text: 'Unable to check permissions.' }));
  }

  function justifyNextPermission(list, studentId) {
    if (list.length === 0) {
      Swal.fire({ icon: 'success', title: 'All justified!' });
      return appendStudent(studentId);
    }
    const p = list.shift();
    Swal.fire({
      title: `Justify permission #${p.permission_id}`,
      html: `<p><b>Destination:</b> ${p.destination}</p><p><b>Reason:</b> ${p.reason}</p>
        <textarea id="justComment" class="form-control" placeholder="Enter justification..."></textarea>`,
      confirmButtonText: 'Save Justification',
      preConfirm: () => {
        const c = $("#justComment").val().trim();
        if (c.length < 3) { Swal.showValidationMessage('Please provide a justification'); return false; }
        return c;
      }
    }).then(result => {
      if (!result.isConfirmed) return;
      $.post("<?= base_url('api/save_justification'); ?>", {
        permission_id: p.permission_id,
        comment: result.value,
        operator: "<?= session('id'); ?>"
      }, function (r) {
        if (r.success) {
          Swal.fire({ icon: 'success', title: 'Saved', timer: 1000, showConfirmButton: false });
          justifyNextPermission(list, studentId);
        } else {
          Swal.fire({ icon: 'error', title: 'Error', text: r.error || 'Failed to save justification.' });
        }
      }, 'json');
    });
  }

  function appendStudent(id) {
    if ($(`input[value='${id}']`).length) {
      Swal.fire({ icon: 'info', title: 'Duplicate', text: 'Student already added!' });
      return;
    }
    $.get("<?= base_url(); ?>get_student/" + id, function (data) {
      const $row = $(data);
      const cleanRow = `<tr>
        <td>${$row.find("td:nth-child(1)").text().trim()}</td>
        <td>${$row.find("td:nth-child(2)").text().trim()}</td>
        <td>${$row.find("td:nth-child(3)").text().trim()}</td>
        <td><button type="button" class="btn btn-danger btn-sm remove-student">Remove</button>
          <input type="hidden" name="discId[]" value="${id}"></td>
      </tr>`;
      $("#disciplineTable").append(cleanRow);
    });
  }

  $(document).on("click", ".remove-student, #removerow", function () {
    $(this).closest('tr').fadeOut(200, function () { $(this).remove(); });
  });

  $("#search_class").on('select2:select', e => {
    $.get("<?= base_url(); ?>get_student/" + e.params.data.id + "/1?from=permission", function (data) {
      $("#disciplineTable").html(data);
    });
  });

  let buffer = "";
  document.addEventListener("keypress", e => {
    if ($("#search_mode").val() !== "card") return;
    if (["reason", "destination"].includes(document.activeElement.id)) return;
    if (e.key === "Enter") {
      const uid = buffer.trim();
      buffer = "";
      if (uid.length >= 4) handleCardScan((window.CardUid && CardUid.forScan) ? CardUid.forScan(uid) : uid.replace(/[^A-Fa-f0-9]/g, '').toUpperCase());
    } else buffer += e.key;
  });

  function handleCardScan(uid) {
    setScanStatus("⏳ Checking card...", "busy");
    fetch("<?= base_url('api/permission_card_scan'); ?>", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: `card=${encodeURIComponent(uid)}&school_id=<?= session('soma_school_id'); ?>`
    })
      .then(r => r.json())
      .then(res => {
        if (res.error) setScanStatus("❌ " + res.error, "err");
        else if (res.student) {
          setScanStatus("✅ " + res.student.name + " loaded", "ok");
          checkUnjustifiedPermissions(res.student.id);
        }
      })
      .catch(err => setScanStatus("⚠️ " + err.message, "err"));
  }

  $("#cardInput").on("focus", () => $("#cardInput").blur());

  let saving = false;
  $(document).on('submit', '#permissionForm', function (e) {
    e.preventDefault();
    if (saving) return;
    saving = true;
    const form = this;
    Swal.fire({ title: 'Saving permission...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    fetch($(form).attr('action'), { method: 'POST', body: new FormData(form) })
      .then(res => res.json())
      .then(data => {
        Swal.close();
        saving = false;
        if (data.error) { Swal.fire({ icon: 'error', title: 'Failed', text: data.error }); return; }
        if (data.permission_id) {
          const w = window.open("<?= base_url('pages/reports/print_permission/'); ?>" + data.permission_id + '?autoprint=1', '_blank');
          if (w) w.focus();
        }
        Swal.fire({ icon: 'success', title: 'Permission saved', timer: 1200, showConfirmButton: false });
        form.reset();
        $("#disciplineTable").empty();
      })
      .catch(err => { Swal.close(); saving = false; Swal.fire({ icon: 'error', title: 'System Error', text: err.message }); });
  });
});
</script>
