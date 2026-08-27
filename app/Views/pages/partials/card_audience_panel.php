<?php
/**
 * Card audience panel (student or staff).
 *
 * @var string $audience student|staff|visitor
 * @var string $title
 * @var string $foldId
 * @var bool $foldOpen
 * @var string $cardTemplate
 * @var string $cardOri
 * @var string $cardBgMode
 * @var bool $hasBk
 * @var string $bkUrl
 * @var string $bgField card_background|sf_card_background
 * @var string $imgId
 * @var string $zoneId
 * @var string $clrId
 * @var string $badgeLabel
 * @var array $cardTemplates
 * @var string $layoutJson
 * @var string $fallbackImg
 * @var string $previewSchool
 * @var string $previewHeader1
 * @var string $previewHeader2
 * @var string $previewMoto
 * @var string $previewHead
 * @var string $previewMain
 * @var string $photoPlaceholder
 * @var string $logo
 * @var string $sigHead
 * @var string $fieldLabelsJson
 * @var string $defaultsJson
 * @var string $sampleValsJson
 */
$audience = $audience ?? 'student';
$prefix = $audience === 'staff' ? 'sf' : ($audience === 'visitor' ? 'vi' : 'st');
$isVisitor = $audience === 'visitor';
$isLandscapeOnly = $isVisitor;
if ($isLandscapeOnly) {
	$cardOri = 'landscape';
}
$audienceIcon = $audience === 'staff' ? 'fa-briefcase' : ($isVisitor ? 'fa-users' : 'fa-graduation-cap');
$oriName = $prefix . '_card_orientation';
$bgModeName = $prefix . '_card_bg_mode';
$tplChoiceId = $prefix . '_card_template_choice';
$oriChoiceId = $prefix . '_card_orientation_choice';
$bgModeChoiceId = $prefix . '_card_bg_mode_choice';
$aiPanelId = $prefix . '_card_ai_panel';
$aiBtnId = $prefix . '_btn_generate_card_bg';
$aiStatusId = $prefix . '_card_ai_status';
$liveId = $prefix . '_card_live_preview';
$canvasId = $prefix . 'EditorCanvas';
$bgLiveId = $prefix . 'LiveBg';
$itemsId = $prefix . 'EditorItems';
$togglesId = $prefix . 'FieldToggles';
$saveBtnId = $prefix . '_btn_save_card_layout';
$resetBtnId = $prefix . '_btn_reset_card_layout';
$statusId = $prefix . '_card_layout_status';
$bootId = $prefix . 'CardLayoutBoot';
$foldOpen = !empty($foldOpen);
$brandValues = is_array($brandValues ?? null) ? $brandValues : [];
$brandFields = is_array($brandFields ?? null) ? $brandFields : [];
?>
<div class="ss-inner-fold card-audience" data-audience="<?= esc($audience, 'attr'); ?>">
	<button type="button" class="ss-inner-fold-btn<?= $foldOpen ? '' : ' collapsed'; ?>"
			data-toggle="collapse" data-target="#<?= esc($foldId, 'attr'); ?>"
			aria-expanded="<?= $foldOpen ? 'true' : 'false'; ?>">
		<span><i class="fa <?= esc($audienceIcon, 'attr'); ?>"></i> <?= esc($title); ?></span>
		<i class="fa fa-chevron-down ss-inner-chevron"></i>
	</button>
	<div id="<?= esc($foldId, 'attr'); ?>" class="collapse<?= $foldOpen ? ' show' : ''; ?>" data-parent="#cardAudienceAcc">
		<div class="ss-inner-fold-body">
			<?php
			$brandTitle = ($audience === 'staff' ? 'Staff' : ($isVisitor ? 'Visitor' : 'Student')) . ' card branding';
			$h1Field = $brandFields['header1'] ?? ($audience === 'staff' ? 'sf_header_text_1' : ($isVisitor ? 'vi_header_text_1' : 'header_text_1'));
			$h2Field = $brandFields['header2'] ?? ($audience === 'staff' ? 'sf_header_text_2' : ($isVisitor ? 'vi_header_text_2' : 'header_text_2'));
			$capField = $brandFields['capitalize'] ?? ($audience === 'staff' ? 'sf_capitalize' : ($isVisitor ? 'vi_capitalize' : 'capitalize'));
			$hcField = $brandFields['header_color'] ?? ($audience === 'staff' ? 'sf_header_color' : ($isVisitor ? 'vi_header_color' : 'header_color'));
			$mcField = $brandFields['main_color'] ?? ($audience === 'staff' ? 'sf_main_color' : ($isVisitor ? 'vi_main_color' : 'main_color'));
			$fcField = $brandFields['footer_color'] ?? ($audience === 'staff' ? 'sf_footer_color' : ($isVisitor ? 'vi_footer_color' : 'footer_color'));
			$h1Val = trim((string)($brandValues['header1'] ?? ''));
			$h2Val = trim((string)($brandValues['header2'] ?? ''));
			$capVal = (int)($brandValues['capitalize'] ?? 0);
			$hcVal = preg_match('/^#[0-9A-Fa-f]{3,8}$/', trim((string)($brandValues['header_color'] ?? '')))
				? trim($brandValues['header_color']) : '#0a66b7';
			$mcVal = preg_match('/^#[0-9A-Fa-f]{3,8}$/', trim((string)($brandValues['main_color'] ?? '')))
				? trim($brandValues['main_color']) : '#0a66b7';
			$fcVal = preg_match('/^#[0-9A-Fa-f]{3,8}$/', trim((string)($brandValues['footer_color'] ?? '')))
				? trim($brandValues['footer_color']) : '#000000';
			?>
			<div class="ss-shared-brand ss-card-brand">
				<h5><?= esc($brandTitle); ?></h5>
				<p class="text-muted" style="font-size:.9rem;margin:-.25rem 0 1rem;">
					Header text 1 &amp; 2 auto-fill from <b>Basic school info</b>: line 1 = Tel · Email, line 2 = Website · Address.
				</p>
				<div class="row">
					<div class="col-md-6">
						<div class="form-group">
							<label><?= lang("app.head1"); ?> <span class="text-muted" style="font-weight:500;font-size:.8em;">(Tel · Email)</span></label>
							<div class="ss-header-autofill" data-header-slot="1" title="From Basic school info">
								<?= $h1Val !== '' ? esc($h1Val) : '<span class="text-muted">Add phone or email in Basic school info</span>'; ?>
							</div>
							<input type="hidden" data-value="<?= esc($h1Val, 'attr'); ?>" data-target="<?= esc($h1Field, 'attr'); ?>"
								   class="spedit ss-header-sync" value="<?= esc($h1Val, 'attr'); ?>">
						</div>
						<div class="form-group">
							<label><?= lang("app.head2"); ?> <span class="text-muted" style="font-weight:500;font-size:.8em;">(Website · Address)</span></label>
							<div class="ss-header-autofill" data-header-slot="2" title="From Basic school info">
								<?= $h2Val !== '' ? esc($h2Val) : '<span class="text-muted">Add website or address in Basic school info</span>'; ?>
							</div>
							<input type="hidden" data-value="<?= esc($h2Val, 'attr'); ?>" data-target="<?= esc($h2Field, 'attr'); ?>"
								   class="spedit ss-header-sync" value="<?= esc($h2Val, 'attr'); ?>">
						</div>
						<div class="form-group">
							<label><?= lang("app.capitals"); ?></label>
							<span data-value="<?= $capVal; ?>" data-type="status"
								  data-target="<?= esc($capField, 'attr'); ?>"
								  class="spedit">&nbsp;<?= $capVal === 1 ? "<span class='text-success'>".lang("app.enabled")."</span>"
										: "<span class='text-danger'>".lang("app.disabled")."</span>"; ?></span>
						</div>
					</div>
					<div class="col-md-6">
						<div class="form-group">
							<label><?= lang("app.headColor"); ?></label>
							<div class="ss-color-row">
								<input data-value="<?= esc($hcVal, 'attr'); ?>" data-type="color" data-target="<?= esc($hcField, 'attr'); ?>"
									   class="spedit ss-color-picker" value="<?= esc($hcVal, 'attr'); ?>">
								<span class="ss-color-hex" data-for="<?= esc($hcField, 'attr'); ?>"><?= esc($hcVal); ?></span>
							</div>
						</div>
						<div class="form-group">
							<label><?= lang("app.mainColor"); ?></label>
							<div class="ss-color-row">
								<input data-value="<?= esc($mcVal, 'attr'); ?>" data-type="color" data-target="<?= esc($mcField, 'attr'); ?>"
									   class="spedit ss-color-picker" value="<?= esc($mcVal, 'attr'); ?>">
								<span class="ss-color-hex" data-for="<?= esc($mcField, 'attr'); ?>"><?= esc($mcVal); ?></span>
							</div>
						</div>
						<div class="form-group">
							<label><?= lang("app.footerColor"); ?></label>
							<div class="ss-color-row">
								<input data-value="<?= esc($fcVal, 'attr'); ?>" data-type="color" data-target="<?= esc($fcField, 'attr'); ?>"
									   class="spedit ss-color-picker" value="<?= esc($fcVal, 'attr'); ?>">
								<span class="ss-color-hex" data-for="<?= esc($fcField, 'attr'); ?>"><?= esc($fcVal); ?></span>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="ss-card-presets">
				<div>
					<label style="font-weight:700;display:block;margin-bottom:.35rem;">CR80 PVC size <span style="font-weight:500;color:#64748b;font-size:.85em;">(85.6×54 mm RFID)</span></label>
					<?php if ($isLandscapeOnly): ?>
					<p style="font-size:.85rem;color:#64748b;margin:0 0 .6rem;">Visitor cards are <b>landscape only</b> (85.6 mm wide × 54 mm tall).</p>
					<input type="hidden" name="<?= esc($oriName, 'attr'); ?>" value="landscape">
					<?php else: ?>
					<p style="font-size:.85rem;color:#64748b;margin:0 0 .6rem;">Choose portrait or landscape — the background preview updates to match.</p>
					<div class="ss-choice card-ori-choice" id="<?= esc($oriChoiceId, 'attr'); ?>">
						<label class="<?= $cardOri === 'landscape' ? 'is-on' : ''; ?>">
							<input type="radio" name="<?= esc($oriName, 'attr'); ?>" value="landscape" <?= $cardOri === 'landscape' ? 'checked' : ''; ?>> Landscape
						</label>
						<label class="<?= $cardOri === 'portrait' ? 'is-on' : ''; ?>">
							<input type="radio" name="<?= esc($oriName, 'attr'); ?>" value="portrait" <?= $cardOri === 'portrait' ? 'checked' : ''; ?>> Portrait
						</label>
					</div>
					<?php endif; ?>

					<label style="font-weight:700;display:block;margin-bottom:.35rem;">Professional card template</label>
					<div class="ss-tpl-grid card-tpl-choice" id="<?= esc($tplChoiceId, 'attr'); ?>">
						<?php foreach ($cardTemplates as $tplId => $tplMeta): ?>
							<div class="ss-tpl-card<?= $cardTemplate === $tplId ? ' is-on' : ''; ?>"
								 data-template="<?= esc($tplId, 'attr'); ?>"
								 data-orientation="<?= esc($tplMeta['orientation'], 'attr'); ?>"
								 data-painted="<?= !empty($tplMeta['painted']) ? '1' : '0'; ?>">
								<strong><?= esc($tplMeta['label']); ?></strong>
								<span><?= esc($tplMeta['desc']); ?></span>
							</div>
						<?php endforeach; ?>
					</div>

					<div class="card-painted-note" style="display:none;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:.75rem .9rem;font-size:.88rem;color:#1e40af;margin-bottom:.75rem;">
						<div class="card-painted-curve">
						<div style="margin-bottom:.55rem;">
							<i class="fa fa-paint-brush"></i> Built-in curve design — uploaded or AI backgrounds are never applied. Change <b>curve color</b> independently below.
						</div>
						<?php
						$pcField = $brandFields['paint_color'] ?? ($audience === 'staff' ? 'sf_paint_color' : ($isVisitor ? 'vi_paint_color' : 'paint_color'));
						$pcVal = preg_match('/^#[0-9A-Fa-f]{3,8}$/', trim((string)($brandValues['paint_color'] ?? '')))
							? trim($brandValues['paint_color']) : ($mcVal ?: '#1E6FD9');
						?>
						<div class="form-group" style="margin:0;">
							<label style="font-weight:700;color:#0f172a;display:block;margin-bottom:.35rem;">
								Smart curve color <span class="text-muted" style="font-weight:500;font-size:.85em;">(background design only)</span>
							</label>
							<div class="ss-color-row" style="display:flex;align-items:center;gap:.6rem;">
								<input data-value="<?= esc($pcVal, 'attr'); ?>" data-type="color" data-target="<?= esc($pcField, 'attr'); ?>"
									   class="spedit ss-color-picker card-paint-color" value="<?= esc($pcVal, 'attr'); ?>">
								<span class="ss-color-hex" data-for="<?= esc($pcField, 'attr'); ?>"><?= esc($pcVal); ?></span>
								<span class="text-muted" style="font-size:.8rem;">Independent from Header / Main / Footer colors</span>
							</div>
						</div>
						</div>
						<div class="card-wisdom-note" style="display:none;">
							<i class="fa fa-id-card"></i> Wisdom Ribbon prints at CR80 RFID size (85.6×54 mm). Nursery/Primary cards show <b>Wisdom School Musanze</b>; all other classes show <b>Wisdom High School</b>.
						</div>
					</div>

					<div class="card-bg-tools">
						<label style="font-weight:700;display:block;margin-bottom:.35rem;">Smart background</label>
						<div class="ss-choice card-bg-mode-choice" id="<?= esc($bgModeChoiceId, 'attr'); ?>">
							<label class="<?= $cardBgMode === 'manual' ? 'is-on' : ''; ?>">
								<input type="radio" name="<?= esc($bgModeName, 'attr'); ?>" value="manual" <?= $cardBgMode === 'manual' ? 'checked' : ''; ?>> Manual upload
							</label>
							<label class="<?= $cardBgMode === 'smart' ? 'is-on' : ''; ?>">
								<input type="radio" name="<?= esc($bgModeName, 'attr'); ?>" value="smart" <?= $cardBgMode === 'smart' ? 'checked' : ''; ?>> Smart AI
							</label>
						</div>

						<div class="ss-ai-box card-ai-panel" id="<?= esc($aiPanelId, 'attr'); ?>">
							<p style="margin:0 0 .5rem;font-size:.9rem;">
								AI builds a full-bleed CR80 background that fills the template, keeping text zones lighter for readability.
							</p>
							<button type="button" class="btn btn-info btn-sm btn-generate-card-bg" id="<?= esc($aiBtnId, 'attr'); ?>">
								<i class="fa fa-magic"></i> Generate 3 <?= esc($audience); ?> backgrounds
							</button>
							<button type="button" class="btn btn-outline-secondary btn-sm btn-regenerate-card-bg" style="display:none;margin-left:.35rem;">
								<i class="fa fa-refresh"></i> Regenerate
							</button>
							<span class="text-muted card-ai-status" id="<?= esc($aiStatusId, 'attr'); ?>" style="margin-left:.5rem;font-size:.85rem;"></span>
							<div class="ss-ai-proposals card-ai-proposals" id="<?= esc($prefix, 'attr'); ?>_ai_proposals" style="display:none;"></div>
						</div>
					</div>
				</div>

				<div class="ss-bg-previews ss-bg-previews-single">
					<div class="ss-upload-card">
						<h4><?= esc($title); ?> background</h4>
						<div class="ss-bg-frame <?= $cardOri === 'portrait' ? 'is-portrait' : 'is-landscape'; ?>" data-bg-frame>
							<img src="<?= esc($bkUrl, 'attr'); ?>" id="<?= esc($imgId, 'attr'); ?>"
								 class="ss-upload-preview ss-bg-preview-img<?= $hasBk ? '' : ' is-empty'; ?>"
								 alt="<?= esc($title); ?> background" data-fallback="<?= esc($fallbackImg, 'attr'); ?>"
								 onerror="this.onerror=null;this.src=this.dataset.fallback;this.classList.add('is-empty');">
						</div>
						<input type="file" class="in_card_backg" style="display:none"
							   data-href="<?= esc($bgField, 'attr'); ?>"
							   data-imageview="#<?= esc($imgId, 'attr'); ?>"
							   data-target="#<?= esc($zoneId, 'attr'); ?>">
						<div class="ss-upload-zone dv_select_img_backg" id="<?= esc($zoneId, 'attr'); ?>">
							<p><?= lang("app.uploadBackground"); ?></p>
							<span class="text-muted"><?= lang("app.sizeNeeded"); ?></span>
						</div>
						<span class="lnk text-danger btn-clear-bg" id="<?= esc($clrId, 'attr'); ?>"
							  style="font-weight:500;cursor:pointer;<?= $hasBk ? '' : 'display:none'; ?>"
							  data-target-field="<?= esc($bgField, 'attr'); ?>"
							  data-imageview="#<?= esc($imgId, 'attr'); ?>">
							<i class="fa fa-times"></i> <?= lang("app.clearBackground"); ?>
						</span>
					</div>
				</div>
			</div>

			<div class="ss-live-wrap card-live-preview" id="<?= esc($liveId, 'attr'); ?>"
				 data-audience="<?= esc($audience, 'attr'); ?>"
				 data-school="<?= esc($previewSchool, 'attr'); ?>"
				 data-header1="<?= esc($previewHeader1, 'attr'); ?>"
				 data-header2="<?= esc($previewHeader2, 'attr'); ?>"
				 data-moto="<?= esc($previewMoto, 'attr'); ?>"
				 data-head="<?= esc($previewHead, 'attr'); ?>"
				 data-photo="<?= esc($photoPlaceholder, 'attr'); ?>"
				 data-logo="<?= esc($logo, 'attr'); ?>"
				 data-sig="<?= esc($sigHead, 'attr'); ?>"
				 data-fallback="<?= esc($fallbackImg, 'attr'); ?>"
				 data-main="<?= esc($previewMain, 'attr'); ?>"
				 data-paint="<?= esc($previewPaint ?? ($brandValues['paint_color'] ?? $previewMain), 'attr'); ?>"
				 data-badge="<?= esc($badgeLabel, 'attr'); ?>"
				 data-year="<?= esc($previewYear ?? '', 'attr'); ?>">
				<h5><i class="fa fa-magic"></i> Professional CR80 preview — matches <?= esc($audience); ?> card PDF</h5>
				<div class="ss-editor-toolbar">
					<button type="button" class="btn btn-success btn-sm btn-save-card-layout" id="<?= esc($saveBtnId, 'attr'); ?>"><i class="fa fa-save"></i> Save template</button>
					<button type="button" class="btn btn-outline-light btn-sm btn-reset-card-layout" id="<?= esc($resetBtnId, 'attr'); ?>"><i class="fa fa-undo"></i> Reset to template default</button>
					<span class="text-muted card-layout-status" id="<?= esc($statusId, 'attr'); ?>" style="font-size:.85rem;color:#94a3b8;"></span>
				</div>
				<div class="ss-editor-canvas-wrap">
					<div class="ss-editor-canvas <?= $cardOri === 'portrait' ? 'is-portrait' : 'is-landscape'; ?>" id="<?= esc($canvasId, 'attr'); ?>">
						<div class="ss-ed-bg" id="<?= esc($bgLiveId, 'attr'); ?>" style="<?= $hasBk ? "background-image:url('" . esc($bkUrl, 'attr') . "?v=" . time() . "');background-color:#fff" : 'background:#ffffff'; ?>"></div>
						<div class="ss-ed-wash"></div>
						<div class="ss-editor-items" id="<?= esc($itemsId, 'attr'); ?>"></div>
					</div>
				</div>
				<div class="ss-field-toggles card-field-toggles" id="<?= esc($togglesId, 'attr'); ?>"></div>
				<p class="ss-live-note">Tick only DB fields to show. Header text 1, Header text 2, and school motto footer are always reserved. Drag fields, then <b>Save template</b>.</p>
				<script type="application/json" class="card-layout-boot" id="<?= esc($bootId, 'attr'); ?>"><?= str_replace('</', '<\/', $layoutJson); ?></script>
				<script type="application/json" class="card-sample-boot"><?= str_replace('</', '<\/', $sampleValsJson ?? '{}'); ?></script>
				<script type="application/json" class="card-labels-boot"><?= str_replace('</', '<\/', $fieldLabelsJson ?? '{}'); ?></script>
				<script type="application/json" class="card-defaults-boot"><?= str_replace('</', '<\/', $defaultsJson ?? '{}'); ?></script>
			</div>
		</div>
	</div>
</div>
