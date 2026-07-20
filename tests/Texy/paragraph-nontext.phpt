<?php declare(strict_types=1);

/**
 * Test: Non-text paragraph.
 */

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
