<?php declare(strict_types=1);

/**
 * Test: the kinds of numbering of a list are switched by their syntax names.
 */

use Tester\Assert;
use Texy\{Syntax, Texy};

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


test('decimal numbering switched off', function () {
	Assert::same('<p>1. a 2. b</p>', render("1. a\n2. b", Syntax::ListDecimal));
	Assert::same('<p>1) a</p>', render('1) a', Syntax::ListDecimal));
	Assert::same("<ol style=\"list-style-type:lower-alpha\">\n\t<li>a</li>\n</ol>", render('a) a', Syntax::ListDecimal));
	Assert::same("<ul>\n\t<li>a</li>\n</ul>", render('- a', Syntax::ListDecimal));
});


test('roman numbering switched off', function () {
	Assert::same('<p>I. a II. b</p>', render("I. a\nII. b", Syntax::ListRoman));
	Assert::same('<p>II) a</p>', render('II) a', Syntax::ListRoman));
	Assert::same("<ol>\n\t<li>a</li>\n\n\t<li>b</li>\n</ol>", render("1. a\n2. b", Syntax::ListRoman));
});


test('I) with roman numbering switched off is a letter', function () {
	Assert::same(
		"<ol start=\"9\" style=\"list-style-type:upper-alpha\">\n\t<li>first</li>\n\n\t<li>second</li>\n</ol>",
		render("I) first\nJ) second", Syntax::ListRoman),
	);
	Assert::same(
		"<ol start=\"22\" style=\"list-style-type:upper-alpha\">\n\t<li>a</li>\n\n\t<li>b</li>\n</ol>",
		render("V) a\nW) b", Syntax::ListRoman),
	);
	Assert::same(
		"<ol start=\"24\" style=\"list-style-type:upper-alpha\">\n\t<li>a</li>\n</ol>",
		render('X) a', Syntax::ListRoman),
	);
});


test('alphabetical numbering switched off', function () {
	Assert::same('<p>a) a</p>', render('a) a', Syntax::ListAlpha));
	Assert::same('<p>B) a</p>', render('B) a', Syntax::ListAlpha));
	Assert::same(
		"<ol style=\"list-style-type:upper-roman\">\n\t<li>a</li>\n</ol>",
		render('I) a', Syntax::ListAlpha),
	);
});


test('a nested list keeps working with a kind switched off', function () {
	Assert::same(
		"<ul>\n\t<li>a\n\t\t<ol>\n\t\t\t<li>b</li>\n\n\t\t\t<li>c</li>\n\t\t</ol>\n\t</li>\n</ul>",
		render("- a\n  1) b\n  2) c", Syntax::ListRoman),
	);
	Assert::same(
		"<ul>\n\t<li>a\n\t\t<ol style=\"list-style-type:lower-alpha\">\n\t\t\t<li>b</li>\n\n\t\t\t<li>c</li>\n\t\t</ol>\n\t</li>\n</ul>",
		render("- a\n  a) b\n  b) c", Syntax::ListDecimal),
	);
});


test('a definition list keeps working with the kinds switched off', function () {
	Assert::same(
		"<dl>\n\t<dt>Term</dt>\n\n\t<dd>a</dd>\n\n\t<dd>b</dd>\n</dl>",
		render("Term:\n  - a\n  - b", Syntax::ListDecimal, Syntax::ListRoman, Syntax::ListAlpha),
	);
});
