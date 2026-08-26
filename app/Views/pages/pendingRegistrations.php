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

$totalPending = count($pendings);
$boardingN = 0;
$dayN = 0;
$maleN = 0;
$femaleN = 0;
foreach ($pendings as $p) {
	$mode = strtolower((string) ($p['mode'] ?? $p['studyingMode'] ?? ''));
	if (strpos($mode, 'board') !== false) {
		$boardingN++;
	} else {
		$dayN++;
	}
	$g = strtoupper(substr((string) ($p['gender'] ?? ''), 0, 1));
	if ($g === 'M') {
		$maleN++;
	} else {
		$femaleN++;
	}
}
?>
<link rel="stylesheet" href="<?= base_url('assets/css/fees-entry.css') ?>">
<style>
  .modal{position:fixed !important; z-index:20050 !important;}
  .modal-backdrop{z-index:20040 !important;}
  .modal-backdrop.show{pointer-events:none !important;}
  .modal, .modal *{ pointer-events:auto !important; }
  .app-inner-layout__content{ transform:none !important; }

  .pending-actions{display:flex;flex-wrap:wrap;gap:6px;justify-content:center;max-width:240px;margin:0 auto;}
  .pending-actions .btn{min-width:72px;font-size:12px;padding:4px 8px;}
  .pending-mobile{display:none;}
  .pending-search-wrap{display:none;}
  .pending-page{overflow-x:hidden;}
  .pending-kpi{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:8px;
    margin:0 0 12px;
  }
  .pending-kpi-item{
    background:#fff;
    border:1px solid #e2e8f0;
    border-radius:12px;
    padding:10px 12px;
    box-shadow:0 4px 12px rgba(15,23,42,.04);
    min-width:0;
  }
  .pending-kpi-item span{
    display:block;
    font-size:1.35rem;
    font-weight:800;
    color:#0b1f4a;
    line-height:1.1;
  }
  .pending-kpi-item small{
    display:block;
    margin-top:2px;
    font-size:.68rem;
    font-weight:700;
    letter-spacing:.04em;
    text-transform:uppercase;
    color:#64748b;
  }
  .pending-kpi-item.is-total span{color:#1d4ed8;}
  .pending-kpi-item.is-board span{color:#0f766e;}
  .pending-kpi-item.is-day span{color:#b45309;}
  .pending-kpi-split{
    display:flex;
    align-items:baseline;
    gap:6px;
  }
  .pending-kpi-split span{font-size:1.15rem;}
  .pending-kpi-split em{
    font-style:normal;
    font-size:.78rem;
    font-weight:700;
    color:#94a3b8;
  }

  .pending-card{
    background:#fff;
    border:1px solid #e2e8f0;
    border-radius:14px;
    padding:14px;
    margin-bottom:12px;
    box-shadow:0 4px 14px rgba(15,23,42,.05);
  }
  .pending-card-head{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:10px;
    margin-bottom:10px;
  }
  .pending-card-head h3{
    margin:0 0 6px;
    font-size:1.05rem;
    font-weight:800;
    color:#0b1f4a;
    line-height:1.25;
    word-break:break-word;
  }
  .pending-num{
    flex-shrink:0;
    min-width:32px;
    height:32px;
    border-radius:999px;
    background:#eff6ff;
    color:#1d4ed8;
    font-weight:800;
    font-size:.82rem;
    display:grid;
    place-items:center;
  }
  .pending-status{
    display:inline-block;
    background:#fef3c7;
    color:#92400e;
    font-size:.72rem;
    font-weight:700;
    padding:3px 8px;
    border-radius:999px;
  }
  .pending-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:8px 10px;
    margin:0 0 12px;
  }
  .pending-grid div{
    min-width:0;
    background:#f8fafc;
    border-radius:10px;
    padding:8px 10px;
  }
  .pending-grid dt{
    margin:0;
    font-size:.68rem;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:.04em;
    color:#64748b;
  }
  .pending-grid dd{
    margin:2px 0 0;
    font-size:.9rem;
    font-weight:600;
    color:#0f172a;
    word-break:break-word;
  }
  .pending-grid .span-2{grid-column:1 / -1;}
  .pending-card .pending-actions{
    max-width:none;
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:8px;
  }
  .pending-card .pending-actions .btn{
    min-width:0;
    min-height:42px;
    font-size:.86rem;
    font-weight:700;
    padding:8px 10px;
  }
  .pending-card .pending-actions .deleteBtn{grid-column:1 / -1;}
  .pending-empty{text-align:center;color:#64748b;padding:28px 12px;}

  @media (max-width:767.98px){
    .app-inner-layout__content .container-fluid{padding-left:10px;padding-right:10px;}
    .pending-kpi{grid-template-columns:1fr 1fr;margin-bottom:10px;}
    .pending-kpi-item{padding:8px 10px;}
    .pending-kpi-item span{font-size:1.2rem;}
    .pending-page .card-header{padding:12px 14px;}
    .pending-page .card-body{padding:12px 10px 16px;}
    .pending-desktop{display:none !important;}
    .pending-page .dataTables_wrapper{display:none !important;}
    .pending-mobile{display:block;}
    .pending-search-wrap{display:block;margin-bottom:12px;}
    .pending-search-wrap input{
      width:100%;
      min-height:44px;
      border:1.5px solid #e2e8f0;
      border-radius:12px;
      padding:10px 14px;
      font-size:16px;
    }
    #approveRegistrationModal .modal-dialog,
    #pendingDocsModal .modal-dialog,
    #rejectRegistrationModal .modal-dialog,
    #deleteRegistrationModal .modal-dialog{
      margin:8px;
      max-width:calc(100% - 16px);
    }
    #approveRegistrationModal .modal-body{padding:12px;}
    .fe-invoice-toolbar{display:flex;flex-wrap:wrap;gap:8px;}
    .fe-invoice-toolbar .btn{flex:1 1 46%;}
    .fe-invoice-hint{flex-basis:100%;}
    .fe-invoice-table-wrap{overflow:visible;}
    #feInvoiceTable thead{display:none;}
    #feInvoiceTable tbody tr.fe-inv-section td{
      display:block;
      background:#eef2ff;
      font-weight:700;
      border:0;
    }
    #feInvoiceTable tbody tr.fe-inv-row{
      display:block;
      border:1px solid #e2e8f0;
      border-radius:10px;
      margin-bottom:10px;
      padding:8px;
      background:#fff;
    }
    #feInvoiceTable tbody tr.fe-inv-row td{
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap:8px;
      border:0;
      padding:4px 0;
    }
    #feInvoiceTable tbody tr.fe-inv-row td:nth-child(1)::before{content:"Pay";font-size:.72rem;font-weight:700;color:#64748b;text-transform:uppercase;}
    #feInvoiceTable tbody tr.fe-inv-row td:nth-child(2)::before{content:"Item";font-size:.72rem;font-weight:700;color:#64748b;text-transform:uppercase;}
    #feInvoiceTable tbody tr.fe-inv-row td:nth-child(3)::before{content:"Term";font-size:.72rem;font-weight:700;color:#64748b;text-transform:uppercase;}
    #feInvoiceTable tbody tr.fe-inv-row td:nth-child(4)::before{content:"Expected";font-size:.72rem;font-weight:700;color:#64748b;text-transform:uppercase;}
    #feInvoiceTable tbody tr.fe-inv-row td:nth-child(5)::before{content:"Paid";font-size:.72rem;font-weight:700;color:#64748b;text-transform:uppercase;}
    #feInvoiceTable tbody tr.fe-inv-row td:nth-child(6)::before{content:"Remain";font-size:.72rem;font-weight:700;color:#64748b;text-transform:uppercase;}
    #feInvoiceTable tbody tr.fe-inv-row td:nth-child(7)::before{content:"Receive";font-size:.72rem;font-weight:700;color:#64748b;text-transform:uppercase;}
    #feInvoiceTable tfoot tr{display:block;}
    #feInvoiceTable tfoot td{display:flex;justify-content:space-between;border:0;}
    .fe-invoice-meta .col-md-4{margin-bottom:10px;}
    .pending-actions .btn{min-height:40px;}
  }

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

  #approveRegistrationModal .modal-dialog{max-width:1100px;}
  .pr-place-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;margin-bottom:12px;}
  @media (max-width:767px){.pr-place-grid{grid-template-columns:1fr 1fr;}}
  .pr-place-chip{border:1px solid #e3e6ef;border-radius:8px;padding:8px 10px;background:#f8fafc;}
  .pr-place-chip span{display:block;font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:.03em;}
  .pr-place-chip strong{font-size:13px;}
  .pr-add-panel{border:1px dashed #cbd5e1;border-radius:8px;padding:12px;margin-bottom:12px;background:#f8fafc;display:none;}
  .pr-add-panel.show{display:block;}
  .pending-actor-bar{
    display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;
    background:#eff6ff;border:1px solid #bfdbfe;border-radius:12px;padding:10px 14px;margin:0 0 12px;
  }
  .pending-actor-bar strong{color:#1d4ed8;}
  .pending-recent{
    background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:10px 14px;margin:0 0 12px;
  }
  .pending-recent h6{margin:0 0 8px;font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#64748b;}
  .pending-recent-list{list-style:none;margin:0;padding:0;}
  .pending-recent-list li{display:flex;flex-wrap:wrap;gap:6px 10px;align-items:baseline;padding:5px 0;border-top:1px solid #f1f5f9;font-size:.85rem;}
  .pending-recent-list li:first-child{border-top:0;}
  .pending-recent-list .act{font-weight:700;}
  .pending-recent-list .act.approved{color:#15803d;}
  .pending-recent-list .act.rejected{color:#b45309;}
  .pending-recent-list .act.deleted{color:#b91c1c;}
  .pending-recent-list .who{color:#1e293b;font-weight:600;}
  .pending-recent-list .when{margin-left:auto;color:#94a3b8;font-size:.75rem;}
</style>

<div class="app-inner-layout app-inner-layout-page">
  <div class="app-inner-layout__wrapper">
    <div class="app-inner-layout__content">
      <div class="tab-content">
        <div class="container-fluid">
          <div class="pending-actor-bar">
            <div>
              <?= esc(lang('app.actingAs')); ?>:
              <strong><?= esc($financeActorName ?? session('soma_name') ?? 'Staff'); ?></strong>
              <span class="text-muted"> — approve, reject, and delete are saved under this name.</span>
            </div>
          </div>
          <div class="pending-recent" id="pendingRecentWrap"<?= empty($recentFinanceActions) ? ' style="display:none"' : ''; ?>>
            <h6><?= esc(lang('app.recentFinanceActions')); ?></h6>
            <ul class="pending-recent-list" id="pendingRecentList">
              <?php foreach (($recentFinanceActions ?? []) as $act):
                $actKey = strtolower((string) ($act['action'] ?? ''));
                $actClass = 'approved';
                $actLabel = 'Recorded';
                if (strpos($actKey, 'approve') !== false) { $actClass = 'approved'; $actLabel = 'Approved'; }
                elseif (strpos($actKey, 'reject') !== false) { $actClass = 'rejected'; $actLabel = 'Rejected'; }
                elseif (strpos($actKey, 'delete') !== false) { $actClass = 'deleted'; $actLabel = 'Deleted'; }
                elseif (strpos($actKey, 'fee') !== false) { $actClass = 'approved'; $actLabel = 'Fee recorded'; }
                $when = !empty($act['created_at']) ? date('d-M H:i', strtotime($act['created_at'])) : '';
              ?>
                <li>
                  <span class="act <?= esc($actClass); ?>"><?= esc($actLabel); ?></span>
                  <span><?= esc($act['subject'] ?? ''); ?></span>
                  <span class="who"><?= esc($act['staff_name'] ?? ''); ?></span>
                  <span class="when"><?= esc($when); ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
          <div class="pending-kpi" id="pendingKpi">
            <div class="pending-kpi-item is-total">
              <span id="kpiTotal"><?= (int) $totalPending ?></span>
              <small>Total pending</small>
            </div>
            <div class="pending-kpi-item is-board">
              <span id="kpiBoard"><?= (int) $boardingN ?></span>
              <small>Boarding</small>
            </div>
            <div class="pending-kpi-item is-day">
              <span id="kpiDay"><?= (int) $dayN ?></span>
              <small>Day</small>
            </div>
            <div class="pending-kpi-item">
              <div class="pending-kpi-split">
                <span id="kpiMale"><?= (int) $maleN ?></span><em>M</em>
                <span id="kpiFemale"><?= (int) $femaleN ?></span><em>F</em>
              </div>
              <small>Gender</small>
            </div>
          </div>
          <div class="card mb-3 pending-page">
            <div class="card-header-tab card-header">
              <div class="card-header-title font-size-lg text-capitalize font-weight-normal">
                <i class="header-icon typcn typcn-home-outline text-muted opacity-6"></i>
                <?= esc($title) ?>
              </div>
            </div>

            <div class="card-body">
              <div class="pending-search-wrap">
                <input type="search" id="pendingMobileSearch" placeholder="Search applicant, parent, phone, class…" autocomplete="off">
              </div>
              <div class="pending-desktop">
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
                          <th>Registration</th>
                          <th>Parent type</th>
                          <th>Parent name</th>
                          <th>Parent phone</th>
                          <th>Status</th>
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
                            $payLabel  = 'Pending approval';
                          ?>
                          <tr data-id="<?= $id ?>" data-applicant="<?= esc($applicant) ?>" data-status="<?= esc($status) ?>" data-mode="<?= esc($pending['mode'] ?? $pending['studyingMode'] ?? '') ?>" data-gender="<?= esc($pending['gender'] ?? '') ?>">
                            <td><?= $key + 1 ?></td>
                            <td><?= esc($applicant) ?></td>
                            <td><?= esc($pending['gender']) ?></td>
                            <td><?= esc($pending['level']) ?></td>
                            <td><?= esc($pending['mode'] ?? $pending['studyingMode'] ?? '') ?></td>
                            <td>
                              <?php $due = (float) ($pending['fee_due'] ?? 0); ?>
                              <?php if ($due > 0): ?>
                                <strong><?= number_format($due); ?></strong>
                                <small class="d-block text-muted"><?= esc($pending['mode'] ?? ''); ?></small>
                              <?php else: ?>
                                <span class="text-muted">—</span>
                              <?php endif; ?>
                            </td>
                            <td><?= esc(parentType($pending['parentType'])) ?></td>
                            <td><?= esc($pending['parentNames']) ?></td>
                            <td><?= esc($pending['parentPhoneNumber']) ?></td>
                            <td><?= esc($payLabel) ?></td>
                            <td><?= esc($pending['code']) ?></td>
                            <td class="text-center">
                              <div class="pending-actions">
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
                          <th>Registration</th>
                          <th>Parent type</th>
                          <th>Parent name</th>
                          <th>Parent phone</th>
                          <th>Status</th>
                          <th>Application - code</th>
                          <th>Actions</th>
                        </tr>
                      </tfoot>
                    </table>
                  </div>
                </div>
              </div>
              </div>
              <div class="pending-mobile" id="pendingCards">
                <?php if (empty($pendings)): ?>
                  <div class="pending-empty">No pending applications.</div>
                <?php else: ?>
                  <?php foreach ($pendings as $key => $pending): ?>
                    <?php
                      $id        = (int)($pending['id'] ?? 0);
                      $status    = (string)($pending['status'] ?? '0');
                      $applicant = trim(($pending['applicant'] ?? (($pending['fname'] ?? '') . ' ' . ($pending['lname'] ?? ''))));
                      $gender    = (string)($pending['gender'] ?? '—');
                      $level     = (string)($pending['level'] ?? '—');
                      $mode      = (string)($pending['mode'] ?? $pending['studyingMode'] ?? '—');
                      $ptype     = parentType($pending['parentType'] ?? '');
                      $pname     = (string)($pending['parentNames'] ?? '—');
                      $pphone    = (string)($pending['parentPhoneNumber'] ?? '—');
                      $code      = (string)($pending['code'] ?? '—');
                      $payLabel  = 'Pending approval';
                      $searchHay = strtolower(trim($applicant.' '.$gender.' '.$level.' '.$mode.' '.$ptype.' '.$pname.' '.$pphone.' '.$code));
                    ?>
                    <article class="pending-card" data-id="<?= $id ?>" data-search="<?= esc($searchHay) ?>" data-mode="<?= esc($mode) ?>" data-gender="<?= esc($gender) ?>">
                      <div class="pending-card-head">
                        <div>
                          <h3><?= esc($applicant !== '' ? $applicant : 'Applicant') ?></h3>
                          <span class="pending-status"><?= esc($payLabel) ?></span>
                        </div>
                        <span class="pending-num"><?= $key + 1 ?></span>
                      </div>
                      <dl class="pending-grid">
                        <div>
                          <dt>Gender</dt>
                          <dd><?= esc($gender) ?></dd>
                        </div>
                        <div>
                          <dt>Level</dt>
                          <dd><?= esc($level) ?></dd>
                        </div>
                        <div>
                          <dt>Studying mode</dt>
                          <dd><?= esc($mode) ?></dd>
                        </div>
                        <div>
                          <dt>Registration</dt>
                          <dd>
                            <?php $due = (float) ($pending['fee_due'] ?? 0); ?>
                            <?= $due > 0 ? number_format($due) . ' Rwf' : '—'; ?>
                          </dd>
                        </div>
                        <div>
                          <dt>Parent type</dt>
                          <dd><?= esc($ptype) ?></dd>
                        </div>
                        <div class="span-2">
                          <dt>Parent name</dt>
                          <dd><?= esc($pname !== '' ? $pname : '—') ?></dd>
                        </div>
                        <div class="span-2">
                          <dt>Parent phone</dt>
                          <dd>
                            <?php if ($pphone !== '' && $pphone !== '—'): ?>
                              <a href="tel:<?= esc($pphone) ?>"><?= esc($pphone) ?></a>
                            <?php else: ?>
                              —
                            <?php endif; ?>
                          </dd>
                        </div>
                        <div class="span-2">
                          <dt>Application code</dt>
                          <dd><?= esc($code) ?></dd>
                        </div>
                      </dl>
                      <div class="pending-actions">
                        <button type="button" class="btn btn-success approveBtn" data-id="<?= $id ?>" data-name="<?= esc($applicant) ?>">Approve</button>
                        <button type="button" class="btn btn-warning rejectBtn" data-id="<?= $id ?>" data-name="<?= esc($applicant) ?>">Reject</button>
                        <button type="button" class="btn btn-danger deleteBtn" data-id="<?= $id ?>" data-name="<?= esc($applicant) ?>">Delete</button>
                      </div>
                    </article>
                  <?php endforeach; ?>
                <?php endif; ?>
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

<!-- APPROVE + FEE RECORD MODAL -->
<div class="modal fade" id="approveRegistrationModal" tabindex="-1" role="dialog" aria-labelledby="approveRegistrationLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content fe-invoice-modal">
      <div class="modal-header">
        <h5 class="modal-title" id="approveRegistrationLabel">Record fees &amp; approve</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <div id="approveAlert" class="alert d-none"></div>
        <p class="mb-2">Record payment for <strong id="approveName"></strong> using the class and studying mode from the application, then approve.</p>
        <div class="pr-place-grid" id="approveStructure">
          <div class="pr-place-chip"><span>Level</span><strong>—</strong></div>
          <div class="pr-place-chip"><span>Class</span><strong>—</strong></div>
          <div class="pr-place-chip"><span>Studying mode</span><strong>—</strong></div>
          <div class="pr-place-chip"><span>Year</span><strong>—</strong></div>
        </div>
        <input type="hidden" id="approveAppId" value="">
        <input type="hidden" id="approveClassId" value="">

        <div id="feInvoiceLoading" class="fe-invoice-loading">
          <i class="fa fa-spinner fa-spin"></i> Loading payable items…
        </div>

        <div id="feInvoiceWrap" style="display:none">
          <div class="fe-invoice-toolbar">
            <button type="button" class="btn btn-sm btn-outline-primary" id="feInvSelectAll">Select all</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="feInvFillBalance">Fill full balance</button>
            <span class="fe-invoice-hint">Only the Registration fee is recorded at approval. School fees and other extras stay on Fees entry.</span>
          </div>

          <div id="prAddFeePanel" class="pr-add-panel">
            <div class="form-row">
              <div class="form-group col-md-3 mb-2" id="prAddTitleWrap">
                <label>Item title</label>
                <input type="text" class="form-control form-control-sm" id="prAddTitle" placeholder="e.g. Uniform">
              </div>
              <div class="form-group col-md-3 mb-2">
                <label><?= lang('app.term') ?></label>
                <select class="form-control form-control-sm" id="prAddTerm">
                  <option value="1"><?= lang('app.term1') ?></option>
                  <option value="2"><?= lang('app.term2') ?></option>
                  <option value="3"><?= lang('app.term3') ?></option>
                </select>
              </div>
              <div class="form-group col-md-3 mb-2">
                <label><?= lang('app.expectedAmount') ?></label>
                <input type="number" min="1" step="1" class="form-control form-control-sm" id="prAddAmount" placeholder="0">
              </div>
              <div class="form-group col-md-3 mb-2 d-flex align-items-end">
                <button type="button" class="btn btn-sm btn-primary mr-2" id="prAddFeeSave">Add item</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="prAddFeeCancel">Cancel</button>
              </div>
            </div>
            <small class="text-muted" id="prAddFeeHint"></small>
          </div>

          <div class="table-responsive fe-invoice-table-wrap">
            <table class="table table-sm table-hover mb-0" id="feInvoiceTable">
              <thead>
                <tr>
                  <th style="width:36px"></th>
                  <th><?= lang('app.item') ?></th>
                  <th><?= lang('app.term') ?></th>
                  <th class="text-right"><?= lang('app.expectedAmount') ?></th>
                  <th class="text-right"><?= lang('app.paidAmount') ?></th>
                  <th class="text-right"><?= lang('app.remainAmount') ?></th>
                  <th class="text-right" style="min-width:120px"><?= lang('app.receivedAmount') ?></th>
                </tr>
              </thead>
              <tbody id="feInvoiceBody"></tbody>
              <tfoot>
                <tr class="fe-invoice-total-row">
                  <td colspan="6" class="text-right"><strong>Total to pay</strong></td>
                  <td class="text-right"><strong id="feInvoiceTotal">0</strong> Rwf</td>
                </tr>
              </tfoot>
            </table>
          </div>

          <div class="row fe-invoice-meta">
            <div class="col-md-4">
              <label><?= lang('app.paymentMode') ?></label>
              <select class="form-control" id="feInvoicePaymentMode" required>
                <option value="" disabled selected><?= lang('app.selectPaymentMode') ?></option>
                <option value="1"><?= lang('app.bankSlip') ?></option>
                <option value="2"><?= lang('app.cash') ?></option>
                <option value="3"><?= lang('app.cheque') ?></option>
                <option value="4"><?= lang('app.momo') ?></option>
                <option value="5"><?= lang('app.airtelMoney') ?></option>
              </select>
            </div>
            <div class="col-md-4">
              <label><?= lang('app.dueDate') ?></label>
              <input type="date" class="form-control" id="feInvoiceDueDate">
            </div>
            <div class="col-md-4" id="feSlipRefWrap">
              <label><?= lang('app.slipReference') ?></label>
              <input type="text" class="form-control" id="feInvoiceSlipRef" maxlength="50" placeholder="<?= lang('app.slipReferencePlaceholder') ?>">
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success" id="approveConfirmBtn" disabled>Save payment &amp; approve</button>
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
  var TERM_LABELS = {
    1: <?= json_encode(lang('app.term1')) ?>,
    2: <?= json_encode(lang('app.term2')) ?>,
    3: <?= json_encode(lang('app.term3')) ?>
  };
  var invoiceItems = [];
  var schoolFeeTerms = {};
  var addFeeKind = 'extra';
  var newItemSeq = 0;
  var financeActorName = <?= json_encode($financeActorName ?? session('soma_name') ?? 'Staff'); ?>;

  function prependRecentAction(label, subject, actor, cls) {
    var $wrap = $('#pendingRecentWrap');
    var $list = $('#pendingRecentList');
    if (!$list.length) return;
    $wrap.show();
    var now = new Date();
    var pad = function (n) { return n < 10 ? '0' + n : '' + n; };
    var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    var when = pad(now.getDate()) + '-' + months[now.getMonth()] + ' ' + pad(now.getHours()) + ':' + pad(now.getMinutes());
    var html = '<li>' +
      '<span class="act ' + (cls || 'approved') + '">' + $('<div>').text(label || '').html() + '</span>' +
      '<span>' + $('<div>').text(subject || '').html() + '</span>' +
      '<span class="who">' + $('<div>').text(actor || financeActorName).html() + '</span>' +
      '<span class="when">' + when + '</span>' +
      '</li>';
    $list.prepend(html);
    $list.find('li').slice(15).remove();
  }

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

  function bumpKpi(sel, delta) {
    var $el = $(sel);
    var n = parseInt($el.text(), 10) || 0;
    $el.text(Math.max(0, n + delta));
  }
  function removePendingRow(appId) {
    var $card = $('.pending-card[data-id="'+appId+'"]');
    var $row = $('tr[data-id="'+appId+'"]');
    var mode = String($card.data('mode') || $row.data('mode') || '').toLowerCase();
    var gender = String($card.data('gender') || $row.data('gender') || '').toLowerCase();
    bumpKpi('#kpiTotal', -1);
    if (mode.indexOf('board') !== -1) {
      bumpKpi('#kpiBoard', -1);
    } else {
      bumpKpi('#kpiDay', -1);
    }
    if (gender.charAt(0) === 'm') {
      bumpKpi('#kpiMale', -1);
    } else {
      bumpKpi('#kpiFemale', -1);
    }
    $row.fadeOut(400, function(){ $(this).remove(); });
    $card.fadeOut(400, function(){ $(this).remove(); });
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

  function formatRwf(n) {
    return Number(n || 0).toLocaleString();
  }

  function renderPlacement(res) {
    var s = (res && res.structure) || {};
    var classLabel = res.defaultClassLabel || [s.level, s.faculty, s.dpt].filter(Boolean).join(' / ') || '—';
    $('#approveStructure').html(
      '<div class="pr-place-chip"><span>Level</span><strong>' + (s.level || '—') + '</strong></div>' +
      '<div class="pr-place-chip"><span>Class</span><strong>' + classLabel + '</strong></div>' +
      '<div class="pr-place-chip"><span>Studying mode</span><strong>' + (res.modeLabel || '—') + '</strong></div>' +
      '<div class="pr-place-chip"><span>Year</span><strong>' + (res.academicYearTitle || '—') + '</strong></div>'
    );
  }

  function feInvoiceUpdateTotal() {
    var total = 0;
    var count = 0;
    $('#feInvoiceBody .fe-inv-check:checked').each(function () {
      var $row = $(this).closest('tr');
      var amt = parseFloat($row.find('.fe-inv-amount').val()) || 0;
      var max = parseFloat($row.data('remain')) || 0;
      if (amt > 0 && amt <= max + 0.001) {
        total += amt;
        count++;
      }
    });
    var mode = $('#feInvoicePaymentMode').val();
    var slipOk = ($('#feInvoiceSlipRef').val() || '').trim().length > 0;
    $('#feInvoiceTotal').text(formatRwf(total));
    $('#approveConfirmBtn').prop('disabled', count === 0 || !mode || !slipOk || !$('#approveClassId').val());
  }

  function feToggleSlipRef() {
    $('#feSlipRefWrap').show();
    feInvoiceUpdateTotal();
  }

  function hideAddFeePanel() {
    $('#prAddFeePanel').removeClass('show');
    $('#prAddTitle').val('');
    $('#prAddAmount').val('');
  }

  function isRegistrationFeeItem(it) {
    var label = String((it && (it.label || it.title)) || '').toLowerCase();
    return /regist/.test(label);
  }

  function feInvoiceRenderItems(items) {
    invoiceItems = (items || []).filter(isRegistrationFeeItem);
    var html = '';
    var lastCat = '';
    invoiceItems.forEach(function (it, idx) {
      if (it.category !== lastCat) {
        html += '<tr class="fe-inv-section"><td colspan="7">' + it.category + '</td></tr>';
        lastCat = it.category;
      }
      html += '<tr class="fe-inv-row" data-index="' + idx + '" data-id="' + (it.id || 0) + '" data-type="' + it.fee_type + '" data-remain="' + it.remain + '">' +
        '<td><input type="checkbox" class="fe-inv-check"></td>' +
        '<td>' + (it.label || '') + (it.is_new ? ' <span class="badge badge-info">New</span>' : '') + '</td>' +
        '<td>' + (it.term || '') + '</td>' +
        '<td class="text-right">' + formatRwf(it.expected) + '</td>' +
        '<td class="text-right">' + formatRwf(it.paid) + '</td>' +
        '<td class="text-right fe-amount-due">' + formatRwf(it.remain) + '</td>' +
        '<td class="text-right"><input type="number" class="form-control form-control-sm fe-inv-amount text-right" min="0" step="1" max="' + it.remain + '" placeholder="0" disabled></td>' +
        '</tr>';
    });
    if (!invoiceItems.length) {
      html = '<tr><td colspan="7" class="text-center text-muted py-3">No registration fee found for this class.</td></tr>';
    }
    $('#feInvoiceBody').html(html);
    $('#feInvoiceBody .fe-inv-check').each(function () {
      $(this).prop('checked', true).trigger('change');
    });
    feInvoiceUpdateTotal();
  }

  function showAddFeePanel(kind) {
    addFeeKind = kind;
    $('#prAddTitleWrap').toggle(kind === 'extra');
    $('#prAddFeeHint').text(kind === 'school'
      ? 'Adds school fees for this registration class and studying mode (boarding or day).'
      : 'Adds an extra fee for this student if it is not already listed.');
    $('#prAddFeePanel').addClass('show');
    if (kind === 'extra') {
      $('#prAddTitle').focus();
    } else {
      $('#prAddAmount').focus();
    }
  }

  function addInvoiceItem() {
    var term = parseInt($('#prAddTerm').val(), 10) || 1;
    var amount = parseFloat($('#prAddAmount').val()) || 0;
    var title = ($('#prAddTitle').val() || '').trim();
    if (amount <= 0) {
      showAlert($('#approveAlert'), 'danger', 'Enter an expected amount greater than 0.');
      return;
    }
    if (addFeeKind === 'extra' && !title) {
      showAlert($('#approveAlert'), 'danger', 'Enter a title for the extra fee.');
      return;
    }
    if (addFeeKind === 'school') {
      var exists = invoiceItems.some(function (it) {
        return parseInt(it.fee_type, 10) === 0 && parseInt(it.term_id, 10) === term;
      });
      if (exists) {
        showAlert($('#approveAlert'), 'danger', 'School fees for that term are already listed. Edit the received amount on the existing row.');
        return;
      }
      var existingMeta = schoolFeeTerms[term] || schoolFeeTerms[String(term)];
      invoiceItems.push({
        id: existingMeta ? existingMeta.id : 0,
        fee_type: 0,
        category: <?= json_encode(lang('app.schoolFees')) ?>,
        label: <?= json_encode(lang('app.schoolFees')) ?>,
        term: TERM_LABELS[term] || ('Term ' + term),
        term_id: term,
        expected: amount,
        paid: 0,
        remain: amount,
        is_new: true,
        title: <?= json_encode(lang('app.schoolFees')) ?>
      });
    } else {
      newItemSeq += 1;
      invoiceItems.push({
        id: 0,
        fee_type: 1,
        category: <?= json_encode(lang('app.extraFees')) ?>,
        label: title,
        term: TERM_LABELS[term] || ('Term ' + term),
        term_id: term,
        expected: amount,
        paid: 0,
        remain: amount,
        is_new: true,
        title: title
      });
    }
    hideAddFeePanel();
    $('#approveAlert').addClass('d-none').text('');
    feInvoiceRenderItems(invoiceItems);
  }

  // APPROVE
  $(document).on('click', '.approveBtn', function () {
    var appId = $(this).data('id') || '';
    var name  = $(this).data('name') || '';
    $('#approveAppId').val(appId);
    $('#approveClassId').val('');
    $('#approveName').text(name);
    $('#approveAlert').addClass('d-none').removeClass('alert-success alert-danger').text('');
    $('#approveStructure').html('<div class="pr-place-chip"><span>Placement</span><strong>Loading…</strong></div>');
    $('#approveConfirmBtn').prop('disabled', true).text('Save payment & approve');
    $('#feInvoiceLoading').show();
    $('#feInvoiceWrap').hide();
    $('#feInvoicePaymentMode').val('');
    $('#feInvoiceDueDate').val('');
    $('#feInvoiceSlipRef').val('');
    hideAddFeePanel();
    invoiceItems = [];
    schoolFeeTerms = {};
    feToggleSlipRef();

    ajaxWithIndexFallback({
      url: APPROVE_INFO_API + '/' + encodeURIComponent(appId),
      method: 'GET',
      dataType: 'json'
    }).done(function(res){
      $('#feInvoiceLoading').hide();
      if(res && res.structure){
        renderPlacement(res);
        if (res.defaultClassId) {
          $('#approveClassId').val(res.defaultClassId);
        } else {
          showAlert($('#approveAlert'), 'danger', 'No matching class found for this registration. Create the class for this level/department first.');
        }
        schoolFeeTerms = res.schoolFeeTerms || {};
        if (res.currentTerm) {
          $('#prAddTerm').val(String(res.currentTerm));
        }
        feInvoiceRenderItems(res.items || []);
        $('#feInvoiceWrap').show();
        feInvoiceUpdateTotal();
      } else {
        $('#approveStructure').html('<div class="pr-place-chip"><span>Placement</span><strong>Could not load</strong></div>');
        showAlert($('#approveAlert'), 'danger', (res && res.error) ? res.error : 'Failed to load application placement.');
      }
    }).fail(function(){
      $('#feInvoiceLoading').hide();
      $('#approveStructure').html('<div class="pr-place-chip"><span>Placement</span><strong>Failed to load</strong></div>');
      showAlert($('#approveAlert'), 'danger', 'Failed to load application placement.');
    });

    $('#approveRegistrationModal').modal('show');
  });

  $(document).on('change', '#approveRegistrationModal .fe-inv-check', function () {
    var $row = $(this).closest('tr');
    var $amt = $row.find('.fe-inv-amount');
    if ($(this).is(':checked')) {
      $amt.prop('disabled', false);
      if (!$amt.val()) {
        $amt.val($row.data('remain'));
      }
      $amt.focus().select();
    } else {
      $amt.prop('disabled', true).val('');
    }
    feInvoiceUpdateTotal();
  });

  $(document).on('input', '#approveRegistrationModal .fe-inv-amount', feInvoiceUpdateTotal);
  $('#feInvoicePaymentMode').on('change', feToggleSlipRef);
  $('#feInvoiceSlipRef').on('input', feInvoiceUpdateTotal);

  $('#feInvSelectAll').on('click', function () {
    $('#feInvoiceBody .fe-inv-check').each(function () {
      $(this).prop('checked', true).trigger('change');
    });
  });

  $('#feInvFillBalance').on('click', function () {
    $('#feInvoiceBody .fe-inv-check:checked').each(function () {
      var $row = $(this).closest('tr');
      $row.find('.fe-inv-amount').val($row.data('remain'));
    });
    feInvoiceUpdateTotal();
  });

  $('#prAddSchoolFeeBtn').on('click', function () { showAddFeePanel('school'); });
  $('#prAddExtraFeeBtn').on('click', function () { showAddFeePanel('extra'); });
  $('#prAddFeeCancel').on('click', hideAddFeePanel);
  $('#prAddFeeSave').on('click', addInvoiceItem);

  $(document).on('click', '#approveConfirmBtn', function (e) {
    e.preventDefault(); e.stopPropagation();
    var appId = $('#approveAppId').val();
    var classId = $('#approveClassId').val();
    if (!appId) { showAlert($('#approveAlert'), 'danger', 'Missing application id.'); return; }
    if (!classId) { showAlert($('#approveAlert'), 'danger', 'No class matched from registration.'); return; }

    var mode = $('#feInvoicePaymentMode').val();
    if (!mode) { showAlert($('#approveAlert'), 'danger', 'Select payment mode.'); return; }
    var slipRef = ($('#feInvoiceSlipRef').val() || '').trim();
    if (!slipRef) {
      showAlert($('#approveAlert'), 'danger', <?= json_encode(lang('app.slipReferenceRequired')) ?>);
      return;
    }

    var payments = [];
    var hasError = false;
    $('#feInvoiceBody .fe-inv-row').each(function () {
      var $cb = $(this).find('.fe-inv-check');
      if (!$cb.is(':checked')) return;
      var idx = parseInt($(this).data('index'), 10);
      var item = invoiceItems[idx];
      if (!item) return;
      var amount = parseFloat($(this).find('.fe-inv-amount').val()) || 0;
      var max = parseFloat($(this).data('remain')) || 0;
      if (amount < 2) return;
      if (amount > max + 0.001) {
        showAlert($('#approveAlert'), 'danger', 'Amount exceeds balance for ' + (item.label || 'item') + '.');
        hasError = true;
        return false;
      }
      payments.push({
        id: item.id || 0,
        fee_type: item.fee_type,
        amount: amount,
        expected: item.expected,
        term_id: item.term_id,
        title: item.title || item.label || ''
      });
    });
    if (hasError) return;
    if (!payments.length) {
      showAlert($('#approveAlert'), 'danger', 'Select at least one item and enter the received amount.');
      return;
    }

    $('#approveConfirmBtn').prop('disabled', true).text('Saving payment…');
    ajaxWithIndexFallback({
      url: APPROVE_POST_API,
      method: 'POST',
      dataType: 'json',
      data: withCsrf({
        applicationId: appId,
        classId: classId,
        paymentMode: mode,
        dueDate: $('#feInvoiceDueDate').val(),
        slipRef: slipRef,
        payments: JSON.stringify(payments)
      })
    })
    .done(function (res) {
      if (res && res.success) {
        showAlert($('#approveAlert'), 'success', res.success || 'Payment recorded and applicant approved.');
        if (res.url || res.print_url) {
          try {
            var w = window.open(res.url || res.print_url, '_blank', 'width=420,height=720');
            if (w) w.focus();
          } catch (_) {}
        }
        setTimeout(function(){
          $('#approveRegistrationModal').modal('hide');
          removePendingRow(appId);
          prependRecentAction(res.action_label || 'Approved', res.subject || $('#approveName').text(), res.actor || financeActorName, 'approved');
        }, 700);
      } else {
        showAlert($('#approveAlert'), 'danger', (res && (res.error||res.message)) ? (res.error||res.message) : 'Approval failed.');
        $('#approveConfirmBtn').prop('disabled', false).text('Save payment & approve');
      }
    })
    .fail(function () {
      showAlert($('#approveAlert'), 'danger', 'Server error during approval.');
      $('#approveConfirmBtn').prop('disabled', false).text('Save payment & approve');
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
        prependRecentAction(res.action_label || 'Rejected', res.subject || $('#rejectName').text(), res.actor || financeActorName, 'rejected');
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
        prependRecentAction(res.action_label || 'Deleted', res.subject || $('#deleteName').text(), res.actor || financeActorName, 'deleted');
      } else {
        showAlert($('#deleteAlert'), 'danger', (res && res.error) ? res.error : 'Delete failed.');
        $('#deleteConfirmBtn').prop('disabled', false).text('Delete permanently');
      }
    }).fail(function(){
      showAlert($('#deleteAlert'), 'danger', 'Server error during delete.');
      $('#deleteConfirmBtn').prop('disabled', false).text('Delete permanently');
    });
  });

  $('#pendingMobileSearch').on('input', function () {
    var q = String($(this).val() || '').toLowerCase().trim();
    $('#pendingCards .pending-card').each(function () {
      var hay = String($(this).data('search') || $(this).text()).toLowerCase();
      $(this).toggle(!q || hay.indexOf(q) !== -1);
    });
  });

})(jQuery);
</script>
