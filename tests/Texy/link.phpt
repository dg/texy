<?php declare(strict_types=1);

/**
 * Test: Link module features
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


// =============================================================================
// nofollow
// =============================================================================

test('link forceNoFollow', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutput->linkNoFollow = true;
	Assert::match(
		'<p><a href="https://example.com" rel="nofollow">https://example.com</a></p>
',
		$texy->toHtml('https://example.com'),
	);
});


test('link forceNoFollow does not affect relative URLs', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutput->linkNoFollow = true;
	$texy->allowedSyntax[Texy\Syntax::LinkReference] = true;
	$texy->config->addLinkDefinition('test', '/local/page');
	// Relative URL should not get nofollow
	Assert::match(
		'<p><a href="/local/page">test</a></p>
',
		$texy->toHtml('[test]'),
	);
});


// =============================================================================
// URL shortening
// =============================================================================

test('url shortening', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutput->lineWrap = 500; // disable line wrapping for this test
	// Long path should be shortened (keeps last 12 chars of path)
	// For non-www URLs, scheme is kept
	Assert::match(
		'<p><a href="https://example.com/very/long/path/to/some/page.html">https://example.com/…me/page.html</a></p>
',
		$texy->toHtml('https://example.com/very/long/path/to/some/page.html'),
	);
});


test('url shortening disabled', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutput->shortenUrls = false;
	$texy->htmlOutput->lineWrap = 500;
	Assert::match(
		'<p><a href="https://example.com/very/long/path/to/some/page.html">https://example.com/very/long/path/to/some/page.html</a></p>
',
		$texy->toHtml('https://example.com/very/long/path/to/some/page.html'),
	);
});


test('url shortening short path', function () {
	$texy = new Texy\Texy;
	// Short path should not be shortened, scheme is kept for non-www
	Assert::match(
		'<p><a href="https://example.com/page">https://example.com/page</a></p>
',
		$texy->toHtml('https://example.com/page'),
	);
});


test('url shortening with query', function () {
	$texy = new Texy\Texy;
	// Long query should be shortened to ?…, scheme kept for non-www
	Assert::match(
		'<p><a href="https://example.com/?query=long">https://example.com/?…</a></p>
',
		$texy->toHtml('https://example.com/?query=long'),
	);
});


test('url shortening www prefix', function () {
	$texy = new Texy\Texy;
	Assert::match(
		'<p><a href="http://www.example.com/page">www.example.com/page</a></p>
',
		$texy->toHtml('www.example.com/page'),
	);
});


test('soft hyphens in the source are stripped from text and link targets', function () {
	$texy = new Texy\Texy;
	Assert::match(
		"<p>slovo <a href=\"https://example.com/path\">text</a></p>\n",
		$texy->toHtml("slo\u{AD}vo \"text\":https://exam\u{AD}ple.com/pa\u{AD}th"),
	);
});


test('&shy; written in text survives as a soft hyphen', function () {
	$texy = new Texy\Texy;
	Assert::match(
		"<p>slo\u{AD}vo</p>\n",
		$texy->toHtml('slo&shy;vo'),
	);
});


test('entities in link targets are decoded once', function () {
	$texy = new Texy\Texy;
	Assert::same('<p><a href="http://a.cz/?a=1&amp;b=2">x</a></p>', trim($texy->toHtml('"x":http://a.cz/?a=1&amp;b=2')));
	Assert::same('<p><a href="http://a.cz/?a=1&amp;b=2">x</a></p>', trim($texy->toHtml("\"x\":[r]\n\n[r]: http://a.cz/?a=1&amp;b=2")));
	Assert::same('<p><a href="http://a.cz/?a=1&amp;b=2">http://a.cz/?…</a></p>', trim($texy->toHtml('http://a.cz/?a=1&amp;b=2')));
	Assert::same('<p>x <img src="a.png?x=1&amp;y=2" alt=""></p>', trim($texy->toHtml('x [* a.png?x=1&amp;y=2 *]')));
});


test('a URL takes the parentheses it opens, not the one around the link', function () {
	$texy = new Texy\Texy;
	Assert::same(
		'<p><a href="https://cs.wikipedia.org/wiki/Texy_(software)">Texy</a>.</p>',
		trim($texy->toHtml('"Texy":https://cs.wikipedia.org/wiki/Texy_(software).')),
	);
	Assert::same('<p>(viz <a href="https://example.com/x">web</a>)</p>', trim($texy->toHtml('(viz "web":https://example.com/x)')));

	Texy\Configurator::safeMode($texy);
	Assert::same('<p>klikni</p>', trim($texy->toHtml('"klikni":javascript:alert(1)')));
});


test('autolinks right after an HTML tag, an e-mail inside a link is obfuscated text', function () {
	$texy = new Texy\Texy;
	Assert::same('<p><b><a href="http://x.cz">http://x.cz</a></b></p>', trim($texy->toHtml('<b>http://x.cz</b>')));
	Assert::same('<p><b><a href="mailto:c&#64;x.cz">c&#64;<!-- -->x.cz</a></b></p>', trim($texy->toHtml('<b>c@x.cz</b>')));
	Assert::same('<p><a href="mailto:a&#64;x.cz">a&#64;<!-- -->x.cz</a></p>', trim($texy->toHtml('<a href="mailto:a@x.cz">a@x.cz</a>')));
});


test('soft hyphens are stripped from email autolinks', function () {
	$texy = new Texy\Texy;
	Assert::match(
		"<p><a href=\"mailto:info&#64;example.com\">info&#64;<!-- -->example.com</a></p>\n",
		$texy->toHtml("in\u{AD}fo@exam\u{AD}ple.com"),
	);
});
