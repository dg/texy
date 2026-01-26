<?php declare(strict_types=1);

/**
 * Test: HTML comments.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('HTML comments', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutputModule->lineWrap = 0;
	Assert::matchFile(
		__DIR__ . '/expected/html-comment.html',
		$texy->process(file_get_contents(__DIR__ . '/sources/html-comment.texy')),
	);
});
