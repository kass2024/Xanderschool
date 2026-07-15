<?php
/** @var array $pendings */
/** @var string $title */

$object = new App\Controllers\Home();

/** Parent type label */
if (!function_exists('parentType')) {
    function parentType($v) {
        $map = [1 => 'Father', 2 => 'Mother', 3 => 'Guardian'];
        return $map[(int)$v] ?? '—';
    }
}

// Endpoints that EXIST in your Home controller
$DOCS_ENDPOINT     = site_url('getApplicationDocs');                 // Home::getApplicationDocs($id)
$APPROVE_INFO_API  = site_url('getApproveStudentInformation');       // Home::getApproveStudentInformation($id)
$APPROVE_POST_API  = site_url('manipulateApproveStudentsRegistration'); // Home::manipulateApproveStudentsRegistration()
$RESEND_SMS_API    = site_url('resendApplicationSms');               // Home::resendApplicationSms($id)
$APP_BASE          = rtrim(base_url(), '/');                         // e.g. http://localhost:8081/apade/public
?>
<style>
  .modal{position:fixed !important; z-index:20050 !important;}
  .modal-backdrop{z-index:20040 !important;}
  .modal-backdrop.show{pointer-events:none !important;}
  .modal, .modal *{ pointer-events:auto !important; }
  .app-inner-layout__content{ transform:none !important; }
</style>

<div class="app-inner-layout app-inner-layout-page">
  <div class="app-inner-layout__wrapper">
    <div class="app-inner-layout__content">
      <div class="tab-content">
        <div class="container-fluid">
          <div class="card mb-3">
            <div class="card-header-tab card-header">
              <div class="card-header-title font-size-lg text-capitalize font-weight-normal">
                <i class="header-icon typcn typcn-home-outline text-muted opacity-6"></i>
                <?= esc($title) ?>
              </div>
            </div>

            <div class="card-body">
              <div id="example_wrapper" class="dataTables_wrapper dt-bootstrap4">
                <div class="row">
                  <div class="col-sm-12">
                    <table style="width:100%" id="example" class="table table-hover table-striped table-bordered">
                      <thead>
                        <tr>
                          <th>#</th>
                          <th>Applicant</th>
                          <th>Gender</th>
                          <th>Level</th>
                          <th>Studying mode</th>
                          <th>Parent type</th>
                          <th>Parent name</th>
                          <th>Parent phone</th>
                          <th>Payment status</th>
                          <th>Application - code</th>
                          <th>Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($pendings as $key => $pending): ?>
                          <?php
                            $id        = (int)($pending['id'] ?? 0);
                            $status    = (string)($pending['status'] ?? '0'); // '0' pending, '1' success
                            $applicant = trim(($pending['applicant'] ?? (($pending['fname'] ?? '') . ' ' . ($pending['lname'] ?? ''))));
                          ?>
                          <tr data-id="<?= $id ?>" data-applicant="<?= esc($applicant) ?>" data-status="<?= esc($status) ?>">
                            <td><?= $key + 1 ?></td>
                            <td><?= esc($applicant) ?></td>
                            <td><?= esc($pending['gender']) ?></td>
                            <td><?= esc($pending['level']) ?></td>
                            <td><?= esc($pending['mode'] ?? $pending['studyingMode'] ?? '') ?></td>
                            <td><?= esc(parentType($pending['parentType'])) ?></td>
                            <td><?= esc($pending['parentNames']) ?></td>
                            <td><?= esc($pending['parentPhoneNumber']) ?></td>
                            <td><?= $status === '0' ? 'Pending' : 'Success' ?></td>
                            <td><?= esc($pending['code']) ?></td>
                            <td class="text-center">
                              <button type="button" class="btn btn-sm btn-info docsBtn" data-id="<?= $id ?>">Docs</button>
                              <button type="button" class="btn btn-sm btn-success approveBtn" data-id="<?= $id ?>" data-name="<?= esc($applicant) ?>">Approve</button>
                              <button type="button" class="btn btn-sm btn-primary smsBtn" data-id="<?= $id ?>">Send sms</button>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                      <tfoot>
                        <tr>
                          <th>#</th>
                          <th>Applicant</th>
                          <th>Gender</th>
                          <th>Level</th>
                          <th>Studying mode</th>
                          <th>Parent type</th>
                          <th>Parent name</th>
                          <th>Parent phone</th>
                          <th>Payment status</th>
                          <th>Application - code</th>
                          <th>Actions</th>
                        </tr>
                      </tfoot>
                    </table>
                  </div>
                </div><!-- /.row -->
              </div><!-- /#example_wrapper -->
            </div><!-- /.card-body -->
          </div><!-- /.card -->
        </div><!-- /.container-fluid -->
      </div><!-- /.tab-content -->
    </div>
  </div>
</div>

<!-- ===================== DOCUMENTS MODAL ===================== -->
<div class="modal fade" id="documentModal" tabindex="-1" role="dialog" aria-labelledby="documentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="documentModalLabel">Uploaded documents</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div id="docsAlert" class="alert alert-info d-none"></div>
        <div class="table-responsive">
          <table class="table table-striped" id="docsTable">
            <thead>
              <tr>
                <th style="width:25%">Document</th>
                <th>Path</th>
                <th style="width:220px">Action</th>
              </tr>
            </thead>
            <tbody><!-- injected via JS --></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- ===================== APPROVE MODAL ===================== -->
<div class="modal fade" id="approveRegistrationModal" tabindex="-1" role="dialog" aria-labelledby="approveRegistrationLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="approveRegistrationLabel">Approve application</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <div id="approveAlert" class="alert d-none"></div>
        <p>Are you sure you want to approve <strong id="approveName"></strong>?</p>

        <div class="form-group">
          <label for="approveClassId">Class</label>
          <select id="approveClassId" class="form-control">
            <option value="">Loading classes…</option>
          </select>
          <small class="form-text text-muted" id="approveStructure"></small>
        </div>

        <input type="hidden" id="approveAppId" value="">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success" id="approveConfirmBtn">Yes, approve</button>
      </div>
    </div>
  </div>
</div>

<script>
(function ($) {
  var APP_BASE   = "<?= addslashes($APP_BASE) ?>";
  var DOCS_API   = "<?= addslashes($DOCS_ENDPOINT) ?>";
  var APPROVE_INFO_API = "<?= addslashes($APPROVE_INFO_API) ?>";
  var APPROVE_POST_API = "<?= addslashes($APPROVE_POST_API) ?>";
  var RESEND_SMS_API   = "<?= addslashes($RESEND_SMS_API) ?>";

  // CSRF (optional if enabled)
  var csrfName = $('meta[name="csrf-token-name"]').attr('content');
  var csrfHash = $('meta[name="csrf-token-value"]').attr('content');
  function withCsrf(data){ data = data || {}; if(csrfName && csrfHash){ data[csrfName] = csrfHash; } return data; }

  function fileUrl(rel) {
    if (!rel) return null;
    rel = String(rel).replace(/^\/+/, '');
    return APP_BASE + '/' + rel;
  }

  function ajaxWithIndexFallback(opts) {
    var dfd = $.Deferred();
    $.ajax(opts).done(dfd.resolve).fail(function(xhr){
      if (xhr && xhr.status === 404 && opts.url.indexOf('/index.php/') === -1) {
        var retry = $.extend({}, opts, {
          url: opts.url.replace(APP_BASE + '/', APP_BASE + '/index.php/')
        });
        $.ajax(retry).done(dfd.resolve).fail(dfd.reject);
      } else {
        dfd.reject(xhr);
      }
    });
    return dfd.promise();
  }

  // ---------------- Helpers (rows + alerts) ----------------
  function docRow(label, relPath) {
    if (!relPath) {
      return '<tr><td><strong>'+label+'</strong></td><td>—</td><td><span class="badge badge-secondary">NOT UPLOADED</span></td></tr>';
    }
    var fullUrl = fileUrl(relPath);
    return (
      '<tr>' +
        '<td><strong>' + label + '</strong></td>' +
        '<td><code style="white-space:normal;">' + relPath + '</code></td>' +
        '<td>' +
          '<button type="button" class="btn btn-sm btn-outline-primary mr-2 openDocBtn" data-url="'+fullUrl+'">Open</button>' +
          '<button type="button" class="btn btn-sm btn-primary downloadDocBtn" data-url="'+fullUrl+'">Download</button>' +
        '</td>' +
      '</tr>'
    );
  }

  function showAlert($el, kind, msg) {
    $el.removeClass('d-none alert-info alert-danger alert-success')
       .addClass('alert-' + kind)
       .text(msg)
       .show();
  }

  // Programmatic OPEN/DOWNLOAD
  $(document).on('click', '.openDocBtn', function(e){
    e.preventDefault(); e.stopPropagation();
    var url = $(this).data('url');
    try { window.open(url, '_blank', 'noopener'); } catch(_){ location.href = url; }
  });
  $(document).on('click', '.downloadDocBtn', function(e){
    e.preventDefault(); e.stopPropagation();
    var url = $(this).data('url'), a = document.createElement('a');
    a.href = url; a.setAttribute('download',''); document.body.appendChild(a); a.click(); a.remove();
  });

  // ---------------- DOCS ----------------
  $(document).on('click', '.docsBtn', function () {
    var appId = $(this).data('id');
    $('#docsTable tbody').html('<tr><td colspan="3">Loading…</td></tr>');
    $('#docsAlert').addClass('d-none').text('');

    ajaxWithIndexFallback({
      url: DOCS_API + '/' + encodeURIComponent(appId),
      method: 'GET',
      dataType: 'json'
    })
    .done(function (res) {
      if (res && res.success && res.data) {
        var d = res.data, rows = '';
        rows += docRow('Report 1', d.report1 || null);
        rows += docRow('Report 2', d.report2 || null);
        rows += docRow('Report 3', d.report3 || null);
        rows += docRow('Legacy documents', d.documents || null);
        $('#docsTable tbody').html(rows);
        if (!d.report1 && !d.report2 && !d.report3 && !d.documents) {
          showAlert($('#docsAlert'), 'info', 'No documents uploaded for this application.');
        }
      } else {
        $('#docsTable tbody').html('<tr><td colspan="3">—</td></tr>');
        showAlert($('#docsAlert'), 'danger', (res && res.error) ? res.error : 'Could not load documents.');
      }
    })
    .fail(function () {
      $('#docsTable tbody').html('<tr><td colspan="3">—</td></tr>');
      showAlert($('#docsAlert'), 'danger', 'Failed to fetch documents.');
    })
    .always(function () { $('#documentModal').modal('show'); });
  });

  // ---------------- APPROVE ----------------
  $(document).on('click', '.approveBtn', function () {
    var appId = $(this).data('id') || '';
    var name  = $(this).data('name') || '';
    $('#approveAppId').val(appId);
    $('#approveName').text(name);
    $('#approveAlert').addClass('d-none').removeClass('alert-success alert-danger').text('');
    $('#approveClassId').html('<option value="">Loading classes…</option>');
    $('#approveStructure').text('');

    // load class options from getApproveStudentInformation/{id}
    ajaxWithIndexFallback({
      url: "<?= addslashes($APPROVE_INFO_API) ?>/" + encodeURIComponent(appId),
      method: 'GET',
      dataType: 'json'
    }).done(function(res){
      if(res && res.structure && res.classes){
        var s = res.structure;
        $('#approveStructure').text(
          'Level: '+ (s.level || '') + ' • Faculty: ' + (s.faculty || '') + ' • Department: ' + (s.dpt || '')
        );
        var opts = '<option value="">-- Select class --</option>';
        res.classes.forEach(function(c){
          opts += '<option value="'+c.id+'">'+ c.title +' ('+(c.department_name||'')+')</option>';
        });
        $('#approveClassId').html(opts);
      }else{
        $('#approveClassId').html('<option value="">No classes found</option>');
      }
    }).fail(function(){
      $('#approveClassId').html('<option value="">Failed to load classes</option>');
    });

    $('#approveRegistrationModal').modal('show');
  });

  $(document).on('click', '#approveConfirmBtn', function (e) {
    e.preventDefault(); e.stopPropagation();
    var appId = $('#approveAppId').val();
    var classId = $('#approveClassId').val();
    if (!appId) { showAlert($('#approveAlert'), 'danger', 'Missing application id.'); return; }
    if (!classId) { showAlert($('#approveAlert'), 'danger', 'Please choose a class.'); return; }

    ajaxWithIndexFallback({
      url: "<?= addslashes($APPROVE_POST_API) ?>",
      method: 'POST',
      dataType: 'json',
      data: withCsrf({ applicationId: appId, classId: classId })
    })
    .done(function (res) {
      if (res && res.success) {
        showAlert($('#approveAlert'), 'success', res.success || 'Applicant approved successfully.');
        var $row = $('tr[data-id="'+appId+'"]');
        $row.find('td:nth-child(9)').text('Success');
        setTimeout(function(){ $('#approveRegistrationModal').modal('hide'); }, 700);
      } else {
        showAlert($('#approveAlert'), 'danger', (res && (res.error||res.message)) ? (res.error||res.message) : 'Approval failed.');
      }
    })
    .fail(function (xhr) {
      showAlert($('#approveAlert'), 'danger', 'Server error during approval.');
    });
  });

  // ---------------- SMS (resend) ----------------
  $(document).on('click', '.smsBtn', function () {
    var appId = $(this).data('id') || '';
    if (!appId) { alert('Missing application id.'); return; }

    ajaxWithIndexFallback({
      url: "<?= addslashes($RESEND_SMS_API) ?>/" + encodeURIComponent(appId),
      method: 'POST',
      dataType: 'json',
      data: withCsrf({})
    }).done(function(res){
      alert(res && res.success ? (res.success || 'Message sent successfully') :
            ((res && res.error) ? res.error : 'Failed to send SMS.'));
    }).fail(function(){
      alert('Network or server error while sending SMS.');
    });
  });

})(jQuery);
</script>
