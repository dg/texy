<?php declare(strict_types=1);

/**
 * Test: toInlineHtml().
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('toInlineHtml() reads inline syntaxes only and wraps no paragraph', function () {
	$texy = new Texy\Texy;
	Assert::same('<strong>a</strong> - b', $texy->toInlineHtml("**a**\n- b"));
});
