<?php declare(strict_types=1);

/**
 * Test: Image reference resolution
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


// =============================================================================
// Basic image reference
// =============================================================================

test('image reference is resolved', function () {
	$texy = new Texy\Texy;
	Assert::match(
		'<div class="figure"><img src="images/image.jpg" alt=""></div>
',
		$texy->process("[*logo*]\n\n[*logo*]: image.jpg"),
	);
});


test('forward image reference is resolved', function () {
	$texy = new Texy\Texy;
	Assert::match(
		'<div class="figure"><img src="images/image.jpg" alt=""></div>
',
		$texy->process("[*logo*]: image.jpg\n\n[* logo *]"),
	);
});


test('image definition is removed from output', function () {
	$texy = new Texy\Texy;
	Assert::match(
		'<p>text</p>
',
		$texy->process("[*logo*]: image.jpg\n\ntext"),
	);
});


test('undefined image reference shows as direct URL', function () {
	$texy = new Texy\Texy;
	Assert::match(
		'<div class="figure"><img src="images/undefined" alt=""></div>
',
		$texy->process('[* undefined *]'),
	);
});


test('case insensitive image reference matching', function () {
	$texy = new Texy\Texy;
	Assert::match(
		'<div class="figure"><img src="images/image.jpg" alt=""></div>
',
		$texy->process("[* LOGO *]\n\n[*logo*]: image.jpg"),
	);
});


test('multiple references to same image definition', function () {
	$texy = new Texy\Texy;
	Assert::match(
		<<<'XX'
			<div class="figure"><img src="images/image.jpg" alt=""></div>

			<div class="figure"><img src="images/image.jpg" alt=""></div>

			XX,
		$texy->process("[* logo *]\n\n[* logo *]\n\n[*logo*]: image.jpg"),
	);
});


// =============================================================================
// Image reference with dimensions
// =============================================================================

test('image reference inherits dimensions from definition', function () {
	$texy = new Texy\Texy;
	Assert::match(
		'<div class="figure"><img src="images/image.jpg" alt="" width="200"
height="100"></div>
',
		$texy->process("[* logo *]\n\n[*logo*]: image.jpg 200x100"),
	);
});


// Note: Dimensions in usage don't override reference - "logo 50x25" is treated as
// a different reference name than "logo", so it falls back to direct URL behavior
test('image reference with dimensions in usage falls back to direct URL', function () {
	$texy = new Texy\Texy;
	Assert::match(
		'<div class="figure"><img src="images/logo" alt="" width="50" height="25"></div>
',
		$texy->process("[* logo 50x25 *]\n\n[*logo*]: image.jpg 200x100"),
	);
});


// =============================================================================
// Image reference with modifiers
// =============================================================================

test('image reference inherits alt text from definition', function () {
	$texy = new Texy\Texy;
	Assert::match(
		'<div class="figure"><img src="images/image.jpg" alt="Default alt"></div>
',
		$texy->process("[* logo *]\n\n[*logo*]: image.jpg .(Default alt)"),
	);
});


test('image reference can override alt text', function () {
	$texy = new Texy\Texy;
	Assert::match(
		'<div class="figure"><img src="images/image.jpg" alt="Local alt"></div>
',
		$texy->process("[* logo .(Local alt) *]\n\n[*logo*]: image.jpg .(Default alt)"),
	);
});


// =============================================================================
// Image reference with alignment
// =============================================================================

test('image reference with alignment override', function () {
	$texy = new Texy\Texy;
	Assert::match(
		'<div style="float:right" class="figure"><img src="images/image.jpg"
alt=""></div>
',
		$texy->process("[* logo >]\n\n[*logo*]: image.jpg"),
	);
});


test('image reference with left alignment', function () {
	$texy = new Texy\Texy;
	Assert::match(
		'<div style="float:left" class="figure"><img src="images/image.jpg" alt=""></div>
',
		$texy->process("[* logo <]\n\n[*logo*]: image.jpg"),
	);
});


// =============================================================================
// Figure with image reference
// =============================================================================

test('figure with image reference', function () {
	$texy = new Texy\Texy;
	Assert::match(
		<<<'XX'
			<div class="figure"><img src="images/image.jpg" alt="">
				<p>Caption</p>
			</div>

			XX,
		$texy->process("[* logo *] *** Caption\n\n[*logo*]: image.jpg"),
	);
});


test('figure with forward image reference', function () {
	$texy = new Texy\Texy;
	Assert::match(
		<<<'XX'
			<div class="figure"><img src="images/image.jpg" alt="">
				<p>Caption</p>
			</div>

			XX,
		$texy->process("[*logo*]: image.jpg\n\n[* logo *] *** Caption"),
	);
});


test('figure with image reference and explicit link', function () {
	$texy = new Texy\Texy;
	Assert::match(
		<<<'XX'
			<div class="figure"><a href="https://example.com"><img src="images/image.jpg"
			alt=""></a>
				<p>Caption</p>
			</div>

			XX,
		$texy->process("[* logo *]:https://example.com *** Caption\n\n[*logo*]: image.jpg"),
	);
});


test('figure with image reference and link reference', function () {
	$texy = new Texy\Texy;
	Assert::match(
		<<<'XX'
			<div class="figure"><a href="https://example.com"><img src="images/image.jpg"
			alt=""></a>
				<p>Caption</p>
			</div>

			XX,
		$texy->process("[* logo *]:[link] *** Caption\n\n[*logo*]: image.jpg\n\n[link]: https://example.com"),
	);
});


test('figure with image reference and image link reference', function () {
	$texy = new Texy\Texy;
	Assert::match(
		<<<'XX'
			<div class="figure"><a href="images/big.jpg"><img src="images/thumb.jpg"
			alt=""></a>
				<p>Caption</p>
			</div>

			XX,
		$texy->process("[* thumb *]:[*big*] *** Caption\n\n[*thumb*]: thumb.jpg\n\n[*big*]: big.jpg"),
	);
});


// =============================================================================
// Figure with direct image and link references
// =============================================================================

test('figure with direct image and link reference', function () {
	$texy = new Texy\Texy;
	Assert::match(
		<<<'XX'
			<div class="figure"><a href="https://example.com"><img src="images/image.jpg"
			alt=""></a>
				<p>Caption</p>
			</div>

			XX,
		$texy->process("[* image.jpg *]:[link] *** Caption\n\n[link]: https://example.com"),
	);
});


test('figure with direct image and image link reference', function () {
	$texy = new Texy\Texy;
	Assert::match(
		<<<'XX'
			<div class="figure"><a href="images/big.jpg"><img src="images/thumb.jpg"
			alt=""></a>
				<p>Caption</p>
			</div>

			XX,
		$texy->process("[* thumb.jpg *]:[*big*] *** Caption\n\n[*big*]: big.jpg"),
	);
});


// =============================================================================
// Direct images (non-reference) still work
// =============================================================================

test('direct image works', function () {
	$texy = new Texy\Texy;
	Assert::match(
		'<div class="figure"><img src="images/image.jpg" alt=""></div>
',
		$texy->process('[* image.jpg *]'),
	);
});


test('direct image with dimensions works', function () {
	$texy = new Texy\Texy;
	Assert::match(
		'<div class="figure"><img src="images/image.jpg" alt="" width="100"
height="50"></div>
',
		$texy->process('[* image.jpg 100x50 *]'),
	);
});


test('direct image with alt text works', function () {
	$texy = new Texy\Texy;
	Assert::match(
		'<div class="figure"><img src="images/image.jpg" alt="Alt text"></div>
',
		$texy->process('[* image.jpg .(Alt text) *]'),
	);
});


test('direct figure works', function () {
	$texy = new Texy\Texy;
	Assert::match(
		<<<'XX'
			<div class="figure"><img src="images/image.jpg" alt="">
				<p>Caption text</p>
			</div>

			XX,
		$texy->process('[* image.jpg *] *** Caption text'),
	);
});


// =============================================================================
// User-defined definitions via addDefinition()
// =============================================================================

test('user-defined image definition works', function () {
	$texy = new Texy\Texy;
	$texy->imageModule->addDefinition('logo', 'logo.png', 100, 50, 'Company Logo');
	// alt parameter is used as alt text for images
	Assert::match(
		'<div class="figure"><img src="images/logo.png" alt="Company Logo" width="100"
height="50"></div>
',
		$texy->process('[* logo *]'),
	);
});


test('user-defined image definition persists across process() calls', function () {
	$texy = new Texy\Texy;
	$texy->imageModule->addDefinition('logo', 'logo.png');

	// First process()
	Assert::match(
		'<div class="figure"><img src="images/logo.png" alt=""></div>
',
		$texy->process('[* logo *]'),
	);

	// Second process() - definition should still work
	Assert::match(
		'<div class="figure"><img src="images/logo.png" alt=""></div>
',
		$texy->process('[* logo *]'),
	);
});


test('document-defined image reference leaks to next process() [BUG]', function () {
	$texy = new Texy\Texy;

	// First process() defines a reference
	$texy->process("[*logo*]: image.jpg\n\n[* logo *]");

	// Second process() - reference should NOT be available, but it is (BUG)
	// This documents the current buggy behavior
	Assert::match(
		'<div class="figure"><img src="images/image.jpg" alt=""></div>
',
		$texy->process('[* logo *]'),
	);
});


test('user-defined image definition is overwritten by document definition', function () {
	$texy = new Texy\Texy;
	$texy->imageModule->addDefinition('logo', 'user-logo.png', 100);

	// Document defines same reference - it overwrites user-defined one
	Assert::match(
		'<div class="figure"><img src="images/document-logo.jpg" alt="" width="200"
height="100"></div>
',
		$texy->process("[* logo *]\n\n[*logo*]: document-logo.jpg 200x100"),
	);
});
