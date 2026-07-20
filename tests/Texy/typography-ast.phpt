<?php declare(strict_types=1);

/**
 * Test: typography and hyphenation as an AST transform.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


function convert(string $text): string
{
	$texy = new Texy\Texy;
	$texy->htmlOutput->lineWrap = 0;
	return $texy->process($text);
}


test('basic typography', function () {
	Assert::same("<p>Rekl „ahoj“ a sel…</p>\n", convert('Rekl "ahoj" a sel...'));
	Assert::same("<p>Vazi 5\u{A0}kg a stoji 10\u{A0}EUR</p>\n", convert('Vazi 5 kg a stoji 10 EUR'));
	Assert::same("<p>datum 23.\u{A0}1.\u{A0}1978 je datum</p>\n", convert('datum 23. 1. 1978 je datum'));
	Assert::same("<p>rozmer je 10\u{A0}\u{D7}\u{A0}20 cm</p>\n", convert('rozmer je 10 x 20 cm'));
	Assert::same("<p>text \u{A9} a \u{2122} a \u{B1}</p>\n", convert('text (c) a (TM) a +-'));
});


test('quotes pair across phrase boundaries', function () {
	Assert::same(
		"<p>řekl „ahoj <strong>světe</strong>!“ a šel</p>\n",
		convert('řekl "ahoj **světe**!" a šel'),
	);
});


test('code phrase content is protected from typography', function () {
	Assert::same(
		"<p>napiš <code>\"quotes\" -- ...</code> takto</p>\n",
		convert('napiš `"quotes" -- ...` takto'),
	);
});


test('long words get soft hyphens', function () {
	Assert::contains("\u{AD}", convert('Dlouheslovohodnedlouhatezkorozdelitelne slovo'));
});


test('typography can be disabled', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutput->lineWrap = 0;
	$texy->allowed[Texy\Syntax::Typography] = false;
	Assert::same("<p>Rekl \"ahoj\" a sel...</p>\n", $texy->process('Rekl "ahoj" a sel...'));
});


test('hyphenation can be disabled', function () {
	$texy = new Texy\Texy;
	$texy->allowed[Texy\Syntax::Hyphenation] = false;
	Assert::notContains("\u{AD}", $texy->process('Dlouheslovohodnedlouhatezkorozdelitelne slovo'));
});


test('modifier title gets typography exactly once', function () {
	$texy = new Texy\Texy;
	Assert::same(
		"<p title=\"říká „ahoj“\">text</p>\n",
		$texy->process('text .(říká "ahoj")'),
	);
});


test('disabled typography leaves titles and alt alone', function () {
	$texy = new Texy\Texy;
	$texy->allowed['typography'] = false;
	Assert::same(
		"<p title=\"s &quot;uvozovkami&quot;\">text -- ok</p>\n",
		$texy->process('text -- ok .(s "uvozovkami")'),
	);

	$texy = new Texy\Texy;
	$texy->allowed['typography'] = false;
	$html = $texy->process('[* obr.png .(popisek "v uvozovkach") *]');
	Assert::contains('alt="popisek &quot;v uvozovkach&quot;"', $html);
});


test('image alt gets typography', function () {
	$texy = new Texy\Texy;
	$html = $texy->process('[* obr.png .(popisek "v uvozovkach") *]');
	Assert::contains("alt=\"popisek „v\u{A0}uvozovkach“\"", $html);
});


test('render is repeatable on typographed AST (purity)', function () {
	$texy = new Texy\Texy;
	$doc = $texy->parse('Rekl "ahoj **svete**" a vazi 5 kg');
	Assert::same(
		(new \Texy\Output\Html\Renderer($texy->htmlOutput, $texy))->render($doc),
		(new \Texy\Output\Html\Renderer($texy->htmlOutput, $texy))->render($doc),
	);
});


test('Markdown output inherits typography from AST', function () {
	$texy = new Texy\Texy;
	$doc = $texy->parse('Rekl "ahoj" a sel...');
	$md = new Texy\Output\Markdown\Renderer;
	$md->escapeSpecialChars = false;
	Assert::same("Rekl „ahoj“ a sel…\n", $md->render($doc));
});


test('entities are decoded before typography', function () {
	// &#160; must act as nbsp, not as a 6-character word for hyphenation
	Assert::notContains(
		"&amp;\u{AD}#160;",
		convert('slovo&#160;dlouheslovokterehyphenacevidi neco'),
	);
});


test('block-level passthrough tag splits the typographic segment', function () {
	// quotes must not pair across a block tag
	$html = convert('text "prvni <div>blok</div> druha" konec');
	Assert::notContains('„', $html);
});


test('notexy raw text gets typography', function () {
	Assert::contains('„uvozovkami“', convert("''raw s \"uvozovkami\" a...''"));
});


test('phrase modifier title is typographed once', function () {
	$texy = new Texy\Texy;
	Assert::same(
		"<p><strong title=\"s „uvozovkami“\">tucne</strong></p>\n",
		$texy->process('**tucne .(s "uvozovkami")**'),
	);
});
