<?php declare(strict_types=1);

/**
 * Test: Headings.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('heading syntax', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutputModule->lineWrap = 180;

	Assert::matchFile(
		__DIR__ . '/expected/heading.html',
		$texy->process(file_get_contents(__DIR__ . '/sources/heading.texy')),
	);
});


test('headings with generated IDs', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutputModule->lineWrap = 180;
	$texy->headingModule->generateID = true;

	Assert::matchFile(
		__DIR__ . '/expected/heading-toc.html',
		$texy->process(file_get_contents(__DIR__ . '/sources/heading-toc.texy')),
	);
});
