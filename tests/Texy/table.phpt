<?php declare(strict_types=1);

/**
 * Test: Tables
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('advanced table features', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutputModule->lineWrap = 180;

	Assert::matchFile(
		__DIR__ . '/expected/table.html',
		$texy->process(file_get_contents(__DIR__ . '/sources/table.texy')),
	);
});
