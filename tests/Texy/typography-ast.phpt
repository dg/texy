<?php declare(strict_types=1);

/**
 * Test: AST typography pass (Texy::$astTypography) vs. string post-processing.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


function convert(string $text, bool $ast): string
{
	$texy = new Texy\Texy;
	$texy->astTypography = $ast;
	$texy->htmlOutput->lineWrap = 0;
	return $texy->process($text);
}


test('basic typography matches string pipeline', function () {
	foreach ([
		'Rekl "ahoj" a sel...',
		'Vazi 5 kg a stoji 10 EUR',
		'datum 23. 1. 1978 je datum',
		'rozmer je 10 x 20 cm',
		'text (c) a (TM) a +-',
	] as $input) {
		Assert::same(convert($input, ast: false), convert($input, ast: true));
	}
});


test('quotes pair across phrase boundaries', function () {
	Assert::same(
		"<p>řekl „ahoj <strong>světe</strong>!“ a šel</p>\n",
		convert('řekl "ahoj **světe**!" a šel', ast: true),
	);
});


test('code phrase content is protected from typography', function () {
	Assert::same(
		"<p>napiš <code>\"quotes\" -- ...</code> takto</p>\n",
		convert('napiš `"quotes" -- ...` takto', ast: true),
	);
});


test('long words get soft hyphens', function () {
	$out = convert('Dlouheslovohodnedlouhatezkorozdelitelne slovo', ast: true);
	Assert::contains("\u{AD}", $out);
	Assert::same(convert('Dlouheslovohodnedlouhatezkorozdelitelne slovo', ast: false), $out);
});


test('modifier title gets typography exactly once', function () {
	$texy = new Texy\Texy;
	$texy->astTypography = true;
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
	$texy->astTypography = true;
	$html = $texy->process('[* obr.png .(popisek "v uvozovkach") *]');
	Assert::contains("alt=\"popisek „v\u{A0}uvozovkach“\"", $html);
});


test('render is repeatable on typographed AST (purity)', function () {
	$texy = new Texy\Texy;
	$texy->astTypography = true;
	$doc = $texy->parse('Rekl "ahoj **svete**" a vazi 5 kg');
	Assert::same(
		(new \Texy\Output\Html\Renderer($texy->htmlOutput, $texy))->render($doc),
		(new \Texy\Output\Html\Renderer($texy->htmlOutput, $texy))->render($doc),
	);
});


test('Markdown output inherits typography from AST', function () {
	$texy = new Texy\Texy;
	$texy->astTypography = true;
	$doc = $texy->parse('Rekl "ahoj" a sel...');
	$md = new Texy\Output\Markdown\Renderer;
	$md->escapeSpecialChars = false;
	Assert::same("Rekl „ahoj“ a sel…\n", $md->render($doc));
});


test('block-level passthrough tag splits the typographic segment', function () {
	// quotes must not pair across a block tag; both pipelines must agree
	$input = 'text "prvni <div>blok</div> druha" konec';
	Assert::same(convert($input, ast: false), convert($input, ast: true));
});


test('notexy raw text gets typography like the string pipeline', function () {
	$input = "''raw s \"uvozovkami\" a...''";
	Assert::same(convert($input, ast: false), convert($input, ast: true));
	Assert::contains('„uvozovkami“', convert($input, ast: true));
});


test('phrase modifier title is typographed once', function () {
	$texy = new Texy\Texy;
	$texy->astTypography = true;
	Assert::same(
		"<p><strong title=\"s „uvozovkami“\">tucne</strong></p>\n",
		$texy->process('**tucne .(s "uvozovkami")**'),
	);
});


test('entities are decoded before typography', function () {
	// &#160; must act as nbsp, not as a 6-character word for hyphenation
	$out = convert('slovo&#160;dlouheslovokterehyphenacevidi neco', ast: true);
	Assert::same(convert('slovo&#160;dlouheslovokterehyphenacevidi neco', ast: false), $out);
});
