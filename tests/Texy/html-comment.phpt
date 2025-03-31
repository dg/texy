<?php declare(strict_types=1);

/**
 * Test: HTML comments.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('HTML comments', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutput->lineWrap = 0;
	Assert::matchFile(
		__DIR__ . '/expected/html-comment.html',
		$texy->process(file_get_contents(__DIR__ . '/sources/html-comment.texy')),
	);
});


test('a comment does not end before its end', function () {
	$texy = new Texy\Texy;
	Assert::same("<p>a<!-- ><b>x</b>--> b</p>\n", $texy->process('a <!--><b>x</b>--> b'));
	Assert::same("<p>a<!-- ><b>x</b>--> b</p>\n", $texy->process('a <!---><b>x</b>--> b'));
});
