<?php
if (!function_exists('disc_t')) {
	function disc_t(string $en, string $rw): string
	{
		return \App\Models\DisciplineCodeModel::discLang() === 'rw' ? $rw : $en;
	}
}
$discLang = $disc_lang ?? \App\Models\DisciplineCodeModel::discLang();
$discLangCompact = !empty($compact);
?>
<div class="disc-lang-switch<?= $discLangCompact ? ' disc-lang-switch--sidebar' : ''; ?>" data-current="<?= esc($discLang, 'attr'); ?>">
	<button type="button" class="disc-lang-btn<?= $discLang === 'en' ? ' is-active' : ''; ?>" data-lang="en">English</button>
	<button type="button" class="disc-lang-btn<?= $discLang === 'rw' ? ' is-active' : ''; ?>" data-lang="rw">Kinyarwanda</button>
</div>
<script>
(function ($) {
	if (window.__discLangBound) return;
	window.__discLangBound = true;
	$(document).on('click', '.disc-lang-btn', function () {
		var lang = $(this).data('lang');
		if (!lang || lang === $('.disc-lang-switch').first().data('current')) return;
		$.post('<?= base_url('set_disc_lang'); ?>', { lang: lang }).always(function () {
			location.reload();
		});
	});
})(jQuery);
</script>
