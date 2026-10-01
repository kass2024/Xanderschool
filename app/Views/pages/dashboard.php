
<div class="app-inner-layout app-inner-layout-page">
	<div class="app-inner-layout__wrapper">
		<div class="app-inner-layout__content">
			<div class="tab-content">
				<div class="container-fluid">
					<?php if (!empty($accountant_watch)): ?>
					<?php
						$aw = $accountant_watch;
						$awSchools = [];
						foreach ($aw['people'] as $person) {
							$key = (string) $person['school'];
							if (!isset($awSchools[$key])) {
								$awSchools[$key] = ['name' => $key, 'is_master' => !empty($person['is_master']), 'people' => []];
							}
							$awSchools[$key]['people'][] = $person;
						}
						$awMaster = null;
						$awChildren = [];
						foreach ($awSchools as $campus) {
							if (!empty($campus['is_master'])) {
								$awMaster = $campus;
							} else {
								$awChildren[] = $campus;
							}
						}
					?>
					<style>
						.aw-wrap { margin-bottom: 16px; }
						.aw-kpis { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; margin-bottom: 12px; }
						.aw-kpi { background: #fff; border: 1px solid #e6e8ee; border-top: 4px solid #1d4ed8; border-radius: 12px; padding: 12px 14px; }
						.aw-kpi b { display: block; font-size: 1.55rem; line-height: 1.1; color: #1d4ed8; }
						.aw-kpi span { display: block; margin-top: 4px; color: #6b7280; font-size: .78rem; }
						.aw-kpi.green { border-top-color: #16a34a; }
						.aw-kpi.green b { color: #16a34a; }
						.aw-kpi.red { border-top-color: #dc2626; }
						.aw-kpi.red b { color: #dc2626; }
						.aw-master { border: 2px solid #1d4ed8; background: linear-gradient(180deg, #eff6ff 0%, #fff 72%); border-radius: 14px; padding: 14px; margin-bottom: 12px; }
						.aw-badge { display: inline-block; background: #1d4ed8; color: #fff; border-radius: 999px; font-size: .72rem; padding: 2px 8px; margin-bottom: 8px; }
						.aw-master h3, .aw-card h4 { margin: 0 0 8px; }
						.aw-master h3 { color: #1d4ed8; font-size: 1.15rem; }
						.aw-grid { display: grid; grid-template-columns: 1fr; gap: 10px; }
						.aw-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 12px; }
						.aw-card.tone-blue { border-top: 4px solid #1d4ed8; }
						.aw-card.tone-blue h4 { color: #1d4ed8; }
						.aw-card.tone-green { border-top: 4px solid #16a34a; }
						.aw-card.tone-green h4 { color: #16a34a; }
						.aw-card.tone-red { border-top: 4px solid #dc2626; }
						.aw-card.tone-red h4 { color: #dc2626; }
						.aw-person { display: flex; justify-content: space-between; gap: 8px; background: rgba(255,255,255,.9); border-radius: 10px; padding: 8px 10px; margin-top: 8px; }
						.aw-person strong { display: block; }
						.aw-person em { font-style: normal; color: #6b7280; font-size: .75rem; }
						.aw-in { color: #15803d; font-weight: 700; }
						.aw-out { color: #dc2626; font-weight: 700; }
						.aw-search { max-width: 360px; margin: 0 0 12px; }
						@media (min-width: 700px) {
							.aw-kpis { grid-template-columns: repeat(3, minmax(0, 1fr)); }
							.aw-grid { grid-template-columns: 1fr 1fr; }
						}
					</style>
					<div class="aw-wrap">
						<div class="aw-kpis">
							<div class="aw-kpi"><b><?= count($aw['people']); ?></b><span>Accountants, all schools</span></div>
							<div class="aw-kpi green"><b><?= (int) $aw['in']; ?></b><span>In today</span></div>
							<div class="aw-kpi red"><b><?= (int) $aw['absent']; ?></b><span>Absent today</span></div>
						</div>
						<?php if ($awMaster): ?>
						<div class="aw-card-block aw-master" data-find="<?= esc(strtolower($awMaster['name'] . ' ' . implode(' ', array_column($awMaster['people'], 'name')))); ?>">
							<div class="aw-badge">Master school</div>
							<h3><?= esc($awMaster['name']); ?></h3>
							<?php foreach ($awMaster['people'] as $person): ?>
								<div class="aw-person">
									<div><strong><?= esc($person['name']); ?></strong><em><?= esc($person['post']); ?></em></div>
									<div class="<?= $person['status'] === 'in' ? 'aw-in' : 'aw-out'; ?>"><?= $person['status'] === 'in' ? 'In · ' . esc($person['time']) : ($person['status'] === 'off' ? 'Off today' : 'Absent'); ?></div>
								</div>
							<?php endforeach; ?>
						</div>
						<?php endif; ?>
						<?php if ($awChildren): ?>
						<input type="search" id="awSearch" class="form-control aw-search" placeholder="Search school or accountant">
						<div class="aw-grid" id="awGrid">
							<?php foreach ($awChildren as $awIndex => $campus):
								$awTone = ['tone-blue', 'tone-green', 'tone-red'][$awIndex % 3];
							?>
							<div class="aw-card <?= $awTone; ?>" data-find="<?= esc(strtolower($campus['name'] . ' ' . implode(' ', array_column($campus['people'], 'name')))); ?>">
								<h4><?= esc($campus['name']); ?></h4>
								<?php foreach ($campus['people'] as $person): ?>
									<div class="aw-person">
										<div><strong><?= esc($person['name']); ?></strong><em><?= esc($person['post']); ?></em></div>
										<div class="<?= $person['status'] === 'in' ? 'aw-in' : 'aw-out'; ?>"><?= $person['status'] === 'in' ? 'In · ' . esc($person['time']) : ($person['status'] === 'off' ? 'Off today' : 'Absent'); ?></div>
									</div>
								<?php endforeach; ?>
							</div>
							<?php endforeach; ?>
						</div>
						<script>
							$(function () {
								$("#awSearch").on("input", function () {
									var q = $.trim($(this).val()).toLowerCase();
									$("#awGrid .aw-card, .aw-master").each(function () {
										var hay = String($(this).attr("data-find") || "");
										$(this).toggle(q === "" || hay.indexOf(q) !== -1);
									});
								});
							});
						</script>
						<?php endif; ?>
						<?php if (!$aw['people']): ?>
							<div class="aw-card">No accountant posts found.</div>
						<?php endif; ?>
					</div>
					<?php endif; ?>
					<div class="card no-shadow bg-transparent no-border rm-borders mb-3">
						<div class="card">
							<div class="no-gutters row">
								<div class="col-md-12 col-lg-4">
									<ul class="list-group list-group-flush">
										<li class="bg-transparent list-group-item">
											<div class="widget-content p-0">
												<div class="widget-content-outer">
													<div class="widget-content-wrapper">
														<div class="widget-content-left">
															<div class="widget-heading"><?= lang("app.totalStudents"); ?>
															</div>
															<div class="widget-subheading"><?= lang("app.thisYearNumbers"); ?>
															</div>
														</div>
														<div class="widget-content-right">
															<div class="widget-numbers text-success">
																<?=$students; ?>
															</div>
														</div>
													</div>
												</div>
											</div>
										</li>
										<li class="bg-transparent list-group-item">
											<div class="widget-content p-0">
												<div class="widget-content-outer">
													<div class="widget-content-wrapper">
														<div class="widget-content-left">
															<div class="widget-heading"><?= lang("app.parents"); ?></div>
															<div class="widget-subheading"><?= lang("app.totalParents"); ?>
															</div>
														</div>
														<div class="widget-content-right">
															<div class="widget-numbers text-primary">
																<?=$parent; ?>
															</div>
														</div>
													</div>
												</div>
											</div>
										</li>
									</ul>
								</div>
								<div class="col-md-12 col-lg-4">
									<ul class="list-group list-group-flush">
										<li class="bg-transparent list-group-item">
											<div class="widget-content p-0">
												<div class="widget-content-outer">
													<div class="widget-content-wrapper">
														<div class="widget-content-left">
															<div class="widget-heading"><?= lang("app.staffs"); ?></div>
															<div class="widget-subheading"><?= lang("app.allEmployees"); ?>
															</div>
														</div>
														<div class="widget-content-right">
															<div class="widget-numbers text-danger">
																<?=$staff; ?>
															</div>
														</div>
													</div>
												</div>
											</div>
										</li>
										<li class="bg-transparent list-group-item">
											<div class="widget-content p-0">
												<div class="widget-content-outer">
													<div class="widget-content-wrapper">
														<div class="widget-content-left">
															<div class="widget-heading"><?= lang("app.permissionsGiven"); ?>
															</div>
															<div class="widget-subheading"><?= lang("app.totalPermissions"); ?>
															</div>
														</div>
														<div class="widget-content-right">
															<div class="widget-numbers text-warning">
																<?=count($permission);?>
															</div>
														</div>
													</div>
												</div>
											</div>
										</li>
									</ul>
								</div>
								<div class="col-md-12 col-lg-4">
									<ul class="list-group list-group-flush">
										<li class="bg-transparent list-group-item">
											<div class="widget-content p-0">
												<div class="widget-content-outer">
													<div class="widget-content-wrapper">
														<div class="widget-content-left">
															<div class="widget-heading"><?= lang("app.totalEvents"); ?>
															</div>
															<div class="widget-subheading"><?= lang("app.eventsCounts"); ?>
															</div>
														</div>
														<div class="widget-content-right">
															<div class="widget-numbers text-success">
																0
															</div>
														</div>
													</div>
												</div>
											</div>
										</li>
										<li class="bg-transparent list-group-item">
											<div class="widget-content p-0">
												<div class="widget-content-outer">
													<div class="widget-content-wrapper">
														<div class="widget-content-left">
															<div class="widget-heading"><?= lang("app.eSMS"); ?></div>
															<div class="widget-subheading"><?= lang("app.smsTotalUsed"); ?>
															</div>
														</div>
														<div class="widget-content-right">
															<div class="widget-numbers text-primary">
																<?=$sms_usage;?>
															</div>
														</div>
													</div>
												</div>
											</div>
										</li>
									</ul>
								</div>
							</div>
						</div>
					</div>
					<?php if (!empty($wisdom_group['schools'])):
						$wgTotals = $wisdom_group['totals'];
						$wgMaster = null;
						$wgChildren = [];
						foreach ($wisdom_group['schools'] as $campus) {
							if (!empty($campus['is_master'])) {
								$wgMaster = $campus;
							} else {
								$wgChildren[] = $campus;
							}
						}
						$wgSingle = !empty($wisdom_group['single']);
						if ($wgSingle && !$wgMaster && $wgChildren) {
							$wgMaster = $wgChildren[0];
							$wgChildren = [];
						}
					?>
					<style>
						.wg-wrap { margin-bottom: 16px; }
						.wg-kpis { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; margin-bottom: 12px; }
						.wg-kpi { background: #fff; border: 1px solid #e6e8ee; border-top: 4px solid #1d4ed8; border-radius: 12px; padding: 12px 14px; min-width: 0; }
						.wg-kpi b { display: block; font-size: 1.55rem; line-height: 1.1; color: #1d4ed8; }
						.wg-kpi.green { border-top-color: #16a34a; }
						.wg-kpi.green b { color: #16a34a; }
						.wg-kpi.red { border-top-color: #dc2626; }
						.wg-kpi.red b { color: #dc2626; }
						a.wg-kpi { text-decoration: none; display: block; }
						a.wg-kpi:hover { box-shadow: 0 4px 14px rgba(15, 23, 42, .08); }
						.wg-metrics a { color: inherit; text-decoration: none; display: block; }
						.wg-metrics a.metric-in { background: #ecfdf5; border: 2px solid #16a34a; }
						.wg-metrics a.metric-in strong, .wg-metrics a.metric-in em { color: #15803d; }
						.wg-metrics a.metric-absent { background: #fef2f2; border: 2px solid #dc2626; }
						.wg-metrics a.metric-absent strong, .wg-metrics a.metric-absent em { color: #dc2626; }
						.wg-metrics a:hover strong { text-decoration: underline; }
						.wg-ranks { display: grid; grid-template-columns: 1fr; gap: 8px; margin-top: 10px; }
						.wg-rank { background: rgba(255,255,255,.9); border-radius: 10px; padding: 8px 10px; }
						.wg-rank h5 { margin: 0 0 6px; font-size: .78rem; text-transform: uppercase; letter-spacing: .03em; }
						.wg-rank.late h5 { color: #dc2626; }
						.wg-rank.early h5 { color: #16a34a; }
						.wg-rank ol { margin: 0; padding-left: 18px; }
						.wg-rank li { font-size: .82rem; margin: 2px 0; }
						@media (min-width: 700px) { .wg-ranks { grid-template-columns: 1fr 1fr; } }
						.wg-kpi span { display: block; margin-top: 4px; color: #6b7280; font-size: .78rem; }
						.wg-master { border: 2px solid #1d4ed8; background: linear-gradient(180deg, #eff6ff 0%, #fff 72%); border-radius: 14px; padding: 14px; margin-bottom: 12px; }
						.wg-master h3 { margin: 0 0 4px; font-size: 1.15rem; color: #1d4ed8; }
						.wg-badge { display: inline-block; background: #1d4ed8; color: #fff; border-radius: 999px; font-size: .72rem; padding: 2px 8px; margin-bottom: 8px; }
						.wg-card.tone-green { border-top: 4px solid #16a34a; }
						.wg-card.tone-green h4 { color: #16a34a; }
						.wg-card.tone-red { border-top: 4px solid #dc2626; }
						.wg-card.tone-red h4 { color: #dc2626; }
						.wg-card.tone-blue { border-top: 4px solid #1d4ed8; }
						.wg-card.tone-blue h4 { color: #1d4ed8; }
						.wg-metrics, .wg-links { display: flex; flex-wrap: wrap; gap: 8px; }
						.wg-metrics { margin-top: 10px; }
						.wg-metrics div, .wg-metrics a { flex: 1 1 88px; background: rgba(255,255,255,.85); border-radius: 10px; padding: 8px 10px; }
						.wg-metrics strong { display: block; font-size: 1.15rem; }
						.wg-metrics em { font-style: normal; color: #6b7280; font-size: .75rem; }
						.wg-links { margin-top: 12px; }
						.wg-links a { flex: 1 1 140px; text-align: center; background: #1d4ed8; color: #fff; border-radius: 8px; padding: 8px 10px; font-size: .85rem; text-decoration: none; }
						.wg-links a.alt { background: #16a34a; }
						.wg-links a.red { background: #dc2626; }
						.wg-flow { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
						.wg-flow div { flex: 1 1 88px; background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 8px 10px; }
						.wg-flow strong { display: block; font-size: 1.15rem; }
						.wg-flow em { font-style: normal; color: #6b7280; font-size: .75rem; }
						.wg-flow .in { border-color: #86efac; background: #f0fdf4; }
						.wg-flow .in strong { color: #15803d; }
						.wg-flow .out { border-color: #fcd34d; background: #fffbeb; }
						.wg-flow .out strong { color: #b45309; }
						.wg-flow .stay { border-color: #93c5fd; background: #eff6ff; }
						.wg-flow .stay strong { color: #1d4ed8; }
						.wg-loc-title { margin: 12px 0 6px; font-size: .78rem; font-weight: 700; letter-spacing: .03em; text-transform: uppercase; color: #1d4ed8; }
						.wg-locs { display: grid; grid-template-columns: repeat(auto-fill, minmax(148px, 1fr)); gap: 8px; }
						.wg-loc { display: block; background: #fff; border: 1px solid #dbeafe; border-radius: 10px; padding: 8px 10px; text-decoration: none; color: inherit; }
						.wg-loc:hover { border-color: #1d4ed8; box-shadow: 0 4px 14px rgba(15, 23, 42, .08); }
						.wg-loc b { display: block; color: #1d4ed8; font-size: .88rem; }
						.wg-loc span { display: block; margin-top: 4px; color: #64748b; font-size: .75rem; }
						.wg-att { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; margin-top: 10px; }
						.wg-att article { background: #f8fafc; border: 1px solid #e5e7eb; border-top: 3px solid #1d4ed8; border-radius: 10px; padding: 7px 8px; min-width: 0; }
						.wg-att article.green { border-top-color: #16a34a; }
						.wg-att article.amber { border-top-color: #d97706; }
						.wg-att article.red { border-top-color: #dc2626; }
						.wg-att span { display: block; font-size: .68rem; font-weight: 700; letter-spacing: .03em; text-transform: uppercase; color: #64748b; }
						.wg-att b { display: block; margin-top: 2px; font-size: 1.05rem; line-height: 1.15; color: #0f172a; }
						.wg-att small { display: block; color: #64748b; font-size: .72rem; }
						.wg-prep { margin-top: 12px; }
						.wg-prep-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin: 0 0 8px; }
						.wg-prep-head h5 { margin: 0; font-size: .78rem; font-weight: 700; letter-spacing: .03em; text-transform: uppercase; color: #1d4ed8; }
						.wg-prep-head a { font-size: .78rem; font-weight: 700; color: #1d4ed8; }
						.wg-prep-grid { display: grid; grid-template-columns: 1fr; gap: 8px; }
						.wg-prep article { background: #fff; border: 1px solid #dbeafe; border-radius: 10px; padding: 8px 10px; min-width: 0; }
						.wg-prep article h6 { margin: 0 0 4px; font-size: .82rem; color: #0f172a; }
						.wg-prep .ok { color: #047857; font-weight: 700; }
						.wg-prep .no { color: #b91c1c; font-weight: 700; margin-left: 8px; }
						.wg-prep ul { list-style: none; margin: 6px 0 0; padding: 0; }
						.wg-prep li { display: flex; justify-content: space-between; gap: 8px; font-size: .8rem; padding: 3px 0; border-top: 1px solid #f1f5f9; }
						.wg-prep li span { color: #64748b; }
						@media (min-width: 700px) { .wg-prep-grid { grid-template-columns: 1fr 1fr; } }
						.wg-book { display: inline-block; margin: 0 0 12px; background: #012F6B; color: #fff; border-radius: 10px; padding: 8px 14px; font-size: .85rem; font-weight: 700; text-decoration: none; }
						.wg-book:hover { color: #fff; background: #011f4b; }
						.wg-grid { display: grid; grid-template-columns: 1fr; gap: 10px; }
						.wg-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 12px; min-width: 0; }
						.wg-card h4 { margin: 0 0 8px; font-size: 1rem; }
						@media (min-width: 700px) {
							.wg-kpis { grid-template-columns: repeat(3, minmax(0, 1fr)); }
							.wg-grid { grid-template-columns: 1fr 1fr; }
						}
						@media (min-width: 1100px) {
							.wg-kpis { grid-template-columns: repeat(6, minmax(0, 1fr)); }
						}
					</style>
					<div class="wg-wrap">
						<?php if (in_array((int) session('soma_post'), [\App\Models\PostsModel::PRINCIPAL_ID, \App\Models\PostsModel::DIRECTOR_ID], true)): ?>
						<a class="wg-book" href="<?= base_url('wisdom-population-report'); ?>">Attendance workbook</a>
						<?php endif; ?>
						<div class="wg-kpis">
							<div class="wg-kpi"><b><?= (int) $wgTotals['students']; ?></b><span><?= $wgSingle ? 'Students' : 'Students, all schools'; ?></span></div>
							<div class="wg-kpi green"><b><?= (int) $wgTotals['boys']; ?></b><span>Boys</span></div>
							<div class="wg-kpi red"><b><?= (int) $wgTotals['girls']; ?></b><span>Girls</span></div>
							<div class="wg-kpi"><b><?= (int) $wgTotals['staff']; ?></b><span>Staff</span></div>
							<div class="wg-kpi green"><b><?= (int) $wgTotals['students_present']; ?></b><span>Students in today</span></div>
							<div class="wg-kpi"><b><?= (int) $wgTotals['student_out']; ?></b><span>Students out today</span></div>
							<div class="wg-kpi"><b><?= (int) $wgTotals['student_inside']; ?></b><span>Students still inside</span></div>
							<a class="wg-kpi green" href="<?= base_url('wisdom-staff-today/0/in'); ?>"><b><?= (int) $wgTotals['staff_present']; ?></b><span>Staff in today</span></a>
							<a class="wg-kpi red" href="<?= base_url('wisdom-staff-today/0/absent'); ?>"><b><?= (int) $wgTotals['staff_absent']; ?></b><span>Staff absent today</span></a>
						</div>
						<?php if ($wgMaster): ?>
						<div class="wg-master">
							<div class="wg-badge"><?= $wgSingle ? 'Your school' : 'Master school'; ?></div>
							<h3><?= esc($wgMaster['name']); ?></h3>
							<div class="wg-metrics">
								<div><strong><?= (int) $wgMaster['students']; ?></strong><em>Students</em></div>
								<div><strong><?= (int) $wgMaster['boys']; ?></strong><em>Boys</em></div>
								<div><strong><?= (int) $wgMaster['girls']; ?></strong><em>Girls</em></div>
								<div><strong><?= (int) $wgMaster['staff']; ?></strong><em>Staff</em></div>
								<?php $matt = $wgMaster['att_summary'] ?? []; ?>
								<div><strong><?= (int) ($matt['daily_present'] ?? 0); ?></strong><em>Present students</em></div>
								<div><strong><?= (int) ($matt['daily_absent'] ?? 0); ?></strong><em>Absent students</em></div>
								<div><strong><?= (int) $wgMaster['student_out']; ?></strong><em>Students out</em></div>
								<div><strong><?= (int) $wgMaster['student_inside']; ?></strong><em>Still inside</em></div>
								<a class="metric-in" href="<?= base_url('wisdom-staff-today/' . (int) $wgMaster['id'] . '/in'); ?>"><strong><?= (int) $wgMaster['staff_present']; ?></strong><em>Staff in today</em></a>
								<a class="metric-absent" href="<?= base_url('wisdom-staff-today/' . (int) $wgMaster['id'] . '/absent'); ?>"><strong><?= (int) $wgMaster['staff_absent']; ?></strong><em>Staff absent today</em></a>
							</div>
							<?php if (!empty($wgMaster['locations'])): ?>
							<div class="wg-loc-title">Student in/out by location</div>
							<div class="wg-locs">
								<?php foreach ($wgMaster['locations'] as $loc): ?>
								<div class="wg-loc">
									<b><?= esc($loc['name']); ?></b>
									<span><?= (int) ($loc['day_in'] ?? 0); ?> dayscholar in · <?= (int) ($loc['day_absent'] ?? 0); ?> dayscholar absent · <?= (int) $loc['checked_out']; ?> out · <?= (int) $loc['absent']; ?> absent</span>
								</div>
								<?php endforeach; ?>
							</div>
							<?php endif; ?>
							<?php if (!empty($wgMaster['prep_summary'])):
								$prep = $wgMaster['prep_summary'];
							?>
							<div class="wg-prep">
								<div class="wg-prep-head">
									<h5>Invigilation today</h5>
									<a href="<?= base_url('prep_invigilation_report'); ?>">Prep attendance</a>
								</div>
								<div class="wg-prep-grid">
									<?php foreach (['morning' => 'Morning prep', 'evening' => 'Evening prep'] as $prepSlot => $prepLabel):
										$prepPack = $prep[$prepSlot] ?? ['present' => 0, 'absent' => 0, 'people' => []];
									?>
									<article>
										<h6><?= esc($prepLabel); ?>
											<span class="ok">Present <?= (int) ($prepPack['present'] ?? 0); ?></span>
											<span class="no">Absent <?= (int) ($prepPack['absent'] ?? 0); ?></span>
										</h6>
										<?php if (empty($prepPack['people'])): ?>
											<div style="color:#64748b;font-size:.8rem;">No invigilators on duty</div>
										<?php else: ?>
										<ul>
											<?php foreach ($prepPack['people'] as $inv): ?>
											<li>
												<div><?= esc($inv['name']); ?><?php if (trim((string) ($inv['post'] ?? '')) !== ''): ?> <span>· <?= esc($inv['post']); ?></span><?php endif; ?></div>
												<?php if (($inv['status'] ?? '') === 'present'): ?>
													<strong class="ok"><?= esc($inv['time'] !== '' ? $inv['time'] : 'Present'); ?></strong>
												<?php else: ?>
													<strong class="no" style="margin-left:0;">Absent</strong>
												<?php endif; ?>
											</li>
											<?php endforeach; ?>
										</ul>
										<?php endif; ?>
									</article>
									<?php endforeach; ?>
								</div>
							</div>
							<?php endif; ?>
							<div class="wg-ranks">
								<div class="wg-rank late"><h5>Most late today</h5><ol><?php foreach (($wgMaster['late_today'] ?? []) as $person): ?><li><?= esc($person['name']); ?><?php if (trim((string) ($person['post'] ?? '')) !== ''): ?> · <?= esc($person['post']); ?><?php endif; ?></li><?php endforeach; ?><?php if (empty($wgMaster['late_today'])): ?><li>No one late after shift start</li><?php endif; ?></ol></div>
								<div class="wg-rank early"><h5>Earliest today</h5><ol><?php foreach (($wgMaster['early_today'] ?? []) as $person): ?><li><?= esc($person['name']); ?><?php if (trim((string) ($person['post'] ?? '')) !== ''): ?> · <?= esc($person['post']); ?><?php endif; ?></li><?php endforeach; ?><?php if (empty($wgMaster['early_today'])): ?><li>No early arrivals</li><?php endif; ?></ol></div>
							</div>
						</div>
						<?php endif; ?>
						<?php if ($wgChildren): ?>
						<input type="search" id="wgSchoolSearch" class="form-control" placeholder="Search school" style="max-width:360px;margin:0 0 12px;">
						<div class="wg-grid" id="wgSchoolGrid">
							<?php foreach ($wgChildren as $wgIndex => $campus):
								$wgTone = ['tone-blue', 'tone-green', 'tone-red'][$wgIndex % 3];
							?>
							<div class="wg-card <?= $wgTone; ?>" data-school-name="<?= esc(strtolower($campus['name'])); ?>">
								<h4><?= esc($campus['name']); ?></h4>
								<div class="wg-metrics">
									<div><strong><?= (int) $campus['students']; ?></strong><em>Students</em></div>
									<div><strong><?= (int) $campus['boys']; ?></strong><em>Boys</em></div>
									<div><strong><?= (int) $campus['girls']; ?></strong><em>Girls</em></div>
									<div><strong><?= (int) $campus['staff']; ?></strong><em>Staff</em></div>
									<a class="metric-in" href="<?= base_url('wisdom-staff-today/' . (int) $campus['id'] . '/in'); ?>"><strong><?= (int) $campus['staff_present']; ?></strong><em>Staff in</em></a>
									<a class="metric-absent" href="<?= base_url('wisdom-staff-today/' . (int) $campus['id'] . '/absent'); ?>"><strong><?= (int) $campus['staff_absent']; ?></strong><em>Staff absent</em></a>
								</div>
								<?php $att = $campus['att_summary'] ?? []; ?>
								<div class="wg-att">
									<article class="green">
										<span>Present students</span>
										<b><?= (int) ($att['daily_present'] ?? 0); ?></b>
										<small>On today's register</small>
									</article>
									<article class="red">
										<span>Absent students</span>
										<b><?= (int) ($att['daily_absent'] ?? 0); ?></b>
										<small>Not on today's register</small>
									</article>
								</div>
								<div class="wg-ranks">
									<div class="wg-rank late"><h5>Most late today</h5><ol><?php foreach (($campus['late_today'] ?? []) as $person): ?><li><?= esc($person['name']); ?><?php if (trim((string) ($person['post'] ?? '')) !== ''): ?> · <?= esc($person['post']); ?><?php endif; ?></li><?php endforeach; ?><?php if (empty($campus['late_today'])): ?><li>No one late after shift start</li><?php endif; ?></ol></div>
									<div class="wg-rank early"><h5>Earliest today</h5><ol><?php foreach (($campus['early_today'] ?? []) as $person): ?><li><?= esc($person['name']); ?><?php if (trim((string) ($person['post'] ?? '')) !== ''): ?> · <?= esc($person['post']); ?><?php endif; ?></li><?php endforeach; ?><?php if (empty($campus['early_today'])): ?><li>No early arrivals</li><?php endif; ?></ol></div>
								</div>
							</div>
							<?php endforeach; ?>
						</div>
						<?php endif; ?>
					</div>
					<?php if ($wgChildren): ?>
					<script>
						$(function () {
							$("#wgSchoolSearch").on("input", function () {
								var q = $.trim($(this).val()).toLowerCase();
								$("#wgSchoolGrid .wg-card").each(function () {
									var name = String($(this).data("school-name") || "");
									$(this).toggle(q === "" || name.indexOf(q) !== -1);
								});
							});
						});
					</script>
					<?php endif; ?>
					<?php endif; ?>
					<div class="mb-3 card">
						<div class="card-header-tab card-header">
							<div
								class="card-header-title font-size-lg text-capitalize font-weight-normal">
								<?= lang("app.leaveChart"); ?>
							</div>
							<div class="btn-actions-pane-right text-capitalize">
								<?= lang("app.leavesStatus"); ?>
							</div>
						</div>
						<div class="no-gutters row">
							<div class="col-sm-12 col-md-12 col-xl-12">
								<div class="row">
									<!-- DONUT CHART -->
									<div class="col-md-6 col-sm-6 pull-left" style="margin-bottom: 15px;background-color: white">
										<div class="box box-danger">
											<div class="box-header with-border">
												<span class="box-title" style="color: rgba(31, 10, 6, 0.6)">
													</span>
											</div>
											<div class="box-body">
												<div class="chart">
													<canvas id="lineChart" style="height:250px"></canvas>
												</div>
											</div>
										</div>
									</div>
									<div class="col-md-6 col-sm-6 pull-left" style="margin-bottom: 15px;background-color: white">
										<div class="box box-danger">
											<div class="box-body">
												<div id="donut-chart" style="height: 300px; width: 100%;"></div>
											</div>
											<!-- /.box-body -->
										</div>
									</div>
								</div>
								<div class="divider m-0 d-md-none d-sm-block"></div>
							</div>
						</div>

					</div>
					<div class="mb-3 card">
						<div class="card-header-tab card-header">
							<div
								class="card-header-title font-size-lg text-capitalize font-weight-normal">
								<i class="header-icon lnr-charts icon-gradient bg-happy-green"> </i>
								<?= lang("app.financeData"); ?>
							</div>
							<div class="btn-actions-pane-right text-capitalize">

							</div>
						</div>
						<div class="no-gutters row">
							<div class="col-sm-12 col-md-12 col-xl-12">
								<div class="row">
									<!-- DONUT CHART -->
									<?php $full=0; $half=0; $none=0; $schoolFeesDeposit=0; $extrafeesdeposit=0;
									foreach ($schoolfees as $fees){
										$expectedScl=$fees['expected'];
										if($fees['expected']==$fees['paid']) {
											$full++;
										}
										if($fees['paid']!=$fees['expected'] and $fees['paid']!=""){
											$half++;
										}
										if($fees['paid']==""){
											$none++;
										}
									}

									?>
									<?php $extrafull=0; $extrahalf=0; $extranone=0;
									foreach ($extrafees as $fees){
										$expectedExt=$fees['expected'];
										if($fees['expected']==$fees['paid']) {
											$extrafull++;
										}
										if($fees['paid']!=$fees['expected'] and $fees['paid']!=""){
											$extrahalf++;
										}
										if($fees['paid']==""){
											$extranone++;
										}
									}
									foreach ($schoolfeesdeposits as $scldeposit){
										$schoolFeesDeposit=$scldeposit['deposit'];
									}
									foreach ($extrafeesdeposits as $extdeposit){
										$extrafeesdeposit=$extdeposit['depositExt'];
									}
									//						print_r($extrafeesdeposits); die();
									?>
									<div class="col-md-6 col-sm-6 pull-left" style="margin-bottom: 15px;background-color: white">
										<div class="box box-danger">
											<div class="box-header with-border">
												<span class="box-title" style="color: rgba(31, 10, 6, 0.6)">
													<?= lang("app.schoolFeesPayments"); ?></span>
											</div>
											<div class="box-body">
												<canvas id="pieChart" style="height:250px"></canvas>
											</div>
											<i class="fa fa-circle" style="color:#3ac47d !important"></i> <a class="link" href="<?=base_url('school_fees_payments/1');?>"><label><?= lang("app.allPayments"); ?></label></a><br>
											<i class="fa fa-circle" style="color:#f7b924 !important"></i> <a class="link" href="<?=base_url('school_fees_payments/2');?>"><label><?= lang("app.payHalf"); ?></label></a><br>
											<i class="fa fa-circle" style="color:#d92550 !important"></i> <a class="link" href="<?=base_url('school_fees_payments/3');?>"><label><?= lang("app.nonePaymentMade"); ?></label></a>
										</div>
									</div>
									<div class="col-md-6 col-sm-6 pull-left" style="margin-bottom: 15px;background-color: white">
										<div class="box box-danger">
											<div class="box-header with-border">
												<span class="box-title" style="color: rgba(31, 10, 6, 0.6)">
													<?= lang("app.xtraFeesPayments"); ?></span>
											</div>
											<div class="box-body">
												<canvas id="pieChart2" style="height:250px"></canvas>
											</div>
											<i class="fa fa-circle" style="color:#3ac47d !important"></i> <a class="link" href="<?=base_url('extra_fees_payments/1');?>"><label><?= lang("app.allPayments"); ?></label></a><br>
											<i class="fa fa-circle" style="color:#f7b924 !important"></i> <a class="link" href="<?=base_url('extra_fees_payments/2');?>"><label><?= lang("app.payHalf"); ?></label></a><br>
											<i class="fa fa-circle" style="color:#d92550 !important"></i> <a class="link" href="<?=base_url('extra_fees_payments/3');?>"><label><?= lang("app.nonePaymentMade"); ?></label></a>
											<!-- /.box-body -->
										</div>
									</div>
								</div>
								<div class="divider m-0 d-md-none d-sm-block"></div>
							</div>
						</div>

					</div>
					<div class="mb-3 card">
						<div class="card-header-tab card-header">
							<div
								class="card-header-title font-size-lg text-capitalize font-weight-normal">
								<?= lang("app.sMStatus");?>
							</div>
							<div class="btn-actions-pane-right text-capitalize">
								<?= lang("app.sMSent");?>
							</div>
						</div>
						<div class="no-gutters row">
							<div class="col-sm-12 col-md-12 col-xl-12">
								<div class="row">
									<div class="col-md-6 col-sm-6 pull-left" style="margin-bottom: 15px;background-color: white">
										<div class="box box-danger">
											<div class="box-body">
												<div id="donut-chart2" style="height: 300px; width: 100%;"></div>
											</div>
											<!-- /.box-body -->
										</div>
									</div>
									<div class="col-md-6 col-sm-6 pull-left" style="margin-bottom: 15px;background-color: white">
										<div class="box box-danger">
											<div class="box-header with-border">
												<span class="box-title" style="color: rgba(31, 10, 6, 0.6)">
													</span>
											</div>
											<div class="box-body">
												<div class="chart">
													<canvas id="lineChart2" style="height:250px"></canvas>
												</div>
											</div>
										</div>
									</div>
								</div>
								<div class="divider m-0 d-md-none d-sm-block"></div>
							</div>
						</div>

					</div>
					<div class="mb-3 card">
						<div class="card-header-tab card-header">
							<div
								class="card-header-title font-size-lg text-capitalize font-weight-normal">
								<i class="header-icon lnr-charts icon-gradient bg-happy-green"> </i>
								<?= lang("app.activeDue-date");?>
							</div>
							<div class="btn-actions-pane-right text-capitalize">
								<button
									class="btn-wide btn-outline-2x mr-md-2 btn btn-outline-focus btn-sm">
									<?= lang("app.viewAll");?>
								</button>
							</div>
						</div>
						<div class="no-gutters row">
							<div class="col-sm-12 col-md-12 col-xl-12">
								<table style="width: 100%;" id="example"
									   class="table table-hover table-striped table-bordered dataTable dtr-inline"
									   role="grid" aria-describedby="example_info">
									<thead>
									<tr role="row">
										<th>#</th>
										<th><?= lang("app.regno");?></th>
										<th><?= lang("app.names");?></th>
										<th><?= lang("app.sClass");?></th>
										<th><?= lang("app.expected");?></th>
										<th><?= lang("app.paid");?></th>
										<th><?= lang("app.debt");?></th>
										<th><?= lang("app.dueDate");?></th>
									</tr>
									</thead>
									<tbody>
									<?php
									$i=1;
									foreach ($scl_due_dates as $due_date){
									?>
										<tr>
											<td><?=$i;?></td>
											<td><?=$due_date['regno'];?></td>
											<td><?=$due_date['student'];?></td>
											<td><?= $due_date['level_name']; ?> <?= $due_date['code']; ?> <?= $due_date['title']; ?></td>
											<td><?=$due_date['expected'];?></td>
											<td><?=$due_date['paid'];?></td>
											<td><?=$due_date['expected']-$due_date['paid'];?></td>
											<td><?=$due_date['due_date'];?></td>
										</tr>
									<?php
										$i++;
									}
									?>
									</tbody>
									<tfoot>
									<tr>
										<th>#</th>
										<th><?= lang("app.regno");?></th>
										<th><?= lang("app.names");?></th>
										<th><?= lang("app.sClass");?></th>
										<th><?= lang("app.expected");?></th>
										<th><?= lang("app.paid");?></th>
										<th><?= lang("app.debt");?></th>
										<th><?= lang("app.dueDate");?></th>
									</tr>
									</tfoot>
								</table>
								<div class="divider m-0 d-md-none d-sm-block"></div>
							</div>
						</div>

					</div>

					<?php if (!empty($installment_due_dates)) : ?>
					<div class="mb-3 card border-warning">
						<div class="card-header-tab card-header">
							<div class="card-header-title font-size-lg text-capitalize font-weight-normal text-warning">
								<i class="header-icon lnr-warning icon-gradient bg-warm-flame"></i>
								Installment promises due
							</div>
						</div>
						<div class="no-gutters row">
							<div class="col-sm-12 col-md-12 col-xl-12">
								<table class="table table-hover table-striped table-bordered mb-0">
									<thead>
									<tr>
										<th>#</th>
										<th><?= lang("app.regno"); ?></th>
										<th><?= lang("app.names"); ?></th>
										<th><?= lang("app.sClass"); ?></th>
										<th>Balance</th>
										<th>Promised date</th>
										<th>Reference</th>
									</tr>
									</thead>
									<tbody>
									<?php $ii = 1; foreach ($installment_due_dates as $inst) : ?>
										<tr>
											<td><?= $ii++; ?></td>
											<td><?= esc($inst['regno'] ?? ''); ?></td>
											<td><?= esc($inst['student'] ?? ''); ?></td>
											<td><?= esc(($inst['level_name'] ?? '') . ' ' . ($inst['code'] ?? '') . ' ' . ($inst['title'] ?? '')); ?></td>
											<td><?= number_format((float) ($inst['balance'] ?? 0)); ?></td>
											<td><?= esc($inst['promised_date'] ?? ''); ?></td>
											<td><?= esc($inst['refNo'] ?? '—'); ?></td>
										</tr>
									<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						</div>
					</div>
					<?php endif; ?>

					<div class="mb-3 card">
						<div class="card-header-tab card-header">
							<div
								class="card-header-title font-size-lg text-capitalize font-weight-normal">
								Weekly Attendance
							</div>
							<div class="btn-actions-pane-right text-capitalize">
								Grow Rate
							</div>
						</div>
						<div class="no-gutters row">
							<div class="col-sm-12 col-md-12 col-xl-12">
								<div class="row">
									<div class="col-md-6 col-sm-6 pull-left" style="margin-bottom: 15px;background-color: white">
										<div class="box box-danger">
											<div class="box-body">
												<div id="donut-chart3" style="height: 300px; width: 100%;"></div>
											</div>
											<!-- /.box-body -->
										</div>
									</div>
									<div class="col-md-6 col-sm-6 pull-left" style="margin-bottom: 15px;background-color: white">
										<div class="box box-danger">
											<div class="box-header with-border">
												<span class="box-title" style="color: rgba(31, 10, 6, 0.6)">
													</span>
											</div>
											<div class="box-body">
												<div class="chart">
													<canvas id="lineChart3" style="height:250px"></canvas>
												</div>
											</div>
										</div>
									</div>
								</div>
								<div class="divider m-0 d-md-none d-sm-block"></div>
							</div>
						</div>

					</div>
				</div>
			</div>
		</div>
	</div>
</div>
<script>
	$(function () {

		// Get context with jQuery - using jQuery's .get() method.
		var pieChartCanvas = $('#pieChart').get(0).getContext('2d')
		var pieChartCanvas2 = $('#pieChart2').get(0).getContext('2d')
		var pieChart       = new Chart(pieChartCanvas)
		var PieData        = [
			{
				value    : <?=$none;?>,
				color    : '#d92550',
				highlight: '#f56954',
				label    : 'None payments'
			},
			{
				value    : <?=$half;?>,
				color    : '#f7b924',
				highlight: '#f3f134',
				label    : 'Pay half payments'
			},
			{
				value    : <?=$full;?>,
				color    : '#3ac47d',
				highlight: '#20a61e',
				label    : 'Finish all payments'
			},
		]
		var pieOptions     = {
			//Boolean - Whether we should show a stroke on each segment
			segmentShowStroke    : true,
			//String - The colour of each segment stroke
			segmentStrokeColor   : '#fff',
			//Number - The width of each segment stroke
			segmentStrokeWidth   : 1,
			//Number - The percentage of the chart that we cut out of the middle
			percentageInnerCutout: 2, // This is 0 for Pie charts
			//Number - Amount of animation steps
			animationSteps       : 250,
			//String - Animation easing effect
			animationEasing      : 'easeOutBounce',
			//Boolean - Whether we animate the rotation of the Doughnut
			animateRotate        : true,
			//Boolean - Whether we animate scaling the Doughnut from the centre
			animateScale         : true,
			//Boolean - whether to make the chart responsive to window resizing
			responsive           : true,
			// Boolean - whether to maintain the starting aspect ratio or not when responsive, if set to false, will take up entire container
			maintainAspectRatio  : true,
			//String - A legend template
			legendTemplate       : '<ul class="<%=name.toLowerCase()%>-legend"><% for (var i=0; i<segments.length; i++){%><li><span style="background-color:<%=segments[i].fillColor%>"></span><%if(segments[i].label){%><%=segments[i].label%><%}%></li><%}%></ul>'
		}
		//Create pie or douhnut chart
		// You can switch between pie and douhnut using the method below.
		pieChart.Doughnut(PieData, pieOptions)
		var pieChart2       = new Chart(pieChartCanvas2)
		var PieData2        = [
			{
				value    : <?=$extranone;?>,
				color    : '#d92550',
				highlight: '#f56954',
				label    : 'None payments'
			},
			{
				value    : <?=$extrahalf; ?>,
				color    : '#f7b924',
				highlight: '#f3f134',
				label    : 'Pay half payments'
			},
			{
				value    : <?=$extrafull;?>,
				color    : '#3ac47d',
				highlight: '#20a61e',
				label    : 'Finish all payments'
			},

		]
		var pieOptions2     = {
			//Boolean - Whether we should show a stroke on each segment
			segmentShowStroke    : true,
			//String - The colour of each segment stroke
			segmentStrokeColor   : '#fff',
			//Number - The width of each segment stroke
			segmentStrokeWidth   : 3,
			//Number - The percentage of the chart that we cut out of the middle
			percentageInnerCutout: 40, // This is 0 for Pie charts
			//Number - Amount of animation steps
			animationSteps       : 700,
			//String - Animation easing effect
			animationEasing      : 'easeOutBounce',
			//Boolean - Whether we animate the rotation of the Doughnut
			animateRotate        : true,
			//Boolean - Whether we animate scaling the Doughnut from the centre
			animateScale         : false,
			//Boolean - whether to make the chart responsive to window resizing
			responsive           : true,
			// Boolean - whether to maintain the starting aspect ratio or not when responsive, if set to false, will take up entire container
			maintainAspectRatio  : true,
			//String - A legend template
			legendTemplate       : '<ul class="<%=name.toLowerCase()%>-legend"><% for (var i=0; i<segments.length; i++){%><li><span style="background-color:<%=segments[i].fillColor%>"></span><%if(segments[i].label){%><%=segments[i].label%><%}%></li><%}%></ul>'
		}
		//Create pie or douhnut chart
		// You can switch between pie and douhnut using the method below.
		pieChart2.Doughnut(PieData2, pieOptions2)
//--------------
		var areaChartData = {
			labels  : ['<?= lang("app.jan"); ?>', '<?= lang("app.feb");?>', '<?= lang("app.mar");?>', '<?= lang("app.apr");?>', '<?= lang("app.may");?>', '<?= lang("app.jun");?>', '<?= lang("app.jul");?>','<?= lang("app.aug");?>','<?= lang("app.sep");?>','<?= lang("app.oct");?>','<?= lang("app.nov");?>','<?= lang("app.dec");?>'],
			datasets: [
				{
					label               : 'Digital Goods',
					fillColor           : 'rgba(60,141,188,0.9)',
					strokeColor         : 'rgba(60,141,188,0.8)',
					pointColor          : '#3b8bba',
					pointStrokeColor    : 'rgba(60,141,188,1)',
					pointHighlightFill  : '#fff',
					pointHighlightStroke: 'rgba(60,141,188,1)',
					data                : <?=$leave_array;?>
				}
			]
		}

		var areaChartData2 = {
			labels  : ['<?= lang("app.jan"); ?>', '<?= lang("app.feb");?>', '<?= lang("app.mar");?>', '<?= lang("app.apr");?>', '<?= lang("app.may");?>', '<?= lang("app.jun");?>', '<?= lang("app.jul");?>','<?= lang("app.aug");?>','<?= lang("app.sep");?>','<?= lang("app.oct");?>','<?= lang("app.nov");?>','<?= lang("app.dec");?>'],
			datasets: [
				{
					label               : 'Digital Goods',
					fillColor           : 'rgba(60,141,188,0.9)',
					strokeColor         : 'rgba(9,188,11,0.8)',
					pointColor          : '#18ba17',
					pointStrokeColor    : 'rgba(60,141,188,1)',
					pointHighlightFill  : '#fff',
					pointHighlightStroke: 'rgba(60,141,188,1)',
					data                : <?=$sms_array;?>
				}
			]
		}

		var areaChartOptions = {
			//Boolean - If we should show the scale at all
			showScale               : true,
			//Boolean - Whether grid lines are shown across the chart
			scaleShowGridLines      : false,
			//String - Colour of the grid lines
			scaleGridLineColor      : 'rgba(0,0,0,.05)',
			//Number - Width of the grid lines
			scaleGridLineWidth      : 1,
			//Boolean - Whether to show horizontal lines (except X axis)
			scaleShowHorizontalLines: true,
			//Boolean - Whether to show vertical lines (except Y axis)
			scaleShowVerticalLines  : true,
			//Boolean - Whether the line is curved between points
			bezierCurve             : true,
			//Number - Tension of the bezier curve between points
			bezierCurveTension      : 0.2,
			//Boolean - Whether to show a dot for each point
			pointDot                : false,
			//Number - Radius of each point dot in pixels
			pointDotRadius          : 4,
			//Number - Pixel width of point dot stroke
			pointDotStrokeWidth     : 1,
			//Number - amount extra to add to the radius to cater for hit detection outside the drawn point
			pointHitDetectionRadius : 20,
			//Boolean - Whether to show a stroke for datasets
			datasetStroke           : true,
			//Number - Pixel width of dataset stroke
			datasetStrokeWidth      : 2,
			//Boolean - Whether to fill the dataset with a color
			datasetFill             : true,
			//String - A legend template
			legendTemplate          : '<ul class="<%=name.toLowerCase()%>-legend"><% for (var i=0; i<datasets.length; i++){%><li><span style="background-color:<%=datasets[i].lineColor%>"></span><%if(datasets[i].label){%><%=datasets[i].label%><%}%></li><%}%></ul>',
			//Boolean - whether to maintain the starting aspect ratio or not when responsive, if set to false, will take up entire container
			maintainAspectRatio     : true,
			//Boolean - whether to make the chart responsive to window resizing
			responsive              : true
		}


		//-------------
		//- LINE CHART -
		//--------------
		var lineChartCanvas          = $('#lineChart').get(0).getContext('2d')
		var lineChart                = new Chart(lineChartCanvas)
		var lineChartOptions         = areaChartOptions
		lineChartOptions.datasetFill = false
		lineChart.Line(areaChartData, lineChartOptions)


		var donutData = [
			{ label: '<?= lang("app.approved"); ?>: '+<?=count($approveds);?>, data: <?=count($approveds);?>, color: '#0073b7' },
			{ label: '<?= lang("app.denied"); ?>: '+<?=count($denieds);?>, data: <?=count($denieds);?>, color: '#3c8dbc' },
		]
		$.plot('#donut-chart', donutData, {
			series: {
				pie: {
					show       : true,
					radius     : 1,
					innerRadius: 0,
					label      : {
						show     : true,
						radius   : 2 / 3,
						formatter: labelFormatter,
						threshold: 0.1
					}

				}
			},
			legend: {
				show: true
			}
		})
		function labelFormatter(label, series) {
			return '<div style="font-size:13px; text-align:center; padding:2px; color: #fff; font-weight: 600;">'
				+ label
				+ '<br>'
		}
		/*
		 * END DONUT CHART
		 */
//-------------
		//- LINE CHART -
		//--------------
		var lineChartCanvas2          = $('#lineChart2').get(0).getContext('2d')
		var lineChart2                = new Chart(lineChartCanvas2)
		var lineChartOptions2         = areaChartOptions
		lineChartOptions2.datasetFill = false
		lineChart2.Line(areaChartData2, lineChartOptions2)


		var donutData2 = [
			{ label: '<?= lang("app.remain");?>: '+<?=$sms_limit-$sms_usage;?>, data: <?=$sms_limit-$sms_usage;?>, color: '#18ba17' },
			{ label: '<?= lang("app.usage");?>: '+<?=$sms_usage;?>, data: <?=$sms_usage;?>, color: 'rgba(11,217,69,0.4)' },
		]
		$.plot('#donut-chart2', donutData2, {
			series: {
				pie: {
					show       : true,
					radius     : 1,
					innerRadius: 0,
					label      : {
						show     : true,
						radius   : 1 / 2,
						formatter: labelFormatter,
						threshold: 0.1
					}

				}
			},
			legend: {
				show: true
			}
		})
		function labelFormatter(label, series) {
			return '<div style="font-size:13px; text-align:center; padding:2px; color: #fff; font-weight: 600;">'
				+ label
				+ '<br>'
		}
		/*
		 * END DONUT CHART
		 */

		var areaChartData3 = {
			labels  : ['Monday', 'Tuesday', 'wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
			datasets: [
				{
					label               : 'days',
					fillColor           : 'rgba(60,141,188,0.9)',
					strokeColor         : 'rgba(9,188,11,0.8)',
					pointColor          : '#18ba17',
					pointStrokeColor    : 'rgba(60,141,188,1)',
					pointHighlightFill  : '#fff',
					pointHighlightStroke: 'rgba(60,141,188,1)',
					data                :<?=$attend_day_array;?>
				}
			]
		}

		var lineChartCanvas3          = $('#lineChart3').get(0).getContext('2d')
		var lineChart3                = new Chart(lineChartCanvas3)
		var lineChartOptions2         = areaChartOptions
		lineChartOptions2.datasetFill = false
		lineChart3.Line(areaChartData3, lineChartOptions2)


		var donutData3 = [
			{ label: 'Present:<?=$present;?>', data: <?=$present;?>, color: '#18ba17' },
			{ label: 'Absent: <?=$absent-$present;?>', data: <?=$absent-$present;?>, color: 'rgba(11,217,69,0.4)' },
		]
		$.plot('#donut-chart3', donutData3, {
			series: {
				pie: {
					show       : true,
					radius     : 1,
					innerRadius: 0,
					label      : {
						show     : true,
						radius   : 1 / 2,
						formatter: labelFormatter,
						threshold: 0.1
					}

				}
			},
			legend: {
				show: true
			}
		})

	})
</script>
