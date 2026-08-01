<?php declare(strict_types=1);

/**
 * Test: Tabs are preserved and act as plain whitespace.
 */

use Tester\Assert;
use Texy\Helpers;

require __DIR__ . '/../bootstrap.php';


test('tabs in code blocks survive verbatim, shared indentation is stripped', function () {
	$texy = new Texy\Texy;
	Assert::match(
		"<pre class=\"php\"><code>if (\$a) {\n\treturn;\n}</code></pre>",
		$texy->process("/--code php\n\tif (\$a) {\n\t\treturn;\n\t}\n\\--"),
	);
});


test('tabs in fenced code blocks survive verbatim', function () {
	$texy = new Texy\Texy;
	Assert::match(
		"<pre><code>\tx = 1</code></pre>",
		$texy->process("```\n\tx = 1\n```"),
	);
});


test('list item continuation indented with a tab', function () {
	$texy = new Texy\Texy;
	Assert::match(
		"<ul>\n\t<li>first continued</li>\n\n\t<li>second</li>\n</ul>",
		$texy->process("- first\n\tcontinued\n- second"),
	);
});


test('definition list indented with tabs', function () {
	$texy = new Texy\Texy;
	Assert::match(
		"<dl>\n\t<dt>Term</dt>\n\n\t<dd>one</dd>\n\n\t<dd>two</dd>\n</dl>",
		$texy->process("Term:\n\t- one\n\t- two"),
	);
});


test('blockquote prefixed with a tab', function () {
	$texy = new Texy\Texy;
	Assert::match(
		"<blockquote>\n\t<p>quote line</p>\n</blockquote>",
		$texy->process(">\tquote\n>\tline"),
	);
});


test('modifier separated by a tab', function () {
	$texy = new Texy\Texy;
	Assert::match(
		'<h1 class="main">Heading</h1>',
		$texy->process("Heading\t.[main]\n======="),
	);
});


test('tab inside a paragraph becomes a space', function () {
	$texy = new Texy\Texy;
	Assert::match(
		'<p>word next</p>',
		$texy->process("word\tnext"),
	);
});


test('tab-indented continuation line makes a hard break', function () {
	$texy = new Texy\Texy;
	Assert::match(
		"<p>first<br>\nsecond</p>",
		$texy->process("first\n\tsecond"),
	);
});


test('div block indented with tabs', function () {
	$texy = new Texy\Texy;
	Assert::match(
		"<div>\n\t<p>Paragraph.</p>\n</div>",
		$texy->process("/--div\n\tParagraph.\n\\--"),
	);
});


test('outdent strips the shared whitespace prefix', function () {
	Assert::same("foo\n\tbar", Helpers::outdent("\tfoo\n\t\tbar"));
	Assert::same("foo\nbar", Helpers::outdent("    foo\n    bar"));
	Assert::same("  a\n\tb", Helpers::outdent("  a\n\tb")); // mixed indent has no shared prefix
	Assert::same("foo\nbar", Helpers::outdent("\t\tfoo\n\tbar", firstLine: true));
});
