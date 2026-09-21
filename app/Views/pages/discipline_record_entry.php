<?php
if (!function_exists('disc_t')) {
	function disc_t(string $en, string $rw): string
	{
		return \App\Models\DisciplineCodeModel::discLang() === 'rw' ? $rw : $en;
	}
}
$discLang = $disc_lang ?? \App\Models\DisciplineCodeModel::discLang();
$discGroups = $discipline_code_groups ?? [];
?>
<div class="app-inner-layout app-inner-layout-page card-scan-page disc-entry-page">
  <div class="disc-entry-inner">

    <div class="disc-lang-bar">
      <?= view('pages/partials/disc_lang_switcher', ['disc_lang' => $discLang]); ?>
    </div>

    <div class="disc-entry-search">
      <?= view('pages/partials/card_scan_search', ['classes' => $classes, 'use_lang' => false]) ?>
    </div>

    <div id="discSaveFlash" class="disc-entry-flash" role="alert" style="display:none">
      <i class="fa fa-check-circle"></i> <?= disc_t('Record saved — ready for next entry', 'Byabitswe — tegura undi munyeshuri'); ?>
    </div>

    <form id="disciplineForm" method="POST" action="<?= base_url('manipulate_discipline_entry'); ?>">

      <div class="disc-entry-grid">

        <div class="disc-entry-panel disc-entry-students">
          <h5 class="disc-entry-panel-title">
            <i class="fa fa-users"></i> <?= disc_t('Student\'s name', 'Amazina y\'umunyeshuri'); ?>
          </h5>
          <div class="disc-entry-table-wrap">
            <table class="table table-hover table-fixed mb-0">
              <thead>
                <tr>
                  <th><?= disc_t('Photo', 'Ifoto'); ?></th>
                  <th><?= disc_t('Reg. no.', 'Nomero'); ?></th>
                  <th><?= disc_t('Student\'s name', 'Amazina'); ?></th>
                  <th><?= disc_t('Class', 'Icyiciro'); ?></th>
                  <th><?= disc_t('Remaining', 'Asigaye'); ?></th>
                  <th><?= disc_t('Remove', 'Kura'); ?></th>
                </tr>
              </thead>
              <tbody id="disciplineTable">
                <tr id="discEmptyRow" class="disc-empty-row">
                  <td colspan="6">
                    <i class="fa fa-inbox"></i>
                    <?= disc_t('No students added yet — search, scan a card, or select a class above.', 'Nta munyeshuri wongereye — shakisha, sikanisha ikariti, cyangwa hitamo icyiciro.'); ?>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="disc-entry-legend">
            <strong><?= disc_t('Legend', 'Ibisobanuro'); ?>:</strong>
            <span class="disc-entry-legend-item">
              <span class="disc-entry-legend-dot disc-entry-legend-dot--warn"></span>
              <?= disc_t('Student has gone under half of total max', 'Umunyeshuri ageze munsi y\'igice cy\'amanota yose'); ?>
            </span>
            <span class="disc-entry-legend-item">
              <span class="disc-entry-legend-dot disc-entry-legend-dot--normal"></span>
              <?= disc_t('Student has normal marks', 'Umunyeshuri afite amanota asanzwe'); ?>
            </span>
          </div>
        </div>

        <div class="disc-entry-panel disc-entry-form">
          <h5 class="disc-entry-panel-title">
            <i class="fa fa-gavel"></i> <?= disc_t('Conduct code', 'Itegeko ry\'imyitwarire'); ?>
          </h5>

          <div class="disc-form-group">
            <label><?= disc_t('Active term', 'Igihembwe gikora'); ?></label>
            <input type="text" class="form-control" readonly
                   value="<?= \App\Controllers\Home::TermToStr($activeTerm['term']); ?>">
            <input type="hidden" name="active_term" id="disc_active_term" value="<?= $activeTerm['id']; ?>">
          </div>

          <div class="disc-form-group">
            <label for="choose_disc_type"><?= disc_t('Type', 'Ubwoko'); ?></label>
            <select class="form-control select2" id="choose_disc_type" name="discipline_type">
              <option value="1" selected><?= disc_t('Reduce discipline marks', 'Gugabanya amanota y\'imyitwarire'); ?></option>
              <option value="0"><?= disc_t('Behaviour comments', 'Ibitekerezo ku myitwarire'); ?></option>
            </select>
          </div>

          <div class="disc-form-group">
            <label for="disc_code_id"><?= disc_t('School conduct code', 'Amabwiriza y\'ishuri'); ?></label>
            <select class="form-control" id="disc_code_id" name="code_id" required>
              <option value=""><?= disc_t('Select a conduct code…', 'Hitamo itegeko…'); ?></option>
              <?php foreach ($discGroups as $g) :
                $gLabel = $discLang === 'rw'
                  ? ((string) ($g['rw'] ?? '') !== '' ? $g['rw'] : ($g['en'] ?? ''))
                  : ((string) ($g['en'] ?? '') !== '' ? $g['en'] : ($g['rw'] ?? ''));
              ?>
                <optgroup label="<?= esc($gLabel); ?>">
                  <?php foreach (($g['items'] ?? []) as $item) :
                    $title = \App\Models\DisciplineCodeModel::titleFor($item, $discLang);
                    $s1 = $discLang === 'rw' ? (string) ($item['first_sanction_rw'] ?? '') : (string) ($item['first_sanction_en'] ?? '');
                    $s2 = $discLang === 'rw' ? (string) ($item['second_sanction_rw'] ?? '') : (string) ($item['second_sanction_en'] ?? '');
                    $s3 = $discLang === 'rw' ? (string) ($item['third_sanction_rw'] ?? '') : (string) ($item['third_sanction_en'] ?? '');
                  ?>
                    <option value="<?= (int) $item['id']; ?>"
                            data-m1="<?= (int) $item['first_marks']; ?>"
                            data-m2="<?= (int) $item['second_marks']; ?>"
                            data-m3="<?= (int) $item['third_marks']; ?>"
                            data-s1="<?= esc($s1, 'attr'); ?>"
                            data-s2="<?= esc($s2, 'attr'); ?>"
                            data-s3="<?= esc($s3, 'attr'); ?>"
                            data-title="<?= esc($title, 'attr'); ?>">
                      <?= (int) $item['code_no']; ?>. <?= esc($title); ?>
                    </option>
                  <?php endforeach; ?>
                </optgroup>
              <?php endforeach; ?>
            </select>
            <p class="disc-code-hint"><?= disc_t('Reason and marks are taken from this law. You cannot type them.', 'Impamvu n\'amanota biva ku itegeko. Ntushobora kubyandika.'); ?></p>
          </div>

          <div class="disc-occur-box" id="discOccurBox" hidden>
            <div class="disc-occur-schedule" id="discOccurSchedule"></div>
            <div class="disc-occur-applied" id="discOccurApplied"></div>
          </div>

          <div class="disc-form-group" id="reduce_marks">
            <label><?= disc_t('Marks to deduct', 'Amanota agabanywa'); ?></label>
            <input type="text" id="reduce_marks_display" class="form-control disc-marks-input" readonly
                   placeholder="<?= disc_t('Automatic', 'Bikora ubwabyo'); ?>">
          </div>

          <div class="disc-form-group disc-form-check" id="send_sms">
            <input type="checkbox" name="sms" value="1" id="notify_parent">
            <label for="notify_parent"><?= disc_t('Notify parent by SMS', 'Menyesha umubyeyi kuri SMS'); ?></label>
          </div>

          <div class="disc-form-actions">
            <button type="submit" class="btn btn-success btn-lg btn-save-main" id="discSaveBtn">
              <i class="fa fa-check"></i> <?= disc_t('Save', 'Bika'); ?>
            </button>
          </div>
        </div>

      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= base_url('assets/js/card-uid.js') ?>"></script>
<script>
$(function () {

  const $table = $("#disciplineTable");
  const $emptyRow = $("#discEmptyRow");
  const $saveBtn = $("#discSaveBtn");
  const saveBtnHtml = $saveBtn.html();
  const isRw = <?= $discLang === 'rw' ? 'true' : 'false'; ?>;
  let saving = false;

  function t(en, rw) { return isRw ? rw : en; }

  function setScanStatus(text, type) {
    const $s = $("#cardScanStatus");
    $s.text(text).removeClass("ok err busy");
    if (type) $s.addClass(type);
  }

  function updateEmptyState() {
    const hasStudents = $table.find("tr.disc_row").length > 0;
    $emptyRow.toggle(!hasStudents);
  }

  function studentAlreadyAdded(id) {
    return $(`#disciplineTable input[name='discId[]'][value='${id}']`).length > 0;
  }

  function appendStudent(id) {
    if (studentAlreadyAdded(id)) {
      Swal.fire({ icon: 'info', title: t('Duplicate', 'Yongeye'), text: t('Student already added!', 'Uyu munyeshuri yamaze kongerwa!') });
      return;
    }
    $.get("<?= base_url(); ?>get_student/" + id, function (data) {
      $table.append(data);
      updateEmptyState();
      refreshOccurrence();
    });
  }

  function ensureDiscIdsInsideForm() {
    $table.find("tr.disc_row").each(function () {
      if (!$(this).find("input[name='discId[]']").length) {
        const id = $(this).attr("id");
        if (id) $(this).append(`<input type="hidden" name="discId[]" value="${id}">`);
      }
    });
  }

  function showSaveFlash() {
    $("#discSaveFlash").stop(true, true).fadeIn(200).delay(3500).fadeOut(400);
  }

  function selectedCodeOption() {
    return $("#disc_code_id").find("option:selected");
  }

  function renderSchedule() {
    const $opt = selectedCodeOption();
    const id = parseInt($opt.val(), 10) || 0;
    const $box = $("#discOccurBox");
    if (!id) {
      $box.attr("hidden", true);
      $("#reduce_marks_display").val("");
      return;
    }
    const m1 = $opt.data("m1") || 0, m2 = $opt.data("m2") || 0, m3 = $opt.data("m3") || 0;
    const s1 = $opt.data("s1") || "", s2 = $opt.data("s2") || "", s3 = $opt.data("s3") || "";
    $("#discOccurSchedule").html(
      `<strong>${t("Deduction schedule", "Amanota agabanywa")}</strong>` +
      `<div class="disc-occur-pills">` +
        `<span>${t("1st time", "Bwa mbere")}: <b>${m1}</b>${s1 ? " · " + s1 : ""}</span>` +
        `<span>${t("2nd time", "Bwa kabiri")}: <b>${m2}</b>${s2 ? " · " + s2 : ""}</span>` +
        `<span>${t("3rd time", "Bwa gatatu")}: <b>${m3}</b>${s3 ? " · " + s3 : ""}</span>` +
      `</div>`
    );
    $box.removeAttr("hidden");
  }

  function refreshOccurrence() {
    renderSchedule();
    const codeId = parseInt($("#disc_code_id").val(), 10) || 0;
    ensureDiscIdsInsideForm();
    const ids = [];
    $table.find("input[name='discId[]']").each(function () {
      const v = parseInt($(this).val(), 10);
      if (v) ids.push(v);
    });
    if (!codeId || !ids.length) {
      $("#discOccurApplied").empty();
      const $opt = selectedCodeOption();
      if (codeId) $("#reduce_marks_display").val($opt.data("m1") || 0);
      return;
    }
    $.post("<?= base_url('discipline_code_preview'); ?>", {
      code_id: codeId,
      active_term: $("#disc_active_term").val(),
      discId: ids
    }).done(function (res) {
      if (!res || !res.students) return;
      let html = `<strong>${t("For selected students", "Ku banyeshuri bahiswemo")}</strong><ul>`;
      const names = {};
      $table.find("tr.disc_row").each(function () {
        const id = $(this).find("input[name='discId[]']").val() || $(this).attr("id");
        names[id] = $(this).find("td").eq(2).text().trim() || ("#" + id);
      });
      let firstMarks = null;
      res.students.forEach(function (s) {
        if (firstMarks === null) firstMarks = s.marks;
        html += `<li>${names[s.id] || ("#" + s.id)} — <b>${s.label}</b> → ${s.marks} ${t("marks", "amanota")}${s.sanction ? " · " + s.sanction : ""}</li>`;
      });
      html += "</ul>";
      $("#discOccurApplied").html(html);
      if (firstMarks !== null) $("#reduce_marks_display").val(firstMarks);
    });
  }

  function resetDisciplineForm() {
    $table.find("tr.disc_row").remove();
    updateEmptyState();
    $("#disc_code_id").val("").trigger("change");
    $("#notify_parent").prop("checked", false);
    $("#student_search_input").val("");
    $("#student_search_box").hide().empty();
    $("#choose_disc_type").val("1").trigger("change");
    if ($("#search_class").data("select2")) {
      $("#search_class").val(null).trigger("change");
    }
    $("#send_sms, #reduce_marks").show();
    $("#discOccurBox").attr("hidden", true);
    $("#reduce_marks_display").val("");
    setScanStatus(t("Ready for next card...", "Tegura indi kariti..."), "");
    $("#successAlert").hide();
  }

  $("#search_mode").on("change", function () {
    $table.find("tr.disc_row").remove();
    updateEmptyState();
    $("#discSaveFlash").hide();
    $("#successAlert").hide();
    setScanStatus(t("Waiting for card...", "Tegereza ikariti..."), "");
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
        if (!data.length) html = "<div class='text-muted text-center p-2'>" + t("No students found", "Nta munyeshuri wabonetse") + "</div>";
        else data.forEach(st => { html += `<div class='card-scan-student-item student-item' data-id='${st.id}'>${st.text}</div>`; });
        $("#student_search_box").html(html).show();
      }
    });
  });

  $(document).on("click", ".student-item", function () {
    const id = $(this).data("id");
    $("#student_search_input").val("");
    $("#student_search_box").hide();
    appendStudent(id);
  });

  $(document).on("click", "#removerow", function () {
    $(this).closest("tr").fadeOut(200, function () {
      $(this).remove();
      updateEmptyState();
      refreshOccurrence();
    });
  });

  $("#search_class").on("select2:select", function (e) {
    $.get("<?= base_url(); ?>get_student/" + e.params.data.id + "/1", function (data) {
      $table.find("tr.disc_row").remove();
      $table.append(data);
      ensureDiscIdsInsideForm();
      updateEmptyState();
      refreshOccurrence();
    });
  });

  let buffer = "";
  document.addEventListener("keypress", function (e) {
    if ($("#search_mode").val() !== "card") return;
    const el = document.activeElement;
    if (el && (
      el.tagName === "INPUT" ||
      el.tagName === "TEXTAREA" ||
      el.tagName === "SELECT" ||
      (el.className && String(el.className).indexOf("select2-search") !== -1)
    )) return;
    if (e.key === "Enter") {
      const uid = buffer.trim();
      buffer = "";
      if (uid.length >= 4) {
        const normalized = (window.CardUid && CardUid.forScan) ? CardUid.forScan(uid) : uid.replace(/[^A-Fa-f0-9]/g, '').toUpperCase();
        handleCardScan(normalized);
      }
    } else {
      buffer += e.key;
    }
  });

  function handleCardScan(uid) {
    setScanStatus(t("⏳ Checking card...", "⏳ Ikariti irasuzumwa..."), "busy");
    fetch("<?= base_url('api/discipline_card_scan'); ?>", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: `card=${encodeURIComponent(uid)}&school_id=<?= session('soma_school_id'); ?>`
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res.error) setScanStatus("❌ " + res.error, "err");
        else if (res.student) {
          setScanStatus("✅ " + res.student.name + " " + t("added", "yongerewe"), "ok");
          appendStudent(res.student.id);
        }
      })
      .catch(function (err) { setScanStatus("⚠️ " + err.message, "err"); });
  }

  $("#choose_disc_type").on("change", function () {
    const val = $(this).val();
    if (val == 0) $("#send_sms, #reduce_marks").hide();
    else $("#send_sms, #reduce_marks").show();
  });

  $("#disc_code_id").on("change", refreshOccurrence);

  $("#disciplineForm").on("submit", function (e) {
    e.preventDefault();
    if (saving) return;
    ensureDiscIdsInsideForm();
    if ($table.find("tr.disc_row").length === 0) {
      Swal.fire({ icon: 'error', title: t('Error', 'Ikosa'), text: t('Please add at least one student before saving.', 'Ongeramo umunyeshuri mbere yo kubika.') });
      return;
    }
    if (!parseInt($("#disc_code_id").val(), 10)) {
      Swal.fire({ icon: 'error', title: t('Error', 'Ikosa'), text: t('Select a conduct code from the school discipline law.', 'Hitamo itegeko mu mabwiriza y\'ishuri.') });
      return;
    }
    saving = true;
    $saveBtn.prop("disabled", true).html('<i class="fa fa-spinner fa-spin"></i> ' + t('Saving...', 'Birabikwa...'));
    $.ajax({
      url: $(this).attr("action"),
      type: "POST",
      dataType: "json",
      data: $(this).serialize(),
      success: function (res) {
        saving = false;
        $saveBtn.prop("disabled", false).html(saveBtnHtml);
        if (res.success || res.status === 'ok') {
          showSaveFlash();
          resetDisciplineForm();
        } else {
          Swal.fire({ icon: 'error', title: t('Error', 'Ikosa'), text: res.error || t("Save failed!", "Kubika byanze!") });
        }
      },
      error: function (xhr, status, err) {
        saving = false;
        $saveBtn.prop("disabled", false).html(saveBtnHtml);
        Swal.fire({ icon: 'error', title: t('Server error', 'Ikosa rya seriveri'), text: err });
      }
    });
  });

  $("#cardInput").on("focus", function () { $(this).blur(); });

  updateEmptyState();
});

function bootSelects() {
  $("#choose_disc_type, #search_class, #disc_code_id").select2({ width: "100%" });
}
$(bootSelects);
</script>
