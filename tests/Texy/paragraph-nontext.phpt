<?php declare(strict_types=1);

/**
 * Test: Non-text paragraph.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('nontext paragraph', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutput->imageParagraphTag = 'section';
	$texy->htmlOutput->lineWrap = 0;

	Assert::match(
		'<section class="n"><img src="images/a" alt=""> <img src="images/b" alt=""></section>',
		$texy->process('[* a *] [* b *] .[n]'),
	);
});
