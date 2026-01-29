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
	$texy->linkModule->forceNoFollow = true;
	Assert::match(
		'<p><a href="https://example.com" rel="nofollow">https://example.com</a></p>
',
		$texy->process('https://example.com'),
	);
});


test('link forceNoFollow does not affect relative URLs', function () {
	$texy = new Texy\Texy;
	$texy->linkModule->forceNoFollow = true;
	$texy->linkModule->addDefinition('test', '/local/page');
	// Relative URL should not get nofollow
	Assert::match(
		'<p><a href="/local/page">Local</a></p>
',
		$texy->process('"Local":[test]'),
	);
});


// =============================================================================
// URL shortening
// =============================================================================

test('url shortening', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutputModule->lineWrap = 500; // disable line wrapping for this test
	// Long path should be shortened (keeps last 12 chars of path)
	Assert::match(
		'<p><a href="https://example.com/very/long/path/to/some/page.html">https://example.com/…me/page.html</a></p>
',
		$texy->process('https://example.com/very/long/path/to/some/page.html'),
	);
});


test('url shortening disabled', function () {
	$texy = new Texy\Texy;
	$texy->autolinkModule->shorten = false;
	$texy->htmlOutputModule->lineWrap = 500;
	Assert::match(
		'<p><a href="https://example.com/very/long/path/to/some/page.html">https://example.com/very/long/path/to/some/page.html</a></p>
',
		$texy->process('https://example.com/very/long/path/to/some/page.html'),
	);
});


test('url shortening short path', function () {
	$texy = new Texy\Texy;
	// Short path should not be shortened
	Assert::match(
		'<p><a href="https://example.com/page">https://example.com/page</a></p>
',
		$texy->process('https://example.com/page'),
	);
});


test('url shortening with query', function () {
	$texy = new Texy\Texy;
	// Long query should be shortened to ?…
	Assert::match(
		'<p><a href="https://example.com/?query=long">https://example.com/?…</a></p>
',
		$texy->process('https://example.com/?query=long'),
	);
});


test('url shortening www prefix', function () {
	$texy = new Texy\Texy;
	Assert::match(
		'<p><a href="http://www.example.com/page">www.example.com/page</a></p>
',
		$texy->process('www.example.com/page'),
	);
});
