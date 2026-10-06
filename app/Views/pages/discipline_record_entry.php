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

    <div class="disc-kpi-grid" id="discKpis">
      <div class="disc-kpi">
        <div class="disc-kpi-icon purple"><i class="fa fa-gavel"></i></div>
        <div class="disc-kpi-value" data-kpi="incidents">—</div>
        <div class="disc-kpi-label"><?= disc_t('Incidents this term', 'Ibyaha muri iki gihembwe'); ?></div>
      </div>
      <div class="disc-kpi">
        <div class="disc-kpi-icon blue"><i class="fa fa-calendar-day"></i></div>
        <div class="disc-kpi-value" data-kpi="today">—</div>
        <div class="disc-kpi-label"><?= disc_t('Today', 'Uyu munsi'); ?></div>
      </div>
      <div class="disc-kpi">
        <div class="disc-kpi-icon orange"><i class="fa fa-user-clock"></i></div>
        <div class="disc-kpi-value" data-kpi="risk">—</div>
        <div class="disc-kpi-label"><?= disc_t('At-risk students', 'Bari mu kaga'); ?></div>
      </div>
      <div class="disc-kpi">
        <div class="disc-kpi-icon green"><i class="fa fa-book"></i></div>
        <div class="disc-kpi-value" data-kpi="codes"><?= (int) array_sum(array_map(static function ($g) { return count($g['items'] ?? []); }, $discGroups)); ?></div>
        <div class="disc-kpi-label"><?= disc_t('Conduct codes', 'Amabwiriza'); ?></div>
      </div>
    </div>

    <div class="disc-entry-search">
      <?= view('pages/partials/card_scan_search', ['classes' => $classes, 'use_lang' => false]) ?>
      <link rel="stylesheet" href="<?= base_url('assets/css/card-scan-ui.css') ?>?v=conduct-app">
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
            <label for="disc_code_filter"><?= disc_t('School conduct code', 'Amabwiriza y\'ishuri'); ?></label>
            <select class="disc-code-native" id="disc_code_id" name="code_id" required>
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
                            data-title="<?= esc($title, 'attr'); ?>"
                            data-cat="<?= esc($gLabel, 'attr'); ?>">
                      <?= (int) $item['code_no']; ?>. <?= esc($title); ?>
                    </option>
                  <?php endforeach; ?>
                </optgroup>
              <?php endforeach; ?>
            </select>
            <input type="search" id="disc_code_filter" class="form-control" autocomplete="off"
                   placeholder="<?= disc_t('Search by number or words…', 'Shakisha nimero cyangwa amagambo…'); ?>">
            <p class="disc-code-hint" id="discCodeHint"><?= disc_t('The blue step is deducted automatically for this student.', 'Icyiciro cy\'ubururu ni cyo kigabanywa ubwacyo kuri uyu munyeshuri.'); ?></p>
            <div class="disc-code-list" id="discCodeList" role="listbox"></div>
          </div>

          <div class="disc-action-panel">
            <input type="hidden" name="discipline_type" id="choose_disc_type" value="1">
            <div class="disc-action-menu" id="discActionMenu">
              <button type="button" class="disc-action-btn" data-type="0"><?= disc_t('Send remarks to parent', 'Ohereza ibitekerezo ku mubyeyi'); ?></button>
              <button type="button" class="disc-action-btn is-on" data-type="1"><?= disc_t('Reduce discipline marks', 'Gugabanya amanota y\'imyitwarire'); ?></button>
            </div>
            <div class="disc-deduct-label" id="discDeductLabel"><?= disc_t('Automatic deduction', 'Imanota zigabanywa ubwazo'); ?></div>
            <div class="disc-deduct-row">
              <div class="disc-deduct-marks" id="reduce_marks_display">—</div>
              <div class="disc-deduct-occ" id="discOccurApplied"><?= disc_t('Tap a code. Marks are taken automatically.', 'Kanda itegeko. Amanota afatwa ubwazo.'); ?></div>
            </div>
            <div class="disc-deduct-sanction" id="discSanction" hidden></div>
            <pre class="disc-remark-preview" id="discRemarkPreview" hidden></pre>
            <div class="disc-form-group disc-form-check" id="send_sms">
              <input type="checkbox" name="sms" value="1" id="notify_parent">
              <label for="notify_parent"><?= disc_t('Notify parent', 'Menyesha umubyeyi'); ?></label>
            </div>
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
  const remarkTpl = <?= json_encode($discLang === 'rw' ? ($remark_tpl_rw ?? '') : ($remark_tpl_en ?? '')); ?>;
  let saving = false;
  let discCounts = {};

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
      loadCounts();
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

  function optionCount() {
    return $("#disc_code_id option").filter(function () { return parseInt($(this).val(), 10) > 0; }).length;
  }

  function esc(value) {
    return $("<div/>").text(value == null ? "" : String(value)).html();
  }

  function stepWord(n) {
    if (isRw) return String(n);
    if (n <= 1) return "1st";
    if (n === 2) return "2nd";
    return "3rd";
  }

  function nextStep(id) {
    const prev = parseInt(discCounts[String(id)] || 0, 10) || 0;
    let n = prev + 1;
    if (n < 1) n = 1;
    if (n > 3) n = 3;
    return n;
  }

  function firstStudentId() {
    const v = $table.find("input[name='discId[]']").first().val();
    return parseInt(v, 10) || 0;
  }

  function firstStudentName() {
    const $row = $table.find("tr.disc_row").first();
    return ($row.find("td").eq(2).text() || "").trim();
  }

  function remarkText(name, law) {
    const who = name || t("your child", "umwana wawe");
    const rule = law || t("a school rule", "itegeko ry'ishuri");
    return String(remarkTpl || "").split("{NAME}").join(who).split("{LAW}").join(rule);
  }

  function buildCodeList() {
    const $list = $("#discCodeList");
    $list.empty();
    const selected = $("#disc_code_id").val();
    const remarks = isRemarks();
    $("#disc_code_id optgroup").each(function () {
      const cat = $(this).attr("label") || "";
      $list.append(`<div class="disc-code-cat">${esc(cat)}</div>`);
      $(this).find("option").each(function () {
        const id = $(this).val();
        if (!id) return;
        const text = $(this).text().trim();
        const isSel = String(id) === String(selected);
        const m1 = parseInt($(this).attr("data-m1"), 10) || 0;
        const m2 = parseInt($(this).attr("data-m2"), 10) || 0;
        const m3 = parseInt($(this).attr("data-m3"), 10) || 0;
        const s1 = $(this).attr("data-s1") || "";
        const s2 = $(this).attr("data-s2") || "";
        const s3 = $(this).attr("data-s3") || "";
        const flat = String(m1) === String(m2) && String(m2) === String(m3) && s1 === s2 && s2 === s3;
        const n = flat ? 1 : nextStep(id);
        const marks = [m1, m2, m3][n - 1] || 0;
        const nowMarks = remarks ? 0 : marks;
        let nowLabel = t("now", "ubu");
        if (remarks) nowLabel = t("remarks", "ibitekerezo");
        else if (flat) nowLabel = t("every time", "buri gihe");
        else if (nowMarks <= 0) nowLabel = t("no marks", "nta manota");
        const nowText = (!remarks && nowMarks > 0) ? ("\u2212" + nowMarks) : "0";
        let steps = "";
        if (flat) {
          const pay = s1 ? esc(s1) : esc(t("Every time", "Buri gihe"));
          steps = `<span class="disc-step${remarks ? "" : " is-on"}">${pay}</span>`;
        } else {
          [m1, m2, m3].forEach(function (mark, index) {
            const step = index + 1;
            const on = !remarks && step === n;
            const shown = mark > 0 ? ("\u2212" + mark) : "0";
            steps += `<span class="disc-step${on ? " is-on" : ""}">${stepWord(step)}  ${shown}</span>`;
          });
        }
        $list.append(
          `<button type="button" class="disc-code-item${isSel ? " is-selected" : ""}" data-id="${id}" role="option">` +
          `<span class="disc-code-head">` +
          `<span class="disc-code-text">${esc(text)}</span>` +
          `<span class="disc-code-now${(!remarks && nowMarks > 0) ? " has-marks" : ""}">` +
          `<b>${nowText}</b><small>${esc(nowLabel)}</small></span></span>` +
          `<span class="disc-code-steps">${steps}</span></button>`
        );
      });
    });
    const count = optionCount();
    $("#discKpis [data-kpi='codes']").text(count);
    if (!count) {
      $list.html('<div class="disc-code-empty">' + t("No conduct codes found. Loading school laws…", "Nta mabwiriza yabonetse. Turimo gukurura amategeko…") + "</div>");
    }
    filterCodeList();
  }

  function filterCodeList() {
    const term = ($("#disc_code_filter").val() || "").toLowerCase().trim();
    let visible = 0;
    let lastCat = null;
    $("#discCodeList .disc-code-cat, #discCodeList .disc-code-item").each(function () {
      if ($(this).hasClass("disc-code-cat")) {
        lastCat = $(this);
        $(this).hide();
        return;
      }
      const match = !term || $(this).text().toLowerCase().indexOf(term) !== -1
        || String($(this).data("id")).indexOf(term) !== -1;
      $(this).toggle(match);
      if (match) {
        visible++;
        if (lastCat) lastCat.show();
      }
    });
    let $empty = $("#discCodeList .disc-code-empty");
    if (optionCount() && term && visible === 0) {
      if (!$empty.length) {
        $("#discCodeList").append('<div class="disc-code-empty">' + t("No matching law", "Nta tegeko rihuye") + "</div>");
      } else {
        $empty.text(t("No matching law", "Nta tegeko rihuye")).show();
      }
    } else if ($empty.length && optionCount()) {
      $empty.remove();
    }
  }

  function loadCodesIfEmpty() {
    if (optionCount()) {
      buildCodeList();
      return;
    }
    $.getJSON("<?= base_url('discipline_codes_json'); ?>").done(function (res) {
      if (!res || !res.groups) return;
      const $sel = $("#disc_code_id");
      $sel.find("optgroup").remove();
      res.groups.forEach(function (g) {
        const $og = $("<optgroup>").attr("label", g.title || "");
        (g.items || []).forEach(function (item) {
          $og.append(
            $("<option>")
              .val(item.id)
              .attr("data-m1", item.first_marks)
              .attr("data-m2", item.second_marks)
              .attr("data-m3", item.third_marks)
              .attr("data-s1", item.first_sanction || "")
              .attr("data-s2", item.second_sanction || "")
              .attr("data-s3", item.third_sanction || "")
              .attr("data-title", item.title || "")
              .text((item.code_no || "") + ". " + (item.title || ""))
          );
        });
        $sel.append($og);
      });
      buildCodeList();
    }).fail(function () {
      $("#discCodeList").html('<div class="disc-code-empty">' + t("Could not load conduct codes.", "Ntibishoboye gukurura amabwiriza.") + "</div>");
    });
  }

  function loadKpis() {
    $.getJSON("<?= base_url('behavior_dashboard_data'); ?>", { mode: "discipline" }).done(function (data) {
      if (!data || !data.kpis) return;
      const k = data.kpis;
      $("#discKpis [data-kpi='incidents']").text(k.discipline_incidents_term != null ? k.discipline_incidents_term : "0");
      $("#discKpis [data-kpi='today']").text(k.discipline_incidents_today != null ? k.discipline_incidents_today : "0");
      $("#discKpis [data-kpi='risk']").text(k.students_at_risk != null ? k.students_at_risk : "0");
      if (k.conduct_codes != null) $("#discKpis [data-kpi='codes']").text(k.conduct_codes);
    });
  }

  function isRemarks() {
    return String($("#choose_disc_type").val()) === "0";
  }

  function paintAction() {
    const remarks = isRemarks();
    $("#discActionMenu .disc-action-btn").removeClass("is-on");
    $("#discActionMenu .disc-action-btn[data-type='" + (remarks ? "0" : "1") + "']").addClass("is-on");
    $("#discCodeHint").text(remarks
      ? t("The parent receives the mistake committed. Marks stay the same.", "Umubyeyi abona ikosa ryakozwe. Amanota ntigabanywa.")
      : t("The blue step is deducted automatically for this student.", "Icyiciro cy'ubururu ni cyo kigabanywa ubwacyo kuri uyu munyeshuri."));
    $("#discDeductLabel").text(remarks
      ? t("Remark to parent", "Igitekerezo ku mubyeyi")
      : t("Automatic deduction", "Imanota zigabanywa ubwazo"));
    $("label[for='notify_parent']").text(remarks
      ? t("Send remarks to parent", "Ohereza ibitekerezo ku mubyeyi")
      : t("Notify parent", "Menyesha umubyeyi"));
    if (remarks) $("#notify_parent").prop("checked", true);
  }

  function showSanction(text) {
    const value = String(text || "").trim();
    const $line = $("#discSanction");
    if (!value) {
      $line.attr("hidden", true).text("");
      return;
    }
    $line.text(value).removeAttr("hidden");
  }

  function renderSchedule() {
    const $opt = selectedCodeOption();
    const id = parseInt($opt.val(), 10) || 0;
    paintAction();
    const $preview = $("#discRemarkPreview");
    if (!id) {
      $("#reduce_marks_display").text("—").removeClass("has-marks");
      $("#discOccurApplied").text(isRemarks()
        ? t("Choose a law, then send the warning to the parent.", "Hitamo itegeko, hanyuma wohereze umuburo ku mubyeyi.")
        : t("Tap a code. Marks are taken automatically.", "Kanda itegeko. Amanota afatwa ubwazo."));
      showSanction("");
      $preview.attr("hidden", true).text("");
      return;
    }
    const m1 = parseInt($opt.attr("data-m1"), 10) || 0;
    const m2 = parseInt($opt.attr("data-m2"), 10) || 0;
    const m3 = parseInt($opt.attr("data-m3"), 10) || 0;
    const s1 = $opt.attr("data-s1") || "";
    const s2 = $opt.attr("data-s2") || "";
    const s3 = $opt.attr("data-s3") || "";
    const flat = String(m1) === String(m2) && String(m2) === String(m3) && s1 === s2 && s2 === s3;
    const n = flat ? 1 : nextStep(id);
    const marks = [m1, m2, m3][n - 1] || 0;
    const sanction = [s1, s2, s3][n - 1] || "";
    const title = ($opt.attr("data-title") || $opt.text() || "").toString().trim();
    if (isRemarks()) {
      $("#reduce_marks_display").text("0").removeClass("has-marks");
      $("#discOccurApplied").text(t("Mistake committed", "Ikosa ryakozwe") + ": " + title);
      showSanction(sanction);
      $preview.text(remarkText(firstStudentName(), title)).removeAttr("hidden");
      return;
    }
    $preview.attr("hidden", true).text("");
    if (marks > 0) {
      $("#reduce_marks_display").text("\u2212" + marks).addClass("has-marks");
    } else {
      $("#reduce_marks_display").text("0").removeClass("has-marks");
    }
    $("#discOccurApplied").text(flat
      ? t("Every time", "Buri gihe")
      : (stepWord(n) + " " + t("time", "nshuro")));
    showSanction(sanction);
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
    if (!codeId || !ids.length) return;
    $.post("<?= base_url('discipline_code_preview'); ?>", {
      code_id: codeId,
      active_term: $("#disc_active_term").val(),
      discId: ids
    }).done(function (res) {
      if (!res || !res.students || !res.students.length) return;
      const names = {};
      $table.find("tr.disc_row").each(function () {
        const id = $(this).find("input[name='discId[]']").val() || $(this).attr("id");
        names[id] = $(this).find("td").eq(2).text().trim() || ("#" + id);
      });
      const remarks = isRemarks();
      const first = res.students[0];
      if (remarks) {
        const title = (selectedCodeOption().attr("data-title") || "").toString();
        const who = names[first.id] || firstStudentName();
        $("#discOccurApplied").text(t("Mistake committed", "Ikosa ryakozwe") + ": " + title);
        $("#discRemarkPreview").text(remarkText(who, title)).removeAttr("hidden");
        if (res.students.length > 1) {
          const extra = res.students.slice(1).map(function (s) { return names[s.id] || ("#" + s.id); }).join(", ");
          $("#discSanction").text(t("Also", "Na") + ": " + extra).removeAttr("hidden");
        }
        return;
      }
      const marks = parseInt(first.marks, 10) || 0;
      if (marks > 0) $("#reduce_marks_display").text("\u2212" + marks).addClass("has-marks");
      else $("#reduce_marks_display").text("0").removeClass("has-marks");
      let line = (names[first.id] || ("#" + first.id)) + " — " + (first.label || "") + " → " + marks + " " + t("marks", "amanota");
      if (res.students.length > 1) {
        line += " · " + t("and", "na") + " " + (res.students.length - 1) + " " + t("more", "abandi");
      }
      $("#discOccurApplied").text(line);
      if (first.sanction) showSanction(first.sanction);
      if (marks === 0) {
        $("#discOccurApplied").append(" · " + t("No marks this time. It is still saved.", "Nta manota kuri iyi nshuro. Bizabikwa."));
      }
    });
  }

  function loadCounts() {
    const studentId = firstStudentId();
    if (!studentId) {
      discCounts = {};
      buildCodeList();
      return;
    }
    $.post("<?= base_url('discipline_occurrence_map'); ?>", {
      student_id: studentId,
      active_term: $("#disc_active_term").val()
    }).done(function (res) {
      discCounts = (res && res.counts) ? res.counts : {};
      buildCodeList();
      refreshOccurrence();
    });
  }

  function resetDisciplineForm() {
    $table.find("tr.disc_row").remove();
    updateEmptyState();
    $("#disc_code_id").val("").trigger("change");
    $("#disc_code_filter").val("");
    $(".disc-code-item").removeClass("is-selected");
    filterCodeList();
    discCounts = {};
    $("#notify_parent").prop("checked", false);
    $("#student_search_input").val("");
    $("#student_search_box").hide().empty();
    $("#choose_disc_type").val("1");
    if ($("#search_class").data("select2")) {
      $("#search_class").val(null).trigger("change");
    }
    $("#discRemarkPreview").attr("hidden", true).text("");
    $("#discSanction").attr("hidden", true).text("");
    buildCodeList();
    renderSchedule();
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
      data: { searchTerm: term, excludeHoliday: 1, year: "<?= (int) ($academic_year ?? 0); ?>" },
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
      loadCounts();
    });
  });

  $("#search_class").on("select2:select", function (e) {
    $.get("<?= base_url(); ?>get_student/" + e.params.data.id + "/1", function (data) {
      $table.find("tr.disc_row").remove();
      $table.append(data);
      ensureDiscIdsInsideForm();
      updateEmptyState();
      loadCounts();
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
    buildCodeList();
    refreshOccurrence();
  });

  $(document).on("click", ".disc-action-btn", function () {
    $("#choose_disc_type").val(String($(this).data("type"))).trigger("change");
  });

  $(document).on("click", ".disc-code-item", function () {
    const id = $(this).data("id");
    $("#disc_code_id").val(String(id)).trigger("change");
    $(".disc-code-item").removeClass("is-selected");
    $(this).addClass("is-selected");
  });

  $("#disc_code_filter").on("input", filterCodeList);

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

  loadCodesIfEmpty();
  loadKpis();
  updateEmptyState();
});

function bootSelects() {
  $("#search_class").each(function () {
    const $el = $(this);
    if ($el.data("select2")) {
      try { $el.select2("destroy"); } catch (e) {}
    }
    $el.select2({ width: "100%", placeholder: $el.find("option:first").text() });
  });
}
$(bootSelects);
</script>
