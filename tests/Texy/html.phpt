<?php declare(strict_types=1);

/**
 * Test: HTML tags.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


function createTexy()
{
	$texy = new Texy\Texy;
	$texy->htmlOutput->linkBaseUrl = 'xxx/';
	$texy->htmlOutput->lineWrap = 180;
	return $texy;
}


test('html-tags5', function () {
	Assert::matchFile(
		__DIR__ . '/expected/html-tags5.html',
		createTexy()->process(file_get_contents(__DIR__ . '/sources/html-tags5.texy')),
	);
});
