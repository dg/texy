<?php declare(strict_types=1);

/**
 * Test: HTML output formatting (Generator::format)
 */

use Tester\Assert;
use Texy\Output\Html\WellFormer;
use Texy\Texy;

require __DIR__ . '/../bootstrap.php';


test('basic indentation of block elements', function () {
	$formatter = new WellFormer($config = (new Texy)->htmlOutput);
	$config->lineWrap = 0;

	Assert::same(
		"<div>\n\t<p>text</p>\n</div>",
		trim($formatter->format('<div><p>text</p></div>')),
	);
});


test('preserves inline elements', function () {
	$formatter = new WellFormer($config = (new Texy)->htmlOutput);
	$config->lineWrap = 0;

	Assert::same(
		'<p><strong>bold</strong> and <em>italic</em></p>',
		trim($formatter->format('<p><strong>bold</strong> and <em>italic</em></p>')),
	);
});


test('br element handling', function () {
	$formatter = new WellFormer($config = (new Texy)->htmlOutput);
	$config->lineWrap = 0;

	Assert::same(
		"<p>line1<br>\nline2</p>",
		trim($formatter->format('<p>line1<br>line2</p>')),
	);
});


test('line wrapping', function () {
	$formatter = new WellFormer($config = (new Texy)->htmlOutput);
	$config->lineWrap = 40;
	$config->indent = false;

	Assert::same(
		"<p>Lorem ipsum dolor sit amet,\nconsectetuer adipiscing elit.</p>",
		trim($formatter->format('<p>Lorem ipsum dolor sit amet, consectetuer adipiscing elit.</p>')),
	);
});


test('preserves whitespace in pre', function () {
	$formatter = new WellFormer($config = (new Texy)->htmlOutput);
	$config->lineWrap = 0;

	$input = "<pre>  code with   spaces\n  and newlines</pre>";
	Assert::same(
		"<pre>  code with   spaces\n  and newlines</pre>",
		normalizeNewlines(trim($formatter->format($input))),
	);
});


test('disabled indentation', function () {
	$formatter = new WellFormer($config = (new Texy)->htmlOutput);
	$config->indent = false;
	$config->lineWrap = 0;

	Assert::same(
		'<div><p>text</p></div>',
		trim($formatter->format('<div><p>text</p></div>')),
	);
});


test('integration with Texy processing', function () {
	$texy = new Texy;
	$texy->htmlOutput->lineWrap = 0;

	// Single heading with DYNAMIC balancing becomes h1
	Assert::same(
		"<h1>Title</h1>\n\n<p>Paragraph text.</p>",
		trim($texy->process("Title\n=====\n\nParagraph text.")),
	);
});
