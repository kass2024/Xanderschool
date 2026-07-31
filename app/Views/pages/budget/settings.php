<div class="card"><div class="card-body"><form id="frmSettings">
<div class="form-row">
<div class="col-md-4 form-group"><label>Default currency</label><input class="form-control" name="default_currency" value="<?= esc($settings['default_currency'] ?? 'RWF'); ?>"></div>
<div class="col-md-4 form-group"><label>Headteacher approval</label><select name="headteacher_approval_mode" class="form-control">
<option value="evidence" <?= ($settings['headteacher_approval_mode'] ?? '') === 'evidence' ? 'selected' : ''; ?>>Evidence upload only</option>
<option value="system" <?= ($settings['headteacher_approval_mode'] ?? '') === 'system' ? 'selected' : ''; ?>>System approval in app</option>
</select></div>
<div class="col-md-4 form-group"><label>Utilization alert %</label><input type="number" step="0.01" class="form-control" name="budget_utilization_alert_pct" value="<?= esc($settings['budget_utilization_alert_pct'] ?? '80'); ?>"></div>
</div>
<div class="form-group"><label><input type="checkbox" name="ai_enabled" value="1" <?= !empty($settings['ai_enabled']) ? 'checked' : ''; ?>> Enable AI suggestions (requires API key)</label></div>
<button type="submit" class="btn btn-primary">Save settings</button>
</form></div></div>
<script>$('#frmSettings').on('submit',function(e){e.preventDefault();$.post('<?= base_url('budget/save_settings'); ?>',$(this).serialize(),function(r){toastada.success(r.success||'Saved');},'json');});</script>
