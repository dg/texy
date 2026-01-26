<?php declare(strict_types=1);

/**
 * Test: parse - Headings.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('heading surrounded', function () {
	$texy = new Texy\Texy;

	// With default DYNAMIC balancing, a single heading becomes h1
	Assert::match(
		'<h1>Heading</h1>',
		$texy->process('## Heading'),
	);
});


test('heading underlined', function () {
	$texy = new Texy\Texy;

	// With default DYNAMIC balancing, a single heading becomes h1
	Assert::match(
		'<h1>Heading</h1>',
		$texy->process(<<<'XX'
			Heading
			-------
			XX),
	);
});


test('heading with modifier', function () {
	$texy = new Texy\Texy;
	Assert::match(
		'<h1 class="class">Heading</h1>',
		$texy->process('## Heading .[class]'),
	);
});
