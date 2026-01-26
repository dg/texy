<?php declare(strict_types=1);

/**
 * Test: Figure module features
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('figure floated left gets its float class besides the figure class', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutput->floatClasses['left'] = 'image-left';
	$texy->htmlOutput->figureClass = 'figure';
	Assert::match(
		'<figure class="figure image-left"><img src="image.jpg" alt="">
	<figcaption>Caption</figcaption>
</figure>
',
		$texy->toHtml('[* image.jpg <] *** Caption'),
	);
});


test('figure floated right', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutput->floatClasses['right'] = 'image-right';
	Assert::match(
		'<figure class="image-right"><img src="image.jpg" alt="">
	<figcaption>Caption</figcaption>
</figure>
',
		$texy->toHtml('[* image.jpg >] *** Caption'),
	);
});


test('text alignment classes do not apply to the float', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutput->textAlignClasses['left'] = 'align-left';
	Assert::match(
		'<figure style="float:left"><img src="image.jpg" alt="">
	<figcaption>Caption</figcaption>
</figure>
',
		$texy->toHtml('[* image.jpg <] *** Caption'),
	);
});


test('figure centered (no float)', function () {
	$texy = new Texy\Texy;
	Assert::match(
		'<figure><img src="image.jpg" alt="">
	<figcaption>Caption</figcaption>
</figure>
',
		$texy->toHtml('[* image.jpg *] *** Caption'),
	);
});
