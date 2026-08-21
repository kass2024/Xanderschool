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

$DOCS_ENDPOINT     = site_url('getApplicationDocs');
$APPROVE_INFO_API  = site_url('getApproveStudentInformation');
$APPROVE_POST_API  = site_url('manipulateApproveStudentsRegistration');
$REJECT_POST_API   = site_url('rejectApplicationRegistration');
$DELETE_POST_API   = site_url('deleteApplicationRegistration');
$APP_BASE          = rtrim(base_url(), '/');
?>
<style>
  .modal{position:fixed !important; z-index:20050 !important;}
  .modal-backdrop{z-index:20040 !important;}
  .modal-backdrop.show{pointer-events:none !important;}
  .modal, .modal *{ pointer-events:auto !important; }
  .app-inner-layout__content{ transform:none !important; }

  .pending-actions{display:flex;flex-wrap:wrap;gap:4px;justify-content:center;max-width:220px;margin:0 auto;}
  .pending-actions .btn{min-width:72px;font-size:12px;padding:4px 8px;}

  #pendingDocsModal .modal-dialog{max-width:960px;}
  .docs-layout{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.15fr);gap:1rem;}
  @media (max-width:767px){.docs-layout{grid-template-columns:1fr;}}

  .doc-card{border:1px solid #e3e6ef;border-radius:10px;padding:12px 14px;background:#fff;cursor:pointer;transition:box-shadow .15s,border-color .15s,transform .15s;margin-bottom:10px;}
  .doc-card:hover{border-color:#3f6ad8;box-shadow:0 4px 14px rgba(63,106,216,.12);}
  .doc-card.active{border-color:#3f6ad8;background:#f5f8ff;box-shadow:0 0 0 2px rgba(63,106,216,.15);}
  .doc-card.missing{opacity:.65;cursor:default;border-style:dashed;background:#fafafa;}
  .doc-card.missing:hover{border-color:#e3e6ef;box-shadow:none;transform:none;}
  .doc-card-head{display:flex;align-items:flex-start;gap:10px;}
  .doc-icon{width:40px;height:40px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;}
  .doc-icon.pdf{background:#fee2e2;color:#b91c1c;}
  .doc-icon.img{background:#dbeafe;color:#1d4ed8;}
  .doc-icon.file{background:#e5e7eb;color:#374151;}
  .doc-icon.missing{background:#f3f4f6;color:#9ca3af;}
  .doc-meta{min-width:0;flex:1;}
  .doc-meta .title{font-weight:600;font-size:14px;line-height:1.3;margin:0 0 2px;}
  .doc-meta .sub{font-size:12px;color:#6b7280;word-break:break-all;}
  .doc-card-actions{display:flex;gap:6px;margin-top:10px;flex-wrap:wrap;}
  .doc-card-actions .btn{font-size:12px;padding:3px 10px;}

  .docs-preview-panel{border:1px solid #e3e6ef;border-radius:10px;background:#f8fafc;min-height:320px;display:flex;flex-direction:column;overflow:hidden;}
  .docs-preview-head{padding:10px 14px;border-bottom:1px solid #e3e6ef;background:#fff;font-weight:600;font-size:14px;}
  .docs-preview-body{flex:1;display:flex;align-items:center;justify-content:center;padding:12px;min-height:280px;background:#fff;}
  .docs-preview-body iframe,.docs-preview-body img{max-width:100%;max-height:420px;border:0;border-radius:6px;}
  .docs-preview-empty{text-align:center;color:#6b7280;padding:24px;}
  .docs-preview-empty i{font-size:42px;display:block;margin-bottom:10px;opacity:.45;}
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
                            $status    = (string)($pending['status'] ?? '0');
                            $applicant = trim(($pending['applicant'] ?? (($pending['fname'] ?? '') . ' ' . ($pending['lname'] ?? ''))));
                            $payLabel  = $status === '0' ? 'Pay on approval' : ($status === '2' ? 'Failed' : 'Received');
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
                            <td><?= esc($payLabel) ?></td>
                            <td><?= esc($pending['code']) ?></td>
                            <td class="text-center">
                              <div class="pending-actions">
                                <button type="button" class="btn btn-sm btn-info docsBtn" data-id="<?= $id ?>" data-name="<?= esc($applicant) ?>">Docs</button>
                                <button type="button" class="btn btn-sm btn-success approveBtn" data-id="<?= $id ?>" data-name="<?= esc($applicant) ?>">Approve</button>
                                <button type="button" class="btn btn-sm btn-warning rejectBtn" data-id="<?= $id ?>" data-name="<?= esc($applicant) ?>">Reject</button>
                                <button type="button" class="btn btn-sm btn-danger deleteBtn" data-id="<?= $id ?>" data-name="<?= esc($applicant) ?>">Delete</button>
                              </div>
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
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- DOCUMENTS MODAL -->
<div class="modal fade" id="pendingDocsModal" tabindex="-1" role="dialog" aria-labelledby="pendingDocsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="pendingDocsModalLabel">Application documents</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div id="docsAlert" class="alert alert-info d-none mb-3"></div>
        <div class="docs-layout">
          <div>
            <div id="docsList"><div class="text-muted small">Loading…</div></div>
          </div>
          <div class="docs-preview-panel">
            <div class="docs-preview-head" id="docsPreviewTitle">Preview</div>
            <div class="docs-preview-body" id="docsPreviewBody">
              <div class="docs-preview-empty">
                <i class="fa fa-file-text-o"></i>
                Select a document to preview or download.
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- APPROVE MODAL -->
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
        <div class="form-group mb-0">
          <label class="text-muted mb-1">Placement (from registration)</label>
          <div id="approveStructure" class="border rounded p-2 bg-light" style="font-size:14px;">Loading…</div>
          <small class="form-text text-muted">Class is taken from the faculty, department and level already chosen on the application form.</small>
        </div>
        <input type="hidden" id="approveAppId" value="">
        <input type="hidden" id="approveClassId" value="">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success" id="approveConfirmBtn">Yes, approve</button>
      </div>
    </div>
  </div>
</div>

<!-- REJECT MODAL -->
<div class="modal fade" id="rejectRegistrationModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Reject application</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <div id="rejectAlert" class="alert d-none"></div>
        <p>Reject <strong id="rejectName"></strong>? The application will be removed from this pending list.</p>
        <input type="hidden" id="rejectAppId" value="">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-warning" id="rejectConfirmBtn">Yes, reject</button>
      </div>
    </div>
  </div>
</div>

<!-- DELETE MODAL -->
<div class="modal fade" id="deleteRegistrationModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title text-danger">Delete application</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <div id="deleteAlert" class="alert d-none"></div>
        <p class="mb-2">Permanently delete <strong id="deleteName"></strong> and all uploaded files?</p>
        <p class="text-muted small mb-0">This cannot be undone.</p>
        <input type="hidden" id="deleteAppId" value="">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="deleteConfirmBtn">Delete permanently</button>
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
  var REJECT_POST_API  = "<?= addslashes($REJECT_POST_API) ?>";
  var DELETE_POST_API  = "<?= addslashes($DELETE_POST_API) ?>";

  var csrfName = $('meta[name="csrf-token-name"]').attr('content');
  var csrfHash = $('meta[name="csrf-token-value"]').attr('content');
  function withCsrf(data){ data = data || {}; if(csrfName && csrfHash){ data[csrfName] = csrfHash; } return data; }

  var currentDocs = [];

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

  function showAlert($el, kind, msg) {
    $el.removeClass('d-none alert-info alert-danger alert-success alert-warning')
       .addClass('alert-' + kind)
       .text(msg)
       .show();
  }

  function docIconClass(ext, hasFile) {
    if (!hasFile) return 'missing';
    ext = (ext || '').toLowerCase();
    if (['jpg','jpeg','png','gif','webp','bmp'].indexOf(ext) >= 0) return 'img';
    if (ext === 'pdf') return 'pdf';
    return 'file';
  }

  function docIconGlyph(ext, hasFile) {
    if (!hasFile) return '—';
    ext = (ext || '').toLowerCase();
    if (['jpg','jpeg','png','gif','webp','bmp'].indexOf(ext) >= 0) return '🖼';
    if (ext === 'pdf') return 'PDF';
    return '📄';
  }

  function renderDocPreview(item) {
    if (!item || !item.url) {
      $('#docsPreviewTitle').text('Preview');
      $('#docsPreviewBody').html(
        '<div class="docs-preview-empty"><i class="fa fa-file-text-o"></i>No file uploaded for this slot.</div>'
      );
      return;
    }
    var ext = (item.ext || '').toLowerCase();
    var label = item.label || 'Document';
    $('#docsPreviewTitle').text(label);

    if (['jpg','jpeg','png','gif','webp','bmp'].indexOf(ext) >= 0) {
      $('#docsPreviewBody').html('<img src="'+item.url+'" alt="'+label+'">');
    } else if (ext === 'pdf') {
      $('#docsPreviewBody').html('<iframe src="'+item.url+'#toolbar=1" title="'+label+'"></iframe>');
    } else {
      $('#docsPreviewBody').html(
        '<div class="docs-preview-empty">' +
          '<i class="fa fa-download"></i>' +
          'Preview not available for .'+ext+' files.<br>' +
          '<button type="button" class="btn btn-primary btn-sm mt-3 openDocBtn" data-url="'+item.url+'">Open in new tab</button>' +
        '</div>'
      );
    }
  }

  function buildDocCard(item, index) {
    var hasFile = !!(item.path && item.url);
    var ext = item.ext || (item.path ? item.path.split('.').pop() : '');
    var iconCls = docIconClass(ext, hasFile);
    var label = item.label || item.field || 'Document';
    if (item.required) label += ' *';
    var sub = hasFile ? (ext ? ext.toUpperCase() + ' file' : 'Uploaded') : 'Not uploaded';

    var actions = '';
    if (hasFile) {
      actions =
        '<div class="doc-card-actions">' +
          '<button type="button" class="btn btn-outline-primary btn-sm openDocBtn" data-url="'+item.url+'">Open</button>' +
          '<button type="button" class="btn btn-primary btn-sm downloadDocBtn" data-url="'+item.url+'" data-filename="'+label.replace(/"/g,'')+'">Download</button>' +
        '</div>';
    }

    return (
      '<div class="doc-card '+(hasFile ? '' : 'missing')+(index === 0 && hasFile ? ' active' : '')+'" data-index="'+index+'">' +
        '<div class="doc-card-head">' +
          '<div class="doc-icon '+iconCls+'">'+docIconGlyph(ext, hasFile)+'</div>' +
          '<div class="doc-meta">' +
            '<p class="title">'+label+'</p>' +
            '<div class="sub">'+sub+'</div>' +
          '</div>' +
        '</div>' +
        actions +
      '</div>'
    );
  }

  function renderDocsList(items) {
    currentDocs = items || [];
    if (!currentDocs.length) {
      $('#docsList').html('<div class="text-muted">No document slots for this application.</div>');
      renderDocPreview(null);
      return;
    }
    var html = '';
    var firstWithFile = -1;
    currentDocs.forEach(function(it, i) {
      if (firstWithFile < 0 && it.path) firstWithFile = i;
      html += buildDocCard(it, i);
    });
    $('#docsList').html(html);
    if (firstWithFile >= 0) {
      $('.doc-card').removeClass('active');
      $('.doc-card[data-index="'+firstWithFile+'"]').addClass('active');
      renderDocPreview(currentDocs[firstWithFile]);
    } else {
      renderDocPreview(null);
    }
  }

  $(document).on('click', '.doc-card:not(.missing)', function(e) {
    if ($(e.target).closest('.doc-card-actions').length) return;
    var idx = parseInt($(this).data('index'), 10);
    $('.doc-card').removeClass('active');
    $(this).addClass('active');
    renderDocPreview(currentDocs[idx]);
  });

  $(document).on('click', '.openDocBtn', function(e){
    e.preventDefault(); e.stopPropagation();
    var url = $(this).data('url');
    try { window.open(url, '_blank', 'noopener'); } catch(_){ location.href = url; }
  });

  $(document).on('click', '.downloadDocBtn', function(e){
    e.preventDefault(); e.stopPropagation();
    var url = $(this).data('url');
    var name = $(this).data('filename') || 'document';
    var a = document.createElement('a');
    a.href = url;
    a.setAttribute('download', name);
    a.target = '_blank';
    document.body.appendChild(a);
    a.click();
    a.remove();
  });

  function removePendingRow(appId) {
    $('tr[data-id="'+appId+'"]').fadeOut(400, function(){ $(this).remove(); });
  }

  // DOCS
  $(document).on('click', '.docsBtn', function (e) {
    e.preventDefault();
    e.stopPropagation();
    var appId = $(this).data('id');
    var name  = $(this).data('name') || 'Applicant';
    $('#pendingDocsModalLabel').text('Documents — ' + name);
    $('#docsList').html('<div class="text-muted small">Loading…</div>');
    $('#docsAlert').addClass('d-none').text('');
    renderDocPreview(null);

    ajaxWithIndexFallback({
      url: DOCS_API + '/' + encodeURIComponent(appId),
      method: 'GET',
      dataType: 'json'
    })
    .done(function (res) {
      if (res && res.success && res.data) {
        var d = res.data;
        var items = Array.isArray(d.items) ? d.items : [];
        if (!items.length) {
          var fallback = [
            { label: 'Previous academic report', field: 'report1', path: d.report1, url: fileUrl(d.report1), ext: d.report1 ? d.report1.split('.').pop() : null },
            { label: 'Supporting certificate / exam slip', field: 'report2', path: d.report2, url: fileUrl(d.report2), ext: d.report2 ? d.report2.split('.').pop() : null },
            { label: 'Additional document', field: 'report3', path: d.report3, url: fileUrl(d.report3), ext: d.report3 ? d.report3.split('.').pop() : null },
            { label: 'Payment proof', field: 'documents', path: d.documents, url: fileUrl(d.documents), ext: d.documents ? d.documents.split('.').pop() : null }
          ];
          items = fallback.filter(function(it){ return it.path; });
          if (!items.length) items = fallback;
        }
        renderDocsList(items);
        var any = items.some(function(it){ return !!it.path; });
        if (!any) {
          showAlert($('#docsAlert'), 'info', 'No documents uploaded for this application.');
        } else if (res.hint) {
          showAlert($('#docsAlert'), 'info', res.hint + (res.level ? (' (' + (res.faculty || '') + ' / ' + res.level + ')') : ''));
        }
      } else {
        $('#docsList').html('<div class="text-danger">Could not load documents.</div>');
        showAlert($('#docsAlert'), 'danger', (res && res.error) ? res.error : 'Could not load documents.');
      }
    })
    .fail(function () {
      $('#docsList').html('<div class="text-danger">Failed to fetch documents.</div>');
      showAlert($('#docsAlert'), 'danger', 'Failed to fetch documents.');
    })
    .always(function () { $('#pendingDocsModal').modal('show'); });
  });

  // APPROVE
  $(document).on('click', '.approveBtn', function () {
    var appId = $(this).data('id') || '';
    var name  = $(this).data('name') || '';
    $('#approveAppId').val(appId);
    $('#approveClassId').val('');
    $('#approveName').text(name);
    $('#approveAlert').addClass('d-none').removeClass('alert-success alert-danger').text('');
    $('#approveStructure').text('Loading placement…');
    $('#approveConfirmBtn').prop('disabled', true).text('Yes, approve');

    ajaxWithIndexFallback({
      url: APPROVE_INFO_API + '/' + encodeURIComponent(appId),
      method: 'GET',
      dataType: 'json'
    }).done(function(res){
      if(res && res.structure){
        var s = res.structure;
        var classLabel = res.defaultClassLabel || (s.level + ' / ' + s.faculty + ' / ' + s.dpt);
        $('#approveStructure').html(
          '<div><strong>Level:</strong> ' + (s.level || '—') + '</div>' +
          '<div><strong>Faculty:</strong> ' + (s.faculty || '—') + '</div>' +
          '<div><strong>Department:</strong> ' + (s.dpt || '—') + '</div>' +
          '<div><strong>Class:</strong> ' + classLabel + '</div>'
        );
        if (res.defaultClassId) {
          $('#approveClassId').val(res.defaultClassId);
          $('#approveConfirmBtn').prop('disabled', false);
        } else {
          showAlert($('#approveAlert'), 'danger', 'No matching class found for this registration. Create the class for this level/department first.');
        }
      } else {
        $('#approveStructure').text('Could not load placement.');
        showAlert($('#approveAlert'), 'danger', (res && res.error) ? res.error : 'Failed to load application placement.');
      }
    }).fail(function(){
      $('#approveStructure').text('Failed to load placement.');
      showAlert($('#approveAlert'), 'danger', 'Failed to load application placement.');
    });

    $('#approveRegistrationModal').modal('show');
  });

  $(document).on('click', '#approveConfirmBtn', function (e) {
    e.preventDefault(); e.stopPropagation();
    var appId = $('#approveAppId').val();
    var classId = $('#approveClassId').val();
    if (!appId) { showAlert($('#approveAlert'), 'danger', 'Missing application id.'); return; }
    if (!classId) { showAlert($('#approveAlert'), 'danger', 'No class matched from registration.'); return; }

    $('#approveConfirmBtn').prop('disabled', true).text('Approving…');
    ajaxWithIndexFallback({
      url: APPROVE_POST_API,
      method: 'POST',
      dataType: 'json',
      data: withCsrf({ applicationId: appId, classId: classId })
    })
    .done(function (res) {
      if (res && res.success) {
        showAlert($('#approveAlert'), 'success', res.success || 'Applicant approved successfully.');
        setTimeout(function(){
          $('#approveRegistrationModal').modal('hide');
          removePendingRow(appId);
        }, 700);
      } else {
        showAlert($('#approveAlert'), 'danger', (res && (res.error||res.message)) ? (res.error||res.message) : 'Approval failed.');
        $('#approveConfirmBtn').prop('disabled', false).text('Yes, approve');
      }
    })
    .fail(function () {
      showAlert($('#approveAlert'), 'danger', 'Server error during approval.');
      $('#approveConfirmBtn').prop('disabled', false).text('Yes, approve');
    });
  });

  // REJECT
  $(document).on('click', '.rejectBtn', function () {
    $('#rejectAppId').val($(this).data('id') || '');
    $('#rejectName').text($(this).data('name') || '');
    $('#rejectAlert').addClass('d-none').text('');
    $('#rejectConfirmBtn').prop('disabled', false).text('Yes, reject');
    $('#rejectRegistrationModal').modal('show');
  });

  $(document).on('click', '#rejectConfirmBtn', function (e) {
    e.preventDefault();
    var appId = $('#rejectAppId').val();
    if (!appId) return;
    $('#rejectConfirmBtn').prop('disabled', true).text('Rejecting…');
    ajaxWithIndexFallback({
      url: REJECT_POST_API,
      method: 'POST',
      dataType: 'json',
      data: withCsrf({ applicationId: appId })
    }).done(function(res){
      if (res && res.success) {
        $('#rejectRegistrationModal').modal('hide');
        removePendingRow(appId);
      } else {
        showAlert($('#rejectAlert'), 'danger', (res && res.error) ? res.error : 'Reject failed.');
        $('#rejectConfirmBtn').prop('disabled', false).text('Yes, reject');
      }
    }).fail(function(){
      showAlert($('#rejectAlert'), 'danger', 'Server error during reject.');
      $('#rejectConfirmBtn').prop('disabled', false).text('Yes, reject');
    });
  });

  // DELETE
  $(document).on('click', '.deleteBtn', function () {
    $('#deleteAppId').val($(this).data('id') || '');
    $('#deleteName').text($(this).data('name') || '');
    $('#deleteAlert').addClass('d-none').text('');
    $('#deleteConfirmBtn').prop('disabled', false).text('Delete permanently');
    $('#deleteRegistrationModal').modal('show');
  });

  $(document).on('click', '#deleteConfirmBtn', function (e) {
    e.preventDefault();
    var appId = $('#deleteAppId').val();
    if (!appId) return;
    $('#deleteConfirmBtn').prop('disabled', true).text('Deleting…');
    ajaxWithIndexFallback({
      url: DELETE_POST_API,
      method: 'POST',
      dataType: 'json',
      data: withCsrf({ applicationId: appId })
    }).done(function(res){
      if (res && res.success) {
        $('#deleteRegistrationModal').modal('hide');
        removePendingRow(appId);
      } else {
        showAlert($('#deleteAlert'), 'danger', (res && res.error) ? res.error : 'Delete failed.');
        $('#deleteConfirmBtn').prop('disabled', false).text('Delete permanently');
      }
    }).fail(function(){
      showAlert($('#deleteAlert'), 'danger', 'Server error during delete.');
      $('#deleteConfirmBtn').prop('disabled', false).text('Delete permanently');
    });
  });

})(jQuery);
</script>
