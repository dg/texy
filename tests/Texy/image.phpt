<?php declare(strict_types=1);

/**
 * Test: Image module features
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('the alternative text and the float are properties of the image, not of its modifier', function () {
	$texy = new Texy\Texy;
	$image = fn(string $s) => $texy->parse($s)->content->children[0]->content->children[1];

	$node = $image('x [* a.png .(Alt)[c] <] y');
	Assert::same(['Alt', 'left', null, null], [$node->alt, $node->float, $node->modifier->title, $node->modifier->hAlign]);
	Assert::same('Attr', $image('x [* a.png .(Alt){alt: Attr} >] y')->alt);

	$texy->config->addImageDefinition('logo', 'logo.png', alt: 'Logo');
	$doc = $texy->parse('x [* logo *] y');
	$texy->transform($doc);
	Assert::same('Logo', $doc->content->children[0]->content->children[1]->alt);
});


// =============================================================================
// Auto-detect dimensions
// =============================================================================

test('image auto-detect dimensions', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutput->imageBasePath = __DIR__ . '/fixtures/';
	$texy->htmlOutput->imageBaseUrl = '';
	Assert::match(
		'<figure><img src="logo.gif" alt="" width="176" height="104"></figure>
',
		$texy->toHtml('[* logo.gif *]'),
	);
});


test('image auto-detect dimensions with specified width', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutput->imageBasePath = __DIR__ . '/fixtures/';
	$texy->htmlOutput->imageBaseUrl = '';
	// Specify width only, height should be calculated proportionally
	// Original: 176x104, ratio = 104/176 = 0.59
	// With width=88: height = 88 * 0.59 = 52
	Assert::match(
		'<figure><img src="logo.gif" alt="" width="88" height="52"></figure>
',
		$texy->toHtml('[* logo.gif 88x? *]'),
	);
});


test('image auto-detect dimensions with specified height', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutput->imageBasePath = __DIR__ . '/fixtures/';
	$texy->htmlOutput->imageBaseUrl = '';
	// Specify height only, width should be calculated proportionally
	// Original: 176x104, ratio = 176/104 = 1.69
	// With height=52: width = 52 * 1.69 = 88
	Assert::match(
		'<figure><img src="logo.gif" alt="" width="88" height="52"></figure>
',
		$texy->toHtml('[* logo.gif ?x52 *]'),
	);
});


test('image dimensions not detected without fileRoot', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutput->imageBaseUrl = '';
	// No fileRoot set, dimensions should not be added
	Assert::match(
		'<figure><img src="logo.gif" alt=""></figure>
',
		$texy->toHtml('[* logo.gif *]'),
	);
});


// =============================================================================
// alignClasses
// =============================================================================

test('image with a float class', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutput->floatClasses['right'] = 'float-right';
	Assert::match(
		'<figure class="float-right"><img src="image.jpg" alt=""></figure>
',
		$texy->toHtml('[* image.jpg >]'),
	);
	Assert::match(
		'<p>a <img src="image.jpg" alt="" class="float-right"> b</p>
',
		$texy->toHtml('a [* image.jpg >] b'),
	);
});


test('image with float fallback', function () {
	$texy = new Texy\Texy;
	// no float class - the float is an inline style on the container
	Assert::match(
		'<figure style="float:left"><img src="image.jpg" alt=""></figure>
',
		$texy->toHtml('[* image.jpg <]'),
	);
});


// =============================================================================
// Link on image
// =============================================================================

test('image with direct link', function () {
	$texy = new Texy\Texy;
	Assert::match(
		'<figure><a href="https://example.com"><img src="image.jpg" alt=""></a></figure>
',
		$texy->toHtml('[* image.jpg *]:https://example.com'),
	);
});


test('image with double colon link (uses main URL)', function () {
	$texy = new Texy\Texy;
	Assert::match(
		'<figure><a href="image.jpg"><img src="image.jpg" alt=""></a></figure>
',
		$texy->toHtml('[* image.jpg *]::'),
	);
});
