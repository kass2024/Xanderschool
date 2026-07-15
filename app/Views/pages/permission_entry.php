<div class="app-inner-layout app-inner-layout-page">
  <div class="app-inner-layout__wrapper" style="display:block;padding-left:20px">

    <style>
      .vl { border-left:3px solid #3ac47d; }

      /* ------------------- CARD SCAN STYLES ------------------- */
      #search_card_dv {
        text-align:center;
        background:#f8f9fa;
        border-radius:8px;
        padding:20px;
        border:1px dashed #ccc;
        margin-top:10px;
      }
      #cardIcon {
        width:100%;
        max-width:280px;
        height:auto;
        display:block;
        margin:0 auto 10px auto;
        opacity:0.95;
        animation:pulse 1.5s infinite;
      }
      @keyframes pulse {
        0% {transform:scale(1);opacity:.9;}
        50% {transform:scale(1.05);opacity:1;}
        100% {transform:scale(1);opacity:.9;}
      }
      #cardScanStatus { font-size:13px;color:#6c757d;margin-top:5px;transition:all .3s ease; }
      #cardInput { background:#fff;border:1px solid #ced4da;text-align:center;font-weight:600; }
      #successAlert {
        display:none;margin-top:10px;background:#d4edda;border:1px solid #c3e6cb;
        color:#155724;border-radius:6px;padding:8px 12px;font-size:14px;
      }
      #student_search_box {
        background:#fff;border:1px solid #ced4da;border-radius:6px;
        padding:6px;margin-top:5px;max-height:230px;overflow-y:auto;display:none;
      }
      .student-item {display:flex;align-items:center;padding:4px 6px;border-bottom:1px solid #f1f1f1;cursor:pointer;}
      .student-item:hover {background:#f8f9fa;}
      .student-item input {margin-right:8px;}
    </style>

    <!-- ================== SEARCH AREA ================== -->
    <div class="pull-left" style="width:100%">
      <div class="col-md-6 col-sm-12 col-lg-4 pull-left">

        <select id="search_mode" class="form-control mb-2">
          <option value="student" selected>🔍 <?= lang("searchByStudent");?></option>
          <option value="class">🏫 <?= lang("searchByClass");?></option>
          <option value="card">💳 <?= lang("scanByCard");?></option>
        </select>

        <div id="search_student_dv">
          <input type="text" id="student_search_input" class="form-control" placeholder="Type student name or reg no...">
          <div id="student_search_box"></div>
        </div>

        <div id="search_class_dv" style="display:none;">
          <select class="form-control select2" id="search_class">
            <option selected disabled><?= lang("selectClass");?></option>
            <?php foreach ($classes as $class): ?>
              <option value="<?= $class['id']; ?>">
                <?= "{$class['level_name']} {$class['title']} {$class['code']}" ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div id="search_card_dv" style="display:none;">
          <img id="cardIcon" src="<?= base_url('assets/images/rfid.png'); ?>" alt="RFID Reader">
          <input type="text" id="cardInput" class="form-control mt-2" placeholder="Tap your card..." readonly>
          <div id="cardScanStatus">Waiting for card...</div>
          <div id="successAlert">✅ Student loaded! Ready for next card...</div>
        </div>

      </div>
    </div>

    <!-- ================== TABLE + FORM ================== -->
    <div style="margin-top:15px;width:100%;float:left;">
      <form id="permissionForm" method="POST" action="<?= base_url('manipulate_permissions'); ?>" class="validate" novalidate>


        <div class="col-md-6 col-sm-12 pull-left" style="margin-bottom:15px">
          <div style="background:white;padding:10px;max-height:500px;overflow:auto;border-radius:8px;">
            <table class="table table-hover table-fixed">
              <thead>
                <tr>
                  <th><?= lang("regNo");?>.</th>
                  <th><?= lang("studentName");?></th>
                  <th><?= lang("sClass");?></th>
                  
                </tr>
              </thead>
              <tbody id="disciplineTable"></tbody>
            </table>
            <label><strong><?= lang("legend");?>: </strong>
              <span class="badge badge-primary" style="background-color:orangered!important;"></span>
              <?= lang("justification");?>
            </label>
          </div>
        </div>

        <div class="col-md-5 col-sm-12 pull-left">
          <div style="background:white;padding:10px;border-radius:8px;">

            <div class="row mb-3">
              <div class="col-md-3"><label><?= lang("activeTerm");?></label></div>
              <div class="col-md-9">
                <input type="text" class="form-control" readonly value="<?= \App\Controllers\Home::TermToStr($activeTerm['term']); ?>">
                <input type="hidden" name="active_term" value="<?= $activeTerm['id']; ?>">
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-md-3"><label><?= lang("leaveRutern");?></label></div>
              <div class="col-md-9">
                <input type="text" class="form-control" placeholder="Leave & return time" required name="datetimes">
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-md-3"><label><?= lang("destination");?></label></div>
              <div class="col-md-9">
                <input type="text" class="form-control" minlength="3" required placeholder="<?= lang("destination");?>" name="destination">
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-md-3"><label><?= lang("discReason");?></label></div>
              <div class="col-md-9">
                <textarea class="form-control" name="reason" required minlength="5"></textarea>
              </div>
            </div>

            <div class="row mb-3" id="send_sms">
              <div class="col-md-9 offset-md-3">
                <?php
                if ($remaining_sms == 0) {
                  echo "<label class='text-danger'>".lang("sendSMS")."</label><br>";
                } elseif ($remaining_sms < 10) {
                  echo "<label class='text-warning'>".lang("remainSMS")." <span class='badge badge-pill badge-warning'>{$remaining_sms}</span></label><br>";
                }
                ?>
                <input type="checkbox" name="sms" value="1" id="notify_parent">
                <label for="notify_parent"><?= lang("notify");?></label>
              </div>
            </div>

            <div class="text-center">
              <button type="submit" class="btn btn-success btn-lg" style="width:50%;font-size:14px;">
                <i class="fa fa-check"></i> <?= lang("save");?>
              </button>
            </div>

          </div>
        </div>
      </form>
    </div>
  </div>
</div>
<!-- ================= JAVASCRIPT ================= -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(function() {

  console.log("✅ Permission Management page ready.");

  // ================== MODE SWITCH ==================
  $("#search_mode").on("change", function(){
    const mode = $(this).val();
    $("#search_student_dv,#search_class_dv,#search_card_dv").hide();
    $("#successAlert").hide();
    $("#cardScanStatus").text("Waiting for card...").css("color","#6c757d");
    if(mode==="student") $("#search_student_dv").show();
    if(mode==="class") $("#search_class_dv").show();
    if(mode==="card") $("#search_card_dv").show();
  });

  // ================== LIVE SEARCH ==================
  $("#student_search_input").on("keyup", function(){
    const term = $(this).val().trim();
    if(term.length < 2){ $("#student_search_box").hide().empty(); return; }

    $.ajax({
      url: "<?= base_url('search_student'); ?>",
      type: "POST",
      dataType: "json",
      data: { searchTerm: term },
      success: function(data){
        let html = "";
        if(!data.length){
          html = "<div class='text-muted text-center p-2'>No students found</div>";
        } else {
          data.forEach(st => {
            html += `<div class='student-item' data-id='${st.id}'>${st.text}</div>`;
          });
        }
        $("#student_search_box").html(html).show();
      },
      error: function(){ console.error("❌ Search request failed."); }
    });
  });

  // ================== SELECT STUDENT ==================
  $(document).on("click", ".student-item", function(){
    const id = $(this).data("id");
    $("#student_search_input").val(""); 
    $("#student_search_box").hide();
    checkUnjustifiedPermissions(id);
  });

  // ================== CHECK FOR UNJUSTIFIED PERMISSIONS ==================
  function checkUnjustifiedPermissions(studentId){
    $.get(`<?= base_url('api/check_permission/'); ?>${studentId}`, function(res){
      if (res.error === "0" || res.length === 0) {
        // no unjustified → allow adding
        return appendStudent(studentId);
      }

      // build table
      let html = `
        <table class="table table-bordered table-sm">
          <thead>
            <tr>
              <th>Destination</th>
              <th>Reason</th>
              <th>Leave</th>
              <th>Return</th>
            </tr>
          </thead><tbody>
      `;
      res.forEach(r=>{
        html += `<tr>
          <td>${r.destination}</td>
          <td>${r.reason}</td>
          <td>${r.leave_time}</td>
          <td>${r.return_time}</td>
        </tr>`;
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

    }).fail(() => Swal.fire({ icon:'error', title:'Error', text:'Unable to check permissions.' }));
  }

  // ================== JUSTIFICATION FLOW ==================
  function justifyNextPermission(list, studentId){
    if (list.length === 0){
      Swal.fire({ icon:'success', title:'All justified!' });
      return appendStudent(studentId);
    }

    const p = list.shift(); // get first
    Swal.fire({
      title: `Justify permission #${p.permission_id}`,
      html: `
        <p><b>Destination:</b> ${p.destination}</p>
        <p><b>Reason:</b> ${p.reason}</p>
        <textarea id="justComment" class="form-control" placeholder="Enter justification..."></textarea>
      `,
      confirmButtonText: 'Save Justification',
      preConfirm: () => {
        const c = $("#justComment").val().trim();
        if(c.length < 3) {
          Swal.showValidationMessage('Please provide a justification');
          return false;
        }
        return c;
      }
    }).then(result => {
      if (result.isConfirmed){
        $.post("<?= base_url('api/save_justification'); ?>", {
          permission_id: p.permission_id,
          comment: result.value,
          operator: "<?= session('id'); ?>"
        }, function(r){
          if(r.success){
            Swal.fire({ icon:'success', title:'Saved', timer:1000, showConfirmButton:false });
            justifyNextPermission(list, studentId);
          } else {
            Swal.fire({ icon:'error', title:'Error', text:r.error || 'Failed to save justification.' });
          }
        }, 'json').fail(()=>{
          Swal.fire({ icon:'error', title:'Error', text:'Request failed while saving justification.' });
        });
      }
    });
  }

  // ================== APPEND STUDENT ==================
  function appendStudent(id) {
    if ($(`input[value='${id}']`).length) {
      Swal.fire({ icon: 'info', title: 'Duplicate', text: 'Student already added!' });
      return;
    }

    $.get("<?= base_url(); ?>get_student/" + id, function (data) {
      const $row = $(data);
      const regNo = $row.find("td:nth-child(1)").text().trim();
      const name = $row.find("td:nth-child(2)").text().trim();
      const className = $row.find("td:nth-child(3)").text().trim();

      const cleanRow = `
        <tr>
          <td>${regNo}</td>
          <td>${name}</td>
          <td>${className}</td>
          <td>
            <button type="button" class="btn btn-danger remove-student">Remove</button>
            <input type="hidden" name="discId[]" value="${id}">
          </td>
        </tr>
      `;
      $("#disciplineTable").append(cleanRow);
    });
  }

  // ================== REMOVE STUDENT ROW ==================
  $(document).on("click", ".remove-student, #removerow", function(){
    $(this).closest('tr').fadeOut(200, function(){ $(this).remove(); });
  });

  // ================== CLASS SEARCH ==================
  $("#search_class").on('select2:select', e => {
    const cls = e.params.data;
    $.get("<?= base_url();?>get_student/" + cls.id + "/1?from=permission", function(data){
      $("#disciplineTable").html(data);
    });
  });

  // ================== CARD SCAN ==================
  let buffer = "";
  document.addEventListener("keypress", e => {
    if ($("#search_mode").val() !== "card") return;
    if (["reason","destination"].includes(document.activeElement.id)) return;

    if (e.key === "Enter") {
      const uid = buffer.trim();
      buffer = "";
      if(uid.length >= 4) {
        const normalized = normalizeUID(uid);
        handleCardScan(normalized);
      }
    } else buffer += e.key;
  });

  function normalizeUID(uid){
    uid = uid.trim();
    if(!uid) return "";
    if(/^\d+$/.test(uid)){
      const num = BigInt(uid);
      uid = num.toString(16).toUpperCase().padStart(8,"0");
    }
    uid = uid.replace(/[^A-Fa-f0-9]/g,'').toUpperCase();
    if(uid.length % 2 === 0){
      const bytes = uid.match(/.{1,2}/g).reverse();
      uid = bytes.join('');
    }
    return uid.toUpperCase();
  }

  function handleCardScan(uid){
    $("#cardScanStatus").text("⏳ Checking card...").css("color","#6c757d");
    fetch("<?= base_url('api/permission_card_scan'); ?>", {
      method:"POST",
      headers:{"Content-Type":"application/x-www-form-urlencoded"},
      body:`card=${encodeURIComponent(uid)}&school_id=<?= session('soma_school_id');?>`
    })
    .then(r=>r.json())
    .then(res=>{
      if(res.error){
        $("#cardScanStatus").text("❌ "+res.error).css("color","red");
      } else if(res.student){
        $("#cardScanStatus").text("✅ "+res.student.name+" loaded").css("color","green");
        checkUnjustifiedPermissions(res.student.id);
      }
    })
    .catch(err=>{
      $("#cardScanStatus").text("⚠️ "+err.message).css("color","red");
    });
  }

  $("#cardInput").on("focus", ()=>$("#cardInput").blur());

  // ================== FORM SUBMIT ==================
  let saving = false;
  $(document).on('submit', '#permissionForm', function (e) {
    e.preventDefault();
    if(saving) return; // prevent duplicate submit
    saving = true;

    const form = this;
    const formData = new FormData(form);

    Swal.fire({
      title: 'Saving permission...',
      text: 'Please wait a moment.',
      allowOutsideClick: false,
      didOpen: () => Swal.showLoading()
    });

    fetch($(form).attr('action'), {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      Swal.close();
      saving = false;

      if (data.error) {
        Swal.fire({ icon: 'error', title: 'Failed', text: data.error });
        return;
      }

      Swal.fire({
        icon: 'success',
        title: 'Permission Saved!',
        text: 'Printing will start automatically...',
        timer: 1500,
        showConfirmButton: false
      });

      if (data.permission_id) {
        const printUrl = "<?= base_url('pages/reports/print_permission/'); ?>" + data.permission_id;
        setTimeout(() => {
          const printWin = window.open(printUrl, '_blank');
          if (printWin) printWin.focus();
        }, 1200);
      }

      form.reset();
      $("#disciplineTable").empty();
    })
    .catch(err => {
      Swal.close();
      saving = false;
      Swal.fire({ icon: 'error', title: 'System Error', text: err.message });
    });
  });

});
</script>
