<?php declare(strict_types=1);

/**
 * Test: parse - Lists.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('unordered list', function () {
	$texy = new Texy\Texy;

	Assert::match(
		<<<'HTML'
			<ul>
				<li>Item 1</li>

				<li>Item 2</li>

				<li>Item 3</li>
			</ul>
			HTML,
		$texy->process(<<<'XX'
			- Item 1
			- Item 2
			- Item 3
			XX),
	);
});


test('ordered list', function () {
	$texy = new Texy\Texy;

	Assert::match(
		<<<'HTML'
			<ol>
				<li>First</li>

				<li>Second</li>

				<li>Third</li>
			</ol>
			HTML,
		$texy->process(<<<'XX'
			1. First
			2. Second
			3. Third
			XX),
	);
});


test('ordered list with letter bullets', function () {
	$texy = new Texy\Texy;

	Assert::match(
		<<<'HTML'
			<ol style="list-style-type:lower-alpha">
				<li>First</li>

				<li>Second</li>
			</ol>
			HTML,
		$texy->process(<<<'XX'
			a) First
			b) Second
			XX),
	);
});


test('a lone image is not wrapped in a figure inside an item', function () {
	// the item is a block container itself, so the wrapper would add nothing
	$texy = new Texy\Texy;

	Assert::match(
		<<<'HTML'
			<ul>
				<li class="img"><img src="images/logo.webp" alt=""></li>

				<li>text</li>
			</ul>
			HTML,
		$texy->process(<<<'XX'
			- [* logo.webp *] .[img]
			- text
			XX),
	);
});


test('an image with a caption keeps its figure inside an item', function () {
	$texy = new Texy\Texy;

	Assert::match(
		<<<'HTML'
			<ul>
				<li>
					<div class="figure"><img src="images/logo.webp" alt="">
						<p>Caption</p>
					</div>
				</li>
			</ul>
			HTML,
		$texy->process('- [* logo.webp *] *** Caption'),
	);
});
