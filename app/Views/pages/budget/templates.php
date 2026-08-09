<div class="row mb-3">
<div class="col-lg-7">
<div class="card border-primary"><div class="card-header bg-primary text-white">Secondary school budget template</div><div class="card-body">
<p class="mb-2">Official template with <strong>Dashboard</strong>, <strong>Budget Summary</strong>, <strong>Detailed Budget</strong>, <strong>Fees &amp; Enrollment</strong>, <strong>Actuals</strong>, and <strong>Monthly Cash Flow</strong> sheets.</p>
<ol class="small pl-3 mb-3">
<li>Install the template below (one click).</li>
<li>Create a budget period under the <em>Periods</em> tab.</li>
<li>Start a new budget — lines load from the template automatically.</li>
</ol>
<a class="btn btn-outline-primary mr-2" href="<?= base_url('budget/download_official_template'); ?>"><i class="fa fa-download"></i> Download Excel template</a>
<?php if (!empty($can_install_official)) { ?>
<button type="button" class="btn btn-success" id="btnInstallOfficial"><i class="fa fa-magic"></i> Install official template</button>
<?php } ?>
</div></div></div>
<div class="col-lg-5">
<div class="card"><div class="card-header">Optional: upload Excel backup</div><div class="card-body">
<p class="small text-muted">Branches prepare budgets <strong>entirely on the web</strong>. Excel upload is only for admins migrating old files.</p>
<form id="frmUpload" enctype="multipart/form-data">
<div class="form-group"><label>Template name</label><input class="form-control" name="name" value="Secondary School Budget Template"></div>
<div class="form-group"><label>Excel file</label><input type="file" class="form-control" name="template_file" accept=".xlsx,.xls" required></div>
<button type="submit" class="btn btn-primary">Upload &amp; import</button>
</form></div></div></div></div>

<div class="card"><div class="card-body">
<table class="table table-bordered table-sm"><thead><tr><th>Name</th><th>Status</th><th>Lines</th><th></th></tr></thead><tbody>
<?php if (empty($templates)) { ?><tr><td colspan="4" class="text-muted">No templates yet. Install or upload the Wisdom workbook.</td></tr><?php } ?>
<?php foreach ($templates as $t) { ?>
<tr><td><?= esc($t['name']); ?></td><td><span class="badge badge-<?= $t['status']==='active'?'success':'secondary'; ?>"><?= esc($t['status']); ?></span></td>
<td><?= (int)($t['line_count'] ?? 0); ?></td>
<td><?php if ($t['status'] !== 'active') { ?><button class="btn btn-sm btn-success btn-activate" data-id="<?= (int)$t['id']; ?>">Activate</button><?php } else { ?><span class="text-success small">In use</span><?php } ?></td></tr>
<?php } ?>
</tbody></table></div></div>
<script>
$('#frmUpload').on('submit',function(e){e.preventDefault();var fd=new FormData(this);$.ajax({url:'<?= base_url('budget/upload_template'); ?>',type:'POST',data:fd,processData:false,contentType:false,success:function(r){if(r.error){toastada.error(r.error);return;}toastada.success(r.success+(r.line_count?' ('+r.line_count+' lines)':''));location.reload();}});});
$('.btn-activate').on('click',function(){$.post('<?= base_url('budget/activate_template'); ?>',{template_id:$(this).data('id')},function(r){toastada.success(r.success);location.reload();},'json');});
$('#btnInstallOfficial').on('click',function(){$.post('<?= base_url('budget/install_official_template'); ?>',{},function(r){if(r.error){toastada.error(r.error);return;}toastada.success(r.success||'Installed');location.reload();},'json');});
</script>
