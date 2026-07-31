<div class="mb-3"><button class="btn btn-success" data-toggle="modal" data-target="#mdlPeriod"><i class="fa fa-plus"></i> Add period</button></div>
<div class="card"><div class="card-body">
<table class="table table-bordered table-striped" id="tblPeriods">
<thead><tr><th>Branch</th><th>Title</th><th>Type</th><th>Start</th><th>End</th><th>Status</th></tr></thead>
<tbody>
<?php foreach ($periods as $p) { ?>
<tr><td><?= esc($p['branch_name'] ?? '—'); ?></td><td><?= esc($p['title']); ?></td><td><?= esc($p['period_type']); ?></td>
<td><?= esc($p['start_date']); ?></td><td><?= esc($p['end_date']); ?></td><td><span class="badge badge-secondary"><?= esc($p['status']); ?></span></td></tr>
<?php } ?>
</tbody></table></div></div>
<div class="modal fade" id="mdlPeriod"><div class="modal-dialog"><form class="modal-content" id="frmPeriod">
<div class="modal-header"><h5>Budget period</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
<div class="modal-body">
<div class="form-group"><label>Branch</label><select name="branch_id" class="form-control"><?php foreach ($branches as $b) { ?><option value="<?= (int)$b['id']; ?>"><?= esc($b['display_name'] ?? $b['name']); ?></option><?php } ?></select></div>
<div class="form-group"><label>Title</label><input class="form-control" name="title" required placeholder="FY 2026-2027"></div>
<div class="form-group"><label>Type</label><select name="period_type" class="form-control"><option value="annual">Annual</option><option value="termly">Termly</option><option value="monthly">Monthly</option></select></div>
<div class="form-row"><div class="col form-group"><label>Start</label><input type="date" class="form-control" name="start_date" required></div>
<div class="col form-group"><label>End</label><input type="date" class="form-control" name="end_date" required></div></div>
<div class="form-group"><label>Status</label><select name="status" class="form-control"><option value="draft">Draft</option><option value="open">Open</option><option value="closed">Closed</option></select></div>
</div>
<div class="modal-footer"><button type="submit" class="btn btn-primary">Save</button></div>
</form></div></div>
<script>$(function(){ if($.fn.DataTable) $('#tblPeriods').DataTable(); $('#frmPeriod').on('submit',function(e){e.preventDefault();$.post('<?= base_url('budget/save_period'); ?>',$(this).serialize(),function(r){if(r.error){toastada.error(r.error);return;}toastada.success(r.success);location.reload();},'json');});});</script>
