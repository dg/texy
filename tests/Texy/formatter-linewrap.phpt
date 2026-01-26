<?php declare(strict_types=1);

/**
 * Test: Very long lines.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('long line wrapping', function () {
	$texy = new Texy\Texy;
	Assert::same(
		normalizeNewlines(file_get_contents(__DIR__ . '/expected/formatter-linewrap.html')),
		$texy->process(file_get_contents(__DIR__ . '/sources/formatter-linewrap.texy')),
	);
});


test('long line wrapping (variant)', function () {
	$texy = new Texy\Texy;
	Assert::same(
		normalizeNewlines(file_get_contents(__DIR__ . '/expected/formatter-linewrap-alt.html')),
		$texy->process(file_get_contents(__DIR__ . '/sources/formatter-linewrap-alt.texy')),
	);
});
