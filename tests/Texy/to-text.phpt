<?php declare(strict_types=1);

/**
 * Test: Texy::toText() - plain-text rendition of the AST
 */

use Tester\Assert;
use Texy\Texy;

require __DIR__ . '/../bootstrap.php';


test('markup is dropped, text and block structure kept', function () {
	$texy = new Texy;
	Assert::same(
		"Title\n\nHello world and code.\n",
		$texy->toText("Title\n=====\n\nHello **world** and `code`.\n"),
	);
});


test('links render their content, autolinks their address', function () {
	$texy = new Texy;
	Assert::same(
		"homepage\n\nhttps://example.com and david@example.com\n",
		$texy->toText("\"homepage\":https://example.com\n\nhttps://example.com and david@example.com"),
	);
});


test('list items and table cells', function () {
	$texy = new Texy;
	Assert::same(
		"first\nsecond\n\na\tb\nc\td\n",
		$texy->toText("- first\n- second\n\n| a | b\n| c | d"),
	);
});


test('typography artifacts are normalized back to plain text', function () {
	$texy = new Texy;
	$texy->typographyModule->locale = 'en';
	// "v lese" would get a non-breaking space; long words a soft hyphen
	$text = $texy->toText('"quoted" a word');
	Assert::same("\u{201C}quoted\u{201D} a word\n", $text);
	Assert::notContains("\u{A0}", $text);
	Assert::notContains("\u{AD}", $text);
});


test('HTML passthrough tags are dropped, their content kept', function () {
	$texy = new Texy;
	Assert::same(
		"bold and comment-free\n",
		$texy->toText('<strong>bold</strong> and <!-- gone --> comment-free'),
	);
});


test('line breaks and paragraphs', function () {
	$texy = new Texy;
	Assert::same(
		"line one\nline two\n\nnext paragraph\n",
		$texy->toText("line one\n line two\n\nnext paragraph"),
	);
});
