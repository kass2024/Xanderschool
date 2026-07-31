<div class="card"><div class="card-body"><table class="table table-sm table-striped" id="tblAudit"><thead><tr><th>When</th><th>Entity</th><th>Action</th><th>Actor</th></tr></thead><tbody>
<?php foreach ($logs as $log) { ?><tr><td><?= esc($log['created_at']); ?></td><td><?= esc($log['entity_type'].' #'.$log['entity_id']); ?></td>
<td><?= esc($log['action']); ?></td><td>#<?= (int)$log['actor_id']; ?></td></tr><?php } ?>
</tbody></table></div></div>
<script>if($.fn.DataTable)$('#tblAudit').DataTable({order:[[0,'desc']]});</script>
