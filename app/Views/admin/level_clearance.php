<?php
/** @var array $posts */
/** @var array $menuTree */
/** @var array $fullAccessPosts */
/** @var array $clearanceByPost */
/** @var array $customByPost */
/** @var array $defaultsByPost */

/** @var array $masterCentralPosts */
/** @var array $masterCentralDefaults */

$fullFlip = array_flip($fullAccessPosts ?? []);
$centralFlip = array_flip($masterCentralPosts ?? []);
?>
<style>
.lc-wrap { display:flex; gap:1rem; min-height:520px; }
.lc-posts {
	width:240px; flex-shrink:0; border:1px solid #dee2e6; border-radius:6px;
	overflow:auto; max-height:70vh; background:#fff;
}
.lc-posts button {
	display:block; width:100%; text-align:left; border:0; border-bottom:1px solid #f0f0f0;
	background:#fff; padding:.7rem .9rem; cursor:pointer; font-size:.9rem;
}
.lc-posts button:hover { background:#f7f9fc; }
.lc-posts button.active { background:#e8f0fe; border-left:3px solid #3f6ad8; font-weight:600; }
.lc-posts .lc-badge {
	display:inline-block; font-size:.68rem; font-weight:600; padding:.1rem .35rem;
	border-radius:3px; margin-left:.35rem; vertical-align:middle;
}
.lc-badge-lock { background:#fff3cd; color:#856404; }
.lc-badge-custom { background:#d4edda; color:#155724; }
.lc-main { flex:1; border:1px solid #dee2e6; border-radius:6px; background:#fff; display:flex; flex-direction:column; min-width:0; }
.lc-toolbar {
	display:flex; flex-wrap:wrap; align-items:center; gap:.5rem;
	padding:.75rem 1rem; border-bottom:1px solid #eee;
}
.lc-toolbar h5 { margin:0; flex:1; font-size:1rem; }
.lc-body { padding:1rem; overflow:auto; max-height:62vh; }
.lc-group {
	border:1px solid #e9ecef; border-radius:5px; margin-bottom:.65rem; overflow:hidden;
}
.lc-group-h {
	display:flex; align-items:center; gap:.5rem; padding:.55rem .75rem;
	background:#f8f9fa; cursor:pointer; user-select:none;
}
.lc-group-h .lc-title { flex:1; font-weight:600; font-size:.9rem; }
.lc-group-body { padding:.4rem .75rem .65rem 2rem; display:none; border-top:1px solid #eee; }
.lc-sub { margin:.45rem 0 .45rem .5rem; border-color:#eef1f4; }
.lc-sub > .lc-group-h { background:#fbfcfd; padding:.45rem .65rem; }
.lc-sub > .lc-group-body { padding-left:1.25rem; }
.lc-sub .lc-child { padding-left:.25rem; }
.lc-group.open .lc-group-body { display:block; }
.lc-group.open .lc-chevron { transform:rotate(90deg); }
.lc-chevron { transition:transform .15s; color:#888; }
.lc-check { margin-right:.35rem; }
.lc-child { display:block; padding:.2rem 0; font-size:.88rem; }
.lc-locked-note {
	background:#fff8e1; border:1px solid #ffe082; color:#6d4c00;
	padding:.65rem .85rem; border-radius:4px; margin-bottom:.75rem; font-size:.88rem;
}
.lc-always { opacity:.7; font-style:italic; }
@media (max-width:900px) {
	.lc-wrap { flex-direction:column; }
	.lc-posts { width:100%; max-height:180px; }
}
</style>

<div class="app-inner-layout app-inner-layout-page">
	<div class="app-inner-layout__wrapper">
		<div class="app-inner-layout__content">
			<div class="tab-content">
				<div class="container-fluid">
					<div class="card mb-3">
						<div class="card-header-tab card-header">
							<div class="card-header-title font-size-lg text-capitalize font-weight-normal">
								<i class="header-icon typcn typcn-lock-closed text-muted opacity-6"></i>
								<?= esc($title ?? 'Level clearance'); ?>
							</div>
						</div>
						<div class="card-body">
							<p class="text-muted mb-3" style="max-width:720px;">
								Choose which school-dashboard menus each staff post can open.
								Head master, Director of studies, and Headmistress always have full access on the master school.
								Posts without a saved override keep the code defaults.
								Menu list is synced automatically from the dashboard sidebar
								(<code>main.php</code>) — <?= (int) count($menuTree ?? []); ?> groups,
								<?= (int) count(\Config\MenuClearance::allKeys()); ?> keys.
							</p>

							<div class="alert alert-info mb-3" style="max-width:960px;">
								<strong><i class="fa fa-school"></i> Finance budget defaults (automatic)</strong>
								<ul class="mb-0 mt-2 small">
									<li><strong>Full control (everything):</strong> #24 Director of Finance — prepare, edit, approve, cash requests (all schools).</li>
									<li><strong>Prepare &amp; fill budget:</strong> #8 Cashier, #9 Accountant (all schools).</li>
									<li><strong>Budget Dashboard — all child schools:</strong> #24 Director of Finance, #19 Budget Manager, #15 Principal (master school only).</li>
									<li><strong>View-only own school</strong> (no Prepare / Start; no all-branches table):
										#1 Head master, #3 Director of studies, #4 Dean of discipline, #18 Headmistress.</li>
									<li><strong>Child schools — all other posts:</strong> Finance menu is hidden.</li>
								</ul>
								<p class="small mb-0 mt-2 text-muted">These rules apply at login from the post + school type. Resetting a post updates the checklist; leadership still cannot prepare budgets. Director of Finance is never restricted.</p>
							</div>

							<div class="card border mb-3" style="max-width:960px;">
								<div class="card-body py-3">
									<h6 class="font-weight-bold mb-2"><i class="fa fa-sitemap"></i> Master school — central access posts</h6>
									<p class="text-muted small mb-2">
										Staff with these posts, when logged in at a <strong>master</strong> school, can switch to all child school dashboards and use the central budget view.
										Configure here instead of hard-coded post IDs.
									</p>
									<div class="d-flex flex-wrap align-items-center mb-2" id="mcPosts">
										<?php foreach ($posts as $post):
											$pid = (int) ($post['id'] ?? 0);
											$checked = isset($centralFlip[$pid]);
											?>
										<label class="mr-3 mb-1" style="font-size:.9rem;">
											<input type="checkbox" class="mc-post-cb" value="<?= $pid; ?>" <?= $checked ? 'checked' : ''; ?>>
											#<?= $pid; ?> <?= esc($post['title'] ?? ''); ?>
										</label>
										<?php endforeach; ?>
									</div>
									<button type="button" class="btn btn-sm btn-outline-secondary" id="mcResetDefaults">Reset defaults</button>
									<button type="button" class="btn btn-sm btn-primary" id="mcSave"><i class="fa fa-save"></i> Save central posts</button>
									<span id="mcStatus" class="text-muted small ml-2"></span>
								</div>
							</div>

							<div class="lc-wrap">
								<div class="lc-posts" id="lcPosts">
									<?php foreach ($posts as $i => $post):
										$pid = (int) ($post['id'] ?? 0);
										$locked = isset($fullFlip[$pid]);
										$custom = !empty($customByPost[$pid]);
										?>
										<button type="button"
												class="lc-post-btn<?= $i === 0 ? ' active' : ''; ?>"
												data-post-id="<?= $pid; ?>"
												data-locked="<?= $locked ? '1' : '0'; ?>"
												data-title="<?= esc($post['title'] ?? ('Post #' . $pid)); ?>">
											#<?= $pid; ?> <?= esc($post['title'] ?? ''); ?>
											<?php if ($locked): ?>
												<span class="lc-badge lc-badge-lock">Full access</span>
											<?php elseif ($custom): ?>
												<span class="lc-badge lc-badge-custom">Custom</span>
											<?php endif; ?>
										</button>
									<?php endforeach; ?>
								</div>
								<div class="lc-main">
									<div class="lc-toolbar">
										<h5 id="lcCurrentTitle">Select a post</h5>
										<button type="button" class="btn btn-sm btn-outline-secondary" id="lcExpandAll">Expand all</button>
										<button type="button" class="btn btn-sm btn-outline-secondary" id="lcCollapseAll">Collapse all</button>
										<button type="button" class="btn btn-sm btn-outline-warning" id="lcResetDefaults">Reset to defaults</button>
										<button type="button" class="btn btn-sm btn-primary" id="lcSave">
											<i class="fa fa-save"></i> Save
										</button>
										<span id="lcStatus" class="text-muted" style="margin-left:.25rem;"></span>
									</div>
									<div class="lc-body" id="lcBody">
										<div class="lc-locked-note" id="lcLockedNote" style="display:none;">
											This post has <strong>full access (locked)</strong>. Menus cannot be restricted.
										</div>
										<div id="lcAccordion"></div>
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

<script>
(function () {
	var menuTree = <?= json_encode($menuTree, JSON_UNESCAPED_UNICODE); ?>;
	var clearanceByPost = <?= json_encode($clearanceByPost, JSON_UNESCAPED_UNICODE); ?>;
	var defaultsByPost = <?= json_encode($defaultsByPost, JSON_UNESCAPED_UNICODE); ?>;
	var customByPost = <?= json_encode($customByPost, JSON_UNESCAPED_UNICODE); ?>;
	var currentPostId = null;
	var locked = false;

	function esc(s) {
		return $('<div>').text(s == null ? '' : String(s)).html();
	}

	function allowedSet(postId) {
		var list = clearanceByPost[postId] || clearanceByPost[String(postId)] || [];
		var set = {};
		for (var i = 0; i < list.length; i++) set[list[i]] = true;
		return set;
	}

	function nodeChecked(node, set) {
		if (node.keys && node.keys.length) {
			for (var i = 0; i < node.keys.length; i++) {
				if (set[node.keys[i]]) return true;
			}
			return false;
		}
		if (node.children && node.children.length) {
			for (var j = 0; j < node.children.length; j++) {
				if (nodeChecked(node.children[j], set)) return true;
			}
			return !!set[node.key];
		}
		return !!set[node.key];
	}

	function renderNodes(nodes, set, depth, always, rootKey) {
		var html = '';
		for (var i = 0; i < nodes.length; i++) {
			var node = nodes[i];
			if (node.children && node.children.length) {
				var subChecked = always || nodeChecked(node, set);
				html += '<div class="lc-group lc-sub depth-' + depth + '" data-group="' + esc(node.key) + '">';
				html += '<div class="lc-group-h">';
				html += '<span class="lc-chevron">&#9654;</span>';
				html += '<label class="mb-0" onclick="event.stopPropagation();">';
				html += '<input type="checkbox" class="lc-check lc-parent' + (always ? ' lc-always' : '') + '" data-key="' + esc(node.key) + '"'
					+ (subChecked ? ' checked' : '')
					+ (locked || always ? ' disabled' : '')
					+ '> ' + esc(node.label || node.key);
				html += '</label>';
				if (!locked && !always) {
					html += '<button type="button" class="btn btn-link btn-sm p-0 lc-select-all" data-group="' + esc(node.key) + '">Select all</button>';
					html += '<button type="button" class="btn btn-link btn-sm p-0 lc-clear-all" data-group="' + esc(node.key) + '">Clear</button>';
				}
				html += '</div>';
				html += '<div class="lc-group-body">' + renderNodes(node.children, set, depth + 1, always, rootKey) + '</div>';
				html += '</div>';
				continue;
			}

			var checked = always;
			if (!checked && node.keys && node.keys.length) {
				checked = node.keys.every(function (k) { return !!set[k]; });
			} else if (!checked) {
				checked = !!set[node.key];
			}

			html += '<label class="lc-child">';
			if (node.keys && node.keys.length) {
				html += '<input type="checkbox" class="lc-check lc-child-cb lc-grouped-cb" data-keys=\'' + esc(JSON.stringify(node.keys)) + '\''
					+ (checked ? ' checked' : '')
					+ (locked || always ? ' disabled' : '')
					+ '> ' + esc(node.label || node.key);
			} else {
				html += '<input type="checkbox" class="lc-check lc-child-cb" data-key="' + esc(node.key) + '"'
					+ (checked ? ' checked' : '')
					+ (locked || always ? ' disabled' : '')
					+ '> ' + esc(node.label || node.key);
			}
			html += '</label>';
		}
		return html;
	}

	function renderAccordion(postId) {
		var set = allowedSet(postId);
		var html = '';
		for (var g = 0; g < menuTree.length; g++) {
			var group = menuTree[g];
			var always = !!group.always;
			var parentChecked = always || nodeChecked(group, set);
			var children = group.children || [];
			html += '<div class="lc-group' + (g < 3 ? ' open' : '') + '" data-group="' + esc(group.key) + '">';
			html += '<div class="lc-group-h">';
			html += '<span class="lc-chevron">&#9654;</span>';
			html += '<label class="mb-0" onclick="event.stopPropagation();">';
			html += '<input type="checkbox" class="lc-check lc-parent' + (always ? ' lc-always' : '') + '" data-key="' + esc(group.key) + '"'
				+ (parentChecked ? ' checked' : '')
				+ (locked || always ? ' disabled' : '')
				+ '> ';
			html += esc(group.label);
			if (always) html += ' <small class="text-muted">(always on)</small>';
			html += '</label>';
			html += '<span class="lc-title"></span>';
			if (!locked && !always && children.length) {
				html += '<button type="button" class="btn btn-link btn-sm p-0 lc-select-all" data-group="' + esc(group.key) + '">Select all</button>';
				html += '<button type="button" class="btn btn-link btn-sm p-0 lc-clear-all" data-group="' + esc(group.key) + '">Clear</button>';
			}
			html += '</div>';
			if (children.length) {
				html += '<div class="lc-group-body">' + renderNodes(children, set, 1, always, group.key) + '</div>';
			}
			html += '</div>';
		}
		$('#lcAccordion').html(html);
	}

	function collectMenus() {
		var keys = {};
		$('#lcAccordion input.lc-check:checked').each(function () {
			var grouped = $(this).data('keys');
			if (grouped && grouped.length) {
				for (var i = 0; i < grouped.length; i++) keys[grouped[i]] = true;
				return;
			}
			var k = $(this).data('key');
			if (k) keys[k] = true;
		});
		$('#lcAccordion .lc-group').each(function () {
			var $g = $(this);
			var parentKey = $g.data('group');
			var anyChild = $g.find('.lc-child-cb:checked').length > 0;
			var parentOn = $g.find('> .lc-group-h .lc-parent').is(':checked');
			if (parentOn || anyChild) keys[parentKey] = true;
		});
		keys['dashboard'] = true;
		keys['profile'] = true;
		return Object.keys(keys);
	}

	function selectPost($btn) {
		$('.lc-post-btn').removeClass('active');
		$btn.addClass('active');
		currentPostId = parseInt($btn.data('post-id'), 10);
		locked = String($btn.data('locked')) === '1';
		$('#lcCurrentTitle').text($btn.data('title') || ('Post #' + currentPostId));
		$('#lcLockedNote').toggle(locked);
		$('#lcSave, #lcResetDefaults').prop('disabled', locked);
		renderAccordion(currentPostId);
		$('#lcStatus').text('');
	}

	function updatePostBadge(postId, custom) {
		var $btn = $('.lc-post-btn[data-post-id="' + postId + '"]');
		$btn.find('.lc-badge-custom').remove();
		if (custom && String($btn.data('locked')) !== '1') {
			$btn.append(' <span class="lc-badge lc-badge-custom">Custom</span>');
		}
		customByPost[postId] = !!custom;
	}

	$(document).on('click', '.lc-post-btn', function () {
		selectPost($(this));
	});

	$(document).on('click', '.lc-group-h', function (e) {
		if ($(e.target).is('input,label,button,a')) return;
		$(this).closest('.lc-group').toggleClass('open');
	});

	$(document).on('change', '.lc-parent', function () {
		if (locked) return;
		var on = $(this).is(':checked');
		var $g = $(this).closest('.lc-group');
		$g.find('.lc-child-cb, .lc-parent').not(this).prop('checked', on);
	});

	$(document).on('click', '.lc-select-all', function (e) {
		e.preventDefault();
		e.stopPropagation();
		if (locked) return;
		var $g = $(this).closest('.lc-group');
		$g.find('.lc-parent, .lc-child-cb').prop('checked', true);
	});

	$(document).on('click', '.lc-clear-all', function (e) {
		e.preventDefault();
		e.stopPropagation();
		if (locked) return;
		var $g = $(this).closest('.lc-group');
		var key = $g.data('group');
		if (key === 'dashboard' || key === 'profile') return;
		$g.find('.lc-parent, .lc-child-cb').prop('checked', false);
	});

	$('#lcExpandAll').on('click', function () {
		$('#lcAccordion .lc-group').addClass('open');
	});
	$('#lcCollapseAll').on('click', function () {
		$('#lcAccordion .lc-group').removeClass('open');
	});

	$('#lcSave').on('click', function () {
		if (!currentPostId || locked) return;
		var $st = $('#lcStatus').text('Saving…');
		$.post('<?= base_url('admin/save_level_clearance'); ?>', {
			post_id: currentPostId,
			menus: JSON.stringify(collectMenus())
		}, function (data) {
			if (data && data.success) {
				clearanceByPost[currentPostId] = data.menus || [];
				updatePostBadge(currentPostId, true);
				$st.text(data.success);
				if (window.toastada) toastada.success(data.success);
			} else {
				$st.text((data && data.error) || 'Save failed');
				if (window.toastada) toastada.error((data && data.error) || 'Save failed');
			}
		}, 'json').fail(function (xhr) {
			var msg = (xhr.responseJSON && xhr.responseJSON.error) || 'Save failed';
			$st.text(msg);
		});
	});

	$('#lcResetDefaults').on('click', function () {
		if (!currentPostId || locked) return;
		if (!confirm('Reset this post to legacy default menus?')) return;
		var $st = $('#lcStatus').text('Resetting…');
		$.post('<?= base_url('admin/save_level_clearance'); ?>', {
			post_id: currentPostId,
			action: 'reset'
		}, function (data) {
			if (data && data.success) {
				clearanceByPost[currentPostId] = data.menus || defaultsByPost[currentPostId] || [];
				updatePostBadge(currentPostId, false);
				renderAccordion(currentPostId);
				$st.text(data.success);
				if (window.toastada) toastada.success(data.success);
			} else {
				$st.text((data && data.error) || 'Reset failed');
			}
		}, 'json').fail(function () {
			$st.text('Reset failed');
		});
	});

	var mcDefaults = <?= json_encode($masterCentralDefaults ?? []); ?>;
	$('#mcResetDefaults').on('click', function () {
		$('.mc-post-cb').each(function () {
			var id = parseInt($(this).val(), 10);
			$(this).prop('checked', mcDefaults.indexOf(id) !== -1);
		});
	});
	$('#mcSave').on('click', function () {
		var ids = [];
		$('.mc-post-cb:checked').each(function () { ids.push(parseInt($(this).val(), 10)); });
		var $st = $('#mcStatus').text('Saving…');
		$.post('<?= base_url('admin/save_master_central_posts'); ?>', {
			post_ids: JSON.stringify(ids)
		}, function (data) {
			if (data && data.success) {
				$st.text(data.success);
				if (window.toastada) toastada.success(data.success);
			} else {
				$st.text((data && data.error) || 'Save failed');
			}
		}, 'json').fail(function () {
			$st.text('Save failed');
		});
	});

	var $first = $('.lc-post-btn').first();
	if ($first.length) selectPost($first);
})();
</script>
