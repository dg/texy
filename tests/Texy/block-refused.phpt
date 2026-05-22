<?php declare(strict_types=1);

/**
 * Test: a block syntax refusing its match leaves the text to the paragraph around it.
 */

use Tester\Assert;
use Texy\{Nodes, Range, Syntax, Texy};

require __DIR__ . '/../bootstrap.php';


function render(string $source, string ...$disabled): string
{
	$texy = new Texy;
	$texy->config->typography = false;
	foreach ($disabled as $name) {
		$texy->allowedSyntax[$name] = false;
	}

	return trim($texy->toHtml($source));
}


test('a list of a single item is a line of the paragraph', function () {
	Assert::same('<p>text 1. item more</p>', render("text\n1. item\nmore"));
});


test('a marker of a switched-off numbering is a line of the paragraph', function () {
	Assert::same('<p>see point 1) first and more</p>', render("see point\n1) first\nand more", Syntax::ListDecimal));
	Assert::same('<p>1) a 2) b</p>', render("1) a\n2) b", Syntax::ListDecimal));
});


test('two refused matches in one paragraph', function () {
	Assert::same('<p>text 1. one middle 2. two end</p>', render("text\n1. one\nmiddle\n2. two\nend"));
});


test('the paragraph ends where an accepted block starts', function () {
	Assert::same(
		"<p>text 1. item</p>\n\n<ul>\n\t<li>a</li>\n\n\t<li>b</li>\n</ul>",
		render("text\n1. item\n- a\n- b"),
	);
});


test('a refused match on the last line', function () {
	Assert::same('<p>text 1. item</p>', render("text\n1. item"));
});


test('the paragraph and its text cover the refused line', function () {
	$src = "text\n1. item\nmore";
	$paragraphs = (new Texy)->parse($src)->find(Nodes\ParagraphNode::class);

	Assert::count(1, $paragraphs);
	Assert::equal(new Range(0, strlen($src)), $paragraphs[0]->range);
	Assert::equal(new Range(0, strlen($src)), $paragraphs[0]->content->children[0]->range);
});
