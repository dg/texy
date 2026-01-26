<?php declare(strict_types=1);

/**
 * Test: Figure module features
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('figure with leftClass', function () {
	$texy = new Texy\Texy;
	$texy->figureModule->leftClass = 'image-left';
	Assert::match(
		'<div class="image-left"><img src="images/image.jpg" alt="">
	<p>Caption</p>
</div>
',
		$texy->process('[* image.jpg <] *** Caption'),
	);
});


test('figure with rightClass', function () {
	$texy = new Texy\Texy;
	$texy->figureModule->rightClass = 'image-right';
	Assert::match(
		'<div class="image-right"><img src="images/image.jpg" alt="">
	<p>Caption</p>
</div>
',
		$texy->process('[* image.jpg >] *** Caption'),
	);
});


test('figure with alignClasses', function () {
	$texy = new Texy\Texy;
	$texy->alignClasses['left'] = 'align-left';
	Assert::match(
		'<div class="figure-align-left"><img src="images/image.jpg" alt="">
	<p>Caption</p>
</div>
',
		$texy->process('[* image.jpg <] *** Caption'),
	);
});


test('figure with float fallback', function () {
	$texy = new Texy\Texy;
	// No leftClass, no alignClasses - should use float style
	Assert::match(
		'<div style="float:left" class="figure"><img src="images/image.jpg" alt="">
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
