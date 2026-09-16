<?php
require __DIR__ . '/../app/Helpers/card_uid_helper.php';

function expect_contains(string $needle, array $hay, string $label): void
{
	if (!in_array($needle, $hay, true)) {
		fwrite(STDERR, "FAIL $label: missing $needle in " . implode(',', $hay) . PHP_EOL);
		exit(1);
	}
	echo "OK $label\n";
}

$v = card_uid_lookup_variants('74AF8DA');
expect_contains('074AF8DA', $v, 'odd hex pads to stored UID');
expect_contains('DAF84A07', $v, 'odd hex includes reversed bytes');

$v = card_uid_lookup_variants('122353882');
expect_contains('074AF8DA', $v, 'decimal of 074AF8DA');

$v = card_uid_lookup_variants('074AF8DA');
expect_contains('074AF8DA', $v, 'exact stored UID');
expect_contains('DAF84A07', $v, 'exact includes reverse');

echo "ALL OK\n";
