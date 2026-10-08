<?php
/**
 * Three term KPI cards: income, expenses, and surplus.
 * $term_figures: optional [1 => ['income'=>, 'expense'=>], ...] for server-rendered pages.
 * The budget editor leaves figures empty and fills .kpi-tN-* from the line grid.
 */
$termFigures = $term_figures ?? [];
$termMeta = [
	1 => ['class' => 't1', 'label' => 'Term I'],
	2 => ['class' => 't2', 'label' => 'Term II'],
	3 => ['class' => 't3', 'label' => 'Term III'],
];
?>
<div class="bp-term-board">
<?php foreach ($termMeta as $n => $meta) {
	$inc = (float) ($termFigures[$n]['income'] ?? 0);
	$exp = (float) ($termFigures[$n]['expense'] ?? 0);
	$net = $inc - $exp;
?>
	<article class="bp-term-card <?= $meta['class']; ?>">
		<header>
			<span><?= $meta['label']; ?></span>
			<span class="bp-term-unit">RWF</span>
		</header>
		<div class="bp-term-metrics">
			<div class="metric-inc">
				<label>Income</label>
				<strong class="kpi-t<?= $n; ?>-inc"><?= number_format($inc, 0); ?></strong>
			</div>
			<div class="metric-exp">
				<label>Expenses</label>
				<strong class="kpi-t<?= $n; ?>-exp"><?= number_format($exp, 0); ?></strong>
			</div>
			<div class="metric-net">
				<label>Surplus</label>
				<strong class="kpi-t<?= $n; ?>-net <?= $net >= 0 ? 'pos' : 'neg'; ?>"><?= number_format($net, 0); ?></strong>
			</div>
		</div>
	</article>
<?php } ?>
</div>
