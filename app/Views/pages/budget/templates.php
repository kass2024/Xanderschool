<div class="row mb-3"><div class="col-md-6">
<div class="card"><div class="card-header">Upload Excel template (.xlsx)</div><div class="card-body">
<form id="frmUpload" enctype="multipart/form-data">
<div class="form-group"><label>Template name</label><input class="form-control" name="name" placeholder="Standard budget template"></div>
<div class="form-group"><label>Excel file</label><input type="file" class="form-control" name="template_file" accept=".xlsx,.xls" required></div>
<button type="submit" class="btn btn-primary">Upload &amp; preview</button>
</form></div></div></div></div>
<div class="card"><div class="card-body">
<table class="table table-bordered"><thead><tr><th>Name</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($templates as $t) { ?>
<tr><td><?= esc($t['name']); ?></td><td><?= esc($t['status']); ?></td>
<td><?php if ($t['status'] !== 'active') { ?><button class="btn btn-sm btn-success btn-activate" data-id="<?= (int)$t['id']; ?>">Activate</button><?php } ?></td></tr>
<?php } ?>
</tbody></table></div></div>
<script>
$('#frmUpload').on('submit',function(e){e.preventDefault();var fd=new FormData(this);$.ajax({url:'<?= base_url('budget/upload_template'); ?>',type:'POST',data:fd,processData:false,contentType:false,success:function(r){if(r.error){toastada.error(r.error);return;}toastada.success(r.success);location.reload();}});});
$('.btn-activate').on('click',function(){$.post('<?= base_url('budget/activate_template'); ?>',{template_id:$(this).data('id')},function(r){toastada.success(r.success);location.reload();},'json');});
</script>
