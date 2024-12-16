<?php declare(strict_types=1);

/**
 * Test: Non-text paragraph.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('nontext paragraph', function () {
	$texy = new Texy\Texy;
	$texy->nontextParagraph = new Texy\HtmlElement('div', ['class' => 'figure']);

	Assert::match(
		'<div class="note figure"><img src="images/image.gif" alt=""></div>',
		$texy->process('
[* image.gif *] .[note]
'),
	);
});
