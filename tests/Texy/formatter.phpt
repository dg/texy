<?php declare(strict_types=1);

/**
 * Test: HTML output formatting (WellFormer)
 */

use Tester\Assert;
use Texy\Output\Html\Config;
use Texy\Output\Html\WellFormer;
use Texy\Texy;

require __DIR__ . '/../bootstrap.php';


function wellform(Config $config, string $s): string
{
	$wellFormer = new WellFormer($config);
	$wellFormer->raw($s);
	return $wellFormer->finish();
}


test('basic indentation of block elements', function () {
	$config = (new Texy)->htmlOutput;
	$config->lineWrap = 0;

	Assert::same(
		"<div>\n\t<p>text</p>\n</div>",
		trim(wellform($config, '<div><p>text</p></div>')),
	);
});


test('preserves inline elements', function () {
	$config = (new Texy)->htmlOutput;
	$config->lineWrap = 0;

	Assert::same(
		'<p><strong>bold</strong> and <em>italic</em></p>',
		trim(wellform($config, '<p><strong>bold</strong> and <em>italic</em></p>')),
	);
});


test('br element handling', function () {
	$config = (new Texy)->htmlOutput;
	$config->lineWrap = 0;

	Assert::same(
		"<p>line1<br>\nline2</p>",
		trim(wellform($config, '<p>line1<br>line2</p>')),
	);
});


test('line wrapping', function () {
	$config = (new Texy)->htmlOutput;
	$config->lineWrap = 40;
	$config->indent = false;

	Assert::same(
		"<p>Lorem ipsum dolor sit amet,\nconsectetuer adipiscing elit.</p>",
		trim(wellform($config, '<p>Lorem ipsum dolor sit amet, consectetuer adipiscing elit.</p>')),
	);
});


test('preserves whitespace in pre', function () {
	$config = (new Texy)->htmlOutput;
	$config->lineWrap = 0;

	$input = "<pre>  code with   spaces\n  and newlines</pre>";
	Assert::same(
		"<pre>  code with   spaces\n  and newlines</pre>",
		normalizeNewlines(trim(wellform($config, $input))),
	);
});


test('disabled indentation', function () {
	$config = (new Texy)->htmlOutput;
	$config->indent = false;
	$config->lineWrap = 0;

	Assert::same(
		'<div><p>text</p></div>',
		trim(wellform($config, '<div><p>text</p></div>')),
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


test('stray < stays in place', function () {
	$config = (new Texy)->htmlOutput;
	$config->lineWrap = 0;
	Assert::same(
		'a < b <span>x</span> c',
		trim(wellform($config, 'a < b <span>x</span> c')),
	);
});
