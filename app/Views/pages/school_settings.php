 <style>
	.settings span { font-weight: 600; }
	.spedit { min-width: 100px; cursor: pointer; display: inline-block; }
	.boxed { padding: 20px; border-radius: 5px; background: #e1e0e0; }
	.ihelp { font-size: 30pt; position: absolute; top: 10px; left: 59%; color: #333333; cursor: pointer; }
	span { font-weight: 600; }
	@media all and (max-width: 1249px) { .ihelp { left: 50%; color: #ffffff; } }

	/* Branding + card presets */
	.ss-brand-grid {
		display: grid;
		grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
		gap: 1rem;
		margin: 0 0 1.25rem;
		clear: both;
	}
	.ss-upload-card {
		position: relative;
		background: #fff;
		border: 1px solid #e2e8f0;
		border-radius: 12px;
		padding: 1rem;
		display: flex;
		flex-direction: column;
		gap: .75rem;
		min-height: 100%;
	}
	.ss-upload-card h4 {
		margin: 0;
		font-size: .95rem;
		font-weight: 650;
		color: #0f172a;
		padding-right: 1.5rem;
	}
	.ss-upload-card .ss-upload-del {
		position: absolute; right: 10px; top: 10px;
		color: #ef4444; cursor: pointer; font-size: .85rem;
	}
	.ss-upload-preview {
		width: 100%;
		height: 96px;
		object-fit: contain;
		background:
			linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
		border: 1px solid #e2e8f0;
		border-radius: 8px;
		padding: 6px;
	}
	.ss-upload-preview.is-empty {
		opacity: 1;
		object-fit: contain;
		padding: 0;
		background: #f8fafc;
	}
	.ss-live-wrap {
		margin-top: 1.25rem;
		padding: 1rem 1.1rem 1.2rem;
		background: #0f172a;
		border-radius: 14px;
		color: #fff;
		clear: both;
	}
	.ss-live-wrap h5 {
		margin: 0 0 .75rem;
		font-size: 1rem;
		font-weight: 650;
		color: #e2e8f0;
	}
	.ss-live-stage {
		display: flex;
		justify-content: center;
		align-items: center;
		min-height: 220px;
		padding: .5rem;
		background:
			radial-gradient(circle at 20% 20%, rgba(14,165,233,.18), transparent 40%),
			#111827;
		border-radius: 10px;
	}
	.ss-live-card {
		position: relative;
		overflow: hidden;
		border-radius: 10px;
		box-shadow: 0 12px 32px rgba(0,0,0,.35);
		background: #f8fafc;
		transition: width .25s ease, height .25s ease;
	}
	/* CR80 aspect 85.6:54 */
	.ss-live-card.is-landscape { width: min(100%, 428px); height: 270px; }
	.ss-live-card.is-portrait { width: min(100%, 216px); height: 342px; }
	.ss-live-card__bg {
		position: absolute; inset: 0;
		background-size: cover;
		background-position: center;
		background-color: #e0f2fe;
	}
	.ss-live-card__wash {
		position: absolute; inset: 0;
		background: linear-gradient(105deg, rgba(255,255,255,.4) 0%, rgba(255,255,255,.2) 52%, rgba(255,255,255,.1) 100%);
		pointer-events: none;
	}
	.ss-live-card.has-bg .ss-live-card__wash {
		background: linear-gradient(105deg, rgba(255,255,255,.35) 0%, rgba(255,255,255,.15) 55%, rgba(255,255,255,.05) 100%);
	}
	.ss-live-card.no-bg .ss-live-card__bg {
		background: #ffffff !important;
	}
	.ss-live-card__inner {
		position: relative; z-index: 1;
		height: 100%;
		padding: 10px 12px;
		box-sizing: border-box;
		display: flex;
		flex-direction: column;
	}
	.ss-live-card__top {
		display: flex; gap: 8px; align-items: center;
		border-bottom: 2px solid #0EA5E9;
		padding-bottom: 6px; margin-bottom: 6px;
	}
	.ss-live-card__top img {
		width: 42px; height: 42px; object-fit: contain;
		background: #fff; border-radius: 6px;
	}
	.ss-live-card__top .meta { flex: 1; text-align: center; min-width: 0; }
	.ss-live-card__top .meta strong {
		display: block; font-size: .78rem; color: #0f172a; line-height: 1.15;
	}
	.ss-live-card__top .meta span {
		display: block; font-size: .65rem; color: #475569; font-weight: 500;
	}
	.ss-live-card__badge {
		text-align: center; color: #fff; font-size: .68rem; font-weight: 700;
		padding: 4px 6px; border-radius: 4px; margin-bottom: 8px; letter-spacing: .03em;
	}
	.ss-live-card__body { display: flex; gap: 10px; flex: 1; min-height: 0; }
	.ss-live-card__photo {
		width: 78px; height: 90px; object-fit: cover;
		border-radius: 8px; border: 2px solid #0EA5E9; background: #fff; flex-shrink: 0;
	}
	.ss-live-card.is-portrait .ss-live-card__photo { width: 64px; height: 78px; }
	.ss-live-card__fields { font-size: .68rem; color: #0f172a; line-height: 1.35; }
	.ss-live-card__fields b { color: #0284c7; }
	.ss-live-card__foot {
		margin-top: auto; padding-top: 6px;
		display: flex; align-items: flex-end; justify-content: space-between; gap: 8px;
	}
	.ss-live-card__sig img { max-height: 28px; max-width: 90px; object-fit: contain; display: block; }
	.ss-live-card__sig small { color: #64748b; font-size: .6rem; display: block; border-top: 1px solid #94a3b8; margin-top: 2px; padding-top: 1px; }
	.ss-live-card__moto {
		flex: 1; text-align: center; font-size: .65rem; font-weight: 700;
		color: #fff; padding: 3px 6px; border-radius: 4px;
	}
	.ss-live-note { margin: .65rem 0 0; font-size: .8rem; color: #94a3b8; }
	.ss-tpl-grid {
		display: grid;
		grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
		gap: .65rem;
		margin: .35rem 0 1rem;
	}
	.ss-tpl-card {
		border: 1.5px solid #cbd5e1;
		border-radius: 10px;
		padding: .7rem .75rem;
		background: #fff;
		cursor: pointer;
		transition: border-color .2s, box-shadow .2s, background .2s;
	}
	.ss-tpl-card:hover { border-color: #38bdf8; }
	.ss-tpl-card.is-on {
		border-color: #0EA5E9;
		background: #e0f2fe;
		box-shadow: 0 0 0 2px rgba(14,165,233,.15);
	}
	.ss-tpl-card strong { display: block; font-size: .9rem; color: #0f172a; }
	.ss-tpl-card span { display: block; font-size: .72rem; color: #64748b; margin-top: .25rem; font-weight: 500; }
	.ss-editor-toolbar {
		display: flex; flex-wrap: wrap; gap: .5rem; align-items: center;
		margin: 0 0 .75rem;
	}
	.ss-editor-canvas-wrap {
		display: flex; justify-content: center; align-items: center;
		min-height: 280px; padding: .75rem;
		background: radial-gradient(circle at 20% 20%, rgba(14,165,233,.18), transparent 40%), #111827;
		border-radius: 10px;
		overflow: auto;
	}
	.ss-editor-canvas {
		position: relative;
		background: #fff;
		border-radius: 10px;
		box-shadow: 0 12px 32px rgba(0,0,0,.35);
		overflow: hidden;
		user-select: none;
	}
	/* Exact CR80 ratio so % matches PDF */
	.ss-editor-canvas.is-landscape { width: min(100%, 428px); height: 270px; border-radius: 12px; }
	.ss-editor-canvas.is-portrait { width: min(100%, 216px); height: 342px; border-radius: 12px; }
	.ss-editor-canvas .ss-ed-bg {
		position: absolute; inset: 0;
		background-size: 100% 100%;
		background-position: center;
		background-repeat: no-repeat;
	}
	.ss-editor-canvas .ss-ed-wash {
		position: absolute; inset: 0;
		background: transparent;
		pointer-events: none;
	}
	.ss-editor-canvas .ss-ed-paint {
		position: absolute; inset: 0;
		overflow: hidden;
		pointer-events: none;
	}
	.ss-ed-item {
		position: absolute;
		box-sizing: border-box;
		border: 1px dashed rgba(14,165,233,.55);
		background: rgba(255,255,255,.72);
		border-radius: 4px;
		padding: 2px 4px;
		cursor: grab;
		font-size: 10px;
		line-height: 1.15;
		overflow: hidden;
		color: #0f172a;
		z-index: 3;
	}
	.ss-ed-item.is-active { border-style: solid; border-color: #0EA5E9; box-shadow: 0 0 0 2px rgba(14,165,233,.25); z-index: 5; }
	.ss-ed-item.is-hidden { opacity: .25; border-style: dotted; }
	.ss-ed-item .ss-ed-label { font-weight: 700; color: #0284c7; margin-right: 3px; }
	.ss-ed-item[data-key="badge"] { background: rgba(14,165,233,.9); color: #fff; border: 0; border-radius: 0 !important; left: 0 !important; width: 100% !important; display: flex; align-items: center; justify-content: center; font-weight: 700; }
	.ss-ed-item[data-key="moto"] { background: rgba(14,165,233,.9); color: #fff; border: 0; display: flex; align-items: center; justify-content: center; font-weight: 700; }
	.ss-ed-item[data-key="photo"] { background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-weight: 600; overflow: hidden; }
	.ss-ed-item[data-key="photo"] img { width: 100% !important; height: 100% !important; max-width: none !important; max-height: none !important; object-fit: cover; pointer-events: none; }
	.ss-ed-item[data-key="logo"] {
		display: flex; align-items: center; justify-content: center;
		background: #ffffff !important;
		border: 1px solid rgba(15,23,42,.15);
		padding: 3px;
	}
	.ss-ed-item img { max-width: 100%; max-height: 100%; object-fit: contain; pointer-events: none; }
	.ss-editor-canvas {
		background: #ffffff !important;
	}
	.ss-editor-canvas .ss-ed-wash {
		background: transparent !important;
	}
	.ss-editor-canvas .ss-ed-item {
		color: #0f172a;
		border-color: rgba(14,165,233,.55);
		background: rgba(255,255,255,.55);
	}
	.ss-editor-canvas .ss-ed-item .ss-ed-label { color: #0284c7; }
	.ss-editor-canvas .ss-ed-item[data-key="logo"],
	.ss-editor-canvas .ss-ed-item[data-key="photo"] {
		background: #ffffff !important;
	}
	.ss-ai-proposals {
		display: grid;
		grid-template-columns: repeat(3, minmax(0, 1fr));
		gap: .65rem;
		margin-top: .75rem;
	}
	@media (max-width: 700px) {
		.ss-ai-proposals { grid-template-columns: 1fr; }
	}
	.ss-ai-proposal {
		border: 1.5px solid #cbd5e1;
		border-radius: 10px;
		padding: .5rem;
		background: #fff;
		cursor: pointer;
		text-align: center;
		transition: border-color .2s, box-shadow .2s;
	}
	.ss-ai-proposal:hover, .ss-ai-proposal.is-on {
		border-color: #0EA5E9;
		box-shadow: 0 0 0 2px rgba(14,165,233,.18);
	}
	.ss-ai-proposal .ss-bg-frame {
		margin: 0 auto .4rem;
		max-width: 100%;
	}
	.ss-ai-proposal img {
		width: 100%;
		height: 100%;
		object-fit: fill;
		border-radius: 6px;
		display: block;
	}
	.ss-ai-proposal strong {
		display: block;
		font-size: .8rem;
		color: #0f172a;
	}
	.ss-ai-proposal span {
		display: block;
		font-size: .72rem;
		color: #64748b;
		margin-top: .15rem;
	}
	.ss-field-toggles {
		display: flex; flex-wrap: wrap; gap: .35rem .6rem;
		margin-top: .75rem; max-height: 120px; overflow: auto;
	}
	.ss-field-toggles label {
		font-size: .78rem; font-weight: 500; margin: 0;
		display: inline-flex; align-items: center; gap: .25rem;
		color: #e2e8f0;
	}
	.ss-upload-zone {
		border: 1.5px dashed #94a3b8;
		border-radius: 8px;
		padding: .75rem .5rem;
		text-align: center;
		cursor: pointer;
		background: #f8fafc;
		transition: border-color .2s, background .2s;
	}
	.ss-upload-zone:hover { border-color: #0EA5E9; background: #f0f9ff; }
	.ss-upload-zone p { margin: 0; font-weight: 600; font-size: .9rem; color: #0f172a; }
	.ss-upload-zone .text-muted { font-size: .75rem; display: block; margin-top: .25rem; }
	.ss-card-presets { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }
	@media (max-width: 900px) { .ss-card-presets { grid-template-columns: 1fr; } }
	.ss-choice {
		display: flex; flex-wrap: wrap; gap: .5rem; margin: .35rem 0 1rem;
	}
	.ss-choice label {
		display: inline-flex; align-items: center; gap: .4rem;
		padding: .45rem .85rem; border: 1px solid #cbd5e1; border-radius: 8px;
		background: #fff; cursor: pointer; font-weight: 500; margin: 0;
	}
	.ss-choice label.is-on { border-color: #0EA5E9; background: #e0f2fe; color: #0369a1; }
	.ss-choice input { margin: 0; }
	.ss-ai-box {
		background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 10px;
		padding: .85rem 1rem; margin: .5rem 0 1rem;
	}
	.ss-ai-box .btn { margin-top: .4rem; }
	.ss-bg-previews { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
	.ss-bg-previews-single { grid-template-columns: 1fr; }
	@media (max-width: 700px) { .ss-bg-previews { grid-template-columns: 1fr; } }
	.ss-bg-frame {
		display: flex;
		align-items: center;
		justify-content: center;
		margin: 0 auto .75rem;
		padding: 10px;
		border-radius: 12px;
		background: radial-gradient(circle at 20% 20%, rgba(14,165,233,.12), transparent 45%), #f1f5f9;
		border: 1px solid #e2e8f0;
		transition: width .25s ease, height .25s ease;
	}
	.ss-bg-frame.is-landscape { width: min(100%, 428px); height: 270px; }
	.ss-bg-frame.is-portrait { width: min(100%, 216px); height: 342px; }
	.ss-bg-frame .ss-bg-preview-img {
		width: 100%;
		height: 100%;
		object-fit: fill;
		border-radius: 8px;
		padding: 0;
	}
	.ss-bg-frame .ss-bg-preview-img.is-empty {
		object-fit: contain;
		background: #fff;
	}
	.ss-inner-fold {
		border: 1px solid #e2e8f0;
		border-radius: 12px;
		overflow: hidden;
		margin-bottom: .75rem;
		background: #fff;
	}
	.ss-inner-fold-btn {
		width: 100%;
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: .75rem;
		border: 0;
		background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
		padding: .9rem 1rem;
		font-weight: 650;
		color: #0f172a;
		text-align: left;
		cursor: pointer;
	}
	.ss-inner-fold-btn span { display: inline-flex; align-items: center; gap: .5rem; }
	.ss-inner-fold-btn .ss-inner-chevron { color: #94a3b8; transition: transform .2s ease; }
	.ss-inner-fold-btn:not(.collapsed) .ss-inner-chevron { transform: rotate(180deg); color: #0284c7; }
	.ss-inner-fold-body { padding: 1rem 1.05rem 1.15rem; }
	#cardAudienceAcc { margin-top: .35rem; }
	.ss-shared-brand {
		margin: 0 0 1rem;
		padding: 1rem;
		border: 1px solid #e2e8f0;
		border-radius: 12px;
		background: #f8fafc;
	}
	.ss-shared-brand h5 {
		margin: 0 0 .75rem;
		font-size: .95rem;
		font-weight: 650;
		color: #0f172a;
	}

	/* Settings accordion */
	.settings-page-wrap {
		width: 100%;
		max-width: none;
		margin: 0;
		padding: 0 0 1.5rem;
		box-sizing: border-box;
	}
	.app-main__inner .settings-page-wrap,
	.app-inner-layout__content .settings-page-wrap {
		width: 100%;
	}
	#accordion.ss-accordion {
		display: flex;
		flex-direction: column;
		gap: .75rem;
	}
	/* .card required so Bootstrap 4 data-parent finds panels (>.card>.collapse) */
	#accordion.ss-accordion > .card.ss-acc-item {
		margin-bottom: 0;
	}
	.ss-acc-item {
		background: #fff;
		border: 1px solid #e2e8f0;
		border-radius: 14px;
		overflow: hidden;
		box-shadow: 0 1px 2px rgba(15,23,42,.04);
		transition: box-shadow .2s ease, border-color .2s ease;
	}
	.ss-acc-item.is-open {
		border-color: #cbd5e1;
		box-shadow: 0 8px 24px rgba(15,23,42,.08);
	}
	.ss-acc-item > .card-header {
		background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
		border: 0;
		padding: 0;
	}
	.ss-acc-item > .card-header .btn-link {
		color: #0f172a;
		text-decoration: none !important;
		padding: 1rem 1.15rem !important;
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: .75rem;
		width: 100%;
	}
	.ss-acc-item > .card-header .btn-link:hover,
	.ss-acc-item > .card-header .btn-link:focus {
		color: #0369a1;
		text-decoration: none !important;
	}
	.ss-acc-item > .card-header h5 {
		margin: 0 !important;
		font-size: 1rem;
		font-weight: 650;
		display: flex;
		align-items: center;
		gap: .55rem;
	}
	.ss-acc-item > .card-header h5 .ss-acc-ico {
		width: 28px; height: 28px; border-radius: 8px;
		display: inline-flex; align-items: center; justify-content: center;
		background: #e0f2fe; color: #0284c7; font-size: .85rem; flex-shrink: 0;
	}
	.ss-acc-chevron {
		color: #94a3b8; font-size: .85rem; transition: transform .2s ease;
	}
	.ss-acc-item.is-open .ss-acc-chevron { transform: rotate(180deg); color: #0284c7; }
	.ss-acc-item .card-body {
		padding: 1.15rem 1.25rem 1.35rem;
		background: #fff;
	}
	.ss-info-grid {
		display: grid;
		grid-template-columns: minmax(0, 1.6fr) minmax(260px, .7fr);
		gap: 1.5rem;
		clear: both;
		width: 100%;
	}
	@media (max-width: 900px) {
		.ss-info-grid { grid-template-columns: 1fr; }
	}
	.ss-field-list .form-group {
		margin-bottom: .85rem;
		padding-bottom: .75rem;
		border-bottom: 1px dashed #e2e8f0;
	}
	.ss-field-list .form-group:last-child { border-bottom: 0; }
	.ss-field-list label {
		display: block;
		font-size: .78rem;
		font-weight: 650;
		color: #64748b;
		text-transform: uppercase;
		letter-spacing: .03em;
		margin-bottom: .2rem;
	}
	.ss-term-card {
		background: linear-gradient(145deg, #0f172a 0%, #1e293b 100%);
		color: #e2e8f0;
		border-radius: 14px;
		padding: 1.15rem 1.25rem;
	}
	.ss-term-card h4 {
		margin: 0 0 .85rem;
		font-size: 1rem;
		font-weight: 650;
		color: #fff;
	}
	.ss-term-card label {
		display: block;
		margin-bottom: .45rem;
		font-weight: 500;
		color: #cbd5e1;
		font-size: .9rem;
	}
	.ss-term-card .ss-term-actions {
		margin-top: .9rem;
	}
	.ss-term-card .btn-change-term {
		background: #0284c7;
		border: 0;
		color: #fff;
		font-weight: 600;
		border-radius: 8px;
		padding: .45rem .85rem;
		font-size: .85rem;
	}
	.ss-term-card .btn-change-term:hover { background: #0369a1; color: #fff; }
	.ss-periods-box {
		margin-top: 1rem;
		padding-top: .85rem;
		border-top: 1px solid rgba(148,163,184,.35);
	}
	.ss-periods-box h5 {
		margin: 0 0 .65rem;
		font-size: .9rem;
		font-weight: 650;
		color: #f8fafc;
	}
	.ss-periods-box .ss-period-hint {
		font-size: .8rem;
		color: #94a3b8;
		margin-bottom: .75rem;
	}
	.ss-period-row {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: .75rem;
		padding: .55rem .65rem;
		margin-bottom: .4rem;
		border-radius: 8px;
		background: rgba(15,23,42,.45);
		border: 1px solid rgba(148,163,184,.2);
	}
	.ss-period-row.is-locked {
		border-color: rgba(248,113,113,.45);
		background: rgba(127,29,29,.25);
	}
	.ss-period-row .ss-period-label {
		font-size: .88rem;
		color: #e2e8f0;
		font-weight: 560;
	}
	.ss-period-row .ss-period-status {
		font-size: .75rem;
		margin-left: .4rem;
		color: #94a3b8;
	}
	.ss-period-row.is-locked .ss-period-status { color: #fca5a5; }
	.ss-period-row .btn-period-lock {
		border: 0;
		border-radius: 7px;
		padding: .3rem .65rem;
		font-size: .78rem;
		font-weight: 600;
		cursor: pointer;
		white-space: nowrap;
	}
	.ss-period-row .btn-lock { background: #b91c1c; color: #fff; }
	.ss-period-row .btn-unlock { background: #15803d; color: #fff; }
	.ss-periods-off {
		margin-top: .85rem;
		font-size: .82rem;
		color: #94a3b8;
	}

	.ss-edit-hint {
		display: flex;
		align-items: flex-start;
		gap: .75rem;
		margin: 0 0 1.1rem;
		padding: .85rem 1rem;
		border-radius: 12px;
		background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%);
		border: 1px solid #fdba74;
		color: #9a3412;
		font-size: .9rem;
		line-height: 1.4;
	}
	.ss-edit-hint i {
		font-size: 1.25rem;
		margin-top: .1rem;
		color: #ea580c;
	}
	.ss-edit-hint strong { color: #c2410c; }
	.ss-field-list .spedit {
		min-width: 140px;
		padding: .2rem .45rem;
		border-radius: 6px;
		border: 1px dashed #cbd5e1;
		background: #f8fafc;
		color: #0f172a;
		transition: background .15s, border-color .15s;
	}
	.ss-field-list .spedit:hover {
		border-color: #fb923c;
		background: #fff7ed;
		cursor: pointer;
	}
	.ss-field-list .spedit::after {
		content: " double-click";
		font-size: .68rem;
		font-weight: 600;
		color: #fb923c;
		opacity: 0;
		margin-left: .35rem;
		transition: opacity .15s;
	}
	.ss-field-list .spedit:hover::after { opacity: 1; }
	.ss-shared-brand .form-group label { font-weight: 600; display: block; margin-bottom: .25rem; }
	.ss-shared-brand .spedit {
		min-width: 180px;
		min-height: 1.6rem;
		padding: .25rem .5rem;
		border-radius: 6px;
		border: 1px dashed #cbd5e1;
		background: #f8fafc;
		color: #0f172a;
		cursor: pointer;
		display: inline-block;
		vertical-align: middle;
	}
	.ss-shared-brand .spedit:hover {
		border-color: #3b82f6;
		background: #eff6ff;
	}
	.ss-shared-brand .spedit.is-empty-hint { color: #94a3b8; font-style: italic; font-weight: 500; }
	.ss-header-autofill {
		display: block;
		width: 100%;
		min-height: 2.4rem;
		padding: .55rem .75rem;
		background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
		border: 1px dashed #94a3b8;
		border-radius: 8px;
		color: #0f172a;
		font-weight: 650;
		font-size: .92rem;
		line-height: 1.35;
		letter-spacing: .01em;
	}
	.ss-header-autofill .text-muted { font-weight: 500; font-style: italic; }
	.ss-editor-canvas .ss-ed-item[data-key="header1"],
	.ss-editor-canvas .ss-ed-item[data-key="header2"] {
		background: transparent !important;
		border-style: dashed;
		border-color: rgba(14,165,233,.35);
		font-weight: 600;
		letter-spacing: .02em;
		color: #334155;
	}
	.ss-editor-canvas .ss-ed-item[data-key="header1"] .ss-ed-label,
	.ss-editor-canvas .ss-ed-item[data-key="header2"] .ss-ed-label {
		display: none;
	}
	.ss-shared-brand .ss-color-row {
		display: flex;
		align-items: center;
		gap: .75rem;
	}
	.ss-shared-brand .ss-color-row .sp-replacer {
		border-radius: 6px;
		border: 1px solid #cbd5e1;
	}
	.ss-shared-brand .ss-color-hex {
		font-family: ui-monospace, Consolas, monospace;
		font-size: .9rem;
		color: #475569;
	}
</style>
<script src="<?=base_url('assets/plugins/spectrum/spectrum.js');?>"></script>
<link href="<?=base_url('assets/plugins/spectrum/spectrum.css');?>" type="text/css" rel="stylesheet"/>
<!-- edit tip shown inside Basic school info -->
<div class="settings settings-page-wrap" id="settings_section" data-id="<?= $settings['id']; ?>">
	<div id="accordion" class="accordion-wrapper mb-3 ss-accordion">
		<div class="card ss-acc-item">
			<div id="headingOne" class="card-header">
				<button type="button" data-toggle="collapse" data-target="#collapseOne1" aria-expanded="false"
						aria-controls="collapseOne1" class="text-left m-0 p-0 btn btn-link btn-block">
					<h5 class="m-0 p-0"><span class="ss-acc-ico"><i class="fa fa-building"></i></span><?= lang("app.basicSchoolInfo"); ?></h5><i class="fa fa-chevron-down ss-acc-chevron"></i>
				</button>
			</div>
			<div id="collapseOne1" aria-labelledby="headingOne" data-parent="#accordion" class="collapse">
				<div class="card-body">
					<div class="ss-edit-hint">
						<i class="fa fa-hand-pointer-o"></i>
						<div>
							<strong>Double-click any value to edit it.</strong>
							Press <strong>Enter</strong> or click outside to save, or <strong>Esc</strong> to cancel.
						</div>
					</div>
					<div class="ss-info-grid">
					<div class="ss-field-list">
						<div class="form-group">
							<label><?= lang("app.schoolName"); ?>:</label>
							<span data-value="<?= $settings['name']; ?>" data-target="name"
								  class="spedit">&nbsp;<?= $settings['name']; ?></span>
						</div>
						<div class="form-group">
							<label><?= lang("app.acronym"); ?>:</label>
							<span data-value="<?= $settings['acronym']; ?>" data-target="acronym"
								  class="spedit">&nbsp;<?= $settings['acronym']; ?></span>
						</div>
						<div class="form-group">
							<label><?= lang("app.sLogan"); ?>:</label>
							<span data-value="<?= $settings['slogan']; ?>" data-target="slogan"
								  class="spedit">&nbsp;<?= $settings['slogan']; ?></span>
						</div>
						<div class="form-group">
							<label><?= lang("app.phone"); ?>:</label>
							<span data-value="<?= $settings['phone']; ?>" data-target="phone"
								  class="spedit">&nbsp;<?= $settings['phone']; ?></span>
						</div>
						<div class="form-group">
							<label><?= lang("app.email"); ?>:</label>
							<span data-value="<?= $settings['email']; ?>" data-target="email"
								  class="spedit">&nbsp;<?= $settings['email']; ?></span>
						</div>
						<div class="form-group">
							<label><?= lang("app.headMaster"); ?>:</label>
							<span data-value="<?= $settings['head_master']; ?>" data-target="head_master"
								  class="spedit">&nbsp;<?= $settings['head_master']; ?></span>
						</div>
						<div class="form-group">
							<label><?= lang("app.website"); ?>:</label>
							<span data-value="<?= $settings['website']; ?>" data-target="website"
								  class="spedit">&nbsp;<?= $settings['website']; ?></span>
						</div>
						<div class="form-group">
							<label>P.O BOX:</label>
							<span data-value="<?= $settings['pobox']; ?>" data-target="pobox"
								  class="spedit">&nbsp;<?= $settings['pobox']; ?></span>
						</div>
						<div class="form-group">
							<label>Address:</label>
							<span data-value="<?= $settings['address']; ?>" data-target="address"
								  class="spedit">&nbsp;<?= $settings['address']; ?></span>
						</div>
						<div class="form-group">
							<label><?= lang("app.disciplineMax"); ?>:</label>
							<span data-value="<?= $settings['discipline_max']; ?>" data-target="discipline_max"
								  class="spedit">&nbsp;<?= $settings['discipline_max']; ?></span>
						</div>
						<div class="form-group">
							<label>Bank Name:</label>
							<span data-value="<?= $settings['bank_name']; ?>" data-target="bank_name"
								  class="spedit">&nbsp;<?= $settings['bank_name']; ?></span>
						</div>
						<div class="form-group">
							<label>Bank account:</label>
							<span data-value="<?= $settings['bank_account']; ?>" data-target="bank_account"
								  class="spedit">&nbsp;<?= $settings['bank_account']; ?></span>
						</div>
						<div class="form-group">
							<label>MOMO account:</label>
							<span data-value="<?= $settings['mtn_momo_phone']; ?>" data-target="mtn_momo_phone"
								  class="spedit">&nbsp;<?= $settings['mtn_momo_phone']; ?></span>
							<label class="text-muted">MTN phone number that is registered in MOMO pay that will be used to receive School and registration fees paid by parents</label>

						</div>
						<div class="form-group">
							<label>MOMO account:</label>
							<span data-value="<?= $settings['pocket_money_phone']; ?>" data-target="pocket_money_phone"
								  class="spedit">&nbsp;<?= $settings['pocket_money_phone']; ?></span>
							<label class="text-muted">MTN phone number that is registered in MOMO pay that will be used to receive student money (Pocket money) sent by parents</label>

						</div>
					</div>
					<div>
						<?php
						$lockedPeriods = [];
						if (!empty($settings['locked_periods'])) {
							foreach (explode(',', (string) $settings['locked_periods']) as $lp) {
								$n = (int) trim($lp);
								if ($n >= 1 && $n <= 4) {
									$lockedPeriods[] = $n;
								}
							}
						}
						$usePeriod = (int) ($settings['use_period'] ?? 0) === 1;
						?>
						<div class="ss-term-card">
							<h4><?= lang("app.activeTerm"); ?></h4>
							<div style="margin-left: 4px">
								<label>Academic year: <?= $academic_year_title; ?></label>
								<label>Term: <?= \App\Controllers\Home::TermToStr($settings['term']); ?></label>
								<label><?= lang("app.usePeriodicSystem"); ?> <?= $usePeriod ? lang("app.yes") : lang("app.no"); ?></label>
								<label><?= lang("app.usedSMS"); ?> <?= $settings['sms_usage']; ?></label>
								<label><?= lang("app.remainSMS"); ?> <?= $settings['extra_sms']; ?></label>
							</div>
							<div class="ss-term-actions">
								<button type="button" class="btn btn-change-term" data-toggle="modal" data-target="#mdlTerm">
									<i class="fa fa-exchange"></i> <?= lang("app.changeActiveTerm"); ?>
								</button>
							</div>
							<?php if ($usePeriod): ?>
								<div class="ss-periods-box" id="ss_periods_box">
									<h5>Periods for this term</h5>
									<p class="ss-period-hint">Lock a period to block marks entry. Teachers who try to enter marks will be told the period is locked.</p>
									<?php for ($p = 1; $p <= 4; $p++):
										$isLocked = in_array($p, $lockedPeriods, true);
										?>
										<div class="ss-period-row<?= $isLocked ? ' is-locked' : ''; ?>" data-period="<?= $p; ?>">
											<div>
												<span class="ss-period-label"><?= lang('app.period' . $p); ?></span>
												<span class="ss-period-status"><?= $isLocked ? 'Locked' : 'Open'; ?></span>
											</div>
											<button type="button"
													class="btn-period-lock <?= $isLocked ? 'btn-unlock' : 'btn-lock'; ?>"
													data-period="<?= $p; ?>"
													data-lock="<?= $isLocked ? '0' : '1'; ?>">
												<?= $isLocked ? 'Unlock' : 'Lock'; ?>
											</button>
										</div>
									<?php endfor; ?>
								</div>
							<?php else: ?>
								<p class="ss-periods-off">Periodic system is off. Use <strong>Change Active Term</strong> and enable “Use periodic option in marks” to manage periods here.</p>
							<?php endif; ?>
						</div>
					</div>
				</div>

				</div>
			</div>
		</div>

		<div class="card ss-acc-item" id="logo-signatures">
			<div id="headingLogoSig" class="b-radius-0 card-header">
				<button type="button" data-toggle="collapse" data-target="#collapseLogoSig" aria-expanded="false"
						aria-controls="collapseLogoSig" class="text-left m-0 p-0 btn btn-link btn-block">
					<h5 class="m-0 p-0"><span class="ss-acc-ico"><i class="fa fa-image"></i></span>Logo &amp; signatures</h5>
					<i class="fa fa-chevron-down ss-acc-chevron"></i>
				</button>
			</div>
			<div id="collapseLogoSig" data-parent="#accordion" class="collapse">
				<div class="card-body">
				<?php
				$blankBg = base_url('assets/images/white_blank.png');
				$fallbackImg = $blankBg;
				$logoFb = base_url('assets/images/fallback-logo.png');
				$avatarFb = base_url('assets/images/fallback-avatar.png');
				$logo = strlen($settings['logo'] ?? '') > 4 ? base_url('assets/images/logo/' . $settings['logo']) : $logoFb;
				$sigHead = strlen($settings['headmaster_signature'] ?? '') > 4 ? base_url('assets/images/signatures/' . $settings['headmaster_signature']) : $avatarFb;
				$sigMatron = strlen($settings['matron_signature'] ?? '') > 4 ? base_url('assets/images/signatures/' . $settings['matron_signature']) : $avatarFb;
				$sigPatron = strlen($settings['patron_signature'] ?? '') > 4 ? base_url('assets/images/signatures/' . $settings['patron_signature']) : $avatarFb;
				$sigDisc = strlen($settings['discipline_signature'] ?? '') > 4 ? base_url('assets/images/signatures/' . $settings['discipline_signature']) : $avatarFb;
				$hasLogo = strlen($settings['logo'] ?? '') > 4;
				$hasSigHead = strlen($settings['headmaster_signature'] ?? '') > 4;
				$hasSigMatron = strlen($settings['matron_signature'] ?? '') > 4;
				$hasSigPatron = strlen($settings['patron_signature'] ?? '') > 4;
				$hasSigDisc = strlen($settings['discipline_signature'] ?? '') > 4;
				$hmGender = $head_master_gender ?? ($settings['head_master_gender'] ?? 'M');
				$headLabel = lang('app.' . ($hmGender === 'F' ? 'schoolHeadmistress' : 'schoolHeadmaster'));
				?>
				<div class="ss-brand-grid" id="ss_brand_assets"
					 data-fallback="<?= esc($avatarFb, 'attr'); ?>"
					 data-logo-fallback="<?= esc($logoFb, 'attr'); ?>"
					 data-sig-fallback="<?= esc($avatarFb, 'attr'); ?>">
					<div class="ss-upload-card">
						<h4><?= lang("app.schoolLogo"); ?></h4>
						<img src="<?= esc($logo, 'attr'); ?>" id="img_logo"
							 class="ss-upload-preview<?= $hasLogo ? '' : ' is-empty'; ?>"
							 alt="Logo" data-fallback="<?= esc($logoFb, 'attr'); ?>"
							 onerror="this.onerror=null;this.src=this.dataset.fallback;this.classList.add('is-empty');">
						<input type="file" id="in_school_logo" style="display:none">
						<div class="ss-upload-zone" id="dv_select_img">
							<p><?= lang("app.uploadLogo"); ?></p>
							<span class="text-muted"><?= lang("app.sizeNeeded"); ?></span>
						</div>
					</div>
					<div class="ss-upload-card">
						<h4><?= $headLabel; ?></h4>
						<?php if ($hasSigHead): ?><span class="ss-upload-del" id="btn-remove-signature"><i class="fa fa-times"></i></span><?php endif; ?>
						<img src="<?= esc($sigHead, 'attr'); ?>" id="img_headmaster_signature"
							 class="ss-upload-preview<?= $hasSigHead ? '' : ' is-empty'; ?>"
							 alt="Head signature" data-fallback="<?= esc($avatarFb, 'attr'); ?>"
							 onerror="this.onerror=null;this.src=this.dataset.fallback;this.classList.add('is-empty');">
						<input type="file" id="in_headmaster_signature" style="display:none">
						<div class="ss-upload-zone" id="dv_select_headmaster_signature">
							<p>Upload signature</p>
							<span class="text-muted">Max 5MB · jpg / png</span>
						</div>
					</div>
					<div class="ss-upload-card">
						<h4>Matron signature</h4>
						<?php if ($hasSigMatron): ?><span class="ss-upload-del" id="btn-remove-matron"><i class="fa fa-times"></i></span><?php endif; ?>
						<img src="<?= esc($sigMatron, 'attr'); ?>" id="img_matron_signature"
							 class="ss-upload-preview<?= $hasSigMatron ? '' : ' is-empty'; ?>"
							 alt="Matron" data-fallback="<?= esc($avatarFb, 'attr'); ?>"
							 onerror="this.onerror=null;this.src=this.dataset.fallback;this.classList.add('is-empty');">
						<input type="file" id="in_matron_signature" style="display:none">
						<div class="ss-upload-zone" id="dv_select_matron_signature">
							<p>Upload signature</p>
							<span class="text-muted">Max 5MB · jpg / png</span>
						</div>
					</div>
					<div class="ss-upload-card">
						<h4>Patron signature</h4>
						<?php if ($hasSigPatron): ?><span class="ss-upload-del" id="btn-remove-patron"><i class="fa fa-times"></i></span><?php endif; ?>
						<img src="<?= esc($sigPatron, 'attr'); ?>" id="img_patron_signature"
							 class="ss-upload-preview<?= $hasSigPatron ? '' : ' is-empty'; ?>"
							 alt="Patron" data-fallback="<?= esc($avatarFb, 'attr'); ?>"
							 onerror="this.onerror=null;this.src=this.dataset.fallback;this.classList.add('is-empty');">
						<input type="file" id="in_patron_signature" style="display:none">
						<div class="ss-upload-zone" id="dv_select_patron_signature">
							<p>Upload signature</p>
							<span class="text-muted">Max 5MB · jpg / png</span>
						</div>
					</div>
					<div class="ss-upload-card">
						<h4>Discipline signature</h4>
						<?php if ($hasSigDisc): ?><span class="ss-upload-del" id="btn-remove-discipline"><i class="fa fa-times"></i></span><?php endif; ?>
						<img src="<?= esc($sigDisc, 'attr'); ?>" id="img_discipline_signature"
							 class="ss-upload-preview<?= $hasSigDisc ? '' : ' is-empty'; ?>"
							 alt="Discipline" data-fallback="<?= esc($avatarFb, 'attr'); ?>"
							 onerror="this.onerror=null;this.src=this.dataset.fallback;this.classList.add('is-empty');">
						<input type="file" id="in_discipline_signature" style="display:none">
						<div class="ss-upload-zone" id="dv_select_discipline_signature">
							<p>Upload signature</p>
							<span class="text-muted">Max 5MB · jpg / png</span>
						</div>
					</div>
				</div>
			</div>
		</div>
		</div>

		<?php
		$appSet = $app_settings ?? [
			'id' => 0,
			'registration_fees' => 10000,
			'start_date' => date('Y-m-d'),
			'end_date' => date('Y-m-d', strtotime('+1 year')),
			'requirement_document' => '',
			'babyeyi_required' => 1,
		];
		$reqDoc = trim((string)($appSet['requirement_document'] ?? ''));
		$hasReqDoc = strlen($reqDoc) > 3;
		$reqDocUrl = $hasReqDoc ? base_url('assets/documents/' . $reqDoc) : '';
		?>
		<div class="card ss-acc-item">
			<div id="headingAppReg" class="b-radius-0 card-header">
				<button type="button" data-toggle="collapse" data-target="#collapseAppReg" aria-expanded="false"
						aria-controls="collapseAppReg" class="text-left m-0 p-0 btn btn-link btn-block">
					<h5 class="m-0 p-0"><span class="ss-acc-ico"><i class="fa fa-credit-card"></i></span>Online registration fees &amp; documents</h5><i class="fa fa-chevron-down ss-acc-chevron"></i>
				</button>
			</div>
			<div id="collapseAppReg" data-parent="#accordion" class="collapse">
				<div class="card-body">
					<p class="text-muted" style="margin-bottom:1rem;">
						Configure what parents pay and which PDFs are required on
						<a href="<?= base_url('application'); ?>" target="_blank">Online Registration</a>.
					</p>
					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label>Registration fee (RWF)</label>
								<input type="number" min="0" step="100" class="form-control" id="app_reg_fees"
									   value="<?= (int)($appSet['registration_fees'] ?? 10000); ?>">
								<small class="text-muted">School portion received via MOMO. Platform charges are added automatically on payment.</small>
							</div>
							<div class="form-row">
								<div class="form-group col-md-6">
									<label>Applications open</label>
									<input type="date" class="form-control" id="app_reg_start"
										   value="<?= esc($appSet['start_date'] ?? date('Y-m-d'), 'attr'); ?>">
								</div>
								<div class="form-group col-md-6">
									<label>Applications close</label>
									<input type="date" class="form-control" id="app_reg_end"
										   value="<?= esc($appSet['end_date'] ?? date('Y-m-d', strtotime('+1 year')), 'attr'); ?>">
								</div>
							</div>
							<div class="form-group">
								<label class="d-flex align-items-center" style="gap:.5rem;">
									<input type="checkbox" id="app_babyeyi_required" value="1"
										<?= !empty($appSet['babyeyi_required']) ? 'checked' : ''; ?>>
									Require <strong>Babyeyi</strong> PDF from applicants
								</label>
								<small class="text-muted">When checked, applicants must upload Babyeyi before paying.</small>
							</div>
							<button type="button" class="btn btn-success" id="btn_save_app_reg">
								<i class="fa fa-save"></i> Save registration settings
							</button>
							<span id="app_reg_status" class="text-muted" style="margin-left:.75rem;"></span>
						</div>
						<div class="col-md-6">
							<div class="ss-upload-card" style="max-width:100%;">
								<h4>Requirement PDF <small class="text-muted">(optional download for parents)</small></h4>
								<?php if ($hasReqDoc): ?>
									<p style="margin:.5rem 0;">
										<a href="<?= esc($reqDocUrl, 'attr'); ?>" target="_blank" id="app_req_doc_link">
											<i class="fa fa-file-pdf-o"></i> <?= esc($reqDoc); ?>
										</a>
									</p>
								<?php else: ?>
									<p class="text-muted" id="app_req_doc_empty">No requirement PDF uploaded yet.</p>
									<p style="display:none;margin:.5rem 0;" id="app_req_doc_wrap">
										<a href="#" target="_blank" id="app_req_doc_link"><i class="fa fa-file-pdf-o"></i> <span></span></a>
									</p>
								<?php endif; ?>
								<input type="file" id="in_requirement_pdf" accept="application/pdf,.pdf" style="display:none">
								<button type="button" class="btn btn-outline-primary btn-sm" id="btn_upload_req_pdf">
									<i class="fa fa-upload"></i> Upload requirement PDF
								</button>
								<span id="app_req_upload_status" class="text-muted" style="margin-left:.5rem;"></span>
							</div>
							<div class="alert alert-light border" style="margin-top:1rem;">
								<strong>Babyeyi</strong> is uploaded by the applicant during registration
								(Documents step). Use the checkbox on the left to make it mandatory.
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="card ss-acc-item" id="staff-attendance-settings">
			<div id="headingStaffAttendance" class="b-radius-0 card-header">
				<button type="button" data-toggle="collapse" data-target="#collapseStaffAttendance" aria-expanded="false"
						aria-controls="collapseStaffAttendance" class="text-left m-0 p-0 btn btn-link btn-block">
					<h5 class="m-0 p-0"><span class="ss-acc-ico"><i class="fa fa-clock-o"></i></span>Staff attendance settings</h5><i class="fa fa-chevron-down ss-acc-chevron"></i>
				</button>
			</div>
			<div id="collapseStaffAttendance" data-parent="#accordion" class="collapse">
				<div class="card-body">
					<?= view('pages/partials/staff_attendance_shifts', ['shifts' => $shifts ?? []]); ?>
				</div>
			</div>
		</div>

		
		<div class="card ss-acc-item" id="pedagogical-documents">
			<div id="headingPedagogical" class="b-radius-0 card-header">
				<button type="button" data-toggle="collapse" data-target="#collapsePedagogical" aria-expanded="false"
						aria-controls="collapsePedagogical" class="text-left m-0 p-0 btn btn-link btn-block">
					<h5 class="m-0 p-0"><span class="ss-acc-ico"><i class="fa fa-book"></i></span>Pedagogical documents</h5>
					<i class="fa fa-chevron-down ss-acc-chevron"></i>
				</button>
			</div>
			<div id="collapsePedagogical" data-parent="#accordion" class="collapse">
				<div class="card-body">
					<?= view('pages/partials/pedagogical_documents', [
						'classes' => $classes ?? [],
						'pedagogical_docs' => $pedagogical_docs ?? [],
						'academic_year_title' => $academic_year_title ?? '',
						'academic_year_id' => $academic_year_id ?? ($academic_year ?? 0),
					]); ?>
				</div>
			</div>
		</div>

<?php
		$cardTemplates = \App\Libraries\CardLayout::TEMPLATES;
		$cardFieldLabels = \App\Libraries\CardLayout::FIELDS;
		$staffFieldLabels = \App\Libraries\CardLayout::STAFF_FIELDS;
		$studentDbFieldLabels = \App\Libraries\CardLayout::STUDENT_DB_FIELDS;
		$staffDbFieldLabels = \App\Libraries\CardLayout::STAFF_DB_FIELDS;
		$cardTemplate = \App\Libraries\CardLayout::normalizeTemplate($settings['card_template'] ?? 'ocean');
		$cardOri = \App\Libraries\CardLayout::normalizeOrientation(
			$settings['card_orientation'] ?? \App\Libraries\CardLayout::preferredOrientation($cardTemplate)
		);
		$cardBgMode = strtolower($settings['card_bg_mode'] ?? 'manual') === 'smart' ? 'smart' : 'manual';
		$sfCardTemplate = \App\Libraries\CardLayout::normalizeTemplate($settings['sf_card_template'] ?? 'ocean');
		$sfCardOri = \App\Libraries\CardLayout::normalizeOrientation(
			$settings['sf_card_orientation'] ?? \App\Libraries\CardLayout::preferredOrientation($sfCardTemplate)
		);
		$sfCardBgMode = strtolower($settings['sf_card_bg_mode'] ?? 'manual') === 'smart' ? 'smart' : 'manual';
		$hasBkStudent = strlen($settings['card_background'] ?? '') > 4;
		$hasBkStaff = strlen($settings['sf_card_background'] ?? '') > 4;
		$bkStudent = $hasBkStudent ? base_url('assets/images/background/' . $settings['card_background']) : $fallbackImg;
		$bkStaff = $hasBkStaff ? base_url('assets/images/background/' . $settings['sf_card_background']) : $fallbackImg;
		$previewSchool = esc($settings['name'] ?? 'School', 'attr');
		$previewMoto = esc($settings['slogan'] ?? 'SmartSMS', 'attr');
		$previewHead = esc($settings['head_master'] ?? 'Headmaster', 'attr');
		$photoPlaceholder = base_url('assets/images/white_blank.png');
		$cardLayoutResolved = \App\Libraries\CardLayout::resolve($settings['card_layout'] ?? null, $cardTemplate, $cardOri);
		$sfCardLayoutResolved = \App\Libraries\CardLayout::resolveStaff($settings['sf_card_layout'] ?? null, $sfCardTemplate, $sfCardOri);
		$cardLayoutJson = json_encode($cardLayoutResolved, JSON_UNESCAPED_UNICODE);
		$sfCardLayoutJson = json_encode($sfCardLayoutResolved, JSON_UNESCAPED_UNICODE);
		$defaultsByTpl = [];
		$staffDefaultsByTpl = [];
		foreach (array_keys($cardTemplates) as $tplKey) {
			$defaultsByTpl[$tplKey] = [
				'landscape' => \App\Libraries\CardLayout::defaults($tplKey, 'landscape'),
				'portrait' => \App\Libraries\CardLayout::defaults($tplKey, 'portrait'),
			];
			$staffDefaultsByTpl[$tplKey] = [
				'landscape' => \App\Libraries\CardLayout::staffDefaults($tplKey, 'landscape'),
				'portrait' => \App\Libraries\CardLayout::staffDefaults($tplKey, 'portrait'),
			];
		}
		$cardDefaultsJson = json_encode($defaultsByTpl, JSON_UNESCAPED_UNICODE);
		$staffDefaultsJson = json_encode($staffDefaultsByTpl, JSON_UNESCAPED_UNICODE);
		$cardFieldLabelsJson = json_encode($cardFieldLabels, JSON_UNESCAPED_UNICODE);
		$staffFieldLabelsJson = json_encode($staffFieldLabels, JSON_UNESCAPED_UNICODE);
		$studentDbFieldLabelsJson = json_encode($studentDbFieldLabels, JSON_UNESCAPED_UNICODE);
		$staffDbFieldLabelsJson = json_encode($staffDbFieldLabels, JSON_UNESCAPED_UNICODE);
		$autoHeaders = \App\Libraries\CardLayout::composeHeaderLines($settings);
		$studentBrand = [
			'header1' => $autoHeaders['header1'],
			'header2' => $autoHeaders['header2'],
			'capitalize' => (int)($settings['capitalize'] ?? 0),
			'header_color' => $settings['header_color'] ?? '#0a66b7',
			'main_color' => $settings['main_color'] ?? '#0a66b7',
			'footer_color' => $settings['footer_color'] ?? '#000000',
			'paint_color' => $settings['paint_color'] ?? ($settings['main_color'] ?? '#1E6FD9'),
		];
		$staffBrand = [
			'header1' => $autoHeaders['header1'],
			'header2' => $autoHeaders['header2'],
			'capitalize' => (int)($settings['sf_capitalize'] ?? ($settings['capitalize'] ?? 0)),
			'header_color' => $settings['sf_header_color'] ?? ($settings['header_color'] ?? '#0a66b7'),
			'main_color' => $settings['sf_main_color'] ?? ($settings['main_color'] ?? '#0a66b7'),
			'footer_color' => $settings['sf_footer_color'] ?? ($settings['footer_color'] ?? '#000000'),
			'paint_color' => $settings['sf_paint_color'] ?? ($settings['paint_color'] ?? ($settings['sf_main_color'] ?? ($settings['main_color'] ?? '#1E6FD9'))),
		];
		$previewHeader1 = esc($studentBrand['header1'], 'attr');
		$previewHeader2 = esc($studentBrand['header2'], 'attr');
		$previewMain = esc($studentBrand['main_color'], 'attr');
		$previewPaint = esc($studentBrand['paint_color'], 'attr');
		$sfPreviewHeader1 = esc($staffBrand['header1'], 'attr');
		$sfPreviewHeader2 = esc($staffBrand['header2'], 'attr');
		$sfPreviewMain = esc($staffBrand['main_color'], 'attr');
		$sfPreviewPaint = esc($staffBrand['paint_color'], 'attr');
		$sampleStaff = $card_sample_staff ?? null;
		$staffSampleVals = [
			'logo' => '',
			'school_name' => $settings['name'] ?? 'School',
			'header1' => $staffBrand['header1'],
			'header2' => $staffBrand['header2'],
			'badge' => 'STAFF CARD',
			'photo' => 'PHOTO',
			'names' => $sampleStaff
				? trim(($sampleStaff['fname'] ?? '') . ' ' . ($sampleStaff['lname'] ?? ''))
				: 'Sample Staff',
			'post' => !empty($sampleStaff['post_title']) ? $sampleStaff['post_title'] : 'Post required',
			'phone' => !empty($sampleStaff['phone']) ? $sampleStaff['phone'] : '—',
			'email' => !empty($sampleStaff['email']) ? $sampleStaff['email'] : '—',
			'staff_id' => !empty($sampleStaff['id']) ? (string) $sampleStaff['id'] : '—',
			'moto' => $settings['slogan'] ?? 'SmartSMS',
		];
		if ($sampleStaff && !empty($sampleStaff['photo'])) {
			$staffSampleVals['photo_url'] = profile_photo_url($sampleStaff['photo']);
		}
		$staffSampleJson = json_encode($staffSampleVals, JSON_UNESCAPED_UNICODE);
		$sharedPreview = [
			'cardTemplates' => $cardTemplates,
			'fallbackImg' => $fallbackImg,
			'previewMoto' => $previewMoto,
			'previewHead' => $previewHead,
			'previewSchool' => $previewSchool,
			'photoPlaceholder' => $photoPlaceholder,
			'logo' => $logo,
			'sigHead' => $sigHead,
		];
		?>
		<div class="card ss-acc-item">
			<div id="headingCardOpts" class="b-radius-0 card-header">
				<button type="button" data-toggle="collapse" data-target="#collapseOne2" aria-expanded="false"
						aria-controls="collapseOne2" class="text-left m-0 p-0 btn btn-link btn-block">
					<h5 class="m-0 p-0"><span class="ss-acc-ico"><i class="fa fa-id-card"></i></span>Card options</h5>
					<i class="fa fa-chevron-down ss-acc-chevron"></i>
				</button>
			</div>
			<div id="collapseOne2" data-parent="#accordion" class="collapse">
				<div class="card-body">
					<div id="cardAudienceAcc">
						<?= view('pages/partials/card_audience_panel', array_merge($sharedPreview, [
							'audience' => 'student',
							'title' => 'Student card',
							'foldId' => 'foldStudentCard',
							'foldOpen' => true,
							'cardTemplate' => $cardTemplate,
							'cardOri' => $cardOri,
							'cardBgMode' => $cardBgMode,
							'hasBk' => $hasBkStudent,
							'bkUrl' => $bkStudent,
							'bgField' => 'card_background',
							'imgId' => 'img_backg',
							'zoneId' => 'dv_select_img_backg',
							'clrId' => 'clr_bg',
							'badgeLabel' => 'STUDENT CARD',
							'layoutJson' => $cardLayoutJson,
							'fieldLabelsJson' => $cardFieldLabelsJson,
							'defaultsJson' => $cardDefaultsJson,
							'sampleValsJson' => '{}',
							'previewHeader1' => $previewHeader1,
							'previewHeader2' => $previewHeader2,
							'previewMain' => $previewMain,
							'previewPaint' => $previewPaint,
							'brandValues' => $studentBrand,
						])); ?>
						<?= view('pages/partials/card_audience_panel', array_merge($sharedPreview, [
							'audience' => 'staff',
							'title' => 'Staff card',
							'foldId' => 'foldStaffCard',
							'foldOpen' => false,
							'cardTemplate' => $sfCardTemplate,
							'cardOri' => $sfCardOri,
							'cardBgMode' => $sfCardBgMode,
							'hasBk' => $hasBkStaff,
							'bkUrl' => $bkStaff,
							'bgField' => 'sf_card_background',
							'imgId' => 'img_backg_sf',
							'zoneId' => 'dv_select_img_backg_sf',
							'clrId' => 'clr_bg_sf',
							'badgeLabel' => 'STAFF CARD',
							'layoutJson' => $sfCardLayoutJson,
							'fieldLabelsJson' => $staffFieldLabelsJson,
							'defaultsJson' => $staffDefaultsJson,
							'sampleValsJson' => $staffSampleJson,
							'previewHeader1' => $sfPreviewHeader1,
							'previewHeader2' => $sfPreviewHeader2,
							'previewMain' => $sfPreviewMain,
							'previewPaint' => $sfPreviewPaint,
							'brandValues' => $staffBrand,
						])); ?>
					</div>
					<script type="application/json" id="ssCardDefaultsBoot"><?= $cardDefaultsJson; ?></script>
					<script type="application/json" id="ssStaffCardDefaultsBoot"><?= $staffDefaultsJson; ?></script>
					<script type="application/json" id="ssCardFieldLabels"><?= $cardFieldLabelsJson; ?></script>
					<script type="application/json" id="ssStaffCardFieldLabels"><?= $staffFieldLabelsJson; ?></script>
					<script type="application/json" id="ssStudentDbFieldLabels"><?= $studentDbFieldLabelsJson; ?></script>
					<script type="application/json" id="ssStaffDbFieldLabels"><?= $staffDbFieldLabelsJson; ?></script>
				</div>
			</div>
		</div>
		<div class="card ss-acc-item">
			<div id="headingGrade" class="b-radius-0 card-header">
				<button type="button" data-toggle="collapse" data-target="#collapseOne3" aria-expanded="false"
						aria-controls="collapseOne3" class="text-left m-0 p-0 btn btn-link btn-block">
					<h5 class="m-0 p-0"><span class="ss-acc-ico"><i class="fa fa-sliders"></i></span><?= lang("app.gradeSetting"); ?></h5>
					<i class="fa fa-chevron-down ss-acc-chevron"></i>
				</button>
			</div>
			<div id="collapseOne3" data-parent="#accordion" class="collapse">
				<div class="card-body">
					<form method="POST" action="<?= base_url('manipulate_grade'); ?>" id="gradeMentionForm" class="validate">
					<div class="col-sm-12 col-md-6 col-lg-5" style="float:left;">
						<?php
						$nurseryFac = $nursery_faculty ?? null;
						$nurseryId = (int) ($nurseryFac['id'] ?? 0);
						$nurseryTitle = $nurseryFac['title'] ?? 'Nursery';
						?>
						<div class="form-group">
							<label><?= lang("app.colorTitle"); ?></label>
							<input class="form-control" type="text" name="color_title" id="gradeMentionTitle" placeholder="e.g. Excellent" required autocomplete="off">
						</div>
						<div class="form-group">
							<label><?= lang("app.maxPoint"); ?></label>
							<input class="form-control" type="number" name="max_point" id="gradeMentionMax" min="0" max="100" required>
						</div>
						<div class="form-group">
							<label><?= lang("app.minPoint"); ?></label>
							<input class="form-control" type="number" name="min_point" id="gradeMentionMin" min="0" max="100" required>
						</div>
						<div class="form-group">
							<label><?= lang("app.selectFaculty"); ?></label>
							<input type="hidden" name="faculite" value="<?= $nurseryId; ?>">
							<div class="form-control" style="background:#f1f5f9;font-weight:600;color:#0f172a;">
								<?= esc($nurseryTitle); ?>
								<span class="text-muted" style="font-weight:500;font-size:.85em;"> (locked)</span>
							</div>
							<?php if ($nurseryId <= 0): ?>
								<small class="text-danger">Nursery path not found in Educational paths — create a faculty named “Nursery”.</small>
							<?php endif; ?>
						</div>
						<div class="form-group">
							<label><?= lang("app.chooseColor"); ?></label>
							<input type="text" id="custom" name="color" value="#22c55e">
						</div>
						<div class="form-group">
							<center>
								<button type="submit" class="btn btn-success btn-lg" id="btnSaveMention" <?= $nurseryId <= 0 ? 'disabled' : ''; ?>>
									<b><?= lang("app.saveChanges"); ?></b>
								</button>
							</center>
							<p class="text-muted text-center mt-2 mb-0" style="font-size:.82rem;">After save, the list updates instantly — keep adding mentions.</p>
						</div>
					</div>
					</form>
					<div class="col-sm-12 col-md-6 col-lg-7" style="float:left;">
						<label><b><?= lang("app.gradeSettingView"); ?></b></label>
						<div style="background-color: white;display: flow-root;">
							<table width="100%" border="1" id="gradeMentionTable">
								<thead>
								<tr>
									<th><?= lang("app.colorTitle"); ?></th>
									<th><?= lang("app.maxPoint"); ?></th>
									<th><?= lang("app.minPoint"); ?></th>
									<th><?= lang("app.faculty"); ?></th>
									<th><?= lang("app.color"); ?></th>
									<th></th>
								</tr>
								</thead>
								<tbody id="gradeMentionBody">
								<?php foreach ($colors as $color): ?>
								<tr data-id="<?= (int) $color['id']; ?>">
									<td><?= esc($color['color_title']); ?></td>
									<td><?= esc($color['max_point']); ?></td>
									<td><?= esc($color['min_point']); ?></td>
									<td><?= esc($color['title']); ?></td>
									<td style="background-color: <?= esc($color['color'], 'attr'); ?>"></td>
									<td><center>
											<a class="btn btn-danger" data-toggle="modal" data-target="#DeleteGradeModal" data-id="<?= (int) $color['id']; ?>">
												<i class="fa fa-trash" style="color: white"></i>
											</a>
										</center>
									</td>
								</tr>
								<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="card ss-acc-item">
			<div id="headingIntouch" class="b-radius-0 card-header">
				<button type="button" data-toggle="collapse" data-target="#collapseOne4" aria-expanded="false"
						aria-controls="collapseOne4" class="text-left m-0 p-0 btn btn-link btn-block">
					<h5 class="m-0 p-0"><span class="ss-acc-ico"><i class="fa fa-plug"></i></span><?= lang("app.intouchSetting"); ?></h5>
					<i class="fa fa-chevron-down ss-acc-chevron"></i>
				</button>
			</div>
			<div id="collapseOne4" data-parent="#accordion" class="collapse">
				<form method="POST" action="<?=base_url('manipulate_intouch');?>/<?= $intouch_info['school_id'] ?>" class="validate autoSubmit">
					<div class="card-body">
						<div class="col-sm-12 col-md-6 col-lg-5">

							<div class="form-group">
								<label><?= lang("app.titleUsername"); ?></label>
								<input class="form-control" type="text" name="intouch_username" value="<?= $intouch_info['username'] ?>">
							</div>
							<div class="form-group">
								<label><?= lang("app.password"); ?></label>
								<input class="form-control" type="password" name="intouch_password" value="<?= $intouch_info['password'] ?>">
							</div>
							<div class="form-group">
								<center><button type="submit" class="btn btn-success btn-lg" data-target="reload"><b><?= lang("app.saveChanges"); ?></b></button></center>
							</div>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>

</div>
<script>
  window.base_url = "<?= base_url(); ?>";
</script>

<script>
	$(function () {
		var sp, value, old_data, target, type = null;
		var cardScopes = {};
		var FALLBACK = $("#ss_brand_assets").data("fallback") || "<?= base_url('assets/images/fallback-avatar.png'); ?>";
		var LOGO_FALLBACK = $("#ss_brand_assets").data("logo-fallback") || "<?= base_url('assets/images/fallback-logo.png'); ?>";
		var SIG_FALLBACK = $("#ss_brand_assets").data("sig-fallback") || FALLBACK;
		var BG_FALLBACK = "<?= base_url('assets/images/white_blank.png'); ?>";

		// Spectrum color pickers for card branding colors
		if ($.fn.spectrum) {
			$("[data-type='color']").spectrum({
				preferredFormat: "hex",
				showInput: true,
				allowEmpty: false,
				showInitial: true,
				showPalette: true,
				palette: [
					["#0a66b7", "#0EA5E9", "#2171C1", "#1E2A44"],
					["#F15A29", "#E53935", "#16a34a", "#000000"]
				]
			});
			$("[data-type='color']").on("change.spectrum hide.spectrum", function () {
				var $el = $(this);
				var id = $("#settings_section").data("id");
				var val = $el.val() || ($el.spectrum("get") ? $el.spectrum("get").toHexString() : "");
				var targ = $el.data("target");
				if (!val || !targ) return;
				$el.val(val).data("value", val);
				var $scope = $el.closest(".card-audience");
				$scope.find(".ss-color-hex[data-for='" + targ + "']").text(val);
				if (targ === "main_color" || targ === "sf_main_color") {
					$scope.find(".card-live-preview").attr("data-main", val);
				}
				if (targ === "paint_color" || targ === "sf_paint_color") {
					$scope.find(".card-live-preview").attr("data-paint", val);
				}
				$.post("<?=base_url('manipulate_settings/');?>color", "id=" + id + "&target=" + encodeURIComponent(targ) + "&val=" + encodeURIComponent(val), function (data) {
					if (data.hasOwnProperty("error")) {
						toastada.error('<?= lang("app.saveFail");?>' + (data.msg || data.error || ""));
					} else if (data.hasOwnProperty("success")) {
						toastada.success('<?= lang("app.saveSuccess");?>');
						if (typeof refreshLivePreview === "function") {
							if ($scope.length && cardScopes[$scope.data("audience")]) {
								cardScopes[$scope.data("audience")].render();
							} else {
								refreshLivePreview();
							}
						}
					} else {
						toastada.error('<?= lang("app.fatalErr"); ?>');
					}
				}).fail(function () {
					toastada.error('<?= lang("app.systemErr"); ?>');
				});
			});
		}

		$(".spedit").on("dblclick", function () {
			sp = $(this);
			if (sp.is("input") || sp.data("type") === "color") return;
			value = sp.data("value");
			if (value === undefined || value === null) value = "";
			old_data = sp.html();
			target = sp.data("target");
			type = sp.data("type") == undefined ? "text" : sp.data("type");
			if (type == "text")
				sp.html("<input type='text' value='" + String(value).replace(/'/g, "&#39;") + "' class='sptxt' style='min-width:200px;'>");
			if (type == "number" || type == "digit")
				sp.html("<input type='text' data-parsley-type='number' value='" + String(value).replace(/'/g, "&#39;") + "' class='sptxt'>");
			if (type == "status") {
				sp.html("<input type='checkbox' value='1' class='spchk'>");
				if (value == 1) {
					$(".spchk").prop("checked", true);
				}
			}
			if (type == "select") {
				sp.html("<select class='select2_auto' style='width:200px !important' data-value='" + value + "' data-href='" + sp.data("href") + "' class='spselect'>");
				load_select(sp.data("href"), value);
			}
			$(".sptxt").focus();
		});
		$(document).on("keydown blur", ".sptxt", function (e) {
			var sptxt = $(this);
			var id = $("#settings_section").data("id");
			var val = sptxt.val();
			if (e.which == 13 || e.type == 'blur' || e.type == 'focusout') {
				if (val == value) {
					sp.html(old_data);
					return;
				}
				$.post("<?=base_url('manipulate_settings/');?>" + type, "id=" + id + "&target=" + target + "&val=" + encodeURIComponent(val), function (data) {
					if (data.hasOwnProperty("error")) {
						toastada.error('<?= lang("app.saveFail");?>' + (data.msg || data.error || ""));
					} else if (data.hasOwnProperty("success")) {
						if (!String(val).trim()) {
							sp.html("&nbsp;Double-click to edit").addClass("is-empty-hint");
						} else {
							sp.html(data.result).removeClass("is-empty-hint");
						}
						sp.data("value", val);
						var $aud = sp.closest(".card-audience");
						if (target === "header_text_1" || target === "sf_header_text_1") {
							$aud.find(".card-live-preview").attr("data-header1", val);
						}
						if (target === "header_text_2" || target === "sf_header_text_2") {
							$aud.find(".card-live-preview").attr("data-header2", val);
						}
						if (["phone","email","address","website"].indexOf(target) >= 0) {
							refreshCardHeadersFromBasicInfo();
						}
						toastada.success('<?= lang("app.saveSuccess");?>');
						if (typeof refreshLivePreview === "function") {
							if ($aud.length && cardScopes[$aud.data("audience")]) {
								cardScopes[$aud.data("audience")].render();
							} else {
								refreshLivePreview();
							}
						}
					} else {
						toastada.error('<?= lang("app.fatalErr"); ?>');
					}
				}).fail(function () {
					toastada.error('<?= lang("app.systemErr"); ?>');
				});
			}
			if (e.which == 27) {
				sp.html(old_data);
			}
		});
		$(document).on("change", ".spchk", function (e) {
			var spchk = $(this);
			var id = $("#settings_section").data("id");
			var val = spchk.is(":checked") ? 1 : 0;
			$.post("<?=base_url('manipulate_settings/');?>" + type, "id=" + id + "&target=" + target + "&val=" + val, function (data) {
				if (data.hasOwnProperty("error")) {
					toastada.error('<?= lang("app.saveFail"); ?>' + (data.msg || data.error || ""));
				} else if (data.hasOwnProperty("success")) {
					sp.html(data.result);
					sp.data("value", val);
					toastada.success('<?= lang("app.saveSuccess"); ?>');
					if (typeof refreshLivePreview === "function") refreshLivePreview();
				} else {
					toastada.error('<?= lang("app.fatalErr"); ?>');
				}
			}).fail(function () {
				toastada.error('<?= lang("app.systemErr"); ?>');
			});
		});

		function setImgFallback($img, fallback) {
			var fb = fallback || FALLBACK;
			$img.off("error.ssfb").attr("src", fb).addClass("is-empty");
		}
		function setImgReal($img, src) {
			if (!src) { setImgFallback($img); return; }
			// Re-bind onerror so a previous failed load cannot stick the placeholder forever
			$img.off("error.ssfb").on("error.ssfb", function () {
				$(this).off("error.ssfb").attr("src", FALLBACK).addClass("is-empty");
			});
			$img.attr("src", src).removeClass("is-empty");
		}
		var fieldLabelsGlobal = {};
		var staffFieldLabelsGlobal = {};
		var studentDbLabelsGlobal = {};
		var staffDbLabelsGlobal = {};
		var defaultsMapGlobal = {};
		var staffDefaultsMapGlobal = {};
		try { fieldLabelsGlobal = JSON.parse($("#ssCardFieldLabels").text() || "{}"); } catch (e) {}
		try { staffFieldLabelsGlobal = JSON.parse($("#ssStaffCardFieldLabels").text() || "{}"); } catch (e) {}
		try { studentDbLabelsGlobal = JSON.parse($("#ssStudentDbFieldLabels").text() || "{}"); } catch (e) {}
		try { staffDbLabelsGlobal = JSON.parse($("#ssStaffDbFieldLabels").text() || "{}"); } catch (e) {}
		try { defaultsMapGlobal = JSON.parse($("#ssCardDefaultsBoot").text() || "{}"); } catch (e) {}
		try { staffDefaultsMapGlobal = JSON.parse($("#ssStaffCardDefaultsBoot").text() || "{}"); } catch (e) {}
		var RESERVED_KEYS = ["logo","school_name","header1","header2","badge","moto"];

		function basicInfoVal(target) {
			var $el = $('.ss-field-list .spedit[data-target="' + target + '"]').first();
			return String(($el.data("value") != null ? $el.data("value") : ($el.text() || ""))).trim();
		}
		function cleanHeaderPart(s) {
			s = String(s || "").replace(/\s*[_\|]+\s*/g, " · ").replace(/\s{2,}/g, " ");
			return s.replace(/^[\s·]+|[\s·]+$/g, "");
		}
		function composeCardHeadersFromBasic() {
			var phone = cleanHeaderPart(basicInfoVal("phone"));
			var email = cleanHeaderPart(basicInfoVal("email"));
			var website = cleanHeaderPart(basicInfoVal("website")).replace(/^https?:\/\//i, "");
			var address = cleanHeaderPart(basicInfoVal("address"));
			var line1 = [];
			if (phone) line1.push("Tel " + phone);
			if (email) line1.push("Email " + email);
			var line2 = [];
			if (website) line2.push(website);
			if (address) line2.push(address);
			return { header1: line1.join(" · "), header2: line2.join(" · ") };
		}
		function persistHeaderField(target, val) {
			var id = $("#settings_section").data("id");
			if (!id) return;
			$.post("<?=base_url('manipulate_settings/');?>text", "id=" + id + "&target=" + target + "&val=" + encodeURIComponent(val));
		}
		function refreshCardHeadersFromBasicInfo() {
			var h = composeCardHeadersFromBasic();
			$(".card-audience").each(function () {
				var $aud = $(this);
				var audience = $aud.data("audience") || "student";
				var h1Field = audience === "staff" ? "sf_header_text_1" : "header_text_1";
				var h2Field = audience === "staff" ? "sf_header_text_2" : "header_text_2";
				$aud.find(".ss-header-autofill[data-header-slot='1']").html(h.header1 || '<span class="text-muted">Add phone or email in Basic school info</span>');
				$aud.find(".ss-header-autofill[data-header-slot='2']").html(h.header2 || '<span class="text-muted">Add website or address in Basic school info</span>');
				$aud.find(".ss-header-sync[data-target='" + h1Field + "']").val(h.header1).attr("data-value", h.header1).data("value", h.header1);
				$aud.find(".ss-header-sync[data-target='" + h2Field + "']").val(h.header2).attr("data-value", h.header2).data("value", h.header2);
				$aud.find(".card-live-preview").attr("data-header1", h.header1).attr("data-header2", h.header2);
				if (cardScopes[audience]) cardScopes[audience].render();
			});
			persistHeaderField("header_text_1", h.header1);
			persistHeaderField("header_text_2", h.header2);
			persistHeaderField("sf_header_text_1", h.header1);
			persistHeaderField("sf_header_text_2", h.header2);
		}

		function createCardScope($root) {
			var audience = $root.data("audience") || "student";
			var prefix = audience === "staff" ? "sf" : "st";
			var $live = $root.find(".card-live-preview").first();
			var $oriChoice = $root.find(".card-ori-choice").first();
			var $tplChoice = $root.find(".card-tpl-choice").first();
			var $bgModeChoice = $root.find(".card-bg-mode-choice").first();
			var $aiPanel = $root.find(".card-ai-panel").first();
			var $canvas = $root.find(".ss-editor-canvas").first();
			var $liveBg = $root.find(".ss-ed-bg").first();
			var $items = $root.find(".ss-editor-items").first();
			var $toggles = $root.find(".card-field-toggles").first();
			var $status = $root.find(".card-layout-status").first();
			var $imgBg = $root.find(".ss-bg-preview-img").first();
			var $bgFrame = $root.find("[data-bg-frame]").first();
			var $clr = $root.find(".btn-clear-bg").first();
			var oriField = audience === "staff" ? "sf_card_orientation" : "card_orientation";
			var bgModeField = audience === "staff" ? "sf_card_bg_mode" : "card_bg_mode";
			var oriInputName = prefix + "_card_orientation";
			var fieldLabels = audience === "staff" ? staffFieldLabelsGlobal : fieldLabelsGlobal;
			var dbToggleLabels = audience === "staff" ? staffDbLabelsGlobal : studentDbLabelsGlobal;
			var defaultsMap = audience === "staff" ? staffDefaultsMapGlobal : defaultsMapGlobal;
			try {
				var localLabels = JSON.parse($root.find(".card-labels-boot").first().text() || "{}");
				if (localLabels && Object.keys(localLabels).length) fieldLabels = localLabels;
			} catch (e) {}
			try {
				var localDefaults = JSON.parse($root.find(".card-defaults-boot").first().text() || "{}");
				if (localDefaults && Object.keys(localDefaults).length) defaultsMap = localDefaults;
			} catch (e) {}
			var layoutState = { template: "ocean", fields: {} };
			try { layoutState = JSON.parse($root.find(".card-layout-boot").first().text() || "{}"); } catch (e) {}
			if (!layoutState.fields) layoutState.fields = {};

			var sampleVals = {
				logo: "",
				school_name: $live.data("school") || "School",
				header1: $live.data("header1") || "",
				header2: $live.data("header2") || "",
				badge: $live.data("badge") || (audience === "staff" ? "STAFF CARD" : "STUDENT CARD"),
				photo: "PHOTO",
				names: "Sample Student",
				regno: "260240001",
				class: "P1",
				dob: "09-12-2005",
				father: "Father name",
				phone: "0780000000",
				mode: "BOARDING",
				moto: $live.data("moto") || "SmartSMS"
			};
			if (audience === "staff") {
				sampleVals = {
					logo: "",
					school_name: $live.data("school") || "School",
					header1: $live.data("header1") || "",
					header2: $live.data("header2") || "",
					badge: $live.data("badge") || "STAFF CARD",
					photo: "PHOTO",
					names: "Sample Staff",
					post: "Post required",
					phone: "—",
					email: "—",
					staff_id: "—",
					moto: $live.data("moto") || "SmartSMS"
				};
				try {
					var fromDb = JSON.parse($root.find(".card-sample-boot").first().text() || "{}");
					if (fromDb && typeof fromDb === "object") {
						Object.keys(fromDb).forEach(function (k) {
							if (fromDb[k] !== undefined && fromDb[k] !== null && fromDb[k] !== "") {
								sampleVals[k] = fromDb[k];
							}
						});
					}
				} catch (e) {}
			}

			function currentOrientation() {
				return $root.find("input[name='" + oriInputName + "']:checked").val() || "landscape";
			}
			function syncBgFrame() {
				var ori = currentOrientation();
				$bgFrame.toggleClass("is-portrait", ori === "portrait").toggleClass("is-landscape", ori !== "portrait");
			}
			function syncCanvasSize() {
				var ori = currentOrientation();
				$canvas
					.toggleClass("is-portrait", ori === "portrait")
					.toggleClass("is-landscape", ori !== "portrait")
					.removeClass("is-ocean is-geo");
				syncBgFrame();
			}
			function currentTpl() {
				return layoutState.template || $tplChoice.find(".ss-tpl-card.is-on").data("template") || "ocean";
			}
			function isPaintedTpl(tpl) {
				return String($tplChoice.find(".ss-tpl-card[data-template='" + tpl + "']").data("painted") || "") === "1";
			}
			function tintHex(hex, ratio) {
				hex = String(hex || "#1E6FD9").replace("#", "");
				if (hex.length === 3) hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
				if (!/^[0-9a-fA-F]{6}$/.test(hex)) hex = "1E6FD9";
				var out = "#";
				for (var i = 0; i < 3; i++) {
					var c = parseInt(hex.substr(i * 2, 2), 16);
					c = Math.round(c + (255 - c) * ratio);
					out += ("0" + c.toString(16)).slice(-2);
				}
				return out;
			}
			function syncPaintedUi(paintColor) {
				var painted = isPaintedTpl(currentTpl());
				$root.find(".card-painted-note").toggle(painted);
				$root.find(".card-bg-tools").toggle(!painted);
				$root.find(".ss-bg-previews").toggle(!painted);
				var $paint = $canvas.find(".ss-ed-paint");
				if (!painted) {
					$paint.remove();
					return;
				}
				if (!$paint.length) {
					$paint = $('<div class="ss-ed-paint"></div>');
					$liveBg.after($paint);
				}
				var paint = paintColor || $root.find("input.card-paint-color").val()
					|| $live.attr("data-paint")
					|| "#1E6FD9";
				var light = tintHex(paint, 0.82);
				var mid = tintHex(paint, 0.55);
				// Same geometry as the PDF painted design (Classic Curve)
				$paint.html(
					'<div style="position:absolute;left:-52%;top:-18%;width:74%;height:136%;border-radius:50%;background:' + light + ';"></div>'
					+ '<div style="position:absolute;left:-56%;top:-15%;width:70%;height:130%;border-radius:50%;background:' + mid + ';"></div>'
					+ '<div style="position:absolute;left:-12%;top:85.5%;width:135%;height:30%;border-radius:50%;background:' + light + ';"></div>'
					+ '<div style="position:absolute;left:-15%;top:89.5%;width:140%;height:30%;border-radius:50%;background:' + mid + ';"></div>'
					+ '<div style="position:absolute;left:0;top:0;right:0;bottom:0;border:4px solid ' + paint + ';border-radius:10px;box-sizing:border-box;"></div>'
				);
			}
			function refreshEditorBg() {
				if (isPaintedTpl(currentTpl())) {
					// Painted template: built-in design only, never a background image
					$liveBg.css({ "background-image": "none", "background-color": "#ffffff" });
					return;
				}
				var bgSrc = ($imgBg.attr("src") || "").split("?")[0];
				var isEmptyBg = $imgBg.hasClass("is-empty") || !bgSrc || bgSrc.indexOf("white_blank") !== -1 || bgSrc.indexOf("no_image") !== -1 || bgSrc.indexOf("fallback-") !== -1;
				// Always pure white until an uploaded / AI background is present
				if (isEmptyBg) {
					$liveBg.css({ "background-image": "none", "background-color": "#ffffff" });
				} else {
					$liveBg.css({
						"background-color": "#ffffff",
						"background-image": "url('" + ($imgBg.attr("src") || bgSrc).replace(/'/g, "%27") + "')"
					});
				}
			}
			function applyTemplateDefaults(template, orientation) {
				var pack = (defaultsMap[template] && defaultsMap[template][orientation]) || { template: template, fields: {} };
				layoutState = JSON.parse(JSON.stringify(pack));
				layoutState.template = template;
				layoutState.orientation = orientation;
				if (audience === "staff" && layoutState.fields && layoutState.fields.post) {
					layoutState.fields.post.visible = true;
				}
				renderEditor();
			}
			function renderEditor() {
				syncCanvasSize();
				refreshEditorBg();
				$items.empty();
				$toggles.empty();
				sampleVals.school_name = $live.attr("data-school") || sampleVals.school_name;
				sampleVals.header1 = $live.attr("data-header1") || "";
				sampleVals.header2 = $live.attr("data-header2") || "";
				sampleVals.moto = $live.attr("data-moto") || sampleVals.moto;
				var main = $root.find("input[data-target='main_color'], input[data-target='sf_main_color']").val()
					|| $live.attr("data-main")
					|| "#0EA5E9";
				var paint = $root.find("input.card-paint-color").val()
					|| $live.attr("data-paint")
					|| main
					|| "#1E6FD9";
				var logoSrc = $("#img_logo").attr("src") || LOGO_FALLBACK;
				var sigSrc = $("#img_headmaster_signature").attr("src") || "";
				syncPaintedUi(paint);
				var labeledKeys = ["names","regno","class","dob","father","phone","mode","post","email","staff_id"];
				Object.keys(fieldLabels).forEach(function (key) {
					var f = layoutState.fields[key] || { x: 5, y: 5, w: 30, h: 8, visible: true };
					if (RESERVED_KEYS.indexOf(key) >= 0) f.visible = true;
					if (audience === "staff" && key === "post") f.visible = true;
					// Card title bar always fills parent edge-to-edge
					if (key === "badge") {
						f.x = 0;
						f.w = 100;
					}
					layoutState.fields[key] = f;
					var $item = $('<div class="ss-ed-item"></div>').attr("data-key", key);
					if (!f.visible) $item.addClass("is-hidden");
					$item.css({ left: f.x + "%", top: f.y + "%", width: f.w + "%", height: f.h + "%" });
					if (key === "badge") {
						$item.css({ left: "0%", width: "100%", borderRadius: 0 });
					}
					if (key === "logo") {
						var lsrc = (logoSrc && logoSrc.indexOf("white_blank") === -1) ? logoSrc : LOGO_FALLBACK;
						$item.html('<img src="' + lsrc + '" alt="Logo">');
					} else if (key === "photo") {
						var photoSrc = sampleVals.photo_url || FALLBACK;
						$item.html('<img src="' + photoSrc + '" alt="Photo">').css("border-color", main);
					} else if (key === "badge" || key === "moto") {
						$item.text(sampleVals[key] || fieldLabels[key]).css("background", main);
					} else if (key === "header1" || key === "header2") {
						$item.text(sampleVals[key] || "—").css({ fontWeight: 600, letterSpacing: ".02em" });
					} else if (key === "school_name") {
						$item.html('<span class="ss-ed-label">' + fieldLabels[key] + '</span> ' + (sampleVals[key] || "—"));
					} else if (labeledKeys.indexOf(key) >= 0) {
						$item.html('<span class="ss-ed-label">' + fieldLabels[key] + '</span>' + (sampleVals[key] || ""));
					} else {
						$item.html((sampleVals[key] || fieldLabels[key] || ""));
					}
					$items.append($item);
				});
				$toggles.append('<div style="width:100%;font-size:.82rem;color:#64748b;margin-bottom:.35rem;">Show DB fields (untick to hide). Header text 1/2 &amp; school motto stay on.</div>');
				Object.keys(dbToggleLabels).forEach(function (key) {
					var f = layoutState.fields[key] || { visible: true };
					var $lab = $('<label><input type="checkbox" data-toggle-field="' + key + '"> ' + dbToggleLabels[key] + '</label>');
					var $cb = $lab.find("input");
					$cb.prop("checked", !!f.visible);
					if (audience === "staff" && key === "post") {
						$cb.prop("checked", true).prop("disabled", true);
						$lab.append(' <span class="text-danger" style="font-size:.8em;">(required)</span>');
					}
					if (key === "names" || key === "photo") {
						$cb.prop("checked", true).prop("disabled", true);
						$lab.append(' <span class="text-muted" style="font-size:.8em;">(required)</span>');
						if (layoutState.fields[key]) layoutState.fields[key].visible = true;
					}
					$toggles.append($lab);
				});
				bindDrag();
			}
			function bindDrag() {
				var dragging = null;
				$items.find(".ss-ed-item").off(".ssdrag").on("mousedown.ssdrag touchstart.ssdrag", function (ev) {
					ev.preventDefault();
					var $el = $(this);
					$items.find(".ss-ed-item").removeClass("is-active");
					$el.addClass("is-active");
					var canvas = $canvas[0];
					var rect = canvas.getBoundingClientRect();
					var pt = ev.type.indexOf("touch") === 0 ? ev.originalEvent.touches[0] : ev;
					dragging = {
						key: $el.data("key"),
						el: $el,
						ox: pt.clientX - $el[0].getBoundingClientRect().left,
						oy: pt.clientY - $el[0].getBoundingClientRect().top,
						rect: rect
					};
				});
				$(document).off(".ssdragmove." + audience).on("mousemove.ssdragmove." + audience + " touchmove.ssdragmove." + audience, function (ev) {
					if (!dragging) return;
					if (dragging.key === "header1" || dragging.key === "header2" || dragging.key === "moto" || dragging.key === "badge") {
						return; // reserved full-bleed / header slots stay fixed
					}
					var pt = ev.type.indexOf("touch") === 0 ? ev.originalEvent.touches[0] : ev;
					var x = ((pt.clientX - dragging.rect.left - dragging.ox) / dragging.rect.width) * 100;
					var y = ((pt.clientY - dragging.rect.top - dragging.oy) / dragging.rect.height) * 100;
					x = Math.max(0, Math.min(95, x));
					y = Math.max(0, Math.min(95, y));
					dragging.el.css({ left: x + "%", top: y + "%" });
					if (layoutState.fields[dragging.key]) {
						layoutState.fields[dragging.key].x = Math.round(x * 10) / 10;
						layoutState.fields[dragging.key].y = Math.round(y * 10) / 10;
					}
				}).on("mouseup.ssdragmove." + audience + " touchend.ssdragmove." + audience, function () {
					dragging = null;
				});
			}

			$root.on("change", "[data-toggle-field]", function () {
				var key = $(this).data("toggle-field");
				if (!layoutState.fields[key]) return;
				if (RESERVED_KEYS.indexOf(key) >= 0 || key === "names" || key === "photo" || (audience === "staff" && key === "post")) {
					$(this).prop("checked", true);
					layoutState.fields[key].visible = true;
					return;
				}
				layoutState.fields[key].visible = $(this).is(":checked");
				renderEditor();
			});
			$tplChoice.on("click", ".ss-tpl-card", function () {
				var tpl = $(this).data("template");
				var ori = $(this).data("orientation") || "landscape";
				$tplChoice.find(".ss-tpl-card").removeClass("is-on");
				$(this).addClass("is-on");
				$root.find("input[name='" + oriInputName + "'][value='" + ori + "']").prop("checked", true);
				$oriChoice.find("label").removeClass("is-on");
				$root.find("input[name='" + oriInputName + "']:checked").closest("label").addClass("is-on");
				applyTemplateDefaults(tpl, ori);
				$status.text("Template “" + tpl + "” (" + ori + ") loaded — save to keep.");
			});
			$root.on("change", "input[name='" + oriInputName + "']", function () {
				var ori = $(this).val();
				var tpl = layoutState.template || ($tplChoice.find(".ss-tpl-card.is-on").data("template")) || "ocean";
				$oriChoice.find("label").removeClass("is-on");
				$(this).closest("label").addClass("is-on");
				applyTemplateDefaults(tpl, ori);
				$status.text("Orientation changed — preview updated. Save template to keep.");
				saveCardPreset(oriField, ori, $oriChoice);
			});
			$root.on("change", "input[name='" + prefix + "_card_bg_mode']", function () {
				var mode = $(this).val();
				saveCardPreset(bgModeField, mode, $bgModeChoice);
				$aiPanel.show();
			});
			$root.on("click", ".btn-save-card-layout", function () {
				$status.text("Saving…");
				if (audience === "staff" && layoutState.fields && layoutState.fields.post) {
					layoutState.fields.post.visible = true;
				}
				$.post("<?= base_url('save_card_layout'); ?>", {
					audience: audience,
					template: layoutState.template || ($tplChoice.find(".ss-tpl-card.is-on").data("template")) || "ocean",
					orientation: currentOrientation(),
					fields: JSON.stringify(layoutState.fields || {})
				}, function (data) {
					if (data && data.success) {
						if (data.orientation) {
							$root.find("input[name='" + oriInputName + "'][value='" + data.orientation + "']").prop("checked", true);
							$oriChoice.find("label").removeClass("is-on");
							$root.find("input[name='" + oriInputName + "']:checked").closest("label").addClass("is-on");
							syncCanvasSize();
						}
						$status.text("Template saved");
						toastada.success(data.success);
					} else {
						$status.text("");
						toastada.error((data && data.error) || "Save failed");
					}
				}, "json").fail(function (xhr) {
					$status.text("");
					var msg = "Save failed";
					try { msg = JSON.parse(xhr.responseText).error || msg; } catch (e) {}
					toastada.error(msg);
				});
			});
			$root.on("click", ".btn-reset-card-layout", function () {
				var tpl = layoutState.template || ($tplChoice.find(".ss-tpl-card.is-on").data("template")) || "ocean";
				$.post("<?= base_url('reset_card_layout'); ?>", {
					audience: audience,
					template: tpl,
					orientation: currentOrientation()
				}, function (data) {
					if (data && data.layout) {
						layoutState = data.layout;
						if (!layoutState.fields) layoutState.fields = {};
						if (data.orientation) {
							$root.find("input[name='" + oriInputName + "'][value='" + data.orientation + "']").prop("checked", true);
							$oriChoice.find("label").removeClass("is-on");
							$root.find("input[name='" + oriInputName + "']:checked").closest("label").addClass("is-on");
						}
						renderEditor();
						$status.text("Reset to template defaults");
						toastada.success(data.success || "Reset");
					} else {
						toastada.error((data && data.error) || "Reset failed");
					}
				}, "json");
			});
			$root.on("click", ".btn-generate-card-bg, .btn-regenerate-card-bg", function () {
				var $btn = $(this);
				var isRegen = $btn.hasClass("btn-regenerate-card-bg");
				var $genBtn = $root.find(".btn-generate-card-bg").first();
				var $regenBtn = $root.find(".btn-regenerate-card-bg").first();
				var $st = $root.find(".card-ai-status").first();
				var $box = $root.find(".card-ai-proposals").first();
				var ori = currentOrientation();
				var tpl = layoutState.template || ($tplChoice.find(".ss-tpl-card.is-on").data("template")) || "ocean";
				$genBtn.prop("disabled", true);
				$regenBtn.prop("disabled", true);
				$st.text(isRegen
					? "Re-analyzing template and generating new backgrounds…"
					: "Analyzing template layout, then generating 3 backgrounds…");
				$box.hide().empty();
				$.ajax({
					url: "<?= base_url('generate_card_background'); ?>",
					method: "POST",
					data: {
						type: audience,
						orientation: ori,
						template: tpl,
						fields: JSON.stringify(layoutState.fields || {}),
						regenerate: isRegen ? 1 : 0
					},
					dataType: "json",
					timeout: 360000
				}).done(function (data) {
					if (data && data.proposals && data.proposals.length) {
						$st.text(data.success || "Pick a background");
						var frameClass = ori === "portrait" ? "is-portrait" : "is-landscape";
						data.proposals.forEach(function (p) {
							var url = (p.url || "") + ((p.url || "").indexOf("?") >= 0 ? "&" : "?") + "v=" + Date.now();
							var $card = $('<button type="button" class="ss-ai-proposal"></button>')
								.attr("data-filename", p.filename || "")
								.attr("data-url", url);
							$card.append('<div class="ss-bg-frame ' + frameClass + '"><img src="' + url.replace(/"/g, "&quot;") + '" alt=""></div>');
							$card.append("<strong>" + (p.label || "Option") + "</strong>");
							$card.append("<span>Click to use on " + audience + " card</span>");
							$box.append($card);
						});
						$box.show();
						$regenBtn.show();
						if (data.source === "gemini") toastada.success(data.success || "Proposals ready");
						else toastada.error(data.success || "Background generation unavailable");
					} else {
						$st.text("");
						toastada.error((data && (data.error || data.msg)) || "<?= lang("app.fatalErr"); ?>");
					}
				}).fail(function (xhr) {
					$st.text("");
					var msg = "<?= lang("app.systemErr"); ?>";
					try { msg = JSON.parse(xhr.responseText).error || msg; } catch (e) {}
					toastada.error(msg);
				}).always(function () {
					$genBtn.prop("disabled", false);
					$regenBtn.prop("disabled", false);
				});
			});
			$root.on("click", ".ss-ai-proposal", function () {
				var $card = $(this);
				var filename = $card.data("filename");
				var url = $card.data("url");
				if (!filename) return;
				$root.find(".ss-ai-proposal").removeClass("is-on");
				$card.addClass("is-on");
				var $st = $root.find(".card-ai-status").first();
				$st.text("Applying…");
				$.post("<?= base_url('apply_card_background_proposal'); ?>", {
					type: audience,
					filename: filename,
					orientation: currentOrientation()
				}, function (data) {
					if (data && data.success) {
						var applied = (data.url || url) + ((data.url || url).indexOf("?") >= 0 ? "&" : "?") + "v=" + Date.now();
						setImgReal($imgBg, applied);
						$clr.show();
						refreshEditorBg();
						$st.text("Background applied — click Regenerate for another set");
						$root.find(".btn-regenerate-card-bg").show();
						toastada.success(data.success);
					} else {
						$st.text("");
						toastada.error((data && data.error) || "Could not apply background");
					}
				}, "json").fail(function () {
					$st.text("");
					toastada.error("<?= lang("app.systemErr"); ?>");
				});
			});

			renderEditor();
			return {
				audience: audience,
				render: renderEditor,
				refreshBg: refreshEditorBg,
				sync: syncCanvasSize,
				$imgBg: $imgBg,
				$clr: $clr
			};
		}

		$(".card-audience").each(function () {
			var scope = createCardScope($(this));
			cardScopes[scope.audience] = scope;
		});
		refreshCardHeadersFromBasicInfo();

		function refreshLivePreview() {
			Object.keys(cardScopes).forEach(function (k) {
				if (cardScopes[k] && cardScopes[k].render) cardScopes[k].render();
			});
		}

		$(document).on("click", ".btn-clear-bg", function () {
			var $btn = $(this);
			var field = $btn.data("target-field");
			var imgSel = $btn.data("imageview");
			if (!confirm('<?= lang("app.clearBackground"); ?>?')) return;
			var id = $("#settings_section").data("id");
			$.post("<?=base_url('manipulate_settings/');?>text", "id=" + id + "&target=" + field + "&val=", function (data) {
				if (data.hasOwnProperty("success")) {
					setImgFallback($(imgSel), BG_FALLBACK);
					$btn.hide();
					refreshLivePreview();
					toastada.success('<?= lang("app.saveSuccess");?>');
				} else {
					toastada.error((data && data.error) || '<?= lang("app.fatalErr"); ?>');
				}
			}).fail(function () {
				toastada.error('<?= lang("app.systemErr"); ?>');
			});
		});

		// After background upload, refresh matching scope
		$(document).on("change", ".in_card_backg", function () {
			setTimeout(refreshLivePreview, 800);
		});

		function saveCardPreset(target, val, $group) {
			var id = $("#settings_section").data("id");
			$.post("<?=base_url('manipulate_settings/');?>text", "id=" + id + "&target=" + target + "&val=" + encodeURIComponent(val), function (data) {
				if (data.hasOwnProperty("error")) {
					toastada.error('<?= lang("app.saveFail");?>' + (data.error || data.msg || ''));
					return;
				}
				if ($group && $group.length) {
					$group.find("label").removeClass("is-on");
					$group.find("input[value='" + val + "']").closest("label").addClass("is-on");
				}
				refreshLivePreview();
				toastada.success('<?= lang("app.saveSuccess");?>');
			}).fail(function () {
				toastada.error('<?= lang("app.systemErr"); ?>');
			});
		}

		$(document).on("focusout", ".sptxt", function () {
			setTimeout(refreshLivePreview, 200);
		});

		$(document).on("click", "#dv_select_img", function () {
			$("#in_school_logo")[0].click();
		});
		$("#in_school_logo").on("change", function (e) {
			var file = $(this)[0].files[0];
			var upload = new Upload(file);
			// maby check size or type here with upload.getSize() and upload.getType()
			if (upload.getType() != "image/jpg" && upload.getType() != "image/jpeg" && upload.getType() != "image/png") {
				toastada.error('<?= lang("app.allowedOnly"); ?>')
				return;
			}
			if (upload.getSize() > 5 * 1024 * 1024) {
				toastada.error('<?= lang("app.sizeNeeded"); ?>');
				return;
			}
			setImgReal($("#img_logo"), upload.getSource());
			upload.doUpload("upload_image/school_logo", $("#dv_select_img p"), $("#img_logo"));
			setTimeout(refreshLivePreview, 400);
		});
		$(document).on("click", "#dv_select_headmaster_signature", function () {
			$("#in_headmaster_signature")[0].click();
		});
		$("#in_headmaster_signature").on("change", function (e) {
			var file = $(this)[0].files[0];
			var upload = new Upload(file);
			if (upload.getType() != "image/jpg" && upload.getType() != "image/jpeg" && upload.getType() != "image/png") {
				toastada.error('<?= lang("app.allowedOnly"); ?>')
				return;
			}
			if (upload.getSize() > 5 * 1024 * 1024) {
				toastada.error('<?= lang("app.sizeNeeded"); ?>');
				return;
			}
			setImgReal($("#img_headmaster_signature"), upload.getSource());
			upload.doUpload("upload_image/headmaster_signature", $("#dv_select_headmaster_signature p"), $("#img_headmaster_signature"));
			setTimeout(refreshLivePreview, 400);
		});
		$(document).on("click", "#dv_select_matron_signature", function () {
			$("#in_matron_signature")[0].click();
		});
		$("#in_matron_signature").on("change", function () {
			var file = this.files[0];
			var upload = new Upload(file);
			if (upload.getType() != "image/jpg" && upload.getType() != "image/jpeg" && upload.getType() != "image/png") {
				toastada.error('<?= lang("app.allowedOnly"); ?>');
				return;
			}
			if (upload.getSize() > 5 * 1024 * 1024) {
				toastada.error('<?= lang("app.sizeNeeded"); ?>');
				return;
			}
			setImgReal($("#img_matron_signature"), upload.getSource());
			upload.doUpload("upload_image/matron_signature", $("#dv_select_matron_signature p"), $("#img_matron_signature"));
		});
		$(document).on("click", "#dv_select_patron_signature", function () {
			$("#in_patron_signature")[0].click();
		});
		$("#in_patron_signature").on("change", function () {
			var file = this.files[0];
			var upload = new Upload(file);
			if (upload.getType() != "image/jpg" && upload.getType() != "image/jpeg" && upload.getType() != "image/png") {
				toastada.error('<?= lang("app.allowedOnly"); ?>');
				return;
			}
			if (upload.getSize() > 5 * 1024 * 1024) {
				toastada.error('<?= lang("app.sizeNeeded"); ?>');
				return;
			}
			setImgReal($("#img_patron_signature"), upload.getSource());
			upload.doUpload("upload_image/patron_signature", $("#dv_select_patron_signature p"), $("#img_patron_signature"));
		});
		$(document).on("click", "#dv_select_discipline_signature", function () {
			$("#in_discipline_signature")[0].click();
		});
		$("#in_discipline_signature").on("change", function () {
			var file = this.files[0];
			var upload = new Upload(file);
			if (upload.getType() != "image/jpg" && upload.getType() != "image/jpeg" && upload.getType() != "image/png") {
				toastada.error('<?= lang("app.allowedOnly"); ?>');
				return;
			}
			if (upload.getSize() > 5 * 1024 * 1024) {
				toastada.error('<?= lang("app.sizeNeeded"); ?>');
				return;
			}
			setImgReal($("#img_discipline_signature"), upload.getSource());
			upload.doUpload("upload_image/discipline_signature", $("#dv_select_discipline_signature p"), $("#img_discipline_signature"));
		});

		$(document).on("click", ".dv_select_img_backg", function () {
			$(this).parent().find(".in_card_backg").click();
		});
		$(".in_card_backg").on("change", function (e) {
			var file = $(this)[0].files[0];
			var upload = new Upload(file);
			if (upload.getType() != "image/jpg" && upload.getType() != "image/jpeg" && upload.getType() != "image/png") {
				toastada.error('<?= lang("app.allowedOnly"); ?>')
				return;
			}
			if (upload.getSize() > 5 * 1024 * 1024) {
				toastada.error('<?= lang("app.sizeNeeded"); ?>');
				return;
			}
			var $view = $($(this).data('imageview'));
			setImgReal($view, upload.getSource());
			upload.doUpload("upload_image/"+$(this).data("href"), $($(this).data('target')+" p"), $view, "card background");
			$view.closest(".ss-upload-card").find(".btn-clear-bg, #clr_bg, #clr_bg_sf").show();
			setTimeout(refreshLivePreview, 300);
		});
		$(document).on("click","#btn-remove-signature",function () {
			if (!confirm('<?= lang("app.removalSignature"); ?>'))
				return;
			var id = $("#settings_section").data("id");
			$.post("<?=base_url('manipulate_settings/');?>text", "id=" + id + "&target=headmaster_signature&val=", function (data) {
				if (data.hasOwnProperty("error")) {
					toastada.error('<?= lang("app.removalSignatureFail");?>' + data.msg);
				} else if (data.hasOwnProperty("success")) {
					setImgFallback($("#img_headmaster_signature"), SIG_FALLBACK);
					$("#btn-remove-signature").hide();
					refreshLivePreview();
					toastada.success('<?=lang("app.removalSignatureSuccess"); ?>');
				} else {
					toastada.error('<?= lang("app.fatalErr"); ?>');
				}
			}).fail(function () {
				toastada.error('<?= lang("app.systemErr"); ?>');
			});
		});
	});

$(document).on("click","#btn-remove-matron",function () {
  if (!confirm('<?= lang("app.removalSignature"); ?>')) return;
  var id = $("#settings_section").data("id");
  var fb = $("#ss_brand_assets").data("sig-fallback") || $("#ss_brand_assets").data("fallback");
  $.post("<?=base_url('manipulate_settings/text'); ?>", "id=" + id + "&target=matron_signature&val=", function (data) {
    if (data.hasOwnProperty("success")) {
      $("#img_matron_signature").attr("src", fb).addClass("is-empty");
      $("#btn-remove-matron").hide();
      toastada.success('Matron signature removed');
    }
  });
});

$(document).on("click","#btn-remove-patron",function () {
  if (!confirm('<?= lang("app.removalSignature"); ?>')) return;
  var id = $("#settings_section").data("id");
  var fb = $("#ss_brand_assets").data("sig-fallback") || $("#ss_brand_assets").data("fallback");
  $.post("<?=base_url('manipulate_settings/text'); ?>", "id=" + id + "&target=patron_signature&val=", function (data) {
    if (data.hasOwnProperty("success")) {
      $("#img_patron_signature").attr("src", fb).addClass("is-empty");
      $("#btn-remove-patron").hide();
      toastada.success('Patron signature removed');
    }
  });
});

// Remove Discipline Signature
$(document).on("click","#btn-remove-discipline",function () {
  if (!confirm('<?= lang("app.removalSignature"); ?>')) return;
  var id = $("#settings_section").data("id");
  var fb = $("#ss_brand_assets").data("sig-fallback") || $("#ss_brand_assets").data("fallback");
  $.post("<?=base_url('manipulate_settings/text'); ?>", "id=" + id + "&target=discipline_signature&val=", function (data) {
    if (data.hasOwnProperty("success")) {
      $("#img_discipline_signature").attr("src", fb).addClass("is-empty");
      $("#btn-remove-discipline").hide();
      toastada.success('Discipline signature removed');
    }
  });
});

	var Upload = function (file) {
		this.file = file;
	};

	Upload.prototype.getType = function () {
		return this.file.type;
	};
	Upload.prototype.getSize = function () {
		return this.file.size;
	};
	Upload.prototype.getName = function () {
		return this.file.name;
	};
	Upload.prototype.getSource = function () {
		return URL.createObjectURL(this.file);
	};
	Upload.prototype.doUpload = function (url, loader, img,txt='logo') {
		var that = this;
		var formData = new FormData();

		// add assoc key values, this will be posts values
		formData.append("file", this.file, this.getName());
		formData.append("upload_file", true);
		loader.text("Uploading...");
		$.ajax({
			type: "POST",
			url: window.base_url + url,
			xhr: function () {
				var myXhr = new window.XMLHttpRequest();
				if (myXhr.upload) {
					myXhr.upload.addEventListener('progress', that.progressHandling, false);
				}
				return myXhr;
			},
			success: function (data) {
				// your callback here
				loader.text('<?= lang("app.upLoad"); ?>'+txt);
				if (data.hasOwnProperty("error")) {
					toastada.error('<?= lang("app.upLoadErr"); ?>' + data.error);
					img.prop("src", "");
				} else if (data.hasOwnProperty("success")) {
					toastada.success(data.success);
				} else {
					toastada.error('<?= lang("app.fatalErr"); ?>');
					img.prop("src", "");
				}
			},
			error: function (error) {
				// handle error
				toastada.error('<?= lang("app.systemErr"); ?>');
				img.prop("src", "");
				loader.text("<?= lang("app.upLoadSucc"); ?>"+txt);
			},
			async: true,
			data: formData,
			dataType: "json",
			cache: false,
			contentType: false,
			processData: false,
			timeout: 60000
		});
	};

	Upload.prototype.progressHandling = function (event) {

	};

	// Online registration settings (fees + Babyeyi + requirement PDF)
	$(function () {
		$("#btn_save_app_reg").on("click", function () {
			var $st = $("#app_reg_status").text("Saving…");
			$.post("<?= base_url('save_application_settings'); ?>", {
				registration_fees: $("#app_reg_fees").val(),
				start_date: $("#app_reg_start").val(),
				end_date: $("#app_reg_end").val(),
				babyeyi_required: $("#app_babyeyi_required").is(":checked") ? 1 : 0
			}, function (data) {
				if (data && data.success) {
					$st.text(data.success);
					if (window.toastada) toastada.success(data.success);
				} else {
					$st.text((data && data.error) || "Save failed");
					if (window.toastada) toastada.error((data && data.error) || "Save failed");
				}
			}, "json").fail(function () {
				$st.text("Save failed");
			});
		});
		$("#btn_upload_req_pdf").on("click", function () {
			$("#in_requirement_pdf")[0].click();
		});
		$("#in_requirement_pdf").on("change", function () {
			var file = this.files && this.files[0];
			if (!file) return;
			if (file.type !== "application/pdf" && !/\.pdf$/i.test(file.name)) {
				if (window.toastada) toastada.error("Only PDF allowed");
				return;
			}
			var fd = new FormData();
			fd.append("file", file);
			fd.append("id", $("#settings_section").data("id"));
			$("#app_req_upload_status").text("Uploading…");
			$.ajax({
				url: "<?= base_url('upload_requirement_document'); ?>",
				type: "POST",
				data: fd,
				processData: false,
				contentType: false,
				dataType: "json",
				success: function (data) {
					if (data && data.success) {
						$("#app_req_upload_status").text(data.success);
						$("#app_req_doc_empty").hide();
						$("#app_req_doc_wrap").show();
						$("#app_req_doc_link").attr("href", data.url).find("span").text(data.filename);
						if (!$("#app_req_doc_link span").length) {
							$("#app_req_doc_link").html('<i class="fa fa-file-pdf-o"></i> ' + data.filename);
						}
						if (window.toastada) toastada.success(data.success);
					} else {
						$("#app_req_upload_status").text((data && data.error) || "Upload failed");
					}
				},
				error: function () {
					$("#app_req_upload_status").text("Upload failed");
				}
			});
			$(this).val("");
		});
	});
</script>
 <script>
	 $(function () {
		 $("#custom").spectrum({
			 color: "#22c55e",
			 preferredFormat: "hex",
			 showInput: true
		 });
		 $("#custom").on('change', function () {
			 var color = $(this).spectrum('get').toHexString();
			 $(this).val(color);
		 });

		 // Live Mentions: save without page reload, append row, keep form ready
		 $("#gradeMentionForm").on("submit", function (e) {
			 e.preventDefault();
			 e.stopImmediatePropagation();
			 var $form = $(this);
			 var $btn = $("#btnSaveMention");
			 var html = $btn.html();
			 $btn.prop("disabled", true).html('<i class="fa fa-spinner fa-spin"></i> Saving…');
			 $.ajax({
				 url: $form.attr("action"),
				 type: "POST",
				 dataType: "json",
				 data: $form.serialize(),
				 success: function (data) {
					 $btn.prop("disabled", false).html(html);
					 if (data && data.error) {
						 if (window.toastada) toastada.error(data.error);
						 return;
					 }
					 if (data && data.success && data.grade) {
						 var g = data.grade;
						 var row = '<tr data-id="' + g.id + '">'
							 + '<td>' + $('<div>').text(g.color_title || '').html() + '</td>'
							 + '<td>' + $('<div>').text(String(g.max_point)).html() + '</td>'
							 + '<td>' + $('<div>').text(String(g.min_point)).html() + '</td>'
							 + '<td>' + $('<div>').text(g.title || 'Nursery').html() + '</td>'
							 + '<td style="background-color:' + $('<div>').text(g.color || '#22c55e').html() + '"></td>'
							 + '<td><center><a class="btn btn-danger" data-toggle="modal" data-target="#DeleteGradeModal" data-id="' + g.id + '">'
							 + '<i class="fa fa-trash" style="color: white"></i></a></center></td>'
							 + '</tr>';
						 $("#gradeMentionBody").prepend(row);
						 if (window.toastada) toastada.success(data.success);
						 // Clear for next mention — keep Nursery locked & color picker ready
						 $("#gradeMentionTitle").val("").focus();
						 $("#gradeMentionMax").val("");
						 $("#gradeMentionMin").val("");
						 try {
							 $("#custom").spectrum("set", "#22c55e");
							 $("#custom").val("#22c55e");
						 } catch (err) {}
						 return;
					 }
					 if (window.toastada) toastada.error("Save failed");
				 },
				 error: function () {
					 $btn.prop("disabled", false).html(html);
					 if (window.toastada) toastada.error("System error — try again");
				 }
			 });
		 });
	 });
 </script>
<script>
	$(function () {
		var $acc = $('#accordion.ss-accordion');
		if (!$acc.length) return;

		function isSectionPanel(el) {
			var $el = $(el);
			return $el.hasClass('collapse')
				&& $el.parent().hasClass('ss-acc-item')
				&& $el.parent().parent()[0] === $acc[0];
		}

		function sectionPanels() {
			return $acc.children('.ss-acc-item').children('.collapse');
		}

		function syncChevrons() {
			$acc.children('.ss-acc-item').each(function () {
				var $item = $(this);
				var open = $item.children('.collapse').first().hasClass('show');
				$item.toggleClass('is-open', open);
				$item.find('> .card-header [data-toggle="collapse"]').attr('aria-expanded', open ? 'true' : 'false');
			});
		}

		function closeOtherSections(exceptEl) {
			sectionPanels().each(function () {
				if (this !== exceptEl && $(this).hasClass('show')) {
					$(this).collapse('hide');
				}
			});
		}

		// Belt-and-suspenders: Bootstrap data-parent works with .card > .collapse;
		// this JS backup closes siblings even if data-parent fails.
		$acc.on('show.bs.collapse', function (e) {
			if (!isSectionPanel(e.target)) return;
			closeOtherSections(e.target);
		});

		$acc.on('shown.bs.collapse hidden.bs.collapse', function (e) {
			if (!isSectionPanel(e.target)) return;
			syncChevrons();
		});

		// Start all folded (do not leave .show on any section panel)
		sectionPanels().each(function () {
			$(this).removeClass('show');
			if ($(this).data('bs.collapse')) {
				$(this).collapse('hide');
			}
		});
		syncChevrons();

		var hashMap = {
			'#staff-attendance-settings': '#collapseStaffAttendance',
			'#pedagogical-documents': '#collapsePedagogical',
			'#logo-signatures': '#collapseLogoSig'
		};
		var target = hashMap[window.location.hash];
		if (target && $(target).length) {
			$(target).collapse('show');
			setTimeout(function () {
				var el = document.querySelector(window.location.hash);
				if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
			}, 280);
		}

		$('#ss_periods_box').on('click', '.btn-period-lock', function () {
			var $btn = $(this);
			if ($btn.data('busy')) return;
			var period = $btn.data('period');
			var lock = $btn.data('lock');
			$btn.data('busy', 1).prop('disabled', true);
			$.ajax({
				url: "<?= base_url('toggle_period_lock'); ?>",
				type: "POST",
				dataType: "json",
				data: { period: period, lock: lock },
				success: function (res) {
					if (res && res.success) {
						if (window.toastada) toastada.success(res.success);
						else alert(res.success);
						var $row = $btn.closest('.ss-period-row');
						var nowLocked = !!res.locked;
						$row.toggleClass('is-locked', nowLocked);
						$row.find('.ss-period-status').text(nowLocked ? 'Locked' : 'Open');
						$btn
							.toggleClass('btn-lock', !nowLocked)
							.toggleClass('btn-unlock', nowLocked)
							.data('lock', nowLocked ? 0 : 1)
							.text(nowLocked ? 'Unlock' : 'Lock');
					} else {
						var err = (res && res.error) ? res.error : 'Could not update period lock';
						if (window.toastada) toastada.error(err);
						else alert(err);
					}
				},
				error: function () {
					if (window.toastada) toastada.error('Could not update period lock');
					else alert('Could not update period lock');
				},
				complete: function () {
					$btn.data('busy', 0).prop('disabled', false);
				}
			});
		});
	});
</script>
