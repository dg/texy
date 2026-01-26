<?php declare(strict_types=1);

/**
 * Test: indentation.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('output indentation', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutputModule->baseIndent = 1;

	Assert::matchFile(
		__DIR__ . '/expected/formatter-indent.html',
		$texy->process(file_get_contents(__DIR__ . '/sources/formatter-indent.texy')),
	);
});
