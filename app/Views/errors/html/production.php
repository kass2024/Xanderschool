<!doctype html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="robots" content="noindex">

	<title>Whoops!</title>

	<style type="text/css">
		<?= preg_replace('#[\r\n\t ]+#', ' ', file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'debug.css')) ?>
	</style>
</head>
<body>

	<div class="container text-center">

		<h1 class="headline">Whoops!</h1>

		<p class="lead">We seem to have hit a snag. Please try again later...</p>
		<?php
		$desktopErr = getenv('XANDER_DESKTOP');
		if (($desktopErr === '1' || $desktopErr === 'true') && isset($exception) && $exception instanceof Throwable):
		?>
		<pre style="text-align:left;max-width:900px;margin:24px auto;padding:16px;background:#111827;color:#f9fafb;border-radius:8px;white-space:pre-wrap;font-size:13px"><?= htmlspecialchars($exception->getMessage() . "\n" . $exception->getFile() . ':' . $exception->getLine(), ENT_QUOTES, 'UTF-8') ?></pre>
		<?php endif; ?>

	</div>

</body>

</html>
