<?php declare(strict_types=1);

/**
 * Test: AST with linkBaseUrl, imageBaseUrl and lineWrap
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('linkBaseUrl prepends to relative URLs', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutput->linkBaseUrl = 'xxx/';
	Assert::same(
		"<p><a href=\"xxx/page\">link</a></p>\n",
		$texy->process('"link":page'),
	);
});


test('linkBaseUrl does not prepend to absolute URLs', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutput->linkBaseUrl = 'xxx/';
	Assert::same(
		"<p><a href=\"https://example.com\">link</a></p>\n",
		$texy->process('"link":https://example.com'),
	);
});


test('linkBaseUrl does not prepend to root-relative URLs', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutput->linkBaseUrl = 'xxx/';
	Assert::same(
		"<p><a href=\"/absolute/path\">link</a></p>\n",
		$texy->process('"link":/absolute/path'),
	);
});


test('imageBaseUrl prepends to relative image URLs', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutput->imageBaseUrl = '../images/';
	Assert::same(
		"<div class=\"figure\"><img src=\"../images/photo.jpg\" alt=\"\"></div>\n",
		$texy->process('[* photo.jpg *]'),
	);
});


test('imageBaseUrl does not prepend to absolute URLs', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutput->imageBaseUrl = '../images/';
	Assert::same(
		"<div class=\"figure\"><img src=\"https://example.com/photo.jpg\" alt=\"\"></div>\n",
		$texy->process('[* https://example.com/photo.jpg *]'),
	);
});


test('combined linkBaseUrl and imageBaseUrl', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutput->linkBaseUrl = 'links/';
	$texy->htmlOutput->imageBaseUrl = 'images/';
	Assert::same(
		"<p><a href=\"links/page\">text</a> and <img src=\"images/photo.jpg\" alt=\"\"></p>\n",
		$texy->process('"text":page and [* photo.jpg *]'),
	);
});


test('lineWrap wraps long lines', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutput->lineWrap = 40;
	Assert::same(
		"<p>Lorem ipsum dolor sit amet,\nconsectetuer adipiscing\xc2\xa0elit.</p>\n",
		$texy->process('Lorem ipsum dolor sit amet, consectetuer adipiscing elit.'),
	);
});


test('lineWrap preserves short lines', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutput->lineWrap = 80;
	Assert::same(
		"<p>Short text.</p>\n",
		$texy->process('Short text.'),
	);
});


test('lineWrap uses htmlOutput setting', function () {
	$texy = new Texy\Texy;
	Assert::same(
		"<p>Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Curabitur turpis\nenim, placerat tincidunt.</p>\n",
		$texy->process('Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Curabitur turpis enim, placerat tincidunt.'),
	);
});
