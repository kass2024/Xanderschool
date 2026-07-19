<?php
/** Shared Pedagogical Documents sub-nav */
$section = $ped_section ?? ($section ?? 'analyse');
$yearTitle = $academic_year_title ?? '';
?>
<style>
	.ped-hero {
		background: linear-gradient(135deg, #0f766e 0%, #0e7490 55%, #1d4ed8 100%);
		color: #fff; border-radius: 14px; padding: 1.1rem 1.3rem; margin-bottom: 1rem;
	}
	.ped-hero h3 { margin: 0 0 .3rem; font-weight: 700; font-size: 1.25rem; }
	.ped-hero p { margin: 0; opacity: .92; font-size: .9rem; }
	.ped-tabs { display: flex; flex-wrap: wrap; gap: .4rem; margin-bottom: 1rem; }
	.ped-tabs a {
		display: inline-block; padding: .45rem .85rem; border-radius: 999px; font-size: .84rem; font-weight: 600;
		border: 1px solid #cbd5e1; color: #334155; background: #fff; text-decoration: none;
	}
	.ped-tabs a.is-on { background: #0f766e; border-color: #0f766e; color: #fff; }
</style>
<div class="ped-hero">
	<h3><i class="fa fa-magic"></i> <?= esc($title ?? 'Pedagogical Documents'); ?></h3>
	<p><?= esc($subtitle ?? ''); ?> · Year: <b><?= esc($yearTitle); ?></b></p>
</div>
<div class="ped-tabs">
	<a class="<?= $section === 'analyse' ? 'is-on' : ''; ?>" href="<?= base_url('ped_analyse'); ?>">1. Analyse Curriculum &amp; Chronogram</a>
	<a class="<?= $section === 'scheme' ? 'is-on' : ''; ?>" href="<?= base_url('ped_scheme_of_work'); ?>">2. Scheme of Work</a>
	<a class="<?= $section === 'session' ? 'is-on' : ''; ?>" href="<?= base_url('ped_session_plan'); ?>">3. Session Plan</a>
</div>
