<?php

/**
 * Test: Paragraphs.
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('paragraphs with modifiers', function () {
	$texy = new Texy\Texy;
	$texy->htmlGenerator->linkRoot = 'xxx/';
	$texy->htmlOutputModule->lineWrap = 180;

	Assert::matchFile(
		__DIR__ . '/expected/paragraph.html',
		$texy->process(file_get_contents(__DIR__ . '/sources/paragraph.texy')),
	);
});
