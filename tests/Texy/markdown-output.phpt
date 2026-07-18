<?php declare(strict_types=1);

/**
 * Test: Markdown output generator.
 */

use Tester\Assert;
use Texy\Output\Markdown;

require __DIR__ . '/../bootstrap.php';


function toMarkdown(string $text): string
{
	$texy = new Texy\Texy;
	$ast = $texy->parse($text);
	$generator = new Markdown\Renderer;
	return $generator->render($ast);
}


// Paragraphs
test('paragraph', function () {
	Assert::same(
		"Hello world.\n",
		toMarkdown('Hello world.'),
	);
});


test('multiple paragraphs', function () {
	Assert::same(
		"First paragraph.\n\nSecond paragraph.\n",
		toMarkdown("First paragraph.\n\nSecond paragraph."),
	);
});


// Headings
test('heading ATX style', function () {
	// Texy uses dynamic balancing - first heading becomes level 1
	Assert::same(
		"# Heading 1\n",
		toMarkdown("Heading 1\n*********"),
	);
});


test('heading setext style', function () {
	$texy = new Texy\Texy;
	$texy->headingModule->top = 1;
	$texy->headingModule->levels = ['#' => 0, '*' => 0];  // * = level 1
	$ast = $texy->parse("Heading 1\n*********");
	$generator = new Markdown\Renderer;
	$generator->headingStyle = 'setext';
	Assert::same(
		"Heading 1\n=========\n",
		$generator->render($ast),
	);
});


// Phrases
test('strong', function () {
	Assert::same(
		"**bold**\n",
		toMarkdown('**bold**'),
	);
});


test('emphasis', function () {
	Assert::same(
		"*italic*\n",
		toMarkdown('//italic//'),
	);
});


test('code', function () {
	Assert::same(
		"`code`\n",
		toMarkdown('`code`'),
	);
});


test('strikethrough (GFM)', function () {
	$texy = new Texy\Texy;
	$texy->allowed[Texy\Syntax::Deleted] = true;
	$ast = $texy->parse('--deleted--');
	$generator = new Markdown\Renderer;
	Assert::same(
		"~~deleted~~\n",
		$generator->render($ast),
	);
});


// Links
test('inline link', function () {
	Assert::same(
		"[link](https://example.com)\n",
		toMarkdown('"link":https://example.com'),
	);
});


test('reference link', function () {
	$texy = new Texy\Texy;
	$ast = $texy->parse('"link":https://example.com');
	$generator = new Markdown\Renderer;
	$generator->linkStyle = 'reference';
	Assert::same(
		"[link][1]\n\n\n[1]: https://example.com\n",
		$generator->render($ast),
	);
});


// Images
test('image', function () {
	Assert::same(
		"![](image.png)\n",
		toMarkdown('[* image.png *]'),
	);
});


test('image with alt', function () {
	Assert::same(
		"![Alt text](image.png)\n",
		toMarkdown('[* image.png .(Alt text) *]'),
	);
});


// Lists
test('unordered list', function () {
	Assert::same(
		"- First\n- Second\n- Third\n",
		toMarkdown("- First\n- Second\n- Third"),
	);
});


test('ordered list', function () {
	Assert::same(
		"1. First\n2. Second\n3. Third\n",
		toMarkdown("1) First\n2) Second\n3) Third"),
	);
});


// Code blocks
test('fenced code block', function () {
	$input = "/--code php\n\$x = 1;\n\\--";
	Assert::same(
		"```php\n\$x = 1;\n```\n",
		toMarkdown($input),
	);
});


test('indented code block', function () {
	$texy = new Texy\Texy;
	$ast = $texy->parse("/--code\n\$x = 1;\n\\--");
	$generator = new Markdown\Renderer;
	$generator->codeBlockStyle = 'indented';
	Assert::same(
		"    \$x = 1;\n",
		$generator->render($ast),
	);
});


// Blockquote
test('blockquote', function () {
	Assert::same(
		"> Quoted text.\n",
		toMarkdown('> Quoted text.'),
	);
});


// Tables (GFM)
test('table', function () {
	$input = "| A | B |\n| 1 | 2 |\n| 3 | 4 |";
	$expected = "| A | B |\n| --- | --- |\n| 1 | 2 |\n| 3 | 4 |\n";
	Assert::same($expected, toMarkdown($input));
});


test('table with colspan falls back to HTML', function () {
	$input = "| Name || Age\n| Jesus | Christ | 33";
	$expected = "<table>\n"
		. "<tr><td colspan=\"2\">Name</td><td>Age</td></tr>\n"
		. "<tr><td>Jesus</td><td>Christ</td><td>33</td></tr>\n"
		. "</table>\n";
	Assert::same($expected, toMarkdown($input));
});


test('table with rowspan falls back to HTML', function () {
	$input = "| Bill | 50\n|^ | 52";
	$expected = "<table>\n"
		. "<tr><td rowspan=\"2\">Bill</td><td>50</td></tr>\n"
		. "<tr><td>52</td></tr>\n"
		. "</table>\n";
	Assert::same($expected, toMarkdown($input));
});


// Horizontal rule
test('horizontal rule', function () {
	Assert::same(
		"---\n",
		toMarkdown('----'),
	);
});


// Line break (Texy syntax: space at the beginning of next line)
test('line break', function () {
	Assert::same(
		"First line  \nsecond line.\n",
		toMarkdown("First line\n second line."),
	);
});


// Autolinks
test('URL autolink', function () {
	Assert::same(
		"<https://example.com>\n",
		toMarkdown('https://example.com'),
	);
});


test('email autolink', function () {
	Assert::same(
		"<test@example.com>\n",
		toMarkdown('test@example.com'),
	);
});


// Special characters escaping
test('escape special characters', function () {
	Assert::same(
		"\\*not bold\\*\n",
		toMarkdown("''*not bold*''"),
	);
});


// HTML passthrough
test('HTML passthrough', function () {
	Assert::same(
		"<span>text</span>\n",
		toMarkdown('<span>text</span>'),
	);
});


// Emoticons
test('emoticons', function () {
	$texy = new Texy\Texy;
	$texy->allowed[Texy\Syntax::Emoticon] = true;
	$ast = $texy->parse(':-)');
	$generator = new Markdown\Renderer;
	Assert::same(
		"🙂\n",
		$generator->render($ast),
	);
});


// URL scheme filtering (security)
test('UrlPolicy filters dangerous schemes', function () {
	$texy = new Texy\Texy;
	Texy\Configurator::safeMode($texy);
	$ast = $texy->parse('"click":javascript:evil and "ok":https://example.com');
	$generator = new Markdown\Renderer($texy->urlPolicy);
	Assert::same(
		"click and [ok](https://example.com)\n",
		$generator->render($ast),
	);
});


test('without UrlPolicy no scheme filtering happens', function () {
	$texy = new Texy\Texy;
	$ast = $texy->parse('"click":javascript:evil');
	$generator = new Markdown\Renderer;
	Assert::same(
		"[click](javascript:evil)\n",
		$generator->render($ast),
	);
});
