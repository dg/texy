<?php

/**
 * Test: Non-text paragraph.
 */

declare(strict_types=1);

use Tester\Assert;
use Texy\Output\Html;

require __DIR__ . '/../bootstrap.php';


test('nontext paragraph', function () {
	$texy = new Texy\Texy;
	$texy->nontextParagraph = new Html\Element('div', ['class' => 'figure']);

	Assert::match(
		'<div class="note figure"><img src="images/image.gif" alt=""></div>',
		$texy->process('
[* image.gif *] .[note]
'),
	);
});
