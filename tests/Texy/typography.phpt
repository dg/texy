<?php declare(strict_types=1);

/**
 * Test: applyTypography()
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


$texy = new Texy\Texy;

Assert::match(
	'„Hello“ © …',
	$texy->applyTypography('"Hello" (c) ...'),
);

// disabled typography is honoured
$texy = new Texy\Texy;
$texy->config->typography = false;
Assert::match(
	'"Hello" (c) ...',
	$texy->applyTypography('"Hello" (c) ...'),
);


// an opening quote without its closing one stays as it is, however long the text after it
$texy = new Texy\Texy;
Assert::same('„a "b“ c', $texy->applyTypography('"a "b" c'));
Assert::same(20_000, substr_count($texy->applyTypography(str_repeat('"a ', 20_000)), '"'));
Assert::same(20_000, substr_count($texy->applyTypography(str_repeat("'a ", 20_000)), "'"));

// numbers chained by hyphens and x, and arrows, take no backtracking
foreach (['1-', 'a1-', '1x', 'a1x', '1x ', '-', '->', '=>', 'a-> '] as $fragment) {
	Assert::noError(fn() => $texy->applyTypography(str_repeat($fragment, 10_000)));
}
