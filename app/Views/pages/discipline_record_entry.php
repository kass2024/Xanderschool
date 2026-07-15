<div class="app-inner-layout app-inner-layout-page">
  <div class="app-inner-layout__wrapper" style="display:block;padding-left:20px">

    <style>
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
          <option value="student" selected>🔍 Search by Student</option>
          <option value="class">🏫 Search by Class</option>
          <option value="card">💳 Scan by Card</option>
        </select>

        <div id="search_student_dv">
          <input type="text" id="student_search_input" class="form-control" placeholder="Type student name or reg no...">
          <div id="student_search_box"></div>
        </div>

        <div id="search_class_dv" style="display:none;">
          <select class="form-control select2" id="search_class">
            <option selected disabled><?= lang("app.selectClass");?></option>
            <?php foreach ($classes as $class): ?>
              <option value="<?= $class['id']; ?>">
                <?= "{$class['level_name']} {$class['title']} {$class['code']}" ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div id="search_card_dv" style="display:none;">
          <img id="cardIcon" src="<?= base_url('assets/images/rfid.png'); ?>" alt="RFID">
          <input type="text" id="cardInput" class="form-control mt-2" placeholder="Tap your card..." readonly>
          <div id="cardScanStatus">Waiting for card...</div>
          <div id="successAlert">✅ Discipline saved! Ready for next card...</div>
        </div>

      </div>
    </div>

    <!-- ================== TABLE + FORM ================== -->
    <div style="margin-top:15px;width:100%;float:left;">
      <form id="disciplineForm" method="POST" action="<?= base_url('manipulate_discipline_entry'); ?>">

        <div class="col-md-6 col-sm-12 pull-left" style="margin-bottom:15px">
          <div style="background:white;padding:10px;max-height:500px;overflow:auto;border-radius:8px;">
            <table class="table table-hover table-fixed">
              <thead>
                <tr>
                  <th><?= lang("app.regNo");?>.</th>
                  <th><?= lang("app.studentName");?></th>
                  <th><?= lang("app.sClass");?></th>
                  <th><?= lang("app.remaining");?></th>
                  <th><?= lang("app.remove");?></th>
                </tr>
              </thead>
              <tbody id="disciplineTable"></tbody>
            </table>
            <label><strong><?= lang("app.legend");?>: </strong>
              <span class="badge badge-primary" style="background-color: orangered !important;"></span>
              <?= lang("app.halfoftotalmax");?><br>
              <span class="badge badge-dark" style="margin-left:70px;"></span>
              <?= lang("app.studenthasnormal");?>
            </label>
          </div>
        </div>

        <div class="col-md-5 col-sm-12 pull-left">
          <div style="background:white;padding:10px;border-radius:8px;">

            <div class="row mb-3">
              <div class="col-md-3"><label><?= lang("app.activeTerm");?></label></div>
              <div class="col-md-9">
                <input type="text" class="form-control" readonly
                       value="<?= \App\Controllers\Home::TermToStr($activeTerm['term']); ?>">
                <input type="hidden" name="active_term" value="<?= $activeTerm['id']; ?>">
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-md-3"><label><?= lang("app.type");?></label></div>
              <div class="col-md-9">
                <select class="form-control select2" id="choose_disc_type" name="discipline_type" style="width:100px;">
                  <option value="1" selected><?= lang("app.reduceDiscMarks");?></option>
                  <option value="0"><?= lang("app.behaviorComments");?></option>
                </select>
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-md-3"><label><?= lang("app.discReason");?></label></div>
              <div class="col-md-9">
                <textarea class="form-control" name="reason" required minlength="5" placeholder="Enter reason"></textarea>
              </div>
            </div>

            <div class="row mb-3" id="reduce_marks">
              <div class="col-md-3"><label><?= lang("app.reducedMarks");?></label></div>
              <div class="col-md-9">
                <input type="number" name="reduce_marks" class="form-control" style="width:80px;">
              </div>
            </div>

            <div class="row mb-3" id="send_sms">
              <div class="col-md-9 offset-md-3">
                <input type="checkbox" name="sms" value="1" id="notify_parent">
                <label for="notify_parent"><?= lang("app.notify");?></label>
              </div>
            </div>

            <div class="text-center">
              <button type="submit" class="btn btn-success btn-lg" style="width:50%;font-size:14px;">
                <i class="fa fa-check"></i> <?= lang("app.save");?>
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
$(function(){

  console.log("✅ Discipline form loaded and ready.");

  // ===== Mode Switch =====
  $("#search_mode").on("change", function(){
    const mode = $(this).val();
    console.log("🔄 Switched mode to:", mode);
    $("#search_student_dv,#search_class_dv,#search_card_dv").hide();
    $("#disciplineTable").empty();
    $("#successAlert").hide();
    $("#cardScanStatus").text("Waiting for card...").css("color","#6c757d");
    if(mode==="student") $("#search_student_dv").show();
    if(mode==="class") $("#search_class_dv").show();
    if(mode==="card") $("#search_card_dv").show();
  });

  // ===== Live Search =====
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
      error: function(xhr, s, e){ console.error("❌ Search error:", e); }
    });
  });

  // ===== Select Student =====
  $(document).on("click", ".student-item", function(){
    const id = $(this).data("id");
    $("#student_search_input").val(""); $("#student_search_box").hide();
    $.get("<?= base_url();?>get_student/" + id, function(data){
      $("#disciplineTable").append(data);
      ensureDiscIdsInsideForm();
    });
  });

  // ===== Ensure discId[] inside form =====
  function ensureDiscIdsInsideForm(){
    $("#disciplineTable tr").each(function(){
      const exists = $(this).find("input[name='discId[]']").length;
      if(!exists){
        const id = $(this).find("td:first").text().trim();
        $(this).append(`<input type="hidden" name="discId[]" value="${id}">`);
      }
    });
  }

  // ===== Class Search =====
  $("#search_class").on('select2:select', e => {
    const cls = e.params.data;
    $.get("<?= base_url();?>get_student/" + cls.id + "/1", function(data){
      $("#disciplineTable").html(data);
      ensureDiscIdsInsideForm();
    });
  });

  // ===== UID Normalizer =====
  function normalizeUID(uid, reverse = true) {
    uid = uid.trim();
    if (!uid) return "";
    if (/^\d+$/.test(uid)) {
      uid = BigInt(uid).toString(16).toUpperCase();
      uid = uid.padStart(uid.length % 2 ? uid.length + 1 : uid.length, "0");
    }
    uid = uid.replace(/[^A-Fa-f0-9]/g, '').toUpperCase();
    if (reverse && uid.length % 2 === 0) {
      uid = uid.match(/.{1,2}/g).reverse().join('');
    }
    return uid;
  }

// ===== Card Scan =====
let buffer = "";
document.addEventListener("keypress", e => {
  if ($("#search_mode").val() !== "card") return;
  const active = document.activeElement.id;
  if (["discReason"].includes(active)) return;
  if (e.key === "Enter") {
    const uid = buffer.trim(); 
    buffer = "";

    if(uid.length >= 4) {
      // ✅ Convert decimal card number to HEX before sending
      const normalized = normalizeUID(uid);
      console.log("💳 Raw UID:", uid, "→ HEX:", normalized);
      handleCardScan(normalized);
    }
  } else {
    buffer += e.key;
  }
});

// ===== HEX Conversion Function =====
function normalizeUID(uid) {
  uid = uid.trim();
  if (!uid) return "";

  // Convert decimal to HEX
  if (/^\d+$/.test(uid)) {
    try {
      const num = BigInt(uid);
      uid = num.toString(16).toUpperCase();
      uid = uid.padStart(8, "0");
    } catch (e) {
      console.warn("⚠️ Decimal to Hex conversion failed:", e);
    }
  }

  // Clean up and uppercase
  uid = uid.replace(/[^A-Fa-f0-9]/g, '').toUpperCase();

  // ✅ Reverse byte order to match assign-card logic
  if (uid.length % 2 === 0) {
    const bytes = uid.match(/.{1,2}/g);
    bytes.reverse();
    uid = bytes.join('');
  }

  return uid.toUpperCase();
}


  function handleCardScan(uid){
    $("#cardScanStatus").text("⏳ Checking card...").css("color","#6c757d");
    fetch("<?= base_url('api/discipline_card_scan'); ?>", {
      method:"POST",
      headers:{"Content-Type":"application/x-www-form-urlencoded"},
      body:`card=${encodeURIComponent(uid)}&school_id=<?= session('soma_school_id');?>`
    })
    .then(r=>r.json())
    .then(res=>{
      if(res.error){
        $("#cardScanStatus").text("❌ "+res.error+` (${uid})`).css("color","red");
      } else if(res.student){
        $("#cardScanStatus").text("✅ "+res.student.name+" added").css("color","green");
        $.get("<?= base_url();?>get_student/"+res.student.id, function(data){
          $("#disciplineTable").append(data);
          ensureDiscIdsInsideForm();
        });
      }
    })
    .catch(err=>{
      $("#cardScanStatus").text("⚠️ "+err.message).css("color","red");
    });
  }

  // ===== Discipline Type Toggle =====
  $("#choose_disc_type").on("change", function(){
    const val = $(this).val();
    if(val == 0){ $("#send_sms, #reduce_marks").hide(); }
    else { $("#send_sms, #reduce_marks").show(); }
  });

  // ===== Submit Form =====
  $("#disciplineForm").on("submit", function(e){
    e.preventDefault();
    ensureDiscIdsInsideForm();
    if($("#disciplineTable tr").length === 0){
      Swal.fire({icon:'error',title:'Error',text:'Please add at least one student before saving.'});
      return;
    }
    const payload = $(this).serialize();
    $.ajax({
      url: $(this).attr("action"),
      type: "POST",
      data: payload,
      success: function(res){
        try{ res = JSON.parse(res); }catch{}
        if(res.success || res.status==='ok'){
          Swal.fire({icon:'success',title:'Saved!',text:'Discipline record saved successfully.'});
          $("#successAlert").fadeIn().delay(2000).fadeOut();
          resetDisciplineForm();
        }else{
          Swal.fire({icon:'error',title:'Error',text:res.error||"Save failed!"});
        }
      },
      error: function(xhr, status, err){
        Swal.fire({icon:'error',title:'Server Error',text:err});
      }
    });
  });

  function resetDisciplineForm(){
    $("#disciplineTable").empty();
    $("#notify_parent").prop("checked", false);
    $("#cardScanStatus").text("Ready for next card...").css("color","#6c757d");
  }

  $("#cardInput").on("focus", ()=>$("#cardInput").blur());
});
</script>
