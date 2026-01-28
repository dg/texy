<?php

/**
 * Test: Formatter
 */

declare(strict_types=1);

use Tester\Assert;
use Texy\Output\Html\Formatter;

require __DIR__ . '/../bootstrap.php';


test('basic indentation of block elements', function () {
	$formatter = new Formatter;
	$formatter->lineWrap = 0;

	Assert::same(
		"<div>\n\t<p>text</p>\n</div>",
		trim($formatter->format('<div><p>text</p></div>')),
	);
});


test('preserves inline elements', function () {
	$formatter = new Formatter;
	$formatter->lineWrap = 0;

	Assert::same(
		'<p><strong>bold</strong> and <em>italic</em></p>',
		trim($formatter->format('<p><strong>bold</strong> and <em>italic</em></p>')),
	);
});


test('br element handling', function () {
	$formatter = new Formatter;
	$formatter->lineWrap = 0;

	Assert::same(
		"<p>line1<br>\nline2</p>",
		trim($formatter->format('<p>line1<br>line2</p>')),
	);
});


test('line wrapping', function () {
	$formatter = new Formatter;
	$formatter->lineWrap = 40;
	$formatter->indent = false;

	Assert::same(
		"<p>Lorem ipsum dolor sit amet,\nconsectetuer adipiscing elit.</p>",
		trim($formatter->format('<p>Lorem ipsum dolor sit amet, consectetuer adipiscing elit.</p>')),
	);
});


test('preserves whitespace in pre', function () {
	$formatter = new Formatter;
	$formatter->lineWrap = 0;

	$input = "<pre>  code with   spaces\n  and newlines</pre>";
	Assert::same(
		"<pre>  code with   spaces\n  and newlines</pre>",
		normalizeNewlines(trim($formatter->format($input))),
	);
});


test('disabled indentation', function () {
	$formatter = new Formatter;
	$formatter->indent = false;
	$formatter->lineWrap = 0;

	Assert::same(
		'<div><p>text</p></div>',
		trim($formatter->format('<div><p>text</p></div>')),
	);
});


test('integration with Texy processing', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutputModule->lineWrap = 0;

	// Single heading with DYNAMIC balancing becomes h1
	Assert::same(
		"<h1>Title</h1>\n\n<p>Paragraph text.</p>",
		trim($texy->process("Title\n=====\n\nParagraph text.")),
	);
});
