<?php declare(strict_types=1);

/**
 * Test: Paragraphs.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('paragraphs with modifiers', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutput->linkBaseUrl = 'xxx/';
	$texy->htmlOutput->lineWrap = 180;

	Assert::matchFile(
		__DIR__ . '/expected/paragraph.html',
		$texy->toHtml(file_get_contents(__DIR__ . '/sources/paragraph.texy')),
	);
});
