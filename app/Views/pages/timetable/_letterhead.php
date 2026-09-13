<?php
/** @var array<string,mixed> $letterhead */
/** @var string $subtitle */
/** @var string $title */
$lh = $letterhead ?? [];
$logoSrc = $lh['logo_data_uri'] ?? $lh['logo_url'] ?? '';
?>
<div class="tt-letterhead">
	<div class="tt-lh-row">
		<?php if ($logoSrc !== ''): ?>
			<div class="tt-lh-logo">
				<img src="<?= esc($logoSrc); ?>" alt="">
			</div>
		<?php endif; ?>
		<div class="tt-lh-info">
			<div class="tt-lh-school"><?= esc(strtoupper($lh['school_name'] ?? 'SCHOOL')); ?></div>
			<?php if (!empty($lh['school_slogan'])): ?>
				<div class="tt-lh-slogan"><?= esc($lh['school_slogan']); ?></div>
			<?php endif; ?>
			<?php if (!empty($lh['school_address'])): ?>
				<div class="tt-lh-line"><?= esc($lh['school_address']); ?></div>
			<?php endif; ?>
			<div class="tt-lh-line tt-lh-contacts">
				<?php if (!empty($lh['school_pobox'])): ?>
					<span>P.O. Box <?= esc($lh['school_pobox']); ?></span>
				<?php endif; ?>
				<?php if (!empty($lh['school_phone'])): ?>
					<span>Tel: <?= esc($lh['school_phone']); ?></span>
				<?php endif; ?>
				<?php if (!empty($lh['school_email'])): ?>
					<span>Email: <?= esc($lh['school_email']); ?></span>
				<?php endif; ?>
				<?php if (!empty($lh['school_website'])): ?>
					<span>Web: <?= esc($lh['school_website']); ?></span>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<div class="tt-lh-doc">
		<div class="tt-lh-doc-title"><?= esc($subtitle ?? ''); ?></div>
		<div class="tt-lh-doc-entity"><?= esc($title ?? ''); ?></div>
		<div class="tt-lh-doc-meta">
			<?php
			$meta = [];
			if (!empty($generated_at)) {
				$meta[] = 'Generated: ' . date('n/j/Y', strtotime($generated_at));
			}
			if (!empty($lh['academic_year_title'])) {
				$meta[] = $lh['academic_year_title'];
			}
			if (!empty($lh['term'])) {
				$meta[] = 'Term ' . (int) $lh['term'];
			}
			echo esc(implode(' | ', $meta));
			?>
		</div>
	</div>
</div>
