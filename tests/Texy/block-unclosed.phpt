<?php declare(strict_types=1);

/**
 * Test: Open block
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('open block syntax', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutputModule->lineWrap = 180;

	Assert::matchFile(
		__DIR__ . '/expected/block-unclosed.html',
		$texy->process(file_get_contents(__DIR__ . '/sources/block-unclosed.texy')),
	);
});
