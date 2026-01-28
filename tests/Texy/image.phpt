<?php declare(strict_types=1);

/**
 * Test: Image module features
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


// =============================================================================
// Auto-detect dimensions
// =============================================================================

test('image auto-detect dimensions', function () {
	$texy = new Texy\Texy;
	$texy->imageModule->fileRoot = __DIR__ . '/fixtures/';
	$texy->imageModule->root = '';
	Assert::match(
		'<div><img src="logo.gif" alt="" width="176" height="104"></div>
',
		$texy->process('[* logo.gif *]'),
	);
});


test('image auto-detect dimensions with specified width', function () {
	$texy = new Texy\Texy;
	$texy->imageModule->fileRoot = __DIR__ . '/fixtures/';
	$texy->imageModule->root = '';
	// Specify width only, height should be calculated proportionally
	// Original: 176x104, ratio = 104/176 = 0.59
	// With width=88: height = 88 * 0.59 = 52
	Assert::match(
		'<div><img src="logo.gif" alt="" width="88" height="52"></div>
',
		$texy->process('[* logo.gif 88x? *]'),
	);
});


test('image auto-detect dimensions with specified height', function () {
	$texy = new Texy\Texy;
	$texy->imageModule->fileRoot = __DIR__ . '/fixtures/';
	$texy->imageModule->root = '';
	// Specify height only, width should be calculated proportionally
	// Original: 176x104, ratio = 176/104 = 1.69
	// With height=52: width = 52 * 1.69 = 88
	Assert::match(
		'<div><img src="logo.gif" alt="" width="88" height="52"></div>
',
		$texy->process('[* logo.gif ?x52 *]'),
	);
});


test('image dimensions not detected without fileRoot', function () {
	$texy = new Texy\Texy;
	$texy->imageModule->root = '';
	// No fileRoot set, dimensions should not be added
	Assert::match(
		'<div><img src="logo.gif" alt=""></div>
',
		$texy->process('[* logo.gif *]'),
	);
});


// =============================================================================
// alignClasses
// =============================================================================

test('image with alignClasses', function () {
	$texy = new Texy\Texy;
	$texy->alignClasses['right'] = 'float-right';
	Assert::match(
		'<div><img src="images/image.jpg" alt="" class="float-right"></div>
',
		$texy->process('[* image.jpg >]'),
	);
});


test('image with leftClass', function () {
	$texy = new Texy\Texy;
	$texy->imageModule->leftClass = 'img-left';
	Assert::match(
		'<div><img src="images/image.jpg" alt="" class="img-left"></div>
',
		$texy->process('[* image.jpg <]'),
	);
});


test('image with float fallback', function () {
	$texy = new Texy\Texy;
	// No leftClass, no alignClasses - should use float style
	Assert::match(
		'<div><img src="images/image.jpg" alt="" style="float:left"></div>
',
		$texy->process('[* image.jpg <]'),
	);
});


// =============================================================================
// Link on image
// =============================================================================

test('image with direct link', function () {
	$texy = new Texy\Texy;
	Assert::match(
		'<div><a href="https://example.com"><img src="images/image.jpg" alt=""></a></div>
',
		$texy->process('[* image.jpg *]:https://example.com'),
	);
});


test('image with double colon link (uses main URL)', function () {
	$texy = new Texy\Texy;
	Assert::match(
		'<div><a href="images/image.jpg"><img src="images/image.jpg" alt=""></a></div>
',
		$texy->process('[* image.jpg *]::'),
	);
});
