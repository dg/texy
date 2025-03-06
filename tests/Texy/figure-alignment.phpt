<?php declare(strict_types=1);

/**
 * Test: Figure module features
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('figure floated left gets its float class besides the figure class', function () {
	$texy = new Texy\Texy;
	$texy->imageModule->leftClass = 'image-left';
	Assert::match(
		'<div class="figure image-left"><img src="images/image.jpg" alt="">
	<p>Caption</p>
</div>
',
		$texy->process('[* image.jpg <] *** Caption'),
	);
});


test('figure floated right', function () {
	$texy = new Texy\Texy;
	$texy->imageModule->rightClass = 'image-right';
	$texy->figureModule->class = null;
	Assert::match(
		'<div class="image-right"><img src="images/image.jpg" alt="">
	<p>Caption</p>
</div>
',
		$texy->process('[* image.jpg >] *** Caption'),
	);
});


test('text alignment classes do not apply to the float', function () {
	$texy = new Texy\Texy;
	$texy->alignClasses['left'] = 'align-left';
	Assert::match(
		'<div class="figure" style="float:left"><img src="images/image.jpg" alt="">
	<p>Caption</p>
</div>
',
		$texy->process('[* image.jpg <] *** Caption'),
	);
});


test('figure centered (no float)', function () {
	$texy = new Texy\Texy;
	Assert::match(
		'<div class="figure"><img src="images/image.jpg" alt="">
	<p>Caption</p>
</div>
',
		$texy->process('[* image.jpg *] *** Caption'),
	);
});
