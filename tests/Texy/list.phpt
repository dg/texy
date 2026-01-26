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
		$texy->toHtml(<<<'XX'
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
		$texy->toHtml(<<<'XX'
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
		$texy->toHtml(<<<'XX'
			a) First
			b) Second
			XX),
	);
});


test('a bullet opening an item is text, a nested list starts on the next line', function () {
	$texy = new Texy\Texy;
	Assert::same("<ul>\n\t<li>* enzym</li>\n\n\t<li>1) text</li>\n</ul>", trim($texy->toHtml("- * enzym\n- 1) text")));
	Assert::same("<ol>\n\t<li>– 3.\u{A0}září</li>\n\n\t<li>text</li>\n</ol>", trim($texy->toHtml("1. – 3. září\n2. text")));
	Assert::same("<ul>\n\t<li>a\n\t\t<ul>\n\t\t\t<li>b</li>\n\t\t</ul>\n\t</li>\n</ul>", trim($texy->toHtml("- a\n  * b")));
});


test('a description has no <p> around its opening paragraph, like an item', function () {
	$texy = new Texy\Texy;
	Assert::same(
		"<dl>\n\t<dt>Term</dt>\n\n\t<dd>a\n\t\t<p>b</p>\n\t</dd>\n</dl>",
		trim($texy->toHtml("Term:\n  - a\n\n    b")),
	);
});


test('a lone image is not wrapped in a figure inside an item', function () {
	// the item is a block container itself, so the wrapper would add nothing
	$texy = new Texy\Texy;

	Assert::match(
		<<<'HTML'
			<ul>
				<li class="img"><img src="logo.webp" alt=""></li>

				<li>text</li>
			</ul>
			HTML,
		$texy->toHtml(<<<'XX'
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
					<figure><img src="logo.webp" alt="">
						<figcaption>Caption</figcaption>
					</figure>
				</li>
			</ul>
			HTML,
		$texy->toHtml('- [* logo.webp *] *** Caption'),
	);
});
