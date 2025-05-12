<?php declare(strict_types=1);

/**
 * Test: parse - Horizontal Rule.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('horizontal rule with dashes', function () {
	$texy = new Texy\Texy;

	Assert::match(
		'<hr>',
		$texy->toHtml('---'),
	);
});


test('horizontal rule with asterisks', function () {
	$texy = new Texy\Texy;

	Assert::match(
		'<hr>',
		$texy->toHtml('***'),
	);
});
